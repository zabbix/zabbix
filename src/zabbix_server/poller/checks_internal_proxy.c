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

#include "common.h"
#include "proxy.h"
#include "checks_internal.h"

/******************************************************************************
 *                                                                            *
 * Purpose: processes program type (proxy) specific internal checks           *
 *                                                                            *
 * Parameters: param1  - [IN] the first parameter                             *
 *             request - [IN] the request                                     *
 *             result  - [OUT] the result                                     *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *               NOTSUPPORTED - requested item is not supported               *
 *               FAIL - not a proxy specific internal check                   *
 *                                                                            *
 * Comments: This function is used to process proxy specific internal checks  *
 *           before generic internal checks are processed.                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_value_internal_ext(const char *param1, const AGENT_REQUEST *request, AGENT_RESULT *result)
{
	if (0 == strcmp(param1, "proxy_history"))
	{
		if (1 != get_rparams_num(request))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			return NOTSUPPORTED;
		}

		SET_UI64_RESULT(result, proxy_get_history_count());
	}
	else
		return FAIL;

	return SUCCEED;
}
