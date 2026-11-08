# ChangeLog


--- 1.4.0 ---

* dev: Run the CI poller with the shared runner group so RRD files and structured directories pass permission verification
* security: Add a version-safe CSP nonce (`plugin_apcupsd_csp_nonce()`) to every inline `<script>`
* security: Replace all inline javascript handlers with jQuery assigned handlers
* issue: Provide better logging when UPSes are having communication issues with the Cacti data collector
* issue: Save the Poller ID chosen on the UPS form it was previously displayed, but never saved
* issue: Store the start time, manufacture date, master update, last on battery and last self test of `apcupsd_ups_stats` as unknown (NULL) when the UPS does not report them
* issue: If an SNMP device goes down, once it returns to service the UPS status is missed
* feature: Add a "Run Graph Automation" action to the UPSes list bulk-action dropdown
* feature: introduce Fleet Dashboard for UPSes
* feature: Properly refresh pages periodically using Cacti native API's
* feature: Update the UPS and Fleet dashboard graph timespan in place instead of reloading the whole page
* feature: Switch from using apcaccess to a native sockets based tcp poller for apcupsd targets
* feature: Allow Enable and Disable from the UPSes UI
* feature: Estimate each UPS's power draw as nominal power x load % for UPSes that do not report real wattage
* feature: Add a per-UPS Timeout (seconds) setting, 5 by default and limited to 1-120
* feature: Add support for monitoring UPSes through Network UPS Tools (NUT)
* feature: Support NUT TLS using self-signed certificates

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
