<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 11/29/15
 * Time: 4:50 AM
 */

class BaseEntityException extends Exception {}
class EntityManagerUndefinedException extends BaseEntityException {}
class EntityManagerClassMissingException extends BaseEntityException {}
class EntityFieldAccessException extends BaseEntityException {}
class EntityContextMismatchException extends BaseEntityException {}
class EntityFormFieldsException extends BaseEntityException {}

/**
 * Core Entities
 */

/** Class JSBaseDataEntity - used for JS Frontend */
class JSDataEntity extends BaseDataEntity implements JsonSerializable
{
    public function JsonSerialize()
    {
        return $this->dataArray;
    }

    public function getJsonObject()
    {
        return json_encode($this->dataArray);
    }
}

class UploadEntity extends DBManagerEntity {

    use
        hasFilenameField,
        hasMd5Field,
        hasCreateTimeField;
}

class SettingEntity extends DBManagerEntity {

    use
        hasValueField;
}

class LogEntity extends DBManagerEntity {

}

class AdminLogEntity extends DBManagerEntity {

}

class VarEntity extends DBManagerEntity {

    use
        hasNameField,
        hasValueField;

}

class UploadErrorEntity extends DBManagerEntity {

}