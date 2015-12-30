/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

static int	com_initialized = 0;

extern "C" int	zbx_co_initialize()
{
	if (0 == com_initialized)
	{
		HRESULT	hres;

		hres = CoInitializeEx(0, COINIT_MULTITHREADED);

		if (FAILED(hres))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot initialized COM library");
			return FAIL;
		}

		/* initialize security */
		hres = CoInitializeSecurity(NULL, -1, NULL, NULL, RPC_C_AUTHN_LEVEL_DEFAULT,
				RPC_C_IMP_LEVEL_IMPERSONATE, NULL, EOAC_NONE, NULL);

		if (FAILED(hres))
		{
			CoUninitialize();

			zabbix_log(LOG_LEVEL_DEBUG, "cannot set default security levels for COM library");
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

extern "C" int	WMI_GET(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*wmi_namespace, *wmi_query;
	IWbemClassObject	*pclsObj = NULL;
	ULONG			uReturn = 0;
	VARIANT			vtProp;
	IWbemLocator		*pLoc = NULL;
	IWbemServices		*pService = NULL;
	IEnumWbemClassObject	*pEnumerator = NULL;
	HRESULT			hres;
	int			ret = SYSINFO_RET_FAIL;

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED != zbx_co_initialize())
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot initialize COM library."));
		return SYSINFO_RET_FAIL;
	}

	wmi_namespace = get_rparam(request, 0);
	wmi_query = get_rparam(request, 1);

	/* obtain the initial locator to Windows Management on a particular host computer */
	hres = CoCreateInstance(CLSID_WbemLocator, NULL, CLSCTX_INPROC_SERVER, IID_IWbemLocator, (LPVOID *)&pLoc);

	if (FAILED(hres) || NULL == pLoc)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot obtain WMI locator service");
		goto out;
	}

	/* connect to the WMI namespace */
	hres = pLoc->ConnectServer(_bstr_t(wmi_namespace), NULL, NULL, NULL, WBEM_FLAG_CONNECT_USE_MAX_WAIT,
			NULL, NULL, &pService);

	if (FAILED(hres))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot obtain %s WMI service", wmi_namespace);
		goto out;
	}

	/* set the IWbemServices proxy so that impersonation of the user (client) occurs */
	hres = CoSetProxyBlanket(pService, RPC_C_AUTHN_WINNT, RPC_C_AUTHZ_NONE, NULL, RPC_C_AUTHN_LEVEL_CALL,
			RPC_C_IMP_LEVEL_IMPERSONATE, NULL, EOAC_NONE);

	if (FAILED(hres))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot set IWbemServices proxy");
		goto out;
	}

	/* perform a WQL query, get the results' enumerator */
	hres = pService->ExecQuery(_bstr_t("WQL"), _bstr_t(wmi_query),
			WBEM_FLAG_FORWARD_ONLY | WBEM_FLAG_RETURN_IMMEDIATELY, NULL, &pEnumerator);

	if (FAILED(hres) || NULL == pEnumerator)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "failed to execute WMI query %s", wmi_query);
		goto out;
	}

	/* TODO: add example (Win32_BootConfiguration fits the purpose) */

	/* get the first object (row) from the query results */
	hres = pEnumerator->Next(WBEM_INFINITE, 1, &pclsObj, &uReturn);

	if (FAILED(hres) || 1 != uReturn)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot get the object from WMI query results");
		goto out;
	}

	/* get the value of the first property (column) of the object */
	hres = pclsObj->BeginEnumeration(WBEM_FLAG_NONSYSTEM_ONLY);

	if (FAILED(hres))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot enumerate properties of WMI object");
		goto out;
	}

	VariantInit(&vtProp);
	hres = pclsObj->Next(0, NULL, &vtProp, 0, 0);

	if (FAILED(hres) || hres == WBEM_S_NO_MORE_DATA)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot get property from WMI object");
		goto out;
	}

	hres = pclsObj->EndEnumeration();

	if (FAILED(hres))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot terminate enumeration of WMI object's properties");
		/* continue since calling EndEnumeration() is optional */
	}

	/* convert value from WMI type to string */
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

			SET_TEXT_RESULT(result, zbx_strdup(NULL, (char *)_bstr_t(vtProp.bstrVal)));
			ret = SYSINFO_RET_OK;

			break;
	}
out:
	VariantClear(&vtProp);

	if (NULL != pclsObj)
		pclsObj->Release();

	if (NULL != pEnumerator)
		pEnumerator->Release();

	if (NULL != pService)
		pService->Release();

	if (NULL != pLoc)
		pLoc->Release();


	if (SYSINFO_RET_FAIL == ret)
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain WMI information."));

	return ret;
}
