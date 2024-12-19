<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
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
			'net.dns.perf[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
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
			'system.sw.os.get',
			'system.sw.packages[<regexp>,<manager>,<format>]',
			'system.sw.packages.get[<regexp>,<manager>]',
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
			'wmi.get[namespace,query]',
			'wmi.getall[namespace,query]',
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
			'eventlog.count[name,<regexp>,<severity>,<source>,<eventid>,<maxproclines>,<mode>]',
			'kernel.maxfiles',
			'kernel.maxproc',
			'kernel.openfiles',
			'log.count[file,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>,<persistent_dir>]',
			'log[file,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>,<persistent_dir>]',
			'logrt.count[file_regexp,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>,<persistent_dir>]',
			'logrt[file_regexp,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>,<persistent_dir>]',
			'modbus.get[endpoint,<slaveid>,<function>,<address>,<count>,<type>,<endianness>,<offset>]',
			'mqtt.get[<broker_url>,topic,<username>,<password>]',
			'net.dns.record[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
			'net.dns.perf[<ip>,name,<type>,<timeout>,<count>,<protocol>]',
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
			'system.sw.os.get',
			'system.sw.packages[<regexp>,<manager>,<format>]',
			'system.sw.packages.get[<regexp>,<manager>]',
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
			'wmi.get[namespace,query]',
			'zabbix.stats[<ip>,<port>,queue,<from>,<to>]',
			'zabbix.stats[<ip>,<port>]'
		],
		ITEM_TYPE_SIMPLE => [
			'icmpping[<target>,<packets>,<interval>,<size>,<timeout>,<options>]',
			'icmppingloss[<target>,<packets>,<interval>,<size>,<timeout>,<options>]',
			'icmppingsec[<target>,<packets>,<interval>,<size>,<timeout>,<mode>,<options>]',
			'net.tcp.service.perf[service,<ip>,<port>]',
			'net.tcp.service[service,<ip>,<port>]',
			'net.udp.service.perf[service,<ip>,<port>]',
			'net.udp.service[service,<ip>,<port>]',
			'vmware.alarms.get[url]',
			'vmware.cl.perfcounter[url,id,path,<instance>]',
			'vmware.cluster.alarms.get[url,id]',
			'vmware.cluster.discovery[url]',
			'vmware.cluster.property[url,id,prop]',
			'vmware.cluster.status[url,name]',
			'vmware.cluster.tags.get[url,id]',
			'vmware.datastore.alarms.get[url,uuid]',
			'vmware.datastore.discovery[url]',
			'vmware.datastore.hv.list[url,datastore]',
			'vmware.datastore.perfcounter[url,uuid,path,<instance>]',
			'vmware.datastore.property[url,uuid,prop]',
			'vmware.cluster.property[url,id,prop]',
			'vmware.datastore.read[url,datastore,<mode>]',
			'vmware.datastore.size[url,datastore,<mode>]',
			'vmware.datastore.tags.get[url,uuid]',
			'vmware.datastore.write[url,datastore,<mode>]',
			'vmware.dc.alarms.get[url,id]',
			'vmware.dc.discovery[url]',
			'vmware.dc.tags.get[url,id]',
			'vmware.dvswitch.discovery[url]',
			'vmware.dvswitch.fetchports.get[url,uuid,<filter>,<mode>]',
			'vmware.eventlog[url,<mode>,<severity>]',
			'vmware.fullname[url]',
			'vmware.hv.alarms.get[url,uuid]',
			'vmware.hv.cluster.name[url,uuid]',
			'vmware.hv.connectionstate[url,uuid]',
			'vmware.hv.cpu.usage.perf[url,uuid]',
			'vmware.hv.cpu.usage[url,uuid]',
			'vmware.hv.cpu.utilization[url,uuid]',
			'vmware.hv.datacenter.name[url,uuid]',
			'vmware.hv.datastore.discovery[url,uuid]',
			'vmware.hv.datastore.list[url,uuid]',
			'vmware.hv.datastore.multipath[url,uuid,<datastore>,<partitionid>]',
			'vmware.hv.datastore.read[url,uuid,datastore,<mode>]',
			'vmware.hv.datastore.size[url,uuid,datastore,<mode>]',
			'vmware.hv.datastore.write[url,uuid,datastore,<mode>]',
			'vmware.hv.discovery[url]',
			'vmware.hv.diskinfo.get[url,uuid]',
			'vmware.hv.fullname[url,uuid]',
			'vmware.hv.hw.cpu.freq[url,uuid]',
			'vmware.hv.hw.cpu.model[url,uuid]',
			'vmware.hv.hw.cpu.num[url,uuid]',
			'vmware.hv.hw.cpu.threads[url,uuid]',
			'vmware.hv.hw.memory[url,uuid]',
			'vmware.hv.hw.model[url,uuid]',
			'vmware.hv.hw.sensors.get[url,uuid]',
			'vmware.hv.hw.serialnumber[url,uuid]',
			'vmware.hv.hw.uuid[url,uuid]',
			'vmware.hv.hw.vendor[url,uuid]',
			'vmware.hv.maintenance[url,uuid]',
			'vmware.hv.memory.size.ballooned[url,uuid]',
			'vmware.hv.memory.used[url,uuid]',
			'vmware.hv.net.if.discovery[url,uuid]',
			'vmware.hv.network.linkspeed[url,uuid,ifname]',
			'vmware.hv.network.in[url,uuid,<mode>]',
			'vmware.hv.network.out[url,uuid,<mode>]',
			'vmware.hv.perfcounter[url,uuid,path,<instance>]',
			'vmware.hv.power[url,uuid,<max>]',
			'vmware.hv.property[url,uuid,prop]',
			'vmware.hv.sensor.health.state[url,uuid]',
			'vmware.hv.tags.get[url,uuid]',
			'vmware.hv.sensors.get[url,uuid]',
			'vmware.hv.status[url,uuid]',
			'vmware.hv.uptime[url,uuid]',
			'vmware.hv.version[url,uuid]',
			'vmware.hv.vm.num[url,uuid]',
			'vmware.rp.cpu.usage[url,rpid]',
			'vmware.rp.memory[url,rpid,<mode>]',
			'vmware.version[url]',
			'vmware.vm.alarms.get[url,uuid]',
			'vmware.vm.attribute[url,uuid,name]',
			'vmware.vm.cluster.name[url,uuid]',
			'vmware.vm.consolidationneeded[url,uuid]',
			'vmware.vm.cpu.latency[url,uuid]',
			'vmware.vm.cpu.num[url,uuid]',
			'vmware.vm.cpu.readiness[url,uuid,<instance>]',
			'vmware.vm.cpu.ready[url,uuid]',
			'vmware.vm.cpu.swapwait[url,uuid,<instance>]',
			'vmware.vm.cpu.usage.perf[url,uuid]',
			'vmware.vm.cpu.usage[url,uuid]',
			'vmware.vm.datacenter.name[url,uuid]',
			'vmware.vm.discovery[url]',
			'vmware.vm.guest.memory.size.swapped[url,uuid]',
			'vmware.vm.guest.osuptime[url,uuid]',
			'vmware.vm.hv.maintenance[url,uuid]',
			'vmware.vm.hv.name[url,uuid]',
			'vmware.vm.memory.size.ballooned[url,uuid]',
			'vmware.vm.memory.size.compressed[url,uuid]',
			'vmware.vm.memory.size.consumed[url,uuid]',
			'vmware.vm.memory.size.private[url,uuid]',
			'vmware.vm.memory.size.shared[url,uuid]',
			'vmware.vm.memory.size.swapped[url,uuid]',
			'vmware.vm.memory.size.usage.guest[url,uuid]',
			'vmware.vm.memory.size.usage.host[url,uuid]',
			'vmware.vm.memory.size[url,uuid]',
			'vmware.vm.memory.usage[url,uuid]',
			'vmware.vm.net.if.discovery[url,uuid]',
			'vmware.vm.net.if.in[url,uuid,instance,<mode>]',
			'vmware.vm.net.if.out[url,uuid,instance,<mode>]',
			'vmware.vm.net.if.usage[url,uuid,<instance>]',
			'vmware.vm.perfcounter[url,uuid,path,<instance>]',
			'vmware.vm.powerstate[url,uuid]',
			'vmware.vm.property[url,uuid,prop]',
			'vmware.vm.snapshot.get[url,uuid]',
			'vmware.vm.state[url,uuid]',
			'vmware.vm.storage.committed[url,uuid]',
			'vmware.vm.storage.readoio[url,uuid,instance]',
			'vmware.vm.storage.totalreadlatency[url,uuid,instance]',
			'vmware.vm.storage.totalwritelatency[url,uuid,instance]',
			'vmware.vm.storage.uncommitted[url,uuid]',
			'vmware.vm.storage.unshared[url,uuid]',
			'vmware.vm.storage.writeoio[url,uuid,instance]',
			'vmware.vm.tags.get[url,uuid]',
			'vmware.vm.tools[url,uuid,mode]',
			'vmware.vm.uptime[url,uuid]',
			'vmware.vm.vfs.dev.discovery[url,uuid]',
			'vmware.vm.vfs.dev.read[url,uuid,instance,<mode>]',
			'vmware.vm.vfs.dev.write[url,uuid,instance,<mode>]',
			'vmware.vm.vfs.fs.discovery[url,uuid]',
			'vmware.vm.vfs.fs.size[url,uuid,fsname,<mode>]'
		],
		ITEM_TYPE_SNMPTRAP => [
			'snmptrap.fallback',
			'snmptrap[<regex>]'
		],
		ITEM_TYPE_INTERNAL => [
			'zabbix[boottime]',
			'zabbix[connector_queue]',
			'zabbix[discovery_queue]',
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
			'zabbix[proxy,discovery]',
			'zabbix[proxy_history]',
			'zabbix[proxy group,<name>,available]',
			'zabbix[proxy group,<name>,pavailable]',
			'zabbix[proxy group,<name>,proxies]',
			'zabbix[proxy group,<name>,state]',
			'zabbix[proxy group,discovery]',
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
			'zabbix[wcache,<cache>,<mode>]',
			'zabbix[proxy_buffer,buffer,<mode>]',
			'zabbix[proxy_buffer,state,current]',
			'zabbix[proxy_buffer,state,changes]',
			'zabbix[vps,written]'
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
		$items = array_intersect_key(self::get(), array_flip(self::KEYS_BY_TYPE[$type]));

		foreach ($items as &$item) {
			$item['documentation_link'] = $item['documentation_link'][$type];
		}
		unset($item);

		return $items;
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
	 * Returns sets of elements (DOM IDs) to set visible, disabled, when a 'parent' field value is changed.
	 *
	 * @return array
	 */
	public static function filterSwitchingConfiguration(): array {
		$all_item_types = -1;

		return [
			'for_type' => [
				$all_item_types => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_CALCULATED => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_DB_MONITOR => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_DEPENDENT => [],
				ITEM_TYPE_EXTERNAL => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_HTTPAGENT => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_INTERNAL => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_IPMI => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_JMX => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_SCRIPT => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_BROWSER => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_SIMPLE => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_SNMP => [
					'js-filter-snmp-oid-label',
					'js-filter-snmp-oid-field',
					'filter_snmp_oid',
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_SNMPTRAP => [],
				ITEM_TYPE_SSH => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_TELNET => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_TRAPPER => [],
				ITEM_TYPE_ZABBIX => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				],
				ITEM_TYPE_ZABBIX_ACTIVE => [
					'js-filter-delay-label',
					'js-filter-delay-field',
					'filter_delay'
				]
			]
		];
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
		if ($data['is_discovery_rule']) {
			$for_authtype = [
				ITEM_AUTHTYPE_PUBLICKEY => [
					'js-item-private-key-label',
					'js-item-private-key-field',
					'privatekey',
					'js-item-public-key-label',
					'js-item-public-key-field',
					'publickey'
				]
			];
		}
		else {
			$for_authtype = [
				ITEM_AUTHTYPE_PASSWORD => [
					'js-item-password-label',
					'js-item-password-field',
					'password'
				],
				ITEM_AUTHTYPE_PUBLICKEY => [
					'js-item-private-key-label',
					'js-item-private-key-field',
					'privatekey',
					'js-item-public-key-label',
					'js-item-public-key-field',
					'publickey',
					'js-item-passphrase-label',
					'js-item-passphrase-field',
					'passphrase'
				]
			];
		}

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
					'js-item-timeout-label',
					'js-item-timeout-field',
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
					'js-item-timeout-label',
					'js-item-timeout-field',
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
					'js-item-timeout-label',
					'js-item-timeout-field',
					'js-item-query-fields-label',
					'js-item-query-fields-field',
					'js-item-request-method-label',
					'js-item-request-method-field',
					'request_method',
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
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'js-item-timeout-label',
					'js-item-timeout-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_BROWSER => [
					'js-item-parameters-label',
					'js-item-parameters-field',
					'js-item-browser-script-label',
					'js-item-browser-script-field',
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'js-item-timeout-label',
					'js-item-timeout-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_SIMPLE => [
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'js-item-timeout-label',
					'js-item-timeout-field',
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
					'js-item-timeout-label',
					'js-item-timeout-field',
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
					'js-item-timeout-label',
					'js-item-timeout-field',
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
					'js-item-timeout-label',
					'js-item-timeout-field',
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
					'js-item-timeout-label',
					'js-item-timeout-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				],
				ITEM_TYPE_ZABBIX_ACTIVE => [
					'js-item-delay-label',
					'js-item-delay-field',
					'delay',
					'js-item-flex-intervals-label',
					'js-item-flex-intervals-field',
					'js-item-timeout-label',
					'js-item-timeout-field',
					['id' => 'key', 'defaultValue' => ''],
					['id' => 'value_type', 'defaultValue' => '']
				]
			],
			// Ids to toggle when the field 'authtype' is changed.
			'for_authtype' => $for_authtype,
			'for_http_auth_type' => [
				ZBX_HTTP_AUTH_BASIC => [
					'js-item-http-username-label',
					'js-item-http-username-field',
					'js-item-http-password-label',
					'js-item-http-password-field'
				],
				ZBX_HTTP_AUTH_NTLM => [
					'js-item-http-username-label',
					'js-item-http-username-field',
					'js-item-http-password-label',
					'js-item-http-password-field'
				],
				ZBX_HTTP_AUTH_KERBEROS => [
					'js-item-http-username-label',
					'js-item-http-username-field',
					'js-item-http-password-label',
					'js-item-http-password-field'
				],
				ZBX_HTTP_AUTH_DIGEST => [
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
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#agent.hostmetadata',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#agent.hostmetadata'
				]
			],
			'agent.hostname' => [
				'description' => _('Agent host name. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#agent.hostname',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#agent.hostname'
				]
			],
			'agent.ping' => [
				'description' => _('Agent availability check. Returns nothing - unavailable; 1 - available'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#agent.ping',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#agent.ping'
				]
			],
			'agent.variant' => [
				'description' => _('Agent variant check. Returns 1 - for Zabbix agent; 2 - for Zabbix agent 2'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#agent.variant',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#agent.variant'
				]
			],
			'agent.version' => [
				'description' => _('Version of Zabbix agent. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#agent.version',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#agent.version'
				]
			],
			'db.odbc.discovery[<unique short description>,<dsn>,<connection string>]' => [
				'description' => _('Transform SQL query result into a JSON array for low-level discovery.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_DB_MONITOR => 'config/items/itemtypes/odbc_checks#db.odbc.discovery'
				]
			],
			'db.odbc.get[<unique short description>,<dsn>,<connection string>]' => [
				'description' => _('Transform SQL query result into a JSON array.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_DB_MONITOR => 'config/items/itemtypes/odbc_checks#db.odbc.get'
				]
			],
			'db.odbc.select[<unique short description>,<dsn>,<connection string>]' => [
				'description' => _('Return first column of the first row of the SQL query result.'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_DB_MONITOR => 'config/items/itemtypes/odbc_checks#db.odbc.select'
				]
			],
			'eventlog[name,<regexp>,<severity>,<source>,<eventid>,<maxlines>,<mode>]' => [
				'description' => _('Event log monitoring. Returns log'),
				'value_type' => ITEM_VALUE_TYPE_LOG,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#eventlog'
				]
			],'eventlog.count[name,<regexp>,<severity>,<source>,<eventid>,<maxproclines>,<mode>]' => [
				'description' => _('Event log monitoring. Returns count of entries'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#eventlog.count'
				]
			],
			'icmpping[<target>,<packets>,<interval>,<size>,<timeout>,<options>]' => [
				'description' => _('Checks if host is accessible by ICMP ping. 0 - ICMP ping fails. 1 - ICMP ping successful.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'config/items/itemtypes/simple_checks#icmpping'
				]
			],
			'icmppingloss[<target>,<packets>,<interval>,<size>,<timeout>,<options>]' => [
				'description' => _('Returns percentage of lost ICMP ping packets.'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'config/items/itemtypes/simple_checks#icmppingloss'
				]
			],
			'icmppingsec[<target>,<packets>,<interval>,<size>,<timeout>,<mode>,<options>]' => [
				'description' => _('Returns ICMP ping response time in seconds. Example: 0.02'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'config/items/itemtypes/simple_checks#icmppingsec'
				]
			],
			'ipmi.get' => [
				'description' => _('IPMI sensor IDs and other sensor-related parameters. Returns JSON.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_IPMI => 'config/items/itemtypes/ipmi#supported-checks'
				]
			],
			'jmx.discovery[<discovery mode>,<object name>,<unique short description>]' => [
				'description' => _('Return a JSON array with LLD macros describing the MBean objects or their attributes. Can be used for LLD.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_JMX => 'config/items/itemtypes/jmx_monitoring#jmx-item-keys-in-more-detail'
				]
			],
			'jmx.get[<discovery mode>,<object name>,<unique short description>]' => [
				'description' => _('Return a JSON array with MBean objects or their attributes. Compared to jmx.discovery it does not define LLD macros. Can be used for LLD.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_JMX => 'config/items/itemtypes/jmx_monitoring#jmx-item-keys-in-more-detail'
				]
			],
			'jmx[object_name,attribute_name,<unique short description>]' => [
				'description' => _('Return value of an attribute of MBean object.'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_JMX => 'config/items/itemtypes/jmx_monitoring#jmx-item-keys-in-more-detail'
				]
			],
			'kernel.maxfiles' => [
				'description' => _('Maximum number of opened files supported by OS. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#kernel.maxfiles',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#kernel.maxfiles'
				]
			],
			'kernel.maxproc' => [
				'description' => _('Maximum number of processes supported by OS. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#kernel.maxproc',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#kernel.maxproc'
				]
			],
			'kernel.openfiles' => [
				'description' => _('Number of currently open file descriptors. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#kernel.openfiles',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#kernel.openfiles'
				]
			],
			'log.count[file,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>,<persistent_dir>]' => [
				'description' => _('Count of matched lines in log file monitoring. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#log.count'
				]
			],
			'log[file,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>,<persistent_dir>]' => [
				'description' => _('Log file monitoring. Returns log'),
				'value_type' => ITEM_VALUE_TYPE_LOG,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#log'
				]
			],
			'logrt.count[file_regexp,<regexp>,<encoding>,<maxproclines>,<mode>,<maxdelay>,<options>,<persistent_dir>]' => [
				'description' => _('Count of matched lines in log file monitoring with log rotation support. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#logrt.count'
				]
			],
			'logrt[file_regexp,<regexp>,<encoding>,<maxlines>,<mode>,<output>,<maxdelay>,<options>,<persistent_dir>]' => [
				'description' => _('Log file monitoring with log rotation support. Returns log'),
				'value_type' => ITEM_VALUE_TYPE_LOG,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#logrt'
				]
			],
			'modbus.get[endpoint,<slaveid>,<function>,<address>,<count>,<type>,<endianness>,<offset>]' => [
				'description' => _('Reads modbus data. Returns various types'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#modbus',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#modbus'
				]
			],
			'mqtt.get[<broker_url>,topic,<username>,<password>]' => [
				'description' => _('Value of MQTT topic. Format of returned data depends on the topic content. If wildcards are used, returns topic values in JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/zabbix_agent2#mqtt.get'
				]
			],
			'net.dns.record[<ip>,name,<type>,<timeout>,<count>,<protocol>]' => [
				'description' => _('Performs a DNS query. Returns character string with the required type of information'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.dns.record',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.dns.record'
				]
			],
			'net.dns.perf[<ip>,name,<type>,<timeout>,<count>,<protocol>]' => [
				'description' => _('Performs a DNS query. Returns 0 if DNS is down, query time in seconds (with fractions) otherwise'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.dns.perf',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.dns.perf'
				]
			],
			'net.dns[<ip>,name,<type>,<timeout>,<count>,<protocol>]' => [
				'description' => _('Checks if DNS service is up. Returns 0 - DNS is down (server did not respond or DNS resolution failed); 1 - DNS is up'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.dns',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.dns'
				]
			],
			'net.if.collisions[if]' => [
				'description' => _('Number of out-of-window collisions. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.if.collisions',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.if.collisions'
				]
			],
			'net.if.discovery' => [
				'description' => _('List of network interfaces. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.if.discovery',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.if.discovery'
				]
			],
			'net.if.in[if,<mode>]' => [
				'description' => _('Incoming traffic statistics on network interface. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.if.in',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.if.in'
				]
			],
			'net.if.list' => [
				'description' => _('Network interface list (includes interface type, status, IPv4 address, description). Returns text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent/win_keys#net.if.list',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#net.if.list'
				]
			],
			'net.if.out[if,<mode>]' => [
				'description' => _('Outgoing traffic statistics on network interface. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.if.out',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.if.out'
				]
			],
			'net.if.total[if,<mode>]' => [
				'description' => _('Sum of incoming and outgoing traffic statistics on network interface. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.if.total',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.if.total'
				]
			],
			'net.tcp.listen[port]' => [
				'description' => _('Checks if this TCP port is in LISTEN state. Returns 0 - it is not in LISTEN state; 1 - it is in LISTEN state'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.tcp.listen',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.tcp.listen'
				]
			],
			'net.tcp.port[<ip>,port]' => [
				'description' => _('Checks if it is possible to make TCP connection to specified port. Returns 0 - cannot connect; 1 - can connect'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.tcp.port',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.tcp.port'
				]
			],
			'net.tcp.service.perf[service,<ip>,<port>]' => [
				'description' => _('Checks performance of TCP service. Returns 0 - service is down; seconds - the number of seconds spent while connecting to the service'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.tcp.service.perf',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.tcp.service.perf',
					ITEM_TYPE_SIMPLE => 'config/items/itemtypes/simple_checks#nettcpserviceperf'
				]
			],
			'net.tcp.service[service,<ip>,<port>]' => [
				'description' => _('Checks if service is running and accepting TCP connections. Returns 0 - service is down; 1 - service is running'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.tcp.service',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.tcp.service',
					ITEM_TYPE_SIMPLE => 'config/items/itemtypes/simple_checks#nettcpservice'
				]
			],
			'net.tcp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]' => [
				'description' => _('Returns number of TCP sockets that match parameters. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.tcp.socket.count',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.tcp.socket.count'
				]
			],
			'net.udp.listen[port]' => [
				'description' => _('Checks if this UDP port is in LISTEN state. Returns 0 - it is not in LISTEN state; 1 - it is in LISTEN state'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.udp.listen',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.udp.listen'
				]
			],
			'net.udp.service.perf[service,<ip>,<port>]' => [
				'description' => _('Checks performance of UDP service. Returns 0 - service is down; seconds - the number of seconds spent waiting for response from the service'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.udp.service.perf',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.udp.service.perf',
					ITEM_TYPE_SIMPLE => 'config/items/itemtypes/simple_checks#netudpserviceperf'
				]
			],
			'net.udp.service[service,<ip>,<port>]' => [
				'description' => _('Checks if service is running and responding to UDP requests. Returns 0 - service is down; 1 - service is running'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.udp.service',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.udp.service',
					ITEM_TYPE_SIMPLE => 'config/items/itemtypes/simple_checks#netudpservice'
				]
			],
			'net.udp.socket.count[<laddr>,<lport>,<raddr>,<rport>,<state>]' => [
				'description' => _('Returns number of UDP sockets that match parameters. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#net.udp.socket.count',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#net.udp.socket.count'
				]
			],
			'perf_counter[counter,<interval>]' => [
				'description' => _('Value of any Windows performance counter. Returns integer, float, string or text (depending on the request)'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent/win_keys#perf.counter',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#perf.counter'
				]
			],
			'perf_counter_en[counter,<interval>]' => [
				'description' => _('Value of any Windows performance counter in English. Returns integer, float, string or text (depending on the request)'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent/win_keys#perf.counter.en',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#perf.counter.en'
				]
			],
			'perf_instance.discovery[object]' => [
				'description' => _('List of object instances of Windows performance counters. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent/win_keys#perf.instance.discovery',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#perf.instance.discovery'
				]
			],
			'perf_instance_en.discovery[object]' => [
				'description' => _('List of object instances of Windows performance counters, discovered using object names in English. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent/win_keys#perf.instance.en.discovery',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#perf.instance.en.discovery'
				]
			],
			'proc.cpu.util[<name>,<user>,<type>,<cmdline>,<mode>,<zone>]' => [
				'description' => _('Process CPU utilization percentage. Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#proc.cpu.util',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#proc.cpu.util'
				]
			],
			'proc.get[<name>,<user>,<cmdline>,<mode>]' => [
				'description' => _('List of OS processes with attributes. Returns JSON array'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#proc.get',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#proc.get'
				]
			],
			'proc.mem[<name>,<user>,<mode>,<cmdline>,<memtype>]' => [
				'description' => _('Memory used by process in bytes. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#proc.mem',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#proc.mem'
				]
			],
			'proc.num[<name>,<user>,<state>,<cmdline>,<zone>]' => [
				'description' => _('The number of processes. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#proc.num',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#proc.num'
				]
			],
			'proc_info[process,<attribute>,<type>]' => [
				'description' => _('Various information about specific process(es). Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent/win_keys#proc.info',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#proc.info'
				]
			],
			'registry.data[key,<value name>]' => [
				'description' => _('Value data for value name in Windows Registry key.'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent/win_keys#registry.data',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#registry.data'
				]
			],
			'registry.get[key,<mode>,<name regexp>]' => [
				'description' => _('List of Windows Registry values or keys located at given key. Returns JSON.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent/win_keys#registry.get',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#registry.get'
				]
			],
			'sensor[device,sensor,<mode>]' => [
				'description' => _('Hardware sensor reading. Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#sensor',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#sensor'
				]
			],
			'service.info[service,<param>]' => [
				'description' => _('Information about a service. Returns integer with param as state, startup; string - with param as displayname, path, user; text - with param as description; Specifically for state: 0 - running, 1 - paused, 2 - start pending, 3 - pause pending, 4 - continue pending, 5 - stop pending, 6 - stopped, 7 - unknown, 255 - no such service; Specifically for startup: 0 - automatic, 1 - automatic delayed, 2 - manual, 3 - disabled, 4 - unknown'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent/win_keys#service.info',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#service.info'
				]
			],
			'services[<type>,<state>,<exclude>]' => [
				'description' => _('Listing of services. Returns 0 - if empty; text - list of services separated by a newline'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent/win_keys#services',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#services'
				]
			],
			'snmptrap.fallback' => [
				'description' => _('Catches all SNMP traps that were not caught by any of snmptrap[] items.'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_SNMPTRAP => 'config/items/itemtypes/snmptrap#configuring-snmp-traps'
				]
			],
			'snmptrap[<regex>]' => [
				'description' => _('Catches all SNMP traps that match regex. If regexp is unspecified, catches any trap.'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_SNMPTRAP => 'config/items/itemtypes/snmptrap#configuring-snmp-traps'
				]
			],
			'system.boottime' => [
				'description' => _('System boot time. Returns integer (Unix timestamp)'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.boottime',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.boottime'
				]
			],
			'system.cpu.discovery' => [
				'description' => _('List of detected CPUs/CPU cores. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.cpu.discovery',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.cpu.discovery'
				]
			],
			'system.cpu.intr' => [
				'description' => _('Device interrupts. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.cpu.intr',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.cpu.intr'
				]
			],
			'system.cpu.load[<cpu>,<mode>]' => [
				'description' => _('CPU load. Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.cpu.load',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.cpu.load'
				]
			],
			'system.cpu.num[<type>]' => [
				'description' => _('Number of CPUs. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.cpu.num',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.cpu.num'
				]
			],
			'system.cpu.switches' => [
				'description' => _('Count of context switches. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.cpu.switches',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.cpu.switches'
				]
			],
			'system.cpu.util[<cpu>,<type>,<mode>,<logical_or_physical>]' => [
				'description' => _('CPU utilization percentage. Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.cpu.util',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.cpu.util'
				]
			],
			'system.hostname[<type>,<transform>]' => [
				'description' => _('System host name. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.hostname',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.hostname'
				]
			],
			'system.hw.chassis[<info>]' => [
				'description' => _('Chassis information. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.hw.chassis',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.hw.chassis'
				]
			],
			'system.hw.cpu[<cpu>,<info>]' => [
				'description' => _('CPU information. Returns string or integer'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.hw.cpu',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.hw.cpu'
				]
			],
			'system.hw.devices[<type>]' => [
				'description' => _('Listing of PCI or USB devices. Returns text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.hw.devices',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.hw.devices'
				]
			],
			'system.hw.macaddr[<interface>,<format>]' => [
				'description' => _('Listing of MAC addresses. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.hw.macaddr',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.hw.macaddr'
				]
			],
			'system.localtime[<type>]' => [
				'description' => _('System time. Returns integer with type as UTC; string - with type as local'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.localtime',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.localtime'
				]
			],
			'system.run[command,<mode>]' => [
				'description' => _('Run specified command on the host. Returns text result of the command; 1 - with mode as nowait (regardless of command result)'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.run',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.run'
				]
			],
			'system.stat[resource,<type>]' => [
				'description' => _('System statistics. Returns integer or float'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.stat',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.stat'
				]
			],
			'system.sw.arch' => [
				'description' => _('Software architecture information. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.sw.arch',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.sw.arch'
				]
			],
			'system.sw.os[<info>]' => [
				'description' => _('Operating system information. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.sw.os',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.sw.os'
				]
			],
			'system.sw.os.get' => [
				'description' => _('Operating system version information. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.sw.os.get',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.sw.os.get'
				]
			],
			'system.sw.packages[<regexp>,<manager>,<format>]' => [
				'description' => _('Listing of installed packages. Returns text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.sw.packages',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.sw.packages'
				]
			],
			'system.sw.packages.get[<regexp>,<manager>]' => [
				'description' => _('Detailed listing of installed packages. Returns text in JSON format'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.sw.packages.get',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.sw.packages.get'
				]
			],
			'system.swap.in[<device>,<type>]' => [
				'description' => _('Swap in (from device into memory) statistics. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.swap.in',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.swap.in'
				]
			],
			'system.swap.out[<device>,<type>]' => [
				'description' => _('Swap out (from memory onto device) statistics. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.swap.out',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.swap.out'
				]
			],
			'system.swap.size[<device>,<type>]' => [
				'description' => _('Swap space size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.swap.size',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.swap.size'
				]
			],
			'system.uname' => [
				'description' => _('Identification of the system. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.uname',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.uname'
				]
			],
			'system.uptime' => [
				'description' => _('System uptime in seconds. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.uptime',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.uptime'
				]
			],
			'system.users.num' => [
				'description' => _('Number of users logged in. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#system.users.num',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#system.users.num'
				]
			],
			'vfs.dev.discovery' => [
				'description' => _('List of block devices and their type. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.dev.discovery',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.dev.discovery'
				]
			],
			'vfs.dev.read[<device>,<type>,<mode>]' => [
				'description' => _('Disk read statistics. Returns integer with type in sectors, operations, bytes; float with type in sps, ops, bps'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.dev.read',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.dev.read'
				]
			],
			'vfs.dev.write[<device>,<type>,<mode>]' => [
				'description' => _('Disk write statistics. Returns integer with type in sectors, operations, bytes; float with type in sps, ops, bps'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.dev.write',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.dev.write'
				]
			],
			'vfs.dir.count[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]' => [
				'description' => _('Count of directory entries, recursively. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.dir.count',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.dir.count'
				]
			],
			'vfs.dir.get[dir,<regex_incl>,<regex_excl>,<types_incl>,<types_excl>,<max_depth>,<min_size>,<max_size>,<min_age>,<max_age>,<regex_excl_dir>]' => [
				'description' => _('List of directory entries, recursively. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.dir.get',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.dir.get'
				]
			],
			'vfs.dir.size[dir,<regex_incl>,<regex_excl>,<mode>,<max_depth>,<regex_excl_dir>]' => [
				'description' => _('Directory size (in bytes). Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.dir.size',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.dir.size'
				]
			],
			'vfs.file.cksum[file,<mode>]' => [
				'description' => _('File checksum, calculated by the UNIX cksum algorithm. Returns integer for crc32 (default) and string for md5, sha256'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.file.cksum',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.file.cksum'
				]
			],
			'vfs.file.contents[file,<encoding>]' => [
				'description' => _('Retrieving contents of a file. Returns text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.file.contents',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.file.contents'
				]
			],
			'vfs.file.exists[file,<types_incl>,<types_excl>]' => [
				'description' => _('Checks if file exists. Returns 0 - not found; 1 - file of the specified type exists'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.file.exists',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.file.exists'
				]
			],
			'vfs.file.get[file]' => [
				'description' => _('Information about a file. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.file.get',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.file.get'
				]
			],
			'vfs.file.md5sum[file]' => [
				'description' => _('MD5 checksum of file. Returns character string (MD5 hash of the file)'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.file.md5sum',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.file.md5sum'
				]
			],
			'vfs.file.owner[file,<ownertype>,<resulttype>]' => [
				'description' => _('File owner information. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.file.owner',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.file.owner'
				]
			],
			'vfs.file.permissions[file]' => [
				'description' => _('Returns 4-digit string containing octal number with Unix permissions'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.file.permissions',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.file.permissions'
				]
			],
			'vfs.file.regexp[file,regexp,<encoding>,<start line>,<end line>,<output>]' => [
				'description' => _('Find string in a file. Returns the line containing the matched string, or as specified by the optional output parameter'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.file.regexp',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.file.regexp'
				]
			],
			'vfs.file.regmatch[file,regexp,<encoding>,<start line>,<end line>]' => [
				'description' => _('Find string in a file. Returns 0 - match not found; 1 - found'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.file.regmatch',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.file.regmatch'
				]
			],
			'vfs.file.size[file,<mode>]' => [
				'description' => _('File size in bytes (default) or in newlines. Returns integer'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.file.size',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.file.size'
				]
			],
			'vfs.file.time[file,<mode>]' => [
				'description' => _('File time information. Returns integer (Unix timestamp)'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.file.time',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.file.time'
				]
			],
			'vfs.fs.discovery' => [
				'description' => _('List of mounted filesystems and their types. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.fs.discovery',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.fs.discovery'
				]
			],
			'vfs.fs.get' => [
				'description' => _('List of mounted filesystems, their types, disk space and inode statistics. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.fs.get',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.fs.get'
				]
			],
			'vfs.fs.inode[fs,<mode>]' => [
				'description' => _('Number or percentage of inodes. Returns integer for number; float for percentage'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.fs.inode',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.fs.inode'
				]
			],
			'vfs.fs.size[fs,<mode>]' => [
				'description' => _('Disk space in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vfs.fs.size',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vfs.fs.size'
				]
			],
			'vm.memory.size[<mode>]' => [
				'description' => _('Memory size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#vm.memory.size',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#vm.memory.size'
				]
			],
			'vm.vmemory.size[<type>]' => [
				'description' => _('Virtual space size in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent/win_keys#vm.vmemory.size',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#vm.vmemory.size'
				]
			],
			'vmware.alarms.get[url]' => [
				'description' => _('VMware virtual center alarms data, returns JSON, "url" - VMware service URL'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.alarms'
				]
			],
			'vmware.cl.perfcounter[url,id,path,<instance>]' => [
				'description' => _('VMware cluster performance counter, "url" - VMware service URL, "id" - VMware cluster id, "path" - performance counter path, "instance" - performance counter instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.cl.perfcounter'
				]
			],
			'vmware.cluster.alarms.get[url,id]' => [
				'description' => _('VMware cluster alarms data, returns JSON, "url" - VMware service URL, "id" - VMware cluster id'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.cluster.alarms'
				]
			],
			'vmware.cluster.discovery[url]' => [
				'description' => _('Discovery of VMware clusters, "url" - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.cluster.discovery'
				]
			],
			'vmware.cluster.property[url,id,prop]' => [
				'description' => _('VMware cluster property, "url" - VMware service URL, "id" - VMware cluster id, "prop" - property path'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.cluster.property'
				]
			],
			'vmware.cluster.status[url,name]' => [
				'description' => _('VMware cluster status, "url" - VMware service URL, "name" - VMware cluster name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.cluster.status'
				]
			],
			'vmware.cluster.tags.get[url,id]' => [
				'description' => _('VMware cluster tags array, "url" - VMware service URL, "id" - VMware cluster id'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.cluster.tags'
				]
			],
			'vmware.datastore.alarms.get[url,uuid]' => [
				'description' => _('VMware datastore alarms data, returns JSON, "url" - VMware service URL, "uuid" - VMware datastore global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.datastore.alarms'
				]
			],
			'vmware.datastore.discovery[url]' => [
				'description' => _('Discovery of VMware datastores, "url" - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.datastore.discovery'
				]
			],
			'vmware.datastore.hv.list[url,datastore]' => [
				'description' => _('VMware datastore hypervisors list, "url" - VMware service URL, "datastore" - datastore name'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.datastore.hv.list'
				]
			],
			'vmware.datastore.perfcounter[url,uuid,path,<instance>]' => [
				'description' => _('VMware datastore performance counter, "url" - VMware service URL, "uuid" - VMware datastore global unique identifier, "path" - performance counter path, "instance" - datastore perfcounter instance from vmware.hv.diskinfo.get'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.datastore.perfcounter'
				]
			],
			'vmware.datastore.property[url,uuid,prop]' => [
				'description' => _('VMware datastore property, "url" - VMware service URL, "uuid" - VMware datastore global unique identifier, "prop" - property path'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.datastore.property'
				]
			],
			'vmware.datastore.read[url,datastore,<mode>]' => [
				'description' => _('VMware datastore read statistics, "url" - VMware service URL, "datastore" - datastore name, "mode"- latency/maxlatency - average or maximum'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.datastore.read'
				]
			],
			'vmware.datastore.size[url,datastore,<mode>]' => [
				'description' => _('VMware datastore capacity statistics in bytes or in percentage from total. Returns integer for bytes; float for percentage'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.datastore.size'
				]
			],
			'vmware.datastore.tags.get[url,uuid]' => [
				'description' => _('VMware datastore tags array, "url" - VMware service URL, "uuid" - VMware datastore global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.datastore.tags'
				]
			],
			'vmware.datastore.write[url,datastore,<mode>]' => [
				'description' => _('VMware datastore write statistics, "url" - VMware service URL, "datastore" - datastore name, "mode"- latency/maxlatency - average or maximum'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.datastore.write'
				]
			],
			'vmware.dc.alarms.get[url,id]' => [
				'description' => _('VMware datacenter alarms data, returns JSON, "url" - VMware service URL, "id" - VMware datacenter id'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.dc.alarms'
				]
			],
			'vmware.dc.discovery[url]' => [
				'description' => _('VMware datacenters and their IDs, "url" - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.dc.discovery'
				]
			],
			'vmware.dc.tags.get[url,id]' => [
				'description' => _('VMware datacenter tags array, "url" - VMware service URL, "id" - VMware datacenter id. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.dc.tags'
				]
			],
			'vmware.dvswitch.discovery[url]' => [
				'description' => _('VMware Distributed Virtual Switch, "url" - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.dvswitch.discovery'
				]
			],
			'vmware.dvswitch.fetchports.get[url,uuid,<filter>,<mode>]' => [
				'description' => _('VMware FetchDVPorts wrapper, "url" - VMware service URL, "uuid" - VMware DVSwitch global unique identifier, "filter" - vmware data object DistributedVirtualSwitchPortCriteria, "mode"- state(default)/full. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.dvswitch.fetchports'
				]
			],
			'vmware.eventlog[url,<mode>,<severity>]' => [
				'description' => _('VMware event log, "url" - VMware service URL, "mode"- all (default) or skip - skip processing of older data, "severity" - filtering is disabled by default or "error,warning,info,user" in any combination'),
				'value_type' => ITEM_VALUE_TYPE_LOG,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.eventlog'
				]
			],
			'vmware.fullname[url]' => [
				'description' => _('VMware service full name, "url" - VMware service URL'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.fullname'
				]
			],
			'vmware.hv.alarms.get[url,uuid]' => [
				'description' => _('VMware hypervisor alarms data, returns JSON, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.alarms'
				]
			],
			'vmware.hv.cluster.name[url,uuid]' => [
				'description' => _('VMware hypervisor cluster name, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.cluster'
				]
			],
			'vmware.hv.connectionstate[url,uuid]' => [
				'description' => _('VMware hypervisor connection state, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.connectionstate'
				]
			],
			'vmware.hv.cpu.usage.perf[url,uuid]' => [
				'description' => _('CPU usage as a percentage during the interval, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.cpu.perf'
				]
			],
			'vmware.hv.cpu.usage[url,uuid]' => [
				'description' => _('VMware hypervisor processor usage in Hz, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.cpu'
				]
			],
			'vmware.hv.cpu.utilization[url,uuid]' => [
				'description' => _('CPU usage as a percentage during the interval depends on power management or HT, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.cpu.utilization'
				]
			],
			'vmware.hv.datacenter.name[url,uuid]' => [
				'description' => _('VMware hypervisor datacenter name, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.datacenter'
				]
			],
			'vmware.hv.datastore.discovery[url,uuid]' => [
				'description' => _('Discovery of VMware hypervisor datastores, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.datastore.discovery'
				]
			],
			'vmware.hv.datastore.list[url,uuid]' => [
				'description' => _('VMware hypervisor datastores list, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.datastore.list'
				]
			],
			'vmware.hv.datastore.multipath[url,uuid,<datastore>,<partitionid>]' => [
				'description' => _('Number of available DS paths, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "datastore" - Datastore name, "partitionid" - internal id of physical device from vmware.hv.datastore.discovery'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.datastore.multipath'
				]
			],
			'vmware.hv.datastore.read[url,uuid,datastore,<mode>]' => [
				'description' => _('VMware hypervisor datastore read statistics, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "datastore" - datastore name, "mode"- latency'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.datastore.read'
				]
			],
			'vmware.hv.datastore.size[url,uuid,datastore,<mode>]' => [
				'description' => _('VMware datastore capacity statistics in bytes or in percentage from total, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "datastore" - datastore name, "mode" - total(default)/free/pfree/uncommitted. Returns integer for bytes; float for percentage'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.datastore.size'
				]
			],
			'vmware.hv.datastore.write[url,uuid,datastore,<mode>]' => [
				'description' => _('VMware hypervisor datastore write statistics, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "datastore" - datastore name, "mode"- latency'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.datastore.write'
				]
			],
			'vmware.hv.discovery[url]' => [
				'description' => _('Discovery of VMware hypervisors, "url" - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.discovery'
				]
			],
			'vmware.hv.diskinfo.get[url,uuid]' => [
				'description' => _('Info about internal disks of hypervisor required for vmware.datastore.perfcounter, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.diskinfo'
				]
			],
			'vmware.hv.fullname[url,uuid]' => [
				'description' => _('VMware hypervisor name, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.fullname'
				]
			],
			'vmware.hv.hw.cpu.freq[url,uuid]' => [
				'description' => _('VMware hypervisor processor frequency, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.hw.cpu.freq'
				]
			],
			'vmware.hv.hw.cpu.model[url,uuid]' => [
				'description' => _('VMware hypervisor processor model, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.hw.cpu.model'
				]
			],
			'vmware.hv.hw.cpu.num[url,uuid]' => [
				'description' => _('Number of processor cores on VMware hypervisor, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.hw.cpu.num'
				]
			],
			'vmware.hv.hw.cpu.threads[url,uuid]' => [
				'description' => _('Number of processor threads on VMware hypervisor, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.hw.cpu.threads'
				]
			],
			'vmware.hv.hw.memory[url,uuid]' => [
				'description' => _('VMware hypervisor total memory size, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.hw.memory'
				]
			],
			'vmware.hv.hw.model[url,uuid]' => [
				'description' => _('VMware hypervisor model, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.hw.model'
				]
			],
			'vmware.hv.hw.sensors.get[url,uuid]' => [
				'description' => _('VMware hypervisor sensors value, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.hw.sensors'
				]
			],
			'vmware.hv.hw.serialnumber[url,uuid]' => [
				'description' => _('VMware hypervisor serialnumber, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.hw.serialnumber'
				]
			],
			'vmware.hv.hw.uuid[url,uuid]' => [
				'description' => _('VMware hypervisor BIOS UUID, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.hw.uuid'
				]
			],
			'vmware.hv.hw.vendor[url,uuid]' => [
				'description' => _('VMware hypervisor vendor name, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.hw.vendor'
				]
			],
			'vmware.hv.maintenance[url,uuid]' => [
				'description' => _('VMware hypervisor maintenance status, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns 0 - not in maintenance; 1 - in maintenance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.maintenance'
				]
			],
			'vmware.hv.memory.size.ballooned[url,uuid]' => [
				'description' => _('VMware hypervisor ballooned memory size, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.memory.size.ballooned'
				]
			],
			'vmware.hv.memory.used[url,uuid]' => [
				'description' => _('VMware hypervisor used memory size, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.memory.used'
				]
			],
			'vmware.hv.net.if.discovery[url,uuid]' => [
				'description' => _('Discovery of VMware hypervisor network interfaces, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.net.if.discovery'
				]
			],
			'vmware.hv.network.in[url,uuid,<mode>]' => [
				'description' => _('VMware hypervisor network input statistics, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "mode"- bps'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.network.in'
				]
			],
			'vmware.hv.network.linkspeed[url,uuid,ifname]' => [
				'description' => _('VMware hypervisor network interface speed, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, <ifname> - interface name'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.network.linkspeed'
				]
			],
			'vmware.hv.network.out[url,uuid,<mode>]' => [
				'description' => _('VMware hypervisor network output statistics, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "mode"- bps'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.network.out'
				]
			],
			'vmware.hv.perfcounter[url,uuid,path,<instance>]' => [
				'description' => _('VMware hypervisor performance counter, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "path" - performance counter path, "instance" - performance counter instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.perfcounter'
				]
			],
			'vmware.hv.power[url,uuid,<max>]' => [
				'description' => _('Power usage , "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "max" - Maximum allowed power usage'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.power'
				]
			],
			'vmware.hv.property[url,uuid,prop]' => [
				'description' => _('VMware hypervisor property , "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier, "prop" - property path'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.property'
				]
			],
			'vmware.hv.sensor.health.state[url,uuid]' => [
				'description' => _('VMware hypervisor health state rollup sensor, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns 0 - gray; 1 - green; 2 - yellow; 3 - red'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.sensor.health'
				]
			],
			'vmware.hv.sensors.get[url,uuid]' => [
				'description' => _('VMware hypervisor HW vendor state sensors, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.sensors'
				]
			],
			'vmware.hv.status[url,uuid]' => [
				'description' => _('VMware hypervisor status, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.status'
				]
			],
			'vmware.hv.tags.get[url,uuid]' => [
				'description' => _('VMware hypervisor tags array, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.tags'
				]
			],
			'vmware.hv.uptime[url,uuid]' => [
				'description' => _('VMware hypervisor uptime, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.uptime'
				]
			],
			'vmware.hv.version[url,uuid]' => [
				'description' => _('VMware hypervisor version, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.version'
				]
			],
			'vmware.hv.vm.num[url,uuid]' => [
				'description' => _('Number of virtual machines on VMware hypervisor, "url" - VMware service URL, "uuid" - VMware hypervisor global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.hv.vm.num'
				]
			],
			'vmware.rp.cpu.usage[url,rpid]' => [
				'description' => _('CPU usage in hertz during the interval on VMware Resource Pool, "url" - VMware service URL, "rpid" - VMware resource pool id'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.rp.cpu'
				]
			],
			'vmware.rp.memory[url,rpid,<mode>]' => [
				'description' => _('Memory metrics of VMware Resource Pool, "url" - VMware service URL, "rpid" - VMware resource pool id, "mode"- consumed(default)/ballooned/overhead memory'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.rp.memory'
				]
			],
			'vmware.version[url]' => [
				'description' => _('VMware service version, "url" - VMware service URL'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.version'
				]
			],
			'vmware.vm.alarms.get[url,uuid]' => [
				'description' => _('VMware virtual machine alarms data, returns JSON, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.alarms'
				]
			],
			'vmware.vm.attribute[url,uuid,name]' => [
				'description' => _('VMware virtual machine custom attribute value, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "name" - custom attribute name'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.attribute'
				]
			],
			'vmware.vm.cluster.name[url,uuid]' => [
				'description' => _('VMware virtual machine name, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.cluster.name'
				]
			],
			'vmware.vm.consolidationneeded[url,uuid]' => [
				'description' => _('VMware virtual machine disk requires consolidation, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.consolidationneeded'
				]
			],
			'vmware.vm.cpu.latency[url,uuid]' => [
				'description' => _('Percent of time the virtual machine is unable to run because it is contending for access to the physical CPU(s), "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.cpu.latency'
				]
			],
			'vmware.vm.cpu.num[url,uuid]' => [
				'description' => _('Number of processors on VMware virtual machine, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.cpu.num'
				]
			],
			'vmware.vm.cpu.readiness[url,uuid,<instance>]' => [
				'description' => _('Percentage of time that the virtual machine was ready, but could not get scheduled to run on the physical CPU, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - CPU instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.cpu.readiness'
				]
			],
			'vmware.vm.cpu.ready[url,uuid]' => [
				'description' => _('VMware virtual machine processor ready time ms, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.cpu.ready'
				]
			],
			'vmware.vm.cpu.swapwait[url,uuid,<instance>]' => [
				'description' => _('CPU time spent waiting for swap-in, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - CPU instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.cpu.swapwait'
				]
			],
			'vmware.vm.cpu.usage.perf[url,uuid]' => [
				'description' => _('CPU usage as a percentage during the interval, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.cpu.usage.perf'
				]
			],
			'vmware.vm.cpu.usage[url,uuid]' => [
				'description' => _('VMware virtual machine processor usage in Hz, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.cpu.usage'
				]
			],
			'vmware.vm.datacenter.name[url,uuid]' => [
				'description' => _('VMware virtual machine datacenter name, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier. Returns string'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.datacenter.name'
				]
			],
			'vmware.vm.discovery[url]' => [
				'description' => _('Discovery of VMware virtual machines, "url" - VMware service URL. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.discovery'
				]
			],
			'vmware.vm.guest.memory.size.swapped[url,uuid]' => [
				'description' => _('Amount of guest physical memory that is swapped out to the swap space, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.guest.memory.size.swapped'
				]
			],
			'vmware.vm.guest.osuptime[url,uuid]' => [
				'description' => _('Total time elapsed, in seconds, since last operating system boot-up, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.guest.osuptime'
				]
			],
			'vmware.vm.hv.maintenance[url,uuid]' => [
				'description' => _('VMware virtual machine hypervisor maintenance status, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier. Returns 0 - not in maintenance; 1 - in maintenance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.hv.maintenance'
				]
			],
			'vmware.vm.hv.name[url,uuid]' => [
				'description' => _('VMware virtual machine hypervisor name, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.hv.name'
				]
			],
			'vmware.vm.memory.size.ballooned[url,uuid]' => [
				'description' => _('VMware virtual machine ballooned memory size, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.memory.size.ballooned'
				]
			],
			'vmware.vm.memory.size.compressed[url,uuid]' => [
				'description' => _('VMware virtual machine compressed memory size, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.memory.size.compressed'
				]
			],
			'vmware.vm.memory.size.consumed[url,uuid]' => [
				'description' => _('Amount of host physical memory consumed for backing up guest physical memory pages, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.memory.size.consumed'
				]
			],
			'vmware.vm.memory.size.private[url,uuid]' => [
				'description' => _('VMware virtual machine private memory size, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.memory.size.private'
				]
			],
			'vmware.vm.memory.size.shared[url,uuid]' => [
				'description' => _('VMware virtual machine shared memory size, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.memory.size.shared'
				]
			],
			'vmware.vm.memory.size.swapped[url,uuid]' => [
				'description' => _('VMware virtual machine swapped memory size, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.memory.size.swapped'
				]
			],
			'vmware.vm.memory.size.usage.guest[url,uuid]' => [
				'description' => _('VMware virtual machine guest memory usage, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.memory.size.usage.guest'
				]
			],
			'vmware.vm.memory.size.usage.host[url,uuid]' => [
				'description' => _('VMware virtual machine host memory usage, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.memory.size.usage.host'
				]
			],
			'vmware.vm.memory.size[url,uuid]' => [
				'description' => _('VMware virtual machine total memory size, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.memory.size'
				]
			],
			'vmware.vm.memory.usage[url,uuid]' => [
				'description' => _('Percentage of host physical memory that has been consumed, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.memory.usage'
				]
			],
			'vmware.vm.net.if.discovery[url,uuid]' => [
				'description' => _('Discovery of VMware virtual machine network interfaces, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.net.if.discovery'
				]
			],
			'vmware.vm.net.if.in[url,uuid,instance,<mode>]' => [
				'description' => _('VMware virtual machine network interface input statistics, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - network interface instance, "mode"- bps/pps - bytes/packets per second'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.net.if.in'
				]
			],
			'vmware.vm.net.if.out[url,uuid,instance,<mode>]' => [
				'description' => _('VMware virtual machine network interface output statistics, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - network interface instance, "mode"- bps/pps - bytes/packets per second'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.net.if.out'
				]
			],
			'vmware.vm.net.if.usage[url,uuid,<instance>]' => [
				'description' => _('Network utilization (combined transmit-rates and receive-rates) during the interval, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - network interface instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.net.if.usage'
				]
			],
			'vmware.vm.perfcounter[url,uuid,path,<instance>]' => [
				'description' => _('VMware virtual machine performance counter, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "path" - performance counter path, "instance" - performance counter instance'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.perfcounter'
				]
			],
			'vmware.vm.powerstate[url,uuid]' => [
				'description' => _('VMware virtual machine power state, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.powerstate'
				]
			],
			'vmware.vm.property[url,uuid,prop]' => [
				'description' => _('VMware virtual machine property, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "prop" - property path'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.property'
				]
			],
			'vmware.vm.snapshot.get[url,uuid]' => [
				'description' => _('VMware virtual machine snapshot state, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.snapshot'
				]
			],
			'vmware.vm.state[url,uuid]' => [
				'description' => _('VMware virtual machine state, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.state'
				]
			],
			'vmware.vm.storage.committed[url,uuid]' => [
				'description' => _('VMware virtual machine committed storage space, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.storage.committed'
				]
			],
			'vmware.vm.storage.readoio[url,uuid,instance]' => [
				'description' => _('Average number of outstanding read requests to the virtual disk during the collection interval , "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - disk device instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.storage.readoio'
				]
			],
			'vmware.vm.storage.totalreadlatency[url,uuid,instance]' => [
				'description' => _('The average time a read from the virtual disk takes, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - disk device instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.storage.totalreadlatency'
				]
			],
			'vmware.vm.storage.totalwritelatency[url,uuid,instance]' => [
				'description' => _('The average time a write to the virtual disk takes, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - disk device instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.storage.totalwritelatency'
				]
			],
			'vmware.vm.storage.uncommitted[url,uuid]' => [
				'description' => _('VMware virtual machine uncommitted storage space, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.storage.uncommitted'
				]
			],
			'vmware.vm.storage.unshared[url,uuid]' => [
				'description' => _('VMware virtual machine unshared storage space, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.storage.unshared'
				]
			],
			'vmware.vm.storage.writeoio[url,uuid,instance]' => [
				'description' => _('Average number of outstanding write requests to the virtual disk during the collection interval, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - disk device instance'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.storage.writeoio'
				]
			],
			'vmware.vm.tags.get[url,uuid]' => [
				'description' => _('VMware virtual machine tags array, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.tags'
				]
			],
			'vmware.vm.tools[url,uuid,mode]' => [
				'description' => _('VMware virtual machine tools state, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "mode"- version or status'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.tools'
				]
			],
			'vmware.vm.uptime[url,uuid]' => [
				'description' => _('VMware virtual machine uptime, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.uptime'
				]
			],
			'vmware.vm.vfs.dev.discovery[url,uuid]' => [
				'description' => _('Discovery of VMware virtual machine disk devices, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.vfs.dev.discovery'
				]
			],
			'vmware.vm.vfs.dev.read[url,uuid,instance,<mode>]' => [
				'description' => _('VMware virtual machine disk device read statistics, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - disk device instance, "mode"- bps/ops - bytes/operations per second'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.vfs.dev.read'
				]
			],
			'vmware.vm.vfs.dev.write[url,uuid,instance,<mode>]' => [
				'description' => _('VMware virtual machine disk device write statistics, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "instance" - disk device instance, "mode"- bps/ops - bytes/operations per second'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.vfs.dev.write'
				]
			],
			'vmware.vm.vfs.fs.discovery[url,uuid]' => [
				'description' => _('Discovery of VMware virtual machine file systems, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier. Returns JSON'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.vfs.fs.discovery'
				]
			],
			'vmware.vm.vfs.fs.size[url,uuid,fsname,<mode>]' => [
				'description' => _('VMware virtual machine file system statistics, "url" - VMware service URL, "uuid" - VMware virtual machine global unique identifier, "fsname" - file system name, "mode"- total/free/used/pfree/pused'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_SIMPLE => 'vm_monitoring/vmware_keys#vmware.vm.vfs.fs.size'
				]
			],
			'web.page.get[host,<path>,<port>]' => [
				'description' => _('Get content of web page. Returns web page source as text'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#web.page.get',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#web.page.get'
				]
			],
			'web.page.perf[host,<path>,<port>]' => [
				'description' => _('Loading time of full web page (in seconds). Returns float'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#web.page.perf',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#web.page.perf'
				]
			],
			'web.page.regexp[host,<path>,<port>,regexp,<length>,<output>]' => [
				'description' => _('Find string on a web page. Returns the matched string, or as specified by the optional output parameter'),
				'value_type' => ITEM_VALUE_TYPE_STR,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#web.page.regexp',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#web.page.regexp'
				]
			],
			'wmi.get[namespace,query]' => [
				'description' => _('Execute WMI query and return the first selected object. Returns integer, float, string or text (depending on the request)'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent/win_keys#wmi.get',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent/win_keys#wmi.get'
				]
			],
			'wmi.getall[namespace,query]' => [
				'description' => _('Execute WMI query and return the JSON document with all selected objects'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent/win_keys#wmi.getall'
				]
			],
			'zabbix.stats[<ip>,<port>,queue,<from>,<to>]' => [
				'description' => _('Number of items in the queue which are delayed in Zabbix server or proxy by "from" till "to" seconds, inclusive.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#zabbix.stats.two',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#zabbix.stats.two'
				]
			],
			'zabbix.stats[<ip>,<port>]' => [
				'description' => _('Returns a JSON object containing Zabbix server or proxy internal metrics.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_ZABBIX => 'config/items/itemtypes/zabbix_agent#zabbix.stats',
					ITEM_TYPE_ZABBIX_ACTIVE => 'config/items/itemtypes/zabbix_agent#zabbix.stats'
				]
			],
			'zabbix[boottime]' => [
				'description' => _('Startup time of Zabbix server, Unix timestamp.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#boottime'
				]
			],
			'zabbix[connector_queue]' => [
				'description' => _('Count of values enqueued in the connector queue.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#connector.queue'
				]
			],
			'zabbix[discovery_queue]' => [
				'description' => _('Count of network checks enqueued in the discovery queue.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#discovery.queue'
				]
			],
			'zabbix[host,,items]' => [
				'description' => _('Number of enabled items on the host.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#host.items'
				]
			],
			'zabbix[host,,items_unsupported]' => [
				'description' => _('Number of unsupported items on the host.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#host.items.unsupported'
				]
			],
			'zabbix[host,,maintenance]' => [
				'description' => _('Returns current maintenance status of the host.'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#maintenance'
				]
			],
			'zabbix[host,<type>,available]' => [
				'description' => _('Returns availability of a particular type of checks on the host. Value of this item corresponds to availability icons in the host list. Valid types are: agent, active_agent, snmp, ipmi, jmx.'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#host.available'
				]
			],
			'zabbix[host,discovery,interfaces]' => [
				'description' => _('Returns a JSON array describing the host network interfaces configured in Zabbix. Can be used for LLD.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#discovery.interfaces'
				]
			],
			'zabbix[hosts]' => [
				'description' => _('Number of monitored hosts'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#hosts'
				]
			],
			'zabbix[items]' => [
				'description' => _('Number of items in Zabbix database.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#items'
				]
			],
			'zabbix[items_unsupported]' => [
				'description' => _('Number of unsupported items in Zabbix database.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#items.unsupported'
				]
			],
			'zabbix[java,,<param>]' => [
				'description' => _('Returns information associated with Zabbix Java gateway. Valid params are: ping, version.'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#java'
				]
			],
			'zabbix[lld_queue]' => [
				'description' => _('Count of values enqueued in the low-level discovery processing queue.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#lld.queue'
				]
			],
			'zabbix[preprocessing_queue]' => [
				'description' => _('Count of values enqueued in the preprocessing queue.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#preprocessing.queue'
				]
			],
			'zabbix[process,<type>,<mode>,<state>]' => [
				'description' => _('Time a particular Zabbix process or a group of processes (identified by <type> and <mode>) spent in <state> in percentage.'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#process'
				]
			],
			'zabbix[proxy,<name>,<param>]' => [
				'description' => _('Time of proxy last access. Name - proxy name. Valid params are: lastaccess - Unix timestamp, delay - seconds.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#proxy'
				]
			],
			'zabbix[proxy,discovery]' => [
				'description' => _('List of Zabbix proxies with name, mode, encryption, compression, version, last seen, host count, item count, required values per second (vps), compatibility (current/outdated/unsupported), timeouts, proxy group name if proxy belongs to group, state (unknown/offline/online). Returns JSON.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#proxy.discovery'
				]
			],
			'zabbix[proxy_history]' => [
				'description' => _('Number of items in proxy history that are not yet sent to the server'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#proxy.history'
				]
			],
			'zabbix[proxy group,<name>,available]' => [
				'description' => _('Number of online proxies. Returns integer.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#proxy.group.a'
				]
			],
			'zabbix[proxy group,<name>,pavailable]' => [
				'description' => _('Percentage of online proxies. Returns float.'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#proxy.group.b'
				]
			],
			'zabbix[proxy group,<name>,proxies]' => [
				'description' => _('List of Zabbix proxies with name, mode, encryption, compression, version, last seen, host count, item count, required values per second (vps), compatibility (current/outdated/unsupported), timeouts, proxy group name, state (unknown/offline/online). Returns JSON.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#proxy.group.c'
				]
			],
			'zabbix[proxy group,<name>,state]' => [
				'description' => _('State of proxy group. Returns integer: 0 - unknown; 1 - offline; 2 - recovering; 3 - online; 4 - degrading.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#proxy.group.d'
				]
			],
			'zabbix[proxy group,discovery]' => [
				'description' => _('List of Zabbix proxy groups configuration data and real-time data. Configuration data includes proxy group name, failover delay, and minimum number of online proxies required for the group to be online. Real-time data includes number of online proxies, percentage of online proxies, and state of proxy group (unknown, offline, recovering, online, degrading). This item does not return groupless proxies. Returns JSON.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#proxy.group.e'
				]
			],
			'zabbix[queue,<from>,<to>]' => [
				'description' => _('Number of items in the queue which are delayed by from to seconds, inclusive.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#queue'
				]
			],
			'zabbix[rcache,<cache>,<mode>]' => [
				'description' => _('Configuration cache statistics. Cache - buffer (modes: pfree, total, used, free).'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#rcache'
				]
			],
			'zabbix[requiredperformance]' => [
				'description' => _('Required performance of the Zabbix server, in new values per second expected.'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#required.performance'
				]
			],
			'zabbix[stats,<ip>,<port>,queue,<from>,<to>]' => [
				'description' => _('Number of items in the queue which are delayed in Zabbix server or proxy by "from" till "to" seconds, inclusive.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#stats.queue'
				]
			],
			'zabbix[stats,<ip>,<port>]' => [
				'description' => _('Returns a JSON object containing Zabbix server or proxy internal metrics.'),
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#stats'
				]
			],
			'zabbix[tcache, cache, <parameter>]' => [
				'description' => _('Trend function cache statistics. Valid parameters are: all, hits, phits, misses, pmisses, items, pitems and requests.'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#tcache'
				]
			],
			'zabbix[triggers]' => [
				'description' => _('Number of triggers in Zabbix database.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#triggers'
				]
			],
			'zabbix[uptime]' => [
				'description' => _('Uptime of Zabbix server process in seconds.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#uptime'
				]
			],
			'zabbix[vcache,buffer,<mode>]' => [
				'description' => _('Value cache statistics. Valid modes are: total, free, pfree, used and pused.'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#vcache'
				]
			],
			'zabbix[vcache,cache,<parameter>]' => [
				'description' => _('Value cache effectiveness. Valid parameters are: requests, hits and misses.'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#vcache.parameter'
				]
			],
			'zabbix[version]' => [
				'description' => _('Version of Zabbix server or proxy'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#version'
				]
			],
			'zabbix[vmware,buffer,<mode>]' => [
				'description' => _('VMware cache statistics. Valid modes are: total, free, pfree, used and pused.'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#vmware'
				]
			],
			'zabbix[wcache,<cache>,<mode>]' => [
				'description' => _('Statistics and availability of Zabbix write cache. Cache - one of values (modes: all, float, uint, str, log, text, not supported), history (modes: pfree, free, total, used, pused), index (modes: pfree, free, total, used, pused), trend (modes: pfree, free, total, used, pused).'),
				'value_type' => null,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal#wcache'
				]
			],
			'zabbix[proxy_buffer,buffer,<mode>]' => [
				'description' => _('Statistics and availability of proxy memory buffer. Mode (modes: pfree, free, total, used, pused).'),
				'value_type' => ITEM_VALUE_TYPE_FLOAT,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal'
				]
			],
			'zabbix[proxy_buffer,state,current]' => [
				'description' => _('State of proxy memory buffer.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal'
				]
			],
			'zabbix[proxy_buffer,state,changes]' => [
				'description' => _('Returns number of state changes from disk/memory mode since start. Frequent state changes indicates that either memory buffer size or age must be increased.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal'
				]
			],
			'zabbix[vps,written]' => [
				'description' => _('Returns total number of history values written to database.'),
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'documentation_link' => [
					ITEM_TYPE_INTERNAL => 'config/items/itemtypes/internal'
				]
			]
		];
	}
}
