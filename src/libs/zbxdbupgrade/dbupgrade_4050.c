/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

/*
 * 5.0 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_4050000(void)
{
	int		i;
	const char	*values[] = {
			"web.usergroup.filter_users_status", "web.usergroup.filter_user_status",
			"web.usergrps.php.sort", "web.usergroup.sort",
			"web.usergrps.php.sortorder", "web.usergroup.sortorder"
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

static int	DBpatch_4050008(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update profiles set idx='web.valuemap.list.sortorder'"
				" where idx='web.adm.valuemapping.php.sortorder'"))
		return FAIL;

	if (ZBX_DB_OK > DBexecute("update profiles set idx='web.valuemap.list.sort'"
				" where idx='web.adm.valuemapping.php.sort'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4050009(void)
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
					{NULL}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_4050010(void)
{
	const ZBX_FIELD	field = {"mediatypeid", NULL, "media_type", "mediatypeid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("media_type_message", 1, &field);
}

static int	DBpatch_4050011(void)
{
	return DBcreate_index("media_type_message", "media_type_message_1", "mediatypeid,eventsource,recovery", 1);
}

static int	DBpatch_4050012(void)
{
	const ZBX_FIELD	field = {"default_msg", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("opmessage", &field);
}

static int	DBpatch_4050013(void)
{
	if (ZBX_DB_OK > DBexecute(
			"update opmessage m"
			" join operations o on m.operationid=o.operationid"
			" join actions a on o.actionid=a.actionid"
			" set m.subject=a.def_shortdata,m.message=a.def_longdata,m.default_msg='0'"
			" where m.default_msg='1' and o.recovery='0'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4050014(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(
			"update opmessage m"
			" join operations o on m.operationid=o.operationid"
			" join actions a on o.actionid=a.actionid"
			" set m.subject=a.r_shortdata,m.message=a.r_longdata,m.default_msg='0'"
			" where m.default_msg='1' and o.recovery='1'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4050015(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(
			"update opmessage m"
			" join operations o on m.operationid=o.operationid"
			" join actions a on o.actionid=a.actionid"
			" set m.subject=a.ack_shortdata,m.message=a.ack_longdata,m.default_msg='0'"
			" where m.default_msg='1' and o.recovery='2'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4050016(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

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
						"placeholder-trigger-normal-html"
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
						"\n"
						"Original problem ID: {EVENT.ID}\n"
						"{TRIGGER.URL}"
						,
						"placeholder-trigger-rec-html"
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
						"placeholder-trigger-ack-html"
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
						"placeholder-discovery-normal-html"
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
						"placeholder-registration-normal-html"
						,
						"Auto registration: {HOST.HOST}\n"
						"Host IP: {HOST.IP}\n"
						"Agent port: {HOST.PORT}"
						,
						"Auto registration: {HOST.HOST}"
					},
					{NULL, NULL, NULL, NULL},
					{NULL, NULL, NULL, NULL}
				}
			};

	int		ret = SUCCEED;
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	mediatypeid;
	int		content_type, i, k;
	char		*msg_esc = NULL, *subj_esc = NULL;

	result = DBselect("SELECT mediatypeid,type,content_type FROM media_type");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(mediatypeid, row[0]);
		content_type = (MEDIA_TYPE_SMS == (zbx_media_type_t)atoi(row[1])) ? 2 : atoi(row[2]);

		for (i = 0; 2 >= i; i++)
		{
			for (k = 0; 2 >= k; k++)
			{
				if (NULL != messages[i][k][0])
				{
					msg_esc = DBdyn_escape_string(messages[i][k][content_type]);
					subj_esc = DBdyn_escape_string(messages[i][k][3]);

					if (ZBX_DB_OK > DBexecute(
							"insert into media_type_message"
							" (mediatype_messageid,mediatypeid,eventsource,recovery,"
							"subject,message)"
							" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%i,%i,'%s','%s')",
							DBget_maxid_num("media_type_message", 1), mediatypeid, i, k,
							subj_esc, msg_esc))
					{
						ret = FAIL;
						goto out;
					}
				}
			}
		}
	}

out:
	zbx_free(msg_esc);
	zbx_free(subj_esc);
	DBfree_result(result);

	return ret;
}

static int	DBpatch_4050017(void)
{
	return DBdrop_field("actions", "def_shortdata");
}

static int	DBpatch_4050018(void)
{
	return DBdrop_field("actions", "def_longdata");
}

static int	DBpatch_4050019(void)
{
	return DBdrop_field("actions", "r_shortdata");
}

static int	DBpatch_4050020(void)
{
	return DBdrop_field("actions", "r_longdata");
}

static int	DBpatch_4050021(void)
{
	return DBdrop_field("actions", "ack_shortdata");
}

static int	DBpatch_4050022(void)
{
	return DBdrop_field("actions", "ack_longdata");
}

#endif

DBPATCH_START(4050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(4050000, 0, 1)
DBPATCH_ADD(4050001, 0, 1)
DBPATCH_ADD(4050002, 0, 1)
DBPATCH_ADD(4050003, 0, 1)
DBPATCH_ADD(4050004, 0, 1)
DBPATCH_ADD(4050005, 0, 1)
DBPATCH_ADD(4050006, 0, 1)
DBPATCH_ADD(4050007, 0, 1)
DBPATCH_ADD(4050008, 0, 1)
DBPATCH_ADD(4050009, 0, 1)
DBPATCH_ADD(4050010, 0, 1)
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

DBPATCH_END()
