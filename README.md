# dokuwiki-linkcheck
Dokuwiki plugin to check & show external link availability.

This has a similar functionality as the xtern plugin, but reimplemented with per-page configurability and efficiency in mind. Similar to xtern plugin, the external links will be marked with icons representing their availability:

<img src="https://i.imgur.com/iPsu6qZ.png" width=310 alt="plugin screenshot">

## Features:
* Can enable/disable the plugin functionality within each page, using on or off. Works in conjunction with an enabledbydefault configuration option.
* Provides option to use a cache database to avoid repeated/frequent url checks. Cache timeout can be configured.
* Ajax calls are avoided for links that have been cached (For efficiency, this is only determined during page-parse time).
* Uses the PHP curl library, but falls back to PHP streams when curl is not available.

## Limitations:
* The plugin will collect the urls during render time. Previously cached pages may not be checked. Either edit a page. You can also make any single change in dokuwiki configuration, which should invalidate the cache for all pages.
* Some websites check for and prevent programmatic access and their pages may appear as broken links, even though you may be able to access them from a browser.
* No admin panel provided at this time. Feature requests would be entertained with donations.
