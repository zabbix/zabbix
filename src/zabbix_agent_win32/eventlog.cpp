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

int process_eventlog_new(char *source,int *lastlogsize, char *timestamp, char *src, char *severity, char *message)
{

    HANDLE  hAppLog;
    long    nRecords,Latest=1;
    long    i;
    double  time;
	DWORD    t,type;
	WORD	category;
	int		ret = 1;

INIT_CHECK_MEMORY(main);

    if (!MyOpenEventLog(source,&hAppLog,&nRecords,&Latest))
	{
		for (i = 0; i<nRecords;i++)
        {
			if(*lastlogsize <= i)
			{

				if(0 == MyGetAEventLog(source,hAppLog,Latest,&time,src,message,&type,&category,&t))
				{
					sprintf(timestamp,"%ld",t);

					if(type==EVENTLOG_ERROR_TYPE)	type=4;
					else if(type==EVENTLOG_AUDIT_FAILURE)	type=7;
					else if(type==EVENTLOG_AUDIT_SUCCESS)	type=8;
					else if(type==EVENTLOG_INFORMATION_TYPE)	type=1;
					else if(type==EVENTLOG_WARNING_TYPE)	type=2;
					sprintf(severity,"%d",type);
					*lastlogsize = Latest;
					ret = 0;
					break;
				}
			}
			Latest++;
		}
        MyCloseEventLog(hAppLog);
    }
CHECK_MEMORY(main, "process_eventlog_new","end");

	return ret;
}

// open event logger and return number of records
DllExport   long    MyOpenEventLog(char *pAppName,HANDLE
*pEventHandle,long *pNumRecords,long *pLatestRecord)
{
    HANDLE  hAppLog;                                    /* handle to the
application log */

INIT_CHECK_MEMORY(main);

//	LOG_DEBUG_INFO("s","MyOpenEventLog: start");
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
//	LOG_DEBUG_INFO("s","MyOpenEventLog: end");
    CHECK_MEMORY(main, "MyOpenEventLog", "end");
    return(0);

}

// close event logger
DllExport   long    MyCloseEventLog(HANDLE hAppLog)
{
INIT_CHECK_MEMORY(main);
//	LOG_DEBUG_INFO("s","MyCloseEventLog: start");
    if (hAppLog)
        CloseEventLog(hAppLog);
//	LOG_DEBUG_INFO("s","MyCloseEventLog: end");
    CHECK_MEMORY(main, "MyCloseEventLog", "end");
	return(0);
}

// clear event log
DllExport   long    MyClearEventLog(HANDLE hAppLog)
{
INIT_CHECK_MEMORY(main);
LOG_DEBUG_INFO("s","MyClearEventLog: start");
    if (!(ClearEventLog(hAppLog,0)))
	{
LOG_DEBUG_INFO("s","MyClearEventLog: end1");
        return(GetLastError());
	}
LOG_DEBUG_INFO("s","MyClearEventLog: end2");
CHECK_MEMORY(main, "MyClearEventLog", "end");
    return(0);

}

// get Nth error from event log. 1 is the first.
DllExport   long    MyGetAEventLog(char *pAppName,HANDLE hAppLog,long
which,double *pTime,char *pSource,char *pMessage,DWORD *pType,WORD *pCategory, DWORD *timestamp)
{
    EVENTLOGRECORD  *pELR = NULL;
    BYTE            bBuffer[1024];                      /* hold the event
log record raw data */
    DWORD           dwRead, dwNeeded;
    BOOL            bSuccess;
    char            temp[MAX_PATH];
    char            MsgDll[MAX_PATH];                   /* the name of the
message DLL */
    HKEY            hk = NULL;
    DWORD           Data;
    DWORD           Type;
    HINSTANCE       hLib = NULL;                        /* handle to the
messagetable DLL */
    char            *pCh = NULL, *pFile = NULL, *pNextFile = NULL;
    char            *aInsertStrs[MAX_INSERT_STRS];      // array of pointers to insert
    long            i;
    LPTSTR          msgBuf = NULL;                       // hold text of the error message that we
    long            err = 0;

INIT_CHECK_MEMORY(main);

//LOG_DEBUG_INFO("s","MyGetAEventLog: start");
    if (!hAppLog)
	{
//LOG_DEBUG_INFO("s","MyGetAEventLog: 1");
        return(0);
	}

    bSuccess = ReadEventLog(hAppLog,                    /* event-log handle */
                EVENTLOG_SEEK_READ |                    /* read forward */
                EVENTLOG_FORWARDS_READ,                 /* sequential read */
                which,                                  /* which record to read 1 is first */
                bBuffer,                                /* address of buffer */
                sizeof(bBuffer),                        /* size of buffer */
                &dwRead,                                /* count of bytes read */
                &dwNeeded);                             /* bytes in next record */

    if (!bSuccess)
	{
//LOG_DEBUG_INFO("s","MyGetAEventLog: 2");
		err = GetLastError();
		if(err==0) err = 1;
	}

	if(err == 0)
	{
		pELR = (EVENTLOGRECORD*)bBuffer;                    // point to data

		strcpy(pSource,((char*)pELR + sizeof(EVENTLOGRECORD)));// copy source name
	// build path to message dll
		strcpy(temp,"SYSTEM\\CurrentControlSet\\Services\\EventLog\\");
		strcat(temp,pAppName);
		strcat(temp,"\\");
		strcat(temp,((char*)pELR + sizeof(EVENTLOGRECORD)));
		if (RegOpenKey(HKEY_LOCAL_MACHINE, temp, &hk))
		{
//LOG_DEBUG_INFO("s","MyGetAEventLog: 3");
			err = GetLastError();
			if(err==0) err = 1;
		}
	}

	if(err == 0)
	{
		Data = MAX_PATH;
		if (RegQueryValueEx(hk,			/* handle of key to query */
				"EventMessageFile",     /* value name             */
				NULL,                   /* must be NULL           */
				&Type,                  /* address of type value  */
				(UCHAR*)temp,           /* address of value data  */
				&Data))                 /* length of value data   */
		{
//LOG_DEBUG_INFO("s","MyGetAEventLog: 4");
			err = GetLastError();
			if(err==0) err = 1;
		}

		pFile = temp;
	}
    
	if(err == 0)
	{
		for (;;)
		{
//LOG_DEBUG_INFO("s","MyGetAEventLog: for 1");
//LOG_DEBUG_INFO("s",pFile);
//LOG_DEBUG_INFO("s","MyGetAEventLog: for 1.1");


			pNextFile = strchr(pFile,';');
	        if (pNextFile)
			{
			    *pNextFile = 0;
			}
//LOG_DEBUG_INFO("s","MyGetAEventLog: for 1.3");

			if (!ExpandEnvironmentStrings(pFile, MsgDll, MAX_PATH))
			{
	            err = GetLastError();
				if(err==0) err = 1;
				break;
			}
//LOG_DEBUG_INFO("s","MyGetAEventLog: for 2.1");
			hLib = LoadLibraryEx(MsgDll, NULL, LOAD_LIBRARY_AS_DATAFILE);
		    if (!hLib)
			{
				err = 1;
				break;
			}
//LOG_DEBUG_INFO("s","MyGetAEventLog: 4");

/* prepare the array of insert strings for FormatMessage - the
            insert strings are in the log entry. */
			pCh = (char *)((LPBYTE)pELR + pELR->StringOffset);

			for (i = 0; i < pELR->NumStrings && i < MAX_INSERT_STRS; i++)
			{
//LOG_DEBUG_INFO("s","MyGetAEventLog: 5");
				aInsertStrs[i] = pCh;
				pCh += strlen(pCh) + 1;		/* point to next string */
			}
//LOG_DEBUG_INFO("s","MyGetAEventLog: 6");


/* Format the message from the message DLL with the insert strings */
			if (FormatMessage(
                FORMAT_MESSAGE_FROM_HMODULE |		/* get the message from the DLL */
                FORMAT_MESSAGE_ALLOCATE_BUFFER |    /* allocate the msg buffer for us */
                FORMAT_MESSAGE_ARGUMENT_ARRAY |     /* lpArgs is an array of pointers */
                //60,
				/* line length for the mesages */
				FORMAT_MESSAGE_FROM_SYSTEM,
                hLib,								/* the messagetable DLL handle */
                pELR->EventID,                      /* message ID */
                MAKELANGID(LANG_NEUTRAL, SUBLANG_ENGLISH_US),	/* language ID */
                (LPTSTR) &msgBuf,                   /* address of pointer to buffer for message */
                MAX_MSG_LENGTH,                     /* maximum size of the message buffer */
                aInsertStrs))                       /* array of insert strings for the message */
			{
				err = 0;
				break;
			}

//LOG_DEBUG_INFO("s","MyGetAEventLog: 9");
			if (!pNextFile)							/* more files to read ? */
			{
//LOG_DEBUG_INFO("s","MyGetAEventLog: 10");
			    RegCloseKey(hk);
		        err = GetLastError();
	            if(err == 0) err = 1;
				break;
			}
			pFile = ++pNextFile;

			LocalFree((HLOCAL) msgBuf);
			msgBuf = NULL;
			FreeLibrary(hLib);
			hLib = NULL;
		}
    }

	if(err == 0)
	{
		strcpy(pMessage,msgBuf);                            // copy message

		*pTime = (double)pELR->TimeGenerated;

		*pType = pELR->EventType;                           // return event type
		*pCategory = pELR->EventCategory;                   // return category

		*timestamp=pELR->TimeGenerated;

//LOG_DEBUG_INFO("s","MyGetAEventLog: 11");
	}

/* Free the buffer that FormatMessage allocated for us. */
    if(msgBuf) LocalFree((HLOCAL) msgBuf);
/* free the message DLL since we don't know if we'll need it again */
    if(hLib) FreeLibrary(hLib);
    if(hk)	RegCloseKey(hk);

//LOG_DEBUG_INFO("s","Y");
//LOG_DEBUG_INFO("d",*pType);    
//LOG_DEBUG_INFO("s","MyGetAEventLog: pMessage");
//LOG_DEBUG_INFO("s",pMessage);

CHECK_MEMORY(main, "MyGetAEventLog", "end");
    return err;

} 