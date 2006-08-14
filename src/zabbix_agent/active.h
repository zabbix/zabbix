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

#ifndef ZABBIX_ACTIVE_H
#define ZABBIX_ACTIVE_H

extern char	*CONFIG_HOSTNAME;
extern int	CONFIG_REFRESH_ACTIVE_CHECKS;

#define MAX_LINES_PER_SECOND	10

#define METRIC struct metric_type
METRIC
{
	char	*key;
	int	refresh;
	int	nextcheck;
	int	status;
/* Must be long for fseek() */
/*	int	lastlogsize;*/
	long	lastlogsize;
};

pid_t   child_active_make(int i,char *server, int port);

#endif
