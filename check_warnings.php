<?php
$xml = simplexml_load_file('./var/phpunit-warnings.xml');
$warnings = 0;

foreach ($xml->xpath('//*') as $element) {
    // In PHPUnit, warnings are stored as testcases with nodes
    $attrs = $element->attributes();
    if (isset($attrs['warnings']) && (int)$attrs['warnings'] > 0) {
        $warnings++;
        echo "Test: " . $attrs['name'] . " - Warnings: " . $attrs['warnings'] . "\n";
    }
}

// Check the root element for summary
echo "\n=== Root Element ===\n";
foreach ($xml->attributes() as $key => $val) {
    echo "$key: $val\n";
}

echo "\n=== First few testcases ===\n"
;
$count = 0;
foreach ($xml->xpath('//testcase') as $testcase) {
    if ($count++ > 5) break;
    echo "Test: " . $testcase['name'] . " - Warnings: " . ($testcase['warnings'] ?? '0') . "\n";
}
?>
