<?php
/**
 * Created by: apcosman
 * Date: 01/07/19
 */

class HostsAppApiV1Controller extends BaseApiV1Controller
{
    const REQUIRES_POST = true;
    const REQUIRES_AUTH = true;

    /** @var HostVersionsManager */
    protected $manager;

    protected $pages = [
        '' => "handle_index",
        'upload-build' => "upload_host_app_build",
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
     * @return HtmlResponse|JSONResponse
     */
    public function upload_host_app_build(Request $request): ApiV1Response
    {
        $platformsManager = $request->managers->platforms();
        $hostBuildsManager = $request->managers->hostBuilds();
        $hostAssetsManager = $request->managers->hostAssets();

        $platforms = $platformsManager->getAllActivePlatformSlugOptions($request);

        $fields = [
            new BuildVersionField(DBField::VERSION, 'Host App Version', 32, true, 'Host app version', ''),
            new FileField(VField::HOST_APP_ARCHIVE, 'Host App ZIP File', true, ['zip']),
            new SelectField(DBField::PLATFORM_SLUG, 'Platform', $platforms, true, 'Which platform is this build for?'),
        ];
        $defaults = [];

        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($isValid = $this->form->is_valid()) {

            $hostVersionNumber = $this->form->getCleanedValue(DBField::VERSION);
            $platformSlug = $this->form->getCleanedValue(DBField::PLATFORM_SLUG);
            $hostVersionArchiveFileId = $this->form->getCleanedValue(VField::HOST_APP_ARCHIVE)['upload_id_host_app_archive'];

            $platform = $platformsManager->getPlatformBySlug($request, $platformSlug);

            $hostVersion = $this->manager->getHostVersionByHostVersionNumber($request, $hostVersionNumber);
            if (!$hostVersion) {
                $hostVersion = $this->manager->createNewHostVersion($request, $hostVersionNumber);
            }

            $sourceFile = UploadsHelper::path_from_file_id($hostVersionArchiveFileId);

            Modules::load_helper(Helpers::ZIP_UPLOAD);

            $destinationFolder = $hostBuildsManager->generateZipDestinationPath();

            $zipUploadHelper = new ZipUploadHelper($sourceFile, $destinationFolder);

            if ($zipUploadHelper->extract() && $zipUploadHelper->getRootFileFound() && $processedFiles = $zipUploadHelper->getProcessedFiles()) {
                $hostAppBuildAssets = [];

                $dbConnection = $request->db->get_connection();
                $dbConnection->begin();

                if ($hostBuild = $hostBuildsManager->getHostBuildByVersionHash($request, $zipUploadHelper->getArchiveVersionHash())) {
                    if (!$hostBuild->is_active()) {
                        $hostBuild->updateField(DBField::IS_ACTIVE, 1)->saveEntityToDb($request);
                    }
                } else {
                    $hostBuild = $hostBuildsManager->createNewHostBuild($request, $hostVersion->getPk(), $platform->getPk(), $zipUploadHelper->getArchiveVersionHash());
                }

                foreach ($processedFiles as $realFilePath => $values) {

                    $fileName = $values[ZipUploadHelper::FILE_NAME];
                    $folderPath = $values[ZipUploadHelper::FOLDER_PATH];

                    try {
                        $hostBuildAsset = $hostAssetsManager->handleHostBuildAssetUpload(
                            $request,
                            $realFilePath,
                            md5_file($realFilePath),
                            base64_encode(hash_file('sha512', $realFilePath, true)),
                            $fileName,
                            $hostBuild->getPk(),
                            $folderPath
                        );

                    } catch (Exception $e) {
                        $dbConnection->rollback();
                        $this->form->set_error($e->getMessage(), VField::HOST_APP_ARCHIVE);
                        $isValid = false;
                        break;
                    }

                    $hostAppBuildAssets[] = $hostBuildAsset;
                    $hostBuild->setHostAsset($hostBuildAsset);

                }

                if ($isValid) {
                    $dbConnection->commit();
                    $this->setResults($hostBuild);
                } else {
                    $dbConnection->rollback();
                }

            }

            FilesToolkit::clear_directory($destinationFolder);
            if (is_dir($destinationFolder))
                rmdir($destinationFolder);
            UploadsHelper::delete_upload($hostVersionArchiveFileId);


            if (!$zipUploadHelper->getZipOpened()) {
                $this->form->set_error('Zip archive failed to open.', VField::HOST_APP_ARCHIVE);
            } else {
                if (!$zipUploadHelper->getProcessedFiles()) {
                    $this->form->set_error('No files found in zip.', VField::HOST_APP_ARCHIVE);
                } else {
                    if (!$zipUploadHelper->getRootFileFound()) {
                        $this->form->set_error("{$hostBuildsManager->getZipRootFileIdentifier()} not found in archive.", VField::HOST_APP_ARCHIVE);
                    }
                }
            }
        }

        return $this->renderApiV1Response($request);
    }

}