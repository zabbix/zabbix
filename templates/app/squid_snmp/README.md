
# Squid by SNMP

## Overview

This template is designed for the effortless deployment of Squid monitoring by Zabbix via SNMP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Squid 3.5.12

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

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
1\. [Import](https://www.zabbix.com/documentation/7.0/manual/xml_export_import/templates) the template [template_app_squid_snmp.yaml](template_app_squid_snmp.yaml) into Zabbix.

2\. Set values for {$SQUID.SNMP.COMMUNITY}, {$SQUID.SNMP.PORT} and {$SQUID.HTTP.PORT} as configured in squid.conf.

3\. [Link](https://www.zabbix.com/documentation/7.0/manual/config/templates/linking) the imported template to a host with Squid.

4\. Add SNMPv2 interface to Squid host. Set **Port** as {$SQUID.SNMP.PORT} and **SNMP community** as {$SQUID.SNMP.COMMUNITY}.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$SQUID.SNMP.PORT}|<p>snmp_port configured in squid.conf (Default: 3401)</p>|`3401`|
|{$SQUID.HTTP.PORT}|<p>http_port configured in squid.conf (Default: 3128)</p>|`3128`|
|{$SQUID.SNMP.COMMUNITY}|<p>SNMP community allowed by ACL in squid.conf</p>|`public`|
|{$SQUID.FILE.DESC.WARN.MIN}|<p>The threshold for minimum number of available file descriptors</p>|`100`|
|{$SQUID.PAGE.FAULT.WARN}|<p>The threshold for sys page faults rate in percent of received HTTP requests</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Squid: Service ping||Simple check|net.tcp.service[tcp,,{$SQUID.HTTP.PORT}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Squid: Uptime|<p>The Uptime of the cache in timeticks (in hundredths of a second) with preprocessing</p>|SNMP agent|squid[cacheUptime]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Squid: Version|<p>Cache Software Version</p>|SNMP agent|squid[cacheVersionId]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Squid: CPU usage|<p>The percentage use of the CPU</p>|SNMP agent|squid[cacheCpuUsage]|
|Squid: Memory maximum resident size|<p>Maximum Resident Size</p>|SNMP agent|squid[cacheMaxResSize]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Squid: Memory maximum cache size|<p>The value of the cache_mem parameter</p>|SNMP agent|squid[cacheMemMaxSize]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Squid: Memory cache usage|<p>Total accounted memory</p>|SNMP agent|squid[cacheMemUsage]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li></ul>|
|Squid: Cache swap low water mark|<p>Cache Swap Low Water Mark</p>|SNMP agent|squid[cacheSwapLowWM]|
|Squid: Cache swap high water mark|<p>Cache Swap High Water Mark</p>|SNMP agent|squid[cacheSwapHighWM]|
|Squid: Cache swap directory size|<p>The total of the cache_dir space allocated</p>|SNMP agent|squid[cacheSwapMaxSize]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Squid: Cache swap current size|<p>Storage Swap Size</p>|SNMP agent|squid[cacheCurrentSwapSize]|
|Squid: File descriptor count - current used|<p>Number of file descriptors in use</p>|SNMP agent|squid[cacheCurrentFileDescrCnt]|
|Squid: File descriptor count - current maximum|<p>Highest number of file descriptors in use</p>|SNMP agent|squid[cacheCurrentFileDescrMax]|
|Squid: File descriptor count - current reserved|<p>Reserved number of file descriptors</p>|SNMP agent|squid[cacheCurrentResFileDescrCnt]|
|Squid: File descriptor count - current available|<p>Available number of file descriptors</p>|SNMP agent|squid[cacheCurrentUnusedFDescrCnt]|
|Squid: Byte hit ratio per 1 minute|<p>Byte Hit Ratios</p>|SNMP agent|squid[cacheRequestByteRatio.1]|
|Squid: Byte hit ratio per 5 minutes|<p>Byte Hit Ratios</p>|SNMP agent|squid[cacheRequestByteRatio.5]|
|Squid: Byte hit ratio per 1 hour|<p>Byte Hit Ratios</p>|SNMP agent|squid[cacheRequestByteRatio.60]|
|Squid: Request hit ratio per 1 minute|<p>Byte Hit Ratios</p>|SNMP agent|squid[cacheRequestHitRatio.1]|
|Squid: Request hit ratio per 5 minutes|<p>Byte Hit Ratios</p>|SNMP agent|squid[cacheRequestHitRatio.5]|
|Squid: Request hit ratio per 1 hour|<p>Byte Hit Ratios</p>|SNMP agent|squid[cacheRequestHitRatio.60]|
|Squid: Sys page faults per second|<p>Page faults with physical I/O</p>|SNMP agent|squid[cacheSysPageFaults]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: HTTP requests received per second|<p>Number of HTTP requests received</p>|SNMP agent|squid[cacheProtoClientHttpRequests]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: HTTP traffic received per second|<p>Number of HTTP traffic received from clients</p>|SNMP agent|squid[cacheHttpInKb]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li><li>Change per second</li></ul>|
|Squid: HTTP traffic sent per second|<p>Number of HTTP traffic sent to clients</p>|SNMP agent|squid[cacheHttpOutKb]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li><li>Change per second</li></ul>|
|Squid: HTTP Hits sent from cache per second|<p>Number of HTTP Hits sent to clients from cache</p>|SNMP agent|squid[cacheHttpHits]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: HTTP Errors sent per second|<p>Number of HTTP Errors sent to clients</p>|SNMP agent|squid[cacheHttpErrors]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: ICP messages sent per second|<p>Number of ICP messages sent</p>|SNMP agent|squid[cacheIcpPktsSent]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: ICP messages received per second|<p>Number of ICP messages received</p>|SNMP agent|squid[cacheIcpPktsRecv]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: ICP traffic transmitted per second|<p>Number of ICP traffic transmitted</p>|SNMP agent|squid[cacheIcpKbSent]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li><li>Change per second</li></ul>|
|Squid: ICP traffic received per second|<p>Number of ICP traffic received</p>|SNMP agent|squid[cacheIcpKbRecv]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `1024`</p></li><li>Change per second</li></ul>|
|Squid: DNS server requests per second|<p>Number of external dns server requests</p>|SNMP agent|squid[cacheDnsRequests]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: DNS server replies per second|<p>Number of external dns server replies</p>|SNMP agent|squid[cacheDnsReplies]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: FQDN cache requests per second|<p>Number of FQDN Cache requests</p>|SNMP agent|squid[cacheFqdnRequests]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: FQDN cache hits per second|<p>Number of FQDN Cache hits</p>|SNMP agent|squid[cacheFqdnHits]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: FQDN cache misses per second|<p>Number of FQDN Cache misses</p>|SNMP agent|squid[cacheFqdnMisses]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: IP cache requests per second|<p>Number of IP Cache requests</p>|SNMP agent|squid[cacheIpRequests]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: IP cache hits per second|<p>Number of IP Cache hits</p>|SNMP agent|squid[cacheIpHits]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: IP cache misses per second|<p>Number of IP Cache misses</p>|SNMP agent|squid[cacheIpMisses]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Squid: Objects count|<p>Number of objects stored by the cache</p>|SNMP agent|squid[cacheNumObjCount]|
|Squid: Objects LRU expiration age|<p>Storage LRU Expiration Age</p>|SNMP agent|squid[cacheCurrentLRUExpiration]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.01`</p></li></ul>|
|Squid: Objects unlinkd requests|<p>Requests given to unlinkd</p>|SNMP agent|squid[cacheCurrentUnlinkRequests]|
|Squid: HTTP all service time per 5 minutes|<p>HTTP all service time per 5 minutes</p>|SNMP agent|squid[cacheHttpAllSvcTime.5]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Squid: HTTP all service time per hour|<p>HTTP all service time per hour</p>|SNMP agent|squid[cacheHttpAllSvcTime.60]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Squid: HTTP miss service time per 5 minutes|<p>HTTP miss service time per 5 minutes</p>|SNMP agent|squid[cacheHttpMissSvcTime.5]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Squid: HTTP miss service time per hour|<p>HTTP miss service time per hour</p>|SNMP agent|squid[cacheHttpMissSvcTime.60]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Squid: HTTP hit service time per 5 minutes|<p>HTTP hit service time per 5 minutes</p>|SNMP agent|squid[cacheHttpHitSvcTime.5]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Squid: HTTP hit service time per hour|<p>HTTP hit service time per hour</p>|SNMP agent|squid[cacheHttpHitSvcTime.60]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Squid: ICP query service time per 5 minutes|<p>ICP query service time per 5 minutes</p>|SNMP agent|squid[cacheIcpQuerySvcTime.5]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Squid: ICP query service time per hour|<p>ICP query service time per hour</p>|SNMP agent|squid[cacheIcpQuerySvcTime.60]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Squid: ICP reply service time per 5 minutes|<p>ICP reply service time per 5 minutes</p>|SNMP agent|squid[cacheIcpReplySvcTime.5]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Squid: ICP reply service time per hour|<p>ICP reply service time per hour</p>|SNMP agent|squid[cacheIcpReplySvcTime.60]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Squid: DNS service time per 5 minutes|<p>DNS service time per 5 minutes</p>|SNMP agent|squid[cacheDnsSvcTime.5]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Squid: DNS service time per hour|<p>DNS service time per hour</p>|SNMP agent|squid[cacheDnsSvcTime.60]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Squid: Port {$SQUID.HTTP.PORT} is down||`last(/Squid by SNMP/net.tcp.service[tcp,,{$SQUID.HTTP.PORT}])=0`|Average|**Manual close**: Yes|
|Squid: Squid has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Squid by SNMP/squid[cacheUptime])<10m`|Info|**Manual close**: Yes|
|Squid: Squid version has been changed|<p>Squid version has changed. Acknowledge to close the problem manually.</p>|`last(/Squid by SNMP/squid[cacheVersionId],#1)<>last(/Squid by SNMP/squid[cacheVersionId],#2) and length(last(/Squid by SNMP/squid[cacheVersionId]))>0`|Info|**Manual close**: Yes|
|Squid: Swap usage is more than low watermark||`last(/Squid by SNMP/squid[cacheCurrentSwapSize])>last(/Squid by SNMP/squid[cacheSwapLowWM])*last(/Squid by SNMP/squid[cacheSwapMaxSize])/100`|Warning||
|Squid: Swap usage is more than high watermark||`last(/Squid by SNMP/squid[cacheCurrentSwapSize])>last(/Squid by SNMP/squid[cacheSwapHighWM])*last(/Squid by SNMP/squid[cacheSwapMaxSize])/100`|High||
|Squid: Squid is running out of file descriptors||`last(/Squid by SNMP/squid[cacheCurrentUnusedFDescrCnt])<{$SQUID.FILE.DESC.WARN.MIN}`|Warning||
|Squid: High sys page faults rate||`avg(/Squid by SNMP/squid[cacheSysPageFaults],5m)>avg(/Squid by SNMP/squid[cacheProtoClientHttpRequests],5m)/100*{$SQUID.PAGE.FAULT.WARN}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

