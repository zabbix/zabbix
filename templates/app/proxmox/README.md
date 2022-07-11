
# Proxmox VE by HTTP

## Overview

For Zabbix version: 6.0 and higher  
Proxmox VE uses a REST like API. The concept is described in (Resource Oriented Architecture - ROA).

We choose JSON as primary data format, and the whole API is formally defined using JSON Schema.

You can explore the API documentation at http://pve.proxmox.com/pve-docs/api-viewer/index.html


## Setup

Create an API token for the monitoring user. Important note: for security reasons, it is recommended to create a separate user (Datacenter - Permissions).

For the created API token and user, provide the necessary access levels:

* Check: ["perm","/",["Sys.Audit"]]

* Check: ["perm","/nodes/{node}",["Sys.Audit"]]

* Check: ["perm","/vms/{vmid}",["VM.Audit"]]

Copy the resulting Token ID and Secret into host macros.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PVE.CPU.PUSE.MAX.WARN} |<p>Maximum used CPU in percentage.</p> |`90` |
|{$PVE.LXC.CPU.PUSE.MAX.WARN} |<p>Maximum used CPU in percentage.</p> |`90` |
|{$PVE.LXC.MEMORY.PUSE.MAX.WARN} |<p>Maximum used memory in percentage.</p> |`90` |
|{$PVE.MEMORY.PUSE.MAX.WARN} |<p>Maximum used memory in percentage.</p> |`90` |
|{$PVE.ROOT.PUSE.MAX.WARN} |<p>Maximum used root space in percentage.</p> |`90` |
|{$PVE.STORAGE.PUSE.MAX.WARN} |<p>Maximum used storage space in percentage.</p> |`90` |
|{$PVE.SWAP.PUSE.MAX.WARN} |<p>Maximum used swap space in percentage.</p> |`90` |
|{$PVE.TOKEN.ID} |<p>API tokens allow stateless access to most parts of the REST API by another system, software or API client.</p> |`USER@REALM!TOKENID` |
|{$PVE.TOKEN.SECRET} |<p>Secret key.</p> |`xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx` |
|{$PVE.URL.PORT} |<p>The API uses the HTTPS protocol and the server listens to port 8006 by default.</p> |`8006` |
|{$PVE.VM.CPU.PUSE.MAX.WARN} |<p>Maximum used CPU in percentage.</p> |`90` |
|{$PVE.VM.MEMORY.PUSE.MAX.WARN} |<p>Maximum used memory in percentage.</p> |`90` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Cluster discovery |<p>-</p> |DEPENDENT |proxmox.cluster.discovery<p>**Filter**:</p>AND <p>- {#RESOURCE.TYPE} MATCHES_REGEX `^cluster$`</p> |
|LXC discovery |<p>-</p> |DEPENDENT |proxmox.lxc.discovery<p>**Filter**:</p>AND <p>- {#RESOURCE.TYPE} MATCHES_REGEX `^lxc$`</p> |
|Node discovery |<p>-</p> |DEPENDENT |proxmox.node.discovery<p>**Filter**:</p>AND <p>- {#RESOURCE.TYPE} MATCHES_REGEX `^node$`</p> |
|QEMU discovery |<p>-</p> |DEPENDENT |proxmox.qemu.discovery<p>**Filter**:</p>AND <p>- {#RESOURCE.TYPE} MATCHES_REGEX `^qemu$`</p> |
|Storage discovery |<p>-</p> |DEPENDENT |proxmox.storage.discovery<p>**Filter**:</p>AND <p>- {#RESOURCE.TYPE} MATCHES_REGEX `^storage$`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |Proxmox: Node [{#NODE.NAME}]: CPU, usage |<p>CPU usage.</p> |DEPENDENT |proxmox.node.cpu[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.cpu`</p><p>- MULTIPLIER: `100`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|CPU |Proxmox: Node [{#NODE.NAME}]: CPU, loadavg |<p>CPU average load.</p> |DEPENDENT |proxmox.node.loadavg[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.loadavg`</p><p>- MULTIPLIER: `100`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|CPU |Proxmox: Node [{#NODE.NAME}]: CPU, iowait |<p>CPU iowait time.</p> |DEPENDENT |proxmox.node.iowait[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.iowait`</p><p>- MULTIPLIER: `100`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|CPU |Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: CPU usage |<p>CPU load.</p> |DEPENDENT |proxmox.qemu.cpu[{#QEMU.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.cpu`</p><p>- MULTIPLIER: `100`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|CPU |Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: CPU usage |<p>CPU load.</p> |DEPENDENT |proxmox.lxc.cpu[{#LXC.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.cpu`</p><p>- MULTIPLIER: `100`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|General |Proxmox: Node [{#NODE.NAME}]: Time zone |<p>Time zone.</p> |DEPENDENT |proxmox.node.timezone[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.timezone`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|General |Proxmox: Node [{#NODE.NAME}]: Localtime |<p>Seconds since 1970-01-01 00:00:00 (local time).</p> |DEPENDENT |proxmox.node.localtime[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.localtime`</p> |
|General |Proxmox: Node [{#NODE.NAME}]: Time |<p>Seconds since 1970-01-01 00:00:00 UTC.</p> |DEPENDENT |proxmox.node.utctime[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.time`</p> |
|Inventory |Proxmox: Node [{#NODE.NAME}]: PVE version |<p>PVE manager version.</p> |DEPENDENT |proxmox.node.pveversion[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.pveversion`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Proxmox: Node [{#NODE.NAME}]: Kernel version |<p>Kernel version info.</p> |DEPENDENT |proxmox.node.kernelversion[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.kversion`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |Proxmox: Node [{#NODE.NAME}]: Memory, used |<p>Memory usage.</p> |DEPENDENT |proxmox.node.memused[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.memused`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Memory |Proxmox: Node [{#NODE.NAME}]: Memory, total |<p>Memory total.</p> |DEPENDENT |proxmox.node.memtotal[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.memtotal`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Memory |Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Memory usage |<p>Used memory in Bytes.</p> |DEPENDENT |proxmox.qemu.mem[{#QEMU.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.mem`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Memory |Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Memory total |<p>Total memory in Bytes.</p> |DEPENDENT |proxmox.qemu.maxmem[{#QEMU.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.maxmem`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Memory |Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Memory usage |<p>Used memory in Bytes.</p> |DEPENDENT |proxmox.lxc.mem[{#LXC.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.mem`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Memory |Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Memory total |<p>Total memory in Bytes.</p> |DEPENDENT |proxmox.lxc.maxmem[{#LXC.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.maxmem`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Network interfaces |Proxmox: Node [{#NODE.NAME}]: Outgoing data, rate |<p>Network usage.</p> |DEPENDENT |proxmox.node.netout[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.netout`</p><p>- MULTIPLIER: `8`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Network interfaces |Proxmox: Node [{#NODE.NAME}]: Incoming data, rate |<p>Network usage.</p> |DEPENDENT |proxmox.node.netin[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.netin`</p><p>- MULTIPLIER: `8`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Network interfaces |Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Incoming data, rate |<p>Incoming data rate.</p> |DEPENDENT |proxmox.qemu.netin[{#QEMU.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.netin`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Network interfaces |Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Outgoing data, rate |<p>Outgoing data rate.</p> |DEPENDENT |proxmox.qemu.netout[{#QEMU.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.netout`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Network interfaces |Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Incoming data, rate |<p>Incoming data rate.</p> |DEPENDENT |proxmox.lxc.netin[{#LXC.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.netin`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Network interfaces |Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Outgoing data, rate |<p>Outgoing data rate.</p> |DEPENDENT |proxmox.lxc.netout[{#LXC.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.netout`</p><p>- CHANGE_PER_SECOND</p><p>- MULTIPLIER: `8`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Status |Proxmox: API service status |<p>Get API service status.</p> |SCRIPT |proxmox.api.available<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p><p>**Expression**:</p>`The text is too long. Please see the template.` |
|Status |Proxmox: Cluster [{#RESOURCE.NAME}]: Quorate |<p>Indicates if there is a majority of nodes online to make decisions.</p> |DEPENDENT |proxmox.cluster.quorate[{#RESOURCE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.[?(@.name == '{#RESOURCE.NAME}' && @.type == 'cluster')].quorate.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Status |Proxmox: Node [{#NODE.NAME}]: Status |<p>Indicates if the node is online or offline.</p> |DEPENDENT |proxmox.node.online[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.[?(@.name == '{#NODE.NAME}' && @.type == 'node')].online.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Status |Proxmox: Node [{#NODE.NAME}]: Uptime |<p>System uptime in 'N days, hh:mm:ss' format.</p> |DEPENDENT |proxmox.node.uptime[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.uptime`</p> |
|Status |Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Uptime |<p>System uptime in 'N days, hh:mm:ss' format.</p> |DEPENDENT |proxmox.qemu.uptime[{#QEMU.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.uptime`</p> |
|Status |Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Status |<p>-</p> |DEPENDENT |proxmox.qemu.vmstatus[{#QEMU.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.status`</p> |
|Status |Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Uptime |<p>System uptime in 'N days, hh:mm:ss' format.</p> |DEPENDENT |proxmox.lxc.uptime[{#LXC.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.uptime`</p> |
|Status |Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Status |<p>-</p> |DEPENDENT |proxmox.lxc.vmstatus[{#LXC.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.status`</p> |
|Storage |Proxmox: Node [{#NODE.NAME}]: Root filesystem, used |<p>Root filesystem usage.</p> |DEPENDENT |proxmox.node.rootused[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.rootused`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Storage |Proxmox: Node [{#NODE.NAME}]: Root filesystem, total |<p>Root filesystem total.</p> |DEPENDENT |proxmox.node.roottotal[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.roottotal`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Storage |Proxmox: Node [{#NODE.NAME}]: Swap filesystem, total |<p>Swap total.</p> |DEPENDENT |proxmox.node.swaptotal[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.swaptotal`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Storage |Proxmox: Node [{#NODE.NAME}]: Swap filesystem, used |<p>Swap used.</p> |DEPENDENT |proxmox.node.swapused[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.swapused`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Storage |Proxmox: Storage [{#NODE.NAME}/{#STORAGE.NAME}]: Type |<p>More specific type, if available.</p> |DEPENDENT |proxmox.node.plugintype[{#NODE.NAME},{#STORAGE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data[?(@.id == "storage/{#NODE.NAME}/{#STORAGE.NAME}")].plugintype.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Storage |Proxmox: Storage [{#NODE.NAME}/{#STORAGE.NAME}]: Size |<p>Storage size in bytes.</p> |DEPENDENT |proxmox.node.maxdisk[{#NODE.NAME},{#STORAGE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data[?(@.id == "storage/{#NODE.NAME}/{#STORAGE.NAME}")].maxdisk.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Storage |Proxmox: Storage [{#NODE.NAME}/{#STORAGE.NAME}]: Content |<p>Allowed storage content types.</p> |DEPENDENT |proxmox.node.content[{#NODE.NAME},{#STORAGE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data[?(@.id == "storage/{#NODE.NAME}/{#STORAGE.NAME}")].content.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `12h`</p> |
|Storage |Proxmox: Storage [{#NODE.NAME}/{#STORAGE.NAME}]: Used |<p>Used disk space in bytes.</p> |DEPENDENT |proxmox.node.disk[{#NODE.NAME},{#STORAGE.NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data[?(@.id == "storage/{#NODE.NAME}/{#STORAGE.NAME}")].disk.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Storage |Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Disk write, rate |<p>Disk write.</p> |DEPENDENT |proxmox.qemu.diskwrite[{#QEMU.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.diskwrite`</p><p>- CHANGE_PER_SECOND</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Storage |Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Disk read, rate |<p>Disk read.</p> |DEPENDENT |proxmox.qemu.diskread[{#QEMU.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.diskread`</p><p>- CHANGE_PER_SECOND</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Storage |Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Disk write, rate |<p>Disk write.</p> |DEPENDENT |proxmox.lxc.diskwrite[{#LXC.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.diskwrite`</p><p>- CHANGE_PER_SECOND</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Storage |Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Disk read, rate |<p>Disk read.</p> |DEPENDENT |proxmox.lxc.diskread[{#LXC.ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.data.diskread`</p><p>- CHANGE_PER_SECOND</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Zabbix raw items |Proxmox: Get cluster resources |<p>Resources index.</p> |HTTP_AGENT |proxmox.cluster.resources<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> Error getting data`</p> |
|Zabbix raw items |Proxmox: Get cluster status |<p>Get cluster status information.</p> |HTTP_AGENT |proxmox.cluster.status<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> Error getting data`</p> |
|Zabbix raw items |Proxmox: Node [{#NODE.NAME}]: Status |<p>Read node status.</p> |HTTP_AGENT |proxmox.node.status[{#NODE.NAME}] |
|Zabbix raw items |Proxmox: Node [{#NODE.NAME}]: RRD statistics |<p>Read node RRD statistics.</p> |HTTP_AGENT |proxmox.node.rrd[{#NODE.NAME}]<p>**Preprocessing**:</p><p>- JAVASCRIPT: `var rrd_data = JSON.parse(value).data; return JSON.stringify(rrd_data[rrd_data.length - 2]) `</p> |
|Zabbix raw items |Proxmox: Node [{#NODE.NAME}]: Time |<p>Read server time and time zone settings.</p> |HTTP_AGENT |proxmox.node.time[{#NODE.NAME}] |
|Zabbix raw items |Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME}]: Status |<p>Read VM status.</p> |HTTP_AGENT |proxmox.qemu.status[{#QEMU.ID}] |
|Zabbix raw items |Proxmox: LXC [{#LXC.NAME}/{#LXC.NAME}]: Status |<p>Read LXC status.</p> |HTTP_AGENT |proxmox.lxc.status[{#LXC.ID}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Proxmox: Node [{#NODE.NAME}] high CPU usage |<p>CPU usage.</p> |`min(/Proxmox VE by HTTP/proxmox.node.cpu[{#NODE.NAME}],5m) > {$PVE.CPU.PUSE.MAX.WARN:"{#NODE.NAME}"}` |WARNING | |
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})] high CPU usage |<p>CPU usage.</p> |`min(/Proxmox VE by HTTP/proxmox.qemu.cpu[{#QEMU.ID}],5m) > {$PVE.VM.CPU.PUSE.MAX.WARN:"{#QEMU.ID}"}` |WARNING | |
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})] high CPU usage |<p>CPU usage.</p> |`min(/Proxmox VE by HTTP/proxmox.lxc.cpu[{#LXC.ID}],5m) > {$PVE.LXC.CPU.PUSE.MAX.WARN:"{#LXC.ID}"}` |WARNING | |
|Proxmox: Node [{#NODE.NAME}]: PVE manager has changed |<p>Firmware version has changed. Ack to close</p> |`last(/Proxmox VE by HTTP/proxmox.node.pveversion[{#NODE.NAME}],#1)<>last(/Proxmox VE by HTTP/proxmox.node.pveversion[{#NODE.NAME}],#2) and length(last(/Proxmox VE by HTTP/proxmox.node.pveversion[{#NODE.NAME}]))>0` |INFO |<p>Manual close: YES</p> |
|Proxmox: Node [{#NODE.NAME}]: Kernel version has changed |<p>Firmware version has changed. Ack to close</p> |`last(/Proxmox VE by HTTP/proxmox.node.kernelversion[{#NODE.NAME}],#1)<>last(/Proxmox VE by HTTP/proxmox.node.kernelversion[{#NODE.NAME}],#2) and length(last(/Proxmox VE by HTTP/proxmox.node.kernelversion[{#NODE.NAME}]))>0` |INFO |<p>Manual close: YES</p> |
|Proxmox: Node [{#NODE.NAME}] high memory usage |<p>Memory usage.</p> |`min(/Proxmox VE by HTTP/proxmox.node.memused[{#NODE.NAME}],5m) / last(/Proxmox VE by HTTP/proxmox.node.memtotal[{#NODE.NAME}]) * 100 >{$PVE.MEMORY.PUSE.MAX.WARN:"{#NODE.NAME}"}` |WARNING | |
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})] high memory usage |<p>Memory usage.</p> |`min(/Proxmox VE by HTTP/proxmox.qemu.mem[{#QEMU.ID}],5m) / last(/Proxmox VE by HTTP/proxmox.qemu.maxmem[{#QEMU.ID}]) * 100 >{$PVE.VM.MEMORY.PUSE.MAX.WARN:"{#QEMU.ID}"}` |WARNING | |
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})] high memory usage |<p>Memory usage.</p> |`min(/Proxmox VE by HTTP/proxmox.lxc.mem[{#LXC.ID}],5m) / last(/Proxmox VE by HTTP/proxmox.lxc.maxmem[{#LXC.ID}]) * 100 >{$PVE.LXC.MEMORY.PUSE.MAX.WARN:"{#LXC.ID}"}` |WARNING | |
|Proxmox: API service not available |<p>The API service is not available. Check your network and authorization settings.</p> |`last(/Proxmox VE by HTTP/proxmox.api.available) <> 200` |HIGH | |
|Proxmox: Cluster [{#RESOURCE.NAME}] not quorum |<p>Proxmox VE use a quorum-based technique to provide a consistent state among all cluster nodes.</p> |`last(/Proxmox VE by HTTP/proxmox.cluster.quorate[{#RESOURCE.NAME}]) <> 1` |HIGH | |
|Proxmox: Node [{#NODE.NAME}] offline |<p>Node offline.</p> |`last(/Proxmox VE by HTTP/proxmox.node.online[{#NODE.NAME}]) <> 1` |HIGH | |
|Proxmox: Node [{#NODE.NAME}]: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Proxmox VE by HTTP/proxmox.node.uptime[{#NODE.NAME}])<10m` |INFO |<p>Manual close: YES</p> |
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME}]: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Proxmox VE by HTTP/proxmox.qemu.uptime[{#QEMU.ID}])<10m` |INFO |<p>Manual close: YES</p> |
|Proxmox: VM [{#NODE.NAME}/{#QEMU.NAME} ({#QEMU.ID})]: Not running |<p>VM state is not "running".</p> |`last(/Proxmox VE by HTTP/proxmox.qemu.vmstatus[{#QEMU.ID}])<>"running"` |AVERAGE | |
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME}]: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Proxmox VE by HTTP/proxmox.lxc.uptime[{#LXC.ID}])<10m` |INFO |<p>Manual close: YES</p> |
|Proxmox: LXC [{#NODE.NAME}/{#LXC.NAME} ({#LXC.ID})]: Not running |<p>LXC state is not "running".</p> |`last(/Proxmox VE by HTTP/proxmox.lxc.vmstatus[{#LXC.ID}])<>"running"` |AVERAGE | |
|Proxmox: Node [{#NODE.NAME}] high root filesystem space usage |<p>Root filesystem space usage.</p> |`min(/Proxmox VE by HTTP/proxmox.node.rootused[{#NODE.NAME}],5m) / last(/Proxmox VE by HTTP/proxmox.node.roottotal[{#NODE.NAME}]) * 100 >{$PVE.ROOT.PUSE.MAX.WARN:"{#NODE.NAME}"}` |WARNING | |
|Proxmox: Node [{#NODE.NAME}] high root filesystem space usage |<p>This trigger is ignored, if there is no swap configured.</p> |`min(/Proxmox VE by HTTP/proxmox.node.swapused[{#NODE.NAME}],5m) / last(/Proxmox VE by HTTP/proxmox.node.swaptotal[{#NODE.NAME}]) * 100 > {$PVE.SWAP.PUSE.MAX.WARN:"{#NODE.NAME}"} and last(/Proxmox VE by HTTP/proxmox.node.swaptotal[{#NODE.NAME}]) > 0` |WARNING | |
|Proxmox: Storage [{#NODE.NAME}/{#STORAGE.NAME}] high filesystem space usage |<p>Root filesystem space usage.</p> |`min(/Proxmox VE by HTTP/proxmox.node.disk[{#NODE.NAME},{#STORAGE.NAME}],5m) / last(/Proxmox VE by HTTP/proxmox.node.maxdisk[{#NODE.NAME},{#STORAGE.NAME}]) * 100 >{$PVE.STORAGE.PUSE.MAX.WARN:"{#NODE.NAME}/{#STORAGE.NAME}"}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

