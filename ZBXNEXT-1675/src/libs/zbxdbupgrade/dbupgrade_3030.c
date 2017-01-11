/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * 3.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_3030000(void)
{
	const ZBX_FIELD	field = {"ipmi_authtype", "-1", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0};

	return DBset_default("hosts", &field);
}

static int	DBpatch_3030001(void)
{
	const ZBX_FIELD field = {"snmp_oid", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field);
}

static int	DBpatch_3030002(void)
{
	const ZBX_FIELD field = {"key_", "", NULL, NULL, 512, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("dchecks", &field);
}

static int	DBpatch_3030003(void)
{
	return DBdrop_field("proxy_dhistory", "type");
}

static int	DBpatch_3030004(void)
{
	return DBdrop_field("proxy_dhistory", "key_");
}

static int	DBpatch_3030005(void)
{
	return DBdrop_foreign_key("dservices", 2);
}

static int	DBpatch_3030006(void)
{
	return DBdrop_index("dservices", "dservices_1");
}

static int	DBpatch_3030007(void)
{
	DB_ROW			row;
	DB_RESULT		result;
	zbx_vector_uint64_t	dserviceids;
	zbx_uint64_t		dserviceid;
	int			ret = SUCCEED;

	zbx_vector_uint64_create(&dserviceids);

	/* After dropping fields type and key_ from table dservices there is no guarantee that a unique
	index with fields dcheckid, ip and port can be created. To create a unique index for the same
	fields later this will delete rows where all three of them are identical only leaving the latest. */
	result = DBselect(
			"select ds.dserviceid"
			" from dservices ds"
			" where not exists ("
				"select null"
				" from dchecks dc"
				" where ds.dcheckid = dc.dcheckid"
					" and ds.type = dc.type"
					" and ds.key_ = dc.key_"
			")");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(dserviceid, row[0]);

		zbx_vector_uint64_append(&dserviceids, dserviceid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&dserviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != dserviceids.values_num)
		ret = DBexecute_multiple_query("delete from dservices where", "dserviceid", &dserviceids);

	zbx_vector_uint64_destroy(&dserviceids);

	return ret;
}

static int	DBpatch_3030008(void)
{
	return DBdrop_field("dservices", "type");
}

static int	DBpatch_3030009(void)
{
	return DBdrop_field("dservices", "key_");
}

static int	DBpatch_3030010(void)
{
	return DBcreate_index("dservices", "dservices_1", "dcheckid,ip,port", 1);
}

static int	DBpatch_3030011(void)
{
	const ZBX_FIELD	field = {"dcheckid", NULL, "dchecks", "dcheckid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("dservices", 2, &field);
}

static int	DBpatch_3030012(void)
{
	const ZBX_FIELD field = {"snmp_lastsize", "0", NULL, NULL, 0, ZBX_TYPE_UINT, ZBX_NOTNULL, 0};

	return DBmodify_field_type("globalvars", &field);
}

static int	DBpatch_3030013(void)
{
	const ZBX_FIELD field = {"period", "1-7,00:00-24:00", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("media", &field);
}

static void	DBpatch_conv_day(int *value, char *suffix)
{
	if (0 != *value && 0 == *value % 7)
	{
		*value /= 7;
		*suffix = 'w';
	}
	else
		*suffix = 'd';
}

static void	DBpatch_conv_sec(int *value, char *suffix)
{
	const int	factors[] = {60, 60, 24, 7, 0}, *factor = factors;
	const char	suffixes[] = "s" "m" "h""d""w";

	if (0 != *value)
	{
		while (0 != *factor && 0 == *value % *factor)
			*value /= *factor++;
	}

	*suffix = suffixes[factor - factors];
}

typedef struct
{
	const char	*field;
	void		(*conv_func)(int *value, char *suffix);
}
DBpatch_field_conv_t;

static int	DBpatch_table_convert(const char *table, const char *recid, const DBpatch_field_conv_t *field_convs)
{
	const DBpatch_field_conv_t	*fc;
	DB_RESULT			result;
	DB_ROW				row;
	char				suffix, *sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	int				value, i, ret = FAIL;

	for (fc = field_convs; NULL != fc->field; fc++)
	{
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ',');
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, fc->field);
	}

	result = DBselect("select %s%s from %s", recid, sql, table);

	sql_offset = 0;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ", table);

		for (i = 1, fc = field_convs; NULL != fc->field; i++, fc++)
		{
			value = atoi(row[i]);
			fc->conv_func(&value, &suffix);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s%s='%d%c'",
					(1 == i ? "" : ","), fc->field, value, suffix);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where %s=%s;\n", recid, row[0]);

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	ret = SUCCEED;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_3030015(void)
{
	const ZBX_FIELD field = {"autologout", "15m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("users", &field);
}

static int	DBpatch_3030016(void)
{
	const ZBX_FIELD field = {"autologout", "15m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("users", &field);
}

static int	DBpatch_3030017(void)
{
	const ZBX_FIELD field = {"refresh", "30s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("users", &field);
}

static int	DBpatch_3030018(void)
{
	const ZBX_FIELD field = {"refresh", "30s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("users", &field);
}

static int	DBpatch_3030019(void)
{
	const DBpatch_field_conv_t	field_convs[] = {
						{"autologout",	DBpatch_conv_sec},
						{"refresh",	DBpatch_conv_sec},
						{NULL}
					};

	return DBpatch_table_convert("users", "userid", field_convs);
}

static int	DBpatch_3030020(void)
{
	const ZBX_FIELD field = {"delay", "1h", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBmodify_field_type("drules", &field);
}

static int	DBpatch_3030021(void)
{
	const ZBX_FIELD field = {"delay", "1h", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBset_default("drules", &field);
}

static int	DBpatch_3030022(void)
{
	const DBpatch_field_conv_t	field_convs[] = {{"delay", DBpatch_conv_sec}, {NULL}};

	return DBpatch_table_convert("drules", "druleid", field_convs);
}

static int	DBpatch_3030023(void)
{
	const ZBX_FIELD field = {"delay", "1m", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBmodify_field_type("httptest", &field);
}

static int	DBpatch_3030024(void)
{
	const ZBX_FIELD field = {"delay", "1m", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBset_default("httptest", &field);
}

static int	DBpatch_3030025(void)
{
	const DBpatch_field_conv_t	field_convs[] = {{"delay", DBpatch_conv_sec}, {NULL}};

	return DBpatch_table_convert("httptest", "httptestid", field_convs);
}

static int	DBpatch_3030026(void)
{
	const ZBX_FIELD field = {"delay", "0s", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBmodify_field_type("items", &field);
}

static int	DBpatch_3030027(void)
{
	const ZBX_FIELD field = {"delay", "0s", NULL, NULL, 1024, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBset_default("items", &field);
}

static int	DBpatch_3030028(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	const char	*delay_flex, *next;
	char		suffix, *sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	int		delay, ret = FAIL;

	result = DBselect("select itemid,delay,delay_flex from items");

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (row = DBfetch(result)))
	{
		delay = atoi(row[1]);
		DBpatch_conv_sec(&delay, &suffix);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set delay='%d%c", delay, suffix);

		for (delay_flex = row[2]; '\0' != *delay_flex; delay_flex = next + 1)
		{
			zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ';');

			if (0 != isdigit(*delay_flex) && NULL != (next = strchr(delay_flex, '/')))	/* flexible */
			{
				delay = atoi(delay_flex);
				DBpatch_conv_sec(&delay, &suffix);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%d%c", delay, suffix);
				delay_flex = next;
			}

			if (NULL == (next = strchr(delay_flex, ';')))
			{
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, delay_flex);
				break;
			}

			zbx_strncpy_alloc(&sql, &sql_alloc, &sql_offset, delay_flex, next - delay_flex);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "' where itemid=%s;\n", row[0]);

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	ret = SUCCEED;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_3030029(void)
{
	return DBdrop_field("items", "delay_flex");
}

static int	DBpatch_3030030(void)
{
	const ZBX_FIELD field = {"history", "90d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field);
}

static int	DBpatch_3030031(void)
{
	const ZBX_FIELD field = {"history", "90d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("items", &field);
}

static int	DBpatch_3030032(void)
{
	const ZBX_FIELD field = {"trends", "365d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field);
}

static int	DBpatch_3030033(void)
{
	const ZBX_FIELD field = {"trends", "365d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("items", &field);
}

static int	DBpatch_3030034(void)
{
	const DBpatch_field_conv_t	field_convs[] = {
						{"history",	DBpatch_conv_day},
						{"trends",	DBpatch_conv_day},
						{NULL}
					};

	return DBpatch_table_convert("items", "itemid", field_convs);
}

static int	DBpatch_3030035(void)
{
	const ZBX_FIELD field = {"lifetime", "30d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("items", &field);
}

static int	DBpatch_3030036(void)
{
	const ZBX_FIELD field = {"lifetime", "30d", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("items", &field);
}

static int	DBpatch_3030037(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		suffix, *sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;
	int		value, ret = FAIL;

	result = DBselect("select itemid,lifetime from items");

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update items set lifetime='");

		if (0 != isdigit(*row[1]))
		{
			value = atoi(row[1]);
			DBpatch_conv_sec(&value, &suffix);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%d%c", value, suffix);
		}
		else	/* items.lifetime may be a macro, in such case simply append days unit */
		{
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, row[1]);
			zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, 'd');
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "' where itemid=%s;\n", row[0]);

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
			goto out;
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto out;
	}

	ret = SUCCEED;
out:
	DBfree_result(result);
	zbx_free(sql);

	return ret;
}

static int	DBpatch_3030038(void)
{
	const ZBX_FIELD field = {"esc_period", "0s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("actions", &field);
}

static int	DBpatch_3030039(void)
{
	const ZBX_FIELD field = {"esc_period", "0s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("actions", &field);
}

static int	DBpatch_3030040(void)
{
	const DBpatch_field_conv_t	field_convs[] = {{"esc_period", DBpatch_conv_sec}, {NULL}};

	return DBpatch_table_convert("actions", "actionid", field_convs);
}

static int	DBpatch_3030041(void)
{
	const ZBX_FIELD field = {"esc_period", "0s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("operations", &field);
}

static int	DBpatch_3030042(void)
{
	const ZBX_FIELD field = {"esc_period", "0s", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("operations", &field);
}

static int	DBpatch_3030043(void)
{
	const DBpatch_field_conv_t	field_convs[] = {{"esc_period", DBpatch_conv_sec}, {NULL}};

	return DBpatch_table_convert("operations", "operationid", field_convs);
}

static int	DBpatch_3030044(void)
{
	const ZBX_FIELD field = {"refresh_unsupported", "0s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030045(void)
{
	const ZBX_FIELD field = {"refresh_unsupported", "0s", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL | ZBX_PROXY, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030046(void)
{
	const ZBX_FIELD field = {"work_period", "1-5,00:00-24:00", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030047(void)
{
	const ZBX_FIELD field = {"event_expire", "1w", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030048(void)
{
	const ZBX_FIELD field = {"event_expire", "1w", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030049(void)
{
	const ZBX_FIELD field = {"ok_period", "30m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030050(void)
{
	const ZBX_FIELD field = {"ok_period", "30m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030051(void)
{
	const ZBX_FIELD field = {"blink_period", "30m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030052(void)
{
	const ZBX_FIELD field = {"blink_period", "30m", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030053(void)
{
	const ZBX_FIELD field = {"hk_events_trigger", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030054(void)
{
	const ZBX_FIELD field = {"hk_events_trigger", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030055(void)
{
	const ZBX_FIELD field = {"hk_events_internal", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030056(void)
{
	const ZBX_FIELD field = {"hk_events_internal", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030057(void)
{
	const ZBX_FIELD field = {"hk_events_discovery", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030058(void)
{
	const ZBX_FIELD field = {"hk_events_discovery", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030059(void)
{
	const ZBX_FIELD field = {"hk_events_autoreg", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030060(void)
{
	const ZBX_FIELD field = {"hk_events_autoreg", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030061(void)
{
	const ZBX_FIELD field = {"hk_services", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030062(void)
{
	const ZBX_FIELD field = {"hk_services", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030063(void)
{
	const ZBX_FIELD field = {"hk_audit", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030064(void)
{
	const ZBX_FIELD field = {"hk_audit", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030065(void)
{
	const ZBX_FIELD field = {"hk_sessions", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030066(void)
{
	const ZBX_FIELD field = {"hk_sessions", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030067(void)
{
	const ZBX_FIELD field = {"hk_history", "90d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030068(void)
{
	const ZBX_FIELD field = {"hk_history", "90d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030069(void)
{
	const ZBX_FIELD field = {"hk_trends", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBmodify_field_type("config", &field);
}

static int	DBpatch_3030070(void)
{
	const ZBX_FIELD field = {"hk_trends", "365d", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("config", &field);
}

static int	DBpatch_3030071(void)
{
	const DBpatch_field_conv_t	field_convs[] = {
						{"refresh_unsupported",	DBpatch_conv_sec},
						{"event_expire",	DBpatch_conv_day},
						{"ok_period",		DBpatch_conv_sec},
						{"blink_period",	DBpatch_conv_sec},
						{"hk_events_trigger",	DBpatch_conv_day},
						{"hk_events_internal",	DBpatch_conv_day},
						{"hk_events_discovery",	DBpatch_conv_day},
						{"hk_events_autoreg",	DBpatch_conv_day},
						{"hk_services",		DBpatch_conv_day},
						{"hk_audit",		DBpatch_conv_day},
						{"hk_sessions",		DBpatch_conv_day},
						{"hk_history",		DBpatch_conv_day},
						{"hk_trends",		DBpatch_conv_day},
						{NULL}
					};

	return DBpatch_table_convert("config", "configid", field_convs);
}

#endif

DBPATCH_START(3030)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3030000, 0, 1)
DBPATCH_ADD(3030001, 0, 1)
DBPATCH_ADD(3030002, 0, 1)
DBPATCH_ADD(3030003, 0, 1)
DBPATCH_ADD(3030004, 0, 1)
DBPATCH_ADD(3030005, 0, 1)
DBPATCH_ADD(3030006, 0, 1)
DBPATCH_ADD(3030007, 0, 1)
DBPATCH_ADD(3030008, 0, 1)
DBPATCH_ADD(3030009, 0, 1)
DBPATCH_ADD(3030010, 0, 1)
DBPATCH_ADD(3030011, 0, 1)
DBPATCH_ADD(3030012, 0, 1)
DBPATCH_ADD(3030013, 0, 1)
DBPATCH_ADD(3030015, 0, 1)
DBPATCH_ADD(3030016, 0, 1)
DBPATCH_ADD(3030017, 0, 1)
DBPATCH_ADD(3030018, 0, 1)
DBPATCH_ADD(3030019, 0, 1)
DBPATCH_ADD(3030020, 0, 1)
DBPATCH_ADD(3030021, 0, 1)
DBPATCH_ADD(3030022, 0, 1)
DBPATCH_ADD(3030023, 0, 1)
DBPATCH_ADD(3030024, 0, 1)
DBPATCH_ADD(3030025, 0, 1)
DBPATCH_ADD(3030026, 0, 1)
DBPATCH_ADD(3030027, 0, 1)
DBPATCH_ADD(3030028, 0, 1)
DBPATCH_ADD(3030029, 0, 1)
DBPATCH_ADD(3030030, 0, 1)
DBPATCH_ADD(3030031, 0, 1)
DBPATCH_ADD(3030032, 0, 1)
DBPATCH_ADD(3030033, 0, 1)
DBPATCH_ADD(3030034, 0, 1)
DBPATCH_ADD(3030035, 0, 1)
DBPATCH_ADD(3030036, 0, 1)
DBPATCH_ADD(3030037, 0, 1)
DBPATCH_ADD(3030038, 0, 1)
DBPATCH_ADD(3030039, 0, 1)
DBPATCH_ADD(3030040, 0, 1)
DBPATCH_ADD(3030041, 0, 1)
DBPATCH_ADD(3030042, 0, 1)
DBPATCH_ADD(3030043, 0, 1)
DBPATCH_ADD(3030044, 0, 1)
DBPATCH_ADD(3030045, 0, 1)
DBPATCH_ADD(3030046, 0, 1)
DBPATCH_ADD(3030047, 0, 1)
DBPATCH_ADD(3030048, 0, 1)
DBPATCH_ADD(3030049, 0, 1)
DBPATCH_ADD(3030050, 0, 1)
DBPATCH_ADD(3030051, 0, 1)
DBPATCH_ADD(3030052, 0, 1)
DBPATCH_ADD(3030053, 0, 1)
DBPATCH_ADD(3030054, 0, 1)
DBPATCH_ADD(3030055, 0, 1)
DBPATCH_ADD(3030056, 0, 1)
DBPATCH_ADD(3030057, 0, 1)
DBPATCH_ADD(3030058, 0, 1)
DBPATCH_ADD(3030059, 0, 1)
DBPATCH_ADD(3030060, 0, 1)
DBPATCH_ADD(3030061, 0, 1)
DBPATCH_ADD(3030062, 0, 1)
DBPATCH_ADD(3030063, 0, 1)
DBPATCH_ADD(3030064, 0, 1)
DBPATCH_ADD(3030065, 0, 1)
DBPATCH_ADD(3030066, 0, 1)
DBPATCH_ADD(3030067, 0, 1)
DBPATCH_ADD(3030068, 0, 1)
DBPATCH_ADD(3030069, 0, 1)
DBPATCH_ADD(3030070, 0, 1)
DBPATCH_ADD(3030071, 0, 1)

DBPATCH_END()
