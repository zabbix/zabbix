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

#ifndef ZABBIX_CFG_H
#define ZABBIX_CFG_H

#define	TYPE_INT	0
#define	TYPE_STRING	1

#define	PARM_OPT	0
#define	PARM_MAND	1

//
// Application flags
//

#define AF_STANDALONE               0x0001
#define AF_USE_EVENT_LOG            0x0002
#define AF_LOG_UNRESOLVED_SYMBOLS   0x0004

extern int	CONFIG_ZABBIX_FORKS;
extern char	*CONFIG_FILE;

extern char	*CONFIG_LOG_FILE;
extern char	CONFIG_ALLOW_ROOT_PERMISSION;
extern int	CONFIG_TIMEOUT;

struct cfg_line
{
	char	*parameter;
	void	*variable;
	int	(*function)();
	int	type;
	int	mandatory;
	int	min;
	int	max;
};

int	parse_cfg_file(char *cfg_file,struct cfg_line *cfg);

#endif
