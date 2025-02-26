![](images/logo.png?raw=true)
# Zammad webhook

## Overview

This guide describes how to integrate your Zabbix installation with Zammad using the Zabbix webhook feature, providing instructions on setting up a media type, user, and action in Zabbix.

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|zabbix_url|\{$ZABBIX\.URL\}|The URL of the Zabbix frontend.|
|zammad_access_token|\<PUT YOUR ACCESS TOKEN\>|Zammad access token.|
|zammad_customer|\<PUT YOUR CUSTOMER EMAIL\>|Zammad customer email.|
|zammad_group|Users|Zammad user group. Change if needed.|
|zammad_enable_tags|false|Zammad enable tags toggle. Zabbix event tags will be added to Zammad tickets if it is set to one of the following values: 1, true, yes, on.|
|zammad_url|\<PUT YOUR ZAMMAD URL\>|Zammad URL.|

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
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|event_tags|\{EVENT\.TAGSJSON\}|A JSON array containing event tag [objects]('https://www.zabbix.com/documentation/current/manual/api/reference/event/object#event-tag'). Expanded to an empty array if no tags exist.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1. Check that *API Token Access* is enabled in *Settings* > *System* > *API*.

[![](images/thumb.1.png?raw=true)](images/1.png)

2. Open the profile settings of the customer user and create a new *Personal User Token*.

[![](images/thumb.2.png?raw=true)](images/2.png)

3. Set the `ticket.agent` permission for the token and press *Create*.

[![](images/thumb.3.png?raw=true)](images/3.png)

4. Copy and save the created token somewhere, as, for security reasons, it will be shown **only once**!

## Zabbix configuration

1. Before you can start using the Zammad webhook, you need to set up the global macro `{$ZABBIX.URL}`:
  - In the Zabbix web interface, go to *Administration* > *Macros* in the top-left dropdown menu.
  - Set up the global macro `{$ZABBIX.URL}` which will contain the URL to the Zabbix frontend. The URL should be either an IP address, a fully qualified domain name, or a localhost.
  - Specifying a protocol is mandatory, whereas the port is optional. Depending on the web server configuration, you might also need to append `/zabbix` to the end of URL. Good examples:
    - `http://zabbix.com`
    - `https://zabbix.lan/zabbix`
    - `http://server.zabbix.lan/`
    - `http://localhost`
    - `http://127.0.0.1:8080`
  - Bad examples:
    - `zabbix.com`
    - `http://zabbix/`

[![](images/thumb.4.png?raw=true)](images/4.png)

2. Import the media type:
  - In the *Alerts* > *Media types* section, import the [`media_zammad.yaml`](media_zammad.yaml) file.

3. Open the imported Zammad media type and set the following webhook parameters:
  - `zammad_access_token` - the access token that you created during Zammad configuration
  - `zammad_url` - the frontend URL of your Zammad installation
  - `zammad_customer` - the Zammad user email
  - `zammad_enable_tags` - if you want to add the Zabbix event tags to the Zammad tickets that are created, you can set it to one of the following values: `1`, `true`, `yes`, `on` (note that if tag support is enabled, each tag is sent via a separate HTTP request and the created tags will also remain in Zammad when tickets are closed/deleted)
  - `zammad_group` - if needed, you can change the Zammad user group

[![](images/thumb.5.png?raw=true)](images/5.png)

4. If you want to prioritize issues according to the severity values in Zabbix, you can define mapping parameters (create them as additional webhook parameters):
  - `severity_<name>` - the Zammad priority ID (`<name>` in the parameter name can be one of the following values: `not_classified`, `information`, `warning`, `average`, `high`, `disaster`)

[![](images/thumb.6.png?raw=true)](images/6.png)

5. Create a Zabbix user and add media:
  - If you want to create a new user,  go to the *Users* > *Users* section and click the *Create user* button in the top-right corner. In the *User* tab, fill in all the required fields (marked with red asterisks).
  - In the *Media* tab, add a new media and select **Zammad** from the *Type* drop-down list. Though the *Send to* field is not used in the Zammad webhook, it cannot be left empty. To comply with frontend requirements, enter any symbol in the field.
  - Make sure this user has access to all the hosts for which you would like problem notifications to be sent to Zammad.

[![](images/thumb.7.png?raw=true)](images/7.png)

6. Done! You can now start using this media type in actions and create tickets.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/7.4/manual/config/notifications) and [Zammad](https://zammad.org/documentation) documentation.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
