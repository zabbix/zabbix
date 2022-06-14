
# Slack webhook
![](images/Slack_RGB.png?raw=true)

This guide describes how to integrate your Zabbix 6.0 and higher installation with Slack using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Setting up a Slack bot

1\. On the page [Your Apps](https://api.slack.com/apps) press **Create New App** and specify its name and workspace.

2\. In the **Add features and functionality** section, select **Bots** and press **Review Scopes to Add**.

3\. In the **Scopes** section, find **Scopes**, press **Add an OAuth Scope** and add **chat:write**, **im:write** and **groups:write** scopes.

4\. Press **Install to Workspace** on the top of the page.

5\. Now you have bot token, but you only need to use **Bot User OAuth Access Token**.

## Zabbix Webhook configuration

### Create a global macro

1\. Before setting up the **Webhook**, you need to setup the global macro **{$ZABBIX.URL}**, which must contain the **URL** to the **Zabbix frontend**.

2\. In the **Administration** > **Media types** section, import the [media_slack.yaml](media_slack.yaml)

3\. Open the added **Slack** media type and set **bot_token** to the previously created token.

* You can also choose between two notification modes:
	- **alarm** (default)
		- Update messages will be attached as replies to Slack message thread
		- Recovery message from Zabbix will update initial message
	- **event**
		- Recovery and update messages from Zabbix will be posted as new messages


4\. Click the **Update** button to save the **Webhook** settings.

5\. To receive notifications in **Slack**, you need to create a **Zabbix user** and add **Media** with the **Slack** type.

The **Send to** field can contain several variants of values:

- Channel name in **#channel\_name** format
- User name in **@slack\_user** format for direct messages
- Identifier (for example: **GQMNQ5G5R**)

6\. You must add your bot to the target channel

For more information, use the [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [Slack API](https://api.slack.com) documentations.

## Supported Versions

Zabbix 5.4
