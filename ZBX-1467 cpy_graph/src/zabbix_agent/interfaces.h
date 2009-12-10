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

#ifndef ZABBIX_INTERFACES_H
#define ZABBIX_INTERFACES_H


#define	MAX_INTERFACE	(16)

typedef struct s_single_interface_data
{
	char    *name;
	int	clock[60*15];
	double	sent[60*15];
	double	received[60*15];
} ZBX_SINGLE_INTERFACE_DATA;

typedef struct s_interfaces_data
{
	ZBX_SINGLE_INTERFACE_DATA intfs[MAX_INTERFACE];
} ZBX_INTERFACES_DATA;

void	collect_stats_interfaces(ZBX_INTERFACES_DATA *pinterfaces);

/*
#define	MAX_INTERFACE	16

#define INTERFACE struct interface_type
INTERFACE
{
	char    *interface;
	int	clock[60*15];
	double	sent[60*15];
	double	received[60*15];
};

void	collect_stats_interfaces(FILE *outfile);
*/
#endif
