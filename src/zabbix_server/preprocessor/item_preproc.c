/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "item_preproc.h"

#include "../db_lengths.h"
#include "zbxregexp.h"
#include "log.h"
#include "zbxembed.h"
#include "zbxprometheus.h"

#include "zbxxml.h"
#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#endif

#include "preproc_history.h"

extern zbx_es_t	es_engine;
/******************************************************************************
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
		*errmsg = zbx_dsprintf(*errmsg, "cannot convert value to %s", zbx_get_variant_type_desc(type));
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
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
 * Purpose: executes during notsupported item preprocessing                   *
 *                                                                            *
 * Return value: FAIL - for further error handling                            *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_validate_notsupport(char **errmsg)
{
	*errmsg = zbx_dsprintf(*errmsg, "item is not supported");
	return FAIL;
}

/******************************************************************************
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
	char	buffer[MAX_STRING_LEN];
	char	*err = NULL;

	zbx_strlcpy(buffer, params, sizeof(buffer));

	zbx_trim_float(buffer);

	if (FAIL == is_double(buffer, NULL))
		err = zbx_dsprintf(NULL, "a numerical value is expected or the value is out of range");
	else if (SUCCEED == item_preproc_multiplier_variant(value_type, value, buffer, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "cannot apply multiplier \"%s\" to value of type \"%s\": %s",
			params, zbx_variant_type_desc(value), err);
	zbx_free(err);

	return FAIL;
}

/******************************************************************************
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
 * Purpose: execute delta type preprocessing operation                        *
 *                                                                            *
 * Parameters: value_type    - [IN] the item value type                       *
 *             value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             op_type       - [IN] the operation type                        *
 *             history_value - [IN/OUT] the historical (previous) data        *
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
	zbx_variant_t			value_num;

	if (FAIL == zbx_item_preproc_convert_value_to_numeric(&value_num, value, value_type, errmsg))
		return FAIL;

	zbx_variant_clear(value);

	if (ZBX_VARIANT_NONE != history_value->type)
	{
		int				ret;

		zbx_variant_copy(value, &value_num);

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
	zbx_variant_clear(history_value);
	zbx_variant_copy(history_value, &value_num);
	zbx_variant_clear(&value_num);

	return SUCCEED;
}

/******************************************************************************
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

	*errmsg = zbx_dsprintf(*errmsg, "cannot calculate delta (simple change) for value of type"
				" \"%s\": %s", zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
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

	*errmsg = zbx_dsprintf(*errmsg, "cannot calculate delta (speed per second) for value of type"
				" \"%s\": %s", zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy first n chars from in to out, unescape escaped characters    *
 *          during copying                                                    *
 *                                                                            *
 * Parameters: op_type - [IN] the operation type                              *
 *             in      - [IN] the value to unescape                           *
 *             len     - [IN] the length of the value to be unescaped         *
 *             out     - [OUT] the value to process                           *
 *                                                                            *
 ******************************************************************************/
static void	unescape_param(int op_type, const char *in, int len, char *out)
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

	unescape_param(op_type, params, strlen(params), params_raw);

	if (ZBX_PREPROC_LTRIM == op_type || ZBX_PREPROC_TRIM == op_type)
		zbx_ltrim(value->data.str, params_raw);

	if (ZBX_PREPROC_RTRIM == op_type || ZBX_PREPROC_TRIM == op_type)
		zbx_rtrim(value->data.str, params_raw);

	return SUCCEED;
}

/******************************************************************************
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
	char	*err = NULL, *characters;

	if (SUCCEED == item_preproc_trim(value, ZBX_PREPROC_RTRIM, params, &err))
		return SUCCEED;

	characters = zbx_str_printable_dyn(params);
	*errmsg = zbx_dsprintf(*errmsg, "cannot perform right trim of \"%s\" for value of type \"%s\": %s",
			characters, zbx_variant_type_desc(value), err);

	zbx_free(characters);
	zbx_free(err);

	return FAIL;
}

/******************************************************************************
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
	char	*err = NULL, *characters;

	if (SUCCEED == item_preproc_trim(value, ZBX_PREPROC_LTRIM, params, &err))
		return SUCCEED;

	characters = zbx_str_printable_dyn(params);
	*errmsg = zbx_dsprintf(*errmsg, "cannot perform left trim of \"%s\" for value of type \"%s\": %s",
			characters, zbx_variant_type_desc(value), err);

	zbx_free(characters);
	zbx_free(err);

	return FAIL;
}

/******************************************************************************
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
	char	*err = NULL, *characters;

	if (SUCCEED == item_preproc_trim(value, ZBX_PREPROC_TRIM, params, &err))
		return SUCCEED;

	characters = zbx_str_printable_dyn(params);
	*errmsg = zbx_dsprintf(*errmsg, "cannot perform trim of \"%s\" for value of type \"%s\": %s",
			characters, zbx_variant_type_desc(value), err);

	zbx_free(characters);
	zbx_free(err);

	return FAIL;
}

/******************************************************************************
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

	*errmsg = zbx_dsprintf(*errmsg, "cannot convert value of type \"%s\" from boolean format: %s",
			zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
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

	*errmsg = zbx_dsprintf(*errmsg, "cannot convert value of type \"%s\" from octal format: %s",
			zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
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

	*errmsg = zbx_dsprintf(*errmsg, "cannot convert value of type \"%s\" from hexadecimal format: %s",
			zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
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
		zbx_regexp_err_msg_free(regex_error);
		return FAIL;
	}

	if (FAIL == zbx_mregexp_sub_precompiled(value->data.str, regex, output, ZBX_MAX_RECV_DATA_SIZE, &new_value))
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
	char	*err = NULL, *ptr;
	int	len;

	if (SUCCEED == item_preproc_regsub_op(value, params, &err))
		return SUCCEED;

	if (NULL == (ptr = strchr(params, '\n')))
		len = strlen(params);
	else
		len = ptr - params;

	*errmsg = zbx_dsprintf(*errmsg, "cannot perform regular expression \"%.*s\" match for value of type \"%s\": %s",
			len, params, zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute jsonpath query                                            *
 *                                                                            *
 * Parameters: cache  - [IN] the preprocessing cache                          *
 *             value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_jsonpath_op(zbx_preproc_cache_t *cache, zbx_variant_t *value, const char *params,
		char **errmsg)
{
	char	*data = NULL;

	if (NULL == cache)
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
		zbx_jsonobj_t	*obj;

		if (NULL == (obj = (zbx_jsonobj_t *)zbx_preproc_cache_get(cache, ZBX_PREPROC_JSONPATH)))
		{
			if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
				return FAIL;

			obj = (zbx_jsonobj_t *)zbx_malloc(NULL, sizeof(zbx_jsonobj_t));

			if (SUCCEED != zbx_jsonobj_open(value->data.str, obj))
			{
				*errmsg = zbx_strdup(*errmsg, zbx_json_strerror());
				zbx_free(obj);
				return FAIL;
			}

			zbx_preproc_cache_put(cache, ZBX_PREPROC_JSONPATH, obj);
		}

		if (FAIL == zbx_jsonobj_query(obj, params, &data))
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
 * Purpose: execute jsonpath query                                            *
 *                                                                            *
 * Parameters: cache  - [IN] the preprocessing cache                          *
 *             value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_jsonpath(zbx_preproc_cache_t *cache, zbx_variant_t *value, const char *params,
		char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_jsonpath_op(cache, value, params, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "cannot extract value from json by path \"%s\": %s", params, err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
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

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	if (SUCCEED == zbx_query_xpath(value, params, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "cannot extract XML value with xpath \"%s\": %s", params, err);
	zbx_free(err);

	return FAIL;
}

/******************************************************************************
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

	return ret;
}

/******************************************************************************
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
static int	item_preproc_validate_regex(const zbx_variant_t *value, const char *params, char **error)
{
	zbx_variant_t	value_str;
	int		ret = FAIL;
	zbx_regexp_t	*regex;
	const char	*errptr = NULL;
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
		zbx_regexp_err_msg_free(errptr);
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
 * Parameters: value_type - [IN] the item type                                *
 *             value      - [IN/OUT] the value to process                     *
 *             params     - [IN] the operation parameters                     *
 *             errmsg     - [OUT] error message                               *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_validate_not_regex(const zbx_variant_t *value, const char *params, char **error)
{
	zbx_variant_t	value_str;
	int		ret = FAIL;
	zbx_regexp_t	*regex;
	const char	*errptr = NULL;
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
		zbx_regexp_err_msg_free(errptr);
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
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             error  - [OUT] error message                                   *
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
static int	item_preproc_get_error_from_json(const zbx_variant_t *value, const char *params, char **error)
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
		else
			ret = FAIL;
	}
out:
	zbx_variant_clear(&value_str);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks for presence of error field in XML data                    *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             error  - [OUT] the error message                               *
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
static int	item_preproc_get_error_from_xml(const zbx_variant_t *value, const char *params, char **error)
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
	xmlErrorPtr		pErr;
	xmlBufferPtr		xmlBufferLocal;

	zbx_variant_copy(&value_str, value);

	if (FAIL == (ret = item_preproc_convert_value(&value_str, ZBX_VARIANT_STR, error)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		goto out;
	}

	if (NULL == (doc = xmlReadMemory(value_str.data.str, strlen(value_str.data.str), "noname.xml", NULL, 0)))
		goto out;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)params, xpathCtx)))
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
	else
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
 * Purpose: checks for presence of error pattern matching regular expression  *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             error  - [OUT] the error message                               *
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
static int	item_preproc_get_error_from_regex(const zbx_variant_t *value, const char *params, char **error)
{
	zbx_variant_t	value_str;
	int		ret;
	char		pattern[ITEM_PREPROC_PARAMS_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1], *output;

	zbx_variant_copy(&value_str, value);

	if (FAIL == (ret = item_preproc_convert_value(&value_str, ZBX_VARIANT_STR, error)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		goto out;
	}

	zbx_strlcpy(pattern, params, sizeof(pattern));
	if (NULL == (output = strchr(pattern, '\n')))
	{
		*error = zbx_strdup(*error, "cannot find second parameter");
		ret = FAIL;
		goto out;
	}

	*output++ = '\0';

	if (FAIL == zbx_mregexp_sub(value_str.data.str, pattern, output, error))
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
		else
			ret = FAIL;
	}
out:
	zbx_variant_clear(&value_str);

	return ret;
}

/******************************************************************************
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

	zbx_variant_clear(history_value);
	zbx_variant_copy(history_value, value);

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

	zbx_variant_clear(history_value);
	zbx_variant_copy(history_value, value);

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
 * Purpose: executes script passed with params                                *
 *                                                                            *
 * Parameters: value    - [IN/OUT] the value to process                       *
 *             params   - [IN] the script to execute                          *
 *             bytecode - [IN] precompiled bytecode, can be NULL              *
 *             errmsg   - [OUT] error message                                 *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_script(zbx_variant_t *value, const char *params, zbx_variant_t *bytecode, char **errmsg)
{
	char	*code, *output = NULL, *error = NULL;
	int	size;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	if (SUCCEED != zbx_es_is_env_initialized(&es_engine))
	{
		if (SUCCEED != zbx_es_init_env(&es_engine, errmsg))
			return FAIL;
	}

	if (ZBX_VARIANT_BIN != bytecode->type)
	{
		if (SUCCEED != zbx_es_compile(&es_engine, params, &code, &size, errmsg))
			goto fail;

		zbx_variant_clear(bytecode);
		zbx_variant_set_bin(bytecode, zbx_variant_data_bin_create(code, size));
		zbx_free(code);
	}

	size = zbx_variant_data_bin_get(bytecode->data.bin, (void **)&code);

	if (SUCCEED == zbx_es_execute(&es_engine, params, code, size, value->data.str, &output, errmsg))
	{
		zbx_variant_clear(value);

		if (NULL != output)
			zbx_variant_set_str(value, output);

		return SUCCEED;
	}
fail:
	if (SUCCEED == zbx_es_fatal_error(&es_engine))
	{
		if (SUCCEED != zbx_es_destroy_env(&es_engine, &error))
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
 * Purpose: parse Prometheus format metrics                                   *
 *                                                                            *
 * Parameters: cache  - [IN] the preprocessing cache                          *
 *             value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_prometheus_pattern(zbx_preproc_cache_t *cache, zbx_variant_t *value, const char *params,
		char **errmsg)
{
	char	pattern[ITEM_PREPROC_PARAMS_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1], *request, *output, *value_out = NULL,
		*err = NULL;
	int	ret = FAIL;

	zbx_strlcpy(pattern, params, sizeof(pattern));

	if (NULL == (request = strchr(pattern, '\n')))
	{
		*errmsg = zbx_strdup(*errmsg, "cannot find second parameter");
		return FAIL;
	}
	*request++ = '\0';

	if (NULL == (output = strchr(request, '\n')))
	{
		*errmsg = zbx_strdup(*errmsg, "cannot find third parameter");
		return FAIL;
	}
	*output++ = '\0';

	if (NULL == cache)
	{
		if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
			return FAIL;

		ret = zbx_prometheus_pattern(value->data.str, pattern, request, output, &value_out, &err);
	}
	else
	{
		zbx_prometheus_t	*prom_cache;

		if (NULL == (prom_cache = (zbx_prometheus_t *)zbx_preproc_cache_get(cache,
				ZBX_PREPROC_PROMETHEUS_PATTERN)))
		{
			prom_cache = (zbx_prometheus_t *)zbx_malloc(NULL, sizeof(zbx_prometheus_t));

			if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
				return FAIL;

			if (SUCCEED != zbx_prometheus_init(prom_cache, value->data.str, &err))
			{
				zbx_free(prom_cache);
				goto out;
			}

			zbx_preproc_cache_put(cache, ZBX_PREPROC_PROMETHEUS_PATTERN, prom_cache);
		}

		ret = zbx_prometheus_pattern_ex(prom_cache, pattern, request, output, &value_out, &err);
	}
out:
	if (FAIL == ret)
	{
		*errmsg = zbx_dsprintf(*errmsg, "cannot apply Prometheus pattern: %s", err);
		zbx_free(err);
		return FAIL;
	}

	zbx_variant_clear(value);
	zbx_variant_set_str(value, value_out);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert Prometheus format metrics to JSON format                  *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_prometheus_to_json(zbx_variant_t *value, const char *params, char **errmsg)
{
	char	*value_out = NULL, *err = NULL;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	if (FAIL == zbx_prometheus_to_json(value->data.str, params, &value_out, &err))
	{
		*errmsg = zbx_dsprintf(*errmsg, "cannot convert Prometheus data to JSON: %s", err);
		zbx_free(err);
		return FAIL;
	}

	zbx_variant_clear(value);
	zbx_variant_set_str(value, value_out);

	return SUCCEED;
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
 *             errmsg  - [OUT] error message                                  *
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
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_csv_to_json(zbx_variant_t *value, const char *params, char **errmsg)
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
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_xml_to_json(zbx_variant_t *value, char **errmsg)
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
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_str_replace(zbx_variant_t *value, const char *params, char **errmsg)
{
	unsigned int	len_search, len_replace;
	const char	*ptr;
	char		*new_string, search_str[ITEM_PREPROC_PARAMS_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1],
			replace_str[ITEM_PREPROC_PARAMS_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

	if (NULL == (ptr = strchr(params, '\n')))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		*errmsg = zbx_strdup(*errmsg, "cannot find second parameter");
		return FAIL;
	}

	if (0 == (len_search = ptr - params))
	{
		*errmsg = zbx_strdup(*errmsg, "first parameter is expected");
		return FAIL;
	}

	unescape_param(ZBX_PREPROC_STR_REPLACE, params, MIN(len_search, sizeof(search_str) - 1), search_str);

	len_replace = strlen(ptr + 1);
	unescape_param(ZBX_PREPROC_STR_REPLACE, ptr + 1, MIN(len_replace, sizeof(replace_str) - 1), replace_str);

	if (SUCCEED != item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	new_string = string_replace(value->data.str, search_str, replace_str);
	zbx_variant_clear(value);
	zbx_variant_set_str(value, new_string);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute preprocessing operation                                   *
 *                                                                            *
 * Parameters: cache         - [IN/OUT] the preprocessing cache               *
 *             value_type    - [IN] the item value type                       *
 *             value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             op            - [IN] the preprocessing operation to execute    *
 *             history_value - [IN/OUT] last historical data of items with    *
 *                                      delta type preprocessing operation    *
 *             error         - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, error contains the error message           *
 *                                                                            *
 * Comments: When preprocessing step was executed successfully, but it must   *
 *           force an error (extract error steps), then success will be       *
 *           returned with error set.                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_item_preproc(zbx_preproc_cache_t *cache, unsigned char value_type, zbx_variant_t *value,
		const zbx_timespec_t *ts, const zbx_preproc_op_t *op, zbx_variant_t *history_value,
		zbx_timespec_t *history_ts, char **error)
{
	int	ret;

	switch (op->type)
	{
		case ZBX_PREPROC_MULTIPLIER:
			ret = item_preproc_multiplier(value_type, value, op->params, error);
			break;
		case ZBX_PREPROC_RTRIM:
			ret = item_preproc_rtrim(value, op->params, error);
			break;
		case ZBX_PREPROC_LTRIM:
			ret = item_preproc_ltrim(value, op->params, error);
			break;
		case ZBX_PREPROC_TRIM:
			ret = item_preproc_lrtrim(value, op->params, error);
			break;
		case ZBX_PREPROC_REGSUB:
			ret = item_preproc_regsub(value, op->params, error);
			break;
		case ZBX_PREPROC_BOOL2DEC:
			ret = item_preproc_bool2dec(value, error);
			break;
		case ZBX_PREPROC_OCT2DEC:
			ret = item_preproc_oct2dec(value, error);
			break;
		case ZBX_PREPROC_HEX2DEC:
			ret = item_preproc_hex2dec(value, error);
			break;
		case ZBX_PREPROC_DELTA_VALUE:
			ret = item_preproc_delta_value(value_type, value, ts, history_value, history_ts, error);
			break;
		case ZBX_PREPROC_DELTA_SPEED:
			ret = item_preproc_delta_speed(value_type, value, ts, history_value, history_ts, error);
			break;
		case ZBX_PREPROC_XPATH:
			ret = item_preproc_xpath(value, op->params, error);
			break;
		case ZBX_PREPROC_JSONPATH:
			ret = item_preproc_jsonpath(cache, value, op->params, error);
			break;
		case ZBX_PREPROC_VALIDATE_RANGE:
			ret = item_preproc_validate_range(value_type, value, op->params, error);
			break;
		case ZBX_PREPROC_VALIDATE_REGEX:
			ret = item_preproc_validate_regex(value, op->params, error);
			break;
		case ZBX_PREPROC_VALIDATE_NOT_REGEX:
			ret = item_preproc_validate_not_regex(value, op->params, error);
			break;
		case ZBX_PREPROC_ERROR_FIELD_JSON:
			ret = item_preproc_get_error_from_json(value, op->params, error);
			break;
		case ZBX_PREPROC_ERROR_FIELD_XML:
			ret = item_preproc_get_error_from_xml(value, op->params, error);
			break;
		case ZBX_PREPROC_ERROR_FIELD_REGEX:
			ret = item_preproc_get_error_from_regex(value, op->params, error);
			break;
		case ZBX_PREPROC_THROTTLE_VALUE:
			ret = item_preproc_throttle_value(value, ts, history_value, history_ts);
			break;
		case ZBX_PREPROC_THROTTLE_TIMED_VALUE:
			ret = item_preproc_throttle_timed_value(value, ts, op->params, history_value, history_ts,
					error);
			break;
		case ZBX_PREPROC_SCRIPT:
			ret = item_preproc_script(value, op->params, history_value, error);
			break;
		case ZBX_PREPROC_PROMETHEUS_PATTERN:
			ret = item_preproc_prometheus_pattern(cache, value, op->params, error);
			break;
		case ZBX_PREPROC_PROMETHEUS_TO_JSON:
			ret = item_preproc_prometheus_to_json(value, op->params, error);
			break;
		case ZBX_PREPROC_CSV_TO_JSON:
			ret = item_preproc_csv_to_json(value, op->params, error);
			break;
		case ZBX_PREPROC_STR_REPLACE:
			ret = item_preproc_str_replace(value, op->params, error);
			break;
		case ZBX_PREPROC_VALIDATE_NOT_SUPPORTED:
			ret = item_preproc_validate_notsupport(error);
			break;
		case ZBX_PREPROC_XML_TO_JSON:
			ret = item_preproc_xml_to_json(value, error);
			break;
		default:
			*error = zbx_dsprintf(*error, "unknown preprocessing operation");
			ret = FAIL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: apply 'on fail' preprocessing error handler                       *
 *                                                                            *
 * Parameters: value         - [IN/OUT] the value                             *
 *             op            - [IN] the preprocessing operation that produced *
 *                                  the error                                 *
 *             error         - [INT/OUT] error message                        *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step result was overridden by    *
 *                         error handler to successful result.                *
 *               FAIL    - the preprocessing step must still fail, error might*
 *                         have been changed.                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_item_preproc_handle_error(zbx_variant_t *value, const zbx_preproc_op_t *op, char **error)
{
	switch (op->error_handler)
	{
		case ZBX_PREPROC_FAIL_DISCARD_VALUE:
			zbx_variant_clear(value);
			zbx_free(*error);
			return SUCCEED;
		case ZBX_PREPROC_FAIL_SET_VALUE:
			zbx_variant_clear(value);
			zbx_variant_set_str(value, zbx_strdup(NULL, op->error_handler_params));
			zbx_free(*error);
			return SUCCEED;
		case ZBX_PREPROC_FAIL_SET_ERROR:
			*error = zbx_strdup(*error, op->error_handler_params);
			return FAIL;
		default:
			return FAIL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: test preprocessing steps                                          *
 *                                                                            *
 * Parameters: value_type    - [IN] the item value type                       *
 *             value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             steps         - [IN] the preprocessing steps to execute        *
 *             steps_num     - [IN] the number of preprocessing steps         *
 *             history_in    - [IN] the preprocessing history                 *
 *             history_out   - [OUT] the new preprocessing history            *
 *             results       - [OUT] the preprocessing step results           *
 *             results_num   - [OUT] the number of step results               *
 *             error         - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing steps finished successfully      *
 *               FAIL - otherwise, error contains the error message           *
 *                                                                            *
 ******************************************************************************/
int	zbx_item_preproc_test(unsigned char value_type, zbx_variant_t *value, const zbx_timespec_t *ts,
		zbx_preproc_op_t *steps, int steps_num, zbx_vector_ptr_t *history_in, zbx_vector_ptr_t *history_out,
		zbx_preproc_result_t *results, int *results_num, char **error)
{
	int	i, ret = SUCCEED;

	for (i = 0; i < steps_num; i++)
	{
		zbx_preproc_op_t	*op = &steps[i];
		zbx_variant_t		history_value;
		zbx_timespec_t		history_ts;

		zbx_preproc_history_pop_value(history_in, i, &history_value, &history_ts);

		if (FAIL == (ret = zbx_item_preproc(NULL, value_type, value, ts, op, &history_value, &history_ts,
				error)))
		{
			results[i].action = op->error_handler;
			results[i].error = zbx_strdup(NULL, *error);
			ret = zbx_item_preproc_handle_error(value, op, error);
		}
		else
		{
			results[i].action = ZBX_PREPROC_FAIL_DEFAULT;
			results[i].error = NULL;
		}

		if (SUCCEED != ret)
		{
			zbx_variant_set_none(&results[i].value);
			zbx_variant_clear(&history_value);
			break;
		}

		zbx_variant_copy(&results[i].value, value);

		if (ZBX_VARIANT_NONE != history_value.type)
		{
			/* the value is byte copied to history_out vector and doesn't have to be cleared */
			zbx_preproc_history_add_value(history_out, i, &history_value, &history_ts);
		}

		if (ZBX_VARIANT_NONE == value->type)
			break;
	}

	*results_num = (i == steps_num ? i : i + 1);

	return ret;
}

#ifdef HAVE_TESTS
#	include "../../../tests/zabbix_server/preprocessor/item_preproc_test.c"
#endif
