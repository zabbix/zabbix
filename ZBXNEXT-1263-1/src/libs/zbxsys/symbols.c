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
#include "symbols.h"

#include "log.h"

DWORD	(__stdcall *zbx_GetGuiResources)(HANDLE, DWORD) = NULL;
BOOL	(__stdcall *zbx_GetProcessIoCounters)(HANDLE, PIO_COUNTERS) = NULL;
BOOL	(__stdcall *zbx_GetPerformanceInfo)(PPERFORMANCE_INFORMATION, DWORD) = NULL;
BOOL	(__stdcall *zbx_GlobalMemoryStatusEx)(LPMEMORYSTATUSEX) = NULL;
BOOL	(__stdcall *zbx_GetFileInformationByHandleEx)(HANDLE, FILE_INFO_BY_HANDLE_CLASS, LPVOID, DWORD) = NULL;

static FARPROC	GetProcAddressAndLog(HMODULE hModule, const char *procName)
{
	FARPROC	ptr;

	if (NULL == (ptr = GetProcAddress(hModule, procName)))
		zabbix_log(LOG_LEVEL_DEBUG, "unable to resolve symbol '%s'", procName);

	return ptr;
}

void	import_symbols()
{
	HMODULE	hModule;

	if (NULL != (hModule = GetModuleHandle(TEXT("USER32.DLL"))))
		zbx_GetGuiResources = (DWORD (__stdcall *)(HANDLE, DWORD))GetProcAddressAndLog(hModule, "GetGuiResources");
	else
		zabbix_log(LOG_LEVEL_DEBUG, "unable to get handle to USER32.DLL");

	if (NULL != (hModule = GetModuleHandle(TEXT("KERNEL32.DLL"))))
	{
		zbx_GetProcessIoCounters = (BOOL (__stdcall *)(HANDLE, PIO_COUNTERS))GetProcAddressAndLog(hModule, "GetProcessIoCounters");
		zbx_GlobalMemoryStatusEx = (BOOL (__stdcall *)(LPMEMORYSTATUSEX))GetProcAddressAndLog(hModule, "GlobalMemoryStatusEx");
		zbx_GetFileInformationByHandleEx = (BOOL (__stdcall *)(HANDLE, FILE_INFO_BY_HANDLE_CLASS, LPVOID,
				DWORD))GetProcAddressAndLog(hModule, "GetFileInformationByHandleEx");
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "unable to get handle to KERNEL32.DLL");

	if (NULL != (hModule = GetModuleHandle(TEXT("PSAPI.DLL"))))
		zbx_GetPerformanceInfo = (BOOL (__stdcall *)(PPERFORMANCE_INFORMATION, DWORD))GetProcAddressAndLog(hModule, "GetPerformanceInfo");
	else
		zabbix_log(LOG_LEVEL_DEBUG, "unable to get handle to PSAPI.DLL");
}
