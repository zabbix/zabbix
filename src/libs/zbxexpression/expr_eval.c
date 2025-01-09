/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "zbxexpression.h"
#include "datafunc.h"
#include "evalfunc.h"
#include "expression.h"

#include "zbxcachevalue.h"
#include "zbxeval.h"
#include "zbxnum.h"
#include "zbxparam.h"
#include "zbxsysinfo.h"
#include "zbx_host_constants.h"
#include "zbx_item_constants.h"
#include "zbxtime.h"
#include "zbxvariant.h"
#include "zbxstr.h"
#include "zbxhistory.h"
#include "zbxexpr.h"
#include "zbxdbhigh.h"
#include "zbxdb.h"
#include "zbxcacheconfig.h"
#include "zbxalgo.h"

#define ZBX_ITEM_QUERY_UNSET		0x0000

#define ZBX_ITEM_QUERY_HOST_SELF	0x0001
#define ZBX_ITEM_QUERY_HOST_ONE		0x0002
#define ZBX_ITEM_QUERY_HOST_ANY		0x0004

#define ZBX_ITEM_QUERY_KEY_ONE		0x0010
#define ZBX_ITEM_QUERY_KEY_SOME		0x0020
#define ZBX_ITEM_QUERY_KEY_ANY		0x0040
#define ZBX_ITEM_QUERY_FILTER		0x0100

#define ZBX_ITEM_QUERY_ERROR		0x8000

#define ZBX_ITEM_QUERY_MANY		(ZBX_ITEM_QUERY_HOST_ANY |\
					ZBX_ITEM_QUERY_KEY_SOME | ZBX_ITEM_QUERY_KEY_ANY |\
					ZBX_ITEM_QUERY_FILTER)

#define ZBX_ITEM_QUERY_ITEM_ANY		(ZBX_ITEM_QUERY_HOST_ANY | ZBX_ITEM_QUERY_KEY_ANY)

/* one item query data - index in hostkeys items */
typedef struct
{
	int	dcitem_hk_index;
}
zbx_expression_query_one_t;

/* many items query data - matching itemids */
typedef struct
{
	zbx_vector_uint64_t	itemids;
}
zbx_expression_query_many_t;

ZBX_PTR_VECTOR_IMPL(expression_group_ptr, zbx_expression_group_t *)
ZBX_PTR_VECTOR_IMPL(expression_item_ptr, zbx_expression_item_t *)
ZBX_PTR_VECTOR_IMPL(expression_query_ptr, zbx_expression_query_t *)

static void	expression_query_free_one(zbx_expression_query_one_t *query)
{
	zbx_free(query);
}

static void	expression_query_free_many(zbx_expression_query_many_t *query)
{
	zbx_vector_uint64_destroy(&query->itemids);
	zbx_free(query);
}

static void	expression_query_free(zbx_expression_query_t *query)
{
	zbx_eval_clear_query(&query->ref);

	if (ZBX_ITEM_QUERY_ERROR == query->flags)
		zbx_free(query->error);
	else if (0 != (query->flags & ZBX_ITEM_QUERY_MANY))
		expression_query_free_many((zbx_expression_query_many_t*) query->data);
	else
		expression_query_free_one((zbx_expression_query_one_t*) query->data);

	zbx_free(query);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if key parameter is a wildcard '*'.                         *
 *                                                                            *
 ******************************************************************************/
static int	test_key_param_wildcard_cb(const char *data, int key_type, int level, int num, int quoted,
		void *cb_data, char **param)
{
	ZBX_UNUSED(key_type);
	ZBX_UNUSED(num);
	ZBX_UNUSED(quoted);
	ZBX_UNUSED(param);

	if (0 == level)
		return SUCCEED;

	if ('*' == data[0] && '\0' == data[1])
	{
		*(int *)cb_data = 1;
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create expression item query from item query /host/key?[filter].  *
 *                                                                            *
 * Parameters: itemquery - [IN] the item query                                *
 *                                                                            *
 * Return value: the created expression item query.                           *
 *                                                                            *
 ******************************************************************************/
static zbx_expression_query_t*	expression_create_query(const char *itemquery)
{
	zbx_expression_query_t	*query;

	query = (zbx_expression_query_t *)zbx_malloc(NULL, sizeof(zbx_expression_query_t));
	memset(query, 0, sizeof(zbx_expression_query_t));

	query->flags = ZBX_ITEM_QUERY_UNSET;

	if (0 != zbx_eval_parse_query(itemquery, strlen(itemquery), &query->ref))
	{
		if (NULL == query->ref.host)
			query->flags |= ZBX_ITEM_QUERY_HOST_SELF;
		else if ('*' == *query->ref.host)
			query->flags |= ZBX_ITEM_QUERY_HOST_ANY;
		else
			query->flags |= ZBX_ITEM_QUERY_HOST_ONE;

		if (NULL != query->ref.filter)
			query->flags |= ZBX_ITEM_QUERY_FILTER;

		if ('*' == *query->ref.key)
		{
			query->flags |= ZBX_ITEM_QUERY_KEY_ANY;
		}
		else if (NULL != strchr(query->ref.key, '*'))
		{
			int	wildcard = 0;

			zbx_replace_key_params_dyn(&query->ref.key, ZBX_KEY_TYPE_ITEM, test_key_param_wildcard_cb,
					&wildcard, NULL, 0);

			if (0 != wildcard)
				query->flags |= ZBX_ITEM_QUERY_KEY_SOME;
			else
				query->flags |= ZBX_ITEM_QUERY_KEY_ONE;
		}
		else
			query->flags |= ZBX_ITEM_QUERY_KEY_ONE;
	}

	return query;
}

static void	expression_group_free(zbx_expression_group_t *group)
{
	zbx_free(group->name);
	zbx_vector_uint64_destroy(&group->hostids);
	zbx_free(group);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get group from cache by name.                                     *
 *                                                                            *
 * Parameters: eval - [IN] evaluation data                                    *
 *             name - [IN] group name                                         *
 *                                                                            *
 * Return value: the cached group.                                            *
 *                                                                            *
 * Comments: cache group if necessary.                                        *
 *                                                                            *
 ******************************************************************************/
static zbx_expression_group_t	*expression_get_group(zbx_expression_eval_t *eval, const char *name)
{
	zbx_expression_group_t	*group;

	for (int i = 0; i < eval->groups.values_num; i++)
	{
		group = eval->groups.values[i];

		if (0 == strcmp(group->name, name))
			return group;
	}

	group = (zbx_expression_group_t *)zbx_malloc(NULL, sizeof(zbx_expression_group_t));
	group->name = zbx_strdup(NULL, name);
	zbx_vector_uint64_create(&group->hostids);
	zbx_dc_get_hostids_by_group_name(name, &group->hostids);
	zbx_vector_expression_group_ptr_append(&eval->groups, group);

	return group;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get item from cache by itemid.                                    *
 *                                                                            *
 * Parameters: eval    - [IN] evaluation data                                 *
 *             itemid  - [IN] item identifier                                 *
 *                                                                            *
 * Return value: the cached item.                                             *
 *                                                                            *
 * Comments: cache item if necessary.                                         *
 *                                                                            *
 ******************************************************************************/
static zbx_expression_item_t	*expression_get_item(zbx_expression_eval_t *eval, zbx_uint64_t itemid)
{
	zbx_expression_item_t	*item;

	for (int i = 0; i < eval->itemtags.values_num; i++)
	{
		item = eval->itemtags.values[i];

		if (item->itemid == itemid)
			return item;
	}

	item = (zbx_expression_item_t *)zbx_malloc(NULL, sizeof(zbx_expression_item_t));
	item->itemid = itemid;
	zbx_vector_item_tag_create(&item->tags);
	zbx_dc_get_item_tags(itemid, &item->tags);
	zbx_vector_expression_item_ptr_append(&eval->itemtags, item);

	return item;
}

static void	expression_item_free(zbx_expression_item_t *item)
{
	zbx_vector_item_tag_clear_ext(&item->tags, zbx_free_item_tag);
	zbx_vector_item_tag_destroy(&item->tags);
	zbx_free(item);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize one item query.                                        *
 *                                                                            *
 * Parameters: eval  - [IN] evaluation data                                   *
 *             query - [IN] query to initialize                               *
 *                                                                            *
 ******************************************************************************/
static void	expression_init_query_one(zbx_expression_eval_t *eval, zbx_expression_query_t *query)
{
	zbx_expression_query_one_t	*data;

	data = (zbx_expression_query_one_t *)zbx_malloc(NULL, sizeof(zbx_expression_query_one_t));
	data->dcitem_hk_index = eval->one_num++;
	query->data = data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: replace wildcards '*'in key parameters with % and escape existing *
 *          %, \ characters for SQL like operation.                           *
 *                                                                            *
 ******************************************************************************/
static int	replace_key_param_wildcard_cb(const char *data, int key_type, int level, int num, int quoted,
		void *cb_data, char **param)
{
	char	*tmp;

	ZBX_UNUSED(key_type);
	ZBX_UNUSED(num);
	ZBX_UNUSED(cb_data);

	if (0 == level)
		return SUCCEED;

	if ('*' == data[0] && '\0' == data[1])
	{
		*param = zbx_strdup(NULL, "%");
		return SUCCEED;
	}

	if (NULL == strchr(data, '%') && NULL == strchr(data, '\\'))
		return SUCCEED;

	tmp = zbx_strdup(NULL, data);
	zbx_unquote_key_param(tmp);
	*param = zbx_dyn_escape_string(tmp, "\\%%");
	zbx_free(tmp);

	/* escaping cannot result in unquotable parameter */
	if (FAIL == zbx_quote_key_param(param, quoted))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		zbx_free(*param);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if item key matches the pattern.                            *
 *                                                                            *
 * Parameters: item_key - [IN] item key to match                              *
 *             pattern  - [IN] pattern                                        *
 *                                                                            *
 ******************************************************************************/
static int	expression_match_item_key(const char *item_key, const AGENT_REQUEST *pattern)
{
	AGENT_REQUEST	key;
	int		i, ret = FAIL;

	zbx_init_agent_request(&key);

	if (SUCCEED != zbx_parse_item_key(item_key, &key))
		goto out;

	if (pattern->nparam != key.nparam)
		goto out;

	if (0 != strcmp(pattern->key, key.key))
		goto out;

	for (i = 0; i < key.nparam; i++)
	{
		if (0 == strcmp(pattern->params[i], "*"))
			continue;

		if (0 != strcmp(pattern->params[i], key.params[i]))
			goto out;
	}

	ret = SUCCEED;
out:
	zbx_free_agent_request(&key);

	return ret;
}

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	hostid;
	zbx_expression_eval_t	*eval;
}
zbx_expression_eval_many_t;

/******************************************************************************
 *                                                                            *
 * Purpose: get itemids + hostids of items that might match query based on    *
 *          host, key and filter groups.                                      *
 *                                                                            *
 * Parameters: eval            - [IN] evaluation data                         *
 *             query           - [IN] expression item query                   *
 *             groups          - [IN] groups in filter template               *
 *             filter_template - [IN] group filter template with {index}      *
 *                                    placeholders referring to a group in    *
 *                                    groups vector                           *
 *             itemhosts       - [out] itemid+hostid pairs matching query     *
 *                                                                            *
 ******************************************************************************/
static void	expression_get_item_candidates(zbx_expression_eval_t *eval, const zbx_expression_query_t *query,
		const zbx_vector_str_t *groups, const char *filter_template, zbx_vector_uint64_pair_t *itemhosts)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*sql = NULL, *esc, *clause = "where";
	size_t		sql_alloc = 0, sql_offset = 0;
	AGENT_REQUEST	pattern;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select i.itemid,i.hostid");

	if (0 != (query->flags & ZBX_ITEM_QUERY_KEY_SOME))
	{
		zbx_init_agent_request(&pattern);
		if (SUCCEED != zbx_parse_item_key(query->ref.key, &pattern))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_free(sql);
			return;
		}

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ",i.key_");
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " from items i");

	if (0 != (query->flags & ZBX_ITEM_QUERY_HOST_ONE))
	{
		esc = zbx_db_dyn_escape_string(query->ref.host);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",hosts h"
				" where h.hostid=i.hostid"
				" and h.host='%s'", esc);
		zbx_free(esc);
		clause = "and";
	}
	else if (0 != (query->flags & ZBX_ITEM_QUERY_HOST_SELF))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where i.hostid=" ZBX_FS_UI64,
				eval->hostid);
		clause = "and";
	}

	if (0 != (query->flags & ZBX_ITEM_QUERY_KEY_SOME))
	{
		char	*key;

		key = zbx_strdup(NULL, query->ref.key);
		zbx_replace_key_params_dyn(&key, ZBX_KEY_TYPE_ITEM, replace_key_param_wildcard_cb, NULL, NULL, 0);

		esc = zbx_db_dyn_escape_string(key);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " %s i.key_ like '%s'", clause, esc);
		zbx_free(esc);
		zbx_free(key);
		clause = "and";
	}
	else if (0 != (query->flags & ZBX_ITEM_QUERY_KEY_ONE))
	{
		esc = zbx_db_dyn_escape_string(query->ref.key);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " %s i.key_='%s'", clause, esc);
		zbx_free(esc);
		clause = "and";
	}

	if (0 != (query->flags & ZBX_ITEM_QUERY_FILTER) && NULL != filter_template && '\0' != *filter_template)
	{
		zbx_uint64_t		index;
		int			pos = 0, last_pos = 0;
		zbx_token_t		token;
		zbx_expression_group_t	*group;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " %s ", clause);

		for (; SUCCEED == zbx_token_find(filter_template, pos, &token, ZBX_TOKEN_SEARCH_FUNCTIONID); pos++)
		{
			if (ZBX_TOKEN_OBJECTID != token.type)
				continue;

			if (SUCCEED != zbx_is_uint64_n(filter_template + token.loc.l + 1, token.loc.r - token.loc.l - 1,
					&index) && (int)index < groups->values_num)
			{
				continue;
			}

			group = expression_get_group(eval, groups->values[index]);

			zbx_strncpy_alloc(&sql, &sql_alloc, &sql_offset, filter_template + last_pos,
					token.loc.l - last_pos);

			if (' ' == sql[sql_offset - 1])
				sql_offset--;

			if (0 < group->hostids.values_num)
			{
				zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid",
						group->hostids.values, group->hostids.values_num);
			}
			else
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " 1=0");

			last_pos = token.loc.r + 1;
			pos = token.loc.r;
		}

		if ('\0' != filter_template[last_pos])
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, filter_template + last_pos);
	}

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_pair_t	pair;

		if (0 == (query->flags & ZBX_ITEM_QUERY_KEY_SOME) ||
				(NULL != pattern.key && SUCCEED == expression_match_item_key(row[2], &pattern)))
		{
			ZBX_STR2UINT64(pair.first, row[0]);
			ZBX_STR2UINT64(pair.second, row[1]);
			zbx_vector_uint64_pair_append(itemhosts, pair);
		}
	}
	zbx_db_free_result(result);

	if (0 != (query->flags & ZBX_ITEM_QUERY_KEY_SOME))
		zbx_free_agent_request(&pattern);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the item matches the tag.                                *
 *                                                                            *
 * Parameters: item - [IN] item with tags                                     *
 *             tag  - [IN] tag to match in format <tag name>[:<tag value>]    *
 *                                                                            *
 * Return value: SUCCEED - the item matches the specified tag                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	expression_item_check_tag(zbx_expression_item_t *item, const char *tag)
{
	int		i;
	size_t		taglen;
	const char	*value;

	if (NULL != (value = strchr(tag, ':')))
	{
		taglen = (value - tag);
		value++;
	}
	else
		taglen = strlen(tag);

	for (i = 0; i < item->tags.values_num; i++)
	{
		zbx_item_tag_t	*itemtag = item->tags.values[i];

		if (taglen != strlen(itemtag->tag.tag) || 0 != memcmp(tag, itemtag->tag.tag, taglen))
			continue;

		if (NULL == value)
			return SUCCEED;

		if (0 == strcmp(itemtag->tag.value, value))
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate filter function.                                         *
 *                                                                            *
 * Parameters: name     - [IN] function name (not zero terminated)            *
 *             len      - [IN] function name length                           *
 *             args_num - [IN] number of function arguments                   *
 *             args     - [IN] an array of function arguments                 *
 *             data     - [IN] caller data used for function evaluation       *
 *             ts       - [IN] function execution time                        *
 *             value    - [OUT] function return value                         *
 *             error    - [OUT]                                               *
 *                                                                            *
 * Return value: SUCCEED - the function was evaluated successfully            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: the group/tag comparisons in filter are converted to function    *
 *           calls that are evaluated by this callback.                       *
 *                                                                            *
 ******************************************************************************/
static int	expression_eval_filter(const char *name, size_t len, int args_num, zbx_variant_t *args,
		void *data, const zbx_timespec_t *ts, zbx_variant_t *value, char **error)
{
	zbx_expression_eval_many_t	*many = (zbx_expression_eval_many_t *)data;

	ZBX_UNUSED(ts);
	ZBX_UNUSED(len);

	if (1 != args_num)
	{
		*error = zbx_strdup(NULL, "invalid number of arguments");
		return FAIL;
	}

	if (ZBX_VARIANT_STR != args[0].type)
	{
		*error = zbx_strdup(NULL, "invalid argument flags");
		return FAIL;
	}

	if (0 == strncmp(name, "group", ZBX_CONST_STRLEN("group")))
	{
		zbx_expression_group_t *group;

		group = expression_get_group(many->eval, args[0].data.str);

		if (FAIL != zbx_vector_uint64_bsearch(&group->hostids, many->hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			zbx_variant_set_dbl(value, 1);
		else
			zbx_variant_set_dbl(value, 0);

		return SUCCEED;
	}
	else if (0 == strncmp(name, "tag", ZBX_CONST_STRLEN("tag")))
	{
		zbx_expression_item_t	*item;

		item = expression_get_item(many->eval, many->itemid);

		if (SUCCEED == expression_item_check_tag(item, args[0].data.str))
			zbx_variant_set_dbl(value, 1);
		else
			zbx_variant_set_dbl(value, 0);

		return SUCCEED;
	}
	else
	{
		*error = zbx_strdup(NULL, "unknown function");
		return FAIL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize many item query.                                       *
 *                                                                            *
 * Parameters: eval    - [IN] evaluation data                                 *
 *             query   - [IN] query to initialize                             *
 *                                                                            *
 ******************************************************************************/
static void	expression_init_query_many(zbx_expression_eval_t *eval, zbx_expression_query_t *query)
{
	zbx_expression_query_many_t	*data;
	char				*error = NULL, *errmsg = NULL, *filter_template = NULL;
	int				i, ret = FAIL;
	zbx_eval_context_t		ctx;
	zbx_vector_uint64_pair_t	itemhosts;
	zbx_vector_str_t		groups;
	zbx_vector_uint64_t		itemids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() /%s/%s?[%s]", __func__, ZBX_NULL2EMPTY_STR(query->ref.host),
			ZBX_NULL2EMPTY_STR(query->ref.key), ZBX_NULL2EMPTY_STR(query->ref.filter));

	zbx_eval_init(&ctx);

	zbx_vector_uint64_create(&itemids);
	zbx_vector_uint64_pair_create(&itemhosts);
	zbx_vector_str_create(&groups);

	if (ZBX_ITEM_QUERY_ITEM_ANY == (query->flags & ZBX_ITEM_QUERY_ITEM_ANY))
	{
		error = zbx_strdup(NULL, "item query must have at least a host or an item key defined");
		goto out;
	}

	if (0 != (query->flags & ZBX_ITEM_QUERY_FILTER))
	{
		if (SUCCEED != zbx_eval_parse_expression(&ctx, query->ref.filter, ZBX_EVAL_PARSE_QUERY_EXPRESSION,
				&errmsg))
		{
			error = zbx_dsprintf(NULL, "failed to parse item query filter: %s", errmsg);
			zbx_free(errmsg);
			goto out;
		}

		zbx_eval_prepare_filter(&ctx);

		if (FAIL == zbx_eval_get_group_filter(&ctx, &groups, &filter_template, &errmsg))
		{
			error = zbx_dsprintf(NULL, "failed to extract groups from item filter: %s", errmsg);
			zbx_free(errmsg);
			goto out;
		}
	}

	expression_get_item_candidates(eval, query, &groups, filter_template, &itemhosts);

	if (0 != (query->flags & ZBX_ITEM_QUERY_FILTER))
	{
		zbx_expression_eval_many_t	eval_data;
		zbx_variant_t		filter_value;

		eval_data.eval = eval;

		for (i = 0; i < itemhosts.values_num; i++)
		{
			eval_data.itemid = itemhosts.values[i].first;
			eval_data.hostid = itemhosts.values[i].second;

			if (SUCCEED != zbx_eval_execute_ext(&ctx, NULL, expression_eval_filter, NULL,
					(void *)&eval_data, &filter_value, &errmsg))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "failed to evaluate item query filter: %s", errmsg);
				zbx_free(errmsg);
				continue;
			}

			if (SUCCEED != zbx_variant_convert(&filter_value, ZBX_VARIANT_DBL))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "unexpected item query filter evaluation result:"
						" value:\"%s\" of type \"%s\"", zbx_variant_value_desc(&filter_value),
						zbx_variant_type_desc(&filter_value));

				zbx_variant_clear(&filter_value);
				continue;
			}

			if (SUCCEED != zbx_double_compare(filter_value.data.dbl, 0))
				zbx_vector_uint64_append(&itemids, eval_data.itemid);
		}
	}
	else
	{
		for (i = 0; i < itemhosts.values_num; i++)
			zbx_vector_uint64_append(&itemids, itemhosts.values[i].first);
	}

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		for (i = 0; i < itemids.values_num; i++)
			zabbix_log(LOG_LEVEL_DEBUG, "%s() itemid:" ZBX_FS_UI64, __func__, itemids.values[i]);
	}

	data = (zbx_expression_query_many_t *)zbx_malloc(NULL, sizeof(zbx_expression_query_many_t));
	data->itemids = itemids;
	query->data = data;
	eval->many_num++;

	ret = SUCCEED;
out:
	if (0 != (query->flags & ZBX_ITEM_QUERY_FILTER) && SUCCEED == zbx_eval_status(&ctx))
		zbx_eval_clear(&ctx);

	if (SUCCEED != ret)
	{
		query->error = error;
		query->flags = ZBX_ITEM_QUERY_ERROR;
		zbx_vector_uint64_destroy(&itemids);
	}

	zbx_free(filter_template);

	zbx_vector_uint64_pair_destroy(&itemhosts);

	zbx_vector_str_clear_ext(&groups, zbx_str_free);
	zbx_vector_str_destroy(&groups);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() items:%d", __func__, (SUCCEED == ret ? data->itemids.values_num : -1));
}

/******************************************************************************
 *                                                                            *
 * Purpose: cache items used in one item queries.                             *
 *                                                                            *
 * Parameters: eval - [IN] evaluation data                                    *
 *                                                                            *
 ******************************************************************************/
static void	expression_cache_dcitems_hk(zbx_expression_eval_t *eval)
{
	int	i;

	eval->hostkeys = (zbx_host_key_t *)zbx_malloc(NULL, sizeof(zbx_host_key_t) * eval->one_num);
	eval->dcitems_hk = (zbx_dc_item_t *)zbx_malloc(NULL, sizeof(zbx_dc_item_t) * eval->one_num);
	eval->errcodes_hk = (int *)zbx_malloc(NULL, sizeof(int) * eval->one_num);

	for (i = 0; i < eval->queries.values_num; i++)
	{
		zbx_expression_query_t	*query = eval->queries.values[i];
		zbx_expression_query_one_t	*data;

		if (0 != (query->flags & ZBX_ITEM_QUERY_MANY) || ZBX_ITEM_QUERY_ERROR == query->flags)
			continue;

		data = (zbx_expression_query_one_t *)query->data;

		eval->hostkeys[data->dcitem_hk_index].host = query->ref.host;
		eval->hostkeys[data->dcitem_hk_index].key = query->ref.key;
	}

	zbx_dc_config_get_items_by_keys(eval->dcitems_hk, eval->hostkeys, eval->errcodes_hk, eval->one_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: dcitem reference vector lookup functions.                         *
 *                                                                            *
 ******************************************************************************/
static	int	compare_dcitems_by_itemid(const void *d1, const void *d2)
{
	zbx_dc_item_t	*dci1 = *(zbx_dc_item_t * const *)d1;
	zbx_dc_item_t	*dci2 = *(zbx_dc_item_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(dci1->itemid, dci2->itemid);

	return 0;
}

static int	expression_find_dcitem_by_itemid(const void *d1, const void *d2)
{
	zbx_uint64_t		itemid = **(zbx_uint64_t * const *)d1;
	zbx_dc_item_t		*dci = *(zbx_dc_item_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(itemid, dci->itemid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: cache items used in many item queries.                            *
 *                                                                            *
 * Parameters: eval - [IN] evaluation data                                    *
 *                                                                            *
 ******************************************************************************/
static void	expression_cache_dcitems(zbx_expression_eval_t *eval)
{
	int			i, j;
	zbx_vector_uint64_t	itemids;

	zbx_vector_uint64_create(&itemids);

	if (0 != eval->one_num)
	{
		for (i = 0; i < eval->one_num; i++)
		{
			if (SUCCEED != eval->errcodes_hk[i])
				continue;

			zbx_vector_dc_item_append(&eval->dcitem_refs, &eval->dcitems_hk[i]);
		}

		zbx_vector_dc_item_sort(&eval->dcitem_refs, compare_dcitems_by_itemid);
	}

	for (i = 0; i < eval->queries.values_num; i++)
	{
		zbx_expression_query_t	*query = eval->queries.values[i];
		zbx_expression_query_many_t	*data;

		if (0 == (query->flags & ZBX_ITEM_QUERY_MANY))
			continue;

		data = (zbx_expression_query_many_t *)query->data;

		for (j = 0; j < data->itemids.values_num; j++)
			zbx_vector_uint64_append(&itemids, data->itemids.values[j]);
	}

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (i = 0; i < itemids.values_num;)
	{
		if (FAIL != zbx_vector_ptr_bsearch((const zbx_vector_ptr_t *)&eval->dcitem_refs, &itemids.values[i],
				expression_find_dcitem_by_itemid))
		{
			zbx_vector_uint64_remove(&itemids, i);
			continue;
		}
		i++;
	}

	if (0 != (eval->dcitems_num = itemids.values_num))
	{
		zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		eval->dcitems = (zbx_dc_item_t *)zbx_malloc(NULL, sizeof(zbx_dc_item_t) * itemids.values_num);
		eval->errcodes = (int *)zbx_malloc(NULL, sizeof(int) * itemids.values_num);

		zbx_dc_config_get_items_by_itemids(eval->dcitems, itemids.values, eval->errcodes, itemids.values_num);

		for (i = 0; i < itemids.values_num; i++)
		{
			if (SUCCEED != eval->errcodes[i])
				continue;

			zbx_vector_dc_item_append(&eval->dcitem_refs, &eval->dcitems[i]);
		}

		zbx_vector_dc_item_sort(&eval->dcitem_refs, compare_dcitems_by_itemid);
	}

	zbx_vector_uint64_destroy(&itemids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if function is to be evaluated for NOTSUPPORTED items.      *
 *                                                                            *
 * Parameters: fn - [IN] function name                                        *
 *                                                                            *
 * Return value: SUCCEED - do evaluate the function for NOTSUPPORTED items    *
 *               FAIL - don't evaluate the function for NOTSUPPORTED items    *
 *                                                                            *
 ******************************************************************************/
int	zbx_evaluatable_for_notsupported(const char *fn)
{
	/* function nodata() are exceptions,                   */
	/* and should be evaluated for NOTSUPPORTED items, too */

	if (0 == strcmp(fn, "nodata"))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate historical function for one item query.                  *
 *                                                                            *
 * Parameters: eval     - [IN] evaluation data                                *
 *             query    - [IN] item query                                     *
 *             name     - [IN] function name (not zero terminated)            *
 *             len      - [IN] function name length                           *
 *             args_num - [IN] number of function arguments                   *
 *             args     - [IN] array of function arguments.                   *
 *             ts       - [IN] function execution time                        *
 *             value    - [OUT] function return value                         *
 *             error    - [OUT]                                               *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	expression_eval_one(zbx_expression_eval_t *eval, zbx_expression_query_t *query, const char *name,
		size_t len, int args_num, zbx_variant_t *args, const zbx_timespec_t *ts, zbx_variant_t *value,
		char **error)
{
	char				func_name[MAX_STRING_LEN], *params = NULL;
	size_t				params_alloc = 0, params_offset = 0;
	zbx_dc_item_t			*item;
	int				i, ret = FAIL;
	zbx_expression_query_one_t	*data;
	zbx_dc_evaluate_item_t		evaluate_item;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() %.*s(/%s/%s?[%s],...)", __func__, (int )len, name,
			ZBX_NULL2EMPTY_STR(query->ref.host), ZBX_NULL2EMPTY_STR(query->ref.key),
			ZBX_NULL2EMPTY_STR(query->ref.filter));

	data = (zbx_expression_query_one_t *)query->data;

	if (SUCCEED != eval->errcodes_hk[data->dcitem_hk_index])
	{
		*error = zbx_dsprintf(NULL, "item \"/%s/%s\" does not exist",
				eval->hostkeys[data->dcitem_hk_index].host, eval->hostkeys[data->dcitem_hk_index].key);
		goto out;
	}

	item = &eval->dcitems_hk[data->dcitem_hk_index];

	/* do not evaluate if the item is disabled or belongs to a disabled host */

	if (ITEM_STATUS_ACTIVE != item->status)
	{
		*error = zbx_dsprintf(NULL, "item \"/%s/%s\" is disabled", eval->hostkeys[data->dcitem_hk_index].host,
				eval->hostkeys[data->dcitem_hk_index].key);
		goto out;
	}

	if (HOST_STATUS_MONITORED != item->host.status)
	{
		*error = zbx_dsprintf(NULL, "host \"%s\" is not monitored", eval->hostkeys[data->dcitem_hk_index].host);
		goto out;
	}

	memcpy(func_name, name, len);
	func_name[len] = '\0';

	/* If the item is NOTSUPPORTED then evaluation is allowed for:   */
	/*   - functions white-listed in evaluatable_for_notsupported(). */
	/*     Their values can be evaluated to regular numbers even for */
	/*     NOTSUPPORTED items. */
	/*   - other functions. Result of evaluation is ZBX_UNKNOWN.     */

	if (ITEM_STATE_NOTSUPPORTED == item->state && FAIL == zbx_evaluatable_for_notsupported(func_name))
	{
		/* compose and store 'unknown' message for future use */
		*error = zbx_dsprintf(NULL, "item \"/%s/%s\" is not supported",
				eval->hostkeys[data->dcitem_hk_index].host, eval->hostkeys[data->dcitem_hk_index].key);
		goto out;
	}

	evaluate_item.itemid = item->itemid;
	evaluate_item.value_type = item->value_type;
	evaluate_item.proxyid = item->host.proxyid;
	evaluate_item.host = item->host.host;
	evaluate_item.key_orig = item->key_orig;

	if (0 == args_num)
	{
		ret = zbx_evaluate_function(value, &evaluate_item, func_name, "", ts, error);
		goto out;
	}

	for (i = 0; i < args_num; i++)
	{
		if (0 != i)
			zbx_chrcpy_alloc(&params, &params_alloc, &params_offset, ',');

		switch (args[i].type)
		{
			case ZBX_VARIANT_DBL:
				zbx_snprintf_alloc(&params, &params_alloc, &params_offset, ZBX_FS_DBL64,
						args[i].data.dbl);
				break;
			case ZBX_VARIANT_STR:
				zbx_strquote_alloc_opt(&params, &params_alloc, &params_offset, args[i].data.str,
						ZBX_STRQUOTE_DEFAULT);
				break;
			case ZBX_VARIANT_UI64:
				zbx_snprintf_alloc(&params, &params_alloc, &params_offset, ZBX_FS_UI64,
						args[i].data.ui64);
				break;
			case ZBX_VARIANT_NONE:
				break;
			default:
				*error = zbx_dsprintf(NULL, " unsupported argument #%d type \"%s\"", i + 1,
						zbx_variant_type_desc(&args[i]));
				goto out;
		}
	}

	ret = zbx_evaluate_function(value, &evaluate_item, func_name, ZBX_NULL2EMPTY_STR(params), ts, error);
out:
	zbx_free(params);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:%s flags:%s", __func__, zbx_result_string(ret),
			zbx_variant_value_desc(value), zbx_variant_type_desc(value));

	return ret;
}

#define ZBX_VALUE_FUNC_UNKNOWN	0
#define ZBX_VALUE_FUNC_MIN	1
#define ZBX_VALUE_FUNC_AVG	2
#define ZBX_VALUE_FUNC_MAX	3
#define ZBX_VALUE_FUNC_SUM	4
#define ZBX_VALUE_FUNC_COUNT	5
#define ZBX_VALUE_FUNC_LAST	6
#define ZBX_ITEM_FUNC_EXISTS	7
#define ZBX_ITEM_FUNC_ITEMCOUNT	8
#define ZBX_ITEM_FUNC_BPERCENTL	9
#define ZBX_MIXVALUE_FUNC_BRATE	10

#define MATCH_STRING(x, name, len)	ZBX_CONST_STRLEN(x) == len && 0 == memcmp(name, x, len)

static int	get_function_by_name(const char *name, size_t len)
{

	if (MATCH_STRING("avg_foreach", name, len))
		return ZBX_VALUE_FUNC_AVG;

	if (MATCH_STRING("count_foreach", name, len))
		return ZBX_VALUE_FUNC_COUNT;

	if (MATCH_STRING("last_foreach", name, len))
		return ZBX_VALUE_FUNC_LAST;

	if (MATCH_STRING("max_foreach", name, len))
		return ZBX_VALUE_FUNC_MAX;

	if (MATCH_STRING("min_foreach", name, len))
		return ZBX_VALUE_FUNC_MIN;

	if (MATCH_STRING("sum_foreach", name, len))
		return ZBX_VALUE_FUNC_SUM;

	if (MATCH_STRING("exists_foreach", name, len))
		return ZBX_ITEM_FUNC_EXISTS;

	if (MATCH_STRING("item_count", name, len))
		return ZBX_ITEM_FUNC_ITEMCOUNT;

	if (MATCH_STRING("bucket_percentile", name, len))
		return ZBX_ITEM_FUNC_BPERCENTL;

	if (MATCH_STRING("bucket_rate_foreach", name, len))
		return ZBX_MIXVALUE_FUNC_BRATE;

	return ZBX_VALUE_FUNC_UNKNOWN;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate minimum value from the history value vector.            *
 *                                                                            *
 * Parameters: values      - [IN] vector containing history values            *
 *             value_type  - [IN] type of values. Only float/uint64 values    *
 *                                are supported.                              *
 *             result      - [OUT]                                            *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_min(zbx_vector_history_record_t *values, int value_type, double *result)
{
	int	i;

	if (ITEM_VALUE_TYPE_UINT64 == value_type)
	{
		*result = (double)values->values[0].value.ui64;

		for (i = 1; i < values->values_num; i++)
			if ((double)values->values[i].value.ui64 < *result)
				*result = (double)values->values[i].value.ui64;
	}
	else
	{
		*result = values->values[0].value.dbl;

		for (i = 1; i < values->values_num; i++)
			if (values->values[i].value.dbl < *result)
				*result = values->values[i].value.dbl;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate maximum value from the history value vector.            *
 *                                                                            *
 * Parameters: values      - [IN] vector containing history values            *
 *             value_type  - [IN] type of values. Only float/uint64 values    *
 *                                are supported.                              *
 *             result      - [OUT]                                            *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_max(zbx_vector_history_record_t *values, int value_type, double *result)
{
	int	i;

	if (ITEM_VALUE_TYPE_UINT64 == value_type)
	{
		*result = (double)values->values[0].value.ui64;

		for (i = 1; i < values->values_num; i++)
			if ((double)values->values[i].value.ui64 > *result)
				*result = (double)values->values[i].value.ui64;
	}
	else
	{
		*result = values->values[0].value.dbl;

		for (i = 1; i < values->values_num; i++)
			if (values->values[i].value.dbl > *result)
				*result = values->values[i].value.dbl;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate sum of values from the history value vector.            *
 *                                                                            *
 * Parameters: values      - [IN] vector containing history values            *
 *             value_type  - [IN] type of values. Only float/uint64 values    *
 *                                are supported.                              *
 *             result      - [OUT]                                            *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_sum(zbx_vector_history_record_t *values, int value_type, double *result)
{
	int	i;

	*result = 0;

	if (ITEM_VALUE_TYPE_UINT64 == value_type)
	{
		for (i = 0; i < values->values_num; i++)
			*result += (double)values->values[i].value.ui64;
	}
	else
	{
		for (i = 0; i < values->values_num; i++)
			*result += values->values[i].value.dbl;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate average value of values from the history value vector.  *
 *                                                                            *
 * Parameters: values      - [IN] vector containing history values            *
 *             value_type  - [IN] type of values. Only float/uint64 values    *
 *                                are supported.                              *
 *             result      - [OUT]                                            *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_avg(zbx_vector_history_record_t *values, int value_type, double *result)
{
	evaluate_history_func_sum(values, value_type, result);
	*result /= values->values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate number of values in value vector.                       *
 *                                                                            *
 * Parameters: values      - [IN] vector containing history values            *
 *             value_type  - [IN] type of values. Only float/uint64 values    *
 *                                are supported.                              *
 *             result      - [OUT]                                            *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_count(zbx_vector_history_record_t *values, double *result)
{
	*result = (double)values->values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate the last (newest) value in value vector.                *
 *                                                                            *
 * Parameters: values      - [IN] vector containing history values            *
 *             value_type  - [IN] only float/uint64 values are supported      *
 *             result      - [OUT]                                            *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_last(zbx_vector_history_record_t *values, int value_type, double *result)
{
	if (ITEM_VALUE_TYPE_UINT64 == value_type)
		*result = (double)values->values[0].value.ui64;
	else
		*result = values->values[0].value.dbl;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert history record to variant and append to variant vector.   *
 *                                                                            *
 * Parameters: values         - [IN] vector containing history values         *
 *             value_type     - [IN] type of item                             *
 *             result_vector  - [OUT] resulting vector                        *
 *                                                                            *
 ******************************************************************************/
static void	var_vector_append_history_record(zbx_vector_history_record_t *values, int value_type,
		zbx_vector_var_t *results_vector)
{
	zbx_variant_t	result;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_UINT64:
			zbx_variant_set_ui64(&result, values->values[0].value.ui64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_variant_set_str(&result, zbx_strdup(NULL, values->values[0].value.str));
			break;
		case ITEM_VALUE_TYPE_LOG:
			zbx_variant_set_str(&result, zbx_strdup(NULL, values->values[0].value.log->value));
			break;
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_variant_set_dbl(&result, values->values[0].value.dbl);
			break;
		case ITEM_VALUE_TYPE_NONE:
			return;
		case ITEM_VALUE_TYPE_BIN:
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return;
	}

	zbx_vector_var_append(results_vector, result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate function with values from value vector.                 *
 *                                                                            *
 * Parameters: values      - [IN] vector containing history values            *
 *             value_type  - [IN] type of values. Only float/uint64 values    *
 *                                are supported.                              *
 *             func        - [IN] function to calculate. Only                 *
 *                                ZBX_VALUE_FUNC_MIN, ZBX_VALUE_FUNC_AVG,     *
 *                                ZBX_VALUE_FUNC_MAX, ZBX_VALUE_FUNC_SUM,     *
 *                                ZBX_VALUE_FUNC_COUNT, ZBX_VALUE_FUNC_LAST   *
 *                                functions are supported.                    *
 *             result      - [OUT]                                            *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func(zbx_vector_history_record_t *values, int value_type, int func,
		double *result)
{
	switch (func)
	{
		case ZBX_VALUE_FUNC_MIN:
			evaluate_history_func_min(values, value_type, result);
			break;
		case ZBX_VALUE_FUNC_AVG:
			evaluate_history_func_avg(values, value_type, result);
			break;
		case ZBX_VALUE_FUNC_MAX:
			evaluate_history_func_max(values, value_type, result);
			break;
		case ZBX_VALUE_FUNC_SUM:
			evaluate_history_func_sum(values, value_type, result);
			break;
		case ZBX_VALUE_FUNC_COUNT:
			evaluate_history_func_count(values, result);
			break;
		case ZBX_VALUE_FUNC_LAST:
			evaluate_history_func_last(values, value_type, result);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get item from cache by itemid.                                    *
 *                                                                            *
 * Parameters: eval    - [IN] evaluation data                                 *
 *             itemid  - [IN] item identifier                                 *
 *                                                                            *
 * Return value: the cached item.                                             *
 *                                                                            *
 ******************************************************************************/
static zbx_dc_item_t	*get_dcitem(zbx_vector_dc_item_t *dcitem_refs, zbx_uint64_t itemid)
{
	int	index;

	if (FAIL == (index = zbx_vector_ptr_bsearch((const zbx_vector_ptr_t *)dcitem_refs, &itemid,
			expression_find_dcitem_by_itemid)))
	{
		return NULL;
	}

	return dcitem_refs->values[index];
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate functions 'exists_foreach' and 'item_count' for multiple *
 *          items.                                                            *
 *                                                                            *
 * Parameters: eval      - [IN] evaluation data                               *
 *             query     - [IN] calculated item query                         *
 *             item_func - [IN] function id                                   *
 *             value     - [OUT] function return value                        *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static void	expression_eval_exists(zbx_expression_eval_t *eval, zbx_expression_query_t *query, int item_func,
		zbx_variant_t *value)
{
	zbx_expression_query_many_t	*data;
	int				i;
	zbx_vector_var_t		*results;

	results = (zbx_vector_var_t*)zbx_malloc(NULL, sizeof(zbx_vector_var_t));
	zbx_vector_var_create(results);

	data = (zbx_expression_query_many_t *)query->data;

	for (i = 0; i < data->itemids.values_num; i++)
	{
		zbx_dc_item_t	*dcitem;
		zbx_variant_t	v;

		if (NULL == (dcitem = get_dcitem(&eval->dcitem_refs, data->itemids.values[i])))
			continue;

		if (ITEM_STATUS_ACTIVE != dcitem->status)
			continue;

		if (HOST_STATUS_MONITORED != dcitem->host.status)
			continue;

		zbx_variant_set_ui64(&v, 1);

		zbx_vector_var_append(results, v);
	}

	if (ZBX_ITEM_FUNC_EXISTS == item_func)
	{
		zbx_variant_set_vector(value, results);
	}
	else
	{
		zbx_variant_set_ui64(value, (zbx_uint64_t)results->values_num);
		zbx_vector_var_destroy(results);
		zbx_free(results);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate functions 'bucket_rate_foreach' for 'histogram_quantile' *
 *          and evaluate functions 'bucket_percentile'.                       *
 *                                                                            *
 * Parameters: eval      - [IN] evaluation data                               *
 *             query     - [IN] calculated item query                         *
 *             args_num  - [IN] number of function arguments                  *
 *             args      - [IN] array of function arguments                   *
 *             ts        - [IN] function execution time                       *
 *             item_func - [IN] function id                                   *
 *             value     - [OUT] function return value                        *
 *             error     - [OUT]                                              *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	expression_eval_bucket_rate(zbx_expression_eval_t *eval, zbx_expression_query_t *query,
		int args_num, zbx_variant_t *args, const zbx_timespec_t *ts, int item_func, zbx_variant_t *value,
		char **error)
{
	zbx_expression_query_many_t	*data;
	int				i, pos, ret = FAIL;
	zbx_vector_var_t		*results = NULL;
	double				percentage, result;
	char				*param = NULL;
	const char			*log_fn = (ZBX_ITEM_FUNC_BPERCENTL == item_func ?
							"bucket_percentile" : "bucket_rate_foreach");

	if (1 > args_num || 2 < args_num || (ZBX_ITEM_FUNC_BPERCENTL == item_func && 2 != args_num))
	{
		*error = zbx_strdup(NULL, "invalid number of function parameters");
		goto err;
	}

	if (ZBX_VARIANT_STR == args[0].type)
	{
		param = zbx_strdup(NULL, args[0].data.str);
	}
	else
	{
		zbx_variant_t	arg;

		zbx_variant_copy(&arg, &args[0]);

		if (SUCCEED != zbx_variant_convert(&arg, ZBX_VARIANT_STR))
		{
			zbx_variant_clear(&arg);
			*error = zbx_strdup(NULL, "invalid second parameter");
			goto err;
		}

		param = zbx_strdup(NULL, arg.data.str);
		zbx_variant_clear(&arg);
	}

	if (ZBX_ITEM_FUNC_BPERCENTL == item_func)
	{
		if (ZBX_VARIANT_DBL == args[1].type)
		{
			percentage = args[1].data.dbl;
		}
		else
		{
			zbx_variant_t	val_copy;

			zbx_variant_copy(&val_copy, &args[1]);

			if (SUCCEED != zbx_variant_convert(&val_copy, ZBX_VARIANT_DBL))
			{
				zbx_variant_clear(&val_copy);
				*error = zbx_strdup(NULL, "invalid third parameter");
				goto err;
			}

			percentage = val_copy.data.dbl;
		}

		if (100 < percentage || 0 > percentage)
		{
			*error = zbx_strdup(NULL, "invalid value of percentile");
			goto err;
		}

		pos = 1;
	}
	else if (2 == args_num)
	{
		if (ZBX_VARIANT_STR == args[1].type)
		{
			if (SUCCEED != zbx_is_ushort(args[1].data.str, &pos) || 0 >= pos)
			{
				*error = zbx_strdup(NULL, "invalid third parameter");
				goto err;
			}
		}
		else if (ZBX_VARIANT_UI64 == args[1].type)
		{
			if (0 >= (pos = (int)args[1].data.ui64))
			{
				*error = zbx_strdup(NULL, "invalid third parameter");
				goto err;
			}
		}
		else
		{
			*error = zbx_strdup(NULL, "invalid third parameter");
			goto err;
		}
	}
	else
	{
		pos = 1;
	}

	data = (zbx_expression_query_many_t *)query->data;
	results = (zbx_vector_var_t *)zbx_malloc(NULL, sizeof(zbx_vector_var_t));
	zbx_vector_var_create(results);

	for (i = 0; i < data->itemids.values_num; i++)
	{
		zbx_dc_item_t	*dcitem;
		zbx_variant_t	rate, le_var;
		double		le;
		char		bucket[ZBX_MAX_DOUBLE_LEN + 1];


		if (NULL == (dcitem = get_dcitem(&eval->dcitem_refs, data->itemids.values[i])))
			continue;

		if (ITEM_STATUS_ACTIVE != dcitem->status)
			continue;

		if (HOST_STATUS_MONITORED != dcitem->host.status)
			continue;

		if (ITEM_VALUE_TYPE_NONE == dcitem->value_type)
			continue;

		if (ITEM_STATE_NOTSUPPORTED == dcitem->state)
			continue;

		if (0 != zbx_get_key_param(dcitem->key_orig, pos, bucket, sizeof(bucket)))
			continue;

		zbx_strupper(bucket);

		if (0 == strcmp(bucket, "+INF") || 0 == strcmp(bucket, "INF"))
			le = ZBX_INFINITY;
		else if (SUCCEED != zbx_is_double(bucket, &le))
			continue;

		if (SUCCEED != (ret = zbx_evaluate_RATE(&rate, dcitem, param, ts, error)))
			goto err;

		zbx_variant_set_dbl(&le_var, le);
		zbx_vector_var_append(results, le_var);
		zbx_vector_var_append(results, rate);
	}

	if (ZBX_MIXVALUE_FUNC_BRATE == item_func)
	{
		zbx_variant_set_vector(value, results);
		results = NULL;
		ret = SUCCEED;
	}
	else if (ZBX_ITEM_FUNC_BPERCENTL == item_func)
	{
		zbx_vector_dbl_t	results_tmp;

		zbx_vector_dbl_create(&results_tmp);

		if (SUCCEED == (ret = zbx_eval_var_vector_to_dbl(results, &results_tmp, error)))
		{
			if (SUCCEED == (ret = zbx_eval_calc_histogram_quantile(percentage / 100, &results_tmp,
					log_fn, &result, error)))
			{
				zbx_variant_set_dbl(value, result);
			}
			else
				goto err;
		}
		else
			goto err;

		zbx_vector_dbl_destroy(&results_tmp);
	}
err:
	zbx_free(param);

	if (NULL != results)
	{
		zbx_vector_var_destroy(results);
		zbx_free(results);
	}

	return ret;
}

static int	evaluate_count_many(char *operator, char *pattern, zbx_dc_item_t *dcitem, int seconds, int count,
		const zbx_timespec_t *ts, zbx_vector_var_t *results_vector, char **error)
{
	int				ret = FAIL;
	zbx_eval_count_pattern_data_t	pdata;
	zbx_vector_history_record_t	values;

	zbx_history_record_vector_create(&values);

	if (FAIL == zbx_init_count_pattern(operator, pattern, dcitem->value_type, &pdata, error))
		goto out;

	if (SUCCEED == zbx_vc_get_values(dcitem->itemid, dcitem->value_type, &values, seconds, count, ts))
	{
		zbx_variant_t	v;
		int		result = 0;

		if (FAIL == zbx_execute_count_with_pattern(pattern, dcitem->value_type, &pdata, &values,
				ZBX_MAX_UINT31_1, &result, error))
		{
			goto out;
		}

		zbx_variant_set_dbl(&v, (double)result);
		zbx_vector_var_append(results_vector, v);
	}

	ret = SUCCEED;
out:
	zbx_clear_count_pattern(&pdata);
	zbx_history_record_vector_destroy(&values, dcitem->value_type);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate historical function for multiple items (aggregate        *
 *          checks).                                                          *
 *                                                                            *
 * Parameters: eval     - [IN] evaluation data                                *
 *             query    - [IN] calculated item query                          *
 *             name     - [IN] function name (not zero terminated)            *
 *             len      - [IN] function name length                           *
 *             args_num - [IN] number of function arguments                   *
 *             args     - [IN/OUT] array of function arguments                *
 *             ts       - [IN] function execution time                        *
 *             value    - [OUT] function return value                         *
 *             error    - [OUT]                                               *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	expression_eval_many(zbx_expression_eval_t *eval, zbx_expression_query_t *query, const char *name,
		size_t len, int args_num, zbx_variant_t *args, const zbx_timespec_t *ts, zbx_variant_t *value,
		char **error)
{
	zbx_expression_query_many_t	*data;
	int				ret = FAIL, item_func, count, i;
	time_t				seconds;
	zbx_vector_var_t		*results_var_vector;
	double				result;
	char				*operator = NULL, *pattern = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() %.*s(/%s/%s?[%s],...)", __func__, (int)len, name,
			ZBX_NULL2EMPTY_STR(query->ref.host), ZBX_NULL2EMPTY_STR(query->ref.key),
			ZBX_NULL2EMPTY_STR(query->ref.filter));

	ZBX_UNUSED(args_num);

	data = (zbx_expression_query_many_t *)query->data;
	item_func = get_function_by_name(name, len);

	switch (item_func)
	{
		case ZBX_ITEM_FUNC_EXISTS:
		case ZBX_ITEM_FUNC_ITEMCOUNT:
			if (0 != args_num)
			{
				*error = zbx_strdup(NULL, "invalid number of function parameters");
				goto out;
			}

			expression_eval_exists(eval, query, item_func, value);
			ret = SUCCEED;
			goto out;
		case ZBX_VALUE_FUNC_LAST:
			count = 1;

			if (1 == args_num && ZBX_VARIANT_STR == args[0].type)
			{
				int	tmp;

				if (FAIL == zbx_is_time_suffix(args[0].data.str, &tmp, ZBX_LENGTH_UNLIMITED))
				{
					*error = zbx_strdup(NULL, "invalid second parameter");
					goto out;
				}

				seconds = tmp;
			}
			else
				seconds = 0;

			break;
		case ZBX_VALUE_FUNC_MIN:
		case ZBX_VALUE_FUNC_AVG:
		case ZBX_VALUE_FUNC_MAX:
		case ZBX_VALUE_FUNC_SUM:
			if (args_num >= 2)
			{
				*error = zbx_strdup(NULL, "invalid number of function parameters");
				goto out;
			}
			ZBX_FALLTHROUGH;
		case ZBX_VALUE_FUNC_COUNT:
			if (1 > args_num)
			{
				*error = zbx_strdup(NULL, "invalid number of function parameters");
				goto out;
			}

			if (ZBX_VARIANT_STR == args[0].type)
			{
				int	tmp;

				if (FAIL == zbx_is_time_suffix(args[0].data.str, &tmp, ZBX_LENGTH_UNLIMITED))
				{
					*error = zbx_strdup(NULL, "invalid second parameter");
					goto out;
				}

				seconds = tmp;
			}
			else
			{
				if (SUCCEED != zbx_variant_convert(&args[0], ZBX_VARIANT_DBL))
				{
					*error = zbx_strdup(NULL, "invalid second parameter");
					goto out;
				}

				seconds = (time_t)args[0].data.dbl;
			}
			count = 0;

			if (2 <= args_num)
			{
				if (ZBX_VARIANT_NONE != args[1].type)
				{
					if (SUCCEED != zbx_variant_convert(&args[1], ZBX_VARIANT_STR))
					{
						*error = zbx_strdup(NULL, "invalid third parameter");
						goto out;
					}

					operator = args[1].data.str;
				}

				if (args_num == 3 && ZBX_VARIANT_NONE != args[2].type)
				{
					if (SUCCEED != zbx_variant_convert(&args[2], ZBX_VARIANT_STR))
					{
						*error = zbx_strdup(NULL, "invalid fourth parameter");
						goto out;
					}

					pattern = args[2].data.str;
				}
			}

			break;
		case ZBX_ITEM_FUNC_BPERCENTL:
		case ZBX_MIXVALUE_FUNC_BRATE:
			ret = expression_eval_bucket_rate(eval, query, args_num, args, ts, item_func, value, error);
			goto out;
		default:
			*error = zbx_strdup(NULL, "unsupported function");
			goto out;
	}

	results_var_vector = (zbx_vector_var_t *)zbx_malloc(NULL, sizeof(zbx_vector_var_t));
	zbx_vector_var_create(results_var_vector);

	for (i = 0; i < data->itemids.values_num; i++)
	{
		zbx_dc_item_t			*dcitem;

		if (NULL == (dcitem = get_dcitem(&eval->dcitem_refs, data->itemids.values[i])))
			continue;

		if (ITEM_STATUS_ACTIVE != dcitem->status)
			continue;

		if (HOST_STATUS_MONITORED != dcitem->host.status)
			continue;

		if (ITEM_STATE_NOTSUPPORTED == dcitem->state)
			continue;

		if (ITEM_VALUE_TYPE_NONE == dcitem->value_type)
			continue;

		if (ZBX_VALUE_FUNC_COUNT == item_func)
		{
			if (FAIL == (ret = evaluate_count_many(operator, pattern, dcitem, seconds, count, ts,
					results_var_vector, error)))
			{
				zbx_vector_var_destroy(results_var_vector);
				zbx_free(results_var_vector);
				goto out;
			}
		}
		else
		{
			zbx_vector_history_record_t	values;

			zbx_history_record_vector_create(&values);

			if (SUCCEED == zbx_vc_get_values(dcitem->itemid, dcitem->value_type, &values, seconds, count, ts)
					&& 0 < values.values_num)
			{
				if (ZBX_VALUE_FUNC_LAST == item_func)
				{
					var_vector_append_history_record(&values, dcitem->value_type, results_var_vector);
				}
				else
				{
					zbx_variant_t	v;

					evaluate_history_func(&values, dcitem->value_type, item_func, &result);
					zbx_variant_set_dbl(&v, result);
					zbx_vector_var_append(results_var_vector, v);
				}
			}

			zbx_history_record_vector_destroy(&values, dcitem->value_type);
		}
	}

	zbx_variant_set_vector(value, results_var_vector);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:%s flags:%s", __func__, zbx_result_string(ret),
			zbx_variant_value_desc(value), zbx_variant_type_desc(value));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate historical function.                                     *
 *                                                                            *
 * Parameters: name     - [IN] function name (not zero terminated)            *
 *             len      - [IN] function name length                           *
 *             args_num - [IN] number of function arguments                   *
 *             args     - [IN] array of function arguments                    *
 *             data     - [IN] caller data used for function evaluation       *
 *             ts       - [IN] function execution time                        *
 *             value    - [OUT] function return value                         *
 *             error    - [OUT]                                               *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	expression_eval_history(const char *name, size_t len, int args_num, zbx_variant_t *args,
		void *data, const zbx_timespec_t *ts, zbx_variant_t *value, char **error)
{
	int			ret = FAIL;
	zbx_expression_eval_t	*eval;
	zbx_expression_query_t	*query;
	char			*errmsg = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() function:%.*s", __func__, (int )len, name);

	zbx_variant_set_none(value);

	if (0 == args_num)
	{
		*error = zbx_strdup(NULL, "Cannot evaluate function: invalid number of arguments");
		goto out;
	}

	if (len >= MAX_STRING_LEN)
	{
		*error = zbx_strdup(NULL, "Cannot evaluate function: name too long");
		goto out;
	}

	eval = (zbx_expression_eval_t *)data;

	/* the historical function item query argument is replaced with corresponding itemrefs index */
	query = eval->queries.values[(int) args[0].data.ui64];

	if (ZBX_ITEM_QUERY_ERROR == query->flags)
	{
		*error = zbx_dsprintf(NULL, "Cannot evaluate function: %s", query->error);
		goto out;
	}

	if (0 == (query->flags & ZBX_ITEM_QUERY_MANY))
	{
		ret = expression_eval_one(eval, query, name, len, args_num - 1, args + 1, ts, value, &errmsg);
	}
	else if (ZBX_EXPRESSION_AGGREGATE == eval->mode)
	{
		ret = expression_eval_many(eval, query, name, len, args_num - 1, args + 1, ts, value, &errmsg);
	}
	else
	{
		errmsg = zbx_strdup(NULL, "aggregate queries are not supported");
		ret = FAIL;
	}

	if (SUCCEED != ret)
	{
		*error = zbx_dsprintf(NULL, "Cannot evaluate function: %s", errmsg);
		zbx_free(errmsg);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error:%s", __func__, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate common function.                                         *
 *                                                                            *
 * Parameters: name     - [IN] function name (not zero terminated)            *
 *             len      - [IN] function name length                           *
 *             args_num - [IN] number of function arguments                   *
 *             args     - [IN] array of function arguments                    *
 *             data     - [IN] caller data used for function evaluation       *
 *             ts       - [IN] function execution time                        *
 *             value    - [OUT] function return value                         *
 *             error    - [OUT]                                               *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: there are no custom common functions in expressions items, but   *
 *           it's used to check for /host/key query quoting errors instead.   *
 *                                                                            *
 ******************************************************************************/
static int	expression_eval_common(const char *name, size_t len, int args_num, zbx_variant_t *args,
		void *data, const zbx_timespec_t *ts, zbx_variant_t *value, char **error)
{
	ZBX_UNUSED(data);
	ZBX_UNUSED(ts);
	ZBX_UNUSED(value);

	if (SUCCEED != zbx_is_trigger_function(name, len))
	{
		*error = zbx_strdup(NULL, "Cannot evaluate formula: unsupported function");
		return FAIL;
	}

	if (0 == args_num)
	{
		*error = zbx_strdup(NULL, "Cannot evaluate function: invalid number of arguments");
		return FAIL;
	}

	if (ZBX_VARIANT_STR == args[0].type)
	{
		zbx_item_query_t query;

		if (0 != zbx_eval_parse_query(args[0].data.str, strlen(args[0].data.str), &query))
		{
			zbx_eval_clear_query(&query);
			*error = zbx_strdup(NULL, "Cannot evaluate function: quoted item query argument");
			return FAIL;
		}
	}
	else if (ZBX_VARIANT_VECTOR == args[0].type)
	{
		return SUCCEED;
	}

	*error = zbx_strdup(NULL, "Cannot evaluate function: invalid first argument");
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize expression evaluation data.                            *
 *                                                                            *
 * Parameters: eval     - [IN] evaluation data                                *
 *             mode     - [IN] ZBX_EXPRESSION_NORMAL - support only single    *
 *                             item queries                                   *
 *                             ZBX_EXPRESSION_AGGREGATE - support aggregate   *
 *                             item queries                                   *
 *             ctx      - [IN] parsed expression                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_expression_eval_init(zbx_expression_eval_t *eval, int mode, zbx_eval_context_t *ctx)
{
	int			i;
	zbx_expression_query_t	*query;
	zbx_vector_str_t	filters;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_str_create(&filters);
	zbx_eval_extract_item_refs(ctx, &filters);

	zbx_vector_expression_query_ptr_create(&eval->queries);
	zbx_vector_expression_group_ptr_create(&eval->groups);
	zbx_vector_expression_item_ptr_create(&eval->itemtags);
	zbx_vector_dc_item_create(&eval->dcitem_refs);

	eval->ctx = ctx;
	eval->mode = mode;
	eval->one_num = 0;
	eval->many_num = 0;
	eval->dcitems_num = 0;
	eval->hostid = 0;

	for (i = 0; i < filters.values_num; i++)
	{
		query = expression_create_query(filters.values[i]);
		zbx_vector_expression_query_ptr_append(&eval->queries, query);

		if (ZBX_ITEM_QUERY_UNSET == query->flags)
		{
			query->error = zbx_strdup(NULL, "invalid item query filter");
			query->flags = ZBX_ITEM_QUERY_ERROR;
		}
	}

	zbx_vector_str_clear_ext(&filters, zbx_str_free);
	zbx_vector_str_destroy(&filters);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by expression evaluation data.           *
 *                                                                            *
 * Parameters: eval - [IN] evaluation data                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_expression_eval_clear(zbx_expression_eval_t *eval)
{
	if (0 != eval->one_num)
	{
		zbx_dc_config_clean_items(eval->dcitems_hk, eval->errcodes_hk, eval->one_num);
		zbx_free(eval->dcitems_hk);
		zbx_free(eval->errcodes_hk);
		zbx_free(eval->hostkeys);
	}

	if (0 != eval->dcitems_num)
	{
		zbx_dc_config_clean_items(eval->dcitems, eval->errcodes, eval->dcitems_num);
		zbx_free(eval->dcitems);
		zbx_free(eval->errcodes);
	}

	zbx_vector_dc_item_destroy(&eval->dcitem_refs);

	zbx_vector_expression_item_ptr_clear_ext(&eval->itemtags, expression_item_free);
	zbx_vector_expression_item_ptr_destroy(&eval->itemtags);

	zbx_vector_expression_group_ptr_clear_ext(&eval->groups, expression_group_free);
	zbx_vector_expression_group_ptr_destroy(&eval->groups);

	zbx_vector_expression_query_ptr_clear_ext(&eval->queries, expression_query_free);
	zbx_vector_expression_query_ptr_destroy(&eval->queries);
}

/******************************************************************************
*                                                                             *
* Purpose: resolve calculated item formula with an empty(default host) and    *
*          macro host references, like:                                       *
*          ( two forward slashes , {HOST.HOST}) to host names.                *
*                                                                             *
* Parameters: eval - [IN] evaluation expression                               *
*             item - [IN] calculated item which defines the evaluation        *
*                         expression                                          *
*                                                                             *
*******************************************************************************/
void	zbx_expression_eval_resolve_item_hosts(zbx_expression_eval_t *eval, const zbx_dc_item_t *item)
{
	int	i;

	eval->hostid = item->host.hostid;

	for (i = 0; i < eval->queries.values_num; i++)
	{
		zbx_expression_query_t	*query = eval->queries.values[i];

		if (0 != (ZBX_ITEM_QUERY_HOST_SELF & query->flags) || 0 == strcmp(query->ref.host, "{HOST.HOST}"))
			query->ref.host = zbx_strdup(query->ref.host, item->host.host);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolve calculated item formula macros in filter.                 *
 *                                                                            *
 * Parameters: eval - [IN] evaluation data                                    *
 *             item - [IN] calculated item                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_expression_eval_resolve_filter_macros(zbx_expression_eval_t *eval, const zbx_dc_item_t *item)
{
	int			i;
	zbx_dc_um_handle_t	*um_handle;

	um_handle = zbx_dc_open_user_macros();

	for (i = 0; i < eval->queries.values_num; i++)
	{
		zbx_expression_query_t	*query = eval->queries.values[i];

		zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, item, NULL, NULL, NULL, NULL, NULL,
				&query->ref.filter, ZBX_MACRO_TYPE_QUERY_FILTER, NULL, 0);
	}

	zbx_dc_close_user_macros(um_handle);
}

typedef struct
{
	int	num;
	char	*macro;
}
zbx_macro_index_t;

ZBX_PTR_VECTOR_DECL(macro_index, zbx_macro_index_t *)
ZBX_PTR_VECTOR_IMPL(macro_index, zbx_macro_index_t *)

static int	macro_index_compare(const void *d1, const void *d2)
{
	const int	*i1 = *(const int * const *)d1;
	const int	*i2 = *(const int * const *)d2;

	return *i1 - *i2;
}

static void	macro_index_free(zbx_macro_index_t *index)
{
	zbx_free(index->macro);
	zbx_free(index);
}

static int	resolve_expression_query_macro(const zbx_db_trigger *trigger, int request, int func_num,
		zbx_expression_query_t *query, char **entity, zbx_vector_macro_index_t *indices)
{
	int			id;
	zbx_macro_index_t	*index;

	if (FAIL == (id = zbx_vector_ptr_search((const zbx_vector_ptr_t *)indices, &func_num, macro_index_compare)))
	{
		index = (zbx_macro_index_t *)zbx_malloc(NULL, sizeof(zbx_macro_index_t));
		index->num = func_num;
		index->macro = NULL;
		expr_db_get_trigger_value(trigger, &index->macro, func_num, request);
		zbx_vector_macro_index_append(indices, index);
	}
	else
		index = indices->values[id];

	if (NULL == index->macro)
	{
		query->flags = ZBX_ITEM_QUERY_ERROR;
		query->error = zbx_dsprintf(NULL, ZBX_REQUEST_HOST_HOST == request ? "invalid host \"%s\"" :
				"invalid item key \"%s\"", ZBX_NULL2EMPTY_STR(*entity));
		return FAIL;
	}

	*entity = zbx_strdup(*entity, index->macro);

	return SUCCEED;
}

/******************************************************************************
*                                                                             *
* Purpose: resolve expression with an empty host macro (default host),        *
*          macro host references and item key references, like:               *
*          (two forward slashes, {HOST.HOST}, {HOST.HOST<N>},                 *
*          {ITEM.KEY} and {ITEM.KEY<N>}) to host names and item keys.         *
*                                                                             *
* Parameters: eval    - [IN/OUT] evaluation expression                        *
*             trigger - [IN] trigger which defines evaluation expression      *
*                                                                             *
*******************************************************************************/
void	zbx_expression_eval_resolve_trigger_hosts_items(zbx_expression_eval_t *eval, const zbx_db_trigger *trigger)
{
	zbx_vector_macro_index_t	hosts, item_keys;

	zbx_vector_macro_index_create(&hosts);
	zbx_vector_macro_index_create(&item_keys);

	for (int i = 0; i < eval->queries.values_num; i++)
	{
		int			func_num;
		zbx_expression_query_t	*query = eval->queries.values[i];

		/* resolve host */

		if (0 != (ZBX_ITEM_QUERY_HOST_ONE & query->flags))
			func_num = zbx_expr_macro_index(query->ref.host);
		else if (0 != (ZBX_ITEM_QUERY_HOST_SELF & query->flags))
			func_num = 1;
		else
			func_num = -1;

		if (-1 != func_num && FAIL == resolve_expression_query_macro(trigger, ZBX_REQUEST_HOST_HOST, func_num,
				query, &query->ref.host, &hosts))
		{
			continue;
		}

		/* resolve item key */

		if (0 != (ZBX_ITEM_QUERY_KEY_ONE & query->flags) &&
				-1 != (func_num = zbx_expr_macro_index(query->ref.key)))
		{
			resolve_expression_query_macro(trigger, ZBX_REQUEST_ITEM_KEY, func_num, query, &query->ref.key,
					&item_keys);
		}
	}

	zbx_vector_macro_index_clear_ext(&hosts, macro_index_free);
	zbx_vector_macro_index_clear_ext(&item_keys, macro_index_free);
	zbx_vector_macro_index_destroy(&hosts);
	zbx_vector_macro_index_destroy(&item_keys);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute expression containing history functions.                  *
 *                                                                            *
 * Parameters: eval  - [IN] evaluation data                                   *
 *             ts    - [IN] calculated item                                   *
 *             value - [OUT] expression evaluation result                     *
 *             error - [OUT]                                                  *
 *                                                                            *
 * Return value: SUCCEED - the expression was evaluated successfully          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_expression_eval_execute(zbx_expression_eval_t *eval, const zbx_timespec_t *ts, zbx_variant_t *value,
		char **error)
{
	int	i, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char	*expression = NULL;

		zbx_eval_compose_expression(eval->ctx, &expression);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() expression:'%s'", __func__, expression);
		zbx_free(expression);
	}

	for (i = 0; i < eval->queries.values_num; i++)
	{
		zbx_expression_query_t	*query = eval->queries.values[i];

		if (ZBX_ITEM_QUERY_ERROR != query->flags)
		{
			if (0 != (query->flags & ZBX_ITEM_QUERY_MANY))
				expression_init_query_many(eval, query);
			else
				expression_init_query_one(eval, query);
		}
	}

	/* cache items for functions using one item queries */
	if (0 != eval->one_num)
		expression_cache_dcitems_hk(eval);

	/* cache items for functions using many item queries */
	if (0 != eval->many_num)
		expression_cache_dcitems(eval);

	zbx_variant_set_none(value);

	ret = zbx_eval_execute_ext(eval->ctx, ts, expression_eval_common, expression_eval_history, (void *)eval, value,
			error);

	zbx_vc_flush_stats();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:%s error:%s", __func__, zbx_result_string(ret),
			zbx_variant_value_desc(value), ZBX_NULL2EMPTY_STR(*error));

	return ret;
}
