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

#endif
