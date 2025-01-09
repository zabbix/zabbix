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

#include "zbxeval.h"
#include "zbxstr.h"
#include "zbxexpr.h"
#include "zbxnum.h"
#include "zbxregexp.h"
#include "zbxvariant.h"

static zbx_get_expressions_by_name_f	get_expressions_by_name_cb = NULL;

void	zbx_init_library_eval(zbx_get_expressions_by_name_f get_expressions_by_name_func)
{
	get_expressions_by_name_cb = get_expressions_by_name_func;
}

static void	count_one_ui64(int *count, int op, zbx_uint64_t value, zbx_uint64_t pattern, zbx_uint64_t mask)
{
	switch (op)
	{
		case OP_EQ:
			if (value == pattern)
				(*count)++;
			break;
		case OP_NE:
			if (value != pattern)
				(*count)++;
			break;
		case OP_GT:
			if (value > pattern)
				(*count)++;
			break;
		case OP_GE:
			if (value >= pattern)
				(*count)++;
			break;
		case OP_LT:
			if (value < pattern)
				(*count)++;
			break;
		case OP_LE:
			if (value <= pattern)
				(*count)++;
			break;
		case OP_BITAND:
			if ((value & mask) == pattern)
				(*count)++;
	}
}

static void	count_one_dbl(int *count, int op, double value, double pattern)
{
	switch (op)
	{
		case OP_EQ:
			if (SUCCEED == zbx_double_compare(value, pattern))
				(*count)++;
			break;
		case OP_NE:
			if (FAIL == zbx_double_compare(value, pattern))
				(*count)++;
			break;
		case OP_GT:
			if (value - pattern > zbx_get_double_epsilon())
				(*count)++;
			break;
		case OP_GE:
			if (value - pattern >= -zbx_get_double_epsilon())
				(*count)++;
			break;
		case OP_LT:
			if (pattern - value > zbx_get_double_epsilon())
				(*count)++;
			break;
		case OP_LE:
			if (pattern - value >= -zbx_get_double_epsilon())
				(*count)++;
	}
}

static int	count_one_str(int *count, int op, const char *value, const char *pattern,
		const zbx_vector_expression_t *regexps, char **error)
{
	int	res;

	switch (op)
	{
		case OP_EQ:
			if (0 == strcmp(value, ZBX_NULL2EMPTY_STR(pattern)))
				(*count)++;
			break;
		case OP_NE:
			if (0 != strcmp(value, ZBX_NULL2EMPTY_STR(pattern)))
				(*count)++;
			break;
		case OP_LIKE:
			if (NULL != strstr(value, ZBX_NULL2EMPTY_STR(pattern)))
				(*count)++;
			break;
		case OP_REGEXP:
			if (FAIL == (res = zbx_regexp_match_ex(regexps, value, pattern, ZBX_CASE_SENSITIVE)))
			{
				*error = zbx_strdup(*error, "invalid regular expression");
				return FAIL;
			}

			if (ZBX_REGEXP_MATCH == res)
				(*count)++;

			break;
		case OP_IREGEXP:
			if (FAIL == (res = zbx_regexp_match_ex(regexps, value, pattern, ZBX_IGNORE_CASE)))
			{
				*error = zbx_strdup(*error, "invalid regular expression");
				return FAIL;
			}

			if (ZBX_REGEXP_MATCH == res)
				(*count)++;

			break;
	}
	return SUCCEED;
}

static int	validate_count_pattern(char *operator, char *pattern, unsigned char value_type,
		zbx_eval_count_pattern_data_t *pdata, char **error)
{
	pdata->numeric_search = (ITEM_VALUE_TYPE_UINT64 == value_type || ITEM_VALUE_TYPE_FLOAT == value_type);

	if (NULL == operator || '\0' == *operator)
	{
		if (NULL == pattern || '\0' == *pattern)
		{
			pdata->op = OP_ANY;
			return SUCCEED;
		}

		pdata->op = (0 != pdata->numeric_search ? OP_EQ : OP_LIKE);
	}
	else if (0 == strcmp(operator, "eq"))
		pdata->op = OP_EQ;
	else if (0 == strcmp(operator, "ne"))
		pdata->op = OP_NE;
	else if (0 == strcmp(operator, "gt"))
		pdata->op = OP_GT;
	else if (0 == strcmp(operator, "ge"))
		pdata->op = OP_GE;
	else if (0 == strcmp(operator, "lt"))
		pdata->op = OP_LT;
	else if (0 == strcmp(operator, "le"))
		pdata->op = OP_LE;
	else if (0 == strcmp(operator, "like"))
		pdata->op = OP_LIKE;
	else if (0 == strcmp(operator, "regexp"))
		pdata->op = OP_REGEXP;
	else if (0 == strcmp(operator, "iregexp"))
		pdata->op = OP_IREGEXP;
	else if (0 == strcmp(operator, "bitand"))
		pdata->op = OP_BITAND;
	else
		pdata->op = OP_UNKNOWN;

	if (OP_UNKNOWN == pdata->op)
	{
		*error = zbx_dsprintf(*error, "operator \"%s\" is not supported for function COUNT", operator);
		return FAIL;
	}

	if (NULL == pattern || '\0' == *pattern)
	{
		/* also match any value if "" is searched in text values */
		if (OP_LIKE == pdata->op || OP_REGEXP == pdata->op || OP_IREGEXP == pdata->op)
		{
			pdata->op = OP_ANY;
			return SUCCEED;
		}
	}

	pdata->numeric_search = (0 != pdata->numeric_search && OP_REGEXP != pdata->op && OP_IREGEXP != pdata->op);

	if (0 != pdata->numeric_search)
	{
		if (NULL != operator && '\0' != *operator && (NULL == pattern || '\0' == *pattern))
		{
			*error = zbx_strdup(*error, "pattern must be provided along with operator for numeric values");
			return FAIL;
		}

		if (OP_LIKE == pdata->op)
		{
			*error = zbx_dsprintf(*error, "operator \"%s\" is not supported for counting numeric values",
					operator);
			return FAIL;
		}

		if (OP_BITAND == pdata->op && ITEM_VALUE_TYPE_FLOAT == value_type)
		{
			*error = zbx_dsprintf(*error, "operator \"%s\" is not supported for counting float values",
					operator);
			return FAIL;
		}

		if (OP_BITAND == pdata->op && NULL != (pdata->pattern2 = strchr(pattern, '/')))
		{
			/* end of the 1st part of the 2nd parameter (number to compare with) */
			*pdata->pattern2 = '\0';
			/* start of the 2nd part of the 2nd parameter (mask) */
			pdata->pattern2++;
		}

		if (NULL != pattern && '\0' != *pattern)
		{
			if (ITEM_VALUE_TYPE_UINT64 == value_type)
			{
				if (OP_BITAND != pdata->op)
				{
					if (SUCCEED != zbx_str2uint64(pattern, ZBX_UNIT_SYMBOLS, &pdata->pattern_ui64))
					{
						*error = zbx_dsprintf(*error, "\"%s\" is not a valid numeric unsigned"
								" value", pattern);
						return FAIL;
					}
					pdata->pattern2_ui64 = 0;
				}
				else
				{
					if (SUCCEED != zbx_is_uint64(pattern, &pdata->pattern_ui64))
					{
						*error = zbx_dsprintf(*error, "\"%s\" is not a valid numeric unsigned"
								" value", pattern);
						return FAIL;
					}

					if (NULL != pdata->pattern2)
					{
						if (SUCCEED != zbx_is_uint64(pdata->pattern2, &pdata->pattern2_ui64))
						{
							*error = zbx_dsprintf(*error, "\"%s\" is not a valid numeric"
									" unsigned value", pdata->pattern2);
							return FAIL;
						}
					}
					else
						pdata->pattern2_ui64 = pdata->pattern_ui64;
				}
			}
			else
			{
				if (SUCCEED != zbx_is_double_suffix(pattern, ZBX_FLAG_DOUBLE_SUFFIX))
				{
					*error = zbx_dsprintf(*error, "\"%s\" is not a valid numeric float value",
							pattern);
					return FAIL;
				}

				pdata->pattern_dbl = zbx_str2double(pattern);
			}
		}
	}
	else if (OP_LIKE != pdata->op && OP_REGEXP != pdata->op && OP_IREGEXP != pdata->op && OP_EQ != pdata->op &&
			OP_NE != pdata->op && ITEM_VALUE_TYPE_NONE != value_type)
	{
		*error = zbx_dsprintf(*error, "operator \"%s\" is not supported for counting textual values", operator);
		return FAIL;
	}

	if ((OP_REGEXP == pdata->op || OP_IREGEXP == pdata->op) && NULL != pattern && '@' == *pattern)
	{
		get_expressions_by_name_cb(&pdata->regexps, pattern + 1);

		if (0 == pdata->regexps.values_num)
		{
			*error = zbx_dsprintf(*error, "global regular expression \"%s\" does not exist", pattern + 1);
			return FAIL;
		}
	}

	return SUCCEED;
}

int	zbx_init_count_pattern(char *operator, char *pattern, unsigned char value_type,
		zbx_eval_count_pattern_data_t *pdata, char **error)
{
	int	ret;

	memset(pdata, 0, sizeof(zbx_eval_count_pattern_data_t));
	zbx_vector_expression_create(&pdata->regexps);

	if (FAIL == (ret = validate_count_pattern(operator, pattern, value_type, pdata, error)))
		zbx_clear_count_pattern(pdata);

	return ret;
}

void	zbx_clear_count_pattern(zbx_eval_count_pattern_data_t *pdata)
{
	if (NULL != pdata->regexps.values)
	{
		zbx_regexp_clean_expressions(&pdata->regexps);
		zbx_vector_expression_destroy(&pdata->regexps);
	}
}

int	zbx_count_var_vector_with_pattern(zbx_eval_count_pattern_data_t *pdata, char *pattern, zbx_vector_var_t *values,
		int limit, int *count, char **error)
{
	int	i;
	char	buf[ZBX_MAX_UINT64_LEN];

	if (OP_ANY == pdata->op)
	{
		if ((*count = values->values_num) > limit)
			*count = values->values_num;

		return SUCCEED;
	}

	for (i = 0; i < values->values_num && *count < limit; i++)
	{
		zbx_variant_t	value;

		value = values->values[i];

		switch (value.type)
		{
			case ZBX_VARIANT_UI64:
				if (0 != pdata->numeric_search)
				{
					count_one_ui64(count, pdata->op, value.data.ui64, pdata->pattern_ui64,
							pdata->pattern2_ui64);
				}
				else
				{
					zbx_snprintf(buf, sizeof(buf), ZBX_FS_UI64, value.data.ui64);
					if (FAIL == count_one_str(count, pdata->op, buf, pattern, &pdata->regexps,
							error))
					{
						return FAIL;
					}
				}
				break;
			case ZBX_VARIANT_DBL:
				if (0 != pdata->numeric_search)
				{
					count_one_dbl(count, pdata->op, value.data.dbl, pdata->pattern_dbl);
				}
				else
				{
					zbx_snprintf(buf, sizeof(buf), ZBX_FS_DBL_EXT(4), value.data.dbl);
					if (FAIL == count_one_str(count, pdata->op, buf, pattern, &pdata->regexps,
							error))
					{
						return FAIL;
					}
				}
				break;
			case ZBX_VARIANT_STR:
				if (FAIL == count_one_str(count, pdata->op, value.data.str, pattern, &pdata->regexps,
						error))
				{
					return FAIL;
				}
				break;
		}
	}

	return SUCCEED;
}
