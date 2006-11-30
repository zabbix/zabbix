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

#include "config.h"

#include "common.h"
#include "sysinfo.h"

int     SYSTEM_HOSTNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	DWORD dwSize;
	char buffer[MAX_COMPUTERNAME_LENGTH];


	dwSize = MAX_COMPUTERNAME_LENGTH;
	GetComputerName(buffer, &dwSize);

	SET_TEXT_RESULT(result, strdup(buffer));

	return SYSINFO_RET_OK;
}

int     SYSTEM_UNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	DWORD	dwSize;
	char	*cpuType,
		computerName[MAX_COMPUTERNAME_LENGTH],
		osVersion[256],
		buffer[MAX_STRING_LEN];
	SYSTEM_INFO
		sysInfo;
	OSVERSIONINFO
		versionInfo;

	dwSize = MAX_COMPUTERNAME_LENGTH;
	GetComputerName(computerName,&dwSize);

	versionInfo.dwOSVersionInfoSize = sizeof(OSVERSIONINFO);
	GetVersionEx(&versionInfo);
	switch(versionInfo.dwPlatformId)
	{
		case VER_PLATFORM_WIN32_WINDOWS:
			zbx_snprintf(
				osVersion, 
				sizeof(osVersion), 
				"Windows %s-%s",
				versionInfo.dwMinorVersion==0 ? "95" :
					(versionInfo.dwMinorVersion==10 ? "98" :
					(versionInfo.dwMinorVersion==90 ? "Me" : "Unknown")),
				versionInfo.szCSDVersion);
			break;
		case VER_PLATFORM_WIN32_NT:
			if (versionInfo.dwMajorVersion!=5)
			{
				zbx_snprintf(
					osVersion,
					sizeof(osVersion),
					"Windows NT %d.%d %s",
					versionInfo.dwMajorVersion,
					versionInfo.dwMinorVersion,
					versionInfo.szCSDVersion
					);
			}
			else      /* Windows 2000, Windows XP or Windows Server 2003 */
			{
				zbx_snprintf(
					osVersion,
					sizeof(osVersion),
					"Windows %s%s%s",
					(versionInfo.dwMinorVersion == 0) ? "2000" :
						((versionInfo.dwMinorVersion == 1) ? "XP" : "Server 2003"),
					versionInfo.szCSDVersion[0]==0 ? "" : " ",
					versionInfo.szCSDVersion);
			}
			break;
		default:
			zbx_snprintf(osVersion, sizeof(osVersion), "Windows [Unknown Version]");
			break;
	}

	GetSystemInfo(&sysInfo);
	switch(sysInfo.wProcessorArchitecture)
	{
		case PROCESSOR_ARCHITECTURE_INTEL:
			cpuType="Intel IA-32";
			break;
		case PROCESSOR_ARCHITECTURE_MIPS:
			cpuType="MIPS";
			break;
		case PROCESSOR_ARCHITECTURE_ALPHA:
			cpuType="Alpha";
			break;
		case PROCESSOR_ARCHITECTURE_PPC:
			cpuType="PowerPC";
			break;
		case PROCESSOR_ARCHITECTURE_IA64:
			cpuType="Intel IA-64";
			break;
		case PROCESSOR_ARCHITECTURE_IA32_ON_WIN64:
			cpuType="IA-32 on IA-64";
			break;
		case PROCESSOR_ARCHITECTURE_AMD64:
			cpuType="AMD-64";
			break;
		default:
			cpuType="unknown";
			break;
	}

	zbx_snprintf(
		buffer, 
		sizeof(buffer), 
		"Windows %s %d.%d.%d %s %s",
		computerName,
		versionInfo.dwMajorVersion,
		versionInfo.dwMinorVersion,
		versionInfo.dwBuildNumber,
		osVersion,
		cpuType
		);

	SET_TEXT_RESULT(result, strdup(buffer));

	return SYSINFO_RET_OK;
}
