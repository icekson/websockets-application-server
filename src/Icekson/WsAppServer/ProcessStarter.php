<?php

namespace Icekson\WsAppServer;

use SplObserver;
use Icekson\WsAppServer\Exception\ThreadsException;
use Symfony\Component\Process\Process;
use Icekson\Utils\Logger;

class ProcessStarter implements \SplSubject
{

    private static $instance = null;

    private $runnerScriptName = "runner.php";

    private $logger = null;

    private $threads = [];

    private $isStoped = false;

    /**
     * @var null|\SplObjectStorage
     */
    private $observers = null;

    private function __construct()
    {
        $this->logger = Logger::createLogger(get_class($this));
        $this->observers = new \SplObjectStorage();

    }

    /**
     * @return null|self
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
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
        $process = new Process($cmd);
        $process->start();
        sleep(1);
        if ($process->isRunning()) {
            $this->getLogger()->debug("Server started: pid - " . $process->getPid());
            $pid = $process->getPid();
        } else {
            $this->getLogger()->warning($process->getErrorOutput());
        }

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
                while (count($parts) > 0) {
                    $part = array_shift($parts);
                    if (!in_array($part, $ar)) {
                        $theSame = false;
                        break;
                    }
                }
                if ($theSame) {
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
        foreach ($this->threads as $pid =>$v) {
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


