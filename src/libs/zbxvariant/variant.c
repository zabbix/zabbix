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

#include "zbxvariant.h"

#include "common.h"
#include "log.h"

ZBX_VECTOR_IMPL(var, zbx_variant_t)

static void	*zbx_variant_data_bin_copy(const void *bin)
{
	zbx_uint32_t		size;
	void	*value_bin;

	memcpy(&size, bin, sizeof(size));
	value_bin = zbx_malloc(NULL, size + sizeof(size));
	memcpy(value_bin, bin, size + sizeof(size));

	return value_bin;
}

void	*zbx_variant_data_bin_create(const void *data, zbx_uint32_t size)
{
	void	*value_bin;

	value_bin = zbx_malloc(NULL, size + sizeof(size));
	memcpy(value_bin, &size, sizeof(size));
	memcpy((unsigned char *)value_bin + sizeof(size), data, size);

	return value_bin;
}

zbx_uint32_t	zbx_variant_data_bin_get(const void *bin, void **data)
{
	zbx_uint32_t	size;

	memcpy(&size, bin, sizeof(zbx_uint32_t));
	if (NULL != data)
		*data = ((unsigned char *)bin) + sizeof(size);
	return size;
}

void	zbx_variant_clear(zbx_variant_t *value)
{
	switch (value->type)
	{
		case ZBX_VARIANT_STR:
			zbx_free(value->data.str);
			break;
		case ZBX_VARIANT_BIN:
			zbx_free(value->data.bin);
			break;
		case ZBX_VARIANT_ERR:
			zbx_free(value->data.err);
			break;
		case ZBX_VARIANT_DBL_VECTOR:
			zbx_vector_dbl_destroy(value->data.dbl_vector);
			zbx_free(value->data.dbl_vector);
			break;
	}

	value->type = ZBX_VARIANT_NONE;
}

/******************************************************************************
 *                                                                            *
 * Setter functions assign passed data and set corresponding variant          *
 * type. Note that for complex data it means the pointer is simply copied     *
 * instead of making a copy of the specified data.                            *
 *                                                                            *
 * The contents of the destination value are not freed. When setting already  *
 * initialized variant it's safer to clear it beforehand, even if the variant *
 * contains primitive value (numeric).                                        *
 *                                                                            *
 ******************************************************************************/

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

void	zbx_variant_set_bin(zbx_variant_t *value, void *value_bin)
{
	value->data.bin = value_bin;
	value->type = ZBX_VARIANT_BIN;
}

void	zbx_variant_set_error(zbx_variant_t *value, char *error)
{
	value->data.err = error;
	value->type = ZBX_VARIANT_ERR;
}

void	zbx_variant_set_dbl_vector(zbx_variant_t *value, zbx_vector_dbl_t *dbl_vector)
{
	value->data.dbl_vector = dbl_vector;
	value->type = ZBX_VARIANT_DBL_VECTOR;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy variant contents from source to value                        *
 *                                                                            *
 * Comments: String and binary data are cloned, which is different from       *
 *           setters where only the pointers are copied.                      *
 *           The contents of the destination value are not freed. If copied   *
 *           over already initialized variant it's safer to clear it          *
 *           beforehand.                                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_variant_copy(zbx_variant_t *value, const zbx_variant_t *source)
{
	zbx_vector_dbl_t	*dbl_vector;

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
		case ZBX_VARIANT_BIN:
			zbx_variant_set_bin(value, zbx_variant_data_bin_copy(source->data.bin));
			break;
		case ZBX_VARIANT_NONE:
			value->type = ZBX_VARIANT_NONE;
			break;
		case ZBX_VARIANT_ERR:
			zbx_variant_set_error(value, zbx_strdup(NULL, source->data.err));
			break;
		case ZBX_VARIANT_DBL_VECTOR:
			dbl_vector = (zbx_vector_dbl_t *)zbx_malloc(NULL, sizeof(zbx_vector_dbl_t));
			zbx_vector_dbl_create(dbl_vector);
			zbx_vector_dbl_append_array(dbl_vector, source->data.dbl_vector->values,
					source->data.dbl_vector->values_num);
			zbx_variant_set_dbl_vector(value, dbl_vector);
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

	zbx_rtrim(buffer, "\n\r"); /* trim newline for historical reasons / backwards compatibility */
	zbx_trim_float(buffer);

	if (SUCCEED != is_double(buffer, &value_dbl))
		return FAIL;

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

			/* uint64_t(double(UINT64_MAX)) conversion results in 0, to avoid      */
			/* conversion issues require floating value to be less than UINT64_MAX */
			if (ZBX_MAX_UINT64 <= value->data.dbl)
				return FAIL;

			zbx_variant_set_ui64(value, value->data.dbl);
			return SUCCEED;
		case ZBX_VARIANT_STR:
			zbx_strlcpy(buffer, value->data.str, sizeof(buffer));
			break;
		default:
			return FAIL;
	}

	zbx_rtrim(buffer, "\n\r"); /* trim newline for historical reasons / backwards compatibility */
	zbx_trim_integer(buffer);
	del_zeros(buffer);

	if (SUCCEED != is_uint64(buffer, &value_ui64))
		return FAIL;

	zbx_variant_clear(value);
	zbx_variant_set_ui64(value, value_ui64);

	return SUCCEED;
}

static int	variant_to_str(zbx_variant_t *value)
{
	char	*value_str, buffer[ZBX_MAX_DOUBLE_LEN + 1];

	switch (value->type)
	{
		case ZBX_VARIANT_STR:
			return SUCCEED;
		case ZBX_VARIANT_DBL:
			value_str = zbx_strdup(NULL, zbx_print_double(buffer, sizeof(buffer), value->data.dbl));
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
	double		dbl_tmp;
	char		buffer[MAX_STRING_LEN];

	zbx_strlcpy(buffer, text, sizeof(buffer));

	zbx_rtrim(buffer, "\n\r"); /* trim newline for historical reasons / backwards compatibility */
	zbx_trim_integer(buffer);
	del_zeros(buffer);

	if ('+' == buffer[0])
	{
		/* zbx_trim_integer() stripped one '+' sign, so there's more than one '+' sign in the 'text' argument */
		return FAIL;
	}

	if (SUCCEED == is_uint64(buffer, &value_ui64))
	{
		zbx_variant_set_ui64(value, value_ui64);
		return SUCCEED;
	}

	if (SUCCEED == is_double(buffer, &dbl_tmp))
	{
		zbx_variant_set_dbl(value, dbl_tmp);
		return SUCCEED;
	}

	return FAIL;
}

const char	*zbx_variant_value_desc(const zbx_variant_t *value)
{
	static ZBX_THREAD_LOCAL char	buffer[64];
	zbx_uint32_t			size, i, len;

	switch (value->type)
	{
		case ZBX_VARIANT_DBL:
			zbx_print_double(buffer, sizeof(buffer), value->data.dbl);
			del_zeros(buffer);
			return buffer;
		case ZBX_VARIANT_UI64:
			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, value->data.ui64);
			return buffer;
		case ZBX_VARIANT_STR:
			return value->data.str;
		case ZBX_VARIANT_NONE:
			return "";
		case ZBX_VARIANT_BIN:
			memcpy(&size, value->data.bin, sizeof(size));
			if (0 != (len = MIN(sizeof(buffer) / 3, size)))
			{
				const unsigned char	*ptr = (const unsigned char *)value->data.bin + sizeof(size);

				for (i = 0; i < len; i++)
					zbx_snprintf(buffer + i * 3, sizeof(buffer) - i * 3, "%02x ", ptr[i]);

				buffer[i * 3 - 1] = '\0';
			}
			else
				buffer[0] = '\0';
			return buffer;
		case ZBX_VARIANT_ERR:
			return value->data.err;
		case ZBX_VARIANT_DBL_VECTOR:
			zbx_snprintf(buffer, sizeof(buffer), "double vector[0:%d]", value->data.dbl_vector->values_num);
			return buffer;
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
		case ZBX_VARIANT_BIN:
			return "binary";
		case ZBX_VARIANT_ERR:
			return "error";
		case ZBX_VARIANT_DBL_VECTOR:
			return "double vector";
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return ZBX_UNKNOWN_STR;
	}
}

const char	*zbx_variant_type_desc(const zbx_variant_t *value)
{
	return zbx_get_variant_type_desc(value->type);
}

int	zbx_validate_value_dbl(double value, int dbl_precision)
{
	if ((ZBX_DB_DBL_PRECISION_ENABLED == dbl_precision && (value < -1e+308 || value > 1e+308)) ||
			(ZBX_DB_DBL_PRECISION_ENABLED != dbl_precision && (value <= -1e12 || value >= 1e12)))
	{
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares two variant values when at least one is empty (having    *
 *          type of ZBX_VARIANT_NONE)                                         *
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
 * Purpose: compare two variant values when at least one contains binary data *
 *                                                                            *
 ******************************************************************************/
static int	variant_compare_bin(const zbx_variant_t *value1, const zbx_variant_t *value2)
{
	if (ZBX_VARIANT_BIN == value1->type)
	{
		zbx_uint32_t	size1, size2;

		if (ZBX_VARIANT_BIN != value2->type)
			return 1;

		memcpy(&size1, value1->data.bin, sizeof(size1));
		memcpy(&size2, value2->data.bin, sizeof(size2));
		ZBX_RETURN_IF_NOT_EQUAL(size1, size2);
		return memcmp(value1->data.bin, value2->data.bin, size1 + sizeof(size1));
	}

	return -1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare two variant values when at least one contains error       *
 *                                                                            *
 ******************************************************************************/
static int	variant_compare_error(const zbx_variant_t *value1, const zbx_variant_t *value2)
{
	if (ZBX_VARIANT_ERR == value1->type)
	{
		if (ZBX_VARIANT_ERR != value2->type)
			return 1;

		return strcmp(value1->data.err, value2->data.err);
	}

	return -1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare two variant values when at least one contains error       *
 *                                                                            *
 ******************************************************************************/
static int	variant_compare_dbl_vector(const zbx_variant_t *value1, const zbx_variant_t *value2)
{
	if (ZBX_VARIANT_DBL_VECTOR == value1->type)
	{
		int	i;

		if (ZBX_VARIANT_DBL_VECTOR != value2->type)
			return 1;

		ZBX_RETURN_IF_NOT_EQUAL(value1->data.dbl_vector->values_num, value2->data.dbl_vector->values_num);

		for (i = 0; i < value1->data.dbl_vector->values_num; i++)
		{
			ZBX_RETURN_IF_NOT_EQUAL(value1->data.dbl_vector->values[i], value2->data.dbl_vector->values[i]);
		}

		return 0;
	}

	return -1;
}
/******************************************************************************
 *                                                                            *
 * Purpose: compare two variant values when at least one is string            *
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
 * Purpose: compare two variant values when at least one is double and the    *
 *          other is double, uint64 or a string representing a valid double   *
 *          value                                                             *
 *                                                                            *
 ******************************************************************************/
static int	variant_compare_dbl(const zbx_variant_t *value1, const zbx_variant_t *value2)
{
	double	value1_dbl, value2_dbl;
	char	buf1[ZBX_MAX_DOUBLE_LEN + 1], buf2[ZBX_MAX_DOUBLE_LEN + 1];

	switch (value1->type)
	{
		case ZBX_VARIANT_DBL:
			value1_dbl = value1->data.dbl;
			break;
		case ZBX_VARIANT_UI64:
			value1_dbl = value1->data.ui64;
			break;
		case ZBX_VARIANT_STR:
			value1_dbl = atof(value1->data.str);
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
		case ZBX_VARIANT_STR:
			value2_dbl = atof(value2->data.str);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}

	if (SUCCEED == zbx_double_compare(value1_dbl, value2_dbl))
		return 0;

	ZBX_RETURN_IF_NOT_EQUAL(value1_dbl, value2_dbl);

	zbx_print_double(buf1, sizeof(buf1), value1_dbl);
	zbx_print_double(buf2, sizeof(buf2), value2_dbl);
	zabbix_log(LOG_LEVEL_ERR, "\"%s\" to \"%s\" comparison result forced to 0", buf1, buf2);

	THIS_SHOULD_NEVER_HAPPEN;

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare two variant values when both are uint64                   *
 *                                                                            *
 ******************************************************************************/
static int	variant_compare_ui64(const zbx_variant_t *value1, const zbx_variant_t *value2)
{
	ZBX_RETURN_IF_NOT_EQUAL(value1->data.ui64, value2->data.ui64);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare two variant values                                        *
 *                                                                            *
 * Parameters: value1 - [IN] the first value                                  *
 *             value2 - [IN] the second value                                 *
 *                                                                            *
 * Return value: <0 - the first value is less than the second                 *
 *               >0 - the first value is greater than the second              *
 *               0  - the values are equal                                    *
 *                                                                            *
 * Comments: The following comparison logic is applied:                       *
 *           1) value of 'none' type is always less than other types, two     *
 *              'none' types are equal                                        *
 *           2) value of error type is always greater than other types, two   *
 *              error types are compared by error messages as strings         *
 *           3) value of binary type is always greater than other types       *
 *              except error, two binary types are compared by length and     *
 *              then by contents                                              *
 *           4) value of double vector type is always greater than other      *
 *              types except error and binary, two double vectors are compared*
 *              by their size and contents                                    *
 *           5) if both values have uint64 types, they are compared as is     *
 *           6) if both values can be converted to floating point values the  *
 *              conversion is done and the result is compared                 *
 *           7) if any of value is of string type, the other is converted to  *
 *              string and both are compared                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_variant_compare(const zbx_variant_t *value1, const zbx_variant_t *value2)
{
	if (ZBX_VARIANT_NONE == value1->type || ZBX_VARIANT_NONE == value2->type)
		return variant_compare_empty(value1, value2);

	if (ZBX_VARIANT_ERR == value1->type || ZBX_VARIANT_ERR == value2->type)
		return variant_compare_error(value1, value2);

	if (ZBX_VARIANT_BIN == value1->type || ZBX_VARIANT_BIN == value2->type)
		return variant_compare_bin(value1, value2);

	if (ZBX_VARIANT_DBL_VECTOR == value1->type || ZBX_VARIANT_DBL_VECTOR == value2->type)
		return variant_compare_dbl_vector(value1, value2);

	if (ZBX_VARIANT_UI64 == value1->type && ZBX_VARIANT_UI64 == value2->type)
		return  variant_compare_ui64(value1, value2);

	if ((ZBX_VARIANT_STR != value1->type || SUCCEED == is_double(value1->data.str, NULL)) &&
			(ZBX_VARIANT_STR != value2->type || SUCCEED == is_double(value2->data.str, NULL)))
	{
		return variant_compare_dbl(value1, value2);
	}

	/* at this point at least one of the values is string data, other can be uint64, floating or string */
	return variant_compare_str(value1, value2);
}
