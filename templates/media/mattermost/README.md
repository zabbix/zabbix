![](images/logo.png?raw=true)
# Mattermost webhook

## Overview

This guide describes how to integrate your Zabbix 7.4 installation with Mattermost using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|bot_token|\<YOUR BOT TOKEN\>|Mattermost bot token.|
|mattermost_url|\<YOUR MATTERMOST URL\>|Mattermost URL.|
|send_mode|alarm|Mattermost notification mode.|
|tls_verify|\{$HTTP\.TLS\.VERIFY:"Mattermost"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "Mattermost", e.g. {$HTTP.TLS.VERIFY:"Mattermost"}.|
|zabbix_url|\{$ZABBIX\.URL\}|Current Zabbix URL.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|discovery_host_dns|\{DISCOVERY\.DEVICE\.DNS\}|DNS name of the discovered device.|
|discovery_host_ip|\{DISCOVERY\.DEVICE\.IPADDRESS\}|IP address of the discovered device.|
|event_date|\{EVENT\.DATE\}|Date of the event that triggered an action.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_opdata|\{EVENT\.OPDATA\}|Operational data of the underlying trigger of a problem.|
|event_recovery_date|\{EVENT\.RECOVERY\.DATE\}|Date of the recovery event.|
|event_recovery_time|\{EVENT\.RECOVERY\.TIME\}|Time of the recovery event.|
|event_severity|\{EVENT\.SEVERITY\}|Name of the event severity.|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_tags|\{EVENT\.TAGS\}|A comma-separated list of event tags. Expanded to an empty string if no tags exist.|
|event_time|\{EVENT\.TIME\}|Time of the event that triggered an action.|
|event_update_date|\{EVENT\.UPDATE\.DATE\}|Date of event [update]('https://www.zabbix.com/documentation/current/manual/config/notifications/action/update_operations') (acknowledgment, etc).|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_update_time|\{EVENT\.UPDATE\.TIME\}|Time of event [update]('https://www.zabbix.com/documentation/current/manual/config/notifications/action/update_operations') (acknowledgment, etc).|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|host_ip|\{HOST\.IP\}|Host IP address|
|host_name|\{HOST\.HOST\}|Host name.|
|send_to|\{ALERT\.SENDTO\}|'Send to' value from user media configuration.|
|trigger_description|\{TRIGGER\.DESCRIPTION\}|Trigger description.|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. From the **Main menu** of your Mattermost installation, select **Integrations** and click on the **Bot accounts** block. 

[![](images/thumb.32.png?raw=true)](images/32.png)
[![](images/thumb.31.png?raw=true)](images/31.png)

2\. Click on the **Add Bot Account** button and fill in the required fields and enable permissions for **post:all** and **post:channels**.

[![](images/thumb.30.png?raw=true)](images/30.png)
[![](images/thumb.29.png?raw=true)](images/29.png)
[![](images/thumb.27.png?raw=true)](images/27.png)

3\. The bot account is created and given an **Access Token** that you need to save. It will not be displayed later.

[![](images/thumb.26.png?raw=true)](images/26.png)
[![](images/thumb.25.png?raw=true)](images/25.png)

* You can always create a new access token with an arbitrary description, but remember that it is only displayed at the creation step.

[![](images/thumb.22.png?raw=true)](images/22.png)
[![](images/thumb.23.png?raw=true)](images/23.png)

4\. Add a **Bot Account** to your **Team** so that it can send messages to the team channels. To do this, click **Invite People** from the **Main menu**.

[![](images/thumb.20.png?raw=true)](images/20.png)
[![](images/thumb.19.png?raw=true)](images/19.png)

5\. The bot can already send messages to **public channels** and **user channels** (direct messages). To send it to a **private channel**, add it as a member.

[![](images/thumb.14.png?raw=true)](images/14.png)
[![](images/thumb.13.png?raw=true)](images/13.png)

## Zabbix configuration

1\. Before setting up the **Webhook**, you need to setup the global macro **{$ZABBIX.URL}**, which must contain the **URL** to the **Zabbix frontend**.

[![](images/thumb.10.png?raw=true)](images/10.png)

2\. In the **Administration** > **Media types** section, import the [media_mattermost.yaml](media_mattermost.yaml)

3\. Open the added **Mattermost** media type and set **bot_token** to the previously created token and **mattermost_url** to the **frontend URL** of your **Mattermost** installation.

[![](images/thumb.9.png?raw=true)](images/9.png)

* You can also choose between two notification modes:
	- **alarm** (default)
		- Update messages will be attached as replies to Mattermost message thread
		- Recovery message from Zabbix will update initial message
	- **event**
		- Recovery and update messages from Zabbix will be posted as new messages

4\. Click the **Update** button to save the **Webhook** settings.

5\. To receive notifications in **Mattermost**, you need to create a **Zabbix user** and add **Media** with the **Mattermost** type.

[![](images/thumb.9.png?raw=true)](images/8.png)

The **Send to** field can contain several variants of values:

- Channel name in **`team_name/#channel_name`** format
- Channel name in **`team_name/@user_name`** format for direct messages
- Identifier of the channel (for example: **fqzj8ysn8frxu8m9hcjna5uqmc**)

[![](images/thumb.2.png?raw=true)](images/2.png)
[![](images/thumb.1.png?raw=true)](images/1.png)
[![](images/thumb.5.png?raw=true)](images/5.png)

You can view the channel identifier in the channel properties.

[![](images/thumb.7.png?raw=true)](images/7.png)
[![](images/thumb.6.png?raw=true)](images/6.png)

For more information, use the [Zabbix](https://www.zabbix.com/documentation/7.4/manual/config/notifications) and [Mattermost](https://docs.mattermost.com) documentations.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
