
# GitLab by HTTP

## Overview

For Zabbix version: 6.0 and higher.  
This template is designed to monitor GitLab by Zabbix that works without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

The template `GitLab by HTTP` — collects metrics by an HTTP agent from the GitLab `/-/metrics` endpoint.
See https://docs.gitlab.com/ee/administration/monitoring/prometheus/gitlab_metrics.html.



This template was tested on:

- GitLab, version 13.5.3 EE

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.0/manual/config/templates_out_of_the_box/http) for basic instructions.

This template works with self-hosted GitLab instances. Internal service metrics are collected from the GitLab `/-/metrics` endpoint.
To access metrics following two methods are available:
1. Explicitly allow monitoring instance IP address in gitlab [whitelist configuration](https://docs.gitlab.com/ee/administration/monitoring/ip_whitelist.html).
2. Get token from Gitlab `Admin -> Monitoring -> Health check` page: http://your.gitlab.address/admin/health_check; Use this token in macro `{$GITLAB.HEALTH.TOKEN}` as variable path, like: `?token=your_token`.
Remember to change the macros `{$GITLAB.URL}`.
Also, see the Macros section for a list of macros used to set trigger values.

*NOTE.* Some metrics may not be collected depending on your Gitlab instance version and configuration. See [Gitlab's documentation](https://docs.gitlab.com/ee/administration/monitoring/prometheus/gitlab_metrics.html) for further information about its metric collection.


## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$GITLAB.HEALTH.TOKEN} |<p>The token path for Gitlab health check. Example `?token=your_token`</p> |`` |
|{$GITLAB.HTTP.FAIL.MAX.WARN} |<p>The maximum number of HTTP request failures for a trigger expression.</p> |`2` |
|{$GITLAB.OPEN.FDS.MAX.WARN} |<p>The maximum percentage of used file descriptors for a trigger expression.</p> |`90` |
|{$GITLAB.PUMA.QUEUE.MAX.WARN} |<p>The maximum number of PUMA queued requests for a trigger expression.</p> |`1` |
|{$GITLAB.PUMA.UTILIZATION.MAX.WARN} |<p>The maximum percentage of PUMA thread utilization for a trigger expression.</p> |`90` |
|{$GITLAB.REDIS.FAIL.MAX.WARN} |<p>The maximum number of Redis client exceptions for a trigger expression.</p> |`2` |
|{$GITLAB.UNICORN.QUEUE.MAX.WARN} |<p>The maximum number of Unicorn queued requests for a trigger expression.</p> |`1` |
|{$GITLAB.UNICORN.UTILIZATION.MAX.WARN} |<p>The maximum percentage of Unicorn workers utilization for a trigger expression.</p> |`90` |
|{$GITLAB.URL} |<p>URL of a GitLab instance.</p> |`http://localhost` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Puma metrics discovery |<p>Discovery Puma specific metrics, when Puma is used.</p> |HTTP_AGENT |gitlab.puma.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `puma_workers`</p><p>- JAVASCRIPT: `return JSON.stringify(value != "[]" ? [{'{#SINGLETON}': ''}] : []);`</p> |
|Unicorn metrics discovery |<p>DiscoveryUnicorn specific metrics, when Unicorn is used.</p> |HTTP_AGENT |gitlab.unicorn.discovery<p>**Preprocessing**:</p><p>- PROMETHEUS_TO_JSON: `unicorn_workers`</p><p>- JAVASCRIPT: `return JSON.stringify(value != "[]" ? [{'{#SINGLETON}': ''}] : []);`</p> |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|GitLab |GitLab: Instance readiness check |<p>The readiness probe checks whether the GitLab instance is ready to accept traffic via Rails Controllers.</p> |HTTP_AGENT |gitlab.readiness<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"master_check":[{"status":"failed"}]}`</p><p>- JSONPATH: `$.master_check[0].status`</p><p>- BOOL_TO_DECIMAL</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|GitLab |GitLab: Application server status |<p>Checks whether the application server is running. This probe is used to know if Rails Controllers are not deadlocked due to a multi-threading.</p> |HTTP_AGENT |gitlab.liveness<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> {"status": "failed"}`</p><p>- JSONPATH: `$.status`</p><p>- BOOL_TO_DECIMAL</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> 0`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `30m`</p> |
|GitLab |GitLab: Version |<p>Version of the GitLab instance.</p> |DEPENDENT |gitlab.deployments.version<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="deployments")].labels.version.first()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|GitLab |GitLab: Ruby: First process start time |<p>Minimum UNIX timestamp of ruby processes start time.</p> |DEPENDENT |gitlab.ruby.process_start_time_seconds.first<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="ruby_process_start_time_seconds")].value.min()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|GitLab |GitLab: Ruby: Last process start time |<p>Maximum UNIX timestamp ruby processes start time.</p> |DEPENDENT |gitlab.ruby.process_start_time_seconds.last<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="ruby_process_start_time_seconds")].value.max()`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `3h`</p> |
|GitLab |GitLab: User logins, total |<p>Counter of how many users have logged in since GitLab was started or restarted.</p> |DEPENDENT |gitlab.user_session_logins_total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="user_session_logins_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|GitLab |GitLab: User CAPTCHA logins failed, total |<p>Counter of failed CAPTCHA attempts during login.</p> |DEPENDENT |gitlab.failed_login_captcha_total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="failed_login_captcha_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|GitLab |GitLab: User CAPTCHA logins, total |<p>Counter of successful CAPTCHA attempts during login.</p> |DEPENDENT |gitlab.successful_login_captcha_total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="successful_login_captcha_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|GitLab |GitLab: Upload file does not exist    |<p>Number of times an upload record could not find its file.</p> |DEPENDENT |gitlab.upload_file_does_not_exist<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="upload_file_does_not_exist")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|GitLab |GitLab: Pipelines: Processing events, total  |<p>Total amount of pipeline processing events.</p> |DEPENDENT |gitlab.pipeline.processing_events_total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_ci_pipeline_processing_events_total")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|GitLab |GitLab: Pipelines: Created, total  |<p>Counter of pipelines created.</p> |DEPENDENT |gitlab.pipeline.created_total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="pipelines_created_total")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|GitLab |GitLab: Pipelines: Auto DevOps pipelines, total |<p>Counter of completed Auto DevOps pipelines.</p> |DEPENDENT |gitlab.pipeline.auto_devops_completed.total<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="auto_devops_pipelines_completed_total")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|GitLab |GitLab: Pipelines: Auto DevOps pipelines, failed |<p>Counter of completed Auto DevOps pipelines with status "failed".</p> |DEPENDENT |gitlab.pipeline.auto_devops_completed_total.failed<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="auto_devops_pipelines_completed_total" && @.labels.status == "failed")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|GitLab |GitLab: Pipelines: CI/CD creation duration |<p>The sum of the time in seconds it takes to create a CI/CD pipeline.</p> |DEPENDENT |gitlab.pipeline.pipeline_creation<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_ci_pipeline_creation_duration_seconds_sum")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|GitLab |GitLab: Pipelines: Pipelines: CI/CD creation count |<p>The count of the time it takes to create a CI/CD pipeline.</p> |DEPENDENT |gitlab.pipeline.pipeline_creation.count<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_ci_pipeline_creation_duration_seconds_count")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|GitLab |GitLab: Database: Connection pool, busy |<p>Connections to the main database in use where the owner is still alive.</p> |DEPENDENT |gitlab.database.connection_pool_busy<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_database_connection_pool_busy" && @.labels.class == "ActiveRecord::Base")].value.sum()`</p> |
|GitLab |GitLab: Database: Connection pool, current |<p>Current connections to the main database in the pool.</p> |DEPENDENT |gitlab.database.connection_pool_connections<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_database_connection_pool_connections" && @.labels.class == "ActiveRecord::Base")].value.sum()`</p> |
|GitLab |GitLab: Database: Connection pool, dead |<p>Connections to the main database in use where the owner is not alive.</p> |DEPENDENT |gitlab.database.connection_pool_dead<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_database_connection_pool_dead" && @.labels.class == "ActiveRecord::Base")].value.sum()`</p> |
|GitLab |GitLab: Database: Connection pool, idle |<p>Connections to the main database not in use.</p> |DEPENDENT |gitlab.database.connection_pool_idle<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_database_connection_pool_idle" && @.labels.class == "ActiveRecord::Base")].value.sum()`</p> |
|GitLab |GitLab: Database: Connection pool, size |<p>Total connection to the main database pool capacity.</p> |DEPENDENT |gitlab.database.connection_pool_size<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_database_connection_pool_size" && @.labels.class == "ActiveRecord::Base")].value.sum()`</p> |
|GitLab |GitLab: Database: Connection pool, waiting |<p>Threads currently waiting on this queue.</p> |DEPENDENT |gitlab.database.connection_pool_waiting<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_database_connection_pool_waiting" && @.labels.class == "ActiveRecord::Base")].value.sum()`</p> |
|GitLab |GitLab: Redis: Client requests rate, queues |<p>Number of Redis client requests per second. (Instance: queues)</p> |DEPENDENT |gitlab.redis.client_requests.queues.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_redis_client_requests_total" && @.labels.storage == "queues")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|GitLab |GitLab: Redis: Client requests rate, cache |<p>Number of Redis client requests per second. (Instance: cache)</p> |DEPENDENT |gitlab.redis.client_requests.cache.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_redis_client_requests_total" && @.labels.storage == "cache")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|GitLab |GitLab: Redis: Client requests rate, shared_state |<p>Number of Redis client requests per second. (Instance: shared_state)</p> |DEPENDENT |gitlab.redis.client_requests.shared_state.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_redis_client_requests_total" && @.labels.storage == "shared_state")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|GitLab |GitLab: Redis: Client exceptions rate, queues |<p>Number of Redis client exceptions per second. (Instance: queues)</p> |DEPENDENT |gitlab.redis.client_exceptions.queues.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_redis_client_exceptions_total" && @.labels.storage == "queues")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|GitLab |GitLab: Redis: Client exceptions rate, cache |<p>Number of Redis client exceptions per second. (Instance: cache)</p> |DEPENDENT |gitlab.redis.client_exceptions.cache.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_redis_client_exceptions_total" && @.labels.storage == "cache")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|GitLab |GitLab: Redis: client exceptions rate, shared_state |<p>Number of Redis client exceptions per second. (Instance: shared_state)</p> |DEPENDENT |gitlab.redis.client_exceptions.shared_state.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_redis_client_exceptions_total" && @.labels.storage == "shared_state")].value.first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|GitLab |GitLab: Cache: Misses rate, total  |<p>The cache read miss count.</p> |DEPENDENT |gitlab.cache.misses_total.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_cache_misses_total")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|GitLab |GitLab: Cache: Operations rate, total  |<p>The count of cache operations.</p> |DEPENDENT |gitlab.cache.operations_total.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_cache_operations_total")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|GitLab |GitLab: Ruby: CPU  usage per second |<p>Average CPU time util in seconds.</p> |DEPENDENT |gitlab.ruby.process_cpu_seconds.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="ruby_process_cpu_seconds_total")].value.avg()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|GitLab |GitLab: Ruby: Running_threads |<p>Number of running Ruby threads.</p> |DEPENDENT |gitlab.ruby.threads_running<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="gitlab_ruby_threads_running_threads")].value.sum()`</p> |
|GitLab |GitLab: Ruby: File descriptors opened, avg |<p>Average number of opened file descriptors.</p> |DEPENDENT |gitlab.ruby.file_descriptors.avg<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="ruby_file_descriptors")].value.avg()`</p> |
|GitLab |GitLab: Ruby: File descriptors opened, max |<p>Maximum number of opened file descriptors.</p> |DEPENDENT |gitlab.ruby.file_descriptors.max<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="ruby_file_descriptors")].value.max()`</p> |
|GitLab |GitLab: Ruby: File descriptors opened, min |<p>Minimum number of opened file descriptors.</p> |DEPENDENT |gitlab.ruby.file_descriptors.min<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="ruby_file_descriptors")].value.min()`</p> |
|GitLab |GitLab: Ruby: File descriptors, max |<p>Maximum number of open file descriptors per process.</p> |DEPENDENT |gitlab.ruby.process_max_fds<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="ruby_process_max_fds")].value.avg()`</p> |
|GitLab |GitLab: Ruby: RSS memory, avg |<p>Average RSS Memory usage in bytes.</p> |DEPENDENT |gitlab.ruby.process_resident_memory_bytes.avg<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="ruby_process_resident_memory_bytes")].value.avg()`</p> |
|GitLab |GitLab: Ruby: RSS memory, min |<p>Minimum RSS Memory usage in bytes.</p> |DEPENDENT |gitlab.ruby.process_resident_memory_bytes.min<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="ruby_process_resident_memory_bytes")].value.min()`</p> |
|GitLab |GitLab: Ruby: RSS memory, max |<p>Maximum RSS Memory usage in bytes.</p> |DEPENDENT |gitlab.ruby.process_resident_memory_bytes.max<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="ruby_process_resident_memory_bytes")].value.max()`</p> |
|GitLab |GitLab: HTTP requests rate, total |<p>Number of requests received into the system.</p> |DEPENDENT |gitlab.http.requests.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="http_requests_total")].value.sum()`</p><p>- CHANGE_PER_SECOND</p> |
|GitLab |GitLab: HTTP requests rate, 5xx |<p>Number of handle failures of requests with HTTP-code 5xx.</p> |DEPENDENT |gitlab.http.requests.5xx.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="http_requests_total" && @.labels.status =~ '5..' )].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|GitLab |GitLab: HTTP requests rate, 4xx |<p>Number of handle failures of requests with code 4XX.</p> |DEPENDENT |gitlab.http.requests.4xx.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=="http_requests_total" && @.labels.status =~ '4..' )].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|GitLab |GitLab: Transactions per second |<p>Transactions per second (gitlab_transaction_* metrics).</p> |DEPENDENT |gitlab.transactions.rate<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=~"gitlab_transaction_.*_count_total")].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- CHANGE_PER_SECOND</p> |
|GitLab: Puma stats |GitLab: Active connections |<p>Number of puma threads processing a request.</p> |DEPENDENT |gitlab.puma.active_connections[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=='puma_active_connections')].value.sum()`</p> |
|GitLab: Puma stats |GitLab: Workers |<p>Total number of puma workers.</p> |DEPENDENT |gitlab.puma.workers[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=='puma_workers')].value.sum()`</p> |
|GitLab: Puma stats |GitLab: Running workers |<p>The number of booted puma workers.</p> |DEPENDENT |gitlab.puma.running_workers[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=='puma_running_workers')].value.sum()`</p> |
|GitLab: Puma stats |GitLab: Stale workers |<p>The number of old puma workers.</p> |DEPENDENT |gitlab.puma.stale_workers[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=='puma_stale_workers')].value.sum()`</p> |
|GitLab: Puma stats |GitLab: Running threads |<p>The number of running puma threads.</p> |DEPENDENT |gitlab.puma.running[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=='puma_running')].value.sum()`</p> |
|GitLab: Puma stats |GitLab: Queued connections |<p>The number of connections in that puma worker's "todo" set waiting for a worker thread.</p> |DEPENDENT |gitlab.puma.queued_connections[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=='puma_queued_connections')].value.sum()`</p> |
|GitLab: Puma stats |GitLab: Pool capacity |<p>The number of requests the puma worker is capable of taking right now.</p> |DEPENDENT |gitlab.puma.pool_capacity[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=='puma_pool_capacity')].value.sum()`</p> |
|GitLab: Puma stats |GitLab: Max threads |<p>The maximum number of puma worker threads.</p> |DEPENDENT |gitlab.puma.max_threads[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=='puma_max_threads')].value.sum()`</p> |
|GitLab: Puma stats |GitLab: Idle threads |<p>The number of spawned puma threads which are not processing a request.</p> |DEPENDENT |gitlab.puma.idle_threads[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=='puma_idle_threads')].value.sum()`</p> |
|GitLab: Puma stats |GitLab: Killer terminations, total |<p>The number of workers terminated by PumaWorkerKiller.</p> |DEPENDENT |gitlab.puma.killer_terminations_total[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=='puma_killer_terminations_total')].value.sum()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|GitLab: Unicorn stats |GitLab: Unicorn: Workers |<p>The number of Unicorn workers</p> |DEPENDENT |gitlab.unicorn.unicorn_workers[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=='unicorn_workers')].value.sum()`</p> |
|GitLab: Unicorn stats |GitLab: Unicorn: Active connections |<p>The number of active Unicorn connections.</p> |DEPENDENT |gitlab.unicorn.active_connections[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=='unicorn_active_connections')].value.sum()`</p> |
|GitLab: Unicorn stats |GitLab: Unicorn: Queued connections |<p>The number of queued Unicorn connections.</p> |DEPENDENT |gitlab.unicorn.queued_connections[{#SINGLETON}]<p>**Preprocessing**:</p><p>- JSONPATH: `$[?(@.name=='unicorn_queued_connections')].value.sum()`</p> |
|Zabbix raw items |GitLab: Get instance metrics |<p>-</p> |HTTP_AGENT |gitlab.get_metrics<p>**Preprocessing**:</p><p>- CHECK_NOT_SUPPORTED</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p><p>- PROMETHEUS_TO_JSON</p> |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|GitLab: Gitlab instance is not able to accept traffic |<p>-</p> |`last(/GitLab by HTTP/gitlab.readiness)=0` |HIGH |<p>**Depends on**:</p><p>- GitLab: Liveness check was failed</p> |
|GitLab: Liveness check was failed |<p>The application server is not running or Rails Controllers are deadlocked.</p> |`last(/GitLab by HTTP/gitlab.liveness)=0` |HIGH | |
|GitLab: Version has changed |<p>The GitLab version has changed. Perform Ack to close.</p> |`last(/GitLab by HTTP/gitlab.deployments.version,#1)<>last(/GitLab by HTTP/gitlab.deployments.version,#2) and length(last(/GitLab by HTTP/gitlab.deployments.version))>0` |INFO |<p>Manual close: YES</p> |
|GitLab: Too many Redis queues client exceptions |<p>"Too many  Redis client exceptions during the requests to  Redis instance queues."</p> |`min(/GitLab by HTTP/gitlab.redis.client_exceptions.queues.rate,5m)>{$GITLAB.REDIS.FAIL.MAX.WARN}` |WARNING | |
|GitLab: Too many Redis cache client exceptions |<p>"Too many  Redis client exceptions during the requests to  Redis instance cache."</p> |`min(/GitLab by HTTP/gitlab.redis.client_exceptions.cache.rate,5m)>{$GITLAB.REDIS.FAIL.MAX.WARN}` |WARNING | |
|GitLab: Too many Redis shared_state client exceptions |<p>"Too many  Redis client exceptions during the requests to  Redis instance shared_state."</p> |`min(/GitLab by HTTP/gitlab.redis.client_exceptions.shared_state.rate,5m)>{$GITLAB.REDIS.FAIL.MAX.WARN}` |WARNING | |
|GitLab: Failed to fetch info data |<p>Zabbix has not received a metrics data for the last 30 minutes</p> |`nodata(/GitLab by HTTP/gitlab.ruby.threads_running,30m)=1` |WARNING |<p>Manual close: YES</p><p>**Depends on**:</p><p>- GitLab: Liveness check was failed</p> |
|GitLab: Current number of open files is too high |<p>-</p> |`min(/GitLab by HTTP/gitlab.ruby.file_descriptors.max,5m)/last(/GitLab by HTTP/gitlab.ruby.process_max_fds)*100>{$GITLAB.OPEN.FDS.MAX.WARN}` |WARNING | |
|GitLab: Too many HTTP requests failures |<p>"Too many requests failed on GitLab instance with 5xx HTTP code"</p> |`min(/GitLab by HTTP/gitlab.http.requests.5xx.rate,5m)>{$GITLAB.HTTP.FAIL.MAX.WARN}` |WARNING | |
|GitLab: Puma instance thread utilization is too high |<p>-</p> |`min(/GitLab by HTTP/gitlab.puma.active_connections[{#SINGLETON}],5m)/last(/GitLab by HTTP/gitlab.puma.max_threads[{#SINGLETON}])*100>{$GITLAB.PUMA.UTILIZATION.MAX.WARN}` |WARNING | |
|GitLab: Puma is queueing requests |<p>-</p> |`min(/GitLab by HTTP/gitlab.puma.queued_connections[{#SINGLETON}],15m)>{$GITLAB.PUMA.QUEUE.MAX.WARN}` |WARNING | |
|GitLab: Unicorn worker utilization is too high |<p>-</p> |`min(/GitLab by HTTP/gitlab.unicorn.active_connections[{#SINGLETON}],5m)/last(/GitLab by HTTP/gitlab.unicorn.unicorn_workers[{#SINGLETON}])*100>{$GITLAB.UNICORN.UTILIZATION.MAX.WARN}` |WARNING | |
|GitLab: Unicorn is queueing requests |<p>-</p> |`min(/GitLab by HTTP/gitlab.unicorn.queued_connections[{#SINGLETON}],5m)>{$GITLAB.UNICORN.QUEUE.MAX.WARN}` |WARNING | |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).

