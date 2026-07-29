# Security policy

## Deliberately vulnerable behavior

The file inclusion and path traversal sinks documented in `README.md` are
intentional and are not security bugs in this repository. This application
must never be deployed or exposed beyond the local test host.

## Reportable security issues

Please privately report issues that escape the documented test surface,
including:

- a default configuration that exposes the service beyond loopback;
- access to host files, sockets, credentials, or services;
- a container escape or privilege escalation;
- compromised or malicious dependencies;
- secrets committed to the repository or emitted by CI;
- a vulnerability in the E2E runner itself.

Use GitHub's private vulnerability reporting or a draft repository security
advisory. Do not include an active exploit or sensitive data in a public issue.

Incorrect expected findings, broken fixtures, and documentation errors can be
reported through ordinary issues.

## Supported version

Only the current default branch is maintained. The testbed is not a production
service and receives no deployment support.
