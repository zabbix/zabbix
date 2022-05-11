
# IIS by Zabbix agent active

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor IIS (Internet Information Services) by Zabbix that works without any external scripts.


This template was tested on:

- Windows Server, version 2012R2

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/zabbix_agent) for basic instructions.

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


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$IIS.APPPOOL.MATCHES} |<p>This macro is used in application pools discovery. Can be overridden on the host or linked template level.</p> |`.+` |
|{$IIS.APPPOOL.MONITORED} |<p>Monitoring status for discovered application pools. Use context to avoid trigger firing for specific application pools. "1" - enabled, "0" - disabled.</p> |`1` |
|{$IIS.APPPOOL.NOT_MATCHES} |<p>This macro is used in application pools discovery. Can be overridden on the host or linked template level.</p> |`<CHANGE_IF_NEEDED>` |
|{$IIS.PORT} |<p>Listening port.</p> |`80` |
|{$IIS.QUEUE.MAX.TIME} |<p>The time during which the queue length may exceed the threshold.</p> |`5m` |
|{$IIS.QUEUE.MAX.WARN} |<p>Maximum application pool's request queue length for trigger expression.</p> |`` |
|{$IIS.SERVICE} |<p>The service (http/https/etc) for port check. See "net.tcp.service" documentation page for more information: https://www.zabbix.com/documentation/6.0/manual/config/items/itemtypes/simple_checks</p> |`http` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Application pools discovery |<p>-</p> |ZABBIX_ACTIVE |wmi.getall[root\webAdministration, select Name from ApplicationPool]<p>**Filter**:</p>AND <p>- {#APPPOOL} NOT_MATCHES_REGEX `{$IIS.APPPOOL.NOT_MATCHES}`</p><p>- {#APPPOOL} MATCHES_REGEX `{$IIS.APPPOOL.MATCHES}`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|IIS |IIS: World Wide Web Publishing Service (W3SVC) state |<p>The World Wide Web Publishing Service (W3SVC) provides web connectivity and administration of websites through the IIS snap-in. If the World Wide Web Publishing Service stops, the operating system cannot serve any form of web request. This service was dependent on "Windows Process Activation Service".</p> |ZABBIX_ACTIVE |service_state[W3SVC]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Windows Process Activation Service (WAS) state |<p>Windows Process Activation Service (WAS) is a tool for managing worker processes that contain applications that host Windows Communication Foundation (WCF) services. Worker processes handle requests that are sent to a Web Server for specific application pools. Each application pool sets boundaries for the applications it contains.</p> |ZABBIX_ACTIVE |service_state[WAS]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: {$IIS.PORT} port ping |<p>-</p> |SIMPLE |net.tcp.service[{$IIS.SERVICE},,{$IIS.PORT}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Uptime |<p>Service uptime in seconds.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Service Uptime"] |
|IIS |IIS: Bytes Received per second |<p>The average rate per minute at which data bytes are received by the service at the Application Layer. Does not include protocol headers or control bytes.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Bytes Received/sec", 60] |
|IIS |IIS: Bytes Sent per second |<p>The average rate per minute at which data bytes are sent by the service.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Bytes Sent/sec", 60] |
|IIS |IIS: Bytes Total per second |<p>The average rate per minute of total bytes/sec transferred by the Web service (sum of bytes sent/sec and bytes received/sec).</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Bytes Total/Sec", 60] |
|IIS |IIS: Current connections |<p>The number of active connections.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Current Connections"] |
|IIS |IIS: Total connection attempts |<p>The total number of connections to the Web or FTP service that have been attempted since service startup. The count is the total for all Web sites or FTP sites combined.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Total Connection Attempts (all instances)"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Connection attempts per second |<p>The average rate per minute that connections using the Web service are being attempted. The count is the average for all Web sites combined.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Connection Attempts/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Anonymous users per second |<p>The number of requests from users over an anonymous connection per second. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Anonymous Users/sec", 60] |
|IIS |IIS: NonAnonymous users per second |<p>The number of requests from users over a non-anonymous connection per second. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\NonAnonymous Users/sec", 60] |
|IIS |IIS: Method GET requests per second |<p>The rate of HTTP requests made using the GET method. GET requests are generally used for basic file retrievals or image maps, though they can be used with forms. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Get Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method COPY requests per second |<p>The rate of HTTP requests made using the COPY method. Copy requests are used for copying files and directories. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Copy Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method CGI requests per second |<p>The rate of CGI requests that are simultaneously being processed by the Web service. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\CGI Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method DELETE requests per second |<p>The rate of HTTP requests using the DELETE method made. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Delete Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method HEAD requests per second |<p>The rate of HTTP requests using the HEAD method made. HEAD requests generally indicate a client is querying the state of a document they already have to see if it needs to be refreshed. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Head Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method ISAPI requests per second |<p>The rate of ISAPI Extension requests that are simultaneously being processed by the Web service. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\ISAPI Extension Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method LOCK requests per second |<p>The rate of HTTP requests made using the LOCK method. Lock requests are used to lock a file for one user so that only that user can modify the file. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Lock Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method MKCOL requests per second |<p>The rate of HTTP requests using the MKCOL method made. Mkcol requests are used to create directories on the server. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Mkcol Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method MOVE requests per second |<p>The rate of HTTP requests using the MOVE method made. Move requests are used for moving files and directories. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Move Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method OPTIONS requests per second |<p>The rate of HTTP requests using the OPTIONS method made. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Options Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method POST requests per second |<p>Rate of HTTP requests using POST method. Generally used for forms or gateway requests. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Post Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method PROPFIND requests per second |<p>The rate of HTTP requests using the PROPFIND method made. Propfind requests retrieve property values on files and directories. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Propfind Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method PROPPATCH requests per second |<p>The rate of HTTP requests using the PROPPATCH method made. Proppatch requests set property values on files and directories. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Proppatch Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method PUT requests per second |<p>The rate of HTTP requests using the PUT method made. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Put Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method MS-SEARCH requests per second |<p>The rate of HTTP requests using the MS-SEARCH method made. Search requests are used to query the server to find resources that match a set of conditions provided by the client. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Search Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method TRACE requests per second |<p>The rate of HTTP requests using the TRACE method made. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Trace Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method TRACE requests per second |<p>The rate of HTTP requests using the UNLOCK method made. Unlock requests are used to remove locks from files. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Unlock Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method Total requests per second |<p>The rate of all HTTP requests received. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Total Method Requests/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Method Total Other requests per second |<p>Total Other Request Methods is the number of HTTP requests that are not OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, MOVE, COPY, MKCOL, PROPFIND, PROPPATCH, SEARCH, LOCK or UNLOCK methods (since service startup). Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Other Request Methods/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Locked errors per second |<p>The rate of errors due to requests that couldn't be satisfied by the server because the requested document was locked. These are generally reported as an HTTP 423 error code to the client. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Locked Errors/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Not Found errors per second |<p>The rate of errors due to requests that couldn't be satisfied by the server because the requested document could not be found. These are generally reported to the client with HTTP error code 404. Average per minute.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service(_Total)\Not Found Errors/Sec", 60]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: Files cache hits percentage |<p>The ratio of user-mode file cache hits to total cache requests (since service startup). Note: This value might be low if the Kernel URI cache hits percentage is high.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service Cache\File Cache Hits %"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: URIs cache hits percentage |<p>The ratio of user-mode URI Cache Hits to total cache requests (since service startup)</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service Cache\URI Cache Hits %"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: File cache misses |<p>The total number of unsuccessful lookups in the user-mode file cache since service startup.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service Cache\File Cache Misses"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: URI cache misses |<p>The total number of unsuccessful lookups in the user-mode URI cache since service startup.</p> |ZABBIX_ACTIVE |perf_counter_en["\Web Service Cache\URI Cache Misses"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: {#APPPOOL} Uptime |<p>The web application uptime period since the last restart.</p> |ZABBIX_ACTIVE |perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Current Application Pool Uptime"] |
|IIS |IIS: AppPool {#APPPOOL} state |<p>The state of the application pool.</p> |ZABBIX_ACTIVE |perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Current Application Pool State"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: AppPool {#APPPOOL} recycles |<p>The number of times the application pool has been recycled since Windows Process Activation Service (WAS) started.</p> |ZABBIX_ACTIVE |perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Total Application Pool Recycles"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|IIS |IIS: AppPool {#APPPOOL} current queue size |<p>The number of requests in the queue.</p> |ZABBIX_ACTIVE |perf_counter_en["\HTTP Service Request Queues({#APPPOOL})\CurrentQueueSize"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|IIS: The World Wide Web Publishing Service (W3SVC) is not running |<p>The World Wide Web Publishing Service (W3SVC) is not in running state. IIS cannot start.</p> |`last(/IIS by Zabbix agent active/service_state[W3SVC])<>0` |HIGH |<p>**Depends on**:</p><p>- IIS: Windows process Activation Service (WAS) is not the running</p> |
|IIS: Windows process Activation Service (WAS) is not the running |<p>Windows Process Activation Service (WAS) is not in the running state. IIS cannot start.</p> |`last(/IIS by Zabbix agent active/service_state[WAS])<>0` |HIGH | |
|IIS: Port {$IIS.PORT} is down |<p>-</p> |`last(/IIS by Zabbix agent active/net.tcp.service[{$IIS.SERVICE},,{$IIS.PORT}])=0` |AVERAGE |<p>Manual close: YES</p><p>**Depends on**:</p><p>- IIS: The World Wide Web Publishing Service (W3SVC) is not running</p> |
|IIS: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/IIS by Zabbix agent active/perf_counter_en["\Web Service(_Total)\Service Uptime"])<10m` |INFO |<p>Manual close: YES</p> |
|IIS: {#APPPOOL} has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/IIS by Zabbix agent active/perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Current Application Pool Uptime"])<10m` |INFO |<p>Manual close: YES</p> |
|IIS: Application pool {#APPPOOL} is not in Running state |<p>-</p> |`last(/IIS by Zabbix agent active/perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Current Application Pool State"])<>3 and {$IIS.APPPOOL.MONITORED:"{#APPPOOL}"}=1` |HIGH |<p>**Depends on**:</p><p>- IIS: The World Wide Web Publishing Service (W3SVC) is not running</p> |
|IIS: Application pool {#APPPOOL} has been recycled |<p>-</p> |`last(/IIS by Zabbix agent active/perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Total Application Pool Recycles"],#1)<>last(/IIS by Zabbix agent active/perf_counter_en["\APP_POOL_WAS({#APPPOOL})\Total Application Pool Recycles"],#2) and {$IIS.APPPOOL.MONITORED:"{#APPPOOL}"}=1` |INFO | |
|IIS: Request queue of {#APPPOOL} is too large |<p>-</p> |`min(/IIS by Zabbix agent active/perf_counter_en["\HTTP Service Request Queues({#APPPOOL})\CurrentQueueSize"],{$IIS.QUEUE.MAX.TIME})>{$IIS.QUEUE.MAX.WARN}` |WARNING |<p>**Depends on**:</p><p>- IIS: Application pool {#APPPOOL} is not in Running state</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/401862-discussion-thread-for-official-zabbix-template-internet-information-services).

