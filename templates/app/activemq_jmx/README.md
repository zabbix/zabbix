
# Apache ActiveMQ by JMX

## Overview

This template is designed for the effortless deployment of Apache ActiveMQ monitoring by Zabbix via JMX and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Apache ActiveMQ 5.15.5

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Metrics are collected by JMX.

1. Enable and configure JMX access to Apache ActiveMQ.
 See documentation for [instructions](https://activemq.apache.org/jmx.html).
2. Set values in host macros {$ACTIVEMQ.USERNAME}, {$ACTIVEMQ.PASSWORD} and {$ACTIVEMQ.PORT}.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ACTIVEMQ.USER}|<p>User for JMX</p>|`admin`|
|{$ACTIVEMQ.PASSWORD}|<p>Password for JMX</p>|`activemq`|
|{$ACTIVEMQ.PORT}|<p>Port for JMX</p>|`1099`|
|{$ACTIVEMQ.LLD.FILTER.BROKER.MATCHES}|<p>Filter of discoverable discovered brokers</p>|`.*`|
|{$ACTIVEMQ.LLD.FILTER.BROKER.NOT_MATCHES}|<p>Filter to exclude discovered brokers</p>|`CHANGE IF NEEDED`|
|{$ACTIVEMQ.LLD.FILTER.DESTINATION.MATCHES}|<p>Filter of discoverable discovered destinations</p>|`.*`|
|{$ACTIVEMQ.LLD.FILTER.DESTINATION.NOT_MATCHES}|<p>Filter to exclude discovered destinations</p>|`CHANGE IF NEEDED`|
|{$ACTIVEMQ.MSG.RATE.WARN.TIME}|<p>The time for message enqueue/dequeue rate. Can be used with destination or broker name as context.</p>|`15m`|
|{$ACTIVEMQ.MEM.MAX.WARN}|<p>Memory threshold for AVERAGE trigger. Can be used with destination or broker name as context.</p>|`75`|
|{$ACTIVEMQ.MEM.MAX.HIGH}|<p>Memory threshold for HIGH trigger. Can be used with destination or broker name as context.</p>|`90`|
|{$ACTIVEMQ.MEM.TIME}|<p>Time during which the metric can be above the threshold. Can be used with destination or broker name as context.</p>|`5m`|
|{$ACTIVEMQ.STORE.MAX.WARN}|<p>Storage threshold for AVERAGE trigger. Can be used with broker name as context.</p>|`75`|
|{$ACTIVEMQ.STORE.TIME}|<p>Time during which the metric can be above the threshold. Can be used with destination or broker name as context.</p>|`5m`|
|{$ACTIVEMQ.STORE.MAX.HIGH}|<p>Storage threshold for HIGH trigger. Can be used with broker name as context.</p>|`90`|
|{$ACTIVEMQ.TEMP.MAX.WARN}|<p>Temp threshold for AVERAGE trigger. Can be used with broker name as context.</p>|`75`|
|{$ACTIVEMQ.TEMP.MAX.HIGH}|<p>Temp threshold for HIGH trigger. Can be used with broker name as context.</p>|`90`|
|{$ACTIVEMQ.TEMP.TIME}|<p>Time during which the metric can be above the threshold. Can be used with destination or broker name as context.</p>|`5m`|
|{$ACTIVEMQ.DESTINATION.CONSUMERS.MIN.TIME}|<p>Time during which there may be no consumers in destination. Can be used with destination name as context.</p>|`10m`|
|{$ACTIVEMQ.DESTINATION.CONSUMERS.MIN.HIGH}|<p>Minimum amount of consumers for destination. Can be used with destination name as context.</p>|`1`|
|{$ACTIVEMQ.DESTINATION.PRODUCERS.MIN.TIME}|<p>Time during which there may be no producers on destination. Can be used with destination name as context.</p>|`10m`|
|{$ACTIVEMQ.DESTINATION.PRODUCERS.MIN.HIGH}|<p>Minimum amount of producers for destination. Can be used with destination name as context.</p>|`1`|
|{$ACTIVEMQ.BROKER.CONSUMERS.MIN.TIME}|<p>Time during which there may be no consumers on destination. Can be used with broker name as context.</p>|`5m`|
|{$ACTIVEMQ.BROKER.CONSUMERS.MIN.HIGH}|<p>Minimum amount of consumers for broker. Can be used with broker name as context.</p>|`1`|
|{$ACTIVEMQ.BROKER.PRODUCERS.MIN.TIME}|<p>Time during which there may be no producers on broker. Can be used with broker name as context.</p>|`5m`|
|{$ACTIVEMQ.BROKER.PRODUCERS.MIN.HIGH}|<p>Minimum amount of producers for broker. Can be used with broker name as context.</p>|`1`|
|{$ACTIVEMQ.TOTAL.CONSUMERS.COUNT}|<p>Attribute for TotalConsumerCount per destination. Used to suppress destination's triggers when the count of consumers on the broker is lower than threshold.</p>|`TotalConsumerCount`|
|{$ACTIVEMQ.TOTAL.PRODUCERS.COUNT}|<p>Attribute for TotalProducerCount per destination. Used to suppress destination's triggers when the count of consumers on the broker is lower than threshold.</p>|`TotalProducerCount`|
|{$ACTIVEMQ.QUEUE.TIME}|<p>Time during which the QueueSize can be higher than threshold. Can be used with destination name as context.</p>|`10m`|
|{$ACTIVEMQ.QUEUE.WARN}|<p>Threshold for QueueSize. Can be used with destination name as context.</p>|`100`|
|{$ACTIVEMQ.QUEUE.ENABLED}|<p>Use this to disable alerting for specific destination. 1 = enabled, 0 = disabled. Can be used with destination name as context.</p>|`1`|
|{$ACTIVEMQ.EXPIRED.WARN}|<p>Threshold for expired messages count. Can be used with destination name as context.</p>|`0`|

### LLD rule Brokers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Brokers discovery|<p>Discovery of brokers</p>|JMX agent|jmx.discovery[beans,"org.apache.activemq:type=Broker,brokerName=*"]|

### Item prototypes for Brokers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Broker {#JMXBROKERNAME}: Version|<p>The version of the broker.</p>|JMX agent|jmx[{#JMXOBJ},BrokerVersion]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Broker {#JMXBROKERNAME}: Uptime|<p>The uptime of the broker.</p>|JMX agent|jmx[{#JMXOBJ},UptimeMillis]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Broker {#JMXBROKERNAME}: Memory limit|<p>Memory limit, in bytes, used for holding undelivered messages before paging to temporary storage.</p>|JMX agent|jmx[{#JMXOBJ},MemoryLimit]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Broker {#JMXBROKERNAME}: Memory usage in percents|<p>Percent of memory limit used.</p>|JMX agent|jmx[{#JMXOBJ}, MemoryPercentUsage]|
|Broker {#JMXBROKERNAME}: Storage limit|<p>Disk limit, in bytes, used for persistent messages before producers are blocked.</p>|JMX agent|jmx[{#JMXOBJ},StoreLimit]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Broker {#JMXBROKERNAME}: Storage usage in percents|<p>Percent of store limit used.</p>|JMX agent|jmx[{#JMXOBJ},StorePercentUsage]|
|Broker {#JMXBROKERNAME}: Temp limit|<p>Disk limit, in bytes, used for non-persistent messages and temporary data before producers are blocked.</p>|JMX agent|jmx[{#JMXOBJ},TempLimit]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Broker {#JMXBROKERNAME}: Temp usage in percents|<p>Percent of temp limit used.</p>|JMX agent|jmx[{#JMXOBJ},TempPercentUsage]|
|Broker {#JMXBROKERNAME}: Messages enqueue rate|<p>Rate of messages that have been sent to the broker.</p>|JMX agent|jmx[{#JMXOBJ},TotalEnqueueCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Broker {#JMXBROKERNAME}: Messages dequeue rate|<p>Rate of messages that have been delivered by the broker and acknowledged by consumers.</p>|JMX agent|jmx[{#JMXOBJ},TotalDequeueCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|Broker {#JMXBROKERNAME}: Consumers count total|<p>Number of consumers attached to this broker.</p>|JMX agent|jmx[{#JMXOBJ},TotalConsumerCount]|
|Broker {#JMXBROKERNAME}: Producers count total|<p>Number of producers attached to this broker.</p>|JMX agent|jmx[{#JMXOBJ},TotalProducerCount]|

### Trigger prototypes for Brokers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Broker {#JMXBROKERNAME}: Version has been changed|<p>The Broker {#JMXBROKERNAME} version has changed. Acknowledge to close the problem manually.</p>|`last(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},BrokerVersion],#1)<>last(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},BrokerVersion],#2) and length(last(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},BrokerVersion]))>0`|Info|**Manual close**: Yes|
|Broker {#JMXBROKERNAME}: Broker has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},UptimeMillis])<10m`|Info|**Manual close**: Yes|
|Broker {#JMXBROKERNAME}: Memory usage is too high||`min(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ}, MemoryPercentUsage],{$ACTIVEMQ.MEM.TIME:"{#JMXBROKERNAME}"})>{$ACTIVEMQ.MEM.MAX.WARN:"{#JMXBROKERNAME}"}`|Average|**Depends on**:<br><ul><li>Broker {#JMXBROKERNAME}: Memory usage is too high</li></ul>|
|Broker {#JMXBROKERNAME}: Memory usage is too high||`min(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ}, MemoryPercentUsage],{$ACTIVEMQ.MEM.TIME:"{#JMXBROKERNAME}"})>{$ACTIVEMQ.MEM.MAX.HIGH:"{#JMXBROKERNAME}"}`|High||
|Broker {#JMXBROKERNAME}: Storage usage is too high||`min(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},StorePercentUsage],{$ACTIVEMQ.STORE.TIME:"{#JMXBROKERNAME}"})>{$ACTIVEMQ.STORE.MAX.WARN:"{#JMXBROKERNAME}"}`|Average|**Depends on**:<br><ul><li>Broker {#JMXBROKERNAME}: Storage usage is too high</li></ul>|
|Broker {#JMXBROKERNAME}: Storage usage is too high||`min(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},StorePercentUsage],{$ACTIVEMQ.STORE.TIME:"{#JMXBROKERNAME}"})>{$ACTIVEMQ.STORE.MAX.HIGH:"{#JMXBROKERNAME}"}`|High||
|Broker {#JMXBROKERNAME}: Temp usage is too high||`min(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},TempPercentUsage],{$ACTIVEMQ.TEMP.TIME:"{#JMXBROKERNAME}"})>{$ACTIVEMQ.TEMP.MAX.WARN}`|Average|**Depends on**:<br><ul><li>Broker {#JMXBROKERNAME}: Temp usage is too high</li></ul>|
|Broker {#JMXBROKERNAME}: Temp usage is too high||`min(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},TempPercentUsage],{$ACTIVEMQ.TEMP.TIME:"{#JMXBROKERNAME}"})>{$ACTIVEMQ.TEMP.MAX.HIGH}`|High||
|Broker {#JMXBROKERNAME}: Message enqueue rate is higher than dequeue rate|<p>Enqueue rate is higher than dequeue rate. It may indicate performance problems.</p>|`avg(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},TotalEnqueueCount],{$ACTIVEMQ.MSG.RATE.WARN.TIME:"{#JMXBROKERNAME}"})>avg(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},TotalEnqueueCount],{$ACTIVEMQ.MSG.RATE.WARN.TIME:"{#JMXBROKERNAME}"})`|Average||
|Broker {#JMXBROKERNAME}: Consumers count is too low||`max(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},TotalConsumerCount],{$ACTIVEMQ.BROKER.CONSUMERS.MIN.TIME:"{#JMXBROKERNAME}"})<{$ACTIVEMQ.BROKER.CONSUMERS.MIN.HIGH:"{#JMXBROKERNAME}"}`|High||
|Broker {#JMXBROKERNAME}: Producers count is too low||`max(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},TotalProducerCount],{$ACTIVEMQ.BROKER.PRODUCERS.MIN.TIME:"{#JMXBROKERNAME}"})<{$ACTIVEMQ.BROKER.PRODUCERS.MIN.HIGH:"{#JMXBROKERNAME}"}`|High||

### LLD rule Destinations discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Destinations discovery|<p>Discovery of destinations</p>|JMX agent|jmx.discovery[beans,"org.apache.activemq:type=Broker,brokerName=*,destinationType=*,destinationName=*"]|

### Item prototypes for Destinations discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Consumers count|<p>Number of consumers attached to this destination.</p>|JMX agent|jmx[{#JMXOBJ},ConsumerCount]|
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Consumers count total on {#JMXBROKERNAME}|<p>Number of consumers attached to the broker of this destination. Used to suppress destination's triggers when the count of consumers on the broker is lower than threshold.</p>|JMX agent|jmx["org.apache.activemq:type=Broker,brokerName={#JMXBROKERNAME}",{$ACTIVEMQ.TOTAL.CONSUMERS.COUNT: "{#JMXDESTINATIONNAME}"}]<p>**Preprocessing**</p><ul><li><p>In range: `0 -> {$ACTIVEMQ.BROKER.CONSUMERS.MIN.HIGH}`</p><p>⛔️Custom on fail: Set value to: `{$ACTIVEMQ.BROKER.CONSUMERS.MIN.HIGH}`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Producers count|<p>Number of producers attached to this destination.</p>|JMX agent|jmx[{#JMXOBJ},ProducerCount]|
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Producers count total on {#JMXBROKERNAME}|<p>Number of producers attached to the broker of this destination. Used to suppress destination's triggers when the count of producers on the broker is lower than threshold.</p>|JMX agent|jmx["org.apache.activemq:type=Broker,brokerName={#JMXBROKERNAME}",{$ACTIVEMQ.TOTAL.PRODUCERS.COUNT: "{#JMXDESTINATIONNAME}"}]<p>**Preprocessing**</p><ul><li><p>In range: `0 -> {$ACTIVEMQ.BROKER.PRODUCERS.MIN.HIGH}`</p><p>⛔️Custom on fail: Set value to: `{$ACTIVEMQ.BROKER.PRODUCERS.MIN.HIGH}`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Memory usage in percents|<p>The percentage of the memory limit used.</p>|JMX agent|jmx[{#JMXOBJ},MemoryPercentUsage]|
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Messages enqueue rate|<p>Rate of messages that have been sent to the destination.</p>|JMX agent|jmx[{#JMXOBJ},EnqueueCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Messages dequeue rate|<p>Rate of messages that has been acknowledged (and removed) from the destination.</p>|JMX agent|jmx[{#JMXOBJ},DequeueCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Queue size|<p>Number of messages on this destination, including any that have been dispatched but not acknowledged.</p>|JMX agent|jmx[{#JMXOBJ},QueueSize]|
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Expired messages count|<p>Number of messages that have been expired.</p>|JMX agent|jmx[{#JMXOBJ},ExpiredCount]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|

### Trigger prototypes for Destinations discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Consumers count is too low||`max(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},ConsumerCount],{$ACTIVEMQ.DESTINATION.CONSUMERS.MIN.TIME:"{#JMXDESTINATIONNAME}"})<{$ACTIVEMQ.DESTINATION.CONSUMERS.MIN.HIGH:"{#JMXDESTINATIONNAME}"} and last(/Apache ActiveMQ by JMX/jmx["org.apache.activemq:type=Broker,brokerName={#JMXBROKERNAME}",{$ACTIVEMQ.TOTAL.CONSUMERS.COUNT: "{#JMXDESTINATIONNAME}"}])>{$ACTIVEMQ.BROKER.CONSUMERS.MIN.HIGH:"{#JMXBROKERNAME}"}`|Average|**Manual close**: Yes|
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Producers count is too low||`max(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},ProducerCount],{$ACTIVEMQ.DESTINATION.PRODUCERS.MIN.TIME:"{#JMXDESTINATIONNAME}"})<{$ACTIVEMQ.DESTINATION.PRODUCERS.MIN.HIGH:"{#JMXDESTINATIONNAME}"} and last(/Apache ActiveMQ by JMX/jmx["org.apache.activemq:type=Broker,brokerName={#JMXBROKERNAME}",{$ACTIVEMQ.TOTAL.PRODUCERS.COUNT: "{#JMXDESTINATIONNAME}"}])>{$ACTIVEMQ.BROKER.PRODUCERS.MIN.HIGH:"{#JMXBROKERNAME}"}`|Average|**Manual close**: Yes|
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Memory usage is too high||`last(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},MemoryPercentUsage])>{$ACTIVEMQ.MEM.MAX.WARN:"{#JMXDESTINATIONNAME}"}`|Average||
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Memory usage is too high||`last(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},MemoryPercentUsage])>{$ACTIVEMQ.MEM.MAX.HIGH:"{#JMXDESTINATIONNAME}"}`|High||
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Message enqueue rate is higher than dequeue rate|<p>Enqueue rate is higher than dequeue rate. It may indicate performance problems.</p>|`avg(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},EnqueueCount],{$ACTIVEMQ.MSG.RATE.WARN.TIME:"{#JMXDESTINATIONNAME}"})>avg(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},DequeueCount],{$ACTIVEMQ.MSG.RATE.WARN.TIME:"{#JMXDESTINATIONNAME}"})`|Average||
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Queue size is high|<p>Queue size is higher than threshold. It may indicate performance problems.</p>|`min(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},QueueSize],{$ACTIVEMQ.QUEUE.TIME:"{#JMXDESTINATIONNAME}"})>{$ACTIVEMQ.QUEUE.WARN:"{#JMXDESTINATIONNAME}"} and {$ACTIVEMQ.QUEUE.ENABLED:"{#JMXDESTINATIONNAME}"}=1`|Average||
|{#JMXBROKERNAME}: {#JMXDESTINATIONTYPE} {#JMXDESTINATIONNAME}: Expired messages count is high|<p>This metric represents the number of messages that expired before they could be delivered. If you expect all messages to be delivered and acknowledged within a certain amount of time, you can set an expiration for each message, and investigate if your ExpiredCount metric rises above zero.</p>|`last(/Apache ActiveMQ by JMX/jmx[{#JMXOBJ},ExpiredCount])>{$ACTIVEMQ.EXPIRED.WARN:"{#JMXDESTINATIONNAME}"}`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

