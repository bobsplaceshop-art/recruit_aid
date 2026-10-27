<?php
declare(strict_types=1);

namespace CorporateAid\Recruit\Controllers;

use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\View;

/**
 * Test / landing controller for the first-run milestone.
 * Real features replace this later.
 */
final class HomeController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function index(Request $request, array $params = []): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);

        $html = $view->render('home', [
            'title' => 'Recruit Aid',
            'phpVersion' => PHP_VERSION,
            'appVersion' => \CorporateAid\Core\App::VERSION,
            'serverTime' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
        ]);

        return Response::html($html);
    }

    public function health(Request $request, array $params = []): Response
    {
        return Response::json([
            'status' => 'ok',
            'app' => 'recruitaid',
            'version' => \CorporateAid\Core\App::VERSION,
            'php' => PHP_VERSION,
            'time' => date('c'),
        ]);
    }
}
