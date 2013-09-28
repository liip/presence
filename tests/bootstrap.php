<?php

// recognize composer's autoloader
$loader = require_once(__DIR__ . '/../vendor/autoload.php');

$loader->addClassMap(
    array(
        'Presence\\PresenceTestCase' => __DIR__ . '/PresenceTestCase.php',
    )
);
