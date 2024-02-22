
# MongoDB cluster by Zabbix agent 2

## Overview

The template to monitor MongoDB sharded cluster by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

`MongoDB cluster by Zabbix agent 2` — collects metrics from mongos proxy(router) by polling zabbix-agent2.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- MongoDB 4.0.21, 4.4.3

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Setup and configure zabbix-agent2 compiled with the MongoDB monitoring plugin.
2. Set the {$MONGODB.CONNSTRING} such as <protocol(host:port)> or named session of mongos proxy(router).
3. Set the user name and password in host macros ({$MONGODB.USER}, {$MONGODB.PASSWORD}) if you want to override parameters from the Zabbix agent configuration file.

**Note**, depending on the number of DBs and collections discovery operation may be expensive. Use filters with macros {$MONGODB.LLD.FILTER.DB.MATCHES}, {$MONGODB.LLD.FILTER.DB.NOT_MATCHES}, {$MONGODB.LLD.FILTER.COLLECTION.MATCHES}, {$MONGODB.LLD.FILTER.COLLECTION.NOT_MATCHES}.

All sharded Mongodb nodes (mongod) will be discovered with attached template "MongoDB node by Zabbix agent 2".


Test availability: `zabbix_get -s mongos.node -k 'mongodb.ping["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]"`

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MONGODB.CONNSTRING}|<p>Connection string in the URI format (password is not used). This param overwrites a value configured in the "Server" option of the configuration file (if it's set), otherwise, the plugin's default value is used: "tcp://localhost:27017"</p>|`tcp://localhost:27017`|
|{$MONGODB.USER}|<p>MongoDB username</p>||
|{$MONGODB.PASSWORD}|<p>MongoDB user password</p>||
|{$MONGODB.CONNS.AVAILABLE.MIN.WARN}|<p>Minimum number of available connections</p>|`1000`|
|{$MONGODB.LLD.FILTER.COLLECTION.MATCHES}|<p>Filter of discoverable collections</p>|`.*`|
|{$MONGODB.LLD.FILTER.COLLECTION.NOT_MATCHES}|<p>Filter to exclude discovered collections</p>|`CHANGE_IF_NEEDED`|
|{$MONGODB.LLD.FILTER.DB.MATCHES}|<p>Filter of discoverable databases</p>|`.*`|
|{$MONGODB.LLD.FILTER.DB.NOT_MATCHES}|<p>Filter to exclude discovered databases</p>|`(admin\|config\|local)`|
|{$MONGODB.CURSOR.TIMEOUT.MAX.WARN}|<p>Maximum number of cursors timing out per second</p>|`1`|
|{$MONGODB.CURSOR.OPEN.MAX.WARN}|<p>Maximum number of open cursors</p>|`10000`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MongoDB cluster: Get server status|<p>The mongos statistic</p>|Zabbix agent|mongodb.server.status["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]|
|MongoDB cluster: Get mongodb.connpool.stats|<p>Returns current info about connpool.stats.</p>|Zabbix agent|mongodb.connpool.stats["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]|
|MongoDB cluster: Ping|<p>Test if a connection is alive or not.</p>|Zabbix agent|mongodb.ping["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|MongoDB cluster: Jumbo chunks|<p>Total number of 'jumbo' chunks in the mongo cluster.</p>|Zabbix agent|mongodb.jumbo_chunks.count["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]|
|MongoDB cluster: Mongos version|<p>Version of the Mongos server</p>|Dependent item|mongodb.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|MongoDB cluster: Uptime|<p>Number of seconds since the Mongos server start.</p>|Dependent item|mongodb.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime`</p></li></ul>|
|MongoDB cluster: Operations: command|<p>The number of commands issued to the database per second.</p><p>Counts all commands except the write commands: insert, update, and delete.</p>|Dependent item|mongodb.opcounters.command.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.opcounters.command`</p></li><li>Change per second</li></ul>|
|MongoDB cluster: Operations: delete|<p>The number of delete operations the mongos instance per second.</p>|Dependent item|mongodb.opcounters.delete.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.opcounters.delete`</p></li><li>Change per second</li></ul>|
|MongoDB cluster: Operations: update, rate|<p>The number of update operations the mongos instance per second.</p>|Dependent item|mongodb.opcounters.update.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.opcounters.update`</p></li><li>Change per second</li></ul>|
|MongoDB cluster: Operations: query, rate|<p>The number of queries received the mongos instance per second.</p>|Dependent item|mongodb.opcounters.query.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.opcounters.query`</p></li><li>Change per second</li></ul>|
|MongoDB cluster: Operations: insert, rate|<p>The number of insert operations received the mongos instance per second.</p>|Dependent item|mongodb.opcounters.insert.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.opcounters.insert`</p></li><li>Change per second</li></ul>|
|MongoDB cluster: Operations: getmore, rate|<p>The number of "getmore" operations the mongos per second. This counter can be high even if the query count is low.</p><p>Secondary nodes send getMore operations as part of the replication process.</p>|Dependent item|mongodb.opcounters.getmore.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.opcounters.getmore`</p></li><li>Change per second</li></ul>|
|MongoDB cluster: Last seen configserver|<p>The latest optime of the CSRS primary that the mongos has seen.</p>|Dependent item|mongodb.last_seen_config_server<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sharding.lastSeenConfigServerOpTime.ts.T`</p></li></ul>|
|MongoDB cluster: Configserver heartbeat|<p>Difference between the latest optime of the CSRS primary that the mongos has seen and cluster time.</p>|Dependent item|mongodb.config_server_heartbeat<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|MongoDB cluster: Bytes in, rate|<p>The total number of bytes that the server has received over network connections initiated by clients or other mongod/mongos instances per second.</p>|Dependent item|mongodb.network.bytes_in.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.network.bytesIn`</p></li><li>Change per second</li></ul>|
|MongoDB cluster: Bytes out, rate|<p>The total number of bytes that the server has sent over network connections initiated by clients or other mongod/mongos instances per second.</p>|Dependent item|mongodb.network.bytes_out.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.network.bytesOut`</p></li><li>Change per second</li></ul>|
|MongoDB cluster: Requests, rate|<p>Number of distinct requests that the server has received per second.</p>|Dependent item|mongodb.network.numRequests.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.network.numRequests`</p></li><li>Change per second</li></ul>|
|MongoDB cluster: Connections, current|<p>The number of incoming connections from clients to the database server.</p><p>This number includes the current shell session.</p>|Dependent item|mongodb.connections.current<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connections.current`</p></li></ul>|
|MongoDB cluster: New connections, rate|<p>"Rate of all incoming connections created to the server."</p>|Dependent item|mongodb.connections.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connections.totalCreated`</p></li><li>Change per second</li></ul>|
|MongoDB cluster: Connections, active|<p>The number of active client connections to the server.</p><p>Active client connections refers to client connections that currently have operations in progress.</p><p>Available starting in  4.0.7, 0 for older versions.</p>|Dependent item|mongodb.connections.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connections.active`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|MongoDB cluster: Connections, available|<p>"The number of unused incoming connections available."</p>|Dependent item|mongodb.connections.available<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connections.available`</p></li></ul>|
|MongoDB cluster: Connection pool: client connections|<p>The number of active and stored outgoing synchronous connections from the current mongos instance to other members of the sharded cluster.</p>|Dependent item|mongodb.connection_pool.client<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numClientConnections`</p></li></ul>|
|MongoDB cluster: Connection pool: scoped|<p>Number of active and stored outgoing scoped synchronous connections from the current mongos instance to other members of the sharded cluster.</p>|Dependent item|mongodb.connection_pool.scoped<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numAScopedConnections`</p></li></ul>|
|MongoDB cluster: Connection pool: created, rate|<p>The total number of outgoing connections created per second by the current mongos instance to other members of the sharded cluster.</p>|Dependent item|mongodb.connection_pool.created.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalCreated`</p></li><li>Change per second</li></ul>|
|MongoDB cluster: Connection pool: available|<p>The total number of available outgoing connections from the current mongos instance to other members of the sharded cluster.</p>|Dependent item|mongodb.connection_pool.available<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalAvailable`</p></li></ul>|
|MongoDB cluster: Connection pool: in use|<p>Reports the total number of outgoing connections from the current mongos instance to other members of the sharded cluster set that are currently in use.</p>|Dependent item|mongodb.connection_pool.in_use<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalInUse`</p></li></ul>|
|MongoDB cluster: Connection pool: refreshing|<p>Reports the total number of outgoing connections from the current mongos instance to other members of the sharded cluster that are currently being refreshed.</p>|Dependent item|mongodb.connection_pool.refreshing<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totalRefreshing`</p></li></ul>|
|MongoDB cluster: Cursor: open no timeout|<p>Number of open cursors with the option DBQuery.Option.noTimeout set to prevent timeout after a period of inactivity.</p>|Dependent item|mongodb.metrics.cursor.open.no_timeout<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cursor.open.noTimeout`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|MongoDB cluster: Cursor: open pinned|<p>Number of pinned open cursors.</p>|Dependent item|mongodb.cursor.open.pinned<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cursor.open.pinned`</p></li></ul>|
|MongoDB cluster: Cursor: open total|<p>Number of cursors that MongoDB is maintaining for clients.</p>|Dependent item|mongodb.cursor.open.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cursor.open.total`</p></li></ul>|
|MongoDB cluster: Cursor: timed out, rate|<p>Number of cursors that time out, per second.</p>|Dependent item|mongodb.cursor.timed_out.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cursor.timedOut`</p></li><li>Change per second</li></ul>|
|MongoDB cluster: Architecture|<p>A number, either 64 or 32, that indicates whether the MongoDB instance is compiled for 64-bit or 32-bit architecture.</p>|Dependent item|mongodb.mem.bits<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem.bits`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|MongoDB cluster: Memory: resident|<p>Amount of memory currently used by the database process.</p>|Dependent item|mongodb.mem.resident<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem.resident`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|MongoDB cluster: Memory: virtual|<p>Amount of virtual memory used by the mongos process.</p>|Dependent item|mongodb.mem.virtual<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem.virtual`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MongoDB cluster: Connection to mongos proxy is unavailable|<p>Connection to mongos proxy instance is currently unavailable.</p>|`last(/MongoDB cluster by Zabbix agent 2/mongodb.ping["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"])=0`|High||
|MongoDB cluster: Version has changed|<p>MongoDB cluster version has changed. Acknowledge to close the problem manually.</p>|`last(/MongoDB cluster by Zabbix agent 2/mongodb.version,#1)<>last(/MongoDB cluster by Zabbix agent 2/mongodb.version,#2) and length(last(/MongoDB cluster by Zabbix agent 2/mongodb.version))>0`|Info|**Manual close**: Yes|
|MongoDB cluster: Mongos server has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/MongoDB cluster by Zabbix agent 2/mongodb.uptime)<10m`|Info|**Manual close**: Yes|
|MongoDB cluster: Failed to fetch info data|<p>Zabbix has not received data for items for the last 10 minutes</p>|`nodata(/MongoDB cluster by Zabbix agent 2/mongodb.uptime,10m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>MongoDB cluster: Connection to mongos proxy is unavailable</li></ul>|
|MongoDB cluster: Available connections is low|<p>Too few available connections.<br>Consider this value in combination with the value of connections current to understand the connection load on the database.</p>|`max(/MongoDB cluster by Zabbix agent 2/mongodb.connections.available,5m)<{$MONGODB.CONNS.AVAILABLE.MIN.WARN}`|Warning||
|MongoDB cluster: Too many cursors opened by MongoDB for clients||`min(/MongoDB cluster by Zabbix agent 2/mongodb.cursor.open.total,5m)>{$MONGODB.CURSOR.OPEN.MAX.WARN}`|Warning||
|MongoDB cluster: Too many cursors are timing out||`min(/MongoDB cluster by Zabbix agent 2/mongodb.cursor.timed_out.rate,5m)>{$MONGODB.CURSOR.TIMEOUT.MAX.WARN}`|Warning||

### LLD rule Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Database discovery|<p>Collect database metrics.</p><p>Note, depending on the number of DBs this discovery operation may be expensive. Use filters with macros {$MONGODB.LLD.FILTER.DB.MATCHES}, {$MONGODB.LLD.FILTER.DB.NOT_MATCHES}.</p>|Zabbix agent|mongodb.db.discovery["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]|

### Item prototypes for Database discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MongoDB {#DBNAME}: Get db stats {#DBNAME}|<p>Returns statistics reflecting the database system's state.</p>|Zabbix agent|mongodb.db.stats["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}","{#DBNAME}"]|
|MongoDB {#DBNAME}: Objects, avg size|<p>The average size of each document in bytes.</p>|Dependent item|mongodb.db.size["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.avgObjSize`</p></li></ul>|
|MongoDB {#DBNAME}: Size, data|<p>Total size of the data held in this database including the padding factor.</p>|Dependent item|mongodb.db.data_size["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.dataSize`</p></li></ul>|
|MongoDB {#DBNAME}: Size, file|<p>Total size of the data held in this database including the padding factor (only available with the mmapv1 storage engine).</p>|Dependent item|mongodb.db.file_size["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fileSize`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|MongoDB {#DBNAME}: Size, index|<p>Total size of all indexes created on this database.</p>|Dependent item|mongodb.db.index_size["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.indexSize`</p></li></ul>|
|MongoDB {#DBNAME}: Size, storage|<p>Total amount of space allocated to collections in this database for document storage.</p>|Dependent item|mongodb.db.storage_size["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.storageSize`</p></li></ul>|
|MongoDB {#DBNAME}: Objects, count|<p>Number of objects (documents) in the database across all collections.</p>|Dependent item|mongodb.db.objects["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.objects`</p></li></ul>|
|MongoDB {#DBNAME}: Extents|<p>Contains a count of the number of extents in the database across all collections.</p>|Dependent item|mongodb.db.extents["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numExtents`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Collection discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Collection discovery|<p>Collect collections metrics.</p><p>Note, depending on the number of DBs and collections this discovery operation may be expensive. Use filters with macros {$MONGODB.LLD.FILTER.DB.MATCHES}, {$MONGODB.LLD.FILTER.DB.NOT_MATCHES}, {$MONGODB.LLD.FILTER.COLLECTION.MATCHES}, {$MONGODB.LLD.FILTER.COLLECTION.NOT_MATCHES}.</p>|Zabbix agent|mongodb.collections.discovery["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]|

### Item prototypes for Collection discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MongoDB {#DBNAME}.{#COLLECTION}: Get collection stats {#DBNAME}.{#COLLECTION}|<p>Returns a variety of storage statistics for a given collection.</p>|Zabbix agent|mongodb.collection.stats["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}","{#DBNAME}","{#COLLECTION}"]|
|MongoDB {#DBNAME}.{#COLLECTION}: Size|<p>The total size in bytes of the data in the collection plus the size of every indexes on the mongodb.collection.</p>|Dependent item|mongodb.collection.size["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.size`</p></li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Objects, avg size|<p>The size of the average object in the collection in bytes.</p>|Dependent item|mongodb.collection.avg_obj_size["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.avgObjSize`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Objects, count|<p>Total number of objects in the collection.</p>|Dependent item|mongodb.collection.count["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.count`</p></li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Capped, max number|<p>Maximum number of documents in a capped collection.</p>|Dependent item|mongodb.collection.max["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.max`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Capped, max size|<p>Maximum size of a capped collection in bytes.</p>|Dependent item|mongodb.collection.max_size["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxSize`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Storage size|<p>Total storage space allocated to this collection for document storage.</p>|Dependent item|mongodb.collection.storage_size["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.storageSize`</p></li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Indexes|<p>Total number of indices on the collection.</p>|Dependent item|mongodb.collection.nindexes["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.nindexes`</p></li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Capped|<p>Whether or not the collection is capped.</p>|Dependent item|mongodb.collection.capped["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.capped`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### LLD rule Shards discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Shards discovery|<p>Discovery shared cluster hosts.</p>|Zabbix agent|mongodb.sh.discovery["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]|

### LLD rule Config servers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Config servers discovery|<p>Discovery shared cluster config servers.</p>|Zabbix agent|mongodb.cfg.discovery["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

