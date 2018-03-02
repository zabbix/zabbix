/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#ifndef ZABBIX_EXPORT_H
#define ZABBIX_EXPOT_H

void	zbx_problems_export_init(const char *process_name, int process_num);
int	zbx_problems_export_write(const char *buf, size_t count);
void	zbx_problems_export_flush(void);

void	zbx_history_export_init(const char *process_name, int process_num);
int	zbx_history_export_write(const char *buf, size_t count);
void	zbx_history_export_flush(void);
int	zbx_trends_export_write(const char *buf, size_t count);
void	zbx_trends_export_flush(void);


#endif
