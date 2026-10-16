# apcupsd

This plugin is to be used to track UPS status from the `apcupsd` daemon, from
the Network UPS Tools (NUT) `upsd` server, as well as from SNMP enabled UPS'.
The `apcupsd` daemon is able to provide information on APC and other types of
UPS' over various network typologies such as USB, Modbus, Ethernet, etc.  NUT
provides the same for a very wide range of UPS vendors.  I have added basic
SNMP support in order to provide direct access to certain models over the SNMP
protocol thus removing the need for an `apcupsd` host to provide the monitoring
details as well.

Each `apcupsd` UPS is defined by a network address and port name.  Cacti uses 
the `apcaccess` command to gather statistics from the UPS and store them 
in the Cacti database for `apcupsd` enabled UPS'.  The data stored in the
Cacti database can be used for Reporting, Graphing, and Alerting.

## Purpose

This plugin allows Cacti Administrators to easily track their Data Center
UPS's that do not support SNMP protocol, but that are supported by the
APCUPSD daemon or by NUT.

## Features

* Track UPS status'

* Log information to Cacti database

* Three collector types: `apcupsd` (via `apcaccess`), SNMP, and NUT (via the
  native NUT network protocol)

* Fleet and per-UPS dashboards, with sortable tables

## Cacti compatibility

If you are running a version of Cacti below 1.2.31, please add the function
below to the `applySkin()` function in `include/layout.js` to enable the Cancel
buttons on forms to work:

```js
$(document).off('click.cactiReturnTo', '.cactiReturnTo')
    .on('click.cactiReturnTo', '.cactiReturnTo', function(event) {
        event.preventDefault();
        cactiReturnTo($(this).attr('data-url'));
    });
```

## Installation

Install just like any other plugin, just throw it in the plugin directory, and
Install and Enabled from the Plugin Management Interface.  To monitor
`apcupsd` UPS', your Cacti install will require the Linux `apcupsd` package be
installed and `apcaccess` be in the system path.  NUT UPS' need no extra
software on the Cacti poller.

After you have installed the plugin, install the Device Package in the 
templates folder from Console > Import/Export > Import Package.  This 
Device Template will be used for showing available graphs.

The dashboards read each reading's min, average and max from the RRD files of
the UPS's Cacti Device, so the device needs its graphs.  They are created when
the device is first added, but if a device has none (for example the Device
Package was imported afterwards), tick the UPS on the UPSes page and choose
`Run Graph Automation` from the action dropdown.  It runs Cacti's graph
automation for the linked device, the same as `Apply Automation Rules` on the
Devices page, and leaves graphs that already exist alone.  It needs the
`Sites/Devices/Data` permission.

## Monitoring UPS' with NUT

Support for Network UPS Tools (NUT) is new in version 1.4.0.  The plugin talks
to the NUT `upsd` data server directly with a native PHP implementation of the
NUT network protocol, so the NUT client tools (`upsc`) do not have to be
installed on the Cacti poller, and `curl` is not involved.  The only
requirement is that the poller can open a TCP connection to `upsd`, which
listens on port 3493 by default.

To add a NUT UPS, go to the UPSes page, add a UPS, and choose the `NUT Based`
type:

* **Hostname** and **TCP Port**: the host running `upsd` and its port (3493).

* **NUT UPS Name**: the name of the UPS on that server, as listed by
  `upsc -l <host>` (the `ups` in `ups@host`).  One `upsd` can serve several
  UPS', so add one UPS here for each of them.

* **NUT Username** and **NUT Password**: optional.  NUT servers have always
  allowed anonymous read-only access and that is how they are normally
  monitored, so leave these blank unless your `upsd.users` is set up to require
  authentication.  A user with no actions is enough, as the plugin only reads
  data.  The password is stored in the Cacti database the same way SNMP
  community strings and passwords are.

* **Use TLS (STARTTLS)**: encrypts the connection before any credentials are
  sent (the `openssl` PHP extension is required).  The server certificate and
  host name are verified, so by default the certificate must be trusted by the
  Cacti poller.  For a homelab with a self-signed certificate there are two
  ways around that:

  * **CA / Certificate File** (recommended): the full path, on the poller, to a
    PEM file holding the CA certificate, or the self-signed server certificate
    itself (for example `/etc/nut/upsd.pem` copied from the NUT server).  The
    server is still authenticated against that file, and the Hostname must
    match a name in the certificate.

  * **Allow Self-Signed Certificate**: accepts any certificate without
    checking it.  The connection is encrypted but the server's identity is not
    verified, so a man-in-the-middle could still read the NUT credentials.  Use
    it only on a network you trust, and prefer the certificate file.

* **Timeout (seconds)**: how long the poller waits for the NUT server, from 1
  to 120 seconds (5 by default).  It bounds the connection attempt and each
  reply, and the connection plus the whole conversation together never take
  longer than twice the timeout, so an unresponsive server cannot hold up the
  poller.  The same setting applies to `apcupsd` UPS', where `apcaccess` is
  stopped if it has not finished in time.  SNMP UPS' keep using the SNMP
  timeout.

On the NUT server, `upsd` only listens on localhost by default.  To let a
Cacti poller reach it, add a `LISTEN` line to `upsd.conf` (for example
`LISTEN 0.0.0.0 3493`), restart `upsd`, and allow the poller through the
firewall.

NUT reports power state with short flags.  These are translated into the
`apcupsd` vocabulary when the data is saved (`OL` becomes `ONLINE`, `OB` becomes
`ONBATT`, `LB` becomes `LOWBATT`, `RB` becomes `REPLACEBATT`, and `OVER`
becomes `OVERLOAD`), so the dashboards and fleet totals treat NUT and `apcupsd`
UPS' the same way.  A Cacti device is created for each NUT UPS automatically,
the same as for `apcupsd` UPS'.

## Possible Bugs

If you figure out this problem, see the Cacti forums!

## Future Changes

Currently the SNMP based collection is functional but only covering APC UPS'.  
I will likely fix that in the next release.  Need some help from others.

Measured real power (`ups.realpower`) is reported by many NUT UPS' but is not
stored yet; the plugin continues to estimate watts from the nominal power
rating and the load.

Got any ideas or complaints, please log an issue on GitHub.

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
