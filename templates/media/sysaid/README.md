![](images/logo.png?raw=true)
# SysAid webhook

## Overview

This guide describes how to integrate your Zabbix installation with SysAid using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Requirements

Zabbix version: 7.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|sysaid_auth_password|\<PUT YOUR USER PASSWORD\>|SysAid user password.|
|sysaid_auth_user|\<PUT YOUR USER NAME\>|SysAid user name.|
|sysaid_category_level_1|\<PUT YOUR CATEGORY\>|SysAid category level 1.|
|sysaid_category_level_2|\<PUT YOUR SUB\-CATEGORY\>|SysAid category level 2.|
|sysaid_category_level_3|\<PUT YOUR THIRD LEVEL CATEGORY\>|SysAid category level 3.|
|sysaid_default_priority_id|1|SysAid default priority ID.|
|sysaid_incident_state|1|SysAid incident state.|
|sysaid_template_id|\<PUT YOUR TEMPLATE ID\>|SysAid template ID.|
|sysaid_urgency_id|\<PUT YOUR URGENCY ID\>|SysAid urgency ID.|
|sysaid_url|\<PUT YOUR SYSAID URL\>|SysAid URL.|
|tls_verify|\{$HTTP\.TLS\.VERIFY:"SysAid"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "SysAid", e.g. {$HTTP.TLS.VERIFY:"SysAid"}.|
|zabbix_url|\{$ZABBIX\.URL\}|Current Zabbix URL.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_update_message|\{EVENT\.UPDATE\.MESSAGE\}|Problem update message.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|sysaid_incident_id|\{EVENT\.TAGS\.\_\_zbx\_sysaid\_incident\_id\}|SysAid incident ID.|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1. Create a user in SysAid for ticket creation
2. Configure Incident templates in Settings -> Service Desk templates -> Incident templates
3. Configure category in Settings -> Service Desk -> Categories (Category, Subcategory and Third level category will be used during ticket creation)

## Zabbix configuration

The configuration consists of a _media type_ in Zabbix, which will invoke the webhook to send alerts to SysAid through the Sysaid API.

You will need to configure the following fields in Sysaid webhook

* sysaid_auth_user = Username
* sysaid_auth_password = Password 
* sysaid_category_level_1 = Category (Example: Basic Software)
* sysaid_category_level_2 = Subcategory (Example: Adobe Reader)
* sysaid_category_level_3 = Third level category (Example: Does not work properly)
* sysaid_template_id = Configured template id (Example: 10)
* sysaid_urgency_id = Your selected urgency id (Example: 1)
* sysaid_url = Sysaid URL (Example: https://sysaid10577.sysaidit.com/)

For more information about the Zabbix Webhook configuration, please see the [documentation](https://www.zabbix.com/documentation/7.0/manual/config/notifications/media/webhook).

To utilize the media type, we recommend creating a dedicated [Zabbix user](https://www.zabbix.com/documentation/7.0/manual/web_interface/frontend_sections/administration/users) to represent SysAid. The default settings for SysAid User should suffice as this user will not be logging into Zabbix. Please note, that in order to be notified about problems on a host, this user must have at least read permissions for the host.  

### Internal alerts
To receive notifications about internal problem and recovery events in SysAid: in the internal action configuration mark the Custom message checkbox and specify custom message templates for problem and recovery operations. 
If an internal action operation is configured without a custom message, the notification will not be sent. 
Note, that this step is required only for notifications about internal events; for other event types specifying a custom message is optional. For other even types message templates still should be defined on media type level.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.0/manual/config/notifications) and [SysAid](http://cdn1.SysAid.com/SysAidUserManual.pdf) documentation.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
