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
<div id="smd_help">

    <h1>smd_ plugin library</h1>

    <p>Offers no public textpattern tags. It is simply a shared library of common functions used by smd_ plugins.</p>

    <h2>Changelog</h2>

    <ul>
        <li>v0.1   07-02-25 Initial public release</li>
        <li>v0.2   07-03-03 Added: <span class="caps"><span class="caps">MLP</span></span> (Multi-Lingual Pack) library support</li>
        <li>v0.21  07-03-06 Added: integer range functionality. <code>smd_getAtts</code> now takes a RegEx arg</li>
        <li>v0.21a 07-03-21 Fixed: Character ranges ignored (thanks mrdale)</li>
        <li>v0.21b 07-04-02 Fixed: Sticky article support in <code>smd_getAtts</code> (thanks pieman)</li>
        <li>v0.21c 07-07-29 Fixed: Numeric ranges in categories (thanks wolle)</li>
        <li>v0.21d 07-08-03 Fixed: Negation with multiple elements</li>
        <li>v0.21e 07-09-14 Fixed: Ability to leave empty splitRange parameters intact</li>
        <li>v0.22  07-09-20 Fixed: Undefined index warnings (thanks Ambitiouslemon). Enhanced matches so spaces are allowed in strings (thanks DrRogg)</li>
        <li>v0.23  07-04-09 <span class="caps">BETA</span> : Added the FuzzyFind class and getWord function. getAtts() now allows <code>?q</code></li>
        <li>v0.3   07-10-29 Rewrote <code>smd_getAtts</code> as <code>smd_getOpts</code> to allow replaced vars within text. Deprecated <code>smd_getAtts</code>, added <code>smd_pregPos</code>. Changed <code>smd_splitRange</code> as <code>smd_split</code> to allow ranges to be switched on or off. Deprecated <code>smd_splitRange</code>. Added generic <span class="caps">MLP</span> class support (<code>smd_MLP</code>). Deprecated <code>smdMLP</code> array, <code>smd_gTxt</code> and <code>smd_getCaller</code>. Made <code>smd_FuzzyFind</code> and <code>smd_getWord</code> official</li>
        <li>v0.31  07-11-27 Removed <code>smdMLP</code> array, <code>smd_gTxt</code> and <code>smd_getCaller</code>. Deprecated <code>smd_getSubCats</code>. Added a few PHP4 helper functions</li>
        <li>v0.32  08-03-29 Removed <code>smd_getAtts</code> and <code>smd_getSubCats</code>. Deprecated <code>smd_getOpts</code> and <code>smd_split</code>. Added <code>smd_doList</code>. Moved the <code>smd_FuzzyFind</code> class and <code>smd_getWord</code> into the smd_fuzzy_find plugin where they should have been all along</li>
        <li>v0.33  08-12-02 Undeprecated(!) <code>smd_split</code> since it&#8217;s actually quite useful ; extended <code>smd_doList</code> to encompass <code>$thisimage</code> (for future) and <code>$variable</code> ; fixed bug in <code>smd_doList</code> when using subcats</li>
        <li>v0.34  08-12-13 <code>smd_doList</code> uses a unicode regex</li>
        <li>v0.35  09-02-24 <code>smd_doList</code> fixed ranges in &#8216;?&#8217; variables (thanks koobs)</li>
        <li>v0.36  09-04-02 <code>smd_doList</code> falls back to <span class="caps">ASCII</span> if Unicode not available (thanks RedFox / mlarino / decoderltd)</li>
    </ul>

    <h2>Function Reference</h2>

    <p><strong>smd_addQSVar</strong><br />
<strong>smd_removeQSVar</strong></p>

    <p>Add or remove a query string variable to the given <span class="caps">URL</span>, taking into account any existing variables that may be in the <span class="caps">URL</span> already. &#8216;Add&#8217; takes three arguments, &#8216;Remove&#8217; just takes the first two:</p>

    <ol>
        <li>The <span class="caps">URL</span> string to add to/remove from</li>
        <li>The id of the querystring (the bit before the = sign)</li>
        <li>The value of the new querystring (the bit after the = sign)</li>
    </ol>

    <p>e.g. <code>smd_addQSVar($thisarticle[&#39;url_title&#39;], &#39;tpg&#39;, 15);</code> would add <code>tpg=15</code> to the current article&#8217;s <span class="caps">URL</span>. If there are no other variables currently in the <span class="caps">URL</span>, it is added with a question mark, otherwise it is appended with an ampersand.</p>

    <p><strong>smd_doList</strong></p>

    <p>Return an expanded list of items with the following properties:</p>

    <ol>
        <li>Anything containing &#8216;?&#8217; or &#8216;!&#8217; is checked for a match with a <span class="caps">TXP</span> field (<code>&lt;txp:variable /&gt;</code>, image, file, link, global article, url <span class="caps">POST</span>/GET/SERVER, or individual article, in that order)</li>
        <li>Any ranges of items are expanded (e.g. 4-7 =&gt; 4,5,6,7) if the <code>rng</code> option permits it</li>
        <li><span class="caps">TXP</span> fields may themselves be lists or ranges</li>
        <li>Anything that is not a <span class="caps">TXP</span> field is used verbatim</li>
        <li>The items are returned as 2 lists; inclusion and exclusion</li>
    </ol>

    <p>Args ( [*] = mandatory ) :
    <ol>
        <li>[*] lst = the list as a delimited string</li>
        <li>rng = whether to allow ranges or not (bool). Default = true</li>
        <li>sub = the type of subcategory to traverse (image, file, link, article, none=&#8221;&#8220;) and how many levels to go down (e.g. image:2). Default = &#8216;&#8217;</li>
        <li>qte = whether to quote each item in the array or not (bool). Default = true</li>
        <li>dlm = the delimiter (string). Default = &#8220;,&#8221;</li>
        <li>lax = Whether to be lax or strict about what characters constitute a field; primarily whether spaces are allowed in, say, custom fields. Default = &#8220;1&#8221;</li>
    </ol></p>

    <p><strong>smd_getOpts</strong></p>

    <p>Deprecated as it is mostly superseded by smd_doList; this one is clunkier but has $idprefix so it remains for now. It searches the passed string for predetermined sequences of characters and, if that sequence is in the given $allowed array, replaces it as follows:</p>

    <ul>
        <li>?c = current global category (!c = not current category)</li>
        <li>?s = current section (!s = not current section)</li>
        <li>?t = current article title (!t = not current title)</li>
        <li>?id = current article ID, prepended with $idprefix (!id = not current ID)</li>
        <li>?q = current query term (!q = not current query term)</li>
        <li>?field = contents of the current article&#8217;s field (could be a comma-separated list)</li>
        <li>!field = not the contents of the current article&#8217;s field (could be a comma-separated list)</li>
    </ul>

    <p>Integer ranges (e.g. 1-5) will be expanded into their individual values if the $allowRange option is true; anything else is returned verbatim. It outputs two arrays: the 1st contains items for inclusion, the 2nd contains items for exclusion.</p>

    <p>Args ( [*] = mandatory ) :
    <ol>
        <li>[*] The string to search for matches</li>
        <li>[*] An array containing shortcuts that are &#8220;allowed&#8221; to be found in the string (?c, ?s, ?t, ?field etc)</li>
        <li>The prefix for ?id strings</li>
        <li>Boolean indicating whether to allow range expansion or not</li>
        <li>RegEx string to split options at (see smd_split)</li>
        <li>preg_split option (see smd_split)</li>
    </ol></p>

    <p><strong>smd_split</strong></p>

    <p>Returns an array of items from a string of (usually) comma-separated values. If any values contain ranges of numbers like 1-5 that need &#8216;expanding&#8217; first (and $allowRange is true), they are dealt with. Takes the following arguments ( [*] = mandatory args) :</p>

    <ol>
        <li>[*] The string to split</li>
        <li>Boolean indicating whether to allow range expansion or not (i.e. 1-5 becomes 1,2,3,4,5)</li>
        <li>The regular expression character classes to match. If a full RegEx starting and ending with &#8216;/&#8217; characters is supplied, the expression is used verbatim. Without the &#8216;/&#8217; characters, the expression is treated as a list of character classes to find. Defaults to &#8220;/(,|,\s)+/&#8221; which is a comma, or comma and a whitespace character.</li>
        <li>preg_split option constant as defined in the php manual</li>
    </ol>

    <p><strong>smd_MLP</strong><br />
Instantiate one of these to handle <acronym title="Multi-Lingual Pack"><span class="caps">MLP</span></acronym> in your plugin like this:</p>

    <p>1) Declare a unique global variable, e.g. global $myPlug<br />
2) Define your default string replacement array (doesn&#8217;t need to be global), e.g:</p>

    <p> $myStrings = array (&#8220;msg1&#8221; =&gt; &#8220;This is message 1&#8221;, &#8220;msg2&#8221; =&gt; &#8220;This is message 2&#8221;);</p>

    <p>3) Create an <span class="caps">MLP</span> handler:</p>

    <p> $myPlug = new smd_MLP(&#8220;plugin_name&#8221;, &#8220;plugin_prefix&#8221;, $myStrings);</p>

    <p>4) That&#8217;s it! There are two optional args to smd_MLP:
    a) the default (full) language to use, e.g &#8220;da-dk&#8221;. Defaults to &#8220;en-gb&#8221;.
    b) the interface the strings are for. Choose from &#8220;public&#8221; (the default), &#8220;admin&#8221; or &#8220;common&#8221;</p>

    <p>5) To use a replacement string in your code:
    a) Make sure to import the unique global variable: e.g. global $myPlug;
    b) Call $myPlug-&gt;gTxt(&#8220;messageID&#8221;); [ e.g. $myPlug-&gt;gTxt(&#8220;msg1&#8221;) ]
    c) If you want to replace any args in your message string, pass an associative array as the 2nd arg to gTxt()</p>

    <p><strong>smd_doDblQuote</strong></p>

    <p>Alternative to the core&#8217;s doQuote(). This one dbl-quotes instead of sgl-quotes</p>

    <p><strong>smd_pregPos</strong></p>

    <p>Lifted from one of the comments in the <span class="caps">PHP</span> manual, this just looks for a RegEx string within another, returning the matches it finds and the position of the first match.</p>

    <p><strong>array_combine</strong></p>

    <p>PHP4 equivalent of the standard PHP5 function, lifted from php.net</p>

    <p><strong>htmlspecialchars_decode</strong></p>

    <p>PHP4 equivalent of the standard PHP5 function, lifted from php.net</p>

</div>
# --- END PLUGIN HELP ---
-->
<?php
}
?>