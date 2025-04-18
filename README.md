# tui

listANSICodes.php

    ANSI text style test list, no deps

tui_cpu_load_test_noLib.php

    CPU load TUI test script, no deps

tui_cpu_load_test.php

    CPU load TUI test script, requires Symfony Console
    composer install

    php tui_cpu_load_test.php -t 50 -d 35 -l 100
        target: 50% cpu load
        run for 35 seconds
        bar length of 100 character

    or just 
    php tui_cpu_load_test.php
    to use defaults