/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "item_preproc.h"

#include "zbxregexp.h"
#include "zbxembed.h"
#include "zbxvariant.h"
#include "zbxtime.h"
#include "zbxjson.h"
#include "zbxstr.h"

#include "zbxxml.h"
#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#	include <libxml/parser.h>
#endif

#include "zbxnum.h"

/******************************************************************************
 *                                                                            *
 * Purpose: returns numeric type hint based on item value type                *
 *                                                                            *
 * Parameters: value_type - [IN] item value type                              *
 *                                                                            *
 * Return value: variant numeric type or none                                 *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_numeric_type_hint(unsigned char value_type)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			return ZBX_VARIANT_DBL;
		case ITEM_VALUE_TYPE_UINT64:
			return ZBX_VARIANT_UI64;
		default:
			return ZBX_VARIANT_NONE;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert variant value to the requested type                       *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to convert                             *
 *             type   - [IN] new value type                                   *
 *             errmsg - [OUT]                                                 *
 *                                                                            *
 * Return value: SUCCEED - the value was converted successfully               *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_convert_value(zbx_variant_t *value, unsigned char type, char **errmsg)
{
	if (FAIL == zbx_variant_convert(value, type))
	{
		*errmsg = zbx_dsprintf(*errmsg, "cannot convert value to %s", zbx_get_variant_type_desc(type));
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts variant value to numeric                                 *
 *                                                                            *
 * Parameters: value_num  - [OUT] converted value                             *
 *             value      - [IN] value to convert                             *
 *             value_type - [IN] item value type                              *
 *             errmsg     - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - the value was converted successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_item_preproc_convert_value_to_numeric(zbx_variant_t *value_num, const zbx_variant_t *value,
		unsigned char value_type, char **errmsg)
{
	int	ret = FAIL, type_hint;

	switch (value->type)
	{
		case ZBX_VARIANT_DBL:
		case ZBX_VARIANT_UI64:
			zbx_variant_copy(value_num, value);
			ret = SUCCEED;
			break;
		case ZBX_VARIANT_STR:
			ret = zbx_variant_set_numeric(value_num, value->data.str);
			break;
		default:
			ret = FAIL;
	}

	if (FAIL == ret)
	{
		*errmsg = zbx_strdup(*errmsg, "cannot convert value to numeric type");
		return FAIL;
	}

	if (ZBX_VARIANT_NONE != (type_hint = item_preproc_numeric_type_hint(value_type)))
		zbx_variant_convert(value_num, type_hint);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute custom multiplier preprocessing operation on variant      *
 *          value type                                                        *
 *                                                                            *
 * Parameters: value_type - [IN] item type                                    *
 *             value      - [IN/OUT] value to process                         *
 *             params     - [IN] operation parameters                         *
 *             errmsg     - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_multiplier_variant(unsigned char value_type, zbx_variant_t *value, const char *params,
		char **errmsg)
{
	zbx_uint64_t	multiplier_ui64, value_ui64;
	double		value_dbl;
	zbx_variant_t	value_num;

	if (FAIL == zbx_item_preproc_convert_value_to_numeric(&value_num, value, value_type, errmsg))
		return FAIL;

	switch (value_num.type)
	{
		case ZBX_VARIANT_DBL:
			value_dbl = value_num.data.dbl * atof(params);
			zbx_variant_clear(value);
			zbx_variant_set_dbl(value, value_dbl);
			break;
		case ZBX_VARIANT_UI64:
			if (SUCCEED == zbx_is_uint64(params, &multiplier_ui64))
				value_ui64 = value_num.data.ui64 * multiplier_ui64;
			else
				value_ui64 = (zbx_uint64_t)((double)value_num.data.ui64 * atof(params));

			zbx_variant_clear(value);
			zbx_variant_set_ui64(value, value_ui64);
			break;
	}

	zbx_variant_clear(&value_num);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute delta type preprocessing operation                        *
 *                                                                            *
 * Parameters: value         - [IN/OUT] value to process                      *
 *             ts            - [IN] value timestamp                           *
 *             op_type       - [IN] operation type                            *
 *             history_value - [IN] item historical data                      *
 *             history_ts    - [IN] historical data timestamp                 *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_delta_float(zbx_variant_t *value, const zbx_timespec_t *ts, int op_type,
		const zbx_variant_t *history_value, const zbx_timespec_t *history_ts)
{
	if (0 == history_ts->sec || history_value->data.dbl > value->data.dbl)
		return FAIL;

	switch (op_type)
	{
		case ZBX_PREPROC_DELTA_SPEED:
			if (0 <= zbx_timespec_compare(history_ts, ts))
				return FAIL;

			value->data.dbl = (value->data.dbl - history_value->data.dbl) /
					((ts->sec - history_ts->sec) +
						(double)(ts->ns - history_ts->ns) / 1000000000);
			break;
		case ZBX_PREPROC_DELTA_VALUE:
			value->data.dbl = value->data.dbl - history_value->data.dbl;
			break;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute delta type preprocessing operation                        *
 *                                                                            *
 * Parameters: value         - [IN/OUT] value to process                      *
 *             ts            - [IN] value timestamp                           *
 *             op_type       - [IN] operation type                            *
 *             history_value - [IN] item historical data                      *
 *             history_ts    - [IN] historical data timestamp                 *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_delta_uint64(zbx_variant_t *value, const zbx_timespec_t *ts, int op_type,
		const zbx_variant_t *history_value, const zbx_timespec_t *history_ts)
{
	if (0 == history_ts->sec || history_value->data.ui64 > value->data.ui64)
		return FAIL;

	switch (op_type)
	{
		case ZBX_PREPROC_DELTA_SPEED:
			if (0 <= zbx_timespec_compare(history_ts, ts))
				return FAIL;

			value->data.ui64 = (zbx_uint64_t)((double)(value->data.ui64 - history_value->data.ui64) /
					((ts->sec - history_ts->sec) +
						(double)(ts->ns - history_ts->ns) / 1000000000));
			break;
		case ZBX_PREPROC_DELTA_VALUE:
			value->data.ui64 = value->data.ui64 - history_value->data.ui64;
			break;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute delta type preprocessing operation                        *
 *                                                                            *
 * Parameters: value_type    - [IN] item value type                           *
 *             value         - [IN/OUT] value to process                      *
 *             ts            - [IN] value timestamp                           *
 *             op_type       - [IN] operation type                            *
 *             history_value_in - [IN] historical (previous) data             *
 *             history_value_out - [OUT] historical (next) data               *
 *             history_ts    - [IN/OUT] timestamp of the historical data      *
 *             errmsg        - [OUT]                                          *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_delta(unsigned char value_type, zbx_variant_t *value, const zbx_timespec_t *ts,
		int op_type, const zbx_variant_t *history_value_in, zbx_variant_t *history_value_out,
		zbx_timespec_t *history_ts, char **errmsg)
{
	zbx_variant_t	value_num;

	if (FAIL == zbx_item_preproc_convert_value_to_numeric(&value_num, value, value_type, errmsg))
		return FAIL;

	zbx_variant_copy(history_value_out, history_value_in);

	zbx_variant_clear(value);

	if (ZBX_VARIANT_NONE != history_value_out->type)
	{
		int	ret;

		zbx_variant_copy(value, &value_num);

		if (ZBX_VARIANT_DBL == value->type || ZBX_VARIANT_DBL == history_value_out->type)
		{
			if (FAIL == zbx_variant_convert(value, ZBX_VARIANT_DBL))
			{
				*errmsg = zbx_dsprintf(*errmsg, "cannot convert value from %s to %s",
						zbx_variant_type_desc(value),
						zbx_get_variant_type_desc(ZBX_VARIANT_DBL));

				zabbix_log(LOG_LEVEL_CRIT, *errmsg);
				THIS_SHOULD_NEVER_HAPPEN;
				return FAIL;
			}

			if (FAIL == zbx_variant_convert(history_value_out, ZBX_VARIANT_DBL))
			{
				*errmsg = zbx_dsprintf(*errmsg, "cannot convert value from %s to %s",
						zbx_variant_type_desc(history_value_out),
						zbx_get_variant_type_desc(ZBX_VARIANT_DBL));

				zabbix_log(LOG_LEVEL_CRIT, *errmsg);
				THIS_SHOULD_NEVER_HAPPEN;
				return FAIL;
			}

			ret = item_preproc_delta_float(value, ts, op_type, history_value_out, history_ts);
		}
		else
		{
			if (FAIL == zbx_variant_convert(value, ZBX_VARIANT_UI64))
			{
				*errmsg = zbx_dsprintf(*errmsg, "cannot convert value from %s to %s",
						zbx_variant_type_desc(value),
						zbx_get_variant_type_desc(ZBX_VARIANT_UI64));

				zabbix_log(LOG_LEVEL_CRIT, *errmsg);
				THIS_SHOULD_NEVER_HAPPEN;
				return FAIL;
			}

			if (FAIL == zbx_variant_convert(history_value_out, ZBX_VARIANT_UI64))
			{
				*errmsg = zbx_dsprintf(*errmsg, "cannot convert value from %s to %s",
						zbx_variant_type_desc(history_value_out),
						zbx_get_variant_type_desc(ZBX_VARIANT_UI64));

				zabbix_log(LOG_LEVEL_CRIT, *errmsg);
				THIS_SHOULD_NEVER_HAPPEN;
				return FAIL;
			}

			ret = item_preproc_delta_uint64(value, ts, op_type, history_value_out, history_ts);
		}

		if (SUCCEED != ret)
			zbx_variant_clear(value);
	}

	*history_ts = *ts;
	zbx_variant_clear(history_value_out);
	zbx_variant_copy(history_value_out, &value_num);
	zbx_variant_clear(&value_num);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy first n chars from in to out, unescape escaped characters    *
 *          during copying                                                    *
 *                                                                            *
 * Parameters: op_type - [IN] operation type                                  *
 *             in      - [IN] value to unescape                               *
 *             len     - [IN] length of the value to be unescaped             *
 *             out     - [OUT] value to process                               *
 *                                                                            *
 ******************************************************************************/
static void	unescape_param(int op_type, const char *in, size_t len, char *out)
{
	const char	*end = in + len;

	for (;in < end; in++, out++)
	{
		if ('\\' == *in)
		{
			switch (*(++in))
			{
				case 's':
					*out = ' ';
					break;
				case 'r':
					*out = '\r';
					break;
				case 'n':
					*out = '\n';
					break;
				case 't':
					*out = '\t';
					break;
				case '\\':
					if (ZBX_PREPROC_STR_REPLACE == op_type)
					{
						*out = '\\';
						break;
					}
					ZBX_FALLTHROUGH;
				default:
					*out = *(--in);
			}
		}
		else
			*out = *in;
	}

	*out = '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute trim type preprocessing operation                         *
 *                                                                            *
 * Parameters: value   - [IN/OUT] value to process                            *
 *             op_type - [IN] operation type                                  *
 *             params  - [IN] characters to trim                              *
 *             errmsg  - [OUT]                                                *
 *                                                                            *
 * Return value: SUCCEED - the value was trimmed successfully                 *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_trim(zbx_variant_t *value, int op_type, const char *params, char **errmsg)
{
	char	*params_raw;
	size_t	params_len;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	params_len = strlen(params);
	params_raw = (char *)zbx_malloc(NULL, params_len + 1);

	unescape_param(op_type, params, params_len, params_raw);

	if (ZBX_PREPROC_LTRIM == op_type || ZBX_PREPROC_TRIM == op_type)
		zbx_ltrim(value->data.str, params_raw);

	if (ZBX_PREPROC_RTRIM == op_type || ZBX_PREPROC_TRIM == op_type)
		zbx_rtrim(value->data.str, params_raw);

	zbx_free(params_raw);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the string is boolean                                    *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             value - [OUT] boolean value                                    *
 *                                                                            *
 * Return value:  SUCCEED - the string is boolean                             *
 *                FAIL - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
static int	is_boolean(const char *str, zbx_uint64_t *value)
{
	double	dbl_tmp;
	int	res;

	if (SUCCEED == (res = zbx_is_double(str, &dbl_tmp)))
		*value = (0 != dbl_tmp);
	else
	{
		char	tmp[16];

		zbx_strscpy(tmp, str);
		zbx_strlower(tmp);

		if (SUCCEED == (res = zbx_str_in_list("true,t,yes,y,on,up,running,enabled,available,ok,master", tmp,
				',')))
		{
			*value = 1;
		}
		else if (SUCCEED == (res = zbx_str_in_list(
				"false,f,no,n,off,down,unused,disabled,unavailable,err,slave", tmp, ',')))
		{
			*value = 0;
		}
	}

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the string is unsigned octal                             *
 *                                                                            *
 * Parameters: str - [IN] string to check                                     *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned octal                      *
 *                FAIL - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
static int	is_uoct(const char *str)
{
	int	res = FAIL;

	while (' ' == *str)	/* trim left spaces */
		str++;

	for (; '\0' != *str; str++)
	{
		if (*str < '0' || *str > '7')
			break;

		res = SUCCEED;
	}

	while (' ' == *str)	/* check right spaces */
		str++;

	if ('\0' != *str)
		return FAIL;

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the string is unsigned hexadecimal representation of     *
 *          data in the form "0-9, a-f or A-F"                                *
 *                                                                            *
 * Parameters: str - [IN] string to check                                     *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned hexadecimal                *
 *                FAIL - otherwise                                            *
 *                                                                            *
 ******************************************************************************/
static int	is_uhex(const char *str)
{
	int	res = FAIL;

	while (' ' == *str)	/* trim left spaces */
		str++;

	for (; '\0' != *str; str++)
	{
		if (0 == isxdigit(*str))
			break;

		res = SUCCEED;
	}

	while (' ' == *str)	/* check right spaces */
		str++;

	if ('\0' != *str)
		return FAIL;

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute decimal value conversion operation                        *
 *                                                                            *
 * Parameters: value   - [IN/OUT] value to convert                            *
 *             op_type - [IN] operation type                                  *
 *             errmsg  - [OUT]                                                *
 *                                                                            *
 * Return value: SUCCEED - the value was converted successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_2dec(zbx_variant_t *value, int op_type, char **errmsg)
{
#define OCT2UINT64(uint, string)	sscanf(string, ZBX_FS_UO64, &uint)
#define HEX2UINT64(uint, string)	sscanf(string, ZBX_FS_UX64, &uint)

	zbx_uint64_t	value_ui64;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	zbx_ltrim(value->data.str, " \"");
	zbx_rtrim(value->data.str, " \"\n\r");

	switch (op_type)
	{
		case ZBX_PREPROC_BOOL2DEC:
			if (SUCCEED != is_boolean(value->data.str, &value_ui64))
			{
				*errmsg = zbx_strdup(NULL, "invalid value format");
				return FAIL;
			}
			break;
		case ZBX_PREPROC_OCT2DEC:
			if (SUCCEED != is_uoct(value->data.str))
			{
				*errmsg = zbx_strdup(NULL, "invalid value format");
				return FAIL;
			}
			OCT2UINT64(value_ui64, value->data.str);
			break;
		case ZBX_PREPROC_HEX2DEC:
			if (SUCCEED != is_uhex(value->data.str))
			{
				if (SUCCEED != zbx_is_hex_string(value->data.str))
				{
					*errmsg = zbx_strdup(NULL, "invalid value format");
					return FAIL;
				}

				zbx_remove_chars(value->data.str, " \n");
			}
			HEX2UINT64(value_ui64, value->data.str);
			break;
		default:
			*errmsg = zbx_strdup(NULL, "unknown operation type");
			return FAIL;
	}

	zbx_variant_clear(value);
	zbx_variant_set_ui64(value, value_ui64);

	return SUCCEED;
#undef OCT2UINT64
#undef HEX2UINT64
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute regular expression substitution operation                 *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] operation parameters                             *
 *             errmsg - [OUT]                                                 *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_regsub_op(zbx_variant_t *value, const char *params, char **errmsg)
{
	char		*pattern, *output, *new_value = NULL;
	char		*regex_error = NULL;
	zbx_regexp_t	*regex = NULL;
	int		ret = FAIL;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	pattern = zbx_strdup(NULL, params);

	if (NULL == (output = strchr(pattern, '\n')))
	{
		*errmsg = zbx_strdup(*errmsg, "cannot find second parameter");
		goto out;
	}

	*output++ = '\0';

	if (FAIL == zbx_regexp_compile_ext(pattern, &regex, 0, &regex_error))	/* PCRE_MULTILINE is not used here */
	{
		*errmsg = zbx_dsprintf(*errmsg, "invalid regular expression: %s", regex_error);
		zbx_free(regex_error);
		goto out;
	}

	if (FAIL == zbx_mregexp_sub_precompiled(value->data.str, regex, output, ZBX_MAX_RECV_DATA_SIZE, &new_value))
	{
		*errmsg = zbx_strdup(*errmsg, "pattern does not match");
		goto out;
	}

	zbx_variant_clear(value);
	zbx_variant_set_str(value, new_value);

	ret = SUCCEED;
out:
	if (NULL != regex)
		zbx_regexp_free(regex);

	zbx_free(pattern);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates value to be within the specified range                  *
 *                                                                            *
 * Parameters: value_type - [IN] item type                                    *
 *             value      - [IN/OUT] value to process                         *
 *             params     - [IN] operation parameters                         *
 *             errmsg     - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_validate_range(unsigned char value_type, const zbx_variant_t *value, const char *params,
		char **errmsg)
{
	zbx_variant_t	value_num;
	char		*min, *max;
	zbx_variant_t	range_min, range_max;
	int		ret = FAIL;

	if (FAIL == zbx_item_preproc_convert_value_to_numeric(&value_num, value, value_type, errmsg))
		return FAIL;

	min = zbx_strdup(NULL, params);

	zbx_variant_set_none(&range_min);
	zbx_variant_set_none(&range_max);

	if (NULL == (max = strchr(min, '\n')))
	{
		*errmsg = zbx_strdup(*errmsg, "validation range is not specified");
		goto out;
	}

	*max++ = '\0';

	if ('\0' != *min && FAIL == zbx_variant_set_numeric(&range_min, min))
	{
		*errmsg = zbx_dsprintf(*errmsg, "validation range minimum value is invalid: %s", min);
		goto out;
	}

	if ('\0' != *max && FAIL == zbx_variant_set_numeric(&range_max, max))
	{
		*errmsg = zbx_dsprintf(*errmsg, "validation range maximum value is invalid: %s", max);
		goto out;
	}

	if ((ZBX_VARIANT_NONE != range_min.type && 0 > zbx_variant_compare(&value_num, &range_min)) ||
			(ZBX_VARIANT_NONE != range_max.type && 0 > zbx_variant_compare(&range_max, &value_num)))
	{
		size_t	errmsg_alloc = 0, errmsg_offset = 0;

		zbx_free(*errmsg);

		zbx_strcpy_alloc(errmsg, &errmsg_alloc, &errmsg_offset, "value is");
		if ('\0' != *min)
		{
			zbx_snprintf_alloc(errmsg, &errmsg_alloc, &errmsg_offset, " less than %s", min);
			if ('\0' != *max)
				zbx_strcpy_alloc(errmsg, &errmsg_alloc, &errmsg_offset, " or");
		}
		if ('\0' != *max)
			zbx_snprintf_alloc(errmsg, &errmsg_alloc, &errmsg_offset, " greater than %s", max);

		goto out;
	}

	ret = SUCCEED;
out:
	zbx_variant_clear(&value_num);
	zbx_variant_clear(&range_min);
	zbx_variant_clear(&range_max);

	zbx_free(min);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates value to match regular expression                       *
 *                                                                            *
 * Parameters: value      - [IN/OUT] value to process                         *
 *             params     - [IN] operation parameters                         *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_validate_regex(const zbx_variant_t *value, const char *params, char **error)
{
	zbx_variant_t	value_str;
	int		ret = FAIL;
	zbx_regexp_t	*regex;
	char		*errptr = NULL;
	char		*errmsg;

	zbx_variant_copy(&value_str, value);

	if (FAIL == zbx_variant_convert(&value_str, ZBX_VARIANT_STR))
	{
		errmsg = zbx_strdup(NULL, "cannot convert value to string");
		goto out;
	}

	if (FAIL == zbx_regexp_compile(params, &regex, &errptr))
	{
		errmsg = zbx_dsprintf(NULL, "invalid regular expression pattern: %s", errptr);
		zbx_free(errptr);
		goto out;
	}

	if (0 != zbx_regexp_match_precompiled(value_str.data.str, regex))
		errmsg = zbx_strdup(NULL, "value does not match regular expression");
	else
		ret = SUCCEED;

	zbx_regexp_free(regex);
out:
	zbx_variant_clear(&value_str);

	if (FAIL == ret)
	{
		*error = zbx_dsprintf(*error, "cannot perform regular expression \"%s\" validation"
				" for value of type \"%s\": %s",
				params, zbx_variant_type_desc(value), errmsg);
		zbx_free(errmsg);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates value to not match regular expression                   *
 *                                                                            *
 * Parameters: value      - [IN/OUT] value to process                         *
 *             params     - [IN] operation parameters                         *
 *             error      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_validate_not_regex(const zbx_variant_t *value, const char *params, char **error)
{
	zbx_variant_t	value_str;
	int		ret = FAIL;
	zbx_regexp_t	*regex;
	char		*errptr = NULL;
	char		*errmsg;

	zbx_variant_copy(&value_str, value);

	if (FAIL == zbx_variant_convert(&value_str, ZBX_VARIANT_STR))
	{
		errmsg = zbx_strdup(NULL, "cannot convert value to string");
		goto out;
	}

	if (FAIL == zbx_regexp_compile(params, &regex, &errptr))
	{
		errmsg = zbx_dsprintf(NULL, "invalid regular expression pattern: %s", errptr);
		zbx_free(errptr);
		goto out;
	}

	if (0 == zbx_regexp_match_precompiled(value_str.data.str, regex))
	{
		errmsg = zbx_strdup(NULL, "value matches regular expression");
	}
	else
		ret = SUCCEED;

	zbx_regexp_free(regex);
out:
	zbx_variant_clear(&value_str);

	if (FAIL == ret)
	{
		*error = zbx_dsprintf(*error, "cannot perform regular expression \"%s\" validation"
				" for value of type \"%s\": %s",
				params, zbx_variant_type_desc(value), errmsg);
		zbx_free(errmsg);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks for presence of error field in json data                   *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] operation parameters                             *
 *             error  - [OUT]                                                 *
 *                                                                            *
 * Return value: FAIL - preprocessing step error                              *
 *               SUCCEED - preprocessing step succeeded, error may contain    *
 *                         extracted error message                            *
 *                                                                            *
 * Comments: This preprocessing step is used to check if the returned data    *
 *           contains explicit (API related) error message and sets it as     *
 *           error, while returning SUCCEED.                                  *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_get_error_from_json(const zbx_variant_t *value, const char *params, char **error)
{
	zbx_variant_t		value_str;
	int			ret;
	struct zbx_json_parse	jp;

	zbx_variant_copy(&value_str, value);

	if (FAIL == (ret = item_preproc_convert_value(&value_str, ZBX_VARIANT_STR, error)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		goto out;
	}

	if (FAIL == zbx_json_open(value->data.str, &jp))
		goto out;

	if (FAIL == (ret = zbx_jsonpath_query(&jp, params, error)))
	{
		*error = zbx_strdup(NULL, zbx_json_strerror());
		goto out;
	}

	if (NULL != *error)
	{
		zbx_lrtrim(*error, ZBX_WHITESPACE);
		if ('\0' == **error)
			zbx_free(*error);
	}
out:
	zbx_variant_clear(&value_str);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks for presence of error field in XML data                    *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] operation parameters                             *
 *             error  - [OUT]                                                 *
 *                                                                            *
 * Return value: FAIL - preprocessing step error                              *
 *               SUCCEED - preprocessing step succeeded, error may contain    *
 *                         extracted error message                            *
 *                                                                            *
 * Comments: This preprocessing step is used to check if the returned data    *
 *           contains explicit (API related) error message and sets it as     *
 *           error, while returning SUCCEED.                                  *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_get_error_from_xml(const zbx_variant_t *value, const char *params, char **error)
{
#ifndef HAVE_LIBXML2
	ZBX_UNUSED(value);
	ZBX_UNUSED(params);
	ZBX_UNUSED(error);

	*error = zbx_dsprintf(*error, "Zabbix was compiled without libxml2 support");

	return FAIL;
#else
	zbx_variant_t		value_str;
	int			ret = SUCCEED, i;
	xmlDoc			*doc = NULL;
	xmlXPathContext		*xpathCtx = NULL;
	xmlXPathObject		*xpathObj = NULL;
	const xmlError		*pErr;
	xmlBufferPtr		xmlBufferLocal;

	zbx_variant_copy(&value_str, value);

	if (FAIL == (ret = item_preproc_convert_value(&value_str, ZBX_VARIANT_STR, error)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		goto out;
	}

	if (NULL == (doc = xmlReadMemory(value_str.data.str, (int)strlen(value_str.data.str), "noname.xml", NULL, 0)))
		goto out;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)params, xpathCtx)))
	{
		pErr = xmlGetLastError();
		*error = zbx_dsprintf(*error, "cannot parse xpath \"%s\": %s", params, pErr->message);
		ret = FAIL;
		goto out;
	}

	switch (xpathObj->type)
	{
		case XPATH_NODESET:
			if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
				goto out;

			xmlBufferLocal = xmlBufferCreate();

			for (i = 0; i < xpathObj->nodesetval->nodeNr; i++)
				xmlNodeDump(xmlBufferLocal, doc, xpathObj->nodesetval->nodeTab[i], 0, 0);

			*error = zbx_strdup(*error, (const char *)xmlBufferLocal->content);
			xmlBufferFree(xmlBufferLocal);
			break;
		case XPATH_STRING:
			*error = zbx_strdup(*error, (const char *)xpathObj->stringval);
			break;
		case XPATH_BOOLEAN:
			*error = zbx_dsprintf(*error, "%d", xpathObj->boolval);
			break;
		case XPATH_NUMBER:
			*error = zbx_dsprintf(*error, ZBX_FS_DBL, xpathObj->floatval);
			break;
		default:
			*error = zbx_strdup(*error, "Unknown error");
			break;
	}

	zbx_lrtrim(*error, ZBX_WHITESPACE);
	if ('\0' == **error)
		zbx_free(*error);
out:
	zbx_variant_clear(&value_str);

	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	if (NULL != xpathCtx)
		xmlXPathFreeContext(xpathCtx);

	if (NULL != doc)
		xmlFreeDoc(doc);

	return ret;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks for presence of error pattern matching regular expression  *
 *                                                                            *
 * Parameters: value  - [IN] value to process                                 *
 *             params - [IN] operation parameters                             *
 *             error  - [OUT]                                                 *
 *                                                                            *
 * Return value: FAIL - preprocessing step error                              *
 *               SUCCEED - preprocessing step succeeded, error may contain    *
 *                         extracted error message                            *
 *                                                                            *
 * Comments: This preprocessing step is used to check if the returned data    *
 *           contains explicit (API related) error message and sets it as     *
 *           error, while returning SUCCEED.                                  *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_get_error_from_regex(const zbx_variant_t *value, const char *params, char **error)
{
	zbx_variant_t	value_str;
	int		ret;
	char		*pattern = NULL, *output;

	zbx_variant_copy(&value_str, value);

	if (FAIL == (ret = item_preproc_convert_value(&value_str, ZBX_VARIANT_STR, error)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		goto out;
	}

	pattern = zbx_strdup(NULL, params);

	if (NULL == (output = strchr(pattern, '\n')))
	{
		*error = zbx_strdup(*error, "cannot find second parameter");
		ret = FAIL;
		goto out;
	}

	*output++ = '\0';

	if (FAIL == zbx_mregexp_sub(value_str.data.str, pattern, output, ZBX_REGEXP_GROUP_CHECK_DISABLE, error))
	{
		*error = zbx_dsprintf(*error, "invalid regular expression \"%s\"", pattern);
		ret = FAIL;
		goto out;
	}

	if (NULL != *error)
	{
		zbx_lrtrim(*error, ZBX_WHITESPACE);
		if ('\0' == **error)
			zbx_free(*error);
	}
out:
	zbx_free(pattern);
	zbx_variant_clear(&value_str);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks error for pattern matching regular expression              *
 *                                                                            *
 * Parameters: value  - [IN] value to process                                 *
 *             params - [IN] operation parameters                             *
 *             error  - [IN/OUT]                                              *
 *                                                                            *
 * Return value: FAIL - preprocessing step error                              *
 *               SUCCEED - preprocessing step succeeded, error may contain    *
 *                         extracted error message                            *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_check_error_regex(const zbx_variant_t *value, const char *params, char **error)
{
#define ZBX_PP_MATCH_TYPE_MATCHES	0
#define ZBX_PP_MATCH_TYPE_ANY		-1
	zbx_variant_t	value_str;
	int		ret = SUCCEED, match_type = ZBX_PP_MATCH_TYPE_ANY;
	char		*pattern = NULL, *newline, *out = NULL, *errptr = NULL;
	zbx_regexp_t	*regex;

	zbx_variant_copy(&value_str, value);

	if (NULL != (newline = strchr(params, '\n')))
	{
		newline++;
		pattern = zbx_strdup(NULL, newline);
		match_type = atoi(params);
	}

	if (ZBX_PP_MATCH_TYPE_ANY == match_type)
		goto out;

	if (ZBX_PP_MATCH_TYPE_MATCHES == match_type)
	{
		if (FAIL == zbx_regexp_compile_ext(pattern, &regex, 0, &errptr))
		{
			*error = zbx_dsprintf(*error, "invalid regular expression: %s", errptr);
			zbx_free(errptr);
			goto out;
		}

		if (SUCCEED == zbx_mregexp_sub_precompiled(value->data.str, regex, *error, ZBX_MAX_RECV_DATA_SIZE,
				&out))
		{
			if (NULL != out)
			{
				zbx_free(*error);
				*error = out;
			}
		}
		else
			ret = FAIL;
	}
	else
	{
		int	res;

		if (FAIL == zbx_regexp_compile(pattern, &regex, &errptr))
		{
			*error = zbx_dsprintf(*error, "invalid regular expression: %s", errptr);
			zbx_free(errptr);
			ret = FAIL;
			goto out;
		}

		if (FAIL != (res = zbx_regexp_match_precompiled2(value_str.data.str, regex, &errptr)))
		{
			if (ZBX_REGEXP_MATCH == res)
				ret = FAIL;
		}
		else
		{
			*error = zbx_dsprintf(*error, "regular expression execution failed: %s", errptr);
			zbx_free(errptr);
			ret = FAIL;
		}
	}

	zbx_regexp_free(regex);
out:
	zbx_free(pattern);
	zbx_variant_clear(&value_str);

	return ret;
#undef ZBX_PP_MATCH_TYPE_MATCHES
#undef ZBX_PP_MATCH_TYPE_ANY
}

/******************************************************************************
 *                                                                            *
 * Purpose: throttles value by suppressing identical values                   *
 *                                                                            *
 * Parameters: value             - [IN/OUT] value to process                  *
 *             ts                - [IN] value timestamp                       *
 *             history_value_in  - [IN] historical (previous) data            *
 *             history_value_out - [OUT] historical (next) data               *
 *             history_ts        - [OUT] timestamp of historical data         *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_throttle_value(zbx_variant_t *value, const zbx_timespec_t *ts,
		const zbx_variant_t *history_value_in, zbx_variant_t *history_value_out, zbx_timespec_t *history_ts)
{
	int	ret;

	ret = zbx_variant_compare(value, history_value_in);

	zbx_variant_clear(history_value_out);
	zbx_variant_copy(history_value_out, value);

	if (0 == ret)
		zbx_variant_clear(value);
	else
		*history_ts = *ts;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: throttles value by suppressing identical values                   *
 *                                                                            *
 * Parameters: value             - [IN/OUT] value to process                  *
 *             ts                - [IN] value timestamp                       *
 *             params            - [IN] throttle period                       *
 *             history_value_in  - [IN] historical (previous) data            *
 *             history_value_out - [OUT] historical (next) data               *
 *             history_ts        - [IN/OUT] timestamp of historical data      *
 *             errmsg            - [OUT]                                      *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_throttle_timed_value(zbx_variant_t *value, const zbx_timespec_t *ts, const char *params,
		const zbx_variant_t *history_value_in, zbx_variant_t *history_value_out, zbx_timespec_t *history_ts,
		char **errmsg)
{
	int	ret, timeout, period = 0;

	if (FAIL == zbx_is_time_suffix(params, &timeout, (int)strlen(params)))
	{
		*errmsg = zbx_dsprintf(*errmsg, "invalid time period: %s", params);
		zbx_variant_clear(history_value_out);
		return FAIL;
	}

	ret = zbx_variant_compare(value, history_value_in);

	zbx_variant_clear(history_value_out);
	zbx_variant_copy(history_value_out, value);

	if (ZBX_VARIANT_NONE != history_value_out->type)
		period = ts->sec - history_ts->sec;

	if (0 == ret && period < timeout )
		zbx_variant_clear(value);
	else
		*history_ts = *ts;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: executes script passed with params                                *
 *                                                                            *
 * Parameters: es               - [IN] execution environment                  *
 *             value            - [IN/OUT] value to process                   *
 *             params           - [IN] script to execute                      *
 *             bytecode         - [IN] precompiled bytecode, can be NULL      *
 *             bytecode_in      - [IN] historical (previous) bytecode         *
 *             bytecode_out     - [IN] historical (next) bytecode             *
 *             config_source_ip - [IN]                                        *
 *             errmsg           - [OUT]                                       *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_script(zbx_es_t *es, zbx_variant_t *value, const char *params, const zbx_variant_t *bytecode_in,
		zbx_variant_t *bytecode_out, const char *config_source_ip, char **errmsg)
{
	char		*output = NULL, *error = NULL;
	const char	*code2;
	int		size;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	if (SUCCEED != zbx_es_is_env_initialized(es))
	{
		if (SUCCEED != zbx_es_init_env(es, config_source_ip, errmsg))
			return FAIL;
	}

	if (ZBX_VARIANT_BIN != bytecode_in->type)
	{
		char	*code;

		if (SUCCEED != zbx_es_compile(es, params, &code, &size, errmsg))
			goto fail;

		zbx_variant_set_bin(bytecode_out, zbx_variant_data_bin_create(code, (zbx_uint32_t)size));
		zbx_free(code);
	}
	else
		zbx_variant_copy(bytecode_out, bytecode_in);

	size = (int)zbx_variant_data_bin_get(bytecode_out->data.bin, (const void ** const)&code2);

	if (SUCCEED == zbx_es_execute(es, params, code2, size, value->data.str, &output, errmsg))
	{
		zbx_variant_clear(value);

		if (NULL != output)
			zbx_variant_set_str(value, output);

		return SUCCEED;
	}
fail:
	if (SUCCEED == zbx_es_fatal_error(es))
	{
		if (SUCCEED != zbx_es_destroy_env(es, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING,
					"Cannot destroy embedded scripting engine environment: %s", error);
			zbx_free(error);
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert CSV format metrics to JSON format                         *
 *                                                                            *
 * Parameters: json    - [IN/OUT] json object                                 *
 *             names   - [IN/OUT] column names                                *
 *             field   - [IN] field                                           *
 *             num     - [IN] field number                                    *
 *             num_max - [IN] maximum number of fields                        *
 *             header  - [IN] header line option                              *
 *             errmsg  - [OUT]                                                *
 *                                                                            *
 * Return value: SUCCEED - the field was added successfully                   *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_csv_to_json_add_field(struct zbx_json *json, char ***names, char *field, unsigned int num,
		unsigned int num_max, unsigned int header, char **errmsg)
{
	char	**fld_names = *names;

	if (0 < num_max && num >= num_max && 1 == header)
	{
		*errmsg = zbx_strdup(*errmsg,
				"cannot convert CSV to JSON: data row contains more fields than header row");
		return FAIL;
	}

	if (NULL == field)
		field = "";

	if (0 == num_max && 1 == header)
	{
		unsigned int	i;

		for (i = 0; i < num; i++)
		{
			if (0 == strcmp(fld_names[i], field))
			{
				*errmsg = zbx_dsprintf(*errmsg,
						"cannot convert CSV to JSON: duplicated column name \"%s\"", field);
				return FAIL;
			}
		}

		fld_names = zbx_realloc(fld_names, (num + 1) * sizeof(char*));
		fld_names[num] = zbx_strdup(NULL, field);
		*names = fld_names;
	}
	else
	{
		if (0 == num)
			zbx_json_addobject(json, NULL);

		if (0 == header)
		{
			char	num_buf[ZBX_MAX_UINT64_LEN];

			zbx_snprintf(num_buf, ZBX_MAX_UINT64_LEN, "%u", num + 1);
			zbx_json_addstring(json, num_buf, field, ZBX_JSON_TYPE_STRING);
		}
		else
			zbx_json_addstring(json, fld_names[num], field, ZBX_JSON_TYPE_STRING);

		if (ZBX_MAX_RECV_DATA_SIZE <= json->buffer_allocated)
		{
			*errmsg = zbx_strdup(*errmsg, "cannot convert CSV to JSON: input data is too large");
			return FAIL;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert CSV format metrics to JSON format                         *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             params - [IN] operation parameters                             *
 *             errmsg - [OUT]                                                 *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_csv_to_json(zbx_variant_t *value, const char *params, char **errmsg)
{
#define CSV_STATE_FIELD		0
#define CSV_STATE_DELIM		1
#define CSV_STATE_FIELD_QUOTED	2

	unsigned int	fld_num = 0, fld_num_max = 0, hdr_line, state = CSV_STATE_DELIM;
	char		*field, *field_esc = NULL, **field_names = NULL, *data, *value_out = NULL,
			delim[ZBX_MAX_BYTES_IN_UTF8_CHAR], quote[ZBX_MAX_BYTES_IN_UTF8_CHAR];
	struct zbx_json	json;
	size_t		data_len, delim_sz = 1, quote_sz = 0, step;
	int		ret = SUCCEED;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	delim[0] = ',';
	zbx_json_initarray(&json, ZBX_JSON_STAT_BUF_LEN);
	data = value->data.str;
	data_len = strlen(value->data.str);

#define CSV_SEP_LINE	"sep="
	if (0 == zbx_strncasecmp(data, CSV_SEP_LINE, ZBX_CONST_STRLEN(CSV_SEP_LINE)))
	{
		char	*p;
		size_t	del_sz;

		p = value->data.str + ZBX_CONST_STRLEN(CSV_SEP_LINE);

		if (NULL == (field = strpbrk(p, "\r\n")))
			field = data + data_len;

		if (0 < (del_sz = zbx_utf8_char_len(p)) && p + del_sz == field)
		{
			memcpy(delim, p, del_sz);
			delim_sz = del_sz;

			if ('\0' == *field)
				data = field;
			else if ('\r' == *field)
				data = field + 2;
			else
				data = field + 1;
		}
	}
#undef CSV_SEP_LINE

	if ('\n' != *params)
	{
		if (NULL == (field = strchr(params, '\n')))
		{
			*errmsg = zbx_strdup(*errmsg, "cannot find second parameter");
			return FAIL;
		}

		if (0 == (delim_sz = zbx_utf8_char_len(params)) || params + delim_sz != field)
		{
			*errmsg = zbx_strdup(*errmsg, "invalid first parameter");
			return FAIL;
		}

		memcpy(delim, params, delim_sz);
		params = field;
	}

	if ('\n' != *(++params))
	{
		if (NULL == (field = strchr(params, '\n')))
		{
			*errmsg = zbx_strdup(*errmsg, "cannot find third parameter");
			return FAIL;
		}

		if (0 == (quote_sz = zbx_utf8_char_len(params)) || params + quote_sz != field)
		{
			*errmsg = zbx_strdup(*errmsg, "invalid second parameter");
			return FAIL;
		}

		memcpy(quote, params, quote_sz);
		params = field;
	}

	hdr_line = ('1' == *(++params) ? 1 : 0);

	if ('\0' == *data)
		goto out;

	for (field = NULL; value->data.str + data_len >= data; data += step)
	{
		if (0 == (step = zbx_utf8_char_len(data)))
		{
			*errmsg = zbx_strdup(*errmsg, "cannot convert CSV to JSON: invalid UTF-8 character in value");
			ret = FAIL;
			goto out;
		}

		if (CSV_STATE_FIELD_QUOTED != state)
		{
			if ('\r' == *data)
			{
				*data = '\0';

				if ('\n' != *(++data) && '\0' != *data)
				{
					*errmsg = zbx_strdup(*errmsg, "cannot convert CSV to JSON: unsupported line "
							"break");
					ret = FAIL;
					goto out;
				}
			}

			if ('\n' == *data || '\0' == *data)
			{
				if (CSV_STATE_FIELD == state || 1 == hdr_line || 0 != fld_num)
				{
					*data = '\0';

					do
					{
						if (FAIL == (ret = item_preproc_csv_to_json_add_field(&json,
								&field_names, field, fld_num, fld_num_max, hdr_line,
								errmsg)))
							goto out;

						field = NULL;
						zbx_free(field_esc);
					} while (++fld_num < fld_num_max && 1 == hdr_line);

					if (fld_num > fld_num_max)
						fld_num_max = fld_num;

					fld_num = 0;
				}
				else
					zbx_json_addobject(&json, NULL);

				zbx_json_close(&json);
				state = CSV_STATE_DELIM;
			}
			else if (step == delim_sz && 0 == memcmp(data, delim, delim_sz))
			{
				*data = '\0';

				if (FAIL == (ret = item_preproc_csv_to_json_add_field(&json,
						&field_names, field, fld_num, fld_num_max, hdr_line,
						errmsg)))
					goto out;

				field = NULL;
				zbx_free(field_esc);
				fld_num++;
				state = CSV_STATE_DELIM;
			}
			else if (CSV_STATE_DELIM == state && step == quote_sz && 0 == memcmp(data, quote, quote_sz))
			{
				state = CSV_STATE_FIELD_QUOTED;
			}
			else if (CSV_STATE_FIELD != state)
			{
				field = data;
				state = CSV_STATE_FIELD;
			}
		}
		else if (step == quote_sz && 0 == memcmp(data, quote, quote_sz))
		{
			char	*data_next = data + quote_sz;
			size_t	char_sz;

			if (0 == (char_sz = zbx_utf8_char_len(data_next)))
				continue;	/* invalid UTF-8 */

			if (char_sz == quote_sz && 0 == memcmp(data_next, quote, quote_sz))
			{
				if (NULL == field)
					field = data;

				*data_next = '\0';
				field_esc = zbx_dsprintf(field_esc, "%s%s", ZBX_NULL2EMPTY_STR(field_esc), field);
				field = NULL;
				data = data_next;
			}
			else if ('\r' == *data_next || '\n' == *data_next || '\0' == *data_next ||
					(char_sz == delim_sz && 0 == memcmp(data_next, delim, delim_sz)))
			{
				state = CSV_STATE_FIELD;
				*data = '\0';

				if (NULL != field_esc)
				{
					field_esc = zbx_dsprintf(field_esc, "%s%s", field_esc,
							ZBX_NULL2EMPTY_STR(field));
					field = field_esc;
				}
			}
			else
			{
				*errmsg = zbx_dsprintf(*errmsg, "cannot convert CSV to JSON: delimiter character or "
						"end of line are not detected after quoted field \"%.*s\"",
						(int)(data - field), field);
				ret = FAIL;
				goto out;
			}
		}
		else if (NULL == field)
		{
			field = data;
		}
	}

	if (CSV_STATE_FIELD_QUOTED == state)
	{
		*errmsg = zbx_dsprintf(*errmsg, "cannot convert CSV to JSON: unclosed quoted field \"%s\"", field);
		ret = FAIL;
	}

out:
	if (SUCCEED == ret)
	{
		value_out = zbx_strdup(NULL, json.buffer);
		zbx_variant_clear(value);
		zbx_variant_set_str(value, value_out);
	}

	if (1 == hdr_line)
	{
		if (0 == fld_num_max)
			fld_num_max = fld_num;

		for (fld_num = 0; fld_num < fld_num_max; fld_num++)
			zbx_free(field_names[fld_num]);

		zbx_free(field_names);
	}

	zbx_free(field_esc);
	zbx_json_free(&json);

	return ret;
#undef CSV_STATE_FIELD
#undef CSV_STATE_DELIM
#undef CSV_STATE_FIELD_QUOTED
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert XML format value to JSON format                           *
 *                                                                            *
 * Parameters: value  - [IN/OUT] value to process                             *
 *             errmsg - [OUT]                                                 *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_xml_to_json(zbx_variant_t *value, char **errmsg)
{
	char	*json = NULL;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	if (FAIL == zbx_xml_to_json(value->data.str, &json, errmsg))
		return FAIL;

	zbx_variant_clear(value);
	zbx_variant_set_str(value, json);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: replace substrings in string                                      *
 *                                                                            *
 * Parameters: value  - [IN/OUT]  value to process                            *
 *             params - [IN] operation parameters                             *
 *             errmsg - [OUT]                                                 *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	item_preproc_str_replace(zbx_variant_t *value, const char *params, char **errmsg)
{
	size_t		len_search, len_replace;
	const char	*ptr;
	char		*new_string, *search_str, *replace_str;
	int		ret = FAIL;

	if (NULL == (ptr = strchr(params, '\n')))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		*errmsg = zbx_strdup(*errmsg, "cannot find second parameter");
		return FAIL;
	}

	if (0 == (len_search = (size_t)(ptr - params)))
	{
		*errmsg = zbx_strdup(*errmsg, "first parameter is expected");
		return FAIL;
	}

	search_str = (char *)zbx_malloc(NULL, len_search + 1);
	unescape_param(ZBX_PREPROC_STR_REPLACE, params, len_search, search_str);

	len_replace = strlen(ptr + 1);
	replace_str = (char *)zbx_malloc(NULL, len_replace + 1);
	unescape_param(ZBX_PREPROC_STR_REPLACE, ptr + 1, len_replace, replace_str);

	if (SUCCEED == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
	{
		new_string = zbx_string_replace(value->data.str, search_str, replace_str);
		zbx_variant_clear(value);
		zbx_variant_set_str(value, new_string);

		ret = SUCCEED;
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;

	zbx_free(replace_str);
	zbx_free(search_str);

	return ret;
}
