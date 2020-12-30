<?php
/**
 * Uploads Manager
 *
 * @package managers
 */

class UploadsManager extends BaseEntityManager
{
    protected $entityClass = UploadEntity::class;
    protected $table = Table::Uploads;
    protected $table_alias = TableAlias::Uploads;

    const DIRECTORY_ORIGINAL = 'artist-fonts-originals';
    const DIRECTORY_CONVERTED = 'artist-fonts-converted';

    public function generateNewFileUploadMD5(Request $request)
    {
        return md5($request->user->id.'-'.TIME_NOW.'-'.rand());
    }

    /**
     * @param $release_id
     * @param $config
     * @param string $directory
     * @return string
     */
    public static function get_target_file_path($directory, $filename)
    {
        global $CONFIG;

        return $CONFIG[ESCConfiguration::DIR_MEDIA]."/{$directory}/{$filename}";
    }


    /**
     * @param Request $request
     * @param ComicEntity $comic
     * @param ActivityEntity $activity
     * @param $fileName
     * @param $fileSize
     * @param $fileMd5
     * @param null $uploadId
     * @return ComicAssetArchiveEntity
     * @throws Exception
     */
    public function handleUploadedComicAssetArchive(Request $request, ComicEntity $comic, $fileName, $fileSize, $fileMd5, $uploadId = null, $langId = null, $releaseId = null, $extractArchive = false)
    {
        $comicsReleasesManager = $request->managers->comicsReleases();
        $comicsAssetsArchivesManager = $request->managers->comicsAssetsArchives();
        $activityTrackingManager = $request->managers->activity();

        // Handle New Asset Archive Upload -- first check if we have it already, and if so, skip writing new archive
        if (!$comicAssetArchive = $comicsAssetsArchivesManager->getSourceComicAssetArchiveByComicMd5($request, $comic->getPk(), $fileMd5)) {
            $comicAssetArchive = $comicsAssetsArchivesManager->createNewComicAssetArchive(
                $request,
                $comic->getPk(),
                ComicsAssetsArchivesTypesManager::TYPE_ORIGINAL,
                $fileName,
                $fileSize,
                $fileMd5
            );

            $activity = $activityTrackingManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_COMIC_NEW_ARCHIVE_UPLOAD,
                $comic->getPk(),
                $comicAssetArchive->getPk(),
                $langId,
                $comic->getOwner($request),
                $comic->getArtistId(),
                $comic->getPk()
            );

            $dest = $comicsReleasesManager->get_directory_path(
                $comicAssetArchive->getPk(),
                $request->config,
                ComicsAssetsArchivesManager::DIRECTORY_ARCHIVES
            );

            if (!is_dir(dirname($dest)))
                mkdir(dirname($dest), 0730, true);

            $src  = UploadsHelper::path_from_file_id($uploadId);

            if (rename($src, $dest)) {
                // delete upload if md5 matches
                if ($uploadId && $fileMd5 == md5_file($dest))
                    UploadsHelper::delete_upload($uploadId, false);

                $request->s3->upload_file_from_media_dir($dest);

                // Convertion for read online
                if ($extractArchive && $releaseId)
                    $comicsReleasesManager->addReleaseExtractionTask($releaseId, $activity->getPk(), false, $comicAssetArchive->getPk());

            } else {
                elog("ComicAssetArchive {$comicAssetArchive->getPk()}: Failed to move $src to $dest");
                $comicAssetArchive->updateField(DBField::COMIC_ASSET_ARCHIVE_STATUS_ID, ComicsAssetsArchivesStatusesManager::STATUS_FAILED)->saveEntityToDb($request);
            }
        } else {
            $activity = $activityTrackingManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_COMIC_EXISTING_ARCHIVE_UPLOAD,
                $comic->getPk(),
                $comicAssetArchive->getPk(),
                $langId,
                $comic->getOwner($request),
                $comic->getArtistId(),
                $comic->getPk()
            );

        }

        return $comicAssetArchive;
    }

    /**
     * @param Request $request
     * @param int $comicId
     * @param $sourceFile
     * @param $fileName
     * @param $fileSize
     * @param $fileMd5
     * @param null $uploadId
     * @param bool|false $triggerConversion
     * @return ComicAssetFileEntity
     * @throws Exception
     */
    public function handleUploadedComicAssetFile(Request $request, $artistId, $comicId, $sourceFile, $fileName, $fileSize, $fileMd5, $uploadId = null, $sourceComicAssetArchiveId = null, $triggerConversion = false, $sourceComicAssetFileId = null)
    {
        $comicsReleasesManager = $request->managers->comicsReleases();

        $comicsAssetsFilesManager = $request->managers->comicsAssetsFiles();

        if (!$comicAssetFile = $comicsAssetsFilesManager->getComicAssetFileByMd5($request, $comicId, $fileMd5)) {

            Modules::load_helper(Helpers::THUMBNAILS);

            list($width, $height) = ThumbnailsHelper::getImageSize($sourceFile);
            $dpi = ThumbnailsHelper::getImageDpi($sourceFile);

            $comicAssetFile = $comicsAssetsFilesManager->createComicAssetFile(
                $request,
                $artistId,
                $comicId,
                $fileMd5,
                $fileName,
                $fileSize,
                $width,
                $height,
                $dpi['x'],
                $dpi['y'],
                $width / $height,
                $sourceComicAssetArchiveId,
                $sourceComicAssetFileId
            );
        }

        $targetFileName = $comicsReleasesManager->get_directory_path(
            $comicAssetFile->getPk(),
            $request->config,
                ComicsAssetsFilesManager::DIRECTORY_ORIGINAL
        )."/{$fileName}";

        if (!is_dir(dirname($targetFileName)))
            mkdir(dirname($targetFileName), 0730, true);

        if ($uploadId)
            $moved = rename($sourceFile, $targetFileName);
        else
            $moved = copy($sourceFile, $targetFileName);

        if ($moved) {
            // delete upload if md5 matches
            if ($uploadId && $fileMd5 == md5_file($targetFileName))
                UploadsHelper::delete_upload($uploadId, false);

            // If the comicAssetFileStatus is new or failed, let's re-upload new file and re-trigger conversion
            if (!$comicAssetFile->is_status_processing()) {

                // If the file was previously not processed, or failed, and the filename changed, let's update the filename
                // in the db record
                if ($fileName != $comicAssetFile->getFilename()) {
                    $updatedFileData = [
                        DBField::FILENAME => $fileName,
                        DBField::FILE_EXTENSION => FilesToolkit::get_file_extension($fileName, true)
                    ];
                    $comicAssetFile->assign($updatedFileData)->saveEntityToDb($request);
                }

                //std_log("* uploading original file for comicAssetFileId {$comicAssetFile->getPk()}: {$targetFileName}");
                $request->s3->upload_file_from_media_dir($targetFileName);

                // Conversion for read online
                if ($triggerConversion)
                    $comicsAssetsFilesManager->addAssetConversionTask($comicAssetFile);
            }

        } else {
            elog("Failed to copy/move $sourceFile to $targetFileName");
        }

        return $comicAssetFile;
    }

    /**
     * @param Request $request
     * @param ArtistFontEntity $artistFont
     * @param $uploadId
     * @param $sourceFile
     * @param bool|true $triggerConversion
     * @return bool
     * @throws Exception
     */
    public function handleUploadedArtistFontFile(Request $request, ArtistFontEntity $artistFont, $uploadId, $sourceFile)
    {
        $success = false;
        $directoryPath = self::DIRECTORY_ORIGINAL."/{$artistFont->getPk()}";

        $targetFileName = "{$artistFont->getMd5()}.{$artistFont->getFileExtension()}";

        $targetFilePath = $this->get_target_file_path($directoryPath, $targetFileName);

        if (!is_dir(dirname($targetFilePath)))
            mkdir(dirname($targetFilePath), 0730, true);

        if (rename($sourceFile, $targetFilePath)) {
            // delete upload if md5 matches
            if ($uploadId && $artistFont->getMd5() == md5_file($targetFilePath))
                UploadsHelper::delete_upload($uploadId, false);

            //std_log("* uploading original file for comicAssetFileId {$comicAssetFile->getPk()}: {$targetFileName}");
            $request->s3->upload_file_from_media_dir($targetFilePath);

            $success = true;

        } else {
            elog("Failed to copy/move $sourceFile to $targetFilePath");
        }

        return $success;
    }

}
