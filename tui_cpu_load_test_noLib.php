<?php

/**
 * CPU Load Testing Script
 *
 * This script provides a real-time CPU load testing tool with a Terminal User Interface (TUI).
 * It simulates and monitors CPU load, targeting 70% utilization over a 60-second period.
 *
 * Display Features:
 * - Real-time CPU load bar with color-coded indicators:
 *   [ | ] Green  - Within 5% of target
 *   [ < ] Yellow - Below target by 5-25%
 *   [ > ] Yellow - Above target by 5-25%
 *   [ < ] Red    - Below target by >25%
 *   [ > ] Red    - Above target by >25%
 *
 * - Progress bar showing test completion
 * - Time remaining countdown
 * - Statistics showing maximum and average deviations
 *
 * Fixed Parameters:
 * - Target CPU Load: 70%
 * - Duration: 60 seconds
 *
 * Control:
 * - Ctrl+C to stop the test early
 *
 * Usage:
 *   php tui_cpu_load_test.php
 *   Configurables just before the function call to the bottom
 *
 * Requirements:
 * - PHP 5.6.0 or higher
 * - Linux/Unix terminal with ANSI support
 * - sys_getloadavg() function enabled
 */

function flushOutput()
{
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    ob_start();
}

function getCpuLoad()
{
    static $previous_info = null;

    $load = sys_getloadavg();
    $cpu_info = file_get_contents('/proc/stat');
    $cpu_lines = explode("\n", $cpu_info);
    $cpu = explode(' ', trim(preg_replace('/\s+/', ' ', $cpu_lines[0])));

    $total = $cpu[1] + $cpu[2] + $cpu[3] + $cpu[4] + $cpu[5] + $cpu[6] + $cpu[7];
    $idle = $cpu[4];

    if ($previous_info !== null) {
        $delta_total = $total - $previous_info['total'];
        $delta_idle = $idle - $previous_info['idle'];

        $cpu_usage = 100 * (1 - $delta_idle / $delta_total);
        $previous_info = ['total' => $total, 'idle' => $idle];

        return $cpu_usage;
    }

    $previous_info = ['total' => $total, 'idle' => $idle];
    return 0;
}

function applyCpuLoad($targetPercent, $duration = 0.1)
{
    $startTime = microtime(true);
    $interval = 0.1; // 100ms intervals

    while ((microtime(true) - $startTime) < $duration) {
        $cycleStart = microtime(true);

        if ($targetPercent > 0) {
            // Calculate work and sleep durations for this cycle
            $workDuration = ($targetPercent / 100) * $interval;
            $sleepDuration = $interval - $workDuration;

            // Work phase
            $workEnd = microtime(true) + $workDuration;
            while (microtime(true) < $workEnd) {
                // Intensive CPU work
                $x = 0;
                for ($i = 0; $i < 10000; $i++) {
                    $x += pow($i, 2);
                }
            }

            // Sleep phase
            if ($sleepDuration > 0) {
                usleep((int)($sleepDuration * 1000000));
            }
        } else {
            usleep(100000); // Sleep for full interval if target is 0%
        }
    }
}

function showProgressInfo($cpuLoad, $targetLoadPercent, $timeElapsed, $totalDuration, $maxDeviation, $avgDeviation, $length = 30)
{
    static $initialized = false;
    static $lines = 7;

    if (!$initialized) {
        echo "Starting CPU Load Test\n";
        echo "Target: {$targetLoadPercent}% CPU for {$totalDuration} seconds\n\n";
        echo "CPU Load: [" . str_repeat('░', $length) . "] waiting...\n";
        echo "Progress: [" . str_repeat('░', $length) . "] 0%\n";
        echo "Time Remaining: calculating...\n";
        echo "Statistics:\n";

        $initialized = true;
        return;
    }

    // Move cursor up
    echo "\033[{$lines}A";

    // Generate bars
    $cpuFilled = round(($cpuLoad / 100) * $length);

    // Calculate deviation and determine color
    $deviation = $cpuLoad - $targetLoadPercent;
    $deviationStr = sprintf("%+6.1f%%", $deviation);

    if (abs($deviation) <= 5) {
        $color = "\033[32m"; // Green
        $indicator = $color . "[ | ]" . "\033[0m";
    } else {
        $color = abs($deviation) > 25 ? "\033[31m" : "\033[33m"; // Red or Yellow
        $indicator = $color . ($deviation > 0 ? "[ > ]" : "[ < ]") . "\033[0m";
    }

    // Create colored CPU bar
    $cpuBar = "[" . $color .
        str_repeat('█', $cpuFilled) .
        str_repeat('░', $length - $cpuFilled) .
        "\033[0m]";

    $timePercent = round(($timeElapsed / $totalDuration) * 100);
    $timeFilled = round(($timeElapsed / $totalDuration) * $length);
    $timeBar = "[" . str_repeat('█', $timeFilled) . str_repeat('░', $length - $timeFilled) . "]";

    // Skip header lines
    echo "\033[3B";

    // Clear and update each line with fixed-width formatting
    printf("\r\033[KCPU Load: %s %5.1f%% vs Target: %3d%% (%s) %s\n",
        $cpuBar, $cpuLoad, $targetLoadPercent, $deviationStr, $indicator);
    printf("\033[KProgress: %s %3d%%\n",
        $timeBar, $timePercent);
    printf("\033[KTime Remaining: %5.1f seconds\n",
        max(0, $totalDuration - $timeElapsed));
    echo "\033[KStatistics:\n";
    printf("\033[KMax Deviation: %6.1f%% | Avg Deviation: %6.1f%%",
        $maxDeviation, $avgDeviation);

    flush();
}


function controlledCpuLoad($targetLoadPercent = 50, $durationSeconds = 30, $barLength = 30, $updateInterval)
{
    $startTime = microtime(true);
    $deviations = [];
    $lastUpdate = 0;

    // Initialize CPU load monitoring
    getCpuLoad();
    usleep(100000); // Wait for initial reading

    while ((microtime(true) - $startTime) < $durationSeconds) {
        $currentTime = microtime(true);
        $elapsed = $currentTime - $startTime;

        // Get current CPU load before applying new load
        $currentLoad = getCpuLoad();

        // Apply CPU load
        applyCpuLoad($targetLoadPercent, 0.1);

        // Update display at interval
        if (($currentTime - $lastUpdate) >= $updateInterval) {
            // Calculate statistics
            $deviation = abs($targetLoadPercent - $currentLoad);
            $deviations[] = $deviation;
            $maxDeviation = max($deviations);
            $avgDeviation = array_sum($deviations) / count($deviations);

            // Update display
            showProgressInfo($currentLoad, $targetLoadPercent, $elapsed, $durationSeconds, $maxDeviation, $avgDeviation, $barLength);

            $lastUpdate = $currentTime;
        }

        // Small sleep to allow CPU measurements
        usleep(10000);
    }

    echo "\n\nTest completed.\n";
}

// Example usage
$targetCpuLoad = 50;    // 50% CPU load
$duration = 60;         // 30 seconds
$barLength = 60;        // Length of progress bar
$updateInterval = 0.15; // Update display every 150ms

controlledCpuLoad($targetCpuLoad, $duration, $barLength, $updateInterval);