/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * 6.0 maintenance database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char program_type;

static int	DBpatch_6000000(void)
{
	return SUCCEED;
}

static int	DBpatch_6000001(void)
{
	const ZBX_TABLE	table =
			{"service_problem_tag", "service_problem_tagid", 0,
				{
					{"service_problem_tagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"serviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"operator", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6000002(void)
{
	const ZBX_FIELD	field = {"serviceid", NULL, "services", "serviceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("service_problem_tag", 1, &field);
}

static int	DBpatch_6000003(void)
{
	return DBcreate_index("service_problem_tag", "service_problem_tag_1", "serviceid", 0);
}
static int	DBpatch_6000004(void)
{
	const ZBX_TABLE	table =
			{"service_problem", "service_problemid", 0,
				{
					{"service_problemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"eventid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"serviceid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"severity", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6000005(void)
{
	return DBcreate_index("service_problem", "service_problem_1", "eventid", 0);
}

static int	DBpatch_6000006(void)
{
	return DBcreate_index("service_problem", "service_problem_2", "serviceid", 0);
}

static int	DBpatch_6000007(void)
{
	const ZBX_FIELD	field = {"eventid", NULL, "problem", "eventid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("service_problem", 1, &field);
}

static int	DBpatch_6000008(void)
{
	const ZBX_FIELD	field = {"serviceid", NULL, "services", "serviceid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("service_problem", 2, &field);
}

#define ZBX_TAGVALUE_MAX_LEN	31

static void	DBpatch_trim_tag_value(char *tag_value)
{
	if (strlen(tag_value) > ZBX_TAGVALUE_MAX_LEN)
	{
		int	i;

		tag_value[ZBX_TAGVALUE_MAX_LEN] = '\0';

		for (i = 1; i <= 3; i++)
		{
			tag_value[ZBX_TAGVALUE_MAX_LEN - i] = '.';
		}
	}
}

static int	DBpatch_6000009(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	old_triggerid = 0, triggerid, serviceid;
	int		ret = FAIL;

	result = DBselect("select t.triggerid,t.description,s.serviceid from triggers t join services s "
			"on t.triggerid=s.triggerid");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	tagid;
		char		*tag_value = NULL;
		char		*desc;

		ZBX_STR2UINT64(triggerid, row[0]);
		desc = row[1];
		ZBX_STR2UINT64(serviceid, row[2]);

		tagid = DBget_maxid("service_problem_tag");

		tag_value = zbx_dsprintf(NULL, "%s:%s", row[0], desc);
		DBpatch_trim_tag_value(tag_value);

		if (ZBX_DB_OK > DBexecute("insert into service_problem_tag values (" ZBX_FS_UI64 "," ZBX_FS_UI64
				",'%s',%i,'%s')", tagid, serviceid, "ServiceLink", 0, tag_value))
		{
			goto out;
		}

		if (old_triggerid != triggerid)
		{
			zbx_uint64_t	triggertagid = DBget_maxid("trigger_tag");

			if (ZBX_DB_OK > DBexecute("insert into trigger_tag values (" ZBX_FS_UI64 "," ZBX_FS_UI64
					",'%s','%s')", triggertagid, triggerid, "ServiceLink", tag_value))
			{
				goto out;
			}
		}

		old_triggerid = triggerid;

		zbx_free(tag_value);
	}

	ret = SUCCEED;
out:
	DBfree_result(result);

	return ret;
}

static int	DBpatch_6000010(void)
{
	return DBdrop_foreign_key("services", 1);
}

static int	DBpatch_6000011(void)
{
	return DBdrop_field("services", "triggerid");
}

#endif

DBPATCH_START(6000)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(6000000, 0, 1)
DBPATCH_ADD(6000001, 0, 1)
DBPATCH_ADD(6000002, 0, 1)
DBPATCH_ADD(6000003, 0, 1)
DBPATCH_ADD(6000004, 0, 1)
DBPATCH_ADD(6000005, 0, 1)
DBPATCH_ADD(6000006, 0, 1)
DBPATCH_ADD(6000007, 0, 1)
DBPATCH_ADD(6000008, 0, 1)
DBPATCH_ADD(6000009, 0, 1)
DBPATCH_ADD(6000010, 0, 1)
DBPATCH_ADD(6000011, 0, 1)

DBPATCH_END()
