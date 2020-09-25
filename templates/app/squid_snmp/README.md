
# Template App Squid SNMP

## Overview

For Zabbix version: 5.0  

This template was tested on:

- Squid, version 3.5.12

## Setup

### Setup Squid
Enable SNMP support following [official documentation](https://wiki.squid-cache.org/Features/Snmp).
Required parameters in squid.conf: 
```
snmp_port <port_number>
acl <zbx_acl_name> snmp_community <community_name>
snmp_access allow <zbx_acl_name> <zabbix_server_ip>
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
|{$SQUID.FILE.DESC.WARN.MIN} |<p>The threshold for minimum number of avaliable file descriptors</p> |`100` |
|{$SQUID.HTTP.PORT} |<p>http_port configured in squid.conf (Default: 3128)</p> |`3128` |
|{$SQUID.PAGE.FAULT.WARN} |<p>The threshold for sys page faults rate in percent of recieved HTTP requests</p> |`90` |
|{$SQUID.SNMP.COMMUNITY} |<p>SNMP community alowed by ACL in squid.conf</p> |`public` |
|{$SQUID.SNMP.PORT} |<p>snmp_port configured in squid.conf (Default: 3401)</p> |`3401` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Squid |Squid: {$SQUID.HTTP.PORT} port ping |<p>-</p> |SIMPLE |net.tcp.service[tcp,,{$SQUID.HTTP.PORT}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Squid |Squid: Uptime |<p>The Uptime of the cache in timeticks (in hundredths of a second) with preprocessing</p> |SNMP |squid[cacheUptime]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Squid |Squid: Version |<p>Cache Software Version</p> |SNMP |squid[cacheVersionId]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Squid |Squid: CPU usage |<p>The percentage use of the CPU</p> |SNMP |squid[cacheCpuUsage] |
|Squid |Squid: Memory maximum resident size |<p>Maximum Resident Size in KB</p> |SNMP |squid[cacheMaxResSize]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: Memory maximum cache size |<p>The value of the cache_mem parameter in MB</p> |SNMP |squid[cacheMemMaxSize]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Squid |Squid: Memory cache usage |<p>Total memory accounted in KB</p> |SNMP |squid[cacheMemUsage]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: Cache swap low water mark |<p>Cache Swap Low Water Mark</p> |SNMP |squid[cacheSwapLowWM] |
|Squid |Squid: Cache swap high water mark |<p>Cache Swap High Water Mark</p> |SNMP |squid[cacheSwapHighWM] |
|Squid |Squid: Cache swap directory size |<p>The total of the cache_dir space allocated in MB</p> |SNMP |squid[cacheSwapMaxSize]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Squid |Squid: Cache swap current size |<p>Storage Swap Size in MB</p> |SNMP |squid[cacheCurrentSwapSize]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Squid |Squid: File descriptor count - current used |<p>Number of file descriptors in use</p> |SNMP |squid[cacheCurrentFileDescrCnt] |
|Squid |Squid: File descriptor count - current maximum |<p>Highest file descriptors in use</p> |SNMP |squid[cacheCurrentFileDescrMax] |
|Squid |Squid: File descriptor count - current reserved |<p>Reserved number of file descriptors</p> |SNMP |squid[cacheCurrentResFileDescrCnt] |
|Squid |Squid: File descriptor count - current available |<p>Available number of file descriptors</p> |SNMP |squid[cacheCurrentUnusedFDescrCnt] |
|Squid |Squid: Byte hit ratio per 1 minute |<p>Byte Hit Ratios</p> |SNMP |squid[cacheRequestByteRatio.1] |
|Squid |Squid: Byte hit ratio per 5 minute |<p>Byte Hit Ratios</p> |SNMP |squid[cacheRequestByteRatio.5] |
|Squid |Squid: Byte hit ratio per 1 hour |<p>Byte Hit Ratios</p> |SNMP |squid[cacheRequestByteRatio.60] |
|Squid |Squid: Request hit ratio per 1 minute |<p>Byte Hit Ratios</p> |SNMP |squid[cacheRequestHitRatio.1] |
|Squid |Squid: Request hit ratio per 5 minute |<p>Byte Hit Ratios</p> |SNMP |squid[cacheRequestHitRatio.5] |
|Squid |Squid: Request hit ratio per 1 hour |<p>Byte Hit Ratios</p> |SNMP |squid[cacheRequestHitRatio.60] |
|Squid |Squid: Sys page faults count |<p>Page faults with physical I/O</p> |SNMP |squid[cacheSysPageFaults] |
|Squid |Squid: Sys page faults per second |<p>Page faults with physical I/O per second</p> |DEPENDENT |squid[cacheSysPageFaultsRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: HTTP requests received count |<p>Number of HTTP requests received</p> |SNMP |squid[cacheProtoClientHttpRequests] |
|Squid |Squid: HTTP requests received per second |<p>Number of HTTP requests received per second</p> |DEPENDENT |squid[cacheProtoClientHttpRequestsRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: HTTP traffic received |<p>Number of HTTP KB's received from clients</p> |SNMP |squid[cacheHttpInKb]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: HTTP traffic received per second |<p>Number of HTTP KB's received per second</p> |DEPENDENT |squid[cacheHttpInKbRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: HTTP traffic sent |<p>Number of HTTP KB's sent to clients</p> |SNMP |squid[cacheHttpOutKb]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: HTTP requests sent per second |<p>Number of HTTP requests sent per second</p> |DEPENDENT |squid[cacheHttpOutKbRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: HTTP Hits sent from cache |<p>Number of HTTP Hits sent to clients from cache</p> |SNMP |squid[cacheHttpHits] |
|Squid |Squid: HTTP Hits sent from cache per second |<p>Number of HTTP Hits sent to clients from cache per second</p> |DEPENDENT |squid[cacheHttpHitsRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: HTTP Errors sent |<p>Number of HTTP Errors sent to clients</p> |SNMP |squid[cacheHttpErrors] |
|Squid |Squid: HTTP Errors sent per second |<p>Number of HTTP Errors sent to clients per second</p> |DEPENDENT |squid[cacheHttpErrorsRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: ICP messages sent |<p>Number of ICP messages sent</p> |SNMP |squid[cacheIcpPktsSent] |
|Squid |Squid: ICP messages sent per second |<p>Number of ICP messages sent per second</p> |DEPENDENT |squid[cacheIcpPktsSentRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: ICP messages received |<p>Number of ICP messages received</p> |SNMP |squid[cacheIcpPktsRecv] |
|Squid |Squid: ICP messages received per second |<p>Number of ICP messages received per second</p> |DEPENDENT |squid[cacheIcpPktsRecvRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: ICP traffic transmitted |<p>Number of ICP KB's transmitted</p> |SNMP |squid[cacheIcpKbSent]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: ICP traffic transmitted per second |<p>Number of ICP KB's transmitted per second</p> |DEPENDENT |squid[cacheIcpKbSentRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: ICP traffic received |<p>Number of ICP KB's received</p> |SNMP |squid[cacheIcpKbRecv]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: ICP traffic received per second |<p>Number of ICP KB's received per second</p> |DEPENDENT |squid[cacheIcpKbRecvRate]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|Squid |Squid: DNS server requests |<p>Number of external dns server requests</p> |SNMP |squid[cacheDnsRequests] |
|Squid |Squid: DNS server replies |<p>Number of external dns server replies</p> |SNMP |squid[cacheDnsReplies] |
|Squid |Squid: FQDN cache requests |<p>Number of FQDN Cache requests</p> |SNMP |squid[cacheFqdnRequests] |
|Squid |Squid: FQDN cache hits |<p>Number of FQDN Cache hits</p> |SNMP |squid[cacheFqdnHits] |
|Squid |Squid: FQDN cache misses |<p>Number of FQDN Cache misses</p> |SNMP |squid[cacheFqdnMisses] |
|Squid |Squid: IP cache requests |<p>Number of IP Cache requests</p> |SNMP |squid[cacheIpRequests] |
|Squid |Squid: IP cache hits |<p>Number of IP Cache hits</p> |SNMP |squid[cacheIpHits] |
|Squid |Squid: Ip cache misses |<p>Number of IP Cache misses</p> |SNMP |squid[cacheIpMisses] |
|Squid |Squid: Objects count |<p>Number of objects stored by the cache</p> |SNMP |squid[cacheNumObjCount] |
|Squid |Squid: Objects LRU expiration age |<p>Storage LRU Expiration Age</p> |SNMP |squid[cacheCurrentLRUExpiration]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Squid |Squid: Objects unlinkd requests |<p>Requests given to unlinkd</p> |SNMP |squid[cacheCurrentUnlinkRequests] |
|Squid |Squid: HTTP all service time per 5 minute |<p>HTTP all service time per 5 minute</p> |SNMP |squid[cacheHttpAllSvcTime.5]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: HTTP all service time per hour |<p>HTTP all service time per hour</p> |SNMP |squid[cacheHttpAllSvcTime.60]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: HTTP miss service time per 5 minute |<p>HTTP miss service time per 5 minute</p> |SNMP |squid[cacheHttpMissSvcTime.5]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: HTTP miss service time per hour |<p>HTTP miss service time per hour</p> |SNMP |squid[cacheHttpMissSvcTime.60]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: HTTP miss service time per 5 minute |<p>HTTP hit service time per 5 minute</p> |SNMP |squid[cacheHttpHitSvcTime.5]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: HTTP hit service time per hour |<p>HTTP hit service time per hour</p> |SNMP |squid[cacheHttpHitSvcTime.60]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: ICP query service time per 5 minute |<p>ICP query service time per 5 minute</p> |SNMP |squid[cacheIcpQuerySvcTime.5]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: ICP query service time per hour |<p>ICP query service time per hour</p> |SNMP |squid[cacheIcpQuerySvcTime.60]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: ICP reply service time per 5 minute |<p>ICP reply service time per 5 minute</p> |SNMP |squid[cacheIcpReplySvcTime.5]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: ICP reply service time per hour |<p>ICP reply service time per hour</p> |SNMP |squid[cacheIcpReplySvcTime.60]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: DNS service time per 5 minute |<p>DNS service time per 5 minute</p> |SNMP |squid[cacheDnsSvcTime.5]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: DNS service time per hour |<p>DNS service time per hour</p> |SNMP |squid[cacheDnsSvcTime.60]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Squid: Port {$SQUID.HTTP.PORT} is down |<p>-</p> |`{TEMPLATE_NAME:net.tcp.service[tcp,,{$SQUID.HTTP.PORT}].last()}=0` |AVERAGE |<p>Manual close: YES</p> |
|Squid: Squid has been restarted (uptime < 10m) |<p>Uptime is less than 10 minutes</p> |`{TEMPLATE_NAME:squid[cacheUptime].last()}<10m` |INFO |<p>Manual close: YES</p> |
|Squid: Squid version has been changed |<p>Squid version has changed. Ack to close.</p> |`{TEMPLATE_NAME:squid[cacheVersionId].diff()}=1 and {TEMPLATE_NAME:squid[cacheVersionId].strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Squid: Swap usage is more than low watermark (>{ITEM.VALUE2}%) |<p>-</p> |`{TEMPLATE_NAME:squid[cacheCurrentSwapSize].last()}>{Template App Squid SNMP:squid[cacheSwapLowWM].last()}*{Template App Squid SNMP:squid[cacheSwapMaxSize].last()}/100` |WARNING | |
|Squid: Swap usage is more than high watermark (>{ITEM.VALUE2}%) |<p>-</p> |`{TEMPLATE_NAME:squid[cacheCurrentSwapSize].last()}>{Template App Squid SNMP:squid[cacheSwapHighWM].last()}*{Template App Squid SNMP:squid[cacheSwapMaxSize].last()}/100` |HIGH | |
|Squid: Squid is running out of file descriptors (<{$SQUID.FILE.DESC.WARN.MIN}) |<p>-</p> |`{TEMPLATE_NAME:squid[cacheCurrentUnusedFDescrCnt].last()}<{$SQUID.FILE.DESC.WARN.MIN}` |WARNING | |
|Squid: High sys page faults rate (>{$SQUID.PAGE.FAULT.WARN}% of recieved HTTP requests) |<p>-</p> |`{TEMPLATE_NAME:squid[cacheSysPageFaultsRate].avg(5m)}>{Template App Squid SNMP:squid[cacheProtoClientHttpRequestsRate].avg(5m)}/100*{$SQUID.PAGE.FAULT.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide a feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/409339-discussion-thread-for-official-zabbix-template-squid).

