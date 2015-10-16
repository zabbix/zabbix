/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#define ZBX_STR2(str)	#str
#define ZBX_STR(str)	ZBX_STR2(str)

#define APPLICATION_NAME	"Zabbix Agent"
#define ZABBIX_REVDATE		"15 October 2015"
#define ZABBIX_VERSION_MAJOR	3
#define ZABBIX_VERSION_MINOR	0
#define ZABBIX_VERSION_PATCH	0
#define ZABBIX_VERSION_REVISION	{ZABBIX_REVISION}
#define ZABBIX_VERSION_RC	"alpha4"
#define ZABBIX_VERSION		ZBX_STR(ZABBIX_VERSION_MAJOR) "." ZBX_STR(ZABBIX_VERSION_MINOR) "." \
				ZBX_STR(ZABBIX_VERSION_PATCH) ZABBIX_VERSION_RC
#define ZABBIX_REVISION		ZBX_STR(ZABBIX_VERSION_REVISION)

#endif
