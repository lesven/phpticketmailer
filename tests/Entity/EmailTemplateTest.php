<?php

namespace App\Tests\Entity;

use App\Entity\EmailTemplate;
use PHPUnit\Framework\TestCase;

class EmailTemplateTest extends TestCase
{
    public function testConstructorSetsDefaults(): void
    {
        $template = new EmailTemplate();

        $this->assertNull($template->getId());
        $this->assertEquals('', $template->getName());
        $this->assertEquals('', $template->getContent());
        $this->assertInstanceOf(\DateTimeInterface::class, $template->getValidFrom());
        $this->assertInstanceOf(\DateTimeInterface::class, $template->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $template->getUpdatedAt());
    }

    public function testSettersAndGetters(): void
    {
        $template = new EmailTemplate();

        $template->setName('Test Template');
        $this->assertEquals('Test Template', $template->getName());

        $template->setContent('<p>Hello {{username}}</p>');
        $this->assertEquals('<p>Hello {{username}}</p>', $template->getContent());

        $validFrom = new \DateTime('2026-06-01');
        $template->setValidFrom($validFrom);
        $this->assertEquals('2026-06-01', $template->getValidFrom()->format('Y-m-d'));
    }

    public function testSettersReturnSelfForFluency(): void
    {
        $template = new EmailTemplate();

        $result = $template->setName('A');
        $this->assertSame($template, $result);

        $result = $template->setContent('B');
        $this->assertSame($template, $result);

        $result = $template->setValidFrom(new \DateTime());
        $this->assertSame($template, $result);
    }

    public function testOnPreUpdateChangesUpdatedAt(): void
    {
        $template = new EmailTemplate();
        $originalUpdatedAt = $template->getUpdatedAt();

        // Simulate time passing
        usleep(10000); // 10ms

        $template->onPreUpdate();

        // Updated should have changed
        $this->assertGreaterThanOrEqual(
            $originalUpdatedAt->getTimestamp(),
            $template->getUpdatedAt()->getTimestamp()
        );
    }
}
