/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "lld.h"
#include "db.h"
#include "log.h"
#include "zbxalgo.h"
#include "zbxserver.h"

static int	lld_check_record(struct zbx_json_parse *jp_row, const char *f_macro, const char *f_regexp,
		ZBX_REGEXP *regexps, int regexps_num)
{
	const char	*__function_name = "lld_check_record";

	char		*value = NULL;
	size_t		value_alloc = 0;
	int		res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() jp_row:'%.*s'", __function_name,
			jp_row->end - jp_row->start + 1, jp_row->start);

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, f_macro, &value, &value_alloc))
		res = regexp_match_ex(regexps, regexps_num, value, f_regexp, ZBX_CASE_SENSITIVE);

	zbx_free(value);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

static int	lld_rows_get(char *value, char *filter, zbx_vector_ptr_t *lld_rows, char **error)
{
	const char		*__function_name = "lld_parse_value";

	struct zbx_json_parse	jp, jp_data, jp_row;
	char			*f_macro = NULL, *f_regexp = NULL;
	const char		*p;
	ZBX_REGEXP		*regexps = NULL;
	int			regexps_alloc = 0, regexps_num = 0;
	zbx_lld_row_t		*lld_row;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != zbx_json_open(value, &jp))
	{
		*error = zbx_strdup(*error, "Value should be a JSON object.");
		goto out;
	}

	/* {"data":[{"{#IFNAME}":"eth0"},{"{#IFNAME}":"lo"},...]} */
	/*         ^-------------------------------------------^  */
	if (SUCCEED != zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		*error = zbx_dsprintf(*error, "Cannot find the \"%s\" array in the received JSON object.",
				ZBX_PROTO_TAG_DATA);
		goto out;
	}

	if (NULL != (f_regexp = strchr(filter, ':')))
	{
		f_macro = filter;
		*f_regexp++ = '\0';

		if ('@' == *f_regexp)
		{
			DB_RESULT	result;
			DB_ROW		row;
			char		*f_regexp_esc;

			f_regexp_esc = DBdyn_escape_string(f_regexp + 1);

			result = DBselect("select e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive"
					" from regexps r,expressions e"
					" where r.regexpid=e.regexpid"
						" and r.name='%s'" DB_NODE,
					f_regexp_esc, DBnode_local("r.regexpid"));

			zbx_free(f_regexp_esc);

			while (NULL != (row = DBfetch(result)))
			{
				add_regexp_ex(&regexps, &regexps_alloc, &regexps_num,
						f_regexp + 1, row[0], atoi(row[1]), row[2][0], atoi(row[3]));
			}
			DBfree_result(result);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() f_macro:'%s' f_regexp:'%s'", __function_name, f_macro, f_regexp);
	}

	p = NULL;
	/* {"data":[{"{#IFNAME}":"eth0"},{"{#IFNAME}":"lo"},...]} */
	/*          ^                                             */
	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
		/* {"data":[{"{#IFNAME}":"eth0"},{"{#IFNAME}":"lo"},...]} */
		/*          ^------------------^                          */
		if (FAIL == zbx_json_brackets_open(p, &jp_row))
			continue;

		if (NULL != f_macro && SUCCEED != lld_check_record(&jp_row, f_macro, f_regexp, regexps, regexps_num))
			continue;

		lld_row = zbx_malloc(NULL, sizeof(zbx_lld_row_t));
		memcpy(&lld_row->jp_row, &jp_row, sizeof(struct zbx_json_parse));
		zbx_vector_ptr_create(&lld_row->item_links);

		zbx_vector_ptr_append(lld_rows, lld_row);
	}

	clean_regexps_ex(regexps, &regexps_num);
	zbx_free(regexps);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	lld_item_links_free(zbx_vector_ptr_t *item_links)
{
	zbx_lld_item_link_t	*item_link;

	while (0 != item_links->values_num)
	{
		item_link = (zbx_lld_item_link_t *)item_links->values[--item_links->values_num];

		zbx_free(item_link);
	}
}

static void	lld_rows_free(zbx_vector_ptr_t *lld_rows)
{
	zbx_lld_row_t	*lld_row;

	while (0 != lld_rows->values_num)
	{
		lld_row = (zbx_lld_row_t *)lld_rows->values[--lld_rows->values_num];

		lld_item_links_free(&lld_row->item_links);
		zbx_vector_ptr_destroy(&lld_row->item_links);
		zbx_free(lld_row);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: lld_process_discovery_rule                                       *
 *                                                                            *
 * Purpose: add or update items, triggers and graphs for discovery item       *
 *                                                                            *
 * Parameters: lld_ruleid - [IN] discovery item identificator from database   *
 *             value      - [IN] received value from agent                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	lld_process_discovery_rule(zbx_uint64_t lld_ruleid, char *value, zbx_timespec_t *ts)
{
	const char		*__function_name = "lld_process_discovery_rule";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		hostid = 0;
	char			*discovery_key = NULL, *filter = NULL, *error = NULL, *db_error = NULL, *error_esc;
	unsigned char		status = 0;
	unsigned short		lifetime;
	zbx_vector_ptr_t	lld_rows;
	char			*sql = NULL;
	size_t			sql_alloc = 128, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64, __function_name, lld_ruleid);

	zbx_vector_ptr_create(&lld_rows);
	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set lastclock=%d,lastns=%d", ts->sec, ts->ns);

	result = DBselect(
			"select hostid,key_,status,filter,error,lifetime"
			" from items"
			" where itemid=" ZBX_FS_UI64,
			lld_ruleid);

	if (NULL != (row = DBfetch(result)))
	{
		char	*lifetime_str;

		ZBX_STR2UINT64(hostid, row[0]);
		discovery_key = zbx_strdup(discovery_key, row[1]);
		status = (unsigned char)atoi(row[2]);
		filter = zbx_strdup(filter, row[3]);
		db_error = zbx_strdup(db_error, row[4]);

		lifetime_str = zbx_strdup(NULL, row[5]);
		substitute_simple_macros(NULL, NULL, &hostid, NULL, NULL, NULL,
				&lifetime_str, MACRO_TYPE_LLD_LIFETIME, NULL, 0);
		if (SUCCEED != is_ushort(lifetime_str, &lifetime))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot process lost resources for the discovery rule \"%s:%s\":"
					" \"%s\" is not a valid value",
					zbx_host_string(hostid), discovery_key, lifetime_str);
			lifetime = 0xffff;
		}
		zbx_free(lifetime_str);
	}
	else
		zabbix_log(LOG_LEVEL_WARNING, "invalid discovery rule ID [" ZBX_FS_UI64 "]", lld_ruleid);
	DBfree_result(result);

	if (0 == hostid)
		goto clean;

	if (SUCCEED != lld_rows_get(value, filter, &lld_rows, &error))
		goto error;

	error = zbx_strdup(error, "");

	lld_update_items(hostid, lld_ruleid, &lld_rows, &error, lifetime, ts->sec);
	lld_update_triggers(hostid, lld_ruleid, &lld_rows, &error);
	lld_update_graphs(hostid, lld_ruleid, &lld_rows, &error);

	if (ITEM_STATUS_NOTSUPPORTED == status)
	{
		zabbix_log(LOG_LEVEL_WARNING,  "discovery rule [" ZBX_FS_UI64 "][%s] became supported",
				lld_ruleid, zbx_host_key_string(lld_ruleid));

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",status=%d", ITEM_STATUS_ACTIVE);
	}
error:
	if (NULL != error && 0 != strcmp(error, db_error))
	{
		error_esc = DBdyn_escape_string_len(error, ITEM_ERROR_LEN);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",error='%s'", error_esc);

		zbx_free(error_esc);
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where itemid=" ZBX_FS_UI64, lld_ruleid);

	DBbegin();

	DBexecute("%s", sql);

	DBcommit();
clean:
	zbx_free(error);
	zbx_free(db_error);
	zbx_free(filter);
	zbx_free(discovery_key);
	zbx_free(sql);

	lld_rows_free(&lld_rows);
	zbx_vector_ptr_destroy(&lld_rows);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
