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

#include "checks_telnet.h"

#include "comms.h"
#include "log.h"

#define TELNET_RUN_KEY	"telnet.run"

static char	prompt_char = '\0';

#define	WAIT_READ	0
#define	WAIT_WRITE	1

static int	telnet_waitsocket(int socket_fd, int mode)
{
	const char	*__function_name = "telnet_waitsocket";
	struct timeval	tv;
	int		rc;
	fd_set		fd, *readfd = NULL, *writefd = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	tv.tv_sec = 0;
	tv.tv_usec = 100000;	/* 1/10 sec. */

	FD_ZERO(&fd);
	FD_SET(socket_fd, &fd);

	if (WAIT_READ == mode)
		readfd = &fd;
	else
		writefd = &fd;

	/* -1 - error			*/
	/*  0 - nothing changed		*/
	/*  1 - read/write possible	*/
	rc = select(socket_fd + 1, readfd, writefd, NULL, &tv);

	if (-1 == rc)
		zabbix_log(LOG_LEVEL_DEBUG, "%s() rc:%d error: %s", __function_name, rc, zbx_strerror(errno));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, rc);

	return rc;
}

static ssize_t	telnet_socket_read(int socket_fd, void *buf, size_t count)
{
	const char	*__function_name = "telnet_socket_read";
	ssize_t		rc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (-1 == (rc = read(socket_fd, buf, count)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() rc:%d error: %s", __function_name, rc, zbx_strerror(errno));

		if (errno == EAGAIN)
		{
			/* Wait a bit. If there is still an error or there is no error, but still */
			/* no input available, we assume the other side has nothing more to say.  */
			if (0 >= (rc = telnet_waitsocket(socket_fd, WAIT_READ)))
				goto ret;

			continue;
		}

		break;
	}

	/* when read() returns 0, it means EOF */
	/* let's consider it a permanent error */
	/* note that if telnet_waitsocket() is */
	/* zero, it is not a permanent condition */
	if (0 == rc)
		rc = -1;

ret:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, rc);

	return rc;
}

static ssize_t	telnet_socket_write(int socket_fd, const void *buf, size_t count)
{
	const char	*__function_name = "telnet_socket_write";
	ssize_t		rc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (-1 == (rc = write(socket_fd, buf, count)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() rc:%d error: %s", __function_name, rc, zbx_strerror(errno));

		if (errno == EAGAIN)
		{
			telnet_waitsocket(socket_fd, WAIT_WRITE);
			continue;
		}

		break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, rc);

	return rc;
}

#define	CMD_IAC		255
#define	CMD_WILL	251
#define	CMD_WONT	252
#define	CMD_DO		253
#define	CMD_DONT	254
#define	OPT_SGA		3

/*
 * Read data from the telnet server.
 * If succeed return 0, -1 if error occurred
 */
static ssize_t	telnet_read(int socket_fd, char *buf, size_t *buf_left, size_t *buf_offset)
{
	const char	*__function_name = "telnet_read";
	unsigned char	c, c1, c2, c3;
	ssize_t		rc = -1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for ( ;; )
	{
		if (1 != (rc = telnet_socket_read(socket_fd, &c1, 1)))
			break;

		zabbix_log(LOG_LEVEL_DEBUG, "%s() c1:[%x=%c]", __function_name, c1, isprint(c1) ? c1 : ' ');

		switch (c1) {
		case CMD_IAC:
			while (0 == (rc = telnet_socket_read(socket_fd, &c2, 1)))
				;

			if (-1 == rc)
				goto end;

			zabbix_log(LOG_LEVEL_DEBUG, "%s() c2:%x", __function_name, c2);

			switch (c2) {
			case CMD_IAC: 	/* Only IAC needs to be doubled to be sent as data */
				if (*buf_left > 0)
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

				if (-1 == rc)
					goto end;

				zabbix_log(LOG_LEVEL_DEBUG, "%s() c3:%x", __function_name, c3);

				/* reply to all options with "WONT" or "DONT", */
				/* unless it is Suppress Go Ahead (SGA)        */

				c = CMD_IAC;
				telnet_socket_write(socket_fd, &c, 1);

				if (CMD_WONT == c2)
					c = CMD_DONT; /* the only valid response */
				else if (CMD_DONT == c2)
					c = CMD_WONT; /* the only valid response */
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
			if (*buf_left > 0)
			{
				buf[(*buf_offset)++] = (char)c1;
				(*buf_left)--;
			}
			break;
		}
	}
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, (int)rc);

	return rc;
}

/*
 * Convert CR+LF to Unix LF and clear CR+NUL
 */
static void	convert_telnet_to_unix_eol(char *buf, size_t *offset)
{
	size_t	i, sz = *offset, new_offset;

	new_offset = 0;

	for (i = 0; i < sz; i++)
	{
		if (i + 1 < sz && buf[i] == '\r' && buf[i + 1] == '\n')		/* CR+LF (Windows) */
		{
			buf[new_offset++] = '\n';
			i++;
		}
		else if (i + 1 < sz && buf[i] == '\r' && buf[i + 1] == '\0')	/* CR+NUL */
		{
			i++;
		}
		else if (i + 1 < sz && buf[i] == '\n' && buf[i + 1] == '\r')	/* LF+CR */
		{
			buf[new_offset++] = '\n';
			i++;
		}
		else if (buf[i] == '\r')					/* CR */
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
		if (buf[i] != '\n')
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
	while (offset > 0)
	{
		offset--;
		if (buf[offset] != ' ')
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
	else
		return FAIL;
}

static void	telnet_rm_prompt(const char *buf, size_t *offset)
{
	unsigned char	state = 0;	/* 0 - init, 1 - prompt */

	while (*offset > 0)
	{
		(*offset)--;
		if (0 == state && buf[*offset] == prompt_char)
			state = 1;
		if (1 == state && buf[*offset] == '\n')
			break;
	}
}

static int	telnet_login(int socket_fd, const char *username,
		const char *password, AGENT_RESULT *result)
{
	const char	*__function_name = "telnet_login";
	char		buf[MAX_BUFFER_LEN], c;
	size_t		sz, offset;
	int		rc, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sz = sizeof(buf);
	offset = 0;
	while (-1 != (rc = telnet_read(socket_fd, buf, &sz, &offset)))
		if (':' == telnet_lastchar(buf, offset))
			break;

	convert_telnet_to_unix_eol(buf, &offset);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() login prompt:'%.*s'",
			__function_name, offset, buf);

	if (-1 == rc)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "No login prompt"));
		goto fail;
	}

	telnet_socket_write(socket_fd, username, strlen(username));
	telnet_socket_write(socket_fd, "\r\n", 2);

	sz = sizeof(buf);
	offset = 0;
	while (-1 != (rc == telnet_read(socket_fd, buf, &sz, &offset)))
		if (':' == telnet_lastchar(buf, offset))
			break;

	convert_telnet_to_unix_eol(buf, &offset);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() password prompt:'%.*s'",
			__function_name, offset, buf);

	if (-1 == rc)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "No password prompt"));
		goto fail;
	}

	telnet_socket_write(socket_fd, password, strlen(password));
	telnet_socket_write(socket_fd, "\r\n", 2);

	sz = sizeof(buf);
	offset = 0;
	while (-1 != (rc = telnet_read(socket_fd, buf, &sz, &offset)))
		if ('$' == (c = telnet_lastchar(buf, offset)) || '#' == c || '>' == c || '%' == c)
		{
			prompt_char = c;
			break;
		}

	convert_telnet_to_unix_eol(buf, &offset);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() prompt:'%.*s'",
			__function_name, offset, buf);

	if (-1 == rc)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Login failed"));
		goto fail;
	}

	ret = SUCCEED;
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name,
			zbx_result_string(ret));

	return ret;
}

static int	telnet_execute(int socket_fd, const char *command,
		AGENT_RESULT *result, const char *encoding)
{
	const char	*__function_name = "telnet_execute";
	char		buf[MAX_BUFFER_LEN];
	size_t		sz, offset;
	int		rc, ret = FAIL;
	char		*command_lf = NULL, *command_crlf = NULL;
	size_t		i, offset_lf, offset_crlf;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* `command' with multiple lines may contain CR+LF from the browser;	*/
	/* it should be converted to plain LF to remove echo later on properly	*/
	offset_lf = strlen(command);
	command_lf = zbx_malloc(command_lf, offset_lf + 1);
	zbx_strlcpy(command_lf, command, offset_lf + 1);
	convert_telnet_to_unix_eol(command_lf, &offset_lf);

	/* telnet protocol requires that end-of-line is transferred as CR+LF	*/
	command_crlf = zbx_malloc(command_crlf, offset_lf * 2 + 1);
	convert_unix_to_telnet_eol(command_lf, offset_lf, command_crlf, &offset_crlf);

	telnet_socket_write(socket_fd, command_crlf, offset_crlf);
	telnet_socket_write(socket_fd, "\r\n", 2);

	sz = sizeof(buf);
	offset = 0;
	while (-1 != (rc = telnet_read(socket_fd, buf, &sz, &offset)))
		if (prompt_char == telnet_lastchar(buf, offset))
			break;

	convert_telnet_to_unix_eol(buf, &offset);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() command output:'%.*s'",
			__function_name, offset, buf);

	if (-1 == rc)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "no prompt: %s", zbx_tcp_strerror()));
		goto fail;
	}

	telnet_rm_echo(buf, &offset, command_lf, offset_lf);

	/* multi-line commands may have returned additional prompts;	*/
	/* this is not a perfect solution, because in case of multiple	*/
	/* multi-line shell statements these prompts might appear in	*/
	/* the middle of the output, but we still try to be helpful by	*/
	/* removing additional prompts at least from the beginning	*/
	for (i = 0; i < offset_lf; i++)
		if (command_lf[i] == '\n')
			if (SUCCEED != telnet_rm_echo(buf, &offset, "$ ", 2) &&
				SUCCEED != telnet_rm_echo(buf, &offset, "# ", 2) &&
				SUCCEED != telnet_rm_echo(buf, &offset, "> ", 2) &&
				SUCCEED != telnet_rm_echo(buf, &offset, "% ", 2))
			{
				break;
			}

	telnet_rm_echo(buf, &offset, "\n", 1);
	telnet_rm_prompt(buf, &offset);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() stripped command output:'%.*s'",
			__function_name, offset, buf);

	if (MAX_BUFFER_LEN == offset)
		offset--;
	buf[offset] = '\0';

	SET_STR_RESULT(result, convert_to_utf8(buf, offset, encoding));

	ret = SUCCEED;
fail:
	zbx_free(command_lf);
	zbx_free(command_crlf);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name,
			zbx_result_string(ret));

	return ret;
}

/*
 * Example: telnet.run["ls /"]
 */
static int	telnet_run(DC_ITEM *item, AGENT_RESULT *result, const char *encoding)
{
	const char	*__function_name = "telnet_run";
	zbx_sock_t	s;
	int		ret = NOTSUPPORTED, flags;
	char		*conn;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	conn = item->host.useip == 1 ? item->host.ip : item->host.dns;

	if (FAIL == zbx_tcp_connect(&s, CONFIG_SOURCE_IP, conn, item->host.port, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "cannot connect to TELNET server: %s",
				zbx_tcp_strerror()));
		goto close;
	}

	flags = fcntl(s.socket, F_GETFL);
	if (0 == (flags & O_NONBLOCK))
		fcntl(s.socket, F_SETFL, flags | O_NONBLOCK);

	if (FAIL == telnet_login(s.socket, item->username, item->password, result))
		goto tcp_close;

	if (FAIL == telnet_execute(s.socket, item->params, result, encoding))
		goto tcp_close;

	ret = SUCCEED;

tcp_close:
	zbx_tcp_close(&s);

close:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

int	get_value_telnet(DC_ITEM *item, AGENT_RESULT *result)
{
	char	cmd[MAX_STRING_LEN], params[MAX_STRING_LEN], dns[HOST_DNS_LEN_MAX],
		port[8], encoding[32];
	int	port_int;

	if (0 == parse_command(item->key, cmd, sizeof(cmd), params, sizeof(params)))
		return NOTSUPPORTED;

	if (0 != strcmp(TELNET_RUN_KEY, cmd))
		return NOTSUPPORTED;

	if (num_param(params) > 4)
		return NOTSUPPORTED;

	if (0 != get_param(params, 2, dns, sizeof(dns)))
		*dns = '\0';

	if ('\0' != *dns)
	{
		zbx_strlcpy(item->host.dns, dns, sizeof(item->host.dns));
		item->host.useip = 0;
	}

	if (0 != get_param(params, 3, port, sizeof(port)))
		*port = '\0';

	if (0 != get_param(params, 4, encoding, sizeof(encoding)))
		*encoding = '\0';

	if ('\0' != *port)
	{
		port_int = atoi(port);
		if (port_int < 1 || port_int > 65535)
			return NOTSUPPORTED;

		item->host.port = (unsigned short)port_int;
	}
	else
		item->host.port = ZBX_DEFAULT_TELNET_PORT;

	return telnet_run(item, result, encoding);
}
