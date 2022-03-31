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

#include "checks_ssh.h"

/* the size of temporary buffer used to read from data channel */
#define DATA_BUFFER_SIZE	4096

#if defined(HAVE_SSH2)
#include <libssh2.h>
#elif defined (HAVE_SSH)
#include <libssh/libssh.h>
#endif

#if defined(HAVE_SSH2) || defined(HAVE_SSH)
#include "zbxcomms.h"
#include "log.h"

#define SSH_RUN_KEY	"ssh.run"
#endif

#if defined(HAVE_SSH2)
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
	zbx_socket_t	s;
	LIBSSH2_SESSION	*session;
	LIBSSH2_CHANNEL	*channel;
	int		auth_pw = 0, rc, ret = NOTSUPPORTED, exitcode;
	char		tmp_buf[DATA_BUFFER_SIZE], *userauthlist, *publickey = NULL, *privatekey = NULL, *ssherr,
			*output, *buffer = NULL;
	size_t		offset = 0, buf_size = DATA_BUFFER_SIZE;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == zbx_tcp_connect(&s, CONFIG_SOURCE_IP, item->interface.addr, item->interface.port, 0,
			ZBX_TCP_SEC_UNENCRYPTED, NULL, NULL))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot connect to SSH server: %s", zbx_socket_strerror()));
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

	zabbix_log(LOG_LEVEL_DEBUG, "%s() supported authentication methods:'%s'", __func__, userauthlist);

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
					zabbix_log(LOG_LEVEL_DEBUG, "%s() password authentication succeeded", __func__);
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
							__func__);
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

				if (0 != rc)
				{
					libssh2_session_last_error(session, &ssherr, NULL, 0);
					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Public key authentication failed:"
							" %s", ssherr));
					goto session_close;
				}
				else
					zabbix_log(LOG_LEVEL_DEBUG, "%s() authentication by public key succeeded",
							__func__);
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

	buffer = (char *)zbx_malloc(buffer, buf_size);

	while (0 != (rc = libssh2_channel_read(channel, tmp_buf, sizeof(tmp_buf))))
	{
		if (rc < 0)
		{
			if (LIBSSH2_ERROR_EAGAIN == rc)
				waitsocket(s.socket, session);

			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read data from SSH server"));
			goto channel_close;
		}

		if (MAX_EXECUTE_OUTPUT_LEN <= offset + rc)
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Command output exceeded limit of %d KB",
					MAX_EXECUTE_OUTPUT_LEN / ZBX_KIBIBYTE));
			goto channel_close;
		}

		zbx_str_memcpy_alloc(&buffer, &buf_size, &offset, tmp_buf, rc);
	}

	output = convert_to_utf8(buffer, offset, encoding);
	zbx_rtrim(output, ZBX_WHITESPACE);
	zbx_replace_invalid_utf8(output);

	SET_TEXT_RESULT(result, output);
	output = NULL;

	ret = SYSINFO_RET_OK;
channel_close:
	/* close an active data channel */
	exitcode = 127;
	while (LIBSSH2_ERROR_EAGAIN == (rc = libssh2_channel_close(channel)))
		waitsocket(s.socket, session);

	zbx_free(buffer);

	if (0 != rc)
	{
		libssh2_session_last_error(session, &ssherr, NULL, 0);
		zabbix_log(LOG_LEVEL_WARNING, "%s() cannot close generic session channel: %s", __func__, ssherr);
	}
	else
		exitcode = libssh2_channel_get_exit_status(channel);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() exitcode:%d bytecount:" ZBX_FS_SIZE_T, __func__, exitcode, offset);

	libssh2_channel_free(channel);
	channel = NULL;

session_close:
	libssh2_session_disconnect(session, "Normal Shutdown");

session_free:
	libssh2_session_free(session);

tcp_close:
	zbx_tcp_close(&s);

close:
	zbx_free(publickey);
	zbx_free(privatekey);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
#elif defined(HAVE_SSH)

/* example ssh.run["ls /"] */
static int	ssh_run(DC_ITEM *item, AGENT_RESULT *result, const char *encoding)
{
	ssh_session	session;
	ssh_channel	channel;
	ssh_key 	privkey = NULL, pubkey = NULL;
	int		rc, userauth, ret = NOTSUPPORTED;
	char		*output, *publickey = NULL, *privatekey = NULL, *buffer = NULL;
	char		tmp_buf[DATA_BUFFER_SIZE], userauthlist[64];
	size_t		offset = 0, buf_size = DATA_BUFFER_SIZE;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* initializes an SSH session object */
	if (NULL == (session = ssh_new()))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot initialize SSH session"));
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot initialize SSH session");

		goto close;
	}

	/* set blocking mode on session */
	ssh_set_blocking(session, 1);

	/* create a session instance and start it up */
	if (0 != ssh_options_set(session, SSH_OPTIONS_HOST, item->interface.addr) ||
			0 != ssh_options_set(session, SSH_OPTIONS_PORT, &item->interface.port) ||
			0 != ssh_options_set(session, SSH_OPTIONS_USER, item->username))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set SSH session options: %s",
				ssh_get_error(session)));
		goto session_free;
	}

	if (SSH_OK != ssh_connect(session))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot establish SSH session: %s", ssh_get_error(session)));
		goto session_free;
	}

	/* check which authentication methods are available */
	if (SSH_AUTH_ERROR == ssh_userauth_none(session, NULL))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Error during authentication: %s", ssh_get_error(session)));
		goto session_close;
	}

	userauthlist[0] = '\0';

	if (0 != (userauth = ssh_userauth_list(session, NULL)))
	{
		if (0 != (userauth & SSH_AUTH_METHOD_NONE))
			offset += zbx_snprintf(userauthlist + offset, sizeof(userauthlist) - offset, "none, ");
		if (0 != (userauth & SSH_AUTH_METHOD_PASSWORD))
			offset += zbx_snprintf(userauthlist + offset, sizeof(userauthlist) - offset, "password, ");
		if (0 != (userauth & SSH_AUTH_METHOD_INTERACTIVE))
			offset += zbx_snprintf(userauthlist + offset, sizeof(userauthlist) - offset,
					"keyboard-interactive, ");
		if (0 != (userauth & SSH_AUTH_METHOD_PUBLICKEY))
			offset += zbx_snprintf(userauthlist + offset, sizeof(userauthlist) - offset, "publickey, ");
		if (0 != (userauth & SSH_AUTH_METHOD_HOSTBASED))
			offset += zbx_snprintf(userauthlist + offset, sizeof(userauthlist) - offset, "hostbased, ");
		if (2 <= offset)
			userauthlist[offset-2] = '\0';
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() supported authentication methods: %s", __func__, userauthlist);

	switch (item->authtype)
	{
		case ITEM_AUTHTYPE_PASSWORD:
			if (0 != (userauth & SSH_AUTH_METHOD_PASSWORD))
			{
				/* we could authenticate via password */
				if (SSH_AUTH_SUCCESS != ssh_userauth_password(session, NULL, item->password))
				{
					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Password authentication failed: %s",
							ssh_get_error(session)));
					goto session_close;
				}
				else
					zabbix_log(LOG_LEVEL_DEBUG, "%s() password authentication succeeded", __func__);
			}
			else if (0 != (userauth & SSH_AUTH_METHOD_INTERACTIVE))
			{
				/* or via keyboard-interactive */
				while (SSH_AUTH_INFO == (rc = ssh_userauth_kbdint(session, item->username, NULL)))
				{
					if (1 == ssh_userauth_kbdint_getnprompts(session) &&
							0 != ssh_userauth_kbdint_setanswer(session, 0, item->password))
					{
						zabbix_log(LOG_LEVEL_DEBUG,"Cannot set answer: %s",
								ssh_get_error(session));
					}
				}

				if (SSH_AUTH_SUCCESS != rc)
				{
					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Keyboard-interactive authentication"
							" failed: %s", ssh_get_error(session)));
					goto session_close;
				}
				else
				{
					zabbix_log(LOG_LEVEL_DEBUG, "%s() keyboard-interactive authentication"
							" succeeded", __func__);
				}
			}
			else
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Unsupported authentication method."
						" Supported methods: %s", userauthlist));
				goto session_close;
			}
			break;
		case ITEM_AUTHTYPE_PUBLICKEY:
			if (0 != (userauth & SSH_AUTH_METHOD_PUBLICKEY))
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

				if (SSH_OK != ssh_pki_import_pubkey_file(publickey, &pubkey))
				{
					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Failed to import public key: %s",
							ssh_get_error(session)));
					goto session_close;
				}

				if (SSH_AUTH_SUCCESS != ssh_userauth_try_publickey(session, NULL, pubkey))
				{
					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Public key try failed: %s",
							ssh_get_error(session)));
					goto session_close;
				}

				if (SSH_OK != (rc = ssh_pki_import_privkey_file(privatekey, item->password, NULL, NULL,
						&privkey)))
				{
					if (SSH_EOF == rc)
					{
						SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot import private key"
								" file \"%s\" because it does not exist or permission"
								" denied", privatekey));
						goto session_close;
					}

					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot import private key \"%s\"",
							privatekey));

					zabbix_log(LOG_LEVEL_DEBUG, "%s() failed to import private key \"%s\", rc:%d",
							__func__, privatekey, rc);

					goto session_close;
				}

				if (SSH_AUTH_SUCCESS != ssh_userauth_publickey(session, NULL, privkey))
				{
					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Public key authentication failed:"
							" %s", ssh_get_error(session)));
					goto session_close;
				}
				else
					zabbix_log(LOG_LEVEL_DEBUG, "%s() authentication by public key succeeded",
							__func__);
			}
			else
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Unsupported authentication method."
						" Supported methods: %s", userauthlist));
				goto session_close;
			}
			break;
	}

	if (NULL == (channel = ssh_channel_new(session)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot create generic session channel"));
		goto session_close;
	}

	while (SSH_OK != (rc = ssh_channel_open_session(channel)))
	{
		if (SSH_AGAIN != rc)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot establish generic session channel"));
			goto channel_free;
		}
	}

	/* request a shell on a channel and execute command */
	dos2unix(item->params);	/* CR+LF (Windows) => LF (Unix) */

	while (SSH_OK != (rc = ssh_channel_request_exec(channel, item->params)))
	{
		if (SSH_AGAIN != rc)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot request a shell"));
			goto channel_free;
		}
	}

	buffer = (char *)zbx_malloc(buffer, buf_size);
	offset = 0;

	while (0 != (rc = ssh_channel_read(channel, tmp_buf, sizeof(tmp_buf), 0)))
	{
		if (rc < 0)
		{
			if (SSH_AGAIN == rc)
				continue;

			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot read data from SSH server"));
			goto channel_close;
		}

		if (MAX_EXECUTE_OUTPUT_LEN <= offset + rc)
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Command output exceeded limit of %d KB",
					MAX_EXECUTE_OUTPUT_LEN / ZBX_KIBIBYTE));
			goto channel_close;
		}

		zbx_str_memcpy_alloc(&buffer, &buf_size, &offset, tmp_buf, rc);
	}

	output = convert_to_utf8(buffer, offset, encoding);
	zbx_rtrim(output, ZBX_WHITESPACE);
	zbx_replace_invalid_utf8(output);

	SET_TEXT_RESULT(result, output);
	output = NULL;

	ret = SYSINFO_RET_OK;
channel_close:
	ssh_channel_close(channel);
	zbx_free(buffer);
channel_free:
	ssh_channel_free(channel);
session_close:
	if (NULL != privkey)
		ssh_key_free(privkey);
	if (NULL != pubkey)
		ssh_key_free(pubkey);
	ssh_disconnect(session);
session_free:
	ssh_free(session);
close:
	zbx_free(publickey);
	zbx_free(privatekey);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
#endif

#if defined(HAVE_SSH2) || defined(HAVE_SSH)
int	get_value_ssh(DC_ITEM *item, AGENT_RESULT *result)
{
	AGENT_REQUEST	request;
	int		ret = NOTSUPPORTED;
	const char	*port, *encoding, *dns;

	init_request(&request);

	if (SUCCEED != parse_item_key(item->key, &request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid item key format."));
		goto out;
	}

	if (0 != strcmp(SSH_RUN_KEY, get_rkey(&request)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported item key for this item type."));
		goto out;
	}

	if (4 < get_rparams_num(&request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto out;
	}

	if (NULL != (dns = get_rparam(&request, 1)) && '\0' != *dns)
	{
		strscpy(item->interface.dns_orig, dns);
		item->interface.addr = item->interface.dns_orig;
	}

	if (NULL != (port = get_rparam(&request, 2)) && '\0' != *port)
	{
		if (FAIL == is_ushort(port, &item->interface.port))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
			goto out;
		}
	}
	else
		item->interface.port = ZBX_DEFAULT_SSH_PORT;

	encoding = get_rparam(&request, 3);

	ret = ssh_run(item, result, ZBX_NULL2EMPTY_STR(encoding));
out:
	free_request(&request);

	return ret;
}
#endif	/* defined(HAVE_SSH2) || defined(HAVE_SSH) */
