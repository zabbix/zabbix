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

#include "common.h"
#include "log.h"
#include "eventlog.h"

#define	DEFAULT_EVENT_CONTENT_SIZE 256

static	LPCWSTR RENDER_ITEMS[] = {
	L"/Event/System/Provider/@Name",
	L"/Event/System/Provider/@EventSourceName",
	L"/Event/System/EventRecordID",
	L"/Event/System/EventID",
	L"/Event/System/Level",
	L"/Event/System/Keywords",
	L"/Event/System/TimeCreated/@SystemTime",
	L"/Event/EventData/Data"
};

#define	RENDER_ITEMS_COUNT (sizeof(RENDER_ITEMS) / sizeof(LPCWSTR))

#define	VAR_PROVIDER_NAME(p)			(p[0].StringVal)
#define	VAR_SOURCE_NAME(p)			(p[1].StringVal)
#define	VAR_RECORD_NUMBER(p)			(p[2].UInt64Val)
#define	VAR_EVENT_ID(p)				(p[3].UInt16Val)
#define	VAR_LEVEL(p)				(p[4].ByteVal)
#define	VAR_KEYWORDS(p)				(p[5].UInt64Val)
#define	VAR_TIME_CREATED(p)			(p[6].FileTimeVal)
#define	VAR_EVENT_DATA_STRING(p)		(p[7].StringVal)
#define	VAR_EVENT_DATA_STRING_ARRAY(p, i)	(p[7].StringArr[i])
#define	VAR_EVENT_DATA_TYPE(p)			(p[7].Type)
#define	VAR_EVENT_DATA_COUNT(p)			(p[7].Count)

#define	EVENTLOG_REG_PATH TEXT("SYSTEM\\CurrentControlSet\\Services\\EventLog\\")

/* open event logger and return number of records */
static int	zbx_open_eventlog(LPCTSTR wsource, HANDLE *eventlog_handle, zbx_uint64_t *FirstID,
		zbx_uint64_t *LastID)
{
	const char	*__function_name = "zbx_open_eventlog";
	TCHAR		reg_path[MAX_PATH];
	HKEY		hk = NULL;
	DWORD		dwNumRecords, dwOldestRecord;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	*eventlog_handle = NULL;

	/* Get path to eventlog */
	zbx_wsnprintf(reg_path, MAX_PATH, EVENTLOG_REG_PATH TEXT("%s"), wsource);

	if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, reg_path, 0, KEY_READ, &hk))
		goto out;

	RegCloseKey(hk);

	if (NULL == (*eventlog_handle = OpenEventLog(NULL, wsource)))	/* open log file */
		goto out;

	if (0 == GetNumberOfEventLogRecords(*eventlog_handle, &dwNumRecords) ||
			0 == GetOldestEventLogRecord(*eventlog_handle, &dwOldestRecord))
	{
		CloseEventLog(*eventlog_handle);
		*eventlog_handle = NULL;
		goto out;
	}

	*FirstID = dwOldestRecord;
	*LastID = dwOldestRecord + dwNumRecords - 1;

	zabbix_log(LOG_LEVEL_DEBUG, "FirstID:" ZBX_FS_UI64 " LastID:" ZBX_FS_UI64 " numIDs:%lu",
			*FirstID, *LastID, dwNumRecords);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/* close event logger */
static void	zbx_close_eventlog(HANDLE eventlog_handle)
{
	if (NULL != eventlog_handle)
		CloseEventLog(eventlog_handle);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_message_files                                            *
 *                                                                            *
 * Purpose: gets event message and parameter translation files from registry  *
 *                                                                            *
 * Parameters: szLogName         - [IN] the log name                          *
 *             szSourceName      - [IN] the log source name                   *
 *             pEventMessageFile - [OUT] the event message file               *
 *             pParamMessageFile - [OUT] the parameter message file           *
 *                                                                            *
 ******************************************************************************/
static void	zbx_get_message_files(LPCTSTR szLogName, LPCTSTR szSourceName, LPTSTR *pEventMessageFile,
		LPTSTR *pParamMessageFile)
{
	TCHAR	buf[MAX_PATH];
	HKEY	hKey = NULL;
	DWORD	szData;

	/* Get path to message dll */
	zbx_wsnprintf(buf, MAX_PATH, EVENTLOG_REG_PATH TEXT("%s\\%s"), szLogName, szSourceName);

	if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, buf, 0, KEY_READ, &hKey))
		return;

	if (ERROR_SUCCESS == RegQueryValueEx(hKey, TEXT("EventMessageFile"), NULL, NULL, NULL, &szData))
	{
		*pEventMessageFile = zbx_malloc(*pEventMessageFile, szData);
		if (ERROR_SUCCESS != RegQueryValueEx(hKey, TEXT("EventMessageFile"), NULL, NULL,
				(LPBYTE)*pEventMessageFile, &szData))
		{
			zbx_free(*pEventMessageFile);
		}
	}

	if (ERROR_SUCCESS == RegQueryValueEx(hKey, TEXT("ParameterMessageFile"), NULL, NULL, NULL, &szData))
	{
		*pParamMessageFile = zbx_malloc(*pParamMessageFile, szData);
		if (ERROR_SUCCESS != RegQueryValueEx(hKey, TEXT("ParameterMessageFile"), NULL, NULL,
				(LPBYTE)*pParamMessageFile, &szData))
		{
			zbx_free(*pParamMessageFile);
		}
	}

	RegCloseKey(hKey);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_load_message_file                                            *
 *                                                                            *
 * Purpose: load the specified message file, expanding environment variables  *
 *          in the file name if necessary                                     *
 *                                                                            *
 * Parameters: szFileName - [IN] the message file name                        *
 *                                                                            *
 * Return value: Handle to the loaded library or NULL otherwise               *
 *                                                                            *
 ******************************************************************************/
static HINSTANCE	zbx_load_message_file(LPCTSTR szFileName)
{
	wchar_t		*dll_name = NULL;
	long int	sz, len = 0;
	HINSTANCE	res = NULL;

	if (NULL == szFileName)
		return NULL;

	do
	{
		if (0 != (sz = len))
			dll_name = zbx_realloc(dll_name, sz * sizeof(wchar_t));

		len = ExpandEnvironmentStrings(szFileName, dll_name, sz);
	}
	while (0 != len && sz < len);

	if (0 != len)
		res = LoadLibraryEx(dll_name, NULL, LOAD_LIBRARY_AS_DATAFILE);

	zbx_free(dll_name);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_format_message                                               *
 *                                                                            *
 * Purpose: extracts the specified message from a message file                *
 *                                                                            *
 * Parameters: hLib           - [IN] the message file handle                  *
 *             dwMessageId    - [IN] the message identifier                   *
 *             pInsertStrings - [IN] a list of insert strings, optional       *
 *                                                                            *
 * Return value: The formatted message converted to utf8 or NULL              *
 *                                                                            *
 * Comments: This function allocates memory for the returned message, which   *
 *           must be freed by the caller later.                               *
 *                                                                            *
 ******************************************************************************/
static char	*zbx_format_message(HINSTANCE hLib, DWORD dwMessageId, LPTSTR *pInsertStrings)
{
	LPTSTR	pMsgBuf = NULL;
	char	*message;

	if (0 == FormatMessage(FORMAT_MESSAGE_FROM_HMODULE | FORMAT_MESSAGE_ALLOCATE_BUFFER |
			FORMAT_MESSAGE_ARGUMENT_ARRAY | FORMAT_MESSAGE_MAX_WIDTH_MASK,
			hLib, dwMessageId, MAKELANGID(LANG_NEUTRAL, SUBLANG_ENGLISH_US), (LPTSTR)&pMsgBuf, 0,
			(va_list *)pInsertStrings))
	{
		return NULL;
	}

	message = zbx_unicode_to_utf8(pMsgBuf);
	zbx_rtrim(message, "\r\n ");

	LocalFree((HLOCAL)pMsgBuf);

	return message;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_translate_message_params                                     *
 *                                                                            *
 * Purpose: translates message by replacing parameters %%<id> with translated *
 *          values                                                            *
 *                                                                            *
 * Parameters: message - [IN/OUT] the message to translate                    *
 *             hLib    - [IN] the parameter message file handle               *
 *                                                                            *
 ******************************************************************************/
static void	zbx_translate_message_params(char **message, HINSTANCE hLib)
{
	char	*param, *pstart, *pend;
	int	dwMessageId;
	size_t	offset = 0;

	while (1)
	{
		if (NULL == (pstart = strstr(*message + offset, "%%")))
			break;

		pend = pstart + 2;

		dwMessageId = atoi(pend);

		while ('\0' != *pend && 0 != isdigit(*pend))
			pend++;

		offset = pend - *message - 1;

		if (NULL != (param = zbx_format_message(hLib, dwMessageId, NULL)))
		{
			zbx_replace_string(message, pstart - *message, &offset, param);

			zbx_free(param);
		}
	}
}

#define MAX_INSERT_STRS 100

/* get Nth error from event log. 1 is the first. */
static int	zbx_get_eventlog_message(LPCTSTR wsource, HANDLE eventlog_handle, DWORD which, char **out_source,
		char **out_message, unsigned short *out_severity, unsigned long *out_timestamp,
		unsigned long *out_eventid)
{
	const char	*__function_name = "zbx_get_eventlog_message";
	int		buffer_size = 512;
	EVENTLOGRECORD	*pELR = NULL;
	DWORD		dwRead, dwNeeded, dwErr;
	LPTSTR		pEventMessageFile = NULL, pParamMessageFile = NULL, pFile = NULL, pNextFile = NULL, pCh,
			aInsertStrings[MAX_INSERT_STRS];
	HINSTANCE	hLib = NULL, hParamLib = NULL;
	long		i, err = 0;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastlogsize:%lu", __function_name, which);

	*out_source = NULL;
	*out_message = NULL;
	*out_severity = 0;
	*out_timestamp = 0;
	*out_eventid = 0;
	memset(aInsertStrings, 0, sizeof(aInsertStrings));

	pELR = (EVENTLOGRECORD *)zbx_malloc((void *)pELR, buffer_size);

	while (0 == ReadEventLog(eventlog_handle, EVENTLOG_SEEK_READ | EVENTLOG_FORWARDS_READ, which,
			pELR, buffer_size, &dwRead, &dwNeeded))
	{
		if (ERROR_INSUFFICIENT_BUFFER != (dwErr = GetLastError()))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): %s", __function_name, strerror_from_system(dwErr));
			goto out;
		}

		buffer_size = dwNeeded;
		pELR = (EVENTLOGRECORD *)zbx_realloc((void *)pELR, buffer_size);
	}

	*out_severity = pELR->EventType;			/* return event type */
	*out_timestamp = pELR->TimeGenerated;			/* return timestamp */
	*out_eventid = pELR->EventID & 0xffff;
	*out_source = zbx_unicode_to_utf8((LPTSTR)(pELR + 1));	/* copy source name */

	/* get message file names */
	zbx_get_message_files(wsource, (LPTSTR)(pELR + 1), &pEventMessageFile, &pParamMessageFile);

	/* prepare insert string array */
	if (0 < pELR->NumStrings)
	{
		pCh = (LPWSTR)((LPBYTE)pELR + pELR->StringOffset);

		for (i = 0; i < pELR->NumStrings && i < MAX_INSERT_STRS; i++)
		{
			aInsertStrings[i] = pCh;
			pCh += zbx_strlen(pCh) + 1;
		}
	}

	err = FAIL;

	for (pFile = pEventMessageFile; NULL != pFile && err != SUCCEED; pFile = pNextFile)
	{
		if (NULL != (pNextFile = zbx_strchr(pFile, ';')))
		{
			*pNextFile = '\0';
			pNextFile++;
		}

		if (NULL != (hLib = zbx_load_message_file(pFile)))
		{
			if (NULL != (*out_message = zbx_format_message(hLib, pELR->EventID, aInsertStrings)))
			{
				err = SUCCEED;

				if (NULL != (hParamLib = zbx_load_message_file(pParamMessageFile)))
				{
					zbx_translate_message_params(out_message, hParamLib);
					FreeLibrary(hParamLib);
				}
			}

			FreeLibrary(hLib);
		}
	}

	zbx_free(pEventMessageFile);
	zbx_free(pParamMessageFile);

	if (SUCCEED != err)
	{
		*out_message = zbx_strdcatf(*out_message, "The description for Event ID:%lu in Source:'%s'"
				" cannot be found. The local computer may not have the necessary registry"
				" information or message DLL files to display messages from a remote computer.",
				*out_eventid, NULL == *out_source ? "" : *out_source);

		if (0 < pELR->NumStrings)
		{
			char	*buf;

			*out_message = zbx_strdcat(*out_message, " The following information is part of the event: ");

			for (i = 0, pCh = (LPWSTR)((LPBYTE)pELR + pELR->StringOffset);
					i < pELR->NumStrings;
					i++, pCh += zbx_strlen(pCh) + 1)
			{
				if (0 < i)
					*out_message = zbx_strdcat(*out_message, "; ");

				buf = zbx_unicode_to_utf8(pCh);
				*out_message = zbx_strdcat(*out_message, buf);
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

int	process_eventlog(const char *source, zbx_uint64_t *lastlogsize, unsigned long *out_timestamp,
		char **out_source, unsigned short *out_severity, char **out_message,
		unsigned long *out_eventid, unsigned char skip_old_data)
{
	const char	*__function_name = "process_eventlog";
	int		ret = FAIL;
	HANDLE		eventlog_handle;
	LPTSTR		wsource;
	zbx_uint64_t	i, FirstID, LastID;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source:'%s' lastlogsize:" ZBX_FS_UI64, __function_name, source,
			*lastlogsize);

	/* From MSDN documentation:                                                                         */
	/* The RecordNumber member of EVENTLOGRECORD contains the record number for the event log record.   */
	/* The very first record written to an event log is record number 1, and other records are          */
	/* numbered sequentially. If the record number reaches ULONG_MAX, the next record number will be 0, */
	/* not 1; however, you use zero to seek to the record.                                              */
	/*                                                                                                  */
	/* This RecordNumber wraparound is handled simply by using 64bit integer to calculate record        */
	/* numbers and then converting to DWORD values.                                                     */

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

	if (SUCCEED != zbx_open_eventlog(wsource, &eventlog_handle, &FirstID, &LastID))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot open eventlog '%s': %s", source,
				strerror_from_system(GetLastError()));
		goto out;
	}

	if (1 == skip_old_data)
	{
		*lastlogsize = LastID;
		zabbix_log(LOG_LEVEL_DEBUG, "skipping existing data: lastlogsize:" ZBX_FS_UI64, *lastlogsize);
		goto finish;
	}

	/* Having lastlogsize greater than LastID means that there was oldest event record */
	/* (FirstID) wraparound. In this case we must also wrap the lastlogsize value.     */
	if (*lastlogsize > LastID)
		*lastlogsize = (DWORD)*lastlogsize;

	/* if the lastlogsize is still outside log record interval reset it to the oldest record number, */
	/* otherwise set FirstID to the next record after lastlogsize, which is the first event record   */
	/* to read                                                                                       */
	if (*lastlogsize > LastID || *lastlogsize < FirstID)
		*lastlogsize = FirstID;
	else
		FirstID = *lastlogsize + 1;

	for (i = FirstID; i <= LastID; i++)
	{
		/* convert to DWORD to handle possible event record number wraparound */
		DWORD	dwRecordNumber = (DWORD)i;

		if (SUCCEED == zbx_get_eventlog_message(wsource, eventlog_handle, dwRecordNumber, out_source,
				out_message, out_severity, out_timestamp, out_eventid))
		{
			/* storing full (not truncated to DWORD) lastlogsize value makes  */
			/* easier to do event record number calculations during next call */
			*lastlogsize = i;
			break;
		}
	}
finish:
	zbx_close_eventlog(eventlog_handle);
	ret = SUCCEED;
out:
	zbx_free(wsource);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/* open eventlog using API 6 and return the number of records */
static int	zbx_open_eventlog6(LPCWSTR wsource, zbx_uint64_t *lastlogsize, EVT_HANDLE *render_context,
		zbx_uint64_t *FirstID, zbx_uint64_t *LastID)
{
	const char	*__function_name = "zbx_open_eventlog6";
	EVT_HANDLE	log = NULL;
	EVT_VARIANT	var;
	EVT_HANDLE	tmp_all_event_query = NULL;
	EVT_HANDLE	event_bookmark = NULL;
	EVT_VARIANT*	renderedContent = NULL;
	DWORD		status = 0;
	DWORD		size_required = 0;
	DWORD		size = DEFAULT_EVENT_CONTENT_SIZE;
	DWORD		bookmarkedCount = 0;
	zbx_uint64_t	numIDs = 0;
	LPSTR		tmp_str = NULL;
	int		ret = FAIL;

	*FirstID = 0;
	*LastID = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* try to open the desired log */
	if (NULL == (log = EvtOpenLog(NULL, wsource, EvtOpenChannelPath)))
	{
		tmp_str = zbx_unicode_to_utf8(wsource);
		zabbix_log(LOG_LEVEL_WARNING, "cannot open eventlog '%s':%s", tmp_str,
				strerror_from_system(GetLastError()));
		goto out;
	}

	/* obtain the number of records in the log */
	if (TRUE != EvtGetLogInfo(log, EvtLogNumberOfLogRecords, sizeof(var), &var, &size_required))
	{
		zabbix_log(LOG_LEVEL_WARNING, "EvtGetLogInfo failed:%s",
				strerror_from_system(GetLastError()));
		goto out;
	}

	numIDs = var.UInt64Val;

	/* get the number of the oldest record in the log				*/
	/* "EvtGetLogInfo()" does not work properly with "EvtLogOldestRecordNumber"	*/
	/* we have to get it from the first EventRecordID				*/

	/* create the system render */
	if (NULL == (*render_context = EvtCreateRenderContext(RENDER_ITEMS_COUNT, RENDER_ITEMS, EvtRenderContextValues)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "EvtCreateRenderContext failed:%s", strerror_from_system(GetLastError()));
		goto out;
	}

	/* get all eventlog */
	tmp_all_event_query = EvtQuery(NULL, wsource, NULL, EvtQueryChannelPath);
	if (NULL == tmp_all_event_query)
	{
		if (ERROR_EVT_CHANNEL_NOT_FOUND == (status = GetLastError()))
			zabbix_log(LOG_LEVEL_WARNING, "EvtQuery channel missed:%s", strerror_from_system(status));
		else
			zabbix_log(LOG_LEVEL_WARNING, "EvtQuery failed:%s", strerror_from_system(status));

		goto out;
	}

	/* get the entries and allocate the required space */
	renderedContent = zbx_malloc(renderedContent, size);
	if (TRUE != EvtNext(tmp_all_event_query, 1, &event_bookmark, INFINITE, 0, &size_required))
	{
		/* no data in eventlog */
		zabbix_log(LOG_LEVEL_DEBUG, "first EvtNext failed:%s", strerror_from_system(GetLastError()));
		*FirstID = 1;
		*LastID = 1;
		numIDs = 0;
		*lastlogsize = 0;
		ret = SUCCEED;
		goto out;
	}

	/* obtain the information from selected events */
	if (TRUE != EvtRender(*render_context, event_bookmark, EvtRenderEventValues, size, renderedContent,
			&size_required, &bookmarkedCount))
	{
		/* information exceeds the allocated space */
		if (ERROR_INSUFFICIENT_BUFFER != GetLastError())
		{
			zabbix_log(LOG_LEVEL_WARNING, "EvtRender failed:%s", strerror_from_system(GetLastError()));
			goto out;
		}

		renderedContent = (EVT_VARIANT*)zbx_realloc((void *)renderedContent, size_required);
		size = size_required;

		if (TRUE != EvtRender(*render_context, event_bookmark, EvtRenderEventValues, size, renderedContent,
				&size_required, &bookmarkedCount))
		{
			zabbix_log(LOG_LEVEL_WARNING, "EvtRender failed:%s", strerror_from_system(GetLastError()));
			goto out;
		}
	}

	*FirstID = VAR_RECORD_NUMBER(renderedContent);
	*LastID = *FirstID + numIDs;

	if (*lastlogsize >= *LastID)
	{
		*lastlogsize = *FirstID - 1;
		zabbix_log(LOG_LEVEL_DEBUG, "lastlogsize is too big. It is set to:" ZBX_FS_UI64, *lastlogsize);
	}

	ret = SUCCEED;
out:
	if (NULL != log)
		EvtClose(log);
	if (NULL != tmp_all_event_query)
		EvtClose(tmp_all_event_query);
	if (NULL != event_bookmark)
		EvtClose(event_bookmark);
	zbx_free(tmp_str);
	zbx_free(renderedContent);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s FirstID:" ZBX_FS_UI64 " LastID:" ZBX_FS_UI64 " numIDs:" ZBX_FS_UI64,
			__function_name, zbx_result_string(ret), *FirstID, *LastID, numIDs);

	return ret;
}

/* get handles of eventlog */
static int	zbx_get_handle_eventlog6(LPCWSTR wsource, zbx_uint64_t *lastlogsize, EVT_HANDLE *query)
{
	const char	*__function_name = "zbx_get_handle_eventlog6";
	LPWSTR		event_query = NULL;
	DWORD		status = 0;
	LPSTR		tmp_str = NULL;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(), previous lastlogsize:" ZBX_FS_UI64, __function_name, *lastlogsize);

	/* start building the query */
	tmp_str = zbx_dsprintf(NULL, "Event/System[EventRecordID>" ZBX_FS_UI64 "]", *lastlogsize);
	event_query = zbx_utf8_to_unicode(tmp_str);

	/* create massive query for an event on a local computer*/
	*query = EvtQuery(NULL, wsource, event_query, EvtQueryChannelPath);
	if (NULL == *query)
	{
		if (ERROR_EVT_CHANNEL_NOT_FOUND == (status = GetLastError()))
			zabbix_log(LOG_LEVEL_WARNING, "EvtQuery channel missed:%s", strerror_from_system(status));
		else
			zabbix_log(LOG_LEVEL_WARNING, "EvtQuery failed:%s", strerror_from_system(status));
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(tmp_str);
	zbx_free(event_query);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/* initialize event logs with Windows API version 6 */
int	initialize_eventlog6(const char *source, zbx_uint64_t *lastlogsize, zbx_uint64_t *FirstID,
		zbx_uint64_t *LastID, EVT_HANDLE *render_context, EVT_HANDLE *query)
{
	const char	*__function_name = "initialize_eventlog6";
	LPWSTR		wsource = NULL;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source:'%s' previous lastlogsize:" ZBX_FS_UI64,
			__function_name, source, *lastlogsize);

	if (NULL == source || '\0' == *source)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open eventlog with empty name.");
		goto out;
	}

	wsource = zbx_utf8_to_unicode(source);

	if (SUCCEED != zbx_open_eventlog6(wsource, lastlogsize, render_context, FirstID, LastID))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot open eventlog '%s'", source);
		goto out;
	}

	if (SUCCEED != zbx_get_handle_eventlog6(wsource, lastlogsize, query))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot get eventlog handle '%s'", source);
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(wsource);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/* expand the string message from a specific event handler */
static LPSTR	expand_message6(LPCWSTR pname, EVT_HANDLE event)
{
	const char	*__function_name = "expand_message6";
	LPWSTR		pmessage = NULL;
	EVT_HANDLE	provider = NULL;
	DWORD		require = 0;
	LPSTR		out_message = NULL;
	LPSTR		tmp_pname = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == (provider = EvtOpenPublisherMetadata(NULL, pname, NULL, 0, 0)))
	{
		tmp_pname = zbx_unicode_to_utf8(pname);
		zabbix_log(LOG_LEVEL_DEBUG, "provider '%s' could not be opened: %s",
				strerror_from_system(GetLastError()), tmp_pname);
		zbx_free(tmp_pname);
		goto out;
	}

	if (TRUE != EvtFormatMessage(provider, event, 0, 0, NULL, EvtFormatMessageEvent, 0, NULL, &require))
	{
		if (ERROR_INSUFFICIENT_BUFFER == GetLastError())
		{
			DWORD	error = ERROR_SUCCESS;

			pmessage = zbx_malloc(pmessage, sizeof(WCHAR) * require);

			if (TRUE != EvtFormatMessage(provider, event, 0, 0, NULL, EvtFormatMessageEvent, require,
					pmessage, &require))
			{
				error = GetLastError();
			}

			if (ERROR_SUCCESS == error || ERROR_EVT_UNRESOLVED_VALUE_INSERT == error ||
					ERROR_EVT_UNRESOLVED_PARAMETER_INSERT == error ||
					ERROR_EVT_MAX_INSERTS_REACHED == error)
			{
				out_message = zbx_unicode_to_utf8(pmessage);
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot format message: %s", __function_name,
						strerror_from_system(error));
				goto out;
			}
		}
	}
out:
	if (NULL != provider)
		EvtClose(provider);
	zbx_free(pmessage);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, out_message);

	/* should be freed*/
	return out_message;
}

/* obtain a particular message from a desired eventlog */
static int	zbx_get_eventlog_message6(LPCWSTR wsource, zbx_uint64_t *which, unsigned short *out_severity,
		unsigned long *out_timestamp, char **out_provider, char **out_source, char **out_message,
		unsigned long *out_eventid, EVT_HANDLE *render_context, EVT_HANDLE *query, zbx_uint64_t *keywords)
{
	const char		*__function_name = "zbx_get_eventlog_message6";
	EVT_HANDLE		event_bookmark = NULL;
	EVT_VARIANT*		renderedContent = NULL;
	LPCWSTR			pprovider = NULL;
	LPSTR			tmp_str = NULL;
	DWORD			size = DEFAULT_EVENT_CONTENT_SIZE;
	DWORD			bookmarkedCount = 0;
	DWORD			require = 0;
	const zbx_uint64_t	sec_1970 = 116444736000000000;
	const zbx_uint64_t	success_audit = 0x20000000000000;
	const zbx_uint64_t	failure_audit = 0x10000000000000;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() EventRecordID:" ZBX_FS_UI64, __function_name, *which);

	if (NULL == *query)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() no EvtQuery handle", __function_name);
		goto out;
	}

	/* get the entries */
	if (TRUE != EvtNext(*query, 1, &event_bookmark, INFINITE, 0, &require))
	{
		/* The event reading query had less items than we calculated before. */
		/* Either the eventlog was cleaned or our calculations were wrong.   */
		/* Either way we can safely abort the query by setting NULL value    */
		/* and returning success, which is interpreted as empty eventlog.    */
		if (ERROR_NO_MORE_ITEMS == GetLastError())
		{
			ret = SUCCEED;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "EvtNext failed: %s, EventRecordID:" ZBX_FS_UI64,
				strerror_from_system(GetLastError()), *which);
		}
		goto out;
	}

	/* obtain the information from the selected events */

	renderedContent = (EVT_VARIANT *)zbx_malloc((void *)renderedContent, size);

	if (TRUE != EvtRender(*render_context, event_bookmark, EvtRenderEventValues, size, renderedContent,
			&require, &bookmarkedCount))
	{
		/* information exceeds the space allocated */
		if (ERROR_INSUFFICIENT_BUFFER != GetLastError())
		{
			zabbix_log(LOG_LEVEL_WARNING, "EvtRender failed: %s", strerror_from_system(GetLastError()));
			goto out;
		}

		renderedContent = (EVT_VARIANT *)zbx_realloc((void *)renderedContent, require);
		size = require;

		if (TRUE != EvtRender(*render_context, event_bookmark, EvtRenderEventValues, size, renderedContent,
				&require, &bookmarkedCount))
		{
			zabbix_log(LOG_LEVEL_WARNING, "EvtRender failed: %s", strerror_from_system(GetLastError()));
			goto out;
		}
	}

	pprovider = VAR_PROVIDER_NAME(renderedContent);
	*out_provider = zbx_unicode_to_utf8(pprovider);

	if (NULL != VAR_SOURCE_NAME(renderedContent))
	{
		*out_source = zbx_unicode_to_utf8(VAR_SOURCE_NAME(renderedContent));
	}

	*keywords = VAR_KEYWORDS(renderedContent) & (success_audit | failure_audit);
	*out_severity = VAR_LEVEL(renderedContent);
	*out_timestamp = (unsigned long)((VAR_TIME_CREATED(renderedContent) - sec_1970) / 10000000);
	*out_eventid = VAR_EVENT_ID(renderedContent);
	*out_message = expand_message6(pprovider, event_bookmark);

	tmp_str = zbx_unicode_to_utf8(wsource);

	if (VAR_RECORD_NUMBER(renderedContent) != *which)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() Overwriting expected EventRecordID:" ZBX_FS_UI64 " with the real"
				" EventRecordID:" ZBX_FS_UI64 " in eventlog '%s'", __function_name, *which,
				VAR_RECORD_NUMBER(renderedContent), tmp_str);
		*which = VAR_RECORD_NUMBER(renderedContent);
	}

	/* some events dont have enough information for making event message */
	if (NULL == *out_message)
	{
		*out_message = zbx_strdcatf(*out_message, "The description for Event ID:%lu in Source:'%s'"
				" cannot be found. Either the component that raises this event is not installed"
				" on your local computer or the installation is corrupted. You can install or repair"
				" the component on the local computer. If the event originated on another computer,"
				" the display information had to be saved with the event.", *out_eventid,
				NULL == *out_provider ? "" : *out_provider);

		if (EvtVarTypeString == (VAR_EVENT_DATA_TYPE(renderedContent) & EVT_VARIANT_TYPE_MASK))
		{
			unsigned int	i;
			char		*data = NULL;

			if (0 != (VAR_EVENT_DATA_TYPE(renderedContent) & EVT_VARIANT_TYPE_ARRAY) &&
				0 < VAR_EVENT_DATA_COUNT(renderedContent))
			{
				*out_message = zbx_strdcatf(*out_message, " The following information was included"
						" with the event: ");

				for (i = 0; i < VAR_EVENT_DATA_COUNT(renderedContent); i++)
				{
					if (NULL != VAR_EVENT_DATA_STRING_ARRAY(renderedContent, i))
					{
						if (0 < i)
							*out_message = zbx_strdcat(*out_message, "; ");

						data = zbx_unicode_to_utf8(VAR_EVENT_DATA_STRING_ARRAY(renderedContent, i));
						*out_message = zbx_strdcatf(*out_message, "%s", data);
						zbx_free(data);
					}
				}
			}
			else if (NULL != VAR_EVENT_DATA_STRING(renderedContent))
			{
				data = zbx_unicode_to_utf8(VAR_EVENT_DATA_STRING(renderedContent));
				*out_message = zbx_strdcatf(*out_message, "The following information was included"
						" with the event: %s", data);
				zbx_free(data);
			}
		}
	}

	ret = SUCCEED;
out:
	if (NULL != event_bookmark)
		EvtClose(event_bookmark);
	zbx_free(tmp_str);
	zbx_free(renderedContent);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/* process eventlog with Windows API version 6 */
int	process_eventlog6(const char *source, zbx_uint64_t *lastlogsize, unsigned long *out_timestamp,
		char **out_provider, char **out_source, unsigned short *out_severity, char **out_message,
		unsigned long *out_eventid, zbx_uint64_t *FirstID, zbx_uint64_t *LastID, EVT_HANDLE *render_context,
		EVT_HANDLE *query, zbx_uint64_t *keywords, unsigned char skip_old_data)
{
	const char	*__function_name = "process_eventlog6";
	zbx_uint64_t	i = 0;
	zbx_uint64_t	reading_startpoint = 0;
	LPWSTR		wsource = NULL;
	int		ret = FAIL;

	*out_timestamp	= 0;
	*out_provider	= NULL;
	*out_source	= NULL;
	*out_severity	= 0;
	*out_message	= NULL;
	*out_eventid	= 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source: '%s' previous lastlogsize: " ZBX_FS_UI64 ", FirstID: "
			ZBX_FS_UI64 ", LastID: " ZBX_FS_UI64, __function_name, source, *lastlogsize, *FirstID,
			*LastID);

	wsource = zbx_utf8_to_unicode(source);

	/* update counters */
	if (1 == skip_old_data)
	{
		(*lastlogsize) = *LastID - 1;
		zabbix_log(LOG_LEVEL_DEBUG, "skipping existing data: lastlogsize:" ZBX_FS_UI64,
				*lastlogsize);
		ret = SUCCEED;
		goto out;
	}
	else if (*lastlogsize >= *FirstID && *lastlogsize < *LastID)
		reading_startpoint = (*lastlogsize) + 1;
	else
		reading_startpoint = *FirstID;

	if (reading_startpoint == *LastID)
	{
		ret = SUCCEED;
		goto out;
	}

	/* cycle through the new records */
	for (i = reading_startpoint; i < *LastID; i++)
	{
		if (SUCCEED == zbx_get_eventlog_message6(wsource, &i, out_severity, out_timestamp, out_provider,
				out_source, out_message, out_eventid, render_context, query, keywords))
		{
			/* update lastlogsize only if we really did read eventlog message */
			if (NULL != *out_message)
				*lastlogsize = i;

			ret = SUCCEED;
			goto out;
		}
	}
out:
	zbx_free(wsource);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/* finalize eventlog6 and free the handles */
int	finalize_eventlog6(EVT_HANDLE *render_context, EVT_HANDLE *query)
{
	const char	*__function_name = "finalize_eventlog6";
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL != *query)
	{
		EvtClose(*query);
		*query = NULL;
	}
	if (NULL != *render_context)
	{
		EvtClose(*render_context);
		*render_context = NULL;
	}
	ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}


