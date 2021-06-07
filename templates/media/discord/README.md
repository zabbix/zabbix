# Discord webhook

This guide describes how to integrate your Zabbix 4.4 installation with Discord using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Setting up Discord Webhook

1\. Go to https://discordapp.com/app or open Discord Desktop application. Select your server and channel where you want to get Zabbix notifications.

2\. Press **Edit channel**, select **Webhooks** tab and press **Create Webhook** button

[![](images/thumb.1.png?raw=true)](images/1.png)
[![](images/thumb.2.png?raw=true)](images/2.png)


3\. Setup your Discord webhook and press **Save**.
<br>You can copy Discord webhook URL now or view it later by clicking on **Edit** button.

[![](images/thumb.3.png?raw=true)](images/3.png)


## Setting up Zabbix Webhook
1\. Before setting up Discord Webhook, you need to setup the global macro "{$ZABBIX.URL}", which must contain the URL to the Zabbix frontend.
<br>The URL should be either an IP address, a fully qualified domain name or localhost. Specifying a protocol is mandatory, whereas port is optional.
Good examples:<br>
http://zabbix.com<br>
https://zabbix.lan/<br>
http://localhost<br>
http://127.0.0.1:8080<br>

Bad examples:<br>
zabbix.com<br>
http://zabbix/<br>

[![](images/thumb.4.png?raw=true)](images/4.png)

2\. In the "Administration > Media types" section, import the [media_discord.yaml](media_discord.yaml)

3\. If you want to change values of default parameters, open the newly added **Discord** media type.<br>
You can also choose between two notification modes by modifying _"use_default_message"_ parameter value:
- **false** (default)
    - receive problem notifications with predefined set of fields (problem, host name, event severity, event type, etc.)
- **true**
    - receive default message defined in the Zabbix Action that triggered notification

[![](images/thumb.5.png?raw=true)](images/5.png)

4\. To receive notifications in Discord, you need to create a **Zabbix user** and add **Media** with the **Discord** media type.
The "Send to" field must contain Discord webhook URL created before.
<br>Also don’t forget that in order to send notifications, this user must have access to hosts that generated such problems

[![](images/thumb.6.png?raw=true)](images/6.png)

5\. Start getting alerts! You have made it!

[![](images/thumb.7.png?raw=true)](images/7.png)

For more information see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [Discord](https://discordapp.com/developers/docs/resources/webhook#execute-webhook) documentations.

## Supported Versions
Zabbix 4.4
