<?php

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * This is the model class for the howtos.
 *
 */
class Howto extends Model
{

    public $id;
    public $title;
    public $content;

    /**
     * Returns all Howto models
     *
     * @return Howto[]
     */
    public function findAll()
    {
        $dir = Yii::getAlias('@app') . '/howtos/';
        $models = [];

        if (file_exists($dir)) {
            $files = scandir($dir, SCANDIR_SORT_ASCENDING);
            foreach ($files as $file) {
                if (preg_match('/^(.*)\.md$/', $file, $matches)) {
                    if (isset($matches[1])) {
                        $models[] = Howto::findOne($matches[1] . '.md');
                    }
                }
            }
        }

        return $models;
    }

    /**
     * Return the Howto model related to the id
     *
     * @param string $id - id
     * @return Howto
     */
    public function findOne($id)
    {
        $file = Yii::getAlias('@app') . '/howtos/' . str_replace('/', '', $id);

        if(file_exists($file) === false){
            return null;
        }

        $me = new Howto;
        $me->id = $id;
        $me->title = trim(fgets(fopen($file, 'r')), ' \t\n\r\0\x0B#');
        $me->content = file_get_contents($file);
        $me->content = substr($me->content, strpos($me->content, "\n")+1);
        return $me;
    }

}
