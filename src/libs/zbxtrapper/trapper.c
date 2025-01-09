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

#include "zbxtrapper.h"
#include "zbxdbwrap.h"

#include "active.h"
#include "trapper_expressions_evaluate.h"
#include "trapper_item_test.h"
#include "nodecommand.h"
#include "version.h"

#include "zbxtimekeeper.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxjson.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxlog.h"
#include "zbxself.h"
#include "zbxnix.h"
#include "zbxcommshigh.h"
#include "zbxpoller.h"
#include "zbxavailability.h"
#include "zbx_availability_constants.h"
#include "zbxxml.h"
#include "zbxcrypto.h"
#include "zbxtime.h"
#include "zbxstats.h"
#include "zbx_host_constants.h"
#include "zbx_trigger_constants.h"
#include "zbx_item_constants.h"
#include "zbxscripts.h"
#include "zbxcomms.h"
#include "zbxdbhigh.h"
#include "zbxthreads.h"
#include "zbxvault.h"
#include "zbxautoreg.h"

#ifdef HAVE_NETSNMP
#	include "zbxrtc.h"
#	include "zbx_rtc_constants.h"
#	include "zbxipcservice.h"
#endif

#define ZBX_MAX_SECTION_ENTRIES		4
#define ZBX_MAX_ENTRY_ATTRIBUTES	3

static zbx_get_program_type_f	zbx_get_program_type_cb = NULL;

zbx_get_program_type_f	trapper_get_program_type(void)
{
	return zbx_get_program_type_cb;
}

typedef struct
{
	zbx_counter_value_t	online;
	zbx_counter_value_t	offline;
}
zbx_user_stats_t;

typedef union
{
	zbx_counter_value_t	counter;		/* single global counter */
	zbx_vector_proxy_counter_ptr_t	counters;	/* array of per proxy counters */
}
zbx_entry_info_t;

typedef struct
{
	const char	*name;
	zbx_uint64_t	value;
}
zbx_entry_attribute_t;

typedef enum
{
	ZBX_COUNTER_TYPE_UI64,
	ZBX_COUNTER_TYPE_DBL
}
zbx_counter_type_t;

typedef struct
{
	zbx_entry_info_t	*info;
	zbx_counter_type_t	counter_type;
	zbx_entry_attribute_t	attributes[ZBX_MAX_ENTRY_ATTRIBUTES];
}
zbx_section_entry_t;

typedef enum
{
	ZBX_SECTION_ENTRY_THE_ONLY,
	ZBX_SECTION_ENTRY_PER_PROXY,
	ZBX_SECTION_ENTRY_SERVER_STATS
}
zbx_entry_type_t;

typedef struct
{
	const char		*name;
	zbx_entry_type_t	entry_type;
	int			*res;
	zbx_section_entry_t	entries[ZBX_MAX_SECTION_ENTRIES];
}
zbx_status_section_t;

/******************************************************************************
 *                                                                            *
 * Purpose: processes received values from active agents                      *
 *                                                                            *
 ******************************************************************************/
static void	recv_agenthistory(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts,
		int config_timeout)
{
	char	*info = NULL, *ext = NULL;
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == zbx_vps_monitor_capped())
	{
		ext = zbx_strdup(NULL, "{\"" ZBX_PROTO_TAG_HISTORY_UPLOAD "\":\""
				ZBX_PROTO_VALUE_HISTORY_UPLOAD_DISABLED "\"}");
		info = zbx_strdup(info, "data collection is paused");
		ret = FAIL;
	}
	else if (SUCCEED == (ret = zbx_process_agent_history_data(sock, jp, ts, &info)))
	{
		if (!ZBX_IS_RUNNING())
		{
			info = zbx_strdup(info, "Zabbix server shutdown in progress");
			zabbix_log(LOG_LEVEL_WARNING, "cannot receive agent history data from \"%s\": %s", sock->peer,
					info);
			ret = FAIL;
		}
	}
	else if (SUCCEED_PARTIAL == ret)
	{
		ext = info;
		info = NULL;
		ret = FAIL;
	}
	else
		zabbix_log(LOG_LEVEL_WARNING, "received invalid agent history data from \"%s\": %s", sock->peer, info);

	zbx_process_command_results(jp);

	zbx_send_response_json(sock, ret, info, NULL, sock->protocol, config_timeout, ext);

	zbx_free(info);
	zbx_free(ext);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes received values from senders                            *
 *                                                                            *
 ******************************************************************************/
static void	recv_senderhistory(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts,
		int config_timeout)
{
	char	*info = NULL, *ext = NULL;
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == (ret = zbx_process_sender_history_data(sock, jp, ts, &info)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot process sender data from \"%s\": %s", sock->peer, info);
	}
	else if (!ZBX_IS_RUNNING())
	{
		info = zbx_strdup(info, "Zabbix server shutdown in progress");
		zabbix_log(LOG_LEVEL_WARNING, "cannot process sender data from \"%s\": %s", sock->peer, info);
		ret = FAIL;
	}
	if (SUCCEED_PARTIAL == ret)
	{
		ext = info;
		info = NULL;
		ret = FAIL;
	}

	zbx_send_response_json(sock, ret, info, NULL, sock->protocol, config_timeout, ext);

	zbx_free(info);
	zbx_free(ext);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes heartbeat sent by proxy servers                         *
 *                                                                            *
 ******************************************************************************/
static void	recv_proxy_heartbeat(zbx_socket_t *sock, struct zbx_json_parse *jp)
{
	char		*error = NULL;
	zbx_dc_proxy_t	proxy;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_get_active_proxy_from_request(jp, &proxy, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse heartbeat from active proxy at \"%s\": %s",
				sock->peer, error);
		goto out;
	}

	if (SUCCEED != zbx_proxy_check_permissions(&proxy, sock, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot accept connection from proxy \"%s\" at \"%s\", allowed address:"
				" \"%s\": %s", proxy.name, sock->peer, proxy.allowed_addresses, error);
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "ignoring heartbeat from active proxy \"%s\" at \"%s\": proxy heartbeats"
			" are deprecated", proxy.name, sock->peer);
out:
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

#define ZBX_GET_QUEUE_UNKNOWN		-1
#define ZBX_GET_QUEUE_OVERVIEW		0
#define ZBX_GET_QUEUE_PROXY		1
#define ZBX_GET_QUEUE_DETAILS		2

/* queue stats split by delay times */
typedef struct
{
	zbx_uint64_t	id;
	int		delay5;
	int		delay10;
	int		delay30;
	int		delay60;
	int		delay300;
	int		delay600;
}
zbx_queue_stats_t;

/******************************************************************************
 *                                                                            *
 * Purpose: updates queue stats with new item delay                           *
 *                                                                            *
 * Parameters: stats - [IN] queue stats                                       *
 *             delay - [IN] delay time of delayed item                        *
 *                                                                            *
 ******************************************************************************/
static void	queue_stats_update(zbx_queue_stats_t *stats, int delay)
{
	if (10 >= delay)
		stats->delay5++;
	else if (30 >= delay)
		stats->delay10++;
	else if (60 >= delay)
		stats->delay30++;
	else if (300 >= delay)
		stats->delay60++;
	else if (600 >= delay)
		stats->delay300++;
	else
		stats->delay600++;
}

/******************************************************************************
 *                                                                            *
 * Purpose: exports queue stats to JSON format                                *
 *                                                                            *
 * Parameters: queue_stats - [IN] hashset containing item stats               *
 *             id_name     - [IN] name of stats id field                      *
 *             json        - [OUT] output JSON                                *
 *                                                                            *
 ******************************************************************************/
static void	queue_stats_export(zbx_hashset_t *queue_stats, const char *id_name, struct zbx_json *json)
{
	zbx_hashset_iter_t	iter;
	zbx_queue_stats_t	*stats;

	zbx_json_addarray(json, ZBX_PROTO_TAG_DATA);

	zbx_hashset_iter_reset(queue_stats, &iter);

	while (NULL != (stats = (zbx_queue_stats_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_json_addobject(json, NULL);
		zbx_json_adduint64(json, id_name, stats->id);
		zbx_json_addint64(json, "delay5", stats->delay5);
		zbx_json_addint64(json, "delay10", stats->delay10);
		zbx_json_addint64(json, "delay30", stats->delay30);
		zbx_json_addint64(json, "delay60", stats->delay60);
		zbx_json_addint64(json, "delay300", stats->delay300);
		zbx_json_addint64(json, "delay600", stats->delay600);
		zbx_json_close(json);
	}

	zbx_json_close(json);
}

/* queue item comparison function used to sort queue by nextcheck */
static int	queue_compare_by_nextcheck_asc(zbx_queue_item_t **d1, zbx_queue_item_t **d2)
{
	zbx_queue_item_t	*i1 = *d1, *i2 = *d2;

	return i1->nextcheck - i2->nextcheck;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes queue request                                           *
 *                                                                            *
 * Parameters:  sock           - [IN] request socket                          *
 *              jp             - [IN] request data                            *
 *              config_timeout - [IN]                                         *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - error occurred                                       *
 *                                                                            *
 ******************************************************************************/
static int	recv_getqueue(zbx_socket_t *sock, struct zbx_json_parse *jp, int config_timeout)
{
	int				ret = FAIL, request_type = ZBX_GET_QUEUE_UNKNOWN, now, limit;
	char				type[MAX_STRING_LEN], limit_str[MAX_STRING_LEN];
	zbx_user_t			user;
	zbx_vector_queue_item_ptr_t	queue;
	struct zbx_json			json;
	zbx_hashset_t			queue_stats;
	zbx_queue_stats_t		*stats;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_user_init(&user);

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL) || USER_TYPE_SUPER_ADMIN > user.type)
	{
		zbx_send_response(sock, ret, "Permission denied.", config_timeout);
		goto out;
	}

	if (FAIL != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_TYPE, type, sizeof(type), NULL))
	{
		if (0 == strcmp(type, ZBX_PROTO_VALUE_GET_QUEUE_OVERVIEW))
		{
			request_type = ZBX_GET_QUEUE_OVERVIEW;
		}
		else if (0 == strcmp(type, ZBX_PROTO_VALUE_GET_QUEUE_PROXY))
		{
			request_type = ZBX_GET_QUEUE_PROXY;
		}
		else if (0 == strcmp(type, ZBX_PROTO_VALUE_GET_QUEUE_DETAILS))
		{
			request_type = ZBX_GET_QUEUE_DETAILS;

			if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_LIMIT, limit_str, sizeof(limit_str),
					NULL) || FAIL == zbx_is_uint31(limit_str, &limit))
			{
				zbx_send_response(sock, ret, "Unsupported limit value.", config_timeout);
				goto out;
			}
		}
	}

	if (ZBX_GET_QUEUE_UNKNOWN == request_type)
	{
		zbx_send_response(sock, ret, "Unsupported request type.", config_timeout);
		goto out;
	}

	now = (int)time(NULL);
	zbx_vector_queue_item_ptr_create(&queue);
	zbx_dc_get_item_queue(&queue, ZBX_QUEUE_FROM_DEFAULT, ZBX_QUEUE_TO_INFINITY);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	switch (request_type)
	{
		case ZBX_GET_QUEUE_OVERVIEW:
			zbx_hashset_create(&queue_stats, 32, ZBX_DEFAULT_UINT64_HASH_FUNC,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			/* gather queue stats by item type */
			for (int i = 0; i < queue.values_num; i++)
			{
				zbx_queue_item_t	*item = (zbx_queue_item_t *)queue.values[i];
				zbx_uint64_t		id = (zbx_uint64_t)item->type;

				if (NULL == (stats = (zbx_queue_stats_t *)zbx_hashset_search(&queue_stats, &id)))
				{
					zbx_queue_stats_t	data = {.id = id};

					stats = (zbx_queue_stats_t *)zbx_hashset_insert(&queue_stats, &data,
							sizeof(data));
				}
				queue_stats_update(stats, now - item->nextcheck);
			}

			zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS,
					ZBX_JSON_TYPE_STRING);
			queue_stats_export(&queue_stats, "itemtype", &json);
			zbx_hashset_destroy(&queue_stats);

			break;
		case ZBX_GET_QUEUE_PROXY:
			zbx_hashset_create(&queue_stats, 32, ZBX_DEFAULT_UINT64_HASH_FUNC,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			/* gather queue stats by proxy hostid */
			for (int i = 0; i < queue.values_num; i++)
			{
				zbx_queue_item_t	*item = (zbx_queue_item_t *)queue.values[i];
				zbx_uint64_t		id = item->proxyid;

				if (NULL == (stats = (zbx_queue_stats_t *)zbx_hashset_search(&queue_stats, &id)))
				{
					zbx_queue_stats_t	data = {.id = id};

					stats = (zbx_queue_stats_t *)zbx_hashset_insert(&queue_stats, &data,
							sizeof(data));
				}
				queue_stats_update(stats, now - item->nextcheck);
			}

			zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS,
					ZBX_JSON_TYPE_STRING);
			queue_stats_export(&queue_stats, "proxyid", &json);
			zbx_hashset_destroy(&queue_stats);

			break;
		case ZBX_GET_QUEUE_DETAILS:
			zbx_vector_queue_item_ptr_sort(&queue, (zbx_compare_func_t)queue_compare_by_nextcheck_asc);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS,
					ZBX_JSON_TYPE_STRING);
			zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

			for (int i = 0; i < queue.values_num && i < limit; i++)
			{
				zbx_queue_item_t	*item = queue.values[i];

				zbx_json_addobject(&json, NULL);
				zbx_json_adduint64(&json, "itemid", item->itemid);
				zbx_json_addint64(&json, "nextcheck", item->nextcheck);
				zbx_json_close(&json);
			}

			zbx_json_close(&json);
			zbx_json_addint64(&json, "total", queue.values_num);

			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() json.buffer:'%s'", __func__, json.buffer);

	(void)zbx_tcp_send_to(sock, json.buffer, config_timeout);

	zbx_dc_free_item_queue(&queue);
	zbx_vector_queue_item_ptr_destroy(&queue);

	zbx_json_free(&json);

	ret = SUCCEED;
out:
	zbx_user_free(&user);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	DBget_template_count(zbx_uint64_t *count)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = FAIL;

	if (NULL == (result = zbx_db_select("select count(*) from hosts where status=%d", HOST_STATUS_TEMPLATE)))
		goto out;

	if (NULL == (row = zbx_db_fetch(result)) || SUCCEED != zbx_is_uint64(row[0], count))
		goto out;

	ret = SUCCEED;
out:
	zbx_db_free_result(result);

	return ret;
}

static int	DBget_user_count(zbx_uint64_t *count_online, zbx_uint64_t *count_offline)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_uint64_t	users_offline, users_online = 0;
	int		now, ret = FAIL;

	if (NULL == (result = zbx_db_select("select count(*) from users")))
		goto out;

	if (NULL == (row = zbx_db_fetch(result)) || SUCCEED != zbx_is_uint64(row[0], &users_offline))
		goto out;

	zbx_db_free_result(result);
	now = (int)time(NULL);

	if (NULL == (result = zbx_db_select("select max(lastaccess) from sessions where status=%d group by userid"
			",status", ZBX_SESSION_ACTIVE)))
	{
		goto out;
	}

	while (NULL != (row = zbx_db_fetch(result)))
	{
#define ZBX_USER_ONLINE_TIME	600
		if (atoi(row[0]) + ZBX_USER_ONLINE_TIME < now)
			continue;
#undef ZBX_USER_ONLINE_TIME
		users_online++;

		if (0 == users_offline)	/* new user can be created and log in between two selects */
			continue;

		users_offline--;
	}

	*count_online = users_online;
	*count_offline = users_offline;
	ret = SUCCEED;
out:
	zbx_db_free_result(result);

	return ret;
}

/* auxiliary variables for status_stats_export() */

static zbx_entry_info_t	templates, hosts_monitored, hosts_not_monitored, items_active_normal, items_active_notsupported,
			items_disabled, triggers_enabled_ok, triggers_enabled_problem, triggers_disabled, users_online,
			users_offline, required_performance;
static int		templates_res, users_res;

static void	zbx_status_counters_init(void)
{
	zbx_vector_proxy_counter_ptr_create(&hosts_monitored.counters);
	zbx_vector_proxy_counter_ptr_create(&hosts_not_monitored.counters);
	zbx_vector_proxy_counter_ptr_create(&items_active_normal.counters);
	zbx_vector_proxy_counter_ptr_create(&items_active_notsupported.counters);
	zbx_vector_proxy_counter_ptr_create(&items_disabled.counters);
	zbx_vector_proxy_counter_ptr_create(&required_performance.counters);
}

static void	zbx_status_counters_free(void)
{
	zbx_vector_proxy_counter_ptr_clear_ext(&hosts_monitored.counters, zbx_proxy_counter_ptr_free);
	zbx_vector_proxy_counter_ptr_clear_ext(&hosts_not_monitored.counters, zbx_proxy_counter_ptr_free);
	zbx_vector_proxy_counter_ptr_clear_ext(&items_active_normal.counters, zbx_proxy_counter_ptr_free);
	zbx_vector_proxy_counter_ptr_clear_ext(&items_active_notsupported.counters, zbx_proxy_counter_ptr_free);
	zbx_vector_proxy_counter_ptr_clear_ext(&items_disabled.counters, zbx_proxy_counter_ptr_free);
	zbx_vector_proxy_counter_ptr_clear_ext(&required_performance.counters, zbx_proxy_counter_ptr_free);

	zbx_vector_proxy_counter_ptr_destroy(&hosts_monitored.counters);
	zbx_vector_proxy_counter_ptr_destroy(&hosts_not_monitored.counters);
	zbx_vector_proxy_counter_ptr_destroy(&items_active_normal.counters);
	zbx_vector_proxy_counter_ptr_destroy(&items_active_notsupported.counters);
	zbx_vector_proxy_counter_ptr_destroy(&items_disabled.counters);
	zbx_vector_proxy_counter_ptr_destroy(&required_performance.counters);
}

const zbx_status_section_t	status_sections[] = {
/*	{SECTION NAME,			NUMBER OF SECTION ENTRIES	SECTION READINESS,                         */
/*		{                                                                                                  */
/*			{ENTRY INFORMATION,		COUNTER TYPE,                                              */
/*				{                                                                                  */
/*					{ATTR. NAME,	ATTRIBUTE VALUE},                                          */
/*					... (up to ZBX_MAX_ENTRY_ATTRIBUTES)                                       */
/*				}                                                                                  */
/*			},                                                                                         */
/*			... (up to ZBX_MAX_SECTION_ENTRIES)                                                        */
/*		}                                                                                                  */
/*	},                                                                                                         */
/*	...                                                                                                        */
	{"template stats",		ZBX_SECTION_ENTRY_THE_ONLY,	&templates_res,
		{
			{&templates,			ZBX_COUNTER_TYPE_UI64,
				{
					{0}
				}
			},
			{0}
		}
	},
	{"host stats",			ZBX_SECTION_ENTRY_PER_PROXY,	NULL,
		{
			{&hosts_monitored,		ZBX_COUNTER_TYPE_UI64,
				{
					{"status",	HOST_STATUS_MONITORED},
					{0}
				}
			},
			{&hosts_not_monitored,		ZBX_COUNTER_TYPE_UI64,
				{
					{"status",	HOST_STATUS_NOT_MONITORED},
					{0}
				}
			},
			{0}
		}
	},
	{"item stats",			ZBX_SECTION_ENTRY_PER_PROXY,	NULL,
		{
			{&items_active_normal,		ZBX_COUNTER_TYPE_UI64,
				{
					{"status",	ITEM_STATUS_ACTIVE},
					{"state",	ITEM_STATE_NORMAL},
					{0}
				}
			},
			{&items_active_notsupported,	ZBX_COUNTER_TYPE_UI64,
				{
					{"status",	ITEM_STATUS_ACTIVE},
					{"state",	ITEM_STATE_NOTSUPPORTED},
					{0}
				}
			},
			{&items_disabled,		ZBX_COUNTER_TYPE_UI64,
				{
					{"status",	ITEM_STATUS_DISABLED},
					{0}
				}
			},
			{0}
		}
	},
	{"trigger stats",		ZBX_SECTION_ENTRY_THE_ONLY,	NULL,
		{
			{&triggers_enabled_ok,		ZBX_COUNTER_TYPE_UI64,
				{
					{"status",	TRIGGER_STATUS_ENABLED},
					{"value",	TRIGGER_VALUE_OK},
					{0}
				}
			},
			{&triggers_enabled_problem,	ZBX_COUNTER_TYPE_UI64,
				{
					{"status",	TRIGGER_STATUS_ENABLED},
					{"value",	TRIGGER_VALUE_PROBLEM},
					{0}
				}
			},
			{&triggers_disabled,		ZBX_COUNTER_TYPE_UI64,
				{
					{"status",	TRIGGER_STATUS_DISABLED},
					{0}
				}
			},
			{0}
		}
	},
	{"user stats",			ZBX_SECTION_ENTRY_THE_ONLY,	&users_res,
		{
			{&users_online,			ZBX_COUNTER_TYPE_UI64,
				{
					{"status",	ZBX_SESSION_ACTIVE},
					{0}
				}
			},
			{&users_offline,		ZBX_COUNTER_TYPE_UI64,
				{
					{"status",	ZBX_SESSION_PASSIVE},
					{0}
				}
			},
			{0}
		}
	},
	{"required performance",	ZBX_SECTION_ENTRY_PER_PROXY,	NULL,
		{
			{&required_performance,		ZBX_COUNTER_TYPE_DBL,
				{
					{0}
				}
			},
			{0}
		}
	},
	{"server stats",		ZBX_SECTION_ENTRY_SERVER_STATS,	NULL,
		{
			{0}
		}
	},
	{0}
};

static void	server_stats_entry_export(struct zbx_json *json, const zbx_status_section_t *section)
{
	zbx_json_addobject(json, section->name);
	zbx_json_addstring(json, "version", ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);
	zbx_json_close(json);
}

static void	status_entry_export(struct zbx_json *json, const zbx_section_entry_t *entry,
		zbx_counter_value_t counter_value, const zbx_uint64_t *proxyid)
{
	const zbx_entry_attribute_t	*attribute;
	char				*tmp = NULL;

	zbx_json_addobject(json, NULL);

	if (NULL != entry->attributes[0].name || NULL != proxyid)
	{
		zbx_json_addobject(json, "attributes");

		if (NULL != proxyid)
			zbx_json_adduint64(json, "proxyid", *proxyid);

		for (attribute = entry->attributes; NULL != attribute->name; attribute++)
			zbx_json_adduint64(json, attribute->name, attribute->value);

		zbx_json_close(json);
	}

	switch (entry->counter_type)
	{
		case ZBX_COUNTER_TYPE_UI64:
			zbx_json_adduint64(json, "count", counter_value.ui64);
			break;
		case ZBX_COUNTER_TYPE_DBL:
			tmp = zbx_dsprintf(tmp, ZBX_FS_DBL64, counter_value.dbl);
			zbx_json_addstring(json, "count", tmp, ZBX_JSON_TYPE_STRING);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	zbx_json_close(json);

	zbx_free(tmp);
}

static void	status_stats_export(struct zbx_json *json)
{
	const zbx_status_section_t	*section;
	const zbx_section_entry_t	*entry;
	int				i;

	zbx_status_counters_init();

	/* get status information */

	templates_res = DBget_template_count(&templates.counter.ui64);
	users_res = DBget_user_count(&users_online.counter.ui64, &users_offline.counter.ui64);
	zbx_dc_get_status(&hosts_monitored.counters, &hosts_not_monitored.counters, &items_active_normal.counters,
			&items_active_notsupported.counters, &items_disabled.counters,
			&triggers_enabled_ok.counter.ui64, &triggers_enabled_problem.counter.ui64,
			&triggers_disabled.counter.ui64, &required_performance.counters);

	/* add status information to JSON */
	for (section = status_sections; NULL != section->name; section++)
	{
		if (NULL != section->res && SUCCEED != *section->res)	/* skip section we have no information for */
			continue;

		if (ZBX_SECTION_ENTRY_SERVER_STATS == section->entry_type)
		{
			server_stats_entry_export(json, section);
			continue;
		}

		zbx_json_addarray(json, section->name);

		for (entry = section->entries; NULL != entry->info; entry++)
		{
			switch (section->entry_type)
			{
				case ZBX_SECTION_ENTRY_THE_ONLY:
					status_entry_export(json, entry, entry->info->counter, NULL);
					break;
				case ZBX_SECTION_ENTRY_PER_PROXY:
					for (i = 0; i < entry->info->counters.values_num; i++)
					{
						const zbx_proxy_counter_t	*proxy_counter;

						proxy_counter = (zbx_proxy_counter_t *)entry->info->counters.values[i];
						status_entry_export(json, entry, proxy_counter->counter_value,
								&proxy_counter->proxyid);
					}
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
			}
		}

		zbx_json_close(json);
	}

	zbx_status_counters_free();
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes status request                                          *
 *                                                                            *
 * Parameters:  sock           - [IN] request socket                          *
 *              jp             - [IN] request data                            *
 *              config_timeout - [IN]                                         *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - error occurred                                       *
 *                                                                            *
 ******************************************************************************/
static int	recv_getstatus(zbx_socket_t *sock, struct zbx_json_parse *jp, int config_timeout)
{
#define ZBX_GET_STATUS_UNKNOWN	-1
#define ZBX_GET_STATUS_PING	0
#define ZBX_GET_STATUS_FULL	1

	zbx_user_t	user;
	int		ret = FAIL, request_type = ZBX_GET_STATUS_UNKNOWN;
	char		type[MAX_STRING_LEN];
	struct zbx_json	json;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_user_init(&user);

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL))
	{
		zbx_send_response(sock, ret, "Permission denied.", config_timeout);
		goto out;
	}

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_TYPE, type, sizeof(type), NULL))
	{
		if (0 == strcmp(type, ZBX_PROTO_VALUE_GET_STATUS_PING))
		{
			request_type = ZBX_GET_STATUS_PING;
		}
		else if (0 == strcmp(type, ZBX_PROTO_VALUE_GET_STATUS_FULL))
		{
			request_type = ZBX_GET_STATUS_FULL;
		}
	}

	if (ZBX_GET_STATUS_UNKNOWN == request_type)
	{
		zbx_send_response(sock, ret, "Unsupported request type.", config_timeout);
		goto out;
	}

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	switch (request_type)
	{
		case ZBX_GET_STATUS_PING:
			zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS,
					ZBX_JSON_TYPE_STRING);
			zbx_json_addobject(&json, ZBX_PROTO_TAG_DATA);
			zbx_json_close(&json);
			break;
		case ZBX_GET_STATUS_FULL:
			zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS,
					ZBX_JSON_TYPE_STRING);
			zbx_json_addobject(&json, ZBX_PROTO_TAG_DATA);

			if (USER_TYPE_SUPER_ADMIN == user.type)
				status_stats_export(&json);

			zbx_json_close(&json);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() json.buffer:'%s'", __func__, json.buffer);

	(void)zbx_tcp_send_to(sock, json.buffer, config_timeout);

	zbx_json_free(&json);

	ret = SUCCEED;
out:
	zbx_user_free(&user);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#undef ZBX_GET_STATUS_UNKNOWN
#undef ZBX_GET_STATUS_PING
#undef ZBX_GET_STATUS_FULL
}

/********************************************************************************
 *                                                                              *
 * Purpose: processes Zabbix stats request                                      *
 *                                                                              *
 * Parameters: sock                    - [IN] request socket                    *
 *             jp                      - [IN] request data                      *
 *             config_comms            - [IN] Zabbix server/proxy configuration *
 *                                            for communication                 *
 *             config_startup_time     - [IN] program startup time              *
 *             config_stats_allowed_ip - [IN]                                   *
 *                                                                              *
 * Return value:  SUCCEED - processed successfully                              *
 *                FAIL - an error occurred                                      *
 *                                                                              *
 ********************************************************************************/
static int	send_internal_stats_json(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_comms_args_t *config_comms, int config_startup_time,
		const char *config_stats_allowed_ip)
{
	struct zbx_json	json;
	char		type[MAX_STRING_LEN], error[MAX_STRING_LEN];
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == config_stats_allowed_ip ||
			SUCCEED != zbx_tcp_check_allowed_peers(sock, config_stats_allowed_ip))
	{
		zabbix_log(LOG_LEVEL_WARNING, "failed to accept an incoming stats request: %s",
				NULL == config_stats_allowed_ip ? "StatsAllowedIP not set" : zbx_socket_strerror());
		zbx_strscpy(error, "Permission denied.");
		goto out;
	}

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_TYPE, type, sizeof(type), NULL) &&
			0 == strcmp(type, ZBX_PROTO_VALUE_ZABBIX_STATS_QUEUE))
	{
		char			from_str[ZBX_MAX_UINT64_LEN + 1], to_str[ZBX_MAX_UINT64_LEN + 1];
		int			from = ZBX_QUEUE_FROM_DEFAULT, to = ZBX_QUEUE_TO_INFINITY;
		struct zbx_json_parse	jp_data;

		if (SUCCEED != zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_PARAMS, &jp_data))
		{
			zbx_snprintf(error, sizeof(error), "cannot find tag: %s", ZBX_PROTO_TAG_PARAMS);
			goto param_error;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_data, ZBX_PROTO_TAG_FROM, from_str, sizeof(from_str), NULL)
				&& FAIL == zbx_is_time_suffix(from_str, &from, ZBX_LENGTH_UNLIMITED))
		{
			zbx_strscpy(error, "invalid 'from' parameter");
			goto param_error;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_data, ZBX_PROTO_TAG_TO, to_str, sizeof(to_str), NULL) &&
				FAIL == zbx_is_time_suffix(to_str, &to, ZBX_LENGTH_UNLIMITED))
		{
			zbx_strscpy(error, "invalid 'to' parameter");
			goto param_error;
		}

		if (ZBX_QUEUE_TO_INFINITY != to && from > to)
		{
			zbx_strscpy(error, "parameters represent an invalid interval");
			goto param_error;
		}

		zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
		zbx_json_addint64(&json, ZBX_PROTO_VALUE_ZABBIX_STATS_QUEUE, zbx_dc_get_item_queue(NULL, from, to));
	}
	else
	{
		zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
		zbx_json_addobject(&json, ZBX_PROTO_TAG_DATA);

		zbx_zabbix_stats_get(&json, config_startup_time);

		zbx_json_close(&json);
	}

	(void)zbx_tcp_send_to(sock, json.buffer, config_comms->config_timeout);
	ret = SUCCEED;
param_error:
	zbx_json_free(&json);
out:
	if (SUCCEED != ret)
		zbx_send_response(sock, ret, error, config_comms->config_timeout);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	process_active_check_heartbeat(zbx_socket_t *sock, const struct zbx_json_parse *jp, int config_timeout)
{
	char			host[ZBX_MAX_HOSTNAME_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1],
				hbfreq[ZBX_MAX_UINT64_LEN];
	zbx_history_recv_host_t	recv_host;
	unsigned char		*data = NULL;
	zbx_uint32_t		data_len;
	zbx_comms_redirect_t	redirect;
	int			ret;

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, host, sizeof(host), NULL))
		return FAIL;

	if (FAIL == (ret = zbx_dc_config_get_host_by_name(host, sock, &recv_host, &redirect)))
		return FAIL;

	if (SUCCEED_PARTIAL == ret)
	{
		struct zbx_json	j;

		zbx_json_init(&j, 1024);
		zbx_add_redirect_response(&j, &redirect);
		zbx_send_response_json(sock, FAIL, NULL, NULL, sock->protocol, config_timeout, j.buffer);
		zbx_json_free(&j);

		return FAIL;
	}

	if (HOST_MONITORED_BY_SERVER != recv_host.monitored_by || HOST_STATUS_NOT_MONITORED == recv_host.status)
		return SUCCEED;

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HEARTBEAT_FREQ, hbfreq, sizeof(hbfreq), NULL))
		return FAIL;

	data_len = zbx_availability_serialize_active_heartbeat(&data, recv_host.hostid, atoi(hbfreq));
	zbx_availability_send(ZBX_IPC_AVAILMAN_ACTIVE_HB, data, data_len, NULL);

	zbx_free(data);

	return SUCCEED;
}

static int	comms_parse_response(char *xml, char *host, size_t host_len, char *key, size_t key_len,
		char *data, size_t data_len, char *lastlogsize, size_t lastlogsize_len,
		char *timestamp, size_t timestamp_len, char *source, size_t source_len,
		char *severity, size_t severity_len)
{
	int	ret = SUCCEED;
	size_t	i;
	char	*data_b64 = NULL;

	assert(NULL != host && 0 != host_len);
	assert(NULL != key && 0 != key_len);
	assert(NULL != data && 0 != data_len);
	assert(NULL != lastlogsize && 0 != lastlogsize_len);
	assert(NULL != timestamp && 0 != timestamp_len);
	assert(NULL != source && 0 != source_len);
	assert(NULL != severity && 0 != severity_len);

	if (SUCCEED == zbx_xml_get_data_dyn(xml, "host", &data_b64))
	{
		zbx_base64_decode(data_b64, host, host_len - 1, &i);
		host[i] = '\0';
		zbx_xml_free_data_dyn(&data_b64);
	}
	else
	{
		*host = '\0';
		ret = FAIL;
	}

	if (SUCCEED == zbx_xml_get_data_dyn(xml, "key", &data_b64))
	{
		zbx_base64_decode(data_b64, key, key_len - 1, &i);
		key[i] = '\0';
		zbx_xml_free_data_dyn(&data_b64);
	}
	else
	{
		*key = '\0';
		ret = FAIL;
	}

	if (SUCCEED == zbx_xml_get_data_dyn(xml, "data", &data_b64))
	{
		zbx_base64_decode(data_b64, data, data_len - 1, &i);
		data[i] = '\0';
		zbx_xml_free_data_dyn(&data_b64);
	}
	else
	{
		*data = '\0';
		ret = FAIL;
	}

	if (SUCCEED == zbx_xml_get_data_dyn(xml, "lastlogsize", &data_b64))
	{
		zbx_base64_decode(data_b64, lastlogsize, lastlogsize_len - 1, &i);
		lastlogsize[i] = '\0';
		zbx_xml_free_data_dyn(&data_b64);
	}
	else
		*lastlogsize = '\0';

	if (SUCCEED == zbx_xml_get_data_dyn(xml, "timestamp", &data_b64))
	{
		zbx_base64_decode(data_b64, timestamp, timestamp_len - 1, &i);
		timestamp[i] = '\0';
		zbx_xml_free_data_dyn(&data_b64);
	}
	else
		*timestamp = '\0';

	if (SUCCEED == zbx_xml_get_data_dyn(xml, "source", &data_b64))
	{
		zbx_base64_decode(data_b64, source, source_len - 1, &i);
		source[i] = '\0';
		zbx_xml_free_data_dyn(&data_b64);
	}
	else
		*source = '\0';

	if (SUCCEED == zbx_xml_get_data_dyn(xml, "severity", &data_b64))
	{
		zbx_base64_decode(data_b64, severity, severity_len - 1, &i);
		severity[i] = '\0';
		zbx_xml_free_data_dyn(&data_b64);
	}
	else
		*severity = '\0';

	return ret;
}

static int	process_trap(zbx_socket_t *sock, char *s, ssize_t bytes_received, zbx_timespec_t *ts,
		const zbx_config_comms_args_t *config_comms, const zbx_config_vault_t *config_vault,
		int config_startup_time, const zbx_events_funcs_t *events_cbs, int proxydata_frequency,
		zbx_get_config_forks_f get_config_forks, const char *config_stats_allowed_ip, const char *progname,
		const char *config_java_gateway, int config_java_gateway_port, const char *config_externalscripts,
		int config_enable_global_scripts, zbx_get_value_internal_ext_f zbx_get_value_internal_ext_cb,
		const char *config_ssh_key_location, const char *config_webdriver_url,
		zbx_trapper_process_request_func_t trapper_process_request_cb,
		zbx_autoreg_update_host_func_t autoreg_update_host_cb)
{
	int	ret = SUCCEED;

	zbx_rtrim(s, " \r\n");

	zabbix_log(LOG_LEVEL_DEBUG, "trapper got '%s'", s);

	if ('{' == *s)	/* JSON protocol */
	{
		struct zbx_json_parse	jp;
		char			value[MAX_STRING_LEN] = "";

		if (SUCCEED != zbx_json_open(s, &jp))
		{
			zbx_send_response(sock, FAIL, zbx_json_strerror(), config_comms->config_timeout);
			zabbix_log(LOG_LEVEL_WARNING, "received invalid JSON object from %s: %s",
					sock->peer, zbx_json_strerror());
			return FAIL;
		}

		if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_REQUEST, value, sizeof(value), NULL))
			return FAIL;

		if (ZBX_GIBIBYTE < bytes_received && 0 != strcmp(value, ZBX_PROTO_VALUE_PROXY_CONFIG))
		{
			zabbix_log(LOG_LEVEL_WARNING, "message size " ZBX_FS_I64 " exceeds the maximum size "
					ZBX_FS_UI64 " for request \"%s\" received from \"%s\"", bytes_received,
					(zbx_uint64_t)ZBX_GIBIBYTE, value, sock->peer);
			return FAIL;
		}

		if (0 == strcmp(value, ZBX_PROTO_VALUE_AGENT_DATA))
		{
			recv_agenthistory(sock, &jp, ts, config_comms->config_timeout);
		}
		else if (0 == strcmp(value, ZBX_PROTO_VALUE_SENDER_DATA))
		{
			recv_senderhistory(sock, &jp, ts, config_comms->config_timeout);
		}
		else if (0 == strcmp(value, ZBX_PROTO_VALUE_PROXY_HEARTBEAT))
		{
			if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
				recv_proxy_heartbeat(sock, &jp);
		}
		else if (0 == strcmp(value, ZBX_PROTO_VALUE_GET_ACTIVE_CHECKS))
		{
			ret = send_list_of_active_checks_json(sock, &jp, events_cbs, config_comms->config_timeout,
					autoreg_update_host_cb);
		}
		else if (0 == strcmp(value, ZBX_PROTO_VALUE_COMMAND))
		{
			if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
			{
				ret = node_process_command(sock, s, &jp, config_comms->config_timeout,
						config_comms->config_trapper_timeout, config_comms->config_source_ip,
						config_ssh_key_location, get_config_forks, config_enable_global_scripts, zbx_get_program_type_cb());
			}
		}
		else if (0 == strcmp(value, ZBX_PROTO_VALUE_GET_QUEUE))
		{
			if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
				ret = recv_getqueue(sock, &jp, config_comms->config_timeout);
		}
		else if (0 == strcmp(value, ZBX_PROTO_VALUE_GET_STATUS))
		{
			if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
				ret = recv_getstatus(sock, &jp, config_comms->config_timeout);
		}
		else if (0 == strcmp(value, ZBX_PROTO_VALUE_ZABBIX_STATS))
		{
			ret = send_internal_stats_json(sock, &jp, config_comms, config_startup_time,
					config_stats_allowed_ip);
		}
		else if (0 == strcmp(value, ZBX_PROTO_VALUE_EXPRESSIONS_EVALUATE))
		{
			if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
				ret = zbx_trapper_expressions_evaluate(sock, &jp, config_comms->config_timeout);
		}
		else if (0 == strcmp(value, ZBX_PROTO_VALUE_ZABBIX_ITEM_TEST))
		{
			if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
			{
				zbx_trapper_item_test(sock, &jp, config_comms, config_startup_time,
						zbx_get_program_type_cb(), progname, get_config_forks,
						config_java_gateway, config_java_gateway_port, config_externalscripts,
						zbx_get_value_internal_ext_cb, config_ssh_key_location,
						config_webdriver_url);
			}
		}
		else if (0 == strcmp(value, ZBX_PROTO_VALUE_ACTIVE_CHECK_HEARTBEAT))
		{
			ret = process_active_check_heartbeat(sock, &jp, config_comms->config_timeout);
		}
		else if (SUCCEED != trapper_process_request_cb(value, sock, &jp, ts, config_comms, config_vault,
				proxydata_frequency, zbx_get_program_type_cb, events_cbs, get_config_forks))
		{
			zabbix_log(LOG_LEVEL_WARNING, "unknown request received from \"%s\": [%s]", sock->peer,
				value);
		}
	}
	else if (0 == strncmp(s, "ZBX_GET_ACTIVE_CHECKS", 21))	/* request for list of active checks */
	{
		ret = send_list_of_active_checks(sock, s, events_cbs, config_comms->config_timeout,
				autoreg_update_host_cb);
	}
	else
	{
		char			value_dec[MAX_BUFFER_LEN], lastlogsize[ZBX_MAX_UINT64_LEN], timestamp[11],
					source[ZBX_HISTORY_LOG_SOURCE_LEN_MAX], severity[11],
					host[ZBX_MAX_HOSTNAME_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1],
					key[ZBX_ITEM_KEY_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
		zbx_agent_value_t	av;
		zbx_host_key_t		hk = {host, key};
		zbx_history_recv_item_t	item;
		int			errcode;

		if (ZBX_GIBIBYTE < bytes_received)
		{
			zabbix_log(LOG_LEVEL_WARNING, "message size " ZBX_FS_I64 " exceeds the maximum size "
					ZBX_FS_UI64 " for XML protocol received from \"%s\"", bytes_received,
					(zbx_uint64_t)ZBX_GIBIBYTE, sock->peer);
			return FAIL;
		}

		if (SUCCEED == zbx_vps_monitor_capped())
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot accept data: data collection has been paused.");
			return FAIL;
		}

		memset(&av, 0, sizeof(zbx_agent_value_t));

		if ('<' == *s)	/* XML protocol */
		{
			comms_parse_response(s, host, sizeof(host), key, sizeof(key), value_dec,
					sizeof(value_dec), lastlogsize, sizeof(lastlogsize), timestamp,
					sizeof(timestamp), source, sizeof(source), severity, sizeof(severity));

			av.value = value_dec;
			if (SUCCEED != zbx_is_uint64(lastlogsize, &av.lastlogsize))
				av.lastlogsize = 0;
			av.timestamp = atoi(timestamp);
			av.source = source;
			av.severity = atoi(severity);
		}
		else
		{
			char	*pl, *pr;

			pl = s;
			if (NULL == (pr = strchr(pl, ':')))
				return FAIL;

			*pr = '\0';
			zbx_strlcpy(host, pl, sizeof(host));
			*pr = ':';

			pl = pr + 1;
			if (NULL == (pr = strchr(pl, ':')))
				return FAIL;

			*pr = '\0';
			zbx_strlcpy(key, pl, sizeof(key));
			*pr = ':';

			av.value = pr + 1;
			av.severity = 0;
		}

		zbx_timespec(&av.ts);

		if (0 == strcmp(av.value, ZBX_NOTSUPPORTED))
			av.state = ITEM_STATE_NOTSUPPORTED;

		zbx_dc_config_history_recv_get_items_by_keys(&item, &hk, &errcode, 1);
		zbx_process_history_data(&item, &av, &errcode, 1, NULL);

		if (SUCCEED != zbx_tcp_send_ext(sock, "OK", ZBX_CONST_STRLEN("OK"), 0, 0, config_comms->config_timeout))
			zabbix_log(LOG_LEVEL_WARNING, "Error sending result back");
	}

	return ret;
}

static void	process_trapper_child(zbx_socket_t *sock, zbx_timespec_t *ts,
		const zbx_config_comms_args_t *config_comms, const zbx_config_vault_t *config_vault,
		int config_startup_time, const zbx_events_funcs_t *events_cbs, int proxydata_frequency,
		zbx_get_config_forks_f get_config_forks, const char *config_stats_allowed_ip, const char *progname,
		const char *config_java_gateway, int config_java_gateway_port, const char *config_externalscripts,
		int config_enable_global_scripts, zbx_get_value_internal_ext_f zbx_get_value_internal_ext_cb,
		const char *config_ssh_key_location, const char *config_webdriver_url,
		zbx_trapper_process_request_func_t trapper_process_request_cb,
		zbx_autoreg_update_host_func_t autoreg_update_host_cb)
{
	ssize_t	bytes_received;

	if (FAIL == (bytes_received = zbx_tcp_recv_ext(sock, config_comms->config_trapper_timeout, ZBX_TCP_LARGE)))
		return;

	process_trap(sock, sock->buffer, bytes_received, ts, config_comms, config_vault, config_startup_time,
			events_cbs, proxydata_frequency, get_config_forks, config_stats_allowed_ip, progname,
			config_java_gateway, config_java_gateway_port, config_externalscripts,
			config_enable_global_scripts, zbx_get_value_internal_ext_cb, config_ssh_key_location,
			config_webdriver_url, trapper_process_request_cb, autoreg_update_host_cb);
}

ZBX_THREAD_ENTRY(zbx_trapper_thread, args)
{
#define POLL_TIMEOUT	1
	zbx_thread_trapper_args	*trapper_args_in = (zbx_thread_trapper_args *)
					(((zbx_thread_args_t *)args)->args);
	double			sec = 0.0;
	zbx_socket_t		s;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	int			ret, server_num = ((zbx_thread_args_t *)args)->info.server_num,
				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;
#ifdef HAVE_NETSNMP
	zbx_uint32_t		rtc_msgs[] = {ZBX_RTC_SNMP_CACHE_RELOAD};
	zbx_ipc_async_socket_t	rtc;
#endif

	zbx_get_program_type_cb = trapper_args_in->zbx_get_program_type_cb_arg;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	memcpy(&s, trapper_args_in->listen_sock, sizeof(zbx_socket_t));

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child(trapper_args_in->config_comms->config_tls, zbx_get_program_type_cb,
			zbx_dc_get_psk_by_identity);
#endif
	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

#ifdef HAVE_NETSNMP
	zbx_rtc_subscribe(process_type, process_num, rtc_msgs, ARRSIZE(rtc_msgs),
			trapper_args_in->config_comms->config_timeout, &rtc);
#endif

	while (ZBX_IS_RUNNING())
	{
#ifdef HAVE_NETSNMP
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;
		int		snmp_reload = 0;
#endif

		zbx_setproctitle("%s #%d [processed data in " ZBX_FS_DBL " sec, waiting for connection%s]",
				get_process_type_string(process_type), process_num, sec, zbx_vps_monitor_status());

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);

		/* Trapper has to accept all types of connections it can accept with the specified configuration. */
		/* Only after receiving data it is known who has sent them and one can decide to accept or discard */
		/* the data. */
		ret = zbx_tcp_accept(&s, ZBX_TCP_SEC_TLS_CERT | ZBX_TCP_SEC_TLS_PSK | ZBX_TCP_SEC_UNENCRYPTED,
				POLL_TIMEOUT);
		zbx_update_env(get_process_type_string(process_type), zbx_time());

		if (TIMEOUT_ERROR == ret)
			continue;

		if (SUCCEED == ret)
		{
			zbx_timespec_t	ts;

			/* get connection timestamp */
			zbx_timespec(&ts);

			zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

			zbx_setproctitle("%s #%d [processing data]", get_process_type_string(process_type),
					process_num);

#ifdef HAVE_NETSNMP
			while (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data, 0) && 0 != rtc_cmd)
			{
				if (ZBX_RTC_SNMP_CACHE_RELOAD == rtc_cmd && 0 == snmp_reload)
				{
					zbx_clear_cache_snmp(process_type, process_num);
					snmp_reload = 1;
				}
				else if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				{
					zbx_tcp_unaccept(&s);
					goto out;
				}

			}
#endif
			sec = zbx_time();
			process_trapper_child(&s, &ts, trapper_args_in->config_comms, trapper_args_in->config_vault,
					trapper_args_in->config_startup_time, trapper_args_in->events_cbs,
					trapper_args_in->proxydata_frequency,
					trapper_args_in->get_process_forks_cb_arg,
					trapper_args_in->config_stats_allowed_ip, trapper_args_in->progname,
					trapper_args_in->config_java_gateway,
					trapper_args_in->config_java_gateway_port,
					trapper_args_in->config_externalscripts,
					trapper_args_in->config_enable_global_scripts,
					trapper_args_in->zbx_get_value_internal_ext_cb,
					trapper_args_in->config_ssh_key_location,
					trapper_args_in->config_webdriver_url,
					trapper_args_in->trapper_process_request_func_cb,
					trapper_args_in->autoreg_update_host_cb);
			sec = zbx_time() - sec;

			zbx_tcp_unaccept(&s);
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to accept an incoming connection: %s",
					zbx_socket_strerror());
		}
	}
#ifdef HAVE_NETSNMP
out:
#endif
	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);

#undef POLL_TIMEOUT
}
