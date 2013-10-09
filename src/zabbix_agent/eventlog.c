/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
#include "log.h"
#include "eventlog.h"

#define MAX_INSERT_STRS 100
#define MAX_MSG_LENGTH 1024

#define EVENTLOG_REG_PATH TEXT("SYSTEM\\CurrentControlSet\\Services\\EventLog\\")

static EVT_HANDLE	(WINAPI *EvtQueryFunc)(EVT_HANDLE, LPCWSTR, LPCWSTR, DWORD) = NULL;
static EVT_HANDLE	(WINAPI *EvtCreateRenderContextFunc)(DWORD, LPCWSTR*, DWORD) = NULL;
static BOOL		(WINAPI *EvtNextFunc)(EVT_HANDLE, DWORD, PEVT_HANDLE, DWORD, DWORD, PDWORD) = NULL;
static BOOL		(WINAPI *EvtRenderFunc)(EVT_HANDLE, EVT_HANDLE, DWORD, DWORD, PVOID, PDWORD, PDWORD) = NULL;
static EVT_HANDLE	(WINAPI *EvtOpenPublisherMetadataFunc)(EVT_HANDLE, LPCWSTR, LPCWSTR, LCID, DWORD) = NULL;
static BOOL		(WINAPI *EvtFormatMessageFunc)(EVT_HANDLE, EVT_HANDLE, DWORD, DWORD, PEVT_VARIANT, DWORD,
					DWORD, LPWSTR, PDWORD) = NULL;
static BOOL		(WINAPI *EvtGetLogInfoFunc)(EVT_HANDLE, EVT_LOG_PROPERTY_ID, DWORD, PEVT_VARIANT,
					PDWORD) = NULL;
static EVT_HANDLE	(WINAPI *EvtOpenLogFunc)(EVT_HANDLE, LPCWSTR, DWORD) = NULL;
static BOOL		(WINAPI *EvtCloseFunc)(EVT_HANDLE) = NULL;

static int GetRecordInfo(LPCWSTR wsource, zbx_uint64_t *lastlogsize, zbx_uint64_t *FirstID, zbx_uint64_t *LastID)
{
	const char	*__function_name = "GetRecordInfo";

	EVT_HANDLE	log = NULL;
	PEVT_VARIANT	logbuf = NULL;
	HANDLE		eventlog_handle = NULL;
	HANDLE		eventlog_context_handle = NULL;
	HANDLE		eventlog_each_handle = NULL;
	PEVT_VARIANT	eventlog_array = NULL;
	DWORD		status = 0;
	LPWSTR		query_array[] = {L"/Event/System/EventRecordID"};
	DWORD		dwReturned = 0, dwValuesCount = 0, dwBufferSize = 0;
	int		ret = FAIL;

	*FirstID = 0;
	*LastID = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == (log = EvtOpenLogFunc(NULL, wsource, EvtOpenChannelPath)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot open eventlog (%s) with error: %s", zbx_unicode_to_utf8(wsource),
				strerror_from_system(GetLastError()));
		goto finish;
	}

	if (TRUE != EvtGetLogInfoFunc(log, EvtLogNumberOfLogRecords, dwBufferSize, NULL, &dwReturned))
	{
		if (ERROR_INSUFFICIENT_BUFFER == (status = GetLastError()))
		{
			dwBufferSize = dwReturned;
			logbuf = (PEVT_VARIANT)zbx_malloc(logbuf, dwBufferSize);

			EvtGetLogInfoFunc(log, EvtLogNumberOfLogRecords, dwBufferSize, logbuf, &dwReturned);
		}

		if (ERROR_SUCCESS != (status = GetLastError()))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "EvtGetLogInfo failed with error: %s",
					strerror_from_system(GetLastError()));
			goto finish;
		}
	}

	*LastID = logbuf[0].UInt64Val;

	if (NULL == (eventlog_context_handle = EvtCreateRenderContextFunc(ARRSIZE(query_array), (LPCWSTR*)query_array,
			EvtRenderContextValues)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "EvtCreateRenderContext failed: %s", strerror_from_system(GetLastError()));
		goto finish;
	}

	if (NULL == (eventlog_handle = EvtQueryFunc(NULL, wsource, NULL, EvtQueryChannelPath)))
	{
		if (ERROR_EVT_CHANNEL_NOT_FOUND == (status = GetLastError()))
			zabbix_log(LOG_LEVEL_DEBUG, "EvtQuery channel missed: %s", strerror_from_system(status));
		else
			zabbix_log(LOG_LEVEL_DEBUG, "EvtQuery failed: %s", strerror_from_system(status));

		goto finish;
	}

	if (TRUE != EvtNextFunc(eventlog_handle, 1, &eventlog_each_handle, INFINITE, 0, &dwReturned))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "First EvtNext failed: %s", strerror_from_system(GetLastError()));
		*FirstID = 1;
		*LastID = 1;
		*lastlogsize = 0;
		ret = SUCCEED;
		goto finish;
	}

	dwBufferSize = 0;
	dwReturned = 0;

	if (TRUE != EvtRenderFunc(eventlog_context_handle, eventlog_each_handle, EvtRenderEventValues,
			dwBufferSize, eventlog_array, &dwReturned, &dwValuesCount))
	{
		if (ERROR_INSUFFICIENT_BUFFER == (status = GetLastError()))
		{
			dwBufferSize = dwReturned;
			eventlog_array = (PEVT_VARIANT)zbx_malloc(eventlog_array, dwBufferSize);

			if (TRUE != EvtRenderFunc(eventlog_context_handle, eventlog_each_handle, EvtRenderEventValues,
					dwBufferSize, eventlog_array, &dwReturned, &dwValuesCount))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "EvtRender failed: %s",
						strerror_from_system(GetLastError()));
				goto finish;
			}
		}

		if (ERROR_SUCCESS != (status = GetLastError()))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "EvtRender failed: %s", strerror_from_system(GetLastError()));
			goto finish;
		}
	}

	*FirstID = eventlog_array[0].UInt64Val;

	ret = SUCCEED;

finish:
	if (NULL != eventlog_each_handle)
		EvtCloseFunc(eventlog_each_handle);
	if (NULL != eventlog_handle)
		EvtCloseFunc(eventlog_handle);
	if (NULL != eventlog_context_handle)
		EvtCloseFunc(eventlog_context_handle);
	if (NULL != log)
		EvtCloseFunc(log);

	zbx_free(logbuf);
	zbx_free(eventlog_array);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s FirstID:" ZBX_FS_UI64 " LastID:" ZBX_FS_UI64,
			__function_name, zbx_result_string(ret), *FirstID, *LastID);

	return ret;
}

static long	zbx_get_eventlog_message_xpath(LPCTSTR wsource, long which, char **out_source, char **out_message,
		unsigned short *out_severity, unsigned long *out_timestamp, unsigned long *out_eventid)
{
	const char	*__function_name = "zbx_get_eventlog_message_xpath";
	long		ret = FAIL;
	LPSTR		tmp_str = NULL;
	LPWSTR		tmp_wstr = NULL;
	LPWSTR		event_query = NULL; /* L"Event/System[EventRecordID=WHICH]" */
	unsigned long	status = ERROR_SUCCESS;
	PEVT_VARIANT	eventlog_array = NULL;
	HANDLE		eventlog_handle = NULL;
	HANDLE		eventlog_each_handle = NULL;
	HANDLE		eventlog_context_handle = NULL;
	HANDLE		eventlog_providermetadata_handle = NULL;
	LPWSTR		query_array[] = {
			L"/Event/System/Provider/@Name",
			L"/Event/System/EventRecordID",
			L"/Event/System/Level",
			L"/Event/System/TimeCreated/@SystemTime",
			L"Event/System/EventID",
			L"Event/EventData/Data"};
	DWORD		dwReturned = 0, dwValuesCount = 0, dwBufferSize = 0;
	const ULONGLONG	sec_1970 = 116444736000000000;

	assert(out_source);
	assert(out_message);
	assert(out_severity);
	assert(out_timestamp);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() which:%ld", __function_name, which);

	*out_source	= NULL;
	*out_message	= NULL;
	*out_severity	= 0;
	*out_timestamp	= 0;

	if (NULL == wsource || L'\0' == *wsource)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Can't open eventlog with empty name");
		goto finish;
	}

	tmp_str = zbx_dsprintf(NULL, "Event/System[EventRecordID=%ld]", which);
	event_query = zbx_utf8_to_unicode(tmp_str);
	zbx_free(tmp_str);

	if (NULL == (eventlog_handle = EvtQueryFunc(NULL, wsource, event_query, EvtQueryChannelPath)))
	{
		if (ERROR_EVT_CHANNEL_NOT_FOUND == (status = GetLastError()))
			zabbix_log(LOG_LEVEL_DEBUG, "EvtQuery channel missed: %s", strerror_from_system(status));
		else
			zabbix_log(LOG_LEVEL_DEBUG, "EvtQuery failed: %s", strerror_from_system(status));

		goto finish;
	}

	if (NULL == (eventlog_context_handle = EvtCreateRenderContextFunc(ARRSIZE(query_array), (LPCWSTR*)query_array,
			EvtRenderContextValues)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "EvtCreateRenderContext failed: %s", strerror_from_system(GetLastError()));
		goto finish;
	}

	if (TRUE != EvtNextFunc(eventlog_handle, 1, &eventlog_each_handle, INFINITE, 0, &dwReturned))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "first EvtNext failed: %s", strerror_from_system(GetLastError()));
		goto finish;
	}

	if (TRUE != EvtRenderFunc(eventlog_context_handle, eventlog_each_handle, EvtRenderEventValues,
			dwBufferSize, eventlog_array, &dwReturned, &dwValuesCount))
	{
		if (ERROR_INSUFFICIENT_BUFFER == (status = GetLastError()))
		{
			dwBufferSize = dwReturned;
			eventlog_array = (PEVT_VARIANT)zbx_malloc(eventlog_array, dwBufferSize);

			if (TRUE != EvtRenderFunc(eventlog_context_handle, eventlog_each_handle, EvtRenderEventValues,
					dwBufferSize, eventlog_array, &dwReturned, &dwValuesCount))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "EvtRender failed: %s",
						strerror_from_system(GetLastError()));
				goto finish;
			}
		}

		if (ERROR_SUCCESS != (status = GetLastError()))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "EvtRender failed: %s", strerror_from_system(GetLastError()));
			goto finish;
		}
	}

	*out_source = zbx_unicode_to_utf8(eventlog_array[0].StringVal);
	*out_eventid = (unsigned long)(eventlog_array[4].UInt16Val);
	*out_timestamp = (unsigned long)((eventlog_array[3].FileTimeVal - sec_1970) / 10000000);
	*out_severity = eventlog_array[2].ByteVal;

	if (NULL == (eventlog_providermetadata_handle = EvtOpenPublisherMetadataFunc(NULL,
			eventlog_array[0].StringVal, NULL, 0, 0)))
	{
		size_t	msg_alloc = 0, msg_offset = 0;

		zabbix_log(LOG_LEVEL_DEBUG, "EvtOpenPublisherMetadata failed: %s",
				strerror_from_system(GetLastError()));

		zbx_snprintf_alloc(out_message, &msg_alloc, &msg_offset, "The description for Event ID (%lu) from"
				" source (%s) cannot be found. Either the component that raises this event is not"
				" installed on your local computer or the installation is corrupted. You can install or"
				" repair the component on the local computer.",
				*out_eventid, NULL == *out_source ? "" : *out_source);

		if (EvtVarTypeNull != eventlog_array[5].Type)
		{
			char	*data;

			data = zbx_unicode_to_utf8(eventlog_array[5].StringArr[0]);

			zbx_snprintf_alloc(out_message, &msg_alloc, &msg_offset,
					" The following information was included with the event: %s", data);

			zbx_free(data);
		}

		ret = SUCCEED;
		goto finish;
	}

	dwBufferSize = 0;
	dwReturned = 0;

	if (TRUE != EvtFormatMessageFunc(eventlog_providermetadata_handle, eventlog_each_handle, 0, 0,
			NULL, EvtFormatMessageEvent, dwBufferSize, tmp_wstr, &dwReturned))
	{
		if (ERROR_INSUFFICIENT_BUFFER == (status = GetLastError()))
		{
			dwBufferSize = dwReturned;
			tmp_wstr = (LPWSTR)zbx_malloc(tmp_wstr, dwBufferSize * sizeof(WCHAR));

			if (TRUE != EvtFormatMessageFunc(eventlog_providermetadata_handle, eventlog_each_handle, 0, 0,
					NULL, EvtFormatMessageEvent, dwBufferSize, tmp_wstr, &dwReturned))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "EvtFormatMessage failed: %s",
						strerror_from_system(GetLastError()));
				goto finish;
			}
		}

		if (ERROR_SUCCESS != (status = GetLastError()))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "EvtFormatMessage failed: %s",
					strerror_from_system(GetLastError()));
			goto finish;
		}
	}

	*out_message= zbx_unicode_to_utf8(tmp_wstr);

	ret = SUCCEED;

finish:
	zbx_free(tmp_wstr);
	zbx_free(event_query);
	zbx_free(eventlog_array);

	if (NULL != eventlog_providermetadata_handle)
		EvtCloseFunc(eventlog_providermetadata_handle);
	if (NULL != eventlog_each_handle)
		EvtCloseFunc(eventlog_each_handle);
	if (NULL != eventlog_context_handle)
		EvtCloseFunc(eventlog_context_handle);
	if (NULL != eventlog_handle)
		EvtCloseFunc(eventlog_handle);

	if (FAIL == ret)
	{
		zbx_free(*out_source);
		zbx_free(*out_message);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/* open event logger and return number of records */
static int    zbx_open_eventlog(LPCTSTR wsource, HANDLE *eventlog_handle, zbx_uint64_t *pNumRecords, zbx_uint64_t *pLatestRecord)
{
	const char	*__function_name = "zbx_open_eventlog";
	TCHAR		reg_path[MAX_PATH];
	HKEY		hk = NULL;
	int		ret = FAIL;

	assert(eventlog_handle);
	assert(pNumRecords);
	assert(pLatestRecord);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	*eventlog_handle = NULL;
	*pNumRecords = 0;
	*pLatestRecord = 0;

	/* Get path to eventlog */
	zbx_wsnprintf(reg_path, MAX_PATH, EVENTLOG_REG_PATH TEXT("%s"), wsource);

	if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, reg_path, 0, KEY_READ, &hk))
		goto out;

	RegCloseKey(hk);

	if (NULL == (*eventlog_handle = OpenEventLog(NULL, wsource)))	/* open log file */
		goto out;

	if (0 == GetNumberOfEventLogRecords(*eventlog_handle, (unsigned long*)pNumRecords))	/* get number of records */
		goto out;

	if (0 == GetOldestEventLogRecord(*eventlog_handle, (unsigned long*)pLatestRecord))
		goto out;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() pNumRecords:%ld pLatestRecord:%ld",
			__function_name, *pNumRecords, *pLatestRecord);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/* close event logger */
static int	zbx_close_eventlog(HANDLE eventlog_handle)
{
	if (NULL != eventlog_handle)
		CloseEventLog(eventlog_handle);

	return SUCCEED;
}

/* get Nth error from event log. 1 is the first. */
static int	zbx_get_eventlog_message(LPCTSTR wsource, HANDLE eventlog_handle, long which, char **out_source, char **out_message,
		unsigned short *out_severity, unsigned long *out_timestamp, unsigned long *out_eventid)
{
	const char	*__function_name = "zbx_get_eventlog_message";
	int		buffer_size = 512;
	EVENTLOGRECORD	*pELR = NULL;
	DWORD		dwRead, dwNeeded, dwErr;
	TCHAR		stat_buf[MAX_PATH], MsgDll[MAX_PATH];
	HKEY		hk = NULL;
	LPTSTR		pFile = NULL, pNextFile = NULL;
	DWORD		szData, Type;
	HINSTANCE	hLib = NULL;				/* handle to the messagetable DLL */
	LPTSTR		pCh, aInsertStrs[MAX_INSERT_STRS];	/* array of pointers to insert */
	LPTSTR		msgBuf = NULL;				/* hold text of the error message */
	char		*buf = NULL;
	long		i, err = 0;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() which:%ld", __function_name, which);

	*out_source	= NULL;
	*out_message	= NULL;
	*out_severity	= 0;
	*out_timestamp	= 0;
	*out_eventid	= 0;
	memset(aInsertStrs, 0, sizeof(aInsertStrs));

	pELR = (EVENTLOGRECORD *)zbx_malloc((void *)pELR, buffer_size);
retry:
	if (0 == ReadEventLog(eventlog_handle, EVENTLOG_SEEK_READ | EVENTLOG_FORWARDS_READ, which,
			pELR, buffer_size, &dwRead, &dwNeeded))
	{
		dwErr = GetLastError();
		if (dwErr == ERROR_INSUFFICIENT_BUFFER)
		{
			buffer_size = dwNeeded;
			pELR = (EVENTLOGRECORD *)zbx_realloc((void *)pELR, buffer_size);
			goto retry;
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): %s", __function_name, strerror_from_system(dwErr));
			goto out;
		}
	}

	*out_severity	= pELR->EventType;			/* return event type */
	*out_timestamp	= pELR->TimeGenerated;			/* return timestamp */
	*out_eventid	= pELR->EventID & 0xffff;
	*out_source	= zbx_unicode_to_utf8((LPTSTR)(pELR + 1));	/* copy source name */

	err = FAIL;

	/* prepare the array of insert strings for FormatMessage - the
	insert strings are in the log entry. */
	for (i = 0, pCh = (LPTSTR)((LPBYTE)pELR + pELR->StringOffset);
			i < pELR->NumStrings && i < MAX_INSERT_STRS;
			i++, pCh += zbx_strlen(pCh) + 1) /* point to next string */
	{
		aInsertStrs[i] = pCh;
	}

	/* Get path to message dll */
	zbx_wsnprintf(stat_buf, MAX_PATH, EVENTLOG_REG_PATH TEXT("%s\\%s"), wsource, (LPTSTR)(pELR + 1));

	if (ERROR_SUCCESS == RegOpenKeyEx(HKEY_LOCAL_MACHINE, stat_buf, 0, KEY_READ, &hk))
	{
		if (ERROR_SUCCESS == RegQueryValueEx(hk, TEXT("EventMessageFile"), NULL, &Type, NULL, &szData))
		{
			buf = zbx_malloc(buf, szData);
			if (ERROR_SUCCESS == RegQueryValueEx(hk, TEXT("EventMessageFile"), NULL, &Type, (LPBYTE)buf, &szData))
				pFile = (LPTSTR)buf;
		}

		RegCloseKey(hk);
	}

	err = FAIL;

	while (NULL != pFile && FAIL == err)
	{
		if (NULL != (pNextFile = zbx_strchr(pFile, ';')))
		{
			*pNextFile = '\0';
			pNextFile++;
		}

		if (0 != ExpandEnvironmentStrings(pFile, MsgDll, MAX_PATH))
		{
			if (NULL != (hLib = LoadLibraryEx(MsgDll, NULL, LOAD_LIBRARY_AS_DATAFILE)))
			{
				/* Format the message from the message DLL with the insert strings */
				if (0 != FormatMessage(FORMAT_MESSAGE_FROM_HMODULE | FORMAT_MESSAGE_ALLOCATE_BUFFER |
						FORMAT_MESSAGE_ARGUMENT_ARRAY | FORMAT_MESSAGE_FROM_SYSTEM |
						FORMAT_MESSAGE_MAX_WIDTH_MASK,	/* do not generate new line breaks */
						hLib,				/* the messagetable DLL handle */
						pELR->EventID,			/* message ID */
						MAKELANGID(LANG_NEUTRAL, SUBLANG_ENGLISH_US),	/* language ID */
						(LPTSTR)&msgBuf,		/* address of pointer to buffer for message */
						0,
						(va_list *)aInsertStrs))	/* array of insert strings for the message */
				{
					*out_message = zbx_unicode_to_utf8(msgBuf);
					zbx_rtrim(*out_message, "\r\n ");

					/* Free the buffer that FormatMessage allocated for us. */
					LocalFree((HLOCAL)msgBuf);

					err = SUCCEED;
				}
				FreeLibrary(hLib);
			}
		}
		pFile = pNextFile;
	}

	zbx_free(buf);

	if (SUCCEED != err)
	{
		*out_message = zbx_strdcatf(*out_message, "The description for Event ID (%lu) in Source (%s) cannot be found."
				" The local computer may not have the necessary registry information or message DLL files to"
				" display messages from a remote computer.", *out_eventid, NULL == *out_source ? "" : *out_source);
		if (pELR->NumStrings)
			*out_message = zbx_strdcatf(*out_message, " The following information is part of the event: ");
		for (i = 0; i < pELR->NumStrings && i < MAX_INSERT_STRS; i++)
		{
			if (i > 0)
				*out_message = zbx_strdcatf(*out_message, "; ");
			if (aInsertStrs[i])
			{
				buf = zbx_unicode_to_utf8(aInsertStrs[i]);
				*out_message = zbx_strdcatf(*out_message, "%s", buf);
				zbx_free(buf);
			}
		}
	}

	ret = SUCCEED;
out:
	zbx_free(pELR);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

int	process_eventlog(const char *source, zbx_uint64_t *lastlogsize, unsigned long *out_timestamp, char **out_source,
		unsigned short *out_severity, char **out_message, unsigned long	*out_eventid, unsigned char skip_old_data)
{
	const char	*__function_name = "process_eventlog";
	int		ret = FAIL;
	static HMODULE	hmod_wevtapi = NULL;
	HANDLE		eventlog_handle;
	LPTSTR		wsource;
	zbx_uint64_t	FirstID, LastID;
	register long	i;
	OSVERSIONINFO	versionInfo;

	assert(NULL != lastlogsize);
	assert(NULL != out_timestamp);
	assert(NULL != out_source);
	assert(NULL != out_severity);
	assert(NULL != out_message);
	assert(NULL != out_eventid);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source:'%s' lastlogsize:" ZBX_FS_UI64,
			__function_name, source, *lastlogsize);

	*out_timestamp = 0;
	*out_source = NULL;
	*out_severity = 0;
	*out_message = NULL;
	*out_eventid = 0;

	if (NULL == source || '\0' == *source)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open eventlog with empty name");
		return ret;
	}

	wsource = zbx_utf8_to_unicode(source);

	versionInfo.dwOSVersionInfoSize = sizeof(OSVERSIONINFO);
	GetVersionEx(&versionInfo);

	if (versionInfo.dwMajorVersion >= 6)    /* Windows Vista, Windows 7 or Windows Server 2008 */
	{
		if (NULL == hmod_wevtapi)
		{
			hmod_wevtapi = LoadLibrary(L"wevtapi.dll");
			if (NULL == hmod_wevtapi)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Can't load wevtapi.dll");
				goto out;
			}
			zabbix_log(LOG_LEVEL_DEBUG, "wevtapi.dll was loaded");

			(FARPROC)EvtQueryFunc = GetProcAddress(hmod_wevtapi, "EvtQuery");
			(FARPROC)EvtCreateRenderContextFunc = GetProcAddress(hmod_wevtapi, "EvtCreateRenderContext");
			(FARPROC)EvtNextFunc = GetProcAddress(hmod_wevtapi, "EvtNext");
			(FARPROC)EvtRenderFunc = GetProcAddress(hmod_wevtapi, "EvtRender");
			(FARPROC)EvtCloseFunc = GetProcAddress(hmod_wevtapi, "EvtClose");
			(FARPROC)EvtOpenLogFunc = GetProcAddress(hmod_wevtapi, "EvtOpenLog");
			(FARPROC)EvtGetLogInfoFunc = GetProcAddress(hmod_wevtapi, "EvtGetLogInfo");
			(FARPROC)EvtOpenPublisherMetadataFunc = GetProcAddress(hmod_wevtapi, "EvtOpenPublisherMetadata");
			(FARPROC)EvtFormatMessageFunc = GetProcAddress(hmod_wevtapi, "EvtFormatMessage");

			if (NULL == EvtQueryFunc ||
				NULL == EvtCreateRenderContextFunc ||
				NULL == EvtNextFunc ||
				NULL == EvtRenderFunc ||
				NULL == EvtCloseFunc ||
				NULL == EvtOpenLogFunc ||
				NULL == EvtOpenPublisherMetadataFunc ||
				NULL == EvtFormatMessageFunc ||
				NULL == EvtGetLogInfoFunc)
			{
				zabbix_log(LOG_LEVEL_WARNING, "can't load wevtapi.dll functions");
				FreeLibrary(hmod_wevtapi);
				hmod_wevtapi = NULL;

				goto out;
			}
			zabbix_log(LOG_LEVEL_DEBUG, "wevtapi.dll functions were loaded");
		}

		if (SUCCEED != GetRecordInfo(wsource, lastlogsize, &FirstID, &LastID))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "error: can't open eventlog '%s'", source);
			goto out;
		}

		LastID = LastID + FirstID;

		if (1 == skip_old_data)
		{
			*lastlogsize = LastID - 1;
			zabbix_log(LOG_LEVEL_DEBUG, "skipping existing data: lastlogsize:" ZBX_FS_UI64, *lastlogsize);
		}

		if (*lastlogsize > LastID)
			*lastlogsize = FirstID;
		else if (*lastlogsize >= FirstID)
			FirstID = (*lastlogsize) + 1;

		for (i = (long)FirstID; i <= LastID; i++)
			{
				if (SUCCEED == zbx_get_eventlog_message_xpath(wsource, i, out_source, out_message,
						out_severity, out_timestamp, out_eventid))
					{
						*lastlogsize = i;
						break;
					}
			}
		ret = SUCCEED;
	}
	else if (versionInfo.dwMajorVersion < 6 && SUCCEED == zbx_open_eventlog(wsource, &eventlog_handle, &LastID /* number */, &FirstID /* oldest */))
	{
		LastID += FirstID;

		if (1 == skip_old_data)
		{
			*lastlogsize = LastID - 1;
			zabbix_log(LOG_LEVEL_DEBUG, "skipping existing data: lastlogsize:" ZBX_FS_UI64, *lastlogsize);
		}

		if (*lastlogsize > LastID)
			*lastlogsize = FirstID;
		else if (*lastlogsize >= FirstID)
			FirstID = (*lastlogsize) + 1;

		for (i = (long)FirstID; i < LastID; i++)
		{
			if (SUCCEED == zbx_get_eventlog_message(wsource, eventlog_handle, i, out_source, out_message,
					out_severity, out_timestamp, out_eventid))
			{
				*lastlogsize = i;
				break;
			}
		}
		zbx_close_eventlog(eventlog_handle);

		ret = SUCCEED;
	}
	else
		zabbix_log(LOG_LEVEL_ERR, "cannot open eventlog '%s': %s", source, strerror_from_system(GetLastError()));

out:
	zbx_free(wsource);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
