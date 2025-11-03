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

#ifndef ZABBIX_VERSION_H
#define ZABBIX_VERSION_H

#define ZBX_STR2(str)	#str
#define ZBX_STR(str)	ZBX_STR2(str)

#define APPLICATION_NAME	"Zabbix Agent"
#define ZABBIX_REVDATE		"29 October 2025"
#define ZABBIX_VERSION_MAJOR	8
#define ZABBIX_VERSION_MINOR	0
#define ZABBIX_VERSION_PATCH	0
#ifndef ZABBIX_VERSION_REVISION
#	define ZABBIX_VERSION_REVISION	{ZABBIX_REVISION}
#endif
#ifdef _WINDOWS
#	ifndef ZABBIX_VERSION_RC_NUM
#		define ZABBIX_VERSION_RC_NUM	{ZABBIX_RC_NUM}
#	endif
#endif
#define ZABBIX_VERSION_RC	"alpha2"
#define ZABBIX_VERSION		ZBX_STR(ZABBIX_VERSION_MAJOR) "." ZBX_STR(ZABBIX_VERSION_MINOR) "." \
				ZBX_STR(ZABBIX_VERSION_PATCH) ZABBIX_VERSION_RC
#define ZABBIX_VERSION_SHORT	ZBX_STR(ZABBIX_VERSION_MAJOR) "." ZBX_STR(ZABBIX_VERSION_MINOR) "." \
				ZBX_STR(ZABBIX_VERSION_PATCH)
#define ZABBIX_REVISION		ZBX_STR(ZABBIX_VERSION_REVISION)

#endif /* ZABBIX_VERSION_H */
