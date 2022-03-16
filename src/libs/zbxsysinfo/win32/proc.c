/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#include "zbxjson.h"
#include "zbxalgo.h"

#define MAX_PROCESSES	4096
#define MAX_NAME	256

typedef struct
{
	zbx_uint64_t	pid;
	zbx_uint64_t	ppid;
	zbx_uint64_t	tid;

	char		*name;
	zbx_uint64_t	processes;
	zbx_uint64_t	threads;
	zbx_uint64_t	handles;

	double		cputime_user;
	double		cputime_system;
	double		page_faults;
	double		io_read_b;
	double		io_write_b;
	double		io_other_b;
	double		io_read_op;
	double		io_write_op;
	double		io_other_op;

	double		vmsize;
	double		wkset;
	double		gdiobj;
	double		userobj;
}
zbx_proc_data_t;

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
	switch (attr)
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
			if (NULL == zbx_GetGuiResources)
				return SYSINFO_RET_FAIL;

			value = (double)zbx_GetGuiResources(hProcess, 5 == attr ? 0 : 1);
			break;
		case 7:        /* io_read_b */
			if (NULL == zbx_GetProcessIoCounters)
				return SYSINFO_RET_FAIL;

			zbx_GetProcessIoCounters(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.ReadTransferCount);
			break;
		case 8:        /* io_read_op */
			if (NULL == zbx_GetProcessIoCounters)
				return SYSINFO_RET_FAIL;

			zbx_GetProcessIoCounters(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.ReadOperationCount);
			break;
		case 9:        /* io_write_b */
			if (NULL == zbx_GetProcessIoCounters)
				return SYSINFO_RET_FAIL;

			zbx_GetProcessIoCounters(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.WriteTransferCount);
			break;
		case 10:       /* io_write_op */
			if (NULL == zbx_GetProcessIoCounters)
				return SYSINFO_RET_FAIL;

			zbx_GetProcessIoCounters(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.WriteOperationCount);
			break;
		case 11:       /* io_other_b */
			if (NULL == zbx_GetProcessIoCounters)
				return SYSINFO_RET_FAIL;

			zbx_GetProcessIoCounters(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.OtherTransferCount);
			break;
		case 12:       /* io_other_op */
			if (NULL == zbx_GetProcessIoCounters)
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

static void	proc_data_free(zbx_proc_data_t *proc_data)
{
	zbx_free(proc_data->name);
	zbx_free(proc_data);
}

static void	proc_free_proc_data(zbx_vector_ptr_t *proc_data)
{
	zbx_vector_ptr_clear_ext(proc_data, (zbx_mem_free_func_t)proc_data_free);
}

int	PROC_GET(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int			zbx_proc_mode, i;
	zbx_proc_data_t		*proc_data;
	zbx_vector_ptr_t	proc_data_ctx;
	struct zbx_json		j;
	HANDLE			hProcessSnap, hProcess;
	PROCESSENTRY32		pe32;
	DWORD			access;
	const OSVERSIONINFOEX	*vi;
	char			*param, *procName, *userName, *procComm, baseName[MAX_PATH], uname[MAX_NAME];

	if (4 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	procName = get_rparam(request, 0);
	userName = get_rparam(request, 1);
	procComm = get_rparam(request, 2);
	param = get_rparam(request, 3);

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "process"))
		zbx_proc_mode = ZBX_PROC_MODE_PROCESS;
	else if (0 == strcmp(param, "thread"))
		zbx_proc_mode = ZBX_PROC_MODE_THREAD;
	else if (0 == strcmp(param, "summary") && (NULL == procComm || '\0' == *procComm))
		zbx_proc_mode = ZBX_PROC_MODE_SUMMARY;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
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

	zbx_vector_ptr_create(&proc_data_ctx);

	do
	{
		zbx_unicode_to_utf8_static(pe32.szExeFile, baseName, MAX_NAME);

		if (NULL != procName && '\0' != *procName && 0 != stricmp(baseName, procName))
			goto next;

		if (NULL == (hProcess = OpenProcess(access, FALSE, pe32.th32ProcessID)))
			goto next;

		if (NULL != userName && '\0' != *userName && (SUCCEED != zbx_get_process_username(hProcess, uname) ||
				0 != stricmp(uname, userName)))
		{
			goto next;
		}

		if (ZBX_PROC_MODE_THREAD == zbx_proc_mode)
		{
			HANDLE		hThreadSnap;
			THREADENTRY32	te32;

			if (INVALID_HANDLE_VALUE == (hThreadSnap = CreateToolhelp32Snapshot(TH32CS_SNAPTHREAD, 0)))
				goto next;

			te32.dwSize = sizeof(THREADENTRY32);

			if (FALSE == Thread32First(hThreadSnap, &te32))
			{
				CloseHandle(hThreadSnap);
				goto next;
			}

			do
			{
				if (te32.th32OwnerProcessID == pe32.th32ProcessID)
				{
					proc_data = (zbx_proc_data_t *)zbx_malloc(NULL, sizeof(zbx_proc_data_t));
					memset(proc_data, 0, sizeof(zbx_proc_data_t));

					proc_data->pid = pe32.th32ProcessID;
					proc_data->ppid = pe32.th32ParentProcessID;
					proc_data->name = zbx_strdup(NULL, baseName);
					proc_data->tid = te32.th32ThreadID;

					zbx_vector_ptr_append(&proc_data_ctx, proc_data);
				}
			}
			while (TRUE == Thread32Next(hThreadSnap, &te32));

			CloseHandle(hThreadSnap);
		}
		else
		{
			DWORD	handleCount;

			proc_data = (zbx_proc_data_t *)zbx_malloc(NULL, sizeof(zbx_proc_data_t));
			memset(proc_data, 0, sizeof(zbx_proc_data_t));

			proc_data->pid = pe32.th32ProcessID;
			proc_data->ppid = pe32.th32ParentProcessID;
			proc_data->name = zbx_strdup(NULL, baseName);
			proc_data->threads = pe32.cntThreads;

			GetProcessHandleCount(hProcess, &handleCount);
			proc_data->handles = (zbx_uint64_t)handleCount;
			GetProcessAttribute(hProcess, 0, 0, 0, &proc_data->vmsize);
			GetProcessAttribute(hProcess, 1, 0, 0, &proc_data->wkset);
			GetProcessAttribute(hProcess, 2, 0, 0, &proc_data->page_faults);
			GetProcessAttribute(hProcess, 3, 0, 0, &proc_data->cputime_system);
			GetProcessAttribute(hProcess, 4, 0, 0, &proc_data->cputime_user);
			GetProcessAttribute(hProcess, 5, 0, 0, &proc_data->gdiobj);
			GetProcessAttribute(hProcess, 6, 0, 0, &proc_data->userobj);
			GetProcessAttribute(hProcess, 7, 0, 0, &proc_data->io_read_b);
			GetProcessAttribute(hProcess, 8, 0, 0, &proc_data->io_read_op);
			GetProcessAttribute(hProcess, 9, 0, 0, &proc_data->io_write_b);
			GetProcessAttribute(hProcess, 10, 0, 0, &proc_data->io_write_op);
			GetProcessAttribute(hProcess, 11, 0, 0, &proc_data->io_other_b);
			GetProcessAttribute(hProcess, 12, 0, 0, &proc_data->io_other_op);

			zbx_vector_ptr_append(&proc_data_ctx, proc_data);
		}
next:
		if (NULL != hProcess)
			CloseHandle(hProcess);
	}
	while (TRUE == Process32Next(hProcessSnap, &pe32));

	CloseHandle(hProcessSnap);

	if (ZBX_PROC_MODE_SUMMARY == zbx_proc_mode)
	{
		int	k;

		for (i = 0; i < proc_data_ctx.values_num; i++)
		{
			zbx_proc_data_t	*pdata = (zbx_proc_data_t *)proc_data_ctx.values[i];

			pdata->processes = 1;

			for (k = i + 1; k < proc_data_ctx.values_num; k++)
			{
				zbx_proc_data_t	*pdata_cmp = (zbx_proc_data_t *)proc_data_ctx.values[k];

				if (0 == strcmp(pdata->name, pdata_cmp->name))
				{
					pdata->processes++;
					pdata->vmsize += pdata_cmp->vmsize;
					pdata->wkset += pdata_cmp->wkset;
					pdata->gdiobj += pdata_cmp->gdiobj;
					pdata->userobj += pdata_cmp->userobj;
					pdata->cputime_user += pdata_cmp->cputime_user;
					pdata->cputime_system += pdata_cmp->cputime_system;
					pdata->threads += pdata_cmp->threads;
					pdata->handles += pdata_cmp->handles;
					pdata->page_faults += pdata_cmp->page_faults;
					pdata->io_read_b += pdata_cmp->io_read_b;
					pdata->io_write_b += pdata_cmp->io_write_b;
					pdata->io_other_b += pdata_cmp->io_other_b;
					pdata->io_read_op += pdata_cmp->io_read_op;
					pdata->io_write_op += pdata_cmp->io_write_op;
					pdata->io_other_op += pdata_cmp->io_other_op;

					proc_data_free(pdata_cmp);
					zbx_vector_ptr_remove(&proc_data_ctx, k--);
				}
			}
		}
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0; i < proc_data_ctx.values_num; i++)
	{
		zbx_proc_data_t	*pdata;

		pdata = (zbx_proc_data_t *)proc_data_ctx.values[i];

		zbx_json_addobject(&j, NULL);

		if (ZBX_PROC_MODE_SUMMARY != zbx_proc_mode)
		{
			zbx_json_adduint64(&j, "pid", pdata->pid);
			zbx_json_adduint64(&j, "ppid", pdata->ppid);
		}

		zbx_json_addstring(&j, "name", ZBX_NULL2EMPTY_STR(pdata->name), ZBX_JSON_TYPE_STRING);

		if (ZBX_PROC_MODE_SUMMARY == zbx_proc_mode)
			zbx_json_adduint64(&j, "processes", pdata->processes);

		if (ZBX_PROC_MODE_THREAD != zbx_proc_mode)
		{
			zbx_json_adduint64(&j, "vmsize", (zbx_uint64_t)pdata->vmsize);
			zbx_json_adduint64(&j, "wkset", (zbx_uint64_t)pdata->wkset);
			zbx_json_adduint64(&j, "cputime_user", (zbx_uint64_t)pdata->cputime_user);
			zbx_json_adduint64(&j, "cputime_system", (zbx_uint64_t)pdata->cputime_system);
			zbx_json_adduint64(&j, "threads", pdata->threads);
			zbx_json_adduint64(&j, "page_faults", (zbx_uint64_t)pdata->page_faults);
			zbx_json_adduint64(&j, "io_read_b", (zbx_uint64_t)pdata->io_read_b);
			zbx_json_adduint64(&j, "io_write_b", (zbx_uint64_t)pdata->io_write_b);
			zbx_json_adduint64(&j, "io_read_op", (zbx_uint64_t)pdata->io_read_op);
			zbx_json_adduint64(&j, "io_write_op", (zbx_uint64_t)pdata->io_write_op);
			zbx_json_adduint64(&j, "io_other_b", (zbx_uint64_t)pdata->io_other_b);
			zbx_json_adduint64(&j, "io_other_op", (zbx_uint64_t)pdata->io_other_op);
			zbx_json_adduint64(&j, "gdiobj", (zbx_uint64_t)pdata->gdiobj);
			zbx_json_adduint64(&j, "userobj", (zbx_uint64_t)pdata->userobj);
		}
		else
			zbx_json_adduint64(&j, "tid", pdata->tid);

		zbx_json_close(&j);
	}

	zbx_json_close(&j);
	proc_free_proc_data(&proc_data_ctx);
	zbx_vector_ptr_destroy(&proc_data_ctx);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
	zbx_json_free(&j);

	return SYSINFO_RET_OK;
}
