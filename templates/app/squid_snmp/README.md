
# Squid SNMP

## Overview

For Zabbix version: 6.0 and higher  

This template was tested on:

- Squid, version 3.5.12

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/network_devices) for basic instructions.

### Setup Squid
Enable SNMP support following [official documentation](https://wiki.squid-cache.org/Features/Snmp).
Required parameters in squid.conf:
```
snmp_port <port_number>
acl <zbx_acl_name> snmp_community <community_name>
snmp_access allow <zbx_acl_name> <zabbix_server_ip>
```

### Setup Zabbix
1\. [Import](https://www.zabbix.com/documentation/6.0/manual/xml_export_import/templates) the template [template_app_squid_snmp.yaml](template_app_squid_snmp.yaml) into Zabbix.

2\. Set values for {$SQUID.SNMP.COMMUNITY}, {$SQUID.SNMP.PORT} and {$SQUID.HTTP.PORT} as configured in squid.conf.

3\. [Link](https://www.zabbix.com/documentation/6.0/manual/config/templates/linking) the imported template to a host with Squid.

4\. Add SNMPv2 interface to Squid host. Set **Port** as {$SQUID.SNMP.PORT} and **SNMP community** as {$SQUID.SNMP.COMMUNITY}.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SQUID.FILE.DESC.WARN.MIN} |<p>The threshold for minimum number of available file descriptors</p> |`100` |
|{$SQUID.HTTP.PORT} |<p>http_port configured in squid.conf (Default: 3128)</p> |`3128` |
|{$SQUID.PAGE.FAULT.WARN} |<p>The threshold for sys page faults rate in percent of received HTTP requests</p> |`90` |
|{$SQUID.SNMP.COMMUNITY} |<p>SNMP community allowed by ACL in squid.conf</p> |`public` |
|{$SQUID.SNMP.PORT} |<p>snmp_port configured in squid.conf (Default: 3401)</p> |`3401` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Squid |Squid: Service ping |<p>-</p> |SIMPLE |net.tcp.service[tcp,,{$SQUID.HTTP.PORT}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Squid |Squid: Uptime |<p>The Uptime of the cache in timeticks (in hundredths of a second) with preprocessing</p> |SNMP |squid[cacheUptime]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Squid |Squid: Version |<p>Cache Software Version</p> |SNMP |squid[cacheVersionId]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Squid |Squid: CPU usage |<p>The percentage use of the CPU</p> |SNMP |squid[cacheCpuUsage] |
|Squid |Squid: Memory maximum resident size |<p>Maximum Resident Size</p> |SNMP |squid[cacheMaxResSize]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: Memory maximum cache size |<p>The value of the cache_mem parameter</p> |SNMP |squid[cacheMemMaxSize]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Squid |Squid: Memory cache usage |<p>Total accounted memory</p> |SNMP |squid[cacheMemUsage]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p> |
|Squid |Squid: Cache swap low water mark |<p>Cache Swap Low Water Mark</p> |SNMP |squid[cacheSwapLowWM] |
|Squid |Squid: Cache swap high water mark |<p>Cache Swap High Water Mark</p> |SNMP |squid[cacheSwapHighWM] |
|Squid |Squid: Cache swap directory size |<p>The total of the cache_dir space allocated</p> |SNMP |squid[cacheSwapMaxSize]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1048576`</p> |
|Squid |Squid: Cache swap current size |<p>Storage Swap Size</p> |SNMP |squid[cacheCurrentSwapSize] |
|Squid |Squid: File descriptor count - current used |<p>Number of file descriptors in use</p> |SNMP |squid[cacheCurrentFileDescrCnt] |
|Squid |Squid: File descriptor count - current maximum |<p>Highest number of file descriptors in use</p> |SNMP |squid[cacheCurrentFileDescrMax] |
|Squid |Squid: File descriptor count - current reserved |<p>Reserved number of file descriptors</p> |SNMP |squid[cacheCurrentResFileDescrCnt] |
|Squid |Squid: File descriptor count - current available |<p>Available number of file descriptors</p> |SNMP |squid[cacheCurrentUnusedFDescrCnt] |
|Squid |Squid: Byte hit ratio per 1 minute |<p>Byte Hit Ratios</p> |SNMP |squid[cacheRequestByteRatio.1] |
|Squid |Squid: Byte hit ratio per 5 minutes |<p>Byte Hit Ratios</p> |SNMP |squid[cacheRequestByteRatio.5] |
|Squid |Squid: Byte hit ratio per 1 hour |<p>Byte Hit Ratios</p> |SNMP |squid[cacheRequestByteRatio.60] |
|Squid |Squid: Request hit ratio per 1 minute |<p>Byte Hit Ratios</p> |SNMP |squid[cacheRequestHitRatio.1] |
|Squid |Squid: Request hit ratio per 5 minutes |<p>Byte Hit Ratios</p> |SNMP |squid[cacheRequestHitRatio.5] |
|Squid |Squid: Request hit ratio per 1 hour |<p>Byte Hit Ratios</p> |SNMP |squid[cacheRequestHitRatio.60] |
|Squid |Squid: Sys page faults per second |<p>Page faults with physical I/O</p> |SNMP |squid[cacheSysPageFaults]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: HTTP requests received per second |<p>Number of HTTP requests received</p> |SNMP |squid[cacheProtoClientHttpRequests]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: HTTP traffic received per second |<p>Number of HTTP traffic received from clients</p> |SNMP |squid[cacheHttpInKb]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: HTTP traffic sent per second |<p>Number of HTTP traffic sent to clients</p> |SNMP |squid[cacheHttpOutKb]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: HTTP Hits sent from cache per second |<p>Number of HTTP Hits sent to clients from cache</p> |SNMP |squid[cacheHttpHits]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: HTTP Errors sent per second |<p>Number of HTTP Errors sent to clients</p> |SNMP |squid[cacheHttpErrors]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: ICP messages sent per second |<p>Number of ICP messages sent</p> |SNMP |squid[cacheIcpPktsSent]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: ICP messages received per second |<p>Number of ICP messages received</p> |SNMP |squid[cacheIcpPktsRecv]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: ICP traffic transmitted per second |<p>Number of ICP traffic transmitted</p> |SNMP |squid[cacheIcpKbSent]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: ICP traffic received per second |<p>Number of ICP traffic received</p> |SNMP |squid[cacheIcpKbRecv]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1024`</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: DNS server requests per second |<p>Number of external dns server requests</p> |SNMP |squid[cacheDnsRequests]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: DNS server replies per second |<p>Number of external dns server replies</p> |SNMP |squid[cacheDnsReplies]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: FQDN cache requests per second |<p>Number of FQDN Cache requests</p> |SNMP |squid[cacheFqdnRequests]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: FQDN cache hits per second |<p>Number of FQDN Cache hits</p> |SNMP |squid[cacheFqdnHits]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: FQDN cache misses per second |<p>Number of FQDN Cache misses</p> |SNMP |squid[cacheFqdnMisses]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: IP cache requests per second |<p>Number of IP Cache requests</p> |SNMP |squid[cacheIpRequests]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: IP cache hits per second |<p>Number of IP Cache hits</p> |SNMP |squid[cacheIpHits]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: IP cache misses per second |<p>Number of IP Cache misses</p> |SNMP |squid[cacheIpMisses]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Squid |Squid: Objects count |<p>Number of objects stored by the cache</p> |SNMP |squid[cacheNumObjCount] |
|Squid |Squid: Objects LRU expiration age |<p>Storage LRU Expiration Age</p> |SNMP |squid[cacheCurrentLRUExpiration]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Squid |Squid: Objects unlinkd requests |<p>Requests given to unlinkd</p> |SNMP |squid[cacheCurrentUnlinkRequests] |
|Squid |Squid: HTTP all service time per 5 minutes |<p>HTTP all service time per 5 minutes</p> |SNMP |squid[cacheHttpAllSvcTime.5]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: HTTP all service time per hour |<p>HTTP all service time per hour</p> |SNMP |squid[cacheHttpAllSvcTime.60]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: HTTP miss service time per 5 minutes |<p>HTTP miss service time per 5 minutes</p> |SNMP |squid[cacheHttpMissSvcTime.5]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: HTTP miss service time per hour |<p>HTTP miss service time per hour</p> |SNMP |squid[cacheHttpMissSvcTime.60]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: HTTP hit service time per 5 minutes |<p>HTTP hit service time per 5 minutes</p> |SNMP |squid[cacheHttpHitSvcTime.5]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: HTTP hit service time per hour |<p>HTTP hit service time per hour</p> |SNMP |squid[cacheHttpHitSvcTime.60]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: ICP query service time per 5 minutes |<p>ICP query service time per 5 minutes</p> |SNMP |squid[cacheIcpQuerySvcTime.5]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: ICP query service time per hour |<p>ICP query service time per hour</p> |SNMP |squid[cacheIcpQuerySvcTime.60]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: ICP reply service time per 5 minutes |<p>ICP reply service time per 5 minutes</p> |SNMP |squid[cacheIcpReplySvcTime.5]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: ICP reply service time per hour |<p>ICP reply service time per hour</p> |SNMP |squid[cacheIcpReplySvcTime.60]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: DNS service time per 5 minutes |<p>DNS service time per 5 minutes</p> |SNMP |squid[cacheDnsSvcTime.5]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Squid |Squid: DNS service time per hour |<p>DNS service time per hour</p> |SNMP |squid[cacheDnsSvcTime.60]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Squid: Port {$SQUID.HTTP.PORT} is down |<p>-</p> |`last(/Squid SNMP/net.tcp.service[tcp,,{$SQUID.HTTP.PORT}])=0` |AVERAGE |<p>Manual close: YES</p> |
|Squid: Squid has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Squid SNMP/squid[cacheUptime])<10m` |INFO |<p>Manual close: YES</p> |
|Squid: Squid version has been changed |<p>Squid version has changed. Ack to close.</p> |`last(/Squid SNMP/squid[cacheVersionId],#1)<>last(/Squid SNMP/squid[cacheVersionId],#2) and length(last(/Squid SNMP/squid[cacheVersionId]))>0` |INFO |<p>Manual close: YES</p> |
|Squid: Swap usage is more than low watermark |<p>-</p> |`last(/Squid SNMP/squid[cacheCurrentSwapSize])>last(/Squid SNMP/squid[cacheSwapLowWM])*last(/Squid SNMP/squid[cacheSwapMaxSize])/100` |WARNING | |
|Squid: Swap usage is more than high watermark |<p>-</p> |`last(/Squid SNMP/squid[cacheCurrentSwapSize])>last(/Squid SNMP/squid[cacheSwapHighWM])*last(/Squid SNMP/squid[cacheSwapMaxSize])/100` |HIGH | |
|Squid: Squid is running out of file descriptors |<p>-</p> |`last(/Squid SNMP/squid[cacheCurrentUnusedFDescrCnt])<{$SQUID.FILE.DESC.WARN.MIN}` |WARNING | |
|Squid: High sys page faults rate |<p>-</p> |`avg(/Squid SNMP/squid[cacheSysPageFaults],5m)>avg(/Squid SNMP/squid[cacheProtoClientHttpRequests],5m)/100*{$SQUID.PAGE.FAULT.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/409339-discussion-thread-for-official-zabbix-template-squid).

