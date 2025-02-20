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

#include "zbxalerter.h"
#include "alerter_defs.h"

#include "alerter_protocol.h"

#include "zbxtimekeeper.h"
#include "zbxlog.h"
#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxipcservice.h"
#include "zbxjson.h"
#include "zbxnix.h"
#include "zbxnum.h"
#include "zbxself.h"
#include "zbxservice.h"
#include "zbxstr.h"
#include "zbxthreads.h"
#include "zbxtime.h"
#include "zbxmedia.h"
#include "zbxcacheconfig.h"

typedef struct
{
	zbx_hashset_t		mediatypes;
	zbx_ipc_async_socket_t	am;
}
zbx_am_db_t;

/******************************************************************************
 *                                                                            *
 * Purpose: creates new alert object                                          *
 *                                                                            *
 * Parameters: ...           - [IN] alert data                                *
 *                                                                            *
 * Return value: alert object.                                                *
 *                                                                            *
 ******************************************************************************/
static zbx_am_db_alert_t	*am_db_create_alert(zbx_uint64_t alertid, zbx_uint64_t mediatypeid, int source,
		int object, zbx_uint64_t objectid, zbx_uint64_t eventid, zbx_uint64_t p_eventid, const char *sendto,
		const char *subject, const char *message, const char *params, int status, int retries)
{
	zbx_am_db_alert_t	*alert;

	alert = (zbx_am_db_alert_t *)zbx_malloc(NULL, sizeof(zbx_am_db_alert_t));
	alert->alertid = alertid;
	alert->mediatypeid = mediatypeid;
	alert->source = source;
	alert->object = object;
	alert->objectid = objectid;
	alert->eventid = eventid;
	alert->p_eventid = p_eventid;

	alert->sendto = zbx_strdup(NULL, sendto);
	alert->subject = zbx_strdup(NULL, subject);
	alert->message = zbx_strdup(NULL, message);
	alert->params = zbx_strdup(NULL, params);

	alert->status = status;
	alert->retries = retries;

	alert->expression = NULL;
	alert->recovery_expression = NULL;

	return alert;
}

static int 	am_db_init(zbx_am_db_t *amdb, char **error)
{
	zbx_hashset_create(&amdb->mediatypes, 5, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (SUCCEED != zbx_ipc_async_socket_open(&amdb->am, ZBX_IPC_SERVICE_ALERTER, SEC_PER_MIN, error))
		return FAIL;

	return SUCCEED;
}

static void	am_db_clear(zbx_am_db_t *amdb)
{
	zbx_hashset_iter_t	iter;
	zbx_am_db_mediatype_t	*mediatype;

	zbx_hashset_iter_reset(&amdb->mediatypes, &iter);
	while (NULL != (mediatype = (zbx_am_db_mediatype_t *)zbx_hashset_iter_next(&iter)))
		zbx_am_db_mediatype_clear(mediatype);

	zbx_hashset_destroy(&amdb->mediatypes);

	zbx_ipc_async_socket_close(&amdb->am);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads the new alerts from database                                *
 *                                                                            *
 * Parameters: alerts - [OUT] new alerts                                      *
 *                                                                            *
 * Comments: On the first call this function will return new and not sent     *
 *           alerts. After that only new alerts are returned.                 *
 *                                                                            *
 * Return value: SUCCEED - the alerts were read successfully                  *
 *               FAIL    - database connection error                          *
 *                                                                            *
 ******************************************************************************/
static int	am_db_get_alerts(zbx_vector_am_db_alert_ptr_t *alerts)
{
	static int		status_limit = 2;
	zbx_uint64_t		status_filter[] = {ALERT_STATUS_NEW, ALERT_STATUS_NOT_SENT};
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	int			ret = SUCCEED;
	zbx_am_db_alert_t	*alert;
	zbx_vector_uint64_t	alertids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&alertids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select a.alertid,a.mediatypeid,a.sendto,a.subject,a.message,a.status,a.retries,"
				"e.source,e.object,e.objectid,a.parameters,a.eventid,a.p_eventid"
			" from alerts a"
			" left join events e"
				" on a.eventid=e.eventid"
			" where alerttype=%d"
			" and",
			ALERT_TYPE_MESSAGE);

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "a.status", status_filter, status_limit);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by a.alertid");

	zbx_db_begin();
	result = zbx_db_select("%s", sql);
	sql_offset = 0;

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	alertid, mediatypeid, objectid, eventid, p_eventid;
		int		status, attempts, source, object;

		ZBX_STR2UINT64(alertid, row[0]);
		ZBX_STR2UINT64(mediatypeid, row[1]);
		ZBX_STR2UINT64(eventid, row[11]);
		ZBX_DBROW2UINT64(p_eventid, row[12]);
		status = atoi(row[5]);
		attempts = atoi(row[6]);

		if (SUCCEED == zbx_db_is_null(row[7]))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update alerts set status=%d,retries=0,error='Related event was removed.';\n",
					ALERT_STATUS_FAILED);
			if (FAIL == (ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
				break;
			continue;
		}

		source = atoi(row[7]);
		object = atoi(row[8]);
		ZBX_STR2UINT64(objectid, row[9]);

		alert = am_db_create_alert(alertid, mediatypeid, source, object, objectid, eventid, p_eventid, row[2],
				row[3], row[4], row[10], status, attempts);

		zbx_vector_am_db_alert_ptr_append(alerts, alert);

		if (ALERT_STATUS_NEW == alert->status)
			zbx_vector_uint64_append(&alertids, alert->alertid);
	}
	zbx_db_free_result(result);

	if (SUCCEED == ret)
	{
		if (0 != alertids.values_num)
		{
			sql_offset = 0;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update alerts set status=%d where",
					ALERT_STATUS_NOT_SENT);
			zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "alertid", alertids.values,
					alertids.values_num);

		}
		if (16 < sql_offset)
			ret = (ZBX_DB_OK <= zbx_db_execute("%s", sql) ? SUCCEED : FAIL);
	}
	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK != zbx_db_commit())
			ret = FAIL;
	}
	else
		zbx_db_rollback();

	zbx_vector_uint64_destroy(&alertids);
	zbx_free(sql);

	if (SUCCEED != ret)
		zbx_vector_am_db_alert_ptr_clear_ext(alerts, zbx_am_db_alert_free);
	else
		status_limit = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s alerts:%d", __func__, zbx_result_string(ret), alerts->values_num);

	return ret;
}

static void	am_db_get_trigger_expressions(zbx_vector_uint64_t *auth_email_mediatypeids,
		zbx_vector_am_db_alert_ptr_t *alerts)
{
	int			i;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_am_db_alert_t	*alert;
	zbx_vector_uint64_t	triggerids;

	if (0 == auth_email_mediatypeids->values_num)
		return;

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_sort(auth_email_mediatypeids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (i = 0; i < alerts->values_num; i++)
	{
		alert = (zbx_am_db_alert_t *)alerts->values[i];

		if (((EVENT_SOURCE_INTERNAL == alert->source && EVENT_OBJECT_TRIGGER == alert->object) ||
				EVENT_SOURCE_TRIGGERS == alert->source) &&
				FAIL != zbx_vector_uint64_bsearch(auth_email_mediatypeids, alert->mediatypeid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			zbx_vector_uint64_append(&triggerids, alert->objectid);
		}
	}

	if (0 == triggerids.values_num)
	{
		zbx_vector_uint64_destroy(&triggerids);
		return;
	}

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", triggerids.values,
			triggerids.values_num);

	result = zbx_db_select(
			"select triggerid,expression,recovery_expression"
			" from triggers"
			" where%s",
			sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	triggerid;

		ZBX_STR2UINT64(triggerid, row[0]);

		for (i = 0; i < alerts->values_num; i++)
		{
			alert = (zbx_am_db_alert_t *)alerts->values[i];

			if (((EVENT_SOURCE_INTERNAL == alert->source && EVENT_OBJECT_TRIGGER == alert->object) ||
					EVENT_SOURCE_TRIGGERS == alert->source) && triggerid == alert->objectid)
			{
				alert->expression = zbx_strdup(alert->expression, row[1]);
				alert->recovery_expression = zbx_strdup(alert->recovery_expression, row[2]);
			}
		}
	}
	zbx_db_free_result(result);

	zbx_free(sql);
	zbx_vector_uint64_destroy(&triggerids);
}

#define ZBX_UPDATE_STR(dst, src, ret)				\
	do							\
	{							\
		if (NULL == dst || 0 != strcmp(dst, src))	\
		{						\
			dst = zbx_strdup(dst, src);		\
			ret = SUCCEED;				\
		}						\
	}							\
	while(0)

#define ZBX_UPDATE_VALUE(dst, src, ret)	\
	do				\
	{				\
		if (dst != src)		\
		{			\
			dst = src;	\
			ret = SUCCEED;	\
		}			\
	}				\
	while(0)

/******************************************************************************
 *                                                                            *
 * Purpose: updates media type object, creating one if necessary              *
 *                                                                            *
 * Parameters: amdb - [IN] alert manager cache                                *
 *             ...  - [IN] mediatype data                                     *
 *                                                                            *
 * Return value: Updated mediatype or NULL, if the cached media was up to     *
 *               date.                                                        *
 *                                                                            *
 ******************************************************************************/
static zbx_am_db_mediatype_t	*am_db_update_mediatype(zbx_am_db_t *amdb, time_t now, zbx_uint64_t mediatypeid,
		int type, const char *smtp_server, const char *smtp_helo, const char *smtp_email,
		const char *exec_path, const char *gsm_modem, const char *username, const char *passwd,
		unsigned short smtp_port, unsigned char smtp_security, unsigned char smtp_verify_peer,
		unsigned char smtp_verify_host, unsigned char smtp_authentication, int maxsessions, int maxattempts,
		const char *attempt_interval, unsigned char message_format, const char *script, const char *timeout,
		int process_tags)
{
	zbx_am_db_mediatype_t	*mediatype;
	int			ret = FAIL;

	if (NULL == (mediatype = (zbx_am_db_mediatype_t *)zbx_hashset_search(&amdb->mediatypes, &mediatypeid)))
	{
		zbx_am_db_mediatype_t	mediatype_local = {
				.mediatypeid = mediatypeid
		};

		mediatype = (zbx_am_db_mediatype_t *)zbx_hashset_insert(&amdb->mediatypes, &mediatype_local,
				sizeof(mediatype_local));
		ret = SUCCEED;
	}

	mediatype->last_access = now;
	ZBX_UPDATE_VALUE(mediatype->type, type, ret);
	ZBX_UPDATE_STR(mediatype->smtp_server, smtp_server, ret);
	ZBX_UPDATE_STR(mediatype->smtp_helo, smtp_helo, ret);
	ZBX_UPDATE_STR(mediatype->smtp_email, smtp_email, ret);
	ZBX_UPDATE_STR(mediatype->exec_path, exec_path, ret);
	ZBX_UPDATE_STR(mediatype->gsm_modem, gsm_modem, ret);
	ZBX_UPDATE_STR(mediatype->username, username, ret);
	ZBX_UPDATE_STR(mediatype->passwd, passwd, ret);
	ZBX_UPDATE_STR(mediatype->script, script, ret);
	ZBX_UPDATE_STR(mediatype->timeout, timeout, ret);
	ZBX_UPDATE_STR(mediatype->attempt_interval, attempt_interval, ret);

	ZBX_UPDATE_VALUE(mediatype->smtp_port, smtp_port, ret);
	ZBX_UPDATE_VALUE(mediatype->smtp_security, smtp_security, ret);
	ZBX_UPDATE_VALUE(mediatype->smtp_verify_peer, smtp_verify_peer, ret);
	ZBX_UPDATE_VALUE(mediatype->smtp_verify_host, smtp_verify_host, ret);
	ZBX_UPDATE_VALUE(mediatype->smtp_authentication, smtp_authentication, ret);

	ZBX_UPDATE_VALUE(mediatype->maxsessions, maxsessions, ret);
	ZBX_UPDATE_VALUE(mediatype->maxattempts, maxattempts, ret);
	ZBX_UPDATE_VALUE(mediatype->message_format, message_format, ret);

	ZBX_UPDATE_VALUE(mediatype->process_tags, process_tags, ret);

	return SUCCEED == ret ? mediatype : NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates alert manager media types                                 *
 *                                                                            *
 * Parameters: amdb                     - [IN] the alert manager cache        *
 *             mediatypeids             - [IN]                                *
 *             medatypeids_num          - [IN]                                *
 *             mediatypes               - [OUT]                               *
 *             auth_email_mediatypeids  - [OUT] email media types ids with    *
 *                                              authentication enabled        *
 *                                                                            *
 ******************************************************************************/
static void	am_db_update_mediatypes(zbx_am_db_t *amdb, const zbx_uint64_t *mediatypeids, int mediatypeids_num,
		zbx_vector_am_db_mediatype_ptr_t *mediatypes, zbx_vector_uint64_t *auth_email_mediatypeids)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	time_t		now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select mediatypeid,type,smtp_server,smtp_helo,smtp_email,exec_path,gsm_modem,username,"
				"passwd,smtp_port,smtp_security,smtp_verify_peer,smtp_verify_host,smtp_authentication,"
				"maxsessions,maxattempts,attempt_interval,message_format,script,timeout,process_tags"
			" from media_type"
			" where");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "mediatypeid", mediatypeids, mediatypeids_num);

	result = zbx_db_select("%s", sql);
	zbx_free(sql);

	now = time(NULL);
	while (NULL != (row = zbx_db_fetch(result)))
	{
		int			type, maxsessions, maxattempts;
		zbx_uint64_t		mediatypeid;
		unsigned short		smtp_port;
		unsigned char		smtp_security, smtp_verify_peer, smtp_verify_host, smtp_authentication,
					message_format;
		zbx_am_db_mediatype_t	*mediatype;

		if (FAIL == zbx_is_ushort(row[9], &smtp_port))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		ZBX_STR2UINT64(mediatypeid, row[0]);
		type = atoi(row[1]);
		ZBX_STR2UCHAR(smtp_security, row[10]);
		ZBX_STR2UCHAR(smtp_verify_peer, row[11]);
		ZBX_STR2UCHAR(smtp_verify_host, row[12]);
		ZBX_STR2UCHAR(smtp_authentication, row[13]);
		maxsessions = atoi(row[14]);
		maxattempts = atoi(row[15]);
		ZBX_STR2UCHAR(message_format, row[17]);

		mediatype = am_db_update_mediatype(amdb, now, mediatypeid, type,row[2], row[3], row[4], row[5],
				row[6], row[7], row[8], smtp_port, smtp_security, smtp_verify_peer, smtp_verify_host,
				smtp_authentication, maxsessions, maxattempts, row[16], message_format, row[18], row[19],
				atoi(row[20]));

		if (NULL != mediatype)
			zbx_vector_am_db_mediatype_ptr_append(mediatypes, mediatype);

		if (NULL != auth_email_mediatypeids && MEDIA_TYPE_EMAIL == type &&
				SMTP_AUTHENTICATION_NORMAL_PASSWORD == smtp_authentication)
		{
			zbx_vector_uint64_append(auth_email_mediatypeids, mediatypeid);
		}
	}
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() mediatypes:%d/%d", __func__, mediatypes->values_num, mediatypeids_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads alerts/mediatypes from database and queues them in alert    *
 *          manager                                                           *
 *                                                                            *
 * Parameters: amdb - [IN] alert manager cache                                *
 *                                                                            *
 * Return value: count of alerts                                              *
 *                                                                            *
 ******************************************************************************/
static int	am_db_queue_alerts(zbx_am_db_t *amdb)
{
	zbx_vector_am_db_mediatype_ptr_t	mediatypes;
	zbx_vector_am_db_alert_ptr_t		alerts;
	int					alerts_num;
	zbx_am_db_alert_t			*alert;
	zbx_vector_uint64_t			mediatypeids, auth_email_mediatypeids;

	zbx_vector_am_db_alert_ptr_create(&alerts);
	zbx_vector_uint64_create(&mediatypeids);
	zbx_vector_uint64_create(&auth_email_mediatypeids);
	zbx_vector_am_db_mediatype_ptr_create(&mediatypes);

	if (FAIL == am_db_get_alerts(&alerts) || 0 == alerts.values_num)
		goto out;

	for (int i = 0; i < alerts.values_num; i++)
	{
		alert = (zbx_am_db_alert_t *)alerts.values[i];
		zbx_vector_uint64_append(&mediatypeids, alert->mediatypeid);
	}

	zbx_vector_uint64_sort(&mediatypeids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&mediatypeids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	am_db_update_mediatypes(amdb, mediatypeids.values, mediatypeids.values_num, &mediatypes,
			&auth_email_mediatypeids);

	am_db_get_trigger_expressions(&auth_email_mediatypeids, &alerts);

	if (0 != mediatypes.values_num)
	{
		unsigned char	*data;
		zbx_uint32_t	data_len;

		data_len = zbx_alerter_serialize_mediatypes(&data, (zbx_am_db_mediatype_t **)mediatypes.values,
				mediatypes.values_num);
		if (FAIL == zbx_ipc_async_socket_send(&amdb->am, ZBX_IPC_ALERTER_MEDIATYPES, data, data_len))
			zabbix_log(LOG_LEVEL_ERR, "failed to queue mediatypes in alerter");
		zbx_free(data);
	}

#define ZBX_ALERT_BATCH_SIZE		1000
	for (int i = 0; i < alerts.values_num; i += ZBX_ALERT_BATCH_SIZE)
	{
		unsigned char	*data;
		zbx_uint32_t	data_len;
		int		to = i + ZBX_ALERT_BATCH_SIZE;

		if (to >= alerts.values_num)
			to = alerts.values_num;

		data_len = zbx_alerter_serialize_alerts(&data, (zbx_am_db_alert_t **)&alerts.values[i], to - i);
		if (FAIL == zbx_ipc_async_socket_send(&amdb->am, ZBX_IPC_ALERTER_ALERTS, data, data_len))
			zabbix_log(LOG_LEVEL_ERR, "failed to queue alerts in alerter");
		zbx_free(data);
	}
#undef ZBX_ALERT_BATCH_SIZE

out:
	zbx_vector_am_db_mediatype_ptr_destroy(&mediatypes);
	zbx_vector_uint64_destroy(&mediatypeids);
	zbx_vector_uint64_destroy(&auth_email_mediatypeids);
	alerts_num = alerts.values_num;
	zbx_vector_am_db_alert_ptr_clear_ext(&alerts, zbx_am_db_alert_free);
	zbx_vector_am_db_alert_ptr_destroy(&alerts);

	return alerts_num;
}

typedef struct
{
	zbx_uint64_t		eventid;
	zbx_vector_tags_ptr_t	tags;
	int			need_to_add_problem_tag;
}
zbx_event_tags_t;

ZBX_PTR_VECTOR_DECL(events_tags, zbx_event_tags_t*)
ZBX_PTR_VECTOR_IMPL(events_tags, zbx_event_tags_t*)

static int	zbx_event_tags_compare_func(const void *d1, const void *d2)
{
	const zbx_event_tags_t	*event_tags_1 = *(const zbx_event_tags_t * const *)d1;
	const zbx_event_tags_t	*event_tags_2 = *(const zbx_event_tags_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(event_tags_1->eventid, event_tags_2->eventid);

	return 0;
}

static void	event_tags_free(zbx_event_tags_t *event_tags)
{
	zbx_vector_tags_ptr_clear_ext(&event_tags->tags, zbx_free_tag);
	zbx_vector_tags_ptr_destroy(&event_tags->tags);
	zbx_free(event_tags);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds event tags to sql query                                      *
 *                                                                            *
 * Parameters: eventid     - [IN]  problem_tag update db event                *
 *             params      - [IN]  values to process                          *
 *             events_tags - [OUT] vector of events with tags                 *
 *                                                                            *
 * Comments: The event tags are in json object format.                        *
 *                                                                            *
 ******************************************************************************/
static void	am_db_update_event_tags(zbx_uint64_t eventid, const char *params, zbx_vector_events_tags_t *events_tags)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	struct zbx_json_parse	jp, jp_tags;
	const char		*pnext = NULL;
	char			key[ZBX_DB_TAG_NAME_LEN * 4 + 1], value[ZBX_DB_TAG_VALUE_LEN * 4 + 1];
	int			event_tag_index, need_to_add_problem_tag = 0;
	zbx_event_tags_t	*event_tags, local_event_tags;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64 " tags:%s", __func__, eventid, params);

	result = zbx_db_select("select p.eventid"
			" from events e left join problem p"
				" on p.eventid=e.eventid"
			" where e.eventid=" ZBX_FS_UI64, eventid);

	if (NULL == (row = zbx_db_fetch(result)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot add event tags: event " ZBX_FS_UI64 " was removed", eventid);
		goto out;
	}

	if (SUCCEED != zbx_db_is_null(row[0]))
		need_to_add_problem_tag = 1;

	if (FAIL == zbx_json_open(params, &jp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot process returned result: %s", zbx_json_strerror());
		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_TAGS, &jp_tags))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot process returned result: missing tags field");
		goto out;
	}

	local_event_tags.eventid = eventid;

	event_tag_index = zbx_vector_events_tags_search(events_tags, &local_event_tags, zbx_event_tags_compare_func);

	if (FAIL == event_tag_index)
	{
		event_tags = (zbx_event_tags_t*) zbx_malloc(NULL, sizeof(zbx_event_tags_t));
		event_tags->eventid = eventid;
		zbx_vector_tags_ptr_create(&(event_tags->tags));
		event_tags->need_to_add_problem_tag = need_to_add_problem_tag;
		zbx_vector_events_tags_append(events_tags, event_tags);
	}
	else
		event_tags = events_tags->values[event_tag_index];

	while (NULL != (pnext = zbx_json_pair_next(&jp_tags, pnext, key, sizeof(key))))
	{
		zbx_tag_t	*tag, tag_local = {.tag = key, .value = value};

		if (NULL == zbx_json_decodevalue(pnext, value, sizeof(value), NULL))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "invalid tag value starting with %s", pnext);
			continue;
		}

		zbx_ltrim(key, ZBX_WHITESPACE);
		zbx_ltrim(value, ZBX_WHITESPACE);

		if (ZBX_DB_TAG_NAME_LEN < zbx_strlen_utf8(key))
			key[zbx_strlen_utf8_nchars(key, ZBX_DB_TAG_NAME_LEN)] = '\0';
		if (ZBX_DB_TAG_VALUE_LEN < zbx_strlen_utf8(value))
			value[zbx_strlen_utf8_nchars(value, ZBX_DB_TAG_VALUE_LEN)] = '\0';

		zbx_rtrim(key, ZBX_WHITESPACE);
		zbx_rtrim(value, ZBX_WHITESPACE);

		if (FAIL == zbx_vector_tags_ptr_search(&(event_tags->tags), &tag_local, zbx_compare_tags_and_values))
		{
			tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
			tag->tag = zbx_strdup(NULL, key);
			tag->value = zbx_strdup(NULL, value);
			zbx_vector_tags_ptr_append(&(event_tags->tags), tag);
		}
	}
out:
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes duplicate event tags and checks if problem tags need to   *
 *          be updated                                                        *
 *                                                                            *
 * Parameters: update_event_tags - [IN/OUT] vector of pointers to events with *
 *                                          tags                              *
 *             db_event          - [IN/OUT] event_tag update db event         *
 *             db_problem        - [IN/OUT] problem_tag update db event       *
 *                                                                            *
 ******************************************************************************/
static void	am_db_validate_tags_for_update(zbx_vector_events_tags_t *update_events_tags, zbx_db_insert_t *db_event,
		zbx_db_insert_t *db_problem)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int i = 0; i < update_events_tags->values_num; i++)
	{
		zbx_tag_t		tag_local, *tag;
		zbx_db_result_t		result;
		zbx_db_row_t		row;
		zbx_event_tags_t	*local_event_tags = update_events_tags->values[i];

		/* remove duplicate tags */
		if (0 != local_event_tags->tags.values_num)
		{
			result = zbx_db_select("select tag,value from event_tag where eventid=" ZBX_FS_UI64,
					local_event_tags->eventid);

			while (NULL != (row = zbx_db_fetch(result)))
			{
				int	index;

				tag_local.tag = row[0];
				tag_local.value = row[1];

				if (FAIL != (index = zbx_vector_tags_ptr_search(&(local_event_tags->tags), &tag_local,
						zbx_compare_tags_and_values)))
				{
					zbx_free_tag(local_event_tags->tags.values[index]);
					zbx_vector_tags_ptr_remove_noorder(&(local_event_tags->tags), index);
				}
			}

			zbx_db_free_result(result);
		}

		for (int j = 0; j < local_event_tags->tags.values_num; j++)
		{
			tag = local_event_tags->tags.values[j];
			zbx_db_insert_add_values(db_event, __UINT64_C(0), local_event_tags->eventid, tag->tag,
					tag->value);

			if (0 != local_event_tags->need_to_add_problem_tag)
			{
				zbx_db_insert_add_values(db_problem, __UINT64_C(0), local_event_tags->eventid,
						tag->tag, tag->value);
			}
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	am_service_add_event_tags(zbx_vector_events_tags_t *events_tags)
{
	unsigned char	*data = NULL;
	size_t		data_alloc = 0, data_offset = 0;

	for (int i = 0; i < events_tags->values_num; i++)
	{
		zbx_event_tags_t	*event_tag = events_tags->values[i];

		zbx_service_serialize_problem_tags(&data, &data_alloc, &data_offset, event_tag->eventid,
				&event_tag->tags);
	}

	if (NULL == data)
		return;

	if (0 != zbx_dc_get_itservices_num())
		zbx_service_flush(ZBX_IPC_SERVICE_SERVICE_PROBLEMS_TAGS, data, data_offset);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flushes alert results to database                                 *
 *                                                                            *
 * Parameters: mediatypes - [IN]                                              *
 *             data       - [IN] serialized alert results                     *
 *                                                                            *
 * Return value: count of results                                             *
 *                                                                            *
 ******************************************************************************/
static int	am_db_flush_results(zbx_hashset_t *mediatypes, const unsigned char *data)
{
	int				results_num;
	zbx_vector_events_tags_t	update_events_tags;
	zbx_am_result_t			**results;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_events_tags_create(&update_events_tags);

	zbx_alerter_deserialize_results(data, &results, &results_num);

	if (0 != results_num)
	{
		int 		ret;
		char		*sql;
		size_t		sql_alloc = results_num * 128, sql_offset;
		zbx_db_insert_t	db_event, db_problem;

		sql = (char *)zbx_malloc(NULL, sql_alloc);

		do
		{
			zbx_vector_events_tags_clear_ext(&update_events_tags, event_tags_free);
			sql_offset = 0;

			zbx_db_begin();
			zbx_db_insert_prepare(&db_event, "event_tag", "eventtagid", "eventid", "tag", "value",
					(char *)NULL);
			zbx_db_insert_prepare(&db_problem, "problem_tag", "problemtagid", "eventid", "tag", "value",
					(char *)NULL);

			for (int i = 0; i < results_num; i++)
			{
				zbx_am_db_mediatype_t	*mediatype;
				zbx_am_result_t		*result = results[i];

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"update alerts set status=%d,retries=%d",
						result->status, result->retries);

				if (NULL != result->error)
				{
					char	*error_esc;

					error_esc = zbx_db_dyn_escape_field("alerts", "error", result->error);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",error='%s'", error_esc);
					zbx_free(error_esc);
				}
				else
					zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ",error=''");

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where alertid=" ZBX_FS_UI64 ";\n",
						result->alertid);

				if ((EVENT_SOURCE_TRIGGERS == result->source ||
						EVENT_SOURCE_INTERNAL == result->source ||
						EVENT_SOURCE_SERVICE == result->source) && NULL != result->value)
				{
					mediatype = zbx_hashset_search(mediatypes, &result->mediatypeid);
					if (NULL != mediatype && 0 != mediatype->process_tags)
					{
						am_db_update_event_tags(result->eventid, result->value,
								&update_events_tags);
					}
				}
				zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
			}

			am_db_validate_tags_for_update(&update_events_tags, &db_event, &db_problem);

			(void)zbx_db_flush_overflowed_sql(sql, sql_offset);
			zbx_db_insert_autoincrement(&db_event, "eventtagid");
			zbx_db_insert_execute(&db_event);
			zbx_db_insert_clean(&db_event);

			zbx_db_insert_autoincrement(&db_problem, "problemtagid");
			zbx_db_insert_execute(&db_problem);
			zbx_db_insert_clean(&db_problem);
		}
		while (ZBX_DB_DOWN == (ret = zbx_db_commit()));

		if (ZBX_DB_OK == ret)
			am_service_add_event_tags(&update_events_tags);

		for (int i = 0; i < results_num; i++)
		{
			zbx_am_result_t	*result = results[i];

			zbx_free(result->value);
			zbx_free(result->error);
			zbx_free(result);
		}

		zbx_free(sql);
	}

	zbx_vector_events_tags_clear_ext(&update_events_tags, event_tags_free);
	zbx_vector_events_tags_destroy(&update_events_tags);
	zbx_free(results);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() flushed:%d", __func__, results_num);

	return results_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes cached media types used more than a day ago               *
 *                                                                            *
 * Parameters: amdb - [IN] alert manager cache                                *
 *                                                                            *
 ******************************************************************************/
static void	am_db_remove_expired_mediatypes(zbx_am_db_t *amdb)
{
	zbx_hashset_iter_t	iter;
	zbx_am_db_mediatype_t	*mediatype;
	time_t			now;
	zbx_vector_uint64_t	dropids;
	int			num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&dropids);
	now = time(NULL);
	zbx_hashset_iter_reset(&amdb->mediatypes, &iter);

#define ZBX_MEDIATYPE_CACHE_TTL	SEC_PER_DAY
	while (NULL != (mediatype = (zbx_am_db_mediatype_t *)zbx_hashset_iter_next(&iter)))
	{
		if (mediatype->last_access + ZBX_MEDIATYPE_CACHE_TTL <= now)
		{
			zbx_vector_uint64_append(&dropids, mediatype->mediatypeid);
			zbx_am_db_mediatype_clear(mediatype);
			zbx_hashset_iter_remove(&iter);
		}
	}
#undef ZBX_MEDIATYPE_CACHE_TTL

	if (0 != dropids.values_num)
	{
		unsigned char	*data;
		zbx_uint32_t	data_len;

		data_len = zbx_alerter_serialize_ids(&data, dropids.values, dropids.values_num);
		if (FAIL == zbx_ipc_async_socket_send(&amdb->am, ZBX_IPC_ALERTER_DROP_MEDIATYPES, data, data_len))
			zabbix_log(LOG_LEVEL_ERR, "failed to send request to drop old media types");
		zbx_free(data);
	}

	num = dropids.values_num;
	zbx_vector_uint64_destroy(&dropids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() removed:%d", __func__, num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates watchdog recipients                                       *
 *                                                                            *
 * Parameters: amdb - [IN] alert manager cache                                *
 *                                                                            *
 ******************************************************************************/
static void	am_db_update_watchdog(zbx_am_db_t *amdb)
{
	zbx_db_result_t				result;
	zbx_db_row_t				row;
	int					medias_num = 0;
	zbx_vector_uint64_t			mediatypeids;
	zbx_vector_am_db_mediatype_ptr_t	mediatypes;
	zbx_vector_am_media_ptr_t		medias;
	unsigned char				*data;
	zbx_uint32_t				data_len;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select(
			"select m.mediaid,m.mediatypeid,m.sendto"
			" from media m,users_groups u,config c,media_type mt"
			" where m.userid=u.userid"
				" and u.usrgrpid=c.alert_usrgrpid"
				" and m.mediatypeid=mt.mediatypeid"
				" and m.active=%d"
				" and mt.status=%d"
				" and mt.type<>%d",
				MEDIA_STATUS_ACTIVE,
				MEDIA_TYPE_STATUS_ACTIVE,
				MEDIA_TYPE_WEBHOOK);

	zbx_vector_uint64_create(&mediatypeids);
	zbx_vector_am_media_ptr_create(&medias);
	zbx_vector_am_db_mediatype_ptr_create(&mediatypes);

	/* read watchdog alert recipients */
	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_am_media_t	*media = (zbx_am_media_t *)zbx_malloc(NULL, sizeof(zbx_am_media_t));

		ZBX_STR2UINT64(media->mediaid, row[0]);
		ZBX_STR2UINT64(media->mediatypeid, row[1]);
		media->sendto = zbx_strdup(NULL, row[2]);
		zbx_vector_am_media_ptr_append(&medias, media);
		zbx_vector_uint64_append(&mediatypeids, media->mediatypeid);
	}
	zbx_db_free_result(result);

	/* update media types used for watchdog alerts */

	if (0 != mediatypeids.values_num)
	{
		zbx_vector_uint64_sort(&mediatypeids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&mediatypeids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		am_db_update_mediatypes(amdb, mediatypeids.values, mediatypeids.values_num, &mediatypes, NULL);

		if (0 != mediatypes.values_num)
		{
			data_len = zbx_alerter_serialize_mediatypes(&data, (zbx_am_db_mediatype_t **)mediatypes.values,
					mediatypes.values_num);
			if (FAIL == zbx_ipc_async_socket_send(&amdb->am, ZBX_IPC_ALERTER_MEDIATYPES, data, data_len))
				zabbix_log(LOG_LEVEL_ERR, "failed to send watchdog media types");
			zbx_free(data);
		}
	}

	data_len = zbx_alerter_serialize_medias(&data, (zbx_am_media_t **)medias.values, medias.values_num);
	if (FAIL == zbx_ipc_async_socket_send(&amdb->am, ZBX_IPC_ALERTER_WATCHDOG, data, data_len))
		zabbix_log(LOG_LEVEL_ERR, "failed to update watchdog recipients");
	zbx_free(data);

	medias_num = medias.values_num;

	zbx_vector_am_media_ptr_clear_ext(&medias, zbx_am_media_free);
	zbx_vector_am_db_mediatype_ptr_destroy(&mediatypes);
	zbx_vector_uint64_destroy(&mediatypeids);
	zbx_vector_am_media_ptr_destroy(&medias);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() recipients:%d", __func__, medias_num);
}

static void	alert_syncer_register(zbx_ipc_async_socket_t *socket)
{
	pid_t	ppid;

	ppid = getppid();

	if (FAIL == zbx_ipc_async_socket_send(socket, ZBX_IPC_ALERT_SYNCER_REGISTER, (unsigned char *)&ppid,
			sizeof(ppid)))
	{
		zabbix_log(LOG_LEVEL_ERR, "failed to send syncer register message");
	}
}

ZBX_THREAD_ENTRY(zbx_alert_syncer_thread, args)
{
#define ZBX_POLL_INTERVAL		1
	zbx_thread_alert_syncer_args	*alert_syncer_args_in = (zbx_thread_alert_syncer_args *)
							(((zbx_thread_args_t *)args)->args);
	int				sleeptime, freq_watchdog, alerts_num;
	zbx_am_db_t			amdb;
	char				*error = NULL;
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	double				time_cleanup = 0,  time_watchdog = 0, time_results = 0, sec1;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	if (SUCCEED != am_db_init(&amdb, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize alert loader: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	alert_syncer_register(&amdb.am);

	sleeptime = ZBX_POLL_INTERVAL;

	if (ZBX_WATCHDOG_ALERT_FREQUENCY < (freq_watchdog = alert_syncer_args_in->confsyncer_frequency))
		freq_watchdog = ZBX_WATCHDOG_ALERT_FREQUENCY;

	zbx_setproctitle("%s [queuing alerts]", get_process_type_string(process_type));

	sec1 = zbx_time();
	alerts_num = am_db_queue_alerts(&amdb);

	zbx_setproctitle("%s [queued %d alerts(s) in " ZBX_FS_DBL " sec]",
			get_process_type_string(process_type), alerts_num, zbx_time() - sec1);


	while (ZBX_IS_RUNNING())
	{
		int			results_num = 0;
		zbx_ipc_message_t	*message = NULL;

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		if (SUCCEED != zbx_ipc_async_socket_recv(&amdb.am, sleeptime, &message))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot read alert syncer request");
			exit(EXIT_FAILURE);
		}
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		sec1 = zbx_time();

		zbx_update_env(get_process_type_string(process_type), sec1);

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_ALERTER_SYNC_ALERTS:
					zbx_setproctitle("%s [queuing alerts]", get_process_type_string(process_type));
					alerts_num = am_db_queue_alerts(&amdb);
					break;
				case ZBX_IPC_ALERTER_RESULTS:
					results_num = am_db_flush_results(&amdb.mediatypes, message->data);
					break;
				default:
					zabbix_log(LOG_LEVEL_WARNING, "unrecognized message in alert syncer %u",
							message->code);
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (time_results + ZBX_POLL_INTERVAL < sec1)
		{
			if (FAIL == zbx_ipc_async_socket_send(&amdb.am, ZBX_IPC_ALERTER_RESULTS, NULL, 0))
				zabbix_log(LOG_LEVEL_ERR, "failed to request alert results");

			time_results = sec1;
		}

		if (time_cleanup + SEC_PER_HOUR < sec1)
		{
			am_db_remove_expired_mediatypes(&amdb);
			time_cleanup = sec1;
		}

		if (time_watchdog + freq_watchdog < sec1)
		{
			am_db_update_watchdog(&amdb);
			time_watchdog = sec1;
		}

		double	sec2 = zbx_time();

		time_t	nextcheck = (time_t)sec1 + ZBX_POLL_INTERVAL;

		sleeptime = (int)((nextcheck > (time_t)sec2) ? nextcheck - (time_t)sec2 : 0);

		zbx_setproctitle("%s [queued %d alerts(s), flushed %d result(s) in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), alerts_num, results_num, sec2 - sec1, sleeptime);
	}

	am_db_clear(&amdb);

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef ZBX_POLL_INTERVAL
}
