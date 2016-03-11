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
	char			*expression;
	char			*comments;
	char			*url;
	unsigned char		status;
	unsigned char		type;
	unsigned char		priority;
	zbx_vector_ptr_t	functions;
	zbx_vector_ptr_t	dependencies;
}
zbx_lld_trigger_prototype_t;

typedef struct
{
	zbx_uint64_t		triggerid;
	zbx_uint64_t		parent_triggerid;
	char			*description;
	char			*description_orig;
	char			*expression;
	char			*expression_orig;
	char			*comments;
	char			*comments_orig;
	char			*url;
	char			*url_orig;
	zbx_vector_ptr_t	functions;
	zbx_vector_ptr_t	dependencies;
	zbx_vector_ptr_t	dependents;
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
	zbx_uint64_t		triggerdepid;
	zbx_uint64_t		triggerid_up;	/* generic trigger */
	zbx_lld_trigger_t	*trigger_up;	/* lld-created trigger; (null) if trigger depends on generic trigger */
#define ZBX_FLAG_LLD_DEPENDENCY_UNSET			__UINT64_C(0x00)
#define ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED		__UINT64_C(0x01)
#define ZBX_FLAG_LLD_DEPENDENCY_DELETE			__UINT64_C(0x02)
	zbx_uint64_t		flags;
}
zbx_lld_dependency_t;

typedef struct
{
	zbx_uint64_t		parent_triggerid;
	zbx_uint64_t		itemid;
	zbx_lld_trigger_t	*trigger;
}
zbx_lld_item_trigger_t;

typedef struct
{
	zbx_uint64_t	itemid;
	unsigned char	flags;
}
zbx_lld_item_t;

/* a reference to trigger which could be either existing trigger in database or */
/* a just discovered trigger stored in memory                                   */
typedef struct
{
	/* trigger id, 0 for newly discovered triggers */
	zbx_uint64_t		triggerid;

	/* trigger data, NULL for non-discovered triggers */
	zbx_lld_trigger_t	*trigger;

	/* flags to mark trigger dependencies during trigger dependency validation */
#define ZBX_LLD_TRIGGER_DEPENDENCY_NORMAL	0
#define ZBX_LLD_TRIGGER_DEPENDENCY_NEW		1
#define ZBX_LLD_TRIGGER_DEPENDENCY_DELETE	2

	/* flags used to mark dependencies when trigger reference is use to store dependency links */
	int			flags;
}
zbx_lld_trigger_ref_t;

/* a trigger node used to build trigger tree for dependency validation */
typedef struct
{
	/* trigger reference */
	zbx_lld_trigger_ref_t	trigger_ref;

	/* the current iteration number, used during dependency validation */
	int			iter_num;

	/* the number of dependents */
	int			parents;

	/* trigger dependency list */
	zbx_vector_ptr_t	dependencies;
}
zbx_lld_trigger_node_t;

/* a structure to keep information about current iteration during trigger dependencies validation */
typedef struct
{
	/* iteration number */
	int			iter_num;

	/* the dependency (from->to) that should be removed in the case of recursive loop */
	zbx_lld_trigger_ref_t	*ref_from;
	zbx_lld_trigger_ref_t	*ref_to;
}
zbx_lld_trigger_node_iter_t;


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

static void	lld_trigger_prototype_free(zbx_lld_trigger_prototype_t *trigger_prototype)
{
	zbx_vector_ptr_clear_ext(&trigger_prototype->dependencies, zbx_ptr_free);
	zbx_vector_ptr_destroy(&trigger_prototype->dependencies);
	zbx_vector_ptr_clear_ext(&trigger_prototype->functions, (zbx_mem_free_func_t)lld_function_free);
	zbx_vector_ptr_destroy(&trigger_prototype->functions);
	zbx_free(trigger_prototype->url);
	zbx_free(trigger_prototype->comments);
	zbx_free(trigger_prototype->expression);
	zbx_free(trigger_prototype->description);
	zbx_free(trigger_prototype);
}

static void	lld_trigger_free(zbx_lld_trigger_t *trigger)
{
	zbx_vector_ptr_destroy(&trigger->dependents);
	zbx_vector_ptr_clear_ext(&trigger->dependencies, zbx_ptr_free);
	zbx_vector_ptr_destroy(&trigger->dependencies);
	zbx_vector_ptr_clear_ext(&trigger->functions, (zbx_clean_func_t)lld_function_free);
	zbx_vector_ptr_destroy(&trigger->functions);
	zbx_free(trigger->url_orig);
	zbx_free(trigger->url);
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
 * Function: lld_trigger_prototoypes_get                                      *
 *                                                                            *
 * Purpose: retrieve trigger prototypes which are inherited from the          *
 *          discovery rule                                                    *
 *                                                                            *
 * Parameters: lld_ruleid         - [IN] discovery rule id                    *
 *             trigger_prototypes - [OUT] sorted list of trigger prototypes   *
 *                                                                            *
 ******************************************************************************/
static void	lld_trigger_prototoypes_get(zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *trigger_prototypes)
{
	DB_RESULT			result;
	DB_ROW				row;
	zbx_lld_trigger_prototype_t	*trigger_prototype;

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
		trigger_prototype = zbx_malloc(NULL, sizeof(zbx_lld_trigger_prototype_t));

		ZBX_STR2UINT64(trigger_prototype->triggerid, row[0]);
		trigger_prototype->description = zbx_strdup(NULL, row[1]);
		trigger_prototype->expression = zbx_strdup(NULL, row[2]);
		ZBX_STR2UCHAR(trigger_prototype->status, row[3]);
		ZBX_STR2UCHAR(trigger_prototype->type, row[4]);
		ZBX_STR2UCHAR(trigger_prototype->priority, row[5]);
		trigger_prototype->comments = zbx_strdup(NULL, row[6]);
		trigger_prototype->url = zbx_strdup(NULL, row[7]);

		zbx_vector_ptr_create(&trigger_prototype->functions);
		zbx_vector_ptr_create(&trigger_prototype->dependencies);

		zbx_vector_ptr_append(trigger_prototypes, trigger_prototype);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(trigger_prototypes, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_triggers_get                                                 *
 *                                                                            *
 * Purpose: retrieve triggers which were created by the specified trigger     *
 *          prototypes                                                        *
 *                                                                            *
 * Parameters: trigger_prototypes - [IN] sorted list of trigger prototypes    *
 *             triggers           - [OUT] sorted list of triggers             *
 *                                                                            *
 ******************************************************************************/
static void	lld_triggers_get(zbx_vector_ptr_t *trigger_prototypes, zbx_vector_ptr_t *triggers)
{
	const char		*__function_name = "lld_triggers_get";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	parent_triggerids;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&parent_triggerids);
	zbx_vector_uint64_reserve(&parent_triggerids, trigger_prototypes->values_num);

	for (i = 0; i < trigger_prototypes->values_num; i++)
	{
		zbx_lld_trigger_prototype_t	*trigger_prototype;

		trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[i];

		zbx_vector_uint64_append(&parent_triggerids, trigger_prototype->triggerid);
	}

	sql = zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select td.parent_triggerid,t.triggerid,t.description,t.expression,t.type,t.priority,"
				"t.comments,t.url"
			" from triggers t,trigger_discovery td"
			" where t.triggerid=td.triggerid"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "td.parent_triggerid",
			parent_triggerids.values, parent_triggerids.values_num);

	zbx_vector_uint64_destroy(&parent_triggerids);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t			parent_triggerid;
		zbx_lld_trigger_prototype_t	*trigger_prototype;
		zbx_lld_trigger_t		*trigger;
		int				index;

		ZBX_STR2UINT64(parent_triggerid, row[0]);

		if (FAIL == (index = zbx_vector_ptr_bsearch(trigger_prototypes, &parent_triggerid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[index];

		trigger = zbx_malloc(NULL, sizeof(zbx_lld_trigger_t));

		ZBX_STR2UINT64(trigger->triggerid, row[1]);
		trigger->parent_triggerid = parent_triggerid;
		trigger->description = zbx_strdup(NULL, row[2]);
		trigger->description_orig = NULL;
		trigger->expression = zbx_strdup(NULL, row[3]);
		trigger->expression_orig = NULL;

		trigger->flags = ZBX_FLAG_LLD_TRIGGER_UNSET;

		if ((unsigned char)atoi(row[4]) != trigger_prototype->type)
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_TYPE;

		if ((unsigned char)atoi(row[5]) != trigger_prototype->priority)
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_PRIORITY;

		trigger->comments = zbx_strdup(NULL, row[6]);
		trigger->comments_orig = NULL;
		trigger->url = zbx_strdup(NULL, row[7]);
		trigger->url_orig = NULL;

		zbx_vector_ptr_create(&trigger->functions);
		zbx_vector_ptr_create(&trigger->dependencies);
		zbx_vector_ptr_create(&trigger->dependents);

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
static void	lld_functions_get(zbx_vector_ptr_t *trigger_prototypes, zbx_vector_ptr_t *triggers)
{
	const char			*__function_name = "lld_functions_get";

	int				i;
	zbx_lld_trigger_prototype_t	*trigger_prototype;
	zbx_lld_trigger_t		*trigger;
	zbx_vector_uint64_t		triggerids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&triggerids);

	if (NULL != trigger_prototypes)
	{
		for (i = 0; i < trigger_prototypes->values_num; i++)
		{
			trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[i];

			zbx_vector_uint64_append(&triggerids, trigger_prototype->triggerid);
		}
	}

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

			if (NULL != trigger_prototypes && FAIL != (index = zbx_vector_ptr_bsearch(trigger_prototypes,
					&triggerid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[index];

				zbx_vector_ptr_append(&trigger_prototype->functions, function);
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

		if (NULL != trigger_prototypes)
		{
			for (i = 0; i < trigger_prototypes->values_num; i++)
			{
				trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[i];

				zbx_vector_ptr_sort(&trigger_prototype->functions, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
			}
		}

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
 * Function: lld_dependencies_get                                             *
 *                                                                            *
 * Purpose: retrieve trigger dependencies                                     *
 *                                                                            *
 ******************************************************************************/
static void	lld_dependencies_get(zbx_vector_ptr_t *trigger_prototypes, zbx_vector_ptr_t *triggers)
{
	const char			*__function_name = "lld_dependencies_get";

	DB_RESULT			result;
	DB_ROW				row;
	zbx_lld_trigger_prototype_t	*trigger_prototype;
	zbx_lld_trigger_t		*trigger;
	zbx_lld_dependency_t		*dependency;
	zbx_vector_uint64_t		triggerids;
	zbx_uint64_t			triggerid_down;
	char				*sql = NULL;
	size_t				sql_alloc = 256, sql_offset = 0;
	int				i, index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&triggerids);

	for (i = 0; i < trigger_prototypes->values_num; i++)
	{
		trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[i];

		zbx_vector_uint64_append(&triggerids, trigger_prototype->triggerid);
	}

	for (i = 0; i < triggers->values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		zbx_vector_uint64_append(&triggerids, trigger->triggerid);
	}

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select triggerdepid,triggerid_down,triggerid_up"
			" from trigger_depends"
			" where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid_down",
			triggerids.values, triggerids.values_num);

	zbx_vector_uint64_destroy(&triggerids);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		dependency = zbx_malloc(NULL, sizeof(zbx_lld_dependency_t));

		ZBX_STR2UINT64(dependency->triggerdepid, row[0]);
		ZBX_STR2UINT64(triggerid_down, row[1]);
		ZBX_STR2UINT64(dependency->triggerid_up, row[2]);
		dependency->trigger_up = NULL;
		dependency->flags = ZBX_FLAG_LLD_DEPENDENCY_UNSET;

		if (FAIL != (index = zbx_vector_ptr_bsearch(trigger_prototypes, &triggerid_down,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[index];

			zbx_vector_ptr_append(&trigger_prototype->dependencies, dependency);
		}
		else if (FAIL != (index = zbx_vector_ptr_bsearch(triggers, &triggerid_down,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			trigger = (zbx_lld_trigger_t *)triggers->values[index];

			zbx_vector_ptr_append(&trigger->dependencies, dependency);
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_ptr_free(dependency);
		}
	}
	DBfree_result(result);

	for (i = 0; i < trigger_prototypes->values_num; i++)
	{
		trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[i];

		zbx_vector_ptr_sort(&trigger_prototype->dependencies, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}

	for (i = 0; i < triggers->values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		zbx_vector_ptr_sort(&trigger->dependencies, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_items_get                                                    *
 *                                                                            *
 * Purpose: returns the list of items which are related to the trigger        *
 *          prototypes                                                        *
 *                                                                            *
 * Parameters: trigger_prototypes - [IN] a vector of trigger prototypes       *
 *             items              - [OUT] sorted list of items                *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_get(zbx_vector_ptr_t *trigger_prototypes, zbx_vector_ptr_t *items)
{
	const char		*__function_name = "lld_items_get";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_item_t		*item;
	zbx_vector_uint64_t	parent_triggerids;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&parent_triggerids);
	zbx_vector_uint64_reserve(&parent_triggerids, trigger_prototypes->values_num);

	for (i = 0; i < trigger_prototypes->values_num; i++)
	{
		zbx_lld_trigger_prototype_t	*trigger_prototype;

		trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[i];

		zbx_vector_uint64_append(&parent_triggerids, trigger_prototype->triggerid);
	}
	sql = zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct i.itemid,i.flags"
			" from items i,functions f"
			" where i.itemid=f.itemid"
				" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "f.triggerid",
			parent_triggerids.values, parent_triggerids.values_num);

	zbx_vector_uint64_destroy(&parent_triggerids);

	result = DBselect("%s", sql);

	zbx_free(sql);

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
 * Function: lld_trigger_get                                                  *
 *                                                                            *
 * Purpose: finds already existing trigger, using an item prototype and items *
 *          already created by it                                             *
 *                                                                            *
 * Return value: upon successful completion return pointer to the trigger     *
 *                                                                            *
 ******************************************************************************/
static zbx_lld_trigger_t	*lld_trigger_get(zbx_uint64_t parent_triggerid, zbx_hashset_t *items_triggers,
		zbx_vector_ptr_t *item_links)
{
	int	i;

	for (i = 0; i < item_links->values_num; i++)
	{
		zbx_lld_item_trigger_t	*item_trigger, item_trigger_local;
		zbx_lld_item_link_t	*item_link = (zbx_lld_item_link_t *)item_links->values[i];

		item_trigger_local.parent_triggerid = parent_triggerid;
		item_trigger_local.itemid = item_link->itemid;

		if (NULL != (item_trigger = zbx_hashset_search(items_triggers, &item_trigger_local)))
			return item_trigger->trigger;
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

		if ('$' == (*expression)[l + 1])
		{
			int	macro_r, context_l, context_r;

			if (SUCCEED == zbx_user_macro_parse(*expression + l, &macro_r, &context_l, &context_r))
				l += macro_r;
			else
				l++;

			continue;
		}

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

		if ('$' == expression[l + 1])
		{
			int	macro_r, context_l, context_r;

			if (SUCCEED == zbx_user_macro_parse(expression + l, &macro_r, &context_l, &context_r))
				l += macro_r;
			else
				l++;

			continue;
		}

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
	zbx_lld_function_t	*function = NULL;

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
static void 	lld_trigger_make(zbx_lld_trigger_prototype_t *trigger_prototype, zbx_vector_ptr_t *triggers,
		zbx_vector_ptr_t *items, zbx_hashset_t *items_triggers, zbx_lld_row_t *lld_row, char **error)
{
	const char		*__function_name = "lld_trigger_make";

	zbx_lld_trigger_t	*trigger;
	char			*buffer = NULL, *expression = NULL, err[64];
	struct zbx_json_parse	*jp_row = &lld_row->jp_row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	trigger = lld_trigger_get(trigger_prototype->triggerid, items_triggers, &lld_row->item_links);

	expression = zbx_strdup(expression, trigger_prototype->expression);
	if (SUCCEED != substitute_discovery_macros(&expression, jp_row, ZBX_MACRO_NUMERIC, err, sizeof(err)))
	{
		*error = zbx_strdcatf(*error, "Cannot %s trigger: %s.\n", (NULL != trigger ? "update" : "create"), err);
		goto out;
	}

	if (NULL != trigger)
	{
		buffer = zbx_strdup(buffer, trigger_prototype->description);
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

		buffer = zbx_strdup(buffer, trigger_prototype->comments);
		substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(trigger->comments, buffer))
		{
			trigger->comments_orig = trigger->comments;
			trigger->comments = buffer;
			buffer = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_COMMENTS;
		}

		buffer = zbx_strdup(buffer, trigger_prototype->url);
		substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(trigger->url, buffer))
		{
			trigger->url_orig = trigger->url;
			trigger->url = buffer;
			buffer = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_URL;
		}
	}
	else
	{
		trigger = zbx_malloc(NULL, sizeof(zbx_lld_trigger_t));

		trigger->triggerid = 0;
		trigger->parent_triggerid = trigger_prototype->triggerid;

		trigger->description = zbx_strdup(NULL, trigger_prototype->description);
		trigger->description_orig = NULL;
		substitute_discovery_macros(&trigger->description, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(trigger->description, ZBX_WHITESPACE);

		trigger->expression = expression;
		trigger->expression_orig = NULL;
		expression = NULL;

		trigger->comments = zbx_strdup(NULL, trigger_prototype->comments);
		trigger->comments_orig = NULL;
		substitute_discovery_macros(&trigger->comments, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(trigger->comments, ZBX_WHITESPACE);

		trigger->url = zbx_strdup(NULL, trigger_prototype->url);
		trigger->url_orig = NULL;
		substitute_discovery_macros(&trigger->url, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(trigger->url, ZBX_WHITESPACE);

		zbx_vector_ptr_create(&trigger->functions);
		zbx_vector_ptr_create(&trigger->dependencies);
		zbx_vector_ptr_create(&trigger->dependents);

		trigger->flags = ZBX_FLAG_LLD_TRIGGER_UNSET;

		zbx_vector_ptr_append(triggers, trigger);
	}

	zbx_free(buffer);

	if (SUCCEED != lld_functions_make(&trigger_prototype->functions, &trigger->functions, items,
			&lld_row->item_links))
		goto out;

	trigger->flags |= ZBX_FLAG_LLD_TRIGGER_DISCOVERED;
out:
	zbx_free(expression);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static zbx_hash_t	items_triggers_hash_func(const void *data)
{
	const zbx_lld_item_trigger_t	*item_trigger = data;
	zbx_hash_t			hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&item_trigger->parent_triggerid);
	hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&item_trigger->itemid, sizeof(zbx_uint64_t), hash);

	return hash;
}

static int	items_triggers_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_item_trigger_t	*item_trigger1 = d1, *item_trigger2 = d2;

	ZBX_RETURN_IF_NOT_EQUAL(item_trigger1->parent_triggerid, item_trigger2->parent_triggerid);
	ZBX_RETURN_IF_NOT_EQUAL(item_trigger1->itemid, item_trigger2->itemid);

	return 0;
}

static void	lld_triggers_make(zbx_vector_ptr_t *trigger_prototypes, zbx_vector_ptr_t *triggers,
		zbx_vector_ptr_t *items, zbx_vector_ptr_t *lld_rows, char **error)
{
	zbx_lld_trigger_prototype_t	*trigger_prototype;
	int				i, j;
	zbx_hashset_t			items_triggers;
	zbx_lld_trigger_t		*trigger;
	zbx_lld_function_t		*function;
	zbx_lld_item_trigger_t		item_trigger;

	/* used for fast search of trigger by item prototype */
	zbx_hashset_create(&items_triggers, 512, items_triggers_hash_func, items_triggers_compare_func);

	for (i = 0; i < triggers->values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		for (j = 0; j < trigger->functions.values_num; j++)
		{
			function = (zbx_lld_function_t *)trigger->functions.values[j];

			item_trigger.parent_triggerid = trigger->parent_triggerid;
			item_trigger.itemid = function->itemid;
			item_trigger.trigger = trigger;
			zbx_hashset_insert(&items_triggers, &item_trigger, sizeof(item_trigger));
		}
	}

	for (i = 0; i < trigger_prototypes->values_num; i++)
	{
		trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[i];

		for (j = 0; j < lld_rows->values_num; j++)
		{
			zbx_lld_row_t	*lld_row = (zbx_lld_row_t *)lld_rows->values[j];

			lld_trigger_make(trigger_prototype, triggers, items, &items_triggers, lld_row, error);
		}
	}

	zbx_hashset_destroy(&items_triggers);

	zbx_vector_ptr_sort(triggers, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_trigger_dependency_make                                      *
 *                                                                            *
 * Purpose: create a trigger dependencies                                     *
 *                                                                            *
 ******************************************************************************/
static void 	lld_trigger_dependency_make(zbx_lld_trigger_prototype_t *trigger_prototype,
		zbx_vector_ptr_t *trigger_prototypes, zbx_vector_ptr_t *triggers, zbx_vector_ptr_t *items,
		zbx_hashset_t *items_triggers, zbx_lld_row_t *lld_row, char **error)
{
	const char			*__function_name = "lld_trigger_dependency_make";

	zbx_lld_trigger_t		*trigger, *dep_trigger;
	zbx_lld_trigger_prototype_t	*dep_trigger_prototype;
	zbx_lld_dependency_t		*dependency;
	zbx_uint64_t			triggerid_up;
	int				i, j, index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == (trigger = lld_trigger_get(trigger_prototype->triggerid, items_triggers, &lld_row->item_links)))
		goto out;

	for (i = 0; i < trigger_prototype->dependencies.values_num; i++)
	{
		triggerid_up = ((zbx_lld_dependency_t *)trigger_prototype->dependencies.values[i])->triggerid_up;

		index = zbx_vector_ptr_bsearch(trigger_prototypes, &triggerid_up, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		if (FAIL != index)
		{
			/* creating trigger dependency based on trigger prototype */

			dep_trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[index];

			dep_trigger = lld_trigger_get(dep_trigger_prototype->triggerid, items_triggers,
					&lld_row->item_links);

			if (NULL != dep_trigger)
			{
				if (0 == dep_trigger->triggerid)
				{
					dependency = zbx_malloc(NULL, sizeof(zbx_lld_dependency_t));

					dependency->triggerdepid = 0;
					dependency->triggerid_up = 0;

					zbx_vector_ptr_append(&trigger->dependencies, dependency);
				}
				else
				{
					for (j = 0; j < trigger->dependencies.values_num; j++)
					{
						dependency = (zbx_lld_dependency_t *)trigger->dependencies.values[j];

						if (0 != (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED))
							continue;

						if (dependency->triggerid_up == dep_trigger->triggerid)
							break;
					}

					if (j == trigger->dependencies.values_num)
					{
						dependency = zbx_malloc(NULL, sizeof(zbx_lld_dependency_t));

						dependency->triggerdepid = 0;
						dependency->triggerid_up = dep_trigger->triggerid;

						zbx_vector_ptr_append(&trigger->dependencies, dependency);
					}
				}

				zbx_vector_ptr_append(&dep_trigger->dependents, trigger);

				dependency->trigger_up = dep_trigger;
				dependency->flags = ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED;
			}
			else
			{
				*error = zbx_strdcatf(*error, "Cannot create dependency on trigger \"%s\".\n",
						trigger->description);
			}
		}
		else
		{
			/* creating trigger dependency based on generic trigger */

			for (j = 0; j < trigger->dependencies.values_num; j++)
			{
				dependency = (zbx_lld_dependency_t *)trigger->dependencies.values[j];

				if (0 != (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED))
					continue;

				if (dependency->triggerid_up == triggerid_up)
					break;
			}

			if (j == trigger->dependencies.values_num)
			{
				dependency = zbx_malloc(NULL, sizeof(zbx_lld_dependency_t));

				dependency->triggerdepid = 0;
				dependency->triggerid_up = triggerid_up;
				dependency->trigger_up = NULL;

				zbx_vector_ptr_append(&trigger->dependencies, dependency);
			}

			dependency->flags = ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED;
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	lld_trigger_dependencies_make(zbx_vector_ptr_t *trigger_prototypes, zbx_vector_ptr_t *triggers,
		zbx_vector_ptr_t *items, zbx_vector_ptr_t *lld_rows, char **error)
{
	zbx_lld_trigger_prototype_t	*trigger_prototype;
	int				i, j;
	zbx_hashset_t			items_triggers;
	zbx_lld_trigger_t		*trigger;
	zbx_lld_function_t		*function;
	zbx_lld_item_trigger_t		item_trigger;
	zbx_lld_dependency_t		*dependency;

	for (i = 0; i < trigger_prototypes->values_num; i++)
	{
		trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[i];

		if (0 != trigger_prototype->dependencies.values_num)
			break;
	}

	/* all trigger prototypes have no dependencies */
	if (i == trigger_prototypes->values_num)
		return;

	/* used for fast search of trigger by item prototype */
	zbx_hashset_create(&items_triggers, 512, items_triggers_hash_func, items_triggers_compare_func);

	for (i = 0; i < triggers->values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		for (j = 0; j < trigger->functions.values_num; j++)
		{
			function = (zbx_lld_function_t *)trigger->functions.values[j];

			item_trigger.parent_triggerid = trigger->parent_triggerid;
			item_trigger.itemid = function->itemid;
			item_trigger.trigger = trigger;
			zbx_hashset_insert(&items_triggers, &item_trigger, sizeof(item_trigger));
		}
	}

	for (i = 0; i < trigger_prototypes->values_num; i++)
	{
		trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[i];

		for (j = 0; j < lld_rows->values_num; j++)
		{
			zbx_lld_row_t	*lld_row = (zbx_lld_row_t *)lld_rows->values[j];

			lld_trigger_dependency_make(trigger_prototype, trigger_prototypes, triggers, items,
					&items_triggers, lld_row, error);
		}
	}

	/* marking dependencies which will be deleted */
	for (i = 0; i < triggers->values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		for (j = 0; j < trigger->dependencies.values_num; j++)
		{
			dependency = (zbx_lld_dependency_t *)trigger->dependencies.values[j];

			if (0 == (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED))
				dependency->flags = ZBX_FLAG_LLD_DEPENDENCY_DELETE;
		}
	}

	zbx_hashset_destroy(&items_triggers);

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

	/* only new triggers or triggers with changed data will be validated */
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
		lld_validate_trigger_field(trigger, &trigger->url, &trigger->url_orig,
				ZBX_FLAG_LLD_TRIGGER_UPDATE_URL, TRIGGER_URL_LEN, error);
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
		char			*sql = NULL;
		size_t			sql_alloc = 256, sql_offset = 0;
		DB_RESULT		result;
		DB_ROW			row;
		zbx_vector_ptr_t	db_triggers;
		zbx_lld_trigger_t	*db_trigger;

		zbx_vector_ptr_create(&db_triggers);

		zbx_vector_str_sort(&descriptions, ZBX_DEFAULT_STR_COMPARE_FUNC);
		zbx_vector_str_uniq(&descriptions, ZBX_DEFAULT_STR_COMPARE_FUNC);

		sql = zbx_malloc(sql, sql_alloc);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select distinct t.triggerid,t.description,t.expression"
				" from triggers t,functions f,items i"
				" where t.triggerid=f.triggerid"
					" and f.itemid=i.itemid"
					" and i.hostid=" ZBX_FS_UI64
					" and",
				hostid);
		DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.description",
				(const char **)descriptions.values, descriptions.values_num);

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
			db_trigger->url = NULL;
			db_trigger->url_orig = NULL;
			db_trigger->flags = ZBX_FLAG_LLD_TRIGGER_UNSET;

			zbx_vector_ptr_create(&db_trigger->functions);
			zbx_vector_ptr_create(&db_trigger->dependencies);
			zbx_vector_ptr_create(&db_trigger->dependents);

			zbx_vector_ptr_append(&db_triggers, db_trigger);
		}
		DBfree_result(result);

		zbx_vector_ptr_sort(&db_triggers, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		lld_functions_get(NULL, &db_triggers);

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

		zbx_vector_ptr_clear_ext(&db_triggers, (zbx_clean_func_t)lld_trigger_free);
		zbx_vector_ptr_destroy(&db_triggers);

		zbx_free(sql);
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

		if ('$' == (*expression)[l + 1])
		{
			int	macro_r, context_l, context_r;

			if (SUCCEED == zbx_user_macro_parse(*expression + l, &macro_r, &context_l, &context_r))
				l += macro_r;
			else
				l++;

			continue;
		}

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
static void	lld_triggers_save(zbx_uint64_t hostid, zbx_vector_ptr_t *trigger_prototypes, zbx_vector_ptr_t *triggers)
{
	const char			*__function_name = "lld_triggers_save";

	int				i, j, new_triggers = 0, upd_triggers = 0, new_functions = 0,
					new_dependencies = 0;
	zbx_lld_trigger_prototype_t	*trigger_prototype;
	zbx_lld_trigger_t		*trigger;
	zbx_lld_function_t		*function;
	zbx_lld_dependency_t		*dependency;
	zbx_vector_ptr_t		upd_functions;	/* the ordered list of functions which will be updated */
	zbx_vector_uint64_t		del_functionids, del_triggerdepids;
	zbx_uint64_t			triggerid = 0, functionid = 0, triggerdepid = 0, triggerid_up;
	char				*sql = NULL, *function_esc, *parameter_esc;
	size_t				sql_alloc = 8 * ZBX_KIBIBYTE, sql_offset = 0;
	zbx_db_insert_t			db_insert, db_insert_tdiscovery, db_insert_tfunctions, db_insert_tdepends;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&upd_functions);
	zbx_vector_uint64_create(&del_functionids);
	zbx_vector_uint64_create(&del_triggerdepids);

	for (i = 0; i < triggers->values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		if (0 == trigger->triggerid)
			new_triggers++;
		else if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE))
			upd_triggers++;

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

		for (j = 0; j < trigger->dependencies.values_num; j++)
		{
			dependency = (zbx_lld_dependency_t *)trigger->dependencies.values[j];

			if (0 != (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DELETE))
			{
				zbx_vector_uint64_append(&del_triggerdepids, dependency->triggerdepid);
				continue;
			}

			if (0 == (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED))
				continue;

			if (0 == dependency->triggerdepid)
				new_dependencies++;
		}
	}

	if (0 == new_triggers && 0 == new_functions && 0 == new_dependencies && 0 == upd_triggers &&
			0 == upd_functions.values_num && 0 == del_functionids.values_num &&
			0 == del_triggerdepids.values_num)
	{
		goto out;
	}

	DBbegin();

	if (SUCCEED != DBlock_hostid(hostid))
	{
		/* the host was removed while processing lld rule */
		DBrollback();
		goto out;
	}

	if (0 != new_triggers)
	{
		triggerid = DBget_maxid_num("triggers", new_triggers);

		zbx_db_insert_prepare(&db_insert, "triggers", "triggerid", "description", "expression", "priority",
				"status", "comments", "url", "type", "value", "state", "flags", NULL);

		zbx_db_insert_prepare(&db_insert_tdiscovery, "trigger_discovery", "triggerid", "parent_triggerid",
				NULL);
	}

	if (0 != new_functions)
	{
		functionid = DBget_maxid_num("functions", new_functions);

		zbx_db_insert_prepare(&db_insert_tfunctions, "functions", "functionid", "itemid", "triggerid",
				"function", "parameter", NULL);
	}

	if (0 != new_dependencies)
	{
		triggerdepid = DBget_maxid_num("trigger_depends", new_dependencies);

		zbx_db_insert_prepare(&db_insert_tdepends, "trigger_depends", "triggerdepid", "triggerid_down",
				"triggerid_up", NULL);
	}

	if (0 != upd_triggers || 0 != upd_functions.values_num || 0 != del_functionids.values_num ||
			0 != del_triggerdepids.values_num)
	{
		sql = zbx_malloc(sql, sql_alloc);
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	}

	for (i = 0; i < triggers->values_num; i++)
	{
		char	*description_esc, *expression_esc, *comments_esc, *url_esc;
		int	index;

		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		index = zbx_vector_ptr_bsearch(trigger_prototypes, &trigger->parent_triggerid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes->values[index];

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
					(int)trigger_prototype->priority, (int)trigger_prototype->status,
					trigger->comments, trigger->url, (int)trigger_prototype->type,
					(int)TRIGGER_VALUE_OK, (int)TRIGGER_STATE_NORMAL,
					(int)ZBX_FLAG_DISCOVERY_CREATED);

			zbx_db_insert_add_values(&db_insert_tdiscovery, triggerid, trigger->parent_triggerid);

			trigger->triggerid = triggerid++;
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
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%stype=%d", d,
						(int)trigger_prototype->type);
				d = ",";
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_PRIORITY))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%spriority=%d", d,
						(int)trigger_prototype->priority);
				d = ",";
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_COMMENTS))
			{
				comments_esc = DBdyn_escape_string(trigger->comments);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scomments='%s'", d, comments_esc);
				zbx_free(comments_esc);
				d = ",";
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_URL))
			{
				url_esc = DBdyn_escape_string(trigger->url);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%surl='%s'", d, url_esc);
				zbx_free(url_esc);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					" where triggerid=" ZBX_FS_UI64 ";\n", trigger->triggerid);
		}
	}

	for (i = 0; i < triggers->values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		for (j = 0; j < trigger->dependencies.values_num; j++)
		{
			dependency = (zbx_lld_dependency_t *)trigger->dependencies.values[j];

			if (0 != (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DELETE))
				continue;

			if (0 == (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED))
				continue;

			if (0 == dependency->triggerdepid)
			{
				triggerid_up = (NULL == dependency->trigger_up ? dependency->triggerid_up :
						dependency->trigger_up->triggerid);

				zbx_db_insert_add_values(&db_insert_tdepends, triggerdepid, trigger->triggerid,
						triggerid_up);

				dependency->triggerdepid = triggerdepid++;
			}
		}
	}

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

	if (0 != del_triggerdepids.values_num)
	{
		zbx_vector_uint64_sort(&del_triggerdepids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from trigger_depends where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerdepid",
				del_triggerdepids.values, del_triggerdepids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (0 != upd_triggers || 0 != upd_functions.values_num || 0 != del_functionids.values_num ||
			0 != del_triggerdepids.values_num)
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

	if (0 != new_dependencies)
	{
		zbx_db_insert_execute(&db_insert_tdepends);
		zbx_db_insert_clean(&db_insert_tdepends);
	}

	DBcommit();
out:
	zbx_vector_uint64_destroy(&del_triggerdepids);
	zbx_vector_uint64_destroy(&del_functionids);
	zbx_vector_ptr_destroy(&upd_functions);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/* hash/comparison functions to support cache/vector lookups by trigger reference */
zbx_hash_t	zbx_lld_trigger_ref_hash_func(const void *data)
{
	zbx_hash_t			hash;
	const zbx_lld_trigger_node_t	*trigger_node = (const zbx_lld_trigger_node_t *)data;
	void				*ptr = NULL;

	hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&trigger_node->trigger_ref.triggerid,
			sizeof(trigger_node->trigger_ref.triggerid), ZBX_DEFAULT_HASH_SEED);

	if (0 == trigger_node->trigger_ref.triggerid)
		ptr = trigger_node->trigger_ref.trigger;

	return ZBX_DEFAULT_PTR_HASH_ALGO(&ptr, sizeof(trigger_node->trigger_ref.trigger), hash);
}

int	zbx_lld_trigger_ref_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_trigger_node_t	*n1 = (const zbx_lld_trigger_node_t *)d1;
	const zbx_lld_trigger_node_t	*n2 = (const zbx_lld_trigger_node_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(n1->trigger_ref.triggerid, n2->trigger_ref.triggerid);

	/* Don't check pointer if id matches. If the reference was loaded from database it will not have pointer. */
	if (0 != n1->trigger_ref.triggerid)
		return 0;

	ZBX_RETURN_IF_NOT_EQUAL(n1->trigger_ref.trigger, n2->trigger_ref.trigger);

	return 0;
}

/* comparison function to determine trigger dependency validation order */
int	zbx_lld_trigger_node_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_trigger_node_t	*n1 = *(const zbx_lld_trigger_node_t **)d1;
	const zbx_lld_trigger_node_t	*n2 = *(const zbx_lld_trigger_node_t **)d2;

	/* sort in ascending order, but ensure that existing triggers are first */
	if (0 != n1->trigger_ref.triggerid && 0 == n2->trigger_ref.triggerid)
		return -1;

	/* give priority to nodes with less parents */
	ZBX_RETURN_IF_NOT_EQUAL(n1->parents, n2->parents);

	/* compare ids */
	ZBX_RETURN_IF_NOT_EQUAL(n1->trigger_ref.triggerid, n2->trigger_ref.triggerid);

	/* Don't check pointer if id matches. If the reference was loaded from database it will not have pointer. */
	if (0 != n1->trigger_ref.triggerid)
		return 0;

	ZBX_RETURN_IF_NOT_EQUAL(n1->trigger_ref.trigger, n2->trigger_ref.trigger);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_trigger_cache_append                                         *
 *                                                                            *
 * Purpose: adds a node to trigger cache                                      *
 *                                                                            *
 * Parameters: cache     - [IN] the trigger cache                             *
 *             triggerid - [IN] the trigger id                                *
 *             trigger   - [IN] the trigger data for new triggers             *
 *                                                                            *
 * Return value: the added node                                               *
 *                                                                            *
 ******************************************************************************/
static zbx_lld_trigger_node_t	*lld_trigger_cache_append(zbx_hashset_t *cache, zbx_uint64_t triggerid,
		zbx_lld_trigger_t *trigger)
{
	zbx_lld_trigger_node_t	node_local;

	node_local.trigger_ref.triggerid = triggerid;
	node_local.trigger_ref.trigger = trigger;
	node_local.trigger_ref.flags = 0;
	node_local.iter_num = 0;
	node_local.parents = 0;

	zbx_vector_ptr_create(&node_local.dependencies);

	return zbx_hashset_insert(cache, &node_local, sizeof(node_local));
}

/******************************************************************************
 *                                                                            *
 * Function: lld_trigger_cache_add_trigger_node                               *
 *                                                                            *
 * Purpose: add trigger and all triggers related to it to trigger dependency  *
 *          validation cache.                                                 *
 *                                                                            *
 * Parameters: cache           - [IN] the trigger cache                       *
 *             trigger         - [IN] the trigger to add                      *
 *             triggerids_up   - [OUT] identifiers of generic trigger         *
 *                                     dependents                             *
 *             triggerids_down - [OUT] identifiers of generic trigger         *
 *                                     dependencies                           *
 *                                                                            *
 ******************************************************************************/
static void	lld_trigger_cache_add_trigger_node(zbx_hashset_t *cache, zbx_lld_trigger_t *trigger,
		zbx_vector_uint64_t *triggerids_up, zbx_vector_uint64_t *triggerids_down)
{
	zbx_lld_trigger_ref_t	*trigger_ref;
	zbx_lld_trigger_node_t	*trigger_node, trigger_node_local;
	zbx_lld_dependency_t	*dependency;
	int			i;

	trigger_node_local.trigger_ref.triggerid = trigger->triggerid;
	trigger_node_local.trigger_ref.trigger = trigger;

	if (NULL != (trigger_node = zbx_hashset_search(cache, &trigger_node_local)))
		return;

	trigger_node = lld_trigger_cache_append(cache, trigger->triggerid, trigger);

	for (i = 0; i < trigger->dependencies.values_num; i++)
	{
		dependency = (zbx_lld_dependency_t *)trigger->dependencies.values[i];

		if (0 == (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED))
			continue;

		trigger_ref = (zbx_lld_trigger_ref_t *)zbx_malloc(NULL, sizeof(zbx_lld_trigger_ref_t));

		trigger_ref->triggerid = dependency->triggerid_up;
		trigger_ref->trigger = dependency->trigger_up;
		trigger_ref->flags = (0 == dependency->triggerdepid ? ZBX_LLD_TRIGGER_DEPENDENCY_NEW :
				ZBX_LLD_TRIGGER_DEPENDENCY_NORMAL);

		zbx_vector_ptr_append(&trigger_node->dependencies, trigger_ref);

		if (NULL == trigger_ref->trigger)
		{
			trigger_node_local.trigger_ref.triggerid = trigger_ref->triggerid;
			trigger_node_local.trigger_ref.trigger = NULL;

			if (NULL == zbx_hashset_search(cache, &trigger_node_local))
			{
				zbx_vector_uint64_append(triggerids_up, trigger_ref->triggerid);
				zbx_vector_uint64_append(triggerids_down, trigger_ref->triggerid);

				lld_trigger_cache_append(cache, trigger_ref->triggerid, NULL);
			}
		}
	}

	if (0 != trigger->triggerid)
		zbx_vector_uint64_append(triggerids_up, trigger->triggerid);

	for (i = 0; i < trigger->dependents.values_num; i++)
	{
		lld_trigger_cache_add_trigger_node(cache, trigger->dependents.values[i], triggerids_up,
				triggerids_down);
	}

	for (i = 0; i < trigger->dependencies.values_num; i++)
	{
		dependency = (zbx_lld_dependency_t *)trigger->dependencies.values[i];

		if (NULL != dependency->trigger_up)
		{
			lld_trigger_cache_add_trigger_node(cache, dependency->trigger_up, triggerids_up,
					triggerids_down);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: lld_trigger_cache_init                                           *
 *                                                                            *
 * Purpose: initializes trigger cache used to perform trigger dependency      *
 *          validation                                                        *
 *                                                                            *
 * Parameters: cache    - [IN] the trigger cache                              *
 *             triggers - [IN] the discovered triggers                        *
 *                                                                            *
 * Comments: Triggers with new dependencies and.all triggers related to them  *
 *           are added to cache.                                              *
 *                                                                            *
 ******************************************************************************/
static void	lld_trigger_cache_init(zbx_hashset_t *cache, zbx_vector_ptr_t *triggers)
{
	const char		*__function_name = "lld_trigger_cache_init";

	zbx_vector_uint64_t	triggerids_up, triggerids_down;
	int			i, j;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_trigger_ref_t	*trigger_ref;
	zbx_lld_trigger_node_t	*trigger_node, trigger_node_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_hashset_create(cache, triggers->values_num, zbx_lld_trigger_ref_hash_func,
			zbx_lld_trigger_ref_compare_func);

	zbx_vector_uint64_create(&triggerids_down);
	zbx_vector_uint64_create(&triggerids_up);

	/* add all triggers with new dependencies to trigger cache */
	for (i = 0; i < triggers->values_num; i++)
	{
		zbx_lld_trigger_t	*trigger = (zbx_lld_trigger_t *)triggers->values[i];

		for (j = 0; j < trigger->dependencies.values_num; j++)
		{
			zbx_lld_dependency_t	*dependency = (zbx_lld_dependency_t *)trigger->dependencies.values[j];

			if (0 == dependency->triggerdepid)
				break;
		}

		if (j != trigger->dependencies.values_num)
			lld_trigger_cache_add_trigger_node(cache, trigger, &triggerids_up, &triggerids_down);
	}

	/* keep trying to load generic dependents/dependencies until there are nothing to load */
	while (0 != triggerids_up.values_num || 0 != triggerids_down.values_num)
	{
		/* load dependents */
		if (0 != triggerids_down.values_num)
		{
			sql_offset = 0;
			zbx_vector_uint64_sort(&triggerids_down, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_vector_uint64_uniq(&triggerids_down, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select td.triggerid_down,td.triggerid_up"
					" from trigger_depends td"
						" left join triggers t"
							" on td.triggerid_up=t.triggerid"
					" where t.flags<>%d"
						" and", ZBX_FLAG_DISCOVERY_PROTOTYPE);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "td.triggerid_down",
					triggerids_down.values, triggerids_down.values_num);

			zbx_vector_uint64_clear(&triggerids_down);

			result = DBselect("%s", sql);

			while (NULL != (row = DBfetch(result)))
			{
				int			new_node = 0;
				zbx_lld_trigger_node_t	*trigger_node_up;

				ZBX_STR2UINT64(trigger_node_local.trigger_ref.triggerid, row[1]);

				if (NULL == (trigger_node_up = zbx_hashset_search(cache, &trigger_node_local)))
				{
					trigger_node_up = lld_trigger_cache_append(cache,
							trigger_node_local.trigger_ref.triggerid, NULL);
					new_node = 1;
				}

				ZBX_STR2UINT64(trigger_node_local.trigger_ref.triggerid, row[0]);

				if (NULL == (trigger_node = zbx_hashset_search(cache, &trigger_node_local)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
				}

				/* check if the dependency is not already registered in cache */
				for (i = 0; i < trigger_node->dependencies.values_num; i++)
				{
					trigger_ref = (zbx_lld_trigger_ref_t *)trigger_node->dependencies.values[i];

					/* references to generic triggers will always have valid id value */
					if (trigger_ref->triggerid == trigger_node_up->trigger_ref.triggerid)
						break;
				}

				/* if the dependency was not found - add it */
				if (i == trigger_node->dependencies.values_num)
				{
					trigger_ref = (zbx_lld_trigger_ref_t *)zbx_malloc(NULL,
							sizeof(zbx_lld_trigger_ref_t));

					trigger_ref->triggerid = trigger_node_up->trigger_ref.triggerid;
					trigger_ref->trigger = NULL;
					trigger_ref->flags = ZBX_LLD_TRIGGER_DEPENDENCY_NORMAL;

					zbx_vector_ptr_append(&trigger_node->dependencies, trigger_ref);

					trigger_node_up->parents++;
				}

				if (1 == new_node)
				{
					/* if the trigger was added to cache, we must check its dependencies */
					zbx_vector_uint64_append(&triggerids_up,
							trigger_node_up->trigger_ref.triggerid);
					zbx_vector_uint64_append(&triggerids_down,
							trigger_node_up->trigger_ref.triggerid);
				}
			}

			DBfree_result(result);
		}

		/* load dependencies */
		if (0 != triggerids_up.values_num)
		{
			sql_offset = 0;
			zbx_vector_uint64_sort(&triggerids_up, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_vector_uint64_uniq(&triggerids_up, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select td.triggerid_down"
					" from trigger_depends td"
						" left join triggers t"
							" on t.triggerid=td.triggerid_down"
					" where t.flags<>%d"
						" and", ZBX_FLAG_DISCOVERY_PROTOTYPE);
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "td.triggerid_up", triggerids_up.values,
					triggerids_up.values_num);

			zbx_vector_uint64_clear(&triggerids_up);

			result = DBselect("%s", sql);

			while (NULL != (row = DBfetch(result)))
			{
				ZBX_STR2UINT64(trigger_node_local.trigger_ref.triggerid, row[0]);

				if (NULL != zbx_hashset_search(cache, &trigger_node_local))
					continue;

				trigger_node = lld_trigger_cache_append(cache, trigger_node_local.trigger_ref.triggerid,
						NULL);

				zbx_vector_uint64_append(&triggerids_up, trigger_node_local.trigger_ref.triggerid);
				zbx_vector_uint64_append(&triggerids_down, trigger_node_local.trigger_ref.triggerid);
			}

			DBfree_result(result);
		}

	}

	zbx_free(sql);

	zbx_vector_uint64_destroy(&triggerids_up);
	zbx_vector_uint64_destroy(&triggerids_down);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_trigger_cache_clean                                          *
 *                                                                            *
 * Purpose: releases resources allocated by trigger cache                     *
 *          validation                                                        *
 *                                                                            *
 * Parameters: cache - [IN] the trigger cache                                 *
 *                                                                            *
 ******************************************************************************/
static void	zbx_trigger_cache_clean(zbx_hashset_t *cache)
{
	zbx_hashset_iter_t	iter;
	zbx_lld_trigger_node_t	*trigger_node;

	zbx_hashset_iter_reset(cache, &iter);
	while (NULL != (trigger_node = zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_ptr_clear_ext(&trigger_node->dependencies, zbx_ptr_free);
		zbx_vector_ptr_destroy(&trigger_node->dependencies);
	}

	zbx_hashset_destroy(cache);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_trigger_dependency_delete                                    *
 *                                                                            *
 * Purpose: removes trigger dependency                                        *
 *                                                                            *
 * Parameters: from  - [IN] the reference to dependent trigger                *
 *             to    - [IN] the reference to trigger the from depends on      *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Comments: If possible (the dependency loop was introduced by discovered    *
 *           dependencies) the last dependency in the loop will be removed.   *
 *           Otherwise (the triggers in database already had dependency loop) *
 *           the last dependency in the loop will be marked as removed,       *
 *           however the dependency in database will be left intact.          *
 *                                                                            *
 ******************************************************************************/
static void	lld_trigger_dependency_delete(zbx_lld_trigger_ref_t *from, zbx_lld_trigger_ref_t *to, char **error)
{
	zbx_lld_trigger_t	*trigger;
	int			i;
	char			*trigger_desc;

	if (ZBX_LLD_TRIGGER_DEPENDENCY_NORMAL == to->flags)
	{
		/* When old dependency loop has been detected mark it as deleted to avoid   */
		/* infinite recursion during dependency validation, but don't really delete */
		/* it because only new dependencies can be deleted.                         */

		/* in old dependency loop there are no new triggers, so all involved */
		/* triggers have valid identifiers                                   */
		zabbix_log(LOG_LEVEL_CRIT, "existing recursive dependency loop detected for trigger \""
				ZBX_FS_UI64 "\"", to->triggerid);
		return;
	}

	trigger = from->trigger;

	/* remove the dependency */
	for (i = 0; i < trigger->dependencies.values_num; i++)
	{
		zbx_lld_dependency_t	*dependency = (zbx_lld_dependency_t *)trigger->dependencies.values[i];

		if ((NULL != dependency->trigger_up && dependency->trigger_up == to->trigger) ||
				(0 != dependency->triggerid_up && dependency->triggerid_up == to->triggerid))
		{
			zbx_free(dependency);
			zbx_vector_ptr_remove(&trigger->dependencies, i);

			break;
		}
	}

	if (0 != from->triggerid)
		trigger_desc = zbx_dsprintf(NULL, ZBX_FS_UI64, from->triggerid);
	else
		trigger_desc = zbx_strdup(NULL, from->trigger->description);

	*error = zbx_strdcatf(*error, "Cannot create all trigger \"%s\" dependencies:"
			" recursion too deep.\n", trigger_desc);

	zbx_free(trigger_desc);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_trigger_dependencies_iter                                    *
 *                                                                            *
 * Purpose: iterates through trigger dependencies to find dependency loops    *
 *                                                                            *
 * Parameters: cache         - [IN] the trigger cache                         *
 *             triggers      - [IN] the discovered triggers                   *
 *             trigger_node  - [IN] the trigger to check                      *
 *             iter          - [IN] the dependency iterator                   *
 *             level         - [IN] the dependency level                      *
 *             error         - [OUT] the error message                        *
 *                                                                            *
 * Return value: SUCCEED - the trigger's dependency chain in valid            *
 *               FAIL    - a dependency loop was detected                     *
 *                                                                            *
 * Comments: If the validation fails the offending dependency is removed.     *
 *                                                                            *
 ******************************************************************************/
static int	lld_trigger_dependencies_iter(zbx_hashset_t *cache, zbx_vector_ptr_t *triggers,
		zbx_lld_trigger_node_t *trigger_node, zbx_lld_trigger_node_iter_t *iter, int level, char **error)
{
	int				i;
	zbx_lld_trigger_ref_t		*trigger_ref;
	zbx_lld_trigger_node_t		*trigger_node_up;
	zbx_lld_trigger_node_iter_t	child_iter, *piter;

	if (trigger_node->iter_num == iter->iter_num || ZBX_TRIGGER_DEPENDENCY_LEVELS_MAX < level)
	{
		/* dependency loop detected, resolve it by deleting corresponding dependency */
		lld_trigger_dependency_delete(iter->ref_from, iter->ref_to, error);

		/* mark the dependency as removed */
		iter->ref_to->flags = ZBX_LLD_TRIGGER_DEPENDENCY_DELETE;

		return FAIL;
	}

	trigger_node->iter_num = iter->iter_num;

	for (i = 0; i < trigger_node->dependencies.values_num; i++)
	{
		trigger_ref = (zbx_lld_trigger_ref_t *)trigger_node->dependencies.values[i];

		/* skip dependencies marked as deleted */
		if (ZBX_LLD_TRIGGER_DEPENDENCY_DELETE == trigger_ref->flags)
			continue;

		if (NULL == (trigger_node_up = zbx_hashset_search(cache, trigger_ref)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		/* Remember last dependency that could be cut.                         */
		/* It should be either a last new dependency or just a last dependency */
		/* if no new dependencies were encountered.                            */
		if (ZBX_LLD_TRIGGER_DEPENDENCY_NEW == trigger_ref->flags || NULL == iter->ref_to ||
				ZBX_LLD_TRIGGER_DEPENDENCY_NORMAL == iter->ref_to->flags)
		{
			child_iter.ref_from = &trigger_node->trigger_ref;
			child_iter.ref_to = trigger_ref;
			child_iter.iter_num = iter->iter_num;

			piter = &child_iter;
		}
		else
			piter = iter;

		if (FAIL == lld_trigger_dependencies_iter(cache, triggers, trigger_node_up, piter, level + 1, error))
			return FAIL;
	}

	trigger_node->iter_num = 0;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_trigger_dependencies_validate                                *
 *                                                                            *
 * Purpose: validate discovered trigger dependencies                          *
 *                                                                            *
 * Parameters: triggers - [IN] the discovered triggers                        *
 *             error    - [OUT] the error message                             *
 *             triggers - [IN] the discovered triggers                        *
 *             trigger  - [IN] the trigger to check                           *
 *             iter     - [IN] the dependency iterator                        *
 *             level    - [IN] the dependency level                           *
 *                                                                            *
 * Comments: During validation the dependency loops will be resolved by       *
 *           removing offending dependencies.                                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_trigger_dependencies_validate(zbx_vector_ptr_t *triggers, char **error)
{
	const char			*__function_name = "lld_trigger_dependencies_validate";

	zbx_hashset_t			cache;
	zbx_hashset_iter_t		iter;
	zbx_lld_trigger_node_t		*trigger_node, *trigger_node_up;
	zbx_lld_trigger_node_iter_t	node_iter = {0};
	zbx_vector_ptr_t		nodes;
	int				i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	lld_trigger_cache_init(&cache, triggers);

	/* Perform dependency validation in the order of trigger ids and starting with parentless triggers. */
	/* This will give some consistency in choosing what dependencies should be deleted in the case of   */
	/* recursion.                                                                                       */
	zbx_vector_ptr_create(&nodes);
	zbx_vector_ptr_reserve(&nodes, cache.num_data);

	zbx_hashset_iter_reset(&cache, &iter);
	while (NULL != (trigger_node = zbx_hashset_iter_next(&iter)))
	{
		for (i = 0; i < trigger_node->dependencies.values_num; i++)
		{
			if (NULL == (trigger_node_up = zbx_hashset_search(&cache,
					trigger_node->dependencies.values[i])))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			trigger_node_up->parents++;
		}
		zbx_vector_ptr_append(&nodes, trigger_node);
	}

	zbx_vector_ptr_sort(&nodes, zbx_lld_trigger_node_compare_func);

	for (i = 0; i < nodes.values_num; i++)
	{
		if (NULL == (trigger_node = zbx_hashset_search(&cache, (zbx_lld_trigger_node_t *)nodes.values[i])))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		/* If dependency iterator returns false it means that dependency loop was detected */
		/* (and resolved). In this case we have to validate dependencies for this trigger  */
		/* again.                                                                          */
		do
		{
			node_iter.iter_num++;
			node_iter.ref_from = NULL;
			node_iter.ref_to = NULL;
		}
		while (SUCCEED != lld_trigger_dependencies_iter(&cache, triggers, trigger_node, &node_iter, 0, error));
	}

	zbx_vector_ptr_destroy(&nodes);
	zbx_trigger_cache_clean(&cache);

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
	const char			*__function_name = "lld_update_triggers";

	zbx_vector_ptr_t		trigger_prototypes;
	zbx_vector_ptr_t		triggers;
	zbx_vector_ptr_t		items;
	zbx_lld_trigger_t		*trigger;
	zbx_lld_trigger_prototype_t	*trigger_prototype;
	int				i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&trigger_prototypes);

	lld_trigger_prototoypes_get(lld_ruleid, &trigger_prototypes);

	if (0 == trigger_prototypes.values_num)
		goto out;

	zbx_vector_ptr_create(&triggers);	/* list of triggers which were created or will be created or */
						/* updated by the trigger prototype */
	zbx_vector_ptr_create(&items);		/* list of items which are related to the trigger prototypes */

	lld_triggers_get(&trigger_prototypes, &triggers);
	lld_functions_get(&trigger_prototypes, &triggers);
	lld_dependencies_get(&trigger_prototypes, &triggers);
	lld_items_get(&trigger_prototypes, &items);

	/* simplifying trigger expressions */

	for (i = 0; i < trigger_prototypes.values_num; i++)
	{
		trigger_prototype = (zbx_lld_trigger_prototype_t *)trigger_prototypes.values[i];

		lld_expression_simplify(&trigger_prototype->expression, &trigger_prototype->functions);
	}

	for (i = 0; i < triggers.values_num; i++)
	{
		trigger = (zbx_lld_trigger_t *)triggers.values[i];

		lld_expression_simplify(&trigger->expression, &trigger->functions);
	}

	/* making triggers */

	lld_triggers_make(&trigger_prototypes, &triggers, &items, lld_rows, error);
	lld_triggers_validate(hostid, &triggers, error);
	lld_trigger_dependencies_make(&trigger_prototypes, &triggers, &items, lld_rows, error);
	lld_trigger_dependencies_validate(&triggers, error);
	lld_triggers_save(hostid, &trigger_prototypes, &triggers);

	/* cleaning */

	zbx_vector_ptr_clear_ext(&items, (zbx_mem_free_func_t)lld_item_free);
	zbx_vector_ptr_clear_ext(&triggers, (zbx_mem_free_func_t)lld_trigger_free);
	zbx_vector_ptr_destroy(&items);
	zbx_vector_ptr_destroy(&triggers);
out:
	zbx_vector_ptr_clear_ext(&trigger_prototypes, (zbx_mem_free_func_t)lld_trigger_prototype_free);
	zbx_vector_ptr_destroy(&trigger_prototypes);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
