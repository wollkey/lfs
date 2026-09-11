<?php

declare(strict_types=1);

namespace App\Tests\Http\Controller;

use App\Http\BadRequest;
use App\Http\Controller\TelegramWebhookController;
use App\Telegram\Inbox\Capture;
use App\Telegram\Inbox\MessageLog;
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

    public function testRejectsABodyThatIsNotAnUpdate(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] = 'secret-token';

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Invalid JSON body.');

        ($this->controller())();
    }

    private function controller(): TelegramWebhookController
    {
        return new TelegramWebhookController(
            new Capture(),
            new MessageLog(sys_get_temp_dir().'/lfs-inbox-unused'),
            'secret-token',
        );
    }
}
