/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbxnix.h"
#include "pid.h"

#include "common.h"
#include "log.h"

static FILE	*fpid = NULL;
static int	fdpid = -1;

int	create_pid_file(const char *pidfile)
{
	int		fd;
	zbx_stat_t	buf;
	struct flock	fl;

	fl.l_type = F_WRLCK;
	fl.l_whence = SEEK_SET;
	fl.l_start = 0;
	fl.l_len = 0;
	fl.l_pid = getpid();

	/* check if pid file already exists */
	if (-1 != (fd = open(pidfile, O_WRONLY | O_APPEND)))
	{
		if (0 == zbx_fstat(fd, &buf) && -1 == fcntl(fd, F_SETLK, &fl))
		{
			close(fd);
			zbx_error("Is this process already running? Could not lock PID file [%s]: %s",
					pidfile, zbx_strerror(errno));
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
	if (-1 != (fdpid = fileno(fpid)) && (-1 == fcntl(fdpid, F_SETLK, &fl) || -1 == fcntl(fdpid,
			F_SETFD, FD_CLOEXEC)))
	{
		zbx_error("error in setting the status flag: %s", zbx_strerror(errno));
	}

	/* write pid to file */
	fprintf(fpid, "%d", (int)getpid());
	fflush(fpid);

	return SUCCEED;
}

int	read_pid_file(const char *pidfile, pid_t *pid, char *error, size_t max_error_len)
{
	int	ret = FAIL;
	FILE	*f_pid;

	if (NULL == (f_pid = fopen(pidfile, "r")))
	{
		zbx_snprintf(error, max_error_len, "cannot open PID file [%s]: %s", pidfile, zbx_strerror(errno));
		return ret;
	}

	if (1 == fscanf(f_pid, "%d", (int *)pid))
		ret = SUCCEED;
	else
		zbx_snprintf(error, max_error_len, "cannot retrieve PID from file [%s]", pidfile);

	zbx_fclose(f_pid);

	return ret;
}

void	drop_pid_file(const char *pidfile)
{
	struct flock	fl;

	fl.l_type = F_UNLCK;
	fl.l_whence = SEEK_SET;
	fl.l_start = 0;
	fl.l_len = 0;
	fl.l_pid = zbx_get_thread_id();

	/* unlock file */
	if (-1 != fdpid && -1 == fcntl(fdpid, F_SETLK, &fl))
		zbx_error("error in setting the status flag: %s", zbx_strerror(errno));

	/* close pid file */
	zbx_fclose(fpid);

	unlink(pidfile);
}
