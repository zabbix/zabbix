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
#include "log.h"
#include "zbxdiag.h"
#include "ha/ha.h"

/******************************************************************************
 *                                                                            *
 * Function: rtc_diaginfo                                                     *
 *                                                                            *
 * Purpose: process diaginfo runtime commmand                                 *
 *                                                                            *
 ******************************************************************************/
static void	rtc_diaginfo(int scope)
{
	int	flags;

	if (ZBX_DIAGINFO_ALL == scope)
	{
		flags = (1 << ZBX_DIAGINFO_HISTORYCACHE) | (1 << ZBX_DIAGINFO_VALUECACHE) |
				(1 << ZBX_DIAGINFO_PREPROCESSING) | (1 << ZBX_DIAGINFO_LLD) |
				(1 << ZBX_DIAGINFO_ALERTING) | 	(1 << ZBX_DIAGINFO_LOCKS);
	}
	else
		flags = 1 << scope;

	zbx_diag_log_info(flags);
}

/******************************************************************************
 *                                                                            *
 * Function: rtc_ha_status                                                    *
 *                                                                            *
 * Purpose: process ha_status runtime commmand                                *
 *                                                                            *
 ******************************************************************************/
static void	rtc_ha_status(void)
{
	char			*nodes = NULL, *error = NULL;
	struct zbx_json_parse	jp, jp_node;

	if (SUCCEED != zbx_ha_get_nodes(&nodes, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get HA node information: %s", error);
		zbx_free(error);
		return;
	}

#define ZBX_HA_REPORT_FMT	"%-25s %-25s %-30s %-11s %s"

	if (SUCCEED == zbx_json_open(nodes, &jp))
	{
		const char	*pnext;
		char		name[256], address[261], id[26], buffer[256];
		int		status, lastaccess_age, index = 1;

		zabbix_log(LOG_LEVEL_INFORMATION, "cluster status:");
		zabbix_log(LOG_LEVEL_INFORMATION, "  %2s  " ZBX_HA_REPORT_FMT, "#", "ID", "Name",
				"Address", "Status", "Last Access");

		for (pnext = NULL; NULL != (pnext = zbx_json_next(&jp, pnext));)
		{
			if (FAIL == zbx_json_brackets_open(pnext, &jp_node))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_node, ZBX_PROTO_TAG_ID, id, sizeof(id), NULL))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_node, ZBX_PROTO_TAG_NAME, name, sizeof(name),
					NULL))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_node, ZBX_PROTO_TAG_STATUS, buffer,
					sizeof(buffer), NULL))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}
			status = atoi(buffer);

			if (SUCCEED != zbx_json_value_by_name(&jp_node, ZBX_PROTO_TAG_LASTACCESS_AGE, buffer,
					sizeof(buffer), NULL))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}
			lastaccess_age = atoi(buffer);

			if (SUCCEED != zbx_json_value_by_name(&jp_node, ZBX_PROTO_TAG_ADDRESS, address,
					sizeof(address), NULL))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			zabbix_log(LOG_LEVEL_INFORMATION, "  %2d. " ZBX_HA_REPORT_FMT, index++, id, name,
					address, zbx_ha_status_str(status), zbx_age2str(lastaccess_age));
		}
	}
	zbx_free(nodes);

#undef ZBX_HA_REPORT_FMT
}

/******************************************************************************
 *                                                                            *
 * Function: rtc_remove_node                                                  *
 *                                                                            *
 * Purpose: process ha_remove_node runtime commmand                           *
 *                                                                            *
 ******************************************************************************/
static void	rtc_remove_node(int index)
{
	char	*error = NULL;

	if (SUCCEED != zbx_ha_remove_node(index, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot remove HA node: %s", error);
		zbx_free(error);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: rtc_ha_failover_delay                                            *
 *                                                                            *
 * Purpose: process ha_failover_delay runtime commmand                        *
 *                                                                            *
 ******************************************************************************/
static void	rtc_ha_failover_delay(int delay)
{
	char	*error = NULL;

	if (SUCCEED != zbx_ha_set_failover_delay(delay, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set HA failover delay: %s", error);
		zbx_free(error);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_rtc_process_command                                          *
 *                                                                            *
 * Purpose: process runtime command                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtc_process_command(int command)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() command:%d", __func__, ZBX_RTC_GET_MSG(command));

	switch (ZBX_RTC_GET_MSG(command))
	{
		case ZBX_RTC_DIAGINFO:
			rtc_diaginfo(ZBX_RTC_GET_SCOPE(command));
			break;
		case ZBX_RTC_HA_STATUS:
			rtc_ha_status();
			break;
		case ZBX_RTC_HA_REMOVE_NODE:
			rtc_remove_node(ZBX_RTC_GET_DATA(command));
			break;
		case ZBX_RTC_HA_FAILOVER_DELAY:
			rtc_ha_failover_delay(ZBX_RTC_GET_DATA(command));
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
