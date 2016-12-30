/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

static char	data_static[ZBX_MAX_B64_LEN];

/******************************************************************************
 *                                                                            *
 * Purpose: get DATA from <tag>DATA</tag>                                     *
 *                                                                            *
 ******************************************************************************/
int	xml_get_data_dyn(const char *xml, const char *tag, char **data)
{
	size_t	len, sz;
	char	*start, *end;

	sz = sizeof(data_static);

	len = zbx_snprintf(data_static, sz, "<%s>", tag);
	if (NULL == (start = strstr(xml, data_static)))
		return FAIL;

	zbx_snprintf(data_static, sz, "</%s>", tag);
	if (NULL == (end = strstr(xml, data_static)))
		return FAIL;

	if (end < start)
		return FAIL;

	start += len;
	len = end - start;

	if (len > sz - 1)
		*data = zbx_malloc(*data, len + 1);
	else
		*data = data_static;

	zbx_strlcpy(*data, start, len + 1);

	return SUCCEED;
}

void	xml_free_data_dyn(char **data)
{
	if (*data == data_static)
		*data = NULL;
	else
		zbx_free(*data);
}
