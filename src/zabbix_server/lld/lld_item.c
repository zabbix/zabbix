/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "lld.h"

#include "lld_audit.h"

#include "zbx_item_constants.h"
#include "zbxexpression.h"
#include "zbxregexp.h"
#include "zbxprometheus.h"
#include "zbxxml.h"
#include "zbxnum.h"
#include "zbxdbwrap.h"
#include "zbxhttp.h"
#include "zbxvariant.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_item.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxeval.h"
#include "zbxexpr.h"
#include "zbxjson.h"
#include "zbxstr.h"
#include "zbxtime.h"
#include "../server_constants.h"

ZBX_PTR_VECTOR_IMPL(lld_item_full_ptr, zbx_lld_item_full_t*)

ZBX_PTR_VECTOR_IMPL(lld_item_preproc_ptr, zbx_lld_item_preproc_t*)

static zbx_lld_item_preproc_t	*zbx_init_lld_item_preproc(zbx_uint64_t item_preprocid, zbx_uint64_t flags, int step,
		int type, const char *params, int error_handler, const char *error_handler_params)
{
	zbx_lld_item_preproc_t	*preproc_op;

	preproc_op = (zbx_lld_item_preproc_t *)zbx_malloc(NULL, sizeof(zbx_lld_item_preproc_t));
	preproc_op->flags = flags;
	preproc_op->item_preprocid = item_preprocid;
	preproc_op->step = step;
	preproc_op->type = type;
	/* Note: temporary initialization to 0 which is not a valid value for 'type'. */
	/* Must be set later when the value is known. */
	preproc_op->type_orig = 0;
	preproc_op->params = zbx_strdup(NULL, params);
	preproc_op->params_orig = NULL;
	preproc_op->error_handler = error_handler;
	/* Note: temporary initialization to 0. Must be set later when the value is known. */
	preproc_op->error_handler_orig = ZBX_PREPROC_FAIL_DEFAULT;
	preproc_op->error_handler_params = zbx_strdup(NULL, error_handler_params);
	preproc_op->error_handler_params_orig = NULL;

	return preproc_op;
}

/* items index hashset support functions */
static zbx_hash_t	lld_item_index_hash_func(const void *data)
{
	const zbx_lld_item_index_t	*item_index = (const zbx_lld_item_index_t *)data;
	zbx_hash_t			hash;

	hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&item_index->parent_itemid,
			sizeof(item_index->parent_itemid), ZBX_DEFAULT_HASH_SEED);
	return ZBX_DEFAULT_PTR_HASH_ALGO(&item_index->lld_row, sizeof(item_index->lld_row), hash);
}

static int	lld_item_index_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_item_index_t	*i1 = (const zbx_lld_item_index_t *)d1;
	const zbx_lld_item_index_t	*i2 = (const zbx_lld_item_index_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(i1->parent_itemid, i2->parent_itemid);
	ZBX_RETURN_IF_NOT_EQUAL(i1->lld_row, i2->lld_row);

	return 0;
}
static int	lld_item_preproc_sort_by_step(const void *d1, const void *d2)
{
	zbx_lld_item_preproc_t	*op1 = *(zbx_lld_item_preproc_t **)d1;
	zbx_lld_item_preproc_t	*op2 = *(zbx_lld_item_preproc_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(op1->step, op2->step);
	return 0;
}

/* items index hashset support functions */
static zbx_hash_t	lld_item_ref_key_hash_func(const void *data)
{
	const zbx_lld_item_ref_t	*ref = (const zbx_lld_item_ref_t *)data;

	return ZBX_DEFAULT_STRING_HASH_FUNC(ref->item->key_);
}

static int	lld_item_ref_key_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_item_ref_t	*ref1 = (const zbx_lld_item_ref_t *)d1;
	const zbx_lld_item_ref_t	*ref2 = (const zbx_lld_item_ref_t *)d2;

	return strcmp(ref1->item->key_, ref2->item->key_);
}

static void	lld_item_preproc_free(zbx_lld_item_preproc_t *op)
{
	zbx_free(op->params);
	if (0 != (op->flags & ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_PARAMS))
		zbx_free(op->params_orig);
	zbx_free(op->error_handler_params);
	if (0 != (op->flags & ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_ERROR_HANDLER_PARAMS))
		zbx_free(op->error_handler_params_orig);
	zbx_free(op);
}

static void	lld_item_prototype_free(zbx_lld_item_prototype_t *item_prototype)
{
	zbx_free(item_prototype->name);
	zbx_free(item_prototype->key);
	zbx_free(item_prototype->delay);
	zbx_free(item_prototype->history);
	zbx_free(item_prototype->trends);
	zbx_free(item_prototype->trapper_hosts);
	zbx_free(item_prototype->units);
	zbx_free(item_prototype->formula);
	zbx_free(item_prototype->logtimefmt);
	zbx_free(item_prototype->params);
	zbx_free(item_prototype->ipmi_sensor);
	zbx_free(item_prototype->snmp_oid);
	zbx_free(item_prototype->username);
	zbx_free(item_prototype->password);
	zbx_free(item_prototype->publickey);
	zbx_free(item_prototype->privatekey);
	zbx_free(item_prototype->description);
	zbx_free(item_prototype->jmx_endpoint);
	zbx_free(item_prototype->timeout);
	zbx_free(item_prototype->url);
	zbx_free(item_prototype->query_fields);
	zbx_free(item_prototype->posts);
	zbx_free(item_prototype->status_codes);
	zbx_free(item_prototype->http_proxy);
	zbx_free(item_prototype->headers);
	zbx_free(item_prototype->ssl_cert_file);
	zbx_free(item_prototype->ssl_key_file);
	zbx_free(item_prototype->ssl_key_password);

	zbx_vector_lld_row_ptr_destroy(&item_prototype->lld_rows);

	zbx_vector_lld_item_preproc_ptr_clear_ext(&item_prototype->preproc_ops, lld_item_preproc_free);
	zbx_vector_lld_item_preproc_ptr_destroy(&item_prototype->preproc_ops);

	zbx_vector_item_param_ptr_clear_ext(&item_prototype->item_params, zbx_item_param_free);
	zbx_vector_item_param_ptr_destroy(&item_prototype->item_params);

	zbx_vector_db_tag_ptr_clear_ext(&item_prototype->item_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&item_prototype->item_tags);

	zbx_hashset_destroy(&item_prototype->item_index);

	zbx_vector_str_destroy(&item_prototype->keys);

	zbx_free(item_prototype);
}

static void	lld_item_free(zbx_lld_item_full_t *item)
{
	zbx_free(item->key_proto);
	zbx_free(item->name);
	zbx_free(item->name_proto);
	zbx_free(item->key_);
	zbx_free(item->key_orig);
	zbx_free(item->delay);
	zbx_free(item->delay_orig);
	zbx_free(item->history);
	zbx_free(item->history_orig);
	zbx_free(item->trends);
	zbx_free(item->trends_orig);
	zbx_free(item->units);
	zbx_free(item->units_orig);
	zbx_free(item->params);
	zbx_free(item->params_orig);
	zbx_free(item->ipmi_sensor);
	zbx_free(item->ipmi_sensor_orig);
	zbx_free(item->snmp_oid);
	zbx_free(item->snmp_oid_orig);
	zbx_free(item->username);
	zbx_free(item->username_orig);
	zbx_free(item->password);
	zbx_free(item->password_orig);
	zbx_free(item->description);
	zbx_free(item->description_orig);
	zbx_free(item->jmx_endpoint);
	zbx_free(item->jmx_endpoint_orig);
	zbx_free(item->timeout);
	zbx_free(item->timeout_orig);
	zbx_free(item->url);
	zbx_free(item->url_orig);
	zbx_free(item->query_fields);
	zbx_free(item->query_fields_orig);
	zbx_free(item->posts);
	zbx_free(item->posts_orig);
	zbx_free(item->status_codes);
	zbx_free(item->status_codes_orig);
	zbx_free(item->http_proxy);
	zbx_free(item->http_proxy_orig);
	zbx_free(item->headers);
	zbx_free(item->headers_orig);
	zbx_free(item->ssl_cert_file);
	zbx_free(item->ssl_cert_file_orig);
	zbx_free(item->ssl_key_file);
	zbx_free(item->ssl_key_file_orig);
	zbx_free(item->ssl_key_password);
	zbx_free(item->ssl_key_password_orig);
	zbx_free(item->trapper_hosts_orig);
	zbx_free(item->formula_orig);
	zbx_free(item->logtimefmt_orig);
	zbx_free(item->publickey_orig);
	zbx_free(item->privatekey_orig);

	zbx_vector_lld_item_preproc_ptr_clear_ext(&item->preproc_ops, lld_item_preproc_free);
	zbx_vector_lld_item_preproc_ptr_destroy(&item->preproc_ops);
	zbx_vector_item_param_ptr_clear_ext(&item->item_params, zbx_item_param_free);
	zbx_vector_item_param_ptr_destroy(&item->item_params);
	zbx_vector_db_tag_ptr_clear_ext(&item->item_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&item->item_tags);
	zbx_vector_lld_item_full_ptr_destroy(&item->dependent_items);

	zbx_vector_db_tag_ptr_destroy(&item->override_tags);

	zbx_free(item);
}

typedef struct
{
	zbx_uint64_t			itemid;
	zbx_uint64_t			parent_itemid;
	const zbx_lld_item_prototype_t	*prototype;
	char				*key_proto;
	int				lastcheck;
	unsigned char			discovery_status;
	int				ts_delete;
	int				ts_disable;
	unsigned char			disable_source;
}
zbx_item_discovery_t;

ZBX_PTR_VECTOR_DECL(item_discovery_ptr, zbx_item_discovery_t *)
ZBX_PTR_VECTOR_IMPL(item_discovery_ptr, zbx_item_discovery_t *)

static int	item_discovery_compare_func(const void *d1, const void *d2)
{
	const zbx_item_discovery_t	*item_discovery_1 = *(const zbx_item_discovery_t **)d1;
	const zbx_item_discovery_t	*item_discovery_2 = *(const zbx_item_discovery_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(item_discovery_1->itemid, item_discovery_2->itemid);

	return 0;
}

static void	zbx_item_discovery_free(zbx_item_discovery_t *data)
{
	zbx_free(data->key_proto);
	zbx_free(data);
}

static void	add_batch_select_condition(char **sql, size_t *sql_alloc, size_t *sql_offset, const char* column,
		const zbx_vector_uint64_t *itemids, int *index)
{
	int	new_index = *index + ZBX_DB_LARGE_QUERY_BATCH_SIZE;

	if (new_index > itemids->values_num)
		new_index = itemids->values_num;

	zbx_db_add_condition_alloc(sql, sql_alloc, sql_offset, column,
			itemids->values + *index, new_index - *index);

	*index = new_index;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Retrieves existing items for the specified item prototypes.       *
 *                                                                            *
 * Parameters: item_prototypes - [IN]                                         *
 *             items           - [OUT]                                        *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_get(const zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		zbx_vector_lld_item_full_ptr_t *items)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_lld_item_full_t		*item, *master;
	zbx_lld_item_preproc_t		*preproc_op;
	const zbx_lld_item_prototype_t	*item_prototype;
	zbx_uint64_t			db_valuemapid, db_interfaceid, itemid, master_itemid;
	zbx_vector_uint64_t		parent_itemids;
	int				index, batch_index;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_item_discovery_ptr_t	item_discoveries;
	zbx_vector_uint64_t		itemids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&parent_itemids);
	zbx_vector_uint64_reserve(&parent_itemids, item_prototypes->values_num);
	zbx_vector_item_discovery_ptr_create(&item_discoveries);
	zbx_vector_uint64_create(&itemids);

	for (int i = 0; i < item_prototypes->values_num; i++)
	{
		item_prototype = item_prototypes->values[i];

		zbx_vector_uint64_append(&parent_itemids, item_prototype->itemid);
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select itemid,key_,lastcheck,status,ts_delete,ts_disable,disable_source,parent_itemid"
			" from item_discovery"
			" where");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "parent_itemid", parent_itemids.values,
			parent_itemids.values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t		parent_id;
		zbx_item_discovery_t	*item_discovery;

		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UINT64(parent_id, row[7]);

		zbx_lld_item_prototype_t	cmp = {.itemid = parent_id};

		if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &cmp,
				lld_item_prototype_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_vector_uint64_append(&itemids, itemid);

		item_discovery = (zbx_item_discovery_t *)zbx_malloc(NULL, sizeof(zbx_item_discovery_t));

		item_discovery->itemid = itemid;
		item_discovery->parent_itemid = parent_id;
		item_discovery->prototype = item_prototypes->values[index];
		item_discovery->key_proto = zbx_strdup(NULL, row[1]);
		item_discovery->lastcheck = atoi(row[2]);
		ZBX_STR2UCHAR(item_discovery->discovery_status, row[3]);
		item_discovery->ts_delete = atoi(row[4]);
		item_discovery->ts_disable = atoi(row[5]);
		ZBX_STR2UCHAR(item_discovery->disable_source, row[6]);

		zbx_vector_item_discovery_ptr_append(&item_discoveries, item_discovery);
	}

	zbx_db_free_result(result);

	if (0 == item_discoveries.values_num)
		goto out;

	zbx_vector_item_discovery_ptr_sort(&item_discoveries, item_discovery_compare_func);
	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	batch_index = 0;

	while (batch_index < itemids.values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select itemid,name,key_,type,value_type,delay,history,trends,trapper_hosts,units,"
					"formula,logtimefmt,valuemapid,params,ipmi_sensor,snmp_oid,authtype,username,"
					"password,publickey,privatekey,description,interfaceid,jmx_endpoint,"
					"master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,"
					"post_type,http_proxy,headers,retrieve_mode,request_method,output_format,"
					"ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,"
					"allow_traps,status"
				" from items"
				" where");

		add_batch_select_condition(&sql, &sql_alloc, &sql_offset, "itemid", &itemids, &batch_index);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			const zbx_item_discovery_t	*item_discovery;

			ZBX_STR2UINT64(itemid, row[0]);

			const zbx_item_discovery_t	cmp = {.itemid = itemid};

			if (FAIL == (index = zbx_vector_item_discovery_ptr_bsearch(&item_discoveries, &cmp,
					item_discovery_compare_func)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			item_discovery = item_discoveries.values[index];
			item_prototype = item_discovery->prototype;

			item = (zbx_lld_item_full_t *)zbx_malloc(NULL, sizeof(zbx_lld_item_full_t));

			item->itemid = item_discovery->itemid;
			item->parent_itemid = item_discovery->parent_itemid;
			item->key_proto = zbx_strdup(NULL, item_discovery->key_proto);
			item->lastcheck = item_discovery->lastcheck;
			item->discovery_status = item_discovery->discovery_status;
			item->ts_delete = item_discovery->ts_delete;
			item->ts_disable = item_discovery->ts_disable;
			item->disable_source = item_discovery->disable_source;

			item->name = zbx_strdup(NULL, row[1]);
			item->name_proto = NULL;
			item->key_ = zbx_strdup(NULL, row[2]);
			item->key_orig = NULL;
			item->flags = ZBX_FLAG_LLD_ITEM_UNSET;

			item->type = item_prototype->type;

			if ((unsigned char)atoi(row[3]) != item_prototype->type)
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_TYPE;
				item->type_orig = (unsigned char)atoi(row[3]);
			}

			if ((unsigned char)atoi(row[4]) != item_prototype->value_type)
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_VALUE_TYPE;
				item->value_type_orig = (unsigned char)atoi(row[4]);
			}

			item->delay = zbx_strdup(NULL, row[5]);
			item->delay_orig = NULL;

			item->history = zbx_strdup(NULL, row[6]);
			item->history_orig = NULL;

			item->trends = zbx_strdup(NULL, row[7]);
			item->trends_orig = NULL;

			item->trapper_hosts_orig = NULL;
			if (0 != strcmp(row[8], item_prototype->trapper_hosts))
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_TRAPPER_HOSTS;
				item->trapper_hosts_orig = zbx_strdup(NULL, row[8]);
			}

			item->units = zbx_strdup(NULL, row[9]);
			item->units_orig = NULL;

			item->formula_orig = NULL;
			if (0 != strcmp(row[10], item_prototype->formula))
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_FORMULA;
				item->formula_orig = zbx_strdup(NULL, row[10]);
			}

			item->logtimefmt_orig = NULL;
			if (0 != strcmp(row[11], item_prototype->logtimefmt))
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_LOGTIMEFMT;
				item->logtimefmt_orig = zbx_strdup(NULL, row[11]);
			}

			ZBX_DBROW2UINT64(db_valuemapid, row[12]);
			if (db_valuemapid != item_prototype->valuemapid)
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_VALUEMAPID;
				item->valuemapid_orig = db_valuemapid;
			}

			item->params = zbx_strdup(NULL, row[13]);
			item->params_orig = NULL;

			item->ipmi_sensor = zbx_strdup(NULL, row[14]);
			item->ipmi_sensor_orig = NULL;

			item->snmp_oid = zbx_strdup(NULL, row[15]);
			item->snmp_oid_orig = NULL;

			if ((unsigned char)atoi(row[16]) != item_prototype->authtype)
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_AUTHTYPE;
				item->authtype_orig = (unsigned char)atoi(row[16]);
			}

			item->username = zbx_strdup(NULL, row[17]);
			item->username_orig = NULL;

			item->password = zbx_strdup(NULL, row[18]);
			item->password_orig = NULL;

			item->publickey_orig = NULL;

			if (0 != strcmp(row[19], item_prototype->publickey))
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PUBLICKEY;
				item->publickey_orig = zbx_strdup(NULL, row[19]);
			}

			item->privatekey_orig = NULL;

			if (0 != strcmp(row[20], item_prototype->privatekey))
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PRIVATEKEY;
				item->privatekey_orig = zbx_strdup(NULL, row[20]);
			}

			item->description = zbx_strdup(NULL, row[21]);
			item->description_orig = NULL;

			ZBX_DBROW2UINT64(db_interfaceid, row[22]);

			if (db_interfaceid != item_prototype->interfaceid)
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_INTERFACEID;
				item->interfaceid_orig = db_interfaceid;
			}

			item->jmx_endpoint = zbx_strdup(NULL, row[23]);
			item->jmx_endpoint_orig = NULL;

			ZBX_DBROW2UINT64(item->master_itemid, row[24]);

			item->timeout = zbx_strdup(NULL, row[25]);
			item->timeout_orig = NULL;

			item->url = zbx_strdup(NULL, row[26]);
			item->url_orig = NULL;

			item->query_fields = zbx_strdup(NULL, row[27]);
			item->query_fields_orig = NULL;

			item->posts = zbx_strdup(NULL, row[28]);
			item->posts_orig = NULL;

			item->status_codes = zbx_strdup(NULL, row[29]);
			item->status_codes_orig = NULL;

			if ((unsigned char)atoi(row[30]) != item_prototype->follow_redirects)
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_FOLLOW_REDIRECTS;
				item->follow_redirects_orig = (unsigned char)atoi(row[30]);
			}

			if ((unsigned char)atoi(row[31]) != item_prototype->post_type)
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_POST_TYPE;
				item->post_type_orig = (unsigned char)atoi(row[31]);
			}

			item->http_proxy = zbx_strdup(NULL, row[32]);
			item->http_proxy_orig = NULL;

			item->headers = zbx_strdup(NULL, row[33]);
			item->headers_orig = NULL;

			if ((unsigned char)atoi(row[34]) != item_prototype->retrieve_mode)
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_RETRIEVE_MODE;
				item->retrieve_mode_orig = (unsigned char)atoi(row[34]);
			}

			if ((unsigned char)atoi(row[35]) != item_prototype->request_method)
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_REQUEST_METHOD;
				item->request_method_orig = (unsigned char)atoi(row[35]);
			}

			if ((unsigned char)atoi(row[36]) != item_prototype->output_format)
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_OUTPUT_FORMAT;
				item->output_format_orig = (unsigned char)atoi(row[36]);
			}

			item->ssl_cert_file = zbx_strdup(NULL, row[37]);
			item->ssl_cert_file_orig = NULL;

			item->ssl_key_file = zbx_strdup(NULL, row[38]);
			item->ssl_key_file_orig = NULL;

			item->ssl_key_password = zbx_strdup(NULL, row[39]);
			item->ssl_key_password_orig = NULL;

			if ((unsigned char)atoi(row[40]) != item_prototype->verify_peer)
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_VERIFY_PEER;
				item->verify_peer_orig = (unsigned char)atoi(row[40]);
			}

			if ((unsigned char)atoi(row[41]) != item_prototype->verify_host)
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_VERIFY_HOST;
				item->verify_host_orig = (unsigned char)atoi(row[41]);
			}

			if ((unsigned char)atoi(row[42]) != item_prototype->allow_traps)
			{
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_ALLOW_TRAPS;
				item->allow_traps_orig = (unsigned char)atoi(row[42]);
			}

			ZBX_STR2UCHAR(item->status, row[43]);

			item->lld_row = NULL;

			zbx_vector_lld_item_preproc_ptr_create(&item->preproc_ops);
			zbx_vector_lld_item_full_ptr_create(&item->dependent_items);
			zbx_vector_item_param_ptr_create(&item->item_params);
			zbx_vector_db_tag_ptr_create(&item->item_tags);
			zbx_vector_db_tag_ptr_create(&item->override_tags);

			zbx_vector_lld_item_full_ptr_append(items, item);
		}

		zbx_db_free_result(result);
	}

	if (0 == items->values_num)
		goto out;

	zbx_vector_lld_item_full_ptr_sort(items, lld_item_full_compare_func);

	for (int i = items->values_num - 1; i >= 0; i--)
	{
		item = items->values[i];
		master_itemid = item->master_itemid;

		zbx_lld_item_full_t	item_full_cmp = {.itemid = master_itemid};

		if (0 != master_itemid && FAIL != (index = zbx_vector_lld_item_full_ptr_bsearch(items, &item_full_cmp,
				lld_item_full_compare_func)))
		{
			/* dependent items based on prototypes should contain prototype itemid */
			master = items->values[index];
			master_itemid = master->parent_itemid;
		}

		zbx_lld_item_prototype_t	item_proto_cmp = {.itemid = item->parent_itemid};

		if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &item_proto_cmp,
				lld_item_prototype_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_prototype = item_prototypes->values[index];

		if (master_itemid != item_prototype->master_itemid)
		{
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_MASTER_ITEM;
			item->master_itemid_orig = master_itemid;
		}

		item->master_itemid = item_prototype->master_itemid;
	}

	batch_index = 0;

	while (batch_index < itemids.values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select item_preprocid,itemid,step,type,params,error_handler,error_handler_params"
				" from item_preproc"
				" where");

		add_batch_select_condition(&sql, &sql_alloc, &sql_offset, "itemid", &itemids, &batch_index);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	item_preprocid;

			ZBX_STR2UINT64(itemid, row[1]);

			zbx_lld_item_full_t	cmp = {.itemid = itemid};

			if (FAIL == (index = zbx_vector_lld_item_full_ptr_bsearch(items, &cmp,
					lld_item_full_compare_func)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			item = items->values[index];
			ZBX_STR2UINT64(item_preprocid, row[0]);
			preproc_op = zbx_init_lld_item_preproc(item_preprocid, ZBX_FLAG_LLD_ITEM_PREPROC_UNSET,
					atoi(row[2]), atoi(row[3]), row[4], atoi(row[5]), row[6]);
			zbx_vector_lld_item_preproc_ptr_append(&item->preproc_ops, preproc_op);
		}
		zbx_db_free_result(result);
	}

	batch_index = 0;

	while (batch_index < itemids.values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select item_parameterid,itemid,name,value"
				" from item_parameter"
				" where");

		add_batch_select_condition(&sql, &sql_alloc, &sql_offset, "itemid", &itemids, &batch_index);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_item_param_t	*item_param;

			ZBX_STR2UINT64(itemid, row[1]);

			zbx_lld_item_full_t	cmp = {.itemid = itemid};

			if (FAIL == (index = zbx_vector_lld_item_full_ptr_bsearch(items, &cmp,
					lld_item_full_compare_func)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			item = items->values[index];
			item_param = zbx_item_param_create(row[2], row[3]);
			ZBX_STR2UINT64(item_param->item_parameterid, row[0]);
			zbx_vector_item_param_ptr_append(&item->item_params, item_param);
		}
		zbx_db_free_result(result);
	}

	batch_index = 0;

	while (batch_index < itemids.values_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select itemtagid,itemid,tag,value"
				" from item_tag"
				" where");

		add_batch_select_condition(&sql, &sql_alloc, &sql_offset, "itemid", &itemids, &batch_index);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_db_tag_t	*db_tag;

			ZBX_STR2UINT64(itemid, row[1]);

			zbx_lld_item_full_t	cmp = {.itemid = itemid};

			if (FAIL == (index = zbx_vector_lld_item_full_ptr_bsearch(items, &cmp,
					lld_item_full_compare_func)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			item = items->values[index];

			db_tag = zbx_db_tag_create(row[2], row[3]);
			ZBX_STR2UINT64(db_tag->tagid, row[0]);
			zbx_vector_db_tag_ptr_append(&item->item_tags, db_tag);
		}

		zbx_db_free_result(result);
	}
out:
	zbx_free(sql);
	zbx_vector_uint64_destroy(&parent_itemids);
	zbx_vector_item_discovery_ptr_clear_ext(&item_discoveries, zbx_item_discovery_free);
	zbx_vector_item_discovery_ptr_destroy(&item_discoveries);
	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if string is user macro                                    *
 *                                                                            *
 * Parameters: str - [IN] string to validate                                  *
 *                                                                            *
 * Returns: SUCCEED - either "{$MACRO}" or "{$MACRO:"{#MACRO}"}"              *
 *          FAIL    - not user macro or contains other characters for example:*
 *                    "dummy{$MACRO}", "{$MACRO}dummy" or "{$MACRO}{$MACRO}"  *
 *                                                                            *
 ******************************************************************************/
static int	is_user_macro(const char *str)
{
	zbx_token_t	token;

	if (FAIL == zbx_token_find(str, 0, &token, ZBX_TOKEN_SEARCH_BASIC) ||
			0 == (token.type & ZBX_TOKEN_USER_MACRO) ||
			0 != token.loc.l || '\0' != str[token.loc.r + 1])
	{
		return FAIL;
	}

	return SUCCEED;
}

static void	lld_validate_item_field(zbx_lld_item_full_t *item, char **field, char **field_orig, zbx_uint64_t flag,
		size_t field_len, char **error)
{
	if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
		return;

	/* only new items or items with changed data or item type will be validated */
	if (0 != item->itemid && 0 == (item->flags & flag) && 0 == (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_TYPE))
		return;

	if (SUCCEED != zbx_is_utf8(*field))
	{
		zbx_replace_invalid_utf8(*field);
		*error = zbx_strdcatf(*error, "Cannot %s item: value \"%s\" has invalid UTF-8 sequence.\n",
				(0 != item->itemid ? "update" : "create"), *field);
	}
	else if (zbx_strlen_utf8(*field) > field_len)
	{
		const char	*err_val;
		char		key_short[VALUE_ERRMSG_MAX * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

		if (0 != (flag & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
			err_val = zbx_truncate_itemkey(*field, VALUE_ERRMSG_MAX, key_short, sizeof(key_short));
		else
			err_val = zbx_truncate_value(*field, VALUE_ERRMSG_MAX, key_short, sizeof(key_short));

		*error = zbx_strdcatf(*error, "Cannot %s item: value \"%s\" is too long.\n",
				(0 != item->itemid ? "update" : "create"), err_val);
	}
	else
	{
		int	value;
		char	*errmsg = NULL;

		switch (flag)
		{
			case ZBX_FLAG_LLD_ITEM_UPDATE_NAME:
				if ('\0' != **field)
					return;

				*error = zbx_strdcatf(*error, "Cannot %s item: name is empty.\n",
						(0 != item->itemid ? "update" : "create"));
				break;
			case ZBX_FLAG_LLD_ITEM_UPDATE_DELAY:
				switch (item->type)
				{
					case ITEM_TYPE_TRAPPER:
					case ITEM_TYPE_SNMPTRAP:
					case ITEM_TYPE_DEPENDENT:
						return;
					case ITEM_TYPE_ZABBIX_ACTIVE:
						if (0 == strncmp(item->key_, "mqtt.get[",
								ZBX_CONST_STRLEN("mqtt.get[")))
						{
							return;
						}
				}

				if (SUCCEED == zbx_validate_interval(*field, &errmsg))
					return;

				*error = zbx_strdcatf(*error, "Cannot %s item: %s\n",
						(0 != item->itemid ? "update" : "create"), errmsg);
				zbx_free(errmsg);

				/* delay alone cannot be rolled back as it depends on item type, revert all updates */
				if (0 != item->itemid)
				{
					item->flags &= ZBX_FLAG_LLD_ITEM_DISCOVERED;
					return;
				}
				break;
			case ZBX_FLAG_LLD_ITEM_UPDATE_HISTORY:
				if (SUCCEED == is_user_macro(*field))
					return;

				if (SUCCEED == zbx_is_time_suffix(*field, &value, ZBX_LENGTH_UNLIMITED) && (0 ==
						value || (ZBX_HK_HISTORY_MIN <= value && ZBX_HK_PERIOD_MAX >= value)))
				{
					return;
				}

				*error = zbx_strdcatf(*error, "Cannot %s item: invalid history storage period"
						" \"%s\".\n", (0 != item->itemid ? "update" : "create"), *field);
				break;
			case ZBX_FLAG_LLD_ITEM_UPDATE_TRENDS:
				if (SUCCEED == is_user_macro(*field))
					return;

				if (SUCCEED == zbx_is_time_suffix(*field, &value, ZBX_LENGTH_UNLIMITED) && (0 ==
						value || (ZBX_HK_TRENDS_MIN <= value && ZBX_HK_PERIOD_MAX >= value)))
				{
					return;
				}

				*error = zbx_strdcatf(*error, "Cannot %s item: invalid trends storage period"
						" \"%s\".\n", (0 != item->itemid ? "update" : "create"), *field);
				break;
			default:
				return;
		}
	}

	if (0 != item->itemid)
		lld_field_str_rollback(field, field_orig, &item->flags, flag);
	else
		item->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Validates an item preprocessing step expressions for discovery    *
 *          process.                                                          *
 *                                                                            *
 * Parameters: pp       - [IN] item preprocessing step                        *
 *             itemid   - [IN] item id for logging                            *
 *             error    - [OUT] error message                                 *
 *                                                                            *
 * Return value: SUCCEED - if preprocessing step is valid                     *
 *               FAIL    - if preprocessing step is not valid                 *
 *                                                                            *
 ******************************************************************************/
static int	lld_items_preproc_step_validate(const zbx_lld_item_preproc_t * pp, zbx_uint64_t itemid, char ** error)
{
	int		ret = SUCCEED;
	zbx_token_t	token;
	char		err[MAX_STRING_LEN], *errmsg = NULL;
	char		param1[ZBX_ITEM_PREPROC_PARAMS_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1], *param2, *param3;
	char		*regexp_err = NULL;
	zbx_uint64_t	value_ui64;
	zbx_jsonpath_t	jsonpath;

	*err = '\0';

	if (FAIL == zbx_db_validate_field_size("item_preproc", "params", pp->params))
	{
		const char	*err_val;
		char		key_short[VALUE_ERRMSG_MAX * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

		err_val = zbx_truncate_value(pp->params, VALUE_ERRMSG_MAX, key_short, sizeof(key_short));
		zbx_snprintf(err, sizeof(err), "parameter \"%s\" is too long.", err_val);
		ret = FAIL;
		goto out;
	}

	if (0 == (pp->flags & ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE)
			|| (SUCCEED == zbx_token_find(pp->params, 0, &token, ZBX_TOKEN_SEARCH_BASIC)
			&& 0 != (token.type & ZBX_TOKEN_USER_MACRO)))
	{
		return SUCCEED;
	}

	switch (pp->type)
	{
		case ZBX_PREPROC_REGSUB:
			/* break; is not missing here */
		case ZBX_PREPROC_ERROR_FIELD_REGEX:
			zbx_strlcpy(param1, pp->params, sizeof(param1));
			if (NULL == (param2 = strchr(param1, '\n')))
			{
				zbx_snprintf(err, sizeof(err), "cannot find second parameter: %s", pp->params);
				ret = FAIL;
				break;
			}

			*param2 = '\0';

			if (FAIL == (ret = zbx_regexp_compile(param1, NULL, &regexp_err)))
			{
				zbx_strlcpy(err, regexp_err, sizeof(err));
				zbx_free(regexp_err);
			}
			break;
		case ZBX_PREPROC_JSONPATH:
			/* break; is not missing here */
		case ZBX_PREPROC_ERROR_FIELD_JSON:
			if (FAIL == (ret = zbx_jsonpath_compile(pp->params, &jsonpath)))
				zbx_strlcpy(err, zbx_json_strerror(), sizeof(err));
			else
				zbx_jsonpath_clear(&jsonpath);
			break;
		case ZBX_PREPROC_XPATH:
			/* break; is not missing here */
		case ZBX_PREPROC_ERROR_FIELD_XML:
			ret = zbx_xml_xpath_check(pp->params, err, sizeof(err));
			break;
		case ZBX_PREPROC_MULTIPLIER:
			if (FAIL == (ret = zbx_is_double(pp->params, NULL)))
				zbx_snprintf(err, sizeof(err), "value is not numeric or out of range: %s", pp->params);
			break;
		case ZBX_PREPROC_VALIDATE_RANGE:
			zbx_strlcpy(param1, pp->params, sizeof(param1));
			if (NULL == (param2 = strchr(param1, '\n')))
			{
				zbx_snprintf(err, sizeof(err), "cannot find second parameter: %s", pp->params);
				ret = FAIL;
				break;
			}
			*param2++ = '\0';
			zbx_lrtrim(param1, " ");
			zbx_lrtrim(param2, " ");

			if ('\0' != *param1 && FAIL == (ret = zbx_is_double(param1, NULL)))
			{
				zbx_snprintf(err, sizeof(err), "first parameter is not numeric or out of range: %s",
						param1);
			}
			else if ('\0' != *param2 && FAIL == (ret = zbx_is_double(param2, NULL)))
			{
				zbx_snprintf(err, sizeof(err), "second parameter is not numeric or out of range: %s",
						param2);
			}
			else if ('\0' == *param1 && '\0' == *param2)
			{
				zbx_snprintf(err, sizeof(err), "at least one parameter must be defined: %s",
						pp->params);
				ret = FAIL;
			}
			else if ('\0' != *param1 && '\0' != *param2)
			{
				/* use variants to handle uint64 and double values */
				zbx_variant_t	min, max;

				zbx_variant_set_numeric(&min, param1);
				zbx_variant_set_numeric(&max, param2);

				if (0 < zbx_variant_compare(&min, &max))
				{
					zbx_snprintf(err, sizeof(err), "first parameter '%s' must be less than second "
							"'%s'", param1, param2);
					ret = FAIL;
				}

				zbx_variant_clear(&min);
				zbx_variant_clear(&max);
			}

			break;
		case ZBX_PREPROC_VALIDATE_REGEX:
			/* break; is not missing here */
		case ZBX_PREPROC_VALIDATE_NOT_REGEX:
			if (FAIL == (ret = zbx_regexp_compile(pp->params, NULL, &regexp_err)))
			{
				zbx_strlcpy(err, regexp_err, sizeof(err));
				zbx_free(regexp_err);
			}
			break;
		case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
			if (SUCCEED != zbx_str2uint64(pp->params, "smhdw", &value_ui64) || 0 == value_ui64)
			{
				zbx_snprintf(err, sizeof(err), "invalid time interval: %s", pp->params);
				ret = FAIL;
			}
			break;
		case ZBX_PREPROC_PROMETHEUS_PATTERN:
			zbx_strlcpy(param1, pp->params, sizeof(param1));
			if (NULL == (param2 = strchr(param1, '\n')))
			{
				zbx_snprintf(err, sizeof(err), "cannot find second parameter: %s", pp->params);
				ret = FAIL;
				break;
			}
			*param2++ = '\0';

			if (NULL == (param3 = strchr(param2, '\n')))
			{
				zbx_snprintf(err, sizeof(err), "cannot find third parameter: %s", pp->params);
				ret = FAIL;
				break;
			}
			*param3++ = '\0';

			if (FAIL == zbx_prometheus_validate_filter(param1, &errmsg))
			{
				zbx_snprintf(err, sizeof(err), "invalid pattern: %s", param1);
				zbx_free(errmsg);
				ret = FAIL;
				break;
			}

			if (0 != strcmp(param2, "value") && 0 != strcmp(param2, "label") &&
					0 != strcmp(param2, "function"))
			{
				zbx_snprintf(err, sizeof(err), "invalid second parameter: %s", param2);
				ret = FAIL;
				break;
			}

			if (FAIL == zbx_prometheus_validate_label(param3))
			{
				zbx_snprintf(err, sizeof(err), "invalid label name: %s", param3);
				ret = FAIL;
				break;
			}

			break;
		case ZBX_PREPROC_PROMETHEUS_TO_JSON:
			if (FAIL == zbx_prometheus_validate_filter(pp->params, &errmsg))
			{
				zbx_snprintf(err, sizeof(err), "invalid pattern: %s", pp->params);
				zbx_free(errmsg);
				ret = FAIL;
				break;
			}
			break;
		case ZBX_PREPROC_STR_REPLACE:
			if ('\n' == *pp->params)
			{
				zbx_snprintf(err, sizeof(err), "first parameter is expected");
				ret = FAIL;
			}
			break;
	}
out:
	if (SUCCEED != ret)
	{
		*error = zbx_strdcatf(*error, "Cannot %s item: invalid value for preprocessing step #%d: %s.\n",
				(0 != itemid ? "update" : "create"), pp->step, err);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: reset discovery flags for all dependent item tree                 *
 *                                                                            *
 *****************************************************************************/
static void	lld_item_update_dep_discovery(zbx_lld_item_full_t *item, zbx_uint64_t reset_flags)
{
	if (0 == reset_flags && 0 != (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
		return;

	for (int i = 0; i < item->dependent_items.values_num; i++)
	{
		zbx_lld_item_full_t	*dep = item->dependent_items.values[i];

		dep->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
		lld_item_update_dep_discovery(item->dependent_items.values[i], ZBX_FLAG_LLD_ITEM_DISCOVERED);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: check for duplicated keys in database                             *
 *                                                                            *
 *****************************************************************************/
static void	lld_items_validate_db_key(zbx_uint64_t hostid, zbx_vector_lld_item_full_ptr_t *items,
		zbx_hashset_t *key_index, char **error)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_str_t	keys;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0, sql_reset;
	int			offset, size;

	zbx_vector_str_create(&keys);		/* list of item keys */

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item;

		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == item->itemid || 0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
			zbx_vector_str_append(&keys, item->key_);
	}

	if (0 == keys.values_num)
		goto out;

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select key_,itemid"
			" from items"
			" where hostid=" ZBX_FS_UI64
				" and",
			hostid);

	sql_reset = sql_offset;

	for (offset = 0; offset < keys.values_num; offset += ZBX_DB_LARGE_QUERY_BATCH_SIZE)
	{
		sql_offset = sql_reset;
		size = ZBX_DB_LARGE_QUERY_BATCH_SIZE;
		if (offset + size > keys.values_num)
			size = keys.values_num - offset;

		zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "key_",
				(const char **)keys.values + offset, size);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_lld_item_full_t	*item, item_stub;
			zbx_lld_item_ref_t	*ref, ref_local = {.item = &item_stub};

			ZBX_STR2UINT64(item_stub.itemid, row[1]);
			item_stub.key_ = row[0];

			if (NULL == (ref = (zbx_lld_item_ref_t *)zbx_hashset_search(key_index, &ref_local)) ||
					ref->item->itemid == ref_local.item->itemid ||
					0 == (ref->item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			{
				continue;
			}

			item = ref->item;

			char key_short[VALUE_ERRMSG_MAX * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

			*error = zbx_strdcatf(*error, "Cannot %s item:"
					" item with the same key \"%s\" already exists.\n",
					(0 != item->itemid ? "update" : "create"),
					zbx_truncate_itemkey(item->key_, VALUE_ERRMSG_MAX,
					key_short, sizeof(key_short)));

			if (0 != item->itemid)
			{
				lld_field_str_rollback(&item->key_, &item->key_orig, &item->flags,
						ZBX_FLAG_LLD_ITEM_UPDATE_KEY);
			}
			else
				item->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
		}
		zbx_db_free_result(result);
	}

	zbx_free(sql);
out:
	zbx_vector_str_destroy(&keys);
}

/******************************************************************************
 *                                                                            *
 * Parameters: hostid            - [IN]                                       *
 *             items             - [IN]                                       *
 *             item_prototypes   - [IN]                                       *
 *             item_dependencies - [IN]                                       *
 *             error             - [OUT] error message                        *
 *                                                                            *
 *****************************************************************************/
static void	lld_items_validate(zbx_uint64_t hostid, zbx_vector_lld_item_full_ptr_t *items, char **error)
{
	zbx_lld_item_full_t	*item;
	zbx_lld_item_ref_t	*ref, ref_local;
	zbx_hashset_t		key_index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* check an item name validity */
	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		lld_validate_item_field(item, &item->name, &item->name_proto,
				ZBX_FLAG_LLD_ITEM_UPDATE_NAME, ZBX_ITEM_NAME_LEN, error);
		lld_validate_item_field(item, &item->key_, &item->key_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_KEY, ZBX_ITEM_KEY_LEN, error);
		lld_validate_item_field(item, &item->delay, &item->delay_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_DELAY, ZBX_ITEM_DELAY_LEN, error);
		lld_validate_item_field(item, &item->history, &item->history_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_HISTORY, ZBX_ITEM_HISTORY_LEN, error);
		lld_validate_item_field(item, &item->trends, &item->trends_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_TRENDS, ZBX_ITEM_TRENDS_LEN, error);
		lld_validate_item_field(item, &item->units, &item->units_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_UNITS, ZBX_ITEM_UNITS_LEN, error);
		lld_validate_item_field(item, &item->params, &item->params_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_PARAMS, ZBX_ITEM_PARAM_LEN, error);
		lld_validate_item_field(item, &item->ipmi_sensor, &item->ipmi_sensor_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_IPMI_SENSOR, ZBX_ITEM_IPMI_SENSOR_LEN, error);
		lld_validate_item_field(item, &item->snmp_oid, &item->snmp_oid_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_OID, ZBX_ITEM_SNMP_OID_LEN, error);
		lld_validate_item_field(item, &item->username, &item->username_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_USERNAME, ZBX_ITEM_USERNAME_LEN, error);
		lld_validate_item_field(item, &item->password, &item->password_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_PASSWORD, ZBX_ITEM_PASSWORD_LEN, error);
		lld_validate_item_field(item, &item->description, &item->description_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION, ZBX_ITEM_DESCRIPTION_LEN, error);
		lld_validate_item_field(item, &item->jmx_endpoint, &item->jmx_endpoint_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_JMX_ENDPOINT, ZBX_ITEM_JMX_ENDPOINT_LEN, error);
		lld_validate_item_field(item, &item->timeout, &item->timeout_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_TIMEOUT, ZBX_ITEM_TIMEOUT_LEN, error);
		lld_validate_item_field(item, &item->url, &item->url_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_URL, ZBX_ITEM_URL_LEN, error);
		lld_validate_item_field(item, &item->query_fields, &item->query_fields_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_QUERY_FIELDS, ZBX_ITEM_QUERY_FIELDS_LEN, error);
		lld_validate_item_field(item, &item->posts, &item->posts_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_POSTS, ZBX_ITEM_POSTS_LEN, error);
		lld_validate_item_field(item, &item->status_codes, &item->status_codes_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_STATUS_CODES, ZBX_ITEM_STATUS_CODES_LEN, error);
		lld_validate_item_field(item, &item->http_proxy, &item->http_proxy_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_HTTP_PROXY, ZBX_ITEM_HTTP_PROXY_LEN, error);
		lld_validate_item_field(item, &item->headers, &item->headers_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_HEADERS, ZBX_ITEM_HEADERS_LEN, error);
		lld_validate_item_field(item, &item->ssl_cert_file, &item->ssl_cert_file_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_SSL_CERT_FILE, ZBX_ITEM_SSL_CERT_FILE_LEN, error);
		lld_validate_item_field(item, &item->ssl_key_file, &item->ssl_key_file_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_SSL_KEY_FILE, ZBX_ITEM_SSL_KEY_FILE_LEN, error);
		lld_validate_item_field(item, &item->ssl_key_password, &item->ssl_key_password_orig,
				ZBX_FLAG_LLD_ITEM_UPDATE_SSL_KEY_PASSWORD, ZBX_ITEM_SSL_KEY_PASSWORD_LEN, error);
	}

	/* check duplicated item keys */

	zbx_hashset_create(&key_index, 0, lld_item_ref_key_hash_func, lld_item_ref_key_compare_func);

	/* add 'good' (existing, discovered and not updated) keys to the hashset */
	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		/* skip new or updated item keys */
		if (0 != item->itemid && 0 == (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
		{
			ref_local.item = item;
			zbx_hashset_insert(&key_index, &ref_local, sizeof(ref_local));
		}
	}

	/* check new and updated keys for duplicated keys in discovered items */
	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		/* only new items or items with changed key will be validated */
		if (0 != item->itemid && 0 == (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
			continue;

		ref_local.item = item;
		ref = (zbx_lld_item_ref_t *)zbx_hashset_insert(&key_index, &ref_local, sizeof(ref_local));

		if (ref->item != item)	/* another item with the same key was already indexed */
		{
			char key_short[VALUE_ERRMSG_MAX * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

			*error = zbx_strdcatf(*error, "Cannot %s item: item with the same key \"%s\" already exists.\n",
						(0 != item->itemid ? "update" : "create"),
						zbx_truncate_itemkey(item->key_, VALUE_ERRMSG_MAX,
						key_short, sizeof(key_short)));

			if (0 != item->itemid)
			{
				lld_field_str_rollback(&item->key_, &item->key_orig, &item->flags,
						ZBX_FLAG_LLD_ITEM_UPDATE_KEY);
			}
			else
				item->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
		}
	}

	/* check preprocessing steps for new and updated discovered items */
	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->preproc_ops.values_num; j++)
		{
			if (SUCCEED != lld_items_preproc_step_validate(item->preproc_ops.values[j], item->itemid,
					error))
			{
				item->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
				break;
			}
		}
	}

	lld_items_validate_db_key(hostid, items, &key_index, error);

	zbx_hashset_destroy(&key_index);

	/* update discovered flags for dependent items */
	for (int i = 0; i < items->values_num; i++)
		lld_item_update_dep_discovery(items->values[i], 0);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Substitutes LLD macros in calculated item formula expression.     *
 *                                                                            *
 * Parameters: data            - [IN/OUT] expression                          *
 *             jp_row          - [IN] LLD data row                            *
 *             lld_macro_paths - [IN] use JSON path to extract from jp_row    *
 *             error           - [OUT] error message                          *
 *                                                                            *
 ******************************************************************************/
static int	substitute_formula_macros(char **data, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **error)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() formula:%s", __func__, *data);

	ret = zbx_substitute_expression_lld_macros(data, ZBX_EVAL_CALC_EXPRESSION_LLD, jp_row, lld_macro_paths, error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() formula:%s", __func__, *data);

	return ret;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: Creates a new item based on item prototype and LLD data row.       *
 *                                                                             *
 * Parameters: item_prototype  - [IN]                                          *
 *             lld_row         - [IN]                                          *
 *             lld_macro_paths - [IN] use JSON path to extract from jp_row     *
 *             error           - [OUT] error message                           *
 *                                                                             *
 * Returns: The created item or NULL if cannot create new item from prototype. *
 *                                                                             *
 *******************************************************************************/
static zbx_lld_item_full_t	*lld_item_make(const zbx_lld_item_prototype_t *item_prototype,
		const zbx_lld_row_t *lld_row, const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, int lastcheck,
		char **error)
{
	zbx_lld_item_full_t		*item;
	const struct zbx_json_parse	*jp_row = (struct zbx_json_parse *)&lld_row->jp_row;
	char				err[MAX_STRING_LEN];
	int				ret;
	const char			*delay, *history, *trends;
	unsigned char			discover;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	item = (zbx_lld_item_full_t *)zbx_malloc(NULL, sizeof(zbx_lld_item_full_t));

	item->itemid = 0;
	item->parent_itemid = item_prototype->itemid;
	item->lastcheck = lastcheck;
	item->discovery_status = ZBX_LLD_DISCOVERY_STATUS_NORMAL;
	item->ts_delete = 0;
	item->ts_disable = 0;
	item->disable_source = ZBX_DISABLE_SOURCE_DEFAULT;
	item->type = item_prototype->type;
	item->key_proto = NULL;
	item->master_itemid = item_prototype->master_itemid;

	item->name = zbx_strdup(NULL, item_prototype->name);
	item->name_proto = NULL;
	zbx_substitute_lld_macros(&item->name, jp_row, lld_macro_paths, ZBX_TOKEN_LLD_MACRO | ZBX_TOKEN_LLD_FUNC_MACRO,
			NULL, 0);
	zbx_lrtrim(item->name, ZBX_WHITESPACE);

	delay = item_prototype->delay;
	history = item_prototype->history;
	trends = item_prototype->trends;
	item->status = item_prototype->status;
	discover = item_prototype->discover;

	zbx_vector_db_tag_ptr_create(&item->override_tags);

	lld_override_item(&lld_row->overrides, item->name, &delay, &history, &trends, &item->override_tags,
			&item->status, &discover);

	item->key_ = zbx_strdup(NULL, item_prototype->key);
	item->key_orig = NULL;

	if (FAIL == (ret = zbx_substitute_key_macros(&item->key_, NULL, NULL, jp_row, lld_macro_paths,
			ZBX_MACRO_TYPE_ITEM_KEY, err, sizeof(err))))
	{
		*error = zbx_strdcatf(*error, "Cannot create item, error in item key parameters %s.\n", err);
	}

	item->delay = zbx_strdup(NULL, delay);
	item->delay_orig = NULL;
	zbx_substitute_lld_macros(&item->delay, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(item->delay, ZBX_WHITESPACE);

	item->history = zbx_strdup(NULL, history);
	item->history_orig = NULL;
	zbx_substitute_lld_macros(&item->history, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(item->history, ZBX_WHITESPACE);

	item->trends = zbx_strdup(NULL, trends);
	item->trends_orig = NULL;
	zbx_substitute_lld_macros(&item->trends, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(item->trends, ZBX_WHITESPACE);

	item->units = zbx_strdup(NULL, item_prototype->units);
	item->units_orig = NULL;
	zbx_substitute_lld_macros(&item->units, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(item->units, ZBX_WHITESPACE);

	item->params = zbx_strdup(NULL, item_prototype->params);
	item->params_orig = NULL;

	if (ITEM_TYPE_CALCULATED == item_prototype->type)
	{
		char	*errmsg = NULL;
		if (SUCCEED == ret && FAIL == (ret = substitute_formula_macros(&item->params, jp_row, lld_macro_paths,
				&errmsg)))
		{
			*error = zbx_strdcatf(*error, "Cannot create item, error in formula: %s.\n", errmsg);
			zbx_free(errmsg);
		}
	}
	else
		zbx_substitute_lld_macros(&item->params, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);

	zbx_lrtrim(item->params, ZBX_WHITESPACE);

	item->ipmi_sensor = zbx_strdup(NULL, item_prototype->ipmi_sensor);
	item->ipmi_sensor_orig = NULL;
	zbx_substitute_lld_macros(&item->ipmi_sensor, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(item->ipmi_sensor, ZBX_WHITESPACE); is not missing here */

	item->snmp_oid = zbx_strdup(NULL, item_prototype->snmp_oid);
	item->snmp_oid_orig = NULL;

	if (SUCCEED == ret && ITEM_TYPE_SNMP == item_prototype->type &&
			FAIL == (ret = zbx_substitute_key_macros(&item->snmp_oid, NULL, NULL, jp_row, lld_macro_paths,
			ZBX_MACRO_TYPE_SNMP_OID, err, sizeof(err))))
	{
		*error = zbx_strdcatf(*error, "Cannot create item, error in SNMP OID key parameters: %s.\n", err);
	}

	zbx_lrtrim(item->snmp_oid, ZBX_WHITESPACE);

	item->username = zbx_strdup(NULL, item_prototype->username);
	item->username_orig = NULL;
	zbx_substitute_lld_macros(&item->username, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(item->username, ZBX_WHITESPACE); is not missing here */

	item->password = zbx_strdup(NULL, item_prototype->password);
	item->password_orig = NULL;
	zbx_substitute_lld_macros(&item->password, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(item->password, ZBX_WHITESPACE); is not missing here */

	item->description = zbx_strdup(NULL, item_prototype->description);
	item->description_orig = NULL;
	zbx_substitute_lld_macros(&item->description, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(item->description, ZBX_WHITESPACE);

	item->jmx_endpoint = zbx_strdup(NULL, item_prototype->jmx_endpoint);
	item->jmx_endpoint_orig = NULL;
	zbx_substitute_lld_macros(&item->jmx_endpoint, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(item->ipmi_sensor, ZBX_WHITESPACE); is not missing here */

	item->timeout = zbx_strdup(NULL, item_prototype->timeout);
	item->timeout_orig = NULL;
	zbx_substitute_lld_macros(&item->timeout, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(item->timeout, ZBX_WHITESPACE);

	item->url = zbx_strdup(NULL, item_prototype->url);
	item->url_orig = NULL;
	zbx_substitute_lld_macros(&item->url, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(item->url, ZBX_WHITESPACE);

	item->query_fields = zbx_strdup(NULL, item_prototype->query_fields);
	item->query_fields_orig = NULL;

	if (SUCCEED == ret && FAIL == (ret = zbx_substitute_macros_in_json_pairs(&item->query_fields, jp_row,
			lld_macro_paths, err, sizeof(err))))
	{
		*error = zbx_strdcatf(*error, "Cannot create item, error in JSON: %s.\n", err);
	}

	item->posts = zbx_strdup(NULL, item_prototype->posts);
	item->posts_orig = NULL;

	switch (item_prototype->post_type)
	{
		case ZBX_POSTTYPE_JSON:
			zbx_substitute_lld_macros(&item->posts, jp_row, lld_macro_paths, ZBX_MACRO_JSON, NULL, 0);
			break;
		case ZBX_POSTTYPE_XML:
			if (SUCCEED == ret && FAIL == (ret = zbx_substitute_macros_xml(&item->posts, NULL, jp_row,
					lld_macro_paths, err, sizeof(err))))
			{
				zbx_lrtrim(err, ZBX_WHITESPACE);
				*error = zbx_strdcatf(*error, "Cannot create item, error in XML: %s.\n", err);
			}
			break;
		default:
			zbx_substitute_lld_macros(&item->posts, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
			/* zbx_lrtrim(item->posts, ZBX_WHITESPACE); is not missing here */
			break;
	}

	item->status_codes = zbx_strdup(NULL, item_prototype->status_codes);
	item->status_codes_orig = NULL;
	zbx_substitute_lld_macros(&item->status_codes, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(item->status_codes, ZBX_WHITESPACE);

	item->http_proxy = zbx_strdup(NULL, item_prototype->http_proxy);
	item->http_proxy_orig = NULL;
	zbx_substitute_lld_macros(&item->http_proxy, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(item->http_proxy, ZBX_WHITESPACE);

	item->headers = zbx_strdup(NULL, item_prototype->headers);
	item->headers_orig = NULL;
	zbx_substitute_lld_macros(&item->headers, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(item->headers, ZBX_WHITESPACE); is not missing here */

	item->ssl_cert_file = zbx_strdup(NULL, item_prototype->ssl_cert_file);
	item->ssl_cert_file_orig = NULL;
	zbx_substitute_lld_macros(&item->ssl_cert_file, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(item->ipmi_sensor, ZBX_WHITESPACE); is not missing here */

	item->ssl_key_file = zbx_strdup(NULL, item_prototype->ssl_key_file);
	item->ssl_key_file_orig = NULL;
	zbx_substitute_lld_macros(&item->ssl_key_file, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(item->ipmi_sensor, ZBX_WHITESPACE); is not missing here */

	item->ssl_key_password = zbx_strdup(NULL, item_prototype->ssl_key_password);
	item->ssl_key_password_orig = NULL;
	zbx_substitute_lld_macros(&item->ssl_key_password, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(item->ipmi_sensor, ZBX_WHITESPACE); is not missing here */

	item->trapper_hosts_orig = NULL;
	item->formula_orig = NULL;
	item->logtimefmt_orig = NULL;
	item->publickey_orig = NULL;
	item->privatekey_orig = NULL;

	item->lld_row = lld_row;

	zbx_vector_lld_item_preproc_ptr_create(&item->preproc_ops);
	zbx_vector_lld_item_full_ptr_create(&item->dependent_items);
	zbx_vector_item_param_ptr_create(&item->item_params);
	zbx_vector_db_tag_ptr_create(&item->item_tags);

	if (SUCCEED == ret && ZBX_PROTOTYPE_NO_DISCOVER != discover)
		item->flags = ZBX_FLAG_LLD_ITEM_DISCOVERED;
	else
		item->flags = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return item;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: Updates an existing item based on item prototype and LLD data row. *
 *                                                                             *
 * Parameters: item_prototype  - [IN]                                          *
 *             lld_row         - [IN]                                          *
 *             lld_macro_paths - [IN] use JSON path to extract from jp_row     *
 *             item            - [IN/OUT] existing item or NULL                *
 *             error           - [OUT] error message                           *
 *                                                                             *
 *******************************************************************************/
static void	lld_item_update(const zbx_lld_item_prototype_t *item_prototype, const zbx_lld_row_t *lld_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, zbx_lld_item_full_t *item, char **error)
{
	char			*buffer = NULL, err[MAX_STRING_LEN];
	struct zbx_json_parse	*jp_row = (struct zbx_json_parse *)&lld_row->jp_row;
	const char		*delay, *history, *trends;
	unsigned char		discover;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	buffer = zbx_strdup(buffer, item_prototype->name);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_TOKEN_LLD_MACRO | ZBX_TOKEN_LLD_FUNC_MACRO,
			NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);

	if (0 != strcmp(item->name, buffer))
	{
		item->name_proto = item->name;
		item->name = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_NAME;
	}

	delay = item_prototype->delay;
	history = item_prototype->history;
	trends = item_prototype->trends;
	discover = item_prototype->discover;

	lld_override_item(&lld_row->overrides, item->name, &delay, &history, &trends, &item->override_tags, NULL,
			&discover);

	if (0 != strcmp(item->key_proto, item_prototype->key))
	{
		buffer = zbx_strdup(buffer, item_prototype->key);

		if (SUCCEED == zbx_substitute_key_macros(&buffer, NULL, NULL, jp_row, lld_macro_paths,
				ZBX_MACRO_TYPE_ITEM_KEY, err, sizeof(err)))
		{
			item->key_orig = item->key_;
			item->key_ = buffer;
			buffer = NULL;
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_KEY;
		}
		else
			*error = zbx_strdcatf(*error, "Cannot update item, error in item key parameters: %s.\n", err);
	}

	buffer = zbx_strdup(buffer, delay);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);

	if (0 != strcmp(item->delay, buffer))
	{
		item->delay_orig = item->delay;
		item->delay = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_DELAY;
	}

	buffer = zbx_strdup(buffer, history);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);

	if (0 != strcmp(item->history, buffer))
	{
		item->history_orig = item->history;
		item->history = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_HISTORY;
	}

	buffer = zbx_strdup(buffer, trends);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);

	if (0 != strcmp(item->trends, buffer))
	{
		item->trends_orig = item->trends;
		item->trends = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_TRENDS;
	}

	buffer = zbx_strdup(buffer, item_prototype->units);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);

	if (0 != strcmp(item->units, buffer))
	{
		item->units_orig = item->units;
		item->units = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_UNITS;
	}

	buffer = zbx_strdup(buffer, item_prototype->params);

	if (ITEM_TYPE_CALCULATED == item_prototype->type)
	{
		char	*errmsg = NULL;

		if (SUCCEED == substitute_formula_macros(&buffer, jp_row, lld_macro_paths, &errmsg))
		{
			zbx_lrtrim(buffer, ZBX_WHITESPACE);

			if (0 != strcmp(item->params, buffer))
			{
				item->params_orig = item->params;
				item->params = buffer;
				buffer = NULL;
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PARAMS;
			}
		}
		else
		{
			*error = zbx_strdcatf(*error, "Cannot update item, error in formula: %s.\n", errmsg);
			zbx_free(errmsg);
		}
	}
	else
	{
		zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);

		if (0 != strcmp(item->params, buffer))
		{
			item->params_orig = item->params;
			item->params = buffer;
			buffer = NULL;
			item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PARAMS;
		}
	}

	buffer = zbx_strdup(buffer, item_prototype->ipmi_sensor);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(buffer, ZBX_WHITESPACE); is not missing here */
	if (0 != strcmp(item->ipmi_sensor, buffer))
	{
		item->ipmi_sensor_orig = item->ipmi_sensor;
		item->ipmi_sensor = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_IPMI_SENSOR;
	}

	buffer = zbx_strdup(buffer, item_prototype->snmp_oid);

	if (ITEM_TYPE_SNMP == item_prototype->type && FAIL == zbx_substitute_key_macros(&buffer, NULL, NULL, jp_row,
			lld_macro_paths, ZBX_MACRO_TYPE_SNMP_OID, err, sizeof(err)))
	{
		*error = zbx_strdcatf(*error, "Cannot update item, error in SNMP OID key parameters: %s.\n", err);
	}

	zbx_lrtrim(buffer, ZBX_WHITESPACE);

	if (0 != strcmp(item->snmp_oid, buffer))
	{
		item->snmp_oid_orig = item->snmp_oid;
		item->snmp_oid = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_OID;
	}

	buffer = zbx_strdup(buffer, item_prototype->username);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(buffer, ZBX_WHITESPACE); is not missing here */

	if (0 != strcmp(item->username, buffer))
	{
		item->username_orig = item->username;
		item->username = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_USERNAME;
	}

	buffer = zbx_strdup(buffer, item_prototype->password);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(buffer, ZBX_WHITESPACE); is not missing here */

	if (0 != strcmp(item->password, buffer))
	{
		item->password_orig = item->password;
		item->password = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_PASSWORD;
	}

	buffer = zbx_strdup(buffer, item_prototype->description);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);

	if (0 != strcmp(item->description, buffer))
	{
		item->description_orig = item->description;
		item->description = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION;
	}

	buffer = zbx_strdup(buffer, item_prototype->jmx_endpoint);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(buffer, ZBX_WHITESPACE); is not missing here */

	if (0 != strcmp(item->jmx_endpoint, buffer))
	{
		item->jmx_endpoint_orig = item->jmx_endpoint;
		item->jmx_endpoint = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_JMX_ENDPOINT;
	}

	buffer = zbx_strdup(buffer, item_prototype->timeout);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);

	if (0 != strcmp(item->timeout, buffer))
	{
		item->timeout_orig = item->timeout;
		item->timeout = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_TIMEOUT;
	}

	buffer = zbx_strdup(buffer, item_prototype->url);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);

	if (0 != strcmp(item->url, buffer))
	{
		item->url_orig = item->url;
		item->url = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_URL;
	}

	buffer = zbx_strdup(buffer, item_prototype->query_fields);

	if (FAIL == zbx_substitute_macros_in_json_pairs(&buffer, jp_row, lld_macro_paths, err, sizeof(err)))
		*error = zbx_strdcatf(*error, "Cannot update item, error in JSON: %s.\n", err);

	if (0 != strcmp(item->query_fields, buffer))
	{
		item->query_fields_orig = item->query_fields;
		item->query_fields = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_QUERY_FIELDS;
	}

	buffer = zbx_strdup(buffer, item_prototype->posts);

	if (ZBX_POSTTYPE_JSON == item_prototype->post_type)
	{
		zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_JSON, NULL, 0);
	}
	else if (ZBX_POSTTYPE_XML == item_prototype->post_type)
	{
		if (FAIL == zbx_substitute_macros_xml(&buffer, NULL, jp_row, lld_macro_paths, err, sizeof(err)))
		{
			zbx_lrtrim(err, ZBX_WHITESPACE);
			*error = zbx_strdcatf(*error, "Cannot update item, error in XML: %s.\n", err);
		}
	}
	else
		zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(buffer, ZBX_WHITESPACE); is not missing here */
	if (0 != strcmp(item->posts, buffer))
	{
		item->posts_orig = item->posts;
		item->posts = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_POSTS;
	}

	buffer = zbx_strdup(buffer, item_prototype->status_codes);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);

	if (0 != strcmp(item->status_codes, buffer))
	{
		item->status_codes_orig = item->status_codes;
		item->status_codes = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_STATUS_CODES;
	}

	buffer = zbx_strdup(buffer, item_prototype->http_proxy);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	zbx_lrtrim(buffer, ZBX_WHITESPACE);

	if (0 != strcmp(item->http_proxy, buffer))
	{
		item->http_proxy_orig = item->http_proxy;
		item->http_proxy = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_HTTP_PROXY;
	}

	buffer = zbx_strdup(buffer, item_prototype->headers);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/*zbx_lrtrim(buffer, ZBX_WHITESPACE); is not missing here */

	if (0 != strcmp(item->headers, buffer))
	{
		item->headers_orig = item->headers;
		item->headers = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_HEADERS;
	}

	buffer = zbx_strdup(buffer, item_prototype->ssl_cert_file);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(buffer, ZBX_WHITESPACE); is not missing here */

	if (0 != strcmp(item->ssl_cert_file, buffer))
	{
		item->ssl_cert_file_orig = item->ssl_cert_file;
		item->ssl_cert_file = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SSL_CERT_FILE;
	}

	buffer = zbx_strdup(buffer, item_prototype->ssl_key_file);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(buffer, ZBX_WHITESPACE); is not missing here */

	if (0 != strcmp(item->ssl_key_file, buffer))
	{
		item->ssl_key_file_orig = item->ssl_key_file;
		item->ssl_key_file = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SSL_KEY_FILE;
	}

	buffer = zbx_strdup(buffer, item_prototype->ssl_key_password);
	zbx_substitute_lld_macros(&buffer, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
	/* zbx_lrtrim(buffer, ZBX_WHITESPACE); is not missing here */

	if (0 != strcmp(item->ssl_key_password, buffer))
	{
		item->ssl_key_password_orig = item->ssl_key_password;
		item->ssl_key_password = buffer;
		buffer = NULL;
		item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_SSL_KEY_PASSWORD;
	}

	if (ZBX_PROTOTYPE_NO_DISCOVER != discover)
		item->flags |= ZBX_FLAG_LLD_ITEM_DISCOVERED;

	item->lld_row = lld_row;

	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates existing items and creates new ones based on item,        *
 *          item prototypes and LLD data.                                     *
 *                                                                            *
 * Parameters: item_prototypes - [IN]                                         *
 *             lld_rows        - [IN] LLD data rows                           *
 *             lld_macro_paths - [IN] use JSON path to extract from jp_row    *
 *             items           - [IN/OUT] sorted list of items                *
 *             items_index     - [OUT] Index of items based on prototype ids  *
 *                                     and LLD rows. Used to quckly find an   *
 *                                     item by prototype and lld_row.         *
 *             error           - [OUT] error message                          *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_make(const zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		zbx_vector_lld_row_ptr_t *lld_rows, const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths,
		zbx_vector_lld_item_full_ptr_t *items, zbx_hashset_t *items_index, int lastcheck, char **error)
{
	int				index;
	zbx_lld_item_prototype_t	*item_prototype;
	zbx_lld_item_full_t		*item;
	zbx_lld_row_t			*lld_row;
	zbx_lld_item_index_t		*item_index, item_index_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* create the items index */
	for (int i = 0; i < item_prototypes->values_num; i++)
	{
		item_prototype = item_prototypes->values[i];

		for (int j = 0; j < lld_rows->values_num; j++)
			zbx_vector_lld_row_ptr_append(&item_prototype->lld_rows, lld_rows->values[j]);
	}


	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_ref_t	ref_local;

		item = items->values[i];

		zbx_lld_item_prototype_t	cmp = {.itemid = item->parent_itemid};

		if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &cmp,
				lld_item_prototype_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_prototype = item_prototypes->values[index];

		if (0 == item_prototype->item_index.num_slots)
			zbx_hashset_reserve(&item_prototype->item_index, items->values_num);

		ref_local.item = item;

		zbx_hashset_insert(&item_prototype->item_index, &ref_local, sizeof(ref_local));

		if (FAIL == zbx_vector_str_search(&item_prototype->keys, item->key_proto, ZBX_DEFAULT_STR_COMPARE_FUNC))
			zbx_vector_str_append(&item_prototype->keys, item->key_proto);
	}

	for (int i = 0; i < item_prototypes->values_num; i++)
	{
		zbx_lld_item_full_t	item_stub = {0};
		zbx_lld_item_ref_t	*ref, ref_local = {.item = &item_stub};

		item_prototype = item_prototypes->values[i];

		for (int j = item_prototype->lld_rows.values_num - 1; j >= 0; j--)
		{
			lld_row = item_prototype->lld_rows.values[j];

			for (int k = 0; k < item_prototype->keys.values_num; k++)
			{
				item_stub.key_ = zbx_strdup(item_stub.key_, item_prototype->keys.values[k]);

				if (SUCCEED != zbx_substitute_key_macros(&item_stub.key_, NULL, NULL, &lld_row->jp_row,
						lld_macro_paths, ZBX_MACRO_TYPE_ITEM_KEY, NULL, 0))
				{
					continue;
				}

				if (NULL == (ref = (zbx_lld_item_ref_t *)zbx_hashset_search(&item_prototype->item_index,
						&ref_local)))
				{
					continue;
				}

				if (SUCCEED != lld_validate_item_override_no_discover(&lld_row->overrides,
						ref->item->name, item_prototype->discover))
				{
					continue;
				}

				item_index_local.parent_itemid = ref->item->parent_itemid;
				item_index_local.lld_row = lld_row;
				item_index_local.item = ref->item;
				zbx_hashset_insert(items_index, &item_index_local, sizeof(item_index_local));

				zbx_vector_lld_row_ptr_remove_noorder(&item_prototype->lld_rows, j);
				zbx_hashset_remove_direct(&item_prototype->item_index, ref);

				break;
			}
		}

		zbx_free(item_stub.key_);
	}

	/* update/create discovered items */
	for (int i = 0; i < item_prototypes->values_num; i++)
	{
		item_prototype = item_prototypes->values[i];
		item_index_local.parent_itemid = item_prototype->itemid;

		for (int j = 0; j < lld_rows->values_num; j++)
		{
			item_index_local.lld_row = lld_rows->values[j];

			if (NULL == (item_index = (zbx_lld_item_index_t *)zbx_hashset_search(items_index,
					&item_index_local)))
			{
				item = lld_item_make(item_prototype, item_index_local.lld_row, lld_macro_paths,
						lastcheck, error);

				/* add the created item to items vector and update index */
				zbx_vector_lld_item_full_ptr_append(items, item);
				item_index_local.item = item;
				zbx_hashset_insert(items_index, &item_index_local, sizeof(item_index_local));
			}
			else
				lld_item_update(item_prototype, item_index_local.lld_row, lld_macro_paths,
						item_index->item, error);
		}
	}

	zbx_vector_lld_item_full_ptr_sort(items, lld_item_full_compare_func);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d items", __func__, items->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Escapes symbols in items preprocessing steps for discovery        *
 *          process.                                                          *
 *                                                                            *
 * Parameters: type            - [IN] item preprocessing step type            *
 *             lld_row         - [IN] LLD source value                        *
 *             lld_macro_paths - [IN] use JSON path to extract from jp_row    *
 *             sub_params      - [IN/OUT] preprocessing parameters            *
 *                                                                            *
 ******************************************************************************/
static void	substitute_lld_macros_in_preproc_params(int type, const zbx_lld_row_t *lld_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **sub_params)
{
	int	params_num = 1, flags1, flags2;

	switch (type)
	{
		case ZBX_PREPROC_REGSUB:
		case ZBX_PREPROC_ERROR_FIELD_REGEX:
			flags1 = ZBX_MACRO_ANY | ZBX_TOKEN_REGEXP;
			flags2 = ZBX_MACRO_ANY | ZBX_TOKEN_REGEXP_OUTPUT;
			params_num = 2;
			break;
		case ZBX_PREPROC_VALIDATE_REGEX:
		case ZBX_PREPROC_VALIDATE_NOT_REGEX:
			flags1 = ZBX_MACRO_ANY | ZBX_TOKEN_REGEXP;
			params_num = 1;
			break;
		case ZBX_PREPROC_XPATH:
		case ZBX_PREPROC_ERROR_FIELD_XML:
			flags1 = ZBX_MACRO_ANY | ZBX_TOKEN_XPATH;
			params_num = 1;
			break;
		case ZBX_PREPROC_PROMETHEUS_PATTERN:
		case ZBX_PREPROC_PROMETHEUS_TO_JSON:
			flags1 = ZBX_MACRO_ANY | ZBX_TOKEN_PROMETHEUS;
			params_num = 1;
			break;
		case ZBX_PREPROC_JSONPATH:
			flags1 = ZBX_MACRO_ANY | ZBX_TOKEN_JSONPATH;
			params_num = 1;
			break;
		case ZBX_PREPROC_STR_REPLACE:
			flags1 = ZBX_MACRO_ANY | ZBX_TOKEN_STR_REPLACE;
			flags2 = ZBX_MACRO_ANY | ZBX_TOKEN_STR_REPLACE;
			params_num = 2;
			break;
		default:
			flags1 = ZBX_MACRO_ANY;
			params_num = 1;
	}

	if (2 == params_num)
	{
		char	*param1, *param2;
		size_t	params_alloc, params_offset = 0;

		zbx_strsplit_first(*sub_params, '\n', &param1, &param2);

		if (NULL == param2)
		{
			zbx_free(param1);
			zabbix_log(LOG_LEVEL_ERR, "Invalid preprocessing parameters: %s.", *sub_params);
			THIS_SHOULD_NEVER_HAPPEN;
			return;
		}

		zbx_substitute_lld_macros(&param1, &lld_row->jp_row, lld_macro_paths, flags1, NULL, 0);
		zbx_substitute_lld_macros(&param2, &lld_row->jp_row, lld_macro_paths, flags2, NULL, 0);

		params_alloc = strlen(param1) + strlen(param2) + 2;
		*sub_params = (char*)zbx_realloc(*sub_params, params_alloc);

		zbx_strcpy_alloc(sub_params, &params_alloc, &params_offset, param1);
		zbx_chrcpy_alloc(sub_params, &params_alloc, &params_offset, '\n');
		zbx_strcpy_alloc(sub_params, &params_alloc, &params_offset, param2);

		zbx_free(param1);
		zbx_free(param2);
	}
	else
		zbx_substitute_lld_macros(sub_params, &lld_row->jp_row, lld_macro_paths, flags1, NULL, 0);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates existing items preprocessing operations and creates new   *
 *          ones based on item prototypes.                                    *
 *                                                                            *
 * Parameters: item_prototypes - [IN]                                         *
 *             lld_macro_paths - [IN] use JSON path to extract from jp_row    *
 *             items           - [IN/OUT] sorted list of items                *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_preproc_make(const zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, zbx_vector_lld_item_full_ptr_t *items)
{
	int				index, preproc_num;
	zbx_lld_item_full_t		*item;
	zbx_lld_item_prototype_t	*item_proto;
	zbx_lld_item_preproc_t		*ppsrc, *ppdst;
	char				*buffer = NULL;

	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		zbx_lld_item_prototype_t	cmp = {.itemid = item->parent_itemid};

		if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &cmp,
				lld_item_prototype_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_vector_lld_item_preproc_ptr_sort(&item->preproc_ops, lld_item_preproc_sort_by_step);

		item_proto = item_prototypes->values[index];

		preproc_num = MAX(item->preproc_ops.values_num, item_proto->preproc_ops.values_num);

		for (int j = 0; j < preproc_num; j++)
		{
			if (j >= item->preproc_ops.values_num)
			{
				ppsrc = item_proto->preproc_ops.values[j];
				ppdst = zbx_init_lld_item_preproc(0, ZBX_FLAG_LLD_ITEM_PREPROC_DISCOVERED |
						ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE, ppsrc->step, ppsrc->type,
						ppsrc->params, ppsrc->error_handler, ppsrc->error_handler_params);
				substitute_lld_macros_in_preproc_params(ppsrc->type, item->lld_row, lld_macro_paths,
						&ppdst->params);
				zbx_substitute_lld_macros(&ppdst->error_handler_params, &item->lld_row->jp_row,
						lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);

				zbx_vector_lld_item_preproc_ptr_append(&item->preproc_ops, ppdst);
				continue;
			}

			ppdst = item->preproc_ops.values[j];

			if (j >= item_proto->preproc_ops.values_num)
			{
				ppdst->flags &= ~ZBX_FLAG_LLD_ITEM_PREPROC_DISCOVERED;
				continue;
			}

			ppsrc = item_proto->preproc_ops.values[j];

			ppdst->flags |= ZBX_FLAG_LLD_ITEM_PREPROC_DISCOVERED;

			if (ppdst->type != ppsrc->type)
			{
				ppdst->type_orig = ppdst->type;
				ppdst->type = ppsrc->type;
				ppdst->flags |= ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_TYPE;
			}

			if (ppdst->step != ppsrc->step)
			{
				/* this should never happen */
				ppdst->step = ppsrc->step;
				ppdst->flags |= ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_STEP;
			}

			buffer = zbx_strdup(buffer, ppsrc->params);
			substitute_lld_macros_in_preproc_params(ppsrc->type, item->lld_row, lld_macro_paths, &buffer);

			if (0 != strcmp(ppdst->params, buffer))
			{
				ppdst->params_orig = zbx_strdup(NULL, ppdst->params);
				zbx_free(ppdst->params);
				ppdst->params = buffer;
				buffer = NULL;
				ppdst->flags |= ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_PARAMS;
			}

			if (ppdst->error_handler != ppsrc->error_handler)
			{
				ppdst->error_handler_orig = ppdst->error_handler;
				ppdst->error_handler = ppsrc->error_handler;
				ppdst->flags |= ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_ERROR_HANDLER;
			}

			buffer = zbx_strdup(buffer, ppsrc->error_handler_params);
			zbx_substitute_lld_macros(&buffer, &item->lld_row->jp_row, lld_macro_paths, ZBX_MACRO_ANY,
					NULL, 0);

			if (0 != strcmp(ppdst->error_handler_params, buffer))
			{
				ppdst->error_handler_params_orig = zbx_strdup(NULL, ppdst->error_handler_params);
				zbx_free(ppdst->error_handler_params);
				ppdst->error_handler_params = buffer;
				buffer = NULL;
				ppdst->flags |= ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_ERROR_HANDLER_PARAMS;
			}
			else
				zbx_free(buffer);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates existing items parameters and creates new ones based on   *
 *          item prototypes.                                                  *
 *                                                                            *
 * Parameters: item_prototypes - [IN]                                         *
 *             lld_macro_paths - [IN] use JSON path to extract from jp_row    *
 *             items           - [IN/OUT] sorted list of items                *
 *             error           - [OUT] error message                          *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_param_make(const zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, zbx_vector_lld_item_full_ptr_t *items,
		char **error)
{
	int				index;
	zbx_lld_item_prototype_t	*item_proto;
	zbx_vector_item_param_ptr_t	new_item_params;
	zbx_item_param_t		*db_item_param;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_item_param_ptr_create(&new_item_params);

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		zbx_lld_item_prototype_t	cmp = {.itemid = item->parent_itemid};

		if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &cmp,
				lld_item_prototype_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_proto = item_prototypes->values[index];

		for (int j = 0; j < item_proto->item_params.values_num; j++)
		{
			db_item_param = zbx_item_param_create(item_proto->item_params.values[j]->name,
					item_proto->item_params.values[j]->value);

			zbx_substitute_lld_macros(&db_item_param->name, &item->lld_row->jp_row,
					lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
			zbx_substitute_lld_macros(&db_item_param->value, &item->lld_row->jp_row,
					lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);

			zbx_vector_item_param_ptr_append(&new_item_params, db_item_param);
		}

		if (SUCCEED != zbx_merge_item_params(&item->item_params, &new_item_params, error))
		{
			if (0 == item->itemid)
			{
				item->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
				*error = zbx_strdcatf(*error,
						"Cannot create item param : item_param validation failed.\n");
			}
		}
	}

	zbx_vector_item_param_ptr_destroy(&new_item_params);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates existing items tags and creates new ones based on item    *
 *          prototypes.                                                       *
 *                                                                            *
 * Parameters: item_prototypes - [IN]                                         *
 *             lld_macro_paths - [IN] use JSON path to extract from jp_row    *
 *             items           - [IN/OUT] sorted list of items                *
 *             error           - [OUT] error message                          *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_tags_make(const zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, zbx_vector_lld_item_full_ptr_t *items,
		char **error)
{
	int				index;
	zbx_lld_item_prototype_t	*item_proto;
	zbx_vector_db_tag_ptr_t		new_tags;
	zbx_db_tag_t			*db_tag;

	zbx_vector_db_tag_ptr_create(&new_tags);

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		zbx_lld_item_prototype_t	cmp = {.itemid = item->parent_itemid};

		if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &cmp,
				lld_item_prototype_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_proto = item_prototypes->values[index];

		for (int j = 0; j < item_proto->item_tags.values_num; j++)
		{
			db_tag = zbx_db_tag_create(item_proto->item_tags.values[j]->tag,
					item_proto->item_tags.values[j]->value);
			zbx_vector_db_tag_ptr_append(&new_tags, db_tag);
		}

		for (int j = 0; j < item->override_tags.values_num; j++)
		{
			db_tag = zbx_db_tag_create(item->override_tags.values[j]->tag,
					item->override_tags.values[j]->value);
			zbx_vector_db_tag_ptr_append(&new_tags, db_tag);
		}

		for (int j = 0; j < new_tags.values_num; j++)
		{
			zbx_substitute_lld_macros(&new_tags.values[j]->tag, &item->lld_row->jp_row, lld_macro_paths,
					ZBX_MACRO_ANY, NULL, 0);
			zbx_substitute_lld_macros(&new_tags.values[j]->value, &item->lld_row->jp_row, lld_macro_paths,
					ZBX_MACRO_ANY, NULL, 0);
		}

		if (SUCCEED != zbx_merge_tags(&item->item_tags, &new_tags, "item", error))
		{
			if (0 == item->itemid)
			{
				item->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
				*error = zbx_strdcatf(*error, "Cannot create item: tag validation failed.\n");
			}
		}
	}

	zbx_vector_db_tag_ptr_destroy(&new_tags);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Recursively prepares LLD item bulk inserts and updates dependent  *
 *          items with their masters.                                         *
 *                                                                            *
 * Parameters: hostid               - [IN] parent host id                     *
 *             item_prototypes      - [IN]                                    *
 *             item                 - [IN/OUT] item to be saved and set       *
 *                                             master for dependent items     *
 *             itemid               - [IN/OUT] item id used for insert        *
 *                                             operations                     *
 *             itemdiscoveryid      - [IN/OUT] item discovery id used for     *
 *                                             insert operations              *
 *             db_insert_items      - [IN] prepared item bulk insert          *
 *             db_insert_idiscovery - [IN] prepared item discovery bulk       *
 *                                         insert                             *
 *             db_insert_irtdata    - [IN] prepared item real-time data bulk  *
 *                                         insert                             *
 *             db_insert_irtname    - [IN]                                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_item_save(zbx_uint64_t hostid, const zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		zbx_lld_item_full_t *item, zbx_uint64_t *itemid, zbx_uint64_t *itemdiscoveryid,
		zbx_db_insert_t *db_insert_items, zbx_db_insert_t *db_insert_idiscovery,
		zbx_db_insert_t *db_insert_irtdata, zbx_db_insert_t *db_insert_irtname)
{
	int	index;

	if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
		return;

	zbx_lld_item_prototype_t	cmp = {.itemid = item->parent_itemid};

	if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &cmp,
			lld_item_prototype_compare_func)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	if (0 == item->itemid)
	{
		const zbx_lld_item_prototype_t	*item_prototype = item_prototypes->values[index];

		zbx_db_insert_add_values(db_insert_items, *itemid, item->name, item->key_, hostid,
				(int)item_prototype->type, (int)item_prototype->value_type,
				item->delay, item->history, item->trends,
				(int)item->status, item_prototype->trapper_hosts, item->units,
				item_prototype->formula, item_prototype->logtimefmt, item_prototype->valuemapid,
				item->params, item->ipmi_sensor, item->snmp_oid, (int)item_prototype->authtype,
				item->username, item->password, item_prototype->publickey, item_prototype->privatekey,
				item->description, item_prototype->interfaceid, (int)ZBX_FLAG_DISCOVERY_CREATED,
				item->jmx_endpoint, item->master_itemid,
				item->timeout, item->url, item->query_fields, item->posts, item->status_codes,
				item_prototype->follow_redirects, item_prototype->post_type, item->http_proxy,
				item->headers, item_prototype->retrieve_mode, item_prototype->request_method,
				item_prototype->output_format, item->ssl_cert_file, item->ssl_key_file,
				item->ssl_key_password, item_prototype->verify_peer, item_prototype->verify_host,
				item_prototype->allow_traps);

		zbx_db_insert_add_values(db_insert_idiscovery, (*itemdiscoveryid)++, *itemid,
				item->parent_itemid, item_prototype->key, item->lastcheck);

		zbx_db_insert_add_values(db_insert_irtdata, *itemid);
		zbx_db_insert_add_values(db_insert_irtname, *itemid, item->name, item->name);

		zbx_audit_item_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_ADD, *itemid, item->name,
				ZBX_FLAG_DISCOVERY_CREATED);
		zbx_audit_item_update_json_add_lld_data(*itemid, item, item_prototype, hostid);
		item->itemid = (*itemid)++;
	}

	for (index = 0; index < item->dependent_items.values_num; index++)
	{
		zbx_lld_item_full_t	*dependent = item->dependent_items.values[index];

		dependent->master_itemid = item->itemid;
		lld_item_save(hostid, item_prototypes, dependent, itemid, itemdiscoveryid, db_insert_items,
				db_insert_idiscovery, db_insert_irtdata, db_insert_irtname);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepares SQL to update LLD item                                   *
 *                                                                            *
 * Parameters: item_prototype       - [IN]                                    *
 *             item                 - [IN] item to be updated                 *
 *             sql                  - [IN/OUT] SQL buffer pointer used for    *
 *                                             update operations              *
 *             sql_alloc            - [IN/OUT] SQL buffer already allocated   *
 *                                             memory                         *
 *             sql_offset           - [IN/OUT] offset for writing within SQL  *
 *                                             buffer                         *
 *                                                                            *
 ******************************************************************************/
static void	lld_item_prepare_update(const zbx_lld_item_prototype_t *item_prototype, const zbx_lld_item_full_t *item,
		char **sql, size_t *sql_alloc, size_t *sql_offset)
{
	char				*value_esc;
	const char			*d = "";

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "update items set ");

	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_NAME))
	{
		value_esc = zbx_db_dyn_escape_string(item->name);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "name='%s'", value_esc);
		d = ",";
		zbx_audit_item_update_json_update_name(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->name_proto, item->name);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
	{
		value_esc = zbx_db_dyn_escape_string(item->key_);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%skey_='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_key_(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->key_orig, item->key_);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_TYPE))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%stype=%d", d, (int)item_prototype->type);
		d = ",";
		zbx_audit_item_update_json_update_type(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->type_orig, (int)item_prototype->type);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_VALUE_TYPE))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%svalue_type=%d", d, (int)item_prototype->value_type);
		d = ",";
		zbx_audit_item_update_json_update_value_type(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->value_type_orig,
				(int)item_prototype->value_type);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_DELAY))
	{
		value_esc = zbx_db_dyn_escape_string(item->delay);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sdelay='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_delay(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->delay_orig, item->delay);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_HISTORY))
	{
		value_esc = zbx_db_dyn_escape_string(item->history);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%shistory='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_history(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->history_orig, item->history);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_TRENDS))
	{
		value_esc = zbx_db_dyn_escape_string(item->trends);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%strends='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_trends(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->trends_orig, item->trends);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_TRAPPER_HOSTS))
	{
		value_esc = zbx_db_dyn_escape_string(item_prototype->trapper_hosts);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%strapper_hosts='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_trapper_hosts(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->trapper_hosts_orig,
				item_prototype->trapper_hosts);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_UNITS))
	{
		value_esc = zbx_db_dyn_escape_string(item->units);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sunits='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_units(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->units_orig, item->units);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_FORMULA))
	{
		value_esc = zbx_db_dyn_escape_string(item_prototype->formula);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sformula='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_formula(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->formula_orig, item_prototype->formula);
		zbx_free(value_esc);

	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_LOGTIMEFMT))
	{
		value_esc = zbx_db_dyn_escape_string(item_prototype->logtimefmt);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%slogtimefmt='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_logtimefmt(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->logtimefmt_orig, item_prototype->logtimefmt);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_VALUEMAPID))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%svaluemapid=%s",
				d, zbx_db_sql_id_ins(item_prototype->valuemapid));
		d = ",";
		zbx_audit_item_update_json_update_valuemapid(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->valuemapid_orig, item_prototype->valuemapid);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PARAMS))
	{
		value_esc = zbx_db_dyn_escape_string(item->params);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sparams='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_params(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->params_orig, item->params);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_IPMI_SENSOR))
	{
		value_esc = zbx_db_dyn_escape_string(item->ipmi_sensor);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sipmi_sensor='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_ipmi_sensor(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->ipmi_sensor_orig, item->ipmi_sensor);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_OID))
	{
		value_esc = zbx_db_dyn_escape_string(item->snmp_oid);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%ssnmp_oid='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_snmp_oid(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->snmp_oid_orig, item->snmp_oid);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_AUTHTYPE))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sauthtype=%d", d, (int)item_prototype->authtype);
		d = ",";
		zbx_audit_item_update_json_update_authtype(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, (int)item->authtype_orig,
				(int)item_prototype->authtype);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_USERNAME))
	{
		value_esc = zbx_db_dyn_escape_string(item->username);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%susername='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_username(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->username_orig, item->username);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PASSWORD))
	{
		value_esc = zbx_db_dyn_escape_string(item->password);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%spassword='%s'", d, value_esc);
		d = ",";

		zbx_audit_item_update_json_update_password(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, (0 == strcmp("", item->password_orig) ? "" :
				ZBX_MACRO_SECRET_MASK), (0 == strcmp("", item->password) ? "" : ZBX_MACRO_SECRET_MASK));
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PUBLICKEY))
	{
		value_esc = zbx_db_dyn_escape_string(item_prototype->publickey);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%spublickey='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_publickey(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->publickey_orig, item_prototype->publickey);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_PRIVATEKEY))
	{
		value_esc = zbx_db_dyn_escape_string(item_prototype->privatekey);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sprivatekey='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_privatekey(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->privatekey_orig, item_prototype->privatekey);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION))
	{
		value_esc = zbx_db_dyn_escape_string(item->description);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sdescription='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_description(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->description_orig, item->description);
		zbx_free(value_esc);

	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_INTERFACEID))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sinterfaceid=%s",
				d, zbx_db_sql_id_ins(item_prototype->interfaceid));
		d = ",";
		zbx_audit_item_update_json_update_interfaceid(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->interfaceid_orig, item_prototype->interfaceid);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_JMX_ENDPOINT))
	{
		value_esc = zbx_db_dyn_escape_string(item->jmx_endpoint);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sjmx_endpoint='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_jmx_endpoint(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->jmx_endpoint_orig, item->jmx_endpoint);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_MASTER_ITEM))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%smaster_itemid=%s",
				d, zbx_db_sql_id_ins(item->master_itemid));
		d = ",";
		zbx_audit_item_update_json_update_master_itemid(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->master_itemid_orig, item->master_itemid);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_TIMEOUT))
	{
		value_esc = zbx_db_dyn_escape_string(item->timeout);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%stimeout='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_timeout(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->timeout_orig, item->timeout);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_URL))
	{
		value_esc = zbx_db_dyn_escape_string(item->url);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%surl='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_url(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->url_orig, item->url);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_QUERY_FIELDS))
	{
		value_esc = zbx_db_dyn_escape_string(item->query_fields);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%squery_fields='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_query_fields(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->query_fields_orig, item->query_fields);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_POSTS))
	{
		value_esc = zbx_db_dyn_escape_string(item->posts);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sposts='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_posts(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->posts_orig, item->posts);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_STATUS_CODES))
	{
		value_esc = zbx_db_dyn_escape_string(item->status_codes);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sstatus_codes='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_status_codes(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->status_codes_orig, item->status_codes);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_FOLLOW_REDIRECTS))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sfollow_redirects=%d", d,
				(int)item_prototype->follow_redirects);
		d = ",";
		zbx_audit_item_update_json_update_follow_redirects(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, (int)item->follow_redirects_orig,
				(int)item_prototype->follow_redirects);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_POST_TYPE))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%spost_type=%d", d, (int)item_prototype->post_type);
		d = ",";
		zbx_audit_item_update_json_update_post_type(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, (int)item->post_type_orig,
				(int)item_prototype->post_type);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_HTTP_PROXY))
	{
		value_esc = zbx_db_dyn_escape_string(item->http_proxy);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%shttp_proxy='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_http_proxy(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->http_proxy_orig, item->http_proxy);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_HEADERS))
	{
		value_esc = zbx_db_dyn_escape_string(item->headers);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sheaders='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_headers(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->headers_orig, item->headers);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_RETRIEVE_MODE))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sretrieve_mode=%d", d,
				(int)item_prototype->retrieve_mode);
		d = ",";
		zbx_audit_item_update_json_update_retrieve_mode(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, (int)item->retrieve_mode_orig,
				(int)item_prototype->retrieve_mode);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_REQUEST_METHOD))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%srequest_method=%d", d,
				(int)item_prototype->request_method);
		d = ",";
		zbx_audit_item_update_json_update_request_method(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, (int)item->request_method_orig,
				(int)item_prototype->request_method);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_OUTPUT_FORMAT))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%soutput_format=%d", d,
				(int)item_prototype->output_format);
		d = ",";
		zbx_audit_item_update_json_update_output_format(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, (int)item->output_format_orig,
				(int)item_prototype->output_format);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SSL_CERT_FILE))
	{
		value_esc = zbx_db_dyn_escape_string(item->ssl_cert_file);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sssl_cert_file='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_ssl_cert_file(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->ssl_cert_file_orig, item->ssl_cert_file);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SSL_KEY_FILE))
	{
		value_esc = zbx_db_dyn_escape_string(item->ssl_key_file);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sssl_key_file='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_ssl_key_file(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, item->ssl_key_file_orig, item->ssl_key_file);
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_SSL_KEY_PASSWORD))
	{
		value_esc = zbx_db_dyn_escape_string(item->ssl_key_password);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sssl_key_password='%s'", d, value_esc);
		d = ",";
		zbx_audit_item_update_json_update_ssl_key_password(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, (0 == strcmp("", item->ssl_key_password_orig) ?
				"" : ZBX_MACRO_SECRET_MASK), (0 == strcmp("", item->ssl_key_password) ? "" :
				ZBX_MACRO_SECRET_MASK));
		zbx_free(value_esc);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_VERIFY_PEER))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sverify_peer=%d", d, (int)item_prototype->verify_peer);
		d = ",";
		zbx_audit_item_update_json_update_verify_peer(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, (int)item->verify_peer_orig,
				(int)item_prototype->verify_peer);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_VERIFY_HOST))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sverify_host=%d", d, (int)item_prototype->verify_host);
		d = ",";
		zbx_audit_item_update_json_update_verify_host(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, (int)item->verify_host_orig,
				(int)item_prototype->verify_host);
	}
	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_ALLOW_TRAPS))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%sallow_traps=%d", d, (int)item_prototype->allow_traps);
		zbx_audit_item_update_json_update_allow_traps(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
				(int)ZBX_FLAG_DISCOVERY_CREATED, (int)item->allow_traps_orig,
				(int)item_prototype->allow_traps);
	}

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where itemid=" ZBX_FS_UI64 ";\n", item->itemid);

	zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);

	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_NAME))
	{
		value_esc = zbx_db_dyn_escape_string(item->name);

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "update item_rtname set"
				" name_resolved='%s',name_resolved_upper=upper('%s')"
				" where itemid=" ZBX_FS_UI64 ";\n",
				value_esc, value_esc, item->itemid);
		zbx_free(value_esc);
		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepares SQL to update key in LLD item discovery                  *
 *                                                                            *
 * Parameters: item_prototype       - [IN]                                    *
 *             item                 - [IN] item to be updated                 *
 *             sql                  - [IN/OUT] SQL buffer pointer used for    *
 *                                             update operations              *
 *             sql_alloc            - [IN/OUT] SQL buffer already allocated   *
 *                                             memory                         *
 *             sql_offset           - [IN/OUT] offset for writing within SQL  *
 *                                             buffer                         *
 *                                                                            *
 ******************************************************************************/
static void lld_item_discovery_prepare_update(const zbx_lld_item_prototype_t *item_prototype,
		const zbx_lld_item_full_t *item, char **sql, size_t *sql_alloc, size_t *sql_offset)
{
	char	*value_esc;

	if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_KEY))
	{
		value_esc = zbx_db_dyn_escape_string(item_prototype->key);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update item_discovery"
				" set key_='%s'"
				" where itemid=" ZBX_FS_UI64 ";\n",
				value_esc, item->itemid);
		zbx_free(value_esc);

		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);
	}
}

/******************************************************************************
 *                                                                            *
 * Parameters: hostid          - [IN] parent host id                          *
 *             item_prototypes - [IN]                                         *
 *             items           - [IN/OUT] items to save                       *
 *             items_index     - [IN] LLD item index                          *
 *             host_locked     - [IN/OUT] host record is locked               *
 *                                                                            *
 * Return value: SUCCEED - if items were successfully saved or saving was not *
 *                         necessary                                          *
 *               FAIL    - items cannot be saved                              *
 *                                                                            *
 ******************************************************************************/
static int	lld_items_save(zbx_uint64_t hostid, const zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		zbx_vector_lld_item_full_ptr_t *items, zbx_hashset_t *items_index, int *host_locked)
{
	int				ret = SUCCEED, new_items = 0, upd_items = 0;
	zbx_lld_item_full_t		*item;
	zbx_uint64_t			itemid, itemdiscoveryid;
	zbx_db_insert_t			db_insert_items, db_insert_idiscovery, db_insert_irtdata, db_insert_irtname;
	zbx_lld_item_index_t		item_index_local;
	zbx_vector_uint64_t		item_protoids;
	char				*sql = NULL;
	size_t				sql_alloc = 8 * ZBX_KIBIBYTE, sql_offset = 0;
	zbx_lld_item_prototype_t	*item_prototype;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&item_protoids);

	if (0 == items->values_num)
		goto out;

	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == item->itemid)
		{
			new_items++;
		}
		else
		{
			zbx_audit_item_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE, item->itemid,
					(NULL == item->name_proto) ? item->name : item->name_proto,
					ZBX_FLAG_DISCOVERY_CREATED);
		}

		if (0 != item->itemid && 0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE))
			upd_items++;
	}

	if (0 == new_items && 0 == upd_items)
		goto out;

	if (0 == *host_locked)
	{
		if (SUCCEED != zbx_db_lock_hostid(hostid))
		{
			/* the host was removed while processing lld rule */
			ret = FAIL;
			goto out;
		}

		*host_locked = 1;
	}

	for (int i = 0; i < item_prototypes->values_num; i++)
	{
		item_prototype = item_prototypes->values[i];
		zbx_vector_uint64_append(&item_protoids, item_prototype->itemid);
	}

	if (SUCCEED != zbx_db_lock_itemids(&item_protoids))
	{
		/* the item prototype was removed while processing lld rule */
		ret = FAIL;
		goto out;
	}

	if (0 != upd_items)
		sql = (char*)zbx_malloc(NULL, sql_alloc);

	if (0 != new_items)
	{
		itemid = zbx_db_get_maxid_num("items", new_items);
		itemdiscoveryid = zbx_db_get_maxid_num("item_discovery", new_items);

		zbx_db_insert_prepare(&db_insert_items, "items", "itemid", "name", "key_", "hostid", "type",
				"value_type", "delay", "history", "trends", "status", "trapper_hosts",
				"units", "formula", "logtimefmt", "valuemapid", "params",
				"ipmi_sensor", "snmp_oid", "authtype", "username", "password",
				"publickey", "privatekey", "description", "interfaceid", "flags",
				"jmx_endpoint", "master_itemid", "timeout", "url", "query_fields", "posts",
				"status_codes", "follow_redirects", "post_type", "http_proxy", "headers",
				"retrieve_mode", "request_method", "output_format", "ssl_cert_file", "ssl_key_file",
				"ssl_key_password", "verify_peer", "verify_host", "allow_traps", (char *)NULL);

		zbx_db_insert_prepare(&db_insert_idiscovery, "item_discovery", "itemdiscoveryid", "itemid",
				"parent_itemid", "key_", "lastcheck", (char *)NULL);

		zbx_db_insert_prepare(&db_insert_irtdata, "item_rtdata", "itemid", (char *)NULL);
		zbx_db_insert_prepare(&db_insert_irtname, "item_rtname", "itemid", "name_resolved",
				"name_resolved_upper", (char *)NULL);
	}

	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		/* dependent items based on item prototypes are saved within recursive lld_item_save calls while */
		/* saving master item */
		if (0 == item->master_itemid)
		{
			lld_item_save(hostid, item_prototypes, item, &itemid, &itemdiscoveryid, &db_insert_items,
					&db_insert_idiscovery, &db_insert_irtdata, &db_insert_irtname);
		}
		else
		{
			item_index_local.parent_itemid = item->master_itemid;
			item_index_local.lld_row = (zbx_lld_row_t *)item->lld_row;

			/* dependent item based on host item should be saved */
			if (NULL == zbx_hashset_search(items_index, &item_index_local))
			{
				lld_item_save(hostid, item_prototypes, item, &itemid, &itemdiscoveryid,
						&db_insert_items, &db_insert_idiscovery, &db_insert_irtdata,
						&db_insert_irtname);
			}
		}

	}

	if (0 != new_items)
	{
		zbx_db_insert_execute(&db_insert_items);
		zbx_db_insert_clean(&db_insert_items);

		zbx_db_insert_execute(&db_insert_idiscovery);
		zbx_db_insert_clean(&db_insert_idiscovery);

		zbx_db_insert_execute(&db_insert_irtname);
		zbx_db_insert_clean(&db_insert_irtname);
		zbx_db_insert_execute(&db_insert_irtdata);
		zbx_db_insert_clean(&db_insert_irtdata);

		zbx_vector_lld_item_full_ptr_sort(items, lld_item_full_compare_func);
	}

	if (0 != upd_items)
	{
		int	index;

		sql_offset = 0;

		for (int i = 0; i < items->values_num; i++)
		{
			item = items->values[i];

			if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED) ||
					0 == (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE))
			{
				continue;
			}

			zbx_lld_item_prototype_t	cmp = {.itemid = item->parent_itemid};

			if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &cmp,
					lld_item_prototype_compare_func)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			item_prototype = item_prototypes->values[index];

			lld_item_prepare_update(item_prototype, item, &sql, &sql_alloc, &sql_offset);
			lld_item_discovery_prepare_update(item_prototype, item, &sql, &sql_alloc, &sql_offset);
		}

		(void)zbx_db_flush_overflowed_sql(sql, sql_offset);
	}
out:
	zbx_free(sql);
	zbx_vector_uint64_destroy(&item_protoids);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: saves/updates/removes item preprocessing operations               *
 *                                                                            *
 * Parameters: hostid      - [IN] parent host id                              *
 *             items       - [IN]                                             *
 *             host_locked - [IN/OUT] host record is locked                   *
 *                                                                            *
 ******************************************************************************/
static int	lld_items_preproc_save(zbx_uint64_t hostid, zbx_vector_lld_item_full_ptr_t *items, int *host_locked)
{
	int			ret = SUCCEED, new_preproc_num = 0, update_preproc_num = 0,
				delete_preproc_num = 0;
	zbx_lld_item_full_t	*item;
	zbx_lld_item_preproc_t	*preproc_op;
	zbx_vector_uint64_t	deleteids;
	zbx_db_insert_t		db_insert;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		new_preprocid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&deleteids);

	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->preproc_ops.values_num; j++)
		{
			preproc_op = item->preproc_ops.values[j];

			if (0 == (preproc_op->flags & ZBX_FLAG_LLD_ITEM_PREPROC_DISCOVERED))
			{
				zbx_vector_uint64_append(&deleteids, preproc_op->item_preprocid);
				zbx_audit_item_delete_preproc(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, preproc_op->item_preprocid);
				continue;
			}

			if (0 == preproc_op->item_preprocid)
			{
				new_preproc_num++;
				continue;
			}

			if (0 == (preproc_op->flags & ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE))
				continue;

			update_preproc_num++;
		}
	}

	if (0 == *host_locked && (0 != update_preproc_num || 0 != new_preproc_num || 0 != deleteids.values_num))
	{
		if (SUCCEED != zbx_db_lock_hostid(hostid))
		{
			/* the host was removed while processing lld rule */
			ret = FAIL;
			goto out;
		}

		*host_locked = 1;
	}

	if (0 != new_preproc_num)
	{
		new_preprocid = zbx_db_get_maxid_num("item_preproc", new_preproc_num);
		zbx_db_insert_prepare(&db_insert, "item_preproc", "item_preprocid", "itemid", "step", "type", "params",
				"error_handler", "error_handler_params", (char *)NULL);
	}

	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->preproc_ops.values_num; j++)
		{
			char	delim = ' ';

			preproc_op = item->preproc_ops.values[j];

			if (0 == preproc_op->item_preprocid)
			{
				zbx_db_insert_add_values(&db_insert, new_preprocid, item->itemid, preproc_op->step,
						preproc_op->type, preproc_op->params, preproc_op->error_handler,
						preproc_op->error_handler_params);
				zbx_audit_item_update_json_add_item_preproc(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
						new_preprocid, (int)ZBX_FLAG_DISCOVERY_CREATED, preproc_op->step,
						preproc_op->type, preproc_op->params, preproc_op->error_handler,
						preproc_op->error_handler_params);
				new_preprocid++;
				continue;
			}

			if (0 == (preproc_op->flags & ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE))
				continue;

			zbx_audit_item_update_json_update_item_preproc_create_entry(ZBX_AUDIT_LLD_CONTEXT,
					item->itemid, (int)ZBX_FLAG_DISCOVERY_CREATED, preproc_op->item_preprocid);

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update item_preproc set");

			if (0 != (preproc_op->flags & ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%ctype=%d", delim, preproc_op->type);
				delim = ',';

				zbx_audit_item_update_json_update_item_preproc_type(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, preproc_op->item_preprocid,
						preproc_op->type_orig, preproc_op->type);
			}

			if (0 != (preproc_op->flags & ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_STEP))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cstep=%d", delim, preproc_op->step);
				delim = ',';
			}

			if (0 != (preproc_op->flags & ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_PARAMS))
			{
				char	*params_esc;

				params_esc = zbx_db_dyn_escape_string(preproc_op->params);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cparams='%s'", delim, params_esc);

				delim = ',';
				zbx_audit_item_update_json_update_item_preproc_params(ZBX_AUDIT_LLD_CONTEXT,
						item->itemid, (int)ZBX_FLAG_DISCOVERY_CREATED,
						preproc_op->item_preprocid, preproc_op->params_orig,
						preproc_op->params);
				zbx_free(params_esc);
			}

			if (0 != (preproc_op->flags & ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_ERROR_HANDLER))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cerror_handler=%d", delim,
						preproc_op->error_handler);
				delim = ',';

				zbx_audit_item_update_json_update_item_preproc_error_handler(ZBX_AUDIT_LLD_CONTEXT,
						item->itemid, (int)ZBX_FLAG_DISCOVERY_CREATED,
						preproc_op->item_preprocid, preproc_op->error_handler_orig,
						preproc_op->error_handler);
			}

			if (0 != (preproc_op->flags & ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_ERROR_HANDLER_PARAMS))
			{
				char	*params_esc;

				params_esc = zbx_db_dyn_escape_string(preproc_op->error_handler_params);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cerror_handler_params='%s'", delim,
						params_esc);

				zbx_audit_item_update_json_update_item_preproc_error_handler_params(
						ZBX_AUDIT_LLD_CONTEXT, item->itemid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, preproc_op->item_preprocid,
						preproc_op->error_handler_params_orig,
						preproc_op->error_handler_params);

				zbx_free(params_esc);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where item_preprocid=" ZBX_FS_UI64 ";\n",
					preproc_op->item_preprocid);

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}
	}

	if (0 != update_preproc_num)
		(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	if (0 != new_preproc_num)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	if (0 != deleteids.values_num)
	{
		zbx_vector_uint64_sort(&deleteids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from item_preproc where", "item_preprocid", &deleteids);
		delete_preproc_num = deleteids.values_num;
	}
out:
	zbx_free(sql);
	zbx_vector_uint64_destroy(&deleteids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() added:%d updated:%d removed:%d", __func__, new_preproc_num,
			update_preproc_num, delete_preproc_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: saves/updates/removes item parameters                             *
 *                                                                            *
 * Parameters: hostid      - [IN] parent host id                              *
 *             items       - [IN]                                             *
 *             host_locked - [IN/OUT] host record is locked                   *
 *                                                                            *
 ******************************************************************************/
static int	lld_items_param_save(zbx_uint64_t hostid, zbx_vector_lld_item_full_ptr_t *items, int *host_locked)
{
	int			ret = SUCCEED, new_param_num = 0, update_param_num = 0, delete_param_num = 0;
	zbx_lld_item_full_t	*item;
	zbx_item_param_t	*item_param;
	zbx_vector_uint64_t	deleteids;
	zbx_db_insert_t		db_insert;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		new_paramid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&deleteids);

	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->item_params.values_num; j++)
		{
			item_param = item->item_params.values[j];

			if (0 != (item_param->flags & ZBX_FLAG_ITEM_PARAM_DELETE))
			{
				zbx_vector_uint64_append(&deleteids, item_param->item_parameterid);
				zbx_audit_item_delete_params(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, item_param->item_parameterid);
				continue;
			}

			if (0 == item_param->item_parameterid)
			{
				new_param_num++;
				continue;
			}

			if (0 == (item_param->flags & ZBX_FLAG_ITEM_PARAM_UPDATE))
				continue;

			update_param_num++;
		}
	}

	if (0 == update_param_num && 0 == new_param_num && 0 == deleteids.values_num)
		goto out;

	if (0 == *host_locked)
	{
		if (SUCCEED != zbx_db_lock_hostid(hostid))
		{
			/* the host was removed while processing lld rule */
			ret = FAIL;
			goto out;
		}

		*host_locked = 1;
	}

	if (0 != new_param_num)
	{
		new_paramid = zbx_db_get_maxid_num("item_parameter", new_param_num);
		zbx_db_insert_prepare(&db_insert, "item_parameter", "item_parameterid", "itemid", "name", "value",
				(char *)NULL);
	}

	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->item_params.values_num; j++)
		{
			char	delim = ' ';

			item_param = item->item_params.values[j];

			if (0 == item_param->item_parameterid)
			{
				zbx_db_insert_add_values(&db_insert, new_paramid, item->itemid, item_param->name,
						item_param->value);

				zbx_audit_item_update_json_add_params(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, new_paramid, item_param->name,
						item_param->value);

				new_paramid++;
				continue;
			}

			if (0 == (item_param->flags & ZBX_FLAG_ITEM_PARAM_UPDATE))
				continue;

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update item_parameter set");

			if (0 != (item_param->flags & ZBX_FLAG_ITEM_PARAM_UPDATE_NAME))
			{
				char	*name_esc;

				name_esc = zbx_db_dyn_escape_string(item_param->name);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cname='%s'", delim, name_esc);

				delim = ',';
				zbx_audit_item_update_json_update_params_name(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, item_param->item_parameterid,
						item_param->name_orig, item_param->name);
				zbx_free(name_esc);
			}

			if (0 != (item_param->flags & ZBX_FLAG_ITEM_PARAM_UPDATE_VALUE))
			{
				char	*value_esc;

				value_esc = zbx_db_dyn_escape_string(item_param->value);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cvalue='%s'", delim, value_esc);

				zbx_audit_item_update_json_update_params_value(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, item_param->item_parameterid,
						item_param->value_orig, item_param->value);

				zbx_free(value_esc);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where item_parameterid=" ZBX_FS_UI64 ";\n",
					item_param->item_parameterid);

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}
	}

	if (0 != update_param_num)
		(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	if (0 != new_param_num)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	if (0 != deleteids.values_num)
	{
		zbx_vector_uint64_sort(&deleteids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from item_parameter where", "item_parameterid", &deleteids);
		delete_param_num = deleteids.values_num;
	}
out:
	zbx_free(sql);
	zbx_vector_uint64_destroy(&deleteids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() added:%d updated:%d removed:%d", __func__, new_param_num,
			update_param_num, delete_param_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: saves/updates/removes item tags                                   *
 *                                                                            *
 * Parameters: hostid      - [IN] parent host id                              *
 *             items       - [IN]                                             *
 *             host_locked - [IN/OUT] host record is locked                   *
 *                                                                            *
 ******************************************************************************/
static int	lld_items_tags_save(zbx_uint64_t hostid, zbx_vector_lld_item_full_ptr_t *items, int *host_locked)
{
	int			ret = SUCCEED, new_tag_num = 0, update_tag_num = 0, delete_tag_num = 0;
	zbx_lld_item_full_t	*item;
	zbx_db_tag_t		*item_tag;
	zbx_vector_uint64_t	deleteids;
	zbx_db_insert_t		db_insert;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		new_tagid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&deleteids);

	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->item_tags.values_num; j++)
		{
			item_tag = item->item_tags.values[j];

			if (0 != (item_tag->flags & ZBX_FLAG_DB_TAG_REMOVE))
			{
				zbx_vector_uint64_append(&deleteids, item_tag->tagid);
				zbx_audit_item_delete_tag(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, item_tag->tagid);
				continue;
			}

			if (0 == item_tag->tagid)
			{
				new_tag_num++;
				continue;
			}

			if (0 == (item_tag->flags & ZBX_FLAG_DB_TAG_UPDATE))
				continue;

			update_tag_num++;
		}
	}

	if (0 == update_tag_num && 0 == new_tag_num && 0 == deleteids.values_num)
		goto out;

	if (0 == *host_locked)
	{
		if (SUCCEED != zbx_db_lock_hostid(hostid))
		{
			/* the host was removed while processing lld rule */
			ret = FAIL;
			goto out;
		}

		*host_locked = 1;
	}

	if (0 != new_tag_num)
	{
		new_tagid = zbx_db_get_maxid_num("item_tag", new_tag_num);
		zbx_db_insert_prepare(&db_insert, "item_tag", "itemtagid", "itemid", "tag", "value",
				(char *)NULL);
	}

	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->item_tags.values_num; j++)
		{
			char	delim = ' ';

			item_tag = item->item_tags.values[j];

			if (0 == item_tag->tagid)
			{
				zbx_db_insert_add_values(&db_insert, new_tagid, item->itemid, item_tag->tag,
						item_tag->value);
				zbx_audit_item_update_json_add_item_tag(ZBX_AUDIT_LLD_CONTEXT, item->itemid, new_tagid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, item_tag->tag, item_tag->value);
				new_tagid++;
				continue;
			}

			if (0 == (item_tag->flags & ZBX_FLAG_DB_TAG_UPDATE))
				continue;

			zbx_audit_item_update_json_update_item_tag_create_entry(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, item_tag->tagid);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update item_tag set");

			if (0 != (item_tag->flags & ZBX_FLAG_DB_TAG_UPDATE_TAG))
			{
				char	*tag_esc;

				tag_esc = zbx_db_dyn_escape_string(item_tag->tag);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%ctag='%s'", delim, tag_esc);

				zbx_audit_item_update_json_update_item_tag_tag(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, item_tag->tagid,
						item_tag->tag_orig, item_tag->tag);
				zbx_free(tag_esc);
				delim = ',';
			}

			if (0 != (item_tag->flags & ZBX_FLAG_DB_TAG_UPDATE_VALUE))
			{
				char	*value_esc;

				value_esc = zbx_db_dyn_escape_string(item_tag->value);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cvalue='%s'", delim, value_esc);

				zbx_audit_item_update_json_update_item_tag_value(ZBX_AUDIT_LLD_CONTEXT, item->itemid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, item_tag->tagid,
						item_tag->value_orig, item_tag->value);

				zbx_free(value_esc);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where itemtagid=" ZBX_FS_UI64 ";\n",
					item_tag->tagid);

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}
	}

	if (0 != update_tag_num)
		(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	if (0 != new_tag_num)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	if (0 != deleteids.values_num)
	{
		zbx_vector_uint64_sort(&deleteids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from item_tag where", "itemtagid", &deleteids);
		delete_tag_num = deleteids.values_num;
	}
out:
	zbx_free(sql);
	zbx_vector_uint64_destroy(&deleteids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() added:%d updated:%d removed:%d", __func__, new_tag_num,
			update_tag_num, delete_tag_num);

	return ret;
}

static	int	get_item_status_value(int status)
{
	if (ZBX_LLD_OBJECT_STATUS_ENABLED == status)
		return ITEM_STATUS_ACTIVE;

	return ITEM_STATUS_DISABLED;
}

static void	lld_item_links_populate(const zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		zbx_vector_lld_row_ptr_t *lld_rows, zbx_hashset_t *items_index)
{
	zbx_lld_item_prototype_t	*item_prototype;
	zbx_lld_item_index_t		*item_index, item_index_local;
	zbx_lld_item_link_t		*item_link;

	for (int i = 0; i < item_prototypes->values_num; i++)
	{
		item_prototype = item_prototypes->values[i];
		item_index_local.parent_itemid = item_prototype->itemid;

		for (int j = 0; j < lld_rows->values_num; j++)
		{
			item_index_local.lld_row = lld_rows->values[j];

			if (NULL == (item_index = (zbx_lld_item_index_t *)zbx_hashset_search(items_index,
					&item_index_local)))
			{
				continue;
			}

			if (0 == (item_index->item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
				continue;

			item_link = (zbx_lld_item_link_t *)zbx_malloc(NULL, sizeof(zbx_lld_item_link_t));

			item_link->parent_itemid = item_index->item->parent_itemid;
			item_link->itemid = item_index->item->itemid;

			zbx_vector_lld_item_link_ptr_append(&item_index_local.lld_row->item_links, item_link);
		}
	}
}

void	lld_item_links_sort(zbx_vector_lld_row_ptr_t *lld_rows)
{
	for (int i = 0; i < lld_rows->values_num; i++)
	{
		zbx_lld_row_t	*lld_row = lld_rows->values[i];

		zbx_vector_lld_item_link_ptr_sort(&lld_row->item_links, lld_item_link_compare_func);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: loads discovery rule item prototypes                              *
 *                                                                            *
 * Parameters: lld_ruleid      - [IN]                                         *
 *             item_prototypes - [OUT]                                        *
 *                                                                            *
 ******************************************************************************/
static void	lld_item_prototypes_get(zbx_uint64_t lld_ruleid, zbx_vector_lld_item_prototype_ptr_t *item_prototypes)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_lld_item_prototype_t	*item_prototype;
	zbx_lld_item_preproc_t		*preproc_op;
	zbx_item_param_t		*item_param;
	zbx_uint64_t			itemid;
	int				index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select(
			"select i.itemid,i.name,i.key_,i.type,i.value_type,i.delay,"
				"i.history,i.trends,i.status,i.trapper_hosts,i.units,i.formula,"
				"i.logtimefmt,i.valuemapid,i.params,i.ipmi_sensor,i.snmp_oid,i.authtype,"
				"i.username,i.password,i.publickey,i.privatekey,i.description,i.interfaceid,"
				"i.jmx_endpoint,i.master_itemid,i.timeout,i.url,i.query_fields,"
				"i.posts,i.status_codes,i.follow_redirects,i.post_type,i.http_proxy,i.headers,"
				"i.retrieve_mode,i.request_method,i.output_format,i.ssl_cert_file,i.ssl_key_file,"
				"i.ssl_key_password,i.verify_peer,i.verify_host,i.allow_traps,i.discover"
			" from items i,item_discovery id"
			" where i.itemid=id.itemid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		item_prototype = (zbx_lld_item_prototype_t *)zbx_malloc(NULL, sizeof(zbx_lld_item_prototype_t));

		ZBX_STR2UINT64(item_prototype->itemid, row[0]);
		item_prototype->name = zbx_strdup(NULL, row[1]);
		item_prototype->key = zbx_strdup(NULL, row[2]);
		zbx_vector_str_create(&item_prototype->keys);
		ZBX_STR2UCHAR(item_prototype->type, row[3]);
		ZBX_STR2UCHAR(item_prototype->value_type, row[4]);
		item_prototype->delay = zbx_strdup(NULL, row[5]);
		item_prototype->history = zbx_strdup(NULL, row[6]);
		item_prototype->trends = zbx_strdup(NULL, row[7]);
		ZBX_STR2UCHAR(item_prototype->status, row[8]);
		item_prototype->trapper_hosts = zbx_strdup(NULL, row[9]);
		item_prototype->units = zbx_strdup(NULL, row[10]);
		item_prototype->formula = zbx_strdup(NULL, row[11]);
		item_prototype->logtimefmt = zbx_strdup(NULL, row[12]);
		ZBX_DBROW2UINT64(item_prototype->valuemapid, row[13]);
		item_prototype->params = zbx_strdup(NULL, row[14]);
		item_prototype->ipmi_sensor = zbx_strdup(NULL, row[15]);
		item_prototype->snmp_oid = zbx_strdup(NULL, row[16]);
		ZBX_STR2UCHAR(item_prototype->authtype, row[17]);
		item_prototype->username = zbx_strdup(NULL, row[18]);
		item_prototype->password = zbx_strdup(NULL, row[19]);
		item_prototype->publickey = zbx_strdup(NULL, row[20]);
		item_prototype->privatekey = zbx_strdup(NULL, row[21]);
		item_prototype->description = zbx_strdup(NULL, row[22]);
		ZBX_DBROW2UINT64(item_prototype->interfaceid, row[23]);
		item_prototype->jmx_endpoint = zbx_strdup(NULL, row[24]);
		ZBX_DBROW2UINT64(item_prototype->master_itemid, row[25]);

		item_prototype->timeout = zbx_strdup(NULL, row[26]);
		item_prototype->url = zbx_strdup(NULL, row[27]);
		item_prototype->query_fields = zbx_strdup(NULL, row[28]);
		item_prototype->posts = zbx_strdup(NULL, row[29]);
		item_prototype->status_codes = zbx_strdup(NULL, row[30]);
		ZBX_STR2UCHAR(item_prototype->follow_redirects, row[31]);
		ZBX_STR2UCHAR(item_prototype->post_type, row[32]);
		item_prototype->http_proxy = zbx_strdup(NULL, row[33]);
		item_prototype->headers = zbx_strdup(NULL, row[34]);
		ZBX_STR2UCHAR(item_prototype->retrieve_mode, row[35]);
		ZBX_STR2UCHAR(item_prototype->request_method, row[36]);
		ZBX_STR2UCHAR(item_prototype->output_format, row[37]);
		item_prototype->ssl_cert_file = zbx_strdup(NULL, row[38]);
		item_prototype->ssl_key_file = zbx_strdup(NULL, row[39]);
		item_prototype->ssl_key_password = zbx_strdup(NULL, row[40]);
		ZBX_STR2UCHAR(item_prototype->verify_peer, row[41]);
		ZBX_STR2UCHAR(item_prototype->verify_host, row[42]);
		ZBX_STR2UCHAR(item_prototype->allow_traps, row[43]);
		ZBX_STR2UCHAR(item_prototype->discover, row[44]);

		zbx_vector_lld_row_ptr_create(&item_prototype->lld_rows);
		zbx_vector_lld_item_preproc_ptr_create(&item_prototype->preproc_ops);
		zbx_vector_item_param_ptr_create(&item_prototype->item_params);
		zbx_vector_db_tag_ptr_create(&item_prototype->item_tags);
		zbx_hashset_create(&item_prototype->item_index, 0, lld_item_ref_key_hash_func,
				lld_item_ref_key_compare_func);

		zbx_vector_lld_item_prototype_ptr_append(item_prototypes, item_prototype);
	}
	zbx_db_free_result(result);

	zbx_vector_lld_item_prototype_ptr_sort(item_prototypes, lld_item_prototype_compare_func);

	if (0 == item_prototypes->values_num)
		goto out;

	/* get item prototype preprocessing options */

	result = zbx_db_select(
			"select ip.itemid,ip.step,ip.type,ip.params,ip.error_handler,ip.error_handler_params"
			" from item_preproc ip,item_discovery id"
			" where ip.itemid=id.itemid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);

		zbx_lld_item_prototype_t	cmp = {.itemid = itemid};

		if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &cmp,
				lld_item_prototype_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_prototype = item_prototypes->values[index];
		preproc_op = zbx_init_lld_item_preproc(0, ZBX_FLAG_LLD_ITEM_PREPROC_UNSET, atoi(row[1]), atoi(row[2]),
				row[3], atoi(row[4]), row[5]);
		zbx_vector_lld_item_preproc_ptr_append(&item_prototype->preproc_ops, preproc_op);
	}

	zbx_db_free_result(result);

	for (int i = 0; i < item_prototypes->values_num; i++)
	{
		item_prototype = item_prototypes->values[i];
		zbx_vector_lld_item_preproc_ptr_sort(&item_prototype->preproc_ops, lld_item_preproc_sort_by_step);
	}

	/* get item prototype parameters */

	result = zbx_db_select(
			"select ip.itemid,ip.name,ip.value"
			" from item_parameter ip,item_discovery id"
			" where ip.itemid=id.itemid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);

		zbx_lld_item_prototype_t	cmp = {.itemid = itemid};

		if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &cmp,
				lld_item_prototype_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_prototype = item_prototypes->values[index];
		item_param = zbx_item_param_create(row[1], row[2]);
		zbx_vector_item_param_ptr_append(&item_prototype->item_params, item_param);
	}
	zbx_db_free_result(result);

	/* get item prototype tags */

	result = zbx_db_select(
			"select it.itemid,it.tag,it.value"
			" from item_tag it,item_discovery id"
			" where it.itemid=id.itemid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_db_tag_t	*db_tag;

		ZBX_STR2UINT64(itemid, row[0]);

		zbx_lld_item_prototype_t	cmp = {.itemid = itemid};

		if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &cmp,
				lld_item_prototype_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_prototype = item_prototypes->values[index];

		db_tag = zbx_db_tag_create(row[1], row[2]);
		zbx_vector_db_tag_ptr_append(&item_prototype->item_tags, db_tag);
	}
	zbx_db_free_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d prototypes", __func__, item_prototypes->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates dependent item index in master item data                  *
 *                                                                            *
 * Parameters: items       - [IN/OUT] LLD items                               *
 *             items_index - [IN] LLD item index                              *
 *                                                                            *
 ******************************************************************************/
static void	lld_link_dependent_items(zbx_vector_lld_item_full_ptr_t *items, zbx_hashset_t *items_index)
{
	zbx_lld_item_full_t	*master;
	zbx_lld_item_index_t	*item_index, item_index_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int i = items->values_num - 1; i >= 0; i--)
	{
		zbx_lld_item_full_t	*item = items->values[i];

		if (0 == item->master_itemid)
			continue;

		item_index_local.parent_itemid = item->master_itemid;
		item_index_local.lld_row = (zbx_lld_row_t *)item->lld_row;

		if (NULL != (item_index = (zbx_lld_item_index_t *)zbx_hashset_search(items_index, &item_index_local)))
		{
			master = item_index->item;

			zbx_vector_lld_item_full_ptr_append(&master->dependent_items, item);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process lost item resources                                       *
 *                                                                            *
 ******************************************************************************/
static void	lld_process_lost_items(zbx_vector_lld_item_full_ptr_t *items, const zbx_lld_lifetime_t *lifetime,
		const zbx_lld_lifetime_t *enabled_lifetime, int now)
{
	zbx_hashset_t	discoveries;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_create(&discoveries, (size_t)items->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item = items->values[i];
		zbx_lld_discovery_t	*discovery;
		unsigned char		object_status;

		object_status = (ITEM_STATUS_DISABLED == item->status ? ZBX_LLD_OBJECT_STATUS_DISABLED :
				ZBX_LLD_OBJECT_STATUS_ENABLED);
		discovery = lld_add_discovery(&discoveries, item->itemid, item->name);

		if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
		{
			lld_process_discovered_object(discovery, item->discovery_status, item->ts_delete,
					item->lastcheck, now);
			lld_enable_discovered_object(discovery, object_status, item->disable_source, item->ts_disable);
			continue;
		}

		/* process lost items */

		lld_process_lost_object(discovery, object_status, item->lastcheck, now, lifetime,
				item->discovery_status, item->disable_source, item->ts_delete);

		lld_disable_lost_object(discovery, object_status, item->lastcheck, now, enabled_lifetime,
				item->ts_disable);
	}

	lld_flush_discoveries(&discoveries, "itemid", "items", "item_discovery", now, get_item_status_value,
			zbx_db_delete_items, zbx_audit_item_create_entry, zbx_audit_item_update_json_update_status);

	zbx_hashset_destroy(&discoveries);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds or updates discovered items                                  *
 *                                                                            *
 * Return value: SUCCEED - if items were successfully added/updated or        *
 *                         adding/updating was not necessary                  *
 *               FAIL    - items cannot be added/updated                      *
 *                                                                            *
 ******************************************************************************/
int	lld_update_items(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, zbx_vector_lld_row_ptr_t *lld_rows,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **error,
		const zbx_lld_lifetime_t *lifetime, const zbx_lld_lifetime_t *enabled_lifetime, int lastcheck)
{
	zbx_vector_lld_item_prototype_ptr_t	item_prototypes;
	zbx_hashset_t				items_index;
	int					ret = SUCCEED, host_record_is_locked = 0;
	zbx_vector_lld_item_full_ptr_t		items;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_lld_item_prototype_ptr_create(&item_prototypes);

	lld_item_prototypes_get(lld_ruleid, &item_prototypes);

	if (0 == item_prototypes.values_num)
		goto out;

	zbx_vector_lld_item_full_ptr_create(&items);
	zbx_hashset_create(&items_index, item_prototypes.values_num * lld_rows->values_num, lld_item_index_hash_func,
			lld_item_index_compare_func);
	zbx_db_begin();
	lld_items_get(&item_prototypes, &items);
	zbx_db_commit();

	lld_items_make(&item_prototypes, lld_rows, lld_macro_paths, &items, &items_index, lastcheck, error);
	lld_items_preproc_make(&item_prototypes, lld_macro_paths, &items);
	lld_items_param_make(&item_prototypes, lld_macro_paths, &items, error);
	lld_items_tags_make(&item_prototypes, lld_macro_paths, &items, error);
	lld_link_dependent_items(&items, &items_index);

	lld_items_validate(hostid, &items, error);

	zbx_db_begin();

	if (SUCCEED == lld_items_save(hostid, &item_prototypes, &items, &items_index, &host_record_is_locked) &&
			SUCCEED == lld_items_param_save(hostid, &items, &host_record_is_locked) &&
			SUCCEED == lld_items_preproc_save(hostid, &items, &host_record_is_locked) &&
			SUCCEED == lld_items_tags_save(hostid, &items, &host_record_is_locked))
	{
		if (ZBX_DB_OK != zbx_db_commit())
		{
			ret = FAIL;
			goto clean;
		}
	}
	else
	{
		zbx_db_rollback();
		goto clean;
	}

	lld_item_links_populate(&item_prototypes, lld_rows, &items_index);
	lld_process_lost_items(&items, lifetime, enabled_lifetime, lastcheck);
clean:
	zbx_hashset_destroy(&items_index);

	zbx_vector_lld_item_full_ptr_clear_ext(&items, lld_item_free);
	zbx_vector_lld_item_full_ptr_destroy(&items);

	zbx_vector_lld_item_prototype_ptr_clear_ext(&item_prototypes, lld_item_prototype_free);
out:
	zbx_vector_lld_item_prototype_ptr_destroy(&item_prototypes);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}
