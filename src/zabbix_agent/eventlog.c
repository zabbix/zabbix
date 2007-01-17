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

// open event logger and return number of records
static long    zbx_open_eventlog(
	char	*source,
	HANDLE	*eventlog_handle,
	long	*pNumRecords,
	long	*pLatestRecord)
{

	assert(eventlog_handle);
	assert(pNumRecords);
	assert(pLatestRecord);

	*eventlog_handle = 0;
	*pNumRecords = 0;

	eventlog_handle = OpenEventLog(NULL, source);              // open log file

	if (!eventlog_handle)	return GetLastError();

	GetNumberOfEventLogRecords(eventlog_handle,(unsigned long*)pNumRecords); // get number of records
	GetOldestEventLogRecord(eventlog_handle,(unsigned long*)pLatestRecord);

	return(0);
}

// close event logger
static long	zbx_close_eventlog(HANDLE eventlog_handle)
{
	if (eventlog_handle)  CloseEventLog(eventlog_handle);

	return(0);
}

// get Nth error from event log. 1 is the first.
static long    zbx_get_eventlog_message(
		char *source,
		HANDLE eventlog_handle,
		long which,
		double *pTime,
		char *pSource,
		char *pMessage,
		DWORD *pType,
		WORD *pCategory, 
		DWORD *timestamp
		)
{
    EVENTLOGRECORD  *pELR = NULL;
    BYTE            bBuffer[1024];                      /* hold the event log record raw data */
    DWORD           dwRead, dwNeeded;
    char            temp[MAX_PATH];
    char            MsgDll[MAX_PATH];                   /* the name of the message DLL */
    HKEY            hk = NULL;
    DWORD           Data;
    DWORD           Type;
    HINSTANCE       hLib = NULL;                        /* handle to the messagetable DLL */
    char            *pCh = NULL, *pFile = NULL, *pNextFile = NULL;
    char            *aInsertStrs[MAX_INSERT_STRS];      // array of pointers to insert
    long            i;
    LPTSTR          msgBuf = NULL;                       // hold text of the error message that we
    long            err = 0;

	if (!eventlog_handle)        return(0);

	pMessage[0] = '\0';

	if(!ReadEventLog(eventlog_handle,                       /* event-log handle */
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

	pELR = (EVENTLOGRECORD*)bBuffer;                    // point to data

	*pTime		= (double)pELR->TimeGenerated;		// return double timestamp
	*pType		= pELR->EventType;                  // return event type
	*pCategory	= pELR->EventCategory;				// return category
	*timestamp	= pELR->TimeGenerated;				// return timestamp

	strcpy(pSource,((char*)pELR + sizeof(EVENTLOGRECORD)));// copy source name

// Get path to message dll
	strcpy(temp,"SYSTEM\\CurrentControlSet\\Services\\EventLog\\");
	strcat(temp,source);
	strcat(temp,"\\");
	strcat(temp,((char*)pELR + sizeof(EVENTLOGRECORD)));

	pFile = NULL;
	if (RegOpenKeyEx(HKEY_LOCAL_MACHINE, temp, 0, KEY_READ, &hk) == ERROR_SUCCESS)
	{
		pFile = temp; 
		Data = MAX_PATH;
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

	err = 1;
	while(pFile)
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
				/* prepare the array of insert strings for FormatMessage - the
				insert strings are in the log entry. */
				for (
					i = 0,	pCh = (char *)((LPBYTE)pELR + pELR->StringOffset);
					i < pELR->NumStrings && i < MAX_INSERT_STRS; 
					i++,	pCh += strlen(pCh) + 1) /* point to next string */
				{
					aInsertStrs[i] = pCh;
				}

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
					strcpy(pMessage,msgBuf);                    // copy message
					err = 0;

					/* Free the buffer that FormatMessage allocated for us. */
					LocalFree((HLOCAL) msgBuf);
				}
				FreeLibrary(hLib);
			}
		}

		if(err == 0) break;

		pFile = pNextFile;
	}

	if(err)
	{
		for (
			i = 0,	pCh = (char *)((LPBYTE)pELR + pELR->StringOffset);
			i < pELR->NumStrings && i < MAX_INSERT_STRS; 
			i++,	pCh += strlen(pCh) + 1) /* point to next string */
		{
			if(i > 0) 			strcat(pMessage,",");
			strcat(pMessage,pCh);
		}
	}

	return 0;

} 
#endif /* _WINDOWS */

int process_eventlog(
	char *source,
	int *lastlogsize, 
	char *timestamp, 
	char *src, 
	char *severity,
	char *message)
{
	int		ret = 1;
	
#if defined(_WINDOWS)
	
	HANDLE  eventlog_handle;
	long    FirstID;
	long    LastID;
	long    i;
	double  time;
	DWORD    t,type;
	WORD	category;

	if (!zbx_open_eventlog(source,&eventlog_handle,&LastID /* number */, &FirstID /* oldest */))
	{
		LastID += FirstID; 

		if(*lastlogsize > LastID)
			*lastlogsize = FirstID;
		else if((*lastlogsize) >= FirstID)
			FirstID = (*lastlogsize)+1;
		
		for (i = FirstID; i < LastID; i++)
		{
			if(zbx_get_eventlog_message(source,eventlog_handle,i,&time,src,message,&type,&category,&t) == 0)
			{
				zbx_snprintf(timestamp, MAX_STRING_LEN, "%ld",t);

				if(type==EVENTLOG_ERROR_TYPE)				type=4;
				else if(type==EVENTLOG_AUDIT_FAILURE)		type=7;
				else if(type==EVENTLOG_AUDIT_SUCCESS)		type=8;
				else if(type==EVENTLOG_INFORMATION_TYPE)	type=1;
				else if(type==EVENTLOG_WARNING_TYPE)		type=2;
				zbx_snprintf(severity, MAX_STRING_LEN, "%d",type);
				*lastlogsize = i;
				ret = 0;
				break;
			}
		}
		zbx_close_eventlog(eventlog_handle);
	}

#endif /* _WINDOWS */
	
	return ret;
}
