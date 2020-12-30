<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 10/26/18
 * Time: 11:43 AM
 */

class ShortUrlEntity extends DBManagerEntity
{
    use
        hasSlugField,
        hasSchemeField,
        hasHostField,
        hasUriField,
        hasParamsField,
        hasAcqMediumField,
        hasAcqSourceField,
        hasAcqCampaignField,
        hasAcqTermField,
        hasTotalViewsField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualUrlField,
        hasVirtualTargetUrlField;
}