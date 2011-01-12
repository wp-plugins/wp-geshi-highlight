=== WP-GeSHi-Highlight -- syntax highlighting with clean, small and valid (X)HTML ===
Contributors: jgehrcke
Donate link: http://gehrcke.de/donate/
Tags: highlight, syntax, geshi, valid, highlighting, wp-geshi-highlight
Tested up to: 3.1
Stable tag: 1.0.4

Syntax highlighting for many languages. High performing. Clean, small and valid (X)HTML. Code block styles are highly&easy configurable.




== Description ==
**I) Features:**

* Syntax highlighting for [**many languages**](http://gehrcke.de/files/perm/wp-geshi-highlight/wp-geshi-highlight_languages_1_0_0.txt) (based on [GeSHi](http://qbnz.com/highlighter/)).
* **Line numbers**.
* Extremely **simple to use**. But, if desired, **styles are highly&easy configurable**.
* Multiple code blocks on a single web page? Each **can** get its own style.

**II) Possible advantages over other highlighters due to sophisticated technology:**

* Use of numbered lists to create line numbers -> Code-number-displacements **never** occur!
* Copy/paste keeps formatting. Line numbers are not copied.
* Uses CSS classes: **clean and small HTML output**.
* **Valid** (X)HTML output.
* Only minimal waste of effort if there is nothing to highlight.
* High **performance**.
* Clean and well documented source code.

**III) Usage:**

Type code snippets without the visual editor. Then it's very simple:

`<pre lang="languagestring">
    CODE
</pre>`

Hence, e.g.

`<pre lang="bash">
    $ dd if=/dev/zero of=image.ext3 bs=1M count=10000 oflag=append conv=notrunc
</pre>`

Full instructions explaining more options can be found on the [plugin's homepage](http://gehrcke.de/wp-geshi-highlight).

**IV) How does it look in action?**

Examples can be found on the [plugin's homepage](http://gehrcke.de/wp-geshi-highlight/#examples).

**V) Issues:**

It works for me. But this is still not much tested, so there are for sure some issues. **Please** let me know :-)
Write a [comment](http://gehrcke.de/wp-geshi-highlight) or a [mail](mailto:jgehrcke@googlemail.com).






== Installation ==
1. Upload the `wp-geshi-highlight` directory to the `/wp-content/plugins` directory.
1. Activate the plugin through the plugins menu in WordPress.
1. Use it :-)




== Frequently Asked Questions ==
= Can I change the starting line number? =

Yes, try e.g. line="13". But this [breaks](http://qbnz.com/highlighter/geshi-doc.html#starting-line-numbers) XHTML strict compliance.




== Screenshots ==
1. This screenshot shows WP-GeSHi-Highlight in action. Furthermore, it visualizes how code blocks on the same page can have different styles (which is very easy to accomplish using WP-GeSHi-Highlight).




== Changelog ==
= 1.0.4 (2011-01-12) =
* optimized: now, CSS code is only printed once if you use the same language for multiple code blocks on the same page
* minor code changes

= 1.0.3 (2011-01-06) =
* fixed: comments were not showing up (bug introduced in 1.0.2, thanks to Uli for reporting)

= 1.0.2 (2011-01-04) =
* minor source code changes
* removed beta tag

= 1.0.1-beta (2010-12-18) =
* fixed: highlight in comments now should work properly

= 1.0.0-beta (2010-11-22) =
* initial release, please report any issues