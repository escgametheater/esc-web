<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 2/25/19
 * Time: 11:44 AM
 */


class GameControllerEntity extends DBManagerEntity {

    use
        hasGameIdField,
        hasGameControllerTypeIdField,
        hasGameControllerVersionField,
        hasGameBuildIdField,
        hasUpdateChannelField,

        hasVersionHashField,

        // Fetched from controller type
        hasDisplayNameField,

        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualGameControllerTypeField,
        hasVirtualUrlField;
    /*
     * We are storing the reference to the game assets in a protected property as well as in the natural data array
     * so we can get game assets by ID specifically if needed. This is because when we deserialize the data array,
     * if the assets are stored in an indexed array, that becomes a JSON Object instead of an array of json objects.
     */

    /** @var GameControllerAssetEntity[] $gameControllerAssets  */
    protected $gameControllerAssets = [];

    /** @var GameControllerAssetEntity */
    protected $manifestJsonAsset;
    /** @var GameControllerAssetEntity */
    protected $assetManifestJsonAsset;
    /** @var GameControllerAssetEntity */
    protected $serviceWorkerJsonAsset;
    /** @var GameControllerAssetEntity */
    protected $cssAsset;
    /** @var GameControllerAssetEntity */
    protected $cssMapAsset;
    /** @var GameControllerAssetEntity */
    protected $bundleJsAsset;
    /** @var GameControllerAssetEntity */
    protected $bundleJsMapAsset;
    /** @var GameControllerAssetEntity[] */
    protected $imageAssets = [];
    /** @var GameControllerAssetEntity[]  */
    protected $jsAssets = [];
    /** @var GameControllerAssetEntity[]  */
    protected $cssAssets = [];

    /** @var GameControllerAssetEntity[]  */
    protected $assetsByPath = [];

    /**
     * @param GameAssetEntity $gameAsset
     */
    public function setGameControllerAsset(GameControllerAssetEntity $gameAsset)
    {
        $this->dataArray[VField::GAME_CONTROLLER_ASSETS][] = $gameAsset;
        $this->gameControllerAssets[$gameAsset->getPk()] = $gameAsset;

        $fileName = $gameAsset->getFileName();

        if (strtolower($gameAsset->getExtension()) == 'js')
            $this->jsAssets[] = $gameAsset;
        elseif (strtolower($gameAsset->getExtension()) == 'css')
            $this->cssAssets[] = $gameAsset;
        elseif ($fileName == "bundle.js.map")
            $this->bundleJsMapAsset = $gameAsset;
        elseif ($fileName == "manifest.json")
            $this->manifestJsonAsset = $gameAsset;
        elseif ($fileName == "service-worker.js")
            $this->serviceWorkerJsonAsset = $gameAsset;
        elseif ($fileName == "asset-manifest.json")
            $this->assetManifestJsonAsset = $gameAsset;
        elseif ($fileName == "main.css.map")
            $this->cssMapAsset = $gameAsset;
        elseif ($gameAsset->getFolderPath() == "images/")
            $this->imageAssets[] = $gameAsset;

        $this->assetsByPath["{$gameAsset->getFolderPath()}{$gameAsset->getFileName()}"] = $gameAsset;
    }

    /**
     * @param $path
     * @return GameControllerAssetEntity|null
     */
    public function getGameAssetByPath($path)
    {
        if (array_key_exists($path, $this->assetsByPath))
            return $this->assetsByPath[$path];
        return null;
    }

    /**
     * @return GameControllerAssetEntity[]
     */
    public function getGameControllerAssets()
    {
        return $this->gameControllerAssets;
    }

    /**
     * @param $gameAssetId
     * @return GameAssetEntity
     */
    public function getGameControllerAssetById($gameAssetId)
    {
        return $this->gameControllerAssets[$gameAssetId];
    }


    /**
     * @return GameControllerAssetEntity
     */
    public function getManifestJsonAsset(): GameControllerAssetEntity
    {
        return $this->manifestJsonAsset;
    }

    /**
     * @return GameControllerAssetEntity
     */
    public function getAssetManifestJsonAsset(): GameControllerAssetEntity
    {
        return $this->assetManifestJsonAsset;
    }

    /**
     * @return GameControllerAssetEntity
     */
    public function getServiceWorkerJsonAsset(): GameControllerAssetEntity
    {
        return $this->serviceWorkerJsonAsset;
    }

    /**
     * @return GameControllerAssetEntity
     */
    public function getCssAsset()
    {
        return $this->cssAsset;
    }

    /**
     * @return GameControllerAssetEntity
     */
    public function getCssMapAsset()
    {
        return $this->cssMapAsset;
    }

    /**
     * @return GameControllerAssetEntity
     */
    public function getBundleJsAsset()
    {
        return $this->bundleJsAsset;
    }

    /**
     * @return GameControllerAssetEntity
     */
    public function getBundleJsMapAsset()
    {
        return $this->bundleJsMapAsset;
    }

    /**
     * @return GameControllerAssetEntity[]
     */
    public function getImageAssets(): array
    {
        return $this->imageAssets;
    }

    /**
     * @return int
     */
    public function getGameControllerAssetsFileSize()
    {
        $fileSize = 0;

        foreach ($this->getGameControllerAssets() as $gameControllerAsset) {
            $fileSize = $fileSize + $gameControllerAsset->getFileSize();
        }

        return $fileSize;
    }

    /**
     * @return array
     */
    public function getJsAssets(): array
    {
        return $this->jsAssets;
    }

    /**
     * @return array
     */
    public function getCssAssets(): array
    {
        return $this->cssAssets;
    }

    public function replaceSdkAssets(SdkBuildEntity $sdkBuild)
    {
        foreach ($this->jsAssets as $key => $jsAsset) {
            if (strpos($jsAsset->getFileName(), "2-esc-controller-sdk") === 0) {
                unset($this->jsAssets[$key]);
            }
        }

        foreach ($this->cssAssets as $key => $cssAsset) {
            if (strpos($cssAsset->getFileName(), "2-esc-controller-sdk") === 0) {
                unset($this->cssAssets[$key]);
            }
        }

        foreach ($sdkBuild->getControllerSdkAssets() as $sdkAsset) {
            if (strpos($sdkAsset->getFileName(), "2-esc-controller-sdk") === 0) {

                if (strpos($sdkAsset->getFileName(), "images") !== -1) {
                    if ($sdkAsset->getExtension() == 'js') $this->jsAssets[] = $sdkAsset;
                    else if ($sdkAsset->getExtension() == 'css') $this->cssAssets[] = $sdkAsset;
                }
            }
        }

        usort($this->jsAssets, function($a, $b)
        {
            return strcmp($a[DBField::FILENAME], $b[DBField::FILENAME]);
        });
        usort($this->cssAssets, function($a, $b)
        {
            return strcmp($a[DBField::FILENAME], $b[DBField::FILENAME]);
        });

    }
}

class GameControllerTypeEntity extends DBManagerEntity {
    use
        hasDisplayNameField,
        hasSlugField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class GameBuildControllerEntity extends DBManagerEntity {

    use
        hasGameBuildIdField,
        hasGameControllerIdField,
        hasIsActiveField,
        hasIsDeletedField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

}