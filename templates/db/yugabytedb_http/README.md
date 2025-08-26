
# YugabyteDB by HTTP

## Overview

This template is designed for the deployment of YugabyteDB monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- YugabyteDB, version 2.19.2.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Set your account ID as a value of the {$YUGABYTEDB.ACCOUNT.ID} macro. The account ID is the unique identifier for your customer account in YugabyteDB Managed. You can access the account ID from your profile in the YugabyteDB Managed user interface. To get your account ID, log in to YugabyteDB Managed and click the user profile icon. See [YugabyteDB documentation](https://yugabyte.stoplight.io/docs/managed-apis/tvsjh28t5ivmw-getting-started#account-id) for instructions.

2. Set your project ID as a value of the {$YUGABYTEDB.PROJECT.ID} macro. The project ID is the unique identifier for a YugabyteDB Managed project. You can access the project ID from your profile in the YugabyteDB Managed user interface (along with the account ID). See [YugabyteDB documentation](https://yugabyte.stoplight.io/docs/managed-apis/tvsjh28t5ivmw-getting-started#project-id) for instructions.

3. Generate the API access token and specify it as a value of the {$YUGABYTEDB.ACCESS.TOKEN} macro. See [YugabyteDB documentation](https://docs.yugabyte.com/preview/yugabyte-cloud/managed-automation/managed-apikeys/#create-an-api-key) for instructions.

*NOTE* If needed, you can specify a HTTP proxy for the template to use by changing the value of the {$YUGABYTEDB.PROXY} user macro.

**IMPORTANT**

  The value of the {$YUGABYTEDB.ACCESS.TOKEN} macro is stored as plain (not secret) text by default.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$YUGABYTEDB.ACCOUNT.ID}|<p>YugabyteDB account ID.</p>||
|{$YUGABYTEDB.PROJECT.ID}|<p>YugabyteDB project ID.</p>||
|{$YUGABYTEDB.ACCESS.TOKEN}|<p>Access token for the YugabyteDB API.</p>||
|{$YUGABYTEDB.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get cluster|<p>Get raw data about clusters.</p>|Script|yugabytedb.clusters.get|
|Get clusters item error|<p>Item for gathering all the cluster item errors.</p>|Dependent item|yugabytedb.clusters.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|YugabyteDB: Failed to fetch data|<p>Failed to fetch data about cluster.</p>|`length(last(/YugabyteDB by HTTP/yugabytedb.clusters.get.errors)) > 0`|Warning||

### LLD rule Cluster discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cluster discovery|<p>Discovery of the available clusters.</p>|Dependent item|yugabytedb.cluster.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

# YugabyteDB Cluster by HTTP

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$YUGABYTEDB.CLUSTER.NAME}|<p>Name of cluster.</p>||
|{$YUGABYTEDB.CLUSTER.ID}|<p>ID of cluster.</p>||
|{$YUGABYTEDB.MEMORY.CLUSTER.UTILIZATION.WARN}|<p>The percentage of memory use on the cluster - for the Warning trigger expression.</p>|`70`|
|{$YUGABYTEDB.MEMORY.CLUSTER.UTILIZATION.CRIT}|<p>The percentage of memory use on the cluster - for the High trigger expression.</p>|`90`|
|{$YUGABYTEDB.DISK.UTILIZATION.WARN}|<p>The percentage of disk use in the cluster - for the Warning trigger expression.</p>|`75`|
|{$YUGABYTEDB.DISK.UTILIZATION.CRIT}|<p>The percentage of disk use in the cluster - for the High trigger expression.</p>|`90`|
|{$YUGABYTEDB.CONNECTION.UTILIZATION.WARN}|<p>The percentage of connections in the cluster - for the Warning trigger expression.</p>|`75`|
|{$YUGABYTEDB.CONNECTION.UTILIZATION.CRIT}|<p>The percentage of connections in the cluster - for the High trigger expression.</p>|`90`|
|{$YUGABYTEDB.CPU.UTILIZATION.CRIT}|<p>The threshold of CPU utilization for the High trigger expression, expressed in percent.</p>|`90`|
|{$YUGABYTEDB.CPU.UTILIZATION.WARN}|<p>The threshold of CPU utilization for the Warning trigger expression, expressed in percent.</p>|`75`|
|{$YUGABYTEDB.IOPS.UTILIZATION.WARN}|<p>The percentage of IOPS use on the node - for the Warning trigger expression.</p>|`75`|
|{$YUGABYTEDB.IOPS.UTILIZATION.CRIT}|<p>The percentage of IOPS use on the node - for the High trigger expression.</p>|`90`|
|{$YUGABYTEDB.PROXY}|<p>Sets the HTTP proxy value. If this macro is empty, then no proxy is used.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get cluster|<p>Get raw data about clusters.</p>|Script|yugabytedb.cluster.get|
|Get cluster item error|<p>Item for gathering all the cluster item errors.</p>|Dependent item|yugabytedb.cluster.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Get keyspace|<p>Get raw data about keyspaces.</p>|Script|yugabytedb.keyspace.get|
|Get keyspace item error|<p>Item for gathering all the keyspace item errors.</p>|Dependent item|yugabytedb.keyspace.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Get node|<p>Get raw data about nodes.</p>|Script|yugabytedb.node.get|
|Get node item error|<p>Item for gathering all the node item errors.</p>|Dependent item|yugabytedb.node.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Get cluster metrics|<p>Getting metrics for the cluster.</p>|Script|yugabytedb.cluster.metric.get|
|Get cluster metrics item error|<p>Item for gathering all the cluster item errors.</p>|Dependent item|yugabytedb.cluster.metric.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Get cluster query statistic|<p>Getting SQL statistics for the cluster.</p>|Script|yugabytedb.cluster.query.statistic.get|
|Get cluster query statistic item error|<p>Item for gathering all the cluster query statistics item errors.</p>|Dependent item|yugabytedb.cluster.query.statistic.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|State|<p>The current state of the cluster. One of the following:</p><p>- INVALID</p><p>- QUEUED</p><p>- INIT</p><p>- BOOTSTRAPPING</p><p>- VPC_PEERING</p><p>- NETWORK_CREATING</p><p>- PROVISIONING</p><p>- CONFIGURING</p><p>- CREATING_LB</p><p>- UPDATING_LB</p><p>- ACTIVE</p><p>- PAUSING</p><p>- PAUSED</p><p>- RESUMING</p><p>- UPDATING</p><p>- MAINTENANCE</p><p>- RESTORE</p><p>- FAILED</p><p>- CREATE_FAILED</p><p>- DELETING</p><p>- STARTING_NODE</p><p>- STOPPING_NODE</p><p>- REBOOTING_NODE</p><p>- CREATE_READ_REPLICA_FAILED</p><p>- DELETE_READ_REPLICA_FAILED</p><p>- DELETE_CLUSTER_FAILED</p><p>- EDIT_CLUSTER_FAILED</p><p>- EDIT_READ_REPLICA_FAILED</p><p>- PAUSE_CLUSTER_FAILED</p><p>- RESUME_CLUSTER_FAILED</p><p>- RESTORE_BACKUP_FAILED</p><p>- CERTIFICATE_ROTATION_FAILED</p><p>- UPGRADE_CLUSTER_FAILED</p><p>- UPGRADE_CLUSTER_GFLAGS_FAILED</p><p>- UPGRADE_CLUSTER_OS_FAILED</p><p>- UPGRADE_CLUSTER_SOFTWARE_FAILED</p><p>- START_NODE_FAILED</p><p>- STOP_NODE_FAILED</p><p>- REBOOT_NODE_FAILED</p><p>- CONFIGURE_CMK</p><p>- ENABLING_CMK</p><p>- DISABLING_CMK</p><p>- UPDATING_CMK</p><p>- ROTATING_CMK</p><p>- STOPPING_METRICS_EXPORTER</p><p>- STARTING_METRICS_EXPORTER</p><p>- CONFIGURING_METRICS_EXPORTER</p><p>- STOP_METRICS_EXPORTER_FAILED</p><p>- START_METRICS_EXPORTER_FAILED</p><p>- CONFIGURE_METRICS_EXPORTER_FAILED</p><p>- REMOVING_METRICS_EXPORTER</p><p>- REMOVE_METRICS_EXPORTER_FAILED</p>|Dependent item|yugabytedb.cluster.state<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Type|<p>The kind of cluster deployment: SYNCHRONOUS or GEO_PARTITIONED.</p>|Dependent item|yugabytedb.cluster.type<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.spec.cluster_info.cluster_type`</p></li><li><p>Replace: `SYNCHRONOUS -> 0`</p></li><li><p>Replace: `GEO_PARTITIONED -> 1`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Number of nodes|<p>How many nodes are in the cluster.</p>|Dependent item|yugabytedb.cluster.node.number<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.spec.cluster_info.num_nodes`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Software version|<p>The current version of YugabyteDB installed on the cluster.</p>|Dependent item|yugabytedb.cluster.software.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.software_version`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|YB controller version|<p>The current version of the YB controller installed on the cluster.</p>|Dependent item|yugabytedb.cluster.ybc.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.info.ybc_version`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Health state|<p>Current state regarding the health of the cluster:</p><p> - HEALTHY</p><p> - NEEDS_ATTENTION</p><p> - UNHEALTHY</p><p> - UNKNOWN</p>|Dependent item|yugabytedb.cluster.health.state<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|CPU utilization|<p>The percentage of CPU use being consumed by the tablet or master server Yugabyte processes, as well as other processes, if any.</p>|Dependent item|yugabytedb.cluster.cpu.utilization<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.CPU_USAGE`</p></li></ul>|
|Disk space usage|<p>Shows the amount of disk space used by the cluster.</p>|Dependent item|yugabytedb.cluster.disk.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DISK_USAGE_GB`</p></li><li><p>Custom multiplier: `1073741824`</p></li></ul>|
|Disk space provisioned|<p>Shows the amount of disk space provisioned for the cluster.</p>|Dependent item|yugabytedb.cluster.disk.provisioned<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.PROVISIONED_DISK_SPACE_GB`</p></li><li><p>Custom multiplier: `1073741824`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Disk space utilization|<p>Shows the percentage of disk space used by the cluster.</p>|Calculated|yugabytedb.cluster.disk.utilization|
|Disk read, Bps|<p>The number of bytes being read from disk per second, averaged over each node.</p>|Dependent item|yugabytedb.cluster.disk.read.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DISK_BYTES_READ_MB_PER_SEC`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Disk write, Bps|<p>The number of bytes being written to disk per second, averaged over each node.</p>|Dependent item|yugabytedb.cluster.disk.write.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.DISK_BYTES_WRITTEN_MB_PER_SEC`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Disk read OPS|<p>The number of read operations per second.</p>|Dependent item|yugabytedb.cluster.disk.read.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.READ_OPS_PER_SEC`</p></li></ul>|
|Disk write OPS|<p>The number of write operations per second.</p>|Dependent item|yugabytedb.cluster.disk.write.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.WRITE_OPS_PER_SEC`</p></li></ul>|
|Average read latency|<p>The average latency of read operations at the tablet level.</p>|Dependent item|yugabytedb.cluster.read.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.AVERAGE_READ_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Average write latency|<p>The average latency of write operations at the tablet level.</p>|Dependent item|yugabytedb.cluster.write.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.AVERAGE_WRITE_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|YSQL connections limit|<p>The limit of the number of connections to the YSQL backend for all nodes.</p>|Dependent item|yugabytedb.cluster.connection.limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_CONNECTION_LIMIT`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|YSQL connections average used|<p>Cumulative number of connections to the YSQL backend for all nodes.</p>|Dependent item|yugabytedb.cluster.connection.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.AVERAGE_YSQL_CONNECTION_COUNT`</p></li></ul>|
|YSQL connections utilization|<p>Cumulative number of connections to the YSQL backend for all nodes, expressed in percent.</p>|Calculated|yugabytedb.cluster.connection.utilization|
|YSQL connections maximum used|<p>Maximum of used connections to the YSQL backend for all nodes.</p>|Dependent item|yugabytedb.cluster.connection.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_MAX_CONNECTION_COUNT`</p></li></ul>|
|Clock skew|<p>The clock drift and skew across different nodes.</p>|Dependent item|yugabytedb.cluster.node.skew<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.NODE_CLOCK_SKEW`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Memory total|<p>Shows the amount of RAM provisioned to the cluster.</p>|Dependent item|yugabytedb.cluster.memory.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.MEMORY_TOTAL_GB`</p></li><li><p>Custom multiplier: `1073741824`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|Memory usage|<p>Shows the amount of RAM used on the cluster.</p>|Dependent item|yugabytedb.cluster.memory.usage<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.MEMORY_USAGE_GB`</p></li><li><p>Custom multiplier: `1073741824`</p></li></ul>|
|Memory utilization|<p>Shows the amount of RAM used on the cluster, expressed in percent.</p>|Calculated|yugabytedb.cluster.memory.utilization|
|Network receive, Bps|<p>The size of network packets received per second, averaged over nodes.</p>|Dependent item|yugabytedb.cluster.network.receive.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.NETWORK_RECEIVE_BYTES_MB_PER_SEC`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Network transmit, Bps|<p>The size of network packets transmitted per second, averaged over nodes.</p>|Dependent item|yugabytedb.cluster.network.transmit.bps<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.NETWORK_TRANSMIT_BYTES_MB_PER_SEC`</p></li><li><p>Custom multiplier: `1048576`</p></li></ul>|
|Network receive error, rate|<p>The number of errors related to network packets received per second, averaged over nodes.</p>|Dependent item|yugabytedb.cluster.network.receive.error.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.NETWORK_RECEIVE_ERRORS_PER_SEC`</p></li></ul>|
|Network transmit error, rate|<p>The number of errors related to network packets transmitted per second, averaged over nodes.</p>|Dependent item|yugabytedb.cluster.network.transmit.error.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.NETWORK_TRANSMIT_ERRORS_PER_SEC`</p></li></ul>|
|YSQL SELECT OPS|<p>The count of SELECT statements executed through the YSQL API per second. This does not include index writes.</p>|Dependent item|yugabytedb.cluster.ysql.select.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_SELECT_OPS_PER_SEC`</p></li></ul>|
|YSQL DELETE OPS|<p>The count of DELETE statements executed through the YSQL API per second. This does not include index writes.</p>|Dependent item|yugabytedb.cluster.ysql.delete.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_DELETE_OPS_PER_SEC`</p></li></ul>|
|YSQL UPDATE OPS|<p>The count of UPDATE statements executed through the YSQL API per second. This does not include index writes.</p>|Dependent item|yugabytedb.cluster.ysql.update.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_UPDATE_OPS_PER_SEC`</p></li></ul>|
|YSQL INSERT OPS|<p>The count of INSERT statements executed through the YSQL API per second. This does not include index writes.</p>|Dependent item|yugabytedb.cluster.ysql.insert.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_INSERT_OPS_PER_SEC`</p></li></ul>|
|YSQL OTHER OPS|<p>The count of OTHER statements executed through the YSQL API per second.</p>|Dependent item|yugabytedb.cluster.ysql.other.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_OTHER_OPS_PER_SEC`</p></li></ul>|
|YSQL transaction OPS|<p>The count of transactions executed through the YSQL API per second.</p>|Dependent item|yugabytedb.cluster.ysql.transaction.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_TRANSACTION_OPS_PER_SEC`</p></li></ul>|
|YSQL SELECT average latency|<p>Average time of executing SELECT statements through the YSQL API.</p>|Dependent item|yugabytedb.cluster.ysql.select.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_SELECT_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|YSQL DELETE average latency|<p>Average time of executing DELETE statements through the YSQL API.</p>|Dependent item|yugabytedb.cluster.ysql.delete.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_DELETE_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|YSQL UPDATE average latency|<p>Average time of executing UPDATE statements through the YSQL API.</p>|Dependent item|yugabytedb.cluster.ysql.update.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_UPDATE_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|YSQL INSERT average latency|<p>Average time of executing INSERT statements through the YSQL API.</p>|Dependent item|yugabytedb.cluster.ysql.insert.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_INSERT_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|YSQL OTHER average latency|<p>Average time of executing OTHER statements through the YSQL API.</p>|Dependent item|yugabytedb.cluster.ysql.other.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_OTHER_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|YSQL transaction average latency|<p>Average time of executing transactions through the YSQL API.</p>|Dependent item|yugabytedb.cluster.ysql.transaction.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YSQL_TRANSACTION_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|YCQL SELECT OPS|<p>The count of SELECT statements executed through the YCQL API per second. This does not include index writes.</p>|Dependent item|yugabytedb.cluster.ycql.select.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YCQL_SELECT_OPS_PER_SEC`</p></li></ul>|
|YCQL DELETE OPS|<p>The count of DELETE statements executed through the YCQL API per second. This does not include index writes.</p>|Dependent item|yugabytedb.cluster.ycql.delete.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YCQL_DELETE_OPS_PER_SEC`</p></li></ul>|
|YCQL INSERT OPS|<p>The count of INSERT statements executed through the YCQL API per second. This does not include index writes.</p>|Dependent item|yugabytedb.cluster.ycql.insert.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YCQL_INSERT_OPS_PER_SEC`</p></li></ul>|
|YCQL OTHER OPS|<p>The count of OTHER statements executed through the YCQL API per second.</p>|Dependent item|yugabytedb.cluster.ycql.other.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YCQL_OTHER_OPS_PER_SEC`</p></li></ul>|
|YCQL UPDATE OPS|<p>The count of UPDATE statements executed through the YCQL API per second. This does not include index writes.</p>|Dependent item|yugabytedb.cluster.ycql.update.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YCQL_UPDATE_OPS_PER_SEC`</p></li></ul>|
|YCQL transaction OPS|<p>The count of transactions executed through the YCQL API per second.</p>|Dependent item|yugabytedb.cluster.ycql.transaction.ops<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YCQL_TRANSACTION_OPS_PER_SEC`</p></li></ul>|
|YCQL SELECT average latency|<p>Average time of executing SELECT statements through the YCQL API.</p>|Dependent item|yugabytedb.cluster.ycql.select.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YCQL_SELECT_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|YCQL DELETE average latency|<p>Average time of executing DELETE statements through the YCQL API.</p>|Dependent item|yugabytedb.cluster.ycql.delete.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YCQL_DELETE_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|YCQL INSERT average latency|<p>Average time of executing INSERT statements through the YCQL API.</p>|Dependent item|yugabytedb.cluster.ycql.insert.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YCQL_INSERT_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|YCQL OTHER average latency|<p>Average time of executing OTHER statements through the YCQL API.</p>|Dependent item|yugabytedb.cluster.ycql.other.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YCQL_OTHER_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|YCQL UPDATE average latency|<p>Average time of executing UPDATE statements through the YCQL API.</p>|Dependent item|yugabytedb.cluster.ycql.update.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YCQL_UPDATE_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|YCQL transaction average latency|<p>Average time of executing transactions through the YCQL API.</p>|Dependent item|yugabytedb.cluster.ycql.transaction.latency<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.YCQL_TRANSACTION_LATENCY_MS`</p></li><li><p>Custom multiplier: `0.001`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|YugabyteDB Cluster: Failed to fetch data|<p>Failed to fetch data from the YugabyteDB API.</p>|`length(last(/YugabyteDB Cluster by HTTP/yugabytedb.node.get.errors)) > 0 or length(last(/YugabyteDB Cluster by HTTP/yugabytedb.keyspace.get.errors)) > 0 or length(last(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.get.errors)) > 0`|Warning||
|YugabyteDB Cluster: Failed to fetch metric data|<p>Failed to fetch cluster metrics or cluster statistics.</p>|`length(last(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.query.statistic.get.errors)) > 0 or length(last(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.metric.get.errors)) > 0`|Warning||
|YugabyteDB Cluster: Cluster software version has changed|<p>YugabyteDB Cluster software version has changed. Acknowledge to close the problem manually.</p>|`last(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.software.version,#1) <> last(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.software.version,#2) and length(last(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.software.version)) > 0`|Info|**Manual close**: Yes|
|YugabyteDB Cluster: YB controller version has changed|<p>YugabyteDB Cluster YB controller version has changed. Acknowledge to close the problem manually.</p>|`last(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.ybc.version,#1) <> last(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.ybc.version,#2) and length(last(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.ybc.version)) > 0`|Info|**Manual close**: Yes|
|YugabyteDB Cluster: Cluster is not healthy|<p>YugabyteDB Cluster is not healthy.</p>|`last(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.health.state,#1) <> 0`|Average||
|YugabyteDB Cluster: CPU utilization is too high|<p>YugabyteDB Cluster CPU utilization is more than {$YUGABYTEDB.CPU.UTILIZATION.CRIT}%. The system might be slow to respond.</p>|`min(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.cpu.utilization,5m) > {$YUGABYTEDB.CPU.UTILIZATION.CRIT}`|High||
|YugabyteDB Cluster: CPU utilization is high|<p>YugabyteDB Cluster CPU utilization is more than {$YUGABYTEDB.CPU.UTILIZATION.WARN}%. The system might be slow to respond.</p>|`min(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.cpu.utilization,5m) > {$YUGABYTEDB.CPU.UTILIZATION.WARN}`|Warning|**Depends on**:<br><ul><li>YugabyteDB Cluster: CPU utilization is too high</li></ul>|
|YugabyteDB Cluster: Storage space is low|<p>YugabyteDB Cluster uses more than {$YUGABYTEDB.DISK.UTILIZATION.WARN}% of disk space.</p>|`min(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.disk.utilization,5m) > {$YUGABYTEDB.DISK.UTILIZATION.WARN}`|Warning|**Depends on**:<br><ul><li>YugabyteDB Cluster: Storage space is critically low</li></ul>|
|YugabyteDB Cluster: Storage space is critically low|<p>YugabyteDB Cluster uses more than {$YUGABYTEDB.DISK.UTILIZATION.CRIT}% of disk space.</p>|`min(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.disk.utilization,5m) > {$YUGABYTEDB.DISK.UTILIZATION.CRIT}`|High||
|YugabyteDB Cluster: Average utilization of connections is high|<p>YugabyteDB Cluster uses more than {$YUGABYTEDB.CONNECTION.UTILIZATION.WARN}% of the connection limit.</p>|`min(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.connection.utilization,5m) > {$YUGABYTEDB.CONNECTION.UTILIZATION.WARN}`|Warning|**Depends on**:<br><ul><li>YugabyteDB Cluster: Average utilization of connections is too high</li></ul>|
|YugabyteDB Cluster: Average utilization of connections is too high|<p>YugabyteDB Cluster uses more than {$YUGABYTEDB.CONNECTION.UTILIZATION.CRIT}% of the connection limit.</p>|`min(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.connection.utilization,5m) > {$YUGABYTEDB.CONNECTION.UTILIZATION.CRIT}`|High||
|YugabyteDB Cluster: Memory utilization is high|<p>YugabyteDB Cluster uses more than {$YUGABYTEDB.MEMORY.CLUSTER.UTILIZATION.WARN}% of memory.</p>|`min(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.memory.utilization,5m) > {$YUGABYTEDB.MEMORY.CLUSTER.UTILIZATION.WARN}`|Warning|**Depends on**:<br><ul><li>YugabyteDB Cluster: Memory utilization is too high</li></ul>|
|YugabyteDB Cluster: Memory utilization is too high|<p>YugabyteDB Cluster uses more than {$YUGABYTEDB.MEMORY.CLUSTER.UTILIZATION.CRIT}% of memory.</p>|`min(/YugabyteDB Cluster by HTTP/yugabytedb.cluster.memory.utilization,5m) > {$YUGABYTEDB.MEMORY.CLUSTER.UTILIZATION.CRIT}`|High||

### LLD rule Keyspace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Keyspace discovery|<p>Discovery of the available keyspaces.</p>|Dependent item|yugabytedb.keyspace.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Keyspace discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|YugabyteDB Keyspace [{#KEYSPACE.NAME}]: Get keyspace info|<p>Get raw data about the keyspace [{#KEYSPACE.NAME}].</p>|Dependent item|yugabytedb.keyspace.get[{#KEYSPACE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.keyspaces.[?(@.keyspace_name=='{#KEYSPACE.NAME}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|YugabyteDB Keyspace [{#KEYSPACE.NAME}]: SST size|<p>The size of the table's SST.</p>|Dependent item|yugabytedb.keyspace.sst.size[{#KEYSPACE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.size_bytes`</p></li></ul>|
|YugabyteDB Keyspace [{#KEYSPACE.NAME}]: Wal size|<p>The size of the table's WAL.</p>|Dependent item|yugabytedb.keyspace.wal.size[{#KEYSPACE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wal_size_bytes`</p></li></ul>|
|YugabyteDB Keyspace [{#KEYSPACE.NAME}]: Type|<p>The type of keyspace: YSQL or YCQL.</p>|Dependent item|yugabytedb.keyspace.type[{#KEYSPACE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.type`</p></li><li><p>Replace: `YSQL -> 0`</p></li><li><p>Replace: `YCQL -> 1`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### LLD rule Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Node discovery|<p>Discovery of the nodes for all clusters.</p>|Dependent item|yugabytedb.node.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Item prototypes for Node discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|YugabyteDB Node [{#NODE.NAME}]: Get node info|<p>Get raw data about the node [{#NODE.NAME}].</p>|Dependent item|yugabytedb.node.get[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.nodes.[?(@.name=='{#NODE.NAME}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Disk IOPS limit|<p>The IOPS to provision for the node [{#NODE.NAME}] for each disk.</p>|Dependent item|yugabytedb.node.iops.limit[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.spec.cluster_info.node_info.disk_iops`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Total disk size|<p>The disk size (GB) for the node [{#NODE.NAME}].</p>|Dependent item|yugabytedb.node.disk.size.total[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.spec.cluster_info.node_info.disk_size_gb`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Total memory, bytes|<p>The amount of RAM for the node [{#NODE.NAME}].</p>|Dependent item|yugabytedb.node.memory.total[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.spec.cluster_info.node_info.memory_mb`</p></li><li><p>Custom multiplier: `1048576`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Total CPU cores|<p>The number of cores for the node [{#NODE.NAME}].</p>|Dependent item|yugabytedb.node.cpu.num.cores[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data.spec.cluster_info.node_info.num_cores`</p></li><li><p>Discard unchanged with heartbeat: `12h`</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Region|<p>The cloud information for the node [{#NODE.NAME}] about the region.</p>|Dependent item|yugabytedb.node.region[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cloud_info.region`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Zone|<p>The cloud information for the node [{#NODE.NAME}] about the zone.</p>|Dependent item|yugabytedb.node.zone[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.cloud_info.zone`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Total SST file size|<p>The size of all SST files.</p>|Dependent item|yugabytedb.node.sst.file.size.total[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.total_sst_file_size_bytes`</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Uncompressed SST file size|<p>The size of uncompressed SST files.</p>|Dependent item|yugabytedb.node.sst.file.size.uncompressed[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.uncompressed_sst_file_size_bytes`</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Read OPS|<p>The amount of read operations per second for the node [{#NODE.NAME}].</p>|Dependent item|yugabytedb.node.read.ops[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.read_ops_per_sec`</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Write OPS|<p>The amount of write operations per second for the node [{#NODE.NAME}].</p>|Dependent item|yugabytedb.node.write.ops[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.metrics.write_ops_per_sec`</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Disk IOPS utilization|<p>Shows the utilization of provisioned IOPS.</p>|Calculated|yugabytedb.node.iops.utilization[{#NODE.NAME}]|
|YugabyteDB Node [{#NODE.NAME}]: Node status|<p>The current status of the node [{#NODE.NAME}]:</p><p>0 = Down</p><p>1 = Up</p>|Dependent item|yugabytedb.node.status[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.is_node_up`</p></li><li><p>Replace: `true -> 1`</p></li><li><p>Replace: `false -> 0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Node is master|<p>The current role of the node [{#NODE.NAME}]:</p><p>0 = False</p><p>1 = True</p>|Dependent item|yugabytedb.node.master[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.is_master`</p></li><li><p>Replace: `true -> 1`</p></li><li><p>Replace: `false -> 0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Node is TServer|<p>This item indicates if the node [{#NODE.NAME}] is a TServer node:</p><p>0 = False</p><p>1 = True</p>|Dependent item|yugabytedb.node.tserver[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.is_tserver`</p></li><li><p>Replace: `true -> 1`</p></li><li><p>Replace: `false -> 0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|YugabyteDB Node [{#NODE.NAME}]: Node is read replica|<p>This item indicates if the node [{#NODE.NAME}] is a read replica:</p><p>0 = False</p><p>1 = True</p>|Dependent item|yugabytedb.node.read.replica[{#NODE.NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.is_read_replica`</p></li><li><p>Replace: `true -> 1`</p></li><li><p>Replace: `false -> 0`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Node discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|YugabyteDB Cluster: YugabyteDB Node [{#NODE.NAME}]: Node disk IOPS utilization is high|<p>IOPS utilization on the node [{#NODE.NAME}] is more than {$YUGABYTEDB.IOPS.UTILIZATION.WARN}% of the provisioned IOPS.</p>|`min(/YugabyteDB Cluster by HTTP/yugabytedb.node.iops.utilization[{#NODE.NAME}],5m) > {$YUGABYTEDB.IOPS.UTILIZATION.WARN}`|Warning|**Depends on**:<br><ul><li>YugabyteDB Cluster: YugabyteDB Node [{#NODE.NAME}]: Node disk IOPS utilization is too high</li></ul>|
|YugabyteDB Cluster: YugabyteDB Node [{#NODE.NAME}]: Node disk IOPS utilization is too high|<p>IOPS utilization on the node [{#NODE.NAME}] is more than {$YUGABYTEDB.IOPS.UTILIZATION.CRIT}% of the provisioned IOPS.</p>|`min(/YugabyteDB Cluster by HTTP/yugabytedb.node.iops.utilization[{#NODE.NAME}],5m) > {$YUGABYTEDB.IOPS.UTILIZATION.CRIT}`|High||
|YugabyteDB Cluster: YugabyteDB Node [{#NODE.NAME}]: Node is down|<p>The node [{#NODE.NAME}] is down.</p>|`max(/YugabyteDB Cluster by HTTP/yugabytedb.node.status[{#NODE.NAME}],3m) = 0`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

