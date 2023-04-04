/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "history_server.h"

#include "zbxcacheconfig.h"
#include "zbxtime.h"
#include "zbxpreproc.h"
#include "zbxcachehistory.h"
#include "../lld/lld_protocol.h"

/******************************************************************************
 *                                                                            *
 * Purpose: processes item value depending on proxy/flags settings            *
 *                                                                            *
 * Parameters: item    - [IN] the item to process                             *
 *             result  - [IN] the item result                                 *
 *                                                                            *
 * Comments: Values gathered by server are sent to the preprocessing manager, *
 *           while values received from proxy are already preprocessed and    *
 *           must be either directly stored to history cache or sent to lld   *
 *           manager.                                                         *
 *                                                                            *
 ******************************************************************************/
void	history_process_item_value_server(const zbx_history_recv_item_t *item, AGENT_RESULT *result, zbx_timespec_t *ts,
		int *h_num, char *error)
{
	if (0 == item->host.proxy_hostid)
	{
		zbx_preprocess_item_value(item->itemid, item->host.hostid, item->value_type, item->flags, result, ts,
				item->state, error);
		*h_num = 0;
	}
	else
	{
		if (0 != (ZBX_FLAG_DISCOVERY_RULE & item->flags))
		{
			zbx_lld_process_agent_result(item->itemid, item->host.hostid, result, ts, error);
			*h_num = 0;
		}
		else
		{
			zbx_dc_add_history(item->itemid, item->value_type, item->flags, result, ts, item->state, error);
			*h_num = 1;
		}
	}
}
