<?php

define('ROOT_PATH', realpath(dirname(__FILE__) . "/../") . "/");

if (!defined("APP")) {
    define("APP", ROOT_PATH);
}

date_default_timezone_set("UTC");

$path = realpath(dirname(__FILE__) . "/../module/Application/src/") . "/";

require_once __DIR__ . "/../vendor/autoload.php";


use Symfony\Component\Console\Application;

$conf = ROOT_PATH . "config/server.json";
$config = (new \Icekson\Config\ConfigAdapter($conf))->toArray();
$config = $config['ws-server'];
$host = $config['amqp']['host'];
$port = $config['amqp']['port'];
$user = $config['amqp']['user'];
$password = $config['amqp']['password'];
$vhost = $config['amqp']['vhost'];
$channel = null;
$topic = "#";

if(isset($argv[1])) {
    $topic = $argv[1];
}

try {
    $conn = new \PhpAmqpLib\Connection\AMQPStreamConnection($host, $port, $user, $password, $vhost);
    $channel = $conn->channel();
    $channel->exchange_declare('monitor-log', 'topic', false, true, false);
    list($queue_name, ,) = $channel->queue_declare('', false, true, false);
    $channel->queue_bind($queue_name, 'monitor-log', $topic);

    if ($channel !== null) {
        $channel->basic_consume($queue_name, '', false, true, false, false, function (\PhpAmqpLib\Message\AMQPMessage $msg) {
            $output = new Symfony\Component\Console\Output\StreamOutput(fopen('php://stdout', "w"));
            $jsonDecoded = json_decode($msg->body);
            $tag = $tag2 = "";
            if($jsonDecoded->level_name == 'INFO'){
                $tag = "<info>";
                $tag2 = "</info>";
            }else if($jsonDecoded->level_name == 'ERROR'){
                $tag = "<error>";
                $tag2 = "</error>";
            }else if($jsonDecoded->level_name == 'WARNING'){
                $tag = "<comment>";
                $tag2 = "<comment>";

            }
            $date = explode(".", $jsonDecoded->datetime->date)[0];
            $output->writeln("{$tag}[{$date}] {$jsonDecoded->level_name} {$jsonDecoded->channel} {$jsonDecoded->message} context: " . json_encode($jsonDecoded->context)."{$tag2}");
        });

        while (count($channel->callbacks)) {
            $channel->wait();
        }
    } else {
        echo "Watching log error";
    }
} catch (\Exception $e) {
    echo 'amqp logger error: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
}
register_shutdown_function(function () use ($conn, $channel) {
    $conn->close();
    $channel->close();
});