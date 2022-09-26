
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
|{$PROC.SUM.NAME.MATCHES} |<p>This macro is used in processes discovery. Can be overridden on the host or linked template level.</p> |`<CHANGE VALUE>` |
|{$PROC.SUM.NAME.NOT_MATCHES} |<p>This macro is used in processes discovery. Can be overridden on the host or linked template level.</p> |`<CHANGE VALUE>` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Processes discovery |<p>Discovery of summary process OS.</p> |DEPENDENT |custom.proc.discovery<p>**Filter**:</p>AND <p>- {#VMEM} NOT_MATCHES_REGEX `-1`</p><p>- {#NAME} MATCHES_REGEX `{$PROC.SUM.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$PROC.SUM.NAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|OS |Process [{#NAME}]: Get data |<p>Summary metrics by process {#NAME}.</p> |DEPENDENT |custom.proc.get[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@["name"]=="{#NAME}")]`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> no data`</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|OS |Process [{#NAME}]: Error |<p>Check raw process {#NAME}.</p> |DEPENDENT |custom.proc.error[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p> |
|OS |Process [{#NAME}]: Summary of resident memory size |<p>Summary resident set size memory used by process {#NAME} in bytes.</p> |DEPENDENT |custom.proc.rss[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.value..rss.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |Process [{#NAME}]: Summary of virtual memory size |<p>Summary of virtual memory used by process {#NAME} in bytes.</p> |DEPENDENT |custom.proc.vmem[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.value..vsize.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |Process [{#NAME}]: Percentage of real memory |<p>Percentage of real memory used by process {#NAME}.</p> |DEPENDENT |custom.proc.pmem[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.value..pmem.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |Process [{#NAME}]: Number of processes |<p>Count process {#NAME}</p> |DEPENDENT |custom.proc.num[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.value..processes.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |Process [{#NAME}]: Number of threads |<p>Number of threads {#NAME}.</p> |DEPENDENT |custom.proc.thread[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.value..threads.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |Process [{#NAME}]: Number of page faults |<p>Number of page faults {#NAME}.</p> |DEPENDENT |custom.proc.page[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.value..page_faults.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |Process [{#NAME}]: Size of locked memory |<p>Size of locked memory {#NAME}.</p> |DEPENDENT |custom.proc.mem.locked[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.value..lck.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |Process [{#NAME}]: Swap space used |<p>Size of swap space used {#NAME}.</p> |DEPENDENT |custom.proc.swap[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.value..swap.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Zabbix raw items |OS: Get process summary |<p>Summary metrics data for all processes.</p> |ZABBIX_PASSIVE |proc.get[,,,summary] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Process [{#NAME}]: down |<p>-</p> |`length(last(/OS processes by Zabbix agent/custom.proc.error[{#NAME}]))>0` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

