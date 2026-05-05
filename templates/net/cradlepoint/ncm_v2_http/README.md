
# Cradlepoint NCM v2 by HTTP

## Overview

This template is designed for the effortless deployment of Cradlepoint NCM v2 monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- NetCloud Manager API v2

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Log into [NetCloud Manager](https://cradlepointecm.com).
2. Select *Tools* and then select the NetCloud API tab.
3. Select *Add* in the NCM API 2.0 section, fill in the required fields, and select *OK* to create the API keys.
4. In the Zabbix frontend, set the displayed `X-ECM-API-ID` and `X-ECM-API-KEY` values in the `{$CP.ECM.API.ID}` and `{$CP.ECM.API.KEY}` macros.
5. Go back to [NetCloud Manager](https://cradlepointecm.com) and select the API Portal link to navigate to the API Portal.
6. In the API Portal, you will see your `X-CP-API-ID` and `X-CP-API-KEY` API key values.
7. In the Zabbix frontend, set the displayed `X-CP-API-ID` and `X-CP-API-KEY` values in the `{$CP.API.ID}` and `{$CP.API.KEY}` macros.

>For more on NetCloud Manager API v2 authentication, please refer to the [vendor documentation](https://docs.cradlepoint.com/r/NCM-APIv2-Overview/Overview-of-NetCloud-API-v2).

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NCM.API.URL}|<p>NetCloud Manager API URL.</p>|`https://www.cradlepointecm.com/api/v2/`|
|{$NCM.CP.API.ID}|<p>NetCloud Manager `X-CP-API-ID`.</p>||
|{$NCM.CP.API.KEY}|<p>NetCloud Manager `X-CP-API-KEY`.</p>||
|{$NCM.ECM.API.ID}|<p>NetCloud Manager `X-ECM-API-ID`.</p>||
|{$NCM.ECM.API.KEY}|<p>NetCloud Manager `X-ECM-API-KEY`.</p>||
|{$NCM.DATA.TIMEOUT}|<p>The response timeout for the API.</p>|`15s`|
|{$NCM.RESP.DATA.LIMIT}|<p>The number of records returned by the API. Max value is `500`.</p>|`500`|
|{$NCM.DEVICES.REBOOT.TH}|<p>The maximum number of devices requiring a reboot before the trigger fires. The trigger can be disabled by setting the value to `-1`.</p>|`5`|
|{$NCM.DEVICES.UPGRADE.TH}|<p>The maximum number of devices with a pending upgrade before the trigger fires. The trigger can be disabled by setting the value to `-1`.</p>|`5`|
|{$NCM.DEVICE.NAME.MATCHES}|<p>A regular expression to filter devices by name. Only devices with names matching this regex will be monitored.</p>|`.*`|
|{$NCM.DEVICE.NAME.NOT_MATCHES}|<p>Regular expression to filter devices by name. Devices with names matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$NCM.HTTP_PROXY}|<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. See the documentation at https://www.zabbix.com/documentation/8.0/manual/config/items/itemtypes/http</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get devices|<p>Item for gathering all devices from NetCloud Manager API.</p>|Script|cradlepoint.devices.get|
|Get devices item errors|<p>Item for gathering all the data item errors.</p>|Dependent item|cradlepoint.devices.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Total devices|<p>The total number of all devices.</p>|Dependent item|cradlepoint.devices.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.length()`</p></li></ul>|
|Devices required reboot|<p>Number of devices required reboot.</p>|Dependent item|cradlepoint.devices.reboot_required<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data[?(@.reboot_required == 'true')].length()`</p></li></ul>|
|Devices with pending upgrade|<p>Number of devices with pending upgrade.</p>|Dependent item|cradlepoint.devices.upgrade_pending<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data[?(@.upgrade_pending == 'true')].length()`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cradlepoint: There are errors in the 'Get devices' metric||`length(last(/Cradlepoint NCM v2 by HTTP/cradlepoint.devices.errors))>0`|Warning||
|Cradlepoint: More than {$NCM.DEVICES.REBOOT.TH} devices required reboot||`{$NCM.DEVICES.REBOOT.TH}<>-1 and length(last(/Cradlepoint NCM v2 by HTTP/cradlepoint.devices.reboot_required))>{$NCM.DEVICES.REBOOT.TH}`|Info||
|Cradlepoint: More than {$NCM.DEVICES.UPGRADE.TH} devices awaiting upgrade||`{$NCM.DEVICES.UPGRADE.TH}<>-1 and length(last(/Cradlepoint NCM v2 by HTTP/cradlepoint.devices.upgrade_pending))>{$NCM.DEVICES.UPGRADE.TH}`|Info||

### LLD rule Devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Devices discovery|<p>Discovering devices from NetCloud Manager API.</p>|Dependent item|cradlepoint.devices.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li></ul>|

# Cradlepoint NCM v2 device by HTTP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$NCM.API.URL}|<p>NetCloud Manager API URL.</p>|`https://www.cradlepointecm.com/api/v2/`|
|{$NCM.CP.API.ID}|<p>NetCloud Manager X-CP-API-ID.</p>||
|{$NCM.CP.API.KEY}|<p>NetCloud Manager X-CP-API-KEY.</p>||
|{$NCM.ECM.API.ID}|<p>NetCloud Manager X-ECM-API-ID.</p>||
|{$NCM.ECM.API.KEY}|<p>NetCloud Manager X-ECM-API-KEY.</p>||
|{$NCM.DEVICE.ID}|<p>NetCloud Manager device ID.</p>||
|{$NCM.DATA.TIMEOUT}|<p>A response timeout for an API.</p>|`15s`|
|{$NCM.RESP.DATA.LIMIT}|<p>The number of records returned by API. Max value is 500.</p>|`500`|
|{$REBOOT.CONTROL}|<p>The macro is used as a flag to control device reboot tracking for the 'Reboot is required' trigger.</p>|`1`|
|{$SIM.PIN.CONTROL}|<p>The macro is used as a flag to control SIM PIN readiness tracking for the 'SIM PIN is not ready' trigger. Can be used with the net device ID as context.</p>|`1`|
|{$NCM.LAN.NAME.MATCHES}|<p>Regular expression to filter LANs based on their names. Only LANs with names matching this regex will be monitored.</p>|`.*`|
|{$NCM.LAN.NAME.NOT_MATCHES}|<p>Regular expression to filter LANs based on their names. LANs with names matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$NCM.LAN.STATUS.MATCHES}|<p>Regular expression to filter LANs based on their statuses. Only LANs with statuses matching this regex will be monitored.</p>|`.*`|
|{$NCM.LAN.STATUS.NOT_MATCHES}|<p>Regular expression to filter LANs based on their statuses. LANs with statuses matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$NCM.NET.DEVICE.NAME.MATCHES}|<p>Regular expression to filter Network devices based on their names. Only devices with names matching this regex will be monitored.</p>|`.*`|
|{$NCM.NET.DEVICE.NAME.NOT_MATCHES}|<p>Regular expression to filter Network devices based on their statuses. Devices with names matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$NCM.NET.DEVICE.MODE.MATCHES}|<p>Regular expression to filter Network devices based on their modes. Only devices with modes matching this regex will be monitored.</p>|`.*`|
|{$NCM.NET.DEVICE.MODE.NOT_MATCHES}|<p>Regular expression to filter Network devices based on their modes. Devices with modes matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$NCM.NET.DEVICE.TYPE.MATCHES}|<p>Regular expression to filter Network devices based on their types. Only devices with types matching this regex will be monitored.</p>|`.*`|
|{$NCM.NET.DEVICE.TYPE.NOT_MATCHES}|<p>Regular expression to filter Network devices based on their types. Devices with types matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$NCM.NET.DEVICE.CONN.MATCHES}|<p>Regular expression to filter Network devices based on their connection types. Only devices with connection types matching this regex will be monitored.</p>|`.*`|
|{$NCM.NET.DEVICE.CONN.NOT_MATCHES}|<p>Regular expression to filter Network devices based on their connection types. Devices with connection types matching this regex will be excluded from monitoring.</p>|`CHANGE_IF_NEEDED`|
|{$NCM.HTTP_PROXY}|<p>HTTP proxy for API requests. You can specify it using the format [protocol://][username[:password]@]proxy.example.com[:port]. See the documentation at https://www.zabbix.com/documentation/8.0/manual/config/items/itemtypes/http</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get device data|<p>Item for gathering device data from the NetCloud Manager API.</p>|Script|cradlepoint_device.data.get|
|Get device item errors|<p>Item for gathering device data errors.</p>|Dependent item|cradlepoint_device.data.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device state|<p>Device state.</p>|Dependent item|cradlepoint_device.state<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.state`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Device type|<p>Type of device, e.g., router or access point.</p>|Dependent item|cradlepoint_device.device_type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.device_type`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Serial number|<p>Serial number of the device.</p>|Dependent item|cradlepoint_device.serial_number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.serial_number`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Product name|<p>Full product name of the device.</p>|Dependent item|cradlepoint_device.device_model<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.full_product_name`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|MAC address|<p>MAC address of the device.</p>|Dependent item|cradlepoint_device.mac_address<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.mac`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Firmware|<p>Firmware of the device.</p>|Dependent item|cradlepoint_device.firmware<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.actual_firmware.version`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|IP address|<p>IPv4 address of the device.</p>|Dependent item|cradlepoint_device.ip_address<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.ipv4_address`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Timezone|<p>Timezone of the device.</p>|Dependent item|cradlepoint_device.timezone<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.locality`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Device latitude|<p>Latitude of the device location.</p>|Dependent item|cradlepoint_device.latitude<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.last_known_location.latitude`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Device longitude|<p>Longitude of the device location.</p>|Dependent item|cradlepoint_device.longitude<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.last_known_location.longitude`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Device description|<p>Description of the device.</p>|Dependent item|cradlepoint_device.description<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.description`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Reboot required|<p>Indicates if a reboot is required to enable additional device functionality.</p>|Dependent item|cradlepoint_device.reboot_required<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.reboot_required`</p><p>⛔️Custom on fail: Discard value</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cradlepoint: There are errors in the 'Get device data' metric||`length(last(/Cradlepoint NCM v2 device by HTTP/cradlepoint_device.data.errors))>0`|Warning||
|Cradlepoint: Device state is not online||`last(/Cradlepoint NCM v2 device by HTTP/cradlepoint_device.state)<>1`|Warning||
|Cradlepoint: Reboot is required||`{$REBOOT.CONTROL}=1 and last(/Cradlepoint NCM v2 device by HTTP/cradlepoint_device.reboot_required)=1`|Info||

### LLD rule LAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|LAN discovery|<p>Discovery of LANs via the NetCloud Manager API.</p>|Dependent item|cradlepoint_device.lan.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.lans`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li></ul>|

### Item prototypes for LAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#NAME}: Get data|<p>Item for gathering data for the `{#NAME}` LAN.</p>|Dependent item|cradlepoint_device.lan.data_get[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.lans[?(@.id == '{#ID}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|{#NAME}: IP address|<p>IP address of the LAN.</p>|Dependent item|cradlepoint_device.lan.ip_address[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ip_address`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|{#NAME}: Netmask|<p>Netmask of the LAN.</p>|Dependent item|cradlepoint_device.lan.netmask[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.netmask`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|{#NAME}: State|<p>State of the LAN, e.g., enabled or disabled.</p>|Dependent item|cradlepoint_device.lan.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.enabled`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Network device discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network device discovery|<p>Discovery of network devices connected through the router.</p>|Dependent item|cradlepoint_device.net_device.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.net_devices`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li></ul>|

### Item prototypes for Network device discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#NAME}: Get device info|<p>Item for gathering general information about the `{#NAME}` network device.</p>|Dependent item|cradlepoint_device.net_device.info_get[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.net_devices[?(@.id == '{#ID}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|{#NAME}: APN|<p>Access Point Name.</p>|Dependent item|cradlepoint_device.net_device.apn[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.apn`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: Carrier|<p>The connected carrier, if available; otherwise, the carrier the modem is configured to connect to.</p>|Dependent item|cradlepoint_device.net_device.carrier[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.carrier`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: Connection state|<p>Current connection state.</p>|Dependent item|cradlepoint_device.net_device.conn_state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connection_state`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|{#NAME}: Gateway|<p>Gateway of the network.</p>|Dependent item|cradlepoint_device.net_device.gateway[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.gateway`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: IPv4 address|<p>IPv4 address of the `{#NAME}` device.</p>|Dependent item|cradlepoint_device.net_device.ipv4_address[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ipv4_address`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: IPv6 address|<p>IPv6 address of the `{#NAME}` device.</p>|Dependent item|cradlepoint_device.net_device.ipv6_address[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ipv6_address`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: DNS0 server|<p>Primary DNS server.</p>|Dependent item|cradlepoint_device.net_device.dns0[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.dns0`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: DNS1 server|<p>Secondary DNS server.</p>|Dependent item|cradlepoint_device.net_device.dns1[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.dns1`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: LTE bandwidth|<p>Indicates the frequency width of the LTE band being used by the modem module.</p>|Dependent item|cradlepoint_device.net_device.ltebandwidth[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ltebandwidth`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: MAC address|<p>MAC address of the `{#NAME}` device.</p>|Dependent item|cradlepoint_device.net_device.mac_address[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mac`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: Netmask|<p>Netmask of the `{#NAME}` device.</p>|Dependent item|cradlepoint_device.net_device.netmask[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.netmask`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: Serial number|<p>Serial number of the `{#NAME}` device.</p>|Dependent item|cradlepoint_device.net_device.serial[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serial`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: Product name|<p>Product name of the `{#NAME}` device.</p>|Dependent item|cradlepoint_device.net_device.product_name[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mfg_product`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: Model|<p>Manufacturing model of the `{#NAME}` device.</p>|Dependent item|cradlepoint_device.net_device.model[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mfg_model`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: Firmware|<p>Firmware version of the `{#NAME}` device.</p>|Dependent item|cradlepoint_device.net_device.firmware[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|{#NAME}: PIN status|<p>Indicates the status of the SIM PIN.</p>|Dependent item|cradlepoint_device.net_device.pin_status[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pin_status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|{#NAME}: Radio frequency band|<p>Indicates the radio frequency band that is currently being used by the modem module.</p>|Dependent item|cradlepoint_device.net_device.rf_band[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rfband`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|{#NAME}: Radio frequency band 5G|<p>Indicates the 5G radio frequency band that is currently being used by the modem module.</p>|Dependent item|cradlepoint_device.net_device.rf_band_5g[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rfband5g`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|{#NAME}: Radio frequency channel|<p>Indicates the radio frequency channel that is currently being used by the modem module.</p>|Dependent item|cradlepoint_device.net_device.rf_channel[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rfchannel`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|{#NAME}: Uptime|<p>Time in seconds since the network device established its link.</p>|Dependent item|cradlepoint_device.net_device.uptime[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|{#NAME}: WiMAX realm|<p>WiMAX realm string used to connect to a WiMAX network.</p>|Dependent item|cradlepoint_device.net_device.wimax_realm[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wimax_realm`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Does not match regular expression: `^null$`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Network device discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Cradlepoint: {#NAME}: SIM PIN is not ready||`{$SIM.PIN.CONTROL:"{#ID}"}=1 and last(/Cradlepoint NCM v2 device by HTTP/cradlepoint_device.net_device.pin_status[{#ID}])<>1`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

