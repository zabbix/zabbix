
# Template App Squid SNMP

## Overview

For Zabbix version: 5.0  

This template was tested on:

- Squid, version 3.5.12

## Setup

### Setup Squid
Enable SNMP support following [official documentation](https://wiki.squid-cache.org/Features/Snmp).
Example of required parameters in squid.conf: 
```
snmp_port 3401
acl zbxaclname snmp_community public
snmp_access allow zbxaclname zabbix_server_ip
```

### Setup Zabbix
1\. [Import](https://www.zabbix.com/documentation/current/manual/xml_export_import/templates) the template [template_app_squid_snmp.xml](template_app_squid_snmp.xml) into Zabbix.

2\. Set values for {$SQUID.SNMP.COMMUNITY}, {$SQUID.SNMP.PORT} and {$SQUID.HTTP.PORT} as configured in squid.conf.

3\. [Link](https://www.zabbix.com/documentation/current/manual/config/templates/linking) the imported template to a host with Squid.

4\. Add SNMPv2 interface to Squid host. Set **Port** as {$SQUID.SNMP.PORT} and **SNMP community** as {$SQUID.SNMP.COMMUNITY}.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SQUID.HTTP.PORT} |<p>http_port configured in squid.conf (Default: 3128)</p> |`3128` |
|{$SQUID.SNMP.COMMUNITY} |<p>SNMP community alowed by ACL in squid.conf</p> |`public` |
|{$SQUID.SNMP.PORT} |<p>snmp_port configured in squid.conf (Default: 3401)</p> |`3401` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Squid |Squid: {$SQUID.HTTP.PORT} port ping |<p>-</p> |SIMPLE |net.tcp.service[tcp,,{$SQUID.HTTP.PORT}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Squid |Squid: Uptime |<p>The Uptime of the cache in timeticks (in hundredths of a second) with preprocessing</p> |SNMP |squid.snmp[cacheUptime]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Squid |Squid: Version |<p>Cache Software Version</p> |SNMP |squid.snmp[cacheVersionId]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Squid |Squid: CPU usage |<p>The percentage use of the CPU</p> |SNMP |squid.cacheCpuUsage[cacheCpuUsage] |
|Squid |Squid: Memory maximum resident size |<p>Maximum Resident Size in KB</p> |SNMP |squid.cacheMaxResSize[cacheMaxResSize]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: Memory maximum cache size |<p>The value of the cache_mem parameter in MB</p> |SNMP |squid.cacheMemMaxSize[cacheMemMaxSize]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Squid |Squid: Memory cache usage |<p>Total memory accounted in KB</p> |SNMP |squid.cacheMemUsage[cacheMemUsage]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: Cache swap low water mark |<p>Cache Swap Low Water Mark</p> |SNMP |squid.snmp[cacheSwapLowWM] |
|Squid |Squid: Cache swap high water mark |<p>Cache Swap High Water Mark</p> |SNMP |squid.snmp[cacheSwapHighWM] |
|Squid |Squid: Cache swap directory size |<p>The total of the cache_dir space allocated in MB</p> |SNMP |squid.snmp[cacheSwapMaxSize]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Squid |Squid: Cache swap current size |<p>Storage Swap Size in MB</p> |SNMP |squid.snmp[cacheCurrentSwapSize]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Squid |Squid: File descriptor count - current used |<p>Number of file descriptors in use</p> |SNMP |squid.cacheCurrentFileDescrCnt[cacheCurrentFileDescrCnt] |
|Squid |Squid: File descriptor count - current maximum |<p>Highest file descriptors in use</p> |SNMP |squid.snmp[cacheCurrentFileDescrMax] |
|Squid |Squid: File descriptor count - current reserved |<p>Reserved number of file descriptors</p> |SNMP |squid.cacheCurrentResFileDescrCnt[cacheCurrentResFileDescrCnt] |
|Squid |Squid: File descriptor count - current available |<p>Available number of file descriptors</p> |SNMP |squid.cacheCurrentUnusedFDescrCnt[cacheCurrentUnusedFDescrCnt] |
|Squid |Squid: Byte hit ratio per 1 minute |<p>Byte Hit Ratios</p> |SNMP |squid.snmp[cacheRequestByteRatio.1] |
|Squid |Squid: Byte hit ratio per 5 minute |<p>Byte Hit Ratios</p> |SNMP |squid.snmp[cacheRequestByteRatio.5] |
|Squid |Squid: Byte hit ratio per 1 hour |<p>Byte Hit Ratios</p> |SNMP |squid.snmp[cacheRequestByteRatio.60] |
|Squid |Squid: Request hit ratio per 1 minute |<p>Byte Hit Ratios</p> |SNMP |squid.snmp[cacheRequestHitRatio.1] |
|Squid |Squid: Request hit ratio per 5 minute |<p>Byte Hit Ratios</p> |SNMP |squid.snmp[cacheRequestHitRatio.5] |
|Squid |Squid: Request hit ratio per 1 hour |<p>Byte Hit Ratios</p> |SNMP |squid.snmp[cacheRequestHitRatio.60] |
|Squid |Squid: Sys page faults count |<p>Page faults with physical I/O</p> |SNMP |squid.syspage.faults.count[cacheSysPageFaults]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Squid |Squid: Sys page faults per second |<p>Page faults with physical I/O per second</p> |DEPENDENT |squid.snmp[cacheSysPageFaultsRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: HTTP requests received count |<p>Number of HTTP requests received</p> |SNMP |squid.http.received.count[cacheProtoClientHttpRequests]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Squid |Squid: HTTP requests received per second |<p>Number of HTTP requests received per second</p> |DEPENDENT |squid.snmp[cacheProtoClientHttpRequestsRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: HTTP traffic received from clients |<p>Number of HTTP KB's received from clients</p> |SNMP |squid.http.in[cacheHttpInKb]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: HTTP requests received from clients per second |<p>Number of HTTP requests received per second</p> |DEPENDENT |squid.snmp[cacheHttpInKbRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: HTTP traffic sent to clients |<p>Number of HTTP KB's sent to clients</p> |SNMP |squid.http.out[cacheHttpOutKb]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: HTTP requests sent to clients per second |<p>Number of HTTP requests sent per second</p> |DEPENDENT |squid.snmp[cacheHttpOutKbRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: HTTP Hits sent to clients from cache |<p>Number of HTTP Hits sent to clients from cache</p> |SNMP |squid.http.cache.hits[cacheHttpHits] |
|Squid |Squid: HTTP Hits sent to clients from cache per second |<p>Number of HTTP Hits sent to clients from cache per second</p> |DEPENDENT |squid.snmp[cacheHttpHitsRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: HTTP Errors sent to clients |<p>Number of HTTP Errors sent to clients</p> |SNMP |squid.http.cache.errors[cacheHttpErrors] |
|Squid |Squid: HTTP Errors sent to clients per second |<p>Number of HTTP Errors sent to clients per second</p> |DEPENDENT |squid.snmp[cacheHttpErrorsRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: ICP messages sent |<p>Number of ICP messages sent</p> |SNMP |squid.icp.sent[cacheIcpPktsSent] |
|Squid |Squid: ICP messages sent per second |<p>Number of ICP messages sent per second</p> |DEPENDENT |squid.snmp[cacheIcpPktsSentRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: ICP messages received |<p>Number of ICP messages received</p> |SNMP |squid.icp.received[cacheIcpPktsRecv] |
|Squid |Squid: ICP messages received per second |<p>Number of ICP messages received per second</p> |DEPENDENT |squid.snmp[cacheIcpPktsRecvRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: ICP traffic transmitted |<p>Number of ICP KB's transmitted</p> |SNMP |squid.icp.out[cacheIcpKbSent]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: ICP traffic transmitted per second |<p>Number of ICP KB's transmitted per second</p> |DEPENDENT |squid.snmp[cacheIcpKbSentRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: ICP traffic received |<p>Number of ICP KB's received</p> |SNMP |squid.icp.in[cacheIcpKbRecv]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: ICP traffic received per second |<p>Number of ICP KB's received per second</p> |DEPENDENT |squid.snmp[cacheIcpKbRecvRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Squid: Port {$IIS.PORT} is down |<p>-</p> |`{TEMPLATE_NAME:net.tcp.service[tcp,,{$SQUID.HTTP.PORT}].last()}=0` |AVERAGE |<p>Manual close: YES</p> |
|Squid: Squid has been restarted (uptime < 10m) |<p>Uptime is less than 10 minutes</p> |`{TEMPLATE_NAME:squid.snmp[cacheUptime].last()}<10m` |INFO |<p>Manual close: YES</p> |
|Squid: Squid version has been changed |<p>Squid version has changed. Ack to close.</p> |`{TEMPLATE_NAME:squid.snmp[cacheVersionId].diff()}=1 and {TEMPLATE_NAME:squid.snmp[cacheVersionId].strlen()}>0` |INFO |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide a feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/409339-discussion-thread-for-official-zabbix-template-squid).

