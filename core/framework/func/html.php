<?php
/**
 * HTML related functions
 *
 */

/**
 * Check html content
 *
 * @param string $value
 */
function valid_html(&$value, $tags = null)
{
    global $FRAMEWORK_DIR;

    if ($tags === null)
        $tags = html_news();

    $pos    = 0;
    $open   = [];
    $stack  = [];

    require_once("$FRAMEWORK_DIR/libs/htmlparse.php");

    $parser = new HtmlParser($value);
    while ($parser->parse()) {
        switch ($parser->iNodeType) {
            case NODE_TYPE_START:
            case NODE_TYPE_ELEMENT:
                $name = $parser->iNodeName;
                if (!array_key_exists($name, $tags))
                    continue;

                $attribs = $parser->iNodeAttributes;

                if (array_key_exists('replacement', $tags[$name]))
                    $name = $tags[$name]['replacement'];

                // Attributes
                $args = [];
                if (safe_count($attribs) > 0) {
                    foreach ($attribs as $a_key => $a_value) {
                        if (in_array($a_key, $tags[$name]['elements'])) {
                            if (!array_key_exists($a_key, $tags[$name])
                            || (is_array($tags[$name][$a_key]) && in_array($a_value, $tags[$name][$a_key]))
                            || ($tags[$name][$a_key] === true && $a_value = $a_key)) {
                                $args[] = $a_key.'="'.$a_value.'"';
                            }
                        }
                    }
                }

                // Update stack
                $stack[$pos++] = "<$name".(safe_count($args) > 0 ? ' '.implode(" ", $args) : '')
                    .(array_get($tags[$name], 'simple', false) == true ? ' /' : '').">";

                if (array_get($tags[$name], 'simple', false) != true)
                    $open[$name][] = $pos - 1;

                break;
            case NODE_TYPE_ENDELEMENT:
            case NODE_TYPE_DONE:
                $name = $parser->iNodeName;
                if (!array_key_exists($name, $tags))
                    continue;

                if (array_key_exists('replacement', $tags[$name]))
                    $name = $tags[$name]['replacement'];

                if (safe_count(array_get($open, $name, null)) > 0) {
                    $stack[$pos++] = "</$name>";
                    array_pop($open[$name]);
                }
                break;
            case NODE_TYPE_TEXT:
                #$stack[$pos++] = str_replace(["<", ">"], ["&lt;", "&rt;"], $parser->iNodeValue);
                $stack[$pos++] = htmlentities($parser->iNodeValue);
                continue;
        }
    }
    return implode("", $stack);
}

/**
 * Word wrapper at a giver colomn
 */
function mywordwrap($str, $cols = 68, $cut = ' ')
{
    $tag_open = '<';
    $tag_close = '>';
    $count = 0;
    $in_tag = 0;
    $str_len = strlen($str);
    $segment_width = 0;

    for ($i=1 ; $i<=$str_len ; $i++){
        if ($str[$i] == $tag_open) {
            $in_tag++;
        } elseif ($str[$i] == $tag_close) {
            if ($in_tag > 0)
                $in_tag--;
        } else {
            if ($in_tag == 0) {
                $segment_width++;
                if ($segment_width > $cols && $str[$i] != " ") {
                    $str = substr($str,0,$i).$cut.substr($str,$i,$str_len);
                    $i += strlen($cut);
                    $str_len = strlen($str);
                    $segment_width = 0;
                } elseif ($str[$i] == " ") {
                    $segment_width = 0;
                }
            }
        }
    }
   return str_replace(['===', '###', '***', '¤¤¤', '---'],
                      ['', '', '', '', ''],
                      $str);
}

/**
 * Format filesize
 * eg.
 * - 1024000 to 1MB
 * - 1024 to 1kB
 */
function format_filesize($data)
{
    // bytes
    if($data < 1024)
        return $data . " Bytes";
    // kilobytes
    elseif($data < 1048576)
        return round( ( $data / 1024 ), 1 ) . " KB";
    // megabytes
    elseif ($data < 1073741824)
        return round( ( $data / 1048576 ), 1 ) . " MB";
    else
        return round( ( $data / 1073741824 ), 1 ) . " GB";
}

function format_large_number($number)
{
    // bytes
    if($number < 1000)
        return $number . "";
    // kilobytes
    elseif($number < 1000000)
        return round( ( $number / 1000 ), 1 ) . "K";
    // megabytes
    elseif ($number < 1000000000)
        return round( ( $number / 1000000 ), 1 ) . "M";
    else
        return round( ( $number / 1000000000 ), 1 ) . "G";
}


/**
 * Select random row from $text and parse it using bbcode
 */
function parse_banner($text)
{
    $banners = explode("\n", trim($text));
    $rand = array_rand($banners);
    $banner = parse_bb($banners[$rand]);
    return $banner;
}

/**
 * Replaces {random} by a random integer
 */
function parse_ad($text)
{
    return str_replace('{$random}', rand(), $text);
}

/**
 * Displays an image tag representing the boolean $value
 * - a tick for true
 * - a cross for false
 */
function bool_field($value)
{
    global $CONFIG;

    $yes = '<div class="status is-yes"></div>';
    $no = '<div class="status is-no"></div>';

    return ($value == '1' || $value == true) ? $yes : $no;
}

/**
 * Wrapper to use bool_field in smarty template
 */
function bool_field_tag($params = '', &$smarty = null)
{
    return bool_field(safe_get($params, 'value'));
}


/**
 * Caracters replacements for friendly urls
 */
function slugify($text)
{
    $patterns = ["/å/", "/æ/", "/ø/", "/ä/", "/ü/", "/ö/", "/ß/", "/_+/", "/[\W]+/"];
    $replacements = ["aa","ae", "ae", "oe", "ue", "oe", "ss", "-", "-"];
    return preg_replace($patterns, $replacements, strtolower($text));
}

/**
 * @param $text
 * @param int $character_length
 * @param string $suffix
 * @return string
 */
function inline_truncate_html($text, $character_length = 50, $suffix = '...')
{
    $inline_parsed_body = trim(strip_tags($text));

    return (strlen($inline_parsed_body) > $character_length) ? substr($inline_parsed_body,0,$character_length).$suffix : $inline_parsed_body;

}

/**
 * Build query part of url from array of values
 */
function build_query($values, $el_to_replace = null, $el_value = null)
{
    if ($el_to_replace)
        $values[$el_to_replace] = $el_value;
    return http_build_query($values);
}

/**
 * @param $rawHtml
 * @param array $params
 * @return string
 */
function add_get_params_to_all_links($rawHtml, array $params)
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML(mb_convert_encoding($rawHtml, 'HTML-ENTITIES', 'UTF-8'));

    /** @var DOMNodeList $links */
    $links = $dom->getElementsByTagName('a');
    $count = $links->length;

    for( $i=0; $i<$count; $i++) {

        /** @var DOMElement $link */
        $link = $links->item($i);
        $href = $link->getAttribute("href");
        $url = parse_url($href);

        $query = [];

        if (!empty($url['query']))
            parse_str($url['query'], $query);

        $gets = $query ? array_merge($query, $params) : $params;

        $newstring = '';

        if(isset($url['scheme'])) $newstring .= $url['scheme'].'://';
        if(isset($url['host']))   $newstring .= $url['host'];
        if(isset($url['port']))   $newstring .= ':'.$url['port'];
        if(isset($url['path']))   $newstring .= $url['path'];

        $newstring .= '?'.http_build_query($gets);

        if(isset($url['fragment']))   $newstring .= '#'.$url['fragment'];

        $link->setAttribute('href',$newstring);

    }

    $body = $dom->getElementsByTagName('html')->item(0);

    $newHtml = '';

    // perform inner html on $body by enumerating child nodes
    // and saving them individually
    foreach ($body->childNodes as $childNode) {
        $newHtml .= $dom->saveHTML($childNode);
    }

    return $newHtml;
}

/**
 * Color quoted text
 */
function color_quote($message)
{
    if (substr($message, -1, 1) != "\n")
        $message .= "\n";

    return preg_replace('/^(&gt;[^\>](.*))\n/m',
            '<span class="markup_quote">\\1</span>' . "\n", $message);
}

/**
 * Auto Linker
 */
function auto_link($proto)
{
    return preg_replace("~(https?|ftp|news)(://[[:alnum:]\+\$\;\?\.%,!#\~*/:@&=_-]+)~",
        "<a href=\"$1$2\" rel=\"nofollow\" target=\"_blank\">$1$2</a>", $proto);
}

/**
 * Comment post parser
 */
function parse_post($text)
{
    return auto_link(nl2br(color_quote(htmlspecialchars($text, ENT_QUOTES))));
}
