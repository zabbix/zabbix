## Zabbix usage insights notes

### Auditlog info

(See: [ZBXNEXT-2148](https://support.zabbix.com/browse/ZBXNEXT-2148))
(Note these are the last 24h based on `auditlog:clock`)

Auditlog range:

```sql
SELECT FROM_UNIXTIME(MIN(clock)), FROM_UNIXTIME(MAX(clock)) FROM auditlog;
```


Audit info by user:

```sql
SELECT COUNT(al.auditid), u.alias, al.userid
 FROM auditlog al
 JOIN users u ON u.userid=al.userid
 WHERE al.clock > UNIX_TIMESTAMP(SYSDATE() - INTERVAL 1 DAY)
 GROUP BY al.userid;
```


Audit info by user's actions:

```sql
SELECT COUNT(al.auditid), al.action, u.alias, al.userid
 FROM auditlog al
 JOIN users u ON u.userid=al.userid
 WHERE al.clock > UNIX_TIMESTAMP(SYSDATE() - INTERVAL 1 DAY)
 GROUP BY al.userid, al.action;
```


Audit info by action and resources:

```sql
SELECT COUNT(al.auditid), al.action, al.resourcetype
 FROM auditlog al
 WHERE al.clock > UNIX_TIMESTAMP(SYSDATE() - INTERVAL 1 DAY)
 GROUP BY al.action, al.resourcetype;
```

Action type and resource type decoder: [defines.inc.php#L151-L186](https://github.llnw.net/Zabbix/svn.zabbix.com/blob/LLNW-UIAPI/frontends/php/include/defines.inc.php#L151-L186)


### Timer process functions

(rel: [Zabbix: Timer process too busy (high CPU load)](http://crypt47.blogspot.com/2012/12/zabbix-timer-process-too-busy-high-cpu.html))

```sql
SELECT function, count(*)
 FROM functions
 GROUP BY function;
```

```sql
SELECT function, parameter, count(*)
 FROM functions
 WHERE function IN ('nodata', 'date', 'dayofmonth', 'dayofweek', 'time', 'now')
 GROUP BY function, parameter;
```
