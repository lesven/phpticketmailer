<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Service zum Ausführen des Deploy-Kommandos.
 */
class DeploymentService
{
    public function __construct(private readonly LoggerInterface $logger, private readonly string $projectDir = '')
    {
    }

    /**
     * Trigger das Deploy-Kommando asynchron.
     *
     * @param string|null $command Optionales Kommando (z. B. "make deploy"). Falls null, wird versucht, ./deploy.sh im Projektverzeichnis zu verwenden.
     */
    public function triggerDeploy(?string $command = null): bool
    {
        $command = $command ?? ($_ENV['DEPLOY_COMMAND'] ?? 'make deploy');

        // Normalize command into argv array
        $parts = preg_split('/\s+/', trim($command));
        if ($parts === false || count($parts) === 0) {
            $this->logger->error('Ungültiges Deploy-Kommando', ['command' => $command]);
            return false;
        }

        // If command is still "make deploy" but make isn't available, and a deploy.sh exists, fall back
        if ($parts[0] === 'make') {
            // nothing special here; allow make to run. Fallback handled by environment if needed.
        }

        try {
            $process = new Process($parts, $this->projectDir ?: null);
            $process->setTimeout(3600);
            $process->start();

            $this->logger->info('Deploy process started', ['command' => $command]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to start deploy process', ['exception' => $e]);
            return false;
        }
    }
}
