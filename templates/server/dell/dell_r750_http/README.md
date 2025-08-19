
# DELL PowerEdge R750 by HTTP

## Overview

This is a template for monitoring DELL PowerEdge R750 servers with iDRAC 8/9 firmware 4.32 (and later) with Redfish API enabled via Zabbix script items. This template works without any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- DELL PowerEdge R750xs

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1\. Enable Redfish API in the Dell iDRAC interface of your server.

2\. Create a user for monitoring with read-only permissions in the Dell iDRAC interface.

3\. Create a host for Dell server with iDRAC IP as the Zabbix agent interface.

4\. Link the template to the host.

5\. Customize the values of the `{$DELL.HTTP.API.URL}`, `{$DELL.HTTP.API.USER}`, and `{$DELL.HTTP.API.PASSWORD}` macros.

> NOTE! If you are experiencing timeouts on some of the items that are executing requests, adjust the `{$DELL.HTTP.REQUEST.TIMEOUT}` macro accordingly.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$DELL.HTTP.API.URL}|<p>The Dell iDRAC Redfish API URL in the format `<scheme>://<host>:<port>`.</p>||
|{$DELL.HTTP.API.USER}|<p>The Dell iDRAC username.</p>||
|{$DELL.HTTP.API.PASSWORD}|<p>The Dell iDRAC user password.</p>||
|{$DELL.HTTP.PROXY}|<p>Set an HTTP proxy for Redfish API requests if needed.</p>||
|{$DELL.HTTP.RETURN.CODE.OK}|<p>Set the HTTP return code that represents an OK response from the API. The default is "200", but can vary, for example, if a proxy is used.</p>|`200`|
|{$DELL.HTTP.REQUEST.TIMEOUT}|<p>Set the timeout for HTTP requests.</p>|`10s`|
|{$DELL.HTTP.IFCONTROL}|<p>Link status trigger will be fired only for interfaces that have the context macro equal to "1".</p>|`1`|
|{$DELL.HTTP.CPU.UTIL.HIGH}|<p>Sets the percentage threshold for creating a "high" severity event about CPU utilization.</p>|`90`|
|{$DELL.HTTP.CPU.UTIL.WARN}|<p>Sets the percentage threshold for creating a "warning" severity event about CPU utilization.</p>|`75`|
|{$DELL.HTTP.MEM.UTIL.HIGH}|<p>Sets the percentage threshold for creating a "high" severity event about memory utilization.</p>|`90`|
|{$DELL.HTTP.MEM.UTIL.WARN}|<p>Sets the percentage threshold for creating a "warning" severity event about memory utilization.</p>|`75`|
|{$DELL.HTTP.IO.UTIL.HIGH}|<p>Sets the percentage threshold for creating a "high" severity event about IO utilization.</p>|`90`|
|{$DELL.HTTP.IO.UTIL.WARN}|<p>Sets the percentage threshold for creating a "warning" severity event about IO utilization.</p>|`75`|
|{$DELL.HTTP.SYS.UTIL.HIGH}|<p>Sets the percentage threshold for creating a "high" severity event about SYS utilization.</p>|`90`|
|{$DELL.HTTP.SYS.UTIL.WARN}|<p>Sets the percentage threshold for creating a "warning" severity event about SYS utilization.</p>|`75`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get system|<p>Returns system metrics.</p>|Script|dell.server.system.get|
|Get sensors|<p>Returns sensors.</p>|Script|dell.server.sensors.get|
|Get array controller resources|<p>Returns array controller resources.</p>|Script|dell.server.array.resources.get|
|Get disks|<p>Returns storage resources.</p>|Script|dell.server.disks.get|
|Get network interfaces|<p>Returns network interfaces.</p>|Script|dell.server.net.iface.get|
|CPU utilization, in %|<p>CPU utilization.</p>|Dependent item|dell.server.util.cpu<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sysBoard[?(@.id == "SystemBoardCPUUsage")].reading.first()`</p></li></ul>|
|Memory utilization, in %|<p>Memory utilization.</p>|Dependent item|dell.server.util.mem<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sysBoard[?(@.id == "SystemBoardMEMUsage")].reading.first()`</p></li></ul>|
|IO utilization, in %|<p>IO utilization.</p>|Dependent item|dell.server.util.io<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sysBoard[?(@.id == "SystemBoardIOUsage")].reading.first()`</p></li></ul>|
|SYS utilization, in %|<p>SYS utilization.</p>|Dependent item|dell.server.util.sys<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sysBoard[?(@.id == "SystemBoardSYSUsage")].reading.first()`</p></li></ul>|
|Overall system health status|<p>This attribute defines the overall rollup status of all the components in the system monitored by the remote access card. Includes system, storage, IO devices, iDRAC, CPU, memory, etc.</p>|Dependent item|dell.server.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Hardware model name|<p>This attribute defines the model name of the system.</p>|Dependent item|dell.server.hw.model<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Hardware serial number|<p>This attribute defines the service tag of the system.</p>|Dependent item|dell.server.hw.serialnumber<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serialnumber`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Firmware version|<p>This attribute defines the firmware version of a remote access card.</p>|Dependent item|dell.server.hw.firmware<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.firmware`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Redfish API status|<p>Availability of Redfish API on the server.</p><p>Possible values:</p><p>  0 - Unavailable</p><p>  1 - Available</p>|Simple check|net.tcp.service[https]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R750: CPU utilization is too high|<p>Current CPU utilization has exceeded `{$DELL.HTTP.CPU.UTIL.HIGH}`%.</p>|`min(/DELL PowerEdge R750 by HTTP/dell.server.util.cpu,5m)>={$DELL.HTTP.CPU.UTIL.HIGH}`|High||
|Dell R750: CPU utilization is high|<p>Current CPU utilization has exceeded `{$DELL.HTTP.CPU.UTIL.WARN}`%.</p>|`min(/DELL PowerEdge R750 by HTTP/dell.server.util.cpu,5m)>={$DELL.HTTP.CPU.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>Dell R750: CPU utilization is too high</li></ul>|
|Dell R750: Memory utilization is too high|<p>Current memory utilization has exceeded `{$DELL.HTTP.MEM.UTIL.HIGH}`%.</p>|`min(/DELL PowerEdge R750 by HTTP/dell.server.util.mem,5m)>={$DELL.HTTP.MEM.UTIL.HIGH}`|High||
|Dell R750: Memory utilization is high|<p>Current memory utilization has exceeded `{$DELL.HTTP.MEM.UTIL.WARN}`%.</p>|`min(/DELL PowerEdge R750 by HTTP/dell.server.util.mem,5m)>={$DELL.HTTP.MEM.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>Dell R750: Memory utilization is too high</li></ul>|
|Dell R750: IO utilization is too high|<p>Current IO utilization has exceeded `{$DELL.HTTP.IO.UTIL.HIGH}`%.</p>|`min(/DELL PowerEdge R750 by HTTP/dell.server.util.io,5m)>={$DELL.HTTP.IO.UTIL.HIGH}`|High||
|Dell R750: IO utilization is high|<p>Current IO utilization has exceeded `{$DELL.HTTP.IO.UTIL.WARN}`%.</p>|`min(/DELL PowerEdge R750 by HTTP/dell.server.util.io,5m)>={$DELL.HTTP.IO.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>Dell R750: IO utilization is too high</li></ul>|
|Dell R750: SYS utilization is too high|<p>Current SYS utilization has exceeded `{$DELL.HTTP.SYS.UTIL.HIGH}`%.</p>|`min(/DELL PowerEdge R750 by HTTP/dell.server.util.sys,5m)>={$DELL.HTTP.SYS.UTIL.HIGH}`|High||
|Dell R750: SYS utilization is high|<p>Current SYS utilization has exceeded `{$DELL.HTTP.SYS.UTIL.WARN}`%.</p>|`min(/DELL PowerEdge R750 by HTTP/dell.server.util.sys,5m)>={$DELL.HTTP.SYS.UTIL.WARN}`|Warning|**Depends on**:<br><ul><li>Dell R750: IO utilization is too high</li></ul>|
|Dell R750: Server is in a critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.status,)=3`|Average||
|Dell R750: Server is in a warning state|<p>Please check the device for warnings.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.status,)=2`|Warning|**Depends on**:<br><ul><li>Dell R750: Server is in a critical state</li></ul>|
|Dell R750: Device has been replaced|<p>The device serial number has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.hw.serialnumber,#1)<>last(/DELL PowerEdge R750 by HTTP/dell.server.hw.serialnumber,#2) and length(last(/DELL PowerEdge R750 by HTTP/dell.server.hw.serialnumber))>0`|Info|**Manual close**: Yes|
|Dell R750: Firmware has changed|<p>The firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.hw.firmware,#1)<>last(/DELL PowerEdge R750 by HTTP/dell.server.hw.firmware,#2) and length(last(/DELL PowerEdge R750 by HTTP/dell.server.hw.firmware))>0`|Info|**Manual close**: Yes|
|Dell R750: Redfish API service is unavailable|<p>The service is unavailable or does not accept TCP connections.</p>|`last(/DELL PowerEdge R750 by HTTP/net.tcp.service[https])=0`|High||

### LLD rule Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Temperature discovery|<p>Discovery of temperature sensors.</p>|Dependent item|dell.server.temp.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temperature`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Temperature discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Probe [{#SENSOR_NAME}]: Get sensor|<p>Returns the metrics of a sensor.</p>|Dependent item|dell.server.sensor.temp.get[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temperature.[?(@.id == '{#ID}')].first()`</p></li></ul>|
|Probe [{#SENSOR_NAME}]: Value|<p>Sensor value.</p>|Dependent item|dell.server.sensor.temp.value[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.reading`</p></li></ul>|
|Probe [{#SENSOR_NAME}]: Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.sensor.temp.status[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Temperature discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R750: Probe [{#SENSOR_NAME}]: Critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.sensor.temp.status[{#SENSOR_NAME}],)=3`|Average||
|Dell R750: Probe [{#SENSOR_NAME}]: Warning state|<p>Please check the device for warnings.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.sensor.temp.status[{#SENSOR_NAME}],)=2`|Warning|**Depends on**:<br><ul><li>Dell R750: Probe [{#SENSOR_NAME}]: Critical state</li></ul>|

### LLD rule PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|PSU discovery|<p>Discovery of PSU sensors.</p>|Dependent item|dell.server.psu.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.psu`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Power supply [{#SENSOR_NAME}]: Get sensor|<p>Returns the metrics of a sensor.</p>|Dependent item|dell.server.sensor.psu.get[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.psu.[?(@.name == '{#SENSOR_NAME}')].first()`</p></li></ul>|
|Power supply [{#SENSOR_NAME}]: Voltage|<p>Sensor value.</p>|Dependent item|dell.server.sensor.psu.voltage[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.voltage.reading`</p></li></ul>|
|Power supply [{#SENSOR_NAME}]: Voltage sensor status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.sensor.psu.voltage.status[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.voltage.health`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Power supply [{#SENSOR_NAME}]: Current|<p>Sensor value.</p>|Dependent item|dell.server.sensor.psu.current[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.current.reading`</p></li></ul>|
|Power supply [{#SENSOR_NAME}]: Current sensor status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.sensor.psu.current.status[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.current.health`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R750: Power supply [{#SENSOR_NAME}]: Voltage sensor: Critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.sensor.psu.voltage.status[{#SENSOR_NAME}],)=3`|Average||
|Dell R750: Power supply [{#SENSOR_NAME}]: Voltage sensor: Warning state|<p>Please check the device for warnings.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.sensor.psu.voltage.status[{#SENSOR_NAME}],)=2`|Warning|**Depends on**:<br><ul><li>Dell R750: Power supply [{#SENSOR_NAME}]: Voltage sensor: Critical state</li></ul>|
|Dell R750: Power supply [{#SENSOR_NAME}]: Current sensor: Critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.sensor.psu.current.status[{#SENSOR_NAME}],)=3`|Average||
|Dell R750: Power supply [{#SENSOR_NAME}]: Current sensor: Warning state|<p>Please check the device for warnings.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.sensor.psu.current.status[{#SENSOR_NAME}],)=2`|Warning|**Depends on**:<br><ul><li>Dell R750: Power supply [{#SENSOR_NAME}]: Current sensor: Critical state</li></ul>|

### LLD rule FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FAN discovery|<p>Discovery of FAN sensors.</p>|Dependent item|dell.server.fan.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fan`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for FAN discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Fan [{#SENSOR_NAME}]: Get sensor|<p>Returns the metrics of a sensor.</p>|Dependent item|dell.server.sensor.fan.get[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fan.[?(@.id == '{#ID}')].first()`</p></li></ul>|
|Fan [{#SENSOR_NAME}]: Speed|<p>Sensor value.</p>|Dependent item|dell.server.sensor.fan.speed[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.reading`</p></li></ul>|
|Fan [{#SENSOR_NAME}]: Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.sensor.fan.status[{#SENSOR_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for FAN discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R750: Fan [{#SENSOR_NAME}]: Critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.sensor.fan.status[{#SENSOR_NAME}],)=3`|Average||
|Dell R750: Fan [{#SENSOR_NAME}]: Warning state|<p>Please check the device for warnings.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.sensor.fan.status[{#SENSOR_NAME}],)=2`|Warning|**Depends on**:<br><ul><li>Dell R750: Fan [{#SENSOR_NAME}]: Critical state</li></ul>|

### LLD rule Array controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Array controller discovery|<p>Discovery of disk array controllers.</p>|Dependent item|dell.server.array.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.arrayControllers`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Array controller discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Controller [{#CNTLR_NAME}]: Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.array.status[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.arrayControllers[?(@.id == "{#ID}")].health.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Array controller discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R750: Controller [{#CNTLR_NAME}]: Critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.array.status[{#ID}],)=3`|Average||
|Dell R750: Controller [{#CNTLR_NAME}]: Warning state|<p>Please check the device for warnings.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.array.status[{#ID}],)=2`|Warning|**Depends on**:<br><ul><li>Dell R750: Controller [{#CNTLR_NAME}]: Critical state</li></ul>|

### LLD rule Battery discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Battery discovery|<p>Discovery of battery controllers.</p>|Dependent item|dell.server.controller.battery.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.batteryControllers`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Battery discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Battery [{#BATTERY_NAME}]: Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.controller.battery.status[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.batteryControllers[?(@.id == "{#ID}")].status.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Battery discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R750: Battery [{#BATTERY_NAME}]: Critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.controller.battery.status[{#ID}],)=3`|Average||
|Dell R750: Battery [{#BATTERY_NAME}]: Warning state|<p>Please check the device for warnings.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.controller.battery.status[{#ID}],)=2`|Warning|**Depends on**:<br><ul><li>Dell R750: Battery [{#BATTERY_NAME}]: Critical state</li></ul>|

### LLD rule Physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical disk discovery|<p>Discovery of physical disks.</p>|Dependent item|dell.server.physicaldisk.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.physicalDisks`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Physical disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Physical disk [{#DISK_NAME}]: Get disk|<p>Returns the metrics of a physical disk.</p>|Script|dell.server.hw.physicaldisk.get[{#DISK_NAME}]|
|Physical disk [{#DISK_NAME}]: Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.hw.physicaldisk.status[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Physical disk [{#DISK_NAME}]: Serial number|<p>The serial number of this drive.</p>|Dependent item|dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sn`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Physical disk [{#DISK_NAME}]: Model name|<p>The model number of the drive.</p>|Dependent item|dell.server.hw.physicaldisk.model[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Physical disk [{#DISK_NAME}]: Media type|<p>The type of media contained in this drive. Possible values: HDD, SSD, SMR, null.</p>|Dependent item|dell.server.hw.physicaldisk.media_type[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mediaType`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Physical disk [{#DISK_NAME}]: Size|<p>The size, in bytes, of this drive.</p>|Dependent item|dell.server.hw.physicaldisk.size[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.capacity`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Physical disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R750: Physical disk [{#DISK_NAME}]: Critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.hw.physicaldisk.status[{#DISK_NAME}],)=3`|Average||
|Dell R750: Physical disk [{#DISK_NAME}]: Warning state|<p>Please check the device for warnings.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.hw.physicaldisk.status[{#DISK_NAME}],)=2`|Warning|**Depends on**:<br><ul><li>Dell R750: Physical disk [{#DISK_NAME}]: Critical state</li></ul>|
|Dell R750: Physical disk [{#DISK_NAME}] has been replaced|<p>[{#DISK_NAME}] serial number has changed. Acknowledge to close the problem manually.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}],#1)<>last(/DELL PowerEdge R750 by HTTP/dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}],#2) and length(last(/DELL PowerEdge R750 by HTTP/dell.server.hw.physicaldisk.serialnumber[{#DISK_NAME}]))>0`|Info|**Manual close**: Yes|

### LLD rule Virtual disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual disk discovery|<p>Discovery of virtual disks.</p>|Dependent item|dell.server.virtualdisk.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.virtualDisks`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Virtual disk discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Virtual disk [{#DISK_NAME}]: Get disk|<p>Returns the metrics of a virtual disk.</p>|Script|dell.server.hw.virtualdisk.get[{#DISK_NAME}]|
|Virtual disk [{#DISK_NAME}]: Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.hw.virtualdisk.status[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Virtual disk [{#DISK_NAME}]: RAID status|<p>This property represents the RAID specific status. Possible values: Blocked, Degraded, Failed, Foreign, Offline, Online, Ready, Unknown, null.</p>|Dependent item|dell.server.hw.virtualdisk.raid_status[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.raidStatus`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Virtual disk [{#DISK_NAME}]: Size|<p>The size in bytes of this Volume.</p>|Dependent item|dell.server.hw.virtualdisk.size[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.capacity`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Virtual disk [{#DISK_NAME}]: Current state|<p>The known state of the Resource, for example, Enabled. Possible values: Enabled, Disabled, StandbyOffline, StandbySpare, InTest, Starting, Absent, UnavailableOffline, Deferring, Quiesced, Updating, Qualified.</p>|Dependent item|dell.server.hw.virtualdisk.state[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Virtual disk [{#DISK_NAME}]: Read policy|<p>Indicates the read cache policy setting for the Volume. Possible values: ReadAhead, NoReadAhead, AdaptiveReadAhead.</p>|Dependent item|dell.server.hw.virtualdisk.read_policy[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.readCachePolicy`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Virtual disk [{#DISK_NAME}]: Write policy|<p>Indicates the write cache policy setting for the Volume. Possible values: WriteThrough, WriteBack, ProtectedWriteBack, UnprotectedWriteBack.</p>|Dependent item|dell.server.hw.virtualdisk.write_policy[{#DISK_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.writeCachePolicy`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Virtual disk discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R750: Virtual disk [{#DISK_NAME}]: Critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.hw.virtualdisk.status[{#DISK_NAME}],)=3`|Average||
|Dell R750: Virtual disk [{#DISK_NAME}]: Warning state|<p>Please check the device for warnings.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.hw.virtualdisk.status[{#DISK_NAME}],)=2`|Warning|**Depends on**:<br><ul><li>Dell R750: Virtual disk [{#DISK_NAME}]: Critical state</li></ul>|
|Dell R750: Virtual disk [{#DISK_NAME}]: RAID status not OK|<p>Please check the disk for faults.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.hw.virtualdisk.raid_status[{#DISK_NAME}],)<8`|Average||

### LLD rule Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Network interface discovery|<p>Discovery of network interfaces.</p>|Dependent item|dell.server.net.if.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Network interface discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Interface [{#IFNAME}]: Get interface|<p>Returns the metrics of a network interface.</p>|Script|dell.server.net.if.get[{#IFNAME}]|
|Interface [{#IFNAME}]: Speed|<p>The network port current link speed.</p>|Dependent item|dell.server.net.if.speed[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.linkSpeed`</p></li><li><p>Custom multiplier: `1000000`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Interface [{#IFNAME}]: Link status|<p>The status of the link between this port and its link partner. Possible values: Down, Up, null.</p>|Dependent item|dell.server.net.if.status[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.linkStatus`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Interface [{#IFNAME}]: State|<p>The known state of the Resource, for example, Enabled. Possible values: Enabled, Disabled, StandbyOffline, StandbySpare, InTest, Starting, Absent, UnavailableOffline, Deferring, Quiesced, Updating, Qualified.</p>|Dependent item|dell.server.net.if.state[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Interface [{#IFNAME}]: Status|<p>The status of the job. Possible values: OK, Warning, Critical.</p>|Dependent item|dell.server.net.if.health[{#IFNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.health`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Network interface discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Dell R750: Interface [{#IFNAME}]: Link down|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is Down (2).<br>2. `{$DELL.HTTP.IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is Down (2).<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for the "eternal off" interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll because of `.diff`.</p>|`{$DELL.HTTP.IFCONTROL:"{#IFNAME}"}=1 and (last(/DELL PowerEdge R750 by HTTP/dell.server.net.if.status[{#IFNAME}],)=2 and last(/DELL PowerEdge R750 by HTTP/dell.server.net.if.status[{#IFNAME}],#1)<>last(/DELL PowerEdge R750 by HTTP/dell.server.net.if.status[{#IFNAME}],#2))`|Average|**Manual close**: Yes|
|Dell R750: Interface [{#IFNAME}]: Link status issue|<p>This trigger expression works as follows:<br>1. It can be triggered if the operations status is Null (1) or Unknown (0).<br>2. `{$DELL.HTTP.IFCONTROL:"{#IFNAME}"}=1` - a user can redefine the context macro to "0", marking this interface as not important. No new trigger will be fired if this interface is Null (1) or Unknown (0).<br>3. `{TEMPLATE_NAME:METRIC.diff()}=1` - the trigger fires only if the operational status was up to (1) sometime before (so, does not fire for the "eternal off" interfaces.)<br><br>WARNING: if closed manually - it will not fire again on the next poll because of `.diff`.</p>|`{$DELL.HTTP.IFCONTROL:"{#IFNAME}"}=1 and (last(/DELL PowerEdge R750 by HTTP/dell.server.net.if.status[{#IFNAME}],)<2 and last(/DELL PowerEdge R750 by HTTP/dell.server.net.if.status[{#IFNAME}],#1)<>last(/DELL PowerEdge R750 by HTTP/dell.server.net.if.status[{#IFNAME}],#2))`|Average|**Manual close**: Yes|
|Dell R750: Interface [{#IFNAME}]: Critical state|<p>Please check the device for faults.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.net.if.health[{#IFNAME}],)=3`|Average||
|Dell R750: Interface [{#IFNAME}]: Warning state|<p>Please check the device for warnings.</p>|`last(/DELL PowerEdge R750 by HTTP/dell.server.net.if.health[{#IFNAME}],)=2`|Warning|**Depends on**:<br><ul><li>Dell R750: Interface [{#IFNAME}]: Critical state</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

