![](images/logo.png?raw=true)
# Line webhook

## Overview

This guide describes how to integrate your Zabbix installation with LINE messenger using Zabbix webhook feature. It also provides instructions on setting up a media type, a user and an action in Zabbix.

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|bot_token|\<PLACE BOT TOKEN\>||
|tls_verify|\{$HTTP\.TLS\.VERIFY:"Line"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "Line", e.g. {$HTTP.TLS.VERIFY:"Line"}.|
|zabbix_url|\{$ZABBIX\.URL\}|some description|

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
|send_to|\{ALERT\.SENDTO\}|'Send to' value from user media configuration.|
|trigger_description|\{TRIGGER\.DESCRIPTION\}|Trigger description.|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1. Create a messaging `channel access token` following original instructions on [How to use the messaging API](https://developers.line.biz/en/docs/messaging-api/overview/).

2. Copy the `channel access token` of your new integration to be used in Zabbix.

## Zabbix configuration

The configuration consists of a _Media type_ in Zabbix, which will invoke the webhook to send alerts to LINE messenger through the LINE messaging API.

1. Create a global macro `{$ZABBIX.URL}` following these instructions in [Zabbix documentation](https://www.zabbix.com/documentation/7.4/manual/config/macros/user_macros) with Zabbix frontend URL - for example, `http://192.168.7.123:8081`.

[![](images/tn_1.png?raw=true)](images/1.png)

2. Import LINE media type from this file [media_line.yaml](media_line.yaml) following these instructions in [Zabbix documentation](https://www.zabbix.com/documentation/7.4/manual/web_interface/frontend_sections/administration/mediatypes). 

[![](images/tn_2.png?raw=true)](images/2.png)

3. Change the value of the variable `bot_token` to the `channel access token`.

For more information on Zabbix webhook configuration, see [Zabbix documentation](https://www.zabbix.com/documentation/7.4/manual/config/notifications/media/webhook).

4. Set _Media type_ `LINE` for each user you would like to get notified and fill _Send to_ field with the corresponding ID of the target recipient. Use a `userId`, `groupId`, or `roomId` value. See [Common properties in webhook event objects](https://developers.line.biz/en/reference/messaging-api/#common-properties) for more information.

See more details on creating [Zabbix user](https://www.zabbix.com/documentation/7.4/manual/web_interface/frontend_sections/users/user_list).

LINE user should suffice the default settings as this user will not be logging into Zabbix. Note that in order to be notified about problems on a host, this user must have at least read permissions for this host.  
When configuring an alert action, add this user in the _Send to users_ field (in _Operation_ details) - this will tell Zabbix to use LINE webhook when sending notifications from this action.
Use the LINE user in any actions of your choice.

### Testing
Media testing can be done manually, from `Media types` page. Press `Test` button opposite to the previously defined media type, under _Actions_.
1. To create a problem, following fields should be set:
    * `alert_message` = Test message
    * `alert_subject` = Test subject
    * `bot_token` = `Channel access token`
    * `event_id` = 1234567890
    * `event_nseverity` = 5
    * `event_source` = 0 (it simulates trigger based event)
    * `event_update_status` = 0 (not an update operation)
    * `event_value` = 1 (this is a problem event)
    * `send_to` = `ID of the recipient`
    * `trigger_description` = Test trigger description
    * `trigger_id` = 0987654
    * `zabbix_url` = https://127.0.0.1

    [![](images/tn_3.png?raw=true)](images/3.png)

2. Having successfully sent a message from Zabbix, check if it has been received by the recipient.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
