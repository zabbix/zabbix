# Spiceworks integration guide

This guide describes how to integrate Zabbix 5.0 installation with Spiceworks using the Zabbix email mediatype. This guide provides instructions on setting up a user and an action in Zabbix.<br>
Please note that recovery and update operations are unavailable for Spiceworks.

## Setting up Zabbix
1\. Set up an [Email media](https://www.zabbix.com/documentation/6.0/manual/config/notifications/media/email) type if it doesn't exist yet.

2\. Create a **Zabbix user** and add **Media** with the Email media type. <br>
The **Send to** field must contain your helpdesk email address (e.g. help@zabbix.on.spiceworks.com).<br>
Make sure this user has access to all hosts for which you would like the problem notifications to be converted into Spiceworks tickets.<br>
[![](images/thumb.1.png?raw=true)](images/1.png)

3\. Configure a [Zabbix action](https://www.zabbix.com/documentation/6.0/manual/config/notifications/action) without recovery and update operations. Since Zabbix doesn't store Spiceworks ticket number, update notifications will result in creating separate tickets instead of updating an existing problem.  
[![](images/thumb.2.png?raw=true)](images/2.png)

See [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications) documentation for more information.
