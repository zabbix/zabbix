/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#include "common.h"
#include "log.h"

#if defined(MAC_OS_X)
#include <crt_externs.h>
#endif

#ifdef HAVE_SYS_PSTAT_H
#include <sys/pstat.h>
#endif

extern char **environ;

#if defined(HAVE_FUNCTION_SETPROCTITLE)
#	define PS_USE_SETPROCTITLE	/* use the function setproctitle() (newer BSD systems) */
#elif defined(HAVE_SYS_PSTAT_H) && defined(PSTAT_SETCMD)
#	define PS_USE_PSTAT		/* use the pstat() (HPUX)*/
#elif defined(__linux__) || defined(_AIX) || (defined(sun) && !defined(BSD)) || defined(__osf__) || defined(MAC_OS_X)
#	define PS_USE_CLOBBER_ARGV	/* write over the argv and environment area (most SysV-like systems) */
#else
#	define PS_USE_NONE		/* do not update ps display (default) */
#endif

/* Different systems want the buffer padded differently */
#if defined(_AIX) || defined(__linux__) || defined(MAC_OS_X)
#	define PS_PADDING	'\0'
#else
#	define PS_PADDING	' '
#endif

#ifndef PS_USE_CLOBBER_ARGV
/* all but one options need a buffer to write their ps line in */
#define PS_BUFFER_SIZE 256
static const size_t	ps_buffer_size = PS_BUFFER_SIZE;
static char		ps_buffer[PS_BUFFER_SIZE];
#else /* PS_USE_CLOBBER_ARGV */
static char		*ps_buffer; /* will point to argv area */
static size_t		ps_buffer_size; /* space determined at run time */
#endif   /* PS_USE_CLOBBER_ARGV */

static int		save_argc;
static char		**save_argv;

static char		argv_full[MAX_STRING_LEN];
static char		prog_prefix[MAX_STRING_LEN];

/*
 * Call this early in startup to save the original argc/argv values.
 * If needed, we make a copy of the original argv[] array to preserve it
 * from being clobbered by subsequent ps_display actions.
 *
 * (The original argv[] will not be overwritten by this routine, but may be
 * overwritten during init_ps_display.    Also, the physical location of the
 * environment strings may be moved, so this should be called before any code
 * that might try to hang onto a getenv() result.)
 */
void save_ps_display_args(int argc, char **argv)
{
#if defined(PS_USE_CLOBBER_ARGV)
	char	*end_of_area = NULL;
	char	**new_environ = NULL;
	char	**new_argv = NULL;
	int	i;
#endif /* PS_USE_CLOBBER_ARGV */

	save_argc = argc;
	save_argv = argv;

#if defined(PS_USE_CLOBBER_ARGV)
	/* count the available space before overwriting argv area and move the environment to make additional room */
	/* check for contiguous argv strings */
	for (i = 0; i < argc; i++)
	{
		if (i == 0 || end_of_area + 1 == argv[i])
			end_of_area = argv[i] + strlen(argv[i]);

		strcat(argv_full, argv[i]);
		strcat(argv_full, " ");
	}

	argv_full[strlen(argv_full)-1] = '\0';

	if (end_of_area == NULL)
	{
		ps_buffer = NULL;
		ps_buffer_size = 0;
		return;
	}

	/* check for contiguous environ strings following argv */
	for (i = 0; environ[i] != NULL; i++)
	{
		if (end_of_area + 1 == environ[i])
			end_of_area = environ[i] + strlen(environ[i]);
	}

	ps_buffer = argv[0];
	ps_buffer_size = end_of_area - argv[0];

	/* move the environment out of the way */
	new_environ = (char **) zbx_malloc(new_environ, (i + 1) * sizeof(char *));
	for (i = 0; environ[i] != NULL; i++)
		new_environ[i] = zbx_strdup(NULL, environ[i]);
	new_environ[i] = NULL;
	environ = new_environ;

/* Before changing the original argv[] make a copy for argument parsing purposes. */
/* Some platforms have various dependencies on argv[]. */

	new_argv = (char **) zbx_malloc(new_argv, (argc + 1) * sizeof(char *));
	for (i = 0; i < argc; i++)
		new_argv[i] = zbx_strdup(NULL, argv[i]);
	new_argv[argc] = NULL;

#if defined(MAC_OS_X)
	/* Darwin has a static copy of the argv pointer */
	*_NSGetArgv() = new_argv;
#endif
	argv = new_argv;
#endif /* PS_USE_CLOBBER_ARGV */

	return;
}

/* Update the ps status display to a fixed prefix plus an activity indication */
void set_ps_display(const char *activity)
{
#ifndef PS_USE_NONE
#ifdef PS_USE_CLOBBER_ARGV
	int	buflen;

	if (!ps_buffer)
		return;
#endif

	if (0 == strcmp(activity, "main process"))
		return;

	zbx_snprintf(ps_buffer, ps_buffer_size, "");

	/* Update ps_buffer to contain both fixed part and activity */
#ifdef PS_USE_SETPROCTITLE
	zbx_strlcpy(ps_buffer + strlen(prog_prefix), activity, ps_buffer_size - strlen(prog_prefix));
#elif defined(sun)
	/* SUN requires new ps name to be longer than the initial one, */
	/* therefore activity is added to the initial ps name*/
	zbx_snprintf(ps_buffer, ps_buffer_size, "%s: %s", argv_full, activity);
#else
	zbx_snprintf(ps_buffer, ps_buffer_size, "%s: %s", prog_prefix, activity);
#endif

#ifdef PS_USE_SETPROCTITLE
	setproctitle("%s", ps_buffer);
#endif

#ifdef PS_USE_PSTAT
	union	pstun pst;

	pst.pst_command = ps_buffer;
	pstat(PSTAT_SETCMD, pst, strlen(ps_buffer), 0, 0);
#endif /* PS_USE_PSTAT */

#ifdef PS_USE_CLOBBER_ARGV
	/* pad unused memory */
	buflen = strlen(ps_buffer);
	memset(ps_buffer + buflen, PS_PADDING, ps_buffer_size - buflen);
#endif

#endif /* not PS_USE_NONE */
}

/* Call this once during subprocess startup to set the identification values */
void init_ps_display(void)
{
#ifndef PS_USE_NONE
#if defined(PS_USE_CLOBBER_ARGV)
	int	i;

	if (!ps_buffer)
		return;
#endif

	if (!save_argv)
		return;

#if defined(PS_USE_CLOBBER_ARGV)
	/* make extra argv slots point at end_of_area (a NUL) */
	for (i = 1; i < save_argc; i++)
		save_argv[i] = ps_buffer + ps_buffer_size;
#endif

#ifdef PS_USE_SETPROCTITLE /* setproctitle() already adds a 'progname:' prefix to the ps line */
	zbx_snprintf(ps_buffer, ps_buffer_size, " ");
#else
	zbx_strlcat(prog_prefix, progname, ps_buffer_size);
#endif

#endif /* not PS_USE_NONE */
}
