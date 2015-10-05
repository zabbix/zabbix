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

/*
** Ideas from PostgreSQL implementation (src/backend/utils/misc/ps_status.c)
** were used in development of this file. Thanks to PostgreSQL developers!
**/

#include "common.h"
#include "setproctitle.h"

#if defined(PS_DARWIN_ARGV)
#include <crt_externs.h>
#endif

#if defined(PS_OVERWRITE_ARGV)
/* external environment we got on startup */
extern char	**environ;
static int	argc_ext_copied_first = 0, argc_ext_copied_last = 0, environ_ext_copied = 0;
static char	**argv_ext = NULL, **environ_ext = NULL;

/* internal copy of environment variables */
static char	**environ_int = NULL;

/* ps display buffer */
static char	*ps_buf = NULL;
static size_t	ps_buf_size = 0;
#elif defined(PS_COPY_ARGV)
static char	**argv_int = NULL, **argv_ext = NULL;

/* ps display buffer */
static char	*ps_buf = NULL;
static size_t	ps_buf_size = 0;
#elif defined(PS_PSTAT_ARGV)
#define PS_BUF_SIZE	512
static char	ps_buf[PS_BUF_SIZE], *p_msg = NULL;
static size_t	ps_buf_size = PS_BUF_SIZE, ps_buf_size_msg = PS_BUF_SIZE;
#endif

/******************************************************************************
 *                                                                            *
 * Function: setproctitle_save_env                                            *
 *                                                                            *
 * Purpose: prepare for changing process commandline to display status        *
 *          messages with "ps" command on platforms which do not support      *
 *          setproctitle(). Depending on platform:                            *
 *             - make a copy of argc, argv[] and environment variables to     *
 *          enable overwriting original argv[].                               *
 *             - prepare a buffer with common part of status message.         *
 *                                                                            *
 * Comments: call this function soon after main process start, before using   *
 *           argv[] and environment variables.                                *
 *                                                                            *
 ******************************************************************************/
#if defined(PS_OVERWRITE_ARGV)
char **	setproctitle_save_env(int argc, char **argv)
{
	int	i;
	char	*arg_next;

	if (NULL == argv || 0 == argc)
		return NULL;

	/* measure a size of continuous argv[] area and make a copy */

	ps_buf = argv[argc_ext_copied_first];

	for (i = argc_ext_copied_first, arg_next = argv[argc_ext_copied_first]; arg_next == argv[i]; i++)
	{
		arg_next += strlen(argv[i]) + 1;
		argv[i] = zbx_strdup(NULL, argv[i]);
	}

	argc_ext_copied_last = i;

	if (argc_ext_copied_last == argc)
	{
		int	envc = 0;

		while (NULL != environ[envc])
			envc++;

		/* measure a size of continuous environment area and make a copy */

		environ_int = zbx_malloc(environ_int, ((unsigned int)envc + 1) * sizeof(char *));

		for (i = 0; arg_next == environ[i]; i++)
		{
			arg_next += strlen(environ[i]) + 1;
			environ_int[i] = zbx_strdup(NULL, environ[i]);
		}

		environ_ext_copied = i;

		for (;  i < envc; i++)
			environ_int[i] = environ[i];

		environ_int[envc] = NULL;

		environ_ext = environ;
		environ = environ_int;		/* switch environment to internal copy */
	}

	ps_buf_size = (size_t)(arg_next - ps_buf);
#if defined(PS_DARWIN_ARGV)
	*_NSGetArgv() = argv;
#endif
	return argv_ext = argv;
}
#elif defined(PS_COPY_ARGV)
char **	setproctitle_save_env(int argc, char **argv)
{
	char	*p;
	int	i;
	size_t	size, len;

	if (NULL == argv || 0 == argc)
		return argv;

	argv_int = zbx_malloc(argv_int, ((unsigned int)argc + 1) * sizeof(char *));

	for (i = 0; i < argc; i++)
	{
		argv_int[i] = argv[i];
		ps_buf_size += strlen(argv[i]);
	}

	argv_int[argc] = NULL;
	ps_buf_size += argc;
	argv[0] = ps_buf = (char *)zbx_malloc(ps_buf, ps_buf_size);
	argv[1] = NULL;
	argv_ext = argv;
	p = ps_buf;
	size = ps_buf_size;

	for (i = 0; i < argc; i++)
	{
		len = zbx_strlcpy(p, argv_int[i], size);
		p += len;
		size -= len;
		zbx_strlcpy(p++, " ", size--);
	}

	return argv_int;
}
#elif defined(PS_PSTAT_ARGV)
char **	setproctitle_save_env(int argc, char **argv)
{
	size_t	len0;

	len0 = strlen(argv[0]);

	if (len0 + 2 < ps_buf_size)	/* is there space for ": " ? */
	{
		zbx_strlcpy(ps_buf, argv[0], ps_buf_size);
		zbx_strlcpy(ps_buf + len0, ": ", (size_t)3);
		p_msg = ps_buf + len0 + 2;
		ps_buf_size_msg = ps_buf_size - len0 - 2;	/* space after "argv[0]: " for status message */
	}

	return argv;
}
#endif	/* defined(PS_PSTAT_ARGV) */

/******************************************************************************
 *                                                                            *
 * Function: setproctitle_set_status                                          *
 *                                                                            *
 * Purpose: set a process command line displayed by "ps" command.             *
 *                                                                            *
 * Comments: call this function when a process starts some interesting task.  *
 *           Program name argv[0] will be displayed "as-is" followed by ": "  *
 *           and a status message.                                            *
 *                                                                            *
 ******************************************************************************/
void	setproctitle_set_status(const char *status)
{
#if defined(PS_OVERWRITE_ARGV) || defined(PS_COPY_ARGV)
	static int	initialized = 0;
	static size_t	prev_msg_size;

	if (1 == initialized)
	{
		size_t	msg_size;

		msg_size = zbx_strlcpy(ps_buf, status, ps_buf_size);
#ifdef PS_COPY_ARGV
		if (msg_size > ps_buf_size)
		{
			argv_ext[0] = zbx_realloc(argv_ext[0], strlen(argv_int[0]) + msg_size + 3);
			ps_buf = argv_ext[0] + 2 + strlen(argv_int[0]);
			ps_buf_size = msg_size + 1;
			msg_size = zbx_strlcpy(ps_buf, status, ps_buf_size);
		}
#endif
#ifdef PS_PADDING
		memset(ps_buf + msg_size, ' ', ps_buf_size - msg_size);

			if (' ' == ps_buf[ps_buf_size - 2])
				ps_buf[ps_buf_size - 2] = '-';

		ps_buf[ps_buf_size - 1] = '\0';
#endif
		prev_msg_size = msg_size;
	}
	else if (NULL != ps_buf)
	{
		size_t	start_pos;

		/* Initialization has not been moved to setproctitle_save_env() because setproctitle_save_env()	*/
		/* is called from the main process and we do not change its command line.			*/
		/* argv[] changing takes place only in child processes.						*/

#ifdef PS_COPY_ARGV
		start_pos = strlen(argv_int[0]);
#else
		start_pos = strlen(ps_buf);
#endif
		if (start_pos + 2 < ps_buf_size)	/* is there space for ": " ? */
		{
			zbx_strlcpy(ps_buf + start_pos, ": ", (size_t)3);
			ps_buf += start_pos + 2;
			ps_buf_size -= start_pos + 2;	/* space after "argv[0]: " for status message */

			prev_msg_size = zbx_strlcpy(ps_buf, status, ps_buf_size);
#ifdef PS_COPY_ARGV
			if (prev_msg_size > ps_buf_size)
			{
				argv_ext[0] = zbx_realloc(argv_ext[0], strlen(argv_int[0]) + prev_msg_size + 3);
				ps_buf = argv_ext[0] + 2 + strlen(argv_int[0]);
				ps_buf_size = prev_msg_size + 1;
				prev_msg_size = zbx_strlcpy(ps_buf, status, ps_buf_size);
			}
#endif
#ifdef PS_PADDING
			memset(ps_buf + prev_msg_size, ' ', ps_buf_size - prev_msg_size);

			if (' ' == ps_buf[ps_buf_size - 2])
				ps_buf[ps_buf_size - 2] = '-';

			ps_buf[ps_buf_size - 1] = '\0';
#endif
			initialized = 1;
		}
	}
#elif defined(PS_PSTAT_ARGV)
	if (NULL != p_msg)
	{
		union pstun	pst;

		zbx_strlcpy(p_msg, status, ps_buf_size_msg);
		pst.pst_command = ps_buf;
		pstat(PSTAT_SETCMD, pst, strlen(ps_buf), 0, 0);
	}
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: setproctitle_free_env                                            *
 *                                                                            *
 * Purpose: release memory allocated in setproctitle_save_env().              *
 *                                                                            *
 * Comments: call this function when process terminates and argv[] and        *
 *           environment variables are not used anymore.                      *
 *                                                                            *
 ******************************************************************************/
#if defined(PS_OVERWRITE_ARGV)
void	setproctitle_free_env(void)
{
	int	i;

	/* restore the original environment variable to safely free our internally allocated environ array */
	if (environ == environ_int)
		environ = environ_ext;

	for (i = argc_ext_copied_first; i < argc_ext_copied_last; i++)
		zbx_free(argv_ext[i]);

	for (i = 0; i <= environ_ext_copied; i++)
		zbx_free(environ_int[i]);

	zbx_free(environ_int);
}
#elif defined(PS_COPY_ARGV)
void	setproctitle_free_env(void)
{
	zbx_free(argv_int);
	zbx_free(argv_ext[0]);
}
#endif
