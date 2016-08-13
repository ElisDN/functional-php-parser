<?php

echo 'Start' . PHP_EOL;

$childPids = [];

$result = [];

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

                $sharedId = shmop_open($childPid, 'a', 0, 0);
                $shareData = shmop_read($sharedId, 0, shmop_size($sharedId));
                $result[] = unserialize($shareData);
                shmop_delete($sharedId);
                shmop_close($sharedId);

            }
            echo 'OK. All subprocesses are ready' . PHP_EOL;
        }

    } else {

        $myPid = getmypid();
        echo 'I am forked process with pid ' . $myPid. PHP_EOL;
        $timeout = rand(1000000, 2000000);
        usleep($timeout);
        echo 'I am already done ' . $myPid . PHP_EOL;

        $shareData = serialize($timeout);
        $sharedId = shmop_open($myPid, 'c', 0644, strlen($shareData));
        shmop_write($sharedId, $shareData, 0);

        die(0);

    }
}

print_r($result);

echo 'Main body' . PHP_EOL;
echo 'Main stop' . PHP_EOL;