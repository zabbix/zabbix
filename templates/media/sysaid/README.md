
# SysAid webhook 

This guide describes how to integrate your Zabbix installation with SysAid using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## In SysAid

1. Create a user in SysAid for ticket creation
2. Configure Incident templates in Settings -> Service Desk templates -> Incident templates
3. Configure category in Settings -> Service Desk -> Categories (Category, Subcategory and Third level category will be used during ticket creation)

## In Zabbix

The configuration consists of a _media type_ in Zabbix, which will invoke the webhook to send alerts to SysAid through the Sysaid API.

You will need to configure the following fields in Sysaid webhook

* sysaid_auth_user = Username
* sysaid_auth_password = Password 
* sysaid_category_level_1 = Category (Example: Basic Software)
* sysaid_category_level_2 = Subcategory (Example: Adobe Reader)
* sysaid_category_level_3 = Third level category (Example: Does not work properly)
* sysaid_template_id = Configured template id (Example: 10)
* sysaid_urgency_id = Your selected urgency id (Example: 1)
* sysaid_url = Sysaid URL (Example: https://sysaid10577.sysaidit.com/)



For more information about the Zabbix Webhook configuration, please see the [documentation](https://www.zabbix.com/documentation/6.0/manual/config/notifications/media/webhook).

To utilize the media type, we recommend creating a dedicated [Zabbix user](https://www.zabbix.com/documentation/6.0/manual/web_interface/frontend_sections/administration/users) to represent SysAid. The default settings for SysAid User should suffice as this user will not be logging into Zabbix. Please note, that in order to be notified about problems on a host, this user must have at least read permissions for the host.  

## Internal alerts
To receive notifications about internal problem and recovery events in SysAid: in the internal action configuration mark the Custom message checkbox and specify custom message templates for problem and recovery operations. 
If an internal action operation is configured without a custom message, the notification will not be sent. 
Note, that this step is required only for notifications about internal events; for other event types specifying a custom message is optional. For other even types message templates still should be defined on media type level.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [SysAid](http://cdn1.SysAid.com/SysAidUserManual.pdf) documentation.

## Supported Versions

Zabbix 5.0+, SysAid.
