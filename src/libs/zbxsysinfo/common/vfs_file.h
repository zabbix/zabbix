/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_SYSINFO_COMMON_VFS_FILE_H
#define ZABBIX_SYSINFO_COMMON_VFS_FILE_H

#include "module.h"

int	vfs_file_size(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_file_time(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_file_exists(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_file_contents(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_file_regexp(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_file_regmatch(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_file_md5sum(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_file_cksum(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_file_owner(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_file_permissions(AGENT_REQUEST *request, AGENT_RESULT *result);
int	vfs_file_get(AGENT_REQUEST *request, AGENT_RESULT *result);

#endif /* ZABBIX_SYSINFO_COMMON_VFS_FILE_H */
