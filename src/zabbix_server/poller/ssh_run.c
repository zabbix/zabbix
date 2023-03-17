/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "ssh_run.h"

#include "zbxcommon.h"

#include <libssh/libssh.h>

#include "zbxcomms.h"
#include "log.h"
#include "zbxnum.h"

#if !defined(HAVE_SSH_OPTIONS_KEY_EXCHANGE) && !defined(HAVE_SSH_OPTIONS_HOSTKEYS) && \
		!defined(HAVE_SSH_OPTIONS_CIPHERS_C_S) && !defined(HAVE_SSH_OPTIONS_CIPHERS_S_C) && \
		!defined(HAVE_SSH_OPTIONS_HMAC_C_S) && !defined(HAVE_SSH_OPTIONS_HMAC_S_C)
#define HAVE_NO_SSH_OPTIONS	1
#endif

/* the size of temporary buffer used to read from data channel */
#define DATA_BUFFER_SIZE	4096

extern char	*CONFIG_SOURCE_IP;
extern char	*CONFIG_SSH_KEY_LOCATION;

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
		*err_msg = zbx_dsprintf(NULL, "SSH option \"%s\" is not supported.", line);
		ret = FAIL;
		break;
	}

	return ret;
}
#undef HAVE_NO_SSH_OPTIONS

/* example ssh.run["ls /"] */
int	ssh_run(DC_ITEM *item, AGENT_RESULT *result, const char *encoding, const char *options)
{
	ssh_session	session;
	ssh_channel	channel;
	ssh_key 	privkey = NULL, pubkey = NULL;
	int		rc, userauth, ret = NOTSUPPORTED;
	char		*output, *publickey = NULL, *privatekey = NULL, *buffer = NULL, *err_msg = NULL;
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
	zbx_dos2unix(item->params);	/* CR+LF (Windows) => LF (Unix) */

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

		if (MAX_EXECUTE_OUTPUT_LEN <= offset + (size_t)rc)
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Command output exceeded limit of %d KB",
					MAX_EXECUTE_OUTPUT_LEN / ZBX_KIBIBYTE));
			goto channel_close;
		}

		zbx_str_memcpy_alloc(&buffer, &buf_size, &offset, tmp_buf, (size_t)rc);
	}

	output = zbx_convert_to_utf8(buffer, offset, encoding);
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
