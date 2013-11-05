<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * Class containing information about help items.
 */
class CHelpItems {

	/**
	 * Available help items groups by item type.
	 *
	 * Each help item has the following properties:
	 * - key			- default key
	 * - description	- description of the item
	 *
	 * @var array
	 */
	protected $items = array(
		ITEM_TYPE_ZABBIX => array(
			array(
				'key' => 'agent.ping',
				'description' => 'Check the agent usability. Always return 1. Can be used as a TCP ping.'
			),
			array(
				'key' => 'agent.version',
				'description' => 'Version of zabbix_agent(d) running on monitored host. String value. Example of returned value: 1.1'
			),
			array(
				'key' => 'kernel.maxfiles',
				'description' => 'Maximum number of opened files supported by OS.'
			),
			array(
				'key' => 'kernel.maxproc',
				'description' => 'Maximum number of processes supported by OS.'
			),
			array(
				'key' => 'net.dns.record[<ip>,zone,<type>,<timeout>,<count>]',
				'description' => 'Performs a DNS query. On success returns a character string with the required type of information.'
			),
			array(
				'key' => 'net.dns[<ip>,zone,<type>,<timeout>,<count>]',
				'description' => 'Checks if DNS service is up. 0 - DNS is down (server did not respond or DNS resolution failed), 1 - DNS is up.'
			),
			array(
				'key' => 'net.if.collisions[if]',
				'description' => 'Out-of-window collision. Collisions count.'
			),
			array(
				'key' => 'net.if.in[if,<mode>]',
				'description' => 'Network interface input statistic. Integer value. If mode is missing bytes is used.'
			),
			array(
				'key' => 'net.if.list',
				'description' => 'List of network interfaces. Text value.'
			),
			array(
				'key' => 'net.if.out[if,<mode>]',
				'description' => 'Network interface output statistic. Integer value. If mode is missing bytes is used.'
			),
			array(
				'key' => 'net.if.total[if,<mode>]',
				'description' => 'Sum of network interface incoming and outgoing statistics. Integer value. Mode - one of bytes (default), packets, errors or dropped'
			),
			array(
				'key' => 'net.tcp.listen[port]',
				'description' => 'Checks if this port is in LISTEN state. 0 - it is not, 1 - it is in LISTEN state.'
			),
			array(
				'key' => 'net.tcp.port[<ip>,port]',
				'description' => 'Check, if it is possible to make TCP connection to the port number. 0 - cannot connect, 1 - can connect. IP address is optional. If ip is missing, 127.0.0.1 is used. Example: net.tcp.port[,80]'
			),
			array(
				'key' => 'net.tcp.service[service,<ip>,<port>]',
				'description' => 'Check if service is available. 0 - service is down, 1 - service is running. If ip is missing 127.0.0.1 is used. If port number is missing, default service port is used. Example: net.tcp.service[ftp,,45].'
			),
			array(
				'key' => 'net.tcp.service.perf[service,<ip>,<port>]',
				'description' => 'Check performance of service "service". 0 - service is down, sec - number of seconds spent on connection to the service. If ip is missing 127.0.0.1 is used.  If port number is missing, default service port is used.'
			),
			array(
				'key' => 'perf_counter[counter,<interval>]',
				'description' => 'Value of any performance counter, where "counter" parameter is the counter path and "interval" parameter is a number of last seconds, for which the agent returns an average value.'
			),
			array(
				'key' => 'proc.mem[<name>,<user>,<mode>,<cmdline>]',
				'description' => 'Memory used by a process. <name> process name (default: "all processes"). <user> user name (default: "all users"). <mode> possible values: avg, max, min, sum (default). <cmdline> filter by command line (supports regex). Example: proc.mem[,root].'
			),
			array(
				'key' => 'proc.num[<name>,<user>,<state>,<cmdline>]',
				'description' => 'Number of processes. <name> and <user> same as in proc.mem item. <state> all (default), run, sleep, zomb. <cmdline> filter by command line (supports regex). Example: proc.num[apache2,www-data]. On Windows, only <name> and <user> are supported.'
			),
			array(
				'key' => 'proc_info[<process>,<attribute>,<type>]',
				'description' => 'Different information about specific process(es)'
			),
			array(
				'key' => 'service_state[service]',
				'description' => 'State of service. 0 - running, 1 - paused, 2 - start pending, 3 - pause pending, 4 - continue pending, 5 - stop pending, 6 - stopped, 7 - unknown, 255 - no such service'
			),
			array(
				'key' => 'system.boottime',
				'description' => 'Timestamp of system boot.'
			),
			array(
				'key' => 'system.cpu.intr',
				'description' => 'Device interrupts.'
			),
			array(
				'key' => 'system.cpu.load[<cpu>,<mode>]',
				'description' => 'CPU(s) load. Processor load. The cpu and mode are optional. If cpu is missing all is used. If mode is missing avg1 is used. Note that this is not percentage.'
			),
			array(
				'key' => 'system.cpu.num',
				'description' => 'Number of available proccessors.'
			),
			array(
				'key' => 'system.cpu.switches',
				'description' => 'Context switches.'
			),
			array(
				'key' => 'system.cpu.util[<cpu>,<type>,<mode>]',
				'description' => 'CPU(s) utilisation. Processor load in percents. The cpu, type and mode are optional. If cpu is missing all is used.  If type is missing user is used. If mode is missing avg1 is used.'
			),
			array(
				'key' => 'system.hostname[<type>]',
				'description' => 'Returns hostname (or NetBIOS name (by default) on Windows). String value. Example of returned value: www.zabbix.com'
			),
			array(
				'key' => 'system.hw.chassis[<info>]',
				'description' => 'Chassis info - returns full info by default'
			),
			array(
				'key' => 'system.hw.cpu[<cpu>,<info>]',
				'description' => 'CPU info - lists full info for all CPUs by default'
			),
			array(
				'key' => 'system.hw.devices[<type>]',
				'description' => 'Device list - lists PCI devices by default'
			),
			array(
				'key' => 'system.hw.macaddr[<interface>,<format>]',
				'description' => 'MAC address - lists all MAC addresses with interface names by default'
			),
			array(
				'key' => 'system.localtime',
				'description' => 'System local time. Time in seconds.'
			),
			array(
				'key' => 'system.run[command,<mode>]',
				'description' => 'Run specified command on the host.'
			),
			array(
				'key' => 'system.stat[resource,<type>]',
				'description' => 'Virtual memory statistics.'
			),
			array(
				'key' => 'system.sw.arch',
				'description' => 'Software architecture'
			),
			array(
				'key' => 'system.sw.os[<info>]',
				'description' => 'Current OS - returns full info by default'
			),
			array(
				'key' => 'system.sw.packages[<package>,<manager>,<format>]',
				'description' => 'Software package list - lists all packages for all supported package managers by default'
			),
			array(
				'key' => 'system.swap.in[<swap>,<type>]',
				'description' => 'Swap in. If type is count - swapins is returned. If type is pages - pages swapped in is returned. If swap is missing all is used.'
			),
			array(
				'key' => 'system.swap.out[<swap>,<type>]',
				'description' => 'Swap out. If type is count - swapouts is returned. If type is pages - pages swapped in is returned. If swap is missing all is used.'
			),
			array(
				'key' => 'system.swap.size[<swap>,<mode>]',
				'description' => 'Swap space. Number of bytes. If swap is missing all is used. If mode is missing free is used.'
			),
			array(
				'key' => 'system.uname',
				'description' => 'Returns detailed host information. String value'
			),
			array(
				'key' => 'system.uptime',
				'description' => 'System uptime in seconds.'
			),
			array(
				'key' => 'system.users.num',
				'description' => 'Number of users connected. Command who is used on agent side.'
			),
			array(
				'key' => 'vfs.dev.read[device,<type>,<mode>]',
				'description' => 'Device read statistics.'
			),
			array(
				'key' => 'vfs.dev.write[device,<type>,<mode>]',
				'description' => 'Device write statistics.'
			),
			array(
				'key' => 'vfs.file.cksum[file]',
				'description' => 'Calculate check sum of a given file. Check sum of the file calculate by standard algorithm used by UNIX utility cksum. Example: vfs.file.cksum[/etc/passwd]'
			),
			array(
				'key' => 'vfs.file.contents[file,<encoding>]',
				'description' => 'Get contents of a given file.'
			),
			array(
				'key' => 'vfs.file.exists[file]',
				'description' => 'Check if file exists. 0 - file does not exist, 1 - file exists'
			),
			array(
				'key' => 'vfs.file.md5sum[file]',
				'description' => 'Calculate MD5 check sum of a given file. String MD5 hash of the file. Can be used for files less than 64MB, unsupported otherwise. Example: vfs.file.md5sum[/usr/local/etc/zabbix_agentd.conf]'
			),
			array(
				'key' => 'vfs.file.regexp[file,regexp,<encoding>]',
				'description' => 'Find string in a file. Matched string'
			),
			array(
				'key' => 'vfs.file.regmatch[file,regexp,<encoding>]',
				'description' => 'Find string in a file. 0 - expression not found, 1 - found'
			),
			array(
				'key' => 'vfs.file.size[file]',
				'description' => 'Size of a given file. Size in bytes. File must have read permissions for user zabbix. Example: vfs.file.size[/var/log/syslog]'
			),
			array(
				'key' => 'vfs.file.time[file,<mode>]',
				'description' => 'File time information. Number of seconds.The mode is optional. If mode is missing modify is used.'
			),
			array(
				'key' => 'vfs.fs.inode[fs,<mode>]',
				'description' => 'Number of inodes for a given volume. If mode is missing total is used.'
			),
			array(
				'key' => 'vfs.fs.size[fs,<mode>]',
				'description' => 'Calculate disk space for a given volume. Disk space in KB. If mode is missing total is used.  In case of mounted volume, unused disk space for local file system is returned. Example: vfs.fs.size[/tmp,free].'
			),
			array(
				'key' => 'vm.memory.size[<mode>]',
				'description' => 'Amount of memory size in bytes. If mode is missing total is used.'
			),
			array(
				'key' => 'web.page.get[host,<path>,<port>]',
				'description' => 'Get content of WEB page. Default path is /'
			),
			array(
				'key' => 'web.page.perf[host,<path>,<port>]',
				'description' => 'Get timing of loading full WEB page. Default path is /'
			),
			array(
				'key' => 'web.page.regexp[host,<path>,<port>,<regexp>,<length>]',
				'description' => 'Get first occurence of regexp in WEB page. Default path is /'
			)
		),
		ITEM_TYPE_ZABBIX_ACTIVE => array(
			array(
				'key' => 'agent.ping',
				'description' => 'Check the agent usability. Always return 1. Can be used as a TCP ping.'
			),
			array(
				'key' => 'agent.version',
				'description' => 'Version of zabbix_agent(d) running on monitored host. String value. Example of returned value: 1.1'
			),
			array(
				'key' => 'eventlog[logtype,<pattern>,<severity>,<source>,<eventid>,<maxlines>,<mode>]',
				'description' => 'Monitoring of Windows event logs. pattern, severity, eventid - regular expressions'
			),
			array(
				'key' => 'kernel.maxfiles',
				'description' => 'Maximum number of opened files supported by OS.'
			),
			array(
				'key' => 'kernel.maxproc',
				'description' => 'Maximum number of processes supported by OS.'
			),
			array(
				'key' => 'log[file,<pattern>,<encoding>,<maxlines>,<mode>,<output>]',
				'description' => 'Monitoring of log file. pattern - regular expression'
			),
			array(
				'key' => 'logrt[file_format,<pattern>,<encoding>,<maxlines>,<mode>,<output>]',
				'description' => 'Monitoring of log file with rotation. fileformat - [path][regexp], pattern - regular expression'
			),
			array(
				'key' => 'net.dns.record[<ip>,zone,<type>,<timeout>,<count>]',
				'description' => 'Performs a DNS query. On success returns a character string with the required type of information.'
			),
			array(
				'key' => 'net.dns[<ip>,zone,<type>,<timeout>,<count>]',
				'description' => 'Checks if DNS service is up. 0 - DNS is down (server did not respond or DNS resolution failed), 1 - DNS is up.'
			),
			array(
				'key' => 'net.if.collisions[if]',
				'description' => 'Out-of-window collision. Collisions count.'
			),
			array(
				'key' => 'net.if.in[if,<mode>]',
				'description' => 'Network interface input statistic. Integer value. If mode is missing bytes is used.'
			),
			array(
				'key' => 'net.if.list',
				'description' => 'List of network interfaces. Text value.'
			),
			array(
				'key' => 'net.if.out[if,<mode>]',
				'description' => 'Network interface output statistic. Integer value. If mode is missing bytes is used.'
			),
			array(
				'key' => 'net.if.total[if,<mode>]',
				'description' => 'Sum of network interface incoming and outgoing statistics. Integer value. Mode - one of bytes (default), packets, errors or dropped'
			),
			array(
				'key' => 'net.tcp.listen[port]',
				'description' => 'Checks if this port is in LISTEN state. 0 - it is not, 1 - it is in LISTEN state.'
			),
			array(
				'key' => 'net.tcp.port[<ip>,port]',
				'description' => 'Check, if it is possible to make TCP connection to the port number. 0 - cannot connect, 1 - can connect. IP address is optional. If ip is missing, 127.0.0.1 is used. Example: net.tcp.port[,80]'
			),
			array(
				'key' => 'net.tcp.service.perf[service,<ip>,<port>]',
				'description' => 'Check performance of service "service". 0 - service is down, sec - number of seconds spent on connection to the service. If ip is missing 127.0.0.1 is used.  If port number is missing, default service port is used.'
			),
			array(
				'key' => 'net.tcp.service[service,<ip>,<port>]',
				'description' => 'Check if service is available. 0 - service is down, 1 - service is running. If ip is missing 127.0.0.1 is used. If port number is missing, default service port is used. Example: net.tcp.service[ftp,,45].'
			),
			array(
				'key' => 'perf_counter[counter,<interval>]',
				'description' => 'Value of any performance counter, where "counter" parameter is the counter path and "interval" parameter is a number of last seconds, for which the agent returns an average value.'
			),
			array(
				'key' => 'proc.mem[<name>,<user>,<mode>,<cmdline>]',
				'description' => 'Memory used by a process. <name> process name (default: "all processes"). <user> user name (default: "all users"). <mode> possible values: avg, max, min, sum (default). <cmdline> filter by command line (supports regex). Example: proc.mem[,root].'
			),
			array(
				'key' => 'proc.num[<name>,<user>,<state>,<cmdline>]',
				'description' => 'Number of processes. <name> and <user> same as in proc.mem item. <state> all (default), run, sleep, zomb. <cmdline> filter by command line (supports regex). Example: proc.num[apache2,www-data]. On Windows, only <name> and <user> are supported.'
			),
			array(
				'key' => 'proc_info[<process>,<attribute>,<type>]',
				'description' => 'Different information about specific process(es)'
			),
			array(
				'key' => 'service_state[service]',
				'description' => 'State of service. 0 - running, 1 - paused, 2 - start pending, 3 - pause pending, 4 - continue pending, 5 - stop pending, 6 - stopped, 7 - unknown, 255 - no such service'
			),
			array(
				'key' => 'system.boottime',
				'description' => 'Timestamp of system boot.'
			),
			array(
				'key' => 'system.cpu.intr',
				'description' => 'Device interrupts.'
			),
			array(
				'key' => 'system.cpu.load[<cpu>,<mode>]',
				'description' => 'CPU(s) load. Processor load. The cpu and mode are optional. If cpu is missing all is used. If mode is missing avg1 is used. Note that this is not percentage.'
			),
			array(
				'key' => 'system.cpu.num',
				'description' => 'Number of available proccessors.'
			),
			array(
				'key' => 'system.cpu.switches',
				'description' => 'Context switches.'
			),
			array(
				'key' => 'system.cpu.util[<cpu>,<type>,<mode>]',
				'description' => 'CPU(s) utilisation. Processor load in percents. The cpu, type and mode are optional. If cpu is missing all is used.  If type is missing user is used. If mode is missing avg1 is used.'
			),
			array(
				'key' => 'system.hostname[<type>]',
				'description' => 'Returns hostname (or NetBIOS name (by default) on Windows). String value. Example of returned value: www.zabbix.com'
			),
			array(
				'key' => 'system.hw.chassis[<info>]',
				'description' => 'Chassis info - returns full info by default'
			),
			array(
				'key' => 'system.hw.cpu[<cpu>,<info>]',
				'description' => 'CPU info - lists full info for all CPUs by default'
			),
			array(
				'key' => 'system.hw.devices[<type>]',
				'description' => 'Device list - lists PCI devices by default'
			),
			array(
				'key' => 'system.hw.macaddr[<interface>,<format>]',
				'description' => 'MAC address - lists all MAC addresses with interface names by default'
			),
			array(
				'key' => 'system.localtime',
				'description' => 'System local time. Time in seconds.'
			),
			array(
				'key' => 'system.run[command,<mode>]',
				'description' => 'Run specified command on the host.'
			),
			array(
				'key' => 'system.stat[resource,<type>]',
				'description' => 'Virtual memory statistics.'
			),
			array(
				'key' => 'system.sw.arch',
				'description' => 'Software architecture'
			),
			array(
				'key' => 'system.sw.os[<info>]',
				'description' => 'Current OS - returns full info by default'
			),
			array(
				'key' => 'system.sw.packages[<package>,<manager>,<format>]',
				'description' => 'Software package list - lists all packages for all supported package managers by default'
			),
			array(
				'key' => 'system.swap.in[<swap>,<type>]',
				'description' => 'Swap in. If type is count - swapins is returned. If type is pages - pages swapped in is returned. If swap is missing all is used.'
			),
			array(
				'key' => 'system.swap.out[<swap>,<type>]',
				'description' => 'Swap out. If type is count - swapouts is returned. If type is pages - pages swapped in is returned. If swap is missing all is used.'
			),
			array(
				'key' => 'system.swap.size[<swap>,<mode>]',
				'description' => 'Swap space. Number of bytes. If swap is missing all is used. If mode is missing free is used.'
			),
			array(
				'key' => 'system.uname',
				'description' => 'Returns detailed host information. String value'
			),
			array(
				'key' => 'system.uptime',
				'description' => 'System uptime in seconds.'
			),
			array(
				'key' => 'system.users.num',
				'description' => 'Number of users connected. Command who is used on agent side.'
			),
			array(
				'key' => 'vfs.dev.read[device,<type>,<mode>]',
				'description' => 'Device read statistics.'
			),
			array(
				'key' => 'vfs.dev.write[device,<type>,<mode>]',
				'description' => 'Device write statistics.'
			),
			array(
				'key' => 'vfs.file.cksum[file]',
				'description' => 'Calculate check sum of a given file. Check sum of the file calculate by standard algorithm used by UNIX utility cksum. Example: vfs.file.cksum[/etc/passwd]'
			),
			array(
				'key' => 'vfs.file.contents[file,<encoding>]',
				'description' => 'Get contents of a given file.'
			),
			array(
				'key' => 'vfs.file.exists[file]',
				'description' => 'Check if file exists. 0 - file does not exist, 1 - file exists'
			),
			array(
				'key' => 'vfs.file.md5sum[file]',
				'description' => 'Calculate MD5 check sum of a given file. String MD5 hash of the file. Can be used for files less than 64MB, unsupported otherwise. Example: vfs.file.md5sum[/usr/local/etc/zabbix_agentd.conf]'
			),
			array(
				'key' => 'vfs.file.regexp[file,regexp,<encoding>,<start line>,<end line>,<output>]',
				'description' => 'Find string in a file. Matched string'
			),
			array(
				'key' => 'vfs.file.regmatch[file,regexp,<encoding>,<start line>,<end line>]',
				'description' => 'Find string in a file. 0 - expression not found, 1 - found'
			),
			array(
				'key' => 'vfs.file.size[file]',
				'description' => 'Size of a given file. Size in bytes. File must have read permissions for user zabbix. Example: vfs.file.size[/var/log/syslog]'
			),
			array(
				'key' => 'vfs.file.time[file,<mode>]',
				'description' => 'File time information. Number of seconds.The mode is optional. If mode is missing modify is used.'
			),
			array(
				'key' => 'vfs.fs.inode[fs,<mode>]',
				'description' => 'Number of inodes for a given volume. If mode is missing total is used.'
			),
			array(
				'key' => 'vfs.fs.size[fs,<mode>]',
				'description' => 'Calculate disk space for a given volume. Disk space in KB. If mode is missing total is used.  In case of mounted volume, unused disk space for local file system is returned. Example: vfs.fs.size[/tmp,free].'
			),
			array(
				'key' => 'vm.memory.size[<mode>]',
				'description' => 'Amount of memory size in bytes. If mode is missing total is used.'
			),
			array(
				'key' => 'web.page.get[host,<path>,<port>]',
				'description' => 'Get content of WEB page. Default path is /'
			),
			array(
				'key' => 'web.page.perf[host,<path>,<port>]',
				'description' => 'Get timing of loading full WEB page. Default path is /'
			),
			array(
				'key' => 'web.page.regexp[host,<path>,<port>,<regexp>,<length>,<output>]',
				'description' => 'Get first occurence of regexp in WEB page. Default path is /'
			)
		),
		ITEM_TYPE_AGGREGATE => array(
			array(
				'key' => 'grpfunc[<group>,<key>,<func>,<param>]',
				'description' => 'Aggregate checks do not require any agent running on a host being monitored. Zabbix server collects aggregate information by doing direct database queries. See Zabbix Manual.'
			)
		),
		ITEM_TYPE_SIMPLE => array(
			array(
				'key' => 'icmpping[<target>,<packets>,<interval>,<size>,<timeout>]',
				'description' => 'Checks if server is accessible by ICMP ping. 0 - ICMP ping fails. 1 - ICMP ping successful. One of zabbix_server processes performs ICMP pings once per PingerFrequency seconds.'
			),
			array(
				'key' => 'icmppingloss[<target>,<packets>,<interval>,<size>,<timeout>]',
				'description' => 'Returns percentage of lost ICMP ping packets.'
			),
			array(
				'key' => 'icmppingsec[<target>,<packets>,<interval>,<size>,<timeout>,<mode>]',
				'description' => 'Returns ICMP ping response time in seconds. Example: 0.02'
			),
			array(
				'key' => 'net.tcp.service.perf[service,<ip>,<port>]',
				'description' => 'Check performance of service. 0 - service is down, sec - number of seconds spent on connection to the service. If <ip> is missing, IP or DNS name is taken from host definition. If <port> is missing, default service port is used.'
			),
			array(
				'key' => 'net.tcp.service[service,<ip>,<port>]',
				'description' => 'Check if service is available. 0 - service is down, 1 - service is running. If <ip> is missing, IP or DNS name is taken from host definition. If <port> is missing, default service port is used.'
			),
			array(
				'key' => 'vmware.cluster.status[<url>,<name>]',
				'description' => 'VMware cluster status, <url> - VMware service URL, <name> - VMware cluster name'
			),
			array(
				'key' => 'vmware.eventlog[<url>]',
				'description' => 'VMware event log, <url> - VMware service URL'
			),
			array(
				'key' => 'vmware.fullname[<url>]',
				'description' => 'VMware service full name, <url> - VMware service URL'
			),
			array(
				'key' => 'vmware.eventlog[<url>]',
				'description' => 'VMware service version, <url> - VMware service URL'
			),
			array(
				'key' => 'vmware.hv.cluster.name[<url>,<uuid>]',
				'description' => 'VMware hypervisor cluster name, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.cpu.usage[<url>,<uuid>]',
				'description' => 'VMware hypervisor processor usage in Hz, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.datastore.read[<url>,<uuid>,<datastore>,<mode>]',
				'description' => 'VMware hypervisor datastore read statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <datastore> - datastore name, <mode> - latency'
			),
			array(
				'key' => 'vmware.hv.datastore.write[<url>,<uuid>,<datastore>,<mode>]',
				'description' => 'VMware hypervisor datastore write statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <datastore> - datastore name, <mode> - latency'
			),
			array(
				'key' => 'vmware.hv.full.name[<url>,<uuid>]',
				'description' => 'VMware hypervisor name, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.hw.cpu.freq[<url>,<uuid>]',
				'description' => 'VMware hypervisor processor frequency, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.hw.cpu.model[<url>,<uuid>]',
				'description' => 'VMware hypervisor processor model, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.hw.cpu.num[<url>,<uuid>]',
				'description' => 'Number of processor cores on VMware hypervisor, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.hw.cpu.threads[<url>,<uuid>]',
				'description' => 'Number of processor threads on VMware hypervisor, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.hw.memory[<url>,<uuid>]',
				'description' => 'VMware hypervisor total memory size, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.hw.model[<url>,<uuid>]',
				'description' => 'VMware hypervisor model, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.hw.uuid[<url>,<uuid>]',
				'description' => 'VMware hypervisor BIOS uuid, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.hw.vendor[<url>,<uuid>]',
				'description' => 'VMware hypervisor vendor name, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.memory.size.ballooned[<url>,<uuid>]',
				'description' => 'VMware hypervisor ballooned memory size, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.memory.used[<url>,<uuid>]',
				'description' => 'VMware hypervisor used memory size, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.network.in[<url>,<uuid>,<mode>]',
				'description' => 'VMware hypervisor netowork input statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <mode> - bps'
			),
			array(
				'key' => 'vmware.hv.network.out[<url>,<uuid>,<mode>]',
				'description' => 'VMware hypervisor netowork output statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <mode> - bps'
			),
			array(
				'key' => 'vmware.hv.status[<url>,<uuid>]',
				'description' => 'VMware hypervisor status, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.uptime[<url>,<uuid>]',
				'description' => 'VMware hypervisor uptime, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.version[<url>,<uuid>]',
				'description' => 'VMware hypervisor version, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.hv.vm.num[<url>,<uuid>]',
				'description' => 'Number of virtual machines on VMware hypervisor, <url> - VMware service URL, <uuid> - VMware hypervisor host name'
			),
			array(
				'key' => 'vmware.vm.cluster.name[<url>,<uuid>]',
				'description' => 'VMware virtual machine name, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.cpu.num[<url>,<uuid>]',
				'description' => 'Number of processors on VMware virtual machine, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.cpu.usage[<url>,<uuid>]',
				'description' => 'VMware virtual machine processor usage in Hz, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.hv.name[<url>,<uuid>]',
				'description' => 'VMware virtual machine hypervisor name, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.memory.size.ballooned[<url>,<uuid>]',
				'description' => 'VMware virtual machine ballooned memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.memory.size.compressed[<url>,<uuid>]',
				'description' => 'VMware virtual machine compressed memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.memory.size.private[<url>,<uuid>]',
				'description' => 'VMware virtual machine private memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.memory.size.shared[<url>,<uuid>]',
				'description' => 'VMware virtual machine shared memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.memory.size.swapped[<url>,<uuid>]',
				'description' => 'VMware virtual machine swapped memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.memory.size.usage.guest[<url>,<uuid>]',
				'description' => 'VMware virtual machine guest memory usage, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.memory.size.usage.host[<url>,<uuid>]',
				'description' => 'VMware virtual machine host memory usage, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.memory.size[<url>,<uuid>]',
				'description' => 'VMware virtual machine total memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.net.if.in[<url>,<uuid>,<instance>,<mode>]',
				'description' => 'VMware virtual machine network interface input statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - network interface instance, <mode> - bps/pps - bytes/packets per second'
			),
			array(
				'key' => 'vmware.vm.net.if.out[<url>,<uuid>,<instance>,<mode>]',
				'description' => 'VMware virtual machine network interface output statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - network interface instance, <mode> - bps/pps - bytes/packets per second'
			),
			array(
				'key' => 'vmware.vm.powerstate[<url>,<uuid>]',
				'description' => 'VMware virtual machine power state, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.storage.committed[<url>,<uuid>]',
				'description' => 'VMware virtual machine committed storage space, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.storage.uncommitted[<url>,<uuid>]',
				'description' => 'VMware virtual machine uncommitted storage space, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.storage.unshared[<url>,<uuid>]',
				'description' => 'VMware virtual machine unshared storage space, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.uptime[<url>,<uuid>]',
				'description' => 'VMware virtual machine uptime, <url> - VMware service URL, <uuid> - VMware virtual machine host name'
			),
			array(
				'key' => 'vmware.vm.vfs.dev.read[<url>,<uuid>,<instance>,<mode>]',
				'description' => 'VMware virtual machine disk device read statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance, <mode> - bps/ops - bytes/operations per second'
			),
			array(
				'key' => 'vmware.vm.vfs.dev.write[<url>,<uuid>,<instance>,<mode>]',
				'description' => 'VMware virtual machine disk device write statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance, <mode> - bps/ops - bytes/operations per second'
			),
			array(
				'key' => 'vmware.vm.vfs.fs.size[<url>,<uuid>,<fsname>,<mode>]',
				'description' => 'VMware virtual machine file system statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <fsname> - file system name, <mode> - total/free/used/pfree/pused'
			),
			array(
				'key' => 'web.page.perf[host,<path>,<port>]',
				'description' => 'Get timing of loading full WEB page. Default path is /'
			)
		),
		ITEM_TYPE_SNMPTRAP => array(
			array(
				'key' => 'snmptrap.fallback',
				'description' => 'Catches all SNMP traps that are not caught by any of the snmptrap[] items from a corresponding address of the interface.'
			),
			array(
				'key' => 'snmptrap[<regex>]',
				'description' => 'Catches all SNMP traps from a corresponding address that match regex. Default regex is an empty string.'
			)
		),
		ITEM_TYPE_INTERNAL => array(
			array(
				'key' => 'zabbix[boottime]',
				'description' => 'Startup time of Zabbix server, Unix timestamp.'
			),
			array(
				'key' => 'zabbix[history]',
				'description' => 'Number of values stored in table HISTORY.'
			),
			array(
				'key' => 'zabbix[history_log]',
				'description' => 'Number of values stored in table HISTORY_LOG.'
			),
			array(
				'key' => 'zabbix[history_str]',
				'description' => 'Number of values stored in table HISTORY_STR.'
			),
			array(
				'key' => 'zabbix[history_text]',
				'description' => 'Number of values stored in table HISTORY_TEXT.'
			),
			array(
				'key' => 'zabbix[history_uint]',
				'description' => 'Number of values stored in table HISTORY_UINT.'
			),
			array(
				'key' => 'zabbix[host,<type>,available]',
				'description' => 'Returns availability of a particular type of checks on the host. Value of this item corresponds to availability icons in the host list. Valid types are: agent, snmp, ipmi, jmx.'
			),
			array(
				'key' => 'zabbix[hosts]',
				'description' => 'Number of monitored hosts'
			),
			array(
				'key' => 'zabbix[items]',
				'description' => 'Number of items in Zabbix database.'
			),
			array(
				'key' => 'zabbix[items_unsupported]',
				'description' => 'Number of unsupported items in Zabbix database.'
			),
			array(
				'key' => 'zabbix[java,,<param>]',
				'description' => 'Returns information associated with Zabbix Java gateway. Valid params are: ping, version.'
			),
			array(
				'key' => 'zabbix[process,<type>,<num>,<state>]',
				'description' => 'Time a particular Zabbix process or a group of processes (identified by <type> and <num>) spent in <state> in percentage.'
			),
			array(
				'key' => 'zabbix[proxy,<name>,<param>]',
				'description' => 'Time of proxy last access. Name - proxy name. Param - lastaccess. Unix timestamp.'
			),
			array(
				'key' => 'zabbix[proxy_history]',
				'description' => 'Number of items in proxy history that are not yet sent to the server'
			),
			array(
				'key' => 'zabbix[queue,<from>,<to>]',
				'description' => 'Number of items in the queue which are delayed by from to to seconds, inclusive.'
			),
			array(
				'key' => 'zabbix[rcache,<cache>,<mode>]',
				'description' => 'Configuration cache statistics. Cache - buffer (modes: pfree, total, used, free).'
			),
			array(
				'key' => 'zabbix[requiredperformance]',
				'description' => 'Required performance of the Zabbix server, in new values per second expected.'
			),
			array(
				'key' => 'zabbix[trends]',
				'description' => 'Number of values stored in table TRENDS.'
			),
			array(
				'key' => 'zabbix[trends_uint]',
				'description' => 'Number of values stored in table TRENDS_UINT.'
			),
			array(
				'key' => 'zabbix[triggers]',
				'description' => 'Number of triggers in Zabbix database.'
			),
			array(
				'key' => 'zabbix[uptime]',
				'description' => 'Uptime of Zabbix server process in seconds.'
			),
			array(
				'key' => 'zabbix[vcache,buffer,<mode>]',
				'description' => 'Value cache statistics. Valid modes are: total, free, pfree, used and pused.'
			),
			array(
				'key' => 'zabbix[vcache,cache,<parameter>]',
				'description' => 'Value cache effectiveness. Valid parameters are: requests, hits and misses.'
			),
			array(
				'key' => 'zabbix[vmware,buffer,<mode>]',
				'description' => 'VMware cache statistics. Valid modes are: total, free, pfree, used and pused.'
			),
			array(
				'key' => 'zabbix[wcache,<cache>,<mode>]',
				'description' => 'Data cache statistics. Cache - one of values (modes: all, float, uint, str, log, text), history (modes: pfree, total, used, free), trend (modes: pfree, total, used, free), text (modes: pfree, total, used, free).'
			)
		)
	);

	/**
	 * Returns the help items available for the given item type.
	 *
	 * @param int $type
	 *
	 * @return array
	 */
	public function getByType($type) {
		return $this->items[$type];
	}
}
