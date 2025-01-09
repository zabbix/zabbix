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

#ifndef ZABBIX_SSH_RUN_H
#define ZABBIX_SSH_RUN_H

#include "zbxcommon.h"

#if defined(HAVE_SSH2) || defined(HAVE_SSH)
#include "zbxcacheconfig.h"

#define KEY_EXCHANGE_STR	"KexAlgorithms"
#define KEY_HOSTKEY_STR		"HostkeyAlgorithms"
#define KEY_CIPHERS_STR		"Ciphers"
#define KEY_MACS_STR		"MACs"
#define KEY_PUBKEY_STR		"PubkeyAcceptedKeyTypes"

int	ssh_run(zbx_dc_item_t *item, AGENT_RESULT *result, const char *encoding, const char *options, int timeout,
		const char *config_source_ip, const char *config_ssh_key_location, const char *subsystem);
#endif	/* defined(HAVE_SSH2) || defined(HAVE_SSH)*/

#endif
