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

#ifndef ZABBIX_DISCOVERER_H
#define ZABBIX_DISCOVERER_H

#include "common.h"

extern	int	CONFIG_DISCOVERER_FORKS;

void	update_dhost(DB_DHOST *host);
void	update_dservice(DB_DSERVICE *service);

void	register_host(DB_DHOST *host, DB_DCHECK *check, const char *ip);
void	register_service(DB_DSERVICE *service, DB_DCHECK *check, const char *ip, int port);

void	main_discoverer_loop(zbx_process_t p, int num);

#endif
