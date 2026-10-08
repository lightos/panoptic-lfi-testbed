#!/usr/bin/env python3
"""Black-box Panoptic E2E suite for the deliberately vulnerable testbed."""

from __future__ import annotations

import csv
import json
import os
import stat
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request
from dataclasses import dataclass
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LISTS = ROOT / "tests" / "lists"
BASE_URL = os.environ.get("BASE_URL", "http://127.0.0.1:8080").rstrip("/")
# PHP built-in server (Compose service "raw") that sees the request target
# before any ../ normalization; Apache would resolve the dot segments first.
RAW_BASE_URL = os.environ.get("RAW_BASE_URL", "http://127.0.0.1:8081").rstrip("/")
PROOF_PATH = "/opt/panoptic-fixtures/proof.txt"
PASSWD_PATH = "/etc/passwd"
HOME_PATH = "/home/panoptic/.bash_history"
MYSQL_INDEX_PATH = "/var/log/mysql-bin.index"
MYSQL_LOG_PATH = "/var/log/mysql-bin.000001"
WINDOWS_PATH = r"C:\Windows\win.ini"
HOSTILE_HOME = "/home/hostile-ok"
HOSTILE_HOME_PATH = f"{HOSTILE_HOME}/.profile"


@dataclass(frozen=True)
class ScanCase:
    name: str
    args: tuple[str, ...]
    expected: frozenset[str]
    list_file: str = "proof.txt"
    exact: bool = True
    assert_redacted: bool = False


def panoptic_environment() -> dict[str, str]:
    env = os.environ.copy()
    panoptic_dir = env.get("PANOPTIC_DIR")
    if panoptic_dir:
        package = Path(panoptic_dir).resolve() / "panoptic" / "__init__.py"
        if not package.is_file():
            raise SystemExit(
                f"PANOPTIC_DIR does not contain the Panoptic package: {panoptic_dir}"
            )
        existing = env.get("PYTHONPATH")
        env["PYTHONPATH"] = str(package.parents[1]) + (
            os.pathsep + existing if existing else ""
        )
    return env


OPENER = urllib.request.build_opener(urllib.request.ProxyHandler({}))


def wait_until_healthy(timeout: float = 45.0) -> None:
    for health_url in (f"{BASE_URL}/health.php", f"{RAW_BASE_URL}/health"):
        deadline = time.monotonic() + timeout
        last_error: Exception | None = None
        while True:
            try:
                with OPENER.open(health_url, timeout=2) as response:
                    if response.status == 200 and response.read() == b"ok\n":
                        break
            except (OSError, urllib.error.URLError) as exc:
                last_error = exc
            if time.monotonic() >= deadline:
                raise RuntimeError(
                    f"testbed did not become healthy at {health_url}: {last_error}"
                )
            time.sleep(0.5)


def invoke(
    *,
    name: str,
    args: tuple[str, ...],
    list_file: str,
    work_dir: Path,
    env: dict[str, str],
    output_format: str = "json",
    output_name: str = "results.json",
    resume_file: Path | None = None,
) -> tuple[subprocess.CompletedProcess[str], Path]:
    output_path = work_dir / output_name
    command = [
        sys.executable,
        "-m",
        "panoptic",
        "--quiet",
        "--auto",
        "--ignore-proxy",
        # Generous timeout plus retries: shared CI runners can stall the
        # resource-limited Apache container for a few seconds, and a dropped
        # request would otherwise surface as a missing finding.
        "--timeout",
        "10",
        "--retries",
        "2",
        "--concurrency",
        "4",
        "--output-format",
        output_format,
        "--output-file",
        str(output_path),
        *args,
        "--load",
        str(LISTS / list_file),
    ]
    if resume_file is not None:
        command.extend(("--resume-file", str(resume_file)))

    completed = subprocess.run(
        command,
        cwd=work_dir,
        env=env,
        capture_output=True,
        text=True,
        timeout=120,
        check=False,
    )
    if completed.returncode != 0:
        raise AssertionError(
            f"{name}: Panoptic exited {completed.returncode}\n"
            f"command: {' '.join(command)}\n"
            f"stdout:\n{completed.stdout}\n"
            f"stderr:\n{completed.stderr}"
        )
    if "requests failed" in completed.stderr:
        raise AssertionError(
            f"{name}: requests failed, so findings may be missing\n"
            f"stderr:\n{completed.stderr}"
        )
    if not output_path.is_file():
        raise AssertionError(f"{name}: Panoptic did not create {output_path}")
    if os.name == "posix" and stat.S_IMODE(output_path.stat().st_mode) != 0o600:
        raise AssertionError(f"{name}: result file mode is not 0600")
    return completed, output_path


def load_json(path: Path) -> list[dict[str, object]]:
    value = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(value, list):
        raise AssertionError(f"{path}: expected a JSON list")
    return value


def assert_locations(
    *,
    name: str,
    results: list[dict[str, object]],
    expected: frozenset[str],
    exact: bool,
) -> None:
    locations = {str(result.get("location")) for result in results}
    if exact and locations != expected:
        raise AssertionError(
            f"{name}: expected {sorted(expected)}, got {sorted(locations)}"
        )
    if not exact and not expected.issubset(locations):
        raise AssertionError(
            f"{name}: missing {sorted(expected - locations)}; got {sorted(locations)}"
        )
    if any(result.get("found") is not True for result in results):
        raise AssertionError(f"{name}: every serialized result must be a finding")


def run_matrix(temp_root: Path, env: dict[str, str]) -> None:
    proof = frozenset({PROOF_PATH})
    empty = frozenset()
    cases = (
        ScanCase(
            "get-query",
            (
                "--url",
                f"{BASE_URL}/classic.php?file=test.txt",
                "--param",
                "file",
                "--skip-parsing",
            ),
            proof,
            assert_redacted=True,
        ),
        ScanCase(
            "post-form",
            (
                "--url",
                f"{BASE_URL}/post.php",
                "--data",
                "file=test.txt&id=1",
                "--param",
                "file",
                "--skip-parsing",
            ),
            proof,
            assert_redacted=True,
        ),
        ScanCase(
            "post-json",
            (
                "--url",
                f"{BASE_URL}/json_api.php",
                "--data",
                '{"file":"FUZZ"}',
                "--skip-parsing",
            ),
            proof,
            assert_redacted=True,
        ),
        ScanCase(
            "post-nested-json",
            (
                "--url",
                f"{BASE_URL}/nested_json.php",
                "--data",
                '{"request":{"template":"FUZZ"}}',
                "--skip-parsing",
            ),
            proof,
            assert_redacted=True,
        ),
        ScanCase(
            "post-raw-body",
            ("--url", f"{BASE_URL}/raw_body.php", "--data", "FUZZ", "--skip-parsing"),
            proof,
            assert_redacted=True,
        ),
        ScanCase(
            "cookie-fuzz",
            (
                "--url",
                f"{BASE_URL}/cookie.php",
                "--header",
                "Cookie: lang=FUZZ",
                "--skip-parsing",
            ),
            proof,
        ),
        ScanCase(
            "custom-header-fuzz",
            (
                "--url",
                f"{BASE_URL}/header.php",
                "--header",
                "X-Template: FUZZ",
                "--skip-parsing",
            ),
            proof,
        ),
        ScanCase(
            "base64",
            (
                "--url",
                f"{BASE_URL}/base64.php?file=dGVzdC50eHQ=",
                "--param",
                "file",
                "--base64",
                "--skip-parsing",
            ),
            proof,
            assert_redacted=True,
        ),
        ScanCase(
            "path-info",
            (
                "--url",
                f"{BASE_URL}/pathinfo.php/placeholder.txt",
                "--path-based",
                "--skip-parsing",
            ),
            proof,
        ),
        ScanCase(
            "rewritten-double-encoded-path",
            (
                "--url",
                f"{BASE_URL}/files/view/test.txt",
                "--path-based",
                "--replace-slash",
                "%252F",
                "--skip-parsing",
            ),
            proof,
        ),
        ScanCase(
            "nested-filter-bypass",
            (
                "--url",
                f"{BASE_URL}/filtered.php?file=test.txt",
                "--param",
                "file",
                "--prefix",
                "....//",
                "--multiplier",
                "4",
                "--skip-parsing",
            ),
            proof,
            assert_redacted=True,
        ),
        ScanCase(
            "split-extension",
            (
                "--url",
                f"{BASE_URL}/param.php?file=test&type=txt",
                "--param",
                "file",
                "--ext-param",
                "type",
                "--skip-parsing",
            ),
            proof,
            assert_redacted=True,
        ),
        ScanCase(
            "slash-replacement",
            (
                "--url",
                f"{BASE_URL}/classic.php?file=test.txt",
                "--param",
                "file",
                "--replace-slash",
                "/./",
                "--skip-parsing",
            ),
            proof,
        ),
        ScanCase(
            "double-decoded-query",
            (
                "--url",
                f"{BASE_URL}/double_decode.php?file=test.txt",
                "--param",
                "file",
                "--replace-slash",
                "%252F",
                "--skip-parsing",
            ),
            proof,
            assert_redacted=True,
        ),
        ScanCase(
            "legacy-null-byte-simulator",
            (
                "--url",
                f"{BASE_URL}/legacy_nullbyte.php?file=test.txt",
                "--param",
                "file",
                "--postfix",
                "%00",
                "--skip-parsing",
            ),
            proof,
            assert_redacted=True,
        ),
        ScanCase(
            "status-code-default",
            (
                "--url",
                f"{BASE_URL}/status.php?file=test.txt",
                "--param",
                "file",
                "--skip-parsing",
            ),
            proof,
        ),
        ScanCase(
            "status-code-match",
            (
                "--url",
                f"{BASE_URL}/status.php?file=test.txt",
                "--param",
                "file",
                "--match-code",
                "200",
                "--skip-parsing",
            ),
            proof,
        ),
        ScanCase(
            "status-code-filter",
            (
                "--url",
                f"{BASE_URL}/status.php?file=test.txt",
                "--param",
                "file",
                "--filter-code",
                "200",
                "--skip-parsing",
            ),
            empty,
        ),
        ScanCase(
            "match-string",
            (
                "--url",
                f"{BASE_URL}/classic.php?file=test.txt",
                "--param",
                "file",
                "--match-string",
                "PANOPTIC_E2E_PROOF",
                "--skip-parsing",
            ),
            proof,
        ),
        ScanCase(
            "bad-string",
            (
                "--url",
                f"{BASE_URL}/classic.php?file=test.txt",
                "--param",
                "file",
                "--bad-string",
                "PANOPTIC_E2E_PROOF",
                "--skip-parsing",
            ),
            empty,
        ),
        ScanCase(
            "redirect-disabled",
            (
                "--url",
                f"{BASE_URL}/redirect.php?file=test.txt",
                "--param",
                "file",
                "--skip-parsing",
            ),
            empty,
        ),
        ScanCase(
            "redirect-enabled",
            (
                "--url",
                f"{BASE_URL}/redirect.php?file=test.txt",
                "--param",
                "file",
                "--follow-redirects",
                "--skip-parsing",
            ),
            proof,
        ),
        ScanCase(
            "auth-denied",
            (
                "--url",
                f"{BASE_URL}/auth.php?file=test.txt",
                "--param",
                "file",
                "--skip-parsing",
            ),
            empty,
        ),
        ScanCase(
            "auth-cookie",
            (
                "--url",
                f"{BASE_URL}/auth.php?file=test.txt",
                "--param",
                "file",
                "--cookie",
                "panoptic_auth=allowed",
                "--skip-parsing",
            ),
            proof,
        ),
        ScanCase(
            "safe-negative-control",
            (
                "--url",
                f"{BASE_URL}/safe.php?file=test.txt",
                "--param",
                "file",
                "--skip-parsing",
            ),
            empty,
        ),
        ScanCase(
            "windows-json-simulator",
            (
                "--url",
                f"{BASE_URL}/windows_json.php",
                "--data",
                '{"request":{"path":"FUZZ"}}',
                "--skip-parsing",
            ),
            frozenset({WINDOWS_PATH}),
            list_file="windows.txt",
        ),
        # Literal ../ in the URL path, served by the "raw" PHP built-in server
        # whose router reads REQUEST_URI unnormalized. A client that collapses
        # dot segments sends GET /opt/panoptic-fixtures/proof.txt instead,
        # which the router answers with 404 "Unknown route", so this case
        # yields no finding on Panoptic before lightos/Panoptic#34 (verified
        # against c47ef9f). Reproduce the collapse with curl:
        #   curl --path-as-is $RAW_BASE_URL/view/../../../../opt/panoptic-fixtures/proof.txt
        #     -> 200 PANOPTIC_E2E_PROOF_4f6c8a71
        #   curl $RAW_BASE_URL/view/../../../../opt/panoptic-fixtures/proof.txt
        #     -> 404 Unknown route (curl collapses the path by default)
        ScanCase(
            "raw-path-traversal",
            (
                "--url",
                f"{RAW_BASE_URL}/view/placeholder.txt",
                "--path-based",
                "--prefix",
                "../",
                "--multiplier",
                "4",
                "--skip-parsing",
            ),
            proof,
        ),
        # Without traversal the router confines paths to its base directory.
        ScanCase(
            "raw-path-without-traversal",
            (
                "--url",
                f"{RAW_BASE_URL}/view/placeholder.txt",
                "--path-based",
                "--skip-parsing",
            ),
            empty,
        ),
        ScanCase(
            "post-query-string",
            (
                "--url",
                f"{BASE_URL}/post_query.php?action=view",
                "--data",
                "file=FUZZ",
                "--skip-parsing",
            ),
            proof,
            assert_redacted=True,
        ),
        ScanCase(
            "post-query-string-param",
            (
                "--url",
                f"{BASE_URL}/post_query.php?action=view",
                "--data",
                "file=test.txt",
                "--param",
                "file",
                "--skip-parsing",
            ),
            proof,
            assert_redacted=True,
        ),
        # The sink ignores the body unless ?action=view reaches the server.
        ScanCase(
            "post-query-string-missing-action",
            (
                "--url",
                f"{BASE_URL}/post_query.php",
                "--data",
                "file=FUZZ",
                "--skip-parsing",
            ),
            empty,
        ),
        ScanCase(
            "xml-body",
            (
                "--url",
                f"{BASE_URL}/xml_body.php",
                "--data",
                "<req><file>FUZZ</file></req>",
                "--header",
                "Content-Type: application/xml",
                "--skip-parsing",
            ),
            proof,
        ),
        # Without the explicit header Panoptic sends a form content type,
        # which the endpoint rejects with 415.
        ScanCase(
            "xml-body-wrong-content-type",
            (
                "--url",
                f"{BASE_URL}/xml_body.php",
                "--data",
                "<req><file>FUZZ</file></req>",
                "--skip-parsing",
            ),
            empty,
        ),
        ScanCase(
            "backslash-traversal",
            (
                "--url",
                f"{BASE_URL}/backslash.php?file=test.txt",
                "--param",
                "file",
                "--prefix",
                "..\\",
                "--multiplier",
                "4",
                "--skip-parsing",
            ),
            proof,
            assert_redacted=True,
        ),
        # The same filter strips forward-slash traversal.
        ScanCase(
            "backslash-filter-blocks-forward-slash",
            (
                "--url",
                f"{BASE_URL}/backslash.php?file=test.txt",
                "--param",
                "file",
                "--prefix",
                "../",
                "--multiplier",
                "4",
                "--skip-parsing",
            ),
            empty,
        ),
        ScanCase(
            "reflected-encoded-base64",
            (
                "--url",
                f"{BASE_URL}/reflected_encoded.php?file=dGVzdC50eHQ=",
                "--param",
                "file",
                "--base64",
                "--skip-parsing",
            ),
            empty,
        ),
        ScanCase(
            "reflected-encoded-prefix",
            (
                "--url",
                f"{BASE_URL}/reflected_encoded.php?file=test.txt",
                "--param",
                "file",
                "--prefix",
                "../",
                "--multiplier",
                "4",
                "--skip-parsing",
            ),
            empty,
        ),
        ScanCase(
            "dynamic-soft-404",
            (
                "--url",
                f"{BASE_URL}/soft404_dynamic.php?file=test.txt",
                "--param",
                "file",
                "--skip-parsing",
            ),
            empty,
        ),
        ScanCase(
            "dynamic-vulnerable",
            (
                "--url",
                f"{BASE_URL}/dynamic_vuln.php?file=test.txt",
                "--param",
                "file",
                "--skip-parsing",
            ),
            proof,
        ),
        ScanCase(
            "passwd-home-expansion",
            (
                "--url",
                f"{BASE_URL}/parser.php?file=test.txt",
                "--param",
                "file",
                "--concurrency",
                "16",
            ),
            frozenset({PASSWD_PATH, HOME_PATH}),
            list_file="passwd.txt",
        ),
        ScanCase(
            "mysql-binlog-expansion",
            (
                "--url",
                f"{BASE_URL}/parser.php?file=test.txt",
                "--param",
                "file",
            ),
            frozenset({MYSQL_INDEX_PATH, MYSQL_LOG_PATH}),
            list_file="mysql-index.txt",
        ),
    )

    for case in cases:
        case_dir = temp_root / case.name
        case_dir.mkdir()
        completed, output_path = invoke(
            name=case.name,
            args=case.args,
            list_file=case.list_file,
            work_dir=case_dir,
            env=env,
        )
        results = load_json(output_path)
        try:
            assert_locations(
                name=case.name,
                results=results,
                expected=case.expected,
                exact=case.exact,
            )
        except AssertionError as exc:
            # Panoptic's warnings (e.g. failed requests) explain most mismatches.
            raise AssertionError(f"{exc}\nPanoptic stderr:\n{completed.stderr}") from None
        if case.assert_redacted and results:
            serialized_url = str(results[0].get("url"))
            if "***" not in serialized_url or PROOF_PATH in serialized_url:
                raise AssertionError(
                    f"{case.name}: injected value was not redacted: {serialized_url}"
                )
        print(f"[PASS] {case.name}: {len(results)} finding(s)")


def run_artifact_checks(temp_root: Path, env: dict[str, str]) -> None:
    args = (
        "--url",
        f"{BASE_URL}/classic.php?file=test.txt",
        "--param",
        "file",
        "--skip-parsing",
        "--write-files",
    )
    work_dir = temp_root / "write-files"
    work_dir.mkdir()
    _, output_path = invoke(
        name="write-files",
        args=args,
        list_file="proof.txt",
        work_dir=work_dir,
        env=env,
    )
    assert_locations(
        name="write-files",
        results=load_json(output_path),
        expected=frozenset({PROOF_PATH}),
        exact=True,
    )
    saved = list((work_dir / "output").rglob("*.txt"))
    if len(saved) != 1 or "PANOPTIC_E2E_PROOF_4f6c8a71" not in saved[0].read_text(
        encoding="utf-8"
    ):
        raise AssertionError(f"write-files: expected one saved proof file, got {saved}")
    if os.name == "posix" and stat.S_IMODE(saved[0].stat().st_mode) != 0o600:
        raise AssertionError("write-files: saved finding mode is not 0600")
    print("[PASS] write-files and secure artifact modes")

    csv_dir = temp_root / "csv-output"
    csv_dir.mkdir()
    _, csv_path = invoke(
        name="csv-output",
        args=(
            "--url",
            f"{BASE_URL}/classic.php?file=test.txt",
            "--param",
            "file",
            "--skip-parsing",
        ),
        list_file="proof.txt",
        work_dir=csv_dir,
        env=env,
        output_format="csv",
        output_name="results.csv",
    )
    with csv_path.open(newline="", encoding="utf-8") as stream:
        rows = list(csv.DictReader(stream))
    if len(rows) != 1 or rows[0].get("location") != PROOF_PATH:
        raise AssertionError(f"csv-output: unexpected rows: {rows}")
    print("[PASS] csv-output")

    resume_dir = temp_root / "resume"
    resume_dir.mkdir()
    resume_file = resume_dir / "scan.checkpoint"
    base_args = (
        "--url",
        f"{BASE_URL}/classic.php?file=test.txt",
        "--param",
        "file",
        "--skip-parsing",
    )
    _, first_path = invoke(
        name="resume-first",
        args=base_args,
        list_file="proof.txt",
        work_dir=resume_dir,
        env=env,
        output_name="first.json",
        resume_file=resume_file,
    )
    assert_locations(
        name="resume-first",
        results=load_json(first_path),
        expected=frozenset({PROOF_PATH}),
        exact=True,
    )
    _, second_path = invoke(
        name="resume-second",
        args=base_args,
        list_file="proof.txt",
        work_dir=resume_dir,
        env=env,
        output_name="second.json",
        resume_file=resume_file,
    )
    assert_locations(
        name="resume-second",
        results=load_json(second_path),
        expected=frozenset({PROOF_PATH}),
        exact=True,
    )
    checkpoint = json.loads(resume_file.read_text(encoding="utf-8"))
    # Panoptic bumps the version whenever stored data changes meaning; any
    # current format (2+) carries findings, which resume-second relies on.
    version = checkpoint.get("version")
    if not isinstance(version, int) or version < 2 or len(checkpoint.get("completed_ids", [])) != 1:
        raise AssertionError(f"resume: malformed checkpoint: {checkpoint}")
    if BASE_URL in resume_file.read_text(encoding="utf-8"):
        raise AssertionError("resume: checkpoint leaked the target URL")
    print("[PASS] resume checkpoint")


def hostile_request_log(*, reset: bool = False) -> list[object]:
    """Read (or clear, with ``reset``) the hostile_passwd.php request log."""
    request = urllib.request.Request(
        f"{BASE_URL}/hostile_log.php",
        data=b"" if reset else None,
        method="POST" if reset else "GET",
    )
    with OPENER.open(request, timeout=5) as response:
        value = json.loads(response.read().decode("utf-8"))
    if not isinstance(value, list):
        raise AssertionError(f"hostile request log is not a list: {value!r}")
    return value


def run_hostile_passwd_checks(temp_root: Path, env: dict[str, str]) -> None:
    """Parse a hostile /etc/passwd and check that hostile homes stay inert.

    The fixture has homes with terminal escapes (CSI colour and OSC title),
    a relative path and a spreadsheet formula, plus one normal home holding
    a real file. Only the normal home may be expanded, and no escape byte or
    formula may reach any console, log, or result artifact.
    """
    expected = frozenset({PASSWD_PATH, HOSTILE_HOME_PATH})
    forbidden_cell_starts = ("=", "+", "-", "@", "\t", "\r")
    for output_format, output_name in (("json", "results.json"), ("csv", "results.csv")):
        name = f"hostile-passwd-{output_format}"
        work_dir = temp_root / name
        work_dir.mkdir()
        log_path = work_dir / "scan.log"
        hostile_request_log(reset=True)
        completed, output_path = invoke(
            name=name,
            args=(
                "--url",
                f"{BASE_URL}/hostile_passwd.php?file=test.txt",
                "--param",
                "file",
                "--concurrency",
                "16",
                "--log-file",
                str(log_path),
            ),
            list_file="passwd.txt",
            work_dir=work_dir,
            env=env,
            output_format=output_format,
            output_name=output_name,
        )
        requested = hostile_request_log()

        if not log_path.is_file():
            raise AssertionError(f"{name}: --log-file was not created")
        artifacts = {
            "stdout": completed.stdout.encode("utf-8"),
            "stderr": completed.stderr.encode("utf-8"),
            "log file": log_path.read_bytes(),
            "output file": output_path.read_bytes(),
        }
        for label, content in artifacts.items():
            if b"\x1b" in content or b"\\u001b" in content.lower():
                raise AssertionError(f"{name}: escape sequence reached the {label}")
            if b"HYPERLINK" in content or b"relative/" in content:
                raise AssertionError(f"{name}: hostile home reached the {label}")

        if output_format == "json":
            results = load_json(output_path)
        else:
            with output_path.open(newline="", encoding="utf-8") as stream:
                rows = list(csv.reader(stream))
            for row in rows:
                for cell in row:
                    if cell.startswith(forbidden_cell_starts):
                        raise AssertionError(
                            f"{name}: CSV cell starts with a formula character: {cell!r}"
                        )
            header, *records = rows
            results = [dict(zip(header, record, strict=True)) for record in records]
            for result in results:
                result["found"] = result.get("found") == "True"
        assert_locations(name=name, results=results, expected=expected, exact=True)

        # Every request must be the original page, the random baseline,
        # /etc/passwd, or a dotfile under the one valid home directory.
        strays = [
            path
            for path in requested
            if not isinstance(path, str)
            or not (
                path in {"test.txt", PASSWD_PATH}
                or path.startswith(f"{HOSTILE_HOME}/")
                or (len(path) == 16 and all(c in "0123456789abcdef" for c in path))
            )
        ]
        if strays:
            raise AssertionError(f"{name}: requests outside the valid home: {strays}")
        if HOSTILE_HOME_PATH not in requested:
            raise AssertionError(f"{name}: the valid home was not expanded")
        print(
            f"[PASS] {name}: {len(results)} finding(s), "
            f"{len(requested)} request(s) all within allowed paths"
        )


def main() -> int:
    env = panoptic_environment()
    wait_until_healthy()
    with tempfile.TemporaryDirectory(prefix="panoptic-testbed-e2e-") as directory:
        temp_root = Path(directory)
        run_matrix(temp_root, env)
        run_hostile_passwd_checks(temp_root, env)
        run_artifact_checks(temp_root, env)
    print("All Panoptic E2E cases passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
