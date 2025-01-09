/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "ssh_run.h"

#include <libssh2.h>

#include "zbxcacheconfig.h"
#include "zbxcomms.h"
#include "zbxfile.h"
#include "zbxstr.h"
#include "zbxtime.h"

#if !defined(HAVE_LIBSSH2_METHOD_KEX) && !defined(HAVE_LIBSSH2_METHOD_HOSTKEY) && \
		!defined(HAVE_LIBSSH2_METHOD_CRYPT_CS) && !defined(HAVE_LIBSSH2_METHOD_CRYPT_SC) && \
		!defined(HAVE_LIBSSH2_METHOD_MAC_CS) && !defined(HAVE_LIBSSH2_METHOD_MAC_SC)
#define HAVE_NO_LIBSSH2_METHODS	1
#endif

static ZBX_THREAD_LOCAL const char	*password;

#ifndef HAVE_NO_LIBSSH2_METHODS
static int	ssh_set_options(LIBSSH2_SESSION *session, int type, const char *key_str, const char *value,
		char **err_msg)
{
	int	res, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key_str:'%s' value:'%s'", __func__, key_str, value);

	if (0 > (res = libssh2_session_method_pref(session, type, value)) && res != LIBSSH2_ERROR_EAGAIN)
	{
		char		*err;
		const char	**algs;
		int		rc;

		if (LIBSSH2_ERROR_NONE != libssh2_session_last_error(session, &err, NULL, 0))
			*err_msg = zbx_dsprintf(NULL, "Cannot set SSH option \"%s\": %s.", key_str, err);
		else
			*err_msg = zbx_dsprintf(NULL, "Cannot set SSH option \"%s\".", key_str);

		if (0 < (rc = libssh2_session_supported_algs(session, type, &algs)))
		{
			*err_msg = zbx_strdcat(*err_msg, " Supported values are: ");

			for (int i = 0; i < rc; i++)
			{
				*err_msg = zbx_strdcat(*err_msg, algs[i]);

				if (i < rc - 1)
					*err_msg = zbx_strdcat(*err_msg, ", ");
			}
			*err_msg = zbx_strdcat(*err_msg, ".");

			libssh2_free(session, algs);
		}
		else
		{
			if (LIBSSH2_ERROR_NONE != libssh2_session_last_error(session, &err, NULL, 0))
				*err_msg = zbx_strdcatf(*err_msg, " Cannot get supported values: %s.", err);
			else
				*err_msg = zbx_strdcat(*err_msg, " Cannot get supported values.");
		}

		ret = FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
#endif

static int	ssh_parse_options(LIBSSH2_SESSION *session, const char *options, char **err_msg)
{
	int	ret = SUCCEED;
	char	opt_copy[1024] = {0};
	char	*saveptr;

	zbx_strscpy(opt_copy, options);

	for (char *line = strtok_r(opt_copy, ";", &saveptr); NULL != line; line = strtok_r(NULL, ";", &saveptr))
	{
		char	*eq_str = strchr(line, '=');

		if (NULL != eq_str)
			*eq_str++ = '\0';

		eq_str = ZBX_NULL2EMPTY_STR(eq_str);

#ifdef HAVE_NO_LIBSSH2_METHODS
		ZBX_UNUSED(session);
		ZBX_UNUSED(eq_str);
#endif

#ifdef HAVE_LIBSSH2_METHOD_KEX
		if (0 == strncmp(line, KEY_EXCHANGE_STR, ZBX_CONST_STRLEN(KEY_EXCHANGE_STR)))
		{
			if (SUCCEED != (ret = ssh_set_options(session, LIBSSH2_METHOD_KEX, KEY_EXCHANGE_STR, eq_str,
					err_msg)))
			{
				break;
			}
			continue;
		}
#endif
#ifdef HAVE_LIBSSH2_METHOD_HOSTKEY
		if (0 == strncmp(line, KEY_HOSTKEY_STR, ZBX_CONST_STRLEN(KEY_HOSTKEY_STR)))
		{
			if (SUCCEED != (ret = ssh_set_options(session, LIBSSH2_METHOD_HOSTKEY, KEY_HOSTKEY_STR, eq_str,
					err_msg)))
			{
				break;
			}
			continue;
		}
#endif
#if defined(HAVE_LIBSSH2_METHOD_CRYPT_CS) && defined(HAVE_LIBSSH2_METHOD_CRYPT_SC)
		if (0 == strncmp(line, KEY_CIPHERS_STR, ZBX_CONST_STRLEN(KEY_CIPHERS_STR)))
		{
			if (SUCCEED != (ret = ssh_set_options(session, LIBSSH2_METHOD_CRYPT_CS, KEY_CIPHERS_STR,
					eq_str, err_msg)))
			{
				break;
			}

			if (SUCCEED != (ret = ssh_set_options(session, LIBSSH2_METHOD_CRYPT_SC, KEY_CIPHERS_STR,
					eq_str, err_msg)))
			{
				break;
			}
			continue;
		}
#endif
#if defined(HAVE_LIBSSH2_METHOD_MAC_CS) && defined(HAVE_LIBSSH2_METHOD_MAC_SC)
		if (0 == strncmp(line, KEY_MACS_STR, ZBX_CONST_STRLEN(KEY_MACS_STR)))
		{
			if (SUCCEED != (ret = ssh_set_options(session, LIBSSH2_METHOD_MAC_CS, KEY_MACS_STR, eq_str,
					err_msg)))
			{
				break;
			}

			if (SUCCEED != (ret = ssh_set_options(session, LIBSSH2_METHOD_MAC_SC, KEY_MACS_STR, eq_str,
					err_msg)))
			{
				break;
			}
			continue;
		}
#endif
		*err_msg = zbx_dsprintf(NULL, "SSH option \"%s\" is not supported.", line);
		ret = FAIL;
		break;
	}

	return ret;
}
#undef HAVE_NO_LIBSSH2_METHODS

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

static int	ssh_socket_wait(ZBX_SOCKET s, LIBSSH2_SESSION *session, int timeout_ms)
{
	zbx_pollfd_t	pd;
	int		ret;
	short		event;

	pd.fd = s;

	if (NULL == session || LIBSSH2_SESSION_BLOCK_INBOUND == libssh2_session_block_directions(session))
		pd.events = event = POLLIN;
	else
		pd.events = event = POLLOUT;

	if (0 > (ret = zbx_socket_poll(&pd, 1, timeout_ms)))
	{
		if (SUCCEED != zbx_socket_had_nonblocking_error())
			return FAIL;

		return SUCCEED;
	}

	if (1 == ret && 0 == (pd.revents & event))
		return FAIL;

	return SUCCEED;
}

static int	ssh_nonblocking_error(zbx_socket_t *s, LIBSSH2_SESSION *session, int errcode, char **error)
{
	if (LIBSSH2_ERROR_EAGAIN != errcode)
	{
		libssh2_session_last_error(session, error, NULL, 1);
		return FAIL;
	}

	if (SUCCEED != zbx_socket_check_deadline(s))
	{
		*error = zbx_strdup(NULL, "timeout error");
		return FAIL;
	}

	if (SUCCEED != ssh_socket_wait(s->socket, session, 1000))
	{
		*error = zbx_strdup(NULL, "connection error");
		return FAIL;
	}

	return SUCCEED;
}

/* example ssh.run["ls /"] */
int	ssh_run(zbx_dc_item_t *item, AGENT_RESULT *result, const char *encoding, const char *options, int timeout,
		const char *config_source_ip, const char *config_ssh_key_location, const char *subsystem)
{
/* the size of temporary buffer used to read from data channel */
#define DATA_BUFFER_SIZE	4096
	zbx_socket_t	s;
	LIBSSH2_SESSION	*session;
	LIBSSH2_CHANNEL	*channel;
	int		auth_pw = 0, rc, ret = NOTSUPPORTED, exitcode;
	char		tmp_buf[DATA_BUFFER_SIZE], *userauthlist, *publickey = NULL, *privatekey = NULL, *ssherr,
			*output, *buffer = NULL, *err_msg = NULL;
	size_t		offset = 0, buf_size = DATA_BUFFER_SIZE;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* initializes an SSH session object */
	if (NULL == (session = libssh2_session_init()))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot initialize SSH session"));
		goto ret_label;
	}

	if (SUCCEED != ssh_parse_options(session, options, &err_msg))
	{
		SET_MSG_RESULT(result, err_msg);
		goto session_free;
	}

	if (FAIL == zbx_tcp_connect(&s, config_source_ip, item->interface.addr, item->interface.port, timeout,
			ZBX_TCP_SEC_UNENCRYPTED, NULL, NULL))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot connect to SSH server: %s", zbx_socket_strerror()));
		goto session_free;
	}

	/* set blocking mode on session */
	libssh2_session_set_blocking(session, 0);

	/* Create a session instance and start it up. This will trade welcome */
	/* banners, exchange keys, and setup crypto, compression, and MAC layers */
	while (0 != (rc = libssh2_session_startup(session, s.socket)))
	{
		if (SUCCEED != ssh_nonblocking_error(&s, session, rc, &ssherr))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot establish SSH session: %s", ssherr));
			zbx_free(ssherr);

			goto tcp_close;
		}
	}

	while (NULL == (userauthlist = libssh2_userauth_list(session, item->username, strlen(item->username))))
	{
		rc = libssh2_session_last_error(session, NULL, NULL, 0);
		if (SUCCEED != ssh_nonblocking_error(&s, session, rc, &ssherr))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain authentication methods: %s", ssherr));
			zbx_free(ssherr);

			goto session_close;
		}
	}

	if (NULL != strstr(userauthlist, "password"))
		auth_pw |= 1;
	if (NULL != strstr(userauthlist, "keyboard-interactive"))
		auth_pw |= 2;
	if (NULL != strstr(userauthlist, "publickey"))
		auth_pw |= 4;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() supported authentication methods:'%s'", __func__, userauthlist);

	switch (item->authtype)
	{
		case ITEM_AUTHTYPE_PASSWORD:
			if (auth_pw & 1)
			{
				while (0 != (rc = libssh2_userauth_password(session, item->username, item->password)))
				{
					if (SUCCEED != ssh_nonblocking_error(&s, session, rc, &ssherr))
					{
						SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Password authentication"
								" failed: %s", ssherr));
						zbx_free(ssherr);

						goto session_close;
					}
				}

				zabbix_log(LOG_LEVEL_DEBUG, "%s() password authentication succeeded", __func__);
			}
			else if (auth_pw & 2)
			{
				/* or via keyboard-interactive */
				password = item->password;

				while (0 != (rc = libssh2_userauth_keyboard_interactive(session, item->username,
						&kbd_callback)))
				{
					if (SUCCEED != ssh_nonblocking_error(&s, session, rc, &ssherr))
					{
						SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Keyboard-interactive "
								"authentication failed: %s", ssherr));
						zbx_free(ssherr);

						goto session_close;
					}
				}

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
				if (NULL == config_ssh_key_location)
				{
					SET_MSG_RESULT(result, zbx_strdup(NULL, "Authentication by public key failed."
							" SSHKeyLocation option is not set"));
					goto session_close;
				}

				/* or by public key */
				publickey = zbx_dsprintf(publickey, "%s/%s", config_ssh_key_location, item->publickey);
				privatekey = zbx_dsprintf(privatekey, "%s/%s", config_ssh_key_location,
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

				while (0 != (rc = libssh2_userauth_publickey_fromfile(session, item->username,
						publickey, privatekey, item->password)))
				{
					if (SUCCEED != ssh_nonblocking_error(&s, session, rc, &ssherr))
					{
						SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Public key authentication "
							"failed: %s", ssherr));
						zbx_free(ssherr);

						goto session_close;
					}
				}

				zabbix_log(LOG_LEVEL_DEBUG, "%s() authentication by public key succeeded", __func__);
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
		rc = libssh2_session_last_error(session, NULL, NULL, 0);
		if (SUCCEED != ssh_nonblocking_error(&s, session, rc, &ssherr))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot establish generic session channel: %s",
					ssherr));
			zbx_free(ssherr);

			goto session_close;
		}
	}

	zbx_dos2unix(item->params);	/* CR+LF (Windows) => LF (Unix) */

	/* request a shell or subsystem on a channel and execute command */
	if (NULL == subsystem || '\0' == *subsystem)
	{
		while (0 != (rc = libssh2_channel_exec(channel, item->params)))
		{
			if (SUCCEED != ssh_nonblocking_error(&s, session, rc, &ssherr))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot request a shell: %s", ssherr));
				zbx_free(ssherr);

				goto channel_close;
			}
		}
	}
	else
	{
		int		timeout_ms;
		zbx_timespec_t	ts;

		while (0 != (rc = libssh2_channel_subsystem(channel, subsystem)))
		{
			if (SUCCEED != ssh_nonblocking_error(&s, session, rc, &ssherr))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot request a subsystem: %s", ssherr));
				zbx_free(ssherr);

				goto channel_close;
			}
		}

		zbx_timespec(&ts);
		timeout_ms = (s.deadline.ns - ts.ns) / 1000000 + (s.deadline.sec - ts.sec) * 1000;

		if (0 >= timeout_ms || SUCCEED != ssh_socket_wait(s.socket, NULL, timeout_ms))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot wait for a subsystem"));

			goto channel_close;
		}

		if (0 > (rc = libssh2_channel_write(channel, item->params, strlen(item->params))))
		{
			char	*err;

			if (LIBSSH2_ERROR_NONE != libssh2_session_last_error(session, &err, NULL, 0))
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot execute request: %s", err));
			else
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot execute request"));

			goto channel_close;
		}
	}

	buffer = (char *)zbx_malloc(buffer, buf_size);

	while (0 != (rc = libssh2_channel_read(channel, tmp_buf, sizeof(tmp_buf))))
	{
		if (rc < 0)
		{
			if (SUCCEED != ssh_nonblocking_error(&s, session, rc, &ssherr))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot read data from SSH server: %s",
						ssherr));
				zbx_free(ssherr);

				goto channel_close;
			}

			continue;
		}

		if (MAX_EXECUTE_OUTPUT_LEN <= offset + (size_t)rc)
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Command output exceeded limit of %d KB",
					MAX_EXECUTE_OUTPUT_LEN / ZBX_KIBIBYTE));
			goto channel_close;
		}

		zbx_str_memcpy_alloc(&buffer, &buf_size, &offset, tmp_buf, (size_t)rc);
	}

	if (NULL == (output = zbx_convert_to_utf8(buffer, offset, encoding, &err_msg)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot convert data from SSH server to"
				" utf8: %s", err_msg));
		zbx_free(err_msg);

		goto channel_close;
	}

	zbx_rtrim(output, ZBX_WHITESPACE);
	zbx_replace_invalid_utf8(output);

	SET_TEXT_RESULT(result, output);
	output = NULL;

	ret = SYSINFO_RET_OK;

channel_close:
	/* close an active data channel */
	exitcode = 127;
	while (0 != (rc = libssh2_channel_close(channel)))
	{
		if (SUCCEED != ssh_nonblocking_error(&s, session, rc, &ssherr))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s() cannot close generic session channel: %s", __func__,
					ssherr);
			zbx_free(ssherr);
			exitcode = 127;
			break;
		}
	}

	zbx_free(buffer);

	if (0 == rc)
		exitcode = libssh2_channel_get_exit_status(channel);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() exitcode:%d bytecount:" ZBX_FS_SIZE_T, __func__, exitcode, offset);

	libssh2_channel_free(channel);
	channel = NULL;

session_close:
	libssh2_session_disconnect(session, "Normal Shutdown");

tcp_close:
	zbx_tcp_close(&s);

session_free:
	libssh2_session_free(session);

ret_label:
	zbx_free(publickey);
	zbx_free(privatekey);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#undef DATA_BUFFER_SIZE
}
