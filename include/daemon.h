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

#ifndef ZABBIX_DAEMON_H
#define ZABBIX_DAEMON_H

#include "sysinc.h"

#if defined(_WINDOWS)
#	error "This module allowed only for Unix OS"
#endif

extern char			*CONFIG_PID_FILE;
extern volatile sig_atomic_t	sig_exiting;

#define ZBX_EXIT_NONE		0
#define ZBX_EXIT_SUCCESS	1
#define ZBX_EXIT_FAILURE	2

int	daemon_start(int allow_root, const char *user, unsigned int flags);
void	daemon_stop(void);

int	zbx_sigusr_send(int flags);
void	zbx_set_sigusr_handler(void (*handler)(int flags));

#define ZBX_IS_RUNNING()	(ZBX_EXIT_NONE == sig_exiting)
#define ZBX_EXIT_STATUS()	(ZBX_EXIT_SUCCESS == sig_exiting ? SUCCEED : FAIL)

#define ZBX_DO_EXIT()

#define START_MAIN_ZABBIX_ENTRY(allow_root, user, flags)	daemon_start(allow_root, user, flags)

void	zbx_signal_process_by_type(int proc_type, int proc_num, int flags, char **out);
void	zbx_signal_process_by_pid(int pid, int flags, char **out);

#endif	/* ZABBIX_DAEMON_H */
