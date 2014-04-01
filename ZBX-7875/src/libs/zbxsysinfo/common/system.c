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
 * Function: get_win_version                                                  *
 *                                                                            *
 * Purpose: get Windows system UNAME form Windows registry                    *
 *                                                                            *
 * Return value:                                                              *
 *         SUCCESS = struct OS_WIN_VERSION                                    *
 *         FAIL if some of registry operations can not be done                *
 *                                                                            *
 * Author: Nikolajs Agafonovs                                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_win_version(zbx_win_version_t *os_version)
{
	const char	*__function_name = "get_win_version";
	int		ret = FAIL;

	/* Order of win_keys is vital.
	 * Version information in registry is stored in multiple keys */
	const char	*win_keys[5] = {
			"ProductName",
			"CSDVersion",
			"CurrentBuild",
			"CurrentVersion",
			"PROCESSOR_ARCHITECTURE"};
	const char	sys_key_1[] = "SOFTWARE\\Microsoft\\Windows NT\\CurrentVersion";
	const char	sys_key_2[] = "System\\CurrentControlSet\\Control\\Session Manager\\Environment";
	int		i;
	HKEY		h_key_registry;
	DWORD		dw_buffer;
	char		*lp_name_strings;
	wchar_t		*wsource;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	wsource = zbx_utf8_to_unicode(sys_key_1);

	lp_name_strings = zbx_malloc(&lp_name_strings, 256);

	if(ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, wsource, 0, KEY_READ, &h_key_registry))
		goto out;

	zbx_free(wsource);

	for (i = 0; i < 4; i++)
	{
		wsource = zbx_utf8_to_unicode(win_keys[i]);

		if (ERROR_SUCCESS != RegQueryValueEx(h_key_registry, wsource, NULL, NULL,
				(LPBYTE)lp_name_strings, &dw_buffer))
		{
			goto out;
		}
		else
		{
			switch (i)
			{
				case 0:
					zbx_snprintf(os_version->ProductName, sizeof(os_version->ProductName),
							zbx_unicode_to_utf8((LPCTSTR)lp_name_strings));
					break;
				case 1:
					zbx_snprintf(os_version->CSDVersion, sizeof(os_version->CSDVersion),
							zbx_unicode_to_utf8((LPCTSTR)lp_name_strings));
					break;
				case 2:
					zbx_snprintf(os_version->CurrentBuild, sizeof(os_version->CurrentBuild),
							zbx_unicode_to_utf8((LPCTSTR)lp_name_strings));
					break;
				case 3:
					zbx_snprintf(os_version->CurrentVersion, sizeof(os_version->CurrentVersion),
							zbx_unicode_to_utf8((LPCTSTR)lp_name_strings));
					break;
			}
		}

		zbx_free(wsource);
	}

	if(ERROR_SUCCESS != RegCloseKey(h_key_registry))
		goto out;

	wsource = zbx_utf8_to_unicode(sys_key_2);

	if(ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, wsource, 0, KEY_READ, &h_key_registry))
		goto out;

	zbx_free(wsource);

	wsource = zbx_utf8_to_unicode(win_keys[4]);

	if(ERROR_SUCCESS != RegQueryValueEx(h_key_registry, wsource, NULL, NULL,
			(LPBYTE)lp_name_strings, &dw_buffer))
		goto out;
	else
		zbx_snprintf(os_version->ProcessorArchitecture, sizeof(os_version->ProcessorArchitecture),
				zbx_unicode_to_utf8( (LPCTSTR)lp_name_strings));

	zbx_free(wsource);

	if(ERROR_SUCCESS != RegCloseKey(h_key_registry))
		goto out;

	if (0 != gethostname(os_version->ComputerName, sizeof(os_version->ComputerName)))
		goto out;
	else
		zbx_strupper(os_version->ComputerName);

	ret = SUCCEED;
out:
	zbx_free(lp_name_strings);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %s", __function_name, strerror_from_system(ret));
	return ret;
}
#endif

int	SYSTEM_UNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef _WINDOWS
	zbx_win_version_t	os_version_info;
	char			*os = NULL;
	size_t			os_alloc, os_offset = 0;

	os_alloc =  sizeof(os_version_info) + 14;

	os = zbx_malloc(os, os_alloc);

	memset(&os_version_info, '\0', sizeof(os_version_info));

	if (0 == zbx_get_win_version(&os_version_info))
	{
		zbx_snprintf_alloc(&os, &os_alloc, &os_offset, "Windows %s %s.%s %s %s %s",
				os_version_info.ComputerName,
				os_version_info.CurrentVersion,
				os_version_info.CurrentBuild,
				os_version_info.ProductName,
				os_version_info.CSDVersion,
				os_version_info.ProcessorArchitecture
				);
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
