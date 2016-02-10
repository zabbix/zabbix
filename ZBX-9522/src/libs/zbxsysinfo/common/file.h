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

#ifndef ZABBIX_SYSINFO_COMMON_FILE_H
#define ZABBIX_SYSINFO_COMMON_FILE_H

#include "sysinfo.h"

#define MAX_FILE_LEN (1024 * 1024)

int	VFS_FILE_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	VFS_FILE_TIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	VFS_FILE_EXISTS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	VFS_FILE_CONTENTS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	VFS_FILE_REGEXP(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	VFS_FILE_REGMATCH(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	VFS_FILE_MD5SUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
int	VFS_FILE_CKSUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

#endif /* ZABBIX_SYSINFO_COMMON_FILE_H */
