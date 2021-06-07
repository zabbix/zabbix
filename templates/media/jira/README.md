# Jira webhook

This guide describes how to integrate Zabbix 5.0 installation with Jira using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.<br>
Please note that recovery and update operations and Jira's custom fields are supported only for trigger-based events.


## Setting up webhook in Zabbix 
1\. Before setting up a Jira Webhook, it is recommended to setup the global macro `{$ZABBIX.URL}` containing an URL to the Zabbix frontend.<br>
As an example, this macro can be used to populate Jira's custom field with URL to an event info or graph.  

[![](images/thumb.1.png?raw=true)](images/1.png)

2\. In the *Administration > Media types* section, import the [media_jira.yaml](media_jira.yaml).

3\. Open the newly added **Jira** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.<br>
The following parameters are required:<br>
**jira_url** - actual URL of your Jira instance,<br>
**jira_user** - Jira user login,<br>
**jira_password** - password or API token (for Jira Cloud installations an API token can be obtained at https://id.atlassian.com/manage/api-tokens),<br>
**jira_project_key** - text key of the Jira project (not to be mistaken with an ID!),<br>
**jira_issue_type** - name of the issue type to be used when creating new issues from Zabbix notifications.<br>

4\. You can customize Jira issue by export information into Jira custom fields.<br>
By default the webhook does not use Jira custom fields and `{ALERT.MESSAGE}` is used as an issue description.<br>

To export information into your Jira custom field, add a parameter with custom field ID as key (if you need help finding custom field ID, see [this page](https://confluence.atlassian.com/jirakb/how-to-find-id-for-custom-field-s-744522503.html) in Jira documentation). If at least one parameter starting with "customfield_" is configured for the media type, the webhook will use `{TRIGGER.DESCRIPTION}` as an issue description instead of `{ALERT.MESSAGE}` to avoid duplication of information in description and custom fields.<br>
We recommend creating custom fields for all of `{ALERT.MESSAGE}` fields like "Host", "Link to event info", "Trigger ID", "Event date", etc.
[![](images/thumb.3.png?raw=true)](images/3.png)<br>

Jira webhook supports the following custom fields types:
- String
- URL
- Number
- Datetime
- Single-select
- Multi-select
- Checkbox
- Radio button

*Examples:*<br>
[![](images/2.png?raw=true)](images/2.png)<br>
You can use combined parameter value as `{$ZABBIX.URL}/tr_events.php?triggerid={TRIGGER.ID}&eventid={EVENT.ID}` or `{EVENT.DATE}T{EVENT.TIME}`.

Note, that you can pass only one value for each custom field including multi-select and checkbox.<br>

Date and time must be in ISO 8601 format with timezone of the Zabbix server (2020-01-01T23:59:59+0200).
If server time is set to UTC, there is no need to add timezone at the end of parameter value.

5\. Create a **Zabbix user** and add **Media** with the **Jira** media type. 
Though a "Send to" field is not used in Jira webhook, it cannot be empty. To comply with frontend requirements, you can put any symbol there.
Make sure this user has access to all hosts for which you would like problem notifications to be converted into Jira tasks.

For more information see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [Jira](https://support.atlassian.com/jira-software-cloud/) documentations.

## Supported Versions
Zabbix 5.0
