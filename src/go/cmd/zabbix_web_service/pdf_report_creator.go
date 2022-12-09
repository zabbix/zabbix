/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

	"git.zabbix.com/ap/plugin-support/log"
	"git.zabbix.com/ap/plugin-support/zbxerr"
	"github.com/chromedp/cdproto/emulation"
	"github.com/chromedp/cdproto/network"
	"github.com/chromedp/cdproto/page"
	"github.com/chromedp/chromedp"
)

type requestBody struct {
	URL        string            `json:"url"`
	Header     map[string]string `json:"headers"`
	Parameters map[string]string `json:"parameters"`
}

func newRequestBody() *requestBody {
	return &requestBody{"", make(map[string]string), make(map[string]string)}
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

	var buf []byte

	if err = chromedp.Run(ctx, chromedp.Tasks{
		network.SetExtraHTTPHeaders(network.Headers(map[string]interface{}{"Cookie": req.Header["Cookie"]})),
		emulation.SetDeviceMetricsOverride(width, height, 1, false),
		navigateAndWaitFor(u.String(), "networkIdle"),
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

func navigateAndWaitFor(url string, eventName string) chromedp.ActionFunc {
	return func(ctx context.Context) error {
		_, _, _, err := page.Navigate(url).Do(ctx)
		if err != nil {
			return err
		}

		return waitFor(ctx, eventName)
	}
}

// This comment is taken from the proof of concept example
//
// waitFor blocks until eventName is received.
// Examples of events you can wait for:
//     init, DOMContentLoaded, firstPaint,
//     firstContentfulPaint, firstImagePaint,
//     firstMeaningfulPaintCandidate,
//     load, networkAlmostIdle, firstMeaningfulPaint, networkIdle
//
// This is not super reliable, I've already found incidental cases where
// networkIdle was sent before load. It's probably smart to see how
// puppeteer implements this exactly.
func waitFor(ctx context.Context, eventName string) error {
	ch := make(chan struct{})
	cctx, cancel := context.WithCancel(ctx)
	chromedp.ListenTarget(cctx, func(ev interface{}) {
		switch e := ev.(type) {
		case *page.EventLifecycleEvent:
			if e.Name == eventName {
				cancel()
				close(ch)
			}
		}
	})
	select {
	case <-ch:
		return nil
	case <-ctx.Done():
		return ctx.Err()
	}
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
