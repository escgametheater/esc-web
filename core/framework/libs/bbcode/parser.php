<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                        |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,          |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                                   |
// | http://www.php.net/license/2_02.txt.                                             |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to              |
// | license@php.net so we can mail you a copy immediately.                   |
// +----------------------------------------------------------------------+
// | Author: Stijn de Reede <sjr@gmx.co.uk>                                       |
// +----------------------------------------------------------------------+
//
// $Id: BBCodeParser.php,v 1.17 2007/07/02 18:46:30 cweiske Exp $
//

/**
* @package  HTML_BBCodeParser
* @author   Stijn de Reede   <sjr@gmx.co.uk>
*
*
* This is a parser to replace UBB style tags with their html equivalents. It
* does not simply do some regex calls, but is complete stack based
* parse engine. This ensures that all tags are properly nested, if not,
* extra tags are added to maintain the nesting. This parser should only produce
* xhtml 1.0 compliant code. All tags are validated and so are all their attributes.
* It should be easy to extend this parser with your own tags, see the _definedTags
* format description below.
*
*
* Usage:
* $parser = new HTML_BBCodeParser();
* $parser->setText('normal [b]bold[/b] and normal again');
* $parser->parse();
* echo $parser->getParsed();
* or:
* $parser = new HTML_BBCodeParser();
* echo $parser->qparse('normal [b]bold[/b] and normal again');
* or:
* echo HTML_BBCodeParser::staticQparse('normal [b]bold[/b] and normal again');
*
*
* Setting the options from the ini file:
* $config = parse_ini_file('BBCodeParser.ini', true);
* $options = &PEAR::getStaticProperty('HTML_BBCodeParser', '_options');
* $options = $config['HTML_BBCodeParser'];
* unset($options);
*
*
* The _definedTags variables should be in this format:
* ['tag'                                           // the actual tag used
*               =>  ['htmlopen' => 'open',  // the opening tag in html
*                           'htmlclose' => 'close', // the closing tag in html,
*                                                               can be set to an empty string
*                                                               if no closing tag is present
*                                                               in html (like <img>)
*                           'allowed'   => 'allow', // tags that are allowed inside
*                                                               this tag. Values can be all
*                                                               or none, or either of these
*                                                               two, followed by a ^ and then
*                                                               followed by a comma seperated
*                                                               list of exceptions on this
*                           'attributes' => [] // an associative array containing
*                                                               the tag attributes and their
*                                                               printf() html equivalents, to
*                                                               which the first argument is
*                                                               the value, and the second is
*                                                               the quote. Default would be
*                                                               something like this:
*                                                               'attr' => 'attr=%2$s%1$s%2$s'
*                         ],
*         'etc'
*               => (...)
*         ]
*/

define('BB_TEXT', 0);
define('BB_OPENING_TAG', 1);
define('BB_CLOSING_TAG', 2);
define('BB_CUSTOM_TAG', 3);
define('BB_HIDDEN_TAG', 4);
define('BB_END_TAG', 5);

class bbParser
{
     /**
      * An array of tags parsed by the engine, should be overwritten by filters
      *
      * @var          array
      */
     public $_definedTags = [];

     /**
      * A string containing the input
      *
      * @var string
      */
     protected $_text = '';

     /**
      * A string containing the preparsed input
      *
      * @var string
      */
     protected $_preparsed = '';

     /**
      * An array tags and texts build from the input text
      *
      * @var array
      */
     private $_tagArray = [];

     /**
      * A string containing the parsed version of the text
      *
      * @var          string
      */
     protected $_parsed = '';

     /**
      * An array of options, filled by an ini file or through the contructor
      *
      * @var          array
      */
     protected $_options = [
          'quotestyle'      => 'double',
          'quotewhat'       => 'all',
          'open'            => '[',
          'close'           => ']',
          'xmlclose'        => true,
          'filters'         => 'Basic'
     ];

     /**
      * An array of filters used for parsing
      *
      * @var          array
      */
     private $_filters = [];

     /**
      * Constructor, initialises the options and filters
      *
      * Sets the private variable _options with base options defined with
      * &PEAR::getStaticProperty(), overwriting them with (if present)
      * the argument to this method.
      * Then it sets the extra options to properly escape the tag
      * characters in preg_replace() etc. The set options are
      * then stored back with &PEAR::getStaticProperty(), so that the filter
      * classes can use them.
      * All the filters in the options are initialised and their defined tags
      * are copied into the private variable _definedTags.
      *
      * @param    array             options to use, can be left out
      * @return   none
      * @author   Stijn de Reede    <sjr@gmx.co.uk>
      */
     public function __construct($options = [])
     {
          // set the options passed as an argument
          foreach ($options as $k => $v)     {
                $this->_options[$k] = $v;
          }

          // add escape open and close chars to the options for preg escaping
          $preg_escape = '\^$.[]|()?*+{}';
          if ($this->_options['open'] != '' && strpos($preg_escape, $this->_options['open'])) {
                $this->_options['open_esc'] = "\\".$this->_options['open'];
          } else {
                $this->_options['open_esc'] = $this->_options['open'];
          }
          if ($this->_options['close'] != '' && strpos($preg_escape, $this->_options['close'])) {
                $this->_options['close_esc'] = "\\".$this->_options['close'];
          } else {
                $this->_options['close_esc'] = $this->_options['close'];
          }

          // extract the definedTags from subclasses */
          $this->addFilters($this->_options['filters']);
     }

     /**
      * Option setter
      *
      * @param string option name
      * @param mixed    option value
      * @author Lorenzo Alberton <l.alberton@quipo.it>
      */
     public function setOption($name, $value)
     {
          $this->_options[$name] = $value;
     }

     /**
      * Add a new filter
      *
      * @param string filter
      * @author Lorenzo Alberton <l.alberton@quipo.it>
      */
     public function addFilter($filter)
     {
          $filter = ucfirst(trim($filter));
          if (!array_key_exists($filter, $this->_filters))
          {
                $class = 'bbFilter_'.$filter;
                global $FRAMEWORK_DIR;
                include_once $FRAMEWORK_DIR.'/libs/bbcode/filters/'.$filter.'.php';
                if (!class_exists($class))
                {
                     elog("BBCode Parser: Failed to load filter $filter");
                     return false;
                }
                $this->_filters[$filter] = new $class($this->_options);
                $this->_definedTags = array_merge(
                     $this->_definedTags,
                     $this->_filters[$filter]->_definedTags
                );
                return true;
          }
          return false;
     }

     /**
      * Remove an existing filter
      *
      * @param string $filter
      * @author Lorenzo Alberton <l.alberton@quipo.it>
      */
     public function removeFilter($filter)
     {
          $filter = ucfirst(trim($filter));
          if (!empty($filter) && array_key_exists($filter, $this->_filters)) {
                unset($this->_filters[$filter]);
          }
          // also remove the related $this->_definedTags for this filter,
          // preserving the others
          $this->_definedTags = [];
          foreach (array_keys($this->_filters) as $filter) {
                $this->_definedTags = array_merge(
                     $this->_definedTags,
                     $this->_filters[$filter]->_definedTags
                );
          }
     }

     /**
      * Add new filters
      *
      * @param mixed (array or string)
      * @return boolean true if all ok, false if not.
      * @author Lorenzo Alberton <l.alberton@quipo.it>
      */
     public function addFilters($filters)
     {
          if (is_string($filters)) {
                //comma-separated list
                if (strpos($filters, ',') !== false) {
                     $filters = explode(',', $filters);
                } else {
                     $filters = [$filters];
                }
          }
          if (!is_array($filters)) {
                //invalid format
                return false;
          }
          foreach ($filters as $filter) {
                if (trim($filter)){
                     $this->addFilter($filter);
                }
          }
          return true;
     }

     /**
      * Executes statements before the actual array building starts
      *
      * This method should be overwritten in a filter if you want to do
      * something before the parsing process starts. This can be useful to
      * allow certain short alternative tags which then can be converted into
      * proper tags with preg_replace() calls.
      * The main class walks through all the filters and and calls this
      * method. The filters should modify their private $_preparsed
      * variable, with input from $_text.
      *
      * @return   none
      * @see          $_text
      * @author   Stijn de Reede    <sjr@gmx.co.uk>
      */
     protected function _preparse()
     {
          // default: assign _text to _preparsed, to be overwritten by filters
          $this->_preparsed = str_replace("\r\n", "\r", $this->_text);
          $this->_preparsed = str_replace("\n", "\r", $this->_preparsed);

          // walk through the filters and execute _preparse
          foreach ($this->_filters as $filter) {
                $this->_preparsed = $filter->preparse($this->_preparsed);
          }
     }

     /**
      * Builds the tag array from the input string $_text
      *
      * An array consisting of tag and text elements is contructed from the
      * $_preparsed variable. The method uses _buildTag() to check if a tag is
      * valid and to build the actual tag to be added to the tag array.
      *
      * TODO: - rewrite whole method, as this one is old and probably slow
      *      - see if a recursive method would be better than an iterative one
      *
      * @return   none
      * @see          _buildTag()
      * @see          $_text
      * @see          $_tagArray
      * @author   Stijn de Reede    <sjr@gmx.co.uk>
      */
     protected function _buildTagArray()
     {
          $this->_tagArray = [];
          $str = $this->_preparsed;
          $strPos = 0;
          $strLength = strlen($str);
          while (($strPos < $strLength)) {
                $tag = [];
                $openPos = strpos($str, $this->_options['open'], $strPos);
                if ($openPos === false) {
                     $openPos = $strLength;
                     $nextOpenPos = $strLength;
                }
                if ($openPos + 1 > $strLength) {
                     $nextOpenPos = $strLength;
                } else {
                     $nextOpenPos = strpos($str, $this->_options['open'], $openPos + 1);
                     if ($nextOpenPos === false)
                          $nextOpenPos = $strLength;
                }
                $closePos = strpos($str, $this->_options['close'], $strPos);
                if ($closePos === false) {
                     $closePos = $strLength + 1;
                }

                if ($openPos == $strPos) {
                     if (($nextOpenPos < $closePos)) {
                          // new open tag before closing tag: treat as text
                          $newPos = $nextOpenPos;
                          $tag['text'] = substr($str, $strPos, $nextOpenPos - $strPos);
                          $tag['type'] = BB_TEXT;
                     } else {
                          // possible valid tag
                          $newPos = $closePos + 1;
                          $newTag = $this->_buildTag(substr($str, $strPos, $closePos - $strPos + 1));
                          if (($newTag !== false)) {
                                $tag = $newTag;
                          } else {
                                // no valid tag after all
                                $tag['text'] = substr($str, $strPos, $closePos - $strPos + 1);
                                $tag['type'] = BB_TEXT;
                          }
                     }
                } else {
                     // just text
                     $newPos = $openPos;
                     $tag['text'] = substr($str, $strPos, $openPos - $strPos);
                     $tag['type'] = BB_TEXT;
                }

                // join 2 following text elements
                if ($tag['type'] === 0 && isset($prev) && $prev['type'] === 0) {
                     $tag['text'] = $prev['text'].$tag['text'];
                     array_pop($this->_tagArray);
                }

                $this->_tagArray[] = $tag;
                $prev = $tag;
                $strPos = $newPos;
          }
     }

     /**
      * Builds a tag from the input string
      *
      * This method builds a tag array based on the string it got as an
      * argument. If the tag is invalid, <false> is returned. The tag
      * attributes are extracted from the string and stored in the tag
      * array as an associative array.
      *
      * @param    string                string to build tag from
      * @return   array             tag in array format
      * @see          _buildTagArray()
      * @author   Stijn de Reede    <sjr@gmx.co.uk>
      */
     private function _buildTag($str)
     {
          $tag = ['text' => $str, 'attributes' => []];

          if (substr($str, 1, 1) == '/') {          // closing tag

                $tag['tag'] = strtolower(substr($str, 2, strlen($str) - 3));
                if (!array_key_exists($tag['tag'], $this->_definedTags)) {
                     return false;                          // nope, it's not valid
                } else {
                     $tag['type'] = BB_CLOSING_TAG;
                     return $tag;
                }
          } else {                                          // opening tag

                $tag['type'] = BB_OPENING_TAG;
                if (strpos($str, ' ') && (strpos($str, '=') === false)) {
                     return false;                          // nope, it's not valid
                }

                // tnx to Onno for the regex
                // split the tag with arguments and all
                $oe = $this->_options['open_esc'];
                $ce = $this->_options['close_esc'];
                $tagArray = [];
                if (preg_match("!$oe([a-z0-9]+)[^$ce]*$ce!i", $str, $tagArray) == 0) {
                     return false;
                }

                // Check if tag is valid
                $tag['tag'] = strtolower($tagArray[1]);
                if (!array_key_exists($tag['tag'], $this->_definedTags)) {
                     return false;
                }

                // If the tag uses a custom defined render function or has different type
                if (array_key_exists('type', $this->_definedTags[$tag['tag']])) {
                     $tag['type'] = $this->_definedTags[$tag['tag']]['type'];
                }

                // tnx to Onno for the regex
                // validate the arguments
                $attributeArray = [];
                $regex = "![\s$oe]([a-z0-9]+)=(\"[^\s$ce]+\"|[^\s$ce]";
                if ($tag['tag'] != 'url') {
                     $regex .= "[^=]";
                }
                $regex .= "+)(?=[\s$ce])!i";
                preg_match_all($regex, $str, $attributeArray, PREG_SET_ORDER);
                foreach ($attributeArray as $attribute)
                {
                     $attNam = strtolower($attribute[1]);
                     if (array_key_exists($attNam, $this->_definedTags[$tag['tag']]['attributes']))
                     {
                          if ($attribute[2][0] == '"' && $attribute[2][strlen($attribute[2])-1] == '"')
                          {
                                $tag['attributes'][$attNam] = substr($attribute[2], 1, -1);
                          } else {
                                $tag['attributes'][$attNam] = $attribute[2];
                          }
                     }
                }
                return $tag;
          }
     }

     /**
      * Validates the tag array, regarding the allowed tags
      *
      * While looping through the tag array, two following text tags are
      * joined, and it is checked that the tag is allowed inside the
      * last opened tag.
      * By remembering what tags have been opened it is checked that
      * there is correct (xml compliant) nesting.
      * In the end all still opened tags are closed.
      *
      * @return   none
      * @see          _isAllowed()
      * @see          $_tagArray
      * @author   Stijn de Reede    <sjr@gmx.co.uk>, Seth Price <seth@pricepages.org>
      */
     private function _validateTagArray()
     {
          $newTagArray = [];
          $openTags = [];
          foreach ($this->_tagArray as $tag) {
                $prevTag = end($newTagArray);
                switch ($tag['type']) {
                case BB_TEXT:
                     if (($child = $this->_childNeeded(end($openTags), 'text')) &&
                          $child !== false &&
                          /*
                            * No idea what to do in this case: A child is needed, but
                            * no valid one is returned. We'll ignore it here and live
                            * with it until someone reports a valid bug.
                            */
                          $child !== true )
                     {
                          if (trim($tag['text']) == '') {
                                //just an empty indentation or newline without value?
                                continue;
                          }
                          $newTagArray[] = $child;
                          $openTags[] = $child['tag'];
                     }
                     if ($prevTag['type'] === 0) {
                          $tag['text'] = $prevTag['text'].$tag['text'];
                          array_pop($newTagArray);
                     }
                     $newTagArray[] = $tag;
                     break;

                case BB_OPENING_TAG:
                     if (!$this->_isAllowed(end($openTags), $tag['tag']) ||
                         ($parent = $this->_parentNeeded(end($openTags), $tag['tag'])) === true ||
                         ($child     = $this->_childNeeded(end($openTags),   $tag['tag'])) === true) {
                          $tag['type'] = 0;
                          if ($prevTag['type'] === 0) {
                                $tag['text'] = $prevTag['text'].$tag['text'];
                                array_pop($newTagArray);
                          }
                     } else {
                          if ($parent) {
                                /*
                                 * Avoid use of parent if we can help it. If we are
                                 * trying to insert a new parent, but the current tag is
                                 * the same as the previous tag, then assume that the
                                 * previous tag structure is valid, and add this tag as
                                 * a sibling. To add as a sibling, we need to close the
                                 * current tag.
                                 */
                                if ($tag['tag'] == end($openTags)){
                                     $newTagArray[] = $this->_buildTag('[/'.$tag['tag'].']');
                                     array_pop($openTags);
                                } else {
                                     $newTagArray[] = $parent;
                                     $openTags[] = $parent['tag'];
                                }
                          }
                          if ($child) {
                                $newTagArray[] = $child;
                                $openTags[] = $child['tag'];
                          }
                          $openTags[] = $tag['tag'];
                     }
                     $newTagArray[] = $tag;
                     break;

                case BB_CLOSING_TAG:
                     if (($tag['tag'] == end($openTags) || $this->_isAllowed(end($openTags), $tag['tag']))) {
                          if (in_array($tag['tag'], $openTags)) {
                                $tmpOpenTags = [];
                                while (end($openTags) != $tag['tag']) {
                                     $newTagArray[] = $this->_buildTag('[/'.end($openTags).']');
                                     $tmpOpenTags[] = end($openTags);
                                     array_pop($openTags);
                                }
                                $newTagArray[] = $tag;
                                array_pop($openTags);
                                /* why is this here? it just seems to break things
                                 * (nested lists where closing tags need to be
                                 * generated)
                                while (end($tmpOpenTags)) {
                                     $tmpTag = $this->_buildTag('['.end($tmpOpenTags).']');
                                     $newTagArray[] = $tmpTag;
                                     $openTags[] = $tmpTag['tag'];
                                     array_pop($tmpOpenTags);
                                }*/
                          }
                     } else {
                          $tag['type'] = 0;
                          if ($prevTag['type'] === 0) {
                                $tag['text'] = $prevTag['text'].$tag['text'];
                                array_pop($newTagArray);
                          }
                          $newTagArray[] = $tag;
                     }
                     break;
                case BB_CUSTOM_TAG:
                    if (array_key_exists('htmlclose', $this->_definedTags[$tag['tag']]))
                        $openTags[] = $tag['tag'];
                case BB_HIDDEN_TAG:
                case BB_END_TAG:
                     $newTagArray[] = $tag;
                     break;
                }
          }
          while (end($openTags)) {
                $newTagArray[] = $this->_buildTag('[/'.end($openTags).']');
                array_pop($openTags);
          }
          $this->_tagArray = $newTagArray;
     }

     /**
      * Checks to see if a parent is needed
      *
      * Checks to see if the current $in tag has an appropriate parent. If it
      * does, then it returns false. If a parent is needed, then it returns the
      * first tag in the list to add to the stack.
      *
      * @param    array             tag that is on the outside
      * @param    array             tag that is on the inside
      * @return   boolean           false if not needed, tag if needed, true if out
      *                                 of   our minds
      * @see          _validateTagArray()
      * @author   Seth Price <seth@pricepages.org>
      */
     private function _parentNeeded($out, $in)
     {
          if (!isset($this->_definedTags[$in]['parent']) ||
                ($this->_definedTags[$in]['parent'] == 'all')
          ) {
                return false;
          }

          $ar = explode('^', $this->_definedTags[$in]['parent']);
          $tags = explode(',', $ar[1]);
          if ($ar[0] == 'none'){
                if ($out && in_array($out, $tags)) {
                     return false;
                }
                //Create a tag from the first one on the list
                return $this->_buildTag('['.$tags[0].']');
          }
          if ($ar[0] == 'all' && $out && !in_array($out, $tags)) {
                return false;
          }
          // Tag is needed, we don't know which one. We could make something up,
          // but it would be so random, I think that it would be worthless.
          return true;
     }

     /**
      * Checks to see if a child is needed
      *
      * Checks to see if the current $out tag has an appropriate child. If it
      * does, then it returns false. If a child is needed, then it returns the
      * first tag in the list to add to the stack.
      *
      * @param    array             tag that is on the outside
      * @param    array             tag that is on the inside
      * @return   boolean           false if not needed, tag if needed, true if out
      *                                 of our minds
      * @see          _validateTagArray()
      * @author   Seth Price <seth@pricepages.org>
      */
     private function _childNeeded($out, $in)
     {
          if (!isset($this->_definedTags[$out]['child']) ||
              ($this->_definedTags[$out]['child'] == 'all')
          ) {
                return false;
          }

          $ar = explode('^', $this->_definedTags[$out]['child']);
          $tags = explode(',', $ar[1]);
          if ($ar[0] == 'none'){
                if ($in && in_array($in, $tags)) {
                     return false;
                }
                //Create a tag from the first one on the list
                return $this->_buildTag('['.$tags[0].']');
          }
          if ($ar[0] == 'all' && $in && !in_array($in, $tags)) {
                return false;
          }
          // Tag is needed, we don't know which one. We could make something up,
          // but it would be so random, I think that it would be worthless.
          return true;
     }

     /**
      * Checks to see if a tag is allowed inside another tag
      *
      * The allowed tags are extracted from the private _definedTags array.
      *
      * @param    array             tag that is on the outside
      * @param    array             tag that is on the inside
      * @return   boolean           return true if the tag is allowed, false
      *                                 otherwise
      * @see          _validateTagArray()
      * @author   Stijn de Reede    <sjr@gmx.co.uk>
      */
     private function _isAllowed($out, $in)
     {
          if (!$out || ($this->_definedTags[$out]['allowed'] == 'all')) {
                return true;
          }
          if ($this->_definedTags[$out]['allowed'] == 'none') {
                return false;
          }

          $ar = explode('^', $this->_definedTags[$out]['allowed']);
          $tags = explode(',', $ar[1]);
          if ($ar[0] == 'none' && in_array($in, $tags)) {
                return true;
          }
          if ($ar[0] == 'all'  && in_array($in, $tags)) {
                return false;
          }
          return false;
     }

     /**
      * Builds a parsed string based on the tag array
      *
      * The correct html and attribute values are extracted from the private
      * _definedTags array.
      *
      * @return   none
      * @see          $_tagArray
      * @see          $_parsed
      * @author   Stijn de Reede    <sjr@gmx.co.uk>
      */
     protected function _buildParsedString()
     {
          $this->_parsed = '';
          foreach ($this->_tagArray as $tag) {
                switch ($tag['type']) {

                // just text
                case BB_TEXT:
                    $this->_parsed .= str_replace("\r", '<br />', htmlspecialchars($tag['text']));
                    //$this->_parsed .= trim(htmlspecialchars($tag['text']));
                    break;

                // opening tag
                case BB_OPENING_TAG:
                    $tag_options = $this->_definedTags[$tag['tag']];
                    if (array_key_exists('prepend', $tag_options)) {
                        if (array_key_exists($tag['tag'], $tag['attributes']))
                            $this->_parsed .= str_replace('{arg}', $tag['attributes'][$tag['tag']], $tag_options['prepend']);
                        elseif (array_key_exists('prepend_default', $tag_options))
                            $this->_parsed .= $tag_options['prepend_default'];
                    }
                    $this->_parsed .= '<'.$tag_options['htmlopen'];
                    if ($this->_options['quotestyle'] == 'single')
                        $q = "'";
                    if ($this->_options['quotestyle'] == 'double')
                        $q = '"';
                    foreach ($tag['attributes'] as $a => $v) {
                        //prevent XSS attacks. IMHO this is not enough, though...
                        //@see http://pear.php.net/bugs/bug.php?id=5609
                        $v = preg_replace('#(script|about|applet|activex|chrome):#is', "\\1&#058;", $v);
                        $v = htmlspecialchars($v);
                        $v = str_replace('&amp;amp;', '&amp;', $v);

                        if (($this->_options['quotewhat'] == 'nothing') ||
                                (($this->_options['quotewhat'] == 'strings') and is_numeric($v))
                        ) {
                            $this->_parsed .= ' '.sprintf($tag_options['attributes'][$a], $v, '');
                        } else {
                            $this->_parsed .= ' '.sprintf($tag_options['attributes'][$a], $v, $q);
                        }
                    }
                    if (!array_key_exists('htmlclose', $tag_options) and $this->_options['xmlclose'])
                        $this->_parsed .= ' /';
                    $this->_parsed .= '>';
                    break;

                // closing tag
                case BB_CLOSING_TAG:
                    $tag_options = $this->_definedTags[$tag['tag']];
                    if (array_key_exists('htmlclose', $tag_options))
                        $this->_parsed .= '</'.$tag_options['htmlclose'].'>';
                    break;
                // custom rendered tag
                case BB_CUSTOM_TAG:
                     $tagClass = $this->_definedTags[$tag['tag']]['class'];
                     $func = $this->_definedTags[$tag['tag']]['render'];
                     $this->_parsed .= call_user_func([$tagClass, $func], $tag['attributes']);
                     break;
                // skip tag
                case BB_HIDDEN_TAG:
                     break;
                // end parsing
                case BB_END_TAG:
                     break 2;
                }
          }
          // Keep new lines
          //$this->_parsed = str_replace("\r", '<br />', $this->_parsed);
     }

     /**
      * Sets text in the object to be parsed
      *
      * @param    string                the text to set in the object
      * @return   none
      * @see          getText()
      * @see          $_text
      * @author   Stijn de Reede    <sjr@gmx.co.uk>
      */
     public function setText($str)
     {
          $this->_text = $str;
     }

     /**
      * Gets the unparsed text from the object
      *
      * @return   string                the text set in the object
      * @see          setText()
      * @see          $_text
      * @author   Stijn de Reede    <sjr@gmx.co.uk>
      */
     public function getText()
     {
          return $this->_text;
     }

     /**
      * Gets the preparsed text from the object
      *
      * @return   string                the text set in the object
      * @see          _preparse()
      * @see          $_preparsed
      * @author   Stijn de Reede    <sjr@gmx.co.uk>
      */
     public function getPreparsed()
     {
          return $this->_preparsed;
     }

     /**
      * Gets the parsed text from the object
      *
      * @return   string                the parsed text set in the object
      * @see          parse()
      * @see          $_parsed
      * @author   Stijn de Reede    <sjr@gmx.co.uk>
      */
     public function getParsed()
     {
          return $this->_parsed;
     }

     /**
      * Parses the text set in the object
      *
      * @return   none
      * @see          _preparse()
      * @see          _buildTagArray()
      * @see          _validateTagArray()
      * @see          _buildParsedString()
      * @author   Stijn de Reede    <sjr@gmx.co.uk>
      */
     public function parse()
     {
          $this->_preparse();
          $this->_buildTagArray();
          $this->_validateTagArray();
          $this->_buildParsedString();
     }

     /**
      * Quick method to do setText(), parse() and getParsed at once
      *
      * @return   string
      * @see          parse()
      * @see          $_text
      * @author   Stijn de Reede    <sjr@gmx.co.uk>
      */
     public function qparse($str)
     {
          $this->_text = $str;
          $this->parse();
          return $this->_parsed;
     }

     /**
      * Quick static method to do setText(), parse() and getParsed at once
      *
      * @return   string parsed text
      * @see          parse()
      * @see          $_text
      * @author   Stijn de Reede    <sjr@gmx.co.uk>
      */
     public static function staticQparse($str)
     {
          $p = new bbParser();
          $str = $p->qparse($str);
          unset($p);
          return $str;
     }
}

/**
 * Base class that filters need to extend from.
 */
abstract class bbFilter
{
    /**
     * An array of options, filled by the contructor
     *
     * @var array
     */
    protected $_options;

    public function __construct($options = [])
    {
        // set the options passed as an argument
        foreach ($options as $k => $v)
             $this->_options[$k] = $v;
    }

    public function preparse($text)
    {
        return $text;
    }
}
