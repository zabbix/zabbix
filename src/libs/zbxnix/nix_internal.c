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

#include "nix_internal.h"
#include "zbxnix.h"

#include "zbxcommon.h"

static zbx_get_progname_f		get_progname_func_cb = NULL;
static zbx_get_process_info_by_thread_f	get_process_info_by_thread_func_cb = NULL;

void	zbx_init_library_nix(zbx_get_progname_f get_progname_cb, zbx_get_process_info_by_thread_f
		get_process_info_by_thread_cb)
{
	get_progname_func_cb = get_progname_cb;
	get_process_info_by_thread_func_cb = get_process_info_by_thread_cb;
}

zbx_get_progname_f	nix_get_progname_cb(void)
{
	return get_progname_func_cb;
}

zbx_get_process_info_by_thread_f	nix_get_process_info_by_thread_func_cb(void)
{
	return get_process_info_by_thread_func_cb;
}
