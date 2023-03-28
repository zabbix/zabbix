/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
package zbxlib

/* cspell:disable */

/*
#include "zbxsysinfo.h"

static int	config_timeout = 3;
static int	get_config_timeout(void)
{
	return config_timeout;
}

void	init_globals(void)
{
	zbx_init_library_sysinfo(get_config_timeout);
}
*/
import "C"

import (
	"git.zabbix.com/ap/plugin-support/log"
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
