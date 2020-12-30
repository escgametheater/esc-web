<?php
/**
 * File class
 * wrapper around file functions
 *
 */
class File
{
    /**
     * Path
     *
     * @var string
     */
    private $path;

    /**
     * File pointer
     *
     * @var mixed
     */
    private $fp;

    /**
     * Lock files during read/write
     *
     * @access private
     * @var boolean
     */
    private $fileLocking = true;

    /**
     * Constructor with path
     *
     * @param string File path
     * @param enable/disable locking
     * @param boolean enable/disable lock functions
     */
    public function __construct($path, $locking = true)
    {
        if (!is_string($path) || strlen($path) == 0)
            elog("Invalid Path: $path");

        $this->path = $path;
        $this->fileLocking = $locking;
    }

    /**
     * Check if the file exists
     *
     * @return boolean
     */
    public function exists()
    {
        return file_exists($this->path);
    }

    /**
     * Open file
     *
     * @param string open mode
     * @return boolean success
     */
    public function open($tag = 'r')
    {
        $this->fp = fopen($this->path, $tag);

        if (!$this->fp)
            elog("Failed to open file ".$this->path);
            return false;

        return true;
    }

    /**
     * Change position of file pointer
     *
     * @param integer position to seek to
     * @return boolean success
     */
    public function seek($pos)
    {
        if (!$this->fp)
            elog("Tried seek before opening file");

        return fseek($this->fp, $pos);
    }

    /**
     * Shared lock file
     *
     * @return boolean success
     */
    public function lockread()
    {
        if (!$this->fp)
            elog("Tried lock (shared) before opening file", LOG_DEFAULT);

        return $this->fileLocking ? flock($this->fp, LOCK_SH + LOCK_NB) : true;
    }

    /**
     * Exclusive lock file
     *
     * @return boolean success
     */
    public function lockwrite()
    {
        if (!$this->fp)
            elog("Tried lock (exclusive) before opening file", LOG_DEFAULT);

        return $this->fileLocking ? flock($this->fp, LOCK_EX + LOCK_NB) : true;
    }

    /**
     * Unlock file
     *
     * @return boolean success
     */
    public function unlock()
    {
        if (!$this->fp)
            elog("Tried unlock before opening file", LOG_DEFAULT);

        return $this->fileLocking ? flock($this->fp, LOCK_UN) : true;
    }

    /**
     * Close file
     *
     * @return string
     */
    public function close()
    {
        if ($this->fp) {
            fclose($this->fp);
            unset($this->fp);
            return true;
        }
        return false;
    }

    /**
     * Read files data
     *
     * @param integer number bytes to read
     * @return string
     */
    public function read($size = 0)
    {
        if ($size == 0)
            $size = filesize($this->path);

        return fread($this->fp, $size);
    }

    /**
     * Write data
     *
     * @param string $data
     * @return boolean
     */
    public function write($data)
    {
        return fwrite($this->fp, $data);
    }

    /**
     * Erase the file
     *
     * @return boolean
     */
    public function delete()
    {
        if (unlink($this->path) == false) {
            elog('Failed to delete file: '.$this->path, LOG_DEFAULT);
            return false;
        }
        return true;
    }
}
