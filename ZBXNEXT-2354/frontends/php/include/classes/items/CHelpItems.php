<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
	 * A list of all available help items grouped by item type.
	 *
	 * @see CHelpItems::getItems()	for a description of the structure
	 *
	 * @var array
	 */
	protected $items = array();

	public function __construct() {
		$this->items = $this->getItems();
	}

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

	/**
	 * Get list of all available help items grouped by item type.
	 *
	 * Each help item has the following properties:
	 * - key			- default key
	 * - description	- description of the item
	 *
	 * @return array
	 */
	protected function getItems() {
		return array(
			ITEM_TYPE_ZABBIX => array(
				array(
					'key' => 'agent.hostname',
					'description' => _('Agent host name defined in the configuration file. String value.')
				),
				array(
					'key' => 'agent.ping',
					'description' => _('Check the agent usability. Always return 1. Can be used as a TCP ping.')
				),
				array(
					'key' => 'agent.version',
					'description' => _('Version of zabbix_agent(d) running on monitored host. String value. Example of returned value: 1.1')
				),
				array(
					'key' => 'kernel.maxfiles',
					'description' => _('Maximum number of opened files supported by OS.')
				),
				array(
					'key' => 'kernel.maxproc',
					'description' => _('Maximum number of processes supported by OS.')
				),
				array(
					'key' => 'net.dns[<ip>,zone,<type>,<timeout>,<count>]',
					'description' => _('Checks if DNS service is up. 0 - DNS is down (server did not respond or DNS resolution failed), 1 - DNS is up.')
				),
				array(
					'key' => 'net.dns.record[<ip>,zone,<type>,<timeout>,<count>]',
					'description' => _('Performs a DNS query. On success returns a character string with the required type of information.')
				),
				array(
					'key' => 'net.if.collisions[if]',
					'description' => _('Out-of-window collision. Collisions count.')
				),
				array(
					'key' => 'net.if.in[if,<mode>]',
					'description' => _('Network interface input statistic. Integer value. If mode is missing bytes is used.')
				),
				array(
					'key' => 'net.if.list',
					'description' => _('List of network interfaces. Text value.')
				),
				array(
					'key' => 'net.if.out[if,<mode>]',
					'description' => _('Network interface output statistic. Integer value. If mode is missing bytes is used.')
				),
				array(
					'key' => 'net.if.total[if,<mode>]',
					'description' => _('Sum of network interface incoming and outgoing statistics. Integer value. Mode - one of bytes (default), packets, errors or dropped')
				),
				array(
					'key' => 'net.tcp.listen[port]',
					'description' => _('Checks if this port is in LISTEN state. 0 - it is not, 1 - it is in LISTEN state.')
				),
				array(
					'key' => 'net.tcp.port[<ip>,port]',
					'description' => _('Check, if it is possible to make TCP connection to the port number. 0 - cannot connect, 1 - can connect. IP address is optional. If ip is missing, 127.0.0.1 is used. Example: net.tcp.port[,80]')
				),
				array(
					'key' => 'net.tcp.service[service,<ip>,<port>]',
					'description' => _('Check if service is available. 0 - service is down, 1 - service is running. If ip is missing 127.0.0.1 is used. If port number is missing, default service port is used. Example: net.tcp.service[ftp,,45].')
				),
				array(
					'key' => 'net.tcp.service.perf[service,<ip>,<port>]',
					'description' => _('Check performance of service "service". 0 - service is down, sec - number of seconds spent on connection to the service. If ip is missing 127.0.0.1 is used.  If port number is missing, default service port is used.')
				),
				array(
					'key' => 'net.udp.listen[port]',
					'description' => _('Checks if this port is in LISTEN state. 0 - it is not, 1 - it is in LISTEN state.')
				),
				array(
					'key' => 'perf_counter[counter,<interval>]',
					'description' => _('Value of any performance counter, where "counter" parameter is the counter path and "interval" parameter is a number of last seconds, for which the agent returns an average value.')
				),
				array(
					'key' => 'proc.mem[<name>,<user>,<mode>,<cmdline>]',
					'description' => _('Memory used by a process. <name> process name (default: "all processes"). <user> user name (default: "all users"). <mode> possible values: avg, max, min, sum (default). <cmdline> filter by command line (supports regex). Example: proc.mem[,root].')
				),
				array(
					'key' => 'proc.num[<name>,<user>,<state>,<cmdline>]',
					'description' => _('Number of processes. <name> and <user> same as in proc.mem item. <state> all (default), run, sleep, zomb. <cmdline> filter by command line (supports regex). Example: proc.num[apache2,www-data]. On Windows, only <name> and <user> are supported.')
				),
				array(
					'key' => 'proc_info[<process>,<attribute>,<type>]',
					'description' => _('Different information about specific process(es)')
				),
				array(
					'key' => 'service_state[service]',
					'description' => _('State of service. 0 - running, 1 - paused, 2 - start pending, 3 - pause pending, 4 - continue pending, 5 - stop pending, 6 - stopped, 7 - unknown, 255 - no such service')
				),
				array(
					'key' => 'system.boottime',
					'description' => _('Timestamp of system boot.')
				),
				array(
					'key' => 'system.cpu.intr',
					'description' => _('Device interrupts.')
				),
				array(
					'key' => 'system.cpu.load[<cpu>,<mode>]',
					'description' => _('CPU(s) load. Processor load. The cpu and mode are optional. If cpu is missing all is used. If mode is missing avg1 is used. Note that this is not percentage.')
				),
				array(
					'key' => 'system.cpu.num[<type>]',
					'description' => _('Number of available processors.')
				),
				array(
					'key' => 'system.cpu.switches',
					'description' => _('Context switches.')
				),
				array(
					'key' => 'system.cpu.util[<cpu>,<type>,<mode>]',
					'description' => _('CPU(s) utilisation. Processor load in percents. The cpu, type and mode are optional. If cpu is missing all is used.  If type is missing user is used. If mode is missing avg1 is used.')
				),
				array(
					'key' => 'system.hostname[<type>]',
					'description' => _('Returns hostname (or NetBIOS name (by default) on Windows). String value. Example of returned value: www.zabbix.com')
				),
				array(
					'key' => 'system.hw.chassis[<info>]',
					'description' => _('Chassis info - returns full info by default')
				),
				array(
					'key' => 'system.hw.cpu[<cpu>,<info>]',
					'description' => _('CPU info - lists full info for all CPUs by default')
				),
				array(
					'key' => 'system.hw.devices[<type>]',
					'description' => _('Device list - lists PCI devices by default')
				),
				array(
					'key' => 'system.hw.macaddr[<interface>,<format>]',
					'description' => _('MAC address - lists all MAC addresses with interface names by default')
				),
				array(
					'key' => 'system.localtime[<type>]',
					'description' => _('System local time. Time in seconds.')
				),
				array(
					'key' => 'system.run[command,<mode>]',
					'description' => _('Run specified command on the host.')
				),
				array(
					'key' => 'system.stat[resource,<type>]',
					'description' => _('Virtual memory statistics.')
				),
				array(
					'key' => 'system.sw.arch',
					'description' => _('Software architecture')
				),
				array(
					'key' => 'system.sw.os[<info>]',
					'description' => _('Current OS - returns full info by default')
				),
				array(
					'key' => 'system.sw.packages[<package>,<manager>,<format>]',
					'description' => _('Software package list - lists all packages for all supported package managers by default')
				),
				array(
					'key' => 'system.swap.in[<device>,<type>]',
					'description' => _('Swap in. If type is count - swapins is returned. If type is pages - pages swapped in is returned. If swap is missing all is used.')
				),
				array(
					'key' => 'system.swap.out[<device>,<type>]',
					'description' => _('Swap out. If type is count - swapouts is returned. If type is pages - pages swapped in is returned. If swap is missing all is used.')
				),
				array(
					'key' => 'system.swap.size[<device>,<type>]',
					'description' => _('Swap space. Number of bytes. If swap is missing all is used. If mode is missing free is used.')
				),
				array(
					'key' => 'system.uname',
					'description' => _('Returns detailed host information. String value')
				),
				array(
					'key' => 'system.uptime',
					'description' => _('System uptime in seconds.')
				),
				array(
					'key' => 'system.users.num',
					'description' => _('Number of users connected. Command who is used on agent side.')
				),
				array(
					'key' => 'vfs.dev.read[<device>,<type>,<mode>]',
					'description' => _('Device read statistics.')
				),
				array(
					'key' => 'vfs.dev.write[<device>,<type>,<mode>]',
					'description' => _('Device write statistics.')
				),
				array(
					'key' => 'vfs.file.cksum[file]',
					'description' => _('Calculate check sum of a given file. Check sum of the file calculate by standard algorithm used by UNIX utility cksum. Example: vfs.file.cksum[/etc/passwd]')
				),
				array(
					'key' => 'vfs.file.contents[file,<encoding>]',
					'description' => _('Get contents of a given file.')
				),
				array(
					'key' => 'vfs.file.exists[file]',
					'description' => _('Check if file exists. 0 - file does not exist, 1 - file exists')
				),
				array(
					'key' => 'vfs.file.md5sum[file]',
					'description' => _('Calculate MD5 check sum of a given file. String MD5 hash of the file. Can be used for files less than 64MB, unsupported otherwise. Example: vfs.file.md5sum[/usr/local/etc/zabbix_agentd.conf]')
				),
				array(
					'key' => 'vfs.file.regexp[file,regexp,<encoding>,<start line>,<end line>,<output>]',
					'description' => _('Find string in a file. Matched string')
				),
				array(
					'key' => 'vfs.file.regmatch[file,regexp,<encoding>,<start line>,<end line>]',
					'description' => _('Find string in a file. 0 - expression not found, 1 - found')
				),
				array(
					'key' => 'vfs.file.size[file]',
					'description' => _('Size of a given file. Size in bytes. File must have read permissions for user zabbix. Example: vfs.file.size[/var/log/syslog]')
				),
				array(
					'key' => 'vfs.file.time[file,<mode>]',
					'description' => _('File time information. Number of seconds.The mode is optional. If mode is missing modify is used.')
				),
				array(
					'key' => 'vfs.fs.inode[fs,<mode>]',
					'description' => _('Number of inodes for a given volume. If mode is missing total is used.')
				),
				array(
					'key' => 'vfs.fs.size[fs,<mode>]',
					'description' => _('Calculate disk space for a given volume. Disk space in KB. If mode is missing total is used.  In case of mounted volume, unused disk space for local file system is returned. Example: vfs.fs.size[/tmp,free].')
				),
				array(
					'key' => 'vm.memory.size[<mode>]',
					'description' => _('Amount of memory size in bytes. If mode is missing total is used.')
				),
				array(
					'key' => 'web.page.get[host,<path>,<port>]',
					'description' => _('Get content of WEB page. Default path is /')
				),
				array(
					'key' => 'web.page.perf[host,<path>,<port>]',
					'description' => _('Get timing of loading full WEB page. Default path is /')
				),
				array(
					'key' => 'web.page.regexp[host,<path>,<port>,<regexp>,<length>,<output>]',
					'description' => _('Get first occurence of regexp in WEB page. Default path is /')
				),
				array(
					'key' => 'wmi.get[<namespace>,<query>]',
					'description' => _('Execute WMI query and return the first selected object.')
				)
			),
			ITEM_TYPE_ZABBIX_ACTIVE => array(
				array(
					'key' => 'agent.hostname',
					'description' => _('Agent host name defined in the configuration file. String value.')
				),
				array(
					'key' => 'agent.ping',
					'description' => _('Check the agent usability. Always return 1. Can be used as a TCP ping.')
				),
				array(
					'key' => 'agent.version',
					'description' => _('Version of zabbix_agent(d) running on monitored host. String value. Example of returned value: 1.1')
				),
				array(
					'key' => 'eventlog[name,<regexp>,<severity>,<source>,<eventid>,<maxlines>,<mode>]',
					'description' => _('Monitoring of Windows event logs. pattern, severity, eventid - regular expressions')
				),
				array(
					'key' => 'kernel.maxfiles',
					'description' => _('Maximum number of opened files supported by OS.')
				),
				array(
					'key' => 'kernel.maxproc',
					'description' => _('Maximum number of processes supported by OS.')
				),
				array(
					'key' => 'log[file,<regexp>,<encoding>,<maxlines>,<mode>,<output>]',
					'description' => _('Monitoring of log file. pattern - regular expression')
				),
				array(
					'key' => 'logrt[file_regexp,<regexp>,<encoding>,<maxlines>,<mode>,<output>]',
					'description' => _('Monitoring of log file with rotation. fileformat - [path][regexp], pattern - regular expression')
				),
				array(
					'key' => 'net.dns[<ip>,zone,<type>,<timeout>,<count>]',
					'description' => _('Checks if DNS service is up. 0 - DNS is down (server did not respond or DNS resolution failed), 1 - DNS is up.')
				),
				array(
					'key' => 'net.dns.record[<ip>,zone,<type>,<timeout>,<count>]',
					'description' => _('Performs a DNS query. On success returns a character string with the required type of information.')
				),
				array(
					'key' => 'net.if.collisions[if]',
					'description' => _('Out-of-window collision. Collisions count.')
				),
				array(
					'key' => 'net.if.in[if,<mode>]',
					'description' => _('Network interface input statistic. Integer value. If mode is missing bytes is used.')
				),
				array(
					'key' => 'net.if.list',
					'description' => _('List of network interfaces. Text value.')
				),
				array(
					'key' => 'net.if.out[if,<mode>]',
					'description' => _('Network interface output statistic. Integer value. If mode is missing bytes is used.')
				),
				array(
					'key' => 'net.if.total[if,<mode>]',
					'description' => _('Sum of network interface incoming and outgoing statistics. Integer value. Mode - one of bytes (default), packets, errors or dropped')
				),
				array(
					'key' => 'net.tcp.listen[port]',
					'description' => _('Checks if this port is in LISTEN state. 0 - it is not, 1 - it is in LISTEN state.')
				),
				array(
					'key' => 'net.tcp.port[<ip>,port]',
					'description' => _('Check, if it is possible to make TCP connection to the port number. 0 - cannot connect, 1 - can connect. IP address is optional. If ip is missing, 127.0.0.1 is used. Example: net.tcp.port[,80]')
				),
				array(
					'key' => 'net.tcp.service[service,<ip>,<port>]',
					'description' => _('Check if service is available. 0 - service is down, 1 - service is running. If ip is missing 127.0.0.1 is used. If port number is missing, default service port is used. Example: net.tcp.service[ftp,,45].')
				),
				array(
					'key' => 'net.tcp.service.perf[service,<ip>,<port>]',
					'description' => _('Check performance of service "service". 0 - service is down, sec - number of seconds spent on connection to the service. If ip is missing 127.0.0.1 is used.  If port number is missing, default service port is used.')
				),
				array(
					'key' => 'net.udp.listen[port]',
					'description' => _('Checks if this port is in LISTEN state. 0 - it is not, 1 - it is in LISTEN state.')
				),
				array(
					'key' => 'perf_counter[counter,<interval>]',
					'description' => _('Value of any performance counter, where "counter" parameter is the counter path and "interval" parameter is a number of last seconds, for which the agent returns an average value.')
				),
				array(
					'key' => 'proc.mem[<name>,<user>,<mode>,<cmdline>]',
					'description' => _('Memory used by a process. <name> process name (default: "all processes"). <user> user name (default: "all users"). <mode> possible values: avg, max, min, sum (default). <cmdline> filter by command line (supports regex). Example: proc.mem[,root].')
				),
				array(
					'key' => 'proc.num[<name>,<user>,<state>,<cmdline>]',
					'description' => _('Number of processes. <name> and <user> same as in proc.mem item. <state> all (default), run, sleep, zomb. <cmdline> filter by command line (supports regex). Example: proc.num[apache2,www-data]. On Windows, only <name> and <user> are supported.')
				),
				array(
					'key' => 'proc_info[<process>,<attribute>,<type>]',
					'description' => _('Different information about specific process(es)')
				),
				array(
					'key' => 'service_state[service]',
					'description' => _('State of service. 0 - running, 1 - paused, 2 - start pending, 3 - pause pending, 4 - continue pending, 5 - stop pending, 6 - stopped, 7 - unknown, 255 - no such service')
				),
				array(
					'key' => 'system.boottime',
					'description' => _('Timestamp of system boot.')
				),
				array(
					'key' => 'system.cpu.intr',
					'description' => _('Device interrupts.')
				),
				array(
					'key' => 'system.cpu.load[<cpu>,<mode>]',
					'description' => _('CPU(s) load. Processor load. The cpu and mode are optional. If cpu is missing all is used. If mode is missing avg1 is used. Note that this is not percentage.')
				),
				array(
					'key' => 'system.cpu.num[<type>]',
					'description' => _('Number of available processors.')
				),
				array(
					'key' => 'system.cpu.switches',
					'description' => _('Context switches.')
				),
				array(
					'key' => 'system.cpu.util[<cpu>,<type>,<mode>]',
					'description' => _('CPU(s) utilisation. Processor load in percents. The cpu, type and mode are optional. If cpu is missing all is used.  If type is missing user is used. If mode is missing avg1 is used.')
				),
				array(
					'key' => 'system.hostname[<type>]',
					'description' => _('Returns hostname (or NetBIOS name (by default) on Windows). String value. Example of returned value: www.zabbix.com')
				),
				array(
					'key' => 'system.hw.chassis[<info>]',
					'description' => _('Chassis info - returns full info by default')
				),
				array(
					'key' => 'system.hw.cpu[<cpu>,<info>]',
					'description' => _('CPU info - lists full info for all CPUs by default')
				),
				array(
					'key' => 'system.hw.devices[<type>]',
					'description' => _('Device list - lists PCI devices by default')
				),
				array(
					'key' => 'system.hw.macaddr[<interface>,<format>]',
					'description' => _('MAC address - lists all MAC addresses with interface names by default')
				),
				array(
					'key' => 'system.localtime[<type>]',
					'description' => _('System local time. Time in seconds.')
				),
				array(
					'key' => 'system.run[command,<mode>]',
					'description' => _('Run specified command on the host.')
				),
				array(
					'key' => 'system.stat[resource,<type>]',
					'description' => _('Virtual memory statistics.')
				),
				array(
					'key' => 'system.sw.arch',
					'description' => _('Software architecture')
				),
				array(
					'key' => 'system.sw.os[<info>]',
					'description' => _('Current OS - returns full info by default')
				),
				array(
					'key' => 'system.sw.packages[<package>,<manager>,<format>]',
					'description' => _('Software package list - lists all packages for all supported package managers by default')
				),
				array(
					'key' => 'system.swap.in[<device>,<type>]',
					'description' => _('Swap in. If type is count - swapins is returned. If type is pages - pages swapped in is returned. If swap is missing all is used.')
				),
				array(
					'key' => 'system.swap.out[<device>,<type>]',
					'description' => _('Swap out. If type is count - swapouts is returned. If type is pages - pages swapped in is returned. If swap is missing all is used.')
				),
				array(
					'key' => 'system.swap.size[<device>,<type>]',
					'description' => _('Swap space. Number of bytes. If swap is missing all is used. If mode is missing free is used.')
				),
				array(
					'key' => 'system.uname',
					'description' => _('Returns detailed host information. String value')
				),
				array(
					'key' => 'system.uptime',
					'description' => _('System uptime in seconds.')
				),
				array(
					'key' => 'system.users.num',
					'description' => _('Number of users connected. Command who is used on agent side.')
				),
				array(
					'key' => 'vfs.dev.read[<device>,<type>,<mode>]',
					'description' => _('Device read statistics.')
				),
				array(
					'key' => 'vfs.dev.write[<device>,<type>,<mode>]',
					'description' => _('Device write statistics.')
				),
				array(
					'key' => 'vfs.file.cksum[file]',
					'description' => _('Calculate check sum of a given file. Check sum of the file calculate by standard algorithm used by UNIX utility cksum. Example: vfs.file.cksum[/etc/passwd]')
				),
				array(
					'key' => 'vfs.file.contents[file,<encoding>]',
					'description' => _('Get contents of a given file.')
				),
				array(
					'key' => 'vfs.file.exists[file]',
					'description' => _('Check if file exists. 0 - file does not exist, 1 - file exists')
				),
				array(
					'key' => 'vfs.file.md5sum[file]',
					'description' => _('Calculate MD5 check sum of a given file. String MD5 hash of the file. Can be used for files less than 64MB, unsupported otherwise. Example: vfs.file.md5sum[/usr/local/etc/zabbix_agentd.conf]')
				),
				array(
					'key' => 'vfs.file.regexp[file,regexp,<encoding>,<start line>,<end line>,<output>]',
					'description' => _('Find string in a file. Matched string')
				),
				array(
					'key' => 'vfs.file.regmatch[file,regexp,<encoding>,<start line>,<end line>]',
					'description' => _('Find string in a file. 0 - expression not found, 1 - found')
				),
				array(
					'key' => 'vfs.file.size[file]',
					'description' => _('Size of a given file. Size in bytes. File must have read permissions for user zabbix. Example: vfs.file.size[/var/log/syslog]')
				),
				array(
					'key' => 'vfs.file.time[file,<mode>]',
					'description' => _('File time information. Number of seconds.The mode is optional. If mode is missing modify is used.')
				),
				array(
					'key' => 'vfs.fs.inode[fs,<mode>]',
					'description' => _('Number of inodes for a given volume. If mode is missing total is used.')
				),
				array(
					'key' => 'vfs.fs.size[fs,<mode>]',
					'description' => _('Calculate disk space for a given volume. Disk space in KB. If mode is missing total is used.  In case of mounted volume, unused disk space for local file system is returned. Example: vfs.fs.size[/tmp,free].')
				),
				array(
					'key' => 'vm.memory.size[<mode>]',
					'description' => _('Amount of memory size in bytes. If mode is missing total is used.')
				),
				array(
					'key' => 'web.page.get[host,<path>,<port>]',
					'description' => _('Get content of WEB page. Default path is /')
				),
				array(
					'key' => 'web.page.perf[host,<path>,<port>]',
					'description' => _('Get timing of loading full WEB page. Default path is /')
				),
				array(
					'key' => 'web.page.regexp[host,<path>,<port>,<regexp>,<length>,<output>]',
					'description' => _('Get first occurence of regexp in WEB page. Default path is /')
				),
				array(
					'key' => 'wmi.get[<namespace>,<query>]',
					'description' => _('Execute WMI query and return the first selected object.')
				)
			),
			ITEM_TYPE_AGGREGATE => array(
				array(
					'key' => 'grpfunc[<group>,<key>,<func>,<param>]',
					'description' => _('Aggregate checks do not require any agent running on a host being monitored. Zabbix server collects aggregate information by doing direct database queries. See Zabbix Manual.')
				)
			),
			ITEM_TYPE_SIMPLE => array(
				array(
					'key' => 'icmpping[<target>,<packets>,<interval>,<size>,<timeout>]',
					'description' => _('Checks if server is accessible by ICMP ping. 0 - ICMP ping fails. 1 - ICMP ping successful. One of zabbix_server processes performs ICMP pings once per PingerFrequency seconds.')
				),
				array(
					'key' => 'icmppingloss[<target>,<packets>,<interval>,<size>,<timeout>]',
					'description' => _('Returns percentage of lost ICMP ping packets.')
				),
				array(
					'key' => 'icmppingsec[<target>,<packets>,<interval>,<size>,<timeout>,<mode>]',
					'description' => _('Returns ICMP ping response time in seconds. Example: 0.02')
				),
				array(
					'key' => 'net.tcp.service[service,<ip>,<port>]',
					'description' => _('Check if service is available. 0 - service is down, 1 - service is running. If <ip> is missing, IP or DNS name is taken from host definition. If <port> is missing, default service port is used.')
				),
				array(
					'key' => 'net.tcp.service.perf[service,<ip>,<port>]',
					'description' => _('Check performance of service. 0 - service is down, sec - number of seconds spent on connection to the service. If <ip> is missing, IP or DNS name is taken from host definition. If <port> is missing, default service port is used.')
				),
				array(
					'key' => 'vmware.cluster.status[<url>,<name>]',
					'description' => _('VMware cluster status, <url> - VMware service URL, <name> - VMware cluster name')
				),
				array(
					'key' => 'vmware.eventlog[<url>]',
					'description' => _('VMware event log, <url> - VMware service URL')
				),
				array(
					'key' => 'vmware.fullname[<url>]',
					'description' => _('VMware service full name, <url> - VMware service URL')
				),
				array(
					'key' => 'vmware.hv.cluster.name[<url>,<uuid>]',
					'description' => _('VMware hypervisor cluster name, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.cpu.usage[<url>,<uuid>]',
					'description' => _('VMware hypervisor processor usage in Hz, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.datastore.read[<url>,<uuid>,<datastore>,<mode>]',
					'description' => _('VMware hypervisor datastore read statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <datastore> - datastore name, <mode> - latency')
				),
				array(
					'key' => 'vmware.hv.datastore.write[<url>,<uuid>,<datastore>,<mode>]',
					'description' => _('VMware hypervisor datastore write statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <datastore> - datastore name, <mode> - latency')
				),
				array(
					'key' => 'vmware.hv.full.name[<url>,<uuid>]',
					'description' => _('VMware hypervisor name, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.hw.cpu.freq[<url>,<uuid>]',
					'description' => _('VMware hypervisor processor frequency, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.hw.cpu.model[<url>,<uuid>]',
					'description' => _('VMware hypervisor processor model, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.hw.cpu.num[<url>,<uuid>]',
					'description' => _('Number of processor cores on VMware hypervisor, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.hw.cpu.threads[<url>,<uuid>]',
					'description' => _('Number of processor threads on VMware hypervisor, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.hw.memory[<url>,<uuid>]',
					'description' => _('VMware hypervisor total memory size, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.hw.model[<url>,<uuid>]',
					'description' => _('VMware hypervisor model, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.hw.uuid[<url>,<uuid>]',
					'description' => _('VMware hypervisor BIOS uuid, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.hw.vendor[<url>,<uuid>]',
					'description' => _('VMware hypervisor vendor name, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.memory.size.ballooned[<url>,<uuid>]',
					'description' => _('VMware hypervisor ballooned memory size, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.memory.used[<url>,<uuid>]',
					'description' => _('VMware hypervisor used memory size, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.network.in[<url>,<uuid>,<mode>]',
					'description' => _('VMware hypervisor network input statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <mode> - bps')
				),
				array(
					'key' => 'vmware.hv.network.out[<url>,<uuid>,<mode>]',
					'description' => _('VMware hypervisor network output statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <mode> - bps')
				),
				array(
					'key' => 'vmware.hv.status[<url>,<uuid>]',
					'description' => _('VMware hypervisor status, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.uptime[<url>,<uuid>]',
					'description' => _('VMware hypervisor uptime, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.version[<url>,<uuid>]',
					'description' => _('VMware hypervisor version, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.hv.vm.num[<url>,<uuid>]',
					'description' => _('Number of virtual machines on VMware hypervisor, <url> - VMware service URL, <uuid> - VMware hypervisor host name')
				),
				array(
					'key' => 'vmware.version[<url>]',
					'description' => _('VMware service version, <url> - VMware service URL')
				),
				array(
					'key' => 'vmware.vm.cluster.name[<url>,<uuid>]',
					'description' => _('VMware virtual machine name, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.cpu.num[<url>,<uuid>]',
					'description' => _('Number of processors on VMware virtual machine, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.cpu.usage[<url>,<uuid>]',
					'description' => _('VMware virtual machine processor usage in Hz, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.hv.name[<url>,<uuid>]',
					'description' => _('VMware virtual machine hypervisor name, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.memory.size.ballooned[<url>,<uuid>]',
					'description' => _('VMware virtual machine ballooned memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.memory.size.compressed[<url>,<uuid>]',
					'description' => _('VMware virtual machine compressed memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.memory.size.private[<url>,<uuid>]',
					'description' => _('VMware virtual machine private memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.memory.size.shared[<url>,<uuid>]',
					'description' => _('VMware virtual machine shared memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.memory.size.swapped[<url>,<uuid>]',
					'description' => _('VMware virtual machine swapped memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.memory.size.usage.guest[<url>,<uuid>]',
					'description' => _('VMware virtual machine guest memory usage, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.memory.size.usage.host[<url>,<uuid>]',
					'description' => _('VMware virtual machine host memory usage, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.memory.size[<url>,<uuid>]',
					'description' => _('VMware virtual machine total memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.net.if.in[<url>,<uuid>,<instance>,<mode>]',
					'description' => _('VMware virtual machine network interface input statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - network interface instance, <mode> - bps/pps - bytes/packets per second')
				),
				array(
					'key' => 'vmware.vm.net.if.out[<url>,<uuid>,<instance>,<mode>]',
					'description' => _('VMware virtual machine network interface output statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - network interface instance, <mode> - bps/pps - bytes/packets per second')
				),
				array(
					'key' => 'vmware.vm.powerstate[<url>,<uuid>]',
					'description' => _('VMware virtual machine power state, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.storage.committed[<url>,<uuid>]',
					'description' => _('VMware virtual machine committed storage space, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.storage.uncommitted[<url>,<uuid>]',
					'description' => _('VMware virtual machine uncommitted storage space, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.storage.unshared[<url>,<uuid>]',
					'description' => _('VMware virtual machine unshared storage space, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.uptime[<url>,<uuid>]',
					'description' => _('VMware virtual machine uptime, <url> - VMware service URL, <uuid> - VMware virtual machine host name')
				),
				array(
					'key' => 'vmware.vm.vfs.dev.read[<url>,<uuid>,<instance>,<mode>]',
					'description' => _('VMware virtual machine disk device read statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance, <mode> - bps/ops - bytes/operations per second')
				),
				array(
					'key' => 'vmware.vm.vfs.dev.write[<url>,<uuid>,<instance>,<mode>]',
					'description' => _('VMware virtual machine disk device write statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance, <mode> - bps/ops - bytes/operations per second')
				),
				array(
					'key' => 'vmware.vm.vfs.fs.size[<url>,<uuid>,<fsname>,<mode>]',
					'description' => _('VMware virtual machine file system statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <fsname> - file system name, <mode> - total/free/used/pfree/pused')
				)
			),
			ITEM_TYPE_SNMPTRAP => array(
				array(
					'key' => 'snmptrap.fallback',
					'description' => _('Catches all SNMP traps that are not caught by any of the snmptrap[] items from a corresponding address of the interface.')
				),
				array(
					'key' => 'snmptrap[<regex>]',
					'description' => _('Catches all SNMP traps from a corresponding address that match regex. Default regex is an empty string.')
				)
			),
			ITEM_TYPE_INTERNAL => array(
				array(
					'key' => 'zabbix[boottime]',
					'description' => _('Startup time of Zabbix server, Unix timestamp.')
				),
				array(
					'key' => 'zabbix[history]',
					'description' => _('Number of values stored in table HISTORY.')
				),
				array(
					'key' => 'zabbix[history_log]',
					'description' => _('Number of values stored in table HISTORY_LOG.')
				),
				array(
					'key' => 'zabbix[history_str]',
					'description' => _('Number of values stored in table HISTORY_STR.')
				),
				array(
					'key' => 'zabbix[history_text]',
					'description' => _('Number of values stored in table HISTORY_TEXT.')
				),
				array(
					'key' => 'zabbix[history_uint]',
					'description' => _('Number of values stored in table HISTORY_UINT.')
				),
				array(
					'key' => 'zabbix[host,<type>,available]',
					'description' => _('Returns availability of a particular type of checks on the host. Value of this item corresponds to availability icons in the host list. Valid types are: agent, snmp, ipmi, jmx.')
				),
				array(
					'key' => 'zabbix[hosts]',
					'description' => _('Number of monitored hosts')
				),
				array(
					'key' => 'zabbix[items]',
					'description' => _('Number of items in Zabbix database.')
				),
				array(
					'key' => 'zabbix[items_unsupported]',
					'description' => _('Number of unsupported items in Zabbix database.')
				),
				array(
					'key' => 'zabbix[java,,<param>]',
					'description' => _('Returns information associated with Zabbix Java gateway. Valid params are: ping, version.')
				),
				array(
					'key' => 'zabbix[process,<type>,<num>,<state>]',
					'description' => _('Time a particular Zabbix process or a group of processes (identified by <type> and <num>) spent in <state> in percentage.')
				),
				array(
					'key' => 'zabbix[proxy,<name>,<param>]',
					'description' => _('Time of proxy last access. Name - proxy name. Param - lastaccess. Unix timestamp.')
				),
				array(
					'key' => 'zabbix[proxy_history]',
					'description' => _('Number of items in proxy history that are not yet sent to the server')
				),
				array(
					'key' => 'zabbix[queue,<from>,<to>]',
					'description' => _('Number of items in the queue which are delayed by from to to seconds, inclusive.')
				),
				array(
					'key' => 'zabbix[rcache,<cache>,<mode>]',
					'description' => _('Configuration cache statistics. Cache - buffer (modes: pfree, total, used, free).')
				),
				array(
					'key' => 'zabbix[requiredperformance]',
					'description' => _('Required performance of the Zabbix server, in new values per second expected.')
				),
				array(
					'key' => 'zabbix[trends]',
					'description' => _('Number of values stored in table TRENDS.')
				),
				array(
					'key' => 'zabbix[trends_uint]',
					'description' => _('Number of values stored in table TRENDS_UINT.')
				),
				array(
					'key' => 'zabbix[triggers]',
					'description' => _('Number of triggers in Zabbix database.')
				),
				array(
					'key' => 'zabbix[uptime]',
					'description' => _('Uptime of Zabbix server process in seconds.')
				),
				array(
					'key' => 'zabbix[vcache,buffer,<mode>]',
					'description' => _('Value cache statistics. Valid modes are: total, free, pfree, used and pused.')
				),
				array(
					'key' => 'zabbix[vcache,cache,<parameter>]',
					'description' => _('Value cache effectiveness. Valid parameters are: requests, hits and misses.')
				),
				array(
					'key' => 'zabbix[vmware,buffer,<mode>]',
					'description' => _('VMware cache statistics. Valid modes are: total, free, pfree, used and pused.')
				),
				array(
					'key' => 'zabbix[wcache,<cache>,<mode>]',
					'description' => _('Data cache statistics. Cache - one of values (modes: all, float, uint, str, log, text), history (modes: pfree, total, used, free), trend (modes: pfree, total, used, free), text (modes: pfree, total, used, free).')
				)
			)
		);
	}
}
