<?php
declare(strict_types=1);

namespace CorporateAid\Modules\Recruit;

use CorporateAid\Core\Container;
use CorporateAid\Core\Database;
use CorporateAid\Core\PDOParams;
use CorporateAid\Platform\Modules\Contracts\ModuleContract;

final class Module implements ModuleContract
{
    public function getKey(): string { return 'recruit'; }
    public function getName(): string { return 'Recruit Aid'; }
    public function getVersion(): string { return '0.6.0-alpha'; }
    public function getMigrations(): array { return []; }
    public function getPermissions(): array { return []; }
    public function getEvents(): array { return []; }
    
    /**
     * Recruit Aid owns every recruit.* permission. The slug list here
     * mirrors what core_permissions already contains after the
     * migrations; PermissionRegistry upserts them into
     * core_permission_registrations so upgrades can add new ones.
     */
    public function getPermissionDefinitions(): array
    {
        return [
            ['slug' => 'recruit.organisations.view',   'name' => 'View Organisations',    'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.organisations.create', 'name' => 'Create Organisations',  'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.organisations.edit',   'name' => 'Edit Organisations',    'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.organisations.delete', 'name' => 'Delete Organisations',  'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.contacts.view',        'name' => 'View Contacts',         'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.contacts.create',      'name' => 'Create Contacts',       'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.contacts.edit',        'name' => 'Edit Contacts',         'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.contacts.delete',      'name' => 'Delete Contacts',       'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.engagements.view',     'name' => 'View Engagements',      'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.engagements.create',   'name' => 'Create Engagements',    'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.engagements.edit',     'name' => 'Edit Engagements',      'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.engagements.delete',   'name' => 'Delete Engagements',    'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.vacancies.view',       'name' => 'View Vacancies',        'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.vacancies.create',     'name' => 'Create Vacancies',      'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.vacancies.edit',       'name' => 'Edit Vacancies',        'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.vacancies.close',      'name' => 'Close Vacancies',       'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.candidates.view',      'name' => 'View Candidates',       'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.candidates.create',    'name' => 'Create Candidates',     'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.candidates.edit',      'name' => 'Edit Candidates',       'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.candidates.delete',    'name' => 'Delete Candidates',     'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.pipeline.advance',     'name' => 'Advance Pipeline',      'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.interviews.view',      'name' => 'View Interviews',       'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.interviews.create',    'name' => 'Schedule Interviews',   'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.interviews.edit',      'name' => 'Edit Interviews',       'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.interviews.delete',    'name' => 'Cancel Interviews',     'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.scorecards.submit',    'name' => 'Submit Scorecards',     'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.offers.view',          'name' => 'View Offers',           'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.offers.create',        'name' => 'Create Offers',         'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.offers.edit',          'name' => 'Edit Offers',           'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.offers.send',          'name' => 'Send Offers',           'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.offers.respond',       'name' => 'Respond to Offers',     'description' => '', 'sensitivity' => 'public'],
            ['slug' => 'recruit.reports.view',         'name' => 'View Reports',          'description' => '', 'sensitivity' => 'public'],
        ];
    }
    
    public function getDashboardMetrics(int $tenantId, Container $c): array
    {
        /** @var Database $db */
        $db = $c->get(Database::class);
        
        $candidates   = $db->scalarInt("SELECT COUNT(*) FROM `recruit_candidates` WHERE `tenant_id` = ? AND `deleted_at` IS NULL", PDOParams::positional([$tenantId]));
        $vacancies    = $db->scalarInt("SELECT COUNT(*) FROM `recruit_vacancies` WHERE `tenant_id` = ? AND `deleted_at` IS NULL", PDOParams::positional([$tenantId]));
        $applications = $db->scalarInt("SELECT COUNT(*) FROM `recruit_applications` WHERE `tenant_id` = ?", PDOParams::positional([$tenantId]));

        return [
            'Total Candidates' => $candidates,
            'Active Vacancies' => $vacancies,
            'Applications'     => $applications,
        ];
    }
    
    public function register(Container $c): void {}
}