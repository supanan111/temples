<?php
/**
 * tests/run_tests.php — Automated Unit Test Runner
 * รันด้วย: php tests/run_tests.php
 */

require_once __DIR__ . '/TestCase.php';

$testDir = __DIR__ . '/Unit';
$testFiles = glob($testDir . '/*Test.php');

if (!$testFiles) {
    echo "No test files found in {$testDir}\n";
    exit(1);
}

$totalTests = 0;
$totalAssertions = 0;
$passedCount = 0;
$failedCount = 0;
$failures = [];

$startTime = microtime(true);

echo "\n";
echo "========================================================\n";
echo " 🧪 ThumWadThaiHub — Automated Unit Test Suite\n";
echo "========================================================\n\n";

foreach ($testFiles as $file) {
    require_once $file;

    $className = 'Tests\\Unit\\' . basename($file, '.php');
    if (!class_exists($className)) {
        continue;
    }

    $refClass = new ReflectionClass($className);
    $methods = $refClass->getMethods(ReflectionMethod::IS_PUBLIC);
    $testMethods = array_filter($methods, static function ($m) {
        return str_starts_with($m->getName(), 'test');
    });

    if (empty($testMethods)) continue;

    $shortName = basename($file, '.php');
    echo "📦 {$shortName}\n";

    foreach ($testMethods as $method) {
        $totalTests++;
        $methodName = $method->getName();

        /** @var \Tests\TestCase $instance */
        $instance = new $className();

        try {
            // เรียก setUp ถ้ามี
            $refSetUp = new ReflectionMethod($instance, 'setUp');
            $refSetUp->setAccessible(true);
            $refSetUp->invoke($instance);

            // รันเมธอดทดสอบ
            $method->invoke($instance);

            // เรียก tearDown ถ้ามี
            $refTearDown = new ReflectionMethod($instance, 'tearDown');
            $refTearDown->setAccessible(true);
            $refTearDown->invoke($instance);

            $totalAssertions += $instance->getAssertionsCount();
            $passedCount++;
            echo "   ✅ {$methodName}\n";
        } catch (\Throwable $e) {
            $failedCount++;
            $totalAssertions += $instance->getAssertionsCount();
            $failures[] = [
                'class'   => $shortName,
                'method'  => $methodName,
                'message' => $e->getMessage(),
                'file'    => $e->getFile() . ':' . $e->getLine(),
            ];
            echo "   ❌ {$methodName} (FAILED)\n";
        }
    }
    echo "\n";
}

$duration = round((microtime(true) - $startTime) * 1000, 2);

echo "========================================================\n";
if ($failedCount === 0) {
    echo " ✨ RESULT: ALL TESTS PASSED!\n";
    echo " 📊 Tests: {$totalTests} | Assertions: {$totalAssertions} | Time: {$duration} ms\n";
    echo "========================================================\n\n";
    exit(0);
} else {
    echo " 💥 RESULT: {$failedCount} TEST(S) FAILED!\n";
    echo " 📊 Tests: {$totalTests} | Assertions: {$totalAssertions} | Time: {$duration} ms\n";
    echo "========================================================\n\n";

    echo "Detailed Failures:\n";
    foreach ($failures as $idx => $fail) {
        $num = $idx + 1;
        echo " {$num}) {$fail['class']}::{$fail['method']}\n";
        echo "    {$fail['message']}\n";
        echo "    at {$fail['file']}\n\n";
    }
    exit(1);
}

