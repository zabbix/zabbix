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

#include "common_internal.h"

static zbx_log_func_t		log_func_callback = NULL;
static zbx_get_progname_f	get_progname_cb = NULL;

zbx_log_func_t	common_get_log_func(void)
{
	return log_func_callback;
}

zbx_get_progname_f	common_get_progname(void)
{
	return get_progname_cb;
}

void	zbx_init_library_common(zbx_log_func_t log_func, zbx_get_progname_f get_progname)
{
	log_func_callback = log_func;
	get_progname_cb = get_progname;
}

