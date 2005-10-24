/*
 * ** ZABBIX
 * ** Copyright (C) 2000-2005 SIA Zabbix
 * **
 * ** This program is free software; you can redistribute it and/or modify
 * ** it under the terms of the GNU General Public License as published by
 * ** the Free Software Foundation; either version 2 of the License, or
 * ** (at your option) any later version.
 * **
 * ** This program is distributed in the hope that it will be useful,
 * ** but WITHOUT ANY WARRANTY; without even the implied warranty of
 * ** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * ** GNU General Public License for more details.
 * **
 * ** You should have received a copy of the GNU General Public License
 * ** along with this program; if not, write to the Free Software
 * ** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 * **/

#include "config.h"

#include <errno.h>

#include <string.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/stat.h>
#include <sys/types.h>

#include "common.h"
#include "sysinfo.h"

/*
#define FDI(f, m) fprintf(stderr, "DEBUG INFO: " f "\n" , m) // show debug info to stderr
#define SDI(m) FDI("%s", m) // string info
#define IDI(i) FDI("%i", i) // integer info
*/
int	PROC_MEMORY(const char *cmd, const char *param,double  *value, const char *msg, int mlen_max)
{
    /* in this moment this function for this platform unsupported */
    return	SYSINFO_RET_FAIL;
}

int	PROC_NUM(const char *cmd, const char *param,double  *value, const char *msg, int mlen_max)
{
    /* in this moment this function for this platform unsupported */
    return	SYSINFO_RET_FAIL;
}

