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
#include "log.h"
#include "base64.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "zbxregexp.h"
#include <locale.h>
#include <winreg.h>

#define MAX_KEY_LENGTH			255
#define MAX_DATA_LENGTH			65534
#define MAX_VALUE_NAME			16383
#define MAX_FULLKEY_LENGTH		4096

#define REGISTRY_DISCOVERY_MODE_KEYS	0
#define REGISTRY_DISCOVERY_MODE_VALUES	1

static HKEY	get_hkey_from_fullkey(char *fullkey)
{
	if (0 == strcmp("HKEY_CLASSES_ROOT", fullkey) || 0 == strcmp("HKCR", fullkey))
	{
		return HKEY_CLASSES_ROOT;
	}
	else if (0 == strcmp("HKEY_CURRENT_CONFIG", fullkey) || 0 == strcmp("HKCC", fullkey))
	{
		return HKEY_CURRENT_CONFIG;
	}
	else if (0 == strcmp("HKEY_CURRENT_USER", fullkey) || 0 == strcmp("HKCU", fullkey))
	{
		return HKEY_CURRENT_USER;
	}
	else if (0 == strcmp("HKEY_CURRENT_USER_LOCAL_SETTINGS", fullkey) || 0 == strcmp("HKCULS", fullkey))
	{
		return HKEY_CURRENT_USER_LOCAL_SETTINGS;
	}
	else if (0 == strcmp("HKEY_LOCAL_MACHINE", fullkey) || 0 == strcmp("HKLM", fullkey))
	{
		return HKEY_LOCAL_MACHINE;
	}
	else if (0 == strcmp("HKEY_PERFORMANCE_DATA", fullkey) || 0 == strcmp("HKPD", fullkey))
	{
		return HKEY_PERFORMANCE_DATA;
	}
	else if (0 == strcmp("HKEY_PERFORMANCE_NLSTEXT", fullkey) || 0 == strcmp("HKPN", fullkey))
	{
		return HKEY_PERFORMANCE_NLSTEXT;
	}
	else if (0 == strcmp("HKEY_PERFORMANCE_TEXT", fullkey) || 0 == strcmp("HKPT", fullkey))
	{
		return HKEY_PERFORMANCE_TEXT;
	}
	else if (0 == strcmp("HKEY_USERS", fullkey) || 0 == strcmp("HKU", fullkey))
	{
		return HKEY_USERS;
	}

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

static void	registry_discovery_convert_value_data(struct zbx_json *j, DWORD type, wchar_t *wbuffer)
{
	char		*buffer, *bin_value;
	zbx_uint64_t	num_value;

	switch (type)
	{
		case REG_BINARY:
			buffer = zbx_unicode_to_utf8(wbuffer);
			str_base64_encode_dyn(buffer, &bin_value, (int)strlen(buffer));
			zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_DATA, bin_value, ZBX_JSON_TYPE_STRING);
			zbx_free(buffer);
			zbx_free(bin_value);
			break;
		case REG_DWORD:
		case REG_QWORD:
			buffer = zbx_unicode_to_utf8(wbuffer);
			ZBX_STR2UINT64(num_value, buffer);
			zbx_json_adduint64(j, ZBX_SYSINFO_REGISTRY_TAG_DATA, num_value);
			zbx_free(buffer);
			break;
		case REG_MULTI_SZ:
			zbx_json_addarray(j, ZBX_SYSINFO_REGISTRY_TAG_DATA);
			registry_get_multistring_value(wbuffer, j);
			zbx_json_close(j);
			break;
		case REG_NONE:
			zbx_json_adduint64(j, ZBX_SYSINFO_REGISTRY_TAG_DATA, 0);
			break;
		case REG_SZ:
		case REG_EXPAND_SZ:
			buffer = zbx_unicode_to_utf8(wbuffer);
			zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_DATA, buffer, ZBX_JSON_TYPE_STRING);
			zbx_free(buffer);
			break;
	}
}

ZBX_PTR_VECTOR_DECL(wchar_ptr, wchar_t *)
ZBX_PTR_VECTOR_IMPL(wchar_ptr, wchar_t *)

static void	discovery_get_regkey_values(HKEY hKey, wchar_t *current_subkey, struct zbx_json *j, int mode, wchar_t *root,
		const char *regexp)
{
	wchar_t			achClass[MAX_PATH] = TEXT(""), achValue[MAX_VALUE_NAME];
	DWORD			cchClassName = MAX_PATH, cSubKeys=0, cValues, cbName, i, retCode,
				cchValue = MAX_VALUE_NAME;
	char			*uroot, *usubkey;
	//zbx_vector_ptr_t	wsubkeys;
	zbx_vector_wchar_ptr_t	wsubkeys;

	retCode = RegQueryInfoKey(hKey, achClass, &cchClassName, NULL, &cSubKeys, NULL, NULL, &cValues, NULL, NULL, NULL, NULL);

	//zbx_vector_ptr_create(&wsubkeys);
	zbx_vector_wchar_ptr_create(&wsubkeys);

	uroot = zbx_unicode_to_utf8(root);
	usubkey = zbx_unicode_to_utf8(current_subkey);

	if (REGISTRY_DISCOVERY_MODE_KEYS == mode)
	{
		zbx_json_addobject(j, NULL);
		zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_FULLKEY, uroot, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_LASTKEY, usubkey, ZBX_JSON_TYPE_STRING);
		zbx_json_close(j);

		zbx_free(uroot);
		zbx_free(usubkey);
	}

	for (i = 0; i < cSubKeys; i++)
	{
		cbName = MAX_KEY_LENGTH;
		retCode = RegEnumKeyExW(hKey, i, achClass, &cbName, NULL, NULL, NULL, NULL);

		if (ERROR_SUCCESS == retCode)
		{
			//zbx_vector_ptr_append
			zbx_vector_wchar_ptr_append(&wsubkeys, wcsdup(achClass));
		}
	}

	for (i = 0; i < (DWORD)wsubkeys.values_num; i++)
	{
		HKEY	hSubkey;
		wchar_t	wnew_root[MAX_FULLKEY_LENGTH];
		wchar_t	*wsubkey;

		wsubkey = wsubkeys.values[i];

		if (0 == wcscmp(wsubkey, L""))
			continue;

		swprintf(wnew_root, MAX_FULLKEY_LENGTH, L"%ls\\%ls", root, wsubkey);

		if (ERROR_SUCCESS == RegOpenKeyExW(HKEY_LOCAL_MACHINE, wnew_root, 0, KEY_READ, &hSubkey))
		{
			discovery_get_regkey_values(hSubkey, wsubkey, j, mode, wnew_root, regexp);
		}

		RegCloseKey(hSubkey);
	}

	zbx_vector_wchar_ptr_clear_ext(&wsubkeys, zbx_ptr_free);
	zbx_vector_wchar_ptr_destroy(&wsubkeys);

	if (REGISTRY_DISCOVERY_MODE_VALUES == mode && cValues)
	{
		for (i = 0, retCode = ERROR_SUCCESS; i < cValues; i++)
		{
			DWORD	valueType, lpDataLength = MAX_BUFFER_LEN;
			wchar_t	dataBuffer[MAX_BUFFER_LEN];
			char	*uvaluename;

			cchValue = MAX_VALUE_NAME;
			achValue[0] = L'\0';

			retCode = RegEnumValueW(hKey, i, achValue, &cchValue, NULL, &valueType, (BYTE*)dataBuffer, &lpDataLength);

			if (ERROR_SUCCESS == retCode)
			{
				uvaluename = zbx_unicode_to_utf8(achValue);

				if (NULL != regexp && '\0' != *regexp)
				{
					if (NULL == zbx_regexp_match(uvaluename, regexp, NULL))
					{
						zbx_free(uvaluename);
						continue;
					}
				}

				zbx_json_addobject(j, NULL);

				zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_FULLKEY, uroot,
						ZBX_JSON_TYPE_STRING);

				zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_LASTKEY, usubkey,
						ZBX_JSON_TYPE_STRING);

				zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_NAME, uvaluename, ZBX_JSON_TYPE_STRING);
				zbx_free(uvaluename);

				registry_discovery_convert_value_data(j, valueType, dataBuffer);

				zbx_json_addstring(j, ZBX_SYSINFO_REGISTRY_TAG_TYPE,
						registry_type_to_string(valueType), ZBX_JSON_TYPE_STRING);

				zbx_json_close(j);
			}
		}
	}

	zbx_free(uroot);
	zbx_free(usubkey);
}

static int	split_fullkey(char **fullkey, HKEY *hive_handle)
{
	char	*end;

	if (NULL == (end = strchr(*fullkey, '\\')))
		return FAIL;

	*end = '\0';

	if (0 == (*hive_handle = get_hkey_from_fullkey(*fullkey)))
		return FAIL;

	*fullkey = *fullkey + (end - *fullkey) + 1;

	return SUCCEED;
}

static int	registry_discover(char *key, int mode, AGENT_RESULT *result, const char *regexp)
{
	wchar_t		*wkey;
	HKEY		hkey, hive_handle;
	struct zbx_json	j;

	if (FAIL == split_fullkey(&key, &hive_handle))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Incorrect key provided"));
		return FAIL;
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	wkey = zbx_utf8_to_unicode(key);

	if (ERROR_SUCCESS == RegOpenKeyEx(hive_handle, wkey, 0, KEY_READ, &hkey))
	{
		discovery_get_regkey_values(hkey, TEXT(""), &j, mode, wkey, regexp);
	}

	RegCloseKey(hkey);

	zbx_json_close(&j);
	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
	zbx_json_free(&j);
	zbx_free(wkey);

	return SUCCEED;
}


static int	registry_get_value(char *key, const char *value, AGENT_RESULT *result)
{
	wchar_t		wbuffer[MAX_VALUE_NAME], *wkey, *wvalue;
	char		*buffer, *bin_value;
	struct zbx_json	j;
	zbx_uint64_t	num_value;
	DWORD		BufferSize = MAX_VALUE_NAME, type;
	LSTATUS		errCode;
	HKEY		hive_handle;
	int		ret = SUCCEED;

	if (FAIL == split_fullkey(&key, &hive_handle))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Incorrect key provided"));
		return FAIL;
	}

	wkey = zbx_utf8_to_unicode(key);
	wvalue = zbx_utf8_to_unicode(value);

	if (ERROR_SUCCESS != (errCode = RegGetValueW(hive_handle, wkey, wvalue, RRF_RT_ANY, &type, (PVOID)&wbuffer,
			&BufferSize)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, strerror_from_system(errCode)));
		ret = FAIL;
		goto out;
	}

	switch (type) {
		case REG_BINARY:
			buffer = zbx_unicode_to_utf8(wbuffer);
			str_base64_encode_dyn(buffer, &bin_value, (int)strlen(buffer));
			SET_STR_RESULT(result, bin_value);
			zbx_free(buffer);
			break;
		case REG_DWORD:
		case REG_QWORD:
			buffer = zbx_unicode_to_utf8(wbuffer);
			ZBX_STR2UINT64(num_value, buffer);
			SET_UI64_RESULT(result, num_value);
			zbx_free(buffer);
			break;
		case REG_MULTI_SZ:
			zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
			zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

			registry_get_multistring_value(wbuffer, &j);

			zbx_json_close(&j);
			SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
			zbx_json_free(&j);
			break;
		case REG_NONE:
			SET_UI64_RESULT(result, 0);
			break;
		case REG_SZ:
		case REG_EXPAND_SZ:
			SET_STR_RESULT(result, zbx_unicode_to_utf8(wbuffer));
			break;
		default:
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported registry data type"));
			ret = FAIL;
			break;
	}
out:
	zbx_free(wkey);
	zbx_free(wvalue);

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
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Incorrect parameter 'mode' was provided."));
		return SYSINFO_RET_FAIL;
	}

	regexp = get_rparam(request, 2);

	if (FAIL == registry_discover(pkey, mode, result, regexp))
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}
