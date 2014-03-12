#include <openssl/conf.h>
#include <openssl/evp.h>
#include <openssl/err.h>
#include <string.h>

#include "common.h"
#include "base64.h"
#include "rsm.h"

static int	nrounds = 500;

int     get_random(void *data, int bytes)
{
	int	fd;

	if (-1 == (fd = open("/dev/random", O_RDONLY)))
		return FAIL;

	read(fd, data, bytes);
	close(fd);

	return SUCCEED;
}

void handleErrors(void)
{
	ERR_print_errors_fp(stderr);
}

int	encrypt(const EVP_CIPHER *cipher, unsigned char *plaintext, int plaintext_len, unsigned char *key,
		unsigned char *iv, unsigned char *ciphertext, int *ciphertext_len)
{
	EVP_CIPHER_CTX	*ctx;
	int		len;

	/* create and initialise the context */
	if (NULL == (ctx = EVP_CIPHER_CTX_new()))
	{
		handleErrors();
		return FAIL;
	}

	/* Initialise the encryption operation. IMPORTANT - ensure you use a key
	 * and IV size appropriate for your cipher
	 * In this example we are using 256 bit AES (i.e. a 256 bit key). The
	 * IV size for *most* modes is the same as the block size. For AES this
	 * is 128 bits */
	if (1 != EVP_EncryptInit_ex(ctx, cipher, NULL, key, iv))
	{
		handleErrors();
		return FAIL;
	}

	/* Provide the message to be encrypted, and obtain the ecrypted output.
	 * EVP_EncryptUpdate can be called multiple times if necessary */
	if (1 != EVP_EncryptUpdate(ctx, ciphertext, &len, plaintext, plaintext_len))
	{
		handleErrors();
		return FAIL;
	}

	*ciphertext_len = len;

	/* Finalise the encryption. Further ciphertext bytes may be written at
	 * this stage. */
	if (1 != EVP_EncryptFinal_ex(ctx, ciphertext + len, &len))
	{
		handleErrors();
		return FAIL;
	}

	*ciphertext_len += len;

	/* Clean up */
	EVP_CIPHER_CTX_free(ctx);

	return SUCCEED;
}

int	decrypt(const EVP_CIPHER *cipher, unsigned char *ciphertext, int ciphertext_len, unsigned char *key,
		unsigned char *iv, unsigned char *plaintext, int *plaintext_len)
{
	EVP_CIPHER_CTX	*ctx;
	int		len;

	/* Create and initialise the context */
	if (NULL == (ctx = EVP_CIPHER_CTX_new()))
	{
		handleErrors();
		return FAIL;
	}

	/* Initialise the decryption operation. IMPORTANT - ensure you use a key
	 * and IV size appropriate for your ciphertext. In this example we are using
	 * 128 bit AES. The IV size for *most* modes is the same as the block size.
	 * For AES this is 128 bits. */
	if (1 != EVP_DecryptInit_ex(ctx, cipher, NULL, key, iv))
	{
		handleErrors();
		return FAIL;
	}

	/* Provide the message to be decrypted, and obtain the plaintext output.
	 * EVP_DecryptUpdate can be called multiple times if necessary */
	if (1 != EVP_DecryptUpdate(ctx, plaintext, &len, ciphertext, ciphertext_len))
	{
		handleErrors();
		return FAIL;
	}

	*plaintext_len = len;

	/* Finalise the decryption. Further plaintext bytes may be written at
	 * this stage. */
	if (1 != EVP_DecryptFinal_ex(ctx, plaintext + len, &len))
	{
		handleErrors();
		return FAIL;
	}

	*plaintext_len += len;

	/* Clean up */
	EVP_CIPHER_CTX_free(ctx);

	return SUCCEED;
}

int	encrypt_cleartext(const char *passphrase, int passphrase_len, const char *secretkey_enc_b64,
		int secretkey_enc_b64_len, const char *secretkey_salt_b64, int secretkey_salt_b64_len,
		const char *cleartext, int cleartext_len, char **cleartext_enc_b64, char **cleartext_salt_b64,
		char *err, size_t err_size)
{
	const EVP_CIPHER	*cipher;
	const EVP_MD		*digest;
	unsigned char		key[EVP_MAX_KEY_LENGTH], iv[EVP_MAX_IV_LENGTH], *secretkey_enc = NULL,
				*secretkey_salt = NULL, *secretkey = NULL, cleartext_salt[8], *cleartext_enc = NULL;
	int			secretkey_enc_len, secretkey_salt_len, secretkey_len, cleartext_enc_len, block_size,
				ret = FAIL;

	/* initialise the library */
	ERR_load_crypto_strings();
	OpenSSL_add_all_algorithms();
	OPENSSL_config(NULL);

	cipher = EVP_aes_128_cbc();
	digest = EVP_sha256();

	block_size = EVP_CIPHER_block_size(cipher);

	/* base64-decode secret key and its salt */
	str_base64_decode_dyn(secretkey_enc_b64, secretkey_enc_b64_len, (char **)&secretkey_enc, &secretkey_enc_len);
	str_base64_decode_dyn(secretkey_salt_b64, secretkey_salt_b64_len, (char **)&secretkey_salt, &secretkey_salt_len);

	/* generate key and IV for secretkey decryption */
	if (0 == EVP_BytesToKey(cipher, digest, secretkey_salt, (const unsigned char *)passphrase, passphrase_len,
			nrounds, key, iv))
	{
		zbx_strlcpy(err, "cannot generate encryption key and initialization vector", err_size);
		goto out;
	}

	/* decrypt the secret key, the ciphertext length is always greater than the input length */
	secretkey = zbx_malloc(secretkey, secretkey_enc_len);
	if (SUCCEED != decrypt(cipher, secretkey_enc, secretkey_enc_len, key, iv, secretkey, &secretkey_len))
	{
		zbx_strlcpy(err, "cannot decrypt secret key", err_size);
		goto out;
	}

	/* generate cleartext salt */
	if (SUCCEED != get_random(cleartext_salt, sizeof(cleartext_salt)))
	{
		zbx_strlcpy(err, "cannot get random salt", err_size);
		goto out;
	}

	/* generate key and IV for cleartext encryption */
	if (0 == EVP_BytesToKey(cipher, digest, cleartext_salt, secretkey, secretkey_len, nrounds, key, iv))
	{
		zbx_strlcpy(err, "cannot generate encryption key and initialization vector", err_size);
		goto out;
	}

	/* encrypt the cleartext, according to docs it can take at most: cleartext_len + block_size - 1 */
	cleartext_enc = zbx_malloc(cleartext_enc, cleartext_len + block_size);
	if (SUCCEED != encrypt(cipher, (unsigned char *)cleartext, cleartext_len, key, iv, cleartext_enc,
			&cleartext_enc_len))
	{
		zbx_strlcpy(err, "cannot encrypt cleartext", err_size);
		goto out;
	}

	/* base64-encode cleartext and salt */
	str_base64_encode_dyn((const char *)cleartext_enc, cleartext_enc_b64, cleartext_enc_len);
	str_base64_encode_dyn((const char *)cleartext_salt, cleartext_salt_b64, sizeof(cleartext_salt));

	ret = SUCCEED;
out:
	if (NULL != cleartext_enc)
		zbx_free(cleartext_enc);

	if (NULL != secretkey)
	{
		memset(secretkey, 0, secretkey_len);
		zbx_free(secretkey);
	}

	if (NULL != secretkey_salt)
		zbx_free(secretkey_salt);

	if (NULL != secretkey_enc)
		zbx_free(secretkey_enc);

	EVP_cleanup();
	ERR_free_strings();

	return ret;
}

int	decrypt_ciphertext(const char *passphrase, int passphrase_len, const char *secretkey_enc_b64,
		int secretkey_enc_b64_len, const char *secretkey_salt_b64, int secretkey_salt_b64_len,
		const char *cleartext_enc_b64, int cleartext_enc_b64_len, const char *cleartext_salt_b64,
		int cleartext_salt_b64_len, char **cleartext, char *err, size_t err_size)
{
	const EVP_CIPHER	*cipher;
	const EVP_MD		*digest;
	unsigned char		key[EVP_MAX_KEY_LENGTH], iv[EVP_MAX_IV_LENGTH], *secretkey_enc = NULL,
				*secretkey_salt = NULL, *secretkey = NULL, *cleartext_salt = NULL, *cleartext_enc = NULL;
	int			secretkey_enc_len, secretkey_salt_len, secretkey_len, cleartext_salt_len,
				cleartext_enc_len, cleartext_len, ret = FAIL;

	/* initialise the library */
	ERR_load_crypto_strings();
	OpenSSL_add_all_algorithms();
	OPENSSL_config(NULL);

	cipher = EVP_aes_128_cbc();
	digest = EVP_sha256();

	/* base64-decode secretkey and its salt */
	str_base64_decode_dyn(secretkey_enc_b64, secretkey_enc_b64_len, (char **)&secretkey_enc, &secretkey_enc_len);
	str_base64_decode_dyn(secretkey_salt_b64, secretkey_salt_b64_len, (char **)&secretkey_salt, &secretkey_salt_len);

	/* generate key and IV for secretkey decryption */
	if (0 == EVP_BytesToKey(cipher, digest, secretkey_salt, (const unsigned char *)passphrase, passphrase_len,
			nrounds, key, iv))
	{
		zbx_strlcpy(err, "cannot generate encryption key and initialization vector", err_size);
		goto out;
	}

	/* decrypt the secret key, the ciphertext length is always greater than the input length */
	secretkey = zbx_malloc(secretkey, secretkey_enc_len);
	if (SUCCEED != decrypt(cipher, secretkey_enc, secretkey_enc_len, key, iv, secretkey, &secretkey_len))
	{
		zbx_strlcpy(err, "cannot decrypt secret key", err_size);
		goto out;
	}

	/* base64-decode cleartext and its salt */
	str_base64_decode_dyn(cleartext_enc_b64, cleartext_enc_b64_len, (char **)&cleartext_enc, &cleartext_enc_len);
	str_base64_decode_dyn(cleartext_salt_b64, cleartext_salt_b64_len, (char **)&cleartext_salt, &cleartext_salt_len);

	/* generate key and IV for cleartext decryption */
	if (0 == EVP_BytesToKey(cipher, digest, cleartext_salt, secretkey, secretkey_len, nrounds, key, iv))
	{
		zbx_strlcpy(err, "cannot generate encryption key and initialization vector", err_size);
		goto out;
	}

	/* decrypt the ciphertext, the result is always smaller than cyphertext */
	*cleartext = zbx_malloc(*cleartext, cleartext_enc_len);
	if (SUCCEED != decrypt(cipher, (unsigned char *)cleartext_enc, cleartext_enc_len, key, iv,
			(unsigned char *)*cleartext, &cleartext_len))
	{
		zbx_strlcpy(err, "cannot decrypt ciphertext", err_size);
		goto out;
	}
	(*cleartext)[cleartext_len] = '\0';

	ret = SUCCEED;
out:
	if (NULL != cleartext_salt)
		zbx_free(cleartext_salt);

	if (NULL != cleartext_enc)
		zbx_free(cleartext_enc);

	if (NULL != secretkey)
	{
		memset(secretkey, 0, secretkey_len);
		zbx_free(secretkey);
	}

	if (NULL != secretkey_salt)
		zbx_free(secretkey_salt);

	if (NULL != secretkey_enc)
		zbx_free(secretkey_enc);

	EVP_cleanup();
	ERR_free_strings();

	return ret;
}
