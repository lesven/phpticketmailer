<?php

namespace App\Tests\Service;

use App\Service\DeploymentService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DeploymentServiceTest extends TestCase
{
    public function testTriggerDeployReturnsTrueForEchoCommand(): void
    {
        $logger = new NullLogger();
        $service = new DeploymentService($logger, __DIR__ . '/../../');

        $result = $service->triggerDeploy('echo hello-world');

        $this->assertTrue($result);
    }
}
