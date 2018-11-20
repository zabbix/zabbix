/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "common.h"

#define ZBX_DB_OK	0
#define ZBX_DB_FAIL	-1
#define ZBX_DB_DOWN	-2

#define ZBX_DB_WAIT_DOWN	10

#define ZBX_MAX_SQL_SIZE	262144	/* 256KB */

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

#ifdef HAVE_MULTIROW_INSERT
#	define ZBX_ROW_DL	","
#else
#	define ZBX_ROW_DL	";\n"
#endif

int	zbx_db_init(const char *dbname, const char *const db_schema, char **error);
void	zbx_db_deinit(void);

int	zbx_db_connect(char *host, char *user, char *password, char *dbname, char *dbschema, char *dbsocket, int port);
void	zbx_db_close(void);

int	zbx_db_begin(void);
int	zbx_db_commit(void);
int	zbx_db_rollback(void);
int	zbx_db_txn_level(void);
int	zbx_db_txn_error(void);
int	zbx_db_txn_end_error(void);
const char	*zbx_db_last_strerr(void);

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

int		zbx_db_statement_prepare(const char *sql);
int		zbx_db_bind_parameter_dyn(zbx_db_bind_context_t *context, int position, unsigned char type,
				zbx_db_value_t **rows, int rows_num);
void		zbx_db_clean_bind_context(zbx_db_bind_context_t *context);
int		zbx_db_statement_execute(int iters);
#endif
int		zbx_db_vexecute(const char *fmt, va_list args);
DB_RESULT	zbx_db_vselect(const char *fmt, va_list args);
DB_RESULT	zbx_db_select_n(const char *query, int n);

DB_ROW		zbx_db_fetch(DB_RESULT result);
void		DBfree_result(DB_RESULT result);
int		zbx_db_is_null(const char *field);

typedef enum
{
	ESCAPE_SEQUENCE_OFF,
	ESCAPE_SEQUENCE_ON
}
zbx_escape_sequence_t;
char		*zbx_db_dyn_escape_string(const char *src, size_t max_bytes, size_t max_chars,
		zbx_escape_sequence_t flag);
#define ZBX_SQL_LIKE_ESCAPE_CHAR '!'
char		*zbx_db_dyn_escape_like_pattern(const char *src);

int		zbx_db_strlen_n(const char *text, size_t maxlen);

#endif
