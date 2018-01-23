/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "db.h"
#include "log.h"
#include "zbxregexp.h"
#include "zbxhttp.h"

#include "httpmacro.h"

#define REGEXP_PREFIX		"regex:"
#define REGEXP_PREFIX_SIZE	ZBX_CONST_STRLEN(REGEXP_PREFIX)

/******************************************************************************
 *                                                                            *
 * Function: httpmacro_cmp_func                                               *
 *                                                                            *
 * Purpose: compare two macros by name                                        *
 *                                                                            *
 * Parameters: d1 - [IN] the first macro                                      *
 *             d2 - [IN] the second macro                                     *
 *                                                                            *
 * Return value: <0 - the first macro name is 'less' than second              *
 *                0 - the macro names are equal                               *
 *               >0 - the first macro name is 'greater' than second           *
 *                                                                            *
 * Author: Andris Zeila                                                       *
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
 * Function: httpmacro_append_pair                                            *
 *                                                                            *
 * Purpose: appends key/value pair to the http test macro cache.              *
 *          If the value format is 'regex:<pattern>', then regular expression *
 *          match is performed against the supplied data value and specified  *
 *          pattern. The first captured group is assigned to the macro value. *
 *                                                                            *
 * Parameters: httptest - [IN/OUT] the http test data                         *
 *             pkey     - [IN] a pointer to the macro name (key) data         *
 *             nkey     - [IN] the macro name (key) size                      *
 *             pvalue   - [IN] a pointer to the macro value data              *
 *             nvalue   - [IN] the value size                                 *
 *             data     - [IN] the data for regexp matching (optional)        *
 *             err_str  - [OUT] the error message (optional)                  *
 *                                                                            *
 * Return value: SUCCEDED - the key/value pair was added successfully         *
 *                   FAIL - key/value pair adding to cache failed.            *
 *                          The failure reason can be either empty key/value, *
 *                          wrong key format or failed regular expression     *
 *                          match.                                            *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	httpmacro_append_pair(zbx_httptest_t *httptest, const char *pkey, size_t nkey,
			const char *pvalue, size_t nvalue, const char *data, char **err_str)
{
	const char	*__function_name = "httpmacro_append_pair";
	char 		*value_str = NULL;
	size_t		key_size = 0, key_offset = 0, value_size = 0, value_offset = 0;
	zbx_ptr_pair_t	pair = {NULL, NULL};
	int		index, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() pkey:'%.*s' pvalue:'%.*s'",
			__function_name, (int)nkey, pkey, (int)nvalue, pvalue);

	if (0 == nkey && 0 != nvalue)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() missing variable name (only value provided): \"%.*s\"",
				__function_name, (int)nvalue, pvalue);

		if (NULL != err_str && NULL == *err_str)
		{
			*err_str = zbx_dsprintf(*err_str, "missing variable name (only value provided):"
					" \"%.*s\"", (int)nvalue, pvalue);
		}

		goto out;
	}

	if ('{' != pkey[0] || '}' != pkey[nkey - 1])
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() \"%.*s\" not enclosed in {}", __function_name, (int)nkey, pkey);

		if (NULL != err_str && NULL == *err_str)
			*err_str = zbx_dsprintf(*err_str, "\"%.*s\" not enclosed in {}", (int)nkey, pkey);

		goto out;
	}

	/* get macro value */
	zbx_strncpy_alloc(&value_str, &value_size, &value_offset, pvalue, nvalue);
	if (0 == strncmp(REGEXP_PREFIX, value_str, REGEXP_PREFIX_SIZE))
	{
		int	rc;
		/* The value contains regexp pattern, retrieve the first captured group or fail.  */
		/* The \@ sequence is a special construct to fail if the pattern matches but does */
		/* not contain groups to capture.                                                 */

		rc = zbx_mregexp_sub(data, value_str + REGEXP_PREFIX_SIZE, "\\@", (char **)&pair.second);
		zbx_free(value_str);

		if (SUCCEED != rc || NULL == pair.second)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot extract the value of \"%.*s\" from response",
					__function_name, (int)nkey, pkey);

			if (NULL != err_str && NULL == *err_str)
			{
				*err_str = zbx_dsprintf(*err_str, "cannot extract the value of \"%.*s\""
						" from response", (int)nkey, pkey);
			}

			goto out;
		}
	}
	else
		pair.second = value_str;

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

	zabbix_log(LOG_LEVEL_DEBUG, "append macro '%s'='%s' in cache", pair.first, pair.second);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: http_substitute_variables                                        *
 *                                                                            *
 * Purpose: substitute variables in input string with their values from http  *
 *          test config                                                       *
 *                                                                            *
 * Parameters: httptest - [IN]     the http test data                         *
 *             data     - [IN/OUT] string to substitute macros in             *
 *                                                                            *
 * Author: Alexei Vladishev, Andris Zeila                                     *
 *                                                                            *
 ******************************************************************************/
int	http_substitute_variables(const zbx_httptest_t *httptest, char **data)
{
	const char	*__function_name = "http_substitute_variables";
	char		replace_char, *substitute;
	size_t		left, right, len, offset;
	int		index, ret = SUCCEED;
	zbx_ptr_pair_t	pair = {NULL, NULL};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __function_name, *data);

	for (left = 0; '\0' != (*data)[left]; left++)
	{
		if ('{' != (*data)[left])
			continue;

		offset = ('{' == (*data)[left + 1] ? 1 : 0);

		for (right = left + 1; '\0' != (*data)[right] && '}' != (*data)[right]; right++)
			;

		if ('}' != (*data)[right])
			break;

		replace_char = (*data)[right + 1];
		(*data)[right + 1] = '\0';

		pair.first = *data + left + offset;
		index = zbx_vector_ptr_pair_search(&httptest->macros, pair, httpmacro_cmp_func);

		(*data)[right + 1] = replace_char;

		if (FAIL == index)
			continue;

		substitute = (char *)httptest->macros.values[index].second;

		if ('.' == replace_char && 1 == offset)
		{
			right += 2;
			offset = right;

			for (; '\0' != (*data)[right] && '}' != (*data)[right]; right++)
				;

			if ('}' != (*data)[right])
				break;

			len = right - offset;

			if (ZBX_CONST_STRLEN("urlencode()") == len && 0 == strncmp(*data + offset, "urlencode()", len))
			{
				/* http_variable_urlencode cannot fail (except for "out of memory") */
				/* so no check is needed */
				substitute = NULL;
				zbx_http_url_encode((char *)httptest->macros.values[index].second, &substitute);
			}
			else if (ZBX_CONST_STRLEN("urldecode()") == len &&
					0 == strncmp(*data + offset, "urldecode()", len))
			{
				/* on error substitute will remain unchanged */
				substitute = NULL;
				if (FAIL == (ret = zbx_http_url_decode((char *)httptest->macros.values[index].second,
						&substitute)))
				{
					break;
				}
			}
			else
				continue;

		}
		else
			left += offset;

		zbx_replace_string(data, left, &right, substitute);
		if (substitute != (char *)httptest->macros.values[index].second)
			zbx_free(substitute);

		left = right;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() data:'%s'", __function_name, *data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: http_process_variables                                           *
 *                                                                            *
 * Purpose: parses http test/step variable string and stores results into     *
 *          httptest macro cache.                                             *
 *          The variables are specified as {<key>}=><value> pairs             *
 *          If the value format is 'regex:<pattern>', then regular expression *
 *          match is performed against the supplied data value and specified  *
 *          pattern. The first captured group is assigned to the macro value. *
 *                                                                            *
 * Parameters: httptest  - [IN/OUT] the http test data                        *
 *             variables - [IN] the variable vector                           *
 *             data      - [IN] the data for variable regexp matching         *
 *                         (optional).                                        *
 *             err_str   - [OUT] the error message (optional)                 *
 *                                                                            *
 * Return value: SUCCEED - the variables were processed successfully          *
 *               FAIL    - the variable processing failed (regexp match       *
 *                         failed).                                           *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
int	http_process_variables(zbx_httptest_t *httptest, zbx_vector_ptr_pair_t *variables, const char *data,
		char **err_str)
{
	const char	*__function_name = "http_process_variables";
	char		*key, *value;
	int		i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() %d variables", __function_name, variables->values_num);

	for (i = 0; i < variables->values_num; i++)
	{
		key = (char *)variables->values[i].first;
		value = (char *)variables->values[i].second;
		if (FAIL == httpmacro_append_pair(httptest, key, strlen(key), value, strlen(value), data, err_str))
			goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
