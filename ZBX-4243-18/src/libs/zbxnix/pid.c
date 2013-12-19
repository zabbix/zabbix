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

#include "common.h"
#include "pid.h"
#include "log.h"
#include "threads.h"
#include <sysinc.h>

static FILE	*fpid = NULL;
static int	fdpid = -1;

int	create_pid_file(const char *pidfile)
{
	int		fd;
	struct stat	buf;
	struct flock	fl;

	fl.l_type = F_WRLCK;	/* F_RDLCK, F_WRLCK, F_UNLCK */
	fl.l_whence = SEEK_SET;	/* SEEK_SET, SEEK_CUR, SEEK_END */
	fl.l_start = 0;		/* offset from l_whence */
	fl.l_len = 0;		/* length, 0 = to EOF */
	fl.l_pid = getpid();	/* our PID */

	/* check if pid file already exists */
	if (0 == stat(pidfile, &buf))
	{
		if (-1 == (fd = open(pidfile, O_WRONLY | O_APPEND)))
		{
			zbx_error("cannot open PID file [%s]: %s", pidfile, zbx_strerror(errno));
			return FAIL;
		}

		if (-1 == fcntl(fd, F_SETLK, &fl))
		{
			zbx_error("Is this process already running? Could not lock PID file [%s]: %s",
					pidfile, zbx_strerror(errno));
			close(fd);
			return FAIL;
		}
		close(fd);
	}

	/* open pid file */
	if (NULL == (fpid = fopen(pidfile, "w")))
	{
		zbx_error("cannot create PID file [%s]: %s", pidfile, zbx_strerror(errno));
		return FAIL;
	}

	/* lock file */
	if (-1 != (fdpid = fileno(fpid)))
	{
		fcntl(fdpid, F_SETLK, &fl);
		fcntl(fdpid, F_SETFD, FD_CLOEXEC);
	}

	/* write pid to file */
	fprintf(fpid, "%d", (int)getpid());
	fflush(fpid);

	return SUCCEED;
}

int	read_pid_file(const char *pidfile, pid_t *pid, char *error, size_t max_error_len)
{
	int	ret = FAIL;
	FILE	*fpid;

	if (NULL == (fpid = fopen(pidfile, "r")))
	{
		zbx_snprintf(error, max_error_len, "cannot open PID file [%s]: %s", pidfile, zbx_strerror(errno));
		return ret;
	}

	if (1 == fscanf(fpid, "%d", (int *)pid))
		ret = SUCCEED;

	zbx_fclose(fpid);

	return ret;
}

void	drop_pid_file(const char *pidfile)
{
	struct flock fl;

	fl.l_type = F_UNLCK;		/* tell it to unlock the region */
	fl.l_whence = SEEK_SET;		/* SEEK_SET, SEEK_CUR, SEEK_END */
	fl.l_start = 0;			/* offset from l_whence */
	fl.l_len = 0;			/* length, 0 = to EOF */
	fl.l_pid = zbx_get_thread_id();	/* our PID */

	/* unlock file */
	if (-1 != fdpid)
		fcntl(fdpid, F_SETLK, &fl);

	zbx_fclose(fpid);

	if (-1 == unlink(pidfile))
		zabbix_log(LOG_LEVEL_DEBUG, "cannot remove PID file [%s]: %s", pidfile, zbx_strerror(errno));
}
