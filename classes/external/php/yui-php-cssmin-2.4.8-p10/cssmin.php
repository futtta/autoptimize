<?php

/*!
 * cssmin.php
 * Author: Tubal Martin - http://tubalmartin.me/
 * Repo: https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port
 *
 * This is a PHP port of the CSS minification tool distributed with YUICompressor,
 * itself a port of the cssmin utility by Isaac Schlueter - http://foohack.com/
 * Permission is hereby granted to use the PHP version under the same
 * conditions as the YUICompressor.
 */

/*!
 * YUI Compressor
 * http://developer.yahoo.com/yui/compressor/
 * Author: Julien Lecomte - http://www.julienlecomte.net/
 * Copyright (c) 2013 Yahoo! Inc. All rights reserved.
 * The copyrights embodied in the content of this file are licensed
 * by Yahoo! Inc. under the BSD (revised) open source license.
 */

class CSSmin
{
    const NL = '___YUICSSMIN_PRESERVED_NL___';
    const CLASSCOLON = '___YUICSSMIN_PSEUDOCLASSCOLON___';
    const QUERY_FRACTION = '___YUICSSMIN_QUERY_FRACTION___';

    const TOKEN = '___YUICSSMIN_PRESERVED_TOKEN_';
    const COMMENT = '___YUICSSMIN_PRESERVE_CANDIDATE_COMMENT_';
    const AT_RULE_BLOCK = '___YUICSSMIN_PRESERVE_AT_RULE_BLOCK_';

    private $comments;
    private $atRuleBlocks;
    private $preservedTokens;
    private $chunkLength = 5000;
    private $minChunkLength = 100;
    private $memoryLimit;
    private $maxExecutionTime = 60; // 1 min
    private $pcreBacktrackLimit;
    private $pcreRecursionLimit;
    private $raisePhpLimits;

    private $unitsGroupRegex = '(?:ch|cm|em|ex|gd|in|mm|px|pt|pc|q|rem|vh|vmax|vmin|vw|%)';
    private $numRegex;

    /**
     * @param bool|int $raisePhpLimits If true, PHP settings will be raised if needed
     */
    public function __construct($raisePhpLimits = true)
    {
        $this->memoryLimit = 128 * 1048576; // 128MB in bytes
        $this->pcreBacktrackLimit = 1000 * 1000;
        $this->pcreRecursionLimit = 500 * 1000;

        $this->raisePhpLimits = (bool) $raisePhpLimits;

        $this->numRegex = '(?:\+|-)?\d*\.?\d+' . $this->unitsGroupRegex .'?';
    }

    /**
     * Minifies a string of CSS
     * @param string $css
     * @param int|bool $linebreakPos
     * @return string
     */
    public function run($css = '', $linebreakPos = false)
    {
        if (empty($css)) {
            return '';
        }

        if ($this->raisePhpLimits) {
            $this->doRaisePhpLimits();
        }

        $this->comments = array();
        $this->atRuleBlocks = array();
        $this->preservedTokens = array();

        // process data urls
        $css = $this->processDataUrls($css);

        // process comments
        $css = preg_replace_callback('/(?<!\\\\)\/\*(.*?)\*(?<!\\\\)\//Ss', array($this, 'processComments'), $css);

        // process strings so their content doesn't get accidentally minified
        $css = preg_replace_callback(
            '/(?:"(?:[^\\\\"]|\\\\.|\\\\)*")|'."(?:'(?:[^\\\\']|\\\\.|\\\\)*')/S",
            array($this, 'processStrings'),
            $css
        );

        // Safe chunking: process at rule blocks so after chunking nothing gets stripped out
        $css = preg_replace_callback(
            '/@(?:document|(?:-(?:atsc|khtml|moz|ms|o|wap|webkit)-)?keyframes|media|supports).+?\}\s*\}/si',
            array($this, 'processAtRuleBlocks'),
            $css
        );

        // Let's divide css code in chunks of {$this->chunkLength} chars aprox.
        // Reason: PHP's PCRE functions like preg_replace have a "backtrack limit"
        // of 100.000 chars by default (php < 5.3.7) so if we're dealing with really
        // long strings and a (sub)pattern matches a number of chars greater than
        // the backtrack limit number (i.e. /(.*)/s) PCRE functions may fail silently
        // returning NULL and $css would be empty.
        $charset = '';
        $charsetRegexp = '/(@charset)( [^;]+;)/i';
        $cssChunks = array();
        $l = strlen($css);

        // if the number of characters is <= {$this->chunkLength}, do not chunk
        if ($l <= $this->chunkLength) {
            $cssChunks[] = $css;
        } else {
            // chunk css code securely
            for ($startIndex = 0, $i = $this->chunkLength; $i < $l; $i++) {
                if ($css[$i - 1] === '}' && $i - $startIndex >= $this->chunkLength) {
                    $cssChunks[] = $this->strSlice($css, $startIndex, $i);
                    $startIndex = $i;
                    // Move forward saving iterations when possible!
                    if ($startIndex + $this->chunkLength < $l) {
                        $i += $this->chunkLength;
                    }
                }
            }

            // Final chunk
            $cssChunks[] = $this->strSlice($css, $startIndex);
        }

        // Minify each chunk
        for ($i = 0, $n = count($cssChunks); $i < $n; $i++) {
            $cssChunks[$i] = $this->minify($cssChunks[$i], $linebreakPos);
            // Keep the first @charset at-rule found
            if (empty($charset) && preg_match($charsetRegexp, $cssChunks[$i], $matches)) {
                $charset = strtolower($matches[1]) . $matches[2];
            }
            // Delete all @charset at-rules
            $cssChunks[$i] = preg_replace($charsetRegexp, '', $cssChunks[$i]);
        }

        // Update the first chunk and push the charset to the top of the file.
        $cssChunks[0] = $charset . $cssChunks[0];

        return trim(implode('', $cssChunks));
    }

    /**
     * Sets the approximate number of characters to use when splitting a string in chunks.
     * @param int $length
     */
    public function set_chunk_length($length)
    {
        $length = (int) $length;
        $this->chunkLength = $length < $this->minChunkLength ? $this->minChunkLength : $length;
    }

    /**
     * Sets the memory limit for this script
     * @param int|string $limit
     */
    public function set_memory_limit($limit)
    {
        $this->memoryLimit = $this->normalizeInt($limit);
    }

    /**
     * Sets the maximum execution time for this script
     * @param int|string $seconds
     */
    public function set_max_execution_time($seconds)
    {
        $this->maxExecutionTime = (int) $seconds;
    }

    /**
     * Sets the PCRE backtrack limit for this script
     * @param int $limit
     */
    public function set_pcre_backtrack_limit($limit)
    {
        $this->pcreBacktrackLimit = (int) $limit;
    }

    /**
     * Sets the PCRE recursion limit for this script
     * @param int $limit
     */
    public function set_pcre_recursion_limit($limit)
    {
        $this->pcreRecursionLimit = (int) $limit;
    }

    /**
     * Tries to configure PHP to use at least the suggested minimum settings
     * @return void
     */
    private function doRaisePhpLimits()
    {
        $phpLimits = array(
            'memory_limit' => $this->memoryLimit,
            'max_execution_time' => $this->maxExecutionTime,
            'pcre.backtrack_limit' => $this->pcreBacktrackLimit,
            'pcre.recursion_limit' =>  $this->pcreRecursionLimit
        );

        // If current settings are higher respect them.
        foreach ($phpLimits as $name => $suggested) {
            $current = $this->normalizeInt(ini_get($name));

            if ($current > $suggested) {
                continue;
            }

            // memoryLimit exception: allow -1 for "no memory limit".
            if ($name === 'memory_limit' && $current === -1) {
                continue;
            }

            // maxExecutionTime exception: allow 0 for "no memory limit".
            if ($name === 'max_execution_time' && $current === 0) {
                continue;
            }

            ini_set($name, $suggested);
        }
    }

    /**
     * Registers a preserved token
     * @param $token
     * @return string The token ID string
     */
    private function registerPreservedToken($token)
    {
        $this->preservedTokens[] = $token;
        return self::TOKEN . (count($this->preservedTokens) - 1) .'___';
    }

    /**
     * Gets the regular expression to match the specified token ID string
     * @param $id
     * @return string
     */
    private function getPreservedTokenPlaceholderRegexById($id)
    {
        return '/'. self::TOKEN . $id .'___/';
    }

    /**
     * Registers a candidate comment token
     * @param $comment
     * @return string The comment token ID string
     */
    private function registerComment($comment)
    {
        $this->comments[] = $comment;
        return '/*'. self::COMMENT . (count($this->comments) - 1) .'___*/';
    }

    /**
     * Gets the candidate comment token ID string for the specified comment token ID
     * @param $id
     * @return string
     */
    private function getCommentPlaceholderById($id)
    {
        return self::COMMENT . $id .'___';
    }

    /**
     * Gets the regular expression to match the specified comment token ID string
     * @param $id
     * @return string
     */
    private function getCommentPlaceholderRegexById($id)
    {
        return '/'. $this->getCommentPlaceholderById($id) .'/';
    }

    /**
     * Registers an at rule block token
     * @param $block
     * @return string The comment token ID string
     */
    private function registerAtRuleBlock($block)
    {
        $this->atRuleBlocks[] = $block;
        return self::AT_RULE_BLOCK . (count($this->atRuleBlocks) - 1) .'___';
    }

    /**
     * Gets the regular expression to match the specified at rule block token ID string
     * @param $id
     * @return string
     */
    private function getAtRuleBlockPlaceholderRegexById($id)
    {
        return '/'. self::AT_RULE_BLOCK . $id .'___/';
    }

    /**
     * Minifies the given input CSS string
     * @param string $css
     * @param int|bool $linebreakPos
     * @return string
     */
    private function minify($css, $linebreakPos)
    {
        // Restore preserved at rule blocks
        for ($i = 0, $max = count($this->atRuleBlocks); $i < $max; $i++) {
            $css = preg_replace(
                $this->getAtRuleBlockPlaceholderRegexById($i),
                $this->escapeReplacementString($this->atRuleBlocks[$i]),
                $css,
                1
            );
        }

        // strings are safe, now wrestle the comments
        for ($i = 0, $max = count($this->comments); $i < $max; $i++) {
            $comment = $this->comments[$i];
            $commentPlaceholder = $this->getCommentPlaceholderById($i);
            $commentPlaceholderRegex = $this->getCommentPlaceholderRegexById($i);

            // ! in the first position of the comment means preserve
            // so push to the preserved tokens keeping the !
            if (preg_match('/^!/', $comment)) {
                $preservedTokenPlaceholder = $this->registerPreservedToken($comment);
                $css = preg_replace($commentPlaceholderRegex, $preservedTokenPlaceholder, $css, 1);
                // Preserve new lines for /*! important comments
                $css = preg_replace('/\R+\s*(\/\*'. $preservedTokenPlaceholder .')/', self::NL.'$1', $css);
                $css = preg_replace('/('. $preservedTokenPlaceholder .'\*\/)\s*\R+/', '$1'.self::NL, $css);
                continue;
            }

            // \ in the last position looks like hack for Mac/IE5
            // shorten that to /*\*/ and the next one to /**/
            if (preg_match('/\\\\$/', $comment)) {
                $preservedTokenPlaceholder = $this->registerPreservedToken('\\');
                $css = preg_replace($commentPlaceholderRegex, $preservedTokenPlaceholder, $css, 1);
                $i = $i + 1; // attn: advancing the loop
                $preservedTokenPlaceholder = $this->registerPreservedToken('');
                $css = preg_replace($this->getCommentPlaceholderRegexById($i), $preservedTokenPlaceholder, $css, 1);
                continue;
            }

            // keep empty comments after child selectors (IE7 hack)
            // e.g. html >/**/ body
            if (strlen($comment) === 0) {
                $startIndex = $this->indexOf($css, $commentPlaceholder);
                if ($startIndex > 2) {
                    if (substr($css, $startIndex - 3, 1) === '>') {
                        $preservedTokenPlaceholder = $this->registerPreservedToken('');
                        $css = preg_replace($commentPlaceholderRegex, $preservedTokenPlaceholder, $css, 1);
                        continue;
                    }
                }
            }

            // in all other cases kill the comment
            $css = preg_replace('/\/\*' . $commentPlaceholder . '\*\//', '', $css, 1);
        }

        // Normalize all whitespace strings to single spaces. Easier to work with that way.
        $css = preg_replace('/\s+/', ' ', $css);

        // Remove spaces before & after newlines
        $css = preg_replace('/\s*'. self::NL .'\s*/', self::NL, $css);

        // Fix IE7 issue on matrix filters which browser accept whitespaces between Matrix parameters
        $css = preg_replace_callback(
            '/\s*filter:\s*progid:DXImageTransform\.Microsoft\.Matrix\(([^)]+)\)/',
            array($this, 'processOldIeSpecificMatrixDefinition'),
            $css
        );

        // Shorten & preserve calculations calc(...) since spaces are important
        $css = preg_replace_callback('/calc(\(((?:[^()]+|(?1))*)\))/i', array($this, 'processCalc'), $css);

        // Replace positive sign from numbers preceded by : or a white-space before the leading space is removed
        // +1.2em to 1.2em, +.8px to .8px, +2% to 2%
        $css = preg_replace('/((?<!\\\\):|\s)\+(\.?\d+)/S', '$1$2', $css);

        // Remove leading zeros from integer and float numbers preceded by : or a white-space
        // 000.6 to .6, -0.8 to -.8, 0050 to 50, -01.05 to -1.05
        $css = preg_replace('/((?<!\\\\):|\s)(-?)0+(\.?\d+)/S', '$1$2$3', $css);

        // Remove trailing zeros from float numbers preceded by : or a white-space
        // -6.0100em to -6.01em, .0100 to .01, 1.200px to 1.2px
        $css = preg_replace('/((?<!\\\\):|\s)(-?)(\d?\.\d+?)0+([^\d])/S', '$1$2$3$4', $css);

        // Remove trailing .0 -> -9.0 to -9
        $css = preg_replace('/((?<!\\\\):|\s)(-?\d+)\.0([^\d])/S', '$1$2$3', $css);

        // Replace 0 length numbers with 0
        $css = preg_replace('/((?<!\\\\):|\s)-?\.?0+([^\d])/S', '${1}0$2', $css);

        // Remove the spaces before the things that should not have spaces before them.
        // But, be careful not to turn "p :link {...}" into "p:link{...}"
        // Swap out any pseudo-class colons with the token, and then swap back.
        $css = preg_replace_callback('/(?:^|\})[^{]*\s+:/', array($this, 'processColon'), $css);

        // Remove spaces before the things that should not have spaces before them.
        $css = preg_replace('/\s+([!{};:>+()\]~=,])/', '$1', $css);

        // Restore spaces for !important
        $css = preg_replace('/!important/i', ' !important', $css);

        // bring back the colon
        $css = preg_replace('/'. self::CLASSCOLON .'/', ':', $css);

        // retain space for special IE6 cases
        $css = preg_replace_callback('/:first-(line|letter)(\{|,)/i', array($this, 'lowercasePseudoFirst'), $css);

        // no space after the end of a preserved comment
        $css = preg_replace('/\*\/ /', '*/', $css);

        // lowercase some popular @directives
        $css = preg_replace_callback(
            '/@(document|font-face|import|(?:-(?:atsc|khtml|moz|ms|o|wap|webkit)-)?keyframes|media|namespace|page|' .
            'supports|viewport)/i',
            array($this, 'lowercaseDirectives'),
            $css
        );

        // lowercase some more common pseudo-elements
        $css = preg_replace_callback(
            '/:(active|after|before|checked|disabled|empty|enabled|first-(?:child|of-type)|focus|hover|' .
            'last-(?:child|of-type)|link|only-(?:child|of-type)|root|:selection|target|visited)/i',
            array($this, 'lowercasePseudoElements'),
            $css
        );

        // lowercase some more common functions
        $css = preg_replace_callback(
            '/:(lang|not|nth-child|nth-last-child|nth-last-of-type|nth-of-type|(?:-(?:moz|webkit)-)?any)\(/i',
            array($this, 'lowercaseCommonFunctions'),
            $css
        );

        // lower case some common function that can be values
        // NOTE: rgb() isn't useful as we replace with #hex later, as well as and() is already done for us
        $css = preg_replace_callback(
            '/([:,( ]\s*)(attr|color-stop|from|rgba|to|url|-webkit-gradient|' .
            '(?:-(?:atsc|khtml|moz|ms|o|wap|webkit)-)?(?:calc|max|min|(?:repeating-)?(?:linear|radial)-gradient))/iS',
            array($this, 'lowercaseCommonFunctionsValues'),
            $css
        );

        // Put the space back in some cases, to support stuff like
        // @media screen and (-webkit-min-device-pixel-ratio:0){
        $css = preg_replace_callback('/(\s|\)\s)(and|not|or)\(/i', array($this, 'processAtRulesOperators'), $css);

        // Remove the spaces after the things that should not have spaces after them.
        $css = preg_replace('/([!{}:;>+(\[~=,])\s+/S', '$1', $css);

        // remove unnecessary semicolons
        $css = preg_replace('/;+\}/', '}', $css);

        // Fix for issue: #2528146
        // Restore semicolon if the last property is prefixed with a `*` (lte IE7 hack)
        // to avoid issues on Symbian S60 3.x browsers.
        $css = preg_replace('/(\*[a-z0-9\-]+\s*:[^;}]+)(\})/', '$1;$2', $css);

        // Shorten zero values for safe properties only
        $css = $this->shortenZeroValues($css);

        // Shorten font-weight values
        $css = preg_replace('/(font-weight:)bold\b/i', '${1}700', $css);
        $css = preg_replace('/(font-weight:)normal\b/i', '${1}400', $css);

        // Shorten suitable shorthand properties with repeated non-zero values
        $css = preg_replace(
            '/(margin|padding):('.$this->numRegex.') ('.$this->numRegex.') (?:\2) (?:\3)(;|\}| !)/i',
            '$1:$2 $3$4',
            $css
        );
        $css = preg_replace(
            '/(margin|padding):('.$this->numRegex.') ('.$this->numRegex.') ('.$this->numRegex.') (?:\3)(;|\}| !)/i',
            '$1:$2 $3 $4$5',
            $css
        );

        // Shorten colors from rgb(51,102,153) to #336699, rgb(100%,0%,0%) to #ff0000 (sRGB color space)
        // Shorten colors from hsl(0, 100%, 50%) to #ff0000 (sRGB color space)
        // This makes it more likely that it'll get further compressed in the next step.
        $css = preg_replace_callback('/rgb\s*\(\s*([0-9,\s\-.%]+)\s*\)(.{1})/i', array($this, 'rgbToHex'), $css);
        $css = preg_replace_callback('/hsl\s*\(\s*([0-9,\s\-.%]+)\s*\)(.{1})/i', array($this, 'hslToHex'), $css);

        // Shorten colors from #AABBCC to #ABC or shorter color name.
        $css = $this->shortenHexColors($css);

        // Shorten long named colors: white -> #fff.
        $css = $this->shortenNamedColors($css);

        // shorter opacity IE filter
        $css = preg_replace('/progid:DXImageTransform\.Microsoft\.Alpha\(Opacity=/i', 'alpha(opacity=', $css);

        // Find a fraction that is used for Opera's -o-device-pixel-ratio query
        // Add token to add the "\" back in later
        $css = preg_replace('/\(([a-z\-]+):([0-9]+)\/([0-9]+)\)/i', '($1:$2'. self::QUERY_FRACTION .'$3)', $css);

        // Patch new lines to avoid being removed when followed by empty rules cases
        $css = preg_replace('/'. self::NL .'/', self::NL .'}', $css);

        // Remove empty rules.
        $css = preg_replace('/[^{};\/]+\{\}/S', '', $css);

        // Restore new lines for /*! important comments
        $css = preg_replace('/'. self::NL .'}/', "\n", $css);

        // Add "/" back to fix Opera -o-device-pixel-ratio query
        $css = preg_replace('/'. self::QUERY_FRACTION .'/', '/', $css);

        // Replace multiple semi-colons in a row by a single one
        // See SF bug #1980989
        $css = preg_replace('/;;+/', ';', $css);

        // Lowercase all uppercase properties
        $css = preg_replace_callback('/(\{|;)([A-Z\-]+)(:)/', array($this, 'lowercaseProperties'), $css);

        // Some source control tools don't like it when files containing lines longer
        // than, say 8000 characters, are checked in. The linebreak option is used in
        // that case to split long lines after a specific column.
        if ($linebreakPos !== false && (int) $linebreakPos >= 0) {
            $linebreakPos = (int) $linebreakPos;
            for ($startIndex = $i = 1, $l = strlen($css); $i < $l; $i++) {
                if ($css[$i - 1] === '}' && $i - $startIndex > $linebreakPos) {
                    $css = $this->strSlice($css, 0, $i) . "\n" . $this->strSlice($css, $i);
                    $l = strlen($css);
                    $startIndex = $i;
                }
            }
        }

        // restore preserved comments and strings in reverse order
        for ($i = count($this->preservedTokens) - 1; $i >= 0; $i--) {
            $css = preg_replace(
                $this->getPreservedTokenPlaceholderRegexById($i),
                $this->escapeReplacementString($this->preservedTokens[$i]),
                $css,
                1
            );
        }

        // Trim the final string for any leading or trailing white space but respect newlines!
        $css = preg_replace('/(^ | $)/', '', $css);

        return $css;
    }

    /**
     * Searches & replaces all data urls with tokens before we start compressing,
     * to avoid performance issues running some of the subsequent regexes against large string chunks.
     * @param string $css
     * @return string
     */
    private function processDataUrls($css)
    {
        // Leave data urls alone to increase parse performance.
        $maxIndex = strlen($css) - 1;
        $appenIndex = $index = $lastIndex = $offset = 0;
        $sb = array();
        $pattern = '/url\(\s*(["\']?)data:/i';

        // Since we need to account for non-base64 data urls, we need to handle
        // ' and ) being part of the data string. Hence switching to indexOf,
        // to determine whether or not we have matching string terminators and
        // handling sb appends directly, instead of using matcher.append* methods.
        while (preg_match($pattern, $css, $m, 0, $offset)) {
            $index = $this->indexOf($css, $m[0], $offset);
            $lastIndex = $index + strlen($m[0]);
            $startIndex = $index + 4; // "url(".length()
            $endIndex = $lastIndex - 1;
            $terminator = $m[1]; // ', " or empty (not quoted)
            $terminatorFound = false;

            if (strlen($terminator) === 0) {
                $terminator = ')';
            }

            while ($terminatorFound === false && $endIndex+1 <= $maxIndex) {
                $endIndex = $this->indexOf($css, $terminator, $endIndex + 1);
                // endIndex == 0 doesn't really apply here
                if ($endIndex > 0 && substr($css, $endIndex - 1, 1) !== '\\') {
                    $terminatorFound = true;
                    if (')' !== $terminator) {
                        $endIndex = $this->indexOf($css, ')', $endIndex);
                    }
                }
            }

            // Enough searching, start moving stuff over to the buffer
            $sb[] = $this->strSlice($css, $appenIndex, $index);

            if ($terminatorFound) {
                $token = $this->strSlice($css, $startIndex, $endIndex);
                // Remove all spaces only for base64 encoded URLs.
                $token = preg_replace_callback(
                    '/.+base64,.+/s',
                    array($this, 'removeSpacesFromDataUrls'),
                    trim($token)
                );
                $preservedTokenPlaceholder = $this->registerPreservedToken($token);
                $sb[] = 'url('. $preservedTokenPlaceholder .')';
                $appenIndex = $endIndex + 1;
            } else {
                // No end terminator found, re-add the whole match. Should we throw/warn here?
                $sb[] = $this->strSlice($css, $index, $lastIndex);
                $appenIndex = $lastIndex;
            }

            $offset = $lastIndex;
        }

        $sb[] = $this->strSlice($css, $appenIndex);

        return implode('', $sb);
    }

    /**
     * Shortens all zero values for a set of safe properties
     * e.g. padding: 0px 1px; -> padding:0 1px
     * e.g. padding: 0px 0rem 0em 0.0pc; -> padding:0
     * @param string $css
     * @return string
     */
    private function shortenZeroValues($css)
    {
        $unitsGroupReg = $this->unitsGroupRegex;
        $numOrPosReg = '('. $this->numRegex .'|top|left|bottom|right|center)';
        $oneZeroSafeProperties = array(
            '(?:line-)?height',
            '(?:(?:min|max)-)?width',
            'top',
            'left',
            'background-position',
            'bottom',
            'right',
            'border(?:-(?:top|left|bottom|right))?(?:-width)?',
            'border-(?:(?:top|bottom)-(?:left|right)-)?radius',
            'column-(?:gap|width)',
            'margin(?:-(?:top|left|bottom|right))?',
            'outline-width',
            'padding(?:-(?:top|left|bottom|right))?'
        );
        $nZeroSafeProperties = array(
            'margin',
            'padding',
            'background-position'
        );

        $regStart = '/(;|\{)';
        $regEnd = '/i';

        // First zero regex start
        $oneZeroRegStart = $regStart .'('. implode('|', $oneZeroSafeProperties) .'):';

        // Multiple zeros regex start
        $nZerosRegStart = $regStart .'('. implode('|', $nZeroSafeProperties) .'):';

        $css = preg_replace(
            array(
                $oneZeroRegStart .'0'. $unitsGroupReg . $regEnd,
                $nZerosRegStart . $numOrPosReg .' 0'. $unitsGroupReg . $regEnd,
                $nZerosRegStart . $numOrPosReg .' '. $numOrPosReg .' 0'. $unitsGroupReg . $regEnd,
                $nZerosRegStart . $numOrPosReg .' '. $numOrPosReg .' '. $numOrPosReg .' 0'. $unitsGroupReg . $regEnd
            ),
            array(
                '$1$2:0',
                '$1$2:$3 0',
                '$1$2:$3 $4 0',
                '$1$2:$3 $4 $5 0'
            ),
            $css
        );

        // Remove background-position
        array_pop($nZeroSafeProperties);

        // Replace 0 0; or 0 0 0; or 0 0 0 0; with 0 for safe properties only.
        $css = preg_replace(
            '/('. implode('|', $nZeroSafeProperties) .'):0(?: 0){1,3}(;|\}| !)'. $regEnd,
            '$1:0$2',
            $css
        );

        // Replace 0 0 0; or 0 0 0 0; with 0 0 for background-position property.
        $css = preg_replace('/(background-position):0(?: 0){2,3}(;|\}| !)'. $regEnd, '$1:0 0$2', $css);

        return $css;
    }

    /**
     * Shortens all named colors with a shorter HEX counterpart for a set of safe properties
     * e.g. white -> #fff
     * @param string $css
     * @return string
     */
    private function shortenNamedColors($css)
    {
        $patterns = array();
        $replacements = array();
        $longNamedColors = include 'data/named-to-hex-color-map.php';
        $propertiesWithColors = array(
            'color',
            'background(?:-color)?',
            'border(?:-(?:top|right|bottom|left|color)(?:-color)?)?',
            'outline(?:-color)?',
            '(?:text|box)-shadow'
        );

        $regStart = '/(;|\{)('. implode('|', $propertiesWithColors) .'):([^;}]*)\b';
        $regEnd = '\b/iS';

        foreach ($longNamedColors as $colorName => $colorCode) {
            $patterns[] = $regStart . $colorName . $regEnd;
            $replacements[] = '$1$2:$3'. $colorCode;
        }

        // Run at least 4 times to cover most cases (same color used several times for the same property)
        for ($i = 0; $i < 4; $i++) {
            $css = preg_replace($patterns, $replacements, $css);
        }

        return $css;
    }

    /**
     * Compresses HEX color values of the form #AABBCC to #ABC or short color name.
     *
     * DOES NOT compress CSS ID selectors which match the above pattern (which would break things).
     * e.g. #AddressForm { ... }
     *
     * DOES NOT compress IE filters, which have hex color values (which would break things).
     * e.g. filter: chroma(color="#FFFFFF");
     *
     * DOES NOT compress invalid hex values.
     * e.g. background-color: #aabbccdd
     *
     * @param string $css
     * @return string
     */
    private function shortenHexColors($css)
    {
        // Look for hex colors inside { ... } (to avoid IDs) and
        // which don't have a =, or a " in front of them (to avoid filters)
        $pattern =
            '/(=\s*?["\']?)?#([0-9a-f])([0-9a-f])([0-9a-f])([0-9a-f])([0-9a-f])([0-9a-f])(\}|[^0-9a-f{][^{]*?\})/iS';
        $_index = $index = $lastIndex = $offset = 0;
        $longHexColors = include 'data/hex-to-named-color-map.php';
        $sb = array();

        while (preg_match($pattern, $css, $m, 0, $offset)) {
            $index = $this->indexOf($css, $m[0], $offset);
            $lastIndex = $index + strlen($m[0]);
            $isFilter = $m[1] !== null && $m[1] !== '';

            $sb[] = $this->strSlice($css, $_index, $index);

            if ($isFilter) {
                // Restore, maintain case, otherwise filter will break
                $sb[] = $m[1] .'#'. $m[2] . $m[3] . $m[4] . $m[5] . $m[6] . $m[7];
            } else {
                if (strtolower($m[2]) == strtolower($m[3]) &&
                    strtolower($m[4]) == strtolower($m[5]) &&
                    strtolower($m[6]) == strtolower($m[7])) {
                    // Compress.
                    $hex = '#'. strtolower($m[3] . $m[5] . $m[7]);
                } else {
                    // Non compressible color, restore but lower case.
                    $hex = '#'. strtolower($m[2] . $m[3] . $m[4] . $m[5] . $m[6] . $m[7]);
                }
                // replace Hex colors with shorter color names
                $sb[] = array_key_exists($hex, $longHexColors) ? $longHexColors[$hex] : $hex;
            }

            $_index = $offset = $lastIndex - strlen($m[8]);
        }

        $sb[] = $this->strSlice($css, $_index);

        return implode('', $sb);
    }

    // ---------------------------------------------------------------------------------------------
    // CALLBACKS
    // ---------------------------------------------------------------------------------------------

    private function processComments($matches)
    {
        $match = !empty($matches[1]) ? $matches[1] : '';
        return $this->registerComment($match);
    }

    private function processStrings($matches)
    {
        $match = $matches[0];
        $quote = substr($match, 0, 1);
        $match = $this->strSlice($match, 1, -1);

        // maybe the string contains a comment-like substring?
        // one, maybe more? put'em back then
        if (($pos = strpos($match, self::COMMENT)) !== false) {
            for ($i = 0, $max = count($this->comments); $i < $max; $i++) {
                $match = preg_replace(
                    $this->getCommentPlaceholderRegexById($i),
                    $this->escapeReplacementString($this->comments[$i]),
                    $match,
                    1
                );
            }
        }

        // minify alpha opacity in filter strings
        $match = preg_replace('/progid:DXImageTransform\.Microsoft\.Alpha\(Opacity=/i', 'alpha(opacity=', $match);

        $preservedTokenPlaceholder = $this->registerPreservedToken($match);
        return $quote . $preservedTokenPlaceholder . $quote;
    }

    private function processAtRuleBlocks($matches)
    {
        return $this->registerAtRuleBlock($matches[0]);
    }

    private function processCalc($matches)
    {
        $token = preg_replace(
            '/\)([+\-]{1})/',
            ') $1',
            preg_replace(
                '/([+\-]{1})\(/',
                '$1 (',
                trim(preg_replace('/\s*([*\/(),])\s*/', '$1', $matches[2]))
            )
        );
        $preservedTokenPlaceholder = $this->registerPreservedToken($token);
        return 'calc('. $preservedTokenPlaceholder .')';
    }

    private function processOldIeSpecificMatrixDefinition($matches)
    {
        $preservedTokenPlaceholder = $this->registerPreservedToken($matches[1]);
        return 'filter:progid:DXImageTransform.Microsoft.Matrix('. $preservedTokenPlaceholder .')';
    }

    private function processColon($matches)
    {
        return preg_replace('/\:/', self::CLASSCOLON, $matches[0]);
    }

    private function removeSpacesFromDataUrls($matches)
    {
        return preg_replace('/\s+/', '', $matches[0]);
    }

    private function rgbToHex($matches)
    {
        $hexColors = array();
        $rgbColors = explode(',', $matches[1]);

        // Values outside the sRGB color space should be clipped (0-255)
        for ($i = 0, $l = count($rgbColors); $i < $l; $i++) {
            $hexColors[$i] = sprintf("%02x", $this->clampNumberSrgb($this->rgbPercentageToRgbInteger($rgbColors[$i])));
        }

        // Fix for issue #2528093
        if (!preg_match('/[\s,);}]/', $matches[2])) {
            $matches[2] = ' '. $matches[2];
        }

        return '#'. implode('', $hexColors) . $matches[2];
    }

    private function hslToHex($matches)
    {
        $hslValues = explode(',', $matches[1]);

        $rgbColors = $this->hslToRgb($hslValues);

        return $this->rgbToHex(array('', implode(',', $rgbColors), $matches[2]));
    }

    private function processAtRulesOperators($matches)
    {
        return $matches[1] . strtolower($matches[2]) .' (';
    }

    private function lowercasePseudoFirst($matches)
    {
        return ':first-'. strtolower($matches[1]) .' '. $matches[2];
    }

    private function lowercaseDirectives($matches)
    {
        return '@'. strtolower($matches[1]);
    }

    private function lowercasePseudoElements($matches)
    {
        return ':'. strtolower($matches[1]);
    }

    private function lowercaseCommonFunctions($matches)
    {
        return ':'. strtolower($matches[1]) .'(';
    }

    private function lowercaseCommonFunctionsValues($matches)
    {
        return $matches[1] . strtolower($matches[2]);
    }

    private function lowercaseProperties($matches)
    {
        return $matches[1] . strtolower($matches[2]) . $matches[3];
    }

    // ---------------------------------------------------------------------------------------------
    // HELPERS
    // ---------------------------------------------------------------------------------------------

    /**
     * Clamps a number between a minimum and a maximum value.
     * @param int|float $n the number to clamp
     * @param int|float $min the lower end number allowed
     * @param int|float $max the higher end number allowed
     * @return int|float
     */
    private function clampNumber($n, $min, $max)
    {
        return min(max($n, $min), $max);
    }

    /**
     * Clamps a RGB color number outside the sRGB color space
     * @param int|float $n the number to clamp
     * @return int|float
     */
    private function clampNumberSrgb($n)
    {
        return $this->clampNumber($n, 0, 255);
    }

    /**
     * Escapes backreferences such as \1 and $1 in a regular expression replacement string
     * @param $string
     * @return string
     */
    private function escapeReplacementString($string)
    {
        return addcslashes($string, '\\$');
    }

    /**
     * Converts a HSL color into a RGB color
     * @param array $hslValues
     * @return array
     */
    private function hslToRgb($hslValues)
    {
        $h = floatval($hslValues[0]);
        $s = floatval(str_replace('%', '', $hslValues[1]));
        $l = floatval(str_replace('%', '', $hslValues[2]));

        // Wrap and clamp, then fraction!
        $h = ((($h % 360) + 360) % 360) / 360;
        $s = $this->clampNumber($s, 0, 100) / 100;
        $l = $this->clampNumber($l, 0, 100) / 100;

        if ($s == 0) {
            $r = $g = $b = $this->roundNumber(255 * $l);
        } else {
            $v2 = $l < 0.5 ? $l * (1 + $s) : ($l + $s) - ($s * $l);
            $v1 = (2 * $l) - $v2;
            $r = $this->roundNumber(255 * $this->hueToRgb($v1, $v2, $h + (1/3)));
            $g = $this->roundNumber(255 * $this->hueToRgb($v1, $v2, $h));
            $b = $this->roundNumber(255 * $this->hueToRgb($v1, $v2, $h - (1/3)));
        }

        return array($r, $g, $b);
    }

    /**
     * Tests and selects the correct formula for each RGB color channel
     * @param $v1
     * @param $v2
     * @param $vh
     * @return mixed
     */
    private function hueToRgb($v1, $v2, $vh)
    {
        $vh = $vh < 0 ? $vh + 1 : ($vh > 1 ? $vh - 1 : $vh);

        if ($vh * 6 < 1) {
            return $v1 + ($v2 - $v1) * 6 * $vh;
        }

        if ($vh * 2 < 1) {
            return $v2;
        }

        if ($vh * 3 < 2) {
            return $v1 + ($v2 - $v1) * ((2 / 3) - $vh) * 6;
        }

        return $v1;
    }

    /**
     * PHP port of Javascript's "indexOf" function for strings only
     * Author: Tubal Martin
     *
     * @param string $haystack
     * @param string $needle
     * @param int    $offset index (optional)
     * @return int
     */
    private function indexOf($haystack, $needle, $offset = 0)
    {
        $index = strpos($haystack, $needle, $offset);

        return ($index !== false) ? $index : -1;
    }

    /**
     * Convert strings like "64M" or "30" to int values
     * @param mixed $size
     * @return int
     */
    private function normalizeInt($size)
    {
        if (is_string($size)) {
            $letter = substr($size, -1);
            $size = intval($size);
            switch ($letter) {
                case 'M':
                case 'm':
                    return (int) $size * 1048576;
                case 'K':
                case 'k':
                    return (int) $size * 1024;
                case 'G':
                case 'g':
                    return (int) $size * 1073741824;
            }
        }
        return (int) $size;
    }

    /**
     * Converts a string containing and RGB percentage value into a RGB integer value i.e. '90%' -> 229.5
     * @param $rgbPercentage
     * @return int
     */
    private function rgbPercentageToRgbInteger($rgbPercentage)
    {
        if (strpos($rgbPercentage, '%') !== false) {
            $rgbPercentage = $this->roundNumber(floatval(str_replace('%', '', $rgbPercentage)) * 2.55);
        }

        return intval($rgbPercentage, 10);
    }

    /**
     * Rounds a number to its closest integer
     * @param $n
     * @return int
     */
    private function roundNumber($n)
    {
        return intval(round(floatval($n)), 10);
    }

    /**
     * PHP port of Javascript's "slice" function for strings only
     * Author: Tubal Martin
     *
     * @param string   $str
     * @param int      $start index
     * @param int|bool $end index (optional)
     * @return string
     */
    private function strSlice($str, $start = 0, $end = false)
    {
        if ($end !== false && ($start < 0 || $end <= 0)) {
            $max = strlen($str);

            if ($start < 0) {
                if (($start = $max + $start) < 0) {
                    return '';
                }
            }

            if ($end < 0) {
                if (($end = $max + $end) < 0) {
                    return '';
                }
            }

            if ($end <= $start) {
                return '';
            }
        }

        $slice = ($end === false) ? substr($str, $start) : substr($str, $start, $end - $start);
        return ($slice === false) ? '' : $slice;
    }
}
