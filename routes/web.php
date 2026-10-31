<?php
declare(strict_types=1);

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Router;
use CorporateAid\Recruit\Controllers\AccountController;
use CorporateAid\Recruit\Controllers\Admin\AdminHomeController;
use CorporateAid\Recruit\Controllers\Admin\CustomFieldController;
use CorporateAid\Recruit\Controllers\Admin\RoleController;
use CorporateAid\Recruit\Controllers\Admin\LookupController;
use CorporateAid\Recruit\Controllers\Admin\BrandingController;
use CorporateAid\Recruit\Controllers\Admin\AdminSettingsController;
use CorporateAid\Recruit\Controllers\Admin\AdminNotificationRuleController;
use CorporateAid\Recruit\Controllers\Admin\AdminUserController;
use CorporateAid\Recruit\Controllers\DashboardController;
use CorporateAid\Recruit\Controllers\HomeController;
use CorporateAid\Recruit\Controllers\AuthController;
use CorporateAid\Platform\Controllers\PortalController;
use CorporateAid\Recruit\Controllers\NotificationController;
use CorporateAid\Recruit\Controllers\OrganisationController;
use CorporateAid\Recruit\Controllers\ContactController;
use CorporateAid\Recruit\Controllers\ActivityController;
use CorporateAid\Recruit\Controllers\GlobalActivityController;
use CorporateAid\Recruit\Controllers\GlobalSearchController;
use CorporateAid\Recruit\Controllers\LiveSearchController;
use CorporateAid\Recruit\Controllers\ComingSoonController;
use CorporateAid\Recruit\Controllers\EngagementController;
use CorporateAid\Recruit\Controllers\FileController;
use CorporateAid\Recruit\Controllers\VacancyController;
use CorporateAid\Recruit\Controllers\CandidateController;
use CorporateAid\Recruit\Controllers\ApplicationController;
use CorporateAid\Recruit\Controllers\InterviewController;
use CorporateAid\Recruit\Controllers\ScorecardController;
use CorporateAid\Recruit\Controllers\OfferController;
use CorporateAid\Recruit\Controllers\ReportController;
use CorporateAid\Recruit\Controllers\ExportController;

/** @var Router $router */

$router->get('/',       [PortalController::class, 'index'], ['auth']);

// ---------- Auth (Root) ----------
$router->get('/login',  [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']); // Allow GET for convenience
$router->post('/logout',[AuthController::class, 'logout']);

$router->get('/health', [HomeController::class, 'health']);

// ---------- Coming Soon (planned features) ----------
// These render a branded "Coming Soon" page instead of a 404.
// When a feature ships, replace the route with the real controller.

$router->get('/recruit/tasks',        [ComingSoonController::class, 'tasks'],        ['auth']);
$router->get('/recruit/calendar',     [ComingSoonController::class, 'calendar'],     ['auth']);
$router->get('/recruit/documents',    [ComingSoonController::class, 'documents'],    ['auth']);
$router->get('/recruit/admin/audit',  [ComingSoonController::class, 'audit'],        ['auth', 'permission:core.settings.manage']);

$router->get('/recruit/search', [GlobalSearchController::class, 'index'], ['auth']);

$router->get('/recruit/dashboard', [DashboardController::class, 'index'], ['auth']);

$router->get('/recruit/account',           [AccountController::class, 'index'],              ['auth']);
$router->get('/recruit/account/password',  [AccountController::class, 'showChangePassword'], ['auth']);
$router->post('/recruit/account/password', [AccountController::class, 'changePassword'],     ['auth', 'csrf']);
$router->post('/recruit/account/theme',    [AccountController::class, 'setTheme'],           ['auth', 'csrf']);
$router->get('/recruit/account/confirm-password',  [AccountController::class, 'showConfirmPassword'], ['auth']);
$router->post('/recruit/account/confirm-password', [AccountController::class, 'confirmPassword'],     ['auth', 'csrf', 'ratelimit:confirm_password']);

// ---------------------------------------------------------------------
// Organisations
// ---------------------------------------------------------------------
$router->get('/recruit/organisations',                 [OrganisationController::class, 'index'],      ['auth', 'permission:recruit.organisations.view']);
$router->get('/recruit/organisations/create',          [OrganisationController::class, 'showCreate'], ['auth', 'permission:recruit.organisations.create']);
$router->post('/recruit/organisations',                [OrganisationController::class, 'store'],      ['auth', 'permission:recruit.organisations.create', 'csrf']);
$router->get('/recruit/organisations/{code}',          [OrganisationController::class, 'show'],       ['auth', 'permission:recruit.organisations.view']);
$router->get('/recruit/organisations/{code}/edit',     [OrganisationController::class, 'showEdit'],   ['auth', 'permission:recruit.organisations.edit']);
$router->put('/recruit/organisations/{code}',          [OrganisationController::class, 'update'],     ['auth', 'permission:recruit.organisations.edit', 'csrf']);
$router->post('/recruit/organisations/{code}/archive', [OrganisationController::class, 'archive'],    ['auth', 'permission:recruit.organisations.delete', 'csrf']);
$router->post('/recruit/organisations/{code}/restore', [OrganisationController::class, 'restore'],    ['auth', 'permission:recruit.organisations.delete', 'csrf']);
$router->post('/recruit/organisations/{code}/delete',  [OrganisationController::class, 'destroy'],    ['auth', 'permission:recruit.organisations.delete', 'csrf']);
$router->get('/recruit/organisations/{code}/delete-info',  [OrganisationController::class, 'deleteInfo'],  ['auth', 'permission:recruit.organisations.delete']);

// ---------------------------------------------------------------------
// Organisation Contacts
// ---------------------------------------------------------------------
$router->get('/recruit/contacts', [ContactController::class, 'showGlobalList'], ['auth', 'permission:recruit.contacts.view']);
$router->get('/recruit/organisations/{code}/contacts',                          [ContactController::class, 'index'],       ['auth', 'permission:recruit.contacts.view']);
$router->get('/recruit/organisations/{code}/contacts/create',                   [ContactController::class, 'showCreate'],  ['auth', 'permission:recruit.contacts.create']);
$router->post('/recruit/organisations/{code}/contacts',                         [ContactController::class, 'store'],       ['auth', 'permission:recruit.contacts.create', 'csrf']);
$router->get('/recruit/organisations/{code}/contacts/{contactCode}',            [ContactController::class, 'show'],        ['auth', 'permission:recruit.contacts.view']);
$router->get('/recruit/organisations/{code}/contacts/{contactCode}/edit',       [ContactController::class, 'showEdit'],    ['auth', 'permission:recruit.contacts.edit']);
$router->put('/recruit/organisations/{code}/contacts/{contactCode}',            [ContactController::class, 'update'],      ['auth', 'permission:recruit.contacts.edit', 'csrf']);
$router->post('/recruit/organisations/{code}/contacts/{contactCode}/archive',   [ContactController::class, 'archive'],     ['auth', 'permission:recruit.contacts.delete', 'csrf']);
$router->post('/recruit/organisations/{code}/contacts/{contactCode}/restore',   [ContactController::class, 'restore'],     ['auth', 'permission:recruit.contacts.delete', 'csrf']);
$router->post('/recruit/organisations/{code}/contacts/{contactCode}/delete',    [ContactController::class, 'destroy'],     ['auth', 'permission:recruit.contacts.delete', 'csrf']);
$router->get('/recruit/organisations/{code}/contacts/{contactCode}/delete-info',[ContactController::class, 'deleteInfo'],  ['auth', 'permission:recruit.contacts.delete']);

// ---------------------------------------------------------------------
// Engagements
// ---------------------------------------------------------------------
$router->get('/recruit/engagements',                             [EngagementController::class, 'index'],       ['auth', 'permission:recruit.engagements.view']);
$router->get('/recruit/engagements/create',                      [EngagementController::class, 'showCreate'],  ['auth', 'permission:recruit.engagements.create']);
$router->post('/recruit/engagements',                            [EngagementController::class, 'store'],       ['auth', 'permission:recruit.engagements.create', 'csrf']);
$router->get('/recruit/engagements/{code}',                      [EngagementController::class, 'show'],        ['auth', 'permission:recruit.engagements.view']);
$router->get('/recruit/engagements/{code}/edit',                 [EngagementController::class, 'showEdit'],    ['auth', 'permission:recruit.engagements.edit']);
$router->put('/recruit/engagements/{code}',                      [EngagementController::class, 'update'],      ['auth', 'permission:recruit.engagements.edit', 'csrf']);
$router->post('/recruit/engagements/{code}/archive',             [EngagementController::class, 'archive'],     ['auth', 'permission:recruit.engagements.delete', 'csrf']);
$router->post('/recruit/engagements/{code}/restore',             [EngagementController::class, 'restore'],     ['auth', 'permission:recruit.engagements.delete', 'csrf']);
$router->post('/recruit/engagements/{code}/delete',              [EngagementController::class, 'destroy'],     ['auth', 'permission:recruit.engagements.delete', 'csrf']);
$router->get('/recruit/engagements/{code}/delete-info',          [EngagementController::class, 'deleteInfo'],  ['auth', 'permission:recruit.engagements.delete']);
$router->post('/recruit/engagements/{code}/recruiters/assign',   [EngagementController::class, 'assignRecruiter'],   ['auth', 'permission:recruit.engagements.edit', 'csrf']);
$router->post('/recruit/engagements/{code}/recruiters/unassign', [EngagementController::class, 'unassignRecruiter'], ['auth', 'permission:recruit.engagements.edit', 'csrf']);

// ---------------------------------------------------------------------
// Vacancies
// ---------------------------------------------------------------------
$router->get('/recruit/vacancies',                        [VacancyController::class, 'index'],       ['auth', 'permission:recruit.vacancies.view']);
$router->get('/recruit/vacancies/create',                 [VacancyController::class, 'showCreate'],  ['auth', 'permission:recruit.vacancies.create']);
$router->post('/recruit/vacancies',                       [VacancyController::class, 'store'],       ['auth', 'permission:recruit.vacancies.create', 'csrf']);
$router->get('/recruit/vacancies/{code}',                 [VacancyController::class, 'show'],        ['auth', 'permission:recruit.vacancies.view']);
$router->get('/recruit/vacancies/{code}/edit',            [VacancyController::class, 'showEdit'],    ['auth', 'permission:recruit.vacancies.edit']);
$router->put('/recruit/vacancies/{code}',                 [VacancyController::class, 'update'],      ['auth', 'permission:recruit.vacancies.edit', 'csrf']);
$router->post('/recruit/vacancies/{code}/archive',        [VacancyController::class, 'archive'],     ['auth', 'permission:recruit.vacancies.close', 'csrf']);
$router->post('/recruit/vacancies/{code}/restore',        [VacancyController::class, 'restore'],     ['auth', 'permission:recruit.vacancies.close', 'csrf']);
$router->post('/recruit/vacancies/{code}/delete',         [VacancyController::class, 'destroy'],     ['auth', 'permission:recruit.vacancies.close', 'csrf']);
$router->get('/recruit/vacancies/{code}/delete-info',     [VacancyController::class, 'deleteInfo'],  ['auth', 'permission:recruit.vacancies.close']);

// ---------------------------------------------------------------------
// Applications (pipeline)
// ---------------------------------------------------------------------
$router->get('/recruit/applications',                    [ApplicationController::class, 'index'],            ['auth', 'permission:recruit.vacancies.view']);

$router->get('/recruit/vacancies/{code}/add-candidate',  [ApplicationController::class, 'addCandidateForm'], ['auth', 'permission:recruit.vacancies.edit']);
$router->post('/recruit/vacancies/{code}/add-candidate', [ApplicationController::class, 'addCandidate'],     ['auth', 'permission:recruit.vacancies.edit', 'csrf']);
$router->post('/recruit/applications/{code}/move',       [ApplicationController::class, 'move'],             ['auth', 'permission:recruit.pipeline.advance', 'csrf']);

// ---------------------------------------------------------------------
// Interviews & Scorecards
// ---------------------------------------------------------------------
$router->get('/recruit/interviews',                              [InterviewController::class, 'index'],         ['auth', 'permission:recruit.interviews.view']);
$router->get('/recruit/applications/{code}/schedule-interview',  [InterviewController::class, 'scheduleForm'],  ['auth', 'permission:recruit.interviews.create']);
$router->post('/recruit/applications/{code}/schedule-interview', [InterviewController::class, 'store'],         ['auth', 'permission:recruit.interviews.create', 'csrf']);
$router->get('/recruit/interviews/{code}',                       [InterviewController::class, 'show'],          ['auth', 'permission:recruit.interviews.view']);
$router->post('/recruit/interviews/{code}/complete',             [InterviewController::class, 'complete'],      ['auth', 'permission:recruit.interviews.edit', 'csrf']);
$router->post('/recruit/interviews/{code}/cancel',               [InterviewController::class, 'cancel'],        ['auth', 'permission:recruit.interviews.delete', 'csrf']);
$router->get('/recruit/interviews/{code}/scorecard',             [ScorecardController::class, 'form'],          ['auth', 'permission:recruit.scorecards.submit']);
$router->post('/recruit/interviews/{code}/scorecard',            [ScorecardController::class, 'submit'],        ['auth', 'permission:recruit.scorecards.submit', 'csrf']);

// ---------------------------------------------------------------------
// Offers
// ---------------------------------------------------------------------
$router->get('/recruit/offers',                                 [OfferController::class, 'index'],       ['auth', 'permission:recruit.offers.view']);
$router->get('/recruit/applications/{code}/make-offer',         [OfferController::class, 'createForm'],  ['auth', 'permission:recruit.offers.create']);
$router->post('/recruit/applications/{code}/make-offer',        [OfferController::class, 'store'],       ['auth', 'permission:recruit.offers.create', 'csrf']);
$router->get('/recruit/offers/{code}',                          [OfferController::class, 'show'],        ['auth', 'permission:recruit.offers.view']);
$router->post('/recruit/offers/{code}/send',                    [OfferController::class, 'send'],        ['auth', 'permission:recruit.offers.send', 'csrf']);
$router->post('/recruit/offers/{code}/accept',                  [OfferController::class, 'accept'],      ['auth', 'permission:recruit.offers.respond', 'csrf']);
$router->post('/recruit/offers/{code}/decline',                 [OfferController::class, 'decline'],     ['auth', 'permission:recruit.offers.respond', 'csrf']);
$router->post('/recruit/offers/{code}/withdraw',                [OfferController::class, 'withdraw'],    ['auth', 'permission:recruit.offers.edit', 'csrf']);

// ---------------------------------------------------------------------
// Reports
// ---------------------------------------------------------------------
$router->get('/recruit/reports',                       [ReportController::class, 'index'],       ['auth', 'permission:recruit.reports.view']);
$router->get('/recruit/reports/funnel',                [ReportController::class, 'funnel'],      ['auth', 'permission:recruit.reports.view']);
$router->get('/recruit/reports/time-to-hire',          [ReportController::class, 'timeToHire'],  ['auth', 'permission:recruit.reports.view']);
$router->get('/recruit/reports/productivity',          [ReportController::class, 'productivity'],['auth', 'permission:recruit.reports.view']);
$router->get('/recruit/reports/time-to-hire.csv',      [ReportController::class, 'exportTimeToHire'], ['auth', 'permission:recruit.reports.view']);

// ---------------------------------------------------------------------
// Universal list export
// ---------------------------------------------------------------------
$router->get('/recruit/{entity}/export.csv', [ExportController::class, 'export'], ['auth']);






// ---------------------------------------------------------------------
// Candidates
// ---------------------------------------------------------------------
$router->get('/recruit/candidates',                       [CandidateController::class, 'index'],      ['auth', 'permission:recruit.candidates.view']);
$router->get('/recruit/candidates/create',                [CandidateController::class, 'showCreate'], ['auth', 'permission:recruit.candidates.create']);
$router->post('/recruit/candidates',                      [CandidateController::class, 'store'],      ['auth', 'permission:recruit.candidates.create', 'csrf']);
$router->get('/recruit/candidates/{code}',                [CandidateController::class, 'show'],       ['auth', 'permission:recruit.candidates.view']);
$router->get('/recruit/candidates/{code}/edit',           [CandidateController::class, 'showEdit'],   ['auth', 'permission:recruit.candidates.edit']);
$router->put('/recruit/candidates/{code}',                [CandidateController::class, 'update'],     ['auth', 'permission:recruit.candidates.edit', 'csrf']);
$router->post('/recruit/candidates/{code}/archive',       [CandidateController::class, 'archive'],    ['auth', 'permission:recruit.candidates.delete', 'csrf']);
$router->post('/recruit/candidates/{code}/restore',       [CandidateController::class, 'restore'],    ['auth', 'permission:recruit.candidates.delete', 'csrf']);


// ---------------------------------------------------------------------
// Files
// ---------------------------------------------------------------------
// Files are served through PHP with permission checks derived from
// the file's linked entities. The route requires 'auth' only; the
// fine-grained permission check happens inside FileController::serve()
// because permission depends on which entity the file is attached to.
$router->get('/recruit/files/{uuid}', [FileController::class, 'serve'], ['auth']);

// ---------------------------------------------------------------------
// Administration
// ---------------------------------------------------------------------
$router->get('/recruit/admin', [AdminHomeController::class, 'index'], ['auth']);

// User accounts
$router->get('/recruit/admin/users',              [AdminUserController::class, 'index'],  ['auth', 'permission:core.users.view']);
$router->get('/recruit/admin/users/create',       [AdminUserController::class, 'create'], ['auth', 'permission:core.users.create']);
$router->post('/recruit/admin/users',             [AdminUserController::class, 'store'],  ['auth', 'permission:core.users.create', 'csrf']);
$router->get('/recruit/admin/users/{id}/edit',    [AdminUserController::class, 'edit'],   ['auth', 'permission:core.users.edit']);
$router->put('/recruit/admin/users/{id}',         [AdminUserController::class, 'update'], ['auth', 'permission:core.users.edit', 'csrf']);

// Roles & permissions
$router->get('/recruit/admin/roles-perm',                 [RoleController::class, 'home'],    ['auth', 'permission:core.settings.manage']);
$router->get('/recruit/admin/roles-perm/roles',           [RoleController::class, 'index'],   ['auth', 'permission:core.settings.manage']);
$router->get('/recruit/admin/roles-perm/roles/create',    [RoleController::class, 'create'],  ['auth', 'permission:core.settings.manage']);
$router->post('/recruit/admin/roles-perm/roles',          [RoleController::class, 'store'],   ['auth', 'permission:core.settings.manage', 'csrf']);
$router->get('/recruit/admin/roles-perm/roles/{id}/edit', [RoleController::class, 'edit'],    ['auth', 'permission:core.settings.manage']);
$router->put('/recruit/admin/roles-perm/roles/{id}',      [RoleController::class, 'update'],  ['auth', 'permission:core.settings.manage', 'csrf']);
$router->delete('/recruit/admin/roles-perm/roles/{id}',   [RoleController::class, 'destroy'], ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/recruit/admin/roles-perm/roles/{id}/users/{userId}/remove', [RoleController::class, 'removeUser'], ['auth', 'permission:core.settings.manage', 'csrf']);
$router->get('/recruit/admin/roles-perm/permissions',     [RoleController::class, 'permissions'], ['auth', 'permission:core.settings.manage']);
$router->post('/recruit/admin/roles-perm/permissions',    [RoleController::class, 'savePermissions'], ['auth', 'permission:core.settings.manage', 'csrf']);

// Legacy redirect
$router->get('/recruit/admin/roles', function (Request $req, array $params): Response {
    return Response::redirect('/admin/roles-perm');
}, ['auth', 'permission:core.settings.manage']);

// Custom fields (field builder)
$router->get('/recruit/admin/lookups',                          [LookupController::class, 'index'],  ['auth', 'permission:core.settings.manage']);
$router->get('/recruit/admin/lookups/{group}',                    [LookupController::class, 'show'],   ['auth', 'permission:core.settings.manage']);
$router->get('/recruit/admin/lookups/{group}/create',             [LookupController::class, 'create'], ['auth', 'permission:core.settings.manage']);
$router->post('/recruit/admin/lookups/{group}',                   [LookupController::class, 'store'],  ['auth', 'permission:core.settings.manage', 'csrf']);
$router->get('/recruit/admin/lookups/{group}/{id}/edit',          [LookupController::class, 'edit'],   ['auth', 'permission:core.settings.manage']);
$router->put('/recruit/
admin/lookups/{group}/{id}',               [LookupController::class, 'update'], ['auth', 'permission:core.settings.manage', 'csrf']);
$router->delete('/recruit/admin/lookups/{group}/{id}',            [LookupController::class, 'destroy'],['auth', 'permission:core.settings.manage', 'csrf']);
$router->get('/recruit/admin/custom-fields',                      [CustomFieldController::class, 'index'],      ['auth', 'permission:core.settings.manage']);
$router->get('/recruit/admin/custom-fields/{entity}',             [CustomFieldController::class, 'listFor'],    ['auth', 'permission:core.settings.manage']);
$router->get('/recruit/admin/custom-fields/{entity}/create',      [CustomFieldController::class, 'showCreate'], ['auth', 'permission:core.settings.manage']);
$router->post('/recruit/admin/custom-fields/{entity}',            [CustomFieldController::class, 'store'],      ['auth', 'permission:core.settings.manage', 'csrf']);
$router->get('/recruit/admin/custom-fields/{entity}/{id}/edit',   [CustomFieldController::class, 'showEdit'],   ['auth', 'permission:core.settings.manage']);
$router->post('/recruit/admin/custom-fields/{entity}/{id}',       [CustomFieldController::class, 'update'],     ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/recruit/admin/custom-fields/{entity}/{id}/archive', [CustomFieldController::class, 'archive'],  ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/recruit/admin/custom-fields/{entity}/{id}/restore', [CustomFieldController::class, 'restore'],  ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/recruit/admin/custom-fields/{entity}/{id}/delete',  [CustomFieldController::class, 'destroy'],  ['auth', 'permission:core.settings.manage', 'csrf']);

// ---------------------------------------------------------------------
// Admin Settings (super-admin only for changes)
// ---------------------------------------------------------------------
$router->get('/recruit/admin/settings',                [AdminSettingsController::class, 'index'],           ['auth', 'permission:core.settings.manage']);
$router->get('/recruit/admin/settings/security',       [AdminSettingsController::class, 'showSecurity'],    ['auth', 'permission:core.settings.manage']);
$router->post('/recruit/admin/settings/security',      [AdminSettingsController::class, 'saveSecurity'],    ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/recruit/admin/settings/security/apply',[AdminSettingsController::class, 'applySecurity'],   ['auth', 'permission:core.settings.manage', 'csrf']);
$router->get('/recruit/admin/settings/branding',        [BrandingController::class, 'show'],   ['auth', 'permission:core.settings.manage']);
$router->post('/recruit/admin/settings/branding',       [BrandingController::class, 'update'], ['auth', 'permission:core.settings.manage', 'csrf']);

$router->get('/recruit/admin/settings/notifications',                  [AdminNotificationRuleController::class, 'index'],   ['auth', 'permission:core.settings.manage']);
$router->post('/recruit/admin/settings/notifications',                 [AdminNotificationRuleController::class, 'store'],   ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/recruit/admin/settings/notifications/{id}/toggle',     [AdminNotificationRuleController::class, 'toggle'],  ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/recruit/admin/settings/notifications/{id}/delete',     [AdminNotificationRuleController::class, 'destroy'], ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/recruit/admin/custom-fields/{entity}/{id}/toggle-required', [CustomFieldController::class, 'toggleRequired'], ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/recruit/admin/custom-fields/{entity}/{id}/move-up',         [CustomFieldController::class, 'moveUp'],         ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/recruit/admin/custom-fields/{entity}/{id}/move-down',       [CustomFieldController::class, 'moveDown'],       ['auth', 'permission:core.settings.manage', 'csrf']);

// Placeholders
$router->get('/recruit/admin/audit', function (Request $req, array $params): Response {
    return Response::html('<div style="padding:2rem;"><h1>Audit log</h1><p class="muted">Coming soon.</p></div>');
}, ['auth', 'permission:core.audit.view']);

$router->get('/recruit/admin/branding', function (Request $req, array $params): Response {
    return Response::html('<div style="padding:2rem;"><h1>Branding</h1><p class="muted">Coming soon.</p></div>');
}, ['auth', 'permission:core.settings.manage']);

$router->get('/recruit/admin/email-templates', function (Request $req, array $params): Response {
    return Response::html('<div style="padding:2rem;"><h1>Email templates</h1><p class="muted">Coming soon.</p></div>');
}, ['auth', 'permission:core.settings.manage']);

// ---------------------------------------------------------------------
// Notifications & Global Activity
// ---------------------------------------------------------------------
$router->get('/recruit/notifications',            [NotificationController::class, 'index'],       ['auth']);
$router->get('/recruit/activity',                 [GlobalActivityController::class, 'index'],     ['auth']);
$router->get('/recruit/activity/{type}/{id}',     [ActivityController::class, 'show'],            ['auth']);
$router->get('/recruit/api/live-search',          [LiveSearchController::class, 'search'],        ['auth']);
$router->get('/recruit/api/search',               [GlobalSearchController::class, 'api'],         ['auth']);
$router->get('/recruit/api/notifications',        [NotificationController::class, 'api'],         ['auth']);
$router->post('/recruit/notifications/{id}/read', [NotificationController::class, 'markRead'],    ['auth', 'csrf']);
$router->post('/recruit/notifications/read-all',  [NotificationController::class, 'markAllRead'], ['auth', 'csrf']);

// ---------- No Access ----------
$router->get('/no-access', [HomeController::class, 'noAccess'], ['auth']);
