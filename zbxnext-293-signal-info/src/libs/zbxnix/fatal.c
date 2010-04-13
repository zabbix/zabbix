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

#include "config.h"

#ifdef	HAVE_SYS_UCONTEXT_H
#	define	_GNU_SOURCE /* required for getting at program counter */
#	include <sys/ucontext.h>
#endif

#ifdef	HAVE_EXECINFO_H
#	include <execinfo.h>
#endif

#include "common.h"
#include "log.h"

const char	*get_signal_name(int sig)
{
	/* either strsignal() or sys_siglist[] could be used to */
	/* get signal name, but these methods are not portable */

	/* not all POSIX signals are available on all platforms, */
	/* so we list only signals that we react to in our handlers */

	switch (sig)
	{
		case SIGALRM:	return "SIGALRM";
		case SIGILL:	return "SIGILL";
		case SIGFPE:	return "SIGFPE";
		case SIGSEGV:	return "SIGSEGV";
		case SIGBUS:	return "SIGBUS";
		case SIGQUIT:	return "SIGQUIT";
		case SIGINT:	return "SIGINT";
		case SIGTERM:	return "SIGTERM";
		case SIGPIPE:	return "SIGPIPE";
		default:	return "unknown";
	}
}

void	print_fatal_info(int sig, siginfo_t *siginfo, void *context)
{
#ifdef	HAVE_SYS_UCONTEXT_H

	ucontext_t	*uctx = (ucontext_t *)context;

	/* look for GET_PC() macro in sigcontextinfo.h files */
	/* of glibc if you wish to add more CPU architectures */

#	if	defined(REG_RIP)
#		define ZBX_GET_PC(uctx)	(uctx)->uc_mcontext.gregs[REG_RIP] /* x86_64 */
#	elif	defined(REG_EIP)
#		define ZBX_GET_PC(uctx)	(uctx)->uc_mcontext.gregs[REG_EIP] /* i386 */
#	endif

#endif	/* HAVE_SYS_UCONTEXT_H */

#ifdef	HAVE_EXECINFO_H

#	define	ZBX_BACKTRACE_SIZE	60

	char	**bcktrc_syms;
	void	*bcktrc[ZBX_BACKTRACE_SIZE];
	int	i, bcktrc_sz;

#endif	/* HAVE_EXECINFO_H */

	FILE	*fd;

	zabbix_log(LOG_LEVEL_CRIT, "====== Fatal information: ======");

#ifdef	HAVE_SYS_UCONTEXT_H

#ifdef	ZBX_GET_PC
	zabbix_log(LOG_LEVEL_CRIT, "Program counter: %p", ZBX_GET_PC(uctx));
#else
	zabbix_log(LOG_LEVEL_CRIT, "program counter not available for this architecture");
#endif

#endif	/* HAVE_SYS_UCONTEXT_H */

	zabbix_log(LOG_LEVEL_CRIT, "=== Backtrace: ===");

#ifdef	HAVE_EXECINFO_H

	bcktrc_sz = backtrace(bcktrc, ZBX_BACKTRACE_SIZE);

	bcktrc_syms = backtrace_symbols(bcktrc, bcktrc_sz);

	if (NULL == bcktrc_syms)
	{
		zabbix_log(LOG_LEVEL_CRIT, "error in backtrace_symbols(): [%s]", strerror(errno));

		for (i = 0; i < bcktrc_sz; i++)
			zabbix_log(LOG_LEVEL_CRIT, "%d: %p", bcktrc_sz - i - 1, bcktrc[i]);
	}
	else
	{
		for (i = 0; i < bcktrc_sz; i++)
			zabbix_log(LOG_LEVEL_CRIT, "%d: %s", bcktrc_sz - i - 1, bcktrc_syms[i]);

		zbx_free(bcktrc_syms);
	}
#else
	zabbix_log(LOG_LEVEL_CRIT, "backtrace not available for this platform");

#endif	/* HAVE_EXECINFO_H */

	zabbix_log(LOG_LEVEL_CRIT, "=== Memory map: ===");

	if (NULL != (fd = fopen("/proc/self/maps", "r")))
	{
		char line[1024];

		while (NULL != fgets(line, sizeof(line), fd))
		{
			if (line[0] != '\0')
				line[strlen(line) - 1] = '\0'; /* remove trailing '\n' */

			zabbix_log(LOG_LEVEL_CRIT, "%s", line);
		}

		zbx_fclose(fd);
	}
	else
		zabbix_log(LOG_LEVEL_CRIT, "memory map not available for this platform");

	zabbix_log(LOG_LEVEL_CRIT, "================================");
}
