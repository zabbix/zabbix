
# Memcached by Zabbix agent 2

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor Memcached server by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `Memcached by Zabbix agent 2` — collects metrics by polling zabbix-agent2.



This template was tested on:

- Memcached, version 1.4, 1.5, 1.6

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent2) for basic instructions.

Setup and configure zabbix-agent2 compiled with the Memcached monitoring [plugin](/go/plugins/memcached).

Test availability: `zabbix_get -s memcached-host -k memcached.ping`


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MEMCACHED.CONN.PRC.MAX.WARN} |<p>Maximum percentage of connected clients</p> |`80` |
|{$MEMCACHED.CONN.QUEUED.MAX.WARN} |<p>Maximum number of queued connections per second</p> |`1` |
|{$MEMCACHED.CONN.THROTTLED.MAX.WARN} |<p>Maximum number of throttled connections per second</p> |`1` |
|{$MEMCACHED.CONN.URI} |<p>Connection string in the URI format (password is not used). This param overwrites a value configured in the "Plugins.Memcached.Uri" option of the configuration file (if it's set), otherwise, the plugin's default value is used: "tcp://localhost:11211"</p> |`tcp://localhost:11211` |
|{$MEMCACHED.MEM.PUSED.MAX.WARN} |<p>Maximum percentage of memory used</p> |`90` |

## Template links

There are no template links in this template.

## Discovery rules


## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Memcached |Memcached: Ping | |ZABBIX_PASSIVE |memcached.ping["{$MEMCACHED.CONN.URI}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Memcached |Memcached: Max connections |<p>Max number of concurrent connections</p> |DEPENDENT |memcached.connections.max<p>**Preprocessing**:</p><p>- JSONPATH: `$.max_connections`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|Memcached |Memcached: Maximum number of bytes |<p>Maximum number of bytes allowed in cache. You can adjust this setting via a config file or the command line while starting your Memcached server.</p> |DEPENDENT |memcached.config.limit_maxbytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.limit_maxbytes`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|Memcached |Memcached: CPU sys |<p>System CPU consumed by the Memcached server</p> |DEPENDENT |memcached.cpu.sys<p>**Preprocessing**:</p><p>- JSONPATH: `$.rusage_system`</p> |
|Memcached |Memcached: CPU user |<p>User CPU consumed by the Memcached server</p> |DEPENDENT |memcached.cpu.user<p>**Preprocessing**:</p><p>- JSONPATH: `$.rusage_user`</p> |
|Memcached |Memcached: Queued connections per second |<p>Number of times that memcached has hit its connections limit and disabled its listener</p> |DEPENDENT |memcached.connections.queued.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.listen_disabled_num`</p><p>- CHANGE_PER_SECOND</p> |
|Memcached |Memcached: New connections per second |<p>Number of connections opened per second</p> |DEPENDENT |memcached.connections.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.total_connections`</p><p>- CHANGE_PER_SECOND</p> |
|Memcached |Memcached: Throttled connections |<p>Number of times a client connection was throttled. When sending GETs in batch mode and the connection contains too many requests (limited by -R parameter) the connection might be throttled to prevent starvation.</p> |DEPENDENT |memcached.connections.throttled.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.conn_yields`</p><p>- CHANGE_PER_SECOND</p> |
|Memcached |Memcached: Connection structures |<p>Number of  connection structures allocated by the server</p> |DEPENDENT |memcached.connections.structures<p>**Preprocessing**:</p><p>- JSONPATH: `$.connection_structures`</p> |
|Memcached |Memcached: Open connections |<p>The number of clients presently connected</p> |DEPENDENT |memcached.connections.current<p>**Preprocessing**:</p><p>- JSONPATH: `$.curr_connections`</p> |
|Memcached |Memcached: Commands: FLUSH per second |<p>The flush_all command invalidates all items in the database. This operation incurs a performance penalty and shouldn't take place in production, so check your debug scripts.</p> |DEPENDENT |memcached.commands.flush.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.cmd_flush`</p><p>- CHANGE_PER_SECOND</p> |
|Memcached |Memcached: Commands: GET per second |<p>Number of GET requests received by server per second.</p> |DEPENDENT |memcached.commands.get.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.cmd_get`</p><p>- CHANGE_PER_SECOND</p> |
|Memcached |Memcached: Commands: SET per second |<p>Number of SET requests received by server per second.</p> |DEPENDENT |memcached.commands.set.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.cmd_set`</p><p>- CHANGE_PER_SECOND</p> |
|Memcached |Memcached: Process id |<p>PID of the server process</p> |DEPENDENT |memcached.process_id<p>**Preprocessing**:</p><p>- JSONPATH: `$.pid`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memcached |Memcached: Memcached version |<p>Version of the Memcached server</p> |DEPENDENT |memcached.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.version`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memcached |Memcached: Uptime |<p>Number of seconds since Memcached server start</p> |DEPENDENT |memcached.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.uptime`</p> |
|Memcached |Memcached: Bytes used |<p>Current number of bytes used to store items.</p> |DEPENDENT |memcached.stats.bytes<p>**Preprocessing**:</p><p>- JSONPATH: `$.bytes`</p> |
|Memcached |Memcached: Written bytes per second |<p>The network's read rate per second in B/sec</p> |DEPENDENT |memcached.stats.bytes_written.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.bytes_written`</p><p>- CHANGE_PER_SECOND</p> |
|Memcached |Memcached: Read bytes per second |<p>The network's read rate per second in B/sec</p> |DEPENDENT |memcached.stats.bytes_read.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.bytes_read`</p><p>- CHANGE_PER_SECOND</p> |
|Memcached |Memcached: Hits per second |<p>Number of successful GET requests (items requested and found) per second.</p> |DEPENDENT |memcached.stats.hits.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.get_hits`</p><p>- CHANGE_PER_SECOND</p> |
|Memcached |Memcached: Misses per second |<p>Number of missed GET requests (items requested but not found) per second.</p> |DEPENDENT |memcached.stats.misses.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.get_misses`</p><p>- CHANGE_PER_SECOND</p> |
|Memcached |Memcached: Evictions per second |<p>"An eviction is when an item that still has time to live is removed from the cache because a brand new item needs to be allocated.</p><p>The item is selected with a pseudo-LRU mechanism.</p><p>A high number of evictions coupled with a low hit rate means your application is setting a large number of keys that are never used again."</p> |DEPENDENT |memcached.stats.evictions.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.evictions`</p><p>- CHANGE_PER_SECOND</p> |
|Memcached |Memcached: New items per second |<p>Number of new items stored per second.</p> |DEPENDENT |memcached.stats.total_items.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.total_items`</p><p>- CHANGE_PER_SECOND</p> |
|Memcached |Memcached: Current number of items stored |<p>Current number of items stored by this instance.</p> |DEPENDENT |memcached.stats.curr_items<p>**Preprocessing**:</p><p>- JSONPATH: `$.curr_items`</p> |
|Memcached |Memcached: Threads |<p>Number of worker threads requested</p> |DEPENDENT |memcached.stats.threads<p>**Preprocessing**:</p><p>- JSONPATH: `$.threads`</p> |
|Zabbix raw items |Memcached: Get status | |ZABBIX_PASSIVE |memcached.stats["{$MEMCACHED.CONN.URI}"] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Memcached: Service is down |<p>-</p> |`last(/Memcached by Zabbix agent 2/memcached.ping["{$MEMCACHED.CONN.URI}"])=0` |AVERAGE |<p>Manual close: YES</p> |
|Memcached: Failed to fetch info data |<p>Zabbix has not received data for items for the last 30 minutes</p> |`nodata(/Memcached by Zabbix agent 2/memcached.cpu.sys,30m)=1` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- Memcached: Service is down</p> |
|Memcached: Too many queued connections |<p>The max number of connections is reached and a new connection had to wait in the queue as a result.</p> |`min(/Memcached by Zabbix agent 2/memcached.connections.queued.rate,5m)>{$MEMCACHED.CONN.QUEUED.MAX.WARN}` |WARNING | |
|Memcached: Too many throttled connections |<p>Number of times a client connection was throttled is too high.</p><p>When sending GETs in batch mode and the connection contains too many requests (limited by -R parameter) the connection might be throttled to prevent starvation.</p> |`min(/Memcached by Zabbix agent 2/memcached.connections.throttled.rate,5m)>{$MEMCACHED.CONN.THROTTLED.MAX.WARN}` |WARNING | |
|Memcached: Total number of connected clients is too high |<p>When the number of connections reaches the value of the "max_connections" parameter, new connections will be rejected.</p> |`min(/Memcached by Zabbix agent 2/memcached.connections.current,5m)/last(/Memcached by Zabbix agent 2/memcached.connections.max)*100>{$MEMCACHED.CONN.PRC.MAX.WARN}` |WARNING | |
|Memcached: Version has changed |<p>Memcached version has changed. Ack to close.</p> |`last(/Memcached by Zabbix agent 2/memcached.version,#1)<>last(/Memcached by Zabbix agent 2/memcached.version,#2) and length(last(/Memcached by Zabbix agent 2/memcached.version))>0` |INFO |<p>Manual close: YES</p> |
|Memcached: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/Memcached by Zabbix agent 2/memcached.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|Memcached: Memory usage is too high |<p>-</p> |`min(/Memcached by Zabbix agent 2/memcached.stats.bytes,5m)/last(/Memcached by Zabbix agent 2/memcached.config.limit_maxbytes)*100>{$MEMCACHED.MEM.PUSED.MAX.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/398623-discussion-thread-for-official-zabbix-template-memcached).

