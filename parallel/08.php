<?php

echo 'Start' . PHP_EOL;

$childPids = [];

for ($i = 1; $i < 5; $i++) {

    $newPid = pcntl_fork();

    if ($newPid == -1) {
        die('Can\'t fork process');
    } elseif ($newPid) {

        $childPids[] = $newPid;
        echo 'Main process have created subprocess ' . $newPid . PHP_EOL;

        if ($i == 4) {
            echo 'Main process is waiting for all subprocesses' . PHP_EOL;
            foreach ($childPids as $childPid) {
                pcntl_waitpid($childPid, $status);
                echo 'OK. Subprocess ' . $childPid . ' is ready' . PHP_EOL;
            }
            echo 'OK. All subprocesses are ready' . PHP_EOL;
        }

    } else {

        $myPid = getmypid();
        echo 'I am forked process with pid ' . $myPid . PHP_EOL;
        usleep(rand(1000000, 2000000));
        echo 'I am already done ' . $myPid . PHP_EOL;
        die(0);

    }
}

echo 'Main body' . PHP_EOL;
echo 'Main stop' . PHP_EOL;