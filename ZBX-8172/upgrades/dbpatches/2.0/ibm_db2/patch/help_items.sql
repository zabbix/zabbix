DROP TABLE help_items
/

CREATE TABLE help_items (
	itemtype	integer		WITH DEFAULT '0'	NOT NULL,
	key_		varchar(255)	WITH DEFAULT ''		NOT NULL,
	description	varchar(255)	WITH DEFAULT ''		NOT NULL,
	PRIMARY KEY (itemtype,key_)
)
/

INSERT INTO help_items (itemtype,key_,description) values ('0','agent.ping','Check the agent usability. Always return 1. Can be used as a TCP ping.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','agent.version','Version of zabbix_agent(d) running on monitored host. String value. Example of returned value: 1.1')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','kernel.maxfiles','Maximum number of opened files supported by OS.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','kernel.maxproc','Maximum number of processes supported by OS.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','net.dns.record[&lt;ip&gt;,zone,&lt;type&gt;,&lt;timeout&gt;,&lt;count&gt;]','Performs a DNS query. On success returns a character string with the required type of information.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','net.dns[&lt;ip&gt;,zone,&lt;type&gt;,&lt;timeout&gt;,&lt;count&gt;]','Checks if DNS service is up. 0 - DNS is down (server did not respond or DNS resolution failed), 1 - DNS is up.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','net.if.collisions[if]','Out-of-window collision. Collisions count.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','net.if.in[if,&lt;mode&gt;]','Network interface input statistic. Integer value. If mode is missing bytes is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','net.if.list','List of network interfaces. Text value.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','net.if.out[if,&lt;mode&gt;]','Network interface output statistic. Integer value. If mode is missing bytes is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','net.if.total[if,&lt;mode&gt;]','Sum of network interface incoming and outgoing statistics. Integer value. Mode - one of bytes (default), packets, errors or dropped')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','net.tcp.listen[port]','Checks if this port is in LISTEN state. 0 - it is not, 1 - it is in LISTEN state.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','net.tcp.port[&lt;ip&gt;,port]','Check, if it is possible to make TCP connection to the port number. 0 - cannot connect, 1 - can connect. IP address is optional. If ip is missing, 127.0.0.1 is used. Example: net.tcp.port[,80]')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','net.tcp.service.perf[service,&lt;ip&gt;,&lt;port&gt;]','Check performance of service &quot;service&quot;. 0 - service is down, sec - number of seconds spent on connection to the service. If ip is missing 127.0.0.1 is used.  If port number is missing, default service port is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','net.tcp.service[service,&lt;ip&gt;,&lt;port&gt;]','Check if service is available. 0 - service is down, 1 - service is running. If ip is missing 127.0.0.1 is used. If port number is missing, default service port is used. Example: net.tcp.service[ftp,,45].')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','perf_counter[counter,&lt;interval&gt;]','Value of any performance counter, where "counter" parameter is the counter path and "interval" parameter is a number of last seconds, for which the agent returns an average value.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','proc.mem[&lt;name&gt;,&lt;user&gt;,&lt;mode&gt;,&lt;cmdline&gt;]','Memory used by process with name name running under user user. Memory used by processes. Process name, user and mode is optional. If name or user is missing all processes will be calculated. If mode is missing sum is used. Example: proc.mem[,root]')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','proc.num[&lt;name&gt;,&lt;user&gt;,&lt;state&gt;,&lt;cmdline&gt;]','Number of processes with name name running under user user having state state. Process name, user and state are optional. Examples: proc.num[,mysql]; proc.num[apache2,www-data]; proc.num[,oracle,sleep,oracleZABBIX]')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','proc_info[&lt;process&gt;,&lt;attribute&gt;,&lt;type&gt;]','Different information about specific process(es)')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','service_state[service]','State of service. 0 - running, 1 - paused, 2 - start pending, 3 - pause pending, 4 - continue pending, 5 - stop pending, 6 - stopped, 7 - unknown, 255 - no such service')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.boottime','Timestamp of system boot.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.cpu.intr','Device interrupts.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.cpu.load[&lt;cpu&gt;,&lt;mode&gt;]','CPU(s) load. Processor load. The cpu and mode are optional. If cpu is missing all is used. If mode is missing avg1 is used. Note that this is not percentage.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.cpu.num','Number of available proccessors.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.cpu.switches','Context switches.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.cpu.util[&lt;cpu&gt;,&lt;type&gt;,&lt;mode&gt;]','CPU(s) utilisation. Processor load in percents. The cpu, type and mode are optional. If cpu is missing all is used.  If type is missing user is used. If mode is missing avg1 is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.hostname[&lt;type&gt;]','Returns hostname (or NetBIOS name (by default) on Windows). String value. Example of returned value: www.zabbix.com')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.hw.chassis[&lt;info&gt;]','Chassis info - returns full info by default')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.hw.cpu[&lt;cpu&gt;,&lt;info&gt;]','CPU info - lists full info for all CPUs by default')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.hw.devices[&lt;type&gt;]','Device list - lists PCI devices by default')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.hw.macaddr[&lt;interface&gt;,&lt;format&gt;]','MAC address - lists all MAC addresses with interface names by default')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.localtime','System local time. Time in seconds.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.run[command,&lt;mode&gt;]','Run specified command on the host.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.stat[resource,&lt;type&gt;]','Virtual memory statistics.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.sw.arch','Software architecture')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.sw.os[&lt;info&gt;]','Current OS - returns full info by default')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.sw.packages[&lt;package&gt;,&lt;manager&gt;,&lt;format&gt;]','Software package list - lists all packages for all supported package managers by default')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.swap.in[&lt;swap&gt;,&lt;type&gt;]','Swap in. If type is count - swapins is returned. If type is pages - pages swapped in is returned. If swap is missing all is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.swap.out[&lt;swap&gt;,&lt;type&gt;]','Swap out. If type is count - swapouts is returned. If type is pages - pages swapped in is returned. If swap is missing all is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.swap.size[&lt;swap&gt;,&lt;mode&gt;]','Swap space. Number of bytes. If swap is missing all is used. If mode is missing free is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.uname','Returns detailed host information. String value')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.uptime','System uptime in seconds.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','system.users.num','Number of users connected. Command who is used on agent side.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','vfs.dev.read[device,&lt;type&gt;,&lt;mode&gt;]','Device read statistics.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','vfs.dev.write[device,&lt;type&gt;,&lt;mode&gt;]','Device write statistics.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','vfs.file.cksum[file]','Calculate check sum of a given file. Check sum of the file calculate by standard algorithm used by UNIX utility cksum. Example: vfs.file.cksum[/etc/passwd]')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','vfs.file.contents[file,&lt;encoding&gt;]','Get contents of a given file.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','vfs.file.exists[file]','Check if file exists. 0 - file does not exist, 1 - file exists')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','vfs.file.md5sum[file]','Calculate MD5 check sum of a given file. String MD5 hash of the file. Can be used for files less than 64MB, unsupported otherwise. Example: vfs.file.md5sum[/usr/local/etc/zabbix_agentd.conf]')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','vfs.file.regexp[file,regexp,&lt;encoding&gt;]','Find string in a file. Matched string')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','vfs.file.regmatch[file,regexp,&lt;encoding&gt;]','Find string in a file. 0 - expression not found, 1 - found')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','vfs.file.size[file]','Size of a given file. Size in bytes. File must have read permissions for user zabbix. Example: vfs.file.size[/var/log/syslog]')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','vfs.file.time[file,&lt;mode&gt;]','File time information. Number of seconds.The mode is optional. If mode is missing modify is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','vfs.fs.inode[fs,&lt;mode&gt;]','Number of inodes for a given volume. If mode is missing total is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','vfs.fs.size[fs,&lt;mode&gt;]','Calculate disk space for a given volume. Disk space in KB. If mode is missing total is used.  In case of mounted volume, unused disk space for local file system is returned. Example: vfs.fs.size[/tmp,free].')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','vm.memory.size[&lt;mode&gt;]','Amount of memory size in bytes. If mode is missing total is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','web.page.get[host,&lt;path&gt;,&lt;port&gt;]','Get content of WEB page. Default path is /')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','web.page.perf[host,&lt;path&gt;,&lt;port&gt;]','Get timing of loading full WEB page. Default path is /')
/
INSERT INTO help_items (itemtype,key_,description) values ('0','web.page.regexp[host,&lt;path&gt;,&lt;port&gt;,&lt;regexp&gt;,&lt;length&gt;]','Get first occurence of regexp in WEB page. Default path is /')
/
INSERT INTO help_items (itemtype,key_,description) values ('3','icmppingloss[&lt;target&gt;,&lt;packets&gt;,&lt;interval&gt;,&lt;size&gt;,&lt;timeout&gt;]','Returns percentage of lost ICMP ping packets.')
/
INSERT INTO help_items (itemtype,key_,description) values ('3','icmppingsec[&lt;target&gt;,&lt;packets&gt;,&lt;interval&gt;,&lt;size&gt;,&lt;timeout&gt;,&lt;mode&gt;]','Returns ICMP ping response time in seconds. Example: 0.02')
/
INSERT INTO help_items (itemtype,key_,description) values ('3','icmpping[&lt;target&gt;,&lt;packets&gt;,&lt;interval&gt;,&lt;size&gt;,&lt;timeout&gt;]','Checks if server is accessible by ICMP ping. 0 - ICMP ping fails. 1 - ICMP ping successful. One of zabbix_server processes performs ICMP pings once per PingerFrequency seconds.')
/
INSERT INTO help_items (itemtype,key_,description) values ('3','net.tcp.service.perf[service,&lt;ip&gt;,&lt;port&gt;]','Check performance of service. 0 - service is down, sec - number of seconds spent on connection to the service. If &lt;ip&gt; is missing, IP or DNS name is taken from host definition. If &lt;port&gt; is missing, default service port is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('3','net.tcp.service[service,&lt;ip&gt;,&lt;port&gt;]','Check if service is available. 0 - service is down, 1 - service is running. If &lt;ip&gt; is missing, IP or DNS name is taken from host definition. If &lt;port&gt; is missing, default service port is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[boottime]','Startup time of Zabbix server, Unix timestamp.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[history]','Number of values stored in table HISTORY.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[history_log]','Number of values stored in table HISTORY_LOG.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[history_str]','Number of values stored in table HISTORY_STR.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[history_text]','Number of values stored in table HISTORY_TEXT.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[history_uint]','Number of values stored in table HISTORY_UINT.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[host,&lt;type&gt;,available]','Returns availability of a particular type of checks on the host. Value of this item corresponds to availability icons in the host list. Valid types are: agent, snmp, ipmi, jmx.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[items]','Number of items in Zabbix database.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[items_unsupported]','Number of unsupported items in Zabbix database.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[java,,&lt;param&gt;]','Returns information associated with Zabbix Java gateway. Valid params are: ping, version.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[process,&lt;type&gt;,&lt;num&gt;,&lt;state&gt;]','Time a particular Zabbix process or a group of processes (identified by &lt;type&gt; and &lt;num&gt;) spent in &lt;state&gt; in percentage.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[proxy,&lt;name&gt;,&lt;param&gt;]','Time of proxy last access. Name - proxy name. Param - lastaccess. Unix timestamp.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[queue,&lt;from&gt;,&lt;to&gt;]','Number of items in the queue which are delayed by from to to seconds, inclusive.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[requiredperformance]','Required performance of the Zabbix server, in new values per second expected.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[rcache,&lt;cache&gt;,&lt;mode&gt;]','Configuration cache statistics. Cache - buffer (modes: pfree, total, used, free).')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[trends]','Number of values stored in table TRENDS.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[trends_uint]','Number of values stored in table TRENDS_UINT.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[triggers]','Number of triggers in Zabbix database.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[uptime]','Uptime of Zabbix server process in seconds.')
/
INSERT INTO help_items (itemtype,key_,description) values ('5','zabbix[wcache,&lt;cache&gt;,&lt;mode&gt;]','Data cache statistics. Cache - one of values (modes: all, float, uint, str, log, text), history (modes: pfree, total, used, free), trend (modes: pfree, total, used, free), text (modes: pfree, total, used, free).')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','agent.ping','Check the agent usability. Always return 1. Can be used as a TCP ping.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','agent.version','Version of zabbix_agent(d) running on monitored host. String value. Example of returned value: 1.1')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','eventlog[logtype,&lt;pattern&gt;,&lt;severity&gt;,&lt;source&gt;,&lt;eventid&gt;,&lt;maxlines&gt;,&lt;mode&gt;]','Monitoring of Windows event logs. pattern, severity, eventid - regular expressions')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','kernel.maxfiles','Maximum number of opened files supported by OS.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','kernel.maxproc','Maximum number of processes supported by OS.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','logrt[file_format,&lt;pattern&gt;,&lt;encoding&gt;,&lt;maxlines&gt;,&lt;mode&gt;]','Monitoring of log file with rotation. fileformat - [path][regexp], pattern - regular expression')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','log[file,&lt;pattern&gt;,&lt;encoding&gt;,&lt;maxlines&gt;,&lt;mode&gt;]','Monitoring of log file. pattern - regular expression')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','net.dns.record[&lt;ip&gt;,zone,&lt;type&gt;,&lt;timeout&gt;,&lt;count&gt;]','Performs a DNS query. On success returns a character string with the required type of information.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','net.dns[&lt;ip&gt;,zone,&lt;type&gt;,&lt;timeout&gt;,&lt;count&gt;]','Checks if DNS service is up. 0 - DNS is down (server did not respond or DNS resolution failed), 1 - DNS is up.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','net.if.collisions[if]','Out-of-window collision. Collisions count.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','net.if.in[if,&lt;mode&gt;]','Network interface input statistic. Integer value. If mode is missing bytes is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','net.if.list','List of network interfaces. Text value.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','net.if.out[if,&lt;mode&gt;]','Network interface output statistic. Integer value. If mode is missing bytes is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','net.if.total[if,&lt;mode&gt;]','Sum of network interface incoming and outgoing statistics. Integer value. Mode - one of bytes (default), packets, errors or dropped')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','net.tcp.listen[port]','Checks if this port is in LISTEN state. 0 - it is not, 1 - it is in LISTEN state.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','net.tcp.port[&lt;ip&gt;,port]','Check, if it is possible to make TCP connection to the port number. 0 - cannot connect, 1 - can connect. IP address is optional. If ip is missing, 127.0.0.1 is used. Example: net.tcp.port[,80]')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','net.tcp.service.perf[service,&lt;ip&gt;,&lt;port&gt;]','Check performance of service &quot;service&quot;. 0 - service is down, sec - number of seconds spent on connection to the service. If ip is missing 127.0.0.1 is used.  If port number is missing, default service port is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','net.tcp.service[service,&lt;ip&gt;,&lt;port&gt;]','Check if service is available. 0 - service is down, 1 - service is running. If ip is missing 127.0.0.1 is used. If port number is missing, default service port is used. Example: net.tcp.service[ftp,,45].')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','perf_counter[counter,&lt;interval&gt;]','Value of any performance counter, where "counter" parameter is the counter path and "interval" parameter is a number of last seconds, for which the agent returns an average value.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','proc.mem[&lt;name&gt;,&lt;user&gt;,&lt;mode&gt;,&lt;cmdline&gt;]','Memory used by process with name name running under user user. Memory used by processes. Process name, user and mode is optional. If name or user is missing all processes will be calculated. If mode is missing sum is used. Example: proc.mem[,root]')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','proc.num[&lt;name&gt;,&lt;user&gt;,&lt;state&gt;,&lt;cmdline&gt;]','Number of processes with name name running under user user having state state. Process name, user and state are optional. Examples: proc.num[,mysql]; proc.num[apache2,www-data]; proc.num[,oracle,sleep,oracleZABBIX]')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','proc_info[&lt;process&gt;,&lt;attribute&gt;,&lt;type&gt;]','Different information about specific process(es)')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','service_state[service]','State of service. 0 - running, 1 - paused, 2 - start pending, 3 - pause pending, 4 - continue pending, 5 - stop pending, 6 - stopped, 7 - unknown, 255 - no such service')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.boottime','Timestamp of system boot.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.cpu.intr','Device interrupts.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.cpu.load[&lt;cpu&gt;,&lt;mode&gt;]','CPU(s) load. Processor load. The cpu and mode are optional. If cpu is missing all is used. If mode is missing avg1 is used. Note that this is not percentage.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.cpu.num','Number of available proccessors.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.cpu.switches','Context switches.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.cpu.util[&lt;cpu&gt;,&lt;type&gt;,&lt;mode&gt;]','CPU(s) utilisation. Processor load in percents. The cpu, type and mode are optional. If cpu is missing all is used.  If type is missing user is used. If mode is missing avg1 is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.hostname[&lt;type&gt;]','Returns hostname (or NetBIOS name (by default) on Windows). String value. Example of returned value: www.zabbix.com')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.hw.chassis[&lt;info&gt;]','Chassis info - returns full info by default')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.hw.cpu[&lt;cpu&gt;,&lt;info&gt;]','CPU info - lists full info for all CPUs by default')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.hw.devices[&lt;type&gt;]','Device list - lists PCI devices by default')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.hw.macaddr[&lt;interface&gt;,&lt;format&gt;]','MAC address - lists all MAC addresses with interface names by default')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.localtime','System local time. Time in seconds.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.run[command,&lt;mode&gt;]','Run specified command on the host.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.stat[resource,&lt;type&gt;]','Virtual memory statistics.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.sw.arch','Software architecture')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.sw.os[&lt;info&gt;]','Current OS - returns full info by default')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.sw.packages[&lt;package&gt;,&lt;manager&gt;,&lt;format&gt;]','Software package list - lists all packages for all supported package managers by default')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.swap.in[&lt;swap&gt;,&lt;type&gt;]','Swap in. If type is count - swapins is returned. If type is pages - pages swapped in is returned. If swap is missing all is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.swap.out[&lt;swap&gt;,&lt;type&gt;]','Swap out. If type is count - swapouts is returned. If type is pages - pages swapped in is returned. If swap is missing all is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.swap.size[&lt;swap&gt;,&lt;mode&gt;]','Swap space. Number of bytes. If swap is missing all is used. If mode is missing free is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.uname','Returns detailed host information. String value')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.uptime','System uptime in seconds.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','system.users.num','Number of users connected. Command who is used on agent side.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','vfs.dev.read[device,&lt;type&gt;,&lt;mode&gt;]','Device read statistics.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','vfs.dev.write[device,&lt;type&gt;,&lt;mode&gt;]','Device write statistics.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','vfs.file.cksum[file]','Calculate check sum of a given file. Check sum of the file calculate by standard algorithm used by UNIX utility cksum. Example: vfs.file.cksum[/etc/passwd]')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','vfs.file.contents[file,&lt;encoding&gt;]','Get contents of a given file.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','vfs.file.exists[file]','Check if file exists. 0 - file does not exist, 1 - file exists')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','vfs.file.md5sum[file]','Calculate MD5 check sum of a given file. String MD5 hash of the file. Can be used for files less than 64MB, unsupported otherwise. Example: vfs.file.md5sum[/usr/local/etc/zabbix_agentd.conf]')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','vfs.file.regexp[file,regexp,&lt;encoding&gt;]','Find string in a file. Matched string')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','vfs.file.regmatch[file,regexp,&lt;encoding&gt;]','Find string in a file. 0 - expression not found, 1 - found')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','vfs.file.size[file]','Size of a given file. Size in bytes. File must have read permissions for user zabbix. Example: vfs.file.size[/var/log/syslog]')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','vfs.file.time[file,&lt;mode&gt;]','File time information. Number of seconds.The mode is optional. If mode is missing modify is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','vfs.fs.inode[fs,&lt;mode&gt;]','Number of inodes for a given volume. If mode is missing total is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','vfs.fs.size[fs,&lt;mode&gt;]','Calculate disk space for a given volume. Disk space in KB. If mode is missing total is used.  In case of mounted volume, unused disk space for local file system is returned. Example: vfs.fs.size[/tmp,free].')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','vm.memory.size[&lt;mode&gt;]','Amount of memory size in bytes. If mode is missing total is used.')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','web.page.get[host,&lt;path&gt;,&lt;port&gt;]','Get content of WEB page. Default path is /')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','web.page.perf[host,&lt;path&gt;,&lt;port&gt;]','Get timing of loading full WEB page. Default path is /')
/
INSERT INTO help_items (itemtype,key_,description) values ('7','web.page.regexp[host,&lt;path&gt;,&lt;port&gt;,&lt;regexp&gt;,&lt;length&gt;]','Get first occurence of regexp in WEB page. Default path is /')
/
INSERT INTO help_items (itemtype,key_,description) values ('8','grpfunc[&lt;group&gt;,&lt;key&gt;,&lt;func&gt;,&lt;param&gt;]','Aggregate checks do not require any agent running on a host being monitored. Zabbix server collects aggregate information by doing direct database queries. See Zabbix Manual.')
/
INSERT INTO help_items (itemtype,key_,description) values ('17','snmptrap.fallback','Catches all SNMP traps from a corresponding address that were not catched by any of the snmptrap[] items for that interface.')
/
INSERT INTO help_items (itemtype,key_,description) values ('17','snmptrap[&lt;regex&gt;]','Catches all SNMP traps from a corresponding address that match regex. Default regex is an empty string.')
/
