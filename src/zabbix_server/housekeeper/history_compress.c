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

#include "history_compress.h"

#if defined(HAVE_POSTGRESQL)

#include "zbxdbhigh.h"
#include "zbxstr.h"
#include "zbxdb.h"

#define ZBX_TS_UNIX_NOW		"zbx_ts_unix_now"
#define ZBX_TS_UNIX_NOW_CREATE	"create or replace function "ZBX_TS_UNIX_NOW"() returns integer language sql" \
				" stable as $$ select extract(epoch from now())::integer $$"

#define COMPRESSION_TOLERANCE		(SEC_PER_HOUR * 2)

/* Compression policy: chunks containing data older than provided data are compressed. */
#define POLICY_COMPRESS_AFTER		0
/* Compression policy: chunks with creation time older than this cut-off point are compressed. */
#define POLICY_COMPRESS_CREATED_BEFORE	1

typedef struct
{
	const char	*name;
	const char	*segmentby;
	const char	*orderby;
	int		compression_policy;
} zbx_history_table_compression_options_t;

static zbx_history_table_compression_options_t	compression_tables[] = {
	{"history",		"itemid",	"clock,ns",	POLICY_COMPRESS_AFTER},
	{"history_uint",	"itemid",	"clock,ns",	POLICY_COMPRESS_AFTER},
	{"history_str",		"itemid",	"clock,ns",	POLICY_COMPRESS_AFTER},
	{"history_text",	"itemid",	"clock,ns",	POLICY_COMPRESS_AFTER},
	{"history_log",		"itemid",	"clock,ns",	POLICY_COMPRESS_AFTER},
	{"history_bin",		"itemid",	"clock,ns",	POLICY_COMPRESS_AFTER},
	{"trends",		"itemid",	"clock",	POLICY_COMPRESS_AFTER},
	{"trends_uint",		"itemid",	"clock",	POLICY_COMPRESS_AFTER},
	/* Since auditlog table uses CUID from auditid field to partition table into chunks we need to use different */
	/* compression policy due to internal TimescaleDB bug. */
	{"auditlog",		"auditid",	"clock",	POLICY_COMPRESS_CREATED_BEFORE}
};

static int	compression_status_cache = 0;
static int	compress_older_cache = 0;

/******************************************************************************
 *                                                                            *
 * Purpose: enables compression policy by declaring column to segment by      *
 *                                                                            *
 * Parameters: table_name - [IN] hypertable name                              *
 *             segmentby  - [IN] field to segment by                          *
 *             orderby    - [IN] field to order by                            *
 *                                                                            *
 ******************************************************************************/
static void	hk_compression_policy_enable(const char *table_name, const char *segmentby, const char *orderby)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): table: %s", __func__, table_name);

	/* get hypertable's 'segmentby' attribute name */

	if (1 == ZBX_DB_TSDB_GE_V2_18)
	{
		/* timescaledb_information.hypertable_compression_settings is available since TimescaleDB 2.18.0 */
		result = zbx_db_select(
				"select segmentby"
				" from timescaledb_information.hypertable_compression_settings"
				" where hypertable::text='%s'"
					" and segmentby is not null",
				table_name);
	}
	else
	{
		/* timescaledb_information.compression_settings is deprecated since TimescaleDB 2.18.0 */
		result = zbx_db_select(
				"select attname"
				" from timescaledb_information.compression_settings"
				" where hypertable_schema='%s'"
					" and hypertable_name='%s'"
					" and segmentby_column_index is not null",
				zbx_db_get_schema_esc(), table_name);
	}

	for (i = 0; NULL != (row = zbx_db_fetch(result)); i++)
	{
		if (0 != strcmp(row[0], segmentby))
			i++;
	}

	if (1 != i)
	{
		if (1 == ZBX_DB_TSDB_GE_V2_18)
		{
			/* Available since TimescaleDB 2.18.0:                                         */
			/* timescaledb.enable_columnstore, timescaledb.segmentby, timescaledb.orderby. */
			if (ZBX_DB_OK > zbx_db_execute(
					"alter table %s set("
						"timescaledb.enable_columnstore=true,"
						"timescaledb.segmentby='%s',"
						"timescaledb.orderby='%s')",
					table_name, segmentby, orderby))

			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot enable compression policy for table \"%s\"",
						table_name);
			}
		}
		else
		{
			/* Deprecated since TimescaleDB 2.18.0:                                                */
			/* timescaledb.compress, timescaledb.compress_segmentby, timescaledb.compress_orderby. */
			if (ZBX_DB_OK > zbx_db_execute(
					"alter table %s set("
						"timescaledb.compress,"
						"timescaledb.compress_segmentby='%s',"
						"timescaledb.compress_orderby='%s')",
					table_name, segmentby, orderby))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot enable compression policy for table \"%s\"",
						table_name);
			}
		}
	}

	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns data compression age configured for hypertable            *
 *                                                                            *
 * Parameters: table_name         - [IN] hypertable name                      *
 *             compression_policy - [IN]                                      *
 *                                                                            *
 * Return value: >=0 - data compression age in seconds                        *
 *               -1  - hypertable has different compression policy            *
 *                                                                            *
 ******************************************************************************/
static int	hk_get_compression_age(const char *table_name, int compression_policy)
{
	int		age = 0;
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	const char	*field = compression_policy == POLICY_COMPRESS_AFTER ? "compress_after" :
					"compress_created_before";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): table: %s", __func__, table_name);

	/* application_name is like 'Columnstore Policy%' in the newer TimescaleDB versions. */
	/* application_name is like 'Compression%' in the older TimescaleDB versions before around TimescaleDB 2.18. */
	result = zbx_db_select(
			"select extract(epoch from (config::json->>'%s')::interval)"
			" from timescaledb_information.jobs"
			" where (application_name like 'Columnstore Policy%%'"
				" or application_name like 'Compression%%')"
				" and hypertable_schema='%s' and hypertable_name='%s'",
			field, zbx_db_get_schema_esc(),	table_name);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		/* extraction from JSON may return empty field when JSON exists but field doesn't */
		if (NULL == row[0])
		{
			zabbix_log(LOG_LEVEL_ERR, "Unexpected TimescaleDB configuration: the %s table does not have %s "
					"compression policy", table_name, field);
			age = -1;
		}
		else
		{
			age = atoi(row[0]);
		}
	}

	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() age: %d", __func__, age);

	return age;
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes compression policy from hypertable                        *
 *                                                                            *
 * Parameters: table_name - [IN]                                              *
 *                                                                            *
 ******************************************************************************/
static void	hk_compression_policy_remove(const char *table_name)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): table: %s", __func__, table_name);

	if (1 == ZBX_DB_TSDB_GE_V2_18)
	{
		/* remove_columnstore_policy() is available since TimescaleDB 2.18.0 */
		if (ZBX_DB_OK > zbx_db_execute("call remove_columnstore_policy('%s')", table_name))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot remove compression policy from table \"%s\"",
					table_name);
		}
	}
	else
	{
		zbx_db_result_t	result;

		/* remove_compression_policy() is deprecated since TimescaleDB 2.18.0 */
		if (NULL == (result = zbx_db_select("select remove_compression_policy('%s')", table_name)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot remove compression policy from table \"%s\"",
					table_name);
		}

		zbx_db_free_result(result);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds compress_after policy to hypertable                          *
 *                                                                            *
 * Parameters: table_name - [IN]                                              *
 *             ts         - [IN] compress older than, timestamp in seconds    *
 *                                                                            *
 * Returns:  SUCCEED - query was executed with no errors                      *
 *           FAIL    - otherwise                                              *
 *                                                                            *
 ******************************************************************************/
static int	hk_policy_compress_after_add(const char *table_name, int ts)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): table:%s ts:%d", __func__, table_name, ts);

	if (1 == ZBX_DB_TSDB_GE_V2_18)
	{
		int	rc;

		/* available since TimescaleDB 2.18.0 */
		rc = zbx_db_execute("call add_columnstore_policy('%s', after => integer '%d', if_not_exists => true)",
				table_name, ts);
		ret = (ZBX_DB_OK > rc ? FAIL : SUCCEED);
	}
	else
	{
		zbx_db_result_t	result;

		/* deprecated since TimescaleDB 2.18.0 */
		result = zbx_db_select("select add_compression_policy('%s', compress_after => integer '%d',"
				" if_not_exists => true)", table_name, ts);
		ret = (NULL == result ? FAIL : SUCCEED);
		zbx_db_free_result(result);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds created_before compression policy to hypertable              *
 *                                                                            *
 * Parameters: table_name - [IN]                                              *
 *             age        - [IN] compress created before, interval in seconds *
 *                                                                            *
 * Returns:  SUCCEED - query was executed with no errors                      *
 *           FAIL    - otherwise                                              *
 *                                                                            *
 ******************************************************************************/
static int	hk_policy_compress_created_before_add(const char *table_name, int age)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): table:%s age:%d", __func__, table_name, age);

	if (1 == ZBX_DB_TSDB_GE_V2_18)
	{
		int	rc;

		/* available since TimescaleDB 2.18.0 */
		rc = zbx_db_execute("call add_columnstore_policy('%s', created_before => interval '%d',"
				" if_not_exists => true)", table_name, age);
		ret = (ZBX_DB_OK > rc ? FAIL : SUCCEED);
	}
	else
	{
		zbx_db_result_t	result;

		/* deprecated since TimescaleDB 2.18.0 */
		result = zbx_db_select("select add_compression_policy('%s', compress_created_before => interval '%d',"
				" if_not_exists => true)", table_name, age);
		ret = (NULL == result ? FAIL : SUCCEED);
		zbx_db_free_result(result);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: ensures that table compression is configured to specified age     *
 *                                                                            *
 * Parameters: table_name         - [IN] hypertable name                      *
 *             age                - [IN] compression age                      *
 *             compression_policy - [IN]                                      *
 *                                                                            *
 ******************************************************************************/
static void	hk_set_table_compression_age(const char *table_name, int age, int compression_policy)
{
	int	compress_after;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): table:%s age:%d, compression_policy:%d", __func__, table_name, age,
			compression_policy);

	if (age != (compress_after = hk_get_compression_age(table_name, compression_policy)) && -1 != compress_after)
	{
		if (0 != compress_after)
			hk_compression_policy_remove(table_name);

		zabbix_log(LOG_LEVEL_DEBUG, "adding compression policy to table:%s age:%d", table_name, age);

		int	res = FAIL;

		switch (compression_policy)
		{
			case POLICY_COMPRESS_AFTER:
				res = hk_policy_compress_after_add(table_name, age);
				break;
			case POLICY_COMPRESS_CREATED_BEFORE:
				res = hk_policy_compress_created_before_add(table_name, age);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				break;
		}

		if (FAIL == res)
			zabbix_log(LOG_LEVEL_WARNING, "cannot add compression policy to table \"%s\"", table_name);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: turns on table compression for items or chunks older than         *
 *           specified age                                                    *
 *                                                                            *
 * Parameters: age - [IN] compression age                                     *
 *                                                                            *
 ******************************************************************************/
static void	hk_history_enable_compression(int age)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (size_t i = 0; i < ARRSIZE(compression_tables); i++)
	{
		const zbx_history_table_compression_options_t	*table = &compression_tables[i];

		if (POLICY_COMPRESS_AFTER == table->compression_policy)
		{
			zbx_db_result_t	res;

			res = zbx_db_select("select set_integer_now_func('%s', '"ZBX_TS_UNIX_NOW"', true)",
					table->name);

			if (NULL == res)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Table \"%s\" is not a hypertable. Execute TimescaleDB"
						" configuration step as described in Zabbix documentation to upgrade"
						" schema.", table->name);
				continue;
			}

			zbx_db_free_result(res);
		}

		hk_compression_policy_enable(table->name, table->segmentby, table->orderby);

		hk_set_table_compression_age(table->name, age, table->compression_policy);
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
		const zbx_history_table_compression_options_t	*table = &compression_tables[i];

		if (0 >= hk_get_compression_age(table->name, table->compression_policy))
			continue;

		hk_compression_policy_remove(table->name);
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

	if (0 != disable_compression &&
			ZBX_DB_OK > zbx_db_execute("update settings set value_int=0 where name='compression_status'"))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot set database compression status");
	}

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
