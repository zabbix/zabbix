## Zabbix DB usage insights notes

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


### Item type states

(rel: [LMN-66](https://support.zabbix.com/browse/LMN-66), item unsupported types inflate the queue)

[Type constants](https://github.llnw.net/Zabbix/svn.zabbix.com/blob/2.2.2%2Bllnw.4/frontends/php/include/defines.inc.php#L318-L334)

[Status constants](https://github.llnw.net/Zabbix/svn.zabbix.com/blob/2.2.2%2Bllnw.4/frontends/php/include/defines.inc.php#L358-L360)

*  0 = Active
*  1 = Disabled
*  3 = Not supported

[State constants](https://github.llnw.net/Zabbix/svn.zabbix.com/blob/2.2.2%2Bllnw.4/frontends/php/include/defines.inc.php#L362-L363)

*  0 = Normal
*  1 = Not supported

```sql
SELECT COUNT(itemid), {status,type,state}
 FROM items
 GROUP BY {status,type,state};
```

Not supported by key-name:

```sql
SELECT COUNT(itemid) as count, state, key_
 FROM items
 WHERE state=1 AND status!=1
 GROUP BY key_
 ORDER BY count DESC;
```


## Zabbix UI apache access log insights

### Number of accesses grouped by client type and IP

```sh
grep "13\/Mar\/2014" /var/log/apache2/access.log \
| awk -F[\ \"] '{for(i=17;i<=NF;++i) printf "%s ", $i; printf "%s\n", $1; }' \
| sort | uniq -c | sort -n | less
```

### Number of accesses grouped by client type, IP, and hostname resolution

```sh
grep "13\/Mar\/2014" /var/log/apache2/access.log \
| awk -F[\ \"] '{for(i=17;i<=NF;++i) printf "%s ", $i; printf " %s\n", $1;}' \
| sort | uniq -c | sort -n \
| awk '{d="dig -x " $(NF) " +short | grep -o \".*\" || echo \"--\""; d | getline z; close(d); printf "%s %s\n", $0, z;}' \
| less
```

### Access counts by hour:minute

```sh
grep "13\/Mar\/2014" /var/log/apache2/access.log \
| grep -v "jsrpc.php" \
| awk -F: '{print $2$3}' \
| sort | uniq -c | less
```


## Zabbix server log insights

### Item became not supported counts

(note: adjust cut..f1-2 to 1 for hostname counts or 2 for item counts)

```sh
grep " item \[" /var/log/zabbix/zabbix_server.log \
| awk -F[\]\[] '{print $2}' \
| cut -d':' -f1-2 \
| sort | uniq -c | sort -n \
| less
```

### Agent item failed network error counts

```sh
grep " failed: " /var/log/zabbix/zabbix_server.log \
| grep -o "on host \".*\" failed:" \
| awk -F\" '{print $2}' \
| sort | uniq -c | sort -n \
| less
```

(note: after an agent item has failed it should have a correlated connection restored)

```sh
grep " connection restored" /var/log/zabbix/zabbix_server.log \
| grep -o "on host \".*" \
| awk -F\" '{print $2}' \
| sort | uniq -c | sort -n \
| less
```

### Discovery rule became supported

```sh
grep " discovery rule " /var/log/zabbix/zabbix_server.log \
| awk -F[\]\[] '{print $2,$4}' \
| cut -d':' -f2 \
| sort | uniq -c | sort -n \
| less
```
