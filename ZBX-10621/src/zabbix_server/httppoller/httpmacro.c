/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

	if (0 == nkey || 0 == nvalue)
	{
		if (0 == nkey && 0 != nvalue)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() missing variable name (only value provided): \"%.*s\"",
					__function_name, (int)nvalue, pvalue);

			if (NULL != err_str && NULL == *err_str)
			{
				*err_str = zbx_dsprintf(*err_str, "missing variable name (only value provided):"
						" \"%.*s\"", (int)nvalue, pvalue);
			}
		}
		else if (0 == nvalue && 0 != nkey)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() missing variable value (only name provided): \"%.*s\"",
					__function_name, (int)nkey, pkey);

			if (NULL != err_str && NULL == *err_str)
			{
				*err_str = zbx_dsprintf(*err_str, "missing variable value (only name provided):"
						" \"%.*s\"", (int)nkey, pkey);
			}
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
		/* The value contains regexp pattern, retrieve the first captured group or fail.  */
		/* The \@ sequence is a special construct to fail if the pattern matches but does */
		/* not contain groups to capture.                                                 */
		if (NULL != data)
			pair.second = (void *)zbx_mregexp_sub(data, value_str + REGEXP_PREFIX_SIZE, "\\@");

		zbx_free(value_str);

		if (NULL == data)
		{
			/* Ignore regex variables when no input data is specified. For example,   */
			/* scenario level regex variables don't have input data before the first  */
			/* web scenario step is processed.                                        */
			ret = SUCCEED;
			goto out;
		}

		if (NULL == pair.second)
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
	zbx_strncpy_alloc((char**)&pair.first, &key_size, &key_offset, pkey, nkey);

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

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s macro:'%s'='%s'",
			__function_name, zbx_result_string(ret), (char*)pair.first, (char*)pair.second);

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
void	http_substitute_variables(zbx_httptest_t *httptest, char **data)
{
	const char	*__function_name = "http_substitute_variables";
	char		replace_char;
	size_t		left, right;
	int		index;
	zbx_ptr_pair_t	pair;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __function_name, *data);

	for (left = 0; '\0' != (*data)[left]; left++)
	{
		if ('{' != (*data)[left])
			continue;

		for (right = left + 1; '\0' != (*data)[right] && '}' != (*data)[right]; right++)
			;

		if ('}' != (*data)[right])
			break;

		replace_char = (*data)[right + 1];
		(*data)[right + 1] = '\0';

		pair.first = *data + left;
		index = zbx_vector_ptr_pair_search(&httptest->macros, pair, httpmacro_cmp_func);

		(*data)[right + 1] = replace_char;

		if (FAIL == index)
			continue;

		zbx_replace_string(data, left, &right, (char*)httptest->macros.values[index].second);

		left = right;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() data:'%s'", __function_name, *data);
}

#define TRIM_LEADING_WHITESPACE(ptr)	while (' ' == *ptr || '\t' == *ptr) ptr++;
#define TRIM_TRAILING_WHITESPACE(ptr)	do { ptr--; } while (' ' == *ptr || '\t' == *ptr);

/******************************************************************************
 *                                                                            *
 * Function: http_process_variables                                           *
 *                                                                            *
 * Purpose: parses http test/step variable string and stores results into     *
 *          httptest macro cache.                                             *
 *          The variables are specified in {<key>}=<value> format, one        *
 *          variable per line.                                                *
 *          If the value format is 'regex:<pattern>', then regular expression *
 *          match is performed against the supplied data value and specified  *
 *          pattern. The first captured group is assigned to the macro value. *
 *                                                                            *
 * Parameters: httptest  - [IN/OUT] the http test data                        *
 *             variables - [IN] the variable string to parse                  *
 *             data      - [IN] the data for variable regexp matching         *
 *                         (optional).                                        *
 *                                                                            *
 * Return value: SUCCEED - the variable string was processed successfully     *
 *               FAIL    - the variable string processing failed (either      *
 *                         because of bad formatting or failed regexp match). *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
int	http_process_variables(zbx_httptest_t *httptest, const char *variables, const char *data, char **err_str)
{
	const char	*__function_name = "http_process_variables";
	const char	*pkey = variables, *pvalue;
	size_t		nkey, nvalue;
	int		rc = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() variables:'%s'", __function_name, variables);

	while ('\0' != *pkey)
	{
		const char	*ptr = pkey;

		/* find end of the line */
		while (NULL == strchr("\n\r", *ptr))
			ptr++;

		/* parse line */
		pvalue = strchr(pkey, '=');
		if (NULL != pvalue && pvalue < ptr)
		{
			const char	*ptail = pvalue++;

			TRIM_LEADING_WHITESPACE(pkey);
			if (pkey != ptail)
			{
				TRIM_TRAILING_WHITESPACE(ptail);
				nkey = ptail - pkey + 1;
			}
			else
				nkey = 0;

			ptail = ptr;
			TRIM_LEADING_WHITESPACE(pvalue);
			if (pvalue != ptail)
			{
				TRIM_TRAILING_WHITESPACE(ptail);
				nvalue = ptail - pvalue + 1;
			}
			else
				nvalue = 0;

			if (FAIL == httpmacro_append_pair(httptest, pkey, nkey, pvalue, nvalue, data, err_str))
				goto out;
		}

		/* skip LF/CR symbols until the next nonempty line */
		while ('\n' == *ptr || '\r' == *ptr)
			ptr++;

		pkey = ptr;
	}

	rc = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(rc));

	return rc;
}
