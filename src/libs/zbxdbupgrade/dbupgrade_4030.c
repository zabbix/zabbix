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

#include "zbxdb.h"
#include "zbxdbschema.h"
#include "zbxstr.h"
#include "zbx_host_constants.h"

/*
 * 4.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_4030000(void)
{
	const zbx_db_field_t	field = {"host", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("autoreg_host", &field, NULL);
}

static int	DBpatch_4030001(void)
{
	const zbx_db_field_t	field = {"host", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("proxy_autoreg_host", &field, NULL);
}

static int	DBpatch_4030002(void)
{
	const zbx_db_field_t	field = {"host", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_discovery", &field, NULL);
}

static int	DBpatch_4030003(void)
{
	const zbx_db_table_t	table =
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
	const zbx_db_field_t	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("item_rtdata", 1, &field);
}

static int	DBpatch_4030005(void)
{
	if (ZBX_DB_OK <= zbx_db_execute("insert into item_rtdata (itemid,lastlogsize,state,mtime,error)"
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
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* 8 - SCREEN_RESOURCE_SCREEN */
	if (ZBX_DB_OK > zbx_db_execute("delete from screens_items where resourcetype=8"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_4030012(void)
{
	const zbx_db_field_t	field = {"flags", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("autoreg_host", &field);
}

static int	DBpatch_4030013(void)
{
	const zbx_db_field_t	field = {"flags", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("proxy_autoreg_host", &field);
}

static int	DBpatch_4030014(void)
{
	const zbx_db_field_t	field = {"view_mode", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("widget", &field);
}

static int	DBpatch_4030015(void)
{
	if (ZBX_DB_OK > zbx_db_execute("update widget set x=x*2, width=width*2"))
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

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; i < (int)ARRSIZE(values); i++)
	{
		if (ZBX_DB_OK > zbx_db_execute(
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
	const zbx_db_field_t	field = {"opdata", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

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

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	for (i = 0; i < (int)ARRSIZE(values); i += 2)
	{
		if (ZBX_DB_OK > zbx_db_execute("update profiles set idx='%s' where idx='%s'", values[i + 1], values[i]))
			return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_4030019(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
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
	zbx_db_row_t	row;
	zbx_db_result_t	result;
	zbx_uint32_t	id, next_id = 0;
	zbx_uint64_t	last_widgetid = 0, widgetid, fieldid;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	result = zbx_db_select("SELECT widgetid,widget_fieldid,name,value_str"
			" FROM widget_field"
			" WHERE widgetid IN (SELECT widgetid FROM widget WHERE type='svggraph') AND type=1"
			" AND (name LIKE 'ds.hosts.%%' OR name LIKE 'ds.items.%%' OR name LIKE 'or.hosts.%%'"
				" OR name LIKE 'or.items.%%' OR name LIKE 'problemhosts.%%')"
			" ORDER BY widgetid, name");

	if (NULL == result)
		return FAIL;

	while (SUCCEED == ret && NULL != (row = zbx_db_fetch(result)))
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
				token_esc = zbx_db_dyn_escape_string(token);

				if (ZBX_DB_OK > zbx_db_execute("insert into widget_field (widgetid,widget_fieldid,type,name,"
						"value_str) values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",1,'%s.%u','%s')",
						widgetid, zbx_db_get_maxid_num("widget_field", 1), field, next_id,
						token_esc) ||
						ZBX_DB_OK > zbx_db_execute("delete from widget_field where widget_fieldid="
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
	zbx_db_free_result(result);

#undef FIELD_LEN
	return ret;
}

static int	DBpatch_4030021(void)
{
	const zbx_db_field_t	field = {"autoreg_tls_accept", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("config", &field);
}

static int	DBpatch_4030022(void)
{
	const zbx_db_field_t	field = {"tls_accepted", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("autoreg_host", &field);
}

static int	DBpatch_4030023(void)
{
	const zbx_db_field_t	field = {"tls_accepted", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("proxy_autoreg_host", &field);
}

static int	DBpatch_4030024(void)
{
	const zbx_db_table_t	table =
			{"config_autoreg_tls", "autoreg_tlsid", 0,
				{
					{"autoreg_tlsid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"tls_psk_identity", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"tls_psk", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_4030025(void)
{
	return DBcreate_index("config_autoreg_tls", "config_autoreg_tls_1", "tls_psk_identity", 1);
}

static int	DBpatch_4030026(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK <= zbx_db_execute("insert into config_autoreg_tls (autoreg_tlsid) values (1)"))
		return SUCCEED;

	return FAIL;
}

static int	DBpatch_4030027(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = FAIL;
	char		*exec_params = NULL, *exec_params_esc;
	size_t		exec_params_alloc = 0;

	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	/* type : 1 - MEDIA_TYPE_EXEC, 3 - MEDIA_TYPE_JABBER, 100 - MEDIA_TYPE_EZ_TEXTING */
	result = zbx_db_select("select mediatypeid,type,username,passwd,exec_path from media_type where type in (3,100)");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		size_t	exec_params_offset = 0;

		zbx_snprintf_alloc(&exec_params, &exec_params_alloc, &exec_params_offset,
			"-username\n%s\n-password\n%s\n", row[2], row[3]);

		if (100 == atoi(row[1]))
		{
			zbx_snprintf_alloc(&exec_params, &exec_params_alloc, &exec_params_offset, "-size\n%d\n",
				0 == atoi(row[4]) ? 160 : 136);
		}

		exec_params_esc = zbx_db_dyn_escape_string_len(exec_params, 255);

		if (ZBX_DB_OK > zbx_db_execute("update media_type"
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
	zbx_db_free_result(result);
	zbx_free(exec_params);

	return ret;
}

static int	DBpatch_4030028(void)
{
	return SUCCEED;
}

static int	DBpatch_4030029(void)
{
	const zbx_db_field_t	field = {"name", "", NULL, NULL, 100, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBrename_field("media_type", "description", &field);
}

static int	DBpatch_4030030(void)
{
	return SUCCEED;
}

static int	DBpatch_4030031(void)
{
	if (0 == (DBget_program_type() & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (ZBX_DB_OK > zbx_db_execute(
			"update profiles"
			" set value_str='name'"
			" where value_str='description'"
				" and idx='web.media_types.php.sort'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

/* skip patches that altered table instead of creating new one and copying contents as this fails on newer MariaDB */
static int	DBpatch_4030032(void)
{
#ifdef HAVE_MYSQL
	return SUCCEED;
#else
	const zbx_db_field_t	field = {"name", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_inventory", &field, NULL);
#endif
}

static int	DBpatch_4030033(void)
{
#ifdef HAVE_MYSQL
	return SUCCEED;
#else
	const zbx_db_field_t	field = {"alias", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_inventory", &field, NULL);
#endif
}

static int	DBpatch_4030034(void)
{
#ifdef HAVE_MYSQL
	return SUCCEED;
#else
	const zbx_db_field_t	field = {"os", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_inventory", &field, NULL);
#endif
}

static int	DBpatch_4030035(void)
{
#ifdef HAVE_MYSQL
	return SUCCEED;
#else
	const zbx_db_field_t	field = {"os_short", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("host_inventory", &field, NULL);
#endif
}

static int	DBpatch_4030036(void)
{
#ifdef HAVE_MYSQL
	return DBdrop_foreign_key("host_inventory", 1);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_4030037(void)
{
#ifdef HAVE_MYSQL
	return DBrename_table("host_inventory", "host_inventory_tmp");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_4030038(void)
{
#ifdef HAVE_MYSQL
	/* Store columns on overflow pages to respect row size limit of 8126 bytes on MariaDB. */
	/* Columns can only be stored on overflow pages if they are 256 bytes or longer.       */
	const zbx_db_table_t	table =
			{"host_inventory", "hostid", 0,
				{
					{"hostid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"inventory_mode", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"type", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"type_full", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"alias", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"os", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"os_full", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"os_short", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"serialno_a", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"serialno_b", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"tag", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"asset_tag", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"macaddress_a", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"macaddress_b", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"hardware", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"hardware_full", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"software", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"software_full", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"software_app_a", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"software_app_b", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"software_app_c", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"software_app_d", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"software_app_e", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"contact", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"location", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"location_lat", "", NULL, NULL, 16, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"location_lon", "", NULL, NULL, 16, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"notes", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"chassis", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"model", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"hw_arch", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"vendor", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"contract_number", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"installer_name", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"deployment_status", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"url_a", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"url_b", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"url_c", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"host_networks", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"host_netmask", "", NULL, NULL, 39, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"host_router", "", NULL, NULL, 39, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"oob_ip", "", NULL, NULL, 39, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"oob_netmask", "", NULL, NULL, 39, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"oob_router", "", NULL, NULL, 39, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"date_hw_purchase", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"date_hw_install", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"date_hw_expiry", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"date_hw_decomm", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"site_address_a", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"site_address_b", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"site_address_c", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"site_city", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"site_state", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"site_country", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"site_zip", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"site_rack", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"site_notes", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"poc_1_name", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"poc_1_email", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"poc_1_phone_a", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"poc_1_phone_b", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"poc_1_cell", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"poc_1_screen", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"poc_1_notes", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{"poc_2_name", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"poc_2_email", "", NULL, NULL, 128, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"poc_2_phone_a", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"poc_2_phone_b", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"poc_2_cell", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"poc_2_screen", "", NULL, NULL, 64, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"poc_2_notes", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_4030039(void)
{
#ifdef HAVE_MYSQL
	if (ZBX_DB_OK <= zbx_db_execute(
			"insert into host_inventory (select * from host_inventory_tmp)"))
	{
		return SUCCEED;
	}

	return FAIL;
#else
	return SUCCEED;
#endif
}

static int	DBpatch_4030040(void)
{
#ifdef HAVE_MYSQL
	return DBdrop_table("host_inventory_tmp");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_4030041(void)
{
#ifdef HAVE_MYSQL
	const zbx_db_field_t	field = {"hostid", NULL, "hosts", "hostid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("host_inventory", 1, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_4030042(void)
{
	const zbx_db_field_t	field = {"script", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_4030043(void)
{
	const zbx_db_field_t	field = {"timeout", "30s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_4030044(void)
{
	const zbx_db_field_t	field = {"process_tags", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_4030045(void)
{
	const zbx_db_field_t	field = {"show_event_menu", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_4030046(void)
{
	const zbx_db_field_t	field = {"event_menu_url", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_4030047(void)
{
	const zbx_db_field_t	field = {"event_menu_name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_4030048(void)
{
	const zbx_db_field_t	field = {"description", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("media_type", &field);
}

static int	DBpatch_4030049(void)
{
	const zbx_db_table_t	table =
			{"media_type_param", "mediatype_paramid", 0,
				{
					{"mediatype_paramid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"mediatypeid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"name", "", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{"value", "", NULL, NULL, 2048, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_4030050(void)
{
	return DBcreate_index("media_type_param", "media_type_param_1", "mediatypeid", 0);
}

static int	DBpatch_4030051(void)
{
	const zbx_db_field_t	field = {"mediatypeid", NULL, "media_type", "mediatypeid", 0, 0, 0,
			ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("media_type_param", 1, &field);
}

static int	DBpatch_4030052(void)
{
	const zbx_db_field_t	field = {"parameters", "{}", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("alerts", &field);
}

static int	DBpatch_4030053(void)
{
	const zbx_db_field_t	field =  {"type", "1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("interface", &field);
}

static int	DBpatch_4030054(void)
{
	const zbx_db_field_t	field = {"description", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("globalmacro", &field);
}

static int	DBpatch_4030055(void)
{
	const zbx_db_field_t	field = {"description", "", NULL, NULL, 0, ZBX_TYPE_TEXT, ZBX_NOTNULL, 0};

	return DBadd_field("hostmacro", &field);
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
DBPATCH_ADD(4030022, 0, 1)
DBPATCH_ADD(4030023, 0, 1)
DBPATCH_ADD(4030024, 0, 1)
DBPATCH_ADD(4030025, 0, 1)
DBPATCH_ADD(4030026, 0, 1)
DBPATCH_ADD(4030027, 0, 1)
DBPATCH_ADD(4030028, 0, 1)
DBPATCH_ADD(4030029, 0, 1)
DBPATCH_ADD(4030030, 0, 1)
DBPATCH_ADD(4030031, 0, 1)
DBPATCH_ADD(4030032, 0, 1)
DBPATCH_ADD(4030033, 0, 1)
DBPATCH_ADD(4030034, 0, 1)
DBPATCH_ADD(4030035, 0, 1)
DBPATCH_ADD(4030036, 0, 1)
DBPATCH_ADD(4030037, 0, 1)
DBPATCH_ADD(4030038, 0, 1)
DBPATCH_ADD(4030039, 0, 1)
DBPATCH_ADD(4030040, 0, 1)
DBPATCH_ADD(4030041, 0, 1)
DBPATCH_ADD(4030042, 0, 1)
DBPATCH_ADD(4030043, 0, 1)
DBPATCH_ADD(4030044, 0, 1)
DBPATCH_ADD(4030045, 0, 1)
DBPATCH_ADD(4030046, 0, 1)
DBPATCH_ADD(4030047, 0, 1)
DBPATCH_ADD(4030048, 0, 1)
DBPATCH_ADD(4030049, 0, 1)
DBPATCH_ADD(4030050, 0, 1)
DBPATCH_ADD(4030051, 0, 1)
DBPATCH_ADD(4030052, 0, 1)
DBPATCH_ADD(4030053, 0, 1)
DBPATCH_ADD(4030054, 0, 1)
DBPATCH_ADD(4030055, 0, 1)

DBPATCH_END()
