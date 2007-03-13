/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"

#include "sysinfo.h"


int	VFS_FS_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	
	char
		path[MAX_PATH],
		mode[20];

	ULARGE_INTEGER freeBytes,totalBytes;

	if(num_param(param) > 2)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 1, path, MAX_PATH) != 0)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, mode, sizeof(mode)) != 0)
	{
		mode[0] = '\0';
	}
	if(mode[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(mode, sizeof(mode), "total");
	}

	if (!GetDiskFreeSpaceEx(path, &freeBytes, &totalBytes, NULL))
	{
		return SYSINFO_RET_FAIL;
	}

	if (strcmp(mode,"free") == 0)
	{
		SET_UI64_RESULT(result, freeBytes.QuadPart);
	}
	else if (strcmp(mode,"used") == 0)
	{
		SET_UI64_RESULT(result, totalBytes.QuadPart - freeBytes.QuadPart);
	}
	else if (strcmp(mode,"total") == 0)
	{
		SET_UI64_RESULT(result, totalBytes.QuadPart);
	}
	else if (strcmp(mode,"pfree") == 0)
	{
		SET_UI64_RESULT(result, (double)(__int64)freeBytes.QuadPart * 100. / (double)(__int64)totalBytes.QuadPart);
	}
	else if (strcmp(mode,"pused") == 0)
	{
		SET_UI64_RESULT(result, (double)((__int64)totalBytes.QuadPart-(__int64)freeBytes.QuadPart) * 100. / (double)(__int64)totalBytes.QuadPart);
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

