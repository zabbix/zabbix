/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "trapper_history_push.h"
#include "zbxjson.h"
#include "zbxcommshigh.h"
#include "zbxcomms.h"
#include "zbxstr.h"
#include "trapper_auth.h"
#include "zbxcacheconfig.h"
#include "zbx_host_constants.h"
#include "zbx_item_constants.h"
#include "zbxexpression.h"
#include "zbxdbwrap.h"
#include "zbxpreproc.h"

static int	parse_item(const char *pnext, time_t now, int *ns_offset, zbx_uint64_t *itemid,
		char **host, char **key, char **value, zbx_timespec_t *ts, char **error)
{
	char			*str = NULL;
	size_t			str_alloc = 0;
	int			ret = FAIL;
	struct zbx_json_parse	jp;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_json_brackets_open(pnext, &jp))
	{
		*error = zbx_strdup(NULL, "cannot open object");
		goto out;
	}

	if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_ITEMID, &str, &str_alloc, NULL))
	{
		if (SUCCEED != zbx_is_uint64(str, itemid) || 0 == itemid)
		{
			*error = zbx_dsprintf(NULL, "invalid \"%s\" tag value: \"%s\"", ZBX_PROTO_TAG_ITEMID, str);
			goto out;
		}
	}

	if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_HOST, &str, &str_alloc, NULL))
		*host = zbx_strdup(NULL, str);

	if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_KEY, &str, &str_alloc, NULL))
		*key = zbx_strdup(NULL, str);

	if (0 != *itemid)
	{
		if (NULL != *host || NULL != *key)
		{
			*error = zbx_dsprintf(NULL, "\"%s\" tag conflicts with \"%s\" and \"%s\" tags",
					ZBX_PROTO_TAG_ITEMID, ZBX_PROTO_TAG_HOST, ZBX_PROTO_TAG_ITEMID);
			goto out;
		}
	}
	else
	{
		if (NULL == *host || NULL == *key)
		{
			*error = zbx_dsprintf(NULL, "missing \"%s\" or \"%s\" tag",
					ZBX_PROTO_TAG_HOST, ZBX_PROTO_TAG_ITEMID);
			goto out;
		}
	}

	if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_CLOCK, &str, &str_alloc, NULL))
	{
		if (SUCCEED != zbx_is_uint_n_range(str, ZBX_SIZE_T_MAX, &ts->sec, 4, 1, INT32_MAX))
		{
			*error = zbx_dsprintf(NULL, "invalid \"%s\" tag value: \"%s\"", ZBX_PROTO_TAG_CLOCK, str);
			goto out;
		}
	}

	if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_NS, &str, &str_alloc, NULL))
	{
		if (SUCCEED != zbx_is_uint_n_range(str, ZBX_SIZE_T_MAX, &ts->ns, 4, 0, 999999999))
		{
			*error = zbx_dsprintf(NULL, "invalid \"%s\" tag value: \"%s\"", ZBX_PROTO_TAG_NS, str);
			goto out;
		}

		if (0 == ts->sec)
		{
			*error = zbx_dsprintf(NULL, "cannot use \"%s\" tag without \"%s\" tag",
					ZBX_PROTO_TAG_NS, ZBX_PROTO_TAG_CLOCK);
			goto out;
		}
	}
	else
	{
		ts->ns = (*ns_offset)++;
	}

	if (0 == ts->sec)
		ts->sec = (int)now;

	if (SUCCEED != zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_VALUE, &str, &str_alloc, NULL))
	{
		*error = zbx_dsprintf(*error, "missing \"%s\" tag", ZBX_PROTO_TAG_VALUE);
		goto out;
	}

	*value = str;
	str = NULL;
	ret = SUCCEED;
out:
	zbx_free(str);

	if (SUCCEED != ret)
	{
		zbx_free(*host);
		zbx_free(*key);
		zbx_free(*value);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s itemid:" ZBX_FS_UI64 " host:%s key:%s ts:%d.%09d value:%s error:%s",
			__func__, zbx_result_string(ret), *itemid, ZBX_NULL2EMPTY_STR(*host), ZBX_NULL2EMPTY_STR(*key),
			ts->sec, ts->ns, ZBX_NULL2EMPTY_STR(*value), ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

ZBX_VECTOR_DECL(ts, zbx_timespec_t)
ZBX_VECTOR_IMPL(ts, zbx_timespec_t)

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_vector_ts_t	timestamps;
}
zbx_item_timestamps_t;

static int	push_item_value(struct addrinfo *ai, zbx_uint64_t userid, zbx_hashset_t *timestamps,
		zbx_history_recv_item_t *item, int errcode, zbx_agent_value_t *value, char **error)
{
	if (SUCCEED != errcode)
	{
		*error = zbx_strdup(NULL, "Invalid item.");
		return FAIL;
	}

	if (ITEM_STATUS_ACTIVE != item->status)
	{
		*error = zbx_strdup(NULL, "Item is disabled.");
		return FAIL;
	}

	if (ITEM_TYPE_TRAPPER != item->type && (ITEM_TYPE_HTTPAGENT != item->type || 0 == item->allow_traps))
	{
		*error = zbx_strdup(NULL, "Unsupported item type.");
		return FAIL;
	}

	if (HOST_STATUS_MONITORED != item->host.status)
	{
		*error = zbx_strdup(NULL, "Host is not monitored.");
		return FAIL;
	}

	if (SUCCEED == zbx_in_maintenance_without_data_collection(item->host.maintenance_status,
			item->host.maintenance_type, item->type) &&
			item->host.maintenance_from <= value->ts.sec)
	{
		*error = zbx_strdup(NULL, "Host is in maintenance without data collection.");
		return FAIL;
	}

	if (NULL != item->trapper_hosts && '\0' != *item->trapper_hosts)
	{
		char	*allowed_peers;
		int	ret = FAIL;

		allowed_peers = zbx_strdup(NULL, item->trapper_hosts);
		zbx_substitute_simple_macros_allowed_hosts(item, &allowed_peers);
		ret = zbx_tcp_check_allowed_peers_info((ZBX_SOCKADDR *)ai->ai_addr, allowed_peers);
		zbx_free(allowed_peers);

		if (FAIL == ret)
		{
			*error = zbx_strdup(NULL, "Client IP is not in item's allowed hosts list.");
			return FAIL;
		}
	}

	char	*user_timezone = NULL;
	int	perm;

	perm = zbx_get_item_permission(userid, item->itemid, &user_timezone);
	zbx_free(user_timezone);
	if (PERM_READ > perm)
	{
		*error = zbx_strdup(NULL, "Insufficient permissions to upload history.");
		return FAIL;
	}

	zbx_item_timestamps_t	*item_ts;

	if (NULL == (item_ts = (zbx_item_timestamps_t *)zbx_hashset_search(timestamps, &item->itemid)))
	{
		zbx_item_timestamps_t	item_ts_local = {.itemid =  item->itemid};

		item_ts = (zbx_item_timestamps_t *)zbx_hashset_insert(timestamps, &item_ts_local, sizeof(item_ts_local));
		zbx_vector_ts_create(&item_ts->timestamps);
	}

	for (int i = 0; i < item_ts->timestamps.values_num; i++)
	{
		if (item_ts->timestamps.values[i].sec == value->ts.sec &&
				item_ts->timestamps.values[i].ns == value->ts.ns)
		{
			*error = zbx_strdup(NULL, "Duplicate timestamp found.");
			return FAIL;
		}
	}

	zbx_vector_ts_append(&item_ts->timestamps, value->ts);

	AGENT_RESULT	result;

	zbx_init_agent_result(&result);

	SET_TEXT_RESULT(&result, value->value);
	value->value = NULL;

	zbx_preprocess_item_value(item->itemid, item->host.hostid, item->value_type, item->flags, &result, &value->ts,
			ITEM_STATE_NORMAL, NULL);

	zbx_free_agent_result(&result);

	return SUCCEED;
}

typedef struct
{
	zbx_uint64_t	itemid;
	char		*error;
}
zbx_item_error_t;

static int	process_history_push(const struct zbx_json_parse *jp, char **data)
{
	zbx_user_t			user;
	struct zbx_json_parse		jp_data;
	int				ret = FAIL;
	char				clientip[MAX_STRING_LEN];
	struct addrinfo			hints, *ai = NULL;
	zbx_vector_uint64_t		itemids, id_index, hk_index;
	zbx_vector_host_key_t		hostkeys;
	zbx_vector_agent_value_t	values;
	int				ns_offset = 0, items_num = 0;
	const char			*pnext = NULL;
	time_t				now;

	zbx_vector_uint64_create(&itemids);
	zbx_vector_uint64_create(&id_index);
	zbx_vector_uint64_create(&hk_index);
	zbx_vector_host_key_create(&hostkeys);
	zbx_vector_agent_value_create(&values);

	zbx_user_init(&user);

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL))
	{
		*data = zbx_strdup(NULL, "Permission denied.");
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLIENTIP, clientip, sizeof(clientip), NULL))
	{
		*data = zbx_strdup(NULL, "Cannot find tag: \"" ZBX_PROTO_TAG_CLIENTIP "\".");
		goto out;
	}

	zbx_tcp_init_hints(&hints, SOCK_STREAM, AI_NUMERICHOST);
	if (0 != getaddrinfo(clientip, NULL, &hints, &ai))
	{
		*data = zbx_dsprintf(NULL, "Invalid tag \"" ZBX_PROTO_TAG_CLIENTIP "\" value: %s.", clientip);
		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		*data = zbx_strdup(NULL, "Cannot find tag: \"" ZBX_PROTO_TAG_DATA "\".");
		goto out;
	}

	now = time(NULL);

	while (NULL != (pnext = zbx_json_next(&jp_data, pnext)))
	{
		char			*host = NULL, *key = NULL, *value = NULL, *error = NULL;
		zbx_uint64_t		itemid = 0;
		zbx_timespec_t		ts = {0, 0};
		zbx_agent_value_t	agent_value = {0};

		if (SUCCEED != parse_item(pnext, now, &ns_offset, &itemid, &host, &key, &value, &ts, &error))
		{
			*data = zbx_dsprintf(NULL, "Cannot parse item #%d data: %s.", items_num + 1, error);
			zbx_free(error);
			goto out;
		}

		if (0 == itemid)
		{
			zbx_host_key_t	hostkey = {.host = host, .key = key};

			zbx_vector_host_key_append_ptr(&hostkeys, &hostkey);
			zbx_vector_uint64_append(&hk_index, items_num);
		}
		else
		{
			zbx_vector_uint64_append(&itemids, itemid);
			zbx_vector_uint64_append(&id_index, items_num);
		}

		agent_value.value = value;
		agent_value.ts = ts;
		zbx_vector_agent_value_append_ptr(&values, &agent_value);

		items_num++;
	}

	zbx_history_recv_item_t	*id_items, *hk_items;
	int			*id_errcodes, *hk_errcodes;

	id_items = (zbx_history_recv_item_t *)zbx_malloc(NULL, sizeof(zbx_history_recv_item_t) *
			(size_t)itemids.values_num);
	id_errcodes = (int *)zbx_malloc(NULL, sizeof(int) * (size_t)itemids.values_num);
	zbx_dc_config_history_recv_get_items_by_itemids(id_items, itemids.values, id_errcodes, itemids.values_num,
			ZBX_ITEM_GET_DEFAULT);

	hk_items = (zbx_history_recv_item_t *)zbx_malloc(NULL, sizeof(zbx_history_recv_item_t) *
			(size_t)hostkeys.values_num);
	hk_errcodes = (int *)zbx_malloc(NULL, sizeof(int) * (size_t)hostkeys.values_num);
	zbx_dc_config_history_recv_get_items_by_keys(hk_items, hostkeys.values, hk_errcodes,
			(size_t)hostkeys.values_num);

	int			hk_i = 0, id_i = 0;
	struct zbx_json		j;
	zbx_hashset_t		item_timestamps, item_errors;

	zbx_dc_um_handle_t	*um_handle = zbx_dc_open_user_macros();

	zbx_hashset_create(&item_timestamps, items_num, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&item_errors, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_json_init(&j, 1024);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (int i = 0; i < items_num; i++)
	{
		zbx_history_recv_item_t	*item;
		int			errcode;
		char			*error = NULL;
		zbx_item_error_t	*item_err;

		if ((int)id_index.values[id_i] == i)
		{
			item = &id_items[id_i];
			errcode = id_errcodes[id_i++];
		}
		else
		{
			item = &hk_items[hk_i];
			errcode = hk_errcodes[hk_i++];
		}

		zbx_json_addobject(&j, NULL);

		zbx_json_adduint64(&j, ZBX_PROTO_TAG_ITEMID, item->itemid);

		if (NULL != (item_err = (zbx_item_error_t *)zbx_hashset_search(&item_errors, &item->itemid)))
		{
			zbx_json_addstring(&j, ZBX_PROTO_TAG_ERROR, item_err->error, ZBX_JSON_TYPE_STRING);
		}
		else if (SUCCEED != push_item_value(ai, user.userid, &item_timestamps, item, errcode, &values.values[i],
				&error))
		{
			zbx_item_error_t	item_err_local = {.itemid = item->itemid, .error = error};

			zbx_hashset_insert(&item_errors, &item_err_local, sizeof(item_err_local));
			zbx_json_addstring(&j, ZBX_PROTO_TAG_ERROR, error, ZBX_JSON_TYPE_STRING);
		}

		zbx_json_close(&j);
	}

	zbx_dc_close_user_macros(um_handle);

	zbx_preprocessor_flush();

	*data = zbx_strdup(NULL, j.buffer);

	zbx_json_clean(&j);

	zbx_hashset_iter_t	iter;
	zbx_item_timestamps_t	*item_ts;

	zbx_hashset_iter_reset(&item_timestamps, &iter);
	while (NULL != (item_ts = (zbx_item_timestamps_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ts_destroy(&item_ts->timestamps);

	zbx_hashset_destroy(&item_timestamps);

	zbx_item_error_t	*item_err;

	zbx_hashset_iter_reset(&item_errors, &iter);
	while (NULL != (item_err = (zbx_item_error_t *)zbx_hashset_iter_next(&iter)))
		zbx_free(item_err->error);

	zbx_hashset_destroy(&item_errors);

	zbx_free(id_items);
	zbx_free(id_errcodes);
	zbx_free(hk_items);
	zbx_free(hk_errcodes);

	ret = SUCCEED;

out:
	if (NULL != ai)
		freeaddrinfo(ai);

	zbx_user_free(&user);

	for (int i = 0; i < values.values_num; i++)
	{
		zbx_free(values.values[i].value);
		zbx_free(values.values[i].source);
	}
	zbx_vector_agent_value_destroy(&values);

	for (int i = 0; i < hostkeys.values_num; i++)
	{
		zbx_free(hostkeys.values[i].host);
		zbx_free(hostkeys.values[i].key);
	}
	zbx_vector_host_key_destroy(&hostkeys);

	zbx_vector_uint64_destroy(&hk_index);
	zbx_vector_uint64_destroy(&id_index);
	zbx_vector_uint64_destroy(&itemids);

	return ret;
}

int	trapper_process_history_push(zbx_socket_t *sock, const struct zbx_json_parse *jp, int timeout)
{
	char		*data = NULL;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != (ret = process_history_push(jp, &data)))
	{
		zbx_send_response(sock, ret, data, timeout);
		zbx_free(data);
		goto out;
	}

	zbx_tcp_send(sock, data);
	zbx_free(data);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
