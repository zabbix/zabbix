<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Class containing information about Item Keys.
 */
final class CItemKeyDefinitionData {

	/**
	* A list of all available items grouped by item type.
	*
	* @see CItemKeyDefinitionData::getItems()	for a description of the structure.
	*
	* @return array
	*/
	final static function getDefinitions(): array {
		return [
			ITEM_TYPE_ZABBIX => [
				[
					'key' => 'agent.hostmetadata',
					'description' => _('Agent host metadata. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'agent.hostname',
					'description' => _('Agent host name. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'agent.ping',
					'description' => _('Agent availability check. Returns nothing - unavailable; 1 - available'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'agent.version',
					'description' => _('Version of Zabbix agent. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'kernel.maxfiles',
					'description' => _('Maximum number of opened files supported by OS. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'kernel.maxproc',
					'description' => _('Maximum number of processes supported by OS. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'kernel.openfiles',
					'description' => _('Number of currently open file descriptors. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'modbus.get[endpoint,<slaveid>,<function>,<address>,<count>,<type>,<endianness>,<offset>]',
					'description' => _('Reads modbus data. Returns various types'),
					'value_type' => null
				],
				[
					'key' => 'net.dns[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
					'description' => _('Checks if DNS service is up. Returns 0 - DNS is down (server did not respond or DNS resolution failed); 1 - DNS is up'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.dns.record[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
					'description' => _('Performs a DNS query. Returns character string with the required type of information'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'net.if.collisions[if]',
					'description' => _('Number of out-of-window collisions. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.if.discovery',
					'description' => _('List of network interfaces. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'net.if.in[if,<mode>]',
					'description' => _('Incoming traffic statistics on network interface. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.if.list',
					'description' => _('Network interface list (includes interface type, status, IPv4 address, description). Returns text'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'net.if.out[if,<mode>]',
					'description' => _('Outgoing traffic statistics on network interface. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.if.total[if,<mode>]',
					'description' => _('Sum of incoming and outgoing traffic statistics on network interface. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.tcp.listen[port]',
					'description' => _('Checks if this TCP port is in LISTEN state. Returns 0 - it is not in LISTEN state; 1 - it is in LISTEN state'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.tcp.port[<ip>,port]',
					'description' => _('Checks if it is possible to make TCP connection to specified port. Returns 0 - cannot connect; 1 - can connect'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.tcp.service[service,<ip>,<port>]',
					'description' => _('Checks if service is running and accepting TCP connections. Returns 0 - service is down; 1 - service is running'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.tcp.service.perf[service,<ip>,<port>]',
					'description' => _('Checks performance of TCP service. Returns 0 - service is down; seconds - the number of seconds spent while connecting to the service'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'net.tcp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]',
					'description' => _('Returns number of TCP sockets that match parameters. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.udp.listen[port]',
					'description' => _('Checks if this UDP port is in LISTEN state. Returns 0 - it is not in LISTEN state; 1 - it is in LISTEN state'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.udp.service[service,<ip>,<port>]',
					'description' => _('Checks if service is running and responding to UDP requests. Returns 0 - service is down; 1 - service is running'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.udp.service.perf[service,<ip>,<port>]',
					'description' => _('Checks performance of UDP service. Returns 0 - service is down; seconds - the number of seconds spent waiting for response from the service'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'net.udp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]',
					'description' => _('Returns number of UDP sockets that match parameters. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'perf_instance.discovery[object]',
					'description' => _('List of object instances of Windows performance counters. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'perf_instance_en.discovery[object]',
					'description' => _('List of object instances of Windows performance counters, discovered using object names in English. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'perf_counter[counter,<interval>]',
					'description' => _('Value of any Windows performance counter. Returns integer, float, string or text (depending on the request)'),
					'value_type' => null
				],
				[
					'key' => 'perf_counter_en[counter,<interval>]',
					'description' => _('Value of any Windows performance counter in English. Returns integer, float, string or text (depending on the request)'),
					'value_type' => null
				],
				[
					'key' => 'proc.cpu.util[<name>,<user>,<type>,<cmdline>,<mode>,<zone>]',
					'description' => _('Process CPU utilization percentage. Returns float'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'proc.mem[<name>,<user>,<mode>,<cmdline>,<memtype>]',
					'description' => _('Memory used by process in bytes. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'proc.num[<name>,<user>,<state>,<cmdline>,<zone>]',
					'description' => _('The number of processes. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'proc_info[process,<attribute>,<type>]',
					'description' => _('Various information about specific process(es). Returns float'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'sensor[device,sensor,<mode>]',
					'description' => _('Hardware sensor reading. Returns float'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'service.info[service,<param>]',
					'description' => _('Information about a service. Returns integer with param as state, startup; string - with param as displayname, path, user; text - with param as description; Specifically for state: 0 - running, 1 - paused, 2 - start pending, 3 - pause pending, 4 - continue pending, 5 - stop pending, 6 - stopped, 7 - unknown, 255 - no such service; Specifically for startup: 0 - automatic, 1 - automatic delayed, 2 - manual, 3 - disabled, 4 - unknown'),
					'value_type' => null
				],
				[
					'key' => 'services[<type>,<state>,<exclude>]',
					'description' => _('Listing of services. Returns 0 - if empty; text - list of services separated by a newline'),
					'value_type' => null
				],
				[
					'key' => 'system.boottime',
					'description' => _('System boot time. Returns integer (Unix timestamp)'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.cpu.discovery',
					'description' => _('List of detected CPUs/CPU cores. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'system.cpu.intr',
					'description' => _('Device interrupts. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.cpu.load[<cpu>,<mode>]',
					'description' => _('CPU load. Returns float'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'system.cpu.num[<type>]',
					'description' => _('Number of CPUs. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.cpu.switches',
					'description' => _('Count of context switches. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.cpu.util[<cpu>,<type>,<mode>,<logical_or_physical>]',
					'description' => _('CPU utilization percentage. Returns float'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'system.hostname[<type>]',
					'description' => _('System host name. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'system.hw.chassis[<info>]',
					'description' => _('Chassis information. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'system.hw.cpu[<cpu>,<info>]',
					'description' => _('CPU information. Returns string or integer'),
					'value_type' => null
				],
				[
					'key' => 'system.hw.devices[<type>]',
					'description' => _('Listing of PCI or USB devices. Returns text'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'system.hw.macaddr[<interface>,<format>]',
					'description' => _('Listing of MAC addresses. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'system.localtime[<type>]',
					'description' => _('System time. Returns integer with type as UTC; string - with type as local'),
					'value_type' => null
				],
				[
					'key' => 'system.run[command,<mode>]',
					'description' => _('Run specified command on the host. Returns text result of the command; 1 - with mode as nowait (regardless of command result)'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'system.stat[resource,<type>]',
					'description' => _('System statistics. Returns integer or float'),
					'value_type' => null
				],
				[
					'key' => 'system.sw.arch',
					'description' => _('Software architecture information. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'system.sw.os[<info>]',
					'description' => _('Operating system information. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'system.sw.packages[<package>,<manager>,<format>]',
					'description' => _('Listing of installed packages. Returns text'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'system.swap.in[<device>,<type>]',
					'description' => _('Swap in (from device into memory) statistics. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.swap.out[<device>,<type>]',
					'description' => _('Swap out (from memory onto device) statistics. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.swap.size[<device>,<type>]',
					'description' => _('Swap space size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
					'value_type' => null
				],
				[
					'key' => 'system.uname',
					'description' => _('Identification of the system. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'system.uptime',
					'description' => _('System uptime in seconds. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.users.num',
					'description' => _('Number of users logged in. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.dev.discovery',
					'description' => _('List of block devices and their type. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vfs.dev.read[<device>,<type>,<mode>]',
					'description' => _('Disk read statistics. Returns integer with type in sectors, operations, bytes; float with type in sps, ops, bps'),
					'value_type' => null
				],
				[
					'key' => 'vfs.dev.write[<device>,<type>,<mode>]',
					'description' => _('Disk write statistics. Returns integer with type in sectors, operations, bytes; float with type in sps, ops, bps'),
					'value_type' => null
				],
				[
					'key' => 'vfs.dir.count[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]',
					'description' => _('Count of directory entries, recursively. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.dir.size[dir,<regex_incl>,<regex_excl>,<mode>,<max_depth>,<regex_excl_dir>]',
					'description' => _('Directory size (in bytes). Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.file.cksum[file,<mode>]',
					'description' => _('File checksum, calculated by the UNIX cksum algorithm. Returns integer for crc32 (default) and string for md5, sha256'),
					'value_type' => null
				],
				[
					'key' => 'vfs.file.contents[file,<encoding>]',
					'description' => _('Retrieving contents of a file. Returns text'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vfs.file.exists[file,<types_incl>,<types_excl>]',
					'description' => _('Checks if file exists. Returns 0 - not found; 1 - file of the specified type exists'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.file.get[file]',
					'description' => _('Information about a file. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vfs.file.md5sum[file]',
					'description' => _('MD5 checksum of file. Returns character string (MD5 hash of the file)'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vfs.file.owner[file,<ownertype>,<resulttype>]',
					'description' => _('File owner information. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vfs.file.permissions[file]',
					'description' => _('Returns 4-digit string containing octal number with Unix permissions'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vfs.file.regexp[file,regexp,<encoding>,<start line>,<end line>,<output>]',
					'description' => _('Find string in a file. Returns the line containing the matched string, or as specified by the optional output parameter'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vfs.file.regmatch[file,regexp,<encoding>,<start line>,<end line>]',
					'description' => _('Find string in a file. Returns 0 - match not found; 1 - found'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.file.size[file,<mode>]',
					'description' => _('File size in bytes (default) or in newlines. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.file.time[file,<mode>]',
					'description' => _('File time information. Returns integer (Unix timestamp)'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.fs.discovery',
					'description' => _('List of mounted filesystems and their types. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vfs.fs.get',
					'description' => _('List of mounted filesystems, their types, disk space and inode statistics. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vfs.fs.inode[fs,<mode>]',
					'description' => _('Number or percentage of inodes. Returns integer for number; float for percentage'),
					'value_type' => null
				],
				[
					'key' => 'vfs.fs.size[fs,<mode>]',
					'description' => _('Disk space in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
					'value_type' => null
				],
				[
					'key' => 'vm.memory.size[<mode>]',
					'description' => _('Memory size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
					'value_type' => null
				],
				[
					'key' => 'vm.vmemory.size[<type>]',
					'description' => _('Virtual space size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
					'value_type' => null
				],
				[
					'key' => 'web.page.get[host,<path>,<port>]',
					'description' => _('Get content of web page. Returns web page source as text'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'web.page.perf[host,<path>,<port>]',
					'description' => _('Loading time of full web page (in seconds). Returns float'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'web.page.regexp[host,<path>,<port>,regexp,<length>,<output>]',
					'description' => _('Find string on a web page. Returns the matched string, or as specified by the optional output parameter'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'wmi.get[<namespace>,<query>]',
					'description' => _('Execute WMI query and return the first selected object. Returns integer, float, string or text (depending on the request)'),
					'value_type' => null
				],
				[
					'key' => 'wmi.getall[<namespace>,<query>]',
					'description' => _('Execute WMI query and return the JSON document with all selected objects'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'zabbix.stats[<ip>,<port>]',
					'description' => _('Returns a JSON object containing Zabbix server or proxy internal metrics.'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'zabbix.stats[<ip>,<port>,queue,<from>,<to>]',
					'description' => _('Number of items in the queue which are delayed in Zabbix server or proxy by "from" till "to" seconds, inclusive.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				]
			],
			ITEM_TYPE_ZABBIX_ACTIVE => [
				[
					'key' => 'agent.hostmetadata',
					'description' => _('Agent host metadata. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'agent.hostname',
					'description' => _('Agent host name. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'agent.ping',
					'description' => _('Agent availability check. Returns nothing - unavailable; 1 - available'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'agent.version',
					'description' => _('Version of Zabbix agent. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'eventlog[name,<regexp>,<severity>,<source>,<eventid>,<maxlines>,<mode>]',
					'description' => _('Event log monitoring. Returns log'),
					'value_type' => ITEM_VALUE_TYPE_LOG
				],
				[
					'key' => 'kernel.maxfiles',
					'description' => _('Maximum number of opened files supported by OS. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'kernel.maxproc',
					'description' => _('Maximum number of processes supported by OS. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'kernel.openfiles',
					'description' => _('Number of currently open file descriptors. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'log[file,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>]',
					'description' => _('Log file monitoring. Returns log'),
					'value_type' => ITEM_VALUE_TYPE_LOG
				],
				[
					'key' => 'log.count[file,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>]',
					'description' => _('Count of matched lines in log file monitoring. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'logrt[file_regexp,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>]',
					'description' => _('Log file monitoring with log rotation support. Returns log'),
					'value_type' => ITEM_VALUE_TYPE_LOG
				],
				[
					'key' => 'logrt.count[file_regexp,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>]',
					'description' => _('Count of matched lines in log file monitoring with log rotation support. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'modbus.get[endpoint,<slaveid>,<function>,<address>,<count>,<type>,<endianness>,<offset>]',
					'description' => _('Reads modbus data. Returns various types'),
					'value_type' => null
				],
				[
					'key' => 'mqtt.get[<broker_url>,topic]',
					'description' => _('Value of MQTT topic. Format of returned data depends on the topic content. If wildcards are used, returns topic values in JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'net.dns[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
					'description' => _('Checks if DNS service is up. Returns 0 - DNS is down (server did not respond or DNS resolution failed); 1 - DNS is up'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.dns.record[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
					'description' => _('Performs a DNS query. Returns character string with the required type of information'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'net.if.collisions[if]',
					'description' => _('Number of out-of-window collisions. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.if.discovery',
					'description' => _('List of network interfaces. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'net.if.in[if,<mode>]',
					'description' => _('Incoming traffic statistics on network interface. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.if.list',
					'description' => _('Network interface list (includes interface type, status, IPv4 address, description). Returns text'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'net.if.out[if,<mode>]',
					'description' => _('Outgoing traffic statistics on network interface. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.if.total[if,<mode>]',
					'description' => _('Sum of incoming and outgoing traffic statistics on network interface. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.tcp.listen[port]',
					'description' => _('Checks if this TCP port is in LISTEN state. Returns 0 - it is not in LISTEN state; 1 - it is in LISTEN state'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.tcp.port[<ip>,port]',
					'description' => _('Checks if it is possible to make TCP connection to specified port. Returns 0 - cannot connect; 1 - can connect'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.tcp.service[service,<ip>,<port>]',
					'description' => _('Checks if service is running and accepting TCP connections. Returns 0 - service is down; 1 - service is running'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.tcp.service.perf[service,<ip>,<port>]',
					'description' => _('Checks performance of TCP service. Returns 0 - service is down; seconds - the number of seconds spent while connecting to the service'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'net.tcp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]',
					'description' => _('Returns number of TCP sockets that match parameters. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.udp.listen[port]',
					'description' => _('Checks if this UDP port is in LISTEN state. Returns 0 - it is not in LISTEN state; 1 - it is in LISTEN state'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.udp.service[service,<ip>,<port>]',
					'description' => _('Checks if service is running and responding to UDP requests. Returns 0 - service is down; 1 - service is running'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.udp.service.perf[service,<ip>,<port>]',
					'description' => _('Checks performance of UDP service. Returns 0 - service is down; seconds - the number of seconds spent waiting for response from the service'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'net.udp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]',
					'description' => _('Returns number of UDP sockets that match parameters. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'perf_instance.discovery[object]',
					'description' => _('List of object instances of Windows performance counters. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'perf_instance_en.discovery[object]',
					'description' => _('List of object instances of Windows performance counters, discovered using object names in English. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'perf_counter[counter,<interval>]',
					'description' => _('Value of any Windows performance counter. Returns integer, float, string or text (depending on the request)'),
					'value_type' => null
				],
				[
					'key' => 'perf_counter_en[counter,<interval>]',
					'description' => _('Value of any Windows performance counter in English. Returns integer, float, string or text (depending on the request)'),
					'value_type' => null
				],
				[
					'key' => 'proc.cpu.util[<name>,<user>,<type>,<cmdline>,<mode>,<zone>]',
					'description' => _('Process CPU utilization percentage. Returns float'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'proc.mem[<name>,<user>,<mode>,<cmdline>,<memtype>]',
					'description' => _('Memory used by process in bytes. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'proc.num[<name>,<user>,<state>,<cmdline>,<zone>]',
					'description' => _('The number of processes. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'proc_info[process,<attribute>,<type>]',
					'description' => _('Various information about specific process(es). Returns float'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'sensor[device,sensor,<mode>]',
					'description' => _('Hardware sensor reading. Returns float'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'service.info[service,<param>]',
					'description' => _('Information about a service. Returns integer with param as state, startup; string - with param as displayname, path, user; text - with param as description; Specifically for state: 0 - running, 1 - paused, 2 - start pending, 3 - pause pending, 4 - continue pending, 5 - stop pending, 6 - stopped, 7 - unknown, 255 - no such service; Specifically for startup: 0 - automatic, 1 - automatic delayed, 2 - manual, 3 - disabled, 4 - unknown'),
					'value_type' => null
				],
				[
					'key' => 'services[<type>,<state>,<exclude>]',
					'description' => _('Listing of services. Returns 0 - if empty; text - list of services separated by a newline'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'system.boottime',
					'description' => _('System boot time. Returns integer (Unix timestamp)'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.cpu.discovery',
					'description' => _('List of detected CPUs/CPU cores. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'system.cpu.intr',
					'description' => _('Device interrupts. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.cpu.load[<cpu>,<mode>]',
					'description' => _('CPU load. Returns float'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'system.cpu.num[<type>]',
					'description' => _('Number of CPUs. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.cpu.switches',
					'description' => _('Count of context switches. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.cpu.util[<cpu>,<type>,<mode>,<logical_or_physical>]',
					'description' => _('CPU utilization percentage. Returns float'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'system.hostname[<type>]',
					'description' => _('System host name. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'system.hw.chassis[<info>]',
					'description' => _('Chassis information. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'system.hw.cpu[<cpu>,<info>]',
					'description' => _('CPU information. Returns string or integer'),
					'value_type' => null
				],
				[
					'key' => 'system.hw.devices[<type>]',
					'description' => _('Listing of PCI or USB devices. Returns text'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'system.hw.macaddr[<interface>,<format>]',
					'description' => _('Listing of MAC addresses. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'system.localtime[<type>]',
					'description' => _('System time. Returns integer with type as UTC; string - with type as local'),
					'value_type' => null
				],
				[
					'key' => 'system.run[command,<mode>]',
					'description' => _('Run specified command on the host. Returns text result of the command; 1 - with mode as nowait (regardless of command result)'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'system.stat[resource,<type>]',
					'description' => _('System statistics. Returns integer or float'),
					'value_type' => null
				],
				[
					'key' => 'system.sw.arch',
					'description' => _('Software architecture information. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'system.sw.os[<info>]',
					'description' => _('Operating system information. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'system.sw.packages[<package>,<manager>,<format>]',
					'description' => _('Listing of installed packages. Returns text'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'system.swap.in[<device>,<type>]',
					'description' => _('Swap in (from device into memory) statistics. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.swap.out[<device>,<type>]',
					'description' => _('Swap out (from memory onto device) statistics. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.swap.size[<device>,<type>]',
					'description' => _('Swap space size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
					'value_type' => null
				],
				[
					'key' => 'system.uname',
					'description' => _('Identification of the system. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'system.uptime',
					'description' => _('System uptime in seconds. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'system.users.num',
					'description' => _('Number of users logged in. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.dev.discovery',
					'description' => _('List of block devices and their type. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vfs.dev.read[<device>,<type>,<mode>]',
					'description' => _('Disk read statistics. Returns integer with type in sectors, operations, bytes; float with type in sps, ops, bps'),
					'value_type' => null
				],
				[
					'key' => 'vfs.dev.write[<device>,<type>,<mode>]',
					'description' => _('Disk write statistics. Returns integer with type in sectors, operations, bytes; float with type in sps, ops, bps'),
					'value_type' => null
				],
				[
					'key' => 'vfs.dir.count[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]',
					'description' => _('Count of directory entries, recursively. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.dir.size[dir,<regex_incl>,<regex_excl>,<mode>,<max_depth>,<regex_excl_dir>]',
					'description' => _('Directory size (in bytes). Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.file.cksum[file,<mode>]',
					'description' => _('File checksum, calculated by the UNIX cksum algorithm. Returns integer for crc32 (default) and string for md5, sha256'),
					'value_type' => null
				],
				[
					'key' => 'vfs.file.contents[file,<encoding>]',
					'description' => _('Retrieving contents of a file. Returns text'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vfs.file.exists[file,<types_incl>,<types_excl>]',
					'description' => _('Checks if file exists. Returns 0 - not found; 1 - file of the specified type exists'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.file.get[file]',
					'description' => _('Information about a file. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vfs.file.md5sum[file]',
					'description' => _('MD5 checksum of file. Returns character string (MD5 hash of the file)'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vfs.file.owner[file,<ownertype>,<resulttype>]',
					'description' => _('File owner information. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vfs.file.permissions[file]',
					'description' => _('Returns 4-digit string containing octal number with Unix permissions'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vfs.file.regexp[file,regexp,<encoding>,<start line>,<end line>,<output>]',
					'description' => _('Find string in a file. Returns the line containing the matched string, or as specified by the optional output parameter'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vfs.file.regmatch[file,regexp,<encoding>,<start line>,<end line>]',
					'description' => _('Find string in a file. Returns 0 - match not found; 1 - found'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.file.size[file,<mode>]',
					'description' => _('File size in bytes (default) or in newlines. Returns integer'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.file.time[file,<mode>]',
					'description' => _('File time information. Returns integer (Unix timestamp)'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vfs.fs.discovery',
					'description' => _('List of mounted filesystems and their types. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vfs.fs.get',
					'description' => _('List of mounted filesystems, their types, disk space and inode statistics. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vfs.fs.inode[fs,<mode>]',
					'description' => _('Number or percentage of inodes. Returns integer for number; float for percentage'),
					'value_type' => null
				],
				[
					'key' => 'vfs.fs.size[fs,<mode>]',
					'description' => _('Disk space in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
					'value_type' => null
				],
				[
					'key' => 'vm.memory.size[<mode>]',
					'description' => _('Memory size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
					'value_type' => null
				],
				[
					'key' => 'vm.vmemory.size[<type>]',
					'description' => _('Virtual space size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
					'value_type' => null
				],
				[
					'key' => 'web.page.get[host,<path>,<port>]',
					'description' => _('Get content of web page. Returns web page source as text'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'web.page.perf[host,<path>,<port>]',
					'description' => _('Loading time of full web page (in seconds). Returns float'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'web.page.regexp[host,<path>,<port>,regexp,<length>,<output>]',
					'description' => _('Find string on a web page. Returns the matched string, or as specified by the optional output parameter'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'wmi.get[<namespace>,<query>]',
					'description' => _('Execute WMI query and return the first selected object. Returns integer, float, string or text (depending on the request)'),
					'value_type' => null
				],
				[
					'key' => 'zabbix.stats[<ip>,<port>]',
					'description' => _('Returns a JSON object containing Zabbix server or proxy internal metrics.'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'zabbix.stats[<ip>,<port>,queue,<from>,<to>]',
					'description' => _('Number of items in the queue which are delayed in Zabbix server or proxy by "from" till "to" seconds, inclusive.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				]
			],
			ITEM_TYPE_SIMPLE => [
				[
					'key' => 'icmpping[<target>,<packets>,<interval>,<size>,<timeout>]',
					'description' => _('Checks if host is accessible by ICMP ping. 0 - ICMP ping fails. 1 - ICMP ping successful.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'icmppingloss[<target>,<packets>,<interval>,<size>,<timeout>]',
					'description' => _('Returns percentage of lost ICMP ping packets.'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'icmppingsec[<target>,<packets>,<interval>,<size>,<timeout>,<mode>]',
					'description' => _('Returns ICMP ping response time in seconds. Example: 0.02'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'net.tcp.service[service,<ip>,<port>]',
					'description' => _('Checks if service is running and accepting TCP connections. Returns 0 - service is down; 1 - service is running'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.tcp.service.perf[service,<ip>,<port>]',
					'description' => _('Checks performance of TCP service. Returns 0 - service is down; seconds - the number of seconds spent while connecting to the service'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'net.udp.service[service,<ip>,<port>]',
					'description' => _('Checks if service is running and responding to UDP requests. Returns 0 - service is down; 1 - service is running'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'net.udp.service.perf[service,<ip>,<port>]',
					'description' => _('Checks performance of UDP service. Returns 0 - service is down; seconds - the number of seconds spent waiting for response from the service'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.cluster.discovery[<url>]',
					'description' => _('Discovery of VMware clusters, <url> - VMware service URL. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.cluster.status[<url>,<name>]',
					'description' => _('VMware cluster status, <url> - VMware service URL, <name> - VMware cluster name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.cl.perfcounter[<url>,<id>,<path>,<instance>]',
					'description' => _('VMware cluster performance counter, <url> - VMware service URL, <id> - VMware cluster id, <path> - performance counter path, <instance> - performance counter instance'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.datastore.discovery[<url>]',
					'description' => _('Discovery of VMware datastores, <url> - VMware service URL. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.datastore.hv.list[<url>,<datastore>]',
					'description' => _('VMware datastore hypervisors list, <url> - VMware service URL, <datastore> - datastore name'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.datastore.read[<url>,<datastore>,<mode>]',
					'description' => _('VMware datastore read statistics, <url> - VMware service URL, <datastore> - datastore name, <mode> - latency/maxlatency - average or maximum'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.datastore.size[<url>,<datastore>,<mode>]',
					'description' => _('VMware datastore capacity statistics in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
					'value_type' => null
				],
				[
					'key' => 'vmware.datastore.write[<url>,<datastore>,<mode>]',
					'description' => _('VMware datastore write statistics, <url> - VMware service URL, <datastore> - datastore name, <mode> - latency/maxlatency - average or maximum'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.dc.discovery[<url>]',
					'description' => _('VMware datacenters and their IDs. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.eventlog[<url>,<mode>]',
					'description' => _('VMware event log, <url> - VMware service URL, <mode> - all (default), skip - skip processing of older data'),
					'value_type' => ITEM_VALUE_TYPE_LOG
				],
				[
					'key' => 'vmware.fullname[<url>]',
					'description' => _('VMware service full name, <url> - VMware service URL'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vmware.hv.cluster.name[<url>,<uuid>]',
					'description' => _('VMware hypervisor cluster name, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vmware.hv.cpu.usage[<url>,<uuid>]',
					'description' => _('VMware hypervisor processor usage in Hz, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.hv.cpu.usage.perf[<url>,<uuid>]',
					'description' => _('CPU usage as a percentage during the interval, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.hv.cpu.utilization[<url>,<uuid>]',
					'description' => _('CPU usage as a percentage during the interval depends on power management or HT, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.hv.datacenter.name[<url>,<uuid>]',
					'description' => _('VMware hypervisor datacenter name, <url> - VMware service URL, <uuid> - VMware hypervisor host name. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vmware.hv.datastore.discovery[<url>,<uuid>]',
					'description' => _('Discovery of VMware hypervisor datastores, <url> - VMware service URL, <uuid> - VMware hypervisor host name. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.hv.datastore.list[<url>,<uuid>]',
					'description' => _('VMware hypervisor datastores list, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.hv.datastore.multipath[<url>,<uuid>,<datastore>,<partitionid>]',
					'description' => _('Number of available DS paths, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <datastore> - Datastore name, <partitionid> - internal id of physical device from vmware.hv.datastore.discovery'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.hv.datastore.read[<url>,<uuid>,<datastore>,<mode>]',
					'description' => _('VMware hypervisor datastore read statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <datastore> - datastore name, <mode> - latency'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.hv.datastore.size[<url>,<uuid>,<datastore>,<mode>]',
					'description' => _('VMware datastore capacity statistics in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
					'value_type' => null
				],
				[
					'key' => 'vmware.hv.datastore.write[<url>,<uuid>,<datastore>,<mode>]',
					'description' => _('VMware hypervisor datastore write statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <datastore> - datastore name, <mode> - latency'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.hv.discovery[<url>]',
					'description' => _('Discovery of VMware hypervisors, <url> - VMware service URL. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.hv.fullname[<url>,<uuid>]',
					'description' => _('VMware hypervisor name, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vmware.hv.hw.cpu.freq[<url>,<uuid>]',
					'description' => _('VMware hypervisor processor frequency, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.hv.hw.cpu.model[<url>,<uuid>]',
					'description' => _('VMware hypervisor processor model, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vmware.hv.hw.cpu.num[<url>,<uuid>]',
					'description' => _('Number of processor cores on VMware hypervisor, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.hv.hw.cpu.threads[<url>,<uuid>]',
					'description' => _('Number of processor threads on VMware hypervisor, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.hv.hw.memory[<url>,<uuid>]',
					'description' => _('VMware hypervisor total memory size, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.hv.hw.model[<url>,<uuid>]',
					'description' => _('VMware hypervisor model, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vmware.hv.hw.uuid[<url>,<uuid>]',
					'description' => _('VMware hypervisor BIOS UUID, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vmware.hv.hw.vendor[<url>,<uuid>]',
					'description' => _('VMware hypervisor vendor name, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vmware.hv.memory.size.ballooned[<url>,<uuid>]',
					'description' => _('VMware hypervisor ballooned memory size, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.hv.memory.used[<url>,<uuid>]',
					'description' => _('VMware hypervisor used memory size, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.hv.network.in[<url>,<uuid>,<mode>]',
					'description' => _('VMware hypervisor network input statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <mode> - bps'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.hv.network.out[<url>,<uuid>,<mode>]',
					'description' => _('VMware hypervisor network output statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <mode> - bps'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.hv.perfcounter[<url>,<uuid>,<path>,<instance>]',
					'description' => _('VMware hypervisor performance counter, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <path> - performance counter path, <instance> - performance counter instance'),
					'value_type' => null
				],
				[
					'key' => 'vmware.hv.power[<url>,<uuid>,<max>]',
					'description' => _('Power usage , <url> - VMware service URL, <uuid> - VMware hypervisor host name, <max> - Maximum allowed power usage'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.hv.sensor.health.state[<url>,<uuid>]',
					'description' => _('VMware hypervisor health state rollup sensor, <url> - VMware service URL, <uuid> - VMware hypervisor host name. Returns 0 - gray; 1 - green; 2 - yellow; 3 - red'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.hv.status[<url>,<uuid>]',
					'description' => _('VMware hypervisor status, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => null
				],
				[
					'key' => 'vmware.hv.uptime[<url>,<uuid>]',
					'description' => _('VMware hypervisor uptime, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.hv.version[<url>,<uuid>]',
					'description' => _('VMware hypervisor version, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vmware.hv.vm.num[<url>,<uuid>]',
					'description' => _('Number of virtual machines on VMware hypervisor, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.version[<url>]',
					'description' => _('VMware service version, <url> - VMware service URL'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vmware.vm.cluster.name[<url>,<uuid>]',
					'description' => _('VMware virtual machine name, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vmware.vm.cpu.latency[<url>,<uuid>]',
					'description' => _('Percent of time the virtual machine is unable to run because it is contending for access to the physical CPU(s), <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.vm.cpu.num[<url>,<uuid>]',
					'description' => _('Number of processors on VMware virtual machine, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.cpu.readiness[<url>,<uuid>,<instance>]',
					'description' => _('Percentage of time that the virtual machine was ready, but could not get scheduled to run on the physical CPU, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - CPU instance'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.vm.cpu.ready[<url>,<uuid>]',
					'description' => _('VMware virtual machine processor ready time ms, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.cpu.swapwait[<url>,<uuid>,<instance>]',
					'description' => _('CPU time spent waiting for swap-in, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - CPU instance'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.cpu.usage[<url>,<uuid>]',
					'description' => _('VMware virtual machine processor usage in Hz, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.cpu.usage.perf[<url>,<uuid>]',
					'description' => _('CPU usage as a percentage during the interval, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.datacenter.name[<url>,<uuid>]',
					'description' => _('VMware virtual machine datacenter name, <url> - VMware service URL, <uuid> - VMware virtual machine host name. Returns string'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vmware.vm.discovery[<url>]',
					'description' => _('Discovery of VMware virtual machines, <url> - VMware service URL. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.vm.guest.memory.size.swapped[<url>,<uuid>]',
					'description' => _('Amount of guest physical memory that is swapped out to the swap space, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.guest.osuptime[<url>,<uuid>]',
					'description' => _('Total time elapsed, in seconds, since last operating system boot-up, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.hv.name[<url>,<uuid>]',
					'description' => _('VMware virtual machine hypervisor name, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_STR
				],
				[
					'key' => 'vmware.vm.memory.size[<url>,<uuid>]',
					'description' => _('VMware virtual machine total memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.memory.size.ballooned[<url>,<uuid>]',
					'description' => _('VMware virtual machine ballooned memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.memory.size.compressed[<url>,<uuid>]',
					'description' => _('VMware virtual machine compressed memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.memory.size.consumed[<url>,<uuid>]',
					'description' => _('Amount of host physical memory consumed for backing up guest physical memory pages, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.memory.size.private[<url>,<uuid>]',
					'description' => _('VMware virtual machine private memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.memory.size.shared[<url>,<uuid>]',
					'description' => _('VMware virtual machine shared memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.memory.size.swapped[<url>,<uuid>]',
					'description' => _('VMware virtual machine swapped memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.memory.size.usage.guest[<url>,<uuid>]',
					'description' => _('VMware virtual machine guest memory usage, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.memory.size.usage.host[<url>,<uuid>]',
					'description' => _('VMware virtual machine host memory usage, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.memory.usage[<url>,<uuid>]',
					'description' => _('Percentage of host physical memory that has been consumed, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.vm.net.if.discovery[<url>,<uuid>]',
					'description' => 'Discovery of VMware virtual machine network interfaces, <url> - VMware service URL, <uuid> - VMware virtual machine host name. Returns JSON',
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.vm.net.if.in[<url>,<uuid>,<instance>,<mode>]',
					'description' => _('VMware virtual machine network interface input statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - network interface instance, <mode> - bps/pps - bytes/packets per second'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.vm.net.if.out[<url>,<uuid>,<instance>,<mode>]',
					'description' => _('VMware virtual machine network interface output statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - network interface instance, <mode> - bps/pps - bytes/packets per second'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.vm.net.if.usage[<url>,<uuid>,<instance>]',
					'description' => _('Network utilization (combined transmit-rates and receive-rates) during the interval, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - network interface instance'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.vm.perfcounter[<url>,<uuid>,<path>,<instance>]',
					'description' => _('VMware virtual machine performance counter, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <path> - performance counter path, <instance> - performance counter instance'),
					'value_type' => null
				],
				[
					'key' => 'vmware.vm.powerstate[<url>,<uuid>]',
					'description' => _('VMware virtual machine power state, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.storage.committed[<url>,<uuid>]',
					'description' => _('VMware virtual machine committed storage space, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => null
				],
				[
					'key' => 'vmware.vm.storage.readoio[<url>,<uuid>,<instance>]',
					'description' => _('Average number of outstanding read requests to the virtual disk during the collection interval , <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.storage.totalreadlatency[<url>,<uuid>,<instance>]',
					'description' => _('The average time a read from the virtual disk takes, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.storage.totalwritelatency[<url>,<uuid>,<instance>]',
					'description' => _('The average time a write to the virtual disk takes, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.storage.uncommitted[<url>,<uuid>]',
					'description' => _('VMware virtual machine uncommitted storage space, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.storage.unshared[<url>,<uuid>]',
					'description' => _('VMware virtual machine unshared storage space, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.storage.writeoio[<url>,<uuid>,<instance>]',
					'description' => _('Average number of outstanding write requests to the virtual disk during the collection interval, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.uptime[<url>,<uuid>]',
					'description' => _('VMware virtual machine uptime, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'vmware.vm.vfs.dev.discovery[<url>,<uuid>]',
					'description' => _('Discovery of VMware virtual machine disk devices, <url> - VMware service URL, <uuid> - VMware virtual machine host name. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.vm.vfs.dev.read[<url>,<uuid>,<instance>,<mode>]',
					'description' => _('VMware virtual machine disk device read statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance, <mode> - bps/ops - bytes/operations per second'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.vm.vfs.dev.write[<url>,<uuid>,<instance>,<mode>]',
					'description' => _('VMware virtual machine disk device write statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance, <mode> - bps/ops - bytes/operations per second'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'vmware.vm.vfs.fs.discovery[<url>,<uuid>]',
					'description' => _('Discovery of VMware virtual machine file systems, <url> - VMware service URL, <uuid> - VMware virtual machine host name. Returns JSON'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'vmware.vm.vfs.fs.size[<url>,<uuid>,<fsname>,<mode>]',
					'description' => _('VMware virtual machine file system statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <fsname> - file system name, <mode> - total/free/used/pfree/pused'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				]
			],
			ITEM_TYPE_SNMPTRAP => [
				[
					'key' => 'snmptrap.fallback',
					'description' => _('Catches all SNMP traps that were not caught by any of snmptrap[] items.'),
					'value_type' => null
				],
				[
					'key' => 'snmptrap[<regex>]',
					'description' => _('Catches all SNMP traps that match regex. If regexp is unspecified, catches any trap.'),
					'value_type' => null
				]
			],
			ITEM_TYPE_INTERNAL => [
				[
					'key' => 'zabbix[boottime]',
					'description' => _('Startup time of Zabbix server, Unix timestamp.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[history]',
					'description' => _('Number of values stored in table HISTORY.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[history_log]',
					'description' => _('Number of values stored in table HISTORY_LOG.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[history_str]',
					'description' => _('Number of values stored in table HISTORY_STR.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[history_text]',
					'description' => _('Number of values stored in table HISTORY_TEXT.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[history_uint]',
					'description' => _('Number of values stored in table HISTORY_UINT.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[host,,items]',
					'description' => _('Number of enabled items on the host.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[host,,items_unsupported]',
					'description' => _('Number of unsupported items on the host.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[host,,maintenance]',
					'description' => _('Returns current maintenance status of the host.'),
					'value_type' => null
				],
				[
					'key' => 'zabbix[host,discovery,interfaces]',
					'description' => _('Returns a JSON array describing the host network interfaces configured in Zabbix. Can be used for LLD.'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'zabbix[host,<type>,available]',
					'description' => _('Returns availability of a particular type of checks on the host. Value of this item corresponds to availability icons in the host list. Valid types are: agent, snmp, ipmi, jmx.'),
					'value_type' => null
				],
				[
					'key' => 'zabbix[hosts]',
					'description' => _('Number of monitored hosts'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[items]',
					'description' => _('Number of items in Zabbix database.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[items_unsupported]',
					'description' => _('Number of unsupported items in Zabbix database.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[java,,<param>]',
					'description' => _('Returns information associated with Zabbix Java gateway. Valid params are: ping, version.'),
					'value_type' => null
				],
				[
					'key' => 'zabbix[lld_queue]',
					'description' => _('Count of values enqueued in the low-level discovery processing queue.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[preprocessing_queue]',
					'description' => _('Count of values enqueued in the preprocessing queue.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[process,<type>,<mode>,<state>]',
					'description' => _('Time a particular Zabbix process or a group of processes (identified by <type> and <mode>) spent in <state> in percentage.'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'zabbix[proxy,<name>,<param>]',
					'description' => _('Time of proxy last access. Name - proxy name. Valid params are: lastaccess - Unix timestamp, delay - seconds.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[proxy_history]',
					'description' => _('Number of items in proxy history that are not yet sent to the server'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[queue,<from>,<to>]',
					'description' => _('Number of items in the queue which are delayed by from to to seconds, inclusive.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[rcache,<cache>,<mode>]',
					'description' => _('Configuration cache statistics. Cache - buffer (modes: pfree, total, used, free).'),
					'value_type' => null
				],
				[
					'key' => 'zabbix[requiredperformance]',
					'description' => _('Required performance of the Zabbix server, in new values per second expected.'),
					'value_type' => ITEM_VALUE_TYPE_FLOAT
				],
				[
					'key' => 'zabbix[stats,<ip>,<port>]',
					'description' => _('Returns a JSON object containing Zabbix server or proxy internal metrics.'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'zabbix[stats,<ip>,<port>,queue,<from>,<to>]',
					'description' => _('Number of items in the queue which are delayed in Zabbix server or proxy by "from" till "to" seconds, inclusive.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[tcache, cache, <parameter>]',
					'description' => _('Trend function cache statistics. Valid parameters are: all, hits, phits, misses, pmisses, items, pitems and requests.'),
					'value_type' => null
				],
				[
					'key' => 'zabbix[trends]',
					'description' => _('Number of values stored in table TRENDS.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[trends_uint]',
					'description' => _('Number of values stored in table TRENDS_UINT.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[triggers]',
					'description' => _('Number of triggers in Zabbix database.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[uptime]',
					'description' => _('Uptime of Zabbix server process in seconds.'),
					'value_type' => ITEM_VALUE_TYPE_UINT64
				],
				[
					'key' => 'zabbix[vcache,buffer,<mode>]',
					'description' => _('Value cache statistics. Valid modes are: total, free, pfree, used and pused.'),
					'value_type' => null
				],
				[
					'key' => 'zabbix[vcache,cache,<parameter>]',
					'description' => _('Value cache effectiveness. Valid parameters are: requests, hits and misses.'),
					'value_type' => null
				],
				[
					'key' => 'zabbix[version]',
					'description' => _('Version of Zabbix server or proxy'),
					'value_type' => null
				],
				[
					'key' => 'zabbix[vmware,buffer,<mode>]',
					'description' => _('VMware cache statistics. Valid modes are: total, free, pfree, used and pused.'),
					'value_type' => null
				],
				[
					'key' => 'zabbix[wcache,<cache>,<mode>]',
					'description' => _('Statistics and availability of Zabbix write cache. Cache - one of values (modes: all, float, uint, str, log, text, not supported), history (modes: pfree, free, total, used, pused), index (modes: pfree, free, total, used, pused), trend (modes: pfree, free, total, used, pused).'),
					'value_type' => null
				]
			],
			ITEM_TYPE_DB_MONITOR => [
				[
					'key' => 'db.odbc.select[<unique short description>,<dsn>,<connection string>]',
					'description' => _('Return first column of the first row of the SQL query result.'),
					'value_type' => null
				],
				[
					'key' => 'db.odbc.discovery[<unique short description>,<dsn>,<connection string>]',
					'description' => _('Transform SQL query result into a JSON array for low-level discovery.'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'db.odbc.get[<unique short description>,<dsn>,<connection string>]',
					'description' => _('Transform SQL query result into a JSON array.'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				]
			],
			ITEM_TYPE_JMX => [
				[
					'key' => 'jmx[object_name,attribute_name,<unique short description>]',
					'description' => _('Return value of an attribute of MBean object.'),
					'value_type' => null
				],
				[
					'key' => 'jmx.discovery[<discovery mode>,<object name>,<unique short description>]',
					'description' => _('Return a JSON array with LLD macros describing the MBean objects or their attributes. Can be used for LLD.'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				],
				[
					'key' => 'jmx.get[<discovery mode>,<object name>,<unique short description>]',
					'description' => _('Return a JSON array with MBean objects or their attributes. Compared to jmx.discovery it does not define LLD macros. Can be used for LLD.'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				]
			],
			ITEM_TYPE_IPMI => [
				[
					'key' => 'ipmi.get',
					'description' => _('IPMI sensor IDs and other sensor-related parameters. Returns JSON.'),
					'value_type' => ITEM_VALUE_TYPE_TEXT
				]
			]
		];
	}

	/**
	* Returns the items available for the given item type.
	*
	* @param int $type
	*
	* @return array
	*/
	public static function getByType($type): array {
		return static::getItems()[$type];
	}

	/**
	* Get list of all available items grouped by item type.
	*
	* Each item has the following properties:
	* - key				- default key
	* - description		- description of the item
	* - value_type		- Most likely (expected for success) value return type, or null if cannot say with certainty.
	*
	* @return array
	*/
	public static function getItems(): array {
		static $item_definitions;

		if ($item_definitions === null) {
			$item_definitions = static::getDefinitions();
		}

		return $item_definitions;
	}

	/**
	* Generate an array used for item type lookups in the form: key_name => value_type.
	* Value type set to null if key return type varies based on parameters.
	*
	* @return array
	 */
	public static function getTypeSuggestionsByKey(): array {
		$type_suggestions = [];

		foreach (static::getItems() as $definitions) {
			foreach ($definitions as $definition) {
				$key = $definition['key'];
				$value_type = $definition['value_type'];
				$param_start_pos = strpos($key, '[');

				if ($param_start_pos !== false) {
					$key = substr($key, 0, $param_start_pos);
				}

				if (!array_key_exists($key, $type_suggestions)) {
					$type_suggestions[$key] = $value_type;
				}
				elseif ($type_suggestions[$key] != $value_type) {
					// In case of Key name repeats with different types (f.e. zabbix[..]), reset to 'unknown'.
					$type_suggestions[$key] = null;
				}
			}
		}

		return $type_suggestions;
	}
}
