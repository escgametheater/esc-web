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
// $Id: Extended.php,v 1.3 2007/07/02 16:54:25 cweiske Exp $
//

/**
* @package  HTML_BBCodeParser
* @author   Stijn de Reede  <sjr@gmx.co.uk>
*/

class bbFilter_Extended extends bbFilter
{

    /**
    * An array of tags parsed by the engine
    *
    * @access   private
    * @var      array
    */
    public $_definedTags = [
                                'important' => ['htmlopen'  => 'div class="important"',
                                                'htmlclose' => 'div',
                                                'allowed'   => 'all',
                                                'attributes'=> []],
                                'color' => ['htmlopen'  => 'span',
                                            'htmlclose' => 'span',
                                            'allowed'   => 'all',
                                            'attributes'=> ['color' => 'style=%2$scolor:%1$s%2$s']],
                                'size' => ['htmlopen'  => 'span',
                                           'htmlclose' => 'span',
                                           'allowed'   => 'all',
                                           'attributes'=> ['size' => 'style=%2$sfont-size:%1$s%2$s']],
                                'sizept' => ['htmlopen'   => 'span',
                                             'htmlclose'  => 'span',
                                             'allowed'    => 'all',
                                             'attributes' => ['size' => 'style=%2$sfont-size:%1$spt%2$s']],
                                'font' => ['htmlopen'  => 'span',
                                           'htmlclose' => 'span',
                                           'allowed'   => 'all',
                                           'attributes'=> ['font' => 'style=%2$sfont-family:%1$s%2$s']],
                                'center' => ['htmlopen'  => 'div style="text-align: center;"',
                                             'htmlclose' => 'div',
                                             'allowed'   => 'all'],
                                'left' => ['htmlopen'  => 'div style="text-align: left;"',
                                           'htmlclose' => 'div',
                                           'allowed'   => 'all'],
                                'right' => ['htmlopen'  => 'div style="text-align: right;"',
                                            'htmlclose' => 'div',
                                            'allowed'   => 'all'],
                                'align' => ['htmlopen'  => 'div',
                                            'htmlclose' => 'div',
                                            'allowed'   => 'all',
                                            'attributes'=> ['align' => 'style=%2$stext-align:%1$s%2$s']],
                                'quote' => ['htmlopen'        => 'q',
                                            'htmlclose'       => 'q',
                                            'prepend'         => '<div>Quote by {arg}:</div>',
                                            'prepend_default' => '<div>Quote:</div>',
                                            'allowed'         => 'all',
                                            'attributes'      => ['quote' => 'cite=%2$s%1$s%2$s']],
                                'code' => ['htmlopen'   => 'code',
                                           'htmlclose'  => 'code',
                                           'allowed'    => 'all',
                                           'attributes' => []],
                                'h1' => ['htmlopen'   => 'h1',
                                         'htmlclose'  => 'h1',
                                         'allowed'    => 'all',
                                         'attributes' => []],
                                'h2' => ['htmlopen'   => 'h2',
                                         'htmlclose'  => 'h2',
                                         'allowed'    => 'all',
                                         'attributes' => []],
                                'h3' => ['htmlopen'   => 'h3',
                                         'htmlclose'  => 'h3',
                                         'allowed'    => 'all',
                                         'attributes' => []],
                                'h4' => ['htmlopen'   => 'h4',
                                         'htmlclose'  => 'h4',
                                         'allowed'    => 'all',
                                         'attributes' => []],
                                'h5' => ['htmlopen'   => 'h5',
                                         'htmlclose'  => 'h5',
                                         'allowed'    => 'all',
                                         'attributes' => []],
                                'h6' => ['htmlopen'   => 'h6',
                                         'htmlclose'  => 'h6',
                                         'allowed'    => 'all',
                                         'attributes' => []],
    ];
}
