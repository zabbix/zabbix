/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
#include "zbxipcservice.h"
#include "zbxserialize.h"
#include "zbxha.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_ha_get_nodes                                                 *
 *                                                                            *
 * Purpose: get HA nodes in json format                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_get_nodes(char **nodes, char **error)
{
	unsigned char		*data, *ptr;
	zbx_uint32_t		str_len;
	int			ret;
	char			*str;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_HA, ZBX_IPC_SERVICE_HA_GET_NODES,
			ZBX_HA_SERVICE_TIMEOUT, NULL, 0, &data, error))
	{
		return FAIL;
	}

	ptr = data;
	ptr += zbx_deserialize_value(ptr, &ret);
	(void)zbx_deserialize_str(ptr, &str, str_len);
	zbx_free(data);

	if (SUCCEED == ret)
		*nodes = str;
	else
		*error = str;

	return ret;
}
