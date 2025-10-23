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

package conn

import (
	"crypto/tls"
	"crypto/x509"
	"testing"

	"github.com/google/go-cmp/cmp"
	"github.com/google/go-cmp/cmp/cmpopts"
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/tlsconfig"
	"golang.zabbix.com/sdk/uri"
)

func Test_getTLSConfig(t *testing.T) {
	t.Parallel()

	// Mocking the URI for consistent test inputs.
	redisURI, err := uri.New("redis://my-redis-host:6379", nil)
	if err != nil {
		t.Fatalf("failed to parse redis URI: %v", err)
	}

	type args struct {
		ck *connKey
	}

	tests := []struct {
		name    string
		args    args
		want    *tls.Config
		wantErr bool
	}{
		{
			name: "+required",
			args: args{
				ck: createConnKey(
					redisURI,
					map[string]string{string(comms.TLSConnect): string(tlsconfig.Required)},
				),
			},
			//nolint:gosec //unit test does not care about tls min version
			want: &tls.Config{
				InsecureSkipVerify: true,
				RootCAs:            x509.NewCertPool(),
			},
		},
		{
			name: "+verifyCA",
			args: args{
				ck: createConnKey(
					redisURI,
					map[string]string{string(comms.TLSConnect): string(tlsconfig.VerifyCA)},
				),
			},
			//nolint:gosec //unit test does not care about tls min version
			want: &tls.Config{
				InsecureSkipVerify:    true,
				ServerName:            "",
				RootCAs:               x509.NewCertPool(),
				VerifyPeerCertificate: nil, //check is ignored because it will try to compare memory address.
			},
			wantErr: false,
		},
		{
			name: "+verifyFull",
			args: args{
				ck: createConnKey(
					redisURI,
					map[string]string{string(comms.TLSConnect): string(tlsconfig.VerifyFull)},
				),
			},
			//nolint:gosec //unit test does not care about tls min version
			want: &tls.Config{
				InsecureSkipVerify: false,
				ServerName:         "",
				RootCAs:            x509.NewCertPool(),
			},
			wantErr: false,
		},
		{
			name: "+disabled",
			args: args{
				ck: createConnKey(
					redisURI,
					map[string]string{string(comms.TLSConnect): string(tlsconfig.Disabled)},
				),
			},
			want:    nil,
			wantErr: false,
		},
		{
			name: "-invalid",
			args: args{
				ck: createConnKey(
					redisURI,
					map[string]string{string(comms.TLSConnect): "invalid_type"},
				),
			},
			want:    nil,
			wantErr: true,
		},
		{
			name: "-unsupportedButValid",
			args: args{
				ck: createConnKey(
					redisURI,
					map[string]string{string(comms.TLSConnect): "some_future_unsupported_type"},
				),
			},
			want:    nil,
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got, err := getTLSConfig(tt.args.ck)
			if (err != nil) != tt.wantErr {
				t.Fatalf("getTLSConfig() error = %v, wantErr %v", err, tt.wantErr)
			}

			diff := cmp.Diff(
				tt.want,
				got,
				//nolint:gosec //unit test does not care about tls min version
				cmpopts.IgnoreUnexported(tls.Config{}),
				//nolint:gosec //unit test does not care about tls min version
				cmpopts.IgnoreFields(tls.Config{}, "VerifyPeerCertificate"),
			)
			if diff != "" {
				t.Fatalf("getTLSConfig() = %s", diff)
			}
		})
	}
}
