<?php
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

// Run a simple test to debug deprecations
$output = [];
$exitCode = 0;
exec("APP_ENV=test php vendor/bin/phpunit --no-coverage -v 2>&1", $output, $exitCode);

$deprecations = array_filter($output, fn($line) => strpos($line, 'Deprecation') !== false || strpos($line, 'deprecated') !== false);
if (!empty($deprecations)) {
    echo "=== Deprecations found ===\n";
    foreach ($deprecations as $line) {
        echo $line . "\n";
    }
} else {
    echo "No deprecations directly in output. Checking for W (warnings) markers.\n";
    $hasWarnings = array_filter($output, fn($line) => strpos($line, 'W') !== false);
    if (!empty($hasWarnings)) {
        echo "Found lines with 'W':\n";
        foreach ($hasWarnings as $line) {
            echo $line . "\n";
        }
    }
}
?>
