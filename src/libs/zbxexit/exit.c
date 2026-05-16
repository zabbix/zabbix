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

#include "zbxexit.h"
#include "zbxtypes_ext.h"

#if !defined(_WINDOWS) && !defined(__MINGW32__)

static ZBX_THREAD_LOCAL void	(*zbx_exit_impl)(int) = exit;
static ZBX_THREAD_LOCAL void	(*zbx_exit_immediate_impl)(int) = _exit;
static ZBX_THREAD_LOCAL zbx_atomic_uint32_t	*zbx_exit_num;

void	zbx_set_exit(zbx_exit_cb_t exit_cb, zbx_atomic_uint32_t *exit_num)
{
	zbx_exit_impl = exit_cb;
	zbx_exit_num = exit_num;
}

void	zbx_set_exit_immediate(zbx_exit_cb_t exit_cb)
{
	zbx_exit_immediate_impl = exit_cb;
}

void	zbx_exit(int ret)
{
	zbx_exit_impl(ret);
	abort();
}

void	zbx_exit_immediate(int ret)
{
	zbx_exit_immediate_impl(ret);
	abort();
}

void	zbx_exit_from_thread(int ret)
{
#if defined(HAVE_STDATOMIC_H)
	if (NULL != zbx_exit_num)
		atomic_fetch_add(zbx_exit_num, 1);
#endif
	pthread_exit((void *)(zbx_int64_t)ret);
}

int	zbx_get_exit_num(void)
{
#if defined(HAVE_STDATOMIC_H)
	if (NULL != zbx_exit_num)
		return atomic_load(zbx_exit_num);
#endif
	return FAIL;
}

#endif
