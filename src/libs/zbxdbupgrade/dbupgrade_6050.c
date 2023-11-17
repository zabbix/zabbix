/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "dbupgrade.h"

#include "zbxdbschema.h"
#include "zbxdbhigh.h"
#include "zbxtypes.h"
#include "zbxregexp.h"

/*
 * 7.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_6050000(void)
{
	const zbx_db_field_t	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field, NULL);
}

static int	DBpatch_6050001(void)
{
	const zbx_db_field_t	field = {"geomaps_tile_url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field, NULL);
}

static int	DBpatch_6050002(void)
{
	const zbx_db_field_t	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("sysmap_url", &field, NULL);
}

static int	DBpatch_6050003(void)
{
	const zbx_db_field_t	field = {"url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("sysmap_element_url", &field, NULL);
}

static int	DBpatch_6050004(void)
{
	const zbx_db_field_t	field = {"url_a", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_inventory", &field, NULL);
}

static int	DBpatch_6050005(void)
{
	const zbx_db_field_t	field = {"url_b", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_inventory", &field, NULL);
}

static int	DBpatch_6050006(void)
{
	const zbx_db_field_t	field = {"url_c", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_inventory", &field, NULL);
}

static int	DBpatch_6050007(void)
{
	const zbx_db_field_t	field = {"value_str", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("widget_field", &field, NULL);
}

static int	DBpatch_6050008(void)
{
	const zbx_db_field_t	field = {"value", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};
	int	ret;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

#if defined(HAVE_ORACLE)
	if (SUCCEED == zbx_db_check_oracle_colum_type("history", "value", ZBX_TYPE_FLOAT))
		return SUCCEED;
#elif defined(HAVE_POSTGRESQL)
	if (SUCCEED == DBcheck_field_type("history", &field))
		return SUCCEED;
#endif
	if (SUCCEED != (ret = DBmodify_field_type("history", &field, &field)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot perform database upgrade of history table, please check upgrade"
				" notes");
	}

	return ret;
}

static int	DBpatch_6050009(void)
{
	const zbx_db_field_t	field = {"value_min", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

#if defined(HAVE_ORACLE)
	if (SUCCEED == zbx_db_check_oracle_colum_type("trends", "value_min", ZBX_TYPE_FLOAT))
		return SUCCEED;
#elif defined(HAVE_POSTGRESQL)
	if (SUCCEED == DBcheck_field_type("trends", &field))
		return SUCCEED;
#endif
	return DBmodify_field_type("trends", &field, &field);
}

static int	DBpatch_6050010(void)
{
	const zbx_db_field_t	field = {"value_avg", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};
	int			ret;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

#if defined(HAVE_ORACLE)
	if (SUCCEED == zbx_db_check_oracle_colum_type("trends", "value_avg", ZBX_TYPE_FLOAT))
		return SUCCEED;
#elif defined(HAVE_POSTGRESQL)
	if (SUCCEED == DBcheck_field_type("trends", &field))
		return SUCCEED;
#endif

	if (SUCCEED != (ret = DBmodify_field_type("trends", &field, &field)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot perform database upgrade of trends table, please check upgrade"
				" notes");
	}

	return ret;
}

static int	DBpatch_6050011(void)
{
	const zbx_db_field_t	field = {"value_max", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0};
	int			ret;

#if defined(HAVE_ORACLE)
	if (SUCCEED == zbx_db_check_oracle_colum_type("trends", "value_max", ZBX_TYPE_FLOAT))
		return SUCCEED;
#elif defined(HAVE_POSTGRESQL)
	if (SUCCEED == DBcheck_field_type("trends", &field))
		return SUCCEED;
#endif /* defined(HAVE_ORACLE) */

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (SUCCEED != (ret = DBmodify_field_type("trends", &field, &field)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot perform database upgrade of trends table, please check upgrade"
				" notes");
	}

	return ret;
}

static int	DBpatch_6050012(void)
{
	const zbx_db_field_t	field = {"allow_redirect", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("dchecks", &field);
}

static int	DBpatch_6050013(void)
{
	const zbx_db_table_t	table =
			{"history_bin", "itemid,clock,ns", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ns", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 0, ZBX_TYPE_BLOB, ZBX_NOTNULL, 0},
					{NULL}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050014(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"delete from widget_field"
			" where name='adv_conf' and widgetid in ("
				"select widgetid"
				" from widget"
				" where type in ('clock', 'item')"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050015(void)
{
	const zbx_db_field_t	field = {"http_user", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBmodify_field_type("httptest", &field, NULL);
}

static int	DBpatch_6050016(void)
{
	const zbx_db_field_t	field = {"http_password", "", NULL, NULL, 255, ZBX_TYPE_CHAR,
			ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBmodify_field_type("httptest", &field, NULL);
}

static int	DBpatch_6050017(void)
{
	const zbx_db_field_t	field = {"username", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBmodify_field_type("items", &field, NULL);
}

static int	DBpatch_6050018(void)
{
	const zbx_db_field_t	field = {"password", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBmodify_field_type("items", &field, NULL);
}

static int	DBpatch_6050019(void)
{
	const zbx_db_field_t	field = {"username", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("connector", &field, NULL);
}

static int	DBpatch_6050020(void)
{
	const zbx_db_field_t	field = {"password", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("connector", &field, NULL);
}

static int	DBpatch_6050021(void)
{
	const zbx_db_field_t	field = {"concurrency_max", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("drules", &field);
}

static int	DBpatch_6050022(void)
{
	if (ZBX_DB_OK > zbx_db_execute("update drules set concurrency_max=1"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050023(void)
{
	const char	*sql =
			"update widget_field"
			" set name='acknowledgement_status'"
			" where name='unacknowledged'"
				" and exists ("
					"select null"
					" from widget w"
					" where widget_field.widgetid=w.widgetid"
						" and w.type='problems'"
				")";

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_6050024(void)
{
	const char	*sql =
			"update widget_field"
			" set name='show_lines'"
			" where name='count'"
				" and exists ("
					"select null"
					" from widget w"
					" where widget_field.widgetid=w.widgetid"
						" and w.type='tophosts'"
				")";

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK <= zbx_db_execute("%s", sql))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_6050025(void)
{
	if (FAIL == zbx_db_index_exists("problem", "problem_4"))
		return DBcreate_index("problem", "problem_4", "cause_eventid", 0);

	return SUCCEED;
}

static int	DBpatch_6050026(void)
{
	const zbx_db_field_t	field = {"id", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBdrop_field_autoincrement("proxy_history", &field);

	return SUCCEED;
}

static int	DBpatch_6050027(void)
{
	const zbx_db_field_t	field = {"id", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBdrop_field_autoincrement("proxy_dhistory", &field);

	return SUCCEED;
}

static int	DBpatch_6050028(void)
{
	const zbx_db_field_t	field = {"id", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBdrop_field_autoincrement("proxy_autoreg_host", &field);

	return SUCCEED;
}

static int	DBpatch_6050029(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("insert into module (moduleid,id,relative_path,status,config) values"
			" (" ZBX_FS_UI64 ",'gauge','widgets/gauge',%d,'[]')", zbx_db_get_maxid("module"), 1))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050030(void)
{
	const zbx_db_table_t table =
			{"optag", "optagid", 0,
				{
					{"optagid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"operationid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int  DBpatch_6050031(void)
{
	return DBcreate_index("optag", "optag_1", "operationid", 0);
}

static int	DBpatch_6050032(void)
{
	const zbx_db_field_t	field = {"operationid", NULL, "operations", "operationid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("optag", 1, &field);
}

static int	DBpatch_6050033(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("insert into module (moduleid,id,relative_path,status,config) values"
			" (" ZBX_FS_UI64 ",'toptriggers','widgets/toptriggers',%d,'[]')", zbx_db_get_maxid("module"), 1))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050034(void)
{
	const zbx_db_table_t	table = {"proxy", "proxyid", 0,
			{
				{"proxyid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"operating_mode", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"description", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0},
				{"tls_connect", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"tls_accept", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"tls_issuer", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"tls_subject", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"tls_psk_identity", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"tls_psk", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"allowed_addresses", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"address", "127.0.0.1", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"port", "10051", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_6050035(void)
{
	return DBcreate_index("proxy", "proxy_1", "name", 1);
}

static int	DBpatch_6050036(void)
{
	return DBcreate_changelog_insert_trigger("proxy", "proxyid");
}

static int	DBpatch_6050037(void)
{
	return DBcreate_changelog_update_trigger("proxy", "proxyid");
}

static int	DBpatch_6050038(void)
{
	return DBcreate_changelog_delete_trigger("proxy", "proxyid");
}

#define DEPRECATED_STATUS_PROXY_ACTIVE	5
#define DEPRECATED_STATUS_PROXY_PASSIVE	6

static int	DBpatch_6050039(void)
{
	zbx_db_row_t		row;
	zbx_db_result_t		result;
	zbx_db_insert_t		db_insert_proxies;
	int			ret;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = zbx_db_select(
			"select h.hostid,h.host,h.status,h.description,h.tls_connect,h.tls_accept,h.tls_issuer,"
				"h.tls_subject,h.tls_psk_identity,h.tls_psk,h.proxy_address,i.useip,i.ip,i.dns,i.port"
			" from hosts h"
			" left join interface i"
				" on h.hostid=i.hostid"
			" where h.status in (%i,%i)",
			DEPRECATED_STATUS_PROXY_PASSIVE, DEPRECATED_STATUS_PROXY_ACTIVE);

	zbx_db_insert_prepare(&db_insert_proxies, "proxy", "proxyid", "name", "operating_mode", "description", "tls_connect",
			"tls_accept", "tls_issuer", "tls_subject", "tls_psk_identity", "tls_psk", "allowed_addresses",
			"address", "port", (char *)NULL);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	proxyid;
		int		status, tls_connect, tls_accept;

		ZBX_STR2UINT64(proxyid, row[0]);
		status = atoi(row[2]);
		tls_connect = atoi(row[4]);
		tls_accept = atoi(row[5]);

		if (DEPRECATED_STATUS_PROXY_ACTIVE == status)
		{
			zbx_db_insert_add_values(&db_insert_proxies, proxyid, row[1], PROXY_OPERATING_MODE_ACTIVE, row[3],
					tls_connect, tls_accept, row[6], row[7], row[8], row[9], row[10],
					"127.0.0.1", "10051");
		}
		else if (DEPRECATED_STATUS_PROXY_PASSIVE == status)
		{
			const char	*address;
			const char	*port;

			if (SUCCEED != zbx_db_is_null(row[11]))
			{
				address = (1 == atoi(row[11]) ? row[12] : row[13]);
				port = row[14];
			}
			else
			{
				address = "127.0.0.1";
				port = "10051";
				zabbix_log(LOG_LEVEL_WARNING, "cannot select interface for proxy '%s'",  row[1]);
			}

			zbx_db_insert_add_values(&db_insert_proxies, proxyid, row[1], PROXY_OPERATING_MODE_PASSIVE, row[3],
					tls_connect, tls_accept, row[6], row[7], row[8], row[9], "", address, port);
		}
	}
	zbx_db_free_result(result);

	ret = zbx_db_insert_execute(&db_insert_proxies);
	zbx_db_insert_clean(&db_insert_proxies);

	return ret;
}

static int	DBpatch_6050040(void)
{
	return DBdrop_foreign_key("hosts", 1);
}

static int	DBpatch_6050041(void)
{
	const zbx_db_field_t	field = {"proxyid", NULL, "hosts", "hostid", 0, ZBX_TYPE_ID, 0, 0};

	return DBrename_field("hosts", "proxy_hostid", &field);
}

static int	DBpatch_6050042(void)
{
	const zbx_db_field_t	field = {"proxyid", NULL, "proxy", "proxyid", 0, 0, 0, 0};

	return DBadd_foreign_key("hosts", 1, &field);
}

static int	DBpatch_6050043(void)
{
	return DBdrop_foreign_key("drules", 1);
}

static int	DBpatch_6050044(void)
{
	const zbx_db_field_t	field = {"proxyid", NULL, "hosts", "hostid", 0, ZBX_TYPE_ID, 0, 0};

	return DBrename_field("drules", "proxy_hostid", &field);
}

static int	DBpatch_6050045(void)
{
	const zbx_db_field_t	field = {"proxyid", NULL, "proxy", "proxyid", 0, 0, 0, 0};

	return DBadd_foreign_key("drules", 1, &field);
}

static int	DBpatch_6050046(void)
{
	return DBdrop_foreign_key("autoreg_host", 1);
}

static int	DBpatch_6050047(void)
{
	const zbx_db_field_t	field = {"proxyid", NULL, "hosts", "hostid", 0, ZBX_TYPE_ID, 0, ZBX_FK_CASCADE_DELETE};

	return DBrename_field("autoreg_host", "proxy_hostid", &field);
}

static int	DBpatch_6050048(void)
{
	const zbx_db_field_t	field = {"proxyid", NULL, "proxy", "proxyid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("autoreg_host", 1, &field);
}

static int	DBpatch_6050049(void)
{
	return DBdrop_foreign_key("task", 1);
}

static int	DBpatch_6050050(void)
{
	const zbx_db_field_t	field = {"proxyid", NULL, "hosts", "hostid", 0, ZBX_TYPE_ID, 0, 0};

	return DBrename_field("task", "proxy_hostid", &field);
}

static int	DBpatch_6050051(void)
{
	const zbx_db_field_t	field = {"proxyid", NULL, "proxy", "proxyid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("task", 1, &field);
}

static int	DBpatch_6050052(void)
{
	const zbx_db_table_t	table = {"proxy_rtdata", "proxyid", 0,
			{
				{"proxyid", NULL, "proxy", "proxyid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"lastaccess", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"version", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"compatibility", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_6050053(void)
{
	const zbx_db_field_t	field = {"proxyid", NULL, "proxy", "proxyid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("proxy_rtdata", 1, &field);
}

static int	DBpatch_6050054(void)
{
	zbx_db_row_t		row;
	zbx_db_result_t		result;
	zbx_db_insert_t		db_insert_rtdata;
	int			ret;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = zbx_db_select(
		"select hr.hostid,hr.lastaccess,hr.version,hr.compatibility"
		" from host_rtdata hr"
		" join hosts h"
			" on hr.hostid=h.hostid"
		" where h.status in (%i,%i)",
		DEPRECATED_STATUS_PROXY_ACTIVE, DEPRECATED_STATUS_PROXY_PASSIVE);

	zbx_db_insert_prepare(&db_insert_rtdata, "proxy_rtdata", "proxyid", "lastaccess", "version", "compatibility",
			(char *)NULL);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		int		lastaccess, version, compatibility;
		zbx_uint64_t	hostid;

		ZBX_STR2UINT64(hostid, row[0]);
		lastaccess = atoi(row[1]);
		version = atoi(row[2]);
		compatibility = atoi(row[3]);

		zbx_db_insert_add_values(&db_insert_rtdata, hostid, lastaccess, version, compatibility);
	}
	zbx_db_free_result(result);

	ret = zbx_db_insert_execute(&db_insert_rtdata);
	zbx_db_insert_clean(&db_insert_rtdata);

	return ret;
}

#undef DEPRECATED_STATUS_PROXY_ACTIVE
#undef DEPRECATED_STATUS_PROXY_PASSIVE

static int	DBpatch_6050055(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from hosts where status in (5,6)"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050056(void)
{
	return DBdrop_field("host_rtdata", "lastaccess");
}

static int	DBpatch_6050057(void)
{
	return DBdrop_field("host_rtdata", "version");
}

static int	DBpatch_6050058(void)
{
	return DBdrop_field("host_rtdata", "compatibility");
}

static int	DBpatch_6050059(void)
{
	return DBdrop_field("hosts", "proxy_address");
}

static int	DBpatch_6050060(void)
{
	return DBdrop_field("hosts", "auto_compress");
}

static int	DBpatch_6050061(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from profiles where idx='web.proxies.filter_status'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050062(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update profiles"
			" set value_str='name'"
			" where value_str like 'host'"
				" and idx='web.proxies.php.sort'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050063(void)
{
#define TM_DATA_TYPE_TEST_ITEM	0
#define TM_DATA_TYPE_PROXYIDS	2

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete"
			" from task"
			" where exists ("
				"select null"
				" from task_data td"
				" where td.taskid=task.taskid and td.type in (%i,%i)"
			")",
			TM_DATA_TYPE_TEST_ITEM, TM_DATA_TYPE_PROXYIDS))
	{
		return FAIL;
	}
#undef TM_DATA_TYPE_TEST_ITEM
#undef TM_DATA_TYPE_PROXYIDS

	return SUCCEED;
}

static int	DBpatch_6050064(void)
{
	if (FAIL == zbx_db_index_exists("dashboard_user", "dashboard_user_2"))
		return DBcreate_index("dashboard_user", "dashboard_user_2", "userid", 0);

	return SUCCEED;
}

static int	DBpatch_6050065(void)
{
	if (FAIL == zbx_db_index_exists("dashboard_usrgrp", "dashboard_usrgrp_2"))
		return DBcreate_index("dashboard_usrgrp", "dashboard_usrgrp_2", "usrgrpid", 0);

	return SUCCEED;
}

static int	DBpatch_6050066(void)
{
	if (FAIL == zbx_db_index_exists("event_suppress", "event_suppress_4"))
		return DBcreate_index("event_suppress", "event_suppress_4", "userid", 0);

	return SUCCEED;
}

static int	DBpatch_6050067(void)
{
	if (FAIL == zbx_db_index_exists("group_discovery", "group_discovery_1"))
		return DBcreate_index("group_discovery", "group_discovery_1", "parent_group_prototypeid", 0);

	return SUCCEED;
}

static int	DBpatch_6050068(void)
{
	if (FAIL == zbx_db_index_exists("group_prototype", "group_prototype_2"))
		return DBcreate_index("group_prototype", "group_prototype_2", "groupid", 0);

	return SUCCEED;
}

static int	DBpatch_6050069(void)
{
	if (FAIL == zbx_db_index_exists("group_prototype", "group_prototype_3"))
		return DBcreate_index("group_prototype", "group_prototype_3", "templateid", 0);

	return SUCCEED;
}

static int	DBpatch_6050070(void)
{
	if (FAIL == zbx_db_index_exists("host_discovery", "host_discovery_1"))
		return DBcreate_index("host_discovery", "host_discovery_1", "parent_hostid", 0);

	return SUCCEED;
}

static int	DBpatch_6050071(void)
{
	if (FAIL == zbx_db_index_exists("host_discovery", "host_discovery_2"))
		return DBcreate_index("host_discovery", "host_discovery_2", "parent_itemid", 0);

	return SUCCEED;
}

static int	DBpatch_6050072(void)
{
	if (FAIL == zbx_db_index_exists("hosts", "hosts_7"))
		return DBcreate_index("hosts", "hosts_7", "templateid", 0);

	return SUCCEED;
}

static int	DBpatch_6050073(void)
{
	if (FAIL == zbx_db_index_exists("interface_discovery", "interface_discovery_1"))
		return DBcreate_index("interface_discovery", "interface_discovery_1", "parent_interfaceid", 0);

	return SUCCEED;
}

static int	DBpatch_6050074(void)
{
	if (FAIL == zbx_db_index_exists("report", "report_2"))
		return DBcreate_index("report", "report_2", "userid", 0);

	return SUCCEED;
}

static int	DBpatch_6050075(void)
{
	if (FAIL == zbx_db_index_exists("report", "report_3"))
		return DBcreate_index("report", "report_3", "dashboardid", 0);

	return SUCCEED;
}

static int	DBpatch_6050076(void)
{
	if (FAIL == zbx_db_index_exists("report_user", "report_user_2"))
		return DBcreate_index("report_user", "report_user_2", "userid", 0);

	return SUCCEED;
}

static int	DBpatch_6050077(void)
{
	if (FAIL == zbx_db_index_exists("report_user", "report_user_3"))
		return DBcreate_index("report_user", "report_user_3", "access_userid", 0);

	return SUCCEED;
}

static int	DBpatch_6050078(void)
{
	if (FAIL == zbx_db_index_exists("report_usrgrp", "report_usrgrp_2"))
		return DBcreate_index("report_usrgrp", "report_usrgrp_2", "usrgrpid", 0);

	return SUCCEED;
}

static int	DBpatch_6050079(void)
{
	if (FAIL == zbx_db_index_exists("report_usrgrp", "report_usrgrp_3"))
		return DBcreate_index("report_usrgrp", "report_usrgrp_3", "access_userid", 0);

	return SUCCEED;
}

static int	DBpatch_6050080(void)
{
	if (FAIL == zbx_db_index_exists("sysmaps", "sysmaps_4"))
		return DBcreate_index("sysmaps", "sysmaps_4", "userid", 0);

	return SUCCEED;
}

static int	DBpatch_6050081(void)
{
	if (FAIL == zbx_db_index_exists("sysmap_element_trigger", "sysmap_element_trigger_2"))
		return DBcreate_index("sysmap_element_trigger", "sysmap_element_trigger_2", "triggerid", 0);

	return SUCCEED;
}

static int	DBpatch_6050082(void)
{
	if (FAIL == zbx_db_index_exists("sysmap_user", "sysmap_user_2"))
		return DBcreate_index("sysmap_user", "sysmap_user_2", "userid", 0);

	return SUCCEED;
}

static int	DBpatch_6050083(void)
{
	if (FAIL == zbx_db_index_exists("sysmap_usrgrp", "sysmap_usrgrp_2"))
		return DBcreate_index("sysmap_usrgrp", "sysmap_usrgrp_2", "usrgrpid", 0);

	return SUCCEED;
}

static int	DBpatch_6050084(void)
{
	if (FAIL == zbx_db_index_exists("tag_filter", "tag_filter_1"))
		return DBcreate_index("tag_filter", "tag_filter_1", "usrgrpid", 0);

	return SUCCEED;
}

static int	DBpatch_6050085(void)
{
	if (FAIL == zbx_db_index_exists("tag_filter", "tag_filter_2"))
		return DBcreate_index("tag_filter", "tag_filter_2", "groupid", 0);

	return SUCCEED;
}

static int	DBpatch_6050086(void)
{
	if (FAIL == zbx_db_index_exists("task", "task_2"))
		return DBcreate_index("task", "task_2", "proxyid", 0);

	return SUCCEED;
}

static int	DBpatch_6050087(void)
{
	if (FAIL == zbx_db_index_exists("users", "users_3"))
		return DBcreate_index("users", "users_3", "roleid", 0);

	return SUCCEED;
}

static int	DBpatch_6050090(void)
{
	const zbx_db_field_t	old_field = {"info", "", NULL, NULL, 0, ZBX_TYPE_SHORTTEXT, ZBX_NOTNULL, 0};
	const zbx_db_field_t	field = {"info", "", NULL, NULL, 0, ZBX_TYPE_LONGTEXT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("task_remote_command_result", &field, &old_field);
}

static int	DBpatch_6050091(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update widget_field"
			" set value_str=' '"
			" where name like 'columns.name.%%'"
			" and value_str like ''"
			" and widgetid in ("
				"select widgetid"
				" from widget"
				" where type='tophosts'"
			")"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050092(void)
{
	return DBrename_table("group_discovery", "group_discovery_tmp");
}

static int	DBpatch_6050093(void)
{
	const zbx_db_table_t	table =
			{"group_discovery", "groupdiscoveryid", 0,
				{
					{"groupdiscoveryid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"parent_group_prototypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"lastcheck", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"ts_delete", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050094(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("insert into group_discovery "
				"(groupdiscoveryid,groupid,parent_group_prototypeid,name,lastcheck,ts_delete)"
			" select groupid,groupid,parent_group_prototypeid,name,lastcheck,ts_delete"
				" from group_discovery_tmp"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050095(void)
{
	return DBdrop_table("group_discovery_tmp");
}

static int	DBpatch_6050096(void)
{
	return DBcreate_index("group_discovery", "group_discovery_1", "groupid,parent_group_prototypeid", 1);
}

static int	DBpatch_6050097(void)
{
	return DBcreate_index("group_discovery", "group_discovery_2", "parent_group_prototypeid", 0);
}

static int	DBpatch_6050098(void)
{
	const zbx_db_field_t	field = {"groupid", NULL, "hstgrp", "groupid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("group_discovery", 1, &field);
}

static int	DBpatch_6050099(void)
{
	const zbx_db_field_t	field = {"parent_group_prototypeid", NULL, "group_prototype", "group_prototypeid", 0, 0,
			0, 0};

	return DBadd_foreign_key("group_discovery", 2, &field);
}

static int	DBpatch_6050100(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("insert into module (moduleid,id,relative_path,status,config) values"
			" (" ZBX_FS_UI64 ",'piechart','widgets/piechart',%d,'[]')", zbx_db_get_maxid("module"), 1))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050101(void)
{
	const zbx_db_field_t	field = {"timeout_zabbix_agent", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR,
			ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050102(void)
{
	const zbx_db_field_t	field = {"timeout_simple_check", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR,
			ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050103(void)
{
	const zbx_db_field_t	field = {"timeout_snmp_agent", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR,
			ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050104(void)
{
	const zbx_db_field_t	field = {"timeout_external_check", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR,
			ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050105(void)
{
	const zbx_db_field_t	field = {"timeout_db_monitor", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR,
			ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050106(void)
{
	const zbx_db_field_t	field = {"timeout_http_agent", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR,
			ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050107(void)
{
	const zbx_db_field_t	field = {"timeout_ssh_agent", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR,
			ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050108(void)
{
	const zbx_db_field_t	field = {"timeout_telnet_agent", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR,
			ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050109(void)
{
	const zbx_db_field_t	field = {"timeout_script", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR,
			ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050110(void)
{
	int	timeout;

	timeout = DBget_config_timeout();

	if (ZBX_DB_OK > zbx_db_execute("update config"
			" set timeout_zabbix_agent='%ds',"
				"timeout_simple_check='%ds',"
				"timeout_snmp_agent='%ds',"
				"timeout_external_check='%ds',"
				"timeout_db_monitor='%ds',"
				"timeout_http_agent='%ds',"
				"timeout_ssh_agent='%ds',"
				"timeout_telnet_agent='%ds',"
				"timeout_script='%ds'",
			timeout, timeout, timeout, timeout, timeout, timeout, timeout, timeout, timeout))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050111(void)
{
	if (ZBX_DB_OK > zbx_db_execute("update items set timeout='' where type not in (%d,%d)", ITEM_TYPE_HTTPAGENT,
			ITEM_TYPE_SCRIPT))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050112(void)
{
	const zbx_db_field_t	field = {"timeout", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBset_default("items", &field);
}

static int	DBpatch_6050113(void)
{
	const zbx_db_field_t	field = {"custom_timeouts", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050114(void)
{
	const zbx_db_field_t	field = {"timeout_zabbix_agent", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050115(void)
{
	const zbx_db_field_t	field = {"timeout_simple_check", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050116(void)
{
	const zbx_db_field_t	field = {"timeout_snmp_agent", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050117(void)
{
	const zbx_db_field_t	field = {"timeout_external_check", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050118(void)
{
	const zbx_db_field_t	field = {"timeout_db_monitor", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050119(void)
{
	const zbx_db_field_t	field = {"timeout_http_agent", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050120(void)
{
	const zbx_db_field_t	field = {"timeout_ssh_agent", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050121(void)
{
	const zbx_db_field_t	field = {"timeout_telnet_agent", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050122(void)
{
	const zbx_db_field_t	field = {"timeout_script", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050123(void)
{
	if (ZBX_DB_OK > zbx_db_execute("update item_preproc set params='-1' where type=26"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050124(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"delete from widget_field"
			" where name in ('source_type','reference')"
				" and widgetid in (select widgetid from widget where type='map')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050125(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update widget_field"
			" set name='sysmapid._reference',value_str=CONCAT(value_str,'._mapid')"
			" where name='filter_widget_reference'"
				" and widgetid in (select widgetid from widget where type='map')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050126(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update widget_field"
			" set type='1',name='override_hostid._reference',value_int=0,value_str='DASHBOARD._hostid'"
			" where type=0"
				" and name='dynamic'"
				" and value_int=1"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050127(void)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	zbx_db_insert_t	db_insert;
	int		ret;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = zbx_db_select(
			"select w.widgetid,wf_from.value_str,wf_to.value_str"
			" from widget w"
			" left join widget_field wf_from"
				" on w.widgetid=wf_from.widgetid"
					" and (wf_from.name='time_from' or wf_from.name is null)"
			" left join widget_field wf_to"
				" on w.widgetid=wf_to.widgetid"
					" and (wf_to.name='time_to' or wf_to.name is null)"
			" where w.type='svggraph' and exists ("
				"select null"
				" from widget_field wf2"
				" where wf2.widgetid=w.widgetid"
					" and wf2.name='graph_time'"
			")");

	zbx_db_insert_prepare(&db_insert, "widget_field", "widget_fieldid", "widgetid", "type", "name", "value_str",
			NULL);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	widgetid;

		ZBX_STR2UINT64(widgetid, row[0]);

		if (SUCCEED == zbx_db_is_null(row[1]))
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, 1, "time_period.from", "now-1h");

		if (SUCCEED == zbx_db_is_null(row[2]))
			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, 1, "time_period.to", "now");
	}
	zbx_db_free_result(result);

	zbx_db_insert_autoincrement(&db_insert, "widget_fieldid");

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_6050128(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update widget_field"
			" set name='time_period.from'"
			" where name='time_from'"
				" and widgetid in (select widgetid from widget where type='svggraph')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050129(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update widget_field"
			" set name='time_period.to'"
			" where name='time_to'"
				" and widgetid in (select widgetid from widget where type='svggraph')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050130(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"delete from widget_field"
			" where name='graph_time'"
				" and widgetid in (select widgetid from widget where type='svggraph')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050131(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update widget_field"
			" set name='date_period.from'"
			" where name='date_from'"
				" and widgetid in (select widgetid from widget where type='slareport')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050132(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update widget_field"
			" set name='date_period.to'"
			" where name='date_to'"
				" and widgetid in (select widgetid from widget where type='slareport')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050133(void)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	zbx_regexp_t	*regex1 = NULL, *regex2 = NULL;
	char		*error = NULL, *replace_to = NULL, *sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	int		ret = FAIL;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (FAIL == zbx_regexp_compile_ext("^([a-z]+)\\.([a-z_]+)\\.(\\d+)\\.(\\d+)$", &regex1, 0, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "internal error, invalid regular expression: %s", error);
		goto out;
	}

	if (FAIL == zbx_regexp_compile_ext("^([a-z]+)\\.([a-z_]+)\\.(\\d+)$", &regex2, 0, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "internal error, invalid regular expression: %s", error);
		goto out;
	}

	zbx_db_begin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = zbx_db_select("select widget_fieldid,name from widget_field where name like '%%.%%.%%'");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	widget_fieldid;
		char		*replace_from;

		ZBX_STR2UINT64(widget_fieldid, row[0]);
		replace_from = row[1];

		if (SUCCEED != zbx_mregexp_sub_precompiled(
						replace_from,
						regex1,
						"\\1.\\3.\\2.\\4",
						0,	/* no output limit */
						&replace_to)
				&& SUCCEED != zbx_mregexp_sub_precompiled(
						replace_from,
						regex2,
						"\\1.\\3.\\2",
						0,	/* no output limit */
						&replace_to))
		{
			continue;
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update widget_field"
				" set name='%s'"
				" where widget_fieldid=" ZBX_FS_UI64 ";\n",
				replace_to, widget_fieldid);

		zbx_free(replace_to);

		if (SUCCEED != zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
		{
			zabbix_log(LOG_LEVEL_CRIT, "internal error, cannot execute multiple SQL \"update\" operations");
			zbx_db_free_result(result);

			goto out;
		}
	}
	zbx_db_free_result(result);

	zbx_db_end_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
		zbx_db_execute("%s", sql);

	ret = SUCCEED;
out:
	if (NULL != regex1)
		zbx_regexp_free(regex1);
	if (NULL != regex2)
		zbx_regexp_free(regex2);

	zbx_free(sql);
	zbx_free(error);
	zbx_free(replace_to);

	return ret;
}

#define REFERENCE_LEN	5
#define FIRST_LETTER	'A'
#define TOTAL_LETTERS	26

static char	*create_widget_reference(const zbx_vector_str_t *references)
{
	static char	buf[REFERENCE_LEN + 1];
	static int	next_index;

	while (1)
	{
		int	i, index = next_index++;

		for (i = REFERENCE_LEN - 1; i >= 0; i--)
		{
			buf[i] = FIRST_LETTER + index % TOTAL_LETTERS;
			index /= TOTAL_LETTERS;
		}

		if (FAIL == zbx_vector_str_search(references, buf, ZBX_DEFAULT_STR_COMPARE_FUNC))
			return buf;
	}
}

#undef TOTAL_LETTERS
#undef FIRST_LETTER
#undef REFERENCE_LEN

static int	DBpatch_6050134(void)
{
	zbx_db_row_t		row;
	zbx_db_result_t		result;
	zbx_db_insert_t		db_insert;
	zbx_vector_str_t	references;
	int			ret;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_vector_str_create(&references);

	result = zbx_db_select("select distinct value_str from widget_field where name='reference' order by value_str");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_vector_str_append(&references, zbx_strdup(NULL, row[0]));
	}
	zbx_db_free_result(result);

	zbx_vector_str_sort(&references, ZBX_DEFAULT_STR_COMPARE_FUNC);

	zbx_db_insert_prepare(&db_insert, "widget_field", "widget_fieldid", "widgetid", "type", "name", "value_str",
			NULL);

	result = zbx_db_select("select widgetid from widget where type in ('graph','svggraph','graphprototype')");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	widgetid;

		ZBX_STR2UINT64(widgetid, row[0]);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, 1, "reference",
				create_widget_reference(&references));
	}
	zbx_db_free_result(result);

	zbx_vector_str_clear_ext(&references, zbx_str_free);
	zbx_vector_str_destroy(&references);

	zbx_db_insert_autoincrement(&db_insert, "widget_fieldid");

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_6050135(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from profiles where idx like 'web.templates.triggers.%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050136(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from profiles where idx like 'web.templates.trigger_prototypes.php.%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050137(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from profiles where idx like 'web.hosts.triggers.%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050138(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from profiles where idx like 'web.hosts.trigger_prototypes.php.%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050139(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_db_insert_t	db_insert;
	int		ret = SUCCEED;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = zbx_db_select("select wf.widgetid from widget_field wf,widget w"
			" where wf.name='interface_type' and w.type='hostavail' and w.widgetid=wf.widgetid"
			" group by wf.widgetid having count(wf.name)=1");

	zbx_db_insert_prepare(&db_insert, "widget_field", "widget_fieldid", "widgetid", "name", "type", "value_int",
			NULL);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	widgetid;

		ZBX_STR2UINT64(widgetid, row[0]);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, "only_totals", 0, 1);
	}
	zbx_db_free_result(result);

	zbx_db_insert_autoincrement(&db_insert, "widget_fieldid");

	ret = zbx_db_insert_execute(&db_insert);

	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_6050140(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from profiles where idx like 'web.templates.items.%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050141(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from profiles where idx like 'web.templates.disc_prototypes.php.%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050142(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from profiles where idx like 'web.hosts.items.%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050143(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("delete from profiles where idx like 'web.hosts.disc_prototypes.php.%%'"))
		return FAIL;

	return SUCCEED;
}

#endif

DBPATCH_START(6050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(6050000, 0, 1)
DBPATCH_ADD(6050001, 0, 1)
DBPATCH_ADD(6050002, 0, 1)
DBPATCH_ADD(6050003, 0, 1)
DBPATCH_ADD(6050004, 0, 1)
DBPATCH_ADD(6050005, 0, 1)
DBPATCH_ADD(6050006, 0, 1)
DBPATCH_ADD(6050007, 0, 1)
DBPATCH_ADD(6050008, 0, 1)
DBPATCH_ADD(6050009, 0, 1)
DBPATCH_ADD(6050010, 0, 1)
DBPATCH_ADD(6050011, 0, 1)
DBPATCH_ADD(6050012, 0, 1)
DBPATCH_ADD(6050013, 0, 1)
DBPATCH_ADD(6050014, 0, 1)
DBPATCH_ADD(6050015, 0, 1)
DBPATCH_ADD(6050016, 0, 1)
DBPATCH_ADD(6050017, 0, 1)
DBPATCH_ADD(6050018, 0, 1)
DBPATCH_ADD(6050019, 0, 1)
DBPATCH_ADD(6050020, 0, 1)
DBPATCH_ADD(6050021, 0, 1)
DBPATCH_ADD(6050022, 0, 1)
DBPATCH_ADD(6050023, 0, 1)
DBPATCH_ADD(6050024, 0, 1)
DBPATCH_ADD(6050025, 0, 1)
DBPATCH_ADD(6050026, 0, 1)
DBPATCH_ADD(6050027, 0, 1)
DBPATCH_ADD(6050028, 0, 1)
DBPATCH_ADD(6050029, 0, 1)
DBPATCH_ADD(6050030, 0, 1)
DBPATCH_ADD(6050031, 0, 1)
DBPATCH_ADD(6050032, 0, 1)
DBPATCH_ADD(6050033, 0, 1)
DBPATCH_ADD(6050034, 0, 1)
DBPATCH_ADD(6050035, 0, 1)
DBPATCH_ADD(6050036, 0, 1)
DBPATCH_ADD(6050037, 0, 1)
DBPATCH_ADD(6050038, 0, 1)
DBPATCH_ADD(6050039, 0, 1)
DBPATCH_ADD(6050040, 0, 1)
DBPATCH_ADD(6050041, 0, 1)
DBPATCH_ADD(6050042, 0, 1)
DBPATCH_ADD(6050043, 0, 1)
DBPATCH_ADD(6050044, 0, 1)
DBPATCH_ADD(6050045, 0, 1)
DBPATCH_ADD(6050046, 0, 1)
DBPATCH_ADD(6050047, 0, 1)
DBPATCH_ADD(6050048, 0, 1)
DBPATCH_ADD(6050049, 0, 1)
DBPATCH_ADD(6050050, 0, 1)
DBPATCH_ADD(6050051, 0, 1)
DBPATCH_ADD(6050052, 0, 1)
DBPATCH_ADD(6050053, 0, 1)
DBPATCH_ADD(6050054, 0, 1)
DBPATCH_ADD(6050055, 0, 1)
DBPATCH_ADD(6050056, 0, 1)
DBPATCH_ADD(6050057, 0, 1)
DBPATCH_ADD(6050058, 0, 1)
DBPATCH_ADD(6050059, 0, 1)
DBPATCH_ADD(6050060, 0, 1)
DBPATCH_ADD(6050061, 0, 1)
DBPATCH_ADD(6050062, 0, 1)
DBPATCH_ADD(6050063, 0, 1)
DBPATCH_ADD(6050064, 0, 1)
DBPATCH_ADD(6050065, 0, 1)
DBPATCH_ADD(6050066, 0, 1)
DBPATCH_ADD(6050067, 0, 1)
DBPATCH_ADD(6050068, 0, 1)
DBPATCH_ADD(6050069, 0, 1)
DBPATCH_ADD(6050070, 0, 1)
DBPATCH_ADD(6050071, 0, 1)
DBPATCH_ADD(6050072, 0, 1)
DBPATCH_ADD(6050073, 0, 1)
DBPATCH_ADD(6050074, 0, 1)
DBPATCH_ADD(6050075, 0, 1)
DBPATCH_ADD(6050076, 0, 1)
DBPATCH_ADD(6050077, 0, 1)
DBPATCH_ADD(6050078, 0, 1)
DBPATCH_ADD(6050079, 0, 1)
DBPATCH_ADD(6050080, 0, 1)
DBPATCH_ADD(6050081, 0, 1)
DBPATCH_ADD(6050082, 0, 1)
DBPATCH_ADD(6050083, 0, 1)
DBPATCH_ADD(6050084, 0, 1)
DBPATCH_ADD(6050085, 0, 1)
DBPATCH_ADD(6050086, 0, 1)
DBPATCH_ADD(6050087, 0, 1)
DBPATCH_ADD(6050090, 0, 1)
DBPATCH_ADD(6050091, 0, 1)
DBPATCH_ADD(6050092, 0, 1)
DBPATCH_ADD(6050093, 0, 1)
DBPATCH_ADD(6050094, 0, 1)
DBPATCH_ADD(6050095, 0, 1)
DBPATCH_ADD(6050096, 0, 1)
DBPATCH_ADD(6050097, 0, 1)
DBPATCH_ADD(6050098, 0, 1)
DBPATCH_ADD(6050099, 0, 1)
DBPATCH_ADD(6050100, 0, 1)
DBPATCH_ADD(6050101, 0, 1)
DBPATCH_ADD(6050102, 0, 1)
DBPATCH_ADD(6050103, 0, 1)
DBPATCH_ADD(6050104, 0, 1)
DBPATCH_ADD(6050105, 0, 1)
DBPATCH_ADD(6050106, 0, 1)
DBPATCH_ADD(6050107, 0, 1)
DBPATCH_ADD(6050108, 0, 1)
DBPATCH_ADD(6050109, 0, 1)
DBPATCH_ADD(6050110, 0, 1)
DBPATCH_ADD(6050111, 0, 1)
DBPATCH_ADD(6050112, 0, 1)
DBPATCH_ADD(6050113, 0, 1)
DBPATCH_ADD(6050114, 0, 1)
DBPATCH_ADD(6050115, 0, 1)
DBPATCH_ADD(6050116, 0, 1)
DBPATCH_ADD(6050117, 0, 1)
DBPATCH_ADD(6050118, 0, 1)
DBPATCH_ADD(6050119, 0, 1)
DBPATCH_ADD(6050120, 0, 1)
DBPATCH_ADD(6050121, 0, 1)
DBPATCH_ADD(6050122, 0, 1)
DBPATCH_ADD(6050123, 0, 1)
DBPATCH_ADD(6050124, 0, 1)
DBPATCH_ADD(6050125, 0, 1)
DBPATCH_ADD(6050126, 0, 1)
DBPATCH_ADD(6050127, 0, 1)
DBPATCH_ADD(6050128, 0, 1)
DBPATCH_ADD(6050129, 0, 1)
DBPATCH_ADD(6050130, 0, 1)
DBPATCH_ADD(6050131, 0, 1)
DBPATCH_ADD(6050132, 0, 1)
DBPATCH_ADD(6050133, 0, 1)
DBPATCH_ADD(6050134, 0, 1)
DBPATCH_ADD(6050135, 0, 1)
DBPATCH_ADD(6050136, 0, 1)
DBPATCH_ADD(6050137, 0, 1)
DBPATCH_ADD(6050138, 0, 1)
DBPATCH_ADD(6050139, 0, 1)
DBPATCH_ADD(6050140, 0, 1)
DBPATCH_ADD(6050141, 0, 1)
DBPATCH_ADD(6050142, 0, 1)
DBPATCH_ADD(6050143, 0, 1)

DBPATCH_END()
