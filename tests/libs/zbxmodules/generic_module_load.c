/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxcommon.h"
#include "zbxmodules.h"
#include "zbxalgo.h"
#include "zbxsysinfo.h"

#include "zbxmocktest.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

void	zbx_mock_test_entry(void **state)
{
	int			r;
	const char		*path;
	zbx_mock_handle_t	in_files, file;
	zbx_vector_str_t	files;

	ZBX_UNUSED(state);

	zbx_vector_str_create(&files);

	zbx_init_metrics();

	path = zbx_mock_get_parameter_string("in.path");
	in_files = zbx_mock_get_parameter_handle("in.files");

	while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(in_files, &file))
	{
		const char	*name;

		r = zbx_mock_string(file, &name);

		zbx_mock_assert_int_eq("file list", ZBX_MOCK_SUCCESS, r);

		zbx_vector_str_append(&files, (char *)name);
	}
	zbx_vector_str_append(&files, NULL);

	r = zbx_load_modules(path, files.values, 1, 1);

	zbx_mock_assert_int_eq("modules loaded", SUCCEED, r);

	zbx_unload_modules();
	zbx_vector_str_destroy(&files);
}
