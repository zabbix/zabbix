/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"

#include "log.h"
#include "eventlog.h"

#if defined (_WINDOWS)

#define MAX_INSERT_STRS 64
#define MAX_MSG_LENGTH 1024

#define EVENTLOG_REG_PATH "SYSTEM\\CurrentControlSet\\Services\\EventLog"

/* open event logger and return number of records */
static int    zbx_open_eventlog(const char *source, HANDLE *eventlog_handle, long *pNumRecords, long *pLatestRecord)
{
	const char	*__function_name = "zbx_open_eventlog";
	char		reg_path[MAX_PATH];
	HKEY		hk = NULL;
	int		ret = FAIL;

	assert(eventlog_handle);
	assert(pNumRecords);
	assert(pLatestRecord);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(source:'%s')", __function_name, source);

	*eventlog_handle = NULL;
	*pNumRecords = 0;
	*pLatestRecord = 0;

	if (NULL == source || '\0' == *source)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Can't open eventlog with empty name");
		goto out;
	}

	/* Get path to eventlog */
	zbx_snprintf(reg_path, sizeof(reg_path), EVENTLOG_REG_PATH "\\%s", source);

	if (ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, reg_path, 0, KEY_READ, &hk))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Missing eventlog '%s'", source);
		goto out;
	}

	RegCloseKey(hk);

	if (NULL == (*eventlog_handle = OpenEventLog(NULL, source)))	/* open log file */
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "Can't open eventlog '%s' [%s]", source, strerror_from_system(GetLastError()));
		goto out;
	}

	GetNumberOfEventLogRecords(*eventlog_handle, (unsigned long*)pNumRecords);	/* get number of records */
	GetOldestEventLogRecord(*eventlog_handle, (unsigned long*)pLatestRecord);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(pNumRecords:%ld,pLatestRecord:%ld):%s",
			__function_name, *pNumRecords, *pLatestRecord, zbx_result_string(ret));

	return ret;
}
#include "afxres.h"
/* close event logger */
static long	zbx_close_eventlog(HANDLE eventlog_handle)
{
	if (NULL != eventlog_handle)
		CloseEventLog(eventlog_handle);

	return SUCCEED;
}

/* get Nth error from event log. 1 is the first. */
static int	zbx_get_eventlog_message(
	const char	*source,
	HANDLE		eventlog_handle,
	long		which,
	char		**out_source,
	char		**out_message,
	unsigned short	*out_severity,
	 unsigned long	*out_timestamp,
	unsigned long	*out_eventid)
{
	const char	*__function_name = "zbx_get_eventlog_message";
	int		buffer_size = 512;
	EVENTLOGRECORD	*pELR = NULL;
	DWORD		dwRead, dwNeeded, dwErr;
	char		stat_buf[MAX_PATH], MsgDll[MAX_PATH];
	HKEY		hk = NULL;
	DWORD		Data, Type;
	HINSTANCE	hLib = NULL;			/* handle to the messagetable DLL */
	char		*pCh = NULL, *pFile = NULL, *pNextFile = NULL;
	char		*aInsertStrs[MAX_INSERT_STRS];	/* array of pointers to insert */
	LPTSTR		msgBuf = NULL;			/* hold text of the error message that we */
	long		i, err = 0;
	int		ret = FAIL;

	assert(out_source);
	assert(out_message);
	assert(out_severity);
	assert(out_timestamp);
	assert(out_eventid);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(source:'%s',which:%ld)", __function_name, source, which);

	*out_source	= NULL;
	*out_message	= NULL;
	*out_severity	= 0;
	*out_timestamp	= 0;
	*out_eventid	= 0;
	memset(aInsertStrs, 0, sizeof(aInsertStrs));

	if (!eventlog_handle)
		goto out;

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

	*out_severity	= pELR->EventType;				/* return event type */
	*out_timestamp	= pELR->TimeGenerated;				/* return timestamp */
	*out_eventid	= pELR->EventID & 0xffff;
	*out_source	= strdup((char*)pELR + sizeof(EVENTLOGRECORD));	/* copy source name */

	err = FAIL;

	/* prepare the array of insert strings for FormatMessage - the
	insert strings are in the log entry. */
	for (i = 0, pCh = (char *)((LPBYTE)pELR + pELR->StringOffset);
			i < pELR->NumStrings && i < MAX_INSERT_STRS;
			i++, pCh += strlen(pCh) + 1) /* point to next string */
	{
		aInsertStrs[i] = pCh;
	}

	if (NULL != source && '\0' != *source)
	{
		/* Get path to message dll */
		zbx_snprintf(stat_buf, sizeof(stat_buf), EVENTLOG_REG_PATH "\\%s\\%s", source, *out_source);

		pFile = NULL;

		if (ERROR_SUCCESS == RegOpenKeyEx(HKEY_LOCAL_MACHINE, stat_buf, 0, KEY_READ, &hk))
		{
			pFile = stat_buf;
			Data = sizeof(stat_buf);

			err = RegQueryValueEx(
					hk,			/* handle of key to query */
					"EventMessageFile",	/* value name             */
					NULL,			/* must be NULL           */
					&Type,			/* address of type value  */
					(UCHAR*)pFile,		/* address of value data  */
					&Data);			/* length of value data   */

			RegCloseKey(hk);

			if (ERROR_SUCCESS != err)
				pFile = NULL;
		}

		err = FAIL;

		while (NULL != pFile && FAIL == err)
		{
			if (NULL != (pNextFile = strchr(pFile, ';')))
			{
				*pNextFile = '\0';
				pNextFile++;
			}

			if (ExpandEnvironmentStrings(pFile, MsgDll, MAX_PATH))
			{
				if (NULL != (hLib = LoadLibraryEx(MsgDll, NULL, LOAD_LIBRARY_AS_DATAFILE)))
				{
					/* Format the message from the message DLL with the insert strings */
					FormatMessage(FORMAT_MESSAGE_FROM_HMODULE | FORMAT_MESSAGE_ALLOCATE_BUFFER |
							FORMAT_MESSAGE_ARGUMENT_ARRAY | FORMAT_MESSAGE_FROM_SYSTEM,
							hLib,				/* the messagetable DLL handle */
							pELR->EventID,			/* message ID */
							MAKELANGID(LANG_NEUTRAL, SUBLANG_ENGLISH_US),	/* language ID */
							(LPTSTR) &msgBuf,		/* address of pointer to buffer for message */
							0,
							aInsertStrs);			/* array of insert strings for the message */

					if(msgBuf)
					{
						*out_message = strdup(msgBuf);	/* copy message */

						/* Free the buffer that FormatMessage allocated for us. */
						LocalFree((HLOCAL)msgBuf);

						err = SUCCEED;
					}
					FreeLibrary(hLib);
				}
			}
			pFile = pNextFile;
		}
	}

	if (SUCCEED != err)
	{
		*out_message = zbx_strdcatf(*out_message, "The description for Event ID ( %lu ) in Source ( %s ) cannot be found."
				" The local computer may not have the necessary registry information or message DLL files to"
				" display messages from a remote computer.", *out_eventid, *out_source);
		if (pELR->NumStrings)
			*out_message = zbx_strdcatf(*out_message, " The following information is part of the event: ");
		for (i = 0; i < pELR->NumStrings && i < MAX_INSERT_STRS; i++)
		{
			if (i > 0)
				*out_message = zbx_strdcatf(*out_message, "; ");
			if (aInsertStrs[i])
				*out_message = zbx_strdcatf(*out_message, "%s", aInsertStrs[i]);
		}

	}

	ret = SUCCEED;
out:
	zbx_free(pELR);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
#endif /* _WINDOWS */

int process_eventlog(
	const char	*source,
	long		*lastlogsize,
	unsigned long	*out_timestamp,
	char		**out_source,
	unsigned short	*out_severity,
	char		**out_message,
	unsigned long	*out_eventid)
{
	int		ret = FAIL;

#if defined(_WINDOWS)

	HANDLE  eventlog_handle;
	long    FirstID;
	long    LastID;
	register long    i;

#endif

	assert(lastlogsize);
	assert(out_timestamp);
	assert(out_source);
	assert(out_severity);
	assert(out_message);
	assert(out_eventid);

	*out_timestamp	= 0;
	*out_source		= NULL;
	*out_severity	= 0;
	*out_message	= NULL;
	*out_eventid	= 0;

#if defined(_WINDOWS)

	if (source && source[0] && SUCCEED == zbx_open_eventlog(source,&eventlog_handle,&LastID /* number */, &FirstID /* oldest */))
	{
		LastID += FirstID;

		if(*lastlogsize > LastID)
			*lastlogsize = FirstID;
		else if((*lastlogsize) >= FirstID)
			FirstID = (*lastlogsize)+1;

		for (i = FirstID; i < LastID; i++)
		{
			if (SUCCEED == zbx_get_eventlog_message(source, eventlog_handle, i, out_source, out_message,
					out_severity, out_timestamp, out_eventid))
			{
				*lastlogsize = i;
				break;
			}
		}
		zbx_close_eventlog(eventlog_handle);

		ret = SUCCEED;
	}

#endif /* _WINDOWS */

	return ret;
}
