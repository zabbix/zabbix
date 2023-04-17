
# RabbitMQ cluster by Zabbix agent

## Overview

The template to monitor RabbitMQ by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `RabbitMQ Cluster` — collects metrics by polling [RabbitMQ management plugin](https://www.rabbitmq.com/management.html) with Zabbix agent.


## Requirements

Zabbix version: 6.4 and higher.

## Tested versions

This template has been tested on:
- RabbitMQ 3.5.7, 3.7.7, 3.7.17, 3.7.18, 3.8.5, 3.8.12 

## Configuration

> Zabbix should be configured according to instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box) section.

## Setup

Enable the RabbitMQ management plugin. See [RabbitMQ's documentation](https://www.rabbitmq.com/management.html) to enable it.

Create a user to monitor the service:

```bash
rabbitmqctl add_user zbx_monitor <PASSWORD>
rabbitmqctl set_permissions  -p / zbx_monitor "" "" ".*"
rabbitmqctl set_user_tags zbx_monitor monitoring
```

Login and password are also set in macros:

- {$RABBITMQ.API.USER}
- {$RABBITMQ.API.PASSWORD}

If your cluster consists of several nodes, it is recommended to assign the `cluster` template to a separate balancing host.
In the case of a single-node installation, you can assign the `cluster` template to one host with a `node` template.

If you use another API endpoint, then don't forget to change `{$RABBITMQ.API.CLUSTER_HOST}` macro.

Install and setup [Zabbix agent](https://www.zabbix.com/documentation/6.4/manual/installation/install_from_packages).


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$RABBITMQ.API.USER}||`zbx_monitor`|
|{$RABBITMQ.API.PASSWORD}||`zabbix`|
|{$RABBITMQ.API.CLUSTER_HOST}|<p>The hostname or IP of RabbitMQ cluster API endpoint</p>|`127.0.0.1`|
|{$RABBITMQ.API.PORT}|<p>The port of RabbitMQ API endpoint</p>|`15672`|
|{$RABBITMQ.API.SCHEME}|<p>Request scheme which may be http or https</p>|`http`|
|{$RABBITMQ.LLD.FILTER.EXCHANGE.MATCHES}|<p>Filter of discoverable exchanges</p>|`.*`|
|{$RABBITMQ.LLD.FILTER.EXCHANGE.NOT_MATCHES}|<p>Filter to exclude discovered exchanges</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|RabbitMQ: Get overview|<p>The HTTP API endpoint that returns cluster-wide metrics</p>|Zabbix agent|web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.CLUSTER_HOST}:{$RABBITMQ.API.PORT}/api/overview"]<p>**Preprocessing**</p><ul><li>Regular expression: `\n\s?\n(.*) \1`</li></ul>|
|RabbitMQ: Get exchanges|<p>The HTTP API endpoint that returns exchanges metrics</p>|Zabbix agent|web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.CLUSTER_HOST}:{$RABBITMQ.API.PORT}/api/exchanges"]<p>**Preprocessing**</p><ul><li>Regular expression: `\n\s?\n(.*) \1`</li></ul>|
|RabbitMQ: Connections total|<p>Total number of connections</p>|Dependent item|rabbitmq.overview.object_totals.connections<p>**Preprocessing**</p><ul><li>JSON Path: `$.object_totals.connections`</li></ul>|
|RabbitMQ: Channels total|<p>Total number of channels</p>|Dependent item|rabbitmq.overview.object_totals.channels<p>**Preprocessing**</p><ul><li>JSON Path: `$.object_totals.channels`</li></ul>|
|RabbitMQ: Queues total|<p>Total number of queues</p>|Dependent item|rabbitmq.overview.object_totals.queues<p>**Preprocessing**</p><ul><li>JSON Path: `$.object_totals.queues`</li></ul>|
|RabbitMQ: Consumers total|<p>Total number of consumers</p>|Dependent item|rabbitmq.overview.object_totals.consumers<p>**Preprocessing**</p><ul><li>JSON Path: `$.object_totals.consumers`</li></ul>|
|RabbitMQ: Exchanges total|<p>Total number of exchanges</p>|Dependent item|rabbitmq.overview.object_totals.exchanges<p>**Preprocessing**</p><ul><li>JSON Path: `$.object_totals.exchanges`</li></ul>|
|RabbitMQ: Messages total|<p>Total number of messages (ready plus unacknowledged)</p>|Dependent item|rabbitmq.overview.queue_totals.messages<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queue_totals.messages`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages ready for delivery|<p>Number of messages ready for deliver</p>|Dependent item|rabbitmq.overview.queue_totals.messages.ready<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queue_totals.messages_ready`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages unacknowledged|<p>Number of unacknowledged messages</p>|Dependent item|rabbitmq.overview.queue_totals.messages.unacknowledged<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.queue_totals.messages_unacknowledged`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages acknowledged|<p>Number of messages delivered to clients and acknowledged</p>|Dependent item|rabbitmq.overview.messages.ack<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.ack`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages acknowledged per second|<p>Rate of messages delivered to clients and acknowledged per second</p>|Dependent item|rabbitmq.overview.messages.ack.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.ack_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages confirmed|<p>Count of messages confirmed</p>|Dependent item|rabbitmq.overview.messages.confirm<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.confirm`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages confirmed per second|<p>Rate of messages confirmed per second</p>|Dependent item|rabbitmq.overview.messages.confirm.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.confirm_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages delivered|<p>Sum of messages delivered in acknowledgement mode to consumers, in no-acknowledgement mode to consumers, in acknowledgement mode in response to basic.get, and in no-acknowledgement mode in response to basic.get</p>|Dependent item|rabbitmq.overview.messages.deliver_get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_get`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages delivered per second|<p>Rate per second of the sum of messages delivered in acknowledgement mode to consumers, in no-acknowledgement mode to consumers, in acknowledgement mode in response to basic.get, and in no-acknowledgement mode in response to basic.get</p>|Dependent item|rabbitmq.overview.messages.deliver_get.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_get_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages published|<p>Count of messages published</p>|Dependent item|rabbitmq.overview.messages.publish<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages published per second|<p>Rate of messages published per second</p>|Dependent item|rabbitmq.overview.messages.publish.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages publish_in|<p>Count of messages published from channels into this overview</p>|Dependent item|rabbitmq.overview.messages.publish_in<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_in`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages publish_in per second|<p>Rate of messages published from channels into this overview per sec</p>|Dependent item|rabbitmq.overview.messages.publish_in.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_in_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages publish_out|<p>Count of messages published from this overview into queues</p>|Dependent item|rabbitmq.overview.messages.publish_out<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_out`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages publish_out per second|<p>Rate of messages published from this overview into queues per second,0,rabbitmq,total msgs pub out rate</p>|Dependent item|rabbitmq.overview.messages.publish_out.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_out_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages returned unroutable|<p>Count of messages returned to publisher as unroutable</p>|Dependent item|rabbitmq.overview.messages.return_unroutable<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.return_unroutable`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages returned unroutable per second|<p>Rate of messages returned to publisher as unroutable per second</p>|Dependent item|rabbitmq.overview.messages.return_unroutable.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.return_unroutable_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages returned redeliver|<p>Count of subset of messages in deliver_get which had the redelivered flag set</p>|Dependent item|rabbitmq.overview.messages.redeliver<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.redeliver`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Messages returned redeliver per second|<p>Rate of subset of messages in deliver_get which had the redelivered flag set per second</p>|Dependent item|rabbitmq.overview.messages.redeliver.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.redeliver_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RabbitMQ: Failed to fetch overview data|<p>Zabbix has not received data for items for the last 30 minutes</p>|`nodata(/RabbitMQ cluster by Zabbix agent/web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.CLUSTER_HOST}:{$RABBITMQ.API.PORT}/api/overview"],30m)=1`|Warning|**Manual close**: Yes|

### LLD rule Health Check 3.8.10+ discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Health Check 3.8.10+ discovery|<p>Version 3.8.10+ specific metrics</p>|Dependent item|rabbitmq.healthcheck.v3810.discovery<p>**Preprocessing**</p><ul><li>JSON Path: `$.management_version`</li><li>JavaScript: `The text is too long. Please see the template.`</li></ul>|

### Item prototypes for Health Check 3.8.10+ discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|RabbitMQ: Healthcheck: alarms in effect in the cluster{#SINGLETON}|<p>Responds a 200 OK if there are no alarms in effect in the cluster, otherwise responds with a 503 Service Unavailable.</p>|Zabbix agent|web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.CLUSTER_HOST}:{$RABBITMQ.API.PORT}/api/health/checks/alarms{#SINGLETON}"]<p>**Preprocessing**</p><ul><li>Regular expression: `HTTP\/1\.1\b\s(\d+) \1`</li><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `3h`</li></ul>|

### Trigger prototypes for Health Check 3.8.10+ discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RabbitMQ: There are active alarms in the cluster|<p>http://{HOST.CONN}:{$RABBITMQ.API.PORT}/api/index.html</p>|`last(/RabbitMQ cluster by Zabbix agent/web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.CLUSTER_HOST}:{$RABBITMQ.API.PORT}/api/health/checks/alarms{#SINGLETON}"])=0`|Average||

### LLD rule Exchanges discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Exchanges discovery|<p>Individual exchange metrics</p>|Dependent item|rabbitmq.exchanges.discovery|

### Item prototypes for Exchanges discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Get data|<p>The HTTP API endpoint that returns [{#VHOST}][{#EXCHANGE}][{#TYPE}] exchanges metrics</p>|Dependent item|rabbitmq.get_exchanges["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li>JSON Path: `The text is too long. Please see the template.`</li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages acknowledged|<p>Number of messages delivered to clients and acknowledged</p>|Dependent item|rabbitmq.exchange.messages.ack["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.ack`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages acknowledged per second|<p>Rate of messages delivered to clients and acknowledged per second</p>|Dependent item|rabbitmq.exchange.messages.ack.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.ack_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages confirmed|<p>Count of messages confirmed</p>|Dependent item|rabbitmq.exchange.messages.confirm["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.confirm`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages confirmed per second|<p>Rate of messages confirmed per second</p>|Dependent item|rabbitmq.exchange.messages.confirm.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.confirm_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages delivered|<p>Sum of messages delivered in acknowledgement mode to consumers, in no-acknowledgement mode to consumers, in acknowledgement mode in response to basic.get, and in no-acknowledgement mode in response to basic.get</p>|Dependent item|rabbitmq.exchange.messages.deliver_get["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_get`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages delivered per second|<p>Rate per second of the sum of messages delivered in acknowledgement mode to consumers, in no-acknowledgement mode to consumers, in acknowledgement mode in response to basic.get, and in no-acknowledgement mode in response to basic.get</p>|Dependent item|rabbitmq.exchange.messages.deliver_get.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_get_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages published|<p>Count of messages published</p>|Dependent item|rabbitmq.exchange.messages.publish["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages published per second|<p>Rate of messages published per second</p>|Dependent item|rabbitmq.exchange.messages.publish.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages publish_in|<p>Count of messages published from channels into this overview</p>|Dependent item|rabbitmq.exchange.messages.publish_in["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_in`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages publish_in per second|<p>Rate of messages published from channels into this overview per sec</p>|Dependent item|rabbitmq.exchange.messages.publish_in.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_in_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages publish_out|<p>Count of messages published from this overview into queues</p>|Dependent item|rabbitmq.exchange.messages.publish_out["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_out`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages publish_out per second|<p>Rate of messages published from this overview into queues per second,0,rabbitmq,total msgs pub out rate</p>|Dependent item|rabbitmq.exchange.messages.publish_out.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_out_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages returned unroutable|<p>Count of messages returned to publisher as unroutable</p>|Dependent item|rabbitmq.exchange.messages.return_unroutable["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.return_unroutable`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages returned unroutable per second|<p>Rate of messages returned to publisher as unroutable per second</p>|Dependent item|rabbitmq.exchange.messages.return_unroutable.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.return_unroutable_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange [{#VHOST}][{#EXCHANGE}][{#TYPE}]: Messages redelivered|<p>Count of subset of messages in deliver_get which had the redelivered flag set</p>|Dependent item|rabbitmq.exchange.messages.redeliver["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.redeliver`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Exchange {#VHOST}/{#EXCHANGE}/{#TYPE}: Messages redelivered per second|<p>Rate of subset of messages in deliver_get which had the redelivered flag set per second</p>|Dependent item|rabbitmq.exchange.messages.redeliver.rate["{#VHOST}/{#EXCHANGE}/{#TYPE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.redeliver_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

# RabbitMQ node by Zabbix agent

## Overview

The template to monitor RabbitMQ by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

  Template `RabbitMQ Node` — (Zabbix version >= 4.2) collects metrics by polling [RabbitMQ management plugin](https://www.rabbitmq.com/management.html) with Zabbix agent.

  It also uses Zabbix agent to collect `RabbitMQ` Linux process stats like CPU usage, memory usage and whether process is running or not.


## Requirements

Zabbix version: 6.4 and higher.

## Tested versions

This template has been tested on:
- RabbitMQ 3.5.7, 3.7.7, 3.7.17, 3.7.18, 3.8.5, 3.8.12 

## Configuration

> Zabbix should be configured according to instructions in the [Templates out of the box](https://www.zabbix.com/documentation/6.4/manual/config/templates_out_of_the_box) section.

## Setup

Enable the RabbitMQ management plugin. See [RabbitMQ's documentation](https://www.rabbitmq.com/management.html) to enable it.

Create a user to monitor the service:

```bash
rabbitmqctl add_user zbx_monitor <PASSWORD>
rabbitmqctl set_permissions  -p / zbx_monitor "" "" ".*"
rabbitmqctl set_user_tags zbx_monitor monitoring
```

Login and password are also set in macros:

- {$RABBITMQ.API.USER}
- {$RABBITMQ.API.PASSWORD}

If you use another API endpoint, then don't forget to change `{$RABBITMQ.API.HOST}` macro.
Install and setup [Zabbix agent](https://www.zabbix.com/documentation/6.4/manual/installation/install_from_packages).


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$RABBITMQ.API.USER}||`zbx_monitor`|
|{$RABBITMQ.API.PASSWORD}||`zabbix`|
|{$RABBITMQ.CLUSTER.NAME}|<p>The name of RabbitMQ cluster</p>|`rabbit`|
|{$RABBITMQ.API.PORT}|<p>The port of RabbitMQ API endpoint</p>|`15672`|
|{$RABBITMQ.API.SCHEME}|<p>Request scheme which may be http or https</p>|`http`|
|{$RABBITMQ.API.HOST}|<p>The hostname or IP of RabbitMQ API endpoint</p>|`127.0.0.1`|
|{$RABBITMQ.PROCESS_NAME}|<p>RabbitMQ server process name</p>|`beam.smp`|
|{$RABBITMQ.LLD.FILTER.QUEUE.MATCHES}|<p>Filter of discoverable queues</p>|`.*`|
|{$RABBITMQ.LLD.FILTER.QUEUE.NOT_MATCHES}|<p>Filter to exclude discovered queues</p>|`CHANGE_IF_NEEDED`|
|{$RABBITMQ.RESPONSE_TIME.MAX.WARN}|<p>Maximum RabbitMQ response time in seconds for trigger expression</p>|`10`|
|{$RABBITMQ.MESSAGES.MAX.WARN}|<p>Maximum number of messages in the queue for trigger expression</p>|`1000`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|RabbitMQ: Service ping| |Zabbix agent|net.tcp.service["{$RABBITMQ.API.SCHEME}","{$RABBITMQ.API.HOST}","{$RABBITMQ.API.PORT}"]<p>**Preprocessing**</p><ul><li>Discard unchanged with heartbeat: `10m`</li></ul>|
|RabbitMQ: Number of processes running| |Zabbix agent|proc.num["{$RABBITMQ.PROCESS_NAME}"]|
|RabbitMQ: Get node overview|<p>The HTTP API endpoint that returns cluster-wide metrics</p>|Zabbix agent|web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/overview"]<p>**Preprocessing**</p><ul><li>Regular expression: `\n\s?\n(.*) \1`</li></ul>|
|RabbitMQ: Get nodes|<p>The HTTP API endpoint that returns nodes metrics</p>|Zabbix agent|web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/nodes/{$RABBITMQ.CLUSTER.NAME}@{HOST.NAME}?memory=true"]<p>**Preprocessing**</p><ul><li>Regular expression: `\n\s?\n(.*) \1`</li></ul>|
|RabbitMQ: Get queues|<p>The HTTP API endpoint that returns queues metrics</p>|Zabbix agent|web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/queues"]<p>**Preprocessing**</p><ul><li>Regular expression: `\n\s?\n(.*) \1`</li></ul>|
|RabbitMQ: Management plugin version|<p>Version of the management plugin in use</p>|Dependent item|rabbitmq.node.overview.management_version<p>**Preprocessing**</p><ul><li>JSON Path: `$.management_version`</li><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|RabbitMQ: RabbitMQ version|<p>Version of RabbitMQ on the node which processed this request</p>|Dependent item|rabbitmq.node.overview.rabbitmq_version<p>**Preprocessing**</p><ul><li>JSON Path: `$.rabbitmq_version`</li><li>Discard unchanged with heartbeat: `1d`</li></ul>|
|RabbitMQ: Used file descriptors|<p>Used file descriptors</p>|Dependent item|rabbitmq.node.fd_used<p>**Preprocessing**</p><ul><li>JSON Path: `$.fd_used`</li></ul>|
|RabbitMQ: Free disk space|<p>Current free disk space</p>|Dependent item|rabbitmq.node.disk_free<p>**Preprocessing**</p><ul><li>JSON Path: `$.disk_free`</li></ul>|
|RabbitMQ: Memory used|<p>Memory used in bytes</p>|Dependent item|rabbitmq.node.mem_used<p>**Preprocessing**</p><ul><li>JSON Path: `$.mem_used`</li></ul>|
|RabbitMQ: Memory limit|<p>Memory usage high watermark in bytes</p>|Dependent item|rabbitmq.node.mem_limit<p>**Preprocessing**</p><ul><li>JSON Path: `$.mem_limit`</li></ul>|
|RabbitMQ: Disk free limit|<p>Disk free space limit in bytes</p>|Dependent item|rabbitmq.node.disk_free_limit<p>**Preprocessing**</p><ul><li>JSON Path: `$.disk_free_limit`</li></ul>|
|RabbitMQ: Runtime run queue|<p>Average number of Erlang processes waiting to run</p>|Dependent item|rabbitmq.node.run_queue<p>**Preprocessing**</p><ul><li>JSON Path: `$.run_queue`</li></ul>|
|RabbitMQ: Sockets used|<p>Number of file descriptors used as sockets</p>|Dependent item|rabbitmq.node.sockets_used<p>**Preprocessing**</p><ul><li>JSON Path: `$.sockets_used`</li></ul>|
|RabbitMQ: Sockets available|<p>File descriptors available for use as sockets</p>|Dependent item|rabbitmq.node.sockets_total<p>**Preprocessing**</p><ul><li>JSON Path: `$.sockets_total`</li></ul>|
|RabbitMQ: Number of network partitions|<p>Number of network partitions this node is seeing</p>|Dependent item|rabbitmq.node.partitions<p>**Preprocessing**</p><ul><li>JSON Path: `$.partitions`</li><li>JavaScript: `return JSON.parse(value).length;`</li></ul>|
|RabbitMQ: Is running|<p>Is the node running or not</p>|Dependent item|rabbitmq.node.running<p>**Preprocessing**</p><ul><li>JSON Path: `$.running`</li><li>Boolean to decimal</li></ul>|
|RabbitMQ: Memory alarm|<p>Does the host has memory alarm</p>|Dependent item|rabbitmq.node.mem_alarm<p>**Preprocessing**</p><ul><li>JSON Path: `$.mem_alarm`</li><li>Boolean to decimal</li></ul>|
|RabbitMQ: Disk free alarm|<p>Does the node have disk alarm</p>|Dependent item|rabbitmq.node.disk_free_alarm<p>**Preprocessing**</p><ul><li>JSON Path: `$.disk_free_alarm`</li><li>Boolean to decimal</li></ul>|
|RabbitMQ: Uptime|<p>Uptime in milliseconds</p>|Dependent item|rabbitmq.node.uptime<p>**Preprocessing**</p><ul><li>JSON Path: `$.uptime`</li><li>Custom multiplier: `0.001`</li></ul>|
|RabbitMQ: Memory usage (rss)|<p>Resident set size memory used by process in bytes.</p>|Zabbix agent|proc.mem["{$RABBITMQ.PROCESS_NAME}",,,,rss]|
|RabbitMQ: Memory usage (vsize)|<p>Virtual memory size used by process in bytes.</p>|Zabbix agent|proc.mem["{$RABBITMQ.PROCESS_NAME}",,,,vsize]|
|RabbitMQ: CPU utilization|<p>Process CPU utilization percentage.</p>|Zabbix agent|proc.cpu.util["{$RABBITMQ.PROCESS_NAME}"]|
|RabbitMQ: Service response time| |Zabbix agent|net.tcp.service.perf["{$RABBITMQ.API.SCHEME}","{$RABBITMQ.API.HOST}","{$RABBITMQ.API.PORT}"]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RabbitMQ: Service is down||`last(/RabbitMQ node by Zabbix agent/net.tcp.service["{$RABBITMQ.API.SCHEME}","{$RABBITMQ.API.HOST}","{$RABBITMQ.API.PORT}"])=0`|Average|**Manual close**: Yes<br>**Depends on**:<br><ul><li>RabbitMQ: Process is not running</li></ul>|
|RabbitMQ: Process is not running||`last(/RabbitMQ node by Zabbix agent/proc.num["{$RABBITMQ.PROCESS_NAME}"])=0`|High||
|RabbitMQ: Failed to fetch nodes data|<p>Zabbix has not received data for items for the last 30 minutes.</p>|`nodata(/RabbitMQ node by Zabbix agent/web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/nodes/{$RABBITMQ.CLUSTER.NAME}@{HOST.NAME}?memory=true"],30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>RabbitMQ: Process is not running</li><li>RabbitMQ: Service is down</li></ul>|
|RabbitMQ: Version has changed|<p>RabbitMQ version has changed. Acknowledge to close manually.</p>|`last(/RabbitMQ node by Zabbix agent/rabbitmq.node.overview.rabbitmq_version,#1)<>last(/RabbitMQ node by Zabbix agent/rabbitmq.node.overview.rabbitmq_version,#2) and length(last(/RabbitMQ node by Zabbix agent/rabbitmq.node.overview.rabbitmq_version))>0`|Info|**Manual close**: Yes|
|RabbitMQ: Number of network partitions is too high|<p>https://www.rabbitmq.com/partitions.html#detecting</p>|`min(/RabbitMQ node by Zabbix agent/rabbitmq.node.partitions,5m)>0`|Warning||
|RabbitMQ: Node is not running|<p>RabbitMQ node is not running</p>|`max(/RabbitMQ node by Zabbix agent/rabbitmq.node.running,5m)=0`|Average|**Depends on**:<br><ul><li>RabbitMQ: Process is not running</li><li>RabbitMQ: Service is down</li></ul>|
|RabbitMQ: Memory alarm|<p>https://www.rabbitmq.com/memory.html</p>|`last(/RabbitMQ node by Zabbix agent/rabbitmq.node.mem_alarm)=1`|Average||
|RabbitMQ: Free disk space alarm|<p>https://www.rabbitmq.com/disk-alarms.html</p>|`last(/RabbitMQ node by Zabbix agent/rabbitmq.node.disk_free_alarm)=1`|Average||
|RabbitMQ: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/RabbitMQ node by Zabbix agent/rabbitmq.node.uptime)<10m`|Info|**Manual close**: Yes|
|RabbitMQ: Service response time is too high||`min(/RabbitMQ node by Zabbix agent/net.tcp.service.perf["{$RABBITMQ.API.SCHEME}","{$RABBITMQ.API.HOST}","{$RABBITMQ.API.PORT}"],5m)>{$RABBITMQ.RESPONSE_TIME.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>RabbitMQ: Process is not running</li><li>RabbitMQ: Service is down</li></ul>|

### LLD rule Health Check 3.8.10+ discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Health Check 3.8.10+ discovery|<p>Version 3.8.10+ specific metrics</p>|Dependent item|rabbitmq.healthcheck.v3810.discovery<p>**Preprocessing**</p><ul><li>JSON Path: `$.management_version`</li><li>JavaScript: `The text is too long. Please see the template.`</li></ul>|

### Item prototypes for Health Check 3.8.10+ discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|RabbitMQ: Healthcheck: local alarms in effect on this node{#SINGLETON}|<p>Responds a 200 OK if there are no local alarms in effect on the target node, otherwise responds with a 503 Service Unavailable.</p>|Zabbix agent|web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/health/checks/local-alarms{#SINGLETON}"]<p>**Preprocessing**</p><ul><li>Regular expression: `HTTP\/1\.1\b\s(\d+) \1`</li><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `3h`</li></ul>|
|RabbitMQ: Healthcheck: expiration date on the certificates{#SINGLETON}|<p>Checks the expiration date on the certificates for every listener configured to use TLS. Responds a 200 OK if all certificates are valid (have not expired), otherwise responds with a 503 Service Unavailable.</p>|Zabbix agent|web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/health/checks/certificate-expiration/1/months{#SINGLETON}"]<p>**Preprocessing**</p><ul><li>Regular expression: `HTTP\/1\.1\b\s(\d+) \1`</li><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `3h`</li></ul>|
|RabbitMQ: Healthcheck: virtual hosts on this node{#SINGLETON}|<p>Responds a 200 OK if all virtual hosts and running on the target node, otherwise responds with a 503 Service Unavailable.</p>|Zabbix agent|web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/health/checks/virtual-hosts{#SINGLETON}"]<p>**Preprocessing**</p><ul><li>Regular expression: `HTTP\/1\.1\b\s(\d+) \1`</li><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `3h`</li></ul>|
|RabbitMQ: Healthcheck: classic mirrored queues without synchronized mirrors online{#SINGLETON}|<p>Checks if there are classic mirrored queues without synchronized mirrors online (queues that would potentially lose data if the target node is shut down). Responds a 200 OK if there are no such classic mirrored queues, otherwise responds with a 503 Service Unavailable.</p>|Zabbix agent|web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/health/checks/node-is-mirror-sync-critical{#SINGLETON}"]<p>**Preprocessing**</p><ul><li>Regular expression: `HTTP\/1\.1\b\s(\d+) \1`</li><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `3h`</li></ul>|
|RabbitMQ: Healthcheck: queues with minimum online quorum{#SINGLETON}|<p>Checks if there are quorum queues with minimum online quorum (queues that would lose their quorum and availability if the target node is shut down). Responds a 200 OK if there are no such quorum queues, otherwise responds with a 503 Service Unavailable.</p>|Zabbix agent|web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/health/checks/node-is-quorum-critical{#SINGLETON}"]<p>**Preprocessing**</p><ul><li>Regular expression: `HTTP\/1\.1\b\s(\d+) \1`</li><li>JavaScript: `The text is too long. Please see the template.`</li><li>Discard unchanged with heartbeat: `3h`</li></ul>|

### Trigger prototypes for Health Check 3.8.10+ discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RabbitMQ: There are active alarms in the node|<p>http://{HOST.CONN}:{$RABBITMQ.API.PORT}/api/index.html</p>|`last(/RabbitMQ node by Zabbix agent/web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/health/checks/local-alarms{#SINGLETON}"])=0`|Average||
|RabbitMQ: There are valid TLS certificates expiring in the next month|<p>http://{HOST.CONN}:{$RABBITMQ.API.PORT}/api/index.html</p>|`last(/RabbitMQ node by Zabbix agent/web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/health/checks/certificate-expiration/1/months{#SINGLETON}"])=0`|Average||
|RabbitMQ: There are not running virtual hosts|<p>http://{HOST.CONN}:{$RABBITMQ.API.PORT}/api/index.html</p>|`last(/RabbitMQ node by Zabbix agent/web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/health/checks/virtual-hosts{#SINGLETON}"])=0`|Average||
|RabbitMQ: There are queues that could potentially lose data if this node goes offline.|<p>http://{HOST.CONN}:{$RABBITMQ.API.PORT}/api/index.html</p>|`last(/RabbitMQ node by Zabbix agent/web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/health/checks/node-is-mirror-sync-critical{#SINGLETON}"])=0`|Average||
|RabbitMQ: There are queues that would lose their quorum and availability if this node is shut down.|<p>http://{HOST.CONN}:{$RABBITMQ.API.PORT}/api/index.html</p>|`last(/RabbitMQ node by Zabbix agent/web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/health/checks/node-is-quorum-critical{#SINGLETON}"])=0`|Average||

### LLD rule Health Check 3.8.9- discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Health Check 3.8.9- discovery|<p>Specific metrics up to and including version 3.8.4</p>|Dependent item|rabbitmq.healthcheck.v389.discovery<p>**Preprocessing**</p><ul><li>JSON Path: `$.management_version`</li><li>JavaScript: `The text is too long. Please see the template.`</li></ul>|

### Item prototypes for Health Check 3.8.9- discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|RabbitMQ: Healthcheck{#SINGLETON}|<p>Runs basic healthchecks in the current node. Checks that the rabbit application is running, channels and queues can be listed successfully, and that no alarms are in effect.</p>|Zabbix agent|web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/healthchecks/node{#SINGLETON}"]<p>**Preprocessing**</p><ul><li>Regular expression: `\n\s?\n(.*) \1`</li><li>JSON Path: `$.status`</li><li><p>Boolean to decimal</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Trigger prototypes for Health Check 3.8.9- discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RabbitMQ: Node healthcheck failed|<p>https://www.rabbitmq.com/monitoring.html#health-checks</p>|`last(/RabbitMQ node by Zabbix agent/web.page.get["{$RABBITMQ.API.SCHEME}://{$RABBITMQ.API.USER}:{$RABBITMQ.API.PASSWORD}@{$RABBITMQ.API.HOST}:{$RABBITMQ.API.PORT}/api/healthchecks/node{#SINGLETON}"])=0`|Average||

### LLD rule Queues discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Queues discovery|<p>Individual queue metrics</p>|Dependent item|rabbitmq.queues.discovery|

### Item prototypes for Queues discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Get data|<p>The HTTP API endpoint that returns [{#VHOST}][{#QUEUE}] queue metrics</p>|Dependent item|rabbitmq.get_exchanges["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li>JSON Path: `$[?(@.name == "{#QUEUE}" && @.vhost == "{#VHOST}")].first()`</li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages|<p>Count of the total messages in the queue</p>|Dependent item|rabbitmq.queue.messages["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li>JSON Path: `$.messages`</li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages per second|<p>Count per second of the total messages in the queue</p>|Dependent item|rabbitmq.queue.messages.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li>JSON Path: `$.messages_details.rate`</li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Consumers|<p>Number of consumers</p>|Dependent item|rabbitmq.queue.consumers["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li>JSON Path: `$.consumers`</li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Memory|<p>Bytes of memory consumed by the Erlang process associated with the queue, including stack, heap and internal structures</p>|Dependent item|rabbitmq.queue.memory["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li>JSON Path: `$.memory`</li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages ready|<p>Number of messages ready to be delivered to clients</p>|Dependent item|rabbitmq.queue.messages_ready["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li>JSON Path: `$.messages_ready`</li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages ready per second|<p>Number per second of messages ready to be delivered to clients</p>|Dependent item|rabbitmq.queue.messages_ready.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li>JSON Path: `$.messages_ready_details.rate`</li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages unacknowledged|<p>Number of messages delivered to clients but not yet acknowledged</p>|Dependent item|rabbitmq.queue.messages_unacknowledged["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li>JSON Path: `$.messages_unacknowledged`</li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages unacknowledged per second|<p>Number per second of messages delivered to clients but not yet acknowledged</p>|Dependent item|rabbitmq.queue.messages_unacknowledged.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li>JSON Path: `$.messages_unacknowledged_details.rate`</li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages acknowledged|<p>Number of messages delivered to clients and acknowledged</p>|Dependent item|rabbitmq.queue.messages.ack["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.ack`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages acknowledged per second|<p>Number per second of messages delivered to clients and acknowledged</p>|Dependent item|rabbitmq.queue.messages.ack.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.ack_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages delivered|<p>Count of messages delivered in acknowledgement mode to consumers</p>|Dependent item|rabbitmq.queue.messages.deliver["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages delivered per second|<p>Count of messages delivered in acknowledgement mode to consumers</p>|Dependent item|rabbitmq.queue.messages.deliver.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Sum of messages delivered|<p>Sum of messages delivered in acknowledgement mode to consumers, in no-acknowledgement mode to consumers, in acknowledgement mode in response to basic.get, and in no-acknowledgement mode in response to basic.get</p>|Dependent item|rabbitmq.queue.messages.deliver_get["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_get`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Sum of messages delivered per second|<p>Rate per second of the sum of messages delivered in acknowledgement mode to consumers, in no-acknowledgement mode to consumers, in acknowledgement mode in response to basic.get, and in no-acknowledgement mode in response to basic.get</p>|Dependent item|rabbitmq.queue.messages.deliver_get.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.deliver_get_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages published|<p>Count of messages published</p>|Dependent item|rabbitmq.queue.messages.publish["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages published per second|<p>Rate per second of messages published</p>|Dependent item|rabbitmq.queue.messages.publish.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.publish_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages redelivered|<p>Count of subset of messages in deliver_get which had the redelivered flag set</p>|Dependent item|rabbitmq.queue.messages.redeliver["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.redeliver`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|RabbitMQ: Queue [{#VHOST}][{#QUEUE}]: Messages redelivered per second|<p>Rate per second of subset of messages in deliver_get which had the redelivered flag set</p>|Dependent item|rabbitmq.queue.messages.redeliver.rate["{#VHOST}/{#QUEUE}"]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.message_stats.redeliver_details.rate`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|

### Trigger prototypes for Queues discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RabbitMQ: Too many messages in queue [{#VHOST}][{#QUEUE}]||`min(/RabbitMQ node by Zabbix agent/rabbitmq.queue.messages["{#VHOST}/{#QUEUE}"],5m)>{$RABBITMQ.MESSAGES.MAX.WARN:"{#QUEUE}"}`|Warning||

## Feedback

Please report any issues with the template at `https://support.zabbix.com`.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
