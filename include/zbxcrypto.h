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

#ifndef ZABBIX_ZBXCRYPTO_H
#define ZABBIX_ZBXCRYPTO_H

#include "zbxtypes.h"
#include "zbxhash.h"

zbx_uint64_t	zbx_letoh_uint64(zbx_uint64_t data);
zbx_uint64_t	zbx_htole_uint64(zbx_uint64_t data);

zbx_uint32_t	zbx_letoh_uint32(zbx_uint32_t data);
zbx_uint32_t	zbx_htole_uint32(zbx_uint32_t data);

int	zbx_hex2bin(const unsigned char *p_hex, unsigned char *buf, int buf_len);
int	zbx_bin2hex(const unsigned char *bin, size_t bin_len, char *out, size_t out_len);

#define ZBX_SESSION_TOKEN_SIZE	(ZBX_MD5_DIGEST_SIZE * 2)

char	*zbx_create_token(zbx_uint64_t seed);
char	*zbx_gen_uuid4(const char *seed);

typedef enum
{
	ZBX_HASH_MD5,
	ZBX_HASH_SHA256
}
zbx_crypto_hash_t;

int	zbx_hmac(zbx_crypto_hash_t hash_type, const char *key, size_t key_len, const char *text, size_t text_len,
		char **out);

#if defined(HAVE_OPENSSL) || defined(HAVE_GNUTLS)
void	zbx_normalize_pem(char **key, size_t *key_len);
int	zbx_rs256_sign(char *key, size_t key_len, char *data, size_t data_len, unsigned char **output,
		size_t *output_len, char **error);
#endif
int	zbx_base64_validate(const char *p_str);
void	zbx_base64_encode(const char *p_str, char *p_b64str, int in_size);
void	zbx_base64_encode_dyn(const char *p_str, char **p_b64str, int in_size);
void	zbx_base64_decode(const char *p_b64str, char *p_str, size_t maxsize, size_t *p_out_size);

#endif /* ZABBIX_ZBXCRYPTO_H */
