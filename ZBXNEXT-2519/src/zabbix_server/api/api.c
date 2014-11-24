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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "zbxalgo.h"
#include "zbxjson.h"

#include "api.h"

/* common get request parameters */
#define ZBX_API_PARAM_OUTPUT			0
#define ZBX_API_PARAM_OUTPUTCOUNT		1
#define ZBX_API_PARAM_EDITABLE			2
#define ZBX_API_PARAM_EXCLUDESEARCH		3
#define ZBX_API_PARAM_FILTER			4
#define ZBX_API_PARAM_LIMIT			5
#define ZBX_API_PARAM_PRESERVEKEYS		6
#define ZBX_API_PARAM_SEARCH			7
#define ZBX_API_PARAM_SEARCHBYANY		8
#define ZBX_API_PARAM_SEARCHWILDCARDSENABLED	9
#define ZBX_API_PARAM_SORTFIELD			10
#define ZBX_API_PARAM_SORTORDER			11
#define ZBX_API_PARAM_STARTSEARCH		12

#define zbx_api_get_PARAMS_TOTAL		13

/* conflicting field definitions */
#define ZBX_API_PARAM_OUTPUT_CONFLICT	\
	((1 << ZBX_API_PARAM_OUTPUT) | (1 << ZBX_API_PARAM_OUTPUTCOUNT))

#define ZBX_API_PARAM_OUTPUTCOUNT_CONFLICT	\
	((1 << ZBX_API_PARAM_OUTPUT) | 		\
	(1 << ZBX_API_PARAM_OUTPUTCOUNT) | 	\
	(1 << ZBX_API_PARAM_LIMIT) |		\
	(1 << ZBX_API_PARAM_PRESERVEKEYS) |	\
	(1 << ZBX_API_PARAM_SORTFIELD) |	\
	(1 << ZBX_API_PARAM_SORTORDER)		\
	)

#define ZBX_API_PARAM_LIMIT_CONFLICT	\
	((1 << ZBX_API_PARAM_OUTPUTCOUNT) | (1 << ZBX_API_PARAM_LIMIT))

#define ZBX_API_PARAM_PRESERVEKEYS_CONFLICT	\
	((1 << ZBX_API_PARAM_OUTPUTCOUNT) | (1 << ZBX_API_PARAM_PRESERVEKEYS))

#define ZBX_API_PARAM_SORTORDER_CONFLICT	\
	((1 << ZBX_API_PARAM_OUTPUTCOUNT) | (1 << ZBX_API_PARAM_SORTORDER))

#define ZBX_API_PARAM_SORTFIELD_CONFLICT	\
	((1 << ZBX_API_PARAM_OUTPUTCOUNT) | (1 << ZBX_API_PARAM_SORTFIELD))

/* common get request parameter names */
static const char *zbx_api_get_tags[] = {
		"output",
		"outputCount",
		"editable",
		"excludeSearch",
		"filter",
		"limit",
		"preservekeys",
		"search",
		"searchByAny",
		"searchWildcardsEnabled",
		"sortfield",
		"sortorder",
		"startSearch"
};

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_register_param                                           *
 *                                                                            *
 * Purpose: checks conflicting parameters and marks parameter as parsed       *
 *                                                                            *
 * Parameters: self    - [IN] common get request options                      *
 *             paramid - [IN] the parameter to check                          *
 *             mask    - [IN] the mask of conflicting parameters              *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the parameter was registered successfully.         *
 *               FAIL    - parameter conflict was found, the error message    *
 *                         is returned in error parameter.                    *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_get_register_param(zbx_api_get_t *self, int paramid, int mask, char **error)
{
	if (0 != (self->parameters & mask))
	{
		int	i;

		mask &= self->parameters;

		for (i = 0; i < zbx_api_get_PARAMS_TOTAL; i++)
		{
			if (0 != (mask & (1 << i)))
			{
				if (paramid != i)
				{
					*error = zbx_dsprintf(*error, "parameter \"%s\" conflicts with parameter \"%s\"",
							zbx_api_get_tags[paramid], zbx_api_get_tags[i]);
				}
				else
				{
					*error = zbx_dsprintf(*error, "duplicate parameter \"%s\" found",
							zbx_api_get_tags[paramid]);
				}

				return FAIL;
			}
		}

		*error = zbx_strdup(*error, "unknown error while parsing get request common parameters");

		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}
	self->parameters |= (1 << paramid);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_validate_param_dependency                            *
 *                                                                            *
 * Purpose: validates common get request parameter dependency                 *
 *                                                                            *
 * Parameters: self       - [IN] common get request options                   *
 *             paramid    - [IN] the parameter to check                       *
 *             requiredid - [IN] the parameter required by paramid            *
 *             error      - [OUT] the error message                           *
 *                                                                            *
 * Return value: SUCCEED - the parameter was validated successfully.          *
 *               FAIL    - dependency error was found, the error message is   *
 *                         returned in error parameter.                       *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_get_validate_param_dependency(const zbx_api_get_t *self, int paramid, int requiredid,
		char **error)
{
	if (0 != (self->parameters & (1 << paramid)) && 0 == (self->parameters & (1 << requiredid)))
	{
		*error = zbx_dsprintf(*error, "parameter \"%s\" requires parameter \"%s\" to be defined",
				zbx_api_get_tags[paramid], zbx_api_get_tags[requiredid]);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_sort_free                                                *
 *                                                                            *
 * Purpose: frees sort data                                                   *
 *                                                                            *
 * Parameters: self - [IN] the sort data                                      *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_sort_free(zbx_api_sort_t *self)
{
	zbx_free(self->field);
	zbx_free(self);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_query_init                                               *
 *                                                                            *
 * Purpose: initializes query parameter                                       *
 *                                                                            *
 * Parameters: self - [IN] the query parameter                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_query_init(zbx_api_query_t *self)
{
	self->type = ZBX_API_QUERY_NONE;
	zbx_vector_str_create(&self->fields);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_query_free                                               *
 *                                                                            *
 * Purpose: frees query parameter                                             *
 *                                                                            *
 * Parameters: self - [IN] the query parameter                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_query_free(zbx_api_query_t *self)
{
	zbx_vector_str_clear_ext(&self->fields, zbx_ptr_free);
	zbx_vector_str_destroy(&self->fields);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_vector_ptr_pair_clear                                    *
 *                                                                            *
 * Purpose: clears ptr pair vector consisting of key=>value string mapping    *
 *                                                                            *
 * Parameters: vector - [IN] the sort vector to clear                         *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_vector_ptr_pair_clear(zbx_vector_ptr_pair_t *vector)
{
	int	i;

	for (i = 0; i < vector->values_num; i++)
	{
		zbx_free(vector->values[i].first);
		zbx_free(vector->values[i].second);
	}

	zbx_vector_ptr_pair_clear(vector);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_filter_init                                              *
 *                                                                            *
 * Purpose: initializes filter data                                           *
 *                                                                            *
 * Parameters: self - [IN] the filter data                                    *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_filter_init(zbx_api_filter_t *self)
{
	zbx_vector_ptr_pair_create(&self->exact);
	zbx_vector_ptr_pair_create(&self->like);
	self->options = 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_filter_free                                              *
 *                                                                            *
 * Purpose: frees filter data                                                 *
 *                                                                            *
 * Parameters: self - [IN] the filter data                                    *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_filter_free(zbx_api_filter_t *self)
{
	zbx_api_vector_ptr_pair_clear(&self->like);
	zbx_api_vector_ptr_pair_clear(&self->exact);

	zbx_vector_ptr_pair_destroy(&self->like);
	zbx_vector_ptr_pair_destroy(&self->exact);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_param_flag                                           *
 *                                                                            *
 * Purpose: gets flag type parameter from json data                           *
 *                                                                            *
 * Parameters: param - [IN] the parameter name                                *
 *             next  - [IN/OUT] the next character in json data buffer        *
 *             value - [OUT] the parsed value                                 *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_param_flag(const char *param, const char **next, unsigned char *value, char **error)
{
	char	*data = NULL;
	size_t	data_alloc = 0;
	int	is_null, ret = FAIL;

	if (NULL == (*next = zbx_json_decodevalue_dyn(*next, &data, &data_alloc, &is_null)))
	{
		*error = zbx_dsprintf(*error, "cannot parse parameter \"%s\" value", param);
		goto out;
	}

	*value = (0 == is_null ? 1 : 0);

	ret = SUCCEED;
out:
	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_param_bool                                           *
 *                                                                            *
 * Purpose: gets boolean type parameter from json data                        *
 *                                                                            *
 * Parameters: param - [IN] the parameter name                                *
 *             next  - [IN/OUT] the next character in json data buffer        *
 *             value - [OUT] the parsed value                                 *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_param_bool(const char *param, const char **next, unsigned char *value, char **error)
{
	char	*data = NULL;
	size_t	data_alloc = 0;
	int	is_null, ret = FAIL;

	if (NULL == (*next = zbx_json_decodevalue_dyn(*next, &data, &data_alloc, &is_null)))
	{
		*error = zbx_dsprintf(*error, "cannot parse parameter \"%s\" value", param);
		goto out;
	}

	if (0 == is_null)
	{
		if (0 == strcmp(data, "true"))
		{
			*value = 1;
		}
		else if (0 == strcmp(data, "false"))
		{
			*value = 0;
		}
		else
		{
			*error = zbx_dsprintf(*error, "invalid parameter \"%s\" value \"%s\"", param, data);
			goto out;

		}
	}

	ret = SUCCEED;
out:
	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_param_int                                            *
 *                                                                            *
 * Purpose: gets integer type parameter from json data                        *
 *                                                                            *
 * Parameters: param - [IN] the parameter name                                *
 *             next  - [IN/OUT] the next character in json data buffer        *
 *             value - [OUT] the parsed value                                 *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_param_int(const char *param, const char **next, int *value, char **error)
{
	char	*data = NULL;
	size_t	data_alloc = 0;
	int	is_null, ret = FAIL;

	if (NULL == (*next = zbx_json_decodevalue_dyn(*next, &data, &data_alloc, &is_null)))
	{
		*error = zbx_dsprintf(*error, "cannot parse parameter \"%s\" value", param);
		goto out;
	}

	if (0 == is_null)
	{
		char	*ptr = data;

		if ('-' == *ptr)
			ptr++;

		if (SUCCEED != is_uint31(ptr, value))
		{
			*error = zbx_dsprintf(*error, "invalid parameter \"%s\" value \"%s\"", param, data);
			goto out;
		}

		if (ptr != data)
			*value = -*value;
	}

	ret = SUCCEED;
out:
	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_param_object                                         *
 *                                                                            *
 * Purpose: gets object type parameter from json data                         *
 *                                                                            *
 * Parameters: param - [IN] the parameter name                                *
 *             next  - [IN/OUT] the next character in json data buffer        *
 *             value - [OUT] the parsed value                                 *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 * Comments: Object is an associative array and is stored as ptr pair vector. *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_param_object(const char *param, const char **next, zbx_vector_ptr_pair_t *value, char **error)
{
	const char		*p = NULL;
	char			name[ZBX_API_PARAM_NAME_SIZE], *data = NULL;
	size_t			data_alloc = 0;
	struct zbx_json_parse	jp;
	int			ret = FAIL, is_null;
	zbx_ptr_pair_t		pair;

	if (SUCCEED != zbx_json_brackets_open(*next, &jp))
	{
		*error = zbx_dsprintf(*error, "cannot open object parameter \"%s\"", param);
		goto out;
	}

	*next = jp.end + 1;

	while (NULL != (p = zbx_json_pair_next(&jp, p, name, sizeof(name))))
	{
		if (NULL == (p = zbx_json_decodevalue_dyn(p, &data, &data_alloc, &is_null)))
		{
			*error = zbx_dsprintf(*error, "cannot parse parameter \"%s\" value", param);
			goto out;
		}

		if (0 != is_null)
			continue;

		pair.first = zbx_strdup(NULL, name);

		pair.second = data;
		zbx_vector_ptr_pair_append(value, pair);

		data = NULL;
		data_alloc = 0;
	}

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
		zbx_api_vector_ptr_pair_clear(value);

	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_param_array                                          *
 *                                                                            *
 * Purpose: gets array type parameter from json data                          *
 *                                                                            *
 * Parameters: param - [IN] the parameter name                                *
 *             next  - [IN/OUT] the next character in json data buffer        *
 *             value - [OUT] the parsed value                                 *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 * Comments: The array is stored as string vector.                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_param_array(const char *param, const char **next, zbx_vector_str_t *value, char **error)
{
	const char		*p = NULL;
	char			*data = NULL;
	size_t			data_alloc = 0;
	struct zbx_json_parse	jp;
	int			ret = FAIL, is_null;

	if (SUCCEED != zbx_json_brackets_open(*next, &jp))
	{
		*error = zbx_dsprintf(*error, "cannot open parameter \"%s\" value array", param);
		goto out;
	}

	while (NULL != (p = zbx_json_next_value_dyn(&jp, p, &data, &data_alloc, &is_null)))
	{
		if (0 != is_null)
			continue;

		zbx_vector_str_append(value, data);

		data = NULL;
		data_alloc = 0;
	}

	*next = jp.end + 1;

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
		zbx_vector_str_clear_ext(value, zbx_ptr_free);

	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_param_string                                         *
 *                                                                            *
 * Purpose: gets string type parameter from json data                         *
 *                                                                            *
 * Parameters: param - [IN] the parameter name                                *
 *             next  - [IN/OUT] the next character in json data buffer        *
 *             value - [OUT] the parsed value                                 *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_param_string(const char *param, const char **next, char **value, char **error)
{
	char	*data = NULL;
	size_t	data_alloc = 0;
	int	is_null, ret = FAIL;

	if (NULL == (*next = zbx_json_decodevalue_dyn(*next, &data, &data_alloc, &is_null)))
	{
		*error = zbx_dsprintf(*error, "cannot parse parameter \"%s\" value", param);
		goto out;
	}

	if (0 == is_null)
	{
		*value = data;
		data = NULL;
	}

	ret = SUCCEED;
out:
	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_param_query                                          *
 *                                                                            *
 * Purpose: gets query type parameter from json data                          *
 *                                                                            *
 * Parameters: param - [IN] the parameter name                                *
 *             next  - [IN/OUT] the next character in json data buffer        *
 *             value - [OUT] the parsed value                                 *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 * Comments: Query parameter can have either value "exact" (all), "count" or  *
 *           contain a list of field names. It is stored in zbx_api_query_t   *
 *           structure where type defines the query type (see ZBX_API_QUERY_  *
 *           defines) and fields contain the defined field names.             *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_param_query(const char *param, const char **next, zbx_api_query_t *value, char **error)
{
	char			*data = NULL;
	int			ret = FAIL;

	if ('[' == **next)
	{
		if (SUCCEED != zbx_api_get_param_array(param, next, &value->fields, error))
			goto out;

		value->type = ZBX_API_QUERY_FIELDS;
	}
	else
	{
		if (SUCCEED != zbx_api_get_param_string(param, next, &data, error))
			goto out;

		if (NULL == data || 0 == strcmp(data, ZBX_API_PARAM_QUERY_EXTEND))
		{
			value->type = ZBX_API_QUERY_ALL;
		}
		else if (0 == strcmp(data, ZBX_API_PARAM_QUERY_COUNT))
		{
			value->type = ZBX_API_QUERY_COUNT;
		}
		else
		{
			*error = zbx_dsprintf(*error, "invalid parameter \"%s\" value \"%s\"", param, data);
			goto out;
		}
	}
	ret = SUCCEED;
out:
	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_param_string_or_array                                *
 *                                                                            *
 * Purpose: gets a parameter that can be defined either a string or an array  *
 *                                                                            *
 * Parameters: param - [IN] the parameter name                                *
 *             next  - [IN/OUT] the next character in json data buffer        *
 *             value - [OUT] the parsed value                                 *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 * Comments: The result is always returned as a vector of strings.            *
 *           If parameter was defined as a single string, then a vector with  *
 *           its value as the single item is returned.                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_param_string_or_array(const char *param, const char **next, zbx_vector_str_t *value, char **error)
{
	char			*data = NULL;
	int			ret = FAIL;

	if ('[' == **next)
	{
		if (SUCCEED != zbx_api_get_param_array(param, next, value, error))
			goto out;
	}
	else
	{
		if (SUCCEED != zbx_api_get_param_string(param, next, &data, error))
			goto out;

		if (NULL != data)
		{
			zbx_vector_str_append(value, data);
			data = NULL;
		}
	}

	ret = SUCCEED;
out:
	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_param_idarray                                        *
 *                                                                            *
 * Purpose: gets id list parameter                                            *
 *                                                                            *
 * Parameters: param - [IN] the parameter name                                *
 *             next  - [IN/OUT] the next character in json data buffer        *
 *             value - [OUT] the parsed value                                 *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 * Comments: Id list parameter is either single string value or an array of   *
 *           strings containing object identifiers. It is returned as         *
 *           a vector of unsigned 64 bit integer values.                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_param_idarray(const char *param, const char **next, zbx_vector_uint64_t *value, char **error)
{
	zbx_vector_str_t	ids;
	zbx_uint64_t		id;
	int			ret = FAIL, i;

	zbx_vector_str_create(&ids);

	if (FAIL == zbx_api_get_param_string_or_array(param, next, &ids, error))
		goto out;

	for (i = 0; i < ids.values_num; i++)
	{
		if (SUCCEED != is_uint64(ids.values[i], &id))
		{
			zbx_vector_uint64_clear(value);
			goto out;
		}

		zbx_vector_uint64_append(value, id);
	}

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_init                                                 *
 *                                                                            *
 * Purpose: initializes common get request parameters                         *
 *                                                                            *
 * Parameters: self - [IN] the common get request parameter data              *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_get_init(zbx_api_get_t *self)
{
	memset(self, 0, sizeof(zbx_api_get_t));

	zbx_api_query_init(&self->output);
	self->output.type = ZBX_API_QUERY_ALL;

	zbx_api_filter_init(&self->filter);

	zbx_vector_ptr_create(&self->sort);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_parse                                                *
 *                                                                            *
 * Purpose: parses get request common parameter                               *
 *                                                                            *
 * Parameters: self      - [IN] the common get request parameter data         *
 *             parameter - [IN] the parameter name                            *
 *             json      - [IN] the json data                                 *
 *             next      - [IN/OUT] the next character in json data buffer    *
 *             error     - [OUT] the error message                            *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_parse(zbx_api_get_t *self, const char *parameter, struct zbx_json_parse *json, const char **next,
		char **error)
{
	int		ret = FAIL;
	unsigned char	flag;

	if (0 == strcmp(parameter, zbx_api_get_tags[ZBX_API_PARAM_OUTPUT]))
	{
		if (SUCCEED != zbx_api_get_register_param(self, ZBX_API_PARAM_OUTPUT, ZBX_API_PARAM_OUTPUT_CONFLICT, error))
			goto out;

		if (SUCCEED != zbx_api_get_param_query(zbx_api_get_tags[ZBX_API_PARAM_OUTPUT], next, &self->output,
				error))
		{
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_get_tags[ZBX_API_PARAM_OUTPUTCOUNT]))
	{
		if (SUCCEED != zbx_api_get_register_param(self, ZBX_API_PARAM_OUTPUTCOUNT,
				ZBX_API_PARAM_OUTPUTCOUNT_CONFLICT, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_flag(zbx_api_get_tags[ZBX_API_PARAM_OUTPUTCOUNT], next, &flag, error))
			goto out;

		if (0 != flag)
			self->output.type = ZBX_API_QUERY_COUNT;
	}
	else if (0 == strcmp(parameter, zbx_api_get_tags[ZBX_API_PARAM_EDITABLE]))
	{
		if (SUCCEED != zbx_api_get_register_param(self, ZBX_API_PARAM_EDITABLE, 1 << ZBX_API_PARAM_EDITABLE, error))
			goto out;

		if (SUCCEED != zbx_api_get_param_bool(zbx_api_get_tags[ZBX_API_PARAM_EDITABLE], next,
				&self->filter_editable, error))
		{
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_get_tags[ZBX_API_PARAM_LIMIT]))
	{
		if (SUCCEED != zbx_api_get_register_param(self, ZBX_API_PARAM_LIMIT, ZBX_API_PARAM_LIMIT_CONFLICT, error))
			goto out;

		if (SUCCEED != zbx_api_get_param_int(zbx_api_get_tags[ZBX_API_PARAM_LIMIT], next, &self->limit, error))
			goto out;
	}
	else if (0 == strcmp(parameter, zbx_api_get_tags[ZBX_API_PARAM_PRESERVEKEYS]))
	{
		if (SUCCEED != zbx_api_get_register_param(self, ZBX_API_PARAM_PRESERVEKEYS,
				ZBX_API_PARAM_PRESERVEKEYS_CONFLICT, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_flag(zbx_api_get_tags[ZBX_API_PARAM_PRESERVEKEYS], next,
				&self->output_indexed, error))
		{
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_get_tags[ZBX_API_PARAM_EXCLUDESEARCH]))
	{
		if (SUCCEED != zbx_api_get_register_param(self, ZBX_API_PARAM_EXCLUDESEARCH,
				1 << ZBX_API_PARAM_EXCLUDESEARCH, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_flag(zbx_api_get_tags[ZBX_API_PARAM_EXCLUDESEARCH], next, &flag,
				error))
		{
			goto out;
		}

		if (0 != flag)
			self->filter.options |= ZBX_API_FILTER_OPTION_EXCLUDE;
	}
	else if (0 == strcmp(parameter, zbx_api_get_tags[ZBX_API_PARAM_STARTSEARCH]))
	{
		if (SUCCEED != zbx_api_get_register_param(self, ZBX_API_PARAM_STARTSEARCH, 1 << ZBX_API_PARAM_STARTSEARCH,
				error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_flag(zbx_api_get_tags[ZBX_API_PARAM_STARTSEARCH], next, &flag, error))
			goto out;

		if (0 != flag)
			self->filter.options |= ZBX_API_FILTER_OPTION_START;
	}
	else if (0 == strcmp(parameter, zbx_api_get_tags[ZBX_API_PARAM_SEARCHBYANY]))
	{
		if (SUCCEED != zbx_api_get_register_param(self, ZBX_API_PARAM_SEARCHBYANY, 1 << ZBX_API_PARAM_SEARCHBYANY,
				error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_bool(zbx_api_get_tags[ZBX_API_PARAM_STARTSEARCH], next, &flag, error))
			goto out;

		if (0 != flag)
			self->filter.options |= ZBX_API_FILTER_OPTION_ANY;
	}
	else if (0 == strcmp(parameter, zbx_api_get_tags[ZBX_API_PARAM_SEARCHWILDCARDSENABLED]))
	{
		if (SUCCEED != zbx_api_get_register_param(self, ZBX_API_PARAM_SEARCHWILDCARDSENABLED,
				1 << ZBX_API_PARAM_SEARCHWILDCARDSENABLED, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_bool(zbx_api_get_tags[ZBX_API_PARAM_SEARCHWILDCARDSENABLED], next,
				&flag, error))
			goto out;

		if (0 != flag)
			self->filter.options |= ZBX_API_FILTER_OPTION_WILDCARD;
	}
	else if (0 == strcmp(parameter, zbx_api_get_tags[ZBX_API_PARAM_FILTER]))
	{
		if (SUCCEED != zbx_api_get_register_param(self, ZBX_API_PARAM_FILTER, 1 << ZBX_API_PARAM_FILTER, error))
			goto out;

		if (SUCCEED != zbx_api_get_param_object(zbx_api_get_tags[ZBX_API_PARAM_FILTER], next,
				&self->filter.exact, error))
		{
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_get_tags[ZBX_API_PARAM_SEARCH]))
	{
		if (SUCCEED != zbx_api_get_register_param(self, ZBX_API_PARAM_SEARCH, 1 << ZBX_API_PARAM_SEARCH, error))
			goto out;

		if (SUCCEED != zbx_api_get_param_object(zbx_api_get_tags[ZBX_API_PARAM_SEARCH], next,
				&self->filter.like, error))
		{
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_get_tags[ZBX_API_PARAM_SORTFIELD]))
	{
		zbx_vector_str_t	fields;
		int			i, rc;

		if (SUCCEED != zbx_api_get_register_param(self, ZBX_API_PARAM_SORTFIELD, ZBX_API_PARAM_SORTFIELD_CONFLICT,
				error))
		{
			goto out;
		}

		zbx_vector_str_create(&fields);

		if (SUCCEED == (rc = zbx_api_get_param_string_or_array(zbx_api_get_tags[ZBX_API_PARAM_SORTFIELD], next,
				&fields, error)))
		{
			/* add the sort fields to field vector if it was not done by sortorder parameter */
			if (0 == self->sort.values_num && 0 != fields.values_num)
			{
				for (i = 0; i < fields.values_num; i++)
				{
					zbx_api_sort_t	*sort;

					sort = (zbx_api_sort_t *)zbx_malloc(NULL, sizeof(zbx_api_sort_t));
					memset(sort, 0, sizeof(zbx_api_sort_t));
					zbx_vector_ptr_append(&self->sort, sort);
				}
			}

			/* check that sortfield items match sortoder items */
			if (fields.values_num != self->sort.values_num)
			{
				*error = zbx_dsprintf(*error, "the number of parameter \"%s\" values differs from the"
						" number of parameter \"%s\" values",
						zbx_api_get_tags[ZBX_API_PARAM_SORTFIELD],
						zbx_api_get_tags[ZBX_API_PARAM_SORTORDER]);
				rc = FAIL;
			}

			/* copy sort fields */
			for (i = 0; i < fields.values_num; i++)
			{
				zbx_api_sort_t	*sort = (zbx_api_sort_t *)self->sort.values[i];

				sort->field = fields.values[i];
			}

			/* clear the field vector without freeing fields, as they are copied to options sort data */
			zbx_vector_str_clear(&fields);
		}

		zbx_vector_str_destroy(&fields);

		if (SUCCEED != rc)
			goto out;
	}
	else if (0 == strcmp(parameter, zbx_api_get_tags[ZBX_API_PARAM_SORTORDER]))
	{
		zbx_vector_str_t	fields;
		int			i, rc;

		if (SUCCEED != zbx_api_get_register_param(self, ZBX_API_PARAM_SORTORDER,
				ZBX_API_PARAM_SORTORDER_CONFLICT, error))
		{
			goto out;
		}

		zbx_vector_str_create(&fields);

		if (SUCCEED == (rc = zbx_api_get_param_string_or_array(zbx_api_get_tags[ZBX_API_PARAM_SORTORDER], next,
				&fields, error)))
		{
			/* add the sort fields to field vector if it was not done by sortfield parameter */
			if (0 == self->sort.values_num && 0 != fields.values_num)
			{
				for (i = 0; i < fields.values_num; i++)
				{
					zbx_api_sort_t	*sort;

					sort = (zbx_api_sort_t *)zbx_malloc(NULL, sizeof(zbx_api_sort_t));
					memset(sort, 0, sizeof(zbx_api_sort_t));
					zbx_vector_ptr_append(&self->sort, sort);
				}
			}

			/* check that sortfield items match sortoder items */
			if (fields.values_num != self->sort.values_num)
			{
				*error = zbx_dsprintf(*error, "the number of parameter \"%s\" values differs from the"
						" number of parameter \"%s\" values",
						zbx_api_get_tags[ZBX_API_PARAM_SORTFIELD],
						zbx_api_get_tags[ZBX_API_PARAM_SORTORDER]);
				rc = FAIL;
			}

			/* set field sort order */
			for (i = 0; i < fields.values_num; i++)
			{
				zbx_api_sort_t	*sort = (zbx_api_sort_t *)self->sort.values[i];

				if (0 == strcmp(fields.values[i], "ASC"))
				{
					sort->order = ZBX_API_SORT_ASC;
				}
				else if (0 == strcmp(fields.values[i], "DESC"))
				{
					sort->order = ZBX_API_SORT_DESC;
				}
				else
				{
					*error = zbx_dsprintf(*error, "invalid sort order \"%s\"", fields.values[i]);
					rc = FAIL;
					break;
				}
			}

			zbx_vector_str_clear_ext(&fields, zbx_ptr_free);
			zbx_vector_str_clear(&fields);
		}

		zbx_vector_str_destroy(&fields);

		if (SUCCEED != rc)
			goto out;
	}

	ret = SUCCEED;
out:
	return ret;
}


/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_validate                                             *
 *                                                                            *
 * Purpose: validates get request common parameters                           *
 *                                                                            *
 * Parameters: self      - [IN] the common get request parameter data         *
 *             error     - [OUT] the error message                            *
 *                                                                            *
 * Return value: SUCCEED - the parameters were validated parsed successfully. *
 *               FAIL    - the validation failed failed, the error message is *
 *                         stored in error parameter.                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_validate(const zbx_api_get_t *self, char **error)
{
	if (SUCCEED != zbx_api_get_validate_param_dependency(self, ZBX_API_PARAM_EXCLUDESEARCH, ZBX_API_PARAM_SEARCH,
			error))
	{
		return FAIL;
	}

	if (SUCCEED != zbx_api_get_validate_param_dependency(self, ZBX_API_PARAM_SEARCHWILDCARDSENABLED,
			ZBX_API_PARAM_SEARCH, error))
	{
		return FAIL;
	}

	if (0 == (self->parameters & (1 << ZBX_API_PARAM_SEARCH)) &&
			0 == (self->parameters & (1 << ZBX_API_PARAM_FILTER)) &&
			0 != (self->parameters & (1 << ZBX_API_PARAM_SEARCHBYANY)))
	{
		*error = zbx_dsprintf(*error, "parameter \"%s\" requires either parameter \"%s\" or parameter \"%s\""
				" to be defined", zbx_api_get_tags[ZBX_API_PARAM_SEARCHBYANY],
				zbx_api_get_tags[ZBX_API_PARAM_FILTER], zbx_api_get_tags[ZBX_API_PARAM_SEARCH]);
		return FAIL;
	}

	if (SUCCEED != zbx_api_get_validate_param_dependency(self, ZBX_API_PARAM_SORTFIELD, ZBX_API_PARAM_SORTORDER,
			error))
	{
		return FAIL;
	}

	if (SUCCEED != zbx_api_get_validate_param_dependency(self, ZBX_API_PARAM_SORTORDER, ZBX_API_PARAM_SORTFIELD,
			error))
	{
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_free                                                 *
 *                                                                            *
 * Purpose: frees get request common parameters                               *
 *                                                                            *
 * Parameters: self      - [IN] the common get request parameter data         *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_get_free(zbx_api_get_t *self)
{
	zbx_vector_ptr_clear_ext(&self->sort, (zbx_mem_free_func_t)zbx_api_sort_free);
	zbx_vector_ptr_destroy(&self->sort);

	zbx_api_filter_free(&self->filter);
	zbx_api_query_free(&self->output);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_field_get                                                *
 *                                                                            *
 * Purpose: searches for the specified field in a fields array                *
 *                                                                            *
 * Parameters: fields - [IN] an array containing field definitions            *
 *             name   - [IN] the field name                                   *
 *                                                                            *
 * Return value: The found field or NULL if no field had the specified name.  *
 *                                                                            *
 ******************************************************************************/
const zbx_api_field_t	*zbx_api_field_get(const zbx_api_field_t *fields, const char *name)
{
	const zbx_api_field_t	*field;

	for (field = fields; NULL != field->name; field++)
	{
		if (0 == strcmp(field->name, name))
			return field;
	}

	return NULL;
}

