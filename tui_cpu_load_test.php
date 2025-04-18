<?php
/**
 * CPU Load Tester Script (Symfony Console Command)
 *
 * This script simulates a target CPU load percentage for a specified duration
 * and provides a real-time text-based user interface (TUI) to monitor
 * the actual load, progress, and deviation from the target.
 *
 * It is designed for Linux systems that have the /proc/stat file available.
 *
 * Usage: php tui_cpu_load_test.php [options]
 *
 * Options:
 *   --target-load (-t)     Target CPU load percentage (0-100) [default: 50]
 *   --duration (-d)        Duration of the test in seconds [default: 60]
 *   --bar-length (-l)      Length of the progress/load bars [default: 100]
 *   --update-interval (-i) Display update interval in seconds [default: 0.15]
 *
 *      php tui_cpu_load_test.php -t 50 -d 35 -l 100
 */

require __DIR__ . '/vendor/autoload.php';

// Import Symfony Console components
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Cursor; // For cursor manipulation

class CpuLoadTester
{
    private OutputInterface $output;
    private Cursor $cursor; // For cursor control
    private int $targetLoadPercent;
    private int $durationSeconds;
    private int $barLength;
    private float $updateInterval;
    private array $deviations = [];
    private ?array $previousCpuInfo = null;
    private int $dynamicOutputLines = 4; // Number of lines used for dynamic updates

    // Constructor now takes required output and configuration values
    public function __construct(
        OutputInterface $output, // Output is now required
        int $targetLoadPercent,
        int $durationSeconds,
        int $barLength,
        float $updateInterval
    ) {
        if (!file_exists('/proc/stat')) {
            throw new \RuntimeException("Error: /proc/stat not found. This script currently only supports Linux.");
        }

        $this->output = $output; // Use provided output
        $this->cursor = new Cursor($this->output); // Initialize cursor helper

        // Validation is primarily handled by InputOption definitions,
        // but keeping max/min here is good practice.
        $this->targetLoadPercent = max(0, min(100, $targetLoadPercent));
        $this->durationSeconds = max(1, $durationSeconds);
        $this->barLength = max(10, $barLength);
        $this->updateInterval = max(0.05, $updateInterval);
        // Padding is always enabled now
    }

    /**
     * Runs the CPU load test.
     */
    public function run(): void
    {
        // Output buffer clearing might not be strictly necessary with ConsoleOutput
        // but doesn't hurt.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $this->output->writeln(''); // Add vertical space

        $this->displayInitialHeader();

        // Initial CPU reading
        $this->getCpuLoad();
        usleep(100000); // Allow time for a stable reading after first call

        $startTime = microtime(true);
        $lastUpdate = 0;
        $currentLoad = 0.0;

        // Hide cursor during updates for cleaner look
        $this->cursor->hide();

        try { // Wrap main loop in try/finally to ensure cursor is shown again
            while (($currentTime = microtime(true)) - $startTime < $this->durationSeconds) {
                $elapsed = $currentTime - $startTime;

                $this->applyLoadCycle($this->targetLoadPercent);
                $currentLoad = $this->getCpuLoad();

                if ($currentLoad === null) {
                    // Move cursor down before writing error to avoid overwriting display
                    // $this->cursor->moveDown($this->dynamicOutputLines); // May not be needed if error is fatal
                    $this->output->writeln('');
                    $this->output->writeln('<error>Error reading CPU stats. Aborting.</error>');
                    // Throw exception to trigger finally block and command failure
                    throw new \RuntimeException('CPU Stat Read Error');
                }

                $this->deviations[] = abs($this->targetLoadPercent - $currentLoad);

                if (($currentTime - $lastUpdate) >= $this->updateInterval) {
                    $this->updateDisplay($currentLoad, $elapsed);
                    $lastUpdate = $currentTime;
                }
            }

            // Final update before finishing
            $finalLoad = $this->getCpuLoad() ?? $currentLoad ?? 0.0;
            $this->updateDisplay($finalLoad, (float)$this->durationSeconds);

        } finally {
            // Ensure cursor is always shown again, even if errors occur
            $this->cursor->show();
        }

        // Add space before final summary
        $this->output->writeln('');
        $this->output->writeln('<info>Test completed.</info>');
        $this->displayFinalStats();
        $this->output->writeln('');
    }

    /**
     * Displays the static header information once.
     */
    private function displayInitialHeader(): void
    {
        $this->output->writeln('<info>Starting CPU Load Test</info>');
        $this->output->writeln("<comment>Target: {$this->targetLoadPercent}% CPU for {$this->durationSeconds} seconds</comment>");
        $this->output->writeln(''); // Extra newline

        // Write placeholder lines that will be overwritten
        // Pad placeholders to roughly match expected line length or terminal width
        $placeholderWidth = $this->getTerminalWidth() ?? 80; // Use terminal width or default
        $this->output->writeln(str_pad("CPU Load: calculating...", $placeholderWidth));
        $this->output->writeln(str_pad("Progress: calculating...", $placeholderWidth));
        $this->output->writeln(str_pad("Time Remaining: calculating...", $placeholderWidth));
        $this->output->writeln(str_pad("Statistics: calculating...", $placeholderWidth));
    }


    /**
     * Reads /proc/stat and calculates the current CPU load percentage.
     * Returns null on error.
     */
    private function getCpuLoad(): ?float
    {
        // Use error suppression for file_get_contents
        $statContent = @file_get_contents('/proc/stat');
        if ($statContent === false) {
            return null; // Error reading file
        }

        $lines = explode("\n", $statContent);
        if (!isset($lines[0])) {
            return null; // File is empty or format is wrong
        }
        $cpuLine = $lines[0];
        // Replace multiple spaces with single space, trim, then explode
        $parts = explode(' ', trim(preg_replace('/\s+/', ' ', $cpuLine)));

        // cpu user nice system idle iowait irq softirq steal guest guest_nice
        // Need at least 'cpu' + 7 values (up to softirq)
        if (count($parts) < 8 || $parts[0] !== 'cpu') {
            return null; // Unexpected format
        }

        // Extract relevant values (ensure they are numeric)
        $user   = (float)($parts[1] ?? 0);
        $nice   = (float)($parts[2] ?? 0);
        $system = (float)($parts[3] ?? 0);
        $idle   = (float)($parts[4] ?? 0);
        $iowait = (float)($parts[5] ?? 0);
        $irq    = (float)($parts[6] ?? 0);
        $softirq= (float)($parts[7] ?? 0);

        $totalIdle = $idle + $iowait; // Consider iowait as idle for load calculation
        $totalNonIdle = $user + $nice + $system + $irq + $softirq;
        $total = $totalIdle + $totalNonIdle;

        $currentCpuInfo = ['total' => $total, 'idle' => $totalIdle];

        $cpuUsage = 0.0;
        if ($this->previousCpuInfo !== null) {
            $deltaTotal = $currentCpuInfo['total'] - $this->previousCpuInfo['total'];
            $deltaIdle = $currentCpuInfo['idle'] - $this->previousCpuInfo['idle'];

            // Prevent division by zero or negative delta if system time jumps backward slightly
            if ($deltaTotal > 0.0001) { // Use a small epsilon instead of just > 0
                $cpuUsage = 100.0 * (1.0 - ($deltaIdle / $deltaTotal));
            } else {
                $cpuUsage = 0.0;
            }
            // Clamp usage between 0 and 100
            $cpuUsage = max(0.0, min(100.0, $cpuUsage));
        }

        $this->previousCpuInfo = $currentCpuInfo;
        return $cpuUsage;
    }

    /**
     * Applies CPU load for a fixed interval (approx 100ms) based on target percentage.
     */
    private function applyLoadCycle(int $targetPercent): void
    {
        $cycleDuration = 0.1; // 100ms cycle
        $startTime = microtime(true);

        if ($targetPercent <= 0) {
            usleep((int)($cycleDuration * 1000000));
            return;
        }

        $workDuration = ($targetPercent / 100.0) * $cycleDuration;
        $workEnd = $startTime + $workDuration;
        while (microtime(true) < $workEnd) {
            // Perform some CPU-intensive work
            for ($i = 0; $i < 5000; $i++) {
                $x = sqrt(mt_rand(1, 1000000));
            }
            if (isset($x) && $x < -1) { /* Condition never met, but uses $x */ }
        }

        // --- Sleep Phase ---
        $elapsed = microtime(true) - $startTime;
        $sleepDuration = $cycleDuration - $elapsed;

        if ($sleepDuration > 0) {
            usleep((int)($sleepDuration * 1000000));
        }
    }

    /**
     * Updates the dynamic display lines in the terminal.
     */
    private function updateDisplay(float $cpuLoad, float $elapsed): void
    {
        // Move cursor up to overwrite the previous dynamic lines
        $this->cursor->moveUp($this->dynamicOutputLines);

        $maxDeviation = !empty($this->deviations) ? max($this->deviations) : 0.0;
        $avgDeviation = !empty($this->deviations) ? (array_sum($this->deviations) / count($this->deviations)) : 0.0;

        // Display each dynamic line, clearing the line first
        $this->displayLoadBar($cpuLoad);
        $this->displayProgressBar($elapsed);
        $this->displayTimeRemaining($elapsed);
        $this->displayCurrentStats($maxDeviation, $avgDeviation);
    }

    /**
     * Displays the CPU load bar using Symfony formatting (padding always enabled).
     */
    private function displayLoadBar(float $cpuLoad): void
    {
        $deviation = $cpuLoad - $this->targetLoadPercent;
        $cpuFilled = round(($cpuLoad / 100) * $this->barLength);
        $cpuFilled = max(0, min($this->barLength, $cpuFilled));
        $barContent = str_repeat('█', $cpuFilled) . str_repeat('░', $this->barLength - $cpuFilled);

        $absDeviation = abs($deviation);
        if ($absDeviation <= 5) {
            $tag = 'fg=green'; $indicator = "[=]";
        } elseif ($absDeviation <= 15) {
            $tag = 'fg=yellow'; $indicator = $deviation > 0 ? "[+]" : "[-]";
        } else {
            $tag = 'fg=red;options=bold'; $indicator = $deviation > 0 ? "[++]" : "[--]";
        }

        // Padded format string (always used)
        $formattedString = sprintf(
            "CPU Load: [<%s>%s</>] %5.1f%% (Target: %3d%%, Dev: <%s>%+6.1f%%</>) <%s>%-4s</>",
            $tag, $barContent, $cpuLoad, $this->targetLoadPercent, $tag, $deviation, $tag, $indicator
        );

        $this->cursor->clearLine();
        $this->output->writeln($formattedString);
    }

    /**
     * Displays the progress bar using Symfony formatting (padding always enabled).
     */
    private function displayProgressBar(float $elapsed): void
    {
        $percent = ($elapsed / $this->durationSeconds) * 100;
        $percent = max(0, min(100, $percent));
        $progressFilled = round(($percent / 100) * $this->barLength);
        $progressFilled = max(0, min($this->barLength, $progressFilled));
        $progressBar = str_repeat('█', $progressFilled) . str_repeat('░', $this->barLength - $progressFilled);

        // Padded format string (always used)
        $formattedString = sprintf(
            "Progress: [<fg=cyan>%s</>] %3d%%", $progressBar, round($percent)
        );

        $this->cursor->clearLine();
        $this->output->writeln($formattedString);
    }

    /**
     * Displays the time remaining (padding always enabled).
     */
    private function displayTimeRemaining(float $elapsed): void
    {
        $remaining = max(0, $this->durationSeconds - $elapsed);
        // Padded format string (always used)
        $formattedString = sprintf("Time Remaining: %5.1f seconds", $remaining);
        $this->cursor->clearLine();
        $this->output->writeln($formattedString);
    }

    /**
     * Displays the current statistics (padding always enabled).
     */
    private function displayCurrentStats(float $maxDeviation, float $avgDeviation): void
    {
        // Padded format string (always used)
        $formattedString = sprintf(
            "Statistics: Max Deviation: %5.1f%% | Avg Deviation: %5.1f%%",
            $maxDeviation, $avgDeviation
        );
        $this->cursor->clearLine();
        $this->output->writeln($formattedString);
    }

    /**
     * Displays final summary statistics using Symfony formatting (padding always enabled).
     */
    private function displayFinalStats(): void
    {
        if (empty($this->deviations)) {
            $this->output->writeln("<comment>No deviation data collected.</comment>");
            return;
        }
        $maxDeviation = max($this->deviations);
        $avgDeviation = array_sum($this->deviations) / count($this->deviations);
        $minDeviation = min($this->deviations);

        $this->output->writeln("<info>Final Statistics:</info>");
        // Padded format strings (always used)
        $this->output->writeln(sprintf(" - Target Load:    %3d%%", $this->targetLoadPercent));
        $this->output->writeln(sprintf(" - Duration:       %3d seconds", $this->durationSeconds));
        $this->output->writeln(sprintf(" - Avg Deviation:  %5.2f%%", $avgDeviation));
        $this->output->writeln(sprintf(" - Max Deviation:  %5.2f%%", $maxDeviation));
        $this->output->writeln(sprintf(" - Min Deviation:  %5.2f%%", $minDeviation));
    }

    /**
     * Helper to get terminal width (optional, for padding placeholders)
     */
    private function getTerminalWidth(): ?int
    {
        // Basic check, might need refinement for different OS/environments
        // Requires `stty` command on Linux/macOS
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            try {
                // Suppress potential errors from shell_exec
                $sizeOutput = @shell_exec('stty size');
                if ($sizeOutput && preg_match('/^\d+\s+(\d+)/', $sizeOutput, $matches)) {
                    $width = (int) $matches[1];
                    return ($width > 0) ? $width : null;
                }
            } catch (\Throwable $e) {
                // Ignore if command fails or shell_exec is disabled
            }
        }
        return null; // Default if width cannot be determined
    }
}


// --- Define the Command ---
class RunCpuTestCommand extends Command
{
    // the name of the command (the part after "php <script_name>")
    // If null, the script name itself is the command
    protected static $defaultName = 'run'; // Give the command a name

    protected function configure(): void
    {
        $this
            ->setDescription('Runs a CPU load test with a TUI.')
            ->setHelp('This command simulates CPU load and displays real-time statistics.')
            ->addOption(
                'target-load',
                't',
                InputOption::VALUE_REQUIRED,
                'Target CPU load percentage (0-100)',
                50 // Default value
            )
            ->addOption(
                'duration',
                'd',
                InputOption::VALUE_REQUIRED,
                'Duration of the test in seconds',
                60 // Default value
            )
            ->addOption(
                'bar-length',
                'l',
                InputOption::VALUE_REQUIRED,
                'Length of the progress/load bars in characters',
                100 // Default value
            )
            ->addOption(
                'update-interval',
                'i',
                InputOption::VALUE_REQUIRED,
                'Display update interval in seconds (e.g., 0.15)',
                0.15 // Default value
            );
        // Removed --no-padding option
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Retrieve options (Symfony Console handles type casting for VALUE_REQUIRED)
            $targetLoad = (int)$input->getOption('target-load');
            $duration = (int)$input->getOption('duration');
            $barLen = (int)$input->getOption('bar-length');
            $updateInt = (float)$input->getOption('update-interval');
            // Padding is now always enabled

            // Basic validation (though Console options add some)
            if ($targetLoad < 0 || $targetLoad > 100) {
                throw new \InvalidArgumentException('Target load must be between 0 and 100.');
            }
            if ($duration < 1) {
                throw new \InvalidArgumentException('Duration must be at least 1 second.');
            }
            if ($barLen < 10) {
                throw new \InvalidArgumentException('Bar length must be at least 10.');
            }
            if ($updateInt < 0.05) {
                throw new \InvalidArgumentException('Update interval must be at least 0.05 seconds.');
            }

            $tester = new CpuLoadTester(
                $output,
                $targetLoad,
                $duration,
                $barLen,
                $updateInt
            // No padding argument needed here anymore
            );
            $tester->run();

            return Command::SUCCESS;

        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>Configuration Error: ' . $e->getMessage() . '</error>');
            return Command::INVALID;
        } catch (\RuntimeException $e) {
            // Errors from CpuLoadTester (like stat read failure) might end up here
            // Error message should have been printed by CpuLoadTester before throwing
            return Command::FAILURE;
        } catch (\Throwable $e) {
            // Catch any other unexpected errors
            $output->writeln('<error>An unexpected error occurred: ' . $e->getMessage() . '</error>');
            // Optionally display stack trace in verbose mode
            if ($output->isVerbose()) {
                $output->writeln($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }
}


// --- Run the Application ---
$application = new Application('CPU Load Tester', '1.1.0'); // Version bump
$application->add(new RunCpuTestCommand());
$application->setDefaultCommand(RunCpuTestCommand::getDefaultName(), true); // Run the 'run' command by default
$application->run();