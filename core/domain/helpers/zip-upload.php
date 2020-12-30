<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 1/7/19
 * Time: 3:57 PM
 */

class ZipUploadHelper {

    const FILE_NAME = 'file_name';
    const FOLDER_PATH = 'folder_path';

    /** @var string  */
    protected $sourceFile = '';
    /** @var string  */
    protected $destinationFolder = '';
    /** @var string  */
    protected $rootFileIdentifier = '';
    /** @var string  */
    protected $removeFolderPrefix = '';
    /** @var array  */
    protected $originalFiles = [];
    /** @var array */
    protected $processedFiles = [];
    /** @var bool  */
    protected $zipOpened = false;
    /** @var bool  */
    protected $rootFileFound = false;
    /** @var string[] */
    protected $fileMd5s = [];


    /**
     * ZipUploadHelper constructor.
     * @param $sourceFile
     * @param $destinationFolder
     * @param $rootFileIdentifier
     */
    public function __construct($sourceFile, $destinationFolder, $rootFileIdentifier = null)
    {
        $this->sourceFile = $sourceFile;
        $this->destinationFolder = $destinationFolder;
        $this->rootFileIdentifier = $rootFileIdentifier;
    }


    /**
     * Method for extracting a zip archive and finding the full file paths, and relative file paths for upload into
     * AWS buckets that want to know the relative bucket path.
     */

    /**
     * @return bool
     */
    public function extract()
    {
        $zipArchive = new ZipArchive();
        $status = $zipArchive->open($this->sourceFile);

        if ($status === true) {
            $this->zipOpened = true;

            if (!is_dir($this->destinationFolder)) {
                if (mkdir($this->destinationFolder, 0777, true)) {

                    $zipArchive->extractTo($this->destinationFolder);

                    $this->originalFiles = FilesToolkit::list_files($this->destinationFolder);

                    if ($this->rootFileIdentifier) {
                        foreach ($this->originalFiles as $file) {
                            $stringPosition = strpos($file, $this->rootFileIdentifier);
                            if ($stringPosition !== false) {
                                $this->rootFileFound = true;

                                if ($stringPosition !== 0)
                                    $this->removeFolderPrefix = str_replace($this->rootFileIdentifier, '', $file);
                            }
                        }
                    } else {
                        $this->rootFileFound = true;
                    }

                    foreach ($this->originalFiles as $file) {

                        if (strpos($file, '/')) {
                            $filePathParts = explode('/', $file);

                            $fileName = array_pop($filePathParts);

                            if (count($filePathParts) > 0) {
                                $filePathParts[] = '';
                                $folderPath = join('/', $filePathParts);
                            }

                        } else {
                            $fileName = $file;
                            $folderPath = null;
                        }

                        if ($folderPath && $this->removeFolderPrefix) {
                            $folderPath = str_replace($this->removeFolderPrefix, '', $folderPath);
                        }

                        $fullFilePath = "{$this->destinationFolder}{$file}";

                        $this->fileMd5s[] = md5_file($fullFilePath);

                        $this->processedFiles[$fullFilePath] = [
                            self::FILE_NAME => $fileName,
                            self::FOLDER_PATH => $folderPath,
                        ];
                    }
                }
            }
        }

        return $this->zipOpened;
    }

    /**
     * @return bool
     */
    public function getZipOpened()
    {
        return $this->zipOpened;
    }

    /**
     * @param $fileName
     * @return bool
     */
    public function hasFileName($fileName)
    {
        foreach ($this->processedFiles as $processedFile) {
            if ($processedFile[self::FILE_NAME] == $fileName)
                return true;
        }
        return false;
    }

    /**
     * @return array
     */
    public function getProcessedFiles(): array
    {
        return $this->processedFiles;
    }

    public function checkFileNameExists($fileName)
    {

    }

    /**
     * @return array
     */
    public function getFilePaths(): array
    {
        return array_keys($this->processedFiles);
    }

    /**
     * @return bool
     */
    public function getRootFileFound()
    {
        return $this->rootFileFound;
    }

    /**
     * @return array
     */
    public function getSortedFileMd5s()
    {
        $md5s = $this->fileMd5s;

        sort($md5s);

        return $md5s;
    }

    /**
     * @return string
     */
    public function getArchiveVersionHash()
    {
        $versionHashSource = '';
        foreach ($this->getSortedFileMd5s() as $md5) {
            $versionHashSource .= $md5;
        }
        return sha1($versionHashSource);
    }

    /**
     * @param $filePath
     * @return string
     */
    public function getFileName($filePath)
    {
        return array_key_exists($filePath, $this->processedFiles) ? $this->processedFiles[$filePath][self::FILE_NAME] : '';
    }

    /**
     * @param $filePath
     * @return string
     */
    public function getFolderPath($filePath)
    {
        return array_key_exists($filePath, $this->processedFiles) ? $this->processedFiles[$filePath][self::FOLDER_PATH] : '';
    }

}