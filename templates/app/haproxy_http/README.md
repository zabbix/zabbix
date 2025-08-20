
# HAProxy by HTTP

## Overview

The template to monitor HAProxy by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

The template collects metrics by polling the HAProxy stats page with HTTP agent.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- HAProxy 1.8

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Set up the [`HAProxy stats page`](https://www.haproxy.com/blog/exploring-the-haproxy-stats-page/).

If you want to use authentication, set the username and password in the `stats auth` option of the configuration file.

The example configuration of HAProxy:

```text
frontend stats
    bind *:8404
    stats enable
    stats uri /stats
    stats refresh 10s
    #stats auth Username:Password  # Authentication credentials
```

2. Set the hostname or IP address of the HAProxy stats host or container in the `{$HAPROXY.STATS.HOST}` macro. You can also change the status page port in the `{$HAPROXY.STATS.PORT}` macro, the status page scheme in the `{$HAPROXY.STATS.SCHEME}` macro and the status page path in the `{$HAPROXY.STATS.PATH}` macro if necessary.

3. If you have enabled authentication in the HAProxy configuration file in step 1, set the username and password in the `{$HAPROXY.USERNAME}` and `{$HAPROXY.PASSWORD}` macros.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$HAPROXY.STATS.SCHEME}|<p>The scheme of HAProxy stats page (http/https).</p>|`http`|
|{$HAPROXY.STATS.HOST}|<p>The hostname or IP address of the HAProxy stats host or container.</p>||
|{$HAPROXY.STATS.PORT}|<p>The port of the HAProxy stats host or container.</p>|`8404`|
|{$HAPROXY.STATS.PATH}|<p>The path of the HAProxy stats page.</p>|`stats`|
|{$HAPROXY.USERNAME}|<p>The username of the HAProxy stats page.</p>||
|{$HAPROXY.PASSWORD}|<p>The password of the HAProxy stats page.</p>||
|{$HAPROXY.RESPONSE_TIME.MAX.WARN}|<p>The HAProxy stats page maximum response time in seconds for trigger expression.</p>|`10s`|
|{$HAPROXY.FRONT_DREQ.MAX.WARN}|<p>The HAProxy maximum denied requests for trigger expression.</p>|`10`|
|{$HAPROXY.FRONT_EREQ.MAX.WARN}|<p>The HAProxy maximum number of request errors for trigger expression.</p>|`10`|
|{$HAPROXY.BACK_QCUR.MAX.WARN}|<p>Maximum number of requests on Backend unassigned in queue for trigger expression.</p>|`10`|
|{$HAPROXY.BACK_RTIME.MAX.WARN}|<p>Maximum of average Backend response time for trigger expression.</p>|`10s`|
|{$HAPROXY.BACK_QTIME.MAX.WARN}|<p>Maximum of average time spent in queue on Backend for trigger expression.</p>|`10s`|
|{$HAPROXY.BACK_ERESP.MAX.WARN}|<p>Maximum of responses with error on Backend for trigger expression.</p>|`10`|
|{$HAPROXY.SERVER_QCUR.MAX.WARN}|<p>Maximum number of requests on server unassigned in queue for trigger expression.</p>|`10`|
|{$HAPROXY.SERVER_RTIME.MAX.WARN}|<p>Maximum of average server response time for trigger expression.</p>|`10s`|
|{$HAPROXY.SERVER_QTIME.MAX.WARN}|<p>Maximum of average time spent in queue on server for trigger expression.</p>|`10s`|
|{$HAPROXY.SERVER_ERESP.MAX.WARN}|<p>Maximum of responses with error on server for trigger expression.</p>|`10`|
|{$HAPROXY.FRONT_SUTIL.MAX.WARN}|<p>Maximum of session usage percentage on frontend for trigger expression.</p>|`80`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get stats|<p>HAProxy Statistics Report in CSV format</p>|HTTP agent|haproxy.get<p>**Preprocessing**</p><ul><li><p>Regular expression: `# ([\s\S]*)\n \1`</p></li><li><p>CSV to JSON</p></li></ul>|
|Get nodes|<p>Array for LLD rules.</p>|Dependent item|haproxy.get.nodes<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Get stats page|<p>HAProxy Statistics Report HTML</p>|HTTP agent|haproxy.get_html|
|Version||Dependent item|haproxy.version<p>**Preprocessing**</p><ul><li><p>Regular expression: `HAProxy version ([^,]*), \1`</p><p>⛔️Custom on fail: Set error to: `HAProxy version is not found`</p></li><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|
|Uptime||Dependent item|haproxy.uptime<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Service status||Simple check|net.tcp.service["{$HAPROXY.STATS.SCHEME}","{$HAPROXY.STATS.HOST}","{$HAPROXY.STATS.PORT}"]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Service response time||Simple check|net.tcp.service.perf["{$HAPROXY.STATS.SCHEME}","{$HAPROXY.STATS.HOST}","{$HAPROXY.STATS.PORT}"]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HAProxy: Version has changed|<p>HAProxy version has changed. Acknowledge to close the problem manually.</p>|`last(/HAProxy by HTTP/haproxy.version,#1)<>last(/HAProxy by HTTP/haproxy.version,#2) and length(last(/HAProxy by HTTP/haproxy.version))>0`|Info|**Manual close**: Yes|
|HAProxy: Service has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/HAProxy by HTTP/haproxy.uptime)<10m`|Info|**Manual close**: Yes|
|HAProxy: Service is down||`last(/HAProxy by HTTP/net.tcp.service["{$HAPROXY.STATS.SCHEME}","{$HAPROXY.STATS.HOST}","{$HAPROXY.STATS.PORT}"])=0`|Average|**Manual close**: Yes|
|HAProxy: Service response time is too high||`min(/HAProxy by HTTP/net.tcp.service.perf["{$HAPROXY.STATS.SCHEME}","{$HAPROXY.STATS.HOST}","{$HAPROXY.STATS.PORT}"],5m)>{$HAPROXY.RESPONSE_TIME.MAX.WARN}`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>HAProxy: Service is down</li></ul>|

### LLD rule Backend discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Backend discovery|<p>Discovery backends</p>|Dependent item|haproxy.backend.discovery|

### Item prototypes for Backend discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Backend {#PXNAME}: Raw data|<p>The raw data of the Backend with the name `{#PXNAME}`</p>|Dependent item|haproxy.backend.raw[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Backend {#PXNAME}: Status|<p>Possible values:</p><p>UP - The server is reporting as healthy.</p><p>DOWN - The server is reporting as unhealthy and unable to receive requests.</p><p>NOLB - You've added http-check disable-on-404 to the backend and the health checked URL has returned an HTTP 404 response.</p><p>MAINT - The server has been disabled or put into maintenance mode.</p><p>DRAIN - The server has been put into drain mode.</p><p>no check - Health checks are not enabled for this server.</p>|Dependent item|haproxy.backend.status[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|Backend {#PXNAME}: Responses time|<p>Average backend response time (in ms) for the last 1,024 requests</p>|Dependent item|haproxy.backend.rtime[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rtime`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Backend {#PXNAME}: Errors connection per second|<p>Number of requests that encountered an error attempting to connect to a backend server.</p>|Dependent item|haproxy.backend.econ.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.econ`</p></li><li>Change per second</li></ul>|
|Backend {#PXNAME}: Responses denied per second|<p>Responses denied due to security concerns (ACL-restricted).</p>|Dependent item|haproxy.backend.dresp.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.dresp`</p></li><li>Change per second</li></ul>|
|Backend {#PXNAME}: Response errors per second|<p>Number of requests whose responses yielded an error</p>|Dependent item|haproxy.backend.eresp.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.eresp`</p></li><li>Change per second</li></ul>|
|Backend {#PXNAME}: Unassigned requests|<p>Current number of requests unassigned in queue.</p>|Dependent item|haproxy.backend.qcur[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.qcur`</p></li></ul>|
|Backend {#PXNAME}: Time in queue|<p>Average time spent in queue (in ms) for the last 1,024 requests</p>|Dependent item|haproxy.backend.qtime[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.qtime`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Backend {#PXNAME}: Redispatched requests per second|<p>Number of times a request was redispatched to a different backend.</p>|Dependent item|haproxy.backend.wredis.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wredis`</p></li><li>Change per second</li></ul>|
|Backend {#PXNAME}: Retried connections per second|<p>Number of times a connection was retried.</p>|Dependent item|haproxy.backend.wretr.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wretr`</p></li><li>Change per second</li></ul>|
|Backend {#PXNAME}: Number of responses with codes 1xx per second|<p>Number of informational HTTP responses per second.</p>|Dependent item|haproxy.backend.hrsp_1xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_1xx`</p></li><li>Change per second</li></ul>|
|Backend {#PXNAME}: Number of responses with codes 2xx per second|<p>Number of successful HTTP responses per second.</p>|Dependent item|haproxy.backend.hrsp_2xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_2xx`</p></li><li>Change per second</li></ul>|
|Backend {#PXNAME}: Number of responses with codes 3xx per second|<p>Number of HTTP redirections per second.</p>|Dependent item|haproxy.backend.hrsp_3xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_3xx`</p></li><li>Change per second</li></ul>|
|Backend {#PXNAME}: Number of responses with codes 4xx per second|<p>Number of HTTP client errors per second.</p>|Dependent item|haproxy.backend.hrsp_4xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_4xx`</p></li><li>Change per second</li></ul>|
|Backend {#PXNAME}: Number of responses with codes 5xx per second|<p>Number of HTTP server errors per second.</p>|Dependent item|haproxy.backend.hrsp_5xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_5xx`</p></li><li>Change per second</li></ul>|
|Backend {#PXNAME}: Incoming traffic|<p>Number of bits received by the backend</p>|Dependent item|haproxy.backend.bin.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bin`</p></li><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|Backend {#PXNAME}: Outgoing traffic|<p>Number of bits sent by the backend</p>|Dependent item|haproxy.backend.bout.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bout`</p></li><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|Backend {#PXNAME}: Number of active servers|<p>Number of active servers.</p>|Dependent item|haproxy.backend.act[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.act`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Backend {#PXNAME}: Number of backup servers|<p>Number of backup servers.</p>|Dependent item|haproxy.backend.bck[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bck`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Backend {#PXNAME}: Sessions per second|<p>Cumulative number of sessions (end-to-end connections) per second.</p>|Dependent item|haproxy.backend.stot.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stot`</p></li><li>Change per second</li></ul>|
|Backend {#PXNAME}: Weight|<p>Total effective weight.</p>|Dependent item|haproxy.backend.weight[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.weight`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Backend discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HAProxy: backend {#PXNAME}: Server is DOWN|<p>Backend is not available.</p>|`count(/HAProxy by HTTP/haproxy.backend.status[{#PXNAME},{#SVNAME}],#5,"eq","DOWN")=5`|Average||
|HAProxy: backend {#PXNAME}: Average response time is high|<p>Average backend response time (in ms) for the last 1,024 requests is more than {$HAPROXY.BACK_RTIME.MAX.WARN}.</p>|`min(/HAProxy by HTTP/haproxy.backend.rtime[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.BACK_RTIME.MAX.WARN}`|Warning||
|HAProxy: backend {#PXNAME}: Number of responses with error is high|<p>Number of requests on backend, whose responses yielded an error, is more than {$HAPROXY.BACK_ERESP.MAX.WARN}.</p>|`min(/HAProxy by HTTP/haproxy.backend.eresp.rate[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.BACK_ERESP.MAX.WARN}`|Warning||
|HAProxy: backend {#PXNAME}: Current number of requests unassigned in queue is high|<p>Current number of requests on backend unassigned in queue is more than {$HAPROXY.BACK_QCUR.MAX.WARN}.</p>|`min(/HAProxy by HTTP/haproxy.backend.qcur[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.BACK_QCUR.MAX.WARN}`|Warning||
|HAProxy: backend {#PXNAME}: Average time spent in queue is high|<p>Average time spent in queue (in ms) for the last 1,024 requests is more than {$HAPROXY.BACK_QTIME.MAX.WARN}.</p>|`min(/HAProxy by HTTP/haproxy.backend.qtime[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.BACK_QTIME.MAX.WARN}`|Warning||

### LLD rule Frontend discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Frontend discovery|<p>Discovery frontends</p>|Dependent item|haproxy.frontend.discovery|

### Item prototypes for Frontend discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Frontend {#PXNAME}: Raw data|<p>The raw data of the Frontend with the name `{#PXNAME}`</p>|Dependent item|haproxy.frontend.raw[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|Frontend {#PXNAME}: Status|<p>Possible values: OPEN, STOP.</p><p>When Status is OPEN, the frontend is operating normally and ready to receive traffic.</p>|Dependent item|haproxy.frontend.status[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Frontend {#PXNAME}: Requests rate|<p>HTTP requests per second</p>|Dependent item|haproxy.frontend.req_rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.req_rate`</p></li></ul>|
|Frontend {#PXNAME}: Sessions rate|<p>Number of sessions created per second</p>|Dependent item|haproxy.frontend.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rate`</p></li></ul>|
|Frontend {#PXNAME}: Established sessions|<p>The current number of established sessions.</p>|Dependent item|haproxy.frontend.scur[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.scur`</p></li></ul>|
|Frontend {#PXNAME}: Session limits|<p>The most simultaneous sessions that are allowed, as defined by the maxconn setting in the frontend.</p>|Dependent item|haproxy.frontend.slim[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.slim`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Frontend {#PXNAME}: Session utilization|<p>Percentage of sessions used (scur / slim * 100).</p>|Calculated|haproxy.frontend.sutil[{#PXNAME},{#SVNAME}]|
|Frontend {#PXNAME}: Request errors per second|<p>Number of request errors per second.</p>|Dependent item|haproxy.frontend.ereq.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.ereq`</p></li><li>Change per second</li></ul>|
|Frontend {#PXNAME}: Denied requests per second|<p>Requests denied due to security concerns (ACL-restricted) per second.</p>|Dependent item|haproxy.frontend.dreq.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.dreq`</p></li><li>Change per second</li></ul>|
|Frontend {#PXNAME}: Number of responses with codes 1xx per second|<p>Number of informational HTTP responses per second.</p>|Dependent item|haproxy.frontend.hrsp_1xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_1xx`</p></li><li>Change per second</li></ul>|
|Frontend {#PXNAME}: Number of responses with codes 2xx per second|<p>Number of successful HTTP responses per second.</p>|Dependent item|haproxy.frontend.hrsp_2xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_2xx`</p></li><li>Change per second</li></ul>|
|Frontend {#PXNAME}: Number of responses with codes 3xx per second|<p>Number of HTTP redirections per second.</p>|Dependent item|haproxy.frontend.hrsp_3xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_3xx`</p></li><li>Change per second</li></ul>|
|Frontend {#PXNAME}: Number of responses with codes 4xx per second|<p>Number of HTTP client errors per second.</p>|Dependent item|haproxy.frontend.hrsp_4xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_4xx`</p></li><li>Change per second</li></ul>|
|Frontend {#PXNAME}: Number of responses with codes 5xx per second|<p>Number of HTTP server errors per second.</p>|Dependent item|haproxy.frontend.hrsp_5xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_5xx`</p></li><li>Change per second</li></ul>|
|Frontend {#PXNAME}: Incoming traffic|<p>Number of bits received by the frontend</p>|Dependent item|haproxy.frontend.bin.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bin`</p></li><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|Frontend {#PXNAME}: Outgoing traffic|<p>Number of bits sent by the frontend</p>|Dependent item|haproxy.frontend.bout.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bout`</p></li><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|

### Trigger prototypes for Frontend discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HAProxy: frontend {#PXNAME}: Session utilization is high|<p>Alerting on this metric is essential to ensure your server has sufficient capacity to handle all concurrent sessions. Unlike requests, upon reaching the session limit HAProxy will deny additional clients until resource consumption drops. Furthermore, if you find your session usage percentage to be hovering above 80%, it could be time to either modify HAProxy's configuration to allow more sessions, or migrate your HAProxy server to a bigger box.</p>|`min(/HAProxy by HTTP/haproxy.frontend.sutil[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.FRONT_SUTIL.MAX.WARN}`|Warning||
|HAProxy: frontend {#PXNAME}: Number of request errors is high|<p>Number of request errors is more than {$HAPROXY.FRONT_EREQ.MAX.WARN}.</p>|`min(/HAProxy by HTTP/haproxy.frontend.ereq.rate[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.FRONT_EREQ.MAX.WARN}`|Warning||
|HAProxy: frontend {#PXNAME}: Number of requests denied is high|<p>Number of requests denied due to security concerns (ACL-restricted) is more than {$HAPROXY.FRONT_DREQ.MAX.WARN}.</p>|`min(/HAProxy by HTTP/haproxy.frontend.dreq.rate[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.FRONT_DREQ.MAX.WARN}`|Warning||

### LLD rule Server discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Server discovery|<p>Discovery servers</p>|Dependent item|haproxy.server.discovery|

### Item prototypes for Server discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Server {#PXNAME} {#SVNAME}: Raw data|<p>The raw data of the Server named `{#SVNAME}` and the proxy with the name `{#PXNAME}`</p>|Dependent item|haproxy.server.raw[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|{#PXNAME} {#SVNAME}: Status||Dependent item|haproxy.server.status[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|
|{#PXNAME} {#SVNAME}: Responses time|<p>Average server response time (in ms) for the last 1,024 requests.</p>|Dependent item|haproxy.server.rtime[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.rtime`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#PXNAME} {#SVNAME}: Errors connection per second|<p>Number of requests that encountered an error attempting to connect to a backend server.</p>|Dependent item|haproxy.server.econ.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.econ`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Responses denied per second|<p>Responses denied due to security concerns (ACL-restricted).</p>|Dependent item|haproxy.server.dresp.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.dresp`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Response errors per second|<p>Number of requests whose responses yielded an error.</p>|Dependent item|haproxy.server.eresp.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.eresp`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Unassigned requests|<p>Current number of requests unassigned in queue.</p>|Dependent item|haproxy.server.qcur[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.qcur`</p></li></ul>|
|{#PXNAME} {#SVNAME}: Time in queue|<p>Average time spent in queue (in ms) for the last 1,024 requests.</p>|Dependent item|haproxy.server.qtime[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.qtime`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|{#PXNAME} {#SVNAME}: Redispatched requests per second|<p>Number of times a request was redispatched to a different backend.</p>|Dependent item|haproxy.server.wredis.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wredis`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Retried connections per second|<p>Number of times a connection was retried.</p>|Dependent item|haproxy.server.wretr.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.wretr`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Number of responses with codes 1xx per second|<p>Number of informational HTTP responses per second.</p>|Dependent item|haproxy.server.hrsp_1xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_1xx`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Number of responses with codes 2xx per second|<p>Number of successful HTTP responses per second.</p>|Dependent item|haproxy.server.hrsp_2xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_2xx`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Number of responses with codes 3xx per second|<p>Number of HTTP redirections per second.</p>|Dependent item|haproxy.server.hrsp_3xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_3xx`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Number of responses with codes 4xx per second|<p>Number of HTTP client errors per second.</p>|Dependent item|haproxy.server.hrsp_4xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_4xx`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Number of responses with codes 5xx per second|<p>Number of HTTP server errors per second.</p>|Dependent item|haproxy.server.hrsp_5xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.hrsp_5xx`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Incoming traffic|<p>Number of bits received by the backend</p>|Dependent item|haproxy.server.bin.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bin`</p></li><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Outgoing traffic|<p>Number of bits sent by the backend</p>|Dependent item|haproxy.server.bout.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bout`</p></li><li><p>Custom multiplier: `8`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Server is active|<p>Shows whether the server is active (marked with a Y) or a backup (marked with a -).</p>|Dependent item|haproxy.server.act[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.act`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|{#PXNAME} {#SVNAME}: Server is backup|<p>Shows whether the server is a backup (marked with a Y) or active (marked with a -).</p>|Dependent item|haproxy.server.bck[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.bck`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|{#PXNAME} {#SVNAME}: Sessions per second|<p>Cumulative number of sessions (end-to-end connections) per second.</p>|Dependent item|haproxy.server.stot.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.stot`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Weight|<p>Effective weight.</p>|Dependent item|haproxy.server.weight[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.weight`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|{#PXNAME} {#SVNAME}: Configured maxqueue|<p>Configured maxqueue for the server, or nothing in the value is 0 (default, meaning no limit).</p>|Dependent item|haproxy.server.qlimit[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.qlimit`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li><li><p>Matches regular expression: `^\d+$`</p><p>⛔️Custom on fail: Set value to: `0`</p></li></ul>|
|{#PXNAME} {#SVNAME}: Server was selected per second|<p>Number of times that server was selected.</p>|Dependent item|haproxy.server.lbtot.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lbtot`</p></li><li>Change per second</li></ul>|
|{#PXNAME} {#SVNAME}: Status of last health check|<p>Status of last health check, one of:</p><p>UNK     -> unknown</p><p>INI     -> initializing</p><p>SOCKERR -> socket error</p><p>L4OK    -> check passed on layer 4, no upper layers testing enabled</p><p>L4TOUT  -> layer 1-4 timeout</p><p>L4CON   -> layer 1-4 connection problem, for example "Connection refused" (tcp rst) or "No route to host" (icmp)</p><p>L6OK    -> check passed on layer 6</p><p>L6TOUT  -> layer 6 (SSL) timeout</p><p>L6RSP   -> layer 6 invalid response - protocol error</p><p>L7OK    -> check passed on layer 7</p><p>L7OKC   -> check conditionally passed on layer 7, for example 404 with disable-on-404</p><p>L7TOUT  -> layer 7 (HTTP/SMTP) timeout</p><p>L7RSP   -> layer 7 invalid response - protocol error</p><p>L7STS   -> layer 7 response error, for example HTTP 5xx</p><p>Notice: If a check is currently running, the last known status will be reported, prefixed with "* ". e. g. "* L7OK".</p>|Dependent item|haproxy.server.check_status[{#PXNAME},{#SVNAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.check_status`</p></li><li><p>Discard unchanged with heartbeat: `10m`</p></li></ul>|

### Trigger prototypes for Server discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|HAProxy: {#PXNAME} {#SVNAME}: Server is DOWN|<p>Server is not available.</p>|`count(/HAProxy by HTTP/haproxy.server.status[{#PXNAME},{#SVNAME}],#5,"eq","DOWN")=5`|Warning||
|HAProxy: {#PXNAME} {#SVNAME}: Average response time is high|<p>Average server response time (in ms) for the last 1,024 requests is more than {$HAPROXY.SERVER_RTIME.MAX.WARN}.</p>|`min(/HAProxy by HTTP/haproxy.server.rtime[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.SERVER_RTIME.MAX.WARN}`|Warning||
|HAProxy: {#PXNAME} {#SVNAME}: Number of responses with error is high|<p>Number of requests on server, whose responses yielded an error, is more than {$HAPROXY.SERVER_ERESP.MAX.WARN}.</p>|`min(/HAProxy by HTTP/haproxy.server.eresp.rate[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.SERVER_ERESP.MAX.WARN}`|Warning||
|HAProxy: {#PXNAME} {#SVNAME}: Current number of requests unassigned in queue is high|<p>Current number of requests unassigned in queue is more than {$HAPROXY.SERVER_QCUR.MAX.WARN}.</p>|`min(/HAProxy by HTTP/haproxy.server.qcur[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.SERVER_QCUR.MAX.WARN}`|Warning||
|HAProxy: {#PXNAME} {#SVNAME}: Average time spent in queue is high|<p>Average time spent in queue (in ms) for the last 1,024 requests is more than {$HAPROXY.SERVER_QTIME.MAX.WARN}.</p>|`min(/HAProxy by HTTP/haproxy.server.qtime[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.SERVER_QTIME.MAX.WARN}`|Warning||
|HAProxy: {#PXNAME} {#SVNAME}: Health check error|<p>Please check the server for faults.</p>|`find(/HAProxy by HTTP/haproxy.server.check_status[{#PXNAME},{#SVNAME}],#3,"regexp","(?:L[4-7]OK\|^$)")=0`|Warning|**Depends on**:<br><ul><li>HAProxy: {#PXNAME} {#SVNAME}: Server is DOWN</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

