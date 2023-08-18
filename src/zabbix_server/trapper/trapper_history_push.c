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
#include "audit/zbxaudit.h"

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
 * Purpose: create item value from json data                                  *
 *                                                                            *
 * Parameters: pnext     - [IN] json data                                     *
 *             now       - [IN] current time                                  *
 *             ns_offset - [IN/OUT] nanosecond offset to apply when json data *
 *                         does not include ns tag                            *
 *             error     - [OUT] error message                                *
 *                                                                            *
 * Return value: The created value or NULL in the case of an error            *
 *                                                                            *
 ******************************************************************************/
static zbx_hp_item_value_t	*create_item_value(const char *pnext, time_t now, int *ns_offset, char **error)
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
		hp->ts.ns = (*ns_offset)++;
	}

	if (0 == hp->ts.sec)
		hp->ts.sec = (int)now;

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
				" host:%s key:%s ts:%d.%09d value:%s error:%s",
				__func__, zbx_result_string(ret), hp->itemid, ZBX_NULL2EMPTY_STR(hp->hk.host),
				ZBX_NULL2EMPTY_STR(hp->hk.key), hp->ts.sec, hp->ts.ns, ZBX_NULL2EMPTY_STR(hp->value),
				ZBX_NULL2EMPTY_STR(*error));
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
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

/******************************************************************************
 *                                                                            *
 * Purpose: validate item configuration if it can accept history              *
 *                                                                            *
 * Return value: SUCCEED - item can accept history values                     *
 *               FAIL    - item configuration error                           *
 *                                                                            *
 ******************************************************************************/
static int	validate_item_config(const struct addrinfo *ai, zbx_uint64_t userid, zbx_history_recv_item_t *item,
		const zbx_hp_item_value_t *value, char **error)
{
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

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate item value for duplicate timestamps                      *
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
 * Purpose: send item value to preprocessing                                  *
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
 * Purpose: process received item values                                      *
 *                                                                            *
 * Parameters: userid        - [IN] user identifier                           *
 *             ai            - [IN] clientip converted to addrinfo            *
 *             values        - [IN] parsed values                             *
 *             itemids_num   - [IN] values with itemids                       *
 *             hostkeys_num  - [IN] values with host/key pairs                *
 *             processed_num - [OUT] number of processed values               *
 *             failed_num    - [OUT] number of failed values                  *
 *             j             - [OUT] json response buffer                     *
 *                                                                            *
 * Return value: SUCCEED - values were pushed to history                      *
 *               FAIL    - parsing failure                                    *
 *                                                                            *
 ******************************************************************************/
static void	process_item_values(zbx_uint64_t userid, const struct addrinfo *ai,
		zbx_vector_hp_item_value_ptr_t *values, int itemids_num, int hostkeys_num, int *processed_num,
		int *failed_num, struct zbx_json *j)
{
	/* extract unique itemids/hostkeys */

	zbx_vector_uint64_t	itemids;
	zbx_vector_host_key_t	hostkeys;

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

			if (FAIL == (index = zbx_vector_host_key_bsearch(&hostkeys, values->values[i]->hk, hk_compare)))
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
				zbx_json_adduint64(j, ZBX_PROTO_TAG_ITEMID, 0);
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
			zbx_json_addstring(j, ZBX_PROTO_TAG_ERROR, "Invalid item.", ZBX_JSON_TYPE_STRING);
		}
		else
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_ITEMID, item->itemid);

			if (NULL != (item_err = (zbx_item_error_t *)zbx_hashset_search(&item_errors, &item->itemid)))
			{
				zbx_json_addstring(j, ZBX_PROTO_TAG_ERROR, item_err->error, ZBX_JSON_TYPE_STRING);
				(*failed_num)++;
			}
			else if (SUCCEED != validate_item_config(ai, userid, item, values->values[i], &error))
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

	zbx_vector_uint64_destroy(&itemids);
	zbx_vector_host_key_destroy(&hostkeys);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process history push request                                      *
 *                                                                            *
 * Parameters: jp    - [IN] history push request in json format               *
 *             j     - [OUT] json response buffer                             *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - values were pushed to history                      *
 *               FAIL    - parsing/authentication failure                     *
 *                                                                            *
 ******************************************************************************/
static int	process_history_push(const struct zbx_json_parse *jp, struct zbx_json *j, char **error)
{
	zbx_user_t			user;
	struct zbx_json_parse		jp_data;
	int				ret = FAIL;
	char				clientip[MAX_STRING_LEN];
	struct addrinfo			hints, *ai = NULL;
	int				ns_offset = 0, hostkeys_num = 0, itemids_num = 0, processed_num = 0,
					failed_num = 0;
	const char			*pnext = NULL;
	time_t				now;
	zbx_vector_hp_item_value_ptr_t	values;
	double				time_start;

	time_start = zbx_time();

	zbx_vector_hp_item_value_ptr_create(&values);

	zbx_user_init(&user);

	if (FAIL == zbx_get_user_from_json(jp, &user, NULL))
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

	now = time(NULL);

	while (NULL != (pnext = zbx_json_next(&jp_data, pnext)))
	{
		char			*errmsg = NULL;
		zbx_hp_item_value_t	*hp;

		if (NULL == (hp = create_item_value(pnext, now, &ns_offset, &errmsg)))
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

	process_item_values(user.userid, ai, &values, itemids_num, hostkeys_num, &processed_num, &failed_num, j);

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
 * Purpose: process history push request                                      *
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

	if (SUCCEED != (ret = process_history_push(jp, &j, &error)))
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
