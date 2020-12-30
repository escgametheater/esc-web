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
// $Id: Email.php,v 1.5 2007/07/02 16:54:25 cweiske Exp $
//

/**
* @package  HTML_BBCodeParser
* @author   Stijn de Reede  <sjr@gmx.co.uk>
*/

class bbFilter_Email extends bbFilter
{

    /**
    * An array of tags parsed by the engine
    *
    * @var array
    */
    public $_definedTags = [
       'mail' => ['htmlopen'   => 'a',
                  'htmlclose'  => 'a',
                  'allowed'    => 'none^img',
                  'attributes' => ['mail' => 'href=%2$smailto:%1$s%2$s']
                 ]
    ];

    public function preparse($text)
    {
        $options = $this->_options;
        $o  = $options['open'];
        $c  = $options['close'];
        $oe = $options['open_esc'];
        $ce = $options['close_esc'];
        $pattern = ["!(^|\s)([-a-z0-9_.]+@[-a-z0-9.]+\.[a-z]{2,4})!i",
                    "!".$oe."mail(".$ce."|\s.*".$ce.")(.*)".$oe."/mail".$ce."!Ui"];
        $replace = ["\\1".$o."mail=\\2".$c."\\2".$o."/mail".$c,
                    $o."mail=\\2\\1\\2".$o."/mail".$c];
        return preg_replace($pattern, $replace, $text);
    }

}
