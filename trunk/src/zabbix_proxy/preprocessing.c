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

#include "common.h"
#include "dbcache.h"
#include "preproc.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocess_item_value                                        *
 *                                                                            *
 * Purpose: perform item value preprocessing and dependend item processing    *
 *                                                                            *
 * Parameters: itemid     - [IN] the itemid                                   *
 *             item_flags - [IN] the item flags (e. g. lld rule)              *
 *             result     - [IN] agent result containing the value to add     *
 *             ts         - [IN] the value timestamp                          *
 *             state      - [IN] the item state                               *
 *             error      - [IN] the error message in case item state is      *
 *                               ITEM_STATE_NOTSUPPORTED                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocess_item_value(zbx_uint64_t itemid, unsigned char item_flags, AGENT_RESULT *result,
		zbx_timespec_t *ts, unsigned char state, char *error)
{
	dc_add_history(itemid, item_flags, result, ts, state, error);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_flush                                           *
 *                                                                            *
 * Purpose: send flush command to preprocessing manager                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_flush(void)
{
	dc_flush_history();
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_get_queue_size                                  *
 *                                                                            *
 * Purpose: get queue size (enqueued value count) of preprocessing manager    *
 *                                                                            *
 * Return value: enqueued item count                                          *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_preprocessor_get_queue_size(void)
{
	return 0;
}
