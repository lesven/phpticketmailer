<?php
$_ENV['SYMFONY_DEPRECATIONS_HELPER'] = 'verbose=1';

require_once 'vendor/autoload.php';

// Run a single test and monitor deprecations
$bootstrap = new \Symfony\Component\Dotenv\Dotenv();
$bootstrap->load('.env.test');

try {
    $test = new \App\Tests\Service\EmailServiceTest();
    $test->setUp();
   echo "setUp complete\n";
    $test->testSendTicketEmailsCallsWrapper();
    echo "Test completed\n";
    $test->tearDown();
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "Deprecations end\n";
?>
