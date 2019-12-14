<?php

if (file_exists($file = __DIR__ . '/../vendor/autoload.php')) {
    require $file;
} else {
    throw new RuntimeException('Install dependencies to run test suite.');
}
