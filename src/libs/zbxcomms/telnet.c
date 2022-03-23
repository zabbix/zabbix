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

#include "telnet.h"

#include "common.h"
#include "log.h"

static char	prompt_char = '\0';

static int	telnet_waitsocket(ZBX_SOCKET socket_fd, int mode)
{
	struct timeval	tv;
	int		rc;
	fd_set		fd, *readfd = NULL, *writefd = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	tv.tv_sec = 0;
	tv.tv_usec = 100000;	/* 1/10 sec */

	FD_ZERO(&fd);
	FD_SET(socket_fd, &fd);

	if (WAIT_READ == mode)
		readfd = &fd;
	else
		writefd = &fd;

	rc = select(ZBX_SOCKET_TO_INT(socket_fd) + 1, readfd, writefd, NULL, &tv);

	if (ZBX_PROTO_ERROR == rc)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() rc:%d errno:%d error:[%s]", __func__, rc, zbx_socket_last_error(),
				strerror_from_system(zbx_socket_last_error()));
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, rc);

	return rc;
}

static ssize_t	telnet_socket_read(ZBX_SOCKET socket_fd, void *buf, size_t count)
{
	ssize_t	rc;
	int	error;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (ZBX_PROTO_ERROR == (rc = ZBX_TCP_READ(socket_fd, buf, count)))
	{
		error = zbx_socket_last_error();	/* zabbix_log() resets the error code */
		zabbix_log(LOG_LEVEL_DEBUG, "%s() rc:%ld errno:%d error:[%s]",
				__func__, (long int)rc, error, strerror_from_system(error));
#ifdef _WINDOWS
		if (WSAEWOULDBLOCK == error)
#else
		if (EAGAIN == error)
#endif
		{
			/* wait and if there is still an error or no input available */
			/* we assume the other side has nothing more to say */
			if (1 > (rc = telnet_waitsocket(socket_fd, WAIT_READ)))
				goto ret;

			continue;
		}

		break;
	}

	/* when ZBX_TCP_READ returns 0, it means EOF - let's consider it a permanent error */
	/* note that if telnet_waitsocket() is zero, it is not a permanent condition */
	if (0 == rc)
		rc = ZBX_PROTO_ERROR;
ret:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%ld", __func__, (long int)rc);

	return rc;
}

static ssize_t	telnet_socket_write(ZBX_SOCKET socket_fd, const void *buf, size_t count)
{
	ssize_t	rc;
	int	error;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (ZBX_PROTO_ERROR == (rc = ZBX_TCP_WRITE(socket_fd, buf, count)))
	{
		error = zbx_socket_last_error();	/* zabbix_log() resets the error code */
		zabbix_log(LOG_LEVEL_DEBUG, "%s() rc:%ld errno:%d error:[%s]",
				__func__, (long int)rc, error, strerror_from_system(error));
#ifdef _WINDOWS
		if (WSAEWOULDBLOCK == error)
#else
		if (EAGAIN == error)
#endif
		{
			telnet_waitsocket(socket_fd, WAIT_WRITE);
			continue;
		}

		break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%ld", __func__, (long int)rc);

	return rc;
}

static ssize_t	telnet_read(ZBX_SOCKET socket_fd, char *buf, size_t *buf_left, size_t *buf_offset)
{
	unsigned char	c, c1, c2, c3;
	ssize_t		rc = ZBX_PROTO_ERROR;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (;;)
	{
		if (1 > (rc = telnet_socket_read(socket_fd, &c1, 1)))
			break;

		zabbix_log(LOG_LEVEL_DEBUG, "%s() c1:[%x=%c]", __func__, c1, isprint(c1) ? c1 : ' ');

		switch (c1)
		{
			case CMD_IAC:
				while (0 == (rc = telnet_socket_read(socket_fd, &c2, 1)))
					;

				if (ZBX_PROTO_ERROR == rc)
					goto end;

				zabbix_log(LOG_LEVEL_DEBUG, "%s() c2:%x", __func__, c2);

				switch (c2)
				{
					case CMD_IAC: 	/* only IAC needs to be doubled to be sent as data */
						if (0 < *buf_left)
						{
							buf[(*buf_offset)++] = (char)c2;
							(*buf_left)--;
						}
						break;
					case CMD_WILL:
					case CMD_WONT:
					case CMD_DO:
					case CMD_DONT:
						while (0 == (rc = telnet_socket_read(socket_fd, &c3, 1)))
							;

						if (ZBX_PROTO_ERROR == rc)
							goto end;

						zabbix_log(LOG_LEVEL_DEBUG, "%s() c3:%x", __func__, c3);

						/* reply to all options with "WONT" or "DONT", */
						/* unless it is Suppress Go Ahead (SGA)        */

						c = CMD_IAC;
						telnet_socket_write(socket_fd, &c, 1);

						if (CMD_WONT == c2)
							c = CMD_DONT;	/* the only valid response */
						else if (CMD_DONT == c2)
							c = CMD_WONT;	/* the only valid response */
						else if (OPT_SGA == c3)
							c = (c2 == CMD_DO ? CMD_WILL : CMD_DO);
						else
							c = (c2 == CMD_DO ? CMD_WONT : CMD_DONT);

						telnet_socket_write(socket_fd, &c, 1);
						telnet_socket_write(socket_fd, &c3, 1);
						break;
					default:
						break;
				}
				break;
			default:
				if (0 < *buf_left)
				{
					buf[(*buf_offset)++] = (char)c1;
					(*buf_left)--;
				}
				break;
		}
	}
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, (int)rc);

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Comments: converts CR+LF to Unix LF and clears CR+NUL                      *
 *                                                                            *
 ******************************************************************************/
static void	convert_telnet_to_unix_eol(char *buf, size_t *offset)
{
	size_t	i, sz = *offset, new_offset;

	new_offset = 0;

	for (i = 0; i < sz; i++)
	{
		if (i + 1 < sz && '\r' == buf[i] && '\n' == buf[i + 1])		/* CR+LF (Windows) */
		{
			buf[new_offset++] = '\n';
			i++;
		}
		else if (i + 1 < sz && '\r' == buf[i] && '\0' == buf[i + 1])	/* CR+NUL */
		{
			i++;
		}
		else if (i + 1 < sz && '\n' == buf[i] && '\r' == buf[i + 1])	/* LF+CR */
		{
			buf[new_offset++] = '\n';
			i++;
		}
		else if ('\r' == buf[i])					/* CR */
		{
			buf[new_offset++] = '\n';
		}
		else
			buf[new_offset++] = buf[i];
	}

	*offset = new_offset;
}

static void	convert_unix_to_telnet_eol(const char *buf, size_t offset, char *out_buf, size_t *out_offset)
{
	size_t	i;

	*out_offset = 0;

	for (i = 0; i < offset; i++)
	{
		if ('\n' != buf[i])
		{
			out_buf[(*out_offset)++] = buf[i];
		}
		else
		{
			out_buf[(*out_offset)++] = '\r';
			out_buf[(*out_offset)++] = '\n';
		}
	}
}

static char	telnet_lastchar(const char *buf, size_t offset)
{
	while (0 < offset)
	{
		offset--;
		if (' ' != buf[offset])
			return buf[offset];
	}

	return '\0';
}

static int	telnet_rm_echo(char *buf, size_t *offset, const char *echo, size_t len)
{
	if (0 == memcmp(buf, echo, len))
	{
		*offset -= len;
		memmove(&buf[0], &buf[len], *offset * sizeof(char));

		return SUCCEED;
	}

	return FAIL;
}

static void	telnet_rm_prompt(const char *buf, size_t *offset)
{
	unsigned char	state = 0;	/* 0 - init, 1 - prompt */

	while (0 < *offset)
	{
		(*offset)--;
		if (0 == state && buf[*offset] == prompt_char)
			state = 1;
		if (1 == state && buf[*offset] == '\n')
			break;
	}
}

int	telnet_test_login(ZBX_SOCKET socket_fd)
{
	char	buf[MAX_BUFFER_LEN];
	size_t	sz, offset;
	int	rc, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	sz = sizeof(buf);
	offset = 0;
	while (ZBX_PROTO_ERROR != (rc = telnet_read(socket_fd, buf, &sz, &offset)))
	{
		if (':' == telnet_lastchar(buf, offset))
			break;
	}

	convert_telnet_to_unix_eol(buf, &offset);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() login prompt:'%.*s'", __func__, (int)offset, buf);

	if (ZBX_PROTO_ERROR != rc)
		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	telnet_login(ZBX_SOCKET socket_fd, const char *username, const char *password, AGENT_RESULT *result)
{
	char	buf[MAX_BUFFER_LEN], c;
	size_t	sz, offset;
	int	rc, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	sz = sizeof(buf);
	offset = 0;
	while (ZBX_PROTO_ERROR != (rc = telnet_read(socket_fd, buf, &sz, &offset)))
	{
		if (':' == telnet_lastchar(buf, offset))
			break;
	}

	convert_telnet_to_unix_eol(buf, &offset);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() login prompt:'%.*s'", __func__, (int)offset, buf);

	if (ZBX_PROTO_ERROR == rc)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "No login prompt."));
		goto fail;
	}

	telnet_socket_write(socket_fd, username, strlen(username));
	telnet_socket_write(socket_fd, "\r\n", 2);

	sz = sizeof(buf);
	offset = 0;
	while (ZBX_PROTO_ERROR != (rc = telnet_read(socket_fd, buf, &sz, &offset)))
	{
		if (':' == telnet_lastchar(buf, offset))
			break;
	}

	convert_telnet_to_unix_eol(buf, &offset);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() password prompt:'%.*s'", __func__, (int)offset, buf);

	if (ZBX_PROTO_ERROR == rc)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "No password prompt."));
		goto fail;
	}

	telnet_socket_write(socket_fd, password, strlen(password));
	telnet_socket_write(socket_fd, "\r\n", 2);

	sz = sizeof(buf);
	offset = 0;
	while (ZBX_PROTO_ERROR != (rc = telnet_read(socket_fd, buf, &sz, &offset)))
	{
		if ('$' == (c = telnet_lastchar(buf, offset)) || '#' == c || '>' == c || '%' == c)
		{
			prompt_char = c;
			break;
		}
	}

	convert_telnet_to_unix_eol(buf, &offset);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() prompt:'%.*s'", __func__, (int)offset, buf);

	if (ZBX_PROTO_ERROR == rc)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Login failed."));
		goto fail;
	}

	ret = SUCCEED;
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	telnet_execute(ZBX_SOCKET socket_fd, const char *command, AGENT_RESULT *result, const char *encoding)
{
	char	buf[MAX_BUFFER_LEN];
	size_t	sz, offset;
	int	rc, ret = FAIL;
	char	*command_lf = NULL, *command_crlf = NULL;
	size_t	i, offset_lf, offset_crlf;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* `command' with multiple lines may contain CR+LF from the browser;	*/
	/* it should be converted to plain LF to remove echo later on properly	*/
	offset_lf = strlen(command);
	command_lf = (char *)zbx_malloc(command_lf, offset_lf + 1);
	zbx_strlcpy(command_lf, command, offset_lf + 1);
	convert_telnet_to_unix_eol(command_lf, &offset_lf);

	/* telnet protocol requires that end-of-line is transferred as CR+LF	*/
	command_crlf = (char *)zbx_malloc(command_crlf, offset_lf * 2 + 1);
	convert_unix_to_telnet_eol(command_lf, offset_lf, command_crlf, &offset_crlf);

	telnet_socket_write(socket_fd, command_crlf, offset_crlf);
	telnet_socket_write(socket_fd, "\r\n", 2);

	sz = sizeof(buf);
	offset = 0;
	while (ZBX_PROTO_ERROR != (rc = telnet_read(socket_fd, buf, &sz, &offset)))
	{
		if (prompt_char == telnet_lastchar(buf, offset))
			break;
	}

	convert_telnet_to_unix_eol(buf, &offset);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() command output:'%.*s'", __func__, (int)offset, buf);

	if (ZBX_PROTO_ERROR == rc)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot find prompt after command execution: %s",
				strerror_from_system(zbx_socket_last_error())));
		goto fail;
	}

	telnet_rm_echo(buf, &offset, command_lf, offset_lf);

	/* multi-line commands may have returned additional prompts;	*/
	/* this is not a perfect solution, because in case of multiple	*/
	/* multi-line shell statements these prompts might appear in	*/
	/* the middle of the output, but we still try to be helpful by	*/
	/* removing additional prompts at least from the beginning	*/
	for (i = 0; i < offset_lf; i++)
	{
		if ('\n' == command_lf[i])
		{
			if (SUCCEED != telnet_rm_echo(buf, &offset, "$ ", 2) &&
				SUCCEED != telnet_rm_echo(buf, &offset, "# ", 2) &&
				SUCCEED != telnet_rm_echo(buf, &offset, "> ", 2) &&
				SUCCEED != telnet_rm_echo(buf, &offset, "% ", 2))
			{
				break;
			}
		}
	}

	telnet_rm_echo(buf, &offset, "\n", 1);
	telnet_rm_prompt(buf, &offset);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() stripped command output:'%.*s'", __func__, (int)offset, buf);

	if (MAX_BUFFER_LEN == offset)
		offset--;
	buf[offset] = '\0';

	SET_STR_RESULT(result, convert_to_utf8(buf, offset, encoding));
	ret = SUCCEED;
fail:
	zbx_free(command_lf);
	zbx_free(command_crlf);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
