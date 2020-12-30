<?php

class SdkBuildEntity extends DBManagerEntity
{
    use
        hasSdkPlatformIdField,
        hasSdkVersionIdField,
        hasVersionHashField,
        hasBuildVersionField,
        hasIsActiveField,
        hasUserIdField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualUrlField,
        hasVirtualEditUrlField;


    /**
     * @param SdkBuildAssetEntity $sdkAsset
     */
    public function setSdkAsset(SdkBuildAssetEntity $sdkAsset)
    {
        $this->dataArray[VField::SDK_ASSETS][] = $sdkAsset;
    }

    /**
     * @return SdkBuildAssetEntity[]|array
     * @throws EntityFieldAccessException
     */
    public function getSdkAssets()
    {
        return $this->getVField(VField::SDK_ASSETS);
    }

    /**
     * @param SdkPlatformEntity $sdkPlatform
     */
    public function setSdkPlatform(SdkPlatformEntity $sdkPlatform)
    {
        $this->updateField(VField::SDK_PLATFORM, $sdkPlatform);
    }

    /**
     * @return SdkPlatformEntity|array
     * @throws EntityFieldAccessException
     */
    public function getSdkPlatform()
    {
        return $this->getVField(VField::SDK_PLATFORM);
    }

    /**
     * @return int
     * @throws EntityFieldAccessException
     */
    public function getSdkAssetsFileSize()
    {
        $fileSize = 0;
        foreach ($this->getSdkAssets() as $sdkAsset) {
            $fileSize = $fileSize + $sdkAsset->getFileSize();
        }

        return $fileSize;
    }

    /**
     * @return int
     * @throws EntityFieldAccessException
     */
    public function getSdkAssetCount()
    {
        return count($this->getSdkAssets());
    }

    /**
     * @return array|SdkBuildAssetEntity
     * @throws EntityFieldAccessException
     */
    public function getAutoUpdateZipFileAsset()
    {
        foreach ($this->getSdkAssets() as $sdkAsset) {
            if ($sdkAsset->getExtension() == 'zip' && $sdkAsset->getFolderPath() == 'sdk_archive/unity-sdk/') {
                return $sdkAsset;
            }
        }
        return [];
    }

    /**
     * @return UserEntity[]
     * @throws EntityFieldAccessException
     */
    public function getUser()
    {
        return $this->getVField(VField::USER);
    }

    /**
     * @param UserEntity $user
     */
    public function setUser(UserEntity $user)
    {
        $this->dataArray[VField::USER] = $user;
    }

    /**
     * @return SdkBuildAssetEntity[]
     * @throws EntityFieldAccessException
     */
    public function getControllerSdkAssets()
    {
        /** @var SdkBuildAssetEntity[] $controllerSdkAssets */
        $controllerSdkAssets = [];
        foreach ($this->getSdkAssets() as $sdkAsset) {
            if (($sdkAsset->getExtension() == 'js' || $sdkAsset->getExtension() == 'css')
                && $sdkAsset->getFolderPath() == 'sdk_archive/controller-sdk/') {
                $controllerSdkAssets[] = $sdkAsset;
            }
        }
        return $controllerSdkAssets;
    }

}

class SdkBuildActiveEntity extends DBManagerEntity
{
    use
        hasSdkBuildIdField,
        hasSdkPlatformIdField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param SdkBuildEntity $sdkBuild
     */
    public function setSdkBuild(SdkBuildEntity $sdkBuild)
    {
        $this->updateField(VField::SDK_BUILD, $sdkBuild);
    }

    /**
     * @return SdkBuildEntity
     * @throws EntityFieldAccessException
     */
    public function getSdkBuild()
    {
        return $this->field(VField::SDK_BUILD);
    }

    /**
     * @param SdkPlatformEntity $sdkPlatform
     */
    public function setSdkPlatform(SdkPlatformEntity $sdkPlatform)
    {
        $this->updateField(VField::SDK_PLATFORM, $sdkPlatform);
    }

    /**
     * @return SdkPlatformEntity
     * @throws EntityFieldAccessException
     */
    public function getSdkPlatform()
    {
        return $this->field(VField::SDK_PLATFORM);
    }
}

class SdkVersionEntity extends DBManagerEntity{
    use
        hasSdkVersionIdField,
        hasVersionField,
        hasUserIdField,
        hasSdkUpdateChannelField,
        hasIsDeprecatedField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualEditUrlField;

    /**
     * @param SdkBuildEntity $sdkBuild
     */
    public function setSdkBuild(SdkBuildEntity $sdkBuild)
    {
        $this->dataArray[VField::SDK_BUILDS][] = $sdkBuild;
    }

    /**
     * @return SdkBuildEntity[]
     * @throws EntityFieldAccessException
     */
    public function getSdkBuilds()
    {
        return $this->getVField(VField::SDK_BUILDS);
    }

    /**
     * @return UserEntity
     * @throws EntityFieldAccessException
     */
    public function getUser()
    {
        return $this->getVField(VField::USER);
    }

    /**
     * @param UserEntity $user
     */
    public function setUser(UserEntity $user)
    {
        $this->dataArray[VField::USER] = $user;
    }

};

class SdkVersionSdkPlatformChannelEntity extends DBManagerEntity {



}
