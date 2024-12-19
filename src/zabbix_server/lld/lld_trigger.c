/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "lld.h"

#include "../db_lengths_constants.h"
#include "../server_constants.h"

#include "zbxexpression.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_trigger.h"
#include "zbxnum.h"
#include "zbxdbwrap.h"
#include "zbx_trigger_constants.h"
#include "zbxvariant.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxeval.h"
#include "zbxexpr.h"
#include "zbxstr.h"

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
#define ZBX_FLAG_LLD_FUNCTION_DELETE			__UINT64_C(0x02)
	zbx_uint64_t	flags;
}
zbx_lld_function_t;

ZBX_PTR_VECTOR_DECL(lld_function_ptr, zbx_lld_function_t*)
ZBX_PTR_VECTOR_IMPL(lld_function_ptr, zbx_lld_function_t*)

static int	lld_function_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_function_t	*f1 = *(const zbx_lld_function_t **)d1;
	const zbx_lld_function_t	*f2 = *(const zbx_lld_function_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(f1->functionid, f2->functionid);

	return 0;
}

struct zbx_lld_trigger_s
{
	zbx_uint64_t		triggerid;
	zbx_uint64_t		parent_triggerid;
	char			*description;
	char			*description_orig;
	char			*expression;
	char			*expression_orig;
	char			*recovery_expression;
	char			*recovery_expression_orig;

	/* expanded expressions having functionids replaced with itemid:func(params) */
	char			*expression_ex;
	char			*recovery_expression_ex;

	char			*comments;
	char			*comments_orig;
	char			*url;
	char			*url_orig;
	char			*url_name;
	char			*url_name_orig;
	char			*correlation_tag;
	char			*correlation_tag_orig;
	char			*opdata;
	char			*opdata_orig;
	char			*event_name;
	char			*event_name_orig;
	zbx_vector_lld_function_ptr_t	functions;
	zbx_vector_lld_dependency_ptr_t	dependencies;
	zbx_vector_lld_trigger_ptr_t	dependents;
	zbx_vector_db_tag_ptr_t	tags;
	zbx_vector_db_tag_ptr_t	override_tags;
#define ZBX_FLAG_LLD_TRIGGER_UNSET			__UINT64_C(0x0000)
#define ZBX_FLAG_LLD_TRIGGER_DISCOVERED			__UINT64_C(0x0001)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION		__UINT64_C(0x0002)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION		__UINT64_C(0x0004)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_TYPE		__UINT64_C(0x0008)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_PRIORITY		__UINT64_C(0x0010)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_COMMENTS		__UINT64_C(0x0020)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_URL			__UINT64_C(0x0040)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_RECOVERY_EXPRESSION	__UINT64_C(0x0080)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_RECOVERY_MODE	__UINT64_C(0x0100)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_CORRELATION_MODE	__UINT64_C(0x0200)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_CORRELATION_TAG	__UINT64_C(0x0400)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_MANUAL_CLOSE	__UINT64_C(0x0800)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_OPDATA		__UINT64_C(0x1000)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_EVENT_NAME		__UINT64_C(0x2000)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE_URL_NAME		__UINT64_C(0x4000)
#define ZBX_FLAG_LLD_TRIGGER_UPDATE										\
		(ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION | ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION |		\
		ZBX_FLAG_LLD_TRIGGER_UPDATE_TYPE | ZBX_FLAG_LLD_TRIGGER_UPDATE_PRIORITY |			\
		ZBX_FLAG_LLD_TRIGGER_UPDATE_COMMENTS | ZBX_FLAG_LLD_TRIGGER_UPDATE_URL |			\
		ZBX_FLAG_LLD_TRIGGER_UPDATE_RECOVERY_EXPRESSION | ZBX_FLAG_LLD_TRIGGER_UPDATE_RECOVERY_MODE |	\
		ZBX_FLAG_LLD_TRIGGER_UPDATE_CORRELATION_MODE | ZBX_FLAG_LLD_TRIGGER_UPDATE_CORRELATION_TAG |	\
		ZBX_FLAG_LLD_TRIGGER_UPDATE_MANUAL_CLOSE | ZBX_FLAG_LLD_TRIGGER_UPDATE_OPDATA |			\
		ZBX_FLAG_LLD_TRIGGER_UPDATE_EVENT_NAME | ZBX_FLAG_LLD_TRIGGER_UPDATE_URL_NAME)
	zbx_uint64_t		flags;
	int			lastcheck;
	unsigned char		discovery_status;
	int			ts_delete;
	int			ts_disable;
	unsigned char		disable_source;
	unsigned char		status;
	unsigned char		priority_orig;
	unsigned char		priority;
	unsigned char		manual_close_orig;
	unsigned char		correlation_mode_orig;
	unsigned char		recovery_mode_orig;
	unsigned char		type_orig;
};

ZBX_PTR_VECTOR_IMPL(lld_trigger_ptr, zbx_lld_trigger_t*)

static int	lld_trigger_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_trigger_t	*lld_trigger_1 = *(const zbx_lld_trigger_t **)d1;
	const zbx_lld_trigger_t	*lld_trigger_2 = *(const zbx_lld_trigger_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(lld_trigger_1->triggerid, lld_trigger_2->triggerid);

	return 0;
}

struct zbx_lld_dependency_s
{
	zbx_uint64_t		triggerdepid;
	zbx_uint64_t		triggerid_up;	/* generic trigger */
	zbx_lld_trigger_t	*trigger_up;	/* lld-created trigger; (null) if trigger depends on generic trigger */
#define ZBX_FLAG_LLD_DEPENDENCY_UNSET			__UINT64_C(0x00)
#define ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED		__UINT64_C(0x01)
#define ZBX_FLAG_LLD_DEPENDENCY_DELETE			__UINT64_C(0x02)
	zbx_uint64_t		flags;
};

ZBX_PTR_VECTOR_IMPL(lld_dependency_ptr, zbx_lld_dependency_t*)

static int	lld_dependency_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_dependency_t	*lld_dependency_1 = *(const zbx_lld_dependency_t **)d1;
	const zbx_lld_dependency_t	*lld_dependency_2 = *(const zbx_lld_dependency_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(lld_dependency_1->triggerdepid, lld_dependency_2->triggerdepid);

	return 0;
}

static void	lld_dependency_free(zbx_lld_dependency_t *dep)
{
	zbx_free(dep);
}

typedef struct
{
	zbx_uint64_t			triggerid;
	char				*description;
	char				*comments;
	char				*url;
	char				*url_name;
	char				*correlation_tag;
	char				*opdata;
	char				*event_name;
	char				*expression_orig;
	char				*recovery_expression_orig;
	unsigned char			status;
	unsigned char			type;
	unsigned char			priority;
	unsigned char			recovery_mode;
	unsigned char			correlation_mode;
	unsigned char			manual_close;
	unsigned char			discover;
	zbx_vector_lld_function_ptr_t	functions;
	zbx_vector_lld_dependency_ptr_t	dependencies;
	zbx_vector_db_tag_ptr_t		tags;
	zbx_eval_context_t		eval_ctx;
	zbx_eval_context_t		eval_ctx_r;
}
zbx_lld_trigger_prototype_t;

ZBX_PTR_VECTOR_DECL(lld_trigger_prototype_ptr, zbx_lld_trigger_prototype_t*)
ZBX_PTR_VECTOR_IMPL(lld_trigger_prototype_ptr, zbx_lld_trigger_prototype_t*)

static int	lld_trigger_prototype_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_trigger_prototype_t	*lld_trigger_prototype_1 = *(const zbx_lld_trigger_prototype_t **)d1;
	const zbx_lld_trigger_prototype_t	*lld_trigger_prototype_2 = *(const zbx_lld_trigger_prototype_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(lld_trigger_prototype_1->triggerid, lld_trigger_prototype_2->triggerid);

	return 0;
}

typedef struct
{
	zbx_uint64_t		parent_triggerid;
	zbx_uint64_t		itemid;
	zbx_lld_trigger_t	*trigger;
}
zbx_lld_item_trigger_t;

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

	/* flags used to mark dependencies when trigger reference is used to store dependency links */
	int			flags;
}
zbx_lld_trigger_ref_t;

static void	lld_trigger_ref_free(zbx_lld_trigger_ref_t *lld_trigger_ref)
{
	zbx_free(lld_trigger_ref);
}

ZBX_PTR_VECTOR_DECL(lld_trigger_ref_ptr, zbx_lld_trigger_ref_t*)
ZBX_PTR_VECTOR_IMPL(lld_trigger_ref_ptr, zbx_lld_trigger_ref_t*)

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
	zbx_vector_lld_trigger_ref_ptr_t	dependencies;
}
zbx_lld_trigger_node_t;

ZBX_PTR_VECTOR_DECL(lld_trigger_node_ptr, zbx_lld_trigger_node_t*)
ZBX_PTR_VECTOR_IMPL(lld_trigger_node_ptr, zbx_lld_trigger_node_t*)

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

typedef struct
{
	const char			*description;

	zbx_vector_lld_trigger_ptr_t	triggers;
}
zbx_lld_trigger_name_t;

static zbx_hash_t	lld_trigger_name_hash(const void *d)
{
	const zbx_lld_trigger_name_t	*name = (zbx_lld_trigger_name_t *)d;

	return ZBX_DEFAULT_STRING_HASH_FUNC(name->description);
}

static int	lld_trigger_name_compare(const void *d1, const void *d2)
{
	const zbx_lld_trigger_name_t	*name1 = (zbx_lld_trigger_name_t *)d1;
	const zbx_lld_trigger_name_t	*name2 = (zbx_lld_trigger_name_t *)d2;

	return strcmp(name1->description, name2->description);
}

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
	zbx_eval_clear(&trigger_prototype->eval_ctx);
	zbx_eval_clear(&trigger_prototype->eval_ctx_r);

	zbx_vector_db_tag_ptr_clear_ext(&trigger_prototype->tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&trigger_prototype->tags);
	zbx_vector_lld_dependency_ptr_clear_ext(&trigger_prototype->dependencies, lld_dependency_free);
	zbx_vector_lld_dependency_ptr_destroy(&trigger_prototype->dependencies);
	zbx_vector_lld_function_ptr_clear_ext(&trigger_prototype->functions, lld_function_free);
	zbx_vector_lld_function_ptr_destroy(&trigger_prototype->functions);
	zbx_free(trigger_prototype->event_name);
	zbx_free(trigger_prototype->opdata);
	zbx_free(trigger_prototype->correlation_tag);
	zbx_free(trigger_prototype->url);
	zbx_free(trigger_prototype->url_name);
	zbx_free(trigger_prototype->comments);
	zbx_free(trigger_prototype->recovery_expression_orig);
	zbx_free(trigger_prototype->expression_orig);
	zbx_free(trigger_prototype->description);
	zbx_free(trigger_prototype);
}

static void	lld_trigger_free(zbx_lld_trigger_t *trigger)
{
	zbx_vector_db_tag_ptr_clear_ext(&trigger->tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&trigger->override_tags);
	zbx_vector_db_tag_ptr_destroy(&trigger->tags);
	zbx_vector_lld_trigger_ptr_destroy(&trigger->dependents);
	zbx_vector_lld_dependency_ptr_clear_ext(&trigger->dependencies, lld_dependency_free);
	zbx_vector_lld_dependency_ptr_destroy(&trigger->dependencies);
	zbx_vector_lld_function_ptr_clear_ext(&trigger->functions, lld_function_free);
	zbx_vector_lld_function_ptr_destroy(&trigger->functions);
	zbx_free(trigger->event_name_orig);
	zbx_free(trigger->event_name);
	zbx_free(trigger->opdata_orig);
	zbx_free(trigger->opdata);
	zbx_free(trigger->correlation_tag_orig);
	zbx_free(trigger->correlation_tag);
	zbx_free(trigger->url_orig);
	zbx_free(trigger->url);
	zbx_free(trigger->url_name_orig);
	zbx_free(trigger->url_name);
	zbx_free(trigger->comments_orig);
	zbx_free(trigger->comments);
	zbx_free(trigger->recovery_expression_ex);
	zbx_free(trigger->recovery_expression_orig);
	zbx_free(trigger->recovery_expression);
	zbx_free(trigger->expression_ex);
	zbx_free(trigger->expression_orig);
	zbx_free(trigger->expression);
	zbx_free(trigger->description_orig);
	zbx_free(trigger->description);
	zbx_free(trigger);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Retrieves trigger prototypes which are inherited from the         *
 *          discovery rule.                                                   *
 *                                                                            *
 * Parameters: lld_ruleid         - [IN]                                      *
 *             trigger_prototypes - [OUT] sorted list of trigger prototypes   *
 *             error              - [OUT] error message                       *
 *                                                                            *
 ******************************************************************************/
static void	lld_trigger_prototypes_get(zbx_uint64_t lld_ruleid,
		zbx_vector_lld_trigger_prototype_ptr_t *trigger_prototypes,
		char **error)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	char				*errmsg = NULL;

	result = zbx_db_select(
			"select t.triggerid,t.description,t.expression,t.status,t.type,t.priority,t.comments,"
				"t.url,t.url_name,t.recovery_expression,t.recovery_mode,t.correlation_mode,"
				"t.correlation_tag,t.manual_close,t.opdata,t.discover,t.event_name"
			" from triggers t"
			" where t.triggerid in (select distinct tg.triggerid"
				" from triggers tg,functions f,items i,item_discovery id"
				" where tg.triggerid=f.triggerid"
					" and f.itemid=i.itemid"
					" and i.itemid=id.itemid"
					" and id.parent_itemid=" ZBX_FS_UI64 ")",
			lld_ruleid);

	/* run through trigger prototypes */
	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_trigger_prototype_t	*trigger_prototype = (zbx_lld_trigger_prototype_t *)zbx_malloc(NULL,
				sizeof(zbx_lld_trigger_prototype_t));

		ZBX_STR2UINT64(trigger_prototype->triggerid, row[0]);
		trigger_prototype->description = zbx_strdup(NULL, row[1]);
		trigger_prototype->expression_orig = zbx_strdup(NULL, row[2]);
		trigger_prototype->recovery_expression_orig = zbx_strdup(NULL, row[9]);
		ZBX_STR2UCHAR(trigger_prototype->status, row[3]);
		ZBX_STR2UCHAR(trigger_prototype->type, row[4]);
		ZBX_STR2UCHAR(trigger_prototype->priority, row[5]);
		ZBX_STR2UCHAR(trigger_prototype->recovery_mode, row[10]);
		trigger_prototype->comments = zbx_strdup(NULL, row[6]);
		trigger_prototype->url = zbx_strdup(NULL, row[7]);
		trigger_prototype->url_name = zbx_strdup(NULL, row[8]);
		ZBX_STR2UCHAR(trigger_prototype->correlation_mode, row[11]);
		trigger_prototype->correlation_tag = zbx_strdup(NULL, row[12]);
		ZBX_STR2UCHAR(trigger_prototype->manual_close, row[13]);
		trigger_prototype->opdata = zbx_strdup(NULL, row[14]);
		ZBX_STR2UCHAR(trigger_prototype->discover, row[15]);
		trigger_prototype->event_name = zbx_strdup(NULL, row[16]);

		zbx_vector_lld_function_ptr_create(&trigger_prototype->functions);
		zbx_vector_lld_dependency_ptr_create(&trigger_prototype->dependencies);
		zbx_vector_db_tag_ptr_create(&trigger_prototype->tags);

		zbx_eval_init(&trigger_prototype->eval_ctx);
		zbx_eval_init(&trigger_prototype->eval_ctx_r);

		if (SUCCEED != zbx_eval_parse_expression(&trigger_prototype->eval_ctx,
				trigger_prototype->expression_orig, ZBX_EVAL_TRIGGER_EXPRESSION_LLD, &errmsg))
		{
			*error = zbx_strdcatf(*error, "Invalid trigger prototype \"%s\" expression: %s\n",
					trigger_prototype->description, errmsg);
			zbx_free(errmsg);
			lld_trigger_prototype_free(trigger_prototype);
			continue;
		}

		if ('\0' != *trigger_prototype->recovery_expression_orig &&
				SUCCEED != zbx_eval_parse_expression(&trigger_prototype->eval_ctx_r,
				trigger_prototype->recovery_expression_orig, ZBX_EVAL_TRIGGER_EXPRESSION_LLD, &errmsg))
		{
			*error = zbx_strdcatf(*error, "Invalid trigger prototype \"%s\" recovery expression: %s\n",
					trigger_prototype->description, errmsg);
			zbx_free(errmsg);
			lld_trigger_prototype_free(trigger_prototype);
			continue;
		}

		zbx_vector_lld_trigger_prototype_ptr_append(trigger_prototypes, trigger_prototype);
	}
	zbx_db_free_result(result);

	zbx_vector_lld_trigger_prototype_ptr_sort(trigger_prototypes, lld_trigger_prototype_compare_func);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Retrieves triggers which were created by the specified trigger    *
 *          prototypes.                                                       *
 *                                                                            *
 * Parameters: trigger_prototypes - [IN] sorted list of trigger prototypes    *
 *             triggers           - [OUT] sorted list of triggers             *
 *                                                                            *
 ******************************************************************************/
static void	lld_triggers_get(const zbx_vector_lld_trigger_prototype_ptr_t *trigger_prototypes,
		zbx_vector_lld_trigger_ptr_t *triggers)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	parent_triggerids;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&parent_triggerids);
	zbx_vector_uint64_reserve(&parent_triggerids, (size_t)trigger_prototypes->values_num);

	for (int i = 0; i < trigger_prototypes->values_num; i++)
	{
		const zbx_lld_trigger_prototype_t	*trigger_prototype = trigger_prototypes->values[i];

		zbx_vector_uint64_append(&parent_triggerids, trigger_prototype->triggerid);
	}

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select td.parent_triggerid,t.triggerid,t.description,t.expression,t.type,t.priority,"
				"t.comments,t.url,t.url_name,t.recovery_expression,t.recovery_mode,t.correlation_mode,"
				"t.correlation_tag,t.manual_close,t.opdata,td.lastcheck,td.status,"
				"td.ts_delete,td.ts_disable,td.disable_source,t.event_name,t.status"
			" from triggers t,trigger_discovery td"
			" where t.triggerid=td.triggerid"
				" and");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "td.parent_triggerid",
			parent_triggerids.values, parent_triggerids.values_num);

	zbx_vector_uint64_destroy(&parent_triggerids);

	result = zbx_db_select("%s", sql);

	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t				parent_triggerid;
		const zbx_lld_trigger_prototype_t	*trigger_prototype;
		zbx_lld_trigger_t			*trigger;
		int					index;
		unsigned char				uc;

		ZBX_STR2UINT64(parent_triggerid, row[0]);

		zbx_lld_trigger_prototype_t	cmp = {.triggerid = parent_triggerid};

		if (FAIL == (index = zbx_vector_lld_trigger_prototype_ptr_bsearch(trigger_prototypes, &cmp,
				lld_trigger_prototype_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		trigger_prototype = trigger_prototypes->values[index];

		trigger = (zbx_lld_trigger_t *)zbx_malloc(NULL, sizeof(zbx_lld_trigger_t));

		ZBX_STR2UINT64(trigger->triggerid, row[1]);
		trigger->parent_triggerid = parent_triggerid;
		trigger->description = zbx_strdup(NULL, row[2]);
		trigger->description_orig = NULL;
		trigger->expression = zbx_strdup(NULL, row[3]);
		trigger->expression_orig = NULL;
		trigger->expression_ex = NULL;
		trigger->recovery_expression = zbx_strdup(NULL, row[9]);
		trigger->recovery_expression_orig = NULL;
		trigger->recovery_expression_ex = NULL;
		trigger->type_orig = 0;

		trigger->flags = ZBX_FLAG_LLD_TRIGGER_UNSET;

		if (trigger_prototype->type != (uc = (unsigned char)atoi(row[4])))
		{
			trigger->type_orig = uc;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_TYPE;
		}

		trigger->priority = (unsigned char)atoi(row[5]);
		trigger->priority_orig = trigger->priority;
		trigger->manual_close_orig = 0;
		trigger->correlation_mode_orig = 0;
		trigger->recovery_mode_orig = 0;

		if (trigger_prototype->recovery_mode != (uc = (unsigned char)atoi(row[10])))
		{
			trigger->recovery_mode_orig = uc;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_RECOVERY_MODE;
		}

		if (trigger_prototype->correlation_mode != (uc = (unsigned char)atoi(row[11])))
		{
			trigger->correlation_mode_orig = uc;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_CORRELATION_MODE;
		}

		if (trigger_prototype->manual_close != (uc = (unsigned char)atoi(row[13])))
		{
			trigger->manual_close_orig = uc;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_MANUAL_CLOSE;
		}

		trigger->comments = zbx_strdup(NULL, row[6]);
		trigger->comments_orig = NULL;
		trigger->url = zbx_strdup(NULL, row[7]);
		trigger->url_orig = NULL;
		trigger->url_name = zbx_strdup(NULL, row[8]);
		trigger->url_name_orig = NULL;
		trigger->correlation_tag = zbx_strdup(NULL, row[12]);
		trigger->correlation_tag_orig = NULL;
		trigger->opdata = zbx_strdup(NULL, row[14]);
		trigger->opdata_orig = NULL;
		trigger->event_name = zbx_strdup(NULL, row[20]);
		trigger->event_name_orig = NULL;
		trigger->lastcheck = atoi(row[15]);
		ZBX_STR2UCHAR(trigger->discovery_status, row[16]);
		trigger->ts_delete = atoi(row[17]);
		trigger->ts_disable = atoi(row[18]);
		ZBX_STR2UCHAR(trigger->disable_source, row[19]);
		ZBX_STR2UCHAR(trigger->status, row[21]);

		zbx_vector_lld_function_ptr_create(&trigger->functions);
		zbx_vector_lld_dependency_ptr_create(&trigger->dependencies);
		zbx_vector_lld_trigger_ptr_create(&trigger->dependents);
		zbx_vector_db_tag_ptr_create(&trigger->tags);
		zbx_vector_db_tag_ptr_create(&trigger->override_tags);

		zbx_vector_lld_trigger_ptr_append(triggers, trigger);
	}
	zbx_db_free_result(result);

	zbx_vector_lld_trigger_ptr_sort(triggers, lld_trigger_compare_func);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

typedef struct
{
	zbx_uint64_t			triggerid;
	zbx_vector_lld_function_ptr_t	*functions;
}
zbx_trigger_functions_t;

/******************************************************************************
 *                                                                            *
 * Purpose: Retrieves functions which are used by all triggers in the host of *
 *          the trigger prototype.                                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_trigger_functions_get(zbx_hashset_t *trigger_functions)
{
	zbx_vector_uint64_t		triggerids;
	zbx_hashset_iter_t		iter;
	zbx_trigger_functions_t		*tfuncs;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_reserve(&triggerids, trigger_functions->num_data);

	zbx_hashset_iter_reset(trigger_functions, &iter);
	while (NULL != (tfuncs = (zbx_trigger_functions_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_uint64_append(&triggerids, tfuncs->triggerid);

	zbx_db_large_query_t	query;
	zbx_db_row_t		row;
	zbx_lld_function_t	*function;
	zbx_uint64_t		triggerid;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select functionid,triggerid,itemid,name,parameter"
			" from functions"
			" where");

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "triggerid", &triggerids);
	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		ZBX_STR2UINT64(triggerid, row[1]);

		if (NULL == (tfuncs = (zbx_trigger_functions_t *)zbx_hashset_search(trigger_functions, &triggerid)))
		{
			THIS_SHOULD_NEVER_HAPPEN_MSG("cannot find trigger in local trigger functions cache");
			continue;
		}

		function = (zbx_lld_function_t *)zbx_malloc(NULL, sizeof(zbx_lld_function_t));

		function->index = 0;
		ZBX_STR2UINT64(function->functionid, row[0]);
		ZBX_STR2UINT64(function->itemid, row[2]);
		function->itemid_orig = 0;
		function->function = zbx_strdup(NULL, row[3]);
		function->function_orig = NULL;
		function->parameter = zbx_strdup(NULL, row[4]);
		function->parameter_orig = NULL;
		function->flags = ZBX_FLAG_LLD_FUNCTION_UNSET;

		zbx_vector_lld_function_ptr_append(tfuncs->functions, function);
	}
	zbx_db_large_query_clear(&query);

	zbx_free(sql);

	zbx_hashset_iter_reset(trigger_functions, &iter);
	while (NULL != (tfuncs = (zbx_trigger_functions_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_lld_function_ptr_sort(tfuncs->functions, lld_function_compare_func);

	zbx_vector_uint64_destroy(&triggerids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Retrieves functions which are used by all triggers in the host of *
 *          the trigger prototype.                                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_functions_get(zbx_vector_lld_trigger_prototype_ptr_t *trigger_prototypes,
		zbx_vector_lld_trigger_ptr_t *triggers)
{
	zbx_lld_trigger_prototype_t	*trigger_prototype;
	zbx_lld_trigger_t		*trigger;
	zbx_hashset_t			trigger_functions;
	zbx_trigger_functions_t		tfuncs_local;
	int				triggers_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != trigger_prototypes)
		triggers_num = trigger_prototypes->values_num;

	if (NULL!= triggers)
		triggers_num += triggers->values_num;

	zbx_hashset_create(&trigger_functions, triggers_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (NULL != trigger_prototypes)
	{
		for (int i = 0; i < trigger_prototypes->values_num; i++)
		{
			trigger_prototype = trigger_prototypes->values[i];
			tfuncs_local.triggerid = trigger_prototype->triggerid;
			tfuncs_local.functions = &trigger_prototype->functions;
			zbx_hashset_insert(&trigger_functions, &tfuncs_local, sizeof(tfuncs_local));
		}
	}

	for (int i = 0; i < triggers->values_num; i++)
	{
		trigger = triggers->values[i];
		tfuncs_local.triggerid = trigger->triggerid;
		tfuncs_local.functions = &trigger->functions;
		zbx_hashset_insert(&trigger_functions, &tfuncs_local, sizeof(tfuncs_local));
	}

	if (0 != trigger_functions.num_data)
		lld_trigger_functions_get(&trigger_functions);

	zbx_hashset_destroy(&trigger_functions);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves trigger dependencies                                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_dependencies_get(zbx_vector_lld_trigger_prototype_ptr_t *trigger_prototypes,
		zbx_vector_lld_trigger_ptr_t *triggers)
{
	zbx_db_row_t			row;
	zbx_lld_trigger_prototype_t	*trigger_prototype;
	zbx_lld_trigger_t		*trigger;
	zbx_lld_dependency_t		*dependency;
	zbx_vector_uint64_t		triggerids;
	zbx_uint64_t			triggerid_down;
	char				*sql = NULL;
	size_t				sql_alloc = 256, sql_offset = 0;
	int				index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&triggerids);

	for (int i = 0; i < trigger_prototypes->values_num; i++)
	{
		trigger_prototype = trigger_prototypes->values[i];

		zbx_vector_uint64_append(&triggerids, trigger_prototype->triggerid);
	}

	for (int i = 0; i < triggers->values_num; i++)
	{
		trigger = triggers->values[i];

		zbx_vector_uint64_append(&triggerids, trigger->triggerid);
	}

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select triggerdepid,triggerid_down,triggerid_up"
			" from trigger_depends"
			" where");

	zbx_db_large_query_t	query;

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "triggerid_down", &triggerids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		dependency = (zbx_lld_dependency_t *)zbx_malloc(NULL, sizeof(zbx_lld_dependency_t));

		ZBX_STR2UINT64(dependency->triggerdepid, row[0]);
		ZBX_STR2UINT64(triggerid_down, row[1]);
		ZBX_STR2UINT64(dependency->triggerid_up, row[2]);
		dependency->trigger_up = NULL;
		dependency->flags = ZBX_FLAG_LLD_DEPENDENCY_UNSET;

		zbx_lld_trigger_prototype_t	lld_trigger_prototype_cmp = {.triggerid = triggerid_down};
		zbx_lld_trigger_t		lld_trigger_cmp = {.triggerid = triggerid_down};

		if (FAIL != (index = zbx_vector_lld_trigger_prototype_ptr_bsearch(trigger_prototypes,
				&lld_trigger_prototype_cmp, lld_trigger_prototype_compare_func)))
		{
			trigger_prototype = trigger_prototypes->values[index];

			zbx_vector_lld_dependency_ptr_append(&trigger_prototype->dependencies, dependency);
		}
		else if (FAIL != (index = zbx_vector_lld_trigger_ptr_bsearch(triggers, &lld_trigger_cmp,
				lld_trigger_compare_func)))
		{
			trigger = triggers->values[index];

			zbx_vector_lld_dependency_ptr_append(&trigger->dependencies, dependency);
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_ptr_free(dependency);
		}
	}
	zbx_db_large_query_clear(&query);

	zbx_vector_uint64_destroy(&triggerids);
	zbx_free(sql);

	for (int i = 0; i < trigger_prototypes->values_num; i++)
	{
		trigger_prototype = trigger_prototypes->values[i];

		zbx_vector_lld_dependency_ptr_sort(&trigger_prototype->dependencies, lld_dependency_compare_func);
	}

	for (int i = 0; i < triggers->values_num; i++)
	{
		trigger = triggers->values[i];

		zbx_vector_lld_dependency_ptr_sort(&trigger->dependencies, lld_dependency_compare_func);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves trigger tags                                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_tags_get(const zbx_vector_lld_trigger_prototype_ptr_t *trigger_prototypes,
		zbx_vector_lld_trigger_ptr_t *triggers)
{
	zbx_db_row_t			row;
	zbx_vector_uint64_t		triggerids;
	zbx_lld_trigger_prototype_t	*trigger_prototype;
	zbx_lld_trigger_t		*trigger;
	char				*sql = NULL;
	size_t				sql_alloc = 256, sql_offset = 0;

	zbx_vector_uint64_create(&triggerids);

	for (int i = 0; i < trigger_prototypes->values_num; i++)
	{
		trigger_prototype = trigger_prototypes->values[i];

		zbx_vector_uint64_append(&triggerids, trigger_prototype->triggerid);
	}

	for (int i = 0; i < triggers->values_num; i++)
	{
		trigger = triggers->values[i];

		zbx_vector_uint64_append(&triggerids, trigger->triggerid);
	}

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select triggertagid,triggerid,tag,value"
			" from trigger_tag"
			" where");

	zbx_db_large_query_t	query;

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "triggerid", &triggerids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_db_tag_t	*tag;
		int		index;
		zbx_uint64_t	triggerid;

		tag = zbx_db_tag_create(row[2], row[3]);
		ZBX_STR2UINT64(triggerid, row[1]);

		zbx_lld_trigger_prototype_t	lld_trigger_prototype_cmp = {.triggerid = triggerid};
		zbx_lld_trigger_t		lld_trigger_cmp = {.triggerid = triggerid};

		if (FAIL != (index = zbx_vector_lld_trigger_prototype_ptr_bsearch(trigger_prototypes,
				&lld_trigger_prototype_cmp, lld_trigger_prototype_compare_func)))
		{
			trigger_prototype = trigger_prototypes->values[index];

			zbx_vector_db_tag_ptr_append(&trigger_prototype->tags, tag);
		}
		else if (FAIL != (index = zbx_vector_lld_trigger_ptr_bsearch(triggers, &lld_trigger_cmp,
				lld_trigger_compare_func)))
		{
			trigger = triggers->values[index];

			ZBX_STR2UINT64(tag->tagid, row[0]);
			zbx_vector_db_tag_ptr_append(&trigger->tags, tag);
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_db_tag_free(tag);
		}
	}
	zbx_db_large_query_clear(&query);

	zbx_vector_uint64_destroy(&triggerids);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Returns the list of items which are related to the trigger        *
 *          prototypes.                                                       *
 *                                                                            *
 * Parameters: trigger_prototypes - [IN]                                      *
 *             items              - [OUT] sorted list of items                *
 *                                                                            *
 ******************************************************************************/
static void	lld_items_get(zbx_vector_lld_trigger_prototype_ptr_t *trigger_prototypes,
		zbx_vector_lld_item_ptr_t *items)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_lld_item_t		*item;
	zbx_vector_uint64_t	parent_triggerids;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&parent_triggerids);
	zbx_vector_uint64_reserve(&parent_triggerids, (size_t)trigger_prototypes->values_num);

	for (int i = 0; i < trigger_prototypes->values_num; i++)
	{
		zbx_lld_trigger_prototype_t	*trigger_prototype;

		trigger_prototype = trigger_prototypes->values[i];

		zbx_vector_uint64_append(&parent_triggerids, trigger_prototype->triggerid);
	}

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct i.itemid,i.flags"
			" from items i,functions f"
			" where i.itemid=f.itemid"
				" and");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "f.triggerid",
			parent_triggerids.values, parent_triggerids.values_num);

	zbx_vector_uint64_destroy(&parent_triggerids);

	result = zbx_db_select("%s", sql);

	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		item = (zbx_lld_item_t *)zbx_malloc(NULL, sizeof(zbx_lld_item_t));

		ZBX_STR2UINT64(item->itemid, row[0]);
		ZBX_STR2UCHAR(item->flags, row[1]);

		zbx_vector_lld_item_ptr_append(items, item);
	}

	zbx_db_free_result(result);

	zbx_vector_lld_item_ptr_sort(items, lld_item_compare_func);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Finds already existing trigger, using an item prototype and items *
 *          already created by it.                                            *
 *                                                                            *
 * Return value: upon successful completion returns pointer to trigger        *
 *                                                                            *
 ******************************************************************************/
static zbx_lld_trigger_t	*lld_trigger_get(zbx_uint64_t parent_triggerid, zbx_hashset_t *items_triggers,
		const zbx_vector_lld_item_link_ptr_t *item_links)
{
	for (int i = 0; i < item_links->values_num; i++)
	{
		zbx_lld_item_trigger_t		*item_trigger, item_trigger_local;
		const zbx_lld_item_link_t	*item_link = item_links->values[i];

		item_trigger_local.parent_triggerid = parent_triggerid;
		item_trigger_local.itemid = item_link->itemid;

		if (NULL != (item_trigger = (zbx_lld_item_trigger_t *)zbx_hashset_search(items_triggers,
				&item_trigger_local)))
			return item_trigger->trigger;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Sets indexes for functionid tokens {<functionid>} from the        *
 *          specified function vector.                                        *
 *                                                                            *
 ******************************************************************************/
static void	lld_eval_expression_index_functions(zbx_eval_context_t *ctx, zbx_vector_lld_function_ptr_t *functions)
{
	int			index;
	zbx_uint64_t		functionid;
	zbx_lld_function_t	*function;

	for (int i = 0; i < ctx->stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx->stack.values[i];

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
			continue;

		if (SUCCEED != zbx_is_uint64_n(ctx->expression + token->loc.l + 1, token->loc.r - token->loc.l - 1,
				&functionid))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_lld_function_t	cmp = {.functionid = functionid};

		if (FAIL != (index = zbx_vector_lld_function_ptr_bsearch(functions, &cmp, lld_function_compare_func)))
		{
			function = functions->values[index];

			function->index = (zbx_uint64_t)(index + 1);
			zbx_variant_set_ui64(&token->value, function->index);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Simplifies parsed expression by replacing {<functionid>} with     *
 *          {<function index>}.                                               *
 *                                                                            *
 ******************************************************************************/
static void	lld_eval_expression_simplify(zbx_eval_context_t *ctx, char **expression,
		zbx_vector_lld_function_ptr_t *functions)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __func__, (NULL != expression ? *expression : ""));

	if (SUCCEED == zbx_eval_status(ctx))
	{
		lld_eval_expression_index_functions(ctx, functions);

		if (NULL != expression)
		{
			char	*new_expression = NULL;

			zbx_eval_compose_expression(ctx, &new_expression);
			zbx_free(*expression);
			*expression = new_expression;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() expression:'%s'", __func__, (NULL != expression ? *expression : ""));
}

/******************************************************************************
 *                                                                            *
 * Purpose: Simplifies trigger expression by replacing {<functionid>} with    *
 *          {<function index>}.                                               *
 *                                                                            *
 ******************************************************************************/
static void	lld_trigger_expression_simplify(const zbx_lld_trigger_t *trigger, char **expression,
		zbx_vector_lld_function_ptr_t *functions)
{
	zbx_eval_context_t	ctx;
	char			*errmsg = NULL;

	if ('\0' == **expression)
		return;

	if (SUCCEED != zbx_eval_parse_expression(&ctx, *expression, ZBX_EVAL_TRIGGER_EXPRESSION_LLD, &errmsg))
	{
		const char	*type;

		type = (*expression == trigger->expression ? "" : " recovery");
		zabbix_log(LOG_LEVEL_DEBUG, "Invalid trigger \"%s\"%s expression: %s", trigger->description, type,
				errmsg);
		zbx_free(errmsg);

		/* set empty expression so it's replaced with discovered one */
		*expression = zbx_strdup(*expression, "");
		return;
	}

	lld_eval_expression_simplify(&ctx, expression, functions);
	zbx_eval_clear(&ctx);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Expands parsed expression function indexes with function strings  *
 *          in format itemid:func(params).                                    *
 *                                                                            *
 ******************************************************************************/
static char	*lld_eval_expression_expand(zbx_eval_context_t *ctx, const zbx_vector_lld_function_ptr_t *functions)
{
	char		*expression = NULL;
	zbx_uint64_t	index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __func__, ctx->expression);

	for (int i = 0; i < ctx->stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx->stack.values[i];

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
			continue;

		if (ZBX_VARIANT_UI64 != token->value.type)
		{
			if (SUCCEED != zbx_is_uint64_n(ctx->expression + token->loc.l + 1,
					token->loc.r - token->loc.l - 1, &index))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}
		}
		else
			index = token->value.data.ui64;

		for (int j = 0; j < functions->values_num; j++)
		{
			const zbx_lld_function_t	*function = functions->values[j];

			if (0 != (ZBX_FLAG_LLD_FUNCTION_DELETE & function->flags))
				continue;

			if (function->index == index)
			{
				char	*value;

				value = zbx_dsprintf(NULL, ZBX_FS_UI64 ":%s(%s)", function->itemid,
						function->function, function->parameter);
				zbx_variant_clear(&token->value);
				zbx_variant_set_str(&token->value, value);
				break;
			}
		}
	}

	zbx_eval_compose_expression(ctx, &expression);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() expression:'%s'", __func__, expression);

	return expression;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Expands trigger expression function indexes with function strings *
 *          in format itemid:func(params).                                    *
 *                                                                            *
 ******************************************************************************/
static char	*lld_trigger_expression_expand(const zbx_lld_trigger_t *trigger, const char *expression,
		const zbx_vector_lld_function_ptr_t *functions)
{
	zbx_eval_context_t	ctx;
	char			*errmsg = NULL, *new_expression;

	if ('\0' == *expression)
		return zbx_strdup(NULL, "");

	if (SUCCEED != zbx_eval_parse_expression(&ctx, expression,
			ZBX_EVAL_TRIGGER_EXPRESSION_LLD & (~ZBX_EVAL_COMPOSE_FUNCTIONID), &errmsg))
	{
		const char	*type;

		type = (expression == trigger->expression ? "" : " recovery");
		zabbix_log(LOG_LEVEL_DEBUG, "Invalid trigger \"%s\"%s expression: %s", trigger->description, type,
				errmsg);
		zbx_free(errmsg);

		return zbx_strdup(NULL, "");
	}

	new_expression = lld_eval_expression_expand(&ctx, functions);
	zbx_eval_clear(&ctx);

	return new_expression;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Sets function indexes and expands them to function strings in     *
 *          format itemid:func(params).                                       *
 *                                                                            *
 ******************************************************************************/
static void	lld_trigger_expression_simplify_and_expand(const zbx_lld_trigger_t *trigger, char **expression,
		zbx_vector_lld_function_ptr_t *functions)
{
	zbx_eval_context_t	ctx;
	char			*errmsg = NULL, *new_expression;

	if ('\0' == **expression)
		return;

	if (SUCCEED != zbx_eval_parse_expression(&ctx, *expression,
			ZBX_EVAL_TRIGGER_EXPRESSION_LLD & (~ZBX_EVAL_COMPOSE_FUNCTIONID), &errmsg))
	{
		const char	*type;

		type = (*expression == trigger->expression ? "" : " recovery");
		zabbix_log(LOG_LEVEL_DEBUG, "Invalid trigger \"%s\"%s expression: %s", trigger->description, type,
				errmsg);
		zbx_free(errmsg);

		/* reset expression so it's replaced with discovered one */
		*expression = zbx_strdup(*expression, "");
		return;
	}

	lld_eval_expression_index_functions(&ctx, functions);
	new_expression = lld_eval_expression_expand(&ctx, functions);
	zbx_eval_clear(&ctx);
	zbx_free(*expression);
	*expression = new_expression;
}

static int	lld_parameter_make(const char *e, char **exp, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macros, char **error)
{
	int	ret;
	size_t	length, exp_alloc = 0, exp_offset = 0;
	char	err[64];

	if (FAIL == zbx_function_validate_parameters(e, &length))
	{
		*error = zbx_dsprintf(*error, "Invalid parameter \"%s\"", e);
		return FAIL;
	}

	if (FAIL == (ret = zbx_substitute_function_lld_param(e, length, 0, exp, &exp_alloc, &exp_offset, jp_row,
			lld_macros, ZBX_BACKSLASH_ESC_ON, err, sizeof(err))))
	{
		*error = zbx_strdup(*error, err);
	}

	return ret;
}

static int	lld_function_make(const zbx_lld_function_t *function_proto, zbx_vector_lld_function_ptr_t *functions,
		zbx_uint64_t itemid, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macros, char **error)
{
	int			ret, function_found = 0;
	zbx_lld_function_t	*function = NULL;
	char			*proto_parameter = NULL;

	for (int i = 0; i < functions->values_num; i++)
	{
		function = functions->values[i];

		if (0 != (function->flags & ZBX_FLAG_LLD_FUNCTION_DISCOVERED))
			continue;

		if (function->index == function_proto->index)
		{
			function_found = 1;
			break;
		}
	}

	if (FAIL == (ret = lld_parameter_make(function_proto->parameter, &proto_parameter, jp_row, lld_macros, error)))
		goto clean;

	if (0 == function_found || function->itemid != itemid ||
			0 != strcmp(function->function, function_proto->function) ||
			0 != strcmp(function->parameter, proto_parameter))
	{
		function = (zbx_lld_function_t *)zbx_malloc(NULL, sizeof(zbx_lld_function_t));

		function->index = function_proto->index;
		function->functionid = 0;
		function->itemid = itemid;
		function->itemid_orig = 0;
		function->function = zbx_strdup(NULL, function_proto->function);
		function->function_orig = NULL;
		function->parameter = proto_parameter;
		proto_parameter = NULL;
		function->parameter_orig = NULL;
		function->flags = ZBX_FLAG_LLD_FUNCTION_DISCOVERED;

		zbx_vector_lld_function_ptr_append(functions, function);
	}
	else
		function->flags = ZBX_FLAG_LLD_FUNCTION_DISCOVERED;
clean:
	zbx_free(proto_parameter);

	return ret;
}

static void	lld_functions_delete(zbx_vector_lld_function_ptr_t *functions)
{
	for (int i = 0; i < functions->values_num; i++)
	{
		zbx_lld_function_t	*function = functions->values[i];

		if (0 != (function->flags & ZBX_FLAG_LLD_FUNCTION_DISCOVERED))
			continue;

		function->flags |= ZBX_FLAG_LLD_FUNCTION_DELETE;
	}
}

static int	lld_functions_make(const zbx_vector_lld_function_ptr_t *functions_proto,
		zbx_vector_lld_function_ptr_t *functions, const zbx_vector_lld_item_ptr_t *items,
		const zbx_vector_lld_item_link_ptr_t *item_links, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macros, char **error)
{
	int				index, ret = FAIL;
	const zbx_lld_function_t	*function_proto;
	const zbx_lld_item_t		*item_proto;
	const zbx_lld_item_link_t	*item_link;
	zbx_uint64_t			itemid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int i = 0; i < functions_proto->values_num; i++)
	{
		function_proto = functions_proto->values[i];

		zbx_lld_item_t	lld_item_cmp = {.itemid = function_proto->itemid};

		index = zbx_vector_lld_item_ptr_bsearch(items, &lld_item_cmp, lld_item_compare_func);

		if (FAIL == index)
			goto out;

		item_proto = items->values[index];

		if (0 != (item_proto->flags & ZBX_FLAG_DISCOVERY_PROTOTYPE))
		{
			zbx_lld_item_link_t	lld_item_link_cmp = {.parent_itemid = item_proto->itemid};

			index = zbx_vector_lld_item_link_ptr_bsearch(item_links, &lld_item_link_cmp,
					lld_item_link_compare_func);

			if (FAIL == index)
				goto out;

			item_link = item_links->values[index];

			itemid = item_link->itemid;
		}
		else
			itemid = item_proto->itemid;

		if (FAIL == lld_function_make(function_proto, functions, itemid, jp_row, lld_macros, error))
			goto out;
	}

	lld_functions_delete(functions);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns copy of expression with expanded LLD macros               *
 *                                                                            *
 ******************************************************************************/
static char	*lld_eval_get_expanded_expression(const zbx_eval_context_t *src, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macros, char *err, size_t err_len)
{
	zbx_eval_context_t	ctx;
	char			*expression = NULL;

	/* empty expression will not be parsed */
	if (SUCCEED != zbx_eval_status(src))
		return zbx_strdup(NULL, "");

	zbx_eval_copy(&ctx, src, src->expression);

	for (int i = 0; i < ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx.stack.values[i];
		char			*value;

		switch(token->type)
		{
			case ZBX_EVAL_TOKEN_VAR_LLDMACRO:
			case ZBX_EVAL_TOKEN_VAR_USERMACRO:
			case ZBX_EVAL_TOKEN_VAR_STR:
				break;
			default:
				continue;
		}

		value = zbx_substr_unquote(ctx.expression, token->loc.l, token->loc.r);

		if (FAIL == zbx_substitute_lld_macros(&value, jp_row, lld_macros, ZBX_MACRO_ANY, err, err_len))
		{
			zbx_free(value);
			goto out;
		}

		zbx_variant_clear(&token->value);
		zbx_variant_set_str(&token->value, value);
	}

	zbx_eval_compose_expression(&ctx, &expression);
out:
	zbx_eval_clear(&ctx);

	return expression;
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates trigger based on LLD rule and adds it to list             *
 *                                                                            *
 ******************************************************************************/
static void 	lld_trigger_make(const zbx_lld_trigger_prototype_t *trigger_prototype,
		zbx_vector_lld_trigger_ptr_t *triggers, const zbx_vector_lld_item_ptr_t *items,
		zbx_hashset_t *items_triggers, const zbx_lld_row_t *lld_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macros, int lastcheck, char **error)
{
	zbx_lld_trigger_t		*trigger;
	char				*expression = NULL, *recovery_expression = NULL, err[64];
	char				*err_msg = NULL, *description = NULL;
	const char			*operation_msg;
	const struct zbx_json_parse	*jp_row = &lld_row->jp_row;
	unsigned char			discover;
	int				func_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	trigger = lld_trigger_get(trigger_prototype->triggerid, items_triggers, &lld_row->item_links);
	operation_msg = NULL != trigger ? "update" : "create";

	description = zbx_strdup(NULL, trigger_prototype->description);
	zbx_substitute_lld_macros(&description, jp_row, lld_macros, ZBX_MACRO_FUNC, NULL, 0);
	zbx_lrtrim(description, ZBX_WHITESPACE);

	if (NULL == (expression = lld_eval_get_expanded_expression(&trigger_prototype->eval_ctx, jp_row, lld_macros,
			err, sizeof(err))) ||
			NULL == (recovery_expression = lld_eval_get_expanded_expression(&trigger_prototype->eval_ctx_r,
					jp_row, lld_macros, err, sizeof(err))))
	{
		*error = zbx_strdcatf(*error, "Cannot %s trigger \"%s\": failed to expand LLD macros in%s expression: %s.\n",
				operation_msg, description, (NULL == expression ? "" : " recovery"), err);
		goto out;
	}

	discover = trigger_prototype->discover;

	if (NULL != trigger)
	{
		unsigned char	priority;
		char		*buffer = NULL;

		priority = trigger_prototype->priority;

		lld_override_trigger(&lld_row->overrides, description, &priority, &trigger->override_tags, NULL, &discover);

		if (ZBX_PROTOTYPE_NO_DISCOVER == discover)
			goto out;

		if (0 != strcmp(trigger->description, description))
		{
			trigger->description_orig = trigger->description;
			trigger->description = description;
			description = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION;
		}

		if (trigger->priority != priority)
		{
			trigger->priority = priority;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_PRIORITY;
		}

		if (0 != strcmp(trigger->expression, expression))
		{
			trigger->expression_orig = trigger->expression;
			trigger->expression = expression;
			expression = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION;
		}

		if (0 != strcmp(trigger->recovery_expression, recovery_expression))
		{
			trigger->recovery_expression_orig = trigger->recovery_expression;
			trigger->recovery_expression = recovery_expression;
			recovery_expression = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_RECOVERY_EXPRESSION;
		}

		buffer = zbx_strdup(buffer, trigger_prototype->comments);
		zbx_substitute_lld_macros(&buffer, jp_row, lld_macros, ZBX_MACRO_FUNC, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(trigger->comments, buffer))
		{
			trigger->comments_orig = trigger->comments;
			trigger->comments = buffer;
			buffer = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_COMMENTS;
		}

		buffer = zbx_strdup(buffer, trigger_prototype->url);
		zbx_substitute_lld_macros(&buffer, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(trigger->url, buffer))
		{
			trigger->url_orig = trigger->url;
			trigger->url = buffer;
			buffer = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_URL;
		}

		buffer = zbx_strdup(buffer, trigger_prototype->url_name);
		zbx_substitute_lld_macros(&buffer, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(trigger->url_name, buffer))
		{
			trigger->url_name_orig = trigger->url_name;
			trigger->url_name = buffer;
			buffer = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_URL_NAME;
		}

		buffer = zbx_strdup(buffer, trigger_prototype->correlation_tag);
		zbx_substitute_lld_macros(&buffer, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(trigger->correlation_tag, buffer))
		{
			trigger->correlation_tag_orig = trigger->correlation_tag;
			trigger->correlation_tag = buffer;
			buffer = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_CORRELATION_TAG;
		}

		buffer = zbx_strdup(buffer, trigger_prototype->opdata);
		zbx_substitute_lld_macros(&buffer, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(trigger->opdata, buffer))
		{
			trigger->opdata_orig = trigger->opdata;
			trigger->opdata = buffer;
			buffer = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_OPDATA;
		}

		buffer = zbx_strdup(buffer, trigger_prototype->event_name);
		zbx_substitute_lld_macros(&buffer, jp_row, lld_macros,
				ZBX_MACRO_ANY | ZBX_TOKEN_EXPRESSION_MACRO | ZBX_MACRO_FUNC, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(trigger->event_name, buffer))
		{
			trigger->event_name_orig = trigger->event_name;
			trigger->event_name = buffer;
			buffer = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_EVENT_NAME;
		}

		zbx_free(buffer);
	}
	else
	{
		trigger = (zbx_lld_trigger_t *)zbx_malloc(NULL, sizeof(zbx_lld_trigger_t));

		trigger->triggerid = 0;
		trigger->lastcheck = lastcheck;
		trigger->discovery_status = ZBX_LLD_DISCOVERY_STATUS_NORMAL;
		trigger->ts_delete = 0;
		trigger->ts_disable = 0;
		trigger->disable_source = ZBX_DISABLE_SOURCE_DEFAULT;
		trigger->parent_triggerid = trigger_prototype->triggerid;

		trigger->description = description;
		trigger->description_orig = NULL;
		description = NULL;

		trigger->status = trigger_prototype->status;
		trigger->priority = trigger_prototype->priority;

		zbx_vector_db_tag_ptr_create(&trigger->override_tags);

		lld_override_trigger(&lld_row->overrides, trigger->description, &trigger->priority,
				&trigger->override_tags, &trigger->status, &discover);

		if (ZBX_PROTOTYPE_NO_DISCOVER == discover)
		{
			zbx_vector_db_tag_ptr_destroy(&trigger->override_tags);
			zbx_free(trigger->description);
			zbx_free(trigger);
			goto out;
		}

		trigger->expression = expression;
		trigger->expression_orig = NULL;
		trigger->expression_ex = NULL;
		expression = NULL;

		trigger->recovery_expression = recovery_expression;
		trigger->recovery_expression_orig = NULL;
		trigger->recovery_expression_ex = NULL;
		recovery_expression = NULL;

		trigger->comments = zbx_strdup(NULL, trigger_prototype->comments);
		trigger->comments_orig = NULL;
		zbx_substitute_lld_macros(&trigger->comments, jp_row, lld_macros, ZBX_MACRO_FUNC, NULL, 0);
		zbx_lrtrim(trigger->comments, ZBX_WHITESPACE);

		trigger->url = zbx_strdup(NULL, trigger_prototype->url);
		trigger->url_orig = NULL;
		zbx_substitute_lld_macros(&trigger->url, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(trigger->url, ZBX_WHITESPACE);

		trigger->url_name = zbx_strdup(NULL, trigger_prototype->url_name);
		trigger->url_name_orig = NULL;
		zbx_substitute_lld_macros(&trigger->url_name, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(trigger->url_name, ZBX_WHITESPACE);

		trigger->correlation_tag = zbx_strdup(NULL, trigger_prototype->correlation_tag);
		trigger->correlation_tag_orig = NULL;
		zbx_substitute_lld_macros(&trigger->correlation_tag, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(trigger->correlation_tag, ZBX_WHITESPACE);

		trigger->opdata = zbx_strdup(NULL, trigger_prototype->opdata);
		trigger->opdata_orig = NULL;
		zbx_substitute_lld_macros(&trigger->opdata, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(trigger->opdata, ZBX_WHITESPACE);

		trigger->event_name = zbx_strdup(NULL, trigger_prototype->event_name);
		trigger->event_name_orig = NULL;
		zbx_substitute_lld_macros(&trigger->event_name, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(trigger->event_name, ZBX_WHITESPACE);

		zbx_vector_lld_function_ptr_create(&trigger->functions);
		zbx_vector_lld_dependency_ptr_create(&trigger->dependencies);
		zbx_vector_lld_trigger_ptr_create(&trigger->dependents);
		zbx_vector_db_tag_ptr_create(&trigger->tags);

		trigger->flags = ZBX_FLAG_LLD_TRIGGER_UNSET;

		zbx_vector_lld_trigger_ptr_append(triggers, trigger);
	}

	func_num = trigger->functions.values_num;

	if (SUCCEED != lld_functions_make(&trigger_prototype->functions, &trigger->functions, items,
			&lld_row->item_links, jp_row, lld_macros, &err_msg))
	{
		if (err_msg)
		{
			*error = zbx_strdcatf(*error, "Cannot %s trigger \"%s\": %s.\n", trigger->description, operation_msg, err_msg);
			zbx_free(err_msg);
		}
		goto out;
	}

	/* functions are recreated instead of being updated */
	if (func_num != trigger->functions.values_num)
	{
		if (NULL != expression)
		{
			trigger->expression_orig = trigger->expression;
			trigger->expression = expression;
			expression = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION;
		}

		if (NULL != recovery_expression)
		{
			trigger->recovery_expression_orig = trigger->recovery_expression;
			trigger->recovery_expression = recovery_expression;
			recovery_expression = NULL;
			trigger->flags |= ZBX_FLAG_LLD_TRIGGER_UPDATE_RECOVERY_EXPRESSION;
		}
	}

	trigger->flags |= ZBX_FLAG_LLD_TRIGGER_DISCOVERED;
out:
	zbx_free(recovery_expression);
	zbx_free(expression);
	zbx_free(description);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_hash_t	items_triggers_hash_func(const void *data)
{
	const zbx_lld_item_trigger_t	*item_trigger = (const zbx_lld_item_trigger_t *)data;
	zbx_hash_t			hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&item_trigger->parent_triggerid);
	hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&item_trigger->itemid, sizeof(zbx_uint64_t), hash);

	return hash;
}

static int	items_triggers_compare_func(const void *d1, const void *d2)
{
	const zbx_lld_item_trigger_t	*item_trigger1 = (const zbx_lld_item_trigger_t *)d1,
					*item_trigger2 = (const zbx_lld_item_trigger_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(item_trigger1->parent_triggerid, item_trigger2->parent_triggerid);
	ZBX_RETURN_IF_NOT_EQUAL(item_trigger1->itemid, item_trigger2->itemid);

	return 0;
}

static void	lld_triggers_make(const zbx_vector_lld_trigger_prototype_ptr_t *trigger_prototypes,
		zbx_vector_lld_trigger_ptr_t *triggers, const zbx_vector_lld_item_ptr_t *items,
		const zbx_vector_lld_row_ptr_t *lld_rows, const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths,
		int lastcheck, char **error)
{
	const zbx_lld_trigger_prototype_t	*trigger_prototype;
	zbx_hashset_t				items_triggers;
	zbx_lld_trigger_t			*trigger;
	const zbx_lld_function_t		*function;
	zbx_lld_item_trigger_t			item_trigger;

	/* used for fast search of trigger by item prototype */
	zbx_hashset_create(&items_triggers, 512, items_triggers_hash_func, items_triggers_compare_func);

	for (int i = 0; i < triggers->values_num; i++)
	{
		trigger = triggers->values[i];

		for (int j = 0; j < trigger->functions.values_num; j++)
		{
			function = trigger->functions.values[j];

			item_trigger.parent_triggerid = trigger->parent_triggerid;
			item_trigger.itemid = function->itemid;
			item_trigger.trigger = trigger;
			zbx_hashset_insert(&items_triggers, &item_trigger, sizeof(item_trigger));
		}
	}

	for (int i = 0; i < trigger_prototypes->values_num; i++)
	{
		trigger_prototype = trigger_prototypes->values[i];

		for (int j = 0; j < lld_rows->values_num; j++)
		{
			zbx_lld_row_t	*lld_row = lld_rows->values[j];

			lld_trigger_make(trigger_prototype, triggers, items, &items_triggers, lld_row, lld_macro_paths,
					lastcheck, error);
		}
	}

	zbx_hashset_destroy(&items_triggers);

	zbx_vector_lld_trigger_ptr_sort(triggers, lld_trigger_compare_func);
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates trigger dependencies                                      *
 *                                                                            *
 ******************************************************************************/
static void 	lld_trigger_dependency_make(const zbx_lld_trigger_prototype_t *trigger_prototype,
		const zbx_vector_lld_trigger_prototype_ptr_t *trigger_prototypes, zbx_hashset_t *items_triggers,
		const zbx_lld_row_t *lld_row, char **error)
{
	zbx_lld_trigger_t			*trigger, *dep_trigger;
	const zbx_lld_trigger_prototype_t	*dep_trigger_prototype;
	zbx_lld_dependency_t			*dependency = NULL;
	zbx_uint64_t				triggerid_up;
	int					index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (trigger = lld_trigger_get(trigger_prototype->triggerid, items_triggers, &lld_row->item_links)))
		goto out;

	for (int i = 0; i < trigger_prototype->dependencies.values_num; i++)
	{
		triggerid_up = (trigger_prototype->dependencies.values[i])->triggerid_up;

		zbx_lld_trigger_prototype_t	cmp = {.triggerid = triggerid_up};

		index = zbx_vector_lld_trigger_prototype_ptr_bsearch(trigger_prototypes, &cmp,
				lld_trigger_prototype_compare_func);

		if (FAIL != index)
		{
			/* creating trigger dependency based on trigger prototype */

			dep_trigger_prototype = trigger_prototypes->values[index];

			dep_trigger = lld_trigger_get(dep_trigger_prototype->triggerid, items_triggers,
					&lld_row->item_links);

			if (NULL != dep_trigger)
			{
				if (0 == dep_trigger->triggerid)
				{
					dependency = (zbx_lld_dependency_t *)zbx_malloc(NULL,
							sizeof(zbx_lld_dependency_t));

					dependency->triggerdepid = 0;
					dependency->triggerid_up = 0;

					zbx_vector_lld_dependency_ptr_append(&trigger->dependencies, dependency);
				}
				else
				{
					int	dependency_found = 0;

					for (int j = 0; j < trigger->dependencies.values_num; j++)
					{
						dependency = trigger->dependencies.values[j];

						if (0 != (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED))
							continue;

						if (dependency->triggerid_up == dep_trigger->triggerid)
						{
							dependency_found = 1;
							break;
						}
					}

					if (0 == dependency_found)
					{
						dependency = (zbx_lld_dependency_t *)zbx_malloc(NULL,
								sizeof(zbx_lld_dependency_t));

						dependency->triggerdepid = 0;
						dependency->triggerid_up = dep_trigger->triggerid;

						zbx_vector_lld_dependency_ptr_append(&trigger->dependencies,
								dependency);
					}
				}

				zbx_vector_lld_trigger_ptr_append(&dep_trigger->dependents, trigger);

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
			int	j;

			for (j = 0; j < trigger->dependencies.values_num; j++)
			{
				dependency = trigger->dependencies.values[j];

				if (0 != (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED))
					continue;

				if (dependency->triggerid_up == triggerid_up)
					break;
			}

			if (j == trigger->dependencies.values_num)
			{
				dependency = (zbx_lld_dependency_t *)zbx_malloc(NULL, sizeof(zbx_lld_dependency_t));

				dependency->triggerdepid = 0;
				dependency->triggerid_up = triggerid_up;
				dependency->trigger_up = NULL;

				zbx_vector_lld_dependency_ptr_append(&trigger->dependencies, dependency);
			}

			dependency->flags = ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED;
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	lld_trigger_dependencies_make(const zbx_vector_lld_trigger_prototype_ptr_t *trigger_prototypes,
		zbx_vector_lld_trigger_ptr_t *triggers, const zbx_vector_lld_row_ptr_t *lld_rows, char **error)
{
	const zbx_lld_trigger_prototype_t	*trigger_prototype;
	zbx_hashset_t				items_triggers;
	zbx_lld_trigger_t			*trigger;
	zbx_lld_function_t			*function;
	zbx_lld_item_trigger_t			item_trigger;
	zbx_lld_dependency_t			*dependency;
	int					ii, jj;

	for (ii = 0; ii < trigger_prototypes->values_num; ii++)
	{
		trigger_prototype = trigger_prototypes->values[ii];

		if (0 != trigger_prototype->dependencies.values_num)
			break;
	}

	for (jj = 0; jj < triggers->values_num; jj++)
	{
		trigger = triggers->values[jj];

		if (0 != trigger->dependencies.values_num)
			break;
	}

	/* all trigger prototypes and triggers have no dependencies */
	if (ii == trigger_prototypes->values_num && jj == triggers->values_num)
		return;

	/* used for fast search of trigger by item prototype */
	zbx_hashset_create(&items_triggers, 512, items_triggers_hash_func, items_triggers_compare_func);

	for (int k = 0; k < triggers->values_num; k++)
	{
		trigger = triggers->values[k];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		for (int m = 0; m < trigger->functions.values_num; m++)
		{
			function = trigger->functions.values[m];

			item_trigger.parent_triggerid = trigger->parent_triggerid;
			item_trigger.itemid = function->itemid;
			item_trigger.trigger = trigger;
			zbx_hashset_insert(&items_triggers, &item_trigger, sizeof(item_trigger));
		}
	}

	for (int k = 0; k < trigger_prototypes->values_num; k++)
	{
		trigger_prototype = trigger_prototypes->values[k];

		for (int m = 0; m < lld_rows->values_num; m++)
		{
			zbx_lld_row_t	*lld_row = lld_rows->values[m];

			lld_trigger_dependency_make(trigger_prototype, trigger_prototypes,
					&items_triggers, lld_row, error);
		}
	}

	/* marking dependencies which will be deleted */
	for (int k = 0; k < triggers->values_num; k++)
	{
		trigger = triggers->values[k];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		for (int m = 0; m < trigger->dependencies.values_num; m++)
		{
			dependency = trigger->dependencies.values[m];

			if (0 == (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED))
				dependency->flags = ZBX_FLAG_LLD_DEPENDENCY_DELETE;
		}
	}

	zbx_hashset_destroy(&items_triggers);

	zbx_vector_lld_trigger_ptr_sort(triggers, lld_trigger_compare_func);
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates trigger tag                                               *
 *                                                                            *
 ******************************************************************************/
static void	lld_trigger_tag_make(const zbx_lld_trigger_prototype_t *trigger_prototype,
		zbx_hashset_t *items_triggers, const zbx_lld_row_t *lld_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **error)
{
	zbx_lld_trigger_t	*trigger;
	zbx_vector_db_tag_ptr_t	new_tags;
	zbx_db_tag_t		*tag;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (trigger = lld_trigger_get(trigger_prototype->triggerid, items_triggers, &lld_row->item_links)))
		goto out;

	zbx_vector_db_tag_ptr_create(&new_tags);

	for (int i = 0; i < trigger_prototype->tags.values_num; i++)
	{
		tag = zbx_db_tag_create(trigger_prototype->tags.values[i]->tag,
				trigger_prototype->tags.values[i]->value);
		zbx_vector_db_tag_ptr_append(&new_tags, tag);
	}

	for (int i = 0; i < trigger->override_tags.values_num; i++)
	{
		tag = zbx_db_tag_create(trigger->override_tags.values[i]->tag,
				trigger->override_tags.values[i]->value);
		zbx_vector_db_tag_ptr_append(&new_tags, tag);
	}

	for (int i = 0; i < new_tags.values_num; i++)
	{
		zbx_substitute_lld_macros(&new_tags.values[i]->tag, &lld_row->jp_row, lld_macro_paths, ZBX_MACRO_FUNC,
				NULL, 0);
		zbx_substitute_lld_macros(&new_tags.values[i]->value, &lld_row->jp_row, lld_macro_paths, ZBX_MACRO_FUNC,
				NULL, 0);
	}

	if (SUCCEED != zbx_merge_tags(&trigger->tags, &new_tags, "trigger", error) && 0 == trigger->triggerid)
	{
		trigger->flags &= ~ZBX_FLAG_LLD_TRIGGER_DISCOVERED;
		*error = zbx_strdcatf(*error, "Cannot create trigger: tag validation failed.\n");
	}

	zbx_vector_db_tag_ptr_destroy(&new_tags);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates trigger tags                                              *
 *                                                                            *
 ******************************************************************************/
static void	lld_trigger_tags_make(const zbx_vector_lld_trigger_prototype_ptr_t *trigger_prototypes,
		zbx_vector_lld_trigger_ptr_t *triggers, const zbx_vector_lld_row_ptr_t *lld_rows,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **error)
{
	zbx_lld_trigger_prototype_t	*trigger_prototype;
	zbx_hashset_t			items_triggers;
	zbx_lld_trigger_t		*trigger;
	zbx_lld_function_t		*function;
	zbx_lld_item_trigger_t		item_trigger;

	/* used for fast search of trigger by item prototype */
	zbx_hashset_create(&items_triggers, 512, items_triggers_hash_func, items_triggers_compare_func);

	for (int i = 0; i < triggers->values_num; i++)
	{
		trigger = triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		for (int j = 0; j < trigger->functions.values_num; j++)
		{
			function = trigger->functions.values[j];

			item_trigger.parent_triggerid = trigger->parent_triggerid;
			item_trigger.itemid = function->itemid;
			item_trigger.trigger = trigger;
			zbx_hashset_insert(&items_triggers, &item_trigger, sizeof(item_trigger));
		}
	}

	for (int i = 0; i < trigger_prototypes->values_num; i++)
	{
		trigger_prototype = trigger_prototypes->values[i];

		for (int j = 0; j < lld_rows->values_num; j++)
		{
			zbx_lld_row_t	*lld_row = lld_rows->values[j];

			lld_trigger_tag_make(trigger_prototype, &items_triggers, lld_row, lld_macro_paths, error);
		}
	}

	zbx_hashset_destroy(&items_triggers);

	zbx_vector_lld_trigger_ptr_sort(triggers, lld_trigger_compare_func);
}

static void	lld_validate_trigger_field(zbx_lld_trigger_t *trigger, char **field, char **field_orig,
		zbx_uint64_t flag, size_t field_len, char **error)
{
	/* only new triggers or triggers with changed data will be validated */
	if (0 != trigger->triggerid && 0 == (trigger->flags & flag))
		return;

	if (SUCCEED != zbx_is_utf8(*field))
	{
		zbx_replace_invalid_utf8(*field);
		*error = zbx_strdcatf(*error, "Cannot %s trigger \"%s\": value \"%s\" has invalid UTF-8 sequence.\n",
				(0 != trigger->triggerid ? "update" : "create"), trigger->description, *field);
	}
	else if (zbx_strlen_utf8(*field) > field_len)
	{
		char	value_short[VALUE_ERRMSG_MAX * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

		zbx_truncate_value(*field, VALUE_ERRMSG_MAX, value_short, sizeof(value_short));

		if (0 != (flag & ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION))
		{
			*error = zbx_strdcatf(*error, "Cannot %s trigger \"%s\": name is too long.\n",
					(0 != trigger->triggerid ? "update" : "create"), value_short);
		}
		else
		{
			*error = zbx_strdcatf(*error, "Cannot %s trigger \"%s\": value \"%s\" is too long.\n",
					(0 != trigger->triggerid ? "update" : "create"), trigger->description, *field);
		}
	}
	else if (ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION == flag && '\0' == **field)
	{
		*error = zbx_strdcatf(*error, "Cannot %s trigger: name is empty.\n",
				(0 != trigger->triggerid ? "update" : "create"));
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
 * Return value: SUCCEED - if trigger description or expression has been      *
 *                         changed                                            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	lld_trigger_changed(const zbx_lld_trigger_t *trigger)
{
	zbx_lld_function_t	*function;

	if (0 == trigger->triggerid)
		return SUCCEED;

	if (0 != (trigger->flags & (ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION | ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION |
			ZBX_FLAG_LLD_TRIGGER_UPDATE_RECOVERY_EXPRESSION)))
	{
		return SUCCEED;
	}

	for (int i = 0; i < trigger->functions.values_num; i++)
	{
		function = trigger->functions.values[i];

		if (0 == function->functionid)
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Return value: SUCCEED - if descriptions and expressions of triggers are    *
 *                         identical                                          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	lld_triggers_equal(zbx_lld_trigger_t *trigger, zbx_lld_trigger_t *db_trigger)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 != strcmp(trigger->expression_ex, db_trigger->expression_ex))
		goto out;

	if (0 == strcmp(trigger->recovery_expression_ex, db_trigger->recovery_expression_ex))
		ret = SUCCEED;
out:

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	lld_trigger_name_index_add(zbx_hashset_t *name_index, zbx_lld_trigger_t *trigger)
{
	zbx_lld_trigger_name_t	*name, name_local;
	int			num = name_index->num_data;

	name_local.description = trigger->description;
	name = (zbx_lld_trigger_name_t *)zbx_hashset_insert(name_index, &name_local, sizeof(name_local));

	if (num != name_index->num_data)
		zbx_vector_lld_trigger_ptr_create(&name->triggers);

	if (NULL == trigger->expression_ex)
	{
		trigger->expression_ex = lld_trigger_expression_expand(trigger, trigger->expression,
				&trigger->functions);
	}

	if (NULL == trigger->recovery_expression_ex)
	{
		trigger->recovery_expression_ex = lld_trigger_expression_expand(trigger, trigger->recovery_expression,
				&trigger->functions);
	}

	for (int i = 0; i < name->triggers.values_num; i++)
	{
		if (SUCCEED == lld_triggers_equal(trigger, name->triggers.values[i]))
			return FAIL;
	}

	zbx_vector_lld_trigger_ptr_append(&name->triggers, trigger);

	return SUCCEED;
}

static zbx_lld_trigger_t	*lld_trigger_name_index_get(zbx_hashset_t *name_index, zbx_lld_trigger_t *trigger)
{
	zbx_lld_trigger_name_t	*name, name_local;

	name_local.description = trigger->description;
	if (NULL == (name = (zbx_lld_trigger_name_t *)zbx_hashset_search(name_index, &name_local)))
		return NULL;

	for (int i = 0; i < name->triggers.values_num; i++)
	{
		if (SUCCEED == lld_triggers_equal(trigger, name->triggers.values[i]))
			return name->triggers.values[i];
	}

	return NULL;
}


static void	lld_trigger_handle_name_conflict(zbx_lld_trigger_t *trigger, char **error)
{
	*error = zbx_strdcatf(*error, "Cannot %s trigger: trigger \"%s\" already exists.\n",
			(0 != trigger->triggerid ? "update" : "create"), trigger->description);

	if (0 != trigger->triggerid)
	{
		zbx_lld_function_t	*function;

		lld_field_str_rollback(&trigger->description, &trigger->description_orig,
				&trigger->flags, ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION);

		lld_field_str_rollback(&trigger->expression, &trigger->expression_orig,
				&trigger->flags, ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION);

		lld_field_str_rollback(&trigger->recovery_expression,
				&trigger->recovery_expression_orig, &trigger->flags,
				ZBX_FLAG_LLD_TRIGGER_UPDATE_RECOVERY_EXPRESSION);

		for (int k = 0; k < trigger->functions.values_num; k++)
		{
			function = trigger->functions.values[k];

			if (0 != function->functionid)
				function->flags &= ~ZBX_FLAG_LLD_FUNCTION_DELETE;
			else
				function->flags &= ~ZBX_FLAG_LLD_FUNCTION_DISCOVERED;
		}
	}
	else
		trigger->flags &= ~ZBX_FLAG_LLD_TRIGGER_DISCOVERED;
}

static void	lld_triggers_validate_db_description(zbx_uint64_t hostid, zbx_vector_lld_trigger_ptr_t *triggers,
		zbx_hashset_t *name_index, char **error)
{
	zbx_vector_str_t		descriptions;
	zbx_lld_trigger_t		*trigger;
	char				*sql = NULL;
	size_t				sql_alloc = 256, sql_offset = 0;
	zbx_db_large_query_t		query;
	zbx_db_row_t			row;
	zbx_vector_lld_trigger_ptr_t	db_triggers;
	zbx_lld_trigger_t		*db_trigger;
	zbx_hashset_t			trigger_functions;
	zbx_trigger_functions_t		tfuncs_local;
	zbx_uint64_t			triggerid;

	zbx_vector_str_create(&descriptions);

	for (int i = 0; i < triggers->values_num; i++)
	{
		trigger = triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		if (SUCCEED == lld_trigger_changed(trigger))
			zbx_vector_str_append(&descriptions, trigger->description);
	}

	if (0 == descriptions.values_num)
		goto out;

	zbx_hashset_create(&trigger_functions, descriptions.values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_lld_trigger_ptr_create(&db_triggers);
	zbx_vector_str_sort(&descriptions, ZBX_DEFAULT_STR_COMPARE_FUNC);
	zbx_vector_str_uniq(&descriptions, ZBX_DEFAULT_STR_COMPARE_FUNC);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.triggerid,t.description,t.expression,t.recovery_expression"
			" from triggers t,functions f,items i"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and",
			hostid);

	zbx_db_large_query_prepare_str(&query, &sql, &sql_alloc, &sql_offset, "t.description", &descriptions);
	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);

		if (NULL != zbx_hashset_search(&trigger_functions, &triggerid))
			continue;

		db_trigger = (zbx_lld_trigger_t *)zbx_malloc(NULL, sizeof(zbx_lld_trigger_t));
		memset(db_trigger, 0, sizeof(zbx_lld_trigger_t));

		db_trigger->triggerid = triggerid;
		db_trigger->description = zbx_strdup(NULL, row[1]);
		db_trigger->expression_ex = zbx_strdup(NULL, row[2]);
		db_trigger->recovery_expression_ex = zbx_strdup(NULL, row[3]);
		zbx_vector_lld_function_ptr_create(&db_trigger->functions);

		zbx_vector_lld_trigger_ptr_append(&db_triggers, db_trigger);

		tfuncs_local.triggerid = db_trigger->triggerid;
		tfuncs_local.functions = &db_trigger->functions;
		zbx_hashset_insert(&trigger_functions, &tfuncs_local, sizeof(tfuncs_local));
	}
	zbx_db_large_query_clear(&query);

	lld_trigger_functions_get(&trigger_functions);

	for (int i = 0; i < db_triggers.values_num; i++)
	{
		db_trigger = db_triggers.values[i];

		lld_trigger_expression_simplify_and_expand(db_trigger, &db_trigger->expression_ex,
				&db_trigger->functions);
		lld_trigger_expression_simplify_and_expand(db_trigger, &db_trigger->recovery_expression_ex,
				&db_trigger->functions);

		if (NULL != (trigger = lld_trigger_name_index_get(name_index, db_trigger)) &&
				0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED) &&
				db_trigger->triggerid != trigger->triggerid)
		{
			lld_trigger_handle_name_conflict(trigger, error);
		}
	}

	for (int i = 0; i < db_triggers.values_num; i++)
	{
		zbx_free(db_triggers.values[i]->description);
		zbx_free(db_triggers.values[i]->expression_ex);
		zbx_free(db_triggers.values[i]->recovery_expression_ex);

		zbx_vector_lld_function_ptr_clear_ext(&db_triggers.values[i]->functions, lld_function_free);
		zbx_vector_lld_function_ptr_destroy(&db_triggers.values[i]->functions);
		zbx_free(db_triggers.values[i]);
	}
	zbx_vector_lld_trigger_ptr_destroy(&db_triggers);

	zbx_free(sql);
	zbx_hashset_destroy(&trigger_functions);
out:
	zbx_vector_str_destroy(&descriptions);
}

/******************************************************************************
 *                                                                            *
 * Parameters:                                                                *
 *   hostid   - [IN]                                                          *
 *   triggers - [IN] sorted list of triggers                                  *
 *   error    - [OUT]                                                         *
 *                                                                            *
 ******************************************************************************/
static void	lld_triggers_validate(zbx_uint64_t hostid, zbx_vector_lld_trigger_ptr_t *triggers, char **error)
{
	zbx_lld_trigger_t	*trigger;
	zbx_vector_uint64_t	triggerids;
	zbx_hashset_t		name_index;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&triggerids);
	zbx_hashset_create(&name_index, triggers->values_num, lld_trigger_name_hash, lld_trigger_name_compare);

	/* checking a validity of the fields */

	for (int i = 0; i < triggers->values_num; i++)
	{
		trigger = triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		lld_validate_trigger_field(trigger, &trigger->description, &trigger->description_orig,
				ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION, TRIGGER_DESCRIPTION_LEN, error);
		lld_validate_trigger_field(trigger, &trigger->comments, &trigger->comments_orig,
				ZBX_FLAG_LLD_TRIGGER_UPDATE_COMMENTS, TRIGGER_COMMENTS_LEN, error);
		lld_validate_trigger_field(trigger, &trigger->url, &trigger->url_orig,
				ZBX_FLAG_LLD_TRIGGER_UPDATE_URL, TRIGGER_URL_LEN, error);
		lld_validate_trigger_field(trigger, &trigger->url_name, &trigger->url_name_orig,
				ZBX_FLAG_LLD_TRIGGER_UPDATE_URL_NAME, TRIGGER_URL_NAME_LEN, error);
		lld_validate_trigger_field(trigger, &trigger->correlation_tag, &trigger->correlation_tag_orig,
				ZBX_FLAG_LLD_TRIGGER_UPDATE_CORRELATION_TAG, ZBX_DB_TAG_NAME_LEN, error);
		lld_validate_trigger_field(trigger, &trigger->opdata, &trigger->opdata_orig,
				ZBX_FLAG_LLD_TRIGGER_UPDATE_OPDATA, TRIGGER_OPDATA_LEN, error);
		lld_validate_trigger_field(trigger, &trigger->event_name, &trigger->event_name_orig,
				ZBX_FLAG_LLD_TRIGGER_UPDATE_EVENT_NAME, TRIGGER_EVENT_NAME_LEN, error);

		/* index existing triggers without pending name/expression changes */
		if (SUCCEED != lld_trigger_changed(trigger))
			(void)lld_trigger_name_index_add(&name_index, trigger);
	}

	/* check for duplicated triggers in discovery data */
	for (int i = 0; i < triggers->values_num; i++)
	{
		trigger = triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		if (SUCCEED != lld_trigger_changed(trigger))
			continue;

		if (SUCCEED != lld_trigger_name_index_add(&name_index, trigger))
			lld_trigger_handle_name_conflict(trigger, error);
	}

	/* check duplicated triggers in DB */
	lld_triggers_validate_db_description(hostid, triggers, &name_index, error);

	/* clear name_index contents */

	zbx_hashset_iter_t	iter;
	zbx_lld_trigger_name_t	*name;

	zbx_hashset_iter_reset(&name_index, &iter);
	while (NULL != (name = (zbx_lld_trigger_name_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_lld_trigger_ptr_destroy(&name->triggers);

	zbx_hashset_destroy(&name_index);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: transforms simple trigger expression to DB format                 *
 *                                                                            *
 * Example:                                                                   *
 *                                                                            *
 *     "{1} > 5" => "{84756} > 5"                                             *
 *       ^            ^                                                       *
 *       |            functionid from the database                            *
 *       internal function index                                              *
 *                                                                            *
 ******************************************************************************/
static int	lld_expression_create(const zbx_lld_trigger_t *trigger, char **expression,
		const zbx_vector_lld_function_ptr_t *functions)
{
	int			ret = FAIL;
	zbx_uint64_t		function_index;
	zbx_eval_context_t	ctx;
	char			*errmsg = NULL, *new_expression = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:'%s'", __func__, *expression);

	if ('\0' == **expression)
	{
		ret = SUCCEED;
		goto out;
	}

	if (SUCCEED != zbx_eval_parse_expression(&ctx, *expression, ZBX_EVAL_TRIGGER_EXPRESSION_LLD, &errmsg))
	{
		const char	*type;

		type = (*expression == trigger->expression ? "" : " recovery");
		zabbix_log(LOG_LEVEL_DEBUG, "Invalid trigger \"%s\"%s expression: %s", trigger->description, type,
				errmsg);
		zbx_free(errmsg);

		THIS_SHOULD_NEVER_HAPPEN;

		goto out;
	}

	for (int i = 0; i < ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx.stack.values[i];

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
			continue;

		if (SUCCEED != zbx_is_uint64_n(ctx.expression + token->loc.l + 1, token->loc.r - token->loc.l - 1,
				&function_index))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		for (int j = 0; j < functions->values_num; j++)
		{
			const zbx_lld_function_t	*function = functions->values[j];

			if (0 != (ZBX_FLAG_LLD_FUNCTION_DELETE & function->flags))
				continue;

			if (function->index == function_index)
			{
				zbx_variant_set_ui64(&token->value, function->functionid);
				break;
			}
		}
	}

	zbx_eval_compose_expression(&ctx, &new_expression);
	zbx_free(*expression);
	*expression = new_expression;

	zbx_eval_clear(&ctx);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() expression:'%s'", __func__, *expression);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds or updates triggers in database based on discovery rule      *
 *                                                                            *
 * Parameters: hostid             - [IN] parent host id                       *
 *             trigger_prototypes - [IN]                                      *
 *             triggers           - [IN/OUT] triggers to save                 *
 *                                                                            *
 * Return value: SUCCEED - if triggers was successfully saved or saving       *
 *                         was not necessary                                  *
 *               FAIL    - triggers cannot be saved                           *
 *                                                                            *
 ******************************************************************************/
static int	lld_triggers_save(zbx_uint64_t hostid, const zbx_vector_lld_trigger_prototype_ptr_t *trigger_prototypes,
		const zbx_vector_lld_trigger_ptr_t *triggers)
{
	int					ret = SUCCEED, new_triggers = 0, upd_triggers = 0,
						new_functions = 0, new_dependencies = 0, new_tags = 0, upd_tags = 0;
	const zbx_lld_trigger_prototype_t	*trigger_prototype;
	zbx_lld_trigger_t			*trigger;
	zbx_lld_function_t			*function;
	zbx_lld_dependency_t			*dependency;
	zbx_db_tag_t				*tag;
	zbx_vector_uint64_t			del_functionids, del_triggerdepids, del_triggertagids, trigger_protoids;
	zbx_uint64_t				triggerid = 0, functionid = 0, triggerdepid = 0, triggerid_up,
						triggertagid;
	char					*sql = NULL;
	size_t					sql_alloc = 8 * ZBX_KIBIBYTE, sql_offset = 0;
	zbx_db_insert_t				db_insert, db_insert_tdiscovery, db_insert_tfunctions,
						db_insert_tdepends, db_insert_ttags;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&del_functionids);
	zbx_vector_uint64_create(&del_triggerdepids);
	zbx_vector_uint64_create(&del_triggertagids);
	zbx_vector_uint64_create(&trigger_protoids);

	for (int i = 0; i < triggers->values_num; i++)
	{
		trigger = triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		if (0 == trigger->triggerid)
			new_triggers++;
		else if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE))
			upd_triggers++;

		if (0 != trigger->triggerid)
		{
			zbx_audit_trigger_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE,
					trigger->triggerid, (NULL == trigger->description_orig) ? trigger->description :
					trigger->description_orig, ZBX_FLAG_DISCOVERY_CREATED);
		}

		for (int j = 0; j < trigger->functions.values_num; j++)
		{
			function = trigger->functions.values[j];

			if (0 != (function->flags & ZBX_FLAG_LLD_FUNCTION_DELETE))
			{
				zbx_vector_uint64_append(&del_functionids, function->functionid);
				continue;
			}

			if (0 == (function->flags & ZBX_FLAG_LLD_FUNCTION_DISCOVERED))
				continue;

			if (0 == function->functionid)
				new_functions++;
		}

		for (int j = 0; j < trigger->dependencies.values_num; j++)
		{
			dependency = trigger->dependencies.values[j];

			if (0 != (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DELETE))
			{
				zbx_vector_uint64_append(&del_triggerdepids, dependency->triggerdepid);

				zbx_audit_trigger_update_json_remove_dependency(ZBX_AUDIT_LLD_CONTEXT,
						ZBX_FLAG_DISCOVERY_NORMAL, dependency->triggerdepid,
						trigger->triggerid);
				continue;
			}

			if (0 == (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED))
				continue;

			if (0 == dependency->triggerdepid)
				new_dependencies++;
		}

		for (int j = 0; j < trigger->tags.values_num; j++)
		{
			tag = trigger->tags.values[j];

			if (0 != (tag->flags & ZBX_FLAG_DB_TAG_REMOVE))
			{
				zbx_vector_uint64_append(&del_triggertagids, tag->tagid);

				zbx_audit_trigger_update_json_delete_tags(ZBX_AUDIT_LLD_CONTEXT, trigger->triggerid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, tag->tagid);
				continue;
			}

			if (0 == tag->tagid)
				new_tags++;
			else if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE))
				upd_tags++;
		}
	}

	if (0 == new_triggers && 0 == new_functions && 0 == new_dependencies && 0 == upd_triggers &&
			0 == del_functionids.values_num && 0 == del_triggerdepids.values_num && 0 == new_tags &&
			0 == upd_tags && 0 == del_triggertagids.values_num)
	{
		goto out;
	}

	zbx_db_begin();

	for (int i = 0; i < trigger_prototypes->values_num; i++)
	{
		trigger_prototype = trigger_prototypes->values[i];
		zbx_vector_uint64_append(&trigger_protoids, trigger_prototype->triggerid);
	}

	if (0 != new_functions)
	{
		functionid = zbx_db_get_maxid_num("functions", new_functions);

		zbx_db_insert_prepare(&db_insert_tfunctions, "functions", "functionid", "itemid", "triggerid",
				"name", "parameter", (char *)NULL);
	}

	if (0 != new_triggers)
	{
		triggerid = zbx_db_get_maxid_num("triggers", new_triggers);

		zbx_db_insert_prepare(&db_insert, "triggers", "triggerid", "description", "expression", "priority",
				"status", "comments", "url", "url_name", "type", "value", "state", "flags",
				"recovery_mode", "recovery_expression", "correlation_mode", "correlation_tag",
				"manual_close", "opdata", "event_name", (char *)NULL);

		zbx_db_insert_prepare(&db_insert_tdiscovery, "trigger_discovery", "triggerid", "parent_triggerid",
				"lastcheck", (char *)NULL);
	}

	if (0 != new_tags)
	{
		triggertagid = zbx_db_get_maxid_num("trigger_tag", new_tags);

		zbx_db_insert_prepare(&db_insert_ttags, "trigger_tag", "triggertagid", "triggerid", "tag", "value",
				(char *)NULL);
	}

	if (0 != new_dependencies)
	{
		triggerdepid = zbx_db_get_maxid_num("trigger_depends", new_dependencies);

		zbx_db_insert_prepare(&db_insert_tdepends, "trigger_depends", "triggerdepid", "triggerid_down",
				"triggerid_up", (char *)NULL);
	}

	if (SUCCEED != zbx_db_lock_hostid(hostid) || SUCCEED != zbx_db_lock_triggerids(&trigger_protoids))
	{
		/* the host or trigger prototype was removed while processing lld rule */
		zbx_db_rollback();
		ret = FAIL;
		goto out;
	}

	if (0 != upd_triggers || 0 != del_functionids.values_num ||
			0 != del_triggerdepids.values_num || 0 != upd_tags || 0 != del_triggertagids.values_num)
	{
		sql = (char *)zbx_malloc(sql, sql_alloc);
	}

	for (int i = 0; i < triggers->values_num; i++)
	{
		int	index;

		trigger = triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		zbx_lld_trigger_prototype_t	cmp = {.triggerid = trigger->parent_triggerid};

		index = zbx_vector_lld_trigger_prototype_ptr_bsearch(trigger_prototypes, &cmp,
				lld_trigger_prototype_compare_func);

		trigger_prototype = trigger_prototypes->values[index];

		for (int j = 0; j < trigger->functions.values_num; j++)
		{
			function = trigger->functions.values[j];

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
		{
			if (FAIL == lld_expression_create(trigger, &trigger->expression, &trigger->functions))
			{
				/* further updates will fail, so there is unnecessary overhead, */
				/* but lld_expression_create() can fail only because of bugs,   */
				/* so better to leave unoptimized handling of 'impossible'      */
				/* errors than unnecessary complicate code                      */
				ret = FAIL;
				goto cleanup;
			}
		}

		if (0 == trigger->triggerid || 0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_RECOVERY_EXPRESSION))
		{
			if (FAIL == lld_expression_create(trigger, &trigger->recovery_expression, &trigger->functions))
			{
				ret = FAIL;
				goto cleanup;
			}
		}

		if (0 == trigger->triggerid)
		{
			zbx_db_insert_add_values(&db_insert, triggerid, trigger->description, trigger->expression,
					(int)trigger->priority, (int)trigger->status,
					trigger->comments, trigger->url, trigger->url_name,
					(int)trigger_prototype->type, (int)TRIGGER_VALUE_OK, (int)TRIGGER_STATE_NORMAL,
					(int)ZBX_FLAG_DISCOVERY_CREATED, (int)trigger_prototype->recovery_mode,
					trigger->recovery_expression, (int)trigger_prototype->correlation_mode,
					trigger->correlation_tag, (int)trigger_prototype->manual_close,
					trigger->opdata, trigger->event_name);

			zbx_audit_trigger_create_entry(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_ADD,triggerid,
					trigger->description, ZBX_FLAG_DISCOVERY_CREATED);

			zbx_audit_trigger_update_json_add_data(ZBX_AUDIT_LLD_CONTEXT, triggerid, 0,
					(int)trigger_prototype->recovery_mode, trigger->status, trigger_prototype->type,
					TRIGGER_VALUE_OK, TRIGGER_STATE_NORMAL, trigger->priority, trigger->comments,
					trigger->url, trigger->url_name, ZBX_FLAG_DISCOVERY_CREATED,
					trigger_prototype->correlation_mode, trigger->correlation_tag,
					trigger_prototype->manual_close, trigger->opdata, 0, trigger->event_name);

			zbx_audit_trigger_update_json_add_expr(ZBX_AUDIT_LLD_CONTEXT, triggerid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, trigger->expression);

			zbx_audit_trigger_update_json_add_rexpr(ZBX_AUDIT_LLD_CONTEXT, triggerid,
					(int)ZBX_FLAG_DISCOVERY_CREATED, trigger->recovery_expression);

			zbx_db_insert_add_values(&db_insert_tdiscovery, triggerid, trigger->parent_triggerid,
					trigger->lastcheck);

			trigger->triggerid = triggerid++;
		}
		else if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE))
		{
			const char	*d = "";
			char	*description_esc, *expression_esc, *comments_esc, *url_esc, *url_name_esc, *value_esc,
				*opdata_esc, *event_name_esc;

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set ");

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_DESCRIPTION))
			{
				description_esc = zbx_db_dyn_escape_string(trigger->description);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "description='%s'",
						description_esc);
				zbx_free(description_esc);
				d = ",";

				zbx_audit_trigger_update_json_update_description(ZBX_AUDIT_LLD_CONTEXT,
						trigger->triggerid, (int)ZBX_FLAG_DISCOVERY_CREATED,
						trigger->description_orig, trigger->description);
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_EXPRESSION))
			{
				expression_esc = zbx_db_dyn_escape_string(trigger->expression);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sexpression='%s'", d,
						expression_esc);
				zbx_free(expression_esc);
				d = ",";

				lld_expression_create(trigger, &trigger->expression_orig, &trigger->functions);
				zbx_audit_trigger_update_json_update_expression(ZBX_AUDIT_LLD_CONTEXT,
						trigger->triggerid, (int)ZBX_FLAG_DISCOVERY_CREATED,
						trigger->expression_orig, trigger->expression);
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_RECOVERY_EXPRESSION))
			{
				expression_esc = zbx_db_dyn_escape_string(trigger->recovery_expression);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%srecovery_expression='%s'", d,
						expression_esc);
				zbx_free(expression_esc);
				d = ",";

				lld_expression_create(trigger, &trigger->recovery_expression_orig, &trigger->functions);
				zbx_audit_trigger_update_json_update_recovery_expression(ZBX_AUDIT_LLD_CONTEXT,
						trigger->triggerid, (int)ZBX_FLAG_DISCOVERY_CREATED,
						trigger->recovery_expression_orig, trigger->recovery_expression);
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_RECOVERY_MODE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%srecovery_mode=%d", d,
						(int)trigger_prototype->recovery_mode);
				d = ",";

				zbx_audit_trigger_update_json_update_recovery_mode(ZBX_AUDIT_LLD_CONTEXT,
						trigger->triggerid, (int)ZBX_FLAG_DISCOVERY_CREATED,
						(int)trigger->recovery_mode_orig,
						(int)trigger_prototype->recovery_mode);
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%stype=%d", d,
						(int)trigger_prototype->type);
				d = ",";

				zbx_audit_trigger_update_json_update_type(ZBX_AUDIT_LLD_CONTEXT, trigger->triggerid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, (int)trigger->type_orig,
						(int)trigger_prototype->type);
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_PRIORITY))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%spriority=%d", d,
						(int)trigger->priority);
				d = ",";

				zbx_audit_trigger_update_json_update_priority(ZBX_AUDIT_LLD_CONTEXT, trigger->triggerid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, (int)trigger->priority_orig,
						(int)trigger->priority);
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_COMMENTS))
			{
				comments_esc = zbx_db_dyn_escape_string(trigger->comments);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scomments='%s'", d, comments_esc);
				zbx_free(comments_esc);
				d = ",";

				zbx_audit_trigger_update_json_update_comments(ZBX_AUDIT_LLD_CONTEXT, trigger->triggerid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, trigger->comments_orig,
						trigger->comments);
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_URL))
			{
				url_esc = zbx_db_dyn_escape_string(trigger->url);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%surl='%s'", d, url_esc);
				zbx_free(url_esc);
				d = ",";

				zbx_audit_trigger_update_json_update_url(ZBX_AUDIT_LLD_CONTEXT, trigger->triggerid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, trigger->url_orig, trigger->url);
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_URL_NAME))
			{
				url_name_esc = zbx_db_dyn_escape_string(trigger->url_name);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%surl_name='%s'", d, url_name_esc);
				zbx_free(url_name_esc);
				d = ",";

				zbx_audit_trigger_update_json_update_url_name(ZBX_AUDIT_LLD_CONTEXT, trigger->triggerid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, trigger->url_name_orig,
						trigger->url_name);
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_CORRELATION_MODE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scorrelation_mode=%d", d,
						(int)trigger_prototype->correlation_mode);
				d = ",";

				zbx_audit_trigger_update_json_update_correlation_mode(ZBX_AUDIT_LLD_CONTEXT,
						trigger->triggerid, (int)ZBX_FLAG_DISCOVERY_CREATED,
						(int)trigger->correlation_mode_orig,
						(int)trigger_prototype->correlation_mode);
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_CORRELATION_TAG))
			{
				value_esc = zbx_db_dyn_escape_string(trigger->correlation_tag);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%scorrelation_tag='%s'", d,
						value_esc);
				zbx_free(value_esc);
				d = ",";

				zbx_audit_trigger_update_json_update_correlation_tag(ZBX_AUDIT_LLD_CONTEXT,
						trigger->triggerid, (int)ZBX_FLAG_DISCOVERY_CREATED,
						trigger->correlation_tag_orig, trigger->correlation_tag);
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_MANUAL_CLOSE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%smanual_close=%d", d,
						(int)trigger_prototype->manual_close);
				d = ",";

				zbx_audit_trigger_update_json_update_manual_close(ZBX_AUDIT_LLD_CONTEXT,
						trigger->triggerid, (int)ZBX_FLAG_DISCOVERY_CREATED,
						(int)trigger->manual_close_orig, (int)trigger_prototype->manual_close);
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_OPDATA))
			{
				opdata_esc = zbx_db_dyn_escape_string(trigger->opdata);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sopdata='%s'", d, opdata_esc);
				zbx_free(opdata_esc);
				d = ",";

				zbx_audit_trigger_update_json_update_opdata(ZBX_AUDIT_LLD_CONTEXT, trigger->triggerid,
						(int)ZBX_FLAG_DISCOVERY_CREATED, trigger->opdata_orig, trigger->opdata);
			}

			if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_UPDATE_EVENT_NAME))
			{
				event_name_esc = zbx_db_dyn_escape_string(trigger->event_name);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%sevent_name='%s'", d,
						event_name_esc);
				zbx_free(event_name_esc);

				zbx_audit_trigger_update_json_update_event_name(ZBX_AUDIT_LLD_CONTEXT,
						trigger->triggerid, (int)ZBX_FLAG_DISCOVERY_CREATED,
						trigger->event_name_orig, trigger->event_name);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					" where triggerid=" ZBX_FS_UI64 ";\n", trigger->triggerid);
		}
	}

	for (int i = 0; i < triggers->values_num; i++)
	{
		trigger = triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		for (int j = 0; j < trigger->dependencies.values_num; j++)
		{
			dependency = trigger->dependencies.values[j];

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

				zbx_audit_trigger_update_json_add_dependency(ZBX_AUDIT_LLD_CONTEXT,
						(int)ZBX_FLAG_DISCOVERY_CREATED, triggerdepid, trigger->triggerid,
						triggerid_up);

				dependency->triggerdepid = triggerdepid++;
			}
		}
	}

	/* create/update trigger tags */
	for (int i = 0; i < triggers->values_num; i++)
	{
		trigger = triggers->values[i];

		if (0 == (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
			continue;

		for (int j = 0; j < trigger->tags.values_num; j++)
		{
			char	*value_esc;

			tag = trigger->tags.values[j];

			if (0 != (tag->flags & ZBX_FLAG_DB_TAG_REMOVE))
				continue;

			if (0 == tag->tagid)
			{
				tag->tagid = triggertagid++;
				zbx_db_insert_add_values(&db_insert_ttags, tag->tagid, trigger->triggerid,
						tag->tag, tag->value);

				zbx_audit_trigger_update_json_add_tags_and_values(ZBX_AUDIT_LLD_CONTEXT,
						trigger->triggerid, (int)ZBX_FLAG_DISCOVERY_CREATED, tag->tagid,
						tag->tag, tag->value);
			}
			else if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE))
			{
				const char	*d = "";

				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update trigger_tag set ");

				if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_TAG))
				{
					value_esc = zbx_db_dyn_escape_string(tag->tag);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "tag='%s'", value_esc);
					zbx_free(value_esc);
					d = ",";

					zbx_audit_trigger_update_json_update_tag_tag(ZBX_AUDIT_LLD_CONTEXT,
							trigger->triggerid, ZBX_FLAG_DISCOVERY_CREATED, tag->tagid,
							tag->tag_orig, tag->tag);
				}

				if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_VALUE))
				{
					value_esc = zbx_db_dyn_escape_string(tag->value);
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%svalue='%s'", d, value_esc);
					zbx_free(value_esc);

					zbx_audit_trigger_update_json_update_tag_value(ZBX_AUDIT_LLD_CONTEXT,
							trigger->triggerid, ZBX_FLAG_DISCOVERY_CREATED, tag->tagid,
							tag->value_orig, tag->value);
				}

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						" where triggertagid=" ZBX_FS_UI64 ";\n", tag->tagid);
			}
		}
	}

	if (0 != del_functionids.values_num)
	{
		zbx_vector_uint64_sort(&del_functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from functions where", "functionid", &del_functionids);
	}

	if (0 != del_triggerdepids.values_num)
	{
		zbx_vector_uint64_sort(&del_triggerdepids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from trigger_depends where", "triggerdepid", &del_triggerdepids);
	}

	if (0 != del_triggertagids.values_num)
	{
		zbx_vector_uint64_sort(&del_triggertagids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from trigger_tag where", "triggertagid", &del_triggertagids);
	}

	if (0 != upd_triggers || 0 != upd_tags)
	{
		zbx_db_execute("%s", sql);
	}
cleanup:
	zbx_free(sql);

	if (0 != new_triggers)
	{
		if (ret == SUCCEED)
			zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		if (ret == SUCCEED)
			zbx_db_insert_execute(&db_insert_tdiscovery);
		zbx_db_insert_clean(&db_insert_tdiscovery);
	}

	if (0 != new_functions)
	{
		if (ret == SUCCEED)
			zbx_db_insert_execute(&db_insert_tfunctions);
		zbx_db_insert_clean(&db_insert_tfunctions);
	}

	if (0 != new_dependencies)
	{
		if (ret == SUCCEED)
			zbx_db_insert_execute(&db_insert_tdepends);
		zbx_db_insert_clean(&db_insert_tdepends);
	}

	if (0 != new_tags)
	{
		if (ret == SUCCEED)
			zbx_db_insert_execute(&db_insert_ttags);
		zbx_db_insert_clean(&db_insert_ttags);
	}

	if (ret == SUCCEED)
		zbx_db_commit();
	else
		zbx_db_rollback();
out:
	zbx_vector_uint64_destroy(&trigger_protoids);
	zbx_vector_uint64_destroy(&del_triggertagids);
	zbx_vector_uint64_destroy(&del_triggerdepids);
	zbx_vector_uint64_destroy(&del_functionids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}


/*******************************************************************************************
 *                                                                                         *
 * Purpose: hash/comparison functions to support cache/vector lookups by trigger reference *
 *                                                                                         *
 *******************************************************************************************/
static zbx_hash_t	zbx_lld_trigger_ref_hash_func(const void *data)
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

static int	zbx_lld_trigger_ref_compare_func(const void *d1, const void *d2)
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

/*******************************************************************************************
 *                                                                                         *
 * comparison function to determine trigger dependency validation order                    *
 *                                                                                         *
 *******************************************************************************************/
static int	zbx_lld_trigger_node_compare_func(const void *d1, const void *d2)
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
 * Purpose: adds node to trigger cache                                        *
 *                                                                            *
 * Parameters: cache     - [IN] trigger cache                                 *
 *             triggerid - [IN]                                               *
 *             trigger   - [IN] trigger data for new triggers                 *
 *                                                                            *
 * Return value: added node                                                   *
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

	zbx_vector_lld_trigger_ref_ptr_create(&node_local.dependencies);

	return (zbx_lld_trigger_node_t *)zbx_hashset_insert(cache, &node_local, sizeof(node_local));
}

/******************************************************************************
 *                                                                            *
 * Purpose: Adds trigger and all triggers related to it to trigger dependency *
 *          validation cache.                                                 *
 *                                                                            *
 * Parameters: cache           - [IN] trigger cache                           *
 *             trigger         - [IN] trigger to add                          *
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

	trigger_node_local.trigger_ref.triggerid = trigger->triggerid;
	trigger_node_local.trigger_ref.trigger = trigger;

	if (NULL != zbx_hashset_search(cache, &trigger_node_local))
		return;

	trigger_node = lld_trigger_cache_append(cache, trigger->triggerid, trigger);

	for (int i = 0; i < trigger->dependencies.values_num; i++)
	{
		dependency = trigger->dependencies.values[i];

		if (0 == (dependency->flags & ZBX_FLAG_LLD_DEPENDENCY_DISCOVERED))
			continue;

		trigger_ref = (zbx_lld_trigger_ref_t *)zbx_malloc(NULL, sizeof(zbx_lld_trigger_ref_t));

		trigger_ref->triggerid = dependency->triggerid_up;
		trigger_ref->trigger = dependency->trigger_up;
		trigger_ref->flags = (0 == dependency->triggerdepid ? ZBX_LLD_TRIGGER_DEPENDENCY_NEW :
				ZBX_LLD_TRIGGER_DEPENDENCY_NORMAL);

		zbx_vector_lld_trigger_ref_ptr_append(&trigger_node->dependencies, trigger_ref);

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

	for (int i = 0; i < trigger->dependents.values_num; i++)
	{
		lld_trigger_cache_add_trigger_node(cache, trigger->dependents.values[i], triggerids_up,
				triggerids_down);
	}

	for (int i = 0; i < trigger->dependencies.values_num; i++)
	{
		dependency = trigger->dependencies.values[i];

		if (NULL != dependency->trigger_up)
		{
			lld_trigger_cache_add_trigger_node(cache, dependency->trigger_up, triggerids_up,
					triggerids_down);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Initializes trigger cache used to perform trigger dependency      *
 *          validation.                                                       *
 *                                                                            *
 * Parameters: cache    - [IN] trigger cache                                  *
 *             triggers - [IN] discovered triggers                            *
 *                                                                            *
 * Comments: Triggers with new dependencies and.all triggers related to them  *
 *           are added to cache.                                              *
 *                                                                            *
 ******************************************************************************/
static void	lld_trigger_cache_init(zbx_hashset_t *cache, zbx_vector_lld_trigger_ptr_t *triggers)
{
	zbx_vector_uint64_t	triggerids_up, triggerids_down;
	int			i, j;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_lld_trigger_ref_t	*trigger_ref;
	zbx_lld_trigger_node_t	*trigger_node, trigger_node_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_create(cache, (size_t)triggers->values_num, zbx_lld_trigger_ref_hash_func,
			zbx_lld_trigger_ref_compare_func);

	zbx_vector_uint64_create(&triggerids_down);
	zbx_vector_uint64_create(&triggerids_up);

	/* add all triggers with new dependencies to trigger cache */
	for (i = 0; i < triggers->values_num; i++)
	{
		zbx_lld_trigger_t	*trigger = triggers->values[i];

		for (j = 0; j < trigger->dependencies.values_num; j++)
		{
			zbx_lld_dependency_t	*dependency = trigger->dependencies.values[j];

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
			zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "td.triggerid_down",
					triggerids_down.values, triggerids_down.values_num);

			zbx_vector_uint64_clear(&triggerids_down);

			result = zbx_db_select("%s", sql);

			while (NULL != (row = zbx_db_fetch(result)))
			{
				int			new_node = 0;
				zbx_lld_trigger_node_t	*trigger_node_up;

				ZBX_STR2UINT64(trigger_node_local.trigger_ref.triggerid, row[1]);

				if (NULL == (trigger_node_up = (zbx_lld_trigger_node_t *)zbx_hashset_search(cache,
						&trigger_node_local)))
				{
					trigger_node_up = lld_trigger_cache_append(cache,
							trigger_node_local.trigger_ref.triggerid, NULL);
					new_node = 1;
				}

				ZBX_STR2UINT64(trigger_node_local.trigger_ref.triggerid, row[0]);

				if (NULL == (trigger_node = (zbx_lld_trigger_node_t *)zbx_hashset_search(cache,
						&trigger_node_local)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
				}

				/* check if the dependency is not already registered in cache */
				for (i = 0; i < trigger_node->dependencies.values_num; i++)
				{
					trigger_ref = trigger_node->dependencies.values[i];

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

					zbx_vector_lld_trigger_ref_ptr_append(&trigger_node->dependencies, trigger_ref);

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

			zbx_db_free_result(result);
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
			zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "td.triggerid_up",
					triggerids_up.values, triggerids_up.values_num);

			zbx_vector_uint64_clear(&triggerids_up);

			result = zbx_db_select("%s", sql);

			while (NULL != (row = zbx_db_fetch(result)))
			{
				ZBX_STR2UINT64(trigger_node_local.trigger_ref.triggerid, row[0]);

				if (NULL != zbx_hashset_search(cache, &trigger_node_local))
					continue;

				lld_trigger_cache_append(cache, trigger_node_local.trigger_ref.triggerid, NULL);

				zbx_vector_uint64_append(&triggerids_up, trigger_node_local.trigger_ref.triggerid);
				zbx_vector_uint64_append(&triggerids_down, trigger_node_local.trigger_ref.triggerid);
			}

			zbx_db_free_result(result);
		}
	}

	zbx_free(sql);

	zbx_vector_uint64_destroy(&triggerids_up);
	zbx_vector_uint64_destroy(&triggerids_down);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: releases resources allocated by trigger cache validation          *
 *                                                                            *
 * Parameters: cache - [IN] trigger cache                                     *
 *                                                                            *
 ******************************************************************************/
static void	zbx_trigger_cache_clean(zbx_hashset_t *cache)
{
	zbx_hashset_iter_t	iter;
	zbx_lld_trigger_node_t	*trigger_node;

	zbx_hashset_iter_reset(cache, &iter);
	while (NULL != (trigger_node = (zbx_lld_trigger_node_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_lld_trigger_ref_ptr_clear_ext(&trigger_node->dependencies, lld_trigger_ref_free);
		zbx_vector_lld_trigger_ref_ptr_destroy(&trigger_node->dependencies);
	}

	zbx_hashset_destroy(cache);
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes trigger dependency                                        *
 *                                                                            *
 * Parameters: from  - [IN] reference to dependent trigger                    *
 *             to    - [IN] reference to trigger the 'from' depends on        *
 *             error - [OUT] error message                                    *
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
		zbx_lld_dependency_t	*dependency = trigger->dependencies.values[i];

		if ((NULL != dependency->trigger_up && dependency->trigger_up == to->trigger) ||
				(0 != dependency->triggerid_up && dependency->triggerid_up == to->triggerid))
		{
			zbx_free(dependency);
			zbx_vector_lld_dependency_ptr_remove(&trigger->dependencies, i);

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
 * Purpose: iterates through trigger dependencies to find dependency loops    *
 *                                                                            *
 * Parameters: cache         - [IN] trigger cache                             *
 *             trigger_node  - [IN] trigger to check                          *
 *             iter          - [IN] dependency iterator                       *
 *             level         - [IN] dependency level                          *
 *             error         - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - trigger's dependency chain is valid                *
 *               FAIL    - dependency loop was detected                       *
 *                                                                            *
 * Comments: If the validation fails the offending dependency is removed.     *
 *                                                                            *
 ******************************************************************************/
static int	lld_trigger_dependencies_iter(zbx_hashset_t *cache, zbx_lld_trigger_node_t *trigger_node,
		zbx_lld_trigger_node_iter_t *iter, int level, char **error)
{
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

	for (int i = 0; i < trigger_node->dependencies.values_num; i++)
	{
		trigger_ref = trigger_node->dependencies.values[i];

		/* skip dependencies marked as deleted */
		if (ZBX_LLD_TRIGGER_DEPENDENCY_DELETE == trigger_ref->flags)
			continue;

		if (NULL == (trigger_node_up = (zbx_lld_trigger_node_t *)zbx_hashset_search(cache, trigger_ref)))
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

		if (FAIL == lld_trigger_dependencies_iter(cache, trigger_node_up, piter, level + 1, error))
			return FAIL;
	}

	trigger_node->iter_num = 0;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates discovered trigger dependencies                         *
 *                                                                            *
 * Parameters: triggers - [IN] discovered triggers                            *
 *             error    - [OUT] error message                                 *
 *                                                                            *
 * Comments: During validation the dependency loops will be resolved by       *
 *           removing offending dependencies.                                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_trigger_dependencies_validate(zbx_vector_lld_trigger_ptr_t *triggers, char **error)
{
	zbx_hashset_t				cache;
	zbx_hashset_iter_t			iter;
	zbx_lld_trigger_node_t			*trigger_node, *trigger_node_up;
	zbx_lld_trigger_node_iter_t		node_iter = {0};
	zbx_vector_lld_trigger_node_ptr_t	nodes;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	lld_trigger_cache_init(&cache, triggers);

	/* Perform dependency validation in the order of trigger ids and starting with parentless triggers. */
	/* This will give some consistency in choosing what dependencies should be deleted in the case of   */
	/* recursion.                                                                                       */
	zbx_vector_lld_trigger_node_ptr_create(&nodes);
	zbx_vector_lld_trigger_node_ptr_reserve(&nodes, (size_t)cache.num_data);

	zbx_hashset_iter_reset(&cache, &iter);
	while (NULL != (trigger_node = (zbx_lld_trigger_node_t *)zbx_hashset_iter_next(&iter)))
	{
		for (int i = 0; i < trigger_node->dependencies.values_num; i++)
		{
			if (NULL == (trigger_node_up = (zbx_lld_trigger_node_t *)zbx_hashset_search(&cache,
					trigger_node->dependencies.values[i])))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			trigger_node_up->parents++;
		}
		zbx_vector_lld_trigger_node_ptr_append(&nodes, trigger_node);
	}

	zbx_vector_lld_trigger_node_ptr_sort(&nodes, zbx_lld_trigger_node_compare_func);

	for (int i = 0; i < nodes.values_num; i++)
	{
		if (NULL == (trigger_node = (zbx_lld_trigger_node_t *)zbx_hashset_search(&cache,
				(zbx_lld_trigger_node_t *)nodes.values[i])))
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
		while (SUCCEED != lld_trigger_dependencies_iter(&cache, trigger_node, &node_iter, 0, error));
	}

	zbx_vector_lld_trigger_node_ptr_destroy(&nodes);
	zbx_trigger_cache_clean(&cache);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static	int	get_trigger_status_value(int status)
{
	if (ZBX_LLD_OBJECT_STATUS_ENABLED == status)
		return TRIGGER_STATUS_ENABLED;

	return TRIGGER_STATUS_DISABLED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process lost trigger resources                                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_process_lost_triggers(zbx_vector_lld_trigger_ptr_t *triggers, const zbx_lld_lifetime_t *lifetime,
		const zbx_lld_lifetime_t *enabled_lifetime, int now)
{
	zbx_hashset_t	discoveries;

	zbx_hashset_create(&discoveries, (size_t)triggers->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (int i = 0; i < triggers->values_num; i++)
	{
		zbx_lld_trigger_t	*trigger = triggers->values[i];
		zbx_lld_discovery_t	*discovery;
		unsigned char		object_status;

		object_status = (TRIGGER_STATUS_DISABLED == trigger->status ? ZBX_LLD_OBJECT_STATUS_DISABLED :
				ZBX_LLD_OBJECT_STATUS_ENABLED);
		discovery = lld_add_discovery(&discoveries, trigger->triggerid, trigger->description);

		if (0 != (trigger->flags & ZBX_FLAG_LLD_TRIGGER_DISCOVERED))
		{
			lld_process_discovered_object(discovery, trigger->discovery_status, trigger->ts_delete,
					trigger->lastcheck, now);
			lld_enable_discovered_object(discovery, object_status, trigger->disable_source,
					trigger->ts_disable);
			continue;
		}

		/* process lost triggers */

		lld_process_lost_object(discovery, object_status, trigger->lastcheck, now, lifetime,
				trigger->discovery_status, trigger->disable_source, trigger->ts_delete);

		lld_disable_lost_object(discovery, object_status, trigger->lastcheck, now, enabled_lifetime,
				trigger->ts_disable);
	}

	lld_flush_discoveries(&discoveries, "triggerid", "triggers", "trigger_discovery", now, get_trigger_status_value,
			zbx_db_delete_triggers, zbx_audit_trigger_create_entry,
			zbx_audit_trigger_update_json_update_status);

	zbx_hashset_destroy(&discoveries);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds or updates triggers for discovered items                     *
 *                                                                            *
 * Return value: SUCCEED - if triggers were successfully added/updated or     *
 *                         adding/updating was not necessary                  *
 *               FAIL    - triggers cannot be added/updated                   *
 *                                                                            *
 ******************************************************************************/
int	lld_update_triggers(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, const zbx_vector_lld_row_ptr_t *lld_rows,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **error, zbx_lld_lifetime_t *lifetime,
		zbx_lld_lifetime_t *enabled_lifetime, int lastcheck)
{
	zbx_vector_lld_trigger_prototype_ptr_t	trigger_prototypes;
	zbx_vector_lld_trigger_ptr_t		triggers;
	zbx_vector_lld_item_ptr_t		items;
	zbx_lld_trigger_t			*trigger;
	zbx_lld_trigger_prototype_t		*trigger_prototype;
	int					ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_lld_trigger_prototype_ptr_create(&trigger_prototypes);

	lld_trigger_prototypes_get(lld_ruleid, &trigger_prototypes, error);

	if (0 == trigger_prototypes.values_num)
		goto out;

	zbx_vector_lld_trigger_ptr_create(&triggers);	/* list of triggers which were created or will be created or */
							/* updated by the trigger prototype */
	zbx_vector_lld_item_ptr_create(&items);		/* list of items which are related to the trigger prototypes */

	lld_triggers_get(&trigger_prototypes, &triggers);
	lld_functions_get(&trigger_prototypes, &triggers);
	lld_dependencies_get(&trigger_prototypes, &triggers);
	lld_tags_get(&trigger_prototypes, &triggers);
	lld_items_get(&trigger_prototypes, &items);

	/* simplifying trigger expressions */

	for (int i = 0; i < trigger_prototypes.values_num; i++)
	{
		trigger_prototype = trigger_prototypes.values[i];

		lld_eval_expression_simplify(&trigger_prototype->eval_ctx, NULL, &trigger_prototype->functions);
		lld_eval_expression_simplify(&trigger_prototype->eval_ctx_r, NULL, &trigger_prototype->functions);
	}

	for (int i = 0; i < triggers.values_num; i++)
	{
		trigger = triggers.values[i];

		lld_trigger_expression_simplify(trigger, &trigger->expression, &trigger->functions);
		lld_trigger_expression_simplify(trigger, &trigger->recovery_expression, &trigger->functions);
	}

	/* making triggers */

	lld_triggers_make(&trigger_prototypes, &triggers, &items, lld_rows, lld_macro_paths, lastcheck, error);
	lld_triggers_validate(hostid, &triggers, error);
	lld_trigger_dependencies_make(&trigger_prototypes, &triggers, lld_rows, error);
	lld_trigger_dependencies_validate(&triggers, error);
	lld_trigger_tags_make(&trigger_prototypes, &triggers, lld_rows, lld_macro_paths, error);
	ret = lld_triggers_save(hostid, &trigger_prototypes, &triggers);
	lld_process_lost_triggers(&triggers, lifetime, enabled_lifetime, lastcheck);

	/* cleaning */

	zbx_vector_lld_item_ptr_clear_ext(&items, lld_item_free);
	zbx_vector_lld_trigger_ptr_clear_ext(&triggers, lld_trigger_free);
	zbx_vector_lld_item_ptr_destroy(&items);
	zbx_vector_lld_trigger_ptr_destroy(&triggers);
out:
	zbx_vector_lld_trigger_prototype_ptr_clear_ext(&trigger_prototypes, lld_trigger_prototype_free);
	zbx_vector_lld_trigger_prototype_ptr_destroy(&trigger_prototypes);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}
