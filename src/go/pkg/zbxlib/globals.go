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
package zbxlib

/* cspell:disable */

/*
#include "zbxsysinfo.h"

ZBX_GET_CONFIG_VAR(int, config_timeout, 3)
ZBX_GET_CONFIG_VAR2(ZBX_THREAD_LOCAL char *, const char *, config_hostname, NULL)
ZBX_GET_CONFIG_VAR2(char *, const char *, config_hostnames, NULL)
ZBX_GET_CONFIG_VAR(int, config_enable_remote_commands, 1)
ZBX_GET_CONFIG_VAR(int, config_log_remote_commands, 0)
ZBX_GET_CONFIG_VAR(int, config_unsafe_user_parameters, 0)
ZBX_GET_CONFIG_VAR2(char *, const char *, config_source_ip, NULL)
ZBX_GET_CONFIG_VAR2(char *, const char *, config_host_metadata, NULL)
ZBX_GET_CONFIG_VAR2(char *, const char *, config_host_metadata_item, NULL)
ZBX_GET_CONFIG_VAR2(char *, const char *, config_service_name, NULL)

void	init_globals(void)
{
	zbx_init_library_sysinfo(get_config_timeout, get_config_enable_remote_commands,
			get_config_log_remote_commands, get_config_unsafe_user_parameters, get_config_source_ip,
			get_config_hostname, get_config_hostnames, get_config_host_metadata,
			get_config_host_metadata_item, get_config_service_name);
}
*/
import "C"

import (
	"golang.zabbix.com/sdk/log"
)

const (
	ItemStateNormal       = 0
	ItemStateNotsupported = 1
)

const (
	Succeed = 0
	Fail    = -1
)

func init() {
	log.Tracef("Calling C function \"init_globals()\"")
	C.init_globals()
}
