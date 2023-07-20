
# Apache Tomcat by JMX

## Overview

This template is designed for the effortless deployment of Apache Tomcat monitoring by Zabbix via JMX and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Apache Tomcat 8.5.59

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Metrics are collected by JMX.

1. Enable and configure JMX access to Apache Tomcat.
 See documentation for [instructions](https://tomcat.apache.org/tomcat-10.0-doc/monitoring.html#Enabling_JMX_Remote) (chose your version).
2. If your Tomcat installation require authentication for JMX, set values in host macros {$TOMCAT.USERNAME} and {$TOMCAT.PASSWORD}.
3. You can set custom macro values and add macros with context for specific metrics following macro description.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TOMCAT.USER}|<p>User for JMX</p>||
|{$TOMCAT.PASSWORD}|<p>Password for JMX</p>||
|{$TOMCAT.LLD.FILTER.MATCHES}|<p>Filter for discoverable objects. Can be used with following contexts: "GlobalRequestProcessor", "ThreadPool", "Manager"</p>|`.*`|
|{$TOMCAT.LLD.FILTER.NOT_MATCHES}|<p>Filter to exclude discovered objects. Can be used with following contexts: "GlobalRequestProcessor", "ThreadPool", "Manager"</p>|`CHANGE IF NEEDED`|
|{$TOMCAT.THREADS.MAX.PCT}|<p>Threshold for busy worker threads trigger. Can be used with {#JMXNAME} as context.</p>|`75`|
|{$TOMCAT.THREADS.MAX.TIME}|<p>The time during which the number of busy threads can exceed the threshold. Can be used with {#JMXNAME} as context.</p>|`5m`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Tomcat: Version|<p>The version of the Tomcat.</p>|JMX agent|jmx["Catalina:type=Server",serverInfo]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Tomcat: Version has been changed|<p>The Tomcat version has changed. Acknowledge to close the problem manually.</p>|`last(/Apache Tomcat by JMX/jmx["Catalina:type=Server",serverInfo],#1)<>last(/Apache Tomcat by JMX/jmx["Catalina:type=Server",serverInfo],#2) and length(last(/Apache Tomcat by JMX/jmx["Catalina:type=Server",serverInfo]))>0`|Info|**Manual close**: Yes|

### LLD rule Global request processors discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Global request processors discovery|<p>Discovery for GlobalRequestProcessor</p>|JMX agent|jmx.discovery[beans,"Catalina:type=GlobalRequestProcessor,name=*"]|

### Item prototypes for Global request processors discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#JMXNAME}: Bytes received per second|<p>Bytes received rate by processor {#JMXNAME}</p>|JMX agent|jmx[{#JMXOBJ},bytesReceived]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|{#JMXNAME}: Bytes sent per second|<p>Bytes sent rate by processor {#JMXNAME}</p>|JMX agent|jmx[{#JMXOBJ},bytesSent]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|{#JMXNAME}: Errors per second|<p>Error rate of request processor {#JMXNAME}</p>|JMX agent|jmx[{#JMXOBJ},errorCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|{#JMXNAME}: Requests per second|<p>Rate of requests served by request processor {#JMXNAME}</p>|JMX agent|jmx[{#JMXOBJ},requestCount]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|{#JMXNAME}: Requests processing time|<p>The total time to process all incoming requests of request processor</p><p>{#JMXNAME}</p>|JMX agent|jmx[{#JMXOBJ},processingTime]<p>**Preprocessing**</p><ul><li><p>Custom multiplier: `0.001`</p></li></ul>|

### LLD rule Protocol handlers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Protocol handlers discovery|<p>Discovery for ProtocolHandler</p>|JMX agent|jmx.discovery[attributes,"Catalina:type=ProtocolHandler,port=*"]|

### Item prototypes for Protocol handlers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#JMXVALUE}: Gzip compression status|<p>Gzip compression status on {#JMXNAME}. Enabling gzip compression may save server bandwidth.</p>|JMX agent|jmx[{#JMXOBJ},compression]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Protocol handlers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#JMXVALUE}: Gzip compression is disabled|<p>gzip compression is disabled for connector {#JMXVALUE}.</p>|`find(/Apache Tomcat by JMX/jmx[{#JMXOBJ},compression],,"like","off") = 1`|Info|**Manual close**: Yes|

### LLD rule Thread pools discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Thread pools discovery|<p>Discovery for ThreadPool</p>|JMX agent|jmx.discovery[beans,"Catalina:type=ThreadPool,name=*"]|

### Item prototypes for Thread pools discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#JMXNAME}: Threads count|<p>Amount of threads the thread pool has right now, both busy and free.</p>|JMX agent|jmx[{#JMXOBJ},currentThreadCount]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|{#JMXNAME}: Threads limit|<p>Limit of the threads count. When currentThreadsBusy counter reaches the maxThreads limit, no more requests could be handled, and the application chokes.</p>|JMX agent|jmx[{#JMXOBJ},maxThreads]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|{#JMXNAME}: Threads busy|<p>Number of the requests that are being currently handled.</p>|JMX agent|jmx[{#JMXOBJ},currentThreadsBusy]|

### Trigger prototypes for Thread pools discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|{#JMXNAME}: Busy worker threads count is high|<p>When current threads busy counter reaches the limit, no more requests could be handled, and the application chokes.</p>|`min(/Apache Tomcat by JMX/jmx[{#JMXOBJ},currentThreadsBusy],{$TOMCAT.THREADS.MAX.TIME:"{#JMXNAME}"})>last(/Apache Tomcat by JMX/jmx[{#JMXOBJ},maxThreads])*{$TOMCAT.THREADS.MAX.PCT:"{#JMXNAME}"}/100`|High||

### LLD rule Contexts discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Contexts discovery|<p>Discovery for contexts</p>|JMX agent|jmx.discovery[beans,"Catalina:type=Manager,host=*,context=*"]|

### Item prototypes for Contexts discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|{#JMXHOST}{#JMXCONTEXT}: Sessions active|<p>Active sessions of the application.</p>|JMX agent|jmx[{#JMXOBJ},activeSessions]|
|{#JMXHOST}{#JMXCONTEXT}: Sessions active maximum so far|<p>Maximum number of active sessions so far.</p>|JMX agent|jmx[{#JMXOBJ},maxActive]|
|{#JMXHOST}{#JMXCONTEXT}: Sessions created per second|<p>Rate of sessions created by this application per second.</p>|JMX agent|jmx[{#JMXOBJ},sessionCounter]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|{#JMXHOST}{#JMXCONTEXT}: Sessions rejected per second|<p>Rate of sessions we rejected due to maxActive being reached.</p>|JMX agent|jmx[{#JMXOBJ},rejectedSessions]<p>**Preprocessing**</p><ul><li>Change per second</li></ul>|
|{#JMXHOST}{#JMXCONTEXT}: Sessions allowed maximum|<p>The maximum number of active Sessions allowed, or -1 for no limit.</p>|JMX agent|jmx[{#JMXOBJ},maxActiveSessions]|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

