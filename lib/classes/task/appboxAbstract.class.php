<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2012 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * @license     http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link        www.phraseanet.com
 */
abstract class task_appboxAbstract extends task_abstract
{

    abstract protected function retrieveContent(appbox $appbox);

    abstract protected function processOneContent(appbox $appbox, Array $row);

    abstract protected function postProcessOneContent(appbox $appbox, Array $row);

    protected function run2()
    {
        $this->running = TRUE;
        while ($this->running) {
            try {
                $conn = connection::getPDOConnection();
            } catch (Exception $e) {
                $this->log($e->getMessage());
                if ($this->getRunner() == self::RUNNER_SCHEDULER) {
                    $this->log(("Warning : abox connection lost, restarting in 10 min."));

                    for ($t = 60 * 10; $this->running && $t; $t -- ) // DON'T do sleep(600) because it prevents ticks !
                        sleep(1);
                    // because connection is lost we cannot change status to 'torestart'
                    // anyway the current status 'running' with no pid
                    // will enforce the scheduler to restart the task
                } else {
                    // runner = manual : can't restart so simply quit
                }
                $this->running = FALSE;

                return;
            }

            $this->setLastExecTime();

            try {
                $sql = 'SELECT task2.* FROM task2 WHERE task_id = :taskid LIMIT 1';
                $stmt = $conn->prepare($sql);
                $stmt->execute(array(':taskid' => $this->getID()));
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                $this->records_done = 0;
                $duration = time();
            } catch (Exception $e) {
                // failed sql, simply return
                $this->running = FALSE;

                return;
            }

            if ($row) {
                if ( ! $this->running)
                    break;

                $appbox = appbox::get_instance(\bootstrap::getCore());
                try {
                    $this->loadSettings(simplexml_load_string($row['settings']));
                } catch (Exception $e) {
                    $this->log($e->getMessage());
                    continue;
                }

                $process_ret = $this->process($appbox);

                switch ($process_ret) {
                    case self::STATE_MAXMEGSREACHED:
                    case self::STATE_MAXRECSDONE:
                        if ($this->getRunner() == self::RUNNER_SCHEDULER) {
                            $this->setState(self::STATUS_TORESTART);
                            $this->running = FALSE;
                        }
                        break;

                    case self::STATUS_TOSTOP:
                        $this->setState(self::STATUS_TOSTOP);
                        $this->running = FALSE;
                        break;

                    case self::STATUS_TODELETE: // formal 'suicidable'
                        $this->setState(self::STATUS_TODELETE);
                        $this->running = FALSE;
                        break;

                    case self::STATE_OK:
                        break;
                }
            } // if(row)

            $this->incrementLoops();
            $this->pause($duration);
        } // while running

        return;
    }

    /**
     *
     * @return <type>
     */
    protected function process(appbox $appbox)
    {
        $ret = self::STATE_OK;

        $conn = $appbox->get_connection();
        $tsub = array();
        try {
            // get the records to process
            $rs = $this->retrieveContent($appbox);
        } catch (Exception $e) {
            $this->log('Error  : ' . $e->getMessage());
            $rs = array();
        }

        $rowstodo = count($rs);
        $rowsdone = 0;

        if ($rowstodo > 0)
            $this->setProgress(0, $rowstodo);

        foreach ($rs as $row) {
            try {
                // process one record
                $this->processOneContent($appbox, $row);
            } catch (Exception $e) {
                $this->log("Exception : " . $e->getMessage() . " " . basename($e->getFile()) . " " . $e->getLine());
            }

            $this->records_done ++;
            $this->setProgress($rowsdone, $rowstodo);

            // post-process
            $this->post_processOneContent($appbox, $row);

            // $this->check_memory_usage();
            $current_memory = memory_get_usage();
            if ($current_memory >> 20 >= $this->maxmegs) {
                $this->log(sprintf("Max memory (%s M) reached (actual is %s M)", $this->maxmegs, $current_memory));
                $this->running = FALSE;
                $ret = self::STATE_MAXMEGSREACHED;
            }

            if ($this->records_done >= (int) ($this->maxrecs)) {
                $this->log(sprintf("Max records done (%s) reached (actual is %s)", $this->maxrecs, $this->records_done));
                $this->running = FALSE;
                $ret = self::STATE_MAXRECSDONE;
            }

            // $this->check_task_status();
            try {
                $status = $this->getState();
                if ($status == self::STATUS_TOSTOP) {
                    $this->running = FALSE;
                    $ret = self::STATUS_TOSTOP;
                }
            } catch (Exception $e) {
                $this->running = FALSE;
            }

            if ( ! $this->running)
                break;
        } // foreach($rs as $row)
        //
        // if nothing was done, at least check the status
        if (count($rs) == 0 && $this->running) {
            // $this->check_memory_usage();
            $current_memory = memory_get_usage();
            if ($current_memory >> 20 >= $this->maxmegs) {
                $this->log(sprintf("Max memory (%s M) reached (current is %s M)", $this->maxmegs, $current_memory));
                $this->running = FALSE;
                $ret = self::STATE_MAXMEGSREACHED;
            }

            if ($this->records_done >= (int) ($this->maxrecs)) {
                $this->log(sprintf("Max records done (%s) reached (actual is %s)", $this->maxrecs, $this->records_done));
                $this->running = FALSE;
                $ret = self::STATE_MAXRECSDONE;
            }

            // $this->check_task_status();
            try {
                $status = $this->getState();
                if ($status == self::STATUS_TOSTOP) {
                    $this->running = FALSE;
                    $ret = self::STATUS_TOSTOP;
                }
            } catch (Exception $e) {
                $this->running = FALSE;
            }
        }

        if ($rowstodo > 0)
            $this->setProgress(0, 0);

        return $ret;
    }
}
