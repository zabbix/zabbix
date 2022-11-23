/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbxsysinfo.h"
#include "../sysinfo.h"

#include "log.h"
#include "zbxwin32.h"
#include "zbxjson.h"
#include "zbxstr.h"

#define ZBX_REGVALUE_PRODUCTNAME	"ProductName"
#define ZBX_REGVALUE_BUILDLABEX		"BuildLabEx"
#define ZBX_REGVALUE_BUILDLAB		"BuildLab"
#define ZBX_REGVALUE_MAJOR		"CurrentBuildNumber"
#define ZBX_REGVALUE_MINOR		"UBR"
#define ZBX_REGVALUE_CSDVERSION		"CSDVersion"
#define ZBX_REGVALUE_CSDBUILDNUMBER	"CSDBuildNumber"
#define ZBX_REGVALUE_EDITION		"EditionID"
#define ZBX_REGVALUE_COMPOSITION	"CompositionEditionID"
#define ZBX_REGVALUE_DISPLAYVERSION	"DisplayVersion"
#define ZBX_REGVALUE_VERSION		"CurrentVersion"

static WORD	get_processor_architecture()
{
	typedef void (WINAPI *PGNSI)(LPSYSTEM_INFO);

	SYSTEM_INFO	si;
	PGNSI		pGNSI;

	memset(&si, 0, sizeof(si));

	if (NULL != (pGNSI = (PGNSI)GetProcAddress(GetModuleHandle(TEXT("kernel32.dll")), "GetNativeSystemInfo")))
		pGNSI(&si);
	else
		GetSystemInfo(&si);

	return si.wProcessorArchitecture;
}

int	system_sw_arch(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	typedef void (WINAPI *PGNSI)(LPSYSTEM_INFO);

	const char	*arch;

	switch (get_processor_architecture())
	{
		case PROCESSOR_ARCHITECTURE_INTEL:
			arch = "x86";
			break;
		case PROCESSOR_ARCHITECTURE_AMD64:
			arch = "x64";
			break;
		case PROCESSOR_ARCHITECTURE_IA64:
			arch = "Intel Itanium-based";
			break;
		default:
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown processor architecture."));
			return SYSINFO_RET_FAIL;
	}

	SET_STR_RESULT(result, zbx_strdup(NULL, arch));

	return SYSINFO_RET_OK;
}

static char	*get_registry_value(HKEY hKey, LPCTSTR name, DWORD value_type)
{
	DWORD	szData;
	wchar_t	*value = NULL;
	char	*ret = NULL;

	if (ERROR_SUCCESS == RegQueryValueEx(hKey, name, NULL, NULL, NULL, &szData))
	{
		value = zbx_malloc(NULL, szData);
		if (ERROR_SUCCESS != RegQueryValueEx(hKey, name, NULL, NULL, (LPBYTE)value, &szData))
		{
			zbx_free(value);
			value = NULL;
		}
	}

	if (NULL == value)
		return NULL;

	if (value_type == REG_DWORD)
		ret = zbx_dsprintf(NULL, "%d", (uint32_t)*(DWORD *)value);
	else
		ret = zbx_unicode_to_utf8(value);
	zbx_free(value);

	return ret;
}

static HKEY	open_registry_info_key(void)
{
#define ZBX_REGKEY_VERSION	"SOFTWARE\\Microsoft\\Windows NT\\CurrentVersion"
	HKEY	handle = NULL;

	if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, TEXT(ZBX_REGKEY_VERSION), 0, KEY_READ, &handle))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to open registry key '%s'", ZBX_REGKEY_VERSION);
		return NULL;
	}

	return handle;
#undef ZBX_REGKEY_VERSION
}

static char	*get_build_string(HKEY handle)
{
	char	*tmp_str, *str = NULL;

	if (NULL != (tmp_str = get_registry_value(handle, TEXT(ZBX_REGVALUE_MAJOR), REG_MULTI_SZ)))
	{
		/* 128 characters should be plenty for major and minor build numbers for years to come */
		str = zbx_calloc(NULL, 128, sizeof(char));

		strcat(str, "Build ");
		strcat(str, tmp_str);
		zbx_free(tmp_str);

		if (NULL != (tmp_str = get_registry_value(handle, TEXT(ZBX_REGVALUE_MINOR), REG_DWORD)))
		{
			strcat(str, ".");
			strcat(str, tmp_str);
			zbx_free(tmp_str);
		}
	}

	return str;
}

static char	*join_nonempty_strs(const char *separator, size_t count, ...)
{
	char	*arg;
	va_list		args;
	char	**nonempty_strs;
	char		*res = NULL;
	size_t		num_nonempty = 0, str_size = 0;

	nonempty_strs = zbx_malloc(NULL, sizeof(char *) * count);

	va_start(args, count);

	for (size_t i = 0; i < count; i++)
	{
		arg = va_arg(args, char *);
		if (NULL != arg && 0 < strlen(arg))
		{
			str_size += strlen(arg) + strlen(separator);
			nonempty_strs[num_nonempty++] = arg;
		}
	}

	va_end(args);

	if (0 < num_nonempty)
	{
		res = zbx_calloc('\0', str_size, sizeof(char));
		strcat(res, nonempty_strs[0]);

		for (size_t i = 1; i < num_nonempty; i++) {
			strcat(res, separator);
			strcat(res, nonempty_strs[i]);
		}
	}
	zbx_free(nonempty_strs);

	return res;
}

static char	*get_full_os_info(HKEY handle)
{
	char	*res = NULL;
	char	*name, *lab, *build;

	name = get_registry_value(handle, TEXT(ZBX_REGVALUE_PRODUCTNAME), REG_MULTI_SZ);
	lab = get_registry_value(handle, TEXT(ZBX_REGVALUE_BUILDLABEX), REG_MULTI_SZ);

	if (NULL == lab)
		lab = get_registry_value(handle, TEXT(ZBX_REGVALUE_BUILDLAB), REG_MULTI_SZ);
	build = get_build_string(handle);

	res = join_nonempty_strs(" ", 3, name, lab, build);
	zbx_free(name);
	zbx_free(lab);
	zbx_free(build);

	return res;
}

static char	*get_pretty_os_info(HKEY handle)
{
	char	*res = NULL;
	char	*name, *build, *sp_version;

	name = get_registry_value(handle, TEXT(ZBX_REGVALUE_PRODUCTNAME), REG_MULTI_SZ);
	build = get_build_string(handle);
	sp_version = get_registry_value(handle, TEXT(ZBX_REGVALUE_CSDVERSION), REG_MULTI_SZ);

	res = join_nonempty_strs(" ", 3, name, build, sp_version);
	zbx_free(name);
	zbx_free(build);
	zbx_free(sp_version);

	return res;
}

int	system_sw_os(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*type, *str;
	int	ret = SYSINFO_RET_FAIL;
	HKEY	handle = NULL;

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return ret;
	}

	if (NULL == (handle = open_registry_info_key()))
		goto out;
	type = get_rparam(request, 0);


	if (NULL == type || '\0' == *type || 0 == strcmp(type, "full")) {
		if (NULL != (str = get_full_os_info(handle)))
			ret = SYSINFO_RET_OK;
	}
	else if (0 == strcmp(type, "short"))
	{
		if (NULL != (str = get_pretty_os_info(handle)))
			ret = SYSINFO_RET_OK;
	}
	else if (0 == strcmp(type, "name"))
	{
		if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_PRODUCTNAME), REG_MULTI_SZ)))
			ret = SYSINFO_RET_OK;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return ret;
	}


	if (SYSINFO_RET_OK == ret) {
		SET_STR_RESULT(result, zbx_strdup(NULL, str));
		zbx_free(str);
	}
	else
	{
		/* if we were not able to get any data, no values could be retrived */
		/* in error specify that ProductName is missing because it is required in all cases */
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Could not read registry value " ZBX_REGVALUE_PRODUCTNAME));
	}
out:
	RegCloseKey(handle);
	return ret;

}

int	system_sw_os_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct zbx_json	j;;
	char		*str;
	const char	*arch;
	HKEY	handle = NULL;

	ZBX_UNUSED(request);
	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&j, "os_type", "windows", ZBX_JSON_TYPE_STRING);

	if (NULL == (handle = open_registry_info_key()))
		goto out;

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_PRODUCTNAME), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, "product_name", str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	switch(get_processor_architecture())
	{
		case PROCESSOR_ARCHITECTURE_AMD64:
			arch = "x86_64";
			break;
		case PROCESSOR_ARCHITECTURE_INTEL:
			arch = "x86";
			break;
		case PROCESSOR_ARCHITECTURE_ARM:
			arch = "arm";
			break;
		case PROCESSOR_ARCHITECTURE_ARM64:
			arch = "arm64";
			break;
		case PROCESSOR_ARCHITECTURE_IA64:
			arch = "Intel Itanium";
			break;
		default:
			arch = "unknown";
	}
	zbx_json_addstring(&j, "architecture", arch, ZBX_JSON_TYPE_STRING);

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_MAJOR), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, "major", str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);

	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_MINOR), REG_DWORD)))
	{
		zbx_json_addstring(&j, "minor", str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_EDITION), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, "edition", str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_COMPOSITION), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, "composition", str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_DISPLAYVERSION), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, "display_version", str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_CSDVERSION), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, "sp_version", str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_CSDBUILDNUMBER), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, "sp_build", str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_VERSION), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, "version", str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_pretty_os_info(handle)))
	{
		zbx_json_addstring(&j, "version_pretty", str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

out:
	if (NULL != handle && NULL != (str = get_full_os_info(handle)))
	{
		zbx_json_addstring(&j, "version_full", str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}
	else
		zbx_json_addstring(&j, "version_full", "", ZBX_JSON_TYPE_STRING);

	RegCloseKey(handle);
	zbx_json_close(&j);
	SET_STR_RESULT(result, strdup(j.buffer));
	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}
