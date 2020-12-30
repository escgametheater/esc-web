<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 3/22/19
 * Time: 10:18 AM
 */

Entities::uses('activations');

class ActivationsManager extends BaseEntityManager
{
    protected $pk = DBField::ACTIVATION_ID;
    protected $entityClass = ActivationEntity::class;
    protected $table = Table::Activation;
    protected $table_alias = TableAlias::Activation;

    public static $fields = [
        DBField::ACTIVATION_ID,
        DBField::ACTIVATION_TYPE_ID,
        DBField::ACTIVATION_STATUS_ID,
        DBField::ACTIVATION_GROUP_ID,
        DBField::IS_PUBLIC,
        DBField::HOST_ID,
        DBField::START_TIME,
        DBField::END_TIME,
        DBField::DISPLAY_NAME,
        DBField::GAME_ID,
        DBField::GAME_MOD_ID,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param ActivationEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $imageTypeSizesManager = $request->managers->imagesTypesSizes();

        if (!$data->hasField(VField::GAME))
            $data->updateField(VField::GAME, []);

        if (!$data->hasField(VField::GAME_MOD))
            $data->updateField(VField::GAME_MOD, []);

        $avatars = [];
        $imageTypeSizes = $imageTypeSizesManager->getAllImageTypeSizesByImageTypeId($request, ImagesTypesManager::ID_ACTIVATION_AVATAR);
        foreach ($imageTypeSizes as $imageTypeSize) {
            $avatars[$imageTypeSize->generateUrlField()] = $request->getWwwUrl("/static/images/placeholder-square.jpg?b=1");
        }
        $data->updateField(VField::AVATAR, $avatars);

    }

    /**
     * @param Request $request
     * @param ActivationEntity|ActivationEntity[] $activations
     */
    public function postProcessActivations(Request $request, $activations, $expand = false)
    {
        $activationStatusesManager = $request->managers->activationsStatuses();
        $activationsTypesManager = $request->managers->activationsTypes();
        $imagesManager = $request->managers->images();

        $hostsManager = $request->managers->hosts();
        $gamesManager = $request->managers->games();
        $gamesModsManager = $request->managers->gamesMods();

        if ($activations) {
            if ($activations instanceof ActivationEntity)
                $activations = [$activations];

            /** @var ActivationEntity[] $activations */
            $activations = array_index($activations, DBField::ACTIVATION_ID);
            $activationIds = array_keys($activations);

            $hostIds = array_extract(DBField::HOST_ID, $activations);
            $hosts = $hostsManager->getHostsByIds($request, $hostIds);
            $hosts = $hostsManager->index($hosts);

            if ($expand) {
                $gameIds = unique_array_extract(DBField::GAME_ID, $activations);
                $games = $gamesManager->getGamesByIds($request, $gameIds, true);
                $games = $gamesManager->index($games);

                $gameModsIds = unique_array_extract(DBField::GAME_MOD_ID, $activations);
                if ($gameModsIds) {
                    $gameMods = $gamesModsManager->getGameModsByIds($request, $gameModsIds, $expand);
                    $gameMods = $gamesModsManager->index($gameMods);
                }
            }


            foreach ($activations as $activation) {
                $activation->setActivationStatus($activationStatusesManager->getActivationStatusById($request, $activation->getActivationStatusId()));
                $activation->setActivationType($activationsTypesManager->getActivationTypeById($request, $activation->getActivationTypeId()));

                $host = $hosts[$activation->getHostId()];
                $activation->setHost($host);

                if ($expand) {
                    if ($activation->getGameId()) {
                        $game = $games[$activation->getGameId()];
                        $activation->setGame($game);
                    }

                    if ($activation->getGameModId()) {
                        $gameMod = $gameMods[$activation->getGameModId()];
                        $activation->setGameMod($gameMod);
                    }
                }
            }

            // Get/set game thumbnail images
            $avatarImages = $imagesManager->getActiveActivationAvatarImagesByActivationIds($request, $activationIds);
            foreach ($avatarImages as $avatarImage) {
                $activations[$avatarImage->getContextEntityId()]->setAvatarImageUrls($avatarImage);
            }

        }
    }

    /**
     * @param Request $request
     * @param $activationTypeId
     * @param $hostId
     * @param $displayName
     * @param $gameId
     * @param null $gameModId
     * @param int $isPublic
     * @param null $startTime
     * @param null $endTime
     * @return ActivationEntity
     */
    public function createNewActivation(Request $request, $activationTypeId, $hostId, $displayName, $activationGroupId = null,
                                        $isPublic = 1,$startTime = null, $endTime = null, $gameId = null, $gameModId = null,
                                        $activationStatusId = ActivationsStatusesManager::ID_DRAFT)
    {
        if ($startTime === null)
            $startTime = $request->getCurrentSqlTime();

        $activationData = [
            DBField::ACTIVATION_TYPE_ID => $activationTypeId,
            DBField::ACTIVATION_STATUS_ID => $activationStatusId,
            DBField::ACTIVATION_GROUP_ID => $activationGroupId,
            DBField::IS_PUBLIC => $isPublic,
            DBField::HOST_ID => $hostId,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
            DBField::DISPLAY_NAME => $displayName,
            DBField::GAME_ID => $gameId,
            DBField::GAME_MOD_ID => $gameModId,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var ActivationEntity $activation */
        $activation = $this->query($request->db)->createNewEntity($request, $activationData);

        return $activation;
    }

    /**
     * @param Request $request
     * @param bool $expand
     * @param int $page
     * @param int $perPage
     * @param null $startTime
     * @param null $endTime
     * @return ActivationEntity[]
     */
    public function getActivations(Request $request, $page = 1, $perPage = 40, $startTime = null, $endTime = null, $expand = true)
    {
        /** @var ActivationEntity[] $activations */
        $activations = $this->query($request->db)
            ->filter($this->filters->Gte(DBField::START_TIME, $startTime))
            ->filter($this->filters->Lt(DBField::END_TIME, $endTime))
            ->paging($page, $perPage)
            ->sort_desc(DBField::START_TIME)
            ->get_entities($request);

        if ($expand)
            $this->postProcessActivations($request, $activations);

        return $activations;
    }

    /**
     * @param Request $request
     * @param $activationId
     * @param bool $expand
     * @return ActivationEntity
     */
    public function getActivationById(Request $request, $activationId, $expand = true)
    {
        /** @var ActivationEntity $activation */
        $activation = $this->query($request->db)
            ->filter($this->filters->byPk($activationId))
            ->get_entity($request);

        if ($expand)
            $this->postProcessActivations($request, $activation);

        return $activation;
    }

    /**
     * @param Request $request
     * @param $activationIds
     * @param bool $expand
     * @return ActivationEntity[]
     */
    public function getActivationsByIds(Request $request, $activationIds, $expand = false)
    {
        /** @var ActivationEntity[] $activation */
        $activation = $this->query($request->db)
            ->filter($this->filters->byPk($activationIds))
            ->get_entities($request);


        $this->postProcessActivations($request, $activation, $expand);

        return $activation;
    }

    /**
     * @param Request $request
     * @param $activationGroupIds
     * @return ActivationEntity[]
     */
    public function getActivationsByActivationGroupIds(Request $request, $activationGroupIds, $expand = true)
    {
        /** @var ActivationEntity[] $activations */
        $activations = $this->query($request->db)
            ->filter($this->filters->byActivationGroupId($activationGroupIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        $this->postProcessActivations($request, $activations, $expand);

        return $activations;
    }

    /**
     * @param Request $request
     * @param $hostId
     * @param $activationTypeId
     * @param $timePoint
     * @param null $endTime
     * @return ActivationEntity[]
     */
    public function getLiveActivationsByTypeAndHostId(Request $request, $hostId, $activationTypeId, $timePoint)
    {
        $timeFilter = $this->filters->Or_(
            $this->filters->And_(
                $this->filters->Lte(DBField::START_TIME, $timePoint),
                $this->filters->Gt(DBField::END_TIME, $timePoint)
            ),
            $this->filters->And_(
                $this->filters->Gt(DBField::START_TIME, $timePoint),
                $this->filters->Gt(DBField::END_TIME, $timePoint)
            )
        );

        /** @var ActivationEntity[] $activation */
        $activations = $this->query($request->db)
            ->filter($this->filters->byHostId($hostId))
            ->filter($this->filters->byActivationStatusId(ActivationsStatusesManager::ID_LIVE))
            ->filter($this->filters->byActivationTypeId($activationTypeId))
            ->filter($timeFilter)
            ->get_entities($request);

        $this->postProcessActivations($request, $activations, false);

        return $activations;
    }

    /**
     * @param Request $request
     * @param $hostId
     * @return ActivationEntity
     */
    public function getLiveRunningCloudActivationForHost(Request $request, $hostId, $expand = true)
    {
        $currentTime = $request->getCurrentSqlTime();

        $timeRangeWhereFilter = $this->filters->And_(
            $this->filters->startTimeLte($currentTime),
            $this->filters->Or_(
                $this->filters->IsNull(DBField::END_TIME),
                $this->filters->endTimeGt($currentTime)
            )
        );

        /** @var ActivationEntity $activation */
        $activation = $this->query($request->db)
            ->filter($this->filters->byHostId($hostId))
            ->filter($this->filters->byActivationTypeId(ActivationsTypesManager::ID_CLOUD))
            ->filter($this->filters->byActivationStatusId(ActivationsStatusesManager::ID_LIVE))
            ->filter($this->filters->IsNotNull(DBField::GAME_ID))
            ->filter($this->filters->isActive())
            ->filter($timeRangeWhereFilter)
            ->get_entity($request);

        $this->postProcessActivations($request, $activation, $expand);

        return $activation;
    }

    /**
     * @param Request $request
     * @param bool $expand
     * @param string $offsetTime
     * @return ActivationEntity[]
     */
    public function getLiveRunningCloudActivationsForWan(Request $request, $expand = true, $offsetTime = "+2 minute")
    {
        $gamesManager = $request->managers->games();
        $joinGamesFilter = $this->filters->And_(
            $gamesManager->filters->byPk($this->field(DBField::GAME_ID)),
            $gamesManager->filters->byGameTypeId(GamesTypesManager::ID_CLOUD_GAME),
            $gamesManager->filters->isActive()
        );

        $dt = new DateTime();
        $dt->modify($offsetTime);

        $timeRangeWhereFilter = $this->filters->And_(
            $this->filters->startTimeLte($dt->format(SQL_DATETIME)),
            $this->filters->Or_(
                $this->filters->IsNull(DBField::END_TIME),
                $this->filters->endTimeGt($request->getCurrentSqlTime())
            )
        );

        /** @var ActivationEntity[] $activations */
        $activations = $this->query($request->db)
            ->inner_join($gamesManager, $joinGamesFilter)
            ->filter($this->filters->byActivationTypeId(ActivationsTypesManager::ID_CLOUD))
            ->filter($this->filters->byActivationStatusId(ActivationsStatusesManager::ID_LIVE))
            ->filter($this->filters->IsNotNull(DBField::GAME_ID))
            ->filter($this->filters->isActive())
            ->filter($timeRangeWhereFilter)
            ->sort_asc($this->field(DBField::START_TIME))
            ->get_entities($request);

        $this->postProcessActivations($request, $activations, $expand);

        return $activations;
    }


}

class ActivationsTypesManager extends BaseEntityManager
{
    const ID_CLOUD = 1;
    const ID_LOCATION_BASED = 2;

    protected $pk = DBField::ACTIVATION_TYPE_ID;
    protected $entityClass = ActivationTypeEntity::class;
    protected $table = Table::ActivationType;
    protected $table_alias = TableAlias::ActivationType;

    const GNS_KEY_PREFIX = GNS_ROOT.'.activation-types';

    /** @var ActivationTypeEntity[] */
    protected $activationTypes = [];

    public static $fields = [
        DBField::ACTIVATION_TYPE_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param ActivationTypeEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @return string
     */
    public function generatelAllCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return ActivationTypeEntity[]
     */
    public function getAllActivationTypes(Request $request)
    {
        if (!$this->activationTypes) {
            $activationTypes = $this->query($request->db)
                ->cache($this->generatelAllCacheKey(), ONE_WEEK)
                ->get_entities($request);
            $this->activationTypes = $this->index($activationTypes);
        }

        return $this->activationTypes;
    }

    /**
     * @param Request $request
     * @param $activationTypeId
     * @return ActivationTypeEntity|array
     */
    public function getActivationTypeById(Request $request, $activationTypeId)
    {
        return $this->getAllActivationTypes($request)[$activationTypeId] ?? [];
    }


}

class ActivationsStatusesManager extends BaseEntityManager
{
    const ID_DRAFT = 1;
    const ID_LIVE = 2;
    const ID_CLOSED = 3;

    protected $pk = DBField::ACTIVATION_STATUS_ID;
    protected $entityClass = ActivationStatusEntity::class;
    protected $table = Table::ActivationStatus;
    protected $table_alias = TableAlias::ActivationStatus;

    /** @var ActivationStatusEntity[] $activationStatuses */
    protected $activationStatuses = [];

    const GNS_KEY_PREFIX = GNS_ROOT.'.activation-statuses';

    public static $fields = [
        DBField::ACTIVATION_STATUS_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param ActivationStatusEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }

    /**
     * @return string
     */
    public function generatelAllCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return ActivationStatusEntity[]
     */
    public function getAllActivationStatuses(Request $request)
    {
        if (!$this->activationStatuses) {
            $activationStatuses = $this->query($request->db)
                ->cache($this->generatelAllCacheKey(), ONE_WEEK)
                ->get_entities($request);
            $this->activationStatuses = $this->index($activationStatuses);
        }

        return $this->activationStatuses;
    }

    /**
     * @param Request $request
     * @param $activationStatusId
     * @return ActivationStatusEntity|array
     */
    public function getActivationStatusById(Request $request, $activationStatusId)
    {
        return $this->getAllActivationStatuses($request)[$activationStatusId] ?? [];
    }

}

class ActivationsGroupsManager extends BaseEntityManager
{
    protected $entityClass = ActivationGroupEntity::class;
    protected $table = Table::ActivationGroup;
    protected $table_alias = TableAlias::ActivationGroup;
    protected $pk = DBField::ACTIVATION_GROUP_ID;

    public static $fields = [
        DBField::ACTIVATION_GROUP_ID,
        DBField::ORGANIZATION_ID,
        DBField::HOST_ID,
        DBField::START_TIME,
        DBField::END_TIME,
        DBField::DISPLAY_NAME,
        DBField::TIME_ZONE,
        DBField::SERVICE_ACCESS_TOKEN_INSTANCE_ID,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param ActivationGroupEntity $data
     * @param Request $request
     * @return DBManagerEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::ACTIVATIONS))
            $data->updateField(VField::ACTIVATIONS, []);

        if (!$data->hasField(VField::HOST))
            $data->updateField(VField::HOST, []);
    }

    /**
     * @param Request $request
     * @param ActivationGroupEntity|ActivationGroupEntity[] $activationGroups
     */
    public function postProcessActivationGroups(Request $request, $activationGroups, $expand = false)
    {
        $activationsManager = $request->managers->activations();
        $hostsManager = $request->managers->hosts();

        if ($activationGroups) {
            if ($activationGroups instanceof ActivationGroupEntity)
                $activationGroups = [$activationGroups];

            /** @var ActivationGroupEntity[] $activationGroups */
            $activationGroups = $this->index($activationGroups);
            $activationGroupIds = array_keys($activationGroups);

            $activations = $activationsManager->getActivationsByActivationGroupIds($request, $activationGroupIds, $expand);

            foreach ($activations as $activation) {
                $activationGroups[$activation->getActivationGroupId()]->setActivation($activation);
            }

            $hostIds = array_extract(DBField::HOST_ID, $activationGroups);
            $hosts = $hostsManager->getHostsByIds($request, $hostIds, true);
            $hosts = $hostsManager->index($hosts);

            foreach ($activationGroups as $activationGroup) {
                $activationGroup->setHost($hosts[$activationGroup->getHostId()]);
            }

        }
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param $hostId
     * @param $displayName
     * @param $startTime
     * @param null $endTime
     * @param null $serviceAccessTokenInstanceId
     * @return ActivationGroupEntity
     */
    public function createNewActivationGroup(Request $request, $organizationId, $hostId, $displayName, $startTime,
                                             $endTime = null, $serviceAccessTokenInstanceId = null, $timeZone = 'UTC')
    {
        $data = [
            DBField::ORGANIZATION_ID => $organizationId,
            DBField::HOST_ID => $hostId,
            DBField::DISPLAY_NAME => $displayName,
            DBField::TIME_ZONE => $timeZone,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
            DBField::SERVICE_ACCESS_TOKEN_INSTANCE_ID => $serviceAccessTokenInstanceId
        ];

        /** @var ActivationGroupEntity $activationGroup */
        $activationGroup = $this->query($request->db)->createNewEntity($request, $data);

        return $activationGroup;
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @return ActivationGroupEntity[]
     */
    public function getAllActivationGroupsByOrganizationId(Request $request, $organizationId, $expand = false)
    {
        /** @var ActivationGroupEntity[] $activationGroups */
        $activationGroups = $this->query($request->db)
            ->filter($this->filters->byOrganizationId($organizationId))
            ->filter($this->filters->isActive())
            ->sort_asc($this->field(DBField::START_TIME))
            ->get_entities($request);

        if ($expand)
            $this->postProcessActivationGroups($request, $activationGroups, $expand);

        return $activationGroups;
    }

    /**
     * @param Request $request
     * @param $hostId
     * @param bool $expand
     * @return ActivationGroupEntity[]
     */
    public function getAllActivationGroupsByHostId(Request $request, $hostId, $postProcess = true, $expand = false)
    {
        /** @var ActivationGroupEntity[] $activationGroups */
        $activationGroups = $this->query($request->db)
            ->filter($this->filters->byHostId($hostId))
            ->filter($this->filters->isActive())
            ->sort_asc($this->field(DBField::START_TIME))
            ->get_entities($request);

        if ($postProcess)
            $this->postProcessActivationGroups($request, $activationGroups, $expand);

        return $activationGroups;
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param int $page
     * @param int $perPage
     * @param bool $expand
     * @return ActivationGroupEntity[]
     */
    public function getActivationGroupsByOrganizationId(Request $request, $organizationId, $page = 1, $perPage = 40, $expand = false)
    {
        /** @var ActivationGroupEntity[] $activationGroups */
        $activationGroups = $this->query($request->db)
            ->filter($this->filters->byOrganizationId($organizationId))
            ->filter($this->filters->isActive())
            ->paging($page, $perPage)
            ->sort_asc($this->field(DBField::START_TIME))
            ->get_entities($request);

        if ($expand)
            $this->postProcessActivationGroups($request, $activationGroups, $expand);

        return $activationGroups;
    }


    /**
     * @param Request $request
     * @param $activationGroupId
     * @param null $organizationId
     * @param bool $expand
     * @return ActivationGroupEntity
     */
    public function getActivationGroupById(Request $request, $activationGroupId, $organizationId = null, $expand = true)
    {
        /** @var ActivationGroupEntity $activationGroup */
        $activationGroup = $this->query($request->db)
            ->filter($this->filters->byPk($activationGroupId))
            ->filter($this->filters->byOrganizationId($organizationId))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        if ($expand)
            $this->postProcessActivationGroups($request, $activationGroup, $expand);

        return $activationGroup;
    }
}