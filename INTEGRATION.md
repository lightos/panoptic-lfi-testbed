# Panoptic E2E integration

The E2E suite launches the testbed and invokes Panoptic as a subprocess against
real HTTP endpoints. It does not mock Panoptic's network layer.

## Local development

Clone Panoptic beside this repository:

```bash
git clone \
  git@github.com:lightos/Panoptic.git ../Panoptic
```

Install its development dependencies if needed:

```bash
python3 -m pip install -e "../Panoptic[dev]"
```

Run the testbed matrix:

```bash
PANOPTIC_DIR=../Panoptic ./tests/e2e.sh
```

`tests/e2e.sh` owns the Compose lifecycle by default. To run the Python matrix
against an already-running target:

```bash
docker compose up --build --wait
E2E_MANAGE_DOCKER=0 \
PANOPTIC_DIR=../Panoptic \
python3 tests/run_e2e.py
docker compose down
```

Environment variables:

| Variable | Default | Purpose |
| --- | --- | --- |
| `PANOPTIC_DIR` | Installed Python package | Add a Panoptic checkout to `PYTHONPATH` |
| `BASE_URL` | `http://127.0.0.1:8080` | Override the local target URL |
| `E2E_MANAGE_DOCKER` | `1` | Set to `0` to preserve an existing Compose stack |

The runner uses temporary output directories and verifies:

- expected and unexpected finding sets;
- JSON and CSV serialization;
- parameter/body redaction;
- owner-only result and retrieved-file modes on POSIX;
- `--write-files` content;
- resume checkpoint behavior;
- dynamic passwd/home and MySQL binlog case expansion.

## GitHub Actions

`.github/workflows/e2e.yml` checks out Panoptic `main`, installs it, and runs
the same script. Use the manual `panoptic_ref` input to test another branch,
tag, or commit. The workflow has read-only repository permissions.

## Adding a case

1. Add a PHP endpoint under `src/`.
2. Prefer `/opt/panoptic-fixtures/proof.txt` as the positive target.
3. Add a custom list under `tests/lists/` only when the path differs.
4. Add a `ScanCase` in `tests/run_e2e.py`.
5. Assert an exact result set unless dynamic expansion is the behavior under test.
6. Add the endpoint to the coverage table in `README.md`.

Do not introduce real credentials, host mounts, cloud deployment definitions,
or a non-loopback port mapping.
