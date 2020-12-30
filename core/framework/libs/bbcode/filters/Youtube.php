<?php

/**
* @package  HTML_BBCodeParser
*/

class bbFilter_Youtube extends bbFilter
{
    protected static $video_width;
    protected static $video_height;


    /**
    * An array of tags parsed by the engine
    *
    * @var array
    */
    public $_definedTags = [
        'youtube' => [
            'allowed'    => 'none',
            'type'       => BB_CUSTOM_TAG,
            'class'      => __CLASS__,
            'render'     => 'render_youtube',
            'attributes' => [
                'id'   => 'src=%2$s%1$s%2$s',
                'w'    => 'width=%2$s%1$d%2$s',
                'h'    => 'height=%2$s%1$d%2$s',
            ]
        ]
    ];

    public static function render_youtube($attributes)
    {
       if (!array_key_exists('id', $attributes))
            return '';

       if (!isset(self::$video_width))
           $width = array_get($attributes, 'w', 425);
       else
           $width = self::$video_width;

       if (!isset(self::$video_height))
           $height = array_get($attributes, 'h', 344);
       else
           $height = self::$video_height;

       if (!Validators::int($width)
        || !Validators::int($height)
        || !preg_match('/^[a-zA-Z0-9_-]+$/', $attributes['id'])) {
           return '';
       }
       return '<object width="'.$width.'" height="'.$height.'"><param name="movie" value="http://www.youtube.com/v/'.$attributes['id'].'&hl=en"></param><param name="allowFullScreen" value="true"></param><param value="transparent" name="wmode"/><embed src="http://www.youtube.com/v/'.$attributes['id'].'&hl=en" wmode="transparent" type="application/x-shockwave-flash" allowfullscreen="true" width="'.$width.'" height="'.$height.'"></embed></object>';
    }

}