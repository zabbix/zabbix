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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxcommon.h"

#include "../../../src/zabbix_server/lld/lld.h"
#include "lld_common.h"

void	get_macros(const char *path, zbx_vector_lld_macro_path_ptr_t *macros)
{
	zbx_lld_macro_path_t	*macro;
	zbx_mock_handle_t	hmacros, hmacro;
	int			macros_num = 1;
	zbx_mock_error_t	err;

	hmacros = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hmacros, &hmacro))))
	{
		if (ZBX_MOCK_SUCCESS != err)
			fail_msg("Cannot read macro #%d: %s", macros_num, zbx_mock_error_string(err));

		macro = (zbx_lld_macro_path_t *)zbx_malloc(NULL, sizeof(zbx_lld_macro_path_t));
		macro->lld_macro = zbx_strdup(NULL, zbx_mock_get_object_member_string(hmacro, "macro"));
		macro->path = zbx_strdup(NULL, zbx_mock_get_object_member_string(hmacro, "path"));
		zbx_vector_lld_macro_path_ptr_append(macros, macro);

		macros_num++;
	}
}
