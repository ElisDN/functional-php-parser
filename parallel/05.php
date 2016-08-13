<?php

echo 'Start' . PHP_EOL;

$newPid = pcntl_fork();

if ($newPid == -1) {

    die('Can\'t fork process');

} elseif ($newPid) {

    echo 'I have created sub process ' . $newPid . PHP_EOL;

} else {

    echo 'I am forked process with ' . getmypid() . PHP_EOL;

}

echo 'Other main body' . PHP_EOL;
echo 'Stop' . PHP_EOL;