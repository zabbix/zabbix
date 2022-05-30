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

#ifndef ZABBIX_ZBXCRYPTO_H
#define ZABBIX_ZBXCRYPTO_H

#include "zbxtypes.h"

int	zbx_hex2bin(const unsigned char *p_hex, unsigned char *buf, int buf_len);
int	zbx_bin2hex(const unsigned char *bin, size_t bin_len, char *out, size_t out_len);

char	*zbx_create_token(zbx_uint64_t seed);
size_t	zbx_get_token_len(void);
char	*zbx_gen_uuid4(const char *seed);

typedef enum
{
	ZBX_HASH_MD5,
	ZBX_HASH_SHA256
}
zbx_crypto_hash_t;

int	zbx_hmac(zbx_crypto_hash_t hash_type, const char *key, size_t key_len, const char *text, size_t text_len,
		char **out);
#endif /* ZABBIX_ZBXCRYPTO_H */
