
# Unbound by HTTP

## Overview

Template Unbound by HTTP monitors an Unbound DNS resolver via the Prometheus endpoint exposed by unbound_exporter.

## Requirements

Zabbix version: 8.2 and higher.

## Tested versions

This template has been tested on:
- Unbound 1.24.1, unbound_exporter v0.6.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.2/manual/config/templates_out_of_the_box) section.

## Setup

Enable the Unbound control interface by adding the following configuration to `unbound.conf`:

```text
remote-control:
  control-enable: yes
  control-interface: /run/unbound.ctl
```
Install and run [unbound_exporter](https://github.com/letsencrypt/unbound_exporter) according to the upstream instructions. For a local Unix socket, run:

`unbound_exporter -unbound.ca "" -unbound.cert "" -unbound.host "unix:///run/unbound.ctl"`

Configure `{$UNBOUND.URL}` with the exporter base URL. The exporter must expose `/metrics` in Prometheus format.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$UNBOUND.URL}|<p>URL of the unbound_exporter instance without a trailing slash, e.g., `https://localhost:9167`.</p>||
|{$UNBOUND.TIMEOUT}|<p>HTTP request timeout for exporter polling, in seconds.</p>|`15`|
|{$UNBOUND.NODATA_TIMEOUT}|<p>The time threshold after which Unbound metrics are considered unavailable. Used in trigger expressions.</p>|`10m`|
|{$UNBOUND.CACHE.HIT_RATIO.MIN.WARN}|<p>The minimum cache hit ratio percentage for the warning trigger expression.</p>|`50`|
|{$UNBOUND.RECURSION.TIME.MAX.WARN}|<p>The maximum average recursion time in seconds for the warning trigger expression.</p>|`1`|
|{$UNBOUND.LLD.FILTER.CACHE.MATCHES}|<p>Filter to include discovered Unbound caches by name.</p>|`.*`|
|{$UNBOUND.LLD.FILTER.CACHE.NOT_MATCHES}|<p>Filter to exclude discovered Unbound caches by name.</p>|`CHANGE_IF_NEEDED`|
|{$UNBOUND.LLD.FILTER.MODULE.MATCHES}|<p>Filter to include discovered Unbound modules by name.</p>|`.*`|
|{$UNBOUND.LLD.FILTER.MODULE.NOT_MATCHES}|<p>Filter to exclude discovered Unbound modules by name.</p>|`CHANGE_IF_NEEDED`|
|{$UNBOUND.HTTP.PROXY}|<p>HTTP proxy used for requests to unbound_exporter.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get exporter metrics|<p>Get raw Unbound metrics from the exporter.</p>|HTTP agent|unbound.metrics.get<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Service status|<p>Indicates whether unbound_exporter successfully collected metrics from the Unbound resolver.</p>|Dependent item|unbound.service.status<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(unbound_up)`</p></li></ul>|
|Cache: Hit ratio|<p>Percentage of cache hits calculated from cache hit and miss rates for the latest polling interval.</p>|Calculated|unbound.cache.hit.ratio|
|Cache: Hits, rate|<p>Number of cache hits per second across all Unbound threads.</p>|Dependent item|unbound.cache.hits.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(unbound_cache_hits_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Cache: Hits, total|<p>Total number of cache hits across all Unbound threads.</p>|Dependent item|unbound.cache.hits.total<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(unbound_cache_hits_total)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Cache: Miss ratio|<p>Percentage of cache misses calculated from cache hit and miss rates for the latest polling interval.</p>|Calculated|unbound.cache.miss.ratio|
|Cache: Misses, rate|<p>Number of cache misses per second across all Unbound threads.</p>|Dependent item|unbound.cache.misses.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(unbound_cache_misses_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Cache: Misses, total|<p>Total number of cache misses across all Unbound threads.</p>|Dependent item|unbound.cache.misses.total<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(unbound_cache_misses_total)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Queries, rate|<p>Number of DNS queries received per second across all Unbound threads.</p>|Dependent item|unbound.queries.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(unbound_queries_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Queries, total|<p>Total number of DNS queries received across all Unbound threads.</p>|Dependent item|unbound.queries.total<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(unbound_queries_total)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Request list: Exceeded, rate|<p>Number of queries per second across all Unbound threads that were dropped because the request list was full.</p>|Dependent item|unbound.request.list.exceeded.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(unbound_request_list_exceeded_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Request list: Exceeded, total|<p>Total number of queries across all Unbound threads that were dropped because the request list was full.</p>|Dependent item|unbound.request.list.exceeded.total<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(unbound_request_list_exceeded_total)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Request list: Overwritten, rate|<p>Number of requests per second across all Unbound threads that were overwritten by newer request list entries.</p>|Dependent item|unbound.request.list.overwritten.rate<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(unbound_request_list_overwritten_total)`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|Request list: Overwritten, total|<p>Total number of requests across all Unbound threads that were overwritten by newer request list entries.</p>|Dependent item|unbound.request.list.overwritten.total<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `SUM(unbound_request_list_overwritten_total)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Recursion time, average|<p>Average time required to answer queries that needed recursive processing, excluding cached queries.</p>|Dependent item|unbound.recursion.time.avg<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(unbound_recursion_time_seconds_avg)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Recursion time, median|<p>Median time required to answer queries that needed recursive processing.</p>|Dependent item|unbound.recursion.time.median<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(unbound_recursion_time_seconds_median)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Uptime|<p>Unbound resolver uptime.</p>|Dependent item|unbound.uptime<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(unbound_time_up_seconds_total)`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Unbound: Failed to collect metrics|<p>The unbound_exporter cannot collect metrics from the Unbound resolver.</p>|`max(/Unbound by HTTP/unbound.service.status,#3)=0 and nodata(/Unbound by HTTP/unbound.service.status,{$UNBOUND.NODATA_TIMEOUT})=0`|High||
|Unbound: Failed to fetch metrics data|<p>Zabbix has not received Unbound metrics for `{$UNBOUND.NODATA_TIMEOUT}`.</p>|`nodata(/Unbound by HTTP/unbound.service.status,{$UNBOUND.NODATA_TIMEOUT})=1`|Warning||
|Unbound: Cache hit ratio is low|<p>The cache hit ratio has remained below `{$UNBOUND.CACHE.HIT_RATIO.MIN.WARN}`% while DNS queries were continuously received.</p>|`max(/Unbound by HTTP/unbound.cache.hit.ratio,#5)<{$UNBOUND.CACHE.HIT_RATIO.MIN.WARN} and min(/Unbound by HTTP/unbound.queries.rate,#5)>0`|Warning|**Depends on**:<br><ul><li>Unbound: Failed to collect metrics</li><li>Unbound: Failed to fetch metrics data</li></ul>|
|Unbound: Dropped requests detected|<p>Queries were dropped in the last 5 minutes because the Unbound request list was full.</p>|`max(/Unbound by HTTP/unbound.request.list.exceeded.rate,5m)>0`|Warning|**Depends on**:<br><ul><li>Unbound: Failed to collect metrics</li><li>Unbound: Failed to fetch metrics data</li></ul>|
|Unbound: Overwritten requests detected|<p>Requests were overwritten by newer Unbound request list entries in the last 5 minutes.</p>|`max(/Unbound by HTTP/unbound.request.list.overwritten.rate,5m)>0`|Warning|**Depends on**:<br><ul><li>Unbound: Failed to collect metrics</li><li>Unbound: Failed to fetch metrics data</li></ul>|
|Unbound: Average recursion time is high|<p>The average recursive request processing time has remained above `{$UNBOUND.RECURSION.TIME.MAX.WARN}` seconds for 5 minutes.</p>|`min(/Unbound by HTTP/unbound.recursion.time.avg,5m)>{$UNBOUND.RECURSION.TIME.MAX.WARN}`|Warning|**Depends on**:<br><ul><li>Unbound: Failed to collect metrics</li><li>Unbound: Failed to fetch metrics data</li></ul>|
|Unbound: Resolver has been restarted|<p>The Unbound resolver has been restarted recently.</p>|`last(/Unbound by HTTP/unbound.uptime)<10m`|Info|**Manual close**: Yes|

### LLD rule Cache memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cache memory discovery|<p>Discovers Unbound cache memory metrics.</p>|Dependent item|unbound.memory.cache.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `unbound_memory_caches_bytes`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Cache memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Cache [{#CACHE}]: Memory usage|<p>Amount of memory used by the Unbound cache `{#CACHE}`.</p>|Dependent item|unbound.memory.cache.bytes["{#CACHE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(unbound_memory_caches_bytes{cache="{#CACHE}"})`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### LLD rule Module memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Module memory discovery|<p>Discovers Unbound module memory metrics.</p>|Dependent item|unbound.memory.module.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `unbound_memory_modules_bytes`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Module memory discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Module [{#MODULE}]: Memory usage|<p>Amount of memory used by the Unbound module `{#MODULE}`.</p>|Dependent item|unbound.memory.module.bytes["{#MODULE}"]<p>**Preprocessing**</p><ul><li><p>Prometheus pattern: `VALUE(unbound_memory_modules_bytes{module="{#MODULE}"})`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

