<?php

namespace Icekson\WsAppServer;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use SplObserver;
use Symfony\Component\Process\Process;
use Icekson\Utils\Logger;

class ProcessStarter implements \SplSubject
{
    private $restartLimit = 10;

    private $limitCounters = [];

    private static $instance = null;

    private $runnerScriptName = "runner.php";

    private $logger = null;

    private $threads = [];

    private $isStoped = false;

    /**
     * @var null|\SplObjectStorage
     */
    private $observers = null;

    /**
     * @var null|LoopInterface
     */
    private $loop = null;

    private function __construct(LoopInterface $loop = null)
    {
        $this->logger = Logger::createLogger(get_class($this));
        $this->observers = new \SplObjectStorage();
        if ($loop !== null) {
            $this->loop = $loop;
        } else {
            $this->loop = Factory::create();
        }

    }

    /**
     * @param LoopInterface|null $loop
     * @return ProcessStarter|null
     */
    public static function getInstance(LoopInterface $loop = null)
    {
        if (self::$instance === null) {
            self::$instance = new self($loop);
        }
        return self::$instance;
    }

    /**
     * @param $cmd
     * @return int|null
     */
    public function runNewProcess($cmd)
    {
        $search = 'ps uwx | grep "' . preg_replace("/\s+/", "\\s", $cmd) . '"';
        $search = preg_replace("/'+/", "", $search);
        $this->getLogger()->info("cmd: " . $cmd);
        $this->getLogger()->info("search cmd: " . $search);
        $process = new Process($search);
        $process->run();
        $out = $process->getOutput();
        $this->getLogger()->debug($out);
        $lines = preg_split('/\n/', $out);
        $pid = -1;
        if (count($lines) > 1) {
            $this->getLogger()->debug("Process is found, try to stop...");
            $this->stop($cmd, $lines);
        }

        // Start Chaild process

        $process = new \React\ChildProcess\Process($cmd);
        $process->on('exit', function($exitCode, $termSignal) use ($process, $cmd) {
            $loop = $this->loop;
            $logger = $this->getLogger();
            $limit = isset($this->limitCounters[$cmd]) ? $this->limitCounters[$cmd] : 0;
            if($termSignal != SIGKILL && $limit <= $this->restartLimit) {
                $logger->warning("Child process is terminated cmd: {$cmd}, exitCode: '{$exitCode}', signal: '{$termSignal}'; restart...");
                // if child process is terminated, restart it
                $loop->addTimer(0.001, function (Timer $timer) use ($process, $logger) {
                    $process->start($timer->getLoop());
                    $process->stdout->on('data', function ($output) use ($logger) {
                        echo $output;
                    });
                });
                if(!isset($this->limitCounters[$cmd])){
                    $this->limitCounters[$cmd] = 0;
                }
                $this->limitCounters[$cmd]++;
            }else{
                $this->logger->warning("Child process is terminated with signal " . SIGKILL);
            }
            $this->notify();
        });

        // Set output handler for child pubsub process
        $this->loop->addTimer(0.001, function(Timer $timer) use ($process) {
            $logger = $this->getLogger();
            $process->start($timer->getLoop());
            $process->stdout->on('data', function($output) use ($logger) {
                echo $output;
            });
        });

        $pid = $process->getPid();
        $this->threads[$pid] = true;
        return $pid;

    }

    private function stop($cmd, $lines)
    {
        $stopped = false;
        $parts = explode(" ", $cmd);
        $pid = -1;
        if (count($lines) > 1) {
            foreach ($lines as $line) {
                $ar = preg_split('/\s+/', trim($line));
                $theSame = true;
                $arr = array_slice($ar, array_search('scripts/runner.php', $ar) - 1);
                while (count($parts) > 0) {
                    $part = str_replace("'", "", array_shift($parts));
                    if (!in_array($part, $arr)) {
                        $theSame = false;
                        break;
                    }
                }
                if ($theSame && count($ar) > 1) {
                    $pid = (int)$ar[1];
                    posix_kill($pid, SIGKILL);
                    $stopped = true;
                }
            }
            if ($stopped) {
                $this->getLogger()->debug("Process $pid is stopped.");
            } else {
                $this->getLogger()->alert("Process not found. Are you sure it's running?");
            }
        } else {
            $this->getLogger()->alert("Process not found. Are you sure it's running?");
        }
    }

    public function check()
    {
        if ($this->isStoped) {
            $this->stopAllProcesses();
            $this->isStoped = false;
        }
    }

    /**
     * @param $cmd
     * @return bool
     */
    public function checkProcessByCmd($cmd)
    {
        $search = 'ps uwx | grep "' . preg_replace("/\s+/", "\\s", $cmd) . '"';
        $search = preg_replace("/'+/", "", $search);
        $this->getLogger()->info("check cmd: " . $cmd);
        $this->getLogger()->info("search cmd: " . $search);
        $process = new Process($search);
        $process->run();
        $out = $process->getOutput();
        $lines = preg_split('/\n/', $out);
        $pid = -1;
        if (count($lines) > 1) {
            $this->getLogger()->info("Process is run");
            return true;
        }else{
            $this->getLogger()->info("Process is not found run");
            return false;
        }
    }


    /**
     * @param int $pid
     */
    public function stopProcess($pid)
    {
        if (isset($this->threads[$pid])) {
            posix_kill($pid, SIGKILL);
            unset($this->threads[$pid]);
            $this->notify();
        }
    }

    public function stopAllProcesses()
    {
        $this->isStoped = true;
        foreach ($this->threads as $pid => $v) {
            $this->stopProcess($pid);
        }
    }

    /**
     * @return array
     */
    public function getActiveProcesses()
    {
        return array_keys($this->threads);
    }

    /**
     * Attach an SplObserver
     * @link http://php.net/manual/en/splsubject.attach.php
     * @param SplObserver $observer <p>
     * The <b>SplObserver</b> to attach.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function attach(SplObserver $observer)
    {
        $this->observers->attach($observer);
    }

    /**
     * Detach an observer
     * @link http://php.net/manual/en/splsubject.detach.php
     * @param SplObserver $observer <p>
     * The <b>SplObserver</b> to detach.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function detach(SplObserver $observer)
    {
        $this->observers->detach($observer);
    }

    /**
     * Notify an observer
     * @link http://php.net/manual/en/splsubject.notify.php
     * @return void
     * @since 5.1.0
     */
    public function notify()
    {
        /** @var \SplObserver $observer */
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }

    /**
     * @return null|\Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }


}


