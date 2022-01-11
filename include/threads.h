/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#ifndef ZABBIX_THREADS_H
#define ZABBIX_THREADS_H

#include "common.h"

#if defined(_WINDOWS) || defined(__MINGW32__)
	/* the ZBXEndThread function is implemented in service.c file */
	void	CALLBACK ZBXEndThread(ULONG_PTR dwParam);

	#define ZBX_THREAD_ERROR		0

	#define ZBX_THREAD_HANDLE		HANDLE
	#define ZBX_THREAD_HANDLE_NULL		NULL

	#define ZBX_THREAD_PRIORITY_NONE	0

	#define ZBX_THREAD_ENTRY_POINTER(pointer_name) \
		unsigned (__stdcall *pointer_name)(void *)

	#define ZBX_THREAD_ENTRY(entry_name, arg_name)	\
		unsigned __stdcall entry_name(void *arg_name)

	#define zbx_thread_exit(status) \
		_endthreadex((unsigned int)(status)); \
		return ((unsigned)(status))

	#define zbx_sleep(sec) SleepEx(((DWORD)(sec)) * ((DWORD)1000), TRUE)

	#define zbx_thread_kill(h) QueueUserAPC(ZBXEndThread, h, 0)
	#define zbx_thread_kill_fatal(h) QueueUserAPC(ZBXEndThread, h, 0)
#else	/* not _WINDOWS */

	int	zbx_fork(void);
	void	zbx_child_fork(pid_t *pid);

	#define ZBX_THREAD_ERROR		-1

	#define ZBX_THREAD_HANDLE		pid_t
	#define ZBX_THREAD_HANDLE_NULL		0

	#define ZBX_THREAD_PRIORITY_NONE	0
	#define ZBX_THREAD_PRIORITY_FIRST	1
	#define ZBX_THREAD_PRIORITY_SECOND	2
	#define ZBX_THREAD_PRIORITY_COUNT	3

	#define ZBX_THREAD_ENTRY_POINTER(pointer_name) \
		unsigned (* pointer_name)(void *)

	#define ZBX_THREAD_ENTRY(entry_name, arg_name)	\
		unsigned entry_name(void *arg_name)

	/* Calling _exit() to terminate child process immediately is important. See ZBX-5732 for details. */
	#define zbx_thread_exit(status) \
		_exit((int)(status)); \
		return ((unsigned)(status))

	#define zbx_sleep(sec) sleep((sec))

	#define zbx_thread_kill(h) kill(h, SIGUSR2)
	#define zbx_thread_kill_fatal(h) kill(h, SIGHUP)
#endif	/* _WINDOWS */

typedef struct
{
	int		server_num;
	int		process_num;
	unsigned char	process_type;
	void		*args;
#if defined(_WINDOWS) || defined(__MINGW32__)
	ZBX_THREAD_ENTRY_POINTER(entry);
#endif
}
zbx_thread_args_t;

void	zbx_thread_start(ZBX_THREAD_ENTRY_POINTER(handler), zbx_thread_args_t *thread_args, ZBX_THREAD_HANDLE *thread);
int	zbx_thread_wait(ZBX_THREAD_HANDLE thread);
void			zbx_threads_wait(ZBX_THREAD_HANDLE *threads, const int *threads_flags, int threads_num, int ret);
/* zbx_thread_exit(status) -- declared as define !!! */
long int		zbx_get_thread_id(void);

#endif	/* ZABBIX_THREADS_H */
