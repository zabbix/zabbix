
# Acronis Cyber Protect Cloud by HTTP

## Overview

This template is designed for the effortless deployment of Acronis Cyber Protect Cloud monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Acronis Cloud Platform version 23.07

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

This is a master template that needs to be assigned to a host, and it will automatically create MSP host prototype, which will monitor Acronis Cyber Protect Cloud metrics.

Before using this template it is required to create a new MSP-level API client for Zabbix to use. To do that, sign into your Acronis Cyber Protect Cloud WEB interface, navigate to `Settings` -> `API clients` and create new API client.
You will be shown credentials for this API client. These credentials need to be entered in the following user macros of this template:

* `{$ACRONIS.CPC.AUTH.CLIENT.ID}` - enter `Client ID` here;

* `{$ACRONIS.CPC.AUTH.SECRET}` - enter `Secret` here;

* `{$ACRONIS.CPC.DATACENTER.URL}` - enter `Data center URL`

This is all the configuration needed for this integration.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ACRONIS.CPC.DATACENTER.URL}|<p>Acronis Cyber Protect Cloud datacenter URL, e.g., https://eu2-cloud.acronis.com.</p>||
|{$ACRONIS.CPC.AUTH.INTERVAL}|<p>API token regeneration interval, in minutes. By default, Acronis Cyber Protect Cloud tokens expire after 2 hours.</p>|`110m`|
|{$ACRONIS.CPC.HTTP.PROXY}|<p>Sets the HTTP proxy for the authorization item. Host prototypes will also use this value for HTTP proxy. If this parameter is empty, then no proxy is used.</p>||
|{$ACRONIS.CPC.AUTH.CLIENT.ID}|<p>Client ID for API user access.</p>||
|{$ACRONIS.CPC.AUTH.SECRET}|<p>Secret for API user access.</p>||
|{$ACRONIS.CPC.PATH.ACCOUNT.MANAGEMENT}|<p>Sub-path for the Account Management API.</p>|`/api/2`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Acronis CPC: Get access token|<p>Authorizes API user and receives access token.</p>|HTTP agent|acronis.cpc.account_manager.get_token<p>**Preprocessing**</p><ul><li><p>JavaScript: `The text is too long. Please see the template.`</p></li></ul>|

### LLD rule Acronis CPC: MSP Discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Acronis CPC: MSP Discovery|<p>Discovers MSP and creates host prototype based on that.</p>|Dependent item|acronis.cpc.lld.msp_discovery|

# Acronis Cyber Protect Cloud MSP by HTTP

## Overview

This template is designed for the effortless deployment of Acronis Cyber Protect Cloud MSP monitoring by Zabbix via HTTP and doesn't require any external scripts.

## Requirements

Zabbix version: 7.0 and higher.

## Tested versions

This template has been tested on:
- Acronis Cloud Platform version 23.07

## Configuration

> Zabbix should be configured according to the instructions in the [Templates out of the box](https://www.zabbix.com/documentation/7.0/manual/config/templates_out_of_the_box) section.

## Setup

This template is not meant to be used independently. A host with the `Acronis Cyber Protect Cloud by HTTP` template will request API token and automatically create a host prototype with this template assigned to it.

If needed, you can specify an HTTP proxy for the template to use by changing the value of `{$ACRONIS.CPC.HTTP.PROXY}` user macro.


### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ACRONIS.CPC.DATACENTER.URL}|<p>Acronis Cyber Protect Cloud datacenter URL, e.g., https://eu2-cloud.acronis.com.</p>||
|{$ACRONIS.CPC.HTTP.PROXY}|<p>Sets the HTTP proxy for the authorization item. Host prototypes will also use this value for HTTP proxy. If this parameter is empty, then no proxy is used.</p>||
|{$ACRONIS.CPC.CYBERFIT.WARN}|<p>CyberFit score threshold for "warning" severity trigger.</p>|`669`|
|{$ACRONIS.CPC.CYBERFIT.HIGH}|<p>CyberFit score threshold for "high" severity trigger.</p>|`579`|
|{$ACRONIS.CPC.DEVICE.RESOURCE.TYPE}|<p>Comma separated list of resource types for devices retrieval.</p>|`resource.machine`|
|{$ACRONIS.CPC.ALERT.DISCOVERY.CATEGORY.MATCHES}|<p>Sets the alert category regex filter to use in alert discovery for including.</p>|`.*`|
|{$ACRONIS.CPC.ALERT.DISCOVERY.CATEGORY.NOT_MATCHES}|<p>Sets the alert category regex filter to use in alert discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$ACRONIS.CPC.ALERT.DISCOVERY.SEVERITY.MATCHES}|<p>Sets the alert severity regex filter to use in alert discovery for including.</p>|`.*`|
|{$ACRONIS.CPC.ALERT.DISCOVERY.SEVERITY.NOT_MATCHES}|<p>Sets the alert severity regex filter to use in alert discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$ACRONIS.CPC.ALERT.DISCOVERY.RESOURCE.MATCHES}|<p>Sets the alert resource name regex filter to use in alert discovery for including.</p>|`.*`|
|{$ACRONIS.CPC.ALERT.DISCOVERY.RESOURCE.NOT_MATCHES}|<p>Sets the alert resource name regex filter to use in alert discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$ACRONIS.CPC.CUSTOMER.DISCOVERY.KIND.MATCHES}|<p>Sets the customer name regex filter to use in customer discovery for including.</p>|`customer`|
|{$ACRONIS.CPC.CUSTOMER.DISCOVERY.NAME.MATCHES}|<p>Sets the customer name regex filter to use in customer discovery for including.</p>|`.*`|
|{$ACRONIS.CPC.CUSTOMER.DISCOVERY.NAME.NOT_MATCHES}|<p>Sets the customer name regex filter to use in customer discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$ACRONIS.CPC.DEVICE.DISCOVERY.TENANT.MATCHES}|<p>Sets the tenant name regex filter to use in device discovery for including.</p>|`.*`|
|{$ACRONIS.CPC.DEVICE.DISCOVERY.TENANT.NOT_MATCHES}|<p>Sets the tenant name regex filter to use in device discovery for excluding.</p>|`CHANGE_IF_NEEDED`|
|{$ACRONIS.CPC.ACCESS_TOKEN}|<p>API access token.</p>||
|{$ACRONIS.CPC.PATH.ACCOUNT.MANAGEMENT}|<p>Sub-path for the Account Management API.</p>|`/api/2`|
|{$ACRONIS.CPC.PATH.RESOURCE.MANAGEMENT}|<p>Sub-path for the Resource Management API.</p>|`/api/resource_management/v4`|
|{$ACRONIS.CPC.PATH.ALERTS}|<p>Sub-path for the Alerts API.</p>|`/api/alert_manager/v1`|
|{$ACRONIS.CPC.PATH.AGENTS}|<p>Sub-path for the Agents API.</p>|`/api/agent_manager/v2`|
|{$ACRONIS.CPC.MSP.TENANT.UUID}|<p>UUID for MSP.</p>||

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Acronis CPC: Register integration|<p>Registers integration on Acronis services.</p>|Script|acronis.cpc.register.integration|
|Acronis CPC: Get alerts|<p>Fetches all alerts.</p>|HTTP agent|acronis.cpc.alerts.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.items`</p></li></ul>|
|Acronis CPC: Get customers|<p>Fetches all customers.</p>|HTTP agent|acronis.cpc.customers.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.items`</p></li></ul>|
|Acronis CPC: Get devices|<p>Fetches all devices.</p>|HTTP agent|acronis.cpc.devices.get<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.items`</p></li></ul>|
|Acronis CPC: Alerts with "ok" severity|<p>Gets count of alerts with "ok" severity.</p>|Dependent item|acronis.cpc.alerts.severity.ok<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..[?(@.severity == 'ok')].length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Acronis CPC: Alerts with "warning" severity|<p>Gets count of alerts with "warning" severity.</p>|Dependent item|acronis.cpc.alerts.severity.warn<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..[?(@.severity == 'warning')].length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Acronis CPC: Alerts with "error" severity|<p>Gets count of alerts with "error" severity.</p>|Dependent item|acronis.cpc.alerts.severity.err<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..[?(@.severity == 'error')].length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Acronis CPC: Alerts with "critical" severity|<p>Gets count of alerts with "critical" severity.</p>|Dependent item|acronis.cpc.alerts.severity.crit<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..[?(@.severity == 'critical')].length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Acronis CPC: Alerts with "information" severity|<p>Gets count of alerts with "information" severity.</p>|Dependent item|acronis.cpc.alerts.severity.info<p>**Preprocessing**</p><ul><li><p>JSON Path: `$..[?(@.severity == 'information')].length()`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Acronis CPC: Alerts discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Acronis CPC: Alerts discovery|<p>Discovers alerts.</p>|Dependent item|acronis.cpc.alerts.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Acronis CPC: Alerts discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Alert [{#TYPE}]:[{#ALERT_ID}]: Alert severity|<p>Severity for the alert.</p>|Dependent item|acronis.cpc.alert.severity[{#ALERT_ID}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.id == "{#ALERT_ID}")].severity.first()`</p><p>⛔️Custom on fail: Set error to: `Could not find alert severity`</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Acronis CPC: Alerts discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Alert [{#TYPE}]:[{#ALERT_ID}]: Alert has "critical" severity|<p>Alert has "critical" severity.</p>|`last(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.alert.severity[{#ALERT_ID}])=3`|High|**Manual close**: Yes|
|Alert [{#TYPE}]:[{#ALERT_ID}]: Alert has "error" severity|<p>Alert has "error" severity.</p>|`last(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.alert.severity[{#ALERT_ID}])=2`|Average|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Alert [{#TYPE}]:[{#ALERT_ID}]: Alert has "critical" severity</li></ul>|
|Alert [{#TYPE}]:[{#ALERT_ID}]: Alert has "warning" severity|<p>Alert has "warning" severity.</p>|`last(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.alert.severity[{#ALERT_ID}])=1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Alert [{#TYPE}]:[{#ALERT_ID}]: Alert has "error" severity</li></ul>|

### LLD rule Acronis CPC: Customer discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Acronis CPC: Customer discovery|<p>Discovers customers.</p>|Dependent item|acronis.cpc.customer.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Acronis CPC: Customer discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Customer [{#NAME}]: Enabled status|<p>Enabled status for customer (true or false).</p>|Dependent item|acronis.cpc.customer.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$[?(@.name == "{#NAME}")].enabled.first()`</p><p>⛔️Custom on fail: Set error to: `Could not find customer status`</p></li><li>Boolean to decimal</li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### LLD rule Acronis CPC: Device discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Acronis CPC: Device discovery|<p>Discovers devices.</p>|Dependent item|acronis.cpc.device.discovery<p>**Preprocessing**</p><ul><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Item prototypes for Acronis CPC: Device discovery

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|Device [{#NAME}]:[{#ID}]: Raw data resources status|<p>Gets statuses for device resources.</p>|HTTP agent|acronis.cpc.device.res.status.raw[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.items[0]`</p><p>⛔️Custom on fail: Set error to: `Could not parse resource status data`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: CyberFit score|<p>Acronis "CyberFit" score for the device. Value of "-1" is assigned if "CyberFit" could not be found for device.</p>|Dependent item|acronis.cpc.device.cyberfit[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set value to: `-1`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Agent version|<p>Agent version for the device.</p>|Dependent item|acronis.cpc.device.agent.version[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set error to: `Could not parse agent version`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Agent enabled|<p>Agent status (enabled or disabled) for the device.</p>|Dependent item|acronis.cpc.device.agent.enabled[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set error to: `Could not parse agent status`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Agent online|<p>Agent reachability for the device.</p>|Dependent item|acronis.cpc.device.agent.online[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Set error to: `Could not parse agent reachability status`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Protection status|<p>Protection status for device.</p>|Dependent item|acronis.cpc.device.protection.status[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aggregate.status`</p><p>⛔️Custom on fail: Set error to: `Could not parse protection status`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Protection plan name|<p>Protection plan name for device.</p>|Dependent item|acronis.cpc.device.protection.name[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `$.aggregate.names`</p><p>⛔️Custom on fail: Set error to: `Could not parse protection plan name`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Previous successful antimalware protection scan|<p>Previous successful antimalware protection scan for device.</p>|Dependent item|acronis.cpc.device.protection.scan.prev.ok[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Previous antimalware protection scan|<p>Previous antimalware protection scan for device.</p>|Dependent item|acronis.cpc.device.protection.scan.prev[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Next antimalware protection scan|<p>Next scheduled antimalware protection scan for device.</p>|Dependent item|acronis.cpc.device.protection.scan.next[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Previous successful machine backup run|<p>Previous successful machine backup run for device.</p>|Dependent item|acronis.cpc.device.backup.prev.ok[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Previous machine backup run|<p>Previous machine backup run for device.</p>|Dependent item|acronis.cpc.device.backup.prev[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Next machine backup run|<p>Next scheduled machine backup run for device.</p>|Dependent item|acronis.cpc.device.backup.next[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Previous successful vulnerability assessment|<p>Previous successful vulnerability assessment for device.</p>|Dependent item|acronis.cpc.device.vuln.prev.ok[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Previous vulnerability assessment|<p>Previous vulnerability assessment for device.</p>|Dependent item|acronis.cpc.device.vuln.prev[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Next vulnerability assessment|<p>Next scheduled vulnerability assessment for device.</p>|Dependent item|acronis.cpc.device.vuln.next[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Previous successful patch management run|<p>Previous successful patch management run for device.</p>|Dependent item|acronis.cpc.device.patch.prev.ok[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Previous patch management run|<p>Previous patch management run for device.</p>|Dependent item|acronis.cpc.device.patch.prev[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|
|Device [{#NAME}]:[{#ID}]: Next patch management run|<p>Next scheduled patch management run for device.</p>|Dependent item|acronis.cpc.device.patch.next[{#NAME}]<p>**Preprocessing**</p><ul><li><p>JSON Path: `The text is too long. Please see the template.`</p><p>⛔️Custom on fail: Discard value</p></li><li><p>JavaScript: `The text is too long. Please see the template.`</p></li><li><p>Discard unchanged with heartbeat: `1h`</p></li></ul>|

### Trigger prototypes for Acronis CPC: Device discovery

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|Device [{#NAME}]:[{#ID}]: CyberFit score critical|<p>CyberFit score for this device is critical for at least 3 minutes.</p>|`min(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.cyberfit[{#NAME}],3m) < {$ACRONIS.CPC.CYBERFIT.HIGH} and max(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.cyberfit[{#NAME}],3m) <> -1`|High|**Manual close**: Yes|
|Device [{#NAME}]:[{#ID}]: CyberFit score low|<p>CyberFit score for this device is low for at least 3 minutes.</p>|`min(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.cyberfit[{#NAME}],3m) < {$ACRONIS.CPC.CYBERFIT.WARN} and max(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.cyberfit[{#NAME}],3m) <> -1`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Device [{#NAME}]:[{#ID}]: CyberFit score critical</li></ul>|
|Device [{#NAME}]:[{#ID}]: Agent disabled|<p>Agent for this device is disabled for at least 3 minutes.</p>|`max(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.agent.enabled[{#NAME}],3m) < 1`|Info|**Manual close**: Yes|
|Device [{#NAME}]:[{#ID}]: Protection status "error"|<p>Device has "error" protection status.</p>|`last(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.protection.status[{#NAME}])="error"`|Average|**Manual close**: Yes|
|Device [{#NAME}]:[{#ID}]: Protection status "warning"|<p>Device has "warning" protection status.</p>|`last(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.protection.status[{#NAME}])="warning"`|Warning|**Manual close**: Yes<br>**Depends on**:<br><ul><li>Device [{#NAME}]:[{#ID}]: Protection status "error"</li></ul>|
|Device [{#NAME}]:[{#ID}]: Previous protection scan not successful|<p>Device has "error" protection status.</p>|`last(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.protection.scan.prev.ok[{#NAME}])<>last(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.protection.scan.prev[{#NAME}])`|Average|**Manual close**: Yes|
|Device [{#NAME}]:[{#ID}]: Scheduled antimalware scan failed to run|<p>Scheduled antimalware scan failed to run for at least 3 minutes.</p>|`max(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.protection.scan.next[{#NAME}],3m) < now()`|Warning|**Manual close**: Yes|
|Device [{#NAME}]:[{#ID}]: Previous machine backup run not successful|<p>Previous machine backup did not run successfully.</p>|`max(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.backup.prev.ok[{#NAME}],1m)<>max(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.backup.prev[{#NAME}],1m)`|Average|**Manual close**: Yes|
|Device [{#NAME}]:[{#ID}]: Scheduled machine backup failed to run|<p>Scheduled machine backup failed to run.</p>|`last(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.backup.next[{#NAME}]) < now()`|Warning|**Manual close**: Yes|
|Device [{#NAME}]:[{#ID}]: Previous vulnerability assessment not successful|<p>Previous vulnerability assessment did not run successfully.</p>|`max(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.vuln.prev.ok[{#NAME}],1m)<>max(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.vuln.prev[{#NAME}],1m)`|Average|**Manual close**: Yes|
|Device [{#NAME}]:[{#ID}]: Scheduled vulnerability assessment failed to run|<p>Scheduled vulnerability assessment failed to run.</p>|`last(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.vuln.next[{#NAME}]) < now()`|Warning|**Manual close**: Yes|
|Device [{#NAME}]:[{#ID}]: Previous patch management run not successful|<p>Previous patch management run did not run successfully.</p>|`max(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.patch.prev.ok[{#NAME}],1m)<>max(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.patch.prev[{#NAME}],1m)`|Average|**Manual close**: Yes|
|Device [{#NAME}]:[{#ID}]: Scheduled patch management failed to run|<p>Scheduled patch management failed to run.</p>|`last(/Acronis Cyber Protect Cloud MSP by HTTP/acronis.cpc.device.patch.next[{#NAME}]) < now()`|Warning|**Manual close**: Yes|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

