/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
 * 5.0 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_4050001(void)
{
	return DBdrop_foreign_key("items", 1);
}

static int	DBpatch_4050002(void)
{
	return DBdrop_index("items", "items_1");
}

static int	DBpatch_4050003(void)
{
	const ZBX_FIELD	field = {"key_", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field, NULL);
}

static int	DBpatch_4050004(void)
{
#ifdef HAVE_MYSQL
	return DBcreate_index("items", "items_1", "hostid,key_(1021)", 0);
#else
	return DBcreate_index("items", "items_1", "hostid,key_", 0);
#endif
}

static int	DBpatch_4050005(void)
{
	const ZBX_FIELD	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("items", 1, &field);
}

static int	DBpatch_4050006(void)
{
	const ZBX_FIELD	field = {"key_", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("item_discovery", &field, NULL);
}

static int	DBpatch_4050007(void)
{
	const ZBX_FIELD	field = {"key_", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("dchecks", &field, NULL);
}

static int	DBpatch_4050011(void)
{
#if defined(HAVE_IBM_DB2) || defined(HAVE_POSTGRESQL)
	const char *cast_value_str = "bigint";
#elif defined(HAVE_MYSQL)
	const char *cast_value_str = "unsigned";
#elif defined(HAVE_ORACLE)
	const char *cast_value_str = "number(20)";
#endif

	if (ZBX_DB_OK > DBexecute(
			"update profiles"
			" set value_id=CAST(value_str as %s),"
				" value_str='',"
				" type=1"	/* PROFILE_TYPE_ID */
			" where type=3"	/* PROFILE_TYPE_STR */
				" and (idx='web.latest.filter.groupids' or idx='web.latest.filter.hostids')", cast_value_str))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_4050012(void)
{
	const ZBX_FIELD	field = {"passwd", "", NULL, NULL, 60, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("users", &field, NULL);
}

static int	DBpatch_4050013(void)
{
	int		i;
	const char	*values[] = {
			"web.usergroup.filter_users_status", "web.usergroup.filter_user_status",
			"web.usergrps.php.sort", "web.usergroup.sort",
			"web.usergrps.php.sortorder", "web.usergroup.sortorder",
			"web.adm.valuemapping.php.sortorder", "web.valuemap.list.sortorder",
			"web.adm.valuemapping.php.sort", "web.valuemap.list.sort",
			"web.latest.php.sort", "web.latest.sort",
			"web.latest.php.sortorder", "web.latest.sortorder",
			"web.paging.lastpage", "web.pager.entity",
			"web.paging.page", "web.pager.page"
		};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; i < (int)ARRSIZE(values); i += 2)
	{
		if (ZBX_DB_OK > DBexecute("update profiles set idx='%s' where idx='%s'", values[i + 1], values[i]))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_4050014(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	int		ret = SUCCEED;
	char		*sql = NULL, *name = NULL, *name_esc;
	size_t		sql_alloc = 0, sql_offset = 0;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select wf.widget_fieldid,wf.name"
			" from widget_field wf,widget w"
			" where wf.widgetid=w.widgetid"
				" and w.type='navtree'"
				" and wf.name like 'map.%%' or wf.name like 'mapid.%%'"
			);

	while (NULL != (row = DBfetch(result)))
	{
		if (0 == strncmp(row[1], "map.", 4))
		{
			name = zbx_dsprintf(name, "navtree.%s", row[1] + 4);
		}
		else
		{
			name = zbx_dsprintf(name, "navtree.sys%s", row[1]);
		}

		name_esc = DBdyn_escape_string_len(name, 255);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"update widget_field set name='%s' where widget_fieldid=%s;\n", name_esc, row[0]);

		zbx_free(name_esc);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
		ret = FAIL;
out:
	DBfree_result(result);
	zbx_free(sql);
	zbx_free(name);

	return ret;
}

static int	DBpatch_4050015(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		time_period_id, every;
	int			invalidate = 0;
	const ZBX_TABLE		*timeperiods;
	const ZBX_FIELD		*field;

	if (NULL != (timeperiods = DBget_table("timeperiods")) &&
			NULL != (field = DBget_field(timeperiods, "every")))
	{
		ZBX_STR2UINT64(every, field->default_value);
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	result = DBselect("select timeperiodid from timeperiods where every=0");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(time_period_id, row[0]);

		zabbix_log(LOG_LEVEL_WARNING, "Invalid maintenance time period found: "ZBX_FS_UI64
				", changing \"every\" to "ZBX_FS_UI64, time_period_id, every);
		invalidate = 1;
	}

	DBfree_result(result);

	if (0 != invalidate &&
			ZBX_DB_OK > DBexecute("update timeperiods set every=1 where timeperiodid!=0 and every=0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4050016(void)
{
	const ZBX_TABLE	table =
			{"media_type_message", "mediatype_messageid", 0,
				{
					{"mediatype_messageid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"mediatypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"eventsource", NULL, NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"recovery", NULL, NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"subject", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"message", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_4050017(void)
{
	const ZBX_FIELD	field = {"mediatypeid", NULL, "media_type", "mediatypeid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("media_type_message", 1, &field);
}

static int	DBpatch_4050018(void)
{
	return DBcreate_index("media_type_message", "media_type_message_1", "mediatypeid,eventsource,recovery", 1);
}

static int	DBpatch_4050019(void)
{
	const ZBX_FIELD	field = {"default_msg", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("opmessage", &field);
}

static int	DBpatch_4050020(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	operationid;
	int		ret = SUCCEED, res, col;
	char		*subject, *message;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = DBselect(
			"select m.operationid,o.recovery,a.def_shortdata,a.def_longdata,a.r_shortdata,a.r_longdata,"
			"a.ack_shortdata,a.ack_longdata from opmessage m"
			" join operations o on m.operationid=o.operationid"
			" left join actions a on o.actionid=a.actionid"
			" where m.default_msg='1' and o.recovery in (0,1,2)");

	while (NULL != (row = DBfetch(result)))
	{
		col = 2 + (atoi(row[1]) * 2);
		subject = DBdyn_escape_string(row[col]);
		message = DBdyn_escape_string(row[col + 1]);
		ZBX_DBROW2UINT64(operationid, row[0]);

		res = DBexecute("update opmessage set subject='%s',message='%s',default_msg='0'"
				" where operationid=" ZBX_FS_UI64, subject, message, operationid);

		zbx_free(subject);
		zbx_free(message);

		if (ZBX_DB_OK > res)
		{
			ret = FAIL;
			break;
		}
	}
	DBfree_result(result);

	return ret;
}

static int	DBpatch_4050021(void)
{
	char	*messages[3][3][4] =
			{
				{
					{
						"Problem started at {EVENT.TIME} on {EVENT.DATE}\n"
						"Problem name: {EVENT.NAME}\n"
						"Host: {HOST.NAME}\n"
						"Severity: {EVENT.SEVERITY}\n"
						"Operational data: {EVENT.OPDATA}\n"
						"Original problem ID: {EVENT.ID}\n"
						"{TRIGGER.URL}"
						,
						"<b>Problem started</b> at {EVENT.TIME} on {EVENT.DATE}<br>"
						"<b>Problem name:</b> {EVENT.NAME}<br>"
						"<b>Host:</b> {HOST.NAME}<br>"
						"<b>Severity:</b> {EVENT.SEVERITY}<br>"
						"<b>Operational data:</b> {EVENT.OPDATA}<br>"
						"<b>Original problem ID:</b> {EVENT.ID}<br>"
						"{TRIGGER.URL}"
						,
						"{EVENT.SEVERITY}: {EVENT.NAME}\n"
						"Host: {HOST.NAME}\n"
						"{EVENT.DATE} {EVENT.TIME}"
						,
						"Problem: {EVENT.NAME}"
					},
					{
						"Problem has been resolved at "
						"{EVENT.RECOVERY.TIME} on {EVENT.RECOVERY.DATE}\n"
						"Problem name: {EVENT.NAME}\n"
						"Host: {HOST.NAME}\n"
						"Severity: {EVENT.SEVERITY}\n"
						"Original problem ID: {EVENT.ID}\n"
						"{TRIGGER.URL}"
						,
						"<b>Problem has been resolved</b> at {EVENT.RECOVERY.TIME} on "
						"{EVENT.RECOVERY.DATE}<br>"
						"<b>Problem name:</b> {EVENT.NAME}<br>"
						"<b>Host:</b> {HOST.NAME}<br>"
						"<b>Severity:</b> {EVENT.SEVERITY}<br>"
						"<b>Original problem ID:</b> {EVENT.ID}<br>"
						"{TRIGGER.URL}"
						,
						"RESOLVED: {EVENT.NAME}\n"
						"Host: {HOST.NAME}\n"
						"{EVENT.DATE} {EVENT.TIME}"
						,
						"Resolved: {EVENT.NAME}"
					},
					{
						"{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem at "
						"{EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.\n"
						"{EVENT.UPDATE.MESSAGE}\n"
						"\n"
						"Current problem status is {EVENT.STATUS}, acknowledged: "
						"{EVENT.ACK.STATUS}."
						,
						"<b>{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem</b> at "
						"{EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}.<br>"
						"{EVENT.UPDATE.MESSAGE}<br>"
						"<br>"
						"<b>Current problem status:</b> {EVENT.STATUS}<br>"
						"<b>Acknowledged:</b> {EVENT.ACK.STATUS}."
						,
						"{USER.FULLNAME} {EVENT.UPDATE.ACTION} problem at "
						"{EVENT.UPDATE.DATE} {EVENT.UPDATE.TIME}"
						,
						"Updated problem: {EVENT.NAME}"
					}
				},
				{
					{
						"Discovery rule: {DISCOVERY.RULE.NAME}\n"
						"\n"
						"Device IP: {DISCOVERY.DEVICE.IPADDRESS}\n"
						"Device DNS: {DISCOVERY.DEVICE.DNS}\n"
						"Device status: {DISCOVERY.DEVICE.STATUS}\n"
						"Device uptime: {DISCOVERY.DEVICE.UPTIME}\n"
						"\n"
						"Device service name: {DISCOVERY.SERVICE.NAME}\n"
						"Device service port: {DISCOVERY.SERVICE.PORT}\n"
						"Device service status: {DISCOVERY.SERVICE.STATUS}\n"
						"Device service uptime: {DISCOVERY.SERVICE.UPTIME}"
						,
						"<b>Discovery rule:</b> {DISCOVERY.RULE.NAME}<br>"
						"<br>"
						"<b>Device IP:</b> {DISCOVERY.DEVICE.IPADDRESS}<br>"
						"<b>Device DNS:</b> {DISCOVERY.DEVICE.DNS}<br>"
						"<b>Device status:</b> {DISCOVERY.DEVICE.STATUS}<br>"
						"<b>Device uptime:</b> {DISCOVERY.DEVICE.UPTIME}<br>"
						"<br>"
						"<b>Device service name:</b> {DISCOVERY.SERVICE.NAME}<br>"
						"<b>Device service port:</b> {DISCOVERY.SERVICE.PORT}<br>"
						"<b>Device service status:</b> {DISCOVERY.SERVICE.STATUS}<br>"
						"<b>Device service uptime:</b> {DISCOVERY.SERVICE.UPTIME}"
						,
						"Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}"
						,
						"Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}"
					},
					{NULL, NULL, NULL, NULL},
					{NULL, NULL, NULL, NULL}
				},
				{
					{
						"Host name: {HOST.HOST}\n"
						"Host IP: {HOST.IP}\n"
						"Agent port: {HOST.PORT}"
						,
						"<b>Host name:</b> {HOST.HOST}<br>"
						"<b>Host IP:</b> {HOST.IP}<br>"
						"<b>Agent port:</b> {HOST.PORT}"
						,
						"Autoregistration: {HOST.HOST}\n"
						"Host IP: {HOST.IP}\n"
						"Agent port: {HOST.PORT}"
						,
						"Autoregistration: {HOST.HOST}"
					},
					{NULL, NULL, NULL, NULL},
					{NULL, NULL, NULL, NULL}
				}
			};
	int		ret = SUCCEED, res;
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	mediatypeid, mediatypemessageid = 1;
	int		content_type, i, k;
	char		*msg_esc = NULL, *subj_esc = NULL;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = DBselect("select mediatypeid,type,content_type from media_type");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(mediatypeid, row[0]);

		switch (atoi(row[1]))
		{
			case MEDIA_TYPE_SMS:
				content_type = 2;
				break;
			case MEDIA_TYPE_EMAIL:
				content_type = atoi(row[2]);
				break;
			default:
				content_type = 0;
		}

		for (i = 0; 2 >= i; i++)
		{
			for (k = 0; 2 >= k; k++)
			{
				if (NULL != messages[i][k][0])
				{
					msg_esc = DBdyn_escape_string(messages[i][k][content_type]);
					subj_esc = content_type == 2 ? NULL : DBdyn_escape_string(messages[i][k][3]);

					res = DBexecute(
							"insert into media_type_message"
							" (mediatype_messageid,mediatypeid,eventsource,recovery,"
							"subject,message)"
							" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%i,%i,'%s','%s')",
							mediatypemessageid++, mediatypeid, i, k,
							ZBX_NULL2EMPTY_STR(subj_esc), msg_esc);

					zbx_free(msg_esc);
					zbx_free(subj_esc);

					if (ZBX_DB_OK > res)
					{
						ret = FAIL;
						goto out;
					}
				}
			}
		}
	}
out:
	DBfree_result(result);

	return ret;
}

static int	DBpatch_4050022(void)
{
	return DBdrop_field("actions", "def_shortdata");
}

static int	DBpatch_4050023(void)
{
	return DBdrop_field("actions", "def_longdata");
}

static int	DBpatch_4050024(void)
{
	return DBdrop_field("actions", "r_shortdata");
}

static int	DBpatch_4050025(void)
{
	return DBdrop_field("actions", "r_longdata");
}

static int	DBpatch_4050026(void)
{
	return DBdrop_field("actions", "ack_shortdata");
}

static int	DBpatch_4050027(void)
{
	return DBdrop_field("actions", "ack_longdata");
}

static int	DBpatch_4050028(void)
{
	const ZBX_TABLE table =
		{"module", "moduleid", 0,
			{
				{"moduleid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"id", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"relative_path", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"config", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050030(void)
{
	return SUCCEED;
}

static int	DBpatch_4050031(void)
{
	const ZBX_TABLE table =
			{"task_data", "taskid", 0,
				{
					{"taskid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"data", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
					{"parent_taskid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_4050032(void)
{
	const ZBX_FIELD	field = {"taskid", NULL, "task", "taskid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("task_data", 1, &field);
}

static int	DBpatch_4050033(void)
{
	const ZBX_TABLE	table =
			{"task_result", "taskid", 0,
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

static int	DBpatch_4050034(void)
{
	return DBcreate_index("task_result", "task_result_1", "parent_taskid", 0);
}

static int	DBpatch_4050035(void)
{
	const ZBX_FIELD	field = {"taskid", NULL, "task", "taskid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("task_result", 1, &field);
}

static int	DBpatch_4050036(void)
{
	int		i;
	const char	*values[] = {
			"web.latest.groupid", "web.latest.hostid", "web.latest.graphid", "web..groupid",
			"web..hostid", "web.view.groupid", "web.view.hostid", "web.view.graphid",
			"web.config.groupid", "web.config.hostid", "web.templates.php.groupid", "web.cm.groupid",
			"web.httpmon.php.sort", "web.httpmon.php.sortorder", "web.avail_report.0.hostid",
			"web.avail_report.0.groupid", "web.charts.php.from", "web.charts.php.to"
		};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; i < (int)ARRSIZE(values); i++)
	{
		if (ZBX_DB_OK > DBexecute("delete from profiles where idx='%s'", values[i]))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_4050037(void)
{
	return DBdrop_field("config", "dropdown_first_entry");
}

static int	DBpatch_4050038(void)
{
	return DBdrop_field("config", "dropdown_first_remember");
}

#endif

DBPATCH_START(4050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(4050001, 0, 1)
DBPATCH_ADD(4050002, 0, 1)
DBPATCH_ADD(4050003, 0, 1)
DBPATCH_ADD(4050004, 0, 1)
DBPATCH_ADD(4050005, 0, 1)
DBPATCH_ADD(4050006, 0, 1)
DBPATCH_ADD(4050007, 0, 1)
DBPATCH_ADD(4050011, 0, 1)
DBPATCH_ADD(4050012, 0, 1)
DBPATCH_ADD(4050013, 0, 1)
DBPATCH_ADD(4050014, 0, 1)
DBPATCH_ADD(4050015, 0, 1)
DBPATCH_ADD(4050016, 0, 1)
DBPATCH_ADD(4050017, 0, 1)
DBPATCH_ADD(4050018, 0, 1)
DBPATCH_ADD(4050019, 0, 1)
DBPATCH_ADD(4050020, 0, 1)
DBPATCH_ADD(4050021, 0, 1)
DBPATCH_ADD(4050022, 0, 1)
DBPATCH_ADD(4050023, 0, 1)
DBPATCH_ADD(4050024, 0, 1)
DBPATCH_ADD(4050025, 0, 1)
DBPATCH_ADD(4050026, 0, 1)
DBPATCH_ADD(4050027, 0, 1)
DBPATCH_ADD(4050028, 0, 1)
DBPATCH_ADD(4050030, 0, 1)
DBPATCH_ADD(4050031, 0, 1)
DBPATCH_ADD(4050032, 0, 1)
DBPATCH_ADD(4050033, 0, 1)
DBPATCH_ADD(4050034, 0, 1)
DBPATCH_ADD(4050035, 0, 1)
DBPATCH_ADD(4050036, 0, 1)
DBPATCH_ADD(4050037, 0, 1)
DBPATCH_ADD(4050038, 0, 1)


DBPATCH_END()
