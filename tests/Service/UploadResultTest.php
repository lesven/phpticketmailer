<?php
namespace App\Tests\Service;

use App\Service\UploadResult;
use PHPUnit\Framework\TestCase;

class UploadResultTest extends TestCase
{
    public function testRedirectToUnknownUsersParameters(): void
    {
        $res = UploadResult::redirectToUnknownUsers(true, false, 5);

        $this->assertSame('unknown_users', $res->redirectRoute);
        $this->assertSame(['testMode' => 1, 'forceResend' => 0], $res->routeParameters);
        $this->assertSame('Es wurden 5 unbekannte Benutzer gefunden', $res->flashMessage);
        $this->assertSame('info', $res->flashType);
    }

    public function testRedirectToUnknownUsersZero(): void
    {
        $res = UploadResult::redirectToUnknownUsers(false, true, 0);

        $this->assertSame('unknown_users', $res->redirectRoute);
        $this->assertSame(['testMode' => 0, 'forceResend' => 1], $res->routeParameters);
        $this->assertSame('Es wurden 0 unbekannte Benutzer gefunden', $res->flashMessage);
        $this->assertSame('info', $res->flashType);
    }

    public function testRedirectToEmailSending(): void
    {
        $res = UploadResult::redirectToEmailSending(false, false);

        $this->assertSame('send_emails', $res->redirectRoute);
        $this->assertSame(['testMode' => 0, 'forceResend' => 0], $res->routeParameters);
        $this->assertSame('CSV-Datei erfolgreich verarbeitet', $res->flashMessage);
        $this->assertSame('success', $res->flashType);
    }

    public function testError(): void
    {
        $res = UploadResult::error('Fehler X');

        $this->assertSame('csv_upload', $res->redirectRoute);
        $this->assertSame([], $res->routeParameters);
        $this->assertSame('Fehler X', $res->flashMessage);
        $this->assertSame('error', $res->flashType);
    }

    public function testPropertiesAreReadonly(): void
    {
        $res = UploadResult::error('msg');

        $this->expectException(\Error::class);
        // Attempting to modify a readonly property should throw an Error
        $res->flashMessage = 'other';
    }
}
