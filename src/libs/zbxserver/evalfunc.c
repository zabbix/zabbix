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

#include "common.h"
#include "db.h"
#include "log.h"
#include "zbxserver.h"
#include "evalfunc.h"
#include "zbxregexp.h"
#include "../zbxalgo/vectorimpl.h"

#define ZBX_VALUEMAP_STRING_LEN	64

#define ZBX_VALUEMAP_TYPE_MATCH			0
#define ZBX_VALUEMAP_TYPE_GREATER_OR_EQUAL	1
#define ZBX_VALUEMAP_TYPE_LESS_OR_EQUAL		2
#define ZBX_VALUEMAP_TYPE_RANGE			3
#define ZBX_VALUEMAP_TYPE_REGEX			4
#define ZBX_VALUEMAP_TYPE_DEFAULT		5

typedef struct
{
	char	value[ZBX_VALUEMAP_STRING_LEN];
	char	newvalue[ZBX_VALUEMAP_STRING_LEN];
	int	type;
}
zbx_valuemaps_t;

ZBX_PTR_VECTOR_DECL(valuemaps_ptr, zbx_valuemaps_t *)
ZBX_PTR_VECTOR_IMPL(valuemaps_ptr, zbx_valuemaps_t *)

/******************************************************************************
 *                                                                            *
 * Function: add_value_suffix_uptime                                          *
 *                                                                            *
 * Purpose: Process suffix 'uptime'                                           *
 *                                                                            *
 * Parameters: value - value for adjusting                                    *
 *             max_len - max len of the value                                 *
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
 * Function: add_value_suffix_s                                               *
 *                                                                            *
 * Purpose: Process suffix 's'                                                *
 *                                                                            *
 * Parameters: value - value for adjusting                                    *
 *             max_len - max len of the value                                 *
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
		offset += zbx_snprintf(value + offset, max_len - offset, "%dm ", (int)n);
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
 * Function: is_blacklisted_unit                                              *
 *                                                                            *
 * Purpose:  check if unit is blacklisted or not                              *
 *                                                                            *
 * Parameters: unit - unit to check                                           *
 *                                                                            *
 * Return value: SUCCEED - unit blacklisted                                   *
 *               FAIL - unit is not blacklisted                               *
 *                                                                            *
 ******************************************************************************/
static int	is_blacklisted_unit(const char *unit)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = str_in_list("%,ms,rpm,RPM", unit, ',');

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: add_value_units_no_kmgt                                          *
 *                                                                            *
 * Purpose: add only units to the value                                       *
 *                                                                            *
 * Parameters: value - value for adjusting                                    *
 *             max_len - max len of the value                                 *
 *             units - units (bps, b, B, etc)                                 *
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
		del_zeros(tmp);
	}
	else
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_DBL_EXT(0), value_double);

	zbx_snprintf(value, max_len, "%s%s %s", minus, tmp, units);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: add_value_units_with_kmgt                                        *
 *                                                                            *
 * Purpose: add units with K,M,G,T prefix to the value                        *
 *                                                                            *
 * Parameters: value - value for adjusting                                    *
 *             max_len - max len of the value                                 *
 *             units - units (bps, b, B, etc)                                 *
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
		strscpy(kmgt, "");
	}
	else if (value_double < base * base)
	{
		strscpy(kmgt, "K");
		value_double /= base;
	}
	else if (value_double < base * base * base)
	{
		strscpy(kmgt, "M");
		value_double /= base * base;
	}
	else if (value_double < base * base * base * base)
	{
		strscpy(kmgt, "G");
		value_double /= base * base * base;
	}
	else
	{
		strscpy(kmgt, "T");
		value_double /= base * base * base * base;
	}

	if (SUCCEED != zbx_double_compare(round(value_double), value_double))
	{
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_DBL_EXT(2), value_double);
		del_zeros(tmp);
	}
	else
		zbx_snprintf(tmp, sizeof(tmp), ZBX_FS_DBL_EXT(0), value_double);

	zbx_snprintf(value, max_len, "%s%s %s%s", minus, tmp, kmgt, units);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Function: add_value_suffix                                                 *
 *                                                                            *
 * Purpose: Add suffix for value                                              *
 *                                                                            *
 * Parameters: value - value for replacing                                    *
 *                                                                            *
 * Return value: SUCCEED - suffix added successfully, value contains new value*
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
				time = (time_t)atoi(value);
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

static void	zbx_valuemaps_free(zbx_valuemaps_t *valuemap)
{
	zbx_free(valuemap);
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_value_by_map                                            *
 *                                                                            *
 * Purpose: replace value by mapping value                                    *
 *                                                                            *
 * Parameters: value - value for replacing                                    *
 *             max_len - maximal length of output value                       *
 *             valuemaps - vector of values mapped                            *
 *             value_type - type of input value                               *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, value contains new value   *
 *               FAIL - evaluation failed, value contains old value           *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_value_by_map(char *value, size_t max_len, zbx_vector_valuemaps_ptr_t *valuemaps,
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
		zbx_vector_ptr_t	regexps;

		valuemap = (zbx_valuemaps_t *)valuemaps->values[i];

		if (ZBX_VALUEMAP_TYPE_MATCH == valuemap->type)
		{
			if (ITEM_VALUE_TYPE_STR != value_type)
			{
				double	num1, num2;

				if (ZBX_INFINITY != (num1 = evaluate_string_to_double(value)) &&
						ZBX_INFINITY != (num2 = evaluate_string_to_double(valuemap->value)) &&
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
			zbx_vector_ptr_create(&regexps);

			pattern = valuemap->value;

			match = regexp_match_ex(&regexps, value, pattern, ZBX_CASE_SENSITIVE);

			zbx_regexp_clean_expressions(&regexps);
			zbx_vector_ptr_destroy(&regexps);

			if (ZBX_REGEXP_MATCH == match)
				goto map_value;
		}

		if (ITEM_VALUE_TYPE_STR != value_type &&
				ZBX_INFINITY != (input_value = evaluate_string_to_double(value)))
		{
			double	min, max;

			if (ZBX_VALUEMAP_TYPE_LESS_OR_EQUAL == valuemap->type &&
					ZBX_INFINITY != (max = evaluate_string_to_double(valuemap->value)))
			{
				if (input_value <= max)
					goto map_value;
			}
			else if (ZBX_VALUEMAP_TYPE_GREATER_OR_EQUAL == valuemap->type &&
					ZBX_INFINITY != (min = evaluate_string_to_double(valuemap->value)))
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
				num = num_param(input_ptr);

				for (j = 0; j < num; j++)
				{
					int	found = 0;
					char	*ptr, *range_str;

					range_str = ptr = get_param_dyn(input_ptr, j + 1, NULL);

					if (1 < strlen(ptr) && '-' == *ptr)
						ptr++;

					while (NULL != (ptr = strchr(ptr, '-')))
					{
						if (ptr > range_str && 'e' != ptr[-1] && 'E' != ptr[-1])
							break;
						ptr++;
					}

					if (NULL == ptr)
					{
						min = evaluate_string_to_double(range_str);
						found = ZBX_INFINITY != min && SUCCEED == zbx_double_compare(input_value, min);
					}
					else
					{
						*ptr = '\0';
						min = evaluate_string_to_double(range_str);
						max = evaluate_string_to_double(ptr + 1);
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
		valuemap = (zbx_valuemaps_t *)valuemaps->values[i];

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
 * Function: replace_value_by_map                                             *
 *                                                                            *
 * Purpose: replace value by mapping value                                    *
 *                                                                            *
 * Parameters: value - value for replacing                                    *
 *             max_len - maximal length of output value                       *
 *             valuemapid - index of value map                                *
 *             value_type - type of input value                               *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully, value contains new value   *
 *               FAIL - evaluation failed, value contains old value           *
 *                                                                            *
 ******************************************************************************/
static int	replace_value_by_map(char *value, size_t max_len, zbx_uint64_t valuemapid, unsigned char value_type)
{
	int				ret = FAIL;
	DB_RESULT			result;
	DB_ROW				row;
	zbx_valuemaps_t			*valuemap;
	zbx_vector_valuemaps_ptr_t	valuemaps;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() value:'%s' valuemapid:" ZBX_FS_UI64, __func__, value, valuemapid);

	if (0 == valuemapid)
		goto clean;

	zbx_vector_valuemaps_ptr_create(&valuemaps);

	result = DBselect(
			"select value,newvalue,type"
			" from valuemap_mapping"
			" where valuemapid=" ZBX_FS_UI64
			" order by sortorder asc",
			valuemapid);

	while (NULL != (row = DBfetch(result)))
	{
		del_zeros(row[1]);

		valuemap = (zbx_valuemaps_t *)zbx_malloc(NULL, sizeof(zbx_valuemaps_t));
		zbx_strlcpy_utf8(valuemap->value, row[0], ZBX_VALUEMAP_STRING_LEN);
		zbx_strlcpy_utf8(valuemap->newvalue, row[1], ZBX_VALUEMAP_STRING_LEN);
		valuemap->type = atoi(row[2]);
		zbx_vector_valuemaps_ptr_append(&valuemaps, valuemap);
	}

	DBfree_result(result);

	ret = evaluate_value_by_map(value, max_len, &valuemaps, value_type);

	zbx_vector_valuemaps_ptr_clear_ext(&valuemaps, zbx_valuemaps_free);
	zbx_vector_valuemaps_ptr_destroy(&valuemaps);
clean:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:'%s'", __func__, value);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_format_value                                                 *
 *                                                                            *
 * Purpose: replace value by value mapping or by units                        *
 *                                                                            *
 * Parameters: value      - [IN/OUT] value for replacing                      *
 *             valuemapid - [IN] identificator of value map                   *
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
			del_zeros(value);
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
 * Function: evaluatable_for_notsupported                                     *
 *                                                                            *
 * Purpose: check is function to be evaluated for NOTSUPPORTED items          *
 *                                                                            *
 * Parameters: fn - [IN] function name                                        *
 *                                                                            *
 * Return value: SUCCEED - do evaluate the function for NOTSUPPORTED items    *
 *               FAIL - don't evaluate the function for NOTSUPPORTED items    *
 *                                                                            *
 ******************************************************************************/
int	zbx_evaluatable_for_notsupported(const char *fn)
{
	/* function nodata() are exceptions,                   */
	/* and should be evaluated for NOTSUPPORTED items, too */

	if (0 == strcmp(fn, "nodata"))
		return SUCCEED;

	return FAIL;
}

#ifdef HAVE_TESTS
#	include "../../../tests/libs/zbxserver/valuemaps_test.c"
#endif
