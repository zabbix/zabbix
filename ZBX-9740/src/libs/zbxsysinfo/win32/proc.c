/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#include <tlhelp32.h>

#include "symbols.h"
#include "log.h"

#define MAX_PROCESSES	4096
#define MAX_NAME	256

/* function 'zbx_get_process_username' require 'userName' with size 'MAX_NAME' */
static int	zbx_get_process_username(HANDLE hProcess, char *userName)
{
	HANDLE		tok;
	TOKEN_USER	*ptu = NULL;
	DWORD		sz = 0, nlen, dlen;
	wchar_t		name[MAX_NAME], dom[MAX_NAME];
	int		iUse, res = FAIL;

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

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	HANDLE			hProcessSnap, hProcess;
	PROCESSENTRY32		pe32;
	DWORD			access;
	const OSVERSIONINFOEX	*vi;
	int			proccount, proc_ok;
	char			*procName, *userName, baseName[MAX_PATH], uname[MAX_NAME];

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	procName = get_rparam(request, 0);
	userName = get_rparam(request, 1);

	if (INVALID_HANDLE_VALUE == (hProcessSnap = CreateToolhelp32Snapshot(TH32CS_SNAPPROCESS, 0)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain system information."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vi = zbx_win_getversion()))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot retrieve system version."));
		return SYSINFO_RET_FAIL;
	}

	if (6 > vi->dwMajorVersion)
	{
		/* PROCESS_QUERY_LIMITED_INFORMATION is not supported on Windows Server 2003 and XP */
		access = PROCESS_QUERY_INFORMATION;
	}
	else
		access = PROCESS_QUERY_LIMITED_INFORMATION;

	pe32.dwSize = sizeof(PROCESSENTRY32);

	if (FALSE == Process32First(hProcessSnap, &pe32))
	{
		CloseHandle(hProcessSnap);
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain system information."));
		return SYSINFO_RET_FAIL;
	}

	proccount = 0;

	do
	{
		proc_ok = 1;

		if (NULL != procName && '\0' != *procName)
		{
			zbx_unicode_to_utf8_static(pe32.szExeFile, baseName, MAX_NAME);

			if (0 != stricmp(baseName, procName))
				proc_ok = 0;
		}

		if (0 != proc_ok && NULL != userName && '\0' != *userName)
		{
			hProcess = OpenProcess(access, FALSE, pe32.th32ProcessID);

			if (NULL == hProcess || SUCCEED != zbx_get_process_username(hProcess, uname) ||
					0 != stricmp(uname, userName))
			{
				proc_ok = 0;
			}

			if (NULL != hProcess)
				CloseHandle(hProcess);
		}

		if (0 != proc_ok)
			proccount++;
	}
	while (TRUE == Process32Next(hProcessSnap, &pe32));

	CloseHandle(hProcessSnap);

	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}

/************ PROC INFO ****************/

/*
 * Convert process time from FILETIME structure (100-nanosecond units) to double (milliseconds)
 */

static double ConvertProcessTime(FILETIME *lpft)
{
	/* Convert 100-nanosecond units to milliseconds */
	return (double)((((__int64)lpft->dwHighDateTime << 32) | lpft->dwLowDateTime) / 10000);
}

/*
 * Get specific process attribute
 */

static int	GetProcessAttribute(HANDLE hProcess, int attr, int type, int count, double *lastValue)
{
	double			value;
	PROCESS_MEMORY_COUNTERS	mc;
	IO_COUNTERS		ioCounters;
	FILETIME		ftCreate, ftExit, ftKernel, ftUser;

	/* Get value for current process instance */
	switch(attr)
	{
		case 0:        /* vmsize */
			GetProcessMemoryInfo(hProcess, &mc, sizeof(PROCESS_MEMORY_COUNTERS));
			value = (double)mc.PagefileUsage / 1024;   /* Convert to Kbytes */
			break;
		case 1:        /* wkset */
			GetProcessMemoryInfo(hProcess, &mc, sizeof(PROCESS_MEMORY_COUNTERS));
			value = (double)mc.WorkingSetSize / 1024;   /* Convert to Kbytes */
			break;
		case 2:        /* pf */
			GetProcessMemoryInfo(hProcess, &mc, sizeof(PROCESS_MEMORY_COUNTERS));
			value = (double)mc.PageFaultCount;
			break;
		case 3:        /* ktime */
		case 4:        /* utime */
			GetProcessTimes(hProcess, &ftCreate, &ftExit, &ftKernel, &ftUser);
			value = ConvertProcessTime(3 == attr ? &ftKernel : &ftUser);
			break;
		case 5:        /* gdiobj */
		case 6:        /* userobj */
			if(NULL == zbx_GetGuiResources)
				return SYSINFO_RET_FAIL;

			value = (double)zbx_GetGuiResources(hProcess, 5 == attr ? 0 : 1);
			break;
		case 7:        /* io_read_b */
			if(NULL == zbx_GetProcessIoCounters)
				return SYSINFO_RET_FAIL;

			zbx_GetProcessIoCounters(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.ReadTransferCount);
			break;
		case 8:        /* io_read_op */
			if(NULL == zbx_GetProcessIoCounters)
				return SYSINFO_RET_FAIL;

			zbx_GetProcessIoCounters(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.ReadOperationCount);
			break;
		case 9:        /* io_write_b */
			if(NULL == zbx_GetProcessIoCounters)
				return SYSINFO_RET_FAIL;

			zbx_GetProcessIoCounters(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.WriteTransferCount);
			break;
		case 10:       /* io_write_op */
			if(NULL == zbx_GetProcessIoCounters)
				return SYSINFO_RET_FAIL;

			zbx_GetProcessIoCounters(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.WriteOperationCount);
			break;
		case 11:       /* io_other_b */
			if(NULL == zbx_GetProcessIoCounters)
				return SYSINFO_RET_FAIL;

			zbx_GetProcessIoCounters(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.OtherTransferCount);
			break;
		case 12:       /* io_other_op */
			if(NULL == zbx_GetProcessIoCounters)
				return SYSINFO_RET_FAIL;

			zbx_GetProcessIoCounters(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.OtherOperationCount);
			break;
		default:       /* Unknown attribute */
			return SYSINFO_RET_FAIL;
	}

	/* Recalculate final value according to selected type */
	switch (type)
	{
		case 0:	/* min */
			if (0 == count || value < *lastValue)
				*lastValue = value;
			break;
		case 1:	/* max */
			if (0 == count || value > *lastValue)
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
int	PROC_INFO(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	HANDLE			hProcessSnap, hProcess;
	PROCESSENTRY32		pe32;
	char			*proc_name, *attr, *type, baseName[MAX_PATH];
	const char		*attrList[] = {"vmsize", "wkset", "pf", "ktime", "utime", "gdiobj", "userobj",
						"io_read_b", "io_read_op", "io_write_b", "io_write_op", "io_other_b",
						"io_other_op", NULL},
				*typeList[] = {"min", "max", "avg", "sum", NULL};
	double			value;
	DWORD			access;
	const OSVERSIONINFOEX	*vi;
	int			counter, attr_id, type_id, ret = SYSINFO_RET_OK;

	if (3 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	proc_name = get_rparam(request, 0);
	attr = get_rparam(request, 1);
	type = get_rparam(request, 2);

	if (NULL == proc_name || '\0' == *proc_name)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	/* Get attribute code from string */
	if (NULL == attr || '\0' == *attr)
	{
		for (attr_id = 0; NULL != attrList[attr_id] && 0 != strcmp(attrList[attr_id], "vmsize"); attr_id++)
			;
	}
	else
	{
		for (attr_id = 0; NULL != attrList[attr_id] && 0 != strcmp(attrList[attr_id], attr); attr_id++)
			;
	}

	if (NULL == attrList[attr_id])     /* Unsupported attribute */
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	/* Get type code from string */
	if (NULL == type || '\0' == *type)
	{
		for (type_id = 0; NULL != typeList[type_id] && 0 != strcmp(typeList[type_id], "avg"); type_id++)
			;
	}
	else
	{
		for (type_id = 0; NULL != typeList[type_id] && 0 != strcmp(typeList[type_id], type); type_id++)
			;
	}

	if (NULL == typeList[type_id])	/* Unsupported type */
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (INVALID_HANDLE_VALUE == (hProcessSnap = CreateToolhelp32Snapshot(TH32CS_SNAPPROCESS, 0)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain system information."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (vi = zbx_win_getversion()))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot retrieve system version."));
		return SYSINFO_RET_FAIL;
	}

	if (6 > vi->dwMajorVersion)
	{
		/* PROCESS_QUERY_LIMITED_INFORMATION is not supported on Windows Server 2003 and XP */
		access = PROCESS_QUERY_INFORMATION;
	}
	else
		access = PROCESS_QUERY_LIMITED_INFORMATION;

	pe32.dwSize = sizeof(PROCESSENTRY32);

	if (FALSE == Process32First(hProcessSnap, &pe32))
	{
		CloseHandle(hProcessSnap);
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain system information."));
		return SYSINFO_RET_FAIL;
	}

	counter = 0;
	value = 0;

	do
	{
		zbx_unicode_to_utf8_static(pe32.szExeFile, baseName, MAX_NAME);

		if (0 == stricmp(baseName, proc_name))
		{
			if (NULL != (hProcess = OpenProcess(access, FALSE, pe32.th32ProcessID)))
			{
				ret = GetProcessAttribute(hProcess, attr_id, type_id, counter++, &value);

				CloseHandle(hProcess);

				if (SYSINFO_RET_OK != ret)
				{
					SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain process information."));
					break;
				}
			}
		}
	}
	while (TRUE == Process32Next(hProcessSnap, &pe32));

	CloseHandle(hProcessSnap);

	if (SYSINFO_RET_OK == ret)
		SET_DBL_RESULT(result, value);
	else
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain process information."));

	return ret;
}
