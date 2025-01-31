<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Stijn de Reede <sjr@gmx.co.uk>                               |
// +----------------------------------------------------------------------+
//
// $Id: Lists.php,v 1.5 2007/07/02 16:54:25 cweiske Exp $
//


/**
* @package  HTML_BBCodeParser
* @author   Stijn de Reede  <sjr@gmx.co.uk>
*/

/**
 *
 */
class bbFilter_Lists extends bbFilter
{

    /**
    * An array of tags parsed by the engine
    *
    * @var array
    */
    public $_definedTags = ['list'  => ['htmlopen'  => 'ol',
                                        'htmlclose' => 'ol',
                                        'allowed'   => 'all',
                                        'child'     => 'none^li',
                                        'attributes'=> ['list' => 'style=%2$slist-style-type:%1$s;%2$s']
                                        ],
                            'ulist' => ['htmlopen'  => 'ul',
                                        'htmlclose' => 'ul',
                                        'allowed'   => 'all',
                                        'child'     => 'none^li',
                                        'attributes'=> ['list' => 'style=%2$slist-style-type:%1$s;%2$s']
                                        ],
                            'li'    => ['htmlopen'   => 'li',
                                        'htmlclose'  => 'li',
                                        'allowed'    => 'all',
                                        'parent'     => 'none^ulist,list',
                                        'attributes' => []
                                        ]
                              ];

    public function preparse($text)
    {
        $options = $this->_options;
        $o = $options['open'];
        $c = $options['close'];
        $oe = $options['open_esc'];
        $ce = $options['close_esc'];

        $pattern = ["!".$oe."\*".$ce."!",
                    "!".$oe."(u?)list=(?-i:A)(\s*[^".$ce."]*)".$ce."!i",
                    "!".$oe."(u?)list=(?-i:a)(\s*[^".$ce."]*)".$ce."!i",
                    "!".$oe."(u?)list=(?-i:I)(\s*[^".$ce."]*)".$ce."!i",
                    "!".$oe."(u?)list=(?-i:i)(\s*[^".$ce."]*)".$ce."!i",
                    "!".$oe."(u?)list=(?-i:1)(\s*[^".$ce."]*)".$ce."!i",
                    "!".$oe."(u?)list([^".$ce."]*)".$ce."!i"];

        $replace = [$o."li".$c,
                    $o."\$1list=upper-alpha\$2".$c,
                    $o."\$1list=lower-alpha\$2".$c,
                    $o."\$1list=upper-roman\$2".$c,
                    $o."\$1list=lower-roman\$2".$c,
                    $o."\$1list=decimal\$2".$c,
                    $o."\$1list\$2".$c ];

        return preg_replace($pattern, $replace, $text);
    }
}
