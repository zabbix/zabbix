/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxsysinfo.h"
#include "../sysinfo.h"

#include "zbxjson.h"
#include "zbxalgo.h"
#include "zbxstr.h"
#include "zbxwin32.h"

#include <tlhelp32.h>
#include <sddl.h> /* ConvertSidToStringSid */

#define MAX_NAME	256

typedef struct
{
	unsigned int	pid;
	unsigned int	ppid;
	unsigned int	tid;

	char		*name;
	zbx_uint64_t	processes;
	zbx_uint64_t	threads;
	zbx_int64_t	handles;

	char		*user;
	char		*sid;

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
}
proc_data_t;

ZBX_PTR_VECTOR_DECL(proc_data_ptr, proc_data_t *)
ZBX_PTR_VECTOR_IMPL(proc_data_ptr, proc_data_t *)

/* function 'zbx_get_process_username' require 'userName' with size 'MAX_NAME' */
static int	zbx_get_process_username(HANDLE hProcess, char *userName, char *sid)
{
	HANDLE		tok;
	TOKEN_USER	*ptu = NULL;
	DWORD		sz = 0, nlen, dlen;
	wchar_t		name[MAX_NAME], dom[MAX_NAME], *sid_string;
	int		iUse, res = FAIL;

	/* clean result; */
	*userName = '\0';

	if (NULL != sid)
		*sid = '\0';

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

	if (NULL != sid)
	{
		if (TRUE == ConvertSidToStringSid(ptu->User.Sid, &sid_string))
		{
			zbx_unicode_to_utf8_static(sid_string, sid, MAX_NAME);
			LocalFree(sid_string);
		}
		else
			goto lbl_err;
	}

	zbx_unicode_to_utf8_static(name, userName, MAX_NAME);

	res = SUCCEED;
lbl_err:
	zbx_free(ptu);

	CloseHandle(tok);

	return res;
}

int	proc_num(AGENT_REQUEST *request, AGENT_RESULT *result)
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

			if (NULL == hProcess || SUCCEED != zbx_get_process_username(hProcess, uname, NULL) ||
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
			if (NULL == zbx_get_GetGuiResources())
				return SYSINFO_RET_FAIL;

			value = (double)(*zbx_get_GetGuiResources())(hProcess, 5 == attr ? 0 : 1);
			break;
		case 7:        /* io_read_b */
			if (NULL == zbx_get_GetProcessIoCounters())
				return SYSINFO_RET_FAIL;

			(*zbx_get_GetProcessIoCounters())(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.ReadTransferCount);
			break;
		case 8:        /* io_read_op */
			if (NULL == zbx_get_GetProcessIoCounters())
				return SYSINFO_RET_FAIL;

			(*zbx_get_GetProcessIoCounters())(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.ReadOperationCount);
			break;
		case 9:        /* io_write_b */
			if (NULL == zbx_get_GetProcessIoCounters())
				return SYSINFO_RET_FAIL;

			(*zbx_get_GetProcessIoCounters())(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.WriteTransferCount);
			break;
		case 10:       /* io_write_op */
			if (NULL == zbx_get_GetProcessIoCounters())
				return SYSINFO_RET_FAIL;

			(*zbx_get_GetProcessIoCounters())(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.WriteOperationCount);
			break;
		case 11:       /* io_other_b */
			if (NULL == zbx_get_GetProcessIoCounters())
				return SYSINFO_RET_FAIL;

			(*zbx_get_GetProcessIoCounters())(hProcess, &ioCounters);
			value = (double)((__int64)ioCounters.OtherTransferCount);
			break;
		case 12:       /* io_other_op */
			if (NULL == zbx_get_GetProcessIoCounters())
				return SYSINFO_RET_FAIL;

			(*zbx_get_GetProcessIoCounters())(hProcess, &ioCounters);
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
int	proc_info(AGENT_REQUEST *request, AGENT_RESULT *result)
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

static void	proc_data_free(proc_data_t *proc_data)
{
	zbx_free(proc_data->name);
	zbx_free(proc_data->user);
	zbx_free(proc_data->sid);
	zbx_free(proc_data);
}

int	proc_get(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#define SUM_PROC_VALUE_DBL(param)					\
	do								\
	{								\
		if (0.0 <= proc_data->param && 0.0 <= pdata_cmp->param)	\
			proc_data->param += pdata_cmp->param;		\
		else if (0.0 <= proc_data->param)			\
			proc_data->param = -1.0;			\
	} while(0)

	int				zbx_proc_mode;
	struct zbx_json			j;
	HANDLE				hProcessSnap, hThreadSnap;
	PROCESSENTRY32			pe32;
	DWORD				access;
	const OSVERSIONINFOEX		*vi;
	char				*param, *procName, *userName, *procComm, baseName[MAX_PATH], uname[MAX_NAME],
					sid[MAX_NAME];
	proc_data_t			*proc_data;
	zbx_vector_proc_data_ptr_t	proc_data_ctx;

	if (4 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	procName = get_rparam(request, 0);
	userName = get_rparam(request, 1);
	procComm = get_rparam(request, 2);
	param = get_rparam(request, 3);

	if (NULL != procComm && '\0' != *procComm)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "process"))
	{
		zbx_proc_mode = ZBX_PROC_MODE_PROCESS;
	}
	else if (0 == strcmp(param, "thread"))
	{
		zbx_proc_mode = ZBX_PROC_MODE_THREAD;
	}
	else if (0 == strcmp(param, "summary"))
	{
		zbx_proc_mode = ZBX_PROC_MODE_SUMMARY;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
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

	if (INVALID_HANDLE_VALUE == (hProcessSnap = CreateToolhelp32Snapshot(TH32CS_SNAPPROCESS, 0)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain system information."));
		return SYSINFO_RET_FAIL;
	}

	pe32.dwSize = sizeof(PROCESSENTRY32);

	if (FALSE == Process32First(hProcessSnap, &pe32) || (ZBX_PROC_MODE_THREAD == zbx_proc_mode &&
			INVALID_HANDLE_VALUE == (hThreadSnap = CreateToolhelp32Snapshot(TH32CS_SNAPTHREAD, 0))))
	{
		CloseHandle(hProcessSnap);
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain system information."));
		return SYSINFO_RET_FAIL;
	}

	zbx_vector_proc_data_ptr_create(&proc_data_ctx);

	do
	{
		HANDLE	hProcess;
		int	ret_usr;

		zbx_unicode_to_utf8_static(pe32.szExeFile, baseName, MAX_NAME);

		if (NULL != procName && '\0' != *procName && 0 != stricmp(baseName, procName))
			continue;

		if (NULL == (hProcess = OpenProcess(access, FALSE, pe32.th32ProcessID)))
			continue;

		ret_usr = zbx_get_process_username(hProcess, uname, sid);

		if (NULL != userName && '\0' != *userName && (SUCCEED != ret_usr || 0 != stricmp(uname, userName)))
			goto next;

		if (ZBX_PROC_MODE_THREAD == zbx_proc_mode)
		{
			THREADENTRY32	te32;

			te32.dwSize = sizeof(THREADENTRY32);

			if (FALSE == Thread32First(hThreadSnap, &te32))
				goto next;

			do
			{
				if (te32.th32OwnerProcessID == pe32.th32ProcessID)
				{
					proc_data = (proc_data_t *)zbx_malloc(NULL, sizeof(proc_data_t));

					proc_data->pid = pe32.th32ProcessID;
					proc_data->ppid = pe32.th32ParentProcessID;
					proc_data->name = zbx_strdup(NULL, baseName);
					proc_data->tid = te32.th32ThreadID;
					proc_data->sid = zbx_strdup(NULL, SUCCEED == ret_usr ? sid : "-1");
					proc_data->user = zbx_strdup(NULL, SUCCEED == ret_usr ? uname : "-1");

					zbx_vector_proc_data_ptr_append(&proc_data_ctx, proc_data);
				}
			}
			while (TRUE == Thread32Next(hThreadSnap, &te32));
		}
		else
		{
			DWORD			handleCount;
			PROCESS_MEMORY_COUNTERS	mc;
			IO_COUNTERS		ioCounters;
			FILETIME		ftCreate, ftExit, ftKernel, ftUser;

			proc_data = (proc_data_t *)zbx_malloc(NULL, sizeof(proc_data_t));

			proc_data->pid = pe32.th32ProcessID;
			proc_data->ppid = pe32.th32ParentProcessID;
			proc_data->name = zbx_strdup(NULL, baseName);
			proc_data->threads = pe32.cntThreads;
			proc_data->sid = zbx_strdup(NULL, SUCCEED == ret_usr ? sid : "-1");
			proc_data->user = zbx_strdup(NULL, SUCCEED == ret_usr ? uname : "-1");

			if (FALSE != GetProcessHandleCount(hProcess, &handleCount))
				proc_data->handles = (zbx_uint64_t)handleCount;
			else
				proc_data->handles = -1;

			if (FALSE != GetProcessMemoryInfo(hProcess, &mc, sizeof(PROCESS_MEMORY_COUNTERS)))
			{
				proc_data->vmsize = (double)mc.PagefileUsage / 1024;
				proc_data->wkset = (double)mc.WorkingSetSize / 1024;
				proc_data->page_faults = (double)mc.PageFaultCount;
			}
			else
				proc_data->vmsize = proc_data->wkset = proc_data->page_faults = -1.0;

			if (FALSE != GetProcessTimes(hProcess, &ftCreate, &ftExit, &ftKernel, &ftUser))
			{
				proc_data->cputime_system = ConvertProcessTime(&ftKernel) / 1000.0;
				proc_data->cputime_user = ConvertProcessTime(&ftUser) / 1000.0;
			}
			else
				proc_data->cputime_system = proc_data->cputime_user = -1.0;

			if (NULL != zbx_get_GetProcessIoCounters() &&
					FALSE != (*zbx_get_GetProcessIoCounters())(hProcess, &ioCounters))
			{
				proc_data->io_read_b = (double)((__int64)ioCounters.ReadTransferCount);
				proc_data->io_read_op = (double)((__int64)ioCounters.ReadOperationCount);
				proc_data->io_write_b = (double)((__int64)ioCounters.WriteTransferCount);
				proc_data->io_write_op = (double)((__int64)ioCounters.WriteOperationCount);
				proc_data->io_other_b = (double)((__int64)ioCounters.OtherTransferCount);
				proc_data->io_other_op = (double)((__int64)ioCounters.OtherOperationCount);
			}
			else
			{
				proc_data->io_read_b = proc_data->io_read_op = proc_data->io_write_b =
						proc_data->io_write_op = proc_data->io_other_b =
						proc_data->io_other_op = -1.0;
			}

			zbx_vector_proc_data_ptr_append(&proc_data_ctx, proc_data);
		}
next:
		CloseHandle(hProcess);
	}
	while (TRUE == Process32Next(hProcessSnap, &pe32));

	if (ZBX_PROC_MODE_THREAD == zbx_proc_mode)
		CloseHandle(hThreadSnap);

	CloseHandle(hProcessSnap);

	if (ZBX_PROC_MODE_SUMMARY == zbx_proc_mode)
	{
		for (int i = 0; i < proc_data_ctx.values_num; i++)
		{
			proc_data = proc_data_ctx.values[i];
			proc_data->processes = 1;

			for (int k = i + 1; k < proc_data_ctx.values_num; k++)
			{
				proc_data_t	*pdata_cmp = proc_data_ctx.values[k];

				if (0 == strcmp(proc_data->name, pdata_cmp->name))
				{
					proc_data->processes++;
					proc_data->threads += pdata_cmp->threads;

					SUM_PROC_VALUE_DBL(vmsize);
					SUM_PROC_VALUE_DBL(wkset);
					SUM_PROC_VALUE_DBL(cputime_user);
					SUM_PROC_VALUE_DBL(cputime_system);
					SUM_PROC_VALUE_DBL(page_faults);
					SUM_PROC_VALUE_DBL(io_read_b);
					SUM_PROC_VALUE_DBL(io_write_b);
					SUM_PROC_VALUE_DBL(io_other_b);
					SUM_PROC_VALUE_DBL(io_read_op);
					SUM_PROC_VALUE_DBL(io_write_op);
					SUM_PROC_VALUE_DBL(io_other_op);

					if (0 <= proc_data->handles && 0 <= pdata_cmp->handles)
						proc_data->handles += pdata_cmp->handles;
					else if (0 <= proc_data->handles)
						proc_data->handles = -1;

					proc_data_free(pdata_cmp);
					zbx_vector_proc_data_ptr_remove(&proc_data_ctx, k--);
				}
			}
		}
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < proc_data_ctx.values_num; i++)
	{
		proc_data = proc_data_ctx.values[i];

		zbx_json_addobject(&j, NULL);

		if (ZBX_PROC_MODE_SUMMARY != zbx_proc_mode)
		{
			zbx_json_adduint64(&j, "pid", proc_data->pid);
			zbx_json_adduint64(&j, "ppid", proc_data->ppid);
		}

		zbx_json_addstring(&j, "name", proc_data->name, ZBX_JSON_TYPE_STRING);

		if (ZBX_PROC_MODE_SUMMARY != zbx_proc_mode)
		{
			zbx_json_addstring(&j, "user", proc_data->user, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "sid", proc_data->sid, ZBX_JSON_TYPE_STRING);
		}
		else
			zbx_json_adduint64(&j, "processes", proc_data->processes);

		if (ZBX_PROC_MODE_THREAD != zbx_proc_mode)
		{
			zbx_json_addint64(&j, "vmsize", (zbx_uint64_t)proc_data->vmsize);
			zbx_json_addint64(&j, "wkset", (zbx_uint64_t)proc_data->wkset);
			zbx_json_addfloat(&j, "cputime_user", proc_data->cputime_user);
			zbx_json_addfloat(&j, "cputime_system", proc_data->cputime_system);
			zbx_json_adduint64(&j, "threads", proc_data->threads);
			zbx_json_addint64(&j, "page_faults", (zbx_uint64_t)proc_data->page_faults);
			zbx_json_addint64(&j, "handles", (zbx_uint64_t)proc_data->handles);
			zbx_json_addint64(&j, "io_read_b", (zbx_uint64_t)proc_data->io_read_b);
			zbx_json_addint64(&j, "io_write_b", (zbx_uint64_t)proc_data->io_write_b);
			zbx_json_addint64(&j, "io_read_op", (zbx_uint64_t)proc_data->io_read_op);
			zbx_json_addint64(&j, "io_write_op", (zbx_uint64_t)proc_data->io_write_op);
			zbx_json_addint64(&j, "io_other_b", (zbx_uint64_t)proc_data->io_other_b);
			zbx_json_addint64(&j, "io_other_op", (zbx_uint64_t)proc_data->io_other_op);
		}
		else
			zbx_json_adduint64(&j, "tid", proc_data->tid);

		zbx_json_close(&j);
	}

	zbx_json_close(&j);
	zbx_vector_proc_data_ptr_clear_ext(&proc_data_ctx, proc_data_free);
	zbx_vector_proc_data_ptr_destroy(&proc_data_ctx);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
	zbx_json_free(&j);

	return SYSINFO_RET_OK;
#undef SUM_PROC_VALUE_DBL
}
