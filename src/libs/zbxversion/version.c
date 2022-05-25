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

#include "version.h"
#include "zbxtypes.h"

/******************************************************************************
 *                                                                            *
 * Purpose: extracts protocol version from value                              *
 *                                                                            *
 * Parameters:                                                                *
 *     value      - [IN] textual representation of version                    *
 *                                                                            *
 * Return value: The protocol version if it was successfully extracted,       *
 *               otherwise -1                                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_component_version(char *value)
{
	char	*pminor, *ptr;

	if (NULL == (pminor = strchr(value, '.')))
		return FAIL;

	*pminor++ = '\0';

	if (NULL != (ptr = strchr(pminor, '.')))
		*ptr = '\0';

	return ZBX_COMPONENT_VERSION(atoi(value), atoi(pminor));
}
