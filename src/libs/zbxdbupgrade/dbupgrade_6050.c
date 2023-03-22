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

#include "zbxdbhigh.h"
#include "dbupgrade.h"

/*
 * 7.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_6050000(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*sql = NULL;
	const int	total_dbl_cols = 4;
	int		ret = FAIL;

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

#if defined(HAVE_MYSQL)
	sql = zbx_db_get_name_esc();
	sql = zbx_dsprintf(sql, "select count(*) from information_schema.columns"
			" where table_schema='%s' and column_type='double'", sql);
#elif defined(HAVE_POSTGRESQL)
	sql = zbx_db_get_schema_esc();
	sql = zbx_dsprintf(sql, "select count(*) from information_schema.columns"
			" where table_schema='%s' and data_type='double precision'", sql);
#elif defined(HAVE_ORACLE)
	sql = zbx_strdup(sql, "select count(*) from user_tab_columns"
			" where data_type='BINARY_DOUBLE'");
#endif

	if (NULL == (result = zbx_db_select("%s"
			" and ((lower(table_name)='trends'"
					" and (lower(column_name) in ('value_min', 'value_avg', 'value_max')))"
			" or (lower(table_name)='history' and lower(column_name)='value'))", sql)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot select records with columns information");
		goto out;
	}

	if (NULL != (row = zbx_db_fetch(result)) && total_dbl_cols == atoi(row[0]))
		ret = SUCCEED;
	else
		zabbix_log(LOG_LEVEL_CRIT, "The old numeric type is no longer supported. Please upgrade to numeric"
				"values of extended range.");

	zbx_db_free_result(result);
out:
	zbx_db_close();
	zbx_free(sql);

	return ret;
}

#endif

DBPATCH_START(6050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(6050000, 0, 1)

DBPATCH_END()
