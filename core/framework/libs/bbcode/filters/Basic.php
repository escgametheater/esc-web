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
// $Id: Basic.php,v 1.6 2007/07/02 16:54:25 cweiske Exp $
//

/**
* @package  HTML_BBCodeParser
* @author   Stijn de Reede  <sjr@gmx.co.uk>
*/

class bbFilter_Basic extends bbFilter
{

    /**
    * An array of tags parsed by the engine
    *
    * @var array
    */
    public $_definedTags = ['b' => ['htmlopen'  => 'strong',
                                    'htmlclose' => 'strong',
                                    'allowed'   => 'all',
                                    'attributes'=> []],
                            'i' => ['htmlopen'  => 'em',
                                    'htmlclose' => 'em',
                                    'allowed'   => 'all',
                                    'attributes'=> []],
                            'o' => ['htmlopen'  => 'span class="overline"',
                                    'htmlclose' => 'span',
                                    'allowed'   => 'all',
                                    'attributes'=> []],
                            'u' => ['htmlopen'  => 'span style="text-decoration:underline;"',
                                    'htmlclose' => 'span',
                                    'allowed'   => 'all',
                                    'attributes'=> []],
                            's' => ['htmlopen'  => 'del',
                                    'htmlclose' => 'del',
                                    'allowed'   => 'all',
                                    'attributes'=> []],
                          'sub' => ['htmlopen'  => 'sub',
                                    'htmlclose' => 'sub',
                                    'allowed'   => 'all',
                                    'attributes'=> []],
                          'sup' => ['htmlopen'  => 'sup',
                                    'htmlclose' => 'sup',
                                    'allowed'   => 'all',
                                    'attributes'=> []],
                            ];

}
