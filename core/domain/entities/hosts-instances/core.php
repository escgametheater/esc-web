<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 11/29/18
 * Time: 12:19 PM
 */


class HostInstanceEntity extends DBManagerEntity {

    use
        hasHostInstanceTypeIdField,
        hasUserIdField,
        hasHostIdField,
        hasHostDeviceIdField,
        hasHostVersionIdField,
        hasPlatformIdField,
        hasLocationIdField,
        hasAddressIdField,
        hasNetworkIdField,
        hasIsPublicField,
        hasPublicHostNameField,
        hasPublicHostDomainField,
        hasLocalIpAddressField,
        hasLocalPortField,
        hasDnsIdField,
        hasDnsIsActiveField,
        hasStartTimeField,
        hasEndTimeField,
        hasLastPingTimeField,
        hasExitStatusField,
        hasPubSubChannelField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualHostDeviceField,
        hasVirtualUserIsHostAdminField,
        hasVirtualUrlField,
        hasVirtualHostField,
        hasVirtualHostInstanceTypeField,
        hasVirtualNetworkField,
        hasVirtualLocationField,
        hasVirtualPlatformField,
        hasVirtualUserField,
        hasVirtualHostVersionField,
        hasVirtualActivationsField;

    /** @var GameInstanceEntity[]  */
    protected $gameInstances = [];

    /**
     * @param GameInstanceEntity $gameInstance
     */
    public function setGameInstance(GameInstanceEntity $gameInstance)
    {
        $this->dataArray[VField::GAME_INSTANCES][] = $gameInstance;
        $this->gameInstances[$gameInstance->getPk()] = $gameInstance;
    }

    /**
     * @return GameInstanceEntity[]
     */
    public function getGameInstances()
    {
        return $this->getVField(VField::GAME_INSTANCES);
    }

    /**
     * @return GameInstanceEntity
     */
    public function getActiveGameInstance()
    {
        foreach ($this->getGameInstances() as $gameInstance) {
            if (!$gameInstance->has_ended())
                return $gameInstance;
        }
    }

    /**
     * @param $gameInstanceId
     * @return array|GameInstanceEntity
     */
    public function getGameInstanceById($gameInstanceId)
    {
        return isset($this->gameInstances[$gameInstanceId]) ? $this->gameInstances[$gameInstanceId] : [];
    }

    /**
     * @param string $suffix
     * @return string
     */
    public function getHostInstanceAdminPubSubKey($suffix = '')
    {
        $adminHash = md5(sha1($this->getPubSubChannel()));
        return "host-{$adminHash}{$suffix}";
    }

    /**
     * @return bool
     */
    public function is_type_esc_host_app()
    {
        return $this->getHostInstanceTypeId() == HostsInstancesTypesManager::ID_HOST_APP;
    }

}

class HostInstanceTypeEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasPriorityField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class HostInstanceDeviceEntity extends DBManagerEntity
{
    use
        hasHostInstanceIdField,
        hasDeviceHashField,
        hasUserIdField,
        hasGuestIdField,
        hasGuestHashField,
        hasSessionIdField,
        hasStartTimeField,
        hasEndTimeField,
        hasLastPingTimeField,
        hasExitStatusField,
        hasPlayerRequestIdField,
        hasCreateTimeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}


class HostInstanceInviteEntity extends DBManagerEntity
{
    use
        hasHostInstanceInviteTypeIdField,
        hasHostInstanceIdField,
        hasGameInstanceIdField,
        hasInviteHashField,
        hasHostInstanceDeviceIdField,
        hasUserIdField,
        hasSmsIdField,
        hasEmailTrackingIdField,
        hasShortUrlIdField,
        hasInviteRecipientField,
        hasCreateTimeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class HostInstanceInviteTypeEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}