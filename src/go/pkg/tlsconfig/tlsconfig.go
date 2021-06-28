package tlsconfig

import (
	"crypto/tls"
	"crypto/x509"
	"errors"
	"fmt"
	"io/ioutil"

	"zabbix.com/pkg/uri"
)

type Details struct {
	SessionName string
	TlsConnect  string
	TlsCaFile   string
	TlsCertFile string
	TlsKeyFile  string
	RawUri      string
}

func CreateConfig(details Details, skipVerify bool) (*tls.Config, error) {
	rootCertPool := x509.NewCertPool()
	pem, err := ioutil.ReadFile(details.TlsCaFile)
	if err != nil {
		return nil, err
	}

	if ok := rootCertPool.AppendCertsFromPEM(pem); !ok {
		return nil, errors.New("Failed to append PEM")
	}

	clientCerts := make([]tls.Certificate, 0, 1)
	certs, err := tls.LoadX509KeyPair(details.TlsCertFile, details.TlsKeyFile)
	if err != nil {
		return nil, err
	}

	clientCerts = append(clientCerts, certs)

	if skipVerify {
		return &tls.Config{RootCAs: rootCertPool, Certificates: clientCerts, InsecureSkipVerify: skipVerify}, nil
	}

	url, err := uri.New(details.RawUri, nil)
	if err != nil {
		return nil, err
	}

	return &tls.Config{RootCAs: rootCertPool, Certificates: clientCerts, InsecureSkipVerify: skipVerify, ServerName: url.Host()}, nil
}

func CreateDetails(session, dbConnect, caFile, certFile, keyFile, uri string) (Details, error) {

	if dbConnect != "" && dbConnect != "required" {
		if caFile == "" {
			return Details{}, fmt.Errorf("missing TLS CA file for database uri %s, with session %s", uri, session)
		}
		if certFile == "" {
			return Details{}, fmt.Errorf("missing TLS certificate file for database uri %s, with session %s", uri, session)
		}
		if keyFile == "" {
			return Details{}, fmt.Errorf("missing TLS key file for database uri %s, with session %s", uri, session)
		}
	} else {
		if caFile != "" {
			return Details{}, fmt.Errorf("TLS CA file configuration parameter set without certificates being used for database uri %s, with session %s", uri, session)

		}
		if certFile != "" {
			return Details{}, fmt.Errorf("TLS certificate file configuration parameter set without certificates being used for database uri %s, with session %s", uri, session)

		}
		if keyFile != "" {
			return Details{}, fmt.Errorf(" TLS key file configuration parameter set without certificates being used for database uri %s, with session %s", uri, session)

		}
	}

	return Details{session, dbConnect, caFile, certFile, keyFile, uri}, nil
}
