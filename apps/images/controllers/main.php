<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/2/18
 * Time: 6:28 PM
 */

require "../../core/domain/controllers/base.php";

class Main extends BaseContent {

    protected $url_key = 1;

    /** @var ImagesManager $imagesManager */
    protected $imagesManager;
    /** @var ImagesTypesManager $imagesTypesManager */
    protected $imagesTypesManager;
    /** @var ImagesAssetsManager $imagesAssetsManager */
    protected $imagesAssetsManager;

    protected $pages = [
        '' => 'handle_index',
        'processed' => "handle_processed_images",
        'originals' => 'handle_original_images',
        'custom-mod-assets' => 'handle_custom_mod_image_asset',
        'custom-game-assets' => 'handle_custom_game_image_asset',
    ];

    /**
     * @param Request $request
     * @param null $url_key
     * @param null $pages
     * @param null $render_default
     * @param null $root
     * @return HttpResponse|ImageAssetResponse
     */
    public function render(Request $request, $url_key = null, $pages = null, $render_default = null, $root = null)
    {
        // Set shared managers for this app
        $this->imagesManager = $request->managers->images();
        $this->imagesTypesManager = $request->managers->imagesTypes();
        $this->imagesAssetsManager = $request->managers->imagesAssets();

        // Invoke Route Handler
        return parent::render($request);
    }

    /**
     * @param Request $request
     * @return HtmlResponse
     */
    protected function handle_index(Request $request)
    {
        return new HtmlResponse('Index');
    }

    /**
     * @param Request $request
     * @return HtmlResponse
     */
    protected function handle_original_images(Request $request)
    {
        return new HtmlResponse('Originals');
    }

    /**
     * @param Request $request
     * @return HtmlResponse|ImageAssetResponse
     */
    protected function handle_processed_images(Request $request)
    {
        /**
         * Example Actual URL
         * "/processed/game-avatar/1_57b5a31d2566cf227c47819eb3e5acfa_big"
         *
         * Example path breakdown
         * "/processed/<image-type-slug>/<imageId>_<imageMd5>_<imageTypeSizeSlug>"
         *
         * Example URL Key mapping
         * "/<url_key>/<url_key>+1/<url_key>+2"
         */

        // Get Image Type Slug and Image Path Url Parts
        $imageTypeSlug = $request->getSlugForEntity($this->url_key+1);
        $imagePathPartsSlug = $request->getSlugForEntity($this->url_key+2);
        $imagePathParts = explode('_', $imagePathPartsSlug);

        // If neither slug nor parts, nor count of parts match, send 404.
        if (!$imageTypeSlug || !$imagePathPartsSlug || count($imagePathParts) < 3)
            return $this->render_404($request);

        // Extract imageId, file hash, and Image Size slug from imagePathParts
        $imageId = $imagePathParts[0];
        $imageMd5 = $imagePathParts[1];
        $imageSizeSlug = $imagePathParts[2];

        // Get Image type from url slug. If it doesnt exist, or size slug parts aren't present, send 404.
        $imageType = $this->imagesTypesManager->getImageTypeBySlug($request, $imageTypeSlug);
        if (!$imageType || !$imageSizeSlug)
            return $this->render_404($request);

        // Render 404 if this image type does not have a size corresponding to the slug.
        if (!$imageTypeSize = $imageType->getImageTypeSizeBySlug($imageSizeSlug))
            return $this->render_404($request);

        // Render 404 if this image does not exist and matches type and id.
        if (!$image = $this->imagesManager->getImageById($request, $imageId, $imageType->getPk()))
            return $this->render_404($request);

        // Compute current imageTypeSize cacheBuster hash and check against URL.
        if ($this->imagesAssetsManager->computeFileName($image->getImageAssetId(), $imageMd5) != $image->getComputedFileName())
            return $this->render_404($request);

        // Populatae image entity with some context.
        $image->setImageType($imageType);

        // Serve the actual image.
        return $this->handle_image($request, $image, $imageTypeSize);
    }

    /**
     * @param Request $request
     * @param ImageEntity $image
     * @param ImageTypeSizeEntity $imageTypeSize
     * @return ImageAssetResponse|HtmlResponse
     */
    protected function handle_image(Request $request, ImageEntity $image, ImageTypeSizeEntity $imageTypeSize)
    {
        if ($request->readHeader('if-none-match') == $imageTypeSize->generateCacheBuster()) {

            return new ImageAssetResponse($image, $imageTypeSize, '', HttpResponse::HTTP_NOT_MODIFIED);
        }

        try {

            $imageFile = $request->s3->readIntoMemory($image->getBucket(), $image->getBucketKey());

            $imagick = new Imagick();

            $imagick->readImageBlob($imageFile);

            $imagick->setCompressionQuality($imageTypeSize->getQuality());
            $imagick->scaleImage($imageTypeSize->getWidth(), $imageTypeSize->getHeight());

            return new ImageAssetResponse($image, $imageTypeSize, $imagick->getImageBlob());

        } catch (Exception $e) {
            return $this->render_404($request);
        }
    }
}