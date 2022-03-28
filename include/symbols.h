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

#ifndef ZABBIX_SYMBOLS_H
#define ZABBIX_SYMBOLS_H

#include "sysinc.h"

#if defined(_WINDOWS) || defined(__MINGW32__)

/* some definitions which are not available on older MS Windows versions */
typedef enum {
	/* we only use below values, the rest of enumerated values are omitted here */
	zbx_FileBasicInfo	= 0,
	zbx_FileIdInfo		= 18
} ZBX_FILE_INFO_BY_HANDLE_CLASS;

typedef struct {
	LARGE_INTEGER	CreationTime;
	LARGE_INTEGER	LastAccessTime;
	LARGE_INTEGER	LastWriteTime;
	LARGE_INTEGER	ChangeTime;
	DWORD		FileAttributes;
} ZBX_FILE_BASIC_INFO;

typedef struct {
	ULONGLONG	LowPart;
	ULONGLONG	HighPart;
} ZBX_EXT_FILE_ID_128;

typedef struct {
	ULONGLONG		VolumeSerialNumber;
	ZBX_EXT_FILE_ID_128	FileId;
} ZBX_FILE_ID_INFO;

DWORD	(__stdcall *zbx_GetGuiResources)(HANDLE, DWORD);
BOOL	(__stdcall *zbx_GetProcessIoCounters)(HANDLE, PIO_COUNTERS);
BOOL	(__stdcall *zbx_GetPerformanceInfo)(PPERFORMANCE_INFORMATION, DWORD);
BOOL	(__stdcall *zbx_GlobalMemoryStatusEx)(LPMEMORYSTATUSEX);
BOOL	(__stdcall *zbx_GetFileInformationByHandleEx)(HANDLE, ZBX_FILE_INFO_BY_HANDLE_CLASS, LPVOID, DWORD);

void	import_symbols(void);

#else
#	define import_symbols()
#endif

#endif
