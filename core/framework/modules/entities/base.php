<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 11/29/15
 * Time: 3:38 AM
 */

require_once("traits.php");

/**
 * Class BaseDataEntity
 */
abstract class BaseDataEntity Implements ArrayAccess, IteratorAggregate
{
    /**
     * @var array
     */
    protected $originalData = [];

    /**
     * @var array
     */
    protected $dataArray = [];

    /**
     * @param $data
     */
    public function __construct($data = null) {
        if (is_array($data)) {
            $this->dataArray = $data;
//            foreach ($data as $key => $value)
//                $this->offsetSet($key, $value);
        } elseif ($data instanceof DBDataEntity)
            $this->dataArray = $data->getDataArray();
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        // Set Original Field Data on update so we know what was updated

        if (array_key_exists($offset, $this->dataArray) && $this->dataArray[$offset] !== $value)
            $this->originalData[$offset] = $this->dataArray[$offset];

        $this->dataArray[$offset] = $value;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->dataArray[$offset]);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        if (isset($this->dataArray[$offset]))
            unset($this->dataArray[$offset]);
    }

    /**
     * @param mixed $offset
     * @return null
     */
    public function offsetGet($offset)
    {
         return isset($this->dataArray[$offset]) ? $this->dataArray[$offset] : null;
    }

    /**
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->dataArray);
    }

    /**
     * @return array
     */
    public function getDataArray()
    {
        return $this->dataArray;
    }

    /**
     * @return array
     */
    public function toArray() {
        $data = $this->dataArray;
        foreach ($data as $key => $value) {
            if ($value instanceof DBDataEntity)
                $data[$key] = $value->toArray();
        }
        return $data;
    }

    /**
     * @param $data
     * @param null $value
     * @return $this
     * @throws BaseManagerEntityException
     */
    public function assign($data, $value = null)
    {
        // Extract Raw Data Array
        if ($data instanceof DBDataEntity) {
            if ($value !== null)
                throw new BaseManagerEntityException("value should null if name is a DBDataEntity Object");
            $data = $data->getDataArray();
        }

        // Handle Array
        if (is_array($data)) {
            if ($value !== null)
                throw new BaseManagerEntityException("value should null if name is an array");
            foreach ($data as $data_key => $data_value)
                $this->offsetSet($data_key, $data_value);
        // Handle Offset Key+Value Data
        } else
            $this->offsetSet($data, $value);

        return $this;
    }


    /**
     * @param $field
     * @return mixed|null
     * @throws EntityFieldAccessException
     */
    public function field($field)
    {
        return $this->offsetGet($field);
    }

    /**
     * @param $field
     * @param null $value
     * @return bool
     * @throws EntityFieldAccessException
     */
    public function matchField($field, $value = null)
    {
        return $this->offsetGet($field) == $value;
    }

    /**
     * @param $field
     * @return bool
     */
    public function hasField($field) {
        return $this->offsetExists($field);
    }

}

/**
 *
 * Primary Class that all DB Entities inherits properties from.
 *
 * Class DBDataEntity
 */
class DBDataEntity extends BaseDataEntity
{
    protected $fields = [];
    protected $removed_json_fields = [];
    protected $owner_field = null;

    protected $changed_data = [];
    protected $data_changed = false;

    /** @var array UserEntity */
    protected $owner_entity = [];

    protected $default_entity_template = "";

    /**
     * @return string
     */
    public function jsonSerialize()
    {
        return json_encode($this->getJSONData());
    }


    /**
     * Get the array of DB fields specified for this entity.
     *
     * @return array
     */
    public function getDbFields()
    {
        return $this->fields;
    }


    /**
     * @param $field
     * @return mixed|null
     * @throws EntityFieldAccessException
     */
    protected function getDBField($field)
    {
        if ($this->hasDbField($field))
            return $this->field($field);
        else {
            throw new EntityFieldAccessException("DB Field: '".$field."' does not exist in object: ".get_called_class());
        }
    }

    /**
     * @return array
     */
    public function getVFields()
    {
        $fields = [];
        $array_fields_list = array_keys($this->dataArray);
        foreach ($array_fields_list as $field)
            if (!$this->hasDbField($field))
                $fields[] = $field;

        return $fields;
    }


    /**
     * @param $field
     * @return mixed|null
     * @throws EntityFieldAccessException
     */
    protected function getVField($field)
    {
        if (!$this->hasDbField($field) && $this->hasField($field))
            return $this->field($field);
        else
            throw new EntityFieldAccessException("Virtual Field: '".$field."' does not exist in object: ".get_called_class());
    }


    /**
     * @param string $field
     * @param string|null $content
     * @return bool
     */
    public function fieldChanged($field, $content = null)
    {
        if (array_key_exists($field, $this->originalData)) {
            if ($content)
                return $this->originalData[$field] != $content;
            return true;
        }
        return $this->field($field) != $content;
    }

    /**
     * @param string $field
     * @return bool
     */
    public function hasDbField($field)
    {
        return in_array($field, $this->fields);
    }

    /**
     * Get the raw data array from the entity.
     *
     * @param bool|false $dbFieldsOnly
     * @return array
     */
    public function getDataArray($dbFieldsOnly = false)
    {
        $data = [];
        if ($dbFieldsOnly) {
            foreach ($this->dataArray as $key => $value) {
                if (in_array($key, $this->fields))
                    $data[$key] = $value;
            }
            return $data;
        }
        return $data ? $data : $this->dataArray;
    }

    /**
     * Get the raw data for this array minus removed fields for use in JSON in the frontend.
     *
     * @param bool|false $dbOnlyFields
     * @return array
     */
    public function getJSONData($dbOnlyFields = false)
    {
        $data = [];
        foreach ($this->dataArray as $key => $value) {
            if (!in_array($key, $this->removed_json_fields)) {
                if (is_array($value) || $value instanceof DBDataEntity) {
                    if ($dbOnlyFields) {
                        if (in_array($key, $this->fields))
                            $result = DBDataEntity::extractJsonDataArrays($value, $dbOnlyFields);
                    } else {
                        $result = DBDataEntity::extractJsonDataArrays($value, $dbOnlyFields);
                    }
                } else {
                     $result = $value;
                }

                $data[$key] = $result;
            }
        }
        return $data;
    }

    /**
     * Get the array of allowed DB fields for use in the frontend.
     *
     * @param bool|false $dbOnlyFields
     * @return array
     */
    public function getJSONFields($dbOnlyFields = false)
    {
        $data = [];

        $fields = !empty($this->dataArray) ? array_keys($this->dataArray) : [];

        if (empty($fields))
            return $data;

        foreach ($fields as $field) {
            if ($dbOnlyFields && array_key_exists($field, $this->fields))
                $data[] = $field;
            elseif (!$dbOnlyFields)
                $data = $field;
        }
        return $data;
    }

    /**
     * Wrapper for getting the active language from the request. May be overridden in subclasses.
     *
     * @param Request $request
     * @param null $default
     * @return null
     */
    public function getRequestLang(Request $request, $default = null)
    {
        return $request->get->readParam(GetRequest::PARAM_LANG, $default);
    }

    /**
     * Static Method to fetch raw data arrays from an array of DBDataEntity Objects.
     *
     * @param $source
     * @param bool|false $dbOnlyFields
     * @return array
     */
    public static function extractJsonDataArrays($source, $dbOnlyFields = false)
    {
        if (empty($source))
            return $source;

        $data = [];
        foreach ($source as $key => $s) {

            if (Validators::int($s))
                $s = (int) $s;

            if ($s instanceof DBDataEntity) {
                /** @var DBDataEntity $s */
                $data[$key] = self::extractJsonDataArrays($s->getJSONData($dbOnlyFields));
            } elseif ($s instanceof JSDataEntity) {
                $data[$key] = self::extractJsonDataArrays($s->getDataArray());
            } elseif (is_array($s)) {
                $data[$key] = self::extractJsonDataArrays($s, $dbOnlyFields);
            } else {
                $data[$key] = $s;
            }
        }
        if (empty($data) && !empty($source))
            $data = $source;
        return $data;
    }

    /**
     * Static Method to fetch raw data arrays from an array of DBDataEntity Objects.
     *
     * @param array $source
     * @param bool|false $dbOnlyFields
     * @return array
     */
    public static function extractDataArrays(Array $source, $dbOnlyFields = false)
    {
        // If source is empty, we can abort here!
        if (empty($source))
            return $source;

        $data = [];
        foreach ($source as $key => $s) {

            // Handle DBEntity Class
            if ($s instanceof DBDataEntity)
                $data[$key] = self::extractDataArrays($s->getDataArray($dbOnlyFields), $dbOnlyFields);

            elseif (is_array($s))
                $data[$key] = self::extractDataArrays($s, $dbOnlyFields);

            // Handle Everything Else
            else
                $data[$key] = $s;
        }

        // If we failed processing and result is empty but source is not, return source!
        if (empty($data) && !empty($source))
            $data = $source;

        return $data;
    }

    /**
     * @param Request $request
     * @return bool
     * @throws EntityFieldAccessException
     */
    public function is_owner(Request $request)
    {
        if (is_null($this->owner_field))
            throw new EntityFieldAccessException('Owner Field for '.get_called_class().' not defined');

        if (!$this->hasField($this->owner_field))
            throw new EntityFieldAccessException('Owner Field ('.$this->owner_field.') for '.get_called_class().' does not exist in object');

        return $this->matchField($this->owner_field, $request->user->id);
    }

    /**
     * @param Request $request
     * @return array|UserEntity
     * @throws EntityFieldAccessException
     */
    public function getOwner(Request $request)
    {
        if (is_null($this->owner_field))
            throw new EntityFieldAccessException('Owner Field for '.get_called_class().' not defined');

        return !empty($this->owner_entity) ? $this->owner_entity : $this->owner_entity = $request->managers->users()->getUserById(
            $request, $this->field($this->owner_field)
        );
    }

    /**
     * @return int|null
     * @throws EntityFieldAccessException
     */
    public function getOwnerId()
    {
        if (is_null($this->owner_field))
            throw new EntityFieldAccessException('Owner Field for '.get_called_class().' not defined');

        return $this->field($this->owner_field);
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return get_class($this);
    }

}

abstract class DBManagerEntity extends DBDataEntity {

    use
        hasInternalCurrentTimeStampField,
        hasDynamicFormFieldNameGenerator;

    const TRANSLATION_KEY_PREFIX = 'db';

    /** @var String */
    protected $managerClass;

    /** @var BaseEntityManager */
    protected $manager;

    /** @var  i18n $translations */
    protected $translations;


    /**
     * DBManagerEntity constructor.
     * @param null $data
     * @param Request $request
     * @param $manager_class
     * @param BaseEntityManager[]|array $foreignManagers
     */
    public function __construct($data, Request $request, $manager_class, $foreignManagers = [])
    {
        /** @var BaseEntityManager $manager_class */
        $this->manager = $manager_class::getInstance($request->db);
        $this->managerClass = $manager_class;
        // Run parent constructs and assign key value data from the raw source into this entity.
        parent::__construct($data);

        foreach ($this->manager->getDBFields() as $field) {

            // Now that we have set the current entity's primary fields in its' data-store, we can omit the non-aliased
            // current entity fields from the data we will pass into mapForeignEntities().
            if (array_key_exists($field, $data))
                unset($data[$field]);

            // If we still have an aliased version of the current entity's primary fields set, we can unset the
            // duplicate value and save memory usage.
            $aliasedFieldName = $this->manager->getAliasedFieldName($field);
            if (array_key_exists($aliasedFieldName, $this->dataArray))
                unset($this->dataArray[$aliasedFieldName]);
        }

        $this->translations = $request->translations;

        // Set Timestamp for when entity was created (used for automatic update fields)
        $this->updateField(VField::CURRENT_TIMESTAMP, $request->getCurrentSqlTime());
        // Fetch DB fields for this entity from its' manager class
        $this->fields = $this->manager->getDBFields();

        // Set Removed JSON Fields - Source Merge Order: Entity Specific -> EntityManager
        $this->removed_json_fields = array_merge($this->manager->base_removed_json_fields, $this->manager->removed_json_fields);
        // Check if we have any managers we join to that need entity mapping
        if ($foreignManagers)
            $this->mapForeignEntities($request, $data, $foreignManagers);

        // Check if there's any localizable timezone fields
        if ($dateTimeFields = array_intersect(array_keys($this->manager->localTimeZoneSourceFieldMappings), array_keys($this->dataArray))) {

            $estTimeZone = new DateTimeZone('America/New_York');

            foreach ($dateTimeFields as $dateTimeField) {
                if ($this->hasField($dateTimeField)) {
                    $localizedDateTimeField = $this->manager->localTimeZoneSourceFieldMappings[$dateTimeField];
                    $dt = new DateTime($this->field($dateTimeField));
                    $dt->setTimezone($estTimeZone);
                    $this->updateField($localizedDateTimeField, $dt->format(SQL_DATETIME));
                }
            }
        }

        // Process VFields
        $this->manager->processVFields($this, $request);
    }

    /**
     * @param Request $request
     * @param BaseEntityManager[]|array $foreignManagers
     */
    private function mapForeignEntities(Request $request, $data, $foreignManagers = [])
    {
        // Loop through provided foreign managers provided to see if this entity has sub-entities of the foreign manager type.
        foreach ($foreignManagers as $foreignManager) {
            $foreignManagerAliasedPKField = $foreignManager->getAliasedFieldName($foreignManager->getPkField());

            if (in_array($foreignManager->getClass(), $this->manager->getForeignManagerClasses()) && array_key_exists($foreignManagerAliasedPKField, $data)) {

                // We have a foreign manager mapping, let's look at its' fields and handle. Let's bootstrap the foreign
                // manager's entity by un-aliasing the primary fields for the entity.
                $foreignEntityBaseData = [];
                foreach ($foreignManager->getDBFields() as $foreignManagerDBField) {
                    // Generate the aliased SQL field representative name
                    $aliasedForeignManagerDBFieldName = $foreignManager->getAliasedFieldName($foreignManagerDBField);

                    // If we already stored the foreign aliased field in this entity's data, we should unset it.
                    if (array_key_exists($aliasedForeignManagerDBFieldName, $this->dataArray))
                        unset($this->dataArray[$aliasedForeignManagerDBFieldName]);

                    // If our provided data contains a field belonging to the foreign manager, we should add the un-aliased
                    // field and value to the data we'll pass to the foreign manager entity constructor.
                    if (array_key_exists($aliasedForeignManagerDBFieldName, $data))
                        $foreignEntityBaseData[$foreignManagerDBField] = $data[$aliasedForeignManagerDBFieldName];
                }

                // We have un-aliased the foreign entity's primary fields. Let's combine them with the total set of retrieved
                // aliased foreign fields. This allows the foreign entity to also map any sub-entities it requires from its'
                // own foreign manager mapping.
                $foreignEntityData = array_merge($foreignEntityBaseData, $data);

                // Finally we can create the sub-entity and insert it into the sub-entity virtual field. Since we are also
                // passing in the full set of foreign managers and aliased data, the constructor will recursively map all
                // child entities mapped to all other child entities automatically until the recursion completes.
                $this->dataArray[$foreignManager->getName(true)] = $foreignManager->createEntity($foreignEntityData, $request, $foreignManagers);
                //dump(get_class($this->dataArray[$foreignManager->getName(true)]));
            }

            foreach ($foreignManager->getDBFields() as $foreignManagerDBField) {
                // Generate the aliased SQL field representative name
                $aliasedForeignManagerDBFieldName = $foreignManager->getAliasedFieldName($foreignManagerDBField);

                // If we already stored the foreign aliased field in this entity's data, we should unset it.
                if (array_key_exists($aliasedForeignManagerDBFieldName, $this->dataArray))
                    unset($this->dataArray[$aliasedForeignManagerDBFieldName]);
            }
        }
    }

    /**
     * @param $field
     * @param bool|true $setDefaultValue
     * @return string
     */
    public function translateField($field, $variables = [])
    {
        $translationId = self::TRANSLATION_KEY_PREFIX.".{$this->manager->getName(true)}.{$this->getPk()}.{$field}";

        return $this->translations->get($translationId, $this->field($field), $variables);
    }

    public function __toString()
    {
        return dump(self::extractDataArrays($this->dataArray));
    }

    /**
     * @param null $manager_name
     * @return BaseEntityManager|Manager
     * @throws EntityManagerClassMissingException
     * @throws EntityManagerUndefinedException
     */
    public function getManager($manager_name = null)
    {
        // If we don't have a manager name override, let's try the default settings.
        if ($manager_name && $manager_name != $this->managerClass)
            return $this->_getManagerByName($manager_name);

        // If we're looking for the Entity's manager, but have not set the class object yet, lets do that first.
        if (!$this->manager instanceof BaseEntityManager && class_exists($this->managerClass))
            $this->manager = $this->_getManagerByName($this->managerClass);

        // Return Manager for Entity
        return $this->manager;
    }

    /**
     * @param $manager_name
     * @return Manager
     * @internal Used to get managers other than this entity.
     * @throws EntityManagerClassMissingException
     * @throws EntityManagerUndefinedException
     */
    private function _getManagerByName($manager_name) {

        if (empty($manager_name))
            throw new EntityManagerUndefinedException('Manager undefined for entity: '.get_called_class());

        if (!class_exists($manager_name))
            throw new EntityManagerClassMissingException('Manager class "'.$manager_name.'" does not exist');

        /** @var BaseEntityManager $manager_name */
        return $manager_name::getInstance();
    }

    /**
     * @param BaseEntityManager $manager
     * @return $this
     */
    public function setManager(BaseEntityManager $manager)
    {
        $this->manager = $manager;
        $this->managerClass = get_class($manager);
        return $this;
    }

    /**
     * @param $idField
     * @param $valueField
     * @return array
     */
    public function getOption($valueField = DBField::DISPLAY_NAME, $idField = null)
    {
        if (!$this->hasField($valueField)) {
            if ($this->hasField(DBField::NAME))
                $valueField = DBField::NAME;
            elseif ($this->hasField(DBField::TITLE))
                $valueField = DBField::TITLE;
        }

        return [
            DBField::VALUE => $idField ? $this->field($idField) : $this->getPk(),
            DBField::DISPLAY_NAME => $this->field($valueField)
        ];
    }

    /**
     * @return bool|string
     */
    public function getBasicCacheBuster($cacheBuster = BaseEntityManager::METHOD_BUST_BASIC_CACHE)
    {
        return $this->hasBasicCacheBuster($cacheBuster) ? $cacheBuster : null;
    }

    /**
     * @param string $cacheBuster
     * @return bool
     */
    public function hasBasicCacheBuster($cacheBuster = BaseEntityManager::METHOD_BUST_BASIC_CACHE)
    {
        return method_exists($this->manager, $cacheBuster);
    }

    /**
     * @throws EntityManagerClassMissingException
     * @throws EntityManagerUndefinedException
     */
    public function bustBasicCache()
    {
        if ($cacheBuster = $this->getBasicCacheBuster())
            return $this->manager->$cacheBuster($this);
        else
            return false;
    }

    /**
     * @return string
     */
    public function generateCacheKeyPrefix($suffix = "")
    {
        return $this->getManager()->buildCacheKey(".{$this->getPk()}{$suffix}");
    }



    /**
     * @return string|null
     * @throws EntityFieldAccessException
     */
    public function getSlug()
    {
        if ($this->hasField(DBField::SLUG))
            return $this->field(DBField::SLUG);

        if ($this->hasField(DBField::NAME))
            return slugify($this->field(DBField::NAME));

        throw new EntityFieldAccessException("Slug Field undefined for entity: ".get_called_class());
    }
    /**
     * @return null|string
     */
    public function getSlugField()
    {
        return $this->getManager()->getSlugField();
    }

    /**
     * @return array
     */
    public function getDbFields()
    {
        return $this->getManager()->getDBFields();
    }

    /**
     * @return string
     */
    public function getPk()
    {
        return $this->getManager()->getPk($this);
    }

    /**
     * @return string
     */
    public function getPkField()
    {
        return $this->getManager()->getPkField();
    }

    /**
     * @param $value
     * @return bool
     */
    public function pkIs($value)
    {
        return $this->matchField($this->getPkField(), $value);
    }

    /**
     * @return bool
     */
    public function needs_save()
    {
        return !empty($this->originalData);
    }

    /**
     * @return string
     */
    public function getClass() {
        return get_class($this);
    }


    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function updateField($field, $value) {

        $this->offsetSet($field, $value);

        return $this;
    }

    /**
     * Overrides for DBManagerEntity
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if (in_array($offset, $this->manager->getDBFields())) {
            parent::offsetSet($offset, $value);
        } else {
            $this->dataArray[$offset] = $value;
        }
    }

    /**
     * @param Request $request
     */
    public function setNewUpdaterData(Request $request)
    {
        if ($this->hasDbField(DBField::UPDATER_ID))
            $this->updateField(DBField::UPDATER_ID, $request->user->id);

        $dateTime = new DateTime();

        if ($this->hasDbField(DBField::UPDATE_DATE))
            $this->updateField(DBField::UPDATE_DATE, $dateTime->format(SQL_DATETIME));

        if ($this->hasDbField(DBField::UPDATE_TIME))
            $this->updateField(DBField::UPDATE_TIME, $dateTime->format(SQL_DATETIME));

        if ($this->hasDbField(DBField::MODIFIED_BY))
            $this->updateField(DBField::MODIFIED_BY, $request->requestId);
    }

    /**
     * @param Request $request
     * @param bool|true $bustCache
     * @return $this|bool
     */
    public function saveEntityToDb(Request $request, $bustCache = true)
    {
        // If we don't need to save, skip that part!
        if (!$this->needs_save() || !$this->getUpdatedDbFieldData())
            return false;

        // Updater Information
        $this->setNewUpdaterData($request);

        // Update DB Data
        $upd = $this->manager->query($request->db)
            ->filter($this->manager->filters->byPk($this->getPk()))
            ->update($this->getUpdatedDbFieldData());

        // Run any entity specific post-save dependencies
        $this->handle_after_save($request);

        // Bust Cache if applicable
        if ($bustCache && $this->hasBasicCacheBuster())
            $this->bustBasicCache();

        return $this;
    }

    /**
     * @return array
     */
    public function getUpdatedDbFieldData()
    {
        $updatedData = [];

        foreach ($this->originalData as $key => $data)
            if ($this->hasDbField($key))
                $updatedData[$key] = $this->field($key);

        return $updatedData;
    }

    /**
     * @return array
     */
    public function getUpdatedData()
    {
        $updatedData = [];

        foreach ($this->originalData as $key => $data) {
            $updatedData[$key] = $this->field($key);
        }

        return $updatedData;
    }

    /**
     * @param $offset
     * @return null
     */
    public function getOrigValue($offset)
    {
        return array_key_exists($offset, $this->originalData) ? $this->originalData[$offset] : null;
    }

    /**
     * @return array|null
     */
    public function getAllOrigValues()
    {
        return !empty($this->originalData) ? $this->originalData : null;
    }


    /**
     * @param Request $request
     * @return $this
     */
    public function delete(Request $request, $updateDB = true)
    {
        if ($this->hasField(DBField::IS_DELETED))
            $this->updateField(DBField::IS_DELETED, 1);

        if ($this->hasField(DBField::DELETER_ID))
            $this->updateField(DBField::DELETER_ID, $request->user->id);

        if ($this->hasField(DBField::DELETED_DATE))
            $this->updateField(DBField::DELETED_DATE, $request->getCurrentSqlTime());

        if ($this->hasField(DBField::UPDATER_ID))
            $this->updateField(DBField::UPDATER_ID, $request->user->id);

        if ($this->hasField(DBField::UPDATE_DATE))
            $this->updateField(DBField::UPDATE_DATE, $request->getCurrentSqlTime());

        if ($updateDB)
            $this->manager->query($request->db)->byPk($this->getPk())->soft_delete($this);

        $this->bustBasicCache();

        return $this;
    }


    /**
     * @param Form $form
     * @return $this
     * @throws BaseManagerEntityException
     */
    public function assignByForm(Form $form)
    {
        $updated_form_data = $form->getAllCleanedData();

        if ($updated_form_data)
            $this->assign($updated_form_data);

        return $this;
    }

    /**
     * Get the raw data for this array minus removed fields for use in JSON in the frontend.
     *
     * @param bool|false $dbOnlyFields
     * @return array
     */
    public function getJSONData($dbOnlyFields = false)
    {
        $data = [];
        $data[DBField::TYPE] = get_class($this);
        foreach ($this->dataArray as $key => $value) {

            if (Validators::int($value))
                $value = (int) $value;

            if (!in_array($key, $this->removed_json_fields)) {
                if (is_array($value)) {
                    if ($dbOnlyFields) {
                        if (in_array($key, $this->fields))
                            $result = DBManagerEntity::extractJsonDataArrays($value, $dbOnlyFields);
                        else
                            continue;
                    } else {
                        $result = DBManagerEntity::extractJsonDataArrays($value, $dbOnlyFields);
                    }
                } elseif ($value instanceof DBManagerEntity) {
                    $result = $value->getJSONData($dbOnlyFields);
                } else {
                    $result = $value;
                }

                if ($key == $this->getPkField())
                    $data[DBField::ID] = $result;
                else
                    $data[$key] = $result;
            }
        }
        return $data;
    }


    /**
     * @param Request $request
     * @return $this
     */
    protected function handle_after_save(Request $request)
    {
        if (method_exists($this, "_handle_after_save"))
            $this->_handle_after_save($request);
        return $this;
    }
}

/**
 * Class BaseIdentityDBEntity
 */
abstract class BaseIdentityDBEntity extends DBManagerEntity {

    public function getAvatarUrl()
    {
        return $this->getVField(VField::AVATAR_URL);
    }

    public function getAvatarSmallUrl()
    {
        return $this->getVField(VField::AVATAR_SMALL_URL);
    }

    public function getAvatarTinyUrl()
    {
        return $this->getVField(VField::AVATAR_TINY_URL);
    }

}


abstract class BaseTrackingEntity extends DBManagerEntity {

    /** @var GeoRegionEntity */
    protected $geo_region;

    /**
     * @return int
     * @throws EntityFieldAccessException
     */
    public function getGuestId()
    {
        return $this->getDBField(DBField::GUEST_ID);
    }

    /**
     * @return string
     * @throws EntityFieldAccessException
     */
    public function getGuestHash()
    {
        return $this->getDBField(DBField::GUEST_HASH);
    }

    /**
     * @return string
     * @throws EntityFieldAccessException
     */
    public function getCountryId()
    {
        return $this->getDBField(DBField::COUNTRY);
    }

    /**
     * @return string
     * @throws EntityFieldAccessException
     */
    public function getDeviceTypeId()
    {
        return $this->getDBField(DBField::DEVICE_TYPE_ID);
    }

    /**
     * @param Request $request
     * @param bool|false $time_ago
     * @param string $date_format
     * @return bool|string
     * @throws EntityFieldAccessException
     */
    public function getTimeOfFirstVisit(Request $request, $time_ago = false, $date_format = ESCConfiguration::DATE_FORMAT_SQL_POST_DATE)
    {
        return $time_ago ? time_elapsed_string($request->translations, $this->getDBField(DBField::CREATE_TIME)) : date($date_format, $this->getDBField(DBField::CREATE_TIME));
    }

    /**
     * @param Request $request
     * @return GeoRegionEntity
     */
    public function getGeoRegion(Request $request)
    {
        $geoRegionsManager = $request->managers->geoRegions();

        return !empty($this->geo_region) ? $this->geo_region : $this->geo_region = $geoRegionsManager->getGeoRegionById(
            $request,
            $this->field(DBField::GEO_REGION_ID)
        );
    }

}
abstract class BaseDeviceTrackingEntity extends BaseTrackingEntity {

    /**
     * @return string
     * @throws EntityFieldAccessException
     */
    public function getLandingPage()
    {
        return $this->getDBField(DBField::ORIGINAL_URL);
    }

    /**
     * @return string
     * @throws EntityFieldAccessException
     */
    public function getOriginalReferer()
    {
        return $this->getDBField(DBField::ORIGINAL_REFERRER);
    }

    /**
     * @return string
     * @throws EntityFieldAccessException
     */
    public function getUserAgent()
    {
        return $this->getDBField(DBField::HTTP_USER_AGENT);
    }

}


/**
 * Tags Base
 */

abstract class TagEntity extends DBManagerEntity {}
