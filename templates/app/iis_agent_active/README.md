
# IIS by Zabbix agent active

## Overview

The template to monitor IIS (Internet Information Services) by Zabbix that works without any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Windows Server 2012R2

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

You have to enable the following Windows Features (Control Panel > Programs and Features > Turn Windows features on or off) on your server
```text
Web Server (IIS)
Web Server (IIS)\Management Tools\IIS Management Scripts and Tools
```

Optionally, it is possible to customize the template:
- Set value for the macro {$IIS.QUEUE.MAX.WARN}, if you want to receive alerts when a number of requests in the application pool queue exceeds the threshold.
- If you use a non-standard port for the IIS, don't forget to update the macros {$IIS.SERVICE} and {$IIS.PORT}.
- Change the value of macro {$IIS.APPPOOL.MONITORED} to "0", if you want to disable all notifications about application pools state.<br>
You can also add additional context macro {$IIS.APPPOOL.MONITORED:<AppPoolName>} for excluding specific application pools from monitoring.
- Change regexp in the macros {$IIS.APPPOOL.MATCHES} and {$IIS.APPPOOL.NOT_MATCHES} used for filtering application pools discovery results.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$IIS.PORT}|<p>Listening port.</p>|`80`|
|{$IIS.SERVICE}|<p>The service (http/https/etc) for port check. See "net.tcp.service" documentation page for more information: https://www.zabbix.com/documentation/7.0/manual/config/items/itemtypes/simple_checks</p>|`http`|
|{$IIS.QUEUE.MAX.WARN}|<p>Maximum application pool's request queue length for trigger expression.</p>||
|{$IIS.QUEUE.MAX.TIME}|<p>The time during which the queue length may exceed the threshold.</p>|`5m`|
|{$IIS.APPPOOL.NOT_MATCHES}|<p>This macro is used in application pools discovery. Can be overridden on the host or linked template level.</p>|`<CHANGE_IF_NEEDED>`|
|{$IIS.APPPOOL.MATCHES}|<p>This macro is used in application pools discovery. Can be overridden on the host or linked template level.</p>|`.+`|
|{$IIS.APPPOOL.MONITORED}|<p>Monitoring status for discovered application pools. Use context to avoid trigger firing for specific application pools. "1" - enabled, "0" - disabled.</p>|`1`|
|{$AGENT.TIMEOUT}|<p>Timeout after which agent is considered unavailable.</p>|`5m`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|IIS: World Wide Web Publishing Service (W3SVC) state|<p>The World Wide Web Publishing Service (W3SVC) provides web connectivity and administration of websites through the IIS snap-in. If the World Wide Web Publishing Service stops, the operating system cannot serve any form of web request. This service was dependent on "Windows Process Activation Service".</p>|Zabbix agent (active)|service.info[W3SVC]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Windows Process Activation Service (WAS) state|<p>Windows Process Activation Service (WAS) is a tool for managing worker processes that contain applications that host Windows Communication Foundation (WCF) services. Worker processes handle requests that are sent to a Web Server for specific application pools. Each application pool sets boundaries for the applications it contains.</p>|Zabbix agent (active)|service.info[WAS]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: {$IIS.PORT} port ping||Simple check|net.tcp.service[{$IIS.SERVICE},,{$IIS.PORT}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Uptime|<p>The service uptime expressed in seconds.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Service Uptime"]|
|IIS: Bytes Received per second|<p>The average rate per minute at which data bytes are received by the service at the Application Layer. Does not include protocol headers or control bytes.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Bytes Received/sec", 60]|
|IIS: Bytes Sent per second|<p>The average rate per minute at which data bytes are sent by the service.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Bytes Sent/sec", 60]|
|IIS: Bytes Total per second|<p>The average rate per minute of total bytes/sec transferred by the Web service (sum of bytes sent/sec and bytes received/sec).</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Bytes Total/Sec", 60]|
|IIS: Current connections|<p>The number of active connections.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Current Connections"]|
|IIS: Total connection attempts|<p>The total number of connections to the Web or FTP service that have been attempted since service startup. The count is the total for all Web sites or FTP sites combined.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Total Connection Attempts (all instances)"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Connection attempts per second|<p>The average rate per minute that connections using the Web service are being attempted. The count is the average for all Web sites combined.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Connection Attempts/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Anonymous users per second|<p>The number of requests from users over an anonymous connection per second. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Anonymous Users/sec", 60]|
|IIS: NonAnonymous users per second|<p>The number of requests from users over a non-anonymous connection per second. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\NonAnonymous Users/sec", 60]|
|IIS: Method GET requests per second|<p>The rate of HTTP requests made using the GET method. GET requests are generally used for basic file retrievals or image maps, though they can be used with forms. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Get Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method COPY requests per second|<p>The rate of HTTP requests made using the COPY method. Copy requests are used for copying files and directories. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Copy Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method CGI requests per second|<p>The rate of CGI requests that are simultaneously being processed by the Web service. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\CGI Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method DELETE requests per second|<p>The rate of HTTP requests using the DELETE method made. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Delete Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method HEAD requests per second|<p>The rate of HTTP requests using the HEAD method made. HEAD requests generally indicate a client is querying the state of a document they already have to see if it needs to be refreshed. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Head Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method ISAPI requests per second|<p>The rate of ISAPI Extension requests that are simultaneously being processed by the Web service. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\ISAPI Extension Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method LOCK requests per second|<p>The rate of HTTP requests made using the LOCK method. Lock requests are used to lock a file for one user so that only that user can modify the file. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Lock Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method MKCOL requests per second|<p>The rate of HTTP requests using the MKCOL method made. Mkcol requests are used to create directories on the server. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Mkcol Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method MOVE requests per second|<p>The rate of HTTP requests using the MOVE method made. Move requests are used for moving files and directories. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Move Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method OPTIONS requests per second|<p>The rate of HTTP requests using the OPTIONS method made. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Options Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method POST requests per second|<p>Rate of HTTP requests using POST method. Generally used for forms or gateway requests. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Post Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method PROPFIND requests per second|<p>The rate of HTTP requests using the PROPFIND method made. Propfind requests retrieve property values on files and directories. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Propfind Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method PROPPATCH requests per second|<p>The rate of HTTP requests using the PROPPATCH method made. Proppatch requests set property values on files and directories. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Proppatch Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method PUT requests per second|<p>The rate of HTTP requests using the PUT method made. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Put Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method MS-SEARCH requests per second|<p>The rate of HTTP requests using the MS-SEARCH method made. Search requests are used to query the server to find resources that match a set of conditions provided by the client. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Search Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method TRACE requests per second|<p>The rate of HTTP requests using the TRACE method made. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Trace Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method TRACE requests per second|<p>The rate of HTTP requests using the UNLOCK method made. Unlock requests are used to remove locks from files. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Unlock Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method Total requests per second|<p>The rate of all HTTP requests received. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Total Method Requests/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Method Total Other requests per second|<p>Total Other Request Methods is the number of HTTP requests that are not OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, MOVE, COPY, MKCOL, PROPFIND, PROPPATCH, SEARCH, LOCK or UNLOCK methods (since service startup). Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Other Request Methods/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Locked errors per second|<p>The rate of errors due to requests that couldn't be satisfied by the server because the requested document was locked. These are generally reported as an HTTP 423 error code to the client. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Locked Errors/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Not Found errors per second|<p>The rate of errors due to requests that couldn't be satisfied by the server because the requested document could not be found. These are generally reported to the client with HTTP error code 404. Average per minute.</p>|Zabbix agent (active)|perf_counter_en["\Web Service(_Total)\Not Found Errors/Sec", 60]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Files cache hits percentage|<p>The ratio of user-mode file cache hits to total cache requests (since service startup). Note: This value might be low if the Kernel URI cache hits percentage is high.</p>|Zabbix agent (active)|perf_counter_en["\Web Service Cache\File Cache Hits %"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: URIs cache hits percentage|<p>The ratio of user-mode URI Cache Hits to total cache requests (since service startup)</p>|Zabbix agent (active)|perf_counter_en["\Web Service Cache\URI Cache Hits %"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: File cache misses|<p>The total number of unsuccessful lookups in the user-mode file cache since service startup.</p>|Zabbix agent (active)|perf_counter_en["\Web Service Cache\File Cache Misses"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: URI cache misses|<p>The total number of unsuccessful lookups in the user-mode URI cache since service startup.</p>|Zabbix agent (active)|perf_counter_en["\Web Service Cache\URI Cache Misses"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: Active agent availability|<p>Availability of active checks on the host. The value of this item corresponds to availability icons in the host list.</p><p>Possible value:</p><p>0 - unknown</p><p>1 - available</p><p>2 - not available</p>|Zabbix internal|zabbix[host,active_agent,available]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|IIS: The World Wide Web Publishing Service (W3SVC) is not running|<p>The World Wide Web Publishing Service (W3SVC) is not in the running state. IIS cannot start.</p>|`last(/IIS by Zabbix agent active/service.info[W3SVC])<>0`|High|**Depends on**:<br><ul><li>IIS: Windows process Activation Service (WAS) is not running</li></ul>|
|IIS: Windows process Activation Service (WAS) is not running|<p>Windows Process Activation Service (WAS) is not in the running state. IIS cannot start.</p>|`last(/IIS by Zabbix agent active/service.info[WAS])<>0`|High||
|IIS: Port {$IIS.PORT} is down||`last(/IIS by Zabbix agent active/net.tcp.service[{$IIS.SERVICE},,{$IIS.PORT}])=0`|Average|**Manual close**: Yes<br>**Depends on**:<br><ul><li>IIS: The World Wide Web Publishing Service (W3SVC) is not running</li></ul>|
|IIS: has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/IIS by Zabbix agent active/perf_counter_en["\Web Service(_Total)\Service Uptime"])<10m`|Info|**Manual close**: Yes|
|IIS: Active checks are not available|<p>Active checks are considered unavailable. Agent is not sending heartbeat for prolonged time.</p>|`min(/IIS by Zabbix agent active/zabbix[host,active_agent,available],{$AGENT.TIMEOUT})=2`|High||

### LLD rule Application pools discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Application pools discovery||Zabbix agent (active)|wmi.getall[root\webAdministration, select Name from ApplicationPool]|

### Item prototypes for Application pools discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|IIS: {#APPPOOL} Uptime|<p>The web application uptime period since the last restart.</p>|Zabbix agent (active)|perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Current Application Pool Uptime"]|
|IIS: AppPool {#APPPOOL} state|<p>The state of the application pool.</p>|Zabbix agent (active)|perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Current Application Pool State"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: AppPool {#APPPOOL} recycles|<p>The number of times the application pool has been recycled since Windows Process Activation Service (WAS) started.</p>|Zabbix agent (active)|perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Total Application Pool Recycles"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|IIS: AppPool {#APPPOOL} current queue size|<p>The number of requests in the queue.</p>|Zabbix agent (active)|perf_counter_en["\HTTP Service Request Queues({#APPPOOL})\CurrentQueueSize"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|

### Trigger prototypes for Application pools discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|IIS: {#APPPOOL} has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/IIS by Zabbix agent active/perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Current Application Pool Uptime"])<10m`|Info|**Manual close**: Yes|
|IIS: Application pool {#APPPOOL} is not in Running state||`last(/IIS by Zabbix agent active/perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Current Application Pool State"])<>3 and {$IIS.APPPOOL.MONITORED:"{#APPPOOL}"}=1`|High|**Depends on**:<br><ul><li>IIS: The World Wide Web Publishing Service (W3SVC) is not running</li></ul>|
|IIS: Application pool {#APPPOOL} has been recycled||`last(/IIS by Zabbix agent active/perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Total Application Pool Recycles"],#1)<>last(/IIS by Zabbix agent active/perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Total Application Pool Recycles"],#2) and {$IIS.APPPOOL.MONITORED:"{#APPPOOL}"}=1`|Info||
|IIS: Request queue of {#APPPOOL} is too large||`min(/IIS by Zabbix agent active/perf_counter_en["\HTTP Service Request Queues({#APPPOOL})\CurrentQueueSize"],{$IIS.QUEUE.MAX.TIME})>{$IIS.QUEUE.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>IIS: Application pool {#APPPOOL} is not in Running state</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

