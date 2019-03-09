<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'smd_lib';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.37';
$plugin['author'] = 'Stef Dawson';
$plugin['author_uri'] = 'http://stefdawson.com/';
$plugin['description'] = 'Shared function library used by smd_ plugins and others.';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '2';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

/** Uncomment me, if you need a textpack
$plugin['textpack'] = <<< EOT
#@admin
#@language en-gb
abc_sample_string => Sample String
abc_one_more => One more
#@language de-de
abc_sample_string => Beispieltext
abc_one_more => Noch einer
EOT;
**/
// End of textpack

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
// Software engineers look away now unless you wish to be horrified by the amount
// of coupling and cohesion in these functions. I'm warning you... it's not pretty
if (!class_exists('smd_MLP')) {
    class smd_MLP
    {
        var $smd_strings;
        var $smd_owner;
        var $smd_prefix;
        var $smd_lang;
        var $smd_event;

        function __construct($plug, $prefx, $strarray, $lng = 'en-gb', $ev = 'public')
        {
            $this->smd_owner = $plug;
            $this->smd_prefix = $prefx;
            $this->smd_strings = $strarray;
            $this->smd_lang = $lng;
            $this->smd_event = $ev;
            register_callback(array(&$this, 'smd_Callback'), 'l10n.enumerate_strings');
        }

        function smd_Callback($event = 'l10n.enumerate_strings', $step = '', $pre = 0)
        {
            $r = array(
                'owner' => $this->smd_owner,
                'prefix' => $this->smd_prefix,
                'lang' => $this->smd_lang,
                'event' => $this->smd_event,
                'strings' => $this->smd_strings,
            );
            return $r;
        }
        // Generic lookup
        //  $what = key to look up
        //  $args = any arguments the key is expecting for replacement
        function gTxt($what, $args = array())
        {
            global $textarray;

            // Prepare the prefixed key for use
            $key = $this->smd_prefix . '-' . $what;
            $key = strtolower($key);

            // Grab from the global textarray (possibly edited by MLP) if we can
            if(isset($textarray[$key])) {
                $str = $textarray[$key];
            } else {
                // The string isn't in the localised textarray so fallback to using
                // the (non prefixed) string array in the plugin
                $key = strtolower($what);
                $str = (isset($this->smd_strings[$key])) ? $this->smd_strings[$key] : $what;
            }
            // Perform substitutions
            if(!empty($args)) {
                $str = strtr($str, $args);
            }

            return $str;
        }
    }
}

if (!function_exists("smd_doList")) {
    function smd_doList($lst, $rng = true, $sub = "", $qte = true, $dlm = ",", $lax = true)
    {
        global $thisarticle, $thisimage, $thisfile, $thislink, $pretext, $variable;

        $inc = $exc = array();
        $lst = do_list($lst, $dlm);

        // Sometimes pesky Unicode is not compiled in. Detect if so and fall back to ASCII
        if (!@preg_match('/\pL/u', 'a')) {
            $modRE = ($lax) ? '/(\?|\!)([A-Za-z0-9_\- ]+)/' : '/(\?|\!)([A-Za-z0-9_\-]+)/';
        } else {
            $modRE = ($lax) ? '/(\?|\!)([\p{L}\p{N}\p{Pc}\p{Pd}\p{Zs}]+)/' : '/(\?|\!)([\p{L}\p{N}\p{Pc}\p{Pd}]+)/';
        }

        foreach ($lst as $item) {
            $mod = 0; // 0 = include, 1 = exclude
            $numMods = preg_match_all($modRE, $item, $mods);

            for ($modCtr = 0; $modCtr < $numMods; $modCtr++) {
                // mod "type" is governed by the first one found only. i.e. if "article-?c!s" was used in one field
                // it would be an "include" of the word "article-" plus the category and section concatenated
                $mod = ($mods[1][0] === "!") ? 1 : 0;
                $modChar = $mods[1][$modCtr];
                $modItem = trim($mods[2][$modCtr]);
                $lowitem = strtolower($modItem);

                if (isset($variable[$lowitem])) {
                    $item = str_replace($modChar.$modItem, $variable[$lowitem], $item);
                } else if (isset($thisimage[$lowitem])) {
                    $item = str_replace($modChar.$modItem, $thisimage[$lowitem], $item);
                } else if (isset($thisfile[$lowitem])) {
                    $item = str_replace($modChar.$modItem, $thisfile[$lowitem], $item);
                } else if (isset($thislink[$lowitem])) {
                    $item = str_replace($modChar.$modItem, $thislink[$lowitem], $item);
                } else if (array_key_exists($lowitem, $pretext)) {
                    $item = str_replace($modChar.$modItem, $pretext[$lowitem], $item);
                } else if (isset($_POST[$modItem])) {
                    $item = str_replace($modChar.$modItem, $_POST[$modItem], $item);
                } else if (isset($_GET[$modItem])) {
                    $item = str_replace($modChar.$modItem, $_GET[$modItem], $item);
                } else if (isset($_SERVER[$modItem])) {
                    $item = str_replace($modChar.$modItem, $_SERVER[$modItem], $item);
                } else if (isset($thisarticle[$lowitem])) {
                    $item = str_replace($modChar.$modItem, $thisarticle[$lowitem], $item);
                } else {
                    $item = str_replace($modChar.$modItem, $modItem, $item);
                }
            }

            // Handle ranges of values
            $sitem = do_list($item, $dlm);

            foreach ($sitem as $idx => $elem) {
                if ($rng && preg_match('/^(\d+)\-(\d+)$/', $elem)) {
                    list($lo, $hi) = explode("-", $elem, 2);
                    $sitem[$idx] = implode($dlm, range($lo, $hi));
                }
            }

            $item = implode($dlm, $sitem);

            // Item may be empty; ignore it if so
            if ($item) {
                $item = do_list($item, $dlm);

                // Handle sub-categories
                if ($sub) {
                    list($subtype, $level) = explode(":", $sub);
                    $level = (empty($level)) ? 0 : $level;
                    $level = (strtolower($level)=="all") ? 99999 : $level;
                    $outitems = array();

                    foreach ($item as $cat) {
                        $cats = getTree(doslash($cat), $subtype);
                        foreach ($cats as $jdx => $val) {
                            if ($cats[$jdx]['level'] <= $level) {
                                $outitems[] = $cats[$jdx]['name'];
                            }
                        }
                    }
                    $item = $outitems;
                }

                // Quote if asked
                $item = ($qte) ? doArray($item, 'doQuote') : $item;

                if ($mod === 0) {
                    $inc = array_unique(array_merge($inc, $item));
                } else {
                    $exc = array_unique(array_merge($exc, $item));
                }
            }
        }

        return array($inc, $exc);
    }
}

// Split a string on a pattern and allow integer ranges to be expanded
if (!function_exists("smd_split")) {
    function smd_split($str, $allowRange = true, $splitat = "/(,|,\s)+/", $pregopt = PREG_SPLIT_NO_EMPTY)
    {
        $retarr = array();

        if ((substr($splitat,0,1) == "/") && (substr($splitat, strlen($splitat)-1, 1) == "/")) {
            $pat = $splitat;
        } else {
            $pat = '/['.$splitat.']+/';
        }

        $elems = preg_split($pat, $str, -1, $pregopt);

        foreach ($elems as $item) {
            $item = trim($item);
            $negate = false;

            // Does the item start with a negation character
            if (substr($item,0,1) === "!") {
                $negate = true;
                $item = substr($item,1);
            }

            // Is the item an integer list range
            if ($allowRange && preg_match('/^(\d+)\-(\d+)$/', $item)) {
                list($lo, $hi) = explode("-", $item, 2);
                $rng = range($lo, $hi);

                // Reapply the negation if necessary
                for($idx = 0; $idx < count($rng); $idx++) {
                    $rng[$idx] = (($negate) ? "!" : "") . $rng[$idx];
                }

                $retarr = array_merge($retarr, $rng);
            } else {
                $retarr[] = (($negate) ? "!" : "") . $item;
            }
        }

        return $retarr;
    }
}

if (!function_exists("smd_doDblQuote")) {
    function smd_doDblQuote($val)
    {
        return '"'.$val.'"';
    }
}

if (!function_exists("smd_removeQSVar")) {
    function smd_removeQSVar($url, $key)
    {
        $url = preg_replace('/(.*)(\?|&)' . $key . '=[^&]+?(&)(.*)/i', '$1$2$4', $url . '&');
        $url = substr($url, 0, -1);

        return ($url);
    }
}

if (!function_exists("smd_addQSVar")) {
    function smd_addQSVar($url, $key, $value)
    {
        $url = smd_removeQSVar($url, $key);

        if (strpos($url, '?') === false) {
            return ($url . '?' . $key . '=' . $value);
        } else {
            return ($url . '&' . $key . '=' . $value);
        }
    }
}

// DEPRECATED: for backwards compatibility only
if (!function_exists("smd_getSubCats")) {
    function smd_getSubCats($parent,$cattype)
    {
        return getTree($parent,$cattype); //getTree() or getTreePath()??
    }
}

// DEPRECATED: for backwards compatibility only
if (!function_exists("smd_getOpts")) {
    function smd_getOpts($str, $allowed, $idprefix = "", $allowRange = true, $splitat = "/(,|,\s)+/", $pregopt = PREG_SPLIT_NO_EMPTY)
    {
        global $pretext, $thisarticle;

        $out = array();
        $notout = array();
        $matches = smd_split($str, $allowRange, $splitat, $pregopt);

        // An array that tells the loop what to do with each of the valid strings
        //  arg1: type (1=exact match, 2=anywhere within string, 3=custom field)
        //  arg2: prefix, if any
        //  arg3: variable to substitute
        //  arg4: extra check, if applicable
        //  arg5: store in the in/exclude list (1=include; 2=exclude)
        $opt = array(
            "?c" => array(2, "", $pretext['c'], "1", 1),
            "!c" => array(2, "", $pretext['c'], "1", 2),
            "?s" => array(2, "", $pretext['s'], "1", 1),
            "!s" => array(2, "", $pretext['s'], "1", 2),
            "?q" => array(2, "", $pretext['q'], "1", 1),
            "!q" => array(2, "", $pretext['q'], "1", 2),
            "?t" => array(2, "", $thisarticle['url_title'], '$thisarticle!=NULL', 1),
            "!t" => array(2, "", $thisarticle['url_title'], '$thisarticle!=NULL', 2),
            "?id" => array(1, $idprefix, $pretext['id'], '$thisarticle!=NULL', 1),
            "!id" => array(1, $idprefix, $pretext['id'], '$thisarticle!=NULL', 2),
            "?" => array(3, "", "", '$thisarticle!=NULL', 1),
            "!" => array(3, "", "", '$thisarticle!=NULL', 2),
        );

        for ($idx = 0; $idx < count($matches); $idx++) {
            $matched = false;
            $thismatch = $matches[$idx];

            foreach ($opt as $var => $args) {
                $opvar = ($args[4] == 1) ? "out" : "notout";

                switch ($args[0]) {
                    case 1:
                        if (($thismatch === $var) && in_array($var,$allowed)) {
                            $matched = true;

                            if ($args[2] != "" && $args[3]) {
                                $rep = str_replace($var, $args[1].$args[2], $thismatch);

                                if (!in_array($rep, ${$opvar})) {
                                    ${$opvar}[] = $rep;
                                }
                            }
                        }
                        break;
                    case 2:
                        $pat = '/\\'.$var.'$|\\'.$var.'[^A-Za-z0-9]/';

                        if ((is_int(smd_pregPos($pat, $thismatch, $fnd))) && in_array($var,$allowed)) {
                            $matched = true;

                            if ($args[2] != "" && $args[3]) {
                                $rep = str_replace($var, $args[1].$args[2], $thismatch);

                                if (!in_array($rep, ${$opvar})) {
                                    ${$opvar}[] = $rep;
                                }
                            }
                        }
                        break;
                    case 3:
                        $len = strlen($var);

                        if ((substr($thismatch,0,$len) === $var) && in_array($var."field",$allowed)) {
                            $matched = true;
                            // Use the given field name; which may be a comma-separated sublist.
                            // Split off the field name from the question mark
                            $fieldname = substr($thismatch,$len);

                            if (($args[3]) && (isset($thisarticle[strtolower($fieldname)]))) {
                                $fieldContents = $thisarticle[strtolower($fieldname)];
                            } else {
                                $fieldContents = $fieldname;
                            }

                            if (!empty($fieldContents)) {
                                $subout = smd_split(strip_tags($fieldContents), $allowRange, $splitat, $pregopt);

                                foreach ($subout as $subname) {
                                    if (!in_array($subname, ${$opvar})) {
                                        ${$opvar}[] = $subname;
                                    }
                                }
                            }
                        }
                        break;
                }

                if ($matched) {
                    break;
                }
            }

            if (!$matched) {
                // Assign the variable verbatim
                if (!in_array($thismatch, $out)) {
                    $out[] = $thismatch;
                }
            }
        }

        return array($out,$notout);
    }
}

// Stolen from php.net: strpos page comments...
if (!function_exists("smd_pregPos")) {
function smd_pregPos($sPattern, $sSubject, &$FoundString, $iOffset = 0)
{
    $FoundString = null;

    if (preg_match($sPattern, $sSubject, $aMatches, PREG_OFFSET_CAPTURE, $iOffset) > 0) {
        $FoundString = $aMatches[0][0];

        return $aMatches[0][1];
    } else {
        return false;
    }
}
}

//... and array_combine...
if (!function_exists("array_combine")) {
    function array_combine($arr1,$arr2)
    {
        $out = array();

        foreach($arr1 as $key1 => $value1) {
            $out[$value1] = $arr2[$key1];
        }

        return $out;
    }
}

//... and htmlspecialchars_decode
if (!function_exists("htmlspecialchars_decode")) {
    function htmlspecialchars_decode($string, $quote_style = ENT_COMPAT)
    {
        return strtr($string, array_flip(get_html_translation_table(HTML_SPECIALCHARS, $quote_style)));
    }
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
h1. smd_ plugin library

Offers no public textpattern tags. It is simply a shared library of common functions used by smd_ plugins.

h2. Function Reference

*smd_addQSVar*
*smd_removeQSVar*

Add or remove a query string variable to the given URL, taking into account any existing variables that may be in the URL already. 'Add' takes three arguments, 'Remove' just takes the first two:

# The URL string to add to/remove from
# The id of the querystring (the bit before the = sign)
# The value of the new querystring (the bit after the = sign)

e.g. @smd_addQSVar($thisarticle['url_title'], 'tpg', 15);@ would add @tpg=15@ to the current article's URL. If there are no other variables currently in the URL, it is added with a question mark, otherwise it is appended with an ampersand.

h3. smd_doList

Return an expanded list of items with the following properties:

# Anything containing '?' or '!' is checked for a match with a TXP field (@<txp:variable />@, image, file, link, global article, url POST/GET/SERVER, or individual article, in that order)
# Any ranges of items are expanded (e.g. 4-7 => 4,5,6,7) if the @rng@ option permits it
# TXP fields may themselves be lists or ranges
# Anything that is not a TXP field is used verbatim
# The items are returned as 2 lists; inclusion and exclusion

Args ( [*] = mandatory ):

# [*] lst = the list as a delimited string
# rng = whether to allow ranges or not (bool). Default = true
# sub = the type of subcategory to traverse (image, file, link, article, none="") and how many levels to go down (e.g. image:2). Default = ''
# qte = whether to quote each item in the array or not (bool). Default = true
# dlm = the delimiter (string). Default = ","
# lax = Whether to be lax or strict about what characters constitute a field; primarily whether spaces are allowed in, say, custom fields. Default = "1"

h3. smd_getOpts

Deprecated as it is mostly superseded by smd_doList; this one is clunkier but has $idprefix so it remains for now. It searches the passed string for predetermined sequences of characters and, if that sequence is in the given $allowed array, replaces it as follows:

* ?c = current global category (!c = not current category)
* ?s = current section (!s = not current section)
* ?t = current article title (!t = not current title)
* ?id = current article ID, prepended with $idprefix (!id = not current ID)
* ?q = current query term (!q = not current query term)
* ?field = contents of the current article's field (could be a comma-separated list)
* !field = not the contents of the current article's field (could be a comma-separated list)

Integer ranges (e.g. 1-5) will be expanded into their individual values if the $allowRange option is true; anything else is returned verbatim. It outputs two arrays: the 1st contains items for inclusion, the 2nd contains items for exclusion.

Args ( [*] = mandatory ):

# [*] The string to search for matches
# [*] An array containing shortcuts that are "allowed" to be found in the string (?c, ?s, ?t, ?field etc)
# The prefix for ?id strings
# Boolean indicating whether to allow range expansion or not
# RegEx string to split options at (see smd_split)
# preg_split option (see smd_split)

h3. smd_split

Returns an array of items from a string of (usually) comma-separated values. If any values contain ranges of numbers like 1-5 that need 'expanding' first (and $allowRange is true), they are dealt with. Takes the following arguments ( [*] = mandatory args) :

# [*] The string to split
# Boolean indicating whether to allow range expansion or not (i.e. 1-5 becomes 1,2,3,4,5)
# The regular expression character classes to match. If a full RegEx starting and ending with '/' characters is supplied, the expression is used verbatim. Without the '/' characters, the expression is treated as a list of character classes to find. Defaults to "/(,|,\s)+/" which is a comma, or comma and a whitespace character.
# preg_split option constant as defined in the php manual

h3. smd_MLP

Instantiate one of these to handle MLP in your plugin like this:

1) Declare a unique global variable, e.g. global $myPlug
2) Define your default string replacement array (doesn't need to be global), e.g:

$myStrings = array ("msg1" => "This is message 1", "msg2" => "This is message 2");

3) Create an MLP handler:

$myPlug = new smd_MLP("plugin_name", "plugin_prefix", $myStrings);

4) That's it! There are two optional args to smd_MLP: a) the default (full) language to use, e.g "da-dk". Defaults to "en-gb". b) the interface the strings are for. Choose from "public" (the default), "admin" or "common"

5) To use a replacement string in your code: a) Make sure to import the unique global variable: e.g. global $myPlug; b) Call $myPlug->gTxt("messageID"); [ e.g. $myPlug->gTxt("msg1") ] c) If you want to replace any args in your message string, pass an associative array as the 2nd arg to gTxt()

h3. smd_doDblQuote

Alternative to the core's doQuote(). This one dbl-quotes instead of sgl-quotes

h3. smd_pregPos

Lifted from one of the comments in the PHP manual, this just looks for a RegEx string within another, returning the matches it finds and the position of the first match.

h3. array_combine

PHP4 equivalent of the standard PHP5 function, lifted from php.net

h3. htmlspecialchars_decode

PHP4 equivalent of the standard PHP5 function, lifted from php.net

# --- END PLUGIN HELP ---
-->
<?php
}
?>