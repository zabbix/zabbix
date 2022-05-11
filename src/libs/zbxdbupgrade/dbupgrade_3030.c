/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#include "dbupgrade.h"
#include "log.h"

/*
 * 3.4 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char program_type;

static int	DBpatch_3030000(void)
{
	const ZBX_FIELD	field = {"ipmi_authtype", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("hosts", &field);
}

static int	DBpatch_3030001(void)
{
	const ZBX_FIELD	field = {"snmp_oid", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field, NULL);
}

static int	DBpatch_3030002(void)
{
	const ZBX_FIELD	field = {"key_", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("dchecks", &field, NULL);
}

static int	DBpatch_3030003(void)
{
	return DBdrop_field("proxy_dhistory", "type");
}

static int	DBpatch_3030004(void)
{
	return DBdrop_field("proxy_dhistory", "key_");
}

static int	DBpatch_3030005(void)
{
	return DBdrop_foreign_key("dservices", 2);
}

static int	DBpatch_3030006(void)
{
	return DBdrop_index("dservices", "dservices_1");
}

static int	DBpatch_3030007(void)
{
	DB_ROW			row;
	DB_RESULT		result;
	zbx_vector_uint64_t	dserviceids;
	zbx_uint64_t		dserviceid;
	int			ret = SUCCEED;

	zbx_vector_uint64_create(&dserviceids);

	/* After dropping fields type and key_ from table dservices there is no guarantee that a unique
	index with fields dcheckid, ip and port can be created. To create a unique index for the same
	fields later this will delete rows where all three of them are identical only leaving the latest. */
	result = DBselect(
			"select ds.dserviceid"
			" from dservices ds"
			" where not exists ("
				"select null"
				" from dchecks dc"
				" where ds.dcheckid = dc.dcheckid"
					" and ds.type = dc.type"
					" and ds.key_ = dc.key_"
			")");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(dserviceid, row[0]);

		zbx_vector_uint64_append(&dserviceids, dserviceid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&dserviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != dserviceids.values_num)
		ret = DBexecute_multiple_query("delete from dservices where", "dserviceid", &dserviceids);

	zbx_vector_uint64_destroy(&dserviceids);

	return ret;
}

static int	DBpatch_3030008(void)
{
	return DBdrop_field("dservices", "type");
}

static int	DBpatch_3030009(void)
{
	return DBdrop_field("dservices", "key_");
}

static int	DBpatch_3030010(void)
{
	return DBcreate_index("dservices", "dservices_1", "dcheckid,ip,port", 1);
}

static int	DBpatch_3030011(void)
{
	const ZBX_FIELD	field = {"dcheckid", NULL, "dchecks", "dcheckid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dservices", 2, &field);
}

static int	DBpatch_3030012(void)
{
	const ZBX_FIELD	field = {"snmp_lastsize", "0", NULL, NULL, 0, ZBX_TYPE_UINT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("globalvars", &field, NULL);
}

static int	DBpatch_3030013(void)
{
	const ZBX_FIELD	field = {"period", "1-7,00:00-24:00", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("media", &field, NULL);
}

static int	DBpatch_3030015(void)
{
	const ZBX_TABLE table =
			{"item_preproc", "item_preprocid", 0,
				{
					{"item_preprocid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"step", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"params", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030016(void)
{
	return DBcreate_index("item_preproc", "item_preproc_1", "itemid,step", 0);
}

static int	DBpatch_3030017(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_preproc", 1, &field);
}

static void	DBpatch_3030018_add_numeric_preproc_steps(zbx_db_insert_t *db_insert, zbx_uint64_t itemid,
		unsigned char data_type, const char *formula, unsigned char delta)
{
	int	step = 1;

	switch (data_type)
	{
		case ITEM_DATA_TYPE_BOOLEAN:
			zbx_db_insert_add_values(db_insert, __UINT64_C(0), itemid, step++, ZBX_PREPROC_BOOL2DEC, "");
			break;
		case ITEM_DATA_TYPE_OCTAL:
			zbx_db_insert_add_values(db_insert, __UINT64_C(0), itemid, step++, ZBX_PREPROC_OCT2DEC, "");
			break;
		case ITEM_DATA_TYPE_HEXADECIMAL:
			zbx_db_insert_add_values(db_insert, __UINT64_C(0), itemid, step++, ZBX_PREPROC_HEX2DEC, "");
			break;
	}

	switch (delta)
	{
		/* ITEM_STORE_SPEED_PER_SECOND */
		case 1:
			zbx_db_insert_add_values(db_insert, __UINT64_C(0), itemid, step++, ZBX_PREPROC_DELTA_SPEED, "");
			break;
		/* ITEM_STORE_SIMPLE_CHANGE */
		case 2:
			zbx_db_insert_add_values(db_insert, __UINT64_C(0), itemid, step++, ZBX_PREPROC_DELTA_VALUE, "");
			break;
	}

	if (NULL != formula)
		zbx_db_insert_add_values(db_insert, __UINT64_C(0), itemid, step++, ZBX_PREPROC_MULTIPLIER, formula);

}

static int	DBpatch_3030018(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	unsigned char	value_type, data_type, delta;
	zbx_db_insert_t	db_insert;
	zbx_uint64_t	itemid;
	const char	*formula;
	int		ret;

	zbx_db_insert_prepare(&db_insert, "item_preproc", "item_preprocid", "itemid", "step", "type", "params", NULL);

	result = DBselect("select itemid,value_type,data_type,multiplier,formula,delta from items");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		ZBX_STR2UCHAR(value_type, row[1]);

		switch (value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
				ZBX_STR2UCHAR(data_type, row[2]);
				formula = (1 == atoi(row[3]) ? row[4] : NULL);
				ZBX_STR2UCHAR(delta, row[5]);
				DBpatch_3030018_add_numeric_preproc_steps(&db_insert, itemid, data_type, formula,
						delta);
				break;
		}
	}

	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, "item_preprocid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_3030019(void)
{
	return DBdrop_field("items", "multiplier");
}

static int	DBpatch_3030020(void)
{
	return DBdrop_field("items", "data_type");
}

static int	DBpatch_3030021(void)
{
	return DBdrop_field("items", "delta");
}

static int	DBpatch_3030022(void)
{
	if (ZBX_DB_OK > DBexecute("update items set formula='' where flags<>1 or evaltype<>3"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3030023(void)
{
	if (ZBX_DB_OK > DBexecute("delete from profiles where idx like 'web.dashboard.widget.%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3030024(void)
{
	const ZBX_FIELD	field = {"hk_events_internal", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030025(void)
{
	const ZBX_FIELD	field = {"hk_events_discovery", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030026(void)
{
	const ZBX_FIELD	field = {"hk_events_autoreg", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030027(void)
{
	const ZBX_FIELD	field = {"p_eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("alerts", &field);
}

static int	DBpatch_3030028(void)
{
	const ZBX_FIELD	field = {"p_eventid", NULL, "events", "eventid", 0, ZBX_TYPE_ID, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("alerts", 5, &field);
}

static int	DBpatch_3030029(void)
{
	return DBcreate_index("alerts", "alerts_7", "p_eventid", 0);
}

/******************************************************************************
 *                                                                            *
 * Comments: This procedure fills in field 'p_eventid' for all recovery       *
 *           actions. 'p_eventid' value is defined as per last problematic    *
 *           event, that was closed by correct recovery event.                *
 *           This is done because the relation between recovery alerts and    *
 *           this method is most successful for updating zabbix 3.0 to latest *
 *           versions.                                                        *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_3030030(void)
{
	int		ret = SUCCEED, upd_num;
	DB_ROW		row;
	DB_RESULT	result;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset;
	zbx_uint64_t	last_r_eventid = 0, r_eventid;

	do
	{
		upd_num = 0;

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select e.eventid, e.r_eventid"
					" from event_recovery e"
						" join alerts a"
							" on a.eventid=e.r_eventid");
		if (0 < last_r_eventid)
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where e.r_eventid<" ZBX_FS_UI64,
					last_r_eventid);
		}
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by e.r_eventid desc, e.eventid desc");

		if (NULL == (result = DBselectN(sql, 10000)))
		{
			ret = FAIL;
			break;
		}

		sql_offset = 0;
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(r_eventid, row[1]);
			if (last_r_eventid == r_eventid)
				continue;

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update alerts set p_eventid=%s where eventid=%s;\n",
					row[0], row[1]);

			if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
				goto out;

			last_r_eventid = r_eventid;
			upd_num++;
		}

		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)
		{
			if (ZBX_DB_OK > DBexecute("%s", sql))
				ret = FAIL;
		}
out:
		DBfree_result(result);
	}
	while (0 < upd_num && SUCCEED == ret);

	zbx_free(sql);

	return ret;
}

static int	DBpatch_3030031(void)
{
	const ZBX_FIELD	field = {"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("task", &field);
}

static int	DBpatch_3030032(void)
{
	const ZBX_FIELD	field = {"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("task", &field);
}

static int	DBpatch_3030033(void)
{
	const ZBX_FIELD	field = {"ttl", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("task", &field);
}

static int	DBpatch_3030034(void)
{
	const ZBX_FIELD	field = {"proxy_hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("task", &field);
}

static int	DBpatch_3030035(void)
{
	return DBcreate_index("task", "task_1", "status,proxy_hostid", 0);
}

static int	DBpatch_3030036(void)
{
	const ZBX_FIELD	field = {"proxy_hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("task", 1, &field);
}

static int	DBpatch_3030037(void)
{
	const ZBX_TABLE table =
			{"task_remote_command", "taskid", 0,
				{
					{"taskid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"command_type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"execute_on", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"port", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"authtype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"username", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"password", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"publickey", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"privatekey", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"command", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
					{"alertid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"parent_taskid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030038(void)
{
	const ZBX_FIELD	field = {"taskid", NULL, "task", "taskid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("task_remote_command", 1, &field);
}

static int	DBpatch_3030039(void)
{
	const ZBX_TABLE	table =
			{"task_remote_command_result", "taskid", 0,
				{
					{"taskid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"parent_taskid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"info", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030040(void)
{
	const ZBX_FIELD	field = {"taskid", NULL, "task", "taskid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("task_remote_command_result", 1, &field);
}

static int	DBpatch_3030041(void)
{
	/* 1 - ZBX_TM_STATUS_NEW */
	if (ZBX_DB_OK > DBexecute("update task set status=1"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3030042(void)
{
	/* 2 - ZBX_SCRIPT_EXECUTE_ON_PROXY */
	const ZBX_FIELD	field = {"execute_on", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("scripts", &field);
}

static int	DBpatch_3030043(void)
{
	const ZBX_TABLE	table =
			{"sysmap_shape", "shapeid", 0,
				{
					{"shapeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"sysmapid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"x", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"y", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"width", "200", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"height", "200", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"text", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
					{"font", "9", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"font_size", "11", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"font_color", "000000", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"text_halign", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"text_valign", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"border_type", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"border_width", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"border_color", "000000", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL,0},
					{"background_color", "", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"zindex", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030044(void)
{
	return DBcreate_index("sysmap_shape", "sysmap_shape_1", "sysmapid", 0);
}

static int	DBpatch_3030045(void)
{
	const ZBX_FIELD	field = {"sysmapid", NULL, "sysmaps", "sysmapid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sysmap_shape", 1, &field);
}

static int	DBpatch_3030046(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = FAIL;
	zbx_uint64_t	shapeid = 0;

	result = DBselect("select sysmapid,width from sysmaps");

	while (NULL != (row = DBfetch(result)))
	{
		if (ZBX_DB_OK > DBexecute("insert into sysmap_shape (shapeid,sysmapid,width,height,text,border_width)"
				" values (" ZBX_FS_UI64 ",%s,%s,15,'{MAP.NAME}',0)", shapeid++, row[0], row[1]))
		{
			goto out;
		}
	}

	ret = SUCCEED;
out:
	DBfree_result(result);

	return ret;
}

static int	DBpatch_3030047(void)
{
	const ZBX_FIELD	field = {"error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("triggers", &field, NULL);
}

static int	DBpatch_3030048(void)
{
	const ZBX_FIELD	field = {"error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("alerts", &field, NULL);
}

static int	DBpatch_3030049(void)
{
	const ZBX_TABLE	table =
			{"sysmap_element_trigger", "selement_triggerid", 0,
				{
					{"selement_triggerid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"selementid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"triggerid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030050(void)
{
	return DBcreate_index("sysmap_element_trigger", "sysmap_element_trigger_1", "selementid,triggerid", 1);
}

static int	DBpatch_3030051(void)
{
	const ZBX_FIELD	field = {"selementid", NULL, "sysmaps_elements", "selementid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sysmap_element_trigger", 1, &field);
}

static int	DBpatch_3030052(void)
{
	const ZBX_FIELD	field = {"triggerid", NULL, "triggers", "triggerid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sysmap_element_trigger", 2, &field);
}

static int	DBpatch_3030053(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_db_insert_t	db_insert;
	zbx_uint64_t	selementid, triggerid;
	int		ret = FAIL;

	zbx_db_insert_prepare(&db_insert, "sysmap_element_trigger", "selement_triggerid", "selementid", "triggerid",
			NULL);

	/* sysmaps_elements.elementid for trigger map elements (2) should be migrated to table sysmap_element_trigger */
	result = DBselect("select e.selementid,e.label,t.triggerid"
			" from sysmaps_elements e"
			" left join triggers t on"
			" e.elementid=t.triggerid"
			" where e.elementtype=2");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(selementid, row[0]);
		if (NULL != row[2])
		{
			ZBX_STR2UINT64(triggerid, row[2]);

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), selementid, triggerid);
		}
		else
		{
			if (ZBX_DB_OK > DBexecute("delete from sysmaps_elements where selementid=" ZBX_FS_UI64,
					selementid))
			{
				goto out;
			}

			zabbix_log(LOG_LEVEL_WARNING, "Map trigger element \"%s\" (selementid: " ZBX_FS_UI64 ") will be"
					" removed during database upgrade: no trigger found", row[1], selementid);
		}
	}

	zbx_db_insert_autoincrement(&db_insert, "selement_triggerid");
	ret = zbx_db_insert_execute(&db_insert);
out:
	DBfree_result(result);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_3030054(void)
{
	const ZBX_TABLE	table =
			{"httptest_field", "httptest_fieldid", 0,
				{
					{"httptest_fieldid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"httptestid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030055(void)
{
	return DBcreate_index("httptest_field", "httptest_field_1", "httptestid", 0);
}

static int	DBpatch_3030056(void)
{
	const ZBX_FIELD	field = {"httptestid", NULL, "httptest", "httptestid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("httptest_field", 1, &field);
}

static int	DBpatch_3030057(void)
{
	const ZBX_TABLE	table =
			{"httpstep_field", "httpstep_fieldid", 0,
				{
					{"httpstep_fieldid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"httpstepid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030058(void)
{
	return DBcreate_index("httpstep_field", "httpstep_field_1", "httpstepid", 0);
}

static int	DBpatch_3030059(void)
{
	const ZBX_FIELD	field = {"httpstepid", NULL, "httpstep", "httpstepid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("httpstep_field", 1, &field);
}

static int 	DBpatch_3030060_pair_cmp_func(const void *d1, const void *d2)
{
	const zbx_ptr_pair_t	*pair1 = (const zbx_ptr_pair_t *)d1;
	const zbx_ptr_pair_t	*pair2 = (const zbx_ptr_pair_t *)d2;

	return strcmp((char *)pair1->first, (char *)pair2->first);
}

#define TRIM_LEADING_WHITESPACE(ptr)	while (' ' == *ptr || '\t' == *ptr) ptr++;
#define TRIM_TRAILING_WHITESPACE(ptr)	do { ptr--; } while (' ' == *ptr || '\t' == *ptr);

static void	DBpatch_3030060_append_pairs(zbx_db_insert_t *db_insert, zbx_uint64_t parentid, int type,
		const char *source, const char separator, int unique, int allow_empty)
{
	char			*buffer, *key, *value, replace;
	zbx_vector_ptr_pair_t	pairs;
	zbx_ptr_pair_t		pair;
	int			index;

	buffer = zbx_strdup(NULL, source);
	key = buffer;
	zbx_vector_ptr_pair_create(&pairs);

	while ('\0' != *key)
	{
		char	*ptr = key;

		/* find end of the line */
		while ('\0' != *ptr && '\n' != *ptr && '\r' != *ptr)
			ptr++;

		replace = *ptr;
		*ptr = '\0';

		/* parse line */
		value = strchr(key, separator);

		/* if separator is absent and empty values are allowed, consider that value is empty */
		if (0 != allow_empty && NULL == value)
			value = ptr;

		if (NULL != value)
		{
			char	*tail = value;

			if (ptr != value)
				value++;

			TRIM_LEADING_WHITESPACE(key);
			if (key != tail)
			{
				TRIM_TRAILING_WHITESPACE(tail);
				tail[1] = '\0';
			}
			else
				goto skip;	/* no key */

			tail = ptr;
			TRIM_LEADING_WHITESPACE(value);
			if (value != tail)
			{
				TRIM_TRAILING_WHITESPACE(tail);
				tail[1] = '\0';
			}
			else
			{
				if (0 == allow_empty)
					goto skip;	/* no value */
			}

			pair.first = key;

			if (0 == unique || FAIL == (index = zbx_vector_ptr_pair_search(&pairs, pair,
					DBpatch_3030060_pair_cmp_func)))
			{
				pair.second = value;
				zbx_vector_ptr_pair_append(&pairs, pair);
			}
			else
				pairs.values[index].second = value;
		}
skip:
		if ('\0' != replace)
			ptr++;

		/* skip LF/CR symbols until the next nonempty line */
		while ('\n' == *ptr || '\r' == *ptr)
			ptr++;

		key = ptr;
	}

	for (index = 0; index < pairs.values_num; index++)
	{
		pair = pairs.values[index];
		zbx_db_insert_add_values(db_insert, __UINT64_C(0), parentid, type, pair.first, pair.second);
	}

	zbx_vector_ptr_pair_destroy(&pairs);
	zbx_free(buffer);
}

static int	DBpatch_3030060_migrate_pairs(const char *table, const char *field, int type, char separator,
		int unique, int allow_empty)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_db_insert_t	db_insert;
	zbx_uint64_t	parentid;
	char		*target, *target_id, *source_id;
	int		ret;

	target = zbx_dsprintf(NULL, "%s%s", table, "_field");
	target_id = zbx_dsprintf(NULL, "%s%s", table, "_fieldid");
	source_id = zbx_dsprintf(NULL, "%s%s", table, "id");

	zbx_db_insert_prepare(&db_insert, target, target_id, source_id, "type", "name", "value", NULL);

	result = DBselect("select %s,%s from %s", source_id, field, table);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(parentid, row[0]);

		if (0 != strlen(row[1]))
		{
			DBpatch_3030060_append_pairs(&db_insert, parentid, type, row[1], separator, unique,
					allow_empty);
		}
	}
	DBfree_result(result);

	zbx_db_insert_autoincrement(&db_insert, target_id);
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_free(source_id);
	zbx_free(target_id);
	zbx_free(target);

	return ret;
}

static int	DBpatch_3030060(void)
{
	return DBpatch_3030060_migrate_pairs("httptest", "variables", ZBX_HTTPFIELD_VARIABLE, '=', 1, 1);
}

static int	DBpatch_3030061(void)
{
	return DBdrop_field("httptest", "variables");
}

static int	DBpatch_3030062(void)
{
	/* headers without value are not allowed by rfc7230 */
	return DBpatch_3030060_migrate_pairs("httptest", "headers", ZBX_HTTPFIELD_HEADER, ':', 0, 0);
}

static int	DBpatch_3030063(void)
{
	return DBdrop_field("httptest", "headers");
}

static int	DBpatch_3030064(void)
{
	return DBpatch_3030060_migrate_pairs("httpstep", "variables", ZBX_HTTPFIELD_VARIABLE, '=', 1, 1);
}

static int	DBpatch_3030065(void)
{
	return DBdrop_field("httpstep", "variables");
}

static int	DBpatch_3030066(void)
{
	return DBpatch_3030060_migrate_pairs("httpstep", "headers", ZBX_HTTPFIELD_HEADER, ':', 0, 0);
}

static int	DBpatch_3030067(void)
{
	return DBdrop_field("httpstep", "headers");
}

static int	DBpatch_3030068(void)
{
	const ZBX_FIELD	field = {"post_type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("httpstep", &field);
}

static int	DBpatch_3030069(void)
{
	const ZBX_FIELD	field = {"sysmap_shapeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBrename_field("sysmap_shape", "shapeid", &field);
}

static int	DBpatch_3030070(void)
{
	const ZBX_FIELD	field = {"text_halign", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("sysmap_shape", &field);
}

static int	DBpatch_3030071(void)
{
	const ZBX_FIELD	field = {"text_valign", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("sysmap_shape", &field);
}

static int	DBpatch_3030072(void)
{
	const ZBX_FIELD	field = {"border_type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("sysmap_shape", &field);
}

static int	DBpatch_3030073(void)
{
	const ZBX_FIELD	field = {"zindex", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("sysmap_shape", &field);
}

static int	DBpatch_3030074(void)
{
	if (ZBX_DB_OK > DBexecute("update sysmap_shape set text_halign=text_halign+1,text_valign=text_valign+1,"
			"border_type=border_type+1"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static void	DBpatch_conv_day(int *value, const char **suffix)
{
	if (0 != *value)
	{
		if (0 == *value % 7)
		{
			*value /= 7;
			*suffix = "w";
		}
		else
			*suffix = "d";
	}
	else
		*suffix = "";
}

static void	DBpatch_conv_day_limit_25y(int *value, const char **suffix)
{
	if (25 * 365 <= *value)
	{
		*value = 25 * 365;
		*suffix = "d";
	}
	else
		DBpatch_conv_day(value, suffix);
}

static void	DBpatch_conv_sec(int *value, const char **suffix)
{
	if (0 != *value)
	{
		const int	factors[] = {60, 60, 24, 7, 0}, *factor = factors;
		const char	*suffixes[] = {"s", "m", "h", "d", "w"};

		while (0 != *factor && 0 == *value % *factor)
			*value /= *factor++;

		*suffix = suffixes[factor - factors];
	}
	else
		*suffix = "";
}

static void	DBpatch_conv_sec_limit_1w(int *value, const char **suffix)
{
	if (7 * 24 * 60 * 60 <= *value)
	{
		*value = 1;
		*suffix = "w";
	}
	else
		DBpatch_conv_sec(value, suffix);
}

typedef struct
{
	const char	*field;
	void		(*conv_func)(int *value, const char **suffix);
}
DBpatch_field_conv_t;

static int	DBpatch_table_convert(const char *table, const char *recid, const DBpatch_field_conv_t *field_convs)
{
	const DBpatch_field_conv_t	*fc;
	DB_RESULT			result;
	DB_ROW				row;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	const char			*suffix;
	int				value, i, ret = FAIL;

	for (fc = field_convs; NULL != fc->field; fc++)
	{
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ',');
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, fc->field);
	}

	result = DBselect("select %s%s from %s", recid, sql, table);

	sql_offset = 0;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ", table);

		for (i = 1, fc = field_convs; NULL != fc->field; i++, fc++)
		{
			value = atoi(row[i]);
			fc->conv_func(&value, &suffix);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s%s='%d%s'",
					(1 == i ? "" : ","), fc->field, value, suffix);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where %s=%s;\n", recid, row[0]);

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	ret = SUCCEED;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_3030075(void)
{
	const ZBX_FIELD	old_field = {"autologout", "900", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"autologout", "15m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("users", &new_field, &old_field);
}

static int	DBpatch_3030076(void)
{
	const ZBX_FIELD	field = {"autologout", "15m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("users", &field);
}

static int	DBpatch_3030077(void)
{
	const ZBX_FIELD	old_field = {"refresh", "30", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"refresh", "30s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("users", &new_field, &old_field);
}

static int	DBpatch_3030078(void)
{
	const ZBX_FIELD	field = {"refresh", "30s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("users", &field);
}

static int	DBpatch_3030079(void)
{
	const DBpatch_field_conv_t	field_convs[] = {
						{"autologout",	DBpatch_conv_sec},
						{"refresh",	DBpatch_conv_sec},
						{NULL}
					};

	return DBpatch_table_convert("users", "userid", field_convs);
}

static int	DBpatch_3030080(void)
{
	const ZBX_FIELD	old_field = {"delay", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"delay", "30s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("slideshows", &new_field, &old_field);
}

static int	DBpatch_3030081(void)
{
	const ZBX_FIELD	field = {"delay", "30s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("slideshows", &field);
}

static int	DBpatch_3030082(void)
{
	const ZBX_FIELD	old_field = {"delay", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"delay", "0", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("slides", &new_field, &old_field);
}

static int	DBpatch_3030083(void)
{
	const ZBX_FIELD	old_field = {"delay", "3600", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"delay", "1h", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("drules", &new_field, &old_field);
}

static int	DBpatch_3030084(void)
{
	const ZBX_FIELD	field = {"delay", "1h", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("drules", &field);
}

static int	DBpatch_3030085(void)
{
	const DBpatch_field_conv_t	field_convs[] = {{"delay", DBpatch_conv_sec}, {NULL}};

	return DBpatch_table_convert("drules", "druleid", field_convs);
}

static int	DBpatch_3030086(void)
{
	const ZBX_FIELD	old_field = {"delay", "60", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"delay", "1m", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("httptest", &new_field, &old_field);
}

static int	DBpatch_3030087(void)
{
	const ZBX_FIELD	field = {"delay", "1m", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("httptest", &field);
}

static int	DBpatch_3030088(void)
{
	const DBpatch_field_conv_t	field_convs[] = {{"delay", DBpatch_conv_sec}, {NULL}};

	return DBpatch_table_convert("httptest", "httptestid", field_convs);
}

static int	DBpatch_3030089(void)
{
	const ZBX_FIELD	old_field = {"timeout", "15", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"timeout", "15s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("httpstep", &new_field, &old_field);
}

static int	DBpatch_3030090(void)
{
	const ZBX_FIELD	field = {"timeout", "15s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("httpstep", &field);
}

static int	DBpatch_3030091(void)
{
	const DBpatch_field_conv_t	field_convs[] = {{"timeout", DBpatch_conv_sec}, {NULL}};

	return DBpatch_table_convert("httpstep", "httpstepid", field_convs);
}

static int	DBpatch_3030092(void)
{
	const ZBX_FIELD	old_field = {"delay", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"delay", "0", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &new_field, &old_field);
}

static int	DBpatch_3030093(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	const char	*delay_flex, *next, *suffix;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	int		delay, ret = FAIL;

	result = DBselect("select itemid,delay,delay_flex from items");

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (row = DBfetch(result)))
	{
		delay = atoi(row[1]);
		DBpatch_conv_sec(&delay, &suffix);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set delay='%d%s", delay, suffix);

		for (delay_flex = row[2]; '\0' != *delay_flex; delay_flex = next + 1)
		{
			zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ';');

			if (0 != isdigit(*delay_flex) && NULL != (next = strchr(delay_flex, '/')))	/* flexible */
			{
				delay = atoi(delay_flex);
				DBpatch_conv_sec(&delay, &suffix);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%d%s", delay, suffix);
				delay_flex = next;
			}

			if (NULL == (next = strchr(delay_flex, ';')))
			{
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, delay_flex);
				break;
			}

			zbx_strncpy_alloc(&sql, &sql_alloc, &sql_offset, delay_flex, next - delay_flex);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "' where itemid=%s;\n", row[0]);

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	ret = SUCCEED;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_3030094(void)
{
	return DBdrop_field("items", "delay_flex");
}

static int	DBpatch_3030095(void)
{
	const ZBX_FIELD	old_field = {"history", "90", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"history", "90d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &new_field, &old_field);
}

static int	DBpatch_3030096(void)
{
	const ZBX_FIELD	field = {"history", "90d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("items", &field);
}

static int	DBpatch_3030097(void)
{
	const ZBX_FIELD	old_field = {"trends", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"trends", "365d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &new_field, &old_field);
}

static int	DBpatch_3030098(void)
{
	const ZBX_FIELD	field = {"trends", "365d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("items", &field);
}

static int	DBpatch_3030099(void)
{
	const DBpatch_field_conv_t	field_convs[] = {
						{"history",	DBpatch_conv_day_limit_25y},
						{"trends",	DBpatch_conv_day_limit_25y},
						{NULL}
					};

	return DBpatch_table_convert("items", "itemid", field_convs);
}

static int	DBpatch_3030100(void)
{
	const ZBX_FIELD	field = {"lifetime", "30d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field, NULL);
}

static int	DBpatch_3030101(void)
{
	const ZBX_FIELD	field = {"lifetime", "30d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("items", &field);
}

static int	DBpatch_3030102(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	const char	*suffix;
	int		value, ret = FAIL;

	result = DBselect("select itemid,lifetime from items");

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update items set lifetime='");

		if (0 != isdigit(*row[1]))
		{
			value = atoi(row[1]);
			DBpatch_conv_day_limit_25y(&value, &suffix);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%d%s", value, suffix);
		}
		else	/* items.lifetime may be a macro, in such case simply overwrite with max allowed value */
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "9125d");	/* 25 * 365 days */

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "' where itemid=%s;\n", row[0]);

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	ret = SUCCEED;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_3030103(void)
{
	const ZBX_FIELD	old_field = {"esc_period", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"esc_period", "1h", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("actions", &new_field, &old_field);
}

static int	DBpatch_3030104(void)
{
	const ZBX_FIELD	field = {"esc_period", "1h", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("actions", &field);
}

static int	DBpatch_3030105(void)
{
	const DBpatch_field_conv_t	field_convs[] = {{"esc_period", DBpatch_conv_sec_limit_1w}, {NULL}};

	return DBpatch_table_convert("actions", "actionid", field_convs);
}

static int	DBpatch_3030106(void)
{
	const ZBX_FIELD	old_field = {"esc_period", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"esc_period", "0", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("operations", &new_field, &old_field);
}

static int	DBpatch_3030107(void)
{
	const DBpatch_field_conv_t	field_convs[] = {{"esc_period", DBpatch_conv_sec_limit_1w}, {NULL}};

	return DBpatch_table_convert("operations", "operationid", field_convs);
}

static int	DBpatch_3030108(void)
{
	const ZBX_FIELD	old_field = {"refresh_unsupported", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"refresh_unsupported", "10m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_3030109(void)
{
	const ZBX_FIELD	field = {"refresh_unsupported", "10m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030110(void)
{
	const ZBX_FIELD	field = {"work_period", "1-5,09:00-18:00", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field, NULL);
}

static int	DBpatch_3030111(void)
{
	const ZBX_FIELD	field = {"work_period", "1-5,09:00-18:00", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030112(void)
{
	const ZBX_FIELD	old_field = {"event_expire", "7", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"event_expire", "1w", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_3030113(void)
{
	const ZBX_FIELD	field = {"event_expire", "1w", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030114(void)
{
	const ZBX_FIELD	old_field = {"ok_period", "1800", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"ok_period", "30m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_3030115(void)
{
	const ZBX_FIELD	field = {"ok_period", "30m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030116(void)
{
	const ZBX_FIELD	old_field = {"blink_period", "1800", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"blink_period", "30m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_3030117(void)
{
	const ZBX_FIELD	field = {"blink_period", "30m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030118(void)
{
	const ZBX_FIELD	old_field = {"hk_events_trigger", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"hk_events_trigger", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_3030119(void)
{
	const ZBX_FIELD	field = {"hk_events_trigger", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030120(void)
{
	const ZBX_FIELD	old_field = {"hk_events_internal", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"hk_events_internal", "1d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_3030121(void)
{
	const ZBX_FIELD	field = {"hk_events_internal", "1d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030122(void)
{
	const ZBX_FIELD	old_field = {"hk_events_discovery", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"hk_events_discovery", "1d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_3030123(void)
{
	const ZBX_FIELD	field = {"hk_events_discovery", "1d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030124(void)
{
	const ZBX_FIELD	old_field = {"hk_events_autoreg", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"hk_events_autoreg", "1d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_3030125(void)
{
	const ZBX_FIELD	field = {"hk_events_autoreg", "1d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030126(void)
{
	const ZBX_FIELD	old_field = {"hk_services", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"hk_services", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_3030127(void)
{
	const ZBX_FIELD	field = {"hk_services", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030128(void)
{
	const ZBX_FIELD	old_field = {"hk_audit", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"hk_audit", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_3030129(void)
{
	const ZBX_FIELD	field = {"hk_audit", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030130(void)
{
	const ZBX_FIELD	old_field = {"hk_sessions", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"hk_sessions", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_3030131(void)
{
	const ZBX_FIELD	field = {"hk_sessions", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030132(void)
{
	const ZBX_FIELD	old_field = {"hk_history", "90", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"hk_history", "90d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_3030133(void)
{
	const ZBX_FIELD	field = {"hk_history", "90d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030134(void)
{
	const ZBX_FIELD	old_field = {"hk_trends", "365", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	new_field = {"hk_trends", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &new_field, &old_field);
}

static int	DBpatch_3030135(void)
{
	const ZBX_FIELD	field = {"hk_trends", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030136(void)
{
	const DBpatch_field_conv_t	field_convs[] = {
						{"refresh_unsupported",	DBpatch_conv_sec},
						{"event_expire",	DBpatch_conv_day_limit_25y},
						{"ok_period",		DBpatch_conv_sec},
						{"blink_period",	DBpatch_conv_sec},
						{"hk_events_trigger",	DBpatch_conv_day_limit_25y},
						{"hk_events_internal",	DBpatch_conv_day_limit_25y},
						{"hk_events_discovery",	DBpatch_conv_day_limit_25y},
						{"hk_events_autoreg",	DBpatch_conv_day_limit_25y},
						{"hk_services",		DBpatch_conv_day_limit_25y},
						{"hk_audit",		DBpatch_conv_day_limit_25y},
						{"hk_sessions",		DBpatch_conv_day_limit_25y},
						{"hk_history",		DBpatch_conv_day_limit_25y},
						{"hk_trends",		DBpatch_conv_day_limit_25y},
						{NULL}
					};

	return DBpatch_table_convert("config", "configid", field_convs);
}

static int	DBpatch_3030137(void)
{
	const char	*sql =
			"delete from profiles"
			" where idx in ("
				"'web.items.filter_delay',"
				"'web.items.filter_history',"
				"'web.items.filter_trends',"
				"'web.items.subfilter_history',"
				"'web.items.subfilter_interval',"
				"'web.items.subfilter_trends'"
			")";

	if (ZBX_DB_OK > DBexecute("%s", sql))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_trailing_semicolon_remove(const char *table, const char *recid, const char *field,
		const char *condition)
{
	DB_RESULT	result;
	DB_ROW		row;
	const char	*semicolon;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	int		ret = FAIL;

	result = DBselect("select %s,%s from %s%s", recid, field, table, condition);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (row = DBfetch(result)))
	{
		if (NULL == (semicolon = strrchr(row[1], ';')) || '\0' != *(semicolon + 1))
			continue;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set %s='%.*s' where %s=%s;\n",
				table, field, (int)(semicolon - row[1]), row[1], recid, row[0]);

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	ret = SUCCEED;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_3030138(void)
{
	return DBpatch_trailing_semicolon_remove("config", "configid", "work_period", "");
}

static int	DBpatch_3030139(void)
{
	return DBpatch_trailing_semicolon_remove("media", "mediaid", "period", "");
}

static int	DBpatch_3030140(void)
{
	/* CONDITION_TYPE_TIME_PERIOD */
	return DBpatch_trailing_semicolon_remove("conditions", "conditionid", "value", " where conditiontype=6");
}

static int	DBpatch_3030141(void)
{
	const ZBX_FIELD	field = {"jmx_endpoint", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3030142(void)
{
#define ZBX_DEFAULT_JMX_ENDPOINT	"service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi"
	/* 16 - ITEM_TYPE_JMX */
	if (ZBX_DB_OK > DBexecute("update items set jmx_endpoint='" ZBX_DEFAULT_JMX_ENDPOINT "' where type=16"))
		return FAIL;

	return SUCCEED;
#undef ZBX_DEFAULT_JMX_ENDPOINT
}

static int	DBpatch_3030143(void)
{
	const ZBX_FIELD field = {"maxsessions", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_3030144(void)
{
	const ZBX_FIELD field = {"maxattempts", "3", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_3030145(void)
{
	const ZBX_FIELD field = {"attempt_interval", "10s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_3030146(void)
{
	return DBdrop_index("alerts", "alerts_4");
}

static int	DBpatch_3030147(void)
{
	return DBcreate_index("alerts", "alerts_4", "status", 0);
}

static int	DBpatch_3030148(void)
{
	const ZBX_TABLE table =
			{"dashboard", "dashboardid", 0,
				{
					{"dashboardid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", NULL, NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"private", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030149(void)
{
	const ZBX_FIELD	field = {"userid", NULL, "users", "userid", 0, 0, 0, 0};

	return DBadd_foreign_key("dashboard", 1, &field);
}

static int	DBpatch_3030150(void)
{
	const ZBX_TABLE table =
			{"dashboard_user", "dashboard_userid", 0,
				{
					{"dashboard_userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"dashboardid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"permission", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030151(void)
{
	return DBcreate_index("dashboard_user", "dashboard_user_1", "dashboardid,userid", 1);
}

static int	DBpatch_3030152(void)
{
	const ZBX_FIELD	field = {"dashboardid", NULL, "dashboard", "dashboardid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dashboard_user", 1, &field);
}

static int	DBpatch_3030153(void)
{
	const ZBX_FIELD	field = {"userid", NULL, "users", "userid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dashboard_user", 2, &field);
}

static int	DBpatch_3030154(void)
{
	const ZBX_TABLE table =
			{"dashboard_usrgrp", "dashboard_usrgrpid", 0,
				{
					{"dashboard_usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"dashboardid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"permission", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030155(void)
{
	return DBcreate_index("dashboard_usrgrp", "dashboard_usrgrp_1", "dashboardid,usrgrpid", 1);
}

static int	DBpatch_3030156(void)
{
	const ZBX_FIELD	field = {"dashboardid", NULL, "dashboard", "dashboardid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dashboard_usrgrp", 1, &field);
}

static int	DBpatch_3030157(void)
{
	const ZBX_FIELD	field = {"usrgrpid", NULL, "usrgrp", "usrgrpid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dashboard_usrgrp", 2, &field);
}

static int	DBpatch_3030158(void)
{
	const ZBX_TABLE table =
			{"widget", "widgetid", 0,
				{
					{"widgetid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"dashboardid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"type", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"x", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"y", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"width", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"height", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030159(void)
{
	return DBcreate_index("widget", "widget_1", "dashboardid", 0);
}

static int	DBpatch_3030160(void)
{
	const ZBX_FIELD	field = {"dashboardid", NULL, "dashboard", "dashboardid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget", 1, &field);
}

static int	DBpatch_3030161(void)
{
	const ZBX_TABLE table =
			{"widget_field", "widget_fieldid", 0,
				{
					{"widget_fieldid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"widgetid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value_int", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value_str", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value_groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"value_hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"value_itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"value_graphid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"value_sysmapid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030162(void)
{
	return DBcreate_index("widget_field", "widget_field_1", "widgetid", 0);
}

static int	DBpatch_3030163(void)
{
	return DBcreate_index("widget_field", "widget_field_2", "value_groupid", 0);
}

static int	DBpatch_3030164(void)
{
	return DBcreate_index("widget_field", "widget_field_3", "value_hostid", 0);
}

static int	DBpatch_3030165(void)
{
	return DBcreate_index("widget_field", "widget_field_4", "value_itemid", 0);
}

static int	DBpatch_3030166(void)
{
	return DBcreate_index("widget_field", "widget_field_5", "value_graphid", 0);
}

static int	DBpatch_3030167(void)
{
	return DBcreate_index("widget_field", "widget_field_6", "value_sysmapid", 0);
}

static int	DBpatch_3030168(void)
{
	const ZBX_FIELD	field = {"widgetid", NULL, "widget", "widgetid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 1, &field);
}

static int	DBpatch_3030169(void)
{
	const ZBX_FIELD	field = {"value_groupid", NULL, "groups", "groupid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 2, &field);
}

static int	DBpatch_3030170(void)
{
	const ZBX_FIELD	field = {"value_hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 3, &field);
}

static int	DBpatch_3030171(void)
{
	const ZBX_FIELD	field = {"value_itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 4, &field);
}

static int	DBpatch_3030172(void)
{
	const ZBX_FIELD	field = {"value_graphid", NULL, "graphs", "graphid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 5, &field);
}

static int	DBpatch_3030173(void)
{
	const ZBX_FIELD	field = {"value_sysmapid", NULL, "sysmaps", "sysmapid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 6, &field);
}

static int	DBpatch_3030174(void)
{
	if (ZBX_PROGRAM_TYPE_SERVER == program_type)
	{
		/* type=3 -> type=USER_TYPE_SUPER_ADMIN */
		if (ZBX_DB_OK > DBexecute(
				"insert into dashboard (dashboardid,name,userid,private)"
				" values (1,'Dashboard',(select min(userid) from users where type=3),0)"))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_3030175(void)
{
	int		i;
	const char	*columns = "widgetid,dashboardid,type,name,x,y,width,height";
	const char	*values[] = {
		"1,1,'favgrph','',0,0,2,3",
		"2,1,'favscr','',2,0,2,3",
		"3,1,'favmap','',4,0,2,3",
		"4,1,'problems','',0,3,6,6",
		"5,1,'webovr','',0,9,3,4",
		"6,1,'dscvry','',3,9,3,4",
		"7,1,'hoststat','',6,0,6,4",
		"8,1,'syssum','',6,4,6,4",
		"9,1,'stszbx','',6,8,6,5",
		NULL
	};

	if (ZBX_PROGRAM_TYPE_SERVER == program_type)
	{
		for (i = 0; NULL != values[i]; i++)
		{
			if (ZBX_DB_OK > DBexecute("insert into widget (%s) values (%s)", columns, values[i]))
				return FAIL;
		}
	}

	return SUCCEED;
}

static int	DBpatch_3030176(void)
{
	const ZBX_TABLE table =
			{"task_acknowledge", "taskid", 0,
				{
					{"taskid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"acknowledgeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3030177(void)
{
	const ZBX_FIELD	field = {"taskid", NULL, "task", "taskid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("task_acknowledge", 1, &field);
}

static int	DBpatch_3030178(void)
{
	const ZBX_FIELD	field = {"acknowledgeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("escalations", &field);
}

static int	DBpatch_3030179(void)
{
	const ZBX_FIELD	field = {"ack_shortdata", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("actions", &field);
}

static int	DBpatch_3030180(void)
{
	const ZBX_FIELD	field = {"ack_longdata", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};

	return DBadd_field("actions", &field);
}

static int	DBpatch_3030181(void)
{
	const ZBX_FIELD	field = {"acknowledgeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("alerts", &field);
}

static int	DBpatch_3030182(void)
{
	const ZBX_FIELD	field = {"acknowledgeid", NULL, "acknowledges", "acknowledgeid", 0, ZBX_TYPE_ID, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("alerts", 6, &field);
}

static int	DBpatch_3030183(void)
{
	if (ZBX_DB_OK > DBexecute("update actions set "
			"ack_shortdata='Acknowledged: {TRIGGER.NAME}', "
			"ack_longdata="
				"'{USER.FULLNAME} acknowledged problem at {ACK.DATE} {ACK.TIME} "
				"with the following message:\r\n"
				"{ACK.MESSAGE}\r\n\r\n"
				"Current problem status is {EVENT.STATUS}' "
			"where eventsource=0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3030184(void)
{
	const ZBX_FIELD	field = {"master_itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_3030185(void)
{
	return DBcreate_index("items", "items_7", "master_itemid", 0);
}

static int	DBpatch_3030186(void)
{
	const ZBX_FIELD	field = {"master_itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("items", 5, &field);
}

/* Patches 3030187-3030198 solve the issue ZBX-12505 */

static int	DBpatch_3030187(void)
{
	if (SUCCEED == DBfield_exists("widget", "row"))
	{
		const ZBX_TABLE table =
				{"widget_tmp", "", 0,
					{
						{"widgetid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
						{"x", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
						{"y", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
						{"width", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
						{"height", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
						{0}
					},
					NULL
				};

		return DBcreate_table(&table);
	}

	return SUCCEED;
}

static int	DBpatch_3030188(void)
{
	if (SUCCEED == DBtable_exists("widget_tmp"))
	{
		if (ZBX_DB_OK > DBexecute("insert into widget_tmp (select widgetid,col,row,width,height from widget)"))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_3030189(void)
{
	if (SUCCEED == DBtable_exists("widget_tmp"))
		return DBdrop_field("widget", "width");

	return SUCCEED;
}

static int	DBpatch_3030190(void)
{
	if (SUCCEED == DBtable_exists("widget_tmp"))
		return DBdrop_field("widget", "height");

	return SUCCEED;
}

static int	DBpatch_3030191(void)
{
	if (SUCCEED == DBtable_exists("widget_tmp"))
		return DBdrop_field("widget", "col");

	return SUCCEED;
}

static int	DBpatch_3030192(void)
{
	if (SUCCEED == DBtable_exists("widget_tmp"))
		return DBdrop_field("widget", "row");

	return SUCCEED;
}

static int	DBpatch_3030193(void)
{
	if (SUCCEED == DBtable_exists("widget_tmp"))
	{
		const ZBX_FIELD field = {"x", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

		return DBadd_field("widget", &field);
	}

	return SUCCEED;
}

static int	DBpatch_3030194(void)
{
	if (SUCCEED == DBtable_exists("widget_tmp"))
	{
		const ZBX_FIELD field = {"y", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

		return DBadd_field("widget", &field);
	}

	return SUCCEED;
}

static int	DBpatch_3030195(void)
{
	if (SUCCEED == DBtable_exists("widget_tmp"))
	{
		const ZBX_FIELD field = {"width", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

		return DBadd_field("widget", &field);
	}

	return SUCCEED;
}

static int	DBpatch_3030196(void)
{
	if (SUCCEED == DBtable_exists("widget_tmp"))
	{
		const ZBX_FIELD field = {"height", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

		return DBadd_field("widget", &field);
	}

	return SUCCEED;
}

static int	DBpatch_3030197(void)
{
	if (SUCCEED == DBtable_exists("widget_tmp"))
	{
		DB_RESULT	result;
		DB_ROW		row;
		int		ret = FAIL;

		result = DBselect("select widgetid,x,y,width,height from widget_tmp");

		while (NULL != (row = DBfetch(result)))
		{
			if (ZBX_DB_OK > DBexecute("update widget set x=%s,y=%s,width=%s,height=%s where widgetid=%s",
					row[1], row[2], row[3], row[4], row[0]))
				goto out;
		}

		ret = SUCCEED;
out:
		DBfree_result(result);

		return ret;
	}

	return SUCCEED;
}

static int	DBpatch_3030198(void)
{
	if (SUCCEED == DBtable_exists("widget_tmp"))
		return DBdrop_table("widget_tmp");

	return SUCCEED;
}
#endif

DBPATCH_START(3030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3030000, 0, 1)
DBPATCH_ADD(3030001, 0, 1)
DBPATCH_ADD(3030002, 0, 1)
DBPATCH_ADD(3030003, 0, 1)
DBPATCH_ADD(3030004, 0, 1)
DBPATCH_ADD(3030005, 0, 1)
DBPATCH_ADD(3030006, 0, 1)
DBPATCH_ADD(3030007, 0, 1)
DBPATCH_ADD(3030008, 0, 1)
DBPATCH_ADD(3030009, 0, 1)
DBPATCH_ADD(3030010, 0, 1)
DBPATCH_ADD(3030011, 0, 1)
DBPATCH_ADD(3030012, 0, 1)
DBPATCH_ADD(3030013, 0, 1)
DBPATCH_ADD(3030015, 0, 1)
DBPATCH_ADD(3030016, 0, 1)
DBPATCH_ADD(3030017, 0, 1)
DBPATCH_ADD(3030018, 0, 1)
DBPATCH_ADD(3030019, 0, 1)
DBPATCH_ADD(3030020, 0, 1)
DBPATCH_ADD(3030021, 0, 1)
DBPATCH_ADD(3030022, 0, 1)
DBPATCH_ADD(3030023, 0, 0)
DBPATCH_ADD(3030024, 0, 1)
DBPATCH_ADD(3030025, 0, 1)
DBPATCH_ADD(3030026, 0, 1)
DBPATCH_ADD(3030027, 0, 1)
DBPATCH_ADD(3030028, 0, 1)
DBPATCH_ADD(3030029, 0, 1)
DBPATCH_ADD(3030030, 0, 1)
DBPATCH_ADD(3030031, 0, 1)
DBPATCH_ADD(3030032, 0, 1)
DBPATCH_ADD(3030033, 0, 1)
DBPATCH_ADD(3030034, 0, 1)
DBPATCH_ADD(3030035, 0, 1)
DBPATCH_ADD(3030036, 0, 1)
DBPATCH_ADD(3030037, 0, 1)
DBPATCH_ADD(3030038, 0, 1)
DBPATCH_ADD(3030039, 0, 1)
DBPATCH_ADD(3030040, 0, 1)
DBPATCH_ADD(3030041, 0, 1)
DBPATCH_ADD(3030042, 0, 1)
DBPATCH_ADD(3030043, 0, 1)
DBPATCH_ADD(3030044, 0, 1)
DBPATCH_ADD(3030045, 0, 1)
DBPATCH_ADD(3030046, 0, 1)
DBPATCH_ADD(3030047, 0, 1)
DBPATCH_ADD(3030048, 0, 1)
DBPATCH_ADD(3030049, 0, 1)
DBPATCH_ADD(3030050, 0, 1)
DBPATCH_ADD(3030051, 0, 1)
DBPATCH_ADD(3030052, 0, 1)
DBPATCH_ADD(3030053, 0, 1)
DBPATCH_ADD(3030054, 0, 1)
DBPATCH_ADD(3030055, 0, 1)
DBPATCH_ADD(3030056, 0, 1)
DBPATCH_ADD(3030057, 0, 1)
DBPATCH_ADD(3030058, 0, 1)
DBPATCH_ADD(3030059, 0, 1)
DBPATCH_ADD(3030060, 0, 1)
DBPATCH_ADD(3030061, 0, 1)
DBPATCH_ADD(3030062, 0, 1)
DBPATCH_ADD(3030063, 0, 1)
DBPATCH_ADD(3030064, 0, 1)
DBPATCH_ADD(3030065, 0, 1)
DBPATCH_ADD(3030066, 0, 1)
DBPATCH_ADD(3030067, 0, 1)
DBPATCH_ADD(3030068, 0, 1)
DBPATCH_ADD(3030069, 0, 1)
DBPATCH_ADD(3030070, 0, 1)
DBPATCH_ADD(3030071, 0, 1)
DBPATCH_ADD(3030072, 0, 1)
DBPATCH_ADD(3030073, 0, 1)
DBPATCH_ADD(3030074, 0, 1)
DBPATCH_ADD(3030075, 0, 1)
DBPATCH_ADD(3030076, 0, 1)
DBPATCH_ADD(3030077, 0, 1)
DBPATCH_ADD(3030078, 0, 1)
DBPATCH_ADD(3030079, 0, 1)
DBPATCH_ADD(3030080, 0, 1)
DBPATCH_ADD(3030081, 0, 1)
DBPATCH_ADD(3030082, 0, 1)
DBPATCH_ADD(3030083, 0, 1)
DBPATCH_ADD(3030084, 0, 1)
DBPATCH_ADD(3030085, 0, 1)
DBPATCH_ADD(3030086, 0, 1)
DBPATCH_ADD(3030087, 0, 1)
DBPATCH_ADD(3030088, 0, 1)
DBPATCH_ADD(3030089, 0, 1)
DBPATCH_ADD(3030090, 0, 1)
DBPATCH_ADD(3030091, 0, 1)
DBPATCH_ADD(3030092, 0, 1)
DBPATCH_ADD(3030093, 0, 1)
DBPATCH_ADD(3030094, 0, 1)
DBPATCH_ADD(3030095, 0, 1)
DBPATCH_ADD(3030096, 0, 1)
DBPATCH_ADD(3030097, 0, 1)
DBPATCH_ADD(3030098, 0, 1)
DBPATCH_ADD(3030099, 0, 1)
DBPATCH_ADD(3030100, 0, 1)
DBPATCH_ADD(3030101, 0, 1)
DBPATCH_ADD(3030102, 0, 1)
DBPATCH_ADD(3030103, 0, 1)
DBPATCH_ADD(3030104, 0, 1)
DBPATCH_ADD(3030105, 0, 1)
DBPATCH_ADD(3030106, 0, 1)
DBPATCH_ADD(3030107, 0, 1)
DBPATCH_ADD(3030108, 0, 1)
DBPATCH_ADD(3030109, 0, 1)
DBPATCH_ADD(3030110, 0, 1)
DBPATCH_ADD(3030111, 0, 1)
DBPATCH_ADD(3030112, 0, 1)
DBPATCH_ADD(3030113, 0, 1)
DBPATCH_ADD(3030114, 0, 1)
DBPATCH_ADD(3030115, 0, 1)
DBPATCH_ADD(3030116, 0, 1)
DBPATCH_ADD(3030117, 0, 1)
DBPATCH_ADD(3030118, 0, 1)
DBPATCH_ADD(3030119, 0, 1)
DBPATCH_ADD(3030120, 0, 1)
DBPATCH_ADD(3030121, 0, 1)
DBPATCH_ADD(3030122, 0, 1)
DBPATCH_ADD(3030123, 0, 1)
DBPATCH_ADD(3030124, 0, 1)
DBPATCH_ADD(3030125, 0, 1)
DBPATCH_ADD(3030126, 0, 1)
DBPATCH_ADD(3030127, 0, 1)
DBPATCH_ADD(3030128, 0, 1)
DBPATCH_ADD(3030129, 0, 1)
DBPATCH_ADD(3030130, 0, 1)
DBPATCH_ADD(3030131, 0, 1)
DBPATCH_ADD(3030132, 0, 1)
DBPATCH_ADD(3030133, 0, 1)
DBPATCH_ADD(3030134, 0, 1)
DBPATCH_ADD(3030135, 0, 1)
DBPATCH_ADD(3030136, 0, 1)
DBPATCH_ADD(3030137, 0, 1)
DBPATCH_ADD(3030138, 0, 1)
DBPATCH_ADD(3030139, 0, 1)
DBPATCH_ADD(3030140, 0, 1)
DBPATCH_ADD(3030141, 0, 1)
DBPATCH_ADD(3030142, 0, 1)
DBPATCH_ADD(3030143, 0, 1)
DBPATCH_ADD(3030144, 0, 1)
DBPATCH_ADD(3030145, 0, 1)
DBPATCH_ADD(3030146, 0, 1)
DBPATCH_ADD(3030147, 0, 1)
DBPATCH_ADD(3030148, 0, 1)
DBPATCH_ADD(3030149, 0, 1)
DBPATCH_ADD(3030150, 0, 1)
DBPATCH_ADD(3030151, 0, 1)
DBPATCH_ADD(3030152, 0, 1)
DBPATCH_ADD(3030153, 0, 1)
DBPATCH_ADD(3030154, 0, 1)
DBPATCH_ADD(3030155, 0, 1)
DBPATCH_ADD(3030156, 0, 1)
DBPATCH_ADD(3030157, 0, 1)
DBPATCH_ADD(3030158, 0, 1)
DBPATCH_ADD(3030159, 0, 1)
DBPATCH_ADD(3030160, 0, 1)
DBPATCH_ADD(3030161, 0, 1)
DBPATCH_ADD(3030162, 0, 1)
DBPATCH_ADD(3030163, 0, 1)
DBPATCH_ADD(3030164, 0, 1)
DBPATCH_ADD(3030165, 0, 1)
DBPATCH_ADD(3030166, 0, 1)
DBPATCH_ADD(3030167, 0, 1)
DBPATCH_ADD(3030168, 0, 1)
DBPATCH_ADD(3030169, 0, 1)
DBPATCH_ADD(3030170, 0, 1)
DBPATCH_ADD(3030171, 0, 1)
DBPATCH_ADD(3030172, 0, 1)
DBPATCH_ADD(3030173, 0, 1)
DBPATCH_ADD(3030174, 0, 1)
DBPATCH_ADD(3030175, 0, 1)
DBPATCH_ADD(3030176, 0, 1)
DBPATCH_ADD(3030177, 0, 1)
DBPATCH_ADD(3030178, 0, 1)
DBPATCH_ADD(3030179, 0, 1)
DBPATCH_ADD(3030180, 0, 1)
DBPATCH_ADD(3030181, 0, 1)
DBPATCH_ADD(3030182, 0, 1)
DBPATCH_ADD(3030183, 0, 1)
DBPATCH_ADD(3030184, 0, 1)
DBPATCH_ADD(3030185, 0, 1)
DBPATCH_ADD(3030186, 0, 1)
DBPATCH_ADD(3030187, 0, 1)
DBPATCH_ADD(3030188, 0, 1)
DBPATCH_ADD(3030189, 0, 1)
DBPATCH_ADD(3030190, 0, 1)
DBPATCH_ADD(3030191, 0, 1)
DBPATCH_ADD(3030192, 0, 1)
DBPATCH_ADD(3030193, 0, 1)
DBPATCH_ADD(3030194, 0, 1)
DBPATCH_ADD(3030195, 0, 1)
DBPATCH_ADD(3030196, 0, 1)
DBPATCH_ADD(3030197, 0, 1)
DBPATCH_ADD(3030198, 0, 1)

DBPATCH_END()
