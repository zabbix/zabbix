
# OS processes by Zabbix agent

## Overview

For Zabbix version: 6.2 and higher.
This template is designed to monitor processes by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.
For example, by specifying "zabbix" as macro value, you can monitor all zabbix processes.



This template was tested on:

- CentOS, version CentOS Linux 8;
- Ubuntu, version Ubuntu 22.04.1 LTS.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

Install and setup [Zabbix agent](https://www.zabbix.com/documentation/6.2/manual/installation/install_from_packages).

Custom processes set in macros:

- {$PROC.NAME.MATCHES}
- {$PROC.NAME.NOT_MATCHES}


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$PROC.NAME.MATCHES} |<p>This macro is used in the discovery of processes. It can be overridden on a host-level or on a linked template-level.</p> |`<CHANGE VALUE>` |
|{$PROC.NAME.NOT_MATCHES} |<p>This macro is used in the discovery of processes. It can be overridden on a host-level or on a linked template-level.</p> |`<CHANGE VALUE>` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Processes discovery |<p>Discovery of OS summary processes.</p> |DEPENDENT |custom.proc.discovery<p>**Filter**:</p>AND <p>- {#VMEM} NOT_MATCHES_REGEX `-1`</p><p>- {#NAME} MATCHES_REGEX `{$PROC.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$PROC.NAME.NOT_MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|OS |Process [{#NAME}]: Get data |<p>Summary metrics collected during the process {#NAME}.</p> |DEPENDENT |custom.proc.get[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@["name"]=="{#NAME}")].first()`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> Failed to retrieve process {#NAME} data`</p> |
|OS |Process [{#NAME}]: Memory usage (rss) |<p>The summary of Resident Set Size (RSS) memory used by the process {#NAME} in bytes.</p> |DEPENDENT |custom.proc.rss[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.rss`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |Process [{#NAME}]: Memory usage (vsize) |<p>The summary of virtual memory used by process {#NAME} in bytes.</p> |DEPENDENT |custom.proc.vmem[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.vsize`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |Process [{#NAME}]: Memory usage, % |<p>The percentage of real memory used by the process {#NAME}.</p> |DEPENDENT |custom.proc.pmem[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.pmem`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |Process [{#NAME}]: Number of running processes |<p>The number of running processes {#NAME}.</p> |DEPENDENT |custom.proc.num[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.processes`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|OS |Process [{#NAME}]: Number of threads |<p>The number of threads {#NAME}.</p> |DEPENDENT |custom.proc.thread[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.threads`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |Process [{#NAME}]: Number of page faults |<p>The number of page faults {#NAME}.</p> |DEPENDENT |custom.proc.page[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.page_faults`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |Process [{#NAME}]: Size of locked memory |<p>The size of locked memory {#NAME}.</p> |DEPENDENT |custom.proc.mem.locked[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.lck`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|OS |Process [{#NAME}]: Swap space used |<p>The swap space used by {#NAME}.</p> |DEPENDENT |custom.proc.swap[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.swap`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Zabbix raw items |OS: Get process summary |<p>The summary of data metrics for all processes.</p> |ZABBIX_PASSIVE |proc.get[,,,summary] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Process [{#NAME}]: is not running |<p>-</p> |`last(/OS processes by Zabbix agent/custom.proc.num[{#NAME}])=0` |HIGH |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

