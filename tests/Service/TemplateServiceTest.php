<?php

namespace App\Tests\Service;

use App\Entity\EmailTemplate;
use App\Repository\EmailTemplateRepository;
use App\Service\TemplateService;
use PHPUnit\Framework\TestCase;

class TemplateServiceTest extends TestCase
{
    private TemplateService $service;
    private EmailTemplateRepository $repository;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EmailTemplateRepository::class);
        $this->tmpDir = sys_get_temp_dir() . '/tpl_svc_' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        $this->service = new TemplateService($this->repository, $this->tmpDir);
    }

    protected function tearDown(): void
    {
        // Cleanup temp directory
        $this->rrmdir($this->tmpDir);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // ── getAllTemplates ──

    public function testGetAllTemplatesReturnsAll(): void
    {
        $t1 = new EmailTemplate();
        $t1->setName('A');
        $t2 = new EmailTemplate();
        $t2->setName('B');

        $this->repository->method('findAllOrderedByValidFrom')->willReturn([$t1, $t2]);

        $result = $this->service->getAllTemplates();
        $this->assertCount(2, $result);
    }

    // ── getTemplate ──

    public function testGetTemplateReturnsNullForUnknownId(): void
    {
        $this->repository->method('find')->with(999)->willReturn(null);
        $this->assertNull($this->service->getTemplate(999));
    }

    public function testGetTemplateReturnsEntity(): void
    {
        $t = new EmailTemplate();
        $t->setName('Found');
        $this->repository->method('find')->with(1)->willReturn($t);

        $result = $this->service->getTemplate(1);
        $this->assertSame($t, $result);
    }

    // ── createTemplate ──

    public function testCreateTemplateSavesAndReturnsEntity(): void
    {
        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (EmailTemplate $tpl) {
                return $tpl->getName() === 'New Template'
                    && $tpl->getValidFrom()->format('Y-m-d') === '2026-03-01'
                    && str_contains($tpl->getContent(), '{{username}}');
            }));

        $result = $this->service->createTemplate('New Template', new \DateTime('2026-03-01'));

        $this->assertInstanceOf(EmailTemplate::class, $result);
        $this->assertEquals('New Template', $result->getName());
    }

    // ── getTemplateContentForTicketDate ──

    public function testGetTemplateContentForTicketDateWithMatchingDate(): void
    {
        $template = new EmailTemplate();
        $template->setContent('<p>Specific template</p>');

        $this->repository->method('findActiveTemplateForDate')
            ->willReturn($template);

        $result = $this->service->getTemplateContentForTicketDate('2026-02-10');

        $this->assertEquals('<p>Specific template</p>', $result);
    }

    public function testGetTemplateContentForTicketDateFallsBackToLatest(): void
    {
        $latest = new EmailTemplate();
        $latest->setContent('<p>Latest template</p>');

        $this->repository->method('findActiveTemplateForDate')->willReturn(null);
        $this->repository->method('findLatestTemplate')->willReturn($latest);

        $result = $this->service->getTemplateContentForTicketDate('invalid-date');

        $this->assertEquals('<p>Latest template</p>', $result);
    }

    public function testGetTemplateContentForTicketDateWithNullDate(): void
    {
        $latest = new EmailTemplate();
        $latest->setContent('<p>Fallback</p>');

        $this->repository->method('findLatestTemplate')->willReturn($latest);

        $result = $this->service->getTemplateContentForTicketDate(null);

        $this->assertEquals('<p>Fallback</p>', $result);
    }

    public function testGetTemplateContentForTicketDateFallsBackToFilesystem(): void
    {
        $this->repository->method('findActiveTemplateForDate')->willReturn(null);
        $this->repository->method('findLatestTemplate')->willReturn(null);

        // Create a filesystem template
        $emailsDir = $this->tmpDir . '/templates/emails';
        mkdir($emailsDir, 0777, true);
        file_put_contents($emailsDir . '/email_template.html', '<p>Filesystem template</p>');

        $result = $this->service->getTemplateContentForTicketDate('2026-01-15');

        $this->assertEquals('<p>Filesystem template</p>', $result);
    }

    public function testGetTemplateContentForTicketDateFallsBackToDefault(): void
    {
        $this->repository->method('findActiveTemplateForDate')->willReturn(null);
        $this->repository->method('findLatestTemplate')->willReturn(null);

        // No filesystem template exists
        $result = $this->service->getTemplateContentForTicketDate('2026-01-15');

        $this->assertStringContainsString('{{username}}', $result);
        $this->assertStringContainsString('Sehr geehrte', $result);
    }

    // ── Date Parsing ──

    public function testGetTemplateContentParsesVariousDateFormats(): void
    {
        $template = new EmailTemplate();
        $template->setContent('<p>Dated</p>');

        // The repository should receive a parsed date
        $this->repository->method('findActiveTemplateForDate')
            ->willReturn($template);

        $formats = [
            '2026-02-13',
            '2026-02-13 10:30:00',
            '13.02.2026',
            '13.02.2026 10:30',
        ];

        foreach ($formats as $fmt) {
            $result = $this->service->getTemplateContentForTicketDate($fmt);
            $this->assertEquals('<p>Dated</p>', $result, "Failed for format: $fmt");
        }
    }

    // ── deleteTemplate ──

    public function testDeleteTemplateCallsRepository(): void
    {
        $template = new EmailTemplate();
        $this->repository->expects($this->once())->method('remove')->with($template);

        $this->service->deleteTemplate($template);
    }

    // ── saveTemplate ──

    public function testSaveTemplateCallsRepository(): void
    {
        $template = new EmailTemplate();
        $this->repository->expects($this->once())->method('save')->with($template);

        $this->service->saveTemplate($template);
    }

    // ── getDefaultContent ──

    public function testGetDefaultContentContainsAllPlaceholders(): void
    {
        $content = $this->service->getDefaultContent();

        $this->assertStringContainsString('{{ticketId}}', $content);
        $this->assertStringContainsString('{{ticketName}}', $content);
        $this->assertStringContainsString('{{username}}', $content);
        $this->assertStringContainsString('{{ticketLink}}', $content);
        $this->assertStringContainsString('{{dueDate}}', $content);
    }
}
