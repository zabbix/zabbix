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
		responses[0].text = zbx_strdup(NULL, password);
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

/* example ssh.run["ls /"] */
static int	ssh_run(DC_ITEM *item, AGENT_RESULT *result, const char *encoding)
{
	const char	*__function_name = "ssh_run";
	zbx_sock_t	s;
	LIBSSH2_SESSION	*session;
	LIBSSH2_CHANNEL	*channel;
	int		auth_pw = 0, rc, ret = NOTSUPPORTED,
			exitcode, bytecount = 0;
	char		buffer[MAX_BUFFER_LEN], buf[16], *userauthlist,
			*publickey = NULL, *privatekey = NULL, *ssherr;
	size_t		sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == zbx_tcp_connect(&s, CONFIG_SOURCE_IP, item->interface.addr, item->interface.port, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot connect to SSH server: %s",
				zbx_tcp_strerror()));
		goto close;
	}

	/* initializes an SSH session object */
	if (NULL == (session = libssh2_session_init()))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot initialize SSH session"));
		goto tcp_close;
	}

	/* set blocking mode on session */
	libssh2_session_set_blocking(session, 1);

	/* Create a session instance and start it up. This will trade welcome */
	/* banners, exchange keys, and setup crypto, compression, and MAC layers */
	if (0 != libssh2_session_startup(session, s.socket))
	{
		libssh2_session_last_error(session, &ssherr, NULL, 0);
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot establish SSH session: %s", ssherr));
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
		libssh2_session_last_error(session, &ssherr, NULL, 0);
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain authentication methods: %s", ssherr));
		goto session_close;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() supported authentication methods:'%s'", __function_name, userauthlist);

	switch (item->authtype)
	{
		case ITEM_AUTHTYPE_PASSWORD:
			if (auth_pw & 1)
			{
				/* we could authenticate via password */
				if (0 != libssh2_userauth_password(session, item->username, item->password))
				{
					libssh2_session_last_error(session, &ssherr, NULL, 0);
					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Password authentication failed: %s",
							ssherr));
					goto session_close;
				}
				else
					zabbix_log(LOG_LEVEL_DEBUG, "%s() password authentication succeeded",
							__function_name);
			}
			else if (auth_pw & 2)
			{
				/* or via keyboard-interactive */
				password = item->password;
				if (0 != libssh2_userauth_keyboard_interactive(session, item->username, &kbd_callback))
				{
					libssh2_session_last_error(session, &ssherr, NULL, 0);
					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Keyboard-interactive authentication"
							" failed: %s", ssherr));
					goto session_close;
				}
				else
					zabbix_log(LOG_LEVEL_DEBUG, "%s() keyboard-interactive authentication succeeded",
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
					SET_MSG_RESULT(result, zbx_strdup(NULL, "Authentication by public key failed."
							" SSHKeyLocation option is not set"));
					goto session_close;
				}

				/* or by public key */
				publickey = zbx_dsprintf(publickey, "%s/%s", CONFIG_SSH_KEY_LOCATION, item->publickey);
				privatekey = zbx_dsprintf(privatekey, "%s/%s", CONFIG_SSH_KEY_LOCATION,
						item->privatekey);

				if (SUCCEED != zbx_is_regular_file(publickey))
				{
					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot access public key file %s",
							publickey));
					goto session_close;
				}

				if (SUCCEED != zbx_is_regular_file(privatekey))
				{
					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot access private key file %s",
							privatekey));
					goto session_close;
				}

				rc = libssh2_userauth_publickey_fromfile(session, item->username, publickey,
						privatekey, item->password);
				zbx_free(publickey);
				zbx_free(privatekey);

				if (0 != rc)
				{
					libssh2_session_last_error(session, &ssherr, NULL, 0);
					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Public key authentication failed:"
							" %s", ssherr));
					goto session_close;
				}
				else
					zabbix_log(LOG_LEVEL_DEBUG, "%s() authentication by public key succeeded",
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

	/* exec non-blocking on the remove host */
	while (NULL == (channel = libssh2_channel_open_session(session)))
	{
		switch (libssh2_session_last_error(session, NULL, NULL, 0))
		{
			/* marked for non-blocking I/O but the call would block. */
			case LIBSSH2_ERROR_EAGAIN:
				waitsocket(s.socket, session);
				continue;
			default:
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot establish generic session channel"));
				goto session_close;
		}
	}

	dos2unix(item->params);	/* CR+LF (Windows) => LF (Unix) */
	/* request a shell on a channel and execute command */
	while (0 != (rc = libssh2_channel_exec(channel, item->params)))
	{
		switch (rc)
		{
			case LIBSSH2_ERROR_EAGAIN:
				waitsocket(s.socket, session);
				continue;
			default:
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot request a shell"));
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
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read data from SSH server"));
			goto channel_close;
		}
		else
			break;
	}

	buffer[bytecount] = '\0';
	SET_STR_RESULT(result, convert_to_utf8(buffer, bytecount, encoding));

	ret = SYSINFO_RET_OK;

channel_close:
	/* close an active data channel */
	exitcode = 127;
	while (0 != (rc = libssh2_channel_close(channel)))
	{
		switch (rc)
		{
			case LIBSSH2_ERROR_EAGAIN:
				waitsocket(s.socket, session);
				continue;
			default:
				libssh2_session_last_error(session, &ssherr, NULL, 0);
				zabbix_log(LOG_LEVEL_WARNING, "%s() cannot close generic session channel: %s",
						__function_name, ssherr);
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
	char	cmd[MAX_STRING_LEN], params[MAX_STRING_LEN], dns[INTERFACE_DNS_LEN_MAX],
		port[8], encoding[32];

	if (ZBX_COMMAND_ERROR == parse_command(item->key, cmd, sizeof(cmd), params, sizeof(params)))
		return NOTSUPPORTED;

	if (0 != strcmp(SSH_RUN_KEY, cmd))
		return NOTSUPPORTED;

	if (num_param(params) > 4)
		return NOTSUPPORTED;

	if (0 != get_param(params, 2, dns, sizeof(dns)))
		*dns = '\0';

	if ('\0' != *dns)
	{
		strscpy(item->interface.dns_orig, dns);
		item->interface.addr = item->interface.dns_orig;
	}

	if (0 != get_param(params, 3, port, sizeof(port)))
		*port = '\0';

	if (0 != get_param(params, 4, encoding, sizeof(encoding)))
		*encoding = '\0';

	if ('\0' != *port)
	{
		if (FAIL == is_ushort(port, &item->interface.port))
			return NOTSUPPORTED;
	}
	else
		item->interface.port = ZBX_DEFAULT_SSH_PORT;

	return ssh_run(item, result, encoding);
}

#endif	/* HAVE_SSH2 */
