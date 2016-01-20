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

void	zbx_wmi_get(char *wmi_namespace, char *wmi_query, char **utf8_value);

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
	char	*os = NULL;
	size_t	os_alloc = 0, os_offset = 0;
	char	*sysname = "Windows";
	char	*nodename_csname = NULL;
	char	*release_version = NULL;
	char	*version_caption = NULL;
	char	*version_csdversion = NULL;
	char	*machine_osarchitecture = NULL;
	char	*wmi_namespace = "root\\cimv2";

	zbx_wmi_get(wmi_namespace, "select CSName from Win32_OperatingSystem", &nodename_csname);
	zbx_wmi_get(wmi_namespace, "select Version from Win32_OperatingSystem", &release_version);
	zbx_wmi_get(wmi_namespace, "select Caption from Win32_OperatingSystem", &version_caption);
	zbx_wmi_get(wmi_namespace, "select CSDVersion from Win32_OperatingSystem", &version_csdversion);
	zbx_wmi_get(wmi_namespace, "select OSArchitecture from Win32_OperatingSystem", &machine_osarchitecture);

	zbx_snprintf_alloc(&os, &os_alloc, &os_offset, "%s %s %s %s%s%s %s",
		sysname,
		nodename_csname ? nodename_csname : "<unknown nodename>",
		release_version ? release_version : "<unknown release>",
		version_caption ? version_caption : "<unknown version>",
		version_csdversion ? " " : "",
		version_csdversion ? version_csdversion : "",
		machine_osarchitecture ? machine_osarchitecture : "32-bit");

	zbx_free(nodename_csname);
	zbx_free(release_version);
	zbx_free(version_caption);
	zbx_free(version_csdversion);
	zbx_free(machine_osarchitecture);

	SET_STR_RESULT(result, os);

	return SYSINFO_RET_OK;
}
