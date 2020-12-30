<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 11/29/18
 * Time: 12:17 PM
 */

Entities::uses('hosts-assets');

class HostAssetsManager extends BaseEntityManager
{
    protected $entityClass = HostAssetEntity::class;
    protected $table = Table::HostAsset;
    protected $table_alias = TableAlias::HostAsset;
    protected $pk = DBField::HOST_ASSET_ID;


    protected $foreign_managers = [

    ];

    public static $fields = [
        DBField::HOST_ASSET_ID,
        DBField::MD5,
        DBField::SHA512,
        DBField::MIME_TYPE,
        DBField::BUCKET,
        DBField::BUCKET_PATH,
        DBField::COMPUTED_FILENAME,
        DBField::FILE_SIZE,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    public $removed_json_fields = [
        DBField::BUCKET_PATH,
        DBField::BUCKET,
    ];

    /**
     * @param $hostVersion
     * @return string
     */
    public function buildHostVersionBucketPath($hostVersion)
    {
        return "{$hostVersion}/";
    }

    /**
     * @param HostAssetEntity $hostAsset
     * @param Request $request
     * @return HostAssetEntity
     */
    public function processVFields(DBManagerEntity $hostAsset, Request $request)
    {
        $hostAsset[VField::URL] = $request->getApiUrl("/v1/host-assets/download/{$hostAsset->getPk()}/");

        return $hostAsset;
    }

    /**
     * @param Request $request
     * @param $hostVersionId
     * @param $hostUpdateChannel
     * @param $md5
     * @param $mimeType
     * @param $bucket
     * @param $folderPath
     * @param $bucketPath
     * @param $fileName
     * @param $fileSize
     * @return HostAssetEntity
     */
    public function createNewHostAsset(Request $request,
                                       $md5,
                                       $sha512,
                                       $mimeType,
                                       $bucket,
                                       $bucketPath,
                                       $fileSize)
    {
        $data = [
            DBField::MD5 => $md5,
            DBField::SHA512 => $sha512,
            DBField::MIME_TYPE => $mimeType,
            DBField::BUCKET => $bucket,
            DBField::BUCKET_PATH => $bucketPath,
            DBField::COMPUTED_FILENAME => '',
            DBField::FILE_SIZE => $fileSize
        ];

        /** @var HostAssetEntity $hostAsset */
        $hostAsset = $this->query($request->db)->createNewEntity($request, $data);

        $computedFileName = "{$hostAsset->getPk()}_{$hostAsset->getMd5()}";

        $hostAsset->updateField(DBField::COMPUTED_FILENAME, $computedFileName)->saveEntityToDb($request);

        return $hostAsset;
    }

    /**
     * @param Request $request
     * @param $contextEntityTypeId
     * @param $contextEntityId
     * @param null $contextXGameAssetId
     * @return SqlQuery
     */
    protected function contextQueryJoin(Request $request, $contextEntityTypeId)
    {
        $contextXHostAssetsManager = $request->managers->contextXHostAssets();

        $entityClass = $this->getEntityClass();
        $extraFields = [];

        switch ($contextEntityTypeId) {
            case EntityType::HOST_BUILD:
                $entityClass = HostBuildAssetEntity::class;
                $extraFields = [
                    $contextXHostAssetsManager->createAliasedPkField(VField::HOST_BUILD_ASSET_ID),
                    $contextXHostAssetsManager->aliasField(DBField::CONTEXT_ENTITY_ID, DBField::HOST_BUILD_ID),
                    $contextXHostAssetsManager->field(DBField::FILENAME),
                    $contextXHostAssetsManager->field(DBField::FOLDER_PATH),
                    $contextXHostAssetsManager->field(DBField::EXTENSION),
                ];
                break;
            case EntityType::HOST_CONTROLLER:
                $entityClass = HostControllerAssetEntity::class;
                $extraFields = [
                    $contextXHostAssetsManager->createAliasedPkField(VField::HOST_CONTROLLER_ASSET_ID),
                    $contextXHostAssetsManager->aliasField(DBField::CONTEXT_ENTITY_ID, DBField::HOST_CONTROLLER_ID),
                    $contextXHostAssetsManager->field(DBField::FILENAME),
                    $contextXHostAssetsManager->field(DBField::FOLDER_PATH),
                    $contextXHostAssetsManager->field(DBField::EXTENSION),
                ];
                break;
        }

        return $contextXHostAssetsManager->query($request->db)
            ->filter($contextXHostAssetsManager->filters->byContextEntityTypeId($contextEntityTypeId))
            ->fields($this->createDBFields())
            ->fields($extraFields)
            ->entity($entityClass)
            ->inner_join($this)
            ->mapper($this);
    }

    /**
     * @param Request $request
     * @param $hostAssetId
     * @return HostAssetEntity|array
     * @throws ObjectNotFound
     */
    public function getHostAssetById(Request $request, $hostAssetId)
    {
        return $this->query($request->db)
            ->filter($this->filters->isActive())
            ->filter($this->filters->byPk($hostAssetId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $md5
     * @return array|HostAssetEntity
     */
    public function getHostAssetByMd5(Request $request, $md5)
    {
        return $this->query($request->db)
            ->filter($this->filters->byMd5($md5))
            ->get_entity($request);
    }


    /**
     * @param Request $request
     * @return HostBuildAssetEntity[]
     */
    public function getHostBuildAssetsByHostBuildIds(Request $request, $hostBuildId)
    {
        $contextXHostAssetsManager = $request->managers->contextXHostAssets();
        $hostAssets = $this->contextQueryJoin($request, EntityType::HOST_BUILD)
            ->filter($contextXHostAssetsManager->filters->byContextEntityId($hostBuildId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        return $hostAssets;
    }

    /**
     * @param Request $request
     * @param $hostControllerIds
     * @return HostControllerAssetEntity[]
     */
    public function getHostControllerAssetsByHostControllerIds(Request $request, $hostControllerIds)
    {
        $contextXHostAssetsManager = $request->managers->contextXHostAssets();
        $hostControllerAssets = $this->contextQueryJoin($request, EntityType::HOST_CONTROLLER)
            ->filter($contextXHostAssetsManager->filters->byContextEntityId($hostControllerIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        return $hostControllerAssets;
    }


    /**
     * @param Request $request
     * @param $hostBuildAssetId
     * @return array|HostBuildAssetEntity
     */
    public function getHostBuildAssetByHostBuildAssetId(Request $request, $hostBuildAssetId)
    {
        $contextXHostAssetsManager = $request->managers->contextXHostAssets();

        return $this->contextQueryJoin($request, EntityType::HOST_BUILD)
            ->filter($contextXHostAssetsManager->filters->isActive())
            ->filter($contextXHostAssetsManager->filters->byPk($hostBuildAssetId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $hostControllerAssetId
     * @return array|HostControllerAssetEntity
     */
    public function getHostControllerAssetByHostControllerAssetId(Request $request, $hostControllerAssetId)
    {
        $contextXHostAssetsManager = $request->managers->contextXHostAssets();

        return $this->contextQueryJoin($request, EntityType::HOST_CONTROLLER)
            ->filter($contextXHostAssetsManager->filters->isActive())
            ->filter($contextXHostAssetsManager->filters->byPk($hostControllerAssetId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $sourceFile
     * @param $md5
     * @param $bucket
     * @return array|HostAssetEntity
     */
    protected function handleHostAssetUpload(Request $request, $sourceFile, $md5, $sha512, $bucket)
    {
        $fileExists = false;

        if ($request->config['aws']['bucket_prefix'])
            $bucket = $request->config['aws']['bucket_prefix'].$bucket;

        if (!$hostAsset = $this->getHostAssetByMd5($request, $md5)) {

            $mimeType = mime_content_type($sourceFile);
            $fileSize = filesize($sourceFile);

            $hostAsset = $this->createNewHostAsset(
                $request,
                $md5,
                $sha512,
                $mimeType,
                $bucket,
                '',
                $fileSize
            );
        } else {
            $fileExists = true;
        }

        if ($hostAsset->getSha512() != $sha512) {
            $hostAsset->updateField(DBField::SHA512, $sha512)->saveEntityToDb($request);
        }

        if (is_file($sourceFile)) {

            if (!$hostAsset->is_active())
                $this->reActivateEntity($request, $hostAsset);

            if (!$fileExists) {

                try {

                    $request->s3->uploadFile($hostAsset->getBucket(), $hostAsset->getBucketKey(), $sourceFile, $hostAsset->getMimeType());

                } catch (\Aws\S3\Exception\S3Exception $e) {

                    $this->deactivateEntity($request, $hostAsset);

                    throw $e;
                } catch (Exception $e) {
                    $this->deactivateEntity($request, $hostAsset);

                    throw $e;
                }
            }

        }

        return $hostAsset;
    }


    /**
     * @param Request $request
     * @param $sourceFile
     * @param $md5
     * @param $fileName
     * @param $hostBuildId
     * @param $folderPath
     * @param null $uploadId
     * @return array|HostBuildAssetEntity
     */
    public function handleHostBuildAssetUpload(Request $request, $sourceFile, $md5, $sha512, $fileName, $hostBuildId, $folderPath, $uploadId = null)
    {
        $contextXHostAssetsManager = $request->managers->contextXHostAssets();

        try {

            $hostAsset = $this->handleHostAssetUpload(
                $request,
                $sourceFile,
                $md5,
                $sha512,
                S3::BUCKET_HOST_ASSETS
            );

        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($uploadId)
                UploadsHelper::delete_upload($uploadId);
            throw $e;
        } catch (Exception $e) {

            if ($uploadId)
                UploadsHelper::delete_upload($uploadId);

            throw $e;
        }

        if ($uploadId)
            UploadsHelper::delete_upload($uploadId);

        return $contextXHostAssetsManager->linkHostAssetToHostBuild($request, $hostAsset->getPk(), $hostBuildId, $folderPath, $fileName);
    }

    /**
     * @param Request $request
     * @param $sourceFile
     * @param $md5
     * @param $fileName
     * @param $hostControllerId
     * @param $folderPath
     * @param null $uploadId
     * @return array|HostControllerAssetEntity
     */
    public function handleHostControllerAssetUpload(Request $request, $sourceFile, $md5, $sha512, $fileName, $hostControllerId, $folderPath, $uploadId = null)
    {
        $contextXHostAssetsManager = $request->managers->contextXHostAssets();

        try {
            $hostAsset = $this->handleHostAssetUpload(
                $request,
                $sourceFile,
                $md5,
                $sha512,
                S3::BUCKET_HOST_CONTROLLER_ASSETS
            );

        } catch (\Aws\S3\Exception\S3Exception $e) {
            if ($uploadId)
                UploadsHelper::delete_upload($uploadId);
            throw $e;
        } catch (Exception $e) {

            if ($uploadId)
                UploadsHelper::delete_upload($uploadId);

            throw $e;
        }

        if ($uploadId)
            UploadsHelper::delete_upload($uploadId);

        return $contextXHostAssetsManager->linkHostAssetToHostController($request, $hostAsset->getPk(), $hostControllerId, $folderPath, $fileName);
    }

}


class HostsControllersManager extends BaseEntityManager
{
    protected $entityClass = HostControllerEntity::class;
    protected $table = Table::HostController;
    protected $table_alias = TableAlias::HostController;
    protected $pk = DBField::HOST_CONTROLLER_ID;

    const ZIP_ROOT_FILE_IDENTIFIER = "index.html";

    public $foreign_managers = [

    ];

    public static $fields = [
        DBField::HOST_CONTROLLER_ID,
        DBField::HOST_VERSION_ID,
        DBField::VERSION,
        DBField::USER_ID,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @return string
     */
    public function getZipRootFileIdentifier()
    {
        return self::ZIP_ROOT_FILE_IDENTIFIER;
    }

    /**
     * @return string
     */
    public function generateZipDestinationPath()
    {
        global $CONFIG;

        $mediaDir = $CONFIG[ESCConfiguration::DIR_MEDIA];

        $uniqueDirName = uuidV4HostName();

        return "{$mediaDir}/host-app/controller-{$uniqueDirName}/";
    }


    /**
     * @param HostControllerEntity $data
     * @param Request $request
     * @return HostControllerEntity
     */
    public function processVFields(DBManagerEntity $hostController, Request $request)
    {
        $editUrl = $request->getWwwUrl("/admin/host-app/view-controller/{$hostController->getPk()}");
        $hostController->updateField(VField::EDIT_URL, $editUrl);

        if (!$hostController->hasField(VField::HOST_ASSETS))
            $hostController->updateField(VField::HOST_ASSETS, []);

        if (!$hostController->hasField(VField::USER))
            $hostController->updateField(VField::USER, []);
    }

    /**
     * @param Request $request
     * @param $version
     * @return HostControllerEntity
     */
    public function createNewHostController(Request $request, $hostVersionId, $version)
    {
        $hostControllerData = [
            DBField::HOST_VERSION_ID => $hostVersionId,
            DBField::VERSION => $version,
            DBField::USER_ID => $request->user->id,
            DBField::IS_ACTIVE => 1
        ];

        $hostController = $this->query($request->db)->createNewEntity($request, $hostControllerData);

        return $hostController;
    }

    /**
     * @param Request $request
     * @param array $games
     * @return FormField[]
     */
    public function getFormFields(Request $request)
    {
        return [
            new CharField(DBField::VERSION, 'Host Controller Version Number', true)
        ];
    }

    /**
     * @param Request $request
     * @param HostControllerEntity[] $hostControllers
     */
    public function postProcessHostControllers(Request $request, $hostControllers = [])
    {
        $hostAssetsManager = $request->managers->hostAssets();
        $usersManager = $request->managers->users();

        if ($hostControllers) {

            if ($hostControllers instanceof HostControllerEntity)
                $hostControllers = [$hostControllers];

            /** @var HostControllerEntity[] $hostControllers */
            $hostControllers = array_index($hostControllers, $this->getPkField());
            $hostControllerIds = array_keys($hostControllers);

            $userIds = unique_array_extract(DBField::USER_ID, $hostControllers);
            $users = $usersManager->getUsersByIds($request, $userIds);
            /** @var UserEntity[] $users */
            $users = array_index($users, $usersManager->getPkField());

            foreach ($hostControllers as $hostController) {
                if (isset($users[$hostController->getUserId()]))
                    $hostController->setUser($users[$hostController->getUserId()]);
            }

            $hostControllerAssets = $hostAssetsManager->getHostControllerAssetsByHostControllerIds($request, $hostControllerIds);

            foreach ($hostControllerAssets as $hostControllerAsset) {
                $hostControllers[$hostControllerAsset->getHostControllerId()]->setHostControllerAsset($hostControllerAsset);
            }
        }
    }

    /**
     * @param Request $request
     * @param $hostControllerId
     * @param bool $expand
     * @return HostControllerEntity
     */
    public function getHostControllerById(Request $request, $hostControllerId, $expand = true)
    {
        $queryBuilder = $this->query($request->db)
            ->filter($this->filters->byPk($hostControllerId));

        /** @var HostControllerEntity $hostController */
        $hostController = $queryBuilder->get_entity($request);

        if ($expand)
            $this->postProcessHostControllers($request, $hostController);

        return $hostController;
    }

    /**
     * @param Request $request
     * @param $hostControllerId
     * @param bool $expand
     * @return HostControllerEntity[]
     */
    public function getHostControllersByHostVersionIds(Request $request, $hostVersionIds, $expand = true)
    {
        /** @var HostControllerEntity[] $hostController */
        $hostControllers = $this->query($request->db)
            ->filter($this->filters->byHostVersionId($hostVersionIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessHostControllers($request, $hostControllers);

        return $hostControllers;
    }

    /**
     * @param Request $request
     * @param $hostVersionId
     * @param $gameControllerTypeId
     */
    public function deactivateOldHostControllersForAppVersion(Request $request, $hostVersionId, $newHostControllerId)
    {
        $updatedData = [
            DBField::DELETED_BY => $request->requestId,
            DBField::MODIFIED_BY => $request->requestId,
            DBField::IS_ACTIVE => 0
        ];

        $this->query($request->db)
            ->filter($this->filters->byHostVersionId($hostVersionId))
            ->filter($this->filters->NotEq($this->getPkField(), $newHostControllerId))
            ->update($updatedData);
    }
}

class ContextXHostAssetsManager extends BaseEntityManager
{
    protected $entityClass = ContextXHostAssetEntity::class;
    protected $table = Table::ContextXHostAsset;
    protected $table_alias = TableAlias::ContextXGameAsset;
    protected $pk = DBField::CONTEXT_X_HOST_ASSET_ID;

    protected $foreign_managers = [
        HostAssetsManager::class => DBField::HOST_ASSET_ID
    ];

    public static $fields = [
        DBField::CONTEXT_X_HOST_ASSET_ID,
        DBField::CONTEXT_ENTITY_TYPE_ID,
        DBField::CONTEXT_ENTITY_ID,
        DBField::HOST_ASSET_ID,
        DBField::FOLDER_PATH,
        DBField::FILENAME,
        DBField::EXTENSION,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];


    /**
     * @param Request $request
     * @param $hostAssetId
     * @param $contextEntityTypeId
     * @param $contextEntityId
     * @return ContextXHostAssetEntity
     */
    protected function linkHostAssetToContextEntity(Request $request, $hostAssetId, $contextEntityTypeId, $contextEntityId,
                                                    $folderPath, $fileName)
    {
        /** @var ContextXHostAssetEntity $contextXHostAssetLink */
        $contextXHostAssetLink = $this->query($request->db)
            ->filter($this->filters->byContextEntityTypeId($contextEntityTypeId))
            ->filter($this->filters->byContextEntityId($contextEntityId))
            ->filter($this->filters->byHostAssetId($hostAssetId))
            ->get_entity($request);

        // If link does not exist at all
        if (!$contextXHostAssetLink) {
            $data = [
                DBField::CONTEXT_ENTITY_TYPE_ID => $contextEntityTypeId,
                DBField::CONTEXT_ENTITY_ID => $contextEntityId,
                DBField::HOST_ASSET_ID => $hostAssetId,
                DBField::FOLDER_PATH => $folderPath,
                DBField::FILENAME => $fileName,
                DBField::EXTENSION => FilesToolkit::get_file_extension($fileName),
                DBField::IS_ACTIVE => 1
            ];

            /** @var ContextXGameAssetEntity $contextXGameAsset */
            $contextXHostAssetLink = $this->query($request->db)->createNewEntity($request, $data);

            // If the link exists but was previously deactivated
        } else if ($contextXHostAssetLink && !$contextXHostAssetLink->is_active()) {

            $this->reActivateEntity($request, $contextXHostAssetLink);
            // Link exists and is active
        } else {
            // Do nothing
        }

        return $contextXHostAssetLink;
    }


    /**
     * @param Request $request
     * @param $hostAssetId
     * @param $hostBuildId
     * @return array|HostBuildAssetEntity
     */
    public function linkHostAssetToHostBuild(Request $request, $hostAssetId, $hostBuildId, $folderPath, $fileName)
    {
        $hostAssetsManager = $request->managers->hostAssets();

        $contextXHostAssetLink = $this->linkHostAssetToContextEntity(
            $request,
            $hostAssetId,
            EntityType::HOST_BUILD,
            $hostBuildId,
            $folderPath,
            $fileName
        );

        return $hostAssetsManager->getHostBuildAssetByHostBuildAssetId($request, $contextXHostAssetLink->getPk());
    }

    /**
     * @param Request $request
     * @param $hostAssetId
     * @param $hostControllerId
     * @return array|HostControllerAssetEntity
     */
    public function linkHostAssetToHostController(Request $request, $hostAssetId, $hostControllerId, $folderPath, $fileName)
    {
        $hostAssetsManager = $request->managers->hostAssets();

        $contextXHostAssetLink = $this->linkHostAssetToContextEntity(
            $request,
            $hostAssetId,
            EntityType::HOST_CONTROLLER,
            $hostControllerId,
            $folderPath,
            $fileName
        );

        return $hostAssetsManager->getHostControllerAssetByHostControllerAssetId($request, $contextXHostAssetLink->getPk());
    }
}