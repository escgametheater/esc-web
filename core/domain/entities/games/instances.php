<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 2/25/19
 * Time: 11:45 AM
 */


class GameInstanceEntity extends DBManagerEntity {

    use
        hasGameIdField,
        hasGameBuildIdField,
        hasGameModBuildIdField,
        hasActivationIdField,
        hasHostInstanceIdField,
        hasPubSubChannelField,
        hasMinimumPlayersField,
        hasMaximumPlayersField,
        hasStartTimeField,
        hasEndTimeField,
        hasLastPingTimeField,
        hasExitStatusField,

        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualUrlField,
        hasVirtualGameField,
        hasVirtualGameBuildField,
        hasVirtualGameModField,
        hasVirtualGameModBuildField,
        hasVirtualGameControllersField,
        hasVirtualActivationField,
        hasVirtualGameInstanceLogsField,
        hasVirtualGameInstanceRoundsField;

    /**
     * @return bool
     */
    public function isShimmedInstance()
    {
        return $this->getPk() === -1;
    }

    /**
     * @return string
     */
    public function getAdminUrl()
    {
        return $this->getUrl("/" . GamesControllersTypesManager::SLUG_ADMIN);
    }

    /**
     * @return bool
     */
    public function has_running_round()
    {
        foreach ($this->getGameInstanceRounds() as $gameInstanceRound) {
            if (!$gameInstanceRound->getEndTime())
                return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function has_admin_controller()
    {
        return $this->getGameBuild()->getAdminController() ? true : false;
    }
}

class GameInstanceRoundEntity extends DBManagerEntity {

    use
        hasGameInstanceIdField,
        hasStartTimeField,
        hasEndTimeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param GameInstanceRoundPlayerEntity $gameInstanceRoundPlayer
     */
    public function setGameInstanceRoundPlayer(GameInstanceRoundPlayerEntity $gameInstanceRoundPlayer)
    {
        $this->dataArray[VField::PLAYERS][] = $gameInstanceRoundPlayer;
    }

    /**
     * @return GameInstanceRoundPlayerEntity[]
     */
    public function getGameInstanceRoundPlayers()
    {
        return $this->getVField(VField::PLAYERS);
    }
}

class GameInstanceRoundPlayerEntity extends DBManagerEntity {

    use
        hasGameInstanceRoundIdField,
        hasSessionIdField,
        hasUserIdField,
        hasStartTimeField,
        hasEndTimeField,
        hasLastPingTimeField,
        hasExitStatusField,
        hasPlayerRequestIdField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

}

class GameInstanceRoundEventEntity extends DBManagerEntity {

    use
        hasGameInstanceRoundIdField,
        hasGameIdField,
        hasEventKeyField,
        hasGameInstanceRoundPlayerIdField,
        hasValueField,
        hasCreateTimeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param GameInstanceRoundEventPropertyEntity $property
     */
    public function setProperty(GameInstanceRoundEventPropertyEntity $property)
    {
        $this->dataArray[VField::PROPERTIES][] = $property;
    }

    /**
     * @return GameInstanceRoundEventPropertyEntity[]
     */
    public function getProperties()
    {
        return $this->getVField(VField::PROPERTIES);
    }
}

class GameInstanceRoundEventPropertyEntity extends DBManagerEntity {

    use
        hasGameInstanceRoundEventIdField,
        hasKeyField,
        hasValueField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

}
class GameInstanceLogEntity extends DBManagerEntity
{
    use
        hasGameInstanceIdField,
        hasGameInstanceLogStatusIdField,
        hasPubSubChannelTypeField,
        hasPubSubChannelField,
        hasProcessingTimeField,
        hasMessageCountField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualGameInstanceLogStatusField,
        hasVirtualGameInstanceLogAssetField;
}

class GameInstanceLogStatusEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}
