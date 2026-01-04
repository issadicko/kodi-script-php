<?php
/**
 * PHP SDK Compliance Test Runner
 * 
 * Runs all .kodi compliance tests and compares output against .out files
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use KodiScript\KodiScript;

$complianceDir = realpath(__DIR__ . '/../../compliance-tests');
$testCategories = [
    'arithmetic',
    'control-flow',
    'control_flow',
    'data_types',
    'functions',
    'higher_order',
    'limits',
    'natives',
    'operators',
    'strings',
];

$passed = 0;
$failed = 0;
$skipped = 0;
$failedTests = [];

echo "KodiScript PHP SDK - Compliance Tests\n";
echo "======================================\n\n";

foreach ($testCategories as $category) {
    $categoryPath = $complianceDir . '/' . $category;
    if (!is_dir($categoryPath)) {
        continue;
    }

    $kodiFiles = glob($categoryPath . '/*.kodi');
    foreach ($kodiFiles as $kodiFile) {
        $testName = $category . '/' . basename($kodiFile, '.kodi');
        $outFile = substr($kodiFile, 0, -5) . '.out';

        if (!file_exists($outFile)) {
            echo "⚠️  SKIP: $testName (no .out file)\n";
            $skipped++;
            continue;
        }

        $script = file_get_contents($kodiFile);
        $expectedOutput = trim(file_get_contents($outFile));
        $expectedLines = array_filter(explode("\n", $expectedOutput), fn($l) => $l !== '');

        try {
            $result = KodiScript::run($script);
            $actualOutput = implode("\n", $result->output);

            // Normalize output for comparison
            $actualLines = array_filter(explode("\n", $actualOutput), fn($l) => $l !== '');
            $expectedLines = array_values($expectedLines);
            $actualLines = array_values($actualLines);

            // Compare normalized outputs
            $match = true;
            if (count($expectedLines) !== count($actualLines)) {
                $match = false;
            } else {
                for ($i = 0; $i < count($expectedLines); $i++) {
                    $expected = trim($expectedLines[$i]);
                    $actual = trim($actualLines[$i]);

                    // Handle numeric comparison (14 == 14.0 should match)
                    if (is_numeric($expected) && is_numeric($actual)) {
                        if (abs((float) $expected - (float) $actual) > 0.0001) {
                            $match = false;
                            break;
                        }
                    } else if ($expected !== $actual) {
                        $match = false;
                        break;
                    }
                }
            }

            if ($match) {
                echo "✅ PASS: $testName\n";
                $passed++;
            } else {
                echo "❌ FAIL: $testName\n";
                echo "   Expected:\n";
                foreach (array_slice($expectedLines, 0, 5) as $line) {
                    echo "     $line\n";
                }
                echo "   Got:\n";
                foreach (array_slice($actualLines, 0, 5) as $line) {
                    echo "     $line\n";
                }
                $failed++;
                $failedTests[] = $testName;
            }
        } catch (\Throwable $e) {
            echo "❌ ERROR: $testName - " . $e->getMessage() . "\n";
            $failed++;
            $failedTests[] = $testName . ' (error: ' . $e->getMessage() . ')';
        }
    }
}

echo "\n======================================\n";
echo "RESULTS: $passed passed, $failed failed, $skipped skipped\n";

if (!empty($failedTests)) {
    echo "\nFailed tests:\n";
    foreach ($failedTests as $test) {
        echo "  - $test\n";
    }
}

exit($failed > 0 ? 1 : 0);
