/* SHA512-based Unix crypt implementation.
Released into the Public Domain by Ulrich Drepper <drepper@redhat.com>.  */

#include "sha512crypt.h"

#ifdef __linux__
	#include <endian.h>
#elif __hpux
/* Nothing to do in HP-UX */
#elif _AIX
/* Nothing to do in AIX */
#else
	#if defined(ZBX_OLD_SOLARIS)
		#include <sys/isa_defs.h>
	#else
		#include <machine/endian.h>
	#endif
#endif
#include <stdint.h>
#include <stdio.h>
#include <string.h>

/* Structure to save state of computation between the single steps.  */
struct sha512_ctx
{
	uint64_t H[8];

	uint64_t total[2];
	uint64_t buflen;
	char buffer[256];	/* NB: always correctly aligned for uint64_t.  */
};

#if __BYTE_ORDER == __LITTLE_ENDIAN
# define SWAP(n) \
	(((n) << 56)					\
	| (((n) & 0xff00) << 40)			\
	| (((n) & 0xff0000) << 24)			\
	| (((n) & 0xff000000) << 8)			\
	| (((n) >> 8) & 0xff000000)			\
	| (((n) >> 24) & 0xff0000)			\
	| (((n) >> 40) & 0xff00)			\
	| ((n) >> 56))
#else
# define SWAP(n) (n)
#endif

/* This array contains the bytes used to pad the buffer to the next
64-byte boundary.  (FIPS 180-2:5.1.2)  */
static const unsigned char fillbuf[128] = { 0x80, 0 /* , 0, 0, ...  */ };

/* Constants for SHA512 from FIPS 180-2:4.2.3.  */
static const uint64_t K[80] =
{
	UINT64_C (0x428a2f98d728ae22), UINT64_C (0x7137449123ef65cd),
	UINT64_C (0xb5c0fbcfec4d3b2f), UINT64_C (0xe9b5dba58189dbbc),
	UINT64_C (0x3956c25bf348b538), UINT64_C (0x59f111f1b605d019),
	UINT64_C (0x923f82a4af194f9b), UINT64_C (0xab1c5ed5da6d8118),
	UINT64_C (0xd807aa98a3030242), UINT64_C (0x12835b0145706fbe),
	UINT64_C (0x243185be4ee4b28c), UINT64_C (0x550c7dc3d5ffb4e2),
	UINT64_C (0x72be5d74f27b896f), UINT64_C (0x80deb1fe3b1696b1),
	UINT64_C (0x9bdc06a725c71235), UINT64_C (0xc19bf174cf692694),
	UINT64_C (0xe49b69c19ef14ad2), UINT64_C (0xefbe4786384f25e3),
	UINT64_C (0x0fc19dc68b8cd5b5), UINT64_C (0x240ca1cc77ac9c65),
	UINT64_C (0x2de92c6f592b0275), UINT64_C (0x4a7484aa6ea6e483),
	UINT64_C (0x5cb0a9dcbd41fbd4), UINT64_C (0x76f988da831153b5),
	UINT64_C (0x983e5152ee66dfab), UINT64_C (0xa831c66d2db43210),
	UINT64_C (0xb00327c898fb213f), UINT64_C (0xbf597fc7beef0ee4),
	UINT64_C (0xc6e00bf33da88fc2), UINT64_C (0xd5a79147930aa725),
	UINT64_C (0x06ca6351e003826f), UINT64_C (0x142929670a0e6e70),
	UINT64_C (0x27b70a8546d22ffc), UINT64_C (0x2e1b21385c26c926),
	UINT64_C (0x4d2c6dfc5ac42aed), UINT64_C (0x53380d139d95b3df),
	UINT64_C (0x650a73548baf63de), UINT64_C (0x766a0abb3c77b2a8),
	UINT64_C (0x81c2c92e47edaee6), UINT64_C (0x92722c851482353b),
	UINT64_C (0xa2bfe8a14cf10364), UINT64_C (0xa81a664bbc423001),
	UINT64_C (0xc24b8b70d0f89791), UINT64_C (0xc76c51a30654be30),
	UINT64_C (0xd192e819d6ef5218), UINT64_C (0xd69906245565a910),
	UINT64_C (0xf40e35855771202a), UINT64_C (0x106aa07032bbd1b8),
	UINT64_C (0x19a4c116b8d2d0c8), UINT64_C (0x1e376c085141ab53),
	UINT64_C (0x2748774cdf8eeb99), UINT64_C (0x34b0bcb5e19b48a8),
	UINT64_C (0x391c0cb3c5c95a63), UINT64_C (0x4ed8aa4ae3418acb),
	UINT64_C (0x5b9cca4f7763e373), UINT64_C (0x682e6ff3d6b2b8a3),
	UINT64_C (0x748f82ee5defb2fc), UINT64_C (0x78a5636f43172f60),
	UINT64_C (0x84c87814a1f0ab72), UINT64_C (0x8cc702081a6439ec),
	UINT64_C (0x90befffa23631e28), UINT64_C (0xa4506cebde82bde9),
	UINT64_C (0xbef9a3f7b2c67915), UINT64_C (0xc67178f2e372532b),
	UINT64_C (0xca273eceea26619c), UINT64_C (0xd186b8c721c0c207),
	UINT64_C (0xeada7dd6cde0eb1e), UINT64_C (0xf57d4f7fee6ed178),
	UINT64_C (0x06f067aa72176fba), UINT64_C (0x0a637dc5a2c898a6),
	UINT64_C (0x113f9804bef90dae), UINT64_C (0x1b710b35131c471b),
	UINT64_C (0x28db77f523047d84), UINT64_C (0x32caab7b40c72493),
	UINT64_C (0x3c9ebe0a15c9bebc), UINT64_C (0x431d67c49c100d4c),
	UINT64_C (0x4cc5d4becb3e42b6), UINT64_C (0x597f299cfc657e2a),
	UINT64_C (0x5fcb6fab3ad6faec), UINT64_C (0x6c44198c4a475817)
};

/* Process LEN bytes of BUFFER, accumulating context into CTX.
It is assumed that LEN % 128 == 0.  */
static void
sha512_process_block (const void *buffer, size_t len, struct sha512_ctx *ctx)
{
	const uint64_t *words = buffer;
	size_t nwords = len / sizeof (uint64_t);
	uint64_t a = ctx->H[0];
	uint64_t b = ctx->H[1];
	uint64_t c = ctx->H[2];
	uint64_t d = ctx->H[3];
	uint64_t e = ctx->H[4];
	uint64_t f = ctx->H[5];
	uint64_t g = ctx->H[6];
	uint64_t h = ctx->H[7];

/* First increment the byte count.  FIPS 180-2 specifies the possible
length of the file up to 2^128 bits.  Here we only compute the
number of bytes.  Do a double word increment.  */
	ctx->total[0] += len;
	if (ctx->total[0] < len)
		++ctx->total[1];

/* Process all bytes in the buffer with 128 bytes in each round of
the loop.  */
	while (nwords > 0)
	{
		uint64_t W[80];
		uint64_t a_save = a;
		uint64_t b_save = b;
		uint64_t c_save = c;
		uint64_t d_save = d;
		uint64_t e_save = e;
		uint64_t f_save = f;
		uint64_t g_save = g;
		uint64_t h_save = h;

/* Operators defined in FIPS 180-2:4.1.2.  */
#define Ch(x, y, z) ((x & y) ^ (~x & z))
#define Maj(x, y, z) ((x & y) ^ (x & z) ^ (y & z))
#define S0(x) (CYCLIC (x, 28) ^ CYCLIC (x, 34) ^ CYCLIC (x, 39))
#define S1(x) (CYCLIC (x, 14) ^ CYCLIC (x, 18) ^ CYCLIC (x, 41))
#define R0(x) (CYCLIC (x, 1) ^ CYCLIC (x, 8) ^ (x >> 7))
#define R1(x) (CYCLIC (x, 19) ^ CYCLIC (x, 61) ^ (x >> 6))

/* It is unfortunate that C does not provide an operator for
cyclic rotation.  Hope the C compiler is smart enough.  */
#define CYCLIC(w, s) ((w >> s) | (w << (64 - s)))

		/* Compute the message schedule according to FIPS 180-2:6.3.2 step 2.  */
		unsigned int t = 0;
		for (t = 0; t < 16; ++t)
		{
			W[t] = SWAP (*words);
			++words;
		}
		for (t = 16; t < 80; ++t)
			W[t] = R1 (W[t - 2]) + W[t - 7] + R0 (W[t - 15]) + W[t - 16];

		/* The actual computation according to FIPS 180-2:6.3.2 step 3.  */
		for (t = 0; t < 80; ++t)
		{
			uint64_t T1 = h + S1 (e) + Ch (e, f, g) + K[t] + W[t];
			uint64_t T2 = S0 (a) + Maj (a, b, c);
			h = g;
			g = f;
			f = e;
			e = d + T1;
			d = c;
			c = b;
			b = a;
			a = T1 + T2;
		}

		/* Add the starting values of the context according to FIPS 180-2:6.3.2 step 4.  */
		a += a_save;
		b += b_save;
		c += c_save;
		d += d_save;
		e += e_save;
		f += f_save;
		g += g_save;
		h += h_save;

		/* Prepare for the next round.  */
		nwords -= 16;
	}

	/* Put checksum in context given as argument.  */
	ctx->H[0] = a;
	ctx->H[1] = b;
	ctx->H[2] = c;
	ctx->H[3] = d;
	ctx->H[4] = e;
	ctx->H[5] = f;
	ctx->H[6] = g;
	ctx->H[7] = h;
}

/* Initialize structure containing state of computation.
(FIPS 180-2:5.3.3)  */
static void
sha512_init_ctx (struct sha512_ctx *ctx)
{
	ctx->H[0] = UINT64_C (0x6a09e667f3bcc908);
	ctx->H[1] = UINT64_C (0xbb67ae8584caa73b);
	ctx->H[2] = UINT64_C (0x3c6ef372fe94f82b);
	ctx->H[3] = UINT64_C (0xa54ff53a5f1d36f1);
	ctx->H[4] = UINT64_C (0x510e527fade682d1);
	ctx->H[5] = UINT64_C (0x9b05688c2b3e6c1f);
	ctx->H[6] = UINT64_C (0x1f83d9abfb41bd6b);
	ctx->H[7] = UINT64_C (0x5be0cd19137e2179);

	ctx->total[0] = ctx->total[1] = 0;
	ctx->buflen = 0;
}

/* Process the remaining bytes in the internal buffer and the usual
prolog according to the standard and write the result to RESBUF.

IMPORTANT: On some systems it is required that RESBUF is correctly
aligned for a 32 bits value.  */
static void *
sha512_finish_ctx (struct sha512_ctx *ctx, void *resbuf)
{
	/* Take yet unprocessed bytes into account.  */
	uint64_t bytes = ctx->buflen;
	unsigned int i = 0;
	size_t pad;

	/* Now count remaining bytes.  */
	ctx->total[0] += bytes;
	if (ctx->total[0] < bytes)
		++ctx->total[1];

	pad = bytes >= 112 ? 128 + 112 - bytes : 112 - bytes;
	memcpy (&ctx->buffer[bytes], fillbuf, pad);

	/* Put the 128-bit file length in *bits* at the end of the buffer.  */
	*(uint64_t *) &ctx->buffer[bytes + pad + 8] = SWAP (ctx->total[0] << 3);
	*(uint64_t *) &ctx->buffer[bytes + pad] = SWAP ((ctx->total[1] << 3) | (ctx->total[0] >> 61));

	/* Process last bytes.  */
	sha512_process_block (ctx->buffer, bytes + pad + 16, ctx);

	/* Put result from CTX in first 64 bytes following RESBUF.  */
	for (i = 0; i < 8; ++i)
		((uint64_t *) resbuf)[i] = SWAP (ctx->H[i]);

	return resbuf;
}

static void
sha512_process_bytes (const void *buffer, size_t len, struct sha512_ctx *ctx)
{
	/* When we already have some bits in our internal buffer concatenate
	both inputs first.  */
	if (ctx->buflen != 0)
	{
		size_t left_over = ctx->buflen;
		size_t add = 256 - left_over > len ? len : 256 - left_over;

		memcpy (&ctx->buffer[left_over], buffer, add);
		ctx->buflen += add;

		if (ctx->buflen > 128)
		{
			sha512_process_block (ctx->buffer, ctx->buflen & ~127, ctx);

			ctx->buflen &= 127;
			/* The regions in the following copy operation cannot overlap.  */
			memcpy (ctx->buffer, &ctx->buffer[(left_over + add) & ~127],
			ctx->buflen);
		}

		buffer = (const char *) buffer + add;
		len -= add;
	}

	/* Process available complete blocks.  */
	if (len >= 128)
	{
#if !_STRING_ARCH_unaligned
	/* To check alignment gcc has an appropriate operator.  Other
	compilers don't.  */
# if __GNUC__ >= 2
#  define UNALIGNED_P(p) (((uintptr_t) p) % __alignof__ (uint64_t) != 0)
# else
#  define UNALIGNED_P(p) (((uintptr_t) p) % sizeof (uint64_t) != 0)
# endif
		if (UNALIGNED_P (buffer))
			while (len > 128)
			{
				sha512_process_block (memcpy (ctx->buffer, buffer, 128), 128, ctx);
				buffer = (const char *) buffer + 128;
				len -= 128;
			}
		else
#endif
		{
			sha512_process_block (buffer, len & ~127, ctx);
			buffer = (const char *) buffer + (len & ~127);
			len &= 127;
		}
	}

	/* Move remaining bytes into internal buffer.  */
	if (len > 0)
	{
		size_t left_over = ctx->buflen;

		memcpy (&ctx->buffer[left_over], buffer, len);
		left_over += len;
		if (left_over >= 128)
		{
			sha512_process_block (ctx->buffer, 128, ctx);
			left_over -= 128;
			memmove (ctx->buffer, &ctx->buffer[128], left_over);
		}
		ctx->buflen = left_over;
	}
}

/* Maximum salt string length.  */
#define SALT_LEN_MAX 16
/* Default number of rounds if not explicitly specified.  */
#define ROUNDS_DEFAULT 5000
/* Minimum number of rounds.  */
#define ROUNDS_MIN 1000
/* Maximum number of rounds.  */
#define ROUNDS_MAX 999999999

void zbx_sha512_hash(const char *in, char *out)
{
	struct	sha512_ctx ctx;
	sha512_init_ctx (&ctx);
	sha512_process_bytes (in, strlen (in), &ctx);
	sha512_finish_ctx (&ctx, out);
}
