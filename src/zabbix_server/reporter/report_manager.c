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

#include "report_manager.h"
#include "report_protocol.h"


extern unsigned char	process_type, program_type;
extern int		server_num, process_num, CONFIG_REPORTWRITER_FORKS;

typedef struct
{
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

	zbx_binary_heap_t	queue;
}
zbx_rm_t;

typedef struct
{
	zbx_uint64_t		viewid;

	char			*url;
	char			*cookie;
	int			nextcheck;
	zbx_vector_str_t	emails;
	zbx_vector_ptr_pair_t	params;

	zbx_ipc_client_t	*client;
}
zbx_rm_view_t;

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

	zbx_rm_view_t		*view;
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
 * Purpose: frees report writer                                               *
 *                                                                            *
 ******************************************************************************/
static void	rm_writer_free(zbx_rm_writer_t *writer)
{
	zbx_ipc_client_close(writer->client);
	zbx_free(writer);
}

static int	rm_views_compare_nextcheck(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	return ((zbx_rm_view_t *)e1->data)->nextcheck - ((zbx_rm_view_t *)e2->data)->nextcheck;
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
	zbx_binary_heap_create(&manager->queue, rm_views_compare_nextcheck, ZBX_BINARY_HEAP_OPTION_DIRECT);

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

	zbx_hashset_iter_reset(&manager->sessions, &iter);
	while (NULL != (session = (zbx_rm_session_t *)zbx_hashset_iter_next(&iter)))
		zbx_free(session->sid);
	zbx_hashset_destroy(&manager->sessions);

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
 * Parameters: timestamp - [IN] the timestamp in seconds since Epoch          *
 *                                                                            *
 * Return value: formatted time to be used in URL query fields                *
 *                                                                            *
 ******************************************************************************/
static char	*rm_time_to_urlfield(time_t timestamp)
{
	static char	buf[26];
	struct tm	*tm;

	tm = localtime(&timestamp);
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
 * Parameters: manager - [IN] the manager                                     *
 *             client  - [IN] the session id                                  *
 *                                                                            *
 * Return value: zbx_session coookie                                          *
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
 * Function: rm_create_view                                                   *
 *                                                                            *
 * Purpose: create new view to be processed by report writers                 *
 *                                                                            *
 * Parameters: manager       - [IN] the manager                               *
 *             dashboardid   - [IN] the dashboard to view                     *
 *             viewer_userid - [IN] the user viewing the dashboard            *
 *             report_time   - [IN] the dashboard time                        *
 *             period        - [IN] the dashboard period                      *
 *             params        - [IN] the viewing and processing parameters     *
 *                                                                            *
 ******************************************************************************/
static zbx_rm_view_t	*rm_create_view(zbx_rm_t *manager, zbx_uint64_t dashboardid, zbx_uint64_t viewer_userid,
		int report_time, int period, zbx_vector_ptr_pair_t *params)
{
	static zbx_uint64_t	last_viewid;
	zbx_rm_view_t		*view;
	size_t			url_alloc = 0, url_offset = 0;
	zbx_rm_session_t	*session;
	char			*value = NULL;
	struct zbx_json		j;

	view = (zbx_rm_view_t *)zbx_malloc(NULL, sizeof(zbx_rm_view_t));
	memset(view, 0, sizeof(zbx_rm_view_t));
	view->viewid = ++last_viewid;
	zbx_vector_str_create(&view->emails);
	zbx_vector_ptr_pair_create(&view->params);

	zbx_snprintf_alloc(&view->url, &url_alloc, &url_offset,
			"%s/zabbix.php?action=dashboard.view&kiosk=1&dashboardid=" ZBX_FS_UI64,
			manager->zabbix_url, dashboardid);
	zbx_snprintf_alloc(&view->url, &url_alloc, &url_offset, "&from=%s", rm_time_to_urlfield(report_time - period));
	zbx_snprintf_alloc(&view->url, &url_alloc, &url_offset, "&to=%s", rm_time_to_urlfield(report_time));

	zbx_vector_ptr_pair_append_array(&view->params, params->values, params->values_num);
	zbx_vector_ptr_pair_clear(params);

	session = rm_get_session(manager, viewer_userid);
	view->cookie = zbx_strdup(NULL, session->cookie);

	return view;
}

/******************************************************************************
 *                                                                            *
 * Function: rm_view_free                                                     *
 *                                                                            *
 * Purpose: frees view and its resources                                      *
 *                                                                            *
 ******************************************************************************/
static void	rm_view_free(zbx_rm_view_t *view)
{
	zbx_vector_str_clear_ext(&view->emails, zbx_str_free);
	zbx_vector_str_destroy(&view->emails);

	report_destroy_params(&view->params);

	zbx_free(view->url);
	zbx_free(view->cookie);
	zbx_free(view);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_read_reports                                                  *
 *                                                                            *
 * Purpose: update configuration and report cache                             *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *                                                                            *
 ******************************************************************************/
static void	rm_read_reports(zbx_rm_t *manager)
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

/******************************************************************************
 *                                                                            *
 * Function: rm_queue_view                                                    *
 *                                                                            *
 * Purpose: queue view for processing                                         *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             view    - [IN] the view to queue                               *
 *                                                                            *
 ******************************************************************************/
static void	rm_queue_view(zbx_rm_t *manager, zbx_rm_view_t *view)
{
	zbx_binary_heap_elem_t	elem = {view->viewid, view};

	zbx_binary_heap_insert(&manager->queue, &elem);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_writer_process_view                                           *
 *                                                                            *
 * Purpose: process view by sending it to writer                              *
 *                                                                            *
 * Parameters: writer - [IN] the writer                                       *
 *             view   - [IN] the view to process                              *
 *                                                                            *
 ******************************************************************************/
static void	rm_writer_process_view(zbx_rm_writer_t *writer, zbx_rm_view_t *view)
{
	unsigned char	*data;
	zbx_uint32_t	size;

	writer->view = view;

	size = report_serialize_send_request(&data, view->url, view->cookie, &view->emails, &view->params);
	zbx_ipc_client_send(writer->client, ZBX_IPC_REPORTER_SEND_REPORT, data, size);

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_process_queue                                                 *
 *                                                                            *
 * Purpose: process queue                                                     *
 *                                                                            *
 * Parameters: manager - [IN] the manager                                     *
 *             now     - [IN] current time                                    *
 *                                                                            *
 ******************************************************************************/
static void	rm_process_queue(zbx_rm_t *manager, int now)
{
	zbx_rm_writer_t		*writer;
	zbx_rm_view_t		*view;
	zbx_binary_heap_elem_t	*elem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (SUCCEED != zbx_binary_heap_empty(&manager->queue))
	{
		elem = zbx_binary_heap_find_min(&manager->queue);
		view = (zbx_rm_view_t *)elem->data;
		if (now < view->nextcheck)
			break;

		if (NULL == (writer = zbx_queue_ptr_pop(&manager->free_writers)))
			break;

		zbx_binary_heap_remove_min(&manager->queue);
		rm_writer_process_view(writer, view);
	}
	ZBX_UNUSED(manager);
	ZBX_UNUSED(now);

	// TODO: process reports

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: rm_get_user_emails                                               *
 *                                                                            *
 * Purpose: get emails of the specified users from database                   *
 *                                                                            *
 * Parameters: userids     - [IN] the user identifiers                        *
 *             userids_num - [IN] the number of user identifiers              *
 *             emails      - [OUT] the emails                                 *
 *                                                                            *
 ******************************************************************************/
static void	rm_get_user_emails(zbx_uint64_t *userids, int userids_num, zbx_vector_str_t *emails)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select m.sendto from media m,media_type mt where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "m.userid", userids, userids_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" and m.active=%d"
				" and m.mediatypeid=mt.mediatypeid"
				" and mt.type=%d"
				" and mt.status=%d",
			MEDIA_STATUS_ACTIVE, MEDIA_TYPE_EMAIL, MEDIA_TYPE_STATUS_ACTIVE);

	result = DBselect("%s", sql);
	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		char	*ptr;

		for (ptr = row[0];; ptr++)
		{
			char	*delim, *email = NULL;
			size_t	len, email_alloc = 0, email_offset = 0;

			if (NULL != (delim = strchr(ptr, '\n')))
				len = delim - ptr;
			else
				len = strlen(ptr);

			zbx_strncpy_alloc(&email, &email_alloc, &email_offset, ptr, len);
			if (FAIL == zbx_vector_str_search(emails, email, ZBX_DEFAULT_STR_COMPARE_FUNC))
				zbx_vector_str_append(emails, email);
			else
				zbx_free(email);

			ptr += len;
			if ('\n' != *ptr)
				break;
		}
	}
	DBfree_result(result);
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
static void	rm_test_report(zbx_rm_t *manager, zbx_ipc_client_t *client, zbx_ipc_message_t *message)
{
	zbx_uint64_t		dashboardid, userid, viewer_userid;
	zbx_vector_ptr_pair_t	params;
	int			report_time, period;
	unsigned char		*data = NULL;
	zbx_rm_view_t		*view;

	zbx_vector_ptr_pair_create(&params);

	report_deserialize_test_request(message->data, &dashboardid, &userid, &viewer_userid, &report_time, &period,
			&params);

	view = rm_create_view(manager, dashboardid, viewer_userid, report_time, period, &params);
	view->client = client;
	rm_get_user_emails(&userid, 1, &view->emails);
	rm_queue_view(manager, view);

	zbx_free(data);
	report_destroy_params(&params);
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

	if (NULL != writer->view->client)
	{
		/* external test request - forward the response to the requester */
		zbx_ipc_client_send(writer->view->client, ZBX_IPC_REPORTER_TEST_REPORT_RESULT, message->data,
				message->size);
	}
	else
	{
		report_deserialize_response(message->data, &status, &error);

		// TODO: update report in database
	}

	rm_view_free(writer->view);
	writer->view = NULL;
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
			rm_read_reports(&manager);
			time_sync = time_now;
		}

		rm_process_queue(&manager, time(NULL));

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
				case ZBX_IPC_REPORTER_TEST_REPORT:
					rm_test_report(&manager, client, message);
					break;
				case ZBX_IPC_REPORTER_SEND_REPORT_RESULT:
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
