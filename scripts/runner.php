<?php

require_once __DIR__ . "/../vendor/autoload.php";

define("PATH_ROOT", realpath(__DIR__. "/../") . "/");

$configPath = PATH_ROOT . "config/server.json";
$conf = new \Icekson\WsAppServer\Config\ConfigAdapter($configPath);

$appRunner = new \Icekson\WsAppServer\Console\Command\AppRun();
$appRunner->setConfiguration($conf);
$serviceRunner = new \Icekson\WsAppServer\Console\Command\ServiceRun();
$serviceRunner->setConfiguration($conf);
$console = new Symfony\Component\Console\Application('app-server');

$console->add($appRunner);
$console->add($serviceRunner);

$console->run();