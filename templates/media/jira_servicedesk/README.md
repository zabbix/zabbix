# Jira Service Desk webhook 

This guide describes how to integrate Zabbix 5.0 installation with Jira Service Desk using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.<br>
Please note that recovery and update operations and Jira Service Desk's custom fields are supported only for trigger-based events.

## Setting up webhook in Zabbix 
1\. Before setting up a Jira Service Desk Webhook, it is recommended to set up a global macro "{$ZABBIX.URL}" containing a URL to the Zabbix frontend.<br>
As an example, this macro can be used to populate Jira Service Desk's custom field with a URL to event info or graph.

[![](images/thumb.1.png?raw=true)](images/1.png)

2\. In the "*Administration -> Media types*" section, import the [media_jira_servicedesk.yaml](media_jira_servicedesk.yaml)

3\. Open the newly added Jira Service Desk media type and replace all <PLACEHOLDERS> with your values.<br>
The following parameters are required:<br>
**jira_url** - actual URL of your Jira Service Desk instance,<br>
**jira_user** - Jira Service Desk user login,<br>
**jira_password** - password or API token (for Jira Service Desk Cloud installations an API token can be obtained at https://id.atlassian.com/manage/api-tokens),<br>
**jira_servicedesk_id** - numerical ID of your Jira Service Desk (not to be mistaken with a project ID or a Service Desk key!),<br>
**jira_request_type_id** - numerical ID of your Jira Service Desk RequestType.<br>
[![](images/thumb.2.png?raw=true)](images/2.png)

By default, the webhook does not use Jira Service Desk custom fields. To export information into your Jira Service Desk custom field, add a parameter with custom field ID as a key (if you need help finding custom field ID, see [this page](https://developer.atlassian.com/cloud/jira/service-desk/rest/#api-rest-servicedeskapi-servicedesk-serviceDeskId-requesttype-requestTypeId-field-get) in Jira Service Desk documentation). <br>
[![](images/thumb.3.png?raw=true)](images/3.png)

**Notes:**
- All custom fields should not be hidden from the request form.
- Jira Service Desk webhook supports string, URL, number, and datetime fields for now. 
- Date and time must be in ISO 8601 format with the timezone of the Zabbix server (yyyy-MM-ddTHH:mm:ss+hhmm).
You can use "{EVENT.DATE}T{EVENT.TIME}" pattern, all dots from Zabbix yyyy.MM.dd format will be replaced by dashes.
- If the server time is set to UTC, there is no need to add a timezone at the end of the parameter value.

4\. Create a **Zabbix user** and add **Media** with the **Jira Service Desk** media type. 
Though a "Send to" field is not used in the Jira Service Desk webhook, it cannot be empty. To comply with frontend requirements, you can put any symbol there.
Make sure this user has access to all hosts for which you would like problem notifications to be converted into Jira Service Desk tasks.

For more information see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [Jira Service Desk](https://confluence.atlassian.com/servicedesk) documentations.

## Supported Versions
Zabbix 5.0
