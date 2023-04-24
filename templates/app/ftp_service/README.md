
# FTP Service


### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|FTP service is running||Simple check|net.tcp.service[ftp]|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|FTP service is down on {HOST.NAME}||`max(/FTP Service/net.tcp.service[ftp],#3)=0`|Average||

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

