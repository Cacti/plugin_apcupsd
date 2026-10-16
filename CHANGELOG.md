# ChangeLog

--- 1.4.0 ---

* issue: Write a WARNING to the Cacti log (APCUPSD) naming the UPS, host, port and reason whenever a NUT query fails; before, the reason was only stored in the UPS's error message and shown with `-d`, so a NUT UPS that could not be reached left nothing in the log
* issue: Save the Poller ID chosen on the UPS form; the field was displayed but never stored, so every UPS was always assigned to poller 1. Changing the Poller ID of a UPS that is linked to a Cacti Device is refused with an explanation, since the device's poller has to be changed on the device itself
* feature: Add support for monitoring UPSes through Network UPS Tools (NUT) as a third collector type, "NUT Based". The poller talks to `upsd` (TCP 3493 by default) over a native PHP socket implementation of the NUT protocol, so no `upsc` or other CLI tool is needed. Optional username/password authentication and STARTTLS (with certificate verification) are supported, and one NUT server can serve several UPSes through the new NUT UPS Name setting. NUT status flags are translated to the existing apcupsd vocabulary (OL to ONLINE, OB to ONBATT, and so on) so the dashboards, fleet totals and alerts keep working unchanged
* issue: Widen the status, model, serial number, firmware, driver and name columns of `apcupsd_ups_stats` so multi-flag NUT statuses and longer NUT strings are not truncated
* feature: Let NUT STARTTLS work with self-signed certificates: a per-UPS CA / Certificate File to verify the server against (certificate and host name are still checked), or an Allow Self-Signed Certificate option that encrypts without verifying the server
* feature: Add a per-UPS Timeout (seconds) setting, 5 by default and limited to 1-120, for `apcupsd` and NUT UPS'. NUT connects and conversations are now bounded by twice the timeout in total, and `apcaccess` is run through Cacti's `exec_with_timeout()` so a hung `apcaccess` can no longer stall the poller; a timed out `apcaccess` now records an error on the UPS
* feature: Make the UPSes table on the Fleet Dashboard sort like the HMIB dashboards: click (or press Enter/Space on) a column header to sort and click again to reverse, in the card and in its maximize dialog
* issue: Right-align the Load, Charge, Time Left, Est. Power and Last Updated columns of the Fleet Dashboard UPSes table
* feature: Add a "Run Graph Automation" action to the UPSes list bulk-action dropdown. It runs Cacti's graph automation (the same as the Devices page's "Apply Automation Rules") for the Cacti Device linked to each selected UPS, creating the graphs, data sources and tree items its templates and automation rules call for, so a device that never got its graphs can be given them without leaving the plugin. Graphs that already exist are left alone, a UPS with no Cacti Device yet is skipped and reported, and the action needs the same realm as Cacti's own device action (Sites/Devices/Data)
* issue: Centre the Status column of the Fleet Dashboard UPSes table
* issue: Make the UPS Dashboard bullets and gauges find the Device Package's data sources. They looked for names the package does not use (`batt_volts`, `line_volts`, `output_volts`, `batt_charge`), so Battery Voltage, Line Voltage and Output Voltage never found an RRD even when the device had graphs. The RRD profile's consolidation functions are now checked first, and when an RRD has no MIN or MAX archive (for example one created from an older or customised profile) the window is bounded by its averages instead of leaving the bound blank. A device with no graphs has no RRD files, so it still has no min/avg/max history; the readings simply show the current value
* issue: The bullet charts now label the ends of the track with the axis (0 and the full-scale value) and list the current, min, avg and max readings above it, leaving out any that are unknown instead of printing N/A; gauge captions do the same
* feature: The Fleet Power gauge now shows min and max ticks and a min / avg / max caption like the other fleet gauges, derived from each UPS's load RRD scaled by its (constant) nominal power
* issue: Changing the Refresh interval on the dashboards now applies it straight away, restarting or stopping the refresh timer, instead of forcing a full page reload; saves are sent one at a time so only a value that was actually saved takes effect, a failed save puts the dropdown (including Cacti's Select2 picker) back to the interval in force, and turning Refresh off puts Cacti's browser auto-logout timer back. The dashboard script is also now loaded before the script that starts the refresh, so the in-place refresh applies the first time the page is opened through Cacti's ajax navigation, not just on later visits
* feature: Share the UPS Dashboard card arrangement across every UPS (graph cards stay per UPS since they belong to each device), hide N/A rows in the UPS Information card, combine Cable and Driver on one row, and add a per-UPS Estimated Power gauge card that reuses the shared nominal power x load % estimate
* issue: Show N/A for a slave UPS's (e.g. ONLINE SLAVE) estimated power on the Fleet Dashboard and leave it out of the fleet power total, since its draw is already counted in its master's
* feature: Estimate each UPS's power draw as nominal power x load % for UPSes that do not report real wattage, and show it on the Fleet Dashboard (an "Est. Power" tile, a new "Fleet Power" gauge card of total estimated watts against rated capacity, and an "Est. Power" column with a fleet total in the UPSes card) and as a sortable "Est. Power" column in the UPSes list
* feature: Make the Fleet Dashboard UPSes table sortable by UPS, Status, Load, Charge, Time Left, Est. Power and Last Updated
* issue: Stop the dashboard auto-refresh interval following the user to another page by driving Cacti's `refreshMSeconds`/`refreshPage` page-timer globals (as the Graphs page does) instead of storing the interval in the session
* feature: Auto-refresh now updates the dashboard's cards in place instead of reloading the whole page: through Cacti's `refreshFunction` page-timer hook where core has it (Cacti 1.3+), and with a timer of its own on Cacti 1.2.x, which has no such hook and stops when the dashboard leaves the screen. The chosen table sort is kept across a refresh (the live one, even if it was changed while the refresh was in flight), overlapping refresh rounds are skipped, and the full-page refresh remains the fallback when the dashboard script is not loaded
* issue: Mark a NUT UPS's linked Cacti host down when its NUT query fails (these devices have availability checking disabled, so core polling cannot detect it), and stop `upsd` from holding a poll open past the deadline by trickling bytes
* issue: Stop the UPS edit form from rewriting an existing UPS's port (3551 to 3493 or back) when it opens; the default port is now only swapped when the collector type is changed by hand
* issue: Store the start time, manufacture date, master update, last on battery and last self test of `apcupsd_ups_stats` as unknown (NULL) when the UPS does not report them, instead of letting each poll invent the current time
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
