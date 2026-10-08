# Panoptic LFI Testbed

This repository is a deliberately vulnerable, deterministic web application
for end-to-end testing of
[Panoptic](https://github.com/lightos/Panoptic). It exercises local file
inclusion and path traversal across the transports and transformations
supported by Panoptic.

> [!CAUTION]
> Never deploy this application. The supplied Compose configuration binds only
> to `127.0.0.1` and runs with a read-only filesystem. Do not weaken those
> controls or expose ports 8080 or 8081 to another host.

## Quick start

```bash
docker compose up --build --wait
```

The testbed is then available at <http://127.0.0.1:8080> (Apache) and
<http://127.0.0.1:8081> (the raw-path service described below). Stop it when
finished:

```bash
docker compose down
```

The deterministic proof file is:

```text
/opt/panoptic-fixtures/proof.txt
```

Its content begins with `PANOPTIC_E2E_PROOF`, making findings independent of
the files supplied by the base image.

## Run the complete E2E suite

Point the runner at a Panoptic checkout. The script builds and starts this
container, runs the black-box matrix, and removes the container afterward.

```bash
PANOPTIC_DIR=/path/to/Panoptic ./tests/e2e.sh
```

To test Panoptic `main`:

```bash
git clone \
  git@github.com:lightos/Panoptic.git ../Panoptic
PANOPTIC_DIR=../Panoptic ./tests/e2e.sh
```

Set `E2E_MANAGE_DOCKER=0` when the testbed is already running. `BASE_URL`
defaults to `http://127.0.0.1:8080` and `RAW_BASE_URL` to
`http://127.0.0.1:8081`.

## Coverage

| Case | Endpoint | Panoptic behavior |
| --- | --- | --- |
| GET query | `classic.php` | `--param file` and automatic parameter detection |
| Form POST | `post.php` | `--data` with `--param` |
| JSON body | `json_api.php` | Top-level `FUZZ` replacement |
| Nested JSON | `nested_json.php` | Nested `FUZZ` replacement |
| Opaque body | `raw_body.php` | Whole-body `FUZZ` replacement |
| Cookie | `cookie.php` | `Cookie: lang=FUZZ` |
| Custom header | `header.php` | `X-Template: FUZZ` |
| Base64 | `base64.php` | `--base64` |
| PATH_INFO | `pathinfo.php` | `--path-based` |
| Rewritten path | `/files/view/...` | Double-encoded slash replacement |
| Naive filter | `filtered.php` | Prefix plus multiplier bypass |
| Split extension | `param.php` | `--ext-param type` |
| Alternate slash | `classic.php` | `--replace-slash /./` |
| Double decode | `double_decode.php` | `--replace-slash %252F` |
| Legacy NUL | `legacy_nullbyte.php` | Explicit modern-runtime simulator for `--postfix %00` |
| Status codes | `status.php` | Default, `--match-code`, and `--filter-code` |
| Redirect | `redirect.php` | Redirect following enabled and disabled |
| Authenticated request | `auth.php` | `--cookie panoptic_auth=allowed` |
| Negative control | `safe.php` | Reflected candidate produces no finding |
| Raw path traversal | `raw` service `/view/...` | `--path-based --prefix ../ --multiplier 4` sends literal `../` |
| POST with query gate | `post_query.php?action=view` | `--data file=FUZZ` (or `--param file`) keeps the query string |
| XML body | `xml_body.php` | `--data '<req><file>FUZZ</file></req>'` with `Content-Type: application/xml` |
| Backslash traversal | `backslash.php` | Simulator: `--prefix '..\'` bypasses a `../` filter |
| Reflected encodings | `reflected_encoded.php` | Negative control under `--base64` and `--prefix ../` |
| Dynamic soft 404 | `soft404_dynamic.php` | Negative control: random token and timestamp on every 200 |
| Dynamic page | `dynamic_vuln.php` | Real sink inside a randomized page |
| Hostile passwd | `hostile_passwd.php` | Escape-sequence, relative, and formula homes stay inert |
| Windows JSON | `windows_json.php` | Explicit Windows/backslash simulator |
| Dynamic passwd parsing | `parser.php` | `/etc/passwd` expands a controlled home file |
| Dynamic MySQL parsing | `parser.php` | `mysql-bin.index` expands a controlled binlog |
| Artifacts | Multiple | JSON, CSV, `--write-files`, redaction, modes, and resume |

The legacy NUL, Windows, and backslash endpoints are labeled simulators
because supported PHP correctly rejects NUL-containing include paths and the
default container is Linux. Their purpose is to test Panoptic's payload
construction without adding an obsolete PHP runtime or a second operating
system.

### Raw-path service

Apache resolves `.` and `..` segments in the request path before PHP runs, so
it cannot test a literal `../` in the URL path. The `raw` Compose service runs
the same image with PHP's built-in server (`php -S`) and `raw/router.php`.
That server leaves `REQUEST_URI` exactly as received (it normalizes only
`SCRIPT_NAME` and `PHP_SELF`), and the router strips `/view/` and includes the
rest relative to `/opt/panoptic-raw/files`. A client that collapses dot
segments sends `/opt/panoptic-fixtures/proof.txt` instead and receives
`404 Unknown route`:

```bash
curl --path-as-is \
  "http://127.0.0.1:8081/view/../../../../opt/panoptic-fixtures/proof.txt"
# 200 PANOPTIC_E2E_PROOF_4f6c8a71
curl "http://127.0.0.1:8081/view/../../../../opt/panoptic-fixtures/proof.txt"
# 404 Unknown route (curl collapses the path by default)
```

### Hostile passwd

`hostile_passwd.php` serves `fixtures/passwd-hostile` for `/etc/passwd`. Its
homes contain a CSI colour sequence and an OSC title sequence, a relative path
(`relative/../../x`), a formula (`=HYPERLINK(1)`), and one normal home,
`/home/hostile-ok`, which holds `.profile`. Every requested path is logged on
the container's `/tmp`; `hostile_log.php` returns the log (GET) or clears it
(POST), so the runner can prove that no hostile home was expanded.

## A few manual examples

Direct query parameter:

```bash
panoptic \
  --url "http://127.0.0.1:8080/classic.php?file=test.txt" \
  --load tests/lists/proof.txt --auto
```

Custom header:

```bash
panoptic \
  --url "http://127.0.0.1:8080/header.php" \
  --header "X-Template: FUZZ" \
  --load tests/lists/proof.txt --auto
```

Rewritten, double-encoded path:

```bash
panoptic \
  --url "http://127.0.0.1:8080/files/view/test.txt" \
  --path-based --replace-slash "%252F" \
  --load tests/lists/proof.txt --auto
```

Safe negative control:

```bash
panoptic \
  --url "http://127.0.0.1:8080/safe.php?file=test.txt" \
  --load tests/lists/proof.txt --auto
```

The negative control should report no findings.

## Testbed boundaries

- No host files or Docker sockets are mounted into the container.
- The published ports are restricted to IPv4 loopback.
- The container filesystem is read-only except for isolated temporary filesystems.
- Credentials and file contents are synthetic.
- The supported PHP Apache image is used so unrelated legacy-runtime flaws are
  not part of the test surface.

The source intentionally contains unsafe file operations. Static-analysis
findings for the documented endpoints are expected; unintended exposure,
container escape, compromised dependencies, or failures of the safety boundary
are not.

## Contributing

When adding a case:

1. Use a deterministic synthetic fixture.
2. Add both the endpoint and an assertion to `tests/run_e2e.py`.
3. Include a negative assertion when the case affects response heuristics.
4. Keep the container localhost-only and free of host mounts.
5. Document whether the endpoint is a real sink or a simulator.

See [INTEGRATION.md](INTEGRATION.md) for runner and CI details and
[SECURITY.md](SECURITY.md) for the reporting policy.

## License

MIT
