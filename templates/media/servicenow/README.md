# ServiceNow webhook 

This guide describes how to integrate Zabbix 5.0 installation with ServiceNow using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.<br>
Please note that recovery and update operations and ServiceNow's custom fields are supported only for trigger-based events.

## Setting up ServiceNow
1\. First, [create](https://docs.servicenow.com/bundle/orlando-platform-administration/page/administer/users-and-groups/task/t_CreateAUser.html) a service user for creating incidents. 

2\. [Assign](https://docs.servicenow.com/bundle/orlando-platform-administration/page/administer/users-and-groups/task/t_AssignARoleToAUser.html) to the newly created user the following roles:<br>
- rest_api_explorer
- sn_incident_write

## Setting up webhook in Zabbix 
1\. Before setting up a ServiceNow Webhook, it is recommended to set up a global macro "*{$ZABBIX.URL}*" containing a URL to the Zabbix frontend.<br>
As an example, this macro can be used to populate ServiceNow's custom field with a URL to an event info or graph.

[![](images/thumb.1.png?raw=true)](images/1.png)

2\. In the "*Administration -> Media types*" section, import the [media_servicenow.xml](media_servicenow.xml)

3\. Open the newly added **ServiceNow** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.<br>
The following parameters are required:<br>
**servicenow_user** - login of the ServiceNow user created earlier<br>
**servicenow_password** - user's password<br>

- By default, the webhook does not use ServiceNow's custom fields and *{ALERT.MESSAGE}* is used as an issue description.<br>
[![](images/thumb.2.png?raw=true)](images/2.png)

- To export information into a ServiceNow custom field, add a parameter with the custom field ID as key (if you need help finding custom field ID, see [this page](https://community.servicenow.com/community?id=community_question&sys_id=c8aa472ddb5cdbc01dcaf3231f96190a) in ServiceNow community). If at least one parameter starting with "u_" is configured for the media type, the webhook will use {TRIGGER.DESCRIPTION} as an incident description instead of {ALERT.MESSAGE}.<br>
[![](images/thumb.3.png?raw=true)](images/3.png)<br>

Date and time must be separated via space (example: "*{EVENT.DATE} {EVENT.TIME}*"). See ServiceNow [documentation](https://docs.servicenow.com/bundle/orlando-platform-administration/page/administer/time/reference/r_FormatDateAndTimeFields.html) for details about date and time format. ServiceNow instance must be in the same timezone as your Zabbix server.

4\. Create a **Zabbix user** and add **Media** with the **ServiceNow** media type.<br>
The **Send to** field must contain the full URL of your ServiceNow instance (https://<INSTANCE>.service-now.com/).<br>
Make sure this user has access to all hosts for which you would like problem notifications to be converted into ServiceNow tasks.

For more information see [Zabbix](https://www.zabbix.com/documentation/current/manual/config/notifications) and [ServiceNow](https://docs.servicenow.com/) documentations.

## Supported Versions
Zabbix 5.0
