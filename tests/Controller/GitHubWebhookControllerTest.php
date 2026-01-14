<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GitHubWebhookControllerTest extends WebTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Ensure test secret is available to controller via env
        putenv('GITHUB_WEBHOOK_SECRET=testing-secret');
        // Use a harmless deploy command in tests so we don't run real deploys
        putenv('DEPLOY_COMMAND=echo test-deploy');
    }

    public function testInvalidSignatureReturns401(): void
    {
        $client = static::createClient();
        $payload = json_encode(['ref' => 'refs/heads/main']);

        $client->request('POST', '/webhook/github-deploy', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);

        $this->assertEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testValidPushToMainReturns202(): void
    {
        $client = static::createClient();
        $payload = json_encode(['ref' => 'refs/heads/main']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'testing-secret');

        $client->request('POST', '/webhook/github-deploy', [], [], [
            'HTTP_X-Hub-Signature-256' => $signature,
            'HTTP_X-GitHub-Event' => 'push',
            'CONTENT_TYPE' => 'application/json'
        ], $payload);

        $this->assertEquals(202, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Deploy triggered', $client->getResponse()->getContent());
    }
}
