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

#include "history_compress.h"

#if defined(HAVE_POSTGRESQL)

#include "zbxdbhigh.h"
#include "zbxstr.h"
#include "zbxdb.h"

#define ZBX_TS_UNIX_NOW		"zbx_ts_unix_now"
#define ZBX_TS_UNIX_NOW_CREATE	"create or replace function "ZBX_TS_UNIX_NOW"() returns integer language sql" \
				" stable as $$ select extract(epoch from now())::integer $$"

#define COMPRESSION_TOLERANCE		(SEC_PER_HOUR * 2)
#define COMPRESSION_POLICY_REMOVE	"remove_compression_policy"
#define COMPRESSION_POLICY_ADD		"add_compression_policy"

typedef struct
{
	const char	*name;
	const char	*segmentby;
	const char	*orderby;
} zbx_history_table_compression_options_t;

static zbx_history_table_compression_options_t	compression_tables[] = {
	{"history",		"itemid",	"clock,ns"},
	{"history_uint",	"itemid",	"clock,ns"},
	{"history_str",		"itemid",	"clock,ns"},
	{"history_text",	"itemid",	"clock,ns"},
	{"history_log",		"itemid",	"clock,ns"},
	{"trends",		"itemid",	"clock"},
	{"trends_uint",		"itemid",	"clock"},
	{"auditlog",		"auditid",	"clock"}
};

static unsigned char	compression_status_cache = 0;
static int		compress_older_cache = 0;

/******************************************************************************
 *                                                                            *
 * Purpose: check that hypertables are segmented                              *
 *                                                                            *
 * Parameters: table_name - [IN] hypertable name                              *
 *             segmentby  - [IN] field to segment by                          *
 *             orderby    - [IN] field to order by                            *
 *                                                                            *
 ******************************************************************************/
static void	hk_check_table_segmentation(const char *table_name, const char *segmentby, const char *orderby)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): table: %s", __func__, table_name);

	/* get hypertable's 'segmentby' attribute name */

	result = zbx_db_select("select attname from timescaledb_information.compression_settings"
			" where hypertable_schema='%s' and hypertable_name='%s'"
			" and segmentby_column_index is not null", zbx_db_get_schema_esc(), table_name);

	for (i = 0; NULL != (row = zbx_db_fetch(result)); i++)
	{
		if (0 != strcmp(row[0], segmentby))
			i++;
	}

	if (1 != i)
	{
		zbx_db_execute("alter table %s set (timescaledb.compress,timescaledb.compress_segmentby='%s',"
				"timescaledb.compress_orderby='%s')", table_name, segmentby, orderby);
	}

	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns data compression age configured for hypertable            *
 *                                                                            *
 * Parameters: table_name - [IN] hypertable name                              *
 *                                                                            *
 * Return value: data compression age in seconds                              *
 *                                                                            *
 ******************************************************************************/
static int	hk_get_table_compression_age(const char *table_name)
{
	int		age = 0;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): table: %s", __func__, table_name);

	result = zbx_db_select("select extract(epoch from (config::json->>'compress_after')::interval) from"
			" timescaledb_information.jobs where application_name like 'Compression%%' and"
			" hypertable_schema='%s' and hypertable_name='%s'", zbx_db_get_schema_esc(), table_name);

	if (NULL != (row = zbx_db_fetch(result)))
		age = atoi(row[0]);

	zbx_db_free_result(result);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() age: %d", __func__, age);

	return age;
}

/******************************************************************************
 *                                                                            *
 * Purpose: ensures that table compression is configured to specified age     *
 *                                                                            *
 * Parameters: table_name - [IN] hypertable name                              *
 *             age        - [IN] compression age                              *
 *                                                                            *
 ******************************************************************************/
static void	hk_check_table_compression_age(const char *table_name, int age)
{
	int	compress_after;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): table: %s age %d", __func__, table_name, age);

	if (age != (compress_after = hk_get_table_compression_age(table_name)))
	{
		zbx_db_result_t	res;

		if (0 != compress_after)
			zbx_db_free_result(zbx_db_select("select %s('%s')", COMPRESSION_POLICY_REMOVE, table_name));

		zabbix_log(LOG_LEVEL_DEBUG, "adding compression policy to table: %s age %d", table_name, age);

		res = zbx_db_select("select %s('%s', integer '%d')", COMPRESSION_POLICY_ADD, table_name, age);

		if (NULL == res)
			zabbix_log(LOG_LEVEL_ERR, "failed to add compression policy to table '%s'", table_name);
		else
			zbx_db_free_result(res);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: turns on table compression for items older than specified age     *
 *                                                                            *
 * Parameters: age - [IN] compression age                                     *
 *                                                                            *
 ******************************************************************************/
static void	hk_history_enable_compression(int age)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (size_t i = 0; i < ARRSIZE(compression_tables); i++)
	{
		zbx_db_result_t	res;

		res = zbx_db_select("select set_integer_now_func('%s', '"ZBX_TS_UNIX_NOW"', true)",
				compression_tables[i].name);
		if(NULL == res)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Table \"%s\"is not hypertable. Run timescaledb.sql script to "
					"upgrade configuration.", compression_tables[i].name);
			continue;
		}

		zbx_db_free_result(res);
		hk_check_table_segmentation(compression_tables[i].name, compression_tables[i].segmentby,
				compression_tables[i].orderby);
		hk_check_table_compression_age(compression_tables[i].name, age);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: turns off table compression                                       *
 *                                                                            *
 ******************************************************************************/
static void	hk_history_disable_compression(void)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (size_t i = 0; i < ARRSIZE(compression_tables); i++)
	{
		if (0 == hk_get_table_compression_age(compression_tables[i].name))
			continue;

		zbx_db_free_result(zbx_db_select("select %s('%s')", COMPRESSION_POLICY_REMOVE,
				compression_tables[i].name));
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

#endif

/******************************************************************************
 *                                                                            *
 * Purpose: initialize compression for history/trends tables                  *
 *                                                                            *
 ******************************************************************************/
void	hk_history_compression_init(void)
{
#if defined(HAVE_POSTGRESQL)
	int		disable_compression = 0;
	char		*db_log_level = NULL;
	zbx_config_t	cfg;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);
	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_DB_EXTENSION);
	compression_status_cache = cfg.db.history_compression_status;
	compress_older_cache = cfg.db.history_compress_older;

	if (0 == zbx_strcmp_null(cfg.db.extension, ZBX_DB_EXTENSION_TIMESCALEDB))
	{
		/* suppress notice logs during DB initialization */
		zbx_db_result_t	result = zbx_db_select("show client_min_messages");
		zbx_db_row_t	row;

		if (NULL != (row = zbx_db_fetch(result)))
		{
			db_log_level = zbx_strdup(db_log_level, row[0]);
			zbx_db_execute("set client_min_messages to warning");
		}
		zbx_db_free_result(result);

		if (ON == zbx_tsdb_get_compression_availability() && ON == cfg.db.history_compression_status)
		{
			if (0 == cfg.db.history_compress_older)
			{
				disable_compression = 1;
				hk_history_disable_compression();
			}
			else
			{
				zbx_db_execute(ZBX_TS_UNIX_NOW_CREATE);
				hk_history_enable_compression(cfg.db.history_compress_older + COMPRESSION_TOLERANCE);
			}
		}
		else
			hk_history_disable_compression();
	}
	else if (ON == cfg.db.history_compression_status)
		disable_compression = 1;

	if (0 != disable_compression && ZBX_DB_OK > zbx_db_execute("update config set compression_status=0"))
		zabbix_log(LOG_LEVEL_ERR, "failed to set database compression status");

	zbx_config_clean(&cfg);

	if (NULL != db_log_level)
	{
		zbx_db_execute("set client_min_messages to %s", db_log_level);
		zbx_free(db_log_level);
	}

	zbx_db_close();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates history compression period settings                       *
 *                                                                            *
 * Parameters: cfg - [IN] database extension history compression settings     *
 *                                                                            *
 ******************************************************************************/
void	hk_history_compression_update(zbx_config_db_t *cfg)
{
#if defined(HAVE_POSTGRESQL)
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ON == zbx_tsdb_get_compression_availability() && ON == cfg->history_compression_status)
	{
		if (cfg->history_compression_status != compression_status_cache ||
				cfg->history_compress_older != compress_older_cache)
		{
			zbx_db_execute(ZBX_TS_UNIX_NOW_CREATE);
			hk_history_enable_compression(cfg->history_compress_older + COMPRESSION_TOLERANCE);
		}
	}
	else if (cfg->history_compression_status != compression_status_cache)
		hk_history_disable_compression();

	compression_status_cache = cfg->history_compression_status;
	compress_older_cache = cfg->history_compress_older;
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
#else
	ZBX_UNUSED(cfg);
#endif
}
