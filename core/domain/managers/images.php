<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 10/29/18
 * Time: 2:36 PM
 */

Entities::uses('images');
class ImagesAssetsManager extends BaseEntityManager
{
    protected $entityClass = ImageAssetEntity::class;
    protected $table = Table::ImageAsset;
    protected $table_alias = TableAlias::ImageAsset;
    protected $pk = DBField::IMAGE_ASSET_ID;

    public static $fields = [
        DBField::IMAGE_ASSET_ID,
        DBField::MD5,
        DBField::WIDTH,
        DBField::HEIGHT,
        DBField::ASPECT_X,
        DBField::DPI_X,
        DBField::DPI_Y,
        DBField::MIME_TYPE,
        DBField::BUCKET,
        DBField::BUCKET_PATH,
        DBField::FILE_SIZE,
        DBField::COMPUTED_FILENAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param ImageAssetEntity $data
     * @param Request $request
     * @return ImageAssetEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        // Definition for post-processing is done in ImagesManager.
    }

    /**
     * @param $imageAssetId
     * @param $md5
     * @return string
     */
    public static function computeFileName($imageAssetId, $md5)
    {
        return "{$imageAssetId}_{$md5}";
    }


    /**
     * @param Request $request
     * @param $imageAssetId
     * @return ImageAssetEntity|array
     */
    public function getImageAssetById(Request $request, $imageAssetId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($imageAssetId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $md5
     * @return array|ImageAssetEntity
     */
    public function getImageAssetByMd5(Request $request, $md5)
    {
        return $this->query($request->db)
            ->filter($this->filters->byMd5($md5))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $md5
     * @param $width
     * @param $height
     * @param $dpiX
     * @param $dpiY
     * @param $mimeType
     * @param $bucket
     * @param $bucketPath
     * @param $fileSize
     * @return ImageAssetEntity
     */
    public function createNewImageAsset(Request $request, $md5, $width, $height, $dpiX, $dpiY, $mimeType, $bucket, $bucketPath, $fileSize)
    {
        $data = [
            DBField::MD5 => $md5,
            DBField::WIDTH => $width,
            DBField::HEIGHT => $height,
            DBField::DPI_X => $dpiX,
            DBField::DPI_Y => $dpiY,
            DBField::MIME_TYPE => $mimeType,
            DBField::BUCKET => $bucket,
            DBField::BUCKET_PATH => $bucketPath,
            DBField::COMPUTED_FILENAME => $md5,
            DBField::FILE_SIZE => $fileSize,
        ];

        /** @var ImageAssetEntity $imageAsset */
        $imageAsset = $this->query($request->db)->createNewEntity($request, $data);

        $imageAsset->updateField(DBField::COMPUTED_FILENAME, $this->computeFileName($imageAsset->getPk(), $md5))->saveEntityToDb($request);

        return $imageAsset;
    }
}

class ImagesManager extends BaseEntityManager
{
    protected $entityClass = ImageEntity::class;
    protected $table = Table::Image;
    protected $table_alias = TableAlias::Image;
    protected $pk = DBField::IMAGE_ID;

    public static $fields = [
        DBField::IMAGE_ID,
        DBField::IMAGE_TYPE_ID,
//        DBField::CONTEXT_ENTITY_TYPE_ID,
        DBField::CONTEXT_ENTITY_ID,
        DBField::IMAGE_ASSET_ID,
        DBField::SLUG,
        DBField::FILENAME,
        DBField::EXTENSION,
        DBField::CREATE_TIME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    protected $foreign_managers = [
        ImagesAssetsManager::class => DBField::IMAGE_ASSET_ID,
        ImagesTypesManager::class => DBField::IMAGE_TYPE_ID,
    ];

    /**
     * @param ImageEntity $image
     * @param ImageTypeEntity $imageType
     * @param ImageTypeSizeEntity $imageTypeSize
     * @return string
     */
    public function computePublicUri(ImageEntity $image, ImageTypeSizeEntity $imageTypeSize)
    {
        $computedFileName = ImagesAssetsManager::computeFileName($image->getPk(), $image->getMd5());

        return "/processed/{$image->getImageType()->getSlug()}/{$computedFileName}_{$imageTypeSize->getSlug()}?cb={$imageTypeSize->generateCacheBuster()}";
    }

    /**
     * @param ImageEntity $data
     * @param Request $request
     * @return ImageEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        // Resized Images
        foreach ($data->getImageType()->getImageTypeSizes() as $imageTypeSize) {
            $uri = $this->computePublicUri($data, $imageTypeSize);
            $url = $request->getImagesUrl($uri);
            $data->updateField($imageTypeSize->generateUrlField(), $url);
        }
    }

    /**
     * @param Request $request
     * @return SqlQuery
     */
    protected function queryJoinImageAssets(Request $request)
    {
        $imageAssetsManager = $request->managers->imagesAssets();
        $imagesTypesManager = $request->managers->imagesTypes();

        $fields = [
            $imageAssetsManager->field(DBField::MD5),
            $imageAssetsManager->field(DBField::WIDTH),
            $imageAssetsManager->field(DBField::HEIGHT),
            $imageAssetsManager->field(DBField::ASPECT_X),
            $imageAssetsManager->field(DBField::DPI_X),
            $imageAssetsManager->field(DBField::DPI_Y),
            $imageAssetsManager->field(DBField::MIME_TYPE),
            $imageAssetsManager->field(DBField::BUCKET),
            $imageAssetsManager->field(DBField::BUCKET_PATH),
            $imageAssetsManager->field(DBField::FILE_SIZE),
            $imageAssetsManager->field(DBField::COMPUTED_FILENAME),
        ];

        $queryBuilder = $this->query($request->db)
            ->fields($fields)
            ->fields($this->selectAliasedManagerFields($imagesTypesManager))
            ->inner_join($imageAssetsManager)
            ->inner_join($imagesTypesManager)
            ->filter($imageAssetsManager->filters->isActive());

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @param $contextEntityId
     * @param $imageTypeId
     * @param $imageAssetId
     * @return ImageEntity
     */
    public function getImageByAssetAndContext(Request $request, $imageTypeId, $contextEntityId, $imageAssetId)
    {
        /** @var ImageEntity $image */
        $image = $this->queryJoinImageAssets($request)
            ->filter($this->filters->byImageTypeId($imageTypeId))
            ->filter($this->filters->byContextEntityId($contextEntityId))
            ->filter($this->filters->byImageAssetId($imageAssetId))
            ->get_entity($request);

        return $image;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @return array|ImageEntity
     */
    public function getActiveGameAvatarImageByGameId(Request $request, $gameId)
    {
        return $this->queryJoinImageAssets($request)
            ->filter($this->filters->byImageTypeId(ImagesTypesManager::ID_GAME_AVATAR))
            ->filter($this->filters->byContextEntityId($gameId))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $gameIds
     * @return ImageEntity[]
     */
    public function getActiveGameAvatarImagesByGameIds(Request $request, $gameIds)
    {
        return $this->queryJoinImageAssets($request)
            ->filter($this->filters->byImageTypeId(ImagesTypesManager::ID_GAME_AVATAR))
            ->filter($this->filters->byContextEntityId($gameIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @return ImageEntity
     */
    public function getActiveOrganizationAvatarImageByOrganizationId(Request $request, $organizationId)
    {
        return $this->queryJoinImageAssets($request)
            ->filter($this->filters->byImageTypeId(ImagesTypesManager::ID_ORGANIZATION_AVATAR))
            ->filter($this->filters->byContextEntityId($organizationId))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $organizationIds
     * @return ImageEntity[]
     */
    public function getActiveOrganizationAvatarImagesByOrganizationIds(Request $request, $organizationIds)
    {
        return $this->queryJoinImageAssets($request)
            ->filter($this->filters->byImageTypeId(ImagesTypesManager::ID_ORGANIZATION_AVATAR))
            ->filter($this->filters->byContextEntityId($organizationIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $userId
     * @return array|ImageEntity
     */
    public function getActiveUserAvatarImageByUserId(Request $request, $userId)
    {
        return $this->queryJoinImageAssets($request)
            ->filter($this->filters->byImageTypeId(ImagesTypesManager::ID_USER_AVATAR))
            ->filter($this->filters->byContextEntityId($userId))
            ->filter($this->filters->isActive())
            ->get_entity($request);

    }

    /**
     * @param Request $request
     * @param $userId
     * @return ImageEntity[]
     */
    public function getActiveUserAvatarImagesByUserIds(Request $request, $userIds)
    {
        return $this->queryJoinImageAssets($request)
            ->filter($this->filters->byImageTypeId(ImagesTypesManager::ID_USER_AVATAR))
            ->filter($this->filters->byContextEntityId($userIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);
    }


    /**
     * @param Request $request
     * @param $activationId
     * @return ImageEntity|array
     */
    public function getActiveActivationAvatarImageByActivationId(Request $request, $activationId)
    {
        return $this->queryJoinImageAssets($request)
            ->filter($this->filters->byImageTypeId(ImagesTypesManager::ID_ACTIVATION_AVATAR))
            ->filter($this->filters->byContextEntityId($activationId))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $userId
     * @return ImageEntity[]
     */
    public function getActiveActivationAvatarImagesByActivationIds(Request $request, $activationIds)
    {
        return $this->queryJoinImageAssets($request)
            ->filter($this->filters->byImageTypeId(ImagesTypesManager::ID_ACTIVATION_AVATAR))
            ->filter($this->filters->byContextEntityId($activationIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);
    }


    /**
     * @param Request $request
     * @param $imageId
     * @param null|int $imageTypeId
     * @return array|ImageEntity
     */
    public function getImageById(Request $request, $imageId, $imageTypeId = null)
    {
        $queryBuilder = $this->queryJoinImageAssets($request)
            ->filter($this->filters->byPk($imageId))
            ->filter($this->filters->isActive());

        if ($imageTypeId)
            $queryBuilder->filter($this->filters->byImageTypeId($imageTypeId));

        return $queryBuilder->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $contextEntityTypeId
     * @param $imageAssetId
     * @param $imageTypeId
     * @param $fileName
     * @param null $slug
     * @return array|ImageEntity
     */
    public function createNewImage(Request $request, $imageTypeId, $contextEntityId, $imageAssetId, $fileName, $slug = null)
    {
        $extension = FilesToolkit::get_file_extension($fileName);

        $data = [
            DBField::CONTEXT_ENTITY_ID => $contextEntityId,
            DBField::IMAGE_ASSET_ID => $imageAssetId,
            DBField::IMAGE_TYPE_ID => $imageTypeId,
            DBField::SLUG => $slug,
            DBField::FILENAME => $fileName,
            DBField::EXTENSION => $extension,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::IS_ACTIVE => 1
        ];

        $imageId = $this->query($request->db)->add($data);

        return $this->getImageById($request, $imageId, $imageTypeId);
    }

}


class ImagesTypesManager extends BaseEntityManager
{
    protected $entityClass = ImageTypeEntity::class;
    protected $table = Table::ImageType;
    protected $table_alias = TableAlias::ImageType;
    protected $pk = DBField::IMAGE_TYPE_ID;

    const ID_GAME_AVATAR = 1;
    const ID_GAME_BANNER = 2;
    const ID_USER_AVATAR = 3;
    const ID_ORGANIZATION_AVATAR = 4;
    const ID_ACTIVATION_AVATAR = 5;
    const ID_HOST_AVATAR = 6;

    public static $fields = [
        DBField::IMAGE_TYPE_ID,
        DBField::SLUG,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param ImageTypeEntity $data
     * @param Request $request
     * @return ImageTypeEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $imagesTypesSizesManager = $request->managers->imagesTypesSizes();

        $imageTypeSizes = $imagesTypesSizesManager->getAllImageTypeSizesByImageTypeId($request, $data->getPk());

        if (!$data->hasField(VField::SIZES))
            $data->updateField(VField::SIZES, []);

        foreach ($imageTypeSizes as $imageTypeSize) {
            $data->setImageTypeSize($imageTypeSize);
        }

    }

    /**
     * @param Request $request
     * @param ImageTypeEntity[]|ImageTypeEntity $imageTypes
     */
    protected function postProcessImageTypes(Request $request, $imageTypes)
    {
        $imagesTypesSizesManager = $request->managers->imagesTypesSizes();

        if ($imageTypes) {
            if ($imageTypes instanceof ImageTypeEntity)
                $imageTypes = [$imageTypes];

            /** @var ImageTypeEntity[] $imageTypes */
            $imageTypes = array_index($imageTypes, $this->getPkField());
            $imageTypeIds = array_keys($imageTypes);

            $imageTypeSizes = $imagesTypesSizesManager->getAllImageTypeSizesByImageTypeIds($request, $imageTypeIds);
            foreach ($imageTypeSizes as $imageTypeSize) {
                $imageTypes[$imageTypeSize->getImageTypeId()]->setImageTypeSize($imageTypeSize);
            }
        }
    }

    /**
     * @param Request $request
     * @return ImageTypeEntity[]
     */
    public function getAllImageTypes(Request $request, $expand = false)
    {
        /** @var ImageTypeEntity[] $imageTypes */
        $imageTypes = $this->query($request->db)
            ->get_entities($request);

        //if ($expand)
            //$this->postProcessImageTypes($request, $imageTypes);

        return $imageTypes;
    }

    /**
     * @param Request $request
     * @param $imageTypeId
     * @param bool $expand
     * @return array|ImageTypeEntity
     */
    public function getImageTypeById(Request $request, $imageTypeId, $expand = true)
    {
        $imageType = $this->query($request->db)
            ->filter($this->filters->byPk($imageTypeId))
            ->get_entity($request);

        //if ($expand)
            //$this->postProcessImageTypes($request, $imageType);

        return $imageType;
    }

    /**
     * @param Request $request
     * @param $slug
     * @return ImageTypeEntity
     */
    public function getImageTypeBySlug(Request $request, $slug)
    {
        /** @var ImageTypeEntity $imageType */
        $imageType = $this->query($request->db)
            ->filter($this->filters->bySlug($slug))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        //$this->postProcessImageTypes($request, $imageType);

        return $imageType;
    }
}

class ImagesTypesSizesManager extends BaseEntityManager
{
    protected $entityClass = ImageTypeSizeEntity::class;
    protected $table = Table::ImageTypeSize;
    protected $table_alias = TableAlias::ImageTypeSize;
    protected $pk = DBField::IMAGE_TYPE_SIZE_ID;

    const GNS_KEY_PREFIX = GNS_ROOT.'.images';

    public static $fields = [
        DBField::IMAGE_TYPE_SIZE_ID,
        DBField::IMAGE_TYPE_ID,
        DBField::SLUG,
        DBField::DISPLAY_NAME,
        DBField::WIDTH,
        DBField::HEIGHT,
        DBField::QUALITY,
        DBField::MIME_TYPE,
        DBField::EXTENSION,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param $imageTypeId
     * @return string
     */
    public function generateCacheKeyForImageTypeId($imageTypeId)
    {
        $prefix = self::GNS_KEY_PREFIX;

        return "{$prefix}.types.{$imageTypeId}.sizes";
    }

    /**
     * @param Request $request
     * @param $imageTypeId
     * @return ImageTypeSizeEntity[]
     */
    public function getAllImageTypeSizesByImageTypeId(Request $request, $imageTypeId)
    {
        return $this->query($request->db)
            ->cache($this->generateCacheKeyForImageTypeId($imageTypeId), ONE_WEEK)
            ->filter($this->filters->byImageTypeId($imageTypeId))
            ->filter($this->filters->isActive())
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param array $imageTypeIds
     * @return array|ImageTypeSizeEntity[]
     */
    public function getAllImageTypeSizesByImageTypeIds(Request $request, array $imageTypeIds = [])
    {
        if ($imageTypeIds) {

            /** @var ImageTypeSizeEntity[] $allImageTypeSizes */
            $allImageTypeSizes = [];

            foreach ($imageTypeIds as $imageTypeId) {
                $imageTypeSizes = $this->getAllImageTypeSizesByImageTypeId($request, $imageTypeId);
                $allImageTypeSizes = array_merge($allImageTypeSizes, $imageTypeSizes);
            }

            return $allImageTypeSizes;

        } else {
            return [];
        }
    }
}


