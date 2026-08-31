![](images/logo.png?raw=true)
# SolarWinds Service Desk webhook

## Overview

This guide describes how to integrate Zabbix 7.0 installation with SolarWinds Service Desk using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.
Please note that recovery and update operations and SolarWinds Service Desk's custom fields are supported only for trigger-based events.

## Requirements

Zabbix version: 7.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|priority_average|Medium|Average priority.|
|priority_default|Low|Default priority.|
|priority_disaster|Critical|Disaster priority.|
|priority_high|High|High priority.|
|samanage_token|\<PUT YOUR TOKEN HERE\>|SolarWinds Service Desk API token.|
|samanage_url|\<PUT YOUR INSTANCE URL HERE\>|SolarWinds Service Desk instance URL.|
|tls_verify|\{$HTTP\.TLS\.VERIFY:"SolarWinds Service Desk"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "SolarWinds Service Desk", e.g. {$HTTP.TLS.VERIFY:"SolarWinds Service Desk"}.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_recovery_value|\{EVENT\.RECOVERY\.VALUE\}|Numeric value of the recovery event.|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|samanage_incident_id|\{EVENT\.TAGS\.\_\_zbx\_solarwinds\_inc\_id\}|Incident ID.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

Refer to the vendor documentation.

## Zabbix configuration

1\. Before setting up a SolarWinds Service Desk Webhook, it is recommended to setup the global macro "*{$ZABBIX.URL}*" containing an URL to the Zabbix frontend.
As an example, this macro can be used to populate SolarWinds Service Desk's custom field with URL to an event info or graph.

[![](images/thumb.1.png?raw=true)](images/1.png)

2\. In the *Administration > Media types* section, import the [media_solarwinds_servicedesk.yaml](media_solarwinds_servicedesk.yaml).

3\. Open the newly added **SolarWinds Service Desk** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.
The following parameters are required:
**samanage_url** - actual URL of your SolarWinds Service Desk instance,
**samanage_token** - API token (see [SolarWinds Service Desk tutorial](https://help.samanage.com/s/article/Tutorial-Tokens-Authentication-for-API-Integration-1536721557657) for more information).

4\. You can add the following parameters to customize SolarWinds Service Desk ticket:

- `priority_<severity>`: add this parameter for each Zabbix's severity or use only `priority_default`.
`priority_default` is mandatary.
Possible values of `<severity>`:
  - not_classified
  - information
  - warning
  - average
  - high
  - disaster

- `sw_field_<fieldname>`: add this to fill default SolarWinds Service Desk fields, where `<fieldname>` is a name of a field. The parameter can contain a simple value or a JSON string.
Name of the field and value should be consistent with [SolarWinds Service Desk API specification](https://documentation.solarwinds.com/en/Success_Center/swsd/Content/APIdocumentation/Incidents.htm).
_Example:_
[![](images/2.png?raw=true)](images/2.png)
Be careful to use user macro as a value, because special symbols such as quotes can make your JSON invalid.

- `sw_customfield_<fieldname>`: add this to fill preconfigured SolarWinds Service Desk custom field. `<fieldname>` is a name of a field and may contain whitespaces.
_Example:_
[![](images/3.png?raw=true)](images/3.png)

5\. Create a **Zabbix user** and add **Media** with the **SolarWinds Service Desk** media type. 
Though a "Send to" field is not used in SolarWinds Service Desk webhook, it cannot be empty. To comply with frontend requirements, you can put any symbol there.
Make sure this user has access to all hosts for which you would like problem notifications to be converted into SolarWinds Service Desk tickets.

For more information see [Zabbix](https://www.zabbix.com/documentation/7.0/manual/config/notifications) and [SOLARWINDS](https://documentation.solarwinds.com/en/Success_Center/swsd/Content/SWSD_Getting_Started_Guide.htm) documentations.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
