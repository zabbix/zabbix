
# Systemd by Zabbix agent 2

## Overview

This template is designed for the effortless deployment of Systemd monitoring by Zabbix via Zabbix agent 2 and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Systemd 219

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Setup and configure zabbix-agent2 compiled with the Systemd monitoring plugin.
2. Set filters with macros if you want to override default filter parameters.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SYSTEMD.NAME.SOCKET.MATCHES}|<p>Filter of systemd socket units by name</p>|`.*`|
|{$SYSTEMD.NAME.SOCKET.NOT_MATCHES}|<p>Filter of systemd socket units by name</p>|`CHANGE_IF_NEEDED`|
|{$SYSTEMD.ACTIVESTATE.SOCKET.MATCHES}|<p>Filter of systemd socket units by active state</p>|`active`|
|{$SYSTEMD.ACTIVESTATE.SOCKET.NOT_MATCHES}|<p>Filter of systemd socket units by active state</p>|`CHANGE_IF_NEEDED`|
|{$SYSTEMD.UNITFILESTATE.SOCKET.MATCHES}|<p>Filter of systemd socket units by unit file state</p>|`enabled`|
|{$SYSTEMD.UNITFILESTATE.SOCKET.NOT_MATCHES}|<p>Filter of systemd socket units by unit file state</p>|`CHANGE_IF_NEEDED`|
|{$SYSTEMD.NAME.SERVICE.MATCHES}|<p>Filter of systemd service units by name</p>|`.*`|
|{$SYSTEMD.NAME.SERVICE.NOT_MATCHES}|<p>Filter of systemd service units by name</p>|`CHANGE_IF_NEEDED`|
|{$SYSTEMD.ACTIVESTATE.SERVICE.MATCHES}|<p>Filter of systemd service units by active state</p>|`active`|
|{$SYSTEMD.ACTIVESTATE.SERVICE.NOT_MATCHES}|<p>Filter of systemd service units by active state</p>|`CHANGE_IF_NEEDED`|
|{$SYSTEMD.UNITFILESTATE.SERVICE.MATCHES}|<p>Filter of systemd service units by unit file state</p>|`enabled`|
|{$SYSTEMD.UNITFILESTATE.SERVICE.NOT_MATCHES}|<p>Filter of systemd service units by unit file state</p>|`CHANGE_IF_NEEDED`|

### LLD rule Service units discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service units discovery|<p>Discover systemd service units and their details.</p>|Zabbix agent|systemd.unit.discovery[service]|

### Item prototypes for Service units discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#UNIT.NAME}: Get unit info|<p>Returns all properties of a systemd service unit.</p><p> Unit description: {#UNIT.DESCRIPTION}.</p>|Zabbix agent|systemd.unit.get["{#UNIT.NAME}"]|
|{#UNIT.NAME}: Active state|<p>State value that reflects whether the unit is currently active or not. The following states are currently defined: "active", "reloading", "inactive", "failed", "activating", and "deactivating".</p>|Dependent item|systemd.service.active_state["{#UNIT.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ActiveState.state`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|{#UNIT.NAME}: Load state|<p>State value that reflects whether the configuration file of this unit has been loaded. The following states are currently defined: "loaded", "error", and "masked".</p>|Dependent item|systemd.service.load_state["{#UNIT.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LoadState.state`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|{#UNIT.NAME}: Unit file state|<p>Encodes the install state of the unit file of FragmentPath. It currently knows the following states: "enabled", "enabled-runtime", "linked", "linked-runtime", "masked", "masked-runtime", "static", "disabled", and "invalid".</p>|Dependent item|systemd.service.unitfile_state["{#UNIT.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.UnitFileState.state`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|{#UNIT.NAME}: Active time|<p>Number of seconds since unit entered the active state.</p>|Dependent item|systemd.service.uptime["{#UNIT.NAME}"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Service units discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#UNIT.NAME}: Service is not running||`last(/Systemd by Zabbix agent 2/systemd.service.active_state["{#UNIT.NAME}"])<>1`|Warning|**Manual close**: Yes|
|{#UNIT.NAME}: has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Systemd by Zabbix agent 2/systemd.service.uptime["{#UNIT.NAME}"])<10m`|Info|**Manual close**: Yes|

### LLD rule Socket units discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Socket units discovery|<p>Discover systemd socket units and their details.</p>|Zabbix agent|systemd.unit.discovery[socket]|

### Item prototypes for Socket units discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#UNIT.NAME}: Get unit info|<p>Returns all properties of a systemd socket unit.</p><p> Unit description: {#UNIT.DESCRIPTION}.</p>|Zabbix agent|systemd.unit.get["{#UNIT.NAME}",Socket]|
|{#UNIT.NAME}: Connections accepted per sec|<p>The number of accepted socket connections (NAccepted) per second.</p>|Dependent item|systemd.socket.conn_accepted.rate["{#UNIT.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.NAccepted`</p></li><li>Change per second</li></ul>|
|{#UNIT.NAME}: Connections connected|<p>The current number of socket connections (NConnections).</p>|Dependent item|systemd.socket.conn_count["{#UNIT.NAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.NConnections`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

