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

#include "httpmacro.h"
#include "zbxregexp.h"
#include "zbxstr.h"
#include "zbxexpr.h"
#include "zbxvariant.h"
#include "zbxxml.h"

/******************************************************************************
 *                                                                            *
 * Purpose: compares two macros by name                                       *
 *                                                                            *
 * Parameters: d1 - [IN] first macro                                          *
 *             d2 - [IN] second macro                                         *
 *                                                                            *
 * Return value: <0 - first macro name is 'less' than second                  *
 *                0 - macro names are equal                                   *
 *               >0 - first macro name is 'greater' than second               *
 *                                                                            *
 ******************************************************************************/
static int 	httpmacro_cmp_func(const void *d1, const void *d2)
{
	const zbx_ptr_pair_t	*pair1 = (const zbx_ptr_pair_t *)d1;
	const zbx_ptr_pair_t	*pair2 = (const zbx_ptr_pair_t *)d2;

	return strcmp((char *)pair1->first, (char *)pair2->first);
}

/******************************************************************************
 *                                                                            *
 * Purpose: find macros                                                       *
 *                                                                            *
 * Parameters: pmacro - [IN] macro values                                     *
 *             key    - [IN] searching value data                             *
 *             loc    - [IN] searching value location in key                  *
 *                                                                            *
 * Return value: index in pmacro                                              *
 *                   FAIL - not found                                         *
 *                                                                            *
 ******************************************************************************/
static int	zbx_macro_variable_search(const zbx_vector_ptr_pair_t *pmacro, const char *key, const zbx_strloc_t loc)
{
	for (int i = 0; i < pmacro->values_num; i++)
	{
		if (SUCCEED == zbx_strloc_cmp(key, &loc, pmacro->values[i].first, strlen(pmacro->values[i].first)))
			return i;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Appends key/value pair to the HTTP test macro cache.              *
 *          If the value format is 'regex:<pattern>', then regular expression *
 *          match is performed against the supplied data value and specified  *
 *          pattern. The first captured group is assigned to the macro value. *
 *                                                                            *
 * Parameters: httptest - [IN/OUT] HTTP test data                             *
 *             pkey     - [IN] pointer to macro name (key) data               *
 *             nkey     - [IN] macro name (key) size                          *
 *             pvalue   - [IN] pointer to macro value data                    *
 *             nvalue   - [IN] value size                                     *
 *             data     - [IN] data for regexp matching (optional)            *
 *             err_str  - [OUT] error message (optional)                      *
 *                                                                            *
 * Return value:  SUCCEED - key/value pair was added successfully             *
 *                   FAIL - key/value pair adding to cache failed             *
 *                          The failure reason can be either empty key/value, *
 *                          wrong key format or failed regular expression     *
 *                          match.                                            *
 *                                                                            *
 ******************************************************************************/
static int	httpmacro_append_pair(zbx_httptest_t *httptest, const char *pkey, size_t nkey,
			const char *pvalue, size_t nvalue, const char *data, char **err_str)
{
#define REGEXP_PREFIX		"regex:"
#define REGEXP_PREFIX_SIZE	ZBX_CONST_STRLEN(REGEXP_PREFIX)
#define JSONPATH_PREFIX		"jsonpath:"
#define JSONPATH_PREFIX_SIZE	ZBX_CONST_STRLEN(JSONPATH_PREFIX)
#define XMLXPATH_PREFIX		"xmlxpath:"
#define XMLXPATH_PREFIX_SIZE	ZBX_CONST_STRLEN(XMLXPATH_PREFIX)
	char 		*value_str = NULL, *errmsg = NULL;
	size_t		key_size = 0, key_offset = 0, value_size = 0, value_offset = 0;
	zbx_ptr_pair_t	pair = {NULL, NULL};
	int		index, ret = FAIL, rc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() pkey:'%.*s' pvalue:'%.*s'", __func__, (int)nkey, pkey, (int)nvalue,
			pvalue);

	if (0 == nkey && 0 == nvalue)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() missing variable name and value", __func__);

		if (NULL != err_str && NULL == *err_str)
		{
			*err_str = zbx_dsprintf(*err_str, "missing variable name and value");
		}

		goto out;
	}

	if (0 == nkey)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() missing variable name (only value provided): \"%.*s\"",
				__func__, (int)nvalue, pvalue);

		if (NULL != err_str && NULL == *err_str)
		{
			*err_str = zbx_dsprintf(*err_str, "missing variable name (only value provided):"
					" \"%.*s\"", (int)nvalue, pvalue);
		}

		goto out;
	}

	if ('{' != pkey[0] || '}' != pkey[nkey - 1])
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() \"%.*s\" not enclosed in {}", __func__, (int)nkey, pkey);

		if (NULL != err_str && NULL == *err_str)
			*err_str = zbx_dsprintf(*err_str, "\"%.*s\" not enclosed in {}", (int)nkey, pkey);

		goto out;
	}

	/* get macro value */
	zbx_strncpy_alloc(&value_str, &value_size, &value_offset, pvalue, nvalue);
	if (0 == strncmp(REGEXP_PREFIX, value_str, REGEXP_PREFIX_SIZE))
	{
		/* The value contains regexp pattern, retrieve the first captured group or fail. */
		zbx_mregexp_sub(data, value_str + REGEXP_PREFIX_SIZE, "\\1", ZBX_REGEXP_GROUP_CHECK_ENABLE,
				(char **)&pair.second);
		zbx_free(value_str);
	}
	else if (0 == strncmp(JSONPATH_PREFIX, value_str, JSONPATH_PREFIX_SIZE))
	{
		zbx_jsonobj_t	obj;

		if (SUCCEED == (rc = zbx_jsonobj_open(data, &obj)))
			rc = zbx_jsonobj_query(&obj, value_str + JSONPATH_PREFIX_SIZE, (char **)&pair.second);

		if (SUCCEED != rc)
		{
			errmsg = zbx_strdup(NULL, zbx_json_strerror());
			zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot parse json: %s", __func__, errmsg);
			zbx_free(pair.second);
		}

		zbx_jsonobj_clear(&obj);
		zbx_free(value_str);
	}
	else if (0 == strncmp(XMLXPATH_PREFIX, value_str, XMLXPATH_PREFIX_SIZE))
	{
		zbx_variant_t	value;
		int		is_empty;

		zbx_variant_set_str(&value, zbx_strdup(NULL, data));
		rc = zbx_query_xpath_contents(&value, value_str + XMLXPATH_PREFIX_SIZE, &is_empty, &errmsg);
		if (SUCCEED == rc && SUCCEED != is_empty)
		{
			pair.second = zbx_strdup(NULL, value.data.str);
		}
		else
		{
			if (NULL != errmsg)
				zabbix_log(LOG_LEVEL_DEBUG, "%s() %s", __func__, errmsg);
			else
				zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot parse xml", __func__);
		}
		zbx_free(value_str);
		zbx_variant_clear(&value);
	}
	else
		pair.second = value_str;

	if (NULL == pair.second)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot extract the value of \"%.*s\" from response",
				__func__, (int)nkey, pkey);

		if (NULL != err_str && NULL == *err_str)
		{
			if (NULL != errmsg)
			{
				*err_str = zbx_dsprintf(*err_str, "cannot extract the value of \"%.*s\""
						" from response\n%s", (int)nkey, pkey, errmsg);
			}
			else
			{
				*err_str = zbx_dsprintf(*err_str, "cannot extract the value of \"%.*s\""
						" from response", (int)nkey, pkey);
			}
		}
		goto out;
	}

	/* get macro name */
	zbx_strncpy_alloc((char **)&pair.first, &key_size, &key_offset, pkey, nkey);

	/* remove existing macro if necessary */
	index = zbx_vector_ptr_pair_search(&httptest->macros, pair, httpmacro_cmp_func);
	if (FAIL != index)
	{
		zbx_ptr_pair_t	*ppair = &httptest->macros.values[index];

		zbx_free(ppair->first);
		zbx_free(ppair->second);
		zbx_vector_ptr_pair_remove_noorder(&httptest->macros, index);
	}
	zbx_vector_ptr_pair_append(&httptest->macros, pair);

	zabbix_log(LOG_LEVEL_DEBUG, "append macro '%s'='%s' in cache", (char *)pair.first, (char *)pair.second);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
	zbx_free(errmsg);

	return ret;
#undef REGEXP_PREFIX
#undef REGEXP_PREFIX_SIZE
#undef JSONPATH_PREFIX
#undef JSONPATH_PREFIX_SIZE
#undef XMLXPATH_PREFIX
#undef XMLXPATH_PREFIX_SIZE
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitutes variables in input string with their values from HTTP *
 *          test config                                                       *
 *                                                                            *
 * Parameters: httptest - [IN] HTTP test data                                 *
 *             data     - [IN/OUT] string to substitute macros in             *
 *                                                                            *
 ******************************************************************************/
int	http_substitute_variables(const zbx_httptest_t *httptest, char **data)
{
#define ZBX_MACRO_UNKNOWN	"*UNKNOWN*"
	int		index, ret = SUCCEED;
	size_t		pos = 0;
	zbx_token_t	token;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __func__, *data);
	for (; SUCCEED == zbx_token_find(*data, (int)pos, &token, ZBX_TOKEN_SEARCH_VAR_MACRO); pos++)
	{
		if (ZBX_TOKEN_VAR_FUNC_MACRO == token.type)
		{
			char	*substitute;

			index = zbx_macro_variable_search(&httptest->macros, *data, token.data.var_func_macro.macro);
			if (FAIL == index)
				continue;
			substitute = zbx_strdup(NULL, httptest->macros.values[index].second);
			if (SUCCEED != zbx_calculate_macro_function(*data, &token.data.var_func_macro,
					&substitute))
			{
				zbx_replace_string(data, token.loc.l, &token.loc.r, ZBX_MACRO_UNKNOWN);
				ret = FAIL;
			}
			else
			{
				zbx_replace_string(data, token.loc.l, &token.loc.r, substitute);
			}
			zbx_free(substitute);
			pos = token.loc.r;
		}
		else if (ZBX_TOKEN_VAR_MACRO == token.type)
		{
			index = zbx_macro_variable_search(&httptest->macros, *data, token.loc);
			if (FAIL == index)
				continue;

			zbx_replace_string(data, token.loc.l, &token.loc.r,
					(char *)httptest->macros.values[index].second);
			pos = token.loc.r;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() data:'%s'", __func__, *data);

	return ret;
#undef ZBX_MACRO_UNKNOWN
}

/******************************************************************************
 *                                                                            *
 * Purpose: Parses HTTP test/step variable string and stores results into     *
 *          httptest macro cache.                                             *
 *          The variables are specified as {<key>}=><value> pairs             *
 *          If the value format is 'regex:<pattern>', then regular expression *
 *          match is performed against the supplied data value and specified  *
 *          pattern. The first captured group is assigned to the macro value. *
 *                                                                            *
 * Parameters: httptest  - [IN/OUT] HTTP test data                            *
 *             variables - [IN] variable vector                               *
 *             data      - [IN] data for variable regexp matching (optional)  *
 *             err_str   - [OUT] error message (optional)                     *
 *                                                                            *
 * Return value: SUCCEED - variables were processed successfully              *
 *               FAIL    - variable processing failed (regexp match failed)   *
 *                                                                            *
 ******************************************************************************/
int	http_process_variables(zbx_httptest_t *httptest, zbx_vector_ptr_pair_t *variables, const char *data,
		char **err_str)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() %d variables", __func__, variables->values_num);

	for (int i = 0; i < variables->values_num; i++)
	{
		char	*key = (char *)variables->values[i].first, *value = (char *)variables->values[i].second;

		if (FAIL == httpmacro_append_pair(httptest, key, strlen(key), value, strlen(value), data, err_str))
			goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
