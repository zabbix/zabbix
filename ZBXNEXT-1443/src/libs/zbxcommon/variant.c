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
#include "zbxalgo.h"

zbx_log_value_t	*zbx_log_value_dup(const zbx_log_value_t *src)
{
	zbx_log_value_t	*log;

	log = zbx_malloc(NULL, sizeof(zbx_log_value_t));

	log->source = (NULL != src->source ? zbx_strdup(NULL, src->source) : NULL);
	log->value = zbx_strdup(NULL, src->value);
	log->timestamp = src->timestamp;
	log->logeventid = src->logeventid;
	log->severity = src->severity;

	return log;
}

void	zbx_variant_clear(zbx_variant_t *value)
{
	switch (value->type)
	{
		case ZBX_VARIANT_STR:
			zbx_free(value->data.str);
			break;
		case ZBX_VARIANT_LOG:
			zbx_free(value->data.log->source);
			zbx_free(value->data.log->value);
			zbx_free(value->data.log);
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

void	zbx_variant_set_ui64(zbx_variant_t *value, double value_ui64)
{
	value->data.ui64 = value_ui64;
	value->type = ZBX_VARIANT_UI64;
}

void	zbx_variant_set_log(zbx_variant_t *value, zbx_log_value_t *log)
{
	value->data.log = log;
	value->type = ZBX_VARIANT_LOG;
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
		case ZBX_VARIANT_LOG:
			zbx_variant_set_log(value, zbx_log_value_dup(source->data.log));
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
		case ZBX_VARIANT_LOG:
			zbx_strlcpy(buffer, value->data.log->value, sizeof(buffer));
			break;
		default:
			return FAIL;
	}

	if (SUCCEED != is_double(buffer))
		return FAIL;

	value_dbl = atof(buffer);

	if (FAIL == zbx_validate_value_dbl(value_dbl))
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
			zbx_variant_set_ui64(value, (zbx_uint64_t)value->data.dbl);
			return SUCCEED;
		case ZBX_VARIANT_STR:
			zbx_strlcpy(buffer, value->data.str, sizeof(buffer));
			break;
		case ZBX_VARIANT_LOG:
			zbx_strlcpy(buffer, value->data.log->value, sizeof(buffer));
			break;
		default:
			return FAIL;
	}

	zbx_ltrim(buffer, " \"+");
	zbx_rtrim(buffer, " \"\n\r");
	del_zeroes(buffer);

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
			break;
		case ZBX_VARIANT_UI64:
			value_str = zbx_dsprintf(NULL, ZBX_FS_UI64, value->data.ui64);
			break;
		case ZBX_VARIANT_LOG:
			value_str = value->data.log->value;
			value->data.log->value = NULL;
			break;
		default:
			return FAIL;
	}

	zbx_variant_clear(value);
	zbx_variant_set_str(value, value_str);

	return SUCCEED;
}

static int	variant_to_log(zbx_variant_t *value)
{
	zbx_log_value_t	*log;
	char		*value_log;

	switch (value->type)
	{
		case ZBX_VARIANT_LOG:
			return SUCCEED;
		case ZBX_VARIANT_DBL:
			value_log = zbx_dsprintf(NULL, ZBX_FS_DBL, value->data.dbl);
			break;
		case ZBX_VARIANT_UI64:
			value_log = zbx_dsprintf(NULL, ZBX_FS_UI64, value->data.ui64);
			break;
		case ZBX_VARIANT_STR:
			value_log = value->data.str;
			value->data.str = NULL;
			break;
		default:
			return FAIL;
	}

	log = zbx_malloc(NULL, sizeof(zbx_log_value_t));
	log->logeventid = 0;
	log->severity = 0;
	log->timestamp = 0;
	log->source = NULL;
	log->value = value_log;

	zbx_variant_clear(value);
	zbx_variant_set_log(value, log);

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
		case ZBX_VARIANT_LOG:
			return variant_to_log(value);
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
	double		value_dbl;
	char		buffer[MAX_STRING_LEN];

	zbx_strlcpy(buffer, text, sizeof(buffer));

	zbx_ltrim(buffer, " \"+");
	zbx_rtrim(buffer, " \"\n\r");
	del_zeroes(buffer);

	if (SUCCEED == is_uint64(buffer, &value_ui64))
	{
		zbx_variant_set_ui64(value, value_ui64);
		return SUCCEED;
	}

	if (SUCCEED == is_double(buffer) && SUCCEED == zbx_validate_value_dbl(value_dbl = atof(buffer)))
	{
		zbx_variant_set_dbl(value, value_dbl);
		return SUCCEED;
	}

	return FAIL;
}

const char	*zbx_variant_value_desc(const zbx_variant_t *value)
{
	static char	buffer[ZBX_MAX_UINT64_LEN + 1];

	switch (value->type)
	{
		case ZBX_VARIANT_LOG:
			return value->data.log->value;
		case ZBX_VARIANT_DBL:
			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_DBL, value->data.dbl);
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

const char	*zbx_variant_type_desc(const zbx_variant_t *value)
{
	switch (value->type)
	{
		case ZBX_VARIANT_LOG:
			return "log";
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

int	zbx_validate_value_dbl(double value)
{
	/* field with precision 16, scale 4 [NUMERIC(16,4)] */
	const double	pg_min_numeric = -1e12;
	const double	pg_max_numeric = 1e12;

	if (value <= pg_min_numeric || value >= pg_max_numeric)
		return FAIL;

	return SUCCEED;
}
