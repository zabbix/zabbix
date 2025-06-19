![](images/logo.png?raw=true)
# Telegram webhook

## Overview

This guide describes how to integrate your Zabbix installation with Telegram messenger using the Telegram Bot API and Zabbix webhook feature, providing instructions on setting up a media type, user, and action in Zabbix.

### Supported features:
* Personal and group notifications (including topics in supergroups)
* Markdown/HTML support

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|api_parse_mode|\<PLACE PARSE MODE\>|Formatting mode applied for messages. Possible values: markdown, html, markdownv2.|
|api_token|\<PLACE YOUR TOKEN\>|Bot token that is used to access the Telegram HTTP API.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_severity|\{EVENT\.SEVERITY\}|Name of the event severity.|
|event_update_nseverity|\{EVENT\.UPDATE\.NSEVERITY\}|Numeric value of the event update severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_update_severity|\{EVENT\.UPDATE\.SEVERITY\}|Name of the event update severity.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|event_tags|\{EVENT\.TAGSJSON\}|A JSON array containing event tag [objects]('https://www.zabbix.com/documentation/current/manual/api/reference/event/object#event-tag'). Expanded to an empty array if no tags exist.|
|api_chat_id|\{ALERT\.SENDTO\}|Recipient's chat ID.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Register a new Telegram bot: send `/newbot` to `@BotFather` and follow the instructions. The token provided by `@BotFather` in the final step will be needed for configuring the Zabbix webhook.

[![](images/thumb.1.png?raw=true)](images/1.png)

2\. Set up personal or group notifications:

2\.1 Personal notifications:

2\.1\.1 Retrieve the chat ID of the user the bot should send messages to. The user should send `/getid` to `@myidbot` in the Telegram messenger.

[![](images/thumb.2.png?raw=true)](images/2.png)

2\.1\.2 The user should also send `/start` to the bot created in step 1. If you skip this step, the Telegram bot won't be able to send messages to the user (bots cannot initiate conversations with users).

[![](images/thumb.3.png?raw=true)](images/3.png)

2\.2 Group notifications:

2\.2\.1 Retrieve the group ID of the group that the bot should send messages to. Add `@myidbot` and the bot created in step 1 to your group.

2\.2\.2 In the group chat, send: `/getgroupid@myidbot`.

[![](images/thumb.4.png?raw=true)](images/4.png)

2\.2\.3 If the bot is added to a supergroup and you want the bot to send messages to a specific topic instead of the default *General* channel, right-click any message in that topic and click *Copy Message Link*. The copied link will have the following format: `https://t.me/c/<short_group_id>/<topic_id>/<message_id>`, for example: `https://t.me/c/1234567890/2/1`. In this example, the topic ID is `2`.

[![](images/thumb.5.png?raw=true)](images/5.png)

Note:
- The group ID is a negative number, for example: `-1234567890`.
- The supergroup ID is a negative number prefixed with `-100`, for example: `-1001234567890`.
- The public group or supergroup ID can also be specified in media type properties as a name prefixed by `@`, for example: `@MyGroupName`.

3\. Depending on where you want to send notifications, copy and save the bot token, personal chat ID or group ID, and topic ID (if you want to send messages to a specific supergroup topic), as you will need these later to set up the media type in Zabbix.

## Zabbix configuration

1. Import the media type:
- In the *Alerts* > *Media types* section, import the [`media_telegram.yaml`](media_telegram.yaml) file.

2. Open the imported **Telegram** media type and set the following webhook parameters:
- `api_parse_mode` - the formatting mode applied for messages (possible values: `markdown`, `html`, `markdownv2`)
- `api_token` - the token of the bot used to send messages

[![](images/thumb.6.png?raw=true)](images/6.png)

Learn more about message formatting options in Telegram Bot API documentation:
- [Markdown](https://core.telegram.org/bots/api#markdown-style)
- [HTML](https://core.telegram.org/bots/api#html-style)
- [MarkdownV2](https://core.telegram.org/bots/api#markdownv2-style)

Note: Your Telegram-related actions should be separated from other notification types (e.g., SMS); otherwise, if you use Markdown or HTML in the alert subject or body, you may receive plain-text alerts with raw tags.

3. Click the *Enabled* checkbox to enable the media type and click the *Update* button to save the webhook settings.

4. Create a Zabbix user and add media:
  - To create a new user, go to the *Users* > *Users* section and click the *Create user* button in the top-right corner. In the *User* tab, fill in all the required fields (marked with red asterisks).
  - Make sure this user has access to all the hosts for which you would like problem notifications to be sent to **Telegram**.
  - In the *Media* tab, click *Add* and select **Telegram** from the *Type* drop-down list.
  - In the *Send to* field, specify the Telegram user chat ID or group ID that you retrieved during Telegram setup. To send notifications to a specific topic within a supergroup, specify the topic ID after the semicolon delimiter in the format `<group_id>:<topic_id>`, for example: `-1001234567890:2`, `@MyGroupName:2`.

[![](images/thumb.7.png?raw=true)](images/7.png)
[![](images/thumb.8.png?raw=true)](images/8.png)
[![](images/thumb.9.png?raw=true)](images/9.png)

5. Done! You can now start using this media type in actions and send notifications.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
