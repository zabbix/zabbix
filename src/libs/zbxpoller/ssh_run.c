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

#include <libssh/libssh.h>

#include "zbxcomms.h"
#include "zbxfile.h"
#include "zbxstr.h"
#include "zbxtime.h"

#if !defined(HAVE_SSH_OPTIONS_KEY_EXCHANGE) && !defined(HAVE_SSH_OPTIONS_HOSTKEYS) && \
		!defined(HAVE_SSH_OPTIONS_CIPHERS_C_S) && !defined(HAVE_SSH_OPTIONS_CIPHERS_S_C) && \
		!defined(HAVE_SSH_OPTIONS_HMAC_C_S) && !defined(HAVE_SSH_OPTIONS_HMAC_S_C) && \
		!defined(HAVE_SSH_OPTIONS_PUBLICKEY_ACCEPTED_TYPES)
#define HAVE_NO_SSH_OPTIONS	1
#endif

/* the size of temporary buffer used to read from data channel */
#define DATA_BUFFER_SIZE	4096

#ifndef HAVE_NO_SSH_OPTIONS
static int	ssh_set_options(ssh_session session, enum ssh_options_e type, const char *key_str, const char *value,
		char **err_msg)
{
	int ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key_str:'%s' value:'%s'", __func__, key_str, value);

	if (0 > ssh_options_set(session, type, value))
	{
		*err_msg = zbx_dsprintf(NULL, "Cannot set SSH option \"%s\": %s.", key_str, ssh_get_error(session));

		ret = FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
#endif

static int	ssh_parse_options(ssh_session session, const char *options, char **err_msg)
{
	int	ret = SUCCEED;
	char	opt_copy[1024] = {0};
	char	*line, *saveptr;

	zbx_strscpy(opt_copy, options);

	for (line = strtok_r(opt_copy, ";", &saveptr); NULL != line; line = strtok_r(NULL, ";", &saveptr))
	{
		char	*eq_str = strchr(line, '=');

		if (NULL != eq_str)
			*eq_str++ = '\0';

		eq_str = ZBX_NULL2EMPTY_STR(eq_str);

#ifdef HAVE_NO_SSH_OPTIONS
		ZBX_UNUSED(session);
		ZBX_UNUSED(eq_str);
#endif

#ifdef HAVE_SSH_OPTIONS_KEY_EXCHANGE
		if (0 == strncmp(line, KEY_EXCHANGE_STR, ZBX_CONST_STRLEN(KEY_EXCHANGE_STR)))
		{
			if (SUCCEED != (ret = ssh_set_options(session, SSH_OPTIONS_KEY_EXCHANGE, KEY_EXCHANGE_STR,
					eq_str, err_msg)))
			{
				break;
			}
			continue;
		}
#endif
#ifdef HAVE_SSH_OPTIONS_HOSTKEYS
		if (0 == strncmp(line, KEY_HOSTKEY_STR, ZBX_CONST_STRLEN(KEY_HOSTKEY_STR)))
		{
			if (SUCCEED != (ret = ssh_set_options(session, SSH_OPTIONS_HOSTKEYS, KEY_HOSTKEY_STR, eq_str,
					err_msg)))
			{
				break;
			}
			continue;
		}
#endif
#if defined(HAVE_SSH_OPTIONS_CIPHERS_C_S) && defined(HAVE_SSH_OPTIONS_CIPHERS_S_C)
		if (0 == strncmp(line, KEY_CIPHERS_STR, ZBX_CONST_STRLEN(KEY_CIPHERS_STR)))
		{
			if (SUCCEED != (ret = ssh_set_options(session, SSH_OPTIONS_CIPHERS_C_S, KEY_CIPHERS_STR,
					eq_str, err_msg)))
			{
				break;
			}

			if (SUCCEED != (ret = ssh_set_options(session, SSH_OPTIONS_CIPHERS_S_C, KEY_CIPHERS_STR,
					eq_str, err_msg)))
			{
				break;
			}
			continue;
		}
#endif
#if defined(HAVE_SSH_OPTIONS_HMAC_C_S) && defined(HAVE_SSH_OPTIONS_HMAC_S_C)
		if (0 == strncmp(line, KEY_MACS_STR, ZBX_CONST_STRLEN(KEY_MACS_STR)))
		{
			if (SUCCEED != (ret = ssh_set_options(session, SSH_OPTIONS_HMAC_C_S, KEY_MACS_STR, eq_str,
					err_msg)))
			{
				break;
			}

			if (SUCCEED != (ret = ssh_set_options(session, SSH_OPTIONS_HMAC_S_C, KEY_MACS_STR, eq_str,
					err_msg)))
			{
				break;
			}
			continue;
		}
#endif
#if defined(HAVE_SSH_OPTIONS_PUBLICKEY_ACCEPTED_TYPES)
		if (0 == strncmp(line, KEY_PUBKEY_STR, ZBX_CONST_STRLEN(KEY_PUBKEY_STR)))
		{
			if (SUCCEED != (ret = ssh_set_options(session, SSH_OPTIONS_PUBLICKEY_ACCEPTED_TYPES,
					KEY_PUBKEY_STR, eq_str, err_msg)))
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
#undef HAVE_NO_SSH_OPTIONS

static int	ssh_socket_wait(ZBX_SOCKET s, int timeout_ms)
{
	zbx_pollfd_t	pd;
	int		ret;

	pd.fd = s;
	pd.events = POLLIN;

	if (0 > (ret = zbx_socket_poll(&pd, 1, timeout_ms)))
	{
		if (SUCCEED != zbx_socket_had_nonblocking_error())
			return FAIL;

		return SUCCEED;
	}

	if (1 == ret && 0 == (pd.revents & POLLIN))
		return FAIL;

	return SUCCEED;
}

static int	ssh_nonblocking_error(ssh_session session, int errcode, int errcode_again, zbx_timespec_t *deadline,
		char **error)
{
	if (errcode_again != errcode)
	{
		*error = zbx_strdup(NULL, ssh_get_error(session));
		return FAIL;
	}

	if (SUCCEED != zbx_ts_check_deadline(deadline))
	{
		*error = zbx_strdup(NULL, "timeout error");
		return FAIL;
	}

	if (SUCCEED != ssh_socket_wait((ZBX_SOCKET)ssh_get_fd(session), 100))
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
	ssh_session	session;
	ssh_channel	channel;
	ssh_key 	privkey = NULL, pubkey = NULL;
	int		rc, userauth, ret = NOTSUPPORTED;
	char		*output, *publickey = NULL, *privatekey = NULL, *buffer = NULL, *err_msg = NULL;
	char		tmp_buf[DATA_BUFFER_SIZE], userauthlist[64];
	size_t		offset = 0, buf_size = DATA_BUFFER_SIZE;
	zbx_timespec_t	deadline;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ZBX_UNUSED(config_source_ip);

	/* initializes an SSH session object */
	if (NULL == (session = ssh_new()))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot initialize SSH session"));
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot initialize SSH session");

		goto close;
	}

	zbx_ts_get_deadline(&deadline, 0 == timeout ? SEC_PER_YEAR : timeout);

	/* set blocking mode on session */
	ssh_set_blocking(session, 0);

	/* create a session instance and start it up */
	if (0 != ssh_options_set(session, SSH_OPTIONS_HOST, item->interface.addr) ||
			0 != ssh_options_set(session, SSH_OPTIONS_PORT, &item->interface.port) ||
			0 != ssh_options_set(session, SSH_OPTIONS_USER, item->username))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot set SSH session options: %s",
				ssh_get_error(session)));
		goto session_free;
	}

	if (0 < strlen(options))
	{
		int	proc_config = 0;

		if (0 != ssh_options_set(session, SSH_OPTIONS_PROCESS_CONFIG, &proc_config))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot turn off SSH default config processing: %s",
					ssh_get_error(session)));
			goto session_free;
		}

		if (SUCCEED != ssh_parse_options(session, options, &err_msg))
		{
			SET_MSG_RESULT(result, err_msg);
			goto session_free;
		}
	}

	while (SSH_OK != (rc = ssh_connect(session)))
	{
		if (SUCCEED != ssh_nonblocking_error(session, rc, SSH_AGAIN, &deadline, &err_msg))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot establish SSH session: %s", err_msg));
			zbx_free(err_msg);

			goto session_free;
		}
	}

	/* check which authentication methods are available */
	while (SSH_AUTH_AGAIN == (rc = ssh_userauth_none(session, NULL)))
	{
		if (SUCCEED != ssh_nonblocking_error(session, rc, SSH_AUTH_AGAIN, &deadline, &err_msg))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Error during authentication: %s", err_msg));
			zbx_free(err_msg);

			goto session_close;
		}
	}

	if (rc == SSH_AUTH_ERROR)
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
				while (SSH_AUTH_SUCCESS != (rc = ssh_userauth_password(session, NULL, item->password)))
				{
					if (SUCCEED != ssh_nonblocking_error(session, rc, SSH_AUTH_AGAIN, &deadline,
							&err_msg))
					{
						SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Password authentication "
								"failed: %s", err_msg));
						zbx_free(err_msg);

						goto session_close;
					}
				}

				zabbix_log(LOG_LEVEL_DEBUG, "%s() password authentication succeeded", __func__);
			}
			else if (0 != (userauth & SSH_AUTH_METHOD_INTERACTIVE))
			{
				/* or via keyboard-interactive */
				while (SSH_AUTH_SUCCESS != (rc = ssh_userauth_kbdint(session, item->username, NULL)))
				{
					if (SSH_AUTH_INFO == rc)
					{
						if (1 == ssh_userauth_kbdint_getnprompts(session) &&
							0 != ssh_userauth_kbdint_setanswer(session, 0, item->password))
						{
							zabbix_log(LOG_LEVEL_DEBUG,"Cannot set answer: %s",
									ssh_get_error(session));
						}
						else
							continue;
					}

					if (SUCCEED != ssh_nonblocking_error(session, rc, SSH_AUTH_AGAIN, &deadline,
										&err_msg))
					{
						SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Keyboard-interactive "
								"authentication failed: %s", err_msg));
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
			if (0 != (userauth & SSH_AUTH_METHOD_PUBLICKEY))
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

				if (SSH_OK != ssh_pki_import_pubkey_file(publickey, &pubkey))
				{
					SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Failed to import public key: %s",
							ssh_get_error(session)));
					goto session_close;
				}

				while (SSH_AUTH_SUCCESS != (rc = ssh_userauth_try_publickey(session, NULL, pubkey)))
				{
					if (SUCCEED != ssh_nonblocking_error(session, rc, SSH_AUTH_AGAIN, &deadline,
										&err_msg))
					{
						SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Public key try failed: %s",
								err_msg));
						zbx_free(err_msg);

						goto session_close;
					}
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

				while (SSH_AUTH_SUCCESS != (rc = ssh_userauth_publickey(session, NULL, privkey)))
				{
					if (SUCCEED != ssh_nonblocking_error(session, rc, SSH_AUTH_AGAIN, &deadline,
										&err_msg))
					{
						SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Public key authentication "
								"failed: %s", err_msg));
						zbx_free(err_msg);

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

	if (NULL == (channel = ssh_channel_new(session)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot create generic session channel"));
		goto session_close;
	}

	while (SSH_OK != (rc = ssh_channel_open_session(channel)))
	{
		if (SUCCEED != ssh_nonblocking_error(session, rc, SSH_AGAIN, &deadline, &err_msg))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot establish generic session channel: %s",
					err_msg));
			zbx_free(err_msg);
			goto channel_free;
		}
	}

	/* request a shell or subsystem on a channel and execute command */

	zbx_dos2unix(item->params);	/* CR+LF (Windows) => LF (Unix) */

	if (NULL == subsystem || '\0' == *subsystem)
	{
		while (SSH_OK != (rc = ssh_channel_request_exec(channel, item->params)))
		{
			if (SUCCEED != ssh_nonblocking_error(session, rc, SSH_AGAIN, &deadline, &err_msg))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot request a shell: %s", err_msg));
				zbx_free(err_msg);

				goto channel_free;
			}
		}
	}
	else
	{
		int		timeout_ms;
		zbx_timespec_t	ts;

		while (SSH_OK != (rc = ssh_channel_request_subsystem(channel, subsystem)))
		{
			if (SUCCEED != ssh_nonblocking_error(session, rc, SSH_AGAIN, &deadline, &err_msg))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot request a subsystem: %s", err_msg));
				zbx_free(err_msg);

				goto channel_free;
			}
		}

		zbx_timespec(&ts);
		timeout_ms = (deadline.ns - ts.ns) / 1000000 + (deadline.sec - ts.sec) * 1000;

		if (0 >= timeout_ms || SUCCEED != ssh_socket_wait((ZBX_SOCKET)ssh_get_fd(session), timeout_ms))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot wait for a subsystem"));

			goto channel_free;
		}

		if (0 > (rc = ssh_channel_write(channel, item->params, (zbx_uint32_t)strlen(item->params))))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot execute request: %s",
					ssh_get_error(session)));

			goto channel_free;
		}
	}

	buffer = (char *)zbx_malloc(buffer, buf_size);
	offset = 0;

	while (SSH_EOF != (rc = ssh_channel_read_nonblocking(channel, tmp_buf, sizeof(tmp_buf), 0)))
	{
		if (0 == rc)
			rc = SSH_AGAIN;

		if (0 > rc)
		{
			if (SUCCEED != ssh_nonblocking_error(session, rc, SSH_AGAIN, &deadline, &err_msg))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot read data from SSH server: %s",
						err_msg));
				zbx_free(err_msg);

				goto channel_close;
			}

			continue;
		}

		if (0 < rc)
		{
			if (MAX_EXECUTE_OUTPUT_LEN <= offset + (size_t)rc)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Command output exceeded limit of %d KB",
						MAX_EXECUTE_OUTPUT_LEN / ZBX_KIBIBYTE));
				goto channel_close;
			}

			zbx_str_memcpy_alloc(&buffer, &buf_size, &offset, tmp_buf, (size_t)rc);
		}
	}

	if (NULL == (output = zbx_convert_to_utf8(buffer, offset, encoding, &err_msg)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot convert result from SSH server"
				" to utf8: %s", err_msg));
		zbx_free(err_msg);

		goto channel_close;
	}

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

