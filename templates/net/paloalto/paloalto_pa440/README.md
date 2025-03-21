
# Palo Alto PA-440 by HTTP

## Overview

This template is designed for the effortless deployment of Palo Alto PA-440 monitoring by Zabbix via XML API and doesn't require any external scripts.

For more details about PAN-OS API, refer to the [official documentation](https://docs.paloaltonetworks.com/pan-os/11-1/pan-os-panorama-api).

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Palo Alto PA-440, PAN-OS 11.2.4-h1

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

Configure a user for monitoring. Note that in order to retrieve the device certificate information, superuser privileges are required. If you opt for a user with limited access (for security reasons), the device certificate expiration metrics will not be discovered.

Superuser privileges user (full access to all data):
1. Add a new administrator user. Go to `Device` > `Administrators` and click `Add`.
2. Enter the necessary details. Set the `Administrator Type` to `Dynamic` and select the built-in `Superuser` role. Commit the changes.

Limited privileges user (no access to device certificate data):
1. Create a new Admin Role. Go to `Device` > `Admin Role` and click `Add`.
2. Enter the necessary details. Adjust the list of permissions:
- Restrict access to all sections in the `Web UI` tab
- Allow access to the `Configuration` and `Operational Requests` sections in the `XML API` tab
- Check that the access to CLI is set to `None` in the `Command Line` tab
- Restrict access to all sections in the `REST API` tab
3. Add a new administrator user. Go to `Device` > `Administrators` and click `Add`.
4. Enter the necessary details. Set the `Administrator Type` to `Role Based` and select the profile that was created in the previous steps. Commit the changes.

Set the host macros:
1. Set the firewall XML API endpoint URL in the `{$PAN.PA440.API.URL}` macro in the format `<scheme>://<host>[:port]/api` (port is optional).
2. Set the name of the user that you created in the `{$PAN.PA440.USER}` macro.
3. Set the password of the user that you created in the `{$PAN.PA440.PASSWORD}` macro.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PAN.PA440.API.URL}|<p>The firewall XML API endpoint in the format `<scheme>://<host>[:port]/api` (port is optional).</p>||
|{$PAN.PA440.HTTP_PROXY}|<p>The HTTP proxy for HTTP agent items (set if needed). If the macro is empty, then no proxy is used.</p>||
|{$PAN.PA440.TIMEOUT}|<p>The timeout threshold for the HTTP items that retrieve data from the API.</p>|`15s`|
|{$PAN.PA440.USER}|<p>The name of the user that is used for monitoring.</p>|`zbx_monitor`|
|{$PAN.PA440.PASSWORD}|<p>The password of the user that is used for monitoring.</p>||
|{$PAN.PA440.HA.CONFIG_SYNC.THRESH}|<p>The threshold for the configuration synchronization trigger. Can be set to an evaluation period in seconds (time suffixes can be used) or an evaluation range of the latest collected values (if preceded by a hash mark).</p>|`#1`|
|{$PAN.PA440.HA.STATE.IGNORE_USER_SUSPENDED}|<p>Controls whether the HA "suspended" state trigger should fire if the state is caused by the user request. "1" - ignored, "0" - not ignored.</p>|`1`|
|{$PAN.PA440.IF.HW.IFNAME.MATCHES}|<p>The interface name regex filter to use in hardware interface discovery - for including.</p>|`.+`|
|{$PAN.PA440.IF.HW.IFNAME.NOT_MATCHES}|<p>The interface name regex filter to use in hardware interface discovery - for excluding.</p>|`^(?:tunnel\|vlan\|loopback)$`|
|{$PAN.PA440.IF.HW.CONTROL}|<p>The link status triggers will fire only for hardware interfaces where the context macro equals "1".</p>|`1`|
|{$PAN.PA440.IF.HW.ERRORS.WARN}|<p>The warning threshold of the packet error rate for hardware interfaces. Can be used with the hardware interface name as context.</p>|`2`|
|{$PAN.PA440.IF.HW.UTIL.MAX}|<p>The threshold in the hardware interface utilization triggers.</p>|`90`|
|{$PAN.PA440.IF.SW.IFNAME.MATCHES}|<p>The interface name regex filter to use in logical interface discovery - for including.</p>|`.+`|
|{$PAN.PA440.IF.SW.IFNAME.NOT_MATCHES}|<p>The interface name regex filter to use in logical interface discovery - for excluding.</p>|`^(?:tunnel\|vlan\|loopback)$`|
|{$PAN.PA440.IF.SW.IFZONE.MATCHES}|<p>The interface zone name regex filter to use in logical interface discovery - for including.</p>|`.+`|
|{$PAN.PA440.IF.SW.IFZONE.NOT_MATCHES}|<p>The interface zone name regex filter to use in logical interface discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$PAN.PA440.IF.SW.VSYS.MATCHES}|<p>The interface virtual system name regex filter to use in logical interface discovery - for including.</p>|`.+`|
|{$PAN.PA440.IF.SW.VSYS.NOT_MATCHES}|<p>The interface virtual system name regex filter to use in logical interface discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$PAN.PA440.IF.SW.ERRORS.WARN}|<p>The warning threshold of the packet error rate for logical interfaces. Can be used with the logical interface name as context.</p>|`2`|
|{$PAN.PA440.BGP.PEER.NAME.MATCHES}|<p>The BGP peer name regex filter to use in BGP peer discovery - for including.</p>|`.+`|
|{$PAN.PA440.BGP.PEER.NAME.NOT_MATCHES}|<p>The BGP peer name regex filter to use in BGP peer discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$PAN.PA440.BGP.PEER.GROUP.MATCHES}|<p>The BGP peer group regex filter to use in BGP peer discovery - for including.</p>|`.+`|
|{$PAN.PA440.BGP.PEER.GROUP.NOT_MATCHES}|<p>The BGP peer group regex filter to use in BGP peer discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$PAN.PA440.BGP.CONTROL}|<p>The BGP session triggers will fire only for peers where the context macro equals "1".</p>|`1`|
|{$PAN.PA440.OSPF.NEIGHBOR.ADDR.MATCHES}|<p>The OSPF neighbor address regex filter to use in OSPF neighbor discovery - for including.</p>|`.+`|
|{$PAN.PA440.OSPF.NEIGHBOR.ADDR.NOT_MATCHES}|<p>The OSPF neighbor address regex filter to use in OSPF neighbor discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$PAN.PA440.OSPF.NEIGHBOR.AREA.MATCHES}|<p>The OSPF neighbor area regex filter to use in OSPF neighbor discovery - for including.</p>|`.+`|
|{$PAN.PA440.OSPF.NEIGHBOR.AREA.NOT_MATCHES}|<p>The OSPF neighbor area regex filter to use in OSPF neighbor discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$PAN.PA440.OSPF.CONTROL}|<p>The OSPF neighbor triggers will fire only for neighbors where the context macro equals "1".</p>|`1`|
|{$PAN.PA440.OSPFV3.NEIGHBOR.ADDR.MATCHES}|<p>The OSPFv3 neighbor address regex filter to use in OSPFv3 neighbor discovery - for including.</p>|`.+`|
|{$PAN.PA440.OSPFV3.NEIGHBOR.ADDR.NOT_MATCHES}|<p>The OSPFv3 neighbor address regex filter to use in OSPFv3 neighbor discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$PAN.PA440.OSPFV3.NEIGHBOR.AREA.MATCHES}|<p>The OSPFv3 neighbor area regex filter to use in OSPFv3 neighbor discovery - for including.</p>|`.+`|
|{$PAN.PA440.OSPFV3.NEIGHBOR.AREA.NOT_MATCHES}|<p>The OSPFv3 neighbor area regex filter to use in OSPFv3 neighbor discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$PAN.PA440.OSPFV3.CONTROL}|<p>The OSPFv3 neighbor triggers will fire only for neighbors where the context macro equals "1".</p>|`1`|
|{$PAN.PA440.LICENSE.FEATURE.MATCHES}|<p>The license feature name regex filter to use in license discovery - for including.</p>|`.+`|
|{$PAN.PA440.LICENSE.FEATURE.NOT_MATCHES}|<p>The license feature name regex filter to use in license discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$PAN.PA440.LICENSE.DESC.MATCHES}|<p>The license feature description regex filter to use in license discovery - for including.</p>|`.+`|
|{$PAN.PA440.LICENSE.DESC.NOT_MATCHES}|<p>The license feature description regex filter to use in license discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$PAN.PA440.LICENSE.EXPIRY.WARN}|<p>The time threshold until the license expires; used in the license expiry trigger. Can be set to an evaluation period in seconds (time suffixes can be used). Can be used with the license feature name as context.</p>|`7d`|
|{$PAN.PA440.CERT.DEVICE.EXPIRY.WARN}|<p>The time threshold until the device certificate expires; used in the device certificate expiry trigger. Can be set to an evaluation period in seconds (time suffixes can be used).</p>|`7d`|
|{$PAN.PA440.CERT.NAME.MATCHES}|<p>The certificate name regex filter to use in certificate discovery - for including.</p>|`.+`|
|{$PAN.PA440.CERT.NAME.NOT_MATCHES}|<p>The certificate name regex filter to use in certificate discovery - for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$PAN.PA440.CERT.EXPIRY.WARN}|<p>The time threshold until the certificate expires; used in the certificate expiry trigger. Can be set to an evaluation period in seconds (time suffixes can be used). Can be used with the certificate name as context.</p>|`7d`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get system info|<p>Get the general system information.</p>|HTTP agent|pan.pa440.system_info.get<p>**Preprocessing**</p><ul><li>XML to JSON</li></ul>|
|Get session info|<p>Get the information about sessions.</p>|HTTP agent|pan.pa440.session_info.get<p>**Preprocessing**</p><ul><li>XML to JSON</li></ul>|
|Get system state|<p>Get the system state information. Used with a filter to retrieve CPU utilization metrics.</p>|HTTP agent|pan.pa440.system_state.get<p>**Preprocessing**</p><ul><li>XML to JSON</li></ul>|
|Get system environmentals|<p>Get the system environment state information.</p>|HTTP agent|pan.pa440.environmentals.get<p>**Preprocessing**</p><ul><li>XML to JSON</li></ul>|
|Get HA info|<p>Get the high availability information.</p>|HTTP agent|pan.pa440.ha.get<p>**Preprocessing**</p><ul><li>XML to JSON</li></ul>|
|Get OSPF neighbors|<p>Get the OSPF neighbor information.</p>|HTTP agent|pan.pa440.ospf.neighbors.get<p>**Preprocessing**</p><ul><li>XML to JSON</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get OSPFv3 neighbors|<p>Get the OSPFv3 neighbor information.</p>|HTTP agent|pan.pa440.ospfv3.neighbors.get<p>**Preprocessing**</p><ul><li>XML to JSON</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get licenses|<p>Get the information about installed licenses.</p>|HTTP agent|pan.pa440.licenses.get<p>**Preprocessing**</p><ul><li>XML to JSON</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get device certificate|<p>Get the information about the device certificate. Note that superuser privileges are required to obtain the device certificate data.</p>|HTTP agent|pan.pa440.certificate.device.get<p>**Preprocessing**</p><ul><li>XML to JSON</li></ul>|
|Get certificates|<p>Get the information about the certificates on the device.</p>|HTTP agent|pan.pa440.certificate.get<p>**Preprocessing**</p><ul><li>XML to JSON</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get system info check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|pan.pa440.system_info.get.check<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get session info check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|pan.pa440.session_info.get.check<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get system state check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|pan.pa440.system_state.get.check<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get system environmental check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|pan.pa440.environmentals.get.check<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get HA info check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|pan.pa440.ha.get.check<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get OSPF neighbor check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|pan.pa440.ospf.neighbors.get.check<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get OSPFv3 neighbor check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|pan.pa440.ospfv3.neighbors.get.check<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get license check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|pan.pa440.licenses.get.check<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get device certificate check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|pan.pa440.certificate.device.get.check<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Get certificate check|<p>Data collection check. Check the latest values for details.</p>|Dependent item|pan.pa440.certificate.get.check<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|App-ID version|<p>Currently installed application definition version. If no application definition is found, 0 is returned.</p>|Dependent item|pan.pa440.app_id.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.system['app-version']`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|App-ID release date|<p>Currently installed application definition release date. If no release date is found, the value is discarded.</p>|Dependent item|pan.pa440.app_id.release_date<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.system['app-release-date']`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|GlobalProtect client package version|<p>Currently installed GlobalProtect client package version. If package is not installed, "0.0.0" is returned.</p>|Dependent item|pan.pa440.gp.client.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Threat version|<p>Currently installed threat definition version. If no threat definition is found, "0" is returned.</p>|Dependent item|pan.pa440.threat.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.system['threat-version']`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|URL filtering version|<p>Currently installed URL filtering version. If no URL filtering is installed, "0" is returned.</p>|Dependent item|pan.pa440.url_filtering.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.system['url-filtering-version']`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|PAN-OS version|<p>Full software version. The first two components of the full version are the major and minor versions. The third component indicates the maintenance release number.</p>|Dependent item|pan.pa440.os.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.system['sw-version']`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Serial number|<p>The serial number of the unit. If not available, an empty string is returned.</p>|Dependent item|pan.pa440.serial_number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.system.serial`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Host name|<p>The host name of the system.</p>|Dependent item|pan.pa440.hostname<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.system.hostname`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Uptime|<p>The system uptime.</p>|Dependent item|pan.pa440.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.system.uptime`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Sessions: Supported, total|<p>Total number of supported sessions.</p>|Dependent item|pan.pa440.sessions.supported.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result['num-max']`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Sessions: Active, total|<p>Total number of active sessions.</p>|Dependent item|pan.pa440.sessions.active.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result['num-active']`</p></li></ul>|
|Sessions: Session table utilization, in %|<p>Session table utilization in percent.</p>|Dependent item|pan.pa440.sessions.table_util<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Sessions: TCP, active|<p>Total number of active TCP sessions.</p>|Dependent item|pan.pa440.sessions.tcp.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result['num-tcp']`</p></li></ul>|
|Sessions: UDP, active|<p>Total number of active UDP sessions.</p>|Dependent item|pan.pa440.sessions.udp.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result['num-udp']`</p></li></ul>|
|Sessions: ICMP, active|<p>Total number of active ICMP sessions.</p>|Dependent item|pan.pa440.sessions.icmp.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result['num-icmp']`</p></li></ul>|
|Data Plane: CPU utilization, in %|<p>The average percentage of time over the last minute that this processor was not idle. Implementations may approximate this one-minute smoothing period if necessary.</p>|Dependent item|pan.pa440.data_plane.cpu.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result`</p></li><li><p>Regular expression: `(?m)^sys\.monitor\.s1\.dp0\.exports:.*1minavg'?:\s*(\d+) \1`</p></li></ul>|
|Management Plane: CPU utilization, in %|<p>The average percentage of time over the last minute that this processor was not idle. Implementations may approximate this one-minute smoothing period if necessary.</p>|Dependent item|pan.pa440.management_plane.cpu.util<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result`</p></li><li><p>Regular expression: `(?m)^sys\.monitor\.s1\.mp\.exports:.*1minavg'?:\s*(\d+) \1`</p></li></ul>|
|CPU temperature|<p>The CPU temperature in degrees Celsius.</p>|Dependent item|pan.pa440.cpu.temp<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.thermal.Slot1.entry.DegreesC`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PA-440: Failed to get system info data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Palo Alto PA-440 by HTTP/pan.pa440.system_info.get.check))>0`|High||
|PA-440: Failed to get session info data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Palo Alto PA-440 by HTTP/pan.pa440.session_info.get.check))>0`|High||
|PA-440: Failed to get system state data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Palo Alto PA-440 by HTTP/pan.pa440.system_state.get.check))>0`|High||
|PA-440: Failed to get environmental data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Palo Alto PA-440 by HTTP/pan.pa440.environmentals.get.check))>0`|High||
|PA-440: Failed to get HA data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.get.check))>0`|High||
|PA-440: Failed to get OSPF neighbor data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Palo Alto PA-440 by HTTP/pan.pa440.ospf.neighbors.get.check))>0`|High||
|PA-440: Failed to get OSPFv3 neighbor data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Palo Alto PA-440 by HTTP/pan.pa440.ospfv3.neighbors.get.check))>0`|High||
|PA-440: Failed to get license data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Palo Alto PA-440 by HTTP/pan.pa440.licenses.get.check))>0`|High||
|PA-440: Failed to get device certificate data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Palo Alto PA-440 by HTTP/pan.pa440.certificate.device.get.check))>0`|High||
|PA-440: Failed to get certificate data from the API|<p>Failed to get data from the API. Check the latest values for details.</p>|`length(last(/Palo Alto PA-440 by HTTP/pan.pa440.certificate.get.check))>0`|High||

### LLD rule HA metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HA metric discovery|<p>Discovers high availability metrics.</p>|Dependent item|pan.pa440.ha.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Item prototypes for HA metric discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HA state|<p>The current state of high availability.</p><p></p><p>Information about high availability states:</p><p>https://docs.paloaltonetworks.com/pan-os/11-1/pan-os-admin/high-availability/ha-firewall-states</p>|Dependent item|pan.pa440.ha.local[state{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.group['local-info'].state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|HA state reason|<p>The reason for the current state of high availability. May be absent in the master item data in some cases; set to an empty string if not found.</p><p></p><p>Information about high availability states:</p><p>https://docs.paloaltonetworks.com/pan-os/11-1/pan-os-admin/high-availability/ha-firewall-states</p>|Dependent item|pan.pa440.ha.local[state_reason{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.group['local-info']['state-reason']`</p><p>⛔️Custom on fail: Set value to: ` `</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|HA peer state|<p>The current peer state of high availability.</p><p></p><p>Information about high availability states:</p><p>https://docs.paloaltonetworks.com/pan-os/11-1/pan-os-admin/high-availability/ha-firewall-states</p>|Dependent item|pan.pa440.ha.peer[state{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.group['peer-info'].state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|HA configuration synchronization status|<p>The current state of the running configuration synchronization.</p>|Dependent item|pan.pa440.ha[config_sync_status{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.group['running-sync']`</p></li></ul>|
|HA mode|<p>The current mode of high availability. Possible values:</p><p></p><p>0 - Active-Passive</p><p>1 - Active-Active</p><p>2 - Unknown</p>|Dependent item|pan.pa440.ha[mode{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.group.mode`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for HA metric discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PA-440: HA state has been changed|<p>The high availability state has changed. The following state transitions are checked:<br><br>1. Active-Passive HA mode:<br>- "passive" > "active"<br>- "active" > "passive"<br><br>2. Active-Active HA mode:<br>- "active-secondary" > "active-primary"<br>- "active-primary" > "active-secondary"<br><br>Information about high availability states:<br>https://docs.paloaltonetworks.com/pan-os/11-1/pan-os-admin/high-availability/ha-firewall-states</p>|`(last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state{#SINGLETON}])=1 and last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state{#SINGLETON}],#2)=2) or (last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state{#SINGLETON}])=2 and last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state{#SINGLETON}],#2)=1) or (last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state{#SINGLETON}])=3 and last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state{#SINGLETON}],#2)=4) or (last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state{#SINGLETON}])=4 and last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state{#SINGLETON}],#2)=3)`|High||
|PA-440: HA is in "non-functional" state|<p>Error state due to a dataplane failure or a configuration mismatch such as: only one firewall configured for packet forwarding, VR sync, or QoS sync.<br><br>In active/passive mode, all of the causes listed for the tentative state cause the non-functional state:<br>- Failure of a firewall.<br>- Failure of a monitored object (a link or path).<br>- The firewall leaves the suspended or non-functional state.<br><br>Information about high availability states:<br>https://docs.paloaltonetworks.com/pan-os/11-1/pan-os-admin/high-availability/ha-firewall-states</p>|`last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state{#SINGLETON}])=6 and length(last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state_reason{#SINGLETON}]))>0`|High||
|PA-440: HA is in "tentative" state|<p>State of a firewall (in an active/active configuration) caused by one of the following:<br>- Failure of a firewall.<br>- Failure of a monitored object (a link or path).<br>- The firewall leaves the suspended or non-functional state.<br><br>A firewall in the tentative state synchronizes sessions and configurations from the peer.<br><br>Information about high availability states:<br>https://docs.paloaltonetworks.com/pan-os/11-1/pan-os-admin/high-availability/ha-firewall-states</p>|`last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state{#SINGLETON}])=5 and length(last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state_reason{#SINGLETON}]))>0`|Average||
|PA-440: HA is in "suspended" state|<p>The device is disabled and won't pass data traffic; although HA communications still occur, the device doesn't participate in the HA election process. It can't move to a HA functional state without user intervention.<br><br>The following case is excluded from the trigger's logic by default (can be changed by setting the `{$PAN.PA440.HA.STATE.IGNORE_USER_SUSPENDED}` macro value to "0"): the user suspends the device for HA manually.<br><br>Information about high availability states:<br>https://docs.paloaltonetworks.com/pan-os/11-1/pan-os-admin/high-availability/ha-firewall-states</p>|`last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state{#SINGLETON}])=7 and not (find(/Palo Alto PA-440 by HTTP/pan.pa440.ha.local[state_reason{#SINGLETON}],,"iregexp","^User requested$")=1 and {$PAN.PA440.HA.STATE.IGNORE_USER_SUSPENDED}=1)`|Average||
|PA-440: Configuration is not synchronized with HA peer|<p>This trigger indicates that the configuration cannot be synchronized with the HA peer. Please debug this manually by checking the logs (Monitor > Logs > System).</p>|`count(/Palo Alto PA-440 by HTTP/pan.pa440.ha[config_sync_status{#SINGLETON}],{$PAN.PA440.HA.CONFIG_SYNC.THRESH},"iregexp","^(?:synchronized\|synchronization in progress)$")=0`|High||

### LLD rule HA link discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HA link discovery|<p>Discovers high availability link metrics.</p><p></p><p>Information about high availability links:</p><p>https://docs.paloaltonetworks.com/pan-os/11-1/pan-os-admin/high-availability/ha-concepts/ha-links-and-backup-links</p>|Dependent item|pan.pa440.ha.links.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for HA link discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HA link [{#HALINK}]: Status|<p>The current state of the high availability link.</p><p></p><p>Information about high availability links:</p><p>https://docs.paloaltonetworks.com/pan-os/11-1/pan-os-admin/high-availability/ha-concepts/ha-links-and-backup-links</p>|Dependent item|pan.pa440.ha.peer.link.state[{#HALINK}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for HA link discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PA-440: HA link [{#HALINK}]: Link down|<p>The status of the high availability link is "down".</p>|`last(/Palo Alto PA-440 by HTTP/pan.pa440.ha.peer.link.state[{#HALINK}])="down"`|High||

### LLD rule Hardware network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Hardware network interface discovery|<p>Discovers hardware network interfaces.</p>|HTTP agent|pan.pa440.if.hw.discovery<p>**Preprocessing**</p><ul><li>XML to JSON</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Hardware network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#IFNAME}]: Get data|<p>Get the interface statistics.</p>|HTTP agent|pan.pa440.if.hw.get[{#IFNAME}]<p>**Preprocessing**</p><ul><li>XML to JSON</li></ul>|
|Interface [{#IFNAME}]: Status|<p>The current state of the interface.</p>|Dependent item|pan.pa440.if.hw.status[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.hw.state`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IFNAME}]: Speed|<p>The current bandwidth of the interface. The item is created only for interfaces that report the actual speed in units of 1,000,000 bits.</p>|Dependent item|pan.pa440.if.hw.speed[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.hw.speed`</p></li><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interface [{#IFNAME}]: Bits received, per second|<p>The number of bits received per second by the interface.</p>|Dependent item|pan.pa440.if.hw.bits.in.rate[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.ifnet.counters.hw.entry.port['rx-bytes']`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#IFNAME}]: Bits sent, per second|<p>The number of bits sent per second by the interface.</p>|Dependent item|pan.pa440.if.hw.bits.out.rate[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.ifnet.counters.hw.entry.port['tx-bytes']`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|Interface [{#IFNAME}]: Inbound packets discarded, per second|<p>The number of inbound packets per second which were chosen to be discarded even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p>|Dependent item|pan.pa440.if.hw.packets.in.discards.rate[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Change per second</li></ul>|
|Interface [{#IFNAME}]: Inbound packets with errors, per second|<p>The number of inbound packets per second that contained errors preventing them from being deliverable to a higher-layer protocol.</p>|Dependent item|pan.pa440.if.hw.packets.in.errors.rate[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.ifnet.counters.hw.entry.port['rx-error']`</p></li><li>Change per second</li></ul>|
|Interface [{#IFNAME}]: Outbound packets with errors, per second|<p>The number of outbound packets per second that contained errors preventing them from being deliverable to a higher-layer protocol.</p>|Dependent item|pan.pa440.if.hw.packets.out.errors.rate[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.ifnet.counters.hw.entry.port['tx-error']`</p></li><li>Change per second</li></ul>|

### Trigger prototypes for Hardware network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PA-440: Interface [{#IFNAME}]: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operational status is "down".<br>2. `{$PAN.PA440.IF.HW.CONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is down.<br>3. `last(/TEMPLATE_NAME/METRIC)<>last(/TEMPLATE_NAME/METRIC,#2)` - the trigger fires only if the operational status has changed to "down" from some other state (it does not fire for "eternal off" interfaces).<br><br>WARNING: if closed manually - it will not fire again on the next poll because of `last(/TEMPLATE_NAME/METRIC)<>last(/TEMPLATE_NAME/METRIC,#2)`.</p>|`{$PAN.PA440.IF.HW.CONTROL:"{#IFNAME}"}=1 and last(/Palo Alto PA-440 by HTTP/pan.pa440.if.hw.status[{#IFNAME}])="down" and (last(/Palo Alto PA-440 by HTTP/pan.pa440.if.hw.status[{#IFNAME}])<>last(/Palo Alto PA-440 by HTTP/pan.pa440.if.hw.status[{#IFNAME}],#2))`|Average|**Manual close**: Yes|
|PA-440: Interface [{#IFNAME}]: High bandwidth usage|<p>The utilization of the network interface is close to its estimated maximum bandwidth.</p>|`(avg(/Palo Alto PA-440 by HTTP/pan.pa440.if.hw.bits.in.rate[{#IFNAME}],15m)>({$PAN.PA440.IF.HW.UTIL.MAX:"{#IFNAME}"}/100)*last(/Palo Alto PA-440 by HTTP/pan.pa440.if.hw.speed[{#IFNAME}]) or avg(/Palo Alto PA-440 by HTTP/pan.pa440.if.hw.bits.out.rate[{#IFNAME}],15m)>({$PAN.PA440.IF.HW.UTIL.MAX:"{#IFNAME}"}/100)*last(/Palo Alto PA-440 by HTTP/pan.pa440.if.hw.speed[{#IFNAME}])) and last(/Palo Alto PA-440 by HTTP/pan.pa440.if.hw.speed[{#IFNAME}])>0`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>PA-440: Interface [{#IFNAME}]: Link down</li></ul>|
|PA-440: Interface [{#IFNAME}]: High error rate|<p>It recovers when it is below 80% of the `{$PAN.PA440.IF.HW.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Palo Alto PA-440 by HTTP/pan.pa440.if.hw.packets.in.errors.rate[{#IFNAME}],5m)>{$PAN.PA440.IF.HW.ERRORS.WARN:"{#IFNAME}"} or min(/Palo Alto PA-440 by HTTP/pan.pa440.if.hw.packets.out.errors.rate[{#IFNAME}],5m)>{$PAN.PA440.IF.HW.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>PA-440: Interface [{#IFNAME}]: Link down</li></ul>|

### LLD rule Logical network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Logical network interface discovery|<p>Discovers logical network interfaces.</p>|HTTP agent|pan.pa440.if.sw.discovery<p>**Preprocessing**</p><ul><li>XML to JSON</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Logical network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|VSYS [{#VSYS}]: Interface [{#IFNAME}]: Get data|<p>Get the interface statistics.</p>|HTTP agent|pan.pa440.if.sw.get[{#VSYS}, {#IFNAME}]<p>**Preprocessing**</p><ul><li>XML to JSON</li></ul>|
|VSYS [{#VSYS}]: Interface [{#IFNAME}]: Bits received, per second|<p>The number of bits received per second by the interface.</p>|Dependent item|pan.pa440.if.sw.bits.in.rate[{#VSYS}, {#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.ifnet.counters.ifnet.entry.ibytes`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|VSYS [{#VSYS}]: Interface [{#IFNAME}]: Bits sent, per second|<p>The number of bits sent by the interface.</p>|Dependent item|pan.pa440.if.sw.bits.out.rate[{#VSYS}, {#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.ifnet.counters.ifnet.entry.obytes`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li></ul>|
|VSYS [{#VSYS}]: Interface [{#IFNAME}]: Inbound packets dropped, per second|<p>The number of inbound packets per second which were chosen to be dropped even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p>|Dependent item|pan.pa440.if.sw.packets.in.drops.rate[{#VSYS}, {#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.ifnet.counters.ifnet.entry.idrops`</p></li><li>Change per second</li></ul>|
|VSYS [{#VSYS}]: Interface [{#IFNAME}]: Inbound packets with errors, per second|<p>The number of inbound packets per second that contained errors preventing them from being deliverable to a higher-layer protocol.</p>|Dependent item|pan.pa440.if.sw.packets.in.errors.rate[{#VSYS}, {#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.ifnet.counters.ifnet.entry.ierrors`</p></li><li>Change per second</li></ul>|

### Trigger prototypes for Logical network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PA-440: VSYS [{#VSYS}]: Interface [{#IFNAME}]: High error rate|<p>It recovers when it is below 80% of the `{$PAN.PA440.IF.SW.ERRORS.WARN:"{#IFNAME}"}` threshold.</p>|`min(/Palo Alto PA-440 by HTTP/pan.pa440.if.sw.packets.in.errors.rate[{#VSYS}, {#IFNAME}],5m)>{$PAN.PA440.IF.SW.ERRORS.WARN:"{#IFNAME}"}`|Warning|**Manual close**: Yes|

### LLD rule BGP peer discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BGP peer discovery|<p>Discovers BGP peers.</p>|HTTP agent|pan.pa440.bgp.peer.discovery<p>**Preprocessing**</p><ul><li>XML to JSON</li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for BGP peer discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|BGP peer [{#PEER}]: Get data|<p>Get the information about the peer.</p>|HTTP agent|pan.pa440.bgp.peer.get[{#PEERGROUP}, {#PEERADDR}, {#PEER}]<p>**Preprocessing**</p><ul><li>XML to JSON</li></ul>|
|BGP peer [{#PEER}]: Status|<p>The current state of the BGP peer.</p>|Dependent item|pan.pa440.bgp.peer.status[{#PEERGROUP}, {#PEERADDR}, {#PEER}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.entry.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|BGP peer [{#PEER}]: Status duration|<p>The duration of the current state of the BGP peer.</p>|Dependent item|pan.pa440.bgp.peer.status.duration[{#PEERGROUP}, {#PEERADDR}, {#PEER}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result.entry['status-duration']`</p></li></ul>|

### Trigger prototypes for BGP peer discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PA-440: BGP peer [{#PEER}]: Session is not Established or Idle|<p>The session with the peer is not "Established" or "Idle".</p>|`{$PAN.PA440.BGP.CONTROL:"{#PEER}"}=1 and last(/Palo Alto PA-440 by HTTP/pan.pa440.bgp.peer.status[{#PEERGROUP}, {#PEERADDR}, {#PEER}])<>5 and last(/Palo Alto PA-440 by HTTP/pan.pa440.bgp.peer.status[{#PEERGROUP}, {#PEERADDR}, {#PEER}])<>0`|High||
|PA-440: BGP peer [{#PEER}]: Session status duration has been reset|<p>The duration of the session status with the peer has been reset.</p>|`{$PAN.PA440.BGP.CONTROL:"{#PEER}"}=1 and last(/Palo Alto PA-440 by HTTP/pan.pa440.bgp.peer.status.duration[{#PEERGROUP}, {#PEERADDR}, {#PEER}])<10m`|Average||

### LLD rule OSPF neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPF neighbor discovery|<p>Discovers OSPF neighbors.</p>|Dependent item|pan.pa440.ospf.neighbor.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for OSPF neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPF neighbor [{#NEIGHBORADDR}]: Status|<p>The current state of the OSPF neighbor.</p>|Dependent item|pan.pa440.ospf.neighbor.status[{#NEIGHBORAREA}, {#NEIGHBORADDR}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for OSPF neighbor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PA-440: OSPF neighbor [{#NEIGHBORADDR}]: Neighbor is not found anymore|<p>The neighbor is not found anymore and the neighborship is gone. Please investigate if this is planned.</p>|`{$PAN.PA440.OSPF.CONTROL:"{#NEIGHBORADDR}"}=1 and nodata(/Palo Alto PA-440 by HTTP/pan.pa440.ospf.neighbor.status[{#NEIGHBORAREA}, {#NEIGHBORADDR}],5m)=1`|High||
|PA-440: OSPF neighbor [{#NEIGHBORADDR}]: Status is not full or 2way|<p>The status of the neighbor is not "full" or "2way". This may indicate issues with the OSPF session.</p>|`{$PAN.PA440.OSPF.CONTROL:"{#NEIGHBORADDR}"}=1 and last(/Palo Alto PA-440 by HTTP/pan.pa440.ospf.neighbor.status[{#NEIGHBORAREA}, {#NEIGHBORADDR}])<>"full" and last(/Palo Alto PA-440 by HTTP/pan.pa440.ospf.neighbor.status[{#NEIGHBORAREA}, {#NEIGHBORADDR}])<>"2way"`|High||

### LLD rule OSPFv3 neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPFv3 neighbor discovery|<p>Discovers OSPFv3 neighbors.</p>|Dependent item|pan.pa440.ospfv3.neighbor.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for OSPFv3 neighbor discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OSPFv3 neighbor [{#NEIGHBORADDR}]: Status|<p>The current status of the OSPFv3 neighbor.</p>|Dependent item|pan.pa440.ospfv3.neighbor.status[{#NEIGHBORAREA}, {#NEIGHBORADDR}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for OSPFv3 neighbor discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PA-440: OSPFv3 neighbor [{#NEIGHBORADDR}]: Neighbor is not found anymore|<p>The neighbor is not found anymore and the neighborship is gone. Please investigate if this is planned.</p>|`{$PAN.PA440.OSPFV3.CONTROL:"{#NEIGHBORADDR}"}=1 and nodata(/Palo Alto PA-440 by HTTP/pan.pa440.ospfv3.neighbor.status[{#NEIGHBORAREA}, {#NEIGHBORADDR}],5m)=1`|High||
|PA-440: OSPFv3 neighbor [{#NEIGHBORADDR}]: Status is not full or 2way|<p>The status of the neighbor is not "full" or "2way". This may indicate issues with the OSPF session.</p>|`{$PAN.PA440.OSPFV3.CONTROL:"{#NEIGHBORADDR}"}=1 and last(/Palo Alto PA-440 by HTTP/pan.pa440.ospfv3.neighbor.status[{#NEIGHBORAREA}, {#NEIGHBORADDR}])<>"full" and last(/Palo Alto PA-440 by HTTP/pan.pa440.ospfv3.neighbor.status[{#NEIGHBORAREA}, {#NEIGHBORADDR}])<>"2way"`|High||

### LLD rule License discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|License discovery|<p>Discovers licenses installed on the device. Only the licenses with an expiration date are discovered.</p>|Dependent item|pan.pa440.licenses.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for License discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|License [{#FEATURE}]: Expires on|<p>The expiration date for the license `{#DESCRIPTION}`.</p>|Dependent item|pan.pa440.license.expires[{#FEATURE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|License [{#FEATURE}]: Expired|<p>Indicates whether the license `{#DESCRIPTION}` has expired.</p>|Dependent item|pan.pa440.license.expired[{#FEATURE}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for License discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PA-440: License [{#FEATURE}]: Expires soon|<p>The license will expire in less than `{$PAN.PA440.LICENSE.EXPIRY.WARN:"{#FEATURE}"}`.</p>|`(last(/Palo Alto PA-440 by HTTP/pan.pa440.license.expires[{#FEATURE}]) - now())<{$PAN.PA440.LICENSE.EXPIRY.WARN:"{#FEATURE}"}`|Warning||
|PA-440: License [{#FEATURE}]: Has expired|<p>The license `{#DESCRIPTION}` has expired.</p>|`last(/Palo Alto PA-440 by HTTP/pan.pa440.license.expired[{#FEATURE}])=1`|High|**Manual close**: Yes|

### LLD rule Device certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Device certificate discovery|<p>Discovers device certificate metrics. Note that superuser privileges are required to obtain the device certificate data.</p>|Dependent item|pan.pa440.certificate.device.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Device certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Device certificate: Expires in|<p>The time in seconds until the device certificate expiration.</p>|Dependent item|pan.pa440.certificate.device.expires_in[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result['device-certificate']['seconds-to-expire']`</p></li></ul>|
|Device certificate: Expires on|<p>The expiration date of the device certificate.</p>|Dependent item|pan.pa440.certificate.device.expires[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.response.result['device-certificate']['not_valid_after']`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Device certificate discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PA-440: Device certificate: Expires soon|<p>The device certificate will expire in less than `{$PAN.PA440.CERT.DEVICE.EXPIRY.WARN}`.</p>|`last(/Palo Alto PA-440 by HTTP/pan.pa440.certificate.device.expires_in[{#SINGLETON}])<{$PAN.PA440.CERT.DEVICE.EXPIRY.WARN}`|Warning||

### LLD rule Certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Certificate discovery|<p>Discovers certificates on the device. Only the certificates with an expiration date are discovered.</p>|Dependent item|pan.pa440.certificates.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Item prototypes for Certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Certificate [{#CERTNAME}]: Expires on|<p>The expiration date for the certificate.</p>|Dependent item|pan.pa440.certificate.expires[{#CERTNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Certificate discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|PA-440: Certificate [{#CERTNAME}]: Expires soon|<p>The certificate will expire in less than `{$PAN.PA440.CERT.EXPIRY.WARN:"{#CERTNAME}"}`.</p>|`(last(/Palo Alto PA-440 by HTTP/pan.pa440.certificate.expires[{#CERTNAME}]) - now())<{$PAN.PA440.CERT.EXPIRY.WARN:"{#CERTNAME}"}`|Warning|**Depends on**:<br><ul><li>PA-440: Certificate [{#CERTNAME}]: Has expired</li></ul>|
|PA-440: Certificate [{#CERTNAME}]: Has expired|<p>The certificate has expired.</p>|`(last(/Palo Alto PA-440 by HTTP/pan.pa440.certificate.expires[{#CERTNAME}]) - now())<0`|High||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

