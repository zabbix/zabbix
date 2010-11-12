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
static int    zbx_open_eventlog(
	const char	*source,
	HANDLE		*eventlog_handle,
	long		*pNumRecords,
	long		*pLatestRecord)
{
	char	reg_path[MAX_PATH];
	HKEY	hk = NULL;

	assert(eventlog_handle);
	assert(pNumRecords);
	assert(pLatestRecord);

	zabbix_log(LOG_LEVEL_DEBUG, "In zbx_open_eventlog() [source:%s]",
		source);

	*eventlog_handle = 0;
	*pNumRecords = 0;

	if( !source || !*source )
	{
		zabbix_log(LOG_LEVEL_WARNING, "Can't open eventlog with empty name");
		return FAIL;
	}

	/* Get path to eventlog */
	zbx_snprintf(reg_path, sizeof(reg_path), EVENTLOG_REG_PATH "\\%s", source);

	if ( ERROR_SUCCESS != RegOpenKeyEx(HKEY_LOCAL_MACHINE, reg_path, 0, KEY_READ, &hk) )
	{
		zabbix_log(LOG_LEVEL_WARNING, "Missed eventlog '%s'", source);
		return FAIL;
	}
	
	RegCloseKey(hk);

	if ( !(*eventlog_handle = OpenEventLog(NULL, source)))	/* open log file */
	{
		
		zabbix_log(LOG_LEVEL_INFORMATION, "Can't open eventlog '%s' [%s]", source, strerror_from_system(GetLastError()));
		return FAIL;
	}

	GetNumberOfEventLogRecords(*eventlog_handle,(unsigned long*)pNumRecords); /* get number of records */
	GetOldestEventLogRecord(*eventlog_handle,(unsigned long*)pLatestRecord);

	return SUCCEED;
}
#include "afxres.h"
/* close event logger */
static long	zbx_close_eventlog(HANDLE eventlog_handle)
{
	if (eventlog_handle)  CloseEventLog(eventlog_handle);

	return(0);
}

/* get Nth error from event log. 1 is the first. */
static long    zbx_get_eventlog_message(
	const char	*source,
	HANDLE		eventlog_handle,
	long		which,
	char		**out_source,
	char		**out_message,
	unsigned short	*out_severity,
	unsigned long	*out_timestamp)
{
	EVENTLOGRECORD	*pELR = NULL;
	BYTE		bBuffer[1024];			/* hold the event log record raw data */
	DWORD		dwRead, dwNeeded;
	char		stat_buf[MAX_PATH];
	char		MsgDll[MAX_PATH];		/* the name of the message DLL */
	HKEY		hk = NULL;
	DWORD		Data;
	DWORD		Type;
	HINSTANCE	hLib = NULL;			/* handle to the messagetable DLL */
	char		*pCh = NULL, *pFile = NULL, *pNextFile = NULL;
	char		*aInsertStrs[MAX_INSERT_STRS];	/* array of pointers to insert */
	long		i;
	LPTSTR		msgBuf = NULL;			/* hold text of the error message that we */
	long		err = 0;

	assert(out_source);
	assert(out_message);
	assert(out_severity);
	assert(out_timestamp);

	zabbix_log(LOG_LEVEL_DEBUG, "In zbx_get_eventlog_message() [source:%s] [which:%ld]",
		source,
		which);

	*out_source		= NULL;
	*out_message	= NULL;
	*out_severity	= 0;
	*out_timestamp	= 0;

	if (!eventlog_handle)        return(0);

	if(!ReadEventLog(eventlog_handle,               /* event-log handle */
		EVENTLOG_SEEK_READ |                    /* read forward */
		EVENTLOG_FORWARDS_READ,                 /* sequential read */
		which,                                  /* which record to read 1 is first */
		bBuffer,                                /* address of buffer */
		sizeof(bBuffer),                        /* size of buffer */
		&dwRead,                                /* count of bytes read */
		&dwNeeded))                             /* bytes in next record */
	{
		return GetLastError();
	}

	pELR = (EVENTLOGRECORD*)bBuffer;                    /* point to data */

	*out_severity	= pELR->EventType;                  /* return event type */
	*out_timestamp	= pELR->TimeGenerated;				/* return timestamp */

	*out_source = strdup((char*)pELR + sizeof(EVENTLOGRECORD));	/* copy source name */

	err = FAIL;

	/* prepare the array of insert strings for FormatMessage - the
	insert strings are in the log entry. */
	for (
		i = 0,	pCh = (char *)((LPBYTE)pELR + pELR->StringOffset);
		i < pELR->NumStrings && i < MAX_INSERT_STRS; 
		i++,	pCh += strlen(pCh) + 1) /* point to next string */
	{
		aInsertStrs[i] = pCh;
	}

	if( source && *source )
	{
		/* Get path to message dll */
		zbx_snprintf(stat_buf, sizeof(stat_buf),
			EVENTLOG_REG_PATH "\\%s\\%s",
			source,
			*out_source
			);

		pFile = NULL;

		if (RegOpenKeyEx(HKEY_LOCAL_MACHINE, stat_buf, 0, KEY_READ, &hk) == ERROR_SUCCESS)
		{
			pFile = stat_buf; 
			Data = sizeof(stat_buf);

			err = RegQueryValueEx(
					hk,						/* handle of key to query */
					"EventMessageFile",     /* value name             */
					NULL,                   /* must be NULL           */
					&Type,                  /* address of type value  */
					(UCHAR*)pFile,          /* address of value data  */
					&Data);                 /* length of value data   */

			RegCloseKey(hk);

			if(err != ERROR_SUCCESS)
				pFile = NULL;
		}

		err = FAIL;

		while(pFile && FAIL == err)
		{
			pNextFile = strchr(pFile,';');
			if(pNextFile)
			{
				*pNextFile = '\0';
				pNextFile++;
			}

			if (ExpandEnvironmentStrings(pFile, MsgDll, MAX_PATH))
			{
				hLib = LoadLibraryEx(MsgDll, NULL, LOAD_LIBRARY_AS_DATAFILE);
				if(hLib)
				{
					/* Format the message from the message DLL with the insert strings */
					FormatMessage(
						FORMAT_MESSAGE_FROM_HMODULE |
						FORMAT_MESSAGE_ALLOCATE_BUFFER |
						FORMAT_MESSAGE_ARGUMENT_ARRAY |
						FORMAT_MESSAGE_FROM_SYSTEM,
						hLib,								/* the messagetable DLL handle */
						pELR->EventID,                      /* message ID */
						MAKELANGID(LANG_NEUTRAL, SUBLANG_ENGLISH_US),	/* language ID */
						(LPTSTR) &msgBuf,                   /* address of pointer to buffer for message */
						MAX_MSG_LENGTH,                     /* maximum size of the message buffer */
						aInsertStrs);                       /* array of insert strings for the message */

					if(msgBuf)
					{
						*out_message = strdup(msgBuf);		/* copy message */

						/* Free the buffer that FormatMessage allocated for us. */
						LocalFree((HLOCAL) msgBuf);

						err = SUCCEED;
					}
					FreeLibrary(hLib);
				}
			}
			pFile = pNextFile;
		}
	}


	if(SUCCEED != err)
	{
		*out_message = zbx_strdcatf(*out_message, "EventID [%lu]", pELR->EventID);
		for ( i = 0; i < pELR->NumStrings && i < MAX_INSERT_STRS; i++ )
		{
			if ( aInsertStrs[i] )
				*out_message = zbx_strdcatf(*out_message, ",%s", aInsertStrs[i]);
		}
	}

	return 0;
} 
#endif /* _WINDOWS */

int process_eventlog(
	const char	*source,
	long		*lastlogsize, 
	unsigned long	*out_timestamp, 
	char		**out_source, 
	unsigned short	*out_severity,
	char		**out_message)
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

	*out_timestamp	= 0;
	*out_source		= NULL;
	*out_severity	= 0;
	*out_message	= NULL;

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
			if( 0 == zbx_get_eventlog_message(
				source,
				eventlog_handle,
				i,
				out_source,
				out_message,
				out_severity,
				out_timestamp) )
			{
				switch(*out_severity)
				{
					case EVENTLOG_ERROR_TYPE:		*out_severity = 4;	break;
					case EVENTLOG_AUDIT_FAILURE:	*out_severity = 7;	break;
					case EVENTLOG_AUDIT_SUCCESS:	*out_severity = 8;	break;
					case EVENTLOG_INFORMATION_TYPE:	*out_severity = 1;	break;
					case EVENTLOG_WARNING_TYPE:		*out_severity = 2;	break;
				}

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
