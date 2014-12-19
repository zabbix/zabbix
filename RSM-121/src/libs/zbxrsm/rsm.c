#include <openssl/bio.h>
#include <openssl/ssl.h>
#include <openssl/err.h>
#include <openssl/pem.h>
#include <openssl/conf.h>
#include <openssl/x509.h>
#include <openssl/x509_vfy.h>
#include <string.h>

#include "common.h"
#include "base64.h"
#include "rsm.h"

static int	nrounds = 500;

int	rsm_ssl_init()
{
	static int	ssl_initialized = 0;

	if (0 == ssl_initialized)
	{
		OpenSSL_add_all_algorithms();
		ERR_load_BIO_strings();
		ERR_load_crypto_strings();
		SSL_load_error_strings();
		OPENSSL_config(NULL);

		/* initialize SSL library and register algorithms */
		if (0 > SSL_library_init())
			return FAIL;

		ssl_initialized = 1;
	}

	return SUCCEED;
}

int     get_random(void *data, int bytes)
{
	int	fd;

	if (-1 == (fd = open("/dev/random", O_RDONLY)))
		return FAIL;

	read(fd, data, bytes);
	close(fd);

	return SUCCEED;
}

void	zbx_ssl_get_error(char *err, size_t err_size)
{
	const char	*reason;
	unsigned long	e;

	*err = '\0';
	while (0 != (e = ERR_get_error()))
	{
		if (NULL != (reason = ERR_reason_error_string(e)) && '\0' != *reason)
			zbx_strlcpy(err, reason, err_size);
	}

	if ('\0' == *err)
		zbx_strlcpy(err, "no error", err_size);
}

int	encrypt(const EVP_CIPHER *cipher, unsigned char *plaintext, int plaintext_len, unsigned char *key,
		unsigned char *iv, unsigned char *ciphertext, int *ciphertext_len, char *err, size_t err_size)
{
	EVP_CIPHER_CTX	*ctx;
	int		len, ret = FAIL;

	/* create and initialise the context */
	if (NULL == (ctx = EVP_CIPHER_CTX_new()))
	{
		zbx_strlcpy(err, "cannot create SSL object (out of memory?)", err_size);
		goto out;
	}

	/* Initialise the encryption operation. NB! Ensure you use a key and IV size appropriate for your cipher. */
	if (1 != EVP_EncryptInit_ex(ctx, cipher, NULL, key, iv))
	{
		zbx_ssl_get_error(err, err_size);
		goto out;
	}

	/* provide the message to be encrypted, and obtain the ecrypted output */
	if (1 != EVP_EncryptUpdate(ctx, ciphertext, &len, plaintext, plaintext_len))
	{
		zbx_ssl_get_error(err, err_size);
		goto out;
	}

	*ciphertext_len = len;

	/* finalise the encryption, further ciphertext bytes may be written at this stage */
	if (1 != EVP_EncryptFinal_ex(ctx, ciphertext + len, &len))
	{
		zbx_ssl_get_error(err, err_size);
		goto out;
	}

	*ciphertext_len += len;

	ret = SUCCEED;
out:
	if (NULL != ctx)
		EVP_CIPHER_CTX_free(ctx);

	return ret;
}

static int	decrypt(const EVP_CIPHER *cipher, unsigned char *ciphertext, int ciphertext_len, unsigned char *key,
		unsigned char *iv, unsigned char *plaintext, int *plaintext_len, char *err, size_t err_size)
{
	EVP_CIPHER_CTX	*ctx;
	int		len, ret = FAIL;

	/* create and initialise the context */
	if (NULL == (ctx = EVP_CIPHER_CTX_new()))
	{
		zbx_strlcpy(err, "cannot create SSL object (out of memory?)", err_size);
		goto out;
	}

	/* Initialise the decryption operation. NB! Ensure you use a key and IV size appropriate for your cipher. */
	if (1 != EVP_DecryptInit_ex(ctx, cipher, NULL, key, iv))
	{
		zbx_ssl_get_error(err, err_size);
		goto out;
	}

	/* provide the message to be decrypted, and obtain the plaintext output */
	if (1 != EVP_DecryptUpdate(ctx, plaintext, &len, ciphertext, ciphertext_len))
	{
		zbx_ssl_get_error(err, err_size);
		goto out;
	}

	*plaintext_len = len;

	/* finalise the encryption, further plaintext bytes may be written at this stage */
	if (1 != EVP_DecryptFinal_ex(ctx, plaintext + len, &len))
	{
		zbx_ssl_get_error(err, err_size);
		goto out;
	}

	*plaintext_len += len;

	ret = SUCCEED;
out:
	if (NULL != ctx)
		EVP_CIPHER_CTX_free(ctx);

	return ret;
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
	if (SUCCEED != decrypt(cipher, secretkey_enc, secretkey_enc_len, key, iv, secretkey, &secretkey_len,
			err, err_size))
	{
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
			&cleartext_enc_len, err, err_size))
	{
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
	if (SUCCEED != decrypt(cipher, secretkey_enc, secretkey_enc_len, key, iv, secretkey, &secretkey_len,
			err, err_size))
	{
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
			(unsigned char *)*cleartext, &cleartext_len, err, err_size))
	{
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

	return ret;
}

int	zbx_read_stdin(const char *prompt, char *output, size_t output_size, char *err, size_t err_size)
{
	struct termios	oflags, nflags;
	int		ret = FAIL;

	/* disable echo */
	tcgetattr(fileno(stdin), &oflags);
	nflags = oflags;

	nflags.c_lflag &= ~ECHO;
	nflags.c_lflag |= ECHONL;

	if (0 != tcsetattr(fileno(stdin), TCSANOW, &nflags))
	{
		if (NULL != err)
			zbx_strlcpy(err, strerror(errno), err_size);
		goto out;
	}

	printf(prompt);
	if (output != fgets(output, output_size, stdin))
	{
		if (NULL != err)
			zbx_strlcpy(err, "cannot read input line", err_size);
		goto out;
	}

	if ('\n' != output[strlen(output) - 1])
	{
		char	buf[128];

		do
		{
			fgets(buf, sizeof(buf), stdin);
		}
		while ('\n' != buf[strlen(buf) - 1]);

		if (NULL != err)
			zbx_snprintf(err, err_size, "input line over limit (" ZBX_FS_SIZE_T " characters)", output_size);
		goto out;
	}

	output[strlen(output) - 1] = '\0';

	ret = SUCCEED;
out:
	tcsetattr(fileno(stdin), TCSANOW, &oflags);

	return ret;
}
