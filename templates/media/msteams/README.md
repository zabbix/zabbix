# Microsoft Teams webhook

This guide describes how to integrate Zabbix 5.0 with MS Teams using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix. 
This integration is supported only for **Teams** as part of Office 365. Note, that **Teams** free plan does not support [incoming webhook](https://docs.microsoft.com/en-US/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook) feature.

## Setting up webhook in MS Teams 

1\. Create **incoming webhook** for the channel, where you want to receive notifications.
(See **Teams** [documentation](https://docs.microsoft.com/en-US/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook#add-an-incoming-webhook-to-a-teams-channel) for the step-by-step instructions).


## Setting up webhook in Zabbix 
1\. In the Zabbix web interface go to Administration → General section and select Macros from the dropdown menu in top left corner. Setup the global macro "{$ZABBIX.URL}" which will contain the URL to the Zabbix frontend. 
<br>The URL should be either an IP address, a fully qualified domain name or localhost. Specifying a protocol is mandatory, whereas port is optional.
Good examples:<br>
http://zabbix.com<br>
https://zabbix.lan/<br>
http://localhost<br>
http://127.0.0.1:8080<br>

Bad examples:<br>
zabbix.com<br>
http://zabbix/<br>

[![](images/thumb.1.png?raw=true)](images/1.png)

2\. In the *Administration > Media types* section, import the [media_ms_teams.xml](media_ms_teams.xml)

3\. Open the newly added **MS Teams** media type and replace placeholder *&lt;PLACE WEBHOOK URL HERE&gt;* with the **incoming webhook URL**, created during the webhook setup in MS Teams.

4\. You can also choose between two notification formats. Set *"use_default_message"* parameter:
- **false** (default)
    - Use preformatted message with predefined set of fields for trigger-based notifications.<br>
In internal, autoregistration and discovery notifications *{ALERT.MESSAGE}* as text a body of the message will be used.
- **true**
    - Use {ALERT.MESSAGE} as text a body of the message in all types of notifications.

[![](images/thumb.2.png?raw=true)](images/2.png)

5\. To receive Zabbix notifications in MS Teams, you need to create a **Zabbix user** and add **Media** with the **MS Teams media type**.<br>
In the *Administration → Users section*, click *Create user* button in the top right corner. In the *User* tab, fill in all required fields (marked with red asterisks). In the *Media* tab, add a new media and select **"MS Teams"** type from the drop-down list. Though a "*Send to*" field is not used in MS Teams media, it cannot be empty. To comply with the frontend requirements, you can put any symbol there.<br>
Don’t forget that in order to send notifications, this user must have access to hosts that generated such problems
[![](images/thumb.3.png?raw=true)](images/3.png)

6\. Great! You can now start receiving alerts!

For more information see [Zabbix](https://www.zabbix.com/documentation/current/manual/config/notifications) and [MS Teams webhook](https://docs.microsoft.com/en-US/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook) documentations.

## Supported Versions
Zabbix 5.0
