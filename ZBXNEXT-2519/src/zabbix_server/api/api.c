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
#include "log.h"

#include "api.h"

#include "objects/user.h"
#include "objects/mediatype.h"

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
static const char *zbx_api_params[] = {
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
 * Function: zbx_api_object_init                                              *
 *                                                                            *
 * Purpose: initializes API object                                            *
 *                                                                            *
 * Parameters: object - [OUT] the value to initialize                         *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the initialization was successful                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: This function sets API object references to respective table     *
 *           fields in database schema.                                       *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_object_init(zbx_api_class_t *object, char **error)
{
	const char		*__function_name = "zbx_api_object_init";
	zbx_api_property_t	*prop;
	const ZBX_TABLE		*table;
	const ZBX_FIELD		*field;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ", __function_name);

	if (NULL == object->table_name)
	{
		ret = SUCCEED;
		goto out;
	}

	if (NULL == (table = DBget_table(object->table_name)))
	{
		*error = zbx_dsprintf(NULL, "invalid object \"%s\" table name \"%s\"", object->name,
				object->table_name);
		goto out;
	}

	for (prop = object->properties; NULL != prop->name; prop++)
	{
		if (NULL == prop->field_name)
			continue;

		if (NULL == (field = DBget_field(table, prop->field_name)))
		{
			*error = zbx_dsprintf(NULL, "invalid object \"%s\" property \"%s\" field \"%s\"", object->name,
					prop->name, prop->field_name);
			goto out;
		}

		prop->field = field;
	}

	ret = SUCCEED;
out:

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

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
 * Purpose: checks conflicting parameters and registers parameter as parsed   *
 *                                                                            *
 * Parameters: self    - [IN/OUT] common get request options                  *
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
					*error = zbx_dsprintf(*error, "parameter \"%s\" conflicts with parameter"
							" \"%s\"", zbx_api_params[paramid],
							zbx_api_params[i]);
				}
				else
				{
					*error = zbx_dsprintf(*error, "duplicate parameter \"%s\" found",
							zbx_api_params[paramid]);
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
		*error = zbx_dsprintf(*error, "parameter \"%s\" requires parameter \"%s\" to be defined",
				zbx_api_params[paramid], zbx_api_params[requiredid]);
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
 * Parameters: self - [OUT] the query parameter                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_query_init(zbx_api_query_t *self)
{
	zbx_vector_ptr_create(&self->properties);
	self->properties_num = 0;
	self->key = 0;
	self->is_set = ZBX_API_FALSE;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_query_clean                                              *
 *                                                                            *
 * Purpose: frees resources used to store query data                          *
 *                                                                            *
 * Parameters: self - [IN/OUT] the query parameter                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_query_clean(zbx_api_query_t *self)
{
	zbx_vector_ptr_destroy(&self->properties);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_vector_ptr_pair_clear                                    *
 *                                                                            *
 * Purpose: clears ptr pair vector. used to store key => value string mapping *
 *                                                                            *
 * Parameters: vector - [IN/OUT] the vector to clear                          *
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
 * Purpose: clears ptr pair vector used to store <property> => value mapping, *
 *          where <property> is a constant reference to class property        *
 *                                                                            *
 * Parameters: vector - [IN/OUT] the vector to clear                          *
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
 * Parameters: self - [OUT] the filter data                                   *
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
 * Function: zbx_api_filter_clean                                             *
 *                                                                            *
 * Purpose: frees resources used to store filter data                         *
 *                                                                            *
 * Parameters: self - [IN/OUT] the filter data                                *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_filter_clean(zbx_api_filter_t *self)
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
		*error = zbx_dsprintf(*error, "cannot parse parameter \"%s\" value", param);
		goto out;
	}

	*value = (0 == is_null ? ZBX_API_TRUE : ZBX_API_FALSE);

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
			*value = ZBX_API_TRUE;
		}
		else if (0 == strcmp(data, "false"))
		{
			*value = ZBX_API_FALSE;
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
 * Function: zbx_api_get_param_map                                            *
 *                                                                            *
 * Purpose: gets a key=>value map type parameter from json data               *
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
 * Comments: The map is stored as a ptr pair vector.                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_param_map(const char *param, const char **next, zbx_vector_ptr_pair_t *value, char **error)
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
 * Function: zbx_api_get_param_filter                                         *
 *                                                                            *
 * Purpose: gets filter exact or like fields from json data                   *
 *                                                                            *
 * Parameters: param    - [IN] the parameter name                             *
 *             next     - [IN/OUT] the next character in json data buffer     *
 *             objclass - [IN] the object class definition                    *
 *             value    - [OUT] the parsed value                              *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_get_param_filter(const char *param, const char **next, const zbx_api_class_t *objclass,
		int like, zbx_vector_ptr_pair_t *value, char **error)
{
	zbx_vector_ptr_pair_t		objects;
	int				ret = FAIL, i;
	const zbx_api_property_t	*prop;
	zbx_ptr_pair_t			pair;

	zbx_vector_ptr_pair_create(&objects);

	if (SUCCEED != zbx_api_get_param_map(param, next, &objects, error))
		goto out;

	for (i = 0; i < objects.values_num; i++)
	{
		if (NULL == (prop = zbx_api_class_get_property(objclass, (char *)objects.values[i].first)))
		{
			*error = zbx_dsprintf(*error, "invalid parameter \"%s\" field name \"%s\"",
					param, (char *)objects.values[i].first);
			goto out;
		}

		pair.first = (void *)prop;

		switch (prop->field->type)
		{
			case ZBX_TYPE_ID:
			case ZBX_TYPE_UINT:
			case ZBX_TYPE_FLOAT:
			case ZBX_TYPE_INT:
				if (ZBX_API_TRUE == like)
				{
					*error = zbx_dsprintf(*error, "invalid parameter \"%s\" field \"%s\" type",
							param, (char *)objects.values[i].second);
					goto out;
				}
				if (SUCCEED != zbx_api_value_validate((char *)objects.values[i].second,
						prop->field->type))
				{
					*error = zbx_dsprintf(*error, "invalid parameter \"%s\" field \"%s\" value",
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
				if (ZBX_API_FALSE != like)
				{
					*error = zbx_dsprintf(*error, "invalid parameter \"%s\" field \"%s\" type",
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
	}

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
		zbx_api_vector_ptr_pair_clear(value);

	zbx_api_vector_ptr_pair_clear(&objects);
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
 * Parameters: param    - [IN] the parameter name                             *
 *             next     - [IN/OUT] the next character in json data buffer     *
 *             objclass - [IN] the object class definition                    *
 *             value    - [OUT] the parsed value                              *
 *             error    - [OUT] the error message                             *
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
int	zbx_api_get_param_query(const char *param, const char **next, const zbx_api_class_t *objclass,
		zbx_api_query_t *value, char **error)
{
	char				*data = NULL;
	int				ret = FAIL, i;
	const zbx_api_property_t	*prop;

	if ('[' == **next)
	{
		zbx_vector_str_t	outfields;

		zbx_vector_str_create(&outfields);

		if (SUCCEED == (ret = zbx_api_get_param_array(param, next, &outfields, error)))
		{
			for (i = 0; i < outfields.values_num; i++)
			{
				if (NULL == (prop = zbx_api_class_get_property(objclass, outfields.values[i])))
				{
					*error = zbx_dsprintf(*error, "invalid parameter \"%s\" query field \"%s\"",
							param, outfields.values[i]);
					ret = FAIL;

					break;
				}
				zbx_vector_ptr_append(&value->properties, (void *)prop);
			}

			if (SUCCEED != ret)
				zbx_vector_ptr_clear(&value->properties);
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
			for (prop = objclass->properties; NULL != prop->name; prop++)
				zbx_vector_ptr_append(&value->properties, (void *)prop);
		}
		else if (0 == strcmp(data, ZBX_API_PARAM_QUERY_COUNT))
		{
			value->key = 0;
		}
		else
		{
			*error = zbx_dsprintf(*error, "invalid parameter \"%s\" value \"%s\"", param, data);
			goto out;
		}

		ret = SUCCEED;
	}

	value->is_set = ZBX_API_TRUE;
	value->properties_num = value->properties.values_num;
out:
	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_param_stringarray                                    *
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
 *           single item containing this string is returned.                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_param_stringarray(const char *param, const char **next, zbx_vector_str_t *value, char **error)
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
 * Purpose: gets id list parameter (sorted)                                   *
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

	if (FAIL == zbx_api_get_param_stringarray(param, next, &ids, error))
		goto out;

	for (i = 0; i < ids.values_num; i++)
	{
		if (SUCCEED != is_uint64(ids.values[i], &id))
		{
			*error = zbx_dsprintf(*error, "invalid parameter \"%s\" value \"%s\"", param, ids.values[i]);
			goto out;
		}

		zbx_vector_uint64_append(value, id);
	}

	zbx_vector_uint64_sort(value, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* check for duplicated identifiers */
	for (i = 0; i < value->values_num - 1; i++)
	{
		if (value->values[i] == value->values[i + 1])
		{
			*error = zbx_dsprintf(*error, "duplicated parameter \"%s\" value \"" ZBX_FS_UI64 "\"", param,
					value->values[i]);
			goto out;
		}
	}

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
		zbx_vector_uint64_clear(value);

	zbx_vector_str_clear_ext(&ids, zbx_ptr_free);
	zbx_vector_str_destroy(&ids);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_object_get_property_value                                *
 *                                                                            *
 * Purpose: converts object property value to native format and allocates     *
 *          property value structure to store it                              *
 *                                                                            *
 * Parameters: objclass  - [IN] the object class definition                   *
 *             name      - [IN] the property name                             *
 *             value     - [IN] the property value in text format             *
 *             propvalue - [OUT] property value data                          *
 *             error     - [OUT] the error message                            *
 *                                                                            *
 * Return value: SUCCEED - the property value was retrieved successfully      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_object_get_property_value(const zbx_api_class_t *objclass, const char *name, const char *value,
		zbx_api_property_value_t **propvalue, char **error)
{
	const zbx_api_property_t	*prop;
	zbx_db_value_t			dbvalue;

	if (NULL == (prop = zbx_api_class_get_property(objclass, name)))
	{
		*error = zbx_dsprintf(*error, "invalid object \"%s\" property name \"%s\"", objclass->name, name);
		return FAIL;
	}

	if (SUCCEED != zbx_api_property_from_string(prop, value, &dbvalue, error))
		return FAIL;

	*propvalue = zbx_malloc(NULL, sizeof(zbx_api_property_value_t));
	(*propvalue)->property = prop;
	(*propvalue)->value = dbvalue;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_param_object                                         *
 *                                                                            *
 * Purpose: gets object parameter                                             *
 *                                                                            *
 * Parameters: param    - [IN] the parameter name                             *
 *             next     - [IN/OUT] the next character in json data buffer     *
 *             objclass - [IN] the object class definition                    *
 *             value    - [OUT] the parsed value                              *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 * Comments: The object parameter is stored as a vector of property values    *
 *           (see zbx_api_property_value_t structure).                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_param_object(const char *param, const char **next, const zbx_api_class_t *objclass,
		zbx_vector_ptr_t *value, char **error)
{
	int			ret = FAIL, i;
	zbx_vector_ptr_pair_t	propmap;

	zbx_vector_ptr_pair_create(&propmap);

	if (SUCCEED != zbx_api_get_param_map(param, next, &propmap, error))
		goto out;

	/* replace property names in (<name>, <value>) ptr pair vector with */
	/* corresponding references to object properties                    */
	for (i = 0; i < propmap.values_num; i++)
	{
		zbx_ptr_pair_t			*pair = &propmap.values[i];
		zbx_api_property_value_t	*propvalue;

		if (SUCCEED != zbx_api_object_get_property_value(objclass, (char *)pair->first, (char *)pair->second,
				&propvalue, error))
		{
			goto out;
		}

		zbx_vector_ptr_append(value, propvalue);
	}

	ret = SUCCEED;
out:
	zbx_api_vector_ptr_pair_clear(&propmap);
	zbx_vector_ptr_pair_destroy(&propmap);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_param_objectarray                                    *
 *                                                                            *
 * Purpose: gets a parameter that can be defined either a object or an array  *
 *          of objects                                                        *
 *                                                                            *
 * Parameters: param    - [IN] the parameter name                             *
 *             next     - [IN/OUT] the next character in json data buffer     *
 *             objclass - [IN] the object class definition                    *
 *             value    - [OUT] the parsed value                              *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 * Comments: The result is always returned as a vector of objects.            *
 *           If parameter was defined as an object, then a vector with one    *
 *           item containing this object is returned.                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_get_param_objectarray(const char *param, const char **next, const zbx_api_class_t *objclass,
		zbx_vector_ptr_t *value, char **error)
{
	const char		*p = NULL;
	int			ret = FAIL;
	zbx_vector_ptr_t	*properties = NULL;
	struct zbx_json_parse	jp;

	if ('[' == **next)
	{
		if (SUCCEED != zbx_json_brackets_open(*next, &jp))
		{
			*error = zbx_dsprintf(*error, "cannot open parameter \"%s\" value array", param);
			goto out;
		}

		while (NULL != (p = zbx_json_next(&jp, p)))
		{
			properties = zbx_malloc(NULL, sizeof(zbx_vector_ptr_t));
			zbx_vector_ptr_create(properties);

			if (SUCCEED != zbx_api_get_param_object(param, &p, objclass, properties, error))
				goto out;

			zbx_vector_ptr_append(value, properties);
		}

		*next = jp.end + 1;
	}
	else
	{
		properties = zbx_malloc(NULL, sizeof(zbx_vector_ptr_t));
		zbx_vector_ptr_create(properties);

		if (SUCCEED != zbx_api_get_param_object(param, next, objclass, properties, error))
			goto out;

		zbx_vector_ptr_append(value, properties);
	}

	ret = SUCCEED;
out:
	if (SUCCEED != ret && NULL != properties)
		zbx_api_object_free(properties);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_getoptions_init                                          *
 *                                                                            *
 * Purpose: initializes common get request parameters                         *
 *                                                                            *
 * Parameters: self - [OUT] the common get request parameter data             *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_getoptions_init(zbx_api_getoptions_t *self)
{
	memset(self, 0, sizeof(zbx_api_getoptions_t));

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
 * Parameters: self      - [IN/OUT] the common get request parameter data     *
 *             objclass  - [IN] the object class definition                   *
 *             parameter - [IN] the parameter name                            *
 *             next      - [IN/OUT] the next character in json data buffer    *
 *             error     - [OUT] the error message                            *
 *                                                                            *
 * Return value: SUCCEED - the parameter was parsed successfully.             *
 *               FAIL    - the parsing failed, the error message is stored    *
 *                         in error parameter.                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_getoptions_parse(zbx_api_getoptions_t *self, const zbx_api_class_t *objclass, const char *parameter,
		const char **next, char **error)
{
	int		ret = FAIL;
	unsigned char	value_flag, value_bool;

	if (0 == strcmp(parameter, zbx_api_params[ZBX_API_PARAM_OUTPUT]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_OUTPUT,
				ZBX_API_PARAM_OUTPUT_CONFLICT, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_query(zbx_api_params[ZBX_API_PARAM_OUTPUT], next, objclass,
				&self->output, error))
		{
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_params[ZBX_API_PARAM_OUTPUTCOUNT]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_OUTPUTCOUNT,
				ZBX_API_PARAM_OUTPUTCOUNT_CONFLICT, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_flag(zbx_api_params[ZBX_API_PARAM_OUTPUTCOUNT], next,
				&value_flag, error))
		{
			goto out;
		}

		if (ZBX_API_TRUE == value_flag)
			self->output.is_set = ZBX_API_TRUE;
	}
	else if (0 == strcmp(parameter, zbx_api_params[ZBX_API_PARAM_EDITABLE]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_EDITABLE,
				1 << ZBX_API_PARAM_EDITABLE, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_bool(zbx_api_params[ZBX_API_PARAM_EDITABLE], next,
				&value_bool, error))
		{
			goto out;
		}

		if (ZBX_API_TRUE == value_bool)
			self->editable = 1;
	}
	else if (0 == strcmp(parameter, zbx_api_params[ZBX_API_PARAM_LIMIT]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_LIMIT,
				ZBX_API_PARAM_LIMIT_CONFLICT, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_int(zbx_api_params[ZBX_API_PARAM_LIMIT], next, &self->limit,
				error))
		{
			goto out;
		}

		if (0 >= self->limit)
		{
			*error = zbx_dsprintf(*error, "invalid parameter \"%s\" value \"%d\"",
					zbx_api_params[ZBX_API_PARAM_LIMIT], self->limit);
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_params[ZBX_API_PARAM_PRESERVEKEYS]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_PRESERVEKEYS,
				ZBX_API_PARAM_PRESERVEKEYS_CONFLICT, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_flag(zbx_api_params[ZBX_API_PARAM_PRESERVEKEYS], next,
				&self->preservekeys, error))
		{
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_params[ZBX_API_PARAM_EXCLUDESEARCH]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_EXCLUDESEARCH,
				1 << ZBX_API_PARAM_EXCLUDESEARCH, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_flag(zbx_api_params[ZBX_API_PARAM_EXCLUDESEARCH], next,
				&value_flag, error))
		{
			goto out;
		}

		if (ZBX_API_TRUE == value_flag)
			self->filter.options |= ZBX_API_FILTER_OPTION_EXCLUDE;
	}
	else if (0 == strcmp(parameter, zbx_api_params[ZBX_API_PARAM_STARTSEARCH]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_STARTSEARCH,
				1 << ZBX_API_PARAM_STARTSEARCH, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_flag(zbx_api_params[ZBX_API_PARAM_STARTSEARCH], next,
				&value_flag, error))
		{
			goto out;
		}

		if (ZBX_API_TRUE == value_flag)
			self->filter.options |= ZBX_API_FILTER_OPTION_START;
	}
	else if (0 == strcmp(parameter, zbx_api_params[ZBX_API_PARAM_SEARCHBYANY]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_SEARCHBYANY,
				1 << ZBX_API_PARAM_SEARCHBYANY, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_bool(zbx_api_params[ZBX_API_PARAM_STARTSEARCH], next,
				&value_flag, error))
		{
			goto out;
		}

		if (ZBX_API_TRUE == value_flag)
			self->filter.options |= ZBX_API_FILTER_OPTION_ANY;
	}
	else if (0 == strcmp(parameter, zbx_api_params[ZBX_API_PARAM_SEARCHWILDCARDSENABLED]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_SEARCHWILDCARDSENABLED,
				1 << ZBX_API_PARAM_SEARCHWILDCARDSENABLED, error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_bool(zbx_api_params[ZBX_API_PARAM_SEARCHWILDCARDSENABLED],
				next, &value_flag, error))
			goto out;

		if (ZBX_API_TRUE == value_flag)
			self->filter.options |= ZBX_API_FILTER_OPTION_WILDCARD;
	}
	else if (0 == strcmp(parameter, zbx_api_params[ZBX_API_PARAM_FILTER]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_FILTER, 1 << ZBX_API_PARAM_FILTER,
				error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_filter(zbx_api_params[ZBX_API_PARAM_FILTER], next, objclass,
				ZBX_API_FALSE, &self->filter.exact, error))
		{
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_params[ZBX_API_PARAM_SEARCH]))
	{
		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_SEARCH, 1 << ZBX_API_PARAM_SEARCH,
				error))
		{
			goto out;
		}

		if (SUCCEED != zbx_api_get_param_filter(zbx_api_params[ZBX_API_PARAM_SEARCH], next, objclass,
				ZBX_API_TRUE, &self->filter.like, error))
		{
			goto out;
		}
	}
	else if (0 == strcmp(parameter, zbx_api_params[ZBX_API_PARAM_SORTFIELD]))
	{
		zbx_vector_str_t	sortfields;
		int			i, rc;

		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_SORTFIELD,
				ZBX_API_PARAM_SORTFIELD_CONFLICT, error))
		{
			goto out;
		}

		zbx_vector_str_create(&sortfields);

		if (SUCCEED == (rc = zbx_api_get_param_stringarray(zbx_api_params[ZBX_API_PARAM_SORTFIELD],
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
				*error = zbx_dsprintf(*error, "the number of parameter \"%s\" values differs from the"
						" number of parameter \"%s\" values",
						zbx_api_params[ZBX_API_PARAM_SORTFIELD],
						zbx_api_params[ZBX_API_PARAM_SORTORDER]);
				rc = FAIL;
			}

			/* copy sort fields */
			for (i = 0; i < sortfields.values_num; i++)
			{
				zbx_api_sort_t	*sort = (zbx_api_sort_t *)self->sort.values[i];

				if (NULL == (sort->field = zbx_api_class_get_property(objclass, sortfields.values[i])))
				{
					*error = zbx_dsprintf(*error, "invalid parameter \"%s\" value \"%s\"",
							zbx_api_params[ZBX_API_PARAM_SORTFIELD],
							sortfields.values[i]);

					rc = FAIL;
					break;
				}

				if (0 == (sort->field->flags & ZBX_API_PROPERTY_SORTABLE))
				{
					*error = zbx_dsprintf(*error, "invalid parameter \"%s\" value \"%s\"",
							zbx_api_params[ZBX_API_PARAM_SORTFIELD],
							sortfields.values[i]);

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
	else if (0 == strcmp(parameter, zbx_api_params[ZBX_API_PARAM_SORTORDER]))
	{
		zbx_vector_str_t	sortfields;
		int			i, rc;

		if (SUCCEED != zbx_api_getoptions_register_param(self, ZBX_API_PARAM_SORTORDER,
				ZBX_API_PARAM_SORTORDER_CONFLICT, error))
		{
			goto out;
		}

		zbx_vector_str_create(&sortfields);

		if (SUCCEED == (rc = zbx_api_get_param_stringarray(zbx_api_params[ZBX_API_PARAM_SORTORDER],
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
				*error = zbx_dsprintf(*error, "the number of parameter \"%s\" values differs from the"
						" number of parameter \"%s\" values",
						zbx_api_params[ZBX_API_PARAM_SORTFIELD],
						zbx_api_params[ZBX_API_PARAM_SORTORDER]);
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
					*error = zbx_dsprintf(*error, "invalid sort order \"%s\"",
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
 * Parameters: self     - [IN/OUT] the common get request parameter data      *
 *             objclass - [IN] the object class definition                    *
 *             name     - [IN] the field name                                 *
 *             index    - [OUT] the index of the field in output fields       *
 *                              vector                                        *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the field was found or added                       *
 *               FAIL    - no field with the specified name was found in      *
 *                         fields vector                                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_getoptions_add_output_field(zbx_api_getoptions_t *self, const zbx_api_class_t *objclass,
		const char *name, int *index, char **error)
{
	const zbx_api_property_t	*field;
	int			ret = FAIL, i;

	if (NULL == (field = zbx_api_class_get_property(objclass, name)))
	{
		*error = zbx_dsprintf(*error, "invalid additional output field name \"%s\"", name);
		goto out;
	}

	ret = SUCCEED;

	for (i = 0; i < self->output.properties.values_num; i++)
	{
		if (self->output.properties.values[i] == field)
			goto out;
	}

	zbx_vector_ptr_append(&self->output.properties, (void *)field);
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
 * Parameters: self     - [IN/OUT] the common get request parameter data      *
 *             objclass - [IN] the object class definition                    *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the request was finalized successfully.            *
 *               FAIL    - the validation failed failed.                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_getoptions_finalize(zbx_api_getoptions_t *self, const zbx_api_class_t *objclass, char **error)
{
	int				i;
	const zbx_api_property_t	*prop;

	if (SUCCEED != zbx_api_getoptions_validate_dependency(self, ZBX_API_PARAM_EXCLUDESEARCH, ZBX_API_PARAM_SEARCH,
			error))
	{
		return FAIL;
	}

	if (SUCCEED != zbx_api_getoptions_validate_dependency(self, ZBX_API_PARAM_STARTSEARCH, ZBX_API_PARAM_SEARCH,
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
		*error = zbx_dsprintf(*error, "parameter \"%s\" requires either parameter \"%s\" or parameter \"%s\""
				" to be defined", zbx_api_params[ZBX_API_PARAM_SEARCHBYANY],
				zbx_api_params[ZBX_API_PARAM_FILTER],
				zbx_api_params[ZBX_API_PARAM_SEARCH]);
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
	if (ZBX_API_TRUE != self->output.is_set)
	{
		for (prop = objclass->properties; NULL != prop->name; prop++)
			zbx_vector_ptr_append(&self->output.properties, (void *)prop);

		self->output.properties_num = self->output.properties.values_num;
		self->output.key = 0;
	}

	if (ZBX_API_TRUE == self->preservekeys)
	{
		/* If indexed output is set ensure that object id              */
		/* (first field in object class definition) is also retrieved. */
		if (SUCCEED != zbx_api_getoptions_add_output_field(self, objclass, zbx_api_object_pk(objclass)->name,
				&self->output.key, error))
		{
			return FAIL;
		}

	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_getoptions_clean                                         *
 *                                                                            *
 * Purpose: frees resources allocated to store common get request parameters  *
 *                                                                            *
 * Parameters: self - [IN/OUT] the common get request parameter data          *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_getoptions_clean(zbx_api_getoptions_t *self)
{
	zbx_vector_ptr_clear_ext(&self->sort, zbx_ptr_free);
	zbx_vector_ptr_destroy(&self->sort);

	zbx_api_filter_clean(&self->filter);
	zbx_api_query_clean(&self->output);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_property_from_string                                     *
 *                                                                            *
 * Purpose: converts property from text format to native format               *
 *                                                                            *
 * Parameters: self      - [IN] the property to convert                       *
 *             value_str - [IN] the property value in text format             *
 *             value     - [OUT] the converted value                          *
 *             error     - [OUT] the error message                            *
 *                                                                            *
 * Return value: SUCCEED - the property value was converted successfully.     *
 *               FAIL    - the value conversion failed                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_property_from_string(const zbx_api_property_t *self, const char *value_str, zbx_db_value_t *value,
		char **error)
{
	int	ret = FAIL;

	if (NULL == self->field)
	{
		/* property is not backed by database, just copy its value */
		value->str = zbx_strdup(NULL, value_str);
		return SUCCEED;
	}

	switch (self->field->type)
	{
		case ZBX_TYPE_CHAR:
		case ZBX_TYPE_TEXT:
		case ZBX_TYPE_SHORTTEXT:
		case ZBX_TYPE_LONGTEXT:
			if (strlen(value_str) > self->field->length)
			{
				if (NULL != error)
					*error = zbx_dsprintf(*error, "property \"%s\" value too long \"%s\"",
							self->name, value_str);
				break;
			}
			/* continue to value copying */
		case ZBX_TYPE_BLOB:
			value->str = zbx_strdup(NULL, value_str);
			ret = SUCCEED;
			break;
		case ZBX_TYPE_INT:
		{
			const char	*ptr = value_str;

			if ('-' == *ptr)
				ptr++;

			if (SUCCEED != is_uint31(ptr, &value->i32))
			{
				*error = zbx_dsprintf(*error, "invalid property \"%s\" integer value \"%s\"",
						self->name, value_str);
				break;
			}

			if (ptr != value_str)
				value->i32 = -value->i32;
			ret = SUCCEED;

			break;
		}
		case ZBX_TYPE_FLOAT:
			if (SUCCEED != is_double(value_str))
			{
				*error = zbx_dsprintf(*error, "invalid property \"%s\" floating value \"%s\"",
						self->name, value_str);
				break;
			}

			value->dbl = atof(value_str);
			ret = SUCCEED;
			break;
		case ZBX_TYPE_UINT:
		case ZBX_TYPE_ID:
			if (SUCCEED != is_uint64(value_str, &value->ui64))
			{
				*error = zbx_dsprintf(*error, "invalid property \"%s\" unsigned 64 bit value \"%s\"",
						self->name, value_str);
				break;
			}
			ret = SUCCEED;
			break;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_property_to_string                                       *
 *                                                                            *
 * Purpose: converts property from native format to text format               *
 *                                                                            *
 * Parameters: str        - [IN/OUT] the output string                        *
 *             str_alloc  - [IN/OUT] the allocated size of output string      *
 *             str_offset - [IN/OUT] the output string end offset             *
 *             self       - [IN] the property to convert                      *
 *             value      - [IN] the property value                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_property_to_string(char **str, size_t *str_alloc, size_t *str_offset, const zbx_api_property_t *self,
		const zbx_db_value_t *value)
{

	switch (self->field->type)
	{
		case ZBX_TYPE_CHAR:
		case ZBX_TYPE_TEXT:
		case ZBX_TYPE_SHORTTEXT:
		case ZBX_TYPE_LONGTEXT:
		case ZBX_TYPE_BLOB:
			zbx_strcpy_alloc(str, str_alloc, str_offset, value->str);
			break;
		case ZBX_TYPE_INT:
			zbx_snprintf_alloc(str, str_alloc, str_offset, "%d", value->i32);
			break;
		case ZBX_TYPE_FLOAT:
			zbx_snprintf_alloc(str, str_alloc, str_offset, ZBX_FS_DBL, value->dbl);
			break;
		case ZBX_TYPE_UINT:
		case ZBX_TYPE_ID:
			zbx_snprintf_alloc(str, str_alloc, str_offset, ZBX_FS_UI64, value->ui64);
			break;
		default:
			zbx_strcpy_alloc(str, str_alloc, str_offset, "(unknown value type)");
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_property_value_clean                                     *
 *                                                                            *
 * Purpose: frees resources allocated to store property value                 *
 *                                                                            *
 * Parameters: propvalue - [IN] the property value                            *
 *                                                                            *
 ******************************************************************************/
static void	zbx_api_property_value_clean(zbx_api_property_value_t *propvalue)
{
	switch (propvalue->property->field->type)
	{
		case ZBX_TYPE_CHAR:
		case ZBX_TYPE_TEXT:
		case ZBX_TYPE_SHORTTEXT:
		case ZBX_TYPE_LONGTEXT:
		case ZBX_TYPE_BLOB:
			zbx_free(propvalue->value.str);
			break;
	}
}


/******************************************************************************
 *                                                                            *
 * Function: zbx_api_property_value_free                                      *
 *                                                                            *
 * Purpose: frees property value                                              *
 *                                                                            *
 * Parameters: propvalue - [IN] the property value                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_property_value_free(zbx_api_property_value_t *propvalue)
{
	zbx_api_property_value_clean(propvalue);
	zbx_free(propvalue);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_property_value_compare                                   *
 *                                                                            *
 * Purpose: compares two property values                                      *
 *                                                                            *
 * Parameters: v1 - [IN] the first property value to compare                  *
 *             v2 - [IN] the second property value to compare                 *
 *                                                                            *
 * Return value: <0 : v1 < v2                                                 *
 *                0 : v1 == v2                                                *
 *               >0 : v1 > v2                                                 *
 *                                                                            *
 ******************************************************************************/
static int	zbx_api_property_value_compare(const zbx_api_property_value_t *v1, const zbx_api_property_value_t *v2)
{
	switch (v1->property->field->type)
	{
		case ZBX_TYPE_CHAR:
		case ZBX_TYPE_TEXT:
		case ZBX_TYPE_SHORTTEXT:
		case ZBX_TYPE_LONGTEXT:
		case ZBX_TYPE_BLOB:
			return strcmp(v1->value.str, v2->value.str);
		case ZBX_TYPE_INT:
			return v1->value.i32 - v2->value.i32;
		case ZBX_TYPE_FLOAT:
		{
			double	diff = v1->value.dbl - v2->value.dbl;

			if (-ZBX_DOUBLE_EPSILON < diff && diff < ZBX_DOUBLE_EPSILON)
				return 0;

			return 0 < diff ? 1 : -1;
		}
		case ZBX_TYPE_UINT:
		case ZBX_TYPE_ID:
			ZBX_RETURN_IF_NOT_EQUAL(v1->value.ui64, v2->value.ui64);
			return 0;
	}
	THIS_SHOULD_NEVER_HAPPEN;
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_object_free                                              *
 *                                                                            *
 * Purpose: frees API object                                                  *
 *                                                                            *
 * Parameters: properties - [IN] the object                                   *
 *                                                                            *
 * Comments: An API object is stored as a ptr vector of property values (see  *
 *           zbx_api_property_value_t type).                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_object_free(zbx_vector_ptr_t *object)
{
	zbx_vector_ptr_clear_ext(object, (zbx_mem_free_func_t)zbx_api_property_value_free);
	zbx_vector_ptr_destroy(object);
	zbx_free(object);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_objects_to_ids                                           *
 *                                                                            *
 * Purpose: get object identifiers from an object vector                      *
 *                                                                            *
 * Parameters: objects - [IN] the object vector                               *
 *             ids     - [OUT] the object identifiers                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_objects_to_ids(const zbx_vector_ptr_t *objects, zbx_vector_uint64_t *ids)
{
	int	i;

	for (i = 0; i < objects->values_num; i++)
	{
		const zbx_vector_ptr_t		*props = (const zbx_vector_ptr_t *)objects->values[i];
		const zbx_api_property_value_t	*propval = (const zbx_api_property_value_t *)props->values[0];

		/* the first property will always be objectid or the request would fail initialization */
		zbx_vector_uint64_append(ids, propval->value.ui64);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_class_get_property                                       *
 *                                                                            *
 * Purpose: searches for the specified property in object class definition    *
 *                                                                            *
 * Parameters: objclass - [IN] the API object class definition                *
 *             name     - [IN] the field name                                 *
 *                                                                            *
 * Return value: The found field or NULL if no field had the specified name.  *
 *                                                                            *
 ******************************************************************************/
const zbx_api_property_t	*zbx_api_class_get_property(const zbx_api_class_t *objclass, const char *name)
{
	const zbx_api_property_t	*prop;

	for (prop = objclass->properties; NULL != prop->name; prop++)
	{
		if (0 == strcmp(prop->name, name))
			return prop;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_3ptr_compare_func                                        *
 *                                                                            *
 * Purpose: helper function for object prepare functions                      *
 *          to sort property index hashset matching the property order in     *
 *          object class definition.                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_3ptr_compare_func(const void *d1, const void *d2)
{
	const void	*p1 = **(const void ***)d1;
	const void	*p2 = **(const void ***)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1, p2);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_prepare_objects_for_create                               *
 *                                                                            *
 * Purpose: prepares an object vector for create operation                    *
 *                                                                            *
 * Parameters: objects  - [IN/OUT] the objects to prepare                     *
 *             objclass - [IN] the object class definition                    *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the objects were successfully prepared for create  *
 *                         operation                                          *
 *               FAIL    - object preparation failed. Currently the only      *
 *                         reason is default value conversion failure.        *
 *                                                                            *
 * Comments: This function ensures that all objects have the same properties  *
 *           ordered by property order in object class definition. If         *
 *           necessary new properties with default values are added.          *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_prepare_objects_for_create(zbx_vector_ptr_t *objects, const zbx_api_class_t *objclass, char **error)
{
	typedef struct
	{
		const char			*name;
		const zbx_api_property_t	*property;
		int				value;
		int				required;
	}
	zbx_api_property_index_t;

	int				ret = FAIL, i, j;
	zbx_hashset_t			propindex;
	zbx_hashset_iter_t		iter;
	zbx_api_property_index_t	*pi;
	zbx_api_property_value_t	*pv1, *pv2;
	const zbx_api_property_t	*prop;

	zbx_hashset_create(&propindex, 100, ZBX_DEFAULT_STRING_HASH_FUNC, ZBX_DEFAULT_STR_COMPARE_FUNC);

	/* scan objects for used properties */
	for (i = 0; i < objects->values_num; i++)
	{
		zbx_vector_ptr_t	*props = (zbx_vector_ptr_t *)objects->values[i];

		for (j = 0; j < props->values_num; j++)
		{
			zbx_api_property_value_t	*propvalue = (zbx_api_property_value_t *)props->values[j];

			if (NULL == zbx_hashset_search(&propindex, &propvalue->property->name))
			{
				zbx_api_property_index_t	rec = {propvalue->property->name, propvalue->property};

				zbx_hashset_insert(&propindex, (void *)&rec, sizeof(zbx_api_property_index_t));
			}
		}
	}

	/* check if the required properties are defined */
	for (prop = objclass->properties; NULL != prop->name; prop++)
	{
		if (0 == (prop->flags & ZBX_API_PROPERTY_REQUIRED))
			continue;

		if (NULL == (pi = zbx_hashset_search(&propindex, &prop->name)))
		{
			*error = zbx_dsprintf(*error, "missing required property \"%s\"", prop->name);
			goto out;
		}

		pi->required = ZBX_API_TRUE;
	}

	/* normalize objects so they have the same properties defined */
	for (i = 0; i < objects->values_num; i++)
	{
		zbx_vector_ptr_t	*props = (zbx_vector_ptr_t *)objects->values[i];

		/* reset field values */
		zbx_hashset_iter_reset(&propindex, &iter);
		while (NULL != (pi = zbx_hashset_iter_next(&iter)))
			pi->value = ZBX_API_FALSE;

		/* mark object properties */
		for (j = 0; j < props->values_num; j++)
		{
			pv1 = (zbx_api_property_value_t *)props->values[j];

			if (NULL != (pi = zbx_hashset_search(&propindex, &pv1->property->name)))
				pi->value = ZBX_API_TRUE;
		}

		/* add missing properties */
		zbx_hashset_iter_reset(&propindex, &iter);
		while (NULL != (pi = zbx_hashset_iter_next(&iter)))
		{
			/* check if property is missing */
			if (ZBX_API_TRUE == pi->value)
				continue;

			/* fail if a required property is missing */
			if (ZBX_API_TRUE == pi->required)
			{
				*error = zbx_dsprintf(*error, "missing required property \"%s\"", pi->name);
				goto out;
			}

			pv1 = zbx_malloc(NULL, sizeof(zbx_api_property_value_t));
			pv1->property = pi->property;

			if (SUCCEED != zbx_api_property_from_string(pv1->property, pv1->property->field->default_value,
					&pv1->value, error))
			{
				goto out;
			}

			zbx_vector_ptr_append(props, pv1);
		}

		/* sort by property order in object class definition */
		zbx_vector_ptr_sort(props, zbx_api_3ptr_compare_func);

		/* check if object id is not specified in property values */
		pv1 = (zbx_api_property_value_t *)props->values[0];

		if (pv1->property == objclass->properties)
		{
			*error = zbx_dsprintf(*error, "cannot set object id property \"%s\"", pv1->property->name);
			goto out;
		}

		/* check for duplicate properties */
		for (j = 0; j < props->values_num - 1; j++)
		{
			pv1 = (zbx_api_property_value_t *)props->values[j];
			pv2 = (zbx_api_property_value_t *)props->values[j + 1];

			if (pv1->property == pv2->property)
			{
				*error = zbx_dsprintf(*error, "duplicate property \"%s\"" " found",
						pv1->property->name);
				goto out;
			}
		}

	}

	ret = SUCCEED;
out:
	zbx_hashset_destroy(&propindex);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_create_objects                                           *
 *                                                                            *
 * Purpose: creates objects specified by the objects vector                   *
 *                                                                            *
 * Parameters: objects  - [IN] the objects to prepare                         *
 *             objclass - [IN] the object class definition                    *
 *             outids   - [OUT] a vector of the created object ids            *
 *             error    - [OUT] error message                                 *
 *                                                                            *
 * Return value: SUCCEED - the objects were created successfully              *
 *               FAIL    - object creation failed.                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_create_objects(const zbx_vector_ptr_t *objects, const zbx_api_class_t *objclass,
		zbx_vector_uint64_t *outids, char **error)
{
	zbx_db_insert_t		db_insert;
	int			i, j, cols_num, ret;
	const ZBX_TABLE		*table;
	const ZBX_FIELD		**fields;
	zbx_vector_ptr_t	*props;
	const zbx_db_value_t	**values;
	zbx_db_value_t		idvalue;

	if (0 == objects->values_num)
		return SUCCEED;

	idvalue.ui64 = DBget_maxid_num(objclass->table_name, objects->values_num);

	table = DBget_table(objclass->table_name);

	/* reserve memory for column definitions/values */
	props = (zbx_vector_ptr_t *)objects->values[0];
	cols_num = props->values_num + 1;

	fields = zbx_malloc(NULL, sizeof(ZBX_FIELD *) * cols_num);
	values = zbx_malloc(NULL, sizeof(zbx_db_value_t *) * cols_num);

	fields[0] = zbx_api_object_pk(objclass)->field;

	for (i = 1; i < cols_num; i++)
	{
		zbx_api_property_value_t	*pv = (zbx_api_property_value_t *)props->values[i - 1];

		fields[i] = pv->property->field;
	}

	zbx_db_insert_prepare_dyn(&db_insert, table, fields, props->values_num + 1);

	values[0] = &idvalue;
	for (i = 0; i < objects->values_num; i++)
	{
		props = (zbx_vector_ptr_t *)objects->values[i];

		for (j = 1; j < cols_num; j++)
		{
			zbx_api_property_value_t	*pv = (zbx_api_property_value_t *)props->values[j - 1];

			values[j] = &pv->value;
		}

		zbx_db_insert_add_values_dyn(&db_insert, values, cols_num);
		zbx_vector_uint64_append(outids, idvalue.ui64++);
	}

	if (SUCCEED != (ret = zbx_db_insert_execute(&db_insert)))
		*error = zbx_strdup(*error, zbx_sqlerror());

	zbx_db_insert_clean(&db_insert);

	zbx_free(values);
	zbx_free(fields);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_delete_objects                                           *
 *                                                                            *
 * Purpose: deletes objects specified by the ids vector                       *
 *                                                                            *
 * Parameters: ids      - [IN] the ids of objects to delete                   *
 *             objclass - [IN] the object class definition                    *
 *             error    - [OUT] error message                                 *
 *                                                                            *
 * Return value: SUCCEED - the objects were created successfully              *
 *               FAIL    - object creation failed.                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_delete_objects(const zbx_vector_uint64_t *ids, const zbx_api_class_t *objclass, char **error)
{
	char	*sql = NULL;
	size_t	sql_offset = 0, sql_alloc = 0;
	int	ret = FAIL;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "delete from %s where ", objclass->table_name);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, zbx_api_object_pk(objclass)->field_name, ids->values,
			ids->values_num);

	if (ZBX_DB_OK > DBexecute("%s", sql))
	{
		*error = zbx_strdup(*error, zbx_sqlerror());
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_prepare_objects_for_update                               *
 *                                                                            *
 * Purpose: prepares an object vector for update operation                    *
 *                                                                            *
 * Parameters: objects  - [IN/OUT] the objects to prepare                     *
 *             objclass - [IN] the object class definition                    *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the objects were successfully prepared for update  *
 *                         operation                                          *
 *               FAIL    - object preparation failed.                         *
 *                                                                            *
 * Comments: This function checks if primary key is specified for all objects *
 *           and sorts properties to ensure that primary key is the first     *
 *           property.                                                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_prepare_objects_for_update(zbx_vector_ptr_t *objects, const zbx_api_class_t *objclass, char **error)
{
	int				ret = FAIL, i, j;
	zbx_vector_ptr_t		*props;
	zbx_api_property_value_t	*pv1, *pv2;

	for (i = 0; i < objects->values_num; i++)
	{
		props = (zbx_vector_ptr_t *)objects->values[i];

		if (0 == props->values_num)
		{
			*error = zbx_dsprintf(*error, "property \"%s\" is required for object update",
					zbx_api_object_pk(objclass)->name);
			goto out;
		}

		if (0 != props->values_num)
		{
			/* sort by property order in object class definition */
			zbx_vector_ptr_sort(props, zbx_api_3ptr_compare_func);

			pv1 = (zbx_api_property_value_t *)props->values[0];
		}
		else
			pv1 = NULL;

		/* check if the object id property is specified */
		if (NULL == pv1 || zbx_api_object_pk(objclass) != pv1->property)
		{
			*error = zbx_dsprintf(*error, "property \"%s\" is required for object update",
					zbx_api_object_pk(objclass)->name);
			goto out;
		}

		if (2 > props->values_num)
		{
			*error = zbx_strdup(*error, "not enough properties specified");
			goto out;
		}

		/* check for duplicate properties */
		for (j = 0; j < props->values_num - 1; j++)
		{
			pv1 = (zbx_api_property_value_t *)props->values[j];
			pv2 = (zbx_api_property_value_t *)props->values[j + 1];

			if (pv1->property == pv2->property)
			{
				*error = zbx_dsprintf(*error, "duplicate property \"%s\"" " found",
						pv1->property->name);
				goto out;
			}
		}
	}

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_update_objects                                           *
 *                                                                            *
 * Purpose: updates objects with properties specified by the objects vector   *
 *                                                                            *
 * Parameters: objects  - [IN] the objects to prepare                         *
 *             objclass - [IN] the object class definition                    *
 *             outids   - [OUT] a vector of the created object ids            *
 *             error    - [OUT] error message                                 *
 *                                                                            *
 * Return value: SUCCEED - the objects were created successfully              *
 *               FAIL    - object creation failed.                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_update_objects(const zbx_vector_ptr_t *objects, const zbx_api_class_t *objclass,
		zbx_vector_uint64_t *outids, char **error)
{
	char				*sql = NULL;
	size_t				sql_offset = 0, sql_alloc = 0;
	int 				ret = FAIL, i, j;
	const zbx_vector_ptr_t		*props;
	const zbx_api_property_value_t	*pv

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < objects->values_num; i++)
	{
		props = (const zbx_vector_ptr_t *)objects->values[i];

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ", objclass->table_name);

		for (j = 1; j < props->values_num; j++)
		{
			pv = (zbx_api_property_value_t *)props->values[j];

			if (1 < j)
				zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ',');

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s=", pv->property->field_name);
			zbx_api_sql_add_field_value(&sql, &sql_alloc, &sql_offset, pv->property->field, &pv->value);
		}

		pv = (zbx_api_property_value_t *)props->values[0];
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where %s=" ZBX_FS_UI64 ";\n",
				zbx_api_object_pk(objclass)->field_name, pv->value.ui64);
		zbx_vector_uint64_append(outids, pv->value.ui64);

		if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
		{
			*error = zbx_strdup(*error, zbx_sqlerror());
			goto out;
		}
	}

	if (16 < sql_offset)
	{
		DBend_multiple_update(sql, sql_alloc, sql_offset);

		if (ZBX_DB_OK > DBexecute("%s", sql))
		{
			*error = zbx_strdup(*error, zbx_sqlerror());
			goto out;
		}
	}

	ret = SUCCEED;
out:
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_check_objects_for_unique_property                        *
 *                                                                            *
 * Purpose: checks if the objects have unique values for the specified        *
 *          property                                                          *
 *                                                                            *
 * Parameters: objects  - [IN] the objects to prepare                         *
 *             objclass - [IN] the object class definition                    *
 *             propname - [IN] the property name                              *
 *             update   - [IN] ZBX_API_TRUE:  check for update uniqueness     *
 *                             ZBX_API_FALSE: check for create uniqueness     *
 *             error    - [OUT] error message                                 *
 *                                                                            *
 * Return value: SUCCEED - the property has unique values                     *
 *               FAIL    - the property has duplicated values                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_check_objects_for_unique_property(const zbx_vector_ptr_t *objects, const zbx_api_class_t *objclass,
		const char *propname, int update, char **error)
{
	typedef struct
	{
		zbx_uint64_t			id;
		const zbx_api_property_value_t	*propval;
	}
	zbx_api_idprop_t;

	DB_RESULT			result;
	DB_ROW				row;
	zbx_vector_ptr_t		values;
	const zbx_api_property_t	*property;
	const zbx_api_property_value_t	*propval, *key = NULL;
	const zbx_vector_ptr_t		*props;
	zbx_api_idprop_t		*idprop1, *idprop2;
	int				ret = FAIL, i, j;
	char				*sql = NULL;
	size_t				sql_offset = 0, sql_alloc = 0;

	if (NULL == (property = zbx_api_class_get_property(objclass, propname)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	zbx_vector_ptr_create(&values);

	/* create a list of new values and object ids */
	for (i = 0; i < objects->values_num; i++)
	{
		props = (const zbx_vector_ptr_t *)objects->values[i];

		for (j = 0; j < props->values_num; j++)
		{
			propval = (const zbx_api_property_value_t *)props->values[j];

			if (propval->property == property)
			{
				idprop1 = (zbx_api_idprop_t *)zbx_malloc(NULL, sizeof(zbx_api_idprop_t));

				idprop1->propval = propval;

				if (ZBX_API_TRUE == update)
				{
					/* the object id (primary key) is first property */
					key = (const zbx_api_property_value_t *)props->values[0];
					idprop1->id = key->value.ui64;
				}
				else
					idprop1->id = 0;

				zbx_vector_ptr_append(&values, idprop1);
				break;
			}
		}
	}

	if (0 == values.values_num)
	{
		ret = SUCCEED;
		goto out;
	}

	/* check if the new values are unique between themselves */
	for (i = 0; i < values.values_num - 1; i++)
	{
		idprop1 = (zbx_api_idprop_t *)values.values[i];

		for (j = i + 1; j < values.values_num; j++)
		{
			idprop2 = (zbx_api_idprop_t *)values.values[j];

			/* check if it is the same object being updated multiple times */
			if (ZBX_API_TRUE == update && idprop1->id == idprop2->id)
				continue;

			if (0 == zbx_api_property_value_compare(idprop1->propval, idprop2->propval))
			{
				size_t	error_alloc = 0, error_offset = 0;

				zbx_free(*error);
				zbx_snprintf_alloc(error, &error_alloc, &error_offset, "duplicate property \"%s\""
						" value \"", property->name);
				zbx_api_property_to_string(error, &error_alloc, &error_offset, property,
						&idprop1->propval->value);
				zbx_chrcpy_alloc(error, &error_alloc, &error_offset, '"');

				goto out;
			}
		}
	}

	/* Check if there are no duplicate values in database.     */

	/* First select all objects with matching property values. */
	if (ZBX_API_TRUE == update)
		zbx_vector_ptr_sort(&values, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select %s,%s from %s where %s in (",
			objclass->properties->field_name, property->field_name, objclass->table_name,
			property->field_name);

	for (i = 0; i < values.values_num; i++)
	{
		if (0 != i)
			zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ',');

		idprop1 = (zbx_api_idprop_t *)values.values[i];

		zbx_api_sql_add_field_value(&sql, &sql_alloc, &sql_offset, idprop1->propval->property->field,
				&idprop1->propval->value);
	}
	zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');

	result = DBselect("%s", sql);

	ret = SUCCEED;

	/* the check if the matching property value is not assigned to the same object */
	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t			objectid;
		zbx_api_property_value_t	value;

		ZBX_STR2UINT64(objectid, row[0]);

		/* find the object in property update list */
		if (FAIL == (i = zbx_vector_ptr_bsearch(&values, &objectid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			/* the object is not being updated, fail immediately */
			ret = FAIL;
			continue;
		}

		idprop1 = values.values[i];
		value.property = idprop1->propval->property;
		zbx_api_property_from_string(value.property, row[1], &value.value, NULL);

		/* fail if the property is not being assigned to the same object */
		if (0 != zbx_api_property_value_compare(idprop1->propval, &value))
			ret = FAIL;

		zbx_api_property_value_clean(&value);
	}

	if (SUCCEED != ret)
		*error = zbx_dsprintf(*error, "duplicate property \"%s\" value \"%s\"", property->name, row[1]);

	DBfree_result(result);
out:
	zbx_free(sql);

	zbx_vector_ptr_clear_ext(&values, zbx_ptr_free);
	zbx_vector_ptr_destroy(&values);

	return ret;
}


/******************************************************************************
 *                                                                            *
 * Function: zbx_api_check_objectids                                          *
 *                                                                            *
 * Purpose: checks if the objectids vector contains valid object identifiers  *
 *                                                                            *
 * Parameters: objectids - [IN/OUT] the object identifiers                    *
 *             objclass  - [IN] the object class definition                   *
 *             error     - [OUT] the error message                            *
 *                                                                            *
 * Return value: SUCCEED - all object identifiers are valid                   *
 *               FAIL    - otherwise                .                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_check_objectids(const zbx_vector_uint64_t *objectids, const zbx_api_class_t *objclass, char **error)
{
	int		ret = FAIL, i;
	char		*sql = NULL;
	size_t		sql_offset = 0, sql_alloc = 0;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	objectid;

	if (0 == objectids->values_num)
	{
		*error = zbx_strdup(*error, "no object identifiers specified");
		goto out;
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select %s from %s where", objclass->properties->field_name,
			objclass->table_name);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, objclass->properties->field_name, objectids->values,
			objectids->values_num);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " order by %s", objclass->properties->field_name);

	i = 0;
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(objectid, row[0]);

		if (objectids->values[i] != objectid)
			break;

		i++;
	}

	if (i != objectids->values_num)
		*error = zbx_dsprintf(*error, "invalid object identifier \"" ZBX_FS_UI64 "\"", objectids->values[i]);
	else
		ret = SUCCEED;

	DBfree_result(result);
out:
	zbx_free(sql);

	return ret;
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
 *             distinct   - [IN] if not 0 then distinct option to select      *
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
		const char *table, const char *alias, int distinct)
{
	int	i;

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "select ");

	if (ZBX_API_TRUE == distinct)
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "distinct ");

	if (0 == query->properties.values_num)
	{
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "count(*)");
	}
	else
	{
		for (i = 0; i < query->properties.values_num; i++)
		{
			const zbx_api_property_t	*prop = (const zbx_api_property_t *)query->properties.values[i];

			if (0 < i)
				zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ',');

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s.%s", alias, prop->field_name);
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
			zbx_ptr_pair_t			*pair = &filter->exact.values[i];
			const zbx_api_property_t	*prop = (const zbx_api_property_t *)pair->first;

			if (0 != i)
				zbx_strcpy_alloc(sql, sql_alloc, sql_offset, *sql_condition);

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s.%s=", alias, prop->field->name);

			switch (prop->field->type)
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
			const zbx_api_property_t	*field = (const zbx_api_property_t *)pair->first;

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
 * Function: zbx_api_sql_add_field_value                                      *
 *                                                                            *
 * Purpose: adds field value to sql statement                                 *
 *                                                                            *
 * Parameters: sql        - [IN/OUT] the sql statement                        *
 *             sql_alloc  - [IN/OUT] the allocated size of sql statement      *
 *             sql_offset - [IN/OUT] the sql statement end offset             *
 *             field      - [IN] the field data                               *
 *             value      - [IN] the value to add                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_sql_add_field_value(char **sql, size_t *sql_alloc, size_t *sql_offset, const ZBX_FIELD *field,
		const zbx_db_value_t *value)
{
	char	*str;

	switch (field->type)
	{
		case ZBX_TYPE_BLOB:
			/* TODO: handle blob stored as base64 */
			break;
		case ZBX_TYPE_CHAR:
		case ZBX_TYPE_TEXT:
		case ZBX_TYPE_SHORTTEXT:
		case ZBX_TYPE_LONGTEXT:
			str = DBdyn_escape_string_len(value->str, field->length);
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "'%s'", str);
			zbx_free(str);
			break;
		case ZBX_TYPE_INT:
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%d", value->i32);
			break;
		case ZBX_TYPE_FLOAT:
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ZBX_FS_DBL, value->dbl);
			break;
		case ZBX_TYPE_ID:
			if (0 == value->ui64)
			{
				zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "null");
				break;
			}
			/* fall through to uint64 value processing */
		case ZBX_TYPE_UINT:
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ZBX_FS_UI64, value->ui64);
			break;
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
 *                                0 for count(*) request                      *
 *             rows_num    - [IN] the maximum number of rows in result set,   *
 *                                0 for unlimited request                     *
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
		*error = zbx_strdup(*error, zbx_sqlerror());
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
 *             prop_name     - [IN] the name of query property                *
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
int	zbx_api_db_fetch_query(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *prop_name,
		const zbx_api_query_t *query, zbx_api_get_result_t *result, char **error)
{
	int			ret = FAIL, i;
	size_t			sql_offset_original = *sql_offset;
	zbx_api_query_result_t *qr;

	/* create a new query and add to the result */
	qr = (zbx_api_query_result_t *)zbx_malloc(NULL, sizeof(zbx_api_query_result_t));
	qr->name = zbx_strdup(NULL, prop_name);
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
		if (SUCCEED != zbx_api_db_fetch_rows(*sql, query->properties.values_num, 0, rows, error))
			goto out;

		/* store the fetched rows to result.queries rows vector */
		zbx_vector_ptr_append(&qr->rows, rows);

		/* restore the query template */
		*sql_offset = sql_offset_original;
	}
	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_get_result_init                                          *
 *                                                                            *
 * Purpose: initializes api get request result storage                        *
 *                                                                            *
 * Parameters: self - [OUT] the get request result storage                    *
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
 * Purpose: frees resources allocated by get request result storage           *
 *                                                                            *
 * Parameters: self - [IN/OUT] the get request result storage                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_get_result_clean(zbx_api_get_result_t *self)
{
	zbx_vector_ptr_clear_ext(&self->queries, (zbx_mem_free_func_t)zbx_api_query_result_free);
	zbx_vector_ptr_destroy(&self->queries);

	zbx_api_db_clean_rows(&self->rows);
	zbx_vector_ptr_destroy(&self->rows);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_db_clean_rows                                            *
 *                                                                            *
 * Purpose: frees resources allocated to store result set (rows)              *
 *                                                                            *
 * Parameters: self - [IN/OUT] the result set data (rows)                     *
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
 * Purpose: frees result set (rows)                                           *
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
 * Parameters: json - [OUT] the json data structure                           *
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
 * Parameters: json  - [IN/OUT] the json data structure                       *
 *             name  - [IN] the query name                                    *
 *             query - [IN] the query data                                    *
 *             rows  - [IN] the query result                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_json_add_query(struct zbx_json *json, const char *name, const zbx_api_query_t *query,
		const zbx_vector_ptr_t *rows)
{
	int	i;

	if (0 == query->properties.values_num)
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
 * Parameters: json    - [IN/OUT] the json data structure                     *
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

	for (i = 0; i < query->properties_num; i++)
	{
		const zbx_api_property_t	*prop = (const zbx_api_property_t *)query->properties.values[i];

		zbx_json_addstring(json, prop->name, columns->values[i], ZBX_JSON_TYPE_STRING);
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
 * Parameters: json - [IN/OUT] the json data structure                        *
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
 * Parameters: json    - [IN/OUT] the json data structure                     *
 *             options - [IN] the common get request options                  *
 *             result  - [IN] the request result data                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_json_add_result(struct zbx_json *json, const zbx_api_getoptions_t *options,
		const zbx_api_get_result_t *result)
{
	int	i;

	if (0 == options->output.properties.values_num)
	{
		zbx_api_json_add_count(json, ZBX_API_RESULT_TAG_RESULT, &result->rows);
		return;
	}

	if (0 == options->preservekeys || 0 == result->rows.values_num)
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
 * Function: zbx_api_json_add_idarray                                         *
 *                                                                            *
 * Purpose: id vector to json data                                            *
 *                                                                            *
 * Parameters: json - [IN/OUT] the json data structure                        *
 *             name - [IN] the vector name                                    *
 *             ids  - [IN] the vector to add                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_json_add_idarray(struct zbx_json *json, const char *name, const zbx_vector_uint64_t *ids)
{
	int	i;

	zbx_json_addobject(json, ZBX_API_RESULT_TAG_RESULT);
	zbx_json_addarray(json, name);

	for (i = 0; i < ids->values_num; i++)
		zbx_json_adduint64(json, NULL, ids->values[i]);

	zbx_json_close(json);
	zbx_json_close(json);
}


/******************************************************************************
 *                                                                            *
 * Function: zbx_api_json_add_error                                           *
 *                                                                            *
 * Purpose: adds error response to json data                                  *
 *                                                                            *
 * Parameters: json   - [IN/OUT] the json data structure                      *
 *             prefox - [IN] the error message prefix, optional               *
 *             error  - [IN] the error message                                *
 *                                                                            *
 * Comments: The error message has the following format:                      *
 *                [<prefix>: ]<error>                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_api_json_add_error(struct zbx_json *json, const char *prefix, const char *error)
{
	const char	*perror = NULL == error ? "Unknown error" : error;
	char		*msg = NULL;
	size_t		msg_alloc = 0, msg_offset = 0;

	zbx_json_addobject(json, ZBX_API_RESULT_TAG_ERROR);
	zbx_json_addint(json, ZBX_API_RESULT_TAG_ERRCODE, -32602);
	zbx_json_addstring(json, ZBX_API_RESULT_TAG_ERRMESSAGE, "Invalid params.", ZBX_JSON_TYPE_STRING);

	if (NULL != prefix)
	{
		zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset, "%s: %s", prefix, perror);
		perror = msg;
	}

	zbx_json_addstring(json, ZBX_API_RESULT_TAG_ERRDATA, perror, ZBX_JSON_TYPE_STRING);

	zbx_json_close(json);

	zbx_free(msg);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_api_init                                                     *
 *                                                                            *
 * Purpose: initializes API subsystem                                         *
 *                                                                            *
 * Parameters: error - [OUT] the error message                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_api_init(char **error)
{
	const char		*__function_name = "zbx_api_init";

	zbx_api_class_t*	zbx_api_objects[] = {
						&zbx_api_class_user,
						&zbx_api_class_mediatype,
						NULL
	};

	zbx_api_class_t	**object;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ", __function_name);

	for (object = zbx_api_objects; NULL != *object; object++)
	{
		if (SUCCEED != zbx_api_object_init(*object, error))
			goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
