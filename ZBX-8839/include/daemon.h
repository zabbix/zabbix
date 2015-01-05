/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#if defined(_WINDOWS)
#	error "This module allowed only for Unix OS"
#endif

#define USE_PID_FILE	1

extern char	*CONFIG_PID_FILE;

#include "threads.h"

int	daemon_start(int allow_root);
void	daemon_stop();

int	zbx_sigusr_send(zbx_task_t task);

#define ZBX_IS_RUNNING()	1
#define ZBX_DO_EXIT()

#define START_MAIN_ZABBIX_ENTRY(a)	daemon_start(a)

#endif	/* ZABBIX_DAEMON_H */
