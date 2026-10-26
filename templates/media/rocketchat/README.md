![](images/logo.png?raw=true)
# Rocket.Chat webhook

## Overview

This guide describes how to integrate Zabbix 8.0 installation with Rocket.Chat using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.
By default, all new alerts will be posted as messages with an attachment card. Event updates and resolve messages will be added to the thread of the first message.

## Requirements

Zabbix version: 8.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|rc_api_url|api/v1/|Rocket.Chat API URL.|
|rc_title_link|\{$ZABBIX\.URL\}/tr\_events\.php?triggerid=\{TRIGGER\.ID\}&eventid=\{EVENT\.ID\}|Rocket.Chat title link.|
|rc_url|\<PLACE YOUR INSTANCE URL HERE\>|Rocket.Chat URL.|
|rc_user_id|\<PLACE USER ID HERE\>|Rocket.Chat user ID.|
|rc_user_token|\<PLACE TOKEN HERE\>|Rocket.Chat token.|
|tls_verify|\{$HTTP\.TLS\.VERIFY:"Rocket\.Chat"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "Rocket.Chat", e.g. {$HTTP.TLS.VERIFY:"Rocket.Chat"}.|
|use_default_message|false|Rocket.Chat use default message toggle.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_update_action|\{EVENT\.UPDATE\.ACTION\}|Human-readable name of the action(s) performed during a [problem update]('https://www.zabbix.com/documentation/current/manual/acknowledgment#updating-problems').|
|event_update_message|\{EVENT\.UPDATE\.MESSAGE\}|Problem update message.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_update_user|\{USER\.FULLNAME\}|Name, surname, and username of the user who added an event acknowledgment or started the script.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|field_1_full:Host|\{HOST\.NAME\} \[\{HOST\.IP\}\]|Full host.|
|field_2_short:Severity|\{EVENT\.SEVERITY\}|Name of the event severity.|
|field_3_short:Event time|\{EVENT\.DATE\} \{EVENT\.TIME\}|Event time.|
|field_3_short_r:Recovery time|\{EVENT\.RECOVERY\.DATE\} \{EVENT\.RECOVERY\.TIME\}|Recovery time.|
|field_4_short_r:Event duration|\{EVENT\.DURATION\}|Duration of the event (time difference between problem and recovery events), with precision down to a second.|
|field_5_short:Operational data|\{EVENT\.OPDATA\}|Operational data of the underlying trigger of a problem.|
|field_999_full_p:Trigger description|\{TRIGGER\.DESCRIPTION\}|Trigger description.|
|rc_msg_id|\{EVENT\.TAGS\.\_\_zbx\_rc\_id\}|Rocket.Chat message id.|
|rc_room_id|\{EVENT\.TAGS\.\_\_zbx\_rc\_rid\}|Rocket.Chat room id.|
|rc_send_to|\{ALERT\.SENDTO\}|'Send to' value from user media configuration.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Create a user for API or use an existing one. Make sure the user is able to post messages in the required channel.

2\. Grant to the user a role with *create-personal-access-tokens* permission. See Rocket.Chat [documentation](https://docs.rocket.chat/api/rest-api/personal-access-tokens) for the information.

3\. Get the API access token. The tokens that will be generated are irrecoverable, after generating, you must save it in a safe place. If the token is lost or forgotten, you can regenerate or delete the token.

## Zabbix configuration

1\. In the *Administration > Media types* section, import [media_rocketchat.yaml](media_rocketchat.yaml).

2\. Open the newly added **Rocket.Chat** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.
The following parameters should be filled:
**rc_url** - the actual URL of your Rocket.Chat instance.
**rc_user_id** - Rocket.Chat API user ID.
**rc_user_token** - user's API access token created earlier.

3\. The following parameters can help you customize the alerts:
**rc_api_url** - API URL. Can be useful if the version will be changed.
**rc_send_to** - *#channel* or *@username*. Supports private and public channels and direct messages.
**use_default_message** - **false** (default) or **true**. If **true** all messages will be posted as text of *{ALERT.MESSAGE}.* For non trigger-based notifications, it is always set as **true**.
**field_1_short_p:Host** - contains data for each field of the attachment. "Field" parameters with another format or empty value will be ignored.
Format explanation:
- *field* - prefix of the parameter with field info.
- *1* - the position of the field. Fields with the same position will be added in the alphabetical order.
- *short* - whether the field should be short or not. If *short*, there can be several fields on one line, otherwise, the field will be placed on a separate line.
- *p* - optional. Used if the field should be sent only on problem/recovery operation. Possible values:
    - *p* - problem
    - *r* - recovery
- *Host* - the title of the field. There can be any text including whitespaces or symbols.aces or symbols.

4\. Create a **Zabbix user** and add **Media** with the **Rocket.Chat** media type.
"Send to" field should be filled as `#channel_name` or `@username`.
Note, that "Send to" field cannot be empty. If the channel is already specified in the **rc_send_to** parameter, you can put any symbol in this field to comply with frontend requirements.
Make sure this user has access to all hosts, for which you would like problem notifications to be converted into Rocket.Chat tasks.

For more information see [Zabbix](https://www.zabbix.com/documentation/8.0/manual/config/notifications) and [Rocket.Chat](https://docs.rocket.chat/) documentations.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
