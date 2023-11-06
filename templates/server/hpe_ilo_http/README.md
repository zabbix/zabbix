
# HPE iLO by HTTP

## Overview

This template is designed for the effortless deployment of HPE iLO monitoring by Zabbix via iLO RESTful API and doesn't require any external scripts.

For more details about HPE Redfish services, refer to the [`official documentation`](https://servermanagementportal.ext.hpe.com/docs/redfishservices/).

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- iLO 5 version 2.95, HPE ProLiant DL160 Gen10

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create the iLO user for monitoring (for example, `zbx_monitor`). The user will only need to have the `Login` privilege, which can be assigned manually or by assigning the `ReadOnly` role to the user.
2. Set the iLO API endpoint URL in the `{$ILO.URL}` macro in the format `<scheme>://<host>[:port]/` (port is optional).
3. Set the name of the user that you created in step 1 in the `{$ILO.USER}` macro.
4. Set the password of the user that you created in step 1 in the `{$ILO.PASSWORD}` macro.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ILO.URL}|<p>The iLO API endpoint in the format "<scheme>://<host>[:port]/" (port is optional).</p>||
|{$ILO.USER}|<p>The name of the user that is used for monitoring.</p>||
|{$ILO.PASSWORD}|<p>The password of the user that is used for monitoring.</p>||
|{$ILO.HTTP_PROXY}|<p>The HTTP proxy for script items (set if needed). If the macro is empty, then no proxy is used.</p>||
|{$ILO.INTERVAL}|<p>The update interval for the script item that retrieves data from API.</p>|`1m`|
|{$ILO.TIMEOUT}|<p>The timeout threshold for the script item that retrieves data from API.</p>|`15s`|
|{$ILO.COMPUTER_SYSTEM.DISCOVERY.HOSTNAME.MATCHES}|<p>The computer system hostname regex filter to use in computer systems related metrics discovery for including. Can be used with the following context to include metrics of the particular entity: System, Storage, Controller, Drive, Volume.</p>|`.+`|
|{$ILO.COMPUTER_SYSTEM.DISCOVERY.HOSTNAME.NOT_MATCHES}|<p>The computer system hostname regex filter to use in computer systems related metrics discovery for excluding. Can be used with the following context to exclude metrics of the particular entity: System, Storage, Controller, Drive, Volume.</p>|`CHANGE_IF_NEEDED`|
|{$ILO.COMPUTER_SYSTEM.DISCOVERY.TYPE.MATCHES}|<p>The computer system type regex filter to use in computer systems related metrics discovery for including. Can be used with the following context to include metrics of the particular entity: System, Storage, Controller, Drive, Volume.</p>|`.+`|
|{$ILO.COMPUTER_SYSTEM.DISCOVERY.TYPE.NOT_MATCHES}|<p>The computer system type regex filter to use in computer systems related metrics discovery for excluding. Can be used with the following context to exclude metrics of the particular entity: System, Storage, Controller, Drive, Volume.</p>|`CHANGE_IF_NEEDED`|
|{$ILO.SENSOR.DISCOVERY.NAME.MATCHES}|<p>The sensor name regex filter to use in temperature sensors discovery for including.</p>|`.+`|
|{$ILO.SENSOR.DISCOVERY.NAME.NOT_MATCHES}|<p>The sensor name regex filter to use in temperature sensors discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$ILO.SENSOR.DISCOVERY.CONTEXT.MATCHES}|<p>The sensor physical context regex filter to use in temperature sensors discovery for including.</p>|`.+`|
|{$ILO.SENSOR.DISCOVERY.CONTEXT.NOT_MATCHES}|<p>The sensor physical context regex filter to use in temperature sensors discovery for excluding.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Get data|<p>The JSON with the result of API requests.</p>|Script|hpe.ilo.get_data|
|HPE iLO: Get data check|<p>The data collection check.</p>|Dependent item|hpe.ilo.get_data.check<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE iLO: Failed to get data from API|<p>Failed to get data from API. Check the debug log for more information.</p>|`length(last(/HPE iLO by HTTP/hpe.ilo.get_data.check))>0`|High||

### LLD rule HPE iLO: Computer systems discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Computer systems discovery|<p>Discovers computer systems.</p>|Dependent item|hpe.ilo.computer_systems.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for HPE iLO: Computer systems discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Get data|<p>Get data about the computer system.</p>|Dependent item|hpe.ilo.computer_system.get_data[{#SYSTEM_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.systems[?(@.Id == '{#SYSTEM_ID}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: System type|<p>The type of the computer system. Possible values:</p><p></p><p>0 - "Physical", a computer system;</p><p>1 - "Virtual", a virtual machine instance running on this system;</p><p>2 - "OS", an operating system instance;</p><p>3 - "PhysicallyPartitioned", a hardware-based partition of a computer system;</p><p>4 - "VirtuallyPartitioned", a virtual or software-based partition of a computer system;</p><p>5 - "DPU", a virtual or software-based partition of a computer system;</p><p>10 - "Unknown", the computer system type is unknown.</p>|Dependent item|hpe.ilo.computer_system.type[{#SYSTEM_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SystemType`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Model|<p>The model name of the computer system.</p>|Dependent item|hpe.ilo.computer_system.model[{#SYSTEM_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Model`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Serial number|<p>The serial number of the computer system.</p>|Dependent item|hpe.ilo.computer_system.serial_number[{#SYSTEM_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SerialNumber`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: BIOS current version|<p>The current BIOS version of the computer system.</p>|Dependent item|hpe.ilo.computer_system.bios.current_version[{#SYSTEM_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Oem.Hpe.Bios.Current.VersionString`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Status|<p>The overall health state from the view of this computer system. Possible values:</p><p></p><p>0 - "OK", the computer system is in normal condition;</p><p>1 - "Warning", the computer system is in condition that requires attention;</p><p>2 - "Critical", the computer system is in critical condition that requires immediate attention;</p><p>10 - "Unknown", the computer system is in unknown condition.</p>|Dependent item|hpe.ilo.computer_system.status[{#SYSTEM_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.HealthRollup`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: CPU utilization, in %|<p>Current CPU utilization of the computer system in percentage.</p>|Dependent item|hpe.ilo.computer_system.usage.cpu_util[{#SYSTEM_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Oem.Hpe.SystemUsage.CPUUtil`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: I/O bus utilization, in %|<p>Current I/O bus utilization of the computer system in percentage.</p>|Dependent item|hpe.ilo.computer_system.usage.io_bus_util[{#SYSTEM_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Oem.Hpe.SystemUsage.IOBusUtil`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Memory bus utilization, in %|<p>Current memory bus utilization of the computer system in percentage.</p>|Dependent item|hpe.ilo.computer_system.usage.memory_bus_util[{#SYSTEM_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Oem.Hpe.SystemUsage.MemoryBusUtil`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for HPE iLO: Computer systems discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Computer system has been replaced|<p>The computer system serial number has changed. Acknowledge to close the problem manually.</p>|`change(/HPE iLO by HTTP/hpe.ilo.computer_system.serial_number[{#SYSTEM_ID}])=1 and length(last(/HPE iLO by HTTP/hpe.ilo.computer_system.serial_number[{#SYSTEM_ID}]))>0`|Info|**Manual close**: Yes|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: BIOS version has changed|<p>The current version of BIOS has changed. Acknowledge to close the problem manually.</p>|`change(/HPE iLO by HTTP/hpe.ilo.computer_system.bios.current_version[{#SYSTEM_ID}])=1 and length(last(/HPE iLO by HTTP/hpe.ilo.computer_system.bios.current_version[{#SYSTEM_ID}]))>0`|Info|**Manual close**: Yes|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Computer system is in warning state|<p>The computer system is in condition that requires attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.computer_system.status[{#SYSTEM_ID}])=1`|Warning|**Depends on**:<br><ul><li>HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Computer system is in critical state</li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Computer system is in critical state|<p>The computer system is in critical condition that requires immediate attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.computer_system.status[{#SYSTEM_ID}])=2`|High||

### LLD rule HPE iLO: Managers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Managers discovery|<p>Discovers managers.</p>|Dependent item|hpe.ilo.managers.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for HPE iLO: Managers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Manager [{#MANAGER_ID}]: Get data|<p>Get data about the manager.</p>|Dependent item|hpe.ilo.manager.get_data[{#MANAGER_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.managers[?(@.Id == '{#MANAGER_ID}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HPE iLO: Manager [{#MANAGER_ID}]: Manager type|<p>The manager type. Possible values:</p><p></p><p>0 - "ManagementController", a controller used primarily to monitor or manage the operation of a device or system;</p><p>1 - "EnclosureManager", a controller which provides management functions for a chassis or group of devices or systems;</p><p>2 - "BMC", a controller which provides management functions for a single computer system;</p><p>10 - "Unknown", the manager type is unknown.</p>|Dependent item|hpe.ilo.manager.type[{#MANAGER_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ManagerType`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Manager [{#MANAGER_ID}]: Model|<p>The model name of the manager.</p>|Dependent item|hpe.ilo.manager.model[{#MANAGER_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Model`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Manager [{#MANAGER_ID}]: Current firmware version|<p>The current firmware version of the manager.</p>|Dependent item|hpe.ilo.manager.firmware.current_version[{#MANAGER_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.FirmwareVersion`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Manager [{#MANAGER_ID}]: Status|<p>The health state of the manager. Possible values:</p><p></p><p>0 - "OK", the manager is in normal condition;</p><p>1 - "Warning", the manager is in condition that requires attention;</p><p>2 - "Critical", the manager is in critical condition that requires immediate attention;</p><p>10 - "Unknown", the manager is in unknown condition.</p>|Dependent item|hpe.ilo.manager.status[{#MANAGER_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.Health`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for HPE iLO: Managers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE iLO: Manager [{#MANAGER_ID}]: Firmware version has changed|<p>The current firmware version of the manager has changed. Acknowledge to close the problem manually.</p>|`change(/HPE iLO by HTTP/hpe.ilo.manager.firmware.current_version[{#MANAGER_ID}])=1 and length(last(/HPE iLO by HTTP/hpe.ilo.manager.firmware.current_version[{#MANAGER_ID}]))>0`|Info|**Manual close**: Yes|
|HPE iLO: Manager [{#MANAGER_ID}]: Manager is in warning state|<p>The manager is in condition that requires attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.manager.status[{#MANAGER_ID}])=1`|Warning|**Depends on**:<br><ul><li>HPE iLO: Manager [{#MANAGER_ID}]: Manager is in critical state</li></ul>|
|HPE iLO: Manager [{#MANAGER_ID}]: Manager is in critical state|<p>The manager is in critical condition that requires immediate attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.manager.status[{#MANAGER_ID}])=2`|High||

### LLD rule HPE iLO: Storages discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Storages discovery|<p>Discovers computer system storages.</p>|Dependent item|hpe.ilo.storages.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for HPE iLO: Storages discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Get data|<p>Get data about the storage.</p>|Dependent item|hpe.ilo.storage.get_data[{#SYSTEM_ID}, {#STORAGE_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Status|<p>The overall health state from the view of this storage. Possible values:</p><p></p><p>0 - "OK", the storage is in normal condition;</p><p>1 - "Warning", the storage is in condition that requires attention;</p><p>2 - "Critical", the storage is in critical condition that requires immediate attention;</p><p>10 - "Unknown", the storage is in unknown condition.</p>|Dependent item|hpe.ilo.storage.status[{#SYSTEM_ID}, {#STORAGE_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.HealthRollup`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for HPE iLO: Storages discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Storage is in warning state|<p>The computer system is in condition that requires attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.storage.status[{#SYSTEM_ID}, {#STORAGE_ID}])=1`|Warning|**Depends on**:<br><ul><li>HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Storage is in critical state</li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Storage is in critical state|<p>The computer system is in critical condition that requires immediate attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.storage.status[{#SYSTEM_ID}, {#STORAGE_ID}])=2`|High||

### LLD rule HPE iLO: Controllers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Controllers discovery|<p>Discovers storage controllers.</p>|Dependent item|hpe.ilo.controllers.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for HPE iLO: Controllers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Controller [{#CONTROLLER_ID}]: Get data|<p>Get data about the controller.</p>|Dependent item|hpe.ilo.controller.get_data[{#SYSTEM_ID}, {#STORAGE_ID}, {#CONTROLLER_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Controller [{#CONTROLLER_ID}]: Model|<p>The model name of the controller.</p>|Dependent item|hpe.ilo.controller.model[{#SYSTEM_ID}, {#STORAGE_ID}, {#CONTROLLER_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Model`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Controller [{#CONTROLLER_ID}]: Serial number|<p>The serial number of the controller.</p>|Dependent item|hpe.ilo.controller.serial_number[{#SYSTEM_ID}, {#STORAGE_ID}, {#CONTROLLER_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SerialNumber`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Controller [{#CONTROLLER_ID}]: Status|<p>The health state of the controller. Possible values:</p><p></p><p>0 - "OK", the controller is in normal condition;</p><p>1 - "Warning", the controller is in condition that requires attention;</p><p>2 - "Critical", the controller is in critical condition that requires immediate attention;</p><p>10 - "Unknown", the controller is in unknown condition.</p>|Dependent item|hpe.ilo.controller.status[{#SYSTEM_ID}, {#STORAGE_ID}, {#CONTROLLER_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.Health`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for HPE iLO: Controllers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Controller [{#CONTROLLER_ID}]: Controller has been replaced|<p>The controller serial number has changed. Acknowledge to close the problem manually.</p>|`change(/HPE iLO by HTTP/hpe.ilo.controller.serial_number[{#SYSTEM_ID}, {#STORAGE_ID}, {#CONTROLLER_ID}])=1 and length(last(/HPE iLO by HTTP/hpe.ilo.controller.serial_number[{#SYSTEM_ID}, {#STORAGE_ID}, {#CONTROLLER_ID}]))>0`|Info|**Manual close**: Yes|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Controller [{#CONTROLLER_ID}]: Controller is in warning state|<p>The controller is in condition that requires attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.controller.status[{#SYSTEM_ID}, {#STORAGE_ID}, {#CONTROLLER_ID}])=1`|Warning|**Depends on**:<br><ul><li>HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Controller [{#CONTROLLER_ID}]: Controller is in critical state</li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Controller [{#CONTROLLER_ID}]: Controller is in critical state|<p>The controller is in critical condition that requires immediate attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.controller.status[{#SYSTEM_ID}, {#STORAGE_ID}, {#CONTROLLER_ID}])=2`|High||

### LLD rule HPE iLO: Drives discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Drives discovery|<p>Discovers storage drives.</p>|Dependent item|hpe.ilo.drives.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for HPE iLO: Drives discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Drive [{#DRIVE_ID}]: Get data|<p>Get data about the drive.</p>|Dependent item|hpe.ilo.drive.get_data[{#SYSTEM_ID}, {#STORAGE_ID}, {#DRIVE_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Drive [{#DRIVE_ID}]: Media type|<p>The media type of the drive.</p>|Dependent item|hpe.ilo.drive.media_type[{#SYSTEM_ID}, {#STORAGE_ID}, {#DRIVE_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.MediaType`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Drive [{#DRIVE_ID}]: Serial number|<p>The serial number of the drive.</p>|Dependent item|hpe.ilo.drive.serial_number[{#SYSTEM_ID}, {#STORAGE_ID}, {#DRIVE_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SerialNumber`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Drive [{#DRIVE_ID}]: Model|<p>The model name of the drive.</p>|Dependent item|hpe.ilo.drive.model[{#SYSTEM_ID}, {#STORAGE_ID}, {#DRIVE_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Model`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Drive [{#DRIVE_ID}]: Capacity|<p>The capacity of the drive.</p>|Dependent item|hpe.ilo.drive.capacity[{#SYSTEM_ID}, {#STORAGE_ID}, {#DRIVE_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CapacityBytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Drive [{#DRIVE_ID}]: Predicted media life left, in %|<p>The percentage of reads and writes that are predicted to still be available for the drive.</p>|Dependent item|hpe.ilo.drive.predicted_life_left[{#SYSTEM_ID}, {#STORAGE_ID}, {#DRIVE_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.PredictedMediaLifeLeftPercent`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Drive [{#DRIVE_ID}]: Status indicator|<p>Status of drive. Possible values:</p><p></p><p>0 - "OK", the drive is ok;</p><p>1 - "Fail", the drive has failed;</p><p>2 - "Rebuild", the drive is being rebuilt;</p><p>3 - "PredictiveFailureAnalysis", the drive is still working but predicted to fail soon;</p><p>4 - "Hotspare", the drive is marked to be automatically rebuilt and used as a replacement for a failed drive;</p><p>5 - "InACriticalArray", the array that this drive is a part of is degraded;</p><p>6 - "InAFailedArray	", the array that this drive is a part of is failed;</p><p>10 - "Unknown", the drive status is unknown.</p>|Dependent item|hpe.ilo.drive.status_indicator[{#SYSTEM_ID}, {#STORAGE_ID}, {#DRIVE_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.StatusIndicator`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for HPE iLO: Drives discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Drive [{#DRIVE_ID}]: Drive has been replaced|<p>The drive serial number has changed. Acknowledge to close the problem manually.</p>|`change(/HPE iLO by HTTP/hpe.ilo.drive.serial_number[{#SYSTEM_ID}, {#STORAGE_ID}, {#DRIVE_ID}])=1 and length(last(/HPE iLO by HTTP/hpe.ilo.drive.serial_number[{#SYSTEM_ID}, {#STORAGE_ID}, {#DRIVE_ID}]))>0`|Info|**Manual close**: Yes|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Drive [{#DRIVE_ID}]: Drive has failed|<p>The drive has failed.</p>|`last(/HPE iLO by HTTP/hpe.ilo.drive.status_indicator[{#SYSTEM_ID}, {#STORAGE_ID}, {#DRIVE_ID}])=1`|High||
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Drive [{#DRIVE_ID}]: Drive is predicted to fail soon|<p>The drive is still working but predicted to fail soon.</p>|`last(/HPE iLO by HTTP/hpe.ilo.drive.status_indicator[{#SYSTEM_ID}, {#STORAGE_ID}, {#DRIVE_ID}])=3`|High|**Depends on**:<br><ul><li>HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Drive [{#DRIVE_ID}]: Drive has failed</li></ul>|

### LLD rule HPE iLO: Volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Volumes discovery|<p>Discovers storage volumes.</p>|Dependent item|hpe.ilo.volumes.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for HPE iLO: Volumes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Volume [{#VOLUME_ID}]: Get data|<p>Get data about the volume.</p>|Dependent item|hpe.ilo.volume.get_data[{#SYSTEM_ID}, {#STORAGE_ID}, {#VOLUME_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Volume [{#VOLUME_ID}]: Capacity|<p>The capacity of the volume.</p>|Dependent item|hpe.ilo.volume.capacity[{#SYSTEM_ID}, {#STORAGE_ID}, {#VOLUME_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.CapacityBytes`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Volume [{#VOLUME_ID}]: Status|<p>The health state of the volume. Possible values:</p><p></p><p>0 - "OK", the volume is in normal condition;</p><p>1 - "Warning", the volume is in condition that requires attention;</p><p>2 - "Critical", the volume is in critical condition that requires immediate attention;</p><p>10 - "Unknown", the volume is in unknown condition.</p>|Dependent item|hpe.ilo.volume.status[{#SYSTEM_ID}, {#STORAGE_ID}, {#VOLUME_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.Health`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Volume [{#VOLUME_ID}]: RAID level|<p>The RAID level of the volume.</p>|Dependent item|hpe.ilo.volume.raid_level[{#SYSTEM_ID}, {#STORAGE_ID}, {#VOLUME_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.RAIDType`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for HPE iLO: Volumes discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Volume [{#VOLUME_ID}]: Volume is in warning state|<p>The volume is in condition that requires attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.volume.status[{#SYSTEM_ID}, {#STORAGE_ID}, {#VOLUME_ID}])=1`|Warning|**Depends on**:<br><ul><li>HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Volume [{#VOLUME_ID}]: Volume is in critical state</li></ul>|
|HPE iLO: Computer system [{#SYSTEM_HOSTNAME}]: Storage [{#STORAGE_ID}]: Volume [{#VOLUME_ID}]: Volume is in critical state|<p>The volume is in critical condition that requires immediate attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.volume.status[{#SYSTEM_ID}, {#STORAGE_ID}, {#VOLUME_ID}])=2`|High||

### LLD rule HPE iLO: Fans discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Fans discovery|<p>Discovers chassis fans.</p>|Dependent item|hpe.ilo.fans.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for HPE iLO: Fans discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Chassis [{#CHASSIS_ID}]: Fan [{#FAN_NAME}]: Get data|<p>Get data about the fan.</p>|Dependent item|hpe.ilo.fan.get_data[{#CHASSIS_ID}, {#FAN_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HPE iLO: Chassis [{#CHASSIS_ID}]: Fan [{#FAN_NAME}]: Status|<p>The health state of the fan. Possible values:</p><p></p><p>0 - "OK", the fan is in normal condition;</p><p>1 - "Warning", the fan is in condition that requires attention;</p><p>2 - "Critical", the fan is in critical condition that requires immediate attention;</p><p>10 - "Unknown", the fan is in unknown condition.</p>|Dependent item|hpe.ilo.fan.status[{#CHASSIS_ID}, {#FAN_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.Health`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|HPE iLO: Chassis [{#CHASSIS_ID}]: Fan [{#FAN_NAME}]: Speed, in %|<p>The current speed of the fan.</p>|Dependent item|hpe.ilo.fan.speed[{#CHASSIS_ID}, {#FAN_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Reading`</p></li></ul>|

### Trigger prototypes for HPE iLO: Fans discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE iLO: Chassis [{#CHASSIS_ID}]: Fan [{#FAN_NAME}]: Fan is in warning state|<p>The fan is in condition that requires attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.fan.status[{#CHASSIS_ID}, {#FAN_ID}])=1`|Warning|**Depends on**:<br><ul><li>HPE iLO: Chassis [{#CHASSIS_ID}]: Fan [{#FAN_NAME}]: Fan is in critical state</li></ul>|
|HPE iLO: Chassis [{#CHASSIS_ID}]: Fan [{#FAN_NAME}]: Fan is in critical state|<p>The fan is in critical condition that requires immediate attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.fan.status[{#CHASSIS_ID}, {#FAN_ID}])=2`|High||

### LLD rule HPE iLO: Temperature sensors discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Temperature sensors discovery|<p>Discovers chassis temperature sensors.</p>|Dependent item|hpe.ilo.sensors.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for HPE iLO: Temperature sensors discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Chassis [{#CHASSIS_ID}]: Sensor [{#SENSOR_NAME}]: Get data|<p>Get data about the sensor.</p>|Dependent item|hpe.ilo.sensor.get_data[{#CHASSIS_ID}, {#SENSOR_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HPE iLO: Chassis [{#CHASSIS_ID}]: Sensor [{#SENSOR_NAME}]: Status|<p>The health state of the sensor. Possible values:</p><p></p><p>0 - "OK", the sensor is in normal condition;</p><p>1 - "Warning", the sensor is in condition that requires attention;</p><p>2 - "Critical", the sensor is in critical condition that requires immediate attention;</p><p>10 - "Unknown", the sensor is in unknown condition.</p>|Dependent item|hpe.ilo.sensor.status[{#CHASSIS_ID}, {#SENSOR_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.Health`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|HPE iLO: Chassis [{#CHASSIS_ID}]: Sensor [{#SENSOR_NAME}]: Temperature|<p>The current temperature reading in Celsius degrees for the sensor.</p>|Dependent item|hpe.ilo.sensor.temperature[{#CHASSIS_ID}, {#SENSOR_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ReadingCelsius`</p></li></ul>|

### Trigger prototypes for HPE iLO: Temperature sensors discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE iLO: Chassis [{#CHASSIS_ID}]: Sensor [{#SENSOR_NAME}]: Sensor is in warning state|<p>The sensor is in condition that requires attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.sensor.status[{#CHASSIS_ID}, {#SENSOR_ID}])=1`|Warning|**Depends on**:<br><ul><li>HPE iLO: Chassis [{#CHASSIS_ID}]: Sensor [{#SENSOR_NAME}]: Sensor is in critical state</li></ul>|
|HPE iLO: Chassis [{#CHASSIS_ID}]: Sensor [{#SENSOR_NAME}]: Sensor is in critical state|<p>The sensor is in critical condition that requires immediate attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.sensor.status[{#CHASSIS_ID}, {#SENSOR_ID}])=2`|High||

### LLD rule HPE iLO: PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: PSU discovery|<p>Discovers chassis power supply units (PSU).</p>|Dependent item|hpe.ilo.psu.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for HPE iLO: PSU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|HPE iLO: Chassis [{#CHASSIS_ID}]: PSU [{#PSU_ID}]: Get data|<p>Get data about the PSU.</p>|Dependent item|hpe.ilo.psu.get_data[{#CHASSIS_ID}, {#PSU_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|HPE iLO: Chassis [{#CHASSIS_ID}]: PSU [{#PSU_ID}]: Model|<p>The model name of the PSU.</p>|Dependent item|hpe.ilo.psu.model[{#CHASSIS_ID}, {#PSU_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Model`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Chassis [{#CHASSIS_ID}]: PSU [{#PSU_ID}]: Serial number|<p>The serial number of the PSU.</p>|Dependent item|hpe.ilo.psu.serial_number[{#CHASSIS_ID}, {#PSU_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.SerialNumber`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|HPE iLO: Chassis [{#CHASSIS_ID}]: PSU [{#PSU_ID}]: Status|<p>The health state of the PSU. Possible values:</p><p></p><p>0 - "OK", the PSU is in normal condition;</p><p>1 - "Warning", the PSU is in condition that requires attention;</p><p>2 - "Critical", the PSU is in critical condition that requires immediate attention;</p><p>10 - "Unknown", the PSU is in unknown condition.</p>|Dependent item|hpe.ilo.psu.status[{#CHASSIS_ID}, {#PSU_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.Status.Health`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|HPE iLO: Chassis [{#CHASSIS_ID}]: PSU [{#PSU_ID}]: Line input voltage|<p>The line input voltage at which the PSU is operating.</p>|Dependent item|hpe.ilo.psu.line_input_voltage[{#CHASSIS_ID}, {#PSU_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LineInputVoltage`</p></li></ul>|
|HPE iLO: Chassis [{#CHASSIS_ID}]: PSU [{#PSU_ID}]: Last power output|<p>The average power output of the PSU.</p>|Dependent item|hpe.ilo.psu.last_power_output[{#CHASSIS_ID}, {#PSU_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.LastPowerOutputWatts`</p></li></ul>|

### Trigger prototypes for HPE iLO: PSU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HPE iLO: Chassis [{#CHASSIS_ID}]: PSU [{#PSU_ID}]: PSU has been replaced|<p>The PSU serial number has changed. Acknowledge to close the problem manually.</p>|`change(/HPE iLO by HTTP/hpe.ilo.psu.serial_number[{#CHASSIS_ID}, {#PSU_ID}])=1 and length(last(/HPE iLO by HTTP/hpe.ilo.psu.serial_number[{#CHASSIS_ID}, {#PSU_ID}]))>0`|Info|**Manual close**: Yes|
|HPE iLO: Chassis [{#CHASSIS_ID}]: PSU [{#PSU_ID}]: PSU is in warning state|<p>The PSU is in condition that requires attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.psu.status[{#CHASSIS_ID}, {#PSU_ID}])=1`|Warning|**Depends on**:<br><ul><li>HPE iLO: Chassis [{#CHASSIS_ID}]: PSU [{#PSU_ID}]: PSU is in critical state</li></ul>|
|HPE iLO: Chassis [{#CHASSIS_ID}]: PSU [{#PSU_ID}]: PSU is in critical state|<p>The PSU is in critical condition that requires immediate attention.</p>|`last(/HPE iLO by HTTP/hpe.ilo.psu.status[{#CHASSIS_ID}, {#PSU_ID}])=2`|High||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

