# User guide to setting up Zabbix monitoring of the Morningstar products

Zabbix provides a powerful automated solution for monitoring the state of Morningstar products. For example, if the device battery voltage is too low or a battery temperature drops below a given threshold, the system will generate an alert and send it to your preferred notification channel (e-mail/phone/Jira/Slack/or many others). The full list of alerts depends on the device - see the individual template documentation for details. This guide explains how to get Zabbix and configure the software for monitoring.

## Pre-requisites and installation

Zabbix is an open-source product that can be installed on a majority of Unix-like distributions at no cost  - see [full list of supported distributions](https://www.zabbix.com/download). Alternatively, Zabbix is available on certain [cloud services](https://www.zabbix.com/cloud_images).

1. Install Zabbix following instructions from the [downloads page](https://www.zabbix.com/download) for your preferred installation method.  

2. Download monitoring templates suitable for your Morningstar products to the computer.

3. Supported Morningstar products

| Product           | Readme                                     | Template                                                      |
|-------------------|--------------------------------------------|---------------------------------------------------------------|
| Prostar MPPT      | [Readme](prostar_mppt_snmp/README.md)      | [Template](prostar_mppt_snmp/prostar_mppt_snmp.xml)           |
| Prostar PWM       | [Readme](prostar_pwm_snmp/README.md)       | [Template](prostar_pwm_snmp/prostar_pwm_snmp.xml)             |
| Sunsaver MPPT     | [Readme](sunsaver_mppt_snmp/README.md)     | [Template](sunsaver_mppt_snmp/sunsaver_mppt_snmp.xml)         |
| Suresine          | [Readme](suresine_snmp/README.md)          | [Template](suresine_snmp/suresine_snmp.xml)                   |
| Tristar MPPT 600V | [Readme](tristar_mppt_600V_snmp/README.md) | [Template](tristar_mppt_600V_snmp/tristar_mppt_600V_snmp.xml) |
| Tristar MPPT      | [Readme](tristar_mppt_snmp/README.md)      | [Template](tristar_mppt_snmp/tristar_mppt_snmp.xml)           |
| Tristar PWM       | [Readme](tristar_pwm_snmp/README.md)       | [Template](tristar_pwm_snmp/tristar_pwm_snmp.xml)             |

## Zabbix set up

1. Log in to Zabbix.

1. Using a sidebar menu at the left, navigate to the *Configuration -> Templates* section.
Import the downloaded template into Zabbix by following these steps:

- Press the *Import* button in the top right corner
- Select the XML file of the required template on your machine
- Press *Import*

1. Now you need to teach Zabbix how to connect to the device.
To do so, first create a host to represent your Morningstar device:

- Using a sidebar menu at the left, navigate to the _Configuration -> Hosts_ section
- Press the *Create host* button in the top right corner
- In the Host configuration window fill in the required fields:

  - *Host name* -  enter any unique name
  - *Groups* - select an existing host group or enter the name of a new group to be created
  - *Interfaces* - press Add and select SNMP from the drop-down list that appears.

- Add an SNMP interface for the host:
  - Enter the IP address/DNS name and port number
  - Select the SNMP v2 from the dropdown
  - In the *SNMP community* field enter 'public'
  - Turn off the *Use bulk requests* checkbox because devices do not work correctly in this mode
- Open the *Templates* tab. In the *Link new templates* field, start typing Morningstar, then select the imported template from the list.

1. Repeat step 3 for each Morningstar device you want to monitor.

By now, Zabbix is configured to generate alerts if something goes wrong with the device. The alerts will be displayed in the Zabbix dashboard. If you would like to receive notifications about problems by e-mail, phone, or some other channel, follow instructions for configuring [notifications upon events](https://www.zabbix.com/documentation/5.0/manual/config/notifications) provided in Zabbix documentation.
