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


#ifndef ZABBIX_OPERATIONS_H
#define ZABBIX_OPERATIONS_H

#include "common.h"
#include "db.h"

void	op_template_add(DB_EVENT *event, DB_ACTION *action, DB_OPERATION *operation);
void	op_template_del(DB_EVENT *event, DB_ACTION *action, DB_OPERATION *operation);
void	op_group_add(DB_EVENT *event, DB_OPERATION *operation);
void	op_group_del(DB_EVENT *event, DB_ACTION *action, DB_OPERATION *operation);
void	op_host_add(DB_EVENT *event);
void	op_host_del(DB_EVENT *event);
void	op_host_enable(DB_EVENT *event);
void	op_host_disable(DB_EVENT *event);
void    op_run_commands(char *cmd_list);
/*int	check_user_active(zbx_uint64_t userid);
void	op_notify_user(DB_EVENT *event, DB_ACTION *action, DB_OPERATION *operation);*/

#endif
