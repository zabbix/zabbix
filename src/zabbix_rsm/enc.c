#include <string.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <unistd.h>

#include "common.h"
#include "rsm.h"

const char      *progname = NULL;
const char	title_message[] = "Password encrypter";
const char	*help_message[] = {NULL};
const char	usage_message[] = "<secretkey_enc_b64> <secretkey_salt_b64>\n"
		"Encrypt EPP password using secret key and salt\n"
		"Parameters:\n"
		"    <secretkey_enc_b64>         encrypted secret key for password encryption, base64-encoded\n"
		"    <secretkey_salt_b64>        salt for secret key decryption, base64-encoded\n"
		"Optional:\n"
		"    <noprompt>                  do not print prompt when requesting EPP passphrase and password";

int	main(int argc, char *argv[])
{
	const char	*secretkey_enc_b64, *secretkey_salt_b64, *prompt = 0;
	char		*password_enc_b64 = NULL, *password_salt_b64 = NULL, passphrase[128], password[128], err[128];

	progname = get_program_name(argv[0]);

	if (argc < 3)
	{
		usage();
		exit(1);
	}

	if (3 == argc || 0 != strcmp("noprompt", argv[3]))
		prompt = "Enter EPP passphrase: ";
	if (SUCCEED != zbx_read_stdin(prompt, passphrase, sizeof(passphrase)))
	{
		fprintf(stderr, "an error occured while requesting EPP passphrase\n");
		goto out;
	}

	if (3 == argc || 0 != strcmp("noprompt", argv[3]))
		prompt = "Enter EPP password: ";
	if (SUCCEED != zbx_read_stdin(prompt, password, sizeof(password)))
	{
		fprintf(stderr, "an error occured while requesting EPP password\n");
		goto out;
	}

	secretkey_enc_b64 = argv[1];
	secretkey_salt_b64 = argv[2];

	/* initialize the library */
	if (SUCCEED != rsm_ssl_init())
	{
		fprintf(stderr, "cannot initialize SSL library\n");
		goto out;
	}

	if (SUCCEED != encrypt_cleartext(passphrase, strlen(passphrase), secretkey_enc_b64, strlen(secretkey_enc_b64),
			secretkey_salt_b64, strlen(secretkey_salt_b64), password, strlen(password), &password_enc_b64,
			&password_salt_b64, err, sizeof(err)))
	{
		fprintf(stderr, "cannot encrypt password: %s\n", err);
		goto out;
	}

	printf("%s|%s\n", password_enc_b64, password_salt_b64);
out:
	zbx_free(password_enc_b64);
	zbx_free(password_salt_b64);

	return 0;
}
