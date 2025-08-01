package conn

import (
	"crypto/tls"
	"errors"
	"testing"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/uri"
)

//nolint:gocyclo,cyclop //this is unit test
func TestGetTLSConfig(t *testing.T) {
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
			name:   "TLS connection type is required",
			params: map[string]string{string(comms.TLSConnect): string(comms.Required)},
			validateFunc: func(t *testing.T, cfg *tls.Config, host string) {
				t.Helper()
				if !cfg.InsecureSkipVerify {
					t.Error("expected InsecureSkipVerify to be true, but it was false")
				}
			},
		},
		{
			name:   "TLS connection type is verify_ca",
			params: map[string]string{string(comms.TLSConnect): string(comms.VerifyCA)},
			validateFunc: func(t *testing.T, cfg *tls.Config, host string) {
				t.Helper()
				if cfg.InsecureSkipVerify {
					t.Error("expected InsecureSkipVerify to be false, but it was true")
				}
				if cfg.ServerName != "" {
					t.Errorf("expected ServerName to be empty, but got %q", cfg.ServerName)
				}
			},
		},
		{
			name:   "TLS connection type is verify_full",
			params: map[string]string{string(comms.TLSConnect): string(comms.VerifyFull)},
			validateFunc: func(t *testing.T, cfg *tls.Config, host string) {
				t.Helper()
				if cfg.InsecureSkipVerify {
					t.Error("expected InsecureSkipVerify to be false, but it was true")
				}
				if diff := cmp.Diff(host, cfg.ServerName); diff != "" {
					t.Errorf("ServerName mismatch (-want +got):\n%s", diff)
				}
			},
		},
		{
			name:          "-tls connection type is disabled",
			params:        map[string]string{string(comms.TLSConnect): string(comms.Disabled)},
			expectedError: errTLSDisabled,
		},
		{
			name:          "-invalid TLS connection type",
			params:        map[string]string{string(comms.TLSConnect): "invalid_type"},
			expectedError: errs.New("Invalid TLS connection type: invalid_type connection type is invalid."),
		},
		{
			name:   "-unsupported but valid TLS connection type",
			params: map[string]string{string(comms.TLSConnect): "some_future_unsupported_type"},
			expectedError: errs.New("Invalid TLS connection type: " +
				"some_future_unsupported_type connection type is invalid."),
		},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			tlsConfig, err := getTLSConfig(redisURI, tc.params)

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
