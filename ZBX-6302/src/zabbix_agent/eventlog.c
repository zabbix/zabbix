/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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

		if (ExpandEnvironmentStrings(pFile, MsgDll, MAX_PATH))
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
			FirstID = (*lastlogsize) + 1;

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
