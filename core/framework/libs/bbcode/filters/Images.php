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
// $Id: Images.php,v 1.8 2007/07/02 17:44:47 cweiske Exp $
//

/**
* @package  HTML_BBCodeParser
* @author   Stijn de Reede  <sjr@gmx.co.uk>
*/

class bbFilter_Images extends bbFilter
{

    /**
    * An array of tags parsed by the engine
    *
    * @var      array
    */
    public $_definedTags = [
        'img' => [
            'htmlopen'   => 'img',
            'allowed'    => 'none',
            'attributes' => [
                'img'    => 'src=%2$s%1$s%2$s',
                'w'      => 'width=%2$s%1$d%2$s',
                'h'      => 'height=%2$s%1$d%2$s',
                'width'  => 'width=%2$s%1$d%2$s',
                'height' => 'height=%2$s%1$d%2$s',
                'alt'    => 'alt=%2$s%1$s%2$s',
            ]
        ]
    ];


    public function preparse($text)
        {
        $options = $this->_options;
        $o  = $options['open'];
        $c  = $options['close'];
        $oe = $options['open_esc'];
        $ce = $options['close_esc'];
//        return preg_replace(
//            "!".$oe."img(\s?.*)".$ce."(.*)".$oe."/img".$ce."!Ui",
//            $o."img=\"\$2\"\$1".$c.$o."/img".$c,
//            $text);

        return preg_replace(
            "!".$oe."img(\s?.*)".$ce."(.*)".$oe."/img".$ce."!Ui",
            $o."img=\"\$2\" alt=\"\"\$1".$c.$o."/img".$c,
            $text);
    }

}