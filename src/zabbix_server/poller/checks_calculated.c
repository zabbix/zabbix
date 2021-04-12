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

#include "checks_calculated.h"
#include "zbxserver.h"
#include "log.h"
#include "../../libs/zbxserver/evalfunc.h"
#include "checks_aggregate.h"

#define ZBX_CALC_QUERY_UNSET		0x0000

#define ZBX_CALC_QUERY_HOST_SELF	0x0001
#define ZBX_CALC_QUERY_HOST_ONE		0x0002
#define ZBX_CALC_QUERY_HOST_ANY		0x0004

#define ZBX_CALC_QUERY_KEY_ONE		0x0010
#define ZBX_CALC_QUERY_KEY_SOME		0x0020
#define ZBX_CALC_QUERY_KEY_ANY		0x0040
#define ZBX_CALC_QUERY_FILTER		0x0100

#define ZBX_CALC_QUERY_ERROR		0x8000

#define ZBX_CALC_QUERY_MANY		(ZBX_CALC_QUERY_HOST_ANY |\
					ZBX_CALC_QUERY_KEY_SOME | ZBX_CALC_QUERY_KEY_ANY |\
					ZBX_CALC_QUERY_FILTER)

#define ZBX_CALC_QUERY_ITEM_ANY		(ZBX_CALC_QUERY_HOST_ANY | ZBX_CALC_QUERY_KEY_ANY)

/* one item query data - index in hostkeys items */
typedef struct
{
	int	dcitem_hk_index;
}
zbx_calc_query_one_t;

/* many item query data - matching itemids */
typedef struct
{
	zbx_vector_uint64_t	itemids;
}
zbx_calc_query_many_t;

/* calculated item query */
typedef struct
{
	/* query flags, see see ZBX_CALC_QUERY_* defines */
	zbx_uint32_t		flags;

	/* the item query /host/key?[filter] */
	zbx_item_query_t	ref;

	/* the query error */
	char			*error;

	/* the calculated item query data, zbx_calc_query_one_t or zbx_calc_query_many_t */
	void			*data;
}
zbx_calc_query_t;

/* group - hostids cache */
typedef struct
{
	char			*name;
	zbx_vector_uint64_t	hostids;
}
zbx_calc_group_t;

/* item - tags cache */
typedef struct
{
	zbx_uint64_t		itemid;
	zbx_vector_ptr_t	tags;
}
zbx_calc_item_t;

typedef struct
{
	zbx_vector_ptr_t	queries;
	int			one_num;
	int			many_num;
	DC_ITEM *calcitem;

	/* cache to resolve one item queries */
	zbx_host_key_t		*hostkeys;
	DC_ITEM			*dcitems_hk;
	int			*errcodes_hk;

	/* cache to resolve many item queries */
	zbx_vector_ptr_t	groups;
	zbx_vector_ptr_t	itemtags;
	zbx_vector_ptr_t	dcitem_refs;
	DC_ITEM			*dcitems;
	int			*errcodes;
	int			dcitems_num;
}
zbx_calc_eval_t;

static void	calc_query_free_one(zbx_calc_query_one_t *query)
{
	zbx_free(query);
}

static void	calc_query_free_many(zbx_calc_query_many_t *query)
{
	zbx_vector_uint64_destroy(&query->itemids);
	zbx_free(query);
}

static void	calc_query_free(zbx_calc_query_t *query)
{
	zbx_eval_clear_query(&query->ref);

	if (ZBX_CALC_QUERY_ERROR == query->flags)
		zbx_free(query->error);
	else if (0 != (query->flags & ZBX_CALC_QUERY_MANY))
		calc_query_free_many((zbx_calc_query_many_t*) query->data);
	else
		calc_query_free_one((zbx_calc_query_one_t*) query->data);

	zbx_free(query);
}

/******************************************************************************
 *                                                                            *
 * Function: test_key_param_wildcard_cb                                       *
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
 * Function: calc_create_query                                                *
 *                                                                            *
 * Purpose: create calculated item query from item query /host/key?[filter]   *
 *                                                                            *
 * Parameters: itemquery - [IN] the item query                                *
 *                                                                            *
 * Return value: The created calculated item query.                           *
 *                                                                            *
 ******************************************************************************/
static zbx_calc_query_t*	calc_create_query(const char *itemquery)
{
	zbx_calc_query_t	*query;

	query = (zbx_calc_query_t *)zbx_malloc(NULL, sizeof(zbx_calc_query_t));
	memset(query, 0, sizeof(zbx_calc_query_t));

	query->flags = ZBX_CALC_QUERY_UNSET;

	if (0 != zbx_eval_parse_query(itemquery, strlen(itemquery), &query->ref))
	{
		if (NULL == query->ref.host)
			query->flags |= ZBX_CALC_QUERY_HOST_SELF;
		else if ('*' == *query->ref.host)
			query->flags |= ZBX_CALC_QUERY_HOST_ANY;
		else
			query->flags |= ZBX_CALC_QUERY_HOST_ONE;

		if (NULL != query->ref.filter)
			query->flags |= ZBX_CALC_QUERY_FILTER;

		if ('*' == *query->ref.key)
		{
			query->flags |= ZBX_CALC_QUERY_KEY_ANY;
		}
		else if (NULL != strchr(query->ref.key, '*'))
		{
			int	wildcard = 0;

			replace_key_params_dyn(&query->ref.key, ZBX_KEY_TYPE_ITEM, test_key_param_wildcard_cb,
					&wildcard, NULL, 0);

			if (0 != wildcard)
				query->flags |= ZBX_CALC_QUERY_KEY_SOME;
			else
				query->flags |= ZBX_CALC_QUERY_KEY_ONE;
		}
		else
			query->flags |= ZBX_CALC_QUERY_KEY_ONE;
	}

	return query;
}

/******************************************************************************
 *                                                                            *
 * Function: calc_group_free                                                  *
 *                                                                            *
 ******************************************************************************/
static void	calc_group_free(zbx_calc_group_t *group)
{
	zbx_free(group->name);
	zbx_vector_uint64_destroy(&group->hostids);
	zbx_free(group);
}

/******************************************************************************
 *                                                                            *
 * Function: calc_get_group                                                   *
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
static zbx_calc_group_t	*calc_get_group(zbx_calc_eval_t *eval, const char *name)
{
	int			i;
	zbx_calc_group_t	*group;

	for (i = 0; i < eval->groups.values_num; i++)
	{
		group = (zbx_calc_group_t *)eval->groups.values[i];

		if (0 == strcmp(group->name, name))
			return group;
	}

	group = (zbx_calc_group_t *)zbx_malloc(NULL, sizeof(zbx_calc_group_t));
	group->name = zbx_strdup(NULL, name);
	zbx_vector_uint64_create(&group->hostids);
	zbx_dc_get_hostids_by_group_name(name, &group->hostids);
	zbx_vector_ptr_append(&eval->groups, group);

	return group;
}

/******************************************************************************
 *                                                                            *
 * Function: calc_get_item                                                    *
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
static zbx_calc_item_t	*calc_get_item(zbx_calc_eval_t *eval, zbx_uint64_t itemid)
{
	int		i;
	zbx_calc_item_t	*item;

	for (i = 0; i < eval->itemtags.values_num; i++)
	{
		item = (zbx_calc_item_t *)eval->itemtags.values[i];

		if (item->itemid == itemid)
			return item;
	}

	item = (zbx_calc_item_t *)zbx_malloc(NULL, sizeof(zbx_calc_group_t));
	item->itemid = itemid;
	zbx_vector_ptr_create(&item->tags);
	zbx_dc_get_item_tags(itemid, &item->tags);
	zbx_vector_ptr_append(&eval->itemtags, item);

	return item;
}

/******************************************************************************
 *                                                                            *
 * Function: calc_item_free                                                   *
 *                                                                            *
 ******************************************************************************/
static void	calc_item_free(zbx_calc_item_t *item)
{
	zbx_vector_ptr_clear_ext(&item->tags, (zbx_clean_func_t) zbx_free_item_tag);
	zbx_vector_ptr_destroy(&item->tags);
	zbx_free(item);
}

/******************************************************************************
 *                                                                            *
 * Function: calc_init_query_one                                              *
 *                                                                            *
 * Purpose: initialize one item query                                         *
 *                                                                            *
 * Parameters: eval  - [IN] the evaluation data                               *
 *             query - [IN] the query to initialize                           *
 *                                                                            *
 ******************************************************************************/
static void	calc_init_query_one(zbx_calc_eval_t *eval, zbx_calc_query_t *query)
{
	zbx_calc_query_one_t	*data;

	data = (zbx_calc_query_one_t *)zbx_malloc(NULL, sizeof(zbx_calc_query_one_t));
	data->dcitem_hk_index = eval->one_num++;
	query->data = data;
}

/******************************************************************************
 *                                                                            *
 * Function: replace_key_param_wildcard_cb                                    *
 *                                                                            *
 * Purpose: replace wildcards '*'in key parameters with % and escape existing *
 *          %, \ characters for SQL like operation                            *
 *                                                                            *
 ******************************************************************************/
static int	replace_key_param_wildcard_cb(const char *data, int key_type, int level, int num, int quoted, void *cb_data,
		char **param)
{
	int	ret;
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
	if (FAIL == (ret = quote_key_param(param, quoted)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		zbx_free(*param);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: calc_match_item_key                                              *
 *                                                                            *
 * Purpose: check if item key matches the pattern                             *
 *                                                                            *
 * Parameters: item_key - [IN] the item key to match                          *
 *             pattern  - [IN] the pattern                                    *
 *                                                                            *
 ******************************************************************************/
static int	calc_match_item_key(const char *item_key, const AGENT_REQUEST *pattern)
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
	zbx_calc_eval_t	*eval;
}
zbx_calc_eval_many_t;

/******************************************************************************
 *                                                                            *
 * Function: calc_get_item_candidates                                         *
 *                                                                            *
 * Purpose: get itemids + hostids of items that might match query based on    *
 *          host, key and filter groups                                       *
 *                                                                            *
 * Parameters: eval            - [IN] the evaluation data                     *
 *             query           - [IN] the calculated item query               *
 *             groups          - [IN] the groups in filter template           *
 *             filter_template - [IN] the group filter template with {index}  *
 *                                    placeholders referring to a group in    *
 *                                    groups vector                           *
 *             itemhosts       - [out] itemid+hostid pairs matching query     *
 *                                                                            *
 ******************************************************************************/
static void	calc_get_item_candidates(zbx_calc_eval_t *eval, const zbx_calc_query_t *query,
		const zbx_vector_str_t *groups, const char *filter_template, zbx_vector_uint64_pair_t *itemhosts)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL, *esc, *clause = "where";
	size_t		sql_alloc = 0, sql_offset = 0;
	AGENT_REQUEST	pattern;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select i.itemid,i.hostid");

	if (0 != (query->flags & ZBX_CALC_QUERY_KEY_SOME))
	{
		init_request(&pattern);
		parse_item_key(query->ref.key, &pattern);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ",i.key_");
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " from items i");

	if (0 != (query->flags & ZBX_CALC_QUERY_HOST_ONE))
	{
		esc = DBdyn_escape_string(query->ref.host);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",hosts h"
				" where h.hostid=i.hostid"
				" and h.host='%s'", esc);
		zbx_free(esc);
		clause = "and";
	}
	else if (0 != (query->flags & ZBX_CALC_QUERY_HOST_SELF))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where i.hostid=" ZBX_FS_UI64,
				eval->calcitem->host.hostid);
		clause = "and";
	}

	if (0 != (query->flags & ZBX_CALC_QUERY_KEY_SOME))
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
	else if (0 != (query->flags & ZBX_CALC_QUERY_KEY_ONE))
	{
		esc = DBdyn_escape_string(query->ref.key);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " %s i.key_='%s'", clause, esc);
		zbx_free(esc);
		clause = "and";
	}

	if (0 != (query->flags & ZBX_CALC_QUERY_FILTER) && NULL != filter_template && '\0' != *filter_template)
	{
		zbx_uint64_t		index;
		int			pos = 0, last_pos = 0;
		zbx_token_t		token;
		zbx_calc_group_t	*group;

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

			group = calc_get_group(eval, groups->values[index]);

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
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " 0");

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

		if (0 == (query->flags & ZBX_CALC_QUERY_KEY_SOME) ||
				(NULL != pattern.key && SUCCEED == calc_match_item_key(row[2], &pattern)))
		{
			ZBX_STR2UINT64(pair.first, row[0]);
			ZBX_STR2UINT64(pair.second, row[1]);
			zbx_vector_uint64_pair_append(itemhosts, pair);
		}
	}
	DBfree_result(result);

	if (0 != (query->flags & ZBX_CALC_QUERY_KEY_SOME))
		free_request(&pattern);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: calc_item_check_tag                                              *
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
static int	calc_item_check_tag(zbx_calc_item_t *item, const char *tag)
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
 * Function: calc_eval_filter                                                 *
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
static int	calc_eval_filter(const char *name, size_t len, int args_num, const zbx_variant_t *args, void *data,
		const zbx_timespec_t *ts, zbx_variant_t *value, char **error)
{
	zbx_calc_eval_many_t	*many = (zbx_calc_eval_many_t *)data;

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
		zbx_calc_group_t *group;

		group = calc_get_group(many->eval, args[0].data.str);

		if (FAIL != zbx_vector_uint64_bsearch(&group->hostids, many->hostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			zbx_variant_set_dbl(value, 1);
		else
			zbx_variant_set_dbl(value, 0);

		return SUCCEED;
	}
	else if (0 == strncmp(name, "tag", ZBX_CONST_STRLEN("tag")))
	{
		zbx_calc_item_t	*item;

		item = calc_get_item(many->eval, many->itemid);

		if (SUCCEED == calc_item_check_tag(item, args[0].data.str))
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
 * Function: calc_init_query_many                                             *
 *                                                                            *
 * Purpose: initialize many item query                                        *
 *                                                                            *
 * Parameters: eval    - [IN] the evaluation data                             *
 *             query   - [IN] the query to initialize                         *
 *                                                                            *
 ******************************************************************************/
static void	calc_init_query_many(zbx_calc_eval_t *eval, zbx_calc_query_t *query)
{
	zbx_calc_query_many_t		*data;
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

	if (ZBX_CALC_QUERY_ITEM_ANY == (query->flags & ZBX_CALC_QUERY_ITEM_ANY))
	{
		error = zbx_strdup(NULL, "item query must have at least a host or an item key defined");
		goto out;
	}

	if (0 != (query->flags & ZBX_CALC_QUERY_FILTER))
	{
		if (SUCCEED != zbx_eval_parse_expression(&ctx, query->ref.filter, ZBX_EVAL_PARSE_QUERY_EXPRESSION,
				&errmsg))
		{
			error = zbx_dsprintf(NULL, "failed to parse calculated item filter: %s", errmsg);
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

	calc_get_item_candidates(eval, query, &groups, filter_template, &itemhosts);

	if (0 != (query->flags & ZBX_CALC_QUERY_FILTER))
	{
		zbx_calc_eval_many_t	eval_data;
		zbx_variant_t		filter_value;

		eval_data.eval = eval;

		for (i = 0; i < itemhosts.values_num; i++)
		{
			eval_data.itemid = itemhosts.values[i].first;
			eval_data.hostid = itemhosts.values[i].second;

			if (SUCCEED != zbx_eval_execute_ext(&ctx, NULL, calc_eval_filter, NULL, (void *)&eval_data,
					&filter_value, &errmsg))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "failed to evaluate calculated item filter: %s", errmsg);
				zbx_free(errmsg);
				continue;
			}

			if (SUCCEED != zbx_variant_convert(&filter_value, ZBX_VARIANT_DBL))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "unexpected calculated item filter evaluation result:"
						" value:\"%s\" of flags \"%s\"", zbx_variant_value_desc(&filter_value),
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

	if (0 == itemids.values_num)
	{
		error = zbx_strdup(NULL, "no items matching query");
		goto out;
	}

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		for (i = 0; i < itemids.values_num; i++)
			zabbix_log(LOG_LEVEL_DEBUG, "%s() itemid:" ZBX_FS_UI64, __func__, itemids.values[i]);
	}

	data = (zbx_calc_query_many_t *)zbx_malloc(NULL, sizeof(zbx_calc_query_many_t));
	data->itemids = itemids;
	query->data = data;
	eval->many_num++;

	ret = SUCCEED;
out:
	if (0 != (query->flags & ZBX_CALC_QUERY_FILTER) && SUCCEED == zbx_eval_status(&ctx))
		zbx_eval_clear(&ctx);

	if (SUCCEED != ret)
	{
		query->error = error;
		query->flags = ZBX_CALC_QUERY_ERROR;
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
 * Function: calc_cache_dcitems_hk                                            *
 *                                                                            *
 * Purpose: cache items used in one item queries                              *
 *                                                                            *
 * Parameters: eval - [IN] the evaluation data                                *
 *                                                                            *
 ******************************************************************************/
static void	calc_cache_dcitems_hk(zbx_calc_eval_t *eval)
{
	int	i;

	eval->hostkeys = (zbx_host_key_t *)zbx_malloc(NULL, sizeof(zbx_host_key_t) * eval->one_num);
	eval->dcitems_hk = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * eval->one_num);
	eval->errcodes_hk = (int *)zbx_malloc(NULL, sizeof(int) * eval->one_num);

	for (i = 0; i < eval->queries.values_num; i++)
	{
		zbx_calc_query_t	*query = (zbx_calc_query_t *)eval->queries.values[i];
		zbx_calc_query_one_t	*data;

		if (0 != (query->flags & ZBX_CALC_QUERY_MANY) || ZBX_CALC_QUERY_ERROR == query->flags)
			continue;

		data = (zbx_calc_query_one_t *)query->data;

		if (NULL == query->ref.host)
			eval->hostkeys[data->dcitem_hk_index].host = eval->calcitem->host.host;
		else
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

int	calc_find_dcitem_by_itemid(const void *d1, const void *d2)
{
	zbx_uint64_t	itemid = **(zbx_uint64_t **)d1;
	DC_ITEM		*dci = *(DC_ITEM **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(itemid, dci->itemid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: calc_cache_dcitems                                               *
 *                                                                            *
 * Purpose: cache items used in many item queries                             *
 *                                                                            *
 * Parameters: eval - [IN] the evaluation data                                *
 *                                                                            *
 ******************************************************************************/
static void	calc_cache_dcitems(zbx_calc_eval_t *eval)
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
	}

	for (i = 0; i < eval->queries.values_num; i++)
	{
		zbx_calc_query_t	*query = (zbx_calc_query_t *)eval->queries.values[i];
		zbx_calc_query_many_t	*data;

		if (0 == (query->flags & ZBX_CALC_QUERY_MANY))
			continue;

		data = (zbx_calc_query_many_t *)query->data;

		for (j = 0; j < data->itemids.values_num; j++)
			zbx_vector_uint64_append(&itemids, data->itemids.values[j]);
	}

	zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (i = 0; i < itemids.values_num;)
	{
		if (FAIL != zbx_vector_ptr_bsearch(&eval->dcitem_refs, &itemids.values[i], calc_find_dcitem_by_itemid))
		{
			zbx_vector_uint64_remove(&itemids, i);
			continue;
		}
		i++;
	}

	if (0 != (eval->dcitems_num = itemids.values_num))
	{
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
 * Function: calc_eval_init                                                   *
 *                                                                            *
 * Purpose: initialize calculated item evaluation data                        *
 *                                                                            *
 * Parameters: eval     - [IN] the evaluation data                            *
 *             dc_item  - [IN] the calculated item                            *
 *             ctx      - [IN] parsed calculated item formula                 *
 *                                                                            *
 ******************************************************************************/
static void	calc_eval_init(zbx_calc_eval_t *eval, DC_ITEM *dc_item, zbx_eval_context_t *ctx)
{
	int			i;
	zbx_calc_query_t	*query;
	zbx_vector_str_t	filters;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64, __func__, dc_item->itemid);

	zbx_vector_str_create(&filters);
	zbx_eval_extract_item_refs(ctx, &filters);

	zbx_vector_ptr_create(&eval->queries);
	zbx_vector_ptr_create(&eval->groups);
	zbx_vector_ptr_create(&eval->itemtags);
	zbx_vector_ptr_create(&eval->dcitem_refs);

	eval->one_num = 0;
	eval->many_num = 0;
	eval->dcitems_num = 0;

	eval->calcitem = dc_item;

	for (i = 0; i < filters.values_num; i++)
	{
		query = calc_create_query(filters.values[i]);
		zbx_vector_ptr_append(&eval->queries, query);

		if (ZBX_CALC_QUERY_UNSET == query->flags)
		{
			query->error = zbx_strdup(NULL, "invalid item query filter");
			query->flags = ZBX_CALC_QUERY_ERROR;
		}
		else if (0 != (query->flags & ZBX_CALC_QUERY_MANY))
			calc_init_query_many(eval, query);
		else
			calc_init_query_one(eval, query);
	}

	/* cache items for functions using one item queries */
	if (0 != eval->one_num)
		calc_cache_dcitems_hk(eval);

	/* cache items for functions using many item queries */
	if (0 != eval->many_num)
		calc_cache_dcitems(eval);

	zbx_vector_str_clear_ext(&filters, zbx_str_free);
	zbx_vector_str_destroy(&filters);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() calculated:%d aggregate:%d", __func__, eval->one_num, eval->many_num);
}

/******************************************************************************
 *                                                                            *
 * Function: calc_eval_clear                                                  *
 *                                                                            *
 * Purpose: free resources allocated by calculated item evaluation data       *
 *                                                                            *
 * Parameters: eval     - [IN] the evaluation data                            *
 *                                                                            *
 ******************************************************************************/
static void	calc_eval_clear(zbx_calc_eval_t *eval)
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

	zbx_vector_ptr_clear_ext(&eval->itemtags, (zbx_clean_func_t) calc_item_free);
	zbx_vector_ptr_destroy(&eval->itemtags);

	zbx_vector_ptr_clear_ext(&eval->groups, (zbx_clean_func_t) calc_group_free);
	zbx_vector_ptr_destroy(&eval->groups);

	zbx_vector_ptr_clear_ext(&eval->queries, (zbx_clean_func_t) calc_query_free);
	zbx_vector_ptr_destroy(&eval->queries);
}

/******************************************************************************
 *                                                                            *
 * Function: calcitem_eval_single                                             *
 *                                                                            *
 * Purpose: evaluate historical function for single item                      *
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
static int	calcitem_eval_one(zbx_calc_eval_t *eval, zbx_calc_query_t *query, const char *name, size_t len, int args_num,
		const zbx_variant_t *args, const zbx_timespec_t *ts, zbx_variant_t *value, char **error)
{
	char			func_name[MAX_STRING_LEN], *params = NULL;
	size_t			params_alloc = 0, params_offset = 0;
	DC_ITEM			*item;
	int			i, ret = FAIL;
	zbx_calc_query_one_t	*data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() %.*s(/%s/%s?[%s],...)", __func__, (int )len, name,
			ZBX_NULL2EMPTY_STR(query->ref.host), ZBX_NULL2EMPTY_STR(query->ref.key),
			ZBX_NULL2EMPTY_STR(query->ref.filter));

	data = (zbx_calc_query_one_t *)query->data;

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
		ret = evaluate_function2(value, item, func_name, "", ts, error);
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

	ret = evaluate_function2(value, item, func_name, params, ts, error);
out:
	zbx_free(params);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:%s flags:%s", __func__, zbx_result_string(ret),
			zbx_variant_value_desc(value), zbx_variant_type_desc(value));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: calcitem_eval_many                                               *
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
static int	calcitem_eval_many(zbx_calc_eval_t *eval, zbx_calc_query_t *query, const char *name, size_t len,
		int args_num, const zbx_variant_t *args, const zbx_timespec_t *ts, zbx_variant_t *value, char **error)
{
	zbx_calc_query_many_t	*data;
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() %.*s(/%s/%s?[%s],...)", __func__, (int)len, name,
			ZBX_NULL2EMPTY_STR(query->ref.host), ZBX_NULL2EMPTY_STR(query->ref.key),
			ZBX_NULL2EMPTY_STR(query->ref.filter));

	ZBX_UNUSED(args_num);

	data = (zbx_calc_query_many_t *)query->data;

	ret = evaluate_aggregate(&data->itemids, &eval->dcitem_refs, ts, name, len, args_num, args, value, error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:%s flags:%s", __func__, zbx_result_string(ret),
			zbx_variant_value_desc(value), zbx_variant_type_desc(value));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: calcitem_eval_history                                            *
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
static int	calcitem_eval_history(const char *name, size_t len, int args_num, const zbx_variant_t *args, void *data,
		const zbx_timespec_t *ts, zbx_variant_t *value, char **error)
{
	int			ret = FAIL;
	zbx_calc_eval_t		*eval;
	zbx_calc_query_t	*query;
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

	eval = (zbx_calc_eval_t *)data;

	/* the historical function item query argument is replaced with corresponding itemrefs index */
	query = (zbx_calc_query_t *)eval->queries.values[(int) args[0].data.ui64];

	if (ZBX_CALC_QUERY_ERROR == query->flags)
	{
		*error = zbx_dsprintf(NULL, "Cannot evaluate function: %s", query->error);
		goto out;
	}

	if (0 == (query->flags & ZBX_CALC_QUERY_MANY))
		ret = calcitem_eval_one(eval, query, name, len, args_num - 1, args + 1, ts, value, &errmsg);
	else
		ret = calcitem_eval_many(eval, query, name, len, args_num - 1, args + 1, ts, value, &errmsg);

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
 * Function: calcitem_eval_common                                             *
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
 * Comments: There are no custom common functions for calculated items, but   *
 *           it's used to check for /host/key query quoting errors instead.   *
 *                                                                            *
 ******************************************************************************/
static int	calcitem_eval_common(const char *name, size_t len, int args_num, const zbx_variant_t *args, void *data,
		const zbx_timespec_t *ts, zbx_variant_t *value, char **error)
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

		if (0 == zbx_eval_parse_query(args[0].data.str, strlen(args[0].data.str), &query))
		{
			zbx_eval_clear_query(&query);
			*error = zbx_strdup(NULL, "Cannot evaluate function: quoted item query argument");
			return FAIL;
		}
	}

	*error = zbx_strdup(NULL, "Cannot evaluate function: invalid first argument");
	return FAIL;
}

int	get_value_calculated(DC_ITEM *dc_item, AGENT_RESULT *result)
{
	int			ret = NOTSUPPORTED;
	char			*error = NULL;
	zbx_eval_context_t	ctx;
	zbx_calc_eval_t		eval;
	zbx_timespec_t		ts;
	zbx_variant_t		value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' expression:'%s'", __func__, dc_item->key_orig, dc_item->params);

	if (NULL == dc_item->formula_bin)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() serialized formula is not set", __func__);
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot evaluate calculated item:"
				" serialized formula is not set"));
		goto out;
	}

	zbx_eval_deserialize(&ctx, dc_item->params, ZBX_EVAL_PARSE_CALC_EXPRESSSION, dc_item->formula_bin);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char	*expression = NULL;

		zbx_eval_compose_expression(&ctx, &expression);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() expanded expression:'%s'", __func__, expression);
		zbx_free(expression);
	}

	calc_eval_init(&eval, dc_item, &ctx);
	zbx_timespec(&ts);
	zbx_variant_set_none(&value);

	if (SUCCEED != zbx_eval_execute_ext(&ctx, &ts, calcitem_eval_common, calcitem_eval_history, (void *)&eval,
			&value, &error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() error:%s", __func__, error);
		SET_MSG_RESULT(result, error);
		error = NULL;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() value:%s", __func__, zbx_variant_value_desc(&value));

		switch (value.type)
		{
			case ZBX_VARIANT_DBL:
				SET_DBL_RESULT(result, value.data.dbl);
				break;
			case ZBX_VARIANT_UI64:
				SET_UI64_RESULT(result, value.data.ui64);
				break;
			case ZBX_VARIANT_STR:
				SET_TEXT_RESULT(result, value.data.str);
				break;
			default:
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "unsupported calculated value result \"%s\""
						" of flags \"%s\"", zbx_variant_value_desc(&value),
						zbx_variant_type_desc(&value)));
				zbx_variant_clear(&value);
				break;
		}

		if (ZBX_VARIANT_NONE != value.type)
		{
			zbx_variant_set_none(&value);
			ret = SUCCEED;
		}
	}

	calc_eval_clear(&eval);
	zbx_eval_clear(&ctx);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
