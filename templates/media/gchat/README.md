# Google Chat webhook

This guide describes how to integrate Zabbix 6.2 with Google Chat using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

You must have a [Google Workspace](https://workspace.google.com/) account to use webhooks in Google Chat.

## Setting up webhook in Google Chat

First, you need to get a webhook URL for your Google Chat space.

Open Google Chat in a web browser.
1. Under Spaces, go to the space to which you want to add a webhook.
2. At the top, next to space title, click &#9662; Down Arrow > **Manage webhooks**.
3. If this space already has other webhooks, click Add another. Otherwise, skip this step.
4. For Name, enter "Zabbix" or something unique for your deployment.
5. For Avatar URL, add a link to your own icon or leave blank.
6. Click **SAVE**.
7. Click **Copy** to copy the full webhook URL.

## Setting up webhook in Zabbix
1\. In the Zabbix web interface go to Administration → General section and select Macros from the dropdown menu in the top left corner. Setup the global macro "{$ZABBIX.URL}" which will contain the URL to the Zabbix frontend.
<br>The URL should be either an IP address, a fully qualified domain name or localhost. Specifying a protocol is mandatory, whereas port is optional.
Good examples:<br>
http://zabbix.com<br>
https://zabbix.lan/<br>
http://server.zabbix.lan/<br>
http://localhost<br>
http://127.0.0.1:8080<br>

Bad examples:<br>
zabbix.com<br>
http://zabbix/<br>

[![](images/add-macro-thumb.png?raw=true)](images/add-macro.png)

2\. In the *Administration > Media types* section, import the [media_gchat.yaml](media_gchat.yaml)

3\. Open the newly added **Google Chat** media type and replace placeholder *&lt;PLACE WEBHOOK URL HERE&gt;* with the **incoming webhook URL**, created during the webhook setup in Google Chat.

[![](images/config-media-thumb.png?raw=true)](images/config-media.png)

(Note: You can also reference a custom macro for the webhook URL and for example set the macro in a Template.)

4\. To receive Zabbix notifications in Google Chat, you need to create a **Zabbix user** and add **Media** with the **Google Chat media type**.<br>
In the *Administration → Users section*, click *Create user* button in the top right corner. In the *User* tab, fill in all required fields (marked with red asterisks). In the *Media* tab, add a new media and select **"Google Chat"** type from the drop-down list. Though a "*Send to*" field is not used in Google Chat media, it cannot be empty. To comply with the frontend requirements, you can put any symbol there.<br>
Make sure this user has access to all hosts for which you would like problem notifications to be sent to Google Chat.<br>
[![](images/add-media-thumb.png?raw=true)](images/add-media.png)

5\. Great! You can now start receiving alerts!

For more information see [Zabbix](https://www.zabbix.com/documentation/6.2/manual/config/notifications) and [Google Chat webhook](https://developers.google.com/chat/how-tos/webhooks) documentations.

## Supported Versions
Zabbix 6.2
