#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ "${1:-}" == --scenario ]]; then
    scenario="$2"
    source "$PROJECT_ROOT/scripts/lib/common.sh"
    source "$PROJECT_ROOT/scripts/steps/02-cloudflare.sh"

    USE_CLOUDFLARE=true
    CF_ZONE_NAME=example.test
    CF_TOKEN=test-token
    DOMAIN=app.example.test
    SERVER_IP=192.0.2.1
    [[ "$scenario" != disabled ]] || USE_CLOUDFLARE=false

    curl() {
        local url='' method=GET data=''
        while [[ $# -gt 0 ]]; do
            case "$1" in
                https://*) url="$1"; shift ;;
                -X) method="$2"; shift 2 ;;
                --data) data="$2"; shift 2 ;;
                *) shift ;;
            esac
        done

        printf 'MOCK REQUEST: %s %s\n' "$method" "$url" >&2
        case "$url" in
            'https://api.cloudflare.com/client/v4/zones?name=example.test&status=active&per_page=1')
                [[ "$scenario" != zone-transport-error ]] || return 22
                case "$scenario" in
                    zone-api-error) printf '%s\n' '{"success":false}' ;;
                    zone-missing) printf '%s\n' '{"success":true,"result":[]}' ;;
                    *) printf '%s\n' '{"success":true,"result":[{"id":"zone-123"}]}' ;;
                esac
                ;;
            'https://api.cloudflare.com/client/v4/zones/zone-123/dns_records?type=A&name=app.example.test&per_page=1')
                [[ "$scenario" != lookup-transport-error ]] || return 22
                case "$scenario" in
                    lookup-api-error) printf '%s\n' '{"success":false}' ;;
                    existing) printf '%s\n' '{"success":true,"result":[{"id":"record-123"}]}' ;;
                    *) printf '%s\n' '{"success":true,"result":[]}' ;;
                esac
                ;;
            'https://api.cloudflare.com/client/v4/zones/zone-123/dns_records')
                [[ "$method" == POST ]] || exit 90
                [[ "$data" == '{"type":"A","name":"app.example.test","content":"192.0.2.1","proxied":true}' ]] || exit 91
                case "$scenario" in
                    create-transport-*) return 22 ;;
                    create-api-*) printf '%s\n' '{"success":false}' ;;
                    *) printf '%s\n' '{"success":true,"result":{"id":"record-123"}}' ;;
                esac
                ;;
            *) exit 92 ;;
        esac
    }

    step_cloudflare_dns
    printf 'BOOTSTRAP CONTINUED\n'
    exit 0
fi

command -v jq >/dev/null 2>&1 || { printf 'jq is required for these tests\n' >&2; exit 1; }

assert_contains() {
    [[ "$output" == *"$1"* ]] || {
        printf 'FAIL %s: missing %s\n%s\n' "$scenario" "$1" "$output" >&2
        exit 1
    }
}

assert_absent() {
    [[ "$output" != *"$1"* ]] || {
        printf 'FAIL %s: unexpected %s\n%s\n' "$scenario" "$1" "$output" >&2
        exit 1
    }
}

for scenario in created existing disabled zone-transport-error zone-api-error zone-missing \
    lookup-transport-error lookup-api-error create-transport-continue create-transport-stop \
    create-api-continue create-api-stop; do
    answer=n
    [[ "$scenario" != *-continue ]] || answer=y
    status=0
    output="$(bash "$0" --scenario "$scenario" <<< "$answer" 2>&1)" || status=$?

    case "$scenario" in
        *-error|zone-missing|*-stop) expected_status=1 ;;
        *) expected_status=0 ;;
    esac
    [[ "$status" -eq "$expected_status" ]] || {
        printf 'FAIL %s: expected exit %s, got %s\n%s\n' "$scenario" "$expected_status" "$status" "$output" >&2
        exit 1
    }
    if [[ "$expected_status" -eq 0 ]]; then
        assert_contains 'BOOTSTRAP CONTINUED'
    else
        assert_absent 'BOOTSTRAP CONTINUED'
    fi
    assert_absent 'command not found'

    case "$scenario" in
        created|create-*) assert_contains 'MOCK REQUEST: POST https://api.cloudflare.com/client/v4/zones/zone-123/dns_records' ;;
        *) assert_absent 'MOCK REQUEST: POST' ;;
    esac
    case "$scenario" in
        created) assert_contains 'Cloudflare DNS record created' ;;
        existing) assert_contains 'already exists; skipping' ;;
        disabled) assert_contains 'Cloudflare DNS was not selected'; assert_absent 'MOCK REQUEST:' ;;
        zone-transport-error) assert_contains 'Cloudflare zone lookup failed' ;;
        zone-api-error) assert_contains 'Cloudflare rejected the API token or zone lookup' ;;
        zone-missing) assert_contains 'Cloudflare zone was not found' ;;
        lookup-transport-error) assert_contains 'Cloudflare DNS lookup failed' ;;
        lookup-api-error) assert_contains 'Cloudflare rejected the DNS lookup' ;;
        create-transport-*) assert_contains 'Cloudflare DNS record creation failed' ;;
        create-api-*) assert_contains 'Cloudflare did not create the DNS record' ;;
    esac
    printf 'PASS %s\n' "$scenario"
done
