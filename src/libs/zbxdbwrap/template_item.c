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

#include "zbxdbhigh.h"

#include "template.h"

#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_item.h"

#include "zbxalgo.h"
#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxinterface.h"
#include "zbx_host_constants.h"
#include "zbx_trigger_constants.h"

struct _zbx_template_item_preproc_t
{
	zbx_uint64_t	item_preprocid;
#define ZBX_FLAG_TEMPLATE_ITEM_PREPROC_RESET_FLAG			__UINT64_C(0x000000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_TYPE			__UINT64_C(0x000000000001)
#define ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_PARAMS			__UINT64_C(0x000000000002)
#define ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_ERROR_HANDLER		__UINT64_C(0x000000000004)
#define ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_ERROR_HANDLER_PARAMS	__UINT64_C(0x000000000008)
#define ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE					\
		(ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_TYPE |			\
		ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_PARAMS |			\
		ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_ERROR_HANDLER |		\
		ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_ERROR_HANDLER_PARAMS	\
		)

#define ZBX_FLAG_TEMPLATE_ITEM_PREPROC_DELETE				__UINT64_C(0x000000010000)

	zbx_uint64_t	upd_flags;
	int		step;
	int		type_orig;
	int		type;
	char		*params_orig;
	char		*params;
	int		error_handler_orig;
	int		error_handler;
	char		*error_handler_params_orig;
	char		*error_handler_params;
};

ZBX_PTR_VECTOR_IMPL(item_preproc_ptr, zbx_template_item_preproc_t *)

struct _zbx_template_lld_macro_t
{
	zbx_uint64_t	lld_macro_pathid;
#define ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_RESET_FLAG	__UINT64_C(0x000000000000)
#define ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_LLD_MACRO	__UINT64_C(0x000000000001)
#define ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_PATH		__UINT64_C(0x000000000002)
#define ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE			\
		(ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_LLD_MACRO |	\
		ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_PATH		\
		)

#define ZBX_FLAG_TEMPLATE_LLD_MACRO_DELETE		__UINT64_C(0x000000010000)

	zbx_uint64_t	upd_flags;
	char		*lld_macro_orig;
	char		*lld_macro;
	char		*path_orig;
	char		*path;
};

ZBX_PTR_VECTOR_IMPL(lld_macro_ptr, zbx_template_lld_macro_t *)

/* lld rule condition */
typedef struct
{
	zbx_uint64_t	item_conditionid;
#define ZBX_FLAG_TEMPLATE_ITEM_CONDITION_UPDATE_RESET_FLAG	__UINT64_C(0x00000000)
#define ZBX_FLAG_TEMPLATE_ITEM_CONDITION_UPDATE_MACRO		__UINT64_C(0x00000001)
#define ZBX_FLAG_TEMPLATE_ITEM_CONDITION_UPDATE_VALUE		__UINT64_C(0x00000002)
#define ZBX_FLAG_TEMPLATE_ITEM_CONDITION_UPDATE_OPERATOR	__UINT64_C(0x00000004)
	zbx_uint64_t	upd_flags;
	char		*macro_orig;
	char		*macro;
	char		*value_orig;
	char		*value;
	unsigned char	op_orig;
	unsigned char	op;
}
zbx_lld_rule_condition_t;

/* lld rule */
typedef struct
{
	/* discovery rule source id */
	zbx_uint64_t		templateid;
	/* discovery rule source conditions */
	zbx_vector_ptr_t	conditions;

	/* discovery rule destination id */
	zbx_uint64_t		itemid;
	/* the starting id to be used for destination condition ids */
	zbx_uint64_t		conditionid;
	/* discovery rule destination condition ids */
	zbx_vector_uint64_t	conditionids;
}
zbx_lld_rule_map_t;

typedef struct
{
	zbx_uint64_t				overrideid;
	zbx_uint64_t				itemid;
	char					*name;
	char					*formula;
	zbx_vector_ptr_t			override_conditions;
	zbx_vector_lld_override_operation_t	override_operations;
	unsigned char				step;
	unsigned char				evaltype;
	unsigned char				stop;
}
lld_override_t;

typedef struct
{
	zbx_uint64_t		override_conditionid;
	char			*macro;
	char			*value;
	unsigned char		operator;
}
lld_override_codition_t;

/* auxiliary function for DBcopy_template_items() */
static void	DBget_interfaces_by_hostid(zbx_uint64_t hostid, zbx_uint64_t *interfaceids)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	unsigned char	type;

	result = zbx_db_select(
			"select type,interfaceid"
			" from interface"
			" where hostid=" ZBX_FS_UI64
				" and type in (%d,%d,%d,%d)"
				" and main=1",
			hostid, INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_IPMI, INTERFACE_TYPE_JMX);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UCHAR(type, row[0]);
		ZBX_STR2UINT64(interfaceids[type - 1], row[1]);
	}
	zbx_db_free_result(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: read template items from database                                 *
 *                                                                            *
 * Parameters: hostid      - [IN] host id                                     *
 *             templateids - [IN] array of template IDs                       *
 *             items       - [OUT] the item data                              *
 *                                                                            *
 * Comments: The itemid and key are set depending on whether the item exists  *
 *           for the specified host.                                          *
 *           If item exists itemid will be set to its itemid and key will be  *
 *           set to NULL.                                                     *
 *           If item does not exist, itemid will be set to 0 and key will be  *
 *           set to item key.                                                 *
 *                                                                            *
 ******************************************************************************/
static void	get_template_items(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids, zbx_vector_ptr_t *items)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0, i;
	unsigned char		interface_type;
	zbx_template_item_t	*item;
	zbx_uint64_t		interfaceids[4];

	memset(&interfaceids, 0, sizeof(interfaceids));
	DBget_interfaces_by_hostid(hostid, interfaceids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select ti.itemid,ti.name,ti.key_,ti.type,ti.value_type,ti.delay,ti.history,ti.trends,"
				"ti.status,ti.trapper_hosts,ti.units,ti.formula,ti.logtimefmt,ti.valuemapid,"
				"ti.params,ti.ipmi_sensor,ti.snmp_oid,ti.authtype,ti.username,ti.password,"
				"ti.publickey,ti.privatekey,ti.flags,ti.description,ti.inventory_link,ti.lifetime,"
				"hi.itemid,ti.evaltype,ti.jmx_endpoint,ti.master_itemid,ti.timeout,ti.url,"
				"ti.query_fields,ti.posts,ti.status_codes,ti.follow_redirects,ti.post_type,"
				"ti.http_proxy,ti.headers,ti.retrieve_mode,ti.request_method,ti.output_format,"
				"ti.ssl_cert_file,ti.ssl_key_file,ti.ssl_key_password,ti.verify_peer,ti.verify_host,"
				"ti.allow_traps,ti.discover,ti.lifetime_type,ti.enabled_lifetime,"
				"ti.enabled_lifetime_type,"
				"hi.interfaceid,hi.templateid,hi.name,hi.type,hi.value_type,hi.delay,hi.history,"
				"hi.trends,hi.status,hi.trapper_hosts,hi.units,hi.formula,hi.logtimefmt,hi.valuemapid,"
				"hi.params,hi.ipmi_sensor,hi.snmp_oid,hi.authtype,hi.username,hi.password,hi.publickey,"
				"hi.privatekey,hi.flags,hi.description,hi.inventory_link,hi.lifetime,hi.evaltype,"
				"hi.jmx_endpoint,hi.master_itemid,hi.timeout,hi.url,hi.query_fields,hi.posts,"
				"hi.status_codes,hi.follow_redirects,hi.post_type,hi.http_proxy,hi.headers,"
				"hi.retrieve_mode,hi.request_method,hi.output_format,hi.ssl_cert_file,hi.ssl_key_file,"
				"hi.ssl_key_password,hi.verify_peer,hi.verify_host,hi.allow_traps,hi.discover,"
				"hi.lifetime_type,hi.enabled_lifetime,hi.enabled_lifetime_type"
			" from items ti"
			" left join items hi on hi.key_=ti.key_"
				" and hi.hostid=" ZBX_FS_UI64
			" where",
			hostid);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values,
			templateids->values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		item = (zbx_template_item_t *)zbx_malloc(NULL, sizeof(zbx_template_item_t));

		item->templateid_orig = 0;
		ZBX_STR2UINT64(item->templateid, row[0]);

		item->type_orig = 0;
		ZBX_STR2UCHAR(item->type, row[3]);

		item->value_type_orig = 0;
		ZBX_STR2UCHAR(item->value_type, row[4]);

		item->status_orig = 0;
		ZBX_STR2UCHAR(item->status, row[8]);

		item->valuemapid_orig = 0;
		ZBX_DBROW2UINT64(item->valuemapid, row[13]);

		item->authtype_orig = 0;
		ZBX_STR2UCHAR(item->authtype, row[17]);

		item->flags_orig = 0;
		ZBX_STR2UCHAR(item->flags, row[22]);

		item->inventory_link_orig = 0;
		ZBX_STR2UCHAR(item->inventory_link, row[24]);

		item->evaltype_orig = 0;
		ZBX_STR2UCHAR(item->evaltype, row[27]);

		item->interfaceid_orig = 0;
		switch (interface_type = zbx_get_interface_type_by_item_type(item->type))
		{
			case INTERFACE_TYPE_UNKNOWN:
			case INTERFACE_TYPE_OPT:
				item->interfaceid = 0;
				break;
			case INTERFACE_TYPE_ANY:
				for (i = 0; INTERFACE_TYPE_COUNT > i; i++)
				{
					if (0 != interfaceids[zbx_get_interface_type_priority(i) - 1])
						break;
				}
				item->interfaceid = interfaceids[zbx_get_interface_type_priority(i) - 1];
				break;
			default:
				item->interfaceid = interfaceids[interface_type - 1];
		}

		item->name_orig = NULL;
		item->name = zbx_strdup(NULL, row[1]);

		item->delay_orig = NULL;
		item->delay = zbx_strdup(NULL, row[5]);

		item->history_orig = NULL;
		item->history = zbx_strdup(NULL, row[6]);

		item->trends_orig = NULL;
		item->trends = zbx_strdup(NULL, row[7]);

		item->trapper_hosts_orig = NULL;
		item->trapper_hosts = zbx_strdup(NULL, row[9]);

		item->units_orig = NULL;
		item->units = zbx_strdup(NULL, row[10]);

		item->formula_orig = NULL;
		item->formula = zbx_strdup(NULL, row[11]);

		item->logtimefmt_orig = NULL;
		item->logtimefmt = zbx_strdup(NULL, row[12]);

		item->params_orig = NULL;
		item->params = zbx_strdup(NULL, row[14]);

		item->ipmi_sensor_orig = NULL;
		item->ipmi_sensor = zbx_strdup(NULL, row[15]);

		item->snmp_oid_orig = NULL;
		item->snmp_oid = zbx_strdup(NULL, row[16]);

		item->username_orig = NULL;
		item->username = zbx_strdup(NULL, row[18]);

		item->password_orig = NULL;
		item->password = zbx_strdup(NULL, row[19]);

		item->publickey_orig = NULL;
		item->publickey = zbx_strdup(NULL, row[20]);

		item->privatekey_orig = NULL;
		item->privatekey = zbx_strdup(NULL, row[21]);

		item->description_orig = NULL;
		item->description = zbx_strdup(NULL, row[23]);

		item->lifetime_orig = NULL;
		item->lifetime = zbx_strdup(NULL, row[25]);

		item->lifetime_type_orig = 0;
		ZBX_STR2UCHAR(item->lifetime_type, row[49]);

		item->enabled_lifetime_orig = NULL;
		item->enabled_lifetime = zbx_strdup(NULL, row[50]);

		item->enabled_lifetime_type_orig = 0;
		ZBX_STR2UCHAR(item->enabled_lifetime_type, row[51]);

		item->jmx_endpoint_orig = NULL;
		item->jmx_endpoint = zbx_strdup(NULL, row[28]);

		ZBX_DBROW2UINT64(item->master_itemid_orig, row[80]);
		ZBX_DBROW2UINT64(item->master_itemid, row[29]);

		item->timeout_orig = NULL;
		item->timeout = zbx_strdup(NULL, row[30]);

		item->url_orig = NULL;
		item->url = zbx_strdup(NULL, row[31]);

		item->query_fields_orig = NULL;
		item->query_fields = zbx_strdup(NULL, row[32]);

		item->posts_orig = NULL;
		item->posts = zbx_strdup(NULL, row[33]);

		item->status_codes_orig = NULL;
		item->status_codes = zbx_strdup(NULL, row[34]);

		item->follow_redirects_orig = 0;
		ZBX_STR2UCHAR(item->follow_redirects, row[35]);

		item->post_type_orig = 0;
		ZBX_STR2UCHAR(item->post_type, row[36]);

		item->http_proxy_orig = NULL;
		item->http_proxy = zbx_strdup(NULL, row[37]);

		item->headers_orig = NULL;
		item->headers = zbx_strdup(NULL, row[38]);

		item->retrieve_mode_orig = 0;
		ZBX_STR2UCHAR(item->retrieve_mode, row[39]);

		item->request_method_orig = 0;
		ZBX_STR2UCHAR(item->request_method, row[40]);

		item->output_format_orig = 0;
		ZBX_STR2UCHAR(item->output_format, row[41]);

		item->ssl_cert_file_orig = NULL;
		item->ssl_cert_file = zbx_strdup(NULL, row[42]);

		item->ssl_key_file_orig = NULL;
		item->ssl_key_file = zbx_strdup(NULL, row[43]);

		item->ssl_key_password_orig = NULL;
		item->ssl_key_password = zbx_strdup(NULL, row[44]);

		item->verify_peer_orig = 0;
		ZBX_STR2UCHAR(item->verify_peer, row[45]);

		item->verify_host_orig = 0;
		ZBX_STR2UCHAR(item->verify_host, row[46]);

		item->allow_traps_orig = 0;
		ZBX_STR2UCHAR(item->allow_traps, row[47]);

		item->discover_orig = 0;
		ZBX_STR2UCHAR(item->discover, row[48]);

		item->upd_flags = ZBX_FLAG_TEMPLATE_ITEM_UPDATE_RESET_FLAG;

		if (SUCCEED != zbx_db_is_null(row[26]))
		{
			unsigned char	uchar_orig;
			zbx_uint64_t	uint64_orig;

#define SET_FLAG_STR(r, i, f)			\
{						\
	if (0 != strcmp(r, (i)))		\
	{					\
		item->upd_flags |= f;		\
		i##_orig = zbx_strdup(NULL, r);	\
	}					\
}

#define SET_FLAG_UCHAR(r, i, f)		\
					\
{					\
	ZBX_STR2UCHAR(uchar_orig, (r));	\
	if (uchar_orig != (i))		\
	{				\
		item->upd_flags |= f;	\
		i##_orig = uchar_orig;	\
	}				\
}

#define SET_FLAG_UINT64(r, i, f)				\
	do							\
	{							\
		if (SUCCEED == zbx_db_is_null(r))		\
			uint64_orig = 0;			\
		else						\
			ZBX_STR2UINT64(uint64_orig, (r));	\
		if (uint64_orig != (i))				\
		{						\
			item->upd_flags |= f;			\
			i##_orig = uint64_orig;			\
		}						\
	}							\
	while(0)
			item->key_ = NULL;
			ZBX_STR2UINT64(item->itemid, row[26]);

			SET_FLAG_UINT64(row[52], item->interfaceid, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_INTERFACEID);
			SET_FLAG_UINT64(row[53], item->templateid, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_TEMPLATEID);
			SET_FLAG_STR(row[54], item->name, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_NAME);
			SET_FLAG_UCHAR(row[55], item->type, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_TYPE);
			SET_FLAG_UCHAR(row[56], item->value_type, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_VALUE_TYPE);
			SET_FLAG_STR(row[57], item->delay, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_DELAY);
			SET_FLAG_STR(row[58], item->history, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_HISTORY);
			SET_FLAG_STR(row[59], item->trends, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_TRENDS);
			SET_FLAG_UCHAR(row[60], item->status, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_STATUS);
			SET_FLAG_STR(row[61], item->trapper_hosts, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_TRAPPER_HOSTS);
			SET_FLAG_STR(row[62], item->units, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_UNITS);
			SET_FLAG_STR(row[63], item->formula, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_FORMULA);
			SET_FLAG_STR(row[64], item->logtimefmt, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_LOGTIMEFMT);
			SET_FLAG_UINT64(row[65], item->valuemapid, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_VALUEMAPID);
			SET_FLAG_STR(row[66], item->params, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_PARAMS);
			SET_FLAG_STR(row[67], item->ipmi_sensor, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_IPMI_SENSOR);
			SET_FLAG_STR(row[68], item->snmp_oid, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_SNMP_OID);
			SET_FLAG_UCHAR(row[69], item->authtype, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_AUTHTYPE);
			SET_FLAG_STR(row[70], item->username, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_USERNAME);
			SET_FLAG_STR(row[71], item->password, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_PASSWORD);
			SET_FLAG_STR(row[72], item->publickey, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_PUBLICKEY);
			SET_FLAG_STR(row[73], item->privatekey, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_PRIVATEKEY);
			SET_FLAG_UCHAR(row[74], item->flags, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_FLAGS);
			SET_FLAG_STR(row[75], item->description, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_DESCRIPTION);
			SET_FLAG_UCHAR(row[76], item->inventory_link, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_INVENTORY_LINK);
			SET_FLAG_STR(row[77], item->lifetime, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_LIFETIME);
			SET_FLAG_UCHAR(row[100], item->lifetime_type, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_LIFETIME_TYPE);
			SET_FLAG_STR(row[101], item->enabled_lifetime, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_ENABLED_LIFETIME);
			SET_FLAG_UCHAR(row[102], item->enabled_lifetime_type,
					ZBX_FLAG_TEMPLATE_ITEM_UPDATE_ENABLED_LIFETIME_TYPE);
			SET_FLAG_UCHAR(row[78], item->evaltype, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_EVALTYPE);
			SET_FLAG_STR(row[79], item->jmx_endpoint, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_JMX_ENDPOINT);
			SET_FLAG_STR(row[81], item->timeout, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_TIMEOUT);
			SET_FLAG_STR(row[82], item->url, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_URL);
			SET_FLAG_STR(row[83], item->query_fields, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_QUERY_FIELDS);
			SET_FLAG_STR(row[84], item->posts, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_POSTS);
			SET_FLAG_STR(row[85], item->status_codes, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_STATUS_CODES);
			SET_FLAG_UCHAR(row[86], item->follow_redirects, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_FOLLOW_REDIRECTS);
			SET_FLAG_UCHAR(row[87], item->post_type, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_POST_TYPE);
			SET_FLAG_STR(row[88], item->http_proxy, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_HTTP_PROXY);
			SET_FLAG_STR(row[89], item->headers, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_HEADERS);
			SET_FLAG_UCHAR(row[90], item->retrieve_mode, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_RETRIEVE_MODE);
			SET_FLAG_UCHAR(row[91], item->request_method, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_REQUEST_METHOD);
			SET_FLAG_UCHAR(row[92], item->output_format, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_OUTPUT_FORMAT);
			SET_FLAG_STR(row[93], item->ssl_cert_file, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_SSL_CERT_FILE);
			SET_FLAG_STR(row[94], item->ssl_key_file, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_SSL_KEY_FILE);
			SET_FLAG_STR(row[95], item->ssl_key_password, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_SSL_KEY_PASSWORD);
			SET_FLAG_UCHAR(row[96], item->verify_peer, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_VERIFY_PEER);
			SET_FLAG_UCHAR(row[97], item->verify_host, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_VERIFY_HOST);
			SET_FLAG_UCHAR(row[98], item->allow_traps, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_ALLOW_TRAPS);
			SET_FLAG_UCHAR(row[99], item->discover, ZBX_FLAG_TEMPLATE_ITEM_UPDATE_DISCOVER);
		}
		else
		{
			item->key_ = zbx_strdup(NULL, row[2]);
			item->itemid = 0;
		}

		zbx_vector_ptr_create(&item->dependent_items);
		zbx_vector_item_preproc_ptr_create(&item->item_preprocs);
		zbx_vector_item_preproc_ptr_create(&item->template_preprocs);
		zbx_vector_db_tag_ptr_create(&item->item_tags);
		zbx_vector_db_tag_ptr_create(&item->template_tags);
		zbx_vector_item_param_ptr_create(&item->item_params);
		zbx_vector_item_param_ptr_create(&item->template_params);
		zbx_vector_lld_macro_ptr_create(&item->item_lld_macros);
		zbx_vector_lld_macro_ptr_create(&item->template_lld_macros);
		zbx_vector_ptr_append(items, item);
	}
	zbx_db_free_result(result);

	zbx_free(sql);

	zbx_vector_ptr_sort(items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads template lld rule conditions and host lld_rule identifiers  *
 *          from database                                                     *
 *                                                                            *
 * Parameters: items - [IN] the host items including lld rules                *
 *             rules - [OUT] the lld rule mapping                             *
 *                                                                            *
 ******************************************************************************/
static void	get_template_lld_rule_map(const zbx_vector_ptr_t *items, zbx_vector_ptr_t *rules)
{
	zbx_template_item_t		*item;
	zbx_lld_rule_map_t		*rule;
	zbx_lld_rule_condition_t	*condition;
	int				i, index;
	zbx_vector_uint64_t		itemids;
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t			itemid, item_conditionid;

	zbx_vector_uint64_create(&itemids);

	/* prepare discovery rules */
	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		if (0 == (ZBX_FLAG_DISCOVERY_RULE & item->flags))
			continue;

		rule = (zbx_lld_rule_map_t *)zbx_malloc(NULL, sizeof(zbx_lld_rule_map_t));

		rule->itemid = item->itemid;
		rule->templateid = item->templateid;
		rule->conditionid = 0;
		zbx_vector_uint64_create(&rule->conditionids);
		zbx_vector_ptr_create(&rule->conditions);

		zbx_vector_ptr_append(rules, rule);

		if (0 != rule->itemid)
			zbx_vector_uint64_append(&itemids, rule->itemid);
		zbx_vector_uint64_append(&itemids, rule->templateid);
	}

	if (0 != itemids.values_num)
	{
		zbx_vector_ptr_sort(rules, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select item_conditionid,itemid,operator,macro,value from item_condition where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[1]);

			index = zbx_vector_ptr_bsearch(rules, &itemid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

			if (FAIL != index)
			{
				/* read template lld conditions */

				rule = (zbx_lld_rule_map_t *)rules->values[index];

				condition = (zbx_lld_rule_condition_t *)zbx_malloc(NULL,
						sizeof(zbx_lld_rule_condition_t));

				ZBX_STR2UINT64(condition->item_conditionid, row[0]);
				ZBX_STR2UCHAR(condition->op, row[2]);
				condition->macro = zbx_strdup(NULL, row[3]);
				condition->value = zbx_strdup(NULL, row[4]);
				condition->upd_flags = ZBX_FLAG_TEMPLATE_ITEM_CONDITION_UPDATE_RESET_FLAG;
				condition->macro_orig = NULL;
				condition->value_orig = NULL;
				condition->op_orig = 0;

				zbx_vector_ptr_append(&rule->conditions, condition);
			}
			else
			{
				/* read host lld conditions identifiers */

				for (i = 0; i < rules->values_num; i++)
				{
					zbx_uint64_t	flags = ZBX_FLAG_TEMPLATE_ITEM_CONDITION_UPDATE_RESET_FLAG;

					rule = (zbx_lld_rule_map_t *)rules->values[i];

					if (itemid != rule->itemid)
						continue;

					index = rule->conditionids.values_num;

					if (rule->conditions.values_num > index)
					{
						unsigned char	uchar_orig;

						condition = (zbx_lld_rule_condition_t *)rule->conditions.values[index];
						ZBX_STR2UCHAR(uchar_orig, row[2]);

						if (uchar_orig != condition->op)
						{
							flags |= ZBX_FLAG_TEMPLATE_ITEM_CONDITION_UPDATE_OPERATOR;
							condition->op_orig = uchar_orig;
						}

						if (0 != strcmp(row[3], condition->macro))
						{
							flags |= ZBX_FLAG_TEMPLATE_ITEM_CONDITION_UPDATE_MACRO;
							condition->macro_orig = zbx_strdup(NULL, row[3]);
						}

						if (0 != strcmp(row[4], condition->value))
						{
							flags |= ZBX_FLAG_TEMPLATE_ITEM_CONDITION_UPDATE_VALUE;
							condition->value_orig = zbx_strdup(NULL, row[4]);
						}

						condition->upd_flags = flags;
					}

					ZBX_STR2UINT64(item_conditionid, row[0]);
					zbx_vector_uint64_append(&rule->conditionids, item_conditionid);

					break;
				}

				if (i == rules->values_num)
					THIS_SHOULD_NEVER_HAPPEN;
			}
		}
		zbx_db_free_result(result);

		zbx_free(sql);
	}

	zbx_vector_uint64_destroy(&itemids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate identifiers for new item conditions                     *
 *                                                                            *
 * Parameters: rules - [IN] the lld rule mapping                              *
 *                                                                            *
 * Return value: The number of new item conditions to be inserted.            *
 *                                                                            *
 ******************************************************************************/
static int	calculate_template_lld_rule_conditionids(zbx_vector_ptr_t *rules)
{
	zbx_lld_rule_map_t	*rule;
	int			i, conditions_num = 0;
	zbx_uint64_t		conditionid;

	/* calculate the number of new conditions to be inserted */
	for (i = 0; i < rules->values_num; i++)
	{
		rule = (zbx_lld_rule_map_t *)rules->values[i];

		if (rule->conditions.values_num > rule->conditionids.values_num)
			conditions_num += rule->conditions.values_num - rule->conditionids.values_num;
	}

	/* reserve ids for the new conditions to be inserted and assign to lld rules */
	if (0 == conditions_num)
		goto out;

	conditionid = zbx_db_get_maxid_num("item_condition", conditions_num);

	for (i = 0; i < rules->values_num; i++)
	{
		rule = (zbx_lld_rule_map_t *)rules->values[i];

		if (rule->conditions.values_num <= rule->conditionids.values_num)
			continue;

		rule->conditionid = conditionid;
		conditionid += rule->conditions.values_num - rule->conditionids.values_num;
	}
out:
	return conditions_num;
}

static void	update_template_lld_formula(char **formula, zbx_uint64_t id_proto, zbx_uint64_t id)
{
	char	srcid[64], dstid[64], *ptr;
	size_t	pos = 0, len;

	zbx_snprintf(srcid, sizeof(srcid), "{" ZBX_FS_UI64 "}", id_proto);
	zbx_snprintf(dstid, sizeof(dstid), "{" ZBX_FS_UI64 "}", id);

	len = strlen(srcid);

	while (NULL != (ptr = strstr(*formula + pos, srcid)))
	{
		pos = ptr - *formula + len - 1;
		zbx_replace_string(formula, ptr - *formula, &pos, dstid);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: translate template item condition identifiers in expression type  *
 *          discovery rule formulas to refer the host item condition          *
 *          identifiers instead.                                              *
 *                                                                            *
 * Parameters:  items  - [IN] the template items                              *
 *              rules  - [IN] the lld rule mapping                            *
 *                                                                            *
 ******************************************************************************/
static void	update_template_lld_rule_formulas(zbx_vector_ptr_t *items, zbx_vector_ptr_t *rules)
{
	zbx_lld_rule_map_t	*rule;
	int			i, j, index;
	char			*formula;
	zbx_uint64_t		conditionid;

	for (i = 0; i < items->values_num; i++)
	{
		zbx_template_item_t	*item = (zbx_template_item_t *)items->values[i];

		if (0 == (ZBX_FLAG_DISCOVERY_RULE & item->flags) || ZBX_CONDITION_EVAL_TYPE_EXPRESSION !=
				item->evaltype)
		{
			continue;
		}

		index = zbx_vector_ptr_bsearch(rules, &item->templateid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		if (FAIL == index)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		rule = (zbx_lld_rule_map_t *)rules->values[index];

		formula = zbx_strdup(NULL, item->formula);

		conditionid = rule->conditionid;

		for (j = 0; j < rule->conditions.values_num; j++)
		{
			zbx_uint64_t			id;
			zbx_lld_rule_condition_t	*condition =
					(zbx_lld_rule_condition_t *)rule->conditions.values[j];

			if (j < rule->conditionids.values_num)
				id = rule->conditionids.values[j];
			else
				id = conditionid++;

			update_template_lld_formula(&formula, condition->item_conditionid, id);
		}

		zbx_free(item->formula);
		item->formula = formula;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: saves (inserts or updates) template item                          *
 *                                                                            *
 * Parameters: hostid             - [IN] parent host id                       *
 *             itemid             - [IN/OUT] item id used for insert          *
 *                                           operations                       *
 *             item               - [IN] item to be saved                     *
 *             db_insert_items    - [IN] prepared item bulk insert            *
 *             db_insert_irtdata  - [IN] prepared item discovery bulk insert  *
 *             audit_context_mode - [IN]                                      *
 *             sql                - [IN/OUT] sql buffer pointer used for      *
 *                                           update operations                *
 *             sql_alloc          - [IN/OUT] sql buffer already allocated     *
 *                                           memory                           *
 *             sql_offset         - [IN/OUT] offset for writing within sql    *
 *                                           buffer                           *
 *                                                                            *
 ******************************************************************************/
static void	save_template_item(zbx_uint64_t hostid, zbx_uint64_t *itemid, zbx_template_item_t *item,
		zbx_db_insert_t *db_insert_items, zbx_db_insert_t *db_insert_irtdata,
		zbx_db_insert_t *db_insert_irtname, int audit_context_mode, char **sql, size_t *sql_alloc,
		size_t *sql_offset)
{
	int			i;
	zbx_template_item_t	*dependent;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == item->key_) /* existing item */
	{
		char		*str_esc;
		const char	*d = "";

		/* Even if there are no updates for an item, we must create audit entry for it */
		/* to accommodate other entities changes that depend on an item (like tags).   */
		zbx_audit_item_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_UPDATE, item->itemid,
				((0 != (item->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_UPDATE_NAME)) ? item->name_orig :
				item->name), item->flags);

		if (0 == item->upd_flags)
			goto dependent;

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "update items set ");

#define PREPARE_UPDATE_ID(FLAG_POSTFIX, field)									\
		if (0 != (item->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_UPDATE_##FLAG_POSTFIX))			\
		{												\
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s"#field"=%s", d,			\
					zbx_db_sql_id_ins(item->field));					\
			d = ",";										\
														\
			zbx_audit_item_update_json_update_##field(audit_context_mode, item->itemid,		\
					item->flags, item->field##_orig, item->field);				\
		}												\

#define PREPARE_UPDATE_STR(FLAG_POSTFIX, field)									\
		if (0 != (item->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_UPDATE_##FLAG_POSTFIX))			\
		{												\
			str_esc = zbx_db_dyn_escape_string(item->field);					\
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s"#field"='%s'", d, str_esc);		\
			d = ",";										\
			zbx_free(str_esc);									\
														\
			zbx_audit_item_update_json_update_##field(audit_context_mode, item->itemid,		\
					item->flags, item->field##_orig, item->field);				\
		}												\

#define PREPARE_UPDATE_STR_SECRET(FLAG_POSTFIX, field)								\
		if (0 != (item->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_UPDATE_##FLAG_POSTFIX))			\
		{												\
			str_esc = zbx_db_dyn_escape_string(item->field);					\
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s"#field"='%s'", d, str_esc);		\
			d = ",";										\
			zbx_free(str_esc);									\
														\
			zbx_audit_item_update_json_update_##field(audit_context_mode, item->itemid,		\
					item->flags, (0 == strcmp("", item->field##_orig) ? "" :		\
					ZBX_MACRO_SECRET_MASK), (0 == strcmp("", item->field) ? "" :		\
					ZBX_MACRO_SECRET_MASK));						\
		}												\

#define PREPARE_UPDATE_UC(FLAG_POSTFIX, field)				\
		if (0 != (item->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_UPDATE_##FLAG_POSTFIX))			\
		{												\
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s"#field"=%d", d, (int)item->field);	\
			d = ",";										\
														\
			zbx_audit_item_update_json_update_##field(audit_context_mode, item->itemid,		\
					item->flags, (int)item->field##_orig, (int)item->field);		\
		}												\

#define PREPARE_UPDATE_UINT64(FLAG_POSTFIX, field)			\
		if (0 != (item->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_UPDATE_##FLAG_POSTFIX))			\
		{												\
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s"#field"=" ZBX_FS_UI64, d,		\
					item->field);								\
			d = ",";										\
														\
			zbx_audit_item_update_json_update_##field(audit_context_mode, item->itemid,		\
					item->flags, item->field##_orig, item->field);				\
		}

		PREPARE_UPDATE_ID(INTERFACEID, interfaceid)
		PREPARE_UPDATE_STR(NAME, name)
		PREPARE_UPDATE_UC(TYPE, type)
		PREPARE_UPDATE_UINT64(TEMPLATEID, templateid)
		PREPARE_UPDATE_UC(VALUE_TYPE, value_type)
		PREPARE_UPDATE_STR(DELAY, delay);
		PREPARE_UPDATE_STR(HISTORY, history)
		PREPARE_UPDATE_STR(TRENDS, trends)
		PREPARE_UPDATE_UC(STATUS, status)
		PREPARE_UPDATE_STR(TRAPPER_HOSTS, trapper_hosts)
		PREPARE_UPDATE_STR(UNITS, units)
		PREPARE_UPDATE_STR(FORMULA, formula)
		PREPARE_UPDATE_STR(LOGTIMEFMT, logtimefmt)
		PREPARE_UPDATE_ID(VALUEMAPID, valuemapid)
		PREPARE_UPDATE_STR(PARAMS, params)
		PREPARE_UPDATE_STR(IPMI_SENSOR, ipmi_sensor)
		PREPARE_UPDATE_STR(SNMP_OID, snmp_oid)
		PREPARE_UPDATE_UC(AUTHTYPE, authtype)
		PREPARE_UPDATE_STR(USERNAME, username)
		PREPARE_UPDATE_STR_SECRET(PASSWORD, password)
		PREPARE_UPDATE_STR(PUBLICKEY, publickey)
		PREPARE_UPDATE_STR(PRIVATEKEY, privatekey)
		PREPARE_UPDATE_UC(FLAGS, flags)
		PREPARE_UPDATE_STR(DESCRIPTION, description)
		PREPARE_UPDATE_UC(INVENTORY_LINK, inventory_link)
		PREPARE_UPDATE_STR(LIFETIME, lifetime)
		PREPARE_UPDATE_UC(LIFETIME_TYPE, lifetime_type)
		PREPARE_UPDATE_STR(ENABLED_LIFETIME, enabled_lifetime)
		PREPARE_UPDATE_UC(ENABLED_LIFETIME_TYPE, enabled_lifetime_type)
		PREPARE_UPDATE_UC(EVALTYPE, evaltype)
		PREPARE_UPDATE_STR(JMX_ENDPOINT, jmx_endpoint)
		PREPARE_UPDATE_ID(MASTER_ITEMID, master_itemid)
		PREPARE_UPDATE_STR(TIMEOUT, timeout)
		PREPARE_UPDATE_STR(URL, url)
		PREPARE_UPDATE_STR(QUERY_FIELDS, query_fields)
		PREPARE_UPDATE_STR(POSTS, posts)
		PREPARE_UPDATE_STR(STATUS_CODES, status_codes)
		PREPARE_UPDATE_UC(FOLLOW_REDIRECTS, follow_redirects)
		PREPARE_UPDATE_UC(POST_TYPE, post_type)
		PREPARE_UPDATE_STR(HTTP_PROXY, http_proxy)
		PREPARE_UPDATE_STR(HEADERS, headers)
		PREPARE_UPDATE_UC(RETRIEVE_MODE, retrieve_mode)
		PREPARE_UPDATE_UC(REQUEST_METHOD, request_method)
		PREPARE_UPDATE_UC(OUTPUT_FORMAT, output_format)
		PREPARE_UPDATE_STR(SSL_CERT_FILE, ssl_cert_file)
		PREPARE_UPDATE_STR(SSL_KEY_FILE, ssl_key_file)
		PREPARE_UPDATE_STR_SECRET(SSL_KEY_PASSWORD, ssl_key_password)
		PREPARE_UPDATE_UC(VERIFY_PEER, verify_peer)
		PREPARE_UPDATE_UC(VERIFY_HOST, verify_host)
		PREPARE_UPDATE_UC(ALLOW_TRAPS, allow_traps)
		PREPARE_UPDATE_UC(DISCOVER, discover)
		ZBX_UNUSED(d);

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where itemid=" ZBX_FS_UI64 ";\n", item->itemid);

		if (0 != (item->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_UPDATE_NAME))
		{
			if (ZBX_FLAG_DISCOVERY_NORMAL == item->flags || ZBX_FLAG_DISCOVERY_CREATED == item->flags)
			{
				str_esc = zbx_db_dyn_escape_string(item->name);

				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "update item_rtname set"
						" name_resolved='%s',name_resolved_upper=upper('%s')"
						" where itemid=" ZBX_FS_UI64 ";\n",
						str_esc, str_esc, item->itemid);
				zbx_free(str_esc);
				zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);
			}
		}
	}
	else
	{
		zbx_db_insert_add_values(db_insert_items, *itemid, item->name, item->key_, hostid, (int)item->type,
				(int)item->value_type, item->delay, item->history, item->trends,
				(int)item->status, item->trapper_hosts, item->units, item->formula, item->logtimefmt,
				item->valuemapid, item->params, item->ipmi_sensor, item->snmp_oid, (int)item->authtype,
				item->username, item->password, item->publickey, item->privatekey, item->templateid,
				(int)item->flags, item->description, (int)item->inventory_link, item->interfaceid,
				item->lifetime, (int)item->lifetime_type, item->enabled_lifetime,
				(int)item->enabled_lifetime_type, (int)item->evaltype,
				item->jmx_endpoint, item->master_itemid, item->timeout, item->url, item->query_fields,
				item->posts, item->status_codes, item->follow_redirects, item->post_type,
				item->http_proxy, item->headers, item->retrieve_mode, item->request_method,
				item->output_format, item->ssl_cert_file, item->ssl_key_file, item->ssl_key_password,
				item->verify_peer, item->verify_host, item->allow_traps, item->discover);

		zbx_db_insert_add_values(db_insert_irtdata, *itemid);

		if (ZBX_FLAG_DISCOVERY_NORMAL == item->flags || ZBX_FLAG_DISCOVERY_CREATED == item->flags)
			zbx_db_insert_add_values(db_insert_irtname, *itemid, item->name, item->name);

		zbx_audit_item_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_ADD, *itemid, item->name, item->flags);
		zbx_audit_item_update_json_add_data(audit_context_mode, *itemid, item, hostid);

		item->itemid = (*itemid)++;
	}
dependent:
	for (i = 0; i < item->dependent_items.values_num; i++)
	{
		dependent = (zbx_template_item_t *)item->dependent_items.values[i];

		if (dependent->master_itemid_orig != item->itemid)
			dependent->upd_flags |= ZBX_FLAG_TEMPLATE_ITEM_UPDATE_MASTER_ITEMID;

		dependent->master_itemid = item->itemid;
		save_template_item(hostid, itemid, dependent, db_insert_items, db_insert_irtdata, db_insert_irtname,
				audit_context_mode, sql, sql_alloc, sql_offset);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: saves template items to target host in database                   *
 *                                                                            *
 * Parameters:                                                                *
 *              hostid             - [IN] target host                         *
 *              items              - [IN] template items                      *
 *              audit_context_mode - [IN]                                     *
 *                                                                            *
 ******************************************************************************/
static void	save_template_items(zbx_uint64_t hostid, zbx_vector_ptr_t *items, int audit_context_mode)
{
	char			*sql = NULL;
	size_t			sql_alloc = 16 * ZBX_KIBIBYTE, sql_offset = 0;
	int			new_items = 0, upd_items = 0, i;
	zbx_uint64_t		itemid = 0;
	zbx_db_insert_t		db_insert_items, db_insert_irtdata, db_insert_irtname;
	zbx_template_item_t	*item;

	if (0 == items->values_num)
		return;

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		if (NULL == item->key_)
			upd_items++;
		else
			new_items++;
	}

	if (0 != new_items)
	{
		itemid = zbx_db_get_maxid_num("items", new_items);

		zbx_db_insert_prepare(&db_insert_items, "items", "itemid", "name", "key_", "hostid", "type",
				"value_type", "delay", "history", "trends", "status", "trapper_hosts", "units",
				"formula", "logtimefmt", "valuemapid", "params", "ipmi_sensor",
				"snmp_oid", "authtype", "username", "password", "publickey", "privatekey",
				"templateid", "flags", "description", "inventory_link", "interfaceid", "lifetime",
				"lifetime_type", "enabled_lifetime", "enabled_lifetime_type", "evaltype","jmx_endpoint",
				"master_itemid", "timeout", "url", "query_fields", "posts", "status_codes",
				"follow_redirects", "post_type", "http_proxy", "headers", "retrieve_mode",
				"request_method", "output_format", "ssl_cert_file", "ssl_key_file", "ssl_key_password",
				"verify_peer", "verify_host", "allow_traps", "discover", (char *)NULL);

		zbx_db_insert_prepare(&db_insert_irtdata, "item_rtdata", "itemid", (char *)NULL);
		zbx_db_insert_prepare(&db_insert_irtname, "item_rtname", "itemid", "name_resolved",
				"name_resolved_upper", (char *)NULL);
	}

	if (0 != upd_items)
	{
		sql = (char *)zbx_malloc(sql, sql_alloc);
	}

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		/* dependent items are saved within recursive save_template_item calls while saving master */
		if (0 == item->master_itemid)
		{
			save_template_item(hostid, &itemid, item, &db_insert_items, &db_insert_irtdata,
					&db_insert_irtname, audit_context_mode, &sql, &sql_alloc, &sql_offset);
		}
	}

	if (0 != new_items)
	{
		zbx_db_insert_execute(&db_insert_items);
		zbx_db_insert_clean(&db_insert_items);

		zbx_db_insert_execute(&db_insert_irtname);
		zbx_db_insert_clean(&db_insert_irtname);
		zbx_db_insert_execute(&db_insert_irtdata);
		zbx_db_insert_clean(&db_insert_irtdata);
	}

	if (0 != upd_items)
	{
		(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

		zbx_free(sql);
	}

	zbx_vector_ptr_sort(items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: saves template lld rule item conditions to the target host in     *
 *          database                                                          *
 *                                                                            *
 * Parameters:  items              - [IN] template items                      *
 *              rules              - [IN] lld rule mapping                    *
 *              new_conditions     - [IN] number of new item conditions to be *
 *                                        inserted                            *
 *              audit_context_mode - [IN]                                     *
 *                                                                            *
 ******************************************************************************/
static void	save_template_lld_rules(zbx_vector_ptr_t *items, zbx_vector_ptr_t *rules, int new_conditions,
		int audit_context_mode)
{
	char				*macro_esc, *value_esc;
	int				i, j, index;
	zbx_db_insert_t			db_insert;
	zbx_lld_rule_map_t		*rule;
	zbx_lld_rule_condition_t	*condition;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t		item_conditionids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == rules->values_num)
		return;

	zbx_vector_uint64_create(&item_conditionids);

	if (0 != new_conditions)
	{
		zbx_db_insert_prepare(&db_insert, "item_condition", "item_conditionid", "itemid", "operator", "macro",
				"value", (char *)NULL);

		/* insert lld rule conditions for new items */
		for (i = 0; i < items->values_num; i++)
		{
			zbx_template_item_t	*item = (zbx_template_item_t *)items->values[i];

			if (NULL == item->key_)
				continue;

			if (0 == (ZBX_FLAG_DISCOVERY_RULE & item->flags))
				continue;

			index = zbx_vector_ptr_bsearch(rules, &item->templateid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

			if (FAIL == index)
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			rule = (zbx_lld_rule_map_t *)rules->values[index];

			for (j = 0; j < rule->conditions.values_num; j++)
			{
				condition = (zbx_lld_rule_condition_t *)rule->conditions.values[j];

				zbx_db_insert_add_values(&db_insert, rule->conditionid, item->itemid,
						(int)condition->op, condition->macro, condition->value);

				zbx_audit_discovery_rule_update_json_add_filter_conditions(audit_context_mode,
						item->itemid, rule->conditionid, condition->op, condition->macro,
						condition->value);

				rule->conditionid++;
			}
		}
	}

	/* update lld rule conditions for existing items */
	for (i = 0; i < rules->values_num; i++)
	{
		rule = (zbx_lld_rule_map_t *)rules->values[i];

		/* skip lld rules of new items */
		if (0 == rule->itemid)
			continue;

		index = MIN(rule->conditions.values_num, rule->conditionids.values_num);

		/* update intersecting rule conditions */
		for (j = 0; j < index; j++)
		{
			const char	*d = "";

			condition = (zbx_lld_rule_condition_t *)rule->conditions.values[j];

			if (0 == condition->upd_flags)
				continue;

			zbx_audit_discovery_rule_update_json_update_filter_conditions_create_entry(audit_context_mode,
					rule->itemid, rule->conditionids.values[j]);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update item_condition set ");
			if (0 != (condition->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_CONDITION_UPDATE_OPERATOR))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%soperator=%d", d,
						(int)condition->op);
				d = ",";
				zbx_audit_discovery_rule_update_json_update_filter_conditions_operator(audit_context_mode,
						rule->itemid, rule->conditionids.values[j], (int)condition->op_orig,
						(int)condition->op);
			}
			if (0 != (condition->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_CONDITION_UPDATE_MACRO))
			{
				macro_esc = zbx_db_dyn_escape_string(condition->macro);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%smacro='%s'", d, macro_esc);
				d = ",";
				zbx_free(macro_esc);

				zbx_audit_discovery_rule_update_json_update_filter_conditions_macro(audit_context_mode,
						rule->itemid, rule->conditionids.values[j], condition->macro_orig,
						condition->macro);
			}
			if (0 != (condition->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_CONDITION_UPDATE_VALUE))
			{
				value_esc = zbx_db_dyn_escape_string(condition->value);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%svalue='%s'", d, value_esc);
				zbx_free(value_esc);

				zbx_audit_discovery_rule_update_json_update_filter_conditions_value(audit_context_mode,
						rule->itemid, rule->conditionids.values[j], condition->value_orig,
						condition->value);
			}
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where item_conditionid=" ZBX_FS_UI64 ";\n",
					rule->conditionids.values[j]);

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		/* delete removed rule conditions */
		for (j = index; j < rule->conditionids.values_num; j++)
		{
			zbx_audit_discovery_rule_update_json_delete_filter_conditions(audit_context_mode, rule->itemid,
					rule->conditionids.values[j]);
			zbx_vector_uint64_append(&item_conditionids, rule->conditionids.values[j]);
		}

		/* insert new rule conditions */
		for (j = index; j < rule->conditions.values_num; j++)
		{
			condition = (zbx_lld_rule_condition_t *)rule->conditions.values[j];

			zbx_db_insert_add_values(&db_insert, rule->conditionid, rule->itemid,
					(int)condition->op, condition->macro, condition->value);

			zbx_audit_discovery_rule_update_json_add_filter_conditions(audit_context_mode, rule->itemid,
					rule->conditionid, condition->op, condition->macro, condition->value);

			rule->conditionid++;
		}
	}

	/* delete removed item conditions */
	if (0 != item_conditionids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from item_condition where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "item_conditionid", item_conditionids.values,
				item_conditionids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	if (0 != new_conditions)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zbx_free(sql);
	zbx_vector_uint64_destroy(&item_conditionids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: saves host item prototypes in database                            *
 *                                                                            *
 * Parameters:  hostid             - [IN] target host                         *
 *              items              - [IN] template items                      *
 *              audit_context_mode - [IN]                                     *
 *                                                                            *
 ******************************************************************************/
static void	save_template_discovery_prototypes(zbx_uint64_t hostid, zbx_vector_ptr_t *items, int audit_context_mode)
{
	typedef struct
	{
		zbx_uint64_t	itemid;
		zbx_uint64_t	parent_itemid;
	}
	zbx_proto_t;

	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	itemids;
	zbx_vector_ptr_t	prototypes;
	zbx_proto_t		*proto;
	int			i;
	zbx_db_insert_t		db_insert;

	zbx_vector_ptr_create(&prototypes);
	zbx_vector_uint64_create(&itemids);

	for (i = 0; i < items->values_num; i++)
	{
		zbx_template_item_t	*item = (zbx_template_item_t *)items->values[i];

		/* process only new prototype items */
		if (NULL == item->key_ || 0 == (ZBX_FLAG_DISCOVERY_PROTOTYPE & item->flags))
			continue;

		zbx_vector_uint64_append(&itemids, item->itemid);
	}

	if (0 == itemids.values_num)
		goto out;

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select i.itemid,r.itemid"
			" from items i,item_discovery id,items r"
			" where i.templateid=id.itemid"
				" and id.parent_itemid=r.templateid"
				" and r.hostid=" ZBX_FS_UI64
				" and",
			hostid);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid", itemids.values, itemids.values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		proto = (zbx_proto_t *)zbx_malloc(NULL, sizeof(zbx_proto_t));

		ZBX_STR2UINT64(proto->itemid, row[0]);
		ZBX_STR2UINT64(proto->parent_itemid, row[1]);
		zbx_vector_ptr_append(&prototypes, proto);
		zbx_audit_item_prototype_update_json_add_lldruleid(audit_context_mode, proto->itemid,
				proto->parent_itemid);
	}

	zbx_db_free_result(result);

	if (0 == prototypes.values_num)
		goto out;

	zbx_db_insert_prepare(&db_insert, "item_discovery", "itemdiscoveryid", "itemid",
					"parent_itemid", (char *)NULL);

	for (i = 0; i < prototypes.values_num; i++)
	{
		proto = (zbx_proto_t *)prototypes.values[i];

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), proto->itemid, proto->parent_itemid);
	}

	zbx_db_insert_autoincrement(&db_insert, "itemdiscoveryid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
out:
	zbx_free(sql);

	zbx_vector_uint64_destroy(&itemids);

	zbx_vector_ptr_clear_ext(&prototypes, zbx_ptr_free);
	zbx_vector_ptr_destroy(&prototypes);
}

static zbx_template_item_preproc_t	*zbx_item_preproc_create(const char *item_preprocid, int step, int type,
		const char *params, int error_handler, const char *error_handler_params)
{
	zbx_template_item_preproc_t	*preproc;

	preproc = (zbx_template_item_preproc_t *)zbx_malloc(NULL, sizeof(zbx_template_item_preproc_t));

	preproc->upd_flags = ZBX_FLAG_TEMPLATE_ITEM_PREPROC_RESET_FLAG;
	ZBX_STR2UINT64(preproc->item_preprocid, item_preprocid);
	preproc->step = step;
	preproc->type = type;
	preproc->params = zbx_strdup(NULL, params);
	preproc->error_handler = error_handler;
	preproc->error_handler_params = zbx_strdup(NULL, error_handler_params);
	preproc->type_orig = 0;
	preproc->params_orig = NULL;
	preproc->error_handler_orig = 0;
	preproc->error_handler_params_orig = NULL;

	return preproc;
}

static void	zbx_item_preproc_free(zbx_template_item_preproc_t *preproc)
{
	if (0 != (preproc->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_PARAMS))
		zbx_free(preproc->params_orig);
	zbx_free(preproc->params);

	zbx_free(preproc->error_handler_params);
	if (0 != (preproc->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_ERROR_HANDLER_PARAMS))
		zbx_free(preproc->error_handler_params_orig);

	zbx_free(preproc);
}

static void	zbx_lld_macros_free(zbx_template_lld_macro_t *macro)
{
	if (0 != (macro->upd_flags & ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_LLD_MACRO))
		zbx_free(macro->lld_macro_orig);
	zbx_free(macro->lld_macro);

	if (0 != (macro->upd_flags & ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_PATH))
		zbx_free(macro->path_orig);
	zbx_free(macro->path);

	zbx_free(macro);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees template item                                               *
 *                                                                            *
 * Parameters:  item  - [IN] the template item                                *
 *                                                                            *
 ******************************************************************************/
static void	free_template_item(zbx_template_item_t *item)
{
	zbx_vector_ptr_destroy(&item->dependent_items);
	zbx_vector_item_preproc_ptr_clear_ext(&item->item_preprocs, zbx_item_preproc_free);
	zbx_vector_item_preproc_ptr_clear_ext(&item->template_preprocs, zbx_item_preproc_free);
	zbx_vector_db_tag_ptr_clear_ext(&item->item_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_clear_ext(&item->template_tags, zbx_db_tag_free);
	zbx_vector_item_param_ptr_clear_ext(&item->item_params, zbx_item_param_free);
	zbx_vector_item_param_ptr_clear_ext(&item->template_params, zbx_item_param_free);
	zbx_vector_lld_macro_ptr_clear_ext(&item->item_lld_macros, zbx_lld_macros_free);
	zbx_vector_lld_macro_ptr_clear_ext(&item->template_lld_macros, zbx_lld_macros_free);
	zbx_vector_item_preproc_ptr_destroy(&item->item_preprocs);
	zbx_vector_item_preproc_ptr_destroy(&item->template_preprocs);
	zbx_vector_db_tag_ptr_destroy(&item->item_tags);
	zbx_vector_db_tag_ptr_destroy(&item->template_tags);
	zbx_vector_item_param_ptr_destroy(&item->item_params);
	zbx_vector_item_param_ptr_destroy(&item->template_params);
	zbx_vector_lld_macro_ptr_destroy(&item->item_lld_macros);
	zbx_vector_lld_macro_ptr_destroy(&item->template_lld_macros);

#define CLEAN_ORIG(FLAG_POSTFIX, field)							\
	if (0 != (item->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_UPDATE_##FLAG_POSTFIX))	\
	{										\
		zbx_free(item->field##_orig);						\
	}										\
	zbx_free(item->field);

	CLEAN_ORIG(NAME, name)
	CLEAN_ORIG(DELAY, delay)
	CLEAN_ORIG(HISTORY, history)
	CLEAN_ORIG(TRENDS, trends)
	CLEAN_ORIG(TRAPPER_HOSTS, trapper_hosts)
	CLEAN_ORIG(UNITS, units)
	CLEAN_ORIG(FORMULA, formula)
	CLEAN_ORIG(LOGTIMEFMT, logtimefmt)
	CLEAN_ORIG(PARAMS, params)
	CLEAN_ORIG(IPMI_SENSOR, ipmi_sensor)
	CLEAN_ORIG(SNMP_OID, snmp_oid)
	CLEAN_ORIG(USERNAME, username)
	CLEAN_ORIG(PASSWORD, password)
	CLEAN_ORIG(PUBLICKEY, publickey)
	CLEAN_ORIG(PRIVATEKEY, privatekey)
	CLEAN_ORIG(DESCRIPTION, description)
	CLEAN_ORIG(LIFETIME, lifetime)
	CLEAN_ORIG(ENABLED_LIFETIME, enabled_lifetime)
	CLEAN_ORIG(JMX_ENDPOINT, jmx_endpoint)
	CLEAN_ORIG(TIMEOUT, timeout)
	CLEAN_ORIG(URL, url)
	CLEAN_ORIG(QUERY_FIELDS, query_fields)
	CLEAN_ORIG(POSTS, posts)
	CLEAN_ORIG(STATUS_CODES, status_codes)
	CLEAN_ORIG(HTTP_PROXY, http_proxy)
	CLEAN_ORIG(HEADERS, headers)
	CLEAN_ORIG(SSL_CERT_FILE, ssl_cert_file)
	CLEAN_ORIG(SSL_KEY_FILE, ssl_key_file)
	CLEAN_ORIG(SSL_KEY_PASSWORD, ssl_key_password)
#undef CLEAN_ORIG
	zbx_free(item->key_);

	zbx_free(item);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees lld rule condition                                          *
 *                                                                            *
 * Parameters:  condition  - [IN]                                             *
 *                                                                            *
 ******************************************************************************/
static void	free_lld_rule_condition(zbx_lld_rule_condition_t *condition)
{
	/* cannot use update flags to check if orig values were set, because they get reset */
	zbx_free(condition->macro_orig);
	zbx_free(condition->value_orig);

	zbx_free(condition->macro);
	zbx_free(condition->value);
	zbx_free(condition);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees lld rule mapping                                            *
 *                                                                            *
 * Parameters:  item  - [IN] the lld rule mapping                             *
 *                                                                            *
 ******************************************************************************/
static void	free_lld_rule_map(zbx_lld_rule_map_t *rule)
{
	zbx_vector_ptr_clear_ext(&rule->conditions, (zbx_clean_func_t)free_lld_rule_condition);
	zbx_vector_ptr_destroy(&rule->conditions);

	zbx_vector_uint64_destroy(&rule->conditionids);

	zbx_free(rule);
}

static zbx_hash_t	template_item_hash_func(const void *d)
{
	const zbx_template_item_t	*item = *(const zbx_template_item_t * const *)d;

	return ZBX_DEFAULT_UINT64_HASH_FUNC(&item->templateid);
}

static int	template_item_compare_func(const void *d1, const void *d2)
{
	const zbx_template_item_t	*item1 = *(const zbx_template_item_t * const *)d1;
	const zbx_template_item_t	*item2 = *(const zbx_template_item_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(item1->templateid, item2->templateid);
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy template item preprocessing options                          *
 *                                                                            *
 * Parameters: items       - [IN] vector of new/updated items                 *
 *                                                                            *
 ******************************************************************************/
static void	copy_template_items_preproc(const zbx_vector_ptr_t *items, int audit_context_mode)
{
	int				i, j, new_preproc_num = 0, update_preproc_num = 0, delete_preproc_num = 0;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_template_item_t		*item;
	zbx_template_item_preproc_t	*preproc;
	zbx_vector_uint64_t		deleteids;
	zbx_db_insert_t			db_insert;
	zbx_uint64_t			new_preprocid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&deleteids);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		for (j = 0; j < item->item_preprocs.values_num; j++)
		{
			preproc = (zbx_template_item_preproc_t *)item->item_preprocs.values[j];

			if (0 != (preproc->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_PREPROC_DELETE))
			{
				zbx_vector_uint64_append(&deleteids, preproc->item_preprocid);
				zbx_audit_item_delete_preproc(audit_context_mode, item->itemid, item->flags,
						preproc->item_preprocid);
				continue;
			}

			if (0 == preproc->item_preprocid)
			{
				new_preproc_num++;
				continue;
			}

			if (0 == (preproc->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE))
				continue;

			update_preproc_num++;
		}
	}

	if (0 != new_preproc_num)
	{
		new_preprocid = zbx_db_get_maxid_num("item_preproc", new_preproc_num);

		zbx_db_insert_prepare(&db_insert, "item_preproc", "item_preprocid", "itemid", "step", "type", "params",
				"error_handler", "error_handler_params", (char *)NULL);
	}

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		for (j = 0; j < item->item_preprocs.values_num; j++)
		{
			const char	*d = "";

			preproc = (zbx_template_item_preproc_t *)item->item_preprocs.values[j];
			if (0 == preproc->item_preprocid)
			{
				zbx_db_insert_add_values(&db_insert, new_preprocid, item->itemid, preproc->step,
						preproc->type, preproc->params, preproc->error_handler,
						preproc->error_handler_params);

				zbx_audit_item_update_json_add_item_preproc(audit_context_mode, item->itemid, new_preprocid,
						item->flags, preproc->step, preproc->type, preproc->params,
						preproc->error_handler, preproc->error_handler_params);

				new_preprocid++;

				continue;
			}

			if (0 == (preproc->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE))
				continue;

			zbx_audit_item_update_json_update_item_preproc_create_entry(audit_context_mode, item->itemid,
					item->flags, preproc->item_preprocid);

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update item_preproc set ");

			if (0 != (preproc->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%stype=%d", d, preproc->type);
				d = ",";

				zbx_audit_item_update_json_update_item_preproc_type(audit_context_mode, item->itemid,
						item->flags, preproc->item_preprocid, preproc->type_orig,
						preproc->type);
			}

			if (0 != (preproc->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_PARAMS))
			{
				char	*params_esc;

				params_esc = zbx_db_dyn_escape_string(preproc->params);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sparams='%s'", d, params_esc);

				zbx_free(params_esc);
				d = ",";

				zbx_audit_item_update_json_update_item_preproc_params(audit_context_mode, item->itemid,
						item->flags, preproc->item_preprocid, preproc->params_orig,
						preproc->params);
			}

			if (0 != (preproc->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_ERROR_HANDLER))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%serror_handler=%d", d,
						preproc->error_handler);
				d = ",";

				zbx_audit_item_update_json_update_item_preproc_error_handler(audit_context_mode,
						item->itemid, item->flags, preproc->item_preprocid,
						preproc->error_handler_orig, preproc->error_handler);
			}

			if (0 != (preproc->upd_flags & ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_ERROR_HANDLER_PARAMS))
			{
				char	*params_esc;

				params_esc = zbx_db_dyn_escape_string(preproc->error_handler_params);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%serror_handler_params='%s'", d,
						params_esc);

				zbx_free(params_esc);

				zbx_audit_item_update_json_update_item_preproc_error_handler_params(audit_context_mode,
						item->itemid, item->flags, preproc->item_preprocid,
						preproc->error_handler_params_orig, preproc->error_handler_params);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where item_preprocid=" ZBX_FS_UI64 ";\n",
					preproc->item_preprocid);

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
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from item_preproc where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "item_preprocid", deleteids.values,
				deleteids.values_num);
		zbx_db_execute("%s", sql);

		delete_preproc_num = deleteids.values_num;
	}
	zbx_free(sql);
	zbx_vector_uint64_destroy(&deleteids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() added:%d updated:%d removed:%d", __func__, new_preproc_num,
			update_preproc_num, delete_preproc_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies template item tags                                         *
 *                                                                            *
 * Parameters:                                                                *
 *             items              - [IN] vector of new/updated items          *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
static void	copy_template_item_tags(const zbx_vector_ptr_t *items, int audit_context_mode)
{
	int				i, j, new_tag_num = 0, update_tag_num = 0, delete_tag_num = 0;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_template_item_t		*item;
	zbx_db_tag_t			*tag;
	zbx_vector_uint64_t		deleteids;
	zbx_db_insert_t			db_insert;
	zbx_uint64_t			new_tagid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&deleteids);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		for (j = 0; j < item->item_tags.values_num; j++)
		{
			tag = item->item_tags.values[j];

			if (0 != (tag->flags & ZBX_FLAG_DB_TAG_REMOVE))
			{
				zbx_vector_uint64_append(&deleteids, tag->tagid);
				zbx_audit_item_delete_tag(audit_context_mode, item->itemid, item->flags, tag->tagid);
				continue;
			}

			if (0 == tag->tagid)
			{
				new_tag_num++;
				continue;
			}

			if (0 == (tag->flags & ZBX_FLAG_DB_TAG_UPDATE))
				continue;

			update_tag_num++;
		}
	}

	if (0 != new_tag_num)
	{
		new_tagid = zbx_db_get_maxid_num("item_tag", new_tag_num);
		zbx_db_insert_prepare(&db_insert, "item_tag", "itemtagid", "itemid", "tag", "value", (char *)NULL);
	}

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		for (j = 0; j < item->item_tags.values_num; j++)
		{
			const char	*d = "";

			tag = item->item_tags.values[j];

			if (0 == tag->tagid)
			{
				zbx_db_insert_add_values(&db_insert, new_tagid, item->itemid, tag->tag, tag->value);
				zbx_audit_item_update_json_add_item_tag(audit_context_mode, item->itemid, new_tagid,
						item->flags, tag->tag, tag->value);
				new_tagid++;

				continue;
			}

			if (0 == (tag->flags & ZBX_FLAG_DB_TAG_UPDATE))
				continue;

			zbx_audit_item_update_json_update_item_tag_create_entry(audit_context_mode, item->itemid,
					item->flags, tag->tagid);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update item_tag set ");

			if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_TAG))
			{
				char	*tag_esc;

				tag_esc = zbx_db_dyn_escape_string(tag->tag);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%stag='%s'", d, tag_esc);

				d = ",";
				zbx_audit_item_update_json_update_item_tag_tag(audit_context_mode, item->itemid,
						item->flags, tag->tagid, tag->tag_orig, tag->tag);
				zbx_free(tag_esc);

			}

			if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_VALUE))
			{
				char	*value_esc;

				value_esc = zbx_db_dyn_escape_string(tag->value);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%svalue='%s'", d, value_esc);
				zbx_audit_item_update_json_update_item_tag_value(audit_context_mode, item->itemid,
						item->flags, tag->tagid, tag->value_orig, tag->value);

				zbx_free(value_esc);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where itemtagid=" ZBX_FS_UI64 ";\n",
					tag->tagid);

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

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from item_tag where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemtagid", deleteids.values,
				deleteids.values_num);
		zbx_db_execute("%s", sql);

		delete_tag_num = deleteids.values_num;
	}
	zbx_free(sql);
	zbx_vector_uint64_destroy(&deleteids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() added:%d updated:%d removed:%d", __func__, new_tag_num, update_tag_num,
			delete_tag_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies template item script parameters                            *
 *                                                                            *
 * Parameters:                                                                *
 *             items              - [IN] vector of new/updated items          *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
static void	copy_template_item_script_params(const zbx_vector_ptr_t *items, int audit_context_mode)
{
	int				i, j, new_param_num = 0, update_param_num = 0, delete_param_num = 0;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t			item_parameter_id = 0;
	zbx_template_item_t		*item;
	zbx_item_param_t		*param;
	zbx_vector_uint64_t		deleteids;
	zbx_db_insert_t			db_insert;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&deleteids);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		for (j = 0; j < item->item_params.values_num; j++)
		{
			param = item->item_params.values[j];

			if (0 != (param->flags & ZBX_FLAG_ITEM_PARAM_DELETE))
			{
				zbx_vector_uint64_append(&deleteids, param->item_parameterid);
				zbx_audit_item_delete_params(audit_context_mode, item->itemid, item->flags,
						param->item_parameterid);
				continue;
			}

			if (0 == param->item_parameterid)
			{
				new_param_num++;
				continue;
			}

			if (0 == (param->flags & ZBX_FLAG_ITEM_PARAM_UPDATE))
				continue;

			update_param_num++;
		}
	}

	if (0 != new_param_num)
	{
		zbx_db_insert_prepare(&db_insert, "item_parameter", "item_parameterid", "itemid", "name", "value",
				(char *)NULL);
		item_parameter_id = zbx_db_get_maxid_num("item_parameter", new_param_num);
	}

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		for (j = 0; j < item->item_params.values_num; j++)
		{
			const char	*d = "";

			param = item->item_params.values[j];

			if (0 == param->item_parameterid)
			{
				zbx_db_insert_add_values(&db_insert, item_parameter_id, item->itemid, param->name,
						param->value);
				zbx_audit_item_update_json_add_params(audit_context_mode, item->itemid, item->flags,
						item_parameter_id,
						param->name, param->value);
				item_parameter_id++;
				continue;
			}

			if (0 == (param->flags & ZBX_FLAG_ITEM_PARAM_UPDATE))
				continue;

			zbx_audit_item_update_json_update_params_create_entry(audit_context_mode, item->itemid,
					item->flags, param->item_parameterid);

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update item_parameter set ");

			if (0 != (param->flags & ZBX_FLAG_ITEM_PARAM_UPDATE_NAME))
			{
				char	*name_esc;

				name_esc = zbx_db_dyn_escape_string(param->name);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sname='%s'", d, name_esc);

				zbx_free(name_esc);
				d = ",";

				zbx_audit_item_update_json_update_params_name(audit_context_mode, item->itemid,
						item->flags, param->item_parameterid, param->name_orig, param->name);
			}

			if (0 != (param->flags & ZBX_FLAG_ITEM_PARAM_UPDATE_VALUE))
			{
				char	*value_esc;

				value_esc = zbx_db_dyn_escape_string(param->value);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%svalue='%s'", d, value_esc);

				zbx_free(value_esc);

				zbx_audit_item_update_json_update_params_value(audit_context_mode, item->itemid,
						item->flags, param->item_parameterid, param->value_orig, param->value);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where item_parameterid=" ZBX_FS_UI64 ";\n",
					param->item_parameterid);

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
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from item_parameter where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "item_parameterid", deleteids.values,
				deleteids.values_num);
		zbx_db_execute("%s", sql);

		delete_param_num = deleteids.values_num;
	}
	zbx_free(sql);
	zbx_vector_uint64_destroy(&deleteids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() added:%d updated:%d removed:%d", __func__, new_param_num,
			update_param_num, delete_param_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies template discovery item lld macro paths                    *
 *                                                                            *
 * Parameters:                                                                *
 *             items              - [IN] vector of new/updated items          *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
static void	copy_template_lld_macro_paths(const zbx_vector_ptr_t *items, int audit_context_mode)
{
	int				i, j, new_lld_macro_num = 0, update_lld_macro_num = 0, delete_lld_macro_num = 0;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t			new_lld_macro_pathid = 0;
	zbx_template_item_t		*item;
	zbx_template_lld_macro_t	*lld_macro;
	zbx_vector_uint64_t		deleteids;
	zbx_db_insert_t			db_insert;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&deleteids);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		for (j = 0; j < item->item_lld_macros.values_num; j++)
		{
			lld_macro = (zbx_template_lld_macro_t *)item->item_lld_macros.values[j];

			if (0 != (lld_macro->upd_flags & ZBX_FLAG_TEMPLATE_LLD_MACRO_DELETE))
			{
				zbx_vector_uint64_append(&deleteids, lld_macro->lld_macro_pathid);
				zbx_audit_discovery_rule_update_json_delete_lld_macro_path(audit_context_mode,
						item->itemid, lld_macro->lld_macro_pathid);
				continue;
			}

			if (0 == lld_macro->lld_macro_pathid)
			{
				new_lld_macro_num++;
				continue;
			}

			if (0 == (lld_macro->upd_flags & ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE))
				continue;

			update_lld_macro_num++;
		}
	}

	if (0 != deleteids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from lld_macro_path where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "lld_macro_pathid", deleteids.values,
				deleteids.values_num);
		zbx_db_execute("%s", sql);

		delete_lld_macro_num = deleteids.values_num;
		sql_offset = 0;
	}

	if (0 != new_lld_macro_num)
	{
		zbx_db_insert_prepare(&db_insert, "lld_macro_path", "lld_macro_pathid", "itemid", "lld_macro", "path",
				(char *)NULL);

		new_lld_macro_pathid = zbx_db_get_maxid_num("lld_macro_path", new_lld_macro_num);
	}

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		for (j = 0; j < item->item_lld_macros.values_num; j++)
		{
			const char	*d = "";

			lld_macro = (zbx_template_lld_macro_t *)item->item_lld_macros.values[j];
			if (0 == lld_macro->lld_macro_pathid)
			{
				zbx_db_insert_add_values(&db_insert, new_lld_macro_pathid, item->itemid,
						lld_macro->lld_macro, lld_macro->path);

				zbx_audit_discovery_rule_update_json_add_lld_macro_path(audit_context_mode,
						item->itemid, new_lld_macro_pathid, lld_macro->lld_macro,
						lld_macro->path);

				new_lld_macro_pathid++;
				continue;
			}

			if (0 == (lld_macro->upd_flags & ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE))
				continue;

			zbx_audit_discovery_rule_update_json_lld_macro_path_create_update_entry(audit_context_mode,
					item->itemid, lld_macro->lld_macro_pathid);

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update lld_macro_path set ");

			if (0 != (lld_macro->upd_flags & ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_LLD_MACRO))
			{
				char	*lld_macro_esc;

				lld_macro_esc = zbx_db_dyn_escape_string(lld_macro->lld_macro);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%slld_macro='%s'", d, lld_macro_esc);

				zbx_free(lld_macro_esc);
				d = ",";

				zbx_audit_discovery_rule_update_json_update_lld_macro_path_lld_macro(audit_context_mode,
						item->itemid, lld_macro->lld_macro_pathid, lld_macro->lld_macro_orig,
						lld_macro->lld_macro);
			}

			if (0 != (lld_macro->upd_flags & ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_PATH))
			{
				char	*path_esc;

				path_esc = zbx_db_dyn_escape_string(lld_macro->path);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%spath='%s'", d, path_esc);

				zbx_free(path_esc);

				zbx_audit_discovery_rule_update_json_update_lld_macro_path_path(audit_context_mode,
						item->itemid, lld_macro->lld_macro_pathid, lld_macro->path_orig,
						lld_macro->path);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where lld_macro_pathid=" ZBX_FS_UI64 ";\n",
					lld_macro->lld_macro_pathid);

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

	}

	if (0 != update_lld_macro_num)
		(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	if (0 != new_lld_macro_num)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zbx_free(sql);
	zbx_vector_uint64_destroy(&deleteids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() added:%d updated:%d removed:%d", __func__, new_lld_macro_num,
			update_lld_macro_num, delete_lld_macro_num);
}

static void	lld_override_condition_free(lld_override_codition_t *override_condition)
{
	zbx_free(override_condition->macro);
	zbx_free(override_condition->value);
	zbx_free(override_condition);
}

static void	lld_override_free(lld_override_t *override)
{
	zbx_vector_ptr_clear_ext(&override->override_conditions, (zbx_clean_func_t)lld_override_condition_free);
	zbx_vector_ptr_destroy(&override->override_conditions);
	zbx_vector_lld_override_operation_clear_ext(&override->override_operations, zbx_lld_override_operation_free);
	zbx_vector_lld_override_operation_destroy(&override->override_operations);
	zbx_free(override->name);
	zbx_free(override->formula);
	zbx_free(override);
}

static void	lld_override_conditions_load(zbx_vector_ptr_t *overrides, const zbx_vector_uint64_t *overrideids,
		char **sql, size_t *sql_alloc)
{
	size_t			sql_offset = 0;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_uint64_t		overrideid;
	int			i;
	lld_override_t		*override;
	lld_override_codition_t	*override_condition;

	zbx_snprintf_alloc(sql, sql_alloc, &sql_offset,
		"select lld_overrideid,lld_override_conditionid,operator,macro,value"
			" from lld_override_condition"
			" where");
	zbx_db_add_condition_alloc(sql, sql_alloc, &sql_offset, "lld_overrideid", overrideids->values,
			overrideids->values_num);

	result = zbx_db_select("%s", *sql);
	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(overrideid, row[0]);

		if (FAIL == (i = zbx_vector_ptr_bsearch(overrides, &overrideid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		override = (lld_override_t *)overrides->values[i];

		override_condition = (lld_override_codition_t *)zbx_malloc(NULL, sizeof(lld_override_codition_t));
		ZBX_STR2UINT64(override_condition->override_conditionid, row[1]);
		ZBX_STR2UCHAR(override_condition->operator, row[2]);
		override_condition->macro = zbx_strdup(NULL, row[3]);
		override_condition->value = zbx_strdup(NULL, row[4]);

		zbx_vector_ptr_append(&override->override_conditions, override_condition);
	}
	zbx_db_free_result(result);
}

static void	lld_override_operations_load(zbx_vector_ptr_t *overrides, const zbx_vector_uint64_t *overrideids,
		char **sql, size_t *sql_alloc)
{
	zbx_vector_lld_override_operation_t	ops;
	int					i, index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_lld_override_operation_create(&ops);

	zbx_load_lld_override_operations(overrideids, sql, sql_alloc, &ops);

	for (i = 0; i < ops.values_num; i++)
	{
		lld_override_t			*override;
		zbx_lld_override_operation_t	*op = ops.values[i];

		if (FAIL == (index = zbx_vector_ptr_bsearch(overrides, &op->overrideid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			zbx_lld_override_operation_free(op);
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}
		override = overrides->values[index];
		zbx_vector_lld_override_operation_append(&override->override_operations, op);
	}

	zbx_vector_lld_override_operation_destroy(&ops);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	save_template_lld_overrides(zbx_vector_ptr_t *overrides, zbx_hashset_t *lld_items,
		int audit_context_mode)
{
	zbx_uint64_t			overrideid, override_operationid = 0, override_conditionid = 0;
	zbx_db_insert_t			db_insert, db_insert_oconditions, db_insert_ooperations, db_insert_opstatus,
					db_insert_opdiscover, db_insert_opperiod, db_insert_ophistory,
					db_insert_optrends, db_insert_opseverity, db_insert_optag, db_insert_optemplate,
					db_insert_opinventory;
	int				i, j, k, conditions_num, operations_num;
	lld_override_t			*override;
	lld_override_codition_t		*override_condition;
	zbx_lld_override_operation_t	*override_operation;
	const zbx_template_item_t	**pitem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 != overrides->values_num)
		overrideid = zbx_db_get_maxid_num("lld_override", overrides->values_num);

	zbx_db_insert_prepare(&db_insert, "lld_override", "lld_overrideid", "itemid", "name", "step", "evaltype",
			"formula", "stop", (char *)NULL);

	zbx_db_insert_prepare(&db_insert_oconditions, "lld_override_condition", "lld_override_conditionid",
			"lld_overrideid", "operator", "macro", "value", (char *)NULL);

	for (i = 0, operations_num = 0, conditions_num = 0; i < overrides->values_num; i++)
	{
		override = (lld_override_t *)overrides->values[i];
		operations_num += override->override_operations.values_num;
		conditions_num += override->override_conditions.values_num;
	}

	if (0 != operations_num)
		override_operationid = zbx_db_get_maxid_num("lld_override_operation", operations_num);

	if (0 != conditions_num)
		override_conditionid = zbx_db_get_maxid_num("lld_override_condition", conditions_num);

	zbx_db_insert_prepare(&db_insert_ooperations, "lld_override_operation", "lld_override_operationid",
				"lld_overrideid", "operationobject", "operator", "value", (char *)NULL);

	zbx_db_insert_prepare(&db_insert_opstatus, "lld_override_opstatus", "lld_override_operationid", "status",
			(char *)NULL);

	zbx_db_insert_prepare(&db_insert_opdiscover, "lld_override_opdiscover", "lld_override_operationid", "discover",
			(char *)NULL);

	zbx_db_insert_prepare(&db_insert_opperiod, "lld_override_opperiod", "lld_override_operationid", "delay",
			(char *)NULL);
	zbx_db_insert_prepare(&db_insert_ophistory, "lld_override_ophistory", "lld_override_operationid", "history",
			(char *)NULL);
	zbx_db_insert_prepare(&db_insert_optrends, "lld_override_optrends", "lld_override_operationid", "trends",
			(char *)NULL);

	zbx_db_insert_prepare(&db_insert_opseverity, "lld_override_opseverity", "lld_override_operationid", "severity",
			(char *)NULL);
	zbx_db_insert_prepare(&db_insert_optag, "lld_override_optag", "lld_override_optagid",
			"lld_override_operationid", "tag", "value", (char *)NULL);

	zbx_db_insert_prepare(&db_insert_optemplate, "lld_override_optemplate", "lld_override_optemplateid",
				"lld_override_operationid", "templateid", (char *)NULL);
	zbx_db_insert_prepare(&db_insert_opinventory, "lld_override_opinventory", "lld_override_operationid",
			"inventory_mode", (char *)NULL);

	for (i = 0; i < overrides->values_num; i++)
	{
		zbx_template_item_t	item_local, *pitem_local = &item_local;

		override = (lld_override_t *)overrides->values[i];

		item_local.templateid = override->itemid;
		if (NULL == (pitem = (const zbx_template_item_t **)zbx_hashset_search(lld_items, &pitem_local)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		for (j = 0; j < override->override_conditions.values_num; j++)
		{
			override_condition = (lld_override_codition_t *)override->override_conditions.values[j];

			zbx_db_insert_add_values(&db_insert_oconditions, override_conditionid, overrideid,
					(int)override_condition->operator, override_condition->macro,
					override_condition->value);

			zbx_audit_discovery_rule_update_json_add_lld_override_condition(audit_context_mode,
					(*pitem)->itemid, overrideid, override_conditionid,
					(int)override_condition->operator, override_condition->macro,
					override_condition->value);

			if (ZBX_CONDITION_EVAL_TYPE_EXPRESSION == override->evaltype)
			{
				update_template_lld_formula(&override->formula,
						override_condition->override_conditionid, override_conditionid);
			}

			override_conditionid++;
		}

		/* prepare lld_override insert after formula is updated */
		zbx_db_insert_add_values(&db_insert, overrideid, (*pitem)->itemid, override->name, (int)override->step,
				(int)override->evaltype, override->formula, (int)override->stop);

		zbx_audit_discovery_rule_update_json_add_lld_override(audit_context_mode, (*pitem)->itemid, overrideid,
				override->name, (int)override->step, (int)override->stop);

		zbx_audit_discovery_rule_update_json_add_lld_override_filter(audit_context_mode, (*pitem)->itemid,
				overrideid, (int)override->evaltype, override->formula);

		for (j = 0; j < override->override_operations.values_num; j++)
		{
			override_operation = (zbx_lld_override_operation_t *)override->override_operations.values[j];

			zbx_db_insert_add_values(&db_insert_ooperations, override_operationid, overrideid,
					(int)override_operation->operationtype, (int)override_operation->operator,
					override_operation->value);

			zbx_audit_discovery_rule_update_json_add_lld_override_operation(audit_context_mode,
					(*pitem)->itemid, overrideid, override_operationid,
					(int)override_operation->operationtype, (int)override_operation->operator,
					override_operation->value);

			if (ZBX_PROTOTYPE_STATUS_COUNT != override_operation->status)
			{
				zbx_db_insert_add_values(&db_insert_opstatus, override_operationid,
						(int)override_operation->status);

				zbx_audit_discovery_rule_update_json_add_lld_override_opstatus(audit_context_mode,
						(*pitem)->itemid, overrideid, override_operationid,
						(int)override_operation->status);
			}

			if (ZBX_PROTOTYPE_DISCOVER_COUNT != override_operation->discover)
			{
				zbx_db_insert_add_values(&db_insert_opdiscover, override_operationid,
						(int)override_operation->discover);

				zbx_audit_discovery_rule_update_json_add_lld_override_opdiscover(audit_context_mode,
						(*pitem)->itemid, overrideid,override_operationid,
						(int)override_operation->discover);
			}

			if (NULL != override_operation->delay)
			{
				zbx_db_insert_add_values(&db_insert_opperiod, override_operationid,
						override_operation->delay);

				zbx_audit_discovery_rule_update_json_add_lld_override_opperiod(audit_context_mode,
						(*pitem)->itemid, overrideid, override_operationid,
						override_operation->delay);
			}

			if (NULL != override_operation->history)
			{
				zbx_db_insert_add_values(&db_insert_ophistory, override_operationid,
						override_operation->history);

				zbx_audit_discovery_rule_update_json_add_lld_override_ophistory(audit_context_mode,
						(*pitem)->itemid, overrideid, override_operationid,
						override_operation->history);
			}

			if (NULL != override_operation->trends)
			{
				zbx_db_insert_add_values(&db_insert_optrends, override_operationid,
						override_operation->trends);

				zbx_audit_discovery_rule_update_json_add_lld_override_optrends(audit_context_mode,
						(*pitem)->itemid, overrideid, override_operationid,
						override_operation->trends);
			}

			if (TRIGGER_SEVERITY_COUNT != override_operation->severity)
			{
				zbx_db_insert_add_values(&db_insert_opseverity, override_operationid,
						(int)override_operation->severity);

				zbx_audit_discovery_rule_update_json_add_lld_override_opseverity(audit_context_mode,
						(*pitem)->itemid, overrideid, override_operationid,
						override_operation->severity);
			}

			if (0 != override_operation->tags.values_num)
			{
				zbx_uint64_t	lld_override_optagid;

				lld_override_optagid = zbx_db_get_maxid_num("lld_override_optag",
						override_operation->tags.values_num);

				for (k = 0; k < override_operation->tags.values_num; k++)
				{
					zbx_db_tag_t	*tag = override_operation->tags.values[k];

					zbx_db_insert_add_values(&db_insert_optag, lld_override_optagid,
							override_operationid, tag->tag, tag->value);

					zbx_audit_discovery_rule_update_json_add_lld_override_optag(audit_context_mode,
							(*pitem)->itemid, overrideid, override_operationid,
							lld_override_optagid, tag->tag, tag->value);

					lld_override_optagid++;
				}
			}

			if (0 != override_operation->templateids.values_num)
			{
				zbx_uint64_t	lld_override_optemplateid;

				lld_override_optemplateid = zbx_db_get_maxid_num("lld_override_optemplate",
						override_operation->templateids.values_num);

				for (k = 0; k < override_operation->templateids.values_num; k++)
				{
					zbx_db_insert_add_values(&db_insert_optemplate, lld_override_optemplateid,
							override_operationid,
						override_operation->templateids.values[k]);

					zbx_audit_discovery_rule_update_json_add_lld_override_optemplate(
							audit_context_mode, (*pitem)->itemid, overrideid,
							lld_override_optemplateid,
							override_operation->templateids.values[k]);

					lld_override_optemplateid++;
				}
			}

			if (HOST_INVENTORY_COUNT != override_operation->inventory_mode)
			{
				zbx_db_insert_add_values(&db_insert_opinventory, override_operationid,
						(int)override_operation->inventory_mode);

				zbx_audit_discovery_rule_update_json_add_lld_override_opinventory(audit_context_mode,
						(*pitem)->itemid, overrideid, override_operationid,
						(int)override_operation->inventory_mode);
			}

			override_operationid++;
		}

		overrideid++;
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_db_insert_execute(&db_insert_oconditions);
	zbx_db_insert_clean(&db_insert_oconditions);

	zbx_db_insert_execute(&db_insert_ooperations);
	zbx_db_insert_clean(&db_insert_ooperations);

	zbx_db_insert_execute(&db_insert_opstatus);
	zbx_db_insert_clean(&db_insert_opstatus);

	zbx_db_insert_execute(&db_insert_opdiscover);
	zbx_db_insert_clean(&db_insert_opdiscover);

	zbx_db_insert_execute(&db_insert_opperiod);
	zbx_db_insert_clean(&db_insert_opperiod);

	zbx_db_insert_execute(&db_insert_ophistory);
	zbx_db_insert_clean(&db_insert_ophistory);

	zbx_db_insert_execute(&db_insert_optrends);
	zbx_db_insert_clean(&db_insert_optrends);

	zbx_db_insert_execute(&db_insert_opseverity);
	zbx_db_insert_clean(&db_insert_opseverity);

	zbx_db_insert_execute(&db_insert_optag);
	zbx_db_insert_clean(&db_insert_optag);

	zbx_db_insert_execute(&db_insert_optemplate);
	zbx_db_insert_clean(&db_insert_optemplate);

	zbx_db_insert_execute(&db_insert_opinventory);
	zbx_db_insert_clean(&db_insert_opinventory);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	copy_template_lld_overrides(const zbx_vector_uint64_t *templateids,
		const zbx_vector_uint64_t *lld_itemids, zbx_hashset_t *lld_items, int audit_context_mode)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	lld_override_t		*override;
	zbx_vector_ptr_t	overrides;
	zbx_vector_uint64_t	overrideids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&overrideids);
	zbx_vector_ptr_create(&overrides);

	/* remove overrides from existing items with same key */
	if (0 != lld_itemids->values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select lld_overrideid,itemid from lld_override where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", lld_itemids->values,
				lld_itemids->values_num);
		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	delete_lld_overrideid, delete_itemid;

			ZBX_STR2UINT64(delete_lld_overrideid, row[0]);
			ZBX_STR2UINT64(delete_itemid, row[1]);
			zbx_audit_discovery_rule_update_json_delete_lld_override(audit_context_mode, delete_itemid,
					delete_lld_overrideid);
		}

		sql_offset = 0;
		zbx_db_free_result(result);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from lld_override where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", lld_itemids->values,
				lld_itemids->values_num);
		zbx_db_execute("%s", sql);
		sql_offset = 0;
	}

	/* read overrides from templates that should be linked */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
		"select l.lld_overrideid,l.itemid,l.name,l.step,l.evaltype,l.formula,l.stop"
			" from lld_override l,items i"
			" where l.itemid=i.itemid"
			" and");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values,
			templateids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by l.lld_overrideid");

	result = zbx_db_select("%s", sql);
	while (NULL != (row = zbx_db_fetch(result)))
	{
		override = (lld_override_t *)zbx_malloc(NULL, sizeof(lld_override_t));
		ZBX_STR2UINT64(override->overrideid, row[0]);
		ZBX_STR2UINT64(override->itemid, row[1]);
		override->name = zbx_strdup(NULL, row[2]);
		ZBX_STR2UCHAR(override->step, row[3]);
		ZBX_STR2UCHAR(override->evaltype, row[4]);
		override->formula = zbx_strdup(NULL, row[5]);
		ZBX_STR2UCHAR(override->stop, row[6]);
		zbx_vector_ptr_create(&override->override_conditions);
		zbx_vector_lld_override_operation_create(&override->override_operations);

		zbx_vector_uint64_append(&overrideids, override->overrideid);
		zbx_vector_ptr_append(&overrides, override);
	}
	zbx_db_free_result(result);

	if (0 != overrides.values_num)
	{
		lld_override_conditions_load(&overrides, &overrideids, &sql, &sql_alloc);
		lld_override_operations_load(&overrides, &overrideids, &sql, &sql_alloc);
		save_template_lld_overrides(&overrides, lld_items, audit_context_mode);
	}
	zbx_free(sql);

	zbx_vector_uint64_destroy(&overrideids);
	zbx_vector_ptr_clear_ext(&overrides, (zbx_clean_func_t)lld_override_free);
	zbx_vector_ptr_destroy(&overrides);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare templateid of two template items                          *
 *                                                                            *
 * Parameters: d1 - [IN] first template item                                  *
 *             d2 - [IN] second template item                                 *
 *                                                                            *
 * Return value: compare result (-1 for d1<d2, 1 for d1>d2, 0 for d1==d2)     *
 *                                                                            *
 ******************************************************************************/
static int	compare_template_items(const void *d1, const void *d2)
{
	const zbx_template_item_t	*i1 = *(const zbx_template_item_t * const *)d1;
	const zbx_template_item_t	*i2 = *(const zbx_template_item_t * const *)d2;

	return zbx_default_uint64_compare_func(&i1->templateid, &i2->templateid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create dependent item index in master item data                   *
 *                                                                            *
 * Parameters: items       - [IN/OUT] the template items                      *
 *                                                                            *
 ******************************************************************************/
static void	link_template_dependent_items(zbx_vector_ptr_t *items)
{
	zbx_template_item_t	*item, *master, item_local;
	int			i, index;
	zbx_vector_ptr_t	template_index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&template_index);
	zbx_vector_ptr_append_array(&template_index, items->values, items->values_num);
	zbx_vector_ptr_sort(&template_index, compare_template_items);

	for (i = items->values_num - 1; i >= 0; i--)
	{
		item = (zbx_template_item_t *)items->values[i];
		if (0 != item->master_itemid)
		{
			item_local.templateid = item->master_itemid;
			if (FAIL == (index = zbx_vector_ptr_bsearch(&template_index, &item_local,
					compare_template_items)))
			{
				/* dependent item without master item should be removed */
				THIS_SHOULD_NEVER_HAPPEN;
				free_template_item(item);
				zbx_vector_ptr_remove(items, i);
			}
			else
			{
				master = (zbx_template_item_t *)template_index.values[index];
				zbx_vector_ptr_append(&master->dependent_items, item);
			}
		}
	}

	zbx_vector_ptr_destroy(&template_index);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	template_item_preproc_sort_by_step(const void *d1, const void *d2)
{
	zbx_template_item_preproc_t *op1 = *(zbx_template_item_preproc_t * const *)d1;
	zbx_template_item_preproc_t *op2 = *(zbx_template_item_preproc_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(op1->step, op2->step);

	return 0;
}

static int	template_lld_macro_sort_by_macro(const void *d1, const void *d2)
{
	zbx_template_lld_macro_t	*ip1 = *(zbx_template_lld_macro_t * const *)d1;
	zbx_template_lld_macro_t	*ip2 = *(zbx_template_lld_macro_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(ip1->lld_macro, ip2->lld_macro);

	return 0;
}
/******************************************************************************
 *                                                                            *
 * Purpose: create item_preproc vectors in item data                          *
 *                                                                            *
 * Parameters: templateids - [IN] vector of template IDs                      *
 *             items       - [IN/OUT] the template items                      *
 *                                                                            *
 ******************************************************************************/
static void	link_template_items_preproc(const zbx_vector_uint64_t *templateids, zbx_vector_ptr_t *items)
{
	int				i, index;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t			itemid;
	zbx_template_item_preproc_t	*ppsrc, *ppdst;
	zbx_template_item_t		*item;
	zbx_hashset_t			items_t;
	zbx_vector_uint64_t		itemids;
	zbx_db_row_t			row;
	zbx_db_result_t			result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&itemids);
	zbx_hashset_create(&items_t, (size_t)items->values_num, template_item_hash_func, template_item_compare_func);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		if (NULL == item->key_)
			zbx_vector_uint64_append(&itemids, item->itemid);

		zbx_hashset_insert(&items_t, &item, sizeof(zbx_template_item_t *));
	}

	if (0 != itemids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select ip.item_preprocid,ip.itemid,ip.step,ip.type,ip.params,ip.error_handler,"
					"ip.error_handler_params"
				" from item_preproc ip"
				" where ");

		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);

		result = zbx_db_select("%s", sql);
		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[1]);

			if (FAIL == (index = zbx_vector_ptr_bsearch(items, &itemid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			item = (zbx_template_item_t *)items->values[index];

			ppdst = zbx_item_preproc_create(row[0], atoi(row[2]), atoi(row[3]), row[4], atoi(row[5]),
					row[6]);

			zbx_vector_item_preproc_ptr_append(&((zbx_template_item_t *)item)->item_preprocs, ppdst);
		}
		zbx_db_free_result(result);
		zbx_free(sql);
		sql_offset = 0;
		sql_alloc = 0;
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select ip.item_preprocid,ip.itemid,ip.step,ip.type,ip.params,ip.error_handler,"
				"ip.error_handler_params"
			" from item_preproc ip,items ti"
			" where ip.itemid=ti.itemid"
			" and");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values,
			templateids->values_num);

	result = zbx_db_select("%s", sql);
	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_template_item_t		item_local, *pitem_local = &item_local, **pitem;

		ZBX_STR2UINT64(item_local.templateid, row[1]);
		if (NULL == (pitem = (zbx_template_item_t **)zbx_hashset_search(&items_t, &pitem_local)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		ppdst = zbx_item_preproc_create(row[0], atoi(row[2]), atoi(row[3]), row[4], atoi(row[5]), row[6]);

		zbx_vector_item_preproc_ptr_append(&(*pitem)->template_preprocs, ppdst);
	}
	zbx_db_free_result(result);
	zbx_free(sql);

	for (i = 0; i < items->values_num; i++)
	{
		int	j, preproc_num;
		char	*buffer = NULL;

		item = (zbx_template_item_t *)items->values[i];

		zbx_vector_item_preproc_ptr_sort(&item->item_preprocs, template_item_preproc_sort_by_step);
		zbx_vector_item_preproc_ptr_sort(&item->template_preprocs, template_item_preproc_sort_by_step);

		preproc_num = MAX(item->item_preprocs.values_num, item->template_preprocs.values_num);

		for (j = 0; j < preproc_num; j++)
		{
			if (j >= item->item_preprocs.values_num)
			{
				ppsrc = (zbx_template_item_preproc_t *)item->template_preprocs.values[j];

				ppdst = zbx_item_preproc_create("0", ppsrc->step, ppsrc->type, ppsrc->params,
						ppsrc->error_handler, ppsrc->error_handler_params);

				zbx_vector_item_preproc_ptr_append(&item->item_preprocs, ppdst);
				continue;
			}

			ppdst = (zbx_template_item_preproc_t *)item->item_preprocs.values[j];

			if (j >= item->template_preprocs.values_num)
			{
				ppdst->upd_flags |= ZBX_FLAG_TEMPLATE_ITEM_PREPROC_DELETE;
				continue;
			}

			ppsrc = (zbx_template_item_preproc_t *)item->template_preprocs.values[j];

			if (ppdst->type != ppsrc->type)
			{
				ppdst->type_orig = ppdst->type;
				ppdst->type = ppsrc->type;
				ppdst->upd_flags |= ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_TYPE;
			}
			buffer = zbx_strdup(buffer, ppsrc->params);

			if (0 != strcmp(ppdst->params, buffer))
			{
				ppdst->params_orig = zbx_strdup(NULL, ppdst->params);
				zbx_free(ppdst->params);
				ppdst->params = buffer;
				buffer = NULL;
				ppdst->upd_flags |= ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_PARAMS;
			}

			if (ppdst->error_handler != ppsrc->error_handler)
			{
				ppdst->error_handler_orig = ppdst->error_handler;
				ppdst->error_handler = ppsrc->error_handler;
				ppdst->upd_flags |= ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_ERROR_HANDLER;
			}

			buffer = zbx_strdup(buffer, ppsrc->error_handler_params);

			if (0 != strcmp(ppdst->error_handler_params, buffer))
			{
				ppdst->error_handler_params_orig = zbx_strdup(NULL, ppdst->error_handler_params);
				zbx_free(ppdst->error_handler_params);
				ppdst->error_handler_params = buffer;
				buffer = NULL;
				ppdst->upd_flags |= ZBX_FLAG_TEMPLATE_ITEM_PREPROC_UPDATE_ERROR_HANDLER_PARAMS;
			}
			else
				zbx_free(buffer);
		}
	}
	zbx_hashset_destroy(&items_t);
	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create item_tags vectors in item data                             *
 *                                                                            *
 * Parameters: templateids - [IN] vector of template IDs                      *
 *             items       - [IN/OUT] the template items                      *
 *                                                                            *
 ******************************************************************************/
static void	link_template_items_tag(const zbx_vector_uint64_t *templateids, zbx_vector_ptr_t *items)
{
	int				i, index;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t			itemid;
	zbx_db_tag_t			*db_tag;
	zbx_template_item_t		*item;
	zbx_hashset_t			items_t;
	zbx_vector_uint64_t		itemids;
	zbx_db_row_t			row;
	zbx_db_result_t			result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&itemids);
	zbx_hashset_create(&items_t, (size_t)items->values_num, template_item_hash_func, template_item_compare_func);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		if (NULL == item->key_)
			zbx_vector_uint64_append(&itemids, item->itemid);

		zbx_hashset_insert(&items_t, &item, sizeof(zbx_template_item_t *));
	}

	if (0 != itemids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select it.itemtagid,it.itemid,it.tag,it.value"
				" from item_tag it"
				" where ");

		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);

		result = zbx_db_select("%s", sql);
		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[1]);

			if (FAIL == (index = zbx_vector_ptr_bsearch(items, &itemid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			item = (zbx_template_item_t *)items->values[index];
			db_tag = zbx_db_tag_create(row[2], row[3]);
			ZBX_STR2UINT64(db_tag->tagid, row[0]);
			zbx_vector_db_tag_ptr_append(&item->item_tags, db_tag);
		}

		zbx_db_free_result(result);
		zbx_free(sql);
		sql_offset = 0;
		sql_alloc = 0;
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select it.itemid,it.tag,it.value"
			" from item_tag it,items ti"
			" where it.itemid=ti.itemid"
			" and");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values,
			templateids->values_num);

	result = zbx_db_select("%s", sql);
	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_template_item_t		item_local, *pitem_local = &item_local, **pitem;

		ZBX_STR2UINT64(item_local.templateid, row[0]);

		if (NULL == (pitem = (zbx_template_item_t **)zbx_hashset_search(&items_t, &pitem_local)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		db_tag = zbx_db_tag_create(row[1], row[2]);
		zbx_vector_db_tag_ptr_append(&(*pitem)->template_tags, db_tag);
	}
	zbx_db_free_result(result);
	zbx_free(sql);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];
		(void)zbx_merge_tags(&item->item_tags, &item->template_tags, NULL, NULL);
	}
	zbx_hashset_destroy(&items_t);
	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create item_params vectors in item data                           *
 *                                                                            *
 * Parameters: templateids - [IN] vector of template IDs                      *
 *             items       - [IN/OUT] the template items                      *
 *                                                                            *
 ******************************************************************************/
static void	link_template_items_param(const zbx_vector_uint64_t *templateids, zbx_vector_ptr_t *items)
{
	int				i, index;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t			itemid;
	zbx_item_param_t		*db_item_param;
	zbx_template_item_t		*item;
	zbx_hashset_t			items_t;
	zbx_vector_uint64_t		itemids;
	zbx_db_row_t			row;
	zbx_db_result_t			result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&itemids);
	zbx_hashset_create(&items_t, (size_t)items->values_num, template_item_hash_func, template_item_compare_func);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];

		if (NULL == item->key_)
			zbx_vector_uint64_append(&itemids, item->itemid);

		zbx_hashset_insert(&items_t, &item, sizeof(zbx_template_item_t *));
	}

	if (0 != itemids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select ip.item_parameterid,ip.itemid,ip.name,ip.value"
				" from item_parameter ip"
				" where ");

		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);

		result = zbx_db_select("%s", sql);
		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[1]);

			if (FAIL == (index = zbx_vector_ptr_bsearch(items, &itemid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			item = (zbx_template_item_t *)items->values[index];

			db_item_param = zbx_item_param_create(row[2], row[3]);
			ZBX_STR2UINT64(db_item_param->item_parameterid, row[0]);
			zbx_vector_item_param_ptr_append(&item->item_params, db_item_param);
		}
		zbx_db_free_result(result);
		zbx_free(sql);
		sql_offset = 0;
		sql_alloc = 0;
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select ip.itemid,ip.name,ip.value"
			" from item_parameter ip,items ti"
			" where ip.itemid=ti.itemid"
			" and");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "ti.hostid", templateids->values,
			templateids->values_num);

	result = zbx_db_select("%s", sql);
	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_template_item_t	item_local, *pitem_local = &item_local, **pitem;

		ZBX_STR2UINT64(item_local.templateid, row[0]);
		if (NULL == (pitem = (zbx_template_item_t **)zbx_hashset_search(&items_t, &pitem_local)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		db_item_param = zbx_item_param_create(row[1], row[2]);
		zbx_vector_item_param_ptr_append(&(*pitem)->template_params, db_item_param);
	}

	zbx_db_free_result(result);
	zbx_free(sql);

	for (i = 0; i < items->values_num; i++)
	{
		item = (zbx_template_item_t *)items->values[i];
		zbx_merge_item_params(&item->item_params, &item->template_params, NULL);
	}
	zbx_hashset_destroy(&items_t);
	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create lld_macro vectors in item data                             *
 *                                                                            *
 * Parameters: templateids - [IN] vector of template IDs                      *
 *             lld_itemids - [IN] the template discovery item ids             *
 *             lld_items   - [IN/OUT] the template discovery items            *
 *             items       - [IN/OUT] the template items                      *
 *                                                                            *
 ******************************************************************************/
static void	link_template_lld_macro_paths(const zbx_vector_uint64_t *templateids,
		const zbx_vector_uint64_t *lld_itemids, zbx_hashset_t *lld_items,  zbx_vector_ptr_t *items)
{
	int				i, index;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t			itemid;
	zbx_template_lld_macro_t	*plmpsrc, *plmpdst;
	zbx_template_item_t		*item;
	zbx_db_row_t			row;
	zbx_db_result_t			result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 != lld_itemids->values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select l.lld_macro_pathid,l.itemid,l.lld_macro,l.path"
				" from lld_macro_path l"
				" where ");

		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", lld_itemids->values,
				lld_itemids->values_num);

		result = zbx_db_select("%s", sql);
		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[1]);

			if (FAIL == (index = zbx_vector_ptr_bsearch(items, &itemid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			item = (zbx_template_item_t *)items->values[index];

			plmpdst = (zbx_template_lld_macro_t *)zbx_malloc(NULL, sizeof(zbx_template_lld_macro_t));

			plmpdst->upd_flags = ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_RESET_FLAG;
			ZBX_STR2UINT64(plmpdst->lld_macro_pathid, row[0]);
			plmpdst->lld_macro = zbx_strdup(NULL, row[2]);
			plmpdst->path = zbx_strdup(NULL, row[3]);

			zbx_vector_lld_macro_ptr_append(&item->item_lld_macros, plmpdst);
		}
		zbx_db_free_result(result);
		zbx_free(sql);
		sql_offset = 0;
		sql_alloc = 0;
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select l.lld_macro_pathid,l.itemid,l.lld_macro,l.path"
			" from lld_macro_path l,items i"
			" where l.itemid=i.itemid"
			" and");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values,
			templateids->values_num);

	result = zbx_db_select("%s", sql);
	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_template_item_t	item_local, *pitem_local = &item_local, **pitem;

		ZBX_STR2UINT64(item_local.templateid, row[1]);
		if (NULL == (pitem = (zbx_template_item_t **)zbx_hashset_search(lld_items, &pitem_local)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}
		plmpdst = (zbx_template_lld_macro_t *)zbx_malloc(NULL, sizeof(zbx_template_lld_macro_t));

		plmpdst->upd_flags = ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_RESET_FLAG;
		ZBX_STR2UINT64(plmpdst->lld_macro_pathid, row[0]);
		plmpdst->lld_macro = zbx_strdup(NULL, row[2]);
		plmpdst->path = zbx_strdup(NULL, row[3]);

		zbx_vector_lld_macro_ptr_append(&(*pitem)->template_lld_macros, plmpdst);
	}
	zbx_db_free_result(result);
	zbx_free(sql);

	for (i = 0; i < items->values_num; i++)
	{
		int	j, lld_macro_num;
		char	*buffer = NULL;

		item = (zbx_template_item_t *)items->values[i];

		zbx_vector_lld_macro_ptr_sort(&item->item_lld_macros, template_lld_macro_sort_by_macro);
		zbx_vector_lld_macro_ptr_sort(&item->template_lld_macros, template_lld_macro_sort_by_macro);

		lld_macro_num = MAX(item->item_lld_macros.values_num, item->template_lld_macros.values_num);

		for (j = 0; j < lld_macro_num; j++)
		{
			if (j >= item->item_lld_macros.values_num)
			{
				plmpsrc = (zbx_template_lld_macro_t *)item->template_lld_macros.values[j];
				plmpdst = (zbx_template_lld_macro_t *)zbx_malloc(NULL,
						sizeof(zbx_template_lld_macro_t));
				plmpdst->lld_macro_pathid = 0;
				plmpdst->upd_flags = ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_RESET_FLAG;
				plmpdst->lld_macro = zbx_strdup(NULL, plmpsrc->lld_macro);
				plmpdst->path = zbx_strdup(NULL, plmpsrc->path);
				zbx_vector_lld_macro_ptr_append(&item->item_lld_macros, plmpdst);
				continue;
			}

			plmpdst = (zbx_template_lld_macro_t *)item->item_lld_macros.values[j];

			if (j >= item->template_lld_macros.values_num)
			{
				plmpdst->upd_flags |= ZBX_FLAG_TEMPLATE_LLD_MACRO_DELETE;
				continue;
			}

			plmpsrc = (zbx_template_lld_macro_t *)item->template_lld_macros.values[j];

			buffer = zbx_strdup(buffer, plmpsrc->lld_macro);

			if (0 != strcmp(plmpdst->lld_macro, buffer))
			{
				plmpdst->lld_macro_orig = zbx_strdup(NULL, plmpdst->lld_macro);
				zbx_free(plmpdst->lld_macro);

				plmpdst->lld_macro = buffer;
				buffer = NULL;
				plmpdst->upd_flags |= ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_LLD_MACRO;
			}

			buffer = zbx_strdup(buffer, plmpsrc->path);

			if (0 != strcmp(plmpdst->path, buffer))
			{
				plmpdst->path_orig = zbx_strdup(NULL, plmpdst->path);
				zbx_free(plmpdst->path);

				plmpdst->path = buffer;
				buffer = NULL;
				plmpdst->upd_flags |= ZBX_FLAG_TEMPLATE_LLD_MACRO_UPDATE_PATH;
			}
			else
				zbx_free(buffer);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
/******************************************************************************
 *                                                                            *
 * Purpose: prepare lld items by indexing them and scanning for already       *
 *          existing items                                                    *
 *                                                                            *
 * Parameters: items       - [IN] lld items                                   *
 *             lld_itemids - [OUT] identifiers of existing lld items          *
 *             lld_items   - [OUT] lld items indexed by itemid                *
 *                                                                            *
 ******************************************************************************/
static void	prepare_lld_items(const zbx_vector_ptr_t *items, zbx_vector_uint64_t *lld_itemids,
		zbx_hashset_t *lld_items)
{
	int				i;
	const zbx_template_item_t	*item;

	for (i = 0; i < items->values_num; i++)
	{
		item = (const zbx_template_item_t *)items->values[i];

		if (0 == (ZBX_FLAG_DISCOVERY_RULE & item->flags))
			continue;

		if (NULL == item->key_)	/* item already existed */
			zbx_vector_uint64_append(lld_itemids, item->itemid);

		zbx_hashset_insert(lld_items, &item, sizeof(zbx_template_item_t *));
	}

	zbx_vector_uint64_sort(lld_itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies template items to host                                     *
 *                                                                            *
 * Parameters:                                                                *
 *             hostid             - [IN]                                      *
 *             templateids        - [IN]                                      *
 *             audit_context_mode - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
void	DBcopy_template_items(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids, int audit_context_mode)
{
	zbx_vector_ptr_t	items, lld_rules;
	int			new_conditions = 0;
	zbx_vector_uint64_t	lld_itemids;
	zbx_hashset_t		lld_items;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_ptr_create(&items);
	zbx_vector_ptr_create(&lld_rules);

	get_template_items(hostid, templateids, &items);

	if (0 == items.values_num)
		goto out;

	get_template_lld_rule_map(&items, &lld_rules);

	new_conditions = calculate_template_lld_rule_conditionids(&lld_rules);
	update_template_lld_rule_formulas(&items, &lld_rules);

	link_template_dependent_items(&items);
	link_template_items_preproc(templateids, &items);
	link_template_items_tag(templateids, &items);
	link_template_items_param(templateids, &items);
	save_template_items(hostid, &items, audit_context_mode);
	save_template_lld_rules(&items, &lld_rules, new_conditions, audit_context_mode);
	save_template_discovery_prototypes(hostid, &items, audit_context_mode);
	copy_template_items_preproc(&items, audit_context_mode);
	copy_template_item_script_params(&items, audit_context_mode);
	copy_template_item_tags(&items, audit_context_mode);

	zbx_vector_uint64_create(&lld_itemids);
	zbx_hashset_create(&lld_items, (size_t)items.values_num, template_item_hash_func, template_item_compare_func);

	prepare_lld_items(&items, &lld_itemids, &lld_items);
	if (0 != lld_items.num_data)
	{
		link_template_lld_macro_paths(templateids, &lld_itemids, &lld_items, &items);
		copy_template_lld_macro_paths(&items, audit_context_mode);
		copy_template_lld_overrides(templateids, &lld_itemids, &lld_items, audit_context_mode);
	}

	zbx_hashset_destroy(&lld_items);
	zbx_vector_uint64_destroy(&lld_itemids);
out:
	zbx_vector_ptr_clear_ext(&lld_rules, (zbx_clean_func_t)free_lld_rule_map);
	zbx_vector_ptr_destroy(&lld_rules);

	zbx_vector_ptr_clear_ext(&items, (zbx_clean_func_t)free_template_item);
	zbx_vector_ptr_destroy(&items);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
