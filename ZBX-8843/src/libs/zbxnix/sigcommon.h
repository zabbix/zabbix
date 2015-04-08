/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#ifndef ZABBIX_SIGCOMMON_H
#define ZABBIX_SIGCOMMON_H

extern int	sig_parent_pid;
extern int	sig_exiting;

#define SIG_CHECKED_FIELD(siginfo, field)		(NULL == siginfo ? -1 : siginfo->field)
#define SIG_CHECKED_FIELD_TYPE(siginfo, field, type)	(NULL == siginfo ? (type)-1 : siginfo->field)
#define SIG_PARENT_PROCESS				(sig_parent_pid == (int)getpid())

#define SIG_CHECK_PARAMS(sig, siginfo, context)											\
		if (NULL == siginfo)												\
			zabbix_log(LOG_LEVEL_DEBUG, "received [signal:%d(%s)] with NULL siginfo", sig, get_signal_name(sig));	\
		if (NULL == context)												\
			zabbix_log(LOG_LEVEL_DEBUG, "received [signal:%d(%s)] with NULL context", sig, get_signal_name(sig))

#endif	/* ZABBIX_SIGCOMMON_H */
