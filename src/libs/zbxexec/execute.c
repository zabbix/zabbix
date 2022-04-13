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

#include "zbxexec.h"

#include "common.h"
#include "threads.h"
#include "log.h"

/* the size of temporary buffer used to read from output stream */
#define PIPE_BUFFER_SIZE	4096

#ifdef _WINDOWS

/******************************************************************************
 *                                                                            *
 * Purpose: considers a difference between times in milliseconds              *
 *                                                                            *
 * Parameters: time1         - [IN] first time point                          *
 *             time2         - [IN] second time point                         *
 *                                                                            *
 * Return value: difference between times in milliseconds                     *
 *                                                                            *
 ******************************************************************************/
static int	zbx_get_timediff_ms(struct _timeb *time1, struct _timeb *time2)
{
	int	ms;

	ms = (int)(time2->time - time1->time) * 1000;
	ms += time2->millitm - time1->millitm;

	if (0 > ms)
		ms = 0;

	return ms;
}

/******************************************************************************
 *                                                                            *
 * Purpose: read data from pipe                                               *
 *                                                                            *
 * Parameters: hRead         - [IN] a handle to the device                    *
 *             buf           - [IN/OUT] a pointer to the buffer               *
 *             buf_size      - [IN] buffer size                               *
 *             offset        - [IN/OUT] current position in the buffer        *
 *             timeout_ms    - [IN] timeout in milliseconds                   *
 *                                                                            *
 * Return value: SUCCEED, FAIL or TIMEOUT_ERROR if timeout reached            *
 *                                                                            *
 ******************************************************************************/
static int	zbx_read_from_pipe(HANDLE hRead, char **buf, size_t *buf_size, size_t *offset, int timeout_ms)
{
	DWORD		in_buf_size, read_bytes;
	struct _timeb	start_time, current_time;
	char 		tmp_buf[PIPE_BUFFER_SIZE];

	_ftime(&start_time);

	while (0 != PeekNamedPipe(hRead, NULL, 0, NULL, &in_buf_size, NULL))
	{
		_ftime(&current_time);
		if (zbx_get_timediff_ms(&start_time, &current_time) >= timeout_ms)
			return TIMEOUT_ERROR;

		if (MAX_EXECUTE_OUTPUT_LEN <= *offset + in_buf_size)
		{
			zabbix_log(LOG_LEVEL_ERR, "command output exceeded limit of %d KB",
					MAX_EXECUTE_OUTPUT_LEN / ZBX_KIBIBYTE);
			return FAIL;
		}

		if (0 != in_buf_size)
		{
			if (0 == ReadFile(hRead, tmp_buf, sizeof(tmp_buf) - 1, &read_bytes, NULL))
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot read command output: %s",
						strerror_from_system(GetLastError()));
				return FAIL;
			}

			if (NULL != buf)
			{
				tmp_buf[read_bytes] = '\0';
				zbx_strcpy_alloc(buf, buf_size, offset, tmp_buf);
			}

			in_buf_size = 0;
			continue;
		}

		Sleep(20);	/* milliseconds */
	}

	return SUCCEED;
}

#else	/* not _WINDOWS */

/******************************************************************************
 *                                                                            *
 * Purpose: this function opens a process by creating a pipe, forking,        *
 *          and invoking the shell                                            *
 *                                                                            *
 * Parameters: pid     - [OUT] child process PID                              *
 *             command - [IN] a pointer to a null-terminated string           *
 *                       containing a shell command line                      *
 *             dir     - [IN] directory to execute command under,             *
 *                       stay in current directory if NULL                    *
 *                                                                            *
 * Return value: on success, reading file descriptor is returned. On error,   *
 *               -1 is returned, and errno is set appropriately               *
 *                                                                            *
 ******************************************************************************/
static int	zbx_popen(pid_t *pid, const char *command, const char *dir)
{
	int	fd[2], stdout_orig, stderr_orig;
	DIR	*test_dir;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() command:'%s'", __func__, command);

	if (NULL != dir)
	{
		if (NULL == (test_dir = opendir(dir)))
			return -1;

		if (-1 == closedir(test_dir))
			return -1;
	}

	if (-1 == pipe(fd))
		return -1;

	if (-1 == (*pid = zbx_fork()))
	{
		close(fd[0]);
		close(fd[1]);
		return -1;
	}

	if (0 != *pid)	/* parent process */
	{
		close(fd[1]);

		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, fd[0]);

		return fd[0];
	}

	/* child process */

	close(fd[0]);

	/* set the child as the process group leader, otherwise orphans may be left after timeout */
	if (-1 == setpgid(0, 0))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): failed to create a process group: %s", __func__, zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): executing script", __func__);

	/* preserve stdout and stderr to restore them in case execl() fails */

	if (-1 == (stdout_orig = dup(STDOUT_FILENO)))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): failed to duplicate stdout: %s",
				__func__, zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	if (-1 == (stderr_orig = dup(STDERR_FILENO)))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): failed to duplicate stderr: %s",
				__func__, zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	if (-1 == fcntl(stdout_orig, F_SETFD, FD_CLOEXEC))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s(): failed to set the FD_CLOEXEC file descriptor flag on stdout: %s",
				__func__, zbx_strerror(errno));
	}

	if (-1 == fcntl(stderr_orig, F_SETFD, FD_CLOEXEC))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s(): failed to set the FD_CLOEXEC file descriptor flag on stderr: %s",
				__func__, zbx_strerror(errno));
	}

	/* redirect output right before script execution after all logging is done */

	dup2(fd[1], STDOUT_FILENO);
	dup2(fd[1], STDERR_FILENO);
	close(fd[1]);

	if (NULL != dir && 0 != chdir(dir))
	{
		fprintf(stderr, "cannot change directory to UserParameterDir: %s\n", zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	execl("/bin/sh", "sh", "-c", command, NULL);

	/* restore original stdout and stderr, because we don't want our output to be confused with script's output */

	dup2(stdout_orig, STDOUT_FILENO);
	dup2(stderr_orig, STDERR_FILENO);
	close(stdout_orig);
	close(stderr_orig);

	/* this message may end up in stdout or stderr, that's why we needed to save and restore them */
	zabbix_log(LOG_LEVEL_WARNING, "execl() failed for [%s]: %s", command, zbx_strerror(errno));

	/* execl() returns only when an error occurs, let parent process know about it */
	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: this function waits for process to change state                   *
 *                                                                            *
 * Parameters: pid     - [IN] child process PID                               *
 *             status  - [OUT] process status                                 *
 *                                                                            *
 * Return value: on success, PID is returned. On error,                       *
 *               -1 is returned, and errno is set appropriately               *
 *                                                                            *
 ******************************************************************************/
static int	zbx_waitpid(pid_t pid, int *status)
{
	int	rc, result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	do
	{
#ifdef WCONTINUED
		static int	wcontinued = WCONTINUED;
retry:
		if (-1 == (rc = waitpid(pid, &result, WUNTRACED | wcontinued)))
		{
			if (EINVAL == errno && 0 != wcontinued)
			{
				wcontinued = 0;
				goto retry;
			}
#else
		if (-1 == (rc = waitpid(pid, &result, WUNTRACED)))
		{
#endif
			zabbix_log(LOG_LEVEL_DEBUG, "%s() waitpid failure: %s", __func__, zbx_strerror(errno));
			goto exit;
		}

		if (WIFEXITED(result))
			zabbix_log(LOG_LEVEL_DEBUG, "%s() exited, status:%d", __func__, WEXITSTATUS(result));
		else if (WIFSIGNALED(result))
			zabbix_log(LOG_LEVEL_DEBUG, "%s() killed by signal %d", __func__, WTERMSIG(result));
		else if (WIFSTOPPED(result))
			zabbix_log(LOG_LEVEL_DEBUG, "%s() stopped by signal %d", __func__, WSTOPSIG(result));
#ifdef WIFCONTINUED
		else if (WIFCONTINUED(result))
			zabbix_log(LOG_LEVEL_DEBUG, "%s() continued", __func__);
#endif
	}
	while (!WIFEXITED(result) && !WIFSIGNALED(result));
exit:
	if (NULL != status)
		*status = result;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, rc);

	return rc;
}

#endif	/* _WINDOWS */

/******************************************************************************
 *                                                                            *
 * Purpose: this function executes a script and returns result from stdout    *
 *                                                                            *
 * Parameters: command       - [IN] command for execution                     *
 *             output        - [OUT] buffer for output, if NULL - ignored     *
 *             error         - [OUT] error string if function fails           *
 *             max_error_len - [IN] length of error buffer                    *
 *             timeout       - [IN] execution timeout                         *
 *             flag          - [IN] indicates if exit code must be checked    *
 *             dir           - [IN] directory to execute command under,       *
 *                                  pass NULL to stay in current directory    *
 *                                                                            *
 * Return value: SUCCEED if processed successfully, TIMEOUT_ERROR if          *
 *               timeout occurred, SIG_ERROR if interrupted by signal or FAIL *
 *               otherwise                                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_execute(const char *command, char **output, char *error, size_t max_error_len, int timeout,
		unsigned char flag, const char *dir)
{
	size_t			buf_size = PIPE_BUFFER_SIZE, offset = 0;
	int			ret = FAIL;
	char			*buffer = NULL;
#ifdef _WINDOWS
	STARTUPINFO		si;
	PROCESS_INFORMATION	pi;
	SECURITY_ATTRIBUTES	sa;
	HANDLE			job = NULL, hWrite = NULL, hRead = NULL;
	char			*cmd = NULL;
	wchar_t			*wcmd = NULL, *wdir = NULL;
	struct _timeb		start_time, current_time;
	DWORD			code;
#else
	pid_t			pid;
	int			fd;
#endif

	*error = '\0';

	if (NULL != output)
		zbx_free(*output);

	buffer = (char *)zbx_malloc(buffer, buf_size);
	*buffer = '\0';

#ifdef _WINDOWS

	/* set the bInheritHandle flag so pipe handles are inherited */
	sa.nLength = sizeof(SECURITY_ATTRIBUTES);
	sa.bInheritHandle = TRUE;
	sa.lpSecurityDescriptor = NULL;

	/* create a pipe for the child process's STDOUT */
	if (0 == CreatePipe(&hRead, &hWrite, &sa, 0))
	{
		zbx_snprintf(error, max_error_len, "unable to create a pipe: %s", strerror_from_system(GetLastError()));
		goto close;
	}

	/* create a new job where the script will be executed */
	if (0 == (job = CreateJobObject(&sa, NULL)))
	{
		zbx_snprintf(error, max_error_len, "unable to create a job: %s", strerror_from_system(GetLastError()));
		goto close;
	}

	/* fill in process startup info structure */
	memset(&si, 0, sizeof(STARTUPINFO));
	si.cb = sizeof(STARTUPINFO);
	si.dwFlags = STARTF_USESTDHANDLES;
	si.hStdInput = GetStdHandle(STD_INPUT_HANDLE);
	si.hStdOutput = hWrite;
	si.hStdError = hWrite;

	/* use cmd command to support scripts */
	cmd = zbx_dsprintf(cmd, "cmd /C \"%s\"", command);
	wcmd = zbx_utf8_to_unicode(cmd);

	if(NULL != dir)
		wdir = zbx_utf8_to_unicode(dir);

	/* create the new process */
	if (0 == CreateProcess(NULL, wcmd, NULL, NULL, TRUE, CREATE_SUSPENDED, NULL, wdir, &si, &pi))
	{
		zbx_snprintf(error, max_error_len, "unable to create process [%s]: %s",
				cmd, strerror_from_system(GetLastError()));
		goto close;
	}

	CloseHandle(hWrite);
	hWrite = NULL;

	/* assign the new process to the created job */
	if (0 == AssignProcessToJobObject(job, pi.hProcess))
	{
		zbx_snprintf(error, max_error_len, "unable to assign process [%s] to a job: %s",
				cmd, strerror_from_system(GetLastError()));
		if (0 == TerminateProcess(pi.hProcess, 0))
		{
			zabbix_log(LOG_LEVEL_ERR, "failed to terminate [%s]: %s",
					cmd, strerror_from_system(GetLastError()));
		}
	}
	else if (-1 == ResumeThread(pi.hThread))
	{
		zbx_snprintf(error, max_error_len, "unable to assign process [%s] to a job: %s",
				cmd, strerror_from_system(GetLastError()));
	}
	else
		ret = SUCCEED;

	if (FAIL == ret)
		goto close;

	_ftime(&start_time);
	timeout *= 1000;

	ret = zbx_read_from_pipe(hRead, &buffer, &buf_size, &offset, timeout);

	if (TIMEOUT_ERROR != ret)
	{
		_ftime(&current_time);
		if (0 < (timeout -= zbx_get_timediff_ms(&start_time, &current_time)) &&
				WAIT_TIMEOUT == WaitForSingleObject(pi.hProcess, timeout))
		{
			ret = TIMEOUT_ERROR;
		}
		else if (WAIT_OBJECT_0 != WaitForSingleObject(pi.hProcess, 0) ||
				0 == GetExitCodeProcess(pi.hProcess, &code))
		{
			if ('\0' != *buffer)
				zbx_strlcpy(error, buffer, max_error_len);
			else
				zbx_strlcpy(error, "Process terminated unexpectedly.", max_error_len);

			ret = FAIL;
		}
		else if (ZBX_EXIT_CODE_CHECKS_ENABLED == flag && 0 != code)
		{
			if ('\0' != *buffer)
				zbx_strlcpy(error, buffer, max_error_len);
			else
				zbx_snprintf(error, max_error_len, "Process exited with code: %d.", code);

			ret = FAIL;
		}
	}

	CloseHandle(pi.hProcess);
	CloseHandle(pi.hThread);
close:
	if (NULL != job)
	{
		/* terminate the child process and its children */
		if (0 == TerminateJobObject(job, 0))
			zabbix_log(LOG_LEVEL_ERR, "failed to terminate job [%s]: %s", cmd, strerror_from_system(GetLastError()));
		CloseHandle(job);
	}

	if (NULL != hWrite)
		CloseHandle(hWrite);

	if (NULL != hRead)
		CloseHandle(hRead);

	zbx_free(cmd);
	zbx_free(wcmd);
	zbx_free(wdir);

#else	/* not _WINDOWS */

	zbx_alarm_on(timeout);

	if (-1 != (fd = zbx_popen(&pid, command, dir)))
	{
		int	rc, status;
		char	tmp_buf[PIPE_BUFFER_SIZE];

		while (0 < (rc = read(fd, tmp_buf, sizeof(tmp_buf) - 1)) && MAX_EXECUTE_OUTPUT_LEN > offset + rc)
		{
			tmp_buf[rc] = '\0';
			zbx_strcpy_alloc(&buffer, &buf_size, &offset, tmp_buf);
		}

		close(fd);

		if (-1 == rc || -1 == zbx_waitpid(pid, &status))
		{
			if (EINTR == errno)
			{
				if (SUCCEED == zbx_alarm_timed_out())
					ret = TIMEOUT_ERROR;
				else
				{
					ret = SIG_ERROR;
					zbx_strlcpy(error, "Signal received while executing a shell script.",
							max_error_len);
				}
			}
			else
				zbx_snprintf(error, max_error_len, "zbx_waitpid() failed: %s", zbx_strerror(errno));

			/* kill the whole process group, pid must be the leader */
			if (-1 == kill(-pid, SIGTERM))
				zabbix_log(LOG_LEVEL_ERR, "failed to kill [%s]: %s", command, zbx_strerror(errno));

			zbx_waitpid(pid, NULL);
		}
		else if (MAX_EXECUTE_OUTPUT_LEN <= offset + rc)
		{
			zabbix_log(LOG_LEVEL_ERR, "command output exceeded limit of %d KB",
					MAX_EXECUTE_OUTPUT_LEN / ZBX_KIBIBYTE);
		}
		else if (0 == WIFEXITED(status) || (ZBX_EXIT_CODE_CHECKS_ENABLED == flag && 0 != WEXITSTATUS(status)))
		{
			if ('\0' == *buffer)
			{
				if (WIFEXITED(status))
				{
					zbx_snprintf(error, max_error_len, "Process exited with code: %d.",
							WEXITSTATUS(status));
				}
				else if (WIFSIGNALED(status))
				{
					zbx_snprintf(error, max_error_len, "Process killed by signal: %d.",
							WTERMSIG(status));
					ret = SIG_ERROR;
				}
				else
					zbx_strlcpy(error, "Process terminated unexpectedly.", max_error_len);
			}
			else
				zbx_strlcpy(error, buffer, max_error_len);
		}
		else
			ret = SUCCEED;
	}
	else
		zbx_strlcpy(error, zbx_strerror(errno), max_error_len);

	zbx_alarm_off();

#endif	/* _WINDOWS */

	if (TIMEOUT_ERROR == ret)
		zbx_strlcpy(error, "Timeout while executing a shell script.", max_error_len);

	if ('\0' != *error)
		zabbix_log(LOG_LEVEL_WARNING, "Failed to execute command \"%s\": %s", command, error);

	if (SUCCEED != ret || NULL == output)
		zbx_free(buffer);

	if (NULL != output)
		*output = buffer;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: this function executes a script in the background and             *
 *          suppresses the std output                                         *
 *                                                                            *
 * Parameters: command - [IN] command for execution                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_execute_nowait(const char *command)
{
#ifdef _WINDOWS
	char			*full_command;
	STARTUPINFO		si;
	PROCESS_INFORMATION	pi;
	wchar_t			*wcommand;

	full_command = zbx_dsprintf(NULL, "cmd /C \"%s\"", command);
	wcommand = zbx_utf8_to_unicode(full_command);

	/* fill in process startup info structure */
	memset(&si, 0, sizeof(si));
	si.cb = sizeof(si);
	GetStartupInfo(&si);

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): executing [%s]", __func__, full_command);

	if (0 == CreateProcess(
		NULL,		/* no module name (use command line) */
		wcommand,	/* name of app to launch */
		NULL,		/* default process security attributes */
		NULL,		/* default thread security attributes */
		FALSE,		/* do not inherit handles from the parent */
		0,		/* normal priority */
		NULL,		/* use the same environment as the parent */
		NULL,		/* launch in the current directory */
		&si,		/* startup information */
		&pi))		/* process information stored upon return */
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to create process for [%s]: %s",
				full_command, strerror_from_system(GetLastError()));
		return FAIL;
	}

	CloseHandle(pi.hProcess);
	CloseHandle(pi.hThread);

	zbx_free(wcommand);
	zbx_free(full_command);

	return SUCCEED;

#else	/* not _WINDOWS */
	pid_t		pid;

	/* use a double fork for running the command in background */
	if (-1 == (pid = zbx_fork()))
	{
		zabbix_log(LOG_LEVEL_WARNING, "first fork() failed for executing [%s]: %s",
				command, zbx_strerror(errno));
		return FAIL;
	}
	else if (0 != pid)
	{
		waitpid(pid, NULL, 0);
		return SUCCEED;
	}

	/* This is the child process. Now create a grand child process which */
	/* will be replaced by execl() with the actual command to be executed. */

	pid = zbx_fork();

	switch (pid)
	{
		case -1:
			zabbix_log(LOG_LEVEL_WARNING, "second fork() failed for executing [%s]: %s",
					command, zbx_strerror(errno));
			break;
		case 0:
			/* this is the grand child process */

			/* suppress the output of the executed script, otherwise */
			/* the output might get written to a logfile or elsewhere */
			zbx_redirect_stdio(NULL);

			/* replace the process with actual command to be executed */
			execl("/bin/sh", "sh", "-c", command, NULL);

			/* execl() returns only when an error occurs */
			zabbix_log(LOG_LEVEL_WARNING, "execl() failed for [%s]: %s", command, zbx_strerror(errno));
			break;
		default:
			/* this is the child process, exit to complete the double fork */

			waitpid(pid, NULL, WNOHANG);
			break;
	}

	/* always exit, parent has already returned */
	exit(EXIT_SUCCESS);
#endif
}
