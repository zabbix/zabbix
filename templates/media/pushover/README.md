# Zabbix Pushover webhook integration

[![](images/pushover_logo.png?raw=true)](images/pushover_logo.png)

With Pushover, a user can be notified the most convenient way — with push notification straight to a mobile device.

## Pushover setup

Register the account at https://pushover.net/ and then install Pushover app at your iOS or Android device.

Then [click here](https://pushover.net/apps/clone/zabbix) to create new integration with Zabbix.

[![](images/tn/pushover2.png?raw=true)](images/pushover2.png)

At this point, we have **Application API Token (token)** of the Zabbix application and **Pushover User Key**.

You would need both in Zabbix pushover webhook.

## Zabbix setup

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

Also, you can customize Pushover message priority for each Zabbix severity. Change value of **priority_\<severity_name\>** parameter. It must be between -2 and 2.<br>
By default, messages have normal priority (a priority of 0).
For more information check [Pushover documentation](https://pushover.net/api#priority).

### Check trigger actions

Make sure proper trigger actions are set at Configuration→Actions page. For starters, you can enable default "Report problems to Zabbix administrators" rule.

[![](images/tn/zabbix3.png?raw=true)](images/zabbix3.png)

## Finally

You are all set! Now break something to receive a notification :)

[![](images/tn/pushoverapp1.png?raw=true)](images/pushoverapp1.png)
