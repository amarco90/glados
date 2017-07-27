<?php

namespace app\commands;

use yii;
use app\commands\DaemonController;
use app\models\Exam;
use yii\helpers\Console;
use app\components\ShellCommand;

/**
 * Analyzer Daemon
 * Desc: TODO
 */
class AnalyzeController extends DaemonController
{

    /**
     * @var array A list of paths to extract
     */    
    public $extractList = [
        '/etc/lernstick-firewall/url_whitelist',
    ];

    /**
     * @var Exam The exam in processing at the moment 
     */    
    public $exam = null;


    /**
     * @inheritdoc
     */
    public function start()
    {
        parent::start();
    }

    /**
     * @inheritdoc
     */
    public function doJobOnce ($id = '')
    {
        if (($this->exam = $this->getNextExam()) !== null) {
            $this->processExam($this->exam);
        }
    }

    /**
     * @inheritdoc
     */
    public function doJob ($id = '')
    {
        while (true) {
            $this->exam = null;

            if ($id != ''){
                $this->exam =  Exam::findOne(['id' => $id]);
            } else {
                $this->exam = $this->getNextExam();
            }

            if($this->exam  !== null) {
                $this->processExam($this->exam);
            }

            if ($id != '') {
                return;
            }

            pcntl_signal_dispatch();
            sleep(rand(5, 10));
        }
    }

    public function processExam ($exam)
    {
        $this->exam = $exam;
        $tmpdir = $this->unpack();
        if (file_exists($tmpdir . "/" . $this->extractList[0])) {
            $this->exam->{"sq_url_whitelist"} = file_get_contents($tmpdir . "/" . $this->extractList[0]);
        } else {
            $this->exam->{"sq_url_whitelist"} = null;
        }

        $this->exam->{"file_analyzed"} = 1;
        $this->exam->save(false);

        if (!empty($this->exam->md5)) {
            $this->removeDirectory("/tmp/" . $this->exam->md5);
        }
    }

    /**
     * Extracts files from the associated squash filesystem
     *
     * @return string the path to the unpacked files
     */
    private function unpack ()
    {
        chdir(dirname(__FILE__));
        if(file_exists("/tmp/" . $this->exam->md5) && !empty($this->exam->md5)) {
            $this->removeDirectory("/tmp/" . $this->exam->md5);
        }

        $cmd = "unsquashfs -d /tmp/" . escapeshellarg($this->exam->md5) . " " . escapeshellarg($this->exam->file)
             . " " . implode(" ", $this->extractList);
        $cmd = new ShellCommand($cmd);

        $cmd->on(ShellCommand::COMMAND_OUTPUT, function($event) use (&$output) {
            echo $this->ansiFormat($event->line, $event->channel == ShellCommand::STDOUT ? Console::NORMAL : Console::FG_RED);
        });

        $retval = $cmd->run();
        return "/tmp/" . $this->exam->md5;
    }

    /**
     * Removes a directory recusively
     *
     * @param string path to directory
     * @return bool success or failure
     */
    private function removeDirectory ($path) {
        $files = glob($path . '/*');
        foreach ($files as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }
        return file_exists($path) ? rmdir($path) : false;
    }


    /**
     * Determines the next exam file to process
     *
     * @return Exam|null
     */
    private function getNextExam ()
    {

        $this->pingOthers();

        $query = Exam::find()
            ->where(['not', ['file' => null]])
            ->andWhere(['file_analyzed' => 0]);

        if (($exam = $query->one()) !== null) {
            return $exam;
        }

        return null;
    }


    /**
     * @inheritdoc
     */
    public function stop()
    {
        if ($this->exam !== null && !empty($this->exam->md5)) {
            $this->removeDirectory("/tmp/" . $this->exam->md5);
        }
        parent::stop();
    }

}
