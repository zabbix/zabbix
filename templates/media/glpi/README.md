![](images/logo.png?raw=true)
# GLPi webhook

## Overview

This guide describes how to integrate your Zabbix installation with your GLPi installation using the Zabbix webhook feature, providing instructions on setting up a media type, user, and action in Zabbix.

The webhook supports both legacy REST API (V1) and RESTful API (V2).
This webhook creates tickets in the GLPi Assistance section. Created tickets have the following urgency mapping:

|Severity in Zabbix|Urgency in GLPi|
|-|-|
0 - Not classified| Very low|
1 - Information| Very low|
2 - Warning| Low|
3 - Average| Medium|
4 - High| High|
5 - Disaster| Very high|

- When a problem is updated in Zabbix, the webhook updates the ticket's title and urgency in GLPi and adds a followup entry with the update comment.
- When a problem is resolved in Zabbix, the webhook updates the ticket's title and adds a followup entry with resolution details.
- Created tickets have the status "New" and resolved tickets - "Solved".
- Due to the specifics of the webhook, the number of retries is, by default, set to 1. We recommend not changing this setting; should a transaction error occur, additional duplicate objects (tickets, followups) may be created during the retry.

## Tested on
- GLPi 10.0.18, 10.0.24, 11.0.5, 11.0.6

## Requirements

Zabbix version: 8.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|zabbix_url|\{$ZABBIX\.URL\}|Current Zabbix URL.|
|glpi_legacy_api|false|Boolean value (true/false) to set API version: `false` (default) enables Modern API v2 with OAuth2 Bearer token authentication, `true` enables Legacy API v1 with Session-Token authentication.|
|glpi_app_token||GLPi application token (optional; specify if the token is set in the API client settings).|
|glpi_user_token|\<PLACE GLPI USER TOKEN\>|GLPi user token.|
|glpi_client_id|\<PLACE GLPI CLIENT ID\>|GLPi client ID.|
|glpi_client_secret|\<PLACE GLPI CLIENT SECRET\>|GLPi client secret.|
|glpi_username|\<PLACE GLPI USERNAME\>|GLPi username.|
|glpi_password|\<PLACE GLPI USER PASSWORD\>|GLPi user password.|
|glpi_url|\<PLACE GLPI URL\>|URL of GLPi installation.|
|glpi_urgency_autoregistration|Low|String value of GLPi urgency to assign to autoregistration event tickets.|
|glpi_urgency_discovery|Low|String value of GLPi urgency to assign to discovery event tickets.|
|glpi_urgency_internal|Low|String value of GLPi urgency to assign to internal event tickets.|
|severity_not_classified|Very low|GLPi urgency to assign to tickets when event has Zabbix severity "Not classified".|
|severity_information|Very low|GLPi urgency to assign to tickets when event has Zabbix severity "Information".|
|severity_warning|Low|GLPi urgency to assign to tickets when event has Zabbix severity "Warning".|
|severity_average|Medium|GLPi urgency to assign to tickets when event has Zabbix severity "Average".|
|severity_high|High|GLPi urgency to assign to tickets when event has Zabbix severity "High".|
|severity_disaster|Very high|GLPi urgency to assign to tickets when event has Zabbix severity "Disaster".|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|event_severity|\{EVENT\.SEVERITY\}|Name of the event severity.|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_update_nseverity|\{EVENT\.UPDATE\.NSEVERITY\}|Numeric value of the event update severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_update_severity|\{EVENT\.UPDATE\.SEVERITY\}|Name of the event update severity.|
|event_update_status|\{EVENT\.UPDATE\.STATUS\}|Numeric value of the problem update status. Possible values: 0 - Webhook was called because of problem/recovery event, 1 - Update operation.|
|alert_subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|alert_message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|
|glpi_problem_id|\{EVENT\.TAGS\.\_\_zbx\_glpi\_problem\_id\}|GLPi problem ID.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

### RESTful API (V2) with OAuth2 - recommended configuration for GLPi 11+:

1. Enable access to the GLPi API:
  - In the GLPi web interface, go to *Setup* > *General* > *API*.
  - Switch the toggle to activate *Enable API* and click the *Save* button.

[![](images/thumb.1.png?raw=true)](images/1.png)

2. Add an [OAuth client](https://help.glpi-project.org/documentation/modules/configuration/oauth-clients):
  - Go to *Setup* > *OAuth clients*.
  - Click the *Add* button on the top of the page.
  - Set the client name; enter *api* in the *Scopes* field and *Password* in *Grants*.
  - Click the *Add* button.
  - Open the settings of the created client, and then copy and save the client ID and client secret.

[![](images/thumb.2.png?raw=true)](images/2.png)

3. Create a new [user profile](https://glpi-user-documentation.readthedocs.io/fr/latest/modules/administration/profiles/profiles.html) with permissions to create tickets and followups (alternatively, you can use an existing profile with sufficient privileges):
  - Go to *Administration* > *Profiles* and click the *Add* button on the top of the page.
  - Specify the profile name and set the *Profile's Interface* option to *Standard Interface*, and then click the *Add* button.
  - Open the created profile and click the *Assistance* tab.
  - In the *Tickets* section, set the *Update*, *Create*, and *See all tickets* permissions.
  - In the *Followups/Tasks* section, set the *Add (Requester)* permission for the *Followups* row.
  - Click the *Save* button.

[![](images/thumb.4.png?raw=true)](images/4.png)
[![](images/thumb.5.png?raw=true)](images/5.png)
[![](images/thumb.6.png?raw=true)](images/6.png)

4. Create a new [user](https://glpi-user-documentation.readthedocs.io/fr/latest/modules/administration/users/users.html):
  - Go to *Administration* > *Users* and click the *Add* button on the top of the page.
  - Specify the user login and set the *Authorization* > *Profile* option to the profile you created in the previous step (or any other existing profile with permissions to create tickets and followups).
  - Set the password for the user.
  - Click the *Add* button.

[![](images/thumb.7.png?raw=true)](images/7.png)

> GLPi 11+ continues to support REST API (V1) without requiring OAuth2/V2. Note that V1 is not recommended for new integrations.

### REST API (V1) - legacy configuration for GLPi 10:

1. Enable access to the GLPi REST API:
  - In the GLPi web interface, go to *Setup* > *General* > *API*.
  - Set the *Enable Rest API* and *Enable login with external token* options to *Yes* and click the *Save* button.

[![](images/thumb.legacy_1.png?raw=true)](images/legacy_1.png)

2. Add a new API client:
  - Click the *Add API client* button.
  - Specify the API client name and set the *Active* option to *Yes*.
  - For security reasons, you may want to restrict the API client to the IP address of Zabbix server and/or create an additional application token (will be generated by default; you can uncheck the *Regenerate* checkbox if you don't want to use it).
  - Click the *Add* button.
  - If you've opted to create an application token, open the settings of the created API client, and then copy and save the generated application token.

[![](images/thumb.legacy_2.png?raw=true)](images/legacy_2.png)
[![](images/thumb.legacy_3.png?raw=true)](images/legacy_3.png)

3. Create a new [user profile](https://glpi-user-documentation.readthedocs.io/fr/latest/modules/administration/profiles/profiles.html) with permissions to create tickets and followups (alternatively, you can use an existing profile with sufficient privileges):
  - Go to *Administration* > *Profiles* and click the *Add* button on the top of the page.
  - Specify the profile name and set the *Profile's Interface* option to *Standard Interface*, and then click the *Add* button.
  - Open the created profile and click the *Assistance* tab.
  - Set the *Update*, *Create*, and *See all tickets* permissions in the *Tickets* section.
  - Set the *Add followup (Requester)* permission for the *Followups* line in the *Followups/Tasks* section.
  - Click the *Save* button.

[![](images/thumb.4.png?raw=true)](images/4.png)
[![](images/thumb.legacy_5.png?raw=true)](images/legacy_5.png)
[![](images/thumb.legacy_6.png?raw=true)](images/legacy_6.png)

4. Create a new [user](https://glpi-user-documentation.readthedocs.io/fr/latest/modules/administration/users/users.html):
  - Go to *Administration* > *Users* and click the *Add User* button on the top of the page.
  - Specify the user login and set the *Profiles* option to the profile that you created in the previous step (or any other existing profile with permissions to create tickets and followups).
  - Click the *Add* button.
  - Open the profile of the created user and check the *Regenerate* checkbox of the *API token* option; click *Save*.
  - Copy and save the generated user API token.

[![](images/thumb.legacy_7.png?raw=true)](images/legacy_7.png)
[![](images/thumb.legacy_8.png?raw=true)](images/legacy_8.png)
[![](images/thumb.legacy_9.png?raw=true)](images/legacy_9.png)

## Zabbix configuration

1. Before you can start using the GLPi webhook, you need to set the global macro `{$ZABBIX.URL}`:
  - In the Zabbix web interface, go to *Administration* > *Macros* in the top-left drop-down menu.
  - Set up the global macro `{$ZABBIX.URL}` which will contain the URL to the Zabbix frontend. The URL should be either an IP address, a fully qualified domain name, or a localhost.
  - Specifying a protocol is mandatory, whereas the port is optional. Depending on the web server configuration, you might also need to append `/zabbix` to the end of the URL. Good examples:
    - `http://zabbix.com`
    - `https://zabbix.lan/zabbix`
    - `http://server.zabbix.lan/`
    - `http://localhost`
    - `http://127.0.0.1:8080`
  - Bad examples:
    - `zabbix.com`
    - `http://zabbix/`

[![](images/thumb.8.png?raw=true)](images/8.png)

2. Import the media type:
  - In the *Alerts* > *Media types* section, import the [`media_glpi.yaml`](media_glpi.yaml) file.

3. It is also possible to set GLPi ticket urgency by parameters. Predefined parameter values are already in place; note that they use the default GLPi urgency.

**Please adjust these values to suit your GLPi environment.**

The following parameters apply to Zabbix events that support severities:
  - `severity_not_classified` - for Zabbix severity "Not Classified"
  - `severity_information` - for Zabbix severity "Information"
  - `severity_warning` - for Zabbix severity "Warning"
  - `severity_average` - for Zabbix severity "Average"
  - `severity_high` - for Zabbix severity "High"
  - `severity_disaster` - for Zabbix severity "Disaster"

And the following for Zabbix events that do not have severities:
  - `glpi_urgency_internal` - for Zabbix internal events
  - `glpi_urgency_discovery` - for Zabbix discovery events
  - `glpi_urgency_autoregistration` - for Zabbix autoregistration events

4. Open the imported GLPi media type and set the following webhook parameters:
  - `glpi_url` - the frontend URL of your GLPi installation, without any path suffix (e.g. http://glpi.example.com:8080).
  - `glpi_legacy_api` - determine whether you are using RESTful API V2 with OAuth2 authorization (default, parameter value `false`) or legacy API (parameter value `true`).

  If using RESTful API V2 with OAuth2, set the required parameters:
  - `glpi_client_id` - the client ID that was generated during the creation of the OAuth client.
  - `glpi_client_secret` - the client secret that was generated during the creation of the OAuth client.
  - `glpi_username` - GLPi username created for the webhook.
  - `glpi_password` - password for the GLPi webhook user.

[![](images/thumb.9.png?raw=true)](images/9.png)

  If using legacy API, set the parameters:
  - `glpi_app_token` - if you've opted to use an application token during the creation of API client, specify it here; otherwise leave it empty.
  - `glpi_user_token` - the user token that was generated during the creation of the GLPi user.

[![](images/thumb.10.png?raw=true)](images/10.png)

5. Click the *Enabled* checkbox to enable the media type and click the *Update* button to save the webhook settings.

6. Create a Zabbix user and add media:
  - To create a new user,  go to the *Users* > *Users* section and click the *Create user* button in the top-right corner. In the *User* tab, fill in all the required fields (marked with red asterisks).
  - In the *Media* tab, click *Add* and select *GLPi* from the *Type* drop-down list. Though the *Send to* field is not used in the GLPi webhook, it cannot be left empty. To comply with frontend requirements, enter any symbol in the field.
  - Make sure this user has access to all the hosts for which you would like problem notifications to be sent to GLPi.

[![](images/thumb.11.png?raw=true)](images/11.png)

7. Done! You can now start using this media type in actions and create ticket items in GLPi.

For more information, please see [Zabbix](https://www.zabbix.com/documentation/8.0/manual/config/notifications) and [GLPi](https://glpi-user-documentation.readthedocs.io/fr/latest/) documentation.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
