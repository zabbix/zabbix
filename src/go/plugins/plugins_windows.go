/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

package plugins

import (
	_ "golang.zabbix.com/agent2/plugins/ceph"
	_ "golang.zabbix.com/agent2/plugins/log"
	_ "golang.zabbix.com/agent2/plugins/memcached"
	_ "golang.zabbix.com/agent2/plugins/modbus"
	_ "golang.zabbix.com/agent2/plugins/mqtt"
	_ "golang.zabbix.com/agent2/plugins/mysql"
	_ "golang.zabbix.com/agent2/plugins/net/dns"
	_ "golang.zabbix.com/agent2/plugins/net/netif"
	_ "golang.zabbix.com/agent2/plugins/net/tcp"
	_ "golang.zabbix.com/agent2/plugins/net/udp"
	_ "golang.zabbix.com/agent2/plugins/oracle"
	_ "golang.zabbix.com/agent2/plugins/proc"
	_ "golang.zabbix.com/agent2/plugins/redis"
	_ "golang.zabbix.com/agent2/plugins/smart"
	_ "golang.zabbix.com/agent2/plugins/system/cpu"
	_ "golang.zabbix.com/agent2/plugins/system/sw"
	_ "golang.zabbix.com/agent2/plugins/system/swap"
	_ "golang.zabbix.com/agent2/plugins/system/uname"
	_ "golang.zabbix.com/agent2/plugins/system/uptime"
	_ "golang.zabbix.com/agent2/plugins/system/users"
	_ "golang.zabbix.com/agent2/plugins/systemrun"
	_ "golang.zabbix.com/agent2/plugins/vfs/dir"
	_ "golang.zabbix.com/agent2/plugins/vfs/file"
	_ "golang.zabbix.com/agent2/plugins/vfs/fs"
	_ "golang.zabbix.com/agent2/plugins/vm/memory"
	_ "golang.zabbix.com/agent2/plugins/vm/vmemory"
	_ "golang.zabbix.com/agent2/plugins/web/certificate"
	_ "golang.zabbix.com/agent2/plugins/web/page"
	_ "golang.zabbix.com/agent2/plugins/windows/eventlog"
	_ "golang.zabbix.com/agent2/plugins/windows/perfinstance"
	_ "golang.zabbix.com/agent2/plugins/windows/perfmon"
	_ "golang.zabbix.com/agent2/plugins/windows/registry"
	_ "golang.zabbix.com/agent2/plugins/windows/services"
	_ "golang.zabbix.com/agent2/plugins/windows/wmi"
	_ "golang.zabbix.com/agent2/plugins/zabbix/async"
	_ "golang.zabbix.com/agent2/plugins/zabbix/stats"
	_ "golang.zabbix.com/agent2/plugins/zabbix/sync"
)
