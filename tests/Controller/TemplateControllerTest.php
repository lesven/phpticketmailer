<?php

namespace App\Tests\Controller;

use App\Controller\TemplateController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TemplateControllerTest extends TestCase
{
    private string $tmpDir;
    private SluggerInterface $slugger;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/template_ctrl_test_' . uniqid();
        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0777, true);
        }

        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->slugger->method('slug')->willReturnCallback(function ($v) {
            return new \Symfony\Component\String\UnicodeString((string) $v);
        });
    }

    protected function tearDown(): void
    {
        // cleanup tmp dir
        $this->rrmdir($this->tmpDir);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object === '.' || $object === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $object;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public function testManageGetCreatesDefaultTemplateAndRenders(): void
    {
        $controller = $this->getMockBuilder(TemplateController::class)
            ->setConstructorArgs([$this->tmpDir, $this->slugger])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                'template/manage.html.twig',
                $this->callback(function ($params) {
                    // template should be created and preview contain sample ticket id
                    return isset($params['templateExists']) && $params['templateExists'] === true
                        && isset($params['previewContent']) && str_contains($params['previewContent'], 'TICKET-12345');
                })
            )
            ->willReturn(new Response('ok'));

        $request = Request::create('/template', 'GET');

        $response = $controller->manage($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        // file should exist
        $path = $this->tmpDir . '/templates/emails/email_template.txt';
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('Sehr geehrte', $content);
    }

    public function testManagePostUploadsFile(): void
    {
        // create a temp uploaded file
        $source = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($source, "Hallo {{username}}\n");

        $uploaded = new UploadedFile($source, 'mytemplate.txt', null, null, true);

        $controller = $this->getMockBuilder(TemplateController::class)
            ->setConstructorArgs([$this->tmpDir, $this->slugger])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                'template/manage.html.twig',
                $this->callback(function ($params) {
                    return isset($params['message']) && $params['message'] === 'Template wurde erfolgreich hochgeladen.'
                        && isset($params['previewContent']) && str_contains($params['previewContent'], 'Hallo');
                })
            )
            ->willReturn(new Response('ok'));

        $request = Request::create('/template', 'POST');
        $request->files->set('template_file', $uploaded);

        $response = $controller->manage($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $path = $this->tmpDir . '/templates/emails/email_template.txt';
        $this->assertFileExists($path);
        $this->assertStringContainsString('Hallo', file_get_contents($path));
    }

    public function testManagePostUploadMoveExceptionShowsErrorMessage(): void
    {
        $mockFile = $this->createMock(UploadedFile::class);
        $mockFile->method('getClientOriginalName')->willReturn('bad.txt');
        $mockFile->expects($this->once())->method('move')->willThrowException(new \Exception('cannot move'));

        $controller = $this->getMockBuilder(TemplateController::class)
            ->setConstructorArgs([$this->tmpDir, $this->slugger])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with(
                'template/manage.html.twig',
                $this->callback(function ($params) {
                    return isset($params['message']) && str_starts_with($params['message'], 'Fehler beim Hochladen des Templates:');
                })
            )
            ->willReturn(new Response('ok'));

        $request = Request::create('/template', 'POST');
        $request->files->set('template_file', $mockFile);

        $response = $controller->manage($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDownloadCreatesDefaultIfMissingAndReturnsBinaryResponse(): void
    {
        $controller = new TemplateController($this->tmpDir, $this->slugger);

        $path = $this->tmpDir . '/templates/emails/email_template.txt';
        if (file_exists($path)) unlink($path);

        $response = $controller->download();

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('email_template.txt', $disposition);

        $this->assertFileExists($path);
    }
}
