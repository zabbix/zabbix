# ManageEngine ServiceDesk webhook

This guide describes how to integrate Zabbix 5.4 installation with ManageEngine ServiceDesk (both on-premise and on-demand) using the Zabbix webhook feature. This guide provides instructions on setting up a media type, a user and an action in Zabbix.<br>
Please note that recovery and update operations are supported only for trigger-based events.

## Setting up ManageEngine ServiceDesk
At first, create a user for API or use an existing one.

### Setting up the on-premise installation
1\. Go to *Admin -> Technicians*.<br>
2\. Click the *Add New Technician* link, enter the Technician details and provide login permission.<br>
3\. Click *Generate link* under the API key details block. Select a time frame for the key to expire using the Calendar icon, or simply retain the same key perpetually.<br>
4\. Save TEHNICAN_KEY for use in Zabbix later.<br>

### Setting up the on-demand installation
1\. Go to [Zoho Developer Console](https://api-console.zoho.com/).<br>
2\. Choose *Self Client* from the list of client types, and click *Create Now*.<br>
3\. Click OK in the pop up to enable a self client for your account.<br>
4\. Now, your *Client ID* and *Client secret* are displayed under the *Client Secret* tab. Save them for use in Zabbix later.<br>
5\. Click the *Generate Code* tab and enter the ***SDPOnDemand.requests.ALL*** scope.<br>
6\. Select the *Time Duration* for which the grant token is valid. Please note that after this time, the grant token expires.<br>
7\. Enter a description and click *Generate*.<br>
8\. The generated code for the specified scope is displayed. Copy the grant code.<br>
9\. Make a POST request with the URL params following parameters:<br>
- **code**: enter the Grant Token / Authorization Code generated from previous step.
- **grant_type**: enter the value as "authorization_code".
- **client_id**: specify client_id obtained in step 4.
- **client_secret**: specify client_secret obtained in step 4.
- **redirect_uri**: specify the Callback URL that you registered during the app registration. You can use any ULR for Self client mode, *https://www.zoho.com* for example.

Example:
```
curl -X POST 'https://accounts.zoho.com/oauth/v2/token?code=1000.f74e7b6fc16c95bbc1fa2f067962f84b.9768e796b6273774817032613ba6892a&grant_type=authorization_code&client_id=1000.15S25B602CISR5WO9RUZ8UT39O3RIH&client_secret=9ea302935eb150d9d6cbefd35b1eb8891332d815b8&redirect_uri=https://www.zoho.com'
```
Use your domain-specific Zoho accounts URL when you make the request.<br>
- For US: https://accounts.zoho.com
- For AU: https://accounts.zoho.com.au
- For EU: https://accounts.zoho.eu
- For IN: https://accounts.zoho.in
- For CN: https://accounts.zoho.com.cn

10\. If the request is successful, you will receive the following output:<br>
```{ “access_token”: “1000.2370ff1fd75e968ae780cd8d14841e82.03518d2d1dab9c6c4cf74ae82b89defa”, “refresh_token”: “1000.2afabf2f5a396325e88f715c6de34d12.edce6130ca3832a14e5f80d005a5324d”, “token_type”: “Bearer”, “expires_in”: 3600 }```<br>
Save the *refresh_token* for using in Zabbix later.

## Setting up the webhook in Zabbix
1\. In the *Administration > Media types* section, import [media_manageengine_servicedesk.yaml](media_manageengine_servicedesk.yaml).

2\. Open the newly added **ManageEngine ServiceDesk** media type and replace all *&lt;PLACEHOLDERS&gt;* with your values.<br>

The following parameters are required for on-premise ServiceDesk:<br>
**sd_on_premise** - *true*.<br>
**sd_url** - the URL of your instance.<br>
**sd_on_premise_auth_token** - the TEHNICAN_KEY generated earlier.<br>
**field_ref:requester** - login of the account used for request creation.<br>

The following parameters are required for on-demand ServiceDesk:<br>
**sd_on_premise** - *false**.<br>
**sd_url** - the URL of your instance.<br>
**sd_on_demand_url_auth** - your domain-specific Zoho accounts URL for refreshing access token.<br>
**sd_on_demand_client_id**, **sd_on_demand_client_secret**, **sd_on_demand_refresh_token** - created earlier authentication details.<br>
**field_ref:requester** - requester's displaying name. You can remove this parameter or use any name. *"Zabbix"*, for example. <br>

3\. Create a **Zabbix user** and add **Media** with the **ManageEngine ServiceDesk** media type.
Though a "Send to" field is not used in ManageEngine ServiceDesk webhook, it cannot be empty. To comply with frontend requirements, you can put any symbol there.
Make sure this user has access to all hosts, for which you would like problem notifications to be converted into ManageEngine ServiceDesk tasks.

## Customize your requests
You can add any data to ServiceDesk or user-defined fields.<br>
Please see the [On-demand](https://www.manageengine.com/products/service-desk/sdpod-v3-api/SDPOD-V3-API.html#add-request) and [On-premise](
https://ui.servicedeskplus.com/APIDocs3/index.html#add-request) API specification for details about fields.<br>
Most of fields should be filled as single-line string, other should be an object with *name* property. Zabbix can fill both, but not *"date"* fields.<br>
Supported field types: Single-line, Multi-line, Numeric, Pick List, Email, Phone, Currency, Decimal, Percent, Web URL, Radio Button, Decision Box. All of them should be passed as string.<br>

Fields should be in format **field_string:fieldname**, where:<br>
**field** - can be *field* for system fields or *udf_field* for user-defined fields. The prefix for payload generator.<br>
**string** - should be *string* for single-line strings or any other for *REFERRED_FIELD*.<br>
**:** - separator between prefix and field name.<br>
**fieldname** - the name of ServiceDesk or user-defined field.<br>
Examples:<br>
`field_string:subject`<br>
`field_ref:template`<br>
`udf_field_string:udf_char1`


For more information see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) and [ManageEngine ServiceDesk](https://www.manageengine.com/products/service-desk/support.html) documentations.

## Supported versions
Zabbix 6.0 and higher
