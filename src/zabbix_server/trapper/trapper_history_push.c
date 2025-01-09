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

#include "trapper_history_push.h"
#include "zbxtrapper.h"
#include "zbxjson.h"
#include "zbxcommshigh.h"
#include "zbxcomms.h"
#include "zbxstr.h"
#include "zbxcacheconfig.h"
#include "zbx_host_constants.h"
#include "zbx_item_constants.h"
#include "zbxexpression.h"
#include "zbxdbwrap.h"
#include "zbxpreproc.h"
#include "audit/zbxaudit.h"
#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxnum.h"
#include "zbxtime.h"

#define INVALID_ITEM_OR_NO_PERMISSION_ERROR	"No permissions to referred object or it does not exist."

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_host_key_t 	hk;
	char		*value;
	zbx_timespec_t	ts;
}
zbx_hp_item_value_t;

ZBX_PTR_VECTOR_DECL(hp_item_value_ptr, zbx_hp_item_value_t *)
ZBX_PTR_VECTOR_IMPL(hp_item_value_ptr, zbx_hp_item_value_t *)

static void	hp_item_value_free(zbx_hp_item_value_t *hp)
{
	zbx_free(hp->hk.host);
	zbx_free(hp->hk.key);
	zbx_free(hp->value);
	zbx_free(hp);
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates item value from json data                                 *
 *                                                                            *
 * Parameters: pnext        - [IN] json data                                  *
 *             ts           - [IN] current time                               *
 *             unique_shift - [IN/OUT] offset to apply when json data does    *
 *                                     not include ns tag                     *
 *             error        - [OUT] error message                             *
 *                                                                            *
 * Return value: created value or NULL in case of error                       *
 *                                                                            *
 ******************************************************************************/
static zbx_hp_item_value_t	*create_item_value(const char *pnext, zbx_timespec_t *ts, zbx_timespec_t *unique_shift,
		char **error)
{
	char			*str = NULL;
	size_t			str_alloc = 0;
	int			ret = FAIL;
	struct zbx_json_parse	jp;
	zbx_hp_item_value_t	*hp = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_json_brackets_open(pnext, &jp))
	{
		*error = zbx_strdup(NULL, "cannot open object");
		goto out;
	}

	hp = (zbx_hp_item_value_t *)zbx_malloc(NULL, sizeof(zbx_hp_item_value_t));
	memset(hp, 0, sizeof(zbx_hp_item_value_t));

	if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_ITEMID, &str, &str_alloc, NULL))
	{
		if (SUCCEED != zbx_is_uint64(str, &hp->itemid) || 0 == hp->itemid)
		{
			*error = zbx_dsprintf(NULL, "invalid \"%s\" tag value: \"%s\"", ZBX_PROTO_TAG_ITEMID, str);
			goto out;
		}
	}

	if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_HOST, &str, &str_alloc, NULL))
		hp->hk.host = zbx_strdup(NULL, str);

	if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_KEY, &str, &str_alloc, NULL))
		hp->hk.key = zbx_strdup(NULL, str);

	if (0 != hp->itemid)
	{
		if (NULL != hp->hk.host || NULL != hp->hk.key)
		{
			*error = zbx_dsprintf(NULL, "\"%s\" tag conflicts with \"%s\" and \"%s\" tags",
					ZBX_PROTO_TAG_ITEMID, ZBX_PROTO_TAG_HOST, ZBX_PROTO_TAG_ITEMID);
			goto out;
		}
	}
	else
	{
		if (NULL == hp->hk.host || NULL == hp->hk.key)
		{
			*error = zbx_dsprintf(NULL, "missing \"%s\" or \"%s\" tag",
					ZBX_PROTO_TAG_HOST, ZBX_PROTO_TAG_ITEMID);
			goto out;
		}
	}

	if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_CLOCK, &str, &str_alloc, NULL))
	{
		if (SUCCEED != zbx_is_uint_n_range(str, ZBX_SIZE_T_MAX, &hp->ts.sec, 4, 1, INT32_MAX))
		{
			*error = zbx_dsprintf(NULL, "invalid \"%s\" tag value: \"%s\"", ZBX_PROTO_TAG_CLOCK, str);
			goto out;
		}
	}

	if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_NS, &str, &str_alloc, NULL))
	{
		if (SUCCEED != zbx_is_uint_n_range(str, ZBX_SIZE_T_MAX, &hp->ts.ns, 4, 0, 999999999))
		{
			*error = zbx_dsprintf(NULL, "invalid \"%s\" tag value: \"%s\"", ZBX_PROTO_TAG_NS, str);
			goto out;
		}

		if (0 == hp->ts.sec)
		{
			*error = zbx_dsprintf(NULL, "cannot use \"%s\" tag without \"%s\" tag",
					ZBX_PROTO_TAG_NS, ZBX_PROTO_TAG_CLOCK);
			goto out;
		}
	}
	else
	{
		if (0 == hp->ts.sec)
			hp->ts.sec = ts->sec;

		hp->ts.ns = ts->ns + unique_shift->ns++;

		if (hp->ts.ns > 999999999)
		{
			unique_shift->sec++;
			unique_shift->ns = hp->ts.ns = 0;
		}

		hp->ts.sec += unique_shift->sec;
	}

	if (SUCCEED != zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_VALUE, &str, &str_alloc, NULL))
	{
		*error = zbx_dsprintf(*error, "missing \"%s\" tag", ZBX_PROTO_TAG_VALUE);
		goto out;
	}

	hp->value = str;
	str = NULL;

	ret = SUCCEED;
out:
	zbx_free(str);

	if (SUCCEED != ret && NULL != hp)
	{
		hp_item_value_free(hp);
		hp = NULL;
	}

	if (NULL != hp)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s itemid:" ZBX_FS_UI64
				" host:%s key:%s ts:%d.%09d value:%s",
				__func__, zbx_result_string(ret), hp->itemid, ZBX_NULL2EMPTY_STR(hp->hk.host),
				ZBX_NULL2EMPTY_STR(hp->hk.key), hp->ts.sec, hp->ts.ns, ZBX_NULL2EMPTY_STR(hp->value));
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error:%s", __func__, zbx_result_string(ret),
				ZBX_NULL2EMPTY_STR(*error));
	}

	return hp;
}

ZBX_VECTOR_DECL(ts, zbx_timespec_t)
ZBX_VECTOR_IMPL(ts, zbx_timespec_t)

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_vector_ts_t	timestamps;
}
zbx_item_timestamps_t;

typedef struct
{
	zbx_uint64_t		hostid;
	zbx_user_permission_t	permission;
}
zbx_host_permission_t;

/******************************************************************************
 *                                                                            *
 * Purpose: validates item configuration if it can accept history             *
 *                                                                            *
 * Return value: SUCCEED - item can accept history values                     *
 *               FAIL    - item configuration error                           *
 *                                                                            *
 ******************************************************************************/
static int	validate_item_config(ZBX_SOCKADDR *peer_addr, ZBX_SOCKADDR *client_addr,
		zbx_hashset_t *rights, const zbx_user_t *user, zbx_history_recv_item_t *item,
		const zbx_hp_item_value_t *value, char **error)
{
	if (NULL != rights)
	{
		zbx_host_permission_t	*perm;

		if (0 == (perm = (zbx_host_permission_t *)zbx_hashset_search(rights, &item->host.hostid)))
		{
			zbx_host_permission_t	perm_local;

			perm_local.hostid = item->host.hostid;
			perm_local.permission = zbx_get_host_permission(user, item->host.hostid);

			perm = (zbx_host_permission_t *)zbx_hashset_insert(rights, &perm_local, sizeof(perm_local));
		}

		if (PERM_READ > perm->permission)
		{
			*error = zbx_strdup(NULL, INVALID_ITEM_OR_NO_PERMISSION_ERROR);
			return FAIL;
		}
	}

	if (ITEM_STATUS_ACTIVE != item->status)
	{
		*error = zbx_strdup(NULL, "Item is disabled.");
		return FAIL;
	}

	if (ITEM_TYPE_TRAPPER != item->type && ITEM_TYPE_HTTPAGENT != item->type)
	{
		*error = zbx_strdup(NULL, "Unsupported item type.");
		return FAIL;
	}

	if (ITEM_TYPE_HTTPAGENT == item->type && 0 == item->allow_traps)
	{
		*error = zbx_strdup(NULL, "HTTP agent item trapping is not enabled.");
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

	if ('\0' != *item->trapper_hosts)
	{
		char	*allowed_peers;
		int	ret = FAIL;

		allowed_peers = zbx_strdup(NULL, item->trapper_hosts);
		zbx_substitute_simple_macros_allowed_hosts(item, &allowed_peers);

		if (SUCCEED == (ret = zbx_tcp_check_allowed_peers_info(peer_addr, allowed_peers)))
		{
			if (FAIL == (ret = zbx_tcp_check_allowed_peers_info(client_addr, allowed_peers)))
				*error = zbx_strdup(NULL, "Client IP is not in item's allowed hosts list.");
		}
		else
			*error = zbx_strdup(NULL, "Connection source address is not in item's allowed hosts list.");

		zbx_free(allowed_peers);

		if (FAIL == ret)
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates item value for duplicate timestamps                     *
 *                                                                            *
 * Return value: SUCCEED - value can be pushed to history                     *
 *               FAIL    - duplicate timestamp detected                       *
 *                                                                            *
 ******************************************************************************/
static int	validate_item_value(zbx_hashset_t *item_timestamps, const zbx_history_recv_item_t *item,
		const zbx_hp_item_value_t *value, char **error)
{
	zbx_item_timestamps_t	*item_ts;

	if (NULL == (item_ts = (zbx_item_timestamps_t *)zbx_hashset_search(item_timestamps, &item->itemid)))
	{
		zbx_item_timestamps_t	item_ts_local = {.itemid =  item->itemid};

		item_ts = (zbx_item_timestamps_t *)zbx_hashset_insert(item_timestamps, &item_ts_local,
				sizeof(item_ts_local));
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

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends item value to preprocessing                                 *
 *                                                                            *
 ******************************************************************************/
static void	push_item_value_to_history(const zbx_history_recv_item_t *item, zbx_hp_item_value_t *value)
{
	AGENT_RESULT	result;

	zbx_init_agent_result(&result);

	SET_TEXT_RESULT(&result, value->value);
	value->value = NULL;

	zbx_preprocess_item_value(item->itemid, item->host.hostid, item->value_type, item->flags, &result, &value->ts,
			ITEM_STATE_NORMAL, NULL);

	zbx_free_agent_result(&result);
}

typedef struct
{
	zbx_uint64_t	itemid;
	char		*error;
}
zbx_item_error_t;

static int	hk_compare(const void *d1, const void *d2)
{
	const zbx_host_key_t	*hk1 = (const zbx_host_key_t *)d1;
	const zbx_host_key_t	*hk2 = (const zbx_host_key_t *)d2;
	int			ret;

	if (0 != (ret = strcmp(hk1->host, hk2->host)))
		return ret;

	return strcmp(hk1->key, hk2->key);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes received item values                                    *
 *                                                                            *
 * Parameters: userid        - [IN]                                           *
 *             peer_addr     - [IN] connection source address                 *
 *             client_addr   - [IN] clientip address                          *
 *             values        - [IN] parsed values                             *
 *             itemids_num   - [IN]                                           *
 *             hostkeys_num  - [IN]                                           *
 *             processed_num - [OUT] number of processed values               *
 *             failed_num    - [OUT] number of failed values                  *
 *             j             - [OUT] json response buffer                     *
 *                                                                            *
 * Return value: SUCCEED - values were pushed to history                      *
 *               FAIL    - parsing failure                                    *
 *                                                                            *
 ******************************************************************************/
static void	process_item_values(const zbx_user_t *user, ZBX_SOCKADDR *peer_addr, ZBX_SOCKADDR *client_addr,
		zbx_vector_hp_item_value_ptr_t *values, int itemids_num, int hostkeys_num, int *processed_num,
		int *failed_num, struct zbx_json *j)
{
	/* extract unique itemids/hostkeys */

	zbx_vector_uint64_t	itemids;
	zbx_vector_host_key_t	hostkeys;
	zbx_hashset_t		rights, *prights;

	if (USER_TYPE_SUPER_ADMIN != user->type)
	{
		zbx_hashset_create(&rights, 100, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		prights = &rights;
	}
	else
		prights = NULL;

	zbx_vector_uint64_create(&itemids);
	if (0 != itemids_num)
		zbx_vector_uint64_reserve(&itemids, (size_t)itemids_num);

	zbx_vector_host_key_create(&hostkeys);
	if (0 != hostkeys_num)
		zbx_vector_host_key_reserve(&hostkeys, (size_t)hostkeys_num);

	for (int i = 0; i < values->values_num; i++)
	{
		if (0 == values->values[i]->itemid)
			zbx_vector_host_key_append(&hostkeys, values->values[i]->hk);
		else
			zbx_vector_uint64_append(&itemids, values->values[i]->itemid);
	}

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_host_key_sort(&hostkeys, hk_compare);
	zbx_vector_host_key_uniq(&hostkeys, hk_compare);

	/* obtain item configuration */

	zbx_history_recv_item_t	*id_items = NULL, *hk_items = NULL;
	int			*id_errcodes = NULL, *hk_errcodes = NULL;

	if (0 != itemids.values_num)
	{
		id_items = (zbx_history_recv_item_t *)zbx_malloc(NULL, sizeof(zbx_history_recv_item_t) *
				(size_t)itemids.values_num);
		id_errcodes = (int *)zbx_malloc(NULL, sizeof(int) * (size_t)itemids.values_num);
		zbx_dc_config_history_recv_get_items_by_itemids(id_items, itemids.values, id_errcodes,
				(size_t)itemids.values_num, ZBX_ITEM_GET_DEFAULT);
	}

	if (0 != hostkeys.values_num)
	{
		hk_items = (zbx_history_recv_item_t *)zbx_malloc(NULL, sizeof(zbx_history_recv_item_t) *
				(size_t)hostkeys.values_num);
		hk_errcodes = (int *)zbx_malloc(NULL, sizeof(int) * (size_t)hostkeys.values_num);
		zbx_dc_config_history_recv_get_items_by_keys(hk_items, hostkeys.values, hk_errcodes,
				(size_t)hostkeys.values_num);
	}

	/* push values */

	zbx_hashset_t		item_timestamps, item_errors;

	zbx_dc_um_handle_t	*um_handle = zbx_dc_open_user_macros();

	zbx_hashset_create(&item_timestamps, 0, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&item_errors, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_json_addstring(j, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	for (int i = 0; i < values->values_num; i++)
	{
		zbx_history_recv_item_t	*item;
		int			errcode;
		char			*error = NULL;
		zbx_item_error_t	*item_err;

		if (0 == values->values[i]->itemid)
		{
			int	index;

			if (FAIL == (index = zbx_vector_host_key_bsearch(&hostkeys, values->values[i]->hk,
					hk_compare)))
			{
				zbx_json_addobject(j, NULL);
				zbx_json_adduint64(j, ZBX_PROTO_TAG_ITEMID, 0);
				zbx_json_addstring(j, ZBX_PROTO_TAG_ERROR, "internal host/key indexing error",
						ZBX_JSON_TYPE_STRING);
				zbx_json_close(j);

				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			item = &hk_items[index];
			errcode = hk_errcodes[index];
		}
		else
		{
			int	index;

			if (FAIL == (index = zbx_vector_uint64_bsearch(&itemids, values->values[i]->itemid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				zbx_json_addobject(j, NULL);
				zbx_json_adduint64(j, ZBX_PROTO_TAG_ITEMID, values->values[i]->itemid);
				zbx_json_addstring(j, ZBX_PROTO_TAG_ERROR, "internal itemid indexing error",
						ZBX_JSON_TYPE_STRING);
				zbx_json_close(j);

				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			item = &id_items[index];
			errcode = id_errcodes[index];
		}

		zbx_json_addobject(j, NULL);

		if (SUCCEED != errcode)
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_ERROR, INVALID_ITEM_OR_NO_PERMISSION_ERROR,
					ZBX_JSON_TYPE_STRING);
		}
		else
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_ITEMID, item->itemid);

			if (NULL != (item_err = (zbx_item_error_t *)zbx_hashset_search(&item_errors, &item->itemid)))
			{
				zbx_json_addstring(j, ZBX_PROTO_TAG_ERROR, item_err->error, ZBX_JSON_TYPE_STRING);
				(*failed_num)++;
			}
			else if (SUCCEED != validate_item_config(peer_addr, client_addr, prights, user, item,
					values->values[i], &error))
			{
				zbx_item_error_t	item_err_local = {.itemid = item->itemid, .error = error};

				zbx_hashset_insert(&item_errors, &item_err_local, sizeof(item_err_local));
				zbx_json_addstring(j, ZBX_PROTO_TAG_ERROR, error, ZBX_JSON_TYPE_STRING);
				(*failed_num)++;
			}
			else if (SUCCEED != validate_item_value(&item_timestamps, item, values->values[i], &error))
			{
				zbx_json_addstring(j, ZBX_PROTO_TAG_ERROR, error, ZBX_JSON_TYPE_STRING);
				zbx_free(error);
				(*failed_num)++;
			}
			else
			{
				push_item_value_to_history(item, values->values[i]);
				(*processed_num)++;
			}
		}

		zbx_json_close(j);
	}

	zbx_dc_close_user_macros(um_handle);

	zbx_preprocessor_flush();

	/* cleanup */

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

	if (NULL != prights)
		zbx_hashset_destroy(&rights);

	zbx_vector_uint64_destroy(&itemids);
	zbx_vector_host_key_destroy(&hostkeys);
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if user has access to history.push api method              *
 *                                                                            *
 * Return value:  SUCCEED - user has access                                   *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	check_user_role_permmissions(const zbx_user_t *user)
{
#define API_METHOD		"api.method."

	int		ret = FAIL, api_access = 0, api_mode = 0, api_method = 0, history_push = 0;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() userid:" ZBX_FS_UI64 , __func__, user->userid);

	if (USER_TYPE_SUPER_ADMIN == user->type)
	{
		ret = SUCCEED;
		goto out;
	}

	result = zbx_db_select("select name,value_int,value_str from role_rule where roleid=" ZBX_FS_UI64
			" and (name='api.access' or name='api.mode' or name like 'api.method.%%')", user->roleid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		if (0 == strcmp(row[0], "api.access"))
		{
			api_access = atoi(row[1]);
		}
		else if (0 == strcmp(row[0], "api.mode"))
		{
			api_mode = atoi(row[1]);
		}
		else if (0 == strncmp(row[0], API_METHOD, ZBX_CONST_STRLEN(API_METHOD)))
		{
			api_method = 1;

			if (0 == strcmp(row[2], "history.push") || 0 == strcmp(row[2], "history.*"))
				history_push = 1;
		}
	}

	zbx_db_free_result(result);

	if (0 != api_access)
	{
		if (0 != api_method)
		{
			if (0 == api_mode)
				history_push = !history_push;

			if (0 != history_push)
				ret = SUCCEED;
		}
		else
			ret = SUCCEED;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#undef API_METHOD
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes history push request                                    *
 *                                                                            *
 * Parameters:                                                                *
 *             sock  - [IN]                                                   *
 *             jp    - [IN] history push request in json format               *
 *             j     - [OUT] json response buffer                             *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - values were pushed to history                      *
 *               FAIL    - parsing/authentication failure                     *
 *                                                                            *
 ******************************************************************************/
static int	process_history_push(zbx_socket_t *sock, const struct zbx_json_parse *jp, struct zbx_json *j,
		char **error)
{
	zbx_user_t			user;
	struct zbx_json_parse		jp_data;
	char				clientip[MAX_STRING_LEN];
	struct addrinfo			hints, *ai = NULL;
	int				ret = FAIL, hostkeys_num = 0, itemids_num = 0, processed_num = 0,
					failed_num = 0;
	const char			*pnext = NULL;
	zbx_timespec_t			ts, unique_shift = {0, 0};
	zbx_vector_hp_item_value_ptr_t	values;
	double				time_start;

	if (SUCCEED == zbx_vps_monitor_capped())
	{
		*error = zbx_strdup(NULL, "Data collection has been paused.");
		return FAIL;
	}

	time_start = zbx_time();

	zbx_vector_hp_item_value_ptr_create(&values);

	zbx_user_init(&user);

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL) ||
			SUCCEED != zbx_db_check_user_perm2system(user.userid) ||
			SUCCEED != check_user_role_permmissions(&user))
	{
		*error = zbx_strdup(NULL, "Permission denied.");
		goto out;
	}

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLIENTIP, clientip, sizeof(clientip), NULL))
	{
		*error = zbx_strdup(NULL, "Cannot find tag: \"" ZBX_PROTO_TAG_CLIENTIP "\".");
		goto out;
	}

	zbx_tcp_init_hints(&hints, SOCK_STREAM, AI_NUMERICHOST);
	if (0 != getaddrinfo(clientip, NULL, &hints, &ai))
	{
		*error = zbx_dsprintf(NULL, "Invalid tag \"" ZBX_PROTO_TAG_CLIENTIP "\" value: %s.", clientip);
		goto out;
	}

	if (FAIL == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		*error = zbx_strdup(NULL, "Cannot find tag: \"" ZBX_PROTO_TAG_DATA "\".");
		goto out;
	}

	zbx_timespec(&ts);

	while (NULL != (pnext = zbx_json_next(&jp_data, pnext)))
	{
		char			*errmsg = NULL;
		zbx_hp_item_value_t	*hp;

		if (NULL == (hp = create_item_value(pnext, &ts, &unique_shift, &errmsg)))
		{
			*error = zbx_dsprintf(NULL, "Cannot parse item #%d data: %s.", hostkeys_num + itemids_num + 1,
					errmsg);
			zbx_free(errmsg);
			goto out;
		}

		zbx_vector_hp_item_value_ptr_append(&values, hp);

		if (0 == hp->itemid)
			hostkeys_num++;
		else
			itemids_num++;
	}

	process_item_values(&user, (ZBX_SOCKADDR *)&sock->peer_info, (ZBX_SOCKADDR *)ai->ai_addr, &values, itemids_num,
			hostkeys_num, &processed_num, &failed_num, j);

	zbx_auditlog_history_push(user.userid, user.username, clientip, processed_num, failed_num,
			zbx_time() - time_start);

	ret = SUCCEED;
out:
	if (NULL != ai)
		freeaddrinfo(ai);

	zbx_user_free(&user);

	zbx_vector_hp_item_value_ptr_clear_ext(&values, hp_item_value_free);
	zbx_vector_hp_item_value_ptr_destroy(&values);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes history push request                                    *
 *                                                                            *
 * Parameters: sock    - [IN] client socket                                   *
 *             jp      - [IN] history push request in json format             *
 *             timeout - [IN] communication timeout                           *
 *                                                                            *
 * Return value: SUCCEED - values were pushed to history                      *
 *               FAIL    - parsing/authentication failure                     *
 *                                                                            *
 ******************************************************************************/
int	trapper_process_history_push(zbx_socket_t *sock, const struct zbx_json_parse *jp, int timeout)
{
	char		*error = NULL;
	int		ret;
	struct zbx_json	j;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_json_init(&j, 1024);

	if (SUCCEED != (ret = process_history_push(sock, jp, &j, &error)))
	{
		zbx_send_response(sock, ret, error, timeout);
		zbx_free(error);
	}
	else
		zbx_tcp_send(sock, j.buffer);

	zbx_json_clean(&j);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#undef INVALID_ITEM_OR_NO_PERMISSION_ERROR
