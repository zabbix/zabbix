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

#include "lld.h"
#include "db.h"
#include "log.h"
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
 * Function: DBlld_get_trigger_functions                                      *
 *                                                                            *
 * Purpose: selects trigger functions to an array by triggerid                *
 *                                                                            *
 * Return value: array of trigger functions                                   *
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

		if (NULL != jp_row && 0 != (function->flags & ZBX_FLAG_DISCOVERY_PROTOTYPE))
			substitute_key_macros(&function->key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(functions, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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

			if (0 != (ZBX_FLAG_DISCOVERY_PROTOTYPE & function->flags))
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
 ******************************************************************************/
static void	DBlld_save_triggers(zbx_vector_ptr_t *triggers, unsigned char status, unsigned char type,
		unsigned char priority, const char *url_esc, zbx_uint64_t parent_triggerid,
		const char *description_proto_esc)
{
	int			i, j, new_triggers = 0, new_functions = 0;
	zbx_lld_trigger_t	*trigger;
	zbx_lld_function_t	*function;
	zbx_uint64_t		triggerid = 0, triggerdiscoveryid = 0, functionid = 0;
	char			*sql1 = NULL, *sql2 = NULL, *sql3 = NULL, *sql4 = NULL, *error_esc = NULL;
	size_t			sql1_alloc = 8 * ZBX_KIBIBYTE, sql1_offset = 0,
				sql2_alloc = 2 * ZBX_KIBIBYTE, sql2_offset = 0,
				sql3_alloc = 2 * ZBX_KIBIBYTE, sql3_offset = 0,
				sql4_alloc = 8 * ZBX_KIBIBYTE, sql4_offset = 0;
	const char		*ins_triggers_sql =
				"insert into triggers"
				" (triggerid,description,expression,priority,status,"
					"comments,url,type,value,state,flags,error)"
				" values ";
	const char		*ins_trigger_discovery_sql =
				"insert into trigger_discovery"
				" (triggerdiscoveryid,triggerid,parent_triggerid,name)"
				" values ";
	const char		*ins_functions_sql =
				"insert into functions"
				" (functionid,itemid,triggerid,function,parameter)"
				" values ";

	for (i = 0; i < triggers->values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		if (0 == trigger->triggerid)
			new_triggers++;

		if (1 == trigger->update_expression)
			new_functions += trigger->functions.values_num;
	}

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
		error_esc = DBdyn_escape_string_len("Trigger just added. No status update so far.", TRIGGER_ERROR_LEN);
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
					"(" ZBX_FS_UI64 ",'%s','%s',%d,%d,'%s','%s',%d,%d,%d,%d,'%s')" ZBX_ROW_DL,
					trigger->triggerid, description_esc, expression_esc, (int)priority, (int)status,
					comments_esc, url_esc, (int)type, TRIGGER_VALUE_OK,
					TRIGGER_STATE_NORMAL, ZBX_FLAG_DISCOVERY_CREATED, error_esc);
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
		zbx_free(error_esc);
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
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_update_triggers                                            *
 *                                                                            *
 * Purpose: add or update triggers for discovered items                       *
 *                                                                            *
 ******************************************************************************/
void	DBlld_update_triggers(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, struct zbx_json_parse *jp_data,
		char **error, const char *f_macro, const char *f_regexp, zbx_vector_ptr_t *regexps)
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
			lld_ruleid);

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
			lld_ruleid);

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

			if (SUCCEED != lld_check_record(&jp_row, f_macro, f_regexp, regexps))
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
