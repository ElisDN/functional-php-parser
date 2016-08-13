<?php

echo 'Start' . PHP_EOL;

$newPid = pcntl_fork();

if ($newPid == -1) {
    die('Can\'t fork process');
} elseif ($newPid) {

    echo 'I have created sub process ' . $newPid . PHP_EOL;

} else {

    echo 'I am forked process with pid ' . getmypid() . PHP_EOL;
    die(0);

}

echo 'Main body' . PHP_EOL;
echo 'Main stop' . PHP_EOL;

// myPid, die