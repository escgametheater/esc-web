<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 11/29/18
 * Time: 12:17 PM
 */

Entities::uses('sdk-assets');

class SdkAssetsManager extends BaseEntityManager
{
    protected $entityClass = SdkAssetEntity::class;
    protected $table = Table::SdkAsset;
    protected $table_alias = TableAlias::SdkAsset;
    protected $pk = DBField::SDK_ASSET_ID;


    protected $foreign_managers = [

    ];

    public static $fields = [
        DBField::SDK_ASSET_ID,
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
     * @param $sdkVersion
     * @return string
     */
    public function buildSdkVersionBucketPath($sdkVersion)
    {
        return "{$sdkVersion}/";
    }

    /**
     * @param SdkBuildAssetEntity $sdkAsset
     * @param Request $request
     * @return SdkBuildAssetEntity
     */
    public function processVFields(DBManagerEntity $sdkAsset, Request $request)
    {
        if ($sdkAsset instanceof SdkBuildAssetEntity) {
            $url = $request->getPlayUrl("/sdk-asset/{$sdkAsset->getSdkBuildId()}/{$sdkAsset->getFileName()}");
        } else {
            $url = '';
        }
        $sdkAsset->updateField(VField::URL, $url);

        return $sdkAsset;
    }

    /**
     * @param Request $request
     * @param $sdkVersionId
     * @param $sdkUpdateChannel
     * @param $md5
     * @param $mimeType
     * @param $bucket
     * @param $folderPath
     * @param $bucketPath
     * @param $fileName
     * @param $fileSize
     * @return SdkAssetEntity
     */
    public function createNewSdkAsset(Request $request,
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

        /** @var SdkAssetEntity $sdkAsset */
        $sdkAsset = $this->query($request->db)->createNewEntity($request, $data);

        $computedFileName = "{$sdkAsset->getPk()}_{$sdkAsset->getMd5()}";

        $sdkAsset->updateField(DBField::COMPUTED_FILENAME, $computedFileName)->saveEntityToDb($request);

        return $sdkAsset;
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
        $contextXSdkAssetsManager = $request->managers->contextXSdkAssets();

        $entityClass = $this->getEntityClass();
        $extraFields = [];

        switch ($contextEntityTypeId) {
            case EntityType::SDK_BUILD:
                $entityClass = SdkBuildAssetEntity::class;
                $extraFields = [
                    $contextXSdkAssetsManager->createAliasedPkField(VField::SDK_BUILD_ASSET_ID),
                    $contextXSdkAssetsManager->aliasField(DBField::CONTEXT_ENTITY_ID, DBField::SDK_BUILD_ID),
                    $contextXSdkAssetsManager->field(DBField::FILENAME),
                    $contextXSdkAssetsManager->field(DBField::FOLDER_PATH),
                    $contextXSdkAssetsManager->field(DBField::EXTENSION),
                ];
                break;
        }

        return $contextXSdkAssetsManager->query($request->db)
            ->filter($contextXSdkAssetsManager->filters->byContextEntityTypeId($contextEntityTypeId))
            ->fields($this->createDBFields())
            ->fields($extraFields)
            ->entity($entityClass)
            ->inner_join($this)
            ->mapper($this);
    }

    /**
     * @param Request $request
     * @param $sdkAssetId
     * @return SdkAssetEntity|array
     * @throws ObjectNotFound
     */
    public function getSdkAssetById(Request $request, $sdkAssetId)
    {
        return $this->query($request->db)
            ->filter($this->filters->isActive())
            ->filter($this->filters->byPk($sdkAssetId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $md5
     * @return array|SdkAssetEntity
     */
    public function getSdkAssetByMd5(Request $request, $md5)
    {
        return $this->query($request->db)
            ->filter($this->filters->byMd5($md5))
            ->get_entity($request);
    }


    /**
     * @param Request $request
     * @return SdkBuildAssetEntity[]
     */
    public function getSdkBuildAssetsBySdkBuildIds(Request $request, $sdkBuildId)
    {
        $contextXSdkAssetsManager = $request->managers->contextXSdkAssets();
        $sdkAssets = $this->contextQueryJoin($request, EntityType::SDK_BUILD)
            ->filter($contextXSdkAssetsManager->filters->byContextEntityId($sdkBuildId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        return $sdkAssets;
    }

    /**
     * @param Request $request
     * @param $sdkBuildAssetId
     * @return array|SdkBuildAssetEntity
     */
    public function getSdkBuildAssetBySdkBuildAssetId(Request $request, $sdkBuildAssetId)
    {
        $contextXSdkAssetsManager = $request->managers->contextXSdkAssets();

        return $this->contextQueryJoin($request, EntityType::SDK_BUILD)
            ->filter($contextXSdkAssetsManager->filters->isActive())
            ->filter($contextXSdkAssetsManager->filters->byPk($sdkBuildAssetId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $sourceFile
     * @param $md5
     * @param $bucket
     * @return array|SdkAssetEntity
     */
    protected function handleSdkAssetUpload(Request $request, $sourceFile, $md5, $sha512, $bucket)
    {
        $fileExists = false;

        if ($request->config['aws']['bucket_prefix'])
            $bucket = $request->config['aws']['bucket_prefix'].$bucket;

        if (!$sdkAsset = $this->getSdkAssetByMd5($request, $md5)) {

            $mimeType = mime_content_type($sourceFile);
            $fileSize = filesize($sourceFile);

            $sdkAsset = $this->createNewSdkAsset(
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

        if ($sdkAsset->getSha512() != $sha512) {
            $sdkAsset->updateField(DBField::SHA512, $sha512)->saveEntityToDb($request);
        }

        if (is_file($sourceFile)) {

            if (!$sdkAsset->is_active())
                $this->reActivateEntity($request, $sdkAsset);

            if (!$fileExists) {

                try {

                    $request->s3->uploadFile($sdkAsset->getBucket(), $sdkAsset->getBucketKey(), $sourceFile, $sdkAsset->getMimeType());

                } catch (\Aws\S3\Exception\S3Exception $e) {

                    $this->deactivateEntity($request, $sdkAsset);

                    throw $e;
                } catch (Exception $e) {
                    $this->deactivateEntity($request, $sdkAsset);

                    throw $e;
                }
            }

        }

        return $sdkAsset;
    }


    /**
     * @param Request $request
     * @param $sourceFile
     * @param $md5
     * @param $fileName
     * @param $sdkBuildId
     * @param $folderPath
     * @param null $uploadId
     * @return array|SdkBuildAssetEntity
     */
    public function handleSdkBuildAssetUpload(Request $request, $sourceFile, $md5, $sha512, $fileName, $sdkBuildId, $folderPath, $uploadId = null)
    {
        $contextXSdkAssetsManager = $request->managers->contextXSdkAssets();

        try {

            $sdkAsset = $this->handleSdkAssetUpload(
                $request,
                $sourceFile,
                $md5,
                $sha512,
                S3::BUCKET_SDK_ASSETS
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

        return $contextXSdkAssetsManager->linkSdkAssetToSdkBuild($request, $sdkAsset->getPk(), $sdkBuildId, $folderPath, $fileName);
    }
}


class ContextXSdkAssetsManager extends BaseEntityManager
{
    protected $entityClass = ContextXSdkAssetEntity::class;
    protected $table = Table::ContextXSdkAsset;
    protected $table_alias = TableAlias::ContextXGameAsset;
    protected $pk = DBField::CONTEXT_X_SDK_ASSET_ID;

    protected $foreign_managers = [
        SdkAssetsManager::class => DBField::SDK_ASSET_ID
    ];

    public static $fields = [
        DBField::CONTEXT_X_SDK_ASSET_ID,
        DBField::CONTEXT_ENTITY_TYPE_ID,
        DBField::CONTEXT_ENTITY_ID,
        DBField::SDK_ASSET_ID,
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
     * @param $sdkAssetId
     * @param $contextEntityTypeId
     * @param $contextEntityId
     * @return ContextXSdkAssetEntity
     */
    protected function linkSdkAssetToContextEntity(Request $request, $sdkAssetId, $contextEntityTypeId, $contextEntityId,
                                                    $folderPath, $fileName)
    {
        /** @var ContextXSdkAssetEntity $contextXSdkAssetLink */
        $contextXSdkAssetLink = $this->query($request->db)
            ->filter($this->filters->byContextEntityTypeId($contextEntityTypeId))
            ->filter($this->filters->byContextEntityId($contextEntityId))
            ->filter($this->filters->bySdkAssetId($sdkAssetId))
            ->get_entity($request);

        // If link does not exist at all
        if (!$contextXSdkAssetLink) {
            $data = [
                DBField::CONTEXT_ENTITY_TYPE_ID => $contextEntityTypeId,
                DBField::CONTEXT_ENTITY_ID => $contextEntityId,
                DBField::SDK_ASSET_ID => $sdkAssetId,
                DBField::FOLDER_PATH => $folderPath,
                DBField::FILENAME => $fileName,
                DBField::EXTENSION => FilesToolkit::get_file_extension($fileName),
                DBField::IS_ACTIVE => 1
            ];

            /** @var ContextXGameAssetEntity $contextXGameAsset */
            $contextXSdkAssetLink = $this->query($request->db)->createNewEntity($request, $data);

            // If the link exists but was previously deactivated
        } else if ($contextXSdkAssetLink && !$contextXSdkAssetLink->is_active()) {

            $this->reActivateEntity($request, $contextXSdkAssetLink);
            // Link exists and is active
        } else {
            // Do nothing
        }

        return $contextXSdkAssetLink;
    }


    /**
     * @param Request $request
     * @param $sdkAssetId
     * @param $sdkBuildId
     * @return array|SdkBuildAssetEntity
     */
    public function linkSdkAssetToSdkBuild(Request $request, $sdkAssetId, $sdkBuildId, $folderPath, $fileName)
    {
        $sdkAssetsManager = $request->managers->sdkAssets();

        $contextXSdkAssetLink = $this->linkSdkAssetToContextEntity(
            $request,
            $sdkAssetId,
            EntityType::SDK_BUILD,
            $sdkBuildId,
            $folderPath,
            $fileName
        );

        return $sdkAssetsManager->getSdkBuildAssetBySdkBuildAssetId($request, $contextXSdkAssetLink->getPk());
    }
}