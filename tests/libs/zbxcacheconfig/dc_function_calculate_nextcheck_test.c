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

#include "zbxcacheconfig.h"

#include "../../src/libs/zbxcacheconfig/dbconfig.c"
/* since zbx_dc_um_handle_t is defined in source file and not accessible */
/* define mock handle with the same structure                            */
typedef struct
{
	zbx_dc_um_handle_t	*prev;
	zbx_um_cache_t		**cache;
	unsigned char		macro_env;
}
zbx_dc_um_handle_mock_t;

int	zbx_dc_function_calculate_nextcheck(const zbx_trigger_timer_t *timer, time_t from, zbx_uint64_t seed);

int	zbx_dc_function_calculate_nextcheck(const zbx_trigger_timer_t *timer, time_t from, zbx_uint64_t seed)
{
	/* note, the test must not have user macros in function period parameter */

	zbx_dc_um_handle_mock_t	um_handle_mock = {.macro_env = ZBX_MACRO_ENV_NONSECURE};

	return dc_function_calculate_nextcheck((zbx_dc_um_handle_t *)&um_handle_mock, timer, from, seed);
}
