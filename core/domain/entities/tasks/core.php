<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 4/9/17
 * Time: 3:16 PM
 */

class TaskEntity extends DBManagerEntity {

    use
        hasPriorityField,
        hasFileField,
        hasFuncField,
        hasArgsField,
        hasCreateTimeField;

    /**
     * @return int
     */
    public function getFailed()
    {
        return $this->field(DBField::FAILED);
    }

    /**
     * @return int
     */
    public function getRunning()
    {
        return $this->field(DBField::RUNNING);
    }

    /**
     * @return string
     */
    public function getPostDate()
    {
        return $this->field(DBField::POST_DATE);
    }

}

class TaskHistoryEntity extends TaskEntity {

    use
        hasUpdateDateField;
}