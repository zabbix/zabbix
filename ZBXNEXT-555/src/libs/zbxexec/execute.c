/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
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
#include "threads.h"
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_popen                                                        *
 *                                                                            *
 * Purpose: this function opens a process by creating a pipe, forking,        *
 *          and invoking the shell                                            *
 *                                                                            *
 * Parameters: pid     - [OUT] child process PID                              *
 *             command - [IN] a pointer to a null-terminated string           *
 *                       containing a shell command line                      *
 *                                                                            *
 * Return value: on success, reading file descriptor is returned. On error,   *
 *               -1 is returned, and errno is set appropriately               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	zbx_popen(pid_t *pid, const char *command)
{
	const char	*__function_name = "zbx_popen";
	int		fd[2];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() command:'%s'", __function_name, command);

	if (-1 == pipe(fd))
		return -1;

	if (-1 == (*pid = zbx_fork()))
	{
		close(fd[0]);
		close(fd[1]);
		return -1;
	}

	if (*pid > 0)	/* parent process */
	{
		close(fd[1]);

		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, fd[0]);

		return fd[0];
	}

	/* child process */
	close(fd[0]);
	dup2(fd[1], STDOUT_FILENO);
	close(fd[1]);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() executing script", __function_name);

	execl("/bin/sh", "sh", "-c", command, NULL);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot execute script [%s]: %s", __function_name, command, strerror(errno));
	exit(FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_waitpid                                                      *
 *                                                                            *
 * Purpose: this function waits for process to change state                   *
 *                                                                            *
 * Parameters: pid     - [IN] child process PID                               *
 *                                                                            *
 * Return value: on success, PID is returned. On error,                       *
 *               -1 is returned, and errno is set appropriately               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	zbx_waitpid(pid_t pid)
{
	const char	*__function_name = "zbx_waitpid";
	int		rc, status;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	do
	{
#ifdef WCONTINUED
		if (-1 == (rc = waitpid(pid, &status, WUNTRACED | WCONTINUED)))
#else
		if (-1 == (rc = waitpid(pid, &status, WUNTRACED)))
#endif
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() waitpid failure: %s", __function_name, strerror(errno));
			goto exit;
		}

		if (WIFEXITED(status))
			zabbix_log(LOG_LEVEL_DEBUG, "%s() exited, status:%d", __function_name, WEXITSTATUS(status));
		else if (WIFSIGNALED(status))
			zabbix_log(LOG_LEVEL_DEBUG, "%s() killed by signal %d", __function_name, WTERMSIG(status));
		else if (WIFSTOPPED(status))
			zabbix_log(LOG_LEVEL_DEBUG, "%s() stopped by signal %d", __function_name, WSTOPSIG(status));
#ifdef WIFCONTINUED
		else if (WIFCONTINUED(status))
			zabbix_log(LOG_LEVEL_DEBUG, "%s() continued", __function_name);
#endif
	}
	while (!WIFEXITED(status) && !WIFSIGNALED(status));
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, rc);

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_execute                                                      *
 *                                                                            *
 * Purpose: this function executes a script and returns result from stdout    *
 *                                                                            *
 * Parameters: command       - [IN] command for execution                     *
 *             buffer        - [OUT] buffer for output, if NULL - ignored     *
 *             error         - [OUT] error string if function fails           *
 *             max_error_len - [IN] length of error buffer                    *
 *                                                                            *
 * Return value: SUCCEED if processed successfully, FAIL - otherwise          *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_execute(const char *command, char **buffer, char *error, size_t max_error_len)
{
	pid_t	pid;
	int	fd, ret = SUCCEED;

	if (-1 != (fd = zbx_popen(&pid, command)))
	{
		int	rc = 0;

		if (NULL != buffer)
		{
			char	*p;
			size_t	buf_size = MAX_BUFFER_LEN;

			*buffer = p = zbx_realloc(*buffer, buf_size);
			buf_size--;	/* '\0' */

			while (0 < (rc = read(fd, p, buf_size)))
			{
				p += rc;
				buf_size -= rc;
			}

			*p = '\0';
		}

		close(fd);

		if (-1 == rc)
		{
			if (EINTR == errno)
				zbx_strlcpy(error, "Timeout while executing a shell script", max_error_len);
			else
				zbx_strlcpy(error, strerror(errno), max_error_len);

			kill(pid, SIGTERM);
			zbx_waitpid(pid);
			ret = FAIL;
		}
		else
		{
			if (-1 == zbx_waitpid(pid))
			{
				if (EINTR == errno)
					zbx_strlcpy(error, "Timeout while executing a shell script", max_error_len);
				else
					zbx_strlcpy(error, strerror(errno), max_error_len);

				kill(pid, SIGTERM);
				zbx_waitpid(pid);
				ret = FAIL;
			}
		}
	}
	else
	{
		zbx_strlcpy(error, strerror(errno), max_error_len);
		ret = FAIL;
	}

	return ret;
}
