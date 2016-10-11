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
#include "log.h"
#include "sysinfo.h"
#include "zbxserver.h"

#include "proxy.h"
#include "dbcache.h"
#include "discovery.h"
#include "zbxalgo.h"

typedef struct
{
	const char		*field;
	const char		*tag;
	zbx_json_type_t		jt;
	char			*default_value;
}
zbx_history_field_t;

typedef struct
{
	const char		*table, *lastidfield;
	zbx_history_field_t	fields[ZBX_MAX_FIELDS];
}
zbx_history_table_t;

typedef struct
{
	zbx_uint64_t	id;
	size_t		offset;
}
zbx_id_offset_t;

static zbx_history_table_t dht = {
	"proxy_dhistory", "dhistory_lastid",
		{
		{"clock",		ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"druleid",		ZBX_PROTO_TAG_DRULE,		ZBX_JSON_TYPE_INT,	NULL},
		{"dcheckid",		ZBX_PROTO_TAG_DCHECK,		ZBX_JSON_TYPE_INT,	NULL},
		{"type",		ZBX_PROTO_TAG_TYPE,		ZBX_JSON_TYPE_INT,	NULL},
		{"ip",			ZBX_PROTO_TAG_IP,		ZBX_JSON_TYPE_STRING,	NULL},
		{"dns",			ZBX_PROTO_TAG_DNS,		ZBX_JSON_TYPE_STRING,	NULL},
		{"port",		ZBX_PROTO_TAG_PORT,		ZBX_JSON_TYPE_INT,	"0"},
		{"key_",		ZBX_PROTO_TAG_KEY,		ZBX_JSON_TYPE_STRING,	""},
		{"value",		ZBX_PROTO_TAG_VALUE,		ZBX_JSON_TYPE_STRING,	""},
		{"status",		ZBX_PROTO_TAG_STATUS,		ZBX_JSON_TYPE_INT,	"0"},
		{NULL}
		}
};

static zbx_history_table_t areg = {
	"proxy_autoreg_host", "autoreg_host_lastid",
		{
		{"clock",		ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"host",		ZBX_PROTO_TAG_HOST,		ZBX_JSON_TYPE_STRING,	NULL},
		{"listen_ip",		ZBX_PROTO_TAG_IP,		ZBX_JSON_TYPE_STRING,	""},
		{"listen_dns",		ZBX_PROTO_TAG_DNS,		ZBX_JSON_TYPE_STRING,	""},
		{"listen_port",		ZBX_PROTO_TAG_PORT,		ZBX_JSON_TYPE_STRING,	"0"},
		{"host_metadata",	ZBX_PROTO_TAG_HOST_METADATA,	ZBX_JSON_TYPE_STRING,	""},
		{NULL}
		}
};

/******************************************************************************
 *                                                                            *
 * Function: get_active_proxy_id                                              *
 *                                                                            *
 * Purpose: extract a proxy name from JSON and find the proxy ID in database. *
 *          The proxy must be configured in active mode.                      *
 *                                                                            *
 * Parameters: jp            - [IN] JSON with the proxy name                  *
 *             hostid        - [OUT] proxy host ID found in database          *
 *             host          - [IN] buffer with minimum size                  *
 *                                  'HOST_HOST_LEN_MAX'                       *
 *             error         - [OUT] error message                            *
 *                                                                            *
 * Return value:  SUCCEED - proxy ID was found in database                    *
 *                FAIL    - an error occurred (e.g. an unknown proxy or the   *
 *                          proxy is configured in passive mode               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	get_active_proxy_id(struct zbx_json_parse *jp, zbx_uint64_t *hostid, char *host, char **error)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*host_esc;
	int		ret = FAIL, status;

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, host, HOST_HOST_LEN_MAX))
	{
		if (FAIL == zbx_check_hostname(host))
		{
			*error = zbx_dsprintf(*error, "invalid proxy name \"%s\"", host);
			return ret;
		}

		host_esc = DBdyn_escape_string(host);

		result = DBselect(
				"select hostid,status"
				" from hosts"
				" where host='%s'"
					" and status in (%d,%d)"
					ZBX_SQL_NODE,
				host_esc, HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE,
				DBand_node_local("hostid"));

		zbx_free(host_esc);

		if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
		{
			if (SUCCEED == is_uint31(row[1], &status))
			{
				if (HOST_STATUS_PROXY_ACTIVE == status)
				{
					ZBX_STR2UINT64(*hostid, row[0]);
					ret = SUCCEED;
				}
				else
				{
					*error = zbx_dsprintf(*error, "proxy \"%s\" is configured in passive mode",
							host);
				}
			}
			else
				THIS_SHOULD_NEVER_HAPPEN;
		}
		else
			*error = zbx_dsprintf(*error, "proxy \"%s\" not found", host);

		DBfree_result(result);
	}
	else
		*error = zbx_strdup(*error, "missing name of proxy");

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: update_proxy_lastaccess                                          *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	update_proxy_lastaccess(const zbx_uint64_t hostid)
{
	DBexecute("update hosts set lastaccess=%d where hostid=" ZBX_FS_UI64, time(NULL), hostid);
}

/******************************************************************************
 *                                                                            *
 * Function: get_proxyconfig_table                                            *
 *                                                                            *
 * Purpose: prepare proxy configuration data                                  *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static int	get_proxyconfig_table(zbx_uint64_t proxy_hostid, struct zbx_json *j, const ZBX_TABLE *table,
		zbx_vector_uint64_t *hosts, zbx_vector_uint64_t *httptests)
{
	const char	*__function_name = "get_proxyconfig_table";
	char		*sql = NULL;
	size_t		sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset = 0;
	int		f, fld, ret = SUCCEED;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() proxy_hostid:" ZBX_FS_UI64 " table:'%s'",
			__function_name, proxy_hostid, table->table);

	zbx_json_addobject(j, table->table);
	zbx_json_addarray(j, "fields");

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select t.%s", table->recid);

	zbx_json_addstring(j, NULL, table->recid, ZBX_JSON_TYPE_STRING);

	for (f = 0; 0 != table->fields[f].name; f++)
	{
		if (0 == (table->fields[f].flags & ZBX_PROXY))
			continue;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",t.%s", table->fields[f].name);

		zbx_json_addstring(j, NULL, table->fields[f].name, ZBX_JSON_TYPE_STRING);
	}

	zbx_json_close(j);	/* fields */

	zbx_json_addarray(j, "data");

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " from %s t", table->table);

	if (SUCCEED == str_in_list("globalmacro,regexps,expressions,config", table->table, ','))
	{
		char	field_name[ZBX_FIELDNAME_LEN + 3];

		zbx_snprintf(field_name, sizeof(field_name), "t.%s", table->recid);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ZBX_SQL_NODE, DBwhere_node_local(field_name));
	}
	else if (SUCCEED == str_in_list("hosts,interface,hosts_templates,hostmacro", table->table, ','))
	{
		if (0 == hosts->values_num)
			goto skip_data;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.hostid", hosts->values, hosts->values_num);
	}
	else if (0 == strcmp(table->table, "items"))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				",hosts r where t.hostid=r.hostid"
					" and r.proxy_hostid=" ZBX_FS_UI64
					" and r.status in (%d,%d)"
					" and t.type in (%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d)",
				proxy_hostid,
				HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
				ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c,
				ITEM_TYPE_SNMPv3, ITEM_TYPE_IPMI, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE,
				ITEM_TYPE_HTTPTEST, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR, ITEM_TYPE_SSH,
				ITEM_TYPE_TELNET, ITEM_TYPE_JMX, ITEM_TYPE_SNMPTRAP, ITEM_TYPE_INTERNAL);
	}
	else if (0 == strcmp(table->table, "drules"))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" where t.proxy_hostid=" ZBX_FS_UI64
					" and t.status=%d",
				proxy_hostid, DRULE_STATUS_MONITORED);
	}
	else if (0 == strcmp(table->table, "dchecks"))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				",drules r where t.druleid=r.druleid"
					" and r.proxy_hostid=" ZBX_FS_UI64
					" and r.status=%d",
				proxy_hostid, DRULE_STATUS_MONITORED);
	}
	else if (0 == strcmp(table->table, "groups"))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				",config r where t.groupid=r.discovery_groupid" ZBX_SQL_NODE,
				DBand_node_local("r.configid"));
	}
	else if (SUCCEED == str_in_list("httptest,httptestitem,httpstep", table->table, ','))
	{
		if (0 == httptests->values_num)
			goto skip_data;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.httptestid",
				httptests->values, httptests->values_num);
	}
	else if (0 == strcmp(table->table, "httpstepitem"))
	{
		if (0 == httptests->values_num)
			goto skip_data;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				",httpstep r where t.httpstepid=r.httpstepid"
					" and");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "r.httptestid",
				httptests->values, httptests->values_num);
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " order by t.%s", table->recid);

	if (NULL == (result = DBselect("%s", sql)))
	{
		ret = FAIL;
		goto skip_data;
	}

	while (NULL != (row = DBfetch(result)))
	{
		fld = 0;
		zbx_json_addarray(j, NULL);
		zbx_json_addstring(j, NULL, row[fld++], ZBX_JSON_TYPE_INT);

		for (f = 0; 0 != table->fields[f].name; f++)
		{
			if (0 == (table->fields[f].flags & ZBX_PROXY))
				continue;

			switch (table->fields[f].type)
			{
				case ZBX_TYPE_INT:
				case ZBX_TYPE_UINT:
				case ZBX_TYPE_ID:
					if (SUCCEED != DBis_null(row[fld]))
						zbx_json_addstring(j, NULL, row[fld], ZBX_JSON_TYPE_INT);
					else
						zbx_json_addstring(j, NULL, NULL, ZBX_JSON_TYPE_NULL);
					break;
				default:
					zbx_json_addstring(j, NULL, row[fld], ZBX_JSON_TYPE_STRING);
					break;
			}

			fld++;
		}
		zbx_json_close(j);
	}
	DBfree_result(result);
skip_data:
	zbx_free(sql);

	zbx_json_close(j);	/* data */
	zbx_json_close(j);	/* table->table */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	get_proxy_monitored_hosts(zbx_uint64_t proxy_hostid, zbx_vector_uint64_t *hosts)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	hostid, *ids = NULL;
	int		ids_alloc = 0, ids_num = 0;
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset;

	sql = zbx_malloc(sql, sql_alloc * sizeof(char));

	result = DBselect(
			"select hostid"
			" from hosts"
			" where proxy_hostid=" ZBX_FS_UI64
				" and status in (%d,%d)"
				" and flags<>%d",
			proxy_hostid, HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, ZBX_FLAG_DISCOVERY_PROTOTYPE);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);

		zbx_vector_uint64_append(hosts, hostid);
		uint64_array_add(&ids, &ids_alloc, &ids_num, hostid, 64);
	}
	DBfree_result(result);

	while (0 != ids_num)
	{
		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct templateid"
				" from hosts_templates"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", ids, ids_num);

		ids_num = 0;

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);

			zbx_vector_uint64_append(hosts, hostid);
			uint64_array_add(&ids, &ids_alloc, &ids_num, hostid, 64);
		}
		DBfree_result(result);
	}

	zbx_free(ids);
	zbx_free(sql);

	zbx_vector_uint64_sort(hosts, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

static void	get_proxy_monitored_httptests(zbx_uint64_t proxy_hostid, zbx_vector_uint64_t *httptests)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	httptestid;

	result = DBselect(
			"select httptestid"
			" from httptest t,hosts h"
			" where t.hostid=h.hostid"
				" and t.status=%d"
				" and h.proxy_hostid=" ZBX_FS_UI64
				" and h.status=%d",
			HTTPTEST_STATUS_MONITORED, proxy_hostid, HOST_STATUS_MONITORED);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(httptestid, row[0]);

		zbx_vector_uint64_append(httptests, httptestid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(httptests, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: get_proxyconfig_data                                             *
 *                                                                            *
 * Purpose: prepare proxy configuration data                                  *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	get_proxyconfig_data(zbx_uint64_t proxy_hostid, struct zbx_json *j, char **error)
{
	static const char	*proxytable[] =
	{
		"globalmacro",
		"hosts",
		"interface",
		"hosts_templates",
		"hostmacro",
		"items",
		"drules",
		"dchecks",
		"regexps",
		"expressions",
		"groups",
		"config",
		"httptest",
		"httptestitem",
		"httpstep",
		"httpstepitem",
		NULL
	};

	const char		*__function_name = "get_proxyconfig_data";

	int			i, ret = FAIL;
	const ZBX_TABLE		*table;
	zbx_vector_uint64_t	hosts, httptests;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() proxy_hostid:" ZBX_FS_UI64, __function_name, proxy_hostid);

	assert(proxy_hostid);

	zbx_vector_uint64_create(&hosts);
	zbx_vector_uint64_create(&httptests);

	get_proxy_monitored_hosts(proxy_hostid, &hosts);
	get_proxy_monitored_httptests(proxy_hostid, &httptests);

	for (i = 0; NULL != proxytable[i]; i++)
	{
		table = DBget_table(proxytable[i]);
		assert(NULL != table);

		if (SUCCEED != get_proxyconfig_table(proxy_hostid, j, table, &hosts, &httptests))
		{
			*error = zbx_dsprintf(*error, "failed to get data from table %s", table->table);
			goto out;
		}
	}

	ret = SUCCEED;
out:
	zbx_vector_uint64_destroy(&httptests);
	zbx_vector_uint64_destroy(&hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remember_record                                                  *
 *                                                                            *
 * Purpose: A record is stored as a sequence of fields and flag bytes for     *
 *          handling NULL values. A field is stored as a null-terminated      *
 *          string to preserve field boundaries. If a field value can be NULL *
 *          a flag byte is inserted after the field to distinguish between    *
 *          empty string and NULL value. The flag byte can be '\1'            *
 *          (not NULL value) or '\2' (NULL value).                            *
 *                                                                            *
 * Examples of representation:                                                *
 *          \0\2    - the field can be NULL and it is NULL                    *
 *          \0\1    - the field can be NULL but is empty string               *
 *          abc\0\1 - the field can be NULL but is a string "abc"             *
 *          \0      - the field can not be NULL and is empty string           *
 *          abc\0   - the field can not be NULL and is a string "abc"         *
 *                                                                            *
 ******************************************************************************/
static void	remember_record(const ZBX_FIELD **fields, int fields_count, char **recs, size_t *recs_alloc,
		size_t *recs_offset, DB_ROW row)
{
	int	f;

	for (f = 0; f < fields_count; f++)
	{
		if (0 != (fields[f]->flags & ZBX_NOTNULL))
		{
			zbx_strcpy_alloc(recs, recs_alloc, recs_offset, row[f]);
			*recs_offset += sizeof(char);
		}
		else if (SUCCEED != DBis_null(row[f]))
		{
			zbx_strcpy_alloc(recs, recs_alloc, recs_offset, row[f]);
			*recs_offset += sizeof(char);
			zbx_chrcpy_alloc(recs, recs_alloc, recs_offset, '\1');
		}
		else
		{
			zbx_strcpy_alloc(recs, recs_alloc, recs_offset, "");
			*recs_offset += sizeof(char);
			zbx_chrcpy_alloc(recs, recs_alloc, recs_offset, '\2');
		}
	}
}

static zbx_hash_t	id_offset_hash_func(const void *data)
{
	const zbx_id_offset_t *p = data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&p->id, sizeof(zbx_uint64_t), ZBX_DEFAULT_HASH_SEED);
}

static int	id_offset_compare_func(const void *d1, const void *d2)
{
	const zbx_id_offset_t *p1 = d1, *p2 = d2;

	return ZBX_DEFAULT_UINT64_COMPARE_FUNC(&p1->id, &p2->id);
}

/******************************************************************************
 *                                                                            *
 * Function: find_field_by_name                                               *
 *                                                                            *
 * Purpose: find a number of the field                                        *
 *                                                                            *
 ******************************************************************************/
static int	find_field_by_name(const ZBX_FIELD **fields, int fields_count, const char *field_name)
{
	int	f;

	for (f = 0; f < fields_count; f++)
	{
		if (0 == strcmp(fields[f]->name, field_name))
			break;
	}

	return f;
}

/******************************************************************************
 *                                                                            *
 * Function: compare_nth_field                                                *
 *                                                                            *
 * Purpose: This function compares a value from JSON record with the value    *
 *          of the n-th field of DB record. For description how DB record is  *
 *          stored in memory see comments in function remember_record().      *
 *                                                                            *
 * Comparing deals with 4 cases:                                              *
 *          - JSON value is not NULL, DB value is not NULL                    *
 *          - JSON value is not NULL, DB value is NULL                        *
 *          - JSON value is NULL, DB value is NULL                            *
 *          - JSON value is NULL, DB value is not NULL                        *
 *                                                                            *
 ******************************************************************************/
static int	compare_nth_field(const ZBX_FIELD **fields, const char *rec_data, int n, const char *str, int is_null,
		int *last_n, size_t *last_pos)
{
	int		i = *last_n, null_in_db = 0;
	const char	*p = rec_data + *last_pos, *field_start = NULL;

	do	/* find starting position of the n-th field */
	{
		field_start = p;
		while ('\0' != *p++)
			;

		null_in_db = 0;

		if (0 == (fields[i++]->flags & ZBX_NOTNULL))	/* field could be NULL */
		{
			if ('\2' == *p && (rec_data == p - 1 || '\0' == *(p - 2) || '\1' == *(p - 2) ||
					'\2' == *(p - 2)))	/* field value is NULL */
			{
				null_in_db = 1;
				p++;
			}
			else if ('\1' == *p)
			{
				p++;
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
				*last_n = 0;
				*last_pos = 0;
				return 1;
			}
		}
	}
	while (n >= i);

	*last_n = i;				/* Preserve number of field and its start position */
	*last_pos = (size_t)(p - rec_data);	/* across calls to avoid searching from start. */

	if (0 == is_null)	/* value in JSON is not NULL */
	{
		if (0 == null_in_db)
			return strcmp(field_start, str);
		else
			return 1;
	}
	else
	{
		if ('\0' == *str)
		{
			if (1 == null_in_db)
				return 0;	/* fields are "equal" - both contain NULL */
			else
				return 1;
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			*last_n = 0;
			*last_pos = 0;
			return 1;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxyconfig_table                                        *
 *                                                                            *
 * Purpose: update configuration table                                        *
 *                                                                            *
 * Return value: SUCCEED - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static int	process_proxyconfig_table(const ZBX_TABLE *table, struct zbx_json_parse *jp_obj,
		zbx_vector_uint64_t *del, char **error)
{
	const char		*__function_name = "process_proxyconfig_table";

	int			f, fields_count = 0, insert, is_null, i, ret = FAIL, id_field_nr = 0,
				move_out = 0, move_field_nr = 0;
	const ZBX_FIELD		*fields[ZBX_MAX_FIELDS];
	struct zbx_json_parse	jp_data, jp_row;
	char			buf[MAX_STRING_LEN], *esc;
	const char		*p, *pf;
	zbx_uint64_t		recid, *p_recid = NULL;
	zbx_vector_uint64_t	ins, moves;
	char			*sql = NULL, *recs = NULL;
	size_t			sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset,
				recs_alloc = 20 * ZBX_KIBIBYTE, recs_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_hashset_t           h_id_offsets, h_del;
	zbx_hashset_iter_t	iter;
	zbx_id_offset_t		id_offset, *p_id_offset = NULL;
	zbx_db_insert_t		db_insert;
	zbx_vector_ptr_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s'", __function_name, table->table);

	/************************************************************************************/
	/* T1. RECEIVED JSON (jp_obj) DATA FORMAT                                           */
	/************************************************************************************/
	/* Line |                  Data                     | Corresponding structure in DB */
	/* -----+-------------------------------------------+------------------------------ */
	/*   1  | {                                         |                               */
	/*   2  |         "hosts": {                        | first table                   */
	/*   3  |                 "fields": [               | list of table's columns       */
	/*   4  |                         "hostid",         | first column                  */
	/*   5  |                         "host",           | second column                 */
	/*   6  |                         ...               | ...columns                    */
	/*   7  |                 ],                        |                               */
	/*   8  |                 "data": [                 | the table data                */
	/*   9  |                         [                 | first entry                   */
	/*  10  |                               1,          | value for first column        */
	/*  11  |                               "zbx01",    | value for second column       */
	/*  12  |                               ...         | ...values                     */
	/*  13  |                         ],                |                               */
	/*  14  |                         [                 | second entry                  */
	/*  15  |                               2,          | value for first column        */
	/*  16  |                               "zbx02",    | value for second column       */
	/*  17  |                               ...         | ...values                     */
	/*  18  |                         ],                |                               */
	/*  19  |                         ...               | ...entries                    */
	/*  20  |                 ]                         |                               */
	/*  21  |         },                                |                               */
	/*  22  |         "items": {                        | second table                  */
	/*  23  |                 ...                       | ...                           */
	/*  24  |         },                                |                               */
	/*  25  |         ...                               | ...tables                     */
	/*  26  | }                                         |                               */
	/************************************************************************************/

	/* get table columns (line 3 in T1) */
	if (FAIL == zbx_json_brackets_by_name(jp_obj, "fields", &jp_data))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	p = NULL;
	/* iterate column names (lines 4-6 in T1) */
	while (NULL != (p = zbx_json_next_value(&jp_data, p, buf, sizeof(buf), NULL)))
	{
		if (NULL == (fields[fields_count++] = DBget_field(table, buf)))
		{
			*error = zbx_dsprintf(*error, "invalid field name \"%s.%s\"", table->table, buf);
			goto out;
		}
	}

	/* get the entries (line 8 in T1) */
	if (FAIL == zbx_json_brackets_by_name(jp_obj, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	/* all records will be stored in one large string */
	recs = zbx_malloc(recs, recs_alloc);

	/* hash set as index for fast access to records via IDs */
	zbx_hashset_create(&h_id_offsets, 10000, id_offset_hash_func, id_offset_compare_func);

	/* a hash set as a list for finding records to be deleted */
	zbx_hashset_create(&h_del, 10000, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	sql = zbx_malloc(sql, sql_alloc);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select ");

	/* make a string with a list of fields for SELECT */
	for (f = 0; f < fields_count; f++)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, fields[f]->name);
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ',');
	}

	sql_offset--;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " from ");
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, table->table);

	/* Find a number of the ID field. Usually the 1st field. */
	id_field_nr = find_field_by_name(fields, fields_count, table->recid);

	/* select all existing records */
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(recid, row[id_field_nr]);

		id_offset.id = recid;
		id_offset.offset = recs_offset;

		zbx_hashset_insert(&h_id_offsets, &id_offset, sizeof(id_offset));
		zbx_hashset_insert(&h_del, &recid, sizeof(recid));

		remember_record(fields, fields_count, &recs, &recs_alloc, &recs_offset, row);
	}
	DBfree_result(result);

	/* these tables have unique indices, need special preparation to avoid conflicts during inserts/updates */
	if (0 == strcmp("hosts_templates", table->table))
	{
		move_out = 1;
		move_field_nr = find_field_by_name(fields, fields_count, "templateid");
	}
	else if (0 == strcmp("hostmacro", table->table))
	{
		move_out = 1;
		move_field_nr = find_field_by_name(fields, fields_count, "macro");
	}
	else if (0 == strcmp("items", table->table))
	{
		move_out = 1;
		move_field_nr = find_field_by_name(fields, fields_count, "key_");
	}
	else if (0 == strcmp("httptest", table->table))
	{
		move_out = 1;
		move_field_nr = find_field_by_name(fields, fields_count, "name");
	}

	zbx_vector_uint64_create(&ins);

	if (1 == move_out)
		zbx_vector_uint64_create(&moves);

	p = NULL;
	/* iterate the entries (lines 9, 14 and 19 in T1) */
	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
		if (FAIL == zbx_json_brackets_open(p, &jp_row) ||
				NULL == (pf = zbx_json_next_value(&jp_row, NULL, buf, sizeof(buf), NULL)))
		{
			*error = zbx_strdup(*error, zbx_json_strerror());
			goto clean2;
		}

		/* check whether we need to update existing entry or insert a new one */

		ZBX_STR2UINT64(recid, buf);

		if (NULL != zbx_hashset_search(&h_del, &recid))
		{
			zbx_hashset_remove(&h_del, &recid);

			if (1 == move_out)
			{
				int	last_n = 0;
				size_t	last_pos = 0;

				/* locate a copy of this record as found in database */
				id_offset.id = recid;
				if (NULL == (p_id_offset = zbx_hashset_search(&h_id_offsets, &id_offset)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					goto clean2;
				}

				/* find the field requiring special preprocessing in JSON record */
				f = 1;
				while (NULL != (pf = zbx_json_next_value(&jp_row, pf, buf, sizeof(buf), &is_null)))
				{
					/* parse values for the entry (lines 10-12 in T1) */

					if (fields_count == f)
					{
						*error = zbx_dsprintf(*error, "invalid number of fields \"%.*s\"",
								jp_row.end - jp_row.start + 1, jp_row.start);
						goto clean2;
					}

					if (move_field_nr == f)
						break;
					f++;
				}

				if (0 != compare_nth_field(fields, recs + p_id_offset->offset, move_field_nr, buf,
						is_null, &last_n, &last_pos))
				{
					zbx_vector_uint64_append(&moves, recid);
				}
			}
		}
		else
			zbx_vector_uint64_append(&ins, recid);
	}

	/* copy IDs of records to be deleted from hash set to vector */
	zbx_hashset_iter_reset(&h_del, &iter);
	while (NULL != (p_recid = zbx_hashset_iter_next(&iter)))
		zbx_vector_uint64_append(del, *p_recid);
	zbx_vector_uint64_sort(del, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_vector_uint64_sort(&ins, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (1 == move_out)
	{
		/* special preprocessing for 'hosts_templates' table to eliminate conflicts */
		/* in the 'hostid, templateid' unique index */
		if (0 == strcmp("hosts_templates", table->table))
		{
			/* Making the 'hostid, templateid' combination unique to avoid collisions when new records */
			/* are inserted and existing ones are updated is a bit complex. Let's take a simpler approach */
			/* - delete affected old records and insert the new ones. */
			for (i = 0; i < moves.values_num; i++)
			{
				zbx_vector_uint64_append(del, moves.values[i]);
				zbx_vector_uint64_append(&ins, moves.values[i]);
			}

			if (0 < moves.values_num)
			{
				zbx_vector_uint64_clear(&moves);
				zbx_vector_uint64_sort(del, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
				zbx_vector_uint64_sort(&ins, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			}
		}

		if (0 != del->values_num)
		{
			sql_offset = 0;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from %s where", table->table);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, table->recid, del->values,
					del->values_num);

			if (ZBX_DB_OK > DBexecute("%s", sql))
				goto clean2;

			zbx_vector_uint64_clear(del);
		}

		/* special preprocessing for 'hostmacro', 'items' and 'httptest' tables to eliminate conflicts */
		/* in the 'hostid,macro', 'hostid,key_' and 'hostid,name' unique indices */
		if (1 < moves.values_num)
		{
			sql_offset = 0;
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set %s=concat('#',%s) where",
					table->table, fields[move_field_nr]->name, table->recid);
#else
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set %s='#'||%s where",
					table->table, fields[move_field_nr]->name, table->recid);
#endif
			zbx_vector_uint64_sort(&moves, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, table->recid, moves.values,
					moves.values_num);

			if (ZBX_DB_OK > DBexecute("%s", sql))
				goto clean2;
		}
	}

	if (0 != ins.values_num)
	{
		zbx_vector_ptr_create(&values);

		zbx_db_insert_prepare_dyn(&db_insert, table, fields, fields_count);
	}

	sql_offset = 0;
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	p = NULL;
	/* iterate the entries (lines 9, 14 and 19 in T1) */
	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
		int	rec_differ = 0;			/* how many fields differ */
		int	last_n = 0;
		size_t	tmp_offset = sql_offset, last_pos = 0;

		zbx_json_brackets_open(p, &jp_row);
		pf = zbx_json_next_value(&jp_row, NULL, buf, sizeof(buf), NULL);

		/* check whether we need to insert a new entry or update an existing one */
		ZBX_STR2UINT64(recid, buf);
		insert = (FAIL != zbx_vector_uint64_bsearch(&ins, recid, ZBX_DEFAULT_UINT64_COMPARE_FUNC));

		if (0 != insert)
		{
			/* perform insert operation */

			zbx_db_value_t	*value;

			/* add the id field */
			value = zbx_malloc(NULL, sizeof(zbx_db_value_t));
			value->ui64 = recid;
			zbx_vector_ptr_append(&values, value);

			/* add the rest of fields */
			for (f = 1; NULL != (pf = zbx_json_next_value(&jp_row, pf, buf, sizeof(buf), &is_null)); f++)
			{
				if (f == fields_count)
				{
					*error = zbx_dsprintf(*error, "invalid number of fields \"%.*s\"",
							jp_row.end - jp_row.start + 1, jp_row.start);
					goto clean;
				}

				if (0 != is_null && 0 != (fields[f]->flags & ZBX_NOTNULL))
				{
					*error = zbx_dsprintf(*error, "column \"%s.%s\" cannot be null",
							table->table, fields[f]->name);
					goto clean;
				}

				value = zbx_malloc(NULL, sizeof(zbx_db_value_t));

				switch (fields[f]->type)
				{
					case ZBX_TYPE_INT:
						value->i32 = atoi(buf);
						break;
					case ZBX_TYPE_UINT:
						ZBX_STR2UINT64(value->ui64, buf);
						break;
					case ZBX_TYPE_ID:
						if (0 == is_null)
							ZBX_STR2UINT64(value->ui64, buf);
						else
							value->ui64 = 0;
						break;
					case ZBX_TYPE_FLOAT:
						value->dbl = atof(buf);
						break;
					case ZBX_TYPE_CHAR:
					case ZBX_TYPE_TEXT:
					case ZBX_TYPE_SHORTTEXT:
					case ZBX_TYPE_LONGTEXT:
						value->str = zbx_strdup(NULL, buf);
						break;
					default:
						*error = zbx_dsprintf(*error, "unsupported field type %d in \"%s.%s\"",
								(int)fields[f]->type, table->table, fields[f]->name);
						zbx_free(value);
						goto clean;

				}

				zbx_vector_ptr_append(&values, value);
			}

			zbx_db_insert_add_values_dyn(&db_insert, (const zbx_db_value_t **)values.values,
					values.values_num);

			for (f = 0; f < fields_count; f++)
			{
				switch (fields[f]->type)
				{
					case ZBX_TYPE_CHAR:
					case ZBX_TYPE_TEXT:
					case ZBX_TYPE_SHORTTEXT:
					case ZBX_TYPE_LONGTEXT:
						value = values.values[f];
						zbx_free(value->str);
				}
			}
			zbx_vector_ptr_clean(&values, zbx_ptr_free);

			if (f != fields_count)
			{
				*error = zbx_dsprintf(*error, "invalid number of fields \"%.*s\"",
						jp_row.end - jp_row.start + 1, jp_row.start);
				goto clean;
			}
		}
		else
		{
			/* perform update operation */

			if (1 == fields_count)	/* only primary key given, no update needed */
				continue;

			/* locate a copy of this record as found in database */
			id_offset.id = recid;
			if (NULL == (p_id_offset = zbx_hashset_search(&h_id_offsets, &id_offset)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				goto clean;
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ", table->table);

			for (f = 1; NULL != (pf = zbx_json_next_value(&jp_row, pf, buf, sizeof(buf), &is_null)); f++)
			{
				int	field_differ = 1;

				/* parse values for the entry (lines 10-12 in T1) */

				if (f == fields_count)
				{
					*error = zbx_dsprintf(*error, "invalid number of fields \"%.*s\"",
							jp_row.end - jp_row.start + 1, jp_row.start);
					goto clean;
				}

				if (0 != is_null && 0 != (fields[f]->flags & ZBX_NOTNULL))
				{
					*error = zbx_dsprintf(*error, "column \"%s.%s\" cannot be null",
							table->table, fields[f]->name);
					goto clean;
				}

				if (0 == (field_differ = compare_nth_field(fields, recs + p_id_offset->offset, f, buf,
						is_null, &last_n, &last_pos)))
				{
					continue;
				}

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s=", fields[f]->name);
				rec_differ++;

				if (0 != is_null)
				{
					zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "null,");
					continue;
				}

				switch (fields[f]->type)
				{
					case ZBX_TYPE_INT:
					case ZBX_TYPE_UINT:
					case ZBX_TYPE_ID:
					case ZBX_TYPE_FLOAT:
						zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s,", buf);
						break;
					default:
						esc = DBdyn_escape_string(buf);
						zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "'%s',", esc);
						zbx_free(esc);
				}
			}

			if (f != fields_count)
			{
				*error = zbx_dsprintf(*error, "invalid number of fields \"%.*s\"",
						jp_row.end - jp_row.start + 1, jp_row.start);
				goto clean;
			}

			sql_offset--;

			if (0 != rec_differ)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where %s=" ZBX_FS_UI64 ";\n",
						table->recid, recid);
			}
			else
			{
				sql_offset = tmp_offset;	/* discard this update, all fields are the same */
				*(sql + sql_offset) = '\0';
			}

			if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
				goto clean;
		}
	}

	if (sql_offset > 16)	/* in ORACLE always present begin..end; */
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto clean;
	}

	ret = (0 == ins.values_num ? SUCCEED : zbx_db_insert_execute(&db_insert));
clean:
	if (0 != ins.values_num)
	{
		zbx_db_insert_clean(&db_insert);
		zbx_vector_ptr_destroy(&values);
	}
clean2:
	zbx_hashset_destroy(&h_id_offsets);
	zbx_hashset_destroy(&h_del);
	zbx_vector_uint64_destroy(&ins);
	if (1 == move_out)
		zbx_vector_uint64_destroy(&moves);
	zbx_free(sql);
	zbx_free(recs);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxyconfig                                              *
 *                                                                            *
 * Purpose: update configuration                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	process_proxyconfig(struct zbx_json_parse *jp_data)
{
	typedef struct
	{
		const ZBX_TABLE		*table;
		zbx_vector_uint64_t	ids;
	}
	table_ids_t;

	const char		*__function_name = "process_proxyconfig";
	char			buf[ZBX_TABLENAME_LEN_MAX];
	const char		*p = NULL;
	struct zbx_json_parse	jp_obj;
	char			*error = NULL;
	int			i, ret = SUCCEED;

	table_ids_t		*table_ids;
	zbx_vector_ptr_t	tables;
	const ZBX_TABLE		*table;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&tables);

	DBbegin();

	/* iterate the tables (lines 2, 22 and 25 in T1) */
	while (NULL != (p = zbx_json_pair_next(jp_data, p, buf, sizeof(buf))) && SUCCEED == ret)
	{
		if (FAIL == zbx_json_brackets_open(p, &jp_obj))
		{
			error = zbx_strdup(error, zbx_json_strerror());
			ret = FAIL;
			break;
		}

		if (NULL == (table = DBget_table(buf)))
		{
			error = zbx_dsprintf(error, "invalid table name \"%s\"", buf);
			ret = FAIL;
			break;
		}

		table_ids = zbx_malloc(NULL, sizeof(table_ids_t));
		table_ids->table = table;
		zbx_vector_uint64_create(&table_ids->ids);
		zbx_vector_ptr_append(&tables, table_ids);

		ret = process_proxyconfig_table(table, &jp_obj, &table_ids->ids, &error);
	}

	if (SUCCEED == ret)
	{
		char 	*sql = NULL;
		size_t	sql_alloc = 512, sql_offset = 0;

		sql = zbx_malloc(sql, sql_alloc * sizeof(char));

		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = tables.values_num - 1; 0 <= i; i--)
		{
			table_ids = tables.values[i];

			if (0 == table_ids->ids.values_num)
				continue;

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from %s where",
					table_ids->table->table);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, table_ids->table->recid,
					table_ids->ids.values, table_ids->ids.values_num);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
		}

		if (sql_offset > 16)	/* in ORACLE always present begin..end; */
		{
			DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

			if (ZBX_DB_OK > DBexecute("%s", sql))
				ret = FAIL;
		}

		zbx_free(sql);
	}

	for (i = 0; i < tables.values_num; i++)
	{
		table_ids = tables.values[i];

		zbx_vector_uint64_destroy(&table_ids->ids);
		zbx_free(table_ids);
	}
	zbx_vector_ptr_destroy(&tables);

	DBend(ret);

	if (SUCCEED != ret)
	{
		zabbix_log(LOG_LEVEL_ERR, "failed to update local proxy configuration copy: %s",
				(NULL == error ? "database error" : error));
	}
	else
		DCsync_configuration();

	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: get_host_availability_data                                       *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
#define CHECK_ARRAY_SIZE(array, alloc, num)				\
	if (num == alloc)						\
	{								\
		alloc = (0 == alloc ? 8 : alloc * 3 / 2);		\
		array = zbx_realloc(array, alloc * sizeof(*array));	\
	}

int	get_host_availability_data(struct zbx_json *j)
{
	typedef struct
	{
		zbx_uint64_t	hostid;
		char		*error, *snmp_error, *ipmi_error, *jmx_error;
		unsigned char	available, snmp_available, ipmi_available, jmx_available;
	}
	zbx_host_availability_t;

	const char			*__function_name = "get_host_availability_data";
	zbx_uint64_t			hostid;
	size_t				sz;
	DB_RESULT			result;
	DB_ROW				row;
	static zbx_host_availability_t	*ha = NULL;
	static int			ha_alloc = 0, ha_num = 0;
	int				index, new, ret = FAIL;
	unsigned char			available, snmp_available, ipmi_available, jmx_available;
	char				*error, *snmp_error, *ipmi_error, *jmx_error;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	result = DBselect(
			"select hostid,available,error,snmp_available,snmp_error,"
				"ipmi_available,ipmi_error,jmx_available,jmx_error"
			" from hosts"
			" where status in (%d,%d)",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);

		new = 0;

		index = get_nearestindex(ha, sizeof(zbx_host_availability_t), ha_num, hostid);

		if (index == ha_num || ha[index].hostid != hostid)
		{
			CHECK_ARRAY_SIZE(ha, ha_alloc, ha_num);

			if (0 != (sz = sizeof(zbx_host_availability_t) * (ha_num - index)))
				memmove(&ha[index + 1], &ha[index], sz);
			ha_num++;

			ha[index].hostid = hostid;
			ha[index].available = HOST_AVAILABLE_UNKNOWN;
			ha[index].snmp_available = HOST_AVAILABLE_UNKNOWN;
			ha[index].ipmi_available = HOST_AVAILABLE_UNKNOWN;
			ha[index].jmx_available = HOST_AVAILABLE_UNKNOWN;
			ha[index].error = NULL;
			ha[index].snmp_error = NULL;
			ha[index].ipmi_error = NULL;
			ha[index].jmx_error = NULL;

			new = 1;
		}

		available = (unsigned char)atoi(row[1]);
		error = row[2];
		snmp_available = (unsigned char)atoi(row[3]);
		snmp_error = row[4];
		ipmi_available = (unsigned char)atoi(row[5]);
		ipmi_error = row[6];
		jmx_available = (unsigned char)atoi(row[7]);
		jmx_error = row[8];

		if (0 == new && ha[index].available == available &&
				ha[index].snmp_available == snmp_available &&
				ha[index].ipmi_available == ipmi_available &&
				ha[index].jmx_available == jmx_available &&
				0 == strcmp(ha[index].error, error) &&
				0 == strcmp(ha[index].snmp_error, snmp_error) &&
				0 == strcmp(ha[index].ipmi_error, ipmi_error) &&
				0 == strcmp(ha[index].jmx_error, jmx_error))
			continue;

		zbx_json_addobject(j, NULL);

		zbx_json_adduint64(j, ZBX_PROTO_TAG_HOSTID, hostid);

		if (1 == new || ha[index].available != available)
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_AVAILABLE, available);
			ha[index].available = available;
		}

		if (1 == new || ha[index].snmp_available != snmp_available)
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_SNMP_AVAILABLE, snmp_available);
			ha[index].snmp_available = snmp_available;
		}

		if (1 == new || ha[index].ipmi_available != ipmi_available)
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_IPMI_AVAILABLE, ipmi_available);
			ha[index].ipmi_available = ipmi_available;
		}

		if (1 == new || ha[index].jmx_available != jmx_available)
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_JMX_AVAILABLE, jmx_available);
			ha[index].jmx_available = jmx_available;
		}

		if (1 == new || 0 != strcmp(ha[index].error, error))
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_ERROR, error, ZBX_JSON_TYPE_STRING);
			ZBX_STRDUP(ha[index].error, error);
		}

		if (1 == new || 0 != strcmp(ha[index].snmp_error, snmp_error))
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_SNMP_ERROR, snmp_error, ZBX_JSON_TYPE_STRING);
			ZBX_STRDUP(ha[index].snmp_error, snmp_error);
		}

		if (1 == new || 0 != strcmp(ha[index].ipmi_error, ipmi_error))
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_IPMI_ERROR, ipmi_error, ZBX_JSON_TYPE_STRING);
			ZBX_STRDUP(ha[index].ipmi_error, ipmi_error);
		}

		if (1 == new || 0 != strcmp(ha[index].jmx_error, jmx_error))
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_JMX_ERROR, jmx_error, ZBX_JSON_TYPE_STRING);
			ZBX_STRDUP(ha[index].jmx_error, jmx_error);
		}

		zbx_json_close(j);

		ret = SUCCEED;
	}
	DBfree_result(result);

	zbx_json_close(j);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_host_availability                                        *
 *                                                                            *
 * Purpose: update proxy hosts availability                                   *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	process_host_availability(struct zbx_json_parse *jp)
{
	const char		*__function_name = "process_host_availability";
	zbx_uint64_t		hostid;
	struct zbx_json_parse	jp_data, jp_row;
	const char		*p = NULL;
	char			tmp[HOST_ERROR_LEN_MAX], *sql = NULL, *error_esc;
	size_t			sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset = 0, tmp_offset;
	int			availability_alloc = 0, availability_num = 0;
	zbx_host_availability_t	*availability = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* "data" tag lists the hosts */
	if (SUCCEED != zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid host availability data: %s", zbx_json_strerror());
		goto out;
	}

	if (SUCCEED == zbx_json_object_is_empty(&jp_data))
		goto out;

	sql = zbx_malloc(sql, sql_alloc);

	DBbegin();

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	while (NULL != (p = zbx_json_next(&jp_data, p)))	/* iterate the host entries */
	{
		if (SUCCEED != zbx_json_brackets_open(p, &jp_row))
		{
			zabbix_log(LOG_LEVEL_WARNING, "invalid host availability data: %s", zbx_json_strerror());
			continue;
		}

		if (SUCCEED != zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_HOSTID, tmp, sizeof(tmp)) ||
				SUCCEED != is_uint64(tmp, &hostid))
		{
			zabbix_log(LOG_LEVEL_WARNING, "invalid host availability data: %s", zbx_json_strerror());
			continue;
		}

		tmp_offset = sql_offset;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update hosts set ");

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_AVAILABLE, tmp, sizeof(tmp)))
		{
			CHECK_ARRAY_SIZE(availability, availability_alloc, availability_num);

			availability[availability_num].hostid = hostid;
			availability[availability_num].type = ITEM_TYPE_ZABBIX;
			availability[availability_num].available = atoi(tmp);
			availability[availability_num].errors_from = 0;
			availability[availability_num].disable_until = 0;

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "available=%d,",
					availability[availability_num].available);

			availability_num++;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_SNMP_AVAILABLE, tmp, sizeof(tmp)))
		{
			CHECK_ARRAY_SIZE(availability, availability_alloc, availability_num);

			availability[availability_num].hostid = hostid;
			availability[availability_num].type = ITEM_TYPE_SNMPv1;
			availability[availability_num].available = atoi(tmp);
			availability[availability_num].errors_from = 0;
			availability[availability_num].disable_until = 0;

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "snmp_available=%d,",
					availability[availability_num].available);

			availability_num++;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IPMI_AVAILABLE, tmp, sizeof(tmp)))
		{
			CHECK_ARRAY_SIZE(availability, availability_alloc, availability_num);

			availability[availability_num].hostid = hostid;
			availability[availability_num].type = ITEM_TYPE_IPMI;
			availability[availability_num].available = atoi(tmp);
			availability[availability_num].errors_from = 0;
			availability[availability_num].disable_until = 0;

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "ipmi_available=%d,",
					availability[availability_num].available);

			availability_num++;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_JMX_AVAILABLE, tmp, sizeof(tmp)))
		{
			CHECK_ARRAY_SIZE(availability, availability_alloc, availability_num);

			availability[availability_num].hostid = hostid;
			availability[availability_num].type = ITEM_TYPE_JMX;
			availability[availability_num].available = atoi(tmp);
			availability[availability_num].errors_from = 0;
			availability[availability_num].disable_until = 0;

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "jmx_available=%d,",
					availability[availability_num].available);

			availability_num++;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_ERROR, tmp, sizeof(tmp)))
		{
			error_esc = DBdyn_escape_string_len(tmp, HOST_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "error='%s',", error_esc);
			zbx_free(error_esc);
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_SNMP_ERROR, tmp, sizeof(tmp)))
		{
			error_esc = DBdyn_escape_string_len(tmp, HOST_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "snmp_error='%s',", error_esc);
			zbx_free(error_esc);
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IPMI_ERROR, tmp, sizeof(tmp)))
		{
			error_esc = DBdyn_escape_string_len(tmp, HOST_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "ipmi_error='%s',", error_esc);
			zbx_free(error_esc);
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_JMX_ERROR, tmp, sizeof(tmp)))
		{
			error_esc = DBdyn_escape_string_len(tmp, HOST_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "jmx_error='%s',", error_esc);
			zbx_free(error_esc);
		}

		sql_offset--;

		if (',' != sql[sql_offset])
		{
			zabbix_log(LOG_LEVEL_WARNING, "invalid host availability data");
			sql_offset = tmp_offset;
			continue;
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where hostid=" ZBX_FS_UI64 ";\n", hostid);

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	DBcommit();

	DChost_update_availability(availability, availability_num);
out:
	zbx_free(availability);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_lastid                                                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	proxy_get_lastid(const char *table_name, const char *lastidfield, zbx_uint64_t *lastid)
{
	const char	*__function_name = "proxy_get_lastid";
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() field:'%s.%s'", __function_name, table_name, lastidfield);

	result = DBselect("select nextid from ids where table_name='%s' and field_name='%s'",
			table_name, lastidfield);

	if (NULL == (row = DBfetch(result)))
		*lastid = 0;
	else
		ZBX_STR2UINT64(*lastid, row[0]);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64,	__function_name, *lastid);
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_set_lastid                                                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	proxy_set_lastid(const char *table_name, const char *lastidfield, const zbx_uint64_t lastid)
{
	const char	*__function_name = "proxy_set_lastid";
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [%s.%s:" ZBX_FS_UI64 "]",
			__function_name, table_name, lastidfield, lastid);

	result = DBselect("select 1 from ids where table_name='%s' and field_name='%s'",
			table_name, lastidfield);

	if (NULL == (row = DBfetch(result)))
	{
		DBexecute("insert into ids (nodeid,table_name,field_name,nextid)"
				"values (0,'%s','%s'," ZBX_FS_UI64 ")",
				table_name, lastidfield, lastid);
	}
	else
	{
		DBexecute("update ids set nextid=" ZBX_FS_UI64
				" where table_name='%s' and field_name='%s'",
				lastid, table_name, lastidfield);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	proxy_set_hist_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid("proxy_history", "history_lastid", lastid);
}

void	proxy_set_dhis_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid(dht.table, dht.lastidfield, lastid);
}

void	proxy_set_areg_lastid(const zbx_uint64_t lastid)
{
	proxy_set_lastid(areg.table, areg.lastidfield, lastid);
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_history_data_simple                                    *
 *                                                                            *
 * Purpose: Get history data from the database.                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static int	proxy_get_history_data_simple(struct zbx_json *j, const zbx_history_table_t *ht, zbx_uint64_t *lastid)
{
	const char	*__function_name = "proxy_get_history_data_simple";
	size_t		offset = 0;
	int		f, records = 0, records_lim = ZBX_MAX_HRECORDS, retries = 1;
	char		sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	id;
	struct timespec	t_sleep = { 0, 100000000L }, t_rem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s'", __function_name, ht->table);

	*lastid = 0;

	proxy_get_lastid(ht->table, ht->lastidfield, &id);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "select id");

	for (f = 0; NULL != ht->fields[f].field; f++)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, ",%s", ht->fields[f].field);
try_again:
	zbx_snprintf(sql + offset, sizeof(sql) - offset, " from %s where id>" ZBX_FS_UI64 " order by id",
			ht->table, id);

	result = DBselectN(sql, records_lim);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(*lastid, row[0]);

		if (1 < *lastid - id)
		{
			/* At least one record is missing. It can happen if some DB syncer process has */
			/* started but not yet committed a transaction or a rollback occurred in a DB syncer. */
			if (0 < retries--)
			{
				DBfree_result(result);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing."
						" Waiting " ZBX_FS_DBL " sec, retrying.",
						__function_name, *lastid - id - 1,
						t_sleep.tv_sec + t_sleep.tv_nsec / 1e9);
				nanosleep(&t_sleep, &t_rem);
				goto try_again;
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing. No more retries.",
						__function_name, *lastid - id - 1);
			}
		}

		zbx_json_addobject(j, NULL);

		for (f = 0; NULL != ht->fields[f].field; f++)
		{
			if (NULL != ht->fields[f].default_value && 0 == strcmp(row[f + 1], ht->fields[f].default_value))
				continue;

			zbx_json_addstring(j, ht->fields[f].tag, row[f + 1], ht->fields[f].jt);
		}

		records++;

		zbx_json_close(j);

		id = *lastid;
		records_lim--;
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d lastid:" ZBX_FS_UI64, __function_name, records, *lastid);

	return records;
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_history_data                                           *
 *                                                                            *
 * Purpose: Get history data from the database. Get items configuration from  *
 *          cache to speed things up.                                         *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static int	proxy_get_history_data(struct zbx_json *j, zbx_uint64_t *lastid, zbx_uint64_t * id, int *records_processed)
{
	const char			*__function_name = "proxy_get_history_data";

	typedef struct
	{
		size_t		psource;
		size_t		pvalue;
		int		clock;
		int		ns;
		int		timestamp;
		int		severity;
		int		logeventid;
		unsigned char	state;
	}
	zbx_history_data_t;

	char				sql[144];
	DB_RESULT			result;
	DB_ROW				row;
	static char			*string_buffer = NULL;
	static size_t			string_buffer_alloc = ZBX_KIBIBYTE;
	size_t				string_buffer_offset = 0, len1, len2;
	static zbx_uint64_t		*itemids = NULL;
	static zbx_history_data_t	*data = NULL;
	static size_t			data_alloc = 0;
	size_t				data_num = 0, i;
	DC_ITEM				*dc_items;
	int				*errcodes, records = 0, records_lim = ZBX_MAX_HRECORDS, retries = 1;
	zbx_history_data_t		*hd;
	struct timespec			t_sleep = { 0, 100000000L }, t_rem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == string_buffer)
		string_buffer = zbx_malloc(string_buffer, string_buffer_alloc);

	*lastid = 0;

try_again:
	zbx_snprintf(sql, sizeof(sql),
			"select id,itemid,clock,ns,timestamp,source,severity,value,logeventid,state"
			" from proxy_history"
			" where id>" ZBX_FS_UI64
			" order by id",
			*id);

	result = DBselectN(sql, records_lim);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(*lastid, row[0]);

		if (1 < *lastid - *id)
		{
			/* At least one record is missing. It can happen if some DB syncer process has */
			/* started but not yet committed a transaction or a rollback occurred in a DB syncer. */
			if (0 < retries--)
			{
				DBfree_result(result);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing."
						" Waiting " ZBX_FS_DBL " sec, retrying.",
						__function_name, *lastid - *id - 1,
						t_sleep.tv_sec + t_sleep.tv_nsec / 1e9);
				nanosleep(&t_sleep, &t_rem);
				goto try_again;
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() " ZBX_FS_UI64 " record(s) missing. No more retries.",
						__function_name, *lastid - *id - 1);
			}
		}

		if (data_alloc == data_num)
		{
			data_alloc += 8;
			data = zbx_realloc(data, sizeof(zbx_history_data_t) * data_alloc);
			itemids = zbx_realloc(itemids, sizeof(zbx_uint64_t) * data_alloc);
		}

		ZBX_STR2UINT64(itemids[data_num], row[1]);

		hd = &data[data_num++];
		hd->clock = atoi(row[2]);
		hd->ns = atoi(row[3]);
		hd->timestamp = atoi(row[4]);
		hd->severity = atoi(row[6]);
		hd->logeventid = atoi(row[8]);
		hd->state = (unsigned char)atoi(row[9]);

		len1 = strlen(row[5]) + 1;
		len2 = strlen(row[7]) + 1;

		if (string_buffer_alloc < string_buffer_offset + len1 + len2)
		{
			while (string_buffer_alloc < string_buffer_offset + len1 + len2)
				string_buffer_alloc += ZBX_KIBIBYTE;

			string_buffer = zbx_realloc(string_buffer, string_buffer_alloc);
		}

		hd->psource = string_buffer_offset;
		memcpy(&string_buffer[string_buffer_offset], row[5], len1);
		string_buffer_offset += len1;
		hd->pvalue = string_buffer_offset;
		memcpy(&string_buffer[string_buffer_offset], row[7], len2);
		string_buffer_offset += len2;

		*id = *lastid;
		records_lim--;
	}
	DBfree_result(result);

	dc_items = zbx_malloc(NULL, (sizeof(DC_ITEM) + sizeof(int)) * data_num);
	errcodes = (int *)(dc_items + data_num);

	DCconfig_get_items_by_itemids(dc_items, itemids, errcodes, data_num);

	for (i = 0; i < data_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		if (ITEM_STATUS_ACTIVE != dc_items[i].status)
			continue;

		if (HOST_STATUS_MONITORED != dc_items[i].host.status)
			continue;

		zbx_json_addobject(j, NULL);

		zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, dc_items[i].host.host, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(j, ZBX_PROTO_TAG_KEY, dc_items[i].key_orig, ZBX_JSON_TYPE_STRING);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_CLOCK, data[i].clock);
		zbx_json_adduint64(j, ZBX_PROTO_TAG_NS, data[i].ns);
		if (0 != data[i].timestamp)
			zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGTIMESTAMP, data[i].timestamp);
		if ('\0' != string_buffer[data[i].psource])
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_LOGSOURCE, &string_buffer[data[i].psource],
					ZBX_JSON_TYPE_STRING);
		}
		if (0 != data[i].severity)
			zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGSEVERITY, data[i].severity);
		zbx_json_addstring(j, ZBX_PROTO_TAG_VALUE, &string_buffer[data[i].pvalue], ZBX_JSON_TYPE_STRING);
		if (0 != data[i].logeventid)
			zbx_json_adduint64(j, ZBX_PROTO_TAG_LOGEVENTID, data[i].logeventid);
		if (0 != data[i].state)
			zbx_json_adduint64(j, ZBX_PROTO_TAG_STATE, data[i].state);

		zbx_json_close(j);

		records++;
	}
	DCconfig_clean_items(dc_items, errcodes, data_num);
	zbx_free(dc_items);

	*records_processed = data_num;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d records_processed:%d lastid:" ZBX_FS_UI64,
			__function_name, records, *records_processed, *lastid);

	return records;
}

int	proxy_get_hist_data(struct zbx_json *j, zbx_uint64_t *lastid)
{
	int		records = 0, records_processed;
	zbx_uint64_t	id;

	proxy_get_lastid("proxy_history", "history_lastid", &id);

	do
	{
		records += proxy_get_history_data(j, lastid, &id, &records_processed);
	}
	while (ZBX_MAX_HRECORDS > records && ZBX_MAX_HRECORDS == records_processed);

	return records;
}

int	proxy_get_dhis_data(struct zbx_json *j, zbx_uint64_t *lastid)
{
	return proxy_get_history_data_simple(j, &dht, lastid);
}

int	proxy_get_areg_data(struct zbx_json *j, zbx_uint64_t *lastid)
{
	return proxy_get_history_data_simple(j, &areg, lastid);
}

void	calc_timestamp(const char *line, int *timestamp, const char *format)
{
	const char	*__function_name = "calc_timestamp";
	int		hh, mm, ss, yyyy, dd, MM;
	int		hhc = 0, mmc = 0, ssc = 0, yyyyc = 0, ddc = 0, MMc = 0;
	int		i, num;
	struct tm	tm;
	time_t		t;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	hh = mm = ss = yyyy = dd = MM = 0;

	for (i = 0; '\0' != format[i] && '\0' != line[i]; i++)
	{
		if (0 == isdigit(line[i]))
			continue;

		num = (int)line[i] - 48;

		switch ((char)format[i])
		{
			case 'h':
				hh = 10 * hh + num;
				hhc++;
				break;
			case 'm':
				mm = 10 * mm + num;
				mmc++;
				break;
			case 's':
				ss = 10 * ss + num;
				ssc++;
				break;
			case 'y':
				yyyy = 10 * yyyy + num;
				yyyyc++;
				break;
			case 'd':
				dd = 10 * dd + num;
				ddc++;
				break;
			case 'M':
				MM = 10 * MM + num;
				MMc++;
				break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() %02d:%02d:%02d %02d/%02d/%04d",
			__function_name, hh, mm, ss, MM, dd, yyyy);

	/* seconds can be ignored, no ssc here */
	if (0 != hhc && 0 != mmc && 0 != yyyyc && 0 != ddc && 0 != MMc)
	{
		tm.tm_sec = ss;
		tm.tm_min = mm;
		tm.tm_hour = hh;
		tm.tm_mday = dd;
		tm.tm_mon = MM - 1;
		tm.tm_year = yyyy - 1900;
		tm.tm_isdst = -1;

		if (0 < (t = mktime(&tm)))
			*timestamp = t;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() timestamp:%d", __function_name, *timestamp);
}

/******************************************************************************
 *                                                                            *
 * Function: process_mass_data                                                *
 *                                                                            *
 * Purpose: process new item value                                            *
 *                                                                            *
 * Parameters: sock         - [IN] descriptor of agent-server socket          *
 *                                 connection. NULL for proxy connection      *
 *             proxy_hostid - [IN] proxy identificator from database          *
 *             values       - [IN] array of incoming values                   *
 *             values_num   - [IN] number of elements in array                *
 *             processed    - [OUT] number of processed elements              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	process_mass_data(zbx_sock_t *sock, zbx_uint64_t proxy_hostid,
		AGENT_VALUE *values, size_t values_num, int *processed)
{
	const char	*__function_name = "process_mass_data";
	AGENT_RESULT	agent;
	DC_ITEM		*items = NULL;
	zbx_host_key_t	*keys = NULL;
	size_t		i;
	zbx_uint64_t	*itemids = NULL, *lastlogsizes = NULL;
	unsigned char	*states = NULL;
	int		*lastclocks = NULL, *errcodes = NULL, *mtimes = NULL, *errcodes2 = NULL;
	size_t		num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	keys = zbx_malloc(keys, sizeof(zbx_host_key_t) * values_num);
	items = zbx_malloc(items, sizeof(DC_ITEM) * values_num);
	errcodes = zbx_malloc(errcodes, sizeof(int) * values_num);
	itemids = zbx_malloc(itemids, sizeof(zbx_uint64_t) * values_num);
	states = zbx_malloc(states, sizeof(unsigned char) * values_num);
	lastclocks = zbx_malloc(lastclocks, sizeof(int) * values_num);
	lastlogsizes = zbx_malloc(lastlogsizes, sizeof(zbx_uint64_t) * values_num);
	mtimes = zbx_malloc(mtimes, sizeof(int) * values_num);
	errcodes2 = zbx_malloc(errcodes2, sizeof(int) * values_num);

	for (i = 0; i < values_num; i++)
	{
		keys[i].host = values[i].host_name;
		keys[i].key = values[i].key;
	}

	DCconfig_get_items_by_keys(items, keys, errcodes, values_num);

	for (i = 0; i < values_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		if (proxy_hostid != items[i].host.proxy_hostid)
			continue;

		if (ITEM_STATUS_ACTIVE != items[i].status)
			continue;

		if (HOST_STATUS_MONITORED != items[i].host.status)
			continue;

		if (HOST_MAINTENANCE_STATUS_ON == items[i].host.maintenance_status &&
				MAINTENANCE_TYPE_NODATA == items[i].host.maintenance_type &&
				items[i].host.maintenance_from <= values[i].ts.sec)
			continue;

		if (ITEM_TYPE_AGGREGATE == items[i].type || ITEM_TYPE_CALCULATED == items[i].type)
			continue;

		if (0 == proxy_hostid && ITEM_TYPE_TRAPPER != items[i].type && ITEM_TYPE_ZABBIX_ACTIVE != items[i].type)
			continue;

		if (ITEM_TYPE_TRAPPER == items[i].type && 0 == proxy_hostid)
		{
			int	security_check;
			char	*allowed_hosts;

			allowed_hosts = zbx_strdup(NULL, items[i].trapper_hosts);
			substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, &items[i], &allowed_hosts,
					MACRO_TYPE_PARAMS_FIELD, NULL, 0);
			security_check = zbx_tcp_check_security(sock, allowed_hosts, 1);
			zbx_free(allowed_hosts);

			if (FAIL == security_check)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot process trapper item \"%s\": %s",
						items[i].key_orig, zbx_tcp_strerror());
				continue;
			}
		}

		if (ITEM_STATE_NOTSUPPORTED == values[i].state || 0 == strcmp(values[i].value, ZBX_NOTSUPPORTED))
		{
			items[i].state = ITEM_STATE_NOTSUPPORTED;
			dc_add_history(items[i].itemid, items[i].value_type, items[i].flags, NULL, &values[i].ts,
					items[i].state, values[i].value);

			if (NULL != processed)
				(*processed)++;
		}
		else
		{
			init_result(&agent);

			if (SUCCEED == set_result_type(&agent, items[i].value_type,
					proxy_hostid ? ITEM_DATA_TYPE_DECIMAL : items[i].data_type, values[i].value))
			{
				if (ITEM_VALUE_TYPE_LOG == items[i].value_type)
				{
					zbx_log_t	*log;

					log = agent.logs[0];

					log->timestamp = values[i].timestamp;
					if (NULL != values[i].source)
					{
						zbx_replace_invalid_utf8(values[i].source);
						log->source = zbx_strdup(log->source, values[i].source);
					}
					log->severity = values[i].severity;
					log->logeventid = values[i].logeventid;
					log->lastlogsize = values[i].lastlogsize;
					log->mtime = values[i].mtime;

					calc_timestamp(log->value, &log->timestamp, items[i].logtimefmt);
				}

				items[i].state = ITEM_STATE_NORMAL;
				dc_add_history(items[i].itemid, items[i].value_type, items[i].flags, &agent,
						&values[i].ts, items[i].state, NULL);

				if (NULL != processed)
					(*processed)++;
			}
			else if (ISSET_MSG(&agent))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "item [%s:%s] error: %s",
						items[i].host.host, items[i].key_orig, agent.msg);

				items[i].state = ITEM_STATE_NOTSUPPORTED;
				dc_add_history(items[i].itemid, items[i].value_type, items[i].flags, NULL,
						&values[i].ts, items[i].state, agent.msg);
			}
			else
				THIS_SHOULD_NEVER_HAPPEN; /* set_result_type() always sets MSG result if not SUCCEED */

			free_result(&agent);
		}

		itemids[num] = items[i].itemid;
		states[num] = items[i].state;
		lastclocks[num] = values[i].ts.sec;
		lastlogsizes[num] = values[i].lastlogsize;
		mtimes[num] = values[i].mtime;
		errcodes2[num] = SUCCEED;
		num++;
	}

	DCconfig_clean_items(items, errcodes, values_num);

	DCrequeue_items(itemids, states, lastclocks, lastlogsizes, mtimes, errcodes2, num);

	zbx_free(errcodes2);
	zbx_free(mtimes);
	zbx_free(lastlogsizes);
	zbx_free(lastclocks);
	zbx_free(states);
	zbx_free(itemids);
	zbx_free(errcodes);
	zbx_free(items);
	zbx_free(keys);

	dc_flush_history();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	clean_agent_values(AGENT_VALUE *values, size_t values_num)
{
	size_t	i;

	for (i = 0; i < values_num; i++)
	{
		zbx_free(values[i].value);
		zbx_free(values[i].source);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: process_hist_data                                                *
 *                                                                            *
 * Purpose: process values sent by proxies, active agents and senders         *
 *                                                                            *
 * Parameters: sock         - [IN] descriptor of agent-server socket          *
 *                                 connection. NULL for proxy connection      *
 *             jp           - [IN] JSON with historical data                  *
 *             proxy_hostid - [IN] proxy identificator from database          *
 *             info         - [OUT] address of a pointer to the info string   *
 *                                  (should be freed by the caller)           *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev, Alexei Vladishev                              *
 *                                                                            *
 ******************************************************************************/
int	process_hist_data(zbx_sock_t *sock, struct zbx_json_parse *jp, const zbx_uint64_t proxy_hostid, char **info)
{
#define VALUES_MAX	256
	const char		*__function_name = "process_hist_data";
	struct zbx_json_parse	jp_data, jp_row;
	const char		*p;
	char			*tmp = NULL;
	size_t			tmp_alloc = 0, values_num = 0;
	int			ret = FAIL, processed = 0, total_num = 0;
	double			sec;
	zbx_timespec_t		ts, proxy_timediff;
	static AGENT_VALUE	*values = NULL, *av;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sec = zbx_time();

	zbx_timespec(&ts);
	proxy_timediff.sec = 0;
	proxy_timediff.ns = 0;

	if (NULL == values)
		values = zbx_malloc(values, VALUES_MAX * sizeof(AGENT_VALUE));

	if (SUCCEED == zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_CLOCK, &tmp, &tmp_alloc))
	{
		proxy_timediff.sec = ts.sec - atoi(tmp);

		if (SUCCEED == zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_NS, &tmp, &tmp_alloc))
		{
			proxy_timediff.ns = ts.ns - atoi(tmp);

			if (proxy_timediff.ns < 0)
			{
				proxy_timediff.sec--;
				proxy_timediff.ns += 1000000000;
			}
		}
	}

	/* "data" tag lists the item keys */
	if (NULL == (p = zbx_json_pair_by_name(jp, ZBX_PROTO_TAG_DATA)))
		zabbix_log(LOG_LEVEL_WARNING, "cannot find \"data\" pair");
	else if (FAIL == zbx_json_brackets_open(p, &jp_data))
		zabbix_log(LOG_LEVEL_WARNING, "cannot process json request: %s", zbx_json_strerror());
	else
		ret = SUCCEED;

	if (SUCCEED == ret && 0 != proxy_hostid)
		DCconfig_set_proxy_timediff(proxy_hostid, &proxy_timediff);

	p = NULL;
	while (SUCCEED == ret && NULL != (p = zbx_json_next(&jp_data, p)))	/* iterate the item key entries */
	{
		if (FAIL == (ret = zbx_json_brackets_open(p, &jp_row)))
			break;

		total_num++;

		av = &values[values_num];

		memset(av, 0, sizeof(AGENT_VALUE));

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_CLOCK, &tmp, &tmp_alloc))
		{
			av->ts.sec = atoi(tmp) + proxy_timediff.sec;

			if (SUCCEED == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_NS, &tmp, &tmp_alloc))
			{
				av->ts.ns = atoi(tmp) + proxy_timediff.ns;

				if (av->ts.ns > 999999999)
				{
					av->ts.sec++;
					av->ts.ns -= 1000000000;
				}
			}
			else
				av->ts.ns = proxy_timediff.ns;
		}
		else
			zbx_timespec(&av->ts);

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_HOST, av->host_name, sizeof(av->host_name)))
			continue;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_KEY, av->key, sizeof(av->key)))
			continue;

		if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_VALUE, &tmp, &tmp_alloc))
			continue;

		av->value = zbx_strdup(av->value, tmp);

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_LOGLASTSIZE, &tmp, &tmp_alloc))
			is_uint64(tmp, &av->lastlogsize);

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_MTIME, &tmp, &tmp_alloc))
			av->mtime = atoi(tmp);

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_LOGTIMESTAMP, &tmp, &tmp_alloc))
			av->timestamp = atoi(tmp);

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_LOGSOURCE, &tmp, &tmp_alloc))
			av->source = strdup(tmp);

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_LOGSEVERITY, &tmp, &tmp_alloc))
			av->severity = atoi(tmp);

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_LOGEVENTID, &tmp, &tmp_alloc))
			av->logeventid = atoi(tmp);

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_STATE, &tmp, &tmp_alloc))
			av->state = (unsigned char)atoi(tmp);

		values_num++;

		if (VALUES_MAX == values_num)
		{
			process_mass_data(sock, proxy_hostid, values, values_num, &processed);

			clean_agent_values(values, values_num);
			values_num = 0;
		}
	}

	zbx_free(tmp);

	if (0 < values_num)
		process_mass_data(sock, proxy_hostid, values, values_num, &processed);

	clean_agent_values(values, values_num);

	if (NULL != info)
	{
		*info = zbx_dsprintf(*info, "processed: %d; failed: %d; total: %d; seconds spent: " ZBX_FS_DBL,
				processed, total_num - processed, total_num, zbx_time() - sec);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_dhis_data                                                *
 *                                                                            *
 * Purpose: update discovery data, received from proxy                        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	process_dhis_data(struct zbx_json_parse *jp)
{
	const char		*__function_name = "process_dhis_data";
	DB_RESULT		result;
	DB_ROW			row;
	DB_DRULE		drule;
	DB_DCHECK		dcheck;
	DB_DHOST		dhost;
	zbx_uint64_t		last_druleid = 0;
	struct zbx_json_parse	jp_data, jp_row;
	int			port, status, ret;
	const char		*p = NULL;
	char			last_ip[INTERFACE_IP_LEN_MAX], ip[INTERFACE_IP_LEN_MAX], key_[ITEM_KEY_LEN * 4 + 1],
				tmp[MAX_STRING_LEN], value[DSERVICE_VALUE_LEN_MAX], dns[INTERFACE_DNS_LEN_MAX];
	time_t			now, hosttime, itemtime;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	now = time(NULL);

	if (SUCCEED != (ret = zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp))))
		goto exit;

	if (SUCCEED != (ret = zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data)))
		goto exit;

	hosttime = atoi(tmp);

	memset(&drule, 0, sizeof(drule));
	*last_ip = '\0';

	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
		if (FAIL == (ret = zbx_json_brackets_open(p, &jp_row)))
			break;

		memset(&dcheck, 0, sizeof(dcheck));
		*key_ = '\0';
		*value = '\0';
		*dns = '\0';
		port = 0;
		status = 0;
		dcheck.key_ = key_;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
			goto json_parse_error;
		itemtime = now - (hosttime - atoi(tmp));

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DRULE, tmp, sizeof(tmp)))
			goto json_parse_error;
		ZBX_STR2UINT64(drule.druleid, tmp);

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_TYPE, tmp, sizeof(tmp)))
			goto json_parse_error;
		dcheck.type = atoi(tmp);

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DCHECK, tmp, sizeof(tmp)))
			goto json_parse_error;
		if ('\0' != *tmp)
			ZBX_STR2UINT64(dcheck.dcheckid, tmp);
		else if (-1 != dcheck.type)
			goto json_parse_error;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IP, ip, sizeof(ip)))
			goto json_parse_error;

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp)))
			port = atoi(tmp);

		zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_KEY, key_, sizeof(key_));
		zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_VALUE, value, sizeof(value));
		zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DNS, dns, sizeof(dns));

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_STATUS, tmp, sizeof(tmp)))
			status = atoi(tmp);

		if (0 == last_druleid || drule.druleid != last_druleid)
		{
			result = DBselect(
					"select dcheckid"
					" from dchecks"
					" where druleid=" ZBX_FS_UI64
						" and uniq=1",
					drule.druleid);

			if (NULL != (row = DBfetch(result)))
				ZBX_STR2UINT64(drule.unique_dcheckid, row[0]);

			DBfree_result(result);

			last_druleid = drule.druleid;
		}

		if ('\0' == *last_ip || 0 != strcmp(ip, last_ip))
		{
			memset(&dhost, 0, sizeof(dhost));
			strscpy(last_ip, ip);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() druleid:" ZBX_FS_UI64 " dcheckid:" ZBX_FS_UI64 " unique_dcheckid:"
				ZBX_FS_UI64 " type:%d time:'%s %s' ip:'%s' dns:'%s' port:%d key:'%s' value:'%s'",
				__function_name, drule.druleid, dcheck.dcheckid, drule.unique_dcheckid, dcheck.type,
				zbx_date2str(itemtime), zbx_time2str(itemtime), ip, dns, port, dcheck.key_, value);

		DBbegin();

		if (-1 == dcheck.type)
		{
			if (SUCCEED != DBlock_druleid(drule.druleid))
			{
				DBrollback();

				zabbix_log(LOG_LEVEL_DEBUG, "druleid:" ZBX_FS_UI64 " does not exist", drule.druleid);

				goto next;
			}

			discovery_update_host(&dhost, ip, status, itemtime);
		}
		else
		{
			if (SUCCEED != DBlock_dcheckid(dcheck.dcheckid, drule.druleid))
			{
				DBrollback();

				zabbix_log(LOG_LEVEL_DEBUG, "dcheckid:" ZBX_FS_UI64 " either does not exist or does not"
						" belong to druleid:" ZBX_FS_UI64, dcheck.dcheckid, drule.druleid);

				goto next;
			}

			discovery_update_service(&drule, &dcheck, &dhost, ip, dns, port, status, value, itemtime);
		}

		DBcommit();
next:
		continue;
json_parse_error:
		zabbix_log(LOG_LEVEL_WARNING, "invalid discovery data: %s", zbx_json_strerror());
	}
exit:
	if (SUCCEED != ret)
		zabbix_log(LOG_LEVEL_WARNING, "invalid discovery data: %s", zbx_json_strerror());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Function: process_areg_data                                                *
 *                                                                            *
 * Purpose: update auto-registration data, received from proxy                *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	process_areg_data(struct zbx_json_parse *jp, zbx_uint64_t proxy_hostid)
{
	const char		*__function_name = "process_areg_data";

	struct zbx_json_parse	jp_data, jp_row;
	int			ret;
	const char		*p = NULL;
	time_t			now, hosttime, itemtime;
	char			host[HOST_HOST_LEN_MAX], ip[INTERFACE_IP_LEN_MAX], dns[INTERFACE_DNS_LEN_MAX],
				tmp[MAX_STRING_LEN], *host_metadata = NULL;
	unsigned short		port;
	size_t			host_metadata_alloc = 1;	/* for at least NUL-termination char */
	zbx_vector_ptr_t	discovered_hosts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&discovered_hosts);

	now = time(NULL);

	if (SUCCEED != (ret = zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp))))
		goto exit;

	if (SUCCEED != (ret = zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data)))
		goto exit;

	if (SUCCEED != DBregister_host_active())
		goto exit;

	zbx_vector_ptr_reserve(&discovered_hosts, ZBX_MAX_HRECORDS);

	hosttime = atoi(tmp);

	host_metadata = zbx_malloc(host_metadata, host_metadata_alloc);

	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
		if (FAIL == (ret = zbx_json_brackets_open(p, &jp_row)))
			break;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
			goto json_parse_error;
		itemtime = now - (hosttime - atoi(tmp));

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_HOST, host, sizeof(host)))
			goto json_parse_error;

		if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_HOST_METADATA,
				&host_metadata, &host_metadata_alloc))
		{
			*host_metadata = '\0';
		}

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IP, ip, sizeof(ip)))
			*ip = '\0';

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DNS, dns, sizeof(dns)))
			*dns = '\0';

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp)))
			*tmp = '\0';

		if (FAIL == is_ushort(tmp, &port))
			port = ZBX_DEFAULT_AGENT_PORT;

		DBregister_host_prepare(&discovered_hosts, proxy_hostid, host, ip, dns, port, host_metadata, itemtime);

		continue;
json_parse_error:
		zabbix_log(LOG_LEVEL_WARNING, "invalid auto registration data: %s", zbx_json_strerror());
	}

	if (0 != discovered_hosts.values_num)
	{
		DBbegin();
		DBregister_host_flush(&discovered_hosts, proxy_hostid);
		DBcommit();
	}
exit:
	if (SUCCEED != ret)
		zabbix_log(LOG_LEVEL_WARNING, "invalid auto registration data: %s", zbx_json_strerror());

	zbx_free(host_metadata);
	zbx_vector_ptr_destroy(&discovered_hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_history_count                                          *
 *                                                                            *
 * Purpose: get the number of values waiting to be sent to the sever          *
 *                                                                            *
 * Return value: the number of history values                                 *
 *                                                                            *
 ******************************************************************************/
int	proxy_get_history_count()
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	id;
	int 		count = 0;

	proxy_get_lastid("proxy_history", "history_lastid", &id);

	result = DBselect(
			"select count(*)"
			" from proxy_history"
			" where id>" ZBX_FS_UI64,
			id);

	if (NULL != (row = DBfetch(result)))
		count = atoi(row[0]);

	DBfree_result(result);

	return count;
}
