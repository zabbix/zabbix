# Event-Driven Ansible webhook

This guide describes how to integrate your Zabbix 6.0 installation with Event-Driven Ansible using the Zabbix webhook feature. This guide will provide instructions on setting up a media type, a user and an action in Zabbix.

## Setting up Event-Driven Ansible Webhook

1\. Make sure you have the webhook plugin loaded from the standard ansible collection (ansible.eda.webhook) and use ansible-rulebook v0.11.0 and higher.

2\. Create a rulebook and specify a webhook from the standard eda collection (ansible.eda.webhook) as the event source. Specify listen address and port.
```
sources:
  - ansible.eda.webhook:
      host: 0.0.0.0
      port: 5001
```
3\. Set necessary actions in rules section. As example you can use:
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


## Setting up Zabbix Webhook

The configuration consists of a _media type_ in Zabbix which will invoke a webhook to send alerts to Event-Driven Ansible.
To utilize the media type, we will create a Zabbix user to represent Event-Driven Ansible. We will then create an alert action to notify the user via this media type whenever a problem is detected.

> Note: only trigger-based and only problem events are currently supported

## Create the Event-Driven Ansible media type

1\. Go to **Administration** tab.

2\. Under Administration, go to the **Media types** page and click the **Import** button.

[![](images/thumb.1.png?raw=true)](images/1.png)

3\. Select the Import file [media_event_driven_ansible.yaml](media_event_driven_ansible.yaml) and click **Import** at the bottom to import the Event-Driven Ansible media type.

## Create the Event-Driven Ansible user for alerting

1\. Go to **Administration** tab.

2\. Under Administration, go to the **Users** page and click **Create user**. Fill in the details of this new user.

[![](images/thumb.2.png?raw=true)](images/2.png)

> Please note: in order to be notified of host problems this user must have at least read permissions for the given host.

3\. Navigate to the **Media** tab and click on the **Add** button inside of the Media box.

4\. Add Media with the **Event-Driven Ansible**. The "Send to" field should be filled as a pair of ip and destination port in the format `xxx.xxx.xxx.xxx:port`. Save user setting.

[![](images/thumb.3.png?raw=true)](images/3.png)

> Note: Because each new rulebook requires a separate port, you have to create a separate user for each rulebook, specifying the ip:port.

5\. Create action. Go to **Configuration** tab. Choose **Actions** > **Trigger actions** and create action for sending events to Event-Driven Ansible.

[![](images/thumb.4.png?raw=true)](images/4.png)

6\. Start getting alerts! You have made it!

For more information see [Zabbix](https://www.zabbix.com/documentation/6.0/manual/config/notifications), [Event-Driven Ansible](https://github.com/ansible/eda-server/blob/main/README.md) and [Ansible-Rulebook](https://ansible-rulebook.readthedocs.io/en/latest/getting_started.html) documentations.

## Supported Versions
Zabbix 6.0 and higher


