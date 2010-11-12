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

#ifndef ZABBIX_PERFMON_H
#define ZABBIX_PERFMON_H

#if !defined(_WINDOWS)
#	error "This module allowed only for Windows OS"
#endif /* _WINDOWS */

//
// Performance Counter Indexes
//

#define PCI_SYSTEM			(2)
#define PCI_PROCESSOR			(238)
#define PCI_PROCESSOR_TIME		(6)
#define PCI_PROCESSOR_QUEUE_LENGTH	(44)
#define PCI_SYSTEM_UP_TIME		(674)

//
// Performance Countername structure
//

#define MAX_PERFCOUNTER_NAME_LEN      (108)	/* old naming <MAX_COUNTER_NAME> */

struct perfcounter
{
	struct perfcounter *next;
	unsigned long	pdhIndex;
	char		name[MAX_PERFCOUNTER_NAME_LEN];	
	/* must be caracter array! if you want to rewrite */
	/* to dinacal memory allocation CHECK for usage   */
	/* of sizeof function                             */
};

typedef struct perfcounter PERFCOUNTER;

extern PERFCOUNTER *PerfCounterList;

char *GetCounterName(unsigned long index);

#endif /* ZABBIX_PERFMON_H */
