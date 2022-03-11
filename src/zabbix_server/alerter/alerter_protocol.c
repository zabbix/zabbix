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

#include "alerter_protocol.h"

#include "log.h"
#include "zbxserialize.h"

void	zbx_am_db_mediatype_clear(zbx_am_db_mediatype_t *mediatype)
{
	zbx_free(mediatype->smtp_server);
	zbx_free(mediatype->smtp_helo);
	zbx_free(mediatype->smtp_email);
	zbx_free(mediatype->exec_path);
	zbx_free(mediatype->exec_params);
	zbx_free(mediatype->gsm_modem);
	zbx_free(mediatype->username);
	zbx_free(mediatype->passwd);
	zbx_free(mediatype->script);
	zbx_free(mediatype->attempt_interval);
	zbx_free(mediatype->timeout);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees the alert object                                            *
 *                                                                            *
 * Parameters: alert - [IN] the alert object                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_am_db_alert_free(zbx_am_db_alert_t *alert)
{
	zbx_free(alert->sendto);
	zbx_free(alert->subject);
	zbx_free(alert->message);
	zbx_free(alert->params);
	zbx_free(alert);
}

void	zbx_am_media_clear(zbx_am_media_t *media)
{
	zbx_free(media->sendto);
}

void	zbx_am_media_free(zbx_am_media_t *media)
{
	zbx_am_media_clear(media);
	zbx_free(media);
}

zbx_uint32_t	zbx_alerter_serialize_result(unsigned char **data, const char *value, int errcode, const char *error,
		const char *debug)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, value_len, error_len, debug_len;

	zbx_serialize_prepare_str(data_len, value);
	zbx_serialize_prepare_value(data_len, errcode);
	zbx_serialize_prepare_str(data_len, error);
	zbx_serialize_prepare_str(data_len, debug);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_str(ptr, value, value_len);
	ptr += zbx_serialize_value(ptr, errcode);
	ptr += zbx_serialize_str(ptr, error, error_len);
	(void)zbx_serialize_str(ptr, debug, debug_len);

	return data_len;
}

void	zbx_alerter_deserialize_result(const unsigned char *data, char **value, int *errcode, char **error,
		char **debug)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_str(data, value, len);
	data += zbx_deserialize_value(data, errcode);
	data += zbx_deserialize_str(data, error, len);
	(void)zbx_deserialize_str(data, debug, len);
}

zbx_uint32_t	zbx_alerter_serialize_result_ext(unsigned char **data, const char *recipient, const char *value,
		int errcode, const char *error, const char *debug)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, value_len, error_len, debug_len, recipient_len;

	zbx_serialize_prepare_str(data_len, recipient);
	zbx_serialize_prepare_str(data_len, value);
	zbx_serialize_prepare_value(data_len, errcode);
	zbx_serialize_prepare_str(data_len, error);
	zbx_serialize_prepare_str(data_len, debug);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_str(ptr, recipient, recipient_len);
	ptr += zbx_serialize_str(ptr, value, value_len);
	ptr += zbx_serialize_value(ptr, errcode);
	ptr += zbx_serialize_str(ptr, error, error_len);
	(void)zbx_serialize_str(ptr, debug, debug_len);

	return data_len;
}

void	zbx_alerter_deserialize_result_ext(const unsigned char *data, char **recipient, char **value, int *errcode,
		char **error, char **debug)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_str(data, recipient, len);
	data += zbx_deserialize_str(data, value, len);
	data += zbx_deserialize_value(data, errcode);
	data += zbx_deserialize_str(data, error, len);
	(void)zbx_deserialize_str(data, debug, len);
}

zbx_uint32_t	zbx_alerter_serialize_email(unsigned char **data, zbx_uint64_t alertid, zbx_uint64_t mediatypeid,
		zbx_uint64_t eventid, const char *sendto, const char *subject, const char *message,
		const char *smtp_server, unsigned short smtp_port, const char *smtp_helo, const char *smtp_email,
		unsigned char smtp_security, unsigned char smtp_verify_peer, unsigned char smtp_verify_host,
		unsigned char smtp_authentication, const char *username, const char *password,
		unsigned char content_type)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, sendto_len, subject_len, message_len, smtp_server_len, smtp_helo_len,
			smtp_email_len, username_len, password_len;

	zbx_serialize_prepare_value(data_len, alertid);
	zbx_serialize_prepare_value(data_len, mediatypeid);
	zbx_serialize_prepare_value(data_len, eventid);
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
	zbx_serialize_prepare_value(data_len, content_type);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, alertid);
	ptr += zbx_serialize_value(ptr, mediatypeid);
	ptr += zbx_serialize_value(ptr, eventid);
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
	ptr += zbx_serialize_str(ptr, password, password_len);
	(void)zbx_serialize_value(ptr, content_type);

	return data_len;
}

void	zbx_alerter_deserialize_email(const unsigned char *data, zbx_uint64_t *alertid, zbx_uint64_t *mediatypeid,
		zbx_uint64_t *eventid, char **sendto, char **subject, char **message, char **smtp_server,
		unsigned short *smtp_port, char **smtp_helo, char **smtp_email, unsigned char *smtp_security,
		unsigned char *smtp_verify_peer, unsigned char *smtp_verify_host, unsigned char *smtp_authentication,
		char **username, char **password, unsigned char *content_type)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_value(data, alertid);
	data += zbx_deserialize_value(data, mediatypeid);
	data += zbx_deserialize_value(data, eventid);
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
	data += zbx_deserialize_str(data, password, len);
	(void)zbx_deserialize_value(data, content_type);
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

static void	alerter_serialize_mediatype(unsigned char **data, zbx_uint32_t *data_alloc, zbx_uint32_t *data_offset,
		zbx_uint64_t mediatypeid, unsigned char type, const char *smtp_server, const char *smtp_helo,
		const char *smtp_email, const char *exec_path, const char *gsm_modem, const char *username,
		const char *passwd, unsigned short smtp_port, unsigned char smtp_security,
		unsigned char smtp_verify_peer, unsigned char smtp_verify_host, unsigned char smtp_authentication,
		const char *exec_params, int maxsessions, int maxattempts, const char *attempt_interval,
		unsigned char content_type, const char *script, const char *timeout)
{
	zbx_uint32_t	data_len = 0, smtp_server_len, smtp_helo_len, smtp_email_len, exec_path_len, gsm_modem_len,
			username_len, passwd_len, exec_params_len, script_len, attempt_interval_len, timeout_len;
	unsigned char	*ptr;

	zbx_serialize_prepare_value(data_len, mediatypeid);
	zbx_serialize_prepare_value(data_len, type);
	zbx_serialize_prepare_str_len(data_len, smtp_server, smtp_server_len);
	zbx_serialize_prepare_str_len(data_len, smtp_helo, smtp_helo_len);
	zbx_serialize_prepare_str_len(data_len, smtp_email, smtp_email_len);
	zbx_serialize_prepare_str_len(data_len, exec_path, exec_path_len);
	zbx_serialize_prepare_str_len(data_len, gsm_modem, gsm_modem_len);
	zbx_serialize_prepare_str_len(data_len, username, username_len);
	zbx_serialize_prepare_str_len(data_len, passwd, passwd_len);
	zbx_serialize_prepare_value(data_len, smtp_port);
	zbx_serialize_prepare_value(data_len, smtp_security);
	zbx_serialize_prepare_value(data_len, smtp_verify_peer);
	zbx_serialize_prepare_value(data_len, smtp_verify_host);
	zbx_serialize_prepare_value(data_len, smtp_authentication);
	zbx_serialize_prepare_str_len(data_len, exec_params, exec_params_len);
	zbx_serialize_prepare_value(data_len, maxsessions);
	zbx_serialize_prepare_value(data_len, maxattempts);
	zbx_serialize_prepare_str_len(data_len, attempt_interval, attempt_interval_len);
	zbx_serialize_prepare_value(data_len, content_type);
	zbx_serialize_prepare_str_len(data_len, script, script_len);
	zbx_serialize_prepare_str_len(data_len, timeout, timeout_len);

	while (data_len > *data_alloc - *data_offset)
	{
		*data_alloc *= 2;
		*data = (unsigned char *)zbx_realloc(*data, *data_alloc);
	}

	ptr = *data + *data_offset;
	ptr += zbx_serialize_value(ptr, mediatypeid);
	ptr += zbx_serialize_value(ptr, type);
	ptr += zbx_serialize_str(ptr, smtp_server, smtp_server_len);
	ptr += zbx_serialize_str(ptr, smtp_helo, smtp_helo_len);
	ptr += zbx_serialize_str(ptr, smtp_email, smtp_email_len);
	ptr += zbx_serialize_str(ptr, exec_path, exec_path_len);
	ptr += zbx_serialize_str(ptr, gsm_modem, gsm_modem_len);
	ptr += zbx_serialize_str(ptr, username, username_len);
	ptr += zbx_serialize_str(ptr, passwd, passwd_len);
	ptr += zbx_serialize_value(ptr, smtp_port);
	ptr += zbx_serialize_value(ptr, smtp_security);
	ptr += zbx_serialize_value(ptr, smtp_verify_peer);
	ptr += zbx_serialize_value(ptr, smtp_verify_host);
	ptr += zbx_serialize_value(ptr, smtp_authentication);
	ptr += zbx_serialize_str(ptr, exec_params, exec_params_len);
	ptr += zbx_serialize_value(ptr, maxsessions);
	ptr += zbx_serialize_value(ptr, maxattempts);
	ptr += zbx_serialize_str(ptr, attempt_interval, attempt_interval_len);
	ptr += zbx_serialize_value(ptr, content_type);
	ptr += zbx_serialize_str(ptr, script, script_len);
	(void)zbx_serialize_str(ptr, timeout, timeout_len);

	*data_offset += data_len;
}

static zbx_uint32_t	alerter_deserialize_mediatype(const unsigned char *data, zbx_uint64_t *mediatypeid,
		unsigned char *type, char **smtp_server, char **smtp_helo, char **smtp_email, char **exec_path,
		char **gsm_modem, char **username, char **passwd, unsigned short *smtp_port,
		unsigned char *smtp_security, unsigned char *smtp_verify_peer, unsigned char *smtp_verify_host,
		unsigned char *smtp_authentication, char **exec_params, int *maxsessions, int *maxattempts,
		char **attempt_interval, unsigned char *content_type, char **script, char **timeout)
{
	zbx_uint32_t		len;
	const unsigned char	*start = data;

	data += zbx_deserialize_value(data, mediatypeid);
	data += zbx_deserialize_value(data, type);
	data += zbx_deserialize_str(data, smtp_server, len);
	data += zbx_deserialize_str(data, smtp_helo, len);
	data += zbx_deserialize_str(data, smtp_email, len);
	data += zbx_deserialize_str(data, exec_path, len);
	data += zbx_deserialize_str(data, gsm_modem, len);
	data += zbx_deserialize_str(data, username, len);
	data += zbx_deserialize_str(data, passwd, len);
	data += zbx_deserialize_value(data, smtp_port);
	data += zbx_deserialize_value(data, smtp_security);
	data += zbx_deserialize_value(data, smtp_verify_peer);
	data += zbx_deserialize_value(data, smtp_verify_host);
	data += zbx_deserialize_value(data, smtp_authentication);
	data += zbx_deserialize_str(data, exec_params, len);
	data += zbx_deserialize_value(data, maxsessions);
	data += zbx_deserialize_value(data, maxattempts);
	data += zbx_deserialize_str(data, attempt_interval, len);
	data += zbx_deserialize_value(data, content_type);
	data += zbx_deserialize_str(data, script, len);
	data += zbx_deserialize_str(data, timeout, len);

	return data - start;
}

zbx_uint32_t	zbx_alerter_serialize_alert_send(unsigned char **data, zbx_uint64_t mediatypeid, unsigned char type,
		const char *smtp_server, const char *smtp_helo, const char *smtp_email, const char *exec_path,
		const char *gsm_modem, const char *username, const char *passwd, unsigned short smtp_port,
		unsigned char smtp_security, unsigned char smtp_verify_peer, unsigned char smtp_verify_host,
		unsigned char smtp_authentication, const char *exec_params, int maxsessions, int maxattempts,
		const char *attempt_interval, unsigned char content_type, const char *script, const char *timeout,
		const char *sendto, const char *subject, const char *message, const char *params)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, data_alloc = 1024, data_offset = 0, sendto_len, subject_len, message_len,
			params_len;

	*data = zbx_malloc(0, data_alloc);
	alerter_serialize_mediatype(data, &data_alloc, &data_offset, mediatypeid, type, smtp_server, smtp_helo,
			smtp_email, exec_path, gsm_modem, username, passwd, smtp_port, smtp_security, smtp_verify_peer,
			smtp_verify_host, smtp_authentication, exec_params, maxsessions, maxattempts, attempt_interval,
			content_type, script, timeout);

	zbx_serialize_prepare_str(data_len, sendto);
	zbx_serialize_prepare_str(data_len, subject);
	zbx_serialize_prepare_str(data_len, message);
	zbx_serialize_prepare_str(data_len, params);

	if (data_alloc - data_offset < data_len)
	{
		data_alloc = data_offset + data_len;
		*data = (unsigned char *)zbx_realloc(*data, data_alloc);
	}

	ptr = *data + data_offset;
	ptr += zbx_serialize_str(ptr, sendto, sendto_len);
	ptr += zbx_serialize_str(ptr, subject, subject_len);
	ptr += zbx_serialize_str(ptr, message, message_len);
	(void)zbx_serialize_str(ptr, params, params_len);

	return data_len + data_offset;
}

void	zbx_alerter_deserialize_alert_send(const unsigned char *data, zbx_uint64_t *mediatypeid,
		unsigned char *type, char **smtp_server, char **smtp_helo, char **smtp_email, char **exec_path,
		char **gsm_modem, char **username, char **passwd, unsigned short *smtp_port,
		unsigned char *smtp_security, unsigned char *smtp_verify_peer, unsigned char *smtp_verify_host,
		unsigned char *smtp_authentication, char **exec_params, int *maxsessions, int *maxattempts,
		char **attempt_interval, unsigned char *content_type, char **script, char **timeout,
		char **sendto, char **subject, char **message, char **params)
{
	zbx_uint32_t	len;

	data += alerter_deserialize_mediatype(data, mediatypeid, type, smtp_server, smtp_helo,
			smtp_email, exec_path, gsm_modem, username, passwd, smtp_port, smtp_security, smtp_verify_peer,
			smtp_verify_host, smtp_authentication, exec_params, maxsessions, maxattempts, attempt_interval,
			content_type, script, timeout);

	data += zbx_deserialize_str(data, sendto, len);
	data += zbx_deserialize_str(data, subject, len);
	data += zbx_deserialize_str(data, message, len);
	(void)zbx_deserialize_str(data, params, len);
}

zbx_uint32_t	zbx_alerter_serialize_webhook(unsigned char **data, const char *script_bin, int script_sz,
		int timeout, const char *params, unsigned char debug)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, params_len;

	data_len += script_sz + sizeof(zbx_uint32_t);
	zbx_serialize_prepare_value(data_len, script_sz);
	zbx_serialize_prepare_value(data_len, timeout);
	zbx_serialize_prepare_str(data_len, params);
	zbx_serialize_prepare_value(data_len, debug);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_str(ptr, script_bin, script_sz);
	ptr += zbx_serialize_value(ptr, script_sz);
	ptr += zbx_serialize_value(ptr, timeout);
	ptr += zbx_serialize_str(ptr, params, params_len);
	(void)zbx_serialize_value(ptr, debug);

	return data_len;
}

void	zbx_alerter_deserialize_webhook(const unsigned char *data, char **script_bin, int *script_sz, int *timeout,
		char **params, unsigned char *debug)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_str(data, script_bin, len);
	data += zbx_deserialize_value(data, script_sz);
	data += zbx_deserialize_value(data, timeout);
	data += zbx_deserialize_str(data, params, len);
	(void)zbx_deserialize_value(data, debug);
}

zbx_uint32_t	zbx_alerter_serialize_mediatypes(unsigned char **data, zbx_am_db_mediatype_t **mediatypes,
		int mediatypes_num)
{
	unsigned char	*ptr;
	int		i;
	zbx_uint32_t	data_alloc = 1024, data_offset = 0;

	ptr = *data = (unsigned char *)zbx_malloc(NULL, data_alloc);
	zbx_serialize_prepare_value(data_offset, mediatypes_num);
	(void)zbx_serialize_value(ptr, mediatypes_num);

	for (i = 0; i < mediatypes_num; i++)
	{
		zbx_am_db_mediatype_t	*mt = mediatypes[i];

		alerter_serialize_mediatype(data, &data_alloc, &data_offset, mt->mediatypeid, mt->type, mt->smtp_server,
				mt->smtp_helo, mt->smtp_email, mt->exec_path, mt->gsm_modem, mt->username, mt->passwd,
				mt->smtp_port, mt->smtp_security, mt->smtp_verify_peer, mt->smtp_verify_host,
				mt->smtp_authentication, mt->exec_params, mt->maxsessions, mt->maxattempts,
				mt->attempt_interval, mt->content_type, mt->script, mt->timeout);
	}

	return data_offset;
}

void	zbx_alerter_deserialize_mediatypes(const unsigned char *data, zbx_am_db_mediatype_t ***mediatypes,
		int *mediatypes_num)
{
	int	i;

	data += zbx_deserialize_value(data, mediatypes_num);
	*mediatypes = (zbx_am_db_mediatype_t **)zbx_malloc(NULL, *mediatypes_num * sizeof(zbx_am_db_mediatype_t *));
	for (i = 0; i < *mediatypes_num; i++)
	{
		zbx_am_db_mediatype_t	*mt;
		mt = (zbx_am_db_mediatype_t *)zbx_malloc(NULL, sizeof(zbx_am_db_mediatype_t));

		data += alerter_deserialize_mediatype(data, &mt->mediatypeid, &mt->type, &mt->smtp_server,
				&mt->smtp_helo, &mt->smtp_email, &mt->exec_path, &mt->gsm_modem, &mt->username,
				&mt->passwd, &mt->smtp_port, &mt->smtp_security, &mt->smtp_verify_peer,
				&mt->smtp_verify_host, &mt->smtp_authentication, &mt->exec_params, &mt->maxsessions,
				&mt->maxattempts, &mt->attempt_interval, &mt->content_type, &mt->script, &mt->timeout);

		(*mediatypes)[i] = mt;
	}
}

zbx_uint32_t	zbx_alerter_serialize_alerts(unsigned char **data, zbx_am_db_alert_t **alerts, int alerts_num)
{
	unsigned char	*ptr;
	int		i;
	zbx_uint32_t	data_alloc = 1024, data_offset = 0;

	ptr = *data = (unsigned char *)zbx_malloc(NULL, data_alloc);
	zbx_serialize_prepare_value(data_offset, alerts_num);
	(void)zbx_serialize_value(ptr, alerts_num);

	for (i = 0; i < alerts_num; i++)
	{
		zbx_uint32_t		data_len = 0, sendto_len, subject_len, message_len, params_len;
		zbx_am_db_alert_t	*alert = alerts[i];

		zbx_serialize_prepare_value(data_len, alert->alertid);
		zbx_serialize_prepare_value(data_len, alert->mediatypeid);
		zbx_serialize_prepare_value(data_len, alert->eventid);
		zbx_serialize_prepare_value(data_len, alert->p_eventid);
		zbx_serialize_prepare_value(data_len, alert->source);
		zbx_serialize_prepare_value(data_len, alert->object);
		zbx_serialize_prepare_value(data_len, alert->objectid);
		zbx_serialize_prepare_str_len(data_len, alert->sendto, sendto_len);
		zbx_serialize_prepare_str_len(data_len, alert->subject, subject_len);
		zbx_serialize_prepare_str_len(data_len, alert->message, message_len);
		zbx_serialize_prepare_str_len(data_len, alert->params, params_len);
		zbx_serialize_prepare_value(data_len, alert->status);
		zbx_serialize_prepare_value(data_len, alert->retries);

		while (data_len > data_alloc - data_offset)
		{
			data_alloc *= 2;
			*data = (unsigned char *)zbx_realloc(*data, data_alloc);
		}
		ptr = *data + data_offset;
		ptr += zbx_serialize_value(ptr, alert->alertid);
		ptr += zbx_serialize_value(ptr, alert->mediatypeid);
		ptr += zbx_serialize_value(ptr, alert->eventid);
		ptr += zbx_serialize_value(ptr, alert->p_eventid);
		ptr += zbx_serialize_value(ptr, alert->source);
		ptr += zbx_serialize_value(ptr, alert->object);
		ptr += zbx_serialize_value(ptr, alert->objectid);
		ptr += zbx_serialize_str(ptr, alert->sendto, sendto_len);
		ptr += zbx_serialize_str(ptr, alert->subject, subject_len);
		ptr += zbx_serialize_str(ptr, alert->message, message_len);
		ptr += zbx_serialize_str(ptr, alert->params, params_len);
		ptr += zbx_serialize_value(ptr, alert->status);
		(void)zbx_serialize_value(ptr, alert->retries);

		data_offset += data_len;
	}

	return data_offset;
}

void	zbx_alerter_deserialize_alerts(const unsigned char *data, zbx_am_db_alert_t ***alerts, int *alerts_num)
{
	zbx_uint32_t	len;
	int		i;

	data += zbx_deserialize_value(data, alerts_num);
	*alerts = (zbx_am_db_alert_t **)zbx_malloc(NULL, *alerts_num * sizeof(zbx_am_db_alert_t *));
	for (i = 0; i < *alerts_num; i++)
	{
		zbx_am_db_alert_t	*alert;
		alert = (zbx_am_db_alert_t *)zbx_malloc(NULL, sizeof(zbx_am_db_alert_t));

		data += zbx_deserialize_value(data, &alert->alertid);
		data += zbx_deserialize_value(data, &alert->mediatypeid);
		data += zbx_deserialize_value(data, &alert->eventid);
		data += zbx_deserialize_value(data, &alert->p_eventid);
		data += zbx_deserialize_value(data, &alert->source);
		data += zbx_deserialize_value(data, &alert->object);
		data += zbx_deserialize_value(data, &alert->objectid);
		data += zbx_deserialize_str(data, &alert->sendto, len);
		data += zbx_deserialize_str(data, &alert->subject, len);
		data += zbx_deserialize_str(data, &alert->message, len);
		data += zbx_deserialize_str(data, &alert->params, len);
		data += zbx_deserialize_value(data, &alert->status);
		data += zbx_deserialize_value(data, &alert->retries);

		(*alerts)[i] = alert;
	}
}

zbx_uint32_t	zbx_alerter_serialize_medias(unsigned char **data, zbx_am_media_t **medias, int medias_num)
{
	unsigned char	*ptr;
	int		i;
	zbx_uint32_t	data_alloc = 1024, data_offset = 0;

	ptr = *data = (unsigned char *)zbx_malloc(NULL, data_alloc);
	zbx_serialize_prepare_value(data_offset, medias_num);
	(void)zbx_serialize_value(ptr, medias_num);

	for (i = 0; i < medias_num; i++)
	{
		zbx_uint32_t	data_len = 0, sendto_len;
		zbx_am_media_t	*media = medias[i];

		zbx_serialize_prepare_value(data_len, media->mediaid);
		zbx_serialize_prepare_value(data_len, media->mediatypeid);
		zbx_serialize_prepare_str_len(data_len, media->sendto, sendto_len);

		while (data_len > data_alloc - data_offset)
		{
			data_alloc *= 2;
			*data = (unsigned char *)zbx_realloc(*data, data_alloc);
		}
		ptr = *data + data_offset;
		ptr += zbx_serialize_value(ptr, media->mediaid);
		ptr += zbx_serialize_value(ptr, media->mediatypeid);
		(void)zbx_serialize_str(ptr, media->sendto, sendto_len);

		data_offset += data_len;
	}

	return data_offset;
}

void	zbx_alerter_deserialize_medias(const unsigned char *data, zbx_am_media_t ***medias, int *medias_num)
{
	zbx_uint32_t	len;
	int		i;

	data += zbx_deserialize_value(data, medias_num);
	*medias = (zbx_am_media_t **)zbx_malloc(NULL, *medias_num * sizeof(zbx_am_media_t *));
	for (i = 0; i < *medias_num; i++)
	{
		zbx_am_media_t	*media;
		media = (zbx_am_media_t *)zbx_malloc(NULL, sizeof(zbx_am_media_t));

		data += zbx_deserialize_value(data, &media->mediaid);
		data += zbx_deserialize_value(data, &media->mediatypeid);
		data += zbx_deserialize_str(data, &media->sendto, len);

		(*medias)[i] = media;
	}
}

zbx_uint32_t	zbx_alerter_serialize_results(unsigned char **data, zbx_am_result_t **results, int results_num)
{
	unsigned char	*ptr;
	int		i;
	zbx_uint32_t	data_alloc = 1024, data_offset = 0;

	ptr = *data = (unsigned char *)zbx_malloc(NULL, data_alloc);
	zbx_serialize_prepare_value(data_offset, results_num);
	(void)zbx_serialize_value(ptr, results_num);

	for (i = 0; i < results_num; i++)
	{
		zbx_uint32_t	data_len = 0, value_len, error_len;
		zbx_am_result_t	*result = results[i];

		zbx_serialize_prepare_value(data_len, result->alertid);
		zbx_serialize_prepare_value(data_len, result->eventid);
		zbx_serialize_prepare_value(data_len, result->mediatypeid);
		zbx_serialize_prepare_value(data_len, result->source);
		zbx_serialize_prepare_value(data_len, result->status);
		zbx_serialize_prepare_value(data_len, result->retries);
		zbx_serialize_prepare_str_len(data_len, result->value, value_len);
		zbx_serialize_prepare_str_len(data_len, result->error, error_len);

		while (data_len > data_alloc - data_offset)
		{
			data_alloc *= 2;
			*data = (unsigned char *)zbx_realloc(*data, data_alloc);
		}
		ptr = *data + data_offset;
		ptr += zbx_serialize_value(ptr, result->alertid);
		ptr += zbx_serialize_value(ptr, result->eventid);
		ptr += zbx_serialize_value(ptr, result->mediatypeid);
		ptr += zbx_serialize_value(ptr, result->source);
		ptr += zbx_serialize_value(ptr, result->status);
		ptr += zbx_serialize_value(ptr, result->retries);
		ptr += zbx_serialize_str(ptr, result->value, value_len);
		(void)zbx_serialize_str(ptr, result->error, error_len);

		data_offset += data_len;
	}

	return data_offset;
}

void	zbx_alerter_deserialize_results(const unsigned char *data, zbx_am_result_t ***results, int *results_num)
{
	zbx_uint32_t	len;
	int		i;

	data += zbx_deserialize_value(data, results_num);
	*results = (zbx_am_result_t **)zbx_malloc(NULL, *results_num * sizeof(zbx_am_result_t *));
	for (i = 0; i < *results_num; i++)
	{
		zbx_am_result_t	*result;
		result = (zbx_am_result_t *)zbx_malloc(NULL, sizeof(zbx_am_result_t));

		data += zbx_deserialize_value(data, &result->alertid);
		data += zbx_deserialize_value(data, &result->eventid);
		data += zbx_deserialize_value(data, &result->mediatypeid);
		data += zbx_deserialize_value(data, &result->source);
		data += zbx_deserialize_value(data, &result->status);
		data += zbx_deserialize_value(data, &result->retries);
		data += zbx_deserialize_str(data, &result->value, len);
		data += zbx_deserialize_str(data, &result->error, len);

		(*results)[i] = result;
	}
}

zbx_uint32_t	zbx_alerter_serialize_ids(unsigned char **data, zbx_uint64_t *ids, int ids_num)
{
	unsigned char	*ptr;
	int		i;
	zbx_uint32_t	data_alloc = 128, data_offset = 0;

	ptr = *data = (unsigned char *)zbx_malloc(NULL, data_alloc);
	zbx_serialize_prepare_value(data_offset, ids_num);
	(void)zbx_serialize_value(ptr, ids_num);

	for (i = 0; i < ids_num; i++)
	{
		zbx_uint32_t	data_len = 0;

		zbx_serialize_prepare_value(data_len, ids[i]);

		while (data_len > data_alloc - data_offset)
		{
			data_alloc *= 2;
			*data = (unsigned char *)zbx_realloc(*data, data_alloc);
		}
		ptr = *data + data_offset;
		(void)zbx_serialize_value(ptr, ids[i]);
		data_offset += data_len;
	}

	return data_offset;
}

void	zbx_alerter_deserialize_ids(const unsigned char *data, zbx_uint64_t **ids, int *ids_num)
{
	int	i;

	data += zbx_deserialize_value(data, ids_num);
	*ids = (zbx_uint64_t *)zbx_malloc(NULL, *ids_num * sizeof(zbx_uint64_t));
	for (i = 0; i < *ids_num; i++)
		data += zbx_deserialize_value(data, &(*ids)[i]);
}

zbx_uint32_t	zbx_alerter_serialize_diag_stats(unsigned char **data, zbx_uint64_t alerts_num)
{
	zbx_uint32_t	data_len = 0;

	zbx_serialize_prepare_value(data_len, alerts_num);
	*data = (unsigned char *)zbx_malloc(NULL, data_len);
	(void)zbx_serialize_value(*data, alerts_num);

	return data_len;
}

static void	zbx_alerter_deserialize_diag_stats(const unsigned char *data, zbx_uint64_t *alerts_num)
{
	(void)zbx_deserialize_value(data, alerts_num);
}

static zbx_uint32_t	zbx_alerter_serialize_top_request(unsigned char **data, int limit)
{
	zbx_uint32_t	len;

	*data = (unsigned char *)zbx_malloc(NULL, sizeof(limit));
	len = zbx_serialize_value(*data, limit);

	return len;
}

void	zbx_alerter_deserialize_top_request(const unsigned char *data, int *limit)
{
	(void)zbx_deserialize_value(data, limit);
}

zbx_uint32_t	zbx_alerter_serialize_top_mediatypes_result(unsigned char **data, zbx_am_mediatype_t **mediatypes,
		int mediatypes_num)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, mediatype_len = 0;
	int		i;

	if (0 != mediatypes_num)
	{
		zbx_serialize_prepare_value(mediatype_len, mediatypes[0]->mediatypeid);
		zbx_serialize_prepare_value(mediatype_len, mediatypes[0]->refcount);
	}

	zbx_serialize_prepare_value(data_len, mediatypes_num);
	data_len += mediatype_len * mediatypes_num;
	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, mediatypes_num);

	for (i = 0; i < mediatypes_num; i++)
	{
		ptr += zbx_serialize_value(ptr, mediatypes[0]->mediatypeid);
		ptr += zbx_serialize_value(ptr, mediatypes[0]->refcount);
	}

	return data_len;
}

static void	zbx_alerter_deserialize_top_mediatypes_result(const unsigned char *data,
		zbx_vector_uint64_pair_t *mediatypes)
{
	int	i, mediatypes_num;

	data += zbx_deserialize_value(data, &mediatypes_num);

	if (0 != mediatypes_num)
	{
		zbx_vector_uint64_pair_reserve(mediatypes, (size_t)mediatypes_num);

		for (i = 0; i < mediatypes_num; i++)
		{
			zbx_uint64_pair_t	pair;
			int			value;

			data += zbx_deserialize_value(data, &pair.first);
			data += zbx_deserialize_value(data, &value);
			pair.second = value;
			zbx_vector_uint64_pair_append_ptr(mediatypes, &pair);
		}
	}
}

zbx_uint32_t	zbx_alerter_serialize_top_sources_result(unsigned char **data, zbx_am_source_stats_t **sources,
		int sources_num)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, source_len = 0;
	int		i;

	if (0 != sources_num)
	{
		zbx_serialize_prepare_value(source_len, sources[0]->source);
		zbx_serialize_prepare_value(source_len, sources[0]->object);
		zbx_serialize_prepare_value(source_len, sources[0]->objectid);
		zbx_serialize_prepare_value(source_len, sources[0]->alerts_num);
	}

	zbx_serialize_prepare_value(data_len, sources_num);
	data_len += source_len * sources_num;
	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, sources_num);

	for (i = 0; i < sources_num; i++)
	{
		ptr += zbx_serialize_value(ptr, sources[i]->source);
		ptr += zbx_serialize_value(ptr, sources[i]->object);
		ptr += zbx_serialize_value(ptr, sources[i]->objectid);
		ptr += zbx_serialize_value(ptr, sources[i]->alerts_num);
	}

	return data_len;
}

static void	zbx_alerter_deserialize_top_sources_result(const unsigned char *data, zbx_vector_ptr_t *sources)
{
	int	i, sources_num;

	data += zbx_deserialize_value(data, &sources_num);

	if (0 != sources_num)
	{
		zbx_vector_ptr_reserve(sources, (size_t)sources_num);

		for (i = 0; i < sources_num; i++)
		{
			zbx_am_source_stats_t	*source;

			source = (zbx_am_source_stats_t *)zbx_malloc(NULL, sizeof(zbx_am_source_stats_t));
			data += zbx_deserialize_value(data, &source->source);
			data += zbx_deserialize_value(data, &source->object);
			data += zbx_deserialize_value(data, &source->objectid);
			data += zbx_deserialize_value(data, &source->alerts_num);
			zbx_vector_ptr_append(sources, source);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get alerter manager diagnostic statistics                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_alerter_get_diag_stats(zbx_uint64_t *alerts_num, char **error)
{
	unsigned char	*result;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_ALERTER, ZBX_IPC_ALERTER_DIAG_STATS, SEC_PER_MIN, NULL, 0,
			&result, error))
	{
		return FAIL;
	}

	zbx_alerter_deserialize_diag_stats(result, alerts_num);
	zbx_free(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the top N mediatypes by the number of queued alerts           *
 *                                                                            *
 * Parameters limit      - [IN] the number of top records to retrieve         *
 *            mediatypes - [OUT] a vector of top mediatypeid,alerts_num pairs *
 *            error      - [OUT] the error message                            *
 *                                                                            *
 * Return value: SUCCEED - the top n mediatypes were returned successfully    *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_alerter_get_top_mediatypes(int limit, zbx_vector_uint64_pair_t *mediatypes, char **error)
{
	int		ret;
	unsigned char	*data, *result;
	zbx_uint32_t	data_len;

	data_len = zbx_alerter_serialize_top_request(&data, limit);

	if (SUCCEED != (ret = zbx_ipc_async_exchange(ZBX_IPC_SERVICE_ALERTER, ZBX_IPC_ALERTER_DIAG_TOP_MEDIATYPES,
			SEC_PER_MIN, data, data_len, &result, error)))
	{
		goto out;
	}

	zbx_alerter_deserialize_top_mediatypes_result(result, mediatypes);
	zbx_free(result);
out:
	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the top N sources by the number of queued alerts              *
 *                                                                            *
 * Parameters limit   - [IN] the number of top records to retrieve            *
 *            sources - [OUT] a vector of top zbx_alerter_source_stats_t      *
 *                             structure                                      *
 *            error   - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the top n sources were returned successfully       *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_alerter_get_top_sources(int limit, zbx_vector_ptr_t *sources, char **error)
{
	int		ret;
	unsigned char	*data, *result;
	zbx_uint32_t	data_len;

	data_len = zbx_alerter_serialize_top_request(&data, limit);

	if (SUCCEED != (ret = zbx_ipc_async_exchange(ZBX_IPC_SERVICE_ALERTER, ZBX_IPC_ALERTER_DIAG_TOP_SOURCES,
			SEC_PER_MIN, data, data_len, &result, error)))
	{
		goto out;
	}

	zbx_alerter_deserialize_top_sources_result(result, sources);
	zbx_free(result);
out:
	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * ZBX_IPC_ALERTER_BEGIN_DISPATCH message serialization/deserialization       *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_alerter_serialize_begin_dispatch(unsigned char **data, const char *subject, const char *message,
		const char *content_name, const char *content_type, const char *content, zbx_uint32_t content_size)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, subject_len, message_len, content_name_len, content_type_len;

	zbx_serialize_prepare_str(data_len, subject);
	zbx_serialize_prepare_str(data_len, message);
	zbx_serialize_prepare_value(data_len, content_size);

	if (0 != content_size)
	{
		data_len += content_size;
		zbx_serialize_prepare_str(data_len, content_name);
		zbx_serialize_prepare_str(data_len, content_type);
	}

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_str(ptr, subject, subject_len);
	ptr += zbx_serialize_str(ptr, message, message_len);
	ptr += zbx_serialize_value(ptr, content_size);

	if (0 != content_size)
	{
		memcpy(ptr, content, content_size);
		ptr += content_size;
		ptr += zbx_serialize_str(ptr, content_name, content_name_len);
		(void)zbx_serialize_str(ptr, content_type, content_type_len);
	}

	return data_len;
}

void	zbx_alerter_deserialize_begin_dispatch(const unsigned char *data, char **subject, char **message,
		char **content_name, char **content_type, char **content, zbx_uint32_t *content_size)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_str(data, subject, len);
	data += zbx_deserialize_str(data, message, len);
	data += zbx_deserialize_value(data, content_size);

	if (0 != *content_size)
	{
		*content = zbx_malloc(NULL, *content_size);
		memcpy(*content, data, *content_size);
		data += *content_size;

		data += zbx_deserialize_str(data, content_name, len);
		(void)zbx_deserialize_str(data, content_type, len);
	}
}

/******************************************************************************
 *                                                                            *
 * ZBX_IPC_ALERTER_SEND_DISPATCH message serialization/deserialization        *
 *                                                                            *
 ******************************************************************************/

zbx_uint32_t	zbx_alerter_serialize_send_dispatch(unsigned char **data, const DB_MEDIATYPE *mt,
		const zbx_vector_str_t *recipients)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, data_alloc = 1024, data_offset = 0, *recipients_len;
	int		i;

	*data = zbx_malloc(NULL, data_alloc);
	zbx_serialize_mediatype(data, &data_alloc, &data_offset, mt);

	zbx_serialize_prepare_value(data_len, recipients->values_num);

	recipients_len = (zbx_uint32_t *)zbx_malloc(NULL, sizeof(zbx_uint32_t) * recipients->values_num);
	for (i = 0; i < recipients->values_num; i++)
	{
		zbx_serialize_prepare_str_len(data_len, recipients->values[i], recipients_len[i]);
	}

	if (data_alloc - data_offset < data_len)
	{
		data_alloc = data_offset + data_len;
		*data = (unsigned char *)zbx_realloc(*data, data_alloc);
	}

	ptr = *data + data_offset;
	ptr += zbx_serialize_value(ptr, recipients->values_num);

	for (i = 0; i < recipients->values_num; i++)
	{
		ptr += zbx_serialize_str(ptr, recipients->values[i], recipients_len[i]);
	}

	zbx_free(recipients_len);

	return data_len + data_offset;
}

void	zbx_alerter_deserialize_send_dispatch(const unsigned char *data, DB_MEDIATYPE *mt, zbx_vector_str_t *recipients)
{
	zbx_uint32_t	len;
	int		recipients_num, i;

	data += zbx_deserialize_mediatype(data, mt);
	data += zbx_deserialize_value(data, &recipients_num);

	zbx_vector_str_reserve(recipients, (size_t)recipients_num);
	for (i = 0; i < recipients_num; i++)
	{
		char	*recipient;

		data += zbx_deserialize_str(data, &recipient, len);
		zbx_vector_str_append(recipients, recipient);
	}
}

#define ZBX_ALERTER_REPORT_TIMEOUT	SEC_PER_MIN * 10

/******************************************************************************
 *                                                                            *
 * Purpose: begin data dispatch                                               *
 *                                                                            *
 * Parameters: dispatch     - [IN] the dispatcher                             *
 *             subject      - [IN] the subject                                *
 *             message      - [IN] the message                                *
 *             content_name - [IN] the content name                           *
 *             content_type - [IN] the content type                           *
 *             content      - [IN] the additional content to dispatch         *
 *             content_size - [IN] the content size                           *
 *             error          [OUT] the error message                         *
 *                                                                            *
 * Return value: SUCCEED - the dispatch was started successfully              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_alerter_begin_dispatch(zbx_alerter_dispatch_t *dispatch, const char *subject, const char *message,
		const char *content_name, const char *content_type, const char *content, zbx_uint32_t content_size,
		char **error)
{
	unsigned char	*data;
	zbx_uint32_t	size;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() subject:\"%s\" content_name:%s content_size:%u message:%s", __func__,
			subject, ZBX_NULL2EMPTY_STR(content_type), content_size, message);

	if (SUCCEED == zbx_ipc_async_socket_connected(&dispatch->alerter))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		zbx_ipc_async_socket_close(&dispatch->alerter);
	}

	if (FAIL == zbx_ipc_async_socket_open(&dispatch->alerter, ZBX_IPC_SERVICE_ALERTER, SEC_PER_MIN, error))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	size = zbx_alerter_serialize_begin_dispatch(&data, subject, message, content_name, content_type, content,
			content_size);

	if (FAIL == zbx_ipc_async_socket_send(&dispatch->alerter, ZBX_IPC_ALERTER_BEGIN_DISPATCH, data, size))
	{
		*error = zbx_strdup(NULL, "cannot send request");
		goto out;
	}

	zbx_vector_ptr_create(&dispatch->results);
	dispatch->total_num = 0;
	ret = SUCCEED;

out:
	zbx_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: dispatch data                                                     *
 *                                                                            *
 * Parameters: dispatch   - [IN] the dispatcher                               *
 *             mediatype  - [IN] the media type to use for sending            *
 *             recipients - [IN] the dispatch recipients                      *
 *             error      - [OUT] the error message                           *
 *                                                                            *
 * Return value: SUCCEED - the dispatch sent successfully                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_alerter_send_dispatch(zbx_alerter_dispatch_t *dispatch, const DB_MEDIATYPE *mediatype,
		const zbx_vector_str_t *recipients, char **error)
{
	unsigned char	*data;
	zbx_uint32_t	size;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() mediatypeid:" ZBX_FS_UI64 " recipients_num:%d", __func__,
			mediatype->mediatypeid, recipients->values_num);

	size = zbx_alerter_serialize_send_dispatch(&data, mediatype, recipients);

	if (FAIL == zbx_ipc_async_socket_send(&dispatch->alerter, ZBX_IPC_ALERTER_SEND_DISPATCH, data, size))
	{
		*error = zbx_strdup(NULL, "cannot send request");
		goto out;
	}

	dispatch->total_num += recipients->values_num;

	ret = SUCCEED;
out:
	zbx_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: finish data dispatch                                              *
 *                                                                            *
 * Parameters: dispatch  - [IN] the dispatcher                                *
 *             sent_num  - [OUT] the number of successfully dispatched        *
 *                              messages                                      *
 *             error     - [OUT] the error message                            *
 *                                                                            *
 * Return value: SUCCEED - the dispatch was finished successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_alerter_end_dispatch(zbx_alerter_dispatch_t *dispatch, char **error)
{
	int				i, ret = FAIL;
	time_t				time_stop;
	zbx_alerter_dispatch_result_t	*result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == zbx_ipc_async_socket_send(&dispatch->alerter, ZBX_IPC_ALERTER_END_DISPATCH, NULL, 0))
	{
		*error = zbx_strdup(NULL, "cannot send request");
		goto out;
	}

	if (FAIL == zbx_ipc_async_socket_flush(&dispatch->alerter, SEC_PER_MIN))
	{
		*error = zbx_strdup(NULL, "cannot flush request");
		goto out;
	}

	/* wait for the send alert responses for all recipients */

	time_stop = time(NULL) + ZBX_ALERTER_REPORT_TIMEOUT;

	for (i = 0; i < dispatch->total_num; i++)
	{
		char			*value = NULL, *errmsg = NULL, *debug = NULL;
		zbx_ipc_message_t	*message;
		time_t			now;

		if (time_stop <= (now = time(NULL)))
		{
			*error = zbx_strdup(NULL, "timeout while waiting for dispatches to be sent");
			goto out;
		}

		if (FAIL == zbx_ipc_async_socket_recv(&dispatch->alerter, time_stop - (int)now, &message))
		{
			*error = zbx_strdup(NULL, "cannot receive response");
			goto out;
		}

		if (NULL == message)
		{
			*error = zbx_strdup(NULL, "timeout while waiting for response");
			goto out;
		}

		switch (message->code)
		{
			case ZBX_IPC_ALERTER_SEND_ALERT:
				result = (zbx_alerter_dispatch_result_t *)zbx_malloc(NULL,
						sizeof(zbx_alerter_dispatch_result_t));
				memset(result, 0, sizeof(zbx_alerter_dispatch_result_t));

				zbx_alerter_deserialize_result_ext(message->data, &result->recipient, &value,
						&result->status, &errmsg, &debug);

				if (SUCCEED != result->status)
				{
					zabbix_log(LOG_LEVEL_DEBUG, "failed to send report to \"%s\": %s",
							result->recipient, ZBX_NULL2EMPTY_STR(errmsg));

					result->info = errmsg;
					errmsg = NULL;
				}
				else
				{
					result->info = value;
					value = NULL;
				}

				zbx_vector_ptr_append(&dispatch->results, result);

				zbx_free(value);
				zbx_free(errmsg);
				zbx_free(debug);

				break;
			case ZBX_IPC_ALERTER_ABORT_DISPATCH:
				*error = zbx_strdup(NULL, "the dispatch was aborted");
				zbx_ipc_message_free(message);
				goto out;
		}

		zbx_ipc_message_free(message);
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error:%s", __func__, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

void	zbx_alerter_dispatch_result_free(zbx_alerter_dispatch_result_t *result)
{
	zbx_free(result->recipient);
	zbx_free(result->info);
	zbx_free(result);
}

void	zbx_alerter_clear_dispatch(zbx_alerter_dispatch_t *dispatch)
{
	if (SUCCEED == zbx_ipc_async_socket_connected(&dispatch->alerter))
		zbx_ipc_async_socket_close(&dispatch->alerter);

	zbx_vector_ptr_clear_ext(&dispatch->results, (zbx_clean_func_t)zbx_alerter_dispatch_result_free);
	zbx_vector_ptr_destroy(&dispatch->results);
}
