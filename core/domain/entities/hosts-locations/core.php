<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/2/18
 * Time: 5:04 PM
 */


class LocationEntity extends DBManagerEntity {

    use
        hasHostIdField,
        hasLocationHashField,
        hasLatitudeField,
        hasLongitudeField,
        hasDisplayNameField,
        hasCreatedByField,
        hasModifiedByField,

        hasVirtualAddressField;
}

class HostEntity extends DBManagerEntity {

    use
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasSlugField,
        hasAlternateUrlField,
        hasDisplayNameField,
        hasIsProdField,
        hasHasCustomSlugField,
        hasOfflineGameIdField,
        hasOfflineGameModIdField,
        hasCreatedByField,
        hasModifiedByField,

        hasVirtualUrlField,
        hasVirtualGameField;

    /**
     * @param ScreenEntity $screen
     */
    public function setScreen(ScreenEntity $screen)
    {
        $this->dataArray[VField::SCREENS][] = $screen;
    }

    /**
     * @return ScreenEntity[]
     */
    public function getScreens()
    {
        return $this->getVField(VField::SCREENS);
    }

    /**
     * @param NetworkEntity $network
     */
    public function setNetwork(NetworkEntity $network)
    {
        $this->dataArray[VField::NETWORKS][] = $network;
    }

    /**
     * @return NetworkEntity[]
     */
    public function getNetworks()
    {
        return $this->getVField(VField::NETWORKS);
    }

    /**
     * @return string
     */
    public function getPubSubChannel()
    {
        return $this->field(DBField::PUB_SUB_CHANNEL);
    }
}

class HostDeviceEntity extends DBManagerEntity
{
    use
        hasUuidField,
        hasPlatformIdField,
        hasDisplayNameField,
        hasCreateTimeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualUrlField;

    /** @var HostDeviceComponentEntity[] */
    protected $hostDeviceComponentsByName = [];

    /**
     * @param HostDeviceComponentEntity $hostDeviceComponent
     */
    public function setHostDeviceComponent(HostDeviceComponentEntity $hostDeviceComponent)
    {
        $this->dataArray[VField::HOST_DEVICE_COMPONENTS][$hostDeviceComponent->getPk()] = $hostDeviceComponent;
        $this->hostDeviceComponentsByName[$hostDeviceComponent->getDisplayName()] = $hostDeviceComponent;
    }

    /**
     * @param null $hostDeviceComponentId
     * @return HostDeviceComponentEntity|HostDeviceComponentEntity[]
     */
    public function getHostDeviceComponents($hostDeviceComponentId = null)
    {
        if ($hostDeviceComponentId)
            return $this->dataArray[VField::HOST_DEVICE_COMPONENTS][$hostDeviceComponentId] ?? [];
        else
            return $this->dataArray[VField::HOST_DEVICE_COMPONENTS];
    }

    /**
     * @param $name
     * @return array|HostDeviceComponentEntity
     */
    public function getHostDeviceComponentByName($name)
    {
        return $this->hostDeviceComponentsByName[$name] ?? [];
    }

    /**
     * @return array
     */
    public function getSpecsArray()
    {
        $data = [];

        foreach ($this->getHostDeviceComponents() as $hostDeviceComponent) {
            $data[$hostDeviceComponent->getDisplayName()] = $hostDeviceComponent->getProcessedValues();
        }

        return $data;
    }
}

class HostDeviceComponentEntity extends DBManagerEntity
{
    use
        hasHostDeviceIdField,
        hasDisplayNameField,
        hasValueField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualProcessedValuesField;
}


class ScreenEntity extends DBManagerEntity
{
    use
        hasScreenIdField,
        hasHostIdField,
        hasNetworkIdField,
        hasDisplayNameField,
        hasCreatedByField,
        hasModifiedByField;
}

class NetworkEntity extends DBManagerEntity
{
    use
        hasNetworkIdField,
        hasHostIdField,
        hasDisplayNameField,
        hasSsidField,
        hasPasswordField,
        hasCreatedByField,
        hasModifiedByField;
}