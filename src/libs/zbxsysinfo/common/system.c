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
#include "system.h"
#include "log.h"

#ifdef _WINDOWS
#	include "perfmon.h"
#endif

int	SYSTEM_LOCALTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	SET_UI64_RESULT(result, time(NULL));

	return SYSINFO_RET_OK;
}

int	SYSTEM_USERS_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(_WINDOWS)
	char	counter_path[64];

	zbx_snprintf(counter_path, sizeof(counter_path), "\\%d\\%d", PCI_TERMINAL_SERVICES, PCI_TOTAL_SESSIONS);

	return PERF_COUNTER(cmd, counter_path, flags, result);
#else
	return EXECUTE_INT(cmd, "who|wc -l", flags, result);
#endif /* _WINDOWS */
}

int	SYSTEM_UNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(_WINDOWS)
	DWORD	dwSize = 256;
	TCHAR	computerName[256], osVersion[256], *cpuType, wide_buffer[MAX_STRING_LEN];
	SYSTEM_INFO
		sysInfo;
	OSVERSIONINFO
		versionInfo;

	/* Buffer size is chosen large enough to contain any DNS name, not just MAX_COMPUTERNAME_LENGTH + 1 */
	/* characters. MAX_COMPUTERNAME_LENGTH is usually less than 32, but it varies among systems, so we  */
	/* cannot use the constant in a precompiled Windows agent, which is expected to work on any system. */
	if (0 == GetComputerName(computerName, &dwSize))
		*computerName = '\0';

	versionInfo.dwOSVersionInfoSize = sizeof(OSVERSIONINFO);
	GetVersionEx(&versionInfo);
	switch (versionInfo.dwPlatformId) {
	case VER_PLATFORM_WIN32_WINDOWS:
		switch (versionInfo.dwMinorVersion) {
		case 0:
			zbx_wsnprintf(osVersion, sizeof(osVersion)/sizeof(TCHAR), TEXT("Windows 95-%s"), versionInfo.szCSDVersion);
			break;
		case 10:
			zbx_wsnprintf(osVersion, sizeof(osVersion)/sizeof(TCHAR), TEXT("Windows 98-%s"), versionInfo.szCSDVersion);
			break;
		case 90:
			zbx_wsnprintf(osVersion, sizeof(osVersion)/sizeof(TCHAR), TEXT("Windows Me-%s"), versionInfo.szCSDVersion);
			break;
		default:
			zbx_wsnprintf(osVersion, sizeof(osVersion)/sizeof(TCHAR), TEXT("Windows [Unknown Version]"));
		}
		break;
	case VER_PLATFORM_WIN32_NT:
		switch (versionInfo.dwMajorVersion) {
		case 4:
			zbx_wsnprintf(osVersion, sizeof(osVersion)/sizeof(TCHAR), TEXT("Windows NT 4.0 %s"), versionInfo.szCSDVersion);
			break;
		case 5:
			switch (versionInfo.dwMinorVersion) {
			case 1:
				zbx_wsnprintf(osVersion, sizeof(osVersion)/sizeof(TCHAR), TEXT("Windows XP %s"), versionInfo.szCSDVersion);
				break;
			case 2:
				zbx_wsnprintf(osVersion, sizeof(osVersion)/sizeof(TCHAR), TEXT("Windows Server 2003 %s"),
						versionInfo.szCSDVersion);
				break;
			default:
				zbx_wsnprintf(osVersion, sizeof(osVersion)/sizeof(TCHAR), TEXT("Windows [Unknown Version]"));
			}
			break;
		case 6:
			zbx_wsnprintf(osVersion, sizeof(osVersion)/sizeof(TCHAR), TEXT("Windows Server 2008 %s"), versionInfo.szCSDVersion);
			break;
		default:
			zbx_wsnprintf(osVersion, sizeof(osVersion)/sizeof(TCHAR), TEXT("Windows [Unknown Version]"));
			break;
		}
	}

	GetSystemInfo(&sysInfo);
	switch(sysInfo.wProcessorArchitecture)
	{
		case PROCESSOR_ARCHITECTURE_INTEL:
			cpuType=TEXT("Intel IA-32");
			break;
		case PROCESSOR_ARCHITECTURE_MIPS:
			cpuType=TEXT("MIPS");
			break;
		case PROCESSOR_ARCHITECTURE_ALPHA:
			cpuType=TEXT("Alpha");
			break;
		case PROCESSOR_ARCHITECTURE_PPC:
			cpuType=TEXT("PowerPC");
			break;
		case PROCESSOR_ARCHITECTURE_IA64:
			cpuType=TEXT("Intel IA-64");
			break;
		case PROCESSOR_ARCHITECTURE_IA32_ON_WIN64:
			cpuType=TEXT("IA-32 on IA-64");
			break;
		case PROCESSOR_ARCHITECTURE_AMD64:
			cpuType=TEXT("AMD-64");
			break;
		default:
			cpuType=TEXT("unknown");
			break;
	}

	zbx_wsnprintf(wide_buffer, MAX_STRING_LEN,
		TEXT("Windows %s %d.%d.%d %s %s"),
		computerName,
		versionInfo.dwMajorVersion,
		versionInfo.dwMinorVersion,
		versionInfo.dwBuildNumber,
		osVersion,
		cpuType);

	SET_STR_RESULT(result, zbx_unicode_to_utf8(wide_buffer));

	return SYSINFO_RET_OK;
#else
	return EXECUTE_STR(cmd, "uname -a", flags, result);
#endif /* _WINDOWS */
}

int	SYSTEM_HOSTNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(_WINDOWS)
	DWORD	dwSize = 256;
	TCHAR	computerName[256]; 
	char	buffer[256];
	int	netbios, ret;
	WSADATA sockInfo;

	if (1 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, buffer, sizeof(buffer)))
		*buffer = '\0';

	if ('\0' == *buffer || 0 == strcmp(buffer, "netbios"))
		netbios = 1;
	else if (0 == strcmp(buffer, "host"))
		netbios = 0;
	else
		return SYSINFO_RET_FAIL;

	if (1 == netbios)
	{
		/* Buffer size is chosen large enough to contain any DNS name, not just MAX_COMPUTERNAME_LENGTH + 1 */
		/* characters. MAX_COMPUTERNAME_LENGTH is usually less than 32, but it varies among systems, so we  */
		/* cannot use the constant in a precompiled Windows agent, which is expected to work on any system. */
		if (0 == GetComputerName(computerName, &dwSize))
			zabbix_log(LOG_LEVEL_ERR, "GetComputerName() failed: %s", strerror_from_system(GetLastError()));
		else
			SET_STR_RESULT(result, zbx_unicode_to_utf8(computerName));
	}
	else
	{
		if (0 != (ret = WSAStartup(MAKEWORD(2, 2), &sockInfo)))
			zabbix_log(LOG_LEVEL_ERR, "WSAStartup() failed: %s", strerror_from_system(ret));
		else if (SUCCEED != gethostname(buffer, sizeof(buffer)))
			zabbix_log(LOG_LEVEL_ERR, "gethostname() failed: %s", strerror_from_system(WSAGetLastError()));
		else
			SET_STR_RESULT(result, zbx_strdup(NULL, buffer));
	}

	if (ISSET_STR(result))
		return SYSINFO_RET_OK;
	else
		return SYSINFO_RET_FAIL;
#else
	return EXECUTE_STR(cmd, "hostname", flags, result);
#endif	/* _WINDOWS */
}
