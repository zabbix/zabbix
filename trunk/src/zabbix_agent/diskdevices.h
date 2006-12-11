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

#ifndef ZABBIX_DISKDEVICES_H
#define ZABBIX_DISKDEVICES_H


#define	MAX_DISKDEVICES	8

typedef struct c_single_diskdevice_data
{
	char    *name;
	int	major;
	int	diskno;
	int	clock[60*15];
	float	read_io_ops[60*15];
	float	blks_read[60*15];
	float	write_io_ops[60*15];
	float	blks_write[60*15];
} ZBX_SINGLE_DISKDEVICE_DATA;

typedef struct c_diskdevices_data
{
	ZBX_SINGLE_DISKDEVICE_DATA device[MAX_DISKDEVICES];
} ZBX_DISKDEVICES_DATA;

void	collect_stats_diskdevices(ZBX_DISKDEVICES_DATA *pdiskdevices);

/*
#define	MAX_DISKDEVICES	8

#define DISKDEVICE struct diskdevice_type
DISKDEVICE
{
	char    *device;
	int	major;
	int	diskno;
	int	clock[60*15];
	float	read_io_ops[60*15];
	float	blks_read[60*15];
	float	write_io_ops[60*15];
	float	blks_write[60*15];
};

void	collect_stats_diskdevices(FILE *outfile);
*/
#endif
