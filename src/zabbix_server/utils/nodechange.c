/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"

#ifndef HAVE_SQLITE3
#include "cfg.h"
#include "db.h"
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Function: convert_expression                                               *
 *                                                                            *
 * Purpose: convert trigger expression to new node ID                         *
 *                                                                            *
 * Parameters: nodeid - new node id                                           *
 *             old_exp - old expression, new_exp - new expression             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	convert_expression(int nodeid, zbx_uint64_t prefix, const char *old_exp,
		char **new_expression, size_t *new_expression_alloc, size_t *new_expression_offset)
{
	enum state_t {NORMAL, ID}	state = NORMAL;
	const char			*c, *p_functionid = NULL;
	zbx_uint64_t			functionid;

	*new_expression_offset = 0;
	**new_expression = '\0';

	for (c = old_exp; '\0' != *c; c++)
	{
		if ('{' == *c)
		{
			state = ID;
			p_functionid = c + 1;
			zbx_chrcpy_alloc(new_expression, new_expression_alloc, new_expression_offset, *c);
		}
		else if (ID == state)
		{
			if ('}' == *c && NULL != p_functionid)
			{
				if (SUCCEED == is_uint64_n(p_functionid, c - p_functionid, &functionid))
				{
					zbx_snprintf_alloc(new_expression, new_expression_alloc, new_expression_offset,
							ZBX_FS_UI64, prefix + functionid);
					zbx_chrcpy_alloc(new_expression, new_expression_alloc, new_expression_offset,
							*c);
				}

				state = NORMAL;
			}
		}
		else
		{
			zbx_chrcpy_alloc(new_expression, new_expression_alloc, new_expression_offset, *c);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: validate_ids                                                     *
 *                                                                            *
 * Purpose: validating of IDs in all tables                                   *
 *                                                                            *
 ******************************************************************************/
static int	validate_ids()
{
	char		sql[42 + ZBX_TABLENAME_LEN + ZBX_FIELDNAME_LEN * 2];
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	max_value;
	int		i, ret = SUCCEED;

	for (i = 0; NULL != tables[i].table; i++)
	{
		const ZBX_TABLE	*table = &tables[i];
		const ZBX_FIELD *field = &table->fields[0];

		if (ZBX_TYPE_ID != field->type || 0 != strcmp(table->recid, field->name))
			continue;

		max_value = (0 != (table->flags & ZBX_SYNC) ? ZBX_DM_MAX_CONFIG_IDS : ZBX_DM_MAX_HISTORY_IDS) - 1;

		zbx_snprintf(sql, sizeof(sql), "select %s from %s where %s>" ZBX_FS_UI64,
				field->name, table->table, field->name, max_value);

		result = DBselectN(sql, 1);

		if (NULL != (row = DBfetch(result)))
		{
			printf("Unable to convert. Some of object IDs are out of range in table \"%s\" (\"%s\" = %s)\n",
					table->table, field->name, row[0]);
			ret = FAIL;
		}
		DBfree_result(result);

		if (FAIL == ret)
			break;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: validate_trigger_expressions                                     *
 *                                                                            *
 * Purpose: validate length of new trigger expressions                        *
 *                                                                            *
 * Parameters: nodeid - new node id                                           *
 *                                                                            *
 ******************************************************************************/
static int	validate_trigger_expressions(int nodeid)
{
	zbx_uint64_t	prefix;
	const ZBX_TABLE	*r_table;
	DB_RESULT	result;
	DB_ROW		row;
	char		*new_expression;
	size_t		new_expression_alloc = ZBX_KIBIBYTE, new_expression_offset = 0;
	int		ret = SUCCEED;

	assert(NULL != (r_table = DBget_table("functions")));

	new_expression = zbx_malloc(NULL, new_expression_alloc);

	prefix = ZBX_DM_MAX_HISTORY_IDS * (zbx_uint64_t)nodeid;
	if (0 != (r_table->flags & ZBX_SYNC))
		prefix += ZBX_DM_MAX_CONFIG_IDS * (zbx_uint64_t)nodeid;

	result = DBselect("select expression,triggerid from triggers");

	while (NULL != (row = DBfetch(result)))
	{
		convert_expression(nodeid, prefix, row[0], &new_expression, &new_expression_alloc,
				&new_expression_offset);

		if (new_expression_offset <= TRIGGER_EXPRESSION_LEN)
			continue;

		printf("Unable to convert. Length of trigger expression is out of range in table"
				" \"triggers\" (\"triggerid\" = %s)\n", row[1]);
		ret = FAIL;
		break;
	}
	DBfree_result(result);

	zbx_free(new_expression);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: convert_trigger_expressions                                      *
 *                                                                            *
 * Purpose: convert trigger expressions to new node ID                        *
 *                                                                            *
 * Parameters: nodeid - new node id                                           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	convert_trigger_expressions(int nodeid)
{
	zbx_uint64_t	prefix;
	const ZBX_TABLE	*r_table;
	DB_RESULT	result;
	DB_ROW		row;
	char		*new_expression, *new_expression_esc;
	size_t		new_expression_alloc = ZBX_KIBIBYTE, new_expression_offset = 0;

	assert(NULL != (r_table = DBget_table("functions")));

	new_expression = zbx_malloc(NULL, new_expression_alloc);

	prefix = ZBX_DM_MAX_HISTORY_IDS * (zbx_uint64_t)nodeid;
	if (0 != (r_table->flags & ZBX_SYNC))
		prefix += ZBX_DM_MAX_CONFIG_IDS * (zbx_uint64_t)nodeid;

	result = DBselect("select expression,triggerid from triggers");

	while (NULL != (row = DBfetch(result)))
	{
		convert_expression(nodeid, prefix, row[0], &new_expression, &new_expression_alloc,
				&new_expression_offset);

		new_expression_esc = DBdyn_escape_string_len(new_expression, TRIGGER_EXPRESSION_LEN);
		DBexecute("update triggers set expression='%s' where triggerid=%s",
				new_expression_esc, row[1]);
		zbx_free(new_expression_esc);
	}
	DBfree_result(result);

	zbx_free(new_expression);
}

/******************************************************************************
 *                                                                            *
 * Function: convert_profiles                                                 *
 *                                                                            *
 * Purpose: convert profiles idx2 and value_id fields to new node ID          *
 *                                                                            *
 * Parameters: nodeid - new node id                                           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	convert_profiles(int nodeid, const char *field_name)
{
	zbx_uint64_t	prefix;

	prefix = (ZBX_DM_MAX_HISTORY_IDS + ZBX_DM_MAX_CONFIG_IDS) * (zbx_uint64_t)nodeid;

	DBexecute("update profiles set %s=%s+" ZBX_FS_UI64 " where %s>0",
			field_name, field_name, prefix, field_name);
}

/******************************************************************************
 *                                                                            *
 * Function: convert_special_field                                            *
 *                                                                            *
 * Purpose: special processing for multipurpose fields                        *
 *                                                                            *
 * Parameters: nodeid - new node id                                           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	convert_special_field(int nodeid, const char *table_name,
		const char *field_name, const char *type_field_name,
		const char *rel_table_name, int type)
{
	zbx_uint64_t	prefix;
	const ZBX_TABLE	*r_table;

	assert(NULL != (r_table = DBget_table(rel_table_name)));

	prefix = ZBX_DM_MAX_HISTORY_IDS * (zbx_uint64_t)nodeid;
	if (0 != (r_table->flags & ZBX_SYNC))
		prefix += ZBX_DM_MAX_CONFIG_IDS * (zbx_uint64_t)nodeid;

	DBexecute("update %s set %s=%s+" ZBX_FS_UI64 " where %s=%d and %s>0",
			table_name, field_name, field_name, prefix, type_field_name, type, field_name);
}

/******************************************************************************
 *                                                                            *
 * Function: convert_condition_values                                         *
 *                                                                            *
 * Purpose: special processing for "value" field in "conditions" table        *
 *                                                                            *
 * Parameters: nodeid - new node id                                           *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
static void	convert_condition_values(int nodeid, const char *rel_table_name, int type)
{
	const ZBX_TABLE	*r_table;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	prefix, value;

	assert(NULL != (r_table = DBget_table(rel_table_name)));

	prefix = ZBX_DM_MAX_HISTORY_IDS * (zbx_uint64_t)nodeid;
	if (0 != (r_table->flags & ZBX_SYNC))
		prefix += ZBX_DM_MAX_CONFIG_IDS * (zbx_uint64_t)nodeid;

	result = DBselect("select conditionid,value"
			" from conditions"
			" where conditiontype=%d",
			type);

	while (NULL != (row = DBfetch(result)))
	{
		if (SUCCEED != is_uint64(row[1], &value))
			continue;

		value += prefix;
		DBexecute("update conditions"
			" set value='" ZBX_FS_UI64 "'"
			" where conditionid=%s",
			value, row[0]);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: change_nodeid                                                    *
 *                                                                            *
 * Purpose: convert database data to new node ID                              *
 *                                                                            *
 * Parameters: nodeid - new node id                                           *
 *                                                                            *
 * Return value: SUCCEED - converted successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	change_nodeid(int nodeid)
{
	struct conv_t
	{
	        const char	*rel;
        	int		type;
	};

	struct special_conv_t
	{
	        const char	*table_name, *field_name, *type_field_name;
		struct conv_t	convs[32];
	};

	struct special_conv_t	special_convs[] =
	{
		{"sysmaps_elements",	"elementid",	"elementtype",
			{
			{"hosts",	SYSMAP_ELEMENT_TYPE_HOST},
			{"sysmaps",	SYSMAP_ELEMENT_TYPE_MAP},
			{"triggers",	SYSMAP_ELEMENT_TYPE_TRIGGER},
			{"groups",	SYSMAP_ELEMENT_TYPE_HOST_GROUP},
			{"images",	SYSMAP_ELEMENT_TYPE_IMAGE},
			{NULL}
			}
		},
		{"events",		"objectid",	"object",
			{
			{"triggers",	EVENT_OBJECT_TRIGGER},
			{"dhosts",	EVENT_OBJECT_DHOST},
			{"dservices",	EVENT_OBJECT_DSERVICE},
			{NULL}
			}
		},
		{"ids",			"nextid",	NULL,
			{
			{NULL}
			}
		},
		{"node_cksum",		"recordid",	NULL,
			{
			{NULL}
			}
		},
		{"screens_items",	"resourceid",	"resourcetype",
			{
			{"graphs",	SCREEN_RESOURCE_GRAPH},
			{"items",	SCREEN_RESOURCE_SIMPLE_GRAPH},
			{"sysmaps",	SCREEN_RESOURCE_MAP},
			{"items",	SCREEN_RESOURCE_PLAIN_TEXT},
			{"groups",	SCREEN_RESOURCE_HOSTS_INFO},
			{"screens",	SCREEN_RESOURCE_SCREEN},
			{"groups",	SCREEN_RESOURCE_TRIGGERS_OVERVIEW},
			{"groups",	SCREEN_RESOURCE_DATA_OVERVIEW},
			{"groups",	SCREEN_RESOURCE_HOSTGROUP_TRIGGERS},
			{"hosts",	SCREEN_RESOURCE_HOST_TRIGGERS},
			{NULL}
			}
		},
		{"auditlog",	"resourceid",	"resourcetype",
			{
			{"users",		AUDIT_RESOURCE_USER},
/*			{"",			AUDIT_RESOURCE_ZABBIX},*/
			{"config",		AUDIT_RESOURCE_ZABBIX_CONFIG},
			{"media_type",		AUDIT_RESOURCE_MEDIA_TYPE},
			{"hosts",		AUDIT_RESOURCE_HOST},
			{"actions",		AUDIT_RESOURCE_ACTION},
			{"graphs",		AUDIT_RESOURCE_GRAPH},
			{"graphs_items",	AUDIT_RESOURCE_GRAPH_ELEMENT},
/*			{"",			AUDIT_RESOURCE_ESCALATION},
			{"",			AUDIT_RESOURCE_ESCALATION_RULE},
			{"",			AUDIT_RESOURCE_AUTOREGISTRATION},*/
			{"usrgrp",		AUDIT_RESOURCE_USER_GROUP},
			{"applications",	AUDIT_RESOURCE_APPLICATION},
			{"triggers",		AUDIT_RESOURCE_TRIGGER},
			{"groups",		AUDIT_RESOURCE_HOST_GROUP},
			{"items",		AUDIT_RESOURCE_ITEM},
			{"images",		AUDIT_RESOURCE_IMAGE},
			{"valuemaps",		AUDIT_RESOURCE_VALUE_MAP},
			{"services",		AUDIT_RESOURCE_IT_SERVICE},
			{"sysmaps",		AUDIT_RESOURCE_MAP},
			{"screens",		AUDIT_RESOURCE_SCREEN},
/*			{"nodes",		AUDIT_RESOURCE_NODE},*/
/*			{"",			AUDIT_RESOURCE_SCENARIO},*/
			{"drules",		AUDIT_RESOURCE_DISCOVERY_RULE},
			{"slideshows",		AUDIT_RESOURCE_SLIDESHOW},
			{"scripts",		AUDIT_RESOURCE_SCRIPT},
/*			{"",			AUDIT_RESOURCE_PROXY},*/
			{"maintenances",	AUDIT_RESOURCE_MAINTENANCE},
			{"regexps",		AUDIT_RESOURCE_REGEXP},
			{NULL}
			}
		},
		{NULL}
	};

	struct conv_t	condition_convs[] =
	{
		{"groups",	CONDITION_TYPE_HOST_GROUP},
		{"hosts",	CONDITION_TYPE_HOST},
		{"hosts",	CONDITION_TYPE_HOST_TEMPLATE},
		{"hosts",	CONDITION_TYPE_PROXY},
		{"triggers",	CONDITION_TYPE_TRIGGER},
		{"dchecks",	CONDITION_TYPE_DCHECK},
		{"drules",	CONDITION_TYPE_DRULE},
		{NULL},
	};

	int		i, j, s, t, ret;
	zbx_uint64_t	prefix;
	const ZBX_TABLE	*r_table;

	if (1 > nodeid || nodeid > 999)
	{
		printf("Node ID must be in range of 1-999.\n");
		return FAIL;
	}

	zabbix_set_log_level(LOG_LEVEL_WARNING);

	DBconnect(ZBX_DB_CONNECT_EXIT);

	if (SUCCEED != validate_ids() || SUCCEED != validate_trigger_expressions(nodeid))
	{
		DBclose();
		return FAIL;
	}

	DBbegin();

	printf("Dropping foreign keys ");
	fflush(stdout);

	for (i = 0; NULL != db_schema_fkeys_drop[i]; i++)
	{
		DBexecute("%s", db_schema_fkeys_drop[i]);
		printf(".");
		fflush(stdout);
	}

	printf(" done.\nConverting tables ");
	fflush(stdout);

	for (i = 0; NULL != tables[i].table; i++)
	{
		printf(".");
		fflush(stdout);

		for (j = 0; NULL != tables[i].fields[j].name; j++)
		{
			for (s = 0; NULL != special_convs[s].table_name; s++)
			{
				if (0 == strcmp(special_convs[s].table_name, tables[i].table) &&
						0 == strcmp(special_convs[s].field_name, tables[i].fields[j].name))
				{
					break;
				}
			}

			if (NULL != special_convs[s].table_name)
			{
				for (t = 0; NULL != special_convs[s].convs[t].rel; t++)
				{
					convert_special_field(nodeid, special_convs[s].table_name,
							special_convs[s].field_name, special_convs[s].type_field_name,
							special_convs[s].convs[t].rel, special_convs[s].convs[t].type);
				}
				continue;
			}

			if (ZBX_TYPE_ID != tables[i].fields[j].type)
				continue;

			/* primary key */
			if (0 == strcmp(tables[i].fields[j].name, tables[i].recid))
			{
				prefix = ZBX_DM_MAX_HISTORY_IDS * (zbx_uint64_t)nodeid;
				if (0 != (tables[i].flags & ZBX_SYNC))
					prefix += ZBX_DM_MAX_CONFIG_IDS * (zbx_uint64_t)nodeid;
			}
			/* relations */
			else if (NULL != tables[i].fields[j].fk_table)
			{
				assert(NULL != (r_table = DBget_table(tables[i].fields[j].fk_table)));

				prefix = ZBX_DM_MAX_HISTORY_IDS * (zbx_uint64_t)nodeid;
				if (0 != (r_table->flags & ZBX_SYNC))
					prefix += ZBX_DM_MAX_CONFIG_IDS * (zbx_uint64_t)nodeid;
			}
			/* special processing for table 'profiles' */
			else if (0 == strcmp("profiles", tables[i].table))
			{
				convert_profiles(nodeid, tables[i].fields[j].name);
				continue;
			}
			else
				assert(0);

			DBexecute("update %s set %s=%s+" ZBX_FS_UI64 " where %s>0",
					tables[i].table,
					tables[i].fields[j].name,
					tables[i].fields[j].name,
					prefix,
					tables[i].fields[j].name);
		}
	}

	/* special processing for trigger expressions */
	convert_trigger_expressions(nodeid);

	/* special processing for condition values */
	for (i = 0; NULL != condition_convs[i].rel; i++)
		convert_condition_values(nodeid, condition_convs[i].rel, condition_convs[i].type);

	DBexecute("insert into nodes (nodeid,name,ip,nodetype) values (%d,'Local node','127.0.0.1',1)", nodeid);

	DBexecute("delete from ids where nodeid=0");

	if (SUCCEED != (ret = DBtxn_status()))
		goto error;

	printf(" done.\nCreating foreign keys ");
	fflush(stdout);

	for (i = 0; NULL != db_schema_fkeys[i]; i++)
	{
		DBexecute("%s", db_schema_fkeys[i]);
		printf(".");
		fflush(stdout);
	}

	ret = DBtxn_status();
error:
	DBcommit();

	DBclose();

	if (SUCCEED != ret)
		printf("Conversion failed.\n");
	else
		printf(" done.\nConversion completed successfully.\n");

	return ret;
}
#else	/* HAVE_SQLITE3 */
int	change_nodeid(int nodeid)
{
	printf("Distributed monitoring with SQLite3 is not supported.\n");
	return FAIL;
}
#endif

