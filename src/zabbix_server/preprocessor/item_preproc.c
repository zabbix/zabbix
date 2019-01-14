/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

/* LIBXML2 is used */
#ifdef HAVE_LIBXML2
#	include <libxml/parser.h>
#	include <libxml/tree.h>
#	include <libxml/xpath.h>
#endif

#include "zbxregexp.h"
#include "zbxjson.h"

#include "item_preproc.h"

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_numeric_type_hint                                   *
 *                                                                            *
 * Purpose: returns numeric type hint based on item value type                *
 *                                                                            *
 * Parameters: value_type - [IN] the item value type                          *
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
 * Function: item_preproc_convert_value                                       *
 *                                                                            *
 * Purpose: convert variant value to the requested type                       *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to convert                         *
 *             type   - [IN] the new value type                               *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was converted successfully               *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_convert_value(zbx_variant_t *value, unsigned char type, char **errmsg)
{
	if (FAIL == zbx_variant_convert(value, type))
	{
		*errmsg = zbx_strdup(*errmsg, "cannot convert value");
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_item_preproc_convert_value_to_numeric                        *
 *                                                                            *
 * Purpose: converts variant value to numeric                                 *
 *                                                                            *
 * Parameters: value_num  - [OUT] the converted value                         *
 *             value      - [IN] the value to convert                         *
 *             value_type - [IN] item value type                              *
 *             errmsg     - [OUT] error message                               *
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
			zbx_variant_set_variant(value_num, value);
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
 * Function: item_preproc_multiplier_variant                                  *
 *                                                                            *
 * Purpose: execute custom multiplier preprocessing operation on variant      *
 *          value type                                                        *
 *                                                                            *
 * Parameters: value_type - [IN] the item type                                *
 *             value      - [IN/OUT] the value to process                     *
 *             params     - [IN] the operation parameters                     *
 *             errmsg     - [OUT] error message                               *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_multiplier_variant(unsigned char value_type, zbx_variant_t *value, const char *params,
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
			if (SUCCEED == is_uint64(params, &multiplier_ui64))
				value_ui64 = value_num.data.ui64 * multiplier_ui64;
			else
				value_ui64 = (double)value_num.data.ui64 * atof(params);

			zbx_variant_clear(value);
			zbx_variant_set_ui64(value, value_ui64);
			break;
	}

	zbx_variant_clear(&value_num);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_multiplier                                          *
 *                                                                            *
 * Purpose: execute custom multiplier preprocessing operation                 *
 *                                                                            *
 * Parameters: value_type - [IN] the item type                                *
 *             value      - [IN/OUT] the value to process                     *
 *             params     - [IN] the operation parameters                     *
 *             errmsg     - [OUT] error message                               *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_multiplier(unsigned char value_type, zbx_variant_t *value, const char *params,
		char **errmsg)
{
	char	*err = NULL;

	if (FAIL == is_double(params))
		err = zbx_dsprintf(NULL, "a numerical value is expected");
	else if (SUCCEED == item_preproc_multiplier_variant(value_type, value, params, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "cannot apply multiplier \"%s\" to value \"%s\" of type \"%s\": %s",
			params, zbx_variant_value_desc(value), zbx_variant_type_desc(value), err);
	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_delta_float                                         *
 *                                                                            *
 * Purpose: execute delta type preprocessing operation                        *
 *                                                                            *
 * Parameters: value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             op_type       - [IN] the operation type                        *
 *             history_value - [IN] the item historical data                  *
 *             history_ts    - [IN] the historical data timestamp             *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_delta_float(zbx_variant_t *value, const zbx_timespec_t *ts, unsigned char op_type,
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
 * Function: item_preproc_delta_uint64                                        *
 *                                                                            *
 * Purpose: execute delta type preprocessing operation                        *
 *                                                                            *
 * Parameters: value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             op_type       - [IN] the operation type                        *
 *             history_value - [IN] the item historical data                  *
 *             history_ts    - [IN] the historical data timestamp             *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_delta_uint64(zbx_variant_t *value, const zbx_timespec_t *ts, unsigned char op_type,
		const zbx_variant_t *history_value, const zbx_timespec_t *history_ts)
{
	if (0 == history_ts->sec || history_value->data.ui64 > value->data.ui64)
		return FAIL;

	switch (op_type)
	{
		case ZBX_PREPROC_DELTA_SPEED:
			if (0 <= zbx_timespec_compare(history_ts, ts))
				return FAIL;

			value->data.ui64 = (value->data.ui64 - history_value->data.ui64) /
					((ts->sec - history_ts->sec) +
						(double)(ts->ns - history_ts->ns) / 1000000000);
			break;
		case ZBX_PREPROC_DELTA_VALUE:
			value->data.ui64 = value->data.ui64 - history_value->data.ui64;
			break;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_delta                                               *
 *                                                                            *
 * Purpose: execute delta type preprocessing operation                        *
 *                                                                            *
 * Parameters: value_type    - [IN] the item value type                       *
 *             value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             op_type       - [IN] the operation type                        *
 *             history_value - [IN/OUT] the historical (previuous) data       *
 *             history_ts    - [IN/OUT] the timestamp of the historical data  *
 *             errmsg        - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_delta(unsigned char value_type, zbx_variant_t *value, const zbx_timespec_t *ts,
		unsigned char op_type, zbx_variant_t *history_value, zbx_timespec_t *history_ts, char **errmsg)
{
	int				ret = FAIL;
	zbx_variant_t			value_num;

	if (FAIL == zbx_item_preproc_convert_value_to_numeric(&value_num, value, value_type, errmsg))
		return FAIL;

	zbx_variant_clear(value);

	if (ZBX_VARIANT_NONE != history_value->type)
	{
		zbx_variant_set_variant(value, &value_num);

		if (ZBX_VARIANT_DBL == value->type || ZBX_VARIANT_DBL == history_value->type)
		{
			zbx_variant_convert(value, ZBX_VARIANT_DBL);
			zbx_variant_convert(history_value, ZBX_VARIANT_DBL);
			ret = item_preproc_delta_float(value, ts, op_type, history_value, history_ts);
		}
		else
		{
			zbx_variant_convert(value, ZBX_VARIANT_UI64);
			zbx_variant_convert(history_value, ZBX_VARIANT_UI64);
			ret = item_preproc_delta_uint64(value, ts, op_type, history_value, history_ts);
		}

		if (SUCCEED != ret)
			zbx_variant_clear(value);
	}

	*history_ts = *ts;
	zbx_variant_set_variant(history_value, &value_num);
	zbx_variant_clear(&value_num);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_delta_value                                         *
 *                                                                            *
 * Purpose: execute delta (simple change) preprocessing operation             *
 *                                                                            *
 * Parameters: value_type    - [IN] the item value type                       *
 *             value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             history_value - [IN] historical data of item with delta        *
 *                                  preprocessing operation                   *
 *             errmsg        - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_delta_value(unsigned char value_type, zbx_variant_t *value, const zbx_timespec_t *ts,
		zbx_variant_t *history_value, zbx_timespec_t *history_ts, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_delta(value_type, value, ts, ZBX_PREPROC_DELTA_VALUE, history_value, history_ts,
			&err))
	{
		return SUCCEED;
	}

	*errmsg = zbx_dsprintf(*errmsg, "cannot calculate delta (simple change) for value \"%s\" of type"
				" \"%s\": %s", zbx_variant_value_desc(value), zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_delta_speed                                         *
 *                                                                            *
 * Purpose: execute delta (speed per second) preprocessing operation          *
 *                                                                            *
 * Parameters: value_type    - [IN] the item value type                       *
 *             value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             history_value - [IN] historical data of item with delta        *
 *                                  preprocessing operation                   *
 *             errmsg        - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_delta_speed(unsigned char value_type, zbx_variant_t *value, const zbx_timespec_t *ts,
		zbx_variant_t *history_value, zbx_timespec_t *history_ts, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_delta(value_type, value, ts, ZBX_PREPROC_DELTA_SPEED, history_value, history_ts,
			&err))
	{
		return SUCCEED;
	}

	*errmsg = zbx_dsprintf(*errmsg, "cannot calculate delta (speed per second) for value \"%s\" of type"
				" \"%s\": %s", zbx_variant_value_desc(value), zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: unescape_trim_params                                             *
 *                                                                            *
 * Purpose: unescapes string used for trim operation parameter                *
 *                                                                            *
 * Parameters: in  - [IN] the string to unescape                              *
 *             out - [OUT] the unescaped string                               *
 *                                                                            *
 ******************************************************************************/
static void	unescape_trim_params(const char *in, char *out)
{
	for (; '\0' != *in; in++, out++)
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
 * Function: item_preproc_trim                                                *
 *                                                                            *
 * Purpose: execute trim type preprocessing operation                         *
 *                                                                            *
 * Parameters: value   - [IN/OUT] the value to process                        *
 *             op_type - [IN] the operation type                              *
 *             params  - [IN] the characters to trim                          *
 *             errmsg  - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - the value was trimmed successfully                 *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int item_preproc_trim(zbx_variant_t *value, unsigned char op_type, const char *params, char **errmsg)
{
	char	params_raw[ITEM_PREPROC_PARAMS_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	unescape_trim_params(params, params_raw);

	if (ZBX_PREPROC_LTRIM == op_type || ZBX_PREPROC_TRIM == op_type)
		zbx_ltrim(value->data.str, params_raw);

	if (ZBX_PREPROC_RTRIM == op_type || ZBX_PREPROC_TRIM == op_type)
		zbx_rtrim(value->data.str, params_raw);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_rtrim                                               *
 *                                                                            *
 * Purpose: execute right trim preprocessing operation                        *
 *                                                                            *
 * Parameters: value   - [IN/OUT] the value to process                        *
 *             params  - [IN] the characters to trim                          *
 *             errmsg  - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - the value was trimmed successfully                 *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int item_preproc_rtrim(zbx_variant_t *value, const char *params, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_trim(value, ZBX_PREPROC_RTRIM, params, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "cannot perform right trim of \"%s\" for value \"%s\" of type \"%s\": %s",
			params, zbx_variant_value_desc(value), zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_ltrim                                               *
 *                                                                            *
 * Purpose: execute left trim preprocessing operation                         *
 *                                                                            *
 * Parameters: value   - [IN/OUT] the value to process                        *
 *             params  - [IN] the characters to trim                          *
 *             errmsg  - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - the value was trimmed successfully                 *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int item_preproc_ltrim(zbx_variant_t *value, const char *params, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_trim(value, ZBX_PREPROC_LTRIM, params, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "cannot perform left trim of \"%s\" for value \"%s\" of type \"%s\": %s",
			params, zbx_variant_value_desc(value), zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_lrtrim                                              *
 *                                                                            *
 * Purpose: execute left and right trim preprocessing operation               *
 *                                                                            *
 * Parameters: value   - [IN/OUT] the value to process                        *
 *             params  - [IN] the characters to trim                          *
 *             errmsg  - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - the value was trimmed successfully                 *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int item_preproc_lrtrim(zbx_variant_t *value, const char *params, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_trim(value, ZBX_PREPROC_TRIM, params, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "cannot perform trim of \"%s\" for value \"%s\" of type \"%s\": %s",
			params, zbx_variant_value_desc(value), zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_2dec                                                *
 *                                                                            *
 * Purpose: execute decimal value conversion operation                        *
 *                                                                            *
 * Parameters: value   - [IN/OUT] the value to convert                        *
 *             op_type - [IN] the operation type                              *
 *             errmsg  - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - the value was converted successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_2dec(zbx_variant_t *value, unsigned char op_type, char **errmsg)
{
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
			ZBX_OCT2UINT64(value_ui64, value->data.str);
			break;
		case ZBX_PREPROC_HEX2DEC:
			if (SUCCEED != is_uhex(value->data.str))
			{
				if (SUCCEED != is_hex_string(value->data.str))
				{
					*errmsg = zbx_strdup(NULL, "invalid value format");
					return FAIL;
				}

				zbx_remove_chars(value->data.str, " \n");
			}
			ZBX_HEX2UINT64(value_ui64, value->data.str);
			break;
		default:
			*errmsg = zbx_strdup(NULL, "unknown operation type");
			return FAIL;
	}

	zbx_variant_clear(value);
	zbx_variant_set_ui64(value, value_ui64);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_bool2dec                                            *
 *                                                                            *
 * Purpose: execute boolean to decimal value conversion operation             *
 *                                                                            *
 * Parameters: value   - [IN/OUT] the value to convert                        *
 *             errmsg  - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - the value was converted successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_bool2dec(zbx_variant_t *value, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_2dec(value, ZBX_PREPROC_BOOL2DEC, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "cannot convert value \"%s\" of type \"%s\" from boolean format: %s",
			zbx_variant_value_desc(value), zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_oct2dec                                             *
 *                                                                            *
 * Purpose: execute octal to decimal value conversion operation               *
 *                                                                            *
 * Parameters: value   - [IN/OUT] the value to convert                        *
 *             errmsg  - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - the value was converted successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_oct2dec(zbx_variant_t *value, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_2dec(value, ZBX_PREPROC_OCT2DEC, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "cannot convert value \"%s\" of type \"%s\" from octal format: %s",
			zbx_variant_value_desc(value), zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_hex2dec                                             *
 *                                                                            *
 * Purpose: execute hexadecimal to decimal value conversion operation         *
 *                                                                            *
 * Parameters: value   - [IN/OUT] the value to convert                        *
 *             errmsg  - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - the value was converted successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_hex2dec(zbx_variant_t *value, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_2dec(value, ZBX_PREPROC_HEX2DEC, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "cannot convert value \"%s\" of type \"%s\" from hexadecimal format: %s",
			zbx_variant_value_desc(value), zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_regsub_op                                           *
 *                                                                            *
 * Purpose: execute regular expression substitution operation                 *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_regsub_op(zbx_variant_t *value, const char *params, char **errmsg)
{
	char		pattern[ITEM_PREPROC_PARAMS_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
	char		*output, *new_value = NULL;
	const char	*regex_error;
	zbx_regexp_t	*regex = NULL;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	zbx_strlcpy(pattern, params, sizeof(pattern));

	if (NULL == (output = strchr(pattern, '\n')))
	{
		*errmsg = zbx_strdup(*errmsg, "cannot find second parameter");
		return FAIL;
	}

	*output++ = '\0';

	if (FAIL == zbx_regexp_compile_ext(pattern, &regex, 0, &regex_error))	/* PCRE_MULTILINE is not used here */
	{
		*errmsg = zbx_dsprintf(*errmsg, "invalid regular expression: %s", regex_error);
		return FAIL;
	}

	if (FAIL == zbx_mregexp_sub_precompiled(value->data.str, regex, output, &new_value))
	{
		*errmsg = zbx_strdup(*errmsg, "pattern does not match");
		zbx_regexp_free(regex);
		return FAIL;
	}

	zbx_variant_clear(value);
	zbx_variant_set_str(value, new_value);

	zbx_regexp_free(regex);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_regsub                                              *
 *                                                                            *
 * Purpose: execute regular expression substitution operation                 *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_regsub(zbx_variant_t *value, const char *params, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_regsub_op(value, params, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "cannot perform regular expression match: %s, type \"%s\", value \"%s\"",
			err, zbx_variant_type_desc(value), zbx_variant_value_desc(value));

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_jsonpath_op                                         *
 *                                                                            *
 * Purpose: execute jsonpath query                                            *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_jsonpath_op(zbx_variant_t *value, const char *params, char **errmsg)
{
	struct zbx_json_parse	jp, jp_out;
	char			*data = NULL;
	size_t			data_alloc = 0;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	if (FAIL == zbx_json_open(value->data.str, &jp) || FAIL == zbx_json_path_open(&jp, params, &jp_out))
	{
		*errmsg = zbx_strdup(*errmsg, zbx_json_strerror());
		return FAIL;
	}

	zbx_json_value_dyn(&jp_out, &data, &data_alloc);
	zbx_variant_clear(value);
	zbx_variant_set_str(value, data);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_jsonpath                                            *
 *                                                                            *
 * Purpose: execute jsonpath query                                            *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_jsonpath(zbx_variant_t *value, const char *params, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_jsonpath_op(value, params, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "cannot extract value from json by path \"%s\": %s", params, err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_xpath_op                                            *
 *                                                                            *
 * Purpose: execute xpath query                                               *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_xpath_op(zbx_variant_t *value, const char *params, char **errmsg)
{
#ifndef HAVE_LIBXML2
	ZBX_UNUSED(value);
	ZBX_UNUSED(params);
	*errmsg = zbx_dsprintf(*errmsg, "Zabbix was compiled without libxml2 support");
	return FAIL;
#else
	xmlDoc		*doc = NULL;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlErrorPtr	pErr;
	xmlBufferPtr	xmlBufferLocal;
	int		ret = FAIL, i;
	char		buffer[32], *ptr;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	if (NULL == (doc = xmlReadMemory(value->data.str, strlen(value->data.str), "noname.xml", NULL, 0)))
	{
		if (NULL != (pErr = xmlGetLastError()))
			*errmsg = zbx_dsprintf(*errmsg, "cannot parse xml value: %s", pErr->message);
		else
			*errmsg = zbx_strdup(*errmsg, "cannot parse xml value");
		return FAIL;
	}

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)params, xpathCtx)))
	{
		pErr = xmlGetLastError();
		*errmsg = zbx_dsprintf(*errmsg, "cannot parse xpath: %s", pErr->message);
		goto out;
	}

	switch (xpathObj->type)
	{
		case XPATH_NODESET:
			xmlBufferLocal = xmlBufferCreate();

			if (0 == xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
			{
				nodeset = xpathObj->nodesetval;
				for (i = 0; i < nodeset->nodeNr; i++)
					xmlNodeDump(xmlBufferLocal, doc, nodeset->nodeTab[i], 0, 0);
			}
			zbx_variant_clear(value);
			zbx_variant_set_str(value, zbx_strdup(NULL, (const char *)xmlBufferLocal->content));

			xmlBufferFree(xmlBufferLocal);
			ret = SUCCEED;
			break;
		case XPATH_STRING:
			zbx_variant_clear(value);
			zbx_variant_set_str(value, zbx_strdup(NULL, (const char *)xpathObj->stringval));
			ret = SUCCEED;
			break;
		case XPATH_BOOLEAN:
			zbx_variant_clear(value);
			zbx_variant_set_str(value, zbx_dsprintf(NULL, "%d", xpathObj->boolval));
			ret = SUCCEED;
			break;
		case XPATH_NUMBER:
			zbx_variant_clear(value);
			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_DBL, xpathObj->floatval);

			/* check for nan/inf values - isnan(), isinf() is not supported by c89/90    */
			/* so simply check the if the result starts with digit (accounting for -inf) */
			if (*(ptr = buffer) == '-')
				ptr++;
			if (0 != isdigit(*ptr))
			{
				del_zeros(buffer);
				zbx_variant_set_str(value, zbx_strdup(NULL, buffer));
				ret = SUCCEED;
			}
			else
				*errmsg = zbx_strdup(*errmsg, "Invalid numeric value");
			break;
		default:
			*errmsg = zbx_strdup(*errmsg, "Unknown result");
			break;
	}
out:
	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
	xmlFreeDoc(doc);

	return ret;
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_xpath                                               *
 *                                                                            *
 * Purpose: execute xpath query                                               *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_xpath(zbx_variant_t *value, const char *params, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_xpath_op(value, params, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "cannot extract XML value with xpath \"%s\": %s", params, err);
	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_validate_range                                      *
 *                                                                            *
 * Purpose: validates value to be within the specified range                  *
 * Parameters: value_type - [IN] the item type                                *
 *             value      - [IN/OUT] the value to process                     *
 *             params     - [IN] the operation parameters                     *
 *             errmsg     - [OUT] error message                               *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_validate_range(unsigned char value_type, const zbx_variant_t *value, const char *params,
		char **errmsg)
{
	zbx_variant_t	value_num;
	char		min[ITEM_PREPROC_PARAMS_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1], *max;
	zbx_variant_t	range_min, range_max;
	int		ret = FAIL;

	if (FAIL == zbx_item_preproc_convert_value_to_numeric(&value_num, value, value_type, errmsg))
		return FAIL;

	zbx_variant_set_none(&range_min);
	zbx_variant_set_none(&range_max);

	zbx_strlcpy(min, params, sizeof(min));
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
		*errmsg = zbx_dsprintf(*errmsg, "range validation error with value '%s'",
				zbx_variant_value_desc(&value_num));
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_variant_clear(&value_num);
	zbx_variant_clear(&range_min);
	zbx_variant_clear(&range_max);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_validate_regex                                      *
 *                                                                            *
 * Purpose: validates value to match regular expression                       *
 * Parameters: value_type - [IN] the item type                                *
 *             value      - [IN/OUT] the value to process                     *
 *             params     - [IN] the operation parameters                     *
 *             errmsg     - [OUT] error message                               *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_validate_regex(const zbx_variant_t *value, const char *params, char **errmsg)
{
	zbx_variant_t	value_str;
	int		ret = FAIL;
	zbx_regexp_t	*regex;
	const char	*errptr = NULL;

	zbx_variant_set_variant(&value_str, value);

	if (FAIL == zbx_variant_convert(&value_str, ZBX_VARIANT_STR))
	{
		*errmsg = zbx_strdup(*errmsg, "cannot convert value to string");
		goto out;
	}

	if (FAIL == zbx_regexp_compile(params, &regex, &errptr))
	{
		*errmsg = zbx_dsprintf(*errmsg, "invalid regular expression pattern: %s", errptr);
		goto out;
	}

	if (0 != zbx_regexp_match_precompiled(value_str.data.str, regex))
	{
		*errmsg = zbx_dsprintf(*errmsg, "regular expression validation error with value '%s'",
				zbx_variant_value_desc(&value_str));
	}
	else
		ret = SUCCEED;

	zbx_regexp_free(regex);
out:
	zbx_variant_clear(&value_str);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_validate_not_regex                                  *
 *                                                                            *
 * Purpose: validates value to not match regular expression                   *
 * Parameters: value_type - [IN] the item type                                *
 *             value      - [IN/OUT] the value to process                     *
 *             params     - [IN] the operation parameters                     *
 *             errmsg     - [OUT] error message                               *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_validate_not_regex(const zbx_variant_t *value, const char *params, char **errmsg)
{
	zbx_variant_t	value_str;
	int		ret = FAIL;
	zbx_regexp_t	*regex;
	const char	*errptr = NULL;

	zbx_variant_set_variant(&value_str, value);

	if (FAIL == zbx_variant_convert(&value_str, ZBX_VARIANT_STR))
	{
		*errmsg = zbx_strdup(*errmsg, "cannot convert value to string");
		goto out;
	}

	if (FAIL == zbx_regexp_compile(params, &regex, &errptr))
	{
		*errmsg = zbx_dsprintf(*errmsg, "invalid regular expression pattern: %s", errptr);
		goto out;
	}

	if (0 == zbx_regexp_match_precompiled(value_str.data.str, regex))
	{
		*errmsg = zbx_dsprintf(*errmsg, "regular expression validation error with value '%s'",
				zbx_variant_value_desc(&value_str));
	}
	else
		ret = SUCCEED;

	zbx_regexp_free(regex);
out:
	zbx_variant_clear(&value_str);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_get_error_from_json                                 *
 *                                                                            *
 * Purpose: checks for presence of error field in json data                   *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message part                              *
 *             error  - [OUT] direct error message                            *
 *                                                                            *
 * Return value: FAIL - the specified error field exists                      *
 *               SUCCEED - otherwise                                          *
 *                                                                            *
 * Comments: This preprocessing step is used to check if the returned data    *
 *           contains explicit (API related) error message and sets it as     *
 *           error.                                                           *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_get_error_from_json(const zbx_variant_t *value, const char *params, char **errmsg,
		char **error)
{
	zbx_variant_t		value_str;
	char			err[MAX_STRING_LEN];
	int			ret = SUCCEED;
	struct zbx_json_parse	jp, jp_out;
	size_t			data_alloc = 0;

	if (FAIL == zbx_json_path_check(params, err, sizeof(err)))
	{
		*errmsg = zbx_strdup(*errmsg, err);
		return FAIL;
	}

	zbx_variant_set_variant(&value_str, value);

	if (FAIL == item_preproc_convert_value(&value_str, ZBX_VARIANT_STR, errmsg))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		zbx_free(*errmsg);
		goto out;
	}

	if (FAIL == zbx_json_open(value->data.str, &jp) || FAIL == zbx_json_path_open(&jp, params, &jp_out))
		goto out;

	zbx_free(*error);
	zbx_json_value_dyn(&jp_out, error, &data_alloc);

	zbx_lrtrim(*error, " \t\n\r");
	if ('\0' == **error)
	{
		zbx_free(*error);
		goto out;
	}

	ret = FAIL;
out:
	zbx_variant_clear(&value_str);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_get_error_from_xml                                  *
 *                                                                            *
 * Purpose: checks for presence of error field in XML data                    *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: FAIL - the specified error field exists                      *
 *               SUCCEED - otherwise                                          *
 *                                                                            *
 * Comments: This preprocessing step is used to check if the returned data    *
 *           contains explicit (API related) error message and sets it as     *
 *           error.                                                           *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_get_error_from_xml(const zbx_variant_t *value, const char *params, char **errmsg,
		char **error)
{
#ifndef HAVE_LIBXML2
	ZBX_UNUSED(value);
	ZBX_UNUSED(params);
	ZBX_UNUSED(errmsg);
	*error = zbx_dsprintf(*error, "Zabbix was compiled without libxml2 support");
	return FAIL;
#else
	zbx_variant_t		value_str;
	int			ret = SUCCEED, i;
	xmlDoc			*doc = NULL;
	xmlXPathContext		*xpathCtx = NULL;
	xmlXPathObject		*xpathObj = NULL;
	xmlErrorPtr		pErr;
	xmlBufferPtr		xmlBufferLocal;

	zbx_variant_set_variant(&value_str, value);

	if (FAIL == item_preproc_convert_value(&value_str, ZBX_VARIANT_STR, errmsg))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		zbx_free(*errmsg);
		goto out;
	}

	if (NULL == (doc = xmlReadMemory(value_str.data.str, strlen(value_str.data.str), "noname.xml", NULL, 0)))
		goto out;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)params, xpathCtx)))
	{
		pErr = xmlGetLastError();
		*errmsg = zbx_dsprintf(*errmsg, "cannot parse xpath: %s", pErr->message);
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

	zbx_lrtrim(*error, " \t\n\r");
	if ('\0' == **error)
	{
		zbx_free(*error);
		goto out;
	}

	ret = FAIL;
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
 * Function: item_preproc_get_error_from_regex                                *
 *                                                                            *
 * Purpose: checks for presence of error pattern matching regular expression  *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message part                              *
 *             error  - [OUT] direct error message                            *
 *                                                                            *
 * Return value: FAIL - the specified error field exists                      *
 *               SUCCEED - otherwise                                          *
 *                                                                            *
 * Comments: This preprocessing step is used to check if the returned data    *
 *           contains explicit (API related) error message and sets it as     *
 *           error.                                                           *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_get_error_from_regex(const zbx_variant_t *value, const char *params, char **errmsg,
		char **error)
{
	zbx_variant_t	value_str;
	int		ret = SUCCEED;
	char		pattern[ITEM_PREPROC_PARAMS_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1], *output;

	zbx_variant_set_variant(&value_str, value);

	if (FAIL == item_preproc_convert_value(&value_str, ZBX_VARIANT_STR, errmsg))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		zbx_free(*errmsg);
		goto out;
	}

	zbx_strlcpy(pattern, params, sizeof(pattern));
	if (NULL == (output = strchr(pattern, '\n')))
	{
		*errmsg = zbx_strdup(*errmsg, "cannot find second parameter");
		ret = FAIL;
		goto out;
	}

	*output++ = '\0';

	if (FAIL == zbx_mregexp_sub(value_str.data.str, pattern, output, error))
	{
		*errmsg = zbx_dsprintf(*errmsg, "invalid regular expression \"%s\"", pattern);
		ret = FAIL;
		goto out;
	}

	if (NULL != *error)
	{
		zbx_lrtrim(*error, " \t\n\r");
		if ('\0' == **error)
		{
			zbx_free(*error);
			goto out;
		}

		ret = FAIL;
	}
out:
	zbx_variant_clear(&value_str);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_throttle_value                                      *
 *                                                                            *
 * Purpose: throttles value by suppressing identical values                   *
 *                                                                            *
 * Parameters: value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             history_value - [IN] historical data of item with delta        *
 *                                  preprocessing operation                   *
 *             errmsg        - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_throttle_value(zbx_variant_t *value, const zbx_timespec_t *ts,
		zbx_variant_t *history_value, zbx_timespec_t *history_ts)
{
	int	ret;

	ret = zbx_variant_compare(value, history_value);

	/* a byte copy of history value is made before and will be cleared at the end, */
	/* so it can be overwritten without clearing here                              */
	zbx_variant_set_variant(history_value, value);

	if (0 == ret)
		zbx_variant_clear(value);
	else
		*history_ts = *ts;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: item_preproc_throttle_timed_value                                *
 *                                                                            *
 * Purpose: throttles value by suppressing identical values                   *
 *                                                                            *
 * Parameters: value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             params        - [IN] the throttle period                       *
 *             history_value - [IN] historical data of item with delta        *
 *                                  preprocessing operation                   *
 *             errmsg        - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_throttle_timed_value(zbx_variant_t *value, const zbx_timespec_t *ts, const char *params,
		zbx_variant_t *history_value, zbx_timespec_t *history_ts, char **errmsg)
{
	int	ret, timeout, period = 0;

	if (FAIL == is_time_suffix(params, &timeout, strlen(params)))
	{
		*errmsg = zbx_dsprintf(*errmsg, "invalid time period: %s", params);
		zbx_variant_clear(history_value);
		return FAIL;
	}

	ret = zbx_variant_compare(value, history_value);

	/* a byte copy of history value is made before and will be cleared at the end, */
	/* so it can be overwritten without clearing here                              */
	zbx_variant_set_variant(history_value, value);

	if (ZBX_VARIANT_NONE != history_value->type)
		period = ts->sec - history_ts->sec;

	if (0 == ret && period < timeout )
		zbx_variant_clear(value);
	else
		*history_ts = *ts;

	return SUCCEED;
}
/******************************************************************************
 *                                                                            *
 * Function: zbx_item_preproc                                                 *
 *                                                                            *
 * Purpose: execute preprocessing operation                                   *
 *                                                                            *
 * Parameters: index         - [IN] the preprocessing step index              *
 *             value_type    - [IN] the item value type                       *
 *             value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             op            - [IN] the preprocessing operation to execute    *
 *             history_value - [IN/OUT] last historical data of items with    *
 *                                      delta type preprocessing operation    *
 *             error        - [OUT] error message                             *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
int	zbx_item_preproc(int index, unsigned char value_type, zbx_variant_t *value, const zbx_timespec_t *ts,
		const zbx_preproc_op_t *op, zbx_variant_t *history_value, zbx_timespec_t *history_ts, char **error)
{
	int	ret;
	char	*errmsg = NULL;

	switch (op->type)
	{
		case ZBX_PREPROC_MULTIPLIER:
			ret = item_preproc_multiplier(value_type, value, op->params, &errmsg);
			break;
		case ZBX_PREPROC_RTRIM:
			ret = item_preproc_rtrim(value, op->params, &errmsg);
			break;
		case ZBX_PREPROC_LTRIM:
			ret = item_preproc_ltrim(value, op->params, &errmsg);
			break;
		case ZBX_PREPROC_TRIM:
			ret = item_preproc_lrtrim(value, op->params, &errmsg);
			break;
		case ZBX_PREPROC_REGSUB:
			ret = item_preproc_regsub(value, op->params, &errmsg);
			break;
		case ZBX_PREPROC_BOOL2DEC:
			ret = item_preproc_bool2dec(value, &errmsg);
			break;
		case ZBX_PREPROC_OCT2DEC:
			ret = item_preproc_oct2dec(value, &errmsg);
			break;
		case ZBX_PREPROC_HEX2DEC:
			ret = item_preproc_hex2dec(value, &errmsg);
			break;
		case ZBX_PREPROC_DELTA_VALUE:
			ret = item_preproc_delta_value(value_type, value, ts, history_value, history_ts, &errmsg);
			break;
		case ZBX_PREPROC_DELTA_SPEED:
			ret = item_preproc_delta_speed(value_type, value, ts, history_value, history_ts, &errmsg);
			break;
		case ZBX_PREPROC_XPATH:
			ret = item_preproc_xpath(value, op->params, &errmsg);
			break;
		case ZBX_PREPROC_JSONPATH:
			ret = item_preproc_jsonpath(value, op->params, &errmsg);
			break;
		case ZBX_PREPROC_VALIDATE_RANGE:
			ret = item_preproc_validate_range(value_type, value, op->params, &errmsg);
			break;
		case ZBX_PREPROC_VALIDATE_REGEX:
			ret = item_preproc_validate_regex(value, op->params, &errmsg);
			break;
		case ZBX_PREPROC_VALIDATE_NOT_REGEX:
			ret = item_preproc_validate_not_regex(value, op->params, &errmsg);
			break;
		case ZBX_PREPROC_ERROR_FIELD_JSON:
			ret = item_preproc_get_error_from_json(value, op->params, &errmsg, error);
			break;
		case ZBX_PREPROC_ERROR_FIELD_XML:
			ret = item_preproc_get_error_from_xml(value, op->params, &errmsg, error);
			break;
		case ZBX_PREPROC_ERROR_FIELD_REGEX:
			ret = item_preproc_get_error_from_regex(value, op->params, &errmsg, error);
			break;
		case ZBX_PREPROC_THROTTLE_VALUE:
			ret = item_preproc_throttle_value(value, ts, history_value, history_ts);
			break;
		case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
			ret = item_preproc_throttle_timed_value(value, ts, op->params, history_value, history_ts,
					&errmsg);
			break;
		default:
			errmsg = zbx_dsprintf(NULL, "unknown preprocessing operation");
			ret = FAIL;
	}

	if (SUCCEED == ret)
		return SUCCEED;

	switch (op->error_handler)
	{
		case ZBX_PREPROC_FAIL_DEFAULT:
			/* if errmsg is NULL then error was set directly by preprocessing step */
			if (NULL != errmsg)
				*error = zbx_dsprintf(*error, "Item preprocessing step #%d failed: %s", index, errmsg);
			break;
		case ZBX_PREPROC_FAIL_DISCARD_VALUE:
			zbx_variant_clear(value);
			ret = SUCCEED;
			break;
		case ZBX_PREPROC_FAIL_SET_VALUE:
			zbx_variant_clear(value);
			zbx_variant_set_str(value, zbx_strdup(NULL, op->error_handler_params));
			ret = SUCCEED;
			break;
		case ZBX_PREPROC_FAIL_SET_ERROR:
			*error = zbx_strdup(*error, op->error_handler_params);
			break;
	}

	zbx_free(errmsg);

	return ret;
}
