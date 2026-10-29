<?php

declare(strict_types=1);

namespace CorporateAid\Core;

use CorporateAid\Core\Exceptions\HttpException;
use CorporateAid\Core\Http\Kernel;
use CorporateAid\Platform\Repositories\CustomFieldRepository;
use CorporateAid\Platform\Repositories\CustomFieldValueRepository;
use CorporateAid\Recruit\Repositories\ContactRepository;
use CorporateAid\Recruit\Repositories\EngagementRepository;
use CorporateAid\Recruit\Repositories\EngagementAssignmentRepository;
use CorporateAid\Recruit\Repositories\VacancyRepository;
use CorporateAid\Recruit\Repositories\ApplicationRepository;
use CorporateAid\Recruit\Repositories\InterviewRepository;
use CorporateAid\Recruit\Repositories\ScorecardRepository;
use CorporateAid\Recruit\Repositories\OfferRepository;
use CorporateAid\Recruit\Repositories\CandidateRepository;
use CorporateAid\Recruit\Repositories\VacancyStageRepository;
use CorporateAid\Platform\Repositories\FileRepository;
use CorporateAid\Platform\Repositories\EmailTemplateRepository;
use CorporateAid\Platform\Repositories\EntityFileRepository;
use CorporateAid\Recruit\Repositories\OrganisationRepository;
use CorporateAid\Recruit\Repositories\PipelineStageRepository;
use CorporateAid\Platform\Repositories\RoleRepository;
use CorporateAid\Platform\Repositories\ActivityRepository;
use CorporateAid\Platform\Services\ActivityTimelineService;
use CorporateAid\Platform\Repositories\LookupRepository;
use CorporateAid\Platform\Services\LookupService;
use CorporateAid\Platform\Services\GlobalSearchService;
use CorporateAid\Platform\LiveSearch\LiveSearchRegistry;
use CorporateAid\Platform\LiveSearch\LiveSearchService;
use CorporateAid\Platform\LiveSearch\LiveSearchable;
use CorporateAid\Platform\Repositories\UserRepository;
use CorporateAid\Platform\Repositories\PasswordResetRepository;
use CorporateAid\Platform\Repositories\RateLimitRepository;
use CorporateAid\Platform\Repositories\NotificationRepository;
use CorporateAid\Platform\Services\CustomFieldService;
use CorporateAid\Platform\Deletion\DeletionService;
use CorporateAid\Recruit\Services\OrganisationService;
use CorporateAid\Recruit\Services\ContactService;
use CorporateAid\Recruit\Services\EngagementService;
use CorporateAid\Recruit\Services\VacancyService;
use CorporateAid\Recruit\Services\CandidateService;
use CorporateAid\Recruit\Services\ApplicationService;
use CorporateAid\Recruit\Services\InterviewService;
use CorporateAid\Recruit\Services\OfferService;
use CorporateAid\Platform\Services\SystemSettingService;
use CorporateAid\Platform\Services\FileService;
use CorporateAid\Platform\Services\NotificationService;
use CorporateAid\Platform\Services\NotificationRuleService;
use CorporateAid\Platform\Services\DashboardService;
use CorporateAid\Platform\Support\ActivityLogger;
use CorporateAid\Platform\CustomFields\CustomFieldRenderer;
use CorporateAid\Platform\CustomFields\CustomFieldValueSaver;
use CorporateAid\Platform\CustomFields\CustomFieldValueValidator;
use CorporateAid\Platform\Support\CodeGenerator;
use CorporateAid\Platform\Support\Logger;
use CorporateAid\Platform\Support\EmailService;
use CorporateAid\Platform\Support\EmailRenderer;
use CorporateAid\Platform\Support\EmailLayout;
use CorporateAid\Platform\Support\RateLimiter;
use CorporateAid\Platform\Validation\CustomFieldValidator;
use CorporateAid\Recruit\Validation\OrganisationValidator;
use CorporateAid\Recruit\Validation\ContactValidator;
use CorporateAid\Recruit\Validation\EngagementValidator;
use CorporateAid\Recruit\Validation\VacancyValidator;
use CorporateAid\Recruit\Validation\CandidateValidator;
use CorporateAid\Platform\Validation\FileValidator;
use Throwable;

final class App
{
    public const VERSION = '0.6.0-alpha';

    private static ?self $instance = null;
    private string $basePath;
    private Container $container;
    private Router $router;

    private function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->container = new Container();
    }

    public static function boot(string $basePath): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }
        $app = new self($basePath);
        self::$instance = $app;
        $app->bootstrap();
        return $app;
    }

    public static function instance(): self
    {
        if (!self::$instance instanceof self) {
            throw new \RuntimeException('App has not been booted.');
        }
        return self::$instance;
    }

    private function bootstrap(): void
    {
        Logger::setBasePath($this->basePath);
        $this->registerErrorHandling();
        $this->loadConfig();
        $this->registerTimezone();
        $this->registerServices();
        $this->loadRoutes();

        // Boot live search registry — after all repository services are bound
        LiveSearchRegistry::boot($this->container);
        LiveSearchRegistry::register('organisations', OrganisationRepository::class);
        LiveSearchRegistry::register('contacts',      ContactRepository::class);
        LiveSearchRegistry::register('engagements',   EngagementRepository::class);
        LiveSearchRegistry::register('vacancies',     VacancyRepository::class);
        LiveSearchRegistry::register('candidates',    CandidateRepository::class);
    }

    private function registerErrorHandling(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $e): void {
            try {
                Logger::exception($e);
            } catch (Throwable) {
                // logging must never break the response
            }
            $this->renderException($e);
        });
        
    }

    private function loadConfig(): void
    {
        $configDir = $this->basePath . '/config';
        $merged = [];
        $files = glob($configDir . '/*.php');
        if ($files === false) {
            $files = [];
        }
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if ($name === 'local') {
                continue;
            }
            $merged[$name] = require $file;
        }
        $local = $configDir . '/local.php';
        if (is_file($local)) {
            $overrides = require $local;
            if (is_array($overrides)) {
                $merged = array_replace_recursive($merged, $overrides);
            }
        }
        $this->container->instance(Config::class, new Config($merged));
    }

    private function registerTimezone(): void
    {
        $tzRaw = $this->container->get(Config::class)->get('app.timezone', 'Africa/Nairobi');
        $tz = is_string($tzRaw) && $tzRaw !== '' ? $tzRaw : 'Africa/Nairobi';
        date_default_timezone_set($tz);
    }

    private function registerServices(): void
    {
        $c = $this->container;

        // ---------- Core services ----------
        $c->singleton(Database::class, function () use ($c) {
            $raw = $c->get(Config::class)->get('database', []);
            $dbConfig = [];
            if (is_array($raw)) {
                foreach ($raw as $k => $v) {
                    $dbConfig[(string) $k] = $v;
                }
            }
            return new Database(DatabaseConfig::fromArray($dbConfig));
        });
        $c->singleton(View::class,     fn() => new View($this->basePath . '/resources/views'));
        $c->singleton(Router::class,   fn() => new Router($c));
        $c->singleton(Session::class, function () use ($c) {
            $raw = $c->get(Config::class)->get('app.session', []);
            $sessionConfig = [];
            if (is_array($raw)) {
                foreach ($raw as $k => $v) {
                    $sessionConfig[(string) $k] = $v;
                }
            }
            return new Session($sessionConfig);
        });
        $c->singleton(Csrf::class,     fn() => new Csrf($c->get(Session::class)));

        // ---------- Repositories ----------
        $c->singleton(UserRepository::class,              fn() => new UserRepository($c->get(Database::class)));
        $c->singleton(RoleRepository::class,              fn() => new RoleRepository($c->get(Database::class)));
        $c->singleton(LookupRepository::class,            fn() => new LookupRepository($c->get(Database::class)));
        $c->singleton(LiveSearchService::class, fn() => new LiveSearchService($c->get(Database::class), $c->get(View::class), $c->get(Auth::class)));
        $c->singleton(GlobalSearchService::class, fn() => new GlobalSearchService($c->get(Database::class), $c->get(Auth::class)));
        $c->singleton(LookupService::class,               fn() => new LookupService($c->get(LookupRepository::class)));
        $c->singleton(PasswordResetRepository::class,     fn() => new PasswordResetRepository($c->get(Database::class)));
        $c->singleton(RateLimitRepository::class,         fn() => new RateLimitRepository($c->get(Database::class)));
        $c->singleton(NotificationRepository::class,      fn() => new NotificationRepository($c->get(Database::class)));
        $c->singleton(OrganisationRepository::class,      fn() => new OrganisationRepository($c->get(Database::class)));
        $c->singleton(CustomFieldRepository::class,       fn() => new CustomFieldRepository($c->get(Database::class)));
        $c->singleton(CustomFieldValueRepository::class,  fn() => new CustomFieldValueRepository($c->get(Database::class)));
        $c->singleton(PipelineStageRepository::class,     fn() => new PipelineStageRepository($c->get(Database::class)));
        $c->singleton(ContactRepository::class,           fn() => new ContactRepository($c->get(Database::class)));
        $c->singleton(EngagementRepository::class,        fn() => new EngagementRepository($c->get(Database::class)));
        $c->singleton(EngagementAssignmentRepository::class, fn() => new EngagementAssignmentRepository($c->get(Database::class)));
        $c->singleton(VacancyRepository::class,           fn() => new VacancyRepository($c->get(Database::class)));
        $c->singleton(CandidateRepository::class,         fn() => new CandidateRepository($c->get(Database::class)));
        $c->singleton(VacancyStageRepository::class,      fn() => new VacancyStageRepository($c->get(Database::class)));
        $c->singleton(ApplicationRepository::class,        fn() => new ApplicationRepository($c->get(Database::class)));
        $c->singleton(InterviewRepository::class,          fn() => new InterviewRepository($c->get(Database::class)));
        $c->singleton(ScorecardRepository::class,          fn() => new ScorecardRepository($c->get(Database::class)));
        $c->singleton(OfferRepository::class,              fn() => new OfferRepository($c->get(Database::class)));
        $c->singleton(FileRepository::class,           fn() => new FileRepository($c->get(Database::class)));
        $c->singleton(EmailTemplateRepository::class,   fn() => new EmailTemplateRepository($c->get(Database::class)));
        $c->singleton(EntityFileRepository::class,     fn() => new EntityFileRepository($c->get(Database::class)));

        // ---------- Auth ----------
        $c->singleton(Auth::class, fn() => new Auth($c->get(UserRepository::class), $c->get(Session::class)));

        // ---------- Support ----------
        $c->singleton(CodeGenerator::class,  fn() => new CodeGenerator($c->get(Database::class)));
        $c->singleton(RateLimiter::class,     fn() => new RateLimiter($c->get(RateLimitRepository::class), $c->get(SystemSettingService::class)));
        $c->singleton(EmailService::class, function () use ($c) {
            $raw = $c->get(Config::class)->get('mail', []);
            $mailConfig = [];
            if (is_array($raw)) {
                foreach ($raw as $k => $v) {
                    $mailConfig[(string) $k] = $v;
                }
            }
            return new EmailService($mailConfig, new Logger());
        });
        $c->singleton(EmailLayout::class, fn() => new EmailLayout());
        $c->singleton(EmailRenderer::class, fn() => new EmailRenderer($c->get(EmailTemplateRepository::class), $c->get(EmailLayout::class)));
        $c->singleton(ActivityLogger::class, fn() => new ActivityLogger($c->get(Database::class), $c->get(Auth::class)));
        $c->singleton(ActivityRepository::class, fn() => new ActivityRepository($c->get(Database::class)));
        $c->singleton(ActivityTimelineService::class, fn() => new ActivityTimelineService($c->get(ActivityRepository::class), $c->get(View::class)));

        // ---------- Validation ----------
        $c->singleton(CustomFieldValidator::class, fn() => new CustomFieldValidator());

        // ---------- Validation ----------
        $c->singleton(OrganisationValidator::class, fn() => new OrganisationValidator());
        $c->singleton(ContactValidator::class,      fn() => new ContactValidator());
        $c->singleton(CandidateValidator::class,    fn() => new CandidateValidator());
        $c->singleton(EngagementValidator::class,   fn() => new EngagementValidator());
        $c->singleton(VacancyValidator::class,      fn() => new VacancyValidator($c->get(LookupService::class)));
        $c->singleton(FileValidator::class,      fn() => new FileValidator());

        // ---------- Services ----------
        $c->singleton(CustomFieldService::class, fn() => new CustomFieldService(
            $c->get(CustomFieldRepository::class),
            $c->get(CustomFieldValidator::class),
            $c->get(ActivityLogger::class),
            $c->get(Auth::class)
        ));

        // ---------- Deletion ----------
        $c->singleton(DeletionService::class, fn() => new DeletionService(
            $c->get(Database::class),
            $c->get(ActivityLogger::class),
            $c->get(Auth::class)
        ));

        // ---------- Organisation service ----------
        $c->singleton(OrganisationService::class, fn() => new OrganisationService(
            $c->get(OrganisationRepository::class),
            $c->get(OrganisationValidator::class),
            $c->get(CustomFieldValueValidator::class),
            $c->get(CustomFieldValueSaver::class),
            $c->get(DeletionService::class),
            $c->get(ActivityLogger::class),
            $c->get(Database::class),
            $c->get(Auth::class)
        ));

        // ---------- Contact service ----------
        $c->singleton(ContactService::class, fn() => new ContactService(
            $c->get(ContactRepository::class),
            $c->get(ContactValidator::class),
            $c->get(CustomFieldValueValidator::class),
            $c->get(CustomFieldValueSaver::class),
            $c->get(DeletionService::class),
            $c->get(ActivityLogger::class),
            $c->get(Database::class),
            $c->get(Auth::class)
        ));

        // ---------- Engagement service ----------
        $c->singleton(EngagementService::class, fn() => new EngagementService(
            $c->get(EngagementRepository::class),
            $c->get(EngagementValidator::class),
            $c->get(CustomFieldValueValidator::class),
            $c->get(CustomFieldValueSaver::class),
            $c->get(DeletionService::class),
            $c->get(ActivityLogger::class),
            $c->get(Database::class),
            $c->get(Auth::class)
        ));

        // ---------- Vacancy service ----------
        $c->singleton(VacancyService::class, fn() => new VacancyService(
            $c->get(VacancyRepository::class),
            $c->get(VacancyStageRepository::class),
            $c->get(EngagementRepository::class),
            $c->get(VacancyValidator::class),
            $c->get(CustomFieldValueValidator::class),
            $c->get(CustomFieldValueSaver::class),
            $c->get(DeletionService::class),
            $c->get(ActivityLogger::class),
            $c->get(Database::class),
            $c->get(Auth::class)
        ));

        $c->singleton(CandidateService::class, fn() => new CandidateService(
            $c->get(CandidateRepository::class),
            $c->get(CandidateValidator::class),
            $c->get(ActivityLogger::class),
            $c->get(Auth::class)
        ));

        $c->singleton(ApplicationService::class, fn() => new ApplicationService(
            $c->get(ApplicationRepository::class),
            $c->get(VacancyRepository::class),
            $c->get(VacancyStageRepository::class),
            $c->get(EngagementRepository::class),
            $c->get(SystemSettingService::class),
            $c->get(ActivityLogger::class),
            $c->get(Auth::class)
        ));

        $c->singleton(InterviewService::class, fn() => new InterviewService(
            $c->get(InterviewRepository::class),
            $c->get(ScorecardRepository::class),
            $c->get(ApplicationRepository::class),
            $c->get(ActivityLogger::class),
            $c->get(Auth::class)
        ));

        $c->singleton(OfferService::class, fn() => new OfferService(
            $c->get(OfferRepository::class),
            $c->get(ApplicationRepository::class),
            $c->get(VacancyStageRepository::class),
            $c->get(ActivityLogger::class),
            $c->get(Auth::class)
        ));

        // ---------- Dashboard ----------
        $c->singleton(DashboardService::class, fn() => new DashboardService(
            $c->get(Database::class),
            $c->get(Auth::class)
        ));

        // ---------- Notification rules ----------
        $c->singleton(NotificationRuleService::class, fn() => new NotificationRuleService(
            $c->get(Database::class),
            $c->get(EngagementAssignmentRepository::class),
            $c->get(Auth::class)
        ));

        // ---------- Notifications ----------
        $c->singleton(FileService::class, fn() => new FileService(
            $c->get(FileRepository::class),
            $c->get(EntityFileRepository::class),
            $c->get(FileValidator::class),
            $this->basePath
        ));

        $c->singleton(NotificationService::class, fn() => new NotificationService(
            $c->get(NotificationRepository::class),
            $c->get(NotificationRuleService::class),
            $c->get(Auth::class)
        ));

        // ---------- System settings ----------
        $c->singleton(SystemSettingService::class, function () use ($c) {
            $svc = new SystemSettingService(
                $c->get(Database::class),
                $c->get(ActivityLogger::class),
                $c->get(Auth::class)
            );
            // Setter injection avoids a circular dependency:
            // NotificationService uses Auth, Auth uses UserRepository, etc.
            $svc->setNotificationService($c->get(NotificationService::class));
            return $svc;
        });

        // ---------- Custom field rendering + values ----------
        $c->singleton(CustomFieldRenderer::class, fn() => new CustomFieldRenderer(
            $c->get(CustomFieldRepository::class),
            $c->get(CustomFieldValueRepository::class)
        ));
        $c->singleton(CustomFieldValueValidator::class, fn() => new CustomFieldValueValidator(
            $c->get(CustomFieldRepository::class)
        ));
        $c->singleton(CustomFieldValueSaver::class, fn() => new CustomFieldValueSaver(
            $c->get(CustomFieldRepository::class),
            $c->get(CustomFieldValueRepository::class),
            $c->get(FileService::class),
            $c->get(Auth::class)
        ));

        // ---------- HTTP ----------
        $c->singleton(Kernel::class, fn() => new Kernel($c));

        // ---------- Middleware ----------
        $c->singleton(\CorporateAid\Platform\Middleware\AuthMiddleware::class,       fn() => new \CorporateAid\Platform\Middleware\AuthMiddleware($c->get(Auth::class)));
        $c->singleton(\CorporateAid\Platform\Middleware\GuestMiddleware::class,      fn() => new \CorporateAid\Platform\Middleware\GuestMiddleware($c->get(Auth::class)));
        $c->singleton(\CorporateAid\Platform\Middleware\CsrfMiddleware::class,       fn() => new \CorporateAid\Platform\Middleware\CsrfMiddleware($c->get(Csrf::class)));
        $c->singleton(\CorporateAid\Platform\Middleware\CustomFieldFileMiddleware::class, fn() => new \CorporateAid\Platform\Middleware\CustomFieldFileMiddleware($c));

        // ---------- Audit ----------
        // AuditLogger writes rows to `core_audit_logs` (HTTP request audit).
        // AuditLoggerMiddleware wraps state-changing requests and calls
        // the logger after the controller runs.
        // AuditContext is static — no container binding required.
        $c->singleton(
            \CorporateAid\Platform\Support\AuditLogger::class,
            fn() => new \CorporateAid\Platform\Support\AuditLogger($c->get(Database::class))
        );
        $c->singleton(
            \CorporateAid\Platform\Middleware\AuditLoggerMiddleware::class,
            fn() => new \CorporateAid\Platform\Middleware\AuditLoggerMiddleware($c)
        );

        // ---------- Metadata ----------
        $c->instance('basePath', $this->basePath);
        $c->instance('app.version', self::VERSION);
    }

    private function loadRoutes(): void
    {
        // Ensure session is started before routes load (needed by CSRF, auth, flash).
        $this->container->get(Session::class)->start();

        $this->router = $this->container->get(Router::class);
        $router = $this->router;
        foreach (['web', 'auth', 'client', 'public'] as $name) {
            $path = $this->basePath . '/routes/' . $name . '.php';
            if (is_file($path)) {
                require $path;
            }
        }
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function basePath(string $suffix = ''): string
    {
        return $this->basePath . ($suffix !== '' ? '/' . ltrim($suffix, '/') : '');
    }

    public function run(): void
    {
        $request = Request::capture();
        $response = $this->router->dispatch($request);
        $response->send();
    }

    private function renderException(Throwable $e): void
    {
        $errorRef = null;

        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            $message = $e->getMessage();
        } else {
            $status = 500;
            $message = 'Something went wrong. Please try again or contact the administrator.';
            // Generate a short, user-facing reference ID for unhandled
            // exceptions. The same ID appears in the log so support can
            // grep for it to find the full trace. Only 500s get a
            // reference — 4xx are user errors, not bugs to track down.
            $errorRef = 'ERR-' . bin2hex(random_bytes(3));
        }

        // Log the exception. For 500s, this includes the reference so
        // grep <ref> storage/logs/app.log returns the exact stack trace.
        try {
            \CorporateAid\Platform\Support\Logger::exception($e, $errorRef);
        } catch (\Throwable $loggingFailure) {
            // Logging must never block error rendering
        }

        http_response_code($status);
        $view = $this->basePath . '/resources/views/errors/' . $status . '.php';
        if (!is_file($view)) {
            $view = $this->basePath . '/resources/views/errors/500.php';
        }
        if (!is_file($view)) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "{$status} {$message}";
            if ($errorRef !== null) {
                echo " (Reference: {$errorRef})";
            }
            return;
        }
        $app = $this;
        $errorMessage = $message;
        // Passed to the view; 500.php renders it when set.
        require $view;
    }
}
