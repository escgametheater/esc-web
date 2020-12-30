<?php
/**
 * Thumbnails Helper
 * handles images:
 * - resizing
 * - thumbnails
 *
 * @package helpers
 */

class ImageResizeException extends Exception {}

class ThumbnailsHelper
{
    public static function compute_height($width, $height, $new_width)
    {
        return round($height * $new_width / $width);
    }

    public static function compute_width($width, $height, $new_height)
    {
        return round($width * $new_height / $height);
    }

    /**
     * Resize a screen
     *
     * @param string $from
     * @param string $to
     * @param integer $new_width
     * @return boolean
     */
    public static function resize($from, $to, $new_width = null, $new_height = null)
    {
        if (!file_exists($from))
            return false;

        $img_src = Image::open($from);
        $width   = $img_src->get_width();
        $height  = $img_src->get_height();

        list($new_width, $new_height) = self::compute_size($width, $height, $new_width, $new_height);

        $img_dest = Image::create($new_width, $new_height);
        $img_dest->set_alpha_blending(false);
        $img_dest->paste($img_src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        //$img_dest->truecolor_to_palette(64);
        //return $img_dest->save_jpg($to, 95);
        return $img_dest->save_png($to, 9, PNG_ALL_FILTERS);
    }

    public static function compute_size($width, $height, $new_width = null, $new_height = null)
    {
        if ($new_width === null && $new_height !== null) {
            $new_width = self::compute_width($width, $height, $new_height);
        } elseif ($new_height === null && $new_width !== null) {
            $new_height = self::compute_height($width, $height, $new_width);
        } elseif ($new_width === null && $new_height === null) {
            $new_height = $height;
            $new_width = $width;
        }
        return [$new_width, $new_height];
    }

    /**
     * Extract dimensions of a ppm image
     *
     * @param string $filepath
     *
     * @return [$width, $height]
     *
     * @throws ImageResizeException
     */
    public static function getPpmSize($filepath)
    {
        $cmd = 'head -n2 '. escapeshellarg($filepath);
        $output = [];
        $r = false;
        exec($cmd, $output, $r);
        if ($r != 0) {
            throw new ImageResizeException("failed to get size for $filepath");
        }
        return explode(' ', $output[1], 2);
    }


    /**
     * Extract dimensions of a picture
     *
     * @param string $filepath
     *
     * @return [$width, $height]
     */
    public static function getImageSize($filepath)
    {
        $extension  = FilesToolkit::get_file_extension($filepath);
        if ($extension == 'ppm') {
            $sizes = self::getPpmSize($filepath);
        } else {
            $sizes = getimagesize($filepath);
        }
        return $sizes;
    }

    public static function resize_imagemagick($from, $to, $new_width = null, $new_height = null,
        $quality = 95, $colors = null, $format = Image::FILE_TYPE_PNG)
    {
        list($width, $height) = ThumbnailsHelper::getImageSize($from);
        if ($width == 0 && $height == 0)
            return 1;

        list($new_width, $new_height) = self::compute_size($width, $height, $new_width, $new_height);

        $escaped_source = escapeshellarg($from."[0]");
        $escaped_dest = escapeshellarg($to);

        global $CONFIG;
        $cmd = $CONFIG['convert_path']." $escaped_source"
            ." -resize ${new_width}x${new_height}\>"
            ." -quality $quality -depth 8"
            .($colors ? " -colors 64" : "")
            ." -strip ".($format ? $format.":" : "").$escaped_dest
            ." 2>&1";

        $out = exec($cmd, $output, $r);
        if ($r != 0 || is_file($to) == false)
            throw new ImageResizeException('failed to generate image '.$r.': '.$out.' for '.$cmd);

        return $out;
    }


    /**
     * @param string $filename
     *
     * @return array
     *
     */
    public static function getImageDpi($filename) {
        if ($filename && file_exists($filename)) {
            $fileMime = self::getFileMimeType($filename);

            if ($fileMime == 'image/jpeg; charset=binary'
                || $fileMime == 'image/jpg; charset=binary'
                || $fileMime == 'image/jpeg2; charset=binary') {
                return self::getImageJpgDpi($filename);
            } elseif ($fileMime == 'image/png; charset=binary') {
                return self::getImagePngDpi($filename);
            } else {
                return ['x' => 72, 'y' => 72];
            }
        } else {
            return null;
        }
    }

    /**
     * Uses unix command file -bi for determining file typ.
     *
     * jpeg = image/jpeg; charset=binary
     * gif = image/gif; charset=binary
     * pdf = application/pdf; charset=binary
     *
     * @static
     *
     * @param string $absolutePath
     *
     * @throws Exception
     *
     * @return string|bool
     */
    public static function getFileMimeType($absolutePath) {
        if ($absolutePath) {
            if (file_exists($absolutePath)) {
                if ($return = exec('file -bi — ' . escapeshellarg($absolutePath))) {
                    return $return;
                }
            }
        } else {
            throw new Exception('Invalid parameters for ' . __FUNCTION__ .
                ' in ' . __FILE__);
        }

        return false;
    }

    /**
     * @param string $filename
     *
     * @return array
     *
     * @throws Exception
     */
    public static function getImageJpgDpi($filename) {
        if ($filename && file_exists($filename)) {
            $dpi = 0;
            $fp = @fopen($filename, 'rb');

            if ($fp) {
                if (fseek($fp, 6) == 0) {
                    if (($bytes = fread($fp, 16)) !== false) {
                        if (substr($bytes, 0, 4) == 'JFIF') {
                            $JFIF_density_unit = ord($bytes[7]);
                            $JFIF_X_density = ord($bytes[8]) * 256 + ord($bytes[9]);
                            $JFIF_Y_density = ord($bytes[10]) * 256 + ord($bytes[11]);

                            if ($JFIF_X_density == $JFIF_Y_density) {
                                if ($JFIF_density_unit == 1) {
                                    $dpi = $JFIF_X_density;
                                } elseif ($JFIF_density_unit == 2) {
                                    $dpi = $JFIF_X_density * 2.54;
                                }
                            }
                        }
                    }
                }
                fclose($fp);
            }

            if (empty($dpi)) {
                if ($exifDpi = self::getImageDpiFromExif($filename)) {
                    $dpi = $exifDpi;
                }
            }

            if ($dpi) {
                return ['x' => $dpi, 'y' => $dpi];
            } else {
                return ['x' => 72, 'y' => 72];
            }
        } else {
            throw new Exception('Invalid parameters');
        }
    }

    /**
     * @static
     *
     * @param string $filename
     *
     * @return array
     *
     * @throws Exception
     */
    public static function getImagePngDpi($filename) {
        if ($filename && file_exists($filename)) {
            $fh = fopen($filename, 'rb');

            if (!$fh) {
                return false;
            }

            $buf = [];

            $x = 0;
            $y = 0;
            $units = 0;

            while (!feof($fh)) {
                array_push($buf, ord(fread($fh, 1)));
                if (count($buf) > 13) {
                    array_shift($buf);
                }

                if (count($buf) < 13) {
                    continue;
                }

                if ($buf[0] == ord('p') &&
                    $buf[1] == ord('H') &&
                    $buf[2] == ord('Y') &&
                    $buf[3] == ord('s')) {
                    $x = ($buf[4] << 24) + ($buf[5] << 16) + ($buf[6] << 8) + $buf[7];
                    $y = ($buf[8] << 24) + ($buf[9] << 16) + ($buf[10] << 8) + $buf[11];
                    $units = $buf[12];
                    break;
                }
            }

            fclose($fh);

            if ($x != false && $units == 1) {
                $x = round($x * 0.0254);
            }

            if ($y != false && $units == 1) {
                $y = round($y * 0.0254);
            }

            if (empty($x) && empty($y)) {
                if ($exifDpi = self::getImageDpiFromExif($filename)) {
                    $x = $exifDpi;
                    $y = $exifDpi;
                }
            }

            if (!empty($x) || !empty($y)) {
                return ['x' => $x, 'y' => $y];
            } else {
                return ['x' => 72, 'y' => 72];
            }
        } else {
            throw new \Exception('Invalid parameters');
        }
    }

    /**
     * Read EXIF data.
     *
     * @static
     *
     * @param string $filename
     *
     * @throws \Exception
     *
     * @return bool|float
     */
    public static function getImageDpiFromExif($filename) {
        if ($filename && file_exists($filename)) {
            if (function_exists('exif_read_data')) {
                if ($exifData = @exif_read_data($filename)) {
                    if (isset($exifData['XResolution'])) {
                        if (strpos($exifData['XResolution'], '/') !== false) {
                            if ($explode = explode('/', $exifData['XResolution'])) {
                                return (float) ((int) $explode[0] / (int) $explode[1]);
                            }
                        } else {
                            return (int) $exifData['XResolution'];
                        }
                    } elseif (isset($exifData['YResolution'])) {
                        if (strpos($exifData['YResolution'], '/') !== false) {
                            if ($explode = explode('/', $exifData['YResolution'])) {
                                return (float) ((int) $explode[0] / (int) $explode[1]);
                            }
                        } else {
                            return (int) $exifData['YResolution'];
                        }
                    }
                }
            } else {
                throw new Exception('Incompatible system.');
            }
        } else {
            throw new Exception('Invalid parameters.');
        }

        return false;
    }
}
