# VictorOps webhook

This guide describes how to integrate Zabbix 5.4 installation with VictorOps using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.<br>


## Setting up VictorOps
1\. Go to *Integrations -> 3rd Party Integrations*<br>

2\. Create *REST* integration. See VictorOps [documentation](https://help.victorops.com/knowledge-base/rest-endpoint-integration-guide/) for the information.<br>

3\. Save endpoint URL from *URL to notify* field.


## Setting up the webhook in Zabbix
1\. In the *Administration > Media types* section, import [media_victorops.yaml](media_victorops.yaml).

2\. Open the newly added **VictorOps** media type and replace *&lt;PLACE ENDPOINT URL HERE&gt;* placeholder with your REST integration endpoint URL.<br>
The following parameters should be filled:<br>
**vops_endpoint** - URL of your VictorOps REST endpoint.<br>
**vops_routing_key** - routing key of the escalation policy.<br>

3\. The following parameters can help you customize the alerts ([documentation](https://help.victorops.com/knowledge-base/incident-fields-glossary/#glossary-of-fields) for the information):<br>
**priority_severity** - value for the VictorOps *message_type* field. *severity* is the severity's name in the default Zabbix installation.<br>
*priority_update* is a mandatory field set as *"INFO"* by default. If you want to create an incident for every event on update operation (include manual close), pass *"ACKNOWLEDGMENT"* as the value of this parameter.<br>
*message_type* is used to determine the behavior of the alert when it arrives.<br>
**field:Hostname** or **field_p:Hostname** - contains data for custom fields. "Field" parameters with another format or empty value will be ignored.<br>
Format explanation:<br>
- *field* - prefix of the parameter with field info.
- *p* - optional. Used if the field should be sent only on problem/recovery/update operation. Possible values:
    - *p* - problem
    - *r* - recovery
    - *u* - update
- *Host* - the title of the field. There can be any text that contains characters and "_" symbol. Whitespaces and special symbols are not allowed.

4\. Create a **Zabbix user** and add **Media** with the **VictorOps** media type.
"Send to" field should be filled as "Default" or your routing key.<br>
Make sure this user has access to all hosts, for which you would like problem notifications to be converted into VictorOps tasks.

For more information see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [VictorOps](https://help.victorops.com/) documentations.

## Supported versions
Zabbix 6.0 and higher
