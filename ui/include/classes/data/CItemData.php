<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * Class containing information about Items.
 */
final class CItemData {

	private const KEYS_BY_TYPE = [
		ITEM_TYPE_ZABBIX => [
			'agent.hostmetadata',
			'agent.hostname',
			'agent.ping',
			'agent.variant',
			'agent.version',
			'kernel.maxfiles',
			'kernel.maxproc',
			'kernel.openfiles',
			'modbus.get[endpoint,<slaveid>,<function>,<address>,<count>,<type>,<endianness>,<offset>]',
			'net.dns.record[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
			'net.dns[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
			'net.if.collisions[if]',
			'net.if.discovery',
			'net.if.in[if,<mode>]',
			'net.if.list',
			'net.if.out[if,<mode>]',
			'net.if.total[if,<mode>]',
			'net.tcp.listen[port]',
			'net.tcp.port[<ip>,port]',
			'net.tcp.service.perf[service,<ip>,<port>]',
			'net.tcp.service[service,<ip>,<port>]',
			'net.tcp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]',
			'net.udp.listen[port]',
			'net.udp.service.perf[service,<ip>,<port>]',
			'net.udp.service[service,<ip>,<port>]',
			'net.udp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]',
			'perf_counter[counter,<interval>]',
			'perf_counter_en[counter,<interval>]',
			'perf_instance.discovery[object]',
			'perf_instance_en.discovery[object]',
			'proc.cpu.util[<name>,<user>,<type>,<cmdline>,<mode>,<zone>]',
			'proc.get[<name>,<user>,<cmdline>,<mode>]',
			'proc.mem[<name>,<user>,<mode>,<cmdline>,<memtype>]',
			'proc.num[<name>,<user>,<state>,<cmdline>,<zone>]',
			'proc_info[process,<attribute>,<type>]',
			'registry.data[key,<value name>]',
			'registry.get[key,<mode>,<name regexp>]',
			'sensor[device,sensor,<mode>]',
			'service.info[service,<param>]',
			'services[<type>,<state>,<exclude>]',
			'system.boottime',
			'system.cpu.discovery',
			'system.cpu.intr',
			'system.cpu.load[<cpu>,<mode>]',
			'system.cpu.num[<type>]',
			'system.cpu.switches',
			'system.cpu.util[<cpu>,<type>,<mode>,<logical_or_physical>]',
			'system.hostname[<type>,<transform>]',
			'system.hw.chassis[<info>]',
			'system.hw.cpu[<cpu>,<info>]',
			'system.hw.devices[<type>]',
			'system.hw.macaddr[<interface>,<format>]',
			'system.localtime[<type>]',
			'system.run[command,<mode>]',
			'system.stat[resource,<type>]',
			'system.sw.arch',
			'system.sw.os[<info>]',
			'system.sw.packages[<package>,<manager>,<format>]',
			'system.swap.in[<device>,<type>]',
			'system.swap.out[<device>,<type>]',
			'system.swap.size[<device>,<type>]',
			'system.uname',
			'system.uptime',
			'system.users.num',
			'vfs.dev.discovery',
			'vfs.dev.read[<device>,<type>,<mode>]',
			'vfs.dev.write[<device>,<type>,<mode>]',
			'vfs.dir.count[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]',
			'vfs.dir.get[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]',
			'vfs.dir.size[dir,<regex_incl>,<regex_excl>,<mode>,<max_depth>,<regex_excl_dir>]',
			'vfs.file.cksum[file,<mode>]',
			'vfs.file.contents[file,<encoding>]',
			'vfs.file.exists[file,<types_incl>,<types_excl>]',
			'vfs.file.get[file]',
			'vfs.file.md5sum[file]',
			'vfs.file.owner[file,<ownertype>,<resulttype>]',
			'vfs.file.permissions[file]',
			'vfs.file.regexp[file,regexp,<encoding>,<start line>,<end line>,<output>]',
			'vfs.file.regmatch[file,regexp,<encoding>,<start line>,<end line>]',
			'vfs.file.size[file,<mode>]',
			'vfs.file.time[file,<mode>]',
			'vfs.fs.discovery',
			'vfs.fs.get',
			'vfs.fs.inode[fs,<mode>]',
			'vfs.fs.size[fs,<mode>]',
			'vm.memory.size[<mode>]',
			'vm.vmemory.size[<type>]',
			'web.page.get[host,<path>,<port>]',
			'web.page.perf[host,<path>,<port>]',
			'web.page.regexp[host,<path>,<port>,regexp,<length>,<output>]',
			'wmi.get[<namespace>,<query>]',
			'wmi.getall[<namespace>,<query>]',
			'zabbix.stats[<ip>,<port>,queue,<from>,<to>]',
			'zabbix.stats[<ip>,<port>]'
		],
		ITEM_TYPE_ZABBIX_ACTIVE => [
			'agent.hostmetadata',
			'agent.hostname',
			'agent.ping',
			'agent.variant',
			'agent.version',
			'eventlog[name,<regexp>,<severity>,<source>,<eventid>,<maxlines>,<mode>]',
			'kernel.maxfiles',
			'kernel.maxproc',
			'kernel.openfiles',
			'log.count[file,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>,<persistent_dir>]',
			'log[file,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>,<persistent_dir>]',
			'logrt.count[file_regexp,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>,<persistent_dir>]',
			'logrt[file_regexp,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>,<persistent_dir>]',
			'modbus.get[endpoint,<slaveid>,<function>,<address>,<count>,<type>,<endianness>,<offset>]',
			'mqtt.get[<broker_url>,topic]',
			'net.dns.record[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
			'net.dns[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
			'net.if.collisions[if]',
			'net.if.discovery',
			'net.if.in[if,<mode>]',
			'net.if.list',
			'net.if.out[if,<mode>]',
			'net.if.total[if,<mode>]',
			'net.tcp.listen[port]',
			'net.tcp.port[<ip>,port]',
			'net.tcp.service.perf[service,<ip>,<port>]',
			'net.tcp.service[service,<ip>,<port>]',
			'net.tcp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]',
			'net.udp.listen[port]',
			'net.udp.service.perf[service,<ip>,<port>]',
			'net.udp.service[service,<ip>,<port>]',
			'net.udp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]',
			'perf_counter[counter,<interval>]',
			'perf_counter_en[counter,<interval>]',
			'perf_instance.discovery[object]',
			'perf_instance_en.discovery[object]',
			'proc.cpu.util[<name>,<user>,<type>,<cmdline>,<mode>,<zone>]',
			'proc.get[<name>,<user>,<cmdline>,<mode>]',
			'proc.mem[<name>,<user>,<mode>,<cmdline>,<memtype>]',
			'proc.num[<name>,<user>,<state>,<cmdline>,<zone>]',
			'proc_info[process,<attribute>,<type>]',
			'registry.data[key,<value name>]',
			'registry.get[key,<mode>,<name regexp>]',
			'sensor[device,sensor,<mode>]',
			'service.info[service,<param>]',
			'services[<type>,<state>,<exclude>]',
			'system.boottime',
			'system.cpu.discovery',
			'system.cpu.intr',
			'system.cpu.load[<cpu>,<mode>]',
			'system.cpu.num[<type>]',
			'system.cpu.switches',
			'system.cpu.util[<cpu>,<type>,<mode>,<logical_or_physical>]',
			'system.hostname[<type>,<transform>]',
			'system.hw.chassis[<info>]',
			'system.hw.cpu[<cpu>,<info>]',
			'system.hw.devices[<type>]',
			'system.hw.macaddr[<interface>,<format>]',
			'system.localtime[<type>]',
			'system.run[command,<mode>]',
			'system.stat[resource,<type>]',
			'system.sw.arch',
			'system.sw.os[<info>]',
			'system.sw.packages[<package>,<manager>,<format>]',
			'system.swap.in[<device>,<type>]',
			'system.swap.out[<device>,<type>]',
			'system.swap.size[<device>,<type>]',
			'system.uname',
			'system.uptime',
			'system.users.num',
			'vfs.dev.discovery',
			'vfs.dev.read[<device>,<type>,<mode>]',
			'vfs.dev.write[<device>,<type>,<mode>]',
			'vfs.dir.count[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]',
			'vfs.dir.get[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]',
			'vfs.dir.size[dir,<regex_incl>,<regex_excl>,<mode>,<max_depth>,<regex_excl_dir>]',
			'vfs.file.cksum[file,<mode>]',
			'vfs.file.contents[file,<encoding>]',
			'vfs.file.exists[file,<types_incl>,<types_excl>]',
			'vfs.file.get[file]',
			'vfs.file.md5sum[file]',
			'vfs.file.owner[file,<ownertype>,<resulttype>]',
			'vfs.file.permissions[file]',
			'vfs.file.regexp[file,regexp,<encoding>,<start line>,<end line>,<output>]',
			'vfs.file.regmatch[file,regexp,<encoding>,<start line>,<end line>]',
			'vfs.file.size[file,<mode>]',
			'vfs.file.time[file,<mode>]',
			'vfs.fs.discovery',
			'vfs.fs.get',
			'vfs.fs.inode[fs,<mode>]',
			'vfs.fs.size[fs,<mode>]',
			'vm.memory.size[<mode>]',
			'vm.vmemory.size[<type>]',
			'web.page.get[host,<path>,<port>]',
			'web.page.perf[host,<path>,<port>]',
			'web.page.regexp[host,<path>,<port>,regexp,<length>,<output>]',
			'wmi.get[<namespace>,<query>]',
			'zabbix.stats[<ip>,<port>,queue,<from>,<to>]',
			'zabbix.stats[<ip>,<port>]'
		],
		ITEM_TYPE_SIMPLE => [
			'icmpping[<target>,<packets>,<interval>,<size>,<timeout>]',
			'icmppingloss[<target>,<packets>,<interval>,<size>,<timeout>]',
			'icmppingsec[<target>,<packets>,<interval>,<size>,<timeout>,<mode>]',
			'net.tcp.service.perf[service,<ip>,<port>]',
			'net.tcp.service[service,<ip>,<port>]',
			'net.udp.service.perf[service,<ip>,<port>]',
			'net.udp.service[service,<ip>,<port>]',
			'vmware.alarms.get[<url>]',
			'vmware.cl.perfcounter[<url>,<id>,<path>,<instance>]',
			'vmware.cluster.alarms.get[<url>,<id>]',
			'vmware.cluster.discovery[<url>]',
			'vmware.cluster.property[<url>,<id>,<prop>]',
			'vmware.cluster.status[<url>,<name>]',
			'vmware.cluster.tags.get[<url>,<id>]',
			'vmware.datastore.alarms.get[<url>,<uuid>]',
			'vmware.datastore.discovery[<url>]',
			'vmware.datastore.hv.list[<url>,<datastore>]',
			'vmware.datastore.perfcounter[<url>,<uuid>,<path>,<instance>]',
			'vmware.datastore.property[<url>,<uuid>,<prop>]',
			'vmware.datastore.read[<url>,<datastore>,<mode>]',
			'vmware.datastore.size[<url>,<datastore>,<mode>]',
			'vmware.datastore.tags.get[<url>,<uuid>]',
			'vmware.datastore.write[<url>,<datastore>,<mode>]',
			'vmware.dc.alarms.get[<url>,<id>]',
			'vmware.dc.discovery[<url>]',
			'vmware.dc.tags.get[<url>,<id>]',
			'vmware.dvswitch.discovery[<url>]',
			'vmware.dvswitch.fetchports.get[<url>,<filter>,<mode>]',
			'vmware.eventlog[<url>,<mode>]',
			'vmware.fullname[<url>]',
			'vmware.hv.alarms.get[<url>,<uuid>]',
			'vmware.hv.cluster.name[<url>,<uuid>]',
			'vmware.hv.connectionstate[<url>,<uuid>]',
			'vmware.hv.cpu.usage.perf[<url>,<uuid>]',
			'vmware.hv.cpu.usage[<url>,<uuid>]',
			'vmware.hv.cpu.utilization[<url>,<uuid>]',
			'vmware.hv.datacenter.name[<url>,<uuid>]',
			'vmware.hv.datastore.discovery[<url>,<uuid>]',
			'vmware.hv.datastore.list[<url>,<uuid>]',
			'vmware.hv.datastore.multipath[<url>,<uuid>,<datastore>,<partitionid>]',
			'vmware.hv.datastore.read[<url>,<uuid>,<datastore>,<mode>]',
			'vmware.hv.datastore.size[<url>,<uuid>,<datastore>,<mode>]',
			'vmware.hv.datastore.write[<url>,<uuid>,<datastore>,<mode>]',
			'vmware.hv.discovery[<url>]',
			'vmware.hv.diskinfo.get[<url>,<uuid>]',
			'vmware.hv.fullname[<url>,<uuid>]',
			'vmware.hv.hw.cpu.freq[<url>,<uuid>]',
			'vmware.hv.hw.cpu.model[<url>,<uuid>]',
			'vmware.hv.hw.cpu.num[<url>,<uuid>]',
			'vmware.hv.hw.cpu.threads[<url>,<uuid>]',
			'vmware.hv.hw.memory[<url>,<uuid>]',
			'vmware.hv.hw.model[<url>,<uuid>]',
			'vmware.hv.hw.sensors.get[<url>,<uuid>]',
			'vmware.hv.hw.serialnumber[<url>,<uuid>]',
			'vmware.hv.hw.uuid[<url>,<uuid>]',
			'vmware.hv.hw.vendor[<url>,<uuid>]',
			'vmware.hv.maintenance[<url>,<uuid>]',
			'vmware.hv.memory.size.ballooned[<url>,<uuid>]',
			'vmware.hv.memory.used[<url>,<uuid>]',
			'vmware.hv.net.if.discovery[<url>,<uuid>]',
			'vmware.hv.network.in[<url>,<uuid>,<mode>]',
			'vmware.hv.network.linkspeed[<url>,<uuid>,<ifname>]',
			'vmware.hv.network.out[<url>,<uuid>,<mode>]',
			'vmware.hv.perfcounter[<url>,<uuid>,<path>,<instance>]',
			'vmware.hv.power[<url>,<uuid>,<max>]',
			'vmware.hv.property[<url>,<uuid>,<prop>]',
			'vmware.hv.sensor.health.state[<url>,<uuid>]',
			'vmware.hv.sensors.get[<url>,<uuid>]',
			'vmware.hv.status[<url>,<uuid>]',
			'vmware.hv.tags.get[<url>,<uuid>]',
			'vmware.hv.uptime[<url>,<uuid>]',
			'vmware.hv.version[<url>,<uuid>]',
			'vmware.hv.vm.num[<url>,<uuid>]',
			'vmware.rp.cpu.usage[<url>,<rpid>]',
			'vmware.rp.memory[<url>,<rpid>,<mode>]',
			'vmware.version[<url>]',
			'vmware.vm.alarms.get[<url>,<uuid>]',
			'vmware.vm.attribute[<url>,<uuid>,<name>]',
			'vmware.vm.cluster.name[<url>,<uuid>]',
			'vmware.vm.consolidationneeded[<url>,<uuid>]',
			'vmware.vm.cpu.latency[<url>,<uuid>]',
			'vmware.vm.cpu.num[<url>,<uuid>]',
			'vmware.vm.cpu.readiness[<url>,<uuid>,<instance>]',
			'vmware.vm.cpu.ready[<url>,<uuid>]',
			'vmware.vm.cpu.swapwait[<url>,<uuid>,<instance>]',
			'vmware.vm.cpu.usage.perf[<url>,<uuid>]',
			'vmware.vm.cpu.usage[<url>,<uuid>]',
			'vmware.vm.datacenter.name[<url>,<uuid>]',
			'vmware.vm.discovery[<url>]',
			'vmware.vm.guest.memory.size.swapped[<url>,<uuid>]',
			'vmware.vm.guest.osuptime[<url>,<uuid>]',
			'vmware.vm.hv.name[<url>,<uuid>]',
			'vmware.vm.memory.size.ballooned[<url>,<uuid>]',
			'vmware.vm.memory.size.compressed[<url>,<uuid>]',
			'vmware.vm.memory.size.consumed[<url>,<uuid>]',
			'vmware.vm.memory.size.private[<url>,<uuid>]',
			'vmware.vm.memory.size.shared[<url>,<uuid>]',
			'vmware.vm.memory.size.swapped[<url>,<uuid>]',
			'vmware.vm.memory.size.usage.guest[<url>,<uuid>]',
			'vmware.vm.memory.size.usage.host[<url>,<uuid>]',
			'vmware.vm.memory.size[<url>,<uuid>]',
			'vmware.vm.memory.usage[<url>,<uuid>]',
			'vmware.vm.net.if.discovery[<url>,<uuid>]',
			'vmware.vm.net.if.in[<url>,<uuid>,<instance>,<mode>]',
			'vmware.vm.net.if.out[<url>,<uuid>,<instance>,<mode>]',
			'vmware.vm.net.if.usage[<url>,<uuid>,<instance>]',
			'vmware.vm.perfcounter[<url>,<uuid>,<path>,<instance>]',
			'vmware.vm.powerstate[<url>,<uuid>]',
			'vmware.vm.property[<url>,<uuid>,<prop>]',
			'vmware.vm.snapshot.get[<url>,<uuid>]',
			'vmware.vm.state[<url>,<uuid>]',
			'vmware.vm.storage.committed[<url>,<uuid>]',
			'vmware.vm.storage.readoio[<url>,<uuid>,<instance>]',
			'vmware.vm.storage.totalreadlatency[<url>,<uuid>,<instance>]',
			'vmware.vm.storage.totalwritelatency[<url>,<uuid>,<instance>]',
			'vmware.vm.storage.uncommitted[<url>,<uuid>]',
			'vmware.vm.storage.unshared[<url>,<uuid>]',
			'vmware.vm.storage.writeoio[<url>,<uuid>,<instance>]',
			'vmware.vm.tags.get[<url>,<uuid>]',
			'vmware.vm.tools[<url>,<uuid>,<mode>]',
			'vmware.vm.uptime[<url>,<uuid>]',
			'vmware.vm.vfs.dev.discovery[<url>,<uuid>]',
			'vmware.vm.vfs.dev.read[<url>,<uuid>,<instance>,<mode>]',
			'vmware.vm.vfs.dev.write[<url>,<uuid>,<instance>,<mode>]',
			'vmware.vm.vfs.fs.discovery[<url>,<uuid>]',
			'vmware.vm.vfs.fs.size[<url>,<uuid>,<fsname>,<mode>]'
		],
		ITEM_TYPE_SNMPTRAP => [
			'snmptrap.fallback',
			'snmptrap[<regex>]'
		],
		ITEM_TYPE_INTERNAL => [
			'zabbix[boottime]',
			'zabbix[host,,items]',
			'zabbix[host,,items_unsupported]',
			'zabbix[host,,maintenance]',
			'zabbix[host,<type>,available]',
			'zabbix[host,discovery,interfaces]',
			'zabbix[hosts]',
			'zabbix[items]',
			'zabbix[items_unsupported]',
			'zabbix[java,,<param>]',
			'zabbix[lld_queue]',
			'zabbix[preprocessing_queue]',
			'zabbix[process,<type>,<mode>,<state>]',
			'zabbix[proxy,<name>,<param>]',
			'zabbix[proxy_history]',
			'zabbix[queue,<from>,<to>]',
			'zabbix[rcache,<cache>,<mode>]',
			'zabbix[requiredperformance]',
			'zabbix[stats,<ip>,<port>,queue,<from>,<to>]',
			'zabbix[stats,<ip>,<port>]',
			'zabbix[tcache, cache, <parameter>]',
			'zabbix[triggers]',
			'zabbix[uptime]',
			'zabbix[vcache,buffer,<mode>]',
			'zabbix[vcache,cache,<parameter>]',
			'zabbix[version]',
			'zabbix[vmware,buffer,<mode>]',
			'zabbix[wcache,<cache>,<mode>]'
		],
		ITEM_TYPE_DB_MONITOR => [
			'db.odbc.discovery[<unique short description>,<dsn>,<connection string>]',
			'db.odbc.get[<unique short description>,<dsn>,<connection string>]',
			'db.odbc.select[<unique short description>,<dsn>,<connection string>]'
		],
		ITEM_TYPE_JMX => [
			'jmx.discovery[<discovery mode>,<object name>,<unique short description>]',
			'jmx.get[<discovery mode>,<object name>,<unique short description>]',
			'jmx[object_name,attribute_name,<unique short description>]'
		],
		ITEM_TYPE_IPMI => [
			'ipmi.get'
		]
	];

	/**
	 * Generates an array used to generate item type lookups in the form: item_type => [key_names].
	 *
	 * @return array
	 */
	public static function getKeysByItemType(): array {
		$keys_by_type = self::KEYS_BY_TYPE;
		$keys_by_type_shortened = [];

		foreach ($keys_by_type as $item_type => $available_keys) {
			$available_keys_shortened = [];

			foreach ($available_keys as $key) {
				$param_start_pos = strpos($key, '[');

				if ($param_start_pos !== false) {
					$key = substr($key, 0, $param_start_pos);
				}

				if (!array_key_exists($key, $available_keys_shortened)) {
					$available_keys_shortened[] = $key;
				}
			}

			$keys_by_type_shortened[$item_type] = $available_keys_shortened;
		}

		return $keys_by_type_shortened;
	}

	/**
	 * Returns items available for the given item type as an array of key => details.
	 *
	 * @param int $type  ITEM_TYPE_ZABBIX, ITEM_TYPE_INTERNAL, etc.
	 *
	 * @return array
	 */
	public static function getByType(int $type): array {
		return array_intersect_key(self::get(), array_flip(self::KEYS_BY_TYPE[$type]));
	}

	/**
	 * Generates an array used to generate item type of information lookups in the form: key_name => value_type.
	 * Value type set to null if key return type varies based on parameters.
	 *
	 * @return array
	 */
	public static function getValueTypeByKey(): array {
		$type_suggestions = [];
		$keys = self::get();

		foreach ($keys as $key => $details) {
			$value_type = $details['value_type'];
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

		return $type_suggestions;
	}

	/**
	 * Returns sets of elements (DOM IDs, default field values, dependent option values) to set visible,
	 * disabled, or set value of, when a 'parent' field value is changed.
	 *
	 * @param bool $data['is_discovery_rule']  Determines default value for ITEM_TYPE_DB_MONITOR Key field.
	 *
	 * @return array
	 */
	public static function fieldSwitchingConfiguration(array $data): array {
		return [
			// Ids to toggle when the field 'type' is changed.
			'for_type' => [
				ITEM_TYPE_CALCULATED => [
					'js-item-formula-label',
					'js-item-formula-field',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_DB_MONITOR => [
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'js-item-username-label',
					'js-item-username-field',
					'username',
					'js-item-password-label',
					'js-item-password-field',
					'password',
					'js-item-sql-query-label',
					'js-item-sql-query-field',
					['id' => 'key', 'defaultValue' => $data['is_discovery_rule']
						? ZBX_DEFAULT_KEY_DB_MONITOR_DISCOVERY
						: ZBX_DEFAULT_KEY_DB_MONITOR
					],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_DEPENDENT => [
					'js-item-master-item-label',
					'js-item-master-item-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_EXTERNAL => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_HTTPAGENT => [
					'js-item-url-label',
					'js-item-url-field',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'js-item-query-fields-label',
					'js-item-query-fields-field',
					'js-item-request-method-label',
					'js-item-request-method-field',
					'request_method',
					'js-item-timeout-label',
					'js-item-timeout-field',
					'js-item-post-type-label',
					'js-item-post-type-field',
					'js-item-posts-label',
					'js-item-posts-field',
					'js-item-headers-label',
					'js-item-headers-field',
					'js-item-status-codes-label',
					'js-item-status-codes-field',
					'js-item-follow-redirects-label',
					'js-item-follow-redirects-field',
					'js-item-http-proxy-label',
					'js-item-http-proxy-field',
					'js-item-http-authtype-label',
					'js-item-http-authtype-field',
					'http_authtype',
					'js-item-retrieve-mode-label',
					'js-item-retrieve-mode-field',
					'js-item-output-format-label',
					'js-item-output-format-field',
					'js-item-verify-peer-label',
					'js-item-verify-peer-field',
					'js-item-verify-host-label',
					'js-item-verify-host-field',
					'js-item-ssl-cert-file-label',
					'js-item-ssl-cert-file-field',
					'js-item-ssl-key-file-label',
					'js-item-ssl-key-file-field',
					'js-item-ssl-key-password-label',
					'js-item-ssl-key-password-field',
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-allow-traps-label',
					'js-item-allow-traps-field',
					'allow_traps',
					'trapper_hosts',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_INTERNAL => [
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_IPMI => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-impi-sensor-label',
					'js-item-impi-sensor-field',
					'ipmi_sensor',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_JMX => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-jmx-endpoint-label',
					'js-item-jmx-endpoint-field',
					'js-item-username-label',
					'js-item-username-field',
					'username',
					'jmx_endpoint',
					'js-item-password-label',
					'js-item-password-field',
					'password',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_SCRIPT => [
					'js-item-parameters-label',
					'js-item-parameters-field',
					'js-item-script-label',
					'js-item-script-field',
					'js-item-timeout-label',
					'js-item-timeout-field',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_SIMPLE => [
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-username-label',
					'js-item-username-field',
					'username',
					'js-item-password-label',
					'js-item-password-field',
					'password',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_SNMP => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-snmp-oid-label',
					'js-item-snmp-oid-field',
					'snmp_oid',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_SNMPTRAP => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_SSH => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-authtype-label',
					'js-item-authtype-field',
					'authtype',
					'js-item-username-label',
					'js-item-username-field',
					'username',
					'js-item-password-label',
					'js-item-password-field',
					'password',
					'js-item-executed-script-label',
					'js-item-executed-script-field',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'params_script',
					['id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_SSH],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_TELNET => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-username-label',
					'js-item-username-field',
					'username',
					'js-item-password-label',
					'js-item-password-field',
					'password',
					'js-item-executed-script-label',
					'js-item-executed-script-field',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'params_script',
					['id' => 'key', 'defaultValue' => ZBX_DEFAULT_KEY_TELNET],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_TRAPPER => [
					'js-item-trapper-hosts-label',
					'js-item-trapper-hosts-field',
					'trapper_hosts',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_ZABBIX => [
					'js-item-interface-label',
					'js-item-interface-field',
					'interfaceid',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_ZABBIX_ACTIVE => [
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				]
			],
			// Ids to toggle when the field 'authtype' is changed.
			'for_authtype' => [
				ITEM_AUTHTYPE_PUBLICKEY => [
					'js-item-private-key-label',
					'js-item-private-key-field',
					'privatekey',
					'js-item-public-key-label',
					'js-item-public-key-field',
					'publickey'
				]
			],
			'for_http_auth_type' => [
				HTTPTEST_AUTH_BASIC => [
					'js-item-http-username-label',
					'js-item-http-username-field',
					'js-item-http-password-label',
					'js-item-http-password-field'
				],
				HTTPTEST_AUTH_NTLM => [
					'js-item-http-username-label',
					'js-item-http-username-field',
					'js-item-http-password-label',
					'js-item-http-password-field'
				],
				HTTPTEST_AUTH_KERBEROS => [
					'js-item-http-username-label',
					'js-item-http-username-field',
					'js-item-http-password-label',
					'js-item-http-password-field'
				],
				HTTPTEST_AUTH_DIGEST => [
					'js-item-http-username-label',
					'js-item-http-username-field',
					'js-item-http-password-label',
					'js-item-http-password-field'
				]
			],
			'for_traps' => [
				HTTPCHECK_ALLOW_TRAPS_ON => [
					'js-item-trapper-hosts-label',
					'js-item-trapper-hosts-field'
				]
			],
			'for_value_type' => [
				ITEM_VALUE_TYPE_FLOAT => [
					'js-item-inventory-link-label',
					'js-item-inventory-link-field',
					'inventory_link',
					'js-item-trends-label',
					'js-item-trends-field',
					'js-item-units-label',
					'js-item-units-field',
					'units',
					'js-item-value-map-label',
					'js-item-value-map-field',
					'valuemap_name',
					'valuemapid'
				],
				ITEM_VALUE_TYPE_LOG => [
					'js-item-log-time-format-label',
					'js-item-log-time-format-field',
					'logtimefmt'
				],
				ITEM_VALUE_TYPE_STR => [
					'js-item-inventory-link-label',
					'js-item-inventory-link-field',
					'inventory_link',
					'js-item-value-map-label',
					'js-item-value-map-field',
					'valuemap_name',
					'valuemapid'
				],
				ITEM_VALUE_TYPE_TEXT => [
					'js-item-inventory-link-label',
					'js-item-inventory-link-field',
					'inventory_link'
				],
				ITEM_VALUE_TYPE_UINT64 => [
					'js-item-inventory-link-label',
					'js-item-inventory-link-field',
					'inventory_link',
					'js-item-trends-label',
					'js-item-trends-field',
					'js-item-units-label',
					'js-item-units-field',
					'units',
					'js-item-value-map-label',
					'js-item-value-map-field',
					'valuemap_name',
					'valuemapid'
				]
			]
		];
	}

	private static function get(): array {
		return [
			'agent.hostmetadata' => [
				'description' => _('Agent host metadata. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'agent.hostname' => [
				'description' => _('Agent host name. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'agent.ping' => [
				'description' => _('Agent availability check. Returns nothing - unavailable; 1 - available'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'agent.variant' => [
				'description' => _('Agent variant check. Returns 1 - for Zabbix agent; 2 - for Zabbix agent 2'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'agent.version' => [
				'description' => _('Version of Zabbix agent. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'db.odbc.discovery[<unique short description>,<dsn>,<connection string>]' => [
				'description' => _('Transform SQL query result into a JSON array for low-level discovery.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'db.odbc.get[<unique short description>,<dsn>,<connection string>]' => [
				'description' => _('Transform SQL query result into a JSON array.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'db.odbc.select[<unique short description>,<dsn>,<connection string>]' => [
				'description' => _('Return first column of the first row of the SQL query result.'),
				'value_type' => null
			],
			'eventlog[name,<regexp>,<severity>,<source>,<eventid>,<maxlines>,<mode>]' => [
				'description' => _('Event log monitoring. Returns log'),
				'value_type' => ITEM_VALUE_TYPE_LOG
			],
			'icmpping[<target>,<packets>,<interval>,<size>,<timeout>]' => [
				'description' => _('Checks if host is accessible by ICMP ping. 0 - ICMP ping fails. 1 - ICMP ping successful.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'icmppingloss[<target>,<packets>,<interval>,<size>,<timeout>]' => [
				'description' => _('Returns percentage of lost ICMP ping packets.'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'icmppingsec[<target>,<packets>,<interval>,<size>,<timeout>,<mode>]' => [
				'description' => _('Returns ICMP ping response time in seconds. Example: 0.02'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'ipmi.get' => [
				'description' => _('IPMI sensor IDs and other sensor-related parameters. Returns JSON.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'jmx.discovery[<discovery mode>,<object name>,<unique short description>]' => [
				'description' => _('Return a JSON array with LLD macros describing the MBean objects or their attributes. Can be used for LLD.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'jmx.get[<discovery mode>,<object name>,<unique short description>]' => [
				'description' => _('Return a JSON array with MBean objects or their attributes. Compared to jmx.discovery it does not define LLD macros. Can be used for LLD.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'jmx[object_name,attribute_name,<unique short description>]' => [
				'description' => _('Return value of an attribute of MBean object.'),
				'value_type' => null
			],
			'kernel.maxfiles' => [
				'description' => _('Maximum number of opened files supported by OS. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'kernel.maxproc' => [
				'description' => _('Maximum number of processes supported by OS. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'kernel.openfiles' => [
				'description' => _('Number of currently open file descriptors. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'log.count[file,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>,<persistent_dir>]' => [
				'description' => _('Count of matched lines in log file monitoring. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'log[file,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>,<persistent_dir>]' => [
				'description' => _('Log file monitoring. Returns log'),
				'value_type' => ITEM_VALUE_TYPE_LOG
			],
			'logrt.count[file_regexp,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>,<persistent_dir>]' => [
				'description' => _('Count of matched lines in log file monitoring with log rotation support. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'logrt[file_regexp,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>,<persistent_dir>]' => [
				'description' => _('Log file monitoring with log rotation support. Returns log'),
				'value_type' => ITEM_VALUE_TYPE_LOG
			],
			'modbus.get[endpoint,<slaveid>,<function>,<address>,<count>,<type>,<endianness>,<offset>]' => [
				'description' => _('Reads modbus data. Returns various types'),
				'value_type' => null
			],
			'mqtt.get[<broker_url>,topic]' => [
				'description' => _('Value of MQTT topic. Format of returned data depends on the topic content. If wildcards are used, returns topic values in JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'net.dns.record[<ip>,name,<type>,<timeout>,<count>,<protocol>]' => [
				'description' => _('Performs a DNS query. Returns character string with the required type of information'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'net.dns[<ip>,name,<type>,<timeout>,<count>,<protocol>]' => [
				'description' => _('Checks if DNS service is up. Returns 0 - DNS is down (server did not respond or DNS resolution failed); 1 - DNS is up'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.if.collisions[if]' => [
				'description' => _('Number of out-of-window collisions. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.if.discovery' => [
				'description' => _('List of network interfaces. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'net.if.in[if,<mode>]' => [
				'description' => _('Incoming traffic statistics on network interface. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.if.list' => [
				'description' => _('Network interface list (includes interface type, status, IPv4 address, description). Returns text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'net.if.out[if,<mode>]' => [
				'description' => _('Outgoing traffic statistics on network interface. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.if.total[if,<mode>]' => [
				'description' => _('Sum of incoming and outgoing traffic statistics on network interface. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.tcp.listen[port]' => [
				'description' => _('Checks if this TCP port is in LISTEN state. Returns 0 - it is not in LISTEN state; 1 - it is in LISTEN state'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.tcp.port[<ip>,port]' => [
				'description' => _('Checks if it is possible to make TCP connection to specified port. Returns 0 - cannot connect; 1 - can connect'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.tcp.service.perf[service,<ip>,<port>]' => [
				'description' => _('Checks performance of TCP service. Returns 0 - service is down; seconds - the number of seconds spent while connecting to the service'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'net.tcp.service[service,<ip>,<port>]' => [
				'description' => _('Checks if service is running and accepting TCP connections. Returns 0 - service is down; 1 - service is running'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.tcp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]' => [
				'description' => _('Returns number of TCP sockets that match parameters. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.udp.listen[port]' => [
				'description' => _('Checks if this UDP port is in LISTEN state. Returns 0 - it is not in LISTEN state; 1 - it is in LISTEN state'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.udp.service.perf[service,<ip>,<port>]' => [
				'description' => _('Checks performance of UDP service. Returns 0 - service is down; seconds - the number of seconds spent waiting for response from the service'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'net.udp.service[service,<ip>,<port>]' => [
				'description' => _('Checks if service is running and responding to UDP requests. Returns 0 - service is down; 1 - service is running'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'net.udp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]' => [
				'description' => _('Returns number of UDP sockets that match parameters. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'perf_counter[counter,<interval>]' => [
				'description' => _('Value of any Windows performance counter. Returns integer, float, string or text (depending on the request)'),
				'value_type' => null
			],
			'perf_counter_en[counter,<interval>]' => [
				'description' => _('Value of any Windows performance counter in English. Returns integer, float, string or text (depending on the request)'),
				'value_type' => null
			],
			'perf_instance.discovery[object]' => [
				'description' => _('List of object instances of Windows performance counters. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'perf_instance_en.discovery[object]' => [
				'description' => _('List of object instances of Windows performance counters, discovered using object names in English. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'proc.cpu.util[<name>,<user>,<type>,<cmdline>,<mode>,<zone>]' => [
				'description' => _('Process CPU utilization percentage. Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'proc.get[<name>,<user>,<cmdline>,<mode>]' => [
				'description' => _('List of OS processes with attributes. Returns JSON array'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'proc.mem[<name>,<user>,<mode>,<cmdline>,<memtype>]' => [
				'description' => _('Memory used by process in bytes. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'proc.num[<name>,<user>,<state>,<cmdline>,<zone>]' => [
				'description' => _('The number of processes. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'proc_info[process,<attribute>,<type>]' => [
				'description' => _('Various information about specific process(es). Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'registry.data[key,<value name>]' => [
				'description' => _('Value data for value name in Windows Registry key.'),
				'value_type' => null
			],
			'registry.get[key,<mode>,<name regexp>]' => [
				'description' => _('List of Windows Registry values or keys located at given key. Returns JSON.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'sensor[device,sensor,<mode>]' => [
				'description' => _('Hardware sensor reading. Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'service.info[service,<param>]' => [
				'description' => _('Information about a service. Returns integer with param as state, startup; string - with param as displayname, path, user; text - with param as description; Specifically for state: 0 - running, 1 - paused, 2 - start pending, 3 - pause pending, 4 - continue pending, 5 - stop pending, 6 - stopped, 7 - unknown, 255 - no such service; Specifically for startup: 0 - automatic, 1 - automatic delayed, 2 - manual, 3 - disabled, 4 - unknown'),
				'value_type' => null
			],
			'services[<type>,<state>,<exclude>]' => [
				'description' => _('Listing of services. Returns 0 - if empty; text - list of services separated by a newline'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'snmptrap.fallback' => [
				'description' => _('Catches all SNMP traps that were not caught by any of snmptrap[] items.'),
				'value_type' => null
			],
			'snmptrap[<regex>]' => [
				'description' => _('Catches all SNMP traps that match regex. If regexp is unspecified, catches any trap.'),
				'value_type' => null
			],
			'system.boottime' => [
				'description' => _('System boot time. Returns integer (Unix timestamp)'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.cpu.discovery' => [
				'description' => _('List of detected CPUs/CPU cores. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'system.cpu.intr' => [
				'description' => _('Device interrupts. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.cpu.load[<cpu>,<mode>]' => [
				'description' => _('CPU load. Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'system.cpu.num[<type>]' => [
				'description' => _('Number of CPUs. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.cpu.switches' => [
				'description' => _('Count of context switches. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.cpu.util[<cpu>,<type>,<mode>,<logical_or_physical>]' => [
				'description' => _('CPU utilization percentage. Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'system.hostname[<type>,<transform>]' => [
				'description' => _('System host name. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'system.hw.chassis[<info>]' => [
				'description' => _('Chassis information. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'system.hw.cpu[<cpu>,<info>]' => [
				'description' => _('CPU information. Returns string or integer'),
				'value_type' => null
			],
			'system.hw.devices[<type>]' => [
				'description' => _('Listing of PCI or USB devices. Returns text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'system.hw.macaddr[<interface>,<format>]' => [
				'description' => _('Listing of MAC addresses. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'system.localtime[<type>]' => [
				'description' => _('System time. Returns integer with type as UTC; string - with type as local'),
				'value_type' => null
			],
			'system.run[command,<mode>]' => [
				'description' => _('Run specified command on the host. Returns text result of the command; 1 - with mode as nowait (regardless of command result)'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'system.stat[resource,<type>]' => [
				'description' => _('System statistics. Returns integer or float'),
				'value_type' => null
			],
			'system.sw.arch' => [
				'description' => _('Software architecture information. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'system.sw.os[<info>]' => [
				'description' => _('Operating system information. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'system.sw.packages[<package>,<manager>,<format>]' => [
				'description' => _('Listing of installed packages. Returns text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'system.swap.in[<device>,<type>]' => [
				'description' => _('Swap in (from device into memory) statistics. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.swap.out[<device>,<type>]' => [
				'description' => _('Swap out (from memory onto device) statistics. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.swap.size[<device>,<type>]' => [
				'description' => _('Swap space size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null
			],
			'system.uname' => [
				'description' => _('Identification of the system. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'system.uptime' => [
				'description' => _('System uptime in seconds. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'system.users.num' => [
				'description' => _('Number of users logged in. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.dev.discovery' => [
				'description' => _('List of block devices and their type. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vfs.dev.read[<device>,<type>,<mode>]' => [
				'description' => _('Disk read statistics. Returns integer with type in sectors, operations, bytes; float with type in sps, ops, bps'),
				'value_type' => null
			],
			'vfs.dev.write[<device>,<type>,<mode>]' => [
				'description' => _('Disk write statistics. Returns integer with type in sectors, operations, bytes; float with type in sps, ops, bps'),
				'value_type' => null
			],
			'vfs.dir.count[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]' => [
				'description' => _('Count of directory entries, recursively. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.dir.get[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]' => [
				'description' => _('List of directory entries, recursively. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vfs.dir.size[dir,<regex_incl>,<regex_excl>,<mode>,<max_depth>,<regex_excl_dir>]' => [
				'description' => _('Directory size (in bytes). Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.file.cksum[file,<mode>]' => [
				'description' => _('File checksum, calculated by the UNIX cksum algorithm. Returns integer for crc32 (default) and string for md5, sha256'),
				'value_type' => null
			],
			'vfs.file.contents[file,<encoding>]' => [
				'description' => _('Retrieving contents of a file. Returns text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vfs.file.exists[file,<types_incl>,<types_excl>]' => [
				'description' => _('Checks if file exists. Returns 0 - not found; 1 - file of the specified type exists'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.file.get[file]' => [
				'description' => _('Information about a file. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vfs.file.md5sum[file]' => [
				'description' => _('MD5 checksum of file. Returns character string (MD5 hash of the file)'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vfs.file.owner[file,<ownertype>,<resulttype>]' => [
				'description' => _('File owner information. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vfs.file.permissions[file]' => [
				'description' => _('Returns 4-digit string containing octal number with Unix permissions'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vfs.file.regexp[file,regexp,<encoding>,<start line>,<end line>,<output>]' => [
				'description' => _('Find string in a file. Returns the line containing the matched string, or as specified by the optional output parameter'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vfs.file.regmatch[file,regexp,<encoding>,<start line>,<end line>]' => [
				'description' => _('Find string in a file. Returns 0 - match not found; 1 - found'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.file.size[file,<mode>]' => [
				'description' => _('File size in bytes (default) or in newlines. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.file.time[file,<mode>]' => [
				'description' => _('File time information. Returns integer (Unix timestamp)'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vfs.fs.discovery' => [
				'description' => _('List of mounted filesystems and their types. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vfs.fs.get' => [
				'description' => _('List of mounted filesystems, their types, disk space and inode statistics. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vfs.fs.inode[fs,<mode>]' => [
				'description' => _('Number or percentage of inodes. Returns integer for number; float for percentage'),
				'value_type' => null
			],
			'vfs.fs.size[fs,<mode>]' => [
				'description' => _('Disk space in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null
			],
			'vm.memory.size[<mode>]' => [
				'description' => _('Memory size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null
			],
			'vm.vmemory.size[<type>]' => [
				'description' => _('Virtual space size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null
			],
			'vmware.alarms.get[<url>]' => [
				'description' => _('VMware virtual center alarms data, returns JSON, <url> - VMware service URL'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.cl.perfcounter[<url>,<id>,<path>,<instance>]' => [
				'description' => _('VMware cluster performance counter, <url> - VMware service URL, <id> - VMware cluster id, <path> - performance counter path, <instance> - performance counter instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.cluster.alarms.get[<url>,<id>]' => [
				'description' => _('VMware cluster alarms data, returns JSON, <url> - VMware service URL, <id> - VMware cluster id'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.cluster.discovery[<url>]' => [
				'description' => _('Discovery of VMware clusters, <url> - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.cluster.property[<url>,<id>,<prop>]' => [
				'description' => _('VMware cluster property, <url> - VMware service URL, <id> - VMware cluster id, <prop> - property path'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.cluster.status[<url>,<name>]' => [
				'description' => _('VMware cluster status, <url> - VMware service URL, <name> - VMware cluster name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.cluster.tags.get[<url>,<id>]' => [
				'description' => _('VMware cluster tags array, <url> - VMware service URL, <id> - VMware cluster id'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.alarms.get[<url>,<uuid>]' => [
				'description' => _('VMware datastore alarms data, returns JSON, <url> - VMware service URL, <uuid> - VMware datastore name'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.discovery[<url>]' => [
				'description' => _('Discovery of VMware datastores, <url> - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.hv.list[<url>,<datastore>]' => [
				'description' => _('VMware datastore hypervisors list, <url> - VMware service URL, <datastore> - datastore name'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.perfcounter[<url>,<uuid>,<path>,<instance>]' => [
				'description' => _('VMware datastore performance counter, <url> - VMware service URL, <id> - VMware datastore uuid, <path> - performance counter path, <instance> - performance counter instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.datastore.property[<url>,<uuid>,<prop>]' => [
				'description' => _('VMware datastore property, <url> - VMware service URL, <uuid> - datastore name, <prop> - property path'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.read[<url>,<datastore>,<mode>]' => [
				'description' => _('VMware datastore read statistics, <url> - VMware service URL, <datastore> - datastore name, <mode> - latency/maxlatency - average or maximum'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.size[<url>,<datastore>,<mode>]' => [
				'description' => _('VMware datastore capacity statistics in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null
			],
			'vmware.datastore.tags.get[<url>,<uuid>]' => [
				'description' => _('VMware datastore tags array, <url> - VMware service URL, <uuid> - VMware datastore uuid. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.datastore.write[<url>,<datastore>,<mode>]' => [
				'description' => _('VMware datastore write statistics, <url> - VMware service URL, <datastore> - datastore name, <mode> - latency/maxlatency - average or maximum'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.dc.alarms.get[<url>,<id>]' => [
				'description' => _('VMware datacenter alarms data, returns JSON, <url> - VMware service URL, <id> - VMware datacenter id'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.dc.discovery[<url>]' => [
				'description' => _('VMware datacenters and their IDs, <url> - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.dc.tags.get[<url>,<id>]' => [
				'description' => _('VMware datacenter tags array, <url> - VMware service URL, <id> - VMware datacenter id. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.dvswitch.discovery[<url>]' => [
				'description' => _('VMware Distributed Virtual Switch, <url> - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.dvswitch.fetchports.get[<url>,<filter>,<mode>]' => [
				'description' => _('VMware FetchDVPorts wrapper, <url> - VMware service URL, <filter> - vmware data object DistributedVirtualSwitchPortCriteria, <mode> - state(default)/full. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.eventlog[<url>,<mode>]' => [
				'description' => _('VMware event log, <url> - VMware service URL, <mode> - all (default), skip - skip processing of older data'),
				'value_type' => ITEM_VALUE_TYPE_LOG
			],
			'vmware.fullname[<url>]' => [
				'description' => _('VMware service full name, <url> - VMware service URL'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.alarms.get[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor alarms data, returns JSON, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.cluster.name[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor cluster name, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.connectionstate[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor connection state, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.cpu.usage.perf[<url>,<uuid>]' => [
				'description' => _('CPU usage as a percentage during the interval, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.hv.cpu.usage[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor processor usage in Hz, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.cpu.utilization[<url>,<uuid>]' => [
				'description' => _('CPU usage as a percentage during the interval depends on power management or HT, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.hv.datacenter.name[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor datacenter name, <url> - VMware service URL, <uuid> - VMware hypervisor host name. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.datastore.discovery[<url>,<uuid>]' => [
				'description' => _('Discovery of VMware hypervisor datastores, <url> - VMware service URL, <uuid> - VMware hypervisor host name. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.datastore.list[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor datastores list, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.datastore.multipath[<url>,<uuid>,<datastore>,<partitionid>]' => [
				'description' => _('Number of available DS paths, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <datastore> - Datastore name, <partitionid> - internal id of physical device from vmware.hv.datastore.discovery'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.datastore.read[<url>,<uuid>,<datastore>,<mode>]' => [
				'description' => _('VMware hypervisor datastore read statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <datastore> - datastore name, <mode> - latency'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.datastore.size[<url>,<uuid>,<datastore>,<mode>]' => [
				'description' => _('VMware datastore capacity statistics in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null
			],
			'vmware.hv.datastore.write[<url>,<uuid>,<datastore>,<mode>]' => [
				'description' => _('VMware hypervisor datastore write statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <datastore> - datastore name, <mode> - latency'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.discovery[<url>]' => [
				'description' => _('Discovery of VMware hypervisors, <url> - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.diskinfo.get[<url>,<uuid>]' => [
				'description' => _('Info about internal disks of hypervisor required for vmware.datastore.perfcounter, <url> - VMware service URL, <uuid> - VMware hypervisor host name. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.fullname[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor name, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.hw.cpu.freq[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor processor frequency, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.hv.hw.cpu.model[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor processor model, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.hw.cpu.num[<url>,<uuid>]' => [
				'description' => _('Number of processor cores on VMware hypervisor, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.hw.cpu.threads[<url>,<uuid>]' => [
				'description' => _('Number of processor threads on VMware hypervisor, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.hw.memory[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor total memory size, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.hw.model[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor model, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.hw.sensors.get[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor sensors value, <url> - VMware service URL, <uuid> - VMware hypervisor host name. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.hw.serialnumber[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor serialnumber, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.hw.uuid[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor BIOS UUID, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.hw.vendor[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor vendor name, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.maintenance[<url>,<uuid>]' => [
				'description' => _('VVMware hypervisor maintenance status, <url> - VMware service URL, <uuid> - VMware hypervisor host name. Returns 0 - not in maintenance; 1 - in maintenance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.memory.size.ballooned[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor ballooned memory size, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.memory.used[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor used memory size, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.net.if.discovery[<url>,<uuid>]' => [
				'description' => _('Discovery of VMware hypervisor network interfaces, <url> - VMware service URL, <uuid> - VMware hypervisor. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.network.in[<url>,<uuid>,<mode>]' => [
				'description' => _('VMware hypervisor network input statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <mode> - bps'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.network.linkspeed[<url>,<uuid>,<ifname>]' => [
				'description' => _('VMware hypervisor network interface speed, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <ifname> - interface name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.network.out[<url>,<uuid>,<mode>]' => [
				'description' => _('VMware hypervisor network output statistics, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <mode> - bps'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.perfcounter[<url>,<uuid>,<path>,<instance>]' => [
				'description' => _('VMware hypervisor performance counter, <url> - VMware service URL, <uuid> - VMware hypervisor host name, <path> - performance counter path, <instance> - performance counter instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.hv.power[<url>,<uuid>,<max>]' => [
				'description' => _('Power usage , <url> - VMware service URL, <uuid> - VMware hypervisor host name, <max> - Maximum allowed power usage'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.hv.property[<url>,<uuid>,<prop>]' => [
				'description' => _('VMware hypervisor property , <url> - VMware service URL, <uuid> - VMware hypervisor host name, <prop> - property path'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.sensor.health.state[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor health state rollup sensor, <url> - VMware service URL, <uuid> - VMware hypervisor host name. Returns 0 - gray; 1 - green; 2 - yellow; 3 - red'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.sensors.get[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor HW vendor state sensors, <url> - VMware service URL, <uuid> - VMware hypervisor host name. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.status[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor status, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => null
			],
			'vmware.hv.tags.get[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor tags array, <url> - VMware service URL, <uuid> - VMware hypervisor host name. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.hv.uptime[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor uptime, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.hv.version[<url>,<uuid>]' => [
				'description' => _('VMware hypervisor version, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.hv.vm.num[<url>,<uuid>]' => [
				'description' => _('Number of virtual machines on VMware hypervisor, <url> - VMware service URL, <uuid> - VMware hypervisor host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.rp.cpu.usage[<url>,<rpid>]' => [
				'description' => _('CPU usage in hertz during the interval on VMware Resource Pool, <url> - VMware service URL, <rpid> - VMware resource pool id'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.rp.memory[<url>,<rpid>,<mode>]' => [
				'description' => _('Memory metrics of VMware Resource Pool, <url> - VMware service URL, <rpid> - VMware resource pool id, <mode> - consumed(default)/ballooned/overhead memory'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.version[<url>]' => [
				'description' => _('VMware service version, <url> - VMware service URL'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.alarms.get[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine alarms data, returns JSON, <url> - VMware service URL, <uuid> - VMware virtual machine name'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.attribute[<url>,<uuid>,<name>]' => [
				'description' => _('VMware virtual machine custom attribute value, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <name> - custom attribute name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.cluster.name[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine name, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.consolidationneeded[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine disk requires consolidation, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.cpu.latency[<url>,<uuid>]' => [
				'description' => _('Percent of time the virtual machine is unable to run because it is contending for access to the physical CPU(s), <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.cpu.num[<url>,<uuid>]' => [
				'description' => _('Number of processors on VMware virtual machine, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.cpu.readiness[<url>,<uuid>,<instance>]' => [
				'description' => _('Percentage of time that the virtual machine was ready, but could not get scheduled to run on the physical CPU, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - CPU instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.cpu.ready[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine processor ready time ms, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.cpu.swapwait[<url>,<uuid>,<instance>]' => [
				'description' => _('CPU time spent waiting for swap-in, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - CPU instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.cpu.usage.perf[<url>,<uuid>]' => [
				'description' => _('CPU usage as a percentage during the interval, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.cpu.usage[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine processor usage in Hz, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.datacenter.name[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine datacenter name, <url> - VMware service URL, <uuid> - VMware virtual machine host name. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.discovery[<url>]' => [
				'description' => _('Discovery of VMware virtual machines, <url> - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.guest.memory.size.swapped[<url>,<uuid>]' => [
				'description' => _('Amount of guest physical memory that is swapped out to the swap space, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.guest.osuptime[<url>,<uuid>]' => [
				'description' => _('Total time elapsed, in seconds, since last operating system boot-up, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.hv.name[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine hypervisor name, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.memory.size.ballooned[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine ballooned memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.compressed[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine compressed memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.consumed[<url>,<uuid>]' => [
				'description' => _('Amount of host physical memory consumed for backing up guest physical memory pages, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.private[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine private memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.shared[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine shared memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.swapped[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine swapped memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.usage.guest[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine guest memory usage, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size.usage.host[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine host memory usage, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.size[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine total memory size, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.memory.usage[<url>,<uuid>]' => [
				'description' => _('Percentage of host physical memory that has been consumed, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.net.if.discovery[<url>,<uuid>]' => [
				'description' => _('Discovery of VMware virtual machine network interfaces, <url> - VMware service URL, <uuid> - VMware virtual machine host name. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.net.if.in[<url>,<uuid>,<instance>,<mode>]' => [
				'description' => _('VMware virtual machine network interface input statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - network interface instance, <mode> - bps/pps - bytes/packets per second'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.net.if.out[<url>,<uuid>,<instance>,<mode>]' => [
				'description' => _('VMware virtual machine network interface output statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - network interface instance, <mode> - bps/pps - bytes/packets per second'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.net.if.usage[<url>,<uuid>,<instance>]' => [
				'description' => _('Network utilization (combined transmit-rates and receive-rates) during the interval, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - network interface instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.perfcounter[<url>,<uuid>,<path>,<instance>]' => [
				'description' => _('VMware virtual machine performance counter, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <path> - performance counter path, <instance> - performance counter instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.powerstate[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine power state, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.property[<url>,<uuid>,<prop>]' => [
				'description' => _('VMware virtual machine property, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <prop> - property path'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.snapshot.get[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine snapshot state, <url> - VMware service URL, <uuid> - VMware virtual machine host name. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.state[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine state, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.storage.committed[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine committed storage space, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => null
			],
			'vmware.vm.storage.readoio[<url>,<uuid>,<instance>]' => [
				'description' => _('Average number of outstanding read requests to the virtual disk during the collection interval , <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.storage.totalreadlatency[<url>,<uuid>,<instance>]' => [
				'description' => _('The average time a read from the virtual disk takes, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.storage.totalwritelatency[<url>,<uuid>,<instance>]' => [
				'description' => _('The average time a write to the virtual disk takes, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.storage.uncommitted[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine uncommitted storage space, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.storage.unshared[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine unshared storage space, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.storage.writeoio[<url>,<uuid>,<instance>]' => [
				'description' => _('Average number of outstanding write requests to the virtual disk during the collection interval, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.tags.get[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine tags array, <url> - VMware service URL, <uuid> - VMware virtual machine host name. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.tools[<url>,<uuid>,<mode>]' => [
				'description' => _('VMware virtual machine tools state, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <mode> - version or status'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'vmware.vm.uptime[<url>,<uuid>]' => [
				'description' => _('VMware virtual machine uptime, <url> - VMware service URL, <uuid> - VMware virtual machine host name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'vmware.vm.vfs.dev.discovery[<url>,<uuid>]' => [
				'description' => _('Discovery of VMware virtual machine disk devices, <url> - VMware service URL, <uuid> - VMware virtual machine host name. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.vfs.dev.read[<url>,<uuid>,<instance>,<mode>]' => [
				'description' => _('VMware virtual machine disk device read statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance, <mode> - bps/ops - bytes/operations per second'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.vfs.dev.write[<url>,<uuid>,<instance>,<mode>]' => [
				'description' => _('VMware virtual machine disk device write statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <instance> - disk device instance, <mode> - bps/ops - bytes/operations per second'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'vmware.vm.vfs.fs.discovery[<url>,<uuid>]' => [
				'description' => _('Discovery of VMware virtual machine file systems, <url> - VMware service URL, <uuid> - VMware virtual machine host name. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'vmware.vm.vfs.fs.size[<url>,<uuid>,<fsname>,<mode>]' => [
				'description' => _('VMware virtual machine file system statistics, <url> - VMware service URL, <uuid> - VMware virtual machine host name, <fsname> - file system name, <mode> - total/free/used/pfree/pused'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'web.page.get[host,<path>,<port>]' => [
				'description' => _('Get content of web page. Returns web page source as text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'web.page.perf[host,<path>,<port>]' => [
				'description' => _('Loading time of full web page (in seconds). Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'web.page.regexp[host,<path>,<port>,regexp,<length>,<output>]' => [
				'description' => _('Find string on a web page. Returns the matched string, or as specified by the optional output parameter'),
				'value_type' => ITEM_VALUE_TYPE_STR
			],
			'wmi.get[<namespace>,<query>]' => [
				'description' => _('Execute WMI query and return the first selected object. Returns integer, float, string or text (depending on the request)'),
				'value_type' => null
			],
			'wmi.getall[<namespace>,<query>]' => [
				'description' => _('Execute WMI query and return the JSON document with all selected objects'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'zabbix.stats[<ip>,<port>,queue,<from>,<to>]' => [
				'description' => _('Number of items in the queue which are delayed in Zabbix server or proxy by "from" till "to" seconds, inclusive.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix.stats[<ip>,<port>]' => [
				'description' => _('Returns a JSON object containing Zabbix server or proxy internal metrics.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'zabbix[boottime]' => [
				'description' => _('Startup time of Zabbix server, Unix timestamp.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[host,,items]' => [
				'description' => _('Number of enabled items on the host.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[host,,items_unsupported]' => [
				'description' => _('Number of unsupported items on the host.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[host,,maintenance]' => [
				'description' => _('Returns current maintenance status of the host.'),
				'value_type' => null
			],
			'zabbix[host,<type>,available]' => [
				'description' => _('Returns availability of a particular type of checks on the host. Value of this item corresponds to availability icons in the host list. Valid types are: agent, active_agent, snmp, ipmi, jmx.'),
				'value_type' => null
			],
			'zabbix[host,discovery,interfaces]' => [
				'description' => _('Returns a JSON array describing the host network interfaces configured in Zabbix. Can be used for LLD.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'zabbix[hosts]' => [
				'description' => _('Number of monitored hosts'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[items]' => [
				'description' => _('Number of items in Zabbix database.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[items_unsupported]' => [
				'description' => _('Number of unsupported items in Zabbix database.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[java,,<param>]' => [
				'description' => _('Returns information associated with Zabbix Java gateway. Valid params are: ping, version.'),
				'value_type' => null
			],
			'zabbix[lld_queue]' => [
				'description' => _('Count of values enqueued in the low-level discovery processing queue.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[preprocessing_queue]' => [
				'description' => _('Count of values enqueued in the preprocessing queue.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[process,<type>,<mode>,<state>]' => [
				'description' => _('Time a particular Zabbix process or a group of processes (identified by <type> and <mode>) spent in <state> in percentage.'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'zabbix[proxy,<name>,<param>]' => [
				'description' => _('Time of proxy last access. Name - proxy name. Valid params are: lastaccess - Unix timestamp, delay - seconds.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[proxy_history]' => [
				'description' => _('Number of items in proxy history that are not yet sent to the server'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[queue,<from>,<to>]' => [
				'description' => _('Number of items in the queue which are delayed by from to seconds, inclusive.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[rcache,<cache>,<mode>]' => [
				'description' => _('Configuration cache statistics. Cache - buffer (modes: pfree, total, used, free).'),
				'value_type' => null
			],
			'zabbix[requiredperformance]' => [
				'description' => _('Required performance of the Zabbix server, in new values per second expected.'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT
			],
			'zabbix[stats,<ip>,<port>,queue,<from>,<to>]' => [
				'description' => _('Number of items in the queue which are delayed in Zabbix server or proxy by "from" till "to" seconds, inclusive.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[stats,<ip>,<port>]' => [
				'description' => _('Returns a JSON object containing Zabbix server or proxy internal metrics.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT
			],
			'zabbix[tcache, cache, <parameter>]' => [
				'description' => _('Trend function cache statistics. Valid parameters are: all, hits, phits, misses, pmisses, items, pitems and requests.'),
				'value_type' => null
			],
			'zabbix[triggers]' => [
				'description' => _('Number of triggers in Zabbix database.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[uptime]' => [
				'description' => _('Uptime of Zabbix server process in seconds.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			'zabbix[vcache,buffer,<mode>]' => [
				'description' => _('Value cache statistics. Valid modes are: total, free, pfree, used and pused.'),
				'value_type' => null
			],
			'zabbix[vcache,cache,<parameter>]' => [
				'description' => _('Value cache effectiveness. Valid parameters are: requests, hits and misses.'),
				'value_type' => null
			],
			'zabbix[version]' => [
				'description' => _('Version of Zabbix server or proxy'),
				'value_type' => null
			],
			'zabbix[vmware,buffer,<mode>]' => [
				'description' => _('VMware cache statistics. Valid modes are: total, free, pfree, used and pused.'),
				'value_type' => null
			],
			'zabbix[wcache,<cache>,<mode>]' => [
				'description' => _('Statistics and availability of Zabbix write cache. Cache - one of values (modes: all, float, uint, str, log, text, not supported), history (modes: pfree, free, total, used, pused), index (modes: pfree, free, total, used, pused), trend (modes: pfree, free, total, used, pused).'),
				'value_type' => null
			]
		];
	}
}
