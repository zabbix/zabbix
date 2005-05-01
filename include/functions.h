/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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


#ifndef ZABBIX_FUNCTIONS_H
#define ZABBIX_FUNCTIONS_H

#include "common.h"
#include "db.h"

#define	EVALUATE_FUNCTION_NORMAL	0
#define	EVALUATE_FUNCTION_SUFFIX	1

void    update_triggers (int itemid);
int	get_lastvalue(char *value,char *host,char *key,char *function,char *parameter);
int	process_data(int sockfd,char *server,char *key, char *value);
void	process_new_value(DB_ITEM *item,char *value);
int	send_list_of_active_checks(int sockfd, char *host);

#ifdef ZABBIX_THREADS
void	update_triggers_thread(MYSQL *database, int itemid);
void	process_new_value_thread(MYSQL *database, DB_ITEM *item,char *value);
void	update_services_thread(MYSQL *database, int triggerid, int status);
void	update_serv_thread(MYSQL *database,int serviceid);
void	apply_actions_thread(MYSQL *database, DB_TRIGGER *trigger,int trigger_value);
void	send_to_user_thread(MYSQL *database, DB_TRIGGER *trigger,DB_ACTION *action);
void	update_functions_thread(MYSQL *database, DB_ITEM *item);
int	get_lastvalue_thread(MYSQL *database, char *value,char *host,char *key,char *function,char *parameter);
#endif

#endif
