<?php

echo 'Start' . PHP_EOL;

pcntl_fork();

echo 'Body' . PHP_EOL;

echo 'Stop' . PHP_EOL;