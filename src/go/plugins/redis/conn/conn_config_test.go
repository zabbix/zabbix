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
	"errors"
	"testing"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/tlsconfig"
	"golang.zabbix.com/sdk/uri"
)

//nolint:gocyclo,cyclop //this is unit test and complexity rises from the possible test cases
func Test_getTLSConfig(t *testing.T) {
	t.Parallel()

	// Mocking the URI for consistent test inputs.
	redisURI, err := uri.New("redis://my-redis-host:6379", nil)
	if err != nil {
		t.Fatalf("failed to parse redis URI: %v", err)
	}

	// Define test cases in a table-driven format.
	testCases := []struct {
		name          string
		params        map[string]string
		expectedError error
		validateFunc  func(t *testing.T, cfg *tls.Config, host string)
	}{
		{
			name:   "+required",
			params: map[string]string{string(comms.TLSConnect): string(tlsconfig.Required)},
			validateFunc: func(t *testing.T, cfg *tls.Config, host string) {
				t.Helper()
				if !cfg.InsecureSkipVerify {
					t.Error("expected InsecureSkipVerify to be true, but it was false")
				}
			},
			expectedError: nil,
		},
		{
			name:   "+verifyCA",
			params: map[string]string{string(comms.TLSConnect): string(tlsconfig.VerifyCA)},
			validateFunc: func(t *testing.T, cfg *tls.Config, host string) {
				t.Helper()
				if !cfg.InsecureSkipVerify {
					t.Error("expected InsecureSkipVerify to be true, but it was false")
				}
				if cfg.ServerName != "" {
					t.Errorf("expected ServerName to be empty, but got %q", cfg.ServerName)
				}
				if cfg.VerifyPeerCertificate == nil {
					t.Error("expected VerifyPeerCertificate to be set, but it was nil")
				}
			},
			expectedError: nil,
		},
		{
			name:   "+verifyFull",
			params: map[string]string{string(comms.TLSConnect): string(tlsconfig.VerifyFull)},
			validateFunc: func(t *testing.T, cfg *tls.Config, host string) {
				t.Helper()
				if cfg.InsecureSkipVerify {
					t.Error("expected InsecureSkipVerify to be false, but it was true")
				}
			},
			expectedError: nil,
		},
		{
			name:          "+disabled",
			params:        map[string]string{string(comms.TLSConnect): string(tlsconfig.Disabled)},
			expectedError: nil,
		},
		{
			name:          "-invalid",
			params:        map[string]string{string(comms.TLSConnect): "invalid_type"},
			expectedError: errs.New("Invalid TLS connection type: invalid_type connection type is invalid."),
		},
		{
			name:   "-unsupportedButValid",
			params: map[string]string{string(comms.TLSConnect): "some_future_unsupported_type"},
			expectedError: errs.New("Invalid TLS connection type: " +
				"some_future_unsupported_type connection type is invalid."),
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			ck := createConnKey(redisURI, tc.params)
			tlsConfig, err := getTLSConfig(ck)

			// Check for expected errors.
			if tc.expectedError != nil {
				if err == nil {
					t.Fatalf("expected error %q, but got nil", tc.expectedError)
				}

				if !errors.Is(err, tc.expectedError) && err.Error() != tc.expectedError.Error() {
					t.Fatalf("expected error to be %q or wrap it, but got %q", tc.expectedError, err)
				}

				return
			}

			if err != nil {
				t.Fatalf("unexpected error: %v", err)
			}

			// Validate the returned tls.Config.
			if tc.validateFunc != nil {
				tc.validateFunc(t, tlsConfig, redisURI.Host())
			}
		})
	}
}
