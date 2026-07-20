#!/usr/bin/env bash

# Runs an authenticated negative test without printing session cookies or sesskeys.
set -euo pipefail

source .env

test_dir="$(mktemp -d)"
trap 'rm -rf "$test_dir"' EXIT

site_url="http://127.0.0.1:8080"
cookie_jar="$test_dir/cookies.txt"
login_page="$test_dir/login.html"
response_body="$test_dir/response.json"

record_count() {
    docker compose exec -T --user daemon moodle /opt/bitnami/php/bin/php -r \
        'define("CLI_SCRIPT", true); require("/bitnami/moodle/config.php"); global $DB; echo $DB->count_records("local_securecoursehub_req");'
}

before_count="$(record_count)"

curl -sS -c "$cookie_jar" "$site_url/login/index.php" -o "$login_page"
login_token="$(sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p' "$login_page" | head -n 1)"

if [ -z "$login_token" ]; then
    echo "invalid_sesskey_test=login_token_not_found"
    exit 1
fi

curl -sS -b "$cookie_jar" -c "$cookie_jar" \
    --data-urlencode "username=teacher1" \
    --data-urlencode "password=$LAB5_TEST_PASSWORD" \
    --data-urlencode "logintoken=$login_token" \
    "$site_url/login/index.php" -o /dev/null

status_code="$(curl -sS -b "$cookie_jar" -o "$response_body" -w '%{http_code}' \
    -H 'Content-Type: application/json' \
    --data '{"action":"update_course_request","courseid":2,"id":1,"status":"resolved","response":"Must not be saved","sesskey":"invalid-sesskey"}' \
    "$site_url/local/securecoursehub/ajax.php")"
after_count="$(record_count)"

if [ "$status_code" = "400" ] && grep -q 'security token is missing or invalid' "$response_body" && [ "$before_count" = "$after_count" ]; then
    echo "invalid_sesskey_test=pass"
else
    echo "invalid_sesskey_test=fail"
    exit 1
fi
