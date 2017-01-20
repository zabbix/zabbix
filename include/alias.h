/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#ifndef ZABBIX_ALIAS_H
#define ZABBIX_ALIAS_H

#define MAX_ALIAS_NAME        120

typedef struct zbx_alias
{
	struct zbx_alias	*next;
	char			name[MAX_ALIAS_NAME];
	char			*value;
}
ALIAS;

void		test_aliases();
void		add_alias(const char *name, const char *value);
void		alias_list_free();
const char	*zbx_alias_get(const char *orig);

#endif	/* ZABBIX_ALIAS_H */
