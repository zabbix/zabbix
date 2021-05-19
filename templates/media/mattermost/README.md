
# Mattermost webhook
![](images/logoHorizontal.png?raw=true)

This guide describes how to integrate your Zabbix 4.4 installation with Mattermost using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Setting up a Mattermost bot

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


## Zabbix Webhook configuration

### Create a global macro

1\. Before setting up the **Webhook**, you need to setup the global macro **{$ZABBIX.URL}**, which must contain the **URL** to the **Zabbix frontend**.

[![](images/thumb.10.png?raw=true)](images/10.png)

2\. In the **Administration** > **Media types** section, import the [media_mattermost.yaml](media_mattermost.yaml)

3\. Open the added **Mattermost** media type and set **bot_token** to the previously created token and **mattermost_url** to the **frontend URL** of your **Mattermost** installation.

[![](images/thumb.9.png?raw=true)](images/9.png)

* You can also choose between two notification modes:
	- **alarm** (default)
		- Update messages will be attached as replies to Slack message thread
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

For more information, use the [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [Mattermost](https://docs.mattermost.com) documentations.

## Supported Versions

Zabbix 4.4
