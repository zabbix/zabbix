
# Veeam Backup and Replication by HTTP

## Overview

This template is designed to monitor Veeam Backup and Replication version 11.0.
It works without any external scripts and uses the script item.

## Requirements

For Zabbix version: 6.2 and higher.

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/6.2/manual/config/templates_out_of_the_box/http) for basic instructions.

1. Create a user to monitor the service or use an existing read-only account.
> See [Veeam Help Center](https://helpcenter.veeam.com/docs/backup/vbr_rest/reference/vbr-rest-v1-rev2.html?ver=110#tag/Login/operation/CreateToken!path=grant_type&t=request) for more details. 
2. Link the template to a host.
3. Configure macros `{$VEEAM.API.URL}`, `{$VEEAM.USER}`, and `{$VEEAM.PASSWORD}`.

## Configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CREATED.AFTER} |<p>Returns sessions that are created after chosen days.</p> |`7` |
|{$JOB.NAME.MATCHES} |<p>This macro is used in discovery rule to evaluate the states of jobs.</p> |`.*` |
|{$JOB.NAME.NOT_MATCHES} |<p>This macro is used in discovery rule to evaluate the states of jobs.</p> |`CHANGE_IF_NEEDED` |
|{$JOB.STATUS.MATCHES} |<p>This macro is used in discovery rule to evaluate the states of jobs.</p> |`.*` |
|{$JOB.STATUS.NOT_MATCHES} |<p>This macro is used in discovery rule to evaluate the states of jobs.</p> |`CHANGE_IF_NEEDED` |
|{$JOB.TYPE.MATCHES} |<p>This macro is used in discovery rule to evaluate the states of jobs.</p> |`.*` |
|{$JOB.TYPE.NOT_MATCHES} |<p>This macro is used in discovery rule to evaluate the states of jobs.</p> |`CHANGE_IF_NEEDED` |
|{$PROXIES.NAME.MATCHES} |<p>This macro is used in proxies discovery rule.</p> |`.*` |
|{$PROXIES.NAME.NOT_MATCHES} |<p>This macro is used in proxies discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$PROXIES.TYPE.MATCHES} |<p>This macro is used in proxies discovery rule.</p> |`.*` |
|{$PROXIES.TYPE.NOT_MATCHES} |<p>This macro is used in proxies discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$REPOSITORIES.NAME.MATCHES} |<p>This macro is used in repositories discovery rule.</p> |`.*` |
|{$REPOSITORIES.NAME.NOT_MATCHES} |<p>This macro is used in repositories discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$REPOSITORIES.TYPE.MATCHES} |<p>This macro is used in repositories discovery rule.</p> |`.*` |
|{$REPOSITORIES.TYPE.NOT_MATCHES} |<p>This macro is used in repositories discovery rule.</p> |`CHANGE_IF_NEEDED` |
|{$SESSION.NAME.MATCHES} |<p>This macro is used in discovery rule to evaluate sessions.</p> |`.*` |
|{$SESSION.NAME.NOT_MATCHES} |<p>This macro is used in discovery rule to evaluate sessions.</p> |`CHANGE_IF_NEEDED` |
|{$SESSION.TYPE.MATCHES} |<p>This macro is used in discovery rule to evaluate sessions.</p> |`.*` |
|{$SESSION.TYPE.NOT_MATCHES} |<p>This macro is used in discovery rule to evaluate sessions.</p> |`CHANGE_IF_NEEDED` |
|{$VEEAM.API.URL} |<p>The Veeam API endpoint is a URL in the format `<scheme>://<host>:<port>`.</p> |`https://localhost:9419` |
|{$VEEAM.DATA.TIMEOUT} |<p>A response timeout for the API.</p> |`10` |
|{$VEEAM.HTTP.PROXY} |<p>Sets the HTTP proxy to `http_proxy` value. If this parameter is empty, then no proxy is used.</p> |`` |
|{$VEEAM.PASSWORD} |<p>The `password` of the Veeam Backup and Replication account. It is used to obtain an access token.</p> |`` |
|{$VEEAM.USER} |<p>The `username` of the Veeam Backup and Replication account. It is used to obtain an access token.</p> |`` |

### Template links

There are no template links in this template.

### Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|Jobs states discovery |<p>Discovery of the jobs states.</p> |DEPENDENT |veeam.job.state.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.jobs_states.data`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `{$JOB.TYPE.MATCHES}`</p><p>- {#TYPE} NOT_MATCHES_REGEX `{$JOB.TYPE.NOT_MATCHES}`</p><p>- {#NAME} MATCHES_REGEX `{$JOB.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$JOB.NAME.NOT_MATCHES}`</p><p>- {#JOB.STATUS} MATCHES_REGEX `{$JOB.STATUS.MATCHES}`</p><p>- {#JOB.STATUS} NOT_MATCHES_REGEX `{$JOB.STATUS.NOT_MATCHES}`</p> |
|Proxies discovery |<p>Discovery of proxies.</p> |DEPENDENT |veeam.proxies.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.proxies.data`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `{$PROXIES.TYPE.MATCHES}`</p><p>- {#TYPE} NOT_MATCHES_REGEX `{$PROXIES.TYPE.NOT_MATCHES}`</p><p>- {#NAME} MATCHES_REGEX `{$PROXIES.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$PROXIES.NAME.NOT_MATCHES}`</p> |
|Repositories discovery |<p>Discovery of repositories.</p> |DEPENDENT |veeam.repositories.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.repositories_states.data`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `{$REPOSITORIES.TYPE.MATCHES}`</p><p>- {#TYPE} NOT_MATCHES_REGEX `{$REPOSITORIES.TYPE.NOT_MATCHES}`</p><p>- {#NAME} MATCHES_REGEX `{$REPOSITORIES.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$REPOSITORIES.NAME.NOT_MATCHES}`</p> |
|Sessions discovery |<p>Discovery of sessions.</p> |DEPENDENT |veeam.sessions.discovery<p>**Preprocessing**:</p><p>- JSONPATH: `$.sessions.data`</p><p>- JAVASCRIPT<p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p><p>**Filter**:</p>AND <p>- {#TYPE} MATCHES_REGEX `{$SESSION.TYPE.MATCHES}`</p><p>- {#TYPE} NOT_MATCHES_REGEX `{$SESSION.TYPE.NOT_MATCHES}`</p><p>- {#NAME} MATCHES_REGEX `{$SESSION.NAME.MATCHES}`</p><p>- {#NAME} NOT_MATCHES_REGEX `{$SESSION.NAME.NOT_MATCHES}`</p> |

### Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|Veeam |Veeam: Get metrics |<p>The result of API requests is expressed in the JSON.</p> |SCRIPT |veeam.get.metrics<p>**Expression**:</p>`The text is too long. Please see the template.` |
|Veeam |Veeam: Get errors |<p>The errors from API requests.</p> |DEPENDENT |veeam.get.errors<p>**Preprocessing**:</p><p>- JSONPATH: `$.error`</p><p>⛔️ON_FAIL: `CUSTOM_VALUE -> `</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Veeam |Veeam: Server [{#NAME}]: Get data |<p>Gets raw data collected by the proxy server.</p> |DEPENDENT |veeam.proxy.server.raw[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.managedServers.data.[?(@.id=='{#HOSTID}')].first()`</p> |
|Veeam |Veeam: Proxy [{#NAME}] [{#TYPE}]: Get data |<p>Gets raw data collected by the proxy with the name `[{#NAME}]`, `[{#TYPE}]`.</p> |DEPENDENT |veeam.proxy.raw[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.proxies.data.[?(@.id=='{#ID}')].first()`</p> |
|Veeam |Veeam: Proxy [{#NAME}] [{#TYPE}]: Max Task Count |<p>The maximum number of concurrent tasks.</p> |DEPENDENT |veeam.proxy.maxtask[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.server.maxTaskCount`</p> |
|Veeam |Veeam: Proxy [{#NAME}] [{#TYPE}]: Host name |<p>The name of the proxy server.</p> |DEPENDENT |veeam.proxy.server.name[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.name`</p> |
|Veeam |Veeam: Proxy [{#NAME}] [{#TYPE}]: Host type |<p>The type of the proxy server.</p> |DEPENDENT |veeam.proxy.server.type[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.type`</p> |
|Veeam |Veeam: Repository [{#NAME}] [{#TYPE}]: Get data |<p>Gets raw data from repository with the name: `[{#NAME}]`, `[{#TYPE}]`.</p> |DEPENDENT |veeam.repositories.raw[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.repositories_states.data.[?(@.id=='{#ID}')].first()`</p> |
|Veeam |Veeam: Repository [{#NAME}] [{#TYPE}]: Used space [{#PATH}] |<p>Used space by repositories expressed in gigabytes (GB).</p> |DEPENDENT |veeam.repository.capacity[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.usedSpaceGB`</p> |
|Veeam |Veeam: Repository [{#NAME}] [{#TYPE}]: Free space [{#PATH}] |<p>Free space of repositories expressed in gigabytes (GB).</p> |DEPENDENT |veeam.repository.free.space[{#NAME}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.freeGB`</p> |
|Veeam |Veeam: Session [{#NAME}] [{#TYPE}]: Get data |<p>Gets raw data from session with the name: `[{#NAME}]`, `[{#TYPE}]`.</p> |DEPENDENT |veeam.sessions.raw[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.sessions.data.[?(@.id=='{#ID}')].first()`</p><p>⛔️ON_FAIL: `DISCARD_VALUE -> `</p> |
|Veeam |Veeam: Session [{#NAME}] [{#TYPE}]: State |<p>The state of the session. The enums used: `Stopped`, `Starting`, `Stopping`, `Working`, `Pausing`, `Resuming`, `WaitingTape`, `Idle`, `Postprocessing`, `WaitingRepository`, `WaitingSlot`.</p> |DEPENDENT |veeam.sessions.state[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.state`</p> |
|Veeam |Veeam: Session [{#NAME}] [{#TYPE}]: Result |<p>The result of the session. The enums used: `None`, `Success`, `Warning`, `Failed`.</p> |DEPENDENT |veeam.sessions.result[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.result.result`</p> |
|Veeam |Veeam: Session [{#NAME}] [{#TYPE}]: Message |<p>A message that explains the session result.</p> |DEPENDENT |veeam.sessions.message[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.result.message`</p> |
|Veeam |Veeam: Session progress percent [{#NAME}] [{#TYPE}] |<p>The progress of the session expressed as percentage.</p> |DEPENDENT |veeam.sessions.progress.percent[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.progressPercent`</p> |
|Veeam |Veeam: Job states [{#NAME}] [{#TYPE}]: Get data |<p>Gets raw data from the job states with the name `[{#NAME}]`.</p> |DEPENDENT |veeam.jobs.states.raw[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.jobs_states.data.[?(@.id=='{#ID}')].first()`</p> |
|Veeam |Veeam: Job states [{#NAME}] [{#TYPE}]: Status |<p>The current status of the job. The enums used: `running`, `inactive`, `disabled`.</p> |DEPENDENT |veeam.jobs.status[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.status`</p> |
|Veeam |Veeam: Job states [{#NAME}] [{#TYPE}]: Last result |<p>The result of the session. The enums used: `None`, `Success`, `Warning`, `Failed`.</p> |DEPENDENT |veeam.jobs.last.result[{#ID}]<p>**Preprocessing**:</p><p>- JSONPATH: `$.lastResult`</p> |

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|Veeam: There are errors in requests to API |<p>Zabbix has received errors in response to API requests.</p> |`length(last(/Veeam Backup and Replication by HTTP/veeam.get.errors))>0` |AVERAGE | |
|Veeam: Last result session failed |<p>-</p> |`find(/Veeam Backup and Replication by HTTP/veeam.sessions.result[{#ID}],,"like","Failed")=1` |AVERAGE |<p>Manual close: YES</p> |
|Veeam: Last result job failed |<p>-</p> |`find(/Veeam Backup and Replication by HTTP/veeam.jobs.last.result[{#ID}],,"like","Failed")=1` |AVERAGE |<p>Manual close: YES</p> |

## Feedback

Please report any issues with the template at https://support.zabbix.com.

You can also provide feedback, discuss the template, or ask for help at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/).

