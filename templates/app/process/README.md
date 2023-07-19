
# OS processes by Zabbix agent

## Overview

This template is designed to monitor processes by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.
For example, by specifying "zabbix" as macro value, you can monitor all zabbix processes.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- CentOS Linux 8
- Ubuntu 22.04.1 LTS

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Install and setup [Zabbix agent](https://www.zabbix.com/documentation/7.0/manual/installation/install_from_packages).

Custom processes set in macros:

- {$PROC.NAME.MATCHES}
- {$PROC.NAME.NOT_MATCHES}

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PROC.NAME.MATCHES}|<p>This macro is used in the discovery of processes. It can be overridden on a host-level or on a linked template-level.</p>|`<CHANGE VALUE>`|
|{$PROC.NAME.NOT_MATCHES}|<p>This macro is used in the discovery of processes. It can be overridden on a host-level or on a linked template-level.</p>|`<CHANGE VALUE>`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|OS: Get process summary|<p>The summary of data metrics for all processes.</p>|Zabbix agent|proc.get[,,,summary]|

### LLD rule Processes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Processes discovery|<p>Discovery of OS summary processes.</p>|Dependent item|custom.proc.discovery|

### Item prototypes for Processes discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Process [{#NAME}]: Get data|<p>Summary metrics collected during the process {#NAME}.</p>|Dependent item|custom.proc.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.[?(@["name"]=="{#NAME}")].first()`</p><p>⛔️Custom on fail: Set value to: `Failed to retrieve process {#NAME} data`</p></li></ul>|
|Process [{#NAME}]: Memory usage (rss)|<p>The summary of Resident Set Size (RSS) memory used by the process {#NAME} in bytes.</p>|Dependent item|custom.proc.rss[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rss`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Process [{#NAME}]: Memory usage (vsize)|<p>The summary of virtual memory used by process {#NAME} in bytes.</p>|Dependent item|custom.proc.vmem[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.vsize`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Process [{#NAME}]: Memory usage, %|<p>The percentage of real memory used by the process {#NAME}.</p>|Dependent item|custom.proc.pmem[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pmem`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Process [{#NAME}]: Number of running processes|<p>The number of running processes {#NAME}.</p>|Dependent item|custom.proc.num[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.processes`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Process [{#NAME}]: Number of threads|<p>The number of threads {#NAME}.</p>|Dependent item|custom.proc.thread[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.threads`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Process [{#NAME}]: Number of page faults|<p>The number of page faults {#NAME}.</p>|Dependent item|custom.proc.page[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.page_faults`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Process [{#NAME}]: Size of locked memory|<p>The size of locked memory {#NAME}.</p>|Dependent item|custom.proc.mem.locked[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lck`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Process [{#NAME}]: Swap space used|<p>The swap space used by {#NAME}.</p>|Dependent item|custom.proc.swap[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.swap`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Processes discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Process [{#NAME}]: Process is not running||`last(/OS processes by Zabbix agent/custom.proc.num[{#NAME}])=0`|High|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

