/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * Function: zbx_am_db_alert_free                                             *
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

zbx_uint32_t	zbx_alerter_serialize_result(unsigned char **data, const char *value, int errcode, const char *error)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, value_len, error_len;

	zbx_serialize_prepare_str(data_len, value);
	zbx_serialize_prepare_value(data_len, errcode);
	zbx_serialize_prepare_str(data_len, error);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_str(ptr, value, value_len);
	ptr += zbx_serialize_value(ptr, errcode);
	(void)zbx_serialize_str(ptr, error, error_len);

	return data_len;
}

void	zbx_alerter_deserialize_result(const unsigned char *data, char **value, int *errcode, char **error)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_str(data, value, len);
	data += zbx_deserialize_value(data, errcode);
	(void)zbx_deserialize_str(data, error, len);
}

zbx_uint32_t	zbx_alerter_serialize_email(unsigned char **data, zbx_uint64_t alertid, const char *sendto,
		const char *subject, const char *message, const char *smtp_server, unsigned short smtp_port,
		const char *smtp_helo, const char *smtp_email, unsigned char smtp_security,
		unsigned char smtp_verify_peer, unsigned char smtp_verify_host, unsigned char smtp_authentication,
		const char *username, const char *password, unsigned char content_type)
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
	zbx_serialize_prepare_value(data_len, content_type);

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
	ptr += zbx_serialize_str(ptr, password, password_len);
	(void)zbx_serialize_value(ptr, content_type);

	return data_len;
}

void	zbx_alerter_deserialize_email(const unsigned char *data, zbx_uint64_t *alertid, char **sendto, char **subject,
		char **message, char **smtp_server, unsigned short *smtp_port, char **smtp_helo, char **smtp_email,
		unsigned char *smtp_security, unsigned char *smtp_verify_peer, unsigned char *smtp_verify_host,
		unsigned char *smtp_authentication, char **username, char **password, unsigned char *content_type)
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
		int timeout, const char *params)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, params_len;

	data_len += script_sz + sizeof(zbx_uint32_t);
	zbx_serialize_prepare_value(data_len, script_sz);
	zbx_serialize_prepare_value(data_len, timeout);
	zbx_serialize_prepare_str(data_len, params);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_str(ptr, script_bin, script_sz);
	ptr += zbx_serialize_value(ptr, script_sz);
	ptr += zbx_serialize_value(ptr, timeout);
	(void)zbx_serialize_str(ptr, params, params_len);

	return data_len;
}

void	zbx_alerter_deserialize_webhook(const unsigned char *data, char **script_bin, int *script_sz, int *timeout,
		char **params)
{
	zbx_uint32_t	len;

	data += zbx_deserialize_str(data, script_bin, len);
	data += zbx_deserialize_value(data, script_sz);
	data += zbx_deserialize_value(data, timeout);
	(void)zbx_deserialize_str(data, params, len);
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
