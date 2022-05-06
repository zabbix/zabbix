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

#include "common.h"
#include "sysinfo.h"
#include "log.h"
#include "base64.h"
#include "zbxjson.h"
#include <locale.h>
#include <zbxalgo.h>
#include <zbxregexp.h>
#include <winreg.h>
#include <tchar.h>

#define MAX_KEY_LENGTH			255
#define MAX_DATA_LENGTH			65534
#define MAX_VALUE_NAME			16383

#define REGISTRY_DISCOVERY_MODE_KEYS	0
#define REGISTRY_DISCOVERY_MODE_VALUES	1

static const char	*registry_type_to_string(DWORD type)
{
	switch (type) {
		case REG_BINARY:
			return "REG_BINARY";
		case REG_DWORD:
			return "REG_DWORD";
		case REG_EXPAND_SZ:
			return "REG_EXPAND_SZ";
		case REG_LINK:
			return "REG_LINK";
		case REG_MULTI_SZ:
			return "REG_LINK";
		case REG_NONE:
			return "REG_NONE";
		case REG_QWORD:
			return "REG_QWORD";
		case REG_SZ:
			return "REG_SZ";
	}

	return "Unknown";
}

static void	discovery_get_regkey_values(HKEY hKey, char *current_subkey, struct zbx_json *j, int mode, char *root,
		const char *regexp)
{
	TCHAR			achClass[MAX_PATH] = TEXT(""), achValue[MAX_VALUE_NAME];
	DWORD			cchClassName = MAX_PATH, cSubKeys=0, cValues, cbName, i, retCode,
				cchValue = MAX_VALUE_NAME;
	zbx_vector_str_t	subkeys;
 
	retCode = RegQueryInfoKey(hKey, achClass, &cchClassName, NULL, &cSubKeys, NULL, NULL, &cValues, NULL, NULL, NULL, NULL);

	zbx_vector_str_create(&subkeys);

	if (REGISTRY_DISCOVERY_MODE_KEYS == mode)
	{
		zbx_json_addobject(j, NULL);
		zbx_json_addstring(j, "fullkey", root, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(j, "lastkey", current_subkey, ZBX_JSON_TYPE_STRING);
		zbx_json_close(j);
	}

	for (i = 0; i < cSubKeys; i++) 
	{ 
		char	buf[MAX_KEY_LENGTH];

		cbName = MAX_KEY_LENGTH;
		retCode = RegEnumKeyEx(hKey, i, achClass, &cbName, NULL, NULL, NULL, NULL); 

		if (ERROR_SUCCESS == retCode) 
		{
			zbx_vector_str_append(&subkeys, zbx_strdup(NULL, buf));
			WideCharToMultiByte(CP_UTF8, 0, &achClass[0], MAX_KEY_LENGTH, &buf[0], MAX_KEY_LENGTH, NULL, NULL);
		}
	}
 
	for (i = 0; i < (DWORD)subkeys.values_num; i++)
	{
		HKEY	hSubkey;
		char	*new_root = NULL;
		wchar_t	wnew_root[MAX_KEY_LENGTH];

		if (0 == strcmp(subkeys.values[i], ""))
			continue;

		new_root = zbx_strdcatf(new_root, "%s\\%s", root, subkeys.values[i]);
		mbstowcs(wnew_root, new_root, MAX_KEY_LENGTH);

		if (ERROR_SUCCESS == RegOpenKeyExW(HKEY_LOCAL_MACHINE, wnew_root, 0, KEY_READ, &hSubkey))
		{
			discovery_get_regkey_values(hSubkey, subkeys.values[i], j, mode, new_root, regexp);
		}

		RegCloseKey(hSubkey);
	}

	if (REGISTRY_DISCOVERY_MODE_VALUES== mode && cValues) 
	{
		for (i = 0, retCode = ERROR_SUCCESS; i < cValues; i++) 
		{ 
			DWORD	valueType, lpDataLength = MAX_BUFFER_LEN;
			BYTE	dataBuffer[MAX_BUFFER_LEN];

			cchValue = MAX_VALUE_NAME; 
			achValue[0] = '\0'; 

			retCode = RegEnumValueA(hKey, i, achValue, &cchValue, NULL, &valueType, dataBuffer, lpDataLength);

			if (ERROR_SUCCESS == retCode) 
			{ 
				if (NULL != regexp && '\0' != *regexp)
				{
					if (NULL == zbx_regexp_match((const char *)achValue, regexp, NULL))
						continue;
				}
				zbx_json_addobject(j, NULL);
				zbx_json_addstring(j, "fullkey", root, ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(j, "lastkey", current_subkey, ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(j, "name", (const char *)achValue, ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(j, "data", dataBuffer, ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(j, "type", registry_type_to_string(valueType), ZBX_JSON_TYPE_STRING);
				zbx_json_close(j);
			} 
		}
	}
}

static void registry_discover(const char *key, int mode, AGENT_RESULT *result, const char *regexp)
{
	HKEY		hTestKey;
	struct zbx_json	j;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	if (ERROR_SUCCESS == RegOpenKeyExA(HKEY_LOCAL_MACHINE, key, 0, KEY_READ, &hTestKey))
	{
		discovery_get_regkey_values(hTestKey, "", &j, mode, key, regexp);
	}

	RegCloseKey(hTestKey);

	zbx_json_close(&j);
	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
	zbx_json_free(&j);
}

static void registry_read_multistring(const TCHAR *buffer, AGENT_RESULT *result)
{
	struct zbx_json j;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	while (*buffer)
	{
		buffer += _tcslen(buffer) + 1 ;
	}

	zbx_json_close(&j);
	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
	zbx_json_free(&j);
}

static int	registry_get_value(const char *key, const char *value, AGENT_RESULT *result)
{
	char		buffer[MAX_VALUE_NAME];
	char		*bvalue = NULL;
	zbx_uint64_t	num_val;
	DWORD		BufferSize = MAX_VALUE_NAME, type;
	LSTATUS		errCode;

	if (ERROR_SUCCESS != (errCode = RegGetValueA(HKEY_LOCAL_MACHINE, key, value, RRF_RT_ANY, &type, (PVOID)&buffer, &BufferSize)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, strerror_from_system(errCode)));
		return FAIL;
	}
	
	switch (type)
	{
		case REG_BINARY:
			str_base64_encode_dyn(buffer, &bvalue, (size_t)strlen(buffer));
			SET_STR_RESULT(result, bvalue);
			break;
		case REG_DWORD:
		case REG_QWORD:
			ZBX_STR2UINT64(num_val, buffer);
			break;
		case REG_MULTI_SZ:
			registry_read_multistring(buffer, result);
			break;
		case REG_NONE:
			SET_UI64_RESULT(result, 0);
			break;
		case REG_SZ:
		case REG_EXPAND_SZ:
			SET_STR_RESULT(result, zbx_strdup(NULL, buffer));
			break;
		default:
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported type"));
	}

	return SUCCEED;
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

	if (2 < request->nparam)
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
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Incorrect parameter 'mode' was provided.")); // !! msg
		return SYSINFO_RET_FAIL;
	}

	regexp = get_rparam(request, 2);

	registry_discover(pkey, mode, result, regexp);

	return SYSINFO_RET_OK;
}