<?php

/**
* @package  HTML_BBCodeParser
*/

class bbFilter_User extends bbFilter
{
   /**
    * An array of tags parsed by the engine
    *
    * @var array
    */
    public $_definedTags = [
        'user' => [
            'allowed'    => 'none',
            'htmlopen'   => 'a',
            'htmlclose'  => 'a',
            'attributes' => [],
        ]
    ];

    public function __construct($options = [])
    {
        global $CONFIG;
        $this->_definedTags['user']['attributes']['user'] = 'href=%2$s/'.SECTION_USERS.'/%1$s%2$s';
        parent::__construct($options);
    }

}