/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_SSH_RUN_H
#define ZABBIX_SSH_RUN_H

#include "config.h"
#include "module.h"

#if defined(HAVE_SSH2) || defined(HAVE_SSH)
#include "zbxcacheconfig.h"

#define KEY_EXCHANGE_STR	"KexAlgorithms"
#define KEY_HOSTKEY_STR		"HostkeyAlgorithms"
#define KEY_CIPHERS_STR		"Ciphers"
#define KEY_MACS_STR		"MACs"

int	ssh_run(DC_ITEM *item, AGENT_RESULT *result, const char *encoding, const char *options);
#endif	/* defined(HAVE_SSH2) || defined(HAVE_SSH)*/

#endif
