<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Recruit\Models\Offer;
use CorporateAid\Recruit\Repositories\ApplicationRepository;
use CorporateAid\Recruit\Repositories\OfferRepository;
use CorporateAid\Recruit\Repositories\VacancyStageRepository;

final class OfferService
{
    public function __construct(
        private OfferRepository $offers,
        private ApplicationRepository $applications,
        private VacancyStageRepository $stages,
        private ActivityLogger $activity,
        private ?Auth $auth = null
    ) {}

    /**
     * Create a new draft offer against an application.
     *
     * @param array<string, mixed> $input
     * @return array{id:int, offer:?Offer, errors:string[]}
     */
    public function create(int $applicationId, array $input): array
    {
        $application = $this->applications->findById($applicationId);
        if ($application === null) {
            return ['id' => 0, 'offer' => null, 'errors' => ['Application not found.']];
        }

        // One active offer per application (no replacing accepted/declined)
        $existing = $this->offers->findByApplication($applicationId);
        if ($existing !== null && $existing->isActive()) {
            return ['id' => 0, 'offer' => null, 'errors' => [
                'This application already has an active offer (' . $existing->code . '). Close or withdraw it first.',
            ]];
        }

        $errors = [];
        $salary = isset($input['offered_salary']) && is_numeric($input['offered_salary'])
            ? (float) $input['offered_salary'] : 0.0;
        if ($salary <= 0) {
            $errors[] = 'Offered salary must be greater than zero.';
        }

        $currency = isset($input['currency']) && is_scalar($input['currency'])
            ? strtoupper(trim((string) $input['currency'])) : '';
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors[] = 'Currency must be a 3-letter ISO code (e.g. KES, USD).';
        }

        $startDate = isset($input['start_date']) && is_scalar($input['start_date'])
            ? trim((string) $input['start_date']) : '';
        if ($startDate === '' || strtotime($startDate) === false) {
            $errors[] = 'Start date is required.';
        }

        $expiresAt = isset($input['expires_at']) && is_scalar($input['expires_at'])
            ? trim((string) $input['expires_at']) : '';
        if ($expiresAt === '' || strtotime($expiresAt) === false) {
            $errors[] = 'Expiry date is required.';
        } elseif ($startDate !== '' && strtotime($expiresAt) < strtotime($startDate)) {
            $errors[] = 'Expiry date must be on or after the start date.';
        }

        if (!empty($errors)) {
            return ['id' => 0, 'offer' => null, 'errors' => $errors];
        }

        $id = $this->offers->create([
            'code'           => $this->offers->nextCode(),
            'application_id' => $applicationId,
            'offered_salary' => $salary,
            'currency'       => $currency,
            'start_date'     => $startDate,
            'expires_at'     => $expiresAt,
            'status'         => 'draft',
            'notes'          => isset($input['notes']) && is_scalar($input['notes'])
                ? trim((string) $input['notes']) : null,
            'created_by'     => $this->auth?->user()?->id,
        ]);

        $offer = $this->offers->findById($id);
        if ($offer === null) {
            return ['id' => $id, 'offer' => null, 'errors' => ['Offer created but could not be retrieved.']];
        }

        $this->offers->recordHistory($id, null, 'draft', $this->auth?->user()?->id, 'Offer created');

        $this->activity->log(
            'offer.created',
            'offers',
            'Created offer ' . $offer->code . ' for application ' . $application->code,
            'offer',
            $id
        );

        return ['id' => $id, 'offer' => $offer, 'errors' => []];
    }

    /** @return array{success: bool, error: ?string} */
    public function send(int $offerId): array
    {
        $offer = $this->offers->findById($offerId);
        if ($offer === null) return ['success' => false, 'error' => 'Offer not found.'];
        if ($offer->status !== 'draft') return ['success' => false, 'error' => 'Only draft offers can be sent.'];
        if (strtotime($offer->expiresAt) < time()) {
            return ['success' => false, 'error' => 'This offer has already expired.'];
        }

        $this->offers->updateStatus($offerId, 'sent');
        $this->offers->recordHistory($offerId, 'draft', 'sent', $this->auth?->user()?->id);

        $this->activity->log(
            'offer.sent',
            'offers',
            'Sent offer ' . $offer->code . ' to candidate',
            'offer',
            $offerId
        );

        return ['success' => true, 'error' => null];
    }

    /** @return array{success: bool, error: ?string} */
    public function accept(int $offerId, ?string $responseNotes = null): array
    {
        $offer = $this->offers->findById($offerId);
        if ($offer === null) return ['success' => false, 'error' => 'Offer not found.'];
        if ($offer->status !== 'sent') return ['success' => false, 'error' => 'Only sent offers can be accepted.'];

        $this->offers->updateStatus($offerId, 'accepted', $responseNotes);
        $this->offers->recordHistory($offerId, 'sent', 'accepted', $this->auth?->user()?->id, $responseNotes);

        // Move application to the terminal 'Hired' stage if it exists
        $this->moveApplicationToHired($offer->applicationId);

        $this->activity->log(
            'offer.accepted',
            'offers',
            'Candidate accepted offer ' . $offer->code,
            'offer',
            $offerId
        );

        return ['success' => true, 'error' => null];
    }

    /** @return array{success: bool, error: ?string} */
    public function decline(int $offerId, ?string $responseNotes = null): array
    {
        $offer = $this->offers->findById($offerId);
        if ($offer === null) return ['success' => false, 'error' => 'Offer not found.'];
        if ($offer->status !== 'sent') return ['success' => false, 'error' => 'Only sent offers can be declined.'];

        $this->offers->updateStatus($offerId, 'declined', $responseNotes);
        $this->offers->recordHistory($offerId, 'sent', 'declined', $this->auth?->user()?->id, $responseNotes);

        $this->activity->log(
            'offer.declined',
            'offers',
            'Candidate declined offer ' . $offer->code,
            'offer',
            $offerId
        );

        return ['success' => true, 'error' => null];
    }

    /** @return array{success: bool, error: ?string} */
    public function withdraw(int $offerId, ?string $notes = null): array
    {
        $offer = $this->offers->findById($offerId);
        if ($offer === null) return ['success' => false, 'error' => 'Offer not found.'];
        if ($offer->isTerminal()) return ['success' => false, 'error' => 'This offer has already been closed.'];

        $this->offers->updateStatus($offerId, 'withdrawn', $notes);
        $this->offers->recordHistory($offerId, $offer->status, 'withdrawn', $this->auth?->user()?->id, $notes);

        $this->activity->log(
            'offer.withdrawn',
            'offers',
            'Withdrew offer ' . $offer->code,
            'offer',
            $offerId
        );

        return ['success' => true, 'error' => null];
    }

    /**
     * Auto-expire sent offers whose expiry date has passed.
     * Intended to be called from a cron / scheduled task.
     *
     * @return int Number of offers expired
     */
    public function expireStale(): int
    {
        $pending = $this->offers->pendingExpiry();
        $count = 0;
        foreach ($pending as $offer) {
            $this->offers->updateStatus($offer->id, 'expired');
            $this->offers->recordHistory($offer->id, 'sent', 'expired', null, 'Auto-expired');
            $this->activity->log(
                'offer.expired',
                'offers',
                'Offer ' . $offer->code . ' auto-expired',
                'offer',
                $offer->id
            );
            $count++;
        }
        return $count;
    }

    /**
     * Move the application to the 'Hired' terminal stage, if the
     * vacancy has such a stage defined.
     */
    private function moveApplicationToHired(int $applicationId): void
    {
        $application = $this->applications->findById($applicationId);
        if ($application === null) return;

        $stages = $this->stages->forVacancy($application->vacancyId);
        $hired = null;
        foreach ($stages as $s) {
            if ($s->keySlug === 'hired' || ($s->isTerminal && strtolower($s->label) === 'hired')) {
                $hired = $s;
                break;
            }
        }
        if ($hired === null) return;

        // Only move if not already there
        if ($application->currentStageId === $hired->id) return;

        $fromStageId = $application->currentStageId;
        $this->applications->updateStage($applicationId, $hired->id);
        $this->applications->recordStageMove($applicationId, $fromStageId, $hired->id, $this->auth?->user()?->id, 'Moved to Hired on offer acceptance');
    }
}
