/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_CFG_H
#define ZABBIX_CFG_H

#define TYPE_INT		0
#define TYPE_STRING		1
#define TYPE_MULTISTRING	2
#define TYPE_UINT64		3

#define	PARM_OPT	0
#define	PARM_MAND	1

/* config file parsing options */
#define	ZBX_CFG_FILE_REQUIRED	0
#define	ZBX_CFG_FILE_OPTIONAL	1

#define	ZBX_CFG_NOT_STRICT	0
#define	ZBX_CFG_STRICT		1

extern char	*CONFIG_FILE;
extern char	*CONFIG_LOG_FILE;
extern int	CONFIG_ALLOW_ROOT;
extern int	CONFIG_TIMEOUT;

struct cfg_line
{
	char		*parameter;
	void		*variable;
	int		type;
	int		mandatory;
	zbx_uint64_t	min;
	zbx_uint64_t	max;
};

int	parse_cfg_file(const char *cfg_file, struct cfg_line *cfg, int optional, int strict);

#endif
