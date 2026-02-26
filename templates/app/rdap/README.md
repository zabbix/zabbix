
# Domain RDAP by HTTP

## Overview

This template monitors domain registration and related information using RDAP (Registration Data Access Protocol) over HTTP. It retrieves data such as expiration dates, registrar information, status, and more for the specified domains. The template also includes low-level discovery (LLD) rules to automatically discover additional information like notices and entities associated with each domain.

## Requirements

Zabbix version: 8.0 and higher.

## Tested versions

This template has been tested on:
- Tested on `zabbix.com` domain.

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/8.0/manual/config/templates_out_of_the_box) section.

## Setup

Set values for the `macros` according to your monitoring requirements.
* `{$RDAP.DOMAINS}`. **Required**. Enter one or more domains, separated by commas (e.g., example.com,test.net).
* `{$RDAP.INTERVAL.TLD.CHECK}` (Optional) TLD data does not change frequently. The default value of 12h (12 hours) or even 1d (1 day) is optimal to reduce the load on the Zabbix server and external RDAP services.
* `{$RDAP.INTERVAL.DOMAIN.CHECK}` (Optional) Use 1h as the standard. Checking too frequently may cause rate-limiting by RDAP services.

### Notes
* Accepts seconds or time unit with suffix (e.g., 30s, 1m, 2h, 1d) and, optionally, one or more custom intervals, all separated by semicolons.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$RDAP.DOMAINS}|<p>List of domains separated by commas.</p>||
|{$RDAP.LLD.ADDITIONAL.INFO.ENABLED}|<p>Enable discovery additional information in LLD (1 - enabled, 0 - disabled).</p>|`1`|
|{$RDAP.EXPIRATION.WARNING.DAYS}|<p>Number of days before expiration to trigger a warning.</p>|`7`|
|{$RDAP.NOT.UPDATED.WARNING.DAYS}|<p>Number of days after last update to trigger a warning.</p>|`7`|
|{$RDAP.INTERVAL.TLD.CHECK}|<p>Interval for checking TLD registration data.</p>|`12h`|
|{$RDAP.INTERVAL.DOMAIN.CHECK}|<p>Interval for checking domain registration data.</p>|`1h`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Get registration data for domains|<p>Get registration data for domains using RDAP protocol over HTTP.</p>|Script|rdap.domain.info|
|Error retrieving registration data for domains|<p>Error message when retrieving registration data for domains.</p>|Dependent item|rdap.domain.info.error<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>JSON Path: `$.error`</p><p>⛔️Custom on fail: Set value to: `Error retrieving registration data.`</p></li></ul>|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RDAP: Error retrieving registration data for domains||`last(/Domain RDAP by HTTP/rdap.domain.info.error)<>""`|High||

### LLD rule Discovery domains with RDAP server

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Discovery domains with RDAP server|<p>Discover domains using RDAP protocol over HTTP.</p>|Dependent item|rdap.domain.with.rdap.discovery<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.data`</p></li></ul>|

### Item prototypes for Discovery domains with RDAP server

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#DOMAIN}]: Get data|<p>Get registration data for domain `{#DOMAIN}` using RDAP protocol over HTTP.</p>|HTTP agent|rdap.domain.data[{#DOMAIN}]<p>**Preprocessing**</p><ul><li><p>Check for error in JSON: `$.errorCode`</p><p>⛔️Custom on fail: Set value to: `{"status": ["Error get data"], "lld_notices": [], "lld_entities": []}`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Check for not supported value: `any error`</p><p>⛔️Custom on fail: Set value to: `{"status": ["Error get data"], "lld_notices": [], "lld_entities": []}`</p></li></ul>|
|[{#DOMAIN}]: Expiration date|<p>Expiration date for domain `{#DOMAIN}`.</p>|Dependent item|rdap.domain.expiration[{#DOMAIN}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>JSON Path: `$.events[?(@.eventAction=="expiration")].eventDate.first()`</p><p>⛔️Custom on fail: Set value to: `1970-01-01T00:00:00Z`</p></li><li><p>JavaScript: `return Math.floor(Date.parse(value) / 1000);<br>`</p></li></ul>|
|[{#DOMAIN}]: Days until expiration|<p>Days until expiration for domain `{#DOMAIN}`.</p>|Dependent item|rdap.domain.registrar.until[{#DOMAIN}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|
|[{#DOMAIN}]: Registrar|<p>Registrar for domain `{#DOMAIN}`.</p>|Dependent item|rdap.domain.registrar[{#DOMAIN}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>JSON Path: `$.events[?(@.eventAction=="registration")].eventDate.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `return Math.floor(Date.parse(value) / 1000);<br>`</p></li></ul>|
|[{#DOMAIN}]: Last changed|<p>Last changed date for domain `{#DOMAIN}`.</p>|Dependent item|rdap.domain.changed[{#DOMAIN}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>JSON Path: `$.events[?(@.eventAction=="last changed")].eventDate.first()`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `return Math.floor(Date.parse(value) / 1000);<br>`</p></li></ul>|
|[{#DOMAIN}]: Last update of RDAP data|<p>Last update date of RDAP data for domain `{#DOMAIN}`.</p>|Dependent item|rdap.domain.updated[{#DOMAIN}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `return Math.floor(Date.parse(value) / 1000);<br>`</p></li></ul>|
|[{#DOMAIN}]: Status|<p>Status of domain `{#DOMAIN}`.</p>|Dependent item|rdap.domain.status[{#DOMAIN}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>JSON Path: `$.status`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `return JSON.parse(value).join(', ');`</p></li></ul>|
|[{#DOMAIN}]: Authority RDAP server|<p>Authority RDAP server for `{#TLD}` domain.</p>|Dependent item|rdap.domain.authority[{#DOMAIN}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li><li><p>JSON Path: `$.data[?(@.domain=="{#DOMAIN}")].rdap_server.first()`</p><p>⛔️Custom on fail: Set value to: ``</p></li></ul>|

### Trigger prototypes for Discovery domains with RDAP server

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|RDAP: [{#DOMAIN}] will expire soon|<p>Until domain `{#DOMAIN}` expiration left is less than {$RDAP.EXPIRATION.WARNING.DAYS:"{#DOMAIN}"} days.</p>|`last(/Domain RDAP by HTTP/rdap.domain.registrar.until[{#DOMAIN}]) < {$RDAP.EXPIRATION.WARNING.DAYS:"{#DOMAIN}"}`|High|**Depends on**:<br><ul><li>RDAP: [{#DOMAIN}] is expired</li><li>RDAP: [{#DOMAIN}] expiration date is unknown</li></ul>|
|RDAP: [{#DOMAIN}] is expired|<p>Domain `{#DOMAIN}` expiration date is in the past.</p>|`last(/Domain RDAP by HTTP/rdap.domain.registrar.until[{#DOMAIN}]) = -1`|High|**Depends on**:<br><ul><li>RDAP: [{#DOMAIN}] expiration date is unknown</li></ul>|
|RDAP: [{#DOMAIN}] expiration date is unknown|<p>Expiration date not found for domain `{#DOMAIN}`.</p>|`last(/Domain RDAP by HTTP/rdap.domain.registrar.until[{#DOMAIN}]) = -2`|Average|**Depends on**:<br><ul><li>RDAP: [{#DOMAIN}] not exist</li></ul>|
|RDAP: [{#DOMAIN}] was changed recently|<p>Domain `{#DOMAIN}` was changed in the last 7 days. Acknowledge to close the problem manually.</p>|`(now() - last(/Domain RDAP by HTTP/rdap.domain.changed[{#DOMAIN}])) < 604800`|Info|**Manual close**: Yes|
|RDAP: [{#DOMAIN}] not updated data for {$RDAP.NOT.UPDATED.WARNING.DAYS:"{#DOMAIN}"} days|<p>RDAP data for domain `{#DOMAIN}` not updated for {$RDAP.NOT.UPDATED.WARNING.DAYS:"{#DOMAIN}"} days. Acknowledge to close the problem manually.</p>|`(now() - last(/Domain RDAP by HTTP/rdap.domain.updated[{#DOMAIN}])) / 86400 > {$RDAP.NOT.UPDATED.WARNING.DAYS:"{#DOMAIN}"}`|Info|**Manual close**: Yes|
|RDAP: [{#DOMAIN}] status was changed recently|<p>Domain `{#DOMAIN}` was changed recently. Acknowledge to close the problem manually.</p>|`length(last(/Domain RDAP by HTTP/rdap.domain.status[{#DOMAIN}]))>0 and change(/Domain RDAP by HTTP/rdap.domain.status[{#DOMAIN}])=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>RDAP: [{#DOMAIN}] not exist</li></ul>|
|RDAP: [{#DOMAIN}] not exist|<p>Status information not found for domain `{#DOMAIN}`. The domain may not be registered or there was an error retrieving the data.</p>|`last(/Domain RDAP by HTTP/rdap.domain.status[{#DOMAIN}])="Error get data"`|Average||
|RDAP: [{#DOMAIN}] authority RDAP server not found|<p>The domain is not registered, there's a typo in your query, <br>or the initial bootstrap service is unable to locate <br>the correct server to direct the request to.</p>|`last(/Domain RDAP by HTTP/rdap.domain.authority[{#DOMAIN}])=""`|Warning||

### LLD rule Discovery notices for domain [{#DOMAIN}]

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Discovery notices for domain [{#DOMAIN}]|<p>Discover notices for domain `{#DOMAIN}`.</p>|Dependent item|rdap.domain.notices.discovery[{#DOMAIN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lld_notices`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li></ul>|

### Item prototypes for Discovery notices for domain [{#DOMAIN}]

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#DOMAIN}]: Notice [{#NOTICE_TITLE}]|<p>{#NOTICE_DESCRIPTION}</p>|Script|rdap.domain.notice.title[{#DOMAIN},{#NOTICE_TITLE}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

### LLD rule Discovery entities for domain [{#DOMAIN}]

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Discovery entities for domain [{#DOMAIN}]|<p>Discover entities for domain `{#DOMAIN}`.</p>|Dependent item|rdap.domain.entities.discovery[{#DOMAIN}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.lld_entities`</p><p>⛔️Custom on fail: Set value to: `[]`</p></li></ul>|

### Item prototypes for Discovery entities for domain [{#DOMAIN}]

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|[{#DOMAIN}]: {#ENTITY_VCARD_NAME}|<p>Information about entity `{#ENTITY_ROLE} {#ENTITY_SUB_ROLE}`.</p>|Script|rdap.domain.entity.vcard.entry[{#DOMAIN},{#ENTITY_VCARD_KEY}]<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1d`</p></li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

