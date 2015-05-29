#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Loop;
use Icicle\Stream\Stream;

$stream = new Stream();

$stream
    ->write("This is just a test.\nThis will not be read.")
    ->then(function () use ($stream) {
        return $stream->read(null, "\n");
    })
    ->then(function ($data) {
        echo $data; // Echos "This is just a test."
    });

Loop\run();
