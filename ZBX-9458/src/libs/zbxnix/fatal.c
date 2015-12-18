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

#include "config.h"

#ifdef	HAVE_SIGNAL_H
#	define	_GNU_SOURCE /* required for getting at program counter */
#	include <signal.h>
#endif

#ifdef	HAVE_SYS_UCONTEXT_H
#	define	_GNU_SOURCE /* required for getting at program counter */
#	include <sys/ucontext.h>
#endif

#ifdef	HAVE_EXECINFO_H
#	include <execinfo.h>
#endif

#include "common.h"
#include "log.h"

#include "fatal.h"

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
		case SIGUSR1:	return "SIGUSR1";
		default:	return "unknown";
	}
}

static const char	*get_register_name(int reg)
{
	switch (reg)
	{

/* i386 */

#ifdef	REG_GS
		case REG_GS:	return "gs";
#endif
#ifdef	REG_FS
		case REG_FS:	return "fs";
#endif
#ifdef	REG_ES
		case REG_ES:	return "es";
#endif
#ifdef	REG_DS
		case REG_DS:	return "ds";
#endif
#ifdef	REG_EDI
		case REG_EDI:	return "edi";
#endif
#ifdef	REG_ESI
		case REG_ESI:	return "esi";
#endif
#ifdef	REG_EBP
		case REG_EBP:	return "ebp";
#endif
#ifdef	REG_ESP
		case REG_ESP:	return "esp";
#endif
#ifdef	REG_EBX
		case REG_EBX:	return "ebx";
#endif
#ifdef	REG_EDX
		case REG_EDX:	return "edx";
#endif
#ifdef	REG_ECX
		case REG_ECX:	return "ecx";
#endif
#ifdef	REG_EAX
		case REG_EAX:	return "eax";
#endif
#ifdef	REG_EIP
		case REG_EIP:	return "eip";
#endif
#ifdef	REG_CS
		case REG_CS:	return "cs";
#endif
#ifdef	REG_UESP
		case REG_UESP:	return "uesp";
#endif
#ifdef	REG_SS
		case REG_SS:	return "ss";
#endif

/* x86_64 */

#ifdef	REG_R8
		case REG_R8:	return "r8";
#endif
#ifdef	REG_R9
		case REG_R9:	return "r9";
#endif
#ifdef	REG_R10
		case REG_R10:	return "r10";
#endif
#ifdef	REG_R11
		case REG_R11:	return "r11";
#endif
#ifdef	REG_R12
		case REG_R12:	return "r12";
#endif
#ifdef	REG_R13
		case REG_R13:	return "r13";
#endif
#ifdef	REG_R14
		case REG_R14:	return "r14";
#endif
#ifdef	REG_R15
		case REG_R15:	return "r15";
#endif
#ifdef	REG_RDI
		case REG_RDI:	return "rdi";
#endif
#ifdef	REG_RSI
		case REG_RSI:	return "rsi";
#endif
#ifdef	REG_RBP
		case REG_RBP:	return "rbp";
#endif
#ifdef	REG_RBX
		case REG_RBX:	return "rbx";
#endif
#ifdef	REG_RDX
		case REG_RDX:	return "rdx";
#endif
#ifdef	REG_RAX
		case REG_RAX:	return "rax";
#endif
#ifdef	REG_RCX
		case REG_RCX:	return "rcx";
#endif
#ifdef	REG_RSP
		case REG_RSP:	return "rsp";
#endif
#ifdef	REG_RIP
		case REG_RIP:	return "rip";
#endif
#ifdef	REG_CSGSFS
		case REG_CSGSFS:	return "csgsfs";
#endif
#ifdef	REG_OLDMASK
		case REG_OLDMASK:	return "oldmask";
#endif
#ifdef	REG_CR2
		case REG_CR2:	return "cr2";
#endif

/* i386 and x86_64 */

#ifdef	REG_EFL
		case REG_EFL:	return "efl";
#endif
#ifdef	REG_ERR
		case REG_ERR:	return "err";
#endif
#ifdef	REG_TRAPNO
		case REG_TRAPNO:	return "trapno";
#endif

/* unknown */

		default:	return "unknown";
	}
}

void	print_fatal_info(int sig, siginfo_t *siginfo, void *context)
{
#ifdef	HAVE_SYS_UCONTEXT_H

	ucontext_t	*uctx = (ucontext_t *)context;

	/* look for GET_PC() macro in sigcontextinfo.h files */
	/* of glibc if you wish to add more CPU architectures */

#	if	defined(REG_EIP)	/* i386 */

#		define ZBX_GET_REG(uctx, reg)	(uctx)->uc_mcontext.gregs[reg]
#		define ZBX_GET_PC(uctx)		ZBX_GET_REG(uctx, REG_EIP)

#	elif	defined(REG_RIP)	/* x86_64 */

#		define ZBX_GET_REG(uctx, reg)	(uctx)->uc_mcontext.gregs[reg]
#		define ZBX_GET_PC(uctx)		ZBX_GET_REG(uctx, REG_RIP)

#	endif

#endif	/* HAVE_SYS_UCONTEXT_H */

#ifdef	HAVE_EXECINFO_H

#	define	ZBX_BACKTRACE_SIZE	60

	char	**bcktrc_syms;
	void	*bcktrc[ZBX_BACKTRACE_SIZE];
	int	bcktrc_sz;

#endif	/* HAVE_EXECINFO_H */

	int	i;
	FILE	*fd;

	zabbix_log(LOG_LEVEL_CRIT, "====== Fatal information: ======");

#ifdef	HAVE_SYS_UCONTEXT_H

#ifdef	ZBX_GET_PC
	zabbix_log(LOG_LEVEL_CRIT, "Program counter: %p", ZBX_GET_PC(uctx));
	zabbix_log(LOG_LEVEL_CRIT, "=== Registers: ===");
	for (i = 0; i < NGREG; i++)
		zabbix_log(LOG_LEVEL_CRIT, "%-7s = %16lx = %20lu = %20ld", get_register_name(i),
				ZBX_GET_REG(uctx, i), ZBX_GET_REG(uctx, i), ZBX_GET_REG(uctx, i));

#ifdef	REG_EBP	/* dump a bit of stack frame for i386 */
	zabbix_log(LOG_LEVEL_CRIT, "=== Stack frame: ===");
	for (i = 16; i >= 2; i--)
		zabbix_log(LOG_LEVEL_CRIT, "+0x%02x(%%ebp) = ebp + %2d = %08x = %10u = %11d%s",
				i * ZBX_PTR_SIZE, i * ZBX_PTR_SIZE,
				*(unsigned int *)((void *)ZBX_GET_REG(uctx, REG_EBP) + i * ZBX_PTR_SIZE),
				*(unsigned int *)((void *)ZBX_GET_REG(uctx, REG_EBP) + i * ZBX_PTR_SIZE),
				*(unsigned int *)((void *)ZBX_GET_REG(uctx, REG_EBP) + i * ZBX_PTR_SIZE),
				i == 2 ? " <--- call arguments" : "");
	zabbix_log(LOG_LEVEL_CRIT, "+0x%02x(%%ebp) = ebp + %2d = %08x%28s<--- return address",
				ZBX_PTR_SIZE, ZBX_PTR_SIZE,
				*(unsigned int *)((void *)ZBX_GET_REG(uctx, REG_EBP) + ZBX_PTR_SIZE), "");
	zabbix_log(LOG_LEVEL_CRIT, "     (%%ebp) = ebp      = %08x%28s<--- saved ebp value",
				*(unsigned int *)((void *)ZBX_GET_REG(uctx, REG_EBP)), "");
	for (i = 1; i <= 16; i++)
		zabbix_log(LOG_LEVEL_CRIT, "-0x%02x(%%ebp) = ebp - %2d = %08x = %10u = %11d%s",
				i * ZBX_PTR_SIZE, i * ZBX_PTR_SIZE,
				*(unsigned int *)((void *)ZBX_GET_REG(uctx, REG_EBP) - i * ZBX_PTR_SIZE),
				*(unsigned int *)((void *)ZBX_GET_REG(uctx, REG_EBP) - i * ZBX_PTR_SIZE),
				*(unsigned int *)((void *)ZBX_GET_REG(uctx, REG_EBP) - i * ZBX_PTR_SIZE),
				i == 1 ? " <--- local variables" : "");
#endif	/* REG_EBP */

#else
	zabbix_log(LOG_LEVEL_CRIT, "program counter not available for this architecture");
	zabbix_log(LOG_LEVEL_CRIT, "=== Registers: ===");
	zabbix_log(LOG_LEVEL_CRIT, "register dump not available for this architecture");
#endif	/* ZBX_GET_PC */

#endif	/* HAVE_SYS_UCONTEXT_H */

	zabbix_log(LOG_LEVEL_CRIT, "=== Backtrace: ===");

#ifdef	HAVE_EXECINFO_H

	bcktrc_sz = backtrace(bcktrc, ZBX_BACKTRACE_SIZE);
	bcktrc_syms = backtrace_symbols(bcktrc, bcktrc_sz);

	if (NULL == bcktrc_syms)
	{
		zabbix_log(LOG_LEVEL_CRIT, "error in backtrace_symbols(): %s", zbx_strerror(errno));

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

#ifdef	ZBX_GET_PC
	zabbix_log(LOG_LEVEL_CRIT, "================================");
	zabbix_log(LOG_LEVEL_CRIT, "Please consider attaching a disassembly listing to your bug report.");
	zabbix_log(LOG_LEVEL_CRIT, "This listing can be produced with, e.g., objdump -DSswx %s.", progname);
#endif

	zabbix_log(LOG_LEVEL_CRIT, "================================");
}
