<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 11/29/18
 * Time: 12:19 PM
 */


class HostBuildEntity extends DBManagerEntity
{
    use
        hasPlatformIdField,
        hasHostVersionIdField,
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
     * @param HostBuildAssetEntity $hostAsset
     */
    public function setHostAsset(HostBuildAssetEntity $hostAsset)
    {
        $this->dataArray[VField::HOST_ASSETS][] = $hostAsset;
    }

    /**
     * @return HostBuildAssetEntity[]|array
     */
    public function getHostAssets()
    {
        return $this->getVField(VField::HOST_ASSETS);
    }

    /**
     * @param PlatformEntity $platform
     */
    public function setPlatform(PlatformEntity $platform)
    {
        $this->updateField(VField::PLATFORM, $platform);
    }

    /**
     * @return PlatformEntity|array
     */
    public function getPlatform()
    {
        return $this->getVField(VField::PLATFORM);
    }

    /**
     * @return int
     */
    public function getHostAssetsFileSize()
    {
        $fileSize = 0;
        foreach ($this->getHostAssets() as $hostAsset) {
            $fileSize = $fileSize + $hostAsset->getFileSize();
        }

        return $fileSize;
    }

    /**
     * @return int
     */
    public function getHostAssetCount()
    {
        return count($this->getHostAssets());
    }

    /**
     * @return array|HostBuildAssetEntity
     */
    public function getAutoUpdateZipFileAsset()
    {
        foreach ($this->getHostAssets() as $hostAsset) {
            if ($hostAsset->getExtension() == 'zip') {
                return $hostAsset;
            }
        }
        return [];
    }

    /**
     * @param array $types
     * @return array|HostBuildAssetEntity
     */
    public function getInstallerFileAsset($types = ['exe', 'dmg'])
    {
        foreach ($this->getHostAssets() as $hostAsset) {
            if (in_array($hostAsset->getExtension(), $types))
                return $hostAsset;
        }
        return [];
    }

    /**
     * @return UserEntity[]
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

}

class HostBuildActiveEntity extends DBManagerEntity
{
    use
        hasHostBuildIdField,
        hasPlatformIdField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param HostBuildEntity $hostBuild
     */
    public function setHostBuild(HostBuildEntity $hostBuild)
    {
        $this->updateField(VField::HOST_BUILD, $hostBuild);
    }

    /**
     * @return HostBuildEntity
     */
    public function getHostBuild()
    {
        return $this->field(VField::HOST_BUILD);
    }

    /**
     * @param PlatformEntity $platform
     */
    public function setPlatform(PlatformEntity $platform)
    {
        $this->updateField(VField::PLATFORM, $platform);
    }

    /**
     * @return PlatformEntity
     */
    public function getPlatform()
    {
        return $this->field(VField::PLATFORM);
    }
}

class HostControllerEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasHostVersionIdField,
        hasMinHostVersionIdField,
        hasVersionField,
        hasUserIdField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualUrlField,
        hasVirtualEditUrlField;



    /*
     * We are storing the reference to the game assets in a protected property as well as in the natural data array
     * so we can get game assets by ID specifically if needed. This is because when we deserialize the data array,
     * if the assets are stored in an indexed array, that becomes a JSON Object instead of an array of json objects.
     */

    /** @var HostControllerAssetEntity[] $hostControllerAssets  */
    protected $hostControllerAssets = [];

    /** @var HostControllerAssetEntity */
    protected $manifestJsonAsset;
    /** @var HostControllerAssetEntity */
    protected $assetManifestJsonAsset;
    /** @var HostControllerAssetEntity */
    protected $serviceWorkerJsonAsset;
    /** @var HostControllerAssetEntity */
    protected $cssAsset;
    /** @var HostControllerAssetEntity */
    protected $cssMapAsset;
    /** @var HostControllerAssetEntity */
    protected $bundleJsAsset;
    /** @var HostControllerAssetEntity */
    protected $bundleJsMapAsset;
    /** @var HostControllerAssetEntity[] */
    protected $imageAssets = [];

    /** @var HostControllerAssetEntity[]  */
    protected $assetsByPath = [];

    /**
     * @param HostControllerAssetEntity $hostAsset
     */
    public function setHostControllerAsset(HostControllerAssetEntity $hostAsset)
    {
        $this->dataArray[VField::HOST_ASSETS][] = $hostAsset;
        $this->hostControllerAssets[$hostAsset->getPk()] = $hostAsset;

        if ($hostAsset->getFileName() == "bundle.js")
            $this->bundleJsAsset = $hostAsset;
        elseif ($hostAsset->getFileName() == "bundle.js.map")
            $this->bundleJsMapAsset = $hostAsset;
        elseif ($hostAsset->getFileName() == "manifest.json")
            $this->manifestJsonAsset = $hostAsset;
        elseif ($hostAsset->getFileName() == "service-worker.js")
            $this->serviceWorkerJsonAsset = $hostAsset;
        elseif ($hostAsset->getFileName() == "asset-manifest.json")
            $this->assetManifestJsonAsset = $hostAsset;
        elseif ($hostAsset->getFileName() == "main.css")
            $this->cssAsset = $hostAsset;
        elseif ($hostAsset->getFileName() == "main.css.map")
            $this->cssMapAsset = $hostAsset;
        elseif ($hostAsset->getFolderPath() == "images/")
            $this->imageAssets[] = $hostAsset;

        $this->assetsByPath["{$hostAsset->getFolderPath()}{$hostAsset->getFileName()}"] = $hostAsset;
    }

    /**
     * @param $path
     * @return HostControllerAssetEntity|null
     */
    public function getHostAssetByPath($path)
    {
        if (array_key_exists($path, $this->assetsByPath))
            return $this->assetsByPath[$path];
        return null;
    }

    /**
     * @return HostControllerAssetEntity[]
     */
    public function getHostControllerAssets()
    {
        return $this->field(VField::HOST_ASSETS);
    }

    /**
     * @return int
     */
    public function getHostAssetsFileSize()
    {
        $fileSize = 0;

        foreach ($this->getHostControllerAssets() as $hostAsset) {
            $fileSize = $fileSize + $hostAsset->getFileSize();
        }

        return $fileSize;
    }

    /**
     * @return int
     */
    public function getHostAssetCount()
    {
        if ($hostControllerAssets = $this->getHostControllerAssets())
            return count($hostControllerAssets);
        else
            return 0;
    }

    /**
     * @return HostControllerAssetEntity
     */
    public function getCssAsset(): HostControllerAssetEntity
    {
        return $this->cssAsset;
    }

    /**
     * @return HostControllerAssetEntity
     */
    public function getCssMapAsset(): HostControllerAssetEntity
    {
        return $this->cssMapAsset;
    }

    /**
     * @return HostControllerAssetEntity
     */
    public function getBundleJsAsset(): HostControllerAssetEntity
    {
        return $this->bundleJsAsset;
    }

    /**
     * @return HostControllerAssetEntity
     */
    public function getBundleJsMapAsset(): HostControllerAssetEntity
    {
        return $this->bundleJsMapAsset;
    }


    /**
     * @return UserEntity
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
}


class HostVersionEntity extends DBManagerEntity{
    use
        hasHostVersionIdField,
        hasVersionField,
        hasUserIdField,
        hasHostUpdateChannelField,
        hasIsDeprecatedField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualEditUrlField;

    /**
     * @param HostBuildEntity $hostBuild
     */
    public function setHostBuild(HostBuildEntity $hostBuild)
    {
        $this->dataArray[VField::HOST_BUILDS][] = $hostBuild;
    }

    /**
     * @return HostBuildEntity[]
     */
    public function getHostBuilds()
    {
        return $this->getVField(VField::HOST_BUILDS);
    }

    /**
     * @param HostControllerEntity $hostController
     */
    public function setHostController(HostControllerEntity $hostController)
    {
        $this->updateField(VField::HOST_CONTROLLER, $hostController);
    }

    /**
     * @return HostControllerEntity
     */
    public function getHostController()
    {
        return $this->getVField(VField::HOST_CONTROLLER);
    }

    /**
     * @return UserEntity
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

class HostVersionPlatformChannelEntity extends DBManagerEntity {



}
