/*
** Copyright (C) 2001-2025 Zabbix SIA
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

package main

import "golang.zabbix.com/agent2/internal/agent/scheduler"

func checkMetrics(s scheduler.Scheduler) {
	metrics := []string{
		`agent.hostname`,
		`agent.ping`,
		`agent.variant`,
		`agent.version`,
		`system.localtime[utc]`,
		`system.run[echo test]`,
		`web.page.get[localhost,,80]`,
		`web.page.perf[localhost,,80]`,
		`web.page.regexp[localhost,,80,OK]`,
		`vfs.file.size[c:\windows\win.ini]`,
		`vfs.file.time[c:\windows\win.ini,modify]`,
		`vfs.file.exists[c:\windows\win.ini]`,
		`vfs.file.contents[c:\windows\win.ini]`,
		`vfs.file.regexp[c:\windows\win.ini,fonts]`,
		`vfs.file.regmatch[c:\windows\win.ini,fonts]`,
		`vfs.file.md5sum[c:\windows\win.ini]`,
		`vfs.file.cksum[c:\windows\win.ini]`,
		`vfs.file.owner[c:\windows\win.ini]`,
		`vfs.file.permissions[c:\windows\win.ini]`,
		`vfs.file.get[c:\windows\win.ini]`,
		`vfs.dir.size[c:\windows]`,
		`vfs.dir.count[c:\windows]`,
		`vfs.dir.get[c:\windows]`,
		`net.dns[,zabbix.com]`,
		`net.dns.record[,zabbix.com]`,
		`net.dns.perf[,zabbix.com]`,
		`net.dns.get[,zabbix.com]`,
		`net.tcp.dns[,zabbix.com]`,
		`net.tcp.dns.query[,zabbix.com]`,
		`net.tcp.port[,80]`,
		`net.tcp.listen[80]`,
		`net.tcp.service[ssh,127.0.0.1,22]`,
		`net.tcp.service.perf[ssh,127.0.0.1,22]`,
		`net.tcp.socket.count[,80]`,
		`net.udp.service[ntp,127.0.0.1,123]`,
		`net.udp.service.perf[ntp,127.0.0.1,123]`,
		`net.udp.socket.count[,53]`,
		`system.users.num`,
		`log[logfile]`,
		`log.count[logfile]`,
		`logrt[logfile]`,
		`logrt.count[logfile]`,
		`eventlog[system]`,
		`zabbix.stats[127.0.0.1,10051]`,
		`vfs.fs.size[c:,free]`,
		`vfs.fs.discovery`,
		`vfs.fs.get`,
		`net.if.in[MS TCP Loopback interface,bytes]`,
		`net.if.out[MS TCP Loopback interface,bytes]`,
		`net.if.total[MS TCP Loopback interface,bytes]`,
		`net.if.discovery`,
		`net.if.list`,
		`vm.memory.size[free]`,
		`proc.num[svchost.exe]`,
		`system.cpu.util[all,system,avg1]`,
		`system.cpu.load[all,avg1]`,
		`system.cpu.num[online]`,
		`system.cpu.discovery`,
		`system.sw.arch`,
		`system.swap.size[all,free]`,
		`vm.vmemory.size[total]`,
		`system.uptime`,
		`system.uname`,
		`service.discovery`,
		`service.info[Zabbix Agent]`,
		`service_state[Zabbix Agent]`,
		`services`,
		`perf_counter[\System\Processes]`,
		`perf_counter_en[\System\Processes]`,
		`proc_info[svchost.exe]`,
		`wmi.get[root\cimv2,select Caption from Win32_OperatingSystem]`,
		`wmi.getall[root\cimv2,select * from Win32_OperatingSystem]`,
		`perf_instance.discovery[Processor]`,
		`perf_instance_en.discovery[Processor]`,
		`modbus.get[tcp://localhost]`,
		`system.hostname`,
	}

	for _, metric := range metrics {
		checkMetric(s, metric)
	}
}
