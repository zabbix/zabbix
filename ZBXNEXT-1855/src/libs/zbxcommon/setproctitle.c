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
#include <stdlib.h>
#include <string.h>
#include "log.h"

#if defined(MAC_OS_X)
#include <crt_externs.h>
#endif

#ifdef HAVE_PS_STRINGS
#include <machine/vmparam.h> /* for old BSD */
#include <sys/exec.h>
#endif

extern char **environ;

#if defined(HAVE_FUNCTION_SETPROCTITLE)
#	define PS_USE_SETPROCTITLE	/* use the function setproctitle() (newer BSD systems) */
#elif defined(HAVE_SYS_PSTAT_H) && defined(PSTAT_SETCMD)
#	define PS_USE_PSTAT		/* use the pstat() (HPUX)*/
#elif defined(HAVE_PS_STRINGS)
#	define PS_USE_PS_STRINGS	/* assign PS_STRINGS->ps_argvstr = "string" (some BSD systems) */
#elif (defined(BSD) || defined(__bsdi__)) && !defined(MAC_OS_X)
#	define PS_USE_CHANGE_ARGV	/* assign argv[0] = "string" (some other BSD systems) */
#elif defined(__linux__) || defined(_AIX) || (defined(sun) && !defined(BSD)) || defined(__osf__) || defined(MAC_OS_X)
#	define PS_USE_CLOBBER_ARGV	/* write over the argv and environment area (most SysV-like systems) */
#else
#	define PS_USE_NONE		/* do not update ps display (default) */
#endif

/* Different systems want the buffer padded differently */

#if defined(_AIX) || defined(__linux__)
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

static size_t		ps_buffer_fixed_size; /* size of the constant prefix */

/* save the original argv[] location here */
static int		save_argc;
static char		**save_argv;

static char	argv_full[MAX_STRING_LEN];

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
	save_argc = argc;
	save_argv = argv;

#if defined(PS_USE_CLOBBER_ARGV)
/*
* If we're going to overwrite the argv area, count the available space.
* Also move the environment to make additional room.
*/
	{
		char	*end_of_area = NULL;
		char	**new_environ;
		int	i;

		/* check for contiguous argv strings */
		for (i = 0; i < argc; i++)
		{
			if (i == 0 || end_of_area + 1 == argv[i])
				end_of_area = argv[i] + strlen(argv[i]);

			strcat(argv_full, argv[i]);
			strcat(argv_full, " ");
		}

		if (end_of_area == NULL)      /* probably can't happen? */
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
			new_environ[i] = strdup(environ[i]);
		new_environ[i] = NULL;
		environ = new_environ;
	}
#endif   /* PS_USE_CLOBBER_ARGV */

#if defined(PS_USE_CHANGE_ARGV) || defined(PS_USE_CLOBBER_ARGV)

/*
* If we're going to change the original argv[] then make a copy for
* argument parsing purposes.
*
* (NB: do NOT think to remove the copying of argv[], even though
* postmaster.c finishes looking at argv[] long before we ever consider
* changing the ps display.  On some platforms, getopt() keeps pointers
* into the argv array, and will get horribly confused when it is
* re-called to analyze a subprocess' argument string if the argv storage
* has been clobbered meanwhile.  Other platforms have other dependencies
* on argv[].
*/
	{
		char	**new_argv;
		int	i;

		new_argv = (char **) zbx_malloc(new_argv, (argc + 1) * sizeof(char *));
		for (i = 0; i < argc; i++)
			new_argv[i] = strdup(argv[i]);
		new_argv[argc] = NULL;

#if defined(MAC_OS_X)
		/*
		* Darwin (and perhaps other NeXT-derived platforms?) has a static
		* copy of the argv pointer, which we may fix like so:
		*/
		*_NSGetArgv() = new_argv;
#endif
		argv = new_argv;
	}
#endif   /* PS_USE_CHANGE_ARGV or PS_USE_CLOBBER_ARGV */

	return;
}

/*
 * Call this to update the ps status display to a fixed prefix plus an
 * indication of what you're currently doing passed in the argument.
 */
void set_ps_display(const char *activity)
{
#ifndef PS_USE_NONE

#ifdef PS_USE_CLOBBER_ARGV
	/* If ps_buffer is a pointer, it might still be null */
	if (!ps_buffer)
		return;
#endif

	/* Update ps_buffer to contain both fixed part and activity */
#if defined(sun)
	if (strlen(argv_full) > strlen(activity))
	{
		zbx_strlcpy(ps_buffer + ps_buffer_fixed_size, argv_full, ps_buffer_size - ps_buffer_fixed_size);
		zbx_strlcpy(ps_buffer + ps_buffer_fixed_size + strlen(argv_full), activity, ps_buffer_size - ps_buffer_fixed_size - strlen(activity));
	}
	else
		zbx_strlcpy(ps_buffer + ps_buffer_fixed_size, activity, ps_buffer_size - ps_buffer_fixed_size);
#else
	zbx_strlcpy(ps_buffer + ps_buffer_fixed_size, activity, ps_buffer_size - ps_buffer_fixed_size);
#endif


	/* Transmit new setting to kernel, if necessary */

#ifdef PS_USE_SETPROCTITLE
	setproctitle("%s", ps_buffer);
#endif

#ifdef PS_USE_PSTAT
	{
	union	pstun pst;

	pst.pst_command = ps_buffer;
	pstat(PSTAT_SETCMD, pst, strlen(ps_buffer), 0, 0);
	}
#endif   /* PS_USE_PSTAT */

#ifdef PS_USE_PS_STRINGS
	PS_STRINGS->ps_nargvstr = 1;
	PS_STRINGS->ps_argvstr = ps_buffer;
#endif   /* PS_USE_PS_STRINGS */

#ifdef PS_USE_CLOBBER_ARGV
	{
	int	buflen;

	/* pad unused memory */
	buflen = strlen(ps_buffer);
	memset(ps_buffer + buflen, PS_PADDING, ps_buffer_size - buflen);
	}
#endif   /* PS_USE_CLOBBER_ARGV */

#endif   /* not PS_USE_NONE */
}

/*
 * Call this once during subprocess startup to set the identification
 * values.  At this point, the original argv[] array may be overwritten.
 */
void init_ps_display(const char *initial_str)
{
	const char	*prog_name;
	int		i;

#ifndef PS_USE_NONE
	/* no ps display if you didn't call save_ps_display_args() */
	if (!save_argv)
		return;
#if defined(PS_USE_CLOBBER_ARGV)
	/* If ps_buffer is a pointer, it might still be null */
	if (!ps_buffer)
		return;
#endif

	/* Overwrite argv[] to point at appropriate space, if needed */
#if defined(PS_USE_CHANGE_ARGV)
	save_argv[0] = ps_buffer;
	save_argv[1] = NULL;
#endif   /* PS_USE_CHANGE_ARGV */

#if defined(PS_USE_CLOBBER_ARGV)
	{
	/* make extra argv slots point at end_of_area (a NUL) */
	for (i = 1; i < save_argc; i++)
		save_argv[i] = ps_buffer + ps_buffer_size;
	}
#endif   /* PS_USE_CLOBBER_ARGV */

	/* Make fixed prefix of ps display. */
#ifdef PS_USE_SETPROCTITLE
	/* apparently setproctitle() already adds a `progname:' prefix to the ps line */
	zbx_snprintf(ps_buffer, ps_buffer_size, " ");
#else
	prog_name = get_program_name(save_argv[0]);
	zbx_snprintf(ps_buffer, ps_buffer_size, "");
	strcat(ps_buffer, prog_name);
	strcat(ps_buffer,": ");
#endif
	ps_buffer_fixed_size = strlen(ps_buffer);

	set_ps_display(initial_str);
#endif   /* not PS_USE_NONE */
}
