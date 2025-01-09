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

#include "dbconn.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbx_dbversion_constants.h"
#include "zbxjson.h"
#include "zbxtypes.h"
#if defined(HAVE_POSTGRESQL)
#	include "zbxstr.h"
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: For PostgreSQL, MySQL and MariaDB:                                *
 *          stoires DBMS version as integer: MMmmuu                           *
 *          M = major version part                                            *
 *          m = minor version part                                            *
 *          u = patch version part                                            *
 *                                                                            *
 * Example: if the original DB version was 1.2.34 then 10234 is set           *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	ZBX_DB_SVERSION = ZBX_DBVERSION_UNDEFINED;

zbx_uint32_t	db_get_server_version(void)
{
	return ZBX_DB_SVERSION;
}

#if defined(HAVE_POSTGRESQL)
static int 	ZBX_TIMESCALE_COMPRESSION_AVAILABLE = OFF;
static int	ZBX_TSDB_VERSION = -1;
#elif defined (HAVE_MYSQL)
static int	ZBX_MARIADB_SFORK = OFF;
#endif

/*********************************************************************************
 *                                                                               *
 * Purpose: determine if a vendor database(MySQL, MariaDB, PostgreSQL,           *
 *          ElasticDB) version satisfies Zabbix requirements                     *
 *                                                                               *
 * Parameters: database                - [IN] database name                      *
 *             current_version         - [IN] detected numeric version           *
 *             min_version             - [IN] minimum required numeric version   *
 *             max_version             - [IN] maximum required numeric version   *
 *             min_supported_version   - [IN] minimum supported numeric version  *
 *                                                                               *
 * Return value: resulting status flag                                           *
 *                                                                               *
 *********************************************************************************/
int	zbx_db_version_check(const char *database, zbx_uint32_t current_version, zbx_uint32_t min_version,
		zbx_uint32_t max_version, zbx_uint32_t min_supported_version)
{
	int	flag;

	if (ZBX_DBVERSION_UNDEFINED == current_version)
	{
		flag = DB_VERSION_FAILED_TO_RETRIEVE;
		zabbix_log(LOG_LEVEL_WARNING, "Failed to retrieve %s version", database);
	}
	else if (min_version > current_version && ZBX_DBVERSION_UNDEFINED != min_version)
	{
		flag = DB_VERSION_LOWER_THAN_MINIMUM;
		zabbix_log(LOG_LEVEL_WARNING, "Unsupported DB! %s version %lu is older than %lu",
				database, (unsigned long)current_version, (unsigned long)min_version);
	}
	else if (max_version < current_version && ZBX_DBVERSION_UNDEFINED != max_version)
	{
		flag = DB_VERSION_HIGHER_THAN_MAXIMUM;
		zabbix_log(LOG_LEVEL_WARNING, "Unsupported DB! %s version %lu is newer than %lu",
				database, (unsigned long)current_version, (unsigned long)max_version);
	}
	else if (min_supported_version > current_version && ZBX_DBVERSION_UNDEFINED != min_supported_version)
	{
		flag = DB_VERSION_NOT_SUPPORTED_ERROR;
		/* log message must be handled by server or proxy */
	}
	else
		flag = DB_VERSION_SUPPORTED;

	return flag;
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare json for front-end with the DB current, minimum and       *
 *          maximum versions and a flag that indicates if the version         *
 *          satisfies the requirements, and other information as well as      *
 *          information about DB extension in a similar way                   *
 *                                                                            *
 * Parameters:  json                     - [IN/OUT] json data                 *
 *              info                     - [IN] info to serialize             *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_version_json_create(struct zbx_json *json, struct zbx_db_version_info_t *info)
{
	zbx_json_addobject(json, NULL);
	zbx_json_addstring(json, "database", info->database, ZBX_JSON_TYPE_STRING);

	if (DB_VERSION_FAILED_TO_RETRIEVE != info->flag)
		zbx_json_addstring(json, "current_version", info->friendly_current_version, ZBX_JSON_TYPE_STRING);

	zbx_json_addstring(json, "min_version", info->friendly_min_version, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, "max_version", info->friendly_max_version, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(json, "history_pk", info->history_pk);

	if (NULL != info->friendly_min_supported_version)
	{
		zbx_json_addstring(json, "min_supported_version", info->friendly_min_supported_version,
				ZBX_JSON_TYPE_STRING);
	}

	zbx_json_addint64(json, "flag", info->flag);
	zbx_json_close(json);

	if (NULL != info->extension)
	{
		zbx_json_addobject(json, NULL);
		zbx_json_addstring(json, "database", info->extension, ZBX_JSON_TYPE_STRING);

		if (DB_VERSION_FAILED_TO_RETRIEVE != info->ext_flag)
		{
			zbx_json_addstring(json, "current_version",
					info->ext_friendly_current_version, ZBX_JSON_TYPE_STRING);
		}

		zbx_json_addstring(json, "min_version", info->ext_friendly_min_version, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(json, "max_version", info->ext_friendly_max_version, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(json, "min_supported_version", info->ext_friendly_min_supported_version,
				ZBX_JSON_TYPE_STRING);

		zbx_json_addint64(json, "flag", info->ext_flag);
		zbx_json_addint64(json, "extension_err_code", info->ext_err_code);
#ifdef HAVE_POSTGRESQL
		if (0 == zbx_strcmp_null(info->extension, ZBX_DB_EXTENSION_TIMESCALEDB))
		{
			if (ON == zbx_tsdb_get_compression_availability())
			{
				zbx_json_addstring(json, "compression_availability", "true", ZBX_JSON_TYPE_INT);
			}
			else
			{
				zbx_json_addstring(json, "compression_availability", "false", ZBX_JSON_TYPE_INT);
			}

			zbx_json_addint64(json, "compressed_chunks_history", info->history_compressed_chunks);
			zbx_json_addint64(json, "compressed_chunks_trends", info->trends_compressed_chunks);
		}
#endif
		zbx_json_close(json);
	}
}


/***************************************************************************************************************
 *                                                                                                             *
 * Purpose: retrieves the DB version info, including numeric version value                                     *
 *                                                                                                             *
 *          For PostgreSQL:                                                                                    *
 *          numeric version is available from the API                                                          *
 *                                                                                                             *
 *          For MySQL and MariaDB:                                                                             *
 *          numeric version is available from the API, but also the additional processing is required          *
 *          to determine if it is a MySQL or MariaDB and save this result as well                              *
 *                                                                                                             *
 *                                                                                                             *
 **************************************************************************************************************/
void	zbx_dbconn_extract_version_info(zbx_dbconn_t *db, struct zbx_db_version_info_t *version_info)
{
#define RIGHT2(x)	((int)((zbx_uint32_t)(x) - ((zbx_uint32_t)((x)/100))*100))
#if defined(HAVE_MYSQL)
	int		client_major_version, client_minor_version, client_release_version, server_major_version,
			server_minor_version, server_release_version;
	const char	*info;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != (info = mysql_get_server_info(db->conn)) && NULL != strstr(info, "MariaDB"))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "MariaDB fork detected");
		ZBX_MARIADB_SFORK = ON;
	}

	if (ON == ZBX_MARIADB_SFORK && NULL != info && 6 == sscanf(info, "%d.%d.%d-%d.%d.%d-MariaDB",
			&client_major_version, &client_minor_version, &client_release_version, &server_major_version,
			&server_minor_version, &server_release_version))
	{
		ZBX_DB_SVERSION = (zbx_uint32_t)(server_major_version * 10000 + server_minor_version * 100 +
				server_release_version);
		zabbix_log(LOG_LEVEL_DEBUG, "MariaDB subversion detected");
	}
	else
		ZBX_DB_SVERSION = (zbx_uint32_t)mysql_get_server_version(db->conn);

	version_info->current_version = ZBX_DB_SVERSION;
	version_info->friendly_current_version = zbx_dsprintf(NULL, "%d.%.2d.%.2d",
			RIGHT2(ZBX_DB_SVERSION/10000), RIGHT2(ZBX_DB_SVERSION/100),
			RIGHT2(ZBX_DB_SVERSION));

	if (ZBX_MARIADB_SFORK)
	{
		version_info->database = "MariaDB";

		version_info->min_version = ZBX_MARIADB_MIN_VERSION;
		version_info->max_version = ZBX_MARIADB_MAX_VERSION;
		version_info->min_supported_version = ZBX_MARIADB_MIN_SUPPORTED_VERSION;

		version_info->friendly_min_version = ZBX_MARIADB_MIN_VERSION_STR;
		version_info->friendly_max_version = ZBX_MARIADB_MAX_VERSION_STR;
		version_info->friendly_min_supported_version = ZBX_MARIADB_MIN_SUPPORTED_VERSION_STR;
	}
	else
	{
		version_info->database = "MySQL";

		version_info->min_version = ZBX_MYSQL_MIN_VERSION;
		version_info->max_version = ZBX_MYSQL_MAX_VERSION;
		version_info->min_supported_version = ZBX_MYSQL_MIN_SUPPORTED_VERSION;

		version_info->friendly_min_version = ZBX_MYSQL_MIN_VERSION_STR;
		version_info->friendly_max_version = ZBX_MYSQL_MAX_VERSION_STR;
		version_info->friendly_min_supported_version = ZBX_MYSQL_MIN_SUPPORTED_VERSION_STR;
	}

	version_info->flag = zbx_db_version_check(version_info->database, version_info->current_version,
			version_info->min_version, version_info->max_version, version_info->min_supported_version);

#elif defined(HAVE_POSTGRESQL)
	zbx_uint32_t major;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
	ZBX_DB_SVERSION = (zbx_uint32_t)PQserverVersion(db->conn);

	major = ZBX_DB_SVERSION/10000;

	version_info->database = "PostgreSQL";

	version_info->current_version = ZBX_DB_SVERSION;
	version_info->min_version = ZBX_POSTGRESQL_MIN_VERSION;
	version_info->max_version = ZBX_POSTGRESQL_MAX_VERSION;
	version_info->min_supported_version = ZBX_POSTGRESQL_MIN_SUPPORTED_VERSION;

	if (10 > major)
	{
		version_info->friendly_current_version = zbx_dsprintf(NULL, "%" PRIu32 ".%d.%d", major,
				RIGHT2(ZBX_DB_SVERSION/100), RIGHT2(ZBX_DB_SVERSION));
	}
	else
	{
		version_info->friendly_current_version = zbx_dsprintf(NULL, "%" PRIu32 ".%d", major,
				RIGHT2(ZBX_DB_SVERSION));
	}

	version_info->friendly_min_version = ZBX_POSTGRESQL_MIN_VERSION_STR;
	version_info->friendly_max_version = ZBX_POSTGRESQL_MAX_VERSION_STR;
	version_info->friendly_min_supported_version = ZBX_POSTGRESQL_MIN_SUPPORTED_VERSION_STR;

	version_info->flag = zbx_db_version_check(version_info->database, version_info->current_version,
			version_info->min_version, version_info->max_version, version_info->min_supported_version);

#else
	ZBX_UNUSED(db);
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
	version_info->flag = DB_VERSION_SUPPORTED;
	version_info->friendly_current_version = NULL;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() version:%lu", __func__, (unsigned long)ZBX_DB_SVERSION);
}

#ifdef HAVE_POSTGRESQL

static int	dbconn_tsdb_table_has_compressed_chunks(zbx_dbconn_t *db, const char *table_names)
{
	zbx_db_result_t	result;
	int		ret;

	result = zbx_dbconn_select(db, "select null from timescaledb_information.chunks"
			" where hypertable_name in (%s) and is_compressed='t'", table_names);

	if ((zbx_db_result_t)ZBX_DB_DOWN == result)
	{
		ret = FAIL;
		goto out;
	}

	if (NULL != zbx_db_fetch(result))
		ret = SUCCEED;
	else
		ret = FAIL;
out:
	zbx_db_free_result(result);

	return ret;
}

void	zbx_dbconn_tsdb_extract_compressed_chunk_flags(zbx_dbconn_t *db, struct zbx_db_version_info_t *version_info)
{
#define ZBX_TSDB_HISTORY_TABLES "'history_uint','history_log','history_str','history_text','history'"
#define ZBX_TSDB_TRENDS_TABLES "'history_bin','trends','trends_uint'"

	version_info->history_compressed_chunks =
			(SUCCEED == dbconn_tsdb_table_has_compressed_chunks(db, ZBX_TSDB_HISTORY_TABLES)) ? 1 : 0;

	version_info->trends_compressed_chunks =
			(SUCCEED == dbconn_tsdb_table_has_compressed_chunks(db, ZBX_TSDB_TRENDS_TABLES)) ? 1 : 0;

#undef ZBX_TSDB_HISTORY_TABLES
#undef ZBX_TSDB_TRENDS_TABLES
}

/***************************************************************************************************************
 *                                                                                                             *
 * Purpose: retrieves TimescaleDB extension info, including license string and numeric version value           *
 *                                                                                                             *
 **************************************************************************************************************/
void	zbx_dbconn_tsdb_info_extract(zbx_dbconn_t *db, struct zbx_db_version_info_t *version_info)
{
	int	tsdb_ver;

	if (0 != zbx_strcmp_null(version_info->extension, ZBX_DB_EXTENSION_TIMESCALEDB))
		return;

	tsdb_ver = zbx_dbconn_tsdb_get_version(db);

	version_info->ext_current_version = (zbx_uint32_t)tsdb_ver;
	version_info->ext_min_version = ZBX_TIMESCALE_MIN_VERSION;
	version_info->ext_max_version = ZBX_TIMESCALE_MAX_VERSION;
	version_info->ext_min_supported_version = ZBX_TIMESCALE_MIN_SUPPORTED_VERSION;

	version_info->ext_friendly_current_version = zbx_dsprintf(NULL, "%d.%d.%d", RIGHT2(tsdb_ver/10000),
			RIGHT2(tsdb_ver/100), RIGHT2(tsdb_ver));

	version_info->ext_friendly_min_version = ZBX_TIMESCALE_MIN_VERSION_STR;
	version_info->ext_friendly_max_version = ZBX_TIMESCALE_MAX_VERSION_STR;
	version_info->ext_friendly_min_supported_version = ZBX_TIMESCALE_MIN_SUPPORTED_VERSION_STR;

	version_info->ext_flag = zbx_db_version_check(version_info->extension, version_info->ext_current_version,
			version_info->ext_min_version, version_info->ext_max_version,
			version_info->ext_min_supported_version);

	zabbix_log(LOG_LEVEL_DEBUG, "TimescaleDB version: [%d]", tsdb_ver);
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns TimescaleDB (TSDB) version as integer: MMmmuu             *
 *          M = major version part                                            *
 *          m = minor version part                                            *
 *          u = patch version part                                            *
 *                                                                            *
 * Example: TSDB 1.5.1 version will be returned as 10501                      *
 *                                                                            *
 * Return value: TSDB version or 0 if unknown or the extension not installed  *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_tsdb_get_version(zbx_dbconn_t *db)
{
	int		ver, major, minor, patch;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	if (-1 == ZBX_TSDB_VERSION)
	{
		/* catalog pg_extension not available */
		if (90001 > ZBX_DB_SVERSION)
		{
			ver = ZBX_TSDB_VERSION = 0;
			goto out;
		}

		result = zbx_dbconn_select(db, "select extversion from pg_extension where extname = 'timescaledb'");

		/* database down, can re-query in the next call */
		if ((zbx_db_result_t)ZBX_DB_DOWN == result)
		{
			ver = 0;
			goto out;
		}

		/* extension is not installed */
		if (NULL == result)
		{
			ver = ZBX_TSDB_VERSION = 0;
			goto out;
		}

		if (NULL != (row = zbx_db_fetch(result)) &&
				3 == sscanf((const char*)row[0], "%d.%d.%d", &major, &minor, &patch))
		{
			ver = major * 10000;
			ver += minor * 100;
			ver += patch;
			ZBX_TSDB_VERSION = ver;
		}
		else
			ver = ZBX_TSDB_VERSION = 0;

		zbx_db_free_result(result);
	}
	else
		ver = ZBX_TSDB_VERSION;
out:
	return ver;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets TimescaleDB (TSDB) compression availability                  *
 *                                                                            *
 * Parameters:  compression_availabile - [IN] compression availability        *
 *              0 (OFF): compression is not available                         *
 *              1 (ON): compression is available                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_tsdb_set_compression_availability(int compression_availabile)
{
	ZBX_TIMESCALE_COMPRESSION_AVAILABLE = compression_availabile;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves TimescaleDB (TSDB) compression availability             *
 *                                                                            *
 * Return value: compression availability as as integer                       *
 *               0 (OFF): compression is not available                        *
 *               1 (ON): compression is available                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_tsdb_get_compression_availability(void)
{
	return ZBX_TIMESCALE_COMPRESSION_AVAILABLE;
}

#endif

