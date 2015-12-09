<?php
/**
 * @author a.itsekson
 * @createdAt: 05.11.2015 14:32
 */

namespace Icekson\Utils;


use Monolog\Handler\AmqpHandler;
use Monolog\Handler\StreamHandler;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;

class Logger
{

    private static $loggers = [];

    /**
     * @param $name
     * @param $config
     * @param int $level
     * @return LoggerInterface
     * @throws \Exception
     */
    public static function createLogger($name, $config = [], $level = \Monolog\Logger::DEBUG)
    {
        if (!isset(self::$loggers[$name])) {
            $logger = new \Monolog\Logger($name);
            $logger->pushHandler(new StreamHandler('php://stdout'));
            $file = PATH_ROOT . '/logs/' . preg_replace('/[\/\\\._-]+/', ".", $name) . ".log";
            $logger->pushHandler(new StreamHandler($file, $level, true, 0766));

            if (!empty($config) && (isset($config['amqp']) || isset($config['vhost']))) {
                $host = isset($config['amqp']) ? $config['amqp']['host'] : $config['host'];
                $port = isset($config['amqp']) ? $config['amqp']['port'] : $config['port'];
                $user = isset($config['amqp']) ? $config['amqp']['user'] : $config['user'];
                $password = isset($config['amqp']) ? $config['amqp']['password'] : $config['password'];
                $vhost = isset($config['amqp']) ? $config['amqp']['vhost'] : $config['vhost'];
                $channel = null;
                try {
                    $conn = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
                    $channel = $conn->channel();
                    $channel->exchange_declare('monitor-log', 'topic', false, true, false);

                    register_shutdown_function(function () use ($conn, $channel) {
                        $conn->close();
                        $channel->close();
                    });

                } catch (\Exception $e) {
                    // throw $e;
                    // echo 'amqp logger error: ' . $e->getMessage() . "\n" . $e->getTraceAsString();
                }
                if ($channel !== null) {
                    $logger->pushHandler(new AmqpHandler($channel, 'monitor-log'));
                }
            }
            self::$loggers[$name] = $logger;
        }

        return self::$loggers[$name];
    }

}