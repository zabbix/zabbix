#include "zabbixw32.h"

#define DllExport   __declspec( dllexport )
#define MAX_INSERT_STRS 64
#define MAX_MSG_LENGTH 1024

DllExport   long    MyOpenEventLog(char *pAppName,HANDLE
*pEventHandle,long *pNumRecords,long *pLatestRecord);
DllExport   long    MyCloseEventLog(HANDLE hAppLog);
DllExport   long    MyClearEventLog(HANDLE hAppLog);
DllExport   long    MyGetAEventLog(char *pAppName,HANDLE hAppLog,long
which,double *pTime,char *pSource,char *pMessage,DWORD *pType,WORD
*pCategory, DWORD *timestamp);

int process_eventlog(
	char *source,
	int *lastlogsize, 
	char *timestamp, 
	char *src, 
	char *severity,
	char *message)
{

    HANDLE  hAppLog;
    long    FirstID;
    long    LastID;
    long    i;
    double  time;
	DWORD    t,type;
	WORD	category;
	int		ret = 1;

LOG_FUNC_CALL("In process_eventlog()");
INIT_CHECK_MEMORY(main);

    if (!MyOpenEventLog(source,&hAppLog,&LastID,&FirstID))
	{
		LastID += FirstID; 
		FirstID = ((*lastlogsize) >= FirstID) ? (*lastlogsize)+1 : FirstID;

		for (i = FirstID; i < LastID; i++)
        {
			if(MyGetAEventLog(source,hAppLog,i,&time,src,message,&type,&category,&t) == 0)
			{
				sprintf(timestamp,"%ld",t);

				if(type==EVENTLOG_ERROR_TYPE)				type=4;
				else if(type==EVENTLOG_AUDIT_FAILURE)		type=7;
				else if(type==EVENTLOG_AUDIT_SUCCESS)		type=8;
				else if(type==EVENTLOG_INFORMATION_TYPE)	type=1;
				else if(type==EVENTLOG_WARNING_TYPE)		type=2;
				sprintf(severity,"%d",type);
				*lastlogsize = i;
				ret = 0;
				break;
			}
		}
        MyCloseEventLog(hAppLog);
    }

CHECK_MEMORY(main, "process_eventlog","end");
LOG_FUNC_CALL("End of process_eventlog()");

	return ret;
}

// open event logger and return number of records
DllExport   long    MyOpenEventLog(
	char	*pAppName,
	HANDLE	*pEventHandle,
	long	*pNumRecords,
	long	*pLatestRecord)
{
    HANDLE  hAppLog;		/* handle to the application log */

LOG_FUNC_CALL("In MyOpenEventLog()");
INIT_CHECK_MEMORY(main);

    *pEventHandle = 0;
    *pNumRecords = 0;
    hAppLog = OpenEventLog(NULL,pAppName);              // open log file
    if (!hAppLog)
	{
		LOG_DEBUG_INFO("s","MyOpenEventLog: 1");
        return(GetLastError());
	}
    GetNumberOfEventLogRecords(hAppLog,(unsigned long*)pNumRecords);// get number of records
    GetOldestEventLogRecord(hAppLog,(unsigned long*)pLatestRecord);
    *pEventHandle = hAppLog;

CHECK_MEMORY(main, "MyOpenEventLog", "end");
LOG_FUNC_CALL("End of MyOpenEventLog()");
    return(0);

}

// close event logger
DllExport   long    MyCloseEventLog(
	HANDLE hAppLog
	)
{
LOG_FUNC_CALL("In MyCloseEventLog()");
INIT_CHECK_MEMORY(main);

    if (hAppLog)  CloseEventLog(hAppLog);

CHECK_MEMORY(main, "MyCloseEventLog", "end");
LOG_FUNC_CALL("End of MyCloseEventLog()");
	return(0);
}

// clear event log
DllExport   long    MyClearEventLog(
	HANDLE hAppLog
	)
{
LOG_FUNC_CALL("In MyClearEventLog()");
INIT_CHECK_MEMORY(main);

    if (!(ClearEventLog(hAppLog,0)))
	{
LOG_DEBUG_INFO("s","MyClearEventLog: error exit");
        return(GetLastError());
	}

CHECK_MEMORY(main, "MyClearEventLog", "end");
LOG_FUNC_CALL("End of MyClearEventLog()");
    return(0);

}

// get Nth error from event log. 1 is the first.
DllExport   long    MyGetAEventLog(
		char *pAppName,
		HANDLE hAppLog,
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

LOG_FUNC_CALL("In MyGetAEventLog()");
INIT_CHECK_MEMORY(main);

    if (!hAppLog)        return(0);

	pMessage[0] = '\0';

    if(!ReadEventLog(hAppLog,                    /* event-log handle */
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
	strcat(temp,pAppName);
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

CHECK_MEMORY(main, "MyGetAEventLog", "end");
LOG_FUNC_CALL("End of MyGetAEventLog()");
    return 0;

} 