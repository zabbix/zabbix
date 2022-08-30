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

#ifndef ZABBIX_VERSION_H
#define ZABBIX_VERSION_H

#include "zbxtypes.h"

#define ZBX_STR2(str)	#str
#define ZBX_STR(str)	ZBX_STR2(str)

#define APPLICATION_NAME	"Zabbix Agent"
#define ZABBIX_REVDATE		"4 July 2022"
#define ZABBIX_VERSION_MAJOR	6
#define ZABBIX_VERSION_MINOR	4
#define ZABBIX_VERSION_PATCH	0
#ifndef ZABBIX_VERSION_REVISION
#	define ZABBIX_VERSION_REVISION	{ZABBIX_REVISION}
#endif
#ifdef _WINDOWS
#	ifndef ZABBIX_VERSION_RC_NUM
#		define ZABBIX_VERSION_RC_NUM	{ZABBIX_RC_NUM}
#	endif
#endif
#define ZABBIX_VERSION_RC	"alpha1"
#define ZABBIX_VERSION		ZBX_STR(ZABBIX_VERSION_MAJOR) "." ZBX_STR(ZABBIX_VERSION_MINOR) "." \
				ZBX_STR(ZABBIX_VERSION_PATCH) ZABBIX_VERSION_RC
#define ZABBIX_REVISION		ZBX_STR(ZABBIX_VERSION_REVISION)

#define ZBX_COMPONENT_VERSION(major, minor, patch)	((major << 16) | (minor << 8) | patch)
#define ZBX_COMPONENT_VERSION_MAJOR(version)		(((zbx_uint32_t)(version) >> 16) & 0xff)
#define ZBX_COMPONENT_VERSION_MINOR(version)		(((zbx_uint32_t)(version) >> 8) & 0xff)
#define ZBX_COMPONENT_VERSION_PATCH(version)		((zbx_uint32_t)(version) & 0xff)
#define ZBX_COMPONENT_VERSION_IGNORE_PATCH(version)	((zbx_uint32_t)(version) & ((0xff << 16) | (0xff << 8)))
#define ZBX_COMPONENT_VERSION_TO_DEC_FORMAT(version)	(ZBX_COMPONENT_VERSION_MAJOR(version) * 10000 + \
		ZBX_COMPONENT_VERSION_MINOR(version) * 100 + ZBX_COMPONENT_VERSION_PATCH(version))
#define ZBX_COMPONENT_VERSION_UNDEFINED			0

#define ZBX_VERSION_UNDEFINED_STR			"undefined"
#define ZBX_VERSION_BUF_LEN				20


int	zbx_get_component_version(const char *version_str);
int	zbx_get_component_version_ignore_patch(const char *value);

/* these values are shared with the UI*/
typedef enum
{
	ZBX_PROXY_VERSION_UNDEFINED = 0,
	ZBX_PROXY_VERSION_CURRENT,
	ZBX_PROXY_VERSION_OUTDATED,
	ZBX_PROXY_VERSION_UNSUPPORTED
}
zbx_proxy_compatibility_t;

#endif /* ZABBIX_VERSION_H */
