<?php

/**
* @package  HTML_BBCodeParser
*/

class bbFilter_News extends bbFilter
{
   public $_definedTags = [
       'endhomepage'   => [
           'allowed'   => 'none',
           'type'      => BB_HIDDEN_TAG, // do not display
            ]
   ];
}