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

#include "sysinfo.h"
#include "base64.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "zbxregexp.h"
#include <locale.h>
#include <winreg.h>

#define ZBX_SYSINFO_REGISTRY_TAG_FULLKEY	"fullkey"
#define ZBX_SYSINFO_REGISTRY_TAG_LASTKEY	"lastsubkey"
#define ZBX_SYSINFO_REGISTRY_TAG_NAME		"name"
#define ZBX_SYSINFO_REGISTRY_TAG_DATA		"data"
#define ZBX_SYSINFO_REGISTRY_TAG_TYPE		"type"

#define MAX_KEY_LENGTH			255
#define MAX_DATA_LENGTH			65534
#define MAX_VALUE_NAME			16383
#define MAX_FULLKEY_LENGTH		4096

#define REGISTRY_DISCOVERY_MODE_KEYS	0
#define REGISTRY_DISCOVERY_MODE_VALUES	1

static HKEY	get_hkey_from_fullkey(char *fullkey)
{
	if (0 == strcmp("HKEY_CLASSES_ROOT", fullkey) || 0 == strcmp("HKCR", fullkey))
		return HKEY_CLASSES_ROOT;
	else if (0 == strcmp("HKEY_CURRENT_CONFIG", fullkey) || 0 == strcmp("HKCC", fullkey))
		return HKEY_CURRENT_CONFIG;
	else if (0 == strcmp("HKEY_CURRENT_USER", fullkey) || 0 == strcmp("HKCU", fullkey))
		return HKEY_CURRENT_USER;
	else if (0 == strcmp("HKEY_CURRENT_USER_LOCAL_SETTINGS", fullkey) || 0 == strcmp("HKCULS", fullkey))
		return HKEY_CURRENT_USER_LOCAL_SETTINGS;
	else if (0 == strcmp("HKEY_LOCAL_MACHINE", fullkey) || 0 == strcmp("HKLM", fullkey))
		return HKEY_LOCAL_MACHINE;
	else if (0 == strcmp("HKEY_PERFORMANCE_DATA", fullkey) || 0 == strcmp("HKPD", fullkey))
		return HKEY_PERFORMANCE_DATA;
	else if (0 == strcmp("HKEY_PERFORMANCE_NLSTEXT", fullkey) || 0 == strcmp("HKPN", fullkey))
		return HKEY_PERFORMANCE_NLSTEXT;
	else if (0 == strcmp("HKEY_PERFORMANCE_TEXT", fullkey) || 0 == strcmp("HKPT", fullkey))
		return HKEY_PERFORMANCE_TEXT;
	else if (0 == strcmp("HKEY_USERS", fullkey) || 0 == strcmp("HKU", fullkey))
		return HKEY_USERS;

	return 0;
}

static const char	*registry_type_to_string(DWORD type)
{
	switch (type)
	{
		case REG_BINARY:
			return "REG_BINARY";
		case REG_DWORD:
			return "REG_DWORD";
		case REG_EXPAND_SZ:
			return "REG_EXPAND_SZ";
		case REG_LINK:
			return "REG_LINK";
		case REG_MULTI_SZ:
			return "REG_MULTI_SZ";
		case REG_NONE:
			return "REG_NONE";
		case REG_QWORD:
			return "REG_QWORD";
		case REG_SZ:
			return "REG_SZ";
	}

	return "Unknown";
}

static void	registry_get_multistring_value(const wchar_t *wbuffer, struct zbx_json *j)
{
	char	*buffer;

	while (L'\0' != *wbuffer)
	{
		buffer = zbx_unicode_to_utf8(wbuffer);
		zbx_json_addstring(j, NULL, buffer, ZBX_JSON_TYPE_STRING);
		zbx_free(buffer);
		wbuffer += wcslen(wbuffer) + 1 ;
	}
}

static int	convert_value(DWORD type, const char *value, DWORD value_len, char **out)
{
	struct zbx_json	j;

	switch (type) {
		case REG_BINARY:
			str_base64_encode_dyn(value, out, (int)value_len);
			return SUCCEED;
		case REG_DWORD:
			*out = zbx_dsprintf(NULL, "%u", *(DWORD *)value);
			return SUCCEED;
		case REG_QWORD:
			*out = zbx_dsprintf(NULL, "%lu", *(DWORDLONG *)value);
			return SUCCEED;
		case REG_MULTI_SZ:
			zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
			zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

			registry_get_multistring_value((wchar_t *)value, &j);

			zbx_json_close(&j);
			*out = zbx_strdup(NULL, j.buffer);
			zbx_json_free(&j);
			return SUCCEED;
		case REG_NONE:
			*out = NULL;
			return SUCCEED;
		case REG_SZ:
		case REG_EXPAND_SZ:
			*out = zbx_unicode_to_utf8((wchar_t *)value);
			return SUCCEED;
		default:
			return FAIL;
	}
}

ZBX_PTR_VECTOR_DECL(wchar_ptr, wchar_t *)
ZBX_PTR_VECTOR_IMPL(wchar_ptr, wchar_t *)

static void	discovery_get_regkey_values(HKEY hKey, wchar_t *current_subkey, struct zbx_json *j, int mode,
		wchar_t *root, const char *regexp)
{
	wchar_t			achClass[MAX_PATH] = TEXT(""), achValue[MAX_VALUE_NAME];
	DWORD			cchClassName = MAX_PATH, cSubKeys=0, cValues, cbName, i, retCode,
				cchValue = MAX_VALUE_NAME;
	char			*uroot, *usubkey;
	zbx_vector_wchar_ptr_t	wsubkeys;

	retCode = RegQueryInfoKey(hKey, achClass, &cchClassName, NULL, &cSubKeys, NULL, NULL, &cValues, NULL, NULL,
			NULL, NULL);

	zbx_vector_wchar_ptr_create(&wsubkeys);

	uroot = zbx_unicode_to_utf8(root);
	usubkey = zbx_unicode_to_utf8(current_subkey);

	if (REGISTRY_DISCOVERY_MODE_KEYS == mode)
	{
		if (*usubkey != '\0')
		{
			zbx_json_addobject(j, NULL);
			zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_FULLKEY, uroot, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_LASTKEY, usubkey, ZBX_JSON_TYPE_STRING);
			zbx_json_close(j);
		}

		zbx_free(uroot);
		zbx_free(usubkey);
	}

	for (i = 0; i < cSubKeys; i++)
	{
		cbName = MAX_KEY_LENGTH;
		retCode = RegEnumKeyEx(hKey, i, achClass, &cbName, NULL, NULL, NULL, NULL);

		if (ERROR_SUCCESS == retCode)
			zbx_vector_wchar_ptr_append(&wsubkeys, wcsdup(achClass));
	}

	for (i = 0; i < (DWORD)wsubkeys.values_num; i++)
	{
		HKEY	hSubkey;
		wchar_t	wnew_root[MAX_FULLKEY_LENGTH];
		wchar_t	*wsubkey;

		wsubkey = wsubkeys.values[i];

		if (0 == wcscmp(wsubkey, L""))
			continue;

		_snwprintf_s(wnew_root, MAX_FULLKEY_LENGTH, MAX_FULLKEY_LENGTH, L"%s\\%s", root, wsubkey);

		if (ERROR_SUCCESS == RegOpenKeyEx(hKey, wsubkey, 0, KEY_READ, &hSubkey))
			discovery_get_regkey_values(hSubkey, wsubkey, j, mode, wnew_root, regexp);

		RegCloseKey(hSubkey);
	}

	zbx_vector_wchar_ptr_clear_ext(&wsubkeys, zbx_ptr_free);
	zbx_vector_wchar_ptr_destroy(&wsubkeys);

	if (REGISTRY_DISCOVERY_MODE_VALUES == mode && 0 != cValues)
	{
		DWORD	buffer_alloc = 1024;
		char	*buffer;

		buffer = zbx_malloc(NULL, buffer_alloc);

		for (i = 0, retCode = ERROR_SUCCESS; i < cValues; i++)
		{
			DWORD	valueType, value_len = buffer_alloc;
			char	*uvaluename, *out = NULL;

			cchValue = MAX_VALUE_NAME;
			achValue[0] = L'\0';

			if (ERROR_MORE_DATA == (retCode = RegEnumValue(hKey, i, achValue, &cchValue, NULL, &valueType,
				buffer, &value_len)))
			{
				buffer = zbx_realloc(buffer, value_len);
				buffer_alloc = value_len;

				cchValue = MAX_VALUE_NAME;
				retCode = RegEnumValue(hKey, i, achValue, &cchValue, NULL, &valueType, buffer, &value_len);
			}

			if (ERROR_SUCCESS != retCode)
				continue;

			uvaluename = zbx_unicode_to_utf8(achValue);

			if (NULL != regexp && '\0' != *regexp)
			{
				if (NULL == zbx_regexp_match(uvaluename, regexp, NULL))
				{
					zbx_free(uvaluename);
					continue;
				}
			}

			if (SUCCEED != convert_value(valueType, buffer, value_len, &out))
				continue;

			zbx_json_addobject(j, NULL);

			zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_FULLKEY, uroot,
					ZBX_JSON_TYPE_STRING);

			zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_LASTKEY, usubkey,
					ZBX_JSON_TYPE_STRING);

			zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_NAME, uvaluename, ZBX_JSON_TYPE_STRING);
			zbx_free(uvaluename);

			switch (valueType)
			{
				case REG_DWORD:
				case REG_QWORD:
					zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_DATA, out, ZBX_JSON_TYPE_INT);
					break;
				case REG_NONE:
					zbx_json_adduint64(j, ZBX_SYSINFO_REGISTRY_TAG_DATA, 0);
					break;
				case REG_MULTI_SZ:
					zbx_json_addraw(j, ZBX_SYSINFO_REGISTRY_TAG_DATA, out);
					break;
				default:
					zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_DATA, out, ZBX_JSON_TYPE_STRING);
			}

			zbx_free(out);

			zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_TYPE,
					registry_type_to_string(valueType), ZBX_JSON_TYPE_STRING);

			zbx_json_close(j);
		}

		zbx_free(buffer);
	}

	zbx_free(uroot);
	zbx_free(usubkey);
}

static int	split_fullkey(char **fullkey, HKEY *hive_handle, char **hive_str)
{
	char	*end;

	if (NULL == (end = strchr(*fullkey, '\\')))
		return FAIL;

	*end = '\0';

	if (NULL == (*hive_handle = get_hkey_from_fullkey(*fullkey)))
		return FAIL;

	if (NULL != hive_str)
		*hive_str = *fullkey;

	*fullkey = *fullkey + (end - *fullkey) + 1;

	return SUCCEED;
}

static int	registry_discover(char *key, int mode, AGENT_RESULT *result, const char *regexp)
{
	wchar_t		*wkey, *wfullkey = NULL;;
	HKEY		hkey, hive_handle;
	struct zbx_json	j;
	DWORD		retCode;
	int		ret = SUCCEED;
	char		*hive_str, *fullkey = NULL;

	if (FAIL == split_fullkey(&key, &hive_handle, &hive_str))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Failed to parse registry key."));
		return FAIL;
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	wkey = zbx_utf8_to_unicode(key);

	if (ERROR_SUCCESS == (retCode = RegOpenKeyEx(hive_handle, wkey, 0, KEY_READ, &hkey)))
	{
		fullkey = zbx_dsprintf(fullkey, "%s\\%s", hive_str, key);
		wfullkey = zbx_utf8_to_unicode(fullkey);
		discovery_get_regkey_values(hkey, L"", &j, mode, wfullkey, regexp);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, strerror_from_system(retCode)));
		ret = FAIL;
		goto out;
	}

	RegCloseKey(hkey);

	zbx_json_close(&j);
	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
out:
	zbx_json_free(&j);
	zbx_free(wkey);
	zbx_free(fullkey);
	zbx_free(wfullkey);

	return ret;
}

static int	registry_get_value(char *key, const char *value, AGENT_RESULT *result)
{
	wchar_t		*wkey, *wvalue;
	char		*data = NULL, *bin_value = NULL, *value_str = NULL;
	DWORD		BufferSize = 0, type;
	LSTATUS		errCode;
	HKEY		hive_handle;
	int		ret = SUCCEED;

	if (FAIL == split_fullkey(&key, &hive_handle, NULL))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Failed to parse registry key."));
		return FAIL;
	}

	wkey = zbx_utf8_to_unicode(key);
	wvalue = (NULL != value ? zbx_utf8_to_unicode(value) : NULL);

	errCode = RegGetValue(hive_handle, wkey, wvalue, RRF_RT_ANY, &type, NULL, &BufferSize);
	if (ERROR_SUCCESS == errCode)
	{
		data = zbx_malloc(NULL, (size_t)BufferSize);
		errCode = RegGetValue(hive_handle, wkey, wvalue, RRF_RT_ANY, &type, (PVOID)data, &BufferSize);
	}

	if (ERROR_SUCCESS != errCode)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, strerror_from_system(errCode)));
		ret = FAIL;
		goto out;
	}

	if (SUCCEED == (ret = convert_value(type, data, BufferSize, &value_str)))
		SET_STR_RESULT(result, value_str);
	else
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported registry data type."));
out:
	zbx_free(wkey);
	zbx_free(wvalue);
	zbx_free(data);

	return ret;
}

int	REGISTRY_DATA(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*regkey, *value_name;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	regkey = get_rparam(request, 0);

	if (NULL == regkey || '\0' == *regkey)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Registry key is not supplied."));
		return SYSINFO_RET_FAIL;
	}

	value_name = get_rparam(request, 1);

	if (FAIL == registry_get_value(regkey, value_name, result))
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	REGISTRY_GET(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*pkey, *pmode, *regexp;
	int	mode;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	pkey = get_rparam(request, 0);

	if (NULL == pkey || '\0' == *pkey)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Registry key is not supplied."));
		return SYSINFO_RET_FAIL;
	}

	pmode = get_rparam(request, 1);

	if (NULL == pmode || '\0' == *pmode || 0 == strcmp(pmode, "values"))
	{
		mode = REGISTRY_DISCOVERY_MODE_VALUES;
	}
	else if (0 == strcmp(pmode, "keys"))
	{
		mode = REGISTRY_DISCOVERY_MODE_KEYS;

		if (2 < request->nparam)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
			return SYSINFO_RET_FAIL;
		}
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid 'mode' parameter."));
		return SYSINFO_RET_FAIL;
	}

	regexp = get_rparam(request, 2);

	if (FAIL == registry_discover(pkey, mode, result, regexp))
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}
