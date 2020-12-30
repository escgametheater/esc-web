<?php

/**
* @package  HTML_BBCodeParser
*/

class bbFilter_Spoiler extends bbFilter
{
   /**
    * An array of tags parsed by the engine
    *
    * @var array
    */
    public $_definedTags = [
        'spoiler' => [
            'allowed'    => 'all',
            'type'       => BB_CUSTOM_TAG,
            'class'      => __CLASS__,
            'render'     => 'render_spoiler',
            'htmlclose'  => 'div',
            'attributes' => [
                'spoiler' => '',
                'text'    => '',
            ]
        ]
    ];

    public function preparse($text)
    {
        return $text;
    }

    public static function render_spoiler($attributes)
    {
       return '<div>Spoiler: <a onclick="$(this).parent().next().toggle(); return false;">'.array_get($attributes, 'spoiler', 'Click to Show/Hide').'</a></div><div style="display: none; padding: 3px;">';
    }

}