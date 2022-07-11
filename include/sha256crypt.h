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

#ifndef ZABBIX_SHA256CRYPT_H
#define ZABBIX_SHA256CRYPT_H

#include "sysinc.h"

#define ZBX_SHA256_DIGEST_SIZE	32

/* Structure to save state of computation between the single steps. */
typedef struct
{
	uint32_t	H[8];
	uint32_t	total[2];
	uint32_t	buflen;
	char		buffer[128];	/* NB: always correctly aligned for uint32_t. */
}
sha256_ctx;

void	zbx_sha256_init(sha256_ctx *ctx);
void	zbx_sha256_process_bytes(const void *buffer, size_t len, sha256_ctx *ctx);
void	*zbx_sha256_finish(sha256_ctx *ctx, void *resbuf);
void	zbx_sha256_hash(const char *in, char *out);
void	zbx_sha256_hash_len(const char *in, size_t len, char *out);

void*	zbx_sha256_hash_for_hmac(const void* data, const size_t datalen, void* out, const size_t outlen);

#endif /* ZABBIX_SHA256CRYPT_H */
