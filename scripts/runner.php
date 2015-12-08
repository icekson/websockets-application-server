<?php

$autoload = __DIR__ . "/../vendor/autoload.php";
if(!file_exists($autoload)){
    define("PATH_ROOT", realpath(__DIR__. "/../../../../") . "/");
    $autoload = __DIR__ . "/../../../../vendor/autoload.php";
}else{
    define("PATH_ROOT", realpath(__DIR__. "/../") . "/");
}

require_once $autoload;

$configPath = "./config/autoload/";
if(!defined("CONFIG_PATH")) {
    define("CONFIG_PATH", $configPath);
}
$conf = new \Icekson\WsAppServer\Config\ConfigAdapter(PATH_ROOT . $configPath);

$appRunner = new \Icekson\WsAppServer\Console\Command\AppRun();
$appRunner->setConfiguration($conf);

$serviceRunner = new \Icekson\WsAppServer\Console\Command\ServiceRun();
$serviceRunner->setConfiguration($conf);
$console = new Symfony\Component\Console\Application('app-server');

$console->add($appRunner);
$console->add($serviceRunner);

$console->run();