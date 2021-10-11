# Microsoft Teams webhook

This guide describes how to integrate Zabbix 5.0 with MS Teams using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix. 
This integration is supported only for **Teams** as part of Office 365. Note, that **Teams** free plan does not support [incoming webhook](https://docs.microsoft.com/en-US/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook) feature.

## Setting up webhook in MS Teams 
Microsoft Teams webhook only supports integrating with a single channel.

First, you need to get a webhook URL for the channel. There are two ways to do this:

- Add official **Zabbix webhook** connector from MS Teams apps for the channel, where you want to receive notifications. (Check [how to add a connector to a channel](https://docs.microsoft.com/en-us/microsoftteams/office-365-custom-connectors#add-a-connector-to-a-channel))

- Create **Incoming webhook** for your channel.
(See **Teams** [documentation](https://docs.microsoft.com/en-US/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook#add-an-incoming-webhook-to-a-teams-channel) for the step-by-step instructions).


## Setting up webhook in Zabbix 
1\. In the Zabbix web interface go to Administration → General section and select Macros from the dropdown menu in top left corner. Setup the global macro "{$ZABBIX.URL}" which will contain the URL to the Zabbix frontend. 
<br>The URL should be either an IP address, a fully qualified domain name or localhost. Specifying a protocol is mandatory, whereas port is optional.
Good examples:<br>
http://zabbix.com<br>
https://zabbix.lan/<br>
http://server.zabbix.lan/</br>
http://localhost<br>
http://127.0.0.1:8080<br>

Bad examples:<br>
zabbix.com<br>
http://zabbix/<br>

[![](images/thumb.1.png?raw=true)](images/1.png)

2\. In the *Administration > Media types* section, import the [media_ms_teams.yaml](media_ms_teams.yaml)

3\. Open the newly added **MS Teams** media type and replace placeholder *&lt;PLACE WEBHOOK URL HERE&gt;* with the **incoming webhook URL**, created during the webhook setup in MS Teams.

4\. You can also choose between two notification formats. Set *"use_default_message"* parameter:
- **false** (default)
    - Use preformatted message with predefined set of fields for trigger-based notifications.<br>
    In internal, autoregistration and discovery notifications *{ALERT.MESSAGE}* as a body of the message will be used.
    In this case you can customize the message template for trigger-based notifications by adding additional fields and up to four buttons with URLs.
        - To add an additional field to message card, put a parameter with prefix **fact_** and field name. For example, *"fact_Data center"* as key and *{EVENT.TAGS.dc}* as value.
        - To create a new button with a link to an external resource, add a parameter with prefix **openUri_** and button name. The value should be a valid URL. For example, *"openUri_Link to Zabbix.com"* as key and *https://www.zabbix.com/* as value.<br>
        If any of the parameters with prefix **openUri_** has invalid URL it will be ignored by Teams.<br>
        Also, since Microsoft only supports five buttons in a message card, one of which is reserved for the "*Event info*" link, the fifth and subsequent parameters with prefix **openUri_** and valid URL will be ignored too.

- **true**
    - Use {ALERT.MESSAGE} as a body of the message in all types of notifications.

[![](images/thumb.2.png?raw=true)](images/2.png)

5\. To receive Zabbix notifications in MS Teams, you need to create a **Zabbix user** and add **Media** with the **MS Teams media type**.<br>
In the *Administration → Users section*, click *Create user* button in the top right corner. In the *User* tab, fill in all required fields (marked with red asterisks). In the *Media* tab, add a new media and select **"MS Teams"** type from the drop-down list. Though a "*Send to*" field is not used in MS Teams media, it cannot be empty. To comply with the frontend requirements, you can put any symbol there.<br>
Make sure this user has access to all hosts for which you would like problem notifications to be sent to MS Teams.<br>
[![](images/thumb.3.png?raw=true)](images/3.png)

6\. Great! You can now start receiving alerts!

For more information see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [MS Teams webhook](https://docs.microsoft.com/en-US/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook) documentations.

## Supported Versions
Zabbix 5.0
