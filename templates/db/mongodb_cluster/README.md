
# MongoDB cluster by Zabbix agent 2

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor MongoDB sharded cluster by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

`MongoDB cluster by Zabbix agent 2` — collects metrics from mongos proxy(router) by polling zabbix-agent2.


This template was tested on:

- MongoDB, version 4.0.21, 4.4.3

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent2) for basic instructions.


1. Setup and configure zabbix-agent2 compiled with the MongoDB monitoring plugin.
2. Set the {$MONGODB.CONNSTRING} such as <protocol(host:port)> or named session of mongos proxy(router).
3. Set the user name and password in host macros ({$MONGODB.USER}, {$MONGODB.PASSWORD}) if you want to override parameters from the Zabbix agent configuration file.

**Note**, depending on the number of DBs and collections discovery operation may be expensive. Use filters with macros {$MONGODB.LLD.FILTER.DB.MATCHES}, {$MONGODB.LLD.FILTER.DB.NOT_MATCHES}, {$MONGODB.LLD.FILTER.COLLECTION.MATCHES}, {$MONGODB.LLD.FILTER.COLLECTION.NOT_MATCHES}.

All sharded Mongodb nodes (mongod) will be discovered with attached template "MongoDB node by Zabbix agent 2".


Test availability: `zabbix_get -s mongos.node -k 'mongodb.ping["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]"`


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MONGODB.CONNS.AVAILABLE.MIN.WARN} |<p>Minimum number of available connections</p> |`1000` |
|{$MONGODB.CONNSTRING} |<p>Connection string in the URI format (password is not used). This param overwrites a value configured in the "Server" option of the configuration file (if it's set), otherwise, the plugin's default value is used: "tcp://localhost:27017"</p> |`tcp://localhost:27017` |
|{$MONGODB.CURSOR.OPEN.MAX.WARN} |<p>Maximum number of open cursors</p> |`10000` |
|{$MONGODB.CURSOR.TIMEOUT.MAX.WARN} |<p>Maximum number of cursors timing out per second</p> |`1` |
|{$MONGODB.LLD.FILTER.COLLECTION.MATCHES} |<p>Filter of discoverable collections</p> |`.*` |
|{$MONGODB.LLD.FILTER.COLLECTION.NOT_MATCHES} |<p>Filter to exclude discovered collections</p> |`CHANGE_IF_NEEDED` |
|{$MONGODB.LLD.FILTER.DB.MATCHES} |<p>Filter of discoverable databases</p> |`.*` |
|{$MONGODB.LLD.FILTER.DB.NOT_MATCHES} |<p>Filter to exclude discovered databases</p> |`(admin|config|local)` |
|{$MONGODB.PASSWORD} |<p>MongoDB user password</p> |`` |
|{$MONGODB.USER} |<p>MongoDB username</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Collection discovery |<p>Collect collections metrics.</p><p>Note, depending on the number of DBs and collections this discovery operation may be expensive. Use filters with macros {$MONGODB.LLD.FILTER.DB.MATCHES}, {$MONGODB.LLD.FILTER.DB.NOT_MATCHES}, {$MONGODB.LLD.FILTER.COLLECTION.MATCHES}, {$MONGODB.LLD.FILTER.COLLECTION.NOT_MATCHES}.</p> |ZABBIX_PASSIVE |mongodb.collections.discovery["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]<p>**Filter**:</p>AND <p>- {#DBNAME} MATCHES_REGEX `{$MONGODB.LLD.FILTER.DB.MATCHES}`</p><p>- {#DBNAME} NOT_MATCHES_REGEX `{$MONGODB.LLD.FILTER.DB.NOT_MATCHES}`</p><p>- {#COLLECTION} MATCHES_REGEX `{$MONGODB.LLD.FILTER.COLLECTION.MATCHES}`</p><p>- {#COLLECTION} NOT_MATCHES_REGEX `{$MONGODB.LLD.FILTER.COLLECTION.NOT_MATCHES}`</p> |
|Config servers discovery |<p>Discovery shared cluster config servers.</p> |ZABBIX_PASSIVE |mongodb.cfg.discovery["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"] |
|Database discovery |<p>Collect database metrics.</p><p>Note, depending on the number of DBs this discovery operation may be expensive. Use filters with macros {$MONGODB.LLD.FILTER.DB.MATCHES}, {$MONGODB.LLD.FILTER.DB.NOT_MATCHES}.</p> |ZABBIX_PASSIVE |mongodb.db.discovery["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]<p>**Filter**:</p>AND <p>- {#DBNAME} MATCHES_REGEX `{$MONGODB.LLD.FILTER.DB.MATCHES}`</p><p>- {#DBNAME} NOT_MATCHES_REGEX `{$MONGODB.LLD.FILTER.DB.NOT_MATCHES}`</p> |
|Shards discovery |<p>Discovery shared cluster hosts.</p> |ZABBIX_PASSIVE |mongodb.sh.discovery["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"] |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|MongoDB sharded cluster |MongoDB cluster: Ping |<p>Test if a connection is alive or not.</p> |ZABBIX_PASSIVE |mongodb.ping["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|MongoDB sharded cluster |MongoDB cluster: Jumbo chunks |<p>Total number of 'jumbo' chunks in the mongo cluster.</p> |ZABBIX_PASSIVE |mongodb.jumbo_chunks.count["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"] |
|MongoDB sharded cluster |MongoDB cluster: Mongos version |<p>Version of the Mongos server</p> |DEPENDENT |mongodb.version<p>**Preprocessing**:</p><p>- JSONPATH: `$.version`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|MongoDB sharded cluster |MongoDB cluster: Uptime |<p>Number of seconds since Mongos server start</p> |DEPENDENT |mongodb.uptime<p>**Preprocessing**:</p><p>- JSONPATH: `$.uptime`</p> |
|MongoDB sharded cluster |MongoDB cluster: Operations: command |<p>"The number of commands issued to the database per second.</p><p>Counts all commands except the write commands: insert, update, and delete."</p> |DEPENDENT |mongodb.opcounters.command.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.opcounters.command`</p><p>- CHANGE_PER_SECOND</p> |
|MongoDB sharded cluster |MongoDB cluster: Operations: delete |<p>The number of delete operations the mongos instance per second.</p> |DEPENDENT |mongodb.opcounters.delete.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.opcounters.delete`</p><p>- CHANGE_PER_SECOND</p> |
|MongoDB sharded cluster |MongoDB cluster: Operations: update, rate |<p>The number of update operations the mongos instance per second.</p> |DEPENDENT |mongodb.opcounters.update.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.opcounters.update`</p><p>- CHANGE_PER_SECOND</p> |
|MongoDB sharded cluster |MongoDB cluster: Operations: query, rate |<p>The number of queries received the mongos instance per second.</p> |DEPENDENT |mongodb.opcounters.query.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.opcounters.query`</p><p>- CHANGE_PER_SECOND</p> |
|MongoDB sharded cluster |MongoDB cluster: Operations: insert, rate |<p>The number of insert operations received the mongos instance per second.</p> |DEPENDENT |mongodb.opcounters.insert.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.opcounters.insert`</p><p>- CHANGE_PER_SECOND</p> |
|MongoDB sharded cluster |MongoDB cluster: Operations: getmore, rate |<p>"The number of “getmore” operations the mongos per second. This counter can be high even if the query count is low.</p><p>Secondary nodes send getMore operations as part of the replication process."</p> |DEPENDENT |mongodb.opcounters.getmore.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.opcounters.getmore`</p><p>- CHANGE_PER_SECOND</p> |
|MongoDB sharded cluster |MongoDB cluster: Last seen configserver |<p>The latest optime of the CSRS primary that the mongos has seen.</p> |DEPENDENT |mongodb.last_seen_config_server<p>**Preprocessing**:</p><p>- JAVASCRIPT: `data = JSON.parse(value) return data.sharding.lastSeenConfigServerOpTime.ts/Math.pow(2,32) `</p> |
|MongoDB sharded cluster |MongoDB cluster: Configserver heartbeat |<p>Difference between the latest optime of the CSRS primary that the mongos has seen and cluster time.</p> |DEPENDENT |mongodb.config_server_heartbeat<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|MongoDB sharded cluster |MongoDB cluster: Bytes in, rate |<p>The total number of bytes that the server has received over network connections initiated by clients or other mongod/mongos instances per second.</p> |DEPENDENT |mongodb.network.bytes_in.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.network.bytesIn`</p><p>- CHANGE_PER_SECOND</p> |
|MongoDB sharded cluster |MongoDB cluster: Bytes out, rate |<p>The total number of bytes that the server has sent over network connections initiated by clients or other mongod/mongos instances per second.</p> |DEPENDENT |mongodb.network.bytes_out.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.network.bytesOut`</p><p>- CHANGE_PER_SECOND</p> |
|MongoDB sharded cluster |MongoDB cluster: Requests, rate |<p>Number of distinct requests that the server has received per second</p> |DEPENDENT |mongodb.network.numRequests.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.network.numRequests`</p><p>- CHANGE_PER_SECOND</p> |
|MongoDB sharded cluster |MongoDB cluster: Connections, current |<p>"The number of incoming connections from clients to the database server.</p><p>This number includes the current shell session"</p> |DEPENDENT |mongodb.connections.current<p>**Preprocessing**:</p><p>- JSONPATH: `$.connections.current`</p> |
|MongoDB sharded cluster |MongoDB cluster: New connections, rate |<p>"Rate of all incoming connections created to the server."</p> |DEPENDENT |mongodb.connections.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.connections.totalCreated`</p><p>- CHANGE_PER_SECOND</p> |
|MongoDB sharded cluster |MongoDB cluster: Connections, active |<p>"The number of active client connections to the server.</p><p>Active client connections refers to client connections that currently have operations in progress.</p><p>Available starting in  4.0.7, 0 for older versions."</p> |DEPENDENT |mongodb.connections.active<p>**Preprocessing**:</p><p>- JSONPATH: `$.connections.active`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|MongoDB sharded cluster |MongoDB cluster: Connections, available |<p>"The number of unused incoming connections available."</p> |DEPENDENT |mongodb.connections.available<p>**Preprocessing**:</p><p>- JSONPATH: `$.connections.available`</p> |
|MongoDB sharded cluster |MongoDB cluster: Connection pool: client connections |<p>The number of active and stored outgoing synchronous connections from the current mongos instance to other members of the sharded cluster.</p> |DEPENDENT |mongodb.connection_pool.client<p>**Preprocessing**:</p><p>- JSONPATH: `$.numClientConnections`</p> |
|MongoDB sharded cluster |MongoDB cluster: Connection pool: scoped |<p>Number of active and stored outgoing scoped synchronous connections from the current mongos instance to other members of the sharded cluster.</p> |DEPENDENT |mongodb.connection_pool.scoped<p>**Preprocessing**:</p><p>- JSONPATH: `$.numAScopedConnections`</p> |
|MongoDB sharded cluster |MongoDB cluster: Connection pool: created, rate |<p>The total number of outgoing connections created per second by the current mongos instance to other members of the sharded cluster.</p> |DEPENDENT |mongodb.connection_pool.created.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.totalCreated`</p><p>- CHANGE_PER_SECOND</p> |
|MongoDB sharded cluster |MongoDB cluster: Connection pool: available |<p>The total number of available outgoing connections from the current mongos instance to other members of the sharded cluster.</p> |DEPENDENT |mongodb.connection_pool.available<p>**Preprocessing**:</p><p>- JSONPATH: `$.totalAvailable`</p> |
|MongoDB sharded cluster |MongoDB cluster: Connection pool: in use |<p>Reports the total number of outgoing connections from the current mongos instance to other members of the sharded cluster set that are currently in use.</p> |DEPENDENT |mongodb.connection_pool.in_use<p>**Preprocessing**:</p><p>- JSONPATH: `$.totalInUse`</p> |
|MongoDB sharded cluster |MongoDB cluster: Connection pool: refreshing |<p>Reports the total number of outgoing connections from the current mongos instance to other members of the sharded cluster that are currently being refreshed.</p> |DEPENDENT |mongodb.connection_pool.refreshing<p>**Preprocessing**:</p><p>- JSONPATH: `$.totalRefreshing`</p> |
|MongoDB sharded cluster |MongoDB cluster: Cursor: open no timeout |<p>Number of open cursors with the option DBQuery.Option.noTimeout set to prevent timeout after a period of inactivity.</p> |DEPENDENT |mongodb.metrics.cursor.open.no_timeout<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cursor.open.noTimeout`</p> |
|MongoDB sharded cluster |MongoDB cluster: Cursor: open pinned |<p>Number of pinned open cursors.</p> |DEPENDENT |mongodb.cursor.open.pinned<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cursor.open.pinned`</p> |
|MongoDB sharded cluster |MongoDB cluster: Cursor: open total |<p>Number of cursors that MongoDB is maintaining for clients.</p> |DEPENDENT |mongodb.cursor.open.total<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cursor.open.total`</p> |
|MongoDB sharded cluster |MongoDB cluster: Cursor: timed out, rate |<p>Number of cursors that time out, per second.</p> |DEPENDENT |mongodb.cursor.timed_out.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$.metrics.cursor.timedOut`</p><p>- CHANGE_PER_SECOND</p> |
|MongoDB sharded cluster |MongoDB cluster: Architecture |<p>A number, either 64 or 32, that indicates whether the MongoDB instance is compiled for 64-bit or 32-bit architecture.</p> |DEPENDENT |mongodb.mem.bits<p>**Preprocessing**:</p><p>- JSONPATH: `$.mem.bits`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|MongoDB sharded cluster |MongoDB cluster: Memory: resident |<p>Amount of memory currently used by the database process.</p> |DEPENDENT |mongodb.mem.resident<p>**Preprocessing**:</p><p>- JSONPATH: `$.mem.resident`</p><p>- MULTIPLIER: `1048576`</p> |
|MongoDB sharded cluster |MongoDB cluster: Memory: virtual |<p>Amount of virtual memory used by the mongos process.</p> |DEPENDENT |mongodb.mem.virtual<p>**Preprocessing**:</p><p>- JSONPATH: `$.mem.virtual`</p><p>- MULTIPLIER: `1048576`</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}: Objects, avg size |<p>The average size of each document in bytes.</p> |DEPENDENT |mongodb.db.size["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.avgObjSize`</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}: Size, data |<p>Total size of the data held in this database including the padding factor.</p> |DEPENDENT |mongodb.db.data_size["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.dataSize`</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}: Size, file |<p>Total size of the data held in this database including the padding factor (only available with the mmapv1 storage engine).</p> |DEPENDENT |mongodb.db.file_size["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.fileSize`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}: Size, index |<p>Total size of all indexes created on this database.</p> |DEPENDENT |mongodb.db.index_size["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.indexSize`</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}: Size, storage |<p>Total amount of space allocated to collections in this database for document storage.</p> |DEPENDENT |mongodb.db.storage_size["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.storageSize`</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}: Objects, count |<p>Number of objects (documents) in the database across all collections.</p> |DEPENDENT |mongodb.db.objects["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.objects`</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}: Extents |<p>Contains a count of the number of extents in the database across all collections.</p> |DEPENDENT |mongodb.db.extents["{#DBNAME}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.numExtents`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}.{#COLLECTION}: Size |<p>The total size in bytes of the data in the collection plus the size of every indexes on the mongodb.collection.</p> |DEPENDENT |mongodb.collection.size["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.size`</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}.{#COLLECTION}: Objects, avg size |<p>The size of the average object in the collection in bytes.</p> |DEPENDENT |mongodb.collection.avg_obj_size["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.avgObjSize`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}.{#COLLECTION}: Objects, count |<p>Total number of objects in the collection.</p> |DEPENDENT |mongodb.collection.count["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.count`</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}.{#COLLECTION}: Capped, max number |<p>Maximum number of documents in a capped collection.</p> |DEPENDENT |mongodb.collection.max["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.max`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}.{#COLLECTION}: Capped, max size |<p>Maximum size of a capped collection in bytes.</p> |DEPENDENT |mongodb.collection.max_size["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.maxSize`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}.{#COLLECTION}: Storage size |<p>Total storage space allocated to this collection for document storage.</p> |DEPENDENT |mongodb.collection.storage_size["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.storageSize`</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}.{#COLLECTION}: Indexes |<p>Total number of indices on the collection.</p> |DEPENDENT |mongodb.collection.nindexes["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.nindexes`</p> |
|MongoDB sharded cluster |MongoDB {#DBNAME}.{#COLLECTION}: Capped |<p>Whether or not the collection is capped.</p> |DEPENDENT |mongodb.collection.capped["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**:</p><p>- JSONPATH: `$.capped`</p><p>- BOOL_TO_DECIMAL</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|Zabbix raw items |MongoDB cluster: Get server status |<p>The mongos statistic</p> |ZABBIX_PASSIVE |mongodb.server.status["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"] |
|Zabbix raw items |MongoDB cluster: Get mongodb.connpool.stats |<p>Returns current info about connpool.stats.</p> |ZABBIX_PASSIVE |mongodb.connpool.stats["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"] |
|Zabbix raw items |MongoDB {#DBNAME}: Get db stats {#DBNAME} |<p>Returns statistics reflecting the database system's state.</p> |ZABBIX_PASSIVE |mongodb.db.stats["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}","{#DBNAME}"] |
|Zabbix raw items |MongoDB {#DBNAME}.{#COLLECTION}: Get collection stats {#DBNAME}.{#COLLECTION} |<p>Returns a variety of storage statistics for a given collection.</p> |ZABBIX_PASSIVE |mongodb.collection.stats["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}","{#DBNAME}","{#COLLECTION}"] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|MongoDB cluster: Connection to mongos proxy is unavailable |<p>Connection to mongos proxy instance is currently unavailable.</p> |`last(/MongoDB cluster by Zabbix agent 2/mongodb.ping["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"])=0` |HIGH | |
|MongoDB cluster: Version has changed |<p>MongoDB cluster version has changed. Ack to close.</p> |`last(/MongoDB cluster by Zabbix agent 2/mongodb.version,#1)<>last(/MongoDB cluster by Zabbix agent 2/mongodb.version,#2) and length(last(/MongoDB cluster by Zabbix agent 2/mongodb.version))>0` |INFO |<p>Manual close: YES</p> |
|MongoDB cluster: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/MongoDB cluster by Zabbix agent 2/mongodb.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|MongoDB cluster: Failed to fetch info data |<p>Zabbix has not received data for items for the last 10 minutes</p> |`nodata(/MongoDB cluster by Zabbix agent 2/mongodb.uptime,10m)=1` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- MongoDB cluster: Connection to mongos proxy is unavailable</p> |
|MongoDB cluster: Available connections is low |<p>"Too few available connections.</p><p>Consider this value in combination with the value of connections current to understand the connection load on the database"</p> |`max(/MongoDB cluster by Zabbix agent 2/mongodb.connections.available,5m)<{$MONGODB.CONNS.AVAILABLE.MIN.WARN}` |WARNING | |
|MongoDB cluster: Too many cursors opened by MongoDB for clients |<p>-</p> |`min(/MongoDB cluster by Zabbix agent 2/mongodb.cursor.open.total,5m)>{$MONGODB.CURSOR.OPEN.MAX.WARN}` |WARNING | |
|MongoDB cluster: Too many cursors are timing out |<p>-</p> |`min(/MongoDB cluster by Zabbix agent 2/mongodb.cursor.timed_out.rate,5m)>{$MONGODB.CURSOR.TIMEOUT.MAX.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/420659-discussion-thread-for-official-zabbix-template-db-mongodb).

