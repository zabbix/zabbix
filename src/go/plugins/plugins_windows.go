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

package plugins

import (
	_ "zabbix.com/plugins/ceph"
	_ "zabbix.com/plugins/log"
	_ "zabbix.com/plugins/memcached"
	_ "zabbix.com/plugins/modbus"
	_ "zabbix.com/plugins/mqtt"
	_ "zabbix.com/plugins/mysql"
	_ "zabbix.com/plugins/net/dns"
	_ "zabbix.com/plugins/net/netif"
	_ "zabbix.com/plugins/net/tcp"
	_ "zabbix.com/plugins/net/udp"
	_ "zabbix.com/plugins/oracle"
	_ "zabbix.com/plugins/proc"
	_ "zabbix.com/plugins/redis"
	_ "zabbix.com/plugins/smart"
	_ "zabbix.com/plugins/system/cpu"
	_ "zabbix.com/plugins/system/swap"
	_ "zabbix.com/plugins/system/uname"
	_ "zabbix.com/plugins/system/uptime"
	_ "zabbix.com/plugins/system/users"
	_ "zabbix.com/plugins/systemrun"
	_ "zabbix.com/plugins/vfs/dir"
	_ "zabbix.com/plugins/vfs/file"
	_ "zabbix.com/plugins/vfs/fs"
	_ "zabbix.com/plugins/vm/memory"
	_ "zabbix.com/plugins/vm/vmemory"
	_ "zabbix.com/plugins/web/certificate"
	_ "zabbix.com/plugins/web/page"
	_ "zabbix.com/plugins/windows/eventlog"
	_ "zabbix.com/plugins/windows/perfinstance"
	_ "zabbix.com/plugins/windows/perfmon"
	_ "zabbix.com/plugins/windows/registry"
	_ "zabbix.com/plugins/windows/services"
	_ "zabbix.com/plugins/windows/wmi"
	_ "zabbix.com/plugins/zabbix/async"
	_ "zabbix.com/plugins/zabbix/stats"
	_ "zabbix.com/plugins/zabbix/sync"
)
