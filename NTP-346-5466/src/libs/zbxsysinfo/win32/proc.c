/*
 * ** ZABBIX
 * ** Copyright (C) 2000-2005 SIA Zabbix
 * **
 * ** This program is free software; you can redistribute it and/or modify
 * ** it under the terms of the GNU General Public License as published by
 * ** the Free Software Foundation; either version 2 of the License, or
 * ** (at your option) any later version.
 * **
 * ** This program is distributed in the hope that it will be useful,
 * ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 * ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * ** GNU General Public License for more details.
 * **
 * ** You should have received a copy of the GNU General Public License
 * ** along with this program; if not, write to the Free Software
 * ** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 * **/

#include "common.h"

#include "sysinfo.h"

#include "symbols.h"

#define MAX_PROCESSES         4096
#define MAX_MODULES           512

static int GetProcessUsername(HANDLE hProcess, char *userName, int userNameLen) {
	HANDLE		tok = 0;
	TOKEN_USER	*ptu;

	DWORD
		nlen, 
		dlen;

	char
		name[300],
		dom[300],
		tubuf[300];

	int iUse;

	assert(userName);
	
	//clean result;
	*userName = '\0';

	//open the processes token
	if (!OpenProcessToken(hProcess,TOKEN_QUERY,&tok)) goto lbl_err;;

	//get the SID of the token
	ptu = (TOKEN_USER*)tubuf;
	if (!GetTokenInformation(tok,(TOKEN_INFORMATION_CLASS)1,ptu,300,&nlen)) goto lbl_err;

	//get the account/domain name of the SID
	dlen = 300;	nlen = 300;
	if (!LookupAccountSid(0, ptu->User.Sid, name, &nlen, dom, &dlen, (PSID_NAME_USE)&iUse)) goto lbl_err;

	nlen = min(userNameLen-1,(int)nlen);

	zbx_strlcpy(userName, name, nlen);

	return 1;

lbl_err:
	if (tok) CloseHandle(tok);
	return 0;
}

				    
int     PROC_MEMORY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{ /* usage: <function name>[ <process name>, <user name>, <mode>, <command> ] */
	#ifdef TODO
	#	error Realize function KERNEL_MAXFILES!!!
	#endif /* todo */

	return SYSINFO_RET_FAIL;
}

int	    PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{ /* usage: <function name>[ <process name>, <user name>] */
	HANDLE	hProcess;
	HMODULE	hMod;
	DWORD	procList[MAX_PROCESSES];

	DWORD dwSize=0;

	int 
		i = 0,
		proccount = 0,
		max_proc_cnt = 0,
		proc_ok = 0,
		user_ok = 0;

	char 
		procName[MAX_PATH],
		userName[MAX_PATH],
		baseName[MAX_PATH], 
		uname[300];

	if(num_param(param) > 2)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 1, procName, sizeof(procName)) != 0)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, userName, sizeof(userName)) != 0)
	{
		userName[0] = '\0';
	}


	EnumProcesses(procList,sizeof(DWORD)*MAX_PROCESSES,&dwSize);

	for(i=0,proccount=0,max_proc_cnt=dwSize/sizeof(DWORD); i < max_proc_cnt; i++)
	{
		proc_ok = 0;
		user_ok = 0;

		hProcess=OpenProcess(PROCESS_QUERY_INFORMATION | PROCESS_VM_READ,FALSE,procList[i]);
		if (hProcess!=NULL)
		{
			if (procName[0] != 0) 
			{
				if (EnumProcessModules(hProcess,&hMod,sizeof(hMod),&dwSize))
				{
					GetModuleBaseName(hProcess,hMod,baseName,sizeof(baseName));
					if (stricmp(baseName,procName) == 0)
					{
						proc_ok = 1;
					}
				}
			} else {
				proc_ok = 1;
			}

			if(userName[0] != '\0')
			{
				if(GetProcessUsername(hProcess, uname, 300))
				{
					if (stricmp(uname,userName) == 0)
						user_ok = 1;
				}
			} else {
				user_ok = 1;
			}

			if(user_ok && proc_ok)		proccount++;

			CloseHandle(hProcess);
		}
	}

	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}



/************ PROC INFO ****************/

/*
 * Convert process time from FILETIME structure (100-nanosecond units) to double (milliseconds)
 */

static double ConvertProcessTime(FILETIME *lpft)
{
   __int64 i;

   memcpy(&i,lpft,sizeof(__int64));
   i/=10000;      /* Convert 100-nanosecond units to milliseconds */
   return (double)i;
}

/*
 * Get specific process attribute
 */

static double GetProcessAttribute(HANDLE hProcess,int attr,int type,int count,double *lastValue)
{
   double value;  
   PROCESS_MEMORY_COUNTERS mc;
   IO_COUNTERS ioCounters;
   FILETIME ftCreate,ftExit,ftKernel,ftUser;

   /* Get value for current process instance */
   switch(attr)
   {
      case 0:        /* vmsize */
         GetProcessMemoryInfo(hProcess,&mc,sizeof(PROCESS_MEMORY_COUNTERS));
         value=(double)mc.PagefileUsage/1024;   /* Convert to Kbytes */
         break;
      case 1:        /* wkset */
         GetProcessMemoryInfo(hProcess,&mc,sizeof(PROCESS_MEMORY_COUNTERS));
         value=(double)mc.WorkingSetSize/1024;   /* Convert to Kbytes */
         break;
      case 2:        /* pf */
         GetProcessMemoryInfo(hProcess,&mc,sizeof(PROCESS_MEMORY_COUNTERS));
         value=(double)mc.PageFaultCount;
         break;
      case 3:        /* ktime */
      case 4:        /* utime */
         GetProcessTimes(hProcess,&ftCreate,&ftExit,&ftKernel,&ftUser);
         value = ConvertProcessTime(attr==3 ? &ftKernel : &ftUser);
         break;

      case 5:        /* gdiobj */
      case 6:        /* userobj */
         if(NULL == zbx_GetGuiResources)
	     return SYSINFO_RET_FAIL;

         value = (double)zbx_GetGuiResources(hProcess,attr==5 ? 0 : 1);
         break;

      case 7:        /* io_read_b */
         if(NULL == zbx_GetProcessIoCounters)
	     return SYSINFO_RET_FAIL;

         zbx_GetProcessIoCounters(hProcess,&ioCounters);
         value=(double)((__int64)ioCounters.ReadTransferCount);
         break;
      case 8:        /* io_read_op */
         if(NULL == zbx_GetProcessIoCounters)
	     return SYSINFO_RET_FAIL;

         zbx_GetProcessIoCounters(hProcess,&ioCounters);
         value=(double)((__int64)ioCounters.ReadOperationCount);
         break;
      case 9:        /* io_write_b */
         if(NULL == zbx_GetProcessIoCounters)
	     return SYSINFO_RET_FAIL;

         zbx_GetProcessIoCounters(hProcess,&ioCounters);
         value=(double)((__int64)ioCounters.WriteTransferCount);
         break;
      case 10:       /* io_write_op */
         if(NULL == zbx_GetProcessIoCounters)
	     return SYSINFO_RET_FAIL;

         zbx_GetProcessIoCounters(hProcess,&ioCounters);
         value=(double)((__int64)ioCounters.WriteOperationCount);
         break;
      case 11:       /* io_other_b */
         if(NULL == zbx_GetProcessIoCounters)
	     return SYSINFO_RET_FAIL;

         zbx_GetProcessIoCounters(hProcess,&ioCounters);
         value=(double)((__int64)ioCounters.OtherTransferCount);
         break;
      case 12:       /* io_other_op */
         if(NULL == zbx_GetProcessIoCounters)
	     return SYSINFO_RET_FAIL;

         zbx_GetProcessIoCounters(hProcess,&ioCounters);
         value=(double)((__int64)ioCounters.OtherOperationCount);
         break;

      default:       /* Unknown attribute */
         return SYSINFO_RET_FAIL;
   }

   /* Recalculate final value according to selected type */
   if (count==1)     /* First instance */
   {
      *lastValue = value;
   }

   switch(type)
   {
      case 0:     /* min */
         *lastValue = min((*lastValue),value);
	 break;
      case 1:     /* max */
         *lastValue = max((*lastValue),value);
	 break;
      case 2:     /* avg */
         *lastValue = ((*lastValue) * (count-1) + value) / count;
	 break;
      case 3:     /* sum */
         *lastValue = (*lastValue) + value;
	 break;
      default:
         return SYSINFO_RET_FAIL;
   }

   return SYSINFO_RET_OK;
}


/*
 * Get process-specific information
 * Parameter has the following syntax:
 *    proc_info[<process>,<attribute>,<type>]
 * where
 *    <process>   - process name (same as in proc_cnt[] parameter)
 *    <attribute> - requested process attribute (see documentation for list of valid attributes)
 *    <type>      - representation type (meaningful when more than one process with the same
 *                  name exists). Valid values are:
 *         min - minimal value among all processes named <process>
 *         max - maximal value among all processes named <process>
 *         avg - average value for all processes named <process>
 *         sum - sum of values for all processes named <process>
 */


int	    PROC_INFO(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	DWORD	*procList, dwSize;
	HMODULE *modList;

	char
		proc_name[MAX_PATH],
		attr[MAX_PATH],
		type[MAX_PATH];

	const char *attrList[]=
	{
		"vmsize",
		"wkset",
		"pf",
		"ktime",
		"utime",
		"gdiobj",
		"userobj",
		"io_read_b",
		"io_read_op",
		"io_write_b",
		"io_write_op",
		"io_other_b",
		"io_other_op",
		NULL
	};

	const char *typeList[]={ "min","max","avg","sum" };

	double	value;
	int	
		i,
		proc_cnt,
		counter,
		attr_id,
		type_id,
		ret = SYSINFO_RET_OK;

	if(num_param(param) > 3)
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 1, proc_name, sizeof(proc_name)) != 0)
	{
		proc_name[0] = '\0';
	}

	if(proc_name[0] == '\0')
	{
		return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, attr, sizeof(attr)) != 0)
	{
		attr[0] = '\0';
	}

	if(attr[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(attr, sizeof(attr), "%s", attrList[0]);
	}

	if(get_param(param, 3, type, sizeof(type)) != 0)
	{
		type[0] = '\0';
	}

	if(type[0] == '\0')
	{
		/* default parameter */
		zbx_snprintf(type, sizeof(type), "%s", typeList[2]);
	}

	/* Get attribute code from string */
	for(attr_id = 0; NULL != attrList[attr_id] && strcmp(attrList[attr_id], attr); attr_id++);

	if (attrList[attr_id]==NULL)
	{
		return SYSINFO_RET_FAIL;     /* Unsupported attribute */
	}

	/* Get type code from string */
	for(type_id = 0; NULL != typeList[type_id] && strcmp(typeList[type_id], type); type_id++);

	if (typeList[type_id]==NULL)
	{
		return SYSINFO_RET_FAIL;     /* Unsupported type */
	}

	procList = (DWORD *)malloc(MAX_PROCESSES*sizeof(DWORD));
	modList = (HMODULE *)malloc(MAX_MODULES*sizeof(HMODULE));

	EnumProcesses(procList, sizeof(DWORD)*MAX_PROCESSES, &dwSize);

	proc_cnt = dwSize / sizeof(DWORD);

	for(i=0, counter=0, value=0; i < proc_cnt; i++)
	{
		HANDLE hProcess;

		if (NULL != (hProcess = OpenProcess(PROCESS_QUERY_INFORMATION | PROCESS_VM_READ,FALSE, procList[i])) )
		{
			if (EnumProcessModules(hProcess, modList, sizeof(HMODULE)*MAX_MODULES, &dwSize))
			{
				if (dwSize >= sizeof(HMODULE))     /* At least one module exist */
				{
					char baseName[MAX_PATH];

					GetModuleBaseName(hProcess,modList[0],baseName,sizeof(baseName));
					if (stricmp(baseName, proc_name) == 0)
					{
						if(SYSINFO_RET_OK != GetProcessAttribute(
							hProcess, 
							attr_id, 
							type_id, 
							++counter, /* Number of processes with specific name */
							&value))
						{
							ret = SYSINFO_RET_FAIL;
							break;
						}
					}
				}
			}
			CloseHandle(hProcess);
		}
	}

	free(procList);
	free(modList);

	if(SYSINFO_RET_OK == ret)
	{
		SET_DBL_RESULT(result, value);
	}

	return ret;
}
