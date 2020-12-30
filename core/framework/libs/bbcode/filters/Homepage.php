<?php

/**
* @package  HTML_BBCodeParser
*/

class bbFilter_Homepage extends bbFilter
{
   public $_definedTags = [
       'endhomepage'   => [
           'allowed'   => 'none',
           'type'      => BB_END_TAG
        ]
   ];
}