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

#include "zbxdbschema.h"
#include "zbxvariant.h"
#include "zbxexpr.h"
#include "zbxeval.h"
#include "zbxalgo.h"
#include "zbxtypes.h"
#include "zbxregexp.h"
#include "zbx_host_constants.h"
#include "zbxstr.h"
#include "zbxhash.h"
#include "zbxcrypto.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxnum.h"

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

#if defined(HAVE_POSTGRESQL)
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

#if defined(HAVE_POSTGRESQL)
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

#if defined(HAVE_POSTGRESQL)
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

#if defined(HAVE_POSTGRESQL)
	if (SUCCEED == DBcheck_field_type("trends", &field))
		return SUCCEED;
#endif

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
					{0}
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
	const zbx_db_field_t	field = {"http_user", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("httptest", &field, NULL);
}

static int	DBpatch_6050016(void)
{
	const zbx_db_field_t	field = {"http_password", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("httptest", &field, NULL);
}

static int	DBpatch_6050017(void)
{
	const zbx_db_field_t	field = {"username", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field, NULL);
}

static int	DBpatch_6050018(void)
{
	const zbx_db_field_t	field = {"password", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

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
}

static int	DBpatch_6050027(void)
{
	const zbx_db_field_t	field = {"id", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBdrop_field_autoincrement("proxy_dhistory", &field);
}

static int	DBpatch_6050028(void)
{
	const zbx_db_field_t	field = {"id", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBdrop_field_autoincrement("proxy_autoreg_host", &field);
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
	zbx_uint64_t	moduleid;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	moduleid = zbx_db_get_maxid("module");

	if (ZBX_DB_OK > zbx_db_execute("insert into module (moduleid,id,relative_path,status,config) values"
			" (" ZBX_FS_UI64 ",'toptriggers','widgets/toptriggers',%d,'[]')", moduleid, 1))
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
				{"description", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
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

	zbx_db_insert_prepare(&db_insert_proxies, "proxy", "proxyid", "name", "operating_mode", "description",
			"tls_connect", "tls_accept", "tls_issuer", "tls_subject", "tls_psk_identity", "tls_psk",
			"allowed_addresses", "address", "port", (char *)NULL);

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
			zbx_db_insert_add_values(&db_insert_proxies, proxyid, row[1], PROXY_OPERATING_MODE_ACTIVE,
					row[3], tls_connect, tls_accept, row[6], row[7], row[8], row[9], row[10],
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

			zbx_db_insert_add_values(&db_insert_proxies, proxyid, row[1], PROXY_OPERATING_MODE_PASSIVE,
					row[3], tls_connect, tls_accept, row[6], row[7], row[8], row[9], "", address,
					port);
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
	const zbx_db_field_t	old_field = {"info", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};
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
	const zbx_db_field_t	field = {"timeout_zabbix_agent", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050102(void)
{
	const zbx_db_field_t	field = {"timeout_simple_check", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050103(void)
{
	const zbx_db_field_t	field = {"timeout_snmp_agent", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050104(void)
{
	const zbx_db_field_t	field = {"timeout_external_check", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR,
			ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050105(void)
{
	const zbx_db_field_t	field = {"timeout_db_monitor", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050106(void)
{
	const zbx_db_field_t	field = {"timeout_http_agent", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050107(void)
{
	const zbx_db_field_t	field = {"timeout_ssh_agent", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050108(void)
{
	const zbx_db_field_t	field = {"timeout_telnet_agent", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050109(void)
{
	const zbx_db_field_t	field = {"timeout_script", "3s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

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
	const zbx_db_field_t	field = {"timeout", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

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

	if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
		goto out;

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

static int	add_widget_references(const char *widget_type_list)
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

	result = zbx_db_select("select widgetid from widget where type in (%s)", widget_type_list);

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

static int	DBpatch_6050134(void)
{
	return add_widget_references("'graph','svggraph','graphprototype'");
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

static int	DBpatch_6050144(void)
{
	const zbx_db_field_t	field = {"hk_audit", "31d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_6050145(void)
{
	const zbx_db_field_t	field = {"hk_history", "31d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_6050146(void)
{
	const zbx_db_field_t	old_field = {"query_fields", "", NULL, NULL, 2048, ZBX_TYPE_CHAR,
			ZBX_NOTNULL | ZBX_PROXY, 0};
	const zbx_db_field_t	field = {"query_fields", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBmodify_field_type("items", &field, &old_field);
}

static int	DBpatch_6050147(void)
{
	const zbx_db_field_t	field = {"item_value_type", "31", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("connector", &field);
}

static int	DBpatch_6050148(void)
{
	const zbx_db_field_t	field = {"attempt_interval", "5s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("connector", &field);
}

static int	DBpatch_6050149(void)
{
/* -------------------------------------------------------*/
/* Formula:                                               */
/* aggregate_function(last_foreach(filter))               */
/* aggregate_function(last_foreach(filter,time))          */
/*--------------------------------------------------------*/
/* Relative positioning of tokens on a stack              */
/*----------------------------+---------------------------*/
/* Time is present in formula | Time is absent in formula */
/*----------------------------+---------------------------*/
/* [i-2] filter               |                           */
/* [i-1] time                 | [i-1] filter              */
/*   [i] last_foreach         |   [i] last_foreach        */
/* [i+2] aggregate function   | [i+2]                     */
/*----------------------------+---------------------------*/

/* Offset in stack of tokens is relative to last_foreach() history function token, */
/* assuming that time is present in formula. */
#define OFFSET_TIME	(-1)
#define TOKEN_LEN(loc)	(loc->r - loc->l + 1)
#define LAST_FOREACH	"last_foreach"
	zbx_db_row_t		row;
	zbx_db_result_t		result;
	int			ret = SUCCEED;
	size_t			sql_alloc = 0, sql_offset = 0;
	char			*sql = NULL, *params = NULL;
	zbx_eval_context_t	ctx;
	zbx_vector_uint32_t	del_idx;

	zbx_eval_init(&ctx);
	zbx_vector_uint32_create(&del_idx);

	/* ITEM_TYPE_CALCULATED = 15 */
	result = zbx_db_select("select itemid,params from items where type=15 and params like '%%%s%%'", LAST_FOREACH);

	while (SUCCEED == ret && NULL != (row = zbx_db_fetch(result)))
	{
		int	i;
		char	*esc, *error = NULL;

		zbx_eval_clear(&ctx);

		if (FAIL == zbx_eval_parse_expression(&ctx, row[1], ZBX_EVAL_PARSE_CALC_EXPRESSION, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s: error parsing calculated item formula '%s' for itemid %s",
					__func__, row[1], row[0]);
			zbx_free(error);
			continue;
		}

		zbx_vector_uint32_clear(&del_idx);

		for (i = 0; i < ctx.stack.values_num; i++)
		{
			zbx_strloc_t	*loc;

			if (ZBX_EVAL_TOKEN_HIST_FUNCTION != ctx.stack.values[i].type)
				continue;

			loc = &ctx.stack.values[i].loc;

			if (0 != strncmp(LAST_FOREACH, &ctx.expression[loc->l], TOKEN_LEN(loc)))
				continue;

			/* if time is absent in formula */
			if (ZBX_EVAL_TOKEN_ARG_QUERY == ctx.stack.values[i + OFFSET_TIME].type)
				continue;

			if (ZBX_EVAL_TOKEN_ARG_NULL == ctx.stack.values[i + OFFSET_TIME].type)
				continue;

			zbx_vector_uint32_append(&del_idx, (zbx_uint32_t)(i + OFFSET_TIME));
		}

		if (0 == del_idx.values_num)
			continue;

		params = zbx_strdup(params, ctx.expression);

		for (i = del_idx.values_num - 1; i >= 0; i--)
		{
			size_t		l, r;
			zbx_strloc_t	*loc = &ctx.stack.values[(int)del_idx.values[i]].loc;

			for (l = loc->l - 1; ',' != params[l]; l--) {}
			for (r = loc->r + 1; ')' != params[r]; r++) {}

			memmove(&params[l], &params[r], strlen(params) - r + 1);
		}

		esc = zbx_db_dyn_escape_string(params);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update items set params='%s' where itemid=%s;\n", esc, row[0]);
		zbx_free(esc);

		ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}
	zbx_db_free_result(result);

	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
			ret = FAIL;
	}

	zbx_eval_clear(&ctx);
	zbx_vector_uint32_destroy(&del_idx);

	zbx_free(sql);
	zbx_free(params);

	return ret;
#undef OFFSET_TIME
#undef TOKEN_LEN
#undef LAST_FOREACH
}

static int	DBpatch_6050150(void)
{
	const zbx_db_table_t	table =
			{"item_rtname", "itemid", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name_resolved", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"name_resolved_upper", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050151(void)
{
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_rtname", 1, &field);
}

static int	DBpatch_6050152(void)
{
	if (ZBX_DB_OK <= zbx_db_execute("insert into item_rtname (itemid,name_resolved,name_resolved_upper)"
			" select i.itemid,i.name,i.name_upper from"
			" items i,hosts h"
			" where i.hostid=h.hostid and (h.status=%d or h.status=%d) and (i.flags=%d or i.flags=%d)",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, ZBX_FLAG_DISCOVERY_NORMAL,
			ZBX_FLAG_DISCOVERY_CREATED))
	{
		return SUCCEED;
	}

	return FAIL;
}

static int	DBpatch_6050153(void)
{
	return DBdrop_index("items", "items_9");
}

static int	DBpatch_6050154(void)
{
	return DBdrop_field("items", "name_upper");
}

static int	DBpatch_6050155(void)
{
	return zbx_dbupgrade_drop_trigger_on_insert("items", "name_upper");
}

static int	DBpatch_6050156(void)
{
	return zbx_dbupgrade_drop_trigger_on_update("items", "name_upper");
}

static int	DBpatch_6050157(void)
{
	return zbx_dbupgrade_drop_trigger_function_on_insert("items", "name_upper", "upper");
}

static int	DBpatch_6050158(void)
{
	return zbx_dbupgrade_drop_trigger_function_on_update("items", "name_upper", "upper");
}

static int	DBpatch_6050159(void)
{
#ifdef HAVE_POSTGRESQL
	if (FAIL == zbx_db_index_exists("group_discovery", "group_discovery_pkey1"))
		return SUCCEED;

	return DBrename_index("group_discovery", "group_discovery_pkey1", "group_discovery_pkey",
			"groupdiscoveryid", 1);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_6050160(void)
{
	const zbx_db_field_t	field = {"manualinput", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_6050161(void)
{
	const zbx_db_field_t	field = {"manualinput_prompt", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_6050162(void)
{
	const zbx_db_field_t	field = {"manualinput_validator", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_6050163(void)
{
	const zbx_db_field_t	field = {"manualinput_validator_type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL,
			0};

	return DBadd_field("scripts", &field);
}

static int	DBpatch_6050164(void)
{
	const zbx_db_field_t	field = {"manualinput_default_value", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL,
			0};

	return DBadd_field("scripts", &field);
}

#define BACKSLASH_MATCH_PATTERN	"\\\\"

static int	DBpatch_6050165(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = SUCCEED;
	char		*sql = NULL, *buf = NULL, *like_condition;
	size_t		sql_alloc = 0, sql_offset = 0, buf_alloc;

	/* functions table contains history functions used in trigger expressions */
	like_condition = zbx_db_dyn_escape_like_pattern(BACKSLASH_MATCH_PATTERN);
	if (NULL == (result = zbx_db_select("select functionid,parameter,triggerid"
			" from functions"
			" where " ZBX_DB_CHAR_LENGTH(parameter) ">1 and"
				" parameter like '%%%s%%'", like_condition)))
	{
		goto clean;
	}

	while (NULL != (row = zbx_db_fetch(result)))
	{
		const char	*ptr;
		char		*tmp, *param = NULL;
		int		func_params_changed = 0;
		size_t		param_pos, param_len, sep_pos, buf_offset = 0, params_len;

		params_len = strlen(row[1]);

		for (ptr = row[1]; ptr < row[1] + params_len; ptr += sep_pos + 1)
		{
			zbx_function_param_parse_ext(ptr, ZBX_TOKEN_USER_MACRO, ZBX_BACKSLASH_ESC_OFF,
					&param_pos, &param_len, &sep_pos);

			if (param_pos < sep_pos)
			{
				int	quoted, changed = 0;

				if ('"' == ptr[param_pos])
				{
					param = zbx_function_param_unquote_dyn_compat(ptr + param_pos,
							sep_pos - param_pos, &quoted);

					/* zbx_function_param_quote() should always succeed with esc_bs set to 1 */
					zbx_function_param_quote(&param, quoted, ZBX_BACKSLASH_ESC_ON);

					if (0 != strncmp(param, ptr + param_pos, strlen(param)))
					{
						zbx_strncpy_alloc(&buf, &buf_alloc, &buf_offset, ptr, param_pos);
						zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, param);
						func_params_changed = changed = 1;
					}
				}

				if (0 == changed)
					zbx_strncpy_alloc(&buf, &buf_alloc, &buf_offset, ptr, sep_pos);
			}

			if (',' == ptr[sep_pos])
				zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, ',');
			zbx_free(param);
		}

		if (0 == buf_offset)
			continue;

		if (0 != func_params_changed)
		{
			tmp = zbx_db_dyn_escape_string(buf);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update functions set parameter='%s' where functionid=%s;\n", tmp, row[0]);
			zbx_free(tmp);
		}

		if (SUCCEED != (ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			break;
	}

	zbx_db_free_result(result);
	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
			ret = FAIL;
	}

clean:
	zbx_free(like_condition);
	zbx_free(buf);
	zbx_free(sql);

	return ret;
}

ZBX_PTR_VECTOR_DECL(eval_token_ptr, zbx_eval_token_t *)
ZBX_PTR_VECTOR_IMPL(eval_token_ptr, zbx_eval_token_t *)

static int	update_escaping_in_expression(const char *expression, char **substitute, char **error)
{
	zbx_eval_context_t		ctx;
	int				ret = SUCCEED;
	int				token_num;
	zbx_eval_token_t		*token;
	zbx_vector_eval_token_ptr_t	hist_param_tokens;

	ret = zbx_eval_parse_expression(&ctx, expression, ZBX_EVAL_PARSE_CALC_EXPRESSION |
			ZBX_EVAL_PARSE_STR_V64_COMPAT | ZBX_EVAL_PARSE_LLDMACRO, error);

	if (FAIL == ret)
		return FAIL;

	zbx_vector_eval_token_ptr_create(&hist_param_tokens);

	/* finding string parameters of history functions */
	for (token_num = ctx.stack.values_num - 1; token_num >= 0; token_num--)
	{
		token = &ctx.stack.values[token_num];

		if (token->type  == ZBX_EVAL_TOKEN_HIST_FUNCTION)
		{
			for (zbx_uint32_t i = 0; i < token->opt; i++)
			{
				if (0 == token_num--)
					break;

				if (ZBX_EVAL_TOKEN_VAR_STR == ctx.stack.values[token_num].type)
				{
					zbx_vector_eval_token_ptr_append(&hist_param_tokens,
							&ctx.stack.values[token_num]);
				}
			}
		}
	}

	for (token_num = hist_param_tokens.values_num - 1; token_num >= 0; token_num--)
	{
		char	*str = NULL, *subst;
		int	quoted;
		size_t	str_alloc = 0, str_offset = 0, str_len;

		token = hist_param_tokens.values[token_num];

		str_len = token->loc.r - token->loc.l + 1;
		zbx_strncpy_alloc(&str, &str_alloc, &str_offset, ctx.expression + token->loc.l, str_len);

		subst = zbx_function_param_unquote_dyn_compat(str, str_len, &quoted);
		zbx_variant_set_str(&(token->value), subst);

		zbx_free(str);
	}

	ctx.rules ^= ZBX_EVAL_PARSE_STR_V64_COMPAT;
	zbx_eval_compose_expression(&ctx, substitute);

	zbx_vector_eval_token_ptr_destroy(&hist_param_tokens);
	zbx_eval_clear(&ctx);

	return SUCCEED;
}

static int	DBpatch_6050166(void)
{
int			ret = SUCCEED;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL, *error = NULL, *like_condition;
	size_t			sql_alloc = 0, sql_offset = 0;

	like_condition = zbx_db_dyn_escape_like_pattern(BACKSLASH_MATCH_PATTERN);

	if (NULL == (result = zbx_db_select("select itemid,params from items "
			"where type=15 and params like '%%%s%%'", like_condition)))
	{
		goto clean;
	}

	while (NULL != (row = zbx_db_fetch(result)))
	{
		char	*substitute = NULL, *tmp = NULL;

		if (SUCCEED == update_escaping_in_expression(row[1], &substitute, &error))
		{
			tmp = zbx_db_dyn_escape_string(substitute);
			zbx_free(substitute);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update items set params='%s' where itemid=%s;\n", tmp, row[0]);
			zbx_free(tmp);
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "Failed to parse calculated item expression \"%s\" for"
				" item with id %s, error: %s", row[1], row[0], error);
			zbx_free(error);
		}

		if (SUCCEED != (ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			break;
	}

	zbx_db_free_result(result);
	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
			ret = FAIL;
	}

clean:
	zbx_free(like_condition);
	zbx_free(error);
	zbx_free(sql);

	return ret;
}

static int	find_expression_macro(const char *macro_start, const char **macro_end, char **substitute,
		char **error)
{
	int		ret = FAIL;

	*macro_end = macro_start + 2;

	while (ret == FAIL && NULL != (*macro_end = strstr(*macro_end, "}")))
	{
		char	*expression = NULL;
		size_t	expr_alloc = 0, expr_offset = 0;

		zbx_free(*error);
		zbx_strncpy_alloc(&expression, &expr_alloc, &expr_offset,
				macro_start + 2, (size_t)(*macro_end - macro_start) - 2);
		ret = update_escaping_in_expression(expression, substitute, error);
		zbx_free(expression);
		(*macro_end)++;
	}

	return ret;
}

static void	get_next_expr_macro_start(const char **expr_start, const char *str, size_t str_len)
{
	const char	*search_pos = *expr_start + 2;

	if (NULL != *expr_start && NULL != str && (size_t)(search_pos - str) < str_len)
		*expr_start = strstr(search_pos, "{?");
	else
		*expr_start = NULL;
}

static int	replace_expression_macro(char **buf, size_t *alloc, size_t *offset, const char *command, size_t cmd_len,
		size_t *pos, const char **expr_macro_start)
{
	const char	*macro_end;
	char		*error = NULL, *substitute = NULL;
	int		ret = FAIL;

	if (NULL != *expr_macro_start &&
			SUCCEED == find_expression_macro(*expr_macro_start, &macro_end, &substitute, &error))
	{
		zbx_strncpy_alloc(buf, alloc, offset, command + *pos, (size_t)(*expr_macro_start - command) - *pos);
		zbx_strcpy_alloc(buf, alloc, offset, "{?");
		zbx_strcpy_alloc(buf, alloc, offset, substitute);
		zbx_strcpy_alloc(buf, alloc, offset, "}");
		zbx_free(substitute);

		*expr_macro_start = strstr(macro_end, "{?");
		*pos = (size_t)(macro_end - command);
		ret = SUCCEED;
	}
	else
	{
		get_next_expr_macro_start(expr_macro_start, command, cmd_len);
		zbx_free(error);
	}

	return ret;
}

static int	fix_expression_macro_escaping(const char *table, const char *id_col, const char *data_col)
{
	int			ret = SUCCEED;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL, *like_condition;
	size_t			sql_alloc = 0, sql_offset = 0;

	like_condition = zbx_db_dyn_escape_like_pattern(BACKSLASH_MATCH_PATTERN);

	if (NULL == (result = zbx_db_select("select %s,%s from %s where %s like '%%%s%%'",
			id_col, data_col, table, data_col, like_condition)))
	{
		goto clean;
	}

	while (NULL != (row = zbx_db_fetch(result)))
	{
		const char	*command = row[1];
		char		*buf = NULL, *tmp = NULL;
		size_t		buf_alloc = 0, buf_offset = 0;
		size_t		pos = 0, cmd_len;
		int		replaced = 0;
		zbx_token_t	token;
		const char	*expr_macro_start;

		cmd_len = strlen(command);
		expr_macro_start = strstr(command, "{?");

		while (SUCCEED == zbx_token_find(command, (int)pos, &token, ZBX_TOKEN_SEARCH_BASIC) &&
				cmd_len >= pos && NULL != expr_macro_start)
		{
			int	replace_success = 0;

			while (NULL != expr_macro_start && token.loc.l >= (size_t)(expr_macro_start - command))
			{
				if (SUCCEED == replace_expression_macro(&buf, &buf_alloc, &buf_offset, command,
							cmd_len, &pos, &expr_macro_start))
				{
					replaced = replace_success = 1;
				}
			}

			if (0 == replace_success)
			{
				expr_macro_start = command + token.loc.r - 2;
				get_next_expr_macro_start(&expr_macro_start, command, cmd_len);
				zbx_strncpy_alloc(&buf, &buf_alloc, &buf_offset, command + pos, token.loc.r - pos + 1);
				pos = token.loc.r + 1;
			}
		}

		while (NULL != expr_macro_start)	/* expression macros after the end of tokens */
		{
			if (SUCCEED == replace_expression_macro(&buf, &buf_alloc, &buf_offset, command,
							cmd_len, &pos, &expr_macro_start))
			{
				replaced = 1;
			}
		}

		if (0 != replaced)
		{
			if (cmd_len >= pos)
				zbx_strncpy_alloc(&buf, &buf_alloc, &buf_offset, command + pos, cmd_len - pos);

			tmp = zbx_db_dyn_escape_string(buf);
			zbx_free(buf);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set %s='%s' where %s=%s;\n",
					table, data_col, tmp, id_col, row[0]);
			zbx_free(tmp);

			if (SUCCEED != (ret = zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
				break;
		}
		else
			zbx_free(buf);
	}

	zbx_db_free_result(result);

	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
			ret = FAIL;
	}

clean:
	zbx_free(like_condition);
	zbx_free(sql);

	return ret;
}

#undef BACKSLASH_MATCH_PATTERN

static int	DBpatch_6050167(void)
{
	return fix_expression_macro_escaping("scripts", "scriptid", "command");
}

static int	DBpatch_6050168(void)
{
	return fix_expression_macro_escaping("script_param", "script_paramid", "value");
}

static int	DBpatch_6050169(void)
{
	return fix_expression_macro_escaping("media_type_message", "mediatype_messageid", "message");
}

static int	DBpatch_6050170(void)
{
	return fix_expression_macro_escaping("media_type_message", "mediatype_messageid", "subject");
}

static int	DBpatch_6050171(void)
{
	return fix_expression_macro_escaping("opmessage", "operationid", "message");
}

static int	DBpatch_6050172(void)
{
	return fix_expression_macro_escaping("opmessage", "operationid", "subject");
}

static int	DBpatch_6050173(void)
{
	return fix_expression_macro_escaping("triggers", "triggerid", "event_name");
}

static int	DBpatch_6050174(void)
{
	return fix_expression_macro_escaping("media_type_param", "mediatype_paramid", "value");
}

static int	DBpatch_6050175(void)
{
	return fix_expression_macro_escaping("media_type_param", "mediatype_paramid", "name");
}

typedef struct
{
	char		*name;
	zbx_uint64_t	wid;
	zbx_uint64_t	wfid;
	char		*value_str;
	int		value_int;
}
zbx_wiget_field_t;

ZBX_PTR_VECTOR_DECL(wiget_field, zbx_wiget_field_t *)
ZBX_PTR_VECTOR_IMPL(wiget_field, zbx_wiget_field_t *)

static void	zbx_wiget_field_free(zbx_wiget_field_t *wf)
{
	zbx_free(wf->name);
	zbx_free(wf->value_str);
	zbx_free(wf);
}

static int	zbx_wiget_field_compare(const void *d1, const void *d2)
{
	const zbx_wiget_field_t	*f1 = *(const zbx_wiget_field_t * const *)d1;
	const zbx_wiget_field_t	*f2 = *(const zbx_wiget_field_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(f1->wid, f2->wid);

	return strcmp(f1->name, f2->name);
}

static void	DBpatch_6050176_transform(zbx_vector_wiget_field_t *timeshift, zbx_vector_wiget_field_t *interval,
		zbx_vector_wiget_field_t *aggr_func, zbx_vector_wiget_field_t *time_from,
		zbx_vector_wiget_field_t *time_to, zbx_vector_uint64_t *nofunc_ids)
{
	int	i;

	zbx_vector_wiget_field_sort(interval, zbx_wiget_field_compare);
	zbx_vector_wiget_field_sort(timeshift, zbx_wiget_field_compare);

	for (i = 0; i < aggr_func->values_num; i++)	/* remove fields if aggregate_function = 0 */
	{
		int			n;
		zbx_wiget_field_t	*val = aggr_func->values[i];

		if (0 != val->value_int)
			continue;

		if (FAIL != (n = zbx_vector_wiget_field_bsearch(interval, val, zbx_wiget_field_compare)))
		{
			zbx_vector_uint64_append(nofunc_ids, interval->values[n]->wfid);
			zbx_wiget_field_free(interval->values[n]);
			zbx_vector_wiget_field_remove_noorder(interval, n);
		}

		if (FAIL != (n = zbx_vector_wiget_field_bsearch(timeshift, val, zbx_wiget_field_compare)))
		{
			zbx_vector_uint64_append(nofunc_ids, timeshift->values[n]->wfid);
			zbx_wiget_field_free(timeshift->values[n]);
			zbx_vector_wiget_field_remove(timeshift, n);
		}
	}

	while (0 < interval->values_num)	/* columns.N.time_period.from */
	{
		int			n;
		const char		*shift, *sign_shift = "+", *sign_interv = "-";
		zbx_wiget_field_t	*val = interval->values[interval->values_num - 1];

		if (FAIL == (n = zbx_vector_wiget_field_bsearch(timeshift, val, zbx_wiget_field_compare)))
			shift = "";
		else
			shift = timeshift->values[n]->value_str;

		if ('\0' == *shift || '-' == *shift)
			sign_shift = "";

		if ('\0' == *val->value_str)
			sign_interv = "";

		val->value_str = zbx_dsprintf(val->value_str, "now%s%s%s%s", sign_shift, shift, sign_interv,
				val->value_str);
		zbx_vector_wiget_field_append(time_from, val);
		zbx_vector_wiget_field_remove_noorder(interval, interval->values_num - 1);
	}

	while (0 < timeshift->values_num)	/* columns.N.time_period.to */
	{
		const char		*sign_shift = "+";
		zbx_wiget_field_t	*val = timeshift->values[timeshift->values_num - 1];

		if ('\0' == *val->value_str || '-' == *val->value_str)
			sign_shift = "";

		val->value_str = zbx_dsprintf(val->value_str, "now%s%s", sign_shift, val->value_str);
		zbx_vector_wiget_field_append(time_to, val);
		zbx_vector_wiget_field_remove_noorder(timeshift, timeshift->values_num - 1);
	}
}

static int	DBpatch_6050176_load(zbx_vector_wiget_field_t *time_from, zbx_vector_wiget_field_t *time_to,
		zbx_vector_uint64_t *nofunc_ids)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_vector_wiget_field_t	timeshift, interval, aggr_func;

	if (NULL == (result = zbx_db_select("select widget_fieldid,widgetid,name,value_str,value_int from widget_field"
				" where name like 'columns.%%.timeshift'"
					" or name like 'columns.%%.aggregate_interval'"
					" or name like 'columns.%%.aggregate_function'"
					" and widgetid in (select widgetid from widget where type='tophosts')")))
	{
		return FAIL;
	}

	zbx_vector_wiget_field_create(&timeshift);
	zbx_vector_wiget_field_create(&interval);
	zbx_vector_wiget_field_create(&aggr_func);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_wiget_field_t	*val;
		const char		*name;
		size_t			l;

		val = (zbx_wiget_field_t *) zbx_malloc(NULL, sizeof(zbx_wiget_field_t));

		ZBX_STR2UINT64(val->wfid, row[0]);
		ZBX_STR2UINT64(val->wid, row[1]);
		name = row[2];
		l = strlen(name);
		val->value_str = zbx_strdup(NULL, row[3]);
		val->value_int = atoi(row[4]);

		if ('t' == name[l - 1])
		{
			val->name = zbx_dsprintf(NULL, "%.*s", (int)(l - ZBX_CONST_STRLEN("columns" "timeshift")),
					&name[ZBX_CONST_STRLEN("columns")]);
			zbx_vector_wiget_field_append(&timeshift, val);
		}
		else if  ('l' == name[l - 1])
		{
			val->name = zbx_dsprintf(NULL, "%.*s",
					(int)(l - ZBX_CONST_STRLEN("columns" "aggregate_interval")),
					&name[ZBX_CONST_STRLEN("columns")]);
			zbx_vector_wiget_field_append(&interval, val);
		}
		else
		{
			val->name = zbx_dsprintf(NULL, "%.*s",
					(int)(l - ZBX_CONST_STRLEN("columns" "aggregate_function")),
					&name[ZBX_CONST_STRLEN("columns")]);
			zbx_vector_wiget_field_append(&aggr_func, val);
		}
	}
	zbx_db_free_result(result);

	DBpatch_6050176_transform(&timeshift, &interval, &aggr_func, time_from, time_to, nofunc_ids);

	zbx_vector_wiget_field_clear_ext(&timeshift, zbx_wiget_field_free);
	zbx_vector_wiget_field_clear_ext(&interval, zbx_wiget_field_free);
	zbx_vector_wiget_field_clear_ext(&aggr_func, zbx_wiget_field_free);
	zbx_vector_wiget_field_destroy(&timeshift);
	zbx_vector_wiget_field_destroy(&interval);
	zbx_vector_wiget_field_destroy(&aggr_func);

	return SUCCEED;
}

static int	DBpatch_6050176_remove(zbx_vector_uint64_t *nofuncs)
{
	if (0 == nofuncs->values_num)
		return SUCCEED;

	zbx_vector_uint64_sort(nofuncs,ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	return zbx_db_execute_multiple_query("delete from widget_field where", "widget_fieldid", nofuncs);
}

static int	DBpatch_6050176_update(zbx_vector_wiget_field_t *time_from, zbx_vector_wiget_field_t *time_to)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	i, ret = SUCCEED;

	for (i = 0; i < time_from->values_num; i++)
	{
		zbx_wiget_field_t	*val = time_from->values[i];
		char			name[255 * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

		zbx_snprintf(name, sizeof(name), "columns%stime_period.from", val->name);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update widget_field"
				" set value_str='%s',name='%s'"
				" where widget_fieldid=" ZBX_FS_UI64 ";\n",
				val->value_str, name, val->wfid);
		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	for (i = 0; i < time_to->values_num; i++)
	{
		zbx_wiget_field_t	*val = time_to->values[i];
		char			name[255 * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

		zbx_snprintf(name, sizeof(name), "columns%stime_period.to", val->name);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update widget_field"
				" set value_str='%s',name='%s'"
				" where widget_fieldid=" ZBX_FS_UI64 ";\n",
				val->value_str, name, val->wfid);
		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
		ret = FAIL;

	zbx_free(sql);

	return ret;
}

static int	DBpatch_6050176(void)
{
	zbx_vector_wiget_field_t	time_from, time_to;
	zbx_vector_uint64_t		nofuncs_ids;
	int				ret = FAIL;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_vector_wiget_field_create(&time_from);
	zbx_vector_wiget_field_create(&time_to);
	zbx_vector_uint64_create(&nofuncs_ids);

	if (SUCCEED == DBpatch_6050176_load(&time_from, &time_to, &nofuncs_ids)
			&& SUCCEED == DBpatch_6050176_remove(&nofuncs_ids)
			&& SUCCEED == DBpatch_6050176_update(&time_from, &time_to))
	{
		ret = SUCCEED;
	}

	zbx_vector_wiget_field_clear_ext(&time_from, zbx_wiget_field_free);
	zbx_vector_wiget_field_clear_ext(&time_to, zbx_wiget_field_free);
	zbx_vector_wiget_field_destroy(&time_from);
	zbx_vector_wiget_field_destroy(&time_to);
	zbx_vector_uint64_destroy(&nofuncs_ids);

	return ret;
}

static int	DBpatch_6050177(void)
{
	const zbx_db_table_t	table =
			{"ugset", "ugsetid", 0,
				{
					{"ugsetid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"hash", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050178(void)
{
	return DBcreate_index("ugset", "ugset_1", "hash", 0);
}

static int	DBpatch_6050179(void)
{
	const zbx_db_table_t	table =
			{"ugset_group", "ugsetid,usrgrpid", 0,
				{
					{"ugsetid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"usrgrpid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050180(void)
{
	return DBcreate_index("ugset_group", "ugset_group_1", "usrgrpid", 0);
}

static int	DBpatch_6050181(void)
{
	const zbx_db_field_t	field = {"ugsetid", NULL, "ugset", "ugsetid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("ugset_group", 1, &field);
}

static int	DBpatch_6050182(void)
{
	const zbx_db_field_t	field = {"usrgrpid", NULL, "usrgrp", "usrgrpid", 0, 0, 0, 0};

	return DBadd_foreign_key("ugset_group", 2, &field);
}

static int	DBpatch_6050183(void)
{
	const zbx_db_table_t	table =
			{"user_ugset", "userid", 0,
				{
					{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"ugsetid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050184(void)
{
	return DBcreate_index("user_ugset", "user_ugset_1", "ugsetid", 0);
}

static int	DBpatch_6050185(void)
{
	const zbx_db_field_t	field = {"userid", NULL, "users", "userid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("user_ugset", 1, &field);
}

static int	DBpatch_6050186(void)
{
	const zbx_db_field_t	field = {"ugsetid", NULL, "ugset", "ugsetid", 0, 0, 0, 0};

	return DBadd_foreign_key("user_ugset", 2, &field);
}

static int	DBpatch_6050187(void)
{
	const zbx_db_table_t	table =
			{"hgset", "hgsetid", 0,
				{
					{"hgsetid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"hash", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050188(void)
{
	return DBcreate_index("hgset", "hgset_1", "hash", 0);
}

static int	DBpatch_6050189(void)
{
	const zbx_db_table_t	table =
			{"hgset_group", "hgsetid,groupid", 0,
				{
					{"hgsetid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050190(void)
{
	return DBcreate_index("hgset_group", "hgset_group_1", "groupid", 0);
}

static int	DBpatch_6050191(void)
{
	const zbx_db_field_t	field = {"hgsetid", NULL, "hgset", "hgsetid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("hgset_group", 1, &field);
}

static int	DBpatch_6050192(void)
{
	const zbx_db_field_t	field = {"groupid", NULL, "hstgrp", "groupid", 0, 0, 0, 0};

	return DBadd_foreign_key("hgset_group", 2, &field);
}

static int	DBpatch_6050193(void)
{
	const zbx_db_table_t	table =
			{"host_hgset", "hostid", 0,
				{
					{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"hgsetid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050194(void)
{
	return DBcreate_index("host_hgset", "host_hgset_1", "hgsetid", 0);
}

static int	DBpatch_6050195(void)
{
	const zbx_db_field_t	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("host_hgset", 1, &field);
}

static int	DBpatch_6050196(void)
{
	const zbx_db_field_t	field = {"hgsetid", NULL, "hgset", "hgsetid", 0, 0, 0, 0};

	return DBadd_foreign_key("host_hgset", 2, &field);
}

static int	DBpatch_6050197(void)
{
	const zbx_db_table_t	table =
			{"permission", "ugsetid,hgsetid", 0,
				{
					{"ugsetid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"hgsetid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"permission", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050198(void)
{
	return DBcreate_index("permission", "permission_1", "hgsetid", 0);
}

static int	DBpatch_6050199(void)
{
	const zbx_db_field_t	field = {"ugsetid", NULL, "ugset", "ugsetid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("permission", 1, &field);
}

static int	DBpatch_6050200(void)
{
	const zbx_db_field_t	field = {"hgsetid", NULL, "hgset", "hgsetid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("permission", 2, &field);
}

typedef struct
{
	char			hash_str[ZBX_SHA256_DIGEST_SIZE * 2 + 1];
	zbx_vector_uint64_t	groupids;
	zbx_vector_uint64_t	ids;
} zbx_dbu_group_set_t;

static zbx_hash_t	dbupgrade_group_set_hash(const void *data)
{
	const zbx_dbu_group_set_t	*group_set = (const zbx_dbu_group_set_t *)data;

	return ZBX_DEFAULT_STRING_HASH_FUNC(group_set->hash_str);
}

static int	dbupgrade_group_set_compare(const void *d1, const void *d2)
{
	const zbx_dbu_group_set_t	*group_set1 = (const zbx_dbu_group_set_t *)d1;
	const zbx_dbu_group_set_t	*group_set2 = (const zbx_dbu_group_set_t *)d2;

	return strcmp(group_set1->hash_str, group_set2->hash_str);
}

static int	dbupgrade_groupsets_make(zbx_vector_uint64_t *ids, const char *fld_name_id,
		const char *fld_name_groupid, const char *tbl_name_groups, zbx_hashset_t *group_sets,
		int allow_empty_groups)
{
	int			ret = SUCCEED;
	char			id_str[MAX_ID_LEN + 2];
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	groupids;
	zbx_dbu_group_set_t	*gset_ptr;

	id_str[0] = '|';
	zbx_vector_uint64_create(&groupids);

	for (int i = 0; i < ids->values_num; i++)
	{
		unsigned char		hash[ZBX_SHA256_DIGEST_SIZE];
		char			*id_str_p = id_str + 1;
		sha256_ctx		ctx;
		zbx_dbu_group_set_t	gset;

		zbx_sha256_init(&ctx);

		result = zbx_db_select("select %s from %s where %s=" ZBX_FS_UI64 " order by %s",
				fld_name_groupid, tbl_name_groups, fld_name_id, ids->values[i], fld_name_groupid);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	groupid;

			ZBX_STR2UINT64(groupid, row[0]);

			if (1 == groupids.values_num)
				id_str_p = id_str;

			zbx_snprintf(id_str + 1, MAX_ID_LEN + 1, "%s", row[0]);
			zbx_sha256_process_bytes(id_str_p, strlen(id_str_p), &ctx);
			zbx_vector_uint64_append(&groupids, groupid);
		}
		zbx_db_free_result(result);

		if (0 == groupids.values_num)
		{
			if (0 == allow_empty_groups)
			{
				zabbix_log(LOG_LEVEL_WARNING, "host or template [hostid=" ZBX_FS_UI64 "] is not"
						" assigned to any group, permissions not granted", ids->values[i]);
			}

			continue;
		}

		zbx_sha256_finish(&ctx, hash);
		(void)zbx_bin2hex(hash, ZBX_SHA256_DIGEST_SIZE, gset.hash_str,
				ZBX_SHA256_DIGEST_SIZE * 2 + 1);

		if (NULL == (gset_ptr = zbx_hashset_search(group_sets, &gset)))
		{
			zbx_vector_uint64_create(&gset.ids);
			zbx_vector_uint64_create(&gset.groupids);
			zbx_vector_uint64_append_array(&gset.groupids, groupids.values, groupids.values_num);

			if (NULL == (gset_ptr = zbx_hashset_insert(group_sets, &gset, sizeof(zbx_dbu_group_set_t))))
			{
				ret = FAIL;
				break;
			}
		}

		zbx_vector_uint64_append(&gset_ptr->ids, ids->values[i]);
		zbx_vector_uint64_clear(&groupids);
	}

	zbx_vector_uint64_destroy(&groupids);

	return ret;
}

static int	dbupgrade_groupsets_insert(const char *tbl_name, zbx_hashset_t *group_sets,
		zbx_db_insert_t *db_gset, zbx_db_insert_t *db_gset_groups, zbx_db_insert_t *db_gset_parents)
{
	zbx_uint64_t		gsetid;
	zbx_hashset_iter_t	iter;
	zbx_dbu_group_set_t	*gset_ptr;

	if (0 == group_sets->num_data)
		return SUCCEED;

	gsetid = zbx_db_get_maxid_num(tbl_name, group_sets->num_data);

	zbx_hashset_iter_reset(group_sets, &iter);

	while (NULL != (gset_ptr = (zbx_dbu_group_set_t *)zbx_hashset_iter_next(&iter)))
	{
		int	i;

		zbx_db_insert_add_values(db_gset, gsetid, gset_ptr->hash_str);

		for (i = 0; i < gset_ptr->groupids.values_num; i++)
			zbx_db_insert_add_values(db_gset_groups, gsetid, gset_ptr->groupids.values[i]);

		for (i = 0; i < gset_ptr->ids.values_num; i++)
			zbx_db_insert_add_values(db_gset_parents, gset_ptr->ids.values[i], gsetid);

		gsetid++;
	}

	if (FAIL == zbx_db_insert_execute(db_gset) ||
			FAIL == zbx_db_insert_execute(db_gset_groups) ||
			FAIL == zbx_db_insert_execute(db_gset_parents))
	{
		return FAIL;
	}

	return SUCCEED;
}

static void	dbupgrade_groupsets_destroy(zbx_hashset_t *group_sets)
{
	zbx_hashset_iter_t	iter;
	zbx_dbu_group_set_t	*gset_ptr;

	zbx_hashset_iter_reset(group_sets, &iter);

	while (NULL != (gset_ptr = (zbx_dbu_group_set_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_uint64_destroy(&gset_ptr->groupids);
		zbx_vector_uint64_destroy(&gset_ptr->ids);
	}

	zbx_hashset_destroy(group_sets);
}

static int	DBpatch_6050201(void)
{
	int			ret;
	zbx_vector_uint64_t	ids;
	zbx_hashset_t		group_sets;
	zbx_db_insert_t		db_insert, db_insert_groups, db_insert_hosts;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_hashset_create(&group_sets, 1, dbupgrade_group_set_hash, dbupgrade_group_set_compare);
	zbx_db_insert_prepare(&db_insert, "hgset", "hgsetid", "hash", (char*)NULL);
	zbx_db_insert_prepare(&db_insert_groups, "hgset_group", "hgsetid", "groupid", (char*)NULL);
	zbx_db_insert_prepare(&db_insert_hosts, "host_hgset", "hostid", "hgsetid", (char*)NULL);

	zbx_vector_uint64_create(&ids);
	zbx_db_select_uint64("select hostid from hosts where flags<>2", &ids);

	if (SUCCEED == (ret = dbupgrade_groupsets_make(&ids, "hostid", "groupid", "hosts_groups", &group_sets, 0)))
		ret = dbupgrade_groupsets_insert("hgset", &group_sets, &db_insert, &db_insert_groups, &db_insert_hosts);

	zbx_db_insert_clean(&db_insert);
	zbx_db_insert_clean(&db_insert_groups);
	zbx_db_insert_clean(&db_insert_hosts);

	zbx_vector_uint64_destroy(&ids);
	dbupgrade_groupsets_destroy(&group_sets);

	return ret;
}

static int	DBpatch_6050202(void)
{
	int			ret;
	zbx_vector_uint64_t	ids;
	zbx_hashset_t		group_sets;
	zbx_db_insert_t		db_insert, db_insert_groups, db_insert_users;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_hashset_create(&group_sets, 1, dbupgrade_group_set_hash, dbupgrade_group_set_compare);
	zbx_db_insert_prepare(&db_insert, "ugset", "ugsetid", "hash", (char*)NULL);
	zbx_db_insert_prepare(&db_insert_groups, "ugset_group", "ugsetid", "usrgrpid", (char*)NULL);
	zbx_db_insert_prepare(&db_insert_users, "user_ugset", "userid", "ugsetid", (char*)NULL);

	zbx_vector_uint64_create(&ids);
	zbx_db_select_uint64("select u.userid from users u join role r on u.roleid=r.roleid where r.type<>3", &ids);

	if (SUCCEED == (ret = dbupgrade_groupsets_make(&ids, "userid", "usrgrpid", "users_groups", &group_sets, 1)))
		ret = dbupgrade_groupsets_insert("ugset", &group_sets, &db_insert, &db_insert_groups, &db_insert_users);

	zbx_db_insert_clean(&db_insert);
	zbx_db_insert_clean(&db_insert_groups);
	zbx_db_insert_clean(&db_insert_users);

	zbx_vector_uint64_destroy(&ids);
	dbupgrade_groupsets_destroy(&group_sets);

	return ret;
}

static int	DBpatch_6050203(void)
{
	int		ret;
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_db_insert_t	db_insert;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "permission", "ugsetid", "hgsetid", "permission", (char*)NULL);

	result = zbx_db_select("select u.ugsetid,h.hgsetid,max(r.permission)"
			" from hgset h"
			" join hgset_group hg"
				" on h.hgsetid=hg.hgsetid"
			" join rights r on hg.groupid=r.id"
			" join ugset_group ug"
				" on r.groupid=ug.usrgrpid"
			" join ugset u"
				" on ug.ugsetid=u.ugsetid"
			" group by u.ugsetid,h.hgsetid"
			" having min(r.permission)>0"
			" order by u.ugsetid,h.hgsetid");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hgsetid, ugsetid;
		int		permission;

		ZBX_STR2UINT64(ugsetid, row[0]);
		ZBX_STR2UINT64(hgsetid, row[1]);
		permission = atoi(row[2]);

		zbx_db_insert_add_values(&db_insert, ugsetid, hgsetid, permission);
	}
	zbx_db_free_result(result);

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_6050204(void)
{
	return DBrename_table("globalvars", "globalvars_tmp");
}

static int	DBpatch_6050205(void)
{
	const zbx_db_table_t	table =
			{"globalvars", "name", 0,
				{
					{"name", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050206(void)
{
	if (ZBX_DB_OK > zbx_db_execute("insert into globalvars (name,value)"
			" select 'snmp_lastsize',snmp_lastsize from globalvars_tmp"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050207(void)
{
	return DBdrop_table("globalvars_tmp");
}

static int	DBpatch_6050208(void)
{
#ifdef HAVE_POSTGRESQL
	if (FAIL == zbx_db_index_exists("globalvars", "globalvars_pkey1"))
		return SUCCEED;

	return DBrename_index("globalvars", "globalvars_pkey1", "globalvars_pkey",
			"name", 1);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_6050209(void)
{
	const zbx_db_field_t	field = {"auditlog_mode", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050210(void)
{
	int		ret = SUCCEED;
	zbx_uint64_t	ugsetid;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = zbx_db_select_n("select ugsetid from ugset where ugsetid not in (select ugsetid from ugset_group)", 1);

	if (NULL == (row = zbx_db_fetch(result)))
		goto out;

	ZBX_STR2UINT64(ugsetid, row[0]);

	if (ZBX_DB_OK > zbx_db_execute("delete from user_ugset where ugsetid=" ZBX_FS_UI64, ugsetid) ||
			ZBX_DB_OK > zbx_db_execute("delete from ugset where ugsetid=" ZBX_FS_UI64, ugsetid))
	{
		ret = FAIL;
		goto out;
	}
out:
	zbx_db_free_result(result);

	return ret;
}

static int	DBpatch_6050211(void)
{
	const zbx_db_field_t	field = {"history", "31d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("items", &field);
}

static int	DBpatch_6050212(void)
{
	const zbx_db_field_t	field = {"history", "31d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("lld_override_ophistory", &field);
}

static int	DBpatch_6050213(void)
{
	const zbx_db_field_t	field = {"mfa_status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("usrgrp", &field);
}

static int	DBpatch_6050214(void)
{
	const zbx_db_field_t	field = {"mfaid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("usrgrp", &field);
}

static int	DBpatch_6050215(void)
{
	return DBcreate_index("usrgrp", "usrgrp_3", "mfaid", 0);
}

static int	DBpatch_6050216(void)
{
	const zbx_db_field_t	field = {"mfa_status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050217(void)
{
	const zbx_db_field_t	field = {"mfaid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050218(void)
{
	return DBcreate_index("config", "config_5", "mfaid", 0);
}

static int	DBpatch_6050219(void)
{
	const zbx_db_table_t	table =
			{"mfa", "mfaid", 0,
				{
					{"mfaid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"hash_function", "1", NULL, NULL, 0, ZBX_TYPE_INT, 0, 0},
					{"code_length", "6", NULL, NULL, 0, ZBX_TYPE_INT, 0, 0},
					{"api_hostname", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, 0, 0},
					{"clientid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, 0, 0},
					{"client_secret", "", NULL, NULL, 64, ZBX_TYPE_CHAR, 0, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050220(void)
{
	return DBcreate_index("mfa", "mfa_1", "name", 1);
}

static int	DBpatch_6050221(void)
{
	const zbx_db_field_t	field = {"mfaid", NULL, "mfa", "mfaid", 0, 0, 0, 0};

	return DBadd_foreign_key("usrgrp", 3, &field);
}

static int	DBpatch_6050222(void)
{
	const zbx_db_field_t	field = {"mfaid", NULL, "mfa", "mfaid", 0, 0, 0, 0};

	return DBadd_foreign_key("config", 5, &field);
}

static int	DBpatch_6050223(void)
{
	const zbx_db_table_t	table =
			{"mfa_totp_secret", "mfa_totp_secretid", 0,
				{
					{"mfa_totp_secretid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"mfaid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"userid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"totp_secret", "", NULL, NULL, 32, ZBX_TYPE_CHAR, 0, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_6050224(void)
{
	return DBcreate_index("mfa_totp_secret", "mfa_totp_secret_1", "mfaid", 0);
}

static int	DBpatch_6050225(void)
{
	const zbx_db_field_t	field = {"mfaid", NULL, "mfa", "mfaid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("mfa_totp_secret", 1, &field);
}

static int	DBpatch_6050226(void)
{
	return DBcreate_index("mfa_totp_secret", "mfa_totp_secret_2", "userid", 0);
}

static int	DBpatch_6050227(void)
{
	const zbx_db_field_t	field = {"userid", NULL, "users", "userid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("mfa_totp_secret", 2, &field);
}

static int	DBpatch_6050228(void)
{
	const zbx_db_field_t	field = {"error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("drules", &field);
}

static int	DBpatch_6050229(void)
{
	const zbx_db_field_t	field = {"error", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy_dhistory", &field);
}

static int	DBpatch_6050230(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("insert into module (moduleid,id,relative_path,status,config) values"
			" (" ZBX_FS_UI64 ",'honeycomb','widgets/honeycomb',%d,'[]')", zbx_db_get_maxid("module"), 1))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050231(void)
{
	const zbx_db_field_t	field = {"lifetime_type", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_6050232(void)
{
	const zbx_db_field_t	field = {"enabled_lifetime_type", "2", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_6050233(void)
{
	const zbx_db_field_t	field = {"enabled_lifetime", "0", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("items", &field);
}

static int	DBpatch_6050234(void)
{
	const zbx_db_field_t	field = {"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("host_discovery", &field);
}

static int	DBpatch_6050235(void)
{
	const zbx_db_field_t	field = {"disable_source", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("host_discovery", &field);
}

static int	DBpatch_6050236(void)
{
	const zbx_db_field_t	field = {"ts_disable", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("host_discovery", &field);
}

static int	DBpatch_6050237(void)
{
	const zbx_db_field_t	field = {"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("item_discovery", &field);
}

static int	DBpatch_6050238(void)
{
	const zbx_db_field_t	field = {"disable_source", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("item_discovery", &field);
}

static int	DBpatch_6050239(void)
{
	const zbx_db_field_t	field = {"ts_disable", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("item_discovery", &field);
}

static int	DBpatch_6050240(void)
{
	const zbx_db_field_t	field = {"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("trigger_discovery", &field);
}

static int	DBpatch_6050241(void)
{
	const zbx_db_field_t	field = {"disable_source", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("trigger_discovery", &field);
}

static int	DBpatch_6050242(void)
{
	const zbx_db_field_t	field = {"ts_disable", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("trigger_discovery", &field);
}

static int	DBpatch_6050243(void)
{
	const zbx_db_field_t	field = {"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("graph_discovery", &field);
}

static int	DBpatch_6050244(void)
{
	const zbx_db_field_t	field = {"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("group_discovery", &field);
}

static int	DBpatch_6050245(void)
{
	const zbx_db_field_t	field = {"lifetime", "7d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("items", &field);
}

static int	DBpatch_6050246(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* update default value for items and item prototypes */
	if (ZBX_DB_OK > zbx_db_execute("update items set lifetime='7d' where flags in (0,2,4)"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050247(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* set LIFETIME_TYPE_IMMEDIATELY for LLD rules with 0 lifetime */
	if (ZBX_DB_OK > zbx_db_execute("update items set lifetime_type=2 where flags=1 and lifetime like '0%%'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050248(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* set LIFETIME_TYPE_NEVER for existing LLD rules */
	if (ZBX_DB_OK > zbx_db_execute("update items set enabled_lifetime_type=1 where flags=1"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050249(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* set DISCOVERY_STATUS_LOST */
	if (ZBX_DB_OK > zbx_db_execute("update host_discovery set status=1 where ts_delete<>0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050250(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* set DISCOVERY_STATUS_LOST */
	if (ZBX_DB_OK > zbx_db_execute("update item_discovery set status=1 where ts_delete<>0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050251(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* set DISCOVERY_STATUS_LOST */
	if (ZBX_DB_OK > zbx_db_execute("update trigger_discovery set status=1 where ts_delete<>0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050252(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* set DISCOVERY_STATUS_LOST */
	if (ZBX_DB_OK > zbx_db_execute("update graph_discovery set status=1 where ts_delete<>0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050253(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* set DISCOVERY_STATUS_LOST */
	if (ZBX_DB_OK > zbx_db_execute("update group_discovery set status=1 where ts_delete<>0"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050254(void)
{
	const zbx_db_field_t	field = {"status", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("mfa_totp_secret", &field);
}

static int	DBpatch_6050255(void)
{
	const zbx_db_field_t	field = {"used_codes", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("mfa_totp_secret", &field);
}

static int	DBpatch_6050256(void)
{
	zbx_uint64_t	moduleid;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	moduleid = zbx_db_get_maxid("module");

	if (ZBX_DB_OK > zbx_db_execute("insert into module (moduleid,id,relative_path,status,config) values"
			" (" ZBX_FS_UI64 ",'hostnavigator','widgets/hostnavigator',%d,'[]')", moduleid, 1))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050257(void)
{
	const zbx_db_table_t	table = {"proxy_group", "proxy_groupid", 0,
			{
				{"proxy_groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"description", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
				{"failover_delay", "1m", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"min_online", "1", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_6050258(void)
{
	return DBcreate_changelog_insert_trigger("proxy_group", "proxy_groupid");
}

static int	DBpatch_6050259(void)
{
	return DBcreate_changelog_update_trigger("proxy_group", "proxy_groupid");
}

static int	DBpatch_6050260(void)
{
	return DBcreate_changelog_delete_trigger("proxy_group", "proxy_groupid");
}

static int	DBpatch_6050261(void)
{
	const zbx_db_table_t	table = {"host_proxy", "hostproxyid", 0,
			{
				{"hostproxyid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
				{"host", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"proxyid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0},
				{"revision", "0", NULL, NULL, 0, ZBX_TYPE_UINT, ZBX_NOTNULL, 0},
				{"tls_accept", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{"tls_issuer", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"tls_subject", "", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"tls_psk_identity", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{"tls_psk", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_6050262(void)
{
	return DBcreate_index("host_proxy", "host_proxy_1", "hostid", 1);
}

static int	DBpatch_6050263(void)
{
	return DBcreate_index("host_proxy", "host_proxy_2", "proxyid", 0);
}

static int	DBpatch_6050264(void)
{
	return DBcreate_index("host_proxy", "host_proxy_3", "revision", 0);
}

static int	DBpatch_6050265(void)
{
	const zbx_db_field_t	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, 0};

	return DBadd_foreign_key("host_proxy", 1, &field);
}

static int	DBpatch_6050266(void)
{
	const zbx_db_field_t	field = {"proxyid", NULL, "proxy", "proxyid", 0, 0, 0, 0};

	return DBadd_foreign_key("host_proxy", 2, &field);
}

static int	DBpatch_6050267(void)
{
	return DBcreate_changelog_insert_trigger("host_proxy", "hostproxyid");
}

static int	DBpatch_6050268(void)
{
	return DBcreate_changelog_update_trigger("host_proxy", "hostproxyid");
}

static int	DBpatch_6050269(void)
{
	return DBcreate_changelog_delete_trigger("host_proxy", "hostproxyid");
}

static int	DBpatch_6050270(void)
{
	const zbx_db_field_t	field = {"local_address", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050271(void)
{
	const zbx_db_field_t	field = {"local_port", "10051", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050272(void)
{
	const zbx_db_field_t	field = {"proxy_groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050273(void)
{
	return DBcreate_index("proxy", "proxy_2", "proxy_groupid", 0);
}

static int	DBpatch_6050274(void)
{
	const zbx_db_field_t	field = {"proxy_groupid", NULL, "proxy_group", "proxy_groupid", 0, 0, 0, 0};

	return DBadd_foreign_key("proxy", 1, &field);
}

static int	DBpatch_6050275(void)
{
	const zbx_db_field_t	field = {"proxy_groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_6050276(void)
{
	return DBcreate_index("hosts", "hosts_8", "proxy_groupid", 0);
}

static int	DBpatch_6050277(void)
{
	const zbx_db_field_t	field = {"proxy_groupid", NULL, "proxy_group", "proxy_groupid", 0, 0, 0, 0};

	return DBadd_foreign_key("hosts", 4, &field);
}

static int	DBpatch_6050278(void)
{
	const zbx_db_field_t	field = {"monitored_by", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("hosts", &field);
}

static int	DBpatch_6050279(void)
{
	const zbx_db_field_t	field = {"state", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("proxy_rtdata", &field);
}

static int	DBpatch_6050280(void)
{
	if (ZBX_DB_OK > zbx_db_execute("update hosts set monitored_by=1 where proxyid is not null"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050281(void)
{
	if (ZBX_DB_OK > zbx_db_execute("delete from profiles where idx='web.hosts.filter_monitored_by'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050282(void)
{
	const zbx_db_table_t	table = {"proxy_group_rtdata", "proxy_groupid", 0,
			{
				{"proxy_groupid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
				{"state", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
				{0}
			},
			NULL
		};

	return DBcreate_table(&table);
}

static int	DBpatch_6050283(void)
{
	const zbx_db_field_t	field = {"proxy_groupid", NULL, "proxy_group", "proxy_groupid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("proxy_group_rtdata", 1, &field);
}

static int	DBpatch_6050284(void)
{
	const zbx_db_field_t	field = {"software_update_checkid", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050285(void)
{
	const zbx_db_field_t	field = {"software_update_check_data", "", NULL, NULL, 0, ZBX_TYPE_TEXT,
			ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050286(void)
{
	const zbx_db_field_t	field = {"timeout_browser", "60s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_6050287(void)
{
	const zbx_db_field_t	field = {"timeout_browser", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("proxy", &field);
}

static int	DBpatch_6050288(void)
{
	const zbx_db_field_t	field = {"userdirectory_mediaid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, 0, 0};

	return DBadd_field("media", &field);
}

static int	DBpatch_6050289(void)
{
	return DBcreate_index("media", "media_3", "userdirectory_mediaid", 0);
}

static int	DBpatch_6050290(void)
{
	const zbx_db_field_t	field = {"userdirectory_mediaid", NULL, "userdirectory_media", "userdirectory_mediaid",
			0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("media", 3, &field);
}

static int	DBpatch_6050291(void)
{
	const zbx_db_field_t	field = {"active", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("userdirectory_media", &field);
}

static int	DBpatch_6050292(void)
{
	const zbx_db_field_t	field = {"severity", "63", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("userdirectory_media", &field);
}

static int	DBpatch_6050293(void)
{
	const zbx_db_field_t	field = {"period", "1-7,00:00-24:00", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("userdirectory_media", &field);
}

static int	DBpatch_6050294(void)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result = NULL;
	int		ret = SUCCEED;

	if (NULL == (result = zbx_db_select("select userdirectory_mediaid,userdirectoryid,mediatypeid"
			" from userdirectory_media")))
	{
		ret = FAIL;
		goto out;
	}

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_db_row_t	row2;
		zbx_db_result_t	result2;
		zbx_uint64_t	userdirectoryid, userdirectory_medeiaid, mediatypeid;

		ZBX_STR2UINT64(userdirectory_medeiaid, row[0]);
		ZBX_STR2UINT64(userdirectoryid, row[1]);
		ZBX_STR2UINT64(mediatypeid, row[2]);

		if (NULL == (result2 = zbx_db_select("select u.userid"
				" from userdirectory ud,users u"
				" where ud.userdirectoryid=" ZBX_FS_UI64 " and"
					" u.userdirectoryid=ud.userdirectoryid and"
					" ud.provision_status=1",
					userdirectoryid)))
		{
			ret = FAIL;
			goto out;
		}

		while (NULL != (row2 = zbx_db_fetch(result2)))
		{
			zbx_db_row_t	row3;
			zbx_db_result_t	result3;
			zbx_uint64_t	userid;
			char		*select_sql;

			ZBX_STR2UINT64(userid, row2[0]);
			select_sql = zbx_dsprintf(NULL, "select mediaid"
					" from media"
					" where userid=" ZBX_FS_UI64 " and"
						" mediatypeid=" ZBX_FS_UI64 " and"
						" userdirectory_mediaid is null"
					" order by mediaid", userid, mediatypeid);

			if (NULL == (result3 = zbx_db_select_n(select_sql, 1)))
			{
				ret = FAIL;
				zbx_free(select_sql);
				zbx_db_free_result(result2);
				goto out;
			}
			zbx_free(select_sql);

			while (NULL != (row3 = zbx_db_fetch(result3)))
			{
				zbx_uint64_t	mediaid;
				char		*update_sql;

				ZBX_STR2UINT64(mediaid, row3[0]);
				update_sql = zbx_dsprintf(NULL,
						"update media"
						" set userdirectory_mediaid=" ZBX_FS_UI64
						" where mediaid=" ZBX_FS_UI64 ";\n",
						userdirectory_medeiaid, mediaid);

				if (ZBX_DB_OK > zbx_db_execute("%s", update_sql))
				{
					ret = FAIL;
					zbx_free(update_sql);
					zbx_db_free_result(result3);
					zbx_db_free_result(result2);
					goto out;
				}
				zbx_free(update_sql);
			}

			zbx_db_free_result(result3);
		}

		zbx_db_free_result(result2);
	}
out:
	zbx_db_free_result(result);

	return ret;
}

static int	DBpatch_6050295(void)
{
	zbx_uint64_t	moduleid;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	moduleid = zbx_db_get_maxid("module");

	if (ZBX_DB_OK > zbx_db_execute("insert into module (moduleid,id,relative_path,status,config) values"
			" (" ZBX_FS_UI64 ",'itemnavigator','widgets/itemnavigator',%d,'[]')", moduleid, 1))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_6050296(void)
{
	const zbx_db_field_t	field = {"message_format", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBrename_field("media_type", "content_type", &field);
}

static int	DBpatch_6050297(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update widget set x=x*3,width=width*3"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050298(void)
{
	return add_widget_references("'geomap','map','plaintext','problemhosts','problems','problemsbysv','tophosts'"
			",'web'");
}

static int	DBpatch_6050299(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = SUCCEED;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = zbx_db_select(
			"select wf.widget_fieldid"
			" from widget w,widget_field wf"
			" where w.widgetid=wf.widgetid"
				" and w.type='plaintext'"
				" and wf.name='style'");

	while (SUCCEED == ret && NULL != (row = zbx_db_fetch(result)))
	{
		if (ZBX_DB_OK > zbx_db_execute("update widget_field set name='layout' where widget_fieldid=%s", row[0]))
			ret = FAIL;
	}
	zbx_db_free_result(result);

	return ret;
}

#define ZBX_WIDGET_FIELD_TYPE_INT32		(0)
#define ZBX_WIDGET_FIELD_TYPE_STR		(1)
#define ZBX_WIDGET_FIELD_TYPE_ITEM		(4)

static int	DBpatch_6050300(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_db_insert_t	db_insert;
	int		ret;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "widget_field", "widget_fieldid", "widgetid", "type", "name",
			"value_int", NULL);

	result = zbx_db_select("select widgetid from widget where type='plaintext'");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	widgetid;

		ZBX_STR2UINT64(widgetid, row[0]);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, ZBX_WIDGET_FIELD_TYPE_INT32,
				"show_timestamp", 1);
	}
	zbx_db_free_result(result);

	zbx_db_insert_autoincrement(&db_insert, "widget_fieldid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

static int	DBpatch_6050301(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_db_insert_t	db_insert_int, db_insert_str, db_insert_itemid;
	zbx_uint64_t	last_widgetid = 0;
	int		index, ret;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert_int, "widget_field", "widget_fieldid", "widgetid", "type", "name",
			"value_int", NULL);
	zbx_db_insert_prepare(&db_insert_str, "widget_field", "widget_fieldid", "widgetid", "type", "name",
			"value_str", NULL);
	zbx_db_insert_prepare(&db_insert_itemid, "widget_field", "widget_fieldid", "widgetid", "type", "name",
			"value_itemid", NULL);

	result = zbx_db_select(
			"select w.widgetid,i.value_type,i.itemid,irn.name_resolved,wfs.value_int"
			" from widget w"
				" join dashboard_page dp on w.dashboard_pageid=dp.dashboard_pageid"
				" join dashboard d on dp.dashboardid=d.dashboardid and d.templateid is null"
				" join widget_field wf on w.widgetid=wf.widgetid and wf.name like 'itemids%%'"
				" join items i on wf.value_itemid=i.itemid"
				" join item_rtname irn on wf.value_itemid=irn.itemid"
				" left join widget_field wfs on w.widgetid=wfs.widgetid and wfs.name='show_as_html'"
			" where w.type='plaintext'"
			" union all"
			" select w.widgetid,i.value_type,i.itemid,i.name,wfs.value_int"
			" from widget w"
				" join dashboard_page dp on w.dashboard_pageid=dp.dashboard_pageid"
				" join dashboard d on dp.dashboardid=d.dashboardid and d.templateid is not null"
				" join widget_field wf on w.widgetid=wf.widgetid and wf.name like 'itemids%%'"
				" join items i on wf.value_itemid=i.itemid"
				" left join widget_field wfs on w.widgetid=wfs.widgetid and wfs.name='show_as_html'"
			" where w.type='plaintext'"
			" order by widgetid");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	widgetid, itemid;
		int		value_type, show_as_html;
		const char	*item_name;
		char		buf[64];

		ZBX_STR2UINT64(widgetid, row[0]);
		value_type = atoi(row[1]);
		ZBX_STR2UINT64(itemid, row[2]);
		item_name = row[3];
		show_as_html = atoi(ZBX_NULL2EMPTY_STR(row[4]));

		if (widgetid != last_widgetid)
			index = 0;
		else
			index++;

		zbx_snprintf(buf, sizeof(buf), "columns.%d.name", index);
		zbx_db_insert_add_values(&db_insert_str, __UINT64_C(0), widgetid, ZBX_WIDGET_FIELD_TYPE_STR, buf,
				item_name);

		zbx_snprintf(buf, sizeof(buf), "columns.%d.itemid", index);
		zbx_db_insert_add_values(&db_insert_itemid, __UINT64_C(0), widgetid, ZBX_WIDGET_FIELD_TYPE_ITEM, buf,
				itemid);

		/* 1 - ITEM_VALUE_TYPE_STR, 2 - ITEM_VALUE_TYPE_LOG, 4 - ITEM_VALUE_TYPE_TEXT */
		if (1 == value_type || 2 == value_type || 4 == value_type)
		{
			zbx_snprintf(buf, sizeof(buf), "columns.%d.monospace_font", index);
			zbx_db_insert_add_values(&db_insert_int, __UINT64_C(0), widgetid, ZBX_WIDGET_FIELD_TYPE_INT32,
					buf, 1);

			if (1 == show_as_html)
			{
				zbx_snprintf(buf, sizeof(buf), "columns.%d.display", index);
				zbx_db_insert_add_values(&db_insert_int, __UINT64_C(0), widgetid,
						ZBX_WIDGET_FIELD_TYPE_INT32, buf, 4);
			}
		}

		last_widgetid = widgetid;
	}
	zbx_db_free_result(result);

	zbx_db_insert_autoincrement(&db_insert_int, "widget_fieldid");
	ret = zbx_db_insert_execute(&db_insert_int);

	if (SUCCEED == ret)
	{
		zbx_db_insert_autoincrement(&db_insert_str, "widget_fieldid");
		ret = zbx_db_insert_execute(&db_insert_str);
	}

	if (SUCCEED == ret)
	{
		zbx_db_insert_autoincrement(&db_insert_itemid, "widget_fieldid");
		ret = zbx_db_insert_execute(&db_insert_itemid);
	}

	zbx_db_insert_clean(&db_insert_int);
	zbx_db_insert_clean(&db_insert_str);
	zbx_db_insert_clean(&db_insert_itemid);

	return ret;
}

static int	DBpatch_6050302(void)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	ids;
	int			ret = SUCCEED;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_vector_uint64_create(&ids);

	result = zbx_db_select(
			"select wf.widget_fieldid"
			" from widget w,widget_field wf"
			" where w.widgetid=wf.widgetid"
				" and w.type='plaintext'"
				" and (wf.name='show_as_html' or wf.name like 'itemids%%')");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	widget_fieldid;

		ZBX_STR2UINT64(widget_fieldid, row[0]);

		zbx_vector_uint64_append(&ids, widget_fieldid);
	}
	zbx_db_free_result(result);

	if (0 != ids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from widget_field where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "widget_fieldid", ids.values, ids.values_num);

		if (ZBX_DB_OK > zbx_db_execute("%s", sql))
			ret = FAIL;

		zbx_free(sql);
	}

	zbx_vector_uint64_destroy(&ids);

	return ret;
}

static int	DBpatch_6050303(void)
{
	const char	*sql =
			"update module"
			" set id='itemhistory',relative_path='widgets/itemhistory'"
			" where id='plaintext'";

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("%s", sql))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050304(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute("update widget set type='itemhistory' where type='plaintext'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_6050305(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_db_insert_t	db_insert;
	int		ret;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	zbx_db_insert_prepare(&db_insert, "widget_field", "widget_fieldid", "widgetid", "type", "name",
			"value_str", NULL);

	result = zbx_db_select("select widgetid from widget where type='itemhistory'");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	widgetid;

		ZBX_STR2UINT64(widgetid, row[0]);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, ZBX_WIDGET_FIELD_TYPE_STR,
				"time_period.from", "now-1y");
		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), widgetid, ZBX_WIDGET_FIELD_TYPE_STR,
				"time_period.to", "now");
	}
	zbx_db_free_result(result);

	zbx_db_insert_autoincrement(&db_insert, "widget_fieldid");
	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

#undef ZBX_WIDGET_FIELD_TYPE_ITEM
#undef ZBX_WIDGET_FIELD_TYPE_STR
#undef ZBX_WIDGET_FIELD_TYPE_INT32

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
DBPATCH_ADD(6050144, 0, 1)
DBPATCH_ADD(6050145, 0, 1)
DBPATCH_ADD(6050146, 0, 1)
DBPATCH_ADD(6050147, 0, 1)
DBPATCH_ADD(6050148, 0, 1)
DBPATCH_ADD(6050149, 0, 1)
DBPATCH_ADD(6050150, 0, 1)
DBPATCH_ADD(6050151, 0, 1)
DBPATCH_ADD(6050152, 0, 1)
DBPATCH_ADD(6050153, 0, 1)
DBPATCH_ADD(6050154, 0, 1)
DBPATCH_ADD(6050155, 0, 1)
DBPATCH_ADD(6050156, 0, 1)
DBPATCH_ADD(6050157, 0, 1)
DBPATCH_ADD(6050158, 0, 1)
DBPATCH_ADD(6050159, 0, 1)
DBPATCH_ADD(6050160, 0, 1)
DBPATCH_ADD(6050161, 0, 1)
DBPATCH_ADD(6050162, 0, 1)
DBPATCH_ADD(6050163, 0, 1)
DBPATCH_ADD(6050164, 0, 1)
DBPATCH_ADD(6050165, 0, 1)
DBPATCH_ADD(6050166, 0, 1)
DBPATCH_ADD(6050167, 0, 1)
DBPATCH_ADD(6050168, 0, 1)
DBPATCH_ADD(6050169, 0, 1)
DBPATCH_ADD(6050170, 0, 1)
DBPATCH_ADD(6050171, 0, 1)
DBPATCH_ADD(6050172, 0, 1)
DBPATCH_ADD(6050173, 0, 1)
DBPATCH_ADD(6050174, 0, 1)
DBPATCH_ADD(6050175, 0, 1)
DBPATCH_ADD(6050176, 0, 1)
DBPATCH_ADD(6050177, 0, 1)
DBPATCH_ADD(6050178, 0, 1)
DBPATCH_ADD(6050179, 0, 1)
DBPATCH_ADD(6050180, 0, 1)
DBPATCH_ADD(6050181, 0, 1)
DBPATCH_ADD(6050182, 0, 1)
DBPATCH_ADD(6050183, 0, 1)
DBPATCH_ADD(6050184, 0, 1)
DBPATCH_ADD(6050185, 0, 1)
DBPATCH_ADD(6050186, 0, 1)
DBPATCH_ADD(6050187, 0, 1)
DBPATCH_ADD(6050188, 0, 1)
DBPATCH_ADD(6050189, 0, 1)
DBPATCH_ADD(6050190, 0, 1)
DBPATCH_ADD(6050191, 0, 1)
DBPATCH_ADD(6050192, 0, 1)
DBPATCH_ADD(6050193, 0, 1)
DBPATCH_ADD(6050194, 0, 1)
DBPATCH_ADD(6050195, 0, 1)
DBPATCH_ADD(6050196, 0, 1)
DBPATCH_ADD(6050197, 0, 1)
DBPATCH_ADD(6050198, 0, 1)
DBPATCH_ADD(6050199, 0, 1)
DBPATCH_ADD(6050200, 0, 1)
DBPATCH_ADD(6050201, 0, 1)
DBPATCH_ADD(6050202, 0, 1)
DBPATCH_ADD(6050203, 0, 1)
DBPATCH_ADD(6050204, 0, 1)
DBPATCH_ADD(6050205, 0, 1)
DBPATCH_ADD(6050206, 0, 1)
DBPATCH_ADD(6050207, 0, 1)
DBPATCH_ADD(6050208, 0, 1)
DBPATCH_ADD(6050209, 0, 1)
DBPATCH_ADD(6050210, 0, 1)
DBPATCH_ADD(6050211, 0, 1)
DBPATCH_ADD(6050212, 0, 1)
DBPATCH_ADD(6050213, 0, 1)
DBPATCH_ADD(6050214, 0, 1)
DBPATCH_ADD(6050215, 0, 1)
DBPATCH_ADD(6050216, 0, 1)
DBPATCH_ADD(6050217, 0, 1)
DBPATCH_ADD(6050218, 0, 1)
DBPATCH_ADD(6050219, 0, 1)
DBPATCH_ADD(6050220, 0, 1)
DBPATCH_ADD(6050221, 0, 1)
DBPATCH_ADD(6050222, 0, 1)
DBPATCH_ADD(6050223, 0, 1)
DBPATCH_ADD(6050224, 0, 1)
DBPATCH_ADD(6050225, 0, 1)
DBPATCH_ADD(6050226, 0, 1)
DBPATCH_ADD(6050227, 0, 1)
DBPATCH_ADD(6050228, 0, 1)
DBPATCH_ADD(6050229, 0, 1)
DBPATCH_ADD(6050230, 0, 1)
DBPATCH_ADD(6050231, 0, 1)
DBPATCH_ADD(6050232, 0, 1)
DBPATCH_ADD(6050233, 0, 1)
DBPATCH_ADD(6050234, 0, 1)
DBPATCH_ADD(6050235, 0, 1)
DBPATCH_ADD(6050236, 0, 1)
DBPATCH_ADD(6050237, 0, 1)
DBPATCH_ADD(6050238, 0, 1)
DBPATCH_ADD(6050239, 0, 1)
DBPATCH_ADD(6050240, 0, 1)
DBPATCH_ADD(6050241, 0, 1)
DBPATCH_ADD(6050242, 0, 1)
DBPATCH_ADD(6050243, 0, 1)
DBPATCH_ADD(6050244, 0, 1)
DBPATCH_ADD(6050245, 0, 1)
DBPATCH_ADD(6050246, 0, 1)
DBPATCH_ADD(6050247, 0, 1)
DBPATCH_ADD(6050248, 0, 1)
DBPATCH_ADD(6050249, 0, 1)
DBPATCH_ADD(6050250, 0, 1)
DBPATCH_ADD(6050251, 0, 1)
DBPATCH_ADD(6050252, 0, 1)
DBPATCH_ADD(6050253, 0, 1)
DBPATCH_ADD(6050254, 0, 1)
DBPATCH_ADD(6050255, 0, 1)
DBPATCH_ADD(6050256, 0, 1)
DBPATCH_ADD(6050257, 0, 1)
DBPATCH_ADD(6050258, 0, 1)
DBPATCH_ADD(6050259, 0, 1)
DBPATCH_ADD(6050260, 0, 1)
DBPATCH_ADD(6050261, 0, 1)
DBPATCH_ADD(6050262, 0, 1)
DBPATCH_ADD(6050263, 0, 1)
DBPATCH_ADD(6050264, 0, 1)
DBPATCH_ADD(6050265, 0, 1)
DBPATCH_ADD(6050266, 0, 1)
DBPATCH_ADD(6050267, 0, 1)
DBPATCH_ADD(6050268, 0, 1)
DBPATCH_ADD(6050269, 0, 1)
DBPATCH_ADD(6050270, 0, 1)
DBPATCH_ADD(6050271, 0, 1)
DBPATCH_ADD(6050272, 0, 1)
DBPATCH_ADD(6050273, 0, 1)
DBPATCH_ADD(6050274, 0, 1)
DBPATCH_ADD(6050275, 0, 1)
DBPATCH_ADD(6050276, 0, 1)
DBPATCH_ADD(6050277, 0, 1)
DBPATCH_ADD(6050278, 0, 1)
DBPATCH_ADD(6050279, 0, 1)
DBPATCH_ADD(6050280, 0, 1)
DBPATCH_ADD(6050281, 0, 1)
DBPATCH_ADD(6050282, 0, 1)
DBPATCH_ADD(6050283, 0, 1)
DBPATCH_ADD(6050284, 0, 1)
DBPATCH_ADD(6050285, 0, 1)
DBPATCH_ADD(6050286, 0, 1)
DBPATCH_ADD(6050287, 0, 1)
DBPATCH_ADD(6050288, 0, 1)
DBPATCH_ADD(6050289, 0, 1)
DBPATCH_ADD(6050290, 0, 1)
DBPATCH_ADD(6050291, 0, 1)
DBPATCH_ADD(6050292, 0, 1)
DBPATCH_ADD(6050293, 0, 1)
DBPATCH_ADD(6050294, 0, 1)
DBPATCH_ADD(6050295, 0, 1)
DBPATCH_ADD(6050296, 0, 1)
DBPATCH_ADD(6050297, 0, 1)
DBPATCH_ADD(6050298, 0, 1)
DBPATCH_ADD(6050299, 0, 1)
DBPATCH_ADD(6050300, 0, 1)
DBPATCH_ADD(6050301, 0, 1)
DBPATCH_ADD(6050302, 0, 1)
DBPATCH_ADD(6050303, 0, 1)
DBPATCH_ADD(6050304, 0, 1)
DBPATCH_ADD(6050305, 0, 1)

DBPATCH_END()
