![](images/logo.png?raw=true)
# Event-Driven Ansible webhook

## Overview

This guide describes how to integrate your Zabbix installation with Event-Driven Ansible using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Requirements

Zabbix version: 8.0 and higher.

## Parameters

After importing the webhook, you can configure it using webhook parameters.

### Configurable parameters

The configurable parameters are intended to be changed according to the webhook setup as well as the user's preferences and environment.

|Name|Value|Description|
|----|-----|-----------|
|endpoint|/endpoint||
|HTTPProxy|||
|monitoring_source|Zabbix sever||
|tls_verify|\{$HTTP\.TLS\.VERIFY:"Event\-Driven Ansible"\}|TLS certificate verification for HTTP requests: "none" - disabled, "peer" - verify the certificate chain and expiration, "full" - full verification. Any other value enables full verification. To override the setting for this media type only, define the global macro with the context "Event-Driven Ansible", e.g. {$HTTP.TLS.VERIFY:"Event-Driven Ansible"}.|

### Internal parameters

Internal parameters are reserved for predefined macros that are not meant to be changed.

|Name|Value|Description|
|----|-----|-----------|
|acknowledged|\{EVENT\.ACK\.STATUS\}|Acknowledgment status of the event (Yes/No).|
|event_date|\{EVENT\.DATE\}|Date of the event that triggered an action.|
|event_id|\{EVENT\.ID\}|Numeric ID of the event that triggered an action.|
|event_name|\{EVENT\.NAME\}|Name of the problem event that triggered an action.|
|event_nseverity|\{EVENT\.NSEVERITY\}|Numeric value of the event severity. Possible values: 0 - Not classified, 1 - Information, 2 - Warning, 3 - Average, 4 - High, 5 - Disaster.|
|event_object|\{EVENT\.OBJECT\}|Numeric value of the event object. Possible values: 0 - Trigger, 1 - Discovered host, 2 - Discovered service, 3 - Autoregistration, 4 - Item, 5 - Low-level discovery rule.|
|event_severity|\{EVENT\.SEVERITY\}|Name of the event severity.|
|event_source|\{EVENT\.SOURCE\}|Numeric value of the event source. Possible values: 0 - Trigger, 1 - Discovery, 2 - Autoregistration, 3 - Internal, 4 - Service.|
|event_tags|\{EVENT\.TAGSJSON\}|A JSON array containing event tag [objects]('https://www.zabbix.com/documentation/current/manual/api/reference/event/object#event-tag'). Expanded to an empty array if no tags exist.|
|event_time|\{EVENT\.TIME\}|Time of the event that triggered an action.|
|event_value|\{EVENT\.VALUE\}|Numeric value of the event that triggered an action (1 for problem, 0 for recovering).|
|host_groups|\{TRIGGER\.HOSTGROUP\.NAME\}|A sorted (by SQL query), comma-space separated list of host groups in which the trigger is defined.|
|host_host|\{HOST\.HOST\}|Host name.|
|host_id|\{HOST\.ID\}|Host ID.|
|host_ip|\{HOST\.IP\}|Host IP address|
|host_port|\{HOST\.PORT\}|Host (agent) port.|
|operation_data|\{EVENT\.OPDATA\}|Operational data of the underlying trigger of a problem.|
|send_to|\{ALERT\.SENDTO\}|'Send to' value from user media configuration.|
|subject|\{ALERT\.SUBJECT\}|'Default subject' value from action configuration.|
|trigger_description|\{TRIGGER\.DESCRIPTION\}|Trigger description.|
|trigger_id|\{TRIGGER\.ID\}|Numeric ID of the trigger of this action.|
|trigger_name|\{TRIGGER\.NAME\}|Name of the trigger (with macros resolved).|

> Please be aware that each webhook supports an HTTP proxy. To use this feature, add a new media type parameter with the name `http_proxy` and set its value to the proxy URL.

## Service setup

1\. Make sure you have the webhook plugin loaded from the standard ansible collection (ansible.eda.webhook) and use ansible-rulebook v0.11.0 and higher.

2\. Create a rulebook and specify a webhook from the standard eda collection (ansible.eda.webhook) as the event source. Specify listen address and port.
```
sources:
  - ansible.eda.webhook:
      host: 0.0.0.0
      port: 5001
```
3\. Set necessary actions in the rules section. As an example you can use:
```
---
- name: Zabbix Test rulebook
  hosts: all
  sources:
    - ansible.eda.webhook:
        host: 0.0.0.0
        port: 5001
  rules:
    - name: debug
      condition: event.payload is defined
      action:
        debug:
```

4\. For testing you can run ansible-rulebook with command:
```
ansible-rulebook --rulebook test-rulebook.yml -i inventory.yml --verbose
```
> Note: before starting, make sure that the eda-server is running and you are in the eda-server virtual environment

## Zabbix configuration

The configuration consists of a _media type_ in Zabbix which will invoke a webhook to send alerts to Event-Driven Ansible.
To utilize the media type, you need to create a Zabbix user to represent Event-Driven Ansible. Then, create an alert action to notify the user via this media type whenever a problem is detected.

> Note: only trigger-based and only problem events are currently supported

## Create the Event-Driven Ansible media type

1\. In the *Alerts* menu section, select *Media types*.

2\. Click on the **Import** button in the upper right corner.

[![](images/thumb.1.png?raw=true)](images/1.png)

3\. Select the file [media_event_driven_ansible.yaml](media_event_driven_ansible.yaml) and press **Import** at the bottom.

## Create the Event-Driven Ansible user for alerting

1\. In the *Users* menu section, select *Users*.

2\. Click on the **Create user** button in the upper right corner. Fill in the details of this new user.

[![](images/thumb.2.png?raw=true)](images/2.png)

> Please note: in order to be notified of host problems this user must have at least read permissions for the given host.

3\. Navigate to the **Media** tab and click on the **Add** button inside of the Media box.

4\. Configure the media type:
- Set *Type* to *Event-Driven Ansible*.
- In the *Send to* field, specify the IP address and destination port in the format `xxx.xxx.xxx.xxx:port`.
- Press Add to save the media type.

[![](images/thumb.3.png?raw=true)](images/3.png)

5\. Press Add in the User configuration form to save the user.

> Note: Because each new rulebook requires a separate port, you have to create a separate user for each rulebook, specifying the ip:port.

6\. Use Event-Driven Ansible user in any [actions](https://www.zabbix.com/documentation/8.0}/manual/config/notifications/action) of your choice.

[![](images/thumb.4.png?raw=true)](images/4.png)

7\. Start getting alerts! You have made it!

For more information see [Zabbix](https://www.zabbix.com/documentation/8.0}/manual/config/notifications), [Event-Driven Ansible](https://github.com/ansible/eda-server/blob/main/README.md) and [Ansible-Rulebook](https://ansible-rulebook.readthedocs.io/en/latest/getting_started.html) documentations.

## Feedback

Please report any issues with the media type at [`https://support.zabbix.com`](https://support.zabbix.com).

You can also provide feedback, discuss the media type, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback).
