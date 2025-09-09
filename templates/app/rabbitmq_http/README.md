
# RabbitMQ cluster by HTTP

## Overview

This template is developed to monitor the messaging broker RabbitMQ cluster by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

The template collects metrics by polling RabbitMQ management plugin with HTTP agent remotely.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- RabbitMQ 3.5.7, 3.7.7, 3.7.17, 3.7.18, 3.8.5, 3.8.12 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Enable the RabbitMQ management plugin. See the [`RabbitMQ documentation`](https://www.rabbitmq.com/management.html) for the instructions.

2. Create a user to monitor the service:

```bash
rabbitmqctl add_user zbx_monitor <PASSWORD>
rabbitmqctl set_permissions  -p / zbx_monitor "" "" ".*"
rabbitmqctl set_user_tags zbx_monitor monitoring
```

3. Set the hostname or IP address of the RabbitMQ cluster host in the `{$RABBITMQ.API.CLUSTER_HOST}` macro. You can also change the port in the `{$RABBITMQ.API.PORT}` macro and the scheme in the `{$RABBITMQ.API.SCHEME}` macro if necessary.

4. Set the user name and password in the macros `{$RABBITMQ.API.USER}` and `{$RABBITMQ.API.PASSWORD}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$RABBITMQ.API.USER}||`zbx_monitor`|
|{$RABBITMQ.API.PASSWORD}||`zabbix`|
|{$RABBITMQ.API.CLUSTER_HOST}|<p>The hostname or IP of the API endpoint for the RabbitMQ cluster.</p>||
|{$RABBITMQ.API.PORT}|<p>The port of the RabbitMQ API endpoint.</p>|`15672`|
|{$RABBITMQ.API.SCHEME}|<p>The request scheme, which may be HTTP or HTTPS.</p>|`http`|
|{$RABBITMQ.LLD.FILTER.EXCHANGE.MATCHES}|<p>This macro is used in the discovery of exchanges. It can be overridden at host level or its linked template level.</p>|`.*`|
|{$RABBITMQ.LLD.FILTER.EXCHANGE.NOT_MATCHES}|<p>This macro is used in the discovery of exchanges. It can be overridden at host level or its linked template level.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get overview|<p>The HTTP API endpoint that returns cluster-wide metrics.</p>|HTTP agent|rabbitmq.get_overview|
|Get exchanges|<p>The HTTP API endpoint that returns exchanges metrics.</p>|HTTP agent|rabbitmq.get_exchanges|
|Connections total|<p>The total number of connections.</p>|Dependent item|rabbitmq.overview.object_totals.connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.object_totals.connections`</p></li></ul>|
|Channels total|<p>The total number of channels.</p>|Dependent item|rabbitmq.overview.object_totals.channels<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.object_totals.channels`</p></li></ul>|
|Queues total|<p>The total number of queues.</p>|Dependent item|rabbitmq.overview.object_totals.queues<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.object_totals.queues`</p></li></ul>|
|Consumers total|<p>The total number of consumers.</p>|Dependent item|rabbitmq.overview.object_totals.consumers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.object_totals.consumers`</p></li></ul>|
|Exchanges total|<p>The total number of exchanges.</p>|Dependent item|rabbitmq.overview.object_totals.exchanges<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.object_totals.exchanges`</p></li></ul>|
|Messages total|<p>The total number of messages (ready, plus unacknowledged).</p>|Dependent item|rabbitmq.overview.queue_totals.messages<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queue_totals.messages`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages ready for delivery|<p>The number of messages ready for delivery.</p>|Dependent item|rabbitmq.overview.queue_totals.messages.ready<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queue_totals.messages_ready`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages unacknowledged|<p>The number of unacknowledged messages.</p>|Dependent item|rabbitmq.overview.queue_totals.messages.unacknowledged<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queue_totals.messages_unacknowledged`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages acknowledged|<p>The number of messages delivered to clients and acknowledged.</p>|Dependent item|rabbitmq.overview.messages.ack<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.ack`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages acknowledged per second|<p>The rate of messages (per second) delivered to clients and acknowledged.</p>|Dependent item|rabbitmq.overview.messages.ack.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.ack_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages confirmed|<p>The count of confirmed messages.</p>|Dependent item|rabbitmq.overview.messages.confirm<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.confirm`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages confirmed per second|<p>The rate of messages confirmed per second.</p>|Dependent item|rabbitmq.overview.messages.confirm.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.confirm_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages delivered|<p>The sum of messages delivered to consumers: in acknowledgement mode and in no-acknowledgement mode; delivered to consumers in response to the `basic.get`: in acknowledgement mode and in no-acknowledgement mode.</p>|Dependent item|rabbitmq.overview.messages.deliver_get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_get`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages delivered per second|<p>The rate of the sum of messages (per second) delivered to consumers: in acknowledgement mode and in no-acknowledgement mode; delivered to consumers in response to the `basic.get`: in acknowledgement mode and in no-acknowledgement mode.</p>|Dependent item|rabbitmq.overview.messages.deliver_get.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_get_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages published|<p>The count of published messages.</p>|Dependent item|rabbitmq.overview.messages.publish<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages published per second|<p>The rate of messages published per second.</p>|Dependent item|rabbitmq.overview.messages.publish.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages publish_in|<p>The count of messages published from the channels into this overview.</p>|Dependent item|rabbitmq.overview.messages.publish_in<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_in`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages publish_in per second|<p>The rate of messages (per second) published from the channels into this overview.</p>|Dependent item|rabbitmq.overview.messages.publish_in.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_in_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages publish_out|<p>The count of messages published from this overview into queues.</p>|Dependent item|rabbitmq.overview.messages.publish_out<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_out`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages publish_out per second|<p>The rate of messages (per second) published from this overview into queues.</p>|Dependent item|rabbitmq.overview.messages.publish_out.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_out_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages returned unroutable|<p>The count of messages returned to a publisher as unroutable.</p>|Dependent item|rabbitmq.overview.messages.return_unroutable<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.return_unroutable`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages returned unroutable per second|<p>The rate of messages (per second) returned to a publisher as unroutable.</p>|Dependent item|rabbitmq.overview.messages.return_unroutable.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.return_unroutable_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages returned redeliver|<p>The count of subset of messages in the `deliver_get`, which had the `redelivered` flag set.</p>|Dependent item|rabbitmq.overview.messages.redeliver<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.redeliver`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Messages returned redeliver per second|<p>The rate of subset of messages (per second) in the `deliver_get`, which had the `redelivered` flag set.</p>|Dependent item|rabbitmq.overview.messages.redeliver.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.redeliver_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RabbitMQ cluster: Failed to fetch overview data|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/RabbitMQ cluster by HTTP/rabbitmq.get_overview,30m)=1`|Warning|**Manual close**: Yes|

### LLD rule Health Check 3.8.10+ discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Health Check 3.8.10+ discovery|<p>Specific metrics for the versions: up to and including 3.8.10.</p>|Dependent item|rabbitmq.healthcheck.v3810.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.management_version`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Health Check 3.8.10+ discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Healthcheck: alarms in effect in the cluster{#SINGLETON}|<p>Responds a 200 OK if there are no alarms in effect in the cluster, otherwise responds with a 503 Service Unavailable.</p>|HTTP agent|rabbitmq.healthcheck.alarms[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Regular expression: `HTTP\/1\.1\b\s(\d+) \1`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Trigger prototypes for Health Check 3.8.10+ discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RabbitMQ cluster: There are active alarms in the cluster|<p>This is the default API endpoint path: http://{$RABBITMQ.API.CLUSTER_HOST}:{$RABBITMQ.API.PORT}/api/index.html.</p>|`last(/RabbitMQ cluster by HTTP/rabbitmq.healthcheck.alarms[{#SINGLETON}])=0`|Average||

### LLD rule Exchanges discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Exchanges discovery|<p>The metrics for an individual exchange.</p>|Dependent item|rabbitmq.exchanges.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Exchanges discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Get data|<p>The HTTP API endpoint that returns [{#VHOST}][{#EXCHANGE}][{#TYPE}] exchanges metrics</p>|Dependent item|rabbitmq.get_exchanges["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages acknowledged|<p>The number of messages delivered to clients and acknowledged.</p>|Dependent item|rabbitmq.exchange.messages.ack["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.ack`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages acknowledged per second|<p>The rate of messages (per second) delivered to clients and acknowledged.</p>|Dependent item|rabbitmq.exchange.messages.ack.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.ack_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages confirmed|<p>The count of confirmed messages.</p>|Dependent item|rabbitmq.exchange.messages.confirm["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.confirm`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages confirmed per second|<p>The rate of messages confirmed per second.</p>|Dependent item|rabbitmq.exchange.messages.confirm.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.confirm_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages delivered|<p>The sum of messages delivered to consumers: in acknowledgement mode and in no-acknowledgement mode; delivered to consumers in response to the `basic.get`: in acknowledgement mode and in no-acknowledgement mode.</p>|Dependent item|rabbitmq.exchange.messages.deliver_get["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_get`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages delivered per second|<p>The rate of the sum of messages (per second) delivered to consumers: in acknowledgement mode and in no-acknowledgement mode; delivered to consumers in response to the `basic.get`: in acknowledgement mode and in no-acknowledgement mode.</p>|Dependent item|rabbitmq.exchange.messages.deliver_get.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_get_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages published|<p>The count of published messages.</p>|Dependent item|rabbitmq.exchange.messages.publish["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages published per second|<p>The rate of messages published per second.</p>|Dependent item|rabbitmq.exchange.messages.publish.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages publish_in|<p>The count of messages published from the channels into this overview.</p>|Dependent item|rabbitmq.exchange.messages.publish_in["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_in`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages publish_in per second|<p>The rate of messages (per second) published from the channels into this overview.</p>|Dependent item|rabbitmq.exchange.messages.publish_in.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_in_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages publish_out|<p>The count of messages published from this overview into queues.</p>|Dependent item|rabbitmq.exchange.messages.publish_out["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_out`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages publish_out per second|<p>The rate of messages (per second) published from this overview into queues.</p>|Dependent item|rabbitmq.exchange.messages.publish_out.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_out_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages returned unroutable|<p>The count of messages returned to a publisher as unroutable.</p>|Dependent item|rabbitmq.exchange.messages.return_unroutable["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.return_unroutable`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages returned unroutable per second|<p>The rate of messages (per second) returned to a publisher as unroutable.</p>|Dependent item|rabbitmq.exchange.messages.return_unroutable.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.return_unroutable_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages redelivered|<p>The count of subset of messages in the `deliver_get`, which had the `redelivered` flag set.</p>|Dependent item|rabbitmq.exchange.messages.redeliver["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.redeliver`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Exchange {#VHOST}/{#EXCHANGE}/{#TYPE}: Messages redelivered per second|<p>The rate of subset of messages (per second) in the `deliver_get`, which had the `redelivered` flag set.</p>|Dependent item|rabbitmq.exchange.messages.redeliver.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.redeliver_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

# RabbitMQ node by HTTP

## Overview

This template is developed to monitor the messaging broker RabbitMQ node by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

The template collects metrics by polling RabbitMQ management plugin with HTTP agent remotely.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- RabbitMQ 3.5.7, 3.7.7, 3.7.17, 3.7.18, 3.8.5, 3.8.12 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Enable the RabbitMQ management plugin. See the [`RabbitMQ documentation`](https://www.rabbitmq.com/management.html) for the instructions.

2. Create a user to monitor the service:

```bash
rabbitmqctl add_user zbx_monitor <PASSWORD>
rabbitmqctl set_permissions  -p / zbx_monitor "" "" ".*"
rabbitmqctl set_user_tags zbx_monitor monitoring
```

3. Set the hostname or IP address of the RabbitMQ node host in the `{$RABBITMQ.API.HOST}` macro. You can also change the port in the `{$RABBITMQ.API.PORT}` macro and the scheme in the `{$RABBITMQ.API.SCHEME}` macro if necessary.

4. Set the user name and password in the macros `{$RABBITMQ.API.USER}` and `{$RABBITMQ.API.PASSWORD}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$RABBITMQ.API.USER}||`zbx_monitor`|
|{$RABBITMQ.API.PASSWORD}||`zabbix`|
|{$RABBITMQ.CLUSTER.NAME}|<p>The name of the RabbitMQ cluster.</p>|`rabbit`|
|{$RABBITMQ.API.PORT}|<p>The port of the RabbitMQ API endpoint.</p>|`15672`|
|{$RABBITMQ.API.SCHEME}|<p>The request scheme, which may be HTTP or HTTPS.</p>|`http`|
|{$RABBITMQ.API.HOST}|<p>The hostname or IP of the API endpoint for the RabbitMQ.</p>||
|{$RABBITMQ.LLD.FILTER.QUEUE.MATCHES}|<p>This macro is used in the discovery of queues. It can be overridden at host level or its linked template level.</p>|`.*`|
|{$RABBITMQ.LLD.FILTER.QUEUE.NOT_MATCHES}|<p>This macro is used in the discovery of queues. It can be overridden at host level or its linked template level.</p>|`CHANGE_IF_NEEDED`|
|{$RABBITMQ.RESPONSE_TIME.MAX.WARN}|<p>The maximum response time by the RabbitMQ expressed in seconds for a trigger expression.</p>|`10`|
|{$RABBITMQ.MESSAGES.MAX.WARN}|<p>The maximum number of messages in the queue for a trigger expression.</p>|`1000`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Service ping||Simple check|net.tcp.service["{$RABBITMQ.API.SCHEME}","{$RABBITMQ.API.HOST}","{$RABBITMQ.API.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Get node overview|<p>The HTTP API endpoint that returns cluster-wide metrics.</p>|HTTP agent|rabbitmq.get_node_overview<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get nodes|<p>The HTTP API endpoint that returns metrics of the nodes.</p>|HTTP agent|rabbitmq.get_nodes|
|Get queues|<p>The HTTP API endpoint that returns metrics of the queues metrics.</p>|HTTP agent|rabbitmq.get_queues|
|Management plugin version|<p>The version of the management plugin in use.</p>|Dependent item|rabbitmq.node.overview.management_version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.management_version`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|RabbitMQ version|<p>The version of the RabbitMQ on the node, which processed this request.</p>|Dependent item|rabbitmq.node.overview.rabbitmq_version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rabbitmq_version`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Used file descriptors|<p>The descriptors of the used file.</p>|Dependent item|rabbitmq.node.fd_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.fd_used`</p></li></ul>|
|Free disk space|<p>The current free disk space.</p>|Dependent item|rabbitmq.node.disk_free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disk_free`</p></li></ul>|
|Disk free limit|<p>The free space limit of a disk expressed in bytes.</p>|Dependent item|rabbitmq.node.disk_free_limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disk_free_limit`</p></li></ul>|
|Memory used|<p>The memory usage expressed in bytes.</p>|Dependent item|rabbitmq.node.mem_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_used`</p></li></ul>|
|Memory limit|<p>The memory usage with high watermark properties expressed in bytes.</p>|Dependent item|rabbitmq.node.mem_limit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_limit`</p></li></ul>|
|Runtime run queue|<p>The average number of Erlang processes waiting to run.</p>|Dependent item|rabbitmq.node.run_queue<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.run_queue`</p></li></ul>|
|Sockets used|<p>The number of file descriptors used as sockets.</p>|Dependent item|rabbitmq.node.sockets_used<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sockets_used`</p></li></ul>|
|Sockets available|<p>The file descriptors available for use as sockets.</p>|Dependent item|rabbitmq.node.sockets_total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sockets_total`</p></li></ul>|
|Number of network partitions|<p>The number of network partitions, which this node "sees".</p>|Dependent item|rabbitmq.node.partitions<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.partitions`</p></li><li><p>JavaScript: `return JSON.parse(value).length;`</p></li></ul>|
|Is running|<p>It "sees" whether the node is running or not.</p>|Dependent item|rabbitmq.node.running<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.running`</p></li><li>Boolean to decimal</li></ul>|
|Memory alarm|<p>It checks whether the host has a memory alarm or not.</p>|Dependent item|rabbitmq.node.mem_alarm<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.mem_alarm`</p></li><li>Boolean to decimal</li></ul>|
|Disk free alarm|<p>It checks whether the node has a disk alarm or not.</p>|Dependent item|rabbitmq.node.disk_free_alarm<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.disk_free_alarm`</p></li><li>Boolean to decimal</li></ul>|
|Uptime|<p>Uptime expressed in milliseconds.</p>|Dependent item|rabbitmq.node.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.uptime`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Service response time||Simple check|net.tcp.service.perf["{$RABBITMQ.API.SCHEME}","{$RABBITMQ.API.HOST}","{$RABBITMQ.API.PORT}"]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RabbitMQ node: Service is down||`last(/RabbitMQ node by HTTP/net.tcp.service["{$RABBITMQ.API.SCHEME}","{$RABBITMQ.API.HOST}","{$RABBITMQ.API.PORT}"])=0`|Average|**Manual close**: Yes|
|RabbitMQ node: Failed to fetch nodes data|<p>Zabbix has not received any data for items for the last 30 minutes.</p>|`nodata(/RabbitMQ node by HTTP/rabbitmq.get_nodes,30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>RabbitMQ node: Service is down</li></ul>|
|RabbitMQ node: Version has changed|<p>RabbitMQ version has changed. Acknowledge to close the problem manually.</p>|`last(/RabbitMQ node by HTTP/rabbitmq.node.overview.rabbitmq_version,#1)<>last(/RabbitMQ node by HTTP/rabbitmq.node.overview.rabbitmq_version,#2) and length(last(/RabbitMQ node by HTTP/rabbitmq.node.overview.rabbitmq_version))>0`|Info|**Manual close**: Yes|
|RabbitMQ node: Number of network partitions is too high|<p>For more details see [Detecting Network Partitions](https://www.rabbitmq.com/partitions.html#detecting).</p>|`min(/RabbitMQ node by HTTP/rabbitmq.node.partitions,5m)>0`|Warning||
|RabbitMQ node: Node is not running|<p>RabbitMQ node is not running.</p>|`max(/RabbitMQ node by HTTP/rabbitmq.node.running,5m)=0`|Average|**Depends on**:<br><ul><li>RabbitMQ node: Service is down</li></ul>|
|RabbitMQ node: Memory alarm|<p>For more details see [Memory Alarms](https://www.rabbitmq.com/memory.html).</p>|`last(/RabbitMQ node by HTTP/rabbitmq.node.mem_alarm)=1`|Average||
|RabbitMQ node: Free disk space alarm|<p>For more details see [Free Disk Space Alarms](https://www.rabbitmq.com/disk-alarms.html).</p>|`last(/RabbitMQ node by HTTP/rabbitmq.node.disk_free_alarm)=1`|Average||
|RabbitMQ node: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/RabbitMQ node by HTTP/rabbitmq.node.uptime)<10m`|Info|**Manual close**: Yes|
|RabbitMQ node: Service response time is too high||`min(/RabbitMQ node by HTTP/net.tcp.service.perf["{$RABBITMQ.API.SCHEME}","{$RABBITMQ.API.HOST}","{$RABBITMQ.API.PORT}"],5m)>{$RABBITMQ.RESPONSE_TIME.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>RabbitMQ node: Service is down</li></ul>|

### LLD rule Health Check 3.8.10+ discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Health Check 3.8.10+ discovery|<p>Specific metrics for the versions: up to and including 3.8.10.</p>|Dependent item|rabbitmq.healthcheck.v3810.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.management_version`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Health Check 3.8.10+ discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Healthcheck: local alarms in effect on this node{#SINGLETON}|<p>It responds with a status code `200 OK` if there are no alarms in effect in the cluster.</p><p>Otherwise, it responds with a status code `503 Service Unavailable`.</p>|HTTP agent|rabbitmq.healthcheck.local_alarms[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Regular expression: `HTTP\/1\.1\b\s(\d+) \1`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Healthcheck: expiration date on the certificates{#SINGLETON}|<p>It checks the expiration date on the certificates for every listener configured to use the Transport Layer Security (TLS).</p><p>It responds with a status code `200 OK` if all the certificates are valid (have not expired).</p><p>Otherwise, it responds with a status code `503 Service Unavailable`.</p>|HTTP agent|rabbitmq.healthcheck.certificate_expiration[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Regular expression: `HTTP\/1\.1\b\s(\d+) \1`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Healthcheck: virtual hosts on this node{#SINGLETON}|<p>It responds with It responds with a status code `200 OK` if all virtual hosts are running on the target node.</p><p>Otherwise it responds with a status code `503 Service Unavailable`.</p>|HTTP agent|rabbitmq.healthcheck.virtual_hosts[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Regular expression: `HTTP\/1\.1\b\s(\d+) \1`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Healthcheck: classic mirrored queues without synchronized mirrors online{#SINGLETON}|<p>It checks if there are classic mirrored queues without synchronized mirrors online (queues that would potentially lose data if the target node is shut down).</p><p>It responds with a status code `200 OK` if there are no such classic mirrored queues.</p><p>Otherwise, it responds with a status code `503 Service Unavailable`.</p>|HTTP agent|rabbitmq.healthcheck.mirror_sync[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Regular expression: `HTTP\/1\.1\b\s(\d+) \1`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Healthcheck: queues with minimum online quorum{#SINGLETON}|<p>It checks if there are quorum queues with minimum online quorum (queues that would lose their quorum and availability if the target node is shut down).</p><p>It responds with a status code `200 OK` if there are no such quorum queues.</p><p>Otherwise, it responds with a status code `503 Service Unavailable`.</p>|HTTP agent|rabbitmq.healthcheck.quorum[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>Regular expression: `HTTP\/1\.1\b\s(\d+) \1`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Trigger prototypes for Health Check 3.8.10+ discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RabbitMQ node: There are active alarms in the node|<p>This is the default API endpoint path: http://{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/index.html.</p>|`last(/RabbitMQ node by HTTP/rabbitmq.healthcheck.local_alarms[{#SINGLETON}])=0`|Average||
|RabbitMQ node: There are valid TLS certificates expiring in the next month|<p>This is the default API endpoint path: http://{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/index.html.</p>|`last(/RabbitMQ node by HTTP/rabbitmq.healthcheck.certificate_expiration[{#SINGLETON}])=0`|Average||
|RabbitMQ node: There are not running virtual hosts|<p>This is the default API endpoint path: http://{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/index.html.</p>|`last(/RabbitMQ node by HTTP/rabbitmq.healthcheck.virtual_hosts[{#SINGLETON}])=0`|Average||
|RabbitMQ node: There are queues that could potentially lose data if this node goes offline.|<p>This is the default API endpoint path: http://{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/index.html.</p>|`last(/RabbitMQ node by HTTP/rabbitmq.healthcheck.mirror_sync[{#SINGLETON}])=0`|Average||
|RabbitMQ node: There are queues that would lose their quorum and availability if this node is shut down.|<p>This is the default API endpoint path: http://{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/index.html.</p>|`last(/RabbitMQ node by HTTP/rabbitmq.healthcheck.quorum[{#SINGLETON}])=0`|Average||

### LLD rule Health Check 3.8.9- discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Health Check 3.8.9- discovery|<p>Specific metrics for the versions: up to and including 3.8.4.</p>|Dependent item|rabbitmq.healthcheck.v389.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.management_version`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Health Check 3.8.9- discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Healthcheck{#SINGLETON}|<p>It checks whether the RabbitMQ application is running; and whether the channels and queues can be listed successfully; and that no alarms are in effect.</p>|HTTP agent|rabbitmq.healthcheck[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Boolean to decimal</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Trigger prototypes for Health Check 3.8.9- discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RabbitMQ node: Node healthcheck failed|<p>For more details see [Health Checks](https://www.rabbitmq.com/monitoring.html#health-checks).</p>|`last(/RabbitMQ node by HTTP/rabbitmq.healthcheck[{#SINGLETON}])=0`|Average||

### LLD rule Queues discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Queues discovery|<p>The metrics for an individual queue.</p>|Dependent item|rabbitmq.queues.discovery<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Queues discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Queue [{#VHOST}][{#QUEUE}]: Get data|<p>The HTTP API endpoint that returns [{#VHOST}][{#QUEUE}] queue metrics</p>|Dependent item|rabbitmq.get_exchanges["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "{#QUEUE}" && @.vhost == "{#VHOST}")].first()`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages total|<p>The count of total messages in the queue.</p>|Dependent item|rabbitmq.queue.messages["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.messages`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages per second|<p>The count of total messages per second in the queue.</p>|Dependent item|rabbitmq.queue.messages.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.messages_details.rate`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Consumers|<p>The number of consumers.</p>|Dependent item|rabbitmq.queue.consumers["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.consumers`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Memory|<p>The bytes of memory consumed by the Erlang process associated with the queue, including stack, heap and internal structures.</p>|Dependent item|rabbitmq.queue.memory["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.memory`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages ready|<p>The number of messages ready to be delivered to clients.</p>|Dependent item|rabbitmq.queue.messages_ready["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.messages_ready`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages ready per second|<p>The number of messages per second ready to be delivered to clients.</p>|Dependent item|rabbitmq.queue.messages_ready.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.messages_ready_details.rate`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages unacknowledged|<p>The number of messages delivered to clients but not yet acknowledged.</p>|Dependent item|rabbitmq.queue.messages_unacknowledged["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.messages_unacknowledged`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages unacknowledged per second|<p>The number of messages per second delivered to clients but not yet acknowledged.</p>|Dependent item|rabbitmq.queue.messages_unacknowledged.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.messages_unacknowledged_details.rate`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages acknowledged|<p>The number of messages delivered to clients and acknowledged.</p>|Dependent item|rabbitmq.queue.messages.ack["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.ack`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages acknowledged per second|<p>The number of messages (per second) delivered to clients and acknowledged.</p>|Dependent item|rabbitmq.queue.messages.ack.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.ack_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages delivered|<p>The count of messages delivered to consumers in acknowledgement mode.</p>|Dependent item|rabbitmq.queue.messages.deliver["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages delivered per second|<p>The count of messages (per second) delivered to consumers in acknowledgement mode.</p>|Dependent item|rabbitmq.queue.messages.deliver.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Sum of messages delivered|<p>The sum of messages delivered to consumers: in acknowledgement mode and in no-acknowledgement mode; delivered to consumers in response to the `basic.get`: in acknowledgement mode and in no-acknowledgement mode.</p>|Dependent item|rabbitmq.queue.messages.deliver_get["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_get`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Sum of messages delivered per second|<p>The rate of delivery per second. The sum of messages delivered (per second) to consumers: in acknowledgement mode and in no-acknowledgement mode; delivered to consumers in response to `basic.get`: in acknowledgement mode and in no-acknowledgement mode.</p>|Dependent item|rabbitmq.queue.messages.deliver_get.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_get_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages published|<p>The count of published messages.</p>|Dependent item|rabbitmq.queue.messages.publish["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages published per second|<p>The rate of published messages per second.</p>|Dependent item|rabbitmq.queue.messages.publish.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages redelivered|<p>The count of subset of messages in the `deliver_get` queue with the `redelivered` flag set.</p>|Dependent item|rabbitmq.queue.messages.redeliver["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.redeliver`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|Queue [{#VHOST}][{#QUEUE}]: Messages redelivered per second|<p>The rate of messages redelivered per second.</p>|Dependent item|rabbitmq.queue.messages.redeliver.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.redeliver_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Trigger prototypes for Queues discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RabbitMQ node: Too many messages in queue [{#VHOST}][{#QUEUE}]||`min(/RabbitMQ node by HTTP/rabbitmq.queue.messages["{#VHOST}/{#QUEUE}"],5m)>{$RABBITMQ.MESSAGES.MAX.WARN:"{#QUEUE}"}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

