![](images/logo.png?raw=true)
# Slack webhook

## Overview

This guide describes how to integrate your Zabbix installation with Slack using the Zabbix webhook feature, providing instructions on setting up a media type, user and action in Zabbix.

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|zabbix_url|\{$ZABBIX\.URL\}|Current Zabbix URL.|
|bot_token|\<PLACE YOUR TOKEN\>|Slack bot token.|
|slack_mode|alarm|Slack mode. Could be "alarm" or "event" as a value.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_severity|\{EVENT\.SEVERITY\}|Name of the event severity.|
|event_update_nseverity|\{EVENT\.UPDATE\.NSEVERITY\}|Numeric value of the event update severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_update_severity|\{EVENT\.UPDATE\.SEVERITY\}|Name of the event update severity.|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|event_tags|\{EVENT\.TAGSJSON\}|A JSON array containing event tag [objects]('https://www.zabbix.com/documentation/current/manual/api/reference/event/object#event-tag'). Expanded to an empty array if no tags exist.|
|event_update_message|\{EVENT\.UPDATE\.MESSAGE\}|Problem update message.|
|event_update_action|\{EVENT\.UPDATE\.ACTION\}|Human-readable name of the action(s) performed during a [problem update]('https://www.zabbix.com/documentation/current/manual/acknowledgment#updating-problems').|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|
|channel|\{ALERT\.SENDTO\}|Slack channel.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. On the page [Your Apps](https://api.slack.com/apps), press *Create an App*, select *From scratch*, and specify its name and workspace.

2\. In the *Add Features and Functionality* section, select *Bots*, and press *Review Scopes to Add*.

3\. In the *Scopes* section, find *Bot Token Scopes*, press *Add an OAuth Scope* and add the scopes `chat:write`, `im:write`, `groups:write`, and `reactions:write`.

4\. In the *Settings* section on the left side of the page, press *Install App* and then *Install to Workspace*.

5\. Press *Allow* and copy *Bot User OAuth Access Token*, which will be used to set up webhook.

## Zabbix configuration

### Create a global macro

1\. Before setting up the webhook, you need to setup the global macro `{$ZABBIX.URL}`, which must contain the URL to the Zabbix frontend.

2\. In the Zabbix interface *Alerts* > *Media types* section, import the [`media_slack.yaml`](media_slack.yaml) file.

3\. Open the newly added **Slack** media type and set `bot_token` to the previously created token.

* You can also choose between two notification modes:
	- **alarm** (default)
		- Recovery and update messages from Zabbix will update existed messages. To acknowledge an event, add a green checkmark reaction and leave a reply as a comment.
	- **event**
		- Recovery and update messages from Zabbix will be posted as new messages.

4\. Click the *Update* button to save the webhook settings.

5\. To receive notifications in Slack, you need to create a Zabbix user and add **Media** with the **Slack** media type.

The **Send to** field can contain several variants of values:

- Channel name in the `#channel\_name` format
- Member ID (for example: `U079U3S5P95`)

6\. Add your bot to the target channel.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.4/manual/config/notifications) and [Slack API](https://api.slack.com) documentation.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
