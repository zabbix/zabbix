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

#include "pp_execute.h"
#include "pp_cache.h"
#include "pp_error.h"
#include "item_preproc.h"
#include "zbxpreprocbase.h"
#include "zbxprometheus.h"
#include "zbxxml.h"
#include "preproc_snmp.h"
#include "zbxvariant.h"
#include "zbxtime.h"
#include "zbxjson.h"
#include "zbxnum.h"
#include "zbxstr.h"

#ifdef HAVE_LIBXML2
#	ifndef LIBXML_THREAD_ENABLED
#		error Zabbix requires libxml2 library built with thread support.
#	endif
#endif

#define PP_VALUE_LOG_LIMIT	4096

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'multiply by' step                                        *
 *                                                                            *
 * Parameters: value_type - [IN] item value type                              *
 *             value      - [IN/OUT] input/output value                       *
 *             params     - [IN] preprocessing parameters                     *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_multiply(unsigned char value_type, zbx_variant_t *value, const char *params)
{
	char	buffer[MAX_STRING_LEN], *error = NULL, *errmsg = NULL;

	zbx_strlcpy(buffer, params, sizeof(buffer));
	zbx_trim_float(buffer);

	if (FAIL == zbx_is_double(buffer, NULL))
	{
		error = zbx_dsprintf(NULL, "a numerical value is expected or the value is out of range");
	}
	else if (SUCCEED == item_preproc_multiplier_variant(value_type, value, buffer, &errmsg))
	{
		return SUCCEED;
	}
	else
	{
		error = zbx_dsprintf(NULL, "cannot apply multiplier \"%s\" to value of type \"%s\": %s",
				params, zbx_variant_type_desc(value), errmsg);
		zbx_free(errmsg);
	}

	zbx_variant_clear(value);
	zbx_variant_set_error(value, error);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return preprocessing 'trim' step descriptions for error messages  *
 *                                                                            *
 ******************************************************************************/
static const char	*pp_trim_desc(int type)
{
	switch (type)
	{
		case ZBX_PREPROC_RTRIM:
			return "right ";
		case ZBX_PREPROC_LTRIM:
			return "left ";
		default:
			return "";
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'trim ?' step                                             *
 *                                                                            *
 * Parameters: type   - [IN] preprocessing step type - (left|right)trim       *
 *             value  - [IN/OUT]                                              *
 *             params - [IN] preprocessing parameters                         *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_trim(int type, zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL, *characters;

	if (SUCCEED == item_preproc_trim(value, type, params, &errmsg))
		return SUCCEED;

	characters = zbx_str_printable_dyn(params);
	zbx_variant_clear(value);
	zbx_variant_set_error(value, zbx_dsprintf(NULL, "cannot perform %strim of \"%s\" for value of type"
			" \"%s\": %s", 	pp_trim_desc(type), characters, zbx_variant_type_desc(value), errmsg));

	zbx_free(characters);
	zbx_free(errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'check for unsupported' step                              *
 * *                                                                          *
 * Parameters: value                - [IN/OUT]                                *
 *             params               - [IN] preprocessing parameters           *
 *             error_handler_params - [IN/OUT]                                *
 *                                                                            *
 * Result value: SUCCEED - the input value does not have an error             *
 *               FAIL    - otherwise.                                         *
 *                                                                            *
 ******************************************************************************/
static int	pp_check_not_supported_error(const zbx_variant_t *value, const char *params,
		char **error_handler_params)
{
	if (ZBX_VARIANT_ERR == value->type && SUCCEED == item_preproc_check_error_regex(value, params,
			error_handler_params))
	{
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return preprocessing 'delta' step descriptions for error messages *
 *                                                                            *
 ******************************************************************************/
static const char	*pp_delta_desc(int type)
{
	switch (type)
	{
		case ZBX_PREPROC_DELTA_VALUE:
			return "simple change";
		case ZBX_PREPROC_DELTA_SPEED:
			return "speed per second";
		default:
			return "";
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'delta ?' step                                            *
 *                                                                            *
 * Parameters: type              - [IN] preprocessing step type -             *
*                                       (change|speed)                        *
 *             value_type        - [IN] item value type                       *
 *             value             - [IN/OUT]                                   *
 *             ts                - [IN] value timestamp                       *
 *             history_value_in  - [IN] historical (previous) data            *
 *             history_value_out - [OUT] historical (next) data               *
 *             history_ts        - [IN/OUT] last value timestamp              *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_delta(int type, unsigned char value_type, zbx_variant_t *value, zbx_timespec_t ts,
		const zbx_variant_t *history_value_in, zbx_variant_t *history_value_out, zbx_timespec_t *history_ts)
{
	char	*errmsg = NULL;

	if (SUCCEED == item_preproc_delta(value_type, value, &ts, type, history_value_in, history_value_out, history_ts,
			&errmsg))
	{
		return SUCCEED;
	}

	zbx_variant_clear(value);
	zbx_variant_set_error(value, zbx_dsprintf(NULL,  "cannot calculate delta (%s) for value of type"
				" \"%s\": %s", pp_delta_desc(type), zbx_variant_type_desc(value), errmsg));
	zbx_free(errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'regsub' step                                             *
 *                                                                            *
 * Parameters: value  - [IN/OUT] input/output value                           *
 *             params - [IN] preprocessing parameters                         *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_regsub(zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL, *ptr;
	int	len;

	if (SUCCEED == item_preproc_regsub_op(value, params, &errmsg))
		return SUCCEED;

	if (NULL == (ptr = strchr(params, '\n')))
		len = (int)strlen(params);
	else
		len = (int)(ptr - params);

	zbx_variant_clear(value);
	zbx_variant_set_error(value, zbx_dsprintf(NULL, "cannot perform regular expression \"%.*s\""
			" match for value of type \"%s\": %s",
			len, params, zbx_variant_type_desc(value), errmsg));

	zbx_free(errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute jsonpath query                                            *
 *                                                                            *
 * Parameters: cache  - [IN] preprocessing cache                              *
 *             value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *             errmsg - [OUT]                                                 *
 *                                                                            *
 * Result value: SUCCEED - the query was executed successfully.               *
 *               FAIL    - otherwise.                                         *
 *                                                                            *
 ******************************************************************************/
static int	pp_excute_jsonpath_query(zbx_pp_cache_t *cache, zbx_variant_t *value, const char *params,
		char **errmsg)
{
	char	*data = NULL;

	if (NULL == cache || ZBX_PREPROC_JSONPATH != cache->type)
	{
		zbx_jsonobj_t	obj;

		if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
			return FAIL;

		if (FAIL == zbx_jsonobj_open(value->data.str, &obj))
		{
			*errmsg = zbx_strdup(*errmsg, zbx_json_strerror());
			return FAIL;
		}

		if (FAIL == zbx_jsonobj_query(&obj, params, &data))
		{
			zbx_jsonobj_clear(&obj);
			*errmsg = zbx_strdup(*errmsg, zbx_json_strerror());
			return FAIL;
		}

		zbx_jsonobj_clear(&obj);
	}
	else
	{
		zbx_pp_cache_jsonpath_t	*index;

		if (NULL != cache->error)
		{
			*errmsg = zbx_strdup(NULL, cache->error);
			return FAIL;
		}

		if (NULL == (index = (zbx_pp_cache_jsonpath_t *)cache->data))
		{
			if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
				return FAIL;

			index = (zbx_pp_cache_jsonpath_t *)zbx_malloc(NULL, sizeof(zbx_pp_cache_jsonpath_t));

			if (SUCCEED != zbx_jsonobj_open(value->data.str, &index->obj))
			{
				cache->error = zbx_strdup(NULL, zbx_json_strerror());
				*errmsg = zbx_strdup(NULL, cache->error);
				zbx_free(index);
				return FAIL;
			}

			if (NULL == (index->index = zbx_jsonpath_index_create(errmsg)))
			{
				zbx_jsonobj_clear(&index->obj);
				zbx_free(index);
				cache->type = ZBX_PREPROC_NONE;
				return FAIL;
			}

			cache->data = (void *)index;
		}

		if (FAIL == zbx_jsonobj_query_ext(&index->obj, index->index, params, &data))
		{
			*errmsg = zbx_strdup(*errmsg, zbx_json_strerror());
			return FAIL;
		}
	}

	if (NULL == data)
	{
		*errmsg = zbx_strdup(*errmsg, "no data matches the specified path");
		return FAIL;
	}

	zbx_variant_clear(value);
	zbx_variant_set_str(value, data);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'jsonpath' step                                           *
 *                                                                            *
 * Parameters: cache  - [IN] preprocessing cache                              *
 *             value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_jsonpath(zbx_pp_cache_t *cache, zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;

	if (SUCCEED == pp_excute_jsonpath_query(cache, value, params, &errmsg))
		return SUCCEED;

	zbx_variant_clear(value);
	zbx_variant_set_error(value, zbx_dsprintf(NULL, "cannot extract value from json by path \"%s\": %s", params,
			errmsg));

	zbx_free(errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return 'to dec' step descriptions for error messages              *
 *                                                                            *
 ******************************************************************************/
static const char	*pp_2dec_desc(int type)
{
	switch (type)
	{
		case ZBX_PREPROC_BOOL2DEC:
			return "boolean";
		case ZBX_PREPROC_OCT2DEC:
			return "octal";
		case ZBX_PREPROC_HEX2DEC:
			return "hexadecimal";
		default:
			return "";
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute '?2dec' step                                              *
 *                                                                            *
 * Parameters: type  - [IN] preprocessing step type -                         *
 *                          (boolean|octal|hexadecimal)2dec                   *
 *             value - [IN/OUT] input/output value                            *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_2dec(int type, zbx_variant_t *value)
{
	char	*errmsg = NULL, *value_desc;

	if (SUCCEED == item_preproc_2dec(value, type, &errmsg))
		return SUCCEED;

	value_desc = zbx_strdup(NULL, zbx_variant_value_desc(value));
	zbx_variant_clear(value);
	zbx_variant_set_error(value, zbx_dsprintf(NULL, "cannot convert value  \"%s\" from %s to decimal format: %s",
			value_desc, pp_2dec_desc(type), errmsg));

	zbx_free(value_desc);
	zbx_free(errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute xpath query                                               *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *             error  - [OUT]                                                 *
 *                                                                            *
 * Result value: SUCCEED - the query was executed successfully.               *
 *               FAIL    - otherwise.                                         *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_xpath_query(zbx_variant_t *value, const char *params, char **error)
{
	char	*errmsg = NULL;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, error))
		return FAIL;

	if (SUCCEED == zbx_query_xpath(value, params, &errmsg))
		return SUCCEED;

	*error = zbx_dsprintf(NULL, "cannot extract XML value with xpath \"%s\": %s", params, errmsg);
	zbx_free(errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'xpath' step                                              *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_xpath(zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;

	if (SUCCEED == pp_execute_xpath_query(value, params, &errmsg))
		return SUCCEED;

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'validate range' step                                     *
 *                                                                            *
 * Parameters: value_type - [IN] item value type                              *
 *             value      - [IN/OUT] value to process                         *
 *             params     - [IN] step parameters                              *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_validate_range(unsigned char value_type, zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;

	if (SUCCEED == item_preproc_validate_range(value_type, value, params, &errmsg))
		return SUCCEED;

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'validate regex' step                                     *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_validate_regex(zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;

	if (SUCCEED == item_preproc_validate_regex(value, params, &errmsg))
		return SUCCEED;

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'validate not regex' step                                 *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_validate_not_regex(zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;

	if (SUCCEED == item_preproc_validate_not_regex(value, params, &errmsg))
		return SUCCEED;

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'error from json' step                                    *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_error_from_json(zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;
	int	ret;

	ret = item_preproc_get_error_from_json(value, params, &errmsg);

	if (NULL != errmsg)
	{
		zbx_variant_clear(value);
		zbx_variant_set_error(value, errmsg);
		ret = FAIL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'error from xml' step                                     *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_error_from_xml(zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;
	int	ret;

	ret = item_preproc_get_error_from_xml(value, params, &errmsg);

	if (NULL != errmsg)
	{
		zbx_variant_clear(value);
		zbx_variant_set_error(value, errmsg);
		ret = FAIL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'error from regex' step                                   *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_error_from_regex(zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;
	int	ret;

	ret = item_preproc_get_error_from_regex(value, params, &errmsg);

	if (NULL != errmsg)
	{
		zbx_variant_clear(value);
		zbx_variant_set_error(value, errmsg);
		ret = FAIL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'throttle timed value' step                               *
 *                                                                            *
 * Parameters: value         - [IN/OUT] input/output value                    *
 *             ts            - [IN] value timestamp                           *
 *             params        - [IN] step parameters                           *
 *             history_value_in  - [IN] historical (previous) data            *
 *             history_value_out - [OUT] historical (next) data               *
 *             history_ts    - [IN/OUT] last value timestamp                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_throttle_timed_value(zbx_variant_t *value, zbx_timespec_t ts, const char *params,
		const zbx_variant_t *history_value_in, zbx_variant_t *history_value_out, zbx_timespec_t *history_ts)
{
	char	*errmsg = NULL;

	if (SUCCEED == item_preproc_throttle_timed_value(value, &ts, params, history_value_in, history_value_out,
			history_ts, &errmsg))
	{
		return SUCCEED;
	}

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'script' step                                             *
 *                                                                            *
 * Parameters: ctx               - [IN] worker specific execution context     *
 *             value             - [IN/OUT] input/output value                *
 *             params            - [IN] step parameters                       *
 *             history_value_in  - [IN] historical (previous) bytecode        *
 *             history_value_out - [OUT] historical (next) bytecode           *
 *             config_source_ip  - [IN]                                       *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_script(zbx_pp_context_t *ctx, zbx_variant_t *value, const char *params,
		const zbx_variant_t *history_value_in, zbx_variant_t *history_value_out, const char *config_source_ip)
{
	char	*errmsg = NULL;

	if (SUCCEED == item_preproc_script(pp_context_es_engine(ctx), value, params, history_value_in,
			history_value_out, config_source_ip, &errmsg))
	{
		return SUCCEED;
	}

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute prometheus pattern query                                  *
 *                                                                            *
 * Parameters: cache  - [IN] preprocessing cache                              *
 *             value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *             errmsg - [OUT]                                                 *
 *                                                                            *
 * Return value: SUCCEED - the query was performed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_prometheus_query(zbx_pp_cache_t *cache, zbx_variant_t *value, const char *params,
		char **errmsg)
{
	char	*pattern, *request, *output, *value_out = NULL, *err = NULL;
	int	ret = FAIL;

	pattern = zbx_strdup(NULL, params);

	if (NULL == (request = strchr(pattern, '\n')))
	{
		*errmsg = zbx_strdup(*errmsg, "cannot find second parameter");
		goto out;
	}
	*request++ = '\0';

	if (NULL == (output = strchr(request, '\n')))
	{
		*errmsg = zbx_strdup(*errmsg, "cannot find third parameter");
		goto out;
	}
	*output++ = '\0';

	if (NULL == cache || ZBX_PREPROC_PROMETHEUS_PATTERN != cache->type)
	{
		if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
			goto out;

		ret = zbx_prometheus_pattern(value->data.str, pattern, request, output, &value_out, &err);
	}
	else
	{
		zbx_prometheus_t	*prom_cache;

		if (NULL != cache->error)
		{
			err = zbx_strdup(NULL, cache->error);
			goto out;
		}

		if (NULL == (prom_cache = (zbx_prometheus_t *)cache->data))
		{
			if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
				goto out;

			prom_cache = (zbx_prometheus_t *)zbx_malloc(NULL, sizeof(zbx_prometheus_t));

			if (SUCCEED != zbx_prometheus_init(prom_cache, value->data.str, &cache->error))
			{
				zbx_free(prom_cache);
				err = zbx_strdup(NULL, cache->error);
				goto out;
			}

			cache->data = (void *)prom_cache;
		}

		ret = zbx_prometheus_pattern_ex(prom_cache, pattern, request, output, &value_out, &err);
	}

	zbx_variant_clear(value);
	zbx_variant_set_str(value, value_out);
out:
	zbx_free(pattern);

	if (FAIL == ret)
	{
		if (NULL == *errmsg)
			*errmsg = zbx_dsprintf(*errmsg, "cannot apply Prometheus pattern: %s", err);

		zbx_free(err);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'prometheus pattern' step                                 *
 *                                                                            *
 * Parameters: cache  - [IN] preprocessing cache                              *
 *             value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_prometheus_pattern(zbx_pp_cache_t *cache, zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;

	if (SUCCEED == pp_execute_prometheus_query(cache, value, params, &errmsg))
		return SUCCEED;

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'prometheus to json' conversion                           *
 *                                                                            *
 * Parameters: cache  - [IN] preprocessing cache                              *
 *             value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise.                                         *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_prometheus_to_json_conversion(zbx_pp_cache_t *cache, zbx_variant_t *value,
		const char *params, char **errmsg)
{
	char	*value_out = NULL, *err = NULL;
	int	ret = FAIL;

	if (NULL == cache || ZBX_PREPROC_PROMETHEUS_PATTERN != cache->type)
	{
		if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
			goto out;

		ret = zbx_prometheus_to_json(value->data.str, params, &value_out, &err);
	}
	else
	{
		zbx_prometheus_t	*prom_cache;

		if (NULL != cache->error)
		{
			err = zbx_strdup(NULL, cache->error);
			goto out;
		}

		if (NULL == (prom_cache = (zbx_prometheus_t *)cache->data))
		{
			if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
				goto out;

			prom_cache = (zbx_prometheus_t *)zbx_malloc(NULL, sizeof(zbx_prometheus_t));

			if (SUCCEED != zbx_prometheus_init(prom_cache, value->data.str, &cache->error))
			{
				zbx_free(prom_cache);
				err = zbx_strdup(NULL, cache->error);
				goto out;
			}

			cache->data = (void *)prom_cache;
		}

		ret = zbx_prometheus_to_json_ex(prom_cache, params, &value_out, &err);
	}

	zbx_variant_clear(value);
	zbx_variant_set_str(value, value_out);
out:
	if (FAIL == ret)
	{
		if (NULL == *errmsg)
			*errmsg = zbx_dsprintf(*errmsg, "cannot convert Prometheus data to JSON: %s", err);

		zbx_free(err);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'prometheus to json' step                                 *
 *                                                                            *
 * Parameters: cache  - [IN] preprocessing cache                              *
 *             value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_prometheus_to_json(zbx_pp_cache_t *cache, zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;

	if (SUCCEED == pp_execute_prometheus_to_json_conversion(cache, value, params, &errmsg))
		return SUCCEED;

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'csv to json' step                                        *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_csv_to_json(zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;

	if (SUCCEED == item_preproc_csv_to_json(value, params, &errmsg))
		return SUCCEED;

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'xml to json' step                                        *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_xml_to_json(zbx_variant_t *value)
{
	char	*errmsg = NULL;

	if (SUCCEED == item_preproc_xml_to_json(value, &errmsg))
		return SUCCEED;

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'str replace' step                                        *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_str_replace(zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;

	if (SUCCEED == item_preproc_str_replace(value, params, &errmsg))
		return SUCCEED;

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'snmp to value' step                                      *
 *                                                                            *
 * Parameters: cache  - [IN/OUT] preprocessing cache                          *
 *             value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_snmp_to_value(zbx_pp_cache_t *cache, zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;

	if (SUCCEED == item_preproc_snmp_walk_to_value(cache, value, params, &errmsg))
		return SUCCEED;

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'snmp to json' step                                       *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] step parameters                                  *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_snmp_to_json(zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;

	if (SUCCEED == item_preproc_snmp_walk_to_json(value, params, &errmsg))
		return SUCCEED;

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute 'snmp to value' step                                      *
 *                                                                            *
 * Parameters:  value  - [IN/OUT] value to process                            *
 *              params - [IN] step parameters                                 *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
static int	pp_execute_snmp_get_to_value(zbx_variant_t *value, const char *params)
{
	char	*errmsg = NULL;

	if (SUCCEED == item_preproc_snmp_get_to_value(value, params, &errmsg))
		return SUCCEED;

	zbx_variant_clear(value);
	zbx_variant_set_error(value, errmsg);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute preprocessing step                                        *
 *                                                                            *
 * Parameters: ctx               - [IN] worker specific execution context     *
 *             cache             - [IN] preprocessing cache                   *
 *             um_handle         - [IN] shared user macro cache handle        *
 *             hostid            - [IN] item host identifier                  *
 *             value_type        - [IN] item value type                       *
 *             value             - [IN/OUT] input/output value                *
 *             ts                - [IN] value timestamp                       *
 *             step              - [IN/OUT] step to execute                   *
 *             history_value_in  - [IN] historical (previous) data            *
 *             history_value_out - [OUT] historical (next) data               *
 *             history_ts        - [IN/OUT] last value timestamp              *
 *             config_source_ip  - [IN]                                       *
 *                                                                            *
 * Result value: SUCCEED - the preprocessing step was executed successfully.  *
 *               FAIL    - otherwise. The error message is stored in value.   *
 *                                                                            *
 ******************************************************************************/
int	pp_execute_step(zbx_pp_context_t *ctx, zbx_pp_cache_t *cache, zbx_dc_um_shared_handle_t *um_handle,
		zbx_uint64_t hostid, unsigned char value_type, zbx_variant_t *value, zbx_timespec_t ts,
		zbx_pp_step_t *step, const zbx_variant_t *history_value_in, zbx_variant_t *history_value_out,
		zbx_timespec_t *history_ts, const char *config_source_ip)
{
	int	ret, user_macros = 0;
	char	*params = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() step:%d params:'%s' value:'%.*s' cache:%p", __func__,
			step->type, step->params, PP_VALUE_LOG_LIMIT, zbx_variant_value_desc(value), (void *)cache);

	params = zbx_strdup(NULL, step->params);

	if (NULL != um_handle)
	{
		if (NULL != strstr(params, "{$"))
		{
			char		*error = NULL;
			unsigned char	env = ZBX_PREPROC_SCRIPT == step->type ? ZBX_MACRO_ENV_SECURE :
					ZBX_MACRO_ENV_NONSECURE;

			if (SUCCEED != zbx_dc_expand_user_and_func_macros_from_cache(um_handle->um_cache, &params,
					&hostid, 1, env, &error))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot resolve user macros: %s", error);
				zbx_free(error);
			}

			user_macros = 1;
		}
	}

	switch (step->type)
	{
		case ZBX_PREPROC_MULTIPLIER:
			ret = pp_execute_multiply(value_type, value, params);
			goto out;
		case ZBX_PREPROC_RTRIM:
		case ZBX_PREPROC_LTRIM:
		case ZBX_PREPROC_TRIM:
			ret = pp_execute_trim(step->type, value, params);
			goto out;
		case ZBX_PREPROC_REGSUB:
			ret = pp_execute_regsub(value, params);
			goto out;
		case ZBX_PREPROC_BOOL2DEC:
		case ZBX_PREPROC_OCT2DEC:
		case ZBX_PREPROC_HEX2DEC:
			ret = pp_execute_2dec(step->type, value);
			goto out;
		case ZBX_PREPROC_DELTA_VALUE:
		case ZBX_PREPROC_DELTA_SPEED:
			ret = pp_execute_delta(step->type, value_type, value, ts, history_value_in,
					history_value_out, history_ts);
			goto out;
		case ZBX_PREPROC_XPATH:
			ret = pp_execute_xpath(value, params);
			goto out;
		case ZBX_PREPROC_JSONPATH:
			ret = pp_execute_jsonpath(cache, value, params);
			goto out;
		case ZBX_PREPROC_VALIDATE_RANGE:
			ret = pp_validate_range(value_type, value, params);
			goto out;
		case ZBX_PREPROC_VALIDATE_REGEX:
			ret = pp_validate_regex(value, params);
			goto out;
		case ZBX_PREPROC_VALIDATE_NOT_REGEX:
			ret = pp_validate_not_regex(value, params);
			goto out;
		case ZBX_PREPROC_VALIDATE_NOT_SUPPORTED:
			ret = pp_check_not_supported_error(value, params, &step->error_handler_params);
			goto out;
		case ZBX_PREPROC_ERROR_FIELD_JSON:
			ret = pp_error_from_json(value, params);
			goto out;
		case ZBX_PREPROC_ERROR_FIELD_XML:
			ret = pp_error_from_xml(value, params);
			goto out;
		case ZBX_PREPROC_ERROR_FIELD_REGEX:
			ret = pp_error_from_regex(value, params);
			goto out;
		case ZBX_PREPROC_THROTTLE_VALUE:
			ret = item_preproc_throttle_value(value, &ts, history_value_in, history_value_out, history_ts);
			goto out;
		case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
			ret = pp_throttle_timed_value(value, ts, params, history_value_in, history_value_out, history_ts);
			goto out;
		case ZBX_PREPROC_SCRIPT:
			ret = pp_execute_script(ctx, value, params, history_value_in, history_value_out, config_source_ip);
			/* don't cache bytecode when user macros are present */
			if (0 != user_macros)
				zbx_variant_clear(history_value_out);
			goto out;
		case ZBX_PREPROC_PROMETHEUS_PATTERN:
			ret = pp_execute_prometheus_pattern(cache, value, params);
			goto out;
		case ZBX_PREPROC_PROMETHEUS_TO_JSON:
			ret = pp_execute_prometheus_to_json(cache, value, params);
			goto out;
		case ZBX_PREPROC_CSV_TO_JSON:
			ret = pp_execute_csv_to_json(value, params);
			goto out;
		case ZBX_PREPROC_XML_TO_JSON:
			ret = pp_execute_xml_to_json(value);
			goto out;
		case ZBX_PREPROC_STR_REPLACE:
			ret = pp_execute_str_replace(value, params);
			goto out;
		case ZBX_PREPROC_SNMP_WALK_VALUE:
			ret = pp_execute_snmp_to_value(cache, value, params);
			goto out;
		case ZBX_PREPROC_SNMP_WALK_TO_JSON:
			ret = pp_execute_snmp_to_json(value, params);
			goto out;
		case ZBX_PREPROC_SNMP_GET_VALUE:
			ret = pp_execute_snmp_get_to_value(value, params);
			goto out;
		default:
			zbx_variant_clear(value);
			zbx_variant_set_error(value, zbx_dsprintf(NULL, "unknown preprocessing step"));
			ret = FAIL;
		}
out:
	zbx_free(params);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%s value:%.*s", __func__, zbx_result_string(ret),
			PP_VALUE_LOG_LIMIT, zbx_variant_value_desc(value));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute preprocessing steps                                       *
 *                                                                            *
 * Parameters: ctx              - [IN] worker specific execution context      *
 *             preproc          - [IN] item preprocessing data                *
 *             cache            - [IN] preprocessing cache                    *
 *             um_handle        - [IN] shared user macro cache handle         *
 *             value_in         - [IN]                                        *
 *             ts               - [IN] value timestamp                        *
 *             config_source_ip - [IN]                                        *
 *             value_out        - [OUT]                                       *
 *             results_out      - [OUT] results for each step (optional)      *
 *             results_num_out  - [OUT] number of results (optional)          *
 *                                                                            *
 ******************************************************************************/
void	pp_execute(zbx_pp_context_t *ctx, zbx_pp_item_preproc_t *preproc, zbx_pp_cache_t *cache,
		zbx_dc_um_shared_handle_t *um_handle, zbx_variant_t *value_in, zbx_timespec_t ts,
		const char *config_source_ip, zbx_variant_t *value_out, zbx_pp_result_t **results_out,
		int *results_num_out)
{
	zbx_pp_result_t		*results;
	zbx_pp_history_t	*history_out, *history_in;
	int			quote_error, results_num, action;
	zbx_variant_t		value_raw;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): value:%.*s type:%s", __func__, PP_VALUE_LOG_LIMIT,
			zbx_variant_value_desc(NULL == cache ? value_in : &cache->value),
			zbx_variant_type_desc(NULL == cache ? value_in : &cache->value));

	if (NULL == preproc || 0 == preproc->steps_num)
	{
		zbx_variant_copy(value_out, NULL != cache ? &cache->value : value_in);

		goto out;
	}

	if (NULL == cache)
	{
		zbx_variant_copy(value_out, value_in);
	}
	else
	{
		/* preprocessing cache is enabled only for the first step, */
		/* so prepare output value based on first step type        */
		pp_cache_prepare_output_value(cache, preproc->steps[0].type, value_out);

		/* set input value for error reporting */
		value_in = &cache->value;
	}

	results = (zbx_pp_result_t *)zbx_malloc(NULL, sizeof(zbx_pp_result_t) * (size_t)preproc->steps_num);
	if (NULL != preproc->history_cache)
	{
		history_out = zbx_pp_history_create(preproc->history_num);
		history_in = zbx_pp_history_cache_history_acquire(preproc->history_cache);
	}
	else
	{
		history_out = NULL;
		history_in = NULL;
	}

	results_num = 0;

	zbx_variant_set_none(&value_raw);

	for (int i = 0; i < preproc->steps_num; i++)
	{
		zbx_variant_t		history_value_out, history_none;
		const zbx_variant_t	*history_value_in;
		zbx_timespec_t		history_ts;

		if (ZBX_VARIANT_ERR == value_out->type && ZBX_PREPROC_VALIDATE_NOT_SUPPORTED != preproc->steps[i].type)
			break;

		action = ZBX_PREPROC_FAIL_DEFAULT;
		quote_error = 0;

		zbx_variant_set_none(&history_value_out);
		zbx_pp_history_get(history_in, i, &history_value_in, &history_ts);

		if (NULL == history_value_in)
		{
			zbx_variant_set_none(&history_none);
			history_value_in = &history_none;
		}

		if (SUCCEED != pp_execute_step(ctx, cache, um_handle, preproc->hostid, preproc->value_type, value_out,
				ts, preproc->steps + i, history_value_in, &history_value_out, &history_ts, config_source_ip))
		{
			zbx_variant_copy(&value_raw, value_out);

			if (ZBX_PREPROC_FAIL_DEFAULT == (action = pp_error_on_fail(um_handle, preproc->hostid, value_out,
					preproc->steps + i)))
			{
				zbx_variant_clear(&value_raw);
			}
		}
		else
		{
			if (ZBX_VARIANT_ERR == value_out->type)
				quote_error = 1;
		}

		pp_result_set(results + results_num++, value_out, action, &value_raw);

		if (NULL != history_out && ZBX_VARIANT_NONE != history_value_out.type && ZBX_VARIANT_ERR != value_out->type)
		{
			if (SUCCEED == zbx_pp_preproc_has_history(preproc->steps[i].type))
				zbx_pp_history_add(history_out, i, &history_value_out, history_ts);
		}

		zbx_variant_clear(&history_value_out);

		cache = NULL;

		if (ZBX_VARIANT_NONE == value_out->type)
			break;
	}

	if (ZBX_VARIANT_ERR == value_out->type)
	{
		/* reset preprocessing history in the case of error */
		if (NULL != history_out)
		{
			zbx_pp_history_release(history_out);
			history_out = NULL;
		}

		if (0 != results_num && ZBX_PREPROC_FAIL_SET_ERROR != action && 0 == quote_error)
		{
			char	*error = NULL;

			pp_format_error(value_in, results, results_num, &error);
			zbx_variant_clear(value_out);
			zbx_variant_set_error(value_out, error);
		}
	}

	/* replace preprocessing history */
	zbx_pp_history_cache_history_set_and_release(preproc->history_cache, history_in, history_out);

	if (NULL != results_out)
	{
		*results_out = results;
		*results_num_out = results_num;
	}
	else
		pp_free_results(results, results_num);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): value:'%.*s' type:%s", __func__, PP_VALUE_LOG_LIMIT,
			zbx_variant_value_desc(value_out), zbx_variant_type_desc(value_out));
}

void	pp_context_init(zbx_pp_context_t *ctx)
{
	memset(ctx, 0, sizeof(zbx_pp_context_t));
}

void	pp_context_destroy(zbx_pp_context_t *ctx)
{
	if (0 != ctx->es_initialized)
		zbx_es_destroy(&ctx->es_engine);
}

zbx_es_t	*pp_context_es_engine(zbx_pp_context_t *ctx)
{
	if (0 == ctx->es_initialized)
	{
		zbx_es_init(&ctx->es_engine);
		ctx->es_initialized = 1;
	}

	return &ctx->es_engine;
}
