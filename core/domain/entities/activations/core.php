<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 3/22/19
 * Time: 10:23 AM
 */

class ActivationEntity extends DBManagerEntity
{
    use
        hasActivationTypeIdField,
        hasActivationStatusIdField,
        hasActivationGroupIdField,
        hasIsPublicField,
        hasHostIdField,
        hasStartTimeField,
        hasEndTimeField,
        hasDisplayNameField,
        hasGameIdField,
        hasGameModIdField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualAvatarsField,
        hasVirtualHostField,
        hasVirtualGameField,
        hasVirtualGameModField;

    /**
     * @param ActivationStatusEntity $activationStatus
     */
    public function setActivationStatus(ActivationStatusEntity $activationStatus)
    {
        $this->dataArray[VField::ACTIVATION_STATUS] = $activationStatus;
    }

    /**
     * @return ActivationStatusEntity
     */
    public function getActivationStatus()
    {
        return $this->getVField(VField::ACTIVATION_STATUS);
    }

    /**
     * @param ActivationTypeEntity $activationType
     */
    public function setActivationType(ActivationTypeEntity $activationType)
    {
        $this->dataArray[VField::ACTIVATION_TYPE] = $activationType;
    }

    /**
     * @return ActivationTypeEntity
     */
    public function getActivationType()
    {
        return $this->getVField(VField::ACTIVATION_TYPE);
    }
}

class ActivationTypeEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class ActivationStatusEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class ActivationGroupEntity extends DBManagerEntity
{
    use
        hasOrganizationIdField,
        hasHostIdField,
        hasStartTimeField,
        hasEndTimeField,
        hasDisplayNameField,
        hasTimeZoneField,
        hasServiceAccessTokenInstanceIdField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualActivationsField,
        hasVirtualHostField;
}