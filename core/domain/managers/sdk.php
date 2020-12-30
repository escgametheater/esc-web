<?php

Entities::uses('sdk');

class SdkPlatformsManager extends BaseEntityManager
{
    const ID_UNITY = 1;

    protected $entityClass = SdkPlatformEntity::class;
    protected $table = Table::SdkPlatform;
    protected $table_alias = TableAlias::SdkPlatform;
    protected $pk = DBField::SDK_PLATFORM_ID;

    public static $fields = [DBField::SDK_PLATFORM_ID, DBField::SLUG, DBField::DISPLAY_NAME, DBField::DISPLAY_ORDER, DBField::IS_ACTIVE, DBField::CREATED_BY, DBField::MODIFIED_BY, DBField::DELETED_BY];

    const GNS_KEY_PREFIX = GNS_ROOT . '.sdk-platforms';

    /**
     * @param DBManagerEntity $data
     * @param Request $request
     * @throws ESCConfigurationKeyNotSet
     * @throws EntityFieldAccessException
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $image_url = "{$request->getWwwUrl()}{$request->settings()->getImagesUrl()}entities/sdk-platform/{$data->getSlug()}.jpg";

        $data->updateField(VField::IMAGE_URL, $image_url);
    }

    /**
     * @return string
     */
    public function generateCacheKey()
    {
        return self::GNS_KEY_PREFIX . '.all';
    }

    /**
     * @param Request $request
     * @return SdkPlatformEntity[]
     */
    public function getAllSdkPlatforms(Request $request)
    {
        /** @var SdkPlatformEntity[] $sdkPlatforms */
        $sdkPlatforms = $this->query($request->db)->cache($this->generateCacheKey(), ONE_DAY)->get_entities($request);

        if ($sdkPlatforms) $sdkPlatforms = array_index($sdkPlatforms, $this->getPkField());

        return $sdkPlatforms;
    }

    /**
     * @param Request $request
     * @return SdkPlatformEntity[]
     */
    public function getAllActiveSdkPlatforms(Request $request)
    {
        /** @var SdkPlatformEntity[] $sdkPlatforms */
        $sdkPlatforms = [];

        foreach ($this->getAllSdkPlatforms($request) as $sdkPlatform) {
            if ($sdkPlatform->is_active()) $sdkPlatforms[] = $sdkPlatform;
        }

        return $sdkPlatforms;
    }

    /**
     * @param Request $request
     * @return SdkPlatformEntity[]
     * @throws EntityFieldAccessException
     */
    public function getAllActiveSdkPlatformSlugOptions(Request $request)
    {
        /** @var SdkPlatformEntity[] $sdkPlatforms */
        $sdkPlatforms = [];

        foreach ($this->getAllSdkPlatforms($request) as $sdkPlatform) {
            if ($sdkPlatform->is_active()) {

                $sdkPlatforms[] = [DBField::ID => $sdkPlatform->getSlug(), DBField::DISPLAY_NAME => $sdkPlatform->getDisplayName(),];


            }
        }

        return $sdkPlatforms;
    }

    /**
     * @param Request $request
     * @param $sdkPlatformId
     * @return SdkPlatformEntity
     */
    public function getSdkPlatformById(Request $request, $sdkPlatformId)
    {
        return $this->getAllSdkPlatforms($request)[$sdkPlatformId];
    }

    /**
     * @param Request $request
     * @param $sdkPlatformSlug
     * @return array|SdkPlatformEntity
     * @throws EntityFieldAccessException
     */
    public function getSdkPlatformBySlug(Request $request, $sdkPlatformSlug)
    {
        $sdkPlatforms = $this->getAllSdkPlatforms($request);

        foreach ($sdkPlatforms as $sdkPlatform) {
            if ($sdkPlatform->getSlug() == $sdkPlatformSlug) return $sdkPlatform;
        }
        return [];
    }
}


class SdkPlatformsVersionsManager extends BaseEntityManager
{
    protected $entityClass = SdkPlatformVersionEntity::class;
    protected $table = Table::SdkPlatformVersion;
    protected $table_alias = TableAlias::SdkPlatformVersion;
    protected $pk = DBField::SDK_PLATFORM_VERSION_ID;

    public static $fields = [DBField::SDK_PLATFORM_VERSION_ID, DBField::SDK_PLATFORM_ID, DBField::DISPLAY_NAME, DBField::DISPLAY_ORDER, DBField::IS_ACTIVE, DBField::CREATED_BY, DBField::MODIFIED_BY, DBField::DELETED_BY];
}
