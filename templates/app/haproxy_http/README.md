
# HAProxy by HTTP

## Overview

For Zabbix version: 6.0 and higher  
The template to monitor HAProxy by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

Template `HAProxy by HTTP` collects metrics by polling [HAProxy Stats Page](https://www.haproxy.com/blog/exploring-the-haproxy-stats-page/) with HTTP agent remotely.

Note that this solution supports https and redirects.

This template was tested on:

- HAProxy, version 1.8

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

Setup [HAProxy Stats Page](https://www.haproxy.com/blog/exploring-the-haproxy-stats-page/).

Example configuration of HAProxy:

```text
frontend stats
    bind *:8404
    stats enable
    stats uri /stats
    stats refresh 10s
    #stats auth Username:Password  # Authentication credentials
```

If you use another location, don't forget to change the macros {$HAPROXY.STATS.SCHEME},{HOST.CONN},
{$HAPROXY.STATS.PORT},{$HAPROXY.STATS.PATH}.

If you want to use authentication, set the username and password in the "stats auth" option of the configuration file and
in the macros {$HAPROXY.USERNAME},{$HAPROXY.PASSWORD}.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$HAPROXY.BACK_ERESP.MAX.WARN} |<p>Maximum of responses with error on Backend for trigger expression.</p> |`10` |
|{$HAPROXY.BACK_QCUR.MAX.WARN} |<p>Maximum number of requests on Backend unassigned in queue for trigger expression.</p> |`10` |
|{$HAPROXY.BACK_QTIME.MAX.WARN} |<p>Maximum of average time spent in queue on Backend for trigger expression.</p> |`10s` |
|{$HAPROXY.BACK_RTIME.MAX.WARN} |<p>Maximum of average Backend response time for trigger expression.</p> |`10s` |
|{$HAPROXY.FRONT_DREQ.MAX.WARN} |<p>The HAProxy maximum denied requests for trigger expression.</p> |`10` |
|{$HAPROXY.FRONT_EREQ.MAX.WARN} |<p>The HAProxy maximum number of request errors for trigger expression.</p> |`10` |
|{$HAPROXY.FRONT_SUTIL.MAX.WARN} |<p>Maximum of session usage percentage on frontend for trigger expression.</p> |`80` |
|{$HAPROXY.PASSWORD} |<p>The password of the HAProxy stats page.</p> |`` |
|{$HAPROXY.RESPONSE_TIME.MAX.WARN} |<p>The HAProxy stats page maximum response time in seconds for trigger expression.</p> |`10s` |
|{$HAPROXY.SERVER_ERESP.MAX.WARN} |<p>Maximum of responses with error on server for trigger expression.</p> |`10` |
|{$HAPROXY.SERVER_QCUR.MAX.WARN} |<p>Maximum number of requests on server unassigned in queue for trigger expression.</p> |`10` |
|{$HAPROXY.SERVER_QTIME.MAX.WARN} |<p>Maximum of average time spent in queue on server for trigger expression.</p> |`10s` |
|{$HAPROXY.SERVER_RTIME.MAX.WARN} |<p>Maximum of average server response time for trigger expression.</p> |`10s` |
|{$HAPROXY.STATS.PATH} |<p>The path of the HAProxy stats page.</p> |`stats` |
|{$HAPROXY.STATS.PORT} |<p>The port of the HAProxy stats host or container.</p> |`8404` |
|{$HAPROXY.STATS.SCHEME} |<p>The scheme of HAProxy stats page(http/https).</p> |`http` |
|{$HAPROXY.USERNAME} |<p>The username of the HAProxy stats page.</p> |`` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Backend discovery |<p>Discovery backends</p> |DEPENDENT |haproxy.backend.discovery<p>**Filter**:</p>AND <p>- {#SVNAME} MATCHES_REGEX `BACKEND`</p><p>- {#MODE} MATCHES_REGEX `http|tcp`</p><p>**Overrides:**</p><p>Discard HTTP status codes<br> - {#MODE} MATCHES_REGEX `tcp`<br>  - ITEM_PROTOTYPE LIKE `Number of responses with codes` - NO_DISCOVER</p> |
|Frontend discovery |<p>Discovery frontends</p> |DEPENDENT |haproxy.frontend.discovery<p>**Filter**:</p>AND <p>- {#SVNAME} MATCHES_REGEX `FRONTEND`</p><p>- {#MODE} MATCHES_REGEX `http|tcp`</p><p>**Overrides:**</p><p>Discard HTTP status codes<br> - {#MODE} MATCHES_REGEX `tcp`<br>  - ITEM_PROTOTYPE LIKE `Number of responses with codes` - NO_DISCOVER</p> |
|Server discovery |<p>Discovery servers</p> |DEPENDENT |haproxy.server.discovery<p>**Filter**:</p>AND <p>- {#SVNAME} NOT_MATCHES_REGEX `FRONTEND|BACKEND`</p><p>- {#MODE} MATCHES_REGEX `http|tcp`</p><p>**Overrides:**</p><p>Discard HTTP status codes<br> - {#MODE} MATCHES_REGEX `tcp`<br>  - ITEM_PROTOTYPE LIKE `Number of responses with codes` - NO_DISCOVER</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|HAProxy |HAProxy: Version |<p>-</p> |DEPENDENT |haproxy.version<p>**Preprocessing**:</p><p>- REGEX: `HAProxy version ([^,]*), \1`</p><p>⛔️ON_FAIL: `CUSTOM_ERROR -> HAProxy version is not found`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|HAProxy |HAProxy: Uptime |<p>-</p> |DEPENDENT |haproxy.uptime<p>**Preprocessing**:</p><p>- JAVASCRIPT: `The text is too long. Please see the template.`</p> |
|HAProxy |HAProxy: Service status |<p>-</p> |SIMPLE |net.tcp.service["{$HAPROXY.STATS.SCHEME}","{HOST.CONN}","{$HAPROXY.STATS.PORT}"]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|HAProxy |HAProxy: Service response time |<p>-</p> |SIMPLE |net.tcp.service.perf["{$HAPROXY.STATS.SCHEME}","{HOST.CONN}","{$HAPROXY.STATS.PORT}"] |
|HAProxy |HAProxy Backend {#PXNAME}: Status |<p>Possible values:</p><p>UP - The server is reporting as healthy.</p><p>DOWN - The server is reporting as unhealthy and unable to receive requests.</p><p>NOLB - You've added http-check disable-on-404 to the backend and the health checked URL has returned an HTTP 404 response.</p><p>MAINT - The server has been disabled or put into maintenance mode.</p><p>DRAIN - The server has been put into drain mode.</p><p>no check - Health checks are not enabled for this server.</p> |DEPENDENT |haproxy.backend.status[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].status.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Responses time |<p>Average backend response time (in ms) for the last 1,024 requests</p> |DEPENDENT |haproxy.backend.rtime[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].rtime.first()`</p><p>- MULTIPLIER: `0.001`</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Errors connection per second |<p>Number of requests that encountered an error attempting to connect to a backend server.</p> |DEPENDENT |haproxy.backend.econ.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].econ.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Responses denied per second |<p>Responses denied due to security concerns (ACL-restricted).</p> |DEPENDENT |haproxy.backend.dresp.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].dresp.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Response errors per second |<p>Number of requests whose responses yielded an error</p> |DEPENDENT |haproxy.backend.eresp.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].eresp.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Unassigned requests |<p>Current number of requests unassigned in queue.</p> |DEPENDENT |haproxy.backend.qcur[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].qcur.first()`</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Time in queue |<p>Average time spent in queue (in ms) for the last 1,024 requests</p> |DEPENDENT |haproxy.backend.qtime[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].qtime.first()`</p><p>- MULTIPLIER: `0.001`</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Redispatched requests per second |<p>Number of times a request was redispatched to a different backend.</p> |DEPENDENT |haproxy.backend.wredis.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].wredis.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Retried connections per second |<p>Number of times a connection was retried.</p> |DEPENDENT |haproxy.backend.wretr.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].wretr.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Number of responses with codes 1xx per second |<p>Number of informational HTTP responses per second.</p> |DEPENDENT |haproxy.backend.hrsp_1xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_1xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Number of responses with codes 2xx per second |<p>Number of successful HTTP responses per second.</p> |DEPENDENT |haproxy.backend.hrsp_2xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_2xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Number of responses with codes 3xx per second |<p>Number of HTTP redirections per second.</p> |DEPENDENT |haproxy.backend.hrsp_3xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_3xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Number of responses with codes 4xx per second |<p>Number of HTTP client errors per second.</p> |DEPENDENT |haproxy.backend.hrsp_4xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_4xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Number of responses with codes 5xx per second |<p>Number of HTTP server errors per second.</p> |DEPENDENT |haproxy.backend.hrsp_5xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_5xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Incoming traffic |<p>Number of bits received by the backend</p> |DEPENDENT |haproxy.backend.bin.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].bin.first()`</p><p>- MULTIPLIER: `8`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Outgoing traffic |<p>Number of bits sent by the backend</p> |DEPENDENT |haproxy.backend.bout.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].bout.first()`</p><p>- MULTIPLIER: `8`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Number of active servers |<p>Number of active servers.</p> |DEPENDENT |haproxy.backend.act[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].act.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Number of backup servers |<p>Number of backup servers.</p> |DEPENDENT |haproxy.backend.bck[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].bck.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Sessions per second |<p>Cumulative number of sessions (end-to-end connections) per second.</p> |DEPENDENT |haproxy.backend.stot.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].stot.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Backend {#PXNAME}: Weight |<p>Total effective weight.</p> |DEPENDENT |haproxy.backend.weight[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].weight.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Status |<p>Possible values: OPEN, STOP.</p><p>When Status is OPEN, the frontend is operating normally and ready to receive traffic.</p> |DEPENDENT |haproxy.frontend.status[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].status.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Requests rate |<p>HTTP requests per second</p> |DEPENDENT |haproxy.frontend.req_rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].req_rate.first()`</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Sessions rate |<p>Number of sessions created per second</p> |DEPENDENT |haproxy.frontend.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].rate.first()`</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Established sessions |<p>The current number of established sessions.</p> |DEPENDENT |haproxy.frontend.scur[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].scur.first()`</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Session limits |<p>The most simultaneous sessions that are allowed, as defined by the maxconn setting in the frontend.</p> |DEPENDENT |haproxy.frontend.slim[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].slim.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Session utilization |<p>Percentage of sessions used (scur / slim * 100).</p> |CALCULATED |haproxy.frontend.sutil[{#PXNAME},{#SVNAME}]<p>**Expression**:</p>`last(//haproxy.frontend.scur[{#PXNAME},{#SVNAME}]) / last(//haproxy.frontend.slim[{#PXNAME},{#SVNAME}]) * 100` |
|HAProxy |HAProxy Frontend {#PXNAME}: Request errors per second |<p>Number of request errors per second.</p> |DEPENDENT |haproxy.frontend.ereq.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].ereq.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Denied requests per second |<p>Requests denied due to security concerns (ACL-restricted) per second.</p> |DEPENDENT |haproxy.frontend.dreq.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].dreq.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Number of responses with codes 1xx per second |<p>Number of informational HTTP responses per second.</p> |DEPENDENT |haproxy.frontend.hrsp_1xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_1xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Number of responses with codes 2xx per second |<p>Number of successful HTTP responses per second.</p> |DEPENDENT |haproxy.frontend.hrsp_2xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_2xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Number of responses with codes 3xx per second |<p>Number of HTTP redirections per second.</p> |DEPENDENT |haproxy.frontend.hrsp_3xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_3xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Number of responses with codes 4xx per second |<p>Number of HTTP client errors per second.</p> |DEPENDENT |haproxy.frontend.hrsp_4xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_4xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Number of responses with codes 5xx per second |<p>Number of HTTP server errors per second.</p> |DEPENDENT |haproxy.frontend.hrsp_5xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_5xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Incoming traffic |<p>Number of bits received by the frontend</p> |DEPENDENT |haproxy.frontend.bin.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].bin.first()`</p><p>- MULTIPLIER: `8`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy Frontend {#PXNAME}: Outgoing traffic |<p>Number of bits sent by the frontend</p> |DEPENDENT |haproxy.frontend.bout.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].bout.first()`</p><p>- MULTIPLIER: `8`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Status | |DEPENDENT |haproxy.server.status[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].status.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Responses time |<p>Average server response time (in ms) for the last 1,024 requests.</p> |DEPENDENT |haproxy.server.rtime[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].rtime.first()`</p><p>- MULTIPLIER: `0.001`</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Errors connection per second |<p>Number of requests that encountered an error attempting to connect to a backend server.</p> |DEPENDENT |haproxy.server.econ.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].econ.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Responses denied per second |<p>Responses denied due to security concerns (ACL-restricted).</p> |DEPENDENT |haproxy.server.dresp.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].dresp.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Response errors per second |<p>Number of requests whose responses yielded an error.</p> |DEPENDENT |haproxy.server.eresp.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].eresp.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Unassigned requests |<p>Current number of requests unassigned in queue.</p> |DEPENDENT |haproxy.server.qcur[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].qcur.first()`</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Time in queue |<p>Average time spent in queue (in ms) for the last 1,024 requests.</p> |DEPENDENT |haproxy.server.qtime[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].qtime.first()`</p><p>- MULTIPLIER: `0.001`</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Redispatched requests per second |<p>Number of times a request was redispatched to a different backend.</p> |DEPENDENT |haproxy.server.wredis.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].wredis.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Retried connections per second |<p>Number of times a connection was retried.</p> |DEPENDENT |haproxy.server.wretr.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].wretr.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Number of responses with codes 1xx per second |<p>Number of informational HTTP responses per second.</p> |DEPENDENT |haproxy.server.hrsp_1xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_1xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Number of responses with codes 2xx per second |<p>Number of successful HTTP responses per second.</p> |DEPENDENT |haproxy.server.hrsp_2xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_2xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Number of responses with codes 3xx per second |<p>Number of HTTP redirections per second.</p> |DEPENDENT |haproxy.server.hrsp_3xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_3xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Number of responses with codes 4xx per second |<p>Number of HTTP client errors per second.</p> |DEPENDENT |haproxy.server.hrsp_4xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_4xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Number of responses with codes 5xx per second |<p>Number of HTTP server errors per second.</p> |DEPENDENT |haproxy.server.hrsp_5xx.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].hrsp_5xx.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Incoming traffic |<p>Number of bits received by the backend</p> |DEPENDENT |haproxy.server.bin.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].bin.first()`</p><p>- MULTIPLIER: `8`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Outgoing traffic |<p>Number of bits sent by the backend</p> |DEPENDENT |haproxy.server.bout.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].bout.first()`</p><p>- MULTIPLIER: `8`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Server is active |<p>Shows whether the server is active (marked with a Y) or a backup (marked with a -).</p> |DEPENDENT |haproxy.server.act[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].act.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Server is backup |<p>Shows whether the server is a backup (marked with a Y) or active (marked with a -).</p> |DEPENDENT |haproxy.server.bck[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].bck.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Sessions per second |<p>Cumulative number of sessions (end-to-end connections) per second.</p> |DEPENDENT |haproxy.server.stot.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].stot.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Weight |<p>Effective weight.</p> |DEPENDENT |haproxy.server.weight[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].weight.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Configured maxqueue |<p>Configured maxqueue for the server, or nothing in the value is 0 (default, meaning no limit).</p> |DEPENDENT |haproxy.server.qlimit[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].qlimit.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>- MATCHES_REGEX: `^\d+$`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Server was selected per second |<p>Number of times that server was selected.</p> |DEPENDENT |haproxy.server.lbtot.rate[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].lbtot.first()`</p><p>- CHANGE_PER_SECOND</p> |
|HAProxy |HAProxy {#PXNAME} {#SVNAME}: Status of last health check |<p>Status of last health check, one of:</p><p>UNK     -> unknown</p><p>INI     -> initializing</p><p>SOCKERR -> socket error</p><p>L4OK    -> check passed on layer 4, no upper layers testing enabled</p><p>L4TOUT  -> layer 1-4 timeout</p><p>L4CON   -> layer 1-4 connection problem, for example "Connection refused" (tcp rst) or "No route to host" (icmp)</p><p>L6OK    -> check passed on layer 6</p><p>L6TOUT  -> layer 6 (SSL) timeout</p><p>L6RSP   -> layer 6 invalid response - protocol error</p><p>L7OK    -> check passed on layer 7</p><p>L7OKC   -> check conditionally passed on layer 7, for example 404 with disable-on-404</p><p>L7TOUT  -> layer 7 (HTTP/SMTP) timeout</p><p>L7RSP   -> layer 7 invalid response - protocol error</p><p>L7STS   -> layer 7 response error, for example HTTP 5xx</p><p>Notice: If a check is currently running, the last known status will be reported, prefixed with "* ". e. g. "* L7OK".</p> |DEPENDENT |haproxy.server.check_status[{#PXNAME},{#SVNAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.[?(@.pxname == '{#PXNAME}' && @.svname == '{#SVNAME}')].check_status.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `10m`</p> |
|Zabbix raw items |HAProxy: Get stats |<p>HAProxy Statistics Report in CSV format</p> |HTTP_AGENT |haproxy.get<p>**Preprocessing**:</p><p>- REGEX: `# ([\s\S]*)\n \1`</p><p>- CSV_TO_JSON: ` 1`</p> |
|Zabbix raw items |HAProxy: Get nodes |<p>Array for LLD rules.</p> |DEPENDENT |haproxy.get.nodes<p>**Preprocessing**:</p><p>- JAVASCRIPT: `return JSON.stringify(JSON.parse(value),['mode','pxname','svname'])`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Zabbix raw items |HAProxy: Get stats page |<p>HAProxy Statistics Report HTML</p> |HTTP_AGENT |haproxy.get_html |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|HAProxy: Version has changed |<p>HAProxy version has changed. Ack to close.</p> |`last(/HAProxy by HTTP/haproxy.version,#1)<>last(/HAProxy by HTTP/haproxy.version,#2) and length(last(/HAProxy by HTTP/haproxy.version))>0` |INFO |<p>Manual close: YES</p> |
|HAProxy: has been restarted |<p>Uptime is less than 10 minutes</p> |`last(/HAProxy by HTTP/haproxy.uptime)<10m` |INFO |<p>Manual close: YES</p> |
|HAProxy: Service is down |<p>-</p> |`last(/HAProxy by HTTP/net.tcp.service["{$HAPROXY.STATS.SCHEME}","{HOST.CONN}","{$HAPROXY.STATS.PORT}"])=0` |AVERAGE |<p>Manual close: YES</p> |
|HAProxy: Service response time is too high |<p>-</p> |`min(/HAProxy by HTTP/net.tcp.service.perf["{$HAPROXY.STATS.SCHEME}","{HOST.CONN}","{$HAPROXY.STATS.PORT}"],5m)>{$HAPROXY.RESPONSE_TIME.MAX.WARN}` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- HAProxy: Service is down</p> |
|HAProxy backend {#PXNAME}: Server is DOWN |<p>Backend is not available.</p> |`count(/HAProxy by HTTP/haproxy.backend.status[{#PXNAME},{#SVNAME}],#5,"eq","DOWN")=5` |AVERAGE | |
|HAProxy backend {#PXNAME}: Average response time is high |<p>Average backend response time (in ms) for the last 1,024 requests is more than {$HAPROXY.BACK_RTIME.MAX.WARN}.</p> |`min(/HAProxy by HTTP/haproxy.backend.rtime[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.BACK_RTIME.MAX.WARN}` |WARNING | |
|HAProxy backend {#PXNAME}: Number of responses with error is high |<p>Number of requests on backend, whose responses yielded an error, is more than {$HAPROXY.BACK_ERESP.MAX.WARN}.</p> |`min(/HAProxy by HTTP/haproxy.backend.eresp.rate[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.BACK_ERESP.MAX.WARN}` |WARNING | |
|HAProxy backend {#PXNAME}: Current number of requests unassigned in queue is high |<p>Current number of requests on backend unassigned in queue is more than {$HAPROXY.BACK_QCUR.MAX.WARN}.</p> |`min(/HAProxy by HTTP/haproxy.backend.qcur[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.BACK_QCUR.MAX.WARN}` |WARNING | |
|HAProxy backend {#PXNAME}: Average time spent in queue is high |<p>Average time spent in queue (in ms) for the last 1,024 requests is more than {$HAPROXY.BACK_QTIME.MAX.WARN}.</p> |`min(/HAProxy by HTTP/haproxy.backend.qtime[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.BACK_QTIME.MAX.WARN}` |WARNING | |
|HAProxy frontend {#PXNAME}: Session utilization is high |<p>Alerting on this metric is essential to ensure your server has sufficient capacity to handle all concurrent sessions. Unlike requests, upon reaching the session limit HAProxy will deny additional clients until resource consumption drops. Furthermore, if you find your session usage percentage to be hovering above 80%, it could be time to either modify HAProxy's configuration to allow more sessions, or migrate your HAProxy server to a bigger box.</p> |`min(/HAProxy by HTTP/haproxy.frontend.sutil[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.FRONT_SUTIL.MAX.WARN}` |WARNING | |
|HAProxy frontend {#PXNAME}: Number of request errors is high |<p>Number of request errors is more than {$HAPROXY.FRONT_EREQ.MAX.WARN}.</p> |`min(/HAProxy by HTTP/haproxy.frontend.ereq.rate[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.FRONT_EREQ.MAX.WARN}` |WARNING | |
|HAProxy frontend {#PXNAME}: Number of requests denied is high |<p>Number of requests denied due to security concerns (ACL-restricted) is more than {$HAPROXY.FRONT_DREQ.MAX.WARN}.</p> |`min(/HAProxy by HTTP/haproxy.frontend.dreq.rate[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.FRONT_DREQ.MAX.WARN}` |WARNING | |
|HAProxy {#PXNAME} {#SVNAME}: Server is DOWN |<p>Server is not available.</p> |`count(/HAProxy by HTTP/haproxy.server.status[{#PXNAME},{#SVNAME}],#5,"eq","DOWN")=5` |WARNING | |
|HAProxy {#PXNAME} {#SVNAME}: Average response time is high |<p>Average server response time (in ms) for the last 1,024 requests is more than {$HAPROXY.SERVER_RTIME.MAX.WARN}.</p> |`min(/HAProxy by HTTP/haproxy.server.rtime[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.SERVER_RTIME.MAX.WARN}` |WARNING | |
|HAProxy {#PXNAME} {#SVNAME}: Number of responses with error is high |<p>Number of requests on server, whose responses yielded an error, is more than {$HAPROXY.SERVER_ERESP.MAX.WARN}.</p> |`min(/HAProxy by HTTP/haproxy.server.eresp.rate[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.SERVER_ERESP.MAX.WARN}` |WARNING | |
|HAProxy {#PXNAME} {#SVNAME}: Current number of requests unassigned in queue is high |<p>Current number of requests unassigned in queue is more than {$HAPROXY.SERVER_QCUR.MAX.WARN}.</p> |`min(/HAProxy by HTTP/haproxy.server.qcur[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.SERVER_QCUR.MAX.WARN}` |WARNING | |
|HAProxy {#PXNAME} {#SVNAME}: Average time spent in queue is high |<p>Average time spent in queue (in ms) for the last 1,024 requests is more than {$HAPROXY.SERVER_QTIME.MAX.WARN}.</p> |`min(/HAProxy by HTTP/haproxy.server.qtime[{#PXNAME},{#SVNAME}],5m)>{$HAPROXY.SERVER_QTIME.MAX.WARN}` |WARNING | |
|HAProxy {#PXNAME} {#SVNAME}: Health check error |<p>Please check the server for faults.</p> |`find(/HAProxy by HTTP/haproxy.server.check_status[{#PXNAME},{#SVNAME}],#3,"regexp","(?:L[4-7]OK|^$)")=0` |WARNING |<p>**Depends on**:</p><p>- HAProxy {#PXNAME} {#SVNAME}: Server is DOWN</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/393527-discussion-thread-for-official-zabbix-template-haproxy).

