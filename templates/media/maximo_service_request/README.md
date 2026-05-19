![](images/logo.png?raw=true)
# IBM Maximo Service Request webhook

## Overview

This guide describes how to integrate your Zabbix installation with IBM Maximo Service Request using its API and the Zabbix webhook feature, providing instructions on setting up a media type, user, and action in Zabbix.

### Supported features:
* Service request creation
* Custom reported priority
* Custom asset number
* Custom classification IDs

## Requirements

Zabbix version: 7.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|api_endpoint|\<ENTER YOUR API ENDPOINT\>|IBM Maximo API endpoint.|
|api_key|\<ENTER YOUR API KEY\>|API key that will be used to access IBM Maximo.|
|reported_priority||Use a custom reported priority here instead of event severity.|
|use_oslc_format|true|`true` to use OSLC API, `false` to use REST API.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_severity|\{EVENT\.SEVERITY\}|Name of the event severity.|
|event_update_nseverity|\{EVENT\.UPDATE\.NSEVERITY\}|Numeric value of the event update severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_update_severity|\{EVENT\.UPDATE\.SEVERITY\}|Name of the event update severity.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|event_name|\{EVENT\.NAME\}|Name of the problem event that triggered an action.|
|class_structure_id|\{ALERT\.SENDTO\}|A numeric ID of the under which the Service Request will be created.|
|asset_number|\{INVENTORY\.ASSET\.TAG\}|Inventory asset tag.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. In IBM Maximo, create an API key in *Administration Work Center* under the *Integration* tab.

2\. Grant permissions to the MXAPISR data set in *Security Groups* / *Object Structures*.

3\. Configure the endpoint in *Integration* > *External Systems* and *Enterprise Services*.

## Zabbix configuration

1. Import the media type:
- In the *Alerts* > *Media types* section, import the `media_maximo_service_request.yaml` file.

2. Open the imported **IBM Maximo Service Request** media type and set the following webhook parameters:
- `api_endpoint` - the address of the IBM Maximo Service Request endpoint.
- `api_key` - the key to access the API.
- `reported_priority` - optional. Reported priority to create the Service Request with. Leave empty to use the event severity from Zabbix.
- `use_oslc_format` - optional, boolean. `true` to use OSLC API, `false` to use REST API. Default value - `true`.

3. Click the *Enabled* checkbox to enable the media type and click the *Update* button to save the webhook settings.

4. Create a Zabbix user and add media:
  - To create a new user, go to the *Users* > *Users* section and click the *Create user* button in the top-right corner. In the *User* tab, fill in all the required fields (marked with red asterisks).
  - Make sure this user has access to all the hosts for which you would like problem notifications to be sent to IBM Maximo Service Request.
  - In the *Media* tab, click *Add* and select *IBM Maximo Service Request* from the *Type* drop-down list.
  - In the *Send to* field, specify the `classstructureid` - the classification ID under which to create the Service Request.

5. Done! You can now start using this media type in actions and send notifications.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
