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

#include "checks_ssh.h"

#ifdef HAVE_SSH2

#include "comms.h"
#include "log.h"

#define SSH_RUN_KEY	"ssh.run"

static const char	*password;

static void	kbd_callback(const char *name, int name_len, const char *instruction,
		int instruction_len, int num_prompts,
		const LIBSSH2_USERAUTH_KBDINT_PROMPT *prompts,
		LIBSSH2_USERAUTH_KBDINT_RESPONSE *responses, void **abstract)
{
	(void)name;
	(void)name_len;
	(void)instruction;
	(void)instruction_len;

	if (num_prompts == 1)
	{
		responses[0].text = strdup(password);
		responses[0].length = strlen(password);
	}

	(void)prompts;
	(void)abstract;
}

static int	waitsocket(int socket_fd, LIBSSH2_SESSION *session)
{
	struct timeval	tv;
	int		rc, dir;
	fd_set		fd, *writefd = NULL, *readfd = NULL;

	tv.tv_sec = 10;
	tv.tv_usec = 0;

	FD_ZERO(&fd);
	FD_SET(socket_fd, &fd);

	/* now make sure we wait in the correct direction */
	dir = libssh2_session_block_directions(session);

	if (0 != (dir & LIBSSH2_SESSION_BLOCK_INBOUND))
		readfd = &fd;

	if (0 != (dir & LIBSSH2_SESSION_BLOCK_OUTBOUND))
		writefd = &fd;

	rc = select(socket_fd + 1, readfd, writefd, NULL, &tv);

	return rc;
}

/* Example ssh.run["ls /"] */
static int	ssh_run(DC_ITEM *item, AGENT_RESULT *result, const char *encoding)
{
	const char	*__function_name = "ssh_run";
	zbx_sock_t	s;
	LIBSSH2_SESSION	*session;
	LIBSSH2_CHANNEL	*channel;
	int		auth_pw = 0, rc, ret = NOTSUPPORTED,
			exitcode, bytecount = 0;
	char		*conn, buffer[MAX_BUFFER_LEN], buf[16], *userauthlist,
			*publickey = NULL, *privatekey = NULL;
	size_t		sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	conn = item->host.useip == 1 ? item->host.ip : item->host.dns;

	if (FAIL == zbx_tcp_connect(&s, CONFIG_SOURCE_IP, conn, item->host.port, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot connect to SSH server: %s",
				zbx_tcp_strerror()));
		goto close;
	}

	/* initializes an SSH session object
	 */
	if (NULL == (session = libssh2_session_init()))
	{
		SET_MSG_RESULT(result, strdup("Failure initializing SSH session"));
		goto tcp_close;
	}

	/* set blocking mode on session */
	libssh2_session_set_blocking(session, 1);

	/* Create a session instance and start it up. This will trade welcome
	 * banners, exchange keys, and setup crypto, compression, and MAC layers
	 */
	if (0 != libssh2_session_startup(session, s.socket))
	{
		SET_MSG_RESULT(result, strdup("Failure establishing SSH session"));
		goto session_free;
	}

	/* check what authentication methods are available */
	if (NULL != (userauthlist = libssh2_userauth_list(session, item->username, strlen(item->username))))
	{
		if (NULL != strstr(userauthlist, "password"))
			auth_pw |= 1;
		if (NULL != strstr(userauthlist, "keyboard-interactive"))
			auth_pw |= 2;
		if (NULL != strstr(userauthlist, "publickey"))
			auth_pw |= 4;
	}
	else
	{
		SET_MSG_RESULT(result, strdup("Failure obtaining authentication methods"));
		goto session_close;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() Supported authentication methods:'%s'", __function_name, userauthlist);

	switch (item->authtype) {
	case ITEM_AUTHTYPE_PASSWORD:
		if (auth_pw & 1)
		{
			/* We could authenticate via password */
			if (0 != libssh2_userauth_password(session, item->username, item->password))
			{
				SET_MSG_RESULT(result, strdup("Authentication by password failed"));
				goto session_close;
			}
			else
				zabbix_log(LOG_LEVEL_DEBUG, "%s() Authentication by password succeeded.",
						__function_name);
		}
		else if (auth_pw & 2)
		{
			/* Or via keyboard-interactive */
			password = item->password;
			if (0 != libssh2_userauth_keyboard_interactive(session, item->username, &kbd_callback))
			{
				SET_MSG_RESULT(result, strdup("Authentication by keyboard-interactive failed"));
				goto session_close;
			}
			else
				zabbix_log(LOG_LEVEL_DEBUG, "%s() Authentication by keyboard-interactive succeeded.",
						__function_name);
		}
		else
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Unsupported authentication method."
					" Supported methods: %s", userauthlist));
			goto session_close;
		}
		break;
	case ITEM_AUTHTYPE_PUBLICKEY:
		if (auth_pw & 4)
		{
			if (NULL == CONFIG_SSH_KEY_LOCATION)
			{
				SET_MSG_RESULT(result, strdup("Authentication by public key failed."
						" SSHKeyLocation option is not set"));
				goto session_close;
			}

			/* Or by public key */
			publickey = zbx_dsprintf(publickey, "%s/%s", CONFIG_SSH_KEY_LOCATION, item->publickey);
			privatekey = zbx_dsprintf(privatekey, "%s/%s", CONFIG_SSH_KEY_LOCATION, item->privatekey);
			rc = libssh2_userauth_publickey_fromfile(session, item->username, publickey,
					privatekey, item->password);
			zbx_free(publickey);
			zbx_free(privatekey);

			if (0 != rc)
			{
				SET_MSG_RESULT(result, strdup("Authentication by public key failed"));
				goto session_close;
			}
			else
				zabbix_log(LOG_LEVEL_DEBUG, "%s() Authentication by public key succeeded.",
						__function_name);
		}
		else
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Unsupported authentication method."
					" Supported methods: %s", userauthlist));
			goto session_close;
		}
		break;
	}

	/* Exec non-blocking on the remove host */
	while (NULL == (channel = libssh2_channel_open_session(session)))
	{
		switch (libssh2_session_last_error(session, NULL, NULL, 0)) {
		/* Marked for non-blocking I/O but the call would block. */
		case LIBSSH2_ERROR_EAGAIN:
			waitsocket(s.socket, session);
			continue;
		default:
			SET_MSG_RESULT(result, strdup("Failure establishing a generic session channel"));
			goto session_close;
		}
	}

	win2unix_eol(item->params);	/* CR+LF (Windows) => LF (Unix) */
	/* request a shell on a channel and execute command */
	while (0 != (rc = libssh2_channel_exec(channel, item->params)))
	{
		switch (rc) {
		case LIBSSH2_ERROR_EAGAIN:
			waitsocket(s.socket, session);
			continue;
		default:
			SET_MSG_RESULT(result, strdup("Failure requesting a shell"));
			goto channel_close;
		}
	}

	for (;;)
	{
		/* loop until we block */
		do
		{
			if (0 < (rc = libssh2_channel_read(channel, buf, sizeof(buf))))
			{
				sz = (size_t)rc;
				if (sz > MAX_BUFFER_LEN - (bytecount + 1))
					sz = MAX_BUFFER_LEN - (bytecount + 1);
				if (0 == sz)
					continue;

				memcpy(buffer + bytecount, buf, sz);
				bytecount += sz;
			}
		}
		while (rc > 0);

		/* this is due to blocking that would occur otherwise so we loop on
		 * this condition
		 */
		if (LIBSSH2_ERROR_EAGAIN == rc)
			waitsocket(s.socket, session);
		else if (rc < 0)
		{
			SET_MSG_RESULT(result, strdup("Failure reading data from SSH server"));
			goto channel_close;
		}
		else
			break;
	}

	buffer[bytecount] = '\0';
	SET_STR_RESULT(result, convert_to_utf8(buffer, bytecount, encoding));

	ret = SYSINFO_RET_OK;

channel_close:
	/* Close an active data channel
	 */
	exitcode = 127;
	while (0 != (rc = libssh2_channel_close(channel)))
	{
		switch (rc) {
		case LIBSSH2_ERROR_EAGAIN:
			waitsocket(s.socket, session);
			continue;
		default:
			zabbix_log(LOG_LEVEL_DEBUG, "%s() Failure closing a generic session channel",
					__function_name);
			break;
		}
	}

	if (0 == rc)
		exitcode = libssh2_channel_get_exit_status(channel);
	zabbix_log(LOG_LEVEL_DEBUG, "%s() exitcode: %d bytecount: %d",
			__function_name, exitcode, bytecount);

	libssh2_channel_free(channel);
	channel = NULL;

session_close:
	libssh2_session_disconnect(session, "Normal Shutdown");

session_free:
	libssh2_session_free(session);

tcp_close:
	zbx_tcp_close(&s);

close:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

int	get_value_ssh(DC_ITEM *item, AGENT_RESULT *result)
{
	char	cmd[MAX_STRING_LEN], params[MAX_STRING_LEN], dns[HOST_DNS_LEN_MAX],
		port[8], encoding[32];
	int	port_int;

	if (0 == parse_command(item->key, cmd, sizeof(cmd), params, sizeof(params)))
		return NOTSUPPORTED;

	if (0 != strcmp(SSH_RUN_KEY, cmd))
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
		item->host.port = ZBX_DEFAULT_SSH_PORT;

	return ssh_run(item, result, encoding);
}

#endif	/* HAVE_SSH2 */
