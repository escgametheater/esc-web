<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 10/30/18
 * Time: 2:49 PM
 */

class ImageUploadForm extends PostForm {

    const RESPONSE_KEY_STATUS = 'status';
    const RESPONSE_KEY_MESSAGE = 'message';

    const RESPONSE_STATUS_ERROR = 'error';
    const RESPONSE_STATUS_SUCCESS = 'success';

    const SETTING_CROPPIE_SETTINGS = 'croppie_settings';
    const SETTING_CROPPIE_WIDTH = 'croppie_width';
    const SETTING_CROPPIE_HEIGHT = 'croppie_height';

    // Used to generate file upload field from FormField
    const PREFIX_FORM_FIELD_UPLOAD = 'upload_id_';

    // Used to generate entity image revision DB field from FormField
    const SUFFIX_DB_FIELD_REVISION = '_revision';

    const SUFFIX_SMALL = '-small';
    const SUFFIX_TINY = '-tiny';

    /** @var string */
    protected $formField;
    /** @var string */
    protected $formFieldUploadId;

    /** @var string */
    protected $form_field_display_name;


    /**
     * ImageUploadForm constructor.
     * @param Request $request
     * @param $contextEntityTypeId
     * @param DBManagerEntity $contextEntity
     * @param ImageTypeEntity $imageType
     * @param string $croppieSettings
     */
    public function __construct(Request $request, $croppieSettings = 'square', $maxWidth = 1600)
    {
        $this->formField = DBField::FILE;
        $this->formFieldUploadId = 'upload_id_'.$this->formField;
        $this->form_field_display_name = 'File';


        // Set Croppie Settings
        $this->settings[self::SETTING_CROPPIE_SETTINGS] = $croppieSettings;
        $this->settings[self::SETTING_CROPPIE_WIDTH] = null;
        $this->settings[self::SETTING_CROPPIE_HEIGHT] = null;

        // Define Form File Field
        $fields = [
            new CroppedImageFileField($this->formField, $this->form_field_display_name, true)
        ];

        parent::__construct($fields, $request);
    }

    /**
     * @return string
     */
    public function getFormFieldUploadId()
    {
        return $this->formFieldUploadId;
    }


    /**
     * @param Request $request
     * @return array|ImageAssetEntity
     */
    public function handleImageUpload(Request $request, $uploadId, $sourceFile)
    {
        $imagesAssetsManager = $request->managers->imagesAssets();

        $md5 = md5_file($sourceFile);
        $fileSize = filesize($sourceFile);
        $mimeType = mime_content_type($sourceFile);

        Modules::load_helper(Helpers::THUMBNAILS);

        list($width, $height) = ThumbnailsHelper::getImageSize($sourceFile);

        if (!$imageAsset = $imagesAssetsManager->getImageAssetByMd5($request, $md5)) {
            $imageAsset = $imagesAssetsManager->createNewImageAsset(
                $request,
                $md5,
                $width,
                $height,
                72,
                72,
                $mimeType,
                S3::BUCKET_IMAGE_ASSETS,
                "originals/",
                $fileSize
            );
        } else {
            if (!$imageAsset->is_active())
                $imageAsset->updateField(DBField::IS_ACTIVE, 1)->saveEntityToDb($request);
        }

        try {
            $request->s3->uploadFile($imageAsset->getBucket(), $imageAsset->getBucketKey(), $sourceFile);

            $this->statusMessage = $this->form_field_display_name . ' ' . $request->translations['Upload Successful'];
            $this->status = self::RESPONSE_STATUS_SUCCESS;

            UploadsHelper::delete_upload($uploadId);

        } catch (Exception $e) {

            UploadsHelper::delete_upload($uploadId);

            $imageAsset->updateField(DBField::IS_ACTIVE, 0)->saveEntityToDb($request);
        }


        return $imageAsset;
    }
}


