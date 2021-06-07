
# TOPdesk webhook 

This guide describes how to integrate your Zabbix installation with TOPdesk using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## In TOPdesk

1\. Please create an **application password** according to the original instruction https://developers.topdesk.com/tutorial.html#show-collapse-usage-createAppPassword.

2\. Copy the **application password** of your new integration to use it in Zabbix.

## In Zabbix

The configuration consists of a _media type_ in Zabbix, which will invoke the webhook to send alerts to TOPdesk through the TOPdesk Rest API.

1\. Create a [global macro](https://www.zabbix.com/documentation/6.0/manual/config/macros/user_macros) {$ZABBIX.URL} with Zabbix frontend URL (for example http://192.168.7.123:8081)

[![](images/tn_1.png?raw=true)](images/1.png)

2\. [Import](https://www.zabbix.com/documentation/6.0/manual/web_interface/frontend_sections/administration/mediatypes) the TOPdesk media type from file [media_topdesk.yaml](media_topdesk.yaml).

[![](images/tn_2.png?raw=true)](images/2.png)

3\. Change in the imported media the values of the variables topdesk_api (URL), topdesk_password, topdesk_user. The topdesk_status is the default status for creating a new TOPdesk ticket.

[![](images/tn_3.png?raw=true)](images/3.png)

For more information about the Zabbix Webhook configuration, please see the [documentation](https://www.zabbix.com/documentation/6.0/manual/config/notifications/media/webhook).

To utilize the media type, we recommend creating a dedicated [Zabbix user](https://www.zabbix.com/documentation/6.0/manual/web_interface/frontend_sections/administration/users) to represent TOPdesk. The default settings for TOPdesk User should suffice as this user will not be logging into Zabbix. Please note, that in order to be notified about problems on a host, this user must have at least read permissions for the host.  
When configuring alert action, add this user in the _Send to users_ field (in Operation details) - this will tell Zabbix to use TOPdesk webhook when sending notifications from this action. Use the TOPdesk User in any action of your choice. Text from "Action Operations" will be sent to "TOPdesk First Line Call Request" field when the problem happens. Text from "Action Recovery Operations" and "Action Update Operations" will be sent to "TOPdesk First Line Call Action" field when the problem is resolved or updated.

## Internal alerts
To receive notifications about internal problem events in TOPdesk: in the internal action configuration mark the Custom message checkbox and specify custom message templates for problem operations. Internal event recovery actions are not supported.
If an internal action operation is configured without a custom message, the notification will not be sent. 
Note, that this step is required only for notifications about internal events; for other event types specifying a custom message is optional. 

For more information, please see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [TOPdesk](https://developers.topdesk.com/documentation/index.html) documentation.

## Supported Versions

Zabbix 5.0, TOPdesk RestApi 3.1.4.
