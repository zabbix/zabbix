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

extern int	CONFIG_ZABBIX_FORKS;	/* contains the number of listeners for processing passive checks */
extern char	*CONFIG_FILE;
extern char	*CONFIG_LOG_FILE;
extern char	CONFIG_ALLOW_ROOT;
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

int	parse_cfg_file(const char *cfg_file, struct cfg_line *cfg);
int	parse_opt_cfg_file(const char *cfg_file, struct cfg_line *cfg);

#endif
