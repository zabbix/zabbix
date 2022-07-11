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
#include "zbxalgo.h"
#include "../zbxalgo/vectorimpl.h"

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
	const ZBX_FIELD	field = {"note", "0", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBrename_field("auditlog", "details", &field);
}

static int	DBpatch_4050037(void)
{
	const ZBX_FIELD	field = {"note", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("auditlog", &field);
}

static int	DBpatch_4050038(void)
{
	return DBcreate_index("auditlog", "auditlog_3", "resourcetype,resourceid", 0);
}

static int	DBpatch_4050039(void)
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
			"web.paging.page", "web.pager.page",
			"web.auditlogs.filter.active", "web.auditlog.filter.active",
			"web.auditlogs.filter.action", "web.auditlog.filter.action",
			"web.auditlogs.filter.alias", "web.auditlog.filter.alias",
			"web.auditlogs.filter.resourcetype", "web.auditlog.filter.resourcetype",
			"web.auditlogs.filter.from", "web.auditlog.filter.from",
			"web.auditlogs.filter.to", "web.auditlog.filter.to"
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

static int	DBpatch_4050040(void)
{
	const ZBX_FIELD	field = {"resourceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_default("auditlog", &field);
}

static int	DBpatch_4050041(void)
{
	const ZBX_FIELD	field = {"resourceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBdrop_not_null("auditlog", &field);
}

static int	DBpatch_4050042(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update auditlog set resourceid=null where resourceid=0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4050043(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from profiles where idx='web.screens.graphid'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4050044(void)
{
	const ZBX_TABLE table =
		{"interface_snmp", "interfaceid", 0,
			{
				{"interfaceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"version", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"bulk", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"community", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"securityname", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"securitylevel", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"authpassphrase", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"privpassphrase", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"authprotocol", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"privprotocol", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"contextname", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050045(void)
{
	const ZBX_FIELD	field = {"interfaceid", NULL, "interface", "interfaceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("interface_snmp", 1, &field);
}

typedef struct
{
	zbx_uint64_t	interfaceid;
	char		*community;
	char		*securityname;
	char		*authpassphrase;
	char		*privpassphrase;
	char		*contextname;
	unsigned char	securitylevel;
	unsigned char	authprotocol;
	unsigned char	privprotocol;
	unsigned char	version;
	unsigned char	bulk;
	zbx_uint64_t	item_interfaceid;
	char		*item_port;
	unsigned char	skip;
}
dbu_snmp_if_t;

typedef struct
{
	zbx_uint64_t	interfaceid;
	zbx_uint64_t	hostid;
	char		*ip;
	char		*dns;
	char		*port;
	unsigned char	type;
	unsigned char	main;
	unsigned char	useip;
}
dbu_interface_t;

ZBX_PTR_VECTOR_DECL(dbu_interface, dbu_interface_t)
ZBX_PTR_VECTOR_IMPL(dbu_interface, dbu_interface_t)
ZBX_PTR_VECTOR_DECL(dbu_snmp_if, dbu_snmp_if_t)
ZBX_PTR_VECTOR_IMPL(dbu_snmp_if, dbu_snmp_if_t)

static void	db_interface_free(dbu_interface_t interface)
{
	zbx_free(interface.ip);
	zbx_free(interface.dns);
	zbx_free(interface.port);
}

static void	db_snmpinterface_free(dbu_snmp_if_t snmp)
{
	zbx_free(snmp.community);
	zbx_free(snmp.securityname);
	zbx_free(snmp.authpassphrase);
	zbx_free(snmp.privpassphrase);
	zbx_free(snmp.contextname);
	zbx_free(snmp.item_port);
}

static int	db_snmp_if_cmp(const dbu_snmp_if_t *snmp1, const dbu_snmp_if_t *snmp2)
{
#define ZBX_RETURN_IF_NOT_EQUAL_STR(s1, s2)	\
	if (0 != (ret = strcmp(s1, s2)))	\
		return ret;

	int	ret;

	ZBX_RETURN_IF_NOT_EQUAL(snmp1->securitylevel, snmp2->securitylevel);
	ZBX_RETURN_IF_NOT_EQUAL(snmp1->authprotocol, snmp2->authprotocol);
	ZBX_RETURN_IF_NOT_EQUAL(snmp1->privprotocol, snmp2->privprotocol);
	ZBX_RETURN_IF_NOT_EQUAL(snmp1->version, snmp2->version);
	ZBX_RETURN_IF_NOT_EQUAL(snmp1->bulk, snmp2->bulk);
	ZBX_RETURN_IF_NOT_EQUAL_STR(snmp1->community, snmp2->community);
	ZBX_RETURN_IF_NOT_EQUAL_STR(snmp1->securityname, snmp2->securityname);
	ZBX_RETURN_IF_NOT_EQUAL_STR(snmp1->authpassphrase, snmp2->authpassphrase);
	ZBX_RETURN_IF_NOT_EQUAL_STR(snmp1->privpassphrase, snmp2->privpassphrase);
	ZBX_RETURN_IF_NOT_EQUAL_STR(snmp1->contextname, snmp2->contextname);

	return 0;

#undef ZBX_RETURN_IF_NOT_EQUAL_STR
}

static int	db_snmp_if_newid_cmp(const dbu_snmp_if_t *snmp1, const dbu_snmp_if_t *snmp2)
{
	ZBX_RETURN_IF_NOT_EQUAL(snmp1->interfaceid, snmp2->interfaceid);

	return db_snmp_if_cmp(snmp1,snmp2);
}

static int	db_snmp_new_if_find(const dbu_snmp_if_t *snmp, const zbx_vector_dbu_snmp_if_t *snmp_new_ifs,
		const zbx_vector_dbu_interface_t *interfaces, const char *if_port)
{
	int		i, index;
	dbu_interface_t	id, *interface;

	for (i = snmp_new_ifs->values_num - 1; i >= 0 &&
			snmp->item_interfaceid == snmp_new_ifs->values[i].item_interfaceid; i--)
	{
		if (0 != db_snmp_if_cmp(snmp, &snmp_new_ifs->values[i]))
			continue;

		if ('\0' != *snmp->item_port && 0 != strcmp(snmp->item_port, snmp_new_ifs->values[i].item_port))
			continue;

		id.interfaceid = snmp_new_ifs->values[i].interfaceid;
		index = zbx_vector_dbu_interface_bsearch(interfaces, id, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		interface = &interfaces->values[index];

		if ('\0' == *snmp->item_port && 0 != strcmp(if_port, interface->port))
			continue;

		return i;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: loading a set of unique combination of snmp data within a single  *
 *          interface and associated interface data                           *
 *                                                                            *
 * Parameters: snmp_ifs     - [OUT] snmp data linked with existing interfaces *
 *             new_ifs      - [OUT] new interfaces for snmp data              *
 *             snmp_new_ifs - [OUT] snmp data associated with new interfaces  *
 *                                                                            *
 ******************************************************************************/
static void	DBpatch_load_data(zbx_vector_dbu_snmp_if_t *snmp_ifs, zbx_vector_dbu_interface_t *new_ifs,
		zbx_vector_dbu_snmp_if_t *snmp_new_ifs)
{
#define ITEM_TYPE_SNMPv1	1
#define ITEM_TYPE_SNMPv2c	4
#define ITEM_TYPE_SNMPv3	6

	DB_RESULT	result;
	DB_ROW		row;
	int		index;

	result = DBselect(
			"select distinct "
				"i.interfaceid,"
				"i.type,"
				"f.bulk,"
				"i.snmp_community,"
				"i.snmpv3_securityname,"
				"i.snmpv3_securitylevel,"
				"i.snmpv3_authpassphrase,"
				"i.snmpv3_privpassphrase,"
				"i.snmpv3_authprotocol,"
				"i.snmpv3_privprotocol,"
				"i.snmpv3_contextname,"
				"i.port,"
				"i.hostid,"
				"f.type,"
				"f.useip,"
				"f.ip,"
				"f.dns,"
				"f.port"
			" from items i"
				" join hosts h on i.hostid=h.hostid"
				" join interface f on i.interfaceid=f.interfaceid"
			" where i.type in (%d,%d,%d)"
				" and h.status in (0,1)"
			" order by i.interfaceid asc,i.type asc,i.port asc,i.snmp_community asc",
			ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c, ITEM_TYPE_SNMPv3);

	while (NULL != (row = DBfetch(result)))
	{
		dbu_interface_t	interface;
		dbu_snmp_if_t	snmp;
		unsigned char	item_type;
		const char 	*if_port;

		ZBX_DBROW2UINT64(snmp.item_interfaceid, row[0]);
		ZBX_STR2UCHAR(item_type, row[1]);
		ZBX_STR2UCHAR(snmp.bulk, row[2]);
		snmp.community = zbx_strdup(NULL, row[3]);
		snmp.securityname = zbx_strdup(NULL, row[4]);
		ZBX_STR2UCHAR(snmp.securitylevel, row[5]);
		snmp.authpassphrase = zbx_strdup(NULL, row[6]);
		snmp.privpassphrase = zbx_strdup(NULL, row[7]);
		ZBX_STR2UCHAR(snmp.authprotocol, row[8]);
		ZBX_STR2UCHAR(snmp.privprotocol, row[9]);
		snmp.contextname = zbx_strdup(NULL, row[10]);
		snmp.item_port = zbx_strdup(NULL, row[11]);
		snmp.skip = 0;
		if_port = row[17];

		if (ITEM_TYPE_SNMPv1 == item_type)
			snmp.version = ZBX_IF_SNMP_VERSION_1;
		else if (ITEM_TYPE_SNMPv2c == item_type)
			snmp.version = ZBX_IF_SNMP_VERSION_2;
		else
			snmp.version = ZBX_IF_SNMP_VERSION_3;

		snmp.interfaceid = snmp.item_interfaceid;
		index = FAIL;

		if (('\0' == *snmp.item_port || 0 == strcmp(snmp.item_port, if_port)) &&
				FAIL == (index = zbx_vector_dbu_snmp_if_bsearch(snmp_ifs, snmp,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			zbx_vector_dbu_snmp_if_append(snmp_ifs, snmp);
			continue;
		}
		else if (FAIL != index && 0 == db_snmp_if_newid_cmp(&snmp_ifs->values[index], &snmp))
		{
			db_snmpinterface_free(snmp);
			continue;
		}
		else if (0 < snmp_new_ifs->values_num &&
				FAIL != (index = db_snmp_new_if_find(&snmp, snmp_new_ifs, new_ifs, if_port)))
		{
			snmp.skip = 1;
			snmp.interfaceid = snmp_new_ifs->values[index].interfaceid;
			zbx_vector_dbu_snmp_if_append(snmp_new_ifs, snmp);
			continue;
		}

		snmp.interfaceid = DBget_maxid("interface");

		zbx_vector_dbu_snmp_if_append(snmp_new_ifs, snmp);

		interface.interfaceid = snmp.interfaceid;
		ZBX_DBROW2UINT64(interface.hostid, row[12]);
		interface.main = 0;
		ZBX_STR2UCHAR(interface.type, row[13]);
		ZBX_STR2UCHAR(interface.useip, row[14]);
		interface.ip = zbx_strdup(NULL, row[15]);
		interface.dns = zbx_strdup(NULL, row[16]);

		if ('\0' != *snmp.item_port)
			interface.port = zbx_strdup(NULL, snmp.item_port);
		else
			interface.port = zbx_strdup(NULL, if_port);

		zbx_vector_dbu_interface_append(new_ifs, interface);
	}
	DBfree_result(result);

#undef ITEM_TYPE_SNMPv1
#undef ITEM_TYPE_SNMPv2c
#undef ITEM_TYPE_SNMPv3
}

static void	DBpatch_load_empty_if(zbx_vector_dbu_snmp_if_t *snmp_def_ifs)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect(
			"select h.interfaceid,h.bulk"
			" from interface h"
			" where h.type=2 and h.interfaceid not in ("
				"select interfaceid"
				" from interface_snmp)");

	while (NULL != (row = DBfetch(result)))
	{
		dbu_snmp_if_t	snmp;

		ZBX_DBROW2UINT64(snmp.interfaceid, row[0]);
		ZBX_STR2UCHAR(snmp.bulk, row[1]);
		snmp.version = ZBX_IF_SNMP_VERSION_2;
		snmp.community = zbx_strdup(NULL, "{$SNMP_COMMUNITY}");
		snmp.securityname = zbx_strdup(NULL, "");
		snmp.securitylevel = 0;
		snmp.authpassphrase = zbx_strdup(NULL, "");
		snmp.privpassphrase = zbx_strdup(NULL, "");
		snmp.authprotocol = 0;
		snmp.privprotocol = 0;
		snmp.contextname = zbx_strdup(NULL, "");
		snmp.item_port = zbx_strdup(NULL, "");
		snmp.skip = 0;
		snmp.item_interfaceid = 0;

		zbx_vector_dbu_snmp_if_append(snmp_def_ifs, snmp);
	}
	DBfree_result(result);
}

static int	DBpatch_snmp_if_save(zbx_vector_dbu_snmp_if_t *snmp_ifs)
{
	zbx_db_insert_t	db_insert_snmp_if;
	int		i, ret;

	zbx_db_insert_prepare(&db_insert_snmp_if, "interface_snmp", "interfaceid", "version", "bulk", "community",
			"securityname", "securitylevel", "authpassphrase", "privpassphrase", "authprotocol",
			"privprotocol", "contextname", NULL);

	for (i = 0; i < snmp_ifs->values_num; i++)
	{
		dbu_snmp_if_t	*s = &snmp_ifs->values[i];

		if (0 != s->skip)
			continue;

		zbx_db_insert_add_values(&db_insert_snmp_if, s->interfaceid, s->version, s->bulk, s->community,
				s->securityname, s->securitylevel, s->authpassphrase, s->privpassphrase, s->authprotocol,
				s->privprotocol, s->contextname);
	}

	ret = zbx_db_insert_execute(&db_insert_snmp_if);
	zbx_db_insert_clean(&db_insert_snmp_if);

	return ret;
}

static int	DBpatch_interface_create(zbx_vector_dbu_interface_t *interfaces)
{
	zbx_db_insert_t	db_insert_interfaces;
	int		i, ret;

	zbx_db_insert_prepare(&db_insert_interfaces, "interface", "interfaceid", "hostid", "main", "type", "useip",
			"ip", "dns", "port", NULL);

	for (i = 0; i < interfaces->values_num; i++)
	{
		dbu_interface_t	*interface = &interfaces->values[i];

		zbx_db_insert_add_values(&db_insert_interfaces, interface->interfaceid,
				interface->hostid, interface->main, interface->type, interface->useip, interface->ip,
				interface->dns, interface->port);
	}

	ret = zbx_db_insert_execute(&db_insert_interfaces);
	zbx_db_insert_clean(&db_insert_interfaces);

	return ret;
}

static int	DBpatch_items_update(zbx_vector_dbu_snmp_if_t *snmp_ifs)
{
#define ITEM_TYPE_SNMPv1	1
#define ITEM_TYPE_SNMPv2c	4
#define ITEM_TYPE_SNMPv3	6
#define ITEM_TYPE_SNMP		20

	int	i, ret = SUCCEED;
	char	*sql;
	size_t	sql_alloc = snmp_ifs->values_num * ZBX_KIBIBYTE / 3 , sql_offset = 0;

	sql = (char *)zbx_malloc(NULL, sql_alloc);
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < snmp_ifs->values_num && SUCCEED == ret; i++)
	{
		int		item_type;
		dbu_snmp_if_t	*s = &snmp_ifs->values[i];

		if (ZBX_IF_SNMP_VERSION_1 == s->version)
			item_type = ITEM_TYPE_SNMPv1;
		else if (ZBX_IF_SNMP_VERSION_2 == s->version)
			item_type = ITEM_TYPE_SNMPv2c;
		else
			item_type = ITEM_TYPE_SNMPv3;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
#ifdef HAVE_ORACLE
				"update items i set type=%d, interfaceid=" ZBX_FS_UI64
				" where exists (select 1 from hosts h"
					" where i.hostid=h.hostid and"
					" i.type=%d and h.status <> 3 and"
					" i.interfaceid=" ZBX_FS_UI64 " and"
					" (('%s' is null and i.snmp_community is null) or"
						" i.snmp_community='%s') and"
					" (('%s' is null and i.snmpv3_securityname is null) or"
						" i.snmpv3_securityname='%s') and"
					" i.snmpv3_securitylevel=%d and"
					" (('%s' is null and i.snmpv3_authpassphrase is null) or"
						" i.snmpv3_authpassphrase='%s') and"
					" (('%s' is null and i.snmpv3_privpassphrase is null) or"
						" i.snmpv3_privpassphrase='%s') and"
					" i.snmpv3_authprotocol=%d and"
					" i.snmpv3_privprotocol=%d and"
					" (('%s' is null and i.snmpv3_contextname is null) or"
						" i.snmpv3_contextname='%s') and"
					" (('%s' is null and i.port is null) or"
						" i.port='%s'));\n",
				ITEM_TYPE_SNMP, s->interfaceid, item_type,
				s->item_interfaceid, s->community, s->community, s->securityname, s->securityname,
				(int)s->securitylevel, s->authpassphrase, s->authpassphrase, s->privpassphrase,
				s->privpassphrase, (int)s->authprotocol, (int)s->privprotocol, s->contextname,
				s->contextname, s->item_port, s->item_port);

#else
#	ifdef HAVE_MYSQL
				"update items i, hosts h set i.type=%d, i.interfaceid=" ZBX_FS_UI64
#	else
				"update items i set type=%d, interfaceid=" ZBX_FS_UI64 " from hosts h"
#	endif
				" where i.hostid=h.hostid and"
					" type=%d and h.status <> 3 and"
					" interfaceid=" ZBX_FS_UI64 " and"
					" snmp_community='%s' and"
					" snmpv3_securityname='%s' and"
					" snmpv3_securitylevel=%d and"
					" snmpv3_authpassphrase='%s' and"
					" snmpv3_privpassphrase='%s' and"
					" snmpv3_authprotocol=%d and"
					" snmpv3_privprotocol=%d and"
					" snmpv3_contextname='%s' and"
					" port='%s';\n",
				ITEM_TYPE_SNMP, s->interfaceid,
				item_type,
				s->item_interfaceid, s->community, s->securityname, (int)s->securitylevel,
				s->authpassphrase, s->privpassphrase, (int)s->authprotocol, (int)s->privprotocol,
				s->contextname, s->item_port);
#endif
		ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (SUCCEED == ret)
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset && ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	zbx_free(sql);

	return ret;

#undef ITEM_TYPE_SNMPv1
#undef ITEM_TYPE_SNMPv2c
#undef ITEM_TYPE_SNMPv3
#undef ITEM_TYPE_SNMP
}

static int	DBpatch_items_type_update(void)
{
#define ITEM_TYPE_SNMPv1	1
#define ITEM_TYPE_SNMPv2c	4
#define ITEM_TYPE_SNMPv3	6
#define ITEM_TYPE_SNMP		20

	if (ZBX_DB_OK > DBexecute("update items set type=%d where type in (%d,%d,%d)", ITEM_TYPE_SNMP,
			ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c, ITEM_TYPE_SNMPv3))
	{
		return FAIL;
	}

	return SUCCEED;

#undef ITEM_TYPE_SNMPv1
#undef ITEM_TYPE_SNMPv2c
#undef ITEM_TYPE_SNMPv3
#undef ITEM_TYPE_SNMP
}

/******************************************************************************
 *                                                                            *
 * Purpose: migration snmp data from 'items' table to 'interface_snmp' new    *
 *          table linked with 'interface' table, except interface links for   *
 *          discovered hosts and parent host interface                        *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_4050046(void)
{
	zbx_vector_dbu_interface_t	new_ifs;
	zbx_vector_dbu_snmp_if_t	snmp_ifs, snmp_new_ifs, snmp_def_ifs;
	int				ret = FAIL;

	zbx_vector_dbu_snmp_if_create(&snmp_ifs);
	zbx_vector_dbu_snmp_if_create(&snmp_new_ifs);
	zbx_vector_dbu_snmp_if_create(&snmp_def_ifs);
	zbx_vector_dbu_interface_create(&new_ifs);

	DBpatch_load_data(&snmp_ifs, &new_ifs, &snmp_new_ifs);

	while (1)
	{
		if (0 < snmp_ifs.values_num && SUCCEED != DBpatch_snmp_if_save(&snmp_ifs))
			break;

		if (0 < new_ifs.values_num && SUCCEED != DBpatch_interface_create(&new_ifs))
			break;

		if (0 < snmp_new_ifs.values_num && SUCCEED != DBpatch_snmp_if_save(&snmp_new_ifs))
			break;

		DBpatch_load_empty_if(&snmp_def_ifs);

		if (0 < snmp_def_ifs.values_num && SUCCEED != DBpatch_snmp_if_save(&snmp_def_ifs))
			break;

		if (0 < snmp_new_ifs.values_num && SUCCEED != DBpatch_items_update(&snmp_new_ifs))
			break;

		if (SUCCEED != DBpatch_items_type_update())
			break;

		ret = SUCCEED;
		break;
	}

	zbx_vector_dbu_interface_clear_ext(&new_ifs, db_interface_free);
	zbx_vector_dbu_interface_destroy(&new_ifs);
	zbx_vector_dbu_snmp_if_clear_ext(&snmp_ifs, db_snmpinterface_free);
	zbx_vector_dbu_snmp_if_destroy(&snmp_ifs);
	zbx_vector_dbu_snmp_if_clear_ext(&snmp_new_ifs, db_snmpinterface_free);
	zbx_vector_dbu_snmp_if_destroy(&snmp_new_ifs);
	zbx_vector_dbu_snmp_if_clear_ext(&snmp_def_ifs, db_snmpinterface_free);
	zbx_vector_dbu_snmp_if_destroy(&snmp_def_ifs);

	return ret;
}

static int	db_if_cmp(const dbu_interface_t *if1, const dbu_interface_t *if2)
{
#define ZBX_RETURN_IF_NOT_EQUAL_STR(s1, s2)	\
	if (0 != (ret = strcmp(s1, s2)))	\
		return ret;

	int	ret;

	ZBX_RETURN_IF_NOT_EQUAL(if1->hostid, if2->hostid);
	ZBX_RETURN_IF_NOT_EQUAL(if1->type, if2->type);
	ZBX_RETURN_IF_NOT_EQUAL(if1->main, if2->main);
	ZBX_RETURN_IF_NOT_EQUAL(if1->useip, if2->useip);
	ZBX_RETURN_IF_NOT_EQUAL_STR(if1->ip, if2->ip);
	ZBX_RETURN_IF_NOT_EQUAL_STR(if1->dns, if2->dns);
	ZBX_RETURN_IF_NOT_EQUAL_STR(if1->port, if2->port);

	return 0;

#undef ZBX_RETURN_IF_NOT_EQUAL_STR
}

static zbx_uint64_t	db_if_find(const dbu_interface_t *interface, dbu_snmp_if_t *snmp,
		zbx_vector_dbu_interface_t *interfaces, zbx_vector_dbu_snmp_if_t *snmp_ifs)
{
	int	i;

	for (i = interfaces->values_num - 1; i >= 0 &&
			interface->hostid == interfaces->values[i].hostid; i--)
	{
		if (0 != db_if_cmp(interface, &interfaces->values[i]))
			continue;

		if (0 != db_snmp_if_cmp(snmp, &snmp_ifs->values[i]))
			continue;

		return interfaces->values[i].interfaceid;
	}

	return 0;
}

static void	db_if_link(zbx_uint64_t if_slave, zbx_uint64_t if_master, zbx_vector_uint64_pair_t *if_links)
{
	zbx_uint64_pair_t	pair = {if_slave, if_master};

	zbx_vector_uint64_pair_append(if_links, pair);
}

/******************************************************************************
 *                                                                            *
 * Purpose: loading all unlinked interfaces, snmp data and hostid of host     *
 *          prototype for discovered hosts                                    *
 *                                                                            *
 * Parameters: new_ifs      - [OUT] new interfaces to be created on master    *
 *                                  hosts                                     *
 *             snmp_new_ifs - [OUT] snmp data associated with new interfaces  *
 *             if_links     - [OUT] set of pairs for discovered host          *
 *                                  interfaceid and parent interfaceid of     *
 *                                  parent host                               *
 *                                                                            *
 * Comments: When host is created by lld the parent host interfaces are       *
 *           copied over to the discovered hosts. Previous patch could have   *
 *           created new SNMP interfaces on discovered hosts, which must be   *
 *           linked to the corresponding interfaces (created if necessary) to *
 *           the parent host.                                                 *
 *                                                                            *
 ******************************************************************************/
static void	DBpatch_if_load_data(zbx_vector_dbu_interface_t *new_ifs, zbx_vector_dbu_snmp_if_t *snmp_new_ifs,
		zbx_vector_uint64_pair_t *if_links)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect(
			"select hreal.hostid,"
				"i.interfaceid,"
				"i.main,"
				"i.type,"
				"i.useip,"
				"i.ip,"
				"i.dns,"
				"i.port,"
				"s.version,"
				"s.bulk,"
				"s.community,"
				"s.securityname,"
				"s.securitylevel,"
				"s.authpassphrase,"
				"s.privpassphrase,"
				"s.authprotocol,"
				"s.privprotocol,"
				"s.contextname"
			" from interface i"
			" left join interface_discovery d on i.interfaceid=d.interfaceid"
			" join interface_snmp s on i.interfaceid=s.interfaceid"
			" join hosts hdisc on i.hostid=hdisc.hostid"
			" join host_discovery hd on hdisc.hostid=hd.hostid"
			" join hosts hproto on hd.parent_hostid=hproto.hostid"
			" join host_discovery hdd on hd.parent_hostid=hdd.hostid"
			" join items drule on drule.itemid=hdd.parent_itemid"
			" join hosts hreal on drule.hostid=hreal.hostid"
			" where"
				" i.type=2 and"
				" hdisc.flags=4 and"
				" drule.flags=1 and"
				" hproto.flags=2 and"
				" hreal.status in (1,0) and"
				" d.interfaceid is null"
			" order by drule.hostid asc, i.interfaceid asc");

	while (NULL != (row = DBfetch(result)))
	{
		dbu_interface_t		interface;
		dbu_snmp_if_t		snmp;
		zbx_uint64_t		if_parentid;

		ZBX_DBROW2UINT64(interface.hostid, row[0]);
		ZBX_DBROW2UINT64(interface.interfaceid , row[1]);
		ZBX_STR2UCHAR(interface.main, row[2]);
		ZBX_STR2UCHAR(interface.type, row[3]);
		ZBX_STR2UCHAR(interface.useip, row[4]);
		interface.ip = zbx_strdup(NULL, row[5]);
		interface.dns = zbx_strdup(NULL, row[6]);
		interface.port = zbx_strdup(NULL, row[7]);

		ZBX_STR2UCHAR(snmp.version, row[8]);
		ZBX_STR2UCHAR(snmp.bulk, row[9]);
		snmp.community = zbx_strdup(NULL, row[10]);
		snmp.securityname = zbx_strdup(NULL, row[11]);
		ZBX_STR2UCHAR(snmp.securitylevel, row[12]);
		snmp.authpassphrase = zbx_strdup(NULL, row[13]);
		snmp.privpassphrase = zbx_strdup(NULL, row[14]);
		ZBX_STR2UCHAR(snmp.authprotocol, row[15]);
		ZBX_STR2UCHAR(snmp.privprotocol, row[16]);
		snmp.contextname = zbx_strdup(NULL, row[17]);
		snmp.item_port = NULL;
		snmp.skip = 0;
		snmp.item_interfaceid = 0;

		if (0 < new_ifs->values_num &&
				0 != (if_parentid = db_if_find(&interface, &snmp, new_ifs, snmp_new_ifs)))
		{
			db_if_link(interface.interfaceid, if_parentid, if_links);
			db_snmpinterface_free(snmp);
			db_interface_free(interface);
			continue;
		}

		if_parentid = DBget_maxid("interface");
		db_if_link(interface.interfaceid, if_parentid, if_links);
		interface.interfaceid = if_parentid;
		snmp.interfaceid = if_parentid;
		zbx_vector_dbu_interface_append(new_ifs, interface);
		zbx_vector_dbu_snmp_if_append(snmp_new_ifs, snmp);
	}
	DBfree_result(result);
}

static int	DBpatch_interface_discovery_save(zbx_vector_uint64_pair_t *if_links)
{
	zbx_db_insert_t	db_insert_if_links;
	int		i, ret;

	zbx_db_insert_prepare(&db_insert_if_links, "interface_discovery", "interfaceid", "parent_interfaceid", NULL);

	for (i = 0; i < if_links->values_num; i++)
	{
		zbx_uint64_pair_t	*l = &if_links->values[i];

		zbx_db_insert_add_values(&db_insert_if_links, l->first, l->second);
	}

	ret = zbx_db_insert_execute(&db_insert_if_links);
	zbx_db_insert_clean(&db_insert_if_links);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: recovery links between the interfaceid of discovered host and     *
 *          parent interfaceid from parent host                               *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	DBpatch_4050047(void)
{
	zbx_vector_dbu_interface_t	new_ifs;
	zbx_vector_dbu_snmp_if_t	snmp_new_ifs;
	zbx_vector_uint64_pair_t	if_links;
	int				ret = FAIL;

	zbx_vector_dbu_snmp_if_create(&snmp_new_ifs);
	zbx_vector_dbu_interface_create(&new_ifs);
	zbx_vector_uint64_pair_create(&if_links);

	DBpatch_if_load_data(&new_ifs, &snmp_new_ifs, &if_links);

	while (1)
	{
		if (0 < new_ifs.values_num && SUCCEED != DBpatch_interface_create(&new_ifs))
			break;

		if (0 < snmp_new_ifs.values_num && SUCCEED != DBpatch_snmp_if_save(&snmp_new_ifs))
			break;

		if (0 < if_links.values_num && SUCCEED != DBpatch_interface_discovery_save(&if_links))
			break;

		ret = SUCCEED;
		break;
	}

	zbx_vector_uint64_pair_destroy(&if_links);
	zbx_vector_dbu_interface_clear_ext(&new_ifs, db_interface_free);
	zbx_vector_dbu_interface_destroy(&new_ifs);
	zbx_vector_dbu_snmp_if_clear_ext(&snmp_new_ifs, db_snmpinterface_free);
	zbx_vector_dbu_snmp_if_destroy(&snmp_new_ifs);

	return ret;
}

static int	DBpatch_4050048(void)
{
	return DBdrop_field("interface", "bulk");
}

static int	DBpatch_4050049(void)
{
	return DBdrop_field("items", "snmp_community");
}

static int	DBpatch_4050050(void)
{
	return DBdrop_field("items", "snmpv3_securityname");
}

static int	DBpatch_4050051(void)
{
	return DBdrop_field("items", "snmpv3_securitylevel");
}

static int	DBpatch_4050052(void)
{
	return DBdrop_field("items", "snmpv3_authpassphrase");
}

static int	DBpatch_4050053(void)
{
	return DBdrop_field("items", "snmpv3_privpassphrase");
}

static int	DBpatch_4050054(void)
{
	return DBdrop_field("items", "snmpv3_authprotocol");
}

static int	DBpatch_4050055(void)
{
	return DBdrop_field("items", "snmpv3_privprotocol");
}

static int	DBpatch_4050056(void)
{
	return DBdrop_field("items", "snmpv3_contextname");
}

static int	DBpatch_4050057(void)
{
	return DBdrop_field("items", "port");
}

static int	DBpatch_4050058(void)
{
	const ZBX_FIELD	field = {"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("globalmacro", &field);
}

static int	DBpatch_4050059(void)
{
	const ZBX_FIELD	field = {"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hostmacro", &field);
}

static int	DBpatch_4050060(void)
{
	const ZBX_FIELD	field = {"compression_status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050061(void)
{
	const ZBX_FIELD	field = {"compression_availability", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050062(void)
{
	const ZBX_FIELD	field = {"compress_older", "7d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050063(void)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	profileid, userid, idx2;
	int		ret = SUCCEED, value_int, i;
	const char	*profile = "web.problem.filter.severities";

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = DBselect(
			"select profileid,userid,value_int"
			" from profiles"
			" where idx='web.problem.filter.severity'");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(profileid, row[0]);

		if (0 == (value_int = atoi(row[2])))
		{
			if (ZBX_DB_OK > DBexecute("delete from profiles where profileid=" ZBX_FS_UI64, profileid))
			{
				ret = FAIL;
				break;
			}

			continue;
		}

		if (ZBX_DB_OK > DBexecute("update profiles set idx='%s'"
				" where profileid=" ZBX_FS_UI64, profile, profileid))
		{
			ret = FAIL;
			break;
		}

		ZBX_DBROW2UINT64(userid, row[1]);
		idx2 = 0;

		for (i = value_int + 1; i < 6; i++)
		{
			if (ZBX_DB_OK > DBexecute("insert into profiles (profileid,userid,idx,idx2,value_id,value_int,"
					"type) values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s'," ZBX_FS_UI64 ",0,%d,2)",
					DBget_maxid("profiles"), userid, profile, ++idx2, i))
			{
				ret = FAIL;
				break;
			}
		}
	}
	DBfree_result(result);

	return ret;
}

static int	DBpatch_4050064(void)
{
	if (ZBX_DB_OK > DBexecute("update profiles set value_int=1 where idx='web.layout.mode' and value_int=2"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4050065(void)
{
	const ZBX_FIELD	field = {"value", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBmodify_field_type("history", &field, &field);
}

static int	DBpatch_4050066(void)
{
	const ZBX_FIELD	field = {"value_min", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBmodify_field_type("trends", &field, &field);
}

static int	DBpatch_4050067(void)
{
	const ZBX_FIELD	field = {"value_avg", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBmodify_field_type("trends", &field, &field);
}

static int	DBpatch_4050068(void)
{
	const ZBX_FIELD	field = {"value_max", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBmodify_field_type("trends", &field, &field);
}

static int	DBpatch_4050069(void)
{
	const ZBX_FIELD	field = {"yaxismin", "0", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("graphs", &field, &field);
}

static int	DBpatch_4050070(void)
{
	const ZBX_FIELD	field = {"yaxismax", "100", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("graphs", &field, &field);
}

static int	DBpatch_4050071(void)
{
	const ZBX_FIELD	field = {"percent_left", "0", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("graphs", &field, &field);
}

static int	DBpatch_4050072(void)
{
	const ZBX_FIELD	field = {"percent_right", "0", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("graphs", &field, &field);
}

static int	DBpatch_4050073(void)
{
	const ZBX_FIELD	field = {"goodsla", "99.9", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("services", &field, &field);
}

static int	DBpatch_4050074(void)
{
	int		i;
	const char	*values[] = {
			"web.latest.groupid", "web.latest.hostid", "web.latest.graphid", "web..groupid",
			"web..hostid", "web.view.groupid", "web.view.hostid", "web.view.graphid",
			"web.config.groupid", "web.config.hostid", "web.templates.php.groupid", "web.cm.groupid",
			"web.httpmon.php.sort", "web.httpmon.php.sortorder", "web.avail_report.0.hostid",
			"web.avail_report.0.groupid", "web.graphs.filter.to", "web.graphs.filter.from", "web.graphs.filter.active"
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

static int	DBpatch_4050075(void)
{
	return DBdrop_field("config", "dropdown_first_entry");
}

static int	DBpatch_4050076(void)
{
	return DBdrop_field("config", "dropdown_first_remember");
}

static int	DBpatch_4050077(void)
{
	const ZBX_FIELD	field = {"message", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("acknowledges", &field, NULL);
}

static int	DBpatch_4050078(void)
{
	const ZBX_FIELD	field = {"write_clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("proxy_history", &field);
}

static int	DBpatch_4050079(void)
{
	const ZBX_FIELD	field = {"instanceid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050080(void)
{
	const ZBX_FIELD	old_field = {"script", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"script", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("media_type", &field, &old_field);
}

static int	DBpatch_4050081(void)
{
	const ZBX_FIELD	old_field = {"oldvalue", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"oldvalue", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("auditlog_details", &field, &old_field);
}

static int	DBpatch_4050082(void)
{
	const ZBX_FIELD	old_field = {"newvalue", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const ZBX_FIELD	field = {"newvalue", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("auditlog_details", &field, &old_field);
}

static int	DBpatch_4050083(void)
{
	const ZBX_FIELD	field = {"saml_auth_enabled", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050084(void)
{
	const ZBX_FIELD	field = {"saml_idp_entityid", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050085(void)
{
	const ZBX_FIELD	field = {"saml_sso_url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050086(void)
{
	const ZBX_FIELD	field = {"saml_slo_url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050087(void)
{
	const ZBX_FIELD	field = {"saml_username_attribute", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050088(void)
{
	const ZBX_FIELD	field = {"saml_sp_entityid", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050089(void)
{
	const ZBX_FIELD	field = {"saml_nameid_format", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050090(void)
{
	const ZBX_FIELD	field = {"saml_sign_messages", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050091(void)
{
	const ZBX_FIELD	field = {"saml_sign_assertions", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050092(void)
{
	const ZBX_FIELD	field = {"saml_sign_authn_requests", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050093(void)
{
	const ZBX_FIELD	field = {"saml_sign_logout_requests", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050094(void)
{
	const ZBX_FIELD	field = {"saml_sign_logout_responses", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050095(void)
{
	const ZBX_FIELD	field = {"saml_encrypt_nameid", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050096(void)
{
	const ZBX_FIELD	field = {"saml_encrypt_assertions", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050097(void)
{
	const ZBX_FIELD	field = {"saml_case_sensitive", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4050098(void)
{
	const ZBX_TABLE	table =
		{"lld_override", "lld_overrideid", 0,
			{
				{"lld_overrideid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"step", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"evaltype", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"formula", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"stop", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050099(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_override", 1, &field);
}

static int	DBpatch_4050100(void)
{
	return DBcreate_index("lld_override", "lld_override_1", "itemid,name", 1);
}

static int	DBpatch_4050101(void)
{
	const ZBX_TABLE	table =
		{"lld_override_condition", "lld_override_conditionid", 0,
			{
				{"lld_override_conditionid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"lld_overrideid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"operator", "8", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"macro", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050102(void)
{
	const ZBX_FIELD	field = {"lld_overrideid", NULL, "lld_override", "lld_overrideid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_override_condition", 1, &field);
}

static int	DBpatch_4050103(void)
{
	return DBcreate_index("lld_override_condition", "lld_override_condition_1", "lld_overrideid", 0);
}

static int	DBpatch_4050104(void)
{
	const ZBX_TABLE	table =
		{"lld_override_operation", "lld_override_operationid", 0,
			{
				{"lld_override_operationid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"lld_overrideid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"operationobject", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"operator", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050105(void)
{
	const ZBX_FIELD	field = {"lld_overrideid", NULL, "lld_override", "lld_overrideid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_override_operation", 1, &field);
}

static int	DBpatch_4050106(void)
{
	return DBcreate_index("lld_override_operation", "lld_override_operation_1", "lld_overrideid", 0);
}

static int	DBpatch_4050107(void)
{
	const ZBX_TABLE	table =
		{"lld_override_opstatus", "lld_override_operationid", 0,
			{
				{"lld_override_operationid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050108(void)
{
	const ZBX_FIELD	field = {"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
			0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_override_opstatus", 1, &field);
}

static int	DBpatch_4050109(void)
{
	const ZBX_TABLE	table =
		{"lld_override_opdiscover", "lld_override_operationid", 0,
			{
				{"lld_override_operationid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"discover", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050110(void)
{
	const ZBX_FIELD	field = {"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
			0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_override_opdiscover", 1, &field);
}

static int	DBpatch_4050111(void)
{
	const ZBX_TABLE	table =
		{"lld_override_opperiod", "lld_override_operationid", 0,
			{
				{"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
						0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"delay", "0", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050112(void)
{
	const ZBX_FIELD	field = {"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
			0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_override_opperiod", 1, &field);
}

static int	DBpatch_4050113(void)
{
	const ZBX_TABLE	table =
		{"lld_override_ophistory", "lld_override_operationid", 0,
			{
				{"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
						0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"history", "90d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050114(void)
{
	const ZBX_FIELD	field = {"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
			0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_override_ophistory", 1, &field);
}

static int	DBpatch_4050115(void)
{
	const ZBX_TABLE	table =
		{"lld_override_optrends", "lld_override_operationid", 0,
			{
				{"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
						0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"trends", "365d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050116(void)
{
	const ZBX_FIELD	field = {"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
			0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_override_optrends", 1, &field);
}

static int	DBpatch_4050117(void)
{
	const ZBX_TABLE	table =
		{"lld_override_opseverity", "lld_override_operationid", 0,
			{
				{"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
						0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"severity", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050118(void)
{
	const ZBX_FIELD	field = {"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
			0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_override_opseverity", 1, &field);
}

static int	DBpatch_4050119(void)
{
	const ZBX_TABLE	table =
		{"lld_override_optag", "lld_override_optagid", 0,
			{
				{"lld_override_optagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
						0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050120(void)
{
	const ZBX_FIELD	field = {"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
			0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_override_optag", 1, &field);
}

static int	DBpatch_4050121(void)
{
	return DBcreate_index("lld_override_optag", "lld_override_optag_1", "lld_override_operationid", 0);
}

static int	DBpatch_4050122(void)
{
	const ZBX_TABLE table =
		{"lld_override_optemplate", "lld_override_optemplateid", 0,
			{
				{"lld_override_optemplateid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"lld_override_operationid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"templateid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050123(void)
{
	const ZBX_FIELD	field = {"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
			0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_override_optemplate", 1, &field);
}

static int	DBpatch_4050124(void)
{
	return DBcreate_index("lld_override_optemplate", "lld_override_optemplate_1",
			"lld_override_operationid,templateid", 1);
}

static int	DBpatch_4050125(void)
{
	const ZBX_FIELD	field = {"templateid", NULL, "hosts", "hostid", 0, 0, 0, 0};

	return DBadd_foreign_key("lld_override_optemplate", 2, &field);
}

static int	DBpatch_4050126(void)
{
	return DBcreate_index("lld_override_optemplate", "lld_override_optemplate_2", "templateid", 0);
}

static int	DBpatch_4050127(void)
{
	const ZBX_TABLE	table =
		{"lld_override_opinventory", "lld_override_operationid", 0,
			{
				{"lld_override_operationid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"inventory_mode", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4050128(void)
{
	const ZBX_FIELD	field = {"lld_override_operationid", NULL, "lld_override_operation", "lld_override_operationid",
			0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lld_override_opinventory", 1, &field);
}

static int	DBpatch_4050129(void)
{
	const ZBX_FIELD field = {"discover", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_4050130(void)
{
	const ZBX_FIELD field = {"discover", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("triggers", &field);
}

static int	DBpatch_4050131(void)
{
	const ZBX_FIELD field = {"discover", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_4050132(void)
{
	const ZBX_FIELD field = {"discover", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("graphs", &field);
}

static int	DBpatch_4050133(void)
{
	const ZBX_FIELD field = {"lastcheck", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("trigger_discovery", &field);
}

static int	DBpatch_4050134(void)
{
	const ZBX_FIELD field = {"ts_delete", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("trigger_discovery", &field);
}

static int	DBpatch_4050135(void)
{
	const ZBX_FIELD field = {"lastcheck", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("graph_discovery", &field);
}

static int	DBpatch_4050136(void)
{
	const ZBX_FIELD field = {"ts_delete", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("graph_discovery", &field);
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
DBPATCH_ADD(4050039, 0, 1)
DBPATCH_ADD(4050040, 0, 1)
DBPATCH_ADD(4050041, 0, 1)
DBPATCH_ADD(4050042, 0, 1)
DBPATCH_ADD(4050043, 0, 0)
DBPATCH_ADD(4050044, 0, 1)
DBPATCH_ADD(4050045, 0, 1)
DBPATCH_ADD(4050046, 0, 1)
DBPATCH_ADD(4050047, 0, 1)
DBPATCH_ADD(4050048, 0, 1)
DBPATCH_ADD(4050049, 0, 1)
DBPATCH_ADD(4050050, 0, 1)
DBPATCH_ADD(4050051, 0, 1)
DBPATCH_ADD(4050052, 0, 1)
DBPATCH_ADD(4050053, 0, 1)
DBPATCH_ADD(4050054, 0, 1)
DBPATCH_ADD(4050055, 0, 1)
DBPATCH_ADD(4050056, 0, 1)
DBPATCH_ADD(4050057, 0, 1)
DBPATCH_ADD(4050058, 0, 1)
DBPATCH_ADD(4050059, 0, 1)
DBPATCH_ADD(4050060, 0, 1)
DBPATCH_ADD(4050061, 0, 1)
DBPATCH_ADD(4050062, 0, 1)
DBPATCH_ADD(4050063, 0, 1)
DBPATCH_ADD(4050064, 0, 1)
DBPATCH_ADD(4050065, 0, 1)
DBPATCH_ADD(4050066, 0, 1)
DBPATCH_ADD(4050067, 0, 1)
DBPATCH_ADD(4050068, 0, 1)
DBPATCH_ADD(4050069, 0, 1)
DBPATCH_ADD(4050070, 0, 1)
DBPATCH_ADD(4050071, 0, 1)
DBPATCH_ADD(4050072, 0, 1)
DBPATCH_ADD(4050073, 0, 1)
DBPATCH_ADD(4050074, 0, 1)
DBPATCH_ADD(4050075, 0, 1)
DBPATCH_ADD(4050076, 0, 1)
DBPATCH_ADD(4050077, 0, 1)
DBPATCH_ADD(4050078, 0, 1)
DBPATCH_ADD(4050079, 0, 1)
DBPATCH_ADD(4050080, 0, 1)
DBPATCH_ADD(4050081, 0, 1)
DBPATCH_ADD(4050082, 0, 1)
DBPATCH_ADD(4050083, 0, 1)
DBPATCH_ADD(4050084, 0, 1)
DBPATCH_ADD(4050085, 0, 1)
DBPATCH_ADD(4050086, 0, 1)
DBPATCH_ADD(4050087, 0, 1)
DBPATCH_ADD(4050088, 0, 1)
DBPATCH_ADD(4050089, 0, 1)
DBPATCH_ADD(4050090, 0, 1)
DBPATCH_ADD(4050091, 0, 1)
DBPATCH_ADD(4050092, 0, 1)
DBPATCH_ADD(4050093, 0, 1)
DBPATCH_ADD(4050094, 0, 1)
DBPATCH_ADD(4050095, 0, 1)
DBPATCH_ADD(4050096, 0, 1)
DBPATCH_ADD(4050097, 0, 1)
DBPATCH_ADD(4050098, 0, 1)
DBPATCH_ADD(4050099, 0, 1)
DBPATCH_ADD(4050100, 0, 1)
DBPATCH_ADD(4050101, 0, 1)
DBPATCH_ADD(4050102, 0, 1)
DBPATCH_ADD(4050103, 0, 1)
DBPATCH_ADD(4050104, 0, 1)
DBPATCH_ADD(4050105, 0, 1)
DBPATCH_ADD(4050106, 0, 1)
DBPATCH_ADD(4050107, 0, 1)
DBPATCH_ADD(4050108, 0, 1)
DBPATCH_ADD(4050109, 0, 1)
DBPATCH_ADD(4050110, 0, 1)
DBPATCH_ADD(4050111, 0, 1)
DBPATCH_ADD(4050112, 0, 1)
DBPATCH_ADD(4050113, 0, 1)
DBPATCH_ADD(4050114, 0, 1)
DBPATCH_ADD(4050115, 0, 1)
DBPATCH_ADD(4050116, 0, 1)
DBPATCH_ADD(4050117, 0, 1)
DBPATCH_ADD(4050118, 0, 1)
DBPATCH_ADD(4050119, 0, 1)
DBPATCH_ADD(4050120, 0, 1)
DBPATCH_ADD(4050121, 0, 1)
DBPATCH_ADD(4050122, 0, 1)
DBPATCH_ADD(4050123, 0, 1)
DBPATCH_ADD(4050124, 0, 1)
DBPATCH_ADD(4050125, 0, 1)
DBPATCH_ADD(4050126, 0, 1)
DBPATCH_ADD(4050127, 0, 1)
DBPATCH_ADD(4050128, 0, 1)
DBPATCH_ADD(4050129, 0, 1)
DBPATCH_ADD(4050130, 0, 1)
DBPATCH_ADD(4050131, 0, 1)
DBPATCH_ADD(4050132, 0, 1)
DBPATCH_ADD(4050133, 0, 1)
DBPATCH_ADD(4050134, 0, 1)
DBPATCH_ADD(4050135, 0, 1)
DBPATCH_ADD(4050136, 0, 1)

DBPATCH_END()
