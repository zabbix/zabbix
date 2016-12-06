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
#include "zbxregexp.h"

#include "item_preproc.h"

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
 * Function: item_preproc_multiplier_variant                                  *
 *                                                                            *
 * Purpose: execute custom multiplier preprocessing operation on variant      *
 *          value type                                                        *
 *                                                                            *
 * Parameters: value         - [IN/OUT] the value to process                  *
 *             params        - [IN] the operation parameters                  *
 *             errmsg        - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_multiplier_variant(zbx_variant_t *value, const char *params, char **errmsg)
{
	zbx_uint64_t	multiplier_ui64, value_ui64;
	double		value_dbl;
	int		ret;
	zbx_variant_t	value_num;

	switch (value->type)
	{
		case ZBX_VARIANT_DBL:
		case ZBX_VARIANT_UI64:
			zbx_variant_set_variant(&value_num, value);
			ret = SUCCEED;
			break;
		case ZBX_VARIANT_STR:
			ret = zbx_variant_set_numeric(&value_num, value->data.str);
			break;
		case ZBX_VARIANT_LOG:
			ret = zbx_variant_set_numeric(&value_num, value->data.log->value);
			break;
		default:
			ret = FAIL;
	}

	if (FAIL == ret)
	{
		*errmsg = zbx_strdup(*errmsg, "cannot convert value to numeric type");
		return FAIL;
	}

	switch (value_num.type)
	{
		case ZBX_VARIANT_DBL:
			value_dbl = value_num.data.dbl * atof(params);
			if (FAIL == zbx_validate_value_dbl(value_dbl))
			{
				*errmsg = zbx_strdup(*errmsg, "value is too small or too large");
				return FAIL;
			}
			zbx_variant_clear(value);
			zbx_variant_set_dbl(value, value_dbl);
			break;
		case ZBX_VARIANT_UI64:
			if (SUCCEED == is_uint64(params, &multiplier_ui64))
				value_ui64 = value_num.data.ui64 * multiplier_ui64;
			else
				value_ui64 = (double)value_num.data.ui64 * atof(params);

			/* check for overflow */
			if (value_ui64 < value_num.data.ui64)
			{
				*errmsg = zbx_strdup(*errmsg, "value is too large");
				return FAIL;
			}
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
 * Parameters: item          - [IN] the item                                  *
 *             value         - [IN/OUT] the value to process                  *
 *             params        - [IN] the operation parameters                  *
 *             errmsg        - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_multiplier(zbx_variant_t *value, const char *params, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_multiplier_variant(value, params, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "Cannot apply multiplier \"%s\" to value \"%s\" of type \"%s\": %s",
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
	if (0 == hvalue->timestamp.sec || hvalue->value.dbl > value->data.dbl)
		return FAIL;

	switch (op_type)
	{
		case ZBX_PREPROC_DELTA_SPEED:
			if (0 <= zbx_timespec_compare(&hvalue->timestamp, ts))
				return FAIL;

			value->data.dbl = (value->data.dbl - hvalue->value.dbl) /
					((ts->sec - hvalue->timestamp.sec) +
						(double)(ts->ns - hvalue->timestamp.ns) / 1000000000);
			break;
		case ZBX_PREPROC_DELTA_VALUE:
			value->data.dbl = value->data.dbl - hvalue->value.dbl;
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
		zbx_item_history_value_t *deltaitem)
{
	if (0 == deltaitem->timestamp.sec || deltaitem->value.dbl > value->data.dbl)
		return FAIL;

	switch (op_type)
	{
		case ZBX_PREPROC_DELTA_SPEED:
			if (0 <= zbx_timespec_compare(&deltaitem->timestamp, ts))
				return FAIL;

			value->data.dbl = (value->data.dbl - deltaitem->value.dbl) /
					((ts->sec - deltaitem->timestamp.sec) +
						(double)(ts->ns - deltaitem->timestamp.ns) / 1000000000);
			break;
		case ZBX_PREPROC_DELTA_VALUE:
			value->data.dbl = value->data.dbl - deltaitem->value.dbl;
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
 * Parameters: item          - [IN] the item                                  *
 *             value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             op_type       - [IN] the operation type                        *
 *             delta_history - [IN] historical data of items with delta       *
 *                                  preprocessing operation                   *
 *             errmsg        - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_delta(const DC_ITEM *item, zbx_variant_t *value, const zbx_timespec_t *ts,
		unsigned char op_type, zbx_hashset_t *delta_history, char **errmsg)
{
	int				ret = FAIL;
	zbx_item_history_value_t	*deltaitem;
	history_value_t			value_delta;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_DBL, errmsg))
				return FAIL;
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_UI64, errmsg))
				return FAIL;
			break;
		default:
			*errmsg = zbx_strdup(*errmsg, "invalid value type");
			return FAIL;
	}

	if (NULL == (deltaitem = zbx_hashset_search(delta_history, &item->itemid)))
	{
		zbx_item_history_value_t	deltaitem_local;

		deltaitem_local.itemid = item->itemid;
		deltaitem_local.timestamp = *ts;
		deltaitem_local.value = value->data;

		zbx_hashset_insert(delta_history, &deltaitem_local, sizeof(deltaitem_local));

		zbx_variant_clear(value);

		return SUCCEED;
	}

	value_delta = value->data;

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			ret = item_preproc_delta_float(value, ts, op_type, deltaitem);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ret = item_preproc_delta_uint64(value, ts, op_type, deltaitem);
			break;
	}

	deltaitem->timestamp = *ts;
	deltaitem->value = value_delta;

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
 * Parameters: item          - [IN] the item                                  *
 *             value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             delta_history - [IN] historical data of items with delta       *
 *                                  preprocessing operation                   *
 *             errmsg        - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_delta_value(const DC_ITEM *item, zbx_variant_t *value, const zbx_timespec_t *ts,
		zbx_hashset_t *delta_history, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_delta(item, value, ts, ZBX_PREPROC_DELTA_VALUE, delta_history, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "Cannot calculate delta (simple change) for value \"%s\" of type"
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
 * Parameters: item          - [IN] the item                                  *
 *             value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             delta_history - [IN] historical data of items with delta       *
 *                                  preprocessing operation                   *
 *             errmsg        - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the value was calculated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	item_preproc_delta_speed(const DC_ITEM *item, zbx_variant_t *value, const zbx_timespec_t *ts,
		zbx_hashset_t *delta_history, char **errmsg)
{
	char	*err = NULL;

	if (SUCCEED == item_preproc_delta(item, value, ts, ZBX_PREPROC_DELTA_SPEED, delta_history, &err))
		return SUCCEED;

	*errmsg = zbx_dsprintf(*errmsg, "Cannot calculate delta (speed per second) for value \"%s\" of type"
				" \"%s\": %s", zbx_variant_value_desc(value), zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
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
	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	if (ZBX_PREPROC_LTRIM == op_type || ZBX_PREPROC_TRIM == op_type)
		zbx_ltrim(value->data.str, params);

	if (ZBX_PREPROC_RTRIM == op_type || ZBX_PREPROC_TRIM == op_type)
		zbx_rtrim(value->data.str, params);

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

	*errmsg = zbx_dsprintf(*errmsg, "Cannot perform right trim of \"%s\" for value \"%s\" of type \"%s\": %s",
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

	*errmsg = zbx_dsprintf(*errmsg, "Cannot perform left trim of \"%s\" for value \"%s\" of type \"%s\": %s",
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

	*errmsg = zbx_dsprintf(*errmsg, "Cannot perform trim of \"%s\" for value \"%s\" of type \"%s\": %s",
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
	char		buffer[MAX_STRING_LEN];
	zbx_uint64_t	value_ui64;

	switch (value->type)
	{
		case ZBX_VARIANT_STR:
			zbx_strlcpy(buffer, value->data.str, sizeof(buffer));
			break;
		case ZBX_VARIANT_LOG:
			zbx_strlcpy(buffer, value->data.log->value, sizeof(buffer));
			break;
		default:
			*errmsg = zbx_strdup(NULL, "invalid value type");
			return FAIL;
	}

	zbx_ltrim(buffer, " \"+");
	zbx_rtrim(buffer, " \"\n\r");
	del_zeroes(buffer);

	switch (op_type)
	{
		case ZBX_PREPROC_BOOL2DEC:
			if (SUCCEED != is_boolean(buffer, &value_ui64))
			{
				*errmsg = zbx_strdup(NULL, "invalid value format");
				return FAIL;
			}
			break;
		case ZBX_PREPROC_OCT2DEC:
			if (SUCCEED != is_uoct(buffer))
			{
				*errmsg = zbx_strdup(NULL, "invalid value format");
				return FAIL;
			}
			ZBX_OCT2UINT64(value_ui64, buffer);
			break;
		case ZBX_PREPROC_HEX2DEC:
			if (SUCCEED != is_uhex(buffer))
			{
				*errmsg = zbx_strdup(NULL, "invalid value format");
				return FAIL;
			}
			ZBX_HEX2UINT64(value_ui64, buffer);
			break;
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

	*errmsg = zbx_dsprintf(*errmsg, "Cannot convert value \"%s\" of type \"%s\" from boolean format: %s",
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

	*errmsg = zbx_dsprintf(*errmsg, "Cannot convert value \"%s\" of type \"%s\" from octal format: %s",
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

	*errmsg = zbx_dsprintf(*errmsg, "Cannot convert value \"%s\" of type \"%s\" from hexadecimal format: %s",
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

	if (FAIL == zbx_regexp_sub(value->data.str, pattern, output, &new_value))
	{
		*errmsg = zbx_dsprintf(*errmsg, "invalid regular expression \"%s\"", pattern);
		return FAIL;
	}

	if (NULL == new_value)
		new_value = zbx_strdup(NULL, "");

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

	*errmsg = zbx_dsprintf(*errmsg, "Cannot perform regular expression match on value \"%s\" of type \"%s\": %s",
			zbx_variant_value_desc(value), zbx_variant_type_desc(value), err);

	zbx_free(err);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_item_preproc                                                 *
 *                                                                            *
 * Purpose: execute preprocessing step                                        *
 *                                                                            *
 * Parameters: item          - [IN] the item                                  *
 *             value         - [IN/OUT] the value to process                  *
 *             ts            - [IN] the value timestamp                       *
 *             op            - [IN] the preprocessing operation to execute    *
 *             delta_history - [IN/OUT] hashset with last historical data     *
 *                                      of items with delta type              *
 *                                      preprocessing operation               *
 *             errmsg        - [OUT] error message                            *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing step finished successfully       *
 *               FAIL - otherwise, errmsg contains the error message          *
 *                                                                            *
 ******************************************************************************/
int	zbx_item_preproc(const DC_ITEM *item, zbx_variant_t *value, const zbx_timespec_t *ts,
		const zbx_item_preproc_t *op, zbx_hashset_t *delta_history, char **errmsg)
{
	switch (op->type)
	{
		case ZBX_PREPROC_MULTIPLIER:
			return item_preproc_multiplier(value, op->params, errmsg);
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
			return item_preproc_delta_value(item, value, ts, delta_history, errmsg);
		case ZBX_PREPROC_DELTA_SPEED:
			return item_preproc_delta_speed(item, value, ts, delta_history, errmsg);
	}

	*errmsg = zbx_dsprintf(*errmsg, "Unknown preprocessing operation.");

	return FAIL;
}
