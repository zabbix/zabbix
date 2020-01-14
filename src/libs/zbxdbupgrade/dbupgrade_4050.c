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

static int	DBpatch_4050015(void)
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
dbu_interface;

ZBX_PTR_VECTOR_DECL(dbu_interface, dbu_interface);
ZBX_PTR_VECTOR_IMPL(dbu_interface, dbu_interface);
ZBX_PTR_VECTOR_DECL(dbu_snmp_if, dbu_snmp_if_t);
ZBX_PTR_VECTOR_IMPL(dbu_snmp_if, dbu_snmp_if_t);

static void	db_interface_free(dbu_interface interface)
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

static void	DBpatch_4050016_load_data(zbx_vector_dbu_interface_t *interfaces, zbx_vector_dbu_snmp_if_t *snmp_ifs,
		zbx_vector_dbu_snmp_if_t *snmp_new_ifs)
{
	DB_RESULT		result;
	DB_ROW			row;

	result = DBselect(
			"SELECT s.interfaceid,"
				"s.type,"
				"s.bulk,"
				"s.snmp_community,"
				"s.snmpv3_securityname,"
				"s.snmpv3_securitylevel,"
				"s.snmpv3_authpassphrase,"
				"s.snmpv3_privpassphrase,"
				"s.snmpv3_authprotocol,"
				"s.snmpv3_privprotocol,"
				"s.snmpv3_contextname,"
				"s.port,"
				"s.hostid,"
				"n.main,"
				"n.type,"
				"n.useip,"
				"n.ip,"
				"n.dns,"
				"n.port"
			" FROM (SELECT i.interfaceid,"
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
					"i.hostid"
				" FROM items i"
					" LEFT JOIN hosts h ON i.hostid=h.hostid"
					" LEFT JOIN interface f ON i.interfaceid=f.interfaceid"
				" WHERE  i.type IN (1,4,6)"
					" AND h.status <> 3"
				" GROUP BY i.interfaceid,"
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
					"i.hostid) s"
				" LEFT JOIN interface n ON s.interfaceid=n.interfaceid"
				" ORDER BY s.interfaceid ASC");

	while (NULL != (row = DBfetch(result)))
	{
		dbu_interface		interface;
		dbu_snmp_if_t		snmp;
		int			item_type;
		const char 		*if_port;

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
		if_port = row[18];

		if (ITEM_TYPE_SNMPv1 == item_type)
			snmp.version = ZBX_IF_SNMP_VERSION_1;
		else if (ITEM_TYPE_SNMPv2c == item_type)
			snmp.version = ZBX_IF_SNMP_VERSION_2;
		else
			snmp.version = ZBX_IF_SNMP_VERSION_3;

		snmp.interfaceid = snmp.item_interfaceid;

		if ((0 == strlen(snmp.item_port) || 0 == strcmp(snmp.item_port, if_port)) &&
				FAIL == zbx_vector_dbu_snmp_if_bsearch(snmp_ifs, snmp, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			zbx_vector_dbu_snmp_if_append(snmp_ifs, snmp);
			continue;
		}

		snmp.interfaceid = DBget_maxid("interface");

		zbx_vector_dbu_snmp_if_append(snmp_new_ifs, snmp);

		interface.interfaceid = snmp.interfaceid;
		ZBX_DBROW2UINT64(interface.hostid, row[12]);
		ZBX_STR2UCHAR(interface.main, row[13]);
		ZBX_STR2UCHAR(interface.type, row[14]);
		ZBX_STR2UCHAR(interface.useip, row[15]);
		interface.ip = zbx_strdup(NULL, row[16]);
		interface.dns = zbx_strdup(NULL, row[17]);

		if (0 < strlen(snmp.item_port))
			interface.port = zbx_strdup(NULL, snmp.item_port);
		else
			interface.port = zbx_strdup(NULL, if_port);

		zbx_vector_dbu_interface_append(interfaces, interface);
	}
	DBfree_result(result);
}

static void	DBpatch_4050016_load_empty_if(zbx_vector_dbu_snmp_if_t *snmp_def_ifs)
{
	DB_RESULT		result;
	DB_ROW			row;

	result = DBselect(
			"select h.interfaceid,h.bulk"
			" from interface h"
			" where type=2 and h.interfaceid not in ("
				"select interfaceid "
				" from items i"
				" where i.interfaceid is not null"
					" and  i.type IN (1,4,6));");

	while (NULL != (row = DBfetch(result)))
	{
		dbu_snmp_if_t		snmp;

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

		zbx_vector_dbu_snmp_if_append(snmp_def_ifs, snmp);
	}
	DBfree_result(result);
}

static int	DBpatch_4050016_snmp_if_save(zbx_vector_dbu_snmp_if_t *snmp_ifs)
{
	zbx_db_insert_t	db_insert_snmp_if;
	int		i, ret;

	zbx_db_insert_prepare(&db_insert_snmp_if, "interface_snmp", "interfaceid", "version", "bulk", "community",
			"securityname", "securitylevel", "authpassphrase", "privpassphrase", "authprotocol",
			"privprotocol", "contextname", NULL);

	for (i = 0; i < snmp_ifs->values_num; i++)
	{
		dbu_snmp_if_t *s = &snmp_ifs->values[i];

		zbx_db_insert_add_values(&db_insert_snmp_if, s->interfaceid, s->version, s->bulk, s->community,
				s->securityname, s->securitylevel, s->authpassphrase, s->privpassphrase, s->authprotocol,
				s->privprotocol, s->contextname);
	}

	ret = zbx_db_insert_execute(&db_insert_snmp_if);
	zbx_db_insert_clean(&db_insert_snmp_if);

	return ret;
}

static int	DBpatch_4050016_interface_create(zbx_vector_dbu_interface_t *interfaces)
{
	zbx_db_insert_t		db_insert_interfaces;
	int			i, ret;

	zbx_db_insert_prepare(&db_insert_interfaces, "interfaces", "interfaceid", "hostid", "main", "type", "useip",
			"ip", "dns", "port", NULL);

	for (i = 0; i < interfaces->values_num; i++)
	{
		dbu_interface	*interface = &interfaces->values[i];

		zbx_db_insert_add_values(&db_insert_interfaces, interface->interfaceid,
				interface->hostid, interface->main, interface->type, interface->useip, interface->ip,
				interface->dns, interface->port);
	}

	ret = zbx_db_insert_execute(&db_insert_interfaces);
	zbx_db_insert_clean(&db_insert_interfaces);

	return ret;
}

static int	DBpatch_4050016_items_update(zbx_vector_dbu_snmp_if_t *snmp_ifs)
{
	int	i, ret = SUCCEED;
	char	*sql;
	size_t	sql_alloc = snmp_ifs->values_num * ZBX_KIBIBYTE / 3 , sql_offset = 0;

	sql = (char *)zbx_malloc(NULL, sql_alloc);
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < snmp_ifs->values_num && SUCCEED == ret; i++)
	{
		dbu_snmp_if_t *s = &snmp_ifs->values[i];

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update items set type=%d, interfaceid=" ZBX_FS_UI64
				" where type IN (1,4,6) AND h.status <> 3 AND"
					" interfaceid=" ZBX_FS_UI64 " AND "
					" snmp_community='%s' AND"
					" snmpv3_securityname='%s' AND"
					" snmpv3_securitylevel=%d AND"
					" snmpv3_authpassphrase='%s' AND"
					" snmpv3_privpassphrase='%s' AND"
					" snmpv3_authprotocol=%d AND"
					" snmpv3_privprotocol=%d AND"
					" snmpv3_contextname='%s' AND"
					" port='%s';\n",
				ITEM_TYPE_SNMP, s->interfaceid,
				s->item_interfaceid, s->community, s->securityname, (int)s->securitylevel,
				s->authpassphrase, s->privpassphrase, (int)s->authprotocol, (int)s->privprotocol,
				s->contextname, s->item_port);

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
}

static int	DBpatch_4050016_items_type_update(void)
{
	if (ZBX_DB_OK > DBexecute("update items set type=%d where type IN (1,4,6)", ITEM_TYPE_SNMP))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4050016(void)
{
	zbx_vector_dbu_interface_t	interfaces;
	zbx_vector_dbu_snmp_if_t	snmp_ifs, snmp_new_ifs, snmp_def_ifs;
	int				ret = FAIL;

	zbx_vector_dbu_snmp_if_create(&snmp_ifs);
	zbx_vector_dbu_snmp_if_create(&snmp_new_ifs);
	zbx_vector_dbu_snmp_if_create(&snmp_def_ifs);
	zbx_vector_dbu_interface_create(&interfaces);

	DBpatch_4050016_load_data(&interfaces, &snmp_ifs, &snmp_new_ifs);
	DBpatch_4050016_load_empty_if(&snmp_def_ifs);

	while(1)
	{
		if (0 < snmp_ifs.values_num && SUCCEED != DBpatch_4050016_snmp_if_save(&snmp_ifs))
			break;

		if (0 < interfaces.values_num && SUCCEED != DBpatch_4050016_interface_create(&interfaces))
			break;

		if (0 < snmp_new_ifs.values_num && SUCCEED != DBpatch_4050016_snmp_if_save(&snmp_new_ifs))
			break;

		if (0 < snmp_def_ifs.values_num && SUCCEED != DBpatch_4050016_snmp_if_save(&snmp_def_ifs))
			break;

		if (0 < snmp_new_ifs.values_num && SUCCEED != DBpatch_4050016_items_update(&snmp_new_ifs))
			break;

		if (SUCCEED != DBpatch_4050016_items_type_update())
			break;

		ret = SUCCEED;
		break;
	}

	zbx_vector_dbu_interface_clear_ext(&interfaces, db_interface_free);
	zbx_vector_dbu_interface_destroy(&interfaces);
	zbx_vector_dbu_snmp_if_clear_ext(&snmp_ifs, db_snmpinterface_free);
	zbx_vector_dbu_snmp_if_destroy(&snmp_ifs);
	zbx_vector_dbu_snmp_if_clear_ext(&snmp_new_ifs, db_snmpinterface_free);
	zbx_vector_dbu_snmp_if_destroy(&snmp_new_ifs);
	zbx_vector_dbu_snmp_if_clear_ext(&snmp_def_ifs, db_snmpinterface_free);
	zbx_vector_dbu_snmp_if_destroy(&snmp_def_ifs);

	return ret;
}

static int	DBpatch_4050017(void)
{
	return DBdrop_field("interface", "bulk");
}

static int	DBpatch_4050018(void)
{
	return DBdrop_field("items", "snmp_community");
}

static int	DBpatch_4050019(void)
{
	return DBdrop_field("items", "snmpv3_securityname");
}

static int	DBpatch_4050020(void)
{
	return DBdrop_field("items", "snmpv3_securitylevel");
}

static int	DBpatch_4050021(void)
{
	return DBdrop_field("items", "snmpv3_authpassphrase");
}

static int	DBpatch_4050022(void)
{
	return DBdrop_field("items", "snmpv3_privpassphrase");
}

static int	DBpatch_4050023(void)
{
	return DBdrop_field("items", "snmpv3_authprotocol");
}

static int	DBpatch_4050024(void)
{
	return DBdrop_field("items", "snmpv3_privprotocol");
}

static int	DBpatch_4050025(void)
{
	return DBdrop_field("items", "snmpv3_contextname");
}

static int	DBpatch_4050026(void)
{
	return DBdrop_field("items", "port");
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

DBPATCH_END()
