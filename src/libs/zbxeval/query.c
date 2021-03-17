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
#include "log.h"
#include "zbxalgo.h"
#include "zbxserver.h"
#include "zbxeval.h"
#include "eval.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_parse_filter                                            *
 *                                                                            *
 * Purpose: parse item query /host/key?[filter] into host, key and filter     *
 *          components                                                        *
 *                                                                            *
 * Parameters: str   - [IN] the item query                                    *
 *             len   - [IN] the query length                                  *
 *             query - [IN] the parsed item query                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_parse_query(const char *str, size_t len, zbx_item_query_t *query)
{
	const char	*ptr = str, *key;

	if ('/' != *ptr || NULL == (key = strchr(++ptr, '/')))
	{
		query->type = ZBX_ITEM_QUERY_UNKNOWN;
		return;
	}

	if (ptr != key)
		query->host = zbx_substr(ptr, 0, key - ptr);
	else
		query->host = NULL;

	query->key = zbx_substr(key, 1, len - (key - str) - 1);
	query->type = ZBX_ITEM_QUERY_SINGLE;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_clear_filter                                            *
 *                                                                            *
 * Purpose: frees resources allocated by item reference                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_clear_query(zbx_item_query_t *ref)
{
	zbx_free(ref->host);
	zbx_free(ref->key);
}
