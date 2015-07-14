/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "zbxdbupgrade.h"
#include "dbupgrade.h"
#include "sysinfo.h"
#include "log.h"

/*
 * 3.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_2050000(void)
{
	const ZBX_FIELD	field = {"agent", "Zabbix", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("httptest", &field);
}

static int	DBpatch_2050001(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*oid = NULL;
	size_t		oid_alloc = 0;
	int		ret = FAIL, rc;

	/* flags - ZBX_FLAG_DISCOVERY_RULE                               */
	/* type  - ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c, ITEM_TYPE_SNMPv3 */
	if (NULL == (result = DBselect("select itemid,snmp_oid from items where flags=1 and type in (1,4,6)")))
		return FAIL;

	while (NULL != (row = DBfetch(result)))
	{
		char	*param, *oid_esc;
		size_t	oid_offset = 0;

		param = zbx_strdup(NULL, row[1]);
		quote_key_param(&param, 0);

		zbx_snprintf_alloc(&oid, &oid_alloc, &oid_offset, "discovery[{#SNMPVALUE},%s]", param);

		/* 255 - ITEM_SNMP_OID_LEN */
		if (255 < oid_offset && 255 < zbx_strlen_utf8(oid))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot convert SNMP discovery OID \"%s\":"
					" resulting OID is too long", row[1]);
			rc = ZBX_DB_OK;
		}
		else
		{
			oid_esc = DBdyn_escape_string(oid);

			rc = DBexecute("update items set snmp_oid='%s' where itemid=%s", oid_esc, row[0]);

			zbx_free(oid_esc);
		}

		zbx_free(param);

		if (ZBX_DB_OK > rc)
			goto out;
	}

	ret = SUCCEED;
out:
	DBfree_result(result);
	zbx_free(oid);

	return ret;
}

static int	DBpatch_2050002(void)
{
	const ZBX_FIELD	field = {"lastlogsize", "0", NULL, NULL, 0, ZBX_TYPE_UINT, ZBX_NOTNULL, 0};

	return DBadd_field("proxy_history", &field);
}

static int	DBpatch_2050003(void)
{
	const ZBX_FIELD	field = {"mtime", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("proxy_history", &field);
}

static int	DBpatch_2050004(void)
{
	const ZBX_FIELD	field = {"meta", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("proxy_history", &field);
}

static int	DBpatch_2050005(void)
{
	return DBdrop_index("triggers", "triggers_2");
}

static int	DBpatch_2050006(void)
{
	return DBcreate_index("triggers", "triggers_2", "value,lastchange", 0);
}

static int	DBpatch_2050007(void)
{
	const ZBX_FIELD	field = {"error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("hosts", &field);
}

static int	DBpatch_2050008(void)
{
	const ZBX_FIELD	field = {"ipmi_error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("hosts", &field);
}

static int	DBpatch_2050009(void)
{
	const ZBX_FIELD	field = {"snmp_error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("hosts", &field);
}

static int	DBpatch_2050010(void)
{
	const ZBX_FIELD	field = {"jmx_error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("hosts", &field);
}

static int	DBpatch_2050011(void)
{
	/* 1 - ITEM_VALUE_TYPE_STR, 2 - ITEM_VALUE_TYPE_LOG, 4 - ITEM_VALUE_TYPE_TEXT */
	if (ZBX_DB_OK <= DBexecute("update items set trends=0 where value_type in (1,2,4)"))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2050012(void)
{
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row;
	char		*key = NULL, *key_esc, *param;
	int		ret = SUCCEED;
	AGENT_REQUEST	request;

	/* type - ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_ZABBIX_ACTIVE */
	result = DBselect(
			"select hostid,itemid,key_"
			" from items"
			" where type in (0,3,7)"
				" and key_ like 'net.tcp.service%%[%%ntp%%'");

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		init_request(&request);

		if (SUCCEED != parse_item_key(row[2], &request))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse item key \"%s\"", row[2]);
			continue;
		}

		param = get_rparam(&request, 0);

		if (0 != strcmp("service.ntp", param) && 0 != strcmp("ntp", param))
		{
			free_request(&request);
			continue;
		}

		key = zbx_strdup(key, row[2]);

		if (0 == strcmp("service.ntp", param))
		{
			/* replace "service.ntp" with "ntp" */

			char	*p;

			p = strstr(key, "service.ntp");

			do
			{
				*p = *(p + 8);
			}
			while ('\0' != *(p++));
		}

		free_request(&request);

		/* replace "net.tcp.service" with "net.udp.service" */

		key[4] = 'u';
		key[5] = 'd';
		key[6] = 'p';

		key_esc = DBdyn_escape_string(key);

		result2 = DBselect("select null from items where hostid=%s and key_='%s'", row[0], key_esc);

		if (NULL == DBfetch(result2))
		{
			if (ZBX_DB_OK > DBexecute("update items set key_='%s' where itemid=%s", key_esc, row[1]))
				ret = FAIL;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot convert item key \"%s\":"
					" item with converted key \"%s\" already exists on host ID [%s]",
					row[2], key, row[0]);
		}
		DBfree_result(result2);

		zbx_free(key_esc);
	}
	DBfree_result(result);

	zbx_free(key);

	return ret;
}

static int	DBpatch_2050013(void)
{
	return DBdrop_table("user_history");
}

static int      DBpatch_2050014(void)
{
	if (ZBX_DB_OK <= DBexecute(
		"update config"
		" set default_theme="
			"case when default_theme in ('classic', 'originalblue')"
			" then 'blue-theme'"
			" else 'dark-theme' end"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int      DBpatch_2050015(void)
{
	if (ZBX_DB_OK <= DBexecute(
		"update users"
		" set theme=case when theme in ('classic', 'originalblue') then 'blue-theme' else 'dark-theme' end"
		" where theme<>'default'"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int      DBpatch_2050016(void)
{
	if (ZBX_DB_OK <= DBexecute(
		"update graph_theme set description='blue-theme',theme='blue-theme' where graphthemeid=1"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int      DBpatch_2050017(void)
{
	if (ZBX_DB_OK <= DBexecute(
		"update graph_theme set description='dark-theme',theme='dark-theme' where graphthemeid=2"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int      DBpatch_2050018(void)
{
	if (ZBX_DB_OK <= DBexecute("delete from graph_theme where graphthemeid in (3,4)"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2050019(void)
{
	const ZBX_FIELD	field = {"smtp_port", "25", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_2050020(void)
{
	const ZBX_FIELD	field = {"smtp_security", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_2050021(void)
{
	const ZBX_FIELD	field = {"smtp_verify_peer", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_2050022(void)
{
	const ZBX_FIELD	field = {"smtp_verify_host", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_2050023(void)
{
	const ZBX_FIELD	field = {"smtp_authentication", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

#endif

DBPATCH_START(2050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(2050000, 0, 1)
DBPATCH_ADD(2050001, 0, 1)
DBPATCH_ADD(2050002, 0, 1)
DBPATCH_ADD(2050003, 0, 1)
DBPATCH_ADD(2050004, 0, 1)
DBPATCH_ADD(2050005, 0, 0)
DBPATCH_ADD(2050006, 0, 0)
DBPATCH_ADD(2050007, 0, 1)
DBPATCH_ADD(2050008, 0, 1)
DBPATCH_ADD(2050009, 0, 1)
DBPATCH_ADD(2050010, 0, 1)
DBPATCH_ADD(2050011, 0, 1)
DBPATCH_ADD(2050012, 0, 1)
DBPATCH_ADD(2050013, 0, 0)
DBPATCH_ADD(2050014, 0, 1)
DBPATCH_ADD(2050015, 0, 1)
DBPATCH_ADD(2050016, 0, 1)
DBPATCH_ADD(2050017, 0, 1)
DBPATCH_ADD(2050018, 0, 1)
DBPATCH_ADD(2050019, 0, 1)
DBPATCH_ADD(2050020, 0, 1)
DBPATCH_ADD(2050021, 0, 1)
DBPATCH_ADD(2050022, 0, 1)
DBPATCH_ADD(2050023, 0, 1)

DBPATCH_END()
