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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"
#include "module.h"

#include <malloc.h>

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
 * Function: zbx_mock_str_to_value_type                                       *
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

/******************************************************************************
 *                                                                            *
 * Function: zbx_mock_str_to_return_code                                      *
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
