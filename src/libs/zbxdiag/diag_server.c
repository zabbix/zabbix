/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

#include "diag.h"

/******************************************************************************
 *                                                                            *
 * Function: diag_add_section_info                                            *
 *                                                                            *
 * Purpose: add requested section diagnostic information                      *
 *                                                                            *
 * Parameters: section - [IN] the section name                                *
 *             jp      - [IN] the request                                     *
 *             j       - [IN/OUT] the json to update                          *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the information was retrieved successfully         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	diag_add_section_info(const char *section, const struct zbx_json_parse *jp, struct zbx_json *j,
		char **error)
{
	int	ret = FAIL;

	if (0 == strcmp(section, "historycache"))
	{
		zbx_diag_map_t	field_map[] = {
				{"all", ZBX_DIAG_HISTORYCACHE_SIMPLE | ZBX_DIAG_HISTORYCACHE_MEM},
				{"items", ZBX_DIAG_HISTORYCACHE_ITEMS},
				{"values", ZBX_DIAG_HISTORYCACHE_VALUES},
				{"mem", ZBX_DIAG_HISTORYCACHE_MEM},
				{"mem.data", ZBX_DIAG_HISTORYCACHE_MEM_DATA},
				{"mem.index", ZBX_DIAG_HISTORYCACHE_MEM_INDEX},
				{"mem.trends", ZBX_DIAG_HISTORYCACHE_MEM_TRENDS},
				{NULL, 0}
		};

		ret = diag_add_historycache_info(jp, field_map, j, error);
	}
	else
		*error = zbx_dsprintf(*error, "Unsupported diagnostics section: %s", section);


	return ret;
}
