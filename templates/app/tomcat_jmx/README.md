
# Apache Tomcat by JMX

## Overview

For Zabbix version: 6.0 and higher  
Official JMX Template for Apache Tomcat.


This template was tested on:

- Apache Tomcat, version 8.5.59

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/jmx) for basic instructions.

Metrics are collected by JMX.

1. Enable and configure JMX access to Apache Tomcat.
 See documentation for [instructions](https://tomcat.apache.org/tomcat-10.0-doc/monitoring.html#Enabling_JMX_Remote) (chose your version).
2. If your Tomcat installation require authentication for JMX, set values in host macros {$TOMCAT.USERNAME} and {$TOMCAT.PASSWORD}.
3. You can set custom macro values and add macros with context for specific metrics following macro description.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$TOMCAT.LLD.FILTER.MATCHES} |<p>Filter for discoverable objects. Can be used with following contexts: "GlobalRequestProcessor", "ThreadPool", "Manager"</p> |`.*` |
|{$TOMCAT.LLD.FILTER.NOT_MATCHES} |<p>Filter to exclude discovered objects. Can be used with following contexts: "GlobalRequestProcessor", "ThreadPool", "Manager"</p> |`CHANGE IF NEEDED` |
|{$TOMCAT.PASSWORD} |<p>Password for JMX</p> |`` |
|{$TOMCAT.THREADS.MAX.PCT} |<p>Threshold for busy worker threads trigger. Can be used with {#JMXNAME} as context.</p> |`75` |
|{$TOMCAT.THREADS.MAX.TIME} |<p>The time during which the number of busy threads can exceed the threshold. Can be used with {#JMXNAME} as context.</p> |`5m` |
|{$TOMCAT.USER} |<p>User for JMX</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Contexts discovery |<p>Discovery for contexts</p> |JMX |jmx.discovery[beans,"Catalina:type=Manager,host=*,context=*"]<p>**Filter**:</p>AND <p>- {#JMXHOST} MATCHES_REGEX `{$TOMCAT.LLD.FILTER.MATCHES:"Manager"}`</p><p>- {#JMXHOST} NOT_MATCHES_REGEX `{$TOMCAT.LLD.FILTER.NOT_MATCHES:"Manager"}`</p> |
|Global request processors discovery |<p>Discovery for GlobalRequestProcessor</p> |JMX |jmx.discovery[beans,"Catalina:type=GlobalRequestProcessor,name=*"]<p>**Filter**:</p>AND <p>- {#JMXNAME} MATCHES_REGEX `{$TOMCAT.LLD.FILTER.MATCHES:"GlobalRequestProcessor"}`</p><p>- {#JMXNAME} NOT_MATCHES_REGEX `{$TOMCAT.LLD.FILTER.NOT_MATCHES:"GlobalRequestProcessor"}`</p> |
|Protocol handlers discovery |<p>Discovery for ProtocolHandler</p> |JMX |jmx.discovery[attributes,"Catalina:type=ProtocolHandler,port=*"]<p>**Filter**:</p>AND <p>- {#JMXATTR} MATCHES_REGEX `^name$`</p> |
|Thread pools discovery |<p>Discovery for ThreadPool</p> |JMX |jmx.discovery[beans,"Catalina:type=ThreadPool,name=*"]<p>**Filter**:</p>AND <p>- {#JMXNAME} MATCHES_REGEX `{$TOMCAT.LLD.FILTER.MATCHES:"ThreadPool"}`</p><p>- {#JMXNAME} NOT_MATCHES_REGEX `{$TOMCAT.LLD.FILTER.NOT_MATCHES:"ThreadPool"}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Tomcat |Tomcat: Version |<p>The version of the Tomcat.</p> |JMX |jmx["Catalina:type=Server",serverInfo]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Tomcat |{#JMXNAME}: Bytes received per second |<p>Bytes received rate by processor {#JMXNAME}</p> |JMX |jmx[{#JMXOBJ},bytesReceived]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Tomcat |{#JMXNAME}: Bytes sent per second |<p>Bytes sent rate by processor {#JMXNAME}</p> |JMX |jmx[{#JMXOBJ},bytesSent]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Tomcat |{#JMXNAME}: Errors per second |<p>Error rate of request processor {#JMXNAME}</p> |JMX |jmx[{#JMXOBJ},errorCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Tomcat |{#JMXNAME}: Requests per second |<p>Rate of requests served by request processor {#JMXNAME}</p> |JMX |jmx[{#JMXOBJ},requestCount]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Tomcat |{#JMXNAME}: Requests processing time |<p>The total time to process all incoming requests of request processor</p><p>{#JMXNAME}</p> |JMX |jmx[{#JMXOBJ},processingTime]<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.001`</p> |
|Tomcat |{#JMXVALUE}: Gzip compression status |<p>Gzip compression status on {#JMXNAME}. Enabling gzip compression may save server bandwidth.</p> |JMX |jmx[{#JMXOBJ},compression]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Tomcat |{#JMXNAME}: Threads count |<p>Amount of threads the thread pool has right now, both busy and free.</p> |JMX |jmx[{#JMXOBJ},currentThreadCount]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Tomcat |{#JMXNAME}: Threads limit |<p>Limit of the threads count. When currentThreadsBusy counter reaches the maxThreads limit, no more requests could be handled, and the application chokes.</p> |JMX |jmx[{#JMXOBJ},maxThreads]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Tomcat |{#JMXNAME}: Threads busy |<p>Number of the requests that are being currently handled.</p> |JMX |jmx[{#JMXOBJ},currentThreadsBusy] |
|Tomcat |{#JMXHOST}{#JMXCONTEXT}: Sessions active |<p>Active sessions of the application.</p> |JMX |jmx[{#JMXOBJ},activeSessions] |
|Tomcat |{#JMXHOST}{#JMXCONTEXT}: Sessions active maximum so far |<p>Maximum number of active sessions so far.</p> |JMX |jmx[{#JMXOBJ},maxActive] |
|Tomcat |{#JMXHOST}{#JMXCONTEXT}: Sessions created per second |<p>Rate of sessions created by this application per second.</p> |JMX |jmx[{#JMXOBJ},sessionCounter]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Tomcat |{#JMXHOST}{#JMXCONTEXT}: Sessions rejected per second |<p>Rate of sessions we rejected due to maxActive being reached.</p> |JMX |jmx[{#JMXOBJ},rejectedSessions]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND</p> |
|Tomcat |{#JMXHOST}{#JMXCONTEXT}: Sessions allowed maximum |<p>The maximum number of active Sessions allowed, or -1 for no limit.</p> |JMX |jmx[{#JMXOBJ},maxActiveSessions] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Tomcat: Version has been changed |<p>Tomcat version has changed. Ack to close.</p> |`last(/Apache Tomcat by JMX/jmx["Catalina:type=Server",serverInfo],#1)<>last(/Apache Tomcat by JMX/jmx["Catalina:type=Server",serverInfo],#2) and length(last(/Apache Tomcat by JMX/jmx["Catalina:type=Server",serverInfo]))>0` |INFO |<p>Manual close: YES</p> |
|{#JMXVALUE}: Gzip compression is disabled |<p>gzip compression is disabled for connector {#JMXVALUE}.</p> |`find(/Apache Tomcat by JMX/jmx[{#JMXOBJ},compression],,"like","off") = 1` |INFO |<p>Manual close: YES</p> |
|{#JMXNAME}: Busy worker threads count is high |<p>When current threads busy counter reaches the limit, no more requests could be handled, and the application chokes.</p> |`min(/Apache Tomcat by JMX/jmx[{#JMXOBJ},currentThreadsBusy],{$TOMCAT.THREADS.MAX.TIME:"{#JMXNAME}"})>last(/Apache Tomcat by JMX/jmx[{#JMXOBJ},maxThreads])*{$TOMCAT.THREADS.MAX.PCT:"{#JMXNAME}"}/100` |HIGH | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/411862-discussion-thread-for-official-zabbix-template-tomcat).

