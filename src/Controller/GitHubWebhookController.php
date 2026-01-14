<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Process\Process;

#[Route('/webhook')]
class GitHubWebhookController
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    #[Route('/github-deploy', name: 'webhook_github_deploy', methods: ['POST'])]
    public function deploy(Request $request, \App\Service\DeploymentService $deploymentService): Response
    {
        $secret = $_ENV['GITHUB_WEBHOOK_SECRET'] ?? null;
        if (!$secret) {
            $this->logger->error('GitHub webhook secret is not configured (GITHUB_WEBHOOK_SECRET)');
            return new Response('Server misconfiguration', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $payload = $request->getContent();
        $signatureHeader = $request->headers->get('X-Hub-Signature-256') ?? $request->headers->get('x-hub-signature-256');
        if (!$signatureHeader) {
            $this->logger->warning('Missing X-Hub-Signature-256 header');
            return new Response('Missing signature', Response::HTTP_UNAUTHORIZED);
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expected, $signatureHeader)) {
            $this->logger->warning('Invalid signature for GitHub webhook', ['expected' => $expected, 'received' => $signatureHeader]);
            return new Response('Invalid signature', Response::HTTP_UNAUTHORIZED);
        }

        $event = $request->headers->get('X-GitHub-Event') ?? $request->headers->get('x-github-event');
        $data = json_decode($payload, true);

        // Nur Push-Events verarbeiten
        if ($event !== 'push') {
            $this->logger->info('Ignoring non-push event', ['event' => $event]);
            return new Response('Event ignored', Response::HTTP_NO_CONTENT);
        }

        $branchRef = $data['ref'] ?? null;
        $deployBranch = $_ENV['GITHUB_DEPLOY_BRANCH'] ?? 'refs/heads/main';

        if ($branchRef !== $deployBranch) {
            $this->logger->info('Push to non-deploy branch, ignoring', ['ref' => $branchRef, 'deployBranch' => $deployBranch]);
            return new Response('Branch ignored', Response::HTTP_NO_CONTENT);
        }

        // Trigger deploy via DeploymentService. Default-Kommando ist "make deploy" oder ENV-Variable DEPLOY_COMMAND.
        $started = $deploymentService->triggerDeploy();
        if ($started) {
            $this->logger->info('Deploy triggered via DeploymentService');
            return new Response('Deploy triggered', Response::HTTP_ACCEPTED);
        }

        return new Response('Failed to trigger deploy', Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
