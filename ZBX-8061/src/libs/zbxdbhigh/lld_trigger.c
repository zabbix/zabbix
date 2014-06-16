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
	zbx_uint64_t		triggerid;
	char			*description;
	char			*description_orig;
	char			*expression;
	char			*expression_orig;
	char			*comments;
	char			*comments_orig;
	zbx_vector_ptr_t	functions;
#define ZBX_FLAG_LLD_TRIGGER_UNSET			__UINT64_C(0x00)
#define ZBX_FLAG_LLD_TRIGGER_DISCOVERED			__UINT64_C(0x01)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION		__UINT64_C(0x02)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION		__UINT64_C(0x04)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_TYPE		__UINT64_C(0x08)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_PRIORITY		__UINT64_C(0x10)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_COMMENTS		__UINT64_C(0x20)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_URL			__UINT64_C(0x40)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE									\
		(ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION | ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION |	\
		ZBX_FLAG_LLD_TRIGGER_UPDATE_TYPE | ZBX_FLAG_LLD_TRIGGER_UPDATE_PRIORITY |		\
		ZBX_FLAG_LLD_TRIGGER_UPDATE_COMMENTS | ZBX_FLAG_LLD_TRIGGER_UPDATE_URL)
	zbx_uint64_t		flags;
}
zbx_lld_trigger_t;

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_uint64_t	index;
	zbx_uint64_t	itemid;
	zbx_uint64_t	itemid_orig;
	char		*function;
	char		*function_orig;
	char		*parameter;
	char		*parameter_orig;
#define ZBX_FLAG_LLD_FUNCTION_UNSET			__UINT64_C(0x00)
#define ZBX_FLAG_LLD_FUNCTION_DISCOVERED		__UINT64_C(0x01)
#define ZBX_FLAG_LLD_FUNCTION_UPDATE_ITEMID		__UINT64_C(0x02)
#define ZBX_FLAG_LLD_FUNCTION_UPDATE_FUNCTION		__UINT64_C(0x04)
#define ZBX_FLAG_LLD_FUNCTION_UPDATE_PARAMETER		__UINT64_C(0x08)
#define ZBX_FLAG_LLD_FUNCTION_UPDATE								\
		(ZBX_FLAG_LLD_FUNCTION_UPDATE_ITEMID | ZBX_FLAG_LLD_FUNCTION_UPDATE_FUNCTION |	\
		ZBX_FLAG_LLD_FUNCTION_UPDATE_PARAMETER)
#define ZBX_FLAG_LLD_FUNCTION_DELETE			__UINT64_C(0x10)
	zbx_uint64_t	flags;
}
zbx_lld_function_t;

typedef struct
{
	zbx_uint64_t	itemid;
	unsigned char	flags;
}
zbx_lld_item_t;

static void	lld_item_free(zbx_lld_item_t *item)
{
	zbx_free(item);
}

static void	lld_function_free(zbx_lld_function_t *function)
{
	zbx_free(function->parameter_orig);
	zbx_free(function->parameter);
	zbx_free(function->function_orig);
	zbx_free(function->function);
	zbx_free(function);
}

static void	lld_trigger_free(zbx_lld_trigger_t *trigger)
{
	zbx_vector_ptr_clean(&trigger->functions, (zbx_mem_free_func_t)lld_function_free);
	zbx_vector_ptr_destroy(&trigger->functions);
	zbx_free(trigger->comments_orig);
	zbx_free(trigger->comments);
	zbx_free(trigger->expression_orig);
	zbx_free(trigger->expression);
	zbx_free(trigger->description_orig);
	zbx_free(trigger->description);
	zbx_free(trigger);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_triggers_get                                                 *
 *                                                                            *
 * Purpose: retrieve triggers which were created by the specified trigger     *
 *          prototype                                                         *
 *                                                                            *
 * Parameters: parent_triggerid - [IN] trigger prototype identificator        *
 *             triggers         - [OUT] sorted list of triggers               *
 *                                                                            *
 ******************************************************************************/
static void	lld_triggers_get(zbx_uint64_t parent_triggerid, zbx_vector_ptr_t *triggers, unsigned char type,
		unsigned char priority, const char *url)
{
	const char		*__function_name = "lld_triggers_get";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_trigger_t	*trigger;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select t.triggerid,t.description,t.expression,t.type,t.priority,t.comments,t.url"
			" from triggers t,trigger_discovery td"
			" where t.triggerid=td.triggerid"
				" and td.parent_triggerid=" ZBX_FS_UI64,
			parent_triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		trigger = zbx_malloc(NULL, sizeof(zbx_lld_trigger_t));

		ZBX_STR2UINT64(trigger->triggerid, row[0]);
		trigger->description = zbx_strdup(NULL, row[1]);
		trigger->description_orig = NULL;
		trigger->expression = zbx_strdup(NULL, row[2]);
		trigger->expression_orig = NULL;

		trigger->flags = ZBX_FLAG_LLD_TRIGGER_UNSET;

		if ((unsigned char)atoi(row[3]) != type)
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_TYPE;

		if ((unsigned char)atoi(row[4]) != priority)
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_PRIORITY;

		trigger->comments = zbx_strdup(NULL, row[5]);
		trigger->comments_orig = NULL;

		if (0 != strcmp(row[6], url))
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_URL;

		zbx_vector_ptr_create(&trigger->functions);

		zbx_vector_ptr_append(triggers, trigger);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(triggers, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_functions_get                                                *
 *                                                                            *
 * Purpose: retrieve functions which are used by all triggers in the host of  *
 *          the trigger prototype                                             *
 *                                                                            *
 ******************************************************************************/
static void	lld_functions_get(zbx_uint64_t parent_triggerid, zbx_vector_ptr_t *functions_proto,
		zbx_vector_ptr_t *triggers)
{
	const char		*__function_name = "lld_functions_get";

	int			i;
	zbx_lld_trigger_t	*trigger;
	zbx_vector_uint64_t	triggerids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&triggerids);
	if (0 != parent_triggerid)
		zbx_vector_uint64_append(&triggerids, parent_triggerid);

	for (i = 0; i < triggers->values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		zbx_vector_uint64_append(&triggerids, trigger->triggerid);
	}

	if (0 != triggerids.values_num)
	{
		DB_RESULT		result;
		DB_ROW			row;
		zbx_lld_function_t	*function;
		zbx_uint64_t		triggerid;
		char			*sql = NULL;
		size_t			sql_alloc = 256, sql_offset = 0;
		int			index;

		zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql = zbx_malloc(sql, sql_alloc);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select functionid,triggerid,itemid,function,parameter"
				" from functions"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid",
				triggerids.values, triggerids.values_num);

		result = DBselect("%s", sql);

		zbx_free(sql);

		while (NULL != (row = DBfetch(result)))
		{
			function = zbx_malloc(NULL, sizeof(zbx_lld_function_t));

			function->index = 0;
			ZBX_STR2UINT64(function->functionid, row[0]);
			ZBX_STR2UINT64(triggerid, row[1]);
			ZBX_STR2UINT64(function->itemid, row[2]);
			function->itemid_orig = 0;
			function->function = zbx_strdup(NULL, row[3]);
			function->function_orig = NULL;
			function->parameter = zbx_strdup(NULL, row[4]);
			function->parameter_orig = NULL;
			function->flags = ZBX_FLAG_LLD_FUNCTION_UNSET;

			if (0 != parent_triggerid && triggerid == parent_triggerid)
			{
				zbx_vector_ptr_append(functions_proto, function);
			}
			else if (FAIL != (index = zbx_vector_ptr_bsearch(triggers, &triggerid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				trigger = (zbx_lld_trigger_t *)triggers->values[index];

				zbx_vector_ptr_append(&trigger->functions, function);
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
				lld_function_free(function);
			}
		}
		DBfree_result(result);

		if (NULL != functions_proto)
			zbx_vector_ptr_sort(functions_proto, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		for (i = 0; i < triggers->values_num; i++)
		{
			trigger = (zbx_lld_trigger_t *)triggers->values[i];

			zbx_vector_ptr_sort(&trigger->functions, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		}
	}

	zbx_vector_uint64_destroy(&triggerids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_items_get                                                    *
 *                                                                            *
 * Purpose: returns the list of items which are related to the trigger        *
 *          prototype                                                         *
 *                                                                            *
 * Parameters: parent_triggerid - [IN] trigger prototype identificator        *
 *             items            - [OUT] sorted list of items                  *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_get(zbx_uint64_t parent_triggerid, zbx_vector_ptr_t *items)
{
	const char	*__function_name = "lld_items_get";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_lld_item_t	*item;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select distinct i.itemid,i.flags"
			" from items i,functions f"
			" where i.itemid=f.itemid"
				" and f.triggerid=" ZBX_FS_UI64,
			parent_triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		item = zbx_malloc(NULL, sizeof(zbx_lld_item_t));

		ZBX_STR2UINT64(item->itemid, row[0]);
		ZBX_STR2UCHAR(item->flags, row[1]);

		zbx_vector_ptr_append(items, item);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(items, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_trigger_by_item                                              *
 *                                                                            *
 * Purpose: finds already existing trigger, using an item                     *
 *                                                                            *
 * Return value: upon successful completion return pointer to the trigger     *
 *                                                                            *
 ******************************************************************************/
static zbx_lld_trigger_t	*lld_trigger_by_item(zbx_vector_ptr_t *triggers, zbx_uint64_t itemid)
{
	int			t, f;
	zbx_lld_trigger_t	*trigger;
	zbx_lld_function_t	*function;

	for (t = 0; t < triggers->values_num; t++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[t];

		if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		for (f = 0; f < trigger->functions.values_num; f++)
		{
			function = (zbx_lld_function_t *)trigger->functions.values[f];

			if (function->itemid == itemid)
				return trigger;
		}
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_trigger_get                                                  *
 *                                                                            *
 * Purpose: finds already existing trigger, using an item prototype and items *
 *          already created by it                                             *
 *                                                                            *
 * Return value: upon successful completion return pointer to the trigger     *
 *                                                                            *
 ******************************************************************************/
static zbx_lld_trigger_t	*lld_trigger_get(const zbx_vector_ptr_t *functions_proto, zbx_vector_ptr_t *triggers,
		zbx_vector_ptr_t *item_links)
{
	int			i, index;
	zbx_lld_trigger_t	*trigger;
	zbx_lld_item_link_t	*item_link;

	for (i = 0; i < functions_proto->values_num; i++)
	{
		zbx_lld_function_t	*function = (zbx_lld_function_t *)functions_proto->values[i];

		index = zbx_vector_ptr_bsearch(item_links, &function->itemid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		if (FAIL == index)
			continue;

		item_link = (zbx_lld_item_link_t *)item_links->values[index];

		if (NULL != (trigger = lld_trigger_by_item(triggers, item_link->itemid)))
			return trigger;
	}

	return NULL;
}

static void	lld_expression_simplify(char **expression, zbx_vector_ptr_t *functions)
{
	const char		*__function_name = "lld_expression_simplify";

	size_t			l, r;
	int			index;
	zbx_uint64_t		functionid, function_index = 0;
	zbx_lld_function_t	*function;
	char			buffer[ZBX_MAX_UINT64_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, *expression);

	for (l = 0; '\0' != (*expression)[l]; l++)
	{
		if ('{' != (*expression)[l])
			continue;

		for (r = l + 1; '\0' != (*expression)[r] && '}' != (*expression)[r]; r++)
			;

		if ('}' != (*expression)[r])
			continue;

		/* ... > 0 | {12345} + ... */
		/*           l     r       */

		if (SUCCEED != is_uint64_n(*expression + l + 1, r - l - 1, &functionid))
			continue;

		if (FAIL != (index = zbx_vector_ptr_bsearch(functions, &functionid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			function = (zbx_lld_function_t *)functions->values[index];

			if (0 == function->index)
				function->index = ++function_index;

			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, function->index);

			r--;
			zbx_replace_string(expression, l + 1, &r, buffer);
			r++;
		}

		l = r;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() expression:'%s'", __function_name, *expression);
}

static char	*lld_expression_expand(const char *expression, zbx_vector_ptr_t *functions)
{
	const char		*__function_name = "lld_expression_expand";

	size_t			l, r;
	int			i;
	zbx_uint64_t		index;
	zbx_lld_function_t	*function;
	char			*buffer = NULL;
	size_t			buffer_alloc = 64, buffer_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, expression);

	buffer = zbx_malloc(buffer, buffer_alloc);

	*buffer = '\0';

	for (l = 0; '\0' != expression[l]; l++)
	{
		zbx_chrcpy_alloc(&buffer, &buffer_alloc, &buffer_offset, expression[l]);

		if ('{' != expression[l])
			continue;

		for (r = l + 1; '\0' != expression[r] && '}' != expression[r]; r++)
			;

		if ('}' != expression[r])
			continue;

		/* ... > 0 | {1} + ... */
		/*           l r       */

		if (SUCCEED != is_uint64_n(expression + l + 1, r - l - 1, &index))
			continue;

		for (i = 0; i < functions->values_num; i++)
		{
			function = (zbx_lld_function_t *)functions->values[i];

			if (function->index != index)
				continue;

			zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset, ZBX_FS_UI64 ":%s(%s)",
					function->itemid, function->function, function->parameter);

			break;
		}

		l = r - 1;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():'%s'", __function_name, buffer);

	return buffer;
}

static void	lld_function_make(zbx_lld_function_t *function_proto, zbx_vector_ptr_t *functions, zbx_uint64_t itemid)
{
	int			i;
	zbx_lld_function_t	*function;

	for (i = 0; i < functions->values_num; i++)
	{
		function = (zbx_lld_function_t *)functions->values[i];

		if (0 != (function->flags & ZBX_FLAG_LLD_FUNCTION_DISCOVERED))
			continue;

		if (function->index == function_proto->index)
			break;
	}

	if (i == functions->values_num)
	{
		function = zbx_malloc(NULL, sizeof(zbx_lld_function_t));

		function->index = function_proto->index;
		function->functionid = 0;
		function->itemid = itemid;
		function->itemid_orig = 0;
		function->function = zbx_strdup(NULL, function_proto->function);
		function->function_orig = NULL;
		function->parameter = zbx_strdup(NULL, function_proto->parameter);
		function->parameter_orig = NULL;
		function->flags = ZBX_FLAG_LLD_FUNCTION_DISCOVERED;

		zbx_vector_ptr_append(functions, function);
	}
	else
	{
		if (function->itemid != itemid)
		{
			function->itemid_orig = function->itemid;
			function->itemid = itemid;
			function->flags |= ZBX_FLAG_LLD_FUNCTION_UPDATE_ITEMID;
		}

		if (0 != strcmp(function->function, function_proto->function))
		{
			function->function_orig = function->function;
			function->function = zbx_strdup(NULL, function_proto->function);
			function->flags |= ZBX_FLAG_LLD_FUNCTION_UPDATE_FUNCTION;
		}

		if (0 != strcmp(function->parameter, function_proto->parameter))
		{
			function->parameter_orig = function->parameter;
			function->parameter = zbx_strdup(NULL, function_proto->parameter);
			function->flags |= ZBX_FLAG_LLD_FUNCTION_UPDATE_PARAMETER;
		}

		function->flags |= ZBX_FLAG_LLD_FUNCTION_DISCOVERED;
	}
}

static void	lld_functions_delete(zbx_vector_ptr_t *functions)
{
	int	i;

	for (i = 0; i < functions->values_num; i++)
	{
		zbx_lld_function_t	*function = (zbx_lld_function_t *)functions->values[i];

		if (0 != (function->flags & ZBX_FLAG_LLD_FUNCTION_DISCOVERED))
			continue;

		function->flags |= ZBX_FLAG_LLD_FUNCTION_DELETE;
	}
}

static int	lld_functions_make(zbx_vector_ptr_t *functions_proto, zbx_vector_ptr_t *functions,
		zbx_vector_ptr_t *items, zbx_vector_ptr_t *item_links)
{
	const char		*__function_name = "lld_functions_make";

	int			i, index, ret = FAIL;
	zbx_lld_function_t	*function_proto;
	zbx_lld_item_t		*item_proto;
	zbx_lld_item_link_t	*item_link;
	zbx_uint64_t		itemid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < functions_proto->values_num; i++)
	{
		function_proto = (zbx_lld_function_t *)functions_proto->values[i];

		index = zbx_vector_ptr_bsearch(items, &function_proto->itemid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		if (FAIL == index)
			goto out;

		item_proto = (zbx_lld_item_t *)items->values[index];

		if (0 != (item_proto->flags & ZBX_FLAG_DISCOVERY_PROTOTYPE))
		{
			index = zbx_vector_ptr_bsearch(item_links, &item_proto->itemid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

			if (FAIL == index)
				goto out;

			item_link = (zbx_lld_item_link_t *)item_links->values[index];

			itemid = item_link->itemid;
		}
		else
			itemid = item_proto->itemid;

		lld_function_make(function_proto, functions, itemid);
	}

	lld_functions_delete(functions);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_trigger_make                                                 *
 *                                                                            *
 * Purpose: create a trigger based on lld rule and add it to the list         *
 *                                                                            *
 ******************************************************************************/
static void 	lld_trigger_make(zbx_vector_ptr_t *functions_proto, zbx_vector_ptr_t *triggers, zbx_vector_ptr_t *items,
		const char *description_proto, const char *expression_proto, const char *comments_proto,
		zbx_lld_row_t *lld_row, char **error)
{
	const char		*__function_name = "lld_trigger_make";

	zbx_lld_trigger_t	*trigger = NULL;
	char			*buffer = NULL, *expression = NULL, err[64];
	struct zbx_json_parse	*jp_row = &lld_row->jp_row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	trigger = lld_trigger_get(functions_proto, triggers, &lld_row->item_links);

	expression = zbx_strdup(expression, expression_proto);
	if (SUCCEED != substitute_discovery_macros(&expression, jp_row, ZBX_MACRO_NUMERIC, err, sizeof(err)))
	{
		*error = zbx_strdcatf(*error, "Cannot %s trigger: %s.\n",
				(NULL != trigger ? "update" : "create"), err);
		goto out;
	}

	if (NULL != trigger)
	{
		buffer = zbx_strdup(buffer, description_proto);
		substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(trigger->description, buffer))
		{
			trigger->description_orig = trigger->description;
			trigger->description = buffer;
			buffer = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION;
		}

		if (0 != strcmp(trigger->expression, expression))
		{
			trigger->expression_orig = trigger->expression;
			trigger->expression = expression;
			expression = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION;
		}

		buffer = zbx_strdup(buffer, comments_proto);
		substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(trigger->comments, buffer))
		{
			trigger->comments_orig = trigger->comments;
			trigger->comments = buffer;
			buffer = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_COMMENTS;
		}
	}
	else
	{
		trigger = zbx_malloc(NULL, sizeof(zbx_lld_trigger_t));

		trigger->triggerid = 0;

		trigger->description = zbx_strdup(NULL, description_proto);
		trigger->description_orig = NULL;
		substitute_discovery_macros(&trigger->description, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(trigger->description, ZBX_WHITESPACE);

		trigger->expression = expression;
		trigger->expression_orig = NULL;
		expression = NULL;

		trigger->comments = zbx_strdup(NULL, comments_proto);
		trigger->comments_orig = NULL;
		substitute_discovery_macros(&trigger->comments, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(trigger->comments, ZBX_WHITESPACE);

		zbx_vector_ptr_create(&trigger->functions);

		trigger->flags = ZBX_FLAG_LLD_TRIGGER_UNSET;

		zbx_vector_ptr_append(triggers, trigger);
	}

	zbx_free(buffer);

	if (SUCCEED != lld_functions_make(functions_proto, &trigger->functions, items, &lld_row->item_links))
		goto out;

	trigger->flags |= ZBX_FLAG_LLD_TRIGGER_DISCOVERED;
out:
	zbx_free(expression);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	lld_triggers_make(zbx_vector_ptr_t *functions_proto, zbx_vector_ptr_t *triggers,
		zbx_vector_ptr_t *items, const char *description_proto, const char *expression_proto,
		const char *comments_proto, zbx_vector_ptr_t *lld_rows, char **error)
{
	int	i;

	for (i = 0; i < lld_rows->values_num; i++)
	{
		zbx_lld_row_t	*lld_row = (zbx_lld_row_t *)lld_rows->values[i];

		lld_trigger_make(functions_proto, triggers, items, description_proto, expression_proto, comments_proto,
				lld_row, error);
	}

	zbx_vector_ptr_sort(triggers, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_validate_trigger_field                                       *
 *                                                                            *
 ******************************************************************************/
static void	lld_validate_trigger_field(zbx_lld_trigger_t *trigger, char **field, char **field_orig,
		zbx_uint64_t flag, size_t field_len, char **error)
{
	if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
		return;

	/* only new triggers or triggers with a new data will be validated */
	if (0 != trigger->triggerid && 0 == (trigger->flags & flag))
		return;

	if (SUCCEED != zbx_is_utf8(*field))
	{
		zbx_replace_invalid_utf8(*field);
		*error = zbx_strdcatf(*error, "Cannot %s trigger: value \"%s\" has invalid UTF-8 sequence.\n",
				(0 != trigger->triggerid ? "update" : "create"), *field);
	}
	else if (zbx_strlen_utf8(*field) > field_len)
	{
		*error = zbx_strdcatf(*error, "Cannot %s trigger: value \"%s\" is too long.\n",
				(0 != trigger->triggerid ? "update" : "create"), *field);
	}
	else
		return;

	if (0 != trigger->triggerid)
		lld_field_str_rollback(field, field_orig, &trigger->flags, flag);
	else
		trigger->flags &= ~ZBX_FLAG_LLD_TRIGGER_DISCOVERED;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_trigger_changed                                              *
 *                                                                            *
 * Return value: returns SUCCEED if a trigger description or expression has   *
 *               been changed; FAIL - otherwise                               *
 *                                                                            *
 ******************************************************************************/
static int	lld_trigger_changed(zbx_lld_trigger_t *trigger)
{
	int			i;
	zbx_lld_function_t	*function;

	if (0 == trigger->triggerid)
		return SUCCEED;

	if (0 != (trigger->flags & (ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION | ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION)))
		return SUCCEED;

	for (i = 0; i < trigger->functions.values_num; i++)
	{
		function = (zbx_lld_function_t *)trigger->functions.values[i];

		if (0 == function->functionid)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			return SUCCEED;
		}

		if (0 != (function->flags & ZBX_FLAG_LLD_FUNCTION_UPDATE))
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_triggers_equal                                               *
 *                                                                            *
 * Return value: returns SUCCEED if descriptions and expressions of           *
 *               the triggers are identical; FAIL - otherwise                 *
 *                                                                            *
 ******************************************************************************/
static int	lld_triggers_equal(zbx_lld_trigger_t *trigger, zbx_lld_trigger_t *trigger_b)
{
	const char	*__function_name = "lld_triggers_equal";

	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == strcmp(trigger->description, trigger_b->description))
	{
		char	*expression, *expression_b;

		expression = lld_expression_expand(trigger->expression, &trigger->functions);
		expression_b = lld_expression_expand(trigger_b->expression, &trigger_b->functions);

		if (0 == strcmp(expression, expression_b))
			ret = SUCCEED;

		zbx_free(expression);
		zbx_free(expression_b);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_triggers_validate                                            *
 *                                                                            *
 * Parameters: triggers - [IN] sorted list of triggers                        *
 *                                                                            *
 ******************************************************************************/
static void	lld_triggers_validate(zbx_uint64_t hostid, zbx_vector_ptr_t *triggers, char **error)
{
	const char		*__function_name = "lld_triggers_validate";

	int			i, j, k;
	zbx_lld_trigger_t	*trigger;
	zbx_lld_function_t	*function;
	zbx_vector_uint64_t	triggerids;
	zbx_vector_str_t	descriptions;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_str_create(&descriptions);

	/* checking a validity of the fields */

	for (i = 0; i < triggers->values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		lld_validate_trigger_field(trigger, &trigger->description, &trigger->description_orig,
				ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION, TRIGGER_DESCRIPTION_LEN, error);
		lld_validate_trigger_field(trigger, &trigger->comments, &trigger->comments_orig,
				ZBX_FLAG_LLD_TRIGGER_UPDATE_COMMENTS, TRIGGER_COMMENTS_LEN, error);
	}

	/* checking duplicated triggers in DB */

	for (i = 0; i < triggers->values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		if (0 != trigger->triggerid)
		{
			zbx_vector_uint64_append(&triggerids, trigger->triggerid);

			if (SUCCEED != lld_trigger_changed(trigger))
				continue;
		}

		zbx_vector_str_append(&descriptions, trigger->description);
	}

	if (0 != descriptions.values_num)
	{
		char			*sql = NULL, *condition = NULL, *description_esc;
		size_t			sql_alloc = 256, sql_offset = 0, condition_alloc = 256, condition_offset = 0;
		DB_RESULT		result;
		DB_ROW			row;
		zbx_vector_ptr_t	db_triggers;
		zbx_lld_trigger_t	*db_trigger;

		zbx_vector_ptr_create(&db_triggers);

		zbx_vector_str_sort(&descriptions, ZBX_DEFAULT_STR_COMPARE_FUNC);
		zbx_vector_str_uniq(&descriptions, ZBX_DEFAULT_STR_COMPARE_FUNC);

		condition = zbx_malloc(condition, condition_alloc);	/* list of trigger descriptions */

		for (i = 0; i < descriptions.values_num; i++)
		{
			description_esc = DBdyn_escape_string(descriptions.values[i]);

			if (0 != condition_offset)
				zbx_chrcpy_alloc(&condition, &condition_alloc, &condition_offset, ',');
			zbx_chrcpy_alloc(&condition, &condition_alloc, &condition_offset, '\'');
			zbx_strcpy_alloc(&condition, &condition_alloc, &condition_offset, description_esc);
			zbx_chrcpy_alloc(&condition, &condition_alloc, &condition_offset, '\'');

			zbx_free(description_esc);
		}

		sql = zbx_malloc(sql, sql_alloc);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct t.triggerid,t.description,t.expression"
				" from triggers t,functions f,items i"
				" where t.triggerid=f.triggerid"
					" and f.itemid=i.itemid"
					" and i.hostid=" ZBX_FS_UI64
					" and t.description in (%s)",
				hostid, condition);
		if (0 != triggerids.values_num)
		{
			zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.triggerid",
					triggerids.values, triggerids.values_num);
		}

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			db_trigger = zbx_malloc(NULL, sizeof(zbx_lld_trigger_t));

			ZBX_STR2UINT64(db_trigger->triggerid, row[0]);
			db_trigger->description = zbx_strdup(NULL, row[1]);
			db_trigger->description_orig = NULL;
			db_trigger->expression = zbx_strdup(NULL, row[2]);
			db_trigger->expression_orig = NULL;
			db_trigger->comments = NULL;
			db_trigger->comments_orig = NULL;
			db_trigger->flags = ZBX_FLAG_LLD_TRIGGER_UNSET;

			zbx_vector_ptr_create(&db_trigger->functions);

			zbx_vector_ptr_append(&db_triggers, db_trigger);
		}
		DBfree_result(result);

		zbx_vector_ptr_sort(&db_triggers, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		lld_functions_get(0, NULL, &db_triggers);

		for (i = 0; i < db_triggers.values_num; i++)
		{
			db_trigger = (zbx_lld_trigger_t *)db_triggers.values[i];

			lld_expression_simplify(&db_trigger->expression, &db_trigger->functions);

			for (j = 0; j < triggers->values_num; j++)
			{
				trigger = (zbx_lld_trigger_t *)triggers->values[j];

				if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
					continue;

				if (SUCCEED != lld_triggers_equal(trigger, db_trigger))
					continue;

				*error = zbx_strdcatf(*error, "Cannot %s trigger: trigger \"%s\" already exists.\n",
						(0 != trigger->triggerid ? "update" : "create"), trigger->description);

				if (0 != trigger->triggerid)
				{
					lld_field_str_rollback(&trigger->description, &trigger->description_orig,
							&trigger->flags, ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION);

					lld_field_str_rollback(&trigger->expression, &trigger->expression_orig,
							&trigger->flags, ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION);

					for (k = 0; k < trigger->functions.values_num; k++)
					{
						function = (zbx_lld_function_t *)trigger->functions.values[k];

						if (0 != function->functionid)
						{
							lld_field_uint64_rollback(&function->itemid,
									&function->itemid_orig,
									&function->flags,
									ZBX_FLAG_LLD_FUNCTION_UPDATE_ITEMID);

							lld_field_str_rollback(&function->function,
									&function->function_orig,
									&function->flags,
									ZBX_FLAG_LLD_FUNCTION_UPDATE_FUNCTION);

							lld_field_str_rollback(&function->parameter,
									&function->parameter_orig,
									&function->flags,
									ZBX_FLAG_LLD_FUNCTION_UPDATE_PARAMETER);

							function->flags &= ~ZBX_FLAG_LLD_FUNCTION_DELETE;
						}
						else
							function->flags &= ~ZBX_FLAG_LLD_FUNCTION_DISCOVERED;
					}
				}
				else
					trigger->flags &= ~ZBX_FLAG_LLD_TRIGGER_DISCOVERED;

				break;	/* only one same trigger can be here */
			}
		}

		zbx_vector_ptr_clean(&db_triggers, (zbx_mem_free_func_t)lld_trigger_free);
		zbx_vector_ptr_destroy(&db_triggers);

		zbx_free(sql);
		zbx_free(condition);
	}

	zbx_vector_str_destroy(&descriptions);
	zbx_vector_uint64_destroy(&triggerids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_expression_create                                            *
 *                                                                            *
 * Purpose: transforms the simple trigger expression to the DB format         *
 *                                                                            *
 * Example:                                                                   *
 *                                                                            *
 *     "{1} > 5" => "{84756} > 5"                                             *
 *       ^            ^                                                       *
 *       |            functionid from the database                            *
 *       internal function index                                              *
 *                                                                            *
 ******************************************************************************/
static void	lld_expression_create(char **expression, zbx_vector_ptr_t *functions)
{
	const char		*__function_name = "lld_expression_create";

	size_t			l, r;
	int			i;
	zbx_uint64_t		function_index;
	zbx_lld_function_t	*function;
	char			buffer[ZBX_MAX_UINT64_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __function_name, *expression);

	for (l = 0; '\0' != (*expression)[l]; l++)
	{
		if ('{' != (*expression)[l])
			continue;

		for (r = l + 1; '\0' != (*expression)[r] && '}' != (*expression)[r]; r++)
			;

		if ('}' != (*expression)[r])
			continue;

		/* ... > 0 | {1} + ... */
		/*           l r       */

		if (SUCCEED != is_uint64_n(*expression + l + 1, r - l - 1, &function_index))
			continue;

		for (i = 0; i < functions->values_num; i++)
		{
			function = (zbx_lld_function_t *)functions->values[i];

			if (function->index != function_index)
				continue;

			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, function->functionid);

			r--;
			zbx_replace_string(expression, l + 1, &r, buffer);
			r++;

			break;
		}

		l = r;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() expression:'%s'", __function_name, *expression);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_triggers_save                                                *
 *                                                                            *
 * Purpose: add or update triggers in database based on discovery rule        *
 *                                                                            *
 ******************************************************************************/
static void	lld_triggers_save(zbx_uint64_t parent_triggerid, zbx_vector_ptr_t *triggers, unsigned char status,
		unsigned char type, unsigned char priority, const char *url)
{
	const char		*__function_name = "lld_triggers_save";

	int			i, j, new_triggers = 0, upd_triggers = 0, new_functions = 0;
	zbx_lld_trigger_t	*trigger;
	zbx_lld_function_t	*function;
	zbx_vector_ptr_t	upd_functions;	/* the ordered list of functions which will be updated */
	zbx_vector_uint64_t	del_functionids;
	zbx_uint64_t		triggerid = 0, triggerdiscoveryid = 0, functionid = 0;
	unsigned char		flags = ZBX_FLAG_LLD_TRIGGER_UNSET;
	char			*sql = NULL, *url_esc = NULL, *function_esc, *parameter_esc;
	size_t			sql_alloc = 8 * ZBX_KIBIBYTE, sql_offset = 0;
	zbx_db_insert_t		db_insert, db_insert_tdiscovery, db_insert_tfunctions;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&upd_functions);
	zbx_vector_uint64_create(&del_functionids);

	for (i = 0; i < triggers->values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		if (0 == trigger->triggerid)
		{
			new_triggers++;
		}
		else if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE))
		{
			upd_triggers++;
			flags |= trigger->flags;
		}

		for (j = 0; j < trigger->functions.values_num; j++)
		{
			function = (zbx_lld_function_t *)trigger->functions.values[j];

			if (0 != (function->flags & ZBX_FLAG_LLD_FUNCTION_DELETE))
			{
				zbx_vector_uint64_append(&del_functionids, function->functionid);
				continue;
			}

			if (0 == (function->flags & ZBX_FLAG_LLD_FUNCTION_DISCOVERED))
				continue;

			if (0 == function->functionid)
				new_functions++;
			else if (0 != (function->flags & ZBX_FLAG_LLD_FUNCTION_UPDATE))
				zbx_vector_ptr_append(&upd_functions, function);
		}
	}

	if (0 == new_triggers && 0 == new_functions && 0 == upd_triggers && 0 == upd_functions.values_num &&
			0 == del_functionids.values_num)
	{
		goto out;
	}

	DBbegin();

	if (0 != new_triggers)
	{
		triggerid = DBget_maxid_num("triggers", new_triggers);
		triggerdiscoveryid = DBget_maxid_num("trigger_discovery", new_triggers);

		zbx_db_insert_prepare(&db_insert, "triggers", "triggerid", "description", "expression", "priority",
				"status", "comments", "url", "type", "value", "state", "flags", NULL);

		zbx_db_insert_prepare(&db_insert_tdiscovery, "trigger_discovery", "triggerdiscoveryid", "triggerid",
				"parent_triggerid", NULL);
	}

	if (0 != new_functions)
	{
		functionid = DBget_maxid_num("functions", new_functions);

		zbx_db_insert_prepare(&db_insert_tfunctions, "functions", "functionid", "itemid", "triggerid",
				"function", "parameter", NULL);
	}

	if (0 != upd_triggers || 0 != upd_functions.values_num || 0 != del_functionids.values_num)
	{
		sql = zbx_malloc(sql, sql_alloc);
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != (flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_URL))
		url_esc = DBdyn_escape_string(url);

	for (i = 0; i < triggers->values_num; i++)
	{
		char	*description_esc, *expression_esc, *comments_esc;

		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		for (j = 0; j < trigger->functions.values_num; j++)
		{
			function = (zbx_lld_function_t *)trigger->functions.values[j];

			if (0 != (function->flags & ZBX_FLAG_LLD_FUNCTION_DELETE))
				continue;

			if (0 == (function->flags & ZBX_FLAG_LLD_FUNCTION_DISCOVERED))
				continue;

			if (0 == function->functionid)
			{
				zbx_db_insert_add_values(&db_insert_tfunctions, functionid, function->itemid,
						(0 == trigger->triggerid ? triggerid : trigger->triggerid),
						function->function, function->parameter);

				function->functionid = functionid++;
			}
		}

		if (0 == trigger->triggerid || 0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION))
			lld_expression_create(&trigger->expression, &trigger->functions);

		if (0 == trigger->triggerid)
		{
			zbx_db_insert_add_values(&db_insert, triggerid, trigger->description, trigger->expression,
					(int)priority, (int)status, trigger->comments, url, (int)type,
					(int)TRIGGER_VALUE_OK, (int)TRIGGER_STATE_NORMAL,
					(int)ZBX_FLAG_DISCOVERY_CREATED);

			zbx_db_insert_add_values(&db_insert_tdiscovery, triggerdiscoveryid, triggerid, parent_triggerid);

			trigger->triggerid = triggerid++;
			triggerdiscoveryid++;
		}
		else if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE))
		{
			const char	*d = "";

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set ");

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION))
			{
				description_esc = DBdyn_escape_string(trigger->description);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "description='%s'",
						description_esc);
				zbx_free(description_esc);
				d = ",";
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION))
			{
				expression_esc = DBdyn_escape_string(trigger->expression);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sexpression='%s'", d,
						expression_esc);
				zbx_free(expression_esc);
				d = ",";
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%stype=%d", d, (int)type);
				d = ",";
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_PRIORITY))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%spriority=%d", d, (int)priority);
				d = ",";
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_COMMENTS))
			{
				comments_esc = DBdyn_escape_string(trigger->comments);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scomments='%s'", d,
						comments_esc);
				zbx_free(comments_esc);
				d = ",";
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_URL))
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%surl='%s'", d, url_esc);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					" where triggerid=" ZBX_FS_UI64 ";\n", trigger->triggerid);
		}
	}

	zbx_free(url_esc);

	zbx_vector_ptr_sort(&upd_functions, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < upd_functions.values_num; i++)
	{
		const char	*d = "";

		function = (zbx_lld_function_t *)upd_functions.values[i];

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update functions set ");

		if (0 != (function->flags & ZBX_FLAG_LLD_FUNCTION_UPDATE_ITEMID))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "itemid=" ZBX_FS_UI64,
					function->itemid);
			d = ",";
		}

		if (0 != (function->flags & ZBX_FLAG_LLD_FUNCTION_UPDATE_FUNCTION))
		{
			function_esc = DBdyn_escape_string(function->function);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sfunction='%s'", d,
					function_esc);
			zbx_free(function_esc);
			d = ",";
		}

		if (0 != (function->flags & ZBX_FLAG_LLD_FUNCTION_UPDATE_PARAMETER))
		{
			parameter_esc = DBdyn_escape_string(function->parameter);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sparameter='%s'", d,
					parameter_esc);
			zbx_free(parameter_esc);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" where functionid=" ZBX_FS_UI64 ";\n", function->functionid);
	}

	if (0 != del_functionids.values_num)
	{
		zbx_vector_uint64_sort(&del_functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from functions where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "functionid",
				del_functionids.values, del_functionids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (0 != upd_triggers || 0 != upd_functions.values_num || 0 != del_functionids.values_num)
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);
		DBexecute("%s", sql);
		zbx_free(sql);
	}

	if (0 != new_triggers)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_db_insert_execute(&db_insert_tdiscovery);
		zbx_db_insert_clean(&db_insert_tdiscovery);
	}

	if (0 != new_functions)
	{
		zbx_db_insert_execute(&db_insert_tfunctions);
		zbx_db_insert_clean(&db_insert_tfunctions);
	}

	DBcommit();
out:
	zbx_vector_uint64_destroy(&del_functionids);
	zbx_vector_ptr_destroy(&upd_functions);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_update_triggers                                              *
 *                                                                            *
 * Purpose: add or update triggers for discovered items                       *
 *                                                                            *
 ******************************************************************************/
void	lld_update_triggers(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *lld_rows, char **error)
{
	const char		*__function_name = "lld_update_triggers";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_ptr_t	triggers;
	zbx_vector_ptr_t	functions_proto;
	zbx_vector_ptr_t	items;
	zbx_lld_trigger_t	*trigger;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&triggers);		/* list of triggers which were created or will be created or */
							/* updated by the trigger prototype */
	zbx_vector_ptr_create(&functions_proto);	/* list of functions which are used by the trigger prototype */
	zbx_vector_ptr_create(&items);			/* list of items which are related to the trigger prototype */

	result = DBselect(
			"select distinct t.triggerid,t.description,t.expression,t.status,t.type,t.priority,t.comments,"
				"t.url"
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
		const char	*description_proto, *comments_proto, *url;
		char		*expression_proto;
		unsigned char	status, type, priority;
		int		i;

		ZBX_STR2UINT64(parent_triggerid, row[0]);
		description_proto = row[1];
		expression_proto = zbx_strdup(NULL, row[2]);
		ZBX_STR2UCHAR(status, row[3]);
		ZBX_STR2UCHAR(type, row[4]);
		ZBX_STR2UCHAR(priority, row[5]);
		comments_proto = row[6];
		url = row[7];

		lld_triggers_get(parent_triggerid, &triggers, type, priority, url);
		lld_functions_get(parent_triggerid, &functions_proto, &triggers);
		lld_items_get(parent_triggerid, &items);

		/* simplifying trigger expressions */

		lld_expression_simplify(&expression_proto, &functions_proto);

		for (i = 0; i < triggers.values_num; i++)
		{
			trigger = (zbx_lld_trigger_t *)triggers.values[i];

			lld_expression_simplify(&trigger->expression, &trigger->functions);
		}

		/* making triggers */

		lld_triggers_make(&functions_proto, &triggers, &items, description_proto, expression_proto,
				comments_proto, lld_rows, error);
		lld_triggers_validate(hostid, &triggers, error);
		lld_triggers_save(parent_triggerid, &triggers, status, type, priority, url);

		/* cleaning */

		zbx_vector_ptr_clean(&items, (zbx_mem_free_func_t)lld_item_free);
		zbx_vector_ptr_clean(&functions_proto, (zbx_mem_free_func_t)lld_function_free);
		zbx_vector_ptr_clean(&triggers, (zbx_mem_free_func_t)lld_trigger_free);

		zbx_free(expression_proto);
	}
	DBfree_result(result);

	zbx_vector_ptr_destroy(&items);
	zbx_vector_ptr_destroy(&functions_proto);
	zbx_vector_ptr_destroy(&triggers);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
