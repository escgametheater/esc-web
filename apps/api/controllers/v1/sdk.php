<?php
class SdkApiV1Controller extends BaseApiV1Controller
{
    const REQUIRES_POST = true;
    const REQUIRES_AUTH = true;

    /** @var SdkVersionsManager */
    protected $manager;

    protected $pages = [
        '' => "handle_index",
        'upload-sdk-build' => "upload_sdk_build",
    ];

    /**
     * @param Request $request
     * @return HttpResponse
     */
    public function handle_index(Request $request) : HttpResponse
    {
        $request->user->sendFlashMessage('Index Not Implemented Yet');
        return $this->redirect(HOMEPAGE);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     * @throws ESCFrameworkException
     * @throws EntityFieldAccessException
     */
    public function upload_sdk_build(Request $request): ApiV1Response
    {
        $sdkPlatformsManager = $request->managers->sdkPlatforms();
        $sdkBuildsManager = $request->managers->sdkBuilds();
        $sdkAssetsManager = $request->managers->sdkAssets();

        $sdkPlatforms = $sdkPlatformsManager->getAllActiveSdkPlatformSlugOptions($request);

        $fields = [
            new BuildVersionField(DBField::VERSION, 'SDK Version', 32, true, 'Sdk app version', ''),
            new FileField(VFIeld::SDK_ARCHIVE, 'SDK ZIP File', true, ['zip']),
            new SelectField(DBField::SDK_PLATFORM_SLUG, 'SDK Platform', $sdkPlatforms, true, 'Which SDK platform is this build for?'),
        ];
        $defaults = [];

        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($isValid = $this->form->is_valid()) {

            $sdkVersionNumber = $this->form->getCleanedValue(DBField::VERSION);
            $sdkPlatformSlug = $this->form->getCleanedValue(DBField::SDK_PLATFORM_SLUG);
            $sdkVersionArchiveFileId = $this->form->getCleanedValue(VField::SDK_ARCHIVE)['upload_id_sdk_archive'];

            $sdkPlatform = $sdkPlatformsManager->getSdkPlatformBySlug($request, $sdkPlatformSlug);

            $sdkVersion = $this->manager->getSdkVersionBySdkVersionNumber($request, $sdkVersionNumber);
            if (!$sdkVersion) {
                $sdkVersion = $this->manager->createNewSdkVersion($request, $sdkVersionNumber);
            }

            $sourceFile = UploadsHelper::path_from_file_id($sdkVersionArchiveFileId);

            Modules::load_helper(Helpers::ZIP_UPLOAD);

            $destinationFolder = $sdkBuildsManager->generateZipDestinationPath();

            $zipUploadHelper = new ZipUploadHelper($sourceFile, $destinationFolder);

            if ($zipUploadHelper->extract() && $zipUploadHelper->getRootFileFound() && $processedFiles = $zipUploadHelper->getProcessedFiles()) {
                $sdkAppBuildAssets = [];

                $dbConnection = $request->db->get_connection();
                $dbConnection->begin();

                $sdkBuild = $sdkBuildsManager->createNewSdkBuild($request, $sdkVersion->getPk(), $sdkPlatform->getPk(), $zipUploadHelper->getArchiveVersionHash());

                foreach ($processedFiles as $realFilePath => $values) {

                    $fileName = $values[ZipUploadHelper::FILE_NAME];
                    $folderPath = $values[ZipUploadHelper::FOLDER_PATH];

                    try {
                        $sdkBuildAsset = $sdkAssetsManager->handleSdkBuildAssetUpload(
                            $request,
                            $realFilePath,
                            md5_file($realFilePath),
                            base64_encode(hash_file('sha512', $realFilePath, true)),
                            $fileName,
                            $sdkBuild->getPk(),
                            $folderPath
                        );

                    } catch (Exception $e) {
                        $dbConnection->rollback();
                        $this->form->set_error($e->getMessage(), VField::SDK_ARCHIVE);
                        $isValid = false;
                        break;
                    }

                    $sdkAppBuildAssets[] = $sdkBuildAsset;
                    $sdkBuild->setSdkAsset($sdkBuildAsset);

                }

                if ($isValid) {
                    $dbConnection->commit();
                    $this->setResults($sdkBuild);
                } else {
                    $dbConnection->rollback();
                }

            }

            FilesToolkit::clear_directory($destinationFolder);
            if (is_dir($destinationFolder))
                rmdir($destinationFolder);
            UploadsHelper::delete_upload($sdkVersionArchiveFileId);


            if (!$zipUploadHelper->getZipOpened()) {
                $this->form->set_error('Zip archive failed to open.', VField::SDK_ARCHIVE);
            } else {
                if (!$zipUploadHelper->getProcessedFiles()) {
                    $this->form->set_error('No files found in zip.', VField::SDK_ARCHIVE);
                } else {
                    if (!$zipUploadHelper->getRootFileFound()) {
                        $this->form->set_error("{$sdkBuildsManager->getZipRootFileIdentifier()} not found in archive.", VField::SDK_ARCHIVE);
                    }
                }
            }
        }

        return $this->renderApiV1Response($request);
    }
}