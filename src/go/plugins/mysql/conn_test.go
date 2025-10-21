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

package mysql

import (
	"errors"
	"testing"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/sdk/tlsconfig"
	"golang.zabbix.com/sdk/uri"
	"golang.zabbix.com/sdk/zbxerr"
)

func Test_getTLSDetails(t *testing.T) {
	t.Parallel()

	// Mock URL for testing.
	testURL, _ := uri.New("https://zabbix.com:10051", nil)

	allowedConnections := map[string]bool{
		string(tlsconfig.Disabled):   true,
		string(tlsconfig.Required):   true,
		string(tlsconfig.VerifyCA):   true,
		string(tlsconfig.VerifyFull): true,
	}

	// Define test cases.
	tests := []struct {
		name          string
		ck            connKey
		wantDetails   *tlsconfig.Details
		wantErr       bool
		validationErr error
	}{
		{
			name: "+tlsConnectDisable",
			ck: connKey{
				rawUri:     "zabbix.com",
				uri:        *testURL,
				tlsConnect: string(tlsconfig.Disabled),
			},
			wantDetails: &tlsconfig.Details{
				TLSConnect:         tlsconfig.Disabled,
				RawURI:             "zabbix.com",
				AllowedConnections: allowedConnections,
			},
			wantErr: false,
		},
		{
			name: "+tlsConnectRequireWithClientCerts",
			ck: connKey{
				rawUri:     "zabbix.com",
				uri:        *testURL,
				tlsCert:    "client.crt",
				tlsKey:     "client.key",
				tlsConnect: string(tlsconfig.Required),
			},
			wantDetails: &tlsconfig.Details{
				TLSConnect:         tlsconfig.Required,
				RawURI:             "zabbix.com",
				TLSCertFile:        "client.crt",
				TLSKeyFile:         "client.key",
				AllowedConnections: allowedConnections,
			},
			wantErr: false,
		},
		{
			name: "+tlsConnectVerifyCa",
			ck: connKey{
				rawUri:     "zabbix.com",
				uri:        *testURL,
				tlsCA:      "ca.pem",
				tlsConnect: string(tlsconfig.VerifyCA),
			},
			wantDetails: &tlsconfig.Details{
				TLSConnect:         tlsconfig.VerifyCA,
				RawURI:             "zabbix.com",
				TLSCaFile:          "ca.pem",
				AllowedConnections: allowedConnections,
			},
			wantErr: false,
		},
		{
			name: "+tlsConnectVerifyFull",
			ck: connKey{
				rawUri:     "zabbix.com",
				uri:        *testURL,
				tlsCA:      "ca.pem",
				tlsCert:    "client.crt",
				tlsKey:     "client.key",
				tlsConnect: string(tlsconfig.VerifyFull),
			},
			wantDetails: &tlsconfig.Details{
				TLSConnect:         tlsconfig.VerifyFull,
				RawURI:             "zabbix.com",
				TLSCaFile:          "ca.pem",
				TLSCertFile:        "client.crt",
				TLSKeyFile:         "client.key",
				AllowedConnections: allowedConnections,
			},
			wantErr: false,
		},
		{
			name: "-validationFails",
			ck: connKey{
				rawUri:     "zabbix.com",
				uri:        *testURL,
				tlsConnect: string(tlsconfig.VerifyFull),
			},
			wantErr:       true,
			validationErr: errors.New("invalid TLS configuration"),
		},
	}

	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()
			t.Helper()

			details, err := getTLSDetails(tc.ck)

			if (err != nil) != tc.wantErr {
				t.Fatalf("getTLSDetails() error = %v, wantErr %v", err, tc.wantErr)
			}

			if tc.wantErr {
				if !errors.Is(err, zbxerr.ErrorInvalidConfiguration) &&
					//nolint:staticcheck //old test that still works
					err.Error() != zbxerr.ErrorInvalidConfiguration.Error()+": "+tc.validationErr.Error() {
					t.Errorf("getTLSDetails() error = %v, "+
						"want wrapped error for %v", err, zbxerr.ErrorInvalidConfiguration)
				}

				return
			}

			if diff := cmp.Diff(tc.wantDetails, details, cmp.AllowUnexported(tlsconfig.Details{})); diff != "" {
				t.Errorf("getTLSDetails() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}
