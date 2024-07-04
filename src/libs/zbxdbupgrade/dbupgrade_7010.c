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

#include "dbupgrade.h"
#include "zbxdbhigh.h"
#include "zbxdb.h"

/*
 * 7.2 development database patches
 */

#ifndef HAVE_SQLITE3

static int	parse_widget_field_index(char *name)
{
	int	value;
	char	*idx, *idx_end;

	if (NULL == (idx = strchr(name, '.')))
		return FAIL;

	idx++;

	if (NULL == (idx_end = strchr(idx, '.')))
		return FAIL;

	*idx_end = '\0';

	if (SUCCEED != zbx_is_int(idx, &value))
		return FAIL;

	return value;
}

static int	DBpatch_7010000(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update profiles"
				" set value_str='operating_mode'"
				" where idx='web.proxies.php.sort'"
				" and value_str like 'status'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_7010001(void)
{
	if (FAIL == zbx_db_index_exists("auditlog", "auditlog_4"))
		return DBcreate_index("auditlog", "auditlog_4", "recordsetid", 0);

	return SUCCEED;
}

static int	DBpatch_7010002(void)
{
#define ZBX_WIDGET_FIELD_TYPE_INT32	0
	int		ret, last_field_idx = 0;
	zbx_uint64_t	last_widgetid = 0;
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_db_insert_t	db_insert;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "widget_field", "widget_fieldid", "widgetid", "type", "name", "value_int",
			(char *)NULL);

	result = zbx_db_select("select w.widgetid,wf.name from widget_field wf "
			" inner join widget w on wf.widgetid=w.widgetid"
			" where w.type='tophosts' and wf.name like 'columns.%%.%%'"
			" order by wf.widgetid,wf.name");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	widgetid;
		int		idx;
		char		*name_ins;

		if (FAIL == (idx = parse_widget_field_index(row[1])))
			continue;

		ZBX_STR2UINT64(widgetid, row[0]);

		if (last_widgetid == widgetid && last_field_idx == idx)
			continue;

		name_ins = zbx_dsprintf(NULL, "columns.%i.display_item_as", idx);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, ZBX_WIDGET_FIELD_TYPE_INT32, name_ins,
				__UINT64_C(0));

		last_widgetid = widgetid;
		last_field_idx = idx;
		zbx_free(name_ins);
	}
	zbx_db_free_result(result);

	zbx_db_insert_autoincrement(&db_insert, "widget_fieldid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
#undef ZBX_WIDGET_FIELD_TYPE_INT32
}

#endif

DBPATCH_START(7010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(7010000, 0, 1)
DBPATCH_ADD(7010001, 0, 1)
DBPATCH_ADD(7010002, 0, 1)

DBPATCH_END()
