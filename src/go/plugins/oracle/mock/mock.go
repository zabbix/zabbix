package mock

import (
	"context"
	"errors"

	"github.com/godror/godror"
	zbxlog "golang.zabbix.com/sdk/log"
)

var (
	_ zbxlog.Logger = (*MockLogger)(nil)
)

// MockLogger type is empty zbxlog.Logger implementation for unittests.
type MockLogger struct {
}

// TestConfig type contains a test Oracle server connection credentials.
type TestConfig struct {
	OraURI  string
	OraUser string
	OraPwd  string
	OraSrv  string
}

// Infof empty mock function.
func (ml *MockLogger) Infof(_ string, _ ...any) {} //nolint:revive

// Critf empty mock function.
func (ml *MockLogger) Critf(_ string, _ ...any) {} //nolint:revive

// Errf empty mock function.
func (ml *MockLogger) Errf(_ string, _ ...any) {} //nolint:revive

// Warningf mock function.
func (ml *MockLogger) Warningf(_ string, _ ...any) {} //nolint:revive

// Debugf mock function.
func (ml *MockLogger) Debugf(_ string, _ ...any) {} //nolint:revive

// Tracef empty mock function.
func (ml *MockLogger) Tracef(_ string, _ ...any) {} //nolint:revive

// ServerVersionMock function mocks godror's server version check function.
func ServerVersionMock(_ context.Context, ex godror.Execer) (godror.VersionInfo, error) {
	if ex == nil {
		return godror.VersionInfo{}, errors.New("server version error") //nolint:err113
	}

	return godror.VersionInfo{}, nil
}
