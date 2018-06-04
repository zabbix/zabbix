/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
const OSVERSIONINFOEX		*zbx_win_getversion(void)
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
	char	*os = NULL;
	size_t	os_alloc = 0, os_offset = 0;
	char	*sysname = "Windows";
	char	*os_csname = NULL;
	char	*os_version = NULL;
	char	*os_caption = NULL;
	char	*os_csdversion = NULL;
	char	*proc_architecture = NULL;
	char	*proc_addresswidth = NULL;
	char	*wmi_namespace = "root\\cimv2";
	char	*arch = "<unknown architecture>";

	/* Emulates uname(2) (POSIX) since it is not provided natively by Windows by taking */
	/* the relevant values from Win32_OperatingSystem and Win32_Processor WMI classes.  */
	/* It was decided that in context of Windows OS ISA is more useful information than */
	/* CPU architecture. This is contrary to POSIX and uname(2) in Unix.                */

	zbx_wmi_get(wmi_namespace, "select CSName from Win32_OperatingSystem", &os_csname);
	zbx_wmi_get(wmi_namespace, "select Version from Win32_OperatingSystem", &os_version);
	zbx_wmi_get(wmi_namespace, "select Caption from Win32_OperatingSystem", &os_caption);
	zbx_wmi_get(wmi_namespace, "select CSDVersion from Win32_OperatingSystem", &os_csdversion);
	zbx_wmi_get(wmi_namespace, "select Architecture from Win32_Processor", &proc_architecture);
	zbx_wmi_get(wmi_namespace, "select AddressWidth from Win32_Processor", &proc_addresswidth);

	if (NULL != proc_architecture)
	{
		switch (atoi(proc_architecture))
		{
			case 0: arch = "x86"; break;
			case 6: arch = "ia64"; break;
			case 9:
				if (NULL != proc_addresswidth)
				{
					if (32 == atoi(proc_addresswidth))
						arch = "x86";
					else
						arch = "x64";
				}

				break;
		}
	}

	/* The comments indicate the relevant field in struct utsname (POSIX) that is used in uname(2). */
	zbx_snprintf_alloc(&os, &os_alloc, &os_offset, "%s %s %s %s%s%s %s",
		sysname,						/* sysname */
		os_csname ? os_csname : "<unknown nodename>",		/* nodename */
		os_version ? os_version : "<unknown release>",		/* release */
		os_caption ? os_caption : "<unknown version>",		/* version */
		os_caption && os_csdversion ? " " : "",
		os_caption && os_csdversion ? os_csdversion : "",	/* version (cont.) */
		arch);							/* machine */

	zbx_free(os_csname);
	zbx_free(os_version);
	zbx_free(os_caption);
	zbx_free(os_csdversion);
	zbx_free(proc_architecture);

	SET_STR_RESULT(result, os);

	return SYSINFO_RET_OK;
}
