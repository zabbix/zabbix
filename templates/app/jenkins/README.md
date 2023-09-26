
# Jenkins by HTTP

## Overview

The template to monitor Apache Jenkins by Zabbix that work without any external scripts.
Most of the metrics are collected in one go, thanks to Zabbix bulk data collection.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Jenkins 2.263.1 

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

Metrics are collected by requests to [Metrics API](https://plugins.jenkins.io/metrics/).
For common metrics:
 Install and configure Metrics plugin parameters according [official documentations](https://plugins.jenkins.io/metrics/). Do not forget to configure access to the Metrics Servlet by issuing API key and change macro {$JENKINS.API.KEY}.

For monitoring computers and builds:
 Create API token for monitoring user according [official documentations](https://www.jenkins.io/doc/book/system-administration/authenticating-scripted-clients/) and change macro {$JENKINS.USER}, {$JENKINS.API.TOKEN}.
Don't forget to change macros {$JENKINS.URL}.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$JENKINS.URL}|<p>Jenkins URL in the format `<scheme>://<host>:<port>`</p>||
|{$JENKINS.API.KEY}|<p>API key to access Metrics Servlet</p>||
|{$JENKINS.USER}|<p>Username for HTTP BASIC authentication</p>|`zabbix`|
|{$JENKINS.API.TOKEN}|<p>API token for HTTP BASIC authentication.</p>||
|{$JENKINS.PING.REPLY}|<p>Expected reply to the ping.</p>|`pong`|
|{$JENKINS.FILE_DESCRIPTORS.MAX.WARN}|<p>Maximum percentage of file descriptors usage alert threshold (for trigger expression).</p>|`85`|
|{$JENKINS.JOB.HEALTH.SCORE.MIN.WARN}|<p>Minimum job's health score (for trigger expression).</p>|`50`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Jenkins: Get service metrics||HTTP agent|jenkins.get_metrics<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Jenkins: Get healthcheck||HTTP agent|jenkins.healthcheck<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Jenkins: Get jobs info||HTTP agent|jenkins.job_info<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Jenkins: Get computer info||HTTP agent|jenkins.computer_info<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Jenkins: Disk space check message|<p>The message will reference the first node which fails this check.  There may be other nodes that fail the check, but this health check is designed to fail fast.</p>|Dependent item|jenkins.disk_space.message<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['disk-space'].message`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Jenkins: Temporary space check message|<p>The message will reference the first node which fails this check. There may be other nodes that fail the check, but this health check is designed to fail fast.</p>|Dependent item|jenkins.temporary_space.message<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['temporary-space'].message`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Jenkins: Plugins check message|<p>The message of plugins health check.</p>|Dependent item|jenkins.plugins.message<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['plugins'].message`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Jenkins: Thread deadlock check message|<p>The message of thread deadlock health check.</p>|Dependent item|jenkins.thread_deadlock.message<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['thread-deadlock'].message`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Jenkins: Disk space check|<p>Returns FAIL if any of the Jenkins disk space monitors are reporting the disk space as less than the configured threshold.</p>|Dependent item|jenkins.disk_space<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['disk-space'].healthy`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Plugins check|<p>Returns FAIL if any of the Jenkins plugins failed to start.</p>|Dependent item|jenkins.plugins<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.plugins.healthy`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Temporary space check|<p>Returns FAIL if any of the Jenkins temporary space monitors are reporting the temporary space as less than the configured threshold.</p>|Dependent item|jenkins.temporary_space<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['temporary-space'].healthy`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Thread deadlock check|<p>Returns FAIL if there are any deadlocked threads in the Jenkins master JVM.</p>|Dependent item|jenkins.thread_deadlock<p>**Preprocessing**</p><ul><li><p>JSON Path: `$['thread-deadlock'].healthy`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Get gauges|<p>Raw items for gauges metrics.</p>|Dependent item|jenkins.gauges.raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.gauges`</p></li></ul>|
|Jenkins: Executors count|<p>The number of executors available to Jenkins. This is corresponds to the sum of all the executors of all the online nodes.</p>|Dependent item|jenkins.executor.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.executor.count.value'].value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Executors free|<p>The number of executors available to Jenkins that are not currently in use.</p>|Dependent item|jenkins.executor.free<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.executor.free.value'].value`</p></li></ul>|
|Jenkins: Executors in use|<p>The number of executors available to Jenkins that are currently in use.</p>|Dependent item|jenkins.executor.in_use<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.executor.in-use.value'].value`</p></li></ul>|
|Jenkins: Nodes count|<p>The number of build nodes available to Jenkins, both online and offline.</p>|Dependent item|jenkins.node.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.node.count.value'].value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Nodes offline|<p>The number of build nodes available to Jenkins but currently offline.</p>|Dependent item|jenkins.node.offline<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.node.offline.value'].value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Nodes online|<p>The number of build nodes available to Jenkins and currently online.</p>|Dependent item|jenkins.node.online<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.node.online.value'].value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Plugins active|<p>The number of plugins in the Jenkins instance that started successfully.</p>|Dependent item|jenkins.plugins.active<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.plugins.active'].value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Plugins failed|<p>The number of plugins in the Jenkins instance that failed to start. A value other than 0 is typically indicative of a potential issue within the Jenkins installation that will either be solved by explicitly disabling the plugin(s) or by resolving the plugin dependency issues.</p>|Dependent item|jenkins.plugins.failed<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.plugins.failed'].value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Plugins inactive|<p>The number of plugins in the Jenkins instance that are not currently enabled.</p>|Dependent item|jenkins.plugins.inactive<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.plugins.inactive'].value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Plugins with update|<p>The number of plugins in the Jenkins instance that have a newer version reported as available in the current Jenkins update center metadata held by Jenkins. This value is not indicative of an issue with Jenkins but high values can be used as a trigger to review the plugins with updates with a view to seeing whether those updates potentially contain fixes for issues that could be affecting your Jenkins instance.</p>|Dependent item|jenkins.plugins.with_update<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.plugins.withUpdate'].value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Projects count|<p>The number of projects.</p>|Dependent item|jenkins.project.count<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.project.count.value'].value`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Jobs count|<p>The number of jobs in Jenkins.</p>|Dependent item|jenkins.job.count.value<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.count.value'].value`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Jenkins: Get meters|<p>Raw items for meters metrics.</p>|Dependent item|jenkins.meters.raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.meters`</p></li></ul>|
|Jenkins: Job scheduled, m1 rate|<p>The rate at which jobs are scheduled. If a job is already in the queue and an identical request for scheduling the job is received then Jenkins will coalesce the two requests. This metric gives a reasonably pure measure of the load requirements of the Jenkins master as it is unaffected by the number of executors available to the system.</p>|Dependent item|jenkins.job.scheduled.m1.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.scheduled'].m1_rate`</p></li></ul>|
|Jenkins: Jobs scheduled, m5 rate|<p>The rate at which jobs are scheduled. If a job is already in the queue and an identical request for scheduling the job is received then Jenkins will coalesce the two requests. This metric gives a reasonably pure measure of the load requirements of the Jenkins master as it is unaffected by the number of executors available to the system.</p>|Dependent item|jenkins.job.scheduled.m5.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.scheduled'].m5_rate`</p></li></ul>|
|Jenkins: Get timers|<p>Raw items for timers metrics.</p>|Dependent item|jenkins.timers.raw<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.timers`</p></li></ul>|
|Jenkins: Job blocked, m1 rate|<p>The rate at which jobs in the build queue enter the blocked state.</p>|Dependent item|jenkins.job.blocked.m1.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.blocked.duration'].m1_rate`</p></li></ul>|
|Jenkins: Job blocked, m5 rate|<p>The rate at which jobs in the build queue enter the blocked state.</p>|Dependent item|jenkins.job.blocked.m5.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.blocked.duration'].m5_rate`</p></li></ul>|
|Jenkins: Job blocked duration, p95|<p>The amount of time which jobs spend in the blocked state.</p>|Dependent item|jenkins.job.blocked.duration.p95<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.blocked.duration'].p95`</p></li></ul>|
|Jenkins: Job blocked duration, median|<p>The amount of time which jobs spend in the blocked state.</p>|Dependent item|jenkins.job.blocked.duration.p50<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.blocked.duration'].p50`</p></li></ul>|
|Jenkins: Job building, m1 rate|<p>The rate at which jobs are built.</p>|Dependent item|jenkins.job.building.m1.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.building.duration'].m1_rate`</p></li></ul>|
|Jenkins: Job building, m5 rate|<p>The rate at which jobs are built.</p>|Dependent item|jenkins.job.building.m5.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.building.duration'].m5_rate`</p></li></ul>|
|Jenkins: Job building duration, p95|<p>The amount of time which jobs spend building.</p>|Dependent item|jenkins.job.building.duration.p95<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.building.duration'].p95`</p></li></ul>|
|Jenkins: Job building duration, median|<p>The amount of time which jobs spend building.</p>|Dependent item|jenkins.job.building.duration.p50<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.building.duration'].p50`</p></li></ul>|
|Jenkins: Job buildable, m1 rate|<p>The rate at which jobs in the build queue enter the buildable state.</p>|Dependent item|jenkins.job.buildable.m1.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.buildable.duration'].m1_rate`</p></li></ul>|
|Jenkins: Job buildable, m5 rate|<p>The rate at which jobs in the build queue enter the buildable state.</p>|Dependent item|jenkins.job.buildable.m5.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.buildable.duration'].m5_rate`</p></li></ul>|
|Jenkins: Job buildable duration, p95|<p>The amount of time which jobs spend in the buildable state.</p>|Dependent item|jenkins.job.buildable.duration.p95<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.buildable.duration'].p95`</p></li></ul>|
|Jenkins: Job buildable duration, median|<p>The amount of time which jobs spend in the buildable state.</p>|Dependent item|jenkins.job.buildable.duration.p50<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.buildable.duration'].p50`</p></li></ul>|
|Jenkins: Job queuing, m1 rate|<p>The rate at which jobs are queued.</p>|Dependent item|jenkins.job.queuing.m1.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.queuing.duration'].m1_rate`</p></li></ul>|
|Jenkins: Job queuing, m5 rate|<p>The rate at which jobs are queued.</p>|Dependent item|jenkins.job.queuing.m5.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.queuing.duration'].m5_rate`</p></li></ul>|
|Jenkins: Job queuing duration, p95|<p>The total time which jobs spend in the build queue.</p>|Dependent item|jenkins.job.queuing.duration.p95<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.queuing.duration'].p95`</p></li></ul>|
|Jenkins: Job queuing duration, median|<p>The total time which jobs spend in the build queue.</p>|Dependent item|jenkins.job.queuing.duration.p50<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.queuing.duration'].p50`</p></li></ul>|
|Jenkins: Job total, m1 rate|<p>The rate at which jobs are queued.</p>|Dependent item|jenkins.job.total.m1.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.total.duration'].m1_rate`</p></li></ul>|
|Jenkins: Job total, m5 rate|<p>The rate at which jobs are queued.</p>|Dependent item|jenkins.job.total.m5.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.total.duration'].m5_rate`</p></li></ul>|
|Jenkins: Job total duration, p95|<p>The total time which jobs spend from entering the build queue to completing building.</p>|Dependent item|jenkins.job.total.duration.p95<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.total.duration'].p95`</p></li></ul>|
|Jenkins: Job total duration, median|<p>The total time which jobs spend from entering the build queue to completing building.</p>|Dependent item|jenkins.job.total.duration.p50<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.total.duration'].p50`</p></li></ul>|
|Jenkins: Job waiting, m1 rate|<p>The rate at which jobs enter the quiet period.</p>|Dependent item|jenkins.job.waiting.m1.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.waiting.duration'].m1_rate`</p></li></ul>|
|Jenkins: Job waiting, m5 rate|<p>The rate at which jobs enter the quiet period.</p>|Dependent item|jenkins.job.waiting.m5.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.waiting.duration'].m5_rate`</p></li></ul>|
|Jenkins: Job waiting duration, p95|<p>The total amount of time that jobs spend in their quiet period.</p>|Dependent item|jenkins.job.waiting.duration.p95<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.waiting.duration'].p95`</p></li></ul>|
|Jenkins: Job waiting duration, median|<p>The total amount of time that jobs spend in their quiet period.</p>|Dependent item|jenkins.job.waiting.duration.p50<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.job.waiting.duration'].p50`</p></li></ul>|
|Jenkins: Build queue, blocked|<p>The number of jobs that are in the Jenkins build queue and currently in the blocked state.</p>|Dependent item|jenkins.queue.blocked<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.queue.blocked.value'].value`</p></li></ul>|
|Jenkins: Build queue, size|<p>The number of jobs that are in the Jenkins build queue.</p>|Dependent item|jenkins.queue.size<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.queue.size.value'].value`</p></li></ul>|
|Jenkins: Build queue, buildable|<p>The number of jobs that are in the Jenkins build queue and currently in the blocked state.</p>|Dependent item|jenkins.queue.buildable<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.queue.buildable.value'].value`</p></li></ul>|
|Jenkins: Build queue, pending|<p>The number of jobs that are in the Jenkins build queue and currently in the blocked state.</p>|Dependent item|jenkins.queue.pending<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.queue.pending.value'].value`</p></li></ul>|
|Jenkins: Build queue, stuck|<p>The number of jobs that are in the Jenkins build queue and currently in the blocked state.</p>|Dependent item|jenkins.queue.stuck<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.queue.stuck.value'].value`</p></li></ul>|
|Jenkins: HTTP active requests, rate|<p>The number of currently active requests against the Jenkins master Web UI.</p>|Dependent item|jenkins.http.active_requests.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.counters.['http.activeRequests'].count`</p></li><li>Change per second</li></ul>|
|Jenkins: HTTP response 400, rate|<p>The rate at which the Jenkins master Web UI is responding to requests with an HTTP/400 status code.</p>|Dependent item|jenkins.http.bad_request.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['http.responseCodes.badRequest'].count`</p></li><li>Change per second</li></ul>|
|Jenkins: HTTP response 500, rate|<p>The rate at which the Jenkins master Web UI is responding to requests with an HTTP/500 status code.</p>|Dependent item|jenkins.http.server_error.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['http.responseCodes.serverError'].count`</p></li><li>Change per second</li></ul>|
|Jenkins: HTTP response 503, rate|<p>The rate at which the Jenkins master Web UI is responding to requests with an HTTP/503 status code.</p>|Dependent item|jenkins.http.service_unavailable.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['http.responseCodes.serviceUnavailable'].count`</p></li><li>Change per second</li></ul>|
|Jenkins: HTTP response 200, rate|<p>The rate at which the Jenkins master Web UI is responding to requests with an HTTP/200 status code.</p>|Dependent item|jenkins.http.ok.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['http.responseCodes.ok'].count`</p></li><li>Change per second</li></ul>|
|Jenkins: HTTP response other, rate|<p>The rate at which the Jenkins master Web UI is responding to requests with a non-informational status code that is not in the list: HTTP/200, HTTP/201, HTTP/204, HTTP/304, HTTP/400, HTTP/403, HTTP/404, HTTP/500, or HTTP/503.</p>|Dependent item|jenkins.http.other.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['http.responseCodes.other'].count`</p></li><li>Change per second</li></ul>|
|Jenkins: HTTP response 201, rate|<p>The rate at which the Jenkins master Web UI is responding to requests with an HTTP/201 status code.</p>|Dependent item|jenkins.http.created.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['http.responseCodes.created'].count`</p></li><li>Change per second</li></ul>|
|Jenkins: HTTP response 204, rate|<p>The rate at which the Jenkins master Web UI is responding to requests with an HTTP/204 status code.</p>|Dependent item|jenkins.http.no_content.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['http.responseCodes.noContent'].count`</p></li><li>Change per second</li></ul>|
|Jenkins: HTTP response 404, rate|<p>The rate at which the Jenkins master Web UI is responding to requests with an HTTP/404 status code.</p>|Dependent item|jenkins.http.not_found.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['http.responseCodes.notFound'].count`</p></li><li>Change per second</li></ul>|
|Jenkins: HTTP response 304, rate|<p>The rate at which the Jenkins master Web UI is responding to requests with an HTTP/304 status code.</p>|Dependent item|jenkins.http.not_modified.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['http.responseCodes.notModified'].count`</p></li><li>Change per second</li></ul>|
|Jenkins: HTTP response 403, rate|<p>The rate at which the Jenkins master Web UI is responding to requests with an HTTP/403 status code.</p>|Dependent item|jenkins.http.forbidden.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['http.responseCodes.forbidden'].count`</p></li><li>Change per second</li></ul>|
|Jenkins: HTTP requests, rate|<p>The rate at which the Jenkins master Web UI is receiving requests.</p>|Dependent item|jenkins.http.requests.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['http.requests'].count`</p></li><li>Change per second</li></ul>|
|Jenkins: HTTP requests, p95|<p>The time spent generating the corresponding responses.</p>|Dependent item|jenkins.http.requests_p95.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['http.requests'].p95`</p></li></ul>|
|Jenkins: HTTP requests, median|<p>The time spent generating the corresponding responses.</p>|Dependent item|jenkins.http.requests_p50.rate<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['http.requests'].p50`</p></li></ul>|
|Jenkins: Version|<p>Version of Jenkins server.</p>|Dependent item|jenkins.version<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['jenkins.versions.core'].value`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Jenkins: CPU Load|<p>The system load on the Jenkins master as reported by the JVM's Operating System JMX bean. The calculation of system load is operating system dependent. Typically this is the sum of the number of processes that are currently running plus the number that are waiting to run. This is typically comparable against the number of CPU cores.</p>|Dependent item|jenkins.system.cpu.load<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['system.cpu.load'].value`</p></li></ul>|
|Jenkins: Uptime|<p>The number of seconds since the Jenkins master JVM started.</p>|Dependent item|jenkins.system.uptime<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['vm.uptime.milliseconds'].value`</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Jenkins: File descriptor ratio|<p>The ratio of used to total file descriptors</p>|Dependent item|jenkins.descriptor.ratio<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.['vm.file.descriptor.ratio'].value`</p></li><li><p>Custom multiplier: `100`</p></li></ul>|
|Jenkins: Service ping||HTTP agent|jenkins.ping<p>**Preprocessing**</p><ul><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Regular expression: `{$JENKINS.PING.REPLY} 1`</p><p>⛔️Custom on fail: Set value to: `0`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Jenkins: Disk space is too low|<p>Jenkins disk space monitors are reporting the disk space as less than the configured threshold. The message will reference the first node which fails this check.<br>Health check message: {{ITEM.LASTVALUE2}.regsub("(.*)",\1)}</p>|`last(/Jenkins by HTTP/jenkins.disk_space)=0 and length(last(/Jenkins by HTTP/jenkins.disk_space.message))>0`|Warning||
|Jenkins: One or more Jenkins plugins failed to start|<p>A failure is typically indicative of a potential issue within the Jenkins installation that will either be solved by explicitly disabling the failing plugin(s) or by resolving the corresponding plugin dependency issues.<br>Health check message: {{ITEM.LASTVALUE2}.regsub("(.*)",\1)}</p>|`last(/Jenkins by HTTP/jenkins.plugins)=0 and length(last(/Jenkins by HTTP/jenkins.plugins.message))>0`|Info|**Manual close**: Yes|
|Jenkins: Temporary space is too low|<p>Jenkins temporary space monitors are reporting the temporary space as less than the configured threshold. The message will reference the first node which fails this check.<br>Health check message: {{ITEM.LASTVALUE2}.regsub("(.*)",\1)}</p>|`last(/Jenkins by HTTP/jenkins.temporary_space)=0 and length(last(/Jenkins by HTTP/jenkins.temporary_space.message))>0`|Warning||
|Jenkins: There are deadlocked threads in Jenkins master JVM|<p>There are any deadlocked threads in the Jenkins master JVM.<br>Health check message: {{ITEM.LASTVALUE2}.regsub('(.*)',\1)}</p>|`last(/Jenkins by HTTP/jenkins.thread_deadlock)=0 and length(last(/Jenkins by HTTP/jenkins.thread_deadlock.message))>0`|Warning||
|Jenkins: Service has no online nodes||`last(/Jenkins by HTTP/jenkins.node.online)=0`|Average||
|Jenkins: Version has changed|<p>The Jenkins version has changed. Acknowledge to close the problem manually.</p>|`last(/Jenkins by HTTP/jenkins.version,#1)<>last(/Jenkins by HTTP/jenkins.version,#2) and length(last(/Jenkins by HTTP/jenkins.version))>0`|Info|**Manual close**: Yes|
|Jenkins: Host has been restarted|<p>Uptime is less than 10 minutes.</p>|`last(/Jenkins by HTTP/jenkins.system.uptime)<10m`|Info|**Manual close**: Yes|
|Jenkins: Current number of used files is too high||`min(/Jenkins by HTTP/jenkins.descriptor.ratio,5m)>{$JENKINS.FILE_DESCRIPTORS.MAX.WARN}`|Warning||
|Jenkins: Service is down||`last(/Jenkins by HTTP/jenkins.ping)=0`|Average|**Manual close**: Yes|

### LLD rule Jobs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Jobs discovery||HTTP agent|jenkins.jobs<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.jobs.[*]`</p></li></ul>|

### Item prototypes for Jobs discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Jenkins job [{#NAME}]: Get job|<p>Raw data for a job.</p>|Dependent item|jenkins.job.get[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.jobs.[?(@.name == "{#NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Jenkins job [{#NAME}]: Health score|<p>Represents health of project. A number between 0-100.</p><p>Job Description: {#DESCRIPTION}</p><p>Job Url: {#URL}</p>|Dependent item|jenkins.build.health[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.healthReport..score.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Jenkins job [{#NAME}]: Last Build number|<p>Details: {#URL}/lastBuild/</p>|Dependent item|jenkins.last_build.number[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastBuild.number`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Jenkins job [{#NAME}]: Last Build duration|<p>Build duration (in seconds).</p>|Dependent item|jenkins.last_build.duration[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastBuild.duration`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Jenkins job [{#NAME}]: Last Build timestamp||Dependent item|jenkins.last_build.timestamp[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastBuild.timestamp`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Jenkins job [{#NAME}]: Last Build result||Dependent item|jenkins.last_build.result[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastBuild.result`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Jenkins job [{#NAME}]: Last Failed Build number|<p>Details: {#URL}/lastFailedBuild/</p>|Dependent item|jenkins.last_failed_build.number[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastFailedBuild.number`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Jenkins job [{#NAME}]: Last Failed Build duration|<p>Build duration (in seconds).</p>|Dependent item|jenkins.last_failed_build.duration[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastFailedBuild.duration`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Jenkins job [{#NAME}]: Last Failed Build timestamp||Dependent item|jenkins.last_failed_build.timestamp[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastFailedBuild.timestamp`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Jenkins job [{#NAME}]: Last Successful Build number|<p>Details: {#URL}/lastSuccessfulBuild/</p>|Dependent item|jenkins.last_successful_build.number[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastSuccessfulBuild.number`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Jenkins job [{#NAME}]: Last Successful Build duration|<p>Build duration (in seconds).</p>|Dependent item|jenkins.last_successful_build.duration[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastSuccessfulBuild.duration`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|
|Jenkins job [{#NAME}]: Last Successful Build timestamp||Dependent item|jenkins.last_successful_build.timestamp[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastSuccessfulBuild.timestamp`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li><li><p>Discard unchanged with heartbeat: `30m`</p></li></ul>|

### Trigger prototypes for Jobs discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Jenkins job [{#NAME}]: Job is unhealthy||`last(/Jenkins by HTTP/jenkins.build.health[{#NAME}])<{$JENKINS.JOB.HEALTH.SCORE.MIN.WARN}`|Warning|**Manual close**: Yes|

### LLD rule Computers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Computers discovery||HTTP agent|jenkins.computers<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.computer.[*]`</p></li></ul>|

### Item prototypes for Computers discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Jenkins: Computer [{#DISPLAY_NAME}]: Get computer|<p>Raw data for a computer.</p>|Dependent item|jenkins.computer.get[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.computer.[?(@.displayName == "{#DISPLAY_NAME}")].first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: Executors|<p>The maximum number of concurrent builds that Jenkins may perform on this node.</p>|Dependent item|jenkins.computer.numExecutors[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.numExecutors`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: State|<p>Represents the actual online/offline state.</p><p>Node description: {#DESCRIPTION}</p>|Dependent item|jenkins.computer.state[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.offline`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: Offline cause reason|<p>If the computer was offline (either temporarily or not), will return the cause as a string (without user info). Empty string if the system was put offline without given a cause.</p>|Dependent item|jenkins.computer.offline.reason[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.offlineCauseReason`</p></li><li><p>Discard unchanged with heartbeat: `3h`</p></li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: Idle|<p>Returns true if all the executors of this computer are idle.</p>|Dependent item|jenkins.computer.idle[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.idle`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: Temporarily offline|<p>Returns true if this node is marked temporarily offline.</p>|Dependent item|jenkins.computer.temp_offline[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.temporarilyOffline`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: Available disk space|<p>The available disk space of $JENKINS_HOME on agent.</p>|Dependent item|jenkins.computer.disk_space[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitorData['hudson.node_monitors.DiskSpaceMonitor'].size`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: Available temp space|<p>The available disk space of the temporary directory. Java tools and tests/builds often create files in the temporary directory, and may not function properly if there's no available space.</p>|Dependent item|jenkins.computer.temp_space[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: Response time average|<p>The round trip network response time from the master to the agent</p>|Dependent item|jenkins.computer.response_time[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: Available physical memory|<p>The total physical memory of the system, available bytes.</p>|Dependent item|jenkins.computer.available_physical_memory[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: Available swap space|<p>Available swap space in bytes.</p>|Dependent item|jenkins.computer.available_swap_space[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: Total physical memory|<p>Total physical memory of the system, in bytes.</p>|Dependent item|jenkins.computer.total_physical_memory[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: Total swap space|<p>Total number of swap space in bytes.</p>|Dependent item|jenkins.computer.total_swap_space[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: Clock difference|<p>The clock difference between the master and nodes.</p>|Dependent item|jenkins.computer.clock_difference[{#DISPLAY_NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.monitorData['hudson.node_monitors.ClockMonitor'].diff`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>Custom multiplier: `0.001`</p></li></ul>|

### Trigger prototypes for Computers discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Jenkins: Computer [{#DISPLAY_NAME}]: Node is down|<p>Node down with reason: {{ITEM.LASTVALUE2}.regsub("(.*)",\1)}</p>|`last(/Jenkins by HTTP/jenkins.computer.state[{#DISPLAY_NAME}])=1 and length(last(/Jenkins by HTTP/jenkins.computer.offline.reason[{#DISPLAY_NAME}]))>0`|Average|**Depends on**:<br><ul><li>Jenkins: Service has no online nodes</li><li>Jenkins: Computer [{#DISPLAY_NAME}]: Node is temporarily offline</li></ul>|
|Jenkins: Computer [{#DISPLAY_NAME}]: Node is temporarily offline|<p>Node is temporarily Offline with reason: {{ITEM.LASTVALUE2}.regsub("(.*)",\1)}</p>|`last(/Jenkins by HTTP/jenkins.computer.temp_offline[{#DISPLAY_NAME}])=1 and length(last(/Jenkins by HTTP/jenkins.computer.offline.reason[{#DISPLAY_NAME}]))>0`|Info|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

