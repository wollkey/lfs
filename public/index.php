<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Http\BadRequest;
use App\Http\Controller\FilmController;
use App\Http\Controller\FilmsController;
use App\Http\Controller\IngestController;
use App\Http\Controller\MemberController;
use App\Http\Controller\OverviewController;
use App\Http\Controller\RoundController;
use App\Http\Controller\TelegramWebhookController;
use App\Http\NotFound;
use App\Persistence\Connection;
use App\Statistics\Statistics;
use App\Telegram\Messages;
use App\Telegram\PhptgClient;
use Phptg\BotApi\TelegramBotApi;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

$isProd = (getenv('APP_ENV') ?: 'dev') === 'prod';

$databasePath = dirname(__DIR__).'/data/lfs.sqlite';
$pdo = $isProd
    ? Connection::openReadOnly($databasePath)
    : Connection::open($databasePath);
$stats = new Statistics($pdo);

$controllers = [
    OverviewController::class => new OverviewController($stats),
    MemberController::class => new MemberController($stats),
    RoundController::class => new RoundController($stats),
    FilmController::class => new FilmController($stats),
    FilmsController::class => new FilmsController($stats),
];

if (!$isProd) {
    $controllers[IngestController::class] = new IngestController(dirname(__DIR__).'/data');
}

$telegramToken = getenv('TELEGRAM_BOT_TOKEN') ?: null;
$telegramSecret = getenv('TELEGRAM_WEBHOOK_SECRET') ?: null;
if ($telegramToken !== null && $telegramSecret !== null) {
    $siteUrl = getenv('LFS_SITE_URL') ?: 'https://lfs.wollkey.ru';
    $controllers[TelegramWebhookController::class] = new TelegramWebhookController(
        new Messages($stats, $siteUrl),
        new PhptgClient(new TelegramBotApi($telegramToken)),
        $telegramSecret,
    );
}

$routes = require dirname(__DIR__).'/config/routes.php';
$context = new RequestContext(method: $_SERVER['REQUEST_METHOD'] ?? 'GET');
$matcher = new UrlMatcher($routes, $context);

header('Content-Type: application/json; charset=utf-8');

try {
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $params = $matcher->match($path);

    $class = $params['_controller'];
    unset($params['_controller'], $params['_route']);

    $data = $controllers[$class](...array_values($params));
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (ResourceNotFoundException|NotFound $e) {
    http_response_code(404);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (BadRequest $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error'], JSON_UNESCAPED_UNICODE);
}
