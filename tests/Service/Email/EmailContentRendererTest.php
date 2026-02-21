<?php

namespace App\Tests\Service\Email;

use App\Service\Email\EmailContentRenderer;
use App\Service\TemplateService;
use App\ValueObject\EmailAddress;
use App\ValueObject\TicketData;
use PHPUnit\Framework\TestCase;

class EmailContentRendererTest extends TestCase
{
    private $templateService;

    protected function setUp(): void
    {
        $this->templateService = $this->createMock(TemplateService::class);
        $this->templateService->method('resolveTemplateForTicketDate')
            ->willReturn([
                'content' => '<p>Template content</p>',
                'debug' => ['selectionMethod' => 'mock'],
            ]);
    }

    public function testRenderReplacesPlaceholders(): void
    {
        $renderer = new EmailContentRenderer($this->templateService, sys_get_temp_dir());
        $template = 'Hallo {{username}}, Ticket: {{ticketId}}, Link: {{ticketLink}}, Name: {{ticketName}}, Due: {{dueDate}}';
        $ticket = TicketData::fromStrings('T-123', 'jsmith', 'Problem');

        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('jsmith@example.com'));

        $result = $renderer->render($template, $ticket, $user, 'https://tickets.example', false);

        $this->assertStringContainsString('T-123', $result);
        $this->assertStringContainsString('jsmith', $result);
        $this->assertStringContainsString('https://tickets.example/T-123', $result);
        $this->assertStringContainsString('Problem', $result);
        $this->assertStringNotContainsString('{{ticketId}}', $result);
        $this->assertStringNotContainsString('{{dueDate}}', $result);
        // Deutsches Datum: z.B. "28. Februar 2026"
        $this->assertMatchesRegularExpression('/\d{1,2}\.\s+\p{L}+\s+\d{4}/u', $result);
    }

    public function testRenderAddsTestModePrefix(): void
    {
        $renderer = new EmailContentRenderer($this->templateService, sys_get_temp_dir());
        $template = 'Hello {{username}}';
        $ticket = TicketData::fromStrings('T-1', 'bob', 'Test');

        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('bob@example.com'));

        $result = $renderer->render($template, $ticket, $user, 'https://example.com', true);

        $this->assertStringContainsString('*** TESTMODUS - E-Mail wäre an bob@example.com gegangen ***', $result);
    }

    public function testRenderNonTestModeDoesNotPrefix(): void
    {
        $renderer = new EmailContentRenderer($this->templateService, sys_get_temp_dir());
        $template = 'Hello {{username}}';
        $ticket = TicketData::fromStrings('T-1', 'bob', 'Test');

        $user = $this->createMock(\App\Entity\User::class);
        $user->method('getEmail')->willReturn(EmailAddress::fromString('bob@example.com'));

        $result = $renderer->render($template, $ticket, $user, 'https://example.com', false);

        $this->assertStringNotContainsString('*** TESTMODUS', $result);
    }

    public function testGetGlobalTemplateFallbackReturnsDefault(): void
    {
        $renderer = new EmailContentRenderer($this->templateService, sys_get_temp_dir());
        $tpl = $renderer->getGlobalTemplate();

        $this->assertStringContainsString('Sehr geehrte', $tpl);
        $this->assertStringContainsString('{{ticketId}}', $tpl);
    }

    public function testGetGlobalTemplateReadsHtmlFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/email_tpl_test_' . uniqid();
        mkdir($tmpDir . '/templates/emails', 0777, true);
        file_put_contents($tmpDir . '/templates/emails/email_template.html', '<p>HTML TPL</p>');

        $renderer = new EmailContentRenderer($this->templateService, $tmpDir);
        $tpl = $renderer->getGlobalTemplate();

        $this->assertStringContainsString('HTML TPL', $tpl);

        unlink($tmpDir . '/templates/emails/email_template.html');
        rmdir($tmpDir . '/templates/emails');
        rmdir($tmpDir . '/templates');
        rmdir($tmpDir);
    }

    public function testResolveTemplateForTicketStoresDebugInfo(): void
    {
        $renderer = new EmailContentRenderer($this->templateService, sys_get_temp_dir());
        $ticket = TicketData::fromStrings('DBG-001', 'user', 'Debug');

        $result = $renderer->resolveTemplateForTicket($ticket, 'fallback');

        $this->assertEquals('<p>Template content</p>', $result);
        $debugInfo = $renderer->getTemplateDebugInfo();
        $this->assertArrayHasKey('DBG-001', $debugInfo);
        $this->assertEquals('mock', $debugInfo['DBG-001']['selectionMethod']);
    }

    public function testResolveTemplateForTicketFallsBackToGlobal(): void
    {
        $templateService = $this->createMock(TemplateService::class);
        $templateService->method('resolveTemplateForTicketDate')
            ->willReturn([
                'content' => '',
                'debug' => ['selectionMethod' => 'no_created_date'],
            ]);

        $renderer = new EmailContentRenderer($templateService, sys_get_temp_dir());
        $ticket = TicketData::fromStrings('FB-001', 'user', 'Fallback');

        $result = $renderer->resolveTemplateForTicket($ticket, 'global fallback content');

        $this->assertEquals('global fallback content', $result);
        $debugInfo = $renderer->getTemplateDebugInfo();
        $this->assertStringContainsString('fallback_global', $debugInfo['FB-001']['selectionMethod']);
    }
}
