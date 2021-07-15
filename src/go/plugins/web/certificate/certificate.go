/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
package webcertificate

import (
	"crypto/sha1"
	"crypto/tls"
	"crypto/x509"
	"encoding/json"
	"errors"
	"fmt"
	"math/big"
	"net"
	"time"

	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxerr"
)

type Output struct {
	X509        Cert             `json:"x509"`
	Result      ValidationResult `json:"result"`
	Fingerprint string           `json:"fingerprint"`
}

type CertTime struct {
	Value     string `json:"value"`
	Timestamp int64  `json:"timestamp"`
}

type Cert struct {
	Version            int      `json:"version"`
	Serial             *big.Int `json:"serial"`
	SignatureAlgorithm string   `json:"signature_algorithm"`
	Issuer             string   `json:"issuer"`
	NotBefore          CertTime `json:"not_before"`
	NotAfter           CertTime `json:"not_after"`
	Subject            string   `json:"subject"`
	AlternativeNames   []string `json:"alternative_names"`
	PublicKeyAlgorithm string   `json:"public_key_algorithm"`
}

type ValidationResult struct {
	Value   string `json:"value"`
	Message string `json:"message"`
}

type Options struct {
	Timeout int `conf:"optional,range=1:30"`
}

type Plugin struct {
	plugin.Base
	options Options
}

var impl Plugin

func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}

	p.options.Timeout = global.Timeout
}

func (p *Plugin) Validate(options interface{}) error {
	var o Options
	return conf.Unmarshal(options, &o)
}

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (interface{}, error) {
	if key != "web.certificate.get" {
		return nil, plugin.UnsupportedMetricError
	}

	hostname, port, ip, dnsName, err := getParameters(params)
	if err != nil {
		return nil, err
	}

	var connection string
	if ip == "" {
		connection = fmt.Sprintf("%s:%s", hostname, port)
	} else {
		connection = fmt.Sprintf("%s:%s", ip, port)
	}

	cert, err := getCertificatesPEM(connection, p.options.Timeout)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	var o Output

	cert.KeyUsage = x509.KeyUsageCertSign

	o.X509 = Cert{
		cert.Version, cert.SerialNumber, cert.SignatureAlgorithm.String(), cert.Issuer.ToRDNSequence().String(),
		CertTime{cert.NotBefore.Format(time.RFC822), cert.NotBefore.Unix()},
		CertTime{cert.NotAfter.Format(time.RFC822), cert.NotAfter.Unix()}, cert.Subject.ToRDNSequence().String(),
		cert.DNSNames, cert.PublicKeyAlgorithm.String(),
	}

	if _, err := cert.Verify(x509.VerifyOptions{DNSName: dnsName}); err != nil {
		if errors.As(err, &x509.UnknownAuthorityError{}) && o.X509.Subject == o.X509.Issuer {
			o.Result = ValidationResult{"valid-but-self-signed",
				"certificate verified successfully, but determined to be self signed"}
		} else {
			o.Result = ValidationResult{"invalid", fmt.Sprintf("failed to verify certificate: %s", err.Error())}
		}
	} else {
		o.Result = ValidationResult{"valid", "certificate verified successfully"}
	}

	o.Fingerprint = fmt.Sprintf("%x", sha1.Sum(cert.Raw))

	b, err := json.Marshal(o)
	if err != nil {
		return nil, err
	}

	return string(b), nil
}

func getParameters(params []string) (hostname, port, ip, dnsName string, err error) {
	switch len(params) {
	case 3:
		ip = params[2]
		port = params[1]
		hostname = params[0]
		dnsName = hostname
	case 2:
		port = params[1]
		hostname = params[0]
		dnsName = hostname
	case 1:
		address := params[0]
		dnsName = address

		addr := net.ParseIP(address)
		if addr != nil {
			ip = address
		} else {
			hostname = address
		}
	default:
		err = zbxerr.ErrorInvalidParams

		return
	}

	if port == "" {
		port = "443"
	}

	return
}

func getCertificatesPEM(address string, timeout int) (*x509.Certificate, error) {
	var dialer net.Dialer
	dialer.Timeout = time.Duration(timeout) * time.Second

	conn, err := tls.DialWithDialer(&dialer, "tcp", address, &tls.Config{InsecureSkipVerify: true})
	if err != nil {
		return nil, err
	}
	defer conn.Close()

	certs := conn.ConnectionState().PeerCertificates
	if len(certs) < 1 {
		return nil, zbxerr.ErrorCannotFetchData
	}

	return certs[0], nil
}

func init() {
	plugin.RegisterMetrics(&impl, "WebCertificate", "web.certificate.get", "Get TLS/SSL website certificate.")
}
