/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "db.h"
#include "zbxipcservice.h"
#include "alert_manager.h"
#include "alert_db.h"
#include "alerter_protocol.h"

#define ZBX_POLL_INTERVAL	1

#define ZBX_ALERT_BATCH_SIZE		1000
#define ZBX_MEDIATYPE_CACHE_TTL		SEC_PER_DAY

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

extern int	CONFIG_CONFSYNCER_FREQUENCY;

typedef struct
{
	zbx_hashset_t		mediatypes;
	zbx_ipc_socket_t	am;
}
zbx_am_db_t;

/******************************************************************************
 *                                                                            *
 * Function: am_db_create_alert                                               *
 *                                                                            *
 * Purpose: creates new alert object                                          *
 *                                                                            *
 * Parameters: ...           - [IN] alert data                                *
 *                                                                            *
 * Return value: The alert object.                                            *
 *                                                                            *
 ******************************************************************************/
static zbx_am_db_alert_t	*am_db_create_alert(zbx_uint64_t alertid, zbx_uint64_t mediatypeid, int source,
		int object, zbx_uint64_t objectid, zbx_uint64_t eventid, const char *sendto, const char *subject,
		const char *message, const char *params, int status, int retries)
{
	zbx_am_db_alert_t	*alert;

	alert = (zbx_am_db_alert_t *)zbx_malloc(NULL, sizeof(zbx_am_db_alert_t));
	alert->alertid = alertid;
	alert->mediatypeid = mediatypeid;
	alert->source = source;
	alert->object = object;
	alert->objectid = objectid;
	alert->eventid = eventid;

	alert->sendto = zbx_strdup(NULL, sendto);
	alert->subject = zbx_strdup(NULL, subject);
	alert->message = zbx_strdup(NULL, message);
	alert->params = zbx_strdup(NULL, params);

	alert->status = status;
	alert->retries = retries;

	return alert;
}

/******************************************************************************
 *                                                                            *
 * Function: am_db_init                                                       *
 *                                                                            *
 ******************************************************************************/
static int 	am_db_init(zbx_am_db_t *amdb, char **error)
{
	zbx_hashset_create(&amdb->mediatypes, 5, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (SUCCEED != zbx_ipc_socket_open(&amdb->am, ZBX_IPC_SERVICE_ALERTER, SEC_PER_MIN, error))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: am_db_clear                                                      *
 *                                                                            *
 ******************************************************************************/
static void	am_db_clear(zbx_am_db_t *amdb)
{
	zbx_hashset_iter_t	iter;
	zbx_am_db_mediatype_t	*mediatype;

	zbx_hashset_iter_reset(&amdb->mediatypes, &iter);
	while (NULL != (mediatype = (zbx_am_db_mediatype_t *)zbx_hashset_iter_next(&iter)))
		zbx_am_db_mediatype_clear(mediatype);

	zbx_hashset_destroy(&amdb->mediatypes);
}

/******************************************************************************
 *                                                                            *
 * Function: am_db_get_alerts                                                 *
 *                                                                            *
 * Purpose: reads the new alerts from database                                *
 *                                                                            *
 * Parameters: alerts - [OUT] the new alerts                                  *
 *                                                                            *
 * Comments: One the first call this function will return new and not sent    *
 *           alerts. After that only new alerts are returned.                 *
 *                                                                            *
 * Return value: SUCCEED - the alerts were read successfully                  *
 *               FAIL    - database connection error                          *
 *                                                                            *
 ******************************************************************************/
static int	am_db_get_alerts(zbx_vector_ptr_t *alerts)
{
	static int		status_limit = 2;
	zbx_uint64_t		status_filter[] = {ALERT_STATUS_NEW, ALERT_STATUS_NOT_SENT};
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		alertid, mediatypeid, objectid, eventid;
	int			status, attempts, source, object, ret = SUCCEED;
	zbx_am_db_alert_t	*alert;
	zbx_vector_uint64_t	alertids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&alertids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select a.alertid,a.mediatypeid,a.sendto,a.subject,a.message,a.status,a.retries,"
				"e.source,e.object,e.objectid,a.parameters,a.eventid"
			" from alerts a"
			" left join events e"
				" on a.eventid=e.eventid"
			" where alerttype=%d"
			" and",
			ALERT_TYPE_MESSAGE);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "a.status", status_filter, status_limit);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by a.alertid");

	DBbegin();
	result = DBselect("%s", sql);
	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(alertid, row[0]);
		ZBX_STR2UINT64(mediatypeid, row[1]);
		ZBX_STR2UINT64(eventid, row[11]);
		status = atoi(row[5]);
		attempts = atoi(row[6]);

		if (SUCCEED == DBis_null(row[7]))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update alerts set status=%d,retries=0,error='Related event was removed.';\n",
					ALERT_STATUS_FAILED);
			if (FAIL == (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
				break;
			continue;
		}

		source = atoi(row[7]);
		object = atoi(row[8]);
		ZBX_STR2UINT64(objectid, row[9]);

		alert = am_db_create_alert(alertid, mediatypeid, source, object, objectid, eventid, row[2], row[3],
				row[4], row[10], status, attempts);

		zbx_vector_ptr_append(alerts, alert);

		if (ALERT_STATUS_NEW == alert->status)
			zbx_vector_uint64_append(&alertids, alert->alertid);
	}
	DBfree_result(result);

	if (SUCCEED == ret)
	{
		if (0 != alertids.values_num)
		{
			sql_offset = 0;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update alerts set status=%d where",
					ALERT_STATUS_NOT_SENT);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "alertid", alertids.values,
					alertids.values_num);

		}
		if (16 < sql_offset)
			ret = (ZBX_DB_OK <= DBexecute("%s", sql) ? SUCCEED : FAIL);
	}
	DBcommit();
	zbx_vector_uint64_destroy(&alertids);
	zbx_free(sql);

	if (SUCCEED != ret)
		zbx_vector_ptr_clear_ext(alerts, (zbx_clean_func_t)zbx_am_db_alert_free);
	else
		status_limit = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s alerts:%d", __func__, zbx_result_string(ret), alerts->values_num);

	return ret;
}

#define ZBX_UPDATE_STR(dst, src, ret)			\
	if (NULL == dst || 0 != strcmp(dst, src))	\
	{						\
		dst = zbx_strdup(dst, src);		\
		ret = SUCCEED;				\
	}

#define ZBX_UPDATE_VALUE(dst, src, ret)			\
	if (dst != src)					\
	{						\
		dst = src;				\
		ret = SUCCEED;				\
	}

/******************************************************************************
 *                                                                            *
 * Function: am_db_update_mediatype                                           *
 *                                                                            *
 * Purpose: updates media type object, creating one if necessary              *
 *                                                                            *
 * Return value: Updated mediatype or NULL, if the cached media was up to     *
 *               date.                                                        *
 *                                                                            *
 ******************************************************************************/
static zbx_am_db_mediatype_t	*am_db_update_mediatype(zbx_am_db_t *amdb, time_t now, zbx_uint64_t mediatypeid,
		int type, const char *smtp_server, const char *smtp_helo, const char *smtp_email,
		const char *exec_path, const char *gsm_modem, const char *username, const char *passwd,
		unsigned short smtp_port, unsigned char smtp_security, unsigned char smtp_verify_peer,
		unsigned char smtp_verify_host, unsigned char smtp_authentication, const char *exec_params,
		int maxsessions, int maxattempts, const char *attempt_interval, unsigned char content_type,
		const char *script, const char *timeout, int save_tags)
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
	ZBX_UPDATE_STR(mediatype->exec_params, exec_params, ret);
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
	ZBX_UPDATE_VALUE(mediatype->content_type, content_type, ret);

	ZBX_UPDATE_VALUE(mediatype->save_tags, save_tags, ret);

	return SUCCEED == ret ? mediatype : NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: am_db_update_mediatypes                                          *
 *                                                                            *
 * Purpose: updates alert manager media types                                 *
 *                                                                            *
 * Parameters: amdb            - [IN] the alert manager cache                 *
 *             mediatypeids    - [IN] the media type identifiers              *
 *             medatypeids_num - [IN] the number of media type identifiers    *
 *             mediatypes      - [OUT] the updated mediatypes                 *
 *                                                                            *
 ******************************************************************************/
static void	am_db_update_mediatypes(zbx_am_db_t *amdb, const zbx_uint64_t *mediatypeids, int mediatypeids_num,
		zbx_vector_ptr_t *mediatypes)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	int			type, maxsessions, maxattempts;
	zbx_uint64_t		mediatypeid;
	unsigned short		smtp_port;
	unsigned char		smtp_security, smtp_verify_peer, smtp_verify_host, smtp_authentication, content_type;
	zbx_am_db_mediatype_t	*mediatype;
	time_t			now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select mediatypeid,type,smtp_server,smtp_helo,smtp_email,exec_path,gsm_modem,username,"
				"passwd,smtp_port,smtp_security,smtp_verify_peer,smtp_verify_host,smtp_authentication,"
				"exec_params,maxsessions,maxattempts,attempt_interval,content_type,script,timeout,"
				"save_tags"
			" from media_type"
			" where");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "mediatypeid", mediatypeids, mediatypeids_num);

	result = DBselect("%s", sql);
	zbx_free(sql);

	now = time(NULL);
	while (NULL != (row = DBfetch(result)))
	{
		if (FAIL == is_ushort(row[9], &smtp_port))
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
		maxsessions = atoi(row[15]);
		maxattempts = atoi(row[16]);
		ZBX_STR2UCHAR(content_type, row[18]);

		mediatype = am_db_update_mediatype(amdb, now, mediatypeid, type,row[2], row[3], row[4], row[5],
				row[6], row[7], row[8], smtp_port, smtp_security, smtp_verify_peer, smtp_verify_host,
				smtp_authentication, row[14], maxsessions, maxattempts, row[17], content_type,
				row[19], row[20], atoi(row[21]));

		if (NULL != mediatype)
			zbx_vector_ptr_append(mediatypes, mediatype);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() mediatypes:%d/%d", __func__, mediatypes->values_num, mediatypeids_num);
}

/******************************************************************************
 *                                                                            *
 * Function: am_db_queue_alerts                                               *
 *                                                                            *
 * Purpose: reads alerts/mediatypes from database and queues them in alert    *
 *          manager                                                           *
 *                                                                            *
 * Parameters: amdb            - [IN] the alert manager cache                 *
 *                                                                            *
 ******************************************************************************/
static int	am_db_queue_alerts(zbx_am_db_t *amdb)
{
	zbx_vector_ptr_t	alerts, mediatypes;
	int			i, alerts_num;
	zbx_am_db_alert_t	*alert;
	zbx_vector_uint64_t	mediatypeids;

	zbx_vector_ptr_create(&alerts);
	zbx_vector_uint64_create(&mediatypeids);
	zbx_vector_ptr_create(&mediatypes);

	am_db_get_alerts(&alerts);
	if (0 == alerts.values_num)
		goto out;

	for (i = 0; i < alerts.values_num; i++)
	{
		alert = (zbx_am_db_alert_t *)alerts.values[i];
		zbx_vector_uint64_append(&mediatypeids, alert->mediatypeid);
	}

	zbx_vector_uint64_sort(&mediatypeids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&mediatypeids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	am_db_update_mediatypes(amdb, mediatypeids.values, mediatypeids.values_num, &mediatypes);

	if (0 != mediatypes.values_num)
	{
		unsigned char	*data;
		zbx_uint32_t	data_len;

		data_len = zbx_alerter_serialize_mediatypes(&data, (zbx_am_db_mediatype_t **)mediatypes.values,
				mediatypes.values_num);
		zbx_ipc_socket_write(&amdb->am, ZBX_IPC_ALERTER_MEDIATYPES, data, data_len);
		zbx_free(data);
	}

	for (i = 0; i < alerts.values_num; i += ZBX_ALERT_BATCH_SIZE)
	{
		unsigned char	*data;
		zbx_uint32_t	data_len;
		int		to = i + ZBX_ALERT_BATCH_SIZE;

		if (to >= alerts.values_num)
			to = alerts.values_num;

		data_len = zbx_alerter_serialize_alerts(&data, (zbx_am_db_alert_t **)&alerts.values[i], to - i);
		zbx_ipc_socket_write(&amdb->am, ZBX_IPC_ALERTER_ALERTS, data, data_len);
		zbx_free(data);
	}

out:
	zbx_vector_ptr_destroy(&mediatypes);
	zbx_vector_uint64_destroy(&mediatypeids);
	alerts_num = alerts.values_num;
	zbx_vector_ptr_clear_ext(&alerts, (zbx_clean_func_t)zbx_am_db_alert_free);
	zbx_vector_ptr_destroy(&alerts);

	return alerts_num;
}

/******************************************************************************
 *                                                                            *
 * Function: am_db_flush_results                                              *
 *                                                                            *
 * Purpose: retrieves alert updates from alert manager and flushes them into  *
 *          database                                                          *
 *                                                                            *
 * Parameters: amdb            - [IN] the alert manager cache                 *
 *                                                                            *
 ******************************************************************************/
static int	am_db_flush_results(zbx_am_db_t *amdb)
{
	zbx_ipc_message_t	message;
	zbx_am_result_t		**results;
	int			results_num;

	zbx_ipc_socket_write(&amdb->am, ZBX_IPC_ALERTER_RESULTS, NULL, 0);
	if (SUCCEED != zbx_ipc_socket_read(&amdb->am, &message))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve alert results");
		return 0;
	}

	zbx_alerter_deserialize_results(message.data, &results, &results_num);

	if (0 != results_num)
	{
		int 	i;
		char	*sql;
		size_t	sql_alloc = results_num * 128, sql_offset = 0;

		sql = (char *)zbx_malloc(NULL, sql_alloc);

		DBbegin();
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < results_num; i++)
		{
			zbx_am_result_t	*result = results[i];

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update alerts set status=%d,retries=%d", result->status, result->retries);
			if (NULL != result->error)
			{
				char	*error_esc;
				error_esc = DBdyn_escape_field("alerts", "error", result->error);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",error='%s'", error_esc);
				zbx_free(error_esc);
			}
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where alertid=" ZBX_FS_UI64 ";\n",
					result->alertid);
			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

			zbx_free(result->value);
			zbx_free(result->error);
			zbx_free(result);
		}

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);
		if (16 < sql_offset)
			DBexecute("%s", sql);

		DBcommit();
		zbx_free(sql);
	}

	zbx_free(results);
	zbx_ipc_message_clean(&message);

	return results_num;
}
/******************************************************************************
 *                                                                            *
 * Function: am_db_remove_expired_mediatypes                                  *
 *                                                                            *
 * Purpose: removes cached media types used more than a day ago               *
 *                                                                            *
 * Parameters: amdb            - [IN] the alert manager cache                 *
 *                                                                            *
 ******************************************************************************/
static void	am_db_remove_expired_mediatypes(zbx_am_db_t *amdb)
{
	zbx_hashset_iter_t	iter;
	zbx_am_db_mediatype_t	*mediatype;
	time_t			now;
	zbx_vector_uint64_t	dropids;

	zbx_vector_uint64_create(&dropids);
	now = time(NULL);
	zbx_hashset_iter_reset(&amdb->mediatypes, &iter);
	while (NULL != (mediatype = (zbx_am_db_mediatype_t *)zbx_hashset_iter_next(&iter)))
	{
		if (mediatype->last_access + ZBX_MEDIATYPE_CACHE_TTL <= now)
		{
			zbx_vector_uint64_append(&dropids, mediatype->mediatypeid);
			zbx_am_db_mediatype_clear(mediatype);
			zbx_hashset_iter_remove(&iter);
		}
	}

	if (0 != dropids.values_num)
	{
		unsigned char	*data;
		zbx_uint32_t	data_len;

		data_len = zbx_alerter_serialize_ids(&data, dropids.values, dropids.values_num);
		zbx_ipc_socket_write(&amdb->am, ZBX_IPC_ALERTER_DROP_MEDIATYPES, data, data_len);
		zbx_free(data);
	}

	zbx_vector_uint64_destroy(&dropids);
}

/******************************************************************************
 *                                                                            *
 * Function: am_db_update_watchdog                                            *
 *                                                                            *
 * Purpose: updates watchdog recipients                                       *
 *                                                                            *
 * Parameters: amdb            - [IN] the alert manager cache                 *
 *                                                                            *
 ******************************************************************************/
static void	am_db_update_watchdog(zbx_am_db_t *amdb)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			medias_num;
	zbx_am_media_t		*media;
	zbx_vector_uint64_t	mediatypeids;
	zbx_vector_ptr_t	medias, mediatypes;
	unsigned char		*data;
	zbx_uint32_t		data_len;


	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect_once(
			"select m.mediaid,m.mediatypeid,m.sendto"
			" from media m,users_groups u,config c,media_type mt"
			" where m.userid=u.userid"
				" and u.usrgrpid=c.alert_usrgrpid"
				" and m.mediatypeid=mt.mediatypeid"
				" and m.active=%d"
				" and mt.status=%d",
				MEDIA_STATUS_ACTIVE,
				MEDIA_TYPE_STATUS_ACTIVE);

	zbx_vector_uint64_create(&mediatypeids);
	zbx_vector_ptr_create(&medias);
	zbx_vector_ptr_create(&mediatypes);

	/* read watchdog alert recipients */
	while (NULL != (row = DBfetch(result)))
	{
		media = (zbx_am_media_t *)zbx_malloc(NULL, sizeof(zbx_am_media_t));
		ZBX_STR2UINT64(media->mediaid, row[0]);
		ZBX_STR2UINT64(media->mediatypeid, row[1]);
		media->sendto = zbx_strdup(NULL, row[2]);
		zbx_vector_ptr_append(&medias, media);
		zbx_vector_uint64_append(&mediatypeids, media->mediatypeid);
	}
	DBfree_result(result);

	/* update media types used for watchdog alerts */

	zbx_vector_uint64_sort(&mediatypeids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&mediatypeids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	am_db_update_mediatypes(amdb, mediatypeids.values, mediatypeids.values_num, &mediatypes);

	if (0 != mediatypes.values_num)
	{
		data_len = zbx_alerter_serialize_mediatypes(&data, (zbx_am_db_mediatype_t **)mediatypes.values,
				mediatypes.values_num);
		zbx_ipc_socket_write(&amdb->am, ZBX_IPC_ALERTER_MEDIATYPES, data, data_len);
		zbx_free(data);
	}

	data_len = zbx_alerter_serialize_medias(&data, (zbx_am_media_t **)medias.values, medias.values_num);
	zbx_ipc_socket_write(&amdb->am, ZBX_IPC_ALERTER_WATCHDOG, data, data_len);
	zbx_free(data);

	medias_num = medias.values_num;

	zbx_vector_ptr_destroy(&mediatypes);
	zbx_vector_uint64_destroy(&mediatypeids);
	zbx_vector_ptr_clear_ext(&medias, (zbx_clean_func_t)zbx_am_media_free);
	zbx_vector_ptr_destroy(&medias);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() recipients:%d", __func__, medias_num);
}

ZBX_THREAD_ENTRY(alert_db_thread, args)
{
	double		sec1, sec2;
	int		alerts_num, sleeptime, nextcheck, freq_watchdog, time_watchdog = 0, time_cleanup = 0,
			results_num;
	zbx_am_db_t	amdb;
	char		*error = NULL;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	if (SUCCEED != am_db_init(&amdb, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize alert loader: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	DBconnect(ZBX_DB_CONNECT_NORMAL);

	sec1 = zbx_time();
	sleeptime = ZBX_POLL_INTERVAL;

	if (ZBX_WATCHDOG_ALERT_FREQUENCY < (freq_watchdog = CONFIG_CONFSYNCER_FREQUENCY))
		freq_watchdog = ZBX_WATCHDOG_ALERT_FREQUENCY;

	zbx_setproctitle("%s [started, idle %d sec]", get_process_type_string(process_type), sleeptime);

	while (ZBX_IS_RUNNING())
	{
		zbx_sleep_loop(sleeptime);

		sec1 = zbx_time();
		zbx_update_env(sec1);

		zbx_setproctitle("%s [queuing alerts]", get_process_type_string(process_type));

		alerts_num = am_db_queue_alerts(&amdb);
		results_num = am_db_flush_results(&amdb);

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

		sec2 = zbx_time();

		nextcheck = (int)sec1 - (int)sec1 % ZBX_POLL_INTERVAL + ZBX_POLL_INTERVAL;

		if (0 > (sleeptime = nextcheck - (int)sec2))
			sleeptime = 0;

		zbx_setproctitle("%s [queued %d alerts(s), flushed %d result(s) in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), alerts_num, results_num, sec2 - sec1, sleeptime);
	}

	am_db_clear(&amdb);

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
