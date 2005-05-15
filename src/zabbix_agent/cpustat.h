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

#ifndef ZABBIX_CPUSTAT_H
#define ZABBIX_CPUSTAT_H

#define CPUSTAT struct cpustat_type
CPUSTAT
{
	char    *device;
	int	major;
	int	diskno;
	int	clock[60*15];
	float	cpu_user[60*15];
	float	cpu_system[60*15];
	float	cpu_nice[60*15];
	float	cpu_idle[60*15];
};

void	collect_stats_cpustat(FILE *outfile);

#endif
