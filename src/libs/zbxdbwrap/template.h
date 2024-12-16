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

#ifndef ZABBIX_TEMPLATE_H
#define ZABBIX_TEMPLATE_H

#include "zbxdbhigh.h"
#include "zbxalgo.h"

typedef struct _zbx_template_item_preproc_t zbx_template_item_preproc_t;
ZBX_PTR_VECTOR_DECL(item_preproc_ptr, zbx_template_item_preproc_t *)

typedef struct _zbx_template_lld_macro_t zbx_template_lld_macro_t;
ZBX_PTR_VECTOR_DECL(lld_macro_ptr, zbx_template_lld_macro_t *)

typedef struct
{
	zbx_uint64_t			itemid;
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_RESET_FLAG		__UINT64_C(0x0000000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_INTERFACEID		__UINT64_C(0x0000000000001)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_TEMPLATEID		__UINT64_C(0x0000000000002)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_NAME			__UINT64_C(0x0000000000004)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_TYPE			__UINT64_C(0x0000000000008)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_VALUE_TYPE		__UINT64_C(0x0000000000010)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_DELAY			__UINT64_C(0x0000000000020)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_HISTORY			__UINT64_C(0x0000000000040)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_TRENDS			__UINT64_C(0x0000000000080)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_STATUS			__UINT64_C(0x0000000000100)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_TRAPPER_HOSTS		__UINT64_C(0x0000000000200)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_UNITS			__UINT64_C(0x0000000000400)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_FORMULA			__UINT64_C(0x0000000000800)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_LOGTIMEFMT		__UINT64_C(0x0000000001000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_VALUEMAPID		__UINT64_C(0x0000000002000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_PARAMS			__UINT64_C(0x0000000004000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_IPMI_SENSOR		__UINT64_C(0x0000000008000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_SNMP_OID			__UINT64_C(0x0000000010000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_AUTHTYPE			__UINT64_C(0x0000000020000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_USERNAME			__UINT64_C(0x0000000040000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_PASSWORD			__UINT64_C(0x0000000080000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_PUBLICKEY			__UINT64_C(0x0000000100000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_PRIVATEKEY		__UINT64_C(0x0000000200000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_FLAGS			__UINT64_C(0x0000000400000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_DESCRIPTION		__UINT64_C(0x0000000800000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_INVENTORY_LINK		__UINT64_C(0x0000001000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_LIFETIME			__UINT64_C(0x0000002000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_EVALTYPE			__UINT64_C(0x0000004000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_JMX_ENDPOINT		__UINT64_C(0x0000008000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_MASTER_ITEMID		__UINT64_C(0x0000010000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_TIMEOUT			__UINT64_C(0x0000020000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_URL			__UINT64_C(0x0000040000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_QUERY_FIELDS		__UINT64_C(0x0000080000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_POSTS			__UINT64_C(0x0000100000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_STATUS_CODES		__UINT64_C(0x0000200000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_FOLLOW_REDIRECTS		__UINT64_C(0x0000400000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_POST_TYPE			__UINT64_C(0x0000800000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_HTTP_PROXY		__UINT64_C(0x0001000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_HEADERS			__UINT64_C(0x0002000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_RETRIEVE_MODE		__UINT64_C(0x0004000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_REQUEST_METHOD		__UINT64_C(0x0008000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_OUTPUT_FORMAT		__UINT64_C(0x0010000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_SSL_CERT_FILE		__UINT64_C(0x0020000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_SSL_KEY_FILE		__UINT64_C(0x0040000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_SSL_KEY_PASSWORD		__UINT64_C(0x0080000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_VERIFY_PEER		__UINT64_C(0x0100000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_VERIFY_HOST		__UINT64_C(0x0200000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_ALLOW_TRAPS		__UINT64_C(0x0400000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_DISCOVER			__UINT64_C(0x0800000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_LIFETIME_TYPE		__UINT64_C(0x1000000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_ENABLED_LIFETIME		__UINT64_C(0x2000000000000)
#define ZBX_FLAG_TEMPLATE_ITEM_UPDATE_ENABLED_LIFETIME_TYPE	__UINT64_C(0x4000000000000)

	zbx_uint64_t			upd_flags;
	zbx_uint64_t			valuemapid_orig;
	zbx_uint64_t			valuemapid;
	zbx_uint64_t			interfaceid_orig;
	zbx_uint64_t			interfaceid;
	zbx_uint64_t			templateid_orig;
	zbx_uint64_t			templateid;
	zbx_uint64_t			master_itemid_orig;
	zbx_uint64_t			master_itemid;
	char				*name_orig;
	char				*name;
	char				*key_;
	char				*delay_orig;
	char				*delay;
	char				*history_orig;
	char				*history;
	char				*trends_orig;
	char				*trends;
	char				*trapper_hosts_orig;
	char				*trapper_hosts;
	char				*units_orig;
	char				*units;
	char				*formula_orig;
	char				*formula;
	char				*logtimefmt_orig;
	char				*logtimefmt;
	char				*params_orig;
	char				*params;
	char				*ipmi_sensor_orig;
	char				*ipmi_sensor;
	char				*snmp_oid_orig;
	char				*snmp_oid;
	char				*username_orig;
	char				*username;
	char				*password_orig;
	char				*password;
	char				*publickey_orig;
	char				*publickey;
	char				*privatekey_orig;
	char				*privatekey;
	char				*description_orig;
	char				*description;
	char				*lifetime_orig;
	char				*lifetime;
	char				*enabled_lifetime_orig;
	char				*enabled_lifetime;
	char				*jmx_endpoint_orig;
	char				*jmx_endpoint;
	char				*timeout_orig;
	char				*timeout;
	char				*url_orig;
	char				*url;
	char				*query_fields_orig;
	char				*query_fields;
	char				*posts_orig;
	char				*posts;
	char				*status_codes_orig;
	char				*status_codes;
	char				*http_proxy_orig;
	char				*http_proxy;
	char				*headers_orig;
	char				*headers;
	char				*ssl_cert_file_orig;
	char				*ssl_cert_file;
	char				*ssl_key_file_orig;
	char				*ssl_key_file;
	char				*ssl_key_password_orig;
	char				*ssl_key_password;
	unsigned char			verify_peer_orig;
	unsigned char			verify_peer;
	unsigned char			verify_host_orig;
	unsigned char			verify_host;
	unsigned char			follow_redirects_orig;
	unsigned char			follow_redirects;
	unsigned char			post_type_orig;
	unsigned char			post_type;
	unsigned char			retrieve_mode_orig;
	unsigned char			retrieve_mode;
	unsigned char			request_method_orig;
	unsigned char			request_method;
	unsigned char			output_format_orig;
	unsigned char			output_format;
	unsigned char			type_orig;
	unsigned char			type;
	unsigned char			value_type_orig;
	unsigned char			value_type;
	unsigned char			status_orig;
	unsigned char			status;
	unsigned char			authtype_orig;
	unsigned char			authtype;
	unsigned char			flags_orig;
	unsigned char			flags;
	unsigned char			inventory_link_orig;
	unsigned char			inventory_link;
	unsigned char			evaltype_orig;
	unsigned char			evaltype;
	unsigned char			allow_traps_orig;
	unsigned char			allow_traps;
	unsigned char			discover_orig;
	unsigned char			discover;
	unsigned char			lifetime_type_orig;
	unsigned char			lifetime_type;
	unsigned char			enabled_lifetime_type_orig;
	unsigned char			enabled_lifetime_type;
	zbx_vector_ptr_t		dependent_items;
	zbx_vector_item_preproc_ptr_t	item_preprocs;
	zbx_vector_item_preproc_ptr_t	template_preprocs;
	zbx_vector_db_tag_ptr_t		item_tags;
	zbx_vector_db_tag_ptr_t		template_tags;
	zbx_vector_item_param_ptr_t	item_params;
	zbx_vector_item_param_ptr_t	template_params;
	zbx_vector_lld_macro_ptr_t	item_lld_macros;
	zbx_vector_lld_macro_ptr_t	template_lld_macros;
}
zbx_template_item_t;

void	DBcopy_template_items(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids, int audit_context_mode);
void	zbx_audit_item_update_json_add_data(int audit_context_mode, zbx_uint64_t itemid,
		const zbx_template_item_t *item, zbx_uint64_t hostid);
#endif
