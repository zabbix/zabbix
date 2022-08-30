/*
 ** Zabbix
 ** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbxserver.h"
#include "log.h"
#include "valuecache.h"
#include "evalfunc.h"
#include "zbxeval.h"
#include "expression.h"

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

/* expression item query */
typedef struct
{
	/* query flags, see ZBX_ITEM_QUERY_* defines */
	zbx_uint32_t		flags;

	/* the item query /host/key?[filter] */
	zbx_item_query_t	ref;

	/* the query error */
	char			*error;

	/* the expression item query data, zbx_expression_query_one_t or zbx_expression_query_many_t */
	void			*data;
}
zbx_expression_query_t;

/* group - hostids cache */
typedef struct
{
	char			*name;
	zbx_vector_uint64_t	hostids;
}
zbx_expression_group_t;

/* item - tags cache */
typedef struct
{
	zbx_uint64_t		itemid;
	zbx_vector_ptr_t	tags;
}
zbx_expression_item_t;

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
 * Purpose: check if key parameter is a wildcard '*'                          *
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
 * Purpose: create expression item query from item query /host/key?[filter]   *
 *                                                                            *
 * Parameters: itemquery - [IN] the item query                                *
 *                                                                            *
 * Return value: The created expression item query.                           *
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

			replace_key_params_dyn(&query->ref.key, ZBX_KEY_TYPE_ITEM, test_key_param_wildcard_cb,
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
 * Purpose: get group from cache by name                                      *
 *                                                                            *
 * Parameters: eval - [IN] the evaluation data                                *
 *             name - [IN] the group name                                     *
 *                                                                            *
 * Return value: The cached group.                                            *
 *                                                                            *
 * Comments: Cache group if necessary.                                        *
 *                                                                            *
 ******************************************************************************/
static zbx_expression_group_t	*expression_get_group(zbx_expression_eval_t *eval, const char *name)
{
	int			i;
	zbx_expression_group_t	*group;

	for (i = 0; i < eval->groups.values_num; i++)
	{
		group = (zbx_expression_group_t *)eval->groups.values[i];

		if (0 == strcmp(group->name, name))
			return group;
	}

	group = (zbx_expression_group_t *)zbx_malloc(NULL, sizeof(zbx_expression_group_t));
	group->name = zbx_strdup(NULL, name);
	zbx_vector_uint64_create(&group->hostids);
	zbx_dc_get_hostids_by_group_name(name, &group->hostids);
	zbx_vector_ptr_append(&eval->groups, group);

	return group;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get item from cache by itemid                                     *
 *                                                                            *
 * Parameters: eval    - [IN] the evaluation data                             *
 *             itemid  - [IN] the item identifier                             *
 *                                                                            *
 * Return value: The cached item.                                             *
 *                                                                            *
 * Comments: Cache item if necessary.                                         *
 *                                                                            *
 ******************************************************************************/
static zbx_expression_item_t	*expression_get_item(zbx_expression_eval_t *eval, zbx_uint64_t itemid)
{
	int		i;
	zbx_expression_item_t	*item;

	for (i = 0; i < eval->itemtags.values_num; i++)
	{
		item = (zbx_expression_item_t *)eval->itemtags.values[i];

		if (item->itemid == itemid)
			return item;
	}

	item = (zbx_expression_item_t *)zbx_malloc(NULL, sizeof(zbx_expression_group_t));
	item->itemid = itemid;
	zbx_vector_ptr_create(&item->tags);
	zbx_dc_get_item_tags(itemid, &item->tags);
	zbx_vector_ptr_append(&eval->itemtags, item);

	return item;
}

static void	expression_item_free(zbx_expression_item_t *item)
{
	zbx_vector_ptr_clear_ext(&item->tags, (zbx_clean_func_t) zbx_free_item_tag);
	zbx_vector_ptr_destroy(&item->tags);
	zbx_free(item);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize one item query                                         *
 *                                                                            *
 * Parameters: eval  - [IN] the evaluation data                               *
 *             query - [IN] the query to initialize                           *
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
 *          %, \ characters for SQL like operation                            *
 *                                                                            *
 ******************************************************************************/
static int	replace_key_param_wildcard_cb(const char *data, int key_type, int level, int num, int quoted, void *cb_data,
		char **param)
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
	unquote_key_param(tmp);
	*param = zbx_dyn_escape_string(tmp, "\\%%");
	zbx_free(tmp);

	/* escaping cannot result in unquotable parameter */
	if (FAIL == quote_key_param(param, quoted))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		zbx_free(*param);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if item key matches the pattern                             *
 *                                                                            *
 * Parameters: item_key - [IN] the item key to match                          *
 *             pattern  - [IN] the pattern                                    *
 *                                                                            *
 ******************************************************************************/
static int	expression_match_item_key(const char *item_key, const AGENT_REQUEST *pattern)
{
	AGENT_REQUEST	key;
	int		i, ret = FAIL;

	init_request(&key);

	if (SUCCEED != parse_item_key(item_key, &key))
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
	free_request(&key);

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
 *          host, key and filter groups                                       *
 *                                                                            *
 * Parameters: eval            - [IN] the evaluation data                     *
 *             query           - [IN] the expression item query               *
 *             groups          - [IN] the groups in filter template           *
 *             filter_template - [IN] the group filter template with {index}  *
 *                                    placeholders referring to a group in    *
 *                                    groups vector                           *
 *             itemhosts       - [out] itemid+hostid pairs matching query     *
 *                                                                            *
 ******************************************************************************/
static void	expression_get_item_candidates(zbx_expression_eval_t *eval, const zbx_expression_query_t *query,
		const zbx_vector_str_t *groups, const char *filter_template, zbx_vector_uint64_pair_t *itemhosts)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL, *esc, *clause = "where";
	size_t		sql_alloc = 0, sql_offset = 0;
	AGENT_REQUEST	pattern;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select i.itemid,i.hostid");

	if (0 != (query->flags & ZBX_ITEM_QUERY_KEY_SOME))
	{
		init_request(&pattern);
		if (SUCCEED != parse_item_key(query->ref.key, &pattern))
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
		esc = DBdyn_escape_string(query->ref.host);
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
		replace_key_params_dyn(&key, ZBX_KEY_TYPE_ITEM, replace_key_param_wildcard_cb, NULL, NULL, 0);

		esc = DBdyn_escape_string(key);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " %s i.key_ like '%s'", clause, esc);
		zbx_free(esc);
		zbx_free(key);
		clause = "and";
	}
	else if (0 != (query->flags & ZBX_ITEM_QUERY_KEY_ONE))
	{
		esc = DBdyn_escape_string(query->ref.key);
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

			if (SUCCEED != is_uint64_n(filter_template + token.loc.l + 1, token.loc.r - token.loc.l - 1,
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
				DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", group->hostids.values,
						group->hostids.values_num);
			}
			else
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " 1=0");

			last_pos = token.loc.r + 1;
			pos = token.loc.r;
		}

		if ('\0' != filter_template[last_pos])
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, filter_template + last_pos);
	}

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
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
	DBfree_result(result);

	if (0 != (query->flags & ZBX_ITEM_QUERY_KEY_SOME))
		free_request(&pattern);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the item matches the tag                                 *
 *                                                                            *
 * Parameters: item - [IN] the item with tags                                 *
 *             tag  - [IN] the tag to match in format <tag name>[:<tag value>]*
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
		zbx_item_tag_t	*itemtag = (zbx_item_tag_t *)item->tags.values[i];

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
 * Purpose: evaluate filter function                                          *
 *                                                                            *
 * Parameters: name     - [IN] the function name (not zero terminated)        *
 *             len      - [IN] the function name length                       *
 *             args_num - [IN] the number of function arguments               *
 *             args     - [IN] an array of the function arguments.            *
 *             data     - [IN] the caller data used for function evaluation   *
 *             ts       - [IN] the function execution time                    *
 *             value    - [OUT] the function return value                     *
 *             error    - [OUT] the error message if function failed          *
 *                                                                            *
 * Return value: SUCCEED - the function was evaluated successfully            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: The group/tag comparisons in filter are converted to function    *
 *           calls that are evaluated by this callback.                       *
 *                                                                            *
 ******************************************************************************/
static int	expression_eval_filter(const char *name, size_t len, int args_num, const zbx_variant_t *args,
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
 * Purpose: initialize many item query                                        *
 *                                                                            *
 * Parameters: eval    - [IN] the evaluation data                             *
 *             query   - [IN] the query to initialize                         *
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

			if (SUCCEED != zbx_eval_execute_ext(&ctx, NULL, expression_eval_filter, NULL, (void *)&eval_data,
					&filter_value, &errmsg))
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
 * Purpose: cache items used in one item queries                              *
 *                                                                            *
 * Parameters: eval - [IN] the evaluation data                                *
 *                                                                            *
 ******************************************************************************/
static void	expression_cache_dcitems_hk(zbx_expression_eval_t *eval)
{
	int	i;

	eval->hostkeys = (zbx_host_key_t *)zbx_malloc(NULL, sizeof(zbx_host_key_t) * eval->one_num);
	eval->dcitems_hk = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * eval->one_num);
	eval->errcodes_hk = (int *)zbx_malloc(NULL, sizeof(int) * eval->one_num);

	for (i = 0; i < eval->queries.values_num; i++)
	{
		zbx_expression_query_t	*query = (zbx_expression_query_t *)eval->queries.values[i];
		zbx_expression_query_one_t	*data;

		if (0 != (query->flags & ZBX_ITEM_QUERY_MANY) || ZBX_ITEM_QUERY_ERROR == query->flags)
			continue;

		data = (zbx_expression_query_one_t *)query->data;

		eval->hostkeys[data->dcitem_hk_index].host = query->ref.host;
		eval->hostkeys[data->dcitem_hk_index].key = query->ref.key;
	}

	DCconfig_get_items_by_keys(eval->dcitems_hk, eval->hostkeys, eval->errcodes_hk, eval->one_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: dcitem reference vector lookup functions                          *
 *                                                                            *
 ******************************************************************************/
static	int	compare_dcitems_by_itemid(const void *d1, const void *d2)
{
	DC_ITEM	*dci1 = *(DC_ITEM **)d1;
	DC_ITEM	*dci2 = *(DC_ITEM **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(dci1->itemid, dci2->itemid);

	return 0;
}

static int	expression_find_dcitem_by_itemid(const void *d1, const void *d2)
{
	zbx_uint64_t	itemid = **(zbx_uint64_t **)d1;
	DC_ITEM		*dci = *(DC_ITEM **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(itemid, dci->itemid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: cache items used in many item queries                             *
 *                                                                            *
 * Parameters: eval - [IN] the evaluation data                                *
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

			zbx_vector_ptr_append(&eval->dcitem_refs, &eval->dcitems_hk[i]);
		}

		zbx_vector_ptr_sort(&eval->dcitem_refs, compare_dcitems_by_itemid);
	}

	for (i = 0; i < eval->queries.values_num; i++)
	{
		zbx_expression_query_t	*query = (zbx_expression_query_t *)eval->queries.values[i];
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
		if (FAIL != zbx_vector_ptr_bsearch(&eval->dcitem_refs, &itemids.values[i], expression_find_dcitem_by_itemid))
		{
			zbx_vector_uint64_remove(&itemids, i);
			continue;
		}
		i++;
	}

	if (0 != (eval->dcitems_num = itemids.values_num))
	{
		zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		eval->dcitems = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * itemids.values_num);
		eval->errcodes = (int *)zbx_malloc(NULL, sizeof(int) * itemids.values_num);

		DCconfig_get_items_by_itemids(eval->dcitems, itemids.values, eval->errcodes, itemids.values_num);

		for (i = 0; i < itemids.values_num; i++)
		{
			if (SUCCEED != eval->errcodes[i])
				continue;

			zbx_vector_ptr_append(&eval->dcitem_refs, &eval->dcitems[i]);
		}

		zbx_vector_ptr_sort(&eval->dcitem_refs, compare_dcitems_by_itemid);
	}

	zbx_vector_uint64_destroy(&itemids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate historical function for one item query                   *
 *                                                                            *
 * Parameters: eval     - [IN] the evaluation data                            *
 *             query    - [IN] the item query                                 *
 *             name     - [IN] the function name (not zero terminated)        *
 *             len      - [IN] the function name length                       *
 *             args_num - [IN] the number of function arguments               *
 *             args     - [IN] an array of the function arguments.            *
 *             data     - [IN] the caller data used for function evaluation   *
 *             ts       - [IN] the function execution time                    *
 *             value    - [OUT] the function return value                     *
 *             error    - [OUT] the error message if function failed          *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	expression_eval_one(zbx_expression_eval_t *eval, zbx_expression_query_t *query, const char *name,
		size_t len, int args_num, const zbx_variant_t *args, const zbx_timespec_t *ts, zbx_variant_t *value, char **error)
{
	char			func_name[MAX_STRING_LEN], *params = NULL;
	size_t			params_alloc = 0, params_offset = 0;
	DC_ITEM			*item;
	int			i, ret = FAIL;
	zbx_expression_query_one_t	*data;

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

	if (0 == args_num)
	{
		ret = evaluate_function(value, item, func_name, "", ts, error);
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
				zbx_strquote_alloc(&params, &params_alloc, &params_offset, args[i].data.str);
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

	ret = evaluate_function(value, item, func_name, ZBX_NULL2EMPTY_STR(params), ts, error);
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
 * Purpose: calculate minimum value from the history value vector             *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
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
 * Purpose: calculate maximum value from the history value vector             *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
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
 * Purpose: calculate sum of values from the history value vector             *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
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
 * Purpose: calculate average value of values from the history value vector   *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_avg(zbx_vector_history_record_t *values, int value_type, double *result)
{
	evaluate_history_func_sum(values, value_type, result);
	*result /= values->values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate number of values in value vector                        *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_count(zbx_vector_history_record_t *values, double *result)
{
	*result = (double)values->values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate the last (newest) value in value vector                 *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             result      - [OUT] the resulting value                        *
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
 * Purpose: calculate function with values from value vector                  *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             func        - [IN] the function to calculate. Only             *
 *                           ZBX_VALUE_FUNC_MIN, ZBX_VALUE_FUNC_AVG,          *
 *                           ZBX_VALUE_FUNC_MAX, ZBX_VALUE_FUNC_SUM,          *
 *                           ZBX_VALUE_FUNC_COUNT, ZBX_VALUE_FUNC_LAST        *
 *                           functions are supported.                         *
 *             result      - [OUT] the resulting value                        *
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
 * Purpose: get item from cache by itemid                                     *
 *                                                                            *
 * Parameters: eval    - [IN] the evaluation data                             *
 *             itemid  - [IN] the item identifier                             *
 *                                                                            *
 * Return value: The cached item.                                             *
 *                                                                            *
 ******************************************************************************/
static DC_ITEM	*get_dcitem(zbx_vector_ptr_t *dcitem_refs, zbx_uint64_t itemid)
{
	int	index;

	if (FAIL == (index = zbx_vector_ptr_bsearch(dcitem_refs, &itemid, expression_find_dcitem_by_itemid)))
		return NULL;

	return dcitem_refs->values[index];
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate functions 'exists_foreach' and 'item_count'              *
 *          for multiple items                                                *
 *                                                                            *
 * Parameters: eval      - [IN] the evaluation data                           *
 *             query     - [IN] the calculated item query                     *
 *             item_func - [IN] the function id                               *
 *             value     - [OUT] the function return value                    *
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
	zbx_vector_dbl_t		results;

	zbx_vector_dbl_create(&results);

	data = (zbx_expression_query_many_t *)query->data;

	for (i = 0; i < data->itemids.values_num; i++)
	{
		DC_ITEM	*dcitem;

		if (NULL == (dcitem = get_dcitem(&eval->dcitem_refs, data->itemids.values[i])))
			continue;

		if (ITEM_STATUS_ACTIVE != dcitem->status)
			continue;

		if (HOST_STATUS_MONITORED != dcitem->host.status)
			continue;

		zbx_vector_dbl_append(&results, 1);
	}

	if (ZBX_ITEM_FUNC_EXISTS == item_func)
	{
		zbx_vector_dbl_t	*v;

		v = (zbx_vector_dbl_t *)zbx_malloc(NULL, sizeof(zbx_vector_dbl_t));
		*v = results;
		zbx_variant_set_dbl_vector(value, v);
	}
	else
	{
		zbx_variant_set_ui64(value, (zbx_uint64_t)results.values_num);
		zbx_vector_dbl_destroy(&results);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate functions 'bucket_rate_foreach' for 'histogram_quantile' *
 *          and evaluate functions 'bucket_percentile'                        *
 *                                                                            *
 * Parameters: eval      - [IN] the evaluation data                           *
 *             query     - [IN] the calculated item query                     *
 *             args_num  - [IN] the number of function arguments              *
 *             args      - [IN] an array of the function arguments.           *
 *             data      - [IN] the caller data used for function evaluation  *
 *             ts        - [IN] the function execution time                   *
 *             item_func - [IN] the function id                               *
 *             value     - [OUT] the function return value                    *
 *             error     - [OUT] the error message if function failed         *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	expression_eval_bucket_rate(zbx_expression_eval_t *eval, zbx_expression_query_t *query,
		int args_num, const zbx_variant_t *args, const zbx_timespec_t *ts, int item_func, zbx_variant_t *value,
		char **error)
{
	zbx_expression_query_many_t	*data;
	int				i, pos, ret = FAIL;
	zbx_vector_dbl_t		*results = NULL;
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
			if (SUCCEED != is_ushort(args[1].data.str, &pos) || 0 >= pos)
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
	results = (zbx_vector_dbl_t *)zbx_malloc(NULL, sizeof(zbx_vector_dbl_t));
	zbx_vector_dbl_create(results);

	for (i = 0; i < data->itemids.values_num; i++)
	{
		DC_ITEM		*dcitem;
		zbx_variant_t	rate;
		double		le;
		char		bucket[ZBX_MAX_DOUBLE_LEN + 1];

		if (NULL == (dcitem = get_dcitem(&eval->dcitem_refs, data->itemids.values[i])))
			continue;

		if (ITEM_STATUS_ACTIVE != dcitem->status)
			continue;

		if (HOST_STATUS_MONITORED != dcitem->host.status)
			continue;

		if (ITEM_VALUE_TYPE_FLOAT != dcitem->value_type && ITEM_VALUE_TYPE_UINT64 != dcitem->value_type)
			continue;

		if (0 != get_key_param(dcitem->key_orig, pos, bucket, sizeof(bucket)))
			continue;

		zbx_strupper(bucket);

		if (0 == strcmp(bucket, "+INF") || 0 == strcmp(bucket, "INF"))
			le = ZBX_INFINITY;
		else if (SUCCEED != is_double(bucket, &le))
			continue;

		if (SUCCEED != (ret = zbx_evaluate_RATE(&rate, dcitem, param, ts, error)))
			goto err;

		zbx_vector_dbl_append(results, le);
		zbx_vector_dbl_append(results, rate.data.dbl);
	}

	if (ZBX_MIXVALUE_FUNC_BRATE == item_func)
	{
		zbx_variant_set_dbl_vector(value, results);
		results = NULL;
		ret = SUCCEED;
	}
	else if (ZBX_ITEM_FUNC_BPERCENTL == item_func && SUCCEED == (
			ret = zbx_eval_calc_histogram_quantile(percentage / 100, results, log_fn, &result, error)))
	{
		zbx_variant_set_dbl(value, result);
	}
err:
	zbx_free(param);

	if (NULL != results)
	{
		zbx_vector_dbl_destroy(results);
		zbx_free(results);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate historical function for multiple items (aggregate checks)*
 *                                                                            *
 * Parameters: eval     - [IN] the evaluation data                            *
 *             query    - [IN] the calculated item query                      *
 *             name     - [IN] the function name (not zero terminated)        *
 *             len      - [IN] the function name length                       *
 *             args_num - [IN] the number of function arguments               *
 *             args     - [IN] an array of the function arguments.            *
 *             data     - [IN] the caller data used for function evaluation   *
 *             ts       - [IN] the function execution time                    *
 *             value    - [OUT] the function return value                     *
 *             error    - [OUT] the error message if function failed          *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	expression_eval_many(zbx_expression_eval_t *eval, zbx_expression_query_t *query, const char *name,
		size_t len, int args_num, const zbx_variant_t *args, const zbx_timespec_t *ts, zbx_variant_t *value,
		char **error)
{
	zbx_expression_query_many_t	*data;
	int				ret = FAIL, item_func, count, seconds, i;
	zbx_vector_history_record_t	values;
	zbx_vector_dbl_t		*results_vector;
	double				result;
	zbx_variant_t			arg;

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
			seconds = 0;
			break;
		case ZBX_VALUE_FUNC_MIN:
		case ZBX_VALUE_FUNC_AVG:
		case ZBX_VALUE_FUNC_MAX:
		case ZBX_VALUE_FUNC_SUM:
		case ZBX_VALUE_FUNC_COUNT:
			if (1 != args_num)
			{
				*error = zbx_strdup(NULL, "invalid number of function parameters");
				goto out;
			}

			if (ZBX_VARIANT_STR == args[0].type)
			{
				if (FAIL == is_time_suffix(args[0].data.str, &seconds, ZBX_LENGTH_UNLIMITED))
				{
					*error = zbx_strdup(NULL, "invalid second parameter");
					goto out;
				}
			}
			else
			{
				zbx_variant_copy(&arg, &args[0]);

				if (SUCCEED != zbx_variant_convert(&arg, ZBX_VARIANT_DBL))
				{
					zbx_variant_clear(&arg);
					*error = zbx_strdup(NULL, "invalid second parameter");
					goto out;
				}

				seconds = arg.data.dbl;
				zbx_variant_clear(&arg);
			}
			count = 0;

			break;
		case ZBX_ITEM_FUNC_BPERCENTL:
		case ZBX_MIXVALUE_FUNC_BRATE:
			ret = expression_eval_bucket_rate(eval, query, args_num, args, ts, item_func, value, error);
			goto out;
		default:
			*error = zbx_strdup(NULL, "unsupported function");
			goto out;
	}

	results_vector = (zbx_vector_dbl_t *)zbx_malloc(NULL, sizeof(zbx_vector_dbl_t));
	zbx_vector_dbl_create(results_vector);

	for (i = 0; i < data->itemids.values_num; i++)
	{
		DC_ITEM	*dcitem;

		if (NULL == (dcitem = get_dcitem(&eval->dcitem_refs, data->itemids.values[i])))
			continue;

		if (ITEM_STATUS_ACTIVE != dcitem->status)
			continue;

		if (HOST_STATUS_MONITORED != dcitem->host.status)
			continue;

		if (ITEM_VALUE_TYPE_FLOAT != dcitem->value_type && ITEM_VALUE_TYPE_UINT64 != dcitem->value_type)
			continue;

		zbx_history_record_vector_create(&values);

		if (SUCCEED == zbx_vc_get_values(dcitem->itemid, dcitem->value_type, &values, seconds, count, ts) &&
				0 < values.values_num)
		{
			evaluate_history_func(&values, dcitem->value_type, item_func, &result);
			zbx_vector_dbl_append(results_vector, result);
		}

		zbx_history_record_vector_destroy(&values, dcitem->value_type);
	}

	zbx_variant_set_dbl_vector(value, results_vector);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:%s flags:%s", __func__, zbx_result_string(ret),
			zbx_variant_value_desc(value), zbx_variant_type_desc(value));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate historical function                                      *
 *                                                                            *
 * Parameters: name     - [IN] the function name (not zero terminated)        *
 *             len      - [IN] the function name length                       *
 *             args_num - [IN] the number of function arguments               *
 *             args     - [IN] an array of the function arguments.            *
 *             data     - [IN] the caller data used for function evaluation   *
 *             ts       - [IN] the function execution time                    *
 *             value    - [OUT] the function return value                     *
 *             error    - [OUT] the error message if function failed          *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	expression_eval_history(const char *name, size_t len, int args_num, const zbx_variant_t *args,
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
	query = (zbx_expression_query_t *)eval->queries.values[(int) args[0].data.ui64];

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
 * Purpose: evaluate common function                                          *
 *                                                                            *
 * Parameters: name     - [IN] the function name (not zero terminated)        *
 *             len      - [IN] the function name length                       *
 *             args_num - [IN] the number of function arguments               *
 *             args     - [IN] an array of the function arguments.            *
 *             data     - [IN] the caller data used for function evaluation   *
 *             ts       - [IN] the function execution time                    *
 *             value    - [OUT] the function return value                     *
 *             error    - [OUT] the error message if function failed          *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: There are no custom common functions in expressions items, but   *
 *           it's used to check for /host/key query quoting errors instead.   *
 *                                                                            *
 ******************************************************************************/
static int	expression_eval_common(const char *name, size_t len, int args_num, const zbx_variant_t *args,
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
	else if (ZBX_VARIANT_DBL_VECTOR == args[0].type)
	{
		return SUCCEED;
	}

	*error = zbx_strdup(NULL, "Cannot evaluate function: invalid first argument");
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize expression evaluation data                             *
 *                                                                            *
 * Parameters: eval     - [IN] the evaluation data                            *
 *             mode     - [IN] ZBX_EXPRESSION_NORMAL - support only single    *
 *                             item queries                                   *
 *                             ZBX_EXPRESSION_AGGREGATE - support aggregate   *
 *                             item queries                                   *
 *             ctx      - [IN] the parsed expression                          *
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

	zbx_vector_ptr_create(&eval->queries);
	zbx_vector_ptr_create(&eval->groups);
	zbx_vector_ptr_create(&eval->itemtags);
	zbx_vector_ptr_create(&eval->dcitem_refs);

	eval->ctx = ctx;
	eval->mode = mode;
	eval->one_num = 0;
	eval->many_num = 0;
	eval->dcitems_num = 0;
	eval->hostid = 0;

	for (i = 0; i < filters.values_num; i++)
	{
		query = expression_create_query(filters.values[i]);
		zbx_vector_ptr_append(&eval->queries, query);

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
 * Purpose: free resources allocated by expression evaluation data            *
 *                                                                            *
 * Parameters: eval     - [IN] the evaluation data                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_expression_eval_clear(zbx_expression_eval_t *eval)
{
	if (0 != eval->one_num)
	{
		DCconfig_clean_items(eval->dcitems_hk, eval->errcodes_hk, eval->one_num);
		zbx_free(eval->dcitems_hk);
		zbx_free(eval->errcodes_hk);
		zbx_free(eval->hostkeys);
	}

	if (0 != eval->dcitems_num)
	{
		DCconfig_clean_items(eval->dcitems, eval->errcodes, eval->dcitems_num);
		zbx_free(eval->dcitems);
		zbx_free(eval->errcodes);
	}

	zbx_vector_ptr_destroy(&eval->dcitem_refs);

	zbx_vector_ptr_clear_ext(&eval->itemtags, (zbx_clean_func_t) expression_item_free);
	zbx_vector_ptr_destroy(&eval->itemtags);

	zbx_vector_ptr_clear_ext(&eval->groups, (zbx_clean_func_t) expression_group_free);
	zbx_vector_ptr_destroy(&eval->groups);

	zbx_vector_ptr_clear_ext(&eval->queries, (zbx_clean_func_t) expression_query_free);
	zbx_vector_ptr_destroy(&eval->queries);
}

/******************************************************************************
*                                                                             *
* Purpose: resolve calculated item formula with an empty(default host) and    *
*          macro host references, like:                                       *
*          ( two forward slashes , {HOST.HOST}) to host names                 *
*                                                                             *
* Parameters: eval - [IN] the evaluation expression                           *
*             item - [IN] the calculated item which defines the evaluation    *
*                         expression                                          *
*                                                                             *
*******************************************************************************/
void	zbx_expression_eval_resolve_item_hosts(zbx_expression_eval_t *eval, const DC_ITEM *item)
{
	int	i;

	eval->hostid = item->host.hostid;

	for (i = 0; i < eval->queries.values_num; i++)
	{
		zbx_expression_query_t	*query = (zbx_expression_query_t *)eval->queries.values[i];

		if (0 != (ZBX_ITEM_QUERY_HOST_SELF & query->flags) || 0 == strcmp(query->ref.host, "{HOST.HOST}"))
			query->ref.host = zbx_strdup(query->ref.host, item->host.host);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_expression_eval_resolve_filter_macros                        *
 *                                                                            *
 * Purpose: resolve calculated item formula macros in filter                  *
 *                                                                            *
 * Parameters: eval - [IN] the evaluation data                                *
 *             item - [IN] the calculated item                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_expression_eval_resolve_filter_macros(zbx_expression_eval_t *eval, const DC_ITEM *item)
{
	int			i;
	zbx_dc_um_handle_t	*um_handle;

	um_handle = zbx_dc_open_user_macros();

	for (i = 0; i < eval->queries.values_num; i++)
	{
		zbx_expression_query_t	*query = (zbx_expression_query_t *)eval->queries.values[i];

		zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, item, NULL, NULL, NULL, NULL, NULL,
				&query->ref.filter, MACRO_TYPE_QUERY_FILTER, NULL, 0);
	}

	zbx_dc_close_user_macros(um_handle);
}

typedef struct
{
	int	num;
	char	*host;
}
zbx_host_index_t;

static int	host_index_compare(const void *d1, const void *d2)
{
	const int	*i1 = *(const int **)d1;
	const int	*i2 = *(const int **)d2;

	return *i1 - *i2;
}

static void	host_index_free(zbx_host_index_t *index)
{
	zbx_free(index->host);
	zbx_free(index);
}

/******************************************************************************
*                                                                             *
* Purpose: resolve expression with an empty host macro (default host)         *
*          and macro host references, like:                                   *
*          (two forward slashes, {HOST.HOST}, {HOST.HOST<N>},                 *
*          {ITEM.KEY} and {ITEM.KEY<N>}) to host names and item keys          *
*                                                                             *
* Parameters: eval    - [IN/OUT] the evaluation expression                    *
*             trigger - [IN] trigger which defines the evaluation expression  *
*                                                                             *
*******************************************************************************/
void	zbx_expression_eval_resolve_trigger_hosts_items(zbx_expression_eval_t *eval, const ZBX_DB_TRIGGER *trigger)
{
	int			i, func_num, index;
	zbx_vector_ptr_t	hosts;
	zbx_host_index_t	*hi;

	zbx_vector_ptr_create(&hosts);

	for (i = 0; i < eval->queries.values_num; i++)
	{
		zbx_expression_query_t	*query = (zbx_expression_query_t *)eval->queries.values[i];

		if (0 != (ZBX_ITEM_QUERY_HOST_ONE & query->flags))
			func_num = zbx_host_macro_index(query->ref.host);
		else if (0 != (ZBX_ITEM_QUERY_HOST_SELF & query->flags))
			func_num = 1;
		else
			func_num = -1;

		if (-1 == func_num)
			continue;

		if (FAIL == (index = zbx_vector_ptr_search(&hosts, &func_num, host_index_compare)))
		{
			hi = (zbx_host_index_t *)zbx_malloc(NULL, sizeof(zbx_host_index_t));
			hi->num = func_num;
			hi->host = NULL;
			DBget_trigger_value(trigger, &hi->host, func_num, ZBX_REQUEST_HOST_HOST);
			zbx_vector_ptr_append(&hosts, hi);
		}
		else
			hi = (zbx_host_index_t *)hosts.values[index];

		if (NULL != hi->host)
		{
			query->ref.host = zbx_strdup(query->ref.host, hi->host);
			DBget_trigger_value(trigger, &query->ref.key, func_num, ZBX_REQUEST_ITEM_KEY);
		}
		else
		{
			query->error = zbx_dsprintf(NULL, "invalid host \"%s\"", ZBX_NULL2EMPTY_STR(query->ref.host));
			query->flags = ZBX_ITEM_QUERY_ERROR;
		}
	}

	zbx_vector_ptr_clear_ext(&hosts, (zbx_clean_func_t)host_index_free);
	zbx_vector_ptr_destroy(&hosts);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute expression containing history functions                   *
 *                                                                            *
 * Parameters: eval  - [IN] the evaluation data                               *
 *             ts    - [IN] the calculated item                               *
 *             value - [OUT] the expression evaluation result                 *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the expression was evaluated successfully.         *
 *               FAIL    - otherwise.                                         *
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
		zbx_expression_query_t	*query = (zbx_expression_query_t *)eval->queries.values[i];

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
