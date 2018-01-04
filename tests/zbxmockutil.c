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

