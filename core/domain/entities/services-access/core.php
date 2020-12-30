<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 12/12/18
 * Time: 12:34 AM
 */

class ServiceAccessTokenEntity extends DBManagerEntity
{
    use
        hasServiceAccessTokenTypeIdField,
        hasTokenField,
        hasOrganizationIdField,
        hasGameIdField,
        hasNetPriceField,
        hasMaxSeatsField,
        hasDurationField,
        hasStartTimeField,
        hasEndTimeField,
        hasOriginalUsesField,
        hasRemainingUsesField,
        hasIsActiveField,
        hasCreatorIdField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param ServiceAccessTokenTypeEntity $serviceAccessTokenType
     */
    public function setServiceAccessTokenType(ServiceAccessTokenTypeEntity $serviceAccessTokenType)
    {
        $this->updateField(VField::SERVICE_ACCESS_TOKEN_TYPE, $serviceAccessTokenType);
    }

    /**
     * @return ServiceAccessTokenTypeEntity|array
     */
    public function getServiceAccessTokenType()
    {
        return $this->getVField(VField::SERVICE_ACCESS_TOKEN_TYPE);
    }
}

class ServiceAccessTokenTypeEntity extends DBManagerEntity
{
    use
        hasServiceAccessTokenTypeGroupIdField,
        hasPriorityField,
        hasSlugField,
        hasDisplayNameField,
        hasDescriptionField,
        hasIsBuyableField,
        hasIsOrganizationCreatableField,
        hasNetPriceField,
        hasOriginalUsesField,
        hasMaxSeatsField,
        hasDurationField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatorIdField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param ServiceAccessTokenTypeGroupEntity $serviceAccessTokenTypeGroup
     */
    public function setServiceAccessTokenTypeGroup(ServiceAccessTokenTypeGroupEntity $serviceAccessTokenTypeGroup)
    {
        $this->updateField(VField::SERVICE_ACCESS_TOKEN_TYPE_GROUP, $serviceAccessTokenTypeGroup);
    }

    /**
     * @return ServiceAccessTokenTypeGroupEntity|array
     */
    public function getServiceAccessTokenTypeGroup()
    {
        return $this->getVField(VField::SERVICE_ACCESS_TOKEN_TYPE_GROUP);
    }


}

class ServiceAccessTokenTypeCategoryEntity extends DBManagerEntity
{
    use
        hasPriorityField,
        hasSlugField,
        hasDisplayNameField,
        hasDescriptionField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatorIdField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class ServiceAccessTokenTypeGroupEntity extends DBManagerEntity
{
    use
        hasServiceAccessTokenTypeCategoryIdField,
        hasPriorityField,
        hasSlugField,
        hasDisplayNameField,
        hasDescriptionField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatorIdField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualServiceAccessTokenTypeCategoryField;
}

class ServiceAccessTokenInstanceEntity extends DBManagerEntity
{
    use
        hasServiceAccessTokenIdField,
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasStartTimeField,
        hasEndTimeField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatorIdField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param ServiceAccessTokenEntity $serviceAccessToken
     */
    public function setServiceAccessToken(ServiceAccessTokenEntity $serviceAccessToken)
    {
        $this->updateField(VField::SERVICE_ACCESS_TOKEN, $serviceAccessToken);
    }

    /**
     * @return ServiceAccessTokenEntity
     */
    public function getServiceAccessToken()
    {
        return $this->getVField(VField::SERVICE_ACCESS_TOKEN);
    }
}