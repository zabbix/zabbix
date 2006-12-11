/* 
** Zabbix Subagent API
** Copyright (C) 2003 Victor Kirhenshtein
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
**
**/

#ifndef _zabbix_subagent_h_
#define _zabbix_subagent_h_

/*
** Subagent exportables call specs
*/

#ifdef _WIN32
#define __zabbix_api __cdecl
#else
#define __zabbix_api
#endif


#define MAX_CMDNAME	256


/*
** Subagent command definition structure
*/

typedef struct
{
   char name[MAX_CMDNAME];
   int (__zabbix_api * handler_float)(char *,double *); /* Handler if return value is floating point numeric */
   int (__zabbix_api * handler_string)(char *,char **); /* Handler if return value is string */
} SUBAGENT_COMMAND;


/*
** Return codes for command handlers
*/

#define SYSINFO_RC_SUCCESS       0
#define SYSINFO_RC_NOTSUPPORTED  1
#define SYSINFO_RC_ERROR         2


/*
** Wrappers for memory allocation functions
*/

#ifdef _WIN32
#define zmalloc(x) HeapAlloc(GetProcessHeap(),0,x)
#define zrealloc(x,y) HeapReAlloc(GetProcessHeap(),0,x,y)
#define zfree(x) HeapFree(GetProcessHeap(),0,x)
#define zstrdup(x) strcpy((char *)HeapAlloc(GetProcessHeap(),0,strlen(x)+1),x)
#else
#define zmalloc(x) malloc(x)
#define zrealloc(x,y) realloc(x,y)
#define zfree(x) free(x)
#define zstrdup(x) strdup(x)
#endif


#endif
