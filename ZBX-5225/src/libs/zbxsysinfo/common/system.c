/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include "system.h"
#include "log.h"

#ifdef _WINDOWS
#	include "perfmon.h"
#	pragma comment(lib, "user32.lib")
#endif

int	SYSTEM_LOCALTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		type[16], buf[32];
	struct tm	*tm;
	size_t		offset;
	int		gmtoff, ms;
	unsigned short	h, m;
#ifdef _WINDOWS
        struct _timeb	tv;
#else
	struct timeval	tv;
	struct timezone	tz;
#endif

	if (3 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, type, sizeof(type)))
		return SYSINFO_RET_FAIL;

	if ('\0' == *type || 0 == strcmp(type, "utc"))
	{
		SET_UI64_RESULT(result, time(NULL));
	}
	else if (0 == strcmp(type, "local"))
	{
#ifdef _WINDOWS
	        _ftime(&tv);
		tm = localtime(&tv.time);
		ms = tv.millitm;
#else
		gettimeofday(&tv, &tz);
		tm = localtime(&tv.tv_sec);
		ms = (int)(tv.tv_usec / 1000);
#endif
		offset = zbx_snprintf(buf, sizeof(buf), "%04d-%02d-%02d,%02d:%02d:%02d.%03d,",
				1900 + tm->tm_year, 1 + tm->tm_mon, tm->tm_mday,
				tm->tm_hour, tm->tm_min, tm->tm_sec, ms);

		/* timezone offset */
#if defined(HAVE_TM_TM_GMTOFF)
		gmtoff = tm->tm_gmtoff;
#else
		gmtoff = -timezone;
#endif
#ifdef _WINDOWS
		if (0 < tm->tm_isdst)		/* daylight saving time */
			gmtoff += SEC_PER_HOUR;	/* assume DST is one hour */
#endif
		h = (unsigned short)(abs(gmtoff) / SEC_PER_HOUR);
		m = (unsigned short)((abs(gmtoff) - h * SEC_PER_HOUR) / SEC_PER_MIN);

		if (0 <= gmtoff)
			offset += zbx_snprintf(buf + offset, sizeof(buf) - offset, "+");
		else
			offset += zbx_snprintf(buf + offset, sizeof(buf) - offset, "-");

		offset += zbx_snprintf(buf + offset, sizeof(buf) - offset, "%02d:%02d", (int)h, (int)m);

		SET_STR_RESULT(result, strdup(buf));
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	SYSTEM_USERS_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef _WINDOWS
	char	counter_path[64];

	zbx_snprintf(counter_path, sizeof(counter_path), "\\%d\\%d", PCI_TERMINAL_SERVICES, PCI_TOTAL_SESSIONS);

	return PERF_COUNTER(cmd, counter_path, flags, result);
#else
	return EXECUTE_INT(cmd, "who | wc -l", flags, result);
#endif
}

#ifdef _WINDOWS
static void	get_50_version(char **os, size_t *os_alloc, size_t *os_offset, OSVERSIONINFOEX *vi)
{
	zbx_strcpy_alloc(os, os_alloc, os_offset, " Microsoft Windows 2000");

	if (VER_NT_WORKSTATION != vi->wProductType)
	{
		if (0 != (vi->wSuiteMask & VER_SUITE_DATACENTER))
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Datacenter Server");
		else if (0 != (vi->wSuiteMask & VER_SUITE_ENTERPRISE))
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Advanced Server");
		else
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Server");
	}
	else
		zbx_strcpy_alloc(os, os_alloc, os_offset, " Professional");

}

static void	get_51_version(char **os, size_t *os_alloc, size_t *os_offset, OSVERSIONINFOEX *vi)
{
	zbx_strcpy_alloc(os, os_alloc, os_offset, " Microsoft Windows XP");

	if (0 != GetSystemMetrics(87))		/* SM_MEDIACENTER */
		zbx_strcpy_alloc(os, os_alloc, os_offset, " Media Center Edition");
	else if (0 != GetSystemMetrics(88))	/* SM_STARTER */
		zbx_strcpy_alloc(os, os_alloc, os_offset, " Starter Edition");
	else if (0 != GetSystemMetrics(86))	/* SM_TABLETPC */
		zbx_strcpy_alloc(os, os_alloc, os_offset, " Tablet PC Edition");
	else if (0 != (vi->wSuiteMask & VER_SUITE_PERSONAL))
		zbx_strcpy_alloc(os, os_alloc, os_offset, " Home Edition");
	else
		zbx_strcpy_alloc(os, os_alloc, os_offset, " Professional");
}

static void	get_52_version(char **os, size_t *os_alloc, size_t *os_offset, OSVERSIONINFOEX *vi, SYSTEM_INFO *si)
{
	zbx_strcpy_alloc(os, os_alloc, os_offset, " Microsoft Windows");

	if (0 != GetSystemMetrics(89))			/* SM_SERVERR2 */
		zbx_strcpy_alloc(os, os_alloc, os_offset, " Server 2003 R2");
	else if (0 != (vi->wSuiteMask & 0x8000))	/* VER_SUITE_WH_SERVER */
		zbx_strcpy_alloc(os, os_alloc, os_offset, " Home Server");
	else if (VER_NT_WORKSTATION == vi->wProductType && PROCESSOR_ARCHITECTURE_AMD64 == si->wProcessorArchitecture)
		zbx_strcpy_alloc(os, os_alloc, os_offset, " XP Professional");
	else
		zbx_strcpy_alloc(os, os_alloc, os_offset, " Server 2003");

	if (VER_NT_WORKSTATION != vi->wProductType)
	{
		if (vi->wSuiteMask & VER_SUITE_COMPUTE_SERVER)
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Compute Cluster Edition");
		else if (vi->wSuiteMask & VER_SUITE_DATACENTER)
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Datacenter Edition");
		else if (vi->wSuiteMask & VER_SUITE_ENTERPRISE)
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Enterprise Edition");
		else if (vi->wSuiteMask & VER_SUITE_BLADE)
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Web Edition");
		else
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Standard Edition");
	}
}

static void	get_6x_version(char **os, size_t *os_alloc, size_t *os_offset, OSVERSIONINFOEX *vi, SYSTEM_INFO *si)
{
	typedef BOOL (WINAPI *PGPI)(DWORD, DWORD, DWORD, DWORD, PDWORD);

	PGPI	pGPI;
	DWORD	product_type;

	zbx_strcpy_alloc(os, os_alloc, os_offset, " Microsoft Windows");

	if (VER_NT_WORKSTATION == vi->wProductType)
	{
		switch (vi->dwMinorVersion)
		{
			case 0:
				zbx_strcpy_alloc(os, os_alloc, os_offset, " Vista");
				break;
			case 1:
				zbx_strcpy_alloc(os, os_alloc, os_offset, " 7");
				break;
			case 2:
				zbx_strcpy_alloc(os, os_alloc, os_offset, " 8");
				break;
		}
	}
	else
	{
		switch (vi->dwMinorVersion)
		{
			case 0:
				zbx_strcpy_alloc(os, os_alloc, os_offset, " Server 2008");
				break;
			case 1:
				zbx_strcpy_alloc(os, os_alloc, os_offset, " Server 2008 R2");
				break;
			case 2:
				zbx_strcpy_alloc(os, os_alloc, os_offset, " Server 2012");
				break;
		}
	}

	pGPI = (PGPI)GetProcAddress(GetModuleHandle(TEXT("kernel32.dll")), "GetProductInfo");

	pGPI(vi->dwMajorVersion, vi->dwMinorVersion, 0, 0, &product_type);

	/* use constants in order to support Windows 2000 */
	switch (product_type)
	{
		case 0x0001:	/* PRODUCT_ULTIMATE */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Ultimate Edition");
			break;
		case 0x0030:	/* PRODUCT_PROFESSIONAL */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Professional");
			break;
		case 0x0003:	/* PRODUCT_HOME_PREMIUM */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Home Premium Edition");
			break;
		case 0x0002:	/* PRODUCT_HOME_BASIC */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Home Basic Edition");
			break;
		case 0x0004:	/* PRODUCT_ENTERPRISE */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Enterprise Edition");
			break;
		case 0x0006:	/* PRODUCT_BUSINESS */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Business Edition");
			break;
		case 0x000B:	/* PRODUCT_STARTER */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Starter Edition");
			break;
		case 0x0012:	/* PRODUCT_CLUSTER_SERVER */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Cluster Server Edition");
			break;
		case 0x0008:	/* PRODUCT_DATACENTER_SERVER */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Datacenter Edition");
			break;
		case 0x000C:	/* PRODUCT_DATACENTER_SERVER_CORE */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Datacenter Edition (core installation)");
			break;
		case 0x000A:	/* PRODUCT_ENTERPRISE_SERVER */
		case 0x000F:	/* PRODUCT_ENTERPRISE_SERVER_IA64 */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Enterprise Edition");
			break;
		case 0x000E:	/* PRODUCT_ENTERPRISE_SERVER_CORE */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Enterprise Edition (core installation)");
			break;
		case 0x0009:	/* PRODUCT_SMALLBUSINESS_SERVER */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Small Business Server");
			break;
		case 0x0019:	/* PRODUCT_SMALLBUSINESS_SERVER_PREMIUM */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Small Business Server Premium Edition");
			break;
		case 0x0007:	/* PRODUCT_STANDARD_SERVER */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Standard Edition");
			break;
		case 0x000D:	/* PRODUCT_STANDARD_SERVER_CORE */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Standard Edition (core installation)");
			break;
		case 0x0011:	/* PRODUCT_WEB_SERVER */
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Web Server Edition");
			break;
	}
}

static void	get_cpu_type(char **os, size_t *os_alloc, size_t *os_offset, SYSTEM_INFO *si)
{
	switch (si->wProcessorArchitecture)
	{
		case PROCESSOR_ARCHITECTURE_INTEL:
			zbx_strcpy_alloc(os, os_alloc, os_offset, " x86");
			break;
		case PROCESSOR_ARCHITECTURE_AMD64:
			zbx_strcpy_alloc(os, os_alloc, os_offset, " x64");
			break;
		case PROCESSOR_ARCHITECTURE_IA64:
			zbx_strcpy_alloc(os, os_alloc, os_offset, " Intel Itanium-based");
			break;
	}
}
#endif

int	SYSTEM_UNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef _WINDOWS
	typedef void (WINAPI *PGNSI)(LPSYSTEM_INFO);

	DWORD		dwSize = 256;
	TCHAR		computer_name[256];
	SYSTEM_INFO	si;
	OSVERSIONINFOEX	vi;
	char		*os = NULL, *utf8;
	size_t		os_alloc = 256, os_offset = 0;
	PGNSI		pGNSI;

	/* Buffer size is chosen large enough to contain any DNS name, not just MAX_COMPUTERNAME_LENGTH + 1 */
	/* characters. MAX_COMPUTERNAME_LENGTH is usually less than 32, but it varies among systems, so we  */
	/* cannot use the constant in a precompiled Windows agent, which is expected to work on any system. */
	if (0 == GetComputerName(computer_name, &dwSize))
		*computer_name = '\0';

	memset(&si, 0, sizeof(si));
	memset(&vi, 0, sizeof(vi));

	vi.dwOSVersionInfoSize = sizeof(OSVERSIONINFOEX);
	if (TRUE != GetVersionEx((OSVERSIONINFO *)&vi))
		return SYSINFO_RET_FAIL;

	if (NULL != (pGNSI = (PGNSI)GetProcAddress(GetModuleHandle(TEXT("kernel32.dll")), "GetNativeSystemInfo")))
		pGNSI(&si);
	else
		GetSystemInfo(&si);

	os = zbx_malloc(os, os_alloc);

	zbx_strcpy_alloc(&os, &os_alloc, &os_offset, "Windows");

	if ('\0' != *computer_name)
	{
		utf8 = zbx_unicode_to_utf8(computer_name);
		zbx_snprintf_alloc(&os, &os_alloc, &os_offset, " %s", utf8);
		zbx_free(utf8);
	}

	zbx_snprintf_alloc(&os, &os_alloc, &os_offset, " %d.%d.%d",
			vi.dwMajorVersion, vi.dwMinorVersion, vi.dwBuildNumber);

	if (VER_PLATFORM_WIN32_NT == vi.dwPlatformId)
	{
		switch (vi.dwMajorVersion)
		{
			case 5:
				switch (vi.dwMinorVersion)
				{
					case 0:
						get_50_version(&os, &os_alloc, &os_offset, &vi);
						break;
					case 1:
						get_51_version(&os, &os_alloc, &os_offset, &vi);
						break;
					case 2:
						get_52_version(&os, &os_alloc, &os_offset, &vi, &si);
						break;
				}
				break;
			case 6:
				get_6x_version(&os, &os_alloc, &os_offset, &vi, &si);
				break;
		}
	}

	if ('\0' != *vi.szCSDVersion)
	{
		utf8 = zbx_unicode_to_utf8(vi.szCSDVersion);
		zbx_snprintf_alloc(&os, &os_alloc, &os_offset, " %s", utf8);
		zbx_free(utf8);
	}

	get_cpu_type(&os, &os_alloc, &os_offset, &si);

	SET_STR_RESULT(result, os);

	return SYSINFO_RET_OK;
#else
	return EXECUTE_STR(cmd, "uname -a", flags, result);
#endif
}

int	SYSTEM_HOSTNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef _WINDOWS
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
#endif
}
