<?php

require_once __DIR__ . "/../vendor/autoload.php";

define("PATH_ROOT", realpath(__DIR__) . "/");

$appRunner = new \Icekson\Console\Command\AppRun();
$connectorRunner = new \Icekson\Console\Command\ConnectorRun();

$console = new Symfony\Component\Console\Application('app-server');
$console->add($appRunner);
$console->add($connectorRunner);

$console->run();