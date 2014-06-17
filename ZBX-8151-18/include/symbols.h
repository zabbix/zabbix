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

#ifndef ZABBIX_SYMBOLS_H
#define ZABBIX_SYMBOLS_H

#if defined (_WINDOWS)

DWORD	(__stdcall *zbx_GetGuiResources)(HANDLE,DWORD);
BOOL	(__stdcall *zbx_GetProcessIoCounters)(HANDLE,PIO_COUNTERS);
BOOL	(__stdcall *zbx_GetPerformanceInfo)(PPERFORMANCE_INFORMATION,DWORD);
BOOL	(__stdcall *zbx_GlobalMemoryStatusEx)(LPMEMORYSTATUSEX);

void import_symbols(void);

#else /* not _WINDOWS */

#	define import_symbols()

#endif /* _WINDOWS */

#endif /* ZABBIX_SYMBOLS_H */
