<?php

echo 'Start' . PHP_EOL;

$newPid = pcntl_fork();

echo 'Pid: ' . $newPid . PHP_EOL;

echo 'Body' . PHP_EOL;
echo 'Stop' . PHP_EOL;