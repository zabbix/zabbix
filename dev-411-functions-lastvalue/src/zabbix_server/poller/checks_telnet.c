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

static int	telnet_waitsocket(int socket_fd, int mode/* 1 - read; 0 - write */)
{
/*	const char	*__function_name = "telnet_waitsocket"; */
	struct timeval	tv;
	int		rc;
	fd_set		fd, *writefd = NULL, *readfd = NULL;

	tv.tv_sec = 0;
	tv.tv_usec = 100000;	/* 1/10 sec. */

	FD_ZERO(&fd);
	FD_SET(socket_fd, &fd);

	if (1 == mode)
		readfd = &fd;
	else
		writefd = &fd;

	rc = select(socket_fd + 1, readfd, writefd, NULL, &tv);

/*	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, rc); */

	return rc;
}

static ssize_t	telnet_socket_read(int socket_fd, void *buf, size_t count)
{
/*	const char	*__function_name = "telnet_socket_read"; */
	ssize_t		rc;

	while (-1 == (rc = read(socket_fd, buf, count)))
	{
		if (errno == EAGAIN)
		{
			if (0 >= (rc = telnet_waitsocket(socket_fd, count)))
				break;
			continue;
		}
		break;
	}

/*	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, rc); */

	return rc;
}

static ssize_t	telnet_socket_write(int socket_fd, const void *buf, size_t count)
{
/*	const char	*__function_name = "telnet_socket_write"; */
	ssize_t		rc;

	while (-1 == (rc = write(socket_fd, buf, count)))
	{
		if (errno == EAGAIN)
		{
			telnet_waitsocket(socket_fd, 0);
			continue;
		}
		break;
	}

/*	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, rc); */

	return rc;
}

/*
 * Read data from the telnet server.
 * If succeed return 0, -1 if error occured
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

		switch (c1) {
		case 255:	/* Interpret as Command (IAC) */
			while (0 == (rc = telnet_socket_read(socket_fd, &c2, 1)))
				;

			if (-1 == rc)
				goto end;

			switch (c2){
			case 255: 	/* Only the IAC need be doubled to be sent as data */
				if (*buf_left > 0)
				{
					buf[(*buf_offset)++] = (char)c2;
					(*buf_left)--;
				}
				break;
			case 251:	/* Option code (WILL) */
			case 252:	/* Option code (WON'T) */
			case 253:	/* Option code (DO) */
			case 254:	/* Option code (DON'T) */
				/* reply to all commands with "WONT", unless it is SGA (suppres go ahead) */
				while (0 == (rc = telnet_socket_read(socket_fd, &c3, 1)))
					;

				if (-1 == rc)
					goto end;

				c = 255;
				telnet_socket_write(socket_fd, &c, 1);	/* IAC */
				c = (c2 == 253) ? 252 : 254; /* DO ? WON'T : DON'T */

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
static void	unix_eol(char *buf, size_t *offset)
{
	size_t	i, sz;

	if (*offset == 0)
		return;

	sz = *offset - 1;

	for (i = 0; i < *offset - 1; i++)
	{
		if ((buf[i] == '\r' && buf[i + 1] == '\n'))	/* CR+LF (Windows) */
		{
			buf[i] = '\n';	/* LF (Unix) */
			(*offset)--; i++;
			memmove(&buf[i], &buf[i + 1], (*offset - i) * sizeof(char));
		}
		if (buf[i] == '\r' && buf[i + 1] == '\0')	/* CR+NUL */
		{
			*offset -= 2;
			memmove(&buf[i], &buf[i + 2], (*offset - i) * sizeof(char));
		}
		if ((buf[i] == '\n' && buf[i + 1] == '\r'))	/* LF+CR */
		{
			(*offset)--; i++;
			memmove(&buf[i], &buf[i + 1], (*offset - i) * sizeof(char));
		}
		else if (buf[i] == '\r')	/* CR */
			buf[i] = '\n';	/* LF (Unix) */
	}
}

static char	telnet_lastchar(const char *buf, size_t offset)
{
	while (offset-- > 0)
		if (buf[offset] != ' ')
			return buf[offset];

	return '\0';
}

static void	telnet_rm_echo(char *buf, size_t *offset, const char *echo, size_t len)
{
	if (0 == memcmp(buf, echo, len))
	{
		*offset -= len;
		memmove(&buf[0], &buf[len], *offset * sizeof(char));
	}
}

static void	telnet_rm_prompt(const char *buf, size_t *offset)
{
	if (*offset == 0)
		return;

	while ((*offset)-- > 0)
		if (buf[*offset] == '\n')
			break;
}

static int	telnet_login(int socket_fd, const char *username,
		const char *password, AGENT_RESULT *result)
{
	const char	*__function_name = "telnet_login";
	char		buf[MAX_BUF_LEN], c;
	size_t		sz, offset;
	int		rc, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sz = sizeof(buf);
	offset = 0;
	while (-1 != (rc = telnet_read(socket_fd, buf, &sz, &offset)))
		if (':' == telnet_lastchar(buf, offset))
			break;

	unix_eol(buf, &offset);
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

	unix_eol(buf, &offset);
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

	unix_eol(buf, &offset);
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
	char	buf[MAX_BUF_LEN];
	size_t	sz, offset;
	int	rc, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	telnet_socket_write(socket_fd, command, strlen(command));
	telnet_socket_write(socket_fd, "\r\n", 2);

	sz = sizeof(buf);
	offset = 0;
	while (-1 != (rc = telnet_read(socket_fd, buf, &sz, &offset)))
		if (prompt_char == telnet_lastchar(buf, offset))
			break;

	unix_eol(buf, &offset);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() command output:'%.*s'",
			__function_name, offset, buf);

	if (-1 == rc)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL,
				"No prompt: %s",
				zbx_tcp_strerror()));
		goto fail;
	}

	telnet_rm_echo(buf, &offset, command, strlen(command));
	telnet_rm_echo(buf, &offset, "\n", 1);
	telnet_rm_prompt(buf, &offset);

	if (MAX_BUF_LEN == offset)
		offset--;
	buf[offset] = '\0';

	SET_STR_RESULT(result, convert_to_utf8(buf, offset, encoding));

	ret = SUCCEED;
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name,
			zbx_result_string(ret));

	return ret;
}

/* Example telnet.run["ls /"] */
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
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot connect to TELNET server: %s",
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
		if (port_int < 1 || port_int > 65536)
			return NOTSUPPORTED;

		item->host.port = (unsigned short)port_int;
	}
	else
		item->host.port = 23;

	return telnet_run(item, result, encoding);
}
