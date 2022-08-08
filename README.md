# apcupsd

This plugin is to be used to track UPS status from the `apcupsd` daemon as 
well as SNMP enabled UPS'.  The `apcupsd` daemon is able to provide information 
on APC and other types of UPS' over various network typologies such as USB, 
Modbus, Ethernet, etc.  I have added basic SNMP support in order to provide
direct access to certain models over the SNMP protocol thus removing the need
for an `apcupsd` host to provide the monitoring details as well.

Each `apcupsd` UPS is defined by a network address and port name.  Cacti uses 
the `apcaccess` command to gather statistics from the UPS and store them 
in the Cacti database for `apcupsd` enabled UPS'.  The data stored in the
Cacti database can be used for Reporting, Graphing, and Alerting.

## Purpose

This plugin allows Cacti Administrators to easily track their Data Center
UPS's that do not support SNMP protocol, but that are supported by the
APCUPSD daemon.

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

I've been looking at the NUT product to see if it's something I would add
support for down the line, but as of yet, I have not made that decision.

Got any ideas or complaints, please log an issue on GitHub.

## Changelog

--- 1.0 ---

* Initial Release
