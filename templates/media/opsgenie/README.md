
# Opsgenie webhook 

This guide describes how to integrate your Zabbix installation with Opsgenie using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## In Opsgenie

1\. Create an **API Key** according by original instruction https://docs.opsgenie.com/docs/api-integration, please.

2\. Copy the **API Key** of your new integration to use it in Zabbix.

## In Zabbix

The configuration consists of a _media type_ in Zabbix, which will invoke the webhook to send alerts to Opsgenie through the Opsgenie Rest API.

1\. Create a [global macro](https://www.zabbix.com/documentation/6.0/manual/config/macros/user_macros) {$ZABBIX.URL} with Zabbix frontend URL (for example http://192.168.7.123:8081)

[![](images/tn_1.png?raw=true)](images/1.png)

2\. [Import](https://www.zabbix.com/documentation/6.0/manual/web_interface/frontend_sections/administration/mediatypes) the Opsgenie media type from file [media_opsgenie.yaml](media_opsgenie.yaml).

[![](images/tn_2.png?raw=true)](images/2.png)

3\. Change the values of the variables opsgenie_api (https://api.opsgenie.com/v2/alerts or https://api.eu.opsgenie.com/v2/alerts) , opsgenie_web (for example, https://myzabbix.app.opsgenie.com), opsgenie_token.
Also you could set own tags into opsgenie_tags as <comma_separated_list_of_tags> and team names into opsgenie_teams as <comma_separated_list_of_responders>.  
The priority level in severity_default will be used for non-triggered actions.

[![](images/tn_3.png?raw=true)](images/3.png)

For more information about the Zabbix Webhook configuration, please see the [documentation](https://www.zabbix.com/documentation/6.0/manual/config/notifications/media/webhook).

To utilize the media type, we recommend creating a dedicated [Zabbix user](https://www.zabbix.com/documentation/6.0/manual/web_interface/frontend_sections/administration/users) to represent Opsgenie. The default settings for Opsgenie User should suffice as this user will not be logging into Zabbix. Please note, that in order to be notified about problems on a host, this user must have at least read permissions for the host.  
When configuring alert action, add this user in the _Send to users_ field (in Operation details) - this will tell Zabbix to use Opsgenie webhook when sending notifications from this action. Use the Opsgenie User in any actions of your choice. Text from "Action Operations" will be sent to "Opsgenie Alert" when the problem happens. Text from "Action Recovery Operations" and "Action Update Operations" will be sent to "Opsgenie Alert Notes" when the problem is resolved or updated.

## Internal alerts
To receive notifications about internal problem and recovery events in Opsgenie: in the internal action configuration mark the Custom message checkbox and specify custom message templates for problem and recovery operations. 
If an internal action operation is configured without a custom message, the notification will not be sent. 
Note, that this step is required only for notifications about internal events; for other event types specifying a custom message is optional. 

For more information, please see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [Opsgenie](https://docs.opsgenie.com/docs/alert-api) documentation.

## Known issue:

If both recovery and update operations are defined for an action and the problem is closed manually in the frontend, closing operation will be executed first. Update operations for the resolved event will not be executed, but the status of these operations will be changed to 'Sent' to stop failed request attempts.

## Supported Versions

Zabbix 5.0, Opsgenie Alert API.
