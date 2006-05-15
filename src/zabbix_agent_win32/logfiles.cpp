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

/*
#include <errno.h>
#include <stdio.h>
#include <string.h>
*/
#include <io.h>

/*
#include <unistd.h>

#include "common.h"

#include "log.h"
#include "logfiles.h"
 */

#include "zabbixw32.h"

int   process_log(char *filename,int *lastlogsize, char *value)
{
	FILE	*f;

LOG_DEBUG_INFO("s","In process_log()");
INIT_CHECK_MEMORY(main);

	f=fopen(filename,"r");
	if(NULL == f)
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Cannot open [%s] [%s]", filename, strerror(errno));
		sprintf(value,"%s","ZBX_NOTSUPPORTED\n");
CHECK_MEMORY(main, "process_log", "fopen");
		return 1;
	}

LOG_DEBUG_INFO("d",_filelength(_fileno(f)));
LOG_DEBUG_INFO("d",*lastlogsize);

	if(_filelength(_fileno(f)) < *lastlogsize)
	{
		*lastlogsize=0;
	}

LOG_DEBUG_INFO("d",*lastlogsize);

	if(-1 == fseek(f,*lastlogsize,SEEK_SET))
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Cannot set postition to [%d] for [%s] [%s]", *lastlogsize, filename, strerror(errno));
		sprintf(value,"%s","ZBX_NOTSUPPORTED\n");
		fclose(f);
CHECK_MEMORY(main, "process_log", "fseek");
		return 1;
	}

	if(NULL == fgets(value, MAX_STRING_LEN-1, f))
	{
		/* EOF */
		fclose(f);
CHECK_MEMORY(main, "process_log", "fgets");
		return 1;
	}
	fclose(f);

	*lastlogsize+=strlen(value);

CHECK_MEMORY(main, "process_log", "end");
LOG_DEBUG_INFO("s","End of process_log()");
	return 0;
}

int process_eventlog(char *source,int *lastlogsize, int *timestamp, char *value)
{
    HANDLE h;
    EVENTLOGRECORD *pevlr; 
    BYTE bBuffer[1024*64]; 
    DWORD dwRead, dwNeeded, dwThisRecord; 

LOG_DEBUG_INFO("s","Lastlogsize:");
LOG_DEBUG_INFO("d",*lastlogsize);


INIT_CHECK_MEMORY(main);
	
    // Open the Application event log. 
 
    h = OpenEventLog( NULL,    // use local computer
             source);   // source name
    if (h == NULL) 
    {
		sprintf(value,"%s","ZBX_NOTSUPPORTED\n");
		LOG_DEBUG_INFO("s","Could not open event log");
CHECK_MEMORY(main, "process_eventlog", "OpenEventLog");
		return 1;
    }
 
    pevlr = (EVENTLOGRECORD *) &bBuffer; 
 
    // Get the record number of the oldest event log record.

    GetOldestEventLogRecord(h, &dwThisRecord);
// LOG_DEBUG_INFO("d", dwThisRecord);

	    // Opening the event log positions the file pointer for this 
    // handle at the beginning of the log. Read the event log records 
    // sequentially until the last record has been read. 
 
//    while (ReadEventLog(h,                // event log handle 
    while(ReadEventLog(h,                // event log handle 
				EVENTLOG_FORWARDS_READ|EVENTLOG_SEEK_READ,
				dwThisRecord,
                pevlr,        // pointer to buffer 
                1024*64,  // size of buffer 
                &dwRead,      // number of bytes read 
                &dwNeeded))   // bytes in next record 
   {
    					//while (dwRead > 0) 
//		LOG_DEBUG_INFO("d", dwRead);
			if (dwRead > 0) 
        { 
            // Print the record number, event identifier, type, 
            // and source name.
				
			//	LOG_DEBUG_INFO("d",dwThisRecord);
				if((dwThisRecord++) >= ((DWORD)(*lastlogsize)))
				{
					sprintf(value, "%03d  Event ID 0x%08X  Event type ", 
					dwThisRecord, pevlr->EventID);
					WriteLog(MSG_ACTIVE_CHECKS,EVENTLOG_ERROR_TYPE,"s",value);
			
					*lastlogsize=dwThisRecord;
					
					break;
				}
 
            

            switch(pevlr->EventType)
            {
                case EVENTLOG_ERROR_TYPE:
//                    printf("EVENTLOG_ERROR_TYPE\t  ");
                    break;
                case EVENTLOG_WARNING_TYPE:
  //                  printf("EVENTLOG_WARNING_TYPE\t  ");
                    break;
                case EVENTLOG_INFORMATION_TYPE:
    //                printf("EVENTLOG_INFORMATION_TYPE  ");
                    break;
                case EVENTLOG_AUDIT_SUCCESS:
      //              printf("EVENTLOG_AUDIT_SUCCESS\t  ");
                    break;
                case EVENTLOG_AUDIT_FAILURE:
        //            printf("EVENTLOG_AUDIT_FAILURE\t  ");
                    break;
                default:
          //          printf("Unknown ");
                    break;
            }

//            printf("Event source: %s\n", 
//                (LPSTR) ((LPBYTE) pevlr + sizeof(EVENTLOGRECORD))); 
 
            dwRead -= pevlr->Length; 
            pevlr = (EVENTLOGRECORD *) 
                ((LPBYTE) pevlr + pevlr->Length); 
        }

        pevlr = (EVENTLOGRECORD *) &bBuffer; 
    }
		
	 
    CloseEventLog(h); 

CHECK_MEMORY(main, "process_eventlog", "end");

	return 0;
}