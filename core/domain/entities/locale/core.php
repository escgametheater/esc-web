<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 11/19/16
 * Time: 2:29 PM
 */

class GeoIpMapperEntity extends DBManagerEntity {

    use
        hasIpFromField,
        hasIpToField,
        hasCountryIdField,
        hasRegionNameField,
        hasCityNameField,
        hasLatitudeField,
        hasLongitudeField,
        hasZipCodeField,
        hasTimeZoneField;
}

class GeoRegionEntity extends DBManagerEntity {

    use
        hasDisplayNameField,
        hasGeoRegionIdField,
        hasIsActiveField,
        hasCreatorIdField,
        hasCreateTimeField;
}

class LanguageEntity extends DBManagerEntity {

    use
        hasDisplayNameField,
        hasI18nActiveField,
        hasI18nPublicField;

}

class CountryEntity extends DBManagerEntity {

    use
        hasIso3Field,
        hasPhoneCodeField,
        hasDisplayNameField,
        hasGeoRegionIdField;
}

class CurrencyEntity extends DBManagerEntity {

    use
        hasNameField,
        hasDisplayNameField,
        hasIsActiveField,
        hasAuditFields;

}

class AddressEntity extends DBManagerEntity {

    use
        hasAddressTypeIdField,
        hasContextEntityTypeIdField,
        hasContextEntityIdField,
        hasIsPrimaryField,
        hasDisplayNameField,
        hasPhoneNumberField,
        hasAddressLine1Field,
        hasAddressLine2Field,
        hasAddressLine3Field,
        hasCityField,
        hasStateField,
        hasZipField,
        hasCountryIdField,
        hasIsActiveField,
        hasAuditFields,
        hasVirtualCountryField;

    /**
     * @return string
     */
    public function getFirstName()
    {
        return $this->field(DBField::FIRSTNAME);
    }

    /**
     * @return string
     */
    public function getLastName()
    {
        return $this->field(DBField::LASTNAME);
    }
}

class AddressTypeEntity extends DBManagerEntity {

    use
        hasNameField,
        hasDisplayNameField,
        hasDisplayOrderField,
        hasIsActiveField,
        hasAuditFields;
}


class TranslationEntity extends DBManagerEntity {

    use
        hasLanguageIdField,
        hasTextField;

}