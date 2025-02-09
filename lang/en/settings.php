<?php
$lang['enabledbydefault'] = 'Whether to enable the linkcheck plugin globally.  Individual pages can enable/disable plugin functionality using <code>{{linkcheck>on}}</code> or <code>{{linkcheck>off}}</code>.';
$lang['usecache'] = 'Whether to use a cache to store the results of link checking to avoid repeated frequent checks of the same URL.';
$lang['cacheexpiry'] = 'Time in seconds to keep a link status in the cache.  Defaults to 604800 (1 week). You may use a number of seconds, or a string like "1 day", "2 hours", "1 week", etc.';
$lang['requireexists'] = 'Restrict url checks to the urls that are actually listed in pages. Use this to prevent our ajax functionality from being used to make requests to arbitrary urls. This option is only available if usecache is enabled and updatecacheduring is set to "parse" or "display".';
$lang['jqueryselector'] = 'The jquery selector to use to find the externallinks to check. When left empty, defaults to <code>a.external</code>. If you want to restrict the javascript linkcheck to certain sections of the page, you can use e.g., <code>#dokuwiki__content a.external</code>.';
$lang['verifypeer'] = 'Whether to verifypeer for https connections.';
$lang['autodownloadcacert'] = 'Whether to automatically download a cacerts.pem file.';
$lang['cacertexpiry'] = 'If the autodownloaded cacert file is older than this, it is redownloaded. Provide a number (seconds) or a string like "1 month", "1 year", "1 week", etc. Defaults to 2419200 (1 month).';
