## Zabbix usage insights notes

### Auditlog info

(Note these are the last 24h based on `auditlog:clock`)


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
