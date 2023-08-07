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

#include "zbxcacheconfig.h"

#include "zbx_trigger_constants.h"

/******************************************************************************
 *                                                                            *
 * Purpose: get trigger severity name.                                        *
 *                                                                            *
 * Parameters: priority   - [IN] trigger data with priority field;            *
 *                               TRIGGER_SEVERITY_*                           *
 *             replace_to - [OUT] pointer to buffer that will receive         *
 *                                null-terminated trigger severity string     *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_config_get_trigger_severity_name(int priority, char **replace_to)
{
	zbx_config_t	cfg;

	if (TRIGGER_SEVERITY_COUNT <= priority)
		return FAIL;

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_SEVERITY_NAME);

	*replace_to = zbx_strdup(*replace_to, cfg.severity_name[priority]);

	zbx_config_clean(&cfg);

	return SUCCEED;
}
