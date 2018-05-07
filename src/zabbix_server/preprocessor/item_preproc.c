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

/* LIBXML2 is used */
#ifdef HAVE_LIBXML2
#	include <libxml/parser.h>
#	include <libxml/tree.h>
#	include <libxml/xpath.h>
#endif

#include "log.h"
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

	if (SUCCEED == item_preproc_multiplier_variant(value_type, value, params, &err))
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
 * Parameters: value   - [IN/OUT] the value to process                        *
 *             ts      - [IN] the value timestamp                             *
 *             op_type - [IN] the operation type                              *
 *             hvalue  - [IN] the item historical data                        *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_delta_float(zbx_variant_t *value, const zbx_timespec_t *ts, unsigned char op_type,
		zbx_item_history_value_t *hvalue)
{
	if (0 == hvalue->timestamp.sec || hvalue->value.data.dbl > value->data.dbl)
		return FAIL;

	switch (op_type)
	{
		case ZBX_PREPROC_DELTA_SPEED:
			if (0 <= zbx_timespec_compare(&hvalue->timestamp, ts))
				return FAIL;

			value->data.dbl = (value->data.dbl - hvalue->value.data.dbl) /
					((ts->sec - hvalue->timestamp.sec) +
						(double)(ts->ns - hvalue->timestamp.ns) / 1000000000);
			break;
		case ZBX_PREPROC_DELTA_VALUE:
			value->data.dbl = value->data.dbl - hvalue->value.data.dbl;
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
 * Parameters: value   - [IN/OUT] the value to process                        *
 *             ts      - [IN] the value timestamp                             *
 *             op_type - [IN] the operation type                              *
 *             hvalue  - [IN] the item historical data                        *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_delta_uint64(zbx_variant_t *value, const zbx_timespec_t *ts, unsigned char op_type,
		zbx_item_history_value_t *hvalue)
{
	if (0 == hvalue->timestamp.sec || hvalue->value.data.ui64 > value->data.ui64)
		return FAIL;

	switch (op_type)
	{
		case ZBX_PREPROC_DELTA_SPEED:
			if (0 <= zbx_timespec_compare(&hvalue->timestamp, ts))
				return FAIL;

			value->data.ui64 = (value->data.ui64 - hvalue->value.data.ui64) /
					((ts->sec - hvalue->timestamp.sec) +
						(double)(ts->ns - hvalue->timestamp.ns) / 1000000000);
			break;
		case ZBX_PREPROC_DELTA_VALUE:
			value->data.ui64 = value->data.ui64 - hvalue->value.data.ui64;
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
 *             history_value - [IN] historical data of item with delta        *
 *                                  preprocessing operation                   *
 *             errmsg        - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_delta(unsigned char value_type, zbx_variant_t *value, const zbx_timespec_t *ts,
		unsigned char op_type, zbx_item_history_value_t *history_value, char **errmsg)
{
	int				ret = FAIL;
	zbx_variant_t			value_num;

	if (FAIL == zbx_item_preproc_convert_value_to_numeric(&value_num, value, value_type, errmsg))
		return FAIL;

	zbx_variant_clear(value);
	zbx_variant_set_variant(value, &value_num);

	if (ZBX_VARIANT_DBL == value->type || ZBX_VARIANT_DBL == history_value->value.type)
	{
		zbx_variant_convert(value, ZBX_VARIANT_DBL);
		zbx_variant_convert(&history_value->value, ZBX_VARIANT_DBL);
		ret = item_preproc_delta_float(value, ts, op_type, history_value);
	}
	else
	{
		zbx_variant_convert(value, ZBX_VARIANT_UI64);
		zbx_variant_convert(&history_value->value, ZBX_VARIANT_UI64);
		ret = item_preproc_delta_uint64(value, ts, op_type, history_value);
	}

	history_value->timestamp = *ts;
	zbx_variant_set_variant(&history_value->value, &value_num);
	zbx_variant_clear(&value_num);

	if (SUCCEED != ret)
		zbx_variant_clear(value);

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
		zbx_item_history_value_t *history_value, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_delta(value_type, value, ts, ZBX_PREPROC_DELTA_VALUE, history_value, &err))
		return SUCCEED;

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
		zbx_item_history_value_t *history_value, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_delta(value_type, value, ts, ZBX_PREPROC_DELTA_SPEED, history_value, &err))
		return SUCCEED;

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
	char	params_raw[ITEM_PREPROC_PARAMS_LEN * 4 + 1];

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
				*errmsg = zbx_strdup(NULL, "invalid value format");
				return FAIL;
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
	char	pattern[ITEM_PREPROC_PARAMS_LEN * 4 + 1], *output, *new_value = NULL;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	zbx_strlcpy(pattern, params, sizeof(pattern));
	if (NULL == (output = strchr(pattern, '\n')))
	{
		*errmsg = zbx_strdup(*errmsg, "cannot find second parameter");
		return FAIL;
	}

	*output++ = '\0';

	if (FAIL == zbx_mregexp_sub(value->data.str, pattern, output, &new_value))
	{
		*errmsg = zbx_dsprintf(*errmsg, "invalid regular expression \"%s\"", pattern);
		return FAIL;
	}

	if (NULL == new_value)
	{
		*errmsg = zbx_dsprintf(*errmsg, "pattern does not match", pattern);
		return FAIL;
	}

	zbx_variant_clear(value);
	zbx_variant_set_str(value, new_value);

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

	*errmsg = zbx_dsprintf(*errmsg, "cannot perform regular expression match on value \"%s\" of type \"%s\": %s",
			zbx_variant_value_desc(value), zbx_variant_type_desc(value), err);

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
				del_zeroes(buffer);
				zbx_variant_set_str(value, zbx_strdup(NULL, buffer));
				ret = SUCCEED;
			}
			else
				*errmsg = zbx_dsprintf(*errmsg, "Invalid numeric value");
			break;
		default:
			*errmsg = zbx_dsprintf(*errmsg, "Unknown result");
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
 * Function: zbx_item_preproc                                                 *
 *                                                                            *
 * Purpose: execute preprocessing operation                                   *
 *                                                                            *
 * Parameters: value_type    - [IN] the item value type                       *
 *             value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             op            - [IN] the preprocessing operation to execute    *
 *             history_value - [IN/OUT] last historical data of items with    *
 *                                      delta type preprocessing operation    *
 *             errmsg        - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
int	zbx_item_preproc(unsigned char value_type, zbx_variant_t *value, const zbx_timespec_t *ts,
		const zbx_preproc_op_t *op, zbx_item_history_value_t *history_value, char **errmsg)
{
	switch (op->type)
	{
		case ZBX_PREPROC_MULTIPLIER:
			return item_preproc_multiplier(value_type, value, op->params, errmsg);
		case ZBX_PREPROC_RTRIM:
			return item_preproc_rtrim(value, op->params, errmsg);
		case ZBX_PREPROC_LTRIM:
			return item_preproc_ltrim(value, op->params, errmsg);
		case ZBX_PREPROC_TRIM:
			return item_preproc_lrtrim(value, op->params, errmsg);
		case ZBX_PREPROC_REGSUB:
			return item_preproc_regsub(value, op->params, errmsg);
		case ZBX_PREPROC_BOOL2DEC:
			return item_preproc_bool2dec(value, errmsg);
		case ZBX_PREPROC_OCT2DEC:
			return item_preproc_oct2dec(value, errmsg);
		case ZBX_PREPROC_HEX2DEC:
			return item_preproc_hex2dec(value, errmsg);
		case ZBX_PREPROC_DELTA_VALUE:
			return item_preproc_delta_value(value_type, value, ts, history_value, errmsg);
		case ZBX_PREPROC_DELTA_SPEED:
			return item_preproc_delta_speed(value_type, value, ts, history_value, errmsg);
		case ZBX_PREPROC_XPATH:
			return item_preproc_xpath(value, op->params, errmsg);
		case ZBX_PREPROC_JSONPATH:
			return item_preproc_jsonpath(value, op->params, errmsg);
	}

	*errmsg = zbx_dsprintf(*errmsg, "unknown preprocessing operation");

	return FAIL;
}
