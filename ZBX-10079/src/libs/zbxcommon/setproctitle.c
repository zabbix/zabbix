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
static char	**environ_ext = NULL;

/* internal copy of argv[] and environment variables */
static char	**argv_int = NULL, **environ_int = NULL;
static char	*empty_str = "";

/* ps display buffer */
static char	*ps_buf = NULL;
static size_t	ps_buf_size = 0, prev_msg_size = 0;
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
char	**setproctitle_save_env(int argc, char **argv)
{
	int	i;
	char	*arg_next = NULL;

	if (NULL == argv || 0 == argc)
		return argv;

	/* measure a size of continuous argv[] area and make a copy */

	argv_int = zbx_malloc(argv_int, ((unsigned int)argc + 1) * sizeof(char *));

#if defined(PS_APPEND_ARGV)
	argc_ext_copied_first = argc - 1;
#else
	argc_ext_copied_first = 0;
#endif
	for (i = 0; i < argc_ext_copied_first; i++)
		argv_int[i] = argv[i];

	for (i = argc_ext_copied_first, arg_next = argv[argc_ext_copied_first]; arg_next == argv[i]; i++)
	{
		arg_next = argv[i] + strlen(argv[i]) + 1;
		argv_int[i] = zbx_strdup(NULL, argv[i]);

		/* argv[argc_ext_copied_first] will be used to display status messages. The rest of arguments can be */
		/* overwritten and their argv[] pointers will point to wrong strings. */
		if (argc_ext_copied_first < i)
			argv[i] = empty_str;
	}

	argc_ext_copied_last = i - 1;

	for (; i < argc; i++)
		argv_int[i] = argv[i];

	argv_int[argc] = NULL;	/* C standard: "argv[argc] shall be a null pointer" */

	if (argc_ext_copied_last == argc - 1)
	{
		int	envc = 0;

		while (NULL != environ[envc])
			envc++;

		/* measure a size of continuous environment area and make a copy */

		environ_int = zbx_malloc(environ_int, ((unsigned int)envc + 1) * sizeof(char *));

		for (i = 0; arg_next == environ[i]; i++)
		{
			arg_next = environ[i] + strlen(environ[i]) + 1;
			environ_int[i] = zbx_strdup(NULL, environ[i]);

			/* environment variables can be overwritten by status messages in argv[0] */
			/* and environ[] pointers will point to wrong strings */
			environ[i] = empty_str;
		}

		environ_ext_copied = i;

		for (;  i < envc; i++)
			environ_int[i] = environ[i];

		environ_int[envc] = NULL;
	}

	ps_buf_size = (size_t)(arg_next - argv[argc_ext_copied_first]);
	ps_buf = argv[argc_ext_copied_first];

#if defined(PS_CONCAT_ARGV)
	{
		char	*p = ps_buf;
		size_t	size = ps_buf_size, len;

		for (i = argc_ext_copied_first + 1; i < argc; i++)
		{
			len = strlen(argv_int[i - 1]);
			p += len;
			size -= len;
			if (2 >= size)
				break;
			zbx_strlcpy(p++, " ", size--);
			zbx_strlcpy(p, argv_int[i], size);
		}
	}
#endif

#if defined(PS_DARWIN_ARGV)
	*_NSGetArgv() = argv_int;
#endif
	environ_ext = environ;
	environ = environ_int;		/* switch environment to internal copy */

	return argv_int;
}
#elif defined(PS_PSTAT_ARGV)
char	**setproctitle_save_env(int argc, char **argv)
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
#if defined(PS_OVERWRITE_ARGV)
	static int	initialized = 0;

	if (1 == initialized)
	{
		size_t	msg_size;

		msg_size = zbx_strlcpy(ps_buf, status, ps_buf_size);

		if (prev_msg_size > msg_size)
			memset(ps_buf + msg_size + 1, '\0', ps_buf_size - msg_size - 1);

		prev_msg_size = msg_size;
	}
	else if (NULL != ps_buf)
	{
		size_t	start_pos;

		/* Initialization has not been moved to setproctitle_save_env() because setproctitle_save_env()	*/
		/* is called from the main process and we do not change its command line.			*/
		/* argv[] changing takes place only in child processes.						*/

#if defined(PS_CONCAT_ARGV)
		start_pos = strlen(argv_int[0]);
#else
		start_pos = strlen(ps_buf);
#endif
		if (start_pos + 2 < ps_buf_size)	/* is there space for ": " ? */
		{
			zbx_strlcpy(ps_buf + start_pos, ": ", (size_t)3);
			ps_buf += start_pos + 2;
			ps_buf_size -= start_pos + 2;	/* space after "argv[copy_first]: " for status message */

			memset(ps_buf, '\0', ps_buf_size);
			prev_msg_size = zbx_strlcpy(ps_buf, status, ps_buf_size);

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

	for (i = argc_ext_copied_first; i <= argc_ext_copied_last; i++)
		zbx_free(argv_int[i]);

	for (i = 0; i <= environ_ext_copied; i++)
		zbx_free(environ_int[i]);

	zbx_free(argv_int);
	zbx_free(environ_int);
}
#endif	/* PS_OVERWRITE_ARGV */
