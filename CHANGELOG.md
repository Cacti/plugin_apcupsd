# ChangeLog

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
Copyright (c) 2004-2025 - The Cacti Group, Inc.
