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

int	__wrap_zbx_dc_expand_user_and_func_macros_from_cache(zbx_um_cache_t *um_cache, char **text,
		const zbx_uint64_t *hostids, int hostids_num, unsigned char env, char **error);

int	__wrap_zbx_dc_expand_user_and_func_macros_from_cache(zbx_um_cache_t *um_cache, char **text,
		const zbx_uint64_t *hostids, int hostids_num, unsigned char env, char **error)
{
	ZBX_UNUSED(um_cache);
	ZBX_UNUSED(text);
	ZBX_UNUSED(hostids);
	ZBX_UNUSED(hostids_num);
	ZBX_UNUSED(env);
	ZBX_UNUSED(error);

	return SUCCEED;
}
