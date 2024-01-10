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

#include "zbxcacheconfig.h"

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num);

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num)
{
	ZBX_UNUSED(local_server_num);
	ZBX_UNUSED(local_process_type);
	ZBX_UNUSED(local_process_num);

	return 0;
}

int	MAIN_ZABBIX_ENTRY(int flags)
{
	ZBX_UNUSED(flags);

	return 0;
}

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
