![](images/logo.png?raw=true)
# Pushover webhook

## Overview

With Pushover, a user can be notified the most convenient way — with push notification straight to a mobile device.

## Requirements

Zabbix version: 7.4 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|endpoint|https://api\.pushover\.net/1/messages\.json|Pushover API endpoint.|
|expire|1200||
|priority_average|0|Average priority.|
|priority_default|0|Default priority.|
|priority_disaster|0|Disaster priority.|
|priority_high|0|High priority.|
|priority_information|0|Information priority.|
|priority_not_classified|0|Not classified priority.|
|priority_warning|0|Warning priority.|
|retry|60||
|tls_verify|\{$HTTP\.TLS\.VERIFY:"Pushover"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "Pushover", e.g. {$HTTP.TLS.VERIFY:"Pushover"}.|
|token|\<PUSHOVER TOKEN HERE\>|Pushover API token.|
|url|\{$ZABBIX\.URL\}|Current Zabbix URL.|
|url_title|Zabbix|URL title.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|eventid|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|message|\{ALERT\.MESSAGE\}|'Default message' value from action configuration.|
|title|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|triggerid|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|
|user|\{ALERT\.SENDTO\}|'Send to' value from user media configuration.|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

Register the account at https://pushover.net/ and then install Pushover app at your iOS or Android device.

Then [click here](https://pushover.net/apps/clone/zabbix) to create new integration with Zabbix.

[![](images/tn/pushover2.png?raw=true)](images/pushover2.png)

At this point, we have **Application API Token (token)** of the Zabbix application and **Pushover User Key**.

You would need both in Zabbix pushover webhook.

## Zabbix configuration

### Set {$ZABBIX.URL} global macro

Go to Administration->General (Macro) and create new macro that points to your Zabbix frontend

`{$ZABBIX.URL}` = <https://myzabbix.local>

### Setup Pushover media type

Proceed to Administration→ Media types at the Zabbix frontend and find Pushover. If you don't have it, import it from the official Zabbix repository here:

https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/media/pushover

Edit Pushover media type parameters and replace token with your Pushover application key.

[![](images/tn/zabbix1.png?raw=true)](images/zabbix1.png)

### Setup media in user profile

Next, proceed to your User profile and create new Media of Pushover type, use your User key in Send to field.

[![](images/tn/zabbix2.png?raw=true)](images/zabbix2.png)

Also, you can customize Pushover message priority for each Zabbix severity. Change value of **priority_\<severity_name\>** parameter. It must be between -2 and 2.
By default, messages have normal priority (a priority of 0).
For more information check [Pushover documentation](https://pushover.net/api#priority).

### Check trigger actions

Make sure proper trigger actions are set at Configuration→Actions page. For starters, you can enable default "Report problems to Zabbix administrators" rule.

[![](images/tn/zabbix3.png?raw=true)](images/zabbix3.png)

### Finally

You are all set! Now break something to receive a notification :)

[![](images/tn/pushoverapp1.png?raw=true)](images/pushoverapp1.png)

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
