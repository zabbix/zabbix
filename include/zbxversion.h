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

#ifndef ZABBIX_ZBXVERSION_H
#define ZABBIX_ZBXVERSION_H

#include "zbxtypes.h"

#define ZBX_COMPONENT_VERSION(major, minor, patch)	((major << 16) | (minor << 8) | patch)
#define ZBX_COMPONENT_VERSION_MAJOR(version)		(((zbx_uint32_t)(version) >> 16) & 0xff)
#define ZBX_COMPONENT_VERSION_MINOR(version)		(((zbx_uint32_t)(version) >> 8) & 0xff)
#define ZBX_COMPONENT_VERSION_PATCH(version)		((zbx_uint32_t)(version) & 0xff)
#define ZBX_COMPONENT_VERSION_WITHOUT_PATCH(version)	((zbx_uint32_t)(version) & ((0xff << 16) | (0xff << 8)))
#define ZBX_COMPONENT_VERSION_TO_DEC_FORMAT(version)	(ZBX_COMPONENT_VERSION_MAJOR(version) * 10000 + \
		ZBX_COMPONENT_VERSION_MINOR(version) * 100 + ZBX_COMPONENT_VERSION_PATCH(version))
#define ZBX_COMPONENT_VERSION_UNDEFINED			0

#define ZBX_VERSION_UNDEFINED_STR			"undefined"
#define ZBX_VERSION_BUF_LEN				20


int	zbx_get_component_version(const char *version_str);
int	zbx_get_component_version_without_patch(const char *value);

/* these values are shared with the UI*/
typedef enum
{
	ZBX_PROXY_VERSION_UNDEFINED = 0,
	ZBX_PROXY_VERSION_CURRENT,
	ZBX_PROXY_VERSION_OUTDATED,
	ZBX_PROXY_VERSION_UNSUPPORTED
}
zbx_proxy_compatibility_t;

#endif
