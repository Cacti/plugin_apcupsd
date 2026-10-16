# ChangeLog

--- develop ---

* feature: Share the UPS Dashboard card arrangement across every UPS (graph cards stay per UPS since they belong to each device), hide N/A rows in the UPS Information card, combine Cable and Driver on one row, and add a per-UPS Estimated Power gauge card that reuses the shared nominal power x load % estimate
* issue: Show N/A for a slave UPS's (e.g. ONLINE SLAVE) estimated power on the Fleet Dashboard and leave it out of the fleet power total, since its draw is already counted in its master's

* feature: Estimate each UPS's power draw as nominal power x load % for UPSes that do not report real wattage, and show it on the Fleet Dashboard (an "Est. Power" tile, a new "Fleet Power" gauge card of total estimated watts against rated capacity, and an "Est. Power" column with a fleet total in the UPSes card) and as a sortable "Est. Power" column in the UPSes list
* feature: Make the Fleet Dashboard UPSes table sortable by UPS, Status, Load, Charge, Time Left, Est. Power and Last Updated; the choice persists in the browser and survives card refreshes
* issue: Stop the dashboard auto-refresh interval following the user to another page by driving Cacti's `refreshMSeconds`/`refreshPage` page-timer globals (as the Graphs page does) instead of storing the interval in the session
* security: Replace the confirmation pages' inline `onClick='cactiReturnTo()'` Cancel buttons with the CSP-safe `cactiReturnTo` class so the pages no longer trip Cacti's Content-Security-Policy script-src-attr directive
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
