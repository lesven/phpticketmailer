<?php
namespace App\Tests\Service;

use App\Service\CsvUploadOrchestrator;
use App\Entity\CsvFieldConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CsvUploadOrchestratorTest extends TestCase
{
    public function testProcessUploadRedirectsToUnknownUsersWhenPresent(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, "a\n");
        $uploaded = new UploadedFile($tmp, 'test.csv', null, null, true);

        $processor = $this->createMock(\App\Service\CsvProcessor::class);
        $processor->method('process')->willReturn(['validTickets'=>[], 'invalidRows'=>[], 'unknownUsers'=>['u1']]);

        $repo = $this->createMock(\App\Repository\CsvFieldConfigRepository::class);
    $session = $this->createMock(\App\Service\SessionManager::class);
    $session->method('getUnknownUsers')->willReturn(['u1']);
    $session->method('storeUploadResults')->willReturnCallback(function() { /* void */ });

        $creator = $this->createMock(\App\Service\UserCreator::class);

        $cfg = $this->createMock(CsvFieldConfig::class);

        $orchestrator = new CsvUploadOrchestrator($processor, $repo, $session, $creator);
    $res = $orchestrator->processUpload($uploaded, true, false, $cfg);

    $this->assertIsObject($res);
    $this->assertTrue(property_exists($res, 'redirectRoute'));
    $this->assertEquals('unknown_users', $res->redirectRoute);

        @unlink($tmp);
    }
}
