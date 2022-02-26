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

#include "eventlog.h"

#include "log.h"
#include "sysinfo.h"
#include "zbxregexp.h"
#include "winmeta.h"
#include <strsafe.h>
#include <delayimp.h>
#include <sddl.h>

#define MAX_NAME			256

static const wchar_t	*RENDER_ITEMS[] = {
	L"/Event/System/Provider/@Name",
	L"/Event/System/Provider/@EventSourceName",
	L"/Event/System/EventRecordID",
	L"/Event/System/EventID",
	L"/Event/System/Level",
	L"/Event/System/Keywords",
	L"/Event/System/TimeCreated/@SystemTime",
	L"/Event/EventData/Data"
};

#define	RENDER_ITEMS_COUNT (sizeof(RENDER_ITEMS) / sizeof(const wchar_t *))

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

#ifndef INFORMATION_TYPE
#	define INFORMATION_TYPE	"Information"
#endif
#ifndef WARNING_TYPE
#	define WARNING_TYPE	"Warning"
#endif
#ifndef ERROR_TYPE
#	define ERROR_TYPE	"Error"
#endif
#ifndef AUDIT_FAILURE
#	define AUDIT_FAILURE	"Failure Audit"
#endif
#ifndef AUDIT_SUCCESS
#	define AUDIT_SUCCESS	"Success Audit"
#endif
#ifndef CRITICAL_TYPE
#	define CRITICAL_TYPE	"Critical"
#endif
#ifndef VERBOSE_TYPE
#	define VERBOSE_TYPE	"Verbose"
#endif

extern int	CONFIG_EVENTLOG_MAX_LINES_PER_SECOND;

LONG WINAPI	DelayLoadDllExceptionFilter(PEXCEPTION_POINTERS excpointers)
{
	LONG		disposition = EXCEPTION_EXECUTE_HANDLER;
	PDelayLoadInfo	delayloadinfo = (PDelayLoadInfo)(excpointers->ExceptionRecord->ExceptionInformation[0]);

	switch (excpointers->ExceptionRecord->ExceptionCode)
	{
		case VcppException(ERROR_SEVERITY_ERROR, ERROR_MOD_NOT_FOUND):
			zabbix_log(LOG_LEVEL_DEBUG, "function %s was not found in %s",
					delayloadinfo->dlp.szProcName, delayloadinfo->szDll);
			break;
		case VcppException(ERROR_SEVERITY_ERROR, ERROR_PROC_NOT_FOUND):
			if (delayloadinfo->dlp.fImportByName)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "function %s was not found in %s",
						delayloadinfo->dlp.szProcName, delayloadinfo->szDll);
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "function ordinal %d was not found in %s",
						delayloadinfo->dlp.dwOrdinal, delayloadinfo->szDll);
			}
			break;
		default:
			disposition = EXCEPTION_CONTINUE_SEARCH;
			break;
	}

	return disposition;
}

/* open event logger and return number of records */
static int	zbx_open_eventlog(LPCTSTR wsource, HANDLE *eventlog_handle, zbx_uint64_t *FirstID,
		zbx_uint64_t *LastID, DWORD *error_code)
{
	wchar_t	reg_path[MAX_PATH];
	HKEY	hk = NULL;
	DWORD	dwNumRecords, dwOldestRecord;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*eventlog_handle = NULL;

	/* Get path to eventlog */
	StringCchPrintf(reg_path, MAX_PATH, EVENTLOG_REG_PATH TEXT("%s"), wsource);

	if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, reg_path, 0, KEY_READ, &hk))
	{
		*error_code = GetLastError();
		goto out;
	}

	RegCloseKey(hk);

	if (NULL == (*eventlog_handle = OpenEventLog(NULL, wsource)))	/* open log file */
	{
		*error_code = GetLastError();
		goto out;
	}

	if (0 == GetNumberOfEventLogRecords(*eventlog_handle, &dwNumRecords) ||
			0 == GetOldestEventLogRecord(*eventlog_handle, &dwOldestRecord))
	{
		*error_code = GetLastError();
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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

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
 * Purpose: gets event message and parameter translation files from registry  *
 *                                                                            *
 * Parameters: szLogName         - [IN] the log name                          *
 *             szSourceName      - [IN] the log source name                   *
 *             pEventMessageFile - [OUT] the event message file               *
 *             pParamMessageFile - [OUT] the parameter message file           *
 *                                                                            *
 ******************************************************************************/
static void	zbx_get_message_files(const wchar_t *szLogName, const wchar_t *szSourceName, wchar_t **pEventMessageFile,
		wchar_t **pParamMessageFile)
{
	wchar_t	buf[MAX_PATH];
	HKEY	hKey = NULL;
	DWORD	szData;

	/* Get path to message dll */
	StringCchPrintf(buf, MAX_PATH, EVENTLOG_REG_PATH TEXT("%s\\%s"), szLogName, szSourceName);

	if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, buf, 0, KEY_READ, &hKey))
		return;

	if (ERROR_SUCCESS == RegQueryValueEx(hKey, TEXT("EventMessageFile"), NULL, NULL, NULL, &szData))
	{
		*pEventMessageFile = zbx_malloc(*pEventMessageFile, szData);
		if (ERROR_SUCCESS != RegQueryValueEx(hKey, TEXT("EventMessageFile"), NULL, NULL,
				(unsigned char *)*pEventMessageFile, &szData))
		{
			zbx_free(*pEventMessageFile);
		}
	}

	if (ERROR_SUCCESS == RegQueryValueEx(hKey, TEXT("ParameterMessageFile"), NULL, NULL, NULL, &szData))
	{
		*pParamMessageFile = zbx_malloc(*pParamMessageFile, szData);
		if (ERROR_SUCCESS != RegQueryValueEx(hKey, TEXT("ParameterMessageFile"), NULL, NULL,
				(unsigned char *)*pParamMessageFile, &szData))
		{
			zbx_free(*pParamMessageFile);
		}
	}

	RegCloseKey(hKey);
}

/******************************************************************************
 *                                                                            *
 * Purpose: load the specified message file, expanding environment variables  *
 *          in the file name if necessary                                     *
 *                                                                            *
 * Parameters: szFileName - [IN] the message file name                        *
 *                                                                            *
 * Return value: Handle to the loaded library or NULL otherwise               *
 *                                                                            *
 ******************************************************************************/
static HINSTANCE	zbx_load_message_file(const wchar_t *szFileName)
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
static char	*zbx_format_message(HINSTANCE hLib, DWORD dwMessageId, wchar_t **pInsertStrings)
{
	wchar_t *pMsgBuf = NULL;
	char	*message;

	if (0 == FormatMessage(FORMAT_MESSAGE_FROM_HMODULE | FORMAT_MESSAGE_ALLOCATE_BUFFER |
			FORMAT_MESSAGE_ARGUMENT_ARRAY | FORMAT_MESSAGE_MAX_WIDTH_MASK,
			hLib, dwMessageId, MAKELANGID(LANG_NEUTRAL, SUBLANG_ENGLISH_US), (wchar_t *)&pMsgBuf, 0,
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

static int get_eventlog6_id(EVT_HANDLE *event_query, EVT_HANDLE *render_context, zbx_uint64_t *id, char **error)
{
	int		ret = FAIL;
	DWORD		size_required_next = 0, size_required = 0, size = 0, status = 0, bookmarkedCount = 0;
	EVT_VARIANT*	renderedContent = NULL;
	EVT_HANDLE	event_bookmark = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (TRUE != EvtNext(*event_query, 1, &event_bookmark, INFINITE, 0, &size_required_next))
	{
		/* no data in eventlog */
		zabbix_log(LOG_LEVEL_DEBUG, "%s() EvtNext failed:%s", __func__, strerror_from_system(GetLastError()));
		*id = 0;
		ret = SUCCEED;
		goto out;
	}

	/* obtain the information from selected events */
	if (TRUE != EvtRender(*render_context, event_bookmark, EvtRenderEventValues, size, renderedContent,
			&size_required, &bookmarkedCount))
	{
		/* information exceeds the allocated space */
		if (ERROR_INSUFFICIENT_BUFFER != (status = GetLastError()))
		{
			*error = zbx_dsprintf(*error, "EvtRender failed:%s", strerror_from_system(status));
			goto out;
		}

		size = size_required;
		renderedContent = (EVT_VARIANT*)zbx_malloc(NULL, size);

		if (TRUE != EvtRender(*render_context, event_bookmark, EvtRenderEventValues, size, renderedContent,
				&size_required, &bookmarkedCount))
		{
			*error = zbx_dsprintf(*error, "EvtRender failed:%s", strerror_from_system(GetLastError()));
			goto out;
		}
	}

	*id = VAR_RECORD_NUMBER(renderedContent);
	ret = SUCCEED;
out:
	if (NULL != event_bookmark)
		EvtClose(event_bookmark);

	zbx_free(renderedContent);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s id:" ZBX_FS_UI64, __func__, zbx_result_string(ret), id);

	return ret;
}

/* open eventlog using API 6 and return the number of records */
static int	zbx_open_eventlog6(const wchar_t *wsource, zbx_uint64_t *lastlogsize, EVT_HANDLE *render_context,
		zbx_uint64_t *FirstID, zbx_uint64_t *LastID, char **error)
{
	EVT_HANDLE	tmp_first_event_query = NULL;
	EVT_HANDLE	tmp_last_event_query = NULL;
	DWORD		status = 0;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastlogsize:" ZBX_FS_UI64, __func__, *lastlogsize);

	*FirstID = 0;
	*LastID = 0;

	/* get the number of the oldest record in the log				*/
	/* "EvtGetLogInfo()" does not work properly with "EvtLogOldestRecordNumber"	*/
	/* we have to get it from the first EventRecordID				*/

	/* create the system render */
	if (NULL == (*render_context = EvtCreateRenderContext(RENDER_ITEMS_COUNT, RENDER_ITEMS, EvtRenderContextValues)))
	{
		*error = zbx_dsprintf(*error, "EvtCreateRenderContext failed:%s", strerror_from_system(GetLastError()));
		goto out;
	}

	/* get all eventlog */
	if (NULL == (tmp_first_event_query = EvtQuery(NULL, wsource, NULL, EvtQueryChannelPath)))
	{
		if (ERROR_EVT_CHANNEL_NOT_FOUND == (status = GetLastError()))
			*error = zbx_dsprintf(*error, "EvtQuery channel missed:%s", strerror_from_system(status));
		else
			*error = zbx_dsprintf(*error, "EvtQuery failed:%s", strerror_from_system(status));

		goto out;
	}

	if (SUCCEED != get_eventlog6_id(&tmp_first_event_query, render_context, FirstID, error))
		goto out;

	if (0 == *FirstID)
	{
		/* no data in eventlog */
		zabbix_log(LOG_LEVEL_DEBUG, "%s() first EvtNext failed", __func__);
		*FirstID = 1;
		*LastID = 1;
		*lastlogsize = 0;
		ret = SUCCEED;
		goto out;
	}

	if (NULL == (tmp_last_event_query = EvtQuery(NULL, wsource, NULL,
			EvtQueryChannelPath | EvtQueryReverseDirection)))
	{
		if (ERROR_EVT_CHANNEL_NOT_FOUND == (status = GetLastError()))
			*error = zbx_dsprintf(*error, "EvtQuery channel missed:%s", strerror_from_system(status));
		else
			*error = zbx_dsprintf(*error, "EvtQuery failed:%s", strerror_from_system(status));

		goto out;
	}

	if (SUCCEED != get_eventlog6_id(&tmp_last_event_query, render_context, LastID, error) || 0 == *LastID)
	{
		/* no data in eventlog */
		zabbix_log(LOG_LEVEL_DEBUG, "%s() last EvtNext failed", __func__);
		*LastID = 1;
	}
	else
		*LastID += 1;	/* we should read the last record */

	if (*lastlogsize >= *LastID)
	{
		*lastlogsize = *FirstID - 1;
		zabbix_log(LOG_LEVEL_WARNING, "lastlogsize is too big. It is set to:" ZBX_FS_UI64, *lastlogsize);
	}

	ret = SUCCEED;
out:
	if (NULL != tmp_first_event_query)
		EvtClose(tmp_first_event_query);
	if (NULL != tmp_last_event_query)
		EvtClose(tmp_last_event_query);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s FirstID:" ZBX_FS_UI64 " LastID:" ZBX_FS_UI64,
			__func__, zbx_result_string(ret), *FirstID, *LastID);

	return ret;
}

/* get handles of eventlog */
static int	zbx_get_handle_eventlog6(const wchar_t *wsource, zbx_uint64_t *lastlogsize, EVT_HANDLE *query,
		char **error)
{
	wchar_t	*event_query = NULL;
	char	*tmp_str = NULL;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(), previous lastlogsize:" ZBX_FS_UI64, __func__, *lastlogsize);

	/* start building the query */
	tmp_str = zbx_dsprintf(NULL, "Event/System[EventRecordID>" ZBX_FS_UI64 "]", *lastlogsize);
	event_query = zbx_utf8_to_unicode(tmp_str);

	/* create massive query for an event on a local computer*/
	*query = EvtQuery(NULL, wsource, event_query, EvtQueryChannelPath);
	if (NULL == *query)
	{
		DWORD	status;

		if (ERROR_EVT_CHANNEL_NOT_FOUND == (status = GetLastError()))
			*error = zbx_dsprintf(*error, "EvtQuery channel missed:%s", strerror_from_system(status));
		else
			*error = zbx_dsprintf(*error, "EvtQuery failed:%s", strerror_from_system(status));

		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(tmp_str);
	zbx_free(event_query);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/* initialize event logs with Windows API version 6 */
int	initialize_eventlog6(const char *source, zbx_uint64_t *lastlogsize, zbx_uint64_t *FirstID,
		zbx_uint64_t *LastID, EVT_HANDLE *render_context, EVT_HANDLE *query, char **error)
{
	wchar_t	*wsource = NULL;
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source:'%s' previous lastlogsize:" ZBX_FS_UI64,
			__func__, source, *lastlogsize);

	if (NULL == source || '\0' == *source)
	{
		*error = zbx_dsprintf(*error, "cannot open eventlog with empty name.");
		goto out;
	}

	wsource = zbx_utf8_to_unicode(source);

	if (SUCCEED != zbx_open_eventlog6(wsource, lastlogsize, render_context, FirstID, LastID, error))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot open eventlog '%s'", source);
		goto out;
	}

	if (SUCCEED != zbx_get_handle_eventlog6(wsource, lastlogsize, query, error))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot get eventlog handle '%s'", source);
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(wsource);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/* expand the string message from a specific event handler */
static char	*expand_message6(const wchar_t *pname, EVT_HANDLE event)
{
	wchar_t		*pmessage = NULL;
	EVT_HANDLE	provider = NULL;
	DWORD		require = 0;
	char		*out_message = NULL;
	char		*tmp_pname = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (provider = EvtOpenPublisherMetadata(NULL, pname, NULL, 0, 0)))
	{
		tmp_pname = zbx_unicode_to_utf8(pname);
		zabbix_log(LOG_LEVEL_DEBUG, "provider '%s' could not be opened: %s",
				tmp_pname, strerror_from_system(GetLastError()));
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
				zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot format message: %s", __func__,
						strerror_from_system(error));
				goto out;
			}
		}
	}
out:
	if (NULL != provider)
		EvtClose(provider);
	zbx_free(pmessage);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, out_message);

	/* should be freed */
	return out_message;
}

static void	replace_sid_to_account(PSID sidVal, char **out_message)
{
	DWORD	nlen = MAX_NAME, dlen = MAX_NAME;
	wchar_t	name[MAX_NAME], dom[MAX_NAME], *sid = NULL;
	int	iUse;
	char	userName[MAX_NAME * 4], domName[MAX_NAME * 4], sidName[MAX_NAME * 4], *tmp, buffer[MAX_NAME * 8];

	if (0 == LookupAccountSid(NULL, sidVal, name, &nlen, dom, &dlen, (PSID_NAME_USE)&iUse))
	{
		/* don't replace security ID if no mapping between account names and security IDs was done */
		zabbix_log(LOG_LEVEL_DEBUG, "LookupAccountSid failed:%s", strerror_from_system(GetLastError()));
		return;
	}

	if (0 == nlen)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LookupAccountSid returned empty user name");
		return;
	}

	if (0 == ConvertSidToStringSid(sidVal, &sid))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ConvertSidToStringSid failed:%s", strerror_from_system(GetLastError()));
		return;
	}

	zbx_unicode_to_utf8_static(sid, sidName, sizeof(sidName));
	zbx_unicode_to_utf8_static(name, userName, sizeof(userName));

	if (0 != dlen)
	{
		zbx_unicode_to_utf8_static(dom, domName, sizeof(domName));
		zbx_snprintf(buffer, sizeof(buffer), "%s\\%s", domName, userName);
	}
	else
		zbx_strlcpy(buffer, userName, sizeof(buffer));	/* NULL SID */

	tmp = *out_message;
	*out_message = string_replace(*out_message, sidName, buffer);

	LocalFree(sid);
	zbx_free(tmp);
}

static void	replace_sids_to_accounts(EVT_HANDLE event_bookmark, char **out_message)
{
	DWORD		status, dwBufferSize = 0, dwBufferUsed = 0, dwPropertyCount = 0, i;
	PEVT_VARIANT	renderedContent = NULL;
	EVT_HANDLE	render_context;

	if (NULL == (render_context = EvtCreateRenderContext(0, NULL, EvtRenderContextUser)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "EvtCreateRenderContext failed:%s", strerror_from_system(GetLastError()));
		goto cleanup;
	}

	if (TRUE != EvtRender(render_context, event_bookmark, EvtRenderEventValues, dwBufferSize, renderedContent,
			&dwBufferUsed, &dwPropertyCount))
	{
		if (ERROR_INSUFFICIENT_BUFFER != (status = GetLastError()))
		{
			zabbix_log(LOG_LEVEL_WARNING, "EvtRender failed:%s", strerror_from_system(status));
			goto cleanup;
		}

		dwBufferSize = dwBufferUsed;
		renderedContent = (PEVT_VARIANT)zbx_malloc(NULL, dwBufferSize);

		if (TRUE != EvtRender(render_context, event_bookmark, EvtRenderEventValues, dwBufferSize,
				renderedContent, &dwBufferUsed, &dwPropertyCount))
		{
			zabbix_log(LOG_LEVEL_WARNING, "EvtRender failed:%s", strerror_from_system(GetLastError()));
			goto cleanup;
		}
	}

	for (i = 0; i < dwPropertyCount; i++)
	{
		if (EvtVarTypeSid == renderedContent[i].Type)
			replace_sid_to_account(renderedContent[i].SidVal, out_message);
	}
cleanup:
	if (NULL != render_context)
		EvtClose(render_context);

	zbx_free(renderedContent);
}

/******************************************************************************
 *                                                                            *
 * Purpose: details parse of a single EventLog record                         *
 *                                                                            *
 * Parameters: wsource        - [IN] EventLog file name                       *
 *             render_context - [IN] the handle to the rendering context      *
 *             event_bookmark - [IN/OUT] the handle of Event record for parse *
 *             which          - [IN/OUT] the position of the EventLog record  *
 *             out_severity   - [OUT] the ELR detail                          *
 *             out_timestamp  - [OUT] the ELR detail                          *
 *             out_provider   - [OUT] the ELR detail                          *
 *             out_source     - [OUT] the ELR detail                          *
 *             out_message    - [OUT] the ELR detail                          *
 *             out_eventid    - [OUT] the ELR detail                          *
 *             out_keywords   - [OUT] the ELR detail                          *
 *             error          - [OUT] the error message in the case of        *
 *                                    failure                                 *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_parse_eventlog_message6(const wchar_t *wsource, EVT_HANDLE *render_context,
		EVT_HANDLE *event_bookmark, zbx_uint64_t *which, unsigned short *out_severity,
		unsigned long *out_timestamp, char **out_provider, char **out_source, char **out_message,
		unsigned long *out_eventid, zbx_uint64_t *out_keywords, char **error)
{
	EVT_VARIANT*		renderedContent = NULL;
	const wchar_t		*pprovider = NULL;
	char			*tmp_str = NULL;
	DWORD			size = 0, bookmarkedCount = 0, require = 0, error_code;
	const zbx_uint64_t	sec_1970 = 116444736000000000;
	const zbx_uint64_t	success_audit = 0x20000000000000;
	const zbx_uint64_t	failure_audit = 0x10000000000000;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() EventRecordID:" ZBX_FS_UI64, __func__, *which);

	/* obtain the information from the selected events */
	if (TRUE != EvtRender(*render_context, *event_bookmark, EvtRenderEventValues, size, renderedContent,
			&require, &bookmarkedCount))
	{
		/* information exceeds the space allocated */
		if (ERROR_INSUFFICIENT_BUFFER != (error_code = GetLastError()))
		{
			*error = zbx_dsprintf(*error, "EvtRender failed: %s", strerror_from_system(error_code));
			goto out;
		}

		size = require;
		renderedContent = (EVT_VARIANT *)zbx_malloc(NULL, size);

		if (TRUE != EvtRender(*render_context, *event_bookmark, EvtRenderEventValues, size, renderedContent,
				&require, &bookmarkedCount))
		{
			*error = zbx_dsprintf(*error, "EvtRender failed: %s", strerror_from_system(GetLastError()));
			goto out;
		}
	}

	pprovider = VAR_PROVIDER_NAME(renderedContent);
	*out_provider = zbx_unicode_to_utf8(pprovider);
	*out_source = NULL;

	if (NULL != VAR_SOURCE_NAME(renderedContent))
	{
		*out_source = zbx_unicode_to_utf8(VAR_SOURCE_NAME(renderedContent));
	}

	*out_keywords = VAR_KEYWORDS(renderedContent) & (success_audit | failure_audit);
	*out_severity = VAR_LEVEL(renderedContent);
	*out_timestamp = (unsigned long)((VAR_TIME_CREATED(renderedContent) - sec_1970) / 10000000);
	*out_eventid = VAR_EVENT_ID(renderedContent);
	*out_message = expand_message6(pprovider, *event_bookmark);

	if (NULL != *out_message)
		replace_sids_to_accounts(*event_bookmark, out_message);

	tmp_str = zbx_unicode_to_utf8(wsource);

	if (VAR_RECORD_NUMBER(renderedContent) != *which)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() Overwriting expected EventRecordID:" ZBX_FS_UI64 " with the real"
				" EventRecordID:" ZBX_FS_UI64 " in eventlog '%s'", __func__, *which,
				VAR_RECORD_NUMBER(renderedContent), tmp_str);
		*which = VAR_RECORD_NUMBER(renderedContent);
	}

	/* some events don't have enough information for making event message */
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

						data = zbx_unicode_to_utf8(VAR_EVENT_DATA_STRING_ARRAY(renderedContent,
								i));
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
	EvtClose(*event_bookmark);
	*event_bookmark = NULL;

	zbx_free(tmp_str);
	zbx_free(renderedContent);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose:  batch processing of Event Log file                               *
 *                                                                            *
 * Parameters: addrs            - [IN] vector for passing server and port     *
 *                                     where to send data                     *
 *             agent2_result    - [IN] address of buffer where to store       *
 *                                     matching log records (used only in     *
 *                                     Agent2)                                *
 *             eventlog_name    - [IN] the name of the event log              *
 *             render_context   - [IN] the handle to the rendering context    *
 *             query            - [IN] the handle to the query results        *
 *             lastlogsize      - [IN] position of the last processed record  *
 *             FirstID          - [IN] first record in the EventLog file      *
 *             LastID           - [IN] last record in the EventLog file       *
 *             regexps          - [IN] set of regexp rules for Event Log test *
 *             pattern          - [IN] the regular expression or global       *
 *                                     regular expression name (@<global      *
 *                                     regexp name>).                         *
 *             key_severity     - [IN] severity of logged data sources        *
 *             key_source       - [IN] name of logged data source             *
 *             key_logeventid   - [IN] the application-specific identifier    *
 *                                     for the event                          *
 *             rate             - [IN] threshold of records count at a time   *
 *             process_value_cb - [IN] callback function for sending data to  *
 *                                     the server                             *
 *             metric           - [IN/OUT] parameters for EventLog process    *
 *             lastlogsize_sent - [OUT] position of the last record sent to   *
 *                                      the server                            *
 *             error            - [OUT] the error message in the case of      *
 *                                      failure                               *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
int	process_eventslog6(zbx_vector_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, const char *eventlog_name,
		EVT_HANDLE *render_context, EVT_HANDLE *query, zbx_uint64_t lastlogsize, zbx_uint64_t FirstID,
		zbx_uint64_t LastID, zbx_vector_ptr_t *regexps, const char *pattern, const char *key_severity,
		const char *key_source, const char *key_logeventid, int rate,
		zbx_process_value_func_t process_value_cb, ZBX_ACTIVE_METRIC *metric, zbx_uint64_t *lastlogsize_sent,
		char **error)
{
#	define EVT_ARRAY_SIZE	100

	const char	*str_severity;
	zbx_uint64_t	keywords, i, reading_startpoint = 0;
	wchar_t		*eventlog_name_w = NULL;
	int		s_count = 0, p_count = 0, send_err = SUCCEED, ret = FAIL, match = SUCCEED;
	DWORD		required_buf_size = 0, error_code = ERROR_SUCCESS;

	unsigned long	evt_timestamp, evt_eventid = 0;
	char		*evt_provider, *evt_source, *evt_message, str_logeventid[8];
	unsigned short	evt_severity;
	EVT_HANDLE	event_bookmarks[EVT_ARRAY_SIZE];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source: '%s' previous lastlogsize: " ZBX_FS_UI64 ", FirstID: "
			ZBX_FS_UI64 ", LastID: " ZBX_FS_UI64, __func__, eventlog_name, lastlogsize, FirstID,
			LastID);

	/* update counters */
	if (1 == metric->skip_old_data)
	{
		metric->lastlogsize = LastID - 1;
		metric->skip_old_data = 0;
		zabbix_log(LOG_LEVEL_DEBUG, "skipping existing data: lastlogsize:" ZBX_FS_UI64, lastlogsize);
		goto finish;
	}

	if (NULL == *query)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() no EvtQuery handle", __func__);
		goto out;
	}

	if (lastlogsize >= FirstID && lastlogsize < LastID)
		reading_startpoint = lastlogsize + 1;
	else
		reading_startpoint = FirstID;

	if (reading_startpoint == LastID)	/* LastID = FirstID + count */
		goto finish;

	eventlog_name_w = zbx_utf8_to_unicode(eventlog_name);

	while (ERROR_SUCCESS == error_code)
	{
		/* get the entries */
		if (TRUE != EvtNext(*query, EVT_ARRAY_SIZE, event_bookmarks, INFINITE, 0, &required_buf_size))
		{
			/* The event reading query had less items than we calculated before. */
			/* Either the eventlog was cleaned or our calculations were wrong.   */
			/* Either way we can safely abort the query by setting NULL value    */
			/* and returning success, which is interpreted as empty eventlog.    */
			if (ERROR_NO_MORE_ITEMS == (error_code = GetLastError()))
				continue;

			*error = zbx_dsprintf(*error, "EvtNext failed: %s, EventRecordID:" ZBX_FS_UI64,
					strerror_from_system(error_code), lastlogsize + 1);
			goto out;
		}

		for (i = 0; i < required_buf_size; i++)
		{
			lastlogsize += 1;

			if (SUCCEED != zbx_parse_eventlog_message6(eventlog_name_w, render_context, &event_bookmarks[i],
					&lastlogsize, &evt_severity, &evt_timestamp, &evt_provider, &evt_source,
					&evt_message, &evt_eventid, &keywords, error))
			{
				goto out;
			}

			switch (evt_severity)
			{
				case WINEVENT_LEVEL_LOG_ALWAYS:
				case WINEVENT_LEVEL_INFO:
					if (0 != (keywords & WINEVENT_KEYWORD_AUDIT_FAILURE))
					{
						evt_severity = ITEM_LOGTYPE_FAILURE_AUDIT;
						str_severity = AUDIT_FAILURE;
						break;
					}
					else if (0 != (keywords & WINEVENT_KEYWORD_AUDIT_SUCCESS))
					{
						evt_severity = ITEM_LOGTYPE_SUCCESS_AUDIT;
						str_severity = AUDIT_SUCCESS;
						break;
					}
					else
						evt_severity = ITEM_LOGTYPE_INFORMATION;
						str_severity = INFORMATION_TYPE;
						break;
				case WINEVENT_LEVEL_WARNING:
					evt_severity = ITEM_LOGTYPE_WARNING;
					str_severity = WARNING_TYPE;
					break;
				case WINEVENT_LEVEL_ERROR:
					evt_severity = ITEM_LOGTYPE_ERROR;
					str_severity = ERROR_TYPE;
					break;
				case WINEVENT_LEVEL_CRITICAL:
					evt_severity = ITEM_LOGTYPE_CRITICAL;
					str_severity = CRITICAL_TYPE;
					break;
				case WINEVENT_LEVEL_VERBOSE:
					evt_severity = ITEM_LOGTYPE_VERBOSE;
					str_severity = VERBOSE_TYPE;
					break;
			}

			zbx_snprintf(str_logeventid, sizeof(str_logeventid), "%lu", evt_eventid);

			if (0 == p_count)
			{
				int	ret1, ret2, ret3, ret4;

				if (FAIL == (ret1 = regexp_match_ex(regexps, evt_message, pattern,
						ZBX_CASE_SENSITIVE)))
				{
					*error = zbx_strdup(*error,
							"Invalid regular expression in the second parameter.");
					match = FAIL;
				}
				else if (FAIL == (ret2 = regexp_match_ex(regexps, str_severity, key_severity,
						ZBX_IGNORE_CASE)))
				{
					*error = zbx_strdup(*error,
							"Invalid regular expression in the third parameter.");
					match = FAIL;
				}
				else if (FAIL == (ret3 = regexp_match_ex(regexps, evt_provider, key_source,
						ZBX_IGNORE_CASE)))
				{
					*error = zbx_strdup(*error,
							"Invalid regular expression in the fourth parameter.");
					match = FAIL;
				}
				else if (FAIL == (ret4 = regexp_match_ex(regexps, str_logeventid,
						key_logeventid, ZBX_CASE_SENSITIVE)))
				{
					*error = zbx_strdup(*error,
							"Invalid regular expression in the fifth parameter.");
					match = FAIL;
				}

				if (FAIL == match)
				{
					zbx_free(evt_source);
					zbx_free(evt_provider);
					zbx_free(evt_message);

					ret = FAIL;
					break;
				}

				match = ZBX_REGEXP_MATCH == ret1 && ZBX_REGEXP_MATCH == ret2 &&
						ZBX_REGEXP_MATCH == ret3 && ZBX_REGEXP_MATCH == ret4;
			}
			else
			{
				match = ZBX_REGEXP_MATCH == regexp_match_ex(regexps, evt_message, pattern,
							ZBX_CASE_SENSITIVE) &&
						ZBX_REGEXP_MATCH == regexp_match_ex(regexps, str_severity,
							key_severity, ZBX_IGNORE_CASE) &&
						ZBX_REGEXP_MATCH == regexp_match_ex(regexps, evt_provider,
							key_source, ZBX_IGNORE_CASE) &&
						ZBX_REGEXP_MATCH == regexp_match_ex(regexps, str_logeventid,
							key_logeventid, ZBX_CASE_SENSITIVE);
			}

			if (1 == match)
			{
				send_err = process_value_cb(addrs, agent2_result, CONFIG_HOSTNAME, metric->key_orig,
						evt_message, ITEM_STATE_NORMAL, &lastlogsize, NULL, &evt_timestamp,
						evt_provider, &evt_severity, &evt_eventid,
						metric->flags | ZBX_METRIC_FLAG_PERSISTENT);

				if (SUCCEED == send_err)
				{
					*lastlogsize_sent = lastlogsize;
					s_count++;
				}
			}
			p_count++;

			zbx_free(evt_source);
			zbx_free(evt_provider);
			zbx_free(evt_message);

			if (SUCCEED == send_err)
			{
				metric->lastlogsize = lastlogsize;
			}
			else
			{
				/* buffer is full, stop processing active checks */
				/* till the buffer is cleared */
				break;
			}

			/* do not flood Zabbix server if file grows too fast */
			if (s_count >= (rate * metric->refresh))
				break;

			/* do not flood local system if file grows too fast */
			if (p_count >= (4 * rate * metric->refresh))
				break;
		}

		if (i < required_buf_size)
			error_code = ERROR_NO_MORE_ITEMS;
	}
finish:
	ret = SUCCEED;
out:
	for (i = 0; i < required_buf_size; i++)
	{
		if (NULL != event_bookmarks[i])
			EvtClose(event_bookmarks[i]);
	}

	zbx_free(eventlog_name_w);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s last eventid:%lu", __func__, zbx_result_string(ret), evt_eventid);

	return ret;
}

/* finalize eventlog6 and free the handles */
int	finalize_eventlog6(EVT_HANDLE *render_context, EVT_HANDLE *query)
{
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: try to set reading position in event log                          *
 *                                                                            *
 * Parameters: eventlog_handle - [IN] the handle to the event log to be read  *
 *             FirstID         - [IN] the first Event log record to be parse  *
 *             ReadDirection   - [IN] direction of reading:                   *
 *                                    EVENTLOG_FORWARDS_READ or               *
 *                                    EVENTLOG_BACKWARDS_READ                 *
 *             LastID          - [IN] position of last record in EventLog     *
 *             eventlog_name   - [IN] the name of the event log               *
 *             pELRs           - [IN/OUT] buffer for read of data of EventLog *
 *             buffer_size     - [IN/OUT] size of the pELRs                   *
 *             num_bytes_read  - [OUT] the number of bytes read from EventLog *
 *             error_code      - [OUT] error code (e.g. from ReadEventLog())  *
 *             error           - [OUT] the error message in the case of       *
 *                                     failure                                *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	seek_eventlog(HANDLE *eventlog_handle, zbx_uint64_t FirstID, DWORD ReadDirection,
		zbx_uint64_t LastID, const char *eventlog_name, BYTE **pELRs, int *buffer_size, DWORD *num_bytes_read,
		DWORD *error_code, char **error)
{
	DWORD		dwRecordNumber, required_buf_size;
	zbx_uint64_t	skip_count = 0;

	/* convert to DWORD to handle possible event record number wraparound */
	dwRecordNumber = (DWORD)FirstID;

	*error_code = ERROR_SUCCESS;

	while (ERROR_SUCCESS == *error_code)
	{
		if (0 != ReadEventLog(eventlog_handle, EVENTLOG_SEEK_READ | EVENTLOG_FORWARDS_READ, dwRecordNumber,
				*pELRs, *buffer_size, num_bytes_read, &required_buf_size))
		{
			return SUCCEED;
		}

		if (ERROR_INVALID_PARAMETER == (*error_code = GetLastError()))
		{
			/* See Microsoft Knowledge Base article, 177199 "BUG: ReadEventLog Fails with Error 87" */
			/* how ReadEventLog() can fail with all valid parameters. */
			/* Error code 87 is named ERROR_INVALID_PARAMETER. */
			break;
		}

		if (ERROR_HANDLE_EOF == *error_code)
			return SUCCEED;

		if (ERROR_INSUFFICIENT_BUFFER == *error_code)
		{
			*buffer_size = required_buf_size;
			*pELRs = (BYTE *)zbx_realloc((void *)*pELRs, *buffer_size);
			*error_code = ERROR_SUCCESS;
			continue;
		}

		*error = zbx_dsprintf(*error, "Cannot read eventlog '%s': %s.", eventlog_name,
				strerror_from_system(*error_code));
		return FAIL;
	}

	if (EVENTLOG_FORWARDS_READ == ReadDirection)
	{
		/* Error 87 when reading forwards is handled outside this function */
		*error_code = ERROR_SUCCESS;
		return SUCCEED;
	}

	/* fallback implementation to deal with Error 87 when reading backwards */

	if (ERROR_INVALID_PARAMETER == *error_code)
	{
		if (LastID == FirstID)
			skip_count = 1;
		else
			skip_count = LastID - FirstID;

		zabbix_log(LOG_LEVEL_DEBUG, "In %s(): fallback error_code=%d skip_count="ZBX_FS_UI64, __func__,
				*error_code, skip_count);
	}

	*error_code = ERROR_SUCCESS;

	while (0 < skip_count && ERROR_SUCCESS == *error_code)
	{
		BYTE	*pEndOfRecords, *pELR;

		if (0 == ReadEventLog(eventlog_handle, EVENTLOG_SEQUENTIAL_READ | ReadDirection, 0, *pELRs,
				*buffer_size, num_bytes_read, &required_buf_size))
		{
			if (ERROR_INSUFFICIENT_BUFFER == (*error_code = GetLastError()))
			{
				*error_code = ERROR_SUCCESS;
				*buffer_size = required_buf_size;
				*pELRs = (BYTE *)zbx_realloc((void *)*pELRs, *buffer_size);
				continue;
			}

			if (ERROR_HANDLE_EOF != *error_code)
				break;

			*error = zbx_dsprintf(*error, "Cannot read eventlog '%s': %s.", eventlog_name,
					strerror_from_system(*error_code));
			return FAIL;
		}

		pELR = *pELRs;
		pEndOfRecords = *pELRs + *num_bytes_read;
		*num_bytes_read = 0;	/* we can't reuse the buffer value because of the sort order */

		while (pELR < pEndOfRecords)
		{
			if (0 == --skip_count)
				break;

			pELR += ((PEVENTLOGRECORD)pELR)->Length;
		}
	}

	if (ERROR_HANDLE_EOF == *error_code)
		*error_code = ERROR_SUCCESS;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: details parse of a single Event Log record                        *
 *                                                                            *
 * Parameters: wsource       - [IN] EventLog file name                        *
 *             pELR          - [IN] buffer with single Event Log Record       *
 *             out_source    - [OUT] the ELR detail                           *
 *             out_message   - [OUT] the ELR detail                           *
 *             out_severity  - [OUT] the ELR detail                           *
 *             out_timestamp - [OUT] the ELR detail                           *
 *             out_eventid   - [OUT] the ELR detail                           *
 *                                                                            *
 ******************************************************************************/
#define MAX_INSERT_STRS 100
static void	zbx_parse_eventlog_message(const wchar_t *wsource, const EVENTLOGRECORD *pELR, char **out_source,
		char **out_message, unsigned short *out_severity, unsigned long *out_timestamp,
		unsigned long *out_eventid)
{
	wchar_t 	*pEventMessageFile = NULL, *pParamMessageFile = NULL, *pFile = NULL, *pNextFile = NULL, *pCh,
			*aInsertStrings[MAX_INSERT_STRS];
	HINSTANCE	hLib = NULL, hParamLib = NULL;
	long		i;
	int		err;

	memset(aInsertStrings, 0, sizeof(aInsertStrings));

	*out_message = NULL;
	*out_severity = pELR->EventType;				/* return event type */
	*out_timestamp = pELR->TimeGenerated;				/* return timestamp */
	*out_eventid = pELR->EventID & 0xffff;
	*out_source = zbx_unicode_to_utf8((wchar_t *)(pELR + 1));	/* copy source name */

	/* get message file names */
	zbx_get_message_files(wsource, (wchar_t *)(pELR + 1), &pEventMessageFile, &pParamMessageFile);

	/* prepare insert string array */
	if (0 < pELR->NumStrings)
	{
		pCh = (wchar_t *)((unsigned char *)pELR + pELR->StringOffset);

		for (i = 0; i < pELR->NumStrings && i < MAX_INSERT_STRS; i++)
		{
			aInsertStrings[i] = pCh;
			pCh += wcslen(pCh) + 1;
		}
	}

	err = FAIL;

	for (pFile = pEventMessageFile; NULL != pFile && err != SUCCEED; pFile = pNextFile)
	{
		if (NULL != (pNextFile = wcschr(pFile, TEXT(';'))))
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

			for (i = 0, pCh = (wchar_t *)((unsigned char *)pELR + pELR->StringOffset);
					i < pELR->NumStrings;
					i++, pCh += wcslen(pCh) + 1)
			{
				if (0 < i)
					*out_message = zbx_strdcat(*out_message, "; ");

				buf = zbx_unicode_to_utf8(pCh);
				*out_message = zbx_strdcat(*out_message, buf);
				zbx_free(buf);
			}
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose:  batch processing of Event Log file                               *
 *                                                                            *
 * Parameters: addrs            - [IN] vector for passing server and port     *
 *                                     where to send data                     *
 *             agent2_result    - [IN] address of buffer where to store       *
 *                                     matching log records (used only in     *
 *                                     Agent2)                                *
 *             eventlog_name    - [IN] the name of the event log              *
 *             regexps          - [IN] set of regexp rules for Event Log test *
 *             pattern          - [IN] the regular expression or global       *
 *                                     regular expression name (@<global      *
 *                                     regexp name>).                         *
 *             key_severity     - [IN] severity of logged data sources        *
 *             key_source       - [IN] name of logged data source             *
 *             key_logeventid   - [IN] the application-specific identifier    *
 *                                     for the event                          *
 *             rate             - [IN] threshold of records count at a time   *
 *             process_value_cb - [IN] callback function for sending data to  *
 *                                     the server                             *
 *             metric           - [IN/OUT] parameters for EventLog process    *
 *             lastlogsize_sent - [OUT] position of the last record sent to   *
 *                                      the server                            *
 *             error            - [OUT] the error message in the case of      *
 *                                     failure                                *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
int	process_eventslog(zbx_vector_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, const char *eventlog_name,
		zbx_vector_ptr_t *regexps, const char *pattern, const char *key_severity, const char *key_source,
		const char *key_logeventid, int rate, zbx_process_value_func_t process_value_cb,
		ZBX_ACTIVE_METRIC *metric, zbx_uint64_t *lastlogsize_sent, char **error)
{
	int		ret = FAIL;
	HANDLE		eventlog_handle = NULL;
	wchar_t 	*eventlog_name_w;
	zbx_uint64_t	FirstID, LastID, lastlogsize;
	int		buffer_size = 64 * ZBX_KIBIBYTE;
	DWORD		num_bytes_read = 0, required_buf_size, ReadDirection, error_code;
	BYTE		*pELRs = NULL;
	int		s_count, p_count, send_err = SUCCEED, match = SUCCEED;
	unsigned long	timestamp = 0;
	char		*source;

	lastlogsize = metric->lastlogsize;
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() source:'%s' lastlogsize:" ZBX_FS_UI64, __func__, eventlog_name,
			lastlogsize);

	/* From MSDN documentation:                                                                         */
	/* The RecordNumber member of EVENTLOGRECORD contains the record number for the event log record.   */
	/* The very first record written to an event log is record number 1, and other records are          */
	/* numbered sequentially. If the record number reaches ULONG_MAX, the next record number will be 0, */
	/* not 1; however, you use zero to seek to the record.                                              */
	/*                                                                                                  */
	/* This RecordNumber wraparound is handled simply by using 64bit integer to calculate record        */
	/* numbers and then converting to DWORD values.                                                     */

	if (NULL == eventlog_name || '\0' == *eventlog_name)
	{
		*error = zbx_strdup(*error, "Cannot open eventlog with empty name.");
		return ret;
	}

	eventlog_name_w = zbx_utf8_to_unicode(eventlog_name);

	if (SUCCEED != zbx_open_eventlog(eventlog_name_w, &eventlog_handle, &FirstID, &LastID, &error_code))
	{
		*error = zbx_dsprintf(*error, "Cannot open eventlog '%s': %s.", eventlog_name,
				strerror_from_system(error_code));
		goto out;
	}

	if (1 == metric->skip_old_data)
	{
		metric->lastlogsize = LastID;
		metric->skip_old_data = 0;
		zabbix_log(LOG_LEVEL_DEBUG, "skipping existing data: lastlogsize:" ZBX_FS_UI64, metric->lastlogsize);
		goto finish;
	}

	/* Having lastlogsize greater than LastID means that there was oldest event record */
	/* (FirstID) wraparound. In this case we must also wrap the lastlogsize value.     */
	if (lastlogsize > LastID)
		lastlogsize = (DWORD)lastlogsize;

	ReadDirection = ((LastID - FirstID) / 2) > lastlogsize ? EVENTLOG_FORWARDS_READ : EVENTLOG_BACKWARDS_READ;

	/* if the lastlogsize is still outside log record interval reset it to the oldest record number, */
	/* otherwise set FirstID to the next record after lastlogsize, which is the first event record   */
	/* to read                                                                                       */
	if (lastlogsize > LastID || lastlogsize < FirstID)
	{
		lastlogsize = FirstID;
		ReadDirection = 0;
	}
	else
		FirstID = lastlogsize + 1;

	pELRs = (BYTE*)zbx_malloc((void *)pELRs, buffer_size);

	if (0 == ReadDirection)		/* read eventlog from the first record */
	{
		error_code = ERROR_SUCCESS;
	}
	else if (LastID < FirstID)	/* no new records */
	{
		error_code = ERROR_HANDLE_EOF;
	}
	else if (SUCCEED != seek_eventlog(eventlog_handle, FirstID, ReadDirection, LastID, eventlog_name, &pELRs,
			&buffer_size, &num_bytes_read, &error_code, error))
	{
		goto out;
	}

	zabbix_log(LOG_LEVEL_TRACE, "%s(): state before EventLog reading: num_bytes_read=%u error=%s FirstID="
			ZBX_FS_UI64 " LastID=" ZBX_FS_UI64 " lastlogsize=" ZBX_FS_UI64, __func__,
			(unsigned int)num_bytes_read, strerror_from_system(error_code), FirstID, LastID, lastlogsize);

	if (ERROR_HANDLE_EOF == error_code)
		goto finish;

	s_count = 0;
	p_count = 0;

	/* Read blocks of records until you reach the end of the log or an           */
	/* error occurs. The records are read from oldest to newest. If the buffer   */
	/* is not big enough to hold a complete event record, reallocate the buffer. */
	while (ERROR_SUCCESS == error_code)
	{
		BYTE	*pELR, *pEndOfRecords;

		if (0 == num_bytes_read && 0 == ReadEventLog(eventlog_handle,
				EVENTLOG_SEQUENTIAL_READ | EVENTLOG_FORWARDS_READ, 0,
				pELRs, buffer_size, &num_bytes_read, &required_buf_size))
		{
			if (ERROR_INSUFFICIENT_BUFFER == (error_code = GetLastError()))
			{
				error_code = ERROR_SUCCESS;
				buffer_size = required_buf_size;
				pELRs = (BYTE *)zbx_realloc((void *)pELRs, buffer_size);
				continue;
			}

			if (ERROR_HANDLE_EOF == error_code)
				break;

			*error = zbx_dsprintf(*error, "Cannot read eventlog '%s': %s.", eventlog_name,
					strerror_from_system(error_code));
			goto out;
		}

		pELR = pELRs;
		pEndOfRecords = pELR + num_bytes_read;

		zabbix_log(LOG_LEVEL_TRACE, "%s(): state before buffer parsing: num_bytes_read = %u RecordNumber = %d"
				"FirstID = "ZBX_FS_UI64" LastID = "ZBX_FS_UI64" lastlogsize="ZBX_FS_UI64,
				__func__, (unsigned int)num_bytes_read, ((PEVENTLOGRECORD)pELR)->RecordNumber,
				FirstID, LastID, lastlogsize);
		num_bytes_read = 0;

		while (pELR < pEndOfRecords)
		{
			/* to prevent mismatch in comparing with RecordNumber in case of wrap-around, */
			/* we look for using '=' */
			if (0 != timestamp || (DWORD)FirstID == ((PEVENTLOGRECORD)pELR)->RecordNumber)
			{
				const char	*str_severity;
				unsigned short	severity;
				unsigned long	logeventid;
				char		*value, str_logeventid[8];

				/* increase counter only for records >= FirstID (start point for the search) */
				/* to avoid wrap-around of the 32b RecordNumber we increase the 64b lastlogsize */
				if (0 == timestamp)
					lastlogsize = FirstID;
				else
					lastlogsize += 1;

				zbx_parse_eventlog_message(eventlog_name_w, (EVENTLOGRECORD *)pELR, &source, &value,
						&severity, &timestamp, &logeventid);

				switch (severity)
				{
					case EVENTLOG_SUCCESS:
					case EVENTLOG_INFORMATION_TYPE:
						severity = ITEM_LOGTYPE_INFORMATION;
						str_severity = INFORMATION_TYPE;
						break;
					case EVENTLOG_WARNING_TYPE:
						severity = ITEM_LOGTYPE_WARNING;
						str_severity = WARNING_TYPE;
						break;
					case EVENTLOG_ERROR_TYPE:
						severity = ITEM_LOGTYPE_ERROR;
						str_severity = ERROR_TYPE;
						break;
					case EVENTLOG_AUDIT_FAILURE:
						severity = ITEM_LOGTYPE_FAILURE_AUDIT;
						str_severity = AUDIT_FAILURE;
						break;
					case EVENTLOG_AUDIT_SUCCESS:
						severity = ITEM_LOGTYPE_SUCCESS_AUDIT;
						str_severity = AUDIT_SUCCESS;
						break;
				}

				zbx_snprintf(str_logeventid, sizeof(str_logeventid), "%lu", logeventid);

				if (0 == p_count)
				{
					int	ret1, ret2, ret3, ret4;

					if (FAIL == (ret1 = regexp_match_ex(regexps, value, pattern,
							ZBX_CASE_SENSITIVE)))
					{
						*error = zbx_strdup(*error,
								"Invalid regular expression in the second parameter.");
						match = FAIL;
					}
					else if (FAIL == (ret2 = regexp_match_ex(regexps, str_severity, key_severity,
							ZBX_IGNORE_CASE)))
					{
						*error = zbx_strdup(*error,
								"Invalid regular expression in the third parameter.");
						match = FAIL;
					}
					else if (FAIL == (ret3 = regexp_match_ex(regexps, source, key_source,
							ZBX_IGNORE_CASE)))
					{
						*error = zbx_strdup(*error,
								"Invalid regular expression in the fourth parameter.");
						match = FAIL;
					}
					else if (FAIL == (ret4 = regexp_match_ex(regexps, str_logeventid,
							key_logeventid, ZBX_CASE_SENSITIVE)))
					{
						*error = zbx_strdup(*error,
								"Invalid regular expression in the fifth parameter.");
						match = FAIL;
					}

					if (FAIL == match)
					{
						zbx_free(source);
						zbx_free(value);

						ret = FAIL;
						break;
					}

					match = ZBX_REGEXP_MATCH == ret1 && ZBX_REGEXP_MATCH == ret2 &&
							ZBX_REGEXP_MATCH == ret3 && ZBX_REGEXP_MATCH == ret4;
				}
				else
				{
					match = ZBX_REGEXP_MATCH == regexp_match_ex(regexps, value, pattern,
								ZBX_CASE_SENSITIVE) &&
							ZBX_REGEXP_MATCH == regexp_match_ex(regexps, str_severity,
								key_severity, ZBX_IGNORE_CASE) &&
							ZBX_REGEXP_MATCH == regexp_match_ex(regexps, source,
								key_source, ZBX_IGNORE_CASE) &&
							ZBX_REGEXP_MATCH == regexp_match_ex(regexps, str_logeventid,
								key_logeventid, ZBX_CASE_SENSITIVE);
				}

				if (1 == match)
				{
					send_err = process_value_cb(addrs, agent2_result, CONFIG_HOSTNAME,
							metric->key_orig, value, ITEM_STATE_NORMAL, &lastlogsize,
							NULL, &timestamp, source, &severity, &logeventid,
							metric->flags | ZBX_METRIC_FLAG_PERSISTENT);

					if (SUCCEED == send_err)
					{
						*lastlogsize_sent = lastlogsize;
						s_count++;
					}
				}
				p_count++;

				zbx_free(source);
				zbx_free(value);

				if (SUCCEED == send_err)
				{
					metric->lastlogsize = lastlogsize;
				}
				else
				{
					/* buffer is full, stop processing active checks */
					/* till the buffer is cleared */
					break;
				}

				/* do not flood Zabbix server if file grows too fast */
				if (s_count >= (rate * metric->refresh))
					break;

				/* do not flood local system if file grows too fast */
				if (p_count >= (4 * rate * metric->refresh))
					break;
			}

			pELR += ((PEVENTLOGRECORD)pELR)->Length;
		}

		if (pELR < pEndOfRecords)
			error_code = ERROR_NO_MORE_ITEMS;
	}

finish:
	ret = SUCCEED;
out:
	zbx_close_eventlog(eventlog_handle);
	zbx_free(eventlog_name_w);
	zbx_free(pELRs);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	process_eventlog_check(zbx_vector_ptr_t *addrs, zbx_vector_ptr_t *agent2_result, zbx_vector_ptr_t *regexps,
		ZBX_ACTIVE_METRIC *metric, zbx_process_value_func_t process_value_cb, zbx_uint64_t *lastlogsize_sent,
		char **error)
{
	int 		ret = FAIL;
	AGENT_REQUEST	request;
	const char	*filename, *pattern, *maxlines_persec, *key_severity, *key_source, *key_logeventid, *skip;
	int		rate;
	OSVERSIONINFO	versionInfo;

	init_request(&request);

	if (SUCCEED != parse_item_key(metric->key, &request))
	{
		*error = zbx_strdup(*error, "Invalid item key format.");
		goto out;
	}

	if (0 == get_rparams_num(&request))
	{
		*error = zbx_strdup(*error, "Invalid number of parameters.");
		goto out;
	}

	if (7 < get_rparams_num(&request))
	{
		*error = zbx_strdup(*error, "Too many parameters.");
		goto out;
	}

	if (NULL == (filename = get_rparam(&request, 0)) || '\0' == *filename)
	{
		*error = zbx_strdup(*error, "Invalid first parameter.");
		goto out;
	}

	if (NULL == (pattern = get_rparam(&request, 1)))
	{
		pattern = "";
	}
	else if ('@' == *pattern && SUCCEED != zbx_global_regexp_exists(pattern + 1, regexps))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", pattern + 1);
		goto out;
	}

	if (NULL == (key_severity = get_rparam(&request, 2)))
	{
		key_severity = "";
	}
	else if ('@' == *key_severity && SUCCEED != zbx_global_regexp_exists(key_severity + 1, regexps))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", key_severity + 1);
		goto out;
	}

	if (NULL == (key_source = get_rparam(&request, 3)))
	{
		key_source = "";
	}
	else if ('@' == *key_source && SUCCEED != zbx_global_regexp_exists(key_source + 1, regexps))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", key_source + 1);
		goto out;
	}

	if (NULL == (key_logeventid = get_rparam(&request, 4)))
	{
		key_logeventid = "";
	}
	else if ('@' == *key_logeventid && SUCCEED != zbx_global_regexp_exists(key_logeventid + 1, regexps))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", key_logeventid + 1);
		goto out;
	}

	if (NULL == (maxlines_persec = get_rparam(&request, 5)) || '\0' == *maxlines_persec)
	{
		rate = CONFIG_EVENTLOG_MAX_LINES_PER_SECOND;
	}
	else if (MIN_VALUE_LINES > (rate = atoi(maxlines_persec)) || MAX_VALUE_LINES < rate)
	{
		*error = zbx_strdup(*error, "Invalid sixth parameter.");
		goto out;
	}

	if (NULL == (skip = get_rparam(&request, 6)) || '\0' == *skip || 0 == strcmp(skip, "all"))
	{
		metric->skip_old_data = 0;
	}
	else if (0 != strcmp(skip, "skip"))
	{
		*error = zbx_strdup(*error, "Invalid seventh parameter.");
		goto out;
	}

	versionInfo.dwOSVersionInfoSize = sizeof(OSVERSIONINFO);
	GetVersionEx(&versionInfo);

	if (versionInfo.dwMajorVersion >= 6)	/* Windows Vista, 7 or Server 2008 */
	{
		__try
		{

			zbx_uint64_t	lastlogsize = metric->lastlogsize;
			EVT_HANDLE	eventlog6_render_context = NULL;
			EVT_HANDLE	eventlog6_query = NULL;
			zbx_uint64_t	eventlog6_firstid = 0;
			zbx_uint64_t	eventlog6_lastid = 0;

			if (SUCCEED != initialize_eventlog6(filename, &lastlogsize, &eventlog6_firstid,
					&eventlog6_lastid, &eventlog6_render_context, &eventlog6_query, error))
			{
				finalize_eventlog6(&eventlog6_render_context, &eventlog6_query);
				goto out;
			}

			ret = process_eventslog6(addrs, agent2_result, filename, &eventlog6_render_context,
					&eventlog6_query, lastlogsize, eventlog6_firstid, eventlog6_lastid, regexps,
					pattern, key_severity, key_source, key_logeventid, rate, process_value_cb,
					metric, lastlogsize_sent, error);

			finalize_eventlog6(&eventlog6_render_context, &eventlog6_query);
		}
		__except (DelayLoadDllExceptionFilter(GetExceptionInformation()))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to process eventlog");
		}
	}
	else if (versionInfo.dwMajorVersion < 6)    /* Windows versions before Vista */
	{
		ret = process_eventslog(addrs, agent2_result, filename, regexps, pattern, key_severity, key_source,
				key_logeventid, rate, process_value_cb, metric, lastlogsize_sent, error);
	}
out:
	free_request(&request);

	return ret;
}
