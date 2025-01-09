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

#include "audit.h"
#include "audit/zbxaudit.h"

#include "zbxalgo.h"
#include "zbxjson.h"
#include "zbxcacheconfig.h"
#include "zbxnum.h"
#include "zbxdb.h"
#include "zbxdbschema.h"
#include "zbxstr.h"
#include "zbx_scripts_constants.h"

#define AUDIT_USERID		__UINT64_C(0)
#define AUDIT_USERID_SQL	"null"
#define AUDIT_USERNAME		"System"
#define AUDIT_IP		""

static zbx_hashset_t	zbx_audit;

static int		auditlog_mode;

int	zbx_get_auditlog_mode(void)
{
	return auditlog_mode;
}

static int		auditlog_enabled;

int	zbx_get_auditlog_enabled(void)
{
	return auditlog_enabled;
}

zbx_hashset_t	*zbx_get_audit_hashset(void)
{
	return &zbx_audit;
}

zbx_audit_entry_t	*zbx_audit_entry_init(zbx_uint64_t id, const int id_table, const char *name, int audit_action,
		int resource_type)
{
	zbx_audit_entry_t	*audit_entry;

	audit_entry = (zbx_audit_entry_t*)zbx_malloc(NULL, sizeof(zbx_audit_entry_t));
	audit_entry->id = id;
	audit_entry->cuid = NULL;
	audit_entry->id_table = id_table;
	audit_entry->name = zbx_strdup(NULL, name);
	audit_entry->audit_action = audit_action;
	audit_entry->resource_type = resource_type;
	zbx_new_cuid(audit_entry->audit_cuid);
	zbx_json_init(&(audit_entry->details_json), ZBX_JSON_STAT_BUF_LEN);

	return audit_entry;
}

zbx_audit_entry_t	*zbx_audit_entry_init_cuid(const char *cuid, const int id_table, const char *name,
		int audit_action, int resource_type)
{
	zbx_audit_entry_t	*audit_entry;

	audit_entry = (zbx_audit_entry_t*)zbx_malloc(NULL, sizeof(zbx_audit_entry_t));
	audit_entry->id = 0;
	audit_entry->cuid = zbx_strdup(NULL, cuid);
	audit_entry->id_table = id_table;
	audit_entry->name = zbx_strdup(NULL, name);
	audit_entry->audit_action = audit_action;
	audit_entry->resource_type = resource_type;
	zbx_new_cuid(audit_entry->audit_cuid);
	zbx_json_init(&(audit_entry->details_json), ZBX_JSON_STAT_BUF_LEN);

	return audit_entry;
}

static void	append_str_json(struct zbx_json *json, const char *audit_op, const char *key, const char *val)
{
	zbx_json_addarray(json, key);
	zbx_json_addstring(json, NULL, audit_op, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, NULL, val, ZBX_JSON_TYPE_STRING);
	zbx_json_close(json);
}

static void	append_uint64_json(struct zbx_json *json, const char *audit_op, const char *key, const uint64_t val)
{
	zbx_json_addarray(json, key);
	zbx_json_addstring(json, NULL, audit_op, ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(json, NULL, val);
	zbx_json_close(json);
}

static void	append_int_json(struct zbx_json *json, const char *audit_op, const char *key, int val)
{
	zbx_json_addarray(json, key);
	zbx_json_addstring(json, NULL, audit_op, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(json, NULL, val);
	zbx_json_close(json);
}

static void	append_double_json(struct zbx_json *json, const char *audit_op, const char *key, double val)
{
	zbx_json_addarray(json, key);
	zbx_json_addstring(json, NULL, audit_op, ZBX_JSON_TYPE_STRING);
	zbx_json_addfloat(json, NULL, val);
	zbx_json_close(json);
}

static void	append_json_no_value(struct zbx_json *json, const char *audit_op, const char *key)
{
	zbx_json_addarray(json, key);
	zbx_json_addstring(json, NULL, audit_op, ZBX_JSON_TYPE_STRING);
	zbx_json_close(json);
}

static void	update_str_json(struct zbx_json *json, const char *key, const char *val_old, const char *val_new)
{
	zbx_json_addarray(json, key);
	zbx_json_addstring(json, NULL, "update", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, NULL, val_new, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, NULL, val_old, ZBX_JSON_TYPE_STRING);
	zbx_json_close(json);
}

static void	update_uint64_json(struct zbx_json *json, const char *key, uint64_t val_old, uint64_t val_new)
{
	zbx_json_addarray(json, key);
	zbx_json_addstring(json, NULL, "update", ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(json, NULL, val_new);
	zbx_json_adduint64(json, NULL, val_old);
	zbx_json_close(json);
}

static void	update_int_json(struct zbx_json *json, const char *key, int val_old, int val_new)
{
	zbx_json_addarray(json, key);
	zbx_json_addstring(json, NULL, "update", ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(json, NULL, val_new);
	zbx_json_addint64(json, NULL, val_old);
	zbx_json_close(json);
}

static void	update_double_json(struct zbx_json *json, const char *key, double val_old, double val_new)
{
	zbx_json_addarray(json, key);
	zbx_json_addstring(json, NULL, "update", ZBX_JSON_TYPE_STRING);
	zbx_json_addfloat(json, NULL, val_new);
	zbx_json_addfloat(json, NULL, val_old);
	zbx_json_close(json);
}

static void	delete_json(struct zbx_json *json, const char *audit_op, const char *key)
{
	zbx_json_addarray(json, key);
	zbx_json_addstring(json, NULL, audit_op, ZBX_JSON_TYPE_STRING);
	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: record global script execution results into audit log             *
 *                                                                            *
 * Comments: 'hostid' should be always > 0. 'eventid' is > 0 in case of       *
 *           "manual script on event"                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_auditlog_global_script(unsigned char script_type, unsigned char script_execute_on,
		const char *script_command_orig, zbx_uint64_t hostid, const char *hostname, zbx_uint64_t eventid,
		zbx_uint64_t proxyid, zbx_uint64_t userid, const char *username, const char *clientip,
		const char *output, const char *error)
{
	int		ret = SUCCEED;
	char		auditid_cuid[CUID_LEN], hostid_s[MAX_ID_LEN + 1], eventid_s[MAX_ID_LEN + 1],
			proxyid_s[MAX_ID_LEN + 1];
	struct zbx_json	details_json;
	zbx_config_t	cfg;
	zbx_db_insert_t	db_insert;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_AUDITLOG_ENABLED);

	if (ZBX_AUDITLOG_ENABLED != cfg.auditlog_enabled)
		goto out;

	zbx_new_cuid(auditid_cuid);

	zbx_json_init(&details_json, ZBX_JSON_STAT_BUF_LEN);

	if (ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT == script_type)
	{
		char	execute_on_s[MAX_ID_LEN + 1];

		zbx_snprintf(execute_on_s, sizeof(execute_on_s), "%hhu", script_execute_on);

		append_str_json(&details_json, AUDIT_DETAILS_ACTION_ADD, "script.execute_on", execute_on_s);
	}

	if (0 != eventid)
	{
		zbx_snprintf(eventid_s, sizeof(eventid_s), ZBX_FS_UI64, eventid);
		append_str_json(&details_json, AUDIT_DETAILS_ACTION_ADD, "script.eventid", eventid_s);
	}

	zbx_snprintf(hostid_s, sizeof(hostid_s), ZBX_FS_UI64, hostid);
	append_str_json(&details_json, AUDIT_DETAILS_ACTION_ADD, "script.hostid", hostid_s);

	if (0 != proxyid)
	{
		zbx_snprintf(proxyid_s, sizeof(proxyid_s), ZBX_FS_UI64, proxyid);
		append_str_json(&details_json, AUDIT_DETAILS_ACTION_ADD, "script.proxyid", proxyid_s);
	}

	if (ZBX_SCRIPT_TYPE_WEBHOOK != script_type)
		append_str_json(&details_json, AUDIT_DETAILS_ACTION_ADD, "script.command", script_command_orig);

	if (NULL != output)
		append_str_json(&details_json, AUDIT_DETAILS_ACTION_ADD, "script.output", output);

	if (NULL != error)
		append_str_json(&details_json, AUDIT_DETAILS_ACTION_ADD, "script.error", error);

	zbx_db_insert_prepare(&db_insert, "auditlog", "auditid", "userid", "username", "clock", "action", "ip",
			"resourceid", "resourcename", "resourcetype", "recordsetid", "details", NULL);

	zbx_db_insert_add_values(&db_insert, auditid_cuid, userid, username, (int)time(NULL), ZBX_AUDIT_ACTION_EXECUTE,
			clientip, hostid, hostname, ZBX_AUDIT_RESOURCE_SCRIPT, auditid_cuid, details_json.buffer);

	ret = zbx_db_insert_execute(&db_insert);

	zbx_db_insert_clean(&db_insert);
	zbx_json_free(&details_json);
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static unsigned	zbx_audit_hash_func(const void *data)
{
	zbx_hash_t	hash;
	const zbx_audit_entry_t	* const *audit_entry = (const zbx_audit_entry_t * const *)data;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&(*audit_entry)->id);

	if (NULL != (*audit_entry)->cuid)
		hash = ZBX_DEFAULT_STRING_HASH_ALGO((*audit_entry)->cuid, strlen((*audit_entry)->cuid), hash);

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&((*audit_entry)->id_table), sizeof((*audit_entry)->id_table), hash);
}

static int	zbx_audit_compare_func(const void *d1, const void *d2)
{
	const zbx_audit_entry_t	* const *audit_entry_1 = (const zbx_audit_entry_t * const *)d1;
	const zbx_audit_entry_t	* const *audit_entry_2 = (const zbx_audit_entry_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL((*audit_entry_1)->id, (*audit_entry_2)->id);
	ZBX_RETURN_IF_NOT_EQUAL((*audit_entry_1)->id_table, (*audit_entry_2)->id_table);

	return zbx_strcmp_null((*audit_entry_1)->cuid, (*audit_entry_2)->cuid);
}

void	zbx_audit_clean(int audit_context_mode)
{
	zbx_hashset_iter_t	iter;
	zbx_audit_entry_t	**audit_entry;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_hashset_iter_reset(&zbx_audit, &iter);

	while (NULL != (audit_entry = (zbx_audit_entry_t **)zbx_hashset_iter_next(&iter)))
	{
		zbx_json_free(&((*audit_entry)->details_json));
		zbx_free((*audit_entry)->name);
		zbx_free((*audit_entry)->cuid);
		zbx_free(*audit_entry);
	}

	zbx_hashset_destroy(&zbx_audit);
}

void	zbx_audit_init(int auditlog_enabled_set, int auditlog_mode_set, int audit_context_mode)
{
	auditlog_enabled = auditlog_enabled_set;
	auditlog_mode = auditlog_mode_set;
	RETURN_IF_AUDIT_OFF(audit_context_mode);
#define AUDIT_HASHSET_DEF_SIZE	100
	zbx_hashset_create(&zbx_audit, AUDIT_HASHSET_DEF_SIZE, zbx_audit_hash_func, zbx_audit_compare_func);
#undef AUDIT_HASHSET_DEF_SIZE
}

void	zbx_audit_prepare(int audit_context_mode)
{
	zbx_config_t	cfg;

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_AUDITLOG_ENABLED | ZBX_CONFIG_FLAGS_AUDITLOG_MODE);
	zbx_audit_init(cfg.auditlog_enabled, cfg.auditlog_mode, audit_context_mode);
}

static int	zbx_audit_validate_entry(const zbx_audit_entry_t *entry)
{
	switch (entry->audit_action)
	{
		case ZBX_AUDIT_ACTION_ADD:
		case ZBX_AUDIT_ACTION_UPDATE:
			if (0 == strcmp(entry->details_json.buffer, "{}"))
				return FAIL;
			return SUCCEED;
		default:
			return SUCCEED;
	}
}

void	zbx_audit_flush(int audit_context_mode)
{
	char			recsetid_cuid[CUID_LEN];
	zbx_hashset_iter_t	iter;
	zbx_audit_entry_t	**audit_entry;
	zbx_db_insert_t		db_insert_audit;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_new_cuid(recsetid_cuid);
	zbx_hashset_iter_reset(&zbx_audit, &iter);

	zbx_db_insert_prepare(&db_insert_audit, "auditlog", "auditid", "userid", "username", "clock", "action", "ip",
			"resourceid", "resourcename", "resourcetype", "recordsetid", "details", (char *)NULL);

	while (NULL != (audit_entry = (zbx_audit_entry_t **)zbx_hashset_iter_next(&iter)))
	{
		if (SUCCEED == zbx_audit_validate_entry(*audit_entry))
		{
			zbx_db_insert_add_values(&db_insert_audit, (*audit_entry)->audit_cuid, AUDIT_USERID,
					AUDIT_USERNAME, (int)time(NULL), (*audit_entry)->audit_action, AUDIT_IP,
					(*audit_entry)->id, (*audit_entry)->name, (*audit_entry)->resource_type,
					recsetid_cuid, 0 == strcmp((*audit_entry)->details_json.buffer, "{}") ? "" :
					(*audit_entry)->details_json.buffer);
		}
	}

	zbx_db_insert_execute(&db_insert_audit);
	zbx_db_insert_clean(&db_insert_audit);

	zbx_audit_clean(audit_context_mode);
}

int	zbx_audit_flush_dbconn(zbx_dbconn_t *db, int audit_context_mode)
{
	char			recsetid_cuid[CUID_LEN];
	int			ret = ZBX_DB_OK;
	zbx_hashset_iter_t	iter;
	zbx_audit_entry_t	**audit_entry;

	if (ZBX_AUDITLOG_ENABLED != zbx_get_auditlog_enabled())
		return ZBX_DB_OK;

	zbx_new_cuid(recsetid_cuid);
	zbx_hashset_iter_reset(&zbx_audit, &iter);

	while (NULL != (audit_entry = (zbx_audit_entry_t **)zbx_hashset_iter_next(&iter)))
	{
		char		id[ZBX_MAX_UINT64_LEN + 1], *pvalue, *name_esc, *details_esc;
		const char	*pfield;

		if (SUCCEED != zbx_audit_validate_entry(*audit_entry))
			continue;

		if (0 != (*audit_entry)->id)
		{
			zbx_snprintf(id, sizeof(id), ZBX_FS_UI64, (*audit_entry)->id);
			pfield = "resourceid";
			pvalue = id;
		}
		else
		{
			pfield = "resource_cuid";
			pvalue = (*audit_entry)->cuid;
		}

		name_esc = zbx_db_dyn_escape_string((*audit_entry)->name);
		details_esc = zbx_db_dyn_escape_string((*audit_entry)->details_json.buffer);

		ret = zbx_dbconn_execute(db, "insert into auditlog (auditid,userid,username,"
				"clock,action,ip,%s,resourcename,resourcetype,recordsetid,details) values"
				" ('%s'," AUDIT_USERID_SQL ",'%s','%d','%d','%s','%s','%s',%d,'%s','%s')",
				pfield, (*audit_entry)->audit_cuid, AUDIT_USERNAME, (int)time(NULL),
				(*audit_entry)->audit_action, AUDIT_IP, pvalue, name_esc, (*audit_entry)->resource_type,
				recsetid_cuid, 0 == strcmp(details_esc, "{}") ? "" : details_esc);

		zbx_free(details_esc);
		zbx_free(name_esc);

		if (ZBX_DB_OK > ret)
			break;
	}

	zbx_audit_clean(audit_context_mode);

	return ret;
}

/*********************************************************************************************
 *                                                                                           *
 * Purpose: Checks if supplied 'value' and 'id' are equal with DB table field default value. *
 *                                                                                           *
 *********************************************************************************************/
int	audit_field_value_matches_db_default(const char *table_name, const char *field_name, const char *value,
		uint64_t id)
{
	static ZBX_THREAD_LOCAL char			cached_table_name[ZBX_TABLENAME_LEN_MAX];
	static ZBX_THREAD_LOCAL const zbx_db_table_t	*table = NULL;
	const zbx_db_field_t				*field;

	if (NULL == table_name || NULL == field_name)
		return FAIL;

	/* Often 'table_name' stays the same and only 'field_name' changes in successive calls of this function. */
	/* Here a simple caching of DBget_table() result is implemented. We rely on static array 'cached_table_name' */
	/* initialization with zero bytes, i.e. with empty string. */

	if ('\0' == cached_table_name[0] || 0 != strcmp(cached_table_name, table_name))
	{
		if (NULL == (table = zbx_db_get_table(table_name)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "%s(): cannot find table '%s'", __func__, table_name);
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
		}

		zbx_strlcpy(cached_table_name, table_name, sizeof(cached_table_name));
	}

	if (NULL == (field = zbx_db_get_field(table, field_name)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s(): table '%s', cannot find field '%s'", __func__, table_name,
				field_name);
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	if (NULL != field->default_value)
	{
		if (NULL != value && (0 == strcmp(value, field->default_value) ||
				(ZBX_TYPE_FLOAT == field->type && SUCCEED == zbx_double_compare(atof(value),
				atof(field->default_value)))))
		{
			return SUCCEED;
		}
	}
	else if (NULL == value || (ZBX_TYPE_ID == field->type && 0 == id))
		return SUCCEED;

	return FAIL;
}

void	zbx_audit_update_json_append_string(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, const char *value, const char *table, const char *field)
{
	zbx_audit_entry_t	local_audit_entry, **found_audit_entry;
	zbx_audit_entry_t	*local_audit_entry_x = &local_audit_entry;

	if (SUCCEED == audit_field_value_matches_db_default(table, field, value, 0))
		return;

	local_audit_entry.id = id;
	local_audit_entry.cuid = NULL;
	local_audit_entry.id_table = id_table;
	found_audit_entry = (zbx_audit_entry_t**)zbx_hashset_search(&zbx_audit, &(local_audit_entry_x));

	if (NULL == found_audit_entry)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	append_str_json(&((*found_audit_entry)->details_json), audit_op, key, value);
}

int	zbx_audit_item_has_password(int item_type)
{
	if (ITEM_TYPE_JMX == item_type || ITEM_TYPE_SIMPLE == item_type || ITEM_TYPE_SSH == item_type ||
			ITEM_TYPE_TELNET == item_type || ITEM_TYPE_DB_MONITOR == item_type ||
			ITEM_TYPE_HTTPAGENT == item_type)
	{
		return SUCCEED;
	}

	return FAIL;
}

int	zbx_audit_item_has_ssl_key_password(int item_type)
{
	if (ITEM_TYPE_HTTPAGENT == item_type)
	{
		return SUCCEED;
	}

	return FAIL;
}

void	zbx_audit_update_json_append_string_secret(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key)
{
	zbx_audit_entry_t	local_audit_entry, **found_audit_entry;
	zbx_audit_entry_t	*local_audit_entry_x = &local_audit_entry;

	/* audit_field_value_matches_db_default() is not missing here, this is intentional for security purposes */

	local_audit_entry.id = id;
	local_audit_entry.cuid = NULL;
	local_audit_entry.id_table = id_table;
	found_audit_entry = (zbx_audit_entry_t**)zbx_hashset_search(&zbx_audit, &(local_audit_entry_x));

	if (NULL == found_audit_entry)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	append_str_json(&((*found_audit_entry)->details_json), audit_op, key, ZBX_MACRO_SECRET_MASK);
}

void	zbx_audit_update_json_append_uint64(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, uint64_t value, const char *table, const char *field)
{
	char			buffer[MAX_ID_LEN];
	zbx_audit_entry_t	local_audit_entry, **found_audit_entry;
	zbx_audit_entry_t	*local_audit_entry_x = &local_audit_entry;

	zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, value);
	if (SUCCEED == audit_field_value_matches_db_default(table, field, buffer, value))
		return;

	local_audit_entry.id = id;
	local_audit_entry.cuid = NULL;
	local_audit_entry.id_table = id_table;
	found_audit_entry = (zbx_audit_entry_t**)zbx_hashset_search(&zbx_audit, &(local_audit_entry_x));

	if (NULL == found_audit_entry)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	append_uint64_json(&((*found_audit_entry)->details_json), audit_op, key, value);
}

#define PREPARE_UPDATE_JSON_APPEND_OP(...)					\
	zbx_audit_entry_t	local_audit_entry, **found_audit_entry;		\
	zbx_audit_entry_t	*local_audit_entry_x = &local_audit_entry;	\
										\
	local_audit_entry.id = id;						\
	local_audit_entry.cuid = NULL;						\
	local_audit_entry.id_table = id_table;					\
	found_audit_entry = (zbx_audit_entry_t**)zbx_hashset_search(&zbx_audit,	\
			&(local_audit_entry_x));				\
	if (NULL == found_audit_entry)						\
	{									\
		zbx_hashset_iter_t	iter;					\
		zbx_audit_entry_t	**log_audit_entry;			\
										\
		zbx_hashset_iter_reset(&zbx_audit, &iter);			\
		zabbix_log(LOG_LEVEL_INFORMATION, "Failed to find audit entry: " ZBX_FS_UI64 ", id_table: %d, "	\
				"key: %s", id, id_table, key);							\
		zabbix_log(LOG_LEVEL_INFORMATION, "Audit entries: ");						\
														\
		while (NULL != (log_audit_entry = (zbx_audit_entry_t **)zbx_hashset_iter_next(&iter)))		\
		{												\
														\
			zabbix_log(LOG_LEVEL_INFORMATION, "Audit entry existing id: " ZBX_FS_UI64  ", "		\
					"id_table: %d",	(*log_audit_entry)->id, (*log_audit_entry)->id_table );	\
		}												\
														\
		THIS_SHOULD_NEVER_HAPPEN;									\
		exit(EXIT_FAILURE);										\
	}													\

void	zbx_audit_update_json_append_no_value(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key)
{
	PREPARE_UPDATE_JSON_APPEND_OP()
	append_json_no_value(&((*found_audit_entry)->details_json), audit_op, key);
}

void	zbx_audit_update_json_append_int(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, int value, const char *table, const char *field)
{
	char	buffer[MAX_ID_LEN];

	zbx_snprintf(buffer, sizeof(buffer), "%d", value);

	if (SUCCEED == audit_field_value_matches_db_default(table, field, buffer, 0))
	{
		return;
	}
	else
	{
		PREPARE_UPDATE_JSON_APPEND_OP()
		append_int_json(&((*found_audit_entry)->details_json), audit_op, key, value);
	}
}

void	zbx_audit_update_json_append_double(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, double value, const char *table, const char *field)
{
	char	buffer[MAX_ID_LEN];

	zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_DBL, value);

	if (SUCCEED == audit_field_value_matches_db_default(table, field, buffer, 0))
	{
		return;
	}
	else
	{
		PREPARE_UPDATE_JSON_APPEND_OP()
		append_double_json(&((*found_audit_entry)->details_json), audit_op, key, value);
	}
}

void	zbx_audit_update_json_update_string(const zbx_uint64_t id, const int id_table, const char *key,
		const char *value_old, const char *value_new)
{
	PREPARE_UPDATE_JSON_APPEND_OP()
	update_str_json(&((*found_audit_entry)->details_json), key, value_old, value_new);
}

void	zbx_audit_update_json_update_uint64(const zbx_uint64_t id, const int id_table, const char *key,
		uint64_t value_old, uint64_t value_new)
{
	PREPARE_UPDATE_JSON_APPEND_OP()
	update_uint64_json(&((*found_audit_entry)->details_json), key, value_old, value_new);
}

void	zbx_audit_update_json_update_int(const zbx_uint64_t id, const int id_table, const char *key, int value_old,
		int value_new)
{
	PREPARE_UPDATE_JSON_APPEND_OP()
	update_int_json(&((*found_audit_entry)->details_json), key, value_old, value_new);
}

void	zbx_audit_update_json_update_double(const zbx_uint64_t id, const int id_table, const char *key,
		double value_old, double value_new)
{
	PREPARE_UPDATE_JSON_APPEND_OP()
	update_double_json(&((*found_audit_entry)->details_json), key, value_old, value_new);
}

void	zbx_audit_update_json_delete(const zbx_uint64_t id, const int id_table, const char *audit_op, const char *key)
{
	PREPARE_UPDATE_JSON_APPEND_OP()
	delete_json(&((*found_audit_entry)->details_json), audit_op, key);
}

zbx_audit_entry_t	*zbx_audit_get_entry(zbx_uint64_t id, const char *cuid, int id_table)
{
	zbx_audit_entry_t	local_audit_entry, *plocal_audit_entry = &local_audit_entry, **paudit_entry;

	local_audit_entry.id = id;
	local_audit_entry.cuid = (char *)cuid;
	local_audit_entry.id_table = id_table;

	if (NULL == (paudit_entry = (zbx_audit_entry_t**)zbx_hashset_search(&zbx_audit, &plocal_audit_entry)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	return *paudit_entry;
}

void	zbx_audit_entry_append_int(zbx_audit_entry_t *entry, int audit_op, const char *key, ...)
{
	va_list		args;
	int		value1, value2;

	va_start(args, key);
	value1 = va_arg(args, int);

	switch (audit_op)
	{
		case ZBX_AUDIT_ACTION_ADD:
			append_int_json(&entry->details_json, AUDIT_DETAILS_ACTION_ADD, key, value1);
			break;
		case ZBX_AUDIT_ACTION_UPDATE:
			value2 = va_arg(args, int);
			update_int_json(&entry->details_json, key, value1, value2);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			break;
	}

	va_end(args);
}

void	zbx_audit_entry_append_string(zbx_audit_entry_t *entry, int audit_op, const char *key, ...)
{
	va_list		args;
	const char	*value1, *value2;

	va_start(args, key);
	value1 = va_arg(args, const char *);

	switch (audit_op)
	{
		case ZBX_AUDIT_ACTION_ADD:
			append_str_json(&entry->details_json, AUDIT_DETAILS_ACTION_ADD, key, value1);
			break;
		case ZBX_AUDIT_ACTION_UPDATE:
			value2 = va_arg(args, const char *);
			update_str_json(&entry->details_json, key, value1, value2);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			break;
	}

	va_end(args);
}

/******************************************************************************
 *                                                                            *
 * Purpose: record history push request results into audit log                *
 *                                                                            *
 ******************************************************************************/
int	zbx_auditlog_history_push(zbx_uint64_t userid, const char *username, const char *clientip, int processed_num,
		int failed_num, double time_spent)
{
	int		ret = SUCCEED;
	char		auditid_cuid[CUID_LEN];
	struct zbx_json	details_json;
	zbx_config_t	cfg;
	zbx_db_insert_t	db_insert;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_AUDITLOG_ENABLED);

	if (ZBX_AUDITLOG_ENABLED != cfg.auditlog_enabled)
		goto out;

	zbx_new_cuid(auditid_cuid);

	zbx_json_init(&details_json, ZBX_JSON_STAT_BUF_LEN);

	append_int_json(&details_json, AUDIT_DETAILS_ACTION_ADD, "history.processed", processed_num);
	append_int_json(&details_json, AUDIT_DETAILS_ACTION_ADD, "history.failed", failed_num);
	append_double_json(&details_json, AUDIT_DETAILS_ACTION_ADD, "history.time", time_spent);

	zbx_db_insert_prepare(&db_insert, "auditlog", "auditid", "userid", "username", "clock", "action", "ip",
			"resourcetype", "recordsetid", "details", NULL);

	zbx_db_begin();

	zbx_db_insert_add_values(&db_insert,  auditid_cuid, userid, username, (int)time(NULL), ZBX_AUDIT_ACTION_PUSH,
			clientip, ZBX_AUDIT_RESOURCE_HISTORY, auditid_cuid,
			details_json.buffer);

	ret = zbx_db_insert_execute(&db_insert);

	zbx_db_commit();

	zbx_db_insert_clean(&db_insert);
	zbx_json_free(&details_json);
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
