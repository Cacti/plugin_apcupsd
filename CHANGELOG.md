# ChangeLog

--- develop ---

* security: Move the UPS filter selects' inline onChange handlers into the ready block so the page no longer trips Cacti's Content-Security-Policy script-src-attr directive
* dev: Enforce patch coverage of changed lines in CI and remove the inert COMPOSER_ROOT_VERSION env from the Pest step
* security: Add a version-safe CSP nonce (`plugin_apcupsd_csp_nonce()`) to every inline `<script>` tag so pages stay compatible with Cacti's Content-Security-Policy nonce enforcement, while falling back cleanly on older Cacti releases that lack the `CactiSecureHeaders` class
* issue: If an SNMP device goes down, once it returns to service the UPS status is missed

--- 1.3.1 ---

* issue#10 Fix Display Version function
* issue#8: Edit/delete UPS issue
* issue#9: Error: Unknown column 'ups_dipsw' in 'INSERT INTO'
* issue: If there has been no LASTSTEST, errors appear in the Cacti Log
* feature#2: Provide Site and Location filters when viewing UPS's

--- 1.2 ---

* issue: Fix issue with replication full sync errors
* issue: A new UPS was not being polled due to a SQL error
* feature: Provide more output when running poller_apcupsd.php --debug

--- 1.1 ---

* feature#3: When changing UPS snmp and other settings on the UPS edit page, transfer those changes to the Cacti device
* feature: Support the 'ONLINE SLAVE' status as being a good status
* feature: Add the unknown column MASTER or ups_master to the stats table
* issue#5: Initial table creation failed due to bogus defaults
* issue#6: Typo field name ups_abmtemp should be ups_ambtemp
* issue: Unable to locate apcaccess command path
* issue: Fix some minor GUI display issues
* issue: Dont run automation until you have some information in the stats table

--- 1.0 ---

* Initial Release

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
