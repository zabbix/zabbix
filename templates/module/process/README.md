
# OS processes by Zabbix agent

## Overview

For Zabbix version: 6.2 and higher  
The template to monitor processes by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.



This template was tested on:

- CentOS, version CentOS Linux 8
- Ubuntu, version Ubuntu 22.04.1 LTS

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

Install and setup [Zabbix agent](https://www.zabbix.com/documentation/6.2/manual/installation/install_from_packages).

Custom processes set in macros:

- {$PROC.SUM.NAME.MATCHES}
- {$PROC.SUM.NAME.NOT_MATCHES}


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PROC.SUM.NAME.MATCHES} |<p>This macro is used in summary process discovery. Can be overridden on the host or linked template level.</p> |`<CHANGE VALUE>` |
|{$PROC.SUM.NAME.NOT_MATCHES} |<p>This macro is used in summary process discovery. Can be overridden on the host or linked template level.</p> |`<CHANGE VALUE>` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Summary of processes discovery |<p>Discovery of summary process OS.</p> |DEPENDENT |custom.proc.sum.discovery<p>**Filter**:</p>AND <p>- {#VMEM} NOT_MATCHES_REGEX `-1`</p><p>- {#NAME} MATCHES_REGEX `{$PROC.SUM.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$PROC.SUM.NAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|OS |OS: Get process: {#NAME} |<p>-</p> |DEPENDENT |custom.proc.get[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@["name"]=="{#NAME}")]`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|OS |OS: Summary of resident memory size: {#NAME} |<p>Summary resident set size memory used by process {#NAME} in bytes.</p> |DEPENDENT |custom.proc.rss[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..rss.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |OS: Summary of virtual memory size: {#NAME} |<p>Summary virtual memory used by process {#NAME} in bytes.</p> |DEPENDENT |custom.proc.vmem[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..vsize.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |OS: Percentage of real memory: {#NAME} |<p>Percentage of real memory used by process {#NAME}.</p> |DEPENDENT |custom.proc.pmem[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..pmem.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |OS: Number of processes: {#NAME} |<p>Count process {#NAME}</p> |DEPENDENT |custom.proc.numb[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..processes.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |OS: Number of threads: {#NAME} |<p>Number threads {#NAME}</p> |DEPENDENT |custom.proc.thread[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..threads.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |OS: Number of page faults: {#NAME} |<p>Number page faults {#NAME}</p> |DEPENDENT |custom.proc.page[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..page_faults.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |OS: Size of locked memory: {#NAME} |<p>Size of locked memory {#NAME}</p> |DEPENDENT |custom.proc.lck[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..lck.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |OS: Size of swap space used: {#NAME} |<p>Size of swap space used {#NAME}</p> |DEPENDENT |custom.proc.swap[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$..swap.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Zabbix raw items |OS: Get process summary |<p>Get all summary procces metrics</p> |ZABBIX_PASSIVE |proc.get[,,,summary] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

