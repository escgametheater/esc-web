<?php

/**
* @package  HTML_BBCodeParser
*/

class bbFilter_BlueDragon extends bbFilter
{
    /**
    * An array of tags parsed by the engine
    *
    * @var array
    */
    public $_definedTags = [
        'bluedragon' => [
            'allowed'    => 'none',
            'type'       => BB_CUSTOM_TAG,
            'class'      => __CLASS__,
            'render'     => 'render_bluedragon',
            'attributes' => [],
        ]
    ];

    public static function render_bluedragon($attributes)
    {
       return ' <object width="324" height="254" type="application/x-shockwave-flash" quality="high" id=<<<REDACTED>>>" data="<<<REDACTED>>>" allowScriptAccess="always" allowNetworking="all" pluginspage="http://www.macromedia.com/go/getflashplayer"><param value="<<<REDACTED>>>" name="movie"/><param name="wmode" value="transparent"/><param name="allowScriptAccess" value="always"/></object>';
    }

}