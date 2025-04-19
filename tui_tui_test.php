<?php

/**
 * Flushes the system output buffer and the PHP output buffer if active and contains content.
 */
function flushOutput() {
    // Flush the system output buffer (e.g., web server buffer)
    flush();

    // Flush the PHP output buffer only if it's active and has content
    if (ob_get_level() > 0 && ob_get_length() > 0) {
        ob_flush();
    }
}

/**
 * Displays a simple command-line spinner for a given duration.
 *
 * @param int $seconds Duration in seconds to show the spinner.
 */
function showSpinner($seconds = 5) {
    $chars = ['|', '/', '-', '\\'];
    $start = time();

    // Ensure output buffering is started if not already active
    if (ob_get_level() == 0) {
        ob_start();
    }

    while (time() - $start < $seconds) {
        foreach ($chars as $char) {
            echo "Loading... $char";
            echo "\033[K"; // Clear line to the end
            echo "\r";     // Return cursor to the start of the line
            flushOutput(); // Use the common flush function
            usleep(100000); // 100ms pause
        }
    }
    // Clear the spinner line and show "Done!"
    echo "\033[K";
    echo "Done!    \n";
    flushOutput(); // Flush the final output
}

/**
 * Runs a demonstration of various TUI (Text User Interface) concepts.
 */
function demoTUI() {
    // Ensure output buffering is started if not already active
    if (ob_get_level() == 0) {
        ob_start();
    }

    // Clear screen and move cursor to home position
    echo "\033[2J\033[H";
    flushOutput();

    // Colors and styling
    echo "\033[1;32m"; // Bold green
    echo "Starting TUI Demo...\n";
    echo "\033[0m"; // Reset formatting
    flushOutput();
    sleep(1);

    // Basic progress indicator
    echo "Task 1: Simple spinner\n";
    flushOutput();
    showSpinner(2); // showSpinner now uses flushOutput internally

    // Progress bar
    echo "\nTask 2: Basic progress bar\n";
    flushOutput();
    $barLength = 20; // Define bar length
    for ($i = 0; $i <= $barLength; $i++) {
        $percent = round(($i / $barLength) * 100);
        $bars = str_repeat('█', $i);
        $spaces = str_repeat('░', $barLength - $i);
        // Use \r to return cursor, \033[K to clear rest of line if needed
        echo "Progress: [$bars$spaces] $percent%";
        echo "\r";
        flushOutput();
        usleep(80000); // Slightly faster update
    }
    // Clear the progress bar line and move to the next line
    echo "\033[K";
    echo "\n";
    flushOutput();

    // Moving cursor and updating specific screen areas
    echo "\nTask 3: Multi-line updates\n";
    echo "Line 1: \n";
    echo "Line 2: \n";
    echo "Line 3: \n";
    flushOutput();

    for ($i = 0; $i < 4; $i++) {
        echo "\033[3A"; // Move cursor up 3 lines
        echo "\033[K";  // Clear line 1
        echo "Line 1: Processing step " . ($i + 1) . "/3\n";
        echo "\033[K";  // Clear line 2
        echo "Line 2: " . str_repeat('*', $i + 1) . "\n";
        echo "\033[K";  // Clear line 3
        echo "Line 3: " . str_repeat('>', $i + 1) . "\n";
        flushOutput();
        usleep(300000); // 0.3 seconds
    }

    // Color palette demo (Basic 8 background colors)
    echo "\nTask 4: Color palette\n";
    flushOutput();
    for ($i = 0; $i < 8; $i++) {
        echo "\033[4" . $i . "m"; // Background color 40-47
        echo "\033[37m";          // White text (usually visible on most backgrounds)
        echo " BG $i ";
        echo "\033[0m";           // Reset formatting
        echo " ";                 // Space between samples
        flushOutput();
    }
    echo "\n";
    flushOutput();

    // 256-color gradient effects demo
    echo "\nTask 5: Gradient Effects (256 colors)\n"; // Renumbered task

    // Red to Yellow gradient (ANSI codes 196-226 approx)
    echo "Red to Yellow: ";
    for ($i = 196; $i <= 226; $i++) {
        echo "\033[48;5;" . $i . "m \033[0m"; // Use background color for solid block
        flushOutput();
        usleep(10000); // Faster transition
    }
    echo "\n";

    // Blue to Cyan gradient (ANSI codes 21-51 approx)
    echo "Blue to Cyan:  ";
    for ($i = 21; $i <= 51; $i++) {
        echo "\033[48;5;" . $i . "m \033[0m";
        flushOutput();
        usleep(10000);
    }
    echo "\n";

    // Purple to Pink gradient (ANSI codes 90-129, 160-195, 196-219 approx ranges)
    echo "Purple to Pink:";
    // Example range (adjust as needed for desired effect)
    for ($i = 90; $i <= 129; $i++) {
        echo "\033[48;5;" . $i . "m \033[0m";
        flushOutput();
        usleep(10000);
    }
    for ($i = 160; $i <= 195; $i++) { // Another part of the spectrum
        echo "\033[48;5;" . $i . "m \033[0m";
        flushOutput();
        usleep(5000);
    }
    echo "\n";

    // Reset formatting and add some spacing
    echo "\033[0m\n";
    echo "Demo completed!\n";
    flushOutput();


    // Add calls to the new demos
    demoBoxDrawing();
    demoTextStyles();
    demoTerminalBell();


}

function demoBoxDrawing() {
    echo "\nTask 6: Box Drawing\n";
    flushOutput();
    usleep(200000);

    // Simple Box
    echo "┌───────────┐\n";
    echo "│ Simple Box│\n";
    echo "└───────────┘\n";
    flushOutput();
    sleep(1);

    // Table-like structure
    echo "┌───┬───────┐\n";
    echo "│ A │ B     │\n";
    echo "├───┼───────┤\n";
    echo "│ 1 │ Data  │\n";
    echo "│ 2 │ More  │\n";
    echo "└───┴───────┘\n";
    flushOutput();
    sleep(1);
}


function demoTextStyles() {
    echo "\nTask 7: More Text Styles\n";
    flushOutput();
    usleep(200000);

    echo "\033[4mUnderlined Text\033[0m\n"; // 4 = Underline
    flushOutput();
    sleep(1);

    echo "\033[7mReverse Video\033[0m\n";   // 7 = Reverse video
    flushOutput();
    sleep(1);

    // Blinking might not work on all terminals or might be disabled
    echo "\033[5mBlinking Text (maybe?)\033[0m\n"; // 5 = Blink
    flushOutput();
    sleep(1);

    // Combining styles (e.g., Bold Red Underlined)
    echo "\033[1;31;4mBold Red Underlined\033[0m\n";
    flushOutput();
    sleep(1);
}

function demoTerminalBell() {
    echo "\nTask 8: Terminal Bell\n";
    flushOutput();
    usleep(500000);
    echo "Listen for the beep... \x07"; // \x07 is the BEL character
    flushOutput();
    sleep(1);
    echo "\nDid you hear it?\n";
    flushOutput();
    sleep(1);
}


// Run the demo
demoTUI();

// Optionally end output buffering if it was started by this script
// Note: This might interfere if buffering was started by the web server or calling script.
// Consider if this is truly needed for your use case.
// if (ob_get_level() > 0) {
//     ob_end_flush();
// }

?>