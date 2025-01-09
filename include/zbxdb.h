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

#ifndef ZABBIX_ZBXDB_H
#define ZABBIX_ZBXDB_H

#include "zbxcommon.h"
#include "zbxjson.h"
#include "zbxdbschema.h"

#define ZBX_DB_OK	0
#define ZBX_DB_FAIL	-1
#define ZBX_DB_DOWN	-2

#define ZBX_DB_TLS_CONNECT_REQUIRED_TXT		"required"
#define ZBX_DB_TLS_CONNECT_VERIFY_CA_TXT	"verify_ca"
#define ZBX_DB_TLS_CONNECT_VERIFY_FULL_TXT	"verify_full"

typedef char	**zbx_db_row_t;
typedef struct zbx_db_result	*zbx_db_result_t;

typedef struct zbx_dbconn zbx_dbconn_t;

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
	char		*dbhost;
	char		*dbname;
	char		*dbschema;
	char		*dbuser;
	char		*dbpassword;
	char		*dbsocket;
	char		*db_tls_connect;
	char		*db_tls_cert_file;
	char		*db_tls_key_file;
	char		*db_tls_ca_file;
	char		*db_tls_cipher;
	char		*db_tls_cipher_13;
	unsigned int	dbport;
	int		log_slow_queries;
	int		read_only_recoverable;
}
zbx_db_config_t;

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

#ifdef HAVE_MYSQL
#	define	ZBX_SQL_STRCMP			"%s binary '%s'"
#else
#	define	ZBX_SQL_STRCMP			"%s'%s'"
#endif
#define	ZBX_SQL_STRVAL_EQ(str)			"=", str
#define	ZBX_SQL_STRVAL_NE(str)			"<>", str

#ifdef HAVE_MYSQL
#	define ZBX_SQL_CONCAT()		"concat(%s,%s)"
#else
#	define ZBX_SQL_CONCAT()		"%s||%s"
#endif

#define ZBX_SQL_NULLCMP(f1, f2)	"((" f1 " is null and " f2 " is null) or " f1 "=" f2 ")"

#define ZBX_DBROW2UINT64(uint, row)			\
	do {						\
		if (SUCCEED == zbx_db_is_null(row))	\
			uint = 0;			\
		else					\
			zbx_is_uint64(row, &uint);	\
	}						\
	while (0)

#ifdef HAVE_MYSQL
#	define ZBX_SQL_SORT_ASC(field)	field " asc"
#	define ZBX_SQL_SORT_DESC(field)	field " desc"
#else
#	define ZBX_SQL_SORT_ASC(field)	field " asc nulls first"
#	define ZBX_SQL_SORT_DESC(field)	field " desc nulls last"
#endif

#define ZBX_DB_MAX_ID	(zbx_uint64_t)__UINT64_C(0x7fffffffffffffff)

int	zbx_db_init(char **error);
void	zbx_db_deinit(void);

typedef enum
{
	ERR_Z3001 = 3001,
	ERR_Z3002,
	ERR_Z3003,
	ERR_Z3004,
	ERR_Z3005,
	ERR_Z3006,
	ERR_Z3007,
	ERR_Z3008,
	ERR_Z3009
}
zbx_err_codes_t;

#ifdef HAVE_POSTGRESQL
int	zbx_tsdb_get_version(void);
#endif

#if defined (HAVE_MYSQL)
void	zbx_mysql_escape_bin(const char *src, char *dst, size_t size);
#elif defined(HAVE_POSTGRESQL)
void	zbx_postgresql_escape_bin(const char *src, char **dst, size_t size);
#endif

typedef enum
{
	ESCAPE_SEQUENCE_OFF,
	ESCAPE_SEQUENCE_ON
}
zbx_escape_sequence_t;

#define ZBX_SQL_LIKE_ESCAPE_CHAR '!'
char		*zbx_db_dyn_escape_like_pattern(const char *src);

size_t		zbx_db_strlen_n(const char *text_loc, size_t maxlen);

#define ZBX_DB_EXTENSION_TIMESCALEDB	"timescaledb"

#if defined(HAVE_POSTGRESQL)
#	define ZBX_SUPPORTED_DB_CHARACTER_SET	"utf8"
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
	/* ZBX_TIMESCALEDB_POSTGRES_TOO_OLD, obsoleted since Zabbix 7.0 */
	ZBX_TIMESCALEDB_VERSION_FAILED_TO_RETRIEVE = 3,
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

	zbx_db_ext_err_code_t	ext_err_code;

	int			history_compressed_chunks;
	int			trends_compressed_chunks;
};

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
#else
#	define ZBX_DB_TIMESTAMP()	"cast(strftime('%s', 'now') as integer)"
#	define ZBX_DB_CHAR_LENGTH(str)	"length(" #str ")"
#endif

#define ZBX_DB_CONNECT_NORMAL	0
#define ZBX_DB_CONNECT_EXIT	1
#define ZBX_DB_CONNECT_ONCE	2

ZBX_CONST_PTR_VECTOR_DECL(const_db_field_ptr, const zbx_db_field_t *)
ZBX_PTR_VECTOR_DECL(db_value_ptr, zbx_db_value_t *)

typedef struct
{
	/* database connection */
	zbx_dbconn_t			*db;
	/* the target table */
	const zbx_db_table_t		*table;
	/* the fields to insert (pointers to the zbx_db_field_t structures from database schema) */
	zbx_vector_const_db_field_ptr_t	fields;
	/* the values rows to insert (pointers to arrays of zbx_db_value_t structures) */
	zbx_vector_db_value_ptr_t	rows;
	/* index of autoincrement field */
	int				autoincrement;
	/* number of rows to cache before flushing (inserting), 0 - no limit */
	int				batch_size;
	/* the last id assigned by autoincrement */
	zbx_uint64_t			lastid;
}
zbx_db_insert_t;

void	zbx_init_library_db(zbx_db_config_t *config);
void	zbx_deinit_library_db(zbx_db_config_t *config);

zbx_dbconn_t	*zbx_dbconn_create(void);
void	zbx_dbconn_free(zbx_dbconn_t *db);

int	zbx_dbconn_set_connect_options(zbx_dbconn_t *db, int options);
void	zbx_dbconn_set_autoincrement(zbx_dbconn_t *db, int options);

int	zbx_dbconn_open(zbx_dbconn_t *db);
void 	zbx_dbconn_close(zbx_dbconn_t *db);

int	zbx_dbconn_execute(zbx_dbconn_t *db, const char *fmt, ...);
int	zbx_dbconn_vexecute(zbx_dbconn_t *db, const char *fmt, va_list args);

zbx_db_result_t	zbx_dbconn_vselect(zbx_dbconn_t *db, const char *fmt, va_list args);
zbx_db_result_t	zbx_dbconn_select(zbx_dbconn_t *db, const char *fmt, ...);
zbx_db_result_t	zbx_dbconn_select_n(zbx_dbconn_t *db, const char *query, int n);

zbx_db_row_t	zbx_db_fetch(zbx_db_result_t result);
void	zbx_db_free_result(zbx_db_result_t result);

int	zbx_dbconn_begin(zbx_dbconn_t *db);
int	zbx_dbconn_commit(zbx_dbconn_t *db);
int	zbx_dbconn_rollback(zbx_dbconn_t *db);
int	zbx_dbconn_end(zbx_dbconn_t *db, int ret);

zbx_uint64_t	zbx_dbconn_get_maxid_num(zbx_dbconn_t *db, const char *tablename, int num);

/* bulk insert support */
void	zbx_dbconn_prepare_insert_dyn(zbx_dbconn_t *db, zbx_db_insert_t *db_insert, const zbx_db_table_t *table,
		const zbx_db_field_t * const *fields, int fields_num);
void	zbx_dbconn_prepare_vinsert(zbx_dbconn_t *db, zbx_db_insert_t *db_insert, const char *table, va_list args);
void	zbx_dbconn_prepare_insert(zbx_dbconn_t *db, zbx_db_insert_t *db_insert, const char *table, ...);
void	zbx_db_insert_add_values(zbx_db_insert_t *db_insert, ...);
void	zbx_db_insert_add_values_dyn(zbx_db_insert_t *db_insert, zbx_db_value_t **values, int values_num);
int	zbx_db_insert_execute(zbx_db_insert_t *db_insert);
void	zbx_db_insert_autoincrement(zbx_db_insert_t *db_insert, const char *field_name);
zbx_uint64_t	zbx_db_insert_get_lastid(zbx_db_insert_t *self);
void	zbx_db_insert_clean(zbx_db_insert_t *db_insert);
void	zbx_db_insert_set_batch_size(zbx_db_insert_t *self, int batch_size);

void	zbx_dbconn_extract_version_info(zbx_dbconn_t *db, struct zbx_db_version_info_t *version_info);

const char	*zbx_dbconn_last_strerr(zbx_dbconn_t *db);
zbx_err_codes_t	zbx_dbconn_last_errcode(zbx_dbconn_t *db);

zbx_db_config_t	*zbx_db_config_create(void);
void	zbx_db_config_free(zbx_db_config_t *config);

int	zbx_dbconn_lock_record(zbx_dbconn_t *db, const char *table, zbx_uint64_t id, const char *add_field,
		zbx_uint64_t add_id);
int	zbx_dbconn_lock_records(zbx_dbconn_t *db, const char *table, const zbx_vector_uint64_t *ids);
int	zbx_dbconn_lock_ids(zbx_dbconn_t *db, const char *table_name, const char *field_name, zbx_vector_uint64_t *ids);

int	zbx_db_config_validate_features(zbx_db_config_t *config, unsigned char program_type);
void	zbx_db_config_validate(zbx_db_config_t *config);

int	zbx_dbconn_check_extension(zbx_dbconn_t *db, struct zbx_db_version_info_t *info, int allow_unsupported);

#if defined(HAVE_POSTGRESQL)
void	zbx_dbconn_tsdb_extract_compressed_chunk_flags(zbx_dbconn_t *db, struct zbx_db_version_info_t *version_info);
void	zbx_dbconn_tsdb_info_extract(zbx_dbconn_t *db, struct zbx_db_version_info_t *version_info);
int	zbx_dbconn_tsdb_get_version(zbx_dbconn_t *db);
#endif
int	zbx_dbconn_table_exists(zbx_dbconn_t *db, const char *table_name);
int	zbx_dbconn_field_exists(zbx_dbconn_t *db, const char *table_name, const char *field_name);

#if !defined(HAVE_SQLITE3)
int	zbx_dbconn_trigger_exists(zbx_dbconn_t *db, const char *table_name, const char *trigger_name);
int	zbx_dbconn_index_exists(zbx_dbconn_t *db, const char *table_name, const char *index_name);
int	zbx_dbconn_pk_exists(zbx_dbconn_t *db, const char *table_name);
#endif

void	zbx_dbconn_select_uint64(zbx_dbconn_t *db, const char *sql, zbx_vector_uint64_t *ids);
int	zbx_dbconn_prepare_multiple_query(zbx_dbconn_t *db, const char *query, const char *field_name,
		zbx_vector_uint64_t *ids, char **sql, size_t	*sql_alloc, size_t *sql_offset);
int	zbx_dbconn_execute_multiple_query(zbx_dbconn_t *db, const char *query, const char *field_name,
		zbx_vector_uint64_t *ids);

char	*zbx_db_dyn_escape_field(const char *table_name, const char *field_name, const char *src);
char	*zbx_db_dyn_escape_string(const char *src);
char	*zbx_db_dyn_escape_string_len(const char *src, size_t length);

void	zbx_db_add_condition_alloc(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *fieldname,
		const zbx_uint64_t *values, const int num);
void	zbx_db_add_str_condition_alloc(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *fieldname,
		const char * const *values, const int num);

const zbx_db_table_t	*zbx_db_get_table(const char *tablename);
const zbx_db_field_t	*zbx_db_get_field(const zbx_db_table_t *table, const char *fieldname);

int		zbx_db_validate_field_size(const char *tablename, const char *fieldname, const char *str);

#define zbx_db_get_maxid(table)	zbx_db_get_maxid_num(table, 1)
zbx_uint64_t	zbx_db_get_maxid_num(const char *tablename, int num);

int	zbx_db_get_row_num(zbx_db_result_t result);

int	zbx_db_is_null(const char *field);

#if defined(HAVE_POSTGRESQL)
char	*zbx_db_get_schema_esc(void);
#endif

int	zbx_dbconn_execute_overflowed_sql(zbx_dbconn_t *db, char **sql, size_t *sql_alloc, size_t *sql_offset);
int	zbx_dbconn_flush_overflowed_sql(zbx_dbconn_t *db, char *sql, size_t sql_offset);

const char	*zbx_db_sql_id_ins(zbx_uint64_t id);
const char	*zbx_db_sql_id_cmp(zbx_uint64_t id);

void	zbx_db_check_character_set(void);

/* large query support */

#define ZBX_DB_LARGE_QUERY_BATCH_SIZE	1000
#define ZBX_DB_LARGE_INSERT_BATCH_SIZE	10000

typedef enum
{
	ZBX_DB_LARGE_QUERY_UI64,
	ZBX_DB_LARGE_QUERY_STR
}
zbx_db_large_query_type_t;

typedef struct
{

	zbx_db_large_query_type_t	type;

	char			**sql;
	size_t			*sql_alloc;
	size_t			*sql_offset;
	size_t			sql_reset;
	const char		*field;
	int			offset;
	char			*suffix;

	union
	{
		const zbx_vector_uint64_t	*ui64;
		const zbx_vector_str_t		*str;
	} ids;

	zbx_db_result_t		result;
	zbx_dbconn_t		*db;
}
zbx_db_large_query_t;

void	zbx_dbconn_large_query_prepare_uint(zbx_db_large_query_t *query, zbx_dbconn_t *db, char **sql,
		size_t *sql_alloc, size_t *sql_offset, const char *field, const zbx_vector_uint64_t *ids);
void	zbx_dbconn_large_query_prepare_str(zbx_db_large_query_t *query, zbx_dbconn_t *db, char **sql,
		size_t *sql_alloc, size_t *sql_offset, const char *field, const zbx_vector_str_t *ids);
zbx_db_row_t	zbx_db_large_query_fetch(zbx_db_large_query_t *query);
void	zbx_db_large_query_clear(zbx_db_large_query_t *query);
void	zbx_dbconn_large_query_append_sql(zbx_db_large_query_t *query, const char *sql);

/* compatibility wrappers */

void	zbx_db_init_autoincrement_options(void);
int	zbx_db_connect(int flag);
void	zbx_db_close(void);
void	zbx_db_begin(void);
int	zbx_db_commit(void);
void	zbx_db_rollback(void);
int	zbx_db_end(int ret);
int	zbx_db_execute(const char *fmt, ...);
int	zbx_db_execute_once(const char *fmt, ...);
zbx_db_result_t	zbx_db_select(const char *fmt, ...);
zbx_db_result_t	zbx_db_vselect(const char *fmt, va_list args);
zbx_db_result_t	zbx_db_select_n(const char *query, int n);
void	zbx_db_insert_prepare_dyn(zbx_db_insert_t *db_insert, const zbx_db_table_t *table,
		const zbx_db_field_t **fields, int fields_num);
void	zbx_db_insert_prepare(zbx_db_insert_t *self, const char *table, ...);
void	zbx_db_extract_version_info(struct zbx_db_version_info_t *version_info);
const char	*zbx_db_last_strerr(void);
zbx_err_codes_t	zbx_db_last_errcode(void);
int	zbx_db_lock_record(const char *table, zbx_uint64_t id, const char *add_field, zbx_uint64_t add_id);
int	zbx_db_lock_records(const char *table, const zbx_vector_uint64_t *ids);
int	zbx_db_lock_ids(const char *table_name, const char *field_name, zbx_vector_uint64_t *ids);
int	zbx_db_check_extension(struct zbx_db_version_info_t *info, int allow_unsupported);
int	zbx_db_flush_overflowed_sql(char *sql, size_t sql_offset);
int	zbx_db_execute_overflowed_sql(char **sql, size_t *sql_alloc, size_t *sql_offset);
int	zbx_db_table_exists(const char *table_name);
int	zbx_db_field_exists(const char *table_name, const char *field_name);
#if !defined(HAVE_SQLITE3)
int	zbx_db_trigger_exists(const char *table_name, const char *trigger_name);
int	zbx_db_index_exists(const char *table_name, const char *index_name);
int	zbx_db_pk_exists(const char *table_name);
#endif
void	zbx_db_select_uint64(const char *sql, zbx_vector_uint64_t *ids);
int	zbx_db_prepare_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids, char **sql,
		size_t *sql_alloc, size_t *sql_offset);
int	zbx_db_execute_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids);

void	zbx_db_large_query_prepare_uint(zbx_db_large_query_t *query, char **sql,
		size_t *sql_alloc, size_t *sql_offset, const char *field, const zbx_vector_uint64_t *ids);
void	zbx_db_large_query_prepare_str(zbx_db_large_query_t *query, char **sql,
		size_t *sql_alloc, size_t *sql_offset, const char *field, const zbx_vector_str_t *ids);
void	zbx_db_large_query_append_sql(zbx_db_large_query_t *query, const char *sql);


#endif
