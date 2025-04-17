<?php

function showSpinner($seconds = 5) {
    $chars = ['|', '/', '-', '\\'];
    $start = time();

    if (ob_get_level() == 0) {
        ob_start();
    }

    while (time() - $start < $seconds) {
        foreach ($chars as $char) {
            echo "Loading... $char";
            echo "\033[K"; // Clear line
            echo "\r"; // Return to start
            flush();
            if (ob_get_length() > 0) {
                ob_flush();
            }
            usleep(100000);
        }
    }
    echo "Done!    \n";
}

// Demo of different TUI concepts
function demoTUI() {
    if (ob_get_level() == 0) {
        ob_start();
    }

    // Clear screen
    echo "\033[2J\033[H";
    flush();

    // Colors and styling
    echo "\033[1;32m"; // Bold green
    echo "Starting TUI Demo...\n";
    echo "\033[0m"; // Reset formatting
    flush();
    ob_flush();
    sleep(1);

    // Basic progress indicator
    echo "Task 1: Simple spinner\n";
    flush();
    ob_flush();
    showSpinner(2);

    // Progress bar
    echo "\nTask 2: Basic progress bar\n";
    flush();
    ob_flush();
    for ($i = 0; $i <= 10; $i++) {
        $percent = $i * 10;
        $bars = str_repeat('█', $i);
        $spaces = str_repeat('░', 10 - $i);
        echo "Progress: [$bars$spaces] $percent%";
        echo "\r";
        flush();
        ob_flush();
        usleep(200000);
    }
    echo "\n";
    flush();
    ob_flush();

    // Moving cursor and updating specific screen areas
    echo "\nTask 3: Multi-line updates\n";
    echo "Line 1: \n";
    echo "Line 2: \n";
    echo "Line 3: \n";
    flush();
    ob_flush();

    for ($i = 0; $i < 3; $i++) {
        echo "\033[3A"; // Move up 3 lines
        echo "\033[K";  // Clear line
        echo "Line 1: Processing step " . ($i + 1) . "/3\n";
        echo "\033[K";  // Clear line
        echo "Line 2: " . str_repeat('*', $i + 1) . "\n";
        echo "\033[K";  // Clear line
        echo "Line 3: " . str_repeat('>', $i + 1) . "\n";
        flush();
        ob_flush();
        sleep(1);
    }

    // Color palette demo
    echo "\nTask 4: Color palette\n";
    flush();
    ob_flush();
    for ($i = 0; $i < 8; $i++) {
        echo "\033[4" . $i . "m"; // Background color
        echo "\033[37m"; // White text
        echo " Color $i ";
        echo "\033[0m"; // Reset
        echo " ";
        flush();
        ob_flush();
    }
    echo "\n";
    flush();
    ob_flush();

    // Final spinner with different message
    echo "\nTask 5: Finalizing demo\n";
    flush();
    ob_flush();
    showSpinner(2);

    // New gradient effects demo
    echo "\nTask 6: Gradient Effects\n";

    // Red to Yellow gradient (196-226)
    echo "Red to Yellow: ";
    for ($i = 0; $i < 30; $i++) {
        echo "\033[38;5;" . (196 + $i) . "m■\033[0m";
        flush();
        ob_flush();
        usleep(50000);
    }
    echo "\n";

    // Blue to Cyan gradient (21-51)
    echo "Blue to Cyan:  ";
    for ($i = 0; $i < 30; $i++) {
        echo "\033[38;5;" . (21 + $i) . "m■\033[0m";
        flush();
        ob_flush();
        usleep(50000);
    }
    echo "\n";

    // Purple to Pink gradient (90-219)
    echo "Purple to Pink:";
    for ($i = 0; $i < 30; $i++) {
        echo "\033[38;5;" . (90 + $i) . "m■\033[0m";
        flush();
        ob_flush();
        usleep(50000);
    }
    echo "\n";

    // Reset formatting and add some spacing
    echo "\033[0m\n";
    echo "Demo completed!\n";
    flush();
    ob_flush();
}

// Run the demo
demoTUI();