
# Proxmox VE by HTTP

## Overview

This template is designed for the effortless deployment of Proxmox VE monitoring by Zabbix via HTTP and doesn't require any external scripts.

Proxmox VE uses a REST like API. The concept is described in (Resource Oriented Architecture - ROA).
You can explore the API documentation at http://pve.proxmox.com/pve-docs/api-viewer/index.html

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Proxmox VE

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Create an API token for the monitoring user. Important note: for security reasons, it is recommended to create a separate user (Datacenter - Permissions).

Please provide the necessary access levels for both the User and the Token.

* Check: ["perm","/",["Sys.Audit"]]

* Check: ["perm","/storage",["Datastore.Audit"]]

* Check: ["perm","/vms",["VM.Audit"]]

Copy the resulting Token ID and Secret into host macros.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PVE.URL.PORT}|<p>The API uses the HTTPS protocol and the server listens to port 8006 by default.</p>|`8006`|
|{$PVE.TOKEN.ID}|<p>API tokens allow stateless access to most parts of the REST API by another system, software or API client.</p>|`USER@REALM!TOKENID`|
|{$PVE.TOKEN.SECRET}|<p>Secret key.</p>|`xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`|
|{$PVE.ROOT.PUSE.MAX.WARN}|<p>Maximum used root space in percentage.</p>|`90`|
|{$PVE.MEMORY.PUSE.MAX.WARN}|<p>Maximum used memory in percentage.</p>|`90`|
|{$PVE.CPU.PUSE.MAX.WARN}|<p>Maximum used CPU in percentage.</p>|`90`|
|{$PVE.SWAP.PUSE.MAX.WARN}|<p>Maximum used swap space in percentage.</p>|`90`|
|{$PVE.VM.MEMORY.PUSE.MAX.WARN}|<p>Maximum used memory in percentage.</p>|`90`|
|{$PVE.VM.CPU.PUSE.MAX.WARN}|<p>Maximum used CPU in percentage.</p>|`90`|
|{$PVE.LXC.MEMORY.PUSE.MAX.WARN}|<p>Maximum used memory in percentage.</p>|`90`|
|{$PVE.LXC.CPU.PUSE.MAX.WARN}|<p>Maximum used CPU in percentage.</p>|`90`|
|{$PVE.STORAGE.PUSE.MAX.WARN}|<p>Maximum used storage space in percentage.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxmox: Get cluster resources|<p>Resources index.</p>|HTTP agent|proxmox.cluster.resources<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `Error getting data`</p></li></ul>|
|Proxmox: Get cluster status|<p>Get cluster status information.</p>|HTTP agent|proxmox.cluster.status<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `Error getting data`</p></li></ul>|
|Proxmox: API service status|<p>Get API service status.</p>|Script|proxmox.api.available<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox: API service not available|<p>The API service is not available. Check your network and authorization settings.</p>|`last(/Proxmox VE by HTTP/proxmox.api.available) <> 200`|High||

### LLD rule Cluster discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster discovery||Dependent item|proxmox.cluster.discovery|

### Item prototypes for Cluster discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxmox: Cluster [{#RESOURCE.NAME}]: Quorate|<p>Indicates if there is a majority of nodes online to make decisions.</p>|Dependent item|proxmox.cluster.quorate[{#RESOURCE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|

### Trigger prototypes for Cluster discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox: Cluster [{#RESOURCE.NAME}] not quorum|<p>Proxmox VE use a quorum-based technique to provide a consistent state among all cluster nodes.</p>|`last(/Proxmox VE by HTTP/proxmox.cluster.quorate[{#RESOURCE.NAME}]) <> 1`|High||

### LLD rule Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node discovery||Dependent item|proxmox.node.discovery|

### Item prototypes for Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxmox: Node [{#NODE.NAME}]: Status|<p>Indicates if the node is online or offline.</p>|Dependent item|proxmox.node.online[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Status|<p>Read node status.</p>|HTTP agent|proxmox.node.status[{#NODE.NAME}]|
|Proxmox: Node [{#NODE.NAME}]: RRD statistics|<p>Read node RRD statistics.</p>|HTTP agent|proxmox.node.rrd[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Time|<p>Read server time and time zone settings.</p>|HTTP agent|proxmox.node.time[{#NODE.NAME}]|
|Proxmox: Node [{#NODE.NAME}]: Uptime|<p>The system uptime expressed in the following format: "N days, hh:mm:ss".</p>|Dependent item|proxmox.node.uptime[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.uptime`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: PVE version|<p>PVE manager version.</p>|Dependent item|proxmox.node.pveversion[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.pveversion`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Kernel version|<p>Kernel version info.</p>|Dependent item|proxmox.node.kernelversion[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.kversion`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Root filesystem, used|<p>Root filesystem usage.</p>|Dependent item|proxmox.node.rootused[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rootused`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Root filesystem, total|<p>Root filesystem total.</p>|Dependent item|proxmox.node.roottotal[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.roottotal`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Memory, used|<p>Memory usage.</p>|Dependent item|proxmox.node.memused[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memused`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Memory, total|<p>Memory total.</p>|Dependent item|proxmox.node.memtotal[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memtotal`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: CPU, usage|<p>CPU usage.</p>|Dependent item|proxmox.node.cpu[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu`</p></li><li><p>Custom multiplier: `100`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Outgoing data, rate|<p>Network usage.</p>|Dependent item|proxmox.node.netout[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.netout`</p></li><li><p>Custom multiplier: `8`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Incoming data, rate|<p>Network usage.</p>|Dependent item|proxmox.node.netin[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.netin`</p></li><li><p>Custom multiplier: `8`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: CPU, loadavg|<p>CPU average load.</p>|Dependent item|proxmox.node.loadavg[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.loadavg`</p></li><li><p>Custom multiplier: `100`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: CPU, iowait|<p>CPU iowait time.</p>|Dependent item|proxmox.node.iowait[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.iowait`</p></li><li><p>Custom multiplier: `100`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Swap filesystem, total|<p>Swap total.</p>|Dependent item|proxmox.node.swaptotal[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.swaptotal`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Swap filesystem, used|<p>Swap used.</p>|Dependent item|proxmox.node.swapused[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.swapused`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Time zone|<p>Time zone.</p>|Dependent item|proxmox.node.timezone[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.timezone`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Localtime|<p>Seconds since 1970-01-01 00:00:00 (local time).</p>|Dependent item|proxmox.node.localtime[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.localtime`</p></li></ul>|
|Proxmox: Node [{#NODE.NAME}]: Time|<p>Seconds since 1970-01-01 00:00:00 UTC.</p>|Dependent item|proxmox.node.utctime[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.time`</p></li></ul>|

### Trigger prototypes for Node discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox: Node [{#NODE.NAME}] offline|<p>Node offline.</p>|`last(/Proxmox VE by HTTP/proxmox.node.online[{#NODE.NAME}]) <> 1`|High||
|Proxmox: Node [{#NODE.NAME}]: has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Proxmox VE by HTTP/proxmox.node.uptime[{#NODE.NAME}])<10m`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Proxmox: Node [{#NODE.NAME}] offline</li></ul>|
|Proxmox: Node [{#NODE.NAME}]: PVE manager has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/Proxmox VE by HTTP/proxmox.node.pveversion[{#NODE.NAME}],#1)<>last(/Proxmox VE by HTTP/proxmox.node.pveversion[{#NODE.NAME}],#2) and length(last(/Proxmox VE by HTTP/proxmox.node.pveversion[{#NODE.NAME}]))>0`|Info|**Manual close**: Yes|
|Proxmox: Node [{#NODE.NAME}]: Kernel version has changed|<p>Firmware version has changed. Acknowledge to close the problem manually.</p>|`last(/Proxmox VE by HTTP/proxmox.node.kernelversion[{#NODE.NAME}],#1)<>last(/Proxmox VE by HTTP/proxmox.node.kernelversion[{#NODE.NAME}],#2) and length(last(/Proxmox VE by HTTP/proxmox.node.kernelversion[{#NODE.NAME}]))>0`|Info|**Manual close**: Yes|
|Proxmox: Node [{#NODE.NAME}] high root filesystem space usage|<p>Root filesystem space usage.</p>|`min(/Proxmox VE by HTTP/proxmox.node.rootused[{#NODE.NAME}],5m) / last(/Proxmox VE by HTTP/proxmox.node.roottotal[{#NODE.NAME}]) * 100 >{$PVE.ROOT.PUSE.MAX.WARN:"{#NODE.NAME}"}`|Warning||
|Proxmox: Node [{#NODE.NAME}] high memory usage|<p>Memory usage.</p>|`min(/Proxmox VE by HTTP/proxmox.node.memused[{#NODE.NAME}],5m) / last(/Proxmox VE by HTTP/proxmox.node.memtotal[{#NODE.NAME}]) * 100 >{$PVE.MEMORY.PUSE.MAX.WARN:"{#NODE.NAME}"}`|Warning||
|Proxmox: Node [{#NODE.NAME}] high CPU usage|<p>CPU usage.</p>|`min(/Proxmox VE by HTTP/proxmox.node.cpu[{#NODE.NAME}],5m) > {$PVE.CPU.PUSE.MAX.WARN:"{#NODE.NAME}"}`|Warning||
|Proxmox: Node [{#NODE.NAME}] high root filesystem space usage|<p>If there is no swap configured, this trigger is ignored.</p>|`min(/Proxmox VE by HTTP/proxmox.node.swapused[{#NODE.NAME}],5m) / last(/Proxmox VE by HTTP/proxmox.node.swaptotal[{#NODE.NAME}]) * 100 > {$PVE.SWAP.PUSE.MAX.WARN:"{#NODE.NAME}"} and last(/Proxmox VE by HTTP/proxmox.node.swaptotal[{#NODE.NAME}]) > 0`|Warning||

### LLD rule Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage discovery||Dependent item|proxmox.storage.discovery|

### Item prototypes for Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxmox: Storage [{#NODE.NAME}/{#STORAGE.NAME}]: Type|<p>More specific type, if available.</p>|Dependent item|proxmox.node.plugintype[{#NODE.NAME},{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Proxmox: Storage [{#NODE.NAME}/{#STORAGE.NAME}]: Size|<p>Storage size in bytes.</p>|Dependent item|proxmox.node.maxdisk[{#NODE.NAME},{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: Storage [{#NODE.NAME}/{#STORAGE.NAME}]: Content|<p>Allowed storage content types.</p>|Dependent item|proxmox.node.content[{#NODE.NAME},{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Proxmox: Storage [{#NODE.NAME}/{#STORAGE.NAME}]: Used|<p>Used disk space in bytes.</p>|Dependent item|proxmox.node.disk[{#NODE.NAME},{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|

### Trigger prototypes for Storage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox: Storage [{#NODE.NAME}/{#STORAGE.NAME}] high filesystem space usage|<p>Root filesystem space usage.</p>|`min(/Proxmox VE by HTTP/proxmox.node.disk[{#NODE.NAME},{#STORAGE.NAME}],5m) / last(/Proxmox VE by HTTP/proxmox.node.maxdisk[{#NODE.NAME},{#STORAGE.NAME}]) * 100 >{$PVE.STORAGE.PUSE.MAX.WARN:"{#NODE.NAME}/{#STORAGE.NAME}"}`|Warning||

### LLD rule QEMU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|QEMU discovery||Dependent item|proxmox.qemu.discovery|

### Item prototypes for QEMU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Disk write, rate|<p>Disk write.</p>|Dependent item|proxmox.qemu.diskwrite[{#QEMU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.diskwrite`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Disk read, rate|<p>Disk read.</p>|Dependent item|proxmox.qemu.diskread[{#QEMU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.diskread`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Memory usage|<p>Used memory in bytes.</p>|Dependent item|proxmox.qemu.mem[{#QEMU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.mem`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Memory total|<p>The total memory expressed in bytes.</p>|Dependent item|proxmox.qemu.maxmem[{#QEMU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.maxmem`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Incoming data, rate|<p>Incoming data rate.</p>|Dependent item|proxmox.qemu.netin[{#QEMU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.netin`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Outgoing data, rate|<p>Outgoing data rate.</p>|Dependent item|proxmox.qemu.netout[{#QEMU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.netout`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: CPU usage|<p>CPU load.</p>|Dependent item|proxmox.qemu.cpu[{#QEMU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cpu`</p></li><li><p>Custom multiplier: `100`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME}]: Status|<p>Read VM status.</p>|HTTP agent|proxmox.qemu.status[{#QEMU.ID}]|
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Uptime|<p>The system uptime expressed in the following format: "N days, hh:mm:ss".</p>|Dependent item|proxmox.qemu.uptime[{#QEMU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.uptime`</p></li></ul>|
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Status||Dependent item|proxmox.qemu.vmstatus[{#QEMU.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.status`</p></li></ul>|

### Trigger prototypes for QEMU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})] high memory usage|<p>Memory usage.</p>|`min(/Proxmox VE by HTTP/proxmox.qemu.mem[{#QEMU.ID}],5m) / last(/Proxmox VE by HTTP/proxmox.qemu.maxmem[{#QEMU.ID}]) * 100 >{$PVE.VM.MEMORY.PUSE.MAX.WARN:"{#QEMU.ID}"}`|Warning||
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})] high CPU usage|<p>CPU usage.</p>|`min(/Proxmox VE by HTTP/proxmox.qemu.cpu[{#QEMU.ID}],5m) > {$PVE.VM.CPU.PUSE.MAX.WARN:"{#QEMU.ID}"}`|Warning||
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME}]: has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Proxmox VE by HTTP/proxmox.qemu.uptime[{#QEMU.ID}])<10m`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Not running</li></ul>|
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Not running|<p>VM state is not "running".</p>|`last(/Proxmox VE by HTTP/proxmox.qemu.vmstatus[{#QEMU.ID}])<>"running"`|Average||

### LLD rule LXC discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|LXC discovery||Dependent item|proxmox.lxc.discovery|

### Item prototypes for LXC discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxmox: LXC [{#LXC.NAME}/{#LXC.NAME}]: Status|<p>Read LXC status.</p>|HTTP agent|proxmox.lxc.status[{#LXC.ID}]|
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Uptime|<p>The system uptime expressed in the following format: "N days, hh:mm:ss".</p>|Dependent item|proxmox.lxc.uptime[{#LXC.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.uptime`</p></li></ul>|
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Status||Dependent item|proxmox.lxc.vmstatus[{#LXC.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.status`</p></li></ul>|
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Disk write, rate|<p>Disk write.</p>|Dependent item|proxmox.lxc.diskwrite[{#LXC.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.diskwrite`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Disk read, rate|<p>Disk read.</p>|Dependent item|proxmox.lxc.diskread[{#LXC.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.diskread`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Memory usage|<p>Used memory in bytes.</p>|Dependent item|proxmox.lxc.mem[{#LXC.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.mem`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Memory total|<p>The total memory expressed in bytes.</p>|Dependent item|proxmox.lxc.maxmem[{#LXC.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.maxmem`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Incoming data, rate|<p>Incoming data rate.</p>|Dependent item|proxmox.lxc.netin[{#LXC.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.netin`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Outgoing data, rate|<p>Outgoing data rate.</p>|Dependent item|proxmox.lxc.netout[{#LXC.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.netout`</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: CPU usage|<p>CPU load.</p>|Dependent item|proxmox.lxc.cpu[{#LXC.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cpu`</p></li><li><p>Custom multiplier: `100`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|

### Trigger prototypes for LXC discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME}]: has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Proxmox VE by HTTP/proxmox.lxc.uptime[{#LXC.ID}])<10m`|Info|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Not running</li></ul>|
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Not running|<p>LXC state is not "running".</p>|`last(/Proxmox VE by HTTP/proxmox.lxc.vmstatus[{#LXC.ID}])<>"running"`|Average||
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})] high memory usage|<p>Memory usage.</p>|`min(/Proxmox VE by HTTP/proxmox.lxc.mem[{#LXC.ID}],5m) / last(/Proxmox VE by HTTP/proxmox.lxc.maxmem[{#LXC.ID}]) * 100 >{$PVE.LXC.MEMORY.PUSE.MAX.WARN:"{#LXC.ID}"}`|Warning||
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})] high CPU usage|<p>CPU usage.</p>|`min(/Proxmox VE by HTTP/proxmox.lxc.cpu[{#LXC.ID}],5m) > {$PVE.LXC.CPU.PUSE.MAX.WARN:"{#LXC.ID}"}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

