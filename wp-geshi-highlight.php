<?php
/*
Plugin Name: WP-GeSHi-Highlight
Plugin URI: http://gehrcke.de/wp-geshi-highlight/
Description: Syntax highlighting for many languages. High performing. Clean, small and valid (X)HTML output. Styles highly and easy configurable. Clean and well documented source code.
Author: Jan-Philip Gehrcke
Version: 1.0.0
Author URI: http://gehrcke.de

WP-GeSHi-Highlight is a largely changed and improved version of WP-Syntax by
Ryan McGeary (http://ryan.mcgeary.org/): wordpress.org/extend/plugins/wp-syntax/
Code parts from WP-Syntax are tagged.

################################################################################
#   :::> Contact: http://gehrcke.de -- jgehrcke@googlemail.com
#
#   Copyright (C) 2010 Jan-Philip Gehrcke
#   Copyright (C) 2007-2009 Ryan McGeary (only the tagged code parts)
#
#   This file is part of WP-GeSHi-Highlight.
#   You can use, modify, redistribute this program under the terms of the GNU
#   General Public License Version 2 (GPL2): http://www.gnu.org/licenses.
#       This program is distributed in the hope that it will be useful, but
#   WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
#   or FITNESS FOR A PARTICULAR PURPOSE (cf. GPL2).
#
################################################################################

This is what's good about this plugin
(and may be better than other highlighters)
===========================================
Secure and efficient
--------------------
- WP-GeSHi-Highlight filters&replaces code snippets as early as possible. The
    highlighted code is inserted as late as possible. Hence, interfering with
    other plugins is minimized.
- If it does not find any code snippets, it does not waste performance by
    senseless inclusion of highlighter libraries etc. And it does not send its
    css code to the user if not necessary (others do...).

Usage of GeSHi setting "GESHI_HEADER_PRE_VALID"
-----------------------------------------------
- it uses numbered lists to create line numbers.
    ->  linenumber-vs-source-shiftings will *never* occur. Those people trusting
        in tables for linenumber-source-alignment often fail while showing long
        sources. I e.g. had this problem with the original version WP-Syntax.
        Many pastebins have this problem.
- it creates valid (X)HTML code.
    ->  this is not trivial while using ordered lists for line numbers.
        This problem is discussed in many bugreports, e.g.:
        http://bit.ly/bks1Uu

Usage of GeSHi's get_stylesheet()
---------------------------------
- it creates very short highlight html code: the styling is not done
    with extremely long <span style"........."> settings. It's done externally
    using CSS classes. These are generated dynamically and only provide what's
    really needed to style a specific piece of code.


Possible issues
===============
- Snippet search and replacement is based on PHP's `preg_replace_callback()`.
"The pcre.backtrack_limit option (added in PHP 5.2) can trigger a NULL return,
with no errors."
http://www.php.net/manual/de/function.preg-replace-callback.php#98721
http://www.php.net/manual/de/function.preg-replace-callback.php#100303
This means, that for very long code snippets, it might happen that this function
simply does not find/replace anything. These snippets then won't get
highlighted. Let me know if you experience something like that.

- The "line" argument allows for numbers greater than 1. This starts the
    numbering at the given number. And it breaks validity.



This is how the plugin works for all page requests
==================================================
I) "template_redirect hook":
----------------------------
1)  The user has sent his request. Wordpress has set up its `$wp_query` object.
2)  `$wp_query` has information about all the content potentially shown to the
    user.
3)  This plugin iterates over this content, i.e. over each post, including each
    (approved) comment belonging to this post.
4)  While iterating over the post and comment texts, occurrences of the pattern
                    <pre args> CODE </pre>
    are searched.
5)  If one such occurrence is found, the information (args and CODE basically)
    is stored safely in a global variable, together with a "match index".
6)  Furthermore, the occurrence of <pre args> CODE </pre> in the original
    content (post/comment text) is deleted and replaced by a unique identifier
    containing the corresponding "match index". Therefore, the content cannot be
    altered by any other wordpress plugins afterwords. From their and Wordpress'
    perspective, the code snippet is "gone".
7)  Now GeSHi iterates over all code snippets. For each, it generates HTML code
    that highlights the snippet according to the given programming language
    (with or wihout line numbers).
8)  Additionally, GeSHi generates optimized CSS code for each snippet. All CSS
    code generated by GeSHi ends up in one string.
9)  For each code snippet, highlighted HTML code AND the corresponding match
    index is stored safely in a global variable.

This was the "fixed" part at the beginning of each page request.
The next steps only happen if there was actually a code snippet to highlight.

II) "wp_head hook":
-------------------
Within this hook, the plugin tells Wordpress to print two important strings
to the <head> section of the HTML page:
  a) Include the wp-geshi-highlight.css (if available in the plugin directory).
     This is for general styling of a code block.
  b) The CSS code generated by GeSHi to make the code colorful.

III) "the content filters":
---------------------------
1)  The plugin defines three very low priority filters on post text and
    excerpt and comment text. This means, these filters run after all or most
    other plugins have done their job, i.e. shortly before the html code is
    delivered to the user's browser.
2)  These filters look for the unique identifiers including the match index,
    which were inserted in I.6.
3)  If such an identifier is found, it gets replaced by the corresponding
    highlighted code snippet. Yeah, that's it.
*/


// This is the entry point of the plugin (Right after Wordpress is done
// processing the user request, setting up `$wp_query` etc, and right before the
// template renders the HTML output.
add_action('template_redirect', 'wp_geshi_main');


function wp_geshi_main() {
    global $wp_geshi_codesnipmatch_arrays;
    global $wp_geshi_run_token;

    // Generate unique token. Code snippets will be replaced by it (+snip ID)
    // temporarily during the action of this plugin.
    $wp_geshi_run_token = md5(uniqid(rand())); // from Ryan McGeary

    // Filter all post/comment text and save and replace code snippets.
    wp_geshi_filter_and_replace_code_snippets();

    // If we did not find any code snippets, it's time to leave...
    if (!count($wp_geshi_codesnipmatch_arrays)) return;

    // `$wp_geshi_codesnipmatch_arrays` is populated. Work on it: it's now
    // GeSHi's part: highlight the code an generate CSS code.
    wp_geshi_highlight_and_generate_css();

    // Now, `$wp_geshi_css_code` and `$wp_geshi_highlighted_matches` are set.
    // Add action to add CSS code to HTML header.
    add_action('wp_head', 'wp_geshi_add_css_to_head');

    // Add low priority filter to replace unique identifiers with highlighted
    // code.
    add_filter('the_content', 'wp_geshi_insert_highlighted_code_filter', 99);
    add_filter('the_excerpt', 'wp_geshi_insert_highlighted_code_filter', 99);
    add_filter('comment_text', 'wp_geshi_insert_highlighted_code_filter', 99);
    }


// Parse all post texts and comment texts (the latter only in case of single
// pages). While iterating over these texts, do the following:
// - detect <pre args> code code code </pre> parts.
// - save these parts in a global variable.
// - modify post/comment texts: replace code parts by a unique token.
function wp_geshi_filter_and_replace_code_snippets() {
    global $wp_query;
    // Iterate over all posts in this query.
    foreach ($wp_query->posts as $post) {
        // Extract code snippets from the content. Replace them.
        $post->post_content = wp_geshi_filter_replace_code($post->post_content);
        if (is_single()) {
            $comments_array = get_approved_comments($post->ID);
            // Iterate over all approved comments belonging to this post.
            // Filter them, too.
            foreach ($comments_array as $comment) {
                $comment->comment_content = wp_geshi_filter_replace_code(
                    $comment->comment_content);
                }
            }
        }
    }


// Search for all <pre args>code</pre> occurrences. Save them in a global var.
// Replace them with unambiguous identifiers.
// `wp_geshi_substitute($match)` is called for each match.
// A `$match` is an array, following the sub-pattern of the regex:
// 0: all
// 1: language
// 2: line
// 3: escaped
// 4: cssfile
// 5: code
function wp_geshi_filter_replace_code($s) {
    return preg_replace_callback(
        "/\s*<pre(?:lang=[\"']([\w-]+)[\"']|line=[\"'](\d*)[\"']"
        ."|escaped=[\"'](true|false)?[\"']|cssfile=[\"']([\S]+)[\"']|\s)+>".
        "(.*)<\/pre>\s*/siU",
        "wp_geshi_store_and_substitute",
        $s
        );
    }


// Store code snippet data. Return unambiguous identifier for this code snippet.
function wp_geshi_store_and_substitute($match_array) {
    global $wp_geshi_run_token, $wp_geshi_codesnipmatch_arrays;

    // count() returns 0 if the variable is not set already.
    // We need this index for the identifier of this code snippet.
    $match_index = count($wp_geshi_codesnipmatch_arrays);

    // Elements of $match_array are strings matching the sub-expressions in the
    // big regular expression searching <pre args>code</pre>. They contain
    // the arguments of <pre> tag and the code snippet itself.
    // Store this array for later usage. Before, store the match index as last
    // array element to `$match_array`.
    $match_array[] = $match_index;
    $wp_geshi_codesnipmatch_arrays[$match_index] = $match_array;

    // Return a string that is unambiguously to identify during another filter
    // run. This string replaces the <pre args>code</pre> code snippet for now.
    return "\n<p>".$wp_geshi_run_token."_".
        sprintf("%06d",$match_index)."</p>\n"; // from Ryan McGeary
    }


// Iterate over all match arrays in `$wp_geshi_codesnipmatch_arrays`.
// Perform syntax highlighting and store the resulting string back in e.g.
// `$wp_geshi_highlighted_matches[$match_index]`.
// Generate CSS code and append it to global `$wp_geshi_css_code`.
function wp_geshi_highlight_and_generate_css() {
    global $wp_geshi_codesnipmatch_arrays;
    global $wp_geshi_css_code;
    global $wp_geshi_highlighted_matches;
    global $wp_geshi_requested_css_files;

    // When we're here, code was found.
    // Time to get the highlight machine running...
    include_once("geshi/geshi.php");
    $wp_geshi_css_code = "";
    foreach($wp_geshi_codesnipmatch_arrays as $match_index => $match) {
            // process the match details. the correspondence is explained at
            // function `wp_geshi_filter_replace_code()`.
            $language = strtolower(trim($match[1]));
            $line = trim($match[2]);
            $escaped = trim($match[3]);
            $cssfile = trim($match[4]);
            $code = wp_geshi_code_trim($match[5]);
            if ($escaped == "true")
                $code = htmlspecialchars_decode($code); // from Ryan McGeary
                
            // set up GeSHi
            $geshi = new GeSHi($code, $language);
            $geshi->enable_keyword_links(false);
            $geshi->enable_classes();
            if ($line) {
                $geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
                $geshi->start_line_numbers_at($line);
                }
            $geshi->set_header_type(GESHI_HEADER_PRE_VALID);
            $wp_geshi_css_code .= $geshi->get_stylesheet();
            
            $output = "";
            // cssfile "none" means no wrapping styling at all!
            if ("$cssfile" != "none") {
                if (empty($cssfile))
                    // for this code snippet we need the default css file!
                    $cssfile = "wp-geshi-highlight";
                // append "the css file" to the array..
                $wp_geshi_requested_css_files[] = $cssfile;
                $output .= "\n\n".'<div class="'.$cssfile.'-wrap5">'.
                           '<div class="'.$cssfile.'-wrap4">'.
                           '<div class="'.$cssfile.'-wrap3">'.
                           '<div class="'.$cssfile.'-wrap2">'.
                           '<div class="'.$cssfile.'-wrap">'.      
                           '<div class="'.$cssfile.'">';
                }
            $output .= $geshi->parse_code();
            if ("$cssfile" != "none")
                $output .= '</div></div></div></div></div></div>'."\n\n";
            $wp_geshi_highlighted_matches[$match_index] = $output;  
        }
    // At this point all code snippets are parsed. highlighted code is stored.
    // CSS code is generated. Delete what's not necessary anymore.
    unset($wp_geshi_codesnipmatch_arrays);
    }


function wp_geshi_insert_highlighted_code_filter($content){
    global $wp_geshi_run_token;
    return preg_replace_callback(
        "/<p>\s*".$wp_geshi_run_token."_(\d{6})\s*<\/p>/si",
        "wp_geshi_get_highlighted_code",
        $content
        ); // from Ryan McGeary
    }


function wp_geshi_get_highlighted_code($match) {
    global $wp_geshi_highlighted_matches;
    // Found a unique identifier. Extract code snippet match index.
    $match_index = intval($match[1]);
    // Return corresponding highlighted code.
    return $wp_geshi_highlighted_matches[$match_index];
    }


function wp_geshi_code_trim($code) {
    // Special ltrim b/c leading whitespace matters on 1st line of content.
    $code = preg_replace("/^\s*\n/siU", "", $code); // from Ryan McGeary
    $code = rtrim($code); // from Ryan McGeary
    return $code;
    }


function wp_geshi_add_css_to_head() {
    global $wp_geshi_css_code;
    global $wp_geshi_requested_css_files;
    
    echo "\n<!-- WP-GeSHi-Highlight plugin by ".
         "Jan-Philip Gehrcke: http://gehrcke.de -->\n";
    
    // set up paths and names
    $csspathpre = WP_PLUGIN_DIR."/wp-geshi-highlight/";
    $cssurlpre = WP_PLUGIN_URL."/wp-geshi-highlight/";
    $csssfx = ".css";

    // echo all required CSS files
    // delete duplicates
    $wp_geshi_requested_css_files = array_unique($wp_geshi_requested_css_files);
    foreach($wp_geshi_requested_css_files as $cssfile)
        wp_geshi_echo_cssfile($csspathpre.$cssfile.$csssfx,
            $cssurlpre.$cssfile.$csssfx);
    // echo GeSHi CSS code if given
    if (strlen($wp_geshi_css_code) > 0)
        echo '<style type="text/css"><!--'.
            $wp_geshi_css_code."//--></style>\n";
    }


function wp_geshi_echo_cssfile($path, $url) {
    if (file_exists($path)) {
        echo '<link rel="stylesheet" href="'.$url.
             '" type="text/css" media="screen" />'."\n";
        }
    }


// Set allowed attributes for pre tags. For more info see wp-includes/kses.php
// credits: wp-syntax (Ryan McGeary)
if (!CUSTOM_TAGS) {
    $allowedposttags['pre'] = array(
        'lang' => array(),
        'line' => array(),
        'escaped' => array(),
        'cssfile' => array()
    );
  //Allow plugin use in comments
    $allowedtags['pre'] = array(
        'lang' => array(),
        'line' => array(),
        'escaped' => array(),
        'cssfile' => array()
    );
}
?>