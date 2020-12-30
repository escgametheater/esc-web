<?php
/*
* Smarty plugin
*
-------------------------------------------------------------
* File: modifier.html_substr.php
* Type: modifier
* Name: html_substr
* Version: 1.0
* Date: June 19th, 2003
* Purpose: Cut a string preserving any tag nesting and matching.
* Install: Drop into the plugin directory.
* Author: Original Javascript Code: Benjamin Lupu <lupufr@aol.com>
* Translation to PHP & Smarty: Edward Dale <scompt@scompt.com>
*
-------------------------------------------------------------
*/
function html_substr($string, $length, $etc = '...')
{
    if ($string && $length > 0) {
        $isText = true;
        $ret = "";
        $i = 0;

        $currentChar = "";
        $lastSpacePosition = -1;
        $lastChar = "";

        $tagsArray = [];
        $currentTag = "";
        $tagLevel = 0;

        $noTagLength = strlen(strip_tags($string));

        $stringlen = strlen($string);
        // Parser loop
        for ($j = 0; $j < $stringlen; $j++) {

            $currentChar = $string[$j];
            $ret .= $currentChar;

            // Lesser than event
            if ($currentChar == "<")
                $isText = false;

            // Character handler
            if ($isText) {
                // Memorize last space position
                if($currentChar == " ")
                    $lastSpacePosition = $j;
                else
                    $lastChar = $currentChar;
                $i++;
            } else {
                $currentTag .= $currentChar;
            }

            // Greater than event
            if ($currentChar == ">") {
                $isText = true;

                // Opening tag handler
                if ((strpos($currentTag, "<" ) !== false) &&
                    (strpos($currentTag, "/>" ) === false) &&
                    (strpos($currentTag, "</") === false)) {

                    if (strpos($currentTag, " ") !== false) {
                        // Tag has attribute(s)
                        // "<tag alt=something" to "tag"
                        $currentTag = substr($currentTag, 1, strpos($currentTag, " ") - 1);
                    } else {
                        // Tag doesn't have attribute(s)
                        // "<tag" to "tag"
                        $currentTag = substr($currentTag, 1, -1);
                    }

                    // Save tag as opened
                    array_push($tagsArray, $currentTag);

                    $lastSpacePosition = -1;
                } elseif (strpos($currentTag, "</") !== false) {
                    // Closing tag so unpop it from the opened tags array
                    array_pop($tagsArray);
                }

                $currentTag = "";
            }
            if ($i >= $length)
                break;
        }

        // Cut HTML string at last space position
        if ($length < $noTagLength) {
            if ($lastSpacePosition != -1)
                $ret = substr($string, 0, $lastSpacePosition);
            else
                $ret = substr($string, 0, $j);
            $ret .= $etc;
        }

        // Close broken XHTML elements
        while (($aTag = array_pop($tagsArray)) !== null)
            $ret .= "</" . $aTag . ">\n";

    } else {
        $ret = "";
    }

    return $ret;
}
