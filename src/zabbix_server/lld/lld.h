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

#ifndef ZABBIX_LLD_H
#define ZABBIX_LLD_H

#include "zbxalgo.h"
#include "zbxjson.h"
#include "zbxcacheconfig.h"
#include "zbxdbhigh.h"

typedef struct zbx_lld_item_full_s zbx_lld_item_full_t;
typedef struct zbx_lld_dependency_s zbx_lld_dependency_t;
typedef struct zbx_lld_trigger_s zbx_lld_trigger_t;

ZBX_PTR_VECTOR_DECL(lld_item_full_ptr, zbx_lld_item_full_t*)
ZBX_PTR_VECTOR_DECL(lld_dependency_ptr, zbx_lld_dependency_t*)
ZBX_PTR_VECTOR_DECL(lld_trigger_ptr, zbx_lld_trigger_t*)

typedef struct
{
	zbx_uint64_t	itemid;
	unsigned char	flags;
}
zbx_lld_item_t;

int	lld_item_compare_func(const void *d1, const void *d2);

ZBX_PTR_VECTOR_DECL(lld_item_ptr, zbx_lld_item_t*)

typedef struct
{
	zbx_uint64_t	parent_itemid;
	zbx_uint64_t	itemid;		/* the item, created by the item prototype */
}
zbx_lld_item_link_t;

ZBX_PTR_VECTOR_DECL(lld_item_link_ptr, zbx_lld_item_link_t*)

int	lld_item_link_compare_func(const void *d1, const void *d2);
void	lld_item_link_free(zbx_lld_item_link_t *item_link);


#define ZBX_LLD_DISCOVERY_STATUS_NORMAL		0
#define ZBX_LLD_DISCOVERY_STATUS_LOST		1

#define ZBX_LLD_OBJECT_STATUS_ENABLED		0
#define ZBX_LLD_OBJECT_STATUS_DISABLED		1

typedef struct
{
	char	*lld_macro;
	char	*path;
}
zbx_lld_macro_path_t;

ZBX_PTR_VECTOR_DECL(lld_macro_path_ptr, zbx_lld_macro_path_t *)

int	zbx_lld_macro_paths_get(zbx_uint64_t lld_ruleid, zbx_vector_lld_macro_path_ptr_t *lld_macro_paths,
		char **error);
void	zbx_lld_macro_path_free(zbx_lld_macro_path_t *lld_macro_path);
int	zbx_lld_macro_paths_compare(const void *d1, const void *d2);
zbx_lld_macro_path_t	*lld_macro_path_create(const char *lld_macro, const char *path);

typedef struct
{
	char	*macro;
	char	*value;
}
zbx_lld_macro_t;

ZBX_VECTOR_DECL(lld_macro, zbx_lld_macro_t)

/* LLD macros extracted from LLD row */
typedef struct
{
	zbx_vector_lld_macro_t	macros;
	zbx_vector_lld_macro_t	*exported_macros;
	const zbx_jsonobj_t	*source;
}
zbx_lld_entry_t;

ZBX_PTR_VECTOR_DECL(lld_entry_ptr, zbx_lld_entry_t *)

void	lld_entry_clear(zbx_lld_entry_t *entry);
void	lld_entry_clear_wrapper(void *data);

zbx_hash_t	lld_entry_hash(const void *data);
int	lld_entry_compare(const void *d1, const void *d2);
void	lld_entry_snprintf_alloc(const zbx_lld_entry_t *entry, char **str, size_t *str_alloc, size_t *str_offset);
const char	*lld_entry_get_macro(const zbx_lld_entry_t *entry, const char *macro);

int	lld_extract_entries(zbx_hashset_t *entries, zbx_vector_lld_entry_ptr_t *entries_sorted,
		const zbx_jsonobj_t *lld_obj, const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **error);
void	lld_free_entries(zbx_hashset_t *entries);
int	lld_compare_entries(const zbx_hashset_t *entries1, const zbx_hashset_t *entries2);

int	lld_macro_value_by_name(const zbx_lld_entry_t *lld_obj, const char *macro, char **value);
void	lld_macro_clear(zbx_lld_macro_t *macro);
int	lld_macro_compare(const void *d1, const void *d2);

/* lld rule lifetime */
typedef struct
{
#define ZBX_LLD_LIFETIME_TYPE_AFTER		0
#define ZBX_LLD_LIFETIME_TYPE_NEVER		1
#define ZBX_LLD_LIFETIME_TYPE_IMMEDIATELY	2
	unsigned char		type;

	int			duration;
}
zbx_lld_lifetime_t;

typedef struct
{
	const zbx_lld_entry_t		*data;
	zbx_vector_lld_item_link_ptr_t	item_links;	/* the list of item prototypes */
	zbx_vector_lld_override_ptr_t	overrides;
}
zbx_lld_row_t;

ZBX_PTR_VECTOR_DECL(lld_row_ptr, zbx_lld_row_t*)

void	lld_row_free(zbx_lld_row_t *lld_row);

typedef struct
{
	zbx_uint64_t	item_preprocid;
	int		step;
	int		type;
	int		type_orig;
	int		error_handler;
	int		error_handler_orig;
	char		*params;
	char		*params_orig;
	char		*error_handler_params;
	char		*error_handler_params_orig;

#define ZBX_FLAG_LLD_ITEM_PREPROC_UNSET				__UINT64_C(0x00)
#define ZBX_FLAG_LLD_ITEM_PREPROC_DISCOVERED			__UINT64_C(0x01)
#define ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_TYPE			__UINT64_C(0x02)
#define ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_PARAMS			__UINT64_C(0x04)
#define ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_ERROR_HANDLER		__UINT64_C(0x08)
#define ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_ERROR_HANDLER_PARAMS	__UINT64_C(0x10)
#define ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_STEP			__UINT64_C(0x20)
#define ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE				\
		(ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_TYPE |		\
		ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_PARAMS |		\
		ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_ERROR_HANDLER |	\
		ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_ERROR_HANDLER_PARAMS |	\
		ZBX_FLAG_LLD_ITEM_PREPROC_UPDATE_STEP			\
		)
	zbx_uint64_t	flags;
}
zbx_lld_item_preproc_t;

ZBX_PTR_VECTOR_DECL(lld_item_preproc_ptr, zbx_lld_item_preproc_t*)

typedef struct
{
	zbx_uint64_t		overrideid;
	zbx_sync_rowset_t	conditions;
	zbx_sync_rowset_t	operations;
	zbx_sync_rowset_t	optags;
	zbx_sync_rowset_t	optemplates;
}
zbx_lld_override_data_t;

ZBX_PTR_VECTOR_DECL(lld_override_data_ptr, zbx_lld_override_data_t *)

zbx_lld_override_data_t *lld_override_data_create(zbx_uint64_t overrideid);
void	lld_override_data_free(void *v);
int	lld_override_data_compare(const void *v1, const void *v2);

typedef struct
{
	zbx_uint64_t				itemid;
	zbx_uint64_t				valuemapid;
	zbx_uint64_t				interfaceid;
	zbx_uint64_t				master_itemid;
	char					*name;
	char					*key;
	char					*delay;
	char					*history;
	char					*trends;
	char					*trapper_hosts;
	char					*units;
	char					*formula;
	char					*logtimefmt;
	char					*params;
	char					*ipmi_sensor;
	char					*snmp_oid;
	char					*username;
	char					*password;
	char					*publickey;
	char					*privatekey;
	char					*description;
	char					*jmx_endpoint;
	char					*timeout;
	char					*url;
	char					*query_fields;
	char					*posts;
	char					*status_codes;
	char					*http_proxy;
	char					*headers;
	char					*ssl_cert_file;
	char					*ssl_key_file;
	char					*ssl_key_password;
	unsigned char				verify_peer;
	unsigned char				verify_host;
	unsigned char				follow_redirects;
	unsigned char				post_type;
	unsigned char				retrieve_mode;
	unsigned char				request_method;
	unsigned char				output_format;
	unsigned char				type;
	unsigned char				value_type;
	unsigned char				status;
	unsigned char				authtype;
	unsigned char				allow_traps;
	unsigned char				discover;
	zbx_vector_lld_row_ptr_t		lld_rows;
	zbx_vector_lld_item_preproc_ptr_t	preproc_ops;
	zbx_vector_item_param_ptr_t		item_params;
	zbx_vector_db_tag_ptr_t			item_tags;
	zbx_hashset_t				item_index;
	zbx_vector_str_t			keys;		/* keys used to create items from this prototype */

	char					*lifetime;
	char					*enabled_lifetime;
	int					lifetime_type;
	int					enabled_lifetime_type;
	int					evaltype;
	int					item_flags;

#define ZBX_LLD_ITEM_PROTOTYPE_MACRO_PATH_COLS_NUM	2
#define ZBX_LLD_ITEM_PROTOTYPE_FILTERS_COLS_NUM		3
	zbx_sync_rowset_t			macro_paths;
	zbx_sync_rowset_t			filters;
	zbx_sync_rowset_t			overrides;
}
zbx_lld_item_prototype_t;

ZBX_PTR_VECTOR_DECL(lld_item_prototype_ptr, zbx_lld_item_prototype_t*)

int	lld_item_prototype_compare_func(const void *d1, const void *d2);

struct zbx_lld_item_full_s
{
	zbx_uint64_t				itemid;
	zbx_uint64_t				parent_itemid;
	zbx_uint64_t				master_itemid;
	zbx_uint64_t				master_itemid_orig;
#define ZBX_FLAG_LLD_ITEM_UNSET				__UINT64_C(0x0000000000000000)
#define ZBX_FLAG_LLD_ITEM_DISCOVERED			__UINT64_C(0x0000000000000001)
#define ZBX_FLAG_LLD_ITEM_UPDATE_NAME			__UINT64_C(0x0000000000000002)
#define ZBX_FLAG_LLD_ITEM_UPDATE_KEY			__UINT64_C(0x0000000000000004)
#define ZBX_FLAG_LLD_ITEM_UPDATE_TYPE			__UINT64_C(0x0000000000000008)
#define ZBX_FLAG_LLD_ITEM_UPDATE_VALUE_TYPE		__UINT64_C(0x0000000000000010)
#define ZBX_FLAG_LLD_ITEM_UPDATE_DELAY			__UINT64_C(0x0000000000000020)
#define ZBX_FLAG_LLD_ITEM_UPDATE_HISTORY		__UINT64_C(0x0000000000000040)
#define ZBX_FLAG_LLD_ITEM_UPDATE_TRENDS			__UINT64_C(0x0000000000000080)
#define ZBX_FLAG_LLD_ITEM_UPDATE_TRAPPER_HOSTS		__UINT64_C(0x0000000000000100)
#define ZBX_FLAG_LLD_ITEM_UPDATE_UNITS			__UINT64_C(0x0000000000000200)
#define ZBX_FLAG_LLD_ITEM_UPDATE_FORMULA		__UINT64_C(0x0000000000000400)
#define ZBX_FLAG_LLD_ITEM_UPDATE_LOGTIMEFMT		__UINT64_C(0x0000000000000800)
#define ZBX_FLAG_LLD_ITEM_UPDATE_VALUEMAPID		__UINT64_C(0x0000000000001000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_PARAMS			__UINT64_C(0x0000000000002000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_IPMI_SENSOR		__UINT64_C(0x0000000000004000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SNMP_OID		__UINT64_C(0x0000000000008000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_AUTHTYPE		__UINT64_C(0x0000000000010000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_USERNAME		__UINT64_C(0x0000000000020000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_PASSWORD		__UINT64_C(0x0000000000040000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_PUBLICKEY		__UINT64_C(0x0000000000080000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_PRIVATEKEY		__UINT64_C(0x0000000000100000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_DESCRIPTION		__UINT64_C(0x0000000000200000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_INTERFACEID		__UINT64_C(0x0000000000400000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_JMX_ENDPOINT		__UINT64_C(0x0000000000800000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_MASTER_ITEM		__UINT64_C(0x0000000001000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_TIMEOUT		__UINT64_C(0x0000000002000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_URL			__UINT64_C(0x0000000004000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_QUERY_FIELDS		__UINT64_C(0x0000000008000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_POSTS			__UINT64_C(0x0000000010000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_STATUS_CODES		__UINT64_C(0x0000000020000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_FOLLOW_REDIRECTS	__UINT64_C(0x0000000040000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_POST_TYPE		__UINT64_C(0x0000000080000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_HTTP_PROXY		__UINT64_C(0x0000000100000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_HEADERS		__UINT64_C(0x0000000200000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_RETRIEVE_MODE		__UINT64_C(0x0000000400000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_REQUEST_METHOD		__UINT64_C(0x0000000800000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_OUTPUT_FORMAT		__UINT64_C(0x0000001000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SSL_CERT_FILE		__UINT64_C(0x0000002000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SSL_KEY_FILE		__UINT64_C(0x0000004000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_SSL_KEY_PASSWORD	__UINT64_C(0x0000008000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_VERIFY_PEER		__UINT64_C(0x0000010000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_VERIFY_HOST		__UINT64_C(0x0000020000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_ALLOW_TRAPS		__UINT64_C(0x0000040000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_LIFETIME		__UINT64_C(0x0000080000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_LIFETIME_TYPE		__UINT64_C(0x0000100000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_ENABLED_LIFETIME	__UINT64_C(0x0000200000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_ENABLED_LIFETIME_TYPE	__UINT64_C(0x0000400000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_EVALTYPE		__UINT64_C(0x0000800000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_STATUS			__UINT64_C(0x0001000000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE_DISCOVER		__UINT64_C(0x0002000000000000)
#define ZBX_FLAG_LLD_ITEM_UPDATE			(~ZBX_FLAG_LLD_ITEM_DISCOVERED)
	zbx_uint64_t				flags;
	char					*key_proto;
	char					*name;
	char					*name_proto;
	char					*key_orig;
	char					*key_;
	char					*delay_orig;
	char					*delay;
	char					*history_orig;
	char					*history;
	char					*trends_orig;
	char					*trends;
	char					*units_orig;
	char					*units;
	char					*params_orig;
	char					*params;
	char					*username_orig;
	char					*username;
	char					*password_orig;
	char					*password;
	char					*ipmi_sensor_orig;
	char					*ipmi_sensor;
	char					*snmp_oid_orig;
	char					*snmp_oid;
	char					*description_orig;
	char					*description;
	char					*jmx_endpoint_orig;
	char					*jmx_endpoint;
	char					*timeout_orig;
	char					*timeout;
	char					*url_orig;
	char					*url;
	char					*query_fields_orig;
	char					*query_fields;
	char					*posts_orig;
	char					*posts;
	char					*status_codes_orig;
	char					*status_codes;
	char					*http_proxy_orig;
	char					*http_proxy;
	char					*headers_orig;
	char					*headers;
	char					*ssl_cert_file_orig;
	char					*ssl_cert_file;
	char					*ssl_key_file_orig;
	char					*ssl_key_file;
	char					*ssl_key_password_orig;
	char					*ssl_key_password;
	unsigned char				discovery_status;
	int					lastcheck;
	int					ts_delete;
	int					ts_disable;
	unsigned char				disable_source;
	const zbx_lld_row_t			*lld_row;
	const zbx_lld_item_prototype_t		*prototype;
	zbx_vector_lld_item_preproc_ptr_t	preproc_ops;
	zbx_vector_lld_item_full_ptr_t		dependent_items;
	zbx_vector_item_param_ptr_t		item_params;
	zbx_vector_db_tag_ptr_t			item_tags;
	zbx_vector_db_tag_ptr_t			override_tags;
	unsigned char				status;
	unsigned char				status_orig;
	unsigned char				type_orig;
	unsigned char				type;
	unsigned char				value_type_orig;
	unsigned char				discover;
	unsigned char				discover_orig;
	char					*trapper_hosts_orig;
	char					*formula;
	char					*formula_orig;
	char					*logtimefmt_orig;
	zbx_uint64_t				valuemapid_orig;
	unsigned char				authtype_orig;
	char					*publickey_orig;
	char					*privatekey_orig;
	zbx_uint64_t				interfaceid_orig;
	unsigned char				follow_redirects_orig;
	unsigned char				post_type_orig;
	unsigned char				retrieve_mode_orig;
	unsigned char				request_method_orig;
	unsigned char				output_format_orig;
	unsigned char				verify_peer_orig;
	unsigned char				verify_host_orig;
	unsigned char				allow_traps_orig;

	char					*lifetime_orig;
	char					*enabled_lifetime_orig;
	int					lifetime_type_orig;
	int					enabled_lifetime_type_orig;
	int					evaltype_orig;
	int					item_flags;		/* discovery flag - lld rule/prototype */

	zbx_sync_rowset_t			macro_paths;
	zbx_sync_rowset_t			filters;
	zbx_sync_rowset_t			overrides;
};

typedef struct
{
	const zbx_lld_entry_t	*data;
	zbx_uint64_t 		ruleid;
}
zbx_lld_row_ruleid_t;

int	lld_item_full_compare_func(const void *d1, const void *d2);

int	lld_ids_names_compare_func(const void *d1, const void *d2);
void	lld_field_str_rollback(char **field, char **field_orig, zbx_uint64_t *flags, zbx_uint64_t flag);

void	lld_override_item(const zbx_vector_lld_override_ptr_t *overrides, const char *name, const char **delay,
		const char **history, const char **trends, zbx_vector_db_tag_ptr_t *override_tags,
		unsigned char *status, unsigned char *discover);
void	lld_override_lldrule(const zbx_vector_lld_override_ptr_t *overrides, const char *name, const char **delay,
		unsigned char *status, unsigned char *discover);
void	lld_override_trigger(const zbx_vector_lld_override_ptr_t *overrides, const char *name, unsigned char *severity,
		zbx_vector_db_tag_ptr_t *override_tags, unsigned char *status, unsigned char *discover);
void	lld_override_host(const zbx_vector_lld_override_ptr_t *overrides, const char *name,
		zbx_vector_uint64_t *lnk_templateids, signed char *inventory_mode,
		zbx_vector_db_tag_ptr_t *override_tags, unsigned char *status, unsigned char *discover);
void	lld_override_graph(const zbx_vector_lld_override_ptr_t *overrides, const char *name, unsigned char *discover);

int	lld_validate_item_override_no_discover(const zbx_vector_lld_override_ptr_t *overrides, const char *name,
		unsigned char override_default);
int	lld_validate_lldrule_override_no_discover(const zbx_vector_lld_override_ptr_t *overrides, const char *name,
		unsigned char override_default);

int	lld_update_items(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, zbx_vector_lld_row_ptr_t *lld_rows, char **error,
		const zbx_lld_lifetime_t *lifetime, const zbx_lld_lifetime_t *enabled_lifetime, int lastcheck,
		int dflags, zbx_hashset_t *rule_index, const zbx_vector_uint64_t *ruleids);

void	lld_item_links_sort(zbx_vector_lld_row_ptr_t *lld_rows);

int	lld_update_triggers(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, const zbx_vector_lld_row_ptr_t *lld_rows,
		char **error, const zbx_lld_lifetime_t *lifetime, const zbx_lld_lifetime_t *enabled_lifetime,
		int lastcheck, int dflags, const zbx_vector_uint64_t *ruleids);

int	lld_update_graphs(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, const zbx_vector_lld_row_ptr_t *lld_rows,
		char **error, const zbx_lld_lifetime_t *lifetime, int lastcheck, int dflags,
		const zbx_vector_uint64_t *ruleids);

void	lld_update_hosts(zbx_uint64_t lld_ruleid, const zbx_vector_lld_row_ptr_t *lld_rows, char **error,
		const zbx_lld_lifetime_t *lifetime, const zbx_lld_lifetime_t *enabled_lifetime, int lastcheck,
		int dflags, zbx_hashset_t *rule_index, const zbx_vector_uint64_t *ruleids);

int	lld_rule_discover_prototypes(zbx_uint64_t hostid, const zbx_vector_lld_row_ptr_t *lld_rows,
		const zbx_vector_lld_item_prototype_ptr_t *item_prototypes, zbx_vector_lld_item_full_ptr_t *items,
		char **error, int lastcheck, zbx_hashset_t *items_index);

int	lld_end_of_life(int lastcheck, int lifetime);

typedef void	(*delete_ids_f)(zbx_vector_uint64_t *ids, int audit_context_mode);
typedef void	(*object_audit_entry_create_f)(int audit_context_mode, int audit_action, zbx_uint64_t objectid,
		const char *name, int flags);
typedef void	(*object_audit_entry_update_status_f)(int audit_context_mode, zbx_uint64_t objectid, int flags,
		int status_old, int status_new);
typedef unsigned char	(get_object_status_val)(int status);

int	lld_process_discovery_rule(zbx_dc_item_t *item, zbx_vector_lld_entry_ptr_t *lld_entries, char **error);

/* discovered resource tracking (*_discovery tables) */
typedef struct
{
	zbx_uint64_t	id;
	const char	*name;

	unsigned char	discovery_status;
	unsigned char	disable_source;
	unsigned char	object_status;

	int		ts_delete;
	int		ts_disable;

	zbx_uint64_t	flags;
#define ZBX_LLD_DISCOVERY_UPDATE_NONE			__UINT64_C(0)
#define ZBX_LLD_DISCOVERY_UPDATE_LASTCHECK		__UINT64_C(0x0001)
#define ZBX_LLD_DISCOVERY_UPDATE_DISCOVERY_STATUS	__UINT64_C(0x0002)
#define ZBX_LLD_DISCOVERY_UPDATE_DISABLE_SOURCE		__UINT64_C(0x0004)
#define ZBX_LLD_DISCOVERY_UPDATE_TS_DELETE		__UINT64_C(0x0008)
#define ZBX_LLD_DISCOVERY_UPDATE_TS_DISABLE		__UINT64_C(0x0010)

#define ZBX_LLD_DISCOVERY_UPDATE_OBJECT_EXISTS		__UINT64_C(0x2000)
#define ZBX_LLD_DISCOVERY_UPDATE_OBJECT_STATUS		__UINT64_C(0x4000)
#define ZBX_LLD_DISCOVERY_DELETE_OBJECT			__UINT64_C(0x8000)

#define ZBX_LLD_DISCOVERY_UPDATE	(ZBX_LLD_DISCOVERY_UPDATE_LASTCHECK | 		\
					ZBX_LLD_DISCOVERY_UPDATE_DISCOVERY_STATUS |	\
					ZBX_LLD_DISCOVERY_UPDATE_DISABLE_SOURCE |	\
					ZBX_LLD_DISCOVERY_UPDATE_TS_DELETE |		\
					ZBX_LLD_DISCOVERY_UPDATE_TS_DISABLE)
}
zbx_lld_discovery_t;

ZBX_VECTOR_DECL(lld_discovery_ptr, zbx_lld_discovery_t *)

zbx_lld_discovery_t	*lld_add_discovery(zbx_hashset_t *discoveries, zbx_uint64_t id, const char *name);
void	lld_process_discovered_object(zbx_lld_discovery_t *discovery, unsigned char discovery_status, int ts_delete,
		int lastcheck, int now);
void	lld_enable_discovered_object(zbx_lld_discovery_t *discovery, unsigned char object_status,
		unsigned char disable_source, int ts_disable);
void	lld_process_lost_object(zbx_lld_discovery_t *discovery, unsigned char object_status, int lastcheck, int now,
		const zbx_lld_lifetime_t *lifetime, unsigned char discovery_status, int disable_source, int ts_delete);
void	lld_disable_lost_object(zbx_lld_discovery_t *discovery, unsigned char object_status, int lastcheck, int now,
		const zbx_lld_lifetime_t *lifetime, int ts_disable);
void	lld_flush_discoveries(zbx_hashset_t *discoveries, const char *id_field, const char *object_table,
		const char *discovery_table, int now, get_object_status_val cb_status, delete_ids_f cb_delete_objects,
		object_audit_entry_create_f cb_audit_create, object_audit_entry_update_status_f cb_audit_update_status);

/* item index by prototype (parent) id and lld row */
typedef struct
{
	zbx_uint64_t		parent_itemid;
	zbx_lld_row_t		*lld_row;
	zbx_lld_item_full_t	*item;
}
zbx_lld_item_index_t;

/* reference to an item either by its id (existing items) or structure (new items) */
typedef struct
{
	zbx_uint64_t		itemid;
	zbx_lld_item_full_t	*item;
}
zbx_lld_item_ref_t;

int	lld_substitute_key_cb(const char *data, int level, int num, int quoted, char **param, va_list args);

int	zbx_substitute_lld_macros(char **data, const zbx_lld_entry_t *lld_obj, int flags, char *error,
		size_t max_error_len);
int	zbx_substitute_function_lld_param(const char *e, size_t len, unsigned char key_in_param,
		char **exp, size_t *exp_alloc, size_t *exp_offset, const zbx_lld_entry_t *lld_obj,
		int esc_flags, char *error, size_t max_error_len);
int	zbx_substitute_expression_lld_macros(char **data, zbx_uint64_t rules, const zbx_lld_entry_t *lld_obj,
		char **error);
int	zbx_substitute_macros_in_json_pairs(char **data, const zbx_lld_entry_t *lld_obj, char *error, int maxerrlen);

void	lld_sync_exported_macros(const zbx_vector_uint64_t *ruleids, const zbx_lld_entry_t *entry);
void	lld_rule_get_exported_macros(zbx_uint64_t ruleid, zbx_vector_lld_macro_t *macros);

void	lld_rule_get_prototype_macro_paths(zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		zbx_vector_uint64_t *protoids);
void	lld_rule_get_prototype_filters(zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		zbx_vector_uint64_t *protoids);
void	lld_rule_get_prototype_overrides(zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		zbx_vector_uint64_t *protoids);
void	lld_rule_fetch_override_data(zbx_vector_lld_override_data_ptr_t *overrides);
void	lld_override_dump(zbx_sync_rowset_t *rowset);
void	lld_rule_process_nested_rule(zbx_uint64_t itemid, const zbx_lld_row_t *lld_row);

void	lld_rule_macro_paths_make(zbx_vector_lld_item_full_ptr_t *items);
void	lld_rule_filters_make(zbx_vector_lld_item_full_ptr_t *items, char **info);
void	lld_rule_overrides_make(zbx_vector_lld_item_full_ptr_t *items, char **info);

int	lld_rule_filters_save(zbx_uint64_t hostid, zbx_vector_lld_item_full_ptr_t *items, int *host_locked);
int	lld_items_overrides_save(zbx_uint64_t hostid, zbx_vector_lld_item_full_ptr_t *items, int *host_locked);
int	lld_rule_macro_paths_save(zbx_uint64_t hostid, zbx_vector_lld_item_full_ptr_t *items, int *host_locked);

int	lld_text_has_lld_macro(const char *text);

#endif
