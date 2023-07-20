
# Veeam Backup and Replication by HTTP

## Overview

This template is designed to monitor Veeam Backup and Replication.
It works without any external scripts and uses the script item.

***NOTE:*** Since the RESTful API may not be available for some editions, the template will only work with the following editions of Veeam Backup and Replication:

1. Veeam Universal License (VUL) editions:
* Foundation
* Advanced
* Premium

2. Veeam Socket License editions:
* Enterprise Plus Socket  

> See [Veeam Data Platform Feature Comparison](https://www.veeam.com/licensing-pricing.html) for more details.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Veeam Backup and Replication, version 11.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a user to monitor the service or use an existing read-only account.
> See [Veeam Help Center](https://helpcenter.veeam.com/docs/backup/vbr_rest/reference/vbr-rest-v1-rev2.html?ver=110#tag/Login/operation/CreateToken!path=grant_type&t=request) for more details. 
2. Link the template to a host.
3. Configure the following macros: `{$VEEAM.API.URL}`, `{$VEEAM.USER}`, and `{$VEEAM.PASSWORD}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VEEAM.API.URL}|<p>The Veeam API endpoint is a URL in the format `<scheme>://<host>:<port>`.</p>|`https://localhost:9419`|
|{$VEEAM.HTTP.PROXY}|<p>Sets the HTTP proxy to `http_proxy` value. If this parameter is empty, then no proxy is used.</p>||
|{$VEEAM.PASSWORD}|<p>The `password` of the Veeam Backup and Replication account. It is used to obtain an access token.</p>||
|{$VEEAM.USER}|<p>The `username` of the Veeam Backup and Replication account. It is used to obtain an access token.</p>||
|{$VEEAM.DATA.TIMEOUT}|<p>A response timeout for the API.</p>|`10`|
|{$CREATED.AFTER}|<p>Returns sessions that are created after chosen days.</p>|`7`|
|{$SESSION.NAME.MATCHES}|<p>This macro is used in discovery rule to evaluate sessions.</p>|`.*`|
|{$SESSION.NAME.NOT_MATCHES}|<p>This macro is used in discovery rule to evaluate sessions.</p>|`CHANGE_IF_NEEDED`|
|{$SESSION.TYPE.MATCHES}|<p>This macro is used in discovery rule to evaluate sessions.</p>|`.*`|
|{$SESSION.TYPE.NOT_MATCHES}|<p>This macro is used in discovery rule to evaluate sessions.</p>|`CHANGE_IF_NEEDED`|
|{$PROXIES.NAME.MATCHES}|<p>This macro is used in proxies discovery rule.</p>|`.*`|
|{$PROXIES.NAME.NOT_MATCHES}|<p>This macro is used in proxies discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$PROXIES.TYPE.MATCHES}|<p>This macro is used in proxies discovery rule.</p>|`.*`|
|{$PROXIES.TYPE.NOT_MATCHES}|<p>This macro is used in proxies discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$REPOSITORIES.NAME.MATCHES}|<p>This macro is used in repositories discovery rule.</p>|`.*`|
|{$REPOSITORIES.NAME.NOT_MATCHES}|<p>This macro is used in repositories discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$REPOSITORIES.TYPE.MATCHES}|<p>This macro is used in repositories discovery rule.</p>|`.*`|
|{$REPOSITORIES.TYPE.NOT_MATCHES}|<p>This macro is used in repositories discovery rule.</p>|`CHANGE_IF_NEEDED`|
|{$JOB.NAME.MATCHES}|<p>This macro is used in discovery rule to evaluate the states of jobs.</p>|`.*`|
|{$JOB.NAME.NOT_MATCHES}|<p>This macro is used in discovery rule to evaluate the states of jobs.</p>|`CHANGE_IF_NEEDED`|
|{$JOB.TYPE.MATCHES}|<p>This macro is used in discovery rule to evaluate the states of jobs.</p>|`.*`|
|{$JOB.TYPE.NOT_MATCHES}|<p>This macro is used in discovery rule to evaluate the states of jobs.</p>|`CHANGE_IF_NEEDED`|
|{$JOB.STATUS.MATCHES}|<p>This macro is used in discovery rule to evaluate the states of jobs.</p>|`.*`|
|{$JOB.STATUS.NOT_MATCHES}|<p>This macro is used in discovery rule to evaluate the states of jobs.</p>|`CHANGE_IF_NEEDED`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Veeam: Get metrics|<p>The result of API requests is expressed in the JSON.</p>|Script|veeam.get.metrics|
|Veeam: Get errors|<p>The errors from API requests.</p>|Dependent item|veeam.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Veeam Backup and Replication by HTTP/veeam.get.errors))>0`|Average||

### LLD rule Proxies discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxies discovery|<p>Discovery of proxies.</p>|Dependent item|veeam.proxies.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.proxies.data`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Proxies discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Veeam: Server [{#NAME}]: Get data|<p>Gets raw data collected by the proxy server.</p>|Dependent item|veeam.proxy.server.raw[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.managedServers.data.[?(@.id=='{#HOSTID}')].first()`</p></li></ul>|
|Veeam: Proxy [{#NAME}] [{#TYPE}]: Get data|<p>Gets raw data collected by the proxy with the name `[{#NAME}]`, `[{#TYPE}]`.</p>|Dependent item|veeam.proxy.raw[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.proxies.data.[?(@.id=='{#ID}')].first()`</p></li></ul>|
|Veeam: Proxy [{#NAME}] [{#TYPE}]: Max Task Count|<p>The maximum number of concurrent tasks.</p>|Dependent item|veeam.proxy.maxtask[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.server.maxTaskCount`</p></li></ul>|
|Veeam: Proxy [{#NAME}] [{#TYPE}]: Host name|<p>The name of the proxy server.</p>|Dependent item|veeam.proxy.server.name[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.name`</p></li></ul>|
|Veeam: Proxy [{#NAME}] [{#TYPE}]: Host type|<p>The type of the proxy server.</p>|Dependent item|veeam.proxy.server.type[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.type`</p></li></ul>|

### LLD rule Repositories discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Repositories discovery|<p>Discovery of repositories.</p>|Dependent item|veeam.repositories.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.repositories_states.data`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Repositories discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Veeam: Repository [{#NAME}] [{#TYPE}]: Get data|<p>Gets raw data from repository with the name: `[{#NAME}]`, `[{#TYPE}]`.</p>|Dependent item|veeam.repositories.raw[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.repositories_states.data.[?(@.id=='{#ID}')].first()`</p></li></ul>|
|Veeam: Repository [{#NAME}] [{#TYPE}]: Used space [{#PATH}]|<p>Used space by repositories expressed in gigabytes (GB).</p>|Dependent item|veeam.repository.capacity[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usedSpaceGB`</p></li></ul>|
|Veeam: Repository [{#NAME}] [{#TYPE}]: Free space [{#PATH}]|<p>Free space of repositories expressed in gigabytes (GB).</p>|Dependent item|veeam.repository.free.space[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.freeGB`</p></li></ul>|

### LLD rule Sessions discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sessions discovery|<p>Discovery of sessions.</p>|Dependent item|veeam.sessions.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sessions.data`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Sessions discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Veeam: Session [{#NAME}] [{#TYPE}]: Get data|<p>Gets raw data from session with the name: `[{#NAME}]`, `[{#TYPE}]`.</p>|Dependent item|veeam.sessions.raw[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sessions.data.[?(@.id=='{#ID}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Veeam: Session [{#NAME}] [{#TYPE}]: State|<p>The state of the session. The enums used: `Stopped`, `Starting`, `Stopping`, `Working`, `Pausing`, `Resuming`, `WaitingTape`, `Idle`, `Postprocessing`, `WaitingRepository`, `WaitingSlot`.</p>|Dependent item|veeam.sessions.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li></ul>|
|Veeam: Session [{#NAME}] [{#TYPE}]: Result|<p>The result of the session. The enums used: `None`, `Success`, `Warning`, `Failed`.</p>|Dependent item|veeam.sessions.result[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.result.result`</p></li></ul>|
|Veeam: Session [{#NAME}] [{#TYPE}]: Message|<p>A message that explains the session result.</p>|Dependent item|veeam.sessions.message[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.result.message`</p></li></ul>|
|Veeam: Session progress percent [{#NAME}] [{#TYPE}]|<p>The progress of the session expressed as percentage.</p>|Dependent item|veeam.sessions.progress.percent[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.progressPercent`</p></li></ul>|

### Trigger prototypes for Sessions discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam: Last result session failed||`find(/Veeam Backup and Replication by HTTP/veeam.sessions.result[{#ID}],,"like","Failed")=1`|Average|**Manual close**: Yes|

### LLD rule Jobs states discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Jobs states discovery|<p>Discovery of the jobs states.</p>|Dependent item|veeam.job.state.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.jobs_states.data`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Jobs states discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Veeam: Job states [{#NAME}] [{#TYPE}]: Get data|<p>Gets raw data from the job states with the name `[{#NAME}]`.</p>|Dependent item|veeam.jobs.states.raw[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.jobs_states.data.[?(@.id=='{#ID}')].first()`</p></li></ul>|
|Veeam: Job states [{#NAME}] [{#TYPE}]: Status|<p>The current status of the job. The enums used: `running`, `inactive`, `disabled`.</p>|Dependent item|veeam.jobs.status[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li></ul>|
|Veeam: Job states [{#NAME}] [{#TYPE}]: Last result|<p>The result of the session. The enums used: `None`, `Success`, `Warning`, `Failed`.</p>|Dependent item|veeam.jobs.last.result[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastResult`</p></li></ul>|

### Trigger prototypes for Jobs states discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam: Last result job failed||`find(/Veeam Backup and Replication by HTTP/veeam.jobs.last.result[{#ID}],,"like","Failed")=1`|Average|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

