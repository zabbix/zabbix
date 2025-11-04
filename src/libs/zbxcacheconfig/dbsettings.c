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

#include "zbxcacheconfig.h"

#include "dbconfig.h"
#include "dbsync.h"
#include "zbxdb.h"
#include "zbxdbschema.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxalgo.h"
#include "zbxstr.h"

#define UPDATE_REVISION(revision, name, format, target, source)							\
	do													\
	{													\
		zabbix_log(LOG_LEVEL_TRACE, "setting %s changed: " format " -> " format, name, target, source); \
		get_dc_config()->revision.settings_table = revision;						\
	}													\
	while(0)

typedef struct
{
	zbx_db_value_t	value;
	int		found;
}
zbx_setting_value_t;

/* ZBX_PROXY flag here means that this setting should be sent to proxy */
/* ZBX_SERVER flag here means that this setting is being used by cache */
static const zbx_setting_entry_t	settings_description_table[] = {
	{"alert_usrgrpid",		ZBX_SETTING_TYPE_USRGRPID, 	ZBX_SERVER,		NULL},
	{"auditlog_enabled",		ZBX_SETTING_TYPE_INT, 		ZBX_SERVER,		"1"},
	{"auditlog_mode",		ZBX_SETTING_TYPE_INT, 		ZBX_SERVER,		"1"},
	{"authentication_type",		ZBX_SETTING_TYPE_INT, 		0,			"0"},
	{"autoreg_tls_accept",		ZBX_SETTING_TYPE_INT, 		ZBX_SERVER | ZBX_PROXY,	"1"},
	{"blink_period",		ZBX_SETTING_TYPE_STR, 		0,			"2m"},
	{"compress_older",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"7d"},
	{"compression_status",		ZBX_SETTING_TYPE_INT, 		ZBX_SERVER,		"0"},
	{"connect_timeout",		ZBX_SETTING_TYPE_STR, 		0,			"3s"},
	{"custom_color",		ZBX_SETTING_TYPE_INT, 		0,			"0"},
	{"db_extension",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		""},
	/* dbversion_status is used only directly */
	{"dbversion_status",		ZBX_SETTING_TYPE_STR, 		0,			""},
	{"default_inventory_mode",	ZBX_SETTING_TYPE_INT, 		ZBX_SERVER,		"-1"},
	{"default_lang",		ZBX_SETTING_TYPE_STR, 		0,			"en_US"},
	{"default_theme",		ZBX_SETTING_TYPE_STR, 		0,			"blue-theme"},
	{"default_timezone",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"system"},
	{"disabled_usrgrpid",		ZBX_SETTING_TYPE_USRGRPID, 	0,			NULL},
	{"discovery_groupid",		ZBX_SETTING_TYPE_HOSTGROUPID, 	ZBX_SERVER,		NULL},
	{"geomaps_attribution",		ZBX_SETTING_TYPE_STR, 		0,			""},
	{"geomaps_max_zoom",		ZBX_SETTING_TYPE_INT, 		0,			"0"},
	{"geomaps_tile_provider",	ZBX_SETTING_TYPE_STR, 		0,			""},
	{"geomaps_tile_url",		ZBX_SETTING_TYPE_STR, 		0,			""},
	/* ha_failover_delay is used only directly */
	{"ha_failover_delay",		ZBX_SETTING_TYPE_STR, 		0,			"1m"},
	{"history_period",		ZBX_SETTING_TYPE_STR, 		0,			"24h"},
	{"hk_audit_mode",		ZBX_SETTING_TYPE_INT, 		ZBX_SERVER,		"1"},
	{"hk_audit",			ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"31d"},
	{"hk_events_autoreg",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"1d"},
	{"hk_events_discovery",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"1d"},
	{"hk_events_internal",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"1d"},
	{"hk_events_mode",		ZBX_SETTING_TYPE_INT, 		ZBX_SERVER,		"1"},
	{"hk_events_service",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"1d"},
	{"hk_events_trigger",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"365d"},
	{"hk_history_global",		ZBX_SETTING_TYPE_INT, 		ZBX_SERVER | ZBX_PROXY,	"0"},
	{"hk_history_mode",		ZBX_SETTING_TYPE_INT, 		ZBX_SERVER,		"1"},
	{"hk_history",			ZBX_SETTING_TYPE_STR, 		ZBX_SERVER | ZBX_PROXY,	"31d"},
	{"hk_services_mode",		ZBX_SETTING_TYPE_INT, 		ZBX_SERVER,		"1"},
	{"hk_services",			ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"365d"},
	{"hk_sessions_mode",		ZBX_SETTING_TYPE_INT, 		ZBX_SERVER,		"1"},
	{"hk_sessions",			ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"31d"},
	{"hk_trends_global",		ZBX_SETTING_TYPE_INT, 		ZBX_SERVER,		"0"},
	{"hk_trends_mode",		ZBX_SETTING_TYPE_INT, 		ZBX_SERVER,		"1"},
	{"hk_trends",			ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"365d"},
	{"http_auth_enabled",		ZBX_SETTING_TYPE_INT, 		0,			"0"},
	{"http_case_sensitive",		ZBX_SETTING_TYPE_INT, 		0,			"1"},
	{"http_login_form",		ZBX_SETTING_TYPE_INT, 		0,			"0"},
	{"http_strip_domains",		ZBX_SETTING_TYPE_STR, 		0,			""},
	{"iframe_sandboxing_enabled",	ZBX_SETTING_TYPE_INT, 		0,			"1"},
	{"iframe_sandboxing_exceptions", ZBX_SETTING_TYPE_STR, 		0,			""},
	{"instanceid",			ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		""},
	{"item_test_timeout",		ZBX_SETTING_TYPE_STR, 		0,			"60s"},
	{"jit_provision_interval",	ZBX_SETTING_TYPE_STR, 		0,			"1h"},
	{"ldap_auth_enabled",		ZBX_SETTING_TYPE_INT, 		0,			"0"},
	{"ldap_case_sensitive",		ZBX_SETTING_TYPE_INT, 		0,			"1"},
	{"ldap_jit_status",		ZBX_SETTING_TYPE_INT, 		0,			"0"},
	{"ldap_userdirectoryid",	ZBX_SETTING_TYPE_USRDIRID, 	0,			NULL},
	{"login_attempts",		ZBX_SETTING_TYPE_INT, 		0,			"5"},
	{"login_block",			ZBX_SETTING_TYPE_STR, 		0,			"30s"},
	{"max_in_table",		ZBX_SETTING_TYPE_INT, 		0,			"50"},
	{"max_overview_table_size",	ZBX_SETTING_TYPE_INT, 		0,			"50"},
	{"max_period",			ZBX_SETTING_TYPE_STR, 		0,			"2y"},
	{"media_type_test_timeout",	ZBX_SETTING_TYPE_STR, 		0,			"65s"},
	{"mfa_status",			ZBX_SETTING_TYPE_INT, 		0,			"0"},
	{"mfaid",			ZBX_SETTING_TYPE_MFAID, 	0,			NULL},
	{"ok_ack_color",		ZBX_SETTING_TYPE_STR, 		0,			"009900"},
	{"ok_ack_style",		ZBX_SETTING_TYPE_INT, 		0,			"1"},
	{"ok_period",			ZBX_SETTING_TYPE_STR, 		0,			"5m"},
	{"ok_unack_color",		ZBX_SETTING_TYPE_STR, 		0,			"009900"},
	{"ok_unack_style",		ZBX_SETTING_TYPE_INT, 		0,			"1"},
	{"passwd_check_rules",		ZBX_SETTING_TYPE_INT, 		0,			"8"},
	{"passwd_min_length",		ZBX_SETTING_TYPE_INT, 		0,			"8"},
	{"period_default",		ZBX_SETTING_TYPE_STR, 		0,			"1h"},
	{"problem_ack_color",		ZBX_SETTING_TYPE_STR, 		0,			"CC0000"},
	{"problem_ack_style",		ZBX_SETTING_TYPE_INT, 		0,			"1"},
	{"problem_unack_color",		ZBX_SETTING_TYPE_STR, 		0,			"CC0000"},
	{"problem_unack_style",		ZBX_SETTING_TYPE_INT, 		0,			"1"},
	{"report_test_timeout",		ZBX_SETTING_TYPE_STR, 		0,			"60s"},
	{"saml_auth_enabled",		ZBX_SETTING_TYPE_INT, 		0,			"0"},
	{"saml_case_sensitive",		ZBX_SETTING_TYPE_INT, 		0,			"0"},
	{"saml_jit_status",		ZBX_SETTING_TYPE_INT, 		0,			"0"},
	{"script_timeout",		ZBX_SETTING_TYPE_STR, 		0,			"60s"},
	{"search_limit",		ZBX_SETTING_TYPE_INT, 		0,			"1000"},
	{"server_check_interval",	ZBX_SETTING_TYPE_INT, 		0,			"10"},
	{"server_status",		ZBX_SETTING_TYPE_STR, 		0,			""},
	{"session_key",			ZBX_SETTING_TYPE_STR, 		0,			""},
	{"severity_color_0",		ZBX_SETTING_TYPE_STR, 		0,			"97AAB3"},
	{"severity_color_1",		ZBX_SETTING_TYPE_STR, 		0,			"7499FF"},
	{"severity_color_2",		ZBX_SETTING_TYPE_STR, 		0,			"FFC859"},
	{"severity_color_3",		ZBX_SETTING_TYPE_STR, 		0,			"FFA059"},
	{"severity_color_4",		ZBX_SETTING_TYPE_STR, 		0,			"E97659"},
	{"severity_color_5",		ZBX_SETTING_TYPE_STR, 		0,			"E45959"},
	{"severity_name_0",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"Not classified"},
	{"severity_name_1",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"Information"},
	{"severity_name_2",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"Warning"},
	{"severity_name_3",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"Average"},
	{"severity_name_4",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"High"},
	{"severity_name_5",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER,		"Disaster"},
	{"show_technical_errors",	ZBX_SETTING_TYPE_INT, 		0,			"0"},
	{"snmptrap_logging",		ZBX_SETTING_TYPE_INT, 		ZBX_SERVER | ZBX_PROXY,	"1"},
	{"socket_timeout",		ZBX_SETTING_TYPE_STR, 		0,			"3s"},
	{"software_update_check_data",	ZBX_SETTING_TYPE_STR, 		0,			""},
	{"software_update_checkid",	ZBX_SETTING_TYPE_STR, 		0,			""},
	{"timeout_browser",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER | ZBX_PROXY,	"60s"},
	{"timeout_db_monitor",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER | ZBX_PROXY,	"3s"},
	{"timeout_external_check",	ZBX_SETTING_TYPE_STR, 		ZBX_SERVER | ZBX_PROXY,	"3s"},
	{"timeout_http_agent",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER | ZBX_PROXY,	"3s"},
	{"timeout_script",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER | ZBX_PROXY,	"3s"},
	{"timeout_simple_check",	ZBX_SETTING_TYPE_STR, 		ZBX_SERVER | ZBX_PROXY,	"3s"},
	{"timeout_snmp_agent",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER | ZBX_PROXY,	"3s"},
	{"timeout_ssh_agent",		ZBX_SETTING_TYPE_STR, 		ZBX_SERVER | ZBX_PROXY,	"3s"},
	{"timeout_telnet_agent",	ZBX_SETTING_TYPE_STR, 		ZBX_SERVER | ZBX_PROXY,	"3s"},
	{"timeout_zabbix_agent",	ZBX_SETTING_TYPE_STR, 		ZBX_SERVER | ZBX_PROXY,	"3s"},
	{"uri_valid_schemes",		ZBX_SETTING_TYPE_STR, 		0,	"http,https,ftp,file,mailto,tel,ssh"},
	{"url",				ZBX_SETTING_TYPE_STR, 		0,			""},
	{"validate_uri_schemes",	ZBX_SETTING_TYPE_INT, 		0,			"1"},
	{"vault_provider",		ZBX_SETTING_TYPE_INT, 		0,			"0"},
	{"work_period",			ZBX_SETTING_TYPE_STR, 		0,			"1-5,09:00-18:00"},
	{"x_frame_options",		ZBX_SETTING_TYPE_STR, 		0,			"SAMEORIGIN"},
	{"proxy_secrets_provider",	ZBX_SETTING_TYPE_INT,		ZBX_SERVER | ZBX_PROXY, "0"},
};

const zbx_setting_entry_t	*zbx_settings_desc_table_get(void)
{
	return settings_description_table;
}

size_t	zbx_settings_descr_table_size(void)
{
	return ARRSIZE(settings_description_table);
}

const zbx_setting_entry_t	*zbx_settings_descr_get(const char *name, int *index)
{
	for (size_t i = 0; i < ARRSIZE(settings_description_table); i++)
	{
		const zbx_setting_entry_t	*e = &settings_description_table[i];

		if (0 == strcmp(name, e->name))
		{
			if (NULL != index)
				*index = (int)i;
			return e;
		}
	}

	zabbix_log(LOG_LEVEL_WARNING, "setting '%s' is not found", name);

	return NULL;
}

int	zbx_dbsync_compare_settings(zbx_dbsync_t *sync)
{
	int		ret = FAIL;
	zbx_db_result_t	result;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	zbx_vector_str_t	names;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_str_create(&names);

	for (size_t i = 0; i < ARRSIZE(settings_description_table); i++)
	{
		if (0 != (ZBX_SERVER & settings_description_table[i].flags))
			zbx_vector_str_append(&names, (char *)settings_description_table[i].name);
	}

	zbx_dcsync_sql_start(sync);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select name, type, value_str, value_int, value_usrgrpid, "
			"value_hostgroupid, value_userdirectoryid, value_mfaid from settings where");

	zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "name", (const char * const*)names.values,
			names.values_num);

	if (NULL == (result = zbx_db_select(sql)))
	{
		goto ret;
	}

	dbsync_prepare(sync, 8, NULL);

	if (ZBX_DBSYNC_INIT == sync->mode)
	{
		sync->dbresult = result;
		ret = SUCCEED;
		goto out;
	}

	zbx_db_free_result(result);

	/* global configuration will be always synchronized directly with database */
	THIS_SHOULD_NEVER_HAPPEN;
out:
	zbx_dcsync_sql_end(sync);
ret:
	zbx_free(sql);
	zbx_vector_str_destroy(&names);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

static void	init_shared_cache(int *found)
{
	zbx_dc_config_t	*config = get_dc_config();

	if (NULL == config->config)
	{
		config->config = (zbx_dc_config_table_t *)dbconfig_shmem_malloc_func(NULL,
				sizeof(zbx_dc_config_table_t));
		memset(config->config, 0, sizeof(zbx_dc_config_table_t));
		*found = 0;
	}
}

static int	setup_entry_table(zbx_dbsync_t *sync, zbx_setting_value_t *values)
{
	zbx_uint64_t	rowid;
	char		**row;
	unsigned char	tag;
	int		found = 0;

	while (SUCCEED == zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		const char			*name = row[0];
		int				type = atoi(row[1]);
		const zbx_setting_entry_t	*entry = zbx_settings_descr_get(name, NULL);
		ssize_t				index;

		if (NULL == entry)
		{
			zabbix_log(LOG_LEVEL_CRIT, "Setting '%s' not found", name);
			continue;
		}

		if (type != entry->type)
		{
			zabbix_log(LOG_LEVEL_CRIT, "Setting '%s' types do not match: %d/%d", name, type, entry->type);
			continue;
		}

		index = entry - settings_description_table;

		switch (entry->type)
		{
			case ZBX_SETTING_TYPE_STR:
				values[index].value.str = (NULL != row[2]) ? zbx_strdup(NULL, row[2]) : NULL;
				break;
			case ZBX_SETTING_TYPE_INT:
				values[index].value.i32 = atoi(row[3]);
				break;
			case ZBX_SETTING_TYPE_USRGRPID:
				values[index].value.ui64 = 0;
				(void)zbx_is_uint64(row[4], &values[index].value.ui64);
				break;
			case ZBX_SETTING_TYPE_HOSTGROUPID:
				values[index].value.ui64 = 0;
				(void)zbx_is_uint64(row[5], &values[index].value.ui64);
				break;
			case ZBX_SETTING_TYPE_USRDIRID:
				values[index].value.ui64 = 0;
				(void)zbx_is_uint64(row[6], &values[index].value.ui64);
				break;
			case ZBX_SETTING_TYPE_MFAID:
				values[index].value.ui64 = 0;
				(void)zbx_is_uint64(row[7], &values[index].value.ui64);
				break;
			default:
				zabbix_log(LOG_LEVEL_CRIT, "Unknown setting type %d", entry->type);
				continue;
		}

		values[index].found = 1;
		found++;
	}

	return found;
}

static int	setting_get_str(const zbx_setting_value_t *values, const char *name, int defaults_log_level,
		const char **value)
{
	int				index;
	const zbx_setting_entry_t	*e;

	if (NULL != (e = zbx_settings_descr_get(name, &index)) && ZBX_SETTING_TYPE_STR == e->type)
	{
		if (1 == values[index].found)
		{
			*value = values[index].value.str;
		}
		else
		{
			*value = e->default_value;
			zabbix_log(defaults_log_level, "string setting '%s' uses default value: %s", name,
					ZBX_NULL2STR(*value));
		}

		return SUCCEED;
	}

	zabbix_log(LOG_LEVEL_WARNING, "string setting '%s' not found", name);

	return FAIL;
}

static int	setting_get_int(const zbx_setting_value_t *values, const char *name, int defaults_log_level,
		int *value)
{
	int				index;
	const zbx_setting_entry_t	*e;

	if (NULL != (e = zbx_settings_descr_get(name, &index)) && ZBX_SETTING_TYPE_INT == e->type)
	{
		int	ret = SUCCEED;

		if (1 == values[index].found)
			*value = values[index].value.i32;
		else if (SUCCEED == (ret = zbx_is_int(e->default_value, value)))
			zabbix_log(defaults_log_level, "integer setting '%s' uses default value: %d", name, *value);

		return ret;
	}

	zabbix_log(LOG_LEVEL_WARNING, "integer setting '%s' not found", name);

	return FAIL;
}

static int	setting_get_uint64(const zbx_setting_value_t *values, const char *name, int defaults_log_level,
		zbx_uint64_t *value)
{
	int				index;
	const zbx_setting_entry_t	*e;

	if (NULL != (e = zbx_settings_descr_get(name, &index)) && (ZBX_SETTING_TYPE_USRGRPID == e->type ||
			ZBX_SETTING_TYPE_HOSTGROUPID == e->type || ZBX_SETTING_TYPE_USRDIRID == e->type ||
			ZBX_SETTING_TYPE_MFAID == e->type))
	{
		int	ret = SUCCEED;

		if (1 == values[index].found)
		{
			*value = values[index].value.ui64;
		}
		else
		{
			if (NULL == e->default_value)
				*value = 0;
			else
				ret = zbx_is_uint64(e->default_value, value);

			if (SUCCEED == ret)
			{
				zabbix_log(defaults_log_level, "identifier setting '%s' uses default value: "
						ZBX_FS_UI64, name, *value);
			}
		}

		return ret;
	}

	zabbix_log(LOG_LEVEL_WARNING, "identifier setting '%s' not found", name);

	return FAIL;
}

static void	store_int_setting(const zbx_setting_value_t *values, const char *name, int defaults_log_level,
		int *target, zbx_uint64_t revision)
{
	int	value_int;

	if (SUCCEED == setting_get_int(values, name, defaults_log_level, &value_int))
	{
		if (*target != value_int)
		{
			UPDATE_REVISION(revision, name, "%d", *target, value_int);
			*target = value_int;
		}
	}
}

static void	store_uint64_setting(const zbx_setting_value_t *values, const char *name, int defaults_log_level,
		zbx_uint64_t *target, zbx_uint64_t revision)
{
	zbx_uint64_t	value_uint64;

	if (SUCCEED == setting_get_uint64(values, name, defaults_log_level, &value_uint64))
	{
		if (*target != value_uint64)
		{
			UPDATE_REVISION(revision, name, ZBX_FS_UI64, *target, value_uint64);
			*target = value_uint64;
		}
	}
}

static void	store_str_setting(const zbx_setting_value_t *values, const char *name, int found,
		int defaults_log_level, const char **target, zbx_uint64_t revision)
{
	const char	*value_str = NULL;

	if (SUCCEED == setting_get_str(values, name, defaults_log_level, &value_str))
	{
		if (NULL == *target || 0 != strcmp(*target, value_str))
		{
			UPDATE_REVISION(revision, name, "%s", *target, value_str);
			dc_strpool_replace(found, target, value_str);
		}
	}
}

static int	store_hk_setting(const zbx_setting_value_t *values, const char *name, int non_zero, int value_min,
		int defaults_log_level, int *value, zbx_uint64_t revision)
{
	const char	*value_str = NULL;
	int		value_int;

	if (SUCCEED != setting_get_str(values, name, defaults_log_level, &value_str))
		return FAIL;

	if (SUCCEED != zbx_is_time_suffix(value_str, &value_int, ZBX_LENGTH_UNLIMITED))
		return FAIL;

	if (0 != non_zero && 0 == value_int)
		return FAIL;

	if (0 != *value && (value_min > value_int || ZBX_HK_PERIOD_MAX < value_int))
		return FAIL;

	if (*value != value_int)
	{
		UPDATE_REVISION(revision, name, "%d", *value, value_int);
		*value = value_int;
	}

	return SUCCEED;
}

static void	store_settings(const zbx_setting_value_t *values, int found, zbx_uint64_t revision,
		int defaults_log_level)
{
	int		value_int = 0;
	zbx_uint64_t	value_uint64;
	const char	*value_str = NULL;
	zbx_dc_config_t	*config = get_dc_config();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == found && SUCCEED == setting_get_str(values, "instanceid", defaults_log_level, &value_str))
	{
		dc_strpool_replace(found, &config->config->instanceid, value_str);
	}

	store_uint64_setting(values, "alert_usrgrpid", defaults_log_level, &config->config->alert_usrgrpid, revision);
	store_int_setting(values, "auditlog_enabled", defaults_log_level, &config->config->auditlog_enabled, revision);
	store_int_setting(values, "auditlog_mode", defaults_log_level, &config->config->auditlog_mode, revision);
	store_int_setting(values, "autoreg_tls_accept", defaults_log_level, &config->config->autoreg_tls_accept,
			revision);

	if (SUCCEED == setting_get_str(values, "compress_older", defaults_log_level, &value_str))
	{
		if (SUCCEED != zbx_is_time_suffix(value_str, &value_int, ZBX_LENGTH_UNLIMITED))
		{
			zabbix_log(LOG_LEVEL_WARNING, "invalid history compression age: %s", value_str);
		}
	}

	if (config->config->db.history_compress_older != value_int)
	{
		UPDATE_REVISION(revision, "compress_older", "%d", config->config->db.history_compress_older, value_int);
		config->config->db.history_compress_older = value_int;
	}

	store_int_setting(values, "compression_status", defaults_log_level,
			&config->config->db.history_compression_status, revision);
	store_str_setting(values, "db_extension", found, defaults_log_level,
			(const char **)&config->config->db.extension, revision);
	store_int_setting(values, "default_inventory_mode", defaults_log_level, &config->config->default_inventory_mode,
			revision);
	store_str_setting(values, "default_timezone", found, defaults_log_level, &config->config->default_timezone,
			revision);

	if (SUCCEED != setting_get_uint64(values, "discovery_groupid", defaults_log_level, &value_uint64))
		value_uint64 = ZBX_DISCOVERY_GROUPID_UNDEFINED;

	if (config->config->discovery_groupid != value_uint64)
	{
		UPDATE_REVISION(revision, "discovery_groupid", ZBX_FS_UI64, config->config->discovery_groupid,
				value_uint64);
		config->config->discovery_groupid = value_uint64;
	}

	/* housekeeper settings for audit */

	if (SUCCEED != setting_get_int(values, "hk_audit_mode", defaults_log_level, &value_int))
		value_int = ZBX_HK_OPTION_DISABLED;

	if (ZBX_HK_OPTION_ENABLED == value_int &&
			SUCCEED != store_hk_setting(values, "hk_audit", 1, SEC_PER_DAY, defaults_log_level,
					&config->config->hk.audit, revision))
	{
		zabbix_log(LOG_LEVEL_WARNING, "audit data housekeeping will be disabled due to invalid settings");
		value_int = ZBX_HK_OPTION_DISABLED;
	}

#ifdef HAVE_POSTGRESQL
	if (ZBX_HK_MODE_DISABLED != value_int &&
			0 == zbx_strcmp_null(config->config->db.extension, ZBX_DB_EXTENSION_TIMESCALEDB))
	{
		if (ZBX_HK_MODE_PARTITION != value_int)
			value_int = ZBX_HK_MODE_PARTITION;
	}
#endif

	if (config->config->hk.audit_mode != value_int)
	{
		UPDATE_REVISION(revision, "hk_audit_mode", "%d", config->config->hk.audit_mode, value_int);
		config->config->hk.audit_mode = value_int;
	}

	/* housekeeper settings for events */

	if (SUCCEED != setting_get_int(values, "hk_events_mode", defaults_log_level, &value_int))
		value_int = ZBX_HK_OPTION_DISABLED;

	if (ZBX_HK_OPTION_ENABLED == value_int && (
			SUCCEED != store_hk_setting(values, "hk_events_trigger", 1, SEC_PER_DAY, defaults_log_level,
					&config->config->hk.events_trigger, revision) ||
			SUCCEED != store_hk_setting(values, "hk_events_internal", 1, SEC_PER_DAY, defaults_log_level,
					&config->config->hk.events_internal, revision) ||
			SUCCEED != store_hk_setting(values, "hk_events_discovery", 1, SEC_PER_DAY, defaults_log_level,
					&config->config->hk.events_discovery, revision) ||
			SUCCEED != store_hk_setting(values, "hk_events_autoreg", 1, SEC_PER_DAY, defaults_log_level,
					&config->config->hk.events_autoreg, revision) ||
			SUCCEED != store_hk_setting(values, "hk_events_service", 1, SEC_PER_DAY, defaults_log_level,
					&config->config->hk.events_service, revision)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "service, trigger, internal, network discovery and auto-registration data"
				" housekeeping will be disabled due to invalid settings");
		value_int = ZBX_HK_OPTION_DISABLED;
	}

	if (config->config->hk.events_mode != value_int)
	{
		UPDATE_REVISION(revision, "hk_events_mode", "%d", config->config->hk.events_mode, value_int);
		config->config->hk.events_mode = value_int;
	}

	/* housekeeper settings for history data */

	if (SUCCEED != setting_get_int(values, "hk_history_global", defaults_log_level, &value_int))
		value_int = ZBX_HK_OPTION_DISABLED;

	if (config->config->hk.history_global != value_int)
	{
		UPDATE_REVISION(revision, "hk_history_global", "%d", config->config->hk.history_global, value_int);
		config->config->hk.history_global = value_int;
	}

	if (SUCCEED != setting_get_int(values, "hk_history_mode", defaults_log_level, &value_int))
		value_int = ZBX_HK_OPTION_DISABLED;

	if (ZBX_HK_OPTION_ENABLED == config->config->hk.history_global &&
			SUCCEED != store_hk_setting(values, "hk_history", 0, ZBX_HK_HISTORY_MIN, defaults_log_level,
					&config->config->hk.history, revision))
	{
		zabbix_log(LOG_LEVEL_WARNING, "history data housekeeping will be disabled and all items will"
				" store their history due to invalid global override settings");

		value_int = ZBX_HK_MODE_DISABLED;

		if (1 != config->config->hk.history)
		{
			UPDATE_REVISION(revision, "hk_history", "%d", config->config->hk.history, 1);
			config->config->hk.history = 1;	/* just enough to make 0 == items[i].history condition fail */
		}
	}

#ifdef HAVE_POSTGRESQL
	if (ZBX_HK_MODE_DISABLED != value_int &&
			ZBX_HK_OPTION_ENABLED == config->config->hk.history_global &&
			0 == zbx_strcmp_null(config->config->db.extension, ZBX_DB_EXTENSION_TIMESCALEDB))
	{
		value_int = ZBX_HK_MODE_PARTITION;
	}
#endif

	if (config->config->hk.history_mode != value_int)
	{
		UPDATE_REVISION(revision, "hk_history_mode", "%d", config->config->hk.history_mode, value_int);
		config->config->hk.history_mode = value_int;
	}

	/* housekeeper settings for services */

	if (SUCCEED != setting_get_int(values, "hk_services_mode", defaults_log_level, &value_int))
		value_int = ZBX_HK_OPTION_DISABLED;

	if (ZBX_HK_OPTION_ENABLED == value_int &&
			SUCCEED != store_hk_setting(values, "hk_services", 1, SEC_PER_DAY, defaults_log_level,
					&config->config->hk.services, revision))
	{
		zabbix_log(LOG_LEVEL_WARNING, "IT services data housekeeping will be disabled due to invalid settings");
		value_int = ZBX_HK_OPTION_DISABLED;
	}

	if (config->config->hk.services_mode != value_int)
	{
		UPDATE_REVISION(revision, "hk_services_mode", "%d", config->config->hk.services_mode, value_int);
		config->config->hk.services_mode = value_int;
	}

	/* housekeeper settings for user sessions data */

	if (SUCCEED != setting_get_int(values, "hk_sessions_mode", defaults_log_level, &value_int))
		value_int = ZBX_HK_OPTION_DISABLED;

	if (ZBX_HK_OPTION_ENABLED == value_int &&
			SUCCEED != store_hk_setting(values, "hk_sessions", 1, SEC_PER_DAY, defaults_log_level,
					&config->config->hk.sessions, revision))
	{
		zabbix_log(LOG_LEVEL_WARNING, "user sessions data housekeeping will be disabled due to invalid"
				" settings");
		value_int = ZBX_HK_OPTION_DISABLED;
	}

	if (config->config->hk.sessions_mode != value_int)
	{
		UPDATE_REVISION(revision, "hk_sessions_mode", "%d", config->config->hk.sessions_mode, value_int);
		config->config->hk.sessions_mode = value_int;
	}

	/* housekeeper settings for trends */

	if (SUCCEED != setting_get_int(values, "hk_trends_global", defaults_log_level, &value_int))
		value_int = ZBX_HK_OPTION_DISABLED;

	if (config->config->hk.trends_global != value_int)
	{
		UPDATE_REVISION(revision, "hk_trends_global", "%d", config->config->hk.trends_global, value_int);
		config->config->hk.trends_global = value_int;
	}

	if (SUCCEED != setting_get_int(values, "hk_trends_mode", defaults_log_level, &value_int))
		value_int = ZBX_HK_OPTION_DISABLED;

	if (ZBX_HK_OPTION_ENABLED == value_int &&
			SUCCEED != store_hk_setting(values, "hk_trends", 0, ZBX_HK_TRENDS_MIN, defaults_log_level,
					&config->config->hk.trends, revision))
	{
		zabbix_log(LOG_LEVEL_WARNING, "trends data housekeeping will be disabled and all numeric items"
				" will store their history due to invalid global override settings");

		if (ZBX_HK_MODE_DISABLED != config->config->hk.trends_mode)
			value_int = ZBX_HK_MODE_DISABLED;

		if (1 != config->config->hk.trends)
		{
			UPDATE_REVISION(revision, "hk_trends", "%d", config->config->hk.trends, 1);
			config->config->hk.trends = 1;	/* just enough to make 0 == items[i].trends condition fail */
		}
	}

#ifdef HAVE_POSTGRESQL
	if (ZBX_HK_MODE_DISABLED != value_int && ZBX_HK_OPTION_ENABLED == config->config->hk.trends_global &&
			0 == zbx_strcmp_null(config->config->db.extension, ZBX_DB_EXTENSION_TIMESCALEDB))
	{
		value_int = ZBX_HK_MODE_PARTITION;
	}
#endif

	if (config->config->hk.trends_mode != value_int)
	{
		UPDATE_REVISION(revision, "hk_trends_mode", "%d", config->config->hk.trends_mode, value_int);
		config->config->hk.trends_mode = value_int;
	}

	store_str_setting(values, "severity_name_0", found, defaults_log_level, &config->config->severity_name[0],
			revision);
	store_str_setting(values, "severity_name_1", found, defaults_log_level, &config->config->severity_name[1],
			revision);
	store_str_setting(values, "severity_name_2", found, defaults_log_level, &config->config->severity_name[2],
			revision);
	store_str_setting(values, "severity_name_3", found, defaults_log_level, &config->config->severity_name[3],
			revision);
	store_str_setting(values, "severity_name_4", found, defaults_log_level, &config->config->severity_name[4],
			revision);
	store_str_setting(values, "severity_name_5", found, defaults_log_level, &config->config->severity_name[5],
			revision);

	store_int_setting(values, "snmptrap_logging", defaults_log_level, &config->config->snmptrap_logging, revision);

	store_str_setting(values, "timeout_browser", found, defaults_log_level, &config->config->item_timeouts.browser,
			revision);
	store_str_setting(values, "timeout_db_monitor", found, defaults_log_level, &config->config->item_timeouts.odbc,
			revision);
	store_str_setting(values, "timeout_external_check", found, defaults_log_level,
			&config->config->item_timeouts.external, revision);
	store_str_setting(values, "timeout_http_agent", found, defaults_log_level, &config->config->item_timeouts.http,
			revision);
	store_str_setting(values, "timeout_script", found, defaults_log_level, &config->config->item_timeouts.script,
			revision);
	store_str_setting(values, "timeout_simple_check", found, defaults_log_level,
			&config->config->item_timeouts.simple, revision);
	store_str_setting(values, "timeout_snmp_agent", found, defaults_log_level, &config->config->item_timeouts.snmp,
			revision);
	store_str_setting(values, "timeout_ssh_agent", found, defaults_log_level, &config->config->item_timeouts.ssh,
			revision);
	store_str_setting(values, "timeout_telnet_agent", found, defaults_log_level,
			&config->config->item_timeouts.telnet, revision);
	store_str_setting(values, "timeout_zabbix_agent", found, defaults_log_level,
			&config->config->item_timeouts.agent, revision);

	store_int_setting(values, "proxy_secrets_provider", defaults_log_level, &config->config->proxy_secrets_provider,
			revision);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	dc_sync_settings(zbx_dbsync_t *sync, zbx_uint64_t revision, unsigned char program_type)
{
	int			defaults_log_level, found = 1;
	zbx_setting_value_t	*values = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		defaults_log_level = LOG_LEVEL_DEBUG;
	else
		defaults_log_level = LOG_LEVEL_WARNING;

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	values = zbx_calloc(values, ARRSIZE(settings_description_table), sizeof(*values));

	init_shared_cache(&found);

	if (0 == setup_entry_table(sync, values))
		zabbix_log(LOG_LEVEL_WARNING, "no records in \"settings\" table");

	store_settings(values, found, revision, defaults_log_level);

	for (size_t i = 0; i < ARRSIZE(settings_description_table); i++)
	{
		if (ZBX_SETTING_TYPE_STR == settings_description_table[i].type && 1 == values[i].found)
			zbx_free(values[i].value.str);
	}
	zbx_free(values);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
