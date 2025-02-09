# dokuwiki-linkcheck
Dokuwiki plugin to check & show external link availability

Features:
* Can enable/disable the plugin functionality within each page, using {{linkcheck>on}} or {{linkcheck>off}}. Works in conjunction with an enabledbydefault configuration option.
* Provides option to use a cache database to avoid repeated/frequent url checks. Cache timeout can be configured.

Limitations:
* The plugin will collect the urls during render time. Previously cached pages may not be checked. Either edit a page. You can also make any single change in dokuwiki configuration, which should invalidate the cache for all pages.
* Some websites check for and prevent programmatic access and their pages may appear as broken links, even though you may be able to access them from a browser.
