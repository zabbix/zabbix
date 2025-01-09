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
#include "zbxdbhigh.h"

#include "zbxcommon.h"
#include "zbxserialize.h"

void 	zbx_db_mediatype_clean(zbx_db_mediatype *mt)
{
	zbx_free(mt->smtp_server);
	zbx_free(mt->smtp_helo);
	zbx_free(mt->smtp_email);
	zbx_free(mt->exec_path);
	zbx_free(mt->gsm_modem);
	zbx_free(mt->username);
	zbx_free(mt->passwd);
	zbx_free(mt->attempt_interval);
	zbx_free(mt->script);
	zbx_free(mt->timeout);
}

void	zbx_serialize_mediatype(unsigned char **data, zbx_uint32_t *data_alloc, zbx_uint32_t *data_offset,
		const zbx_db_mediatype *mt)
{
	zbx_uint32_t	data_len = 0, smtp_server_len, smtp_helo_len, smtp_email_len, exec_path_len, gsm_modem_len,
			username_len, passwd_len, script_len, attempt_interval_len, timeout_len;
	unsigned char	*ptr, type = mt->type;

	zbx_serialize_prepare_value(data_len, mt->mediatypeid);
	zbx_serialize_prepare_value(data_len, type);
	zbx_serialize_prepare_str_len(data_len, mt->smtp_server, smtp_server_len);
	zbx_serialize_prepare_str_len(data_len, mt->smtp_helo, smtp_helo_len);
	zbx_serialize_prepare_str_len(data_len, mt->smtp_email, smtp_email_len);
	zbx_serialize_prepare_str_len(data_len, mt->exec_path, exec_path_len);
	zbx_serialize_prepare_str_len(data_len, mt->gsm_modem, gsm_modem_len);
	zbx_serialize_prepare_str_len(data_len, mt->username, username_len);
	zbx_serialize_prepare_str_len(data_len, mt->passwd, passwd_len);
	zbx_serialize_prepare_value(data_len, mt->smtp_port);
	zbx_serialize_prepare_value(data_len, mt->smtp_security);
	zbx_serialize_prepare_value(data_len, mt->smtp_verify_peer);
	zbx_serialize_prepare_value(data_len, mt->smtp_verify_host);
	zbx_serialize_prepare_value(data_len, mt->smtp_authentication);
	zbx_serialize_prepare_value(data_len, mt->maxsessions);
	zbx_serialize_prepare_value(data_len, mt->maxattempts);
	zbx_serialize_prepare_str_len(data_len, mt->attempt_interval, attempt_interval_len);
	zbx_serialize_prepare_value(data_len, mt->message_format);
	zbx_serialize_prepare_str_len(data_len, mt->script, script_len);
	zbx_serialize_prepare_str_len(data_len, mt->timeout, timeout_len);

	while (data_len > *data_alloc - *data_offset)
	{
		*data_alloc *= 2;
		*data = (unsigned char *)zbx_realloc(*data, *data_alloc);
	}

	ptr = *data + *data_offset;
	ptr += zbx_serialize_value(ptr, mt->mediatypeid);
	ptr += zbx_serialize_value(ptr, type);
	ptr += zbx_serialize_str(ptr, mt->smtp_server, smtp_server_len);
	ptr += zbx_serialize_str(ptr, mt->smtp_helo, smtp_helo_len);
	ptr += zbx_serialize_str(ptr, mt->smtp_email, smtp_email_len);
	ptr += zbx_serialize_str(ptr, mt->exec_path, exec_path_len);
	ptr += zbx_serialize_str(ptr, mt->gsm_modem, gsm_modem_len);
	ptr += zbx_serialize_str(ptr, mt->username, username_len);
	ptr += zbx_serialize_str(ptr, mt->passwd, passwd_len);
	ptr += zbx_serialize_value(ptr, mt->smtp_port);
	ptr += zbx_serialize_value(ptr, mt->smtp_security);
	ptr += zbx_serialize_value(ptr, mt->smtp_verify_peer);
	ptr += zbx_serialize_value(ptr, mt->smtp_verify_host);
	ptr += zbx_serialize_value(ptr, mt->smtp_authentication);
	ptr += zbx_serialize_value(ptr, mt->maxsessions);
	ptr += zbx_serialize_value(ptr, mt->maxattempts);
	ptr += zbx_serialize_str(ptr, mt->attempt_interval, attempt_interval_len);
	ptr += zbx_serialize_value(ptr, mt->message_format);
	ptr += zbx_serialize_str(ptr, mt->script, script_len);
	(void)zbx_serialize_str(ptr, mt->timeout, timeout_len);

	*data_offset += data_len;
}

zbx_uint32_t	zbx_deserialize_mediatype(const unsigned char *data, zbx_db_mediatype *mt)
{
	zbx_uint32_t		len;
	const unsigned char	*start = data;
	unsigned char		type;

	data += zbx_deserialize_value(data, &mt->mediatypeid);
	data += zbx_deserialize_value(data, &type);
	data += zbx_deserialize_str(data, &mt->smtp_server, len);
	data += zbx_deserialize_str(data, &mt->smtp_helo, len);
	data += zbx_deserialize_str(data, &mt->smtp_email, len);
	data += zbx_deserialize_str(data, &mt->exec_path, len);
	data += zbx_deserialize_str(data, &mt->gsm_modem, len);
	data += zbx_deserialize_str(data, &mt->username, len);
	data += zbx_deserialize_str(data, &mt->passwd, len);
	data += zbx_deserialize_value(data, &mt->smtp_port);
	data += zbx_deserialize_value(data, &mt->smtp_security);
	data += zbx_deserialize_value(data, &mt->smtp_verify_peer);
	data += zbx_deserialize_value(data, &mt->smtp_verify_host);
	data += zbx_deserialize_value(data, &mt->smtp_authentication);
	data += zbx_deserialize_value(data, &mt->maxsessions);
	data += zbx_deserialize_value(data, &mt->maxattempts);
	data += zbx_deserialize_str(data, &mt->attempt_interval, len);
	data += zbx_deserialize_value(data, &mt->message_format);
	data += zbx_deserialize_str(data, &mt->script, len);
	data += zbx_deserialize_str(data, &mt->timeout, len);

	mt->type = type;

	return (zbx_uint32_t)(data - start);
}
