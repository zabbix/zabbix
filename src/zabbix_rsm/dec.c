#include <string.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <unistd.h>

#include "common.h"
#include "rsm.h"

const char      *progname = NULL;
const char	title_message[] = "Password decrypter";
const char	*help_message[] = {NULL};
const char	usage_message[] = "<secretkey_enc_b64> <secretkey_salt_b64> <password_enc_b64> <password_salt_b64>\n"
		"Decrypt EPP password using secret key and salt\n"
		"Parameters:\n"
		"    <secretkey_enc_b64>         encrypted secret key for password decryption, base64-encoded\n"
		"    <secretkey_salt_b64>        salt for secret key decryption, base64-encoded\n"
		"    <password_enc_b64>          encrypted password, base64-encoded\n"
		"    <password_salt_b64>         password salt, base64-encoded";

int	main(int argc, char *argv[])
{
	const char	*secretkey_enc_b64, *secretkey_salt_b64, *password_enc_b64, *password_salt_b64;
	char		passphrase[128], *password = NULL, err[128];

	progname = get_program_name(argv[0]);

	if (argc != 5)
	{
		usage();
		exit(1);
	}

	secretkey_enc_b64 = argv[1];
	secretkey_salt_b64 = argv[2];
	password_enc_b64 = argv[3];
	password_salt_b64 = argv[4];

	if (SUCCEED != zbx_read_stdin("Enter EPP passphrase: ", passphrase, sizeof(passphrase)))
	{
		fprintf(stderr, "an error occured while requesting EPP passphrase\n");
		goto out;
	}

	/* initialize the library */
	if (SUCCEED != rsm_ssl_init())
	{
		fprintf(stderr, "cannot initialize SSL library\n");
		goto out;
	}

	if (SUCCEED != decrypt_ciphertext(passphrase, strlen(passphrase), secretkey_enc_b64, strlen(secretkey_enc_b64),
			secretkey_salt_b64, strlen(secretkey_salt_b64), password_enc_b64, strlen(password_enc_b64),
			password_salt_b64, strlen(password_salt_b64), &password, err, sizeof(err)))
	{
		fprintf(stderr, "cannot encrypt password: %s\n", err);
		goto out;
	}

	printf("password: %s\n", password);
out:
	if (NULL != password)
	{
		memset(password, 0, strlen(password));
		zbx_free(password);
	}

	return 0;
}
