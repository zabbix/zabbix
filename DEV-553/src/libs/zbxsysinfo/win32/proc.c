/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"

#include "symbols.h"
#include "log.h"

#define MAX_PROCESSES		4096
/*#define MAX_MODULES		512*/
#define MAX_NAME		256

/* function 'zbx_get_processname' require 'baseName' with size 'MAX_NAME' */
static int	zbx_get_processname(HANDLE hProcess, char *baseName)
{
	HMODULE	hMod;
	DWORD	dwSize;
	TCHAR	name[MAX_NAME];
/*			if (0 != EnumProcessModules(hProcess, modList, sizeof(HMODULE) * MAX_MODULES, &dwSize))
				if (0 != GetModuleBaseName(hProcess,modList[0],baseName,sizeof(baseName)))*/

	if (0 == EnumProcessModules(hProcess, &hMod, sizeof(hMod), &dwSize))
		return FAIL;

	if (0 == GetModuleBaseName(hProcess, hMod, name, sizeof(name)))
		return FAIL;

	zbx_unicode_to_utf8_static(name, baseName, MAX_NAME);

	return SUCCEED;
}

/* function 'zbx_get_process_username' require 'userName' with size 'MAX_NAME' */
static int	zbx_get_process_username(HANDLE hProcess, char *userName)
{
	HANDLE		tok;
	TOKEN_USER	*ptu = NULL;
	DWORD		sz = 0, nlen, dlen;
	TCHAR		name[MAX_NAME], dom[MAX_NAME];
	int		iUse, res = FAIL;

	assert(userName);

	/* clean result; */
	*userName = '\0';

	/* open the processes token */
	if (0 == OpenProcessToken(hProcess, TOKEN_QUERY, &tok))
		return res;

	/* Get required buffer size and allocate the TOKEN_USER buffer */
	if (0 == GetTokenInformation(tok, (TOKEN_INFORMATION_CLASS)1, (LPVOID)ptu, 0, &sz))
	{
		if (GetLastError() != ERROR_INSUFFICIENT_BUFFER)
			goto lbl_err;
		ptu = (PTOKEN_USER)zbx_malloc(ptu, sz);
	}

	/* Get the token user information from the access token. */
	if (0 == GetTokenInformation(tok, (TOKEN_INFORMATION_CLASS)1, (LPVOID)ptu, sz, &sz))
		goto lbl_err;

	/* get the account/domain name of the SID */
	nlen = MAX_NAME;
	dlen = MAX_NAME;
	if (0 == LookupAccountSid(NULL, ptu->User.Sid, name, &nlen, dom, &dlen, (PSID_NAME_USE)&iUse))
		goto lbl_err;

	zbx_unicode_to_utf8_static(name, userName, MAX_NAME);

	res = SUCCEED;
lbl_err:
	zbx_free(ptu);

	CloseHandle(tok);

	return res;
}

int	PROC_NUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	HANDLE	hProcess;
	DWORD	procList[MAX_PROCESSES], dwSize;
	int	i, proccount, max_proc_cnt,
		proc_ok = 0,
		user_ok = 0;
	char	procName[MAX_PATH],
		userName[MAX_PATH],
		baseName[MAX_PATH],
		uname[MAX_NAME];

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, procName, sizeof(procName)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, userName, sizeof(userName)))
		*userName = '\0';

	if (0 == EnumProcesses(procList, sizeof(DWORD) * MAX_PROCESSES, &dwSize))
		return SYSINFO_RET_FAIL;

	max_proc_cnt = dwSize / sizeof(DWORD);
	proccount = 0;

	for (i = 0; i < max_proc_cnt; i++)
	{
		proc_ok = 0;
		user_ok = 0;

		if (NULL != (hProcess = OpenProcess(PROCESS_QUERY_INFORMATION | PROCESS_VM_READ, FALSE, procList[i])))
		{
			if ('\0' != *procName)
			{
				if (SUCCEED == zbx_get_processname(hProcess, baseName))
					if (0 == stricmp(baseName, procName))
						proc_ok = 1;
			}
			else
				proc_ok = 1;

			if (0 != proc_ok && '\0' != *userName)
			{
				if (SUCCEED == zbx_get_process_username(hProcess, uname))
					if (0 == stricmp(uname, userName))
						user_ok = 1;
			}
			else
				user_ok = 1;

			if (0 != user_ok && 0 != proc_ok)
				proccount++;

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

static int	GetProcessAttribute(HANDLE hProcess,int attr,int type,int count,double *lastValue)
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
	switch (type) {
	case 0:	/* min */
		if (count == 0 || value < *lastValue)
			*lastValue = value;
		break;
	case 1:	/* max */
		if (count == 0 || value > *lastValue)
			*lastValue = value;
		break;
	case 2:	/* avg */
		*lastValue = (*lastValue * count + value) / (count + 1);
		break;
	case 3:	/* sum */
		*lastValue += value;
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


int	PROC_INFO(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	DWORD		*procList, dwSize;
	HANDLE		hProcess;
	char		proc_name[MAX_PATH],
			attr[MAX_PATH],
			type[MAX_PATH],
			baseName[MAX_PATH];
	const char	*attrList[] = {"vmsize", "wkset", "pf", "ktime", "utime", "gdiobj", "userobj", "io_read_b", "io_read_op",
					"io_write_b", "io_write_op", "io_other_b", "io_other_op", NULL},
			*typeList[] = {"min", "max", "avg", "sum", NULL};
	double		value;
	int		i, proc_cnt, counter, attr_id, type_id, ret = SYSINFO_RET_OK;

	if (num_param(param) > 3)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, proc_name, sizeof(proc_name)))
		*proc_name = '\0';

	if ('\0' == *proc_name)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, attr, sizeof(attr)))
		*attr = '\0';

	if ('\0' == *attr)	/* default parameter */
		zbx_snprintf(attr, sizeof(attr), "%s", attrList[0]);

	if (0 != get_param(param, 3, type, sizeof(type)))
		*type = '\0';

	if ('\0' == *type)	/* default parameter */
		zbx_snprintf(type, sizeof(type), "%s", typeList[2]);

	/* Get attribute code from string */
	for (attr_id = 0; NULL != attrList[attr_id] && 0 != strcmp(attrList[attr_id], attr); attr_id++)
		;

	if (NULL == attrList[attr_id])     /* Unsupported attribute */
		return SYSINFO_RET_FAIL;

	/* Get type code from string */
	for (type_id = 0; NULL != typeList[type_id] && 0 != strcmp(typeList[type_id], type); type_id++)
		;

	if (NULL == typeList[type_id])
		return SYSINFO_RET_FAIL;     /* Unsupported type */

	procList = (DWORD *)malloc(MAX_PROCESSES * sizeof(DWORD));

	EnumProcesses(procList, sizeof(DWORD) * MAX_PROCESSES, &dwSize);

	proc_cnt = dwSize / sizeof(DWORD);
	counter = 0;
	value = 0;

	for (i = 0; i < proc_cnt; i++)
	{
		if (NULL != (hProcess = OpenProcess(PROCESS_QUERY_INFORMATION | PROCESS_VM_READ,FALSE, procList[i])))
		{
			if (SUCCEED == zbx_get_processname(hProcess, baseName))
				if (0 == stricmp(baseName, proc_name))
					if (SYSINFO_RET_OK != (ret = GetProcessAttribute(hProcess, attr_id, type_id, counter++, &value)))
						break;
			CloseHandle(hProcess);
		}
	}

	free(procList);

	if (SYSINFO_RET_OK == ret)
		SET_DBL_RESULT(result, value);

	return ret;
}
