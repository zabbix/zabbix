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

#ifndef ZABBIX_ALERTER_PROTOCOL_H
#define ZABBIX_ALERTER_PROTOCOL_H

#include "zbxalerter.h"

#include "zbxalgo.h"
#include "zbxdbhigh.h"

#define ZBX_WATCHDOG_ALERT_FREQUENCY	(15 * SEC_PER_MIN)
#define ZBX_ALERT_NO_DEBUG		0
#define ZBX_ALERT_DEBUG			1

/* media type data */
typedef struct
{
	zbx_uint64_t		mediatypeid;

	int			location;

	/* number of currently processing alerts */
	int			alerts_num;

	/* number of alert objects for this media type */
	int			refcount;

	/* alert pool queue */
	zbx_binary_heap_t	queue;

	/* media type data */
	int			type;
	char			*smtp_server;
	char			*smtp_helo;
	char			*smtp_email;
	char			*exec_path;
	char			*gsm_modem;
	char			*username;
	char			*passwd;
	char			*script;
	char			*script_bin;
	char			*error;
	unsigned short		smtp_port;
	unsigned char		smtp_security;
	unsigned char		smtp_verify_peer;
	unsigned char		smtp_verify_host;
	unsigned char		smtp_authentication;

	int			maxsessions;
	int			maxattempts;
	int			attempt_interval;
	int			timeout;
	int			script_bin_sz;
	unsigned char		message_format;
	unsigned char		flags;
}
zbx_am_mediatype_t;

ZBX_PTR_VECTOR_DECL(am_mediatype_ptr, zbx_am_mediatype_t *)

typedef struct
{
	zbx_uint64_t	mediaid;
	zbx_uint64_t	mediatypeid;
	char		*sendto;
}
zbx_am_media_t;

ZBX_PTR_VECTOR_DECL(am_media_ptr, zbx_am_media_t *)

/* media type data */
typedef struct
{
	zbx_uint64_t		mediatypeid;

	/* media type data */
	unsigned char		type;
	char			*smtp_server;
	char			*smtp_helo;
	char			*smtp_email;
	char			*exec_path;
	char			*gsm_modem;
	char			*username;
	char			*passwd;
	char			*timeout;
	char			*script;
	char			*attempt_interval;
	unsigned short		smtp_port;
	unsigned char		smtp_security;
	unsigned char		smtp_verify_peer;
	unsigned char		smtp_verify_host;
	unsigned char		smtp_authentication;

	int			maxsessions;
	int			maxattempts;
	unsigned char		message_format;
	unsigned char		process_tags;
	time_t			last_access;
}
zbx_am_db_mediatype_t;

ZBX_PTR_VECTOR_DECL(am_db_mediatype_ptr, zbx_am_db_mediatype_t *)

/* alert data */
typedef struct
{
	zbx_uint64_t	alertid;
	zbx_uint64_t	mediatypeid;
	zbx_uint64_t	eventid;
	zbx_uint64_t	objectid;
	zbx_uint64_t	p_eventid;

	char		*sendto;
	char		*subject;
	char		*message;
	char		*params;
	int		status;
	int		retries;
	int		source;
	int		object;

	char		*expression;
	char		*recovery_expression;
}
zbx_am_db_alert_t;

ZBX_PTR_VECTOR_DECL(am_db_alert_ptr, zbx_am_db_alert_t *)

/* alert status update data */
typedef struct
{
	zbx_uint64_t	alertid;
	zbx_uint64_t	eventid;
	zbx_uint64_t	mediatypeid;
	int		retries;
	int		status;
	int		source;
	char		*value;
	char		*error;
}
zbx_am_result_t;

ZBX_PTR_VECTOR_DECL(am_result_ptr, zbx_am_result_t *)

void	zbx_am_db_mediatype_clear(zbx_am_db_mediatype_t *mediatype);
void	zbx_am_db_alert_free(zbx_am_db_alert_t *alert);
void	zbx_am_media_clear(zbx_am_media_t *media);
void	zbx_am_media_free(zbx_am_media_t *media);

zbx_uint32_t	zbx_alerter_serialize_result(unsigned char **data, const char *value, int errcode, const char *error,
		const char *debug);
void	zbx_alerter_deserialize_result(const unsigned char *data, char **value, int *errcode, char **error,
		char **debug);

zbx_uint32_t	zbx_alerter_serialize_result_ext(unsigned char **data, const char *recipient, const char *value,
		int errcode, const char *error, const char *debug);

zbx_uint32_t	zbx_alerter_serialize_email(unsigned char **data, zbx_uint64_t alertid, zbx_uint64_t mediatypeid,
		zbx_uint64_t eventid, int source, int object, zbx_uint64_t objectid, const char *sendto,
		const char *subject, const char *message, const char *smtp_server, unsigned short smtp_port,
		const char *smtp_helo, const char *smtp_email, unsigned char smtp_security,
		unsigned char smtp_verify_peer, unsigned char smtp_verify_host, unsigned char smtp_authentication,
		const char *username, const char *password, unsigned char message_format, const char *expression,
		const char *recovery_expression);

void	zbx_alerter_deserialize_email(const unsigned char *data, zbx_uint64_t *alertid, zbx_uint64_t *mediatypeid,
		zbx_uint64_t *eventid, int *source, int *object, zbx_uint64_t *objectid, char **sendto, char **subject,
		char **message, char **smtp_server, unsigned short *smtp_port, char **smtp_helo, char **smtp_email,
		unsigned char *smtp_security, unsigned char *smtp_verify_peer, unsigned char *smtp_verify_host,
		unsigned char *smtp_authentication, char **username, char **password, unsigned char *message_format,
		char **expression, char **recovery_expression);

zbx_uint32_t	zbx_alerter_serialize_sms(unsigned char **data, zbx_uint64_t alertid,  const char *sendto,
		const char *message, const char *gsm_modem);

void	zbx_alerter_deserialize_sms(const unsigned char *data, zbx_uint64_t *alertid, char **sendto, char **message,
		char **gsm_modem);

zbx_uint32_t	zbx_alerter_serialize_exec(unsigned char **data, zbx_uint64_t alertid, const char *command);

void	zbx_alerter_deserialize_exec(const unsigned char *data, zbx_uint64_t *alertid, char **command);

void	zbx_alerter_deserialize_alert_send(const unsigned char *data, zbx_uint64_t *mediatypeid,
		unsigned char *type, char **smtp_server, char **smtp_helo, char **smtp_email, char **exec_path,
		char **gsm_modem, char **username, char **passwd, unsigned short *smtp_port,
		unsigned char *smtp_security, unsigned char *smtp_verify_peer, unsigned char *smtp_verify_host,
		unsigned char *smtp_authentication, int *maxsessions, int *maxattempts, char **attempt_interval,
		unsigned char *message_format, char **script, char **timeout, char **sendto, char **subject,
		char **message, char **params);

zbx_uint32_t	zbx_alerter_serialize_webhook(unsigned char **data, const char *script_bin, int script_sz,
		int timeout, const char *params, unsigned char debug);

void	zbx_alerter_deserialize_webhook(const unsigned char *data, char **script_bin, int *script_sz, int *timeout,
		char **params, unsigned char *debug);

zbx_uint32_t	zbx_alerter_serialize_mediatypes(unsigned char **data, zbx_am_db_mediatype_t **mediatypes,
		int mediatypes_num);

void	zbx_alerter_deserialize_mediatypes(const unsigned char *data, zbx_am_db_mediatype_t ***mediatypes,
		int *mediatypes_num);

zbx_uint32_t	zbx_alerter_serialize_alerts(unsigned char **data, zbx_am_db_alert_t **alerts, int alerts_num);

void	zbx_alerter_deserialize_alerts(const unsigned char *data, zbx_am_db_alert_t ***alerts, int *alerts_num);

zbx_uint32_t	zbx_alerter_serialize_medias(unsigned char **data, zbx_am_media_t **medias, int medias_num);

void	zbx_alerter_deserialize_medias(const unsigned char *data, zbx_am_media_t ***medias, int *medias_num);

zbx_uint32_t	zbx_alerter_serialize_results(unsigned char **data, zbx_am_result_t **results, int results_num);

void	zbx_alerter_deserialize_results(const unsigned char *data, zbx_am_result_t ***results, int *results_num);

zbx_uint32_t	zbx_alerter_serialize_ids(unsigned char **data, zbx_uint64_t *ids, int ids_num);

void	zbx_alerter_deserialize_ids(const unsigned char *data, zbx_uint64_t **ids, int *ids_num);

void	zbx_alerter_deserialize_top_request(const unsigned char *data, int *limit);

zbx_uint32_t	zbx_alerter_serialize_diag_stats(unsigned char **data, zbx_uint64_t alerts_num);

zbx_uint32_t	zbx_alerter_serialize_top_mediatypes_result(unsigned char **data, zbx_am_mediatype_t **mediatypes,
		int mediatypes_num);

zbx_uint32_t	zbx_alerter_serialize_top_sources_result(unsigned char **data, zbx_am_source_stats_t **sources,
		int sources_num);

zbx_uint32_t	zbx_alerter_serialize_begin_dispatch(unsigned char **data, const char *subject, const char *message,
		const char *content_name, const char *message_format, const char *content, zbx_uint32_t content_size);
void	zbx_alerter_deserialize_begin_dispatch(const unsigned char *data, char **subject, char **message,
		char **content_name, char **message_format, char **content, zbx_uint32_t *content_size);

zbx_uint32_t	zbx_alerter_serialize_send_dispatch(unsigned char **data, const zbx_db_mediatype *mt,
		const zbx_vector_str_t *recipients);
void	zbx_alerter_deserialize_send_dispatch(const unsigned char *data, zbx_db_mediatype *mt, zbx_vector_str_t
		*recipients);

#endif
