/*
** Copyright (C) 2001-2024 Zabbix SIA
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

package main

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io/ioutil"
	"net"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"

	"github.com/chromedp/cdproto/emulation"
	"github.com/chromedp/cdproto/network"
	"github.com/chromedp/cdproto/page"
	"github.com/chromedp/chromedp"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/zbxerr"
)

type requestBody struct {
	URL        string            `json:"url"`
	Header     map[string]string `json:"headers"`
	Parameters map[string]string `json:"parameters"`
}

func newRequestBody() *requestBody {
	return &requestBody{"", make(map[string]string), make(map[string]string)}
}

// httpCookiesGet parses Cookie HTTP request header returns HTTP cookies
func (b *requestBody) httpCookiesGet() []*http.Cookie {
	r := http.Request{Header: http.Header{}}
	r.Header.Add("Cookie", b.Header["Cookie"])

	return r.Cookies()
}

func logAndWriteError(w http.ResponseWriter, errMsg string, code int) {
	log.Errf("%s", errMsg)
	w.Header().Set("Content-Type", "application/problem+json")
	w.Header().Set("X-Content-Type-Options", "nosniff")
	w.WriteHeader(code)
	json.NewEncoder(w).Encode(map[string]string{"detail": errMsg})
}

func (h *handler) report(w http.ResponseWriter, r *http.Request) {
	log.Infof("received report request from %s", r.RemoteAddr)

	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		logAndWriteError(w, fmt.Sprintf("Cannot remove port from host for incoming ip %s.", err.Error()), http.StatusInternalServerError)

		return
	}

	if !h.allowedPeers.CheckPeer(net.ParseIP(host)) {
		logAndWriteError(w, fmt.Sprintf("Cannot accept incoming connection for peer: %s.", r.RemoteAddr), http.StatusInternalServerError)

		return
	}

	if r.Method != http.MethodPost {
		logAndWriteError(w, "Method is not supported.", http.StatusMethodNotAllowed)

		return
	}

	if r.Header.Get("Content-Type") != "application/json" {
		logAndWriteError(w, "Content Type is not application/json.", http.StatusMethodNotAllowed)

		return
	}

	b, err := ioutil.ReadAll(r.Body)
	if err != nil {
		logAndWriteError(w, "Can not read body data.", http.StatusInternalServerError)

		return
	}

	req := newRequestBody()
	if err = json.Unmarshal(b, &req); err != nil {
		logAndWriteError(w, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err).Error(), http.StatusInternalServerError)

		return
	}

	opts := chromedp.DefaultExecAllocatorOptions[:]

	if options.IgnoreURLCertErrors == 1 {
		opts = append(opts, chromedp.Flag("ignore-certificate-errors", "1"))
	}

	allocCtx, cancel := chromedp.NewExecAllocator(context.Background(), opts...)
	defer cancel()

	ctx, cancel := chromedp.NewContext(allocCtx)
	defer cancel()

	width, err := strconv.ParseInt(req.Parameters["width"], 10, 64)
	if err != nil {
		logAndWriteError(w, fmt.Sprintf("Incorrect parameter width: %s", err.Error()), http.StatusBadRequest)

		return
	}

	height, err := strconv.ParseInt(req.Parameters["height"], 10, 64)
	if err != nil {
		logAndWriteError(w, fmt.Sprintf("Incorrect parameter height: %s", err.Error()), http.StatusBadRequest)

		return
	}

	u, err := parseUrl(req.URL)
	if err != nil {
		logAndWriteError(w, fmt.Sprintf("Incorrect request url: %s", err.Error()), http.StatusBadRequest)

		return
	}

	if u.Scheme != "http" && u.Scheme != "https" {
		logAndWriteError(w, fmt.Sprintf("Unexpected URL scheme: \"%s\"", u.Scheme), http.StatusBadRequest)

		return
	}

	if !strings.HasSuffix(u.Path, "/zabbix.php") {
		logAndWriteError(w, fmt.Sprintf("Unexpected URL path: \"%s\"", u.Path), http.StatusBadRequest)

		return
	}

	queryParams := u.Query()

	if queryParams.Get("action") != "dashboard.print" {
		logAndWriteError(w, fmt.Sprintf("Unexpected URL action: \"%s\"", queryParams.Get("action")), http.StatusBadRequest)

		return
	}

	log.Tracef(
		"making chrome headless request with parameters url: %s, width: %s, height: %s for report request from %s",
		u.String(), req.Parameters["width"], req.Parameters["height"], r.RemoteAddr)

	var cookieParams []*network.CookieParam

	for _, cookie := range req.httpCookiesGet() {
		cookieParam := network.CookieParam{
			Name:     cookie.Name,
			Value:    cookie.Value,
			URL:      req.URL,
			Domain:   u.Hostname(),
			SameSite: network.CookieSameSiteStrict,
			HTTPOnly: true,
		}

		cookieParams = append(cookieParams, &cookieParam)
	}

	var buf []byte

	if err = chromedp.Run(ctx, chromedp.Tasks{
		network.SetCookies(cookieParams),
		emulation.SetDeviceMetricsOverride(width, height, 1, false),
		prepareDashboard(u.String()),
		chromedp.ActionFunc(func(ctx context.Context) error {
			timeoutContext, cancel := context.WithTimeout(ctx, time.Duration(options.Timeout)*time.Second)
			defer cancel()
			var err error
			buf, _, err = page.PrintToPDF().
				WithPrintBackground(true).
				WithPreferCSSPageSize(true).
				WithPaperWidth(pixels2inches(width)).
				WithPaperHeight(pixels2inches(height)).
				Do(timeoutContext)

			return err
		}),
	}); err != nil {
		logAndWriteError(w, zbxerr.ErrorCannotFetchData.Wrap(err).Error(), http.StatusInternalServerError)

		return
	}

	log.Infof("writing response for report request from %s", r.RemoteAddr)

	w.Header().Set("Content-type", "application/pdf")
	w.Write(buf)

	return
}

func pixels2inches(value int64) float64 {
	return float64(value) * 0.0104166667
}

func prepareDashboard(url string) chromedp.ActionFunc {
	return func(ctx context.Context) error {
		_, _, _, err := page.Navigate(url).Do(ctx)
		if err != nil {
			return err
		}

		return waitForDashboardReady(ctx, url)
	}
}

func waitForDashboardReady(ctx context.Context, url string) error {
	const wrapperIsReady = ".wrapper.is-ready"
	const timeout = time.Minute

	expression := fmt.Sprintf("document.querySelector('%s') !== null", wrapperIsReady)
	var isReady bool

	err := chromedp.Run(ctx, chromedp.Poll(expression, &isReady, chromedp.WithPollingTimeout(timeout)))

	if errors.Is(err, chromedp.ErrPollingTimeout) {
		log.Warningf("timeout occurred while dashboard was getting ready, url: '%s'", url)
	} else if err != nil {
		log.Warningf("error while dashboard was getting ready: %s, url: '%s'", err.Error(), url)
	} else if !isReady {
		log.Warningf("should never happen, dashboard failed to get ready with no error, url: '%s'", url)
	}

	return nil
}

func parseUrl(u string) (*url.URL, error) {
	if u == "" {
		return nil, errors.New("url is empty")
	}

	parsed, err := url.Parse(u)
	if err != nil {
		return nil, err
	}

	if parsed.Scheme == "" {
		return nil, errors.New("url is missing scheme")
	}

	return parsed, nil
}
