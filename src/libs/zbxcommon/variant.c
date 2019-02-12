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
#include "zbxalgo.h"

void	zbx_variant_clear(zbx_variant_t *value)
{
	switch (value->type)
	{
		case ZBX_VARIANT_STR:
			zbx_free(value->data.str);
			break;
	}

	value->type = ZBX_VARIANT_NONE;
}

void	zbx_variant_set_str(zbx_variant_t *value, char *text)
{
	value->data.str = text;
	value->type = ZBX_VARIANT_STR;
}

void	zbx_variant_set_dbl(zbx_variant_t *value, double value_dbl)
{
	value->data.dbl = value_dbl;
	value->type = ZBX_VARIANT_DBL;
}

void	zbx_variant_set_ui64(zbx_variant_t *value, zbx_uint64_t value_ui64)
{
	value->data.ui64 = value_ui64;
	value->type = ZBX_VARIANT_UI64;
}

void	zbx_variant_set_none(zbx_variant_t *value)
{
	value->type = ZBX_VARIANT_NONE;
}

void	zbx_variant_set_variant(zbx_variant_t *value, const zbx_variant_t *source)
{
	switch (source->type)
	{
		case ZBX_VARIANT_STR:
			zbx_variant_set_str(value, zbx_strdup(NULL, source->data.str));
			break;
		case ZBX_VARIANT_UI64:
			zbx_variant_set_ui64(value, source->data.ui64);
			break;
		case ZBX_VARIANT_DBL:
			zbx_variant_set_dbl(value, source->data.dbl);
			break;
		case ZBX_VARIANT_NONE:
			value->type = ZBX_VARIANT_NONE;
			break;
	}
}

static int	variant_to_dbl(zbx_variant_t *value)
{
	char	buffer[MAX_STRING_LEN];
	double	value_dbl;

	switch (value->type)
	{
		case ZBX_VARIANT_DBL:
			return SUCCEED;
		case ZBX_VARIANT_UI64:
			zbx_variant_set_dbl(value, (double)value->data.ui64);
			return SUCCEED;
		case ZBX_VARIANT_STR:
			zbx_strlcpy(buffer, value->data.str, sizeof(buffer));
			break;
		default:
			return FAIL;
	}

	zbx_ltrim(buffer, " \"+");
	zbx_rtrim(buffer, " \"\n\r");

	if (SUCCEED != is_double(buffer))
		return FAIL;

	value_dbl = atof(buffer);

	zbx_variant_clear(value);
	zbx_variant_set_dbl(value, value_dbl);

	return SUCCEED;
}

static int	variant_to_ui64(zbx_variant_t *value)
{
	zbx_uint64_t	value_ui64;
	char		buffer[MAX_STRING_LEN];

	switch (value->type)
	{
		case ZBX_VARIANT_UI64:
			return SUCCEED;
		case ZBX_VARIANT_DBL:
			if (0 > value->data.dbl)
				return FAIL;

			zbx_variant_set_ui64(value, value->data.dbl);
			return SUCCEED;
		case ZBX_VARIANT_STR:
			zbx_strlcpy(buffer, value->data.str, sizeof(buffer));
			break;
		default:
			return FAIL;
	}

	zbx_ltrim(buffer, " \"+");
	zbx_rtrim(buffer, " \"\n\r");
	del_zeros(buffer);

	if (SUCCEED != is_uint64(buffer, &value_ui64))
		return FAIL;

	zbx_variant_clear(value);
	zbx_variant_set_ui64(value, value_ui64);

	return SUCCEED;
}

static int	variant_to_str(zbx_variant_t *value)
{
	char	*value_str;

	switch (value->type)
	{
		case ZBX_VARIANT_STR:
			return SUCCEED;
		case ZBX_VARIANT_DBL:
			value_str = zbx_dsprintf(NULL, ZBX_FS_DBL, value->data.dbl);
			del_zeros(value_str);
			break;
		case ZBX_VARIANT_UI64:
			value_str = zbx_dsprintf(NULL, ZBX_FS_UI64, value->data.ui64);
			break;
		default:
			return FAIL;
	}

	zbx_variant_clear(value);
	zbx_variant_set_str(value, value_str);

	return SUCCEED;
}

int	zbx_variant_convert(zbx_variant_t *value, int type)
{
	switch(type)
	{
		case ZBX_VARIANT_UI64:
			return variant_to_ui64(value);
		case ZBX_VARIANT_DBL:
			return variant_to_dbl(value);
		case ZBX_VARIANT_STR:
			return variant_to_str(value);
		case ZBX_VARIANT_NONE:
			zbx_variant_clear(value);
			return SUCCEED;
		default:
			return FAIL;
	}
}

int	zbx_variant_set_numeric(zbx_variant_t *value, const char *text)
{
	zbx_uint64_t	value_ui64;
	char		buffer[MAX_STRING_LEN];

	zbx_strlcpy(buffer, text, sizeof(buffer));

	zbx_ltrim(buffer, " \"+");
	zbx_rtrim(buffer, " \"\n\r");
	del_zeros(buffer);

	if (SUCCEED == is_uint64(buffer, &value_ui64))
	{
		zbx_variant_set_ui64(value, value_ui64);
		return SUCCEED;
	}

	if (SUCCEED == is_double(buffer))
	{
		zbx_variant_set_dbl(value, atof(buffer));
		return SUCCEED;
	}

	return FAIL;
}

const char	*zbx_variant_value_desc(const zbx_variant_t *value)
{
	static char	buffer[ZBX_MAX_UINT64_LEN + 1];

	switch (value->type)
	{
		case ZBX_VARIANT_DBL:
			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_DBL, value->data.dbl);
			del_zeros(buffer);
			return buffer;
		case ZBX_VARIANT_UI64:
			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, value->data.ui64);
			return buffer;
		case ZBX_VARIANT_STR:
			return value->data.str;
		case ZBX_VARIANT_NONE:
			return "";
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return ZBX_UNKNOWN_STR;
	}
}

const char	*zbx_get_variant_type_desc(unsigned char type)
{
	switch (type)
	{
		case ZBX_VARIANT_DBL:
			return "double";
		case ZBX_VARIANT_UI64:
			return "uint64";
		case ZBX_VARIANT_STR:
			return "string";
		case ZBX_VARIANT_NONE:
			return "none";
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return ZBX_UNKNOWN_STR;
	}
}

const char	*zbx_variant_type_desc(const zbx_variant_t *value)
{
	return zbx_get_variant_type_desc(value->type);
}

int	zbx_validate_value_dbl(double value)
{
	/* field with precision 16, scale 4 [NUMERIC(16,4)] */
	const double	pg_min_numeric = -1e12;
	const double	pg_max_numeric = 1e12;

	if (value <= pg_min_numeric || value >= pg_max_numeric)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: variant_compare_empty                                            *
 *                                                                            *
 * Purpose: compares two variant values when at least one is empty            *
 *                                                                            *
 ******************************************************************************/
static int	variant_compare_empty(const zbx_variant_t *value1, const zbx_variant_t *value2)
{
	if (ZBX_VARIANT_NONE == value1->type)
	{
		if (ZBX_VARIANT_NONE == value2->type)
			return 0;

		return -1;
	}

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: variant_compare_str                                              *
 *                                                                            *
 * Purpose: compares two variant values when at least one is string           *
 *                                                                            *
 ******************************************************************************/
static int	variant_compare_str(const zbx_variant_t *value1, const zbx_variant_t *value2)
{
	if (ZBX_VARIANT_STR == value1->type)
		return strcmp(value1->data.str, zbx_variant_value_desc(value2));

	return strcmp(zbx_variant_value_desc(value1), value2->data.str);
}

/******************************************************************************
 *                                                                            *
 * Function: variant_compare_dbl                                              *
 *                                                                            *
 * Purpose: compares two variant values when at least one is double and the   *
 *          other is double or uint64                                         *
 *                                                                            *
 ******************************************************************************/
static int	variant_compare_dbl(const zbx_variant_t *value1, const zbx_variant_t *value2)
{
	double	value1_dbl, value2_dbl;

	switch (value1->type)
	{
		case ZBX_VARIANT_DBL:
			value1_dbl = value1->data.dbl;
			break;
		case ZBX_VARIANT_UI64:
			value1_dbl = value1->data.ui64;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}

	switch (value2->type)
	{
		case ZBX_VARIANT_DBL:
			value2_dbl = value2->data.dbl;
			break;
		case ZBX_VARIANT_UI64:
			value2_dbl = value2->data.ui64;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}

	if (SUCCEED == zbx_double_compare(value1_dbl, value2_dbl))
		return 0;

	ZBX_RETURN_IF_NOT_EQUAL(value1_dbl, value2_dbl);
	THIS_SHOULD_NEVER_HAPPEN;
	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Function: variant_compare_ui64                                             *
 *                                                                            *
 * Purpose: compares two variant values when both are uint64                  *
 *                                                                            *
 ******************************************************************************/
static int	variant_compare_ui64(const zbx_variant_t *value1, const zbx_variant_t *value2)
{
	ZBX_RETURN_IF_NOT_EQUAL(value1->data.ui64, value2->data.ui64);
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_variant_compare                                              *
 *                                                                            *
 * Purpose: compares two variant values                                       *
 *                                                                            *
 * Parameters: value1 - [IN] the first value                                  *
 *             value2 - [IN] the second value                                 *
 *                                                                            *
 * Return value: <0 - the first value is less than second                     *
 *               >0 - the first value is greater than second                  *
 *               0  - the values are equal                                    *
 *                                                                            *
 * Comments: The following priority is applied:                               *
 *           1) value of none type is always less than other types, two       *
 *              none types are equal                                          *
 *           2) if any of value is of string type, the other is converted to  *
 *              string and both are compared                                  *
 *           3) if any of value is of floating type, the other is converted   *
 *              to floating value and both are compared                       *
 *           4) only uin64 types are left, compare as uin64                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_variant_compare(const zbx_variant_t *value1, const zbx_variant_t *value2)
{
	if (ZBX_VARIANT_NONE == value1->type || ZBX_VARIANT_NONE == value2->type)
		return variant_compare_empty(value1, value2);

	if (ZBX_VARIANT_STR == value1->type || ZBX_VARIANT_STR == value2->type)
		return variant_compare_str(value1, value2);

	if (ZBX_VARIANT_DBL == value1->type || ZBX_VARIANT_DBL == value2->type)
		return variant_compare_dbl(value1, value2);

	if (ZBX_VARIANT_UI64 == value1->type && ZBX_VARIANT_UI64 == value2->type)
		return variant_compare_ui64(value1, value2);

	THIS_SHOULD_NEVER_HAPPEN;
	exit(EXIT_FAILURE);
}


