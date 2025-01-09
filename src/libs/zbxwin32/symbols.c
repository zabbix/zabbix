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

#include "zbxwin32.h"

#include "zbxlog.h"

static DWORD	(__stdcall *zbx_GetGuiResources)(HANDLE, DWORD) = NULL;
static BOOL	(__stdcall *zbx_GetProcessIoCounters)(HANDLE, PIO_COUNTERS) = NULL;
static BOOL	(__stdcall *zbx_GetPerformanceInfo)(PPERFORMANCE_INFORMATION, DWORD) = NULL;
static BOOL	(__stdcall *zbx_GlobalMemoryStatusEx)(LPMEMORYSTATUSEX) = NULL;
static BOOL	(__stdcall *zbx_GetFileInformationByHandleEx)(HANDLE, zbx_file_info_by_handle_class_t, LPVOID, DWORD) =
			NULL;

GetGuiResources_t	zbx_get_GetGuiResources(void)
{
	return zbx_GetGuiResources;
}

GetProcessIoCounters_t	zbx_get_GetProcessIoCounters(void)
{
	return zbx_GetProcessIoCounters;
}

GetPerformanceInfo_t	zbx_get_GetPerformanceInfo(void)
{
	return zbx_GetPerformanceInfo;
}

GlobalMemoryStatusEx_t	zbx_get_GlobalMemoryStatusEx(void)
{
	return zbx_GlobalMemoryStatusEx;
}

GetFileInformationByHandleEx_t	zbx_get_GetFileInformationByHandleEx(void)
{
	return zbx_GetFileInformationByHandleEx;
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
		zbx_GetGuiResources = (DWORD (__stdcall *)(HANDLE, DWORD))GetProcAddressAndLog(hModule, "GetGuiResources");
	else
		zabbix_log(LOG_LEVEL_DEBUG, "unable to get handle to USER32.DLL");

	if (NULL != (hModule = GetModuleHandle(TEXT("KERNEL32.DLL"))))
	{
		zbx_GetProcessIoCounters = (BOOL (__stdcall *)(HANDLE, PIO_COUNTERS))GetProcAddressAndLog(hModule,
				"GetProcessIoCounters");
		zbx_GlobalMemoryStatusEx = (BOOL (__stdcall *)(LPMEMORYSTATUSEX))GetProcAddressAndLog(hModule,
				"GlobalMemoryStatusEx");
		zbx_GetFileInformationByHandleEx = (BOOL (__stdcall *)(HANDLE, zbx_file_info_by_handle_class_t, LPVOID,
				DWORD))GetProcAddressAndLog(hModule, "GetFileInformationByHandleEx");
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "unable to get handle to KERNEL32.DLL");

	if (NULL != (hModule = GetModuleHandle(TEXT("PSAPI.DLL"))))
	{
		zbx_GetPerformanceInfo = (BOOL (__stdcall *)(PPERFORMANCE_INFORMATION, DWORD))
				GetProcAddressAndLog(hModule, "GetPerformanceInfo");

	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "unable to get handle to PSAPI.DLL");
}
