
# ICMP Ping

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$ICMP_LOSS_WARN}||`20`|
|{$ICMP_RESPONSE_TIME_WARN}||`0.15`|

### Items

|Name|Description|Type|Key and additional info|
|----|-----------|----|-----------------------|
|ICMP: ICMP ping||Simple check|icmpping|
|ICMP: ICMP loss||Simple check|icmppingloss|
|ICMP: ICMP response time||Simple check|icmppingsec|

### Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----------|--------|--------------------------------|
|ICMP: Unavailable by ICMP ping|<p>Last three attempts returned timeout.  Please check device connectivity.</p>|`max(/ICMP Ping/icmpping,#3)=0`|High||
|ICMP: High ICMP ping loss||`min(/ICMP Ping/icmppingloss,5m)>{$ICMP_LOSS_WARN} and min(/ICMP Ping/icmppingloss,5m)<100`|Warning|**Depends on**:<br><ul><li>ICMP: Unavailable by ICMP ping</li></ul>|
|ICMP: High ICMP ping response time||`avg(/ICMP Ping/icmppingsec,5m)>{$ICMP_RESPONSE_TIME_WARN}`|Warning|**Depends on**:<br><ul><li>ICMP: High ICMP ping loss</li><li>ICMP: Unavailable by ICMP ping</li></ul>|

## Feedback

Please report any issues with the template at [`https://support.zabbix.com`](https://support.zabbix.com)

You can also provide feedback, discuss the template, or ask for help at [`ZABBIX forums`](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback)

