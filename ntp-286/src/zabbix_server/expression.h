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


#ifndef ZABBIX_EXPRESSION_H
#define ZABBIX_EXPRESSION_H

#include "common.h"
#include "db.h"

int	cmp_double(double a,double b);
int	find_char(char *str,char c);
int	evaluate_expression(int *result,char **expression, int triggger_value, char *error, int maxerrlen);
void	substitute_macros(DB_EVENT *event, DB_ACTION *action, char **data);
void	delete_reol(char *c);

#define MACRO_TYPE_TRIGGER_DESCRIPTION	1
#define MACRO_TYPE_MESSAGE_SUBJECT	2
#define MACRO_TYPE_MESSAGE_BODY		4
#define MACRO_TYPE_TRIGGER_EXPRESSION	5

void	substitute_simple_macros(DB_EVENT *event, DB_ACTION *action, char **data, int macro_type);
	
#endif
