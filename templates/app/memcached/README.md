
# Memcached by Zabbix agent 2

## Overview

This template is designed for the effortless deployment of Memcached monitoring by Zabbix via Zabbix agent 2 and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Memcached 1.4, 1.5, 1.6

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Setup and configure zabbix-agent2 compiled with the Memcached monitoring [plugin](/go/plugins/memcached).

Test availability: `zabbix_get -s memcached-host -k memcached.ping`

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MEMCACHED.CONN.URI}|<p>Connection string in the URI format (password is not used). This param overwrites a value configured in the "Plugins.Memcached.Uri" option of the configuration file (if it's set), otherwise, the plugin's default value is used: "tcp://localhost:11211"</p>|`tcp://localhost:11211`|
|{$MEMCACHED.CONN.THROTTLED.MAX.WARN}|<p>Maximum number of throttled connections per second</p>|`1`|
|{$MEMCACHED.CONN.QUEUED.MAX.WARN}|<p>Maximum number of queued connections per second</p>|`1`|
|{$MEMCACHED.CONN.PRC.MAX.WARN}|<p>Maximum percentage of connected clients</p>|`80`|
|{$MEMCACHED.MEM.PUSED.MAX.WARN}|<p>Maximum percentage of memory used</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Memcached: Get status||Zabbix agent|memcached.stats["{$MEMCACHED.CONN.URI}"]|
|Memcached: Ping||Zabbix agent|memcached.ping["{$MEMCACHED.CONN.URI}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Memcached: Max connections|<p>Max number of concurrent connections</p>|Dependent item|memcached.connections.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.max_connections`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Memcached: Maximum number of bytes|<p>Maximum number of bytes allowed in cache. You can adjust this setting via a config file or the command line while starting your Memcached server.</p>|Dependent item|memcached.config.limit_maxbytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.limit_maxbytes`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Memcached: CPU sys|<p>System CPU consumed by the Memcached server</p>|Dependent item|memcached.cpu.sys<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rusage_system`</p></li></ul>|
|Memcached: CPU user|<p>User CPU consumed by the Memcached server</p>|Dependent item|memcached.cpu.user<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rusage_user`</p></li></ul>|
|Memcached: Queued connections per second|<p>Number of times that memcached has hit its connections limit and disabled its listener</p>|Dependent item|memcached.connections.queued.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.listen_disabled_num`</p></li><li>Change per second</li></ul>|
|Memcached: New connections per second|<p>Number of connections opened per second</p>|Dependent item|memcached.connections.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_connections`</p></li><li>Change per second</li></ul>|
|Memcached: Throttled connections|<p>Number of times a client connection was throttled. When sending GETs in batch mode and the connection contains too many requests (limited by -R parameter) the connection might be throttled to prevent starvation.</p>|Dependent item|memcached.connections.throttled.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.conn_yields`</p></li><li>Change per second</li></ul>|
|Memcached: Connection structures|<p>Number of  connection structures allocated by the server</p>|Dependent item|memcached.connections.structures<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connection_structures`</p></li></ul>|
|Memcached: Open connections|<p>The number of clients presently connected</p>|Dependent item|memcached.connections.current<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.curr_connections`</p></li></ul>|
|Memcached: Commands: FLUSH per second|<p>The flush_all command invalidates all items in the database. This operation incurs a performance penalty and shouldn't take place in production, so check your debug scripts.</p>|Dependent item|memcached.commands.flush.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cmd_flush`</p></li><li>Change per second</li></ul>|
|Memcached: Commands: GET per second|<p>Number of GET requests received by server per second.</p>|Dependent item|memcached.commands.get.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cmd_get`</p></li><li>Change per second</li></ul>|
|Memcached: Commands: SET per second|<p>Number of SET requests received by server per second.</p>|Dependent item|memcached.commands.set.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cmd_set`</p></li><li>Change per second</li></ul>|
|Memcached: Process id|<p>PID of the server process</p>|Dependent item|memcached.process_id<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.pid`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Memcached: Memcached version|<p>Version of the Memcached server</p>|Dependent item|memcached.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Memcached: Uptime|<p>Number of seconds since Memcached server start</p>|Dependent item|memcached.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime`</p></li></ul>|
|Memcached: Bytes used|<p>Current number of bytes used to store items.</p>|Dependent item|memcached.stats.bytes<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes`</p></li></ul>|
|Memcached: Written bytes per second|<p>The network's read rate per second in B/sec</p>|Dependent item|memcached.stats.bytes_written.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes_written`</p></li><li>Change per second</li></ul>|
|Memcached: Read bytes per second|<p>The network's read rate per second in B/sec</p>|Dependent item|memcached.stats.bytes_read.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bytes_read`</p></li><li>Change per second</li></ul>|
|Memcached: Hits per second|<p>Number of successful GET requests (items requested and found) per second.</p>|Dependent item|memcached.stats.hits.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.get_hits`</p></li><li>Change per second</li></ul>|
|Memcached: Misses per second|<p>Number of missed GET requests (items requested but not found) per second.</p>|Dependent item|memcached.stats.misses.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.get_misses`</p></li><li>Change per second</li></ul>|
|Memcached: Evictions per second|<p>"An eviction is when an item that still has time to live is removed from the cache because a brand new item needs to be allocated.</p><p>The item is selected with a pseudo-LRU mechanism.</p><p>A high number of evictions coupled with a low hit rate means your application is setting a large number of keys that are never used again."</p>|Dependent item|memcached.stats.evictions.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.evictions`</p></li><li>Change per second</li></ul>|
|Memcached: New items per second|<p>Number of new items stored per second.</p>|Dependent item|memcached.stats.total_items.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.total_items`</p></li><li>Change per second</li></ul>|
|Memcached: Current number of items stored|<p>Current number of items stored by this instance.</p>|Dependent item|memcached.stats.curr_items<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.curr_items`</p></li></ul>|
|Memcached: Threads|<p>Number of worker threads requested</p>|Dependent item|memcached.stats.threads<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.threads`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Memcached: Service is down||`last(/Memcached by Zabbix agent 2/memcached.ping["{$MEMCACHED.CONN.URI}"])=0`|Average|**Manual close**: Yes|
|Memcached: Failed to fetch info data|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/Memcached by Zabbix agent 2/memcached.cpu.sys,30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Memcached: Service is down</li></ul>|
|Memcached: Too many queued connections|<p>The max number of connections is reached and a new connection had to wait in the queue as a result.</p>|`min(/Memcached by Zabbix agent 2/memcached.connections.queued.rate,5m)>{$MEMCACHED.CONN.QUEUED.MAX.WARN}`|Warning||
|Memcached: Too many throttled connections|<p>Number of times a client connection was throttled is too high.<br>When sending GETs in batch mode and the connection contains too many requests (limited by -R parameter) the connection might be throttled to prevent starvation.</p>|`min(/Memcached by Zabbix agent 2/memcached.connections.throttled.rate,5m)>{$MEMCACHED.CONN.THROTTLED.MAX.WARN}`|Warning||
|Memcached: Total number of connected clients is too high|<p>When the number of connections reaches the value of the "max_connections" parameter, new connections will be rejected.</p>|`min(/Memcached by Zabbix agent 2/memcached.connections.current,5m)/last(/Memcached by Zabbix agent 2/memcached.connections.max)*100>{$MEMCACHED.CONN.PRC.MAX.WARN}`|Warning||
|Memcached: Version has changed|<p>The Memcached version has changed. Acknowledge to close the problem manually.</p>|`last(/Memcached by Zabbix agent 2/memcached.version,#1)<>last(/Memcached by Zabbix agent 2/memcached.version,#2) and length(last(/Memcached by Zabbix agent 2/memcached.version))>0`|Info|**Manual close**: Yes|
|Memcached: has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Memcached by Zabbix agent 2/memcached.uptime)<10m`|Info|**Manual close**: Yes|
|Memcached: Memory usage is too high||`min(/Memcached by Zabbix agent 2/memcached.stats.bytes,5m)/last(/Memcached by Zabbix agent 2/memcached.config.limit_maxbytes)*100>{$MEMCACHED.MEM.PUSED.MAX.WARN}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

