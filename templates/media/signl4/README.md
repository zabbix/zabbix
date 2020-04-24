# SIGNL4 Integration for Zabbix

## Why SIGNL4

SIGNL4 is a mobile alert notification app for powerful alerting, alert management and mobile assignment of work items. It offers alerting via app push, SMS and voice calls including escalations, tracking, and duty scheduling.

Get the app at https://www.signl4.com.

Pairing Zabbix with SIGNL4 can enhance your daily operations by letting you efficiently reach your team members wherever their are.

![SIGNL4](images/signl4-zabbix.png?raw=true)

## Webhook Integraion

This section describes the setup and configuration of the SIGNL4 webhook for Zabbix:

1. Get SIGNL4  
If not already done, sign up for your SIGNL4 account at https://www.signl4.com or directly from within your SIGNL4 app you can download from the Play Store or App Store.

2. Get the Webhook XML  
Get the XML file (media_signl4.xml) for Zabbix from [Git](https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/media/signl4).

3. Import the Media Type  
In Zabbix you can now import a new Media Type under Administration -> Media types -> Import. Select the file zabbix-signl4.xml here.

![Zabbix Media Type](images/zabbix-webhook-media-type.png?raw=true)

The parameter "teamsecret" is filled from the user's "Sent to" field by default. **Please note that "Send to" field is exposed in list of problems. If there are multiple Zabbix users with access to problem list it is recomended to set value of the "teamsecret" parameter directly in the mediatype configuration to avoid exposure of "teamsecret".**
The other parameters are flexible and you can add, remove or adapt them as needed.

4. Add Media Type to a User  
Under Administration -> Users, create a dedicated user and add the media type we have created above.
Sent to: insert the teamsecret of your SIGNL4 team. This is the last part of your webhook URL: https://connect.signl4.com/webhook/<teamsecret>.

![User](images/zabbix-webhook-user.png?raw=true)

Please note that this user represents your SIGNL4 team, so it is more a team, than a single user in this case.

5. Create an Action  
Under Configuration -> Actions you can create an Action that will send the notification to the SIGNL4 user, created in step 4.

![Action](images/zabbix-script-action.png?raw=true)

6. Test it  
Now you can trigger a problem that will call the action, configured in step 5, to send the alert notification to your SIGNL4 user.

You can find the package in Git:
https://git.zabbix.com/projects/ZBX/repos/zabbix/browse/templates/media/signl4
