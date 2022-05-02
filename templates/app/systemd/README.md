
# Systemd by Zabbix agent 2

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor systemd units.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

`Systemd by Zabbix agent 2` — collects metrics by polling zabbix-agent2.



This template was tested on:

- Systemd, version 219

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent2) for basic instructions.

1. Setup and configure zabbix-agent2 compiled with the Systemd monitoring plugin.
2. Set filters with macros if you want to override default filter parameters.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SYSTEMD.ACTIVESTATE.SERVICE.MATCHES} |<p>Filter of systemd service units by active state</p> |`active` |
|{$SYSTEMD.ACTIVESTATE.SERVICE.NOT_MATCHES} |<p>Filter of systemd service units by active state</p> |`CHANGE_IF_NEEDED` |
|{$SYSTEMD.ACTIVESTATE.SOCKET.MATCHES} |<p>Filter of systemd socket units by active state</p> |`active` |
|{$SYSTEMD.ACTIVESTATE.SOCKET.NOT_MATCHES} |<p>Filter of systemd socket units by active state</p> |`CHANGE_IF_NEEDED` |
|{$SYSTEMD.NAME.SERVICE.MATCHES} |<p>Filter of systemd service units by name</p> |`.*` |
|{$SYSTEMD.NAME.SERVICE.NOT_MATCHES} |<p>Filter of systemd service units by name</p> |`CHANGE_IF_NEEDED` |
|{$SYSTEMD.NAME.SOCKET.MATCHES} |<p>Filter of systemd socket units by name</p> |`.*` |
|{$SYSTEMD.NAME.SOCKET.NOT_MATCHES} |<p>Filter of systemd socket units by name</p> |`CHANGE_IF_NEEDED` |
|{$SYSTEMD.UNITFILESTATE.SERVICE.MATCHES} |<p>Filter of systemd service units by unit file state</p> |`enabled` |
|{$SYSTEMD.UNITFILESTATE.SERVICE.NOT_MATCHES} |<p>Filter of systemd service units by unit file state</p> |`CHANGE_IF_NEEDED` |
|{$SYSTEMD.UNITFILESTATE.SOCKET.MATCHES} |<p>Filter of systemd socket units by unit file state</p> |`enabled` |
|{$SYSTEMD.UNITFILESTATE.SOCKET.NOT_MATCHES} |<p>Filter of systemd socket units by unit file state</p> |`CHANGE_IF_NEEDED` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Service units discovery |<p>Discover systemd service units and their details.</p> |ZABBIX_PASSIVE |systemd.unit.discovery[service]<p>**Filter**:</p>AND <p>- {#UNIT.ACTIVESTATE} MATCHES_REGEX `{$SYSTEMD.ACTIVESTATE.SERVICE.MATCHES}`</p><p>- {#UNIT.ACTIVESTATE} NOT_MATCHES_REGEX `{$SYSTEMD.ACTIVESTATE.SERVICE.NOT_MATCHES}`</p><p>- {#UNIT.UNITFILESTATE} MATCHES_REGEX `{$SYSTEMD.UNITFILESTATE.SERVICE.MATCHES}`</p><p>- {#UNIT.UNITFILESTATE} NOT_MATCHES_REGEX `{$SYSTEMD.UNITFILESTATE.SERVICE.NOT_MATCHES}`</p><p>- {#UNIT.NAME} NOT_MATCHES_REGEX `{$SYSTEMD.NAME.SERVICE.NOT_MATCHES}`</p><p>- {#UNIT.NAME} MATCHES_REGEX `{$SYSTEMD.NAME.SERVICE.MATCHES}`</p> |
|Socket units discovery |<p>Discover systemd socket units and their details.</p> |ZABBIX_PASSIVE |systemd.unit.discovery[socket]<p>**Filter**:</p>AND <p>- {#UNIT.ACTIVESTATE} MATCHES_REGEX `{$SYSTEMD.ACTIVESTATE.SOCKET.MATCHES}`</p><p>- {#UNIT.ACTIVESTATE} NOT_MATCHES_REGEX `{$SYSTEMD.ACTIVESTATE.SOCKET.NOT_MATCHES}`</p><p>- {#UNIT.UNITFILESTATE} MATCHES_REGEX `{$SYSTEMD.UNITFILESTATE.SOCKET.MATCHES}`</p><p>- {#UNIT.UNITFILESTATE} NOT_MATCHES_REGEX `{$SYSTEMD.UNITFILESTATE.SOCKET.NOT_MATCHES}`</p><p>- {#UNIT.NAME} NOT_MATCHES_REGEX `{$SYSTEMD.NAME.SOCKET.NOT_MATCHES}`</p><p>- {#UNIT.NAME} MATCHES_REGEX `{$SYSTEMD.NAME.SOCKET.MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Systemd |{#UNIT.NAME}: Active state |<p>State value that reflects whether the unit is currently active or not. The following states are currently defined: "active", "reloading", "inactive", "failed", "activating", and "deactivating".</p> |DEPENDENT |systemd.service.active_state["{#UNIT.NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.ActiveState.state`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|Systemd |{#UNIT.NAME}: Load state |<p>State value that reflects whether the configuration file of this unit has been loaded. The following states are currently defined: "loaded", "error", and "masked".</p> |DEPENDENT |systemd.service.load_state["{#UNIT.NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.LoadState.state`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|Systemd |{#UNIT.NAME}: Unit file state |<p>Encodes the install state of the unit file of FragmentPath. It currently knows the following states: "enabled", "enabled-runtime", "linked", "linked-runtime", "masked", "masked-runtime", "static", "disabled", and "invalid".</p> |DEPENDENT |systemd.service.unitfile_state["{#UNIT.NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.UnitFileState.state`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|Systemd |{#UNIT.NAME}: Active time |<p>Number of seconds since unit entered the active state.</p> |DEPENDENT |systemd.service.uptime["{#UNIT.NAME}"]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|Systemd |{#UNIT.NAME}: Connections accepted per sec |<p>The number of accepted socket connections (NAccepted) per second.</p> |DEPENDENT |systemd.socket.conn_accepted.rate["{#UNIT.NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.NAccepted`</p><p>- CHANGE_PER_SECOND</p> |
|Systemd |{#UNIT.NAME}: Connections connected |<p>The current number of socket connections (NConnections).</p> |DEPENDENT |systemd.socket.conn_count["{#UNIT.NAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.NConnections`</p> |
|Zabbix raw items |{#UNIT.NAME}: Get unit info |<p>Returns all properties of a systemd service unit.</p><p> Unit description: {#UNIT.DESCRIPTION}.</p> |ZABBIX_PASSIVE |systemd.unit.get["{#UNIT.NAME}"] |
|Zabbix raw items |{#UNIT.NAME}: Get unit info |<p>Returns all properties of a systemd socket unit.</p><p> Unit description: {#UNIT.DESCRIPTION}.</p> |ZABBIX_PASSIVE |systemd.unit.get["{#UNIT.NAME}",Socket] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|{#UNIT.NAME}: Service is not running |<p>-</p> |`last(/Systemd by Zabbix agent 2/systemd.service.active_state["{#UNIT.NAME}"])<>1` |WARNING |<p>Manual close: YES</p> |
|{#UNIT.NAME}: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Systemd by Zabbix agent 2/systemd.service.uptime["{#UNIT.NAME}"])<10m` |INFO |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

