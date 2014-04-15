/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "common.h"
#include "sysinfo.h"
#include "system.h"
#include "log.h"

#ifdef _WINDOWS
#	include "perfmon.h"
#	pragma comment(lib, "user32.lib")
#	pragma comment(lib, "advapi32.lib") /* Link to ADV API library to read registry */
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

/******************************************************************************
 *                                                                            *
 * Function: zbx_clean_win_version                                            *
 *                                                                            *
 * Purpose: frees resources allocated to store Windows version data           *
 *                                                                            *
 ******************************************************************************/
void	zbx_clean_win_version(zbx_win_version_t *os_version)
{
	zbx_free(os_version->ComputerName);
	zbx_free(os_version->CurrentVersion);
	zbx_free(os_version->CurrentBuildNumber);
	zbx_free(os_version->ProductName);
	zbx_free(os_version->CSDVersion);
	zbx_free(os_version->ProcessorArchitecture);
}

static char	*read_registry_value(HKEY hKey, LPCTSTR name)
{
	DWORD	szData;
	LPTSTR	value;
	char	*value_utf8 = NULL;

	if (ERROR_SUCCESS == RegQueryValueEx(hKey, name, NULL, NULL, NULL, &szData))
	{
		value = zbx_malloc(NULL, szData);
		if (ERROR_SUCCESS == RegQueryValueEx(hKey, name, NULL, NULL, (LPBYTE)value, &szData))
			value_utf8 = zbx_unicode_to_utf8(value);

		zbx_free(value);
	}

	return value_utf8;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_win_version                                              *
 *                                                                            *
 * Purpose: get Windows system UNAME form Windows registry                    *
 *                                                                            *
 * Return value:                                                              *
 *         SUCCESS - the Windows version data was retrieved successfully      *
 *         FAIL    - otherwise                                                *
 *                                                                            *
 * Author: Nikolajs Agafonovs                                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_win_version(zbx_win_version_t *os_version)
{

#define ZBX_REGKEY_VERSION		"SOFTWARE\\Microsoft\\Windows NT\\CurrentVersion"
#define ZBX_REGKEY_ENVIRONMENT		"System\\CurrentControlSet\\Control\\Session Manager\\Environment"

#define ZBX_REGVALUE_PRODUCTNAME	"ProductName"
#define ZBX_REGVALUE_CSDVERSION		"CSDVersion"
#define ZBX_REGVALUE_CURRENTBUILDNUMBER	"CurrentBuildNumber"
#define ZBX_REGVALUE_CURRENTVERSION	"CurrentVersion"
#define ZBX_REGVALUE_ARCHITECTURE	"PROCESSOR_ARCHITECTURE"

	const char	*__function_name = "zbx_get_win_version";
	int		ret = FAIL;

	/* Order of win_keys is vital.
	 * Version information in registry is stored in multiple keys */
	HKEY		h_key_registry;
	TCHAR		computer_name[256];
	DWORD		dwSize = 256;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, TEXT(ZBX_REGKEY_VERSION), 0, KEY_READ, &h_key_registry))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to open registry key '%s'", ZBX_REGKEY_VERSION);
		goto out;
	}

	if (NULL == (os_version->ProductName = read_registry_value(h_key_registry, TEXT(ZBX_REGVALUE_PRODUCTNAME))))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to read registry value '%s'", ZBX_REGVALUE_PRODUCTNAME);
		goto out;
	}
	if (NULL == (os_version->CSDVersion = read_registry_value(h_key_registry, TEXT(ZBX_REGVALUE_CSDVERSION))))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to read registry value '%s'", ZBX_REGVALUE_CSDVERSION);
		goto out;
	}
	if (NULL == (os_version->CurrentBuildNumber = read_registry_value(h_key_registry, TEXT(ZBX_REGVALUE_CURRENTBUILDNUMBER))))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to read registry value '%s'", ZBX_REGVALUE_CURRENTBUILDNUMBER);
		goto out;
	}
	if (NULL == (os_version->CurrentVersion = read_registry_value(h_key_registry, TEXT(ZBX_REGVALUE_CURRENTVERSION))))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to read registry value '%s'", ZBX_REGVALUE_CURRENTVERSION);
		goto out;
	}

	if (ERROR_SUCCESS != RegCloseKey(h_key_registry))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to close registry key '%s'", ZBX_REGKEY_VERSION);
		goto out;
	}

	if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, TEXT(ZBX_REGKEY_ENVIRONMENT), 0, KEY_READ, &h_key_registry))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to open registry key '%s'", ZBX_REGKEY_ENVIRONMENT);
		goto out;
	}

	if (NULL == (os_version->ProcessorArchitecture = read_registry_value(h_key_registry, TEXT(ZBX_REGVALUE_ARCHITECTURE))))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to read registry value '%s'", ZBX_REGVALUE_ARCHITECTURE);
		goto out;
	}

	if (ERROR_SUCCESS != RegCloseKey(h_key_registry))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to close registry key '%s'", ZBX_REGKEY_ENVIRONMENT);
		goto out;
	}

	/* Buffer size is chosen large enough to contain any DNS name, not just MAX_COMPUTERNAME_LENGTH + 1 */
	/* characters. MAX_COMPUTERNAME_LENGTH is usually less than 32, but it varies among systems, so we  */
	/* cannot use the constant in a precompiled Windows agent, which is expected to work on any system. */
	if (0 == GetComputerName(computer_name, &dwSize))
		*computer_name = TEXT('\0');

	os_version->ComputerName = zbx_unicode_to_utf8(computer_name);

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
		zbx_clean_win_version(os_version);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %s", __function_name, strerror_from_system(ret));
	return ret;
}
#endif

int	SYSTEM_UNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef _WINDOWS
	zbx_win_version_t	os_version_info = {NULL};
	char			*os = NULL;
	size_t			os_alloc = 256, os_offset = 0;

	os = zbx_malloc(os, os_alloc);

	if (SUCCEED == zbx_get_win_version(&os_version_info))
	{
		zbx_snprintf_alloc(&os, &os_alloc, &os_offset, "Windows %s %s.%s %s %s %s",
				os_version_info.ComputerName,
				os_version_info.CurrentVersion,
				os_version_info.CurrentBuildNumber,
				os_version_info.ProductName,
				os_version_info.CSDVersion,
				os_version_info.ProcessorArchitecture
				);

		zbx_clean_win_version(&os_version_info);
	}
	else
	{
		return SYSINFO_RET_FAIL;
	}

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
