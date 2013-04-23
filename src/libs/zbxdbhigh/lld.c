/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
#include "events.h"
#include "zbxalgo.h"
#include "zbxserver.h"

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_uint64_t	itemid;
	char		*host;
	char		*key;
	char		*function;
	char		*parameter;
	unsigned char	flags;
}
zbx_lld_function_t;

typedef struct
{
	zbx_uint64_t		triggerid;
	zbx_vector_ptr_t	functions;
	char			*description;
	char			*expression;
	char			*full_expression;
	char			*comments;
	unsigned char		update_expression;
}
zbx_lld_trigger_t;

typedef struct
{
	zbx_uint64_t	graphid;
	char		*name;
	ZBX_GRAPH_ITEMS	*gitems;
	ZBX_GRAPH_ITEMS	*del_gitems;
	zbx_uint64_t	ymin_itemid;
	zbx_uint64_t	ymax_itemid;
	size_t		gitems_num;
	size_t		del_gitems_num;
}
zbx_lld_graph_t;

static void	DBlld_clean_graphs(zbx_vector_ptr_t *graphs)
{
	zbx_lld_graph_t	*graph;

	while (0 != graphs->values_num)
	{
		graph = (zbx_lld_graph_t *)graphs->values[--graphs->values_num];

		zbx_free(graph->del_gitems);
		zbx_free(graph->gitems);
		zbx_free(graph->name);
		zbx_free(graph);
	}
}

static void	DBlld_clean_trigger_functions(zbx_vector_ptr_t *functions)
{
	zbx_lld_function_t	*function;

	while (0 != functions->values_num)
	{
		function = (zbx_lld_function_t *)functions->values[--functions->values_num];

		zbx_free(function->parameter);
		zbx_free(function->function);
		zbx_free(function->key);
		zbx_free(function->host);
		zbx_free(function);
	}
}

static void	DBlld_clean_triggers(zbx_vector_ptr_t *triggers)
{
	zbx_lld_trigger_t	*trigger;

	while (0 != triggers->values_num)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[--triggers->values_num];

		DBlld_clean_trigger_functions(&trigger->functions);
		zbx_vector_ptr_destroy(&trigger->functions);

		zbx_free(trigger->comments);
		zbx_free(trigger->full_expression);
		zbx_free(trigger->expression);
		zbx_free(trigger->description);
		zbx_free(trigger);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_expand_trigger_expression                                  *
 *                                                                            *
 * Purpose: expand trigger expression                                         *
 *                                                                            *
 * Parameters: triggerid  - [IN] trigger identificator from database          *
 *             expression - [IN] trigger short expression                     *
 *             jp_row     - [IN] received discovery record                    *
 *                                                                            *
 * Return value: pointer to expanded expression                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static char	*DBlld_expand_trigger_expression(const char *expression, zbx_vector_ptr_t *functions)
{
	const char		*__function_name = "DBlld_expand_trigger_expression";

	const char		*bl, *br;
	char			*expr = NULL;
	size_t			expr_alloc = 256, expr_offset = 0;
	zbx_uint64_t		functionid;
	zbx_lld_function_t	*function;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, expression);

	expr = zbx_malloc(expr, expr_alloc);

	bl = br = expression;

	while (1)
	{
		if (NULL == (bl = strchr(bl, '{')))
		{
			zbx_strcpy_alloc(&expr, &expr_alloc, &expr_offset, br);
			break;
		}

		zbx_strncpy_alloc(&expr, &expr_alloc, &expr_offset, br, bl - br);

		if (NULL == (br = strchr(bl, '}')))
		{
			zbx_strcpy_alloc(&expr, &expr_alloc, &expr_offset, bl);
			break;
		}

		bl++;

		if (SUCCEED != is_uint64_n(bl, br - bl, &functionid) || FAIL == (i = zbx_vector_ptr_bsearch(
				functions, &functionid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			br = bl - 1;
			continue;
		}

		function = (zbx_lld_function_t *)functions->values[i];

		zbx_snprintf_alloc(&expr, &expr_alloc, &expr_offset, "{%s:%s.%s(%s)}",
				function->host, function->key, function->function, function->parameter);

		bl = ++br;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() expr:'%s'", __function_name, expr);

	return expr;
}

static int	DBlld_compare_trigger_items(zbx_uint64_t triggerid, struct zbx_json_parse *jp_row)
{
	const char	*__function_name = "DBlld_compare_trigger_items";
	DB_RESULT	result;
	DB_ROW		row;
	char		*old_key = NULL;
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select id.key_,i.key_"
			" from functions f,items i,item_discovery id"
			" where f.itemid=i.itemid"
				" and i.itemid=id.itemid"
				" and f.triggerid=" ZBX_FS_UI64,
			triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		old_key = zbx_strdup(old_key, row[0]);
		substitute_key_macros(&old_key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);

		if (0 == strcmp(old_key, row[1]))
		{
			res = SUCCEED;
			break;
		}
	}
	DBfree_result(result);

	zbx_free(old_key);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_get_item                                                   *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static int	DBlld_get_item(zbx_uint64_t hostid, const char *tmpl_key,
		struct zbx_json_parse *jp_row, zbx_uint64_t *itemid)
{
	const char	*__function_name = "DBlld_get_item";

	DB_RESULT	result;
	DB_ROW		row;
	char		*key = NULL, *key_esc;
	int		res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL != jp_row)
	{
		key = zbx_strdup(key, tmpl_key);
		substitute_key_macros(&key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);
		key_esc = DBdyn_escape_string_len(key, ITEM_KEY_LEN);
	}
	else
		key_esc = DBdyn_escape_string_len(tmpl_key, ITEM_KEY_LEN);

	result = DBselect(
			"select itemid"
			" from items"
			" where hostid=" ZBX_FS_UI64
				" and key_='%s'",
			hostid, key_esc);

	zbx_free(key_esc);
	zbx_free(key);

	if (NULL == (row = DBfetch(result)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot find item [%s] on the host",
				__function_name, key);
		res = FAIL;
	}
	else
		ZBX_STR2UINT64(*itemid, row[0]);

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_get_trigger_functions                                      *
 *                                                                            *
 * Purpose: selects trigger functions to an array by triggerid                *
 *                                                                            *
 * Return value: array of trigger functions                                   *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: use DBlld_clean_trigger_functions() before destroying            *
 *           a functions array                                                *
 *                                                                            *
 ******************************************************************************/
static void	DBlld_get_trigger_functions(zbx_uint64_t triggerid, struct zbx_json_parse *jp_row,
		zbx_vector_ptr_t *functions)
{
	const char		*__function_name = "DBlld_get_trigger_functions";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_function_t	*function;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select f.functionid,f.itemid,h.host,i.key_,f.function,f.parameter,i.flags"
			" from functions f,items i,hosts h"
			" where f.itemid=i.itemid"
				" and i.hostid=h.hostid"
				" and f.triggerid=" ZBX_FS_UI64,
			triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		function = zbx_malloc(NULL, sizeof(zbx_lld_function_t));
		ZBX_STR2UINT64(function->functionid, row[0]);
		ZBX_STR2UINT64(function->itemid, row[1]);
		function->host = zbx_strdup(NULL, row[2]);
		function->key = zbx_strdup(NULL, row[3]);
		function->function = zbx_strdup(NULL, row[4]);
		function->parameter = zbx_strdup(NULL, row[5]);
		function->flags = (unsigned char)atoi(row[6]);
		zbx_vector_ptr_append(functions, function);

		if (NULL != jp_row && 0 != (function->flags & ZBX_FLAG_DISCOVERY_CHILD))
			substitute_key_macros(&function->key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(functions, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

int	lld_check_record(struct zbx_json_parse *jp_row, const char *f_macro, const char *f_regexp, ZBX_REGEXP *regexps,
	int regexps_num)
{
	const char	*__function_name = "lld_check_record";

	char		*value = NULL;
	size_t		value_alloc = 0;
	int		res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() jp_row:'%.*s'", __function_name,
			jp_row->end - jp_row->start + 1, jp_row->start);

	if (NULL == f_macro || NULL == f_regexp)
		goto out;

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, f_macro, &value, &value_alloc))
		res = regexp_match_ex(regexps, regexps_num, value, f_regexp, ZBX_CASE_SENSITIVE);

	zbx_free(value);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_trigger_exists                                             *
 *                                                                            *
 * Purpose: check if trigger exists either in triggers list or in the         *
 *          database                                                          *
 *                                                                            *
 * Parameters: hostid - host identificator from database                      *
 *             triggerid - trigger identificator from database                *
 *             description - trigger description                              *
 *             full_expression - trigger expression                           *
 *             triggers - list of triggers                                    *
 *                                                                            *
 * Return value: SUCCEED if trigger exists otherwise FAIL                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static int	DBlld_trigger_exists(zbx_uint64_t hostid, zbx_uint64_t triggerid, const char *description,
		const char *full_expression, zbx_vector_ptr_t *triggers)
{
	char			*description_esc, *sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_vector_ptr_t	functions;
	DB_RESULT		result;
	DB_ROW			row;
	int			i, ret = FAIL;

	for (i = 0; i < triggers->values_num; i++)
	{
		zbx_lld_trigger_t	*trigger;

		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		if (0 == strcmp(description, trigger->description) && 0 == strcmp(full_expression, trigger->full_expression))
			return SUCCEED;
	}

	sql = zbx_malloc(sql, sql_alloc);
	description_esc = DBdyn_escape_string_len(description, TRIGGER_DESCRIPTION_LEN);
	zbx_vector_ptr_create(&functions);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct t.triggerid,t.expression"
			" from triggers t,functions f,items i"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and t.description='%s'",
			hostid, description_esc);

	if (0 != triggerid)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and t.triggerid<>" ZBX_FS_UI64, triggerid);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	h_triggerid;
		char		*h_full_expression;

		ZBX_STR2UINT64(h_triggerid, row[0]);

		DBlld_get_trigger_functions(h_triggerid, NULL, &functions);
		h_full_expression = DBlld_expand_trigger_expression(row[1], &functions);
		DBlld_clean_trigger_functions(&functions);

		if (0 == strcmp(full_expression, h_full_expression))
			ret = SUCCEED;

		zbx_free(h_full_expression);

		if (SUCCEED == ret)
			break;
	}
	DBfree_result(result);

	zbx_vector_ptr_destroy(&functions);
	zbx_free(description_esc);
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_make_trigger                                               *
 *                                                                            *
 * Purpose: create a trigger based on lld rule and add it to the list         *
 *                                                                            *
 * Parameters: hostid - trigger host identificator                            *
 *             parent_triggerid - trigger identificator from database         *
 *             triggers - [OUT] list of triggers                              *
 *             description_proto - trigger description                        *
 *             expression - trigger expression                                *
 *             jp_row - json record corresponding to the trigger              *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static int	DBlld_make_trigger(zbx_uint64_t hostid, zbx_uint64_t parent_triggerid, zbx_vector_ptr_t *triggers,
		const char *description_proto, const char *expression_proto, const char *comments_proto,
		struct zbx_json_parse *jp_row, char **error)
{
	const char		*__function_name = "DBlld_make_trigger";

	DB_RESULT		result;
	DB_ROW			row;
	char			*description_esc, *expression, *full_expression, err[64];
	zbx_vector_ptr_t	functions;
	zbx_lld_trigger_t	*trigger;
	zbx_lld_function_t	*function;
	zbx_uint64_t		h_triggerid;
	int			i, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&functions);

	trigger = zbx_calloc(NULL, 1, sizeof(zbx_lld_trigger_t));
	trigger->update_expression = 1;
	trigger->description = zbx_strdup(NULL, description_proto);
	substitute_discovery_macros(&trigger->description, jp_row, ZBX_MACRO_ANY, NULL, 0);

	expression = zbx_strdup(NULL, expression_proto);
	if (SUCCEED != substitute_discovery_macros(&expression, jp_row, ZBX_MACRO_NUMERIC, err, sizeof(err)))
	{
		*error = zbx_strdcatf(*error, "Cannot create trigger \"%s\": %s.\n",
				trigger->description, err);
		ret = FAIL;
		goto out;
	}

	zbx_vector_ptr_create(&trigger->functions);
	DBlld_get_trigger_functions(parent_triggerid, jp_row, &trigger->functions);
	trigger->full_expression = DBlld_expand_trigger_expression(expression, &trigger->functions);

	trigger->comments = zbx_strdup(NULL, comments_proto);
	substitute_discovery_macros(&trigger->comments, jp_row, ZBX_MACRO_ANY, NULL, 0);

	description_esc = DBdyn_escape_string_len(trigger->description, TRIGGER_DESCRIPTION_LEN);

	result = DBselect(
			"select distinct t.triggerid,t.expression"
			" from triggers t,trigger_discovery td"
			" where t.triggerid=td.triggerid"
				" and td.parent_triggerid=" ZBX_FS_UI64
				" and t.description='%s'",
			parent_triggerid, description_esc);

	zbx_free(description_esc);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(h_triggerid, row[0]);

		DBlld_get_trigger_functions(h_triggerid, NULL, &functions);
		full_expression = DBlld_expand_trigger_expression(row[1], &functions);
		DBlld_clean_trigger_functions(&functions);

		if (0 == strcmp(trigger->full_expression, full_expression))
		{
			trigger->update_expression = 0;
			trigger->triggerid = h_triggerid;
		}

		zbx_free(full_expression);

		if (0 != trigger->triggerid)
			break;
	}
	DBfree_result(result);

	if (0 == trigger->triggerid)
	{
		result = DBselect(
				"select distinct t.triggerid,t.expression,td.name,t.description"
				" from triggers t,trigger_discovery td"
				" where t.triggerid=td.triggerid"
					" and td.parent_triggerid=" ZBX_FS_UI64,
				parent_triggerid);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(h_triggerid, row[0]);

			DBlld_get_trigger_functions(h_triggerid, NULL, &functions);
			full_expression = DBlld_expand_trigger_expression(row[1], &functions);
			DBlld_clean_trigger_functions(&functions);

			if (0 == strcmp(trigger->full_expression, full_expression))
			{
				trigger->update_expression = 0;
				trigger->triggerid = h_triggerid;
			}

			zbx_free(full_expression);

			if (0 == trigger->triggerid)
			{
				char	*old_name = NULL;

				old_name = zbx_strdup(old_name, row[2]);
				substitute_discovery_macros(&old_name, jp_row, ZBX_MACRO_ANY, NULL, 0);

				if (0 == strcmp(old_name, row[3]))
				{
					if (SUCCEED == DBlld_compare_trigger_items(h_triggerid, jp_row))
						trigger->triggerid = h_triggerid;
				}

				zbx_free(old_name);
			}

			if (0 != trigger->triggerid)
				break;
		}
		DBfree_result(result);
	}

	if (SUCCEED == DBlld_trigger_exists(hostid, trigger->triggerid, trigger->description,
			trigger->full_expression, triggers))
	{
		*error = zbx_strdcatf(*error, "Cannot %s trigger \"%s\": trigger already exists\n",
				0 != trigger->triggerid ? "update" : "create", trigger->description);
		ret = FAIL;
		goto out;
	}

	if (1 == trigger->update_expression)
	{
		trigger->expression = zbx_strdup(NULL, expression);

		for (i = 0; i < trigger->functions.values_num; i++)
		{
			function = (zbx_lld_function_t *)trigger->functions.values[i];

			if (0 != (ZBX_FLAG_DISCOVERY_CHILD & function->flags))
			{
				if (FAIL == (ret = DBlld_get_item(hostid, function->key, NULL, &function->itemid)))
					break;
			}
		}
	}

	if (FAIL == ret)
		goto out;

	zbx_vector_ptr_append(triggers, trigger);
out:
	if (FAIL == ret)
	{
		DBlld_clean_trigger_functions(&trigger->functions);
		zbx_vector_ptr_destroy(&trigger->functions);

		zbx_free(trigger->full_expression);
		zbx_free(trigger->expression);
		zbx_free(trigger->description);
		zbx_free(trigger);
	}

	zbx_free(expression);
	zbx_vector_ptr_destroy(&functions);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_save_triggers                                              *
 *                                                                            *
 * Purpose: add or update triggers in database based on discovery rule        *
 *                                                                            *
 * Parameters: triggers - list of triggers                                    *
 *             status - trigger status                                        *
 *             type - trigger type                                            *
 *             priority - trigger priority                                    *
 *             comments_esc - trigger comments                                *
 *             url_esc - trigger url                                          *
 *             parent_triggerid - trigger identificator from database         *
 *             description_proto_esc - trigger description                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DBlld_save_triggers(zbx_vector_ptr_t *triggers, unsigned char status, unsigned char type,
		unsigned char priority, const char *url_esc, zbx_uint64_t parent_triggerid,
		const char *description_proto_esc)
{
	int			i, j, new_triggers = 0, new_functions = 0;
	zbx_lld_trigger_t	*trigger;
	zbx_lld_function_t	*function;
	zbx_uint64_t		triggerid = 0, triggerdiscoveryid = 0, functionid = 0;
	char			*sql1 = NULL, *sql2 = NULL, *sql3 = NULL, *sql4 = NULL;
	size_t			sql1_alloc = 8 * ZBX_KIBIBYTE, sql1_offset = 0,
				sql2_alloc = 2 * ZBX_KIBIBYTE, sql2_offset = 0,
				sql3_alloc = 2 * ZBX_KIBIBYTE, sql3_offset = 0,
				sql4_alloc = 8 * ZBX_KIBIBYTE, sql4_offset = 0;
	const char		*ins_triggers_sql =
				"insert into triggers"
				" (triggerid,description,expression,priority,status,"
					"comments,url,type,value,state,flags)"
				" values ";
	const char		*ins_trigger_discovery_sql =
				"insert into trigger_discovery"
				" (triggerdiscoveryid,triggerid,parent_triggerid,name)"
				" values ";
	const char		*ins_functions_sql =
				"insert into functions"
				" (functionid,itemid,triggerid,function,parameter)"
				" values ";

	if (0 == triggers->values_num)
		return;

	for (i = 0; i < triggers->values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		if (0 == trigger->triggerid)
			new_triggers++;

		if (1 == trigger->update_expression)
			new_functions += trigger->functions.values_num;
	}

	DBbegin();

	if (0 != new_triggers)
	{
		triggerid = DBget_maxid_num("triggers", new_triggers);
		triggerdiscoveryid = DBget_maxid_num("trigger_discovery", new_triggers);

		sql1 = zbx_malloc(sql1, sql1_alloc);
		sql2 = zbx_malloc(sql2, sql2_alloc);
		DBbegin_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
		DBbegin_multiple_update(&sql2, &sql2_alloc, &sql2_offset);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ins_triggers_sql);
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ins_trigger_discovery_sql);
#endif
	}

	if (0 != new_functions)
	{
		functionid = DBget_maxid_num("functions", new_functions);

		sql3 = zbx_malloc(sql3, sql3_alloc);
		DBbegin_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ins_functions_sql);
#endif
	}

	if (new_triggers < triggers->values_num)
	{
		sql4 = zbx_malloc(sql4, sql4_alloc);
		DBbegin_multiple_update(&sql4, &sql4_alloc, &sql4_offset);
	}

	for (i = 0; i < triggers->values_num; i++)
	{
		char	*description_esc, *expression_esc, *comments_esc;

		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		if (1 == trigger->update_expression)
		{
			char	*old_expression, search[MAX_ID_LEN + 2], replace[MAX_ID_LEN + 2],
				*function_esc, *parameter_esc;

			for (j = 0; j < trigger->functions.values_num; j++)
			{
				function = (zbx_lld_function_t *)trigger->functions.values[j];

				zbx_snprintf(search, sizeof(search), "{" ZBX_FS_UI64 "}", function->functionid);
				zbx_snprintf(replace, sizeof(replace), "{" ZBX_FS_UI64 "}", functionid);

				old_expression = trigger->expression;
				trigger->expression = string_replace(old_expression, search, replace);
				zbx_free(old_expression);

				function_esc = DBdyn_escape_string_len(function->function, FUNCTION_FUNCTION_LEN);
				parameter_esc = DBdyn_escape_string_len(function->parameter, FUNCTION_PARAMETER_LEN);

#ifndef HAVE_MULTIROW_INSERT
				zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ins_functions_sql);
#endif
				zbx_snprintf_alloc(&sql3, &sql3_alloc, &sql3_offset,
						"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s','%s')" ZBX_ROW_DL,
						functionid, function->itemid,
						(0 == trigger->triggerid ? triggerid : trigger->triggerid),
						function_esc, parameter_esc);

				zbx_free(parameter_esc);
				zbx_free(function_esc);

				functionid++;
			}
		}

		description_esc = DBdyn_escape_string_len(trigger->description, TRIGGER_DESCRIPTION_LEN);
		comments_esc = DBdyn_escape_string_len(trigger->comments, TRIGGER_COMMENTS_LEN);

		if (0 == trigger->triggerid)
		{
			trigger->triggerid = triggerid++;

#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ins_triggers_sql);
#endif
			expression_esc = DBdyn_escape_string_len(trigger->expression, TRIGGER_EXPRESSION_LEN);
			zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
					"(" ZBX_FS_UI64 ",'%s','%s',%d,%d,'%s','%s',%d,%d,%d,%d)" ZBX_ROW_DL,
					trigger->triggerid, description_esc, expression_esc, (int)priority, (int)status,
					comments_esc, url_esc, (int)type, TRIGGER_VALUE_OK, TRIGGER_STATE_NORMAL,
					ZBX_FLAG_DISCOVERY_CREATED);
			zbx_free(expression_esc);

#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ins_trigger_discovery_sql);
#endif
			zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset,
					" (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s')" ZBX_ROW_DL,
					triggerdiscoveryid, trigger->triggerid, parent_triggerid,
					description_proto_esc);

			triggerdiscoveryid++;
		}
		else
		{
			zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
					"update triggers"
					" set description='%s',",
					description_esc);
			if (1 == trigger->update_expression)
			{
				expression_esc = DBdyn_escape_string_len(trigger->expression, TRIGGER_EXPRESSION_LEN);
				zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
						"expression='%s',", expression_esc);
				zbx_free(expression_esc);
			}
			zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
						"priority=%d,"
						"comments='%s',"
						"url='%s',"
						"type=%d,"
						"flags=%d"
					" where triggerid=" ZBX_FS_UI64 ";\n",
					(int)priority, comments_esc, url_esc, (int)type,
					ZBX_FLAG_DISCOVERY_CREATED, trigger->triggerid);
			zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
					"update trigger_discovery"
					" set name='%s'"
					" where triggerid=" ZBX_FS_UI64
						" and parent_triggerid=" ZBX_FS_UI64 ";\n",
					description_proto_esc, trigger->triggerid, parent_triggerid);

			if (1 == trigger->update_expression)
			{
				zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
						"delete from functions"
						" where triggerid=" ZBX_FS_UI64 ";\n",
						trigger->triggerid);
			}
		}

		zbx_free(comments_esc);
		zbx_free(description_esc);
	}

	if (0 != new_triggers)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql1_offset--;
		sql2_offset--;
		zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ";\n");
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
#endif
		DBend_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
		DBend_multiple_update(&sql2, &sql2_alloc, &sql2_offset);
		DBexecute("%s", sql1);
		DBexecute("%s", sql2);
		zbx_free(sql1);
		zbx_free(sql2);
	}

	if (new_triggers < triggers->values_num)
	{
		DBend_multiple_update(&sql4, &sql4_alloc, &sql4_offset);
		DBexecute("%s", sql4);
		zbx_free(sql4);
	}

	if (0 != new_functions)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql3_offset--;
		zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ";\n");
#endif
		DBend_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
		DBexecute("%s", sql3);
		zbx_free(sql3);
	}

	DBcommit();
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_update_triggers                                            *
 *                                                                            *
 * Purpose: add or update triggers for discovered items                       *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DBlld_update_triggers(zbx_uint64_t hostid, zbx_uint64_t discovery_itemid,
		struct zbx_json_parse *jp_data, char **error, const char *f_macro,
		const char *f_regexp, ZBX_REGEXP *regexps, int regexps_num)
{
	const char		*__function_name = "DBlld_update_triggers";

	struct zbx_json_parse	jp_row;
	const char		*p;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_ptr_t	triggers;
	zbx_vector_uint64_t	triggerids;
	zbx_uint64_t		triggerid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&triggers);
	zbx_vector_uint64_create(&triggerids);

	/* list of generated trigger IDs (for later deletion of obsoleted triggers) */
	result = DBselect(
			"select distinct td.triggerid"
			" from trigger_discovery td,triggers t,functions f,items i,item_discovery id"
			" where td.parent_triggerid=t.triggerid"
				" and t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and i.itemid=id.itemid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			discovery_itemid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);
		zbx_vector_uint64_append(&triggerids, triggerid);
	}
	DBfree_result(result);

	result = DBselect(
			"select distinct t.triggerid,t.description,t.expression,"
				"t.status,t.type,t.priority,t.comments,t.url"
			" from triggers t,functions f,items i,item_discovery id"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and i.itemid=id.itemid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			discovery_itemid);

	/* run through trigger prototypes */
	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	parent_triggerid;
		const char	*description_proto, *expression_proto, *comments_proto;
		char		*description_proto_esc, *url_esc;
		unsigned char	status, type, priority;
		int		i;

		ZBX_STR2UINT64(parent_triggerid, row[0]);
		description_proto = row[1];
		description_proto_esc = DBdyn_escape_string(description_proto);
		expression_proto = row[2];
		status = (unsigned char)atoi(row[3]);
		type = (unsigned char)atoi(row[4]);
		priority = (unsigned char)atoi(row[5]);
		comments_proto = row[6];
		url_esc = DBdyn_escape_string(row[7]);

		p = NULL;
		/* {"net.if.discovery":[{"{#IFNAME}":"eth0"},{"{#IFNAME}":"lo"},...]} */
		/*                      ^                                             */
		while (NULL != (p = zbx_json_next(jp_data, p)))
		{
			/* {"net.if.discovery":[{"{#IFNAME}":"eth0"},{"{#IFNAME}":"lo"},...]} */
			/*                      ^------------------^                          */
			if (FAIL == zbx_json_brackets_open(p, &jp_row))
				continue;

			if (SUCCEED != lld_check_record(&jp_row, f_macro, f_regexp, regexps, regexps_num))
				continue;

			DBlld_make_trigger(hostid, parent_triggerid, &triggers, description_proto, expression_proto,
					comments_proto, &jp_row, error);
		}

		zbx_vector_ptr_sort(&triggers, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		DBlld_save_triggers(&triggers, status, type, priority, url_esc, parent_triggerid,
				description_proto_esc);

		zbx_free(url_esc);
		zbx_free(description_proto_esc);

		for (i = 0; i < triggerids.values_num;)
		{
			if (FAIL != zbx_vector_ptr_bsearch(&triggers, &triggerids.values[i],
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC))
			{
				zbx_vector_uint64_remove_noorder(&triggerids, i);
			}
			else
				i++;
		}

		DBlld_clean_triggers(&triggers);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	DBdelete_triggers(&triggerids);

	zbx_vector_uint64_destroy(&triggerids);
	zbx_vector_ptr_destroy(&triggers);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_graph_exists                                               *
 *                                                                            *
 * Purpose: check if graph exists                                             *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static int	DBlld_graph_exists(zbx_uint64_t hostid, zbx_uint64_t graphid, const char *name, zbx_vector_ptr_t *graphs)
{
	char		*name_esc, *sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	DB_RESULT	result;
	int		i, res = FAIL;

	for (i = 0; i < graphs->values_num; i++)
	{
		if (0 == strcmp(name, ((zbx_lld_graph_t *)graphs->values[i])->name))
			return SUCCEED;
	}

	sql = zbx_malloc(sql, sql_alloc);
	name_esc = DBdyn_escape_string_len(name, GRAPH_NAME_LEN);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct g.graphid"
			" from graphs g,graphs_items gi,items i"
			" where g.graphid=gi.graphid"
				" and gi.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and g.name='%s'",
			hostid, name_esc);

	if (0 != graphid)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and g.graphid<>" ZBX_FS_UI64, graphid);

	result = DBselect("%s", sql);

	if (NULL != DBfetch(result))
		res = SUCCEED;
	DBfree_result(result);

	zbx_free(name_esc);
	zbx_free(sql);

	return res;
}

static int	DBlld_make_graph(zbx_uint64_t hostid, zbx_uint64_t parent_graphid, zbx_vector_ptr_t *graphs,
		const char *name_proto, ZBX_GRAPH_ITEMS *gitems_proto, int gitems_proto_num,
		unsigned char ymin_type, zbx_uint64_t ymin_itemid, unsigned char ymin_flags, const char *ymin_key_proto,
		unsigned char ymax_type, zbx_uint64_t ymax_itemid, unsigned char ymax_flags, const char *ymax_key_proto,
		struct zbx_json_parse *jp_row, char **error)
{
	const char	*__function_name = "DBlld_make_graph";

	DB_RESULT	result;
	DB_ROW		row;
	char		*name_esc;
	int		res = SUCCEED, i;
	zbx_lld_graph_t	*graph;
	ZBX_GRAPH_ITEMS	*gitem;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	graph = zbx_calloc(NULL, 1, sizeof(zbx_lld_graph_t));
	graph->name = zbx_strdup(NULL, name_proto);
	substitute_discovery_macros(&graph->name, jp_row, ZBX_MACRO_SIMPLE, NULL, 0);

	name_esc = DBdyn_escape_string_len(graph->name, GRAPH_NAME_LEN);

	result = DBselect(
			"select distinct g.graphid"
			" from graphs g,graph_discovery gd"
			" where g.graphid=gd.graphid"
				" and gd.parent_graphid=" ZBX_FS_UI64
				" and g.name='%s'",
			parent_graphid, name_esc);

	if (NULL != (row = DBfetch(result)))
		ZBX_STR2UINT64(graph->graphid, row[0]);
	DBfree_result(result);

	if (0 == graph->graphid)
	{
		result = DBselect(
				"select distinct g.graphid,gd.name,g.name"
				" from graphs g,graph_discovery gd"
				" where g.graphid=gd.graphid"
					" and gd.parent_graphid=" ZBX_FS_UI64,
				parent_graphid);

		while (NULL != (row = DBfetch(result)))
		{
			char	*old_name = NULL;

			old_name = zbx_strdup(old_name, row[1]);
			substitute_discovery_macros(&old_name, jp_row, ZBX_MACRO_SIMPLE, NULL, 0);

			if (0 == strcmp(old_name, row[2]))
				ZBX_STR2UINT64(graph->graphid, row[0]);

			zbx_free(old_name);

			if (0 != graph->graphid)
				break;
		}
		DBfree_result(result);
	}

	if (SUCCEED == DBlld_graph_exists(hostid, graph->graphid, graph->name, graphs))
	{
		*error = zbx_strdcatf(*error, "Cannot %s graph \"%s\": graph already exists\n",
				0 != graph->graphid ? "update" : "create", graph->name);
		res = FAIL;
		goto out;
	}

	if (0 != gitems_proto_num)
	{
		size_t	gitems_alloc;

		graph->gitems_num = gitems_proto_num;
		gitems_alloc = graph->gitems_num * sizeof(ZBX_GRAPH_ITEMS);
		graph->gitems = zbx_malloc(graph->gitems, gitems_alloc);
		memcpy(graph->gitems, gitems_proto, gitems_alloc);

		for (i = 0; i < graph->gitems_num; i++)
		{
			gitem = &graph->gitems[i];

			if (0 != (ZBX_FLAG_DISCOVERY_CHILD & gitem->flags))
			{
				if (FAIL == (res = DBlld_get_item(hostid, gitem->key, jp_row, &gitem->itemid)))
					break;
			}
		}

		/* sort by itemid */
		qsort(graph->gitems, graph->gitems_num, sizeof(ZBX_GRAPH_ITEMS), ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	if (FAIL == res)
		goto out;

	if (GRAPH_YAXIS_TYPE_ITEM_VALUE == ymin_type)
	{
		graph->ymin_itemid = ymin_itemid;

		if (0 != (ZBX_FLAG_DISCOVERY_CHILD & ymin_flags) &&
				FAIL == (res = DBlld_get_item(hostid, ymin_key_proto, jp_row, &graph->ymin_itemid)))
		{
			goto out;
		}
	}

	if (GRAPH_YAXIS_TYPE_ITEM_VALUE == ymax_type)
	{
		graph->ymax_itemid = ymax_itemid;

		if (0 != (ZBX_FLAG_DISCOVERY_CHILD & ymax_flags) &&
				FAIL == (res = DBlld_get_item(hostid, ymax_key_proto, jp_row, &graph->ymax_itemid)))
		{
			goto out;
		}
	}

	if (0 != graph->graphid)
	{
		char	*sql = NULL;
		size_t	sql_alloc = ZBX_KIBIBYTE, sql_offset = 0, sz, del_gitems_alloc = 0;
		int	idx;

		sql = zbx_malloc(sql, sql_alloc);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select gi.gitemid,i.itemid,i.key_,gi.drawtype,gi.sortorder,gi.color,"
					"gi.yaxisside,gi.calc_fnc,gi.type,i.flags"
				" from graphs_items gi,items i"
				" where gi.itemid=i.itemid"
					" and gi.graphid=" ZBX_FS_UI64
				" order by i.itemid",
				graph->graphid);

		DBget_graphitems(sql, &graph->del_gitems, &del_gitems_alloc, &graph->del_gitems_num);

		/* Run through graph items that must exist removing them from */
		/* del_items. What's left in del_items will be removed later. */
		for (i = 0; i < graph->gitems_num; i++)
		{
			if (NULL != (gitem = bsearch(&graph->gitems[i].itemid, graph->del_gitems, graph->del_gitems_num,
					sizeof(ZBX_GRAPH_ITEMS), ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				graph->gitems[i].gitemid = gitem->gitemid;

				graph->del_gitems_num--;

				idx = (int)(gitem - graph->del_gitems);

				if (0 != (sz = (graph->del_gitems_num - idx) * sizeof(ZBX_GRAPH_ITEMS)))
					memmove(&graph->del_gitems[idx], &graph->del_gitems[idx + 1], sz);
			}
		}
		zbx_free(sql);
	}

	zbx_vector_ptr_append(graphs, graph);
out:
	if (FAIL == res)
	{
		zbx_free(graph->gitems);
		zbx_free(graph->name);
		zbx_free(graph);
	}

	zbx_free(name_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

static void	DBlld_save_graphs(zbx_vector_ptr_t *graphs, int width, int height, double yaxismin, double yaxismax,
		unsigned char show_work_period, unsigned char show_triggers, unsigned char graphtype,
		unsigned char show_legend, unsigned char show_3d, double percent_left, double percent_right,
		unsigned char ymin_type, unsigned char ymax_type, zbx_uint64_t parent_graphid,
		const char *name_proto_esc)
{
	int		i, j, new_graphs = 0, new_graphs_items = 0;
	zbx_lld_graph_t	*graph;
	zbx_uint64_t	graphid = 0, graphdiscoveryid = 0, gitemid = 0;
	char		*sql1 = NULL, *sql2 = NULL, *sql3 = NULL, *sql4 = NULL,
			*name_esc;
	size_t		sql1_alloc = 8 * ZBX_KIBIBYTE, sql1_offset = 0,
			sql2_alloc = 2 * ZBX_KIBIBYTE, sql2_offset = 0,
			sql3_alloc = 2 * ZBX_KIBIBYTE, sql3_offset = 0,
			sql4_alloc = 8 * ZBX_KIBIBYTE, sql4_offset = 0;
	const char	*ins_graphs_sql =
			"insert into graphs"
			" (graphid,name,width,height,yaxismin,yaxismax,show_work_period,"
				"show_triggers,graphtype,show_legend,show_3d,percent_left,"
				"percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags)"
			" values ";
	const char	*ins_graph_discovery_sql =
			"insert into graph_discovery"
			" (graphdiscoveryid,graphid,parent_graphid,name)"
			" values ";
	const char	*ins_graphs_items_sql =
			"insert into graphs_items"
			" (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type)"
			" values ";

	if (0 == graphs->values_num)
		return;

	for (i = 0; i < graphs->values_num; i++)
	{
		graph = (zbx_lld_graph_t *)graphs->values[i];

		if (0 == graph->graphid)
			new_graphs++;

		for (j = 0; j < graph->gitems_num; j++)
		{
			if (0 == graph->gitems[j].gitemid)
				new_graphs_items++;
		}
	}

	DBbegin();

	if (0 != new_graphs)
	{
		graphid = DBget_maxid_num("graphs", new_graphs);
		graphdiscoveryid = DBget_maxid_num("graph_discovery", new_graphs);

		sql1 = zbx_malloc(sql1, sql1_alloc);
		sql2 = zbx_malloc(sql2, sql2_alloc);
		DBbegin_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
		DBbegin_multiple_update(&sql2, &sql2_alloc, &sql2_offset);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ins_graphs_sql);
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ins_graph_discovery_sql);
#endif
	}

	if (0 != new_graphs_items)
	{
		gitemid = DBget_maxid_num("graphs_items", new_graphs_items);

		sql3 = zbx_malloc(sql3, sql3_alloc);
		DBbegin_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ins_graphs_items_sql);
#endif
	}

	if (new_graphs < graphs->values_num)
	{
		sql4 = zbx_malloc(sql4, sql4_alloc);
		DBbegin_multiple_update(&sql4, &sql4_alloc, &sql4_offset);
	}

	for (i = 0; i < graphs->values_num; i++)
	{
		graph = (zbx_lld_graph_t *)graphs->values[i];

		name_esc = DBdyn_escape_string_len(graph->name, GRAPH_NAME_LEN);

		if (0 == graph->graphid)
		{
			graph->graphid = graphid++;
#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ins_graphs_sql);
#endif
			zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
					"(" ZBX_FS_UI64 ",'%s',%d,%d," ZBX_FS_DBL ","
						ZBX_FS_DBL ",%d,%d,%d,%d,%d," ZBX_FS_DBL ","
						ZBX_FS_DBL ",%d,%d,%s,%s,%d)" ZBX_ROW_DL,
					graph->graphid, name_esc, width, height, yaxismin, yaxismax,
					(int)show_work_period, (int)show_triggers,
					(int)graphtype, (int)show_legend, (int)show_3d,
					percent_left, percent_right, (int)ymin_type, (int)ymax_type,
					DBsql_id_ins(graph->ymin_itemid), DBsql_id_ins(graph->ymax_itemid),
					ZBX_FLAG_DISCOVERY_CREATED);

#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ins_graph_discovery_sql);
#endif
			zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset,
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s')" ZBX_ROW_DL,
					graphdiscoveryid, graph->graphid, parent_graphid, name_proto_esc);

			graphdiscoveryid++;
		}
		else
		{
			zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
					"update graphs"
					" set name='%s',"
						"width=%d,"
						"height=%d,"
						"yaxismin=" ZBX_FS_DBL ","
						"yaxismax=" ZBX_FS_DBL ","
						"show_work_period=%d,"
						"show_triggers=%d,"
						"graphtype=%d,"
						"show_legend=%d,"
						"show_3d=%d,"
						"percent_left=" ZBX_FS_DBL ","
						"percent_right=" ZBX_FS_DBL ","
						"ymin_type=%d,"
						"ymax_type=%d,"
						"ymin_itemid=%s,"
						"ymax_itemid=%s,"
						"flags=%d"
					" where graphid=" ZBX_FS_UI64 ";\n",
					name_esc, width, height, yaxismin, yaxismax,
					(int)show_work_period, (int)show_triggers,
					(int)graphtype, (int)show_legend, (int)show_3d,
					percent_left, percent_right, (int)ymin_type, (int)ymax_type,
					DBsql_id_ins(graph->ymin_itemid), DBsql_id_ins(graph->ymax_itemid),
					ZBX_FLAG_DISCOVERY_CREATED, graph->graphid);

			zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
					"update graph_discovery"
					" set name='%s'"
					" where graphid=" ZBX_FS_UI64
						" and parent_graphid=" ZBX_FS_UI64 ";\n",
					name_proto_esc, graph->graphid, parent_graphid);
		}

		for (j = 0; j < graph->gitems_num; j++)
		{
			ZBX_GRAPH_ITEMS	*gitem;
			char		*color_esc;

			gitem = &graph->gitems[j];
			color_esc = DBdyn_escape_string_len(gitem->color, GRAPH_ITEM_COLOR_LEN);

			if (0 != gitem->gitemid)
			{
				zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
						"update graphs_items"
						" set drawtype=%d,"
							"sortorder=%d,"
							"color='%s',"
							"yaxisside=%d,"
							"calc_fnc=%d,"
							"type=%d"
						" where gitemid=" ZBX_FS_UI64 ";\n",
						gitem->drawtype,
						gitem->sortorder,
						color_esc,
						gitem->yaxisside,
						gitem->calc_fnc,
						gitem->type,
						gitem->gitemid);
			}
			else
			{
				gitem->gitemid = gitemid++;
#ifndef HAVE_MULTIROW_INSERT
				zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ins_graphs_items_sql);
#endif
				zbx_snprintf_alloc(&sql3, &sql3_alloc, &sql3_offset,
						"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64
							",%d,%d,'%s',%d,%d,%d)" ZBX_ROW_DL,
						gitem->gitemid, graph->graphid, gitem->itemid,
						gitem->drawtype, gitem->sortorder, color_esc,
						gitem->yaxisside, gitem->calc_fnc, gitem->type);
			}

			zbx_free(color_esc);
		}

		for (j = 0; j < graph->del_gitems_num; j++)
		{
			zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
					"delete from graphs_items"
					" where gitemid=" ZBX_FS_UI64 ";\n",
					graph->del_gitems[j].gitemid);
		}

		zbx_free(name_esc);
	}

	if (0 != new_graphs)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql1_offset--;
		sql2_offset--;
		zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ";\n");
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
#endif
		DBend_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
		DBend_multiple_update(&sql2, &sql2_alloc, &sql2_offset);
		DBexecute("%s", sql1);
		DBexecute("%s", sql2);
		zbx_free(sql1);
		zbx_free(sql2);
	}

	if (0 != new_graphs_items)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql3_offset--;
		zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, ";\n");
#endif
		DBend_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
		DBexecute("%s", sql3);
		zbx_free(sql3);
	}

	if (new_graphs < graphs->values_num)
	{
		DBend_multiple_update(&sql4, &sql4_alloc, &sql4_offset);
		DBexecute("%s", sql4);
		zbx_free(sql4);
	}

	DBcommit();
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_update_graphs                                              *
 *                                                                            *
 * Purpose: add or update graphs for discovery item                           *
 *                                                                            *
 * Parameters: hostid  - [IN] host identificator from database                *
 *             agent   - [IN] discovery item identificator from database      *
 *             jp_data - [IN] received data                                   *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	DBlld_update_graphs(zbx_uint64_t hostid, zbx_uint64_t discovery_itemid,
		struct zbx_json_parse *jp_data, char **error, const char *f_macro,
		const char *f_regexp, ZBX_REGEXP *regexps, int regexps_num)
{
	const char		*__function_name = "DBlld_update_graphs";

	struct zbx_json_parse	jp_row;
	const char		*p;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_ptr_t	graphs;
	zbx_vector_uint64_t	graphids;
	char			*sql = NULL;
	size_t			sql_alloc = 512, sql_offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&graphs);
	zbx_vector_uint64_create(&graphids);
	sql = zbx_malloc(sql, sql_alloc);

	result = DBselect(
			"select distinct gd.graphid"
			" from item_discovery id,items i,graphs_items gi,graphs g"
			" left join items i1 on i1.itemid=g.ymin_itemid"
			" left join items i2 on i2.itemid=g.ymax_itemid"
			" join graph_discovery gd on gd.parent_graphid=g.graphid"
			" where id.itemid=i.itemid"
				" and i.itemid=gi.itemid"
				" and gi.graphid=g.graphid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			discovery_itemid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	graphid;

		ZBX_STR2UINT64(graphid, row[0]);

		zbx_vector_uint64_append(&graphids, graphid);
	}
	DBfree_result(result);

	result = DBselect(
			"select distinct g.graphid,g.name,g.width,g.height,g.yaxismin,g.yaxismax,g.show_work_period,"
				"g.show_triggers,g.graphtype,g.show_legend,g.show_3d,g.percent_left,g.percent_right,"
				"g.ymin_type,i1.itemid,i1.flags,i1.key_,g.ymax_type,i2.itemid,i2.flags,i2.key_"
			" from item_discovery id,items i,graphs_items gi,graphs g"
			" left join items i1 on i1.itemid=g.ymin_itemid"
			" left join items i2 on i2.itemid=g.ymax_itemid"
			" where id.itemid=i.itemid"
				" and i.itemid=gi.itemid"
				" and gi.graphid=g.graphid"
				" and id.parent_itemid=" ZBX_FS_UI64,
			discovery_itemid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_GRAPH_ITEMS	*gitems_proto = NULL;
		size_t		gitems_proto_alloc = 0, gitems_proto_num = 0;
		zbx_uint64_t	parent_graphid, ymin_itemid = 0, ymax_itemid = 0;
		const char	*name_proto, *ymin_key_proto = NULL, *ymax_key_proto = NULL;
		char		*name_proto_esc;
		int		width, height;
		double		yaxismin, yaxismax, percent_left, percent_right;
		unsigned char	show_work_period, show_triggers, graphtype, show_legend, show_3d,
				ymin_type = GRAPH_YAXIS_TYPE_CALCULATED, ymax_type = GRAPH_YAXIS_TYPE_CALCULATED,
				ymin_flags = 0, ymax_flags = 0;
		int		i;

		ZBX_STR2UINT64(parent_graphid, row[0]);
		name_proto = row[1];
		name_proto_esc = DBdyn_escape_string(name_proto);
		width = atoi(row[2]);
		height = atoi(row[3]);
		yaxismin = atof(row[4]);
		yaxismax = atof(row[5]);
		show_work_period = (unsigned char)atoi(row[6]);
		show_triggers = (unsigned char)atoi(row[7]);
		graphtype = (unsigned char)atoi(row[8]);
		show_legend = (unsigned char)atoi(row[9]);
		show_3d = (unsigned char)atoi(row[10]);
		percent_left = atof(row[11]);
		percent_right = atof(row[12]);
		ymin_type = (unsigned char)atoi(row[13]);
		if (GRAPH_YAXIS_TYPE_ITEM_VALUE == ymin_type && SUCCEED != DBis_null(row[14]))
		{
			ymin_type = GRAPH_YAXIS_TYPE_ITEM_VALUE;
			ZBX_STR2UINT64(ymin_itemid, row[14]);
			ymin_flags = (unsigned char)atoi(row[15]);
			ymin_key_proto = row[16];
		}
		ymax_type = (unsigned char)atoi(row[17]);
		if (GRAPH_YAXIS_TYPE_ITEM_VALUE == ymax_type && SUCCEED != DBis_null(row[18]))
		{
			ymax_type = GRAPH_YAXIS_TYPE_ITEM_VALUE;
			ZBX_STR2UINT64(ymax_itemid, row[18]);
			ymax_flags = (unsigned char)atoi(row[19]);
			ymax_key_proto = row[20];
		}

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select 0,i.itemid,i.key_,gi.drawtype,gi.sortorder,gi.color,"
					"gi.yaxisside,gi.calc_fnc,gi.type,i.flags"
				" from graphs_items gi,items i"
				" where gi.itemid=i.itemid"
					" and gi.graphid=" ZBX_FS_UI64,
				parent_graphid);

		DBget_graphitems(sql, &gitems_proto, &gitems_proto_alloc, &gitems_proto_num);

		p = NULL;
		/* {"net.if.discovery":[{"{#IFNAME}":"eth0"},{"{#IFNAME}":"lo"},...]} */
		/*                      ^                                             */
		while (NULL != (p = zbx_json_next(jp_data, p)))
		{
			/* {"net.if.discovery":[{"{#IFNAME}":"eth0"},{"{#IFNAME}":"lo"},...]} */
			/*                      ^------------------^                          */
			if (FAIL == zbx_json_brackets_open(p, &jp_row))
				continue;

			if (SUCCEED != lld_check_record(&jp_row, f_macro, f_regexp, regexps, regexps_num))
				continue;

			DBlld_make_graph(hostid, parent_graphid, &graphs, name_proto, gitems_proto, gitems_proto_num,
					ymin_type, ymin_itemid, ymin_flags, ymin_key_proto,
					ymax_type, ymax_itemid, ymax_flags, ymax_key_proto,
					&jp_row, error);
		}

		zbx_vector_ptr_sort(&graphs, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		DBlld_save_graphs(&graphs, width, height, yaxismin, yaxismax, show_work_period, show_triggers,
				graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type,
				parent_graphid, name_proto_esc);

		zbx_free(gitems_proto);
		zbx_free(name_proto_esc);

		for (i = 0; i < graphids.values_num;)
		{
			if (FAIL != zbx_vector_ptr_bsearch(&graphs, &graphids.values[i],
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC))
			{
				zbx_vector_uint64_remove_noorder(&graphids, i);
			}
			else
				i++;
		}

		DBlld_clean_graphs(&graphs);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&graphids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	DBdelete_graphs(&graphids);

	zbx_free(sql);
	zbx_vector_uint64_destroy(&graphids);
	zbx_vector_ptr_destroy(&graphs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_process_discovery_rule                                     *
 *                                                                            *
 * Purpose: add or update items, triggers and graphs for discovery item       *
 *                                                                            *
 * Parameters: discovery_itemid - [IN] discovery item identificator           *
 *                                     from database                          *
 *             value            - [IN] received value from agent              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	DBlld_process_discovery_rule(zbx_uint64_t discovery_itemid, char *value, zbx_timespec_t *ts)
{
	const char		*__function_name = "DBlld_process_discovery_rule";
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		hostid = 0;
	struct zbx_json_parse	jp, jp_data;
	char			*discovery_key = NULL, *filter = NULL, *error = NULL, *db_error = NULL, *error_esc;
	unsigned char		state = 0;
	unsigned short		lifetime;
	char			*f_macro = NULL, *f_regexp = NULL;
	ZBX_REGEXP		*regexps = NULL;
	int			regexps_alloc = 0, regexps_num = 0;
	char			*sql = NULL;
	size_t			sql_alloc = 128, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64, __function_name, discovery_itemid);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update items set lastclock=%d,lastns=%d", ts->sec, ts->ns);

	result = DBselect(
			"select hostid,key_,state,filter,error,lifetime"
			" from items"
			" where itemid=" ZBX_FS_UI64,
			discovery_itemid);

	if (NULL != (row = DBfetch(result)))
	{
		char	*lifetime_str;

		ZBX_STR2UINT64(hostid, row[0]);
		discovery_key = zbx_strdup(discovery_key, row[1]);
		state = (unsigned char)atoi(row[2]);
		filter = zbx_strdup(filter, row[3]);
		db_error = zbx_strdup(db_error, row[4]);

		lifetime_str = zbx_strdup(NULL, row[5]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &hostid, NULL, NULL, NULL,
				&lifetime_str, MACRO_TYPE_COMMON, NULL, 0);
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
		zabbix_log(LOG_LEVEL_WARNING, "invalid discovery rule ID [" ZBX_FS_UI64 "]", discovery_itemid);
	DBfree_result(result);

	if (0 == hostid)
		goto clean;

	error = zbx_strdup(error, "");

	if (SUCCEED != zbx_json_open(value, &jp))
	{
		error = zbx_strdup(error, "Value should be a JSON object");
		goto error;
	}

	/* {"net.if.discovery":[{"{#IFNAME}":"eth0"},{"{#IFNAME}":"lo"},...]} */
	/*                     ^-------------------------------------------^  */
	if (SUCCEED != zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		error = zbx_dsprintf(error, "Cannot find the \"%s\" array in the received JSON object",
				ZBX_PROTO_TAG_DATA);
		goto error;
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
						" and r.name='%s'" ZBX_SQL_NODE,
					f_regexp_esc, DBand_node_local("r.regexpid"));

			zbx_free(f_regexp_esc);

			while (NULL != (row = DBfetch(result)))
			{
				add_regexp_ex(&regexps, &regexps_alloc, &regexps_num,
						f_regexp + 1, row[0], atoi(row[1]), row[2][0], atoi(row[3]));
			}
			DBfree_result(result);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() f_macro:'%s' f_regexp:'%s'",
				__function_name, f_macro, f_regexp);
	}

	DBlld_update_items(hostid, discovery_itemid, &jp_data, &error, f_macro, f_regexp, regexps, regexps_num,
			lifetime, ts->sec);
	DBlld_update_triggers(hostid, discovery_itemid, &jp_data, &error, f_macro, f_regexp, regexps, regexps_num);
	DBlld_update_graphs(hostid, discovery_itemid, &jp_data, &error, f_macro, f_regexp, regexps, regexps_num);

	clean_regexps_ex(regexps, &regexps_num);
	zbx_free(regexps);

	if (ITEM_STATE_NOTSUPPORTED == state)
	{
		zabbix_log(LOG_LEVEL_WARNING,  "discovery rule [" ZBX_FS_UI64 "][%s] became supported",
				discovery_itemid, zbx_host_key_string(discovery_itemid));

		add_event(0, EVENT_SOURCE_INTERNAL, EVENT_OBJECT_LLDRULE, discovery_itemid, ts, ITEM_STATE_NORMAL,
				NULL, NULL, 0, 0);
		process_events();

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",state=%d", ITEM_STATE_NORMAL);
	}
error:
	if (NULL != error && 0 != strcmp(error, db_error))
	{
		error_esc = DBdyn_escape_string_len(error, ITEM_ERROR_LEN);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",error='%s'", error_esc);

		zbx_free(error_esc);
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where itemid=" ZBX_FS_UI64, discovery_itemid);

	DBbegin();

	DBexecute("%s", sql);

	DBcommit();
clean:
	zbx_free(error);
	zbx_free(db_error);
	zbx_free(filter);
	zbx_free(discovery_key);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
