<?php

/**
* @package  HTML_BBCodeParser
*/

require_once "Youtube.php";

class bbFilter_YoutubeHomepage extends bbFilter_Youtube
{
    protected static $video_width = 300;
    protected static $video_height = 200;

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
        parent::$video_width = self::$video_width;
        parent::$video_height = self::$video_height;
        return parent::render_youtube($attributes);
    }
}