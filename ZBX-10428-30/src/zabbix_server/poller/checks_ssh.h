/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#ifndef ZABBIX_CHECKS_SSH_H
#define ZABBIX_CHECKS_SSH_H

#include "common.h"

#ifdef HAVE_SSH2
#include "dbcache.h"

extern char	*CONFIG_SOURCE_IP;
extern char	*CONFIG_SSH_KEY_LOCATION;

int	get_value_ssh(DC_ITEM *item, zbx_result_t *result);
#endif	/* HAVE_SSH2 */

#endif
