/*
** Copyright (C) 2001-2026 Zabbix SIA
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
**
** Portable BLAKE3 cryptographic hash implementation derived from the official
** BLAKE3 specification and reference implementation released under CC0 1.0
** Universal into the Public Domain.
**/

#include "zbxhash.h"

#define ZBX_BLAKE3_CHUNK_START		(1u << 0)
#define ZBX_BLAKE3_CHUNK_END		(1u << 1)
#define ZBX_BLAKE3_PARENT		(1u << 2)
#define ZBX_BLAKE3_ROOT			(1u << 3)
#define ZBX_BLAKE3_KEYED_HASH		(1u << 4)

static const zbx_uint32_t	ZBX_BLAKE3_IV[8] = {
	0x6A09E667UL, 0xBB67AE85UL, 0x3C6EF372UL, 0xA54FF53AUL,
	0x510E527FUL, 0x9B05688CUL, 0x1F83D9ABUL, 0x5BE0CD19UL
};

static const unsigned char	ZBX_BLAKE3_MSG_SCHEDULE[7][16] = {
	{0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15},
	{2, 6, 3, 10, 7, 0, 4, 13, 1, 11, 12, 5, 9, 14, 15, 8},
	{3, 4, 10, 12, 13, 2, 7, 14, 6, 5, 9, 0, 11, 15, 8, 1},
	{10, 7, 12, 9, 14, 3, 13, 15, 4, 0, 11, 2, 5, 8, 1, 6},
	{12, 13, 9, 11, 15, 10, 14, 8, 7, 2, 5, 3, 0, 1, 6, 4},
	{9, 14, 11, 5, 8, 12, 15, 1, 13, 3, 0, 10, 2, 6, 4, 7},
	{11, 15, 5, 0, 1, 9, 8, 6, 14, 10, 2, 12, 3, 4, 7, 13}
};

typedef struct
{
	zbx_uint32_t	input_cv[8];
	zbx_uint64_t	counter;
	unsigned char	block[ZBX_BLAKE3_BLOCK_LEN];
	unsigned char	block_len;
	unsigned char	flags;
}
blake3_output_t;

static size_t	blake3_popcount64(zbx_uint64_t x)
{
	size_t	count = 0;

	while (0 != x)
	{
		x &= (x - 1);
		count++;
	}

	return count;
}

static zbx_uint32_t	blake3_rotr32(zbx_uint32_t w, unsigned int c)
{
	return (w >> c) | (w << (32 - c));
}

static zbx_uint32_t	blake3_load32(const void *src)
{
	const unsigned char	*p = (const unsigned char *)src;

	return ((zbx_uint32_t)p[0] << 0) |
		((zbx_uint32_t)p[1] << 8) |
		((zbx_uint32_t)p[2] << 16) |
		((zbx_uint32_t)p[3] << 24);
}

static void	blake3_store32(void *dst, zbx_uint32_t w)
{
	unsigned char	*p = (unsigned char *)dst;

	p[0] = (unsigned char)(w >> 0);
	p[1] = (unsigned char)(w >> 8);
	p[2] = (unsigned char)(w >> 16);
	p[3] = (unsigned char)(w >> 24);
}

static void	blake3_load_key_words(const unsigned char key[ZBX_BLAKE3_KEY_LEN], zbx_uint32_t key_words[8])
{
	size_t	i;

	for (i = 0; i < 8; i++)
		key_words[i] = blake3_load32(&key[i * 4]);
}

static void	blake3_store_cv_words(unsigned char bytes_out[32], const zbx_uint32_t cv_words[8])
{
	size_t	i;

	for (i = 0; i < 8; i++)
		blake3_store32(&bytes_out[i * 4], cv_words[i]);
}

static void	blake3_g(zbx_uint32_t *state, size_t a, size_t b, size_t c, size_t d, zbx_uint32_t x, zbx_uint32_t y)
{
	state[a] = state[a] + state[b] + x;
	state[d] = blake3_rotr32(state[d] ^ state[a], 16);
	state[c] = state[c] + state[d];
	state[b] = blake3_rotr32(state[b] ^ state[c], 12);
	state[a] = state[a] + state[b] + y;
	state[d] = blake3_rotr32(state[d] ^ state[a], 8);
	state[c] = state[c] + state[d];
	state[b] = blake3_rotr32(state[b] ^ state[c], 7);
}

static void	blake3_round_fn(zbx_uint32_t state[16], const zbx_uint32_t *msg, size_t round)
{
	const unsigned char	*schedule = ZBX_BLAKE3_MSG_SCHEDULE[round];

	blake3_g(state, 0, 4, 8, 12, msg[schedule[0]], msg[schedule[1]]);
	blake3_g(state, 1, 5, 9, 13, msg[schedule[2]], msg[schedule[3]]);
	blake3_g(state, 2, 6, 10, 14, msg[schedule[4]], msg[schedule[5]]);
	blake3_g(state, 3, 7, 11, 15, msg[schedule[6]], msg[schedule[7]]);

	blake3_g(state, 0, 5, 10, 15, msg[schedule[8]], msg[schedule[9]]);
	blake3_g(state, 1, 6, 11, 12, msg[schedule[10]], msg[schedule[11]]);
	blake3_g(state, 2, 7, 8, 13, msg[schedule[12]], msg[schedule[13]]);
	blake3_g(state, 3, 4, 9, 14, msg[schedule[14]], msg[schedule[15]]);
}

static void	blake3_compress_pre(zbx_uint32_t state[16], const zbx_uint32_t cv[8],
		const unsigned char block[ZBX_BLAKE3_BLOCK_LEN], unsigned char block_len, zbx_uint64_t counter,
		unsigned char flags)
{
	zbx_uint32_t	block_words[16];
	size_t		i;

	for (i = 0; i < 16; i++)
		block_words[i] = blake3_load32(&block[i * 4]);

	state[0] = cv[0];
	state[1] = cv[1];
	state[2] = cv[2];
	state[3] = cv[3];
	state[4] = cv[4];
	state[5] = cv[5];
	state[6] = cv[6];
	state[7] = cv[7];
	state[8] = ZBX_BLAKE3_IV[0];
	state[9] = ZBX_BLAKE3_IV[1];
	state[10] = ZBX_BLAKE3_IV[2];
	state[11] = ZBX_BLAKE3_IV[3];
	state[12] = (zbx_uint32_t)counter;
	state[13] = (zbx_uint32_t)(counter >> 32);
	state[14] = (zbx_uint32_t)block_len;
	state[15] = (zbx_uint32_t)flags;

	for (i = 0; i < 7; i++)
		blake3_round_fn(state, block_words, i);
}

static void	blake3_compress_in_place(zbx_uint32_t cv[8], const unsigned char block[ZBX_BLAKE3_BLOCK_LEN],
		unsigned char block_len, zbx_uint64_t counter, unsigned char flags)
{
	zbx_uint32_t	state[16];
	size_t		i;

	blake3_compress_pre(state, cv, block, block_len, counter, flags);

	for (i = 0; i < 8; i++)
		cv[i] = state[i] ^ state[i + 8];
}

static void	blake3_compress_xof(const zbx_uint32_t cv[8], const unsigned char block[ZBX_BLAKE3_BLOCK_LEN],
		unsigned char block_len, zbx_uint64_t counter, unsigned char flags, unsigned char out[64])
{
	zbx_uint32_t	state[16];
	size_t		i;

	blake3_compress_pre(state, cv, block, block_len, counter, flags);

	for (i = 0; i < 8; i++)
	{
		blake3_store32(&out[i * 4], state[i] ^ state[i + 8]);
		blake3_store32(&out[(i + 8) * 4], state[i + 8] ^ cv[i]);
	}
}

static blake3_output_t	blake3_make_output(const zbx_uint32_t input_cv[8],
		const unsigned char block[ZBX_BLAKE3_BLOCK_LEN], unsigned char block_len, zbx_uint64_t counter,
		unsigned char flags)
{
	blake3_output_t	ret;

	memcpy(ret.input_cv, input_cv, 32);
	memcpy(ret.block, block, ZBX_BLAKE3_BLOCK_LEN);
	ret.block_len = block_len;
	ret.counter = counter;
	ret.flags = flags;

	return ret;
}

static void	blake3_output_chaining_value(const blake3_output_t *self, unsigned char cv[32])
{
	zbx_uint32_t	cv_words[8];

	memcpy(cv_words, self->input_cv, 32);
	blake3_compress_in_place(cv_words, self->block, self->block_len, self->counter, self->flags);
	blake3_store_cv_words(cv, cv_words);
}

static void	blake3_output_root_bytes(const blake3_output_t *self, zbx_uint64_t seek, unsigned char *out,
		size_t out_len)
{
	zbx_uint64_t	output_block_counter;
	size_t		offset_within_block;
	unsigned char	wide_buf[64];

	if (0 == out_len)
		return;

	output_block_counter = seek / 64;
	offset_within_block = (size_t)(seek % 64);

	while (0 < out_len)
	{
		size_t	bytes_available, memcpy_len;

		blake3_compress_xof(self->input_cv, self->block, self->block_len, output_block_counter,
				(unsigned char)(self->flags | ZBX_BLAKE3_ROOT), wide_buf);

		bytes_available = 64 - offset_within_block;
		memcpy_len = out_len < bytes_available ? out_len : bytes_available;

		memcpy(out, wide_buf + offset_within_block, memcpy_len);
		out += memcpy_len;
		out_len -= memcpy_len;
		output_block_counter += 1;
		offset_within_block = 0;
	}
}

static void	blake3_chunk_state_init(zbx_blake3_chunk_state_t *self, const zbx_uint32_t key[8], unsigned char flags)
{
	memcpy(self->cv, key, 32);
	self->chunk_counter = 0;
	memset(self->buf, 0, ZBX_BLAKE3_BLOCK_LEN);
	self->buf_len = 0;
	self->blocks_compressed = 0;
	self->flags = flags;
}

static void	blake3_chunk_state_reset(zbx_blake3_chunk_state_t *self, const zbx_uint32_t key[8],
		zbx_uint64_t chunk_counter)
{
	memcpy(self->cv, key, 32);
	self->chunk_counter = chunk_counter;
	self->blocks_compressed = 0;
	memset(self->buf, 0, ZBX_BLAKE3_BLOCK_LEN);
	self->buf_len = 0;
}

static size_t	blake3_chunk_state_len(const zbx_blake3_chunk_state_t *self)
{
	return (ZBX_BLAKE3_BLOCK_LEN * (size_t)self->blocks_compressed) + (size_t)self->buf_len;
}

static unsigned char	blake3_chunk_state_maybe_start_flag(const zbx_blake3_chunk_state_t *self)
{
	if (0 == self->blocks_compressed)
		return ZBX_BLAKE3_CHUNK_START;

	return 0;
}

static void	blake3_chunk_state_update(zbx_blake3_chunk_state_t *self, const unsigned char *input, size_t input_len)
{
	if (0 < self->buf_len)
	{
		size_t	take = ZBX_BLAKE3_BLOCK_LEN - (size_t)self->buf_len;

		if (take > input_len)
			take = input_len;

		memcpy(self->buf + self->buf_len, input, take);
		self->buf_len = (unsigned char)(self->buf_len + take);
		input += take;
		input_len -= take;

		if (0 < input_len)
		{
			blake3_compress_in_place(self->cv, self->buf, ZBX_BLAKE3_BLOCK_LEN, self->chunk_counter,
					(unsigned char)(self->flags | blake3_chunk_state_maybe_start_flag(self)));
			self->blocks_compressed += 1;
			self->buf_len = 0;
			memset(self->buf, 0, ZBX_BLAKE3_BLOCK_LEN);
		}
	}

	while (ZBX_BLAKE3_BLOCK_LEN < input_len)
	{
		blake3_compress_in_place(self->cv, input, ZBX_BLAKE3_BLOCK_LEN, self->chunk_counter,
				(unsigned char)(self->flags | blake3_chunk_state_maybe_start_flag(self)));
		self->blocks_compressed += 1;
		input += ZBX_BLAKE3_BLOCK_LEN;
		input_len -= ZBX_BLAKE3_BLOCK_LEN;
	}

	if (0 < input_len)
	{
		memcpy(self->buf + self->buf_len, input, input_len);
		self->buf_len = (unsigned char)(self->buf_len + input_len);
	}
}

static blake3_output_t	blake3_chunk_state_output(const zbx_blake3_chunk_state_t *self)
{
	unsigned char	block_flags;

	block_flags = (unsigned char)(self->flags | blake3_chunk_state_maybe_start_flag(self) | ZBX_BLAKE3_CHUNK_END);

	return blake3_make_output(self->cv, self->buf, self->buf_len, self->chunk_counter, block_flags);
}

static blake3_output_t	blake3_parent_output(const unsigned char block[ZBX_BLAKE3_BLOCK_LEN],
		const zbx_uint32_t key[8], unsigned char flags)
{
	return blake3_make_output(key, block, ZBX_BLAKE3_BLOCK_LEN, 0, (unsigned char)(flags | ZBX_BLAKE3_PARENT));
}

static void	blake3_hasher_init_base(zbx_blake3_hasher_t *self, const zbx_uint32_t key[8], unsigned char flags)
{
	memcpy(self->key, key, 32);
	blake3_chunk_state_init(&self->chunk, key, flags);
	self->cv_stack_len = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes BLAKE3 hasher in standard mode                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_blake3_init(zbx_blake3_hasher_t *self)
{
	blake3_hasher_init_base(self, ZBX_BLAKE3_IV, 0);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes BLAKE3 hasher in 256-bit keyed mode                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_blake3_init_keyed(zbx_blake3_hasher_t *self, const unsigned char key[ZBX_BLAKE3_KEY_LEN])
{
	zbx_uint32_t	key_words[8];

	blake3_load_key_words(key, key_words);
	blake3_hasher_init_base(self, key_words, ZBX_BLAKE3_KEYED_HASH);
}

static void	blake3_hasher_merge_cv_stack(zbx_blake3_hasher_t *self, zbx_uint64_t total_len)
{
	size_t	post_merge_stack_len = blake3_popcount64(total_len);

	while ((size_t)self->cv_stack_len > post_merge_stack_len)
	{
		unsigned char	*parent_node = &self->cv_stack[(self->cv_stack_len - 2) * ZBX_BLAKE3_OUT_LEN];
		blake3_output_t	output = blake3_parent_output(parent_node, self->key, self->chunk.flags);

		blake3_output_chaining_value(&output, parent_node);
		self->cv_stack_len -= 1;
	}
}

static void	blake3_hasher_push_cv(zbx_blake3_hasher_t *self, const unsigned char new_cv[ZBX_BLAKE3_OUT_LEN],
		zbx_uint64_t chunk_counter)
{
	blake3_hasher_merge_cv_stack(self, chunk_counter);
	memcpy(&self->cv_stack[self->cv_stack_len * ZBX_BLAKE3_OUT_LEN], new_cv, ZBX_BLAKE3_OUT_LEN);
	self->cv_stack_len += 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: feeds input bytes into BLAKE3 hasher                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_blake3_update(zbx_blake3_hasher_t *self, const void *input, size_t input_len)
{
	const unsigned char	*input_bytes = (const unsigned char *)input;

	if (0 == input_len)
		return;

	if (0 < blake3_chunk_state_len(&self->chunk))
	{
		size_t	take = ZBX_BLAKE3_CHUNK_LEN - blake3_chunk_state_len(&self->chunk);

		if (take > input_len)
			take = input_len;

		blake3_chunk_state_update(&self->chunk, input_bytes, take);
		input_bytes += take;
		input_len -= take;

		if (0 < input_len)
		{
			blake3_output_t	output = blake3_chunk_state_output(&self->chunk);
			unsigned char	chunk_cv[32];

			blake3_output_chaining_value(&output, chunk_cv);
			blake3_hasher_push_cv(self, chunk_cv, self->chunk.chunk_counter);
			blake3_chunk_state_reset(&self->chunk, self->key, self->chunk.chunk_counter + 1);
		}
		else
			return;
	}

	while (ZBX_BLAKE3_CHUNK_LEN < input_len)
	{
		zbx_uint32_t	cv_words[8];
		unsigned char	chunk_cv[32];
		size_t		b;

		memcpy(cv_words, self->key, 32);

		for (b = 0; b < 15; b++)
		{
			unsigned char	block_flags = self->chunk.flags;

			if (0 == b)
				block_flags |= ZBX_BLAKE3_CHUNK_START;

			blake3_compress_in_place(cv_words, input_bytes + (b * ZBX_BLAKE3_BLOCK_LEN),
					ZBX_BLAKE3_BLOCK_LEN, self->chunk.chunk_counter, block_flags);
		}

		blake3_compress_in_place(cv_words, input_bytes + (15 * ZBX_BLAKE3_BLOCK_LEN),
				ZBX_BLAKE3_BLOCK_LEN, self->chunk.chunk_counter,
				(unsigned char)(self->chunk.flags | ZBX_BLAKE3_CHUNK_END));
		blake3_store_cv_words(chunk_cv, cv_words);

		blake3_hasher_push_cv(self, chunk_cv, self->chunk.chunk_counter);
		self->chunk.chunk_counter += 1;
		input_bytes += ZBX_BLAKE3_CHUNK_LEN;
		input_len -= ZBX_BLAKE3_CHUNK_LEN;
	}

	if (0 < input_len)
	{
		blake3_hasher_merge_cv_stack(self, self->chunk.chunk_counter);
		blake3_chunk_state_update(&self->chunk, input_bytes, input_len);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: finalizes BLAKE3 state and writes digest bytes                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_blake3_finalize(const zbx_blake3_hasher_t *self, unsigned char *out, size_t out_len)
{
	blake3_output_t	output;
	size_t		cvs_remaining;

	if (0 == out_len)
		return;

	if (0 == self->cv_stack_len)
	{
		output = blake3_chunk_state_output(&self->chunk);
		blake3_output_root_bytes(&output, 0, out, out_len);
		return;
	}

	if (0 < blake3_chunk_state_len(&self->chunk))
	{
		cvs_remaining = self->cv_stack_len;
		output = blake3_chunk_state_output(&self->chunk);
	}
	else
	{
		cvs_remaining = (size_t)(self->cv_stack_len - 2);
		output = blake3_parent_output(&self->cv_stack[cvs_remaining * 32], self->key, self->chunk.flags);
	}

	while (0 < cvs_remaining)
	{
		unsigned char	parent_block[ZBX_BLAKE3_BLOCK_LEN];

		cvs_remaining -= 1;
		memcpy(parent_block, &self->cv_stack[cvs_remaining * 32], 32);
		blake3_output_chaining_value(&output, &parent_block[32]);
		output = blake3_parent_output(parent_block, self->key, self->chunk.flags);
	}

	blake3_output_root_bytes(&output, 0, out, out_len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: computes 256-bit BLAKE3 digest in a single call                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_blake3_hash(const void *in, size_t len, unsigned char out[ZBX_BLAKE3_OUT_LEN])
{
	zbx_blake3_hasher_t	hasher;

	zbx_blake3_init(&hasher);
	zbx_blake3_update(&hasher, in, len);
	zbx_blake3_finalize(&hasher, out, ZBX_BLAKE3_OUT_LEN);
}

/******************************************************************************
 *                                                                            *
 * Purpose: encodes 32-byte BLAKE3 digest into lowercase hex string           *
 *                                                                            *
 ******************************************************************************/
void	zbx_blake3_buf2hex(const unsigned char digest[ZBX_BLAKE3_OUT_LEN], char hex_out[ZBX_BLAKE3_HEX_LEN])
{
	static const char	hex_digits[] = "0123456789abcdef";
	size_t			i;

	for (i = 0; i < ZBX_BLAKE3_OUT_LEN; i++)
	{
		hex_out[i * 2] = hex_digits[(digest[i] >> 4) & 0x0f];
		hex_out[i * 2 + 1] = hex_digits[digest[i] & 0x0f];
	}

	hex_out[ZBX_BLAKE3_OUT_LEN * 2] = '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: decodes 64-character hex string into 32-byte BLAKE3 digest        *
 *                                                                            *
 ******************************************************************************/
int	zbx_blake3_hex2buf(const char *hex_in, unsigned char digest_out[ZBX_BLAKE3_OUT_LEN])
{
	size_t	i;

	if (NULL == hex_in)
		return FAIL;

	for (i = 0; i < ZBX_BLAKE3_OUT_LEN; i++)
	{
		unsigned char	hi, lo;
		char		c1 = hex_in[i * 2];
		char		c2 = hex_in[i * 2 + 1];

		if ('0' <= c1 && c1 <= '9')
			hi = (unsigned char)(c1 - '0');
		else if ('a' <= c1 && c1 <= 'f')
			hi = (unsigned char)(c1 - 'a' + 10);
		else if ('A' <= c1 && c1 <= 'F')
			hi = (unsigned char)(c1 - 'A' + 10);
		else
			return FAIL;

		if ('0' <= c2 && c2 <= '9')
			lo = (unsigned char)(c2 - '0');
		else if ('a' <= c2 && c2 <= 'f')
			lo = (unsigned char)(c2 - 'a' + 10);
		else if ('A' <= c2 && c2 <= 'F')
			lo = (unsigned char)(c2 - 'A' + 10);
		else
			return FAIL;

		digest_out[i] = (unsigned char)((hi << 4) | lo);
	}

	if ('\0' != hex_in[ZBX_BLAKE3_OUT_LEN * 2])
		return FAIL;

	return SUCCEED;
}
