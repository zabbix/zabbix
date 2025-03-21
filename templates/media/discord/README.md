![](images/logo.png?raw=true)
# Discord webhook

## Overview

This guide describes how to integrate your Zabbix installation with Discord using the Zabbix webhook feature, providing instructions on setting up a media type, user, and action in Zabbix.

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|zabbix_url|\{$ZABBIX\.URL\}|The URL of the Zabbix frontend.|
|user_agent|ZabbixServer \(zabbix\.com, 7\.4\)|The user agent to use in the request.|

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
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|discord_endpoint|\{ALERT\.SENDTO\}|The URL of the Discord webhook.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1. Go to `https://discord.com/app` or open the Discord desktop application. Open your *Server Settings* and head to the *Integrations* tab.

2. Press the *Create Webhook* button to create a new webhook.

[![](images/thumb.1.png?raw=true)](images/1.png)

3. Click on the webhook that has been created and edit the details if needed. You can:

  - Edit the avatar.
  - Choose what channel the webhook posts to.
  - Rename the webhook.

4. After setting up your Discord webhook, press *Save Changes*.

You can copy the Discord webhook URL now by pressing *Copy Webhook URL*, or you can view it later.

[![](images/thumb.2.png?raw=true)](images/2.png)

## Zabbix configuration

1. Before you can start using the Discord webhook, you need to set up the global macro `{$ZABBIX.URL}`:
  - In the Zabbix web interface, go to *Administration* > *Macros* in the top-left dropdown menu.
  - Set up the global macro `{$ZABBIX.URL}` which will contain the URL to the Zabbix frontend. The URL should be either an IP address, a fully qualified domain name, or a localhost.
  - Specifying a protocol is mandatory, whereas the port is optional. Depending on the web server configuration, you might also need to append `/zabbix` to the end of URL. Good examples:  
    - `http://zabbix.com`
    - `https://zabbix.lan/zabbix`
    - `http://server.zabbix.lan/`
    - `http://localhost`
    - `http://127.0.0.1:8080`
  - Bad examples:
    - `zabbix.com`
    - `http://zabbix/`

[![](images/thumb.3.png?raw=true)](images/3.png)

2. Import the media type:
  - In the *Alerts* > *Media types* section, import the [`media_discord.yaml`](media_discord.yaml) file.

3. Create a Zabbix user and add media:
  - If you want to create a new user, go to the *Users* > *Users* section and click the *Create user* button in the top-right corner. In the *User* tab, fill in all the required fields (marked with red asterisks).
  - In the *Media* tab, add a new media and select **Discord** from the *Type* drop-down list. The *Send to* field must contain the URL of the Discord webhook created previously.
  - Make sure this user has access to all the hosts for which you would like problem notifications to be sent to Discord.

[![](images/thumb.4.png?raw=true)](images/4.png)

4. Done! You can now start using this media type in actions and receive alerts.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.4/manual/config/notifications) and [Discord](https://discordapp.com/developers/docs/resources/webhook#execute-webhook) documentation.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
