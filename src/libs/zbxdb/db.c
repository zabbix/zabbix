/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "zbxdb.h"

#include "zbxstr.h"
#include "zbxtime.h"
#include "zbxjson.h"
#include "zbx_dbversion_constants.h"

#if defined(HAVE_MYSQL)
#	include "mysql.h"
#	include "errmsg.h"
#	include "mysqld_error.h"
#elif defined(HAVE_POSTGRESQL)
#	include <libpq-fe.h>
#elif defined(HAVE_SQLITE3)
#	include <sqlite3.h>
#endif

#if defined(HAVE_SQLITE3)
#	include "zbxmutexs.h"
#endif
struct zbx_db_result
{
#if defined(HAVE_MYSQL)
	MYSQL_RES	*result;
#elif defined(HAVE_POSTGRESQL)
	PGresult	*pg_result;
	int		row_num;
	int		fld_num;
	int		cursor;
	zbx_db_row_t	values;
#elif defined(HAVE_SQLITE3)
	int		curow;
	char		**data;
	int		nrow;
	int		ncolumn;
	zbx_db_row_t	values;
#endif
};

static int		txn_level = 0;	/* transaction level, nested transactions are not supported */
static int		txn_error = ZBX_DB_OK;	/* failed transaction */
static int		txn_end_error = ZBX_DB_OK;	/* transaction result */

static char		*last_db_strerror = NULL;	/* last database error message */

static int		config_log_slow_queries;

static int		db_auto_increment;

#if defined(HAVE_MYSQL)
static MYSQL			*conn = NULL;
static int			mysql_err_cnt = 0;
static zbx_uint32_t		ZBX_MYSQL_SVERSION = ZBX_DBVERSION_UNDEFINED;
static int			ZBX_MARIADB_SFORK = OFF;
static int			txn_begin = 0;	/* transaction begin statement is executed */
#elif defined(HAVE_POSTGRESQL)
#define ZBX_PG_READ_ONLY	"25006"
#define ZBX_PG_UNIQUE_VIOLATION	"23505"
#define ZBX_PG_DEADLOCK		"40P01"

static PGconn			*conn = NULL;
static int			ZBX_TSDB_VERSION = -1;
static zbx_uint32_t		ZBX_PG_SVERSION = ZBX_DBVERSION_UNDEFINED;
char				ZBX_PG_ESCAPE_BACKSLASH = 1;
static int 			ZBX_TIMESCALE_COMPRESSION_AVAILABLE = OFF;
static int			ZBX_PG_READ_ONLY_RECOVERABLE;
#elif defined(HAVE_SQLITE3)
static sqlite3			*conn = NULL;
static zbx_mutex_t		sqlite_access = ZBX_MUTEX_NULL;
#endif

