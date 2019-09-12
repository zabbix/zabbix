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
 * 4.4 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_4030000(void)
{
	const ZBX_FIELD	field = {"host", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("autoreg_host", &field, NULL);
}

static int	DBpatch_4030001(void)
{
	const ZBX_FIELD	field = {"host", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("proxy_autoreg_host", &field, NULL);
}

static int	DBpatch_4030002(void)
{
	const ZBX_FIELD	field = {"host", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_discovery", &field, NULL);
}

static int	DBpatch_4030003(void)
{
	const ZBX_TABLE table =
		{"item_rtdata", "itemid", 0,
			{
				{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"lastlogsize", "0", NULL, NULL, 0, ZBX_TYPE_UINT, ZBX_NOTNULL, 0},
				{"state", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"mtime", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_4030004(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_rtdata", 1, &field);
}

static int	DBpatch_4030005(void)
{
	if (ZBX_DB_OK <= DBexecute("insert into item_rtdata (itemid,lastlogsize,state,mtime,error)"
			" select i.itemid,i.lastlogsize,i.state,i.mtime,i.error"
			" from items i"
			" join hosts h on i.hostid=h.hostid"
			" where h.status in (%d,%d) and i.flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, ZBX_FLAG_DISCOVERY_PROTOTYPE))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_4030006(void)
{
	return DBdrop_field("items", "lastlogsize");
}

static int	DBpatch_4030007(void)
{
	return DBdrop_field("items", "state");
}

static int	DBpatch_4030008(void)
{
	return DBdrop_field("items", "mtime");
}

static int	DBpatch_4030009(void)
{
	return DBdrop_field("items", "error");
}

static int	DBpatch_4030010(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* 8 - SCREEN_RESOURCE_SCREEN */
	if (ZBX_DB_OK > DBexecute("delete from screens_items where resourcetype=8"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4030012(void)
{
	const ZBX_FIELD	field = {"flags", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("autoreg_host", &field);
}

static int	DBpatch_4030013(void)
{
	const ZBX_FIELD	field = {"flags", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("proxy_autoreg_host", &field);
}

static int	DBpatch_4030014(void)
{
	const ZBX_FIELD	field = {"view_mode", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("widget", &field);
}

static int	DBpatch_4030015(void)
{
	if (ZBX_DB_OK > DBexecute("update widget set x=x*2, width=width*2"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4030016(void)
{
	int		i;
	const char	*values[] = {
			"alarm_ok",
			"no_sound",
			"alarm_information",
			"alarm_warning",
			"alarm_average",
			"alarm_high",
			"alarm_disaster"
		};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; i < (int)ARRSIZE(values); i++)
	{
		if (ZBX_DB_OK > DBexecute(
				"update profiles"
				" set value_str='%s.mp3'"
				" where value_str='%s.wav'"
					" and idx='web.messages'", values[i], values[i]))
		{
			return FAIL;
		}
	}

	return SUCCEED;
}

static int	DBpatch_4030017(void)
{
	const ZBX_FIELD	field = {"opdata", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBrename_field("triggers", "details", &field);
}

static int	DBpatch_4030018(void)
{
	int		i;
	const char      *values[] = {
			"web.users.filter.usrgrpid", "web.user.filter.usrgrpid",
			"web.users.php.sort", "web.user.sort",
			"web.users.php.sortorder", "web.user.sortorder",
			"web.problem.filter.show_latest_values", "web.problem.filter.show_opdata"
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

static int	DBpatch_4030019(void)
{
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(
			"update widget_field"
			" set name='show_opdata'"
			" where name='show_latest_values'"
				" and exists ("
					"select null"
					" from widget"
					" where widget.widgetid=widget_field.widgetid"
						" and widget.type in ('problems','problemsbysv')"
				")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_4030020(void)
{
#define FIELD_LEN	32

	const char	*tmp_token;
	char		*pos, *token = NULL, *token_esc = NULL, *value = NULL, field[FIELD_LEN];
	int		ret = SUCCEED;
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint32_t	id, next_id = 0;
	zbx_uint64_t	last_widgetid = 0, widgetid, fieldid;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = DBselect("SELECT widgetid,widget_fieldid,name,value_str"
			" FROM widget_field"
			" WHERE widgetid IN (SELECT widgetid FROM widget WHERE type='svggraph') AND type=1"
			" AND (name LIKE 'ds.hosts.%%' OR name LIKE 'ds.items.%%' OR name LIKE 'or.hosts.%%'"
				" OR name LIKE 'or.items.%%' OR name LIKE 'problemhosts.%%')"
			" ORDER BY widgetid, name");

	if (NULL == result)
		return FAIL;

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(widgetid, row[0]);
		ZBX_DBROW2UINT64(fieldid, row[1]);

		if (NULL == (pos = strrchr(row[2], '.')) || FIELD_LEN <= pos - row[2])
		{
			ret = FAIL;

			break;
		}

		if (last_widgetid != widgetid || 0 != strncmp(field, row[2], pos - row[2]))
		{
			last_widgetid = widgetid;
			next_id = 0;

			zbx_strlcpy(field, row[2], (pos + 1) - row[2]);
		}

		id = atoi(pos + 1);
		value = zbx_strdup(value, row[3]);
		tmp_token = strtok(value, ",\n");

		while (NULL != tmp_token)
		{
			token = zbx_strdup(token, tmp_token);
			zbx_lrtrim(token, " \t\r");

			if ('\0' == token[0])
			{
				tmp_token = strtok(NULL, ",\n");

				continue;
			}

			if (id != next_id || 0 != strcmp(row[3], token))
			{
				token_esc = DBdyn_escape_string(token);

				if (ZBX_DB_OK > DBexecute("insert into widget_field (widgetid,widget_fieldid,type,name,"
						"value_str) values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",1,'%s.%u','%s')",
						widgetid, DBget_maxid_num("widget_field", 1), field, next_id,
						token_esc) ||
						ZBX_DB_OK > DBexecute("delete from widget_field where widget_fieldid="
								ZBX_FS_UI64, fieldid))
				{
					zbx_free(token_esc);
					ret = FAIL;

					break;
				}

				zbx_free(token_esc);
			}

			next_id++;
			tmp_token = strtok(NULL, ",\n");
		}
	}

	zbx_free(token);
	zbx_free(value);
	DBfree_result(result);

#undef FIELD_LEN
	return ret;
}

static int	DBpatch_4030021(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;
	char		*exec_params = NULL, *exec_params_esc;
	size_t		exec_params_alloc = 0;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* type : 1 - MEDIA_TYPE_EXEC, 3 - MEDIA_TYPE_JABBER, 100 - MEDIA_TYPE_EZ_TEXTING */
	result = DBselect("select mediatypeid,type,username,passwd,exec_path from media_type where type in (3,100)");

	while (NULL != (row = DBfetch(result)))
	{
		size_t	exec_params_offset = 0;

		if (3 == atoi(row[1])) {
			zbx_snprintf_alloc(&exec_params, &exec_params_alloc, &exec_params_offset,
				"Jabber identifier\n%s\nPassword\n%s\n", row[2], row[3]);
		}
		else
		{
			zbx_snprintf_alloc(&exec_params, &exec_params_alloc, &exec_params_offset,
				"Username\n%s\nPassword\n%s\nMessage text limit\n%d\n", row[2], row[3],
				0 == atoi(row[4]) ? 160 : 136);
		}

		exec_params_esc = DBdyn_escape_string_len(exec_params, 255);

		if (ZBX_DB_OK > DBexecute("update media_type"
				" set type=1,"
					"exec_path='dummy.sh',"
					"exec_params='%s',"
					"username='',"
					"passwd=''"
				" where mediatypeid=%s", exec_params_esc, row[0]))
		{
			zbx_free(exec_params_esc);
			goto out;
		}

		zbx_free(exec_params_esc);
	}

	ret = SUCCEED;
out:
	DBfree_result(result);
	zbx_free(exec_params);

	return ret;
}

#endif

DBPATCH_START(4030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(4030000, 0, 1)
DBPATCH_ADD(4030001, 0, 1)
DBPATCH_ADD(4030002, 0, 1)
DBPATCH_ADD(4030003, 0, 1)
DBPATCH_ADD(4030004, 0, 1)
DBPATCH_ADD(4030005, 0, 1)
DBPATCH_ADD(4030006, 0, 1)
DBPATCH_ADD(4030007, 0, 1)
DBPATCH_ADD(4030008, 0, 1)
DBPATCH_ADD(4030009, 0, 1)
DBPATCH_ADD(4030010, 0, 1)
DBPATCH_ADD(4030012, 0, 1)
DBPATCH_ADD(4030013, 0, 1)
DBPATCH_ADD(4030014, 0, 1)
DBPATCH_ADD(4030015, 0, 1)
DBPATCH_ADD(4030016, 0, 1)
DBPATCH_ADD(4030017, 0, 1)
DBPATCH_ADD(4030018, 0, 1)
DBPATCH_ADD(4030019, 0, 1)
DBPATCH_ADD(4030020, 0, 1)
DBPATCH_ADD(4030021, 0, 1)

DBPATCH_END()
