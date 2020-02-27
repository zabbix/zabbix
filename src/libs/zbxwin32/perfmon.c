/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "stats.h"
#include "perfmon.h"
#include "log.h"

static ZBX_THREAD_LOCAL zbx_perf_counter_id_t	*PerfCounterList = NULL;

/* This struct contains mapping between built-in English counter names and PDH indexes. */
/* If you change it then you also need to add enum values to zbx_builtin_counter_ref_t.  */
static struct builtin_counter_ref
{
	unsigned long	pdhIndex;
	wchar_t 	eng_name[PDH_MAX_COUNTER_NAME];
}
builtin_counter_map[] =
{
	{ 0, L"System" },
	{ 0, L"Processor" },
	{ 0, L"Processor Information" },
	{ 0, L"% Processor Time" },
	{ 0, L"Processor Queue Length" },
	{ 0, L"System Up Time" },
	{ 0, L"Terminal Services" },
	{ 0, L"Total Sessions" }
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
				function, object, counter, strerror_from_module(pdh_status, L"PDH.DLL"));

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
				function, strerror_from_module(pdh_status, L"PDH.DLL"));
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
				function, counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));
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
				function, counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));
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
				function, counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));
	}

	return pdh_status;
}

/******************************************************************************
 *                                                                            *
 * Comments: Get the value of a counter. If it is a rate counter,             *
 *           sleep 1 second to get the second raw value.                      *
 *                                                                            *
 ******************************************************************************/
PDH_STATUS	calculate_counter_value(const char *function, const char *counterpath,
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
				function, counterpath, strerror_from_module(pdh_status, L"PDH.DLL"));
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
 * Function: get_builtin_counter_index                                        *
 *                                                                            *
 * Purpose: get performance counter index by reference value described by     *
 *          zbx_builtin_counter_ref_t enum                                    *
 *                                                                            *
 * Parameters: counter_ref    - [IN] built-in performance counter             *
 *                                                                            *
 * Return value: PDH performance counter index or 0 on failure                *
 *                                                                            *
 * Comments: Performance counter index values can differ across Windows       *
 *           installations for the same names                                 *
 *                                                                            *
 ******************************************************************************/
DWORD	get_builtin_counter_index(zbx_builtin_counter_ref_t counter_ref)
{
	if (PCI_MAX_INDEX < counter_ref)
	{
		static int first_error = 1;

		if (0 != first_error)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			first_error = 0;
		}

		return 0;
	}

	return builtin_counter_map[counter_ref].pdhIndex;
}

/******************************************************************************
 *                                                                            *
 * Function: get_all_counter_eng_names                                        *
 *                                                                            *
 * Purpose: helper function for init_builtin_counter_indexes()                *
 *                                                                            *
 * Parameters: reg_value_name    - [IN] name of the registry value            *
 *                                                                            *
 * Return value: wchar_t* buffer with list of strings on success,             *
 *               NULL on failure                                              *
 *                                                                            *
 * Comments: This function should be normally called with L"Counter"          *
 *           parameter. It returns a list of null-terminated string pairs.    *
 *           Last string is followed by an additional null-terminator.        *
 *           The return buffer must be freed by the caller.                   *
 *                                                                            *
 ******************************************************************************/
static wchar_t	*get_all_counter_eng_names(wchar_t *reg_value_name)
{
	wchar_t		*buffer = NULL;
	DWORD		buffer_size = 0;
	LSTATUS		status = ERROR_SUCCESS;
	/* this registry key guaranteed to hold english counter texts even in localized Win versions */
	static HKEY reg_key = HKEY_PERFORMANCE_TEXT;

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
 * Function: init_builtin_counter_indexes                                     *
 *                                                                            *
 * Purpose: scans registry key with all performance counter English names     *
 *          and obtains system-dependent PDH counter indexes for further      *
 *          use by corresponding items                                        *
 *                                                                            *
 * Return value: SUCCEED/FAIL                                                 *
 *                                                                            *
 * Comments: This function should be normally called during agent             *
 *           initialization from init_perf_collector().                       *
 *                                                                            *
 ******************************************************************************/
int	init_builtin_counter_indexes(void)
{
	int 		ret = FAIL, i;
	wchar_t 	*counter_text, *saved_ptr;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* Get buffer holding a list of performance counter indexes and English counter names. */
	/* L"Counter" stores names, L"Help" stores descriptions ("Help" is not used).          */
	if (NULL == (counter_text = saved_ptr = get_all_counter_eng_names(L"Counter")))
		goto out;

	/* bypass first pair of counter data elements - these contain number of records */
	counter_text += wcslen(counter_text) + 1;
	counter_text += wcslen(counter_text) + 1;

	for (; 0 != *counter_text; counter_text += wcslen(counter_text) + 1)
	{
		DWORD counter_index = (DWORD)_wtoi(counter_text);
		counter_text += wcslen(counter_text) + 1;

		for (i = 0; i < ARRSIZE(builtin_counter_map); i++)
		{
			if (0 == wcscmp(builtin_counter_map[i].eng_name, counter_text))
			{
				builtin_counter_map[i].pdhIndex = counter_index;
				break;
			}
		}
	}

	ret = SUCCEED;
	zbx_free(saved_ptr);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

wchar_t	*get_counter_name(DWORD pdhIndex)
{
	zbx_perf_counter_id_t	*counterName;
	DWORD			dwSize;
	PDH_STATUS		pdh_status;

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

		dwSize = PDH_MAX_COUNTER_NAME;
		if (ERROR_SUCCESS == (pdh_status = PdhLookupPerfNameByIndex(NULL, pdhIndex, counterName->name, &dwSize)))
			PerfCounterList = counterName;
		else
		{
			zabbix_log(LOG_LEVEL_ERR, "PdhLookupPerfNameByIndex() failed: %s",
					strerror_from_module(pdh_status, L"PDH.DLL"));
			zbx_free(counterName);
			zabbix_log(LOG_LEVEL_DEBUG, "End of %s():FAIL", __func__);
			return L"UnknownPerformanceCounter";
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():SUCCEED", __func__);

	return counterName->name;
}

int	check_counter_path(char *counterPath, int convert_from_numeric)
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
				counterPath, strerror_from_module(status, L"PDH.DLL"));
		goto clean;
	}

	if (ERROR_SUCCESS != (status = PdhParseCounterPath(wcounterPath, cpe, &dwSize, 0)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse counter path '%s': %s",
				counterPath, strerror_from_module(status, L"PDH.DLL"));
		goto clean;
	}

	if (0 != convert_from_numeric)
	{
		int is_numeric = (SUCCEED == _wis_uint(cpe->szObjectName) ? 0x01 : 0);
		is_numeric |= (SUCCEED == _wis_uint(cpe->szCounterName) ? 0x02 : 0);

		if (0 != is_numeric)
		{
			if (0x01 & is_numeric)
				cpe->szObjectName = get_counter_name(_wtoi(cpe->szObjectName));
			if (0x02 & is_numeric)
				cpe->szCounterName = get_counter_name(_wtoi(cpe->szCounterName));

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
