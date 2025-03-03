![](images/logo.png?raw=true)
# Telegram webhook

## Overview

This guide describes how to integrate your Zabbix installation with Telegram messenger using the Telegram Bot API and Zabbix webhook feature.

### Supported features:
* Personal and group notifications
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

2\. If you want to send personal notifications, you need to obtain the chat ID of the user the bot should send messages to.

Send `/getid` to `@myidbot` in the Telegram messenger.

[![](images/thumb.3.png?raw=true)](images/3.png)

Ask the user to send `/start` to the bot created in step 1. If you skip this step, the Telegram bot won't be able to send messages to the user.

[![](images/thumb.5.png?raw=true)](images/5.png)

3\.If you want to send group notifications, you need to obtain the group ID of the group that the bot should send messages to. To do so:

Add `@myidbot` and `@your_bot_name_here` to your group.
In the group chat, send: `/getgroupid@myidbot`.
In the group chat, send: `/start@your_bot_name_here`. If you skip this step, the Telegram bot won't be able to send messages to the group.

[![](images/thumb.9.png?raw=true)](images/9.png)

## Zabbix configuration

1\. In the Zabbix interface *Alerts* > *Media types* section, import the [`media_telegram.yaml`](media_telegram.yaml) file.

2\. Configure the added media type: 

Copy and paste your Telegram bot token into the *telegramToken* field.

[![](images/thumb.2.png?raw=true)](images/2.png)

In the `ParseMode` parameter, set the required option according to Telegram documentation.

More on formatting action notification messages in Telegram Bot API documentation: [Markdown](https://core.telegram.org/bots/api#markdown-style) / [HTML](https://core.telegram.org/bots/api#html-style) / [MarkdownV2](https://core.telegram.org/bots/api#markdownv2-style).

Note: Your Telegram-related actions should be separated from other notification actions (for example, SMS), otherwise you may get plain-text alerts with raw Markdown/HTML tags.

Test the media type using your chat ID or group ID.

[![](images/thumb.6.png?raw=true)](images/6.png)
[![](images/thumb.7.png?raw=true)](images/7.png)

If you have forgotten to send `/start` to the Telegram bot, you will get the following error:

[![](images/thumb.8.png?raw=true)](images/8.png)

3\.To receive notifications in Telegram, you need to create a Zabbix user and add **Media** with the **Telegram** media type.

In the *Send to* field, enter the Telegram user chat ID or group ID obtained during Telegram setup.

[![](images/thumb.4.png?raw=true)](images/4.png)

Make sure the user has access to all the hosts for which you would like to receive Telegram notifications.

Done! You can now start receiving Zabbix notifications in Telegram.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
