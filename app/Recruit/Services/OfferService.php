<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Services;

use CorporateAid\Core\Auth;
use CorporateAid\Platform\Modules\EventBus;
use CorporateAid\Platform\Modules\Events\PersonHiredEvent;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Platform\Support\EmailService;
use CorporateAid\Recruit\Models\Offer;
use CorporateAid\Recruit\Repositories\ApplicationRepository;
use CorporateAid\Recruit\Repositories\OfferRepository;
use CorporateAid\Recruit\Repositories\CandidateRepository;
use CorporateAid\Recruit\Repositories\VacancyRepository;
use CorporateAid\Recruit\Repositories\VacancyStageRepository;

final class OfferService
{
    public function __construct(
        private OfferRepository $offers,
        private ApplicationRepository $applications,
        private VacancyStageRepository $stages,
        private CandidateRepository $candidates,
        private VacancyRepository $vacancies,
        private ActivityLogger $activity,
        private EmailService $mailer,
        private ?Auth $auth = null,
        private ?EventBus $events = null
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

        // Fire the offer email (best-effort: status change already committed)
        $application = $this->applications->findById($offer->applicationId);
        if ($application !== null) {
            $candidate = $this->candidates->findById($application->candidateId);
            $vacancy = $this->vacancies->findById($application->vacancyId);
            if ($candidate !== null && $vacancy !== null && $candidate->email !== '') {
                $appUrl = app_url();
                $authUser = $this->auth?->user();
                $senderName = $authUser !== null ? $authUser->name : 'The team';
                $this->mailer->sendDbTemplate(
                    $candidate->email,
                    'offer.sent',
                    [
                        'candidate_first_name' => $candidate->firstName,
                        'vacancy_title'        => $vacancy->title,
                        'client_name'          => app_setting_string('branding.app_name', 'Recruit Aid'),
                        'salary'               => $offer->salaryFormatted(),
                        'start_date'           => $offer->startDate,
                        'expires_at'           => $offer->expiresAt,
                        'offer_url'            => app_url('/offers/' . $offer->code),
                        'sender_name'          => $senderName,
                        'product_name'         => app_setting_string('branding.app_name', 'Recruit Aid'),
                    ]
                );
            }
        }

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

        // Close the hire loop: application → hired, candidate → hired,
        // vacancy → filled if positions_count reached.
        $this->finalizeHire($offer->applicationId);

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
    /**
     * On offer acceptance: mark the application hired, mark the
     * candidate hired, and if the vacancy's positions_count is
     * reached, mark the vacancy filled.
     */
    private function finalizeHire(int $applicationId): void
    {
        $application = $this->applications->findById($applicationId);
        if ($application === null) return;

        $candidate = $this->candidates->findById($application->candidateId);
        if ($candidate === null) return;

        // 1. Move the application to the terminal 'Hired' stage (if any)
        $stages = $this->stages->forVacancy($application->vacancyId);
        $hiredStage = null;
        foreach ($stages as $s) {
            if ($s->keySlug === 'hired' || ($s->isTerminal && strtolower($s->label) === 'hired')) {
                $hiredStage = $s;
                break;
            }
        }
        if ($hiredStage !== null && $application->currentStageId !== $hiredStage->id) {
            $fromStageId = $application->currentStageId;
            $this->applications->updateStage($applicationId, $hiredStage->id);
            $this->applications->recordStageMove($applicationId, $fromStageId, $hiredStage->id, $this->auth?->user()?->id, 'Moved to Hired on offer acceptance');
        }

        // 2. Mark the application as hired (status)
        if ($application->status !== 'hired') {
            $this->applications->updateStatus($applicationId, 'hired');
        }

        // 3. Mark the candidate as hired
        if ($candidate->status !== 'hired') {
            $this->candidates->update($candidate->id, ['status' => 'hired']);
        }

        // 4. Dispatch the cross-module event
        if ($candidate->personId !== null) {
            $user = $this->auth?->user();
            $tenantId = $user->tenantId ?? 1;
            $this->events?->dispatch(PersonHiredEvent::TYPE, [
                'tenant_id'    => $tenantId,
                'person_id'    => $candidate->personId,
                'candidate_id' => $candidate->id
            ]);
        }
    }

}
