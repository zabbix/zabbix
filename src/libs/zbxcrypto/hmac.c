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

#include "zbxcrypto.h"

#include "zbxhash.h"
#include "common.h"

static void	*hmac_hash_init(zbx_crypto_hash_t type)
{
	void	*ctx;

	switch (type)
	{
		case ZBX_HASH_MD5:
			ctx = zbx_malloc(NULL, sizeof(md5_state_t));
			zbx_md5_init((md5_state_t *)ctx);
			break;
		case ZBX_HASH_SHA256:
			ctx = zbx_malloc(NULL, sizeof(sha256_ctx));
			zbx_sha256_init((sha256_ctx *)ctx);
			break;
		default:
			return NULL;
	}

	return ctx;
}

static void	hmac_hash_append(zbx_crypto_hash_t type, void *ctx, const char *text, size_t text_len)
{
	switch (type)
	{
		case ZBX_HASH_MD5:
			zbx_md5_append((md5_state_t *)(ctx), (const md5_byte_t *)text, text_len);
			break;
		case ZBX_HASH_SHA256:
			zbx_sha256_process_bytes(text, text_len, (sha256_ctx *)ctx);
			break;
		default:
			return;
	}
}

static void	hmac_hash_finish(zbx_crypto_hash_t type, void *ctx, char *out)
{
	switch (type)
	{
		case ZBX_HASH_MD5:
			zbx_md5_finish((md5_state_t *)(ctx), (md5_byte_t *)out);
			break;
		case ZBX_HASH_SHA256:
			zbx_sha256_finish((sha256_ctx *)ctx, out);
			break;
		default:
			return;
	}

	zbx_free(ctx);
}

static void	hmac_hash(zbx_crypto_hash_t type, const char *left, size_t left_len, const char *right,
		size_t right_len, char *out)
{
	void	*ctx;

	ctx = hmac_hash_init(type);
	hmac_hash_append(type, ctx, left, left_len);
	if (0 != right_len)
		hmac_hash_append(type, ctx, right, right_len);
	hmac_hash_finish(type, ctx, out);
}

int	zbx_hmac(zbx_crypto_hash_t hash_type, const char *key, size_t key_len, const char *text, size_t text_len,
		char **out)
{
	size_t	block_size, digest_size, out_len, i;
	char	*key_block, *key_ipad, *key_opad, *ihash, *ohash;
#define MD5_BLOCK_SIZE		64
#define SHA256_BLOCK_SIZE	64
	switch (hash_type)
	{
		case ZBX_HASH_MD5:
			block_size = MD5_BLOCK_SIZE;
			digest_size = ZBX_MD5_DIGEST_SIZE;
			break;
		case ZBX_HASH_SHA256:
			block_size = SHA256_BLOCK_SIZE;
			digest_size = ZBX_SHA256_DIGEST_SIZE;
			break;
		default:
			return FAIL;
	}
#undef MD5_BLOCK_SIZE
#undef SHA256_BLOCK_SIZE
	key_block = (char *)zbx_malloc(NULL, block_size);
	key_ipad = (char *)zbx_malloc(NULL, block_size);
	key_opad = (char *)zbx_malloc(NULL, block_size);

	memset(key_block, 0, block_size);
	memset(key_ipad, 0x36, block_size);
	memset(key_opad, 0x5c, block_size);

	ihash = (char *)zbx_malloc(NULL, digest_size);
	ohash = (char *)zbx_malloc(NULL, digest_size);

	if (key_len > block_size)
		hmac_hash(hash_type, key, key_len, "", 0, key_block);
	else
		memcpy(key_block, key, key_len);

	for (i = 0; i < block_size; i++)
	{
		key_ipad[i] ^= key_block[i];
		key_opad[i] ^= key_block[i];
	}

	hmac_hash(hash_type, key_ipad, block_size, text, text_len, ihash);
	hmac_hash(hash_type, key_opad, block_size, ihash, digest_size, ohash);

	out_len = digest_size * 2 + 1;
	*out = (char *)zbx_malloc(NULL, out_len);

	(void)zbx_bin2hex((const unsigned char *)ohash, digest_size, *out, out_len);

	zbx_free(ohash);
	zbx_free(ihash);
	zbx_free(key_opad);
	zbx_free(key_ipad);
	zbx_free(key_block);

	return SUCCEED;
}
