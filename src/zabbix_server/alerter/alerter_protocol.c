/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "common.h"

#include "log.h"
#include "zbxserialize.h"

#include "alerter_protocol.h"

zbx_uint32_t	zbx_alerter_serialize_result(unsigned char **data, int errcode, const char *errmsg)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, errmsg_len;

	zbx_serialize_prepare_value(data_len, errcode);
	zbx_serialize_prepare_str(data_len, errmsg);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, errcode);
	(void)zbx_serialize_str(ptr, errmsg, errmsg_len);

	return data_len;
}

void	zbx_alerter_deserialize_result(const unsigned char *data, int *errcode, char **errmsg)
{
	zbx_uint32_t	errmsg_len;

	data += zbx_deserialize_value(data, errcode);
	(void)zbx_deserialize_str(data, errmsg, errmsg_len);
}

zbx_uint32_t	zbx_alerter_serialize_email(unsigned char **data, zbx_uint64_t alertid, const char *sendto,
		const char *subject, const char *message, const char *smtp_server, unsigned short smtp_port,
		const char *smtp_helo, const char *smtp_email, unsigned char smtp_security,
		unsigned char smtp_verify_peer, unsigned char smtp_verify_host, unsigned char smtp_authentication,
		const char *username, const char *password)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, sendto_len, subject_len, message_len, smtp_server_len, smtp_helo_len,
			smtp_email_len, username_len, password_len;

	zbx_serialize_prepare_value(data_len, alertid);
	zbx_serialize_prepare_str(data_len, sendto);
	zbx_serialize_prepare_str(data_len, subject);
	zbx_serialize_prepare_str(data_len, message);
	zbx_serialize_prepare_str(data_len, smtp_server);
	zbx_serialize_prepare_value(data_len, smtp_port);
	zbx_serialize_prepare_str(data_len, smtp_helo);
	zbx_serialize_prepare_str(data_len, smtp_email);
	zbx_serialize_prepare_value(data_len, smtp_security);
	zbx_serialize_prepare_value(data_len, smtp_verify_peer);
	zbx_serialize_prepare_value(data_len, smtp_verify_host);
	zbx_serialize_prepare_value(data_len, smtp_authentication);
	zbx_serialize_prepare_str(data_len, username);
	zbx_serialize_prepare_str(data_len, password);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, alertid);
	ptr += zbx_serialize_str(ptr, sendto, sendto_len);
	ptr += zbx_serialize_str(ptr, subject, subject_len);
	ptr += zbx_serialize_str(ptr, message, message_len);
	ptr += zbx_serialize_str(ptr, smtp_server, smtp_server_len);
	ptr += zbx_serialize_value(ptr, smtp_port);
	ptr += zbx_serialize_str(ptr, smtp_helo, smtp_helo_len);
	ptr += zbx_serialize_str(ptr, smtp_email, smtp_email_len);
	ptr += zbx_serialize_value(ptr, smtp_security);
	ptr += zbx_serialize_value(ptr, smtp_verify_peer);
	ptr += zbx_serialize_value(ptr, smtp_verify_host);
	ptr += zbx_serialize_value(ptr, smtp_authentication);
	ptr += zbx_serialize_str(ptr, username, username_len);
	(void)zbx_serialize_str(ptr, password, password_len);

	return data_len;
}

void	zbx_alerter_deserialize_email(const unsigned char *data, zbx_uint64_t *alertid, char **sendto, char **subject,
		char **message, char **smtp_server, unsigned short *smtp_port, char **smtp_helo, char **smtp_email,
		unsigned char *smtp_security, unsigned char *smtp_verify_peer, unsigned char *smtp_verify_host,
		unsigned char *smtp_authentication, char **username, char **password)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_value(data, alertid);
	data += zbx_deserialize_str(data, sendto, len);
	data += zbx_deserialize_str(data, subject, len);
	data += zbx_deserialize_str(data, message, len);
	data += zbx_deserialize_str(data, smtp_server, len);
	data += zbx_deserialize_value(data, smtp_port);
	data += zbx_deserialize_str(data, smtp_helo, len);
	data += zbx_deserialize_str(data, smtp_email, len);
	data += zbx_deserialize_value(data, smtp_security);
	data += zbx_deserialize_value(data, smtp_verify_peer);
	data += zbx_deserialize_value(data, smtp_verify_host);
	data += zbx_deserialize_value(data, smtp_authentication);
	data += zbx_deserialize_str(data, username, len);
	(void)zbx_deserialize_str(data, password, len);
}

zbx_uint32_t	zbx_alerter_serialize_jabber(unsigned char **data, zbx_uint64_t alertid,  const char *sendto,
		const char *subject, const char *message, const char *username, const char *password)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, sendto_len, subject_len, message_len, username_len, password_len;

	zbx_serialize_prepare_value(data_len, alertid);
	zbx_serialize_prepare_str(data_len, sendto);
	zbx_serialize_prepare_str(data_len, subject);
	zbx_serialize_prepare_str(data_len, message);
	zbx_serialize_prepare_str(data_len, username);
	zbx_serialize_prepare_str(data_len, password);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, alertid);
	ptr += zbx_serialize_str(ptr, sendto, sendto_len);
	ptr += zbx_serialize_str(ptr, subject, subject_len);
	ptr += zbx_serialize_str(ptr, message, message_len);
	ptr += zbx_serialize_str(ptr, username, username_len);
	(void)zbx_serialize_str(ptr, password, password_len);

	return data_len;
}

void	zbx_alerter_deserialize_jabber(const unsigned char *data, zbx_uint64_t *alertid, char **sendto, char **subject,
		char **message, char **username, char **password)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_value(data, alertid);
	data += zbx_deserialize_str(data, sendto, len);
	data += zbx_deserialize_str(data, subject, len);
	data += zbx_deserialize_str(data, message, len);
	data += zbx_deserialize_str(data, username, len);
	(void)zbx_deserialize_str(data, password, len);
}

zbx_uint32_t	zbx_alerter_serialize_sms(unsigned char **data, zbx_uint64_t alertid,  const char *sendto,
		const char *message, const char *gsm_modem)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, sendto_len, gsm_modem_len, message_len;

	zbx_serialize_prepare_value(data_len, alertid);
	zbx_serialize_prepare_str(data_len, sendto);
	zbx_serialize_prepare_str(data_len, message);
	zbx_serialize_prepare_str(data_len, gsm_modem);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, alertid);
	ptr += zbx_serialize_str(ptr, sendto, sendto_len);
	ptr += zbx_serialize_str(ptr, message, message_len);
	(void)zbx_serialize_str(ptr, gsm_modem, gsm_modem_len);

	return data_len;
}

void	zbx_alerter_deserialize_sms(const unsigned char *data, zbx_uint64_t *alertid, char **sendto, char **message,
		char **gsm_modem)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_value(data, alertid);
	data += zbx_deserialize_str(data, sendto, len);
	data += zbx_deserialize_str(data, message, len);
	(void)zbx_deserialize_str(data, gsm_modem, len);
}

zbx_uint32_t	zbx_alerter_serialize_eztexting(unsigned char **data, zbx_uint64_t alertid,  const char *sendto,
		const char *message, const char *username, const char *password, const char *exec_path)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, sendto_len, exec_path_len, message_len, username_len, password_len;

	zbx_serialize_prepare_value(data_len, alertid);
	zbx_serialize_prepare_str(data_len, sendto);
	zbx_serialize_prepare_str(data_len, message);
	zbx_serialize_prepare_str(data_len, username);
	zbx_serialize_prepare_str(data_len, password);
	zbx_serialize_prepare_str(data_len, exec_path);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, alertid);
	ptr += zbx_serialize_str(ptr, sendto, sendto_len);
	ptr += zbx_serialize_str(ptr, message, message_len);
	ptr += zbx_serialize_str(ptr, username, username_len);
	ptr += zbx_serialize_str(ptr, password, password_len);
	(void)zbx_serialize_str(ptr, exec_path, exec_path_len);

	return data_len;
}

void	zbx_alerter_deserialize_eztexting(const unsigned char *data, zbx_uint64_t *alertid, char **sendto,
		char **message, char **username, char **password, char **exec_path)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_value(data, alertid);
	data += zbx_deserialize_str(data, sendto, len);
	data += zbx_deserialize_str(data, message, len);
	data += zbx_deserialize_str(data, username, len);
	data += zbx_deserialize_str(data, password, len);
	(void)zbx_deserialize_str(data, exec_path, len);
}

zbx_uint32_t	zbx_alerter_serialize_exec(unsigned char **data, zbx_uint64_t alertid, const char *command)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, command_len;

	zbx_serialize_prepare_value(data_len, alertid);
	zbx_serialize_prepare_str(data_len, command);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, alertid);
	(void)zbx_serialize_str(ptr, command, command_len);

	return data_len;
}

void	zbx_alerter_deserialize_exec(const unsigned char *data, zbx_uint64_t *alertid, char **command)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_value(data, alertid);
	(void)zbx_deserialize_str(data, command, len);
}


