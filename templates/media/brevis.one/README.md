# Brevis.one webhook

This guide describes how to integrate Zabbix 5.0 installation with Brevis.one SMS Gateway using the HTTP API and the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user, and an action in Zabbix.<br>

## Setting up Brevis.one
1\. Create a user for HTTP API or use an existing one.<br>

2\. Grant to the user *Access to the HTTP API* permission. See Brevis.one [documentation](https://docs.brevis.one/current/en/Content/Functionality/Sending%20Messages/HTTP%20API.htm) for the information.<br>


## Setting up the webhook in Zabbix
1\. Before setting up media type, you need to set up the global macro "{$ZABBIX.URL}", which must contain the URL to the Zabbix frontend.

2\. In the *Administration > Media types* section, import [media_brevis.one.xml](media_brevis.one.xml).

3\. Open the newly added **Brevis.one** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.<br>
The following parameters should be filled:<br>
**endpoint** - the actual URL of your Brevis.one API instance. The API can be addressed with the following: `https://<SMS Gateway IP>/api.php`<br>
**username** - Brevis.one API username.<br>
**password** - user's password.<br>

3\. The following parameters can help you customize the alerts: ***ring**, **flash**, **telauto**<br>
See Brevis.one [documentation](https://docs.brevis.one/current/en/Content/Functionality/Sending%20Messages/HTTP%20API.htm) for the information.<br>

4\. Create a service **Zabbix user** or use any existing and add **Media** with the **Brevis.one**.
"Send to" field should be filled as phone number without "+" symbol or as "mode:option".<br>
Allowed modes: number (Default), group, telgroup, telnumber, user, teluser.<br>
Examples:
`37167784742` (Send SMS to the individual telephone number)<br>
`group:11` (Send a text message to a user group using this option. User groups are managed via Configuration - Groups.)<br>
`telnumber:37167784742` (Send a message via automatic to individual telephone numbers using this option. Automatic tries to deliver the notification via Telegram, if it fails the notification will be delivered by text message.)<br>
See Brevis.one [documentation](https://docs.brevis.one/current/en/Content/Functionality/Sending%20Messages/HTTP%20API.htm) for the additional information.<br>
Note, that "Send to" field cannot be empty. If the phone number or user/group ID is already specified in the **send_to** parameter, you can put any symbol in this field to comply with frontend requirements.
Make sure this user has access to all hosts, for which you would like problem notifications to be converted into Brevis.one tasks.

For more information see [Zabbix](https://www.zabbix.com/documentation/5.0/manual/config/notifications) and [Brevis.one](https://docs.brevis.one/current/en/Content/Home.htm) documentations.

## Supported versions
Zabbix 5.0 and higher
