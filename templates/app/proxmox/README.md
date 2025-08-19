
# Proxmox VE by HTTP

## Overview

Proxmox VE is a complete open-source platform for enterprise virtualization.
It tightly integrates two virtualization technologies: KVM for virtual machines
and LXC for containers, on a single platform.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Proxmox VE 8.4.0.

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

To use this template, you need to have a Proxmox VE server set up and accessible via HTTP.
Ensure that you have the necessary API access configured on your Proxmox VE server.
Check the [`API documentation`](https://pve.proxmox.com/pve-docs/api-viewer/index.html) for details.

1. Create an API token for the monitoring user. Important note: for security reasons, it is recommended to create a separate user (Datacenter - Permissions).

Please provide the necessary access levels for both the User and the Token:

* /cluster/resources
* /cluster/status
* /access/users
* /nodes
* /nodes/{#NODE.NAME}/apt/update
* /nodes/{#NODE.NAME}/certificates/info
* /nodes/{#NODE.NAME}/disks/list
* /nodes/{#NODE.NAME}/disks/smart?disk={#DISK.NAME}
* /nodes/{#NODE.NAME}/hardware/pci
* /nodes/{#NODE.NAME}/hardware/usb
* /nodes/{#NODE.NAME}/lxc
* /nodes/{#NODE.NAME}/lxc/{#LXC.VMID}/status/current
* /nodes/{#NODE.NAME}/qemu
* /nodes/{#NODE.NAME}/qemu/{#QEMU.VMID}/agent/get-fsinfo
* /nodes/{#NODE.NAME}/qemu/{#QEMU.VMID}/agent/get-osinfo
* /nodes/{#NODE.NAME}/qemu/{#QEMU.VMID}/agent/get-time
* /nodes/{#NODE.NAME}/qemu/{#QEMU.VMID}/agent/get-timezone
* /nodes/{#NODE.NAME}/qemu/{#QEMU.VMID}/agent/info
* /nodes/{#NODE.NAME}/qemu/{#QEMU.VMID}/agent/network-get-interfaces
* /nodes/{#NODE.NAME}/qemu/{#QEMU.VMID}/status/current
* /nodes/{#NODE.NAME}/storage
* /nodes/{#NODE.NAME}/time
* /nodes/{#NODE.NAME}/version

2. Copy the resulting Token ID and Secret into the host macros `{$PVE.TOKEN.ID}` and `{$PVE.TOKEN.SECRET}`.

3. Set the hostname or IP address of the Proxmox API VE host in the `{$PVE.URL.HOST}` macro. You can also change the API port in the `{$PVE.URL.PORT}` macro if necessary.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PVE.URL.HOST}|<p>The hostname or IP address of the Proxmox VE API host.</p>||
|{$PVE.URL.PORT}|<p>The port number of the Proxmox VE API host.</p>|`8006`|
|{$PVE.TOKEN.ID}|<p>API tokens allow stateless access to most parts of the REST API by another system, software or API client.</p>|`USER@REALM!TOKENID`|
|{$PVE.TOKEN.SECRET}|<p>Secret key.</p>||
|{$PVE.PROXY}|<p>Proxy settings for the Proxmox VE API.</p>||
|{$PVE.PARAMS.INTERVAL.CLUSTER}|<p>Interval for cluster data retrieval.</p>|`1m`|
|{$PVE.PARAMS.INTERVAL.USER}|<p>Interval for user data retrieval.</p>|`1h`|
|{$PVE.PARAMS.INTERVAL.NODE}|<p>Interval for node data retrieval.</p>|`1m`|
|{$PVE.FILTER.USER.MATCH}|<p>Filter for user discovery by fullname.</p>|`.*`|
|{$PVE.FILTER.USER.NOTMATCH}|<p>Exclude filter for user discovery by fullname.</p>|`CHANGE_ME_IF_NEEDED`|
|{$PVE.FILTER.NODE.MATCH}|<p>Filter for node discovery by name.</p>|`.*`|
|{$PVE.FILTER.NODE.NOTMATCH}|<p>Exclude filter for node discovery by name.</p>|`CHANGE_ME_IF_NEEDED`|
|{$PVE.FILTER.NODE.STATUS}|<p>Filter for node discovery by status.</p>|`.*`|
|{$PVE.TRIGGER.UPTIME}|<p>Threshold for uptime triggers. This macro support context.</p>|`15m`|
|{$PVE.TRIGGER.CPU.WARNING}|<p>Threshold for CPU utilization warning triggers in percentage. This macro support context.</p>|`90`|
|{$PVE.TRIGGER.MEMORY.WARNING}|<p>Threshold for memory usage warning triggers in percentage. This macro support context.</p>|`90`|
|{$PVE.TRIGGER.DISK.WARNING}|<p>Threshold for disk usage warning triggers in percentage. This macro support context.</p>|`90`|
|{$PVE.TRIGGER.SWAP.WARNING}|<p>Threshold for swap usage warning triggers in percentage. This macro support context.</p>|`90`|
|{$PVE.LLD.ENABLE.CERT}|<p>Enable discovery of node certificates.</p>|`.*`|
|{$PVE.LLD.ENABLE.DISK}|<p>Enable discovery of node disks.</p>|`.*`|
|{$PVE.LLD.ENABLE.STORAGE}|<p>Enable discovery of node storage.</p>|`.*`|
|{$PVE.FILTER.CERT.FILE.MATCH}|<p>Filter for certificate file discovery by name.</p>|`.*`|
|{$PVE.FILTER.CERT.FILE.NOTMATCH}|<p>Exclude filter for certificate file discovery by name.</p>|`CHANGE_ME_IF_NEEDED`|
|{$PVE.PARAMS.INTERVAL.CERT}|<p>Interval for certificate data retrieval.</p>|`1h`|
|{$PVE.FILTER.DISK.MATCH}|<p>Filter for disk discovery by name.</p>|`.*`|
|{$PVE.FILTER.DISK.NOTMATCH}|<p>Exclude filter for disk discovery by name.</p>|`CHANGE_ME_IF_NEEDED`|
|{$PVE.PARAMS.INTERVAL.DISK}|<p>Interval for disk discovery.</p>|`1h`|
|{$PVE.FILTER.QEMULXC.STATUS.MATCH}|<p>Filter for QEMU and LXC discovery by status.</p>|`.*`|
|{$PVE.FILTER.LXC.MATCH}|<p>Filter for LXC discovery by name.</p>|`.*`|
|{$PVE.FILTER.LXC.NOTMATCH}|<p>Exclude filter for LXC discovery by name.</p>|`CHANGE_ME_IF_NEEDED`|
|{$PVE.FILTER.QEMU.MATCH}|<p>Filter for QEMU virtual machine discovery by name.</p>|`.*`|
|{$PVE.FILTER.QEMU.NOTMATCH}|<p>Exclude filter for QEMU virtual machine discovery by name.</p>|`CHANGE_ME_IF_NEEDED`|
|{$PVE.FILTER.QEMU.FS.MOUNT_POINT.MATCH}|<p>Filter for QEMU virtual machine filesystem discovery by mount point.</p>|`.*`|
|{$PVE.FILTER.QEMU.FS.MOUNT_POINT.NOTMATCH}|<p>Exclude filter for QEMU virtual machine filesystem discovery by mount point.</p>|`CHANGE_ME_IF_NEEDED`|
|{$PVE.FILTER.QEMU.FS.TYPE.MATCH}|<p>Filter for QEMU virtual machine filesystem discovery by type.</p>|`.*`|
|{$PVE.FILTER.QEMU.FS.TYPE.NOTMATCH}|<p>Exclude filter for QEMU virtual machine filesystem discovery by type.</p>|`CHANGE_ME_IF_NEEDED`|
|{$PVE.PARAMS.INTERVAL.QEMU.FS}|<p>Interval for QEMU virtual machine filesystem discover.</p>|`1h`|
|{$PVE.FILTER.QEMU.OS.METRIC.MATCH}|<p>Filter for QEMU virtual machine OS metric discovery by name.</p>|`.*`|
|{$PVE.FILTER.QEMU.OS.METRIC.NOTMATCH}|<p>Exclude filter for QEMU virtual machine OS metric discovery by name.</p>|`CHANGE_ME_IF_NEEDED`|
|{$PVE.PARAMS.INTERVAL.QEMU.OS}|<p>Interval for QEMU virtual machine OS metric data retrieval.</p>|`12h`|
|{$PVE.FILTER.QEMU.NET.IFACE.NAME.MATCH}|<p>Filter for QEMU virtual machine network interface discovery by name.</p>|`.*`|
|{$PVE.FILTER.QEMU.NET.IFACE.NAME.NOTMATCH}|<p>Exclude filter for QEMU virtual machine network interface discovery by name.</p>|`lo\|Loopback.*`|
|{$PVE.PARAMS.INTERVAL.QEMU.NETWORK}|<p>Interval for QEMU virtual machine network interface discovery.</p>|`12h`|
|{$PVE.FILTER.STORAGE.NAME.MATCH}|<p>Filter for storage discovery by name.</p>|`.*`|
|{$PVE.FILTER.STORAGE.NAME.NOTMATCH}|<p>Exclude filter for storage discovery by name.</p>|`CHANGE_ME_IF_NEEDED`|
|{$PVE.PARAMS.INTERVAL.STORAGE}|<p>Interval for storage discovery.</p>|`12h`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster: Get user data|<p>Retrieves the list of users from the Proxmox VE API.</p>|HTTP agent|proxmox_ve.get_user_data<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Cluster: Get node data|<p>Retrieves the list of nodes from the Proxmox VE API.</p>|HTTP agent|proxmox_ve.get_node_data<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p><p>⛔️Custom on fail: Set error to: `Error retrieving node data`</p></li></ul>|
|Cluster: Get resources|<p>Retrieves the list of cluster resources from the Proxmox VE API.</p>|Script|proxmox_ve.cluster.resources.get|
|Cluster: Quorum status|<p>Retrieves the cluster quorum status from the Proxmox VE API.</p>|Dependent item|proxmox_ve.cluster.quorum.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cluster.quorate`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Number of cluster nodes|<p>Retrieves the number of nodes in the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.nodes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cluster.nodes`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Number of cluster nodes online|<p>Retrieves the number of online nodes in the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.nodes.online<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cluster.node_online`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Number of cluster nodes offline|<p>Retrieves the number of offline nodes in the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.nodes.offline<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cluster.node_offline`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Number of running virtual machines|<p>Retrieves the number of running virtual machines in the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.vms.running<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cluster.qemu_running`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Number of stopped virtual machines|<p>Retrieves the number of stopped virtual machines in the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.vms.stopped<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cluster.qemu_stopped`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Number of running LXC containers|<p>Retrieves the number of running LXC containers in the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.lxc.running<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cluster.lxc_running`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Number of stopped LXC containers|<p>Retrieves the number of stopped LXC containers in the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.lxc.stopped<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cluster.lxc_stopped`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Number of CPUs|<p>Retrieves the total number of CPUs in the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.cpu.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cluster.cpu_count`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: CPU utilization|<p>Retrieves the CPU utilization percentage for the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.cpu.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cluster.cpu_average`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Memory used|<p>Retrieves the total memory used in the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.memory.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cluster.memory_used`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Memory total|<p>Retrieves the total memory available in the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.memory.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cluster.memory_total`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Memory utilization|<p>Retrieves the memory utilization percentage for the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.memory.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.cluster.memory_utilization`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Storage used|<p>Retrieves the total storage used in the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.storage.used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.storage.used`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Storage total|<p>Retrieves the total storage available in the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.storage.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.storage.total`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Cluster: Storage utilization|<p>Retrieves the storage utilization percentage for the Proxmox VE cluster.</p>|Dependent item|proxmox_ve.cluster.storage.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.storage.utilization`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox VE: Cluster resources update error|<p>An error occurred while retrieving cluster resources from the Proxmox VE API.</p>|`jsonpath(last(/Proxmox VE by HTTP/proxmox_ve.cluster.resources.get), "$.status") <> 0 or jsonpath(last(/Proxmox VE by HTTP/proxmox_ve.cluster.resources.get), "$.message") <> ""`|High||
|Proxmox VE: Cluster quorum status changed|<p>No majority of nodes for decision making.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.cluster.quorum.status) = 0`|Warning|**Manual close**: Yes|

### LLD rule Proxmox shared storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxmox shared storage discovery||Dependent item|proxmox_ve.shared.storage.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.storage.list`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li></ul>|

### Item prototypes for Proxmox shared storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Storage [{#STORAGE.NAME}]: Type|<p>Retrieves the type of shared storage {#STORAGE.NAME}.</p>|Dependent item|proxmox_ve.storage.type[{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `unknown`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage [{#STORAGE.NAME}]: Total|<p>Retrieves the total size of shared storage {#STORAGE.NAME}.</p>|Dependent item|proxmox_ve.storage.total[{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage [{#STORAGE.NAME}]: Used|<p>Retrieves the used size of shared storage {#STORAGE.NAME}.</p>|Dependent item|proxmox_ve.storage.used[{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage [{#STORAGE.NAME}]: Utilization|<p>Retrieves the storage utilization percentage for shared storage {#STORAGE.NAME}.</p>|Calculated|proxmox_ve.storage.utilization[{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Storage [{#STORAGE.NAME}]: Content|<p>Retrieves the content type of shared storage {#STORAGE.NAME}.</p>|Dependent item|proxmox_ve.storage.content[{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `unknown`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Proxmox users discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxmox users discovery||Dependent item|proxmox_ve.users.discovery|

### Item prototypes for Proxmox users discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|User [{#USER.FULLNAME}]: Expire after|<p>Retrieves the expiration time for the user {#USER.FULLNAME}.</p><p>The value is calculated as the difference between the current time and the expiration time.</p>|Dependent item|proxmox_ve.user.expire[{#USER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data[?(@.userid == "{#USER.ID}")].expire.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|User [{#USER.FULLNAME}]: Enabled|<p>Retrieves enabled status for the user {#USER.FIRSTNAME} {#USER.LASTNAME} ({#USER.ID}).</p>|Dependent item|proxmox_ve.user.enabled[{#USER.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data[?(@.userid == "{#USER.ID}")].enable.first()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Trigger prototypes for Proxmox users discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox VE: User [{#USER.FULLNAME}] expired|<p>The user {#USER.FULLNAME} has expired.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.user.expire[{#USER.ID}]) = 0 and last(/Proxmox VE by HTTP/proxmox_ve.user.enabled[{#USER.ID}]) = 1`|Info|**Manual close**: Yes|
|Proxmox VE: User [{#USER.FULLNAME}] enabled status changed|<p>The user {#USER.FULLNAME} enabled status has changed.</p>|`change(/Proxmox VE by HTTP/proxmox_ve.user.enabled[{#USER.ID}]) <> 0`|Info|**Manual close**: Yes|

### LLD rule Proxmox nodes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxmox nodes discovery||Dependent item|proxmox_ve.nodes.discovery|

### Item prototypes for Proxmox nodes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: Status|<p>Retrieves the status of the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.status[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id == "{#NODE.ID}")].status.first()`</p><p>⛔️Custom on fail: Set value to: `unknown`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Uptime|<p>Retrieves the uptime of the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.uptime[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id == "{#NODE.ID}")].uptime.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node [{#NODE.NAME}]: CPU utilization|<p>Retrieves the CPU utilization of the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.cpu[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id == "{#NODE.ID}")].cpu.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `100.0`</p></li></ul>|
|Node [{#NODE.NAME}]: CPU count|<p>Retrieves the maximum CPU count of the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.cpu.max[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id == "{#NODE.ID}")].maxcpu.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Memory used|<p>Retrieves the memory used by the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.mem[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id == "{#NODE.ID}")].mem.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: Memory total|<p>Retrieves the total memory on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.maxmem[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id == "{#NODE.ID}")].maxmem.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Memory utilization|<p>Retrieves the memory utilization percentage for the node {#NODE.NAME}.</p>|Calculated|proxmox_ve.node.mem.utilization[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: Disk used|<p>Retrieves the disk used by the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.disk[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id == "{#NODE.ID}")].disk.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: Disk total|<p>Retrieves the total disk space on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.maxdisk[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@.id == "{#NODE.ID}")].maxdisk.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Disk utilization|<p>Retrieves the disk utilization percentage for the node {#NODE.NAME}.</p>|Calculated|proxmox_ve.node.disk.utilization[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: Available updates||HTTP agent|proxmox_ve.node.updates[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Number of USB devices|<p>Retrieves the number of USB devices on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.hardware.usb[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Number of PCI devices|<p>Retrieves the number of PCI devices on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.hardware.pci[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.length()`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Time info|<p>Retrieves the current time on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.time.info[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Node [{#NODE.NAME}]: Time|<p>Retrieves the current time on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.time[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.time`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Node [{#NODE.NAME}]: Time zone|<p>Retrieves the timezone of the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.timezone[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.timezone`</p><p>⛔️Custom on fail: Set value to: `Unknown`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Time (local)|<p>Retrieves the local time of the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.localtime[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.localtime`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Node [{#NODE.NAME}]: Version|<p>Retrieves the version of the Proxmox VE on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.version[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.version`</p><p>⛔️Custom on fail: Set value to: `Unknown`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: IP address|<p>Retrieves the IP address of the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.ip[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.nodes["{#NODE.NAME}"].ip`</p><p>⛔️Custom on fail: Set value to: `Unknown`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Subscribe level|<p>Retrieves the subscription level of the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.subscribe[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.nodes["{#NODE.NAME}"].level`</p><p>⛔️Custom on fail: Set value to: ``</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Proxmox nodes discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox VE: Node [{#NODE.NAME}] not online|<p>The node {#NODE.NAME} is not online.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.status[{#NODE.ID}]) <> 10`|Warning|**Manual close**: Yes|
|Proxmox VE: Node [{#NODE.NAME}] has been restarted|<p>The node {#NODE.NAME} has been restarted.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.uptime[{#NODE.ID}]) < {$PVE.TRIGGER.UPTIME:"{#NODE.NAME}"}`|Info||
|Proxmox VE: Node [{#NODE.NAME}] CPU utilization high|<p>The CPU utilization of the node {#NODE.NAME} is high.</p>|`avg(/Proxmox VE by HTTP/proxmox_ve.node.cpu[{#NODE.ID}], 5m) > {$PVE.TRIGGER.CPU.WARNING:"{#NODE.NAME}"}`|Warning||
|Proxmox VE: Node [{#NODE.NAME}] memory utilization high|<p>The memory utilization of the node {#NODE.NAME} is high.</p>|`avg(/Proxmox VE by HTTP/proxmox_ve.node.mem.utilization[{#NODE.ID}], 5m) > {$PVE.TRIGGER.MEMORY.WARNING:"{#NODE.NAME}"}`|Warning||
|Proxmox VE: Node [{#NODE.NAME}] disk utilization high|<p>The disk utilization of the node {#NODE.NAME} is high.</p>|`avg(/Proxmox VE by HTTP/proxmox_ve.node.disk.utilization[{#NODE.ID}], 5m) > {$PVE.TRIGGER.DISK.WARNING:"{#NODE.NAME}"}`|Warning||
|Proxmox VE: Node [{#NODE.NAME}] has available updates|<p>The node {#NODE.NAME} has available updates.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.updates[{#NODE.ID}]) > 0`|Info||
|Proxmox VE: Node [{#NODE.NAME}] changed number of USB devices|<p>The number of USB devices on the node {#NODE.NAME} has changed.</p>|`change(/Proxmox VE by HTTP/proxmox_ve.node.hardware.usb[{#NODE.ID}]) <> 0`|Info|**Manual close**: Yes|
|Proxmox VE: Node [{#NODE.NAME}] changed number of PCI devices|<p>The number of PCI devices on the node {#NODE.NAME} has changed.</p>|`change(/Proxmox VE by HTTP/proxmox_ve.node.hardware.pci[{#NODE.ID}]) <> 0`|Info|**Manual close**: Yes|
|Proxmox VE: Node [{#NODE.NAME}] version changed|<p>The version of Proxmox VE on the node {#NODE.NAME} has changed.</p>|`change(/Proxmox VE by HTTP/proxmox_ve.node.version[{#NODE.ID}]) = 1`|Info|**Manual close**: Yes|

### LLD rule Node [{#NODE.NAME}]: Certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: Certificate discovery|<p>Discovers certificates on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.certificates.discovery[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Item prototypes for Node [{#NODE.NAME}]: Certificate discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: Certificate [{#CERT.FILENAME}]: Get info|<p>Retrieves info for the certificate {#CERT.FILENAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.certificates.expire[{#NODE.ID},{#CERT.FILENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.[?(@.filename == "{#CERT.FILENAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Certificate [{#CERT.FILENAME}]: Issuer|<p>Retrieves the issuer for the certificate {#CERT.FILENAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.certificates.issuer[{#NODE.ID},{#CERT.FILENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.issuer`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Certificate [{#CERT.FILENAME}]: Subject|<p>Retrieves the subject for the certificate {#CERT.FILENAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.certificates.subject[{#NODE.ID},{#CERT.FILENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.subject`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Certificate [{#CERT.FILENAME}]: Fingerprint|<p>Retrieves the fingerprint for the certificate {#CERT.FILENAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.certificates.fingerprint[{#NODE.ID},{#CERT.FILENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fingerprint`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Certificate [{#CERT.FILENAME}]: Valid not before|<p>Retrieves the not before date for the certificate {#CERT.FILENAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.certificates.notbefore[{#NODE.ID},{#CERT.FILENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.notbefore`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Certificate [{#CERT.FILENAME}]: Valid not after|<p>Retrieves the not after date for the certificate {#CERT.FILENAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.certificates.notafter[{#NODE.ID},{#CERT.FILENAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.notafter`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Node [{#NODE.NAME}]: Certificate discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox VE: Node [{#NODE.NAME}]: Certificate [{#CERT.FILENAME}]: Not valid|<p>The certificate {#CERT.FILENAME} on the node {#NODE.NAME} expires in less than 7 days.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.certificates.notafter[{#NODE.ID},{#CERT.FILENAME}]) < now() or last(/Proxmox VE by HTTP/proxmox_ve.node.certificates.notbefore[{#NODE.ID},{#CERT.FILENAME}]) > now()`|Average||

### LLD rule Node [{#NODE.NAME}]: Disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: Disks discovery|<p>Discovers disks on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.disks.discovery[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Item prototypes for Node [{#NODE.NAME}]: Disks discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: Disk [{#DISK.NAME}]: Get info|<p>Retrieves info for the disk {#DISK.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.disk.info[{#NODE.ID},{#DISK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.[?(@.devpath == "{#DISK.NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node [{#NODE.NAME}]: Disk [{#DISK.NAME}]: Vendor|<p>Retrieves the vendor for the disk {#DISK.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.disk.vendor[{#NODE.ID},{#DISK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.vendor`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Disk [{#DISK.NAME}]: Model|<p>Retrieves the model for the disk {#DISK.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.disk.model[{#NODE.ID},{#DISK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.model`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Disk [{#DISK.NAME}]: Serial|<p>Retrieves the serial number for the disk {#DISK.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.disk.serial[{#NODE.ID},{#DISK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.serial`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Disk [{#DISK.NAME}]: Type|<p>Retrieves the type for the disk {#DISK.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.disk.type[{#NODE.ID},{#DISK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.type`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Disk [{#DISK.NAME}]: SMART status|<p>Retrieves the SMART status for the disk {#DISK.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.disk.smart[{#NODE.ID},{#DISK.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.health`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Node [{#NODE.NAME}]: Disks discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox VE: Node [{#NODE.NAME}]: Disk [{#DISK.NAME}]: SMART status fail|<p>The SMART status for the disk {#DISK.NAME} on the node {#NODE.NAME} is not healthy.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.disk.smart[{#NODE.ID},{#DISK.NAME}]) <> 1`|High||

### LLD rule Node [{#NODE.NAME}]: Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: Storage discovery|<p>Discovers storage devices on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.storage.discovery[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Item prototypes for Node [{#NODE.NAME}]: Storage discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: Storage [{#STORAGE.NAME}]: Get info|<p>Retrieves information about the storage device "{#STORAGE.NAME}" on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.storage[{#NODE.ID},{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data[?(@.storage=="{#STORAGE.NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node [{#NODE.NAME}]: Storage [{#STORAGE.NAME}]: Type|<p>Retrieves the type of the storage device "{#STORAGE.NAME}" on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.storage.type[{#NODE.ID},{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.type`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Storage [{#STORAGE.NAME}]: Content|<p>Retrieves the content type of the storage device "{#STORAGE.NAME}" on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.storage.content[{#NODE.ID},{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.content`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Storage [{#STORAGE.NAME}]: Used|<p>Retrieves the used space of the storage device "{#STORAGE.NAME}" on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.storage.used[{#NODE.ID},{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.used`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Storage [{#STORAGE.NAME}]: Total|<p>Retrieves the total space of the storage device "{#STORAGE.NAME}" on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.storage.total[{#NODE.ID},{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: Storage [{#STORAGE.NAME}]: Utilization|<p>Retrieves the utilization percentage of the storage device "{#STORAGE.NAME}" on the node {#NODE.NAME}.</p>|Calculated|proxmox_ve.node.storage.utilization[{#NODE.ID},{#STORAGE.NAME}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Node [{#NODE.NAME}]: Storage discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox VE: Node [{#NODE.NAME}]: Storage [{#STORAGE.NAME}]: Usage high|<p>The storage usage of {#STORAGE.NAME} on the node {#NODE.NAME} is high.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.storage.used[{#NODE.ID},{#STORAGE.NAME}]) / last(/Proxmox VE by HTTP/proxmox_ve.node.storage.total[{#NODE.ID},{#STORAGE.NAME}]) * 100 > {$PVE.TRIGGER.DISK.WARNING:"{#STORAGE.NAME}"}`|Warning||
|Proxmox VE: Node [{#NODE.NAME}]: Storage [{#STORAGE.NAME}]: Usage high|<p>The storage usage of {#STORAGE.NAME} on the node {#NODE.NAME} is high.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.storage.utilization[{#NODE.ID},{#STORAGE.NAME}]) > {$PVE.TRIGGER.DISK.WARNING:"{#STORAGE.NAME}"}`|Warning||

### LLD rule Proxmox LXC discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxmox LXC discovery|<p>Discovers LXC containers on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.discovery[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.lxc`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Item prototypes for Proxmox LXC discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Get info|<p>Retrieves information about the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.lxc[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p><p>⛔️Custom on fail: Set value to: `{}`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Worked node|<p>Retrieves the node where the LXC container {#LXC.NAME} is running.</p>|Dependent item|proxmox_ve.node.lxc.node[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.lxc[?(@.vmid == "{#LXC.VMID}")].node.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Status|<p>Retrieves the status of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.status[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Set value to: `unknown`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: CPU utilization|<p>Retrieves the CPU utilization of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.cpu[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `100.0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: CPU count|<p>Retrieves CPU count of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.cpu.count[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpus`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Disk used|<p>Retrieves the Root disk usage of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.disk.usage[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disk`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Disk total|<p>Retrieves the Root disk total of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.disk.maxdisk[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxdisk`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Disk utilization|<p>Retrieves disk utilization of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Calculated|proxmox_ve.node.lxc.disk.utilization[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Disk read speed|<p>Retrieves disk read speed of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.disk.read[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.diskread`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Disk write speed|<p>Retrieves disk write speed of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.disk.write[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.diskwrite`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Memory used|<p>Retrieves the memory usage of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.memory.usage[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Memory total|<p>Retrieves the total memory of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.memory.max[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxmem`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Memory utilization|<p>Retrieves memory utilization of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Calculated|proxmox_ve.node.lxc.memory.utilization[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Swap used|<p>Retrieves the swap usage of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.swap.usage[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.swap`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Swap total|<p>Retrieves the total swap of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.maxswap[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxswap`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Network input speed|<p>Retrieves network input speed of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.network.input[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.netin`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Network output speed|<p>Retrieves network output speed of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.network.output[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.netout`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Uptime|<p>Retrieves the uptime of the LXC container {#LXC.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.lxc.uptime[{#NODE.ID},{#LXC.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Proxmox LXC discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox VE: Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Changed worked node|<p>The LXC container {#LXC.NAME} migrated to another node.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.lxc.node[{#NODE.ID},{#LXC.VMID}], #1) <> last(/Proxmox VE by HTTP/proxmox_ve.node.lxc.node[{#NODE.ID},{#LXC.VMID}], #2)`|Info|**Manual close**: Yes|
|Proxmox VE: Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Not running|<p>The LXC container {#LXC.NAME} on the node {#NODE.NAME} is not running.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.lxc.status[{#NODE.ID},{#LXC.VMID}]) <> 2`|Warning|**Manual close**: Yes|
|Proxmox VE: Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: CPU utilization high|<p>The CPU utilization of the LXC container {#LXC.NAME} on the node {#NODE.NAME} is high.</p>|`avg(/Proxmox VE by HTTP/proxmox_ve.node.lxc.cpu[{#NODE.ID},{#LXC.VMID}], 5m) > {$PVE.TRIGGER.CPU.WARNING:"{#LXC.NAME}"}`|Warning||
|Proxmox VE: Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Disk usage high|<p>The disk usage of the LXC container {#LXC.NAME} on the node {#NODE.NAME} is high.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.lxc.disk.utilization[{#NODE.ID},{#LXC.VMID}]) > {$PVE.TRIGGER.DISK.WARNING:"{#LXC.NAME}"}`|Warning||
|Proxmox VE: Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Memory usage high|<p>The memory usage of the LXC container {#LXC.NAME} on the node {#NODE.NAME} is high.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.lxc.memory.utilization[{#NODE.ID},{#LXC.VMID}]) > {$PVE.TRIGGER.MEMORY.WARNING:"{#LXC.NAME}"}`|Warning||
|Proxmox VE: Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Swap usage high|<p>The swap usage of the LXC container {#LXC.NAME} on the node {#NODE.NAME} is high.</p>|`avg(/Proxmox VE by HTTP/proxmox_ve.node.lxc.swap.usage[{#NODE.ID},{#LXC.VMID}],5m) / last(/Proxmox VE by HTTP/proxmox_ve.node.lxc.maxswap[{#NODE.ID},{#LXC.VMID}]) * 100 > {$PVE.TRIGGER.SWAP.WARNING:"{#LXC.NAME}"}`|Warning||
|Proxmox VE: Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Has been restarted|<p>The LXC container {#LXC.NAME} on the node {#NODE.NAME} has been restarted.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.lxc.uptime[{#NODE.ID},{#LXC.VMID}]) < {$PVE.TRIGGER.UPTIME:"{#LXC.NAME}"}`|Warning|**Depends on**:<br><ul><li>Proxmox VE: Node [{#NODE.NAME}]: LXC [{#LXC.NAME}]: Not running</li></ul>|

### LLD rule Proxmox QEMU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxmox QEMU discovery|<p>Discovers QEMU virtual machines on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.discovery[{#NODE.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.qemu`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Item prototypes for Proxmox QEMU discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Get info|<p>Retrieves information about the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.qemu[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p><p>⛔️Custom on fail: Set value to: `{}`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Worked node|<p>Retrieves the node where the QEMU virtual machine {#QEMU.NAME} is running.</p>|Dependent item|proxmox_ve.node.qemu.node[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.qemu[?(@.vmid == "{#QEMU.VMID}")].node.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Guest agent|<p>Retrieves the QEMU guest agent status for the virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.agent[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.agent`</p><p>⛔️Custom on fail: Set value to: `3`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Guest agent version|<p>Retrieves the QEMU guest agent version for the virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.qemu.agent.version[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.result.version`</p><p>⛔️Custom on fail: Set value to: `Unknown`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: CPU utilization|<p>Retrieves the QEMU CPU utilization for the virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.cpu.usage[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpu`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `100.0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: CPU count|<p>Retrieves CPU count of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.cpu.count[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cpus`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Disk read speed|<p>Retrieves disk read speed of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.disk.read[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.diskread`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Disk write speed|<p>Retrieves disk write speed of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.disk.write[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.diskwrite`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Memory used|<p>Retrieves the memory usage of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.memory.usage[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Memory total|<p>Retrieves the total memory of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.memory.maxmem[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxmem`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Memory utilization|<p>Retrieves memory utilization of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Calculated|proxmox_ve.node.qemu.memory.utilization[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Network input speed|<p>Retrieves network input speed of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.network.input[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.netin`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Network output speed|<p>Retrieves network output speed of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.network.output[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.netout`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Custom multiplier: `8`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Uptime|<p>Retrieves the uptime of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.uptime[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Status|<p>Retrieves the status of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.status[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Set value to: `unknown`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Time|<p>Retrieves the system time of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.qemu.time[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.result`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1e-09`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Time zone|<p>Retrieves the system timezone of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.qemu.timezone[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.result.zone`</p><p>⛔️Custom on fail: Set value to: `Unknown`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Proxmox QEMU discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox VE: Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Changed worked node|<p>The QEMU virtual machine {#QEMU.NAME} migrated to another node.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.qemu.node[{#NODE.ID},{#QEMU.VMID}], #1) <> last(/Proxmox VE by HTTP/proxmox_ve.node.qemu.node[{#NODE.ID},{#QEMU.VMID}], #2)`|Info|**Manual close**: Yes|
|Proxmox VE: Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Agent is not enabled|<p>The QEMU guest agent for the virtual machine {#QEMU.NAME} on the node {#NODE.NAME} is not enabled.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.qemu.agent[{#NODE.ID},{#QEMU.VMID}]) <> 1`|Info||
|Proxmox VE: Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Error getting guest agent data|<p>Error retrieving the QEMU guest agent data for the virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.<br>The guest agent may not be running.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.qemu.agent.version[{#NODE.ID},{#QEMU.VMID}])="Unknown"`|Warning|**Depends on**:<br><ul><li>Proxmox VE: Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Agent is not enabled</li></ul>|
|Proxmox VE: Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: CPU utilization high|<p>The CPU utilization of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME} is high.</p>|`avg(/Proxmox VE by HTTP/proxmox_ve.node.qemu.cpu.usage[{#NODE.ID},{#QEMU.VMID}], 5m) > {$PVE.TRIGGER.CPU.WARNING:"{#QEMU.NAME}"}`|Warning||
|Proxmox VE: Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Memory usage high|<p>The memory usage of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME} is high.</p>|`avg(/Proxmox VE by HTTP/proxmox_ve.node.qemu.memory.utilization[{#NODE.ID},{#QEMU.VMID}],5m) > {$PVE.TRIGGER.MEMORY.WARNING:"{#QEMU.NAME}"}`|Warning||
|Proxmox VE: Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Has been restarted|<p>The QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME} has been restarted.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.qemu.uptime[{#NODE.ID},{#QEMU.VMID}]) < {$PVE.TRIGGER.UPTIME:"{#QEMU.NAME}"}`|Warning|**Depends on**:<br><ul><li>Proxmox VE: Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Not running</li></ul>|
|Proxmox VE: Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Not running|<p>The QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME} is not running.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.qemu.status[{#NODE.ID},{#QEMU.VMID}]) <> 2`|Warning||

### LLD rule Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Filesystem discovery|<p>Discovers filesystems of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.qemu.filesystem.discovery[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.result`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Filesystem discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.FS.MOUNTPOINT}]: Filesystem get info|<p>Retrieves information about the filesystem mounted at {#QEMU.FS.MOUNTPOINT} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.qemu.filesystem[{#NODE.ID},{#QEMU.VMID},{#QEMU.FS.MOUNTPOINT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.FS.MOUNTPOINT}]: Filesystem used|<p>Retrieves the used space of the filesystem mounted at {#QEMU.FS.MOUNTPOINT} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.filesystem.usage[{#NODE.ID},{#QEMU.VMID},{#QEMU.FS.MOUNTPOINT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["used-bytes"]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.FS.MOUNTPOINT}]: Filesystem total|<p>Retrieves the total space of the filesystem mounted at {#QEMU.FS.MOUNTPOINT} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.filesystem.total[{#NODE.ID},{#QEMU.VMID},{#QEMU.FS.MOUNTPOINT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["total-bytes"]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.FS.MOUNTPOINT}]: Filesystem utilization|<p>Retrieves filesystem utilization of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Calculated|proxmox_ve.node.qemu.filesystem.utilization[{#NODE.ID},{#QEMU.VMID},{#QEMU.FS.MOUNTPOINT}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.FS.MOUNTPOINT}]: Filesystem total (privileged)|<p>Retrieves the total space of the privileged filesystem mounted at {#QEMU.FS.MOUNTPOINT} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.filesystem.total.privileged[{#NODE.ID},{#QEMU.VMID},{#QEMU.FS.MOUNTPOINT}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["total-bytes-privileged"]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Trigger prototypes for Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Filesystem discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Proxmox VE: Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.FS.MOUNTPOINT}]: Filesystem used high|<p>The filesystem usage of {#QEMU.FS.MOUNTPOINT} on the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME} is high.</p>|`last(/Proxmox VE by HTTP/proxmox_ve.node.qemu.filesystem.utilization[{#NODE.ID},{#QEMU.VMID},{#QEMU.FS.MOUNTPOINT}]) > {$PVE.TRIGGER.DISK.WARNING:"{#QEMU.NAME}"}`|Warning||

### LLD rule Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: OS info discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: OS info discovery|<p>Discovers operating system information of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.qemu.os.discovery[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.result`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Item prototypes for Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: OS info discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: OS [{#QEMU.OS.METRIC}]|<p>Retrieves the operating system information of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.qemu.os.info[{#NODE.ID},{#QEMU.VMID},{#QEMU.OS.METRIC}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.result["{#QEMU.OS.METRIC}"]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### LLD rule Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Network interfaces discovery|<p>Discovers network interfaces of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.qemu.network.discovery[{#NODE.ID},{#QEMU.VMID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.result`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li></ul>|

### Item prototypes for Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}]: Network interfaces discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: Network interface info|<p>Retrieves information about the network interface {#QEMU.NETIF.NAME} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.qemu.network[{#NODE.ID},{#QEMU.VMID},{#QEMU.NETIF.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.result[?(@.name=="{#QEMU.NETIF.NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: Hardware address|<p>Retrieves the hardware address of the network interface {#QEMU.NETIF.NAME} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.network.mac[{#NODE.ID},{#QEMU.VMID},{#QEMU.NETIF.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["hardware-address"]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: Network input errors|<p>Retrieves the number of input errors on the network interface {#QEMU.NETIF.NAME} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.network.input.errors[{#NODE.ID},{#QEMU.VMID},{#QEMU.NETIF.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["statistics"]["rx-errs"]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: Network output errors|<p>Retrieves the number of output errors on the network interface {#QEMU.NETIF.NAME} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.network.output.errors[{#NODE.ID},{#QEMU.VMID},{#QEMU.NETIF.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["statistics"]["tx-errs"]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: Network input dropped|<p>Retrieves the number of input packets dropped on the network interface {#QEMU.NETIF.NAME} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.network.input.dropped[{#NODE.ID},{#QEMU.VMID},{#QEMU.NETIF.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["statistics"]["rx-dropped"]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: Network output dropped|<p>Retrieves the number of output packets dropped on the network interface {#QEMU.NETIF.NAME} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.network.output.dropped[{#NODE.ID},{#QEMU.VMID},{#QEMU.NETIF.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["statistics"]["tx-dropped"]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: Network input speed (in bps)|<p>Retrieves the input speed of the network interface {#QEMU.NETIF.NAME} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.network.input.speed.bits[{#NODE.ID},{#QEMU.VMID},{#QEMU.NETIF.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["statistics"]["rx-bytes"]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `8`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: Network output speed (in bps)|<p>Retrieves the output speed of the network interface {#QEMU.NETIF.NAME} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.network.output.speed.bits[{#NODE.ID},{#QEMU.VMID},{#QEMU.NETIF.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["statistics"]["tx-bytes"]`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `8`</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: Network input speed (in packets/s)|<p>Retrieves the input speed in packets of the network interface {#QEMU.NETIF.NAME} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.network.input.speed.packets[{#NODE.ID},{#QEMU.VMID},{#QEMU.NETIF.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["statistics"]["rx-packets"]`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: Network output speed (in packets/s)|<p>Retrieves the output speed in packets of the network interface {#QEMU.NETIF.NAME} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|Dependent item|proxmox_ve.node.qemu.network.output.speed.packets[{#NODE.ID},{#QEMU.VMID},{#QEMU.NETIF.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$["statistics"]["tx-packets"]`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: IP addresses discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: IP addresses discovery|<p>Discovers network interfaces of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.qemu.network.ip.discovery[{#NODE.ID},{#QEMU.VMID},{#QEMU.NETIF.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Item prototypes for Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: IP addresses discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node [{#NODE.NAME}]: QEMU [{#QEMU.NAME}][{#QEMU.NETIF.NAME}]: IP address #{#QEMU.NETIF.IP.ID}|<p>Retrieves information about the IP address {#QEMU.NETIF.IP.ADDRESS} of the network interface {#QEMU.NETIF.NAME} of the QEMU virtual machine {#QEMU.NAME} on the node {#NODE.NAME}.</p>|HTTP agent|proxmox_ve.node.qemu.network.ip[{#NODE.ID},{#QEMU.VMID},{#QEMU.NETIF.NAME},{#QEMU.NETIF.IP.ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>JSON Path: `$.[?(@.id=="{#QEMU.NETIF.IP.ID}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

