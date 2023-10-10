
# Apache Kafka by JMX

## Overview

This template is designed for the effortless deployment of Apache Kafka monitoring by Zabbix via JMX and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Apache Kafka 2.6.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Metrics are collected by JMX.

1. Enable and configure JMX access to Apache Kafka. See documentation for [instructions](https://kafka.apache.org/documentation/#remote_jmx).
2. Set the user name and password in host macros {$KAFKA.USER} and {$KAFKA.PASSWORD}.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$KAFKA.USER}||`zabbix`|
|{$KAFKA.PASSWORD}||`zabbix`|
|{$KAFKA.TOPIC.MATCHES}|<p>Filter of discoverable topics</p>|`.*`|
|{$KAFKA.TOPIC.NOT_MATCHES}|<p>Filter to exclude discovered topics</p>|`__consumer_offsets`|
|{$KAFKA.NET_PROC_AVG_IDLE.MIN.WARN}|<p>The minimum Network processor average idle percent for trigger expression.</p>|`30`|
|{$KAFKA.REQUEST_HANDLER_AVG_IDLE.MIN.WARN}|<p>The minimum Request handler average idle percent for trigger expression.</p>|`30`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kafka: Leader election per second|<p>Number of leader elections per second.</p>|JMX agent|jmx["kafka.controller:type=ControllerStats,name=LeaderElectionRateAndTimeMs","Count"]|
|Kafka: Unclean leader election per second|<p>Number of “unclean” elections per second.</p>|JMX agent|jmx["kafka.controller:type=ControllerStats,name=UncleanLeaderElectionsPerSec","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Kafka: Controller state on broker|<p>One indicates that the broker is the controller for the cluster.</p>|JMX agent|jmx["kafka.controller:type=KafkaController,name=ActiveControllerCount","Value"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Kafka: Ineligible pending replica deletes|<p>The number of ineligible pending replica deletes.</p>|JMX agent|jmx["kafka.controller:type=KafkaController,name=ReplicasIneligibleToDeleteCount","Value"]|
|Kafka: Pending replica deletes|<p>The number of pending replica deletes.</p>|JMX agent|jmx["kafka.controller:type=KafkaController,name=ReplicasToDeleteCount","Value"]|
|Kafka: Ineligible pending topic deletes|<p>The number of ineligible pending topic deletes.</p>|JMX agent|jmx["kafka.controller:type=KafkaController,name=TopicsIneligibleToDeleteCount","Value"]|
|Kafka: Pending topic deletes|<p>The number of pending topic deletes.</p>|JMX agent|jmx["kafka.controller:type=KafkaController,name=TopicsToDeleteCount","Value"]|
|Kafka: Offline log directory count|<p>The number of offline log directories (for example, after a hardware failure).</p>|JMX agent|jmx["kafka.log:type=LogManager,name=OfflineLogDirectoryCount","Value"]|
|Kafka: Offline partitions count|<p>Number of partitions that don't have an active leader.</p>|JMX agent|jmx["kafka.controller:type=KafkaController,name=OfflinePartitionsCount","Value"]|
|Kafka: Bytes out per second|<p>The rate at which data is fetched and read from the broker by consumers.</p>|JMX agent|jmx["kafka.server:type=BrokerTopicMetrics,name=BytesOutPerSec","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Kafka: Bytes in per second|<p>The rate at which data sent from producers is consumed by the broker.</p>|JMX agent|jmx["kafka.server:type=BrokerTopicMetrics,name=BytesInPerSec","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Kafka: Messages in per second|<p>The rate at which individual messages are consumed by the broker.</p>|JMX agent|jmx["kafka.server:type=BrokerTopicMetrics,name=MessagesInPerSec","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Kafka: Bytes rejected per second|<p>The rate at which bytes rejected per second by the broker.</p>|JMX agent|jmx["kafka.server:type=BrokerTopicMetrics,name=BytesRejectedPerSec","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Kafka: Client fetch request failed per second|<p>Number of client fetch request failures per second.</p>|JMX agent|jmx["kafka.server:type=BrokerTopicMetrics,name=FailedFetchRequestsPerSec","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Kafka: Produce requests failed per second|<p>Number of failed produce requests per second.</p>|JMX agent|jmx["kafka.server:type=BrokerTopicMetrics,name=FailedProduceRequestsPerSec","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Kafka: Request handler average idle percent|<p>Indicates the percentage of time that the request handler (IO) threads are not in use.</p>|JMX agent|jmx["kafka.server:type=KafkaRequestHandlerPool,name=RequestHandlerAvgIdlePercent","OneMinuteRate"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `100`</p></li></ul>|
|Kafka: Fetch-Consumer response send time, mean|<p>Average time taken, in milliseconds, to send the response.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=ResponseSendTimeMs,request=FetchConsumer","Mean"]|
|Kafka: Fetch-Consumer response send time, p95|<p>The time taken, in milliseconds, to send the response for 95th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=ResponseSendTimeMs,request=FetchConsumer","95thPercentile"]|
|Kafka: Fetch-Consumer response send time, p99|<p>The time taken, in milliseconds, to send the response for 99th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=ResponseSendTimeMs,request=FetchConsumer","99thPercentile"]|
|Kafka: Fetch-Follower response send time, mean|<p>Average time taken, in milliseconds, to send the response.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=ResponseSendTimeMs,request=FetchFollower","Mean"]|
|Kafka: Fetch-Follower response send time, p95|<p>The time taken, in milliseconds, to send the response for 95th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=ResponseSendTimeMs,request=FetchFollower","95thPercentile"]|
|Kafka: Fetch-Follower response send time, p99|<p>The time taken, in milliseconds, to send the response for 99th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=ResponseSendTimeMs,request=FetchFollower","99thPercentile"]|
|Kafka: Produce response send time, mean|<p>Average time taken, in milliseconds, to send the response.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=ResponseSendTimeMs,request=Produce","Mean"]|
|Kafka: Produce response send time, p95|<p>The time taken, in milliseconds, to send the response for 95th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=ResponseSendTimeMs,request=Produce","95thPercentile"]|
|Kafka: Produce response send time, p99|<p>The time taken, in milliseconds, to send the response for 99th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=ResponseSendTimeMs,request=Produce","99thPercentile"]|
|Kafka: Fetch-Consumer request total time, mean|<p>Average time in ms to serve the Fetch-Consumer request.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TotalTimeMs,request=FetchConsumer","Mean"]|
|Kafka: Fetch-Consumer request total time, p95|<p>Time in ms to serve the Fetch-Consumer request for 95th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TotalTimeMs,request=FetchConsumer","95thPercentile"]|
|Kafka: Fetch-Consumer request total time, p99|<p>Time in ms to serve the specified Fetch-Consumer for 99th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TotalTimeMs,request=FetchConsumer","99thPercentile"]|
|Kafka: Fetch-Follower request total time, mean|<p>Average time in ms to serve the Fetch-Follower request.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TotalTimeMs,request=FetchFollower","Mean"]|
|Kafka: Fetch-Follower request total time, p95|<p>Time in ms to serve the Fetch-Follower request for 95th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TotalTimeMs,request=FetchFollower","95thPercentile"]|
|Kafka: Fetch-Follower request total time, p99|<p>Time in ms to serve the Fetch-Follower request for 99th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TotalTimeMs,request=FetchFollower","99thPercentile"]|
|Kafka: Produce request total time, mean|<p>Average time in ms to serve the Produce request.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TotalTimeMs,request=Produce","Mean"]|
|Kafka: Produce request total time, p95|<p>Time in ms  to serve the Produce requests for 95th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TotalTimeMs,request=Produce","95thPercentile"]|
|Kafka: Produce request total time, p99|<p>Time in ms  to serve the Produce requests for 99th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TotalTimeMs,request=Produce","99thPercentile"]|
|Kafka: Fetch-Consumer request total time, mean|<p>Average time for a request to update metadata.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TotalTimeMs,request=UpdateMetadata","Mean"]|
|Kafka: UpdateMetadata request total time, p95|<p>Time for update metadata requests for 95th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TotalTimeMs,request=UpdateMetadata","95thPercentile"]|
|Kafka: UpdateMetadata request total time, p99|<p>Time for update metadata requests for 99th percentile.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TotalTimeMs,request=UpdateMetadata","99thPercentile"]|
|Kafka: Temporary memory size in bytes (Fetch), max|<p>The maximum of temporary memory used for converting message formats and decompressing messages.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TemporaryMemoryBytes,request=Fetch","Max"]|
|Kafka: Temporary memory size in bytes (Fetch), min|<p>The minimum of temporary memory used for converting message formats and decompressing messages.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TemporaryMemoryBytes,request=Fetch","Mean"]|
|Kafka: Temporary memory size in bytes (Produce), max|<p>The maximum of temporary memory used for converting message formats and decompressing messages.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TemporaryMemoryBytes,request=Produce","Max"]|
|Kafka: Temporary memory size in bytes (Produce), avg|<p>The amount of temporary memory used for converting message formats and decompressing messages.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TemporaryMemoryBytes,request=Produce","Mean"]|
|Kafka: Temporary memory size in bytes (Produce), min|<p>The minimum of temporary memory used for converting message formats and decompressing messages.</p>|JMX agent|jmx["kafka.network:type=RequestMetrics,name=TemporaryMemoryBytes,request=Produce","Min"]|
|Kafka: Network processor average idle percent|<p>The average percentage of time that the network processors are idle.</p>|JMX agent|jmx["kafka.network:type=SocketServer,name=NetworkProcessorAvgIdlePercent","Value"]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `100`</p></li></ul>|
|Kafka: Requests in producer purgatory|<p>Number of requests waiting in producer purgatory.</p>|JMX agent|jmx["kafka.server:type=DelayedOperationPurgatory,name=PurgatorySize,delayedOperation=Fetch","Value"]|
|Kafka: Requests in fetch purgatory|<p>Number of requests waiting in fetch purgatory.</p>|JMX agent|jmx["kafka.server:type=DelayedOperationPurgatory,name=PurgatorySize,delayedOperation=Produce","Value"]|
|Kafka: Replication maximum lag|<p>The maximum lag between the time that messages are received by the leader replica and by the follower replicas.</p>|JMX agent|jmx["kafka.server:type=ReplicaFetcherManager,name=MaxLag,clientId=Replica","Value"]|
|Kafka: Under minimum ISR partition count|<p>The number of partitions under the minimum In-Sync Replica (ISR) count.</p>|JMX agent|jmx["kafka.server:type=ReplicaManager,name=UnderMinIsrPartitionCount","Value"]|
|Kafka: Under replicated partitions|<p>The number of partitions that have not been fully replicated in the follower replicas (the number of non-reassigning replicas - the number of ISR > 0).</p>|JMX agent|jmx["kafka.server:type=ReplicaManager,name=UnderReplicatedPartitions","Value"]|
|Kafka: ISR expands per second|<p>The rate at which the number of ISRs in the broker increases.</p>|JMX agent|jmx["kafka.server:type=ReplicaManager,name=IsrExpandsPerSec","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Kafka: ISR shrink per second|<p>Rate of replicas leaving the ISR pool.</p>|JMX agent|jmx["kafka.server:type=ReplicaManager,name=IsrShrinksPerSec","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Kafka: Leader count|<p>The number of replicas for which this broker is the leader.</p>|JMX agent|jmx["kafka.server:type=ReplicaManager,name=LeaderCount","Value"]|
|Kafka: Partition count|<p>The number of partitions in the broker.</p>|JMX agent|jmx["kafka.server:type=ReplicaManager,name=PartitionCount","Value"]|
|Kafka: Number of reassigning partitions|<p>The number of reassigning leader partitions on a broker.</p>|JMX agent|jmx["kafka.server:type=ReplicaManager,name=ReassigningPartitions","Value"]|
|Kafka: Request queue size|<p>The size of the delay queue.</p>|JMX agent|jmx["kafka.server:type=Request","queue-size"]|
|Kafka: Version|<p>Current version of broker.</p>|JMX agent|jmx["kafka.server:type=app-info","version"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Kafka: Uptime|<p>The service uptime expressed in seconds.</p>|JMX agent|jmx["kafka.server:type=app-info","start-time-ms"]<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Kafka: ZooKeeper client request latency|<p>Latency in milliseconds for ZooKeeper requests from broker.</p>|JMX agent|jmx["kafka.server:type=ZooKeeperClientMetrics,name=ZooKeeperRequestLatencyMs","Count"]|
|Kafka: ZooKeeper connection status|<p>Connection status of broker's ZooKeeper session.</p>|JMX agent|jmx["kafka.server:type=SessionExpireListener,name=SessionState","Value"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Kafka: ZooKeeper disconnect rate|<p>ZooKeeper client disconnect per second.</p>|JMX agent|jmx["kafka.server:type=SessionExpireListener,name=ZooKeeperDisconnectsPerSec","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Kafka: ZooKeeper session expiration rate|<p>ZooKeeper client session expiration per second.</p>|JMX agent|jmx["kafka.server:type=SessionExpireListener,name=ZooKeeperExpiresPerSec","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Kafka: ZooKeeper readonly rate|<p>ZooKeeper client readonly per second.</p>|JMX agent|jmx["kafka.server:type=SessionExpireListener,name=ZooKeeperReadOnlyConnectsPerSec","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Kafka: ZooKeeper sync rate|<p>ZooKeeper client sync per second.</p>|JMX agent|jmx["kafka.server:type=SessionExpireListener,name=ZooKeeperSyncConnectsPerSec","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Kafka: Unclean leader election detected|<p>Unclean leader elections occur when there is no qualified partition leader among Kafka brokers. If Kafka is configured to allow an unclean leader election, a leader is chosen from the out-of-sync replicas, and any messages that were not synced prior to the loss of the former leader are lost forever. Essentially, unclean leader elections sacrifice consistency for availability.</p>|`last(/Apache Kafka by JMX/jmx["kafka.controller:type=ControllerStats,name=UncleanLeaderElectionsPerSec","Count"])>0`|Average||
|Kafka: There are offline log directories|<p>The offline log directory count metric indicate the number of log directories which are offline (due to a hardware failure for example) so that the broker cannot store incoming messages anymore.</p>|`last(/Apache Kafka by JMX/jmx["kafka.log:type=LogManager,name=OfflineLogDirectoryCount","Value"]) > 0`|Warning||
|Kafka: One or more partitions have no leader|<p>Any partition without an active leader will be completely inaccessible, and both consumers and producers of that partition will be blocked until a leader becomes available.</p>|`last(/Apache Kafka by JMX/jmx["kafka.controller:type=KafkaController,name=OfflinePartitionsCount","Value"]) > 0`|Warning||
|Kafka: Request handler average idle percent is too low|<p>The request handler idle ratio metric indicates the percentage of time the request handlers are not in use. The lower this number, the more loaded the broker is.</p>|`max(/Apache Kafka by JMX/jmx["kafka.server:type=KafkaRequestHandlerPool,name=RequestHandlerAvgIdlePercent","OneMinuteRate"],15m)<{$KAFKA.REQUEST_HANDLER_AVG_IDLE.MIN.WARN}`|Average||
|Kafka: Network processor average idle percent is too low|<p>The network processor idle ratio metric indicates the percentage of time the network processor are not in use. The lower this number, the more loaded the broker is.</p>|`max(/Apache Kafka by JMX/jmx["kafka.network:type=SocketServer,name=NetworkProcessorAvgIdlePercent","Value"],15m)<{$KAFKA.NET_PROC_AVG_IDLE.MIN.WARN}`|Average||
|Kafka: Failed to fetch info data|<p>Zabbix has not received data for items for the last 15 minutes</p>|`nodata(/Apache Kafka by JMX/jmx["kafka.network:type=SocketServer,name=NetworkProcessorAvgIdlePercent","Value"],15m)=1`|Warning||
|Kafka: There are partitions under the min ISR|<p>The Under min ISR partitions metric displays the number of partitions, where the number of In-Sync Replicas (ISR) is less than the minimum number of in-sync replicas specified. The two most common causes of under-min ISR partitions are that one or more brokers is unresponsive, or the cluster is experiencing performance issues and one or more brokers are falling behind.</p>|`last(/Apache Kafka by JMX/jmx["kafka.server:type=ReplicaManager,name=UnderMinIsrPartitionCount","Value"])>0`|Average||
|Kafka: There are under replicated partitions|<p>The Under replicated partitions metric displays the number of partitions that do not have enough replicas to meet the desired replication factor. A partition will also be considered under-replicated if the correct number of replicas exist, but one or more of the replicas have fallen significantly behind the partition leader. The two most common causes of under-replicated partitions are that one or more brokers is unresponsive, or the cluster is experiencing performance issues and one or more brokers have fallen behind.</p>|`last(/Apache Kafka by JMX/jmx["kafka.server:type=ReplicaManager,name=UnderReplicatedPartitions","Value"])>0`|Average||
|Kafka: Version has changed|<p>The Kafka version has changed. Acknowledge to close the problem manually.</p>|`last(/Apache Kafka by JMX/jmx["kafka.server:type=app-info","version"],#1)<>last(/Apache Kafka by JMX/jmx["kafka.server:type=app-info","version"],#2) and length(last(/Apache Kafka by JMX/jmx["kafka.server:type=app-info","version"]))>0`|Info|**Manual close**: Yes|
|Kafka: has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Apache Kafka by JMX/jmx["kafka.server:type=app-info","start-time-ms"])<10m`|Info|**Manual close**: Yes|
|Kafka: Broker is not connected to ZooKeeper||`find(/Apache Kafka by JMX/jmx["kafka.server:type=SessionExpireListener,name=SessionState","Value"],,"regexp","CONNECTED")=0`|Average||

### LLD rule Topic Metrics (write)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Topic Metrics (write)||JMX agent|jmx.discovery[beans,"kafka.server:type=BrokerTopicMetrics,name=MessagesInPerSec,topic=*"]|

### Item prototypes for Topic Metrics (write)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kafka {#JMXTOPIC}: Messages in per second|<p>The rate at which individual messages are consumed by topic.</p>|JMX agent|jmx["kafka.server:type=BrokerTopicMetrics,name=MessagesInPerSec,topic={#JMXTOPIC}","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Kafka {#JMXTOPIC}: Bytes in per second|<p>The rate at which data sent from producers is consumed by topic.</p>|JMX agent|jmx["kafka.server:type=BrokerTopicMetrics,name=BytesInPerSec,topic={#JMXTOPIC}","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### LLD rule Topic Metrics (read)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Topic Metrics (read)||JMX agent|jmx.discovery[beans,"kafka.server:type=BrokerTopicMetrics,name=BytesOutPerSec,topic=*"]|

### Item prototypes for Topic Metrics (read)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kafka {#JMXTOPIC}: Bytes out per second|<p>The rate at which data is fetched and read from the broker by consumers (by topic).</p>|JMX agent|jmx["kafka.server:type=BrokerTopicMetrics,name=BytesOutPerSec,topic={#JMXTOPIC}","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

### LLD rule Topic Metrics (errors)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Topic Metrics (errors)||JMX agent|jmx.discovery[beans,"kafka.server:type=BrokerTopicMetrics,name=BytesRejectedPerSec,topic=*"]|

### Item prototypes for Topic Metrics (errors)

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Kafka {#JMXTOPIC}: Bytes rejected per second|<p>Rejected bytes rate by topic.</p>|JMX agent|jmx["kafka.server:type=BrokerTopicMetrics,name=BytesRejectedPerSec,topic={#JMXTOPIC}","Count"]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

