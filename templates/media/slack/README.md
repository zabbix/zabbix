
# Slack webhook
![](images/Slack_RGB.png?raw=true)

This guide describes how to integrate your Zabbix 4.4 installation with Slack using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Setting up a Slack bot

1\. On the page [Your Apps](https://api.slack.com/apps) press **Create New App** and specify its name and workspace.

[![](images/thumb.1.png?raw=true)](images/1.png)
[![](images/thumb.2.png?raw=true)](images/2.png)

2\. In the **Add features and functionality** section, select **Bots** and press **Add Bot User**.

[![](images/thumb.3.png?raw=true)](images/3.png)
[![](images/thumb.4.png?raw=true)](images/4.png)

3\. Go to the **OAuth & Permissions** menu and press **Install App for Workspace**.

[![](images/thumb.5.png?raw=true)](images/5.png)
[![](images/thumb.6.png?raw=true)](images/6.png)

4\. Now you have 2 tokens, but you only need to use **Bot User OAuth Access Token**.

[![](images/thumb.7.png?raw=true)](images/7.png)

## Zabbix Webhook configuration

### Create a global macro

1\. Before setting up the **Webhook**, you need to setup the global macro **{$ZABBIX.URL}**, which must contain the **URL** to the **Zabbix frontend**.

[![](images/thumb.8.png?raw=true)](images/8.png)

2\. In the **Administration** > **Media types** section, import the [media_slack.xml](media_slack.xml)

3\. Open the added **Slack** media type and set **bot_token** to the previously created token.

[![](images/thumb.9.png?raw=true)](images/9.png)

* You can also choose between two notification modes:
	- **alarm** (default)
		- Update messages will be attached as replies to Slack message thread
		- Recovery message from Zabbix will update initial message
	- **event**
		- Recovery and update messages from Zabbix will be posted as new messages


4\. Click the **Update** button to save the **Webhook** settings.

5\. To receive notifications in **Slack**, you need to create a **Zabbix user** and add **Media** with the **Slack** type.

[![](images/thumb.10.png?raw=true)](images/10.png)

The **Send to** field can contain several variants of values:

- Channel name in **#channel\_name** format
- User name in **@slack\_user** format for direct messages
- Identifier (for example: **GQMNQ5G5R**)

6\. You must add your bot to the target channel

[![](images/thumb.11.png?raw=true)](images/11.png)
[![](images/thumb.12.png?raw=true)](images/12.png)

For more information, use the [Zabbix](https://www.zabbix.com/documentation/current/manual/config/notifications) and [Slack API](https://api.slack.com) documentations.

## Supported Versions

Zabbix 4.4
