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

#include "zbxdiag.h"
#include "diag_proxy.h"

#include "common.h"

/******************************************************************************
 *                                                                            *
 * Purpose: add requested section diagnostic information                      *
 *                                                                            *
 * Parameters: section - [IN] the section name                                *
 *             jp      - [IN] the request                                     *
 *             json    - [IN/OUT] the json to update                          *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the information was retrieved successfully         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	diag_add_section_info(const char *section, const struct zbx_json_parse *jp, struct zbx_json *json,
		char **error)
{
	int	ret = FAIL;

	if (0 == strcmp(section, ZBX_DIAG_HISTORYCACHE))
		ret = zbx_diag_add_historycache_info(jp, json, error);
	else if (0 == strcmp(section, ZBX_DIAG_PREPROCESSING))
		ret = zbx_diag_add_preproc_info(jp, json, error);
	else if (0 == strcmp(section, ZBX_DIAG_LOCKS))
	{
		zbx_diag_add_locks_info(json);
		ret = SUCCEED;
	}
	else
		*error = zbx_dsprintf(*error, "Unsupported diagnostics section: %s", section);

	return ret;
}
