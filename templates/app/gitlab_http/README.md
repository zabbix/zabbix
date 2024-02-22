
# GitLab by HTTP

## Overview

This template is designed to monitor GitLab by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

The template `GitLab by HTTP` — collects metrics by an HTTP agent from the GitLab `/-/metrics` endpoint.
See https://docs.gitlab.com/ee/administration/monitoring/prometheus/gitlab_metrics.html.


## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- GitLab 13.5.3 EE 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

This template works with self-hosted GitLab instances. Internal service metrics are collected from the GitLab `/-/metrics` endpoint.
To access metrics following two methods are available:
1. Explicitly allow monitoring instance IP address in gitlab [whitelist configuration](https://docs.gitlab.com/ee/administration/monitoring/ip_whitelist.html).
2. Get token from Gitlab `Admin -> Monitoring -> Health check` page: http://your.gitlab.address/admin/health_check; Use this token in macro `{$GITLAB.HEALTH.TOKEN}` as variable path, like: `?token=your_token`.
Remember to change the macros `{$GITLAB.URL}`.
Also, see the Macros section for a list of [macros used](#Macros-used) to set trigger values.

*NOTE.* Some metrics may not be collected depending on your Gitlab instance version and configuration. See [Gitlab's documentation](https://docs.gitlab.com/ee/administration/monitoring/prometheus/gitlab_metrics.html) for further information about its metric collection.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$GITLAB.URL}|<p>URL of a GitLab instance.</p>|`http://localhost`|
|{$GITLAB.HEALTH.TOKEN}|<p>The token path for Gitlab health check. Example `?token=your_token`</p>||
|{$GITLAB.UNICORN.UTILIZATION.MAX.WARN}|<p>The maximum percentage of Unicorn workers utilization for a trigger expression.</p>|`90`|
|{$GITLAB.PUMA.UTILIZATION.MAX.WARN}|<p>The maximum percentage of Puma thread utilization for a trigger expression.</p>|`90`|
|{$GITLAB.HTTP.FAIL.MAX.WARN}|<p>The maximum number of HTTP request failures for a trigger expression.</p>|`2`|
|{$GITLAB.REDIS.FAIL.MAX.WARN}|<p>The maximum number of Redis client exceptions for a trigger expression.</p>|`2`|
|{$GITLAB.UNICORN.QUEUE.MAX.WARN}|<p>The maximum number of Unicorn queued requests for a trigger expression.</p>|`1`|
|{$GITLAB.PUMA.QUEUE.MAX.WARN}|<p>The maximum number of Puma queued requests for a trigger expression.</p>|`1`|
|{$GITLAB.OPEN.FDS.MAX.WARN}|<p>The maximum percentage of used file descriptors for a trigger expression.</p>|`90`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|GitLab: Get instance metrics||HTTP agent|gitlab.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li><li>Prometheus to JSON</li></ul>|
|GitLab: Instance readiness check|<p>The readiness probe checks whether the GitLab instance is ready to accept traffic via Rails Controllers.</p>|HTTP agent|gitlab.readiness<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"master_check":[{"status":"failed"}]}`</p></li><li><p>JSON Path: `$.master_check[0].status`</p></li><li><p>Boolean to decimal</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|GitLab: Application server status|<p>Checks whether the application server is running. This probe is used to know if Rails Controllers are not deadlocked due to a multi-threading.</p>|HTTP agent|gitlab.liveness<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"status": "failed"}`</p></li><li><p>JSON Path: `$.status`</p></li><li><p>Boolean to decimal</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|GitLab: Version|<p>Version of the GitLab instance.</p>|Dependent item|gitlab.deployments.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="deployments")].labels.version.first()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|GitLab: Ruby: First process start time|<p>Minimum UNIX timestamp of ruby processes start time.</p>|Dependent item|gitlab.ruby.process_start_time_seconds.first<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="ruby_process_start_time_seconds")].value.min()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|GitLab: Ruby: Last process start time|<p>Maximum UNIX timestamp ruby processes start time.</p>|Dependent item|gitlab.ruby.process_start_time_seconds.last<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="ruby_process_start_time_seconds")].value.max()`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|GitLab: User logins, total|<p>Counter of how many users have logged in since GitLab was started or restarted.</p>|Dependent item|gitlab.user_session_logins_total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="user_session_logins_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|GitLab: User CAPTCHA logins failed, total|<p>Counter of failed CAPTCHA attempts during login.</p>|Dependent item|gitlab.failed_login_captcha_total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="failed_login_captcha_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|GitLab: User CAPTCHA logins, total|<p>Counter of successful CAPTCHA attempts during login.</p>|Dependent item|gitlab.successful_login_captcha_total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="successful_login_captcha_total")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|GitLab: Upload file does not exist|<p>Number of times an upload record could not find its file.</p>|Dependent item|gitlab.upload_file_does_not_exist<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="upload_file_does_not_exist")].value.first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|GitLab: Pipelines: Processing events, total|<p>Total amount of pipeline processing events.</p>|Dependent item|gitlab.pipeline.processing_events_total<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|GitLab: Pipelines: Created, total|<p>Counter of pipelines created.</p>|Dependent item|gitlab.pipeline.created_total<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="pipelines_created_total")].value.sum()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|GitLab: Pipelines: Auto DevOps pipelines, total|<p>Counter of completed Auto DevOps pipelines.</p>|Dependent item|gitlab.pipeline.auto_devops_completed.total<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|GitLab: Pipelines: Auto DevOps pipelines, failed|<p>Counter of completed Auto DevOps pipelines with status "failed".</p>|Dependent item|gitlab.pipeline.auto_devops_completed_total.failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|GitLab: Pipelines: CI/CD creation duration|<p>The sum of the time in seconds it takes to create a CI/CD pipeline.</p>|Dependent item|gitlab.pipeline.pipeline_creation<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|GitLab: Pipelines: Pipelines: CI/CD creation count|<p>The count of the time it takes to create a CI/CD pipeline.</p>|Dependent item|gitlab.pipeline.pipeline_creation.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|GitLab: Database: Connection pool, busy|<p>Connections to the main database in use where the owner is still alive.</p>|Dependent item|gitlab.database.connection_pool_busy<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|GitLab: Database: Connection pool, current|<p>Current connections to the main database in the pool.</p>|Dependent item|gitlab.database.connection_pool_connections<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|GitLab: Database: Connection pool, dead|<p>Connections to the main database in use where the owner is not alive.</p>|Dependent item|gitlab.database.connection_pool_dead<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|GitLab: Database: Connection pool, idle|<p>Connections to the main database not in use.</p>|Dependent item|gitlab.database.connection_pool_idle<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|GitLab: Database: Connection pool, size|<p>Total connection to the main database pool capacity.</p>|Dependent item|gitlab.database.connection_pool_size<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|GitLab: Database: Connection pool, waiting|<p>Threads currently waiting on this queue.</p>|Dependent item|gitlab.database.connection_pool_waiting<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|GitLab: Redis: Client requests rate, queues|<p>Number of Redis client requests per second. (Instance: queues)</p>|Dependent item|gitlab.redis.client_requests.queues.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|GitLab: Redis: Client requests rate, cache|<p>Number of Redis client requests per second. (Instance: cache)</p>|Dependent item|gitlab.redis.client_requests.cache.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|GitLab: Redis: Client requests rate, shared_state|<p>Number of Redis client requests per second. (Instance: shared_state)</p>|Dependent item|gitlab.redis.client_requests.shared_state.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|GitLab: Redis: Client exceptions rate, queues|<p>Number of Redis client exceptions per second. (Instance: queues)</p>|Dependent item|gitlab.redis.client_exceptions.queues.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|GitLab: Redis: Client exceptions rate, cache|<p>Number of Redis client exceptions per second. (Instance: cache)</p>|Dependent item|gitlab.redis.client_exceptions.cache.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|GitLab: Redis: client exceptions rate, shared_state|<p>Number of Redis client exceptions per second. (Instance: shared_state)</p>|Dependent item|gitlab.redis.client_exceptions.shared_state.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|GitLab: Cache: Misses rate, total|<p>The cache read miss count.</p>|Dependent item|gitlab.cache.misses_total.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="gitlab_cache_misses_total")].value.sum()`</p></li><li>Change per second</li></ul>|
|GitLab: Cache: Operations rate, total|<p>The count of cache operations.</p>|Dependent item|gitlab.cache.operations_total.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="gitlab_cache_operations_total")].value.sum()`</p></li><li>Change per second</li></ul>|
|GitLab: Ruby: CPU  usage per second|<p>Average CPU time util in seconds.</p>|Dependent item|gitlab.ruby.process_cpu_seconds.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="ruby_process_cpu_seconds_total")].value.avg()`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|GitLab: Ruby: Running_threads|<p>Number of running Ruby threads.</p>|Dependent item|gitlab.ruby.threads_running<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|GitLab: Ruby: File descriptors opened, avg|<p>Average number of opened file descriptors.</p>|Dependent item|gitlab.ruby.file_descriptors.avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="ruby_file_descriptors")].value.avg()`</p></li></ul>|
|GitLab: Ruby: File descriptors opened, max|<p>Maximum number of opened file descriptors.</p>|Dependent item|gitlab.ruby.file_descriptors.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="ruby_file_descriptors")].value.max()`</p></li></ul>|
|GitLab: Ruby: File descriptors opened, min|<p>Minimum number of opened file descriptors.</p>|Dependent item|gitlab.ruby.file_descriptors.min<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="ruby_file_descriptors")].value.min()`</p></li></ul>|
|GitLab: Ruby: File descriptors, max|<p>Maximum number of open file descriptors per process.</p>|Dependent item|gitlab.ruby.process_max_fds<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="ruby_process_max_fds")].value.avg()`</p></li></ul>|
|GitLab: Ruby: RSS memory, avg|<p>Average RSS Memory usage in bytes.</p>|Dependent item|gitlab.ruby.process_resident_memory_bytes.avg<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|GitLab: Ruby: RSS memory, min|<p>Minimum RSS Memory usage in bytes.</p>|Dependent item|gitlab.ruby.process_resident_memory_bytes.min<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|GitLab: Ruby: RSS memory, max|<p>Maximum RSS Memory usage in bytes.</p>|Dependent item|gitlab.ruby.process_resident_memory_bytes.max<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p></li></ul>|
|GitLab: HTTP requests rate, total|<p>Number of requests received into the system.</p>|Dependent item|gitlab.http.requests.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=="http_requests_total")].value.sum()`</p></li><li>Change per second</li></ul>|
|GitLab: HTTP requests rate, 5xx|<p>Number of handle failures of requests with HTTP-code 5xx.</p>|Dependent item|gitlab.http.requests.5xx.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|GitLab: HTTP requests rate, 4xx|<p>Number of handle failures of requests with code 4XX.</p>|Dependent item|gitlab.http.requests.4xx.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|
|GitLab: Transactions per second|<p>Transactions per second (gitlab_transaction_* metrics).</p>|Dependent item|gitlab.transactions.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li>Change per second</li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GitLab: Gitlab instance is not able to accept traffic||`last(/GitLab by HTTP/gitlab.readiness)=0`|High|**Depends on**:<br><ul><li>GitLab: Liveness check was failed</li></ul>|
|GitLab: Liveness check was failed|<p>The application server is not running or Rails Controllers are deadlocked.</p>|`last(/GitLab by HTTP/gitlab.liveness)=0`|High||
|GitLab: Version has changed|<p>The GitLab version has changed. Acknowledge to close the problem manually.</p>|`last(/GitLab by HTTP/gitlab.deployments.version,#1)<>last(/GitLab by HTTP/gitlab.deployments.version,#2) and length(last(/GitLab by HTTP/gitlab.deployments.version))>0`|Info|**Manual close**: Yes|
|GitLab: Too many Redis queues client exceptions|<p>"Too many  Redis client exceptions during the requests to  Redis instance queues."</p>|`min(/GitLab by HTTP/gitlab.redis.client_exceptions.queues.rate,5m)>{$GITLAB.REDIS.FAIL.MAX.WARN}`|Warning||
|GitLab: Too many Redis cache client exceptions|<p>"Too many  Redis client exceptions during the requests to  Redis instance cache."</p>|`min(/GitLab by HTTP/gitlab.redis.client_exceptions.cache.rate,5m)>{$GITLAB.REDIS.FAIL.MAX.WARN}`|Warning||
|GitLab: Too many Redis shared_state client exceptions|<p>"Too many  Redis client exceptions during the requests to  Redis instance shared_state."</p>|`min(/GitLab by HTTP/gitlab.redis.client_exceptions.shared_state.rate,5m)>{$GITLAB.REDIS.FAIL.MAX.WARN}`|Warning||
|GitLab: Failed to fetch info data|<p>Zabbix has not received a metrics data for the last 30 minutes</p>|`nodata(/GitLab by HTTP/gitlab.ruby.threads_running,30m)=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>GitLab: Liveness check was failed</li></ul>|
|GitLab: Current number of open files is too high||`min(/GitLab by HTTP/gitlab.ruby.file_descriptors.max,5m)/last(/GitLab by HTTP/gitlab.ruby.process_max_fds)*100>{$GITLAB.OPEN.FDS.MAX.WARN}`|Warning||
|GitLab: Too many HTTP requests failures|<p>"Too many requests failed on GitLab instance with 5xx HTTP code"</p>|`min(/GitLab by HTTP/gitlab.http.requests.5xx.rate,5m)>{$GITLAB.HTTP.FAIL.MAX.WARN}`|Warning||

### LLD rule Unicorn metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Unicorn metrics discovery|<p>DiscoveryUnicorn specific metrics, when Unicorn is used.</p>|HTTP agent|gitlab.unicorn.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `unicorn_workers`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Unicorn metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|GitLab: Unicorn: Workers|<p>The number of Unicorn workers</p>|Dependent item|gitlab.unicorn.unicorn_workers[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=='unicorn_workers')].value.sum()`</p></li></ul>|
|GitLab: Unicorn: Active connections|<p>The number of active Unicorn connections.</p>|Dependent item|gitlab.unicorn.active_connections[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=='unicorn_active_connections')].value.sum()`</p></li></ul>|
|GitLab: Unicorn: Queued connections|<p>The number of queued Unicorn connections.</p>|Dependent item|gitlab.unicorn.queued_connections[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=='unicorn_queued_connections')].value.sum()`</p></li></ul>|

### Trigger prototypes for Unicorn metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GitLab: Unicorn worker utilization is too high||`min(/GitLab by HTTP/gitlab.unicorn.active_connections[{#SINGLETON}],5m)/last(/GitLab by HTTP/gitlab.unicorn.unicorn_workers[{#SINGLETON}])*100>{$GITLAB.UNICORN.UTILIZATION.MAX.WARN}`|Warning||
|GitLab: Unicorn is queueing requests||`min(/GitLab by HTTP/gitlab.unicorn.queued_connections[{#SINGLETON}],5m)>{$GITLAB.UNICORN.QUEUE.MAX.WARN}`|Warning||

### LLD rule Puma metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Puma metrics discovery|<p>Discovery of Puma specific metrics when Puma is used.</p>|HTTP agent|gitlab.puma.discovery<p>**Preprocessing**</p><ul><li><p>Prometheus to JSON: `puma_workers`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Item prototypes for Puma metrics discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|GitLab: Active connections|<p>Number of puma threads processing a request.</p>|Dependent item|gitlab.puma.active_connections[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=='puma_active_connections')].value.sum()`</p></li></ul>|
|GitLab: Workers|<p>Total number of puma workers.</p>|Dependent item|gitlab.puma.workers[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=='puma_workers')].value.sum()`</p></li></ul>|
|GitLab: Running workers|<p>The number of booted puma workers.</p>|Dependent item|gitlab.puma.running_workers[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=='puma_running_workers')].value.sum()`</p></li></ul>|
|GitLab: Stale workers|<p>The number of old puma workers.</p>|Dependent item|gitlab.puma.stale_workers[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=='puma_stale_workers')].value.sum()`</p></li></ul>|
|GitLab: Running threads|<p>The number of running puma threads.</p>|Dependent item|gitlab.puma.running[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=='puma_running')].value.sum()`</p></li></ul>|
|GitLab: Queued connections|<p>The number of connections in that puma worker's "todo" set waiting for a worker thread.</p>|Dependent item|gitlab.puma.queued_connections[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=='puma_queued_connections')].value.sum()`</p></li></ul>|
|GitLab: Pool capacity|<p>The number of requests the puma worker is capable of taking right now.</p>|Dependent item|gitlab.puma.pool_capacity[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=='puma_pool_capacity')].value.sum()`</p></li></ul>|
|GitLab: Max threads|<p>The maximum number of puma worker threads.</p>|Dependent item|gitlab.puma.max_threads[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=='puma_max_threads')].value.sum()`</p></li></ul>|
|GitLab: Idle threads|<p>The number of spawned puma threads which are not processing a request.</p>|Dependent item|gitlab.puma.idle_threads[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=='puma_idle_threads')].value.sum()`</p></li></ul>|
|GitLab: Killer terminations, total|<p>The number of workers terminated by PumaWorkerKiller.</p>|Dependent item|gitlab.puma.killer_terminations_total[{#SINGLETON}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name=='puma_killer_terminations_total')].value.sum()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|

### Trigger prototypes for Puma metrics discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|GitLab: Puma instance thread utilization is too high||`min(/GitLab by HTTP/gitlab.puma.active_connections[{#SINGLETON}],5m)/last(/GitLab by HTTP/gitlab.puma.max_threads[{#SINGLETON}])*100>{$GITLAB.PUMA.UTILIZATION.MAX.WARN}`|Warning||
|GitLab: Puma is queueing requests||`min(/GitLab by HTTP/gitlab.puma.queued_connections[{#SINGLETON}],15m)>{$GITLAB.PUMA.QUEUE.MAX.WARN}`|Warning||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

