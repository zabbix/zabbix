# SolarWinds Service Desk webhook

This guide describes how to integrate Zabbix 5.0 installation with SolarWinds Service Desk using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.<br>
Please note that recovery and update operations and SolarWinds Service Desk's custom fields are supported only for trigger-based events.


## Setting up webhook in Zabbix 
1\. Before setting up a SolarWinds Service Desk Webhook, it is recommended to setup the global macro "*{$ZABBIX.URL}*" containing an URL to the Zabbix frontend.<br>
As an example, this macro can be used to populate SolarWinds Service Desk's custom field with URL to an event info or graph.

[![](images/thumb.1.png?raw=true)](images/1.png)

2\. In the *Administration > Media types* section, import the [media_solarwinds_servicedesk.yaml](media_solarwinds_servicedesk.yaml).

3\. Open the newly added **SolarWinds Service Desk** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.<br>
The following parameters are required:<br>
**samanage_url** - actual URL of your SolarWinds Service Desk instance,<br>
**samanage_token** - API token (see [SolarWinds Service Desk tutorial](https://help.samanage.com/s/article/Tutorial-Tokens-Authentication-for-API-Integration-1536721557657) for more information).<br>

4\. You can add the following parameters to customize SolarWinds Service Desk ticket:

- `priority_<severity>`: add this parameter for each Zabbix's severity or use only `priority_default`.<br>
`priority_default` is mandatary.<br>
Possible values of `<severity>`:
  - not_classified
  - information
  - warning
  - average
  - high
  - disaster

- `sw_field_<fieldname>`: add this to fill default SolarWinds Service Desk fields, where `<fieldname>` is a name of a field. The parameter can contain a simple value or a JSON string.<br>
Name of the field and value should be consistent with [SolarWinds Service Desk API specification](https://documentation.solarwinds.com/en/Success_Center/swsd/Content/APIdocumentation/Incidents.htm).<br>
_Example:_<br>
[![](images/2.png?raw=true)](images/2.png)<br>
Be careful to use user macro as a value, because special symbols such as quotes can make your JSON invalid.<br>

- `sw_customfield_<fieldname>`: add this to fill preconfigured SolarWinds Service Desk custom field. `<fieldname>` is a name of a field and may contain whitespaces.<br>
_Example:_<br>
[![](images/3.png?raw=true)](images/3.png)<br>


5\. Create a **Zabbix user** and add **Media** with the **SolarWinds Service Desk** media type. 
Though a "Send to" field is not used in SolarWinds Service Desk webhook, it cannot be empty. To comply with frontend requirements, you can put any symbol there.
Make sure this user has access to all hosts for which you would like problem notifications to be converted into SolarWinds Service Desk tickets.

For more information see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [SOLARWINDS](https://documentation.solarwinds.com/en/Success_Center/swsd/Content/SWSD_Getting_Started_Guide.htm) documentations.

## Supported Versions
Zabbix 5.0
