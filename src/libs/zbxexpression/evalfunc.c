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

#include "evalfunc.h"
#include "funcparam.h"
#include "zbxexpression.h"

#include "zbxregexp.h"
#include "zbxcachevalue.h"
#include "zbxtrends.h"
#include "anomalystl.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxexpr.h"
#include "zbxparam.h"
#include "zbxvariant.h"
#include "zbxdb.h"
#include "zbxeval.h"

#define ZBX_VALUEMAP_TYPE_MATCH			0
#define ZBX_VALUEMAP_TYPE_GREATER_OR_EQUAL	1
#define ZBX_VALUEMAP_TYPE_LESS_OR_EQUAL		2
#define ZBX_VALUEMAP_TYPE_RANGE			3
#define ZBX_VALUEMAP_TYPE_REGEX			4
#define ZBX_VALUEMAP_TYPE_DEFAULT		5

ZBX_PTR_VECTOR_IMPL(valuemaps_ptr, zbx_valuemaps_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: process suffix 'uptime'.                                          *
 *                                                                            *
 * Parameters: value   - [IN/OUT] value for adjusting                         *
 *             max_len - [IN] max len of value                                *
 *                                                                            *
 ******************************************************************************/
static void	add_value_suffix_uptime(char *value, size_t max_len)
{
	double	secs, days;
	size_t	offset = 0;
	int	hours, mins;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 > (secs = round(atof(value))))
	{
		offset += zbx_snprintf(value, max_len, "-");
		secs = -secs;
	}

	days = floor(secs / SEC_PER_DAY);
	secs -= days * SEC_PER_DAY;

	hours = (int)(secs / SEC_PER_HOUR);
	secs -= (double)hours * SEC_PER_HOUR;

	mins = (int)(secs / SEC_PER_MIN);
	secs -= (double)mins * SEC_PER_MIN;

	if (0 != days)
	{
		if (1 == days)
			offset += zbx_snprintf(value + offset, max_len - offset, ZBX_FS_DBL_EXT(0) " day, ", days);
		else
			offset += zbx_snprintf(value + offset, max_len - offset, ZBX_FS_DBL_EXT(0) " days, ", days);
	}

	zbx_snprintf(value + offset, max_len - offset, "%02d:%02d:%02d", hours, mins, (int)secs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process suffix 's'.                                               *
 *                                                                            *
 * Parameters: value   - [IN/OUT] value for adjusting                         *
 *             max_len - [IN] max len of value                                *
 *                                                                            *
 ******************************************************************************/
static void	add_value_suffix_s(char *value, size_t max_len)
{
	double	secs, n;
	size_t	offset = 0;
	int	n_unit = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	secs = atof(value);

	if (0 == floor(fabs(secs) * 1000))
	{
		zbx_snprintf(value, max_len, "%s", (0 == secs ? "0s" : "< 1ms"));
		goto clean;
	}

	if (0 > (secs = round(secs * 1000) / 1000))
	{
		offset += zbx_snprintf(value, max_len, "-");
		secs = -secs;
	}
	else
		*value = '\0';

	if (0 != (n = floor(secs / SEC_PER_YEAR)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, ZBX_FS_DBL_EXT(0) "y ", n);
		secs -= n * SEC_PER_YEAR;
		if (0 == n_unit)
			n_unit = 4;
	}

	if (0 != (n = floor(secs / SEC_PER_MONTH)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%dM ", (int)n);
		secs -= n * SEC_PER_MONTH;
		if (0 == n_unit)
			n_unit = 3;
	}

	if (0 != (n = floor(secs / SEC_PER_DAY)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%dd ", (int)n);
		secs -= n * SEC_PER_DAY;
		if (0 == n_unit)
			n_unit = 2;
	}

	if (4 > n_unit && 0 != (n = floor(secs / SEC_PER_HOUR)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%dh ", (int)n);
		secs -= n * SEC_PER_HOUR;
		if (0 == n_unit)
			n_unit = 1;
	}

	if (3 > n_unit && 0 != (n = floor(secs / SEC_PER_MIN)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%dm ", (int)n);
		secs -= n * SEC_PER_MIN;
	}

	if (2 > n_unit && 0 != (n = floor(secs)))
	{
		offset += zbx_snprintf(value + offset, max_len - offset, "%ds ", (int)n);
		secs -= n;
	}

	if (1 > n_unit && 0 != (n = round(secs * 1000)))
		offset += zbx_snprintf(value + offset, max_len - offset, "%dms", (int)n);

	if (0 != offset && ' ' == value[--offset])
		value[offset] = '\0';
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose:  check if unit is blacklisted or not.                             *
 *                                                                            *
 * Parameters: unit - [IN] unit to check                                      *
 *                                                                            *
 * Return value: SUCCEED - unit blacklisted                                   *
 *               FAIL - unit is not blacklisted                               *
 *                                                                            *
 ******************************************************************************/
static int	is_blacklisted_unit(const char *unit)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = zbx_str_in_list("%,ms,rpm,RPM", unit, ',');

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add only units to the value.                                      *
 *                                                                            *
 * Parameters: value   - [IN/OUT] value for adjusting                         *
 *             max_len - [IN] max len of value                                *
 *             units   - [IN] units (bps, b, B, etc)                          *
 *                                                                            *
 ******************************************************************************/
static void	add_value_units_no_kmgt(char *value, size_t max_len, const char *units)
{
	const char	*minus = "";
	char		tmp[64];
	double		value_double;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 > (value_double = atof(value)))
	{
		minus = "-";
		value_double = -value_double;
	}

	if (SUCCEED != zbx_double_compare(round(value_double), value_double))
	{
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_DBL_EXT(2), value_double);
		zbx_del_zeros(tmp);
	}
	else
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_DBL_EXT(0), value_double);

	zbx_snprintf(value, max_len, "%s%s %s", minus, tmp, units);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add units with K,M,G,T prefix to the value.                       *
 *                                                                            *
 * Parameters: value   - [IN/OUT] value for adjusting                         *
 *             max_len - [IN] max len of value                                *
 *             units   - [IN] units (bps, b, B, etc)                          *
 *                                                                            *
 ******************************************************************************/
static void	add_value_units_with_kmgt(char *value, size_t max_len, const char *units)
{
	const char	*minus = "";
	char		kmgt[8];
	char		tmp[64];
	double		base;
	double		value_double;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 > (value_double = atof(value)))
	{
		minus = "-";
		value_double = -value_double;
	}

	base = (0 == strcmp(units, "B") || 0 == strcmp(units, "Bps") ? 1024 : 1000);

	if (value_double < base)
	{
		zbx_strscpy(kmgt, "");
	}
	else if (value_double < base * base)
	{
		zbx_strscpy(kmgt, "K");
		value_double /= base;
	}
	else if (value_double < base * base * base)
	{
		zbx_strscpy(kmgt, "M");
		value_double /= base * base;
	}
	else if (value_double < base * base * base * base)
	{
		zbx_strscpy(kmgt, "G");
		value_double /= base * base * base;
	}
	else
	{
		zbx_strscpy(kmgt, "T");
		value_double /= base * base * base * base;
	}

	if (SUCCEED != zbx_double_compare(round(value_double), value_double))
	{
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_DBL_EXT(2), value_double);
		zbx_del_zeros(tmp);
	}
	else
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_DBL_EXT(0), value_double);

	zbx_snprintf(value, max_len, "%s%s %s%s", minus, tmp, kmgt, units);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add suffix for value.                                             *
 *                                                                            *
 * Parameters: value      - [IN/OUT] value for replacing                      *
 *             max_len    - [IN] max len of value                             *
 *             units      - [IN] units (bps, b, B, etc)                       *
 *             value_type - [IN] type of input value; ITEM_VALUE_TYPE_*       *
 *                                                                            *
 * Return value: SUCCEED - suffix added successfully, value contains new      *
 *                         value                                              *
 *               FAIL - adding failed, value contains old value               *
 *                                                                            *
 ******************************************************************************/
static void	add_value_suffix(char *value, size_t max_len, const char *units, unsigned char value_type)
{
	struct tm	*local_time;
	time_t		time;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() value:'%s' units:'%s' value_type:%d",
			__func__, value, units, (int)value_type);

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_UINT64:
			if (0 == strcmp(units, "unixtime"))
			{
				time = (time_t)atol(value);
				local_time = localtime(&time);
				strftime(value, max_len, "%Y.%m.%d %H:%M:%S", local_time);
				break;
			}
			ZBX_FALLTHROUGH;
		case ITEM_VALUE_TYPE_FLOAT:
			if (0 == strcmp(units, "s"))
				add_value_suffix_s(value, max_len);
			else if (0 == strcmp(units, "uptime"))
				add_value_suffix_uptime(value, max_len);
			else if ('!' == *units)
				add_value_units_no_kmgt(value, max_len, (const char *)(units + 1));
			else if (SUCCEED == is_blacklisted_unit(units))
				add_value_units_no_kmgt(value, max_len, units);
			else if ('\0' != *units)
				add_value_units_with_kmgt(value, max_len, units);
			break;
		default:
			;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:'%s'", __func__, value);
}

void	zbx_valuemaps_free(zbx_valuemaps_t *valuemap)
{
	zbx_free(valuemap);
}

/******************************************************************************
 *                                                                            *
 * Purpose: replace value by mapping value.                                   *
 *                                                                            *
 * Parameters: value      - [IN/OUT] value for replacing                      *
 *             max_len    - [IN] maximal length of output value               *
 *             valuemaps  - [IN] vector of values mapped                      *
 *             value_type - [IN] type of input value; ITEM_VALUE_TYPE_*       *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, value contains new value   *
 *               FAIL - evaluation failed, value contains old value           *
 *                                                                            *
 ******************************************************************************/
int	evaluate_value_by_map(char *value, size_t max_len, zbx_vector_valuemaps_ptr_t *valuemaps,
		unsigned char value_type)
{
	char		*value_tmp;
	int		i, ret = FAIL;
	double		input_value;
	zbx_valuemaps_t	*valuemap;

	for (i = 0; i < valuemaps->values_num; i++)
	{
		char			*pattern;
		int			match;
		zbx_vector_expression_t	regexps;

		valuemap = valuemaps->values[i];

		if (ZBX_VALUEMAP_TYPE_MATCH == valuemap->type)
		{
			if (ITEM_VALUE_TYPE_STR != value_type)
			{
				double	num1, num2;

				if (ZBX_INFINITY != (num1 = zbx_evaluate_string_to_double(value)) &&
						ZBX_INFINITY != (num2 = zbx_evaluate_string_to_double(valuemap->value)) &&
						SUCCEED == zbx_double_compare(num1, num2))
				{
					goto map_value;
				}
			}
			else if (0 == strcmp(valuemap->value, value))
				goto map_value;
		}

		if (ITEM_VALUE_TYPE_STR == value_type && ZBX_VALUEMAP_TYPE_REGEX == valuemap->type)
		{
			zbx_vector_expression_create(&regexps);

			pattern = valuemap->value;

			match = zbx_regexp_match_ex(&regexps, value, pattern, ZBX_CASE_SENSITIVE);

			zbx_regexp_clean_expressions(&regexps);
			zbx_vector_expression_destroy(&regexps);

			if (ZBX_REGEXP_MATCH == match)
				goto map_value;
		}

		if (ITEM_VALUE_TYPE_STR != value_type &&
				ZBX_INFINITY != (input_value = zbx_evaluate_string_to_double(value)))
		{
			double	min, max;

			if (ZBX_VALUEMAP_TYPE_LESS_OR_EQUAL == valuemap->type &&
					ZBX_INFINITY != (max = zbx_evaluate_string_to_double(valuemap->value)))
			{
				if (input_value <= max)
					goto map_value;
			}
			else if (ZBX_VALUEMAP_TYPE_GREATER_OR_EQUAL == valuemap->type &&
					ZBX_INFINITY != (min = zbx_evaluate_string_to_double(valuemap->value)))
			{
				if (input_value >= min)
					goto map_value;
			}
			else if (ZBX_VALUEMAP_TYPE_RANGE == valuemap->type)
			{
				int	num, j;
				char	*input_ptr;

				input_ptr = valuemap->value;

				zbx_trim_str_list(input_ptr, ',');
				zbx_trim_str_list(input_ptr, '-');
				num = zbx_num_param(input_ptr);

				for (j = 0; j < num; j++)
				{
					int	found = 0;
					char	*ptr, *range_str;

					range_str = ptr = zbx_get_param_dyn(input_ptr, j + 1, NULL);

					if (1 < strlen(ptr) && '-' == *ptr)
						ptr++;

					while (NULL != (ptr = strchr(ptr, '-')))
					{
						if (ptr > range_str && 'e' != *(ptr - 1) && 'E' != *(ptr - 1))
							break;
						ptr++;
					}

					if (NULL == ptr)
					{
						min = zbx_evaluate_string_to_double(range_str);
						found = ZBX_INFINITY != min &&
								SUCCEED == zbx_double_compare(input_value, min);
					}
					else
					{
						*ptr = '\0';
						min = zbx_evaluate_string_to_double(range_str);
						max = zbx_evaluate_string_to_double(ptr + 1);
						if (ZBX_INFINITY != min && ZBX_INFINITY != max &&
								input_value >= min && input_value <= max)
						{
							found = 1;
						}
					}

					zbx_free(range_str);

					if (0 != found)
						goto map_value;
				}
			}
		}
	}

	for (i = 0; i < valuemaps->values_num; i++)
	{
		valuemap = valuemaps->values[i];

		if (ZBX_VALUEMAP_TYPE_DEFAULT == valuemap->type)
			goto map_value;
	}
map_value:
	if (i < valuemaps->values_num)
	{
		value_tmp = zbx_dsprintf(NULL, "%s (%s)", valuemap->newvalue, value);
		zbx_strlcpy_utf8(value, value_tmp, max_len);
		zbx_free(value_tmp);

		ret = SUCCEED;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: replace value by mapping value.                                   *
 *                                                                            *
 * Parameters: value      - [IN/OUT] value for replacing                      *
 *             max_len    - [IN] maximal length of output value               *
 *             valuemapid - [IN] index of value map                           *
 *             value_type - [IN] type of input value; ITEM_VALUE_TYPE_*       *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, value contains new value   *
 *               FAIL - evaluation failed, value contains old value           *
 *                                                                            *
 ******************************************************************************/
static int	replace_value_by_map(char *value, size_t max_len, zbx_uint64_t valuemapid, unsigned char value_type)
{
	int				ret = FAIL;
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_valuemaps_t			*valuemap;
	zbx_vector_valuemaps_ptr_t	valuemaps;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() value:'%s' valuemapid:" ZBX_FS_UI64, __func__, value, valuemapid);

	if (0 == valuemapid)
		goto clean;

	zbx_vector_valuemaps_ptr_create(&valuemaps);

	result = zbx_db_select(
			"select value,newvalue,type"
			" from valuemap_mapping"
			" where valuemapid=" ZBX_FS_UI64
			" order by sortorder asc",
			valuemapid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_del_zeros(row[1]);

		valuemap = (zbx_valuemaps_t *)zbx_malloc(NULL, sizeof(zbx_valuemaps_t));
		zbx_strlcpy_utf8(valuemap->value, row[0], ZBX_VALUEMAP_STRING_LEN);
		zbx_strlcpy_utf8(valuemap->newvalue, row[1], ZBX_VALUEMAP_STRING_LEN);
		valuemap->type = atoi(row[2]);
		zbx_vector_valuemaps_ptr_append(&valuemaps, valuemap);
	}

	zbx_db_free_result(result);

	ret = evaluate_value_by_map(value, max_len, &valuemaps, value_type);

	zbx_vector_valuemaps_ptr_clear_ext(&valuemaps, zbx_valuemaps_free);
	zbx_vector_valuemaps_ptr_destroy(&valuemaps);
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:'%s'", __func__, value);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: replace value by value mapping or by units.                       *
 *                                                                            *
 * Parameters: value      - [IN/OUT] value for replacing                      *
 *             valuemapid - [IN] identifier of value map                      *
 *             units      - [IN] units                                        *
 *             value_type - [IN] value type; ITEM_VALUE_TYPE_*                *
 *                                                                            *
 ******************************************************************************/
void	zbx_format_value(char *value, size_t max_len, zbx_uint64_t valuemapid,
		const char *units, unsigned char value_type)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
			replace_value_by_map(value, max_len, valuemapid, value_type);
			break;
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_del_zeros(value);
			ZBX_FALLTHROUGH;
		case ITEM_VALUE_TYPE_UINT64:
			if (SUCCEED != replace_value_by_map(value, max_len, valuemapid, value_type))
				add_value_suffix(value, max_len, units, value_type);
			break;
		default:
			;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get last Nth value defined by #num:now-timeshift first parameter. *
 *                                                                            *
 * Parameters: item       - [IN] item (performance metric)                    *
 *             parameters - [IN] parameter string with #sec|num/timeshift in  *
 *                               first parameter                              *
 *             ts         - [IN] starting timestamp                           *
 *             value      - [OUT] Nth value                                   *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - value was found successfully copied                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	get_last_n_value(const zbx_dc_evaluate_item_t *item, const char *parameters, const zbx_timespec_t *ts,
		zbx_history_record_t *value, char **error)
{
	int				arg1 = 1, ret = FAIL, time_shift;
	zbx_value_type_t		arg1_type = ZBX_VALUE_NVALUES;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;

	zbx_history_record_vector_create(&values);

	if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 1, &arg1, &arg1_type, &time_shift))
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	if (ZBX_VALUE_NVALUES != arg1_type)
		arg1 = 1;	/* time or non parameter is defaulted to "last(0)" */

	ts_end.sec -= time_shift;

	if (SUCCEED != zbx_vc_get_values(item->itemid, item->value_type, &values, 0, arg1, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (arg1 <= values.values_num)
	{
		*value = values.values[arg1 - 1];
		zbx_vector_history_record_remove(&values, arg1 - 1);
		ret = SUCCEED;
	}
	else
		*error = zbx_strdup(*error, "not enough data");
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'logeventid' for the item.                      *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] regex string for event id matching           *
 *             ts         - [IN] starting timestamp                           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_LOGEVENTID(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	char			*pattern = NULL;
	int			ret = FAIL, nparams;
	zbx_vector_expression_t	regexps;
	zbx_history_record_t	vc_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_expression_create(&regexps);

	if (ITEM_VALUE_TYPE_LOG != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (2 < (nparams = zbx_function_param_parse_count(parameters)))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (2 == nparams)
	{
		if (SUCCEED != get_function_parameter_str(parameters, 2, &pattern))
		{
			*error = zbx_strdup(*error, "invalid third parameter");
			goto out;
		}

		if ('@' == *pattern)
		{
			zbx_dc_get_expressions_by_name(&regexps, pattern + 1);

			if (0 == regexps.values_num)
			{
				*error = zbx_dsprintf(*error, "global regular expression \"%s\" does not exist",
						pattern + 1);
				goto out;
			}
		}
	}
	else
		pattern = zbx_strdup(NULL, "");

	if (SUCCEED == get_last_n_value(item, parameters, ts, &vc_value, error))
	{
		char	logeventid[16];
		int	regexp_ret;

		zbx_snprintf(logeventid, sizeof(logeventid), "%d", vc_value.value.log->logeventid);

		if (FAIL == (regexp_ret = zbx_regexp_match_ex(&regexps, logeventid, pattern, ZBX_CASE_SENSITIVE)))
		{
			*error = zbx_dsprintf(*error, "invalid regular expression \"%s\"", pattern);
		}
		else
		{
			if (ZBX_REGEXP_MATCH == regexp_ret)
				zbx_variant_set_dbl(value, 1);
			else if (ZBX_REGEXP_NO_MATCH == regexp_ret)
				zbx_variant_set_dbl(value, 0);

			ret = SUCCEED;
		}

		zbx_history_record_clear(&vc_value, item->value_type);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "result for LOGEVENTID is empty");
out:
	zbx_free(pattern);

	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_expression_destroy(&regexps);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'logsource' for the item.                       *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] ignored                                      *
 *             ts         - [IN] starting timestamp                           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_LOGSOURCE(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	char			*pattern = NULL;
	int			ret = FAIL, nparams;
	zbx_vector_expression_t	regexps;
	zbx_history_record_t	vc_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_expression_create(&regexps);

	if (ITEM_VALUE_TYPE_LOG != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (2 < (nparams = zbx_function_param_parse_count(parameters)))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (2 == nparams)
	{
		if (SUCCEED != get_function_parameter_str(parameters, 2, &pattern))
		{
			*error = zbx_strdup(*error, "invalid third parameter");
			goto out;
		}

		if ('@' == *pattern)
		{
			zbx_dc_get_expressions_by_name(&regexps, pattern + 1);

			if (0 == regexps.values_num)
			{
				*error = zbx_dsprintf(*error, "global regular expression \"%s\" does not exist",
						pattern + 1);
				goto out;
			}
		}
	}
	else
		pattern = zbx_strdup(NULL, "");

	if (SUCCEED == get_last_n_value(item, parameters, ts, &vc_value, error))
	{
		switch (zbx_regexp_match_ex(&regexps, vc_value.value.log->source, pattern, ZBX_CASE_SENSITIVE))
		{
			case ZBX_REGEXP_MATCH:
				zbx_variant_set_dbl(value, 1);
				ret = SUCCEED;
				break;
			case ZBX_REGEXP_NO_MATCH:
				zbx_variant_set_dbl(value, 0);
				ret = SUCCEED;
				break;
			case FAIL:
				*error = zbx_dsprintf(*error, "invalid regular expression");
		}

		zbx_history_record_clear(&vc_value, item->value_type);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "result for LOGSOURCE is empty");
out:
	zbx_free(pattern);

	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_expression_destroy(&regexps);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'logseverity' for the item.                     *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] Nth last value and time shift (optional)     *
 *             ts         - [IN] starting timestamp                           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_LOGSEVERITY(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	int			ret = FAIL;
	zbx_history_record_t	vc_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ITEM_VALUE_TYPE_LOG != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (1 < zbx_function_param_parse_count(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED == get_last_n_value(item, parameters, ts, &vc_value, error))
	{
		zbx_variant_set_dbl(value, vc_value.value.log->severity);
		zbx_history_record_clear(&vc_value, item->value_type);

		ret = SUCCEED;
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "result for LOGSEVERITY is empty");
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	zbx_history_record_float_compare(const zbx_history_record_t *d1, const zbx_history_record_t *d2)
{
	ZBX_RETURN_IF_NOT_EQUAL(d1->value.dbl, d2->value.dbl);

	return 0;
}

static int	history_record_uint64_compare(const zbx_history_record_t *d1, const zbx_history_record_t *d2)
{
	ZBX_RETURN_IF_NOT_EQUAL(d1->value.ui64, d2->value.ui64);

	return 0;
}

static int	history_record_str_compare(const zbx_history_record_t *d1, const zbx_history_record_t *d2)
{
	return strcmp(d1->value.str, d2->value.str);
}

static int	history_record_log_compare(const zbx_history_record_t *d1, const zbx_history_record_t *d2)
{
	int	value_match;

	if (0 != (value_match = strcmp(d1->value.log->value, d2->value.log->value)))
		return value_match;

	if (NULL != d1->value.log->source && NULL != d2->value.log->source)
		return strcmp(d1->value.log->source, d2->value.log->source);

	if (NULL != d2->value.log->source)
		return -1;

	if (NULL != d1->value.log->source)
		return 1;

	return 0;
}

/* Specialized versions of zbx_vector_history_record_*_uniq() because */
/* standard versions do not release memory occupied by duplicate elements. */

static void	zbx_vector_history_record_str_uniq(zbx_vector_history_record_t *vector, zbx_compare_func_t compare_func)
{
	if (2 <= vector->values_num)
	{
		int	i = 0, j = 1;

		while (j < vector->values_num)
		{
			if (0 != compare_func(&vector->values[i], &vector->values[j]))
			{
				i++;
				j++;
			}
			else
			{
				zbx_free(vector->values[j].value.str);
				zbx_vector_history_record_remove(vector, j);
			}
		}
	}
}

static void	zbx_vector_history_record_log_uniq(zbx_vector_history_record_t *vector, zbx_compare_func_t compare_func)
{
	if (2 <= vector->values_num)
	{
		int	i = 0, j = 1;

		while (j < vector->values_num)
		{
			if (0 != compare_func(&vector->values[i], &vector->values[j]))
			{
				i++;
				j++;
			}
			else
			{
				zbx_free(vector->values[j].value.log->source);
				zbx_free(vector->values[j].value.log->value);
				zbx_free(vector->values[j].value.log);
				zbx_vector_history_record_remove(vector, j);
			}
		}
	}
}

/* flags for evaluate_COUNT() */
#define COUNT_ALL	0
#define COUNT_UNIQUE	1

int	zbx_execute_count_with_pattern(char *pattern, unsigned char value_type, zbx_eval_count_pattern_data_t *pdata,
		zbx_vector_history_record_t *records, int limit, int *count, char **error)
{
	int			i, ret;
	zbx_vector_var_t	values;

	zbx_vector_var_create(&values);
	zbx_vector_var_reserve(&values, records->values_num);
	values.values_num = records->values_num;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_UINT64:
			for (i = 0; i < records->values_num; i++)
				zbx_variant_set_ui64(&values.values[i], records->values[i].value.ui64);

			break;

		case ITEM_VALUE_TYPE_FLOAT:
			for (i = 0; i < records->values_num; i++)
				zbx_variant_set_dbl(&values.values[i], records->values[i].value.dbl);

			break;

		case ITEM_VALUE_TYPE_LOG:
			for (i = 0; i < records->values_num; i++)
				zbx_variant_set_str(&values.values[i], records->values[i].value.log->value);

			break;
		default:
			for (i = 0; i < records->values_num; i++)
				zbx_variant_set_str(&values.values[i], records->values[i].value.str);

			break;
	}

	ret = zbx_count_var_vector_with_pattern(pdata, pattern, &values, limit, count, error);

	zbx_vector_var_destroy(&values);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate functions 'count' and 'find' for the item.               *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] up to three comma-separated fields:          *
 *                            (1) number of seconds/values + timeshift        *
 *                            (2) comparison operator (optional)              *
 *                            (3) value to compare with (optional)            *
 *                                Becomes mandatory for numeric items if 3rd  *
 *                                parameter is specified and is not "regexp"  *
 *                                or "iregexp". With "bitand" can take one of *
 *                                two forms:                                  *
 *                                  - value_to_compare_with/mask,             *
 *                                  - mask.                                   *
 *             ts         - [IN] function evaluation time                     *
 *             limit      - [IN] limit of counted values, will return         *
 *                               when the limit is reached                    *
 *             unique     - [IN] COUNT_ALL - count all values,                *
 *                               COUNT_UNIQUE - count unique values           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_COUNT(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, int limit, int unique, char **error)
{
	int				arg1, nparams, count = 0, ret = FAIL, seconds = 0, nvalues = 0, time_shift;
	char				*operator = NULL, *pattern = NULL;
	zbx_value_type_t		arg1_type;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;
	zbx_eval_count_pattern_data_t	pdata;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() params:%s", __func__, ZBX_NULL2EMPTY_STR(parameters));

	zbx_history_record_vector_create(&values);

	if (3 < (nparams = zbx_function_param_parse_count(parameters)))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 1, &arg1, &arg1_type, &time_shift))
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	if (2 <= nparams && SUCCEED != get_function_parameter_str(parameters, 2, &operator))
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto out;
	}

	if (3 <= nparams)
	{
		if (SUCCEED != get_function_parameter_str(parameters, 3, &pattern))
		{
			*error = zbx_strdup(*error, "invalid fourth parameter");
			goto out;
		}
	}

	ts_end.sec -= time_shift;

	if (FAIL == zbx_init_count_pattern(operator, pattern, item->value_type, &pdata, error))
	{
		goto out;
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		case ZBX_VALUE_NONE:
			nvalues = 1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto clean;
	}

	if (COUNT_UNIQUE == unique)
	{
		switch (item->value_type)
		{
			case ITEM_VALUE_TYPE_UINT64:
				zbx_vector_history_record_sort(&values,
						(zbx_compare_func_t)history_record_uint64_compare);
				zbx_vector_history_record_uniq(&values,
						(zbx_compare_func_t)history_record_uint64_compare);
				break;
			case ITEM_VALUE_TYPE_FLOAT:
				zbx_vector_history_record_sort(&values,
						(zbx_compare_func_t)zbx_history_record_float_compare);
				zbx_vector_history_record_uniq(&values,
						(zbx_compare_func_t)zbx_history_record_float_compare);
				break;
			case ITEM_VALUE_TYPE_LOG:
				zbx_vector_history_record_sort(&values,
						(zbx_compare_func_t)history_record_log_compare);
				zbx_vector_history_record_log_uniq(&values,
						(zbx_compare_func_t)history_record_log_compare);
				break;
			default:
				zbx_vector_history_record_sort(&values,
						(zbx_compare_func_t)history_record_str_compare);
				zbx_vector_history_record_str_uniq(&values,
						(zbx_compare_func_t)history_record_str_compare);
		}
	}

	/* skip counting values one by one if filter matches any value */
	if (OP_ANY != pdata.op)
	{
		if (FAIL == zbx_execute_count_with_pattern(pattern, item->value_type, &pdata, &values, limit, &count,
				error))
		{
			goto clean;
		}
	}
	else
	{
		if ((count = values.values_num) > limit)
			count = limit;
	}

	zbx_variant_set_dbl(value, count);

	ret = SUCCEED;
clean:
	zbx_clear_count_pattern(&pdata);
out:
	zbx_free(operator);
	zbx_free(pattern);

	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'sum' for the item.                             *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] number of seconds/values and time shift      *
 *                               (optional)                                   *
 *             ts         - [IN] starting timestamp                           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_SUM(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	int				arg1, i, ret = FAIL, seconds = 0, nvalues = 0, time_shift;
	zbx_value_type_t		arg1_type;
	zbx_vector_history_record_t	values;
	zbx_history_value_t		result;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (1 != zbx_function_param_parse_count(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 1, &arg1, &arg1_type, &time_shift) ||
			ZBX_VALUE_NONE == arg1_type)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	ts_end.sec -= time_shift;

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
	{
		result.dbl = 0;

		for (i = 0; i < values.values_num; i++)
			result.dbl += values.values[i].value.dbl;
	}
	else
	{
		result.ui64 = 0;

		for (i = 0; i < values.values_num; i++)
			result.ui64 += values.values[i].value.ui64;
	}

	zbx_history_value2variant(&result, item->value_type, value);
	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'avg' for the item.                             *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] number of seconds/values and time shift      *
 *                               (optional)                                   *
 *             ts         - [IN] starting timestamp                           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_AVG(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	int				arg1, ret = FAIL, i, seconds = 0, nvalues = 0, time_shift;
	zbx_value_type_t		arg1_type;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (1 != zbx_function_param_parse_count(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 1, &arg1, &arg1_type, &time_shift) ||
			ZBX_VALUE_NONE == arg1_type)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	ts_end.sec -= time_shift;

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (0 < values.values_num)
	{
		double	avg = 0;

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
		{
			for (i = 0; i < values.values_num; i++)
				avg += values.values[i].value.dbl / (i + 1) - avg / (i + 1);
		}
		else
		{
			for (i = 0; i < values.values_num; i++)
				avg += (double)values.values[i].value.ui64;

			avg = avg / values.values_num;
		}
		zbx_variant_set_dbl(value, avg);

		ret = SUCCEED;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "result for AVG is empty");
		*error = zbx_strdup(*error, "not enough data");
	}
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'last' for the item.                            *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] Nth last value and time shift (optional)     *
 *             ts         - [IN] starting timestamp                           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_LAST(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	int			ret;
	zbx_history_record_t	vc_value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == (ret = get_last_n_value(item, parameters, ts, &vc_value, error)))
	{
		zbx_history_value2variant(&vc_value.value, item->value_type, value);
		zbx_history_record_clear(&vc_value, item->value_type);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/* flags for evaluate_MIN_or_MAX() */
#define EVALUATE_MIN	0
#define EVALUATE_MAX	1

#define LOOP_FIND_MIN_OR_MAX(type, mode_op)							\
	do											\
	{											\
		for (i = 1; i < values.values_num; i++)							\
		{											\
			if (values.values[i].value.type mode_op values.values[index].value.type)	\
				index = i;								\
		}											\
	}												\
	while(0)

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'min' or 'max' for the item.                    *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] number of seconds/values and time shift      *
 *                               (optional)                                   *
 *             ts         - [IN] starting timestamp                           *
 *             min_or_max - [IN] is this evaluate_MIN or evaluate_MAX         *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_MIN_or_MAX(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error, int min_or_max)
{
	int				arg1, i, ret = FAIL, seconds = 0, nvalues = 0, time_shift;
	zbx_value_type_t		arg1_type;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (1 != zbx_function_param_parse_count(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 1, &arg1, &arg1_type, &time_shift) ||
			ZBX_VALUE_NONE == arg1_type)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	ts_end.sec -= time_shift;

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (0 < values.values_num)
	{
		int	index = 0;

		if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			if (EVALUATE_MIN == min_or_max)
			{
				LOOP_FIND_MIN_OR_MAX(ui64, <);
			}
			else
			{
				LOOP_FIND_MIN_OR_MAX(ui64, >);
			}
		}
		else
		{
			if (EVALUATE_MIN == min_or_max)
			{
				LOOP_FIND_MIN_OR_MAX(dbl, <);
			}
			else
			{
				LOOP_FIND_MIN_OR_MAX(dbl, >);
			}
		}

		zbx_history_value2variant(&values.values[index].value, item->value_type, value);
		ret = SUCCEED;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "result for MIN or MAX is empty");
		*error = zbx_strdup(*error, "not enough data");
	}
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'percentile' for the item.                      *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] seconds/values, time shift (optional),       *
 *                               percentage                                   *
 *             ts         - [IN] starting timestamp                           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in        *
 *                         'value'                                            *
 *               FAIL    - failed to evaluate function                        *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_PERCENTILE(zbx_variant_t  *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	int				arg1, time_shift, ret = FAIL, seconds = 0, nvalues = 0;
	zbx_value_type_t		arg1_type;
	double				percentage;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (2 != zbx_function_param_parse_count(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 1, &arg1, &arg1_type, &time_shift) ||
			ZBX_VALUE_NONE == arg1_type)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	ts_end.sec -= time_shift;

	if (SUCCEED != get_function_parameter_float(parameters, 2, ZBX_FLAG_DOUBLE_PLAIN, &percentage) ||
			0.0 > percentage || 100.0 < percentage)
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto out;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (0 < values.values_num)
	{
		int	index;

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
			zbx_vector_history_record_sort(&values, (zbx_compare_func_t)zbx_history_record_float_compare);
		else
			zbx_vector_history_record_sort(&values, (zbx_compare_func_t)history_record_uint64_compare);

		if (0 == percentage)
			index = 1;
		else
			index = (int)ceil(values.values_num * (percentage / 100));

		zbx_history_value2variant(&values.values[index - 1].value, item->value_type, value);

		ret = SUCCEED;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "result for PERCENTILE is empty");
		*error = zbx_strdup(*error, "not enough data");
	}
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'nodata' for the item.                          *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] number of seconds                            *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_NODATA(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		char **error)
{
	int				arg1, num, period, lazy = 1, ret = FAIL;
	zbx_value_type_t		arg1_type;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts;
	char				*arg2 = NULL;
	zbx_proxy_suppress_t		nodata_win;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (2 < (num = zbx_function_param_parse_count(parameters)))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_period(parameters, 1, &arg1, &arg1_type) ||
			ZBX_VALUE_SECONDS != arg1_type || 0 >= arg1)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	if (1 < num && (SUCCEED != get_function_parameter_str(parameters, 2, &arg2) ||
			('\0' != *arg2 && 0 != (lazy = strcmp("strict", arg2)))))
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto out;
	}

	zbx_timespec(&ts);
	nodata_win.flags = ZBX_PROXY_SUPPRESS_DISABLE;

	if (0 != item->proxyid && 0 != lazy)
	{
		int			lastaccess;

		if (SUCCEED != zbx_dc_get_proxy_nodata_win(item->proxyid, &nodata_win, &lastaccess))
		{
			*error = zbx_strdup(*error, "cannot retrieve proxy last access");
			goto out;
		}

		period = arg1 + (ts.sec - lastaccess);
	}
	else
		period = arg1;

	if (SUCCEED == zbx_vc_get_values(item->itemid, item->value_type, &values, period, 1, &ts) &&
			1 == values.values_num)
	{
		zbx_variant_set_dbl(value, 0);
	}
	else
	{
		int	seconds;

		if (SUCCEED != zbx_dc_get_data_expected_from(item->itemid, &seconds))
		{
			*error = zbx_strdup(*error, "item does not exist, is disabled or belongs to a disabled host");
			goto out;
		}

		if (seconds + arg1 > ts.sec)
		{
			*error = zbx_strdup(*error,
					"item does not have enough data after server start or item creation");
			goto out;
		}

		if (0 != (nodata_win.flags & ZBX_PROXY_SUPPRESS_ACTIVE))
		{
			*error = zbx_strdup(*error, "historical data transfer from proxy is still in progress");
			goto out;
		}

		zbx_variant_set_dbl(value, 1);

		if (0 != item->proxyid && 0 != lazy)
		{
			zabbix_log(LOG_LEVEL_TRACE, "Nodata in %s() flag:%d values_num:%d start_time:%d period:%d",
					__func__, nodata_win.flags, nodata_win.values_num, ts.sec - period, period);
		}
	}

	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);
	zbx_free(arg2);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'change' for the item.                          *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             ts         - [IN] starting timestamp                           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_CHANGE(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const zbx_timespec_t *ts,
		char **error)
{
	int				ret = FAIL;
	zbx_vector_history_record_t	values;
	double				result;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (SUCCEED != zbx_vc_get_values(item->itemid, item->value_type, &values, 0, 2, ts) ||
			2 > values.values_num)
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			result = values.values[0].value.dbl - values.values[1].value.dbl;
			break;
		case ITEM_VALUE_TYPE_UINT64:
			/* to avoid overflow */
			if (values.values[0].value.ui64 >= values.values[1].value.ui64)
				result = values.values[0].value.ui64 - values.values[1].value.ui64;
			else
				result = -(double)(values.values[1].value.ui64 - values.values[0].value.ui64);
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (0 == strcmp(values.values[0].value.log->value, values.values[1].value.log->value))
				result = 0;
			else
				result = 1;
			break;

		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			if (0 == strcmp(values.values[0].value.str, values.values[1].value.str))
				result = 0;
			else
				result = 1;
			break;
		default:
			*error = zbx_strdup(*error, "invalid value type");
			goto out;
	}

	zbx_variant_set_dbl(value, result);
	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'fuzzytime' for the item.                       *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] number of seconds                            *
 *             ts         - [IN] starting timestamp                           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_FUZZYTIME(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	int			arg1, ret = FAIL;
	zbx_value_type_t	arg1_type;
	zbx_history_record_t	vc_value;
	zbx_uint64_t		fuzlow, fuzhig;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (1 < zbx_function_param_parse_count(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_period(parameters, 1, &arg1, &arg1_type) ||
			0 >= arg1)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	if (ZBX_VALUE_SECONDS != arg1_type || ts->sec <= arg1)
	{
		*error = zbx_strdup(*error, "invalid argument type or value");
		goto out;
	}

	if (SUCCEED != zbx_vc_get_value(item->itemid, item->value_type, ts, &vc_value))
	{
		*error = zbx_strdup(*error, "cannot get value from value cache");
		goto out;
	}

	fuzlow = (zbx_uint64_t)(ts->sec - arg1);
	fuzhig = (zbx_uint64_t)(ts->sec + arg1);

	if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
	{
		if (vc_value.value.ui64 >= fuzlow && vc_value.value.ui64 <= fuzhig)
			zbx_variant_set_dbl(value, 1);
		else
			zbx_variant_set_dbl(value, 0);
	}
	else
	{
		if (vc_value.value.dbl >= fuzlow && vc_value.value.dbl <= fuzhig)
			zbx_variant_set_dbl(value, 1);
		else
			zbx_variant_set_dbl(value, 0);
	}

	zbx_history_record_clear(&vc_value, item->value_type);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate logical bitwise function 'and' for the item.             *
 *                                                                            *
 * Parameters: value      - [OUT] dynamic buffer,result                       *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] to 2 comma-separated fields:                 *
 *                            (1) same as the 1st parameter for function      *
 *                                evaluate_LAST() (see documentation of       *
 *                                trigger function last()),                   *
 *                            (2) mask to bitwise AND with (mandatory),       *
 *             ts         - [IN] starting timestamp                           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_BITAND(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	char		*last_parameters = NULL;
	int		ret = FAIL;
	zbx_uint64_t	mask;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto clean;
	}

	if (2 < zbx_function_param_parse_count(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto clean;
	}

	if (SUCCEED != get_function_parameter_uint64(parameters, 2, &mask))
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto clean;
	}

	if (NULL == (last_parameters = zbx_function_get_param_dyn(parameters, 1)))
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto clean;
	}

	/* bitand(<item_key>,#0,1)                                                       */
	/* First parameter is the item name, second is history count, third is the mask. */
	/* First and second parameters are resent to evaluate_LAST().                    */
	if (SUCCEED == evaluate_LAST(value, item, last_parameters, ts, error))
	{
		/* the evaluate_LAST() should return uint64 value, but just to be sure try to convert it */
		if (SUCCEED != zbx_variant_convert(value, ZBX_VARIANT_UI64))
		{
			*error = zbx_strdup(*error, "invalid value type");
			goto clean;
		}
		zbx_variant_set_dbl(value, value->data.ui64 & (zbx_uint64_t)mask);
		ret = SUCCEED;
	}

	zbx_free(last_parameters);
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'forecast' for the item.                        *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] number of seconds/values and time shift      *
 *                               (optional)                                   *
 *             ts         - [IN] starting timestamp                           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in 'value'*
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_FORECAST(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	char				*fit_str = NULL, *mode_str = NULL;
	double				*t = NULL, *x = NULL;
	int				nparams, time, arg1, i, ret = FAIL, seconds = 0, nvalues = 0, time_shift;
	zbx_value_type_t		time_type, arg1_type;
	unsigned int			k = 0;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			zero_time;
	zbx_fit_t			fit;
	zbx_mode_t			mode;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (2 > (nparams = zbx_function_param_parse_count(parameters)) || nparams > 4)
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 1, &arg1, &arg1_type, &time_shift) ||
			ZBX_VALUE_NONE == arg1_type)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	if (SUCCEED != get_function_parameter_period(parameters, 2, &time, &time_type) ||
			ZBX_VALUE_SECONDS != time_type)
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto out;
	}

	if (3 <= nparams)
	{
		if (SUCCEED != get_function_parameter_str(parameters, 3, &fit_str) ||
				SUCCEED != zbx_fit_code(fit_str, &fit, &k, error))
		{
			*error = zbx_strdup(*error, "invalid fourth parameter");
			goto out;
		}
	}
	else
	{
		fit = FIT_LINEAR;
	}

	if (4 == nparams)
	{
		if (SUCCEED != get_function_parameter_str(parameters, 4, &mode_str) ||
				SUCCEED != zbx_mode_code(mode_str, &mode, error))
		{
			*error = zbx_strdup(*error, "invalid fifth parameter");
			goto out;
		}
	}
	else
	{
		mode = MODE_VALUE;
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	ts_end.sec -= time_shift;

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (0 < values.values_num)
	{
		t = (double *)zbx_malloc(t, (size_t)values.values_num * sizeof(double));
		x = (double *)zbx_malloc(x, (size_t)values.values_num * sizeof(double));

		zero_time.sec = values.values[values.values_num - 1].timestamp.sec;
		zero_time.ns = values.values[values.values_num - 1].timestamp.ns;

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
		{
			for (i = 0; i < values.values_num; i++)
			{
				t[i] = values.values[i].timestamp.sec - zero_time.sec + 1.0e-9 *
						(values.values[i].timestamp.ns - zero_time.ns + 1);
				x[i] = values.values[i].value.dbl;
			}
		}
		else
		{
			for (i = 0; i < values.values_num; i++)
			{
				t[i] = values.values[i].timestamp.sec - zero_time.sec + 1.0e-9 *
						(values.values[i].timestamp.ns - zero_time.ns + 1);
				x[i] = values.values[i].value.ui64;
			}
		}

		zbx_variant_set_dbl(value, zbx_forecast(t, x, values.values_num,
				ts->sec - zero_time.sec - 1.0e-9 * (zero_time.ns + 1), time, fit, k, mode));
	}
	else
	{
		zbx_variant_set_dbl(value, ZBX_MATH_ERROR);
	}

	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zbx_free(fit_str);
	zbx_free(mode_str);

	zbx_free(t);
	zbx_free(x);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'timeleft' for the item.                        *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] number of seconds/values and time shift      *
 *                               (optional)                                   *
 *             ts         - [IN] starting timestamp                           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in        *
 *                         'value'                                            *
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_TIMELEFT(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	char				*fit_str = NULL;
	double				*t = NULL, *x = NULL, threshold;
	int				nparams, arg1, i, ret = FAIL, seconds = 0, nvalues = 0, time_shift;
	zbx_value_type_t		arg1_type;
	unsigned			k = 0;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			zero_time;
	zbx_fit_t			fit;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (2 > (nparams = zbx_function_param_parse_count(parameters)) || nparams > 3)
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 1, &arg1, &arg1_type, &time_shift) ||
			ZBX_VALUE_NONE == arg1_type)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	if (SUCCEED != get_function_parameter_float(parameters, 2, ZBX_FLAG_DOUBLE_SUFFIX, &threshold))
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto out;
	}

	if (3 == nparams)
	{
		if (SUCCEED != get_function_parameter_str(parameters, 3, &fit_str) ||
				SUCCEED != zbx_fit_code(fit_str, &fit, &k, error))
		{
			*error = zbx_strdup(*error, "invalid fourth parameter");
			goto out;
		}
	}
	else
	{
		fit = FIT_LINEAR;
	}

	if ((FIT_EXPONENTIAL == fit || FIT_POWER == fit) && 0.0 >= threshold)
	{
		*error = zbx_strdup(*error, "exponential and power functions are always positive");
		goto out;
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	ts_end.sec -= time_shift;

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (0 < values.values_num)
	{
		t = (double *)zbx_malloc(t, (size_t)values.values_num * sizeof(double));
		x = (double *)zbx_malloc(x, (size_t)values.values_num * sizeof(double));

		zero_time.sec = values.values[values.values_num - 1].timestamp.sec;
		zero_time.ns = values.values[values.values_num - 1].timestamp.ns;

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
		{
			for (i = 0; i < values.values_num; i++)
			{
				t[i] = values.values[i].timestamp.sec - zero_time.sec + 1.0e-9 *
						(values.values[i].timestamp.ns - zero_time.ns + 1);
				x[i] = values.values[i].value.dbl;
			}
		}
		else
		{
			for (i = 0; i < values.values_num; i++)
			{
				t[i] = values.values[i].timestamp.sec - zero_time.sec + 1.0e-9 *
						(values.values[i].timestamp.ns - zero_time.ns + 1);
				x[i] = values.values[i].value.ui64;
			}
		}

		zbx_variant_set_dbl(value, zbx_timeleft(t, x, values.values_num,
				ts->sec - zero_time.sec - 1.0e-9 * (zero_time.ns + 1), threshold, fit, k));
	}
	else
	{
		zbx_variant_set_dbl(value, ZBX_MATH_ERROR);
	}

	ret = SUCCEED;
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zbx_free(fit_str);

	zbx_free(t);
	zbx_free(x);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	trends_eval_stl(const char *table, zbx_uint64_t itemid, int start, int end, int start_detect_period,
		int end_detect_period, int season, double deviations, const char *dev_alg, int s_window,
		double *value, char **error)
{
	int				i, period_counter, ret = FAIL;
	double				tmp_res, neighboring_right_value, neighboring_left_value = ZBX_INFINITY;
	zbx_vector_history_record_t	values, trend, seasonal, remainder;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);
	zbx_history_record_vector_create(&trend);
	zbx_history_record_vector_create(&seasonal);
	zbx_history_record_vector_create(&remainder);

	for (period_counter = start; period_counter <= end; period_counter += 3600)
	{
		zbx_history_record_t	val;

		val.timestamp.sec = period_counter;
		val.timestamp.ns = 0;

		if (FAIL == zbx_trends_eval_avg(table, itemid, period_counter, period_counter, &tmp_res, NULL))
		{
			val.value.dbl = neighboring_left_value;
		}
		else
		{
			val.value.dbl = tmp_res;
			neighboring_left_value = tmp_res;
		}

		zbx_vector_history_record_append_ptr(&values, &val);
	}

	if (ZBX_INFINITY == neighboring_left_value)
	{
		*error = zbx_strdup(*error, "all data is empty");
		goto out;
	}

	neighboring_right_value = values.values[values.values_num - 1].value.dbl;

	for (i = values.values_num - 2; i >= 0; i--)
	{
		if (ZBX_INFINITY == values.values[i].value.dbl)
			values.values[i].value.dbl = neighboring_right_value;
		else
			neighboring_right_value = values.values[i].value.dbl;
	}

	ret = zbx_STL(&values, season, ROBUST_DEF, s_window, S_DEGREE_DEF, T_WINDOW_DEF, T_DEGREE_DEF, L_WINDOW_DEF,
			L_DEGREE_DEF, S_JUMP_DEF, T_JUMP_DEF, L_JUMP_DEF, INNER_DEF, OUTER_DEF,
			&trend, &seasonal, &remainder, error);

	if (SUCCEED == ret)
	{
		ret = zbx_get_percentage_of_deviations_in_stl_remainder(&remainder, deviations, dev_alg,
				start_detect_period, end_detect_period, value, error);
	}
out:
	zbx_history_record_vector_destroy(&trend, ITEM_VALUE_TYPE_FLOAT);
	zbx_history_record_vector_destroy(&seasonal, ITEM_VALUE_TYPE_FLOAT);
	zbx_history_record_vector_destroy(&remainder, ITEM_VALUE_TYPE_FLOAT);
	zbx_history_record_vector_destroy(&values, ITEM_VALUE_TYPE_FLOAT);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate trend* functions for the item.                           *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             func       - [IN] the trend function to evaluate               *
 *                               (avg, sum, count, delta, max, min)           *
 *             parameters - [IN] function parameters                          *
 *             ts         - [IN] historical time when function must be        *
 *                               evaluated                                    *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in        *
 *                         'value'                                            *
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_TREND(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *func,
		const char *parameters, const zbx_timespec_t *ts, char **error)
{
	time_t		start, end;
	int		ret = FAIL, param_count;
	char		*period = NULL;
	const char	*table;
	double		value_dbl;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	param_count = zbx_function_param_parse_count(parameters);

	if (0 != strcmp(func, "stl") && 1 != param_count)
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (0 == strcmp(func, "stl") && (3 > param_count || 6 < param_count))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_str(parameters, 1, &period))
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	if (SUCCEED != zbx_trends_parse_range(ts->sec, period, &start, &end, error))
		goto out;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			table = "trends";
			break;
		case ITEM_VALUE_TYPE_UINT64:
			table = "trends_uint";
			break;
		default:
			*error = zbx_strdup(*error, "unsupported value type");
			goto out;
	}

	if (0 == strcmp(func, "avg"))
	{
		ret = zbx_trends_eval_avg(table, item->itemid, start, end, &value_dbl, error);
	}
	else if (0 == strcmp(func, "count"))
	{
		ret = zbx_trends_eval_count(table, item->itemid, start, end, &value_dbl, error);
	}
	else if (0 == strcmp(func, "max"))
	{
		ret = zbx_trends_eval_max(table, item->itemid, start, end, &value_dbl, error);
	}
	else if (0 == strcmp(func, "min"))
	{
		ret = zbx_trends_eval_min(table, item->itemid, start, end, &value_dbl, error);
	}
	else if (0 == strcmp(func, "sum"))
	{
		ret = zbx_trends_eval_sum(table, item->itemid, start, end, &value_dbl, error);
	}
	else if (0 == strcmp(func, "stl"))
	{
		char			*dev_alg = NULL;
		int			start_detect_period, end_detect_period, season_shift, season, season_processed,
					detect_period, detect_period_shift;
		double			deviations;
		zbx_uint64_t		s_window;
		zbx_value_type_t	detect_period_type, season_type;

		if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 2, &detect_period,
				&detect_period_type, &detect_period_shift))
		{
			*error = zbx_strdup(*error, "invalid third parameter");
			goto out;
		}

		if (ZBX_VALUE_SECONDS != detect_period_type)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			goto out;
		}

		ZBX_UNUSED(detect_period_shift);

		end_detect_period = end + SEC_PER_HOUR - 1;
		start_detect_period = end_detect_period - detect_period + 1;

		if (start_detect_period < start)
		{
			*error = zbx_strdup(*error, "detection period must not exceed evaluation period");
			goto out;
		}

		if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 3, &season, &season_type,
				&season_shift))
		{
			*error = zbx_strdup(*error, "invalid fourth parameter");
			goto out;
		}

		ZBX_UNUSED(season_shift);

		if (ZBX_VALUE_SECONDS != season_type)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			goto out;
		}

		if (SUCCEED != get_function_parameter_float(parameters, 4, ZBX_FLAG_DOUBLE_PLAIN, &deviations))
			deviations = STL_DEF_DEVIATIONS;

		if (SUCCEED != get_function_parameter_str(parameters, 5, &dev_alg) || '\0' == *dev_alg)
		{
			dev_alg = zbx_strdup(dev_alg, "mad");
		}
		else if ((0 != strcmp("mad", dev_alg) && (0 != strcmp("stddevpop", dev_alg)) &&
				(0 != strcmp("stddevsamp", dev_alg))))
		{
			*error = zbx_strdup(*error, "invalid sixth parameter");
			zbx_free(dev_alg);
			goto out;
		}

		if (SUCCEED != get_function_parameter_uint64(parameters, 6, &s_window))
			s_window = S_WINDOW_DEF;

		season_processed = (int)((double)season / 3600);

		ret = trends_eval_stl(table, item->itemid, start, end, start_detect_period, end_detect_period,
				season_processed, deviations, dev_alg, (int)s_window, &value_dbl, error);

		zbx_free(dev_alg);
	}
	else
	{
		*error = zbx_strdup(*error, "unknown trend function");
		goto out;
	}

	if (SUCCEED == ret)
		zbx_variant_set_dbl(value, value_dbl);
out:
	zbx_free(period);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	validate_params_and_get_data(const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, zbx_vector_history_record_t *values, char **error)
{
	int			arg1, seconds = 0, nvalues = 0, time_shift;
	zbx_value_type_t	arg1_type;
	zbx_timespec_t		ts_end = *ts;

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		return FAIL;
	}

	if (1 != zbx_function_param_parse_count(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		return FAIL;
	}

	if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 1, &arg1, &arg1_type, &time_shift) ||
			ZBX_VALUE_NONE == arg1_type)
	{
		*error = zbx_strdup(*error, "invalid parameter");
		return FAIL;
	}

	ts_end.sec -= time_shift;

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			*error = zbx_strdup(*error, "invalid type of first argument");
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'first' for the item.                           *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] Nth first value and time shift (optional)    *
 *             ts         - [IN] starting timestamp                           *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in        *
 *                         'value'                                            *
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_FIRST(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	int				arg1 = 1, ret = FAIL, seconds = 0, time_shift;
	zbx_value_type_t		arg1_type = ZBX_VALUE_NVALUES;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (1 != zbx_function_param_parse_count(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 1, &arg1, &arg1_type, &time_shift))
	{
		*error = zbx_strdup(*error, "invalid parameter");
		goto out;
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NONE:
			*error = zbx_strdup(*error, "the first argument is not specified");
			goto out;
		case ZBX_VALUE_NVALUES:
			*error = zbx_strdup(*error, "the first argument cannot be number of value");
			goto out;
		default:
			*error = zbx_strdup(*error, "invalid type of first argument");
			THIS_SHOULD_NEVER_HAPPEN;
			goto out;
	}

	if (0 >= arg1)
	{
		*error = zbx_strdup(*error, "the first argument must be greater than 0");
		goto out;
	}

	ts_end.sec -= time_shift;

	if (SUCCEED == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, 0, &ts_end))
	{
		if (0 < values.values_num)
		{
			zbx_history_value2variant(&values.values[values.values_num - 1].value, item->value_type, value);
			ret = SUCCEED;
		}
		else
		{
			*error = zbx_strdup(*error, "not enough data");
			goto out;
		}
	}
	else
		*error = zbx_strdup(*error, "cannot get values from value cache");
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/* flags for evaluate_MONO() */
#define MONOINC		0
#define MONODEC		1

#define CHECK_MONOTONICITY(type, mode_op, epsi_op)						\
	do											\
	{											\
		for (i = 0; i < values.values_num - 1; i++)					\
		{										\
			if (0 == strict && values.values[i + 1].value.type mode_op		\
					(epsi_op + values.values[i].value.type))		\
			{									\
				res = 0;							\
				break;								\
			}									\
			else if (1 == strict && values.values[i + 1].value.type mode_op##=	\
					(epsi_op + values.values[i].value.type ) )		\
			{									\
				res = 0;							\
				break;								\
			}									\
		}										\
	}											\
	while(0)

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate functions 'monoinc' and 'monodec' for the item.          *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] mode, strict or weak monotonicity            *
 *             ts         - [IN] function execution time                      *
 *             gradient   - [IN] check increase or decrease of monotonicity   *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in        *
 *                         'value'                                            *
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_MONO(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, int gradient, char **error)
{
	int				arg1, i, num, time_shift, strict = 0, ret = FAIL, seconds = 0, nvalues = 0;
	char				*arg2 = NULL;
	zbx_uint64_t			res;
	zbx_value_type_t		arg1_type;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	num = zbx_function_param_parse_count(parameters);

	if (1 > num || 2 < num )
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 1, &arg1, &arg1_type, &time_shift) ||
			ZBX_VALUE_NONE == arg1_type)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	if (1 < num && (SUCCEED != get_function_parameter_str(parameters, 2, &arg2) ||
			('\0' != *arg2 && 0 == (strict = (0 == strcmp("strict", arg2))) &&
			0 != strcmp("weak", arg2))))
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto out;
	}

	ts_end.sec -= time_shift;

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (0 < values.values_num)
	{
		res = 1;

		if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
		{
			if (MONOINC == gradient)
			{
				CHECK_MONOTONICITY(dbl, >, +zbx_get_double_epsilon());
			}
			else if (MONODEC == gradient)
			{
				CHECK_MONOTONICITY(dbl, <, -zbx_get_double_epsilon());
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
			}
		}
		else if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
		{
			if (MONOINC == gradient)
			{
				CHECK_MONOTONICITY(ui64, >, 0);
			}
			else if (MONODEC == gradient)
			{
				CHECK_MONOTONICITY(ui64, <, 0);
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
			}
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
		}
		zbx_variant_set_ui64(value, res);
		ret = SUCCEED;
	}
	else
	{
		if (MONOINC == gradient)
			zabbix_log(LOG_LEVEL_DEBUG, "result for MONOINC is empty");
		else if (MONODEC == gradient)
			zabbix_log(LOG_LEVEL_DEBUG, "result for MONODEC is empty");
		else
			THIS_SHOULD_NEVER_HAPPEN;

		*error = zbx_strdup(*error, "not enough data");
	}
out:
	zbx_history_record_vector_destroy(&values, item->value_type);
	zbx_free(arg2);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate functions 'rate' for the item.                           *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] seconds, time shift (optional)               *
 *             ts         - [IN] function execution time                      *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in        *
 *                         'value'                                            *
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_RATE(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
#	define HVD(v) (ITEM_VALUE_TYPE_FLOAT == item->value_type ? v.dbl : (double)v.ui64)
#	define TS2DBL(t) (t.sec + t.ns / 1e9)
#	define LAST(v) (v.values[0])
#	define FIRST(v) (v.values[v.values_num - 1])

	int				arg1, time_shift, ret = FAIL, seconds = 0, nvalues = 0;
	zbx_value_type_t		arg1_type;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() params:%s", __func__, parameters);

	zbx_history_record_vector_create(&values);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	if (1 != zbx_function_param_parse_count(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 1, &arg1, &arg1_type, &time_shift) ||
			ZBX_VALUE_NONE == arg1_type)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	ts_end.sec -= time_shift;

	if (FAIL == zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (1 < values.values_num)
	{
		zbx_history_value_t	last = {0};
		int			i;
		double			delta, gap_start, gap_end, sampled_interval, average_duration_between_samples,
					threshold, interval, range, range_start, range_end;

		/* Reset detection */

		delta = HVD(LAST(values).value) - HVD(FIRST(values).value);

		for (i = values.values_num - 1; i >= 0; i--)
		{
			if (FAIL == zbx_double_compare(HVD(values.values[i].value), HVD(last)) &&
					HVD(values.values[i].value) < HVD(last))
			{
				delta = delta + HVD(last);
			}

			last = values.values[i].value;
		}

		/* Extrapolation */

		if (ZBX_VALUE_NVALUES == arg1_type)
			range = TS2DBL(LAST(values).timestamp) - TS2DBL(FIRST(values).timestamp);
		else
			range = seconds;

		range_start = TS2DBL(ts_end) - range;
		range_end = TS2DBL(ts_end);
		gap_start = TS2DBL(FIRST(values).timestamp) - range_start;
		gap_end = range_end - TS2DBL(LAST(values).timestamp);
		sampled_interval = TS2DBL(LAST(values).timestamp) - TS2DBL(FIRST(values).timestamp);
		average_duration_between_samples = sampled_interval / (values.values_num - 1);

		if (delta > 0 && HVD(FIRST(values).value) >= 0)
		{
			double	zero = sampled_interval * (HVD(FIRST(values).value) / delta);

			if (zero < gap_start)
				gap_start = zero;
		}

		threshold = average_duration_between_samples * 1.1;
		interval = sampled_interval;

		if (gap_start < threshold)
			interval += gap_start;
		else
			interval += average_duration_between_samples / 2;

		if (gap_end < threshold)
			interval += gap_end;
		else
			interval += average_duration_between_samples / 2;

		zbx_variant_set_dbl(value, (delta * (interval / sampled_interval)) / range);

		ret = SUCCEED;
	}
	else
	{
		*error = zbx_strdup(*error, "not enough data");
	}
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s rate=" ZBX_FS_DBL " error:%s", __func__, zbx_result_string(ret),
			(FAIL == ret ? 0 : value->data.dbl), ZBX_NULL2EMPTY_STR(*error));

	return ret;

#	undef HVD
#	undef TS2DBL
#	undef LAST
#	undef FIRST
}

int	zbx_evaluate_RATE(zbx_variant_t *value, zbx_dc_item_t *item, const char *parameters, const zbx_timespec_t *ts,
		char **error)
{
	zbx_dc_evaluate_item_t	evaluate_item;

	evaluate_item.itemid = item->itemid;
	evaluate_item.value_type = item->value_type;
	evaluate_item.proxyid = item->host.proxyid;
	evaluate_item.host = item->host.host;
	evaluate_item.key_orig = item->key_orig;

	return evaluate_RATE(value, &evaluate_item, parameters, ts, error);
}

#define LAST(v, type) v.values[i].value.type
#define PREV(v, type) v.values[i + 1].value.type

#define CHANGECOUNT_DBL(op)										\
	do												\
	{												\
		for (i = 0; i < values.values_num - 1; i++)						\
		{											\
			if (SUCCEED != zbx_double_compare(PREV(values, dbl), LAST(values, dbl)) &&	\
					PREV(values, dbl) op LAST(values, dbl))				\
			{										\
				count++;								\
			}										\
		}											\
	}												\
	while(0)

#define CHANGECOUNT_UI64(op)						\
	do								\
	{								\
		for (i = 0; i < values.values_num - 1; i++)		\
		{							\
			if (PREV(values, ui64) op LAST(values, ui64))	\
				count++;				\
		}							\
	}								\
	while(0)

#define CHANGECOUNT_STR(type)							\
	do									\
	{									\
		for (i = 0; i < values.values_num - 1; i++)			\
		{								\
			if (0 != strcmp(PREV(values, type), LAST(values, type)))\
			{							\
				count++;					\
			}							\
		}								\
	}									\
	while(0)

/* flags for evaluate_CHANGECOUNT() */
#define CHANGE_ALL	0
#define CHANGE_INC	1
#define CHANGE_DEC	2

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function 'changecount' for the item.                     *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] mode, increases, decreases or all changes    *
 *             ts         - [IN] function execution time                      *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in        *
 *                         'value'                                            *
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_CHANGECOUNT(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *parameters,
		const zbx_timespec_t *ts, char **error)
{
	int				arg1, i, nparams, time_shift, mode, ret = FAIL, seconds = 0, nvalues = 0;
	char				*arg2 = NULL;
	zbx_value_type_t		arg1_type;
	zbx_vector_history_record_t	values;
	zbx_timespec_t			ts_end = *ts;
	zbx_uint64_t			count = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	nparams = zbx_function_param_parse_count(parameters);
	if (1 > nparams || 2 < nparams)
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_hist_range(ts->sec, parameters, 1, &arg1, &arg1_type, &time_shift) ||
			ZBX_VALUE_NONE == arg1_type)
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	if (1 < nparams && (SUCCEED != get_function_parameter_str(parameters, 2, &arg2)))
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto out;
	}

	if (NULL == arg2 || '\0' == *arg2 || (0 == strcmp("all", arg2)))
	{
		mode = CHANGE_ALL;
	}
	else if (0 == strcmp("dec", arg2))
	{
		mode = CHANGE_DEC;
	}
	else if (0 == strcmp("inc", arg2))
	{
		mode = CHANGE_INC;
	}
	else
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto out;
	}

	ts_end.sec -= time_shift;

	switch (arg1_type)
	{
		case ZBX_VALUE_SECONDS:
			seconds = arg1;
			break;
		case ZBX_VALUE_NVALUES:
			nvalues = arg1;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (SUCCEED != zbx_vc_get_values(item->itemid, item->value_type, &values, seconds, nvalues, &ts_end))
	{
		*error = zbx_strdup(*error, "cannot get values from value cache");
		goto out;
	}

	if (2 > values.values_num)
	{
		*error = zbx_strdup(*error, "not enough data");
		goto out;
	}

	if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
	{
		if (CHANGE_ALL == mode)
		{
			CHANGECOUNT_UI64(!=);
		}
		else if (CHANGE_INC == mode)
		{
			CHANGECOUNT_UI64(<);
		}
		else if (CHANGE_DEC == mode)
		{
			CHANGECOUNT_UI64(>);
		}
	}
	else if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
	{
		if (CHANGE_ALL == mode)
		{
			for (i = 0; i < values.values_num - 1; i++)
			{
				if (SUCCEED != zbx_double_compare(PREV(values, dbl), LAST(values, dbl)))
				{
					count++;
				}
			}
		}
		else if (CHANGE_INC == mode)
		{
			CHANGECOUNT_DBL(<);
		}
		else if (CHANGE_DEC == mode)
		{
			CHANGECOUNT_DBL(>);
		}
	}
	else if (ITEM_VALUE_TYPE_STR == item->value_type || ITEM_VALUE_TYPE_TEXT == item->value_type)
	{
		CHANGECOUNT_STR(str);
	}
	else if (ITEM_VALUE_TYPE_LOG == item->value_type)
	{
		CHANGECOUNT_STR(log->value);
	}
	else
	{
		*error = zbx_strdup(*error, "invalid value type");
		goto out;
	}

	ret = SUCCEED;
	zbx_variant_set_ui64(value, count);
out:
	zbx_free(arg2);
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#undef CHANGE_ALL
#undef CHANGE_INC
#undef CHANGE_DEC
#undef PREV
#undef LAST

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate baseline* functions for the item.                        *
 *                                                                            *
 * Parameters: value      - [OUT] function result                             *
 *             item       - [IN] item (performance metric)                    *
 *             func       - [IN] baseline function to evaluate (wma, dev)     *
 *             parameters - [IN] function parameters                          *
 *             ts         - [IN] historical time when function must be        *
 *                               evaluated                                    *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in        *
 *                         'value'                                            *
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_BASELINE(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *func,
		const char *parameters, const zbx_timespec_t *ts, char **error)
{
	int			ret = FAIL, season_num;
	char			*period = NULL, *tmp = NULL;
	zbx_vector_dbl_t	values;
	zbx_vector_uint64_t	index;
	double			value_dbl;
	zbx_time_unit_t		season_unit;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_dbl_create(&values);
	zbx_vector_uint64_create(&index);

	if (3 != zbx_function_param_parse_count(parameters))
	{
		*error = zbx_strdup(*error, "invalid number of parameters");
		goto out;
	}

	if (SUCCEED != get_function_parameter_str(parameters, 1, &period))
	{
		*error = zbx_strdup(*error, "invalid second parameter");
		goto out;
	}

	if (SUCCEED != get_function_parameter_str(parameters, 2, &tmp) ||
			ZBX_TIME_UNIT_HOUR > (season_unit = zbx_tm_str_to_unit(tmp)))
	{
		*error = zbx_strdup(*error, "invalid third parameter");
		goto out;
	}
	zbx_free(tmp);

	if (SUCCEED != get_function_parameter_str(parameters, 3, &tmp) || 0 >= (season_num = atoi(tmp)))
	{
		*error = zbx_strdup(*error, "invalid fourth parameter");
		goto out;
	}

	if (0 == strcmp(func, "wma"))
	{
		int	i, weights = 0;

		if (SUCCEED != zbx_baseline_get_data(item->itemid, item->value_type, ts->sec, period, season_num,
				season_unit, 1, &values, &index, error))
		{
			goto out;
		}

		if (0 == values.values_num)
		{
			*error = zbx_strdup(NULL, "not enough data");
			goto out;
		}

		value_dbl = 0;

		for (i = 0; i < values.values_num; i++)
		{
			int	weight = season_num - (int)index.values[i];

			value_dbl += values.values[i] * weight;
			weights += weight;
		}

		value_dbl /= weights;
	}
	else if (0 == strcmp(func, "dev"))
	{
		double	value_dev, value_avg = 0;
		int	i;

		if (SUCCEED != zbx_baseline_get_data(item->itemid, item->value_type, ts->sec, period, season_num,
				season_unit, 0, &values, &index, error))
		{
			goto out;
		}

		if (1 >= values.values_num)
		{
			*error = zbx_strdup(NULL, "not enough data");
			goto out;
		}

		if (SUCCEED != zbx_eval_calc_stddevpop(&values, &value_dev, error))
			goto out;

		if (zbx_get_double_epsilon() <= value_dev)
		{
			for (i = 0; i < values.values_num; i++)
				value_avg += values.values[i];
			value_avg /= values.values_num;

			value_dbl = fabs(values.values[0] - value_avg) / value_dev;
		}
		else
			value_dbl = 0;

		zabbix_log(LOG_LEVEL_TRACE, "fabs(" ZBX_FS_DBL " - " ZBX_FS_DBL ") / " ZBX_FS_DBL " = " ZBX_FS_DBL,
				values.values[0], value_avg, value_dev, value_dbl);
	}
	else
	{
		*error = zbx_strdup(*error, "unknown baseline function");
		goto out;
	}

	zbx_variant_set_dbl(value, value_dbl);

	ret = SUCCEED;
out:
	zbx_free(tmp);
	zbx_free(period);

	zbx_vector_uint64_destroy(&index);
	zbx_vector_dbl_destroy(&values);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	history_to_dbl_vector(const zbx_history_record_t *v, int n, unsigned char value_type,
		zbx_vector_dbl_t *values)
{
	int	i;

	zbx_vector_dbl_reserve(values, (size_t)n);

	if (ITEM_VALUE_TYPE_FLOAT == value_type)
	{
		for (i = 0; i < n; i++)
			zbx_vector_dbl_append(values, v[i].value.dbl);
	}
	else
	{
		for (i = 0; i < n; i++)
			zbx_vector_dbl_append(values, (double)v[i].value.ui64);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: common operations for aggregate function calculation.             *
 *                                                                            *
 * Parameters: value      - [OUT] result                                      *
 *             item       - [IN] item (performance metric)                    *
 *             parameters - [IN] number of seconds/values and time shift      *
 *                               (optional)                                   *
 *             ts         - [IN] time shift                                   *
 *             stat_func  - [IN] pointer to aggregate function to be called   *
 *             min_values - [IN] minimum data values required                 *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, result is stored in        *
 *                         'value'                                            *
 *               FAIL - failed to evaluate function                           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_statistical_func(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item,
		const char *parameters, const zbx_timespec_t *ts, zbx_statistical_func_t stat_func, int min_values,
		char **error)
{
	int				ret = FAIL;
	zbx_vector_history_record_t	values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_history_record_vector_create(&values);

	if (SUCCEED != validate_params_and_get_data(item, parameters, ts, &values, error))
		goto out;

	if (min_values <= values.values_num)
	{
		zbx_vector_dbl_t	values_dbl;
		double			result;

		zbx_vector_dbl_create(&values_dbl);

		history_to_dbl_vector(values.values, values.values_num, item->value_type, &values_dbl);

		if (SUCCEED == (ret = stat_func(&values_dbl, &result, error)))
			zbx_variant_set_dbl(value, result);

		zbx_vector_dbl_destroy(&values_dbl);
	}
	else
		*error = zbx_strdup(*error, "not enough data");
out:
	zbx_history_record_vector_destroy(&values, item->value_type);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate function.                                                *
 *                                                                            *
 * Parameters: value     - [OUT] dynamic buffer, result                       *
 *             item      - [IN] item to calculate function for                *
 *             function  - [IN] function (for example, 'max')                 *
 *             parameter - [IN] parameter of function                         *
 *             ts        - [IN] starting timestamp                            *
 *             error     - [OUT]                                              *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, value contains its value   *
 *               FAIL - evaluation failed                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_evaluate_function(zbx_variant_t *value, const zbx_dc_evaluate_item_t *item, const char *function,
		const char *parameter, const zbx_timespec_t *ts, char **error)
{
	int		ret;
	const char	*ptr;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() function:'%s(/%s/%s,%s)' ts:'%s\'", __func__,
			function, item->host, item->key_orig, parameter, zbx_timespec_str(ts));

	if (0 == strcmp(function, "last"))
	{
		ret = evaluate_LAST(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "min"))
	{
		ret = evaluate_MIN_or_MAX(value, item, parameter, ts, error, EVALUATE_MIN);
	}
	else if (0 == strcmp(function, "max"))
	{
		ret = evaluate_MIN_or_MAX(value, item, parameter, ts, error, EVALUATE_MAX);
	}
	else if (0 == strcmp(function, "avg"))
	{
		ret = evaluate_AVG(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "sum"))
	{
		ret = evaluate_SUM(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "percentile"))
	{
		ret = evaluate_PERCENTILE(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "count"))
	{
		ret = evaluate_COUNT(value, item, parameter, ts, ZBX_MAX_UINT31_1, COUNT_ALL, error);
	}
	else if (0 == strcmp(function, "countunique"))
	{
		ret = evaluate_COUNT(value, item, parameter, ts, ZBX_MAX_UINT31_1, COUNT_UNIQUE, error);
	}
	else if (0 == strcmp(function, "nodata"))
	{
		ret = evaluate_NODATA(value, item, parameter, error);
	}
	else if (0 == strcmp(function, "change"))
	{
		ret = evaluate_CHANGE(value, item, ts, error);
	}
	else if (0 == strcmp(function, "find"))
	{
		ret = evaluate_COUNT(value, item, parameter, ts, 1, COUNT_ALL, error);
	}
	else if (0 == strcmp(function, "fuzzytime"))
	{
		ret = evaluate_FUZZYTIME(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "logeventid"))
	{
		ret = evaluate_LOGEVENTID(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "logseverity"))
	{
		ret = evaluate_LOGSEVERITY(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "logsource"))
	{
		ret = evaluate_LOGSOURCE(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "bitand"))
	{
		ret = evaluate_BITAND(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "forecast"))
	{
		ret = evaluate_FORECAST(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "timeleft"))
	{
		ret = evaluate_TIMELEFT(value, item, parameter, ts, error);
	}
	else if (0 == strncmp(function, "trend", 5))
	{
		ret = evaluate_TREND(value, item, function + 5, parameter, ts, error);
	}
	else if (0 == strcmp(function, "first"))
	{
		ret = evaluate_FIRST(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "kurtosis"))
	{
		ret = evaluate_statistical_func(value, item, parameter, ts, zbx_eval_calc_kurtosis, 1, error);
	}
	else if (0 == strcmp(function, "mad"))
	{
		ret = evaluate_statistical_func(value, item, parameter, ts, zbx_eval_calc_mad, 1, error);
	}
	else if (0 == strcmp(function, "skewness"))
	{
		ret = evaluate_statistical_func(value, item, parameter, ts, zbx_eval_calc_skewness, 1, error);
	}
	else if (0 == strcmp(function, "stddevpop"))
	{
		ret = evaluate_statistical_func(value, item, parameter, ts, zbx_eval_calc_stddevpop, 1, error);
	}
	else if (0 == strcmp(function, "stddevsamp"))
	{
		ret = evaluate_statistical_func(value, item, parameter, ts, zbx_eval_calc_stddevsamp, 2, error);
	}
	else if (0 == strcmp(function, "sumofsquares"))
	{
		ret = evaluate_statistical_func(value, item, parameter, ts, zbx_eval_calc_sumofsquares, 1, error);
	}
	else if (0 == strcmp(function, "varpop"))
	{
		ret = evaluate_statistical_func(value, item, parameter, ts, zbx_eval_calc_varpop, 1, error);
	}
	else if (0 == strcmp(function, "varsamp"))
	{
		ret = evaluate_statistical_func(value, item, parameter, ts, zbx_eval_calc_varsamp, 2, error);
	}
	else if (0 == strcmp(function, "monoinc"))
	{
		ret = evaluate_MONO(value, item, parameter, ts, MONOINC, error);
	}
	else if (0 == strcmp(function, "monodec"))
	{
		ret = evaluate_MONO(value, item, parameter, ts, MONODEC, error);
	}
	else if (0 == strcmp(function, "rate"))
	{
		ret = evaluate_RATE(value, item, parameter, ts, error);
	}
	else if (0 == strcmp(function, "changecount"))
	{
		ret = evaluate_CHANGECOUNT(value, item, parameter, ts, error);
	}
	else if (0 == strncmp(function, "baseline", 8))
	{
		ret = evaluate_BASELINE(value, item, function + 8, parameter, ts, error);
	}
	else if (NULL != (ptr = strstr(function, "_foreach")) && ZBX_CONST_STRLEN("_foreach") == strlen(ptr))
	{
		*error = zbx_dsprintf(*error, "single item query is not supported by \"%s\" function", function);
		ret = FAIL;
	}
	else
	{
		*error = zbx_strdup(*error, "function is not supported");
		ret = FAIL;
	}

	if (SUCCEED == ret)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:'%s' of type:'%s'", __func__, zbx_result_string(ret),
				zbx_variant_value_desc(value), zbx_variant_type_desc(value));
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
#undef MONOINC
#undef MONODEC
#undef EVALUATE_MIN
#undef EVALUATE_MAX

/******************************************************************************
 *                                                                            *
 * Purpose: check if the specified function is a trigger function.            *
 *                                                                            *
 * Parameters: name - [IN] function name to check                             *
 *             len  - [IN] length of function name                            *
 *                                                                            *
 * Return value: SUCCEED - the function is a trigger function                 *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_trigger_function(const char *name, size_t len)
{
	char	*functions[] = {"last", "min", "max", "avg", "sum", "percentile", "count", "countunique", "nodata",
			"change", "find", "fuzzytime", "logeventid", "logseverity", "logsource", "bitand", "forecast",
			"timeleft", "trendavg", "trendcount", "trendmax", "trendmin", "trendsum", "abs", "cbrt",
			"ceil", "exp", "floor", "log", "log10", "power", "round", "rand", "signum", "sqrt", "truncate",
			"acos", "asin", "atan", "cos", "cosh", "cot", "sin", "sinh", "tan", "degrees", "radians", "mod",
			"pi", "e", "expm1", "atan2", "first", "kurtosis", "mad", "skewness", "stddevpop", "stddevsamp",
			"sumofsquares", "varpop", "varsamp", "ascii", "bitlength", "char", "concat", "insert", "lcase",
			"left", "ltrim", "bytelength", "repeat", "replace", "right", "rtrim", "mid", "trim", "between",
			"in", "bitor", "bitxor", "bitnot", "bitlshift", "bitrshift", "baselinewma", "baselinedev",
			"jsonpath", "xmlxpath",
			NULL};
	char	**ptr;

	for (ptr = functions; NULL != *ptr; ptr++)
	{
		size_t	compare_len;

		compare_len = strlen(*ptr);
		if (compare_len == len && 0 == memcmp(*ptr, name, len))
			return SUCCEED;
	}

	return FAIL;
}
