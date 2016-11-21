<?php
/**
 * @author a.itsekson
 * @createdAt: 21.11.2016 14:49
 */

namespace Icekson\WsAppServer\Console\Command;


use Icekson\WsAppServer\Config\ConfigAdapter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Utils\ConsoleTextColorizator;

class LogMonitorCommand
{
    protected function configure()
    {
        $this->setName('tool:logs-monitor')
            ->setDescription('Monitor for application logs')
            ->addOption('mask', null, InputOption::VALUE_OPTIONAL, 'mask', "#")
            ->addOption('config-path', null, InputOption::VALUE_OPTIONAL, 'config', "./config/server.json");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mask = $input->getOption("mask");
        $configPath = $input->getOption("config-path");
        try {
            $config = new ConfigAdapter(PATH_ROOT . $configPath);
            $host = $config->get('amqp')['host'];
            $port = $config->get('amqp')['port'];
            $user = $config->get('amqp')['user'];
            $password = $config->get('amqp')['password'];
            $vhost = $config->get('amqp')['vhost'];

            $conn = new \PhpAmqpLib\Connection\AMQPStreamConnection($host, $port, $user, $password, $vhost);
            $channel = $conn->channel();
            $channel->exchange_declare('monitor-log', 'topic', false, true, false);
            list($queue_name, ,) = $channel->queue_declare('', false, true, false);
            $channel->queue_bind($queue_name, 'monitor-log', $mask);

            if ($channel !== null) {
                $channel->basic_consume($queue_name, '', false, true, false, false, function (\PhpAmqpLib\Message\AMQPMessage $msg)  {
                    $colorizator = new ConsoleTextColorizator();
                    $output = new StreamOutput(fopen('php://stdout', "w"));
                    $jsonDecoded = json_decode($msg->body);
                    $color = ConsoleTextColorizator::COLOR_LIGHT_GRAY;

                    switch ($jsonDecoded->level_name){
                        case 'INFO':
                            $color = ConsoleTextColorizator::COLOR_LIGHT_GREEN;
                            break;
                        case 'ERROR':
                        case 'CRITICAL' :
                        case 'EMERGENCY':
                            $color = ConsoleTextColorizator::COLOR_RED;
                            break;
                        case 'WARNING':
                            $color = ConsoleTextColorizator::COLOR_ORANGE;
                            break;
                        case 'ALERT':
                            $color = ConsoleTextColorizator::COLOR_LIGHT_RED;
                            break;
                        case 'NOTICE':
                            $color = ConsoleTextColorizator::COLOR_WHITE;
                            break;
                    }
                    $date = explode(".", $jsonDecoded->datetime->date)[0];
                    $text = "[{$date}] {$jsonDecoded->level_name} {$jsonDecoded->channel} {$jsonDecoded->message} context: " . json_encode($jsonDecoded->context);
                    $output->writeln($colorizator->wrap($text, $color));
                });

                while (count($channel->callbacks)) {
                    $channel->wait();
                }
            } else {
                echo "Watching log error";
            }
        } catch (\Exception $e) {
            $output->writeln('amqp logger error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
        register_shutdown_function(function () use ($conn, $channel) {
            $channel->close();
            $conn->close();
        });

    }
}