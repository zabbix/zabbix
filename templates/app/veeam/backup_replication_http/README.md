
# Veeam Backup and Replication by HTTP

## Overview

This template is designed to monitor Veeam Backup and Replication.
It works without any external scripts and uses the script item.

***NOTE:*** The native Veeam Backup & Replication REST API (port 9419) is available only in editions that include REST API functionality.

Supported editions include:

1. Veeam Universal License (VUL) editions:
* Foundation
* Advanced
* Premium

2. Socket-based editions:
* Enterprise
* Enterprise Plus

3. Community Edition
* Supported with workload limitations defined by Veeam licensing.

> See [Veeam Data Platform Feature Comparison](https://www.veeam.com/licensing-pricing.html) for more details.

## Requirements

Zabbix version: 7.4 and higher.

## Tested versions

This template has been tested on:
- Veeam Backup and Replication, version 13.0

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.4/manual/config/templates_out_of_the_box) section.

## Setup

1. Create a user to monitor the service or use an existing read-only account.
> See [Veeam Help Center](https://helpcenter.veeam.com/references/vbr/13/rest/1.3-rev1/tag/SectionOverview#section/Authorization-and-Security) for more details.
2. Link the template to a host.
3. Configure the following macros: `{$VEEAM.API.URL}`, `{$VEEAM.API.VERSION}`, `{$VEEAM.USER}`, and `{$VEEAM.PASSWORD}`.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$VEEAM.API.URL}|<p>The Veeam API endpoint is a URL in the format `<scheme>://<host>:<port>`.</p>|`https://localhost:9419`|
|{$VEEAM.API.VERSION}|<p>The REST API revision.</p>|`1.3-rev1`|
|{$VEEAM.HTTP.PROXY}|<p>Sets the HTTP proxy to `http_proxy` value. If this parameter is empty, then no proxy is used.</p>||
|{$VEEAM.PASSWORD}|<p>The `password` of the Veeam Backup and Replication account. It is used to obtain an access token.</p>||
|{$VEEAM.USER}|<p>The `username` of the Veeam Backup and Replication account. It is used to obtain an access token.</p>||
|{$VEEAM.DATA.FREQUENCY}|<p>The update interval for the get metrics item.</p>|`1m`|
|{$VEEAM.SECURITY.DATA.FREQUENCY}|<p>The update interval for the security analyzer best practices item.</p>|`4h`|
|{$VEEAM.DATA.TIMEOUT}|<p>A response timeout for the API.</p>|`30`|
|{$CREATED.AFTER}|<p>Returns sessions that are created after chosen days.</p>|`7`|
|{$SESSION.NAME.MATCHES}|<p>Filter of discoverable sessions by name.</p>|`.*`|
|{$SESSION.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable sessions by name.</p>|`CHANGE_IF_NEEDED`|
|{$SESSION.TYPE.MATCHES}|<p>Filter of discoverable sessions by type.</p>|`.*`|
|{$SESSION.TYPE.NOT_MATCHES}|<p>Filter to exclude discoverable sessions by type.</p>|`CHANGE_IF_NEEDED`|
|{$SESSION.RESULT.MATCHES}|<p>Filter of discoverable sessions by result.</p>|`.*`|
|{$SESSION.RESULT.NOT_MATCHES}|<p>Filter to exclude discoverable sessions by result.</p>|`Success`|
|{$PROXIES.NAME.MATCHES}|<p>Filter of discoverable proxies by name.</p>|`.*`|
|{$PROXIES.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable proxies by name.</p>|`CHANGE_IF_NEEDED`|
|{$PROXIES.TYPE.MATCHES}|<p>Filter of discoverable proxies by type.</p>|`.*`|
|{$PROXIES.TYPE.NOT_MATCHES}|<p>Filter to exclude discoverable proxies by type.</p>|`CHANGE_IF_NEEDED`|
|{$REPOSITORIES.NAME.MATCHES}|<p>Filter of discoverable repositories by name.</p>|`.*`|
|{$REPOSITORIES.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable repositories by name.</p>|`CHANGE_IF_NEEDED`|
|{$REPOSITORIES.TYPE.MATCHES}|<p>Filter of discoverable repositories by type.</p>|`.*`|
|{$REPOSITORIES.TYPE.NOT_MATCHES}|<p>Filter to exclude discoverable repositories by type.</p>|`CHANGE_IF_NEEDED`|
|{$JOB.NAME.MATCHES}|<p>Filter of discoverable jobs by name.</p>|`.*`|
|{$JOB.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable jobs by name.</p>|`CHANGE_IF_NEEDED`|
|{$JOB.TYPE.MATCHES}|<p>Filter of discoverable jobs by type.</p>|`.*`|
|{$JOB.TYPE.NOT_MATCHES}|<p>Filter to exclude discoverable jobs by type.</p>|`CHANGE_IF_NEEDED`|
|{$JOB.STATUS.MATCHES}|<p>Filter of discoverable jobs by status.</p>|`.*`|
|{$JOB.STATUS.NOT_MATCHES}|<p>Filter to exclude discoverable jobs by status.</p>|`CHANGE_IF_NEEDED`|
|{$VEEAM.LICENSE.EXPIRY.WARN}|<p>Number of days until the license expires.</p>|`7`|
|{$MALWARE_EVENTS.TYPE.MATCHES}|<p>Filter of discoverable malware events by type.</p>|`.*`|
|{$MALWARE_EVENTS.TYPE.NOT_MATCHES}|<p>Filter to exclude discoverable malware events by type.</p>|`CHANGE_IF_NEEDED`|
|{$MALWARE_EVENTS.SEVERITY.MATCHES}|<p>Filter of discoverable malware events by severity.</p>|`.*`|
|{$MALWARE_EVENTS.SEVERITY.NOT_MATCHES}|<p>Filter to exclude discoverable malware events by severity.</p>|`CHANGE_IF_NEEDED`|
|{$MALWARE_EVENTS.STATE.MATCHES}|<p>Filter of discoverable malware events by state.</p>|`.*`|
|{$MALWARE_EVENTS.STATE.NOT_MATCHES}|<p>Filter to exclude discoverable malware events by state.</p>|`CHANGE_IF_NEEDED`|
|{$SECURITY_ANALYZER.STATUS.MATCHES}|<p>Filter of discoverable security best practices by status.</p>|`.*`|
|{$SECURITY_ANALYZER.STATUS.NOT_MATCHES}|<p>Filter to exclude discoverable security best practices by status.</p>|`OK`|
|{$SECURITY_ANALYZER.BEST_PRACTICE.MATCHES}|<p>Filter of discoverable security best practices by name.</p>|`.*`|
|{$SECURITY_ANALYZER.BEST_PRACTICE.NOT_MATCHES}|<p>Filter to exclude discoverable security best practices by name.</p>|`CHANGE_IF_NEEDED`|
|{$AUTH_EVENTS.NAME.MATCHES}|<p>Filter of discoverable authorization events by name.</p>|`.*`|
|{$AUTH_EVENTS.NAME.NOT_MATCHES}|<p>Filter to exclude discoverable authorization events by name.</p>|`CHANGE_IF_NEEDED`|
|{$AUTH_EVENTS.STATE.MATCHES}|<p>Filter of discoverable authorization events by state.</p>|`.*`|
|{$AUTH_EVENTS.STATE.NOT_MATCHES}|<p>Filter to exclude discoverable authorization events by state.</p>|`CHANGE_IF_NEEDED`|
|{$VEEAM.REPOSITORY.SPACE.WARN}|<p>Repository space warning threshold, expressed in %.</p>|`80`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get metrics|<p>The result of API requests is returned as JSON.</p>|Script|veeam.get.metrics|
|Get Security Analyzer results|<p>Authenticates with the Veeam API and retrieves security analyzer best practices data.</p>|HTTP agent|veeam.security.analyzer.get<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Get errors|<p>The errors from API requests.</p>|Dependent item|veeam.get.errors<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|License: Status|<p>The status of the license.</p>|Dependent item|veeam.license.status<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.license.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|License: Expiration date|<p>The expiration date of the license.</p>|Dependent item|veeam.license.expiration<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.license.expirationDate`</p></li><li><p>JavaScript: `return Math.floor(Date.parse(value) / 1000);`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|License: Licensed instances|<p>The number of licensed instances.</p>|Dependent item|veeam.license.licensed.instances<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.license.instanceLicenseSummary.licensedInstancesNumber`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|License: Used instances|<p>The number of used instances.</p>|Dependent item|veeam.license.used.instances<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.license.instanceLicenseSummary.usedInstancesNumber`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam Backup: There are errors in requests to API|<p>Zabbix has received errors in response to API requests.</p>|`length(last(/Veeam Backup and Replication by HTTP/veeam.get.errors))>0`|Average||
|Veeam Backup: License is expired|<p>The Veeam license is expired.</p>|`last(/Veeam Backup and Replication by HTTP/veeam.license.status)=1`|Average||
|Veeam Backup: License is invalid|<p>The Veeam license is invalid.</p>|`last(/Veeam Backup and Replication by HTTP/veeam.license.status)=2`|Warning||
|Veeam Backup: License expires soon|<p>The Veeam license expires soon.</p>|`(last(/Veeam Backup and Replication by HTTP/veeam.license.expiration) - now()) / 86400 < {$VEEAM.LICENSE.EXPIRY.WARN} and last(/Veeam Backup and Replication by HTTP/veeam.license.expiration) > now()`|Warning|**Manual close**: Yes|
|Veeam Backup: Used instances exceed the licensed count|<p>The number of used instances is more than the number of licensed instances.</p>|`last(/Veeam Backup and Replication by HTTP/veeam.license.used.instances)>last(/Veeam Backup and Replication by HTTP/veeam.license.licensed.instances)`|Average||

### LLD rule Proxies discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Proxies discovery|<p>Discovery of proxies.</p>|Dependent item|veeam.proxies.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.proxies.data`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Proxies discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Server [{#NAME}]: Get data|<p>Gets raw data collected by the proxy server.</p>|Dependent item|veeam.proxy.server.raw[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.managedServers.data.[?(@.id=='{#HOST.ID}')].first()`</p></li></ul>|
|Proxy [{#NAME}]: Get data|<p>Gets raw data collected by the proxy with the name `[{#NAME}]`, `[{#TYPE}]`.</p>|Dependent item|veeam.proxy.raw[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.proxies.data.[?(@.id=='{#ID}')].first()`</p></li></ul>|
|Proxy [{#NAME}]: Max Task Count|<p>The maximum number of concurrent tasks.</p>|Dependent item|veeam.proxy.max.task[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.server.maxTaskCount`</p></li></ul>|
|Proxy [{#NAME}]: Host name|<p>The name of the proxy server.</p>|Dependent item|veeam.proxy.server.name[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.name`</p></li></ul>|
|Proxy [{#NAME}]: Host type|<p>The type of the proxy server.</p>|Dependent item|veeam.proxy.server.type[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.type`</p></li></ul>|
|Proxy [{#NAME}]: Status|<p>The status of the proxy server.</p>|Dependent item|veeam.proxy.server.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.proxies_states.data.[?(@.id=='{#ID}')].isOnline.first()`</p></li><li>Boolean to decimal</li></ul>|

### Trigger prototypes for Proxies discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam Backup: Proxy [{#NAME}] is offline|<p>Proxy `[{#NAME}]` is offline.</p>|`min(/Veeam Backup and Replication by HTTP/veeam.proxy.server.status[{#NAME}],5m)=0`|High||

### LLD rule Repositories discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Repositories discovery|<p>Discovery of repositories.</p>|Dependent item|veeam.repositories.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.repositories_states.data`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Repositories discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Repository [{#NAME}]: Path [{#PATH}]: Get data|<p>Gets raw data from repository with the name: `[{#NAME}]`, `[{#TYPE}]`.</p>|Dependent item|veeam.repositories.raw[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.repositories_states.data.[?(@.id=='{#ID}')].first()`</p></li></ul>|
|Repository [{#NAME}]: Path [{#PATH}]: Capacity|<p>The total capacity of the repository.</p>|Dependent item|veeam.repository.capacity.space[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.capacityGB`</p></li><li><p>Custom multiplier: `1073741824`</p></li></ul>|
|Repository [{#NAME}]: Path [{#PATH}]: Used space|<p>The used space in the repository.</p>|Dependent item|veeam.repository.used.space[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.usedSpaceGB`</p></li><li><p>Custom multiplier: `1073741824`</p></li></ul>|
|Repository [{#NAME}]: Path [{#PATH}]: Free space|<p>The free space in the repository.</p>|Dependent item|veeam.repository.free.space[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.freeGB`</p></li><li><p>Custom multiplier: `1073741824`</p></li></ul>|
|Repository [{#NAME}]: Path [{#PATH}]: Utilization space|<p>The percentage of repository capacity in use.</p>|Calculated|veeam.repository.utilization[{#ID}]|
|Repository [{#NAME}]: Path [{#PATH}]: Is Out Of Date|<p>Indicates whether the repository is out of date.</p>|Dependent item|veeam.repository.out_of_date[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.isOutOfDate`</p></li><li>Boolean to decimal</li></ul>|
|Repository [{#NAME}]: Path [{#PATH}]: State|<p>The online/offline state of the repository.</p>|Dependent item|veeam.repository.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.isOnline`</p></li><li>Boolean to decimal</li></ul>|

### Trigger prototypes for Repositories discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam Backup: Repository [{#NAME}] utilization is high|<p>Utilization of the repository `[{#NAME}]` is high.</p>|`min(/Veeam Backup and Replication by HTTP/veeam.repository.utilization[{#ID}],5m)>{$VEEAM.REPOSITORY.SPACE.WARN}`|High||
|Veeam Backup: Repository [{#NAME}] is out of date|<p>Repository `[{#NAME}]` is out of date.</p>|`last(/Veeam Backup and Replication by HTTP/veeam.repository.out_of_date[{#ID}])=1`|Warning||
|Veeam Backup: Repository [{#NAME}] is offline|<p>Repository `[{#NAME}]` is offline.</p>|`min(/Veeam Backup and Replication by HTTP/veeam.repository.state[{#ID}],5m)=0`|High||

### LLD rule Sessions discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Sessions discovery|<p>Discovery of sessions.</p>|Dependent item|veeam.sessions.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sessions.data`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Sessions discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Session [{#NAME}] [{#TYPE}]: Get data|<p>Gets raw data from session with the name: `[{#NAME}]`, `[{#TYPE}]`.</p>|Dependent item|veeam.sessions.raw[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.sessions.data.[?(@.id=='{#ID}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Session [{#NAME}] [{#TYPE}]: State|<p>The state of the session. The enums used: `Stopped`, `Starting`, `Stopping`, `Working`, `Pausing`, `Resuming`, `WaitingTape`, `Idle`, `Postprocessing`, `WaitingRepository`, `WaitingSlot`.</p>|Dependent item|veeam.sessions.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Session [{#NAME}] [{#TYPE}]: Result|<p>The result of the session. The enums used: `None`, `Success`, `Warning`, `Failed`.</p>|Dependent item|veeam.sessions.result[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.result.result`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Session [{#NAME}] [{#TYPE}]: Message|<p>A message that explains the session result.</p>|Dependent item|veeam.sessions.message[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.result.message`</p></li></ul>|
|Session [{#NAME}] [{#TYPE}]: Progress percent|<p>The progress of the session expressed as percentage.</p>|Dependent item|veeam.sessions.progress.percent[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.progressPercent`</p></li></ul>|

### Trigger prototypes for Sessions discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam Backup: Last result session failed|<p>The last result of the session `[{#NAME}]` is failed.</p>|`last(/Veeam Backup and Replication by HTTP/veeam.sessions.result[{#ID}])=4`|Average|**Manual close**: Yes|

### LLD rule Jobs states discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Jobs states discovery|<p>Discovery of the jobs states.</p>|Dependent item|veeam.job.state.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.jobs_states.data`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Jobs states discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Job states [{#NAME}] [{#TYPE}]: Get data|<p>Gets raw data from the job states with the name `[{#NAME}]`.</p>|Dependent item|veeam.jobs.states.raw[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.jobs_states.data.[?(@.id=='{#ID}')].first()`</p></li></ul>|
|Job states [{#NAME}] [{#TYPE}]: Status|<p>The current status of the job. The enums used: `Running`, `Inactive`, `Disabled`, `Enabled`, `Stopping`, `Stopped`, `Starting`.</p>|Dependent item|veeam.jobs.status[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.status`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|Job states [{#NAME}] [{#TYPE}]: Last result|<p>The last run result of the job. The enums used: `None`, `Success`, `Warning`, `Failed`.</p>|Dependent item|veeam.jobs.last.result[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lastResult`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### Trigger prototypes for Jobs states discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam Backup: Last result job failed|<p>The last run result of the job `[{#NAME}]` is failed.</p>|`last(/Veeam Backup and Replication by HTTP/veeam.jobs.last.result[{#ID}])=4`|Average|**Manual close**: Yes|

### LLD rule Malware events discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Malware events discovery|<p>Discovery of malware events.</p>|Dependent item|veeam.malware.events.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.malware_events.data`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Malware events discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Malware event [{#MACHINE}]: Get data|<p>Gets raw data from malware event with the ID `[{#ID}]`.</p>|Dependent item|veeam.malware.event.get[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.malware_events.data.[?(@.id=='{#ID}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Malware event [{#MACHINE}]: Severity|<p>The severity level of the malware event. The enums used: `Clean`, `Suspicious`, `Infected`, `Informative`.</p>|Dependent item|veeam.malware.event.severity[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.severity`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|
|Malware event [{#MACHINE}]: State|<p>The state of the malware event. The enums used: `Created`, `FalsePositive`.</p>|Dependent item|veeam.malware.event.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Malware events discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam Backup: Malware infection detected on [{#MACHINE}]|<p>A malware infection has been detected on the machine `[{#MACHINE}]`. The malware event ID is `{#ID}`.</p>|`last(/Veeam Backup and Replication by HTTP/veeam.malware.event.severity[{#ID}])=3`|High|**Manual close**: Yes|
|Veeam Backup: Suspicious malware activity detected on [{#MACHINE}]|<p>Suspicious malware activity has been detected on the machine `[{#MACHINE}]`. The malware event ID is `{#ID}`.</p>|`last(/Veeam Backup and Replication by HTTP/veeam.malware.event.severity[{#ID}])=2`|Average|**Manual close**: Yes|

### LLD rule Authorization events discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Authorization events discovery|<p>Discovery of authorization events.</p>|Dependent item|veeam.authorization.events.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.authorization_events.data`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Authorization events discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Authorization event [{#NAME}]: Get data|<p>Gets raw data from authorization event with the ID `[{#ID}]`.</p>|Dependent item|veeam.authorization.event.get[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.authorization_events.data.[?(@.id=='{#ID}')].first()`</p><p>⛔️Custom on fail: Discard value</p></li></ul>|
|Authorization event [{#NAME}]: State|<p>The state of the authorization event. The enums used: `Pending`, `Approved`, `Rejected`, `Expired`, `Info`, `CockpitMessage`.</p>|Dependent item|veeam.authorization.event.state[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.state`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Authorization events discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam Backup: Authorization event [{#NAME}] is rejected|<p>The authorization event `[{#NAME}]` has been rejected.</p>|`last(/Veeam Backup and Replication by HTTP/veeam.authorization.event.state[{#ID}])=3`|High|**Manual close**: Yes|

### LLD rule Security analyzer discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Security analyzer discovery|<p>Discovery of security best practices compliance checks.</p>|Dependent item|veeam.security.analyzer.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.items`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Item prototypes for Security analyzer discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Security [{#BEST_PRACTICE}]: Status|<p>The compliance status of the security best practice. The enums used: `Analyzing`, `None`, `OK`, `Suppressed`, `UnableToCheck`, `Violation`.</p>|Dependent item|veeam.security.best.practice.status[{#ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.items.[?(@.id=='{#ID}')].status.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `6h`</p></li></ul>|

### Trigger prototypes for Security analyzer discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Veeam Backup: Security best practice violation: [{#BEST_PRACTICE}]|<p>The security best practice `[{#BEST_PRACTICE}]` has a violation status.</p>|`last(/Veeam Backup and Replication by HTTP/veeam.security.best.practice.status[{#ID}])=6`|High|**Manual close**: Yes|
|Veeam Backup: Security best practice unable to check: [{#BEST_PRACTICE}]|<p>The security best practice `[{#BEST_PRACTICE}]` could not be checked.</p>|`last(/Veeam Backup and Replication by HTTP/veeam.security.best.practice.status[{#ID}])=5`|Warning|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

