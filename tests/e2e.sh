#!/usr/bin/env bash
set -Eeuo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
manage_docker="${E2E_MANAGE_DOCKER:-1}"

cleanup() {
    status=$?
    if [[ "$manage_docker" == "1" ]]; then
        if [[ $status -ne 0 ]]; then
            docker compose --project-directory "$repo_root" logs --no-color
        fi
        docker compose --project-directory "$repo_root" down --volumes
    fi
    exit "$status"
}
trap cleanup EXIT

if [[ "$manage_docker" == "1" ]]; then
    docker compose --project-directory "$repo_root" up --build --detach --wait
fi

python3 "$repo_root/tests/run_e2e.py"
