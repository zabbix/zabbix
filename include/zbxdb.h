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

#ifndef ZABBIX_ZBXDB_H
#define ZABBIX_ZBXDB_H

#include "zbxcommon.h"
#include "zbxjson.h"

#define ZBX_DB_OK	0
#define ZBX_DB_FAIL	-1
#define ZBX_DB_DOWN	-2

#define ZBX_DB_TLS_CONNECT_REQUIRED_TXT		"required"
#define ZBX_DB_TLS_CONNECT_VERIFY_CA_TXT	"verify_ca"
#define ZBX_DB_TLS_CONNECT_VERIFY_FULL_TXT	"verify_full"

typedef char	**DB_ROW;
typedef struct zbx_db_result	*DB_RESULT;

/* database field value */
typedef union
{
	int		i32;
	zbx_uint64_t	ui64;
	double		dbl;
	char		*str;
}
zbx_db_value_t;

typedef struct
{
	char	*config_dbhost;
	char	*config_dbname;
	char	*config_dbschema;
	char	*config_dbuser;
	char	*config_dbpassword;
	char	*config_dbsocket;
	char	*config_db_tls_connect;
	char	*config_db_tls_cert_file;
	char	*config_db_tls_key_file;
	char	*config_db_tls_ca_file;
	char	*config_db_tls_cipher;
	char	*config_db_tls_cipher_13;
	int	config_dbport;
}
zbx_config_dbhigh_t;

#ifdef HAVE_SQLITE3
	/* we have to put double % here for sprintf */
#	define ZBX_SQL_MOD(x, y) #x "%%" #y
#else
#	define ZBX_SQL_MOD(x, y) "mod(" #x "," #y ")"
#endif

#ifdef HAVE_SQLITE3
#	define ZBX_FOR_UPDATE	""	/* SQLite3 does not support "select ... for update" */
#else
#	define ZBX_FOR_UPDATE	" for update"
#endif

int	zbx_db_init_basic(const char *dbname, const char *const dbschema, char **error);
void	zbx_db_deinit_basic(void);

void	zbx_db_init_autoincrement_options_basic(void);

int	zbx_db_connect_basic(const zbx_config_dbhigh_t *config_dbhigh);
void	zbx_db_close_basic(void);

int	zbx_db_begin_basic(void);
int	zbx_db_commit_basic(void);
int	zbx_db_rollback_basic(void);
int	zbx_db_txn_level(void);
int	zbx_db_txn_error(void);
int	zbx_db_txn_end_error(void);
const char	*zbx_db_last_strerr(void);

typedef enum
{
	ERR_Z3001 = 3001,
	ERR_Z3002,
	ERR_Z3003,
	ERR_Z3004,
	ERR_Z3005,
	ERR_Z3006,
	ERR_Z3007,
	ERR_Z3008
}
zbx_err_codes_t;

zbx_err_codes_t	zbx_db_last_errcode(void);

#ifdef HAVE_POSTGRESQL
int	zbx_tsdb_get_version(void);
#define ZBX_DB_TSDB_V1	(20000 > zbx_tsdb_get_version())
#endif

#ifdef HAVE_ORACLE

/* context for dynamic parameter binding */
typedef struct
{
	/* the parameter position, starting with 0 */
	int			position;
	/* the parameter type (ZBX_TYPE_* ) */
	unsigned char		type;
	/* the maximum parameter size */
	size_t			size_max;
	/* the data to bind - array of rows, each row being an array of columns */
	zbx_db_value_t		**rows;
	/* custom data, depending on column type */
	void			*data;
}
zbx_db_bind_context_t;

int		zbx_db_statement_prepare_basic(const char *sql);
int		zbx_db_bind_parameter_dyn(zbx_db_bind_context_t *context, int position, unsigned char type,
				zbx_db_value_t **rows, int rows_num);
void		zbx_db_clean_bind_context(zbx_db_bind_context_t *context);
int		zbx_db_statement_execute(int iters);
#endif
int		zbx_db_vexecute(const char *fmt, va_list args);
DB_RESULT	zbx_db_vselect(const char *fmt, va_list args);
DB_RESULT	zbx_db_select_n_basic(const char *query, int n);

DB_ROW		zbx_db_fetch_basic(DB_RESULT result);
void		zbx_db_free_result(DB_RESULT result);
int		zbx_db_is_null_basic(const char *field);

typedef enum
{
	ESCAPE_SEQUENCE_OFF,
	ESCAPE_SEQUENCE_ON
}
zbx_escape_sequence_t;
char		*zbx_db_dyn_escape_string_basic(const char *src, size_t max_bytes, size_t max_chars,
		zbx_escape_sequence_t flag);
#define ZBX_SQL_LIKE_ESCAPE_CHAR '!'
char		*zbx_db_dyn_escape_like_pattern_basic(const char *src);

int		zbx_db_strlen_n(const char *text_loc, size_t maxlen);

#define ZBX_DB_EXTENSION_TIMESCALEDB	"timescaledb"

#if defined(HAVE_POSTGRESQL)
#	define ZBX_SUPPORTED_DB_CHARACTER_SET	"utf8"
#elif defined(HAVE_ORACLE)
#	define ZBX_ORACLE_UTF8_CHARSET "AL32UTF8"
#	define ZBX_ORACLE_CESU8_CHARSET "UTF8"
#elif defined(HAVE_MYSQL)
#	define ZBX_DB_STRLIST_DELIM		','
#	define ZBX_SUPPORTED_DB_CHARACTER_SET_UTF8	"utf8"
#	define ZBX_SUPPORTED_DB_CHARACTER_SET_UTF8MB3	"utf8mb3"
#	define ZBX_SUPPORTED_DB_CHARACTER_SET_UTF8MB4 	"utf8mb4"
#	define ZBX_SUPPORTED_DB_CHARACTER_SET		ZBX_SUPPORTED_DB_CHARACTER_SET_UTF8 ","\
							ZBX_SUPPORTED_DB_CHARACTER_SET_UTF8MB3 ","\
							ZBX_SUPPORTED_DB_CHARACTER_SET_UTF8MB4
#	define ZBX_SUPPORTED_DB_COLLATION		"utf8_bin,utf8mb3_bin,utf8mb4_bin"
#endif

typedef enum
{	/* db version status flags shared with FRONTEND */
	DB_VERSION_SUPPORTED,
	DB_VERSION_LOWER_THAN_MINIMUM,
	DB_VERSION_HIGHER_THAN_MAXIMUM,
	DB_VERSION_FAILED_TO_RETRIEVE,
	DB_VERSION_NOT_SUPPORTED_ERROR,
	DB_VERSION_NOT_SUPPORTED_WARNING,
	DB_VERSION_HIGHER_THAN_MAXIMUM_ERROR,
	DB_VERSION_HIGHER_THAN_MAXIMUM_WARNING
}
zbx_db_version_status_t;

typedef enum
{	/* db extension error codes shared with FRONTEND */
	ZBX_EXT_ERR_UNDEFINED = 0,
	ZBX_EXT_SUCCEED = 1,
	ZBX_TIMESCALEDB_POSTGRES_TOO_OLD,
	ZBX_TIMESCALEDB_VERSION_FAILED_TO_RETRIEVE,
	ZBX_TIMESCALEDB_VERSION_LOWER_THAN_MINIMUM,
	ZBX_TIMESCALEDB_VERSION_NOT_SUPPORTED,
	ZBX_TIMESCALEDB_VERSION_HIGHER_THAN_MAXIMUM,
	ZBX_TIMESCALEDB_LICENSE_NOT_COMMUNITY
}
zbx_db_ext_err_code_t;

struct zbx_db_version_info_t
{
	/* information about database server */

	const char		*database;

	zbx_uint32_t		current_version;
	zbx_uint32_t		min_version;
	zbx_uint32_t		max_version;
	zbx_uint32_t		min_supported_version;

	char			*friendly_current_version;
	const char		*friendly_min_version;
	const char		*friendly_max_version;
	const char		*friendly_min_supported_version;

	zbx_db_version_status_t	flag;

	int			history_pk;

	/* information about database server extension */

	char			*extension;

	zbx_uint32_t		ext_current_version;
	zbx_uint32_t		ext_min_version;
	zbx_uint32_t		ext_max_version;
	zbx_uint32_t		ext_min_supported_version;

	char			*ext_friendly_current_version;
	const char		*ext_friendly_min_version;
	const char		*ext_friendly_max_version;
	const char		*ext_friendly_min_supported_version;

	zbx_db_version_status_t	ext_flag;

	char			*ext_lic;
	zbx_db_ext_err_code_t	ext_err_code;

	int			history_compressed_chunks;
	int			trends_compressed_chunks;
#ifdef HAVE_ORACLE
	struct zbx_json		tables_json;
#endif
};

void	zbx_dbms_version_info_extract(struct zbx_db_version_info_t *version_info);
#ifdef HAVE_POSTGRESQL
void	zbx_tsdb_info_extract(struct zbx_db_version_info_t *version_info);
void	zbx_tsdb_set_compression_availability(int compression_availabile);
int	zbx_tsdb_get_compression_availability(void);
void	zbx_tsdb_extract_compressed_chunk_flags(struct zbx_db_version_info_t *version_info);
#endif

int	zbx_db_version_check(const char *database, zbx_uint32_t current_version, zbx_uint32_t min_version,
		zbx_uint32_t max_version, zbx_uint32_t min_supported_version);
void	zbx_db_version_json_create(struct zbx_json *json, struct zbx_db_version_info_t *info);

#if defined(HAVE_MYSQL)
#	define ZBX_DB_TIMESTAMP()	"unix_timestamp()"
#	define ZBX_DB_CHAR_LENGTH(str)	"char_length(" #str ")"
#elif defined(HAVE_POSTGRESQL)
#	define ZBX_DB_TIMESTAMP()	"cast(extract(epoch from now()) as int)"
#	define ZBX_DB_CHAR_LENGTH(str)	"char_length(" #str ")"
#elif defined(HAVE_ORACLE)
#	define ZBX_DB_TIMESTAMP()	"(cast(sys_extract_utc(systimestamp) as date) - date'1970-01-01') * 86400"
#	define ZBX_DB_CHAR_LENGTH(str)	"length(" #str ")"
#else
#	define ZBX_DB_TIMESTAMP()	"cast(strftime('%s', 'now') as integer)"
#	define ZBX_DB_CHAR_LENGTH(str)	"length(" #str ")"
#endif

#endif
