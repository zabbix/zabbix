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

#ifndef ZABBIX_SYSINFO_COMMON_DIR_H
#define ZABBIX_SYSINFO_COMMON_DIR_H

#include "sysinfo.h"

#define DISK_BLOCK_SIZE			512 // 512-byte blocks

#define SIZE_MODE_APPARENT		0	// Bytes in file
#define SIZE_MODE_DISK			1	// Size on disk

#define TRAVERSAL_DEPTH_UNLIMITED	-1	// Directory traversal depth is not limited

typedef struct
{
	int depth;
	char *path;
} zbx_directory_item_t;

int	VFS_DIR_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result);

#endif /* ZABBIX_SYSINFO_COMMON_DIR_H */
