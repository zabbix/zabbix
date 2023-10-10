
# MongoDB node by Zabbix agent 2

## Overview

The template to monitor single MongoDB server by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

`MongoDB node by Zabbix Agent 2` — collects metrics by polling zabbix-agent2.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- MongoDB 4.0.21, 4.4.3

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Setup and configure zabbix-agent2 compiled with the MongoDB monitoring plugin.
2. Set the {$MONGODB.CONNSTRING} such as <protocol(host:port)> or named session.
3. Set the user name and password in host macros ({$MONGODB.USER}, {$MONGODB.PASSWORD}) if you want to override parameters from the Zabbix agent configuration file.

**Note**, depending on the number of DBs and collections discovery operation may be expensive. Use filters with macros {$MONGODB.LLD.FILTER.DB.MATCHES}, {$MONGODB.LLD.FILTER.DB.NOT_MATCHES}, {$MONGODB.LLD.FILTER.COLLECTION.MATCHES}, {$MONGODB.LLD.FILTER.COLLECTION.NOT_MATCHES}.

Test availability: `zabbix_get -s mongodb.node -k 'mongodb.ping["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]"`


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$MONGODB.CONNSTRING}|<p>Connection string in the URI format (password is not used). This param overwrites a value configured in the "Server" option of the configuration file (if it's set), otherwise, the plugin's default value is used: "tcp://localhost:27017"</p>|`tcp://localhost:27017`|
|{$MONGODB.USER}|<p>MongoDB username</p>||
|{$MONGODB.PASSWORD}|<p>MongoDB user password</p>||
|{$MONGODB.CONNS.PCT.USED.MAX.WARN}|<p>Maximum percentage of used connections</p>|`80`|
|{$MONGODB.CURSOR.TIMEOUT.MAX.WARN}|<p>Maximum number of cursors timing out per second</p>|`1`|
|{$MONGODB.CURSOR.OPEN.MAX.WARN}|<p>Maximum number of open cursors</p>|`10000`|
|{$MONGODB.REPL.LAG.MAX.WARN}|<p>Maximum replication lag in seconds</p>|`10s`|
|{$MONGODB.LLD.FILTER.COLLECTION.MATCHES}|<p>Filter of discoverable collections</p>|`.*`|
|{$MONGODB.LLD.FILTER.COLLECTION.NOT_MATCHES}|<p>Filter to exclude discovered collections</p>|`CHANGE_IF_NEEDED`|
|{$MONGODB.LLD.FILTER.DB.MATCHES}|<p>Filter of discoverable databases</p>|`.*`|
|{$MONGODB.LLD.FILTER.DB.NOT_MATCHES}|<p>Filter to exclude discovered databases</p>|`(admin\|config\|local)`|
|{$MONGODB.WIRED_TIGER.TICKETS.AVAILABLE.MIN.WARN}|<p>Minimum number of available WiredTiger read or write tickets remaining</p>|`5`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MongoDB: Get server status|<p>Returns a database's state.</p>|Zabbix agent|mongodb.server.status["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]|
|MongoDB: Get Replica Set status|<p>Returns the replica set status from the point of view of the member where the method is run.</p>|Zabbix agent|mongodb.rs.status["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]|
|MongoDB: Get oplog stats|<p>Returns status of the replica set, using data polled from the oplog.</p>|Zabbix agent|mongodb.oplog.stats["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]|
|MongoDB: Ping|<p>Test if a connection is alive or not.</p>|Zabbix agent|mongodb.ping["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|MongoDB: Get collections usage stats|<p>Returns usage statistics for each collection.</p>|Zabbix agent|mongodb.collections.usage["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"]|
|MongoDB: MongoDB version|<p>Version of the MongoDB server.</p>|Dependent item|mongodb.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.version`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|MongoDB: Uptime|<p>Number of seconds that the mongod process has been active.</p>|Dependent item|mongodb.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime`</p></li></ul>|
|MongoDB: Asserts: message, rate|<p>The number of message assertions raised per second.</p><p>Check the log file for more information about these messages.</p>|Dependent item|mongodb.asserts.msg.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.asserts.msg`</p></li><li>Change per second</li></ul>|
|MongoDB: Asserts: user, rate|<p>The number of "user asserts" that have occurred per second.</p><p>These are errors that user may generate, such as out of disk space or duplicate key.</p>|Dependent item|mongodb.asserts.user.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.asserts.user`</p></li><li>Change per second</li></ul>|
|MongoDB: Asserts: warning, rate|<p>The number of warnings raised per second.</p>|Dependent item|mongodb.asserts.warning.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.asserts.warning`</p></li><li>Change per second</li></ul>|
|MongoDB: Asserts: regular, rate|<p>The number of regular assertions raised per second.</p><p>Check the log file for more information about these messages.</p>|Dependent item|mongodb.asserts.regular.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.asserts.regular`</p></li><li>Change per second</li></ul>|
|MongoDB: Asserts: rollovers, rate|<p>Number of times that the rollover counters roll over per second.</p><p>The counters rollover to zero every 2^30 assertions.</p>|Dependent item|mongodb.asserts.rollovers.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.asserts.rollovers`</p></li><li>Change per second</li></ul>|
|MongoDB: Active clients: writers|<p>The number of active client connections performing write operations.</p>|Dependent item|mongodb.active_clients.writers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.globalLock.activeClients.writers`</p></li></ul>|
|MongoDB: Active clients: readers|<p>The number of the active client connections performing read operations.</p>|Dependent item|mongodb.active_clients.readers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.globalLock.activeClients.readers`</p></li></ul>|
|MongoDB: Active clients: total|<p>The total number of internal client connections to the database including system threads as well as queued readers and writers.</p>|Dependent item|mongodb.active_clients.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.globalLock.activeClients.total`</p></li></ul>|
|MongoDB: Current queue: writers|<p>The number of operations that are currently queued and waiting for the write lock.</p><p> A consistently small write-queue, particularly of shorter operations, is no cause for concern.</p>|Dependent item|mongodb.current_queue.writers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.globalLock.currentQueue.writers`</p></li></ul>|
|MongoDB: Current queue: readers|<p>The number of operations that are currently queued and waiting for the read lock.</p><p>A consistently small read-queue, particularly of shorter operations, should cause no concern.</p>|Dependent item|mongodb.current_queue.readers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.globalLock.currentQueue.readers`</p></li></ul>|
|MongoDB: Current queue: total|<p>The total number of operations queued waiting for the lock.</p>|Dependent item|mongodb.current_queue.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.globalLock.currentQueue.total`</p></li></ul>|
|MongoDB: Operations: command, rate|<p>The number of commands issued to the database the mongod instance per second.</p><p>Counts all commands except the write commands: insert, update, and delete.</p>|Dependent item|mongodb.opcounters.command.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.opcounters.command`</p></li><li>Change per second</li></ul>|
|MongoDB: Operations: delete, rate|<p>The number of delete operations the mongod instance per second.</p>|Dependent item|mongodb.opcounters.delete.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.opcounters.delete`</p></li><li>Change per second</li></ul>|
|MongoDB: Operations: update, rate|<p>The number of update operations the mongod instance per second.</p>|Dependent item|mongodb.opcounters.update.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.opcounters.update`</p></li><li>Change per second</li></ul>|
|MongoDB: Operations: query, rate|<p>The number of queries received the mongod instance per second.</p>|Dependent item|mongodb.opcounters.query.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.opcounters.query`</p></li><li>Change per second</li></ul>|
|MongoDB: Operations: insert, rate|<p>The number of insert operations received since the mongod instance per second.</p>|Dependent item|mongodb.opcounters.insert.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.opcounters.insert`</p></li><li>Change per second</li></ul>|
|MongoDB: Operations: getmore, rate|<p>The number of "getmore" operations since the mongod instance per second. This counter can be high even if the query count is low.</p><p>Secondary nodes send getMore operations as part of the replication process.</p>|Dependent item|mongodb.opcounters.getmore.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.opcounters.getmore`</p></li><li>Change per second</li></ul>|
|MongoDB: Connections, current|<p>The number of incoming connections from clients to the database server.</p><p>This number includes the current shell session.</p>|Dependent item|mongodb.connections.current<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connections.current`</p></li></ul>|
|MongoDB: New connections, rate|<p>Rate of all incoming connections created to the server.</p>|Dependent item|mongodb.connections.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connections.totalCreated`</p></li><li>Change per second</li></ul>|
|MongoDB: Connections, available|<p>The number of unused incoming connections available.</p>|Dependent item|mongodb.connections.available<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connections.available`</p></li></ul>|
|MongoDB: Connections, active|<p>The number of active client connections to the server.</p><p>Active client connections refers to client connections that currently have operations in progress.</p><p>Available starting in  4.0.7, 0 for older versions.</p>|Dependent item|mongodb.connections.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.connections.active`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|MongoDB: Bytes in, rate|<p>The total number of bytes that the server has received over network connections initiated by clients or other mongod/mongos instances per second.</p>|Dependent item|mongodb.network.bytes_in.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.network.bytesIn`</p></li><li>Change per second</li></ul>|
|MongoDB: Bytes out, rate|<p>The total number of bytes that the server has sent over network connections initiated by clients or other mongod/mongos instances per second.</p>|Dependent item|mongodb.network.bytes_out.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.network.bytesOut`</p></li><li>Change per second</li></ul>|
|MongoDB: Requests, rate|<p>Number of distinct requests that the server has received per second</p>|Dependent item|mongodb.network.numRequests.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.network.numRequests`</p></li><li>Change per second</li></ul>|
|MongoDB: Document: deleted, rate|<p>Number of documents deleted per second.</p>|Dependent item|mongod.document.deleted.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.document.deleted`</p></li><li>Change per second</li></ul>|
|MongoDB: Document: inserted, rate|<p>Number of documents inserted per second.</p>|Dependent item|mongod.document.inserted.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.document.inserted`</p></li><li>Change per second</li></ul>|
|MongoDB: Document: returned, rate|<p>Number of documents returned by queries per second.</p>|Dependent item|mongod.document.returned.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.document.returned`</p></li><li>Change per second</li></ul>|
|MongoDB: Document: updated, rate|<p>Number of documents updated per second.</p>|Dependent item|mongod.document.updated.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.document.updated`</p></li><li>Change per second</li></ul>|
|MongoDB: Cursor: open no timeout|<p>Number of open cursors with the option DBQuery.Option.noTimeout set to prevent timeout after a period of inactivity.</p>|Dependent item|mongodb.metrics.cursor.open.no_timeout<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cursor.open.noTimeout`</p></li></ul>|
|MongoDB: Cursor: open pinned|<p>Number of pinned open cursors.</p>|Dependent item|mongodb.cursor.open.pinned<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cursor.open.pinned`</p></li></ul>|
|MongoDB: Cursor: open total|<p>Number of cursors that MongoDB is maintaining for clients.</p>|Dependent item|mongodb.cursor.open.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cursor.open.total`</p></li></ul>|
|MongoDB: Cursor: timed out, rate|<p>Number of cursors that time out, per second.</p>|Dependent item|mongodb.cursor.timed_out.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.cursor.timedOut`</p></li><li>Change per second</li></ul>|
|MongoDB: Architecture|<p>A number, either 64 or 32, that indicates whether the MongoDB instance is compiled for 64-bit or 32-bit architecture.</p>|Dependent item|mongodb.mem.bits<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem.bits`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|MongoDB: Memory: mapped|<p>Amount of mapped memory by the database.</p>|Dependent item|mongodb.mem.mapped<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem.mapped`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|MongoDB: Memory: mapped with journal|<p>The amount of mapped memory, including the memory used for journaling.</p>|Dependent item|mongodb.mem.mapped_with_journal<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem.mappedWithJournal`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|MongoDB: Memory: resident|<p>Amount of memory currently used by the database process.</p>|Dependent item|mongodb.mem.resident<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem.resident`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|MongoDB: Memory: virtual|<p>Amount of virtual memory used by the mongod process.</p>|Dependent item|mongodb.mem.virtual<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem.virtual`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MongoDB: Connection to MongoDB is unavailable|<p>Connection to MongoDB instance is currently unavailable.</p>|`last(/MongoDB node by Zabbix agent 2/mongodb.ping["{$MONGODB.CONNSTRING}","{$MONGODB.USER}","{$MONGODB.PASSWORD}"])=0`|High||
|MongoDB: Version has changed|<p>MongoDB version has changed. Acknowledge to close the problem manually.</p>|`last(/MongoDB node by Zabbix agent 2/mongodb.version,#1)<>last(/MongoDB node by Zabbix agent 2/mongodb.version,#2) and length(last(/MongoDB node by Zabbix agent 2/mongodb.version))>0`|Info|**Manual close**: Yes|
|MongoDB: mongod process has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/MongoDB node by Zabbix agent 2/mongodb.uptime)<10m`|Info|**Manual close**: Yes|
|MongoDB: Failed to fetch info data|<p>Zabbix has not received data for items for the last 10 minutes</p>|`nodata(/MongoDB node by Zabbix agent 2/mongodb.uptime,10m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>MongoDB: Connection to MongoDB is unavailable</li></ul>|
|MongoDB: Total number of open connections is too high|<p>Too few available connections.<br>If MongoDB runs low on connections, in may not be able to handle incoming requests in a timely manner.</p>|`min(/MongoDB node by Zabbix agent 2/mongodb.connections.current,5m)/(last(/MongoDB node by Zabbix agent 2/mongodb.connections.available)+last(/MongoDB node by Zabbix agent 2/mongodb.connections.current))*100>{$MONGODB.CONNS.PCT.USED.MAX.WARN}`|Warning||
|MongoDB: Too many cursors opened by MongoDB for clients||`min(/MongoDB node by Zabbix agent 2/mongodb.cursor.open.total,5m)>{$MONGODB.CURSOR.OPEN.MAX.WARN}`|Warning||
|MongoDB: Too many cursors are timing out||`min(/MongoDB node by Zabbix agent 2/mongodb.cursor.timed_out.rate,5m)>{$MONGODB.CURSOR.TIMEOUT.MAX.WARN}`|Warning||

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
|MongoDB {#DBNAME}: Collections|<p>Contains a count of the number of collections in that database.</p>|Dependent item|mongodb.db.collections["{#DBNAME}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.collections`</p></li></ul>|
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
|MongoDB {#DBNAME}.{#COLLECTION}: Capped: max number|<p>Maximum number of documents that may be present in a capped collection.</p>|Dependent item|mongodb.collection.max_number["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.max`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Capped: max size|<p>Maximum size of a capped collection in bytes.</p>|Dependent item|mongodb.collection.max_size["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.maxSize`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Storage size|<p>Total storage space allocated to this collection for document storage.</p>|Dependent item|mongodb.collection.storage_size["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.storageSize`</p></li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Indexes|<p>Total number of indices on the collection.</p>|Dependent item|mongodb.collection.nindexes["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.nindexes`</p></li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Capped|<p>Whether or not the collection is capped.</p>|Dependent item|mongodb.collection.capped["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.capped`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: total, rate|<p>The number of operations per second.</p>|Dependent item|mongodb.collection.ops.total.rate["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].total.count`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Read lock, rate|<p>The number of operations per second.</p>|Dependent item|mongodb.collection.read_lock.rate["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].readLock.count`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Write lock, rate|<p>The number of operations per second.</p>|Dependent item|mongodb.collection.write_lock.rate["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].writeLock.count`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: queries, rate|<p>The number of operations per second.</p>|Dependent item|mongodb.collection.ops.queries.rate["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].queries.count`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: getmore, rate|<p>The number of operations per second.</p>|Dependent item|mongodb.collection.ops.getmore.rate["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].getmore.count`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: insert, rate|<p>The number of operations per second.</p>|Dependent item|mongodb.collection.ops.insert.rate["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].insert.count`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: update, rate|<p>The number of operations per second.</p>|Dependent item|mongodb.collection.ops.update.rate["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].update.count`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: remove, rate|<p>The number of operations per second.</p>|Dependent item|mongodb.collection.ops.remove.rate["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].remove.count`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: commands, rate|<p>The number of operations per second.</p>|Dependent item|mongodb.collection.ops.commands.rate["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].commands.count`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: total, ms/s|<p>Fraction of time (ms/s) the mongod has spent to operations.</p>|Dependent item|mongodb.collection.ops.total.ms["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].total.time`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Read lock, ms/s|<p>Fraction of time (ms/s) the mongod has spent to operations.</p>|Dependent item|mongodb.collection.read_lock.ms["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].readLock.time`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Write lock, ms/s|<p>Fraction of time (ms/s) the mongod has spent to operations.</p>|Dependent item|mongodb.collection.write_lock.ms["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].writeLock.time`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: queries, ms/s|<p>Fraction of time (ms/s) the mongod has spent to operations.</p>|Dependent item|mongodb.collection.ops.queries.ms["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].queries.time`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: getmore, ms/s|<p>Fraction of time (ms/s) the mongod has spent to operations.</p>|Dependent item|mongodb.collection.ops.getmore.ms["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].getmore.time`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: insert, ms/s|<p>Fraction of time (ms/s) the mongod has spent to operations.</p>|Dependent item|mongodb.collection.ops.insert.ms["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].insert.time`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: update, ms/s|<p>Fraction of time (ms/s) the mongod has spent to operations.</p>|Dependent item|mongodb.collection.ops.update.ms["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].update.time`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: remove, ms/s|<p>Fraction of time (ms/s) the mongod has spent to operations.</p>|Dependent item|mongodb.collection.ops.remove.ms["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].remove.time`</p></li><li>Change per second</li></ul>|
|MongoDB {#DBNAME}.{#COLLECTION}: Operations: commands, ms/s|<p>Fraction of time (ms/s) the mongod has spent to operations.</p>|Dependent item|mongodb.collection.ops.commands.ms["{#DBNAME}","{#COLLECTION}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.totals["{#DBNAME}.{#COLLECTION}"].commands.time`</p></li><li>Change per second</li></ul>|

### LLD rule Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Replication discovery|<p>Collect metrics by Zabbix agent if it exists.</p>|Dependent item|mongodb.rs.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Replication discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MongoDB: Node state|<p>An integer between 0 and 10 that represents the replica state of the current member.</p>|Dependent item|mongodb.rs.state[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.myState`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MongoDB: Replication lag|<p>Delay between a write operation on the primary and its copy to a secondary.</p>|Dependent item|mongodb.rs.lag[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.self == "true")].lag.first()`</p></li></ul>|
|MongoDB: Number of replicas|<p>The number of replicated nodes in current ReplicaSet.</p>|Dependent item|mongodb.rs.total_nodes[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.self == "true")].totalNodes.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MongoDB: Number of unhealthy replicas|<p>The number of replicated nodes with member health value  = 0.</p>|Dependent item|mongodb.rs.unhealthy_count[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.self == "true")].unhealthyCount.first()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MongoDB: Unhealthy replicas|<p>The replicated nodes in current ReplicaSet with member health value  = 0.</p>|Dependent item|mongodb.rs.unhealthy[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.members[?(@.self == "true")].unhealthyNodes.first()`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|MongoDB: Apply batches, rate|<p>Number of batches applied across all databases per second.</p>|Dependent item|mongodb.rs.apply.batches.rate[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.apply.batches.num`</p></li><li>Change per second</li></ul>|
|MongoDB: Apply batches, ms/s|<p>Fraction of time (ms/s) the mongod has spent applying operations from the oplog.</p>|Dependent item|mongodb.rs.apply.batches.ms.rate[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.apply.batches.totalMillis`</p></li><li>Change per second</li></ul>|
|MongoDB: Apply ops, rate|<p>Number of oplog operations applied per second.</p>|Dependent item|mongodb.rs.apply.rate[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.apply.ops`</p></li><li>Change per second</li></ul>|
|MongoDB: Buffer|<p>Number of operations in the oplog buffer.</p>|Dependent item|mongodb.rs.buffer.count[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.buffer.count`</p></li></ul>|
|MongoDB: Buffer, max size|<p>Maximum size of the buffer.</p>|Dependent item|mongodb.rs.buffer.max_size[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.buffer.maxSizeBytes`</p></li></ul>|
|MongoDB: Buffer, size|<p>Current size of the contents of the oplog buffer.</p>|Dependent item|mongodb.rs.buffer.size[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.buffer.sizeBytes`</p></li></ul>|
|MongoDB: Network bytes, rate|<p>Amount of data read from the replication sync source per second.</p>|Dependent item|mongodb.rs.network.bytes.rate[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.network.bytes`</p></li><li>Change per second</li></ul>|
|MongoDB: Network getmores, rate|<p>Number of getmore operations per second.</p>|Dependent item|mongodb.rs.network.getmores.rate[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.network.getmores.num`</p></li><li>Change per second</li></ul>|
|MongoDB: Network getmores, ms/s|<p>Fraction of time (ms/s) required to collect data from getmore operations.</p>|Dependent item|mongodb.rs.network.getmores.ms.rate[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.network.getmores.totalMillis`</p></li><li>Change per second</li></ul>|
|MongoDB: Network ops, rate|<p>Number of operations read from the replication source per second.</p>|Dependent item|mongodb.rs.network.ops.rate[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.network.ops`</p></li><li>Change per second</li></ul>|
|MongoDB: Network readers created, rate|<p>Number of oplog query processes created per second.</p>|Dependent item|mongodb.rs.network.readers.rate[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.network.readersCreated`</p></li><li>Change per second</li></ul>|
|MongoDB {#RS_NAME}: Oplog time diff|<p>Oplog window: difference between the first and last operation in the oplog. Only present if there are entries in the oplog.</p>|Dependent item|mongodb.rs.oplog.timediff[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.timediff`</p></li></ul>|
|MongoDB: Preload docs, rate|<p>Number of documents loaded per second during the pre-fetch stage of replication.</p>|Dependent item|mongodb.rs.preload.docs.rate[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.preload.docs.num`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|MongoDB: Preload docs, ms/s|<p>Fraction of time (ms/s) spent loading documents as part of the pre-fetch stage of replication.</p>|Dependent item|mongodb.rs.preload.docs.ms.rate[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.preload.docs.totalMillis`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|MongoDB: Preload indexes, rate|<p>Number of index entries loaded by members before updating documents as part of the pre-fetch stage of replication.</p>|Dependent item|mongodb.rs.preload.indexes.rate[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.preload.indexes.num`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|MongoDB: Preload indexes, ms/s|<p>Fraction of time (ms/s) spent loading documents as part of the pre-fetch stage of replication.</p>|Dependent item|mongodb.rs.preload.indexes.ms.rate[{#RS_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.repl.preload.indexes.totalMillis`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### Trigger prototypes for Replication discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MongoDB: Node in ReplicaSet changed the state|<p>Node in ReplicaSet  changed the state. Acknowledge to close the problem manually.</p>|`last(/MongoDB node by Zabbix agent 2/mongodb.rs.state[{#RS_NAME}],#1)<>last(/MongoDB node by Zabbix agent 2/mongodb.rs.state[{#RS_NAME}],#2)`|Warning|**Manual close**: Yes|
|MongoDB: Replication lag with primary is too high||`min(/MongoDB node by Zabbix agent 2/mongodb.rs.lag[{#RS_NAME}],5m)>{$MONGODB.REPL.LAG.MAX.WARN}`|Warning||
|MongoDB: There are unhealthy replicas in ReplicaSet||`last(/MongoDB node by Zabbix agent 2/mongodb.rs.unhealthy_count[{#RS_NAME}])>0 and length(last(/MongoDB node by Zabbix agent 2/mongodb.rs.unhealthy[{#RS_NAME}]))>0`|Average||

### LLD rule WiredTiger metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|WiredTiger metrics|<p>Collect metrics of WiredTiger Storage Engine if it exists.</p>|Dependent item|mongodb.wired_tiger.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for WiredTiger metrics

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|MongoDB: WiredTiger cache: bytes|<p>Size of the data currently in cache.</p>|Dependent item|mongodb.wired_tiger.cache.bytes_in_cache[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.cache['bytes currently in the cache']`</p></li></ul>|
|MongoDB: WiredTiger cache: in-memory page splits|<p>In-memory page splits.</p>|Dependent item|mongodb.wired_tiger.cache.splits[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.cache['in-memory page splits']`</p></li></ul>|
|MongoDB: WiredTiger cache: bytes, max|<p>Maximum cache size.</p>|Dependent item|mongodb.wired_tiger.cache.maximum_bytes_configured[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.cache['maximum bytes configured']`</p></li></ul>|
|MongoDB: WiredTiger cache: max page size at eviction|<p>Maximum page size at eviction.</p>|Dependent item|mongodb.wired_tiger.cache.max_page_size_eviction[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.cache['maximum page size at eviction']`</p></li></ul>|
|MongoDB: WiredTiger cache: modified pages evicted|<p>Number of pages, that have been modified, evicted from the cache.</p>|Dependent item|mongodb.wired_tiger.cache.modified_pages_evicted[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.cache['modified pages evicted']`</p></li></ul>|
|MongoDB: WiredTiger cache: pages read into cache|<p>Number of pages read into the cache.</p>|Dependent item|mongodb.wired_tiger.cache.pages_read[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.cache['pages read into cache']`</p></li></ul>|
|MongoDB: WiredTiger cache: pages written from cache|<p>Number of pages written from the cache.</p>|Dependent item|mongodb.wired_tiger.cache.pages_written[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.cache['pages written from cache']`</p></li></ul>|
|MongoDB: WiredTiger cache: pages held in cache|<p>Number of pages currently held in the cache.</p>|Dependent item|mongodb.wired_tiger.cache.pages_in_cache[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.cache['pages currently held in the cache']`</p></li></ul>|
|MongoDB: WiredTiger cache: pages evicted by application threads, rate|<p>Number of page evicted by application threads per second.</p>|Dependent item|mongodb.wired_tiger.cache.pages_evicted_threads.rate[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.cache.['pages evicted by application threads']`</p></li></ul>|
|MongoDB: WiredTiger cache: tracked dirty bytes in the cache|<p>Size of the dirty data in the cache.</p>|Dependent item|mongodb.wired_tiger.cache.tracked_dirty_bytes[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.cache.['tracked dirty bytes in the cache']`</p></li></ul>|
|MongoDB: WiredTiger cache: unmodified pages evicted|<p>Number of pages, that were not modified, evicted from the cache.</p>|Dependent item|mongodb.wired_tiger.cache.unmodified_pages_evicted[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.cache.['unmodified pages evicted']`</p></li></ul>|
|MongoDB: WiredTiger concurrent transactions: read, available|<p>Number of available read tickets (concurrent transactions) remaining.</p>|Dependent item|mongodb.wired_tiger.concurrent_transactions.read.available[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.concurrentTransactions.read.available`</p></li></ul>|
|MongoDB: WiredTiger concurrent transactions: read, out|<p>Number of read tickets (concurrent transactions) in use.</p>|Dependent item|mongodb.wired_tiger.concurrent_transactions.read.out[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.concurrentTransactions.read.out`</p></li></ul>|
|MongoDB: WiredTiger concurrent transactions: read, total tickets|<p>Total number of read tickets (concurrent transactions) available.</p>|Dependent item|mongodb.wired_tiger.concurrent_transactions.read.totalTickets[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.concurrentTransactions.read.totalTickets`</p></li></ul>|
|MongoDB: WiredTiger concurrent transactions: write, available|<p>Number of available write tickets (concurrent transactions) remaining.</p>|Dependent item|mongodb.wired_tiger.concurrent_transactions.write.available[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.concurrentTransactions.write.available`</p></li></ul>|
|MongoDB: WiredTiger concurrent transactions: write, out|<p>Number of write tickets (concurrent transactions) in use.</p>|Dependent item|mongodb.wired_tiger.concurrent_transactions.write.out[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.concurrentTransactions.write.out`</p></li></ul>|
|MongoDB: WiredTiger concurrent transactions: write, total tickets|<p>Total number of write tickets (concurrent transactions) available.</p>|Dependent item|mongodb.wired_tiger.concurrent_transactions.write.totalTickets[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wiredTiger.concurrentTransactions.write.totalTickets`</p></li></ul>|

### Trigger prototypes for WiredTiger metrics

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|MongoDB: Available WiredTiger read tickets is low|<p>Too few available read tickets.<br>When the number of available read tickets remaining reaches zero, new read requests will be queued until a new read ticket is available.</p>|`max(/MongoDB node by Zabbix agent 2/mongodb.wired_tiger.concurrent_transactions.read.available[{#SINGLETON}],5m)<{$MONGODB.WIRED_TIGER.TICKETS.AVAILABLE.MIN.WARN}`|Warning||
|MongoDB: Available WiredTiger write tickets is low|<p>Too few available write tickets.<br>When the number of available write tickets remaining reaches zero, new write requests will be queued until a new write ticket is available.</p>|`max(/MongoDB node by Zabbix agent 2/mongodb.wired_tiger.concurrent_transactions.write.available[{#SINGLETON}],5m)<{$MONGODB.WIRED_TIGER.TICKETS.AVAILABLE.MIN.WARN}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

