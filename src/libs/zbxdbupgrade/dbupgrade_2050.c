/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "zbxsysinfo.h"
#include "zbxparam.h"
#include "zbxdb.h"
#include "zbxdbschema.h"
#include "zbxstr.h"

/*
 * 3.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_2050000(void)
{
	const zbx_db_field_t	field = {"agent", "Zabbix", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("httptest", &field);
}

static int	DBpatch_2050001(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*oid = NULL;
	size_t		oid_alloc = 0;
	int		ret = FAIL, rc;

	/* flags - ZBX_FLAG_DISCOVERY_RULE                               */
	/* type  - ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c, ITEM_TYPE_SNMPv3 */
	if (NULL == (result = zbx_db_select("select itemid,snmp_oid from items where flags=1 and type in (1,4,6)")))
		return FAIL;

	while (NULL != (row = zbx_db_fetch(result)))
	{
		char	*param, *oid_esc;
		size_t	oid_offset = 0;

		param = zbx_strdup(NULL, row[1]);
		zbx_snprintf_alloc(&oid, &oid_alloc, &oid_offset, "discovery[{#SNMPVALUE},%s]", param);

		if (FAIL == zbx_quote_key_param(&param, 0))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot convert SNMP discovery OID \"%s\":"
					" OID contains invalid character(s)", row[1]);
			rc = ZBX_DB_OK;
		}
		else if (255 < oid_offset && 255 < zbx_strlen_utf8(oid)) /* 255 - ITEM_SNMP_OID_LEN */
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot convert SNMP discovery OID \"%s\":"
					" resulting OID is too long", row[1]);
			rc = ZBX_DB_OK;
		}
		else
		{
			oid_esc = zbx_db_dyn_escape_string(oid);

			rc = zbx_db_execute("update items set snmp_oid='%s' where itemid=%s", oid_esc, row[0]);

			zbx_free(oid_esc);
		}

		zbx_free(param);

		if (ZBX_DB_OK > rc)
			goto out;
	}

	ret = SUCCEED;
out:
	zbx_db_free_result(result);
	zbx_free(oid);

	return ret;
}

static int	DBpatch_2050002(void)
{
	const zbx_db_field_t	field = {"lastlogsize", "0", NULL, NULL, 0, ZBX_TYPE_UINT, ZBX_NOTNULL, 0};

	return DBadd_field("proxy_history", &field);
}

static int	DBpatch_2050003(void)
{
	const zbx_db_field_t	field = {"mtime", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("proxy_history", &field);
}

static int	DBpatch_2050004(void)
{
	const zbx_db_field_t	field = {"meta", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

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
	const zbx_db_field_t	field = {"error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("hosts", &field, NULL);
}

static int	DBpatch_2050008(void)
{
	const zbx_db_field_t	field = {"ipmi_error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("hosts", &field, NULL);
}

static int	DBpatch_2050009(void)
{
	const zbx_db_field_t	field = {"snmp_error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("hosts", &field, NULL);
}

static int	DBpatch_2050010(void)
{
	const zbx_db_field_t	field = {"jmx_error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("hosts", &field, NULL);
}

static int	DBpatch_2050011(void)
{
	/* 1 - ITEM_VALUE_TYPE_STR, 2 - ITEM_VALUE_TYPE_LOG, 4 - ITEM_VALUE_TYPE_TEXT */
	if (ZBX_DB_OK <= zbx_db_execute("update items set trends=0 where value_type in (1,2,4)"))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2050012(void)
{
	zbx_db_result_t	result;
	zbx_db_result_t	result2;
	zbx_db_row_t	row;
	char		*key = NULL, *key_esc, *param;
	int		ret = SUCCEED;
	AGENT_REQUEST	request;

	/* type - ITEM_TYPE_ZABBIX, ITEM_TYPE_SIMPLE, ITEM_TYPE_ZABBIX_ACTIVE */
	result = zbx_db_select(
			"select hostid,itemid,key_"
			" from items"
			" where type in (0,3,7)"
				" and key_ like 'net.tcp.service%%[%%ntp%%'");

	while (SUCCEED == ret && NULL != (row = zbx_db_fetch(result)))
	{
		zbx_init_agent_request(&request);

		if (SUCCEED != zbx_parse_item_key(row[2], &request))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse item key \"%s\"", row[2]);
			continue;
		}

		param = get_rparam(&request, 0);

		/* NULL check to silence static analyzer warning */
		if (NULL == param || (0 != strcmp("service.ntp", param) && 0 != strcmp("ntp", param)))
		{
			zbx_free_agent_request(&request);
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

		zbx_free_agent_request(&request);

		/* replace "net.tcp.service" with "net.udp.service" */

		key[4] = 'u';
		key[5] = 'd';
		key[6] = 'p';

		key_esc = zbx_db_dyn_escape_string(key);

		result2 = zbx_db_select("select null from items where hostid=%s and key_='%s'", row[0], key_esc);

		if (NULL == zbx_db_fetch(result2))
		{
			if (ZBX_DB_OK > zbx_db_execute("update items set key_='%s' where itemid=%s", key_esc, row[1]))
				ret = FAIL;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot convert item key \"%s\":"
					" item with converted key \"%s\" already exists on host ID [%s]",
					row[2], key, row[0]);
		}
		zbx_db_free_result(result2);

		zbx_free(key_esc);
	}
	zbx_db_free_result(result);

	zbx_free(key);

	return ret;
}

static int	DBpatch_2050013(void)
{
	return DBdrop_table("user_history");
}

static int	DBpatch_2050014(void)
{
	if (ZBX_DB_OK <= zbx_db_execute(
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

static int	DBpatch_2050015(void)
{
	if (ZBX_DB_OK <= zbx_db_execute(
		"update users"
		" set theme=case when theme in ('classic', 'originalblue') then 'blue-theme' else 'dark-theme' end"
		" where theme<>'default'"))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_2050019(void)
{
	const zbx_db_field_t	field = {"smtp_port", "25", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_2050020(void)
{
	const zbx_db_field_t	field = {"smtp_security", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_2050021(void)
{
	const zbx_db_field_t	field = {"smtp_verify_peer", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_2050022(void)
{
	const zbx_db_field_t	field = {"smtp_verify_host", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_2050023(void)
{
	const zbx_db_field_t	field = {"smtp_authentication", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_2050029(void)
{
	const zbx_db_field_t	field = {"default_theme", "blue-theme", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_2050030(void)
{
	const zbx_db_table_t	table =
			{"application_prototype", "application_prototypeid", 0,
				{
					{"application_prototypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"templateid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2050031(void)
{
	return DBcreate_index("application_prototype", "application_prototype_1", "itemid", 0);
}

static int	DBpatch_2050032(void)
{
	return DBcreate_index("application_prototype", "application_prototype_2", "templateid", 0);
}

static int	DBpatch_2050033(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("application_prototype", 1, &field);
}

static int	DBpatch_2050034(void)
{
	const zbx_db_field_t	field = {"templateid", NULL, "application_prototype", "application_prototypeid",
			0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("application_prototype", 2, &field);
}

static int	DBpatch_2050035(void)
{
	const zbx_db_table_t	table =
			{"item_application_prototype", "item_application_prototypeid", 0,
				{
					{"item_application_prototypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL,
							0},
					{"application_prototypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2050036(void)
{
	return DBcreate_index("item_application_prototype", "item_application_prototype_1",
			"application_prototypeid,itemid", 1);
}

static int	DBpatch_2050037(void)
{
	return DBcreate_index("item_application_prototype", "item_application_prototype_2", "itemid", 0);
}

static int	DBpatch_2050038(void)
{
	const zbx_db_field_t	field = {"application_prototypeid", NULL, "application_prototype",
			"application_prototypeid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_application_prototype", 1, &field);
}

static int	DBpatch_2050039(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_application_prototype", 2, &field);
}

static int	DBpatch_2050040(void)
{
	const zbx_db_table_t	table =
			{"application_discovery", "application_discoveryid", 0,
				{
					{"application_discoveryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"applicationid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"application_prototypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"lastcheck", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ts_delete", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2050041(void)
{
	return DBcreate_index("application_discovery", "application_discovery_1", "applicationid", 0);
}

static int	DBpatch_2050042(void)
{
	return DBcreate_index("application_discovery", "application_discovery_2", "application_prototypeid", 0);
}

static int	DBpatch_2050043(void)
{
	const zbx_db_field_t	field = {"applicationid", NULL, "applications", "applicationid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("application_discovery", 1, &field);
}

static int	DBpatch_2050044(void)
{
	const zbx_db_field_t	field = {"application_prototypeid", NULL, "application_prototype",
			"application_prototypeid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("application_discovery", 2, &field);
}

static int	DBpatch_2050045(void)
{
	const zbx_db_field_t	field = {"flags", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("applications", &field);
}

static int	DBpatch_2050051(void)
{
	const zbx_db_field_t	field = {"iprange", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("drules", &field, NULL);
}

static int	DBpatch_2050052(void)
{
	const zbx_db_field_t	field = {"default_inventory_mode", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_2050053(void)
{
	const zbx_db_table_t	table =
			{"opinventory", "operationid", 0,
				{
					{"operationid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"inventory_mode", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2050054(void)
{
	const zbx_db_field_t	field = {"operationid", NULL, "operations", "operationid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("opinventory", 1, &field);
}

static int	DBpatch_2050055(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = FAIL;

	if (NULL == (result = zbx_db_select(
			"select severity_color_0,severity_color_1,severity_color_2,severity_color_3,severity_color_4,"
				"severity_color_5"
			" from config")))
	{
		return FAIL;
	}

	if (NULL != (row = zbx_db_fetch(result)) &&
			0 == strcmp(row[0], "DBDBDB") && 0 == strcmp(row[1], "D6F6FF") &&
			0 == strcmp(row[2], "FFF6A5") && 0 == strcmp(row[3], "FFB689") &&
			0 == strcmp(row[4], "FF9999") && 0 == strcmp(row[5], "FF3838"))
	{
		if (ZBX_DB_OK > zbx_db_execute(
				"update config set severity_color_0='97AAB3',severity_color_1='7499FF',"
					"severity_color_2='FFC859',severity_color_3='FFA059',"
					"severity_color_4='E97659',severity_color_5='E45959'"))
		{
			goto out;
		}
	}

	ret = SUCCEED;
out:
	zbx_db_free_result(result);

	return ret;
}

static int	DBpatch_2050056(void)
{
	const zbx_db_field_t	field = {"severity_color_0", "97AAB3", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_2050057(void)
{
	const zbx_db_field_t	field = {"severity_color_1", "7499FF", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_2050058(void)
{
	const zbx_db_field_t	field = {"severity_color_2", "FFC859", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_2050059(void)
{
	const zbx_db_field_t	field = {"severity_color_3", "FFA059", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_2050060(void)
{
	const zbx_db_field_t	field = {"severity_color_4", "E97659", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_2050061(void)
{
	const zbx_db_field_t	field = {"severity_color_5", "E45959", NULL, NULL, 6, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_2050062(void)
{
	const zbx_db_field_t	field = {"exec_params", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_2050063(void)
{
	/* type=1 -> type=MEDIA_TYPE_EXEC */
	if (ZBX_DB_OK > zbx_db_execute("update media_type"
			" set exec_params='{ALERT.SENDTO}\n{ALERT.SUBJECT}\n{ALERT.MESSAGE}\n'"
			" where type=1"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_2050064(void)
{
	const zbx_db_field_t	field = {"tls_connect", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_2050065(void)
{
	const zbx_db_field_t	field = {"tls_accept", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_2050066(void)
{
	const zbx_db_field_t	field = {"tls_issuer", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_2050067(void)
{
	const zbx_db_field_t	field = {"tls_subject", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_2050068(void)
{
	const zbx_db_field_t	field = {"tls_psk_identity", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_2050069(void)
{
	const zbx_db_field_t	field = {"tls_psk", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_2050070(void)
{
	const zbx_db_field_t	field = {"macro", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("globalmacro", &field, NULL);
}

static int	DBpatch_2050071(void)
{
	const zbx_db_field_t	field = {"macro", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("hostmacro", &field, NULL);
}

static int	DBpatch_2050077(void)
{
	const zbx_db_field_t	field = {"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("sysmaps", &field);
}

static int	DBpatch_2050078(void)
{
	/* type=3 -> type=USER_TYPE_SUPER_ADMIN */
	if (ZBX_DB_OK > zbx_db_execute("update sysmaps set userid=(select min(userid) from users where type=3)"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_2050079(void)
{
	const zbx_db_field_t	field = {"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBset_not_null("sysmaps", &field);
}

static int	DBpatch_2050080(void)
{
	const zbx_db_field_t	field = {"userid", NULL, "users", "userid", 0, 0, 0, 0};

	return DBadd_foreign_key("sysmaps", 3, &field);
}

static int	DBpatch_2050081(void)
{
	const zbx_db_field_t field = {"private", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("sysmaps", &field);
}

static int	DBpatch_2050082(void)
{
	const zbx_db_table_t	table =
			{"sysmap_user",	"sysmapuserid",	0,
				{
					{"sysmapuserid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"sysmapid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"permission", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2050083(void)
{
	return DBcreate_index("sysmap_user", "sysmap_user_1", "sysmapid,userid", 1);
}

static int	DBpatch_2050084(void)
{
	const zbx_db_field_t	field = {"sysmapid", NULL, "sysmaps", "sysmapid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sysmap_user", 1, &field);
}

static int	DBpatch_2050085(void)
{
	const zbx_db_field_t	field = {"userid", NULL, "users", "userid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sysmap_user", 2, &field);
}

static int	DBpatch_2050086(void)
{
	const zbx_db_table_t	table =
			{"sysmap_usrgrp", "sysmapusrgrpid", 0,
				{
					{"sysmapusrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"sysmapid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"permission", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2050087(void)
{
	return DBcreate_index("sysmap_usrgrp", "sysmap_usrgrp_1", "sysmapid,usrgrpid", 1);
}

static int	DBpatch_2050088(void)
{
	const zbx_db_field_t	field = {"sysmapid", NULL, "sysmaps", "sysmapid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sysmap_usrgrp", 1, &field);
}

static int	DBpatch_2050089(void)
{
	const zbx_db_field_t	field = {"usrgrpid", NULL, "usrgrp", "usrgrpid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("sysmap_usrgrp", 2, &field);
}

static int	DBpatch_2050090(void)
{
	if (ZBX_DB_OK > zbx_db_execute("update profiles"
			" set idx='web.triggers.filter_status',value_int=case when value_int=0 then 0 else -1 end"
			" where idx='web.triggers.showdisabled'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_2050091(void)
{
	if (ZBX_DB_OK > zbx_db_execute("update profiles"
			" set idx='web.httpconf.filter_status',value_int=case when value_int=0 then 0 else -1 end"
			" where idx='web.httpconf.showdisabled'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_2050092(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	const char	*end, *start;
	int		len, ret = FAIL, rc;
	char		*url = NULL, *url_esc;
	size_t		i, url_alloc = 0, url_offset;
	const char	*url_map[] = {
				"dashboard.php", "dashboard.view",
				"discovery.php", "discovery.view",
				"maps.php", "map.view",
				"httpmon.php", "web.view",
				"media_types.php", "mediatype.list",
				"proxies.php", "proxy.list",
				"scripts.php", "script.list",
				"report3.php", "report.services",
				"report1.php", "report.status"
			};

	if (NULL == (result = zbx_db_select("select userid,url from users where url<>''")))
		return FAIL;

	while (NULL != (row = (zbx_db_fetch(result))))
	{
		if (NULL == (end = strchr(row[1], '?')))
			end = row[1] + strlen(row[1]);

		for (start = end - 1; start > row[1] && '/' != start[-1]; start--)
			;

		len = end - start;

		for (i = 0; ARRSIZE(url_map) > i; i += 2)
		{
			if (0 == strncmp(start, url_map[i], len))
				break;
		}

		if (ARRSIZE(url_map) == i)
			continue;

		url_offset = 0;
		zbx_strncpy_alloc(&url, &url_alloc, &url_offset, row[1], start - row[1]);
		zbx_strcpy_alloc(&url, &url_alloc, &url_offset, "zabbix.php?action=");
		zbx_strcpy_alloc(&url, &url_alloc, &url_offset, url_map[i + 1]);

		if ('\0' != *end)
		{
			zbx_chrcpy_alloc(&url, &url_alloc, &url_offset, '&');
			zbx_strcpy_alloc(&url, &url_alloc, &url_offset, end + 1);
		}

		/* 255 - user url field size */
		if (url_offset > 255)
		{
			*url = '\0';
			zabbix_log(LOG_LEVEL_WARNING, "Cannot convert URL for user id \"%s\":"
					" value is too long. The URL field was reset.", row[0]);
		}

		url_esc = zbx_db_dyn_escape_string(url);
		rc = zbx_db_execute("update users set url='%s' where userid=%s", url_esc, row[0]);
		zbx_free(url_esc);

		if (ZBX_DB_OK > rc)
			goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(url);
	zbx_db_free_result(result);

	return ret;
}
static int	DBpatch_2050093(void)
{
	const zbx_db_field_t	field = {"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("screens", &field);
}

static int	DBpatch_2050094(void)
{
	/* type=3 -> type=USER_TYPE_SUPER_ADMIN */
	if (ZBX_DB_OK > zbx_db_execute("update screens"
			" set userid=(select min(userid) from users where type=3)"
			" where templateid is null"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_2050095(void)
{
	const zbx_db_field_t	field = {"userid", NULL, "users", "userid", 0, 0, 0, 0};

	return DBadd_foreign_key("screens", 3, &field);
}

static int	DBpatch_2050096(void)
{
	const zbx_db_field_t	field = {"private", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("screens", &field);
}

static int	DBpatch_2050097(void)
{
	const zbx_db_table_t	table =
			{"screen_user",	"screenuserid",	0,
				{
					{"screenuserid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"screenid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"permission", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2050098(void)
{
	return DBcreate_index("screen_user", "screen_user_1", "screenid,userid", 1);
}

static int	DBpatch_2050099(void)
{
	const zbx_db_field_t	field = {"screenid", NULL, "screens", "screenid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("screen_user", 1, &field);
}

static int	DBpatch_2050100(void)
{
	const zbx_db_field_t	field = {"userid", NULL, "users", "userid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("screen_user", 2, &field);
}

static int	DBpatch_2050101(void)
{
	const zbx_db_table_t	table =
			{"screen_usrgrp", "screenusrgrpid", 0,
				{
					{"screenusrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"screenid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"permission", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2050102(void)
{
	return DBcreate_index("screen_usrgrp", "screen_usrgrp_1", "screenid,usrgrpid", 1);
}

static int	DBpatch_2050103(void)
{
	const zbx_db_field_t	field = {"screenid", NULL, "screens", "screenid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("screen_usrgrp", 1, &field);
}

static int	DBpatch_2050104(void)
{
	const zbx_db_field_t	field = {"usrgrpid", NULL, "usrgrp", "usrgrpid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("screen_usrgrp", 2, &field);
}

static int	DBpatch_2050105(void)
{
	const zbx_db_field_t	field = {"flags", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBrename_field("proxy_history", "meta", &field);
}

static int	DBpatch_2050106(void)
{
	/* convert meta value (1) to ZBX_PROXY_HISTORY_FLAG_META | ZBX_PROXY_HISTORY_FLAG_NOVALUE (0x03) flags */
	if (ZBX_DB_OK > zbx_db_execute("update proxy_history set flags=3 where flags=1"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_2050107(void)
{
	const zbx_db_field_t	field = {"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("slideshows", &field);
}

static int	DBpatch_2050108(void)
{
	/* type=3 -> type=USER_TYPE_SUPER_ADMIN */
	if (ZBX_DB_OK > zbx_db_execute("update slideshows set userid=(select min(userid) from users where type=3)"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_2050109(void)
{
	const zbx_db_field_t	field = {"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBset_not_null("slideshows", &field);
}

static int	DBpatch_2050110(void)
{
	const zbx_db_field_t	field = {"userid", NULL, "users", "userid", 0, 0, 0, 0};

	return DBadd_foreign_key("slideshows", 3, &field);
}

static int	DBpatch_2050111(void)
{
	const zbx_db_field_t	field = {"private", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("slideshows", &field);
}

static int	DBpatch_2050112(void)
{
	const zbx_db_table_t	table =
			{"slideshow_user", "slideshowuserid", 0,
				{
					{"slideshowuserid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"slideshowid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"permission", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2050113(void)
{
	return DBcreate_index("slideshow_user", "slideshow_user_1", "slideshowid,userid", 1);
}

static int	DBpatch_2050114(void)
{
	const zbx_db_field_t	field = {"slideshowid", NULL, "slideshows", "slideshowid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("slideshow_user", 1, &field);
}

static int	DBpatch_2050115(void)
{
	const zbx_db_field_t	field = {"userid", NULL, "users", "userid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("slideshow_user", 2, &field);
}

static int	DBpatch_2050116(void)
{
	const zbx_db_table_t	table =
			{"slideshow_usrgrp", "slideshowusrgrpid", 0,
				{
					{"slideshowusrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"slideshowid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"permission", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_2050117(void)
{
	return DBcreate_index("slideshow_usrgrp", "slideshow_usrgrp_1", "slideshowid,usrgrpid", 1);
}

static int	DBpatch_2050118(void)
{
	const zbx_db_field_t	field = {"slideshowid", NULL, "slideshows", "slideshowid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("slideshow_usrgrp", 1, &field);
}

static int	DBpatch_2050119(void)
{
	const zbx_db_field_t	field = {"usrgrpid", NULL, "usrgrp", "usrgrpid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("slideshow_usrgrp", 2, &field);
}

static int	DBpatch_2050120(void)
{
	/* private=0 -> PUBLIC_SHARING */
	if (ZBX_DB_OK <= zbx_db_execute("update sysmaps set private=0"))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2050121(void)
{
	/* private=0 -> PUBLIC_SHARING */
	if (ZBX_DB_OK <= zbx_db_execute("update screens set private=0"))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_2050122(void)
{
	/* private=0 -> PUBLIC_SHARING */
	if (ZBX_DB_OK <= zbx_db_execute("update slideshows set private=0"))
		return SUCCEED;

	return FAIL;
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
DBPATCH_ADD(2050019, 0, 1)
DBPATCH_ADD(2050020, 0, 1)
DBPATCH_ADD(2050021, 0, 1)
DBPATCH_ADD(2050022, 0, 1)
DBPATCH_ADD(2050023, 0, 1)
DBPATCH_ADD(2050029, 0, 1)
DBPATCH_ADD(2050030, 0, 1)
DBPATCH_ADD(2050031, 0, 1)
DBPATCH_ADD(2050032, 0, 1)
DBPATCH_ADD(2050033, 0, 1)
DBPATCH_ADD(2050034, 0, 1)
DBPATCH_ADD(2050035, 0, 1)
DBPATCH_ADD(2050036, 0, 1)
DBPATCH_ADD(2050037, 0, 1)
DBPATCH_ADD(2050038, 0, 1)
DBPATCH_ADD(2050039, 0, 1)
DBPATCH_ADD(2050040, 0, 1)
DBPATCH_ADD(2050041, 0, 1)
DBPATCH_ADD(2050042, 0, 1)
DBPATCH_ADD(2050043, 0, 1)
DBPATCH_ADD(2050044, 0, 1)
DBPATCH_ADD(2050045, 0, 1)
DBPATCH_ADD(2050051, 0, 1)
DBPATCH_ADD(2050052, 0, 1)
DBPATCH_ADD(2050053, 0, 1)
DBPATCH_ADD(2050054, 0, 1)
DBPATCH_ADD(2050055, 0, 1)
DBPATCH_ADD(2050056, 0, 1)
DBPATCH_ADD(2050057, 0, 1)
DBPATCH_ADD(2050058, 0, 1)
DBPATCH_ADD(2050059, 0, 1)
DBPATCH_ADD(2050060, 0, 1)
DBPATCH_ADD(2050061, 0, 1)
DBPATCH_ADD(2050062, 0, 1)
DBPATCH_ADD(2050063, 0, 1)
DBPATCH_ADD(2050064, 0, 1)
DBPATCH_ADD(2050065, 0, 1)
DBPATCH_ADD(2050066, 0, 1)
DBPATCH_ADD(2050067, 0, 1)
DBPATCH_ADD(2050068, 0, 1)
DBPATCH_ADD(2050069, 0, 1)
DBPATCH_ADD(2050070, 0, 1)
DBPATCH_ADD(2050071, 0, 1)
DBPATCH_ADD(2050077, 0, 1)
DBPATCH_ADD(2050078, 0, 1)
DBPATCH_ADD(2050079, 0, 1)
DBPATCH_ADD(2050080, 0, 1)
DBPATCH_ADD(2050081, 0, 1)
DBPATCH_ADD(2050082, 0, 1)
DBPATCH_ADD(2050083, 0, 1)
DBPATCH_ADD(2050084, 0, 1)
DBPATCH_ADD(2050085, 0, 1)
DBPATCH_ADD(2050086, 0, 1)
DBPATCH_ADD(2050087, 0, 1)
DBPATCH_ADD(2050088, 0, 1)
DBPATCH_ADD(2050089, 0, 1)
DBPATCH_ADD(2050090, 0, 1)
DBPATCH_ADD(2050091, 0, 1)
DBPATCH_ADD(2050092, 0, 1)
DBPATCH_ADD(2050093, 0, 1)
DBPATCH_ADD(2050094, 0, 1)
DBPATCH_ADD(2050095, 0, 1)
DBPATCH_ADD(2050096, 0, 1)
DBPATCH_ADD(2050097, 0, 1)
DBPATCH_ADD(2050098, 0, 1)
DBPATCH_ADD(2050099, 0, 1)
DBPATCH_ADD(2050100, 0, 1)
DBPATCH_ADD(2050101, 0, 1)
DBPATCH_ADD(2050102, 0, 1)
DBPATCH_ADD(2050103, 0, 1)
DBPATCH_ADD(2050104, 0, 1)
DBPATCH_ADD(2050105, 0, 1)
DBPATCH_ADD(2050106, 0, 1)
DBPATCH_ADD(2050107, 0, 1)
DBPATCH_ADD(2050108, 0, 1)
DBPATCH_ADD(2050109, 0, 1)
DBPATCH_ADD(2050110, 0, 1)
DBPATCH_ADD(2050111, 0, 1)
DBPATCH_ADD(2050112, 0, 1)
DBPATCH_ADD(2050113, 0, 1)
DBPATCH_ADD(2050114, 0, 1)
DBPATCH_ADD(2050115, 0, 1)
DBPATCH_ADD(2050116, 0, 1)
DBPATCH_ADD(2050117, 0, 1)
DBPATCH_ADD(2050118, 0, 1)
DBPATCH_ADD(2050119, 0, 1)
DBPATCH_ADD(2050120, 0, 1)
DBPATCH_ADD(2050121, 0, 1)
DBPATCH_ADD(2050122, 0, 1)

DBPATCH_END()
