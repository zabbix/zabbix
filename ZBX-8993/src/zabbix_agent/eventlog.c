/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#define EVENTLOG_REG_PATH TEXT("SYSTEM\\CurrentControlSet\\Services\\EventLog\\")

/* open event logger and return number of records */
static int    zbx_open_eventlog(LPCTSTR wsource, HANDLE *eventlog_handle, long *pNumRecords, long *pLatestRecord)
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
	TCHAR	MsgDll[MAX_PATH];

	if (NULL == szFileName || 0 == ExpandEnvironmentStrings(szFileName, MsgDll, MAX_PATH))
		return NULL;

	return LoadLibraryEx(MsgDll, NULL, LOAD_LIBRARY_AS_DATAFILE);
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
static int	zbx_get_eventlog_message(LPCTSTR wsource, HANDLE eventlog_handle, long which, char **out_source,
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

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() which:%ld", __function_name, which);

	*out_source	= NULL;
	*out_message	= NULL;
	*out_severity	= 0;
	*out_timestamp	= 0;
	*out_eventid	= 0;
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

	*out_severity	= pELR->EventType;			/* return event type */
	*out_timestamp	= pELR->TimeGenerated;			/* return timestamp */
	*out_eventid	= pELR->EventID & 0xffff;
	*out_source	= zbx_unicode_to_utf8((LPTSTR)(pELR + 1));	/* copy source name */

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
		*out_message = zbx_strdcatf(*out_message, "The description for Event ID (%lu) in Source (%s) cannot be found."
				" The local computer may not have the necessary registry information or message DLL files to"
				" display messages from a remote computer.", *out_eventid, NULL == *out_source ? "" : *out_source);

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

int	process_eventlog(const char *source, zbx_uint64_t *lastlogsize, unsigned long *out_timestamp, char **out_source,
		unsigned short *out_severity, char **out_message, unsigned long	*out_eventid, unsigned char skip_old_data)
{
	const char	*__function_name = "process_eventlog";
	int		ret = FAIL;
	HANDLE		eventlog_handle;
	long		FirstID, LastID;
	register long	i;
	LPTSTR		wsource;

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

	if (SUCCEED == zbx_open_eventlog(wsource, &eventlog_handle, &LastID /* number */, &FirstID /* oldest */))
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
			FirstID = (long)*lastlogsize + 1;

		for (i = FirstID; i < LastID; i++)
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

	zbx_free(wsource);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
