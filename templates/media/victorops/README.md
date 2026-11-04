![](images/logo.png?raw=true)
# VictorOps webhook

## Overview

> [!CAUTION]
> VictorOps has been acquired by Splunk and is now called "Splunk On-Call" - https://www.splunk.com/en_us/about-splunk/acquisitions/splunk-on-call.html
> Not sure if this integration still works with the new software. Should be tested.

This guide describes how to integrate Zabbix 8.0 installation with VictorOps using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.

## Requirements

Zabbix version: 8.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|event_info|\{$ZABBIX\.URL\}/tr\_events\.php?triggerid=\{TRIGGER\.ID\}&eventid=\{EVENT\.ID\}|Event info.|
|field:monitoring_tool|Zabbix|Monitoring tool.|
|priority_average|WARNING|Average priority.|
|priority_default|INFO|Default priority.|
|priority_disaster|CRITICAL|Disaster priority.|
|priority_high|WARNING|High priority.|
|priority_information|INFO|Information priority.|
|priority_not_classified|INFO|Not classified priority.|
|priority_resolved|OK|Resolved priority.|
|priority_update|INFO|Update priority.|
|priority_warning|INFO|Warning priority.|
|tls_verify|\{$HTTP\.TLS\.VERIFY:"VictorOps"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "VictorOps", e.g. {$HTTP.TLS.VERIFY:"VictorOps"}.|
|vops_endpoint|\<PLACE ENDPOINT URL HERE\>|VictorOps endpoint URL.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_recovery_value|\{EVENT\.RECOVERY\.VALUE\}|Numeric value of the recovery event.|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|field:entity_display_name|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|field:entity_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|field:hostname|\{HOST\.NAME\}|Visible host name.|
|field:operational_data|\{EVENT\.OPDATA\}|Operational data of the underlying trigger of a problem.|
|field:severity|\{EVENT\.SEVERITY\}|Name of the event severity.|
|field:state_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|field_p:trigger_description|\{TRIGGER\.DESCRIPTION\}|Trigger description.|
|field_r:event_duration|\{EVENT\.DURATION\}|Duration of the event (time difference between problem and recovery events), with precision down to a second.|
|field_r:recovery time|\{EVENT\.RECOVERY\.DATE\} \{EVENT\.RECOVERY\.TIME\}|Recovery datetime.|
|vops_routing_key|\{ALERT\.SENDTO\}|VictorOps routing key.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Go to *Integrations -> 3rd Party Integrations*

2\. Create *REST* integration. See VictorOps [documentation](https://help.victorops.com/knowledge-base/rest-endpoint-integration-guide/) for the information.

3\. Save endpoint URL from *URL to notify* field.

## Zabbix configuration

1\. In the *Administration > Media types* section, import [media_victorops.yaml](media_victorops.yaml).

2\. Open the newly added **VictorOps** media type and replace *&lt;PLACE ENDPOINT URL HERE&gt;* placeholder with your REST integration endpoint URL.
The following parameters should be filled:
**vops_endpoint** - URL of your VictorOps REST endpoint.
**vops_routing_key** - routing key of the escalation policy.

3\. The following parameters can help you customize the alerts ([documentation](https://help.victorops.com/knowledge-base/incident-fields-glossary/#glossary-of-fields) for the information):
**priority_severity** - value for the VictorOps *message_type* field. *severity* is the severity's name in the default Zabbix installation.
*priority_update* is a mandatory field set as *"INFO"* by default. If you want to create an incident for every event on update operation (include manual close), pass *"ACKNOWLEDGMENT"* as the value of this parameter.
*message_type* is used to determine the behavior of the alert when it arrives.
**field:Hostname** or **field_p:Hostname** - contains data for custom fields. "Field" parameters with another format or empty value will be ignored.
Format explanation:
- *field* - prefix of the parameter with field info.
- *p* - optional. Used if the field should be sent only on problem/recovery/update operation. Possible values:
    - *p* - problem
    - *r* - recovery
    - *u* - update
- *Host* - the title of the field. There can be any text that contains characters and "_" symbol. Whitespaces and special symbols are not allowed.

4\. Create a **Zabbix user** and add **Media** with the **VictorOps** media type.
"Send to" field should be filled as "Default" or your routing key.
Make sure this user has access to all hosts, for which you would like problem notifications to be converted into VictorOps tasks.

For more information see [Zabbix](https://www.zabbix.com/documentation/8.0/manual/config/notifications) and [VictorOps](https://help.victorops.com/) documentations.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
