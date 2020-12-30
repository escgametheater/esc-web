<?php
/**
 * Uploads Helper
 * handles uploads
 *
 * @package helpers
 */

class UploadException extends Exception {}

class UploadsHelper
{
    /**
     * Move uploaded file to destination
     *
     * @param int file id
     */
    public static function finish_upload($tmp_name, $fileid, $filename, $copy = false)
    {
        global $CONFIG;

        if (!is_dir($CONFIG[ESCConfiguration::DIR_UPLOAD])) {
            if (!mkdir($CONFIG[ESCConfiguration::DIR_UPLOAD], 0700, true))
                throw new eConfiguration('Impossible to create uploads directory: '.$CONFIG[ESCConfiguration::DIR_UPLOAD]);
        }
        if (!is_writable($CONFIG[ESCConfiguration::DIR_UPLOAD]))
            throw new eConfiguration('Uploads directory is not writable: '.$CONFIG[ESCConfiguration::DIR_UPLOAD]);

        if ($copy)
            $success = copy($tmp_name, self::path_from_file_id($fileid));
        else
            $success = move_uploaded_file($tmp_name, self::path_from_file_id($fileid));


        if ($success) {

            self::insert_upload_db_record($fileid, $filename);

            return true;
        }
        return false;
    }

    /**
     * @param $fileid
     * @param $filename
     */
    public static function insert_upload_db_record($fileid, $filename)
    {
        $sqli = DB::inst(SQLN_SITE);

        $md5 = md5_file(self::path_from_file_id($fileid));

        $sqli->query_write('
                REPLACE INTO `'.Table::Uploads.'`
                (`'.DBField::UPLOAD_ID.'`, `filename`, `md5`, `create_time`)
                VALUES (
                    "'.$sqli->escape_string($fileid).'",
                    "'.$sqli->escape_string($filename).'",
                    "'.$sqli->escape_string($md5).'",
                    NOW())
            ');
    }

    /**
     * Get file path for uploaded file
     *
     * @param int file id
     */
    public static function path_from_file_id($fileid)
    {
        global $CONFIG;
        return $CONFIG[ESCConfiguration::DIR_UPLOAD].'/'.$fileid;
    }

    /**
     * Get uploaded file info
     *
     * @param $fileid
     * @return array
     */
    public static function get_file_info($fileid)
    {
        $sqli = DB::inst(SQLN_SITE);

        $info = $sqli->query_first('
            SELECT `filename`, `md5`
            FROM `'.Table::Uploads.'`
            WHERE `'.DBField::UPLOAD_ID.'` = "'.$sqli->escape_string($fileid).'"
        ');

        return $info;
    }

    /**
     * Check uploaded file
     *
     * @param int file id
     */
    public static function check_file_id($fileid)
    {
        $sqli = DB::inst(SQLN_SITE);

        $field = $sqli->quote_field(DBField::UPLOAD_ID);
        $table = $sqli->quote_field(Table::Uploads);
        $file_id = $sqli->quote_value($fileid);

        $r = $sqli->query_first("
            SELECT {$field}
            FROM {$table}
            WHERE {$field} = {$file_id}
        ");

        return $r !== null;
    }

    /**
     * Delete uploaded file
     *
     * @param int file id
     */
    public static function delete_upload($fileid, $delete_file = true)
    {
        $sqli = DB::inst(SQLN_SITE);

        $r = $sqli->query_write('
            DELETE
            FROM `'.Table::Uploads.'`
            WHERE `'.DBField::UPLOAD_ID.'` = "'.$sqli->escape_string($fileid).'"
        ');

        if ($delete_file) {
            $path = self::path_from_file_id($fileid);
            if (is_file($path))
                $r = unlink($path);
            else
                $r = true;
        }

        return $r;
    }

    /**
     * Check upload errors
     *
     * @param string $_FILES array
     * @param integer file input field name
     */
    public static function check_upload_errors($files, $name)
    {
        switch ($files[$name]['error'])
        {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
                throw new UploadException('The uploaded file exceeds the upload_max_filesize directive ('.ini_get('upload_max_filesize').') in php.ini.', $files[$name]['error']);
                break;
            case UPLOAD_ERR_FORM_SIZE:
                throw new UploadException('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.', $files[$name]['error']);
                break;
            case UPLOAD_ERR_PARTIAL:
                throw new UploadException('The uploaded file was only partially uploaded.', $files[$name]['error']);
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new UploadException('No file was uploaded.', $files[$name]['error']);
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new UploadException('Missing a temporary folder.', $files[$name]['error']);
                break;
            case UPLOAD_ERR_CANT_WRITE:
                throw new UploadException('Failed to write file to disk.', $files[$name]['error']);
                break;
            case UPLOAD_ERR_EXTENSION:
                throw new UploadException('File upload stopped by extension.', $files[$name]['error']);
                break;
            default:
                throw new UploadException('An unknown file upload error occured: ', $files[$name]['error']);
        }
    }


    /**
     * Image/Download URL Generation
     *
     * @param $config
     * @param $path
     * @param $ip
     * @param string $mirror
     * @param null $timestamp
     * @return string
     */
    public static function get_url($path, $ip, $timestamp = null)
    {
        global $CONFIG;

        # current timestamp in hexa
        if ($timestamp === null)
            $timestamp = time();

        $timestamp += 3600*2;

        $path = '/secure'.$path;
        $checksum = md5($timestamp.' '.$path.' '.$ip.' '.$CONFIG[ESCConfiguration::MEDIA_KEY], true);
        $checksum = base64_encode($checksum);
        $checksum = str_replace("/", "_", $checksum);
        $checksum = str_replace("+", "-", $checksum);
        $checksum = str_replace("=", "", $checksum);

        if ($CONFIG[ESCConfiguration::IS_DEV])
            $relativePathPrefix = '';
        else
            $relativePathPrefix = '//'.$CONFIG[ESCConfiguration::WEBSITE_DOMAIN];

        $url = $relativePathPrefix.$path.'?md5='.$checksum.'&expires='.$timestamp;

        return $url;
    }
}
