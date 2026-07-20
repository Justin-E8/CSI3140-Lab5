#!/usr/bin/env bash

# Verifies protected teacher-only JSON operations without exposing session data.
set -euo pipefail

source .env

test_dir="$(mktemp -d)"
trap 'rm -rf "$test_dir"' EXIT

site_url="http://127.0.0.1:8080"

record_count() {
    docker compose exec -T --user daemon moodle /opt/bitnami/php/bin/php -r \
        'define("CLI_SCRIPT", true); require("/bitnami/moodle/config.php"); global $DB; echo $DB->count_records("local_securecoursehub_req");'
}

login_and_get_sesskey() {
    local username="$1"
    local cookie_jar="$test_dir/$username.cookies"
    local login_page="$test_dir/$username.login.html"
    local plugin_page="$test_dir/$username.plugin.html"

    curl -sS -c "$cookie_jar" "$site_url/login/index.php" -o "$login_page"
    local login_token
    login_token="$(sed -n 's/.*name="logintoken" value="\([^"]*\)".*/\1/p' "$login_page" | head -n 1)"
    [ -n "$login_token" ]

    curl -sS -b "$cookie_jar" -c "$cookie_jar" \
        --data-urlencode "username=$username" \
        --data-urlencode "password=$LAB5_TEST_PASSWORD" \
        --data-urlencode "logintoken=$login_token" \
        "$site_url/login/index.php" -o /dev/null

    curl -sS -b "$cookie_jar" "$site_url/local/securecoursehub/index.php?courseid=2" -o "$plugin_page"
    local sesskey
    sesskey="$(sed -n 's/.*name="sesskey" value="\([^"]*\)".*/\1/p' "$plugin_page" | head -n 1)"
    [ -n "$sesskey" ]
    printf '%s|%s' "$cookie_jar" "$sesskey"
}

run_case() {
    local label="$1"
    local username="$2"
    local courseid="$3"
    local requestid="$4"
    local expected_status="$5"
    local auth
    auth="$(login_and_get_sesskey "$username")"
    local cookie_jar="${auth%%|*}"
    local sesskey="${auth#*|}"
    local response_body="$test_dir/$label.json"
    local before_count
    local after_count
    before_count="$(record_count)"

    local status_code
    status_code="$(curl -sS -b "$cookie_jar" -o "$response_body" -w '%{http_code}' \
        -H 'Content-Type: application/json' \
        --data "{\"action\":\"update_course_request\",\"courseid\":$courseid,\"id\":$requestid,\"status\":\"resolved\",\"response\":\"Must not be saved\",\"sesskey\":\"$sesskey\"}" \
        "$site_url/local/securecoursehub/ajax.php")"
    after_count="$(record_count)"

    if [ "$status_code" = "$expected_status" ] && [ "$before_count" = "$after_count" ]; then
        echo "$label=pass"
    else
        echo "$label=fail"
        exit 1
    fi
}

run_case "student_teacher_operation" "studentb" 2 1 403
run_case "teacher_unauthorized_course" "teacher1" 1 1 403
run_case "teacher_missing_record" "teacher1" 2 999999 404
