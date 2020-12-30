<?php
/**
 * Picture system
 * wrapper around gd
 *
 */
class Image
{
    private $image;

    const FILE_TYPE_PNG = 'png';
    const FILE_TYPE_JPG = 'jpg';
    const FILE_TYPE_JPEG = 'jpeg';
    const FILE_TYPE_TIF = 'tif';
    const FILE_TYPE_TIFF = 'tiff';
    const FILE_TYPE_BMP = 'bmp';
    const FILE_TYPE_GIF = 'gif';
    const FILE_TYPE_PPM = 'ppm';

    protected static $acceptedFileTypes = [
        self::FILE_TYPE_JPEG,
        self::FILE_TYPE_JPG,
        self::FILE_TYPE_PNG,
        self::FILE_TYPE_TIF,
        self::FILE_TYPE_TIFF,
        self::FILE_TYPE_BMP,
        self::FILE_TYPE_GIF,
        self::FILE_TYPE_PPM
    ];

    /**
     * Constructor
     *
     * @param Image image
     */
    public function __construct($image)
    {
        $this->image = $image;
    }

    /**
     * @param $fileType
     * @return bool
     */
    public static function is_valid_file_type($fileType)
    {
        return in_array(strtolower($fileType), self::$acceptedFileTypes);
    }

    /*
     * Factories
     */

    /**
     * Factory which creates the gd picture from file
     *
     * @param string file path
     */
    public static function open($file)
    {
        $image = @imagecreatefromstring(file_get_contents($file));
        $c = __CLASS__;
        return new $c($image);
    }

    /**
     * Factory which creates the gd picture pointer
     *
     * @param integer $width
     * @param integer $height
     * @return Image
     */

    public static function create($width, $height)
    {
        $image = imagecreatetruecolor($width, $height);
        $c = __CLASS__;
        return new $c($image);
    }

    /**
     * Create an allocate color from html color (e.g. #cccccc)
     *
     * @param string $color
     * @return mixed
     */
    public function htmlcolor($color)
    {
        $len = strlen($color);

        if ($len == 7)
            $color = substr($color, 1);
        elseif ($len != 6)
            return false;

        if (!ereg("([a-fA-F0-9])+", $color))
            return false;

        $red   = hexdec(substr($color, 0, 2));
        $green = hexdec(substr($color, 2, 2));
        $blue  = hexdec(substr($color, 4, 2));

        return imagecolorallocate($this->image, $red, $green, $blue);
    }

    /**
     * Create a text in the current picture
     *
     * @param integer $size
     * @param integer $angle
     * @param integer $x
     * @param integer $y
     * @param mixed $color gd color pointer
     * @param string $fontfile
     * @param string $text
     * @return boolean
     */
    public function createText($size, $angle, $x, $y, $color, $fontfile, $text)
    {
        return imagefttext($this->image, $size, $angle, $x, $y, $color, $fontfile, $text);
    }

    /**
     * Save the picture to disk as jpg
     *
     * @param string $file path if needed
     * @param integer $quality 0 to 100
     * @return string data
     */
    public function save_jpg($file = '', $quality = 100)
    {
        return imagejpeg($this->image, $file, $quality);
    }

    /**
     * Save the picture to disk as png
     *
     * @param string file path if needed
     * @param integer quality 0 to 100
     * @param integer filters to use
     * @return string data
     */
    public function save_png($file = '', $quality = 6, $filters = PNG_NO_FILTER)
    {
        return imagepng($this->image, $file, $quality, $filters);
    }

    /**
     * Reduce the number of colors used by the image
     *
     * @param integer number of colors of the new image
     * @param boolean switch to enable/disable dither
     * @return boolean success
     */
    public function truecolor_to_palette($ncolor = 255, $dither = true)
    {
        return imagetruecolortopalette($this->image, $dither, $ncolor);
    }

    /**
     * Returns width of the image
     *
     * @return integer width
     */
    public function get_width()
    {
        return imagesx($this->image);
    }

    /**
     * Returns height of the image
     *
     * @return integer height
     */
    public function get_height()
    {
        return imagesy($this->image);
    }

    public function set_alpha_blending($bool)
    {
        return imagealphablending($this->image, $bool);
    }

    public function get_image()
    {
        return $this->image;
    }

    public function paste(Image $src_image, $dst_x, $dst_y, $src_x, $src_y,
        $dst_w, $dst_h, $src_w, $src_h)
    {
        return imagecopyresampled($this->image, $src_image->get_image(), $dst_x, $dst_y,
            $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
    }

    /**
     * Destructor
     * destroy gd pointer
     */
    public function __destruct()
    {
        if (isset($this->image))
            imagedestroy($this->image);
    }

}
