/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
#include "zbxvariant.h"

/******************************************************************************
 *                                                                            *
 * Purpose: converts variant value to type compatible with requested value    *
 *          type                                                              *
 *                                                                            *
 * Parameters: value         - [IN/OUT] the value to convert                  *
 *             value_type    - [IN] the target value type                     *
 *             dbl_precision - [IN] double precision option                   *
 *             errmsg        - [OUT] the error message                        *
 *                                                                            *
 * Return value: SUCCEED - Value conversion was successful.                   *
 *               FAIL    - Otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_variant_to_value_type(zbx_variant_t *value, unsigned char value_type, int dbl_precision, char **errmsg)
{
#define ERROR_VALUE_MAX_CHAR	240
	int		ret;

	zbx_free(*errmsg);

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (SUCCEED == (ret = zbx_variant_convert(value, ZBX_VARIANT_DBL)))
			{
				if (FAIL == (ret = zbx_validate_value_dbl(value->data.dbl, dbl_precision)))
				{
					char	buffer[ZBX_MAX_DOUBLE_LEN + 1];

					*errmsg = zbx_dsprintf(NULL, "Value %s is too small or too large.",
							zbx_print_double(buffer, sizeof(buffer), value->data.dbl));
				}
			}
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ret = zbx_variant_convert(value, ZBX_VARIANT_UI64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
		case ITEM_VALUE_TYPE_LOG:
			ret = zbx_variant_convert(value, ZBX_VARIANT_STR);
			break;
		default:
			*errmsg = zbx_dsprintf(NULL, "Unknown value type \"%d\"", value_type);
			THIS_SHOULD_NEVER_HAPPEN;
			ret = FAIL;
	}

	if (FAIL == ret && NULL == *errmsg)
	{
		char		*err_val_tostring;
		const char	*err_val, *value_desc;
		char		short_value[ERROR_VALUE_MAX_CHAR * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];

		value_desc = zbx_variant_value_desc(value);
		err_val = zbx_truncate_value(value_desc, ERROR_VALUE_MAX_CHAR, short_value, sizeof(short_value));
		err_val_tostring = zbx_str_printable_dyn(err_val);
		*errmsg = zbx_dsprintf(NULL, "Value of type \"%s\" is not suitable for value"
				" type \"%s\". Value \"%s%s\"", zbx_variant_type_desc(value),
				zbx_item_value_type_string(value_type), err_val_tostring,
				(strlen(value_desc) > strlen(err_val)) ? "(truncated)" : "");
		zbx_free(err_val_tostring);
	}

	return ret;
#undef ERROR_VALUE_MAX_CHAR
}
