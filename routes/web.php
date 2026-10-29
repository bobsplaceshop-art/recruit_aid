<?php
declare(strict_types=1);

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Router;
use CorporateAid\Recruit\Controllers\AccountController;
use CorporateAid\Recruit\Controllers\Admin\AdminHomeController;
use CorporateAid\Recruit\Controllers\Admin\CustomFieldController;
use CorporateAid\Recruit\Controllers\Admin\RoleController;
use CorporateAid\Recruit\Controllers\Admin\AdminSettingsController;
use CorporateAid\Recruit\Controllers\Admin\AdminNotificationRuleController;
use CorporateAid\Recruit\Controllers\Admin\UserController as AdminUserController;
use CorporateAid\Recruit\Controllers\DashboardController;
use CorporateAid\Recruit\Controllers\HomeController;
use CorporateAid\Recruit\Controllers\NotificationController;
use CorporateAid\Recruit\Controllers\OrganisationController;
use CorporateAid\Recruit\Controllers\ContactController;
use CorporateAid\Recruit\Controllers\EngagementController;
use CorporateAid\Recruit\Controllers\FileController;
use CorporateAid\Recruit\Controllers\VacancyController;

/** @var Router $router */

$router->get('/',       [HomeController::class, 'index']);
$router->get('/health', [HomeController::class, 'health']);

$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);

$router->get('/account',           [AccountController::class, 'index'],              ['auth']);
$router->get('/account/password',  [AccountController::class, 'showChangePassword'], ['auth']);
$router->post('/account/password', [AccountController::class, 'changePassword'],     ['auth', 'csrf']);
$router->post('/account/theme',    [AccountController::class, 'setTheme'],           ['auth', 'csrf']);
$router->get('/account/confirm-password',  [AccountController::class, 'showConfirmPassword'], ['auth']);
$router->post('/account/confirm-password', [AccountController::class, 'confirmPassword'],     ['auth', 'csrf', 'ratelimit:confirm_password']);

// ---------------------------------------------------------------------
// Organisations
// ---------------------------------------------------------------------
$router->get('/organisations',                 [OrganisationController::class, 'index'],      ['auth', 'permission:recruit.organisations.view']);
$router->get('/organisations/create',          [OrganisationController::class, 'showCreate'], ['auth', 'permission:recruit.organisations.create']);
$router->post('/organisations',                [OrganisationController::class, 'store'],      ['auth', 'permission:recruit.organisations.create', 'csrf']);
$router->get('/organisations/{code}',          [OrganisationController::class, 'show'],       ['auth', 'permission:recruit.organisations.view']);
$router->get('/organisations/{code}/edit',     [OrganisationController::class, 'showEdit'],   ['auth', 'permission:recruit.organisations.edit']);
$router->put('/organisations/{code}',          [OrganisationController::class, 'update'],     ['auth', 'permission:recruit.organisations.edit', 'csrf']);
$router->post('/organisations/{code}/archive', [OrganisationController::class, 'archive'],    ['auth', 'permission:recruit.organisations.delete', 'csrf']);
$router->post('/organisations/{code}/restore', [OrganisationController::class, 'restore'],    ['auth', 'permission:recruit.organisations.delete', 'csrf']);
$router->post('/organisations/{code}/delete',  [OrganisationController::class, 'destroy'],    ['auth', 'permission:recruit.organisations.delete', 'csrf']);
$router->get('/organisations/{code}/delete-info',  [OrganisationController::class, 'deleteInfo'],  ['auth', 'permission:recruit.organisations.delete']);

// ---------------------------------------------------------------------
// Organisation Contacts
// ---------------------------------------------------------------------
$router->get('/organisations/{code}/contacts',                          [ContactController::class, 'index'],       ['auth', 'permission:recruit.contacts.view']);
$router->get('/organisations/{code}/contacts/create',                   [ContactController::class, 'showCreate'],  ['auth', 'permission:recruit.contacts.create']);
$router->post('/organisations/{code}/contacts',                         [ContactController::class, 'store'],       ['auth', 'permission:recruit.contacts.create', 'csrf']);
$router->get('/organisations/{code}/contacts/{contactCode}',            [ContactController::class, 'show'],        ['auth', 'permission:recruit.contacts.view']);
$router->get('/organisations/{code}/contacts/{contactCode}/edit',       [ContactController::class, 'showEdit'],    ['auth', 'permission:recruit.contacts.edit']);
$router->put('/organisations/{code}/contacts/{contactCode}',            [ContactController::class, 'update'],      ['auth', 'permission:recruit.contacts.edit', 'csrf']);
$router->post('/organisations/{code}/contacts/{contactCode}/archive',   [ContactController::class, 'archive'],     ['auth', 'permission:recruit.contacts.delete', 'csrf']);
$router->post('/organisations/{code}/contacts/{contactCode}/restore',   [ContactController::class, 'restore'],     ['auth', 'permission:recruit.contacts.delete', 'csrf']);
$router->post('/organisations/{code}/contacts/{contactCode}/delete',    [ContactController::class, 'destroy'],     ['auth', 'permission:recruit.contacts.delete', 'csrf']);
$router->get('/organisations/{code}/contacts/{contactCode}/delete-info',[ContactController::class, 'deleteInfo'],  ['auth', 'permission:recruit.contacts.delete']);

// ---------------------------------------------------------------------
// Engagements
// ---------------------------------------------------------------------
$router->get('/engagements',                             [EngagementController::class, 'index'],       ['auth', 'permission:recruit.engagements.view']);
$router->get('/engagements/create',                      [EngagementController::class, 'showCreate'],  ['auth', 'permission:recruit.engagements.create']);
$router->post('/engagements',                            [EngagementController::class, 'store'],       ['auth', 'permission:recruit.engagements.create', 'csrf']);
$router->get('/engagements/{code}',                      [EngagementController::class, 'show'],        ['auth', 'permission:recruit.engagements.view']);
$router->get('/engagements/{code}/edit',                 [EngagementController::class, 'showEdit'],    ['auth', 'permission:recruit.engagements.edit']);
$router->put('/engagements/{code}',                      [EngagementController::class, 'update'],      ['auth', 'permission:recruit.engagements.edit', 'csrf']);
$router->post('/engagements/{code}/archive',             [EngagementController::class, 'archive'],     ['auth', 'permission:recruit.engagements.delete', 'csrf']);
$router->post('/engagements/{code}/restore',             [EngagementController::class, 'restore'],     ['auth', 'permission:recruit.engagements.delete', 'csrf']);
$router->post('/engagements/{code}/delete',              [EngagementController::class, 'destroy'],     ['auth', 'permission:recruit.engagements.delete', 'csrf']);
$router->get('/engagements/{code}/delete-info',          [EngagementController::class, 'deleteInfo'],  ['auth', 'permission:recruit.engagements.delete']);
$router->post('/engagements/{code}/recruiters/assign',   [EngagementController::class, 'assignRecruiter'],   ['auth', 'permission:recruit.engagements.edit', 'csrf']);
$router->post('/engagements/{code}/recruiters/unassign', [EngagementController::class, 'unassignRecruiter'], ['auth', 'permission:recruit.engagements.edit', 'csrf']);

// ---------------------------------------------------------------------
// Vacancies
// ---------------------------------------------------------------------
$router->get('/vacancies',                        [VacancyController::class, 'index'],       ['auth', 'permission:recruit.vacancies.view']);
$router->get('/vacancies/create',                 [VacancyController::class, 'showCreate'],  ['auth', 'permission:recruit.vacancies.create']);
$router->post('/vacancies',                       [VacancyController::class, 'store'],       ['auth', 'permission:recruit.vacancies.create', 'csrf']);
$router->get('/vacancies/{code}',                 [VacancyController::class, 'show'],        ['auth', 'permission:recruit.vacancies.view']);
$router->get('/vacancies/{code}/edit',            [VacancyController::class, 'showEdit'],    ['auth', 'permission:recruit.vacancies.edit']);
$router->put('/vacancies/{code}',                 [VacancyController::class, 'update'],      ['auth', 'permission:recruit.vacancies.edit', 'csrf']);
$router->post('/vacancies/{code}/archive',        [VacancyController::class, 'archive'],     ['auth', 'permission:recruit.vacancies.close', 'csrf']);
$router->post('/vacancies/{code}/restore',        [VacancyController::class, 'restore'],     ['auth', 'permission:recruit.vacancies.close', 'csrf']);
$router->post('/vacancies/{code}/delete',         [VacancyController::class, 'destroy'],     ['auth', 'permission:recruit.vacancies.close', 'csrf']);
$router->get('/vacancies/{code}/delete-info',     [VacancyController::class, 'deleteInfo'],  ['auth', 'permission:recruit.vacancies.close']);

// ---------------------------------------------------------------------
// Files
// ---------------------------------------------------------------------
// Files are served through PHP with permission checks derived from
// the file's linked entities. The route requires 'auth' only; the
// fine-grained permission check happens inside FileController::serve()
// because permission depends on which entity the file is attached to.
$router->get('/files/{uuid}', [FileController::class, 'serve'], ['auth']);

// ---------------------------------------------------------------------
// Administration
// ---------------------------------------------------------------------
$router->get('/admin', [AdminHomeController::class, 'index'], ['auth']);

// User accounts
$router->get('/admin/users', [AdminUserController::class, 'index'], ['auth', 'permission:core.users.view']);

// Roles & permissions
$router->get('/admin/roles-perm',                 [RoleController::class, 'home'],    ['auth', 'permission:core.settings.manage']);
$router->get('/admin/roles-perm/roles',           [RoleController::class, 'index'],   ['auth', 'permission:core.settings.manage']);
$router->get('/admin/roles-perm/roles/create',    [RoleController::class, 'create'],  ['auth', 'permission:core.settings.manage']);
$router->post('/admin/roles-perm/roles',          [RoleController::class, 'store'],   ['auth', 'permission:core.settings.manage', 'csrf']);
$router->get('/admin/roles-perm/roles/{id}/edit', [RoleController::class, 'edit'],    ['auth', 'permission:core.settings.manage']);
$router->put('/admin/roles-perm/roles/{id}',      [RoleController::class, 'update'],  ['auth', 'permission:core.settings.manage', 'csrf']);
$router->delete('/admin/roles-perm/roles/{id}',   [RoleController::class, 'destroy'], ['auth', 'permission:core.settings.manage', 'csrf']);
$router->get('/admin/roles-perm/permissions',     [RoleController::class, 'permissions'], ['auth', 'permission:core.settings.manage']);
$router->post('/admin/roles-perm/permissions',    [RoleController::class, 'savePermissions'], ['auth', 'permission:core.settings.manage', 'csrf']);

// Legacy redirect
$router->get('/admin/roles', function (Request $req, array $params): Response {
    return Response::redirect('/admin/roles-perm');
}, ['auth', 'permission:core.settings.manage']);

// Custom fields (field builder)
$router->get('/admin/custom-fields',                      [CustomFieldController::class, 'index'],      ['auth', 'permission:core.settings.manage']);
$router->get('/admin/custom-fields/{entity}',             [CustomFieldController::class, 'listFor'],    ['auth', 'permission:core.settings.manage']);
$router->get('/admin/custom-fields/{entity}/create',      [CustomFieldController::class, 'showCreate'], ['auth', 'permission:core.settings.manage']);
$router->post('/admin/custom-fields/{entity}',            [CustomFieldController::class, 'store'],      ['auth', 'permission:core.settings.manage', 'csrf']);
$router->get('/admin/custom-fields/{entity}/{id}/edit',   [CustomFieldController::class, 'showEdit'],   ['auth', 'permission:core.settings.manage']);
$router->post('/admin/custom-fields/{entity}/{id}',       [CustomFieldController::class, 'update'],     ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/admin/custom-fields/{entity}/{id}/archive', [CustomFieldController::class, 'archive'],  ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/admin/custom-fields/{entity}/{id}/restore', [CustomFieldController::class, 'restore'],  ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/admin/custom-fields/{entity}/{id}/delete',  [CustomFieldController::class, 'destroy'],  ['auth', 'permission:core.settings.manage', 'csrf']);

// ---------------------------------------------------------------------
// Admin Settings (super-admin only for changes)
// ---------------------------------------------------------------------
$router->get('/admin/settings',                [AdminSettingsController::class, 'index'],           ['auth', 'permission:core.settings.manage']);
$router->get('/admin/settings/security',       [AdminSettingsController::class, 'showSecurity'],    ['auth', 'permission:core.settings.manage']);
$router->post('/admin/settings/security',      [AdminSettingsController::class, 'saveSecurity'],    ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/admin/settings/security/apply',[AdminSettingsController::class, 'applySecurity'],   ['auth', 'permission:core.settings.manage', 'csrf']);

$router->get('/admin/settings/notifications',                  [AdminNotificationRuleController::class, 'index'],   ['auth', 'permission:core.settings.manage']);
$router->post('/admin/settings/notifications',                 [AdminNotificationRuleController::class, 'store'],   ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/admin/settings/notifications/{id}/toggle',     [AdminNotificationRuleController::class, 'toggle'],  ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/admin/settings/notifications/{id}/delete',     [AdminNotificationRuleController::class, 'destroy'], ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/admin/custom-fields/{entity}/{id}/toggle-required', [CustomFieldController::class, 'toggleRequired'], ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/admin/custom-fields/{entity}/{id}/move-up',         [CustomFieldController::class, 'moveUp'],         ['auth', 'permission:core.settings.manage', 'csrf']);
$router->post('/admin/custom-fields/{entity}/{id}/move-down',       [CustomFieldController::class, 'moveDown'],       ['auth', 'permission:core.settings.manage', 'csrf']);

// Placeholders
$router->get('/admin/audit', function (Request $req, array $params): Response {
    return Response::html('<div style="padding:2rem;"><h1>Audit log</h1><p class="muted">Coming soon.</p></div>');
}, ['auth', 'permission:core.audit.view']);

$router->get('/admin/branding', function (Request $req, array $params): Response {
    return Response::html('<div style="padding:2rem;"><h1>Branding</h1><p class="muted">Coming soon.</p></div>');
}, ['auth', 'permission:core.settings.manage']);

$router->get('/admin/email-templates', function (Request $req, array $params): Response {
    return Response::html('<div style="padding:2rem;"><h1>Email templates</h1><p class="muted">Coming soon.</p></div>');
}, ['auth', 'permission:core.settings.manage']);

// ---------------------------------------------------------------------
// Notifications
// ---------------------------------------------------------------------
$router->get('/notifications',            [NotificationController::class, 'index'],       ['auth']);
$router->get('/api/notifications',        [NotificationController::class, 'api'],         ['auth']);
$router->post('/notifications/{id}/read', [NotificationController::class, 'markRead'],    ['auth', 'csrf']);
$router->post('/notifications/read-all',  [NotificationController::class, 'markAllRead'], ['auth', 'csrf']);
