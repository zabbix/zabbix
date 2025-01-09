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
#include "zbxcfg.h"
#include "zbxmutexs.h"
#include "zbxshmem.h"
#include "zbxalgo.h"
#include "zbxdbschema.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxtypes.h"
#if defined(HAVE_POSTGRESQL)
#	include "zbx_dbversion_constants.h"
#endif

#define ZBX_MAX_SQL_SIZE	262144	/* 256KB */
#ifndef ZBX_MAX_OVERFLOW_SQL_SIZE
#	define ZBX_MAX_OVERFLOW_SQL_SIZE	ZBX_MAX_SQL_SIZE
#elif 0 != ZBX_MAX_OVERFLOW_SQL_SIZE && \
	(1024 > ZBX_MAX_OVERFLOW_SQL_SIZE || ZBX_MAX_OVERFLOW_SQL_SIZE > ZBX_MAX_SQL_SIZE)
#error ZBX_MAX_OVERFLOW_SQL_SIZE is out of range
#endif

ZBX_CONST_PTR_VECTOR_IMPL(const_db_field_ptr, const zbx_db_field_t *)
ZBX_PTR_VECTOR_IMPL(db_value_ptr, zbx_db_value_t *)

const char	*idcache_tables[] = {"events", "event_tag", "problem_tag", "dservices", "dhosts", "alerts",
					"escalations", "autoreg_host", "event_suppress", "trigger_queue",
					"proxy_history", "proxy_dhistory", "proxy_autoreg_host", "host_proxy"};

#define ZBX_IDS_SIZE	ARRSIZE(idcache_tables)

static int	compare_table_names(const void *d1, const void *d2)
{
	const char *n1 = *(const char * const *)d1;
	const char *n2 = *(const char * const *)d2;

	return strcmp(n1, n2);
}

typedef struct
{
	zbx_uint64_t	lastids[ZBX_IDS_SIZE];
}
zbx_db_idcache_t;

/* nextid cache for tables updated only by server/proxy */
static zbx_mutex_t	idcache_mutex = ZBX_MUTEX_NULL;
zbx_shmem_info_t	*idcache_mem;
static zbx_db_idcache_t	*idcache = NULL;

int	zbx_db_init(char **error)
{
	qsort(idcache_tables, ZBX_IDS_SIZE, sizeof(idcache_tables[0]), compare_table_names);

	if (SUCCEED != dbconn_init(error))
		return FAIL;

	if (SUCCEED != zbx_mutex_create(&idcache_mutex, ZBX_MUTEX_CACHE_IDS, error))
		return FAIL;

	if (SUCCEED != zbx_shmem_create_min(&idcache_mem, sizeof(zbx_db_idcache_t), "table ids cache", NULL, 0, error))
		return FAIL;

	idcache = idcache_mem->base;
	memset(idcache, 0, sizeof(zbx_db_idcache_t));

	return SUCCEED;
}

void	zbx_db_deinit(void)
{
	dbconn_deinit();

	zbx_shmem_destroy(idcache_mem);
	idcache_mem = NULL;

	zbx_mutex_destroy(&idcache_mutex);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get next id for requested table from cache                        *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	dbconn_get_cached_nextid(zbx_dbconn_t *db, size_t index, zbx_uint64_t num)
{
	zbx_uint64_t	nextid, lastid;
	const char	*table_name = idcache_tables[index];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s' num:" ZBX_FS_UI64, __func__, table_name, num);

	zbx_mutex_lock(idcache_mutex);

	if (0 == idcache->lastids[index])
	{
		zbx_db_result_t		result;
		zbx_db_row_t		row;
		const zbx_db_table_t	*table;
		zbx_uint64_t		min = 0, max = ZBX_DB_MAX_ID;

		if (NULL == (table = zbx_db_get_table(table_name)))
		{
			zbx_mutex_unlock(idcache_mutex);
			THIS_SHOULD_NEVER_HAPPEN_MSG("unknown table: %s", table_name);
			exit(EXIT_FAILURE);
		}

		result = zbx_dbconn_select(db, "select max(%s) from %s where %s between " ZBX_FS_UI64 " and "
				ZBX_FS_UI64, table->recid, table_name, table->recid, min, max);

		if (NULL == result)
		{
			lastid = 0;
			nextid = 0;
			goto out;
		}

		if (NULL == (row = zbx_db_fetch(result)) || SUCCEED == zbx_db_is_null(row[0]))
			idcache->lastids[index] = min;
		else
			ZBX_STR2UINT64(idcache->lastids[index], row[0]);

		zbx_db_free_result(result);
	}

	nextid = idcache->lastids[index] + 1;
	idcache->lastids[index] += num;
	lastid = idcache->lastids[index];
out:
	zbx_mutex_unlock(idcache_mutex);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() table:'%s' [" ZBX_FS_UI64 ":" ZBX_FS_UI64 "]",
			__func__, table_name, nextid, lastid);

	return nextid;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get next id for requested table from database                     *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	dbconn_get_nextid(zbx_dbconn_t *db, const char *tablename, zbx_uint64_t num)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_uint64_t		ret1, ret2;
	zbx_uint64_t		min = 0, max = ZBX_DB_MAX_ID;
	int			found = FAIL, dbres;
	const zbx_db_table_t	*table;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tablename:'%s'", __func__, tablename);

	if (NULL == (table = zbx_db_get_table(tablename)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Error getting table: %s", tablename);
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	while (FAIL == found)
	{
		/* avoid eternal loop within failed transaction */
		if (0 < db->txn_level && 0 != db->txn_error)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "End of %s() transaction failed", __func__);
			return 0;
		}

		result = zbx_dbconn_select(db, "select nextid from ids where table_name='%s' and field_name='%s'",
				table->table, table->recid);

		if (NULL == (row = zbx_db_fetch(result)))
		{
			zbx_db_free_result(result);

			result = zbx_dbconn_select(db, "select max(%s) from %s where %s between " ZBX_FS_UI64 " and "
					ZBX_FS_UI64, table->recid, table->table, table->recid, min, max);

			if (NULL == (row = zbx_db_fetch(result)) || SUCCEED == zbx_db_is_null(row[0]))
			{
				ret1 = min;
			}
			else
			{
				ZBX_STR2UINT64(ret1, row[0]);
				if (ret1 >= max)
				{
					zabbix_log(LOG_LEVEL_CRIT, "maximum number of id's exceeded"
							" [table:%s, field:%s, id:" ZBX_FS_UI64 "]",
							table->table, table->recid, ret1);
					exit(EXIT_FAILURE);
				}
			}

			zbx_db_free_result(result);

			dbres = zbx_dbconn_execute(db, "insert into ids (table_name,field_name,nextid)"
					" values ('%s','%s'," ZBX_FS_UI64 ")",
					table->table, table->recid, ret1);

			if (ZBX_DB_OK > dbres)
			{
				/* solving the problem of an invisible record created in a parallel transaction */
				zbx_dbconn_execute(db, "update ids set nextid=nextid+1 where table_name='%s' and"
						" field_name='%s'", table->table, table->recid);
			}

			continue;
		}
		else
		{
			ZBX_STR2UINT64(ret1, row[0]);
			zbx_db_free_result(result);

			if (ret1 < min || ret1 >= max)
			{
				zbx_dbconn_execute(db, "delete from ids where table_name='%s' and field_name='%s'",
						table->table, table->recid);
				continue;
			}

			zbx_dbconn_execute(db, "update ids set nextid=nextid+" ZBX_FS_UI64 " where table_name='%s' and"
					" field_name='%s'", num, table->table, table->recid);

			result = zbx_dbconn_select(db, "select nextid from ids where table_name='%s' and"
					" field_name='%s'", table->table, table->recid);

			if (NULL != (row = zbx_db_fetch(result)) && SUCCEED != zbx_db_is_null(row[0]))
			{
				ZBX_STR2UINT64(ret2, row[0]);

				if (ret1 + num == ret2)
					found = SUCCEED;
			}
			else
				THIS_SHOULD_NEVER_HAPPEN;

			zbx_db_free_result(result);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64 " table:'%s' recid:'%s'",
			__func__, ret2 - num + 1, table->table, table->recid);

	return ret2 - num + 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get next id for requested table                                   *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_dbconn_get_maxid_num(zbx_dbconn_t *db, const char *tablename, int num)
{
	const char	**ptr;

	if (NULL != (ptr = (const char **)bsearch(&tablename, idcache_tables, ZBX_IDS_SIZE, sizeof(idcache_tables[0]),
			compare_table_names)))
	{
		return dbconn_get_cached_nextid(db, (size_t)(ptr - idcache_tables), (zbx_uint64_t)num);
	}

	return dbconn_get_nextid(db, tablename, (zbx_uint64_t)num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush SQL request                                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_flush_overflowed_sql(zbx_dbconn_t *db, char *sql, size_t sql_offset)
{
	if (0 != sql_offset)
		return zbx_dbconn_execute(db, "%s", sql);

	return ZBX_DB_OK;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a set of SQL statements IF it is big enough               *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_execute_overflowed_sql(zbx_dbconn_t *db, char **sql, size_t *sql_alloc, size_t *sql_offset)
{
	int	ret = SUCCEED;

	if (ZBX_MAX_OVERFLOW_SQL_SIZE < *sql_offset)
	{
#ifdef HAVE_MULTIROW_INSERT
		if (',' == (*sql)[*sql_offset - 1])
		{
			(*sql_offset)--;
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ";\n");
		}
#else
		ZBX_UNUSED(sql_alloc);
#endif
		/* For Oracle with max_overflow_sql_size == 0, jump over "begin\n" */
		/* before execution. ZBX_SQL_EXEC_FROM is 0 for all other cases. */
		if (ZBX_DB_OK > zbx_dbconn_execute(db, "%s", *sql))
			ret = FAIL;

		*sql_offset = 0;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Return value: escaped string                                               *
 *                                                                            *
 * Comments: sync changes with 'db_get_escape_string_len'                     *
 *           and 'zbx_db_dyn_escape_string'                                   *
 *                                                                            *
 ******************************************************************************/
static void	db_escape_string(const char *src, char *dst, size_t len, zbx_escape_sequence_t flag)
{
	const char	*s;
	char		*d;

	assert(dst);

	len--;	/* '\0' */

	for (s = src, d = dst; NULL != s && '\0' != *s && 0 < len; s++)
	{
		if (ESCAPE_SEQUENCE_ON == flag && SUCCEED == db_is_escape_sequence(*s))
		{
			if (2 > len)
				break;

#if defined(HAVE_MYSQL)
			*d++ = '\\';
#elif defined(HAVE_POSTGRESQL)
			*d++ = *s;
#else
			*d++ = '\'';
#endif
			len--;
		}
		*d++ = *s;
		len--;
	}
	*d = '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: to calculate escaped string length limited by bytes or characters *
 *          whichever is reached first.                                       *
 *                                                                            *
 * Parameters: s         - [IN] string to escape                              *
 *             max_bytes - [IN] limit in bytes                                *
 *             max_chars - [IN] limit in characters                           *
 *             flag      - [IN] sequences need to be escaped on/off           *
 *                                                                            *
 * Return value: return length in bytes of escaped string                     *
 *               with terminating '\0'                                        *
 *                                                                            *
 ******************************************************************************/
static size_t	db_get_escape_string_len(const char *s, size_t max_bytes, size_t max_chars,
		zbx_escape_sequence_t flag)
{
	size_t	csize, len = 1;	/* '\0' */

	if (NULL == s)
		return len;

	while ('\0' != *s && 0 < max_chars)
	{
		csize = zbx_utf8_char_len(s);

		/* process non-UTF-8 characters as single byte characters */
		if (0 == csize)
			csize = 1;

		if (max_bytes < csize)
			break;

		if (ESCAPE_SEQUENCE_ON == flag && SUCCEED == db_is_escape_sequence(*s))
			len++;

		s += csize;
		len += csize;
		max_bytes -= csize;
		max_chars--;
	}

	return len;
}

/******************************************************************************
 *                                                                            *
 * Purpose: to escape string limited by bytes or characters, whichever limit  *
 *          is reached first.                                                 *
 *                                                                            *
 * Parameters: src       - [IN] string to escape                              *
 *             max_bytes - [IN] limit in bytes                                *
 *             max_chars - [IN] limit in characters                           *
 *             flag      - [IN] sequences need to be escaped on/off           *
 *                                                                            *
 * Return value: escaped string                                               *
 *                                                                            *
 ******************************************************************************/
char	*db_dyn_escape_string(const char *src, size_t max_bytes, size_t max_chars, zbx_escape_sequence_t flag)
{
	char	*dst = NULL;
	size_t	len;

	len = db_get_escape_string_len(src, max_bytes, max_chars, flag);

	dst = (char *)zbx_malloc(dst, len);

	db_escape_string(src, dst, len, flag);

	return dst;
}

char	*zbx_db_dyn_escape_string_len(const char *src, size_t length)
{
	return db_dyn_escape_string(src, ZBX_SIZE_T_MAX, length, ESCAPE_SEQUENCE_ON);
}

char	*zbx_db_dyn_escape_string(const char *src)
{
	return db_dyn_escape_string(src, ZBX_SIZE_T_MAX, ZBX_SIZE_T_MAX, ESCAPE_SEQUENCE_ON);
}

/******************************************************************************
 *                                                                            *
 * Return value: return length of escaped LIKE pattern with terminating '\0'  *
 *                                                                            *
 * Comments: sync changes with 'db_escape_like_pattern'                       *
 *                                                                            *
 ******************************************************************************/
static size_t	db_get_escape_like_pattern_len(const char *src)
{
	size_t		len;
	const char	*s;

	len = db_get_escape_string_len(src, ZBX_SIZE_T_MAX, ZBX_SIZE_T_MAX, ESCAPE_SEQUENCE_ON) - 1; /* minus '\0' */

	for (s = src; s && *s; s++)
	{
		len += (*s == '_' || *s == '%' || *s == ZBX_SQL_LIKE_ESCAPE_CHAR);
		len += 1;
	}

	len++; /* '\0' */

	return len;
}

/******************************************************************************
 *                                                                            *
 * Return value: escaped string to be used as pattern in LIKE                 *
 *                                                                            *
 * Comments: sync changes with 'db_get_escape_like_pattern_len'               *
 *                                                                            *
 *           For instance, we wish to find string a_b%c\d'e!f in our database *
 *           using '!' as escape character. Our queries then become:          *
 *                                                                            *
 *           ... LIKE 'a!_b!%c\\d\'e!!f' ESCAPE '!' (MySQL, PostgreSQL)       *
 *           ... LIKE 'a!_b!%c\d''e!!f' ESCAPE '!' (Oracle, SQLite3)          *
 *                                                                            *
 *           Using backslash as escape character in LIKE would be too much    *
 *           trouble, because escaping backslashes would have to be escaped   *
 *           as well, like so:                                                *
 *                                                                            *
 *           ... LIKE 'a\\_b\\%c\\\\d\'e!f' ESCAPE '\\' or                    *
 *           ... LIKE 'a\\_b\\%c\\\\d\\\'e!f' ESCAPE '\\' (MySQL, PostgreSQL) *
 *           ... LIKE 'a\_b\%c\\d''e!f' ESCAPE '\' (Oracle, SQLite3)          *
 *                                                                            *
 *           Hence '!' instead of backslash.                                  *
 *                                                                            *
 ******************************************************************************/
static void	db_escape_like_pattern(const char *src, char *dst, size_t len)
{
	char		*d;
	char		*tmp = NULL;
	const char	*t;

	assert(dst);

	tmp = (char *)zbx_malloc(tmp, len);

	db_escape_string(src, tmp, len, ESCAPE_SEQUENCE_ON);

	len--; /* '\0' */

	for (t = tmp, d = dst; t && *t && len; t++)
	{
		if (*t == '_' || *t == '%' || *t == ZBX_SQL_LIKE_ESCAPE_CHAR)
		{
			if (len <= 1)
				break;
			*d++ = ZBX_SQL_LIKE_ESCAPE_CHAR;
			len--;
		}
		*d++ = *t;
		len--;
	}

	*d = '\0';

	zbx_free(tmp);
}

/******************************************************************************
 *                                                                            *
 * Return value: escaped string to be used as pattern in LIKE                 *
 *                                                                            *
 ******************************************************************************/
char	*zbx_db_dyn_escape_like_pattern(const char *src)
{
	size_t	len;
	char	*dst = NULL;

	len = db_get_escape_like_pattern_len(src);

	dst = (char *)zbx_malloc(dst, len);

	db_escape_like_pattern(src, dst, len);

	return dst;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return the string length to fit into a database field of the      *
 *          specified size                                                    *
 *                                                                            *
 * Return value: the string length in bytes                                   *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_db_strlen_n(const char *text_loc, size_t maxlen)
{
	return zbx_strlen_utf8_nchars(text_loc, maxlen);
}

static zbx_db_table_t	*db_get_table(const char *tablename)
{
	zbx_db_table_t	*tables = zbx_dbschema_get_tables();

	for (int t = 0; NULL != tables[t].table; t++)
	{
		if (0 == strcmp(tables[t].table, tablename))
			return &tables[t];
	}

	return NULL;
}

static const zbx_db_field_t	*db_get_field(const zbx_db_table_t *table, const char *fieldname)
{
	int	f;

	for (f = 0; NULL != table->fields[f].name; f++)
	{
		if (0 == strcmp(table->fields[f].name, fieldname))
			return &table->fields[f];
	}

	return NULL;
}

const zbx_db_table_t	*zbx_db_get_table(const char *tablename)
{
	return db_get_table(tablename);
}

const zbx_db_field_t	*zbx_db_get_field(const zbx_db_table_t *table, const char *fieldname)
{
	return db_get_field(table, fieldname);
}

#ifdef HAVE_MYSQL
static size_t	get_string_field_size(const zbx_db_field_t *field)
{
	switch (field->type)
	{
		case ZBX_TYPE_BLOB:
		case ZBX_TYPE_LONGTEXT:
			return 4294967295ul;
		case ZBX_TYPE_CHAR:
		case ZBX_TYPE_TEXT:
			return 65535u;
		case ZBX_TYPE_CUID:
			return CUID_LEN - 1;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}
}
#endif

static size_t	get_string_field_chars(const zbx_db_field_t *field)
{
	if ((ZBX_TYPE_LONGTEXT == field->type || ZBX_TYPE_BLOB == field->type) && 0 == field->length)
		return ZBX_SIZE_T_MAX;
	else if (ZBX_TYPE_CUID == field->type)
		return CUID_LEN - 1;
	else
		return field->length;
}

int	zbx_db_validate_field_size(const char *tablename, const char *fieldname, const char *str)
{
	const zbx_db_table_t	*table;
	const zbx_db_field_t	*field;
	size_t			max_bytes, max_chars;

	if (NULL == (table = zbx_db_get_table(tablename)) || NULL == (field = zbx_db_get_field(table, fieldname)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid table: \"%s\" field: \"%s\"", tablename, fieldname);
		return FAIL;
	}

#if defined(HAVE_MYSQL) || defined(HAVE_ORACLE)
	max_bytes = get_string_field_size(field);
#else
	max_bytes = ZBX_SIZE_T_MAX;
#endif
	max_chars = get_string_field_chars(field);

	if (max_bytes < strlen(str))
		return FAIL;

	if (ZBX_SIZE_T_MAX == max_chars)
		return SUCCEED;

	if (max_chars != max_bytes && max_chars < zbx_strlen_utf8(str))
		return FAIL;

	return SUCCEED;
}

char	*db_dyn_escape_field_len(const zbx_db_field_t *field, const char *src, zbx_escape_sequence_t flag)
{
#if defined(HAVE_MYSQL)
	return db_dyn_escape_string(src, get_string_field_size(field), get_string_field_chars(field), flag);
#else
	return db_dyn_escape_string(src, ZBX_SIZE_T_MAX, get_string_field_chars(field), flag);
#endif
}

char	*zbx_db_dyn_escape_field(const char *table_name, const char *field_name, const char *src)
{
	const zbx_db_table_t	*table;
	const zbx_db_field_t	*field;

	if (NULL == (table = zbx_db_get_table(table_name)) || NULL == (field = zbx_db_get_field(table, field_name)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid table: \"%s\" field: \"%s\"", table_name, field_name);
		exit(EXIT_FAILURE);
	}

	return db_dyn_escape_field_len(field, src, ESCAPE_SEQUENCE_ON);
}

int	zbx_db_is_null(const char *field)
{
	if (NULL == field)
		return SUCCEED;

	return FAIL;
}

zbx_db_config_t	*zbx_db_config_create(void)
{
	zbx_db_config_t	*config;

	config = (zbx_db_config_t *)zbx_malloc(NULL, sizeof(zbx_db_config_t));
	memset(config, 0, sizeof(zbx_db_config_t));

	return config;
}

void	zbx_db_config_free(zbx_db_config_t *config)
{
	zbx_free(config->dbhost);
	zbx_free(config->dbname);
	zbx_free(config->dbschema);
	zbx_free(config->dbuser);
	zbx_free(config->dbpassword);
	zbx_free(config->dbsocket);
	zbx_free(config->db_tls_connect);
	zbx_free(config->db_tls_cert_file);
	zbx_free(config->db_tls_key_file);
	zbx_free(config->db_tls_ca_file);
	zbx_free(config->db_tls_cipher);
	zbx_free(config->db_tls_cipher_13);

	zbx_free(config);
}

/******************************************************************************
 *                                                                            *
 * Return value: validate database configuration parameters depending on      *
 *               component type (server/proxy)                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_config_validate_features(zbx_db_config_t *config, unsigned char program_type)
{
	int	err = 0;

#if !(defined(HAVE_MYSQL_TLS) || defined(HAVE_MARIADB_TLS) || defined(HAVE_POSTGRESQL))
	err |= (FAIL == zbx_check_cfg_feature_str("DBTLSConnect", config->db_tls_connect,
			"PostgreSQL or MySQL library version that support TLS"));
	err |= (FAIL == zbx_check_cfg_feature_str("DBTLSCAFile", config->db_tls_ca_file,
			"PostgreSQL or MySQL library version that support TLS"));
	err |= (FAIL == zbx_check_cfg_feature_str("DBTLSCertFile", config->db_tls_cert_file,
			"PostgreSQL or MySQL library version that support TLS"));
	err |= (FAIL == zbx_check_cfg_feature_str("DBTLSKeyFile", config->db_tls_key_file,
			"PostgreSQL or MySQL library version that support TLS"));
#endif

#if !(defined(HAVE_MYSQL_TLS) || defined(HAVE_POSTGRESQL))
	if (NULL != config->db_tls_connect && 0 == strcmp(config->db_tls_connect,
			ZBX_DB_TLS_CONNECT_VERIFY_CA_TXT))
	{
		zbx_error("\"DBTLSConnect\" configuration parameter value '%s' cannot be used: Zabbix %s was compiled"
			" without PostgreSQL or MySQL library version that support this value",
			ZBX_DB_TLS_CONNECT_VERIFY_CA_TXT, get_program_type_string(program_type));
		err |= 1;
	}
#else
	ZBX_UNUSED(program_type);
	ZBX_UNUSED(config);
#endif

#if !(defined(HAVE_MYSQL_TLS) || defined(HAVE_MARIADB_TLS))
	err |= (FAIL == zbx_check_cfg_feature_str("DBTLSCipher", config->db_tls_cipher,
			"MySQL library version that support configuration of cipher"));
#endif

#if !defined(HAVE_MYSQL_TLS_CIPHERSUITES)
	err |= (FAIL == zbx_check_cfg_feature_str("DBTLSCipher13", config->db_tls_cipher_13,
			"MySQL library version that support configuration of TLSv1.3 ciphersuites"));
#endif

	return 0 != err ? FAIL : SUCCEED;
}

#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
static void	check_cfg_empty_str(const char *parameter, const char *value)
{
	if (NULL != value && 0 == strlen(value))
	{
		zabbix_log(LOG_LEVEL_CRIT, "configuration parameter \"%s\" is defined but empty", parameter);
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Return value: validate database configuration parameters                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_config_validate(zbx_db_config_t *config)
{
	check_cfg_empty_str("DBTLSConnect", config->db_tls_connect);
	check_cfg_empty_str("DBTLSCertFile", config->db_tls_cert_file);
	check_cfg_empty_str("DBTLSKeyFile", config->db_tls_key_file);
	check_cfg_empty_str("DBTLSCAFile", config->db_tls_ca_file);
	check_cfg_empty_str("DBTLSCipher", config->db_tls_cipher);
	check_cfg_empty_str("DBTLSCipher13", config->db_tls_cipher_13);

	if (NULL != config->db_tls_connect &&
			0 != strcmp(config->db_tls_connect, ZBX_DB_TLS_CONNECT_REQUIRED_TXT) &&
			0 != strcmp(config->db_tls_connect, ZBX_DB_TLS_CONNECT_VERIFY_CA_TXT) &&
			0 != strcmp(config->db_tls_connect, ZBX_DB_TLS_CONNECT_VERIFY_FULL_TXT))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid \"DBTLSConnect\" configuration parameter: '%s'",
				config->db_tls_connect);
		exit(EXIT_FAILURE);
	}

	if (NULL != config->db_tls_connect &&
			(0 == strcmp(ZBX_DB_TLS_CONNECT_VERIFY_CA_TXT, config->db_tls_connect) ||
			0 == strcmp(ZBX_DB_TLS_CONNECT_VERIFY_FULL_TXT, config->db_tls_connect)) &&
			NULL == config->db_tls_ca_file)
	{
		zabbix_log(LOG_LEVEL_CRIT, "parameter \"DBTLSConnect\" value \"%s\" requires \"DBTLSCAFile\", but it"
				" is not defined", config->db_tls_connect);
		exit(EXIT_FAILURE);
	}

	if ((NULL != config->db_tls_cert_file || NULL != config->db_tls_key_file) &&
			(NULL == config->db_tls_cert_file ||
			NULL == config->db_tls_key_file || NULL == config->db_tls_ca_file))
	{
		zabbix_log(LOG_LEVEL_CRIT, "parameter \"DBTLSKeyFile\" or \"DBTLSCertFile\" is defined, but"
				" \"DBTLSKeyFile\", \"DBTLSCertFile\" or \"DBTLSCAFile\" is not defined");
		exit(EXIT_FAILURE);
	}
}
#endif

#ifdef HAVE_POSTGRESQL
/******************************************************************************
 *                                                                            *
 * Purpose: retrieves TimescaleDB (TSDB) license information                  *
 *                                                                            *
 * Return value: license information from datase as string                    *
 *               "apache"    for TimescaleDB Apache 2 Edition                 *
 *               "timescale" for TimescaleDB Community Edition                *
 *                                                                            *
 * Comments: returns a pointer to allocated memory                            *
 *                                                                            *
 ******************************************************************************/
static char	*dbconn_tsdb_get_license(zbx_dbconn_t *db)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*tsdb_lic = NULL;

	result = zbx_dbconn_select(db, "show timescaledb.license");

	if ((zbx_db_result_t)ZBX_DB_DOWN != result && NULL != result && NULL != (row = zbx_db_fetch(result)))
	{
		tsdb_lic = zbx_strdup(NULL, row[0]);
	}

	zbx_db_free_result(result);

	return tsdb_lic;
}
#endif

int	zbx_dbconn_check_extension(zbx_dbconn_t *db, struct zbx_db_version_info_t *info, int allow_unsupported)
{
#ifdef HAVE_POSTGRESQL
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*tsdb_lic = NULL;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* in case of major upgrade, db_extension may be missing */
	if (FAIL == zbx_db_field_exists("config", "db_extension"))
		goto out;

	if (NULL == (result = zbx_dbconn_select(db, "select db_extension from config")))
		goto out;

	if (NULL == (row = zbx_db_fetch(result)) || '\0' == *row[0])
	{
		zbx_db_free_result(result);
		goto out;
	}

	info->extension = zbx_strdup(NULL, row[0]);

	zbx_db_free_result(result);

	if (0 != zbx_strcmp_null(info->extension, ZBX_DB_EXTENSION_TIMESCALEDB))
		goto out;

	/* at this point we know the TimescaleDB extension is enabled in Zabbix */

	zbx_tsdb_info_extract(info);

	if (DB_VERSION_FAILED_TO_RETRIEVE == info->ext_flag)
	{
		info->ext_err_code = ZBX_TIMESCALEDB_VERSION_FAILED_TO_RETRIEVE;
		ret = FAIL;
		goto out;
	}

	if (DB_VERSION_LOWER_THAN_MINIMUM == info->ext_flag)
	{
		zabbix_log(LOG_LEVEL_WARNING, "TimescaleDB version must be at least %d. Recommended version is at least"
				" %s %s.", ZBX_TIMESCALE_MIN_VERSION, ZBX_TIMESCALE_LICENSE_COMMUNITY_STR,
				ZBX_TIMESCALE_MIN_SUPPORTED_VERSION_STR);
		info->ext_err_code = ZBX_TIMESCALEDB_VERSION_LOWER_THAN_MINIMUM;
		ret = FAIL;
		goto out;
	}

	if (DB_VERSION_NOT_SUPPORTED_ERROR == info->ext_flag)
	{
		zabbix_log(LOG_LEVEL_WARNING, "TimescaleDB version %u is not officially supported. Recommended version"
				" is at least %s %s.", info->ext_current_version,
				ZBX_TIMESCALE_LICENSE_COMMUNITY_STR, ZBX_TIMESCALE_MIN_SUPPORTED_VERSION_STR);
		info->ext_err_code = ZBX_TIMESCALEDB_VERSION_NOT_SUPPORTED;

		if (0 == allow_unsupported)
		{
			ret = FAIL;
			goto out;
		}

		info->ext_flag = DB_VERSION_NOT_SUPPORTED_WARNING;
	}

	if (DB_VERSION_HIGHER_THAN_MAXIMUM == info->ext_flag)
	{
		zabbix_log(LOG_LEVEL_WARNING, "TimescaleDB version is too new. Recommended version is up to %s %s.",
				ZBX_TIMESCALE_LICENSE_COMMUNITY_STR, ZBX_TIMESCALE_MAX_VERSION_STR);
		info->ext_err_code = ZBX_TIMESCALEDB_VERSION_HIGHER_THAN_MAXIMUM;

		if (0 == allow_unsupported)
		{
			info->ext_flag = DB_VERSION_HIGHER_THAN_MAXIMUM_ERROR;
			ret = FAIL;
			goto out;
		}

		info->ext_flag = DB_VERSION_HIGHER_THAN_MAXIMUM_WARNING;
	}

	tsdb_lic = dbconn_tsdb_get_license(db);

	zbx_tsdb_extract_compressed_chunk_flags(info);

	zabbix_log(LOG_LEVEL_DEBUG, "TimescaleDB license: [%s]", ZBX_NULL2EMPTY_STR(tsdb_lic));

	if (0 != zbx_strcmp_null(tsdb_lic, ZBX_TIMESCALE_LICENSE_COMMUNITY))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Detected license [%s] does not support compression. Compression is"
				" supported in %s.", ZBX_NULL2EMPTY_STR(tsdb_lic),
				ZBX_TIMESCALE_LICENSE_COMMUNITY_STR);
		info->ext_err_code = ZBX_TIMESCALEDB_LICENSE_NOT_COMMUNITY;
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s was detected. TimescaleDB compression is supported.",
			ZBX_TIMESCALE_LICENSE_COMMUNITY_STR);

	if (ZBX_EXT_ERR_UNDEFINED == info->ext_err_code)
		info->ext_err_code = ZBX_EXT_SUCCEED;

	zbx_tsdb_set_compression_availability(ON);
out:
	zbx_free(tsdb_lic);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#else
	ZBX_UNUSED(db);
	ZBX_UNUSED(info);
	ZBX_UNUSED(allow_unsupported);

	return SUCCEED;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: locks a record in a table by its primary key and an optional      *
 *          constraint field                                                  *
 *                                                                            *
 * Parameters: db        - [IN] database connection                           *
 *             table     - [IN] the target table                              *
 *             id        - [IN] primary key value                             *
 *             add_field - [IN] additional constraint field name (optional)   *
 *             add_id    - [IN] constraint field value                        *
 *                                                                            *
 * Return value: SUCCEED - the record was successfully locked                 *
 *               FAIL    - the table does not contain the specified record    *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_lock_record(zbx_dbconn_t *db, const char *table, zbx_uint64_t id, const char *add_field,
		zbx_uint64_t add_id)
{
	zbx_db_result_t		result;
	const zbx_db_table_t	*t;
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == db->txn_level)
		zabbix_log(LOG_LEVEL_DEBUG, "%s() called outside of transaction", __func__);

	t = zbx_db_get_table(table);

	if (NULL == add_field)
	{
		result = zbx_dbconn_select(db, "select null from %s where %s=" ZBX_FS_UI64 ZBX_FOR_UPDATE, table,
				t->recid, id);
	}
	else
	{
		result = zbx_dbconn_select(db, "select null from %s where %s=" ZBX_FS_UI64 " and %s=" ZBX_FS_UI64
				ZBX_FOR_UPDATE, table, t->recid, id, add_field, add_id);
	}

	if (NULL == zbx_db_fetch(result))
		ret = FAIL;
	else
		ret = SUCCEED;

	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: locks a records in a table by its primary key                     *
 *                                                                            *
 * Parameters: db        - [IN] database connection                           *
 *             table     - [IN] the target table                              *
 *             ids       - [IN] primary key values                            *
 *                                                                            *
 * Return value: SUCCEED - one or more of the specified records were          *
 *                         successfully locked                                *
 *               FAIL    - the table does not contain any of the specified    *
 *                         records or 'table' name not found                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_lock_records(zbx_dbconn_t *db, const char *table, const zbx_vector_uint64_t *ids)
{
	zbx_db_result_t		result;
	const zbx_db_table_t	*t;
	int			ret;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == db->txn_level)
		zabbix_log(LOG_LEVEL_DEBUG, "%s() called outside of transaction", __func__);

	if (NULL == (t = zbx_db_get_table(table)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s(): cannot find table '%s'", __func__, table);
		THIS_SHOULD_NEVER_HAPPEN;
		ret = FAIL;
		goto out;
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select null from %s where", table);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, t->recid, ids->values, ids->values_num);

	result = zbx_dbconn_select(db, "%s" ZBX_FOR_UPDATE, sql);

	zbx_free(sql);

	if (NULL == zbx_db_fetch(result))
		ret = FAIL;
	else
		ret = SUCCEED;

	zbx_db_free_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: locks a records in a table by field name                          *
 *                                                                            *
 * Parameters: table      - [IN] the target table                             *
 *             field_name - [IN] field name                                   *
 *             ids        - [IN/OUT] IN - sorted array of IDs to lock         *
 *                                   OUT - resulting array of locked IDs      *
 *                                                                            *
 * Return value: SUCCEED - one or more of the specified records were          *
 *                         successfully locked                                *
 *               FAIL    - no records were locked                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_lock_ids(zbx_dbconn_t *db, const char *table_name, const char *field_name, zbx_vector_uint64_t *ids)
{
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t	id;
	int		i;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	if (0 == ids->values_num)
		return FAIL;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select %s from %s where", field_name, table_name);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, field_name, ids->values, ids->values_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " order by %s" ZBX_FOR_UPDATE, field_name);
	result = zbx_dbconn_select(db, "%s", sql);
	zbx_free(sql);

	for (i = 0; NULL != (row = zbx_db_fetch(result)); i++)
	{
		ZBX_STR2UINT64(id, row[0]);

		while (id != ids->values[i])
			zbx_vector_uint64_remove(ids, i);
	}
	zbx_db_free_result(result);

	while (i != ids->values_num)
		zbx_vector_uint64_remove_noorder(ids, i);

	return (0 != ids->values_num ? SUCCEED : FAIL);
}

#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
#define MAX_EXPRESSIONS	1000	/* tune according to batch size to avoid unnecessary or conditions */
#else
#define MAX_EXPRESSIONS	950
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: Takes an initial part of SQL query and appends a generated        *
 *          WHERE condition. The WHERE condition is generated from the given  *
 *          list of values as a mix of <fieldname> BETWEEN <id1> AND <idN>"   *
 *          and "<fieldname> IN (<id1>,<id2>,...,<idN>)" elements.            *
 *                                                                            *
 * Parameters: sql        - [IN/OUT] buffer for SQL query construction        *
 *             sql_alloc  - [IN/OUT] size of the 'sql' buffer                 *
 *             sql_offset - [IN/OUT] current position in the 'sql' buffer     *
 *             fieldname  - [IN] field name to be used in SQL WHERE condition *
 *             values     - [IN] array of numerical values sorted in          *
 *                               ascending order to be included in WHERE      *
 *             num        - [IN] number of elements in 'values' array         *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_add_condition_alloc(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *fieldname,
		const zbx_uint64_t *values, const int num)
{
	int		i, in_cnt;
#if defined(HAVE_SQLITE3)
	int		expr_num, expr_cnt = 0;
#endif
	if (0 == num)
		return;

	zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ' ');
	if (MAX_EXPRESSIONS < num)
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '(');

#if defined(HAVE_SQLITE3)
	expr_num = (num + MAX_EXPRESSIONS - 1) / MAX_EXPRESSIONS;

	if (MAX_EXPRESSIONS < expr_num)
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '(');
#endif

	if (1 < num)
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s in (", fieldname);

	/* compose "in"s */
	for (i = 0, in_cnt = 0; i < num; i++)
	{
		if (1 == num)
		{
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s=" ZBX_FS_UI64, fieldname,
					values[i]);
			break;
		}
		else
		{
			if (MAX_EXPRESSIONS == in_cnt)
			{
				in_cnt = 0;
				(*sql_offset)--;
#if defined(HAVE_SQLITE3)
				if (MAX_EXPRESSIONS == ++expr_cnt)
				{
					zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ")) or (%s in (",
							fieldname);
					expr_cnt = 0;
				}
				else
				{
#endif
					zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ") or %s in (",
							fieldname);
#if defined(HAVE_SQLITE3)
				}
#endif
			}

			in_cnt++;
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ZBX_FS_UI64 ",",
					values[i]);
		}
	}

	if (1 < num)
	{
		(*sql_offset)--;
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');
	}

#if defined(HAVE_SQLITE3)
	if (MAX_EXPRESSIONS < expr_num)
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');
#endif
	if (MAX_EXPRESSIONS < num)
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');

#undef MAX_EXPRESSIONS
}

/*********************************************************************************
 *                                                                               *
 * Purpose: This function is similar to the zbx_db_add_condition_alloc(), except *
 *          it is designed for generating WHERE conditions for strings. Hence,   *
 *          this function is simpler, because only IN condition is possible.     *
 *                                                                               *
 * Parameters: sql        - [IN/OUT] buffer for SQL query construction           *
 *             sql_alloc  - [IN/OUT] size of the 'sql' buffer                    *
 *             sql_offset - [IN/OUT] current position in the 'sql' buffer        *
 *             fieldname  - [IN] field name to be used in SQL WHERE condition    *
 *             values     - [IN] array of string values                          *
 *             num        - [IN] number of elements in 'values' array            *
 *                                                                               *
 *                                                                               *
 *********************************************************************************/
void	zbx_db_add_str_condition_alloc(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *fieldname,
		const char * const *values, const int num)
{
#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
#define MAX_EXPRESSIONS	1000	/* tune according to batch size to avoid unnecessary or conditions */
#else
#define MAX_EXPRESSIONS	950
#endif

	int	i, cnt = 0;
	char	*value_esc;
	int	values_num = 0, empty_num = 0;

	if (0 == num)
		return;

	zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ' ');

	for (i = 0; i < num; i++)
	{
		if ('\0' == *values[i])
			empty_num++;
		else
			values_num++;
	}

	if (MAX_EXPRESSIONS < values_num || (0 != values_num && 0 != empty_num))
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '(');

	if (0 != empty_num)
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s" ZBX_SQL_STRCMP, fieldname, ZBX_SQL_STRVAL_EQ(""));

		if (0 == values_num)
			return;

		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " or ");
	}

	if (1 == values_num)
	{
		for (i = 0; i < num; i++)
		{
			if ('\0' == *values[i])
				continue;

			value_esc = zbx_db_dyn_escape_string(values[i]);
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s='%s'", fieldname, value_esc);
			zbx_free(value_esc);
		}

		if (0 != empty_num)
			zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');
		return;
	}

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, fieldname);
	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " in (");

	for (i = 0; i < num; i++)
	{
		if ('\0' == *values[i])
			continue;

		if (MAX_EXPRESSIONS == cnt)
		{
			cnt = 0;
			(*sql_offset)--;
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ") or ");
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, fieldname);
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " in (");
		}

		value_esc = zbx_db_dyn_escape_string(values[i]);
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '\'');
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, value_esc);
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "',");
		zbx_free(value_esc);

		cnt++;
	}

	(*sql_offset)--;
	zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');

	if (MAX_EXPRESSIONS < values_num || 0 != empty_num)
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');

#undef MAX_EXPRESSIONS
}

/******************************************************************************
 *                                                                            *
 * Purpose: construct insert statement                                        *
 *                                                                            *
 * Return value: "<id>" if id not equal zero,                                 *
 *               otherwise "null"                                             *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_db_sql_id_ins(zbx_uint64_t id)
{
	static unsigned char	n = 0;
	static char		buf[4][21];	/* 20 - value size, 1 - '\0' */
	static const char	null[5] = "null";

	if (0 == id)
		return null;

	n = (n + 1) & 3;

	zbx_snprintf(buf[n], sizeof(buf[n]), ZBX_FS_UI64, id);

	return buf[n];
}

/******************************************************************************
 *                                                                            *
 * Purpose: construct where condition                                         *
 *                                                                            *
 * Return value: "=<id>" if id not equal zero,                                *
 *               otherwise " is null"                                         *
 *                                                                            *
 * Comments: NB! Do not use this function more than once in same SQL query    *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_db_sql_id_cmp(zbx_uint64_t id)
{
	static char		buf[22];	/* 1 - '=', 20 - value size, 1 - '\0' */
	static const char	is_null[9] = " is null";

	if (0 == id)
		return is_null;

	zbx_snprintf(buf, sizeof(buf), "=" ZBX_FS_UI64, id);

	return buf;
}
