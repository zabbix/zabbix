/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxwin32.h"

#include "log.h"

static DWORD	(__stdcall *GetGuiResources)(HANDLE, DWORD) = NULL;
static BOOL	(__stdcall *GetProcessIoCounters)(HANDLE, PIO_COUNTERS) = NULL;
static BOOL	(__stdcall *GetPerformanceInfo)(PPERFORMANCE_INFORMATION, DWORD) = NULL;
static BOOL	(__stdcall *GlobalMemoryStatusEx)(LPMEMORYSTATUSEX) = NULL;
static BOOL	(__stdcall *GetFileInformationByHandleEx)(HANDLE, zbx_file_info_by_handle_class_t, LPVOID, DWORD) = NULL;

GetGuiResources*        zbx_get_GetGuidResources(void)
{
	return GetGuidResources; 
}

GetProcessIoCounters*   zbx_get_GetProcessIoCounters(void)
{
	return GetProcessIoCounters;
}

GetPerformanceInfo*     zbx_get_GetPerformanceInfo(void)
{
	return GetPerformanceInfo;
}

GetGlobalMemoryStatusEx*        zbx_get_GetGlobalMemoryStatusEx(void)
{
	return GetGlobalMemoryStatusEx;
}

GetFileInformationByHandleEx*   zbx_get_GetFileInformationByHandleEx(void)
{
	return GetFileInformationByHandleEx;
}

static FARPROC	GetProcAddressAndLog(HMODULE hModule, const char *procName)
{
	FARPROC	ptr;

	if (NULL == (ptr = GetProcAddress(hModule, procName)))
		zabbix_log(LOG_LEVEL_DEBUG, "unable to resolve symbol '%s'", procName);

	return ptr;
}

void	zbx_import_symbols(void)
{
	HMODULE	hModule;

	if (NULL != (hModule = GetModuleHandle(TEXT("USER32.DLL"))))
		GetGuiResources = (DWORD (__stdcall *)(HANDLE, DWORD))GetProcAddressAndLog(hModule, "GetGuiResources");
	else
		zabbix_log(LOG_LEVEL_DEBUG, "unable to get handle to USER32.DLL");

	if (NULL != (hModule = GetModuleHandle(TEXT("KERNEL32.DLL"))))
	{
		GetProcessIoCounters = (BOOL (__stdcall *)(HANDLE, PIO_COUNTERS))GetProcAddressAndLog(hModule, "GetProcessIoCounters");
		GlobalMemoryStatusEx = (BOOL (__stdcall *)(LPMEMORYSTATUSEX))GetProcAddressAndLog(hModule, "GlobalMemoryStatusEx");
		GetFileInformationByHandleEx = (BOOL (__stdcall *)(HANDLE, zbx_file_info_by_handle_class_t, LPVOID,
				DWORD))GetProcAddressAndLog(hModule, "GetFileInformationByHandleEx");
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "unable to get handle to KERNEL32.DLL");

	if (NULL != (hModule = GetModuleHandle(TEXT("PSAPI.DLL"))))
		GetPerformanceInfo = (BOOL (__stdcall *)(PPERFORMANCE_INFORMATION, DWORD))GetProcAddressAndLog(hModule, "GetPerformanceInfo");
	else
		zabbix_log(LOG_LEVEL_DEBUG, "unable to get handle to PSAPI.DLL");
}
