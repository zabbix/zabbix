/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "common.h"
#include "db.h"
#include "log.h"
#include "sysinfo.h"
#include "zbxdbupgrade.h"
#include "dbupgrade.h"

/*
 * 2.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_2030000(void)
{
	return SUCCEED;
}

static int	DBpatch_2030001(void)
{
	const ZBX_FIELD	field = {"every", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("timeperiods", &field);
}

static int	DBpatch_2030002(void)
{
	const ZBX_TABLE table =
			{"trigger_discovery_tmp", "", 0,
				{
					{"triggerid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"parent_triggerid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2030003(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"insert into trigger_discovery_tmp (select triggerid,parent_triggerid from trigger_discovery)"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2030004(void)
{
	return DBdrop_table("trigger_discovery");
}

static int	DBpatch_2030005(void)
{
	const ZBX_TABLE table =
			{"trigger_discovery", "triggerid", 0,
				{
					{"triggerid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"parent_triggerid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2030006(void)
{
	return DBcreate_index("trigger_discovery", "trigger_discovery_1", "parent_triggerid", 0);
}

static int	DBpatch_2030007(void)
{
	const ZBX_FIELD	field = {"triggerid", NULL, "triggers", "triggerid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("trigger_discovery", 1, &field);
}

static int	DBpatch_2030008(void)
{
	const ZBX_FIELD	field = {"parent_triggerid", NULL, "triggers", "triggerid", 0, 0, 0, 0};

	return DBadd_foreign_key("trigger_discovery", 2, &field);
}

static int	DBpatch_2030009(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"insert into trigger_discovery (select triggerid,parent_triggerid from trigger_discovery_tmp)"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2030010(void)
{
	return DBdrop_table("trigger_discovery_tmp");
}

static int	DBpatch_2030011(void)
{
	const ZBX_FIELD	field = {"application", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps_elements", &field);
}

static int	DBpatch_2030012(void)
{
	const ZBX_TABLE table =
			{"graph_discovery_tmp", "", 0,
				{
					{"graphid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"parent_graphid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2030013(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"insert into graph_discovery_tmp (select graphid,parent_graphid from graph_discovery)"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2030014(void)
{
	return DBdrop_table("graph_discovery");
}

static int	DBpatch_2030015(void)
{
	const ZBX_TABLE table =
			{"graph_discovery", "graphid", 0,
				{
					{"graphid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"parent_graphid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2030016(void)
{
	return DBcreate_index("graph_discovery", "graph_discovery_1", "parent_graphid", 0);
}

static int	DBpatch_2030017(void)
{
	const ZBX_FIELD	field = {"graphid", NULL, "graphs", "graphid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("graph_discovery", 1, &field);
}

static int	DBpatch_2030018(void)
{
	const ZBX_FIELD	field = {"parent_graphid", NULL, "graphs", "graphid", 0, 0, 0, 0};

	return DBadd_foreign_key("graph_discovery", 2, &field);
}

static int	DBpatch_2030019(void)
{
	if (ZBX_DB_OK <= DBexecute(
			"insert into graph_discovery (select graphid,parent_graphid from graph_discovery_tmp)"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2030020(void)
{
	return DBdrop_table("graph_discovery_tmp");
}

static int	DBpatch_2030021(void)
{
	const ZBX_TABLE	table =
			{"item_condition", "item_conditionid", 0,
				{
					{"item_conditionid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"operator", "8", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"macro", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{NULL}
				}
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2030022(void)
{
	return DBcreate_index("item_condition", "item_condition_1", "itemid", 0);
}

static int	DBpatch_2030023(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_condition", 1, &field);
}

static int	DBpatch_2030024(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*value, *macro_esc, *value_esc;
	int		ret = FAIL, rc;

	result = DBselect("select itemid,filter from items where filter<>'' and flags=%d", ZBX_FLAG_DISCOVERY_RULE);

	while (NULL != (row = DBfetch(result)))
	{
		if (NULL == (value = strchr(row[1], ':')) || 0 == strcmp(row[1], ":"))
			continue;

		*value++ = '\0';

		macro_esc = DBdyn_escape_string(row[1]);
		value_esc = DBdyn_escape_string(value);

		rc = DBexecute("insert into item_condition"
				" (item_conditionid,itemid,macro,value)"
				" values (%s,%s,'%s','%s')",
				row[0], row[0],  macro_esc, value_esc);

		zbx_free(value_esc);
		zbx_free(macro_esc);

		if (ZBX_DB_OK > rc)
			goto out;
	}

	ret = SUCCEED;
out:
	DBfree_result(result);

	return ret;
}

static int	DBpatch_2030025(void)
{
	const ZBX_FIELD field = {"evaltype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_2030026(void)
{
	return DBdrop_field("items", "filter");
}

static int	DBpatch_2030027(void)
{
	const ZBX_FIELD	field = {"formula", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("items", &field);
}

static int	DBpatch_2030028(void)
{
	if (ZBX_DB_OK > DBexecute("update items set formula='' where flags=%d", ZBX_FLAG_DISCOVERY_RULE))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_2030029(void)
{
	/* 7 - SCREEN_SORT_TRIGGERS_STATUS_ASC */
	/* 9 - SCREEN_SORT_TRIGGERS_RETRIES_LEFT_ASC (no more supported) */
	if (ZBX_DB_OK > DBexecute("update screens_items set sort_triggers=7 where sort_triggers=9"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_2030030(void)
{
	/* 8 - SCREEN_SORT_TRIGGERS_STATUS_DESC */
	/* 10 - SCREEN_SORT_TRIGGERS_RETRIES_LEFT_DESC (no more supported) */
	if (ZBX_DB_OK > DBexecute("update screens_items set sort_triggers=8 where sort_triggers=10"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_2030031(void)
{
	/* 16 - CONDITION_TYPE_MAINTENANCE */
	if (ZBX_DB_OK > DBexecute("update conditions set value='' where conditiontype=16"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_2030032(void)
{
	const ZBX_FIELD	field = {"description", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_2030033(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;
	char		*p, *expr = NULL, *expr_esc;
	size_t		expr_alloc = 0, expr_offset;

	result = DBselect("select triggerid,expression from triggers");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		expr_offset = 0;

		for (p = row[1]; '\0' != *p; p++)
		{
			if (NULL == strchr("#&|", *p))
			{
				if (' ' != *p || (0 != expr_offset && ' ' != expr[expr_offset - 1]))
					zbx_chrcpy_alloc(&expr, &expr_alloc, &expr_offset, *p);

				continue;
			}

			if (('&' == *p || '|' == *p) && 0 != expr_offset && ' ' != expr[expr_offset - 1])
				zbx_chrcpy_alloc(&expr, &expr_alloc, &expr_offset, ' ');

			switch (*p)
			{
				case '#':
					zbx_strcpy_alloc(&expr, &expr_alloc, &expr_offset, "<>");
					break;
				case '&':
					zbx_strcpy_alloc(&expr, &expr_alloc, &expr_offset, "and");
					break;
				case '|':
					zbx_strcpy_alloc(&expr, &expr_alloc, &expr_offset, "or");
					break;
			}

			if (('&' == *p || '|' == *p) && ' ' != *(p + 1))
				zbx_chrcpy_alloc(&expr, &expr_alloc, &expr_offset, ' ');
		}

		if (2048 < expr_offset && 2048 /* TRIGGER_EXPRESSION_LEN */ < zbx_strlen_utf8(expr))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot convert trigger expression \"%s\":"
					" resulting expression is too long", row[1]);
		}
		else if (0 != strcmp(row[1], expr))
		{
			expr_esc = DBdyn_escape_string(expr);

			if (ZBX_DB_OK > DBexecute("update triggers set expression='%s' where triggerid=%s",
					expr_esc, row[0]))
			{
				ret = FAIL;
			}

			zbx_free(expr_esc);
		}
	}
	DBfree_result(result);

	zbx_free(expr);

	return ret;
}

static int	DBpatch_2030034(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;
	char		*p, *q, *params = NULL, *params_esc;
	size_t		params_alloc = 0, params_offset;

	result = DBselect("select itemid,params from items where type=%d", 15 /* ITEM_TYPE_CALCULATED */);

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		params_offset = 0;

		for (p = row[1]; '\0' != *p; p++)
		{
			if (NULL != strchr(ZBX_WHITESPACE, *p))
			{
				if (' ' != *p || (0 != params_offset &&
						NULL == strchr(ZBX_WHITESPACE, params[params_offset - 1])))
				{
					zbx_chrcpy_alloc(&params, &params_alloc, &params_offset, *p);
				}

				continue;
			}

			if (NULL != strchr("#&|", *p))
			{
				if (('&' == *p || '|' == *p) && 0 != params_offset &&
						NULL == strchr(ZBX_WHITESPACE, params[params_offset - 1]))
				{
					zbx_chrcpy_alloc(&params, &params_alloc, &params_offset, ' ');
				}

				switch (*p)
				{
					case '#':
						zbx_strcpy_alloc(&params, &params_alloc, &params_offset, "<>");
						break;
					case '&':
						zbx_strcpy_alloc(&params, &params_alloc, &params_offset, "and");
						break;
					case '|':
						zbx_strcpy_alloc(&params, &params_alloc, &params_offset, "or");
						break;
				}

				if (('&' == *p || '|' == *p) && NULL == strchr(ZBX_WHITESPACE, *(p + 1)))
					zbx_chrcpy_alloc(&params, &params_alloc, &params_offset, ' ');

				continue;
			}

			q = p;

			if (SUCCEED == parse_function(&q, NULL, NULL))
			{
				zbx_strncpy_alloc(&params, &params_alloc, &params_offset, p, q - p);
				p = q - 1;
				continue;
			}

			zbx_chrcpy_alloc(&params, &params_alloc, &params_offset, *p);
		}

#if defined(HAVE_IBM_DB2) || defined(HAVE_ORACLE)
		if (2048 < params_offset && 2048 /* ITEM_PARAM_LEN */ < zbx_strlen_utf8(params))
#else
		if (65535 < params_offset && 65535 /* ITEM_PARAM_LEN */ < zbx_strlen_utf8(params))
#endif
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot convert calculated item expression \"%s\":"
					" resulting expression is too long", row[1]);
		}
		else if (0 != strcmp(row[1], params))
		{
			params_esc = DBdyn_escape_string(params);

			if (ZBX_DB_OK > DBexecute("update items set params='%s' where itemid=%s", params_esc, row[0]))
				ret = FAIL;

			zbx_free(params_esc);
		}
	}
	DBfree_result(result);

	zbx_free(params);

	return ret;
}

#endif

DBPATCH_START(2030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(2030000, 0, 1)
DBPATCH_ADD(2030001, 0, 1)
DBPATCH_ADD(2030002, 0, 1)
DBPATCH_ADD(2030003, 0, 1)
DBPATCH_ADD(2030004, 0, 1)
DBPATCH_ADD(2030005, 0, 1)
DBPATCH_ADD(2030006, 0, 1)
DBPATCH_ADD(2030007, 0, 1)
DBPATCH_ADD(2030008, 0, 1)
DBPATCH_ADD(2030009, 0, 1)
DBPATCH_ADD(2030010, 0, 1)
DBPATCH_ADD(2030011, 0, 1)
DBPATCH_ADD(2030012, 0, 1)
DBPATCH_ADD(2030013, 0, 1)
DBPATCH_ADD(2030014, 0, 1)
DBPATCH_ADD(2030015, 0, 1)
DBPATCH_ADD(2030016, 0, 1)
DBPATCH_ADD(2030017, 0, 1)
DBPATCH_ADD(2030018, 0, 1)
DBPATCH_ADD(2030019, 0, 1)
DBPATCH_ADD(2030020, 0, 1)
DBPATCH_ADD(2030021, 0, 1)
DBPATCH_ADD(2030022, 0, 1)
DBPATCH_ADD(2030023, 0, 1)
DBPATCH_ADD(2030024, 0, 1)
DBPATCH_ADD(2030025, 0, 1)
DBPATCH_ADD(2030026, 0, 1)
DBPATCH_ADD(2030027, 0, 1)
DBPATCH_ADD(2030028, 0, 1)
DBPATCH_ADD(2030029, 0, 1)
DBPATCH_ADD(2030030, 0, 1)
DBPATCH_ADD(2030031, 0, 0)
DBPATCH_ADD(2030032, 0, 1)
DBPATCH_ADD(2030033, 0, 1)
DBPATCH_ADD(2030034, 0, 1)

DBPATCH_END()
