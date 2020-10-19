
# Apache Activemq by JMX

## Overview

For Zabbix version: 5.0 and higher  
Official JMX Template for Apache ActiveMQ.


This template was tested on:

- Apache ActiveMQ, version 3.15.5
- Zabbix, version 5.0, 5.2

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/5.0/manual/config/templates_out_of_the_box/jmx) for basic instructions.

Metrics are collected by JMX.

1. Enable and configure JMX access to Apache ActiveMQ.
 See documentation for [instructions](https://activemq.apache.org/jmx.html).
2. Set values in host macros {$ACTIVEMQ.USERNAME}, {$ACTIVEMQ.PASSWORD}, {$ACTIVEMQ.PORT} and {$ACTIVEMQ.HTTP.PORT}.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ACTIVEMQ.CONSUMERS.MIN.HIGH} |<p>Minimum amaunt of consumers for destination. Can be used with destination name as context.</p> |`0` |
|{$ACTIVEMQ.CONSUMERS.MIN.TIME} |<p>Time during which there may be no consumers in destination. Can be used with destination name as context.</p> |`10m` |
|{$ACTIVEMQ.EXPIRIED.WARN} |<p>Threshold for expiried messages count. Can be used with destination name as context.</p> |`0` |
|{$ACTIVEMQ.LLD.FILTER.BROKER.MATCHES} |<p>Filter of discoverable discovered brokers</p> |`.*` |
|{$ACTIVEMQ.LLD.FILTER.BROKER.NOT_MATCHES} |<p>Filter to exclude discovered brokers</p> |`CHANGE IF NEEDED` |
|{$ACTIVEMQ.LLD.FILTER.DESTINATION.MATCHES} |<p>Filter of discoverable discovered destinations</p> |`.*` |
|{$ACTIVEMQ.LLD.FILTER.DESTINATION.NOT_MATCHES} |<p>Filter to exclude discovered destinations</p> |`CHANGE IF NEEDED` |
|{$ACTIVEMQ.MEM.MAX.HIGH} |<p>Memory threshold for HIGH trigger. Can be used with destination or broker name as context.</p> |`90` |
|{$ACTIVEMQ.MEM.MAX.WARN} |<p>Memory threshold for AVERAGE trigger. Can be used with destination or broker name as context.</p> |`75` |
|{$ACTIVEMQ.MSG.RATE.WARN.TIME} |<p>The time for message enqueue/dequeue rate. Can be used with destination or broker name as context.</p> |`15m` |
|{$ACTIVEMQ.PASSWORD} |<p>Password for JMX</p> |`activemq` |
|{$ACTIVEMQ.PORT} |<p>Port for JMX</p> |`1099` |
|{$ACTIVEMQ.PRODUCERS.MIN.HIGH} |<p>Minimum amaunt of producers for destination. Can be used with destination name as context.</p> |`0` |
|{$ACTIVEMQ.PRODUCERS.MIN.TIME} |<p>Time during which there may be no producers in destination.</p> |`10m` |
|{$ACTIVEMQ.QUEUE.ENABLED} |<p>Use this to disable alerting for specific destination. 1 = enabled, 0 = disabled. Can be used with destination name as context.</p> |`1` |
|{$ACTIVEMQ.QUEUE.TIME} |<p>Time during which the QueueSize can be higher than threshold. Can be used with destination name as context.</p> |`10m` |
|{$ACTIVEMQ.QUEUE.WARN} |<p>Threshold for QueueSize. Can be used with destination name as context.</p> |`100` |
|{$ACTIVEMQ.STORE.MAX.HIGH} |<p>Storage threshold for HIGH trigger. Can be used with broker name as context.</p> |`90` |
|{$ACTIVEMQ.STORE.MAX.WARN} |<p>Storage threshold for AVERAGE trigger. Can be used with broker name as context.</p> |`75` |
|{$ACTIVEMQ.TEMP.MAX.HIGH} |<p>Temp threshold for HIGH trigger. Can be used with broker name as context.</p> |`90` |
|{$ACTIVEMQ.TEMP.MAX.WARN} |<p>Temp threshold for AVERAGE trigger. Can be used with broker name as context.</p> |`75` |
|{$ACTIVEMQ.USER} |<p>User for JMX</p> |`admin` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Brokers discovery |<p>Discovery of brokers</p> |JMX |jmx.discovery[beans,"org.apache.activemq:type=Broker,brokerName=*"]<p>**Filter**:</p>AND <p>- A: {#JMXBROKERNAME} MATCHES_REGEX `{$ACTIVEMQ.LLD.FILTER.BROKER.MATCHES}`</p><p>- B: {#JMXBROKERNAME} NOT_MATCHES_REGEX `{$ACTIVEMQ.LLD.FILTER.BROKER.NOT_MATCHES}`</p> |
|Destinations discovery |<p>Discovery of destinations</p> |JMX |jmx.discovery[beans,"org.apache.activemq:type=Broker,brokerName=*,destinationType=*,destinationName=*"]<p>**Filter**:</p>AND <p>- A: {#JMXBROKERNAME} MATCHES_REGEX `{$ACTIVEMQ.LLD.FILTER.DESTINATION.MATCHES:"{#JMXBROKERNAME}"}`</p><p>- B: {#JMXBROKERNAME} NOT_MATCHES_REGEX `{$ACTIVEMQ.LLD.FILTER.DESTINATION.NOT_MATCHES:"{#JMXBROKERNAME}"}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|ActiveMQ |Broker {#JMXBROKERNAME}: Brocker version |<p>The version of the broker.</p> |JMX |jmx[{#JMXOBJ},BrokerVersion] |
|ActiveMQ |Broker {#JMXBROKERNAME}: Memory limit |<p>Memory limit, in bytes, used for holding undelivered messages before paging to temporary storage.</p> |JMX |jmx[{#JMXOBJ},MemoryLimit] |
|ActiveMQ |Broker {#JMXBROKERNAME}: Memory usage in percents |<p>Percent of memory limit used.</p> |JMX |jmx[{#JMXOBJ},MemoryPercentUsage] |
|ActiveMQ |Broker {#JMXBROKERNAME}: Storage limit |<p>Disk limit, in bytes, used for persistent messages before producers are blocked.</p> |JMX |jmx[{#JMXOBJ},StoreLimit] |
|ActiveMQ |Broker {#JMXBROKERNAME}: Storage usage in percents |<p>Percent of store limit used.</p> |JMX |jmx[{#JMXOBJ},StorePercentUsage] |
|ActiveMQ |Broker {#JMXBROKERNAME}: Temp limit |<p>Disk limit, in bytes, used for non-persistent messages and temporary data before producers are blocked.</p> |JMX |jmx[{#JMXOBJ},TempLimit] |
|ActiveMQ |Broker {#JMXBROKERNAME}: Temp usage in percents |<p>Percent of temp limit used.</p> |JMX |jmx[{#JMXOBJ},TempPercentUsage] |
|ActiveMQ |Broker {#JMXBROKERNAME}: Messages enqueue rate |<p>Rate of messages that have been sent to the broker.</p> |JMX |jmx[{#JMXOBJ},TotalEnqueueCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|ActiveMQ |Broker {#JMXBROKERNAME}: Messages dequeue rate |<p>Rate of messages that have been delivered by the broker and acknowledged by consumers.</p> |JMX |jmx[{#JMXOBJ},TotalDequeueCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|ActiveMQ |{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Memory usage in percents |<p>The percentage of the memory limit used.</p> |JMX |jmx[{#JMXOBJ},MemoryPercentUsage] |
|ActiveMQ |{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Consumers count |<p>Number of consumers attached to this destination..</p> |JMX |jmx[{#JMXOBJ},ConsumerCount] |
|ActiveMQ |{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Producers count |<p>Number of producers attached to this destination.</p> |JMX |jmx[{#JMXOBJ},ProducerCount] |
|ActiveMQ |{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Messages enqueue rate |<p>Rate of messages that have been sent to the destination.</p> |JMX |jmx[{#JMXOBJ},EnqueueCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|ActiveMQ |{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Messages dequeue rate |<p>Rate of messages that has been acknowledged (and removed) from the destination.</p> |JMX |jmx[{#JMXOBJ},DequeueCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND |
|ActiveMQ |{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Queue size |<p>Number of messages on this destination, including any that have been dispatched but not acknowledged.</p> |JMX |jmx[{#JMXOBJ},QueueSize] |
|ActiveMQ |{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Expired messages count |<p>Number of messages that have been expired.</p> |JMX |jmx[{#JMXOBJ},ExpiredCount] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Broker {#JMXBROKERNAME}: Version has been changed |<p>Broker {#JMXBROKERNAME} version has changed. Ack to close.</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},BrokerVersion].diff()}=1 and {TEMPLATE_NAME:jmx[{#JMXOBJ},BrokerVersion].strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Broker {#JMXBROKERNAME}: Memory usage is too high (>$ACTIVEMQ.MEM.MAX.WARN}%) |<p>-</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},MemoryPercentUsage].last()}>{$ACTIVEMQ.MEM.MAX.WARN:"{#JMXBROKERNAME}"}` |AVERAGE | |
|Broker {#JMXBROKERNAME}: Memory usage is too high (>$ACTIVEMQ.MEM.MAX.WARN}%) |<p>-</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},MemoryPercentUsage].last()}>{$ACTIVEMQ.MEM.MAX.HIGH:"{#JMXBROKERNAME}"}` |HIGH | |
|Broker {#JMXBROKERNAME}: Storage usage is too high (>$ACTIVEMQ.STORE.MAX.WARN}%) |<p>-</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},StorePercentUsage].last()}>{$ACTIVEMQ.STORE.MAX.WARN}` |AVERAGE | |
|Broker {#JMXBROKERNAME}: Storage usage is too high (>$ACTIVEMQ.STORE.MAX.WARN}%) |<p>-</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},StorePercentUsage].last()}>{$ACTIVEMQ.STORE.MAX.HIGH}` |HIGH | |
|Broker {#JMXBROKERNAME}: Temp usage is too high (>$ACTIVEMQ.TEMP.MAX.WARN}%) |<p>-</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},TempLimit].last()}>{$ACTIVEMQ.TEMP.MAX.WARN}` |AVERAGE | |
|Broker {#JMXBROKERNAME}: Temp usage is too high (>$ACTIVEMQ.TEMP.MAX.WARN}%) |<p>-</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},TempLimit].last()}>{$ACTIVEMQ.TEMP.MAX.HIGH}` |HIGH | |
|Broker {#JMXBROKERNAME}: Message enqueue rate is higer than dequeue rate for {$ACTIVEMQ.MSG.RATE.WARN.TIME:"{#JMXBROKERNAME}"} |<p>Enqueue rate is higer than dequeue rate. It may indicate performance problems.</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},TotalEnqueueCount].avg({$ACTIVEMQ.MSG.RATE.WARN.TIME:"{#JMXBROKERNAME}"})}>{Apache Activemq by JMX:jmx[{#JMXOBJ},TotalEnqueueCount].avg({$ACTIVEMQ.MSG.RATE.WARN.TIME:"{#JMXBROKERNAME}"})}` |AVERAGE | |
|{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Memory usage is too high (>$ACTIVEMQ.MEM.MAX.WARN:"{#JMXDESTINATIONNAME}"}%) |<p>-</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},MemoryPercentUsage].last()}>{$ACTIVEMQ.MEM.MAX.WARN:"{#JMXDESTINATIONNAME}"}` |AVERAGE | |
|{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Memory usage is too high (>$ACTIVEMQ.MEM.MAX.WARN:"{#JMXDESTINATIONNAME}"}%) |<p>-</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},MemoryPercentUsage].last()}>{$ACTIVEMQ.MEM.MAX.HIGH:"{#JMXDESTINATIONNAME}"}` |HIGH | |
|{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Consumers count less or equal {$ACTIVEMQ.CONSUMERS.MIN.HIGH:"{#JMXDESTINATIONNAME}"} for {$ACTIVEMQ.CONSUMERS.MIN.TIME:"{#JMXDESTINATIONNAME}"} |<p>-</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},ConsumerCount].min({$ACTIVEMQ.CONSUMERS.MIN.TIME:"{#JMXDESTINATIONNAME}"})}<={$ACTIVEMQ.CONSUMERS.MIN.HIGH:"{#JMXDESTINATIONNAME}"}` |HIGH | |
|{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Producers count less or equal {$ACTIVEMQ.PRODUCERS.MIN.HIGH:"{#JMXDESTINATIONNAME}"} for {$ACTIVEMQ.PRODUCERS.MIN.TIME:"{#JMXDESTINATIONNAME}"} |<p>-</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},ProducerCount].min({$ACTIVEMQ.PRODUCERS.MIN.TIME:"{#JMXDESTINATIONNAME}"})}<={$ACTIVEMQ.PRODUCERS.MIN.HIGH:"{#JMXDESTINATIONNAME}"}` |HIGH | |
|{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Message enqueue rate is higer than dequeue rate for {$ACTIVEMQ.MSG.RATE.WARN.TIME:"{#JMXDESTINATIONNAME}"} |<p>Enqueue rate is higer than dequeue rate. It may indicate performance problems.</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},EnqueueCount].avg({$ACTIVEMQ.MSG.RATE.WARN.TIME:"{#JMXDESTINATIONNAME}"})}>{Apache Activemq by JMX:jmx[{#JMXOBJ},DequeueCount].avg({$ACTIVEMQ.MSG.RATE.WARN.TIME:"{#JMXDESTINATIONNAME}"})}` |AVERAGE | |
|{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Queue size higer than {$ACTIVEMQ.QUEUE.WARN:"{#JMXDESTINATIONNAME}"} for {$ACTIVEMQ.QUEUE.TIME:"{#JMXDESTINATIONNAME}"} |<p>Queue size is higer than treshold. It may indicate performance problems.</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},QueueSize].min({$ACTIVEMQ.QUEUE.TIME:"{#JMXDESTINATIONNAME}"})}>{$ACTIVEMQ.QUEUE.WARN:"{#JMXDESTINATIONNAME}"} and {$ACTIVEMQ.QUEUE.ENABLED:"{#JMXDESTINATIONNAME}"}=1` |AVERAGE | |
|{#JMXDESTINATIONTYPE} "{#JMXDESTINATIONNAME}" on {#JMXBROKERNAME}: Expired messages count higer than {$ACTIVEMQ.EXPIRIED.WARN:"{#JMXDESTINATIONNAME}"} |<p>This metric represents the number of messages that expired before they could be delivered. If you expect all messages to be delivered and acknowledged within a certain amount of time, you can set an expiration for each message, and investigate if your ExpiredCount metric rises above zero.</p> |`{TEMPLATE_NAME:jmx[{#JMXOBJ},ExpiredCount].last()}>{$ACTIVEMQ.EXPIRIED.WARN:"{#JMXDESTINATIONNAME}"}` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide a feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/411049-discussion-thread-for-official-zabbix-template-amq).

