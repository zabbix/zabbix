/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "trigger_linking.h"
#include "db.h"

typedef struct
{
  zbx_uint64_t *new_triggerid;
  zbx_uint64_t *cur_triggerid;
  zbx_uint64_t hostid;
  zbx_uint64_t triggerid;
  const char *description;
  const char *expression;
  const char *recovery_expression;
  unsigned char recovery_mode;
  unsigned char status;
  unsigned char type;
  unsigned char priority;
  const char *comments;
  const char *url;
  unsigned char flags;
  unsigned char correlation_mode;
  const char *correlation_tag;
  unsigned char manual_close;
  const char *opdata;
  unsigned char discover;
  const char *event_name;
  char **error;

  char *parsed_expression;
  char *parsed_recovery_expression;
}
zbx_trigger_copy_t;

ZBX_PTR_VECTOR_DECL(trigger_copies, zbx_trigger_copy_t *);
ZBX_PTR_VECTOR_IMPL(trigger_copies, zbx_trigger_copy_t *);


////////////////////////////////////
// TARGET HOST TRIGGER DATA
typedef struct
{
  zbx_uint64_t triggerid;
  const char *description;
  const char *expression;
  const char *recovery_expression;

  char *parsed_expression;
  char *parsed_resovery_expression;
}
zbx_target_host_trigger_entry_t;

ZBX_PTR_VECTOR_DECL(target_host_trigger_data, zbx_target_host_trigger_entry_t *);
ZBX_PTR_VECTOR_IMPL(target_host_trigger_data, zbx_target_host_trigger_entry_t *);

static unsigned	zbx_host_triggers_main_data_hash_func(const void *data)
{
	const zbx_target_host_trigger_entry_t	* const *trigger_entry = (const zbx_target_host_trigger_entry_t * const *)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&((*trigger_entry)->triggerid), sizeof((*trigger_entry)->triggerid),
			ZBX_DEFAULT_HASH_SEED);
}

static int	zbx_host_triggers_main_data_compare_func(const void *d1, const void *d2)
{
	const zbx_target_host_trigger_entry_t	* const *trigger_entry_1 =
			(const zbx_target_host_trigger_entry_t * const *)d1;
	const zbx_target_host_trigger_entry_t	* const *trigger_entry_2 =
			(const zbx_target_host_trigger_entry_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL((*trigger_entry_1)->triggerid, (*trigger_entry_2)->triggerid);

	return 0;
}

static void	zbx_host_triggers_main_data_clean(zbx_hashset_t *x)
{
	zbx_hashset_iter_t	iter;
	zbx_target_host_trigger_entry_t	**trigger_entry;

	zbx_hashset_iter_reset(x, &iter);

	while (NULL != (trigger_entry = (zbx_target_host_trigger_entry_t **)zbx_hashset_iter_next(&iter)))
	{
	  //zbx_free((*audit_entry)->name);
	  //zbx_free(*audit_entry);
	}

	zbx_hashset_destroy(x);
}

//////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////
// TRIGGER FUNCTIONS DATA

typedef struct zbx_trigger_functions_entry
{
    zbx_uint64_t *triggerid;
  //vector of functionids/itemids/parameters/itemids/itemkeys

  zbx_vector_uint64_t	functionids;
  zbx_vector_uint64_t	itemids;
  zbx_vector_str_t	itemkeys;
  zbx_vector_str_t	parameters;
  
} zbx_trigger_functions_entry_t;

static unsigned	zbx_templates_triggers_functions_hash_func(const void *data)
{
	const zbx_trigger_functions_entry_t	* const *trigger_entry = (const zbx_trigger_functions_entry_t * const *)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&((*trigger_entry)->triggerid), sizeof((*trigger_entry)->triggerid),
			ZBX_DEFAULT_HASH_SEED);
}

static int	zbx_templates_triggers_functions_compare_func(const void *d1, const void *d2)
{
	const zbx_trigger_functions_entry_t	* const *trigger_entry_1 = (const zbx_trigger_functions_entry_t * const *)d1;
	const zbx_trigger_functions_entry_t	* const *trigger_entry_2 = (const zbx_trigger_functions_entry_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL((*trigger_entry_1)->triggerid, (*trigger_entry_2)->triggerid);

	return 0;
}

static void	zbx_templates_triggers_functions_clean(zbx_hashset_t *x)
{
	zbx_hashset_iter_t	iter;
	zbx_trigger_functions_entry_t	**trigger_entry;

	zbx_hashset_iter_reset(x, &iter);

	while (NULL != (trigger_entry = (zbx_trigger_functions_entry_t **)zbx_hashset_iter_next(&iter)))
	{
	  //zbx_free((*audit_entry)->name);
	  //zbx_free(*audit_entry);
	}

	zbx_hashset_destroy(x);
}

// TRIGGER HASHSET END




///////////////////////////////////////////////////////////
// TRIGGER DESCRIPTIONS MAP

typedef struct zbx_trigger_descriptions_entry
{
  const char *description;
  zbx_vector_uint64_t   triggerids;  
} zbx_trigger_descriptions_entry_t;

static unsigned	zbx_triggers_descriptions_hash_func(const void *data)
{
	const zbx_trigger_descriptions_entry_t	* const *trigger_entry = (const zbx_trigger_descriptions_entry_t * const *)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&((*trigger_entry)->description), sizeof((*trigger_entry)->description),
			ZBX_DEFAULT_HASH_SEED);
}

static int	zbx_triggers_descriptions_compare_func(const void *d1, const void *d2)
{
	const zbx_trigger_descriptions_entry_t	* const *trigger_entry_1 =
			(const zbx_trigger_descriptions_entry_t * const *)d1;
	const zbx_trigger_descriptions_entry_t	* const *trigger_entry_2 =
			(const zbx_trigger_descriptions_entry_t * const *)d2;

	//ZBX_RETURN_IF_NOT_EQUAL((*trigger_entry_1)->description, (*trigger_entry_2)->description);
	return strcmp((*trigger_entry_1)->description, (*trigger_entry_2)->description);
}

static void	zbx_templates_triggers_descriptions_clean(zbx_hashset_t *x)
{
	zbx_hashset_iter_t	iter;
	zbx_trigger_descriptions_entry_t	**trigger_entry;

	zbx_hashset_iter_reset(x, &iter);

	while (NULL != (trigger_entry = (zbx_trigger_descriptions_entry_t **)zbx_hashset_iter_next(&iter)))
	{
	  //zbx_free((*audit_entry)->name);
	  //zbx_free(*audit_entry);
	}

	zbx_hashset_destroy(x);
}

// TRIGGER HASHSET END



/******************************************************************************
 *                                                                            *
 * Function: DBcmp_triggers                                                   *
 *                                                                            *
 * Purpose: compare two triggers                                              *
 *                                                                            *
 * Parameters: triggerid1 - first trigger identificator from database         *
 *             triggerid2 - second trigger identificator from database        *
 *                                                                            *
 * Return value: SUCCEED - if triggers coincide                               *
 *                                                                            *
 ******************************************************************************/
static int	DBcmp_triggers(zbx_uint64_t triggerid1, const char *expression1, const char *recovery_expression1,
		zbx_uint64_t triggerid2, const char *expression2, const char *recovery_expression2)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		search[MAX_ID_LEN + 3], replace[MAX_ID_LEN + 3], *old_expr = NULL, *expr = NULL, *rexpr = NULL;
	int		res = SUCCEED;

	expr = zbx_strdup(NULL, expression2);
	rexpr = zbx_strdup(NULL, recovery_expression2);


	// triggerid1 - template trigger
	// triggerid2 - host trigger

	// expression1, recovery_expression1 - template
	// expression2, recovery_expression2 - host

	// select functions from triggers of interest, so that function item keys are the same and function parameters
	// are the same
	result = DBselect(
			"select f1.functionid,f2.functionid"
			" from functions f1,functions f2,items i1,items i2"
			" where f1.name=f2.name"
				" and f1.parameter=f2.parameter"
				" and i1.key_=i2.key_"
				" and i1.itemid=f1.itemid"
				" and i2.itemid=f2.itemid"
				" and f1.triggerid=" ZBX_FS_UI64
				" and f2.triggerid=" ZBX_FS_UI64,
				triggerid1, triggerid2);

	while (NULL != (row = DBfetch(result)))
	{
	  zabbix_log(LOG_LEVEL_INFORMATION, "333 functinoid1: %s, functionid2: %s ", row[0], row[1]);

		zbx_snprintf(search, sizeof(search), "{%s}", row[1]);
		zbx_snprintf(replace, sizeof(replace), "{%s}", row[0]);

		zabbix_log(LOG_LEVEL_INFORMATION, "BEFORE expr: %s, rexpr: %s", expr, rexpr);

		old_expr = expr;
		expr = string_replace(expr, search, replace);
		zbx_free(old_expr);

		old_expr = rexpr;
		rexpr = string_replace(rexpr, search, replace);
		zbx_free(old_expr);
		zabbix_log(LOG_LEVEL_INFORMATION, "AFTER expr: %s, rexpr: %s", expr, rexpr);
	}
	DBfree_result(result);

	if (0 != strcmp(expression1, expr) || 0 != strcmp(recovery_expression1, rexpr))
		res = FAIL;

	zbx_free(rexpr);
	zbx_free(expr);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_trigger_to_host                                           *
 *                                                                            *
 * Purpose: copy specified trigger to host                                    *
 *                                                                            *
 * Parameters: new_triggerid - [OUT] id of new trigger created based on       *
 *                                   template trigger                         *
 *             cur_triggerid - [OUT] id of existing trigger that was linked   *
 *                                   to the template trigger                  *
 *             hostid - host identificator from database                      *
 *             triggerid - trigger identificator from database                *
 *             description - trigger description                              *
 *             expression - trigger expression                                *
 *             recovery_expression - trigger recovery expression              *
 *             recovery_mode - trigger recovery mode                          *
 *             status - trigger status                                        *
 *             type - trigger type                                            *
 *             priority - trigger priority                                    *
 *             comments - trigger comments                                    *
 *             url - trigger url                                              *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_trigger_to_host(zbx_uint64_t *new_triggerid, zbx_uint64_t *cur_triggerid, zbx_uint64_t hostid,
		zbx_uint64_t triggerid, const char *description, const char *expression,
		const char *recovery_expression, unsigned char recovery_mode, unsigned char status, unsigned char type,
		unsigned char priority, const char *comments, const char *url, unsigned char flags,
		unsigned char correlation_mode, const char *correlation_tag, unsigned char manual_close,
		const char *opdata, unsigned char discover, const char *event_name, char **error)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	zbx_uint64_t	itemid,	h_triggerid, functionid;
	char		*description_esc = NULL,
			*comments_esc = NULL,
			*url_esc = NULL,
			*function_esc = NULL,
			*parameter_esc = NULL,
			*correlation_tag_esc,
			*opdata_esc, *event_name_esc;
	int		res = FAIL;

	sql = (char *)zbx_malloc(sql, sql_alloc);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	description_esc = DBdyn_escape_string(description);
	correlation_tag_esc = DBdyn_escape_string(correlation_tag);
	opdata_esc = DBdyn_escape_string(opdata);
	event_name_esc = DBdyn_escape_string(event_name);


	// comparing name
	// selecting trigger, functions, items data for TARGET host, so that trigger desription is the same
	result = DBselect(
			"select distinct t.triggerid,t.expression,t.recovery_expression"
			" from triggers t,functions f,items i"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and t.templateid is null"
				" and i.hostid=" ZBX_FS_UI64
				" and t.description='%s'",
			hostid, description_esc);

	while (NULL != (row = DBfetch(result)))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "222 triggerid: %s, expression: %s, rec_expression: %s ", row[0],
				row[1], row[2]);
		ZBX_STR2UINT64(h_triggerid, row[0]);

		// comparing expression
		if (SUCCEED != DBcmp_triggers(triggerid, expression, recovery_expression,
				h_triggerid, row[1], row[2]))
			continue;

		/* link not linked trigger with same description and expression */
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update triggers"
				" set templateid=" ZBX_FS_UI64
					",flags=%d"
					",recovery_mode=%d"
					",correlation_mode=%d"
					",correlation_tag='%s'"
					",manual_close=%d"
					",opdata='%s'"
					",discover=%d"
					",event_name='%s'"
				" where triggerid=" ZBX_FS_UI64 ";\n",
				triggerid, (int)flags, (int)recovery_mode, (int)correlation_mode, correlation_tag_esc,
				(int)manual_close, opdata_esc, (int)discover, event_name_esc, h_triggerid);

		*new_triggerid = 0;
		*cur_triggerid = h_triggerid;

		res = SUCCEED;
		break;
	}
	DBfree_result(result);

	/* create trigger if no updated triggers */
	if (SUCCEED != res)
	{
		zbx_eval_context_t	ctx, ctx_r;

		*new_triggerid = DBget_maxid("triggers");
		*cur_triggerid = 0;

		comments_esc = DBdyn_escape_string(comments);
		url_esc = DBdyn_escape_string(url);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"insert into triggers"
					" (triggerid,description,priority,status,"
						"comments,url,type,value,state,templateid,flags,recovery_mode,"
						"correlation_mode,correlation_tag,manual_close,opdata,discover,"
						"event_name)"
					" values (" ZBX_FS_UI64 ",'%s',%d,%d,"
						"'%s','%s',%d,%d,%d," ZBX_FS_UI64 ",%d,%d,"
						"%d,'%s',%d,'%s',%d,'%s');\n",
					*new_triggerid, description_esc, (int)priority, (int)status, comments_esc,
					url_esc, (int)type, TRIGGER_VALUE_OK, TRIGGER_STATE_NORMAL, triggerid,
					(int)flags, (int)recovery_mode, (int)correlation_mode, correlation_tag_esc,
					(int)manual_close, opdata_esc, (int)discover, event_name_esc);

		zbx_free(url_esc);
		zbx_free(comments_esc);

		if (SUCCEED != (res = zbx_eval_parse_expression(&ctx, expression,
				ZBX_EVAL_PARSE_TRIGGER_EXPRESSSION | ZBX_EVAL_COMPOSE_FUNCTIONID, error)))
			goto out;

		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == recovery_mode &&
				(SUCCEED != (res = zbx_eval_parse_expression(&ctx_r, recovery_expression,
						ZBX_EVAL_PARSE_TRIGGER_EXPRESSSION | ZBX_EVAL_COMPOSE_FUNCTIONID,
						error))))
		{
			zbx_eval_clear(&ctx);
			goto out;
		}

		/* Loop: functions */
		result = DBselect(
				"select hi.itemid,tf.functionid,tf.name,tf.parameter,ti.key_"
				" from functions tf,items ti"
				" left join items hi"
					" on hi.key_=ti.key_"
						" and hi.hostid=" ZBX_FS_UI64
				" where tf.itemid=ti.itemid"
					" and tf.triggerid=" ZBX_FS_UI64,
				hostid, triggerid);

		while (SUCCEED == res && NULL != (row = DBfetch(result)))
		{
			if (SUCCEED != DBis_null(row[0]))
			{
				zbx_uint64_t	old_functionid;

				ZBX_STR2UINT64(itemid, row[0]);

				functionid = DBget_maxid("functions");

				function_esc = DBdyn_escape_string(row[2]);
				parameter_esc = DBdyn_escape_string(row[3]);

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"insert into functions"
						" (functionid,itemid,triggerid,name,parameter)"
						" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ","
							ZBX_FS_UI64 ",'%s','%s');\n",
						functionid, itemid, *new_triggerid,
						function_esc, parameter_esc);

				ZBX_DBROW2UINT64(old_functionid, row[1]);
				zbx_eval_replace_functionid(&ctx, old_functionid, functionid);
				if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == recovery_mode)
					zbx_eval_replace_functionid(&ctx_r, old_functionid, functionid);

				zbx_free(parameter_esc);
				zbx_free(function_esc);
			}
			else
			{
				*error = zbx_dsprintf(*error, "Missing similar key '%s' for host [" ZBX_FS_UI64 "]",
						row[4], hostid);
				res = FAIL;
			}
		}
		DBfree_result(result);

		if (SUCCEED == (res = zbx_eval_validate_replaced_functionids(&ctx, error)) &&
				TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == recovery_mode)
		{
			res = zbx_eval_validate_replaced_functionids(&ctx_r, error);
		}

		if (SUCCEED == res)
		{
			char	*new_expression = NULL, *esc;

			zbx_eval_compose_expression(&ctx, &new_expression);
			esc = DBdyn_escape_field("triggers", "expression", new_expression);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set expression='%s'", esc);
			zbx_free(esc);
			zbx_free(new_expression);

			if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == recovery_mode)
			{
				zbx_eval_compose_expression(&ctx_r, &new_expression);
				esc = DBdyn_escape_field("triggers", "recovery_expression", new_expression);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",recovery_expression='%s'", esc);
				zbx_free(esc);
				zbx_free(new_expression);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where triggerid=" ZBX_FS_UI64 ";\n",
					*new_triggerid);
		}

		zbx_eval_clear(&ctx);
		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == recovery_mode)
			zbx_eval_clear(&ctx_r);
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);
out:
	zbx_free(sql);
	zbx_free(correlation_tag_esc);
	zbx_free(event_name_esc);
	zbx_free(opdata_esc);
	zbx_free(description_esc);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBresolve_template_trigger_dependencies                          *
 *                                                                            *
 * Purpose: resolves trigger dependencies for the specified triggers based on *
 *          host and linked templates                                         *
 *                                                                            *
 * Parameters: hostid    - [IN] host identificator from database              *
 *             trids     - [IN] array of trigger identifiers from database    *
 *             trids_num - [IN] trigger count in trids array                  *
 *             links     - [OUT] pairs of trigger dependencies  (down,up)     *
 *                                                                            *
 ******************************************************************************/
static void	DBresolve_template_trigger_dependencies(zbx_uint64_t hostid, const zbx_uint64_t *trids,
		int trids_num, zbx_vector_uint64_pair_t *links)
{
	DB_RESULT			result;
	DB_ROW				row;
	zbx_uint64_pair_t		map_id, dep_list_id;
	char				*sql = NULL;
	size_t				sql_alloc = 512, sql_offset;
	zbx_vector_uint64_pair_t	dep_list_ids, map_ids;
	zbx_vector_uint64_t		all_templ_ids;
	zbx_uint64_t			templateid_down, templateid_up,
					triggerid_down, triggerid_up,
					hst_triggerid, tpl_triggerid;
	int				i, j;

	zbx_vector_uint64_create(&all_templ_ids);
	zbx_vector_uint64_pair_create(&dep_list_ids);
	zbx_vector_uint64_pair_create(links);
	sql = (char *)zbx_malloc(sql, sql_alloc);

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct td.triggerid_down,td.triggerid_up"
			" from triggers t,trigger_depends td"
			" where t.templateid in (td.triggerid_up,td.triggerid_down) and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.triggerid", trids, trids_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(dep_list_id.first, row[0]);
		ZBX_STR2UINT64(dep_list_id.second, row[1]);
		zbx_vector_uint64_pair_append(&dep_list_ids, dep_list_id);
		zbx_vector_uint64_append(&all_templ_ids, dep_list_id.first);
		zbx_vector_uint64_append(&all_templ_ids, dep_list_id.second);
	}
	DBfree_result(result);

	if (0 == dep_list_ids.values_num)	/* not all trigger template have a dependency trigger */
	{
		zbx_vector_uint64_destroy(&all_templ_ids);
		zbx_vector_uint64_pair_destroy(&dep_list_ids);
		zbx_free(sql);
		return;
	}

	zbx_vector_uint64_pair_create(&map_ids);
	zbx_vector_uint64_sort(&all_templ_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&all_templ_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.triggerid,t.templateid"
			" from triggers t,functions f,items i"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and",
				hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.templateid", all_templ_ids.values,
			all_templ_ids.values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(map_id.first, row[0]);
		ZBX_DBROW2UINT64(map_id.second, row[1]);
		zbx_vector_uint64_pair_append(&map_ids, map_id);
	}
	DBfree_result(result);

	zbx_free(sql);
	zbx_vector_uint64_destroy(&all_templ_ids);

	for (i = 0; i < dep_list_ids.values_num; i++)
	{
		templateid_down = dep_list_ids.values[i].first;
		templateid_up = dep_list_ids.values[i].second;

		/* Convert template ids to corresponding trigger ids.         */
		/* If template trigger depends on host trigger rather than    */
		/* template trigger then up id conversion will fail and the   */
		/* original value (host trigger id) will be used as intended. */
		triggerid_down = 0;
		triggerid_up = templateid_up;

		for (j = 0; j < map_ids.values_num; j++)
		{
			hst_triggerid = map_ids.values[j].first;
			tpl_triggerid = map_ids.values[j].second;

			if (tpl_triggerid == templateid_down)
				triggerid_down = hst_triggerid;

			if (tpl_triggerid == templateid_up)
				triggerid_up = hst_triggerid;
		}

		if (0 != triggerid_down)
		{
			zbx_uint64_pair_t	link = {triggerid_down, triggerid_up};

			zbx_vector_uint64_pair_append(links, link);
		}
	}

	zbx_vector_uint64_pair_destroy(&map_ids);
	zbx_vector_uint64_pair_destroy(&dep_list_ids);
}

/******************************************************************************
 *                                                                            *
 * Function: DBadd_template_dependencies_for_new_triggers                     *
 *                                                                            *
 * Purpose: update trigger dependencies for specified host                    *
 *                                                                            *
 * Parameters: hostid    - [IN] host identificator from database              *
 *             trids     - [IN] array of trigger identifiers from database    *
 *             trids_num - [IN] trigger count in trids array                  *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
static int	DBadd_template_dependencies_for_new_triggers(zbx_uint64_t hostid, zbx_uint64_t *trids, int trids_num)
{
	int				i;
	zbx_uint64_t			triggerdepid;
	zbx_db_insert_t			db_insert;
	zbx_vector_uint64_pair_t	links;

	if (0 == trids_num)
		return SUCCEED;

	DBresolve_template_trigger_dependencies(hostid, trids, trids_num, &links);

	if (0 < links.values_num)
	{
		triggerdepid = DBget_maxid_num("trigger_depends", links.values_num);

		zbx_db_insert_prepare(&db_insert, "trigger_depends", "triggerdepid", "triggerid_down", "triggerid_up",
				NULL);

		for (i = 0; i < links.values_num; i++)
		{
			zbx_db_insert_add_values(&db_insert, triggerdepid++, links.values[i].first,
					links.values[i].second);
		}

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zbx_vector_uint64_pair_destroy(&links);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_trigger_tags                                     *
 *                                                                            *
 * Purpose: copies tags from template triggers to created/linked triggers     *
 *                                                                            *
 * Parameters: new_triggerids - the created trigger ids                        *
 *             cur_triggerids - the linked trigfer ids                         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 ******************************************************************************/
static int	DBcopy_template_trigger_tags(const zbx_vector_uint64_t *new_triggerids,
		const zbx_vector_uint64_t *cur_triggerids)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	int			i;
	zbx_vector_uint64_t	triggerids;
	zbx_uint64_t		triggerid;
	zbx_db_insert_t		db_insert;

	if (0 == new_triggerids->values_num && 0 == cur_triggerids->values_num)
		return SUCCEED;

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_reserve(&triggerids, new_triggerids->values_num + cur_triggerids->values_num);

	if (0 != cur_triggerids->values_num)
	{
		/* remove tags from host triggers that were linking to template triggers */
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from trigger_tag where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", cur_triggerids->values,
				cur_triggerids->values_num);
		DBexecute("%s", sql);

		sql_offset = 0;

		for (i = 0; i < cur_triggerids->values_num; i++)
			zbx_vector_uint64_append(&triggerids, cur_triggerids->values[i]);
	}

	for (i = 0; i < new_triggerids->values_num; i++)
		zbx_vector_uint64_append(&triggerids, new_triggerids->values[i]);

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.triggerid,tt.tag,tt.value"
			" from trigger_tag tt,triggers t"
			" where tt.triggerid=t.templateid"
			" and");

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.triggerid", triggerids.values, triggerids.values_num);

	result = DBselect("%s", sql);

	zbx_db_insert_prepare(&db_insert, "trigger_tag", "triggertagid", "triggerid", "tag", "value", NULL);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), triggerid, row[1], row[2]);
	}
	DBfree_result(result);

	zbx_free(sql);

	zbx_db_insert_autoincrement(&db_insert, "triggertagid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_vector_uint64_destroy(&triggerids);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBcopy_template_triggers                                         *
 *                                                                            *
 * Purpose: Copy template triggers to host                                    *
 *                                                                            *
 * Parameters: hostid      - [IN] host identificator from database            *
 *             templateids - [IN] array of template IDs                       *
 *             error       - [IN] the error message                           *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	DBcopy_template_triggers(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids, char **error)
{
	char			*sql = NULL;
	size_t			sql_alloc = 512, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		triggerid, new_triggerid, cur_triggerid;
	int			res = SUCCEED;
	zbx_vector_uint64_t	new_triggerids, cur_triggerids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&new_triggerids);
	zbx_vector_uint64_create(&cur_triggerids);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	sql_offset = 0;

	// selecting trigger, functions, items data from TEMPLATES
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct t.triggerid,t.description,t.expression,t.status,"
				"t.type,t.priority,t.comments,t.url,t.flags,t.recovery_expression,t.recovery_mode,"
				"t.correlation_mode,t.correlation_tag,t.manual_close,t.opdata,t.discover,t.event_name"
			" from triggers t,functions f,items i"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (SUCCEED == res && NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);

		res = DBcopy_trigger_to_host(&new_triggerid, &cur_triggerid, hostid, triggerid,
				row[1],				/* description */
				row[2],				/* expression */
				row[9],				/* recovery_expression */
				(unsigned char)atoi(row[10]),	/* recovery_mode */
				(unsigned char)atoi(row[3]),	/* status */
				(unsigned char)atoi(row[4]),	/* type */
				(unsigned char)atoi(row[5]),	/* priority */
				row[6],				/* comments */
				row[7],				/* url */
				(unsigned char)atoi(row[8]),	/* flags */
				(unsigned char)atoi(row[11]),	/* correlation_mode */
				row[12],			/* correlation_tag */
				(unsigned char)atoi(row[13]),	/* manual_close */
				row[14],			/* opdata */
				(unsigned char)atoi(row[15]),	/* discover */
				row[16],			/* event_name */
				error);

		if (0 != new_triggerid)				/* new trigger added */
			zbx_vector_uint64_append(&new_triggerids, new_triggerid);
		else
			zbx_vector_uint64_append(&cur_triggerids, cur_triggerid);
	}
	DBfree_result(result);



	//////////////////////////////////////////////////////////////////////////////////////////////








	
	if (SUCCEED == res)
		res = DBadd_template_dependencies_for_new_triggers(hostid, new_triggerids.values, new_triggerids.values_num);

	if (SUCCEED == res)
		res = DBcopy_template_trigger_tags(&new_triggerids, &cur_triggerids);

	zbx_vector_uint64_destroy(&cur_triggerids);
	zbx_vector_uint64_destroy(&new_triggerids);

	if (FAIL == res && NULL == *error)
		*error = zbx_strdup(NULL, "unknown error while linking triggers");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static void	get_trigger_funcs(zbx_vector_uint64_t *triggerids, zbx_hashset_t *funcs_res)
{
	char		*sql_yy = NULL;
	size_t		sql_alloc_yy = 256, sql_offset_yy = 0;
	DB_RESULT	result_yy;
	DB_ROW		row_yy;

#define	TRIGGER_FUNCS_HASHSET_DEF_SIZE	100
	zbx_hashset_create(funcs_res, TRIGGER_FUNCS_HASHSET_DEF_SIZE,
			   zbx_templates_triggers_functions_hash_func,
			zbx_templates_triggers_functions_compare_func);
#undef TRIGGER_FUNCS_HASHSET_DEF_SIZE
	
	sql_yy = (char *)zbx_malloc(sql_yy, sql_alloc_yy);

	zbx_strcpy_alloc(&sql_yy, &sql_alloc_yy, &sql_offset_yy,
			 "select f.triggerid,f.functionid,f.parameter,i.itemid,i.key_"
				" from functions f,items i"
				" where "
				" and i.itemid=f.itemid"
				" and");

	DBadd_condition_alloc(&sql_yy, &sql_alloc_yy, &sql_offset_yy, "f.triggerid", triggerids->values,
			triggerids->values_num);

	result_yy = DBselect("%s", sql_yy);

	while (NULL != (row_yy = DBfetch(result_yy)))
	{
		zbx_trigger_functions_entry_t *found;
		zbx_trigger_functions_entry_t temp_t;
		zbx_uint64_t functionid_temp_var, itemid_temp_var;

		ZBX_STR2UINT64(temp_t.triggerid,row_yy[0]);
		ZBX_STR2UINT64(functionid_temp_var, row_yy[1]);
		ZBX_STR2UINT64(itemid_temp_var, row_yy[3]);

		if (NULL != (found =  (zbx_trigger_functions_entry_t *)zbx_hashset_search(funcs_res,
				&temp_t)))
		{
			zbx_vector_uint64_append(&(found->functionids), functionid_temp_var);
			zbx_vector_uint64_append(&(found->itemids), itemid_temp_var);
			zbx_vector_str_append(&(found->itemkeys), row_yy[4]);
			zbx_vector_str_append(&(found->parameters), row_yy[2]);
		}
		else
		{
			zbx_vector_uint64_t	functionids_local;
			zbx_vector_uint64_t	itemids_local;
			zbx_vector_str_t	itemkeys_local;
			zbx_vector_str_t	parameters_local;
			zbx_trigger_functions_entry_t *local_temp_t;

			zbx_vector_uint64_create(&functionids_local);
			zbx_vector_uint64_create(&itemids_local);
			zbx_vector_str_create(&itemkeys_local);
			zbx_vector_str_create(&parameters_local);

			zbx_vector_uint64_append(&functionids_local, functionid_temp_var);
			zbx_vector_uint64_append(&itemids_local, itemid_temp_var);
			zbx_vector_str_append(&itemkeys_local, row_yy[4]);
			zbx_vector_str_append(&parameters_local, row_yy[2]);

			local_temp_t = (zbx_trigger_functions_entry_t *)zbx_malloc(NULL,
					sizeof(zbx_trigger_functions_entry_t));
			local_temp_t->triggerid = temp_t.triggerid;
			local_temp_t->functionids = functionids_local;
			local_temp_t->itemids = itemids_local;
			local_temp_t->parameters = parameters_local;
			zbx_hashset_insert(funcs_res, &local_temp_t, sizeof(local_temp_t));
		}

	}
	zbx_free(sql_yy);
	DBfree_result(result_yy);
}


static void	get_templates_triggers_data(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids,
		zbx_vector_trigger_copies_t *trigger_copies, zbx_vector_str_t *templates_triggers_descriptions,
		zbx_vector_uint64_t *temp_templates_triggerids, int *status_res)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = 512, sql_offset = 0;
	zbx_trigger_copy_t	*trigger_copy;

	zbx_vector_trigger_copies_create(trigger_copies);
	zbx_vector_uint64_create(temp_templates_triggerids);
	zbx_vector_str_create(templates_triggers_descriptions);
	
	// need to select functions data for every trigger
	// need to select target host data

	// need to consturct an expression for every trigger
	// then for every template trigger expression - compare it with host trigger expression,
	// if their expressions are the same - then do UPDATE, otherwise do the INSERT
	// UPDATE compare the old values with new ones and add that to the long SQL structure

	// selecting trigger, functions, items data from TEMPLATES
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct t.triggerid,t.description,t.expression,t.status,"
				"t.type,t.priority,t.comments,t.url,t.flags,t.recovery_expression,t.recovery_mode,"
				"t.correlation_mode,t.correlation_tag,t.manual_close,t.opdata,t.discover,t.event_name"
			" from triggers t,functions f,items i"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", templateids->values, templateids->values_num);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (SUCCEED == status_res && NULL != (row = DBfetch(result)))
	{
		//ZBX_STR2UINT64(triggerid, row[0]);
		//	addtotriggerids

		trigger_copy = (zbx_trigger_copy_t *)zbx_malloc(NULL, sizeof(zbx_trigger_copy_t));
		trigger_copy->hostid = hostid;
		ZBX_STR2UINT64(trigger_copy->triggerid, row[0]);
		trigger_copy->description = zbx_strdup(NULL, row[1]);
		trigger_copy->expression = zbx_strdup(NULL, row[2]);
		trigger_copy->recovery_expression = zbx_strdup(NULL, row[9]);
		trigger_copy->recovery_mode = (unsigned char)atoi(row[10]);
		trigger_copy->status = (unsigned char)atoi(row[3]);
		trigger_copy->type = (unsigned char)atoi(row[4]);
		trigger_copy->priority = (unsigned char)atoi(row[5]);
		trigger_copy->comments = zbx_strdup(NULL, row[6]);
		trigger_copy->url = zbx_strdup(NULL, row[7]);
		trigger_copy->flags = (unsigned char)atoi(row[8]);
		trigger_copy->correlation_mode = (unsigned char)atoi(row[11]);
		trigger_copy->correlation_tag = zbx_strdup(NULL, row[12]);
		trigger_copy->manual_close = (unsigned char)atoi(row[13]);
		trigger_copy->opdata = zbx_strdup(NULL, row[14]);
		trigger_copy->discover = (unsigned char)atoi(row[15]);
		trigger_copy->event_name = zbx_strdup(NULL, row[16]);

		zbx_vector_trigger_copies_append(trigger_copies, trigger_copy);
		zbx_vector_str_append(templates_triggers_descriptions, zbx_strdup(NULL,
				DBdyn_escape_string(trigger_copy->description)));

		zbx_vector_uint64_append(temp_templates_triggerids, trigger_copy->triggerid);
	}
}

static void	get_target_host_main_data(zbx_uint64_t hostid, zbx_vector_str_t *templates_triggers_descriptions,
		zbx_hashset_t *zbx_host_triggers_main_data, zbx_vector_uint64_t *temp_host_triggerids,
		zbx_hashset_t *triggers_descriptions)
{
	char		*sql_xx = NULL;
	size_t		sql_alloc_xx = 256, sql_offset_xx = 0;
	DB_RESULT	result_xx;
	DB_ROW		row_xx;

#define	TRIGGER_FUNCS_HASHSET_DEF_SIZE  100
	zbx_hashset_create(zbx_host_triggers_main_data, TRIGGER_FUNCS_HASHSET_DEF_SIZE, zbx_host_triggers_main_data_hash_func,
			zbx_host_triggers_main_data_compare_func);
#undef TRIGGER_FUNCS_HASHSET_DEF_SIZE

	zbx_vector_uint64_create(temp_host_triggerids);
	
	sql_xx = (char *)zbx_malloc(sql_xx, sql_alloc_xx);

	zbx_snprintf_alloc(&sql_xx, &sql_alloc_xx, &sql_offset_xx,
		"select distinct t.triggerid,t.description, t.expression,t.recovery_expression"
		" from triggers t,functions f,items i"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and t.templateid is null"
				" and i.hostid=" ZBX_FS_UI64
				" and",
			   hostid);

	DBadd_str_condition_alloc(&sql_xx, &sql_alloc_xx, &sql_offset_xx, "t.description",
		(const char **)templates_triggers_descriptions->values,
		templates_triggers_descriptions->values_num);

	result_xx = DBselect("%s", sql_xx);

	while (NULL != (row_xx = DBfetch(result_xx)))
	{
		zbx_target_host_trigger_entry_t *ttt = (zbx_target_host_trigger_entry_t *)zbx_malloc(NULL,
				sizeof(zbx_target_host_trigger_entry_t));

		ZBX_STR2UINT64(ttt->triggerid,row_xx[0]);
		ttt->description = zbx_strdup(NULL, row_xx[1]);
		ttt->expression = zbx_strdup(NULL, row_xx[2]);
		ttt->recovery_expression = zbx_strdup(NULL, row_xx[3]);		  
		zbx_hashset_insert(zbx_host_triggers_main_data, ttt, sizeof(*ttt));
		zbx_vector_uint64_append(temp_host_triggerids, ttt->triggerid);


		//update descriptions
		{
			zbx_trigger_descriptions_entry_t	*found;
			zbx_trigger_descriptions_entry_t	temp_t;

			temp_t.description = zbx_strdup(NULL, ttt->description);

			if (NULL != (found =  (zbx_trigger_descriptions_entry_t *)zbx_hashset_search(
					triggers_descriptions, &temp_t)))
			{
				zbx_vector_uint64_append(&(found->triggerids), ttt->triggerid);
			}
			else
			{
				zbx_vector_uint64_t			triggerids_local;
				zbx_trigger_descriptions_entry_t	*local_temp_t;

				zbx_vector_uint64_create(&triggerids_local);
				zbx_vector_uint64_append(&triggerids_local, ttt->triggerid);

				local_temp_t = (zbx_trigger_descriptions_entry_t *)zbx_malloc(NULL,
						sizeof(zbx_trigger_descriptions_entry_t));
				local_temp_t->description = zbx_strdup(NULL, ttt->description);
				zbx_vector_uint64_append(&(local_temp_t->triggerids), ttt->triggerid);
				zbx_hashset_insert(triggers_descriptions, &local_temp_t, sizeof(local_temp_t));
			}
		}
	}
	zbx_free(sql_xx);
	DBfree_result(result_xx);
}


// get the templates | host
//     1) main data - triggers
//     2) functions
int	DBcopy_template_triggers3(zbx_uint64_t hostid, const zbx_vector_uint64_t *templateids, char **error)
{
	int			res = SUCCEED;
	zbx_vector_uint64_t	new_triggerids, cur_triggerids;

	zbx_vector_trigger_copies_t	trigger_copies;
	zbx_vector_str_t		templates_triggers_descriptions;
	zbx_vector_uint64_t		temp_templates_triggerids;
	zbx_vector_uint64_t		temp_host_triggerids;
	
	// TRIGGER HASHSET
	zbx_hashset_t zbx_templates_triggers_funcs;
	zbx_hashset_t zbx_host_triggers_funcs;

	zbx_hashset_t zbx_host_triggers_main_data;
	zbx_hashset_t host_triggers_descriptions;
	
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&new_triggerids);
	zbx_vector_uint64_create(&cur_triggerids);

#define	TRIGGER_FUNCS_HASHSET_DEF_SIZE	100
	zbx_hashset_create(&host_triggers_descriptions, TRIGGER_FUNCS_HASHSET_DEF_SIZE,
			   zbx_triggers_descriptions_hash_func,
			zbx_triggers_descriptions_compare_func);
#undef TRIGGER_FUNCS_HASHSET_DEF_SIZE
	
	/////////////////////////////////////////////////////////////////////////////////
	get_templates_triggers_data(hostid, templateids, &trigger_copies, &templates_triggers_descriptions,
			&temp_templates_triggerids, &res);
	get_target_host_main_data(hostid, &templates_triggers_descriptions, &zbx_host_triggers_main_data,
			&temp_host_triggerids, &host_triggers_descriptions);
	get_trigger_funcs(&temp_templates_triggerids, &zbx_templates_triggers_funcs);
	get_trigger_funcs(&temp_host_triggerids, &zbx_host_triggers_funcs);

	/////////////////////////////////////////////////////////////////////////////////

	{
	  int i = 0;
	// go through template vector - quickly compare template_trigger with targ
	  for (i = 0; i < trigger_copies->values_num; i++) 
	  {
	    
	  }
	///////
	}

	// BLOCK x
	/////////////////
	//res = DBcopy_triggers_to_host3(&new_triggerids, &cur_triggerids, hostid, &triggerids, &trigger_copies, error);










	



	
	//move to copy_trigger_to_host3
	/* if (0 != new_triggerid)				/\* new trigger added *\/ */
	/*	zbx_vector_uint64_append(&new_triggerids, new_triggerid); */
	/* else */
	/*	zbx_vector_uint64_append(&cur_triggerids, cur_triggerid); */

//DBfree_result(result);



	//////////////////////////////////////////////////////////////////////////////////////////////


	if (SUCCEED == res)
	{
		res = DBadd_template_dependencies_for_new_triggers(hostid, new_triggerids.values,
				new_triggerids.values_num);
	}

if (SUCCEED == res)
		res = DBcopy_template_trigger_tags(&new_triggerids, &cur_triggerids);

	zbx_vector_uint64_destroy(&cur_triggerids);
	zbx_vector_uint64_destroy(&new_triggerids);

	if (FAIL == res && NULL == *error)
		*error = zbx_strdup(NULL, "unknown error while linking triggers");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}
