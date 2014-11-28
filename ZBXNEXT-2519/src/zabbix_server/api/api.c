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

#define ZBX_API_GETOPTIONS_PARAMS_TOTAL		13

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
static const char *zbx_api_getoptions_tags[] = {
		"output",
		"countOutput",
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
 * Function: zbx_api_value_validate                                           *
 *                                                                            *
 * Purpose: validates if the value contains acceptable data for its type      *
 *                                                                            *
 * Parameters: value - [IN] the value to validate                             *
 *             type  - [IN] the value type (see ZBX_TYPE_* defines)           *
 *                                                                            *
 * Return value: SUCCEED - the validation was successful                      *
 *               FAIL    - the value contains invalid data                    *
 *                                                                            *
 * Comments: Object is an associative array and is stored as ptr pair vector. *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_value_validate(const char *value, int type)
{
	switch (type)
	{
		case ZBX_TYPE_ID:
		case ZBX_TYPE_UINT:
			return is_uint64(value, NULL);
		case ZBX_TYPE_INT:
			if ('-' == *value)
				value++;
			return is_uint31(value, NULL);
		case ZBX_TYPE_FLOAT:
		{
			char	*float_chars = ".,-+eE";

			while ('\0' != *value)
			{
				if (0 == isdigit((unsigned char)*value) && NULL == strchr(float_chars, *value))
					return FAIL;
			}
			return SUCCEED;
		}
	}
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_getoptions_register_param                                *
 *                                                                            *
 * Purpose: checks conflicting parameters and marks parameter as parsed       *
 *                                                                            *
 * Parameters: self    - [IN] common get request options                      *
 *             paramid - [IN] the parameter to check                          *
 *             mask    - [IN] the mask of conflicting parameters              *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the parameter was registered successfully.         *
 *               FAIL    - parameter conflict was found, the error message    *
 *                         is returned in error parameter.                    *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_getoptions_register_param(zbx_api_getoptions_t *self, int paramid, int mask, char **error)
{
	if (0 != (self->parameters & mask))
	{
		int	i;

		mask &= self->parameters;

		for (i = 0; i < ZBX_API_GETOPTIONS_PARAMS_TOTAL; i++)
		{
			if (0 != (mask & (1 << i)))
			{
				if (paramid != i)
				{
					*error = zbx_dsprintf(*error, "Parameter \"%s\" conflicts with parameter"
							" \"%s\"", zbx_api_getoptions_tags[paramid],
							zbx_api_getoptions_tags[i]);
				}
				else
				{
					*error = zbx_dsprintf(*error, "Duplicate parameter \"%s\" found",
							zbx_api_getoptions_tags[paramid]);
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
 * Function: zbx_api_getoptions_validate_dependency                           *
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
static int	zbx_api_getoptions_validate_dependency(const zbx_api_getoptions_t *self, int paramid, int requiredid,
		char **error)
{
	if (0 != (self->parameters & (1 << paramid)) && 0 == (self->parameters & (1 << requiredid)))
	{
		*error = zbx_dsprintf(*error, "Parameter \"%s\" requires parameter \"%s\" to be defined",
				zbx_api_getoptions_tags[paramid], zbx_api_getoptions_tags[requiredid]);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_db_free_columns                                          *
 *                                                                            *
 * Purpose: frees result set columns                                          *
 *                                                                            *
 * Parameters: columns - [IN] the columns to free                             *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_db_free_columns(zbx_vector_str_t *columns)
{
	zbx_vector_str_clear_ext(columns, zbx_ptr_free);
	zbx_vector_str_destroy(columns);
	zbx_free(columns);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_query_result_free                                        *
 *                                                                            *
 * Purpose: frees query result set                                            *
 *                                                                            *
 * Parameters: self - [IN] the result set to free                             *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_query_result_free(zbx_api_query_result_t *self)
{
	zbx_free(self->name);
	zbx_vector_ptr_clear_ext(&self->rows, (zbx_mem_free_func_t)zbx_api_db_free_rows);
	zbx_vector_ptr_destroy(&self->rows);
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
	self->key = -1;
	self->fields_num = 0;
	zbx_vector_ptr_create(&self->fields);
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
	zbx_vector_ptr_destroy(&self->fields);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_vector_ptr_pair_clear                                    *
 *                                                                            *
 * Purpose: clears ptr pair vector. used to store key=>value string mapping   *
 *                                                                            *
 * Parameters: vector - [IN] the vector to clear                              *
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
 * Function: zbx_api_filter_vector_clear                                      *
 *                                                                            *
 * Purpose: clears ptr pair vector used to store <field>=>value mapping,      *
 *          where <field> is constant reference to filter field.              *
 *                                                                            *
 * Parameters: vector - [IN] the vector to clear                              *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_filter_vector_clear(zbx_vector_ptr_pair_t *vector)
{
	int	i;

	for (i = 0; i < vector->values_num; i++)
		zbx_free(vector->values[i].second);

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
	zbx_api_filter_vector_clear(&self->like);
	zbx_api_filter_vector_clear(&self->exact);

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
		*error = zbx_dsprintf(*error, "Cannot parse parameter \"%s\" value", param);
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
		*error = zbx_dsprintf(*error, "Cannot parse parameter \"%s\" value", param);
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
			*error = zbx_dsprintf(*error, "Invalid parameter \"%s\" value \"%s\"", param, data);
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
		*error = zbx_dsprintf(*error, "Cannot parse parameter \"%s\" value", param);
		goto out;
	}

	if (0 == is_null)
	{
		char	*ptr = data;

		if ('-' == *ptr)
			ptr++;

		if (SUCCEED != is_uint31(ptr, value))
		{
			*error = zbx_dsprintf(*error, "Invalid parameter \"%s\" value \"%s\"", param, data);
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
		*error = zbx_dsprintf(*error, "Cannot open object parameter \"%s\"", param);
		goto out;
	}

	*next = jp.end + 1;

	while (NULL != (p = zbx_json_pair_next(&jp, p, name, sizeof(name))))
	{
		if (NULL == (p = zbx_json_decodevalue_dyn(p, &data, &data_alloc, &is_null)))
		{
			*error = zbx_dsprintf(*error, "Cannot parse parameter \"%s\" value", param);
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
 * Function: zbx_api_get_param_filter                                         *
 *                                                                            *
 * Purpose: gets filter exact or like fields from json data                   *
 *                                                                            *
 * Parameters: param  - [IN] the parameter name                               *
 *             next   - [IN/OUT] the next character in json data buffer       *
 *             fields - [IN] an array of available fields                     *
 *             value  - [OUT] the parsed value                                *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_get_param_filter(const char *param, const char **next, const zbx_api_field_t *fields, int like,
		zbx_vector_ptr_pair_t *value, char **error)
{
	zbx_vector_ptr_pair_t	objects;
	int			ret = FAIL, i;
	const zbx_api_field_t	*field;
	zbx_ptr_pair_t		pair;

	zbx_vector_ptr_pair_create(&objects);

	if (SUCCEED != zbx_api_get_param_object(param, next, &objects, error))
		goto out;

	for (i = 0; i < objects.values_num; i++)
	{
		if (NULL == (field = zbx_api_object_get_field(fields, (char *)objects.values[i].first)))
		{
			*error = zbx_dsprintf(*error, "Invalid parameter \"%s\" field name \"%s\"",
					param, (char *)objects.values[i].first);
			goto out;
		}

		pair.first = (void *)field;

		switch (field->type)
		{
			case ZBX_TYPE_ID:
			case ZBX_TYPE_UINT:
			case ZBX_TYPE_FLOAT:
			case ZBX_TYPE_INT:
				if (0 != like)
				{
					*error = zbx_dsprintf(*error, "Invalid parameter \"%s\" field \"%s\" type",
							param, (char *)objects.values[i].second);
					goto out;
				}
				if (SUCCEED != zbx_api_value_validate((char *)objects.values[i].second, field->type))
				{
					*error = zbx_dsprintf(*error, "Invalid parameter \"%s\" field \"%s\" value",
							param, (char *)objects.values[i].second);
					goto out;
				}

				/* in the case of numeric values simply move the value to filter */
				pair.second = objects.values[i].second;
				objects.values[i].second = NULL;
				break;
			case ZBX_TYPE_TEXT:
			case ZBX_TYPE_LONGTEXT:
			case ZBX_TYPE_SHORTTEXT:
				if (0 == like)
				{
					*error = zbx_dsprintf(*error, "Invalid parameter \"%s\" field \"%s\" type",
							param, (char *)objects.values[i].second);
					goto out;
				}
				/* fall through to char type processing */
			case ZBX_TYPE_CHAR:
				pair.second = DBdyn_escape_like_pattern(objects.values[i].second);
				zbx_free(objects.values[i].second);
				break;
		}

		zbx_vector_ptr_pair_append(value, pair);

		/* free the field name and move ownership of field value to filter */
		zbx_free(objects.values[i].first);
	}

	ret = SUCCEED;
out:
	zbx_api_filter_vector_clear(&objects);

	if (SUCCEED != ret)
		zbx_api_vector_ptr_pair_clear(value);

	zbx_vector_ptr_pair_destroy(&objects);

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
		*error = zbx_dsprintf(*error, "Cannot open parameter \"%s\" value array", param);
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
		*error = zbx_dsprintf(*error, "Cannot parse parameter \"%s\" value", param);
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
 * Parameters: param  - [IN] the parameter name                               *
 *             next   - [IN/OUT] the next character in json data buffer       *
 *             fields - [IN] an array of available object fields              *
 *             value  - [OUT] the parsed value                                *
 *             error  - [OUT] the error message                               *
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
int	zbx_api_get_param_query(const char *param, const char **next, const zbx_api_field_t *fields,
		zbx_api_query_t *value, char **error)
{
	char			*data = NULL;
	int			ret = FAIL, i;
	const zbx_api_field_t	*field;

	if ('[' == **next)
	{
		zbx_vector_str_t	outfields;

		zbx_vector_str_create(&outfields);

		if (SUCCEED == (ret = zbx_api_get_param_array(param, next, &outfields, error)))
		{
			for (i = 0; i < outfields.values_num; i++)
			{
				field = zbx_api_object_get_field(fields, outfields.values[i]);
				if (NULL == field)
				{
					*error = zbx_dsprintf(*error, "Invalid parameter \"%s\" query field \"%s\"",
							param, outfields.values[i]);
					ret = FAIL;

					break;
				}
				zbx_vector_ptr_append(&value->fields, (void *)field);
			}

			if (SUCCEED != ret)
				zbx_vector_ptr_clear(&value->fields);
		}

		zbx_vector_str_clear_ext(&outfields, zbx_ptr_free);
		zbx_vector_str_destroy(&outfields);
	}
	else
	{
		if (SUCCEED != zbx_api_get_param_string(param, next, &data, error))
			goto out;

		if (NULL == data || 0 == strcmp(data, ZBX_API_PARAM_QUERY_EXTEND))
		{
			for (field = fields; NULL != field->name; field++)
				zbx_vector_ptr_append(&value->fields, (void *)field);
		}
		else if (0 == strcmp(data, ZBX_API_PARAM_QUERY_COUNT))
		{
			value->key = 0;
		}
		else
		{
			*error = zbx_dsprintf(*error, "Invalid parameter \"%s\" value \"%s\"", param, data);
			goto out;
		}

		ret = SUCCEED;
	}

	value->key = 0;
	value->fields_num = value->fields.values_num;
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
 * Function: zbx_api_getoptions_init                                          *
 *                                                                            *
 * Purpose: initializes common get request parameters                         *
 *                                                                            *
 * Parameters: self - [IN] the common get request parameter data              *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_getoptions_init(zbx_api_getoptions_t *self)
{
	memset(self, 0, sizeof(zbx_api_getoptions_t));

	self->access = ZBX_API_ACCESS_READ;

	zbx_api_query_init(&self->output);
	zbx_api_filter_init(&self->filter);

	zbx_vector_ptr_create(&self->sort);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_getoptions_parse                                         *
 *                                                                            *
 * Purpose: parses get request common parameter                               *
 *                                                                            *
 * Parameters: self      - [IN] the common get request parameter data         *
 *             fields    - [IN] an array of object fields                     *
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
int	zbx_api_getoptions_parse(zbx_api_getoptions_t *self, const zbx_api_field_t *fields, const char *parameter,
		struct zbx_json_parse *json, const char **next, char **error)
{
	int		ret = FAIL;
	unsigned char	value_flag, value_bool;

	if (0 == strcmp(parameter, zbx_api_getoptions_tags[ZBX_API_PARAM_OUTPUT]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_OUTPUT,
				ZBX_API_PARAM_OUTPUT_CONFLICT, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_query(zbx_api_getoptions_tags[ZBX_API_PARAM_OUTPUT], next, fields,
				&self->output, error))
		{
			goto out;
		}

		self->output_num = self->output.fields.values_num;
	}
	else if (0 == strcmp(parameter, zbx_api_getoptions_tags[ZBX_API_PARAM_OUTPUTCOUNT]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_OUTPUTCOUNT,
				ZBX_API_PARAM_OUTPUTCOUNT_CONFLICT, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_flag(zbx_api_getoptions_tags[ZBX_API_PARAM_OUTPUTCOUNT], next,
				&value_flag, error))
		{
			goto out;
		}

		if (0 != value_flag)
			self->output.key = 0;
	}
	else if (0 == strcmp(parameter, zbx_api_getoptions_tags[ZBX_API_PARAM_EDITABLE]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_EDITABLE,
				1 << ZBX_API_PARAM_EDITABLE, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_bool(zbx_api_getoptions_tags[ZBX_API_PARAM_EDITABLE], next,
				&value_bool, error))
		{
			goto out;
		}

		if (0 != value_bool)
			self->access = ZBX_API_ACCESS_WRITE;
	}
	else if (0 == strcmp(parameter, zbx_api_getoptions_tags[ZBX_API_PARAM_LIMIT]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_LIMIT,
				ZBX_API_PARAM_LIMIT_CONFLICT, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_int(zbx_api_getoptions_tags[ZBX_API_PARAM_LIMIT], next, &self->limit,
				error))
		{
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_getoptions_tags[ZBX_API_PARAM_PRESERVEKEYS]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_PRESERVEKEYS,
				ZBX_API_PARAM_PRESERVEKEYS_CONFLICT, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_flag(zbx_api_getoptions_tags[ZBX_API_PARAM_PRESERVEKEYS], next,
				&self->output_byid, error))
		{
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_getoptions_tags[ZBX_API_PARAM_EXCLUDESEARCH]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_EXCLUDESEARCH,
				1 << ZBX_API_PARAM_EXCLUDESEARCH, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_flag(zbx_api_getoptions_tags[ZBX_API_PARAM_EXCLUDESEARCH], next,
				&value_flag, error))
		{
			goto out;
		}

		if (0 != value_flag)
			self->filter.options |= ZBX_API_FILTER_OPTION_EXCLUDE;
	}
	else if (0 == strcmp(parameter, zbx_api_getoptions_tags[ZBX_API_PARAM_STARTSEARCH]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_STARTSEARCH,
				1 << ZBX_API_PARAM_STARTSEARCH, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_flag(zbx_api_getoptions_tags[ZBX_API_PARAM_STARTSEARCH], next,
				&value_flag, error))
		{
			goto out;
		}

		if (0 != value_flag)
			self->filter.options |= ZBX_API_FILTER_OPTION_START;
	}
	else if (0 == strcmp(parameter, zbx_api_getoptions_tags[ZBX_API_PARAM_SEARCHBYANY]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_SEARCHBYANY,
				1 << ZBX_API_PARAM_SEARCHBYANY, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_bool(zbx_api_getoptions_tags[ZBX_API_PARAM_STARTSEARCH], next,
				&value_flag, error))
		{
			goto out;
		}

		if (0 != value_flag)
			self->filter.options |= ZBX_API_FILTER_OPTION_ANY;
	}
	else if (0 == strcmp(parameter, zbx_api_getoptions_tags[ZBX_API_PARAM_SEARCHWILDCARDSENABLED]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_SEARCHWILDCARDSENABLED,
				1 << ZBX_API_PARAM_SEARCHWILDCARDSENABLED, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_bool(zbx_api_getoptions_tags[ZBX_API_PARAM_SEARCHWILDCARDSENABLED],
				next, &value_flag, error))
			goto out;

		if (0 != value_flag)
			self->filter.options |= ZBX_API_FILTER_OPTION_WILDCARD;
	}
	else if (0 == strcmp(parameter, zbx_api_getoptions_tags[ZBX_API_PARAM_FILTER]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_FILTER, 1 << ZBX_API_PARAM_FILTER,
				error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_filter(zbx_api_getoptions_tags[ZBX_API_PARAM_FILTER], next, fields, 0,
				&self->filter.exact, error))
		{
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_getoptions_tags[ZBX_API_PARAM_SEARCH]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_SEARCH, 1 << ZBX_API_PARAM_SEARCH,
				error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_filter(zbx_api_getoptions_tags[ZBX_API_PARAM_SEARCH], next, fields, 1,
				&self->filter.like, error))
		{
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_getoptions_tags[ZBX_API_PARAM_SORTFIELD]))
	{
		zbx_vector_str_t	sortfields;
		int			i, rc;

		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_SORTFIELD,
				ZBX_API_PARAM_SORTFIELD_CONFLICT, error))
		{
			goto out;
		}

		zbx_vector_str_create(&sortfields);

		if (SUCCEED == (rc = zbx_api_get_param_string_or_array(zbx_api_getoptions_tags[ZBX_API_PARAM_SORTFIELD],
				next, &sortfields, error)))
		{
			/* add the sort fields to field vector if it was not done by sortorder parameter */
			if (0 == self->sort.values_num && 0 != sortfields.values_num)
			{
				for (i = 0; i < sortfields.values_num; i++)
				{
					zbx_api_sort_t	*sort;

					sort = (zbx_api_sort_t *)zbx_malloc(NULL, sizeof(zbx_api_sort_t));
					memset(sort, 0, sizeof(zbx_api_sort_t));
					zbx_vector_ptr_append(&self->sort, sort);
				}
			}

			/* check that sortfield items match sortoder items */
			if (sortfields.values_num != self->sort.values_num)
			{
				*error = zbx_dsprintf(*error, "The number of parameter \"%s\" values differs from the"
						" number of parameter \"%s\" values",
						zbx_api_getoptions_tags[ZBX_API_PARAM_SORTFIELD],
						zbx_api_getoptions_tags[ZBX_API_PARAM_SORTORDER]);
				rc = FAIL;
			}

			/* copy sort fields */
			for (i = 0; i < sortfields.values_num; i++)
			{
				zbx_api_sort_t	*sort = (zbx_api_sort_t *)self->sort.values[i];

				if (NULL == (sort->field = zbx_api_object_get_field(fields, sortfields.values[i])))
				{
					*error = zbx_dsprintf(*error, "Invalid parameter \"%s\" value \"%s\"",
							zbx_api_getoptions_tags[ZBX_API_PARAM_SORTFIELD],
							sortfields.values[i]);

					zbx_vector_ptr_clear_ext(&self->sort, zbx_ptr_free);
					rc = FAIL;
					break;
				}

			}

			/* clear the field vector without freeing fields, as they are copied to options sort data */
			zbx_vector_str_clear_ext(&sortfields, zbx_ptr_free);
		}

		zbx_vector_str_destroy(&sortfields);

		if (SUCCEED != rc)
			goto out;
	}
	else if (0 == strcmp(parameter, zbx_api_getoptions_tags[ZBX_API_PARAM_SORTORDER]))
	{
		zbx_vector_str_t	sortfields;
		int			i, rc;

		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_SORTORDER,
				ZBX_API_PARAM_SORTORDER_CONFLICT, error))
		{
			goto out;
		}

		zbx_vector_str_create(&sortfields);

		if (SUCCEED == (rc = zbx_api_get_param_string_or_array(zbx_api_getoptions_tags[ZBX_API_PARAM_SORTORDER],
				next, &sortfields, error)))
		{
			/* add the sort fields to field vector if it was not done by sortfield parameter */
			if (0 == self->sort.values_num && 0 != sortfields.values_num)
			{
				for (i = 0; i < sortfields.values_num; i++)
				{
					zbx_api_sort_t	*sort;

					sort = (zbx_api_sort_t *)zbx_malloc(NULL, sizeof(zbx_api_sort_t));
					memset(sort, 0, sizeof(zbx_api_sort_t));
					zbx_vector_ptr_append(&self->sort, sort);
				}
			}

			/* check that sortfield items match sortoder items */
			if (sortfields.values_num != self->sort.values_num)
			{
				*error = zbx_dsprintf(*error, "The number of parameter \"%s\" values differs from the"
						" number of parameter \"%s\" values",
						zbx_api_getoptions_tags[ZBX_API_PARAM_SORTFIELD],
						zbx_api_getoptions_tags[ZBX_API_PARAM_SORTORDER]);
				rc = FAIL;
			}

			/* set field sort order */
			for (i = 0; i < sortfields.values_num; i++)
			{
				zbx_api_sort_t	*sort = (zbx_api_sort_t *)self->sort.values[i];

				if (0 == strcmp(sortfields.values[i], "ASC"))
				{
					sort->order = ZBX_API_SORT_ASC;
				}
				else if (0 == strcmp(sortfields.values[i], "DESC"))
				{
					sort->order = ZBX_API_SORT_DESC;
				}
				else
				{
					*error = zbx_dsprintf(*error, "Invalid sort order \"%s\"",
							sortfields.values[i]);
					rc = FAIL;
					break;
				}
			}

			zbx_vector_str_clear_ext(&sortfields, zbx_ptr_free);
		}

		zbx_vector_str_destroy(&sortfields);

		if (SUCCEED != rc)
			goto out;
	}

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_getoptions_add_output_field                              *
 *                                                                            *
 * Purpose: checks if the specified field is in output fields vector and adds *
 *          it if necessary                                                   *
 *                                                                            *
 * Parameters: self   - [IN] the common get request parameter data            *
 *             fields - [IN] an array of object fields                        *
 *             name   - [IN] the field name                                   *
 *             index  - [OUT] the index of the field in output fields vector  *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the field was found or added                       *
 *               FAIL    - no field with the specified name was found in      *
 *                         fields vector                                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_getoptions_add_output_field(zbx_api_getoptions_t *self, const zbx_api_field_t *fields, const char *name,
		int *index, char **error)
{
	const zbx_api_field_t	*field;
	int			ret = FAIL, i;

	if (NULL == (field = zbx_api_object_get_field(fields, name)))
	{
		*error = zbx_dsprintf(*error, "Invalid additional output field name \"%s\"", name);
		goto out;
	}

	ret = SUCCEED;

	for (i = 0; i < self->output.fields.values_num; i++)
	{
		if (self->output.fields.values[i] == field)
			goto out;
	}

	zbx_vector_ptr_append(&self->output.fields, (void *)field);
out:
	if (SUCCEED == ret)
		*index = i;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_getoptions_finalize                                      *
 *                                                                            *
 * Purpose: finalizes common get request and validates it                     *
 *                                                                            *
 * Parameters: self   - [IN] the common get request parameter data            *
 *             fields - [IN] an array of object fields                        *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the request was finalized successfully.            *
 *               FAIL    - the validation failed failed.                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_getoptions_finalize(zbx_api_getoptions_t *self, const zbx_api_field_t *fields, char **error)
{
	int			i;
	const zbx_api_field_t	*field;

	if (SUCCEED != zbx_api_getoptions_validate_dependency(self, ZBX_API_PARAM_EXCLUDESEARCH, ZBX_API_PARAM_SEARCH,
			error))
	{
		return FAIL;
	}

	if (SUCCEED != zbx_api_getoptions_validate_dependency(self, ZBX_API_PARAM_SEARCHWILDCARDSENABLED,
			ZBX_API_PARAM_SEARCH, error))
	{
		return FAIL;
	}

	if (0 == (self->parameters & (1 << ZBX_API_PARAM_SEARCH)) &&
			0 == (self->parameters & (1 << ZBX_API_PARAM_FILTER)) &&
			0 != (self->parameters & (1 << ZBX_API_PARAM_SEARCHBYANY)))
	{
		*error = zbx_dsprintf(*error, "Parameter \"%s\" requires either parameter \"%s\" or parameter \"%s\""
				" to be defined", zbx_api_getoptions_tags[ZBX_API_PARAM_SEARCHBYANY],
				zbx_api_getoptions_tags[ZBX_API_PARAM_FILTER],
				zbx_api_getoptions_tags[ZBX_API_PARAM_SEARCH]);
		return FAIL;
	}

	if (SUCCEED != zbx_api_getoptions_validate_dependency(self, ZBX_API_PARAM_SORTFIELD, ZBX_API_PARAM_SORTORDER,
			error))
	{
		return FAIL;
	}

	if (SUCCEED != zbx_api_getoptions_validate_dependency(self, ZBX_API_PARAM_SORTORDER, ZBX_API_PARAM_SORTFIELD,
			error))
	{
		return FAIL;
	}

	/* translate '*' to '%' if wildcard search option is enabled */
	if (0 != (self->filter.options & ZBX_API_FILTER_OPTION_WILDCARD))
	{
		for (i = 0; i < self->filter.like.values_num; i++)
		{
			char	*ptr;

			for (ptr = (char *)self->filter.like.values[i].second; '\0' != *ptr; ptr++)
			{
				if ('*' == *ptr)
					*ptr = '%';
			}
		}
	}

	/* if output was not set add all fields, as 'extend' is the default output option */
	if (-1 == self->output.key)
	{
		for (field = fields; NULL != field->name; field++)
			zbx_vector_ptr_append(&self->output.fields, (void *)field);

		self->output.fields_num = self->output.fields.values_num;
		self->output.key = 0;
	}

	if (0 != self->output_byid)
	{
		/* if indexed output is set ensure that object id (first field in object definition) */
		/* is also retrieved.                                                                */
		if (SUCCEED != zbx_api_getoptions_add_output_field(self, fields, fields->name, &self->output.key,
				error))
		{
			return FAIL;
		}

	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_getoptions_free                                          *
 *                                                                            *
 * Purpose: frees get request common parameters                               *
 *                                                                            *
 * Parameters: self - [IN] the common get request parameter data              *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_getoptions_free(zbx_api_getoptions_t *self)
{
	zbx_vector_ptr_clear_ext(&self->sort, zbx_ptr_free);
	zbx_vector_ptr_destroy(&self->sort);

	zbx_api_filter_free(&self->filter);
	zbx_api_query_free(&self->output);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_object_get_field                                         *
 *                                                                            *
 * Purpose: searches for the specified field in a fields array                *
 *                                                                            *
 * Parameters: fields - [IN] an array containing field definitions            *
 *             name   - [IN] the field name                                   *
 *                                                                            *
 * Return value: The found field or NULL if no field had the specified name.  *
 *                                                                            *
 ******************************************************************************/
const zbx_api_field_t	*zbx_api_object_get_field(const zbx_api_field_t *fields, const char *name)
{
	const zbx_api_field_t	*field;

	for (field = fields; NULL != field->name; field++)
	{
		if (0 == strcmp(field->name, name))
			return field;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_sql_add_query                                            *
 *                                                                            *
 * Purpose: adds query data to sql statement                                  *
 *                                                                            *
 * Parameters: sql        - [IN/OUT] the sql statement                        *
 *             sql_alloc  - [IN/OUT] the allocated size of sql statement      *
 *             sql_offset - [IN/OUT] the sql statement end offset             *
 *             query      - [IN] the query to add                             *
 *             table      - [IN] the source table name                        *
 *             alias      - [IN] the source table alias                       *
 *                                                                            *
 * Comments: This function formats sql select statement beginning. For        *
 *           ZBX_API_QUERY_COUNT queries it will have format:                 *
 *              select count(*) from <table> <alias>                          *
 *           while for other queries it will have format:                     *
 *              select <alias>.<f1>,<alias>.<f2>... from <table <alias>       *
 *           where <f1>, <f2>.. are field names from query fields list.       *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_sql_add_query(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_api_query_t *query,
		const char *table, const char *alias)
{
	int	i;

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "select ");

	if (0 == query->fields.values_num)
	{
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "count(*)");
	}
	else
	{
		for (i = 0; i < query->fields.values_num; i++)
		{
			const zbx_api_field_t	*field = (const zbx_api_field_t *)query->fields.values[i];

			if (0 < i)
				zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ',');

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s.%s", alias, field->name);
		}
	}

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " from %s %s", table, alias);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_sql_add_filter                                           *
 *                                                                            *
 * Purpose: adds filter data to sql statement                                 *
 *                                                                            *
 * Parameters: sql           - [IN/OUT] the sql statement                     *
 *             sql_alloc     - [IN/OUT] the allocated size of sql statement   *
 *             sql_offset    - [IN/OUT] the sql statement end offset          *
 *             filter        - [IN] the filter to add                         *
 *             alias         - [IN] the source table alias                    *
 *             sql_condition - [IN/OUT] the sql condition inserted before     *
 *                             next filter rule. It is set to 'where'  if     *
 *                             there were no where rules added before and     *
 *                             'and'/'or' for the rest of filter rule         *
 *                             depending on ZBX_API_FILTER_OPTION_ANY flag.   *
 *                                                                            *
 * Comments: This function adds a set of exact match rules specified in       *
 *           filter.exact vector and a set of 'like' match rules specified in *
 *           filter.like vector.                                              *
 *           The exact and like matching rules are already validated and      *
 *           escaped if necessary during request parsing.                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_sql_add_filter(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_api_filter_t *filter,
		const char *alias, const char **sql_condition)
{
	int	i;

	if (0 != filter->exact.values_num)
	{
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, *sql_condition);
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ' ');
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '(');

		if (0 != (filter->options & ZBX_API_FILTER_OPTION_ANY))
			*sql_condition = " or ";
		else
			*sql_condition = " and ";

		for (i = 0; i < filter->exact.values_num; i++)
		{
			zbx_ptr_pair_t		*pair = &filter->exact.values[i];
			const zbx_api_field_t	*field = (const zbx_api_field_t *)pair->first;

			if (0 != i)
				zbx_strcpy_alloc(sql, sql_alloc, sql_offset, *sql_condition);

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s.%s=", alias, field->name);

			switch (field->type)
			{
				case ZBX_TYPE_ID:
				case ZBX_TYPE_FLOAT:
				case ZBX_TYPE_INT:
				case ZBX_TYPE_UINT:
					zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s", (char *)pair->second);
					break;
				case ZBX_TYPE_CHAR:
					zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "'%s'", (char *)pair->second);
					break;
			}
		}
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');

		*sql_condition = " and";
	}

	if (0 != filter->like.values_num)
	{
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, *sql_condition);

		if (0 != (filter->options & ZBX_API_FILTER_OPTION_EXCLUDE))
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " not");

		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ' ');
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '(');

		if (0 != (filter->options & ZBX_API_FILTER_OPTION_ANY))
			*sql_condition = " or ";
		else
			*sql_condition = " and ";

		for (i = 0; i < filter->like.values_num; i++)
		{
			zbx_ptr_pair_t		*pair = &filter->like.values[i];
			const zbx_api_field_t	*field = (const zbx_api_field_t *)pair->first;

			if (0 != i)
				zbx_strcpy_alloc(sql, sql_alloc, sql_offset, *sql_condition);

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s.%s like '", alias, field->name);

			if (0 == (filter->options & ZBX_API_FILTER_OPTION_START))
				zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '%');

			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, (char *)pair->second);

			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "%' escape '");
			zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ZBX_SQL_LIKE_ESCAPE_CHAR);
			zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '\'');
		}
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');

		*sql_condition = " and";
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_sql_add_sort                                             *
 *                                                                            *
 * Purpose: adds sort data to sql statement                                   *
 *                                                                            *
 * Parameters: sql        - [IN/OUT] the sql statement                        *
 *             sql_alloc  - [IN/OUT] the allocated size of sql statement      *
 *             sql_offset - [IN/OUT] the sql statement end offset             *
 *             sort       - [IN] the sort order to add                        *
 *             alias      - [IN] the source table alias                       *
 *                                                                            *
 * Comments: This function adds order by clause from the sort vector fields.  *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_sql_add_sort(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_vector_ptr_t *sort,
		const char *alias)
{
	const char	*sql_condition = " order by", *order;
	int		i;

	for (i = 0; i < sort->values_num; i++)
	{
		zbx_api_sort_t	*field = (zbx_api_sort_t *)sort->values[i];

		order = (ZBX_API_SORT_ASC == field->order ? "asc" : "desc");
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s %s.%s %s", sql_condition, alias, field->field->name,
				order);
		sql_condition = ",";
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_db_fetch_rows                                            *
 *                                                                            *
 * Purpose: perform sql select, fetch and store the resulting rows            *
 *                                                                            *
 * Parameters: sql         - [IN] the sql statement                           *
 *             columns_num - [IN] the number of columns in result set,        *
 *                                0 - count(*) request                        *
 *             rows_num    - [IN] the maximum number of rows in result set,   *
 *                                0 - unlimited                               *
 *             rows        - [OUT] the fetched rows                           *
 *             error       - [OUT] error message                              *
 *                                                                            *
 * Return value: SUCCEED - the rows were fetched successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: The result set is stored as ptr vector (rows) of str vectors     *
 *           (columns).                                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_db_fetch_rows(const char *sql, int fields_num, int rows_num, zbx_vector_ptr_t *rows, char **error)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL, i, col_num;

	if (0 == rows_num)
		result = DBselect("%s", sql);
	else
		result = DBselectN(sql, rows_num);

	if (NULL == result)
	{
		/* TODO: fetch the correct SQL error message ? */
		*error = zbx_dsprintf(*error, "An SQL error occurred while executing: %s", sql);
		goto out;
	}

	/* reserve 1 column for count(*) requests - they don't have output fields */
	col_num = (0 == fields_num ? 1 : fields_num);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_vector_str_t	*columns;

		columns = (zbx_vector_str_t *)zbx_malloc(NULL, sizeof(zbx_vector_str_t));
		zbx_vector_str_create(columns);

		for (i = 0; i < col_num; i++)
		{
			char	*value = NULL;

			if (SUCCEED != DBis_null(row[i]))
				value = zbx_strdup(value, row[i]);

			zbx_vector_str_append(columns, value);
		}

		zbx_vector_ptr_append(rows, columns);
	}

	DBfree_result(result);

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_db_fetch_query                                           *
 *                                                                            *
 * Purpose: fetches a query based on a key field from result set              *
 *                                                                            *
 * Parameters: sql           - [IN/OUT] the sql statement                     *
 *             sql_alloc     - [IN/OUT] the allocated size of sql statement   *
 *             sql_offset    - [IN/OUT] the sql statement end offset          *
 *             column_name   - [IN] the name of query column                  *
 *             query         - [IN] the query defining output fields          *
 *             result        - [IN/OUT] the result containing input rows and  *
 *                                      retrieved result set                  *
 *             error         - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the rows were fetched successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: This function performs subqueries for each input result set row  *
 *           based on initial query specified in sql and the field value      *
 *           specified by key_index. The resulting rows are stored in         *
 *           result.queries vector.                                           *
 *           The sql query has the following format:                          *
 *             select <field1>,<field2>... from <table> where.... <key>=      *
 *           The query is finished by appending field value from input result *
 *           set key_index column.                                            *
 *           Note that only fields of type ID are should to be used as key    *
 *           fields.                                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_db_fetch_query(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *column_name,
		const zbx_api_query_t *query, zbx_api_get_result_t *result, char **error)
{
	int			ret = FAIL, i;
	size_t			sql_offset_original = *sql_offset;
	zbx_api_query_result_t *qr;

	/* create a new query and add to the result */
	qr = (zbx_api_query_result_t *)zbx_malloc(NULL, sizeof(zbx_api_query_result_t));
	qr->name = zbx_strdup(NULL, column_name);
	qr->query = query;
	zbx_vector_ptr_create(&qr->rows);

	zbx_vector_ptr_append(&result->queries, qr);

	for (i = 0; i < result->rows.values_num; i++)
	{
		zbx_vector_str_t	*columns = (zbx_vector_str_t *)result->rows.values[i];
		zbx_vector_ptr_t	*rows;

		/* finish the query by appending key field value to the sql template*/
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, columns->values[query->key]);

		/* create the result set row vector */
		rows = (zbx_vector_ptr_t *)zbx_malloc(NULL, sizeof(zbx_vector_ptr_t));
		zbx_vector_ptr_create(rows);

		/* fetch the result set */
		if (SUCCEED != zbx_api_db_fetch_rows(*sql, query->fields.values_num, 0, rows, error))
			goto out;

		/* store the fetched rows to result.queries rows vector */
		zbx_vector_ptr_append(&qr->rows, rows);

		/* restore the query template */
		*sql_offset = sql_offset_original;
	}
	ret = SUCCEED;

	/* TODO: handle SQL error  */
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_result_init                                          *
 *                                                                            *
 * Purpose: initializes api get request result storage                        *
 *                                                                            *
 * Parameters: self - [IN] the get request result storage                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_get_result_init(zbx_api_get_result_t *self)
{
	zbx_vector_ptr_create(&self->rows);
	zbx_vector_ptr_create(&self->queries);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_result_clean                                         *
 *                                                                            *
 * Purpose: releases resources allocated by get request result storage        *
 *                                                                            *
 * Parameters: self - [IN] the get request result storage                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_get_result_clean(zbx_api_get_result_t *self)
{
	zbx_vector_ptr_clear_ext(&self->queries, (zbx_mem_free_func_t)zbx_api_query_result_free);
	zbx_vector_ptr_destroy(&self->queries);

	zbx_api_db_clean_rows(&self->rows);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_db_clean_rows                                            *
 *                                                                            *
 * Purpose: releases resources allocated to store result set (rows)           *
 *                                                                            *
 * Parameters: self - [IN] the result set data (rows)                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_db_clean_rows(zbx_vector_ptr_t *rows)
{
	zbx_vector_ptr_clear_ext(rows, (zbx_mem_free_func_t)zbx_api_db_free_columns);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_db_free_rows                                             *
 *                                                                            *
 * Purpose: frees resources allocated to store result set (rows)              *
 *                                                                            *
 * Parameters: self - [IN] the result set data (rows)                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_db_free_rows(zbx_vector_ptr_t *rows)
{
	zbx_api_db_clean_rows(rows);
	zbx_vector_ptr_destroy(rows);
	zbx_free(rows);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_json_init                                                *
 *                                                                            *
 * Purpose: initializes json data for api responses                           *
 *                                                                            *
 * Parameters: json - [IN] the json data structure                            *
 *             id   - [IN] the response id                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_json_init(struct zbx_json *json, const char *id)
{
	zbx_json_init(json, 1024);

	zbx_json_addstring(json, ZBX_API_RESULT_TAG_JSONRPC, "2.0", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, ZBX_API_RESULT_TAG_ID, id, ZBX_JSON_TYPE_STRING);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_json_add_query                                           *
 *                                                                            *
 * Purpose: adds a sub query result to json data                              *
 *                                                                            *
 * Parameters: json  - [IN] the json data structure                           *
 *             name  - [IN] the query name                                    *
 *             query - [IN] the query data                                    *
 *             rows  - [IN] the query result                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_json_add_query(struct zbx_json *json, const char *name, const zbx_api_query_t *query,
		const zbx_vector_ptr_t *rows)
{
	int	i;

	if (0 == query->fields.values_num)
	{
		zbx_api_json_add_count(json, name, rows);
	}
	else
	{
		zbx_json_addarray(json, name);

		for (i = 0; i < rows->values_num; i++)
		{
			zbx_json_addobject(json, NULL);
			zbx_api_json_add_row(json, query, rows->values[i], NULL, 0);
			zbx_json_close(json);
		}

		zbx_json_close(json);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_json_add_row                                             *
 *                                                                            *
 * Purpose: adds a single query row to json data                              *
 *                                                                            *
 * Parameters: json    - [IN] the json data structure                         *
 *             query   - [IN] the query data                                  *
 *             columns - [IN] the row to add                                  *
 *             queries - [IN] the sub query vector. Should be NULL when       *
 *                            used for sub query rows                         *
 *             row     - [IN] the row index                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_json_add_row(struct zbx_json *json, const zbx_api_query_t *query, const zbx_vector_str_t *columns,
		const zbx_vector_ptr_t *queries, int row)
{
	int	i;

	for (i = 0; i < query->fields_num; i++)
	{
		const zbx_api_field_t	*field = (const zbx_api_field_t *)query->fields.values[i];

		zbx_json_addstring(json, field->name, columns->values[i], ZBX_JSON_TYPE_STRING);
	}

	if (NULL != queries)
	{
		for (i = 0; i < queries->values_num; i++)
		{
			zbx_api_query_result_t *qr = (zbx_api_query_result_t *)queries->values[i];

			zbx_api_json_add_query(json, qr->name, qr->query, qr->rows.values[row]);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_json_add_count                                           *
 *                                                                            *
 * Purpose: adds count(*) request results to json data                        *
 *                                                                            *
 * Parameters: json - [IN] the json data structure                            *
 *             name - [IN] the column name                                    *
 *             rows - [IN] the query result set                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_json_add_count(struct zbx_json *json, const char *name, const zbx_vector_ptr_t *rows)
{
	zbx_vector_str_t	*columns = (zbx_vector_str_t *)rows->values[0];

	zbx_json_addstring(json, name, columns->values[0], ZBX_JSON_TYPE_STRING);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_json_add_result                                          *
 *                                                                            *
 * Purpose: adds get request result to json data                              *
 *                                                                            *
 * Parameters: json    - [IN] the json data structure                         *
 *             options - [IN] the common get request options                  *
 *             result  - [IN] the request result data                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_json_add_result(struct zbx_json *json, const zbx_api_getoptions_t *options,
		const zbx_api_get_result_t *result)
{
	int	i;

	if (0 == options->output.fields.values_num)
	{
		zbx_api_json_add_count(json, ZBX_API_RESULT_TAG_RESULT, &result->rows);
		return;
	}

	if (0 == options->output_byid || 0 == result->rows.values_num)
	{
		zbx_json_addarray(json, ZBX_API_RESULT_TAG_RESULT);

		for (i = 0; i < result->rows.values_num; i++)
		{
			zbx_json_addobject(json, NULL);
			zbx_api_json_add_row(json, &options->output, result->rows.values[i], &result->queries, i);
			zbx_json_close(json);
		}
	}
	else
	{
		zbx_json_addobject(json, ZBX_API_RESULT_TAG_RESULT);

		for (i = 0; i < result->rows.values_num; i++)
		{
			zbx_vector_str_t	*columns = (zbx_vector_str_t *)result->rows.values[i];

			zbx_json_addobject(json, columns->values[options->output.key]);
			zbx_api_json_add_row(json, &options->output, columns, &result->queries, i);
			zbx_json_close(json);
		}
	}

	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_json_add_error                                           *
 *                                                                            *
 * Purpose: adds error response to json data                                  *
 *                                                                            *
 * Parameters: json  - [IN] the json data structure                           *
 *             error - [IN] the error message                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_json_add_error(struct zbx_json *json, const char *error)
{
	zbx_json_addobject(json, ZBX_API_RESULT_TAG_ERROR);
	zbx_json_addint(json, ZBX_API_RESULT_TAG_ERRCODE, -32602);
	zbx_json_addstring(json, ZBX_API_RESULT_TAG_ERRMESSAGE, "Invalid params.", ZBX_JSON_TYPE_STRING);

	zbx_json_addstring(json, ZBX_API_RESULT_TAG_ERRDATA, NULL == error ? "unknown error" : error,
			ZBX_JSON_TYPE_STRING);

	zbx_json_close(json);
}

