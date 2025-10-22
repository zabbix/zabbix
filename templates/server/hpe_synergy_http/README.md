
# HPE Synergy by HTTP

## Overview

The template to monitor HPE Synergy by HTTP.
It works without any external scripts and uses the script item.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- HPE Synergy 12000 Frame with API version 1200

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Link template to the host.
2. Set the hostname or IP address of the host in the {$HPE.SYNERGY.API.HOST} macro and configure the username and password in the {$HPE.SYNERGY.API.USERNAME} and {$HPE.SYNERGY.API.PASSWORD} macros.
3. Change the {$HPE.SYNERGY.API.SCHEME} and {$HPE.SYNERGY.API.PORT} macros if needed.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$HPE.SYNERGY.API.PASSWORD}|<p>Specify password for API.</p>||
|{$HPE.SYNERGY.API.USERNAME}|<p>Specify user name for API.</p>|`zabbix`|
|{$HPE.SYNERGY.DATA.TIMEOUT}|<p>Response timeout for API.</p>|`15s`|
|{$HPE.SYNERGY.API.SCHEME}|<p>The API scheme (http/https).</p>|`https`|
|{$HPE.SYNERGY.API.HOST}|<p>The hostname or IP address of the API host.</p>||
|{$HPE.SYNERGY.API.PORT}|<p>The API port.</p>|`443`|
|{$HPE.SYNERGY.API.LOGIN_DOMAIN}|<p>User domain.</p>|`LOCAL`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get data|<p>The JSON with the result from requests to API.</p>|Script|hpe.synergy.get.data|
|Get errors|<p>A list of errors from API requests.</p>|Dependent item|hpe.synergy.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.errors`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get enclosures data|<p>A list of enclosures.</p>|Dependent item|hpe.synergy.get.enclosures<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.enclosures`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get datacenters data|<p>Data of the datacenters.</p>|Dependent item|hpe.synergy.get.datacenters<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.datacenters`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get ethernet networks data|<p>Data of the ethernet networks.</p>|Dependent item|hpe.synergy.get.ethernet_networks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["ethernet-networks"]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get fabrics data|<p>Data of the fabrics.</p>|Dependent item|hpe.synergy.get.fabrics<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fabrics`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get FC networks data|<p>Data of the FC networks.</p>|Dependent item|hpe.synergy.get.fc_networks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["fc-networks"]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get hypervisor managers data|<p>Data of the hypervisor managers.</p>|Dependent item|hpe.synergy.get.hypervisor_managers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["hypervisor-managers"]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get interconnects data|<p>Data of the interconnects.</p>|Dependent item|hpe.synergy.get.interconnects<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.interconnects`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get logical enclosures data|<p>Data of the logical enclosures.</p>|Dependent item|hpe.synergy.get.logical_enclosures<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["logical-enclosures"]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get racks data|<p>Data of the racks.</p>|Dependent item|hpe.synergy.get.racks<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.racks`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get server hardware data|<p>Data of the server hardware.</p>|Dependent item|hpe.synergy.get.server_hardware<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["server-hardware"]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get storage pools data|<p>Data of the storage pools.</p>|Dependent item|hpe.synergy.get.storage_pools<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["storage-pools"]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get storage systems data|<p>Data of the storage systems.</p>|Dependent item|hpe.synergy.get.storage_systems<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["storage-systems"]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get storage volumes data|<p>Data of the storage volumes.</p>|Dependent item|hpe.synergy.get.storage_volumes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["storage-volumes"]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Get uplink sets data|<p>Data of the uplink sets.</p>|Dependent item|hpe.synergy.get.uplink_sets<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.["uplink-sets"]`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Service ping|<p>Checks if the service is running and accepting the TCP connections.</p>|Simple check|net.tcp.service["{$HPE.SYNERGY.API.SCHEME}","{$HPE.SYNERGY.API.HOST}","{$HPE.SYNERGY.API.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: There are errors in requests to API|<p>Zabbix has received errors from API.</p>|`length(last(/HPE Synergy by HTTP/hpe.synergy.get.errors))>0`|Average|**Depends on**:<br><ul><li>HPE Synergy: Service is unavailable</li></ul>|
|HPE Synergy: Service is unavailable||`max(/HPE Synergy by HTTP/net.tcp.service["{$HPE.SYNERGY.API.SCHEME}","{$HPE.SYNERGY.API.HOST}","{$HPE.SYNERGY.API.PORT}"],5m)=0`|High|**Manual close**: Yes|

### LLD rule Appliance bays discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Appliance bays discovery|<p>A list of the appliance bays in the enclosure.</p>|Dependent item|hpe.synergy.appliances.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members.[0].applianceBays`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Appliance bays discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Appliance bay [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Get data|<p>Data of the appliance bay [{#ENCLOSURE_NAME}:{#BAY_NUMBER}].</p>|Dependent item|hpe.synergy.appliance["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Appliance bay [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Model|<p>The model name of the appliance.</p>|Dependent item|hpe.synergy.appliance["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",model]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Appliance bay [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Part number|<p>The part number of the appliance.</p>|Dependent item|hpe.synergy.appliance["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.partNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Appliance bay [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Powered on|<p>*Yes*, if the appliance is powered on; *false*, otherwise.</p>|Dependent item|hpe.synergy.appliance["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",powered_on]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.poweredOn`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Appliance bay [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Power state|<p>The power state of the appliance bay.</p><p></p><p>*EFuse* - the power state of the bay - it has been EFused.</p><p>*Reset* - the power state of the bay - it has been reset.</p><p>*SoftReset* - the power state of the bay - it has been softly reset.</p><p>*Unknown* - the power state of the bay is unknown.</p>|Dependent item|hpe.synergy.appliance["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",bay_power_state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bayPowerState`</p></li><li><p>Replace: `EFuse -> 0`</p></li><li><p>Replace: `SoftReset -> 1`</p></li><li><p>Replace: `Reset -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>In range: `0 -> 3`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|
|Appliance bay [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Presence|<p>Indicates whether an appliance is present in the bay:</p><p></p><p>*Absent* - the device slot is empty;</p><p>*PresenceNoOp* - the device slot is uninitialized;</p><p>*PresenceUnknown* - the device presence is unknown;</p><p>*Present* - the device slot has a device in it;</p><p>*Subsumed* - the device slot is configured to be part of another device slot. Not applicable for the fan or power supply bays.</p>|Dependent item|hpe.synergy.appliance["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",presence]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.devicePresence`</p></li><li><p>Replace: `Absent -> 0`</p></li><li><p>Replace: `PresenceNoOp -> 1`</p></li><li><p>Replace: `PresenceUnknown -> 2`</p></li><li><p>Replace: `Present -> 3`</p></li><li><p>Replace: `Subsumed -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `2`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Appliance bay [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Serial number|<p>The serial number of the appliance.</p>|Dependent item|hpe.synergy.appliance["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serialNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Appliance bay [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Spare part number|<p>The spare part number of the appliance.</p>|Dependent item|hpe.synergy.appliance["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",spare_part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sparePartNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Appliance bay [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Status|<p>The hardware status of the appliance:</p><p></p><p>*Critical* - requires immediate attention;</p><p>*Disabled* - the resource is currently not operational;</p><p>*OK* - indicates normal/informational behavior;</p><p>*Unknown* - the health status is not yet known or cannot be determined;</p><p>*Warning* - requires attention soon.</p>|Dependent item|hpe.synergy.appliance["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Appliance bays discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Appliance bay [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Has critical status|<p>The appliance [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] status is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.appliance["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=0`|High||
|HPE Synergy: Appliance bay [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Has warning status|<p>The appliance [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] status is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.appliance["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=4`|Warning||
|HPE Synergy: Appliance bay [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Is disabled|<p>The appliance [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is currently not operational</p>|`last(/HPE Synergy by HTTP/hpe.synergy.appliance["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=1`|Info||

### LLD rule Cross bars discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cross bars discovery|<p>SDX cross fabric module connects to all computing devices installed in the system enclosure and brings in the capability of hard partitioning. Crossbar details are relevant only for enclosures with type "SDX".</p>|Dependent item|hpe.synergy.crossbars.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members.[0].crossBars`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Cross bars discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Get data|<p>Crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] data</p>|Dependent item|hpe.synergy.crossbar["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: HW version|<p>The hardware version.</p>|Dependent item|hpe.synergy.crossbar["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",hw_version]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hwVersion`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Part number|<p>The part number provided by the manufacturer.</p>|Dependent item|hpe.synergy.crossbar["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.partNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Presence|<p>The presence in a bay:</p><p></p><p>*Absent* - the device slot is empty;</p><p>*PresenceNoOp* - the device slot is uninitialized;</p><p>*PresenceUnknown* - the device presence is unknown;</p><p>*Present* - the device slot has a device in it;</p><p>*Subsumed* - the device slot is configured to be part of another device slot. Not applicable for the fan or power supply bays.</p>|Dependent item|hpe.synergy.crossbar["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",presence]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.presence`</p></li><li><p>Replace: `Absent -> 0`</p></li><li><p>Replace: `PresenceNoOp -> 1`</p></li><li><p>Replace: `PresenceUnknown -> 2`</p></li><li><p>Replace: `Present -> 3`</p></li><li><p>Replace: `Subsumed -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `2`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Serial number|<p>A serial number.</p>|Dependent item|hpe.synergy.crossbar["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serialNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Status|<p>The overall health status of the crossbar:</p><p></p><p>*Critical* - requires immediate attention;</p><p>*Disabled* - the resource is currently not operational;</p><p>*OK* - indicates normal/informational behavior;</p><p>*Unknown* - the health status is not yet known or cannot be determined;</p><p>*Warning* - requires attention soon.</p>|Dependent item|hpe.synergy.crossbar["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Cross bars discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Is subsumed|<p>The device slot is configured to be part of another device slot.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.crossbar["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",presence])=4`|Average||
|HPE Synergy: Crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Has critical status|<p>The crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] status is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.crossbar["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=0`|High||
|HPE Synergy: Crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Has warning status|<p>The crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] status is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.crossbar["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=4`|Warning||
|HPE Synergy: Crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Is disabled|<p>The crossbar [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.crossbar["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=1`|Info||

### LLD rule Datacenters discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Datacenters discovery|<p>A list of the datacenters.</p>|Dependent item|hpe.synergy.datacenters.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Datacenters discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Datacenter [{#NAME}]: Get data|<p>Data of the datacenter [{#NAME}].</p>|Dependent item|hpe.synergy.datacenter["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.name == "{#NAME}")].first()`</p></li></ul>|
|Datacenter [{#NAME}]: State|<p>The current state of the resource. The valid values include Adding, AddError, Configured, CredentialError, Refreshing, RefreshError, Removing, RemoveError, and Unmanaged.</p>|Dependent item|hpe.synergy.datacenter["{#NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Replace: `Adding -> 0`</p></li><li><p>Replace: `AddError -> 1`</p></li><li><p>Replace: `Configured -> 2`</p></li><li><p>Replace: `CredentialError -> 3`</p></li><li><p>Replace: `Refreshing -> 4`</p></li><li><p>Replace: `RefreshError -> 5`</p></li><li><p>Replace: `Removing -> 6`</p></li><li><p>Replace: `RemoveError -> 7`</p></li><li><p>Replace: `Unmanaged -> 8`</p></li><li><p>In range: `0 -> 8`</p><p>⛔️Custom on fail: Set value to: `9`</p></li></ul>|
|Datacenter [{#NAME}]: Status|<p>The overall health status of the resource. The following are the valid values for the status of the resource:</p><p></p><p>*OK* - indicates normal/informational behavior;</p><p>*Disabled* - indicates that a resource is not operational;</p><p>*Warning* - requires attention soon;</p><p>*Critical* - requires immediate attention;</p><p>*Unknown* - should be avoided, but there may be rare occasions when the status is unknown.</p>|Dependent item|hpe.synergy.datacenter["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Datacenters discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Datacenter [{#NAME}]: Add error|<p>The adding of the datacenter [{#NAME}] has failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.datacenter["{#NAME}",state])=1`|Average||
|HPE Synergy: Datacenter [{#NAME}]: Has credential error|<p>The datacenter [{#NAME}] has a credential error.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.datacenter["{#NAME}",state])=3`|Average||
|HPE Synergy: Datacenter [{#NAME}]: Has refresh error|<p>The datacenter [{#NAME}] has a refresh error.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.datacenter["{#NAME}",state])=5`|Average||
|HPE Synergy: Datacenter [{#NAME}]: Has remove error|<p>The datacenter [{#NAME}] has a remove error.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.datacenter["{#NAME}",state])=7`|Average||
|HPE Synergy: Datacenter [{#NAME}]: Has critical status|<p>The datacenter [{#NAME}] status is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.datacenter["{#NAME}",status])=0`|High||
|HPE Synergy: Datacenter [{#NAME}]: Has warning status|<p>The datacenter [{#NAME}] status is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.datacenter["{#NAME}",status])=4`|Warning||
|HPE Synergy: Datacenter [{#NAME}]: Is disabled|<p>the datacenter [{#NAME}] currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.datacenter["{#NAME}",status])=1`|Info||

### LLD rule Devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Devices discovery|<p>A list of device bays in the enclosure.</p>|Dependent item|hpe.synergy.devices.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members.[0].deviceBays`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Devices discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Device [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Get data|<p>Data of the device [{#ENCLOSURE_NAME}:{#BAY_NUMBER}].</p>|Dependent item|hpe.synergy.device["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Device [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Model|<p>The model name of an unsupported device occupying the bay if available.</p>|Dependent item|hpe.synergy.device["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",model]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Device [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Power allocated|<p>The power allocated for the enclosed blade.</p>|Dependent item|hpe.synergy.device["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",power_allocation]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.powerAllocationWatts`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Presence|<p>Indicates whether a device is present:</p><p></p><p>*Absent* - the device slot is empty;</p><p>*PresenceNoOp* - the device slot is uninitialized;</p><p>*PresenceUnknown* - the device presence is unknown;</p><p>*Present* - the device slot has a device in it;</p><p>*Subsumed* - the device slot is configured to be part of another device slot. Not applicable for the fan or power supply bays.</p>|Dependent item|hpe.synergy.device["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",presence]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.devicePresence`</p></li><li><p>Replace: `Absent -> 0`</p></li><li><p>Replace: `PresenceNoOp -> 1`</p></li><li><p>Replace: `PresenceUnknown -> 2`</p></li><li><p>Replace: `Present -> 3`</p></li><li><p>Replace: `Subsumed -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `2`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Serial number|<p>If available, the serial number of any device occupying the bay.</p>|Dependent item|hpe.synergy.device["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serialNumber`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Trigger prototypes for Devices discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Device [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Is subsumed|<p>The device slot is configured to be part of another device slot.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.device["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",presence])=4`|Average||

### LLD rule Enclosures discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Enclosures discovery|<p>A list of enclosures resources.</p>|Dependent item|hpe.synergy.enclosures.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Enclosures discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Enclosure [{#NAME}]: Get data|<p>Data of the enclosure [{#NAME}].</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.name == "{#NAME}")].first()`</p></li></ul>|
|Enclosure [{#NAME}]: Appliance bays count|<p>The number of the appliance bays in the enclosure.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",appliance_bay_count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.applianceBayCount`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Enclosure [{#NAME}]: Device bays count|<p>The number of the device bays in the enclosure.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",device_bay_count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deviceBayCount`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Enclosure [{#NAME}]: Device bays power|<p>The amount of power allocated for the blades in the enclosure.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",device_bay_watts]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deviceBayWatts`</p></li></ul>|
|Enclosure [{#NAME}]: Fan bays count|<p>The number of the fan bays in the enclosure.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",fan_bay_count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fanBayCount`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Enclosure [{#NAME}]: Firmware baseline|<p>The name of the current firmware baseline.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",fw_baseline_name]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fwBaselineName`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Enclosure [{#NAME}]: Interconnect bays count|<p>The number of the interconnect bays in the enclosure.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",interconnect_bay_count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.interconnectBayCount`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Enclosure [{#NAME}]: Interconnect bays power|<p>The amount of power allocated for the interconnects in the enclosure.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",interconnect_bay_watts]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.interconnectBayWatts`</p></li></ul>|
|Enclosure [{#NAME}]: Min power supplies|<p>The minimum number of the power supplies needed.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",min_ps]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.minimumPowerSupplies`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Enclosure [{#NAME}]: Min power supplies for redundant power feed|<p>The minimum number of the power supplies needed to fulfill the redundant line feed power mode.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",min_ps_redundant]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.minimumPowerSuppliesForRedundantPowerFeed`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Enclosure [{#NAME}]: Model|<p>The enclosure model name, for example, "BladeSystem c7000 Enclosure G2.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",model]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.enclosureModel`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Enclosure [{#NAME}]: Part number|<p>The part number of the enclosure.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.partNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Enclosure [{#NAME}]: Power allocated for fans and management devices|<p>The amount of the power allocated for the fans and management devices of the enclosure.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",fans_mgmt_power]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fansAndManagementDevicesWatts`</p></li></ul>|
|Enclosure [{#NAME}]: Power capacity|<p>The power capacity based on power mode.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",power_capacity]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.powerCapacityWatts`</p></li></ul>|
|Enclosure [{#NAME}]: Power supply bays count|<p>The number of the power supply bays in the enclosure.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",ps_bay_count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.powerSupplyBayCount`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Enclosure [{#NAME}]: Serial number|<p>The serial number of the enclosure.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serialNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Enclosure [{#NAME}]: State|<p>The current resource state of the enclosure:</p><p></p><p>*Adding* - the enclosure is being added;</p><p>*Configured* - the enclosure is configured and is a part of the logical enclosure. This is the usual state for an enclosure under full management;</p><p>*Configuring* - a transient state while the enclosure is being configured for a logical enclosure;</p><p>*Interrupted* - the previous operation on the enclosure did not complete. The operation should be re-attempted;</p><p>*Monitored* - the enclosure is being monitored. It is not a part of the logical enclosure and only hardware-control operations are available;</p><p>*Pending* - there are pending operations on the enclosure. Additional operations are denied;</p><p>*RemoveFailed* - the previous operation to remove the enclosure did not succeed. The operation should be re-attempted;</p><p>*Removing* - the enclosure is being removed;</p><p>*Unmanaged* - the enclosure has been discovered, but has not yet been added for the management or monitoring;</p><p>*Unsupported* - the enclosure model or version is not currently supported by HPE OneView. It cannot be configured or monitored.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Replace: `Adding -> 0`</p></li><li><p>Replace: `Configured -> 1`</p></li><li><p>Replace: `Configuring -> 2`</p></li><li><p>Replace: `Interrupted -> 3`</p></li><li><p>Replace: `Monitored -> 4`</p></li><li><p>Replace: `Pending -> 5`</p></li><li><p>Replace: `RemoveFailed -> 6`</p></li><li><p>Replace: `Removing -> 7`</p></li><li><p>Replace: `Unmanaged -> 8`</p></li><li><p>Replace: `Unsupported -> 9`</p></li><li><p>In range: `0 -> 9`</p><p>⛔️Custom on fail: Set value to: `10`</p></li></ul>|
|Enclosure [{#NAME}]: State reason|<p>Indicates the reason why the resource in its current state:</p><p></p><p>*Missing* - the enclosure is no longer connected into the frame link topology;</p><p>*None* - no reason is available, or none applies;</p><p>*NotAdded* - the enclosure has not been added;</p><p>*NotOwner* - the enclosure reports being managed by something other than this HPE OneView;</p><p>*OperationFailed* - a prior operation was interrupted;</p><p>*Unowned* - the enclosure reports are not being under the management;</p><p>*UnsupportedFirmware* - the firmware version of the enclosure is not supported by this version of HPE OneView;</p><p>*UpdatingFirmware* - a firmware update is in progress.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",state_reason]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stateReason`</p></li><li><p>Replace: `Missing -> 0`</p></li><li><p>Replace: `None -> 1`</p></li><li><p>Replace: `NotAdded -> 2`</p></li><li><p>Replace: `NotOwner -> 3`</p></li><li><p>Replace: `OperationFailed -> 4`</p></li><li><p>Replace: `Unowned -> 5`</p></li><li><p>Replace: `UnsupportedFirmware -> 6`</p></li><li><p>Replace: `UpdatingFirmware -> 7`</p></li><li><p>In range: `0 -> 7`</p><p>⛔️Custom on fail: Set value to: `8`</p></li></ul>|
|Enclosure [{#NAME}]: Status|<p>The overall health status of the enclosure.</p><p>The enclosure status reflects the hardware health of the enclosure, all the bays, and the enclosure components (e.g. the enclosure mid-plane, fans, power supplies, Synergy Frame Link Modules, and Synergy Composers). It explicitly does not include the status of the other HPE OneView resources such as the blades (server hardware), the interconnects, and the drive enclosures.</p><p></p><p>*Critical* - requires immediate attention.</p><p>*Disabled* - the resource is currently not operational.</p><p>*OK* - indicates normal/informational behavior.</p><p>*Unknown* - the health status is not yet known or cannot be determined.</p><p>*Warning* - requires attention soon.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|
|Enclosure [{#NAME}]: Total allocated power|<p>The total amount of the power allocated in the enclosure.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",power_total_allocated]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.powerAllocatedWatts`</p></li></ul>|
|Enclosure [{#NAME}]: Total available power|<p>The amount of the unallocated power in the enclosure.</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",power_total_available]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.powerAvailableWatts`</p></li></ul>|
|Enclosure [{#NAME}]: Type|<p>The type of the enclosure, for example, "C7000" or "SY12000" or "SDX".</p>|Dependent item|hpe.synergy.enclosure["{#NAME}",type]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.enclosureType`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Enclosures discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Enclosure [{#NAME}]: Is interrupted|<p>The previous operation on the enclosure did not complete. The operation should be re-attempted.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.enclosure["{#NAME}",state])=3 and last(/HPE Synergy by HTTP/hpe.synergy.enclosure["{#NAME}",state_reason])>-1`|Warning||
|HPE Synergy: Enclosure [{#NAME}]: Is unsupported|<p>The enclosure model or version is not currently supported by HPE OneView. It cannot be configured or monitored.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.enclosure["{#NAME}",state])=9 and last(/HPE Synergy by HTTP/hpe.synergy.enclosure["{#NAME}",state_reason])>-1`|Average||
|HPE Synergy: Enclosure [{#NAME}]: Remove failed|<p>The previous operation to remove the enclosure did not succeed. The operation should be re-attempted.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.enclosure["{#NAME}",state])=6 and last(/HPE Synergy by HTTP/hpe.synergy.enclosure["{#NAME}",state_reason])>-1`|Warning||
|HPE Synergy: Enclosure [{#NAME}]: Is missing|<p>The enclosure is no longer connected into the frame link topology.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.enclosure["{#NAME}",state_reason])=0`|Average||
|HPE Synergy: Enclosure [{#NAME}]: Is unowned|<p>The enclosure reports are not being under the management.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.enclosure["{#NAME}",state_reason])=5`|Average||
|HPE Synergy: Enclosure [{#NAME}]: Has critical status|<p>The status of the enclosure [{#NAME}] is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.enclosure["{#NAME}",status])=0`|High||
|HPE Synergy: Enclosure [{#NAME}]: Has warning status|<p>The status of the enclosure [{#NAME}] is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.enclosure["{#NAME}",status])=4`|Warning||
|HPE Synergy: Enclosure [{#NAME}]: Is disabled|<p>The enclosure [{#NAME}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.enclosure["{#NAME}",status])=1`|Info||

### LLD rule Ethernet networks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ethernet networks discovery|<p>A list of the ethernet networks.</p>|Dependent item|hpe.synergy.ethernet.networks.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Ethernet networks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Ethernet network [{#NAME}]: Get data|<p>Data of the ethernet network [{#NAME}].</p>|Dependent item|hpe.synergy.ethernet.network["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.name == "{#NAME}")].first()`</p></li></ul>|
|Ethernet network [{#NAME}]: State|<p>The current state of the resource.</p>|Dependent item|hpe.synergy.ethernet.network["{#NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Ethernet network [{#NAME}]: Status|<p>The overall health status of the resource. The following are the valid values for the status of the resource:</p><p></p><p>*OK* - indicates normal/informational behavior;</p><p>*Disabled* - indicates that the resource is not operational;</p><p>*Warning* - requires attention soon;</p><p>*Critical* - requires immediate attention;</p><p>*Unknown* - should be avoided, but there may be rare occasions when the status is unknown.</p>|Dependent item|hpe.synergy.ethernet.network["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Ethernet networks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Ethernet network [{#NAME}]: Has critical status|<p>The ethernet network [{#NAME}] status is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.ethernet.network["{#NAME}",status])=0`|High||
|HPE Synergy: Ethernet network [{#NAME}]: Has warning status|<p>The ethernet network [{#NAME}] status is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.ethernet.network["{#NAME}",status])=4`|Warning||
|HPE Synergy: Ethernet network [{#NAME}]: Is disabled|<p>The ethernet network [{#NAME}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.ethernet.network["{#NAME}",status])=1`|Info||

### LLD rule Fabrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fabrics discovery|<p>A list of the fabrics.</p>|Dependent item|hpe.synergy.fabrics.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Fabrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fabric [{#NAME}]: Get data|<p>Data of the fabric [{#NAME}].</p>|Dependent item|hpe.synergy.fabric["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.name == "{#NAME}")].first()`</p></li></ul>|
|Fabric [{#NAME}]: State|<p>The current state of the resource.</p>|Dependent item|hpe.synergy.fabric["{#NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Fabric [{#NAME}]: Status|<p>The overall health status of the resource. The following are the valid values for the status of the resource:</p><p></p><p>*OK* - indicates normal/informational behavior;</p><p>*Disabled* - indicates that the resource is not operational;</p><p>*Warning* - requires attention soon;</p><p>*Critical* - requires immediate attention;</p><p>*Unknown* - should be avoided, but there may be rare occasions when the status is unknown.</p>|Dependent item|hpe.synergy.fabric["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Fabrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Fabric [{#NAME}]: Has critical status|<p>The status of the fabric [{#NAME}] is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.fabric["{#NAME}",status])=0`|High||
|HPE Synergy: Fabric [{#NAME}]: Has warning status|<p>The status of the fabric [{#NAME}] is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.fabric["{#NAME}",status])=4`|Warning||
|HPE Synergy: Fabric [{#NAME}]: Is disabled|<p>The status of the fabric [{#NAME}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.fabric["{#NAME}",status])=1`|Info||

### LLD rule Fans discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fans discovery|<p>A list of the fan bays in the enclosure.</p>|Dependent item|hpe.synergy.fans.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members.[0].fanBays`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Fans discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Get data|<p>Data of the fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}].</p>|Dependent item|hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Model|<p>The common descriptive model of the fan.</p>|Dependent item|hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",model]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Part number|<p>The part number of the fan.</p>|Dependent item|hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.partNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Presence|<p>Indicates whether a fan is present:</p><p></p><p>*Absent* - the device slot is empty;</p><p>*PresenceNoOp* - the device slot is uninitialized;</p><p>*PresenceUnknown* - the device presence is unknown;</p><p>*Present* - the device slot has a device in it;</p><p>*Subsumed* - the device slot is configured to be part of another device slot. Not applicable for the fan or power supply bays.</p>|Dependent item|hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",presence]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.devicePresence`</p></li><li><p>Replace: `Absent -> 0`</p></li><li><p>Replace: `PresenceNoOp -> 1`</p></li><li><p>Replace: `PresenceUnknown -> 2`</p></li><li><p>Replace: `Present -> 3`</p></li><li><p>Replace: `Subsumed -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `2`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Required|<p>Indicates whether the enclosure configuration requires a fan to be present in the bay.</p>|Dependent item|hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",required]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deviceRequired`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Serial number|<p>The serial number of the fan.</p>|Dependent item|hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serialNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Spare part number|<p>The spare part number to be used when ordering an additional or replacement fan of this type.</p>|Dependent item|hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",spare_part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sparePartNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: State|<p>The current state of the fan:</p><p></p><p>*Degraded* - a fan is degraded;</p><p>*Failed* -  a fan has failed;</p><p>*Misplaced* - a fan is present, but not required in this bay, and the overall fan configuration is not compliant with the enclosure fan placement rules;</p><p>*Missing* - a fan is required, but is not present;</p><p>*OK* - a fan bay has no issues;</p><p>*Unknown* - the state of a fan is unknown.</p>|Dependent item|hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p><p>⛔️Custom on fail: Set value to: `5`</p></li><li><p>Replace: `Degraded -> 0`</p></li><li><p>Replace: `Failed -> 1`</p></li><li><p>Replace: `Misplaced -> 2`</p></li><li><p>Replace: `Missing -> 3`</p></li><li><p>Replace: `OK -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `5`</p></li></ul>|
|Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Status|<p>The overall health status of the fan:</p><p></p><p>*Critical* - requires immediate attention;</p><p>*Disabled* - the resource is currently not operational;</p><p>*OK* - indicates normal/informational behavior;</p><p>*Unknown* - the health status is not yet known or cannot be determined;</p><p>*Warning* - requires attention soon.</p>|Dependent item|hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Fans discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Is degraded|<p>The fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is in degraded state.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",state])=0`|Average||
|HPE Synergy: Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Is failed|<p>The fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is in failed state.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",state])=1`|High||
|HPE Synergy: Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Is misplaced|<p>The fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is misplaced.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",state])=2`|Warning||
|HPE Synergy: Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Is missing|<p>The fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is missing.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",state])=3`|Average||
|HPE Synergy: Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Has critical status|<p>The fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] status is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=0`|High||
|HPE Synergy: Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Has warning status|<p>The fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] status is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=4`|Warning||
|HPE Synergy: Fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Is disabled|<p>The fan [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.fan["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=1`|Info||

### LLD rule FC networks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FC networks discovery|<p>A list of the FC networks.</p>|Dependent item|hpe.synergy.fc.networks.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for FC networks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FC network [{#NAME}]: Get data|<p>Data of the FC network [{#NAME}].</p>|Dependent item|hpe.synergy.fc.network["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.name == "{#NAME}")].first()`</p></li></ul>|
|FC network [{#NAME}]: State|<p>The current state of the resource.</p>|Dependent item|hpe.synergy.fc.network["{#NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|FC network [{#NAME}]: Status|<p>The overall health status of the resource. The following are the valid values for the status of the resource:</p><p></p><p>*OK* - indicates normal/informational behavior;</p><p>*Disabled* - indicates that the resource is not operational;</p><p>*Warning* - requires attention soon;</p><p>*Critical* - requires immediate attention;</p><p>*Unknown* - should be avoided, but there may be rare occasions when the status is unknown.</p>|Dependent item|hpe.synergy.fc.network["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for FC networks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: FC network [{#NAME}]: Has critical status|<p>The FC network [{#NAME}] status is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.fc.network["{#NAME}",status])=0`|High||
|HPE Synergy: FC network [{#NAME}]: Has warning status|<p>The FC network [{#NAME}] status is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.fc.network["{#NAME}",status])=4`|Warning||
|HPE Synergy: FC network [{#NAME}]: Is disabled|<p>The FC network [{#NAME}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.fc.network["{#NAME}",status])=1`|Info||

### LLD rule Hypervisor managers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Hypervisor managers discovery|<p>A list of the hypervisor managers.</p>|Dependent item|hpe.synergy.hypervisor.managers.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Hypervisor managers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Hypervisor manager [{#NAME}]: Get data|<p>Data of the hypervisor manager [{#NAME}].</p>|Dependent item|hpe.synergy.hypervisor_manager["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.displayName == "{#NAME}")].first()`</p></li></ul>|
|Hypervisor manager [{#NAME}]: State|<p>The current state of the resource. The valid values include Connected, Disconnected, Configuring and Error.</p>|Dependent item|hpe.synergy.hypervisor_manager["{#NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Replace: `Connected -> 0`</p></li><li><p>Replace: `Disconnected -> 1`</p></li><li><p>Replace: `Configuring -> 2`</p></li><li><p>Replace: `Error -> 3`</p></li><li><p>In range: `0 -> 3`</p><p>⛔️Custom on fail: Set value to: `4`</p></li></ul>|
|Hypervisor manager [{#NAME}]: State reason|<p>Indicates the reason why the resource is in its current state.</p>|Dependent item|hpe.synergy.hypervisor_manager["{#NAME}",state_reason]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stateReason`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Hypervisor manager [{#NAME}]: Status|<p>The current status of this resource:</p><p></p><p>*Critical* - requires immediate attention;</p><p>*Disabled* - the resource is currently not operational;</p><p>*OK* - indicates normal/informational behavior;</p><p>*Unknown* - the health status is not yet known or cannot be determined;</p><p>*Warning* - requires attention soon.</p>|Dependent item|hpe.synergy.hypervisor_manager["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Hypervisor managers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Hypervisor manager [{#NAME}]: Is in error state|<p>The hypervisor manager [{#NAME}] has an error.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.hypervisor_manager["{#NAME}",state])=3 and length(last(/HPE Synergy by HTTP/hpe.synergy.hypervisor_manager["{#NAME}",state_reason]))>0`|High||
|HPE Synergy: Hypervisor manager [{#NAME}]: Has critical status|<p>The hypervisor manager [{#NAME}] status is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.hypervisor_manager["{#NAME}",status])=0`|High||
|HPE Synergy: Hypervisor manager [{#NAME}]: Has warning status|<p>The hypervisor manager [{#NAME}] status is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.hypervisor_manager["{#NAME}",status])=4`|Warning||
|HPE Synergy: Hypervisor manager [{#NAME}]: Is disabled|<p>The hypervisor manager [{#NAME}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.hypervisor_manager["{#NAME}",status])=1`|Info||

### LLD rule Interconnects discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interconnects discovery|<p>Interconnects are centrally managed by their containing logical interconnect. The interconnect provides a physical view of a detailed downlink and uplink port state and configuration, including the current link state, speed, port role (uplink, downlink, or stacking), current pluggable media, power state, and immediate connected neighbor.</p>|Dependent item|hpe.synergy.interconnects.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Interconnects discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interconnect [{#NAME}]: Get data|<p>Data of the interconnect [{#NAME}].</p>|Dependent item|hpe.synergy.interconnect["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.name == "{#NAME}")].first()`</p></li></ul>|
|Interconnect [{#NAME}]: Hardware health|<p>The health status of the interconnect hardware.</p>|Dependent item|hpe.synergy.interconnect["{#NAME}",hw.health]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.interconnectHardwareHealth`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interconnect [{#NAME}]: Model|<p>The interconnect model.</p>|Dependent item|hpe.synergy.interconnect["{#NAME}",model]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interconnect [{#NAME}]: Part number|<p>The part number of the interconnect.</p>|Dependent item|hpe.synergy.interconnect["{#NAME}",part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.partNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interconnect [{#NAME}]: Port count|<p>The number of ports on the interconnect.</p>|Dependent item|hpe.synergy.interconnect["{#NAME}",port_count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.portCount`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interconnect [{#NAME}]: Serial number|<p>The serial number of the interconnect.</p>|Dependent item|hpe.synergy.interconnect["{#NAME}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serialNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interconnect [{#NAME}]: Spare part number|<p>The spare part number of the interconnect.</p>|Dependent item|hpe.synergy.interconnect["{#NAME}",spare_part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sparePartNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Interconnect [{#NAME}]: State|<p>The current state of the resource.</p>|Dependent item|hpe.synergy.interconnect["{#NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Interconnect [{#NAME}]: Status|<p>The overall health status of the resource. The following are the valid values for the status of the resource:</p><p></p><p>*OK* - indicates normal/informational behavior;</p><p>*Disabled* - indicates that the resource is not operational;</p><p>*Warning* - requires attention soon;</p><p>*Critical* - requires immediate attention;</p><p>*Unknown* - should be avoided, but there may be rare occasions when the status is unknown.</p>|Dependent item|hpe.synergy.interconnect["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Interconnects discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Interconnect [{#NAME}]: Has critical status|<p>The interconnect [{#NAME}] status is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.interconnect["{#NAME}",status])=0`|High||
|HPE Synergy: Interconnect [{#NAME}]: Has warning status|<p>The interconnect [{#NAME}] status is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.interconnect["{#NAME}",status])=4`|Warning||
|HPE Synergy: Interconnect [{#NAME}]: Is disabled|<p>The interconnect [{#NAME}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.interconnect["{#NAME}",status])=1`|Info||

### LLD rule Logical enclosures discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Logical enclosures discovery|<p>A list of the logical enclosures.</p>|Dependent item|hpe.synergy.logical_enclosures.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Logical enclosures discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Logical enclosure [{#NAME}]: Get data|<p>Data of the logical enclosure [{#NAME}].</p>|Dependent item|hpe.synergy.logical_enclosure["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.name == "{#NAME}")].first()`</p></li></ul>|
|Logical enclosure [{#NAME}]: State|<p>The current resource state of the logical enclosure:</p><p></p><p>*Consistent* - this is the expected state of the logical enclosure. The logical enclosure configuration is consistent with the enclosure group, and the configuration of the hardware resources is consistent with the logical enclosure configuration;</p><p>*Creating* - the logical enclosure is being created;</p><p>*DeleteFailed* - the prior attempt to delete the logical enclosure failed. Retry the delete operation potentially with the force option. No other logical enclosure operations are allowed in this state;</p><p>*Deleting* - the logical enclosure is being deleted;</p><p>*Inconsistent* - the configuration of the logical enclosure differs from that of the enclosure group, or the configuration of the hardware resources is inconsistent with the logical enclosure configuration. Perform an Update from group, Reapply configuration, or Update firmware action as an appropriate to bring the configuration back into consistency;</p><p>*Updating* - configuration changes are being applied to the hardware configuration.</p>|Dependent item|hpe.synergy.logical_enclosure["{#NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Replace: `Inconsistent -> 0`</p></li><li><p>Replace: `Creating -> 1`</p></li><li><p>Replace: `DeleteFailed -> 2`</p></li><li><p>Replace: `Deleting -> 3`</p></li><li><p>Replace: `Consistent -> 4`</p></li><li><p>Replace: `Updating -> 5`</p></li><li><p>In range: `0 -> 5`</p><p>⛔️Custom on fail: Set value to: `6`</p></li></ul>|
|Logical enclosure [{#NAME}]: Status|<p>The overall health status of the resource. The following are the valid values for the status of the resource:</p><p></p><p>*OK* - indicates normal/informational behavior;</p><p>*Disabled* - indicates that the resource is not operational;</p><p>*Warning* - requires attention soon;</p><p>*Critical* - requires immediate attention;</p><p>*Unknown* - should be avoided, but there may be rare occasions when the status is unknown.</p>|Dependent item|hpe.synergy.logical_enclosure["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Logical enclosures discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Logical enclosure [{#NAME}]: Delete failed|<p>Indicator that the deletion of a logical enclosure failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.logical_enclosure["{#NAME}",state])=2`|Average||
|HPE Synergy: Logical enclosure [{#NAME}]: Is inconsistent|<p>The configuration of the logical enclosure differs from that of the enclosure group, or the configuration of the hardware resources is inconsistent with the logical enclosure configuration. Perform an Update from group, Reapply configuration, or Update firmware action as an appropriate to bring the configuration back into consistency.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.logical_enclosure["{#NAME}",state])=0`|Average||
|HPE Synergy: Logical enclosure [{#NAME}]: Has critical status|<p>The status of the logical enclosure [{#NAME}] is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.logical_enclosure["{#NAME}",status])=0`|High||
|HPE Synergy: Logical enclosure [{#NAME}]: Has warning status|<p>The status of the logical enclosure [{#NAME}] is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.logical_enclosure["{#NAME}",status])=4`|Warning||
|HPE Synergy: Logical enclosure [{#NAME}]: Is disabled|<p>The logical enclosure [{#NAME}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.logical_enclosure["{#NAME}",status])=1`|Info||

### LLD rule nPar discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|nPar discovery|<p>Electrically isolated hardware partition (nPar). Partition details are relevant only for enclosures with type "SDX".</p>|Dependent item|hpe.synergy.npar.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members.[0].partitions`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for nPar discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Partition [{#ENCLOSURE_NAME}:{#PARTITION_ID}]: Get data|<p>Data of the partition [{#ENCLOSURE_NAME}:{#PARTITION_ID}].</p>|Dependent item|hpe.synergy.partition["{#PARTITION_ID}","{#ENCLOSURE_NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Partition [{#ENCLOSURE_NAME}:{#PARTITION_ID}]: Devices count|<p>The number of blades in the partition.</p>|Dependent item|hpe.synergy.partition["{#PARTITION_ID}","{#ENCLOSURE_NAME}",device_count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.deviceCount`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Partition [{#ENCLOSURE_NAME}:{#PARTITION_ID}]: Health|<p>Indicates the health of the partition and the health of its owned resources (blades and IO bays) as reported by the firmware. If a problem is detected with one of the resources, the health of the partition is reported as Degraded. If all the resources in the partition are operating correctly, the health of the partition is reported as OK.</p><p></p><p>*NparDegrade* - one or more resources in the partition are unhealthy.</p><p>*NparHealthInvalid* - a partition health is invalid.</p><p>*NparHealthMax* - a delimiter defined by the firmware.</p><p>*NparOk* - all the resources in the partition are healthy.</p>|Dependent item|hpe.synergy.partition["{#PARTITION_ID}","{#ENCLOSURE_NAME}",health]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.partitionHealth`</p></li><li><p>Replace: `NparDegrade -> 0`</p></li><li><p>Replace: `NparHealthInvalid -> 1`</p></li><li><p>Replace: `NparHealthMax -> 2`</p></li><li><p>Replace: `NparOk -> 3`</p></li><li><p>In range: `0 -> 3`</p><p>⛔️Custom on fail: Set value to: `4`</p></li></ul>|
|Partition [{#ENCLOSURE_NAME}:{#PARTITION_ID}]: Memory|<p>The total memory of the partition.</p>|Dependent item|hpe.synergy.partition["{#PARTITION_ID}","{#ENCLOSURE_NAME}",memory]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memoryMb`</p></li><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Partition [{#ENCLOSURE_NAME}:{#PARTITION_ID}]: Processors Count|<p>The number of processors in the partition.</p>|Dependent item|hpe.synergy.partition["{#PARTITION_ID}","{#ENCLOSURE_NAME}",processor_count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.processorCount`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Partition [{#ENCLOSURE_NAME}:{#PARTITION_ID}]: Status|<p>Indicates whether the partition has been booted and also indicates its current power state:</p><p></p><p>*ParStatusActive* - a partition is active when a 'poweron' operation is initiated on the partition and the firmware boot process is started;</p><p>*ParStatusInactive* - a partition is in an inactive state after it has been created or shut down;</p><p>*ParStatusInvalid* - a partition status is invalid;</p><p>*ParStatusManualRepair* -  a partition under manual repair;</p><p>*ParStatusMax* - a delimiter defined by the OA firmware;</p><p>*ParStatusUndefined* - partition status is undefined;</p><p>*ParStatusUnknown* - a partition might report an Unknown state after an OA restart. This state is possible when the firmware is not able to identify the correct partition state due to the internal firmware errors at an OA startup. The state is persistent and can only be cleared by force powering off of the partition from the OA. A partition in this state will not accept any partition operation except parstatus and force poweroff. Any active OS instances continue to run unhindered even when the partition is in an unknown state.</p>|Dependent item|hpe.synergy.partition["{#PARTITION_ID}","{#ENCLOSURE_NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.partitionStatus`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `ParStatusActive -> 0`</p></li><li><p>Replace: `ParStatusInactive -> 1`</p></li><li><p>Replace: `ParStatusInvalid -> 2`</p></li><li><p>Replace: `ParStatusManualRepair -> 3`</p></li><li><p>Replace: `ParStatusMax -> 4`</p></li><li><p>Replace: `ParStatusUndefined -> 5`</p></li><li><p>Replace: `ParStatusUnknown -> 6`</p></li><li><p>In range: `0 -> 6`</p><p>⛔️Custom on fail: Set value to: `6`</p></li></ul>|

### Trigger prototypes for nPar discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Partition [{#ENCLOSURE_NAME}:{#PARTITION_ID}]: Health is invalid|<p>The partition health is invalid.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.partition["{#PARTITION_ID}","{#ENCLOSURE_NAME}",health])=1`|Average||
|HPE Synergy: Partition [{#ENCLOSURE_NAME}:{#PARTITION_ID}]: Health is degraded|<p>One or more resources in the partition are unhealthy.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.partition["{#PARTITION_ID}","{#ENCLOSURE_NAME}",health])=0`|High||
|HPE Synergy: Partition [{#ENCLOSURE_NAME}:{#PARTITION_ID}]: Is invalid|<p>The partition status is invalid.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.partition["{#PARTITION_ID}","{#ENCLOSURE_NAME}",status])=2`|Average||

### LLD rule Power supplies discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power supplies discovery|<p>List of power supply bays in the enclosure.</p>|Dependent item|hpe.synergy.ps.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members.[0].powerSupplyBays`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Power supplies discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power Supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Get data|<p>Data of the power Supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}].</p>|Dependent item|hpe.synergy.power_supply["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Power Supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Model|<p>The common descriptive model of the power supply.</p>|Dependent item|hpe.synergy.power_supply["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",model]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Power Supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Output capacity|<p>The output capacity of the power supply.</p>|Dependent item|hpe.synergy.power_supply["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",output_capacity]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.outputCapacityWatts`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Power Supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Part number|<p>The part number of the power supply.</p>|Dependent item|hpe.synergy.power_supply["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.partNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Power Supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Presence|<p>Indicates whether a power supply is present:</p><p></p><p>*Absent* - the device slot is empty;</p><p>*PresenceNoOp* - the device slot is uninitialized;</p><p>*PresenceUnknown* - the device presence is unknown;</p><p>*Present* - the device slot has a device in it;</p><p>*Subsumed* - the device slot is configured to be part of another device slot. Not applicable for the fan or power supply bays.</p>|Dependent item|hpe.synergy.power_supply["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",presence]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.devicePresence`</p></li><li><p>Replace: `Absent -> 0`</p></li><li><p>Replace: `PresenceNoOp -> 1`</p></li><li><p>Replace: `PresenceUnknown -> 2`</p></li><li><p>Replace: `Present -> 3`</p></li><li><p>Replace: `Subsumed -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `2`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Power Supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Serial number|<p>The unique serial number of the power supply.</p>|Dependent item|hpe.synergy.power_supply["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serialNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Power Supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Spare part number|<p>The spare part number to be used when ordering an additional or replacement power supply of this type.</p>|Dependent item|hpe.synergy.power_supply["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",spare_part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sparePartNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Power Supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Status|<p>The overall health status of the resource. The following are the valid values for the status of the resource:</p><p></p><p>*OK* - indicates normal/informational behavior;</p><p>*Disabled* - indicates that the resource is not operational;</p><p>*Warning* - requires attention soon;</p><p>*Critical* - requires immediate attention;</p><p>*Unknown* - should be avoided, but there may be rare occasions when the status is unknown.</p>|Dependent item|hpe.synergy.power_supply["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Power supplies discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Power Supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Has critical status|<p>The status of the power supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.power_supply["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=0`|High||
|HPE Synergy: Power Supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Has warning status|<p>The status of the power supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.power_supply["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=4`|Warning||
|HPE Synergy: Power Supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Is disabled|<p>The status of Power Supply [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.power_supply["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=1`|Info||

### LLD rule Racks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Racks discovery|<p>A list of the racks.</p>|Dependent item|hpe.synergy.racks.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Racks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Rack [{#NAME}]: Get data|<p>Data of the rack [{#NAME}].</p>|Dependent item|hpe.synergy.rack["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.name == "{#NAME}")].first()`</p></li></ul>|
|Rack [{#NAME}]: State|<p>The current state of the resource. the valid values include Adding, AddError, Configured, CredentialError, Refreshing, RefreshError, Removing, RemoveError, and Unmanaged.</p>|Dependent item|hpe.synergy.rack["{#NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Replace: `Adding -> 0`</p></li><li><p>Replace: `AddError -> 1`</p></li><li><p>Replace: `Configured -> 2`</p></li><li><p>Replace: `CredentialError -> 3`</p></li><li><p>Replace: `Refreshing -> 4`</p></li><li><p>Replace: `RefreshError -> 5`</p></li><li><p>Replace: `Removing -> 6`</p></li><li><p>Replace: `RemoveError -> 7`</p></li><li><p>Replace: `Unmanaged -> 8`</p></li><li><p>In range: `0 -> 8`</p><p>⛔️Custom on fail: Set value to: `9`</p></li></ul>|
|Rack [{#NAME}]: Status|<p>The overall health status of the resource. The following are the valid values for the status of the resource:</p><p></p><p>*OK* - indicates normal/informational behavior;</p><p>*Disabled* - indicates that the resource is not operational;</p><p>*Warning* - requires attention soon;</p><p>*Critical* - requires immediate attention;</p><p>*Unknown* - should be avoided, but there may be rare occasions when the status is unknown.</p>|Dependent item|hpe.synergy.rack["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Racks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Rack [{#NAME}]: Add error|<p>Adding the rack [{#NAME}] failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.rack["{#NAME}",state])=1`|Average||
|HPE Synergy: Rack [{#NAME}]: Has credential error|<p>The rack [{#NAME}] has credential error.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.rack["{#NAME}",state])=3`|Average||
|HPE Synergy: Rack [{#NAME}]: Has refresh error|<p>The rack [{#NAME}] has refresh error.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.rack["{#NAME}",state])=5`|Average||
|HPE Synergy: Rack [{#NAME}]: Has remove error|<p>The rack [{#NAME}] has remove error.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.rack["{#NAME}",state])=7`|Average||
|HPE Synergy: Rack [{#NAME}]: Has critical status|<p>The rack [{#NAME}] status is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.rack["{#NAME}",status])=0`|High||
|HPE Synergy: Rack [{#NAME}]: Has warning status|<p>The rack [{#NAME}] status is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.rack["{#NAME}",status])=4`|Warning||
|HPE Synergy: Rack [{#NAME}]: Is disabled|<p>The rack [{#NAME}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.rack["{#NAME}",status])=1`|Info||

### LLD rule Server hardware discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Server hardware discovery|<p>The server hardware resource is a representation of a physical server.</p>|Dependent item|hpe.synergy.server_hardware.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Server hardware discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Server [{#SERVER_NAME}:{#LOCATION}]: Get data|<p>Data of the server [{#SERVER_NAME}:{#LOCATION}].</p>|Dependent item|hpe.synergy.server_hardware["{#LOCATION}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.name == "{#LOCATION}")].first()`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: Maintenance state|<p>The maintenance flag of the Server Hardware - disruptive maintenance operations, such as firmware update, can cause many server hardware alerts to be generated in a short period of time. Example: network connectivity is lost or the server reset is detected. When this field is set, predefined alerts for this particular device are suppressed. This field is set only when firmware update is ongoing. The alerts are processed normally once firmware update operation completes. Possible values are Maintenance and Normal.</p>|Dependent item|hpe.synergy.server_hardware["{#LOCATION}",maintenance_state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maintenanceState`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Replace: `Maintenance -> 0`</p></li><li><p>Replace: `Normal -> 1`</p></li><li><p>In range: `0 -> 1`</p><p>⛔️Custom on fail: Set value to: `2`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: Maintenance state reason|<p>This field is set to Firmware update when the server is put under maintenance.</p>|Dependent item|hpe.synergy.server_hardware["{#LOCATION}",maintenance_state_reason]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maintenanceStateReason`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: Memory|<p>The amount of memory installed on this server hardware.</p>|Dependent item|hpe.synergy.server_hardware["{#LOCATION}",memory]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memoryMb`</p></li><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: Migration state|<p>The state of an ongoing virtual connect manager (VCM) migration:</p><p></p><p>*Migrating* - the enclosure is in the process of migrating from VCM;</p><p>*NotApplicable* - the enclosure did not require or has already completed the migration;</p><p>*Unknown* - the migration state is unknown.</p>|Dependent item|hpe.synergy.server_hardware["{#LOCATION}",migration_state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.migrationState`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Replace: `Migrating -> 0`</p></li><li><p>Replace: `NotApplicable -> 1`</p></li><li><p>Replace: `Unknown -> 2`</p></li><li><p>In range: `0 -> 2`</p><p>⛔️Custom on fail: Set value to: `2`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: Model|<p>The model string of the full server hardware.</p>|Dependent item|hpe.synergy.server_hardware["{#LOCATION}",model]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: Part number|<p>The part number for this server hardware.</p>|Dependent item|hpe.synergy.server_hardware["{#LOCATION}",part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.partNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: Power state|<p>The current power state of the server hardware. The values are Unknown, On, Off, PoweringOn, PoweringOff or Resetting.</p>|Dependent item|hpe.synergy.server_hardware["{#LOCATION}",power_state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.powerState`</p></li><li><p>Replace: `PoweringOff -> 0`</p></li><li><p>Replace: `PoweringOn -> 1`</p></li><li><p>Replace: `Resetting -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Off -> 4`</p></li><li><p>Replace: `On -> 5`</p></li><li><p>In range: `0 -> 5`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: Processor cores count|<p>The number of cores available per processor.</p>|Dependent item|hpe.synergy.server_hardware.processor["{#LOCATION}",cores_count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.processorCoreCount`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: Processors count|<p>The number of processors installed on this server hardware.</p>|Dependent item|hpe.synergy.server_hardware.processor["{#LOCATION}",count]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.processorCount`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: Processor speed|<p>The speed of the CPUs.</p>|Dependent item|hpe.synergy.server_hardware.processor["{#LOCATION}",speed]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.processorSpeedMhz`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: Processor type|<p>The type of the CPU installed on this server hardware.</p>|Dependent item|hpe.synergy.server_hardware.processor["{#LOCATION}",type]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.processorType`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: Serial number|<p>The serial number of the server hardware.</p>|Dependent item|hpe.synergy.server_hardware["{#LOCATION}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serialNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: State|<p>The current resource state of the server hardware. The allowable values are:</p><p></p><p>*Unknown* - not initialized;</p><p>*Adding* - a server is being added;</p><p>*NoProfileApplied* - a server successfully added;</p><p>*Monitored* - a server is being monitored;</p><p>*Unmanaged* - a discovered and supported server;</p><p>*Removing* - a server is being removed;</p><p>*RemoveFailed* - an unsuccessful server removal;</p><p>*Removed* - a server is successfully removed;</p><p>*ApplyingProfile* - a server is successfully removed;</p><p>*ProfileApplied* - a profile is successfully applied;</p><p>*RemovingProfile* - a profile is being removed;</p><p>*ProfileError* -  an Unsuccessful profile is applied or removed;</p><p>*Unsupported* - a server model or version is not currently supported by the appliance;</p><p>*UpdatingFirmware* - a server firmware update is in progress.</p>|Dependent item|hpe.synergy.server_hardware["{#LOCATION}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Replace: `Unknown -> 0`</p></li><li><p>Replace: `Adding -> 1`</p></li><li><p>Replace: `NoProfileApplied -> 2`</p></li><li><p>Replace: `Monitored -> 3`</p></li><li><p>Replace: `Unmanaged -> 4`</p></li><li><p>Replace: `Removing -> 5`</p></li><li><p>Replace: `RemoveFailed -> 6`</p></li><li><p>Replace: `Removed -> 7`</p></li><li><p>Replace: `ApplyingProfile -> 8`</p></li><li><p>Replace: `ProfileApplied -> 9`</p></li><li><p>Replace: `RemovingProfile -> 10`</p></li><li><p>Replace: `ProfileError -> 11`</p></li><li><p>Replace: `Unsupported -> 12`</p></li><li><p>Replace: `UpdatingFirmware -> 13`</p></li><li><p>In range: `0 -> 13`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: State reason|<p>The reason for the current resource state of the server hardware. This only applies if the state is Unmanaged, otherwise it is set to NotApplicable. The allowable values are:</p><p></p><p>*Unsupported* - a server model or version is not currently supported by the appliance;</p><p>*UpdatingFirmware* - a server firmware update is in progress;</p><p>*NotApplicable* - when PhysicalServerState is anything besides Unmanaged;</p><p>*NotOwner* -  no claim on the server;</p><p>*Inventory* - a server is added by the PDU;</p><p>*Unconfigured* - the discovery data is incomplete or an iLO configuration has failed;</p><p>*UnsupportedFirmware* - an iLO firmware version is below the minimum support level;</p><p>*Interrupted* - when PhysicalServerState is a result of an operation that was terminated before completing;</p><p>*CommunicationError* - an appliance cannot communicate with an iLO or an OA.</p>|Dependent item|hpe.synergy.server_hardware["{#LOCATION}",state_reason]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stateReason`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Server [{#SERVER_NAME}:{#LOCATION}]: Status|<p>The overall health status of the resource. The following are the valid values for the status of the resource:</p><p></p><p>*OK* - indicates normal/informational behavior;</p><p>*Disabled* - indicates that the resource is not operational;</p><p>*Warning* - requires attention soon;</p><p>*Critical* - requires immediate attention;</p><p>*Unknown* - should be avoided, but there may be rare occasions when the status is unknown.</p>|Dependent item|hpe.synergy.server_hardware["{#LOCATION}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Server hardware discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Server [{#SERVER_NAME}:{#LOCATION}]: Is in maintenance mode|<p>The disruptive maintenance operations like firmware update can cause many server hardware alerts to be generated in a short period of time. Example: Network connectivity is lost or the server reset is detected.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.server_hardware["{#LOCATION}",maintenance_state])=0 and length(last(/HPE Synergy by HTTP/hpe.synergy.server_hardware["{#LOCATION}",maintenance_state_reason]))>0`|Info||
|HPE Synergy: Server [{#SERVER_NAME}:{#LOCATION}]: Has profile error|<p>The unsuccessful profile application or removal.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.server_hardware["{#LOCATION}",state])=11`|Average||
|HPE Synergy: Server [{#SERVER_NAME}:{#LOCATION}]: Is not initialized|<p>The server is not initialized.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.server_hardware["{#LOCATION}",state])=0`|Warning||
|HPE Synergy: Server [{#SERVER_NAME}:{#LOCATION}]: Is unsupported|<p>The server model or version is not currently supported by the appliance.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.server_hardware["{#LOCATION}",state])=12`|Average||
|HPE Synergy: Server [{#SERVER_NAME}:{#LOCATION}]: Remove failed|<p>The previous operation to remove the server hardware did not succeed. The operation should be re-attempted.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.server_hardware["{#LOCATION}",state])=6`|Average||
|HPE Synergy: Server [{#SERVER_NAME}:{#LOCATION}]: Is unmanaged|<p>Discovered a supported server.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.server_hardware["{#LOCATION}",state])=4 and length(last(/HPE Synergy by HTTP/hpe.synergy.server_hardware["{#LOCATION}",state_reason]))>0`|Average||
|HPE Synergy: Server [{#SERVER_NAME}:{#LOCATION}]: Has critical status|<p>The status of the server [{#SERVER_NAME}:{#LOCATION}] is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.server_hardware["{#LOCATION}",status])=0`|High||
|HPE Synergy: Server [{#SERVER_NAME}:{#LOCATION}]: Has warning status|<p>The status of the server [{#SERVER_NAME}:{#LOCATION}] is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.server_hardware["{#LOCATION}",status])=4`|Warning||
|HPE Synergy: Server [{#SERVER_NAME}:{#LOCATION}]: Is disabled|<p>The server [{#SERVER_NAME}:{#LOCATION}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.server_hardware["{#LOCATION}",status])=1`|Info||

### LLD rule Storage pools discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage pools discovery|<p>A list of the storage pools.</p>|Dependent item|hpe.synergy.storage_pools.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Storage pools discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage pool [{#NAME}]: Get data|<p>Data of the storage pool [{#NAME}].</p>|Dependent item|hpe.synergy.storage.pools["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.name == "{#NAME}")].first()`</p></li></ul>|
|Storage pool [{#NAME}]: Capacity allocated|<p>The capacity allocated from the storage pool in bytes.</p>|Dependent item|hpe.synergy.storage.pools.capacity["{#NAME}",allocated]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocatedCapacity`</p></li></ul>|
|Storage pool [{#NAME}]: Capacity free|<p>The free capacity available from the storage pool in bytes.</p>|Dependent item|hpe.synergy.storage.pools.capacity["{#NAME}",free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.freeCapacity`</p></li></ul>|
|Storage pool [{#NAME}]: Capacity allocated to snapshots|<p>The pool capacity allocated to the snapshots in bytes.</p>|Dependent item|hpe.synergy.storage.pools.capacity["{#NAME}",snapshot]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Storage pool [{#NAME}]: State|<p>The current state of the resource:</p><p></p><p>*AddFailed* - an attempt to add the resource failed;</p><p>*Adding* - the resource is in the process of being added;</p><p>*Configured* - the resource is configured;</p><p>*Connected* - the appliance has connected to the resource;</p><p>*Copying* - the resource is in the process of being copied;</p><p>*CreateFailed* - an attempt to create the resource failed;</p><p>*Creating* - the resource is in the process of being created;</p><p>*DeleteFailed* - an attempt to delete the resource failed;</p><p>*Deleting* - the resource is in the process of being deleted;</p><p>*Discovered* - the resource has been discovered by the appliance, but it is not managed by the appliance;</p><p>*Managed* - the resource is managed by the appliance;</p><p>*Normal* - the resource is in a normal state;</p><p>*UpdateFailed* - an attempt to update the resource failed;</p><p>*Updating* - the resource is in the process of being updated.</p>|Dependent item|hpe.synergy.storage.pools["{#NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Replace: `AddFailed -> 0`</p></li><li><p>Replace: `Adding -> 1`</p></li><li><p>Replace: `Configured -> 2`</p></li><li><p>Replace: `Connected -> 3`</p></li><li><p>Replace: `Copying -> 4`</p></li><li><p>Replace: `CreateFailed -> 5`</p></li><li><p>Replace: `Creating -> 6`</p></li><li><p>Replace: `DeleteFailed -> 7`</p></li><li><p>Replace: `Deleting -> 8`</p></li><li><p>Replace: `Discovered -> 9`</p></li><li><p>Replace: `Managed -> 10`</p></li><li><p>Replace: `Normal -> 11`</p></li><li><p>Replace: `UpdateFailed -> 12`</p></li><li><p>Replace: `Updating -> 13`</p></li><li><p>In range: `0 -> 13`</p><p>⛔️Custom on fail: Set value to: `14`</p></li></ul>|
|Storage pool [{#NAME}]: Status|<p>The overall health status of the resource. The following are the valid values for the status of the resource:</p><p></p><p>*OK* - indicates normal/informational behavior;</p><p>*Disabled* - indicates that the resource is not operational;</p><p>*Warning* - requires attention soon;</p><p>*Critical* - requires immediate attention;</p><p>*Unknown* - should be avoided, but there may be rare occasions when the status is unknown.</p>|Dependent item|hpe.synergy.storage.pools["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|
|Storage pool [{#NAME}]: Capacity total|<p>The total capacity of the storage pool in bytes.</p>|Dependent item|hpe.synergy.storage.pools.capacity["{#NAME}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalCapacity`</p></li></ul>|

### Trigger prototypes for Storage pools discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Storage pool [{#NAME}]: Add error|<p>Adding of the storage pool [{#NAME}] failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.pools["{#NAME}",state])=0`|Average||
|HPE Synergy: Storage pool [{#NAME}]: Create failed|<p>Creating of the storage pool [{#NAME}] failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.pools["{#NAME}",state])=5`|Average||
|HPE Synergy: Storage pool [{#NAME}]: Delete failed|<p>Deletion of the storage pool [{#NAME}] failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.pools["{#NAME}",state])=7`|Average||
|HPE Synergy: Storage pool [{#NAME}]: Update failed|<p>Updating of the storage pool [{#NAME}] failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.pools["{#NAME}",state])=12`|Average||
|HPE Synergy: Storage pool [{#NAME}]: Has critical status|<p>The status of the storage pool [{#NAME}] is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.pools["{#NAME}",status])=0`|High||
|HPE Synergy: Storage pool [{#NAME}]: Has warning status|<p>The status of the storage pool [{#NAME}] is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.pools["{#NAME}",status])=4`|Warning||
|HPE Synergy: Storage pool [{#NAME}]: Is disabled|<p>The storage pool [{#NAME}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.pools["{#NAME}",status])=1`|Info||

### LLD rule Storage systems discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage systems discovery|<p>A list of the storage systems.</p>|Dependent item|hpe.synergy.storage_systems.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Storage systems discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage system [{#NAME}]: Get data|<p>Data of the storage system [{#NAME}].</p>|Dependent item|hpe.synergy.storage.system["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.name == "{#NAME}")].first()`</p></li></ul>|
|Storage system [{#NAME}]: Capacity allocated|<p>The capacity allocated in bytes.</p>|Dependent item|hpe.synergy.storage.system.capacity["{#NAME}",allocated]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocatedCapacity`</p></li></ul>|
|Storage system [{#NAME}]: Capacity free|<p>The free capacity of the storage system in bytes.</p>|Dependent item|hpe.synergy.storage.system.capacity["{#NAME}",free]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.freeCapacity`</p></li></ul>|
|Storage system [{#NAME}]: State|<p>The current state of the resource:</p><p></p><p>*AddFailed* - an attempt to add the resource failed;</p><p>*Adding* - the resource is in the process of being added;</p><p>*Configured* - the resource is configured;</p><p>*Connected* - the appliance has connected to the resource;</p><p>*Copying* - the resource is in the process of being copied;</p><p>*CreateFailed* - an attempt to create the resource failed;</p><p>*Creating* - the resource is in the process of being created;</p><p>*DeleteFailed* - an attempt to delete the resource failed;</p><p>*Deleting* - the resource is in the process of being deleted;</p><p>*Discovered* - the resource has been discovered by the appliance, but it is not managed by the appliance;</p><p>*Managed* - the resource is managed by the appliance;</p><p>*Normal* - the resource is in a normal state;</p><p>*UpdateFailed* - an attempt to update the resource failed;</p><p>*Updating* - the resource is in the process of being updated.</p>|Dependent item|hpe.synergy.storage.system["{#NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Replace: `AddFailed -> 0`</p></li><li><p>Replace: `Adding -> 1`</p></li><li><p>Replace: `Configured -> 2`</p></li><li><p>Replace: `Connected -> 3`</p></li><li><p>Replace: `Copying -> 4`</p></li><li><p>Replace: `CreateFailed -> 5`</p></li><li><p>Replace: `Creating -> 6`</p></li><li><p>Replace: `DeleteFailed -> 7`</p></li><li><p>Replace: `Deleting -> 8`</p></li><li><p>Replace: `Discovered -> 9`</p></li><li><p>Replace: `Managed -> 10`</p></li><li><p>Replace: `Normal -> 11`</p></li><li><p>Replace: `UpdateFailed -> 12`</p></li><li><p>Replace: `Updating -> 13`</p></li><li><p>In range: `0 -> 13`</p><p>⛔️Custom on fail: Set value to: `14`</p></li></ul>|
|Storage system [{#NAME}]: Status|<p>The overall health status of the resource. The following are the valid values for the status of the resource:</p><p></p><p>*OK* - indicates normal/informational behavior;</p><p>*Disabled* - indicates that the resource is not operational;</p><p>*Warning* - requires attention soon;</p><p>*Critical* - requires immediate attention;</p><p>*Unknown* - should be avoided, but there may be rare occasions when the status is unknown.</p>|Dependent item|hpe.synergy.storage.system["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|
|Storage system [{#NAME}]: Capacity total|<p>The total capacity of the storage system in bytes.</p>|Dependent item|hpe.synergy.storage.system.capacity["{#NAME}",total]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalCapacity`</p></li></ul>|

### Trigger prototypes for Storage systems discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Storage system [{#NAME}]: Add error|<p>Adding the storage system [{#NAME}] failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.system["{#NAME}",state])=0`|Average||
|HPE Synergy: Storage system [{#NAME}]: Create failed|<p>Creating of the storage system [{#NAME}] failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.system["{#NAME}",state])=5`|Average||
|HPE Synergy: Storage system [{#NAME}]: Delete failed|<p>Deletion of the storage system [{#NAME}] failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.system["{#NAME}",state])=7`|Average||
|HPE Synergy: Storage system [{#NAME}]: Update failed|<p>Updating of the storage system [{#NAME}] failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.system["{#NAME}",state])=12`|Average||
|HPE Synergy: Storage system [{#NAME}]: Has critical status|<p>The status of the storage system [{#NAME}] is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.system["{#NAME}",status])=0`|High||
|HPE Synergy: Storage system [{#NAME}]: Has warning status|<p>The status of the storage system [{#NAME}] is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.system["{#NAME}",status])=4`|Warning||
|HPE Synergy: Storage system [{#NAME}]: Is disabled|<p>The storage system [{#NAME}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.system["{#NAME}",status])=1`|Info||

### LLD rule Storage volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage volumes discovery|<p>A list of the storage volumes.</p>|Dependent item|hpe.synergy.storage_volumes.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Storage volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage volume [{#NAME}]: Get data|<p>Data of the storage volume [{#NAME}].</p>|Dependent item|hpe.synergy.storage.volumes["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.name == "{#NAME}")].first()`</p></li></ul>|
|Storage volume [{#NAME}]: Capacity allocated|<p>The capacity allocated in bytes.</p>|Dependent item|hpe.synergy.storage.volumes.capacity["{#NAME}",allocated]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.allocatedCapacity`</p></li></ul>|
|Storage volume [{#NAME}]: Capacity provisioned|<p>The total provisioned capacity of the volume in bytes.</p>|Dependent item|hpe.synergy.storage.volumes.capacity["{#NAME}",provisioned]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.provisionedCapacity`</p></li></ul>|
|Storage volume [{#NAME}]: State|<p>The current state of the resource:</p><p></p><p>*AddFailed* - an attempt to add the resource failed;</p><p>*Adding* - the resource is in the process of being added;</p><p>*Configured* - the resource is configured;</p><p>*Connected* - the appliance has connected to the resource;</p><p>*Copying* - the resource is in the process of being copied;</p><p>*CreateFailed* - an attempt to create the resource failed;</p><p>*Creating* - the resource is in the process of being created;</p><p>*DeleteFailed* - an attempt to delete the resource failed;</p><p>*Deleting* - the resource is in the process of being deleted;</p><p>*Discovered* - the resource has been discovered by the appliance, but it is not managed by the appliance;</p><p>*Managed* - the resource is managed by the appliance;</p><p>*Normal* - the resource is in a normal state;</p><p>*UpdateFailed* - an attempt to update the resource failed;</p><p>*Updating* - the resource is in the process of being updated.</p>|Dependent item|hpe.synergy.storage.volumes["{#NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Replace: `AddFailed -> 0`</p></li><li><p>Replace: `Adding -> 1`</p></li><li><p>Replace: `Configured -> 2`</p></li><li><p>Replace: `Connected -> 3`</p></li><li><p>Replace: `Copying -> 4`</p></li><li><p>Replace: `CreateFailed -> 5`</p></li><li><p>Replace: `Creating -> 6`</p></li><li><p>Replace: `DeleteFailed -> 7`</p></li><li><p>Replace: `Deleting -> 8`</p></li><li><p>Replace: `Discovered -> 9`</p></li><li><p>Replace: `Managed -> 10`</p></li><li><p>Replace: `Normal -> 11`</p></li><li><p>Replace: `UpdateFailed -> 12`</p></li><li><p>Replace: `Updating -> 13`</p></li><li><p>In range: `0 -> 13`</p><p>⛔️Custom on fail: Set value to: `14`</p></li></ul>|
|Storage volume [{#NAME}]: Status|<p>The overall health status of the resource. The following are the valid values for the status of the resource:</p><p></p><p>*OK* - indicates normal/informational behavior;</p><p>*Disabled* - indicates that the resource is not operational;</p><p>*Warning* - requires attention soon;</p><p>*Critical* - requires immediate attention;</p><p>*Unknown* - should be avoided, but there may be rare occasions when the status is unknown.</p>|Dependent item|hpe.synergy.storage.volumes["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Storage volumes discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Storage volume [{#NAME}]: Add error|<p>Adding the storage volume [{#NAME}] failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.volumes["{#NAME}",state])=0`|Average||
|HPE Synergy: Storage volume [{#NAME}]: Create failed|<p>Creating of the storage volume [{#NAME}] failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.volumes["{#NAME}",state])=5`|Average||
|HPE Synergy: Storage volume [{#NAME}]: Delete failed|<p>Deletion of the storage volume [{#NAME}] failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.volumes["{#NAME}",state])=7`|Average||
|HPE Synergy: Storage volume [{#NAME}]: Update failed|<p>Updating of the storage volume [{#NAME}] failed.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.volumes["{#NAME}",state])=12`|Average||
|HPE Synergy: Storage volume [{#NAME}]: Has critical status|<p>The status of the storage volume [{#NAME}] is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.volumes["{#NAME}",status])=0`|High||
|HPE Synergy: Storage volume [{#NAME}]: Has warning status|<p>The status of the storage volume [{#NAME}] is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.volumes["{#NAME}",status])=4`|Warning||
|HPE Synergy: Storage volume [{#NAME}]: Is disabled|<p>The storage volume [{#NAME}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.storage.volumes["{#NAME}",status])=1`|Info||

### LLD rule Managers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Managers discovery|<p>A list of the Synergy Frame Link Module bays.</p>|Dependent item|hpe.synergy.frame_link_modules.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members.[0].managerBays`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Managers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Get data|<p>Data of the manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}].</p>|Dependent item|hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Firmware version|<p>The firmware version of the manager.</p>|Dependent item|hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",fw_version]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fwVersion`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Link port state|<p>The state of the LINK port:</p><p></p><p>*Disabled* - the port is disabled;</p><p>*Linked* - the port is linked;</p><p>*Unlinked* - the port is unlinked.</p>|Dependent item|hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",link_port_state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.linkPortState`</p></li><li><p>Replace: `Disabled -> 0`</p></li><li><p>Replace: `Linked -> 1`</p></li><li><p>Replace: `Unlinked -> 2`</p></li><li><p>In range: `0 -> 2`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Link port status|<p>The status of the LINK port:</p><p></p><p>*Critical* - requires immediate attention;</p><p>*Disabled* - the resource is currently not operational;</p><p>*OK* - indicates normal/informational behavior;</p><p>*Unknown* - the health status is not yet known or cannot be determined;</p><p>*Warning* - requires attention soon.</p>|Dependent item|hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",link_port_status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.linkPortStatus`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|
|Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: MGMT port state|<p>The state of the MGMT port:</p><p></p><p>*Active* - the port is in active mode;</p><p>*Disabled* - the port is in disabled mode;</p><p>*I3s* - the port is configured for the deployment of an OS network traffic.</p><p>*Other* - the port is in other mode;</p><p>*Standby* - the port is in standby mode;</p><p>*Unknown* - the mode of the port is not known.</p>|Dependent item|hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",mgmt_port_state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mgmtPortState`</p></li><li><p>Replace: `Active -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `I3s -> 2`</p></li><li><p>Replace: `Other -> 3`</p></li><li><p>Replace: `Standby -> 4`</p></li><li><p>Replace: `Unknown -> 5`</p></li><li><p>In range: `0 -> 5`</p><p>⛔️Custom on fail: Set value to: `5`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: MGMT port status|<p>The status of the MGMT port:</p><p></p><p>*Critical* - requires immediate attention;</p><p>*Disabled* - the resource is currently not operational;</p><p>*OK* - indicates normal/informational behavior;</p><p>*Unknown* - the health status is not yet known or cannot be determined;</p><p>*Warning* - requires attention soon.</p>|Dependent item|hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",mgmt_port_status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mgmtPortStatus`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|
|Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Model|<p>The model of the link module.</p>|Dependent item|hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",model]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Part number|<p>The part number of the link module.</p>|Dependent item|hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.partNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Presence|<p>Indicates whether a manager is present in the bay:</p><p></p><p>*Absent* - the device slot is empty;</p><p>*PresenceNoOp* - the device slot is uninitialized;</p><p>*PresenceUnknown* - the device presence is unknown;</p><p>*Present* - the device slot has a device in it;</p><p>*Subsumed* - the device slot is configured to be part of another device slot. Not applicable for the fan or power supply bays.</p>|Dependent item|hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",presence]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.devicePresence`</p></li><li><p>Replace: `Absent -> 0`</p></li><li><p>Replace: `PresenceNoOp -> 1`</p></li><li><p>Replace: `PresenceUnknown -> 2`</p></li><li><p>Replace: `Present -> 3`</p></li><li><p>Replace: `Subsumed -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `2`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Serial number|<p>The serial number of the link module.</p>|Dependent item|hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",serial_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serialNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Spare part number|<p>The spare part number of the link module.</p>|Dependent item|hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",spare_part_number]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sparePartNumber`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Status|<p>The health status of the link module:</p><p></p><p>*Critical* - requires immediate attention;</p><p>*Disabled* - the resource is currently not operational;</p><p>*OK* - indicates normal/informational behavior;</p><p>*Unknown* - the health status is not yet known or cannot be determined;</p><p>*Warning* - requires attention soon.</p>|Dependent item|hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Managers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Link port has critical status|<p>The link port status of the manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",link_port_status])=0`|High||
|HPE Synergy: Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Link port has warning status|<p>The link port status of the manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",link_port_status])=4`|Warning||
|HPE Synergy: Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Link port is disabled|<p>The link port of the manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",link_port_status])=1`|Info||
|HPE Synergy: Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: MGMT port has critical status|<p>The MGMT port status of the manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",mgmt_port_status])=0`|High||
|HPE Synergy: Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: MGMT port has warning status|<p>The MGMT port status of the manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",mgmt_port_status])=4`|Warning||
|HPE Synergy: Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: MGMT port is disabled|<p>The MGMT port of the manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",mgmt_port_status])=1`|Info||
|HPE Synergy: Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Is subsumed|<p>The device slot is configured to be part of another device slot.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",presence])=4`|Average||
|HPE Synergy: Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Has critical status|<p>The status of the manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=0`|High||
|HPE Synergy: Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Has warning status|<p>The status of the manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=4`|Warning||
|HPE Synergy: Manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}]: Is disabled|<p>The manager [{#ENCLOSURE_NAME}:{#BAY_NUMBER}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.manager["{#BAY_NUMBER}","{#ENCLOSURE_NAME}",status])=1`|Info||

### LLD rule Uplink sets discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Uplink sets discovery|<p>A list of the uplink sets.</p>|Dependent item|hpe.synergy.uplink_sets.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Uplink sets discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Uplink set [{#NAME}]: Get data|<p>Data of the uplink set [{#NAME}].</p>|Dependent item|hpe.synergy.uplink_set["{#NAME}",data]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.name == "{#NAME}")].first()`</p></li></ul>|
|Uplink set [{#NAME}]: State|<p>The current state of the resource.</p>|Dependent item|hpe.synergy.uplink_set["{#NAME}",state]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Uplink set [{#NAME}]: Status|<p>The overall health status of the resource. The following are the valid values for the status of the resource:</p><p></p><p>*OK* - indicates normal/informational behavior;</p><p>*Disabled* - indicates that the resource is not operational;</p><p>*Warning* - requires attention soon;</p><p>*Critical* - requires immediate attention;</p><p>*Unknown* - should be avoided, but there may be rare occasions when the status is unknown.</p>|Dependent item|hpe.synergy.uplink_set["{#NAME}",status]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li><li><p>Replace: `Critical -> 0`</p></li><li><p>Replace: `Disabled -> 1`</p></li><li><p>Replace: `OK -> 2`</p></li><li><p>Replace: `Unknown -> 3`</p></li><li><p>Replace: `Warning -> 4`</p></li><li><p>In range: `0 -> 4`</p><p>⛔️Custom on fail: Set value to: `3`</p></li></ul>|

### Trigger prototypes for Uplink sets discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE Synergy: Uplink set [{#NAME}]: Has critical status|<p>The status of the uplink set [{#NAME}] is critical. Needs immediate attention.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.uplink_set["{#NAME}",status])=0`|High||
|HPE Synergy: Uplink set [{#NAME}]: Has warning status|<p>The status of the uplink set [{#NAME}] is warning. Needs attention soon.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.uplink_set["{#NAME}",status])=4`|Warning||
|HPE Synergy: Uplink set [{#NAME}]: Is disabled|<p>The uplink set [{#NAME}] is currently not operational.</p>|`last(/HPE Synergy by HTTP/hpe.synergy.uplink_set["{#NAME}",status])=1`|Info||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

