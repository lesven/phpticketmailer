<?php

namespace App\Tests\Dto;

use App\Dto\TemplateResolutionResult;
use PHPUnit\Framework\TestCase;

class TemplateResolutionResultTest extends TestCase
{
    public function testConstructorWithContentOnly(): void
    {
        $result = new TemplateResolutionResult('Hello {{ticketId}}!');

        $this->assertSame('Hello {{ticketId}}!', $result->content);
        $this->assertNull($result->inputCreated);
        $this->assertNull($result->parsedDate);
        $this->assertNull($result->selectedTemplateName);
        $this->assertNull($result->selectedTemplateValidFrom);
        $this->assertNull($result->selectionMethod);
        $this->assertSame([], $result->allTemplates);
    }

    public function testConstructorWithAllParameters(): void
    {
        $templates = [
            ['id' => 1, 'name' => 'Default', 'validFrom' => null],
            ['id' => 2, 'name' => 'Q1-2024', 'validFrom' => '2024-01-01'],
        ];

        $result = new TemplateResolutionResult(
            content: 'Ticket content here',
            inputCreated: '01.03.2024',
            parsedDate: '2024-03-01',
            selectedTemplateName: 'Q1-2024',
            selectedTemplateValidFrom: '2024-01-01',
            selectionMethod: 'date_match',
            allTemplates: $templates
        );

        $this->assertSame('Ticket content here', $result->content);
        $this->assertSame('01.03.2024', $result->inputCreated);
        $this->assertSame('2024-03-01', $result->parsedDate);
        $this->assertSame('Q1-2024', $result->selectedTemplateName);
        $this->assertSame('2024-01-01', $result->selectedTemplateValidFrom);
        $this->assertSame('date_match', $result->selectionMethod);
        $this->assertCount(2, $result->allTemplates);
    }

    public function testToDebugArrayContainsAllKeys(): void
    {
        $result = new TemplateResolutionResult(
            content: 'Content',
            inputCreated: '15.06.2023',
            parsedDate: '2023-06-15',
            selectedTemplateName: 'Summer-2023',
            selectedTemplateValidFrom: '2023-06-01',
            selectionMethod: 'fallback'
        );

        $debug = $result->toDebugArray();

        $this->assertArrayHasKey('inputCreated', $debug);
        $this->assertArrayHasKey('parsedDate', $debug);
        $this->assertArrayHasKey('selectedTemplateName', $debug);
        $this->assertArrayHasKey('selectedTemplateValidFrom', $debug);
        $this->assertArrayHasKey('selectionMethod', $debug);
        $this->assertArrayHasKey('allTemplates', $debug);
    }

    public function testToDebugArrayValues(): void
    {
        $templates = [['id' => 5, 'name' => 'T5', 'validFrom' => '2024-05-01']];

        $result = new TemplateResolutionResult(
            content: 'body',
            inputCreated: '01.05.2024',
            parsedDate: '2024-05-01',
            selectedTemplateName: 'T5',
            selectedTemplateValidFrom: '2024-05-01',
            selectionMethod: 'exact_date',
            allTemplates: $templates
        );

        $debug = $result->toDebugArray();

        $this->assertSame('01.05.2024', $debug['inputCreated']);
        $this->assertSame('2024-05-01', $debug['parsedDate']);
        $this->assertSame('T5', $debug['selectedTemplateName']);
        $this->assertSame('2024-05-01', $debug['selectedTemplateValidFrom']);
        $this->assertSame('exact_date', $debug['selectionMethod']);
        $this->assertSame($templates, $debug['allTemplates']);
    }

    public function testToDebugArrayDoesNotIncludeContent(): void
    {
        $result = new TemplateResolutionResult('some content');

        $debug = $result->toDebugArray();

        $this->assertArrayNotHasKey('content', $debug);
    }

    public function testToDebugArrayWithNullValues(): void
    {
        $result = new TemplateResolutionResult('body');

        $debug = $result->toDebugArray();

        $this->assertNull($debug['inputCreated']);
        $this->assertNull($debug['parsedDate']);
        $this->assertNull($debug['selectedTemplateName']);
        $this->assertNull($debug['selectedTemplateValidFrom']);
        $this->assertNull($debug['selectionMethod']);
        $this->assertSame([], $debug['allTemplates']);
    }
}
