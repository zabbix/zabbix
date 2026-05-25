/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#ifndef ZABBIX_EXIT_H
#define ZABBIX_EXIT_H

#include "zbxcommon.h"
#include "zbxtypes_ext.h"

#if !defined(_WINDOWS) && !defined(__MINGW32__)

typedef void (*zbx_exit_cb_t)(int) ZBX_NORETURN;

void	zbx_exit(int ret) ZBX_NORETURN;
void	zbx_exit_immediate(int ret) ZBX_NORETURN;

void	zbx_set_exit(zbx_exit_cb_t exit_cb, zbx_atomic_uint32_t *exit_num);
void	zbx_set_exit_immediate(zbx_exit_cb_t exit_cb);

void	zbx_exit_from_thread(int ret) ZBX_NORETURN;
int	zbx_get_exit_num(void);

#else
#	define zbx_exit(status)		exit(status)
#	define zbx_exit_immediate(status)	_exit(status)
#endif

#endif
