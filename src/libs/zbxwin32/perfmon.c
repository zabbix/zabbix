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

#include "zbxwin32.h"

#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxthreads.h"
#include "zbxlog.h"

static ZBX_THREAD_LOCAL zbx_perf_counter_id_t	*PerfCounterList = NULL;

/* This struct contains mapping between built-in English object names and PDH indexes. */
/* If you change it then you also need to add enum values to zbx_builtin_object_ref_t. */
static struct builtin_object_ref
{
	unsigned long	pdhIndex;
	wchar_t		eng_name[PDH_MAX_COUNTER_NAME];
	DWORD		minSupported_dwMajorVersion;
	DWORD		minSupported_dwMinorVersion;
}
builtin_object_map[] =
{
	{ 0, L"System", 0, 0 },
	{ 0, L"Processor", 0, 0 },
	{ 0, L"Processor Information", 6, 1 },
	{ 0, L"Terminal Services", 0, 0 }
};

/* this enum must be only modified along with builtin_object_map[] */
typedef enum
{
	POI_SYSTEM = 0,
	POI_PROCESSOR,
	POI_PROCESSOR_INFORMATION,
	POI_TERMINAL_SERVICES,
	POI_MAX_INDEX = POI_TERMINAL_SERVICES
}
zbx_builtin_object_ref_t;

/* This struct contains mapping between built-in English counter names and PDH indexes. */
/* If you change it then you also need to add enum values to zbx_builtin_counter_ref_t. */
static struct builtin_counter_ref
{
	unsigned long			pdhIndex;
	zbx_builtin_object_ref_t	object;
	wchar_t				eng_name[PDH_MAX_COUNTER_NAME];
	DWORD				minSupported_dwMajorVersion;
	DWORD				minSupported_dwMinorVersion;
}
builtin_counter_map[] =
{
	{ 0,	POI_SYSTEM,			L"Processor Queue Length",	0,	0},
	{ 0,	POI_SYSTEM,			L"System Up Time",		0,	0},
	{ 0,	POI_PROCESSOR,			L"% Processor Time",		0, 	0},
	{ 0,	POI_PROCESSOR_INFORMATION,	L"% Processor Time",		6,	1},
	{ 0,	POI_TERMINAL_SERVICES,		L"Total Sessions",		0,	0}
};

PDH_STATUS	zbx_PdhMakeCounterPath(const char *function, PDH_COUNTER_PATH_ELEMENTS *cpe, char *counterpath)
{
	DWORD		dwSize = PDH_MAX_COUNTER_PATH;
	wchar_t		*wcounterPath = NULL;
	PDH_STATUS	pdh_status;

	wcounterPath = zbx_malloc(wcounterPath, sizeof(wchar_t) * PDH_MAX_COUNTER_PATH);

	if (ERROR_SUCCESS != (pdh_status = PdhMakeCounterPath(cpe, wcounterPath, &dwSize, 0)))
	{
		char	*object, *counter;

		object = zbx_unicode_to_utf8(cpe->szObjectName);
		counter = zbx_unicode_to_utf8(cpe->szCounterName);

		zabbix_log(LOG_LEVEL_ERR, "%s(): cannot make counterpath for \"\\%s\\%s\": %s",
				function, object, counter, zbx_strerror_from_module(pdh_status, L"PDH.DLL"));

		zbx_free(counter);
		zbx_free(object);
	}
	else
		zbx_unicode_to_utf8_static(wcounterPath, counterpath, PDH_MAX_COUNTER_PATH);

	zbx_free(wcounterPath);

	return pdh_status;
}

PDH_STATUS	zbx_PdhOpenQuery(const char *function, PDH_HQUERY query)
{
	PDH_STATUS	pdh_status;

	if (ERROR_SUCCESS != (pdh_status = PdhOpenQuery(NULL, 0, query)))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s(): call to PdhOpenQuery() failed: %s",
				function, zbx_strerror_from_module(pdh_status, L"PDH.DLL"));
	}

	return pdh_status;
}

/******************************************************************************
 *                                                                            *
 * Comments: counter is NULL if it is not in the collector,                   *
 *           do not call it for PERF_COUNTER_ACTIVE counters                  *
 *                                                                            *
 ******************************************************************************/
PDH_STATUS	zbx_PdhAddCounter(const char *function, zbx_perf_counter_data_t *counter, PDH_HQUERY query,
		const char *counterpath, zbx_perf_counter_lang_t lang, PDH_HCOUNTER *handle)
{
	/* pointer type to PdhAddEnglishCounterW() */
	typedef PDH_STATUS (WINAPI *ADD_ENG_COUNTER)(PDH_HQUERY, LPCWSTR, DWORD_PTR, PDH_HCOUNTER);

	PDH_STATUS	pdh_status = ERROR_SUCCESS;
	wchar_t		*wcounterPath = NULL;
	int		need_english;

	ZBX_THREAD_LOCAL static ADD_ENG_COUNTER add_eng_counter;
	ZBX_THREAD_LOCAL static int 		first_call = 1;

	need_english = PERF_COUNTER_LANG_DEFAULT != lang ||
			(NULL != counter && PERF_COUNTER_LANG_DEFAULT != counter->lang);

	/* PdhAddEnglishCounterW() is only available on Windows 2008/Vista and onwards, */
	/* so we need to resolve it dynamically and fail if it's not available */
	if (0 != first_call && 0 != need_english)
	{
		if (NULL == (add_eng_counter = (ADD_ENG_COUNTER)GetProcAddress(GetModuleHandle(L"PDH.DLL"),
				"PdhAddEnglishCounterW")))
		{
			zabbix_log(LOG_LEVEL_WARNING, "PdhAddEnglishCounter() is not available, "
					"perf_counter_en[] is not supported");
		}

		first_call = 0;
	}

	if (0 != need_english && NULL == add_eng_counter)
	{
		pdh_status = PDH_NOT_IMPLEMENTED;
	}

	if (ERROR_SUCCESS == pdh_status)
	{
		wcounterPath = zbx_utf8_to_unicode(counterpath);
	}

	if (ERROR_SUCCESS == pdh_status && NULL == *handle)
	{
		pdh_status = need_english ?
			add_eng_counter(query, wcounterPath, 0, handle) :
			PdhAddCounter(query, wcounterPath, 0, handle);
	}

	if (ERROR_SUCCESS != pdh_status && NULL != *handle)
	{
		if (ERROR_SUCCESS == PdhRemoveCounter(*handle))
			*handle = NULL;
	}

	if (ERROR_SUCCESS == pdh_status)
	{
		if (NULL != counter)
			counter->status = PERF_COUNTER_INITIALIZED;

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): PerfCounter '%s' successfully added", function, counterpath);
	}
	else
	{
		if (NULL != counter)
			counter->status = PERF_COUNTER_NOTSUPPORTED;

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): unable to add PerfCounter '%s': %s",
				function, counterpath, zbx_strerror_from_module(pdh_status, L"PDH.DLL"));
	}

	zbx_free(wcounterPath);

	return pdh_status;
}

PDH_STATUS	zbx_PdhCollectQueryData(const char *function, const char *counterpath, PDH_HQUERY query)
{
	PDH_STATUS	pdh_status;

	if (ERROR_SUCCESS != (pdh_status = PdhCollectQueryData(query)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot collect data '%s': %s",
				function, counterpath, zbx_strerror_from_module(pdh_status, L"PDH.DLL"));
	}

	return pdh_status;
}

PDH_STATUS	zbx_PdhGetRawCounterValue(const char *function, const char *counterpath, PDH_HCOUNTER handle, PPDH_RAW_COUNTER value)
{
	PDH_STATUS	pdh_status;

	if (ERROR_SUCCESS != (pdh_status = PdhGetRawCounterValue(handle, NULL, value)) ||
		(PDH_CSTATUS_VALID_DATA != value->CStatus && PDH_CSTATUS_NEW_DATA != value->CStatus))
	{
		if (ERROR_SUCCESS == pdh_status)
			pdh_status = value->CStatus;

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot get counter value '%s': %s",
				function, counterpath, zbx_strerror_from_module(pdh_status, L"PDH.DLL"));
	}

	return pdh_status;
}

/******************************************************************************
 *                                                                            *
 * Comments: Get the value of a counter. If it is a rate counter,             *
 *           sleep 1 second to get the second raw value.                      *
 *                                                                            *
 ******************************************************************************/
PDH_STATUS	zbx_calculate_counter_value(const char *function, const char *counterpath,
		zbx_perf_counter_lang_t lang, double *value)
{
	PDH_HQUERY		query;
	PDH_HCOUNTER		handle = NULL;
	PDH_STATUS		pdh_status;
	PDH_RAW_COUNTER		rawData, rawData2;
	PDH_FMT_COUNTERVALUE	counterValue;

	if (ERROR_SUCCESS != (pdh_status = zbx_PdhOpenQuery(function, &query)))
		return pdh_status;

	if (ERROR_SUCCESS != (pdh_status = zbx_PdhAddCounter(function, NULL, query, counterpath, lang, &handle)))
		goto close_query;

	if (ERROR_SUCCESS != (pdh_status = zbx_PdhCollectQueryData(function, counterpath, query)))
		goto remove_counter;

	if (ERROR_SUCCESS != (pdh_status = zbx_PdhGetRawCounterValue(function, counterpath, handle, &rawData)))
		goto remove_counter;

	if (PDH_CSTATUS_INVALID_DATA == (pdh_status = PdhCalculateCounterFromRawValue(handle, PDH_FMT_DOUBLE |
			PDH_FMT_NOCAP100, &rawData, NULL, &counterValue)))
	{
		/* some (e.g., rate) counters require two raw values, MSDN lacks documentation */
		/* about what happens but tests show that PDH_CSTATUS_INVALID_DATA is returned */

		zbx_sleep(1);

		if (ERROR_SUCCESS == (pdh_status = zbx_PdhCollectQueryData(function, counterpath, query)) &&
				ERROR_SUCCESS == (pdh_status = zbx_PdhGetRawCounterValue(function, counterpath,
				handle, &rawData2)))
		{
			pdh_status = PdhCalculateCounterFromRawValue(handle, PDH_FMT_DOUBLE | PDH_FMT_NOCAP100,
					&rawData2, &rawData, &counterValue);
		}
	}

	if (ERROR_SUCCESS != pdh_status || (PDH_CSTATUS_VALID_DATA != counterValue.CStatus &&
			PDH_CSTATUS_NEW_DATA != counterValue.CStatus))
	{
		if (ERROR_SUCCESS == pdh_status)
			pdh_status = counterValue.CStatus;

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot calculate counter value '%s': %s",
				function, counterpath, zbx_strerror_from_module(pdh_status, L"PDH.DLL"));
	}
	else
	{
		*value = counterValue.doubleValue;
	}
remove_counter:
	PdhRemoveCounter(handle);
close_query:
	PdhCloseQuery(query);

	return pdh_status;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get performance object index by reference value described by      *
 *          zbx_builtin_counter_ref_t enum                                    *
 *                                                                            *
 * Parameters: counter_ref - [IN] built-in performance object                 *
 *                                                                            *
 * Comments: Performance object index values can differ across Windows        *
 *           installations for the same names                                 *
 *                                                                            *
 ******************************************************************************/
DWORD	zbx_get_builtin_object_index(zbx_builtin_counter_ref_t counter_ref)
{
	return builtin_object_map[builtin_counter_map[counter_ref].object].pdhIndex;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get performance counter index by reference value described by     *
 *          zbx_builtin_counter_ref_t enum                                    *
 *                                                                            *
 * Parameters: counter_ref - [IN] built-in performance counter                *
 *                                                                            *
 * Comments: Performance counter index values can differ across Windows       *
 *           installations for the same names                                 *
 *                                                                            *
 ******************************************************************************/
DWORD	zbx_get_builtin_counter_index(zbx_builtin_counter_ref_t counter_ref)
{
	return builtin_counter_map[counter_ref].pdhIndex;
}

/******************************************************************************
 *                                                                            *
 * Purpose: function to read counter names/help from registry                 *
 *                                                                            *
 * Parameters: reg_key           - [IN] registry key                          *
 *             reg_value_name    - [IN] name of the registry value            *
 *                                                                            *
 * Return value: wchar_t* buffer with list of strings on success,             *
 *               NULL on failure                                              *
 *                                                                            *
 * Comments: This function should be normally called with reg_key parameter   *
 *           set to HKEY_PERFORMANCE_NLSTEXT (localized names) or             *
 *           HKEY_PERFORMANCE_TEXT (English names); and reg_value_name        *
 *           parameter set to L"Counter" parameter. It returns a list of      *
 *           null-terminated string pairs. Last string is followed by         *
 *           an additional null-terminator. The return buffer must be freed   *
 *           by the caller.                                                   *
 *                                                                            *
 ******************************************************************************/
wchar_t	*zbx_get_all_counter_names(HKEY reg_key, wchar_t *reg_value_name)
{
	wchar_t		*buffer = NULL;
	DWORD		buffer_size = 0;
	LSTATUS		status = ERROR_SUCCESS;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* query the size of the text data for further buffer allocation */
	if (ERROR_SUCCESS != (status = RegQueryValueEx(reg_key, reg_value_name, NULL, NULL, NULL, &buffer_size)))
	{
		zabbix_log(LOG_LEVEL_ERR, "RegQueryValueEx() failed at getting buffer size, 0x%lx",
				(unsigned long)status);
		goto finish;
	}

	buffer = (wchar_t*)zbx_malloc(buffer, (size_t)buffer_size);

	if (ERROR_SUCCESS != (status = RegQueryValueEx(reg_key, reg_value_name, NULL, NULL, (LPBYTE)buffer,
			&buffer_size)))
	{
		zabbix_log(LOG_LEVEL_ERR, "RegQueryValueEx() failed with 0x%lx", (unsigned long)status);
		zbx_free(buffer);
		goto finish;
	}
finish:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return buffer;
}

/******************************************************************************
 *                                                                            *
 * Purpose: fills performance counter name based on its index                 *
 *                                                                            *
 * Parameters: index - [IN]  PDH counter index                                *
 *             name  - [OUT] counter name buffer                              *
 *             size  - [IN]  counter name buffer size                         *
 *                                                                            *
 * Return value: SUCCEED if counter data is valid,                            *
 *               FAIL otherwise                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_perf_name_by_index(DWORD index, wchar_t *name, DWORD size)
{
	int		ret = SUCCEED;
	PDH_STATUS	pdh_status;

	if (ERROR_SUCCESS != (pdh_status = PdhLookupPerfNameByIndex(NULL, index, name, &size)))
	{
		zabbix_log(LOG_LEVEL_ERR, "PdhLookupPerfNameByIndex() failed: %s",
				zbx_strerror_from_module(pdh_status, L"PDH.DLL"));
		ret = FAIL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if a specified counter path data is pointing to a valid    *
 *          counter                                                           *
 *                                                                            *
 * Parameters: cpe - [IN] PDH counter path data                               *
 *                                                                            *
 * Return value: SUCCEED if counter data is valid,                            *
 *               FAIL otherwise                                               *
 *                                                                            *
 ******************************************************************************/
static int	validate_counter_path(PDH_COUNTER_PATH_ELEMENTS	*cpe)
{
	int		ret = FAIL;
	DWORD		s = 0;
	PDH_STATUS	pdh_status;
	wchar_t		*path = NULL;

	if (PDH_MORE_DATA == (pdh_status = PdhMakeCounterPath(cpe, NULL, &s, 0)))
	{
		path = zbx_malloc(path, sizeof(wchar_t) * s);

		if (ERROR_SUCCESS != (pdh_status = PdhMakeCounterPath(cpe, path, &s, 0)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "PdhMakeCounterPath() failed: %s",
					zbx_strerror_from_module(pdh_status, L"PDH.DLL"));
		}
		else if (ERROR_SUCCESS != (pdh_status = PdhValidatePath(path)))
		{
			if (PDH_CSTATUS_NO_COUNTER != pdh_status && PDH_CSTATUS_NO_INSTANCE != pdh_status)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "PdhValidatePath() szObjectName:%s szCounterName:%s"
						" failed: %s", cpe->szObjectName, cpe->szCounterName,
						zbx_strerror_from_module(pdh_status, L"PDH.DLL"));
			}
		}
		else
		{
			ret = SUCCEED;
		}

		zbx_free(path);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "PdhMakeCounterPath() failed: %s",
				zbx_strerror_from_module(pdh_status, L"PDH.DLL"));
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if specified counter is valid successor of the object      *
 *                                                                            *
 * Parameters: object  - [IN] PDH object index                                *
 *             counter - [IN] PDH counter index                               *
 *                                                                            *
 * Return value: SUCCEED if object - counter combination is valid,            *
 *               FAIL otherwise                                               *
 *                                                                            *
 ******************************************************************************/
static int	validate_object_counter(DWORD object, DWORD counter)
{
	PDH_COUNTER_PATH_ELEMENTS	*cpe;
	int				ret = SUCCEED;

	cpe = (PDH_COUNTER_PATH_ELEMENTS *)zbx_malloc(NULL, sizeof(PDH_COUNTER_PATH_ELEMENTS));
	memset(cpe, 0, sizeof(PDH_COUNTER_PATH_ELEMENTS));

	cpe->szObjectName = zbx_malloc(NULL, sizeof(wchar_t) * PDH_MAX_COUNTER_NAME);
	cpe->szCounterName = zbx_malloc(NULL, sizeof(wchar_t) * PDH_MAX_COUNTER_NAME);

	if (SUCCEED != get_perf_name_by_index(object, cpe->szObjectName, PDH_MAX_COUNTER_NAME) ||
			SUCCEED != get_perf_name_by_index(counter, cpe->szCounterName, PDH_MAX_COUNTER_NAME))
	{
		ret = FAIL;
		goto out;
	}

	if (SUCCEED != validate_counter_path(cpe))
	{
		/* try with "any" instance name */
		cpe->szInstanceName = L"*";

		if (SUCCEED != validate_counter_path(cpe))
			ret = FAIL;
	}

out:
	zbx_free(cpe->szCounterName);
	zbx_free(cpe->szObjectName);
	zbx_free(cpe);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Scans registry key with all performance counter English names     *
 *          and obtains system-dependent PDH counter indexes for further      *
 *          use by corresponding items.                                       *
 *                                                                            *
 * Return value: SUCCEED/FAIL                                                 *
 *                                                                            *
 * Comments: This function should be normally called during agent             *
 *           initialization from zbx_init_perf_collector().                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_init_builtin_counter_indexes(void)
{
#	define VER_CMP(vi, c)								\
		10 * vi->dwMajorVersion + vi->dwMinorVersion >=				\
		10 * c.minSupported_dwMajorVersion + c.minSupported_dwMinorVersion

	int				ret = SUCCEED, i;
	wchar_t				*counter_text, *eng_names, *counter_base;
	DWORD				counter_index;
	static const OSVERSIONINFOEX	*vi = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == vi && NULL == (vi = zbx_win_getversion()))
	{
		zabbix_log(LOG_LEVEL_ERR, "Failed to get windows version");
		ret = FAIL;
		goto out;
	}

	/* Get buffer holding a list of performance counter indexes and English counter names. */
	/* L"Counter" stores names, L"Help" stores descriptions ("Help" is not used).          */
	if (NULL == (counter_base = eng_names = zbx_get_all_counter_names(HKEY_PERFORMANCE_TEXT, L"Counter")))
	{
		ret = FAIL;
		goto out;
	}

	/* bypass first pair of counter data elements - these contain number of records */
	counter_base += wcslen(counter_base) + 1;
	counter_base += wcslen(counter_base) + 1;

	/* get builtin object names */
	for (counter_text = counter_base; 0 != *counter_text; counter_text += wcslen(counter_text) + 1)
	{
		counter_index = (DWORD)_wtoi(counter_text);
		counter_text += wcslen(counter_text) + 1;

		for (i = 0; i < ARRSIZE(builtin_object_map); i++)
		{
			if (0 == builtin_object_map[i].pdhIndex && VER_CMP(vi, builtin_object_map[i]) && 0 ==
					wcscmp(builtin_object_map[i].eng_name, counter_text))
			{
				builtin_object_map[i].pdhIndex = counter_index;
				break;
			}
		}
	}

	/* Get builtin counter names. There may be counter name duplicates. */
	/* Validate them in combination with parent object.                 */
	for (counter_text = counter_base; 0 != *counter_text; counter_text += wcslen(counter_text) + 1)
	{
		counter_index = (DWORD)_wtoi(counter_text);
		counter_text += wcslen(counter_text) + 1;

		for (i = 0; i < ARRSIZE(builtin_counter_map); i++)
		{
			if (0 == builtin_counter_map[i].pdhIndex && VER_CMP(vi, builtin_counter_map[i]) && 0 ==
					wcscmp(builtin_counter_map[i].eng_name, counter_text) && SUCCEED ==
					validate_object_counter(zbx_get_builtin_object_index(i), counter_index))
			{
				builtin_counter_map[i].pdhIndex = counter_index;
				break;
			}
		}
	}

	zbx_free(eng_names);

#define CHECK_COUNTER_INDICES(index_map)								\
	for (i = 0; i < ARRSIZE(index_map); i++)							\
	{												\
		if (0 == index_map[i].pdhIndex && VER_CMP(vi, index_map[i]))				\
		{											\
			char	*counter;								\
													\
			counter = zbx_unicode_to_utf8(index_map[i].eng_name);				\
			zabbix_log(LOG_LEVEL_ERR, "Failed to initialize builtin counter: %s", counter);	\
			zbx_free(counter);								\
		}											\
	}

	/* check if all builtin counter indices are filled */
	CHECK_COUNTER_INDICES(builtin_object_map);
	CHECK_COUNTER_INDICES(builtin_counter_map);

#undef CHECK_COUNTER_INDICES
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef VER_CMP
}

/******************************************************************************
 *                                                                            *
 * Purpose: get performance object or counter name by PDH index               *
 *                                                                            *
 * Parameters: pdhIndex - [IN] built-in performance counter index             *
 *                                                                            *
 * Return value: PDH performance counter name                                 *
 *               or "UnknownPerformanceCounter" on failure                    *
 *                                                                            *
 * Comments: Performance counter index values can differ across Windows       *
 *           installations for the same names                                 *
 *                                                                            *
 ******************************************************************************/
wchar_t	*zbx_get_counter_name(DWORD pdhIndex)
{
	zbx_perf_counter_id_t	*counterName;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() pdhIndex:%u", __func__, pdhIndex);

	counterName = PerfCounterList;
	while (NULL != counterName)
	{
		if (counterName->pdhIndex == pdhIndex)
			break;
		counterName = counterName->next;
	}

	if (NULL == counterName)
	{
		counterName = (zbx_perf_counter_id_t *)zbx_malloc(counterName, sizeof(zbx_perf_counter_id_t));

		memset(counterName, 0, sizeof(zbx_perf_counter_id_t));
		counterName->pdhIndex = pdhIndex;
		counterName->next = PerfCounterList;

		if (SUCCEED == get_perf_name_by_index(pdhIndex, counterName->name, PDH_MAX_COUNTER_NAME))
		{
			PerfCounterList = counterName;
		}
		else
		{
			zbx_free(counterName);
			zabbix_log(LOG_LEVEL_DEBUG, "End of %s():FAIL", __func__);
			return L"UnknownPerformanceCounter";
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():SUCCEED", __func__);

	return counterName->name;
}

int	zbx_check_counter_path(char *counterPath, int convert_from_numeric)
{
	PDH_COUNTER_PATH_ELEMENTS	*cpe = NULL;
	PDH_STATUS			status;
	int				ret = FAIL;
	DWORD				dwSize = 0;
	wchar_t				*wcounterPath;

	wcounterPath = zbx_utf8_to_unicode(counterPath);

	status = PdhParseCounterPath(wcounterPath, NULL, &dwSize, 0);
	if (PDH_MORE_DATA == status || ERROR_SUCCESS == status)
	{
		cpe = (PDH_COUNTER_PATH_ELEMENTS *)zbx_malloc(cpe, dwSize);
	}
	else
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot get required buffer size for counter path '%s': %s",
				counterPath, zbx_strerror_from_module(status, L"PDH.DLL"));
		goto clean;
	}

	if (ERROR_SUCCESS != (status = PdhParseCounterPath(wcounterPath, cpe, &dwSize, 0)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse counter path '%s': %s",
				counterPath, zbx_strerror_from_module(status, L"PDH.DLL"));
		goto clean;
	}

	if (0 != convert_from_numeric)
	{
		int is_numeric = (SUCCEED == zbx_wis_uint(cpe->szObjectName) ? 0x01 : 0);
		is_numeric |= (SUCCEED == zbx_wis_uint(cpe->szCounterName) ? 0x02 : 0);

		if (0 != is_numeric)
		{
			if (0x01 & is_numeric)
				cpe->szObjectName = zbx_get_counter_name(_wtoi(cpe->szObjectName));
			if (0x02 & is_numeric)
				cpe->szCounterName = zbx_get_counter_name(_wtoi(cpe->szCounterName));

			if (ERROR_SUCCESS != zbx_PdhMakeCounterPath(__func__, cpe, counterPath))
				goto clean;

			zabbix_log(LOG_LEVEL_DEBUG, "counter path converted to '%s'", counterPath);
		}
	}

	ret = SUCCEED;
clean:
	zbx_free(cpe);
	zbx_free(wcounterPath);

	return ret;
}
