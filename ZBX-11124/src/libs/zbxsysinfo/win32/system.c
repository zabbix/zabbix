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

#include "sysinfo.h"
#include "log.h"
#include "perfmon.h"
#pragma comment(lib, "user32.lib")

static void	get_50_version(char **os, size_t *os_alloc, size_t *os_offset, const OSVERSIONINFOEX *vi)
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

static void	get_51_version(char **os, size_t *os_alloc, size_t *os_offset, const OSVERSIONINFOEX *vi)
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

static void	get_52_version(char **os, size_t *os_alloc, size_t *os_offset, const OSVERSIONINFOEX *vi,
		const SYSTEM_INFO *si)
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

static void	get_6x_version(char **os, size_t *os_alloc, size_t *os_offset, const OSVERSIONINFOEX *vi,
		const SYSTEM_INFO *si)
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
			case 3:
				zbx_strcpy_alloc(os, os_alloc, os_offset, " 8.1");
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
			case 3:
				zbx_strcpy_alloc(os, os_alloc, os_offset, " Server 2012 R2");
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

static void	get_cpu_type(char **os, size_t *os_alloc, size_t *os_offset, const SYSTEM_INFO *si)
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

/******************************************************************************
 *                                                                            *
 * Function: read_registry_value                                              *
 *                                                                            *
 * Purpose: read value from Windows registry                                  *
 *                                                                            *
 ******************************************************************************/
static wchar_t	*read_registry_value(HKEY hKey, LPCTSTR name)
{
	DWORD	szData;
	wchar_t	*value = NULL;

	if (ERROR_SUCCESS == RegQueryValueEx(hKey, name, NULL, NULL, NULL, &szData))
	{
		value = zbx_malloc(NULL, szData);
		if (ERROR_SUCCESS != RegQueryValueEx(hKey, name, NULL, NULL, (LPBYTE)value, &szData))
			zbx_free(value);
	}

	return value;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_win_getversion                                               *
 *                                                                            *
 * Purpose: get Windows version information                                   *
 *                                                                            *
 ******************************************************************************/
const OSVERSIONINFOEX		*zbx_win_getversion()
{
#	define ZBX_REGKEY_VERSION		"SOFTWARE\\Microsoft\\Windows NT\\CurrentVersion"
#	define ZBX_REGVALUE_CURRENTVERSION	"CurrentVersion"
#	define ZBX_REGVALUE_CURRENTBUILDNUMBER	"CurrentBuildNumber"
#	define ZBX_REGVALUE_CSDVERSION		"CSDVersion"

#	define ZBX_REGKEY_PRODUCT		"System\\CurrentControlSet\\Control\\ProductOptions"
#	define ZBX_REGVALUE_PRODUCTTYPE		"ProductType"

	static OSVERSIONINFOEX	vi = {sizeof(OSVERSIONINFOEX)};

	OSVERSIONINFOEX		*pvi = NULL;
	HKEY			h_key_registry = NULL;
	wchar_t			*key_value = NULL, *ptr;

	if (0 != vi.dwMajorVersion)
		return &vi;

	if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, TEXT(ZBX_REGKEY_VERSION), 0, KEY_READ, &h_key_registry))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to open registry key '%s'", ZBX_REGKEY_VERSION);
		goto out;
	}

	if (NULL == (key_value = read_registry_value(h_key_registry, TEXT(ZBX_REGVALUE_CURRENTVERSION))))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to read registry value '%s'", ZBX_REGVALUE_CURRENTVERSION);
		goto out;
	}

	if (NULL != (ptr = wcschr(key_value, TEXT('.'))))
	{
		*ptr++ = L'\0';
		vi.dwMinorVersion = _wtoi(ptr);
	}

	vi.dwMajorVersion = _wtoi(key_value);

	zbx_free(key_value);

	if (6 > vi.dwMajorVersion || 2 > vi.dwMinorVersion)
	{
		GetVersionEx((OSVERSIONINFO *)&vi);
	}
	else
	{
		if (NULL != (key_value = read_registry_value(h_key_registry, TEXT(ZBX_REGVALUE_CSDVERSION))))
		{
			wcscpy_s(vi.szCSDVersion, sizeof(vi.szCSDVersion) / sizeof(*vi.szCSDVersion), key_value);

			zbx_free(key_value);
		}

		if (NULL == (key_value = read_registry_value(h_key_registry, TEXT(ZBX_REGVALUE_CURRENTBUILDNUMBER))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "failed to read registry value '%s'",
					ZBX_REGVALUE_CURRENTBUILDNUMBER);
			goto out;
		}

		vi.dwBuildNumber = _wtoi(key_value);
		zbx_free(key_value);

		RegCloseKey(h_key_registry);
		h_key_registry = NULL;

		if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, TEXT(ZBX_REGKEY_PRODUCT), 0, KEY_READ,
				&h_key_registry))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "failed to open registry key '%s'", ZBX_REGKEY_PRODUCT);
			goto out;
		}

		if (NULL == (key_value = read_registry_value(h_key_registry, TEXT(ZBX_REGVALUE_PRODUCTTYPE))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "failed to read registry value '%s'", ZBX_REGVALUE_PRODUCTTYPE);
			goto out;
		}

		if (0 == wcscmp(key_value, L"WinNT"))
			vi.wProductType = 1;
		else if (0 == wcscmp(key_value, L"LenmanNT"))
			vi.wProductType = 2;
		else if (0 == wcscmp(key_value, L"ServerNT"))
			vi.wProductType = 3;

		zbx_free(key_value);

		vi.dwPlatformId = VER_PLATFORM_WIN32_NT;
	}

	pvi = &vi;
out:
	if (NULL != h_key_registry)
		RegCloseKey(h_key_registry);

	return pvi;
}

int	SYSTEM_UNAME(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	typedef void (WINAPI *PGNSI)(LPSYSTEM_INFO);

	DWORD			dwSize = 256;
	TCHAR			computer_name[256];
	SYSTEM_INFO		si;
	const OSVERSIONINFOEX	*vi;
	char			*os = NULL, *utf8;
	size_t			os_alloc = 256, os_offset = 0;
	PGNSI			pGNSI;

	/* Buffer size is chosen large enough to contain any DNS name, not just MAX_COMPUTERNAME_LENGTH + 1 */
	/* characters. MAX_COMPUTERNAME_LENGTH is usually less than 32, but it varies among systems, so we  */
	/* cannot use the constant in a precompiled Windows agent, which is expected to work on any system. */
	if (0 == GetComputerName(computer_name, &dwSize))
		*computer_name = '\0';

	memset(&si, 0, sizeof(si));

	if (NULL == (vi = zbx_win_getversion()))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot retrieve system version."));
		return SYSINFO_RET_FAIL;
	}

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
			vi->dwMajorVersion, vi->dwMinorVersion, vi->dwBuildNumber);

	if (VER_PLATFORM_WIN32_NT == vi->dwPlatformId)
	{
		switch (vi->dwMajorVersion)
		{
			case 5:
				switch (vi->dwMinorVersion)
				{
					case 0:
						get_50_version(&os, &os_alloc, &os_offset, vi);
						break;
					case 1:
						get_51_version(&os, &os_alloc, &os_offset, vi);
						break;
					case 2:
						get_52_version(&os, &os_alloc, &os_offset, vi, &si);
						break;
				}
				break;
			case 6:
				get_6x_version(&os, &os_alloc, &os_offset, vi, &si);
				break;
		}
	}

	if ('\0' != *vi->szCSDVersion)
	{
		utf8 = zbx_unicode_to_utf8(vi->szCSDVersion);
		zbx_snprintf_alloc(&os, &os_alloc, &os_offset, " %s", utf8);
		zbx_free(utf8);
	}

	get_cpu_type(&os, &os_alloc, &os_offset, &si);

	SET_STR_RESULT(result, os);

	return SYSINFO_RET_OK;
}
