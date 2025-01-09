/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#ifndef ZABBIX_PROF_H
#define ZABBIX_PROF_H

#define ZBX_PROF_UNKNOWN	0x00
#define ZBX_PROF_PROCESSING	0x01
#define ZBX_PROF_RWLOCK		0x02
#define ZBX_PROF_MUTEX		0x04
#define ZBX_PROF_ALL		0xff

typedef int zbx_prof_scope_t;

void	zbx_prof_enable(zbx_prof_scope_t scope);
void	zbx_prof_disable(void);
void	zbx_prof_start(const char *func_name, zbx_prof_scope_t scope);
void	zbx_prof_end_wait(void);
void	zbx_prof_end(void);
void	zbx_prof_update(const char *info, double time_now);

#endif
