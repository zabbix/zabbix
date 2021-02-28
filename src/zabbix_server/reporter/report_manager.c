/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
#include "zbxself.h"
#include "zbxipcservice.h"
#include "daemon.h"
#include "db.h"
#include "zbxjson.h"
#include "base64.h"
#include "../../libs/zbxcrypto/aes.h"
#include "zbxmedia.h"
#include "zbxreport.h"
#include "report_manager.h"
#include "report_protocol.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num, CONFIG_REPORTWRITER_FORKS;

/* report manager data */
typedef struct
{
	/* config.url, config.session_key fields synced from database together with reprots */
	char			*zabbix_url;
	char			*session_key;

	/* the IPC service */
	zbx_ipc_service_t	ipc;

	/* the next writer index to be assigned to new IPC service clients */
	int			next_writer_index;

	/* report writer vector, created during manager initialization */
	zbx_vector_ptr_t	writers;
	zbx_queue_ptr_t		free_writers;

	zbx_hashset_t		sessions;
	zbx_hashset_t		reports;

	zbx_binary_heap_t	report_queue;

	zbx_list_t		job_queue;
}
zbx_rm_t;

typedef struct
{
	zbx_uint64_t		reportid;
	zbx_uint64_t		dashboardid;

	zbx_vector_ptr_pair_t	params;

	int			nextcheck;
}
zbx_rm_report_t;

typedef struct
{
	char			*url;
	char			*cookie;
	zbx_vector_uint64_t	userids;
	zbx_vector_ptr_pair_t	params;

	zbx_ipc_client_t	*client;
}
zbx_rm_job_t;

/* user session, cached to generate authentication cookies */
typedef struct
{
	zbx_uint64_t	userid;
	char		*sid;
	char		*cookie;
	int		db_lastaccess;
	int		lastaccess;
}
zbx_rm_session_t;

typedef struct
{
	/* the connected report writer client */
	zbx_ipc_client_t	*client;

	zbx_rm_job_t		*job;
}
zbx_rm_writer_t;

/******************************************************************************
 *                                                                            *
 * Function: rm_get_writer                                                    *
 *                                                                            *
 * Purpose: return writer with the specified client                           *
 *                                                                            *
 ******************************************************************************/
static	zbx_rm_writer_t	*rm_get_writer(zbx_rm_t *manager, const zbx_ipc_client_t *client)
{
	int	i;

	for (i = 0; i < manager->writers.values_num; i++)
	{
		zbx_rm_writer_t	*writer = (zbx_rm_writer_t *)manager->writers.values[i];

		if (writer->client == client)
			return writer;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: rm_writer_free                                                   *
 *                                                                            *
 ******************************************************************************/
static void	rm_writer_free(zbx_rm_writer_t *writer)
{
	zbx_ipc_client_close(writer->client);
	zbx_free(writer);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_report_compare_nextcheck                                      *
 *                                                                            *
 ******************************************************************************/
static int	rm_report_compare_nextcheck(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	return ((zbx_rm_report_t *)e1->data)->nextcheck - ((zbx_rm_report_t *)e2->data)->nextcheck;
}

/******************************************************************************
 *                                                                            *
 * Function: rm_report_clean                                                  *
 *                                                                            *
 ******************************************************************************/
static void	rm_report_clean(zbx_rm_report_t *report)
{
	report_destroy_params(&report->params);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_job_free                                                      *
 *                                                                            *
 ******************************************************************************/
static void	rm_job_free(zbx_rm_job_t *job)
{
	zbx_free(job->url);
	zbx_free(job->cookie);

	zbx_vector_uint64_destroy(&job->userids);
	report_destroy_params(&job->params);

	zbx_free(job);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_init                                                          *
 *                                                                            *
 * Purpose: initializes report manager                                        *
 *                                                                            *
 * Parameters: manager - [IN] the manager to initialize                       *
 *                                                                            *
 ******************************************************************************/
static int	rm_init(zbx_rm_t *manager, char **error)
{
	int		i, ret;
	zbx_rm_writer_t	*writer;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() writers:%d", __func__, CONFIG_REPORTWRITER_FORKS);

	if (FAIL == (ret = zbx_ipc_service_start(&manager->ipc, ZBX_IPC_SERVICE_REPORTER, error)))
		goto out;

	zbx_vector_ptr_create(&manager->writers);
	zbx_queue_ptr_create(&manager->free_writers);
	zbx_hashset_create(&manager->sessions, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&manager->reports, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_binary_heap_create(&manager->report_queue, rm_report_compare_nextcheck, ZBX_BINARY_HEAP_OPTION_DIRECT);
	zbx_list_create(&manager->job_queue);

	manager->next_writer_index = 0;
	manager->session_key = NULL;
	manager->zabbix_url = NULL;

	for (i = 0; i < CONFIG_REPORTWRITER_FORKS; i++)
	{
		writer = (zbx_rm_writer_t *)zbx_malloc(NULL, sizeof(zbx_rm_writer_t));
		writer->client = NULL;
		zbx_vector_ptr_append(&manager->writers, writer);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: rm_destroy                                                       *
 *                                                                            *
 * Purpose: destroys report manager                                           *
 *                                                                            *
 * Parameters: manager - [IN] the manager to destroy                          *
 *                                                                            *
 ******************************************************************************/
static void	rm_destroy(zbx_rm_t *manager)
{
	zbx_hashset_iter_t	iter;
	zbx_rm_session_t	*session;
	zbx_rm_report_t		*report;
	zbx_rm_job_t		*job;

	while (SUCCEED == zbx_list_pop(&manager->job_queue, (void **)&job))
		rm_job_free(job);

	zbx_hashset_iter_reset(&manager->sessions, &iter);
	while (NULL != (session = (zbx_rm_session_t *)zbx_hashset_iter_next(&iter)))
		zbx_free(session->sid);
	zbx_hashset_destroy(&manager->sessions);

	zbx_hashset_iter_reset(&manager->reports, &iter);
	while (NULL != (report = (zbx_rm_report_t *)zbx_hashset_iter_next(&iter)))
		rm_report_clean(report);
	zbx_hashset_destroy(&manager->reports);

	zbx_queue_ptr_destroy(&manager->free_writers);
	zbx_vector_ptr_clear_ext(&manager->writers, (zbx_mem_free_func_t)rm_writer_free);
	zbx_vector_ptr_destroy(&manager->writers);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_register_writer                                               *
 *                                                                            *
 * Purpose: registers report writer                                           *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected writer                            *
 *             message - [IN] the received message                            *
 *                                                                            *
 ******************************************************************************/
static void	rm_register_writer(zbx_rm_t *manager, zbx_ipc_client_t *client, zbx_ipc_message_t *message)
{
	zbx_rm_writer_t	*writer = NULL;
	pid_t		ppid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	memcpy(&ppid, message->data, sizeof(ppid));

	if (ppid != getppid())
	{
		zbx_ipc_client_close(client);
		zabbix_log(LOG_LEVEL_DEBUG, "refusing connection from foreign process");
	}
	else
	{
		if (manager->next_writer_index == manager->writers.values_num)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		writer = (zbx_rm_writer_t *)manager->writers.values[manager->next_writer_index++];
		writer->client = client;

		zbx_queue_ptr_push(&manager->free_writers, writer);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_time_to_urlfield                                              *
 *                                                                            *
 * Purpose: convert timestamp to range format used in URL query fields        *
 *                                                                            *
 * Parameters: tm - [IN] the timestamp                                        *
 *                                                                            *
 * Return value: formatted time to be used in URL query fields                *
 *                                                                            *
 ******************************************************************************/
static char	*rm_time_to_urlfield(const struct tm *tm)
{
	static char	buf[26];

	zbx_snprintf(buf, sizeof(buf), "%02d-%02d-%02d%%20%02d%%3A%02d%%3A%02d", tm->tm_year + 1900, tm->tm_mon + 1,
			tm->tm_mday, tm->tm_hour, tm->tm_min, tm->tm_sec);

	return buf;
}

/******************************************************************************
 *                                                                            *
 * Function: report_create_cookie                                             *
 *                                                                            *
 * Purpose: create zbx_session cookie for frontend authentication             *
 *                                                                            *
 * Parameters: manager   - [IN] the manager                                   *
 *             sessionid - [IN] the session id                                *
 *                                                                            *
 * Return value: zbx_session cookie                                           *
 *                                                                            *
 ******************************************************************************/
static char	*report_create_cookie(zbx_rm_t *manager, const char *sessionid)
{
	struct zbx_json	j;
	char		*sign = NULL, *cookie = NULL, *sign_esc, *sign_raw;
	size_t		size, i;
	unsigned char	*data;
	struct AES_ctx 	ctx;

	zbx_json_init(&j, 512);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_SESSIONID, sessionid, ZBX_JSON_TYPE_STRING);

	size = (j.buffer_size / 16 + 1) * 16;
	data = zbx_malloc(NULL, size);
	memcpy(data, j.buffer, j.buffer_size);

	if (j.buffer_size < size)
		memset(data + j.buffer_size, size - j.buffer_size, size - j.buffer_size);

	AES_init_ctx(&ctx, (unsigned char *)manager->session_key);

	for (i = 0; i < size / 16; i++)
		AES_ECB_encrypt(&ctx, data + i * 16);

	str_base64_encode_dyn((char *)data, &sign, size);
	sign_esc = zbx_dyn_escape_string(sign, "/");
	sign_raw = zbx_dsprintf(NULL, "\"%s\"", sign_esc);

	zbx_json_addraw(&j, ZBX_PROTO_TAG_SIGN, sign_raw);
	str_base64_encode_dyn(j.buffer, &cookie, j.buffer_size);

	zbx_free(sign_raw);
	zbx_free(sign_esc);
	zbx_free(sign);
	zbx_free(data);
	zbx_json_clean(&j);

	return cookie;
}

/******************************************************************************
 *                                                                            *
 * Function: rm_get_session                                                   *
 *                                                                            *
 * Purpose: get specified user session, creating one if necessary             *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             userid  - [IN] the userid                                      *
 *                                                                            *
 * Return value: session                                                      *
 *                                                                            *
 * Comments: When returning new session it's cached and also stored in        *
 *           database.                                                        *
 *           When returning cached session database is checked if the session *
 *           is not removed. In that case a new session is created.           *
 *                                                                            *
 ******************************************************************************/
static	zbx_rm_session_t	*rm_get_session(zbx_rm_t *manager, zbx_uint64_t userid)
{
	zbx_rm_session_t	*session;
	int			now;

	now = time(NULL);

	if (NULL != (session = (zbx_rm_session_t *)zbx_hashset_search(&manager->sessions, &userid)))
	{
		DB_RESULT	result;

		result = DBselect("select NULL from sessions where sessionid='%s'", session->sid);
		if (NULL == DBfetch(result))
		{
			zbx_hashset_remove_direct(&manager->sessions, session);
			session = NULL;
		}
		DBfree_result(result);
	}

	if (NULL == session)
	{
		zbx_rm_session_t	session_local;
		zbx_db_insert_t		db_insert;

		session_local.userid = userid;
		session = (zbx_rm_session_t *)zbx_hashset_insert(&manager->sessions, &session_local,
				sizeof(session_local));

		session->sid = zbx_create_token(0);
		session->cookie = report_create_cookie(manager, session->sid);
		session->db_lastaccess = now;

		zbx_db_insert_prepare(&db_insert, "sessions", "sessionid", "userid", "lastaccess", "status", NULL);
		zbx_db_insert_add_values(&db_insert, session->sid, userid, now, ZBX_SESSION_ACTIVE);
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	session->lastaccess = now;

	return session;
}

/******************************************************************************
 *                                                                            *
 * Function: rm_flush_sessions                                                *
 *                                                                            *
 * Purpose: flushes session lastaccess changes to database                    *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *                                                                            *
 ******************************************************************************/
static void	rm_flush_sessions(zbx_rm_t *manager)
{
	zbx_hashset_iter_t	iter;
	zbx_rm_session_t	*session;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	DBbegin();
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	zbx_hashset_iter_reset(&manager->sessions, &iter);
	while (NULL != (session = (zbx_rm_session_t *)zbx_hashset_iter_next(&iter)))
	{
		if (session->lastaccess == session->db_lastaccess)
			continue;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update sessions set lastaccess=%d"
				" where sessionid='%s';\n", session->lastaccess, session->sid);
		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		session->db_lastaccess = session->lastaccess;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)
		DBexecute("%s", sql);

	DBcommit();
	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_get_report_range                                              *
 *                                                                            *
 * Purpose: calculate report range from report time and period                *
 *                                                                            *
 * Parameters: report_time - [IN] the report writing time                     *
 *             period      - [IN] the dashboard period                        *
 *             from        - [OUT] the report start time                      *
 *             to          - [OUT] the report end time                        *
 *                                                                            *
 * Return value: SUCCEED - the report range was calculated successfully       *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	rm_get_report_range(time_t report_time, unsigned char period, struct tm *from, struct tm *to)
{
	struct tm	*tm;
	zbx_time_unit_t	period2unit[] = {ZBX_TIME_UNIT_DAY, ZBX_TIME_UNIT_WEEK, ZBX_TIME_UNIT_MONTH, ZBX_TIME_UNIT_YEAR};

	if (ARRSIZE(period2unit) <= period || NULL == (tm = localtime(&report_time)))
		return FAIL;

	*to = *tm;
	to->tm_sec = 0;
	to->tm_min = 0;
	to->tm_hour = 0;

	*from = *to;
	zbx_tm_sub(from, 1, period2unit[period]);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: rm_create_job                                                    *
 *                                                                            *
 * Purpose: create new job to be processed by report writers                  *
 *                                                                            *
 * Parameters: manager       - [IN] the manager                               *
 *             dashboardid   - [IN] the dashboard to view                     *
 *             viewer_userid - [IN] the user viewing the dashboard            *
 *             report_time   - [IN] the report time                           *
 *             period        - [IN] the report period                         *
 *             userids       - [IN] the recipient user identifiers            *
 *             userids_num   - [IN] the number of recipients                  *
 *             params        - [IN] the viewing and processing parameters     *
 *                                                                            *
 ******************************************************************************/
static zbx_rm_job_t	*rm_create_job(zbx_rm_t *manager, zbx_uint64_t dashboardid, zbx_uint64_t viewer_userid,
		int report_time, unsigned char period, zbx_uint64_t *userids, int userids_num,
		zbx_vector_ptr_pair_t *params, char **error)
{
	zbx_rm_job_t		*job;
	size_t			url_alloc = 0, url_offset = 0;
	zbx_rm_session_t	*session;
	struct tm		from, to;

	if (SUCCEED != rm_get_report_range(report_time, period, &from, &to))
	{
		*error = zbx_strdup(NULL, "invalid report time or period");
		return NULL;
	}

	job = (zbx_rm_job_t *)zbx_malloc(NULL, sizeof(zbx_rm_job_t));
	memset(job, 0, sizeof(zbx_rm_job_t));

	zbx_vector_ptr_pair_create(&job->params);
	/* move key-value pairs from params to job */
	zbx_vector_ptr_pair_append_array(&job->params, params->values, params->values_num);
	zbx_vector_ptr_pair_clear(params);

	zbx_vector_uint64_create(&job->userids);
	zbx_vector_uint64_append_array(&job->userids, userids, userids_num);

	zbx_snprintf_alloc(&job->url, &url_alloc, &url_offset,
			"%s/zabbix.php?action=dashboard.view&kiosk=1&dashboardid=" ZBX_FS_UI64,
			manager->zabbix_url, dashboardid);
	zbx_snprintf_alloc(&job->url, &url_alloc, &url_offset, "&from=%s", rm_time_to_urlfield(&from));
	zbx_snprintf_alloc(&job->url, &url_alloc, &url_offset, "&to=%s", rm_time_to_urlfield(&to));

	session = rm_get_session(manager, viewer_userid);
	job->cookie = zbx_strdup(NULL, session->cookie);

	return job;
}

/******************************************************************************
 *                                                                            *
 * Function: rm_update_cache                                                  *
 *                                                                            *
 * Purpose: update configuration and report cache                             *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *                                                                            *
 ******************************************************************************/
static void	rm_update_cache(zbx_rm_t *manager)
{
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect("select session_key,url from config");

	if (NULL != (row = DBfetch(result)))
	{
		manager->session_key = zbx_strdup(manager->session_key, row[0]);
		manager->zabbix_url = zbx_strdup(manager->zabbix_url, row[1]);
	}
	else
	{
		manager->session_key = zbx_strdup(manager->session_key, "");
		manager->zabbix_url = zbx_strdup(manager->zabbix_url, "");
	}
	DBfree_result(result);

	ZBX_UNUSED(manager);

	// TODO: sync report cache with database

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

typedef struct
{
	zbx_uint64_t	mediatypeid;
	char		*recipient;
}
zbx_report_dst_t;

static void	zbx_report_dst_free(zbx_report_dst_t *dst)
{
	zbx_free(dst->recipient);
	zbx_free(dst);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_writer_process_job                                            *
 *                                                                            *
 * Purpose: process job by sending it to writer                               *
 *                                                                            *
 * Parameters: writer - [IN] the writer                                       *
 *             job    - [IN] the view to process                              *
 *                                                                            *
 ******************************************************************************/
static int	rm_writer_process_job(zbx_rm_writer_t *writer, zbx_rm_job_t *job)
{
	unsigned char		*data;
	zbx_uint32_t		size;
	int			ret = FAIL, rc;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	mediatypeids;
	zbx_vector_ptr_t	dsts;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_report_dst_t	*dst;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() url:%s", __func__, job->url);

	zbx_vector_uint64_create(&mediatypeids);
	zbx_vector_ptr_create(&dsts);

	size = report_serialize_begin_report(&data, job->url, job->cookie, &job->params);
	if (SUCCEED != zbx_ipc_client_send(writer->client, ZBX_IPC_REPORTER_BEGIN_REPORT, data, size))
		goto out;

	zbx_free(data);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select m.sendto,mt.mediatypeid"
			" from media m,media_type mt "
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "m.userid", job->userids.values, job->userids.values_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" and m.active=%d"
				" and m.mediatypeid=mt.mediatypeid"
				" and mt.type=%d"
				" and mt.status=%d",
			MEDIA_STATUS_ACTIVE, MEDIA_TYPE_EMAIL, MEDIA_TYPE_STATUS_ACTIVE);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		dst = (zbx_report_dst_t *)zbx_malloc(NULL, sizeof(zbx_report_dst_t));
		ZBX_STR2UINT64(dst->mediatypeid, row[1]);
		dst->recipient = zbx_strdup(NULL, row[0]);
		zbx_vector_ptr_append(&dsts, dst);
		zbx_vector_uint64_append(&mediatypeids, dst->mediatypeid);
	}
	DBfree_result(result);

	ret = SUCCEED;

	if (0 != dsts.values_num)
	{
		zbx_vector_str_t	recipients;
		int			index = 0;

		zbx_vector_str_create(&recipients);

		zbx_vector_ptr_sort(&dsts, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		zbx_vector_uint64_sort(&mediatypeids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&mediatypeids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select mediatypeid,type,smtp_server,smtp_helo,smtp_email,exec_path,gsm_modem,username,"
					"passwd,smtp_port,smtp_security,smtp_verify_peer,smtp_verify_host,"
					"smtp_authentication,exec_params,maxsessions,maxattempts,attempt_interval,"
					"content_type,script,timeout"
				" from media_type"
				" where");

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "mediatypeid", mediatypeids.values,
				mediatypeids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)) && SUCCEED == ret)
		{
			DB_MEDIATYPE	mt;

			ZBX_STR2UINT64(mt.mediatypeid, row[0]);

			mt.type = atoi(row[1]);
			mt.smtp_server = zbx_strdup(NULL, row[2]);
			mt.smtp_helo = zbx_strdup(NULL, row[3]);
			mt.smtp_email = zbx_strdup(NULL, row[4]);
			mt.exec_path = zbx_strdup(NULL, row[5]);
			mt.gsm_modem = zbx_strdup(NULL, row[6]);
			mt.username = zbx_strdup(NULL, row[7]);
			mt.passwd = zbx_strdup(NULL, row[8]);
			mt.smtp_port = atoi(row[9]);
			ZBX_STR2UCHAR(mt.smtp_security, row[10]);
			ZBX_STR2UCHAR(mt.smtp_verify_peer, row[11]);
			ZBX_STR2UCHAR(mt.smtp_verify_host, row[12]);
			ZBX_STR2UCHAR(mt.smtp_authentication, row[13]);
			mt.exec_params = zbx_strdup(NULL, row[14]);
			mt.maxsessions = atoi(row[15]);
			mt.maxattempts = atoi(row[16]);
			mt.attempt_interval = zbx_strdup(NULL, row[17]);
			ZBX_STR2UCHAR(mt.content_type, row[18]);
			mt.script = zbx_strdup(NULL, row[19]);
			mt.timeout = zbx_strdup(NULL, row[20]);

			for (; index < dsts.values_num; index++)
			{
				dst = (zbx_report_dst_t *)dsts.values[index];
				if (dst->mediatypeid != mt.mediatypeid)
					break;
				zbx_vector_str_append(&recipients, dst->recipient);
			}

			if (0 != recipients.values_num)
			{
				size = report_serialize_send_report(&data, &mt, &recipients);
				ret = zbx_ipc_client_send(writer->client, ZBX_IPC_REPORTER_SEND_REPORT, data, size);
				zbx_free(data);
			}
			else
				THIS_SHOULD_NEVER_HAPPEN;

			zbx_vector_str_clear(&recipients);
			zbx_db_mediatype_clean(&mt);
		}
		DBfree_result(result);

		zbx_vector_str_destroy(&recipients);
	}

	/* attempt to send finish request even if last sending failed */
	rc = zbx_ipc_client_send(writer->client, ZBX_IPC_REPORTER_END_REPORT, NULL, 0);
	if (SUCCEED == ret)
		ret = rc;
out:
	zbx_free(sql);
	zbx_free(data);
	zbx_vector_ptr_clear_ext(&dsts, (zbx_ptr_free_func_t)zbx_report_dst_free);
	zbx_vector_ptr_destroy(&dsts);
	zbx_vector_uint64_destroy(&mediatypeids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: rm_schedule_jobs                                                 *
 *                                                                            *
 * Purpose: process queue                                                     *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             now     - [IN] current time                                    *
 *                                                                            *
 ******************************************************************************/
static void	rm_schedule_jobs(zbx_rm_t *manager, int now)
{
	zbx_rm_report_t		*report;
	zbx_binary_heap_elem_t	*elem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (SUCCEED != zbx_binary_heap_empty(&manager->report_queue))
	{
		elem = zbx_binary_heap_find_min(&manager->report_queue);
		report = (zbx_rm_report_t *)elem->data;
		if (now < report->nextcheck)
			break;

		zbx_binary_heap_remove_min(&manager->report_queue);

		// TODO: create jobs, reschedule report
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_process_jobs                                                  *
 *                                                                            *
 * Purpose: process queue                                                     *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             now     - [IN] current time                                    *
 *                                                                            *
 ******************************************************************************/
static void	rm_process_jobs(zbx_rm_t *manager)
{
	zbx_rm_writer_t		*writer;
	zbx_rm_job_t		*job;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (SUCCEED != zbx_queue_ptr_empty(&manager->free_writers))
	{
		if (SUCCEED != zbx_list_pop(&manager->job_queue, (void **)&job))
			break;

		writer = zbx_queue_ptr_pop(&manager->free_writers);

		if (SUCCEED != rm_writer_process_job(writer, job))
		{
			rm_job_free(job);
			zbx_queue_ptr_push(&manager->free_writers, writer);
		}
		else
			writer->job = job;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_test_report                                                   *
 *                                                                            *
 * Purpose: test report                                                       *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected writer                            *
 *             message - [IN] the received message                            *
 *             error   - [IN] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the test report job was created successfully       *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	rm_test_report(zbx_rm_t *manager, zbx_ipc_client_t *client, zbx_ipc_message_t *message, char **error)
{
	zbx_uint64_t		dashboardid, userid, viewer_userid;
	zbx_vector_ptr_pair_t	params;
	int			report_time, ret;
	unsigned char		period;
	zbx_rm_job_t		*job;

	zbx_vector_ptr_pair_create(&params);

	report_deserialize_test_report(message->data, &dashboardid, &userid, &viewer_userid, &report_time, &period,
			&params);

	if (NULL != (job = rm_create_job(manager, dashboardid, viewer_userid, report_time, period, &userid, 1, &params,
			error)))
	{
		job->client = client;
		zbx_list_append(&manager->job_queue, job, NULL);
		ret = SUCCEED;
	}
	else
		ret = FAIL;

	report_destroy_params(&params);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: rm_send_test_error_result                                        *
 *                                                                            *
 * Purpose: send error result in reponse to test request                      *
 *                                                                            *
 * Parameters: client - [IN] the connected trapper                            *
 *             error  - [IN] the error message                                *
 *                                                                            *
 ******************************************************************************/
static void	rm_send_test_error_result(zbx_ipc_client_t *client, const char *error)
{
	unsigned char	*data;
	zbx_uint32_t	size;

	size = report_serialize_response(&data, FAIL, error);
	zbx_ipc_client_send(client, ZBX_IPC_REPORTER_TEST_RESULT, data, size);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_test_report                                                   *
 *                                                                            *
 * Purpose: test report                                                       *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the connected writer                            *
 *             message - [IN] the received message                            *
 *                                                                            *
 ******************************************************************************/
static void	rm_process_result(zbx_rm_t *manager, zbx_ipc_client_t *client, zbx_ipc_message_t *message)
{
	int		status;
	char		*error;
	zbx_rm_writer_t	*writer;

	if (NULL == (writer = rm_get_writer(manager, client)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	if (NULL != writer->job->client)
	{
		/* external test request - forward the response to the requester */
		zbx_ipc_client_send(writer->job->client, ZBX_IPC_REPORTER_TEST_RESULT, message->data, message->size);
	}
	else
	{
		report_deserialize_response(message->data, &status, &error);

		// TODO: update report in database
	}

	rm_job_free(writer->job);
	writer->job = NULL;
	zbx_queue_ptr_push(&manager->free_writers, writer);
}

ZBX_THREAD_ENTRY(report_manager_thread, args)
{
#define	ZBX_STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
					/* once in STAT_INTERVAL seconds */
#define ZBX_SYNC_INTERVAL	60	/* report configuration refresh interval */

	char			*error = NULL;
	zbx_ipc_client_t	*client;
	zbx_ipc_message_t	*message;
	double			time_stat, time_idle = 0, time_now, sec, time_sync, time_flush_sessions;
	int			ret, sent_num = 0, failed_num = 0, delay;
	zbx_rm_t		manager;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zbx_setproctitle("%s #%d starting", get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	if (FAIL == rm_init(&manager, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize alert manager: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	/* initialize statistics */
	time_stat = zbx_time();
	time_sync = 0;
	time_flush_sessions = time_stat;

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	while (ZBX_IS_RUNNING())
	{
		time_now = zbx_time();

		if (ZBX_STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [sent %d, failed %d reports, idle " ZBX_FS_DBL " sec during "
					ZBX_FS_DBL " sec]", get_process_type_string(process_type), process_num,
					sent_num, failed_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			sent_num = 0;
			failed_num = 0;
		}

		if (SEC_PER_HOUR < time_now - time_flush_sessions)
		{
			rm_flush_sessions(&manager);
			time_flush_sessions = time_now;
		}

		if (time_now - time_sync >= ZBX_SYNC_INTERVAL)
		{
			rm_update_cache(&manager);
			time_sync = time_now;
		}

		rm_schedule_jobs(&manager, time(NULL));
		rm_process_jobs(&manager);

		sec = zbx_time();
		delay = (sec - time_now > 0.5 ? 0 : 1);
		time_now = sec;

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		ret = zbx_ipc_service_recv(&manager.ipc, delay, &client, &message);
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

		sec = zbx_time();
		zbx_update_env(sec);

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += sec - time_now;

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_REPORTER_REGISTER:
					rm_register_writer(&manager, client, message);
					break;
				case ZBX_IPC_REPORTER_TEST:
					if (FAIL == rm_test_report(&manager, client, message, &error))
					{
						rm_send_test_error_result(client, error);
						zbx_free(error);
					}
					break;
				case ZBX_IPC_REPORTER_RESULT:
					rm_process_result(&manager, client, message);
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);

	zbx_ipc_service_close(&manager.ipc);
	rm_destroy(&manager);
}
