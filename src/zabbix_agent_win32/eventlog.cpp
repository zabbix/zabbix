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

	char tmp[1024];

	sprintf(tmp,"process_ebent_log_new([%s],[%d],[%s],[%s],[%s])", source,*lastlogsize, timestamp, src,message);
	
// open up event log
//    if (!MyOpenEventLog("Application",&hAppLog,&nRecords,&Latest))
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","process_eventlog_new: start");
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",tmp);
    if (!MyOpenEventLog(source,&hAppLog,&nRecords,&Latest))
	{
//		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","process_eventlog_new: if 1");

    
//        for (i = nRecords + 1;--i;++Latest)
		for (i = 0; i<nRecords;i++)
        {
			WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","process_eventlog_new: for 1");
//           if (Latest > nRecords)                          // need totreat as circular que
//               Latest = 1;
//				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","i");
//				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"d",i);
			WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","process_eventlog_new: for 1.1");
			sprintf(tmp,"[%d],[%d],[%d]", i, nRecords, *lastlogsize);
			WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",tmp);
			WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","process_eventlog_new: for 1.2");
			if(*lastlogsize <= i)
			{

//				MyGetAEventLog("Application",hAppLog,Latest,&time,src,msg,&type,&category);
				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","process_eventlog_new: if 2");
				if(0 == MyGetAEventLog(source,hAppLog,Latest,&time,src,message,&type,&category,&t))
				{
					WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","process_eventlog_new: if 3");
					sprintf(timestamp,"%ld",t);
//					WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"d",type);
//				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"d",t);
					if(type==EVENTLOG_ERROR_TYPE)	type=4;
					else if(type==EVENTLOG_AUDIT_FAILURE)	type=7;
					else if(type==EVENTLOG_AUDIT_SUCCESS)	type=8;
					else if(type==EVENTLOG_INFORMATION_TYPE)	type=1;
					else if(type==EVENTLOG_WARNING_TYPE)	type=2;
					sprintf(severity,"%d",type);
//				sprintf(message,"Src = %s, Msg = %s, type = %d, Category = %d\n",src,msg,type,category);
//				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"d",Latest);
//					WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",severity);
					*lastlogsize = Latest;
					WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","pen:4");
					MyCloseEventLog(hAppLog);
					WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","pen:5");
					return 0;
				}
				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","pen:6");
			}
			Latest++;
			WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","pen:8");
		}
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","process_eventlog_new: 6");
        MyCloseEventLog(hAppLog);
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","process_eventlog_new: 7");
    }
WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","process_eventlog_new: end");
	return 1;
}

// open event logger and return number of records
DllExport   long    MyOpenEventLog(char *pAppName,HANDLE
*pEventHandle,long *pNumRecords,long *pLatestRecord)
{
    HANDLE  hAppLog;                                    /* handle to the
application log */

	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyOpenEventLog: start");
    *pEventHandle = 0;
    *pNumRecords = 0;
    hAppLog = OpenEventLog(NULL,pAppName);              // open log file
    if (!hAppLog)
	{
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyOpenEventLog: 1");
        return(GetLastError());
	}
    GetNumberOfEventLogRecords(hAppLog,(unsigned long*)pNumRecords);// get number of records
    GetOldestEventLogRecord(hAppLog,(unsigned long*)pLatestRecord);
    *pEventHandle = hAppLog;
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyOpenEventLog: end");
    return(0);

}

// close event logger
DllExport   long    MyCloseEventLog(HANDLE hAppLog)
{
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyCloseEventLog: start");
    if (hAppLog)
        CloseEventLog(hAppLog);
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyCloseEventLog: end");
    return(0);

}

// clear event log
DllExport   long    MyClearEventLog(HANDLE hAppLog)
{
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyClearEventLog: start");
    if (!(ClearEventLog(hAppLog,0)))
	{
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyClearEventLog: end1");
        return(GetLastError());
	}
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyClearEventLog: end2");
    return(0);

}

// get Nth error from event log. 1 is the first.
DllExport   long    MyGetAEventLog(char *pAppName,HANDLE hAppLog,long
which,double *pTime,char *pSource,char *pMessage,DWORD *pType,WORD *pCategory, DWORD *timestamp)
{
    EVENTLOGRECORD  *pELR;
    BYTE            bBuffer[1024];                      /* hold the event
log record raw data */
    DWORD           dwRead, dwNeeded;
    BOOL            bSuccess;
    char            temp[MAX_PATH];
    char            MsgDll[MAX_PATH];                   /* the name of the
message DLL */
    HKEY            hk;
    DWORD           Data;
    DWORD           Type;
    HINSTANCE       hLib;                               /* handle to the
messagetable DLL */
    char            *pCh,*pFile,*pNextFile;
    char            *aInsertStrs[MAX_INSERT_STRS];      // array of pointers to insert
    long            i;
    LPTSTR          msgBuf;                             // hold text of the error message that we
    long            err;

	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: start");
    if (!hAppLog)
	{
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: 1");
        return(0);
	}
    bSuccess = ReadEventLog(hAppLog,                    /* event-log handle */
                EVENTLOG_SEEK_READ |                    /* read forward */
                EVENTLOG_FORWARDS_READ,                 /* sequential read */
                which,                                  /* which record to
read 1 is first */
                bBuffer,                                /* address of buffer */
                sizeof(bBuffer),                        /* size of buffer */
                &dwRead,                                /* count of bytes
read */
                &dwNeeded);                             /* bytes in next
record */
    if (!bSuccess)
	{
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: 2");
        return(GetLastError());
	}
    pELR = (EVENTLOGRECORD*)bBuffer;                    // point to data

    strcpy(pSource,((char*)pELR + sizeof(EVENTLOGRECORD)));// copy source name
// build path to message dll
    strcpy(temp,"SYSTEM\\CurrentControlSet\\Services\\EventLog\\");
    strcat(temp,pAppName);
    strcat(temp,"\\");
    strcat(temp,((char*)pELR + sizeof(EVENTLOGRECORD)));
    if (RegOpenKey(HKEY_LOCAL_MACHINE, temp, &hk))
	{
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: 3");
        return(GetLastError());
	}
    Data = MAX_PATH;
    if (RegQueryValueEx(hk,                             /* handle of key
to query        */
            "EventMessageFile",                         /* value
name            */
            NULL,                                       /* must be
NULL          */
            &Type,                                      /* address of type
value           */
            (UCHAR*)temp,                               /* address of
value data */
            &Data))                                     /* length of value
data  */
	{
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: 4");
        return(GetLastError());
	}
    pFile = temp;
    err = 1;

    for (;;)
    {
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: for 1");
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",pFile);
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: for 1.1");


        if ((pNextFile = strchr(pFile,';')))
		{
			WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: for 1.2");
            *pNextFile = 0;
		}
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: for 1.3");


        if (!ExpandEnvironmentStrings(pFile, MsgDll, MAX_PATH))
		{
			WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: for 2");
            return(GetLastError());
		}
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: for 2.1");
        if (!(hLib = LoadLibraryEx(MsgDll, NULL, LOAD_LIBRARY_AS_DATAFILE)))
		{
			WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: for 3");
            return(1);
		}

/* prepare the array of insert strings for FormatMessage - the
            insert strings are in the log entry. */
        pCh = (char *)((LPBYTE)pELR + pELR->StringOffset);
			WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: 4");

        for (i = 0; i < pELR->NumStrings && i < MAX_INSERT_STRS; i++)
        {
			WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: 5");
            aInsertStrs[i] = pCh;
            pCh += strlen(pCh) + 1;                         /* point to
next string */
        }
		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: 6");


/* Format the message from the message DLL with the insert strings */
        if (FormatMessage(
                FORMAT_MESSAGE_FROM_HMODULE |               /* get the
message from the DLL */
                FORMAT_MESSAGE_ALLOCATE_BUFFER |            /* allocate
the msg buffer for us */
                FORMAT_MESSAGE_ARGUMENT_ARRAY |             /* lpArgs is
an array of pointers */
                //60,
				/* line length
for the mesages */
				FORMAT_MESSAGE_FROM_SYSTEM,
                hLib,                                       /* the
messagetable DLL handle */
                pELR->EventID,                              /* message ID */
                MAKELANGID(LANG_NEUTRAL, SUBLANG_ENGLISH_US),/* language ID */
                (LPTSTR) &msgBuf,                           /* address of
pointer to buffer for message */
                MAX_MSG_LENGTH,                             /* maximum
size of the message buffer */
                aInsertStrs))                               /* array of
insert strings for the message */
		{
			WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: 7");
                    break;
		}
WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: 8");
        FreeLibrary(hLib);

		WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: 9");
		if (!pNextFile)                                     // more files to read ?
        {
            RegCloseKey(hk);
            i = GetLastError();
			WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: 10");
            return(i);
        }
        pFile = ++pNextFile;
    }

    strcpy(pMessage,msgBuf);                                // copy message

    *pTime = (double)pELR->TimeGenerated;

    *pType = pELR->EventType;                           // return event type
	*pCategory = pELR->EventCategory;                   // return category

	*timestamp=pELR->TimeGenerated;


/* Free the buffer that FormatMessage allocated for us. */
    LocalFree((HLOCAL) msgBuf);

/* free the message DLL since we don't know if we'll need it again */
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: 11");
    FreeLibrary(hLib);
	WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: 12");
    RegCloseKey(hk);

//WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","Y");
//WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"d",*pType);    
WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","MyGetAEventLog: end");
    return(0);

} 