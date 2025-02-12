<?php

namespace app\components;
 
use Yii;
use yii\base\Component;
use yii\web\NotFoundHttpException;
use yii\helpers\FileHelper;
 
class Squashfs extends File
{

    /**
     * @inheritdoc
     *
     * @throws NotFoundHttpException if the unsquashfs binary could not be found or if the
     *         file is not executable.     
     */
    public function __construct()
    {
        parent::__construct();
        
        if (!file_exists('/usr/bin/unsquashfs') || !is_executable('/usr/bin/unsquashfs')) {
            throw new NotFoundHttpException('/usr/bin/unsquashfs: No such file or directory or the binary is not executable. Please install squashsftools.');
        }
    }

    /**
     * @inheritdoc
     */
    public static function getInstance($path, $class_name = __CLASS__)
    {
        return parent::getInstance($path, $class_name);
    }

    /**
     * Generates an array with file information about every file in the squash filesystem
     *
     * @return array the file list. The array has the following structure, for example:
     *      [
     *          [
     *              'mode' => '-rwxr-wr-x',
     *              'owner' => 'root',
     *              'group' => 'root',
     *              'size' => 27,
     *              'date' => '2015-03-13',
     *              'time' => '16:56',
     *              'path' => 'squashfs-root/home/user/file'
     *          ],
     *      ]
     */
    public function getFileList()
    {
        exec('/usr/bin/unsquashfs -ll ' . escapeshellarg($this->path), $output, $retval);
        for ($i=3;$i<=count($output);$i++){
            if(!array_key_exists($i, $output)){
                break;
            }

            list(
                $a['mode'],
                $a['owner'],
                $a['group'],
                $a['size'],
                $a['date'],
                $a['time'],
                $a['path'],
            ) = preg_split('/[\s,\/]+/', $output[$i], 7, PREG_SPLIT_NO_EMPTY);
            $a['path'] = FileHelper::normalizePath(substr($a['path'], strlen('squashfs-root')));

            $file_list[] = $a;
        }
        return $this->exists ? $file_list : null;
    }

    public function file_exists($path)
    {
        foreach ($this->fileList as $file) {
            if ($file['path'] == FileHelper::normalizePath('squashfs-root/' . $path)) {
                return true;
            }
        }
        return false;
    }

}

