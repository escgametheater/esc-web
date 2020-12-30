<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 10/29/18
 * Time: 2:24 PM
 */

class ImageAssetEntity extends DBManagerEntity
{
    use
        hasMd5Field,
        hasWidthField,
        hasHeightField,
        hasAspectXField,
        hasDpiXField,
        hasDpiYField,
        hasMimeTypeField,
        hasBucketField,
        hasBucketPathField,
        hasFileSizeField,
        hasComputedFileNameField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualUrlField,
        hasVirtualBucketKeyField;
}


class ImageEntity extends ImageAssetEntity
{
    use
        hasContextEntityTypeIdField,
        hasContextEntityIdField,
        hasImageAssetIdField,
        hasImageTypeIdField,
        hasSlugField,
        hasFileNameField,
        hasExtensionField,
        hasCreateTimeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param ImageTypeEntity $imageType
     */
    public function setImageType(ImageTypeEntity $imageType)
    {
        $this->updateField(VField::IMAGE_TYPE, $imageType);
    }

    /**
     * @return ImageTypeEntity|array
     */
    public function getImageType()
    {
        return $this->getVField(VField::IMAGE_TYPE);
    }
}

class ImageTypeEntity extends DBManagerEntity
{
    use
        hasSlugField,
        hasDisplayNameField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param ImageTypeSizeEntity $imageTypeSize
     */
    public function setImageTypeSize(ImageTypeSizeEntity $imageTypeSize)
    {
        $this->dataArray[VField::SIZES][$imageTypeSize->getSlug()] = $imageTypeSize;
    }

    /**
     * @return ImageTypeSizeEntity[]
     */
    public function getImageTypeSizes()
    {
        return $this->getVField(VField::SIZES);
    }

    /**
     * @param $slug
     * @return array|ImageTypeSizeEntity
     */
    public function getImageTypeSizeBySlug($slug)
    {
        return array_key_exists($slug, $this->dataArray[VField::SIZES]) ? $this->dataArray[VField::SIZES][$slug] : [];
    }
}

class ImageTypeSizeEntity extends DBManagerEntity
{
    use
        hasImageTypeIdField,
        hasSlugField,
        hasDisplayNameField,
        hasWidthField,
        hasHeightField,
        hasQualityField,
        hasMimeTypeField,
        hasExtensionField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @return string
     */
    public function generateUrlField()
    {
        return "{$this->getSlug()}_url";
    }

    /**
     * @return string
     */
    public function generateCacheBuster()
    {
        return md5("{$this->getWidth()}x{$this->getHeight()}@{$this->getQuality()}");
    }
}