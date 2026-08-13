![](images/logo.png?raw=true)
# TOPdesk webhook

## Overview

This guide describes how to integrate your Zabbix installation with TOPdesk using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Requirements

Zabbix version: 7.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|severity_average|P3|Average severity.|
|severity_default|P5|Default severity.|
|severity_disaster|P1|Disaster severity.|
|severity_high|P2|High severity.|
|severity_information|P5|Information severity.|
|severity_not_classified|P5|Not classified severity.|
|severity_warning|P4|Warning severity.|
|tls_verify|\{$HTTP\.TLS\.VERIFY:"TOPdesk"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "TOPdesk", e.g. {$HTTP.TLS.VERIFY:"TOPdesk"}.|
|topdesk_api|\<put your TOPdesk API URL\>|TOPdesk API URL.|
|topdesk_password|\<put your TOPdesk application password\>|TOPdesk application password.|
|topdesk_status|\<put default status for new tickets\>|Default status for new TOPdesk tickets.|
|topdesk_user|\<put your TOPdesk username\>|TOPdesk username.|
|zbxurl|\{$ZABBIX\.URL\}|Current Zabbix URL.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|topdesk_issue_key|\{EVENT\.TAGS\.\_\_zbx\_tpd\_issuekey\}|TOPdesk issue key.|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Please create an **application password** according to the original instruction https://developers.topdesk.com/tutorial.html#show-collapse-usage-createAppPassword.

2\. Copy the **application password** of your new integration to use it in Zabbix.

## Zabbix configuration

The configuration consists of a _media type_ in Zabbix, which will invoke the webhook to send alerts to TOPdesk through the TOPdesk Rest API.

1\. Create a [global macro](https://www.zabbix.com/documentation/7.0/manual/config/macros/user_macros) {$ZABBIX.URL} with Zabbix frontend URL (for example http://192.168.7.123:8081)

[![](images/tn_1.png?raw=true)](images/1.png)

2\. [Import](https://www.zabbix.com/documentation/7.0/manual/web_interface/frontend_sections/alerts/mediatypes) the TOPdesk media type from file [media_topdesk.yaml](media_topdesk.yaml).

[![](images/tn_2.png?raw=true)](images/2.png)

3\. Change in the imported media the values of the variables topdesk_api (URL), topdesk_password, topdesk_user. The topdesk_status is the default status for creating a new TOPdesk ticket.

[![](images/tn_3.png?raw=true)](images/3.png)

For more information about the Zabbix Webhook configuration, please see the [documentation](https://www.zabbix.com/documentation/7.0/manual/config/notifications/media/webhook).

To utilize the media type, we recommend creating a dedicated [Zabbix user](https://www.zabbix.com/documentation/7.0/manual/web_interface/frontend_sections/administration/users) to represent TOPdesk. The default settings for TOPdesk User should suffice as this user will not be logging into Zabbix. Please note, that in order to be notified about problems on a host, this user must have at least read permissions for the host.  
When configuring alert action, add this user in the _Send to users_ field (in Operation details) - this will tell Zabbix to use TOPdesk webhook when sending notifications from this action. Use the TOPdesk User in any action of your choice. Text from "Action Operations" will be sent to "TOPdesk First Line Call Request" field when the problem happens. Text from "Action Recovery Operations" and "Action Update Operations" will be sent to "TOPdesk First Line Call Action" field when the problem is resolved or updated.

### Internal alerts
To receive notifications about internal problem events in TOPdesk: in the internal action configuration mark the Custom message checkbox and specify custom message templates for problem operations. Internal event recovery actions are not supported.
If an internal action operation is configured without a custom message, the notification will not be sent. 
Note, that this step is required only for notifications about internal events; for other event types specifying a custom message is optional. 

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.0/manual/config/notifications) and [TOPdesk](https://developers.topdesk.com/documentation/index.html) documentation.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
