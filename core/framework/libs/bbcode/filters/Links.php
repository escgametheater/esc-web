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
// $Id: Links.php,v 1.12 2007/07/02 18:26:17 cweiske Exp $
//

/**
* @package  HTML_BBCodeParser
* @author   Stijn de Reede  <sjr@gmx.co.uk>
*/

/**
 *
 */
class bbFilter_Links extends bbFilter
{
    /**
     * List of allowed schemes
     *
     * @var array
     */
    private $_allowedSchemes = ['http', 'https', 'ftp'];

    /**
     * Default scheme
     *
     * @var string
     */
    private $_defaultScheme = 'http';

    /**
     * An array of tags parsed by the engine
     *
     * @access   private
     * @var      array
     */
    public $_definedTags = [
        'url' => [
            'htmlopen'   => 'a rel="nofollow"',
            'htmlclose'  => 'a',
            'allowed'    => 'none^img',
            'attributes' => ['url' => 'href=%2$s%1$s%2$s']
        ]
    ];

    public function preparse($text)
    {
        $options = $this->_options;
        $o  = $options['open'];
        $c  = $options['close'];
        $oe = $options['open_esc'];
        $ce = $options['close_esc'];

        $schemes = implode('|', $this->_allowedSchemes);

        $pattern = ["/(?<![\"'=".$ce."\/])(".$oe."[^".$ce."]*".$ce.")?(((".$schemes."):\/\/|www)[@-a-z0-9.]+\.[a-z]{2,4}[^\s()\[\]]*)/i",
                    "!".$oe."url(".$ce."|\s.*".$ce.")(.*)".$oe."/url".$ce."!iU",
                    "!".$oe."url=((([a-z]*:(//)?)|www)[@-a-z0-9.]+)([^\s\[\]]*)".$ce."(.*)".$oe."/url".$ce."!i"];

        $pp = preg_replace_callback($pattern[0], [$this, 'smarterPPLinkExpand'], $text);
        $pp = preg_replace($pattern[1], $o."url=\$2\$1\$2".$o."/url".$c, $pp);
        return preg_replace_callback($pattern[2], [$this, 'smarterPPLink'], $pp);
    }

    /**
     * Intelligently expand a URL into a link
     *
     * @return  string
     * @author  Seth Price <seth@pricepages.org>
     * @author  Lorenzo Alberton <l.alberton@quipo.it>
     */
    private function smarterPPLinkExpand($matches)
    {
        $options = $this->_options;
        $o = $options['open'];
        $c = $options['close'];

        //If we have an intro tag that is [url], then skip this match
        if ($matches[1] == $o.'url'.$c) {
            return $matches[0];
        }

        $punctuation = '.,;:'; // Links can't end with these chars
        $trailing = '';
        // Knock off ending punctuation
        $last = substr($matches[2], -1);
        while (strpos($punctuation, $last) !== false) {
            // Last character is punctuation - remove it from the url
            $trailing = $last.$trailing;
            $matches[2] = substr($matches[2], 0, -1);
            $last = substr($matches[2], -1);
        }

        $off = strpos($matches[2], ':');

        //Is a ":" (therefore a scheme) defined?
        if ($off === false) {
            /*
             * Create a link with the default scheme of http. Notice that the
             * text that is viewable to the user is unchanged, but the link
             * itself contains the "http://".
             */
            return $matches[1].$o.'url='.$this->_defaultScheme.'://'.$matches[2].$c.$matches[2].$o.'/url'.$c.$trailing;
        }

        $scheme = substr($matches[2], 0, $off);

        /*
         * If protocol is in the approved list than allow it. Note that this
         * check isn't really needed, but the created link will just be deleted
         * later in smarterPPLink() if we create it now and it isn't on the
         * scheme list.
         */
        if (in_array($scheme, $this->_allowedSchemes)) {
            return $matches[1].$o.'url'.$c.$matches[2].$o.'/url'.$c.$trailing;
        }

        return $matches[0];
    }

    /**
     * Finish preparsing URL to clean it up
     *
     * @return  string
     * @author  Seth Price <seth@pricepages.org>
     */
    private function smarterPPLink($matches)
    {
        $options = $this->_options;
        $o = $options['open'];
        $c = $options['close'];

        $urlServ = $matches[1];
        $path = $matches[5];

        $off = strpos($urlServ, ':');

        if ($off === false) {
            //Default to http
            $urlServ = $this->_defaultScheme.'://'.$urlServ;
            $off = strpos($urlServ, ':');
        }

        //Add trailing slash if missing (to create a valid URL)
        if (!$path) {
            $path = '/';
        }

        $protocol = substr($urlServ, 0, $off);

        if (in_array($protocol, $this->_allowedSchemes)) {
            //If protocol is in the approved list than allow it
            return $o.'url='.$urlServ.$path.$c.$matches[6].$o.'/url'.$c;
        }

        //Else remove url tag
        return $matches[6];
    }
}
