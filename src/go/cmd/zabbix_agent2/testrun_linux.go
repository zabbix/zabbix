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

import (
	"golang.zabbix.com/agent2/internal/agent/scheduler"
)

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
		`vfs.file.size[/etc/passwd]`,
		`vfs.file.time[/etc/passwd,modify]`,
		`vfs.file.exists[/etc/passwd]`,
		`vfs.file.contents[/etc/passwd]`,
		`vfs.file.regexp[/etc/passwd,root]`,
		`vfs.file.regmatch[/etc/passwd,root]`,
		`vfs.file.md5sum[/etc/passwd]`,
		`vfs.file.cksum[/etc/passwd]`,
		`vfs.file.owner[/etc/passwd]`,
		`vfs.file.permissions[/etc/passwd]`,
		`vfs.file.get[/etc/passwd]`,
		`vfs.dir.size[/var/log]`,
		`vfs.dir.count[/var/log]`,
		`vfs.dir.get[/var/log]`,
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
		`net.udp.listen[68]`,
		`net.udp.service[ntp,127.0.0.1,123]`,
		`net.udp.service.perf[ntp,127.0.0.1,123]`,
		`net.udp.socket.count[,53]`,
		`system.users.num`,
		`log[logfile]`,
		`log.count[logfile]`,
		`logrt[logfile]`,
		`logrt.count[logfile]`,
		`zabbix.stats[127.0.0.1,10051]`,
		`kernel.maxfiles`,
		`kernel.maxproc`,
		`kernel.openfiles`,
		`vfs.fs.size[/,free]`,
		`vfs.fs.inode[/,free]`,
		`vfs.fs.discovery`,
		`vfs.fs.get`,
		`vfs.dev.write[sda,operations]`,
		`net.if.in[lo,bytes]`,
		`net.if.out[lo,bytes]`,
		`net.if.total[lo,bytes]`,
		`net.if.collisions[lo]`,
		`net.if.discovery`,
		`vm.memory.size[total]`,
		`proc.cpu.util[inetd]`,
		`proc.num[inetd]`,
		`proc.mem[inetd]`,
		`system.cpu.switches`,
		`system.cpu.intr`,
		`system.cpu.util[all,user,avg1]`,
		`system.cpu.load[all,avg1]`,
		`system.cpu.num[online]`,
		`system.cpu.discovery`,
		`system.uname`,
		`system.hw.chassis`,
		`system.hw.cpu`,
		`system.hw.devices`,
		`system.hw.macaddr`,
		`system.sw.arch`,
		`system.sw.os`,
		`system.sw.packages`,
		`system.swap.size[all,free]`,
		`system.swap.in[all]`,
		`system.swap.out[all]`,
		`system.uptime`,
		`system.boottime`,
		`sensor[w83781d-i2c-0-2d,temp1]`,
		`modbus.get[tcp://localhost]`,
		`system.hostname`,
	}

	for _, metric := range metrics {
		checkMetric(s, metric)
	}
}
