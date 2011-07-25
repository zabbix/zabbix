/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_THREADS_H
#define ZABBIX_THREADS_H

#include "common.h"

#if defined(_WINDOWS)

	#define ZBX_THREAD_ERROR	0

	#define ZBX_THREAD_HANDLE	HANDLE
	#define ZBX_THREAD_HANDLE_NULL	NULL

	#define ZBX_THREAD_ENTRY_POINTER(pointer_name) \
		unsigned (__stdcall *pointer_name)(void *)

	#define ZBX_THREAD_ENTRY(entry_name, arg_name)	\
		unsigned __stdcall entry_name(void *arg_name)

	#define zbx_thread_exit(status) \
		_endthreadex((unsigned int)(status)); \
		return ((unsigned)(status))

	#define zbx_sleep(sec) Sleep(((DWORD)(sec)) * ((DWORD)1000))

	#define zbx_thread_kill(h) TerminateThread(h, SUCCEED);

#else	/* not _WINDOWS */

	int	zbx_fork();
	int	zbx_child_fork();

	#define ZBX_THREAD_ERROR	-1

	#define ZBX_THREAD_HANDLE	pid_t
	#define ZBX_THREAD_HANDLE_NULL	0

	#define ZBX_THREAD_ENTRY_POINTER(pointer_name) \
		unsigned (* pointer_name)(void *)

	#define ZBX_THREAD_ENTRY(entry_name, arg_name)	\
		unsigned entry_name(void *arg_name)

	#define zbx_thread_exit(status) \
		exit((int)(status)); \
		return ((unsigned)(status))

	#define zbx_sleep(sec) sleep((sec))

	#define zbx_thread_kill(h) kill(h, SIGTERM);

#endif	/* _WINDOWS */

typedef struct
{
	int	thread_num;
	void	*args;
}
zbx_thread_args_t;

ZBX_THREAD_HANDLE	zbx_thread_start(ZBX_THREAD_ENTRY_POINTER(handler), zbx_thread_args_t *thread_args);
int			zbx_thread_wait(ZBX_THREAD_HANDLE thread);
/* zbx_thread_exit(status) -- declared as define !!! */
long int		zbx_get_thread_id();

#endif	/* ZABBIX_THREADS_H */
