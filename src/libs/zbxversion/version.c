/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxversion.h"
#include "zbxtypes.h"
#include "zbxcommon.h"

/******************************************************************************
 *                                                                            *
 * Purpose: Extracts protocol version from string cont. Alphanumeric release  *
 *          candidate version part is ignored.                                *
 *                                                                            *
 * Parameters:                                                                *
 *     version_str - [IN] textual representation of version                   *
 *                   Example: "6.4.0alpha1", "6.4.0" or "6.4"                 *
 *                                                                            *
 * Return value: The protocol version if it was successfully extracted,       *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_component_version(const char *version_str)
{
	char	*pmid, *plow;
	char	version_buf[ZBX_VERSION_BUF_LEN];

	zbx_strlcpy(version_buf, version_str, sizeof(version_buf));

	if (NULL == (pmid = strchr(version_buf, '.')))
		return FAIL;

	*pmid++ = '\0';

	if (NULL == (plow = strchr(pmid, '.')))
		return ZBX_COMPONENT_VERSION(atoi(version_buf), atoi(pmid), 0);

	*plow++ = '\0';

	return ZBX_COMPONENT_VERSION(atoi(version_buf), atoi(pmid), atoi(plow));
}

/******************************************************************************
 *                                                                            *
 * Purpose: Extracts protocol version from string. Only the two most          *
 *          significant groups of digits are extracted.                       *
 *                                                                            *
 * Parameters:                                                                *
 *     value      - [IN] textual representation of version                    *
 *                  Example: "6.4.0alpha1"                                    *
 *                                                                            *
 * Return value: The protocol version if it was successfully extracted,       *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_component_version_without_patch(const char *value)
{
	int	ver;

	if (FAIL == (ver = zbx_get_component_version(value)))
		return FAIL;

	return ZBX_COMPONENT_VERSION_WITHOUT_PATCH(ver);
}
