<?php
/**
 * Files function
 *
 */

class FilesToolkit
{

    /**
     * @param $suffix
     *
     * @return string
     *
     * @throws ExtractionException
     */
    public static function make_directory($mediaDir, $suffix) {
        $dir = $mediaDir . '/' . $suffix;
        $i = 0;
        while (!is_dir($dir) && !mkdir($dir) && $i < 3) {
            ++$i;
        }

        if ($i >= 3) {
            throw new Exception("failed to create directory $dir");
        }

        return $dir;
    }

    /**
    * Clear all files in a given directory.
    *
    * @param  string An absolute filesystem path to a directory.
    * @return void
    */
    public static function clear_directory($directory)
    {
        if (!is_dir($directory))
            return;

        // Deleted files count
        $count = 0;

        // open a file point to the cache dir
        $fp = opendir($directory);

        // ignore names
        $ignore = ['.', '..', 'CVS', '.svn'];

        while (($file = readdir($fp)) !== false) {
            if (!in_array($file, $ignore)) {
                if (is_link($directory.'/'.$file)) {
                    // delete symlink
                    unlink($directory.'/'.$file);
                } elseif (is_dir($directory.'/'.$file)) {
                    // recurse through directory
                    $count += self::clear_directory($directory.'/'.$file);
                    // delete the directory
                    rmdir($directory.'/'.$file);
                } else {
                    // delete the file
                    $count += unlink($directory.'/'.$file);
                }
            }
        }

        return $count;
    }

    /**
     * Clear all files and directories corresponding to a glob pattern.
     *
     * @param  string An absolute filesystem pattern.
     * @return void
     */
    public static function clear_glob($pattern)
    {
        $files = glob($pattern);

        // Deleted files count
        $count = 0;

        // order is important when removing directories
        sort($files);

        foreach ($files as $file) {
            if (is_dir($file)) {
                // delete directory
                $count += self::clear_directory($file);
            } else {
                // delete file
                $count += unlink($file);
            }
        }

        return $count;
    }

    /**
     * Count of files
     *
     * @param string $dir
     * @return integer
     */
    public static function num_files($dir)
    {
        if (!is_dir($dir))
            return 0;

        $i = 0;

        while (foreach_dir($dir, $entrie))
            $i++;

        return $i;
    }

    /**
     * List files in a directory
     *
     * @param string $dir
     * @return array
     */
    public static function list_files($dir, $sort = false, $onlyType = null)
    {
        $files = [];

        if (is_dir($dir)) {
            $fp = opendir($dir);
            while (($file = readdir($fp)) !== false) {
                if ($onlyType === null || Strings::endswith($file, $onlyType)) {
                    if (!in_array($file, ["__MACOSX", ".", ".."])) {

                        $secondLevel = rtrim($dir, '/').'/'.$file;

                        if (is_dir($secondLevel)) {
                            $subFiles = self::list_files($secondLevel);
                            foreach ($subFiles as $subFile) {
                                $files[] = "{$file}/{$subFile}";
                            }
                        } else {
                            $files[] = $file;
                        }

                    }
                }
            }
        }

        if ($sort)
            sort($files);

        return $files;
    }

    /**
     * Correctly join multiple paths
     *
     * @param string $dir
     * @return integer
     */
    public static function join_path()
    {
        $num_args = func_num_args();
        $args = func_get_args();
        $path = $args[0];

        if( $num_args > 1 ) {
            for ($i = 1; $i < $num_args; $i++) {
                if ($path != '' && substr($path, -1) == '/')
                    $path = substr($path, 0, strlen($path) - 1);

                $s = $args[$i];
                if ($s != '' && $s[0] == '/')
                    $path .= $s;
                else
                    $path .= '/'.$s;
            }
        }

        return $path;
    }

    /**
     * Split filename and extension
     *
     * @param string $dir
     * @return integer
     */
    public static function split_filename($filename)
    {
        $pos = strrpos($filename, '.');
        if ($pos === false) {  // dot is not found in the filename
            return [$filename, '']; // no extension
        } else {
            $basename = substr($filename, 0, $pos);
            $extension = substr($filename, $pos + 1);
            return [$basename, $extension];
        }
    }

    /**
     * @param $filename
     * @return string
     */
    public static function get_base_filename($filename)
    {
        return self::split_filename($filename)[0];
    }

    /**
     * @param $filename
     * @return string
     */
    public static function get_file_extension($filename, $lowerCase = false)
    {
        list($baseFileName, $fileExtension) = self::split_filename($filename);

        return $lowerCase ? strtolower($fileExtension) : $fileExtension;
    }
}
