#include "zabbixw32.h"

#define DllExport   __declspec( dllexport )
#define MAX_INSERT_STRS 8
#define MAX_MSG_LENGTH 1024

DllExport   long    MyOpenEventLog(char *pAppName,HANDLE
*pEventHandle,long *pNumRecords,long *pLatestRecord);
DllExport   long    MyCloseEventLog(HANDLE hAppLog);
DllExport   long    MyClearEventLog(HANDLE hAppLog);
DllExport   long    MyGetAEventLog(char *pAppName,HANDLE hAppLog,long
which,double *pTime,char *pSource,char *pMessage,long *pType,long
*pCategory, int *timestamp);

int process_eventlog_new(char *source,int *lastlogsize, char *timestamp, char *src, char *severity, char *message)
{

    HANDLE  hAppLog;
    long    nRecords,Latest=1;
    long    i;
    double  time;
	int	t;
    char    msg[1024];
    long    type,category;
	
// open up event log
//    if (!MyOpenEventLog("Application",&hAppLog,&nRecords,&Latest))
    if (!MyOpenEventLog(source,&hAppLog,&nRecords,&Latest))
	{

    
//        for (i = nRecords + 1;--i;++Latest)
		for (i = 0; i<nRecords;i++)
        {
//           if (Latest > nRecords)                          // need totreat as circular que
//               Latest = 1;
//				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s","i");
//				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"d",i);
			if(*lastlogsize <= i)
			{

//				MyGetAEventLog("Application",hAppLog,Latest,&time,src,msg,&type,&category);
				MyGetAEventLog(source,hAppLog,Latest,&time,src,msg,&type,&category,&t);
				sprintf(timestamp,"%d",t);
				sprintf(severity,"%ld",type);
				sprintf(message,"Src = %s, Msg = %s, type = %d, Category = %d\n",src,msg,type,category);
				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"d",Latest);
				WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",message);
				*lastlogsize = Latest;
				MyCloseEventLog(hAppLog);
				return 0;
			}
			Latest++;
		}
        MyCloseEventLog(hAppLog);
    }

	return 1;
}

// open event logger and return number of records
DllExport   long    MyOpenEventLog(char *pAppName,HANDLE
*pEventHandle,long *pNumRecords,long *pLatestRecord)
{
    HANDLE  hAppLog;                                    /* handle to the
application log */

    *pEventHandle = 0;
    *pNumRecords = 0;
    hAppLog = OpenEventLog(NULL,pAppName);              // open log file
    if (!hAppLog)
        return(GetLastError());
    GetNumberOfEventLogRecords(hAppLog,(unsigned long*)pNumRecords);// get number of records
    GetOldestEventLogRecord(hAppLog,(unsigned long*)pLatestRecord);
    *pEventHandle = hAppLog;
    return(0);

}

// close event logger
DllExport   long    MyCloseEventLog(HANDLE hAppLog)
{
    if (hAppLog)
        CloseEventLog(hAppLog);
    return(0);

}

// clear event log
DllExport   long    MyClearEventLog(HANDLE hAppLog)
{
    if (!(ClearEventLog(hAppLog,0)))
        return(GetLastError());
    return(0);

}

// get Nth error from event log. 1 is the first.
DllExport   long    MyGetAEventLog(char *pAppName,HANDLE hAppLog,long
which,double *pTime,char *pSource,char *pMessage,long *pType,long *pCategory, int *timestamp)
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

    if (!hAppLog)
        return(0);
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
        return(GetLastError());
    pELR = (EVENTLOGRECORD*)bBuffer;                    // point to data

    strcpy(pSource,((char*)pELR + sizeof(EVENTLOGRECORD)));// copy source name
// build path to message dll
    strcpy(temp,"SYSTEM\\CurrentControlSet\\Services\\EventLog\\");
    strcat(temp,pAppName);
    strcat(temp,"\\");
    strcat(temp,((char*)pELR + sizeof(EVENTLOGRECORD)));
    if (RegOpenKey(HKEY_LOCAL_MACHINE, temp, &hk))
        return(GetLastError());
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
        return(GetLastError());
    pFile = temp;
    err = 1;

    for (;;)
    {
        if ((pNextFile = strchr(pFile,';')))
            *pNextFile = 0;
        if (!ExpandEnvironmentStrings(pFile, MsgDll, MAX_PATH))
            return(GetLastError());
        if (!(hLib = LoadLibraryEx(MsgDll, NULL, LOAD_LIBRARY_AS_DATAFILE)))
            return(1);

/* prepare the array of insert strings for FormatMessage - the
            insert strings are in the log entry. */
        pCh = (char *)((LPBYTE)pELR + pELR->StringOffset);
        for (i = 0; i < pELR->NumStrings && i < MAX_INSERT_STRS; i++)
        {
            aInsertStrs[i] = pCh;
            pCh += strlen(pCh) + 1;                         /* point to
next string */
        }

/* Format the message from the message DLL with the insert strings */
        if (FormatMessage(
                FORMAT_MESSAGE_FROM_HMODULE |               /* get the
message from the DLL */
                FORMAT_MESSAGE_ALLOCATE_BUFFER |            /* allocate
the msg buffer for us */
                FORMAT_MESSAGE_ARGUMENT_ARRAY |             /* lpArgs is
an array of pointers */
                60,                                         /* line length
for the mesages */
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
                    break;
        FreeLibrary(hLib);
        if (!pNextFile)                                     // more files to read ?
        {
            RegCloseKey(hk);
            i = GetLastError();
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
    FreeLibrary(hLib);
    RegCloseKey(hk);
    return(0);

} 