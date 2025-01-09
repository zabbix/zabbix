/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxsysinfo.h"
#include "../sysinfo.h"

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

#define ZBX_REGKEY_VERSION	"SOFTWARE\\Microsoft\\Windows NT\\CurrentVersion"

/********************************************************************************
 *                                                                              *
 * Purpose: joins strings into one, placing separator in between them,          *
 *          skipping empty strings                                              *
 *                                                                              *
 * Parameters: separator - [IN] separator to place in between strings           *
 *             count     - [IN] number of strings                               *
 *             ...       - [IN] strings to join (strings can be empty  or NULL) *
 *                                                                              *
 ********************************************************************************/
static char	*join_nonempty_strs(const char *separator, size_t count, ...)
{
	char	*arg;
	va_list	args;
	char	**nonempty_strs;
	char	*res = NULL;
	size_t	num_nonempty = 0, str_size = 0;

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
		res = zbx_malloc(NULL, str_size * sizeof(char));
		res[0] = '\0';
		strcat(res, nonempty_strs[0]);

		for (size_t i = 1; i < num_nonempty; i++) {
			strcat(res, separator);
			strcat(res, nonempty_strs[i]);
		}
	}
	zbx_free(nonempty_strs);

	return res;
}

static WORD	get_processor_architecture(void)
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
	DWORD	szData = 0;
	wchar_t	*value = NULL;
	char	*ret = NULL;

	if (ERROR_SUCCESS == RegQueryValueEx(hKey, name, NULL, NULL, NULL, &szData))
	{
		value = zbx_malloc(NULL, szData + sizeof(wchar_t));

		/* syscall RegQueryValueEx does not guarantee that the returned string will be '\0' terminated */
		if (ERROR_SUCCESS != RegQueryValueEx(hKey, name, NULL, NULL, (LPBYTE)value, &szData))
			zbx_free(value);
		else
			value[szData / sizeof(wchar_t)] = L'\0';
	}

	if (NULL == value)
		return NULL;

	if (REG_DWORD == value_type)
		ret = zbx_dsprintf(NULL, "%d", (uint32_t)*(DWORD *)value);
	else
		ret = zbx_unicode_to_utf8(value);

	zbx_free(value);

	return ret;
}

static HKEY	open_registry_info_key(void)
{
	HKEY	handle = NULL;

	if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, TEXT(ZBX_REGKEY_VERSION), 0, KEY_READ, &handle))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to open registry key '%s'", ZBX_REGKEY_VERSION);
		return NULL;
	}

	return handle;
}

static char	*get_build_string(HKEY handle, int include_descriptor)
{
	char	*major = NULL, *minor = NULL, *str = NULL;

	if (NULL != (major = get_registry_value(handle, TEXT(ZBX_REGVALUE_MAJOR), REG_MULTI_SZ)))
		minor = get_registry_value(handle, TEXT(ZBX_REGVALUE_MINOR), REG_DWORD);

	str = join_nonempty_strs(".", 2, major, minor);
	zbx_free(major);
	zbx_free(minor);

	if (include_descriptor && 0 < strlen(str))
		return zbx_dsprintf(str, "Build %s", str);

	return str;
}

static char	*get_full_os_info(HKEY handle)
{
	char	*name, *lab, *build, *res = NULL;

	name = get_registry_value(handle, TEXT(ZBX_REGVALUE_PRODUCTNAME), REG_MULTI_SZ);
	lab = get_registry_value(handle, TEXT(ZBX_REGVALUE_BUILDLABEX), REG_MULTI_SZ);

	if (NULL == lab)
		lab = get_registry_value(handle, TEXT(ZBX_REGVALUE_BUILDLAB), REG_MULTI_SZ);
	build = get_build_string(handle, 1);

	res = join_nonempty_strs(" ", 3, name, lab, build);
	zbx_free(name);
	zbx_free(lab);
	zbx_free(build);

	return res;
}

static char	*get_pretty_os_info(HKEY handle)
{
	char	*name, *build, *sp_version, *res = NULL;

	name = get_registry_value(handle, TEXT(ZBX_REGVALUE_PRODUCTNAME), REG_MULTI_SZ);
	build = get_build_string(handle, 1);
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
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Could not open registry key " ));
		goto out;
	}

	type = get_rparam(request, 0);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "full"))
	{
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

	if (SYSINFO_RET_OK == ret)
	{
		SET_STR_RESULT(result, zbx_strdup(NULL, str));
		zbx_free(str);
	}
	else
	{
		/* if we were not able to get any data, no values could be retrieved */
		/* in error specify that ProductName is missing because it is required in all cases */
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Could not read registry value " ZBX_REGVALUE_PRODUCTNAME));
	}
out:
	RegCloseKey(handle);

	return ret;
}

int	system_sw_os_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#define SW_OS_GET_TYPE		"os_type"
#define SW_OS_GET_PROD_NAME	"product_name"
#define SW_OS_GET_ARCH		"architecture"
#define SW_OS_GET_BLD_MAJOR	"build_number"
#define SW_OS_GET_BLD_MINOR	"build_revision"
#define SW_OS_GET_BLD		"build"
#define SW_OS_GET_EDITION	"edition"
#define SW_OS_GET_COMPOSITION	"composition"
#define SW_OS_GET_DSPL_VER	"display_version"
#define SW_OS_GET_SP_VER	"sp_version"
#define SW_OS_GET_SP_BUILD	"sp_build"
#define SW_OS_GET_VER		"version"
#define SW_OS_GET_VER_PRETTY	"version_pretty"
#define SW_OS_GET_VER_FULL	"version_full"

	struct zbx_json	j;
	char		*str;
	const char	*arch;
	HKEY		handle = NULL;

	ZBX_UNUSED(request);

	if (0 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&j, SW_OS_GET_TYPE, "windows", ZBX_JSON_TYPE_STRING);

	if (NULL == (handle = open_registry_info_key()))
		goto out;

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_PRODUCTNAME), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, SW_OS_GET_PROD_NAME, str, ZBX_JSON_TYPE_STRING);
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
	zbx_json_addstring(&j, SW_OS_GET_ARCH, arch, ZBX_JSON_TYPE_STRING);

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_MAJOR), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, SW_OS_GET_BLD_MAJOR, str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_MINOR), REG_DWORD)))
	{
		zbx_json_addstring(&j, SW_OS_GET_BLD_MINOR, str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_build_string(handle, 0)))
	{
		zbx_json_addstring(&j, SW_OS_GET_BLD, str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_EDITION), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, SW_OS_GET_EDITION, str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_COMPOSITION), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, SW_OS_GET_COMPOSITION, str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_DISPLAYVERSION), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, SW_OS_GET_DSPL_VER, str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_CSDVERSION), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, SW_OS_GET_SP_VER, str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_CSDBUILDNUMBER), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, SW_OS_GET_SP_BUILD, str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_registry_value(handle, TEXT(ZBX_REGVALUE_VERSION), REG_MULTI_SZ)))
	{
		zbx_json_addstring(&j, SW_OS_GET_VER, str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

	if (NULL != (str = get_pretty_os_info(handle)))
	{
		zbx_json_addstring(&j, SW_OS_GET_VER_PRETTY, str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}

out:
	if (NULL != handle && NULL != (str = get_full_os_info(handle)))
	{
		zbx_json_addstring(&j, SW_OS_GET_VER_FULL, str, ZBX_JSON_TYPE_STRING);
		zbx_free(str);
	}
	else
		zbx_json_addstring(&j, SW_OS_GET_VER_FULL, "", ZBX_JSON_TYPE_STRING);

	RegCloseKey(handle);
	SET_STR_RESULT(result, strdup(j.buffer));
	zbx_json_free(&j);

	return SYSINFO_RET_OK;

#undef SW_OS_GET_TYPE
#undef SW_OS_GET_PROD_NAME
#undef SW_OS_GET_ARCH
#undef SW_OS_GET_BLD_MAJOR
#undef SW_OS_GET_BLD_MINOR
#undef SW_OS_GET_BLD
#undef SW_OS_GET_EDITION
#undef SW_OS_GET_COMPOSITION
#undef SW_OS_GET_DSPL_VER
#undef SW_OS_GET_SP_VER
#undef SW_OS_GET_SP_BUILD
#undef SW_OS_GET_VER
#undef SW_OS_GET_VER_PRETTY
#undef SW_OS_GET_VER_FULL
}
