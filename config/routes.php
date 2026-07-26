<?php

declare(strict_types=1);

use App\Http\Controller\FilmController;
use App\Http\Controller\FilmsController;
use App\Http\Controller\IngestController;
use App\Http\Controller\MemberController;
use App\Http\Controller\OverviewController;
use App\Http\Controller\RoundController;
use App\Http\Controller\TelegramWebhookController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();

$routes->add('overview', new Route('/api/overview', ['_controller' => OverviewController::class]));
$routes->add('films', new Route('/api/films', ['_controller' => FilmsController::class]));
$routes->add('film', new Route('/api/films/{slug}', ['_controller' => FilmController::class]));
$routes->add('members', new Route('/api/members', ['_controller' => MemberController::class]));
$routes->add('rounds', new Route('/api/rounds', ['_controller' => RoundController::class]));

if ((getenv('APP_ENV') ?: 'dev') !== 'prod') {
    $routes->add('ingest', new Route('/api/ingest', ['_controller' => IngestController::class]));
}

if ((getenv('TELEGRAM_BOT_TOKEN') ?: '') !== '' && (getenv('TELEGRAM_WEBHOOK_SECRET') ?: '') !== '') {
    $routes->add('telegram', new Route('/api/telegram/webhook', ['_controller' => TelegramWebhookController::class]));
}

return $routes;
