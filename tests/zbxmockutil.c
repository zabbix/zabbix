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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"
#include "module.h"
#include "zbxvariant.h"

#include <stdlib.h>

const char	*zbx_mock_get_parameter_string(const char *path)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	handle;
	const char		*parameter;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_parameter(path, &handle)) ||
			ZBX_MOCK_SUCCESS != (err = zbx_mock_string(handle, &parameter)))
	{
		fail_msg("Cannot read parameter at \"%s\": %s", path, zbx_mock_error_string(err));
	}

	return parameter;
}

const char	*zbx_mock_get_object_member_string(zbx_mock_handle_t object, const char *name)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	handle;
	const char		*member;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_object_member(object, name, &handle)) ||
			ZBX_MOCK_SUCCESS != (err = zbx_mock_string(handle, &member)))
	{
		fail_msg("Cannot read object member \"%s\": %s", name, zbx_mock_error_string(err));
	}

	return member;
}

zbx_mock_handle_t	zbx_mock_get_parameter_handle(const char *path)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	parameter;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_parameter(path, &parameter)))
		fail_msg("Cannot read parameter at \"%s\": %s", path, zbx_mock_error_string(err));

	return parameter;
}

zbx_mock_handle_t	zbx_mock_get_object_member_handle(zbx_mock_handle_t object, const char *name)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	member;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_object_member(object, name, &member)))
		fail_msg("Cannot read object member \"%s\": %s", name, zbx_mock_error_string(err));

	return member;
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts token type from text format                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_mock_str_to_token_type(const char *str, int *out)
{
	if (0 == strcmp(str, "ZBX_TOKEN_OBJECTID"))
		*out = ZBX_TOKEN_OBJECTID;
	else if (0 == strcmp(str, "ZBX_TOKEN_MACRO"))
		*out = ZBX_TOKEN_MACRO;
	else if (0 == strcmp(str, "ZBX_TOKEN_LLD_MACRO"))
		*out = ZBX_TOKEN_LLD_MACRO;
	else if (0 == strcmp(str, "ZBX_TOKEN_USER_MACRO"))
		*out = ZBX_TOKEN_USER_MACRO;
	else if (0 == strcmp(str, "ZBX_TOKEN_FUNC_MACRO"))
		*out = ZBX_TOKEN_FUNC_MACRO;
	else if (0 == strcmp(str, "ZBX_TOKEN_SIMPLE_MACRO"))
		*out = ZBX_TOKEN_SIMPLE_MACRO;
	else if (0 == strcmp(str, "ZBX_TOKEN_REFERENCE"))
		*out = ZBX_TOKEN_REFERENCE;
	else if (0 == strcmp(str, "ZBX_TOKEN_LLD_FUNC_MACRO"))
		*out = ZBX_TOKEN_LLD_FUNC_MACRO;
	else if (0 == strcmp(str, "ZBX_TOKEN_EXPRESSION_MACRO"))
		*out = ZBX_TOKEN_EXPRESSION_MACRO;
	else
		fail_msg("Unknown token type \"%s\"", str);
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts item value type from text format                         *
 *                                                                            *
 ******************************************************************************/
unsigned char	zbx_mock_str_to_value_type(const char *str)
{
	if (0 == strcmp(str, "ITEM_VALUE_TYPE_FLOAT"))
		return ITEM_VALUE_TYPE_FLOAT;

	if (0 == strcmp(str, "ITEM_VALUE_TYPE_STR"))
		return ITEM_VALUE_TYPE_STR;

	if (0 == strcmp(str, "ITEM_VALUE_TYPE_LOG"))
		return ITEM_VALUE_TYPE_LOG;

	if (0 == strcmp(str, "ITEM_VALUE_TYPE_UINT64"))
		return ITEM_VALUE_TYPE_UINT64;

	if (0 == strcmp(str, "ITEM_VALUE_TYPE_TEXT"))
		return ITEM_VALUE_TYPE_TEXT;

	fail_msg("Unknown value type \"%s\"", str);
	return ITEM_VALUE_TYPE_MAX;
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts item type from text format                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_mock_str_to_item_type(const char *str)
{
	if (0 == strcmp(str, "ITEM_TYPE_ZABBIX"))
		return ITEM_TYPE_ZABBIX;

	if (0 == strcmp(str, "ITEM_TYPE_TRAPPER"))
		return ITEM_TYPE_TRAPPER;

	if (0 == strcmp(str, "ITEM_TYPE_SIMPLE"))
		return ITEM_TYPE_SIMPLE;

	if (0 == strcmp(str, "ITEM_TYPE_INTERNAL"))
		return ITEM_TYPE_INTERNAL;

	if (0 == strcmp(str, "ITEM_TYPE_ZABBIX_ACTIVE"))
		return ITEM_TYPE_ZABBIX_ACTIVE;

	if (0 == strcmp(str, "ITEM_TYPE_HTTPTEST"))
		return ITEM_TYPE_HTTPTEST;

	if (0 == strcmp(str, "ITEM_TYPE_EXTERNAL"))
		return ITEM_TYPE_EXTERNAL;

	if (0 == strcmp(str, "ITEM_TYPE_DB_MONITOR"))
		return ITEM_TYPE_DB_MONITOR;

	if (0 == strcmp(str, "ITEM_TYPE_IPMI"))
		return ITEM_TYPE_IPMI;

	if (0 == strcmp(str, "ITEM_TYPE_SSH"))
		return ITEM_TYPE_SSH;

	if (0 == strcmp(str, "ITEM_TYPE_TELNET"))
		return ITEM_TYPE_TELNET;

	if (0 == strcmp(str, "ITEM_TYPE_CALCULATED"))
		return ITEM_TYPE_CALCULATED;

	if (0 == strcmp(str, "ITEM_TYPE_JMX"))
		return ITEM_TYPE_JMX;

	if (0 == strcmp(str, "ITEM_TYPE_SNMPTRAP"))
		return ITEM_TYPE_SNMPTRAP;

	if (0 == strcmp(str, "ITEM_TYPE_DEPENDENT"))
		return ITEM_TYPE_DEPENDENT;

	if (0 == strcmp(str, "ITEM_TYPE_HTTPAGENT"))
		return ITEM_TYPE_HTTPAGENT;

	if (0 == strcmp(str, "ITEM_TYPE_SNMP"))
		return ITEM_TYPE_SNMP;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts variant from text format                                 *
 *                                                                            *
 ******************************************************************************/
unsigned char	zbx_mock_str_to_variant(const char *str)
{
	if (0 == strcmp(str, "ZBX_VARIANT_NONE"))
		return ZBX_VARIANT_NONE;

	if (0 == strcmp(str, "ZBX_VARIANT_STR"))
		return ZBX_VARIANT_STR;

	if (0 == strcmp(str, "ZBX_VARIANT_DBL"))
		return ZBX_VARIANT_DBL;

	if (0 == strcmp(str, "ZBX_VARIANT_UI64"))
		return ZBX_VARIANT_UI64;

	fail_msg("Unknown variant \"%s\"", str);
	return ZBX_VARIANT_NONE;
}

zbx_uint64_t	zbx_mock_get_parameter_uint64(const char *path)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	handle;
	zbx_uint64_t		parameter;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_parameter(path, &handle)) ||
			ZBX_MOCK_SUCCESS != (err = zbx_mock_uint64(handle, &parameter)))
	{
		fail_msg("Cannot read parameter at \"%s\": %s", path, zbx_mock_error_string(err));
	}

	return parameter;
}

zbx_uint64_t	zbx_mock_get_object_member_uint64(zbx_mock_handle_t object, const char *name)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	handle;
	zbx_uint64_t		member;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_object_member(object, name, &handle)) ||
			ZBX_MOCK_SUCCESS != (err = zbx_mock_uint64(handle, &member)))
	{
		fail_msg("Cannot read object member \"%s\": %s", name, zbx_mock_error_string(err));
	}

	return member;
}

double	zbx_mock_get_parameter_float(const char *path)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	handle;
	double			parameter;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_parameter(path, &handle)) ||
			ZBX_MOCK_SUCCESS != (err = zbx_mock_float(handle, &parameter)))
	{
		fail_msg("Cannot read parameter at \"%s\": %s", path, zbx_mock_error_string(err));
	}

	return parameter;
}

double	zbx_mock_get_object_member_float(zbx_mock_handle_t object, const char *name)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	handle;
	double			member;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_object_member(object, name, &handle)) ||
			ZBX_MOCK_SUCCESS != (err = zbx_mock_float(handle, &member)))
	{
		fail_msg("Cannot read object member \"%s\": %s", name, zbx_mock_error_string(err));
	}

	return member;
}

int	zbx_mock_get_object_member_int(zbx_mock_handle_t object, const char *name)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	handle;
	int			member;

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_object_member(object, name, &handle)) ||
			ZBX_MOCK_SUCCESS != (err = zbx_mock_int(handle, &member)))
	{
		fail_msg("Cannot read object member \"%s\": %s", name, zbx_mock_error_string(err));
	}

	return member;
}


/******************************************************************************
 *                                                                            *
 * Purpose: converts common function return code from text format             *
 *                                                                            *
 ******************************************************************************/
int	zbx_mock_str_to_return_code(const char *str)
{
	if (0 == strcmp(str, "SUCCEED"))
		return SUCCEED;

	if (0 == strcmp(str, "FAIL"))
		return FAIL;

	if (0 == strcmp(str, "NOTSUPPORTED"))
		return NOTSUPPORTED;

	if (0 == strcmp(str, "NETWORK_ERROR"))
		return NETWORK_ERROR;

	if (0 == strcmp(str, "TIMEOUT_ERROR"))
		return TIMEOUT_ERROR;

	if (0 == strcmp(str, "AGENT_ERROR"))
		return AGENT_ERROR;

	if (0 == strcmp(str, "GATEWAY_ERROR"))
		return GATEWAY_ERROR;

	if (0 == strcmp(str, "CONFIG_ERROR"))
		return CONFIG_ERROR;

	if (0 == strcmp(str, "SYSINFO_RET_OK"))
		return SYSINFO_RET_OK;

	if (0 == strcmp(str, "SYSINFO_RET_FAIL"))
		return SYSINFO_RET_FAIL;

	fail_msg("Unknown return code  \"%s\"", str);
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts item value type from text format                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_mock_str_to_family(const char *str)
{
	if (0 == strcmp(str, "AF_INET"))
		return AF_INET;

	if (0 == strcmp(str, "AF_INET6"))
		return AF_INET6;

	fail_msg("Unknown family \"%s\"", str);
	return AF_UNSPEC;
}

