/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#ifndef ZABBIX_TELNET_H
#define ZABBIX_TELNET_H

#include "module.h"
#include "comms.h"

#define WAIT_READ	0
#define WAIT_WRITE	1

#define CMD_IAC		255
#define CMD_WILL	251
#define CMD_WONT	252
#define CMD_DO		253
#define CMD_DONT	254
#define OPT_SGA		3

int	telnet_test_login(ZBX_SOCKET socket_fd);
int	telnet_login(ZBX_SOCKET socket_fd, const char *username, const char *password, AGENT_RESULT *result);
int	telnet_execute(ZBX_SOCKET socket_fd, const char *command, AGENT_RESULT *result, const char *encoding);

#endif
