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

#ifndef ZABBIX_SIGCOMMON_H
#define ZABBIX_SIGCOMMON_H

void	set_sig_parent_pid(int in);
int	get_sig_parent_pid(void);

#define SIG_CHECKED_FIELD(siginfo, field)		(NULL == siginfo ? -1 : (int)siginfo->field)
#define SIG_CHECKED_FIELD_TYPE(siginfo, field, type)	(NULL == siginfo ? (type)-1 : siginfo->field)
#define SIG_PARENT_PROCESS				(get_sig_parent_pid() == (int)getpid())

#define SIG_CHECK_PARAMS(sig, siginfo, context)											\
		if (NULL == siginfo)												\
			zabbix_log(LOG_LEVEL_DEBUG, "received [signal:%d(%s)] with NULL siginfo", sig, get_signal_name(sig));	\
		if (NULL == context)												\
			zabbix_log(LOG_LEVEL_DEBUG, "received [signal:%d(%s)] with NULL context", sig, get_signal_name(sig))

#endif	/* ZABBIX_SIGCOMMON_H */
