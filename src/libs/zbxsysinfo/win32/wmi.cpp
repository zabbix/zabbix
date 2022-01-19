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

#include "sysinc.h"

extern "C"
{
#	include "common.h"
#	include "sysinfo.h"
#	include "log.h"
#	include "zbxalgo.h"
#	include "../../zbxalgo/vectorimpl.h"
#	include "zbxjson.h"
#	include "cfg.h"
}

#include <comdef.h>
#include <Wbemidl.h>

#pragma comment(lib, "wbemuuid.lib")

typedef struct
{
	BSTR	name;
	VARIANT	*value;
}
zbx_wmi_prop_t;

ZBX_VECTOR_DECL(wmi_prop, zbx_wmi_prop_t)
ZBX_VECTOR_IMPL(wmi_prop, zbx_wmi_prop_t)

ZBX_PTR_VECTOR_DECL(wmi_instance, zbx_vector_wmi_prop_t *)
ZBX_PTR_VECTOR_IMPL(wmi_instance, zbx_vector_wmi_prop_t *)

extern "C" static void	wmi_prop_clear(zbx_wmi_prop_t *prop)
{
	SysFreeString(prop->name);
	VariantClear(prop->value);
	zbx_free(prop->value);
}

extern "C" static void	wmi_instance_clear(zbx_vector_wmi_prop_t *wmi_inst_value)
{
	int	i;

	for (i = 0; i < wmi_inst_value->values_num; i++)
		wmi_prop_clear(&wmi_inst_value->values[i]);

	zbx_vector_wmi_prop_destroy(wmi_inst_value);
	zbx_free(wmi_inst_value);
}

typedef int	(*zbx_parse_wmi_t)(IEnumWbemClassObject *pEnumerator, double timeout,
		zbx_vector_wmi_instance_t *wmi_values, char **error);

extern "C" int	put_variant_json(const char *prop_json, const char *prop_err, VARIANT *vtProp, struct zbx_json *jdoc,
		char **error);

static ZBX_THREAD_LOCAL int	com_initialized = 0;

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
 * Purpose: extract only one value from the search result                     *
 *                                                                            *
 * Parameters: pEnumerator - [IN] the search result                           *
 *             timeout     - [IN] query timeout in seconds                    *
 *             wmi_values  - [IN/OUT] vector with found value                 *
 *             error       - [OUT] the error description                      *
 *                                                                            *
 * Return value: SYSINFO_RET_OK   - wmi_values contains the retrieved value   *
 *               SYSINFO_RET_FAIL - retrieving WMI value failed               *
 *                                                                            *
 * Comments: one value is the value from the first property of the first      *
 *           instance from search result                                      *
 *                                                                            *
 ******************************************************************************/
extern "C" static int	parse_first_first(IEnumWbemClassObject *pEnumerator, double timeout,
		zbx_vector_wmi_instance_t *wmi_values, char **error)
{
	int			ret = SYSINFO_RET_FAIL;
	VARIANT			*vtProp = NULL;
	IWbemClassObject	*pclsObj = 0;
	ULONG			uReturn = 0;
	HRESULT			hres;
	zbx_vector_wmi_prop_t	*inst_val;
	zbx_wmi_prop_t		prop;

	hres = pEnumerator->Next((long)(1000 * timeout), 1, &pclsObj, &uReturn);

	if (WBEM_S_TIMEDOUT == hres)
	{
		*error = zbx_strdup(*error, "WMI query timeout.");
		goto out2;
	}

	if (FAILED(hres) || 0 == uReturn)
		goto out2;

	hres = pclsObj->BeginEnumeration(WBEM_FLAG_NONSYSTEM_ONLY);

	if (FAILED(hres))
	{
		*error = zbx_strdup(*error, "Cannot start WMI query result enumeration.");
		goto out1;
	}

	vtProp = (VARIANT*) zbx_malloc(NULL, sizeof(VARIANT));
	VariantInit(vtProp);
	hres = pclsObj->Next(0, NULL, vtProp, 0, 0);

	if (FAILED(hres))
	{
		*error = zbx_strdup(*error, "Cannot parse WMI result field.");
		zbx_free(vtProp);
		goto out1;
	}

	pclsObj->EndEnumeration();

	if (hres == WBEM_S_NO_MORE_DATA || VT_EMPTY == V_VT(vtProp) || VT_NULL == V_VT(vtProp))
	{
		zbx_free(vtProp);
		goto out1;
	}
	else
		ret = SYSINFO_RET_OK;

	prop.name = NULL;
	prop.value = vtProp;
	inst_val = (zbx_vector_wmi_prop_t*) zbx_malloc(NULL, sizeof(zbx_vector_wmi_prop_t));
	zbx_vector_wmi_prop_create(inst_val);
	zbx_vector_wmi_prop_append(inst_val, prop);
	zbx_vector_wmi_instance_append(wmi_values, inst_val);
out1:
	pclsObj->Release();
out2:	
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: extract all values from the search result                         *
 *                                                                            *
 * Parameters: pEnumerator - [IN] the search result                           *
 *             timeout     - [IN] query timeout in seconds                    *
 *             wmi_values  - [IN/OUT] vector with found values                *
 *             error       - [OUT] the error description                      *
 *                                                                            *
 * Return value: SYSINFO_RET_OK   - wmi_values contains the retrieved values  *
 *               SYSINFO_RET_FAIL - retrieving WMI value failed               *
 *                                                                            *
 ******************************************************************************/
extern "C" static int	parse_all(IEnumWbemClassObject *pEnumerator, double timeout,
		zbx_vector_wmi_instance_t *wmi_values, char **error)
{
	int	ret = SYSINFO_RET_FAIL;
	VARIANT	*vtProp;
	HRESULT	hres = S_OK;

	while (pEnumerator && SUCCEEDED(hres))
	{
		IWbemClassObject	*pclsObj;
		ULONG			uReturn = 0;
		zbx_vector_wmi_prop_t	*inst_val = NULL;

		hres = pEnumerator->Next((long)(1000 * timeout), 1, &pclsObj, &uReturn);

		if (WBEM_S_TIMEDOUT == hres)
		{
			ret = SYSINFO_RET_FAIL;
			*error = zbx_strdup(*error, "WMI query timeout.");
			return ret;
		}

		if (WBEM_S_FALSE == hres && 0 == uReturn)
			return SYSINFO_RET_OK;

		if (FAILED(hres) || 0 == uReturn)
			return ret;

		hres = pclsObj->BeginEnumeration(WBEM_FLAG_NONSYSTEM_ONLY);

		if (FAILED(hres))
		{
			*error = zbx_strdup(*error, "Cannot start WMI query result enumeration.");
			pclsObj->Release();
			break;
		}

		inst_val = (zbx_vector_wmi_prop_t*)zbx_malloc(NULL, sizeof(zbx_vector_wmi_prop_t));
		zbx_vector_wmi_prop_create(inst_val);
		zbx_vector_wmi_instance_append(wmi_values, inst_val);

		while (!(FAILED(hres) || WBEM_S_NO_MORE_DATA == hres))
		{
			zbx_wmi_prop_t	prop = {NULL, NULL};

			vtProp = (VARIANT*)zbx_malloc(NULL, sizeof(VARIANT));
			VariantInit(vtProp);
			hres = pclsObj->Next(0, &prop.name, vtProp, 0, 0);

			if (FAILED(hres) || WBEM_S_NO_MORE_DATA == hres || VT_EMPTY == V_VT(vtProp) ||
					VT_NULL == V_VT(vtProp))
			{
				if (FAILED(hres))
					*error = zbx_strdup(*error, "Cannot parse WMI result field.");

				SysFreeString(prop.name);
				zbx_free(vtProp);
				continue;
			}

			prop.value = vtProp;
			zbx_vector_wmi_prop_append(inst_val, prop);
			ret = SYSINFO_RET_OK;
		}

		pclsObj->EndEnumeration();
		pclsObj->Release();
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves WMI value and stores it in the provided memory location *
 *                                                                            *
 * Parameters: wmi_namespace  - [IN] object path of the WMI namespace (UTF-8) *
 *             wmi_query      - [IN] WQL query (UTF-8)                        *
 *             parse_value_cb - [IN] callback parsing function                *
 *             timeout        - [IN] query timeout in seconds                 *
 *             wmi_values     - [OUT] pointer to memory for the queried       *
 *                                    values                                  *
 *             error          - [OUT] the error description                   *
 *                                                                            *
 * Return value: SYSINFO_RET_OK   - *vtProp contains the retrieved WMI value  *
 *               SYSINFO_RET_FAIL - retrieving WMI value failed               *
 *                                                                            *
 * Comments: *vtProp must be initialized with VariantInit(),                  *
 *           wmi_* must not be NULL. The callers must convert value to the    *
 *           intended format using VariantChangeType()                        *
 *                                                                            *
 ******************************************************************************/
extern "C" int	zbx_wmi_get_variant(const char *wmi_namespace, const char *wmi_query, zbx_parse_wmi_t parse_value_cb,
		double timeout, zbx_vector_wmi_instance_t *wmi_values, char **error)
{
	IWbemLocator		*pLoc = 0;
	IWbemServices		*pService = 0;
	IEnumWbemClassObject	*pEnumerator = 0;
	int			ret = SYSINFO_RET_FAIL;
	HRESULT			hres;
	wchar_t			*wmi_namespace_wide;
	wchar_t			*wmi_query_wide;

	/* obtain the initial locator to Windows Management on a particular host computer */
	hres = CoCreateInstance(CLSID_WbemLocator, 0, CLSCTX_INPROC_SERVER, IID_IWbemLocator, (LPVOID *) &pLoc);

	if (FAILED(hres))
	{
		*error = zbx_strdup(*error, "Cannot obtain WMI locator service.");
		goto exit;
	}

	wmi_namespace_wide = zbx_utf8_to_unicode(wmi_namespace);
	hres = pLoc->ConnectServer(_bstr_t(wmi_namespace_wide), NULL, NULL, 0, NULL, 0, 0, &pService);
	zbx_free(wmi_namespace_wide);

	if (FAILED(hres))
	{
		*error = zbx_dsprintf(*error, "Cannot obtain %s WMI service.", wmi_namespace);
		goto exit;
	}

	/* set the IWbemServices proxy so that impersonation of the user (client) occurs */
	hres = CoSetProxyBlanket(pService, RPC_C_AUTHN_WINNT, RPC_C_AUTHZ_NONE, NULL, RPC_C_AUTHN_LEVEL_CALL,
			RPC_C_IMP_LEVEL_IMPERSONATE, NULL, EOAC_NONE);

	if (FAILED(hres))
	{
		*error = zbx_strdup(*error, "Cannot set IWbemServices proxy.");
		goto exit;
	}

	wmi_query_wide = zbx_utf8_to_unicode(wmi_query);
	hres = pService->ExecQuery(_bstr_t("WQL"), _bstr_t(wmi_query_wide),
			WBEM_FLAG_FORWARD_ONLY | WBEM_FLAG_RETURN_IMMEDIATELY, NULL, &pEnumerator);
	zbx_free(wmi_query_wide);

	if (FAILED(hres))
	{
		*error = zbx_dsprintf(*error, "Failed to execute WMI query %s.", wmi_query);
		goto exit;
	}

	if (NULL != pEnumerator)
		ret = parse_value_cb(pEnumerator, timeout, wmi_values, error);

	if (SYSINFO_RET_FAIL == ret && NULL == *error)
		*error = zbx_strdup(*error, "Empty WMI search result.");
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
 * Purpose: wrapper function for zbx_wmi_get_variant(), stores the retrieved  *
 *          WMI value as UTF-8 encoded string                                 *
 *                                                                            *
 * Parameters: wmi_namespace - [IN] object path of the WMI namespace (UTF-8)  *
 *             wmi_query     - [IN] WQL query (UTF-8)                         *
 *             timeout       - [IN] query timeout in seconds                  *
 *             utf8_value    - [OUT] address of the pointer to the retrieved  *
 *                                   value (dynamically allocated)            *
 *                                                                            *
 * Comments: if either retrieval or type conversion failed then *utf8_value   *
 *           remains unchanged (set it to NULL before calling this function   *
 *           to check for this condition). Callers must free *utf8_value.     *
 *                                                                            *
 ******************************************************************************/
extern "C" void	zbx_wmi_get(const char *wmi_namespace, const char *wmi_query, double timeout, char **utf8_value)
{
	VARIANT				*vtProp;
	HRESULT				hres;
	zbx_vector_wmi_instance_t	wmi_values;
	char				*error = NULL;

	zbx_vector_wmi_instance_create(&wmi_values);

	if (SUCCEED != zbx_co_initialize())
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot initialize COM library for querying WMI");
		goto out;
	}

	if (SYSINFO_RET_FAIL == zbx_wmi_get_variant(wmi_namespace, wmi_query, parse_first_first, timeout, &wmi_values,
			&error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, error);
		goto out;
	}

	vtProp = wmi_values.values[0]->values[0].value;
	hres = VariantChangeType(vtProp, vtProp, VARIANT_ALPHABOOL, VT_BSTR);

	if (FAILED(hres))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot convert WMI result of type %d to VT_BSTR", V_VT(vtProp));
		goto out;
	}

	*utf8_value = zbx_unicode_to_utf8((wchar_t *)_bstr_t(vtProp->bstrVal));
out:
	zbx_vector_wmi_instance_clear_ext(&wmi_values, wmi_instance_clear);
	zbx_vector_wmi_instance_destroy(&wmi_values);
	zbx_free(error);
}


/******************************************************************************
 *                                                                            *
 * Purpose: wrapper function for wmi.get metric                               *
 *                                                                            *
 * Parameters: request - [IN] WMI request parameters                          *
 *             result  - [OUT] one value of property from WMI Class           *
 *                                                                            *
 * Return value: SYSINFO_RET_OK   - result contains the retrieved WMI value   *
 *               SYSINFO_RET_FAIL - retrieving WMI value failed               *
 *                                                                            *
 ******************************************************************************/
extern "C" int	WMI_GET(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char				*wmi_namespace, *wmi_query, *error = NULL;
	VARIANT				*vtProp;
	HRESULT				hres;
	int				ret = SYSINFO_RET_FAIL;
	zbx_vector_wmi_instance_t	wmi_values;

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		return SYSINFO_RET_FAIL;
	}

	wmi_namespace = get_rparam(request, 0);
	wmi_query = get_rparam(request, 1);

	if (SUCCEED != zbx_co_initialize())
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot initialize COM library."));
		return SYSINFO_RET_FAIL;
	}

	zbx_vector_wmi_instance_create(&wmi_values);

	if (SYSINFO_RET_FAIL == zbx_wmi_get_variant(wmi_namespace, wmi_query, parse_first_first, CONFIG_TIMEOUT,
			&wmi_values, &error))
	{
		goto out;
	}

	vtProp = wmi_values.values[0]->values[0].value;

	if (V_ISARRAY(vtProp))
	{
		error = zbx_strdup(error, "Cannot convert WMI array result.");
		goto out;
	}

	switch (V_VT(vtProp))
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
			hres = VariantChangeType(vtProp, vtProp, 0, VT_I8);

			if (FAILED(hres))
			{
				error = zbx_dsprintf(error, "Cannot convert WMI result of type %d to VT_I8",
						V_VT(vtProp));
				goto out;
			}

			SET_UI64_RESULT(result, vtProp->llVal);
			ret = SYSINFO_RET_OK;

			break;
		case VT_R4:
		case VT_R8:
			hres = VariantChangeType(vtProp, vtProp, 0, VT_R8);

			if (FAILED(hres))
			{
				error = zbx_dsprintf(error, "Cannot convert WMI result of type %d to VT_R8",
						V_VT(vtProp));
				goto out;
			}

			SET_DBL_RESULT(result, vtProp->dblVal);
			ret = SYSINFO_RET_OK;

			break;
		default:
			hres = VariantChangeType(vtProp, vtProp, VARIANT_ALPHABOOL, VT_BSTR);

			if (FAILED(hres))
			{
				error = zbx_dsprintf(error, "Cannot convert WMI result of type %d to VT_BSTR",
						V_VT(vtProp));
				goto out;
			}

			SET_TEXT_RESULT(result, zbx_unicode_to_utf8((wchar_t *)_bstr_t(V_BSTR(vtProp))));
			ret = SYSINFO_RET_OK;

			break;
	}
out:
	zbx_vector_wmi_instance_clear_ext(&wmi_values, wmi_instance_clear);
	zbx_vector_wmi_instance_destroy(&wmi_values);

	if (SYSINFO_RET_FAIL == ret)
		SET_MSG_RESULT(result, error);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: take one element from array and put value to JSON document        *
 *                                                                            *
 * Parameters: sa       - [IN] SafeArray from WMI property                    *
 *             index    - [IN] ID of element in array                         *
 *             prop_err - [IN] json attribute name                            *
 *             jdoc     - [IN/OUT] JSON document                              *
 *             error    - [OUT] the error description                         *
 *                                                                            *
 * Return value: SYSINFO_RET_OK   - json document contains the array element  *
 *               SYSINFO_RET_FAIL - transformation of variant array failed    *
 *                                                                            *
 ******************************************************************************/
extern "C" int	proc_arr_element(SAFEARRAY *sa, LONG *index, const char *prop_err, struct zbx_json *jdoc,
		char **error)
{
	HRESULT	hres;
	BYTE	*pbData;
	int	ret = SYSINFO_RET_OK;
	VARTYPE	pvt;
	VARIANT	arProp;

	pbData = (BYTE*)zbx_malloc(NULL, (size_t)SafeArrayGetElemsize(sa));
	hres = SafeArrayGetElement(sa, index, pbData);

	if (FAILED(hres))
	{
		*error = zbx_dsprintf(*error, "Cannot get element from WMI array '%s'", prop_err);
		zbx_free(pbData);
		return SYSINFO_RET_FAIL;
	}

	hres = SafeArrayGetVartype(sa, &pvt);

	if (FAILED(hres))
	{
		*error = zbx_dsprintf(*error, "Cannot get element type from WMI array '%s'", prop_err);
		zbx_free(pbData);
		return SYSINFO_RET_FAIL;
	}

	VariantInit(&arProp);
	V_VT(&arProp) = pvt;

	switch(pvt &(~VT_ARRAY))
	{
		case VT_BOOL:
			V_BOOL(&arProp) = *((VARIANT_BOOL*)pbData);
			break;
		case VT_I1:
			V_I1(&arProp) = *((char*)pbData);
			break;
		case VT_I2:
			V_I2(&arProp) = *((short*)pbData);
			break;
		case VT_I4:
			V_I4(&arProp) = *((long*)pbData);
			break;
		case VT_I8:
			V_I8(&arProp) = *((LONGLONG*)pbData);
			break;
		case VT_UI1:
			V_UI1(&arProp) = *((BYTE*)pbData);
			break;
		case VT_UI2:
			V_UI2(&arProp) = *((WORD*)pbData);
			break;
		case VT_UI4:
			V_UI4(&arProp) = *((DWORD*)pbData);
			break;
		case VT_UI8:
			V_UI8(&arProp) = *((ULONGLONG*)pbData);
			break;
		case VT_R4:
			V_R4(&arProp) = *((float*)pbData);
			break;
		case VT_R8:
			V_R8(&arProp) = *((double*)pbData);
			break;
		case VT_CY:
			V_CY(&arProp) = *((CY*)pbData);
			hres = VariantChangeType(&arProp, &arProp, 0, VT_BSTR);

			if (FAILED(hres))
			{
				*error = zbx_dsprintf(*error, "Cannot convert WMI property '%s' of "
						"type %d to VT_BSTR", prop_err, pvt);
				ret = SYSINFO_RET_FAIL;
			}

			break;
		case VT_DATE:
			V_DATE(&arProp) = *((DATE*)pbData);
			hres = VariantChangeType(&arProp, &arProp, 0, VT_BSTR);

			if (FAILED(hres))
			{
				*error = zbx_dsprintf(*error, "Cannot convert WMI property '%s' of "
						"type %d to VT_BSTR", prop_err, pvt);
				ret = SYSINFO_RET_FAIL;
			}

			break;
		case VT_BSTR:
			V_BSTR(&arProp) = *((BSTR*)pbData);
			break;
		case VT_VARIANT:
			hres = VariantCopy(&arProp, (VARIANT*)pbData);

			if (FAILED(hres))
			{
				*error = zbx_dsprintf(*error, "Cannot copy array element of WMI property '%s'",
						prop_err);
				ret = SYSINFO_RET_FAIL;
			}

			break;
		default:
			*error = zbx_dsprintf(*error, "Unsupported type %d for array element of WMI property '%s'",
					pvt, prop_err);
			ret = SYSINFO_RET_FAIL;
			break;
	}

	if (SYSINFO_RET_OK == ret)
		ret = put_variant_json(NULL, prop_err, &arProp, jdoc, error);

	VariantClear(&arProp);
	zbx_free(pbData);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: transformation of variant array from WMI search result to JSON    *
 *                                                                            *
 * Parameters: vtProp     - [IN] variant WMI property value                   *
 *             prop_name  - [IN] json attribute name                          *
 *             dim        - [IN] dimension of array                           *
 *             offset_dim - [IN] index of dimension for processing            *
 *             index      - [IN/OUT] index of element in the array            *
 *             jdoc       - [IN/OUT] JSON document                            *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SYSINFO_RET_OK   - json document contains the WMI array      *
 *               SYSINFO_RET_FAIL - transformation of variant array failed    *
 *                                                                            *
 ******************************************************************************/
extern "C" int	convert_wmiarray_json(VARIANT *vtProp, const char *prop_name, ULONG dim, ULONG offset_dim,
		LONG **index, struct zbx_json *jdoc, char **error)
{
	HRESULT		hres;
	LONG		i, lBound, uBound;
	int		ret = SYSINFO_RET_OK;
	SAFEARRAY	*sa = V_ARRAY(vtProp);

	if (0 == dim)
	{
		dim = SafeArrayGetDim(sa);
		*index = (LONG*) zbx_malloc(*index, (size_t)(sizeof(LONG) * dim));
		offset_dim = 1;
		zbx_json_addarray(jdoc, prop_name);
	}
	else
		zbx_json_addarray(jdoc, NULL);

	hres = SafeArrayGetLBound(sa, offset_dim, &lBound);

	if (FAILED(hres))
	{
		*error = zbx_dsprintf(*error, "Cannot get index of first element from WMI array '%s'", prop_name);
		return SYSINFO_RET_FAIL;
	}

	hres = SafeArrayGetUBound(sa, offset_dim, &uBound);

	if (FAILED(hres))
	{
		*error = zbx_dsprintf(*error, "Cannot get index of last element from WMI array '%s'", prop_name);
		return SYSINFO_RET_FAIL;
	}


	for(i=lBound; i <= uBound && SYSINFO_RET_OK == ret; i++)
	{
		(*index)[offset_dim-1] = i;

		if (offset_dim < dim)
		{
			ret = convert_wmiarray_json(vtProp, prop_name, dim, offset_dim + 1, index, jdoc, error);
			continue;
		}

		ret = proc_arr_element(sa, *index, prop_name, jdoc, error);
	}

	zbx_json_close(jdoc);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy value of VARIANT type to JSON document                       *
 *                                                                            *
 * Parameters: prop_json - [IN] json attribute name                           *
 *             prop_err  - [IN] json attribute name                           *
 *             vtProp    - [IN] variant WMI property value                    *
 *             jdoc      - [IN/OUT] JSON document                             *
 *             error     - [OUT] the error description                        *
 *                                                                            *
 * Return value: SYSINFO_RET_OK   - json document contains the WMI property   *
 *               SYSINFO_RET_FAIL - transformation of variant failed          *
 *                                                                            *
 ******************************************************************************/
extern "C" int	put_variant_json(const char *prop_json, const char *prop_err, VARIANT *vtProp, struct zbx_json *jdoc,
		char **error)
{
	HRESULT	hres;
	int	ret = SYSINFO_RET_OK;

	switch (V_VT(vtProp))
	{
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
			hres = VariantChangeType(vtProp, vtProp, 0, VT_I8);

			if (FAILED(hres))
			{
				*error = zbx_dsprintf(*error, "Cannot convert WMI property '%s' of "
						"type %d to VT_I8", prop_err, V_VT(vtProp));
				ret = SYSINFO_RET_FAIL;
			}
			else
				zbx_json_adduint64(jdoc, prop_json, vtProp->llVal);

			break;
		case VT_R4:
		case VT_R8:
			hres = VariantChangeType(vtProp, vtProp, 0, VT_R8);

			if (FAILED(hres))
			{
				*error = zbx_dsprintf(*error, "Cannot convert WMI property '%s' of "
						"type %d to VT_R8", prop_err, V_VT(vtProp));
				ret = SYSINFO_RET_FAIL;
			}
			else
				zbx_json_addfloat(jdoc, prop_json, vtProp->dblVal);

			break;
		default:
			char *str;

			if (V_ISARRAY(vtProp))
			{
				LONG *index = NULL;
				ret = convert_wmiarray_json(vtProp, prop_json, 0, 0, &index, jdoc, error);
				zbx_free(index);
				break;
			}

			hres = VariantChangeType(vtProp, vtProp, VARIANT_ALPHABOOL, VT_BSTR);

			if (FAILED(hres))
			{
				*error = zbx_dsprintf(*error, "Cannot convert WMI property '%s' of "
						"type %d to VT_BSTR", prop_err, V_VT(vtProp));
				ret = SYSINFO_RET_FAIL;
			}
			else
			{
				str = zbx_unicode_to_utf8((wchar_t *)_bstr_t(V_BSTR(vtProp)));
				zbx_json_escape(&str);
				zbx_json_addstring(jdoc, prop_json, str, ZBX_JSON_TYPE_STRING);
				zbx_free(str);
			}

			break;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: transformation of WMI search result to JSON                       *
 *                                                                            *
 * Parameters: wmi_values - [IN] WMI search result                            *
 *             json_data  - [OUT] JSON document with WMI search result        *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SYSINFO_RET_OK   - result contains the retrieved WMI value   *
 *               SYSINFO_RET_FAIL - retrieving WMI value failed               *
 *                                                                            *
 ******************************************************************************/
extern "C" int	convert_wmi_json(zbx_vector_wmi_instance_t *wmi_values, char **json_data, char **error)
{
	struct zbx_json	j;
	int		inst_i, prop_i, ret = SYSINFO_RET_OK;

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	for (inst_i = 0; inst_i < wmi_values->values_num && SYSINFO_RET_OK == ret; inst_i++)
	{
		zbx_json_addobject(&j, NULL);

		for (prop_i = 0; prop_i < wmi_values->values[inst_i]->values_num && SYSINFO_RET_OK == ret; prop_i++)
		{
			VARIANT	*vtProp = wmi_values->values[inst_i]->values[prop_i].value;
			char	*prop_name = zbx_unicode_to_utf8(
					(wchar_t *)_bstr_t(wmi_values->values[inst_i]->values[prop_i].name));

			zbx_json_escape(&prop_name);
			ret = put_variant_json(prop_name, prop_name, vtProp, &j, error);
			zbx_free(prop_name);
		}

		zbx_json_close(&j);
	}

	zbx_json_close(&j);

	if (SYSINFO_RET_OK == ret)
	{
		size_t offset, len = 0;

		zbx_str_memcpy_alloc(json_data, &len, &offset, j.buffer, j.buffer_size);
	}

	zbx_json_free(&j);
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: wrapper function for wmi.getall metric                            *
 *                                                                            *
 * Parameters: request - [IN] WMI request parameters                          *
 *             result  - [OUT] all values from WMI Class in JSON format       *
 *                                                                            *
 * Return value: SYSINFO_RET_OK   - result contains the retrieved WMI value   *
 *               SYSINFO_RET_FAIL - retrieving WMI value failed               *
 *                                                                            *
 ******************************************************************************/
extern "C" int	WMI_GETALL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char				*wmi_namespace, *wmi_query, *jd = NULL, *error = NULL;
	int				ret = SYSINFO_RET_FAIL;
	zbx_vector_wmi_instance_t	wmi_values;

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		return SYSINFO_RET_FAIL;
	}

	wmi_namespace = get_rparam(request, 0);
	wmi_query = get_rparam(request, 1);

	if (SUCCEED != zbx_co_initialize())
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot initialize COM library."));
		return SYSINFO_RET_FAIL;
	}

	zbx_vector_wmi_instance_create(&wmi_values);

	if (SYSINFO_RET_OK == zbx_wmi_get_variant(wmi_namespace, wmi_query, parse_all, CONFIG_TIMEOUT, &wmi_values,
			&error))
	{
		ret = convert_wmi_json(&wmi_values, &jd, &error);
	}

	zbx_vector_wmi_instance_clear_ext(&wmi_values, wmi_instance_clear);
	zbx_vector_wmi_instance_destroy(&wmi_values);

	if (SYSINFO_RET_OK == ret)
		SET_TEXT_RESULT(result,jd);
	else
		SET_MSG_RESULT(result, error);

	return ret;
}
