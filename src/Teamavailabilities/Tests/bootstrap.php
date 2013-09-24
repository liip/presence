<?php

// recognize composer's autoloader
$loader = require_once(__DIR__ . '/../../../vendor/autoload.php');

// make it aware of additional libraries and classes
$loader->add("Teamavailabilities\\Tests", __DIR__);
$loader->add("lapistano\\ProxyObject", __DIR__ . '/../../../vendor/lapistano/proxy-object/src/');

require __DIR__ . "/TeamAvailabilitiesTestCase.php";
