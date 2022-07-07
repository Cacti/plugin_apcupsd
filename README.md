# apcupsd

This plugin is to be used to track UPS status from the apcupsd daemon which
is able to provide information on APC and other types of UPS's over various
network topologies such as USB, Modbus, Ethernet, etc.  Each UPS is defined
by a network address and port name.  Cacti uses the apcaccess command to 
gather statistics from the UPS and store them in the Cacti database for both
Reporting, Graphing, and alerting.

## Purpose

This plugin allows Cacti Administrators to easily track their Data Center
UPS's that do not natively support SNMP protocol.

## Features

* Track UPS status'

* Log information to Cacti database

## Installation

Install just like any other plugin, just throw it in the plugin directory, and
Install and Enabled from the Plugin Management Interface.  Your Cacti install
will require the Linux `apcupsd` package be installed and `apcaccess` be in the
system path.

## Possible Bugs

If you figure out this problem, see the Cacti forums!

## Future Changes

Got any ideas or complaints, please log an issue on GitHub.

## Changelog

--- 1.0 ---

* Initial Release
