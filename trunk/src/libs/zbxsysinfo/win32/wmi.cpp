/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "sysinc.h"

extern "C"
{
#	include "common.h"
#	include "sysinfo.h"
#	include "log.h"
}

#include <comdef.h>
#include <Wbemidl.h>

#pragma comment(lib, "wbemuuid.lib")

ZBX_THREAD_LOCAL static int	com_initialized = 0;

extern "C" int	zbx_co_initialize()
{
	if (0 == com_initialized)
	{
		HRESULT	hres;

		/* must be called once per each thread */
		hres = CoInitializeEx(0, COINIT_MULTITHREADED);

		if (FAILED(hres))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot initialized COM library");
			return FAIL;
		}

		/* must be called once per process, subsequent calls return RPC_E_TOO_LATE */
		hres = CoInitializeSecurity(NULL, -1, NULL, NULL, RPC_C_AUTHN_LEVEL_DEFAULT,
				RPC_C_IMP_LEVEL_IMPERSONATE, NULL, EOAC_NONE, NULL);

		if (FAILED(hres) && RPC_E_TOO_LATE != hres)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot set default security levels for COM library");
			CoUninitialize();
			return FAIL;
		}

		com_initialized = 1;
	}

	return SUCCEED;
}

extern "C" void	zbx_co_uninitialize()
{
	if (1 == com_initialized)
		CoUninitialize();
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_wmi_get_variant                                              *
 *                                                                            *
 * Purpose: retrieves WMI value and stores it in the provided memory location *
 *                                                                            *
 * Parameters: wmi_namespace [IN]  - object path of the WMI namespace (UTF-8) *
 *             wmi_query     [IN]  - WQL query (UTF-8)                        *
 *             vtProp        [OUT] - pointer to memory for the queried value  *
 *                                                                            *
 * Return value: SYSINFO_RET_OK   - *vtProp contains the retrieved WMI value  *
 *               SYSINFO_RET_FAIL - retreiving WMI value failed               *
 *                                                                            *
 * Comments: *vtProp must be initialized with VariantInit(),                  *
 *           wmi_* must not be NULL. The callers must convert value to the    *
 *           intended format using VariantChangeType()                        *
 *                                                                            *
 ******************************************************************************/
extern "C" int	zbx_wmi_get_variant(const char *wmi_namespace, const char *wmi_query, VARIANT *vtProp)
{
	IWbemLocator		*pLoc = 0;
	IWbemServices		*pService = 0;
	IEnumWbemClassObject	*pEnumerator = 0;
	int			ret = SYSINFO_RET_FAIL;
	HRESULT			hres;
	wchar_t			*wmi_namespace_wide;
	wchar_t			*wmi_query_wide;
	ULONG			obj_num = 0;

	/* obtain the initial locator to Windows Management on a particular host computer */
	hres = CoCreateInstance(CLSID_WbemLocator, 0, CLSCTX_INPROC_SERVER, IID_IWbemLocator, (LPVOID *) &pLoc);

	if (FAILED(hres))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot obtain WMI locator service");
		goto exit;
	}

	wmi_namespace_wide = zbx_utf8_to_unicode(wmi_namespace);
	hres = pLoc->ConnectServer(_bstr_t(wmi_namespace_wide), NULL, NULL, 0, NULL, 0, 0, &pService);
	zbx_free(wmi_namespace_wide);

	if (FAILED(hres))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot obtain %s WMI service", wmi_namespace);
		goto exit;
	}

	/* set the IWbemServices proxy so that impersonation of the user (client) occurs */
	hres = CoSetProxyBlanket(pService, RPC_C_AUTHN_WINNT, RPC_C_AUTHZ_NONE, NULL, RPC_C_AUTHN_LEVEL_CALL,
			RPC_C_IMP_LEVEL_IMPERSONATE, NULL, EOAC_NONE);

	if (FAILED(hres))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot set IWbemServices proxy");
		goto exit;
	}

	wmi_query_wide = zbx_utf8_to_unicode(wmi_query);
	hres = pService->ExecQuery(_bstr_t("WQL"), _bstr_t(wmi_query_wide),
			WBEM_FLAG_FORWARD_ONLY | WBEM_FLAG_RETURN_IMMEDIATELY, NULL, &pEnumerator);
	zbx_free(wmi_query_wide);

	if (FAILED(hres))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to execute WMI query %s", wmi_query);
		goto exit;
	}

	while (pEnumerator)
	{
		IWbemClassObject	*pclsObj = 0;
		ULONG			uReturn = 0;

		hres = pEnumerator->Next(WBEM_INFINITE, 1, &pclsObj, &uReturn);

		if (0 == uReturn)
			goto exit;

		obj_num += uReturn;

		if (1 == obj_num)
		{
			hres = pclsObj->BeginEnumeration(WBEM_FLAG_NONSYSTEM_ONLY);

			if (FAILED(hres))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot start WMI query result enumeration");
				goto out;
			}

			hres = pclsObj->Next(0, NULL, vtProp, 0, 0);

			if (FAILED(hres))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot convert WMI result of type %d to VT_BSTR",
						vtProp->vt);
				goto out;
			}

			pclsObj->EndEnumeration();

			if (FAILED(hres) || hres == WBEM_S_NO_MORE_DATA)
				goto out;
			else
				ret = SYSINFO_RET_OK;
		}

out:
		if (0 != pclsObj)
			pclsObj->Release();
	}

exit:
	if (0 != pEnumerator)
		pEnumerator->Release();

	if (0 != pService)
		pService->Release();

	if (0 != pLoc)
		pLoc->Release();

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_wmi_get                                                      *
 *                                                                            *
 * Purpose: wrapper function for zbx_wmi_get_variant(), stores the retrieved  *
 *          WMI value as UTF-8 encoded string                                 *
 *                                                                            *
 * Parameters: wmi_namespace [IN]  - object path of the WMI namespace (UTF-8) *
 *             wmi_query     [IN]  - WQL query (UTF-8)                        *
 *             utf8_value    [OUT] - address of the pointer to the retrieved  *
 *                                   value (dynamically allocated)            *
 *                                                                            *
 * Comments: if either retrieval or type conversion failed then *utf8_value   *
 *           remains unchanged (set it to NULL before calling this function   *
 *           to check for this condition). Callers must free *utf8_value.     *
 *                                                                            *
 ******************************************************************************/
extern "C" void	zbx_wmi_get(const char *wmi_namespace, const char *wmi_query, char **utf8_value)
{
	VARIANT		vtProp;
	HRESULT		hres;

	VariantInit(&vtProp);

	if (SUCCEED != zbx_co_initialize())
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot initialize COM library for querying WMI");
		goto out;
	}

	if (SYSINFO_RET_FAIL == zbx_wmi_get_variant(wmi_namespace, wmi_query, &vtProp))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot get WMI result");
		goto out;
	}

	hres = VariantChangeType(&vtProp, &vtProp, VARIANT_ALPHABOOL, VT_BSTR);

	if (FAILED(hres))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot convert WMI result of type %d to VT_BSTR", vtProp.vt);
		goto out;
	}

	*utf8_value = zbx_unicode_to_utf8((wchar_t *)_bstr_t(vtProp.bstrVal));
out:
	VariantClear(&vtProp);
}

extern "C" int	WMI_GET(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*wmi_namespace, *wmi_query;
	VARIANT		vtProp;
	HRESULT		hres;
	int		ret = SYSINFO_RET_FAIL;

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		return SYSINFO_RET_FAIL;
	}

	wmi_namespace = get_rparam(request, 0);
	wmi_query = get_rparam(request, 1);

	VariantInit(&vtProp);

	if (SUCCEED != zbx_co_initialize())
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot initialize COM library."));
		return SYSINFO_RET_FAIL;
	}

	if (SYSINFO_RET_FAIL == zbx_wmi_get_variant(wmi_namespace, wmi_query, &vtProp))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot get WMI result");
		goto out;
	}

	if (0 != (vtProp.vt & VT_ARRAY))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot convert WMI array result");
		goto out;
	}

	switch (vtProp.vt)
	{
		case VT_EMPTY:
		case VT_NULL:
			goto out;
		case VT_I8:
		case VT_I4:
		case VT_UI1:
		case VT_I2:
		case VT_I1:
		case VT_UI2:
		case VT_UI4:
		case VT_UI8:
		case VT_INT:
		case VT_UINT:
			hres = VariantChangeType(&vtProp, &vtProp, 0, VT_I8);

			if (FAILED(hres))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot convert WMI result of type %d to VT_I8", vtProp.vt);
				goto out;
			}

			SET_UI64_RESULT(result, vtProp.llVal);
			ret = SYSINFO_RET_OK;

			break;
		case VT_R4:
		case VT_R8:
			hres = VariantChangeType(&vtProp, &vtProp, 0, VT_R8);

			if (FAILED(hres))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot convert WMI result of type %d to VT_R8", vtProp.vt);
				goto out;
			}

			SET_DBL_RESULT(result, vtProp.dblVal);
			ret = SYSINFO_RET_OK;

			break;
		default:
			hres = VariantChangeType(&vtProp, &vtProp, VARIANT_ALPHABOOL, VT_BSTR);

			if (FAILED(hres))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "cannot convert WMI result of type %d to VT_BSTR", vtProp.vt);
				goto out;
			}

			SET_TEXT_RESULT(result, zbx_unicode_to_utf8((wchar_t *)_bstr_t(vtProp.bstrVal)));
			ret = SYSINFO_RET_OK;

			break;
	}
out:
	VariantClear(&vtProp);

	if (SYSINFO_RET_FAIL == ret)
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain WMI information."));

	return ret;
}
