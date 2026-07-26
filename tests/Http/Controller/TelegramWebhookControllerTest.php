<?php

declare(strict_types=1);

namespace App\Tests\Http\Controller;

use App\Http\BadRequest;
use App\Http\Controller\TelegramWebhookController;
use App\Persistence\Connection;
use App\Statistics\Statistics;
use App\Telegram\Messages;
use App\Tests\Telegram\RecordingTelegramClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TelegramWebhookController::class)]
final class TelegramWebhookControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']);
    }

    public function testRejectsNonPostRequests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->expectException(BadRequest::class);

        ($this->controller())();
    }

    public function testRejectsAnInvalidSecretToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] = 'wrong';

        $this->expectException(BadRequest::class);

        ($this->controller())();
    }

    private function controller(): TelegramWebhookController
    {
        $stats = new Statistics(Connection::open(':memory:'));

        return new TelegramWebhookController(
            new Messages($stats),
            new RecordingTelegramClient(),
            'secret-token',
        );
    }
}
