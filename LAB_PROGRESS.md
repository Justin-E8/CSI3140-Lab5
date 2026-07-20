# CSI 3140 Lab 5 Progress Log

Last updated: 2026-07-20

This file tracks the lab implementation, verification evidence, and outstanding work. Do not place passwords, database credentials, sesskeys, cookies, or other secrets here.

## Local environment

| Item | Recorded value |
|---|---|
| Moodle | 4.5.4 (Build: 20250414) |
| PHP | 8.1.32 |
| Database | Bitnami legacy MariaDB 11.4 container |
| Local site | `http://127.0.0.1:8080` |
| Development mode | Local-only developer diagnostics enabled |
| Environment files | `compose.yaml` and the untracked `.env` file |

## Test data

All test identities use synthetic data. Passwords are stored only in `.env` and must not be included in a submitted ZIP, screenshots, report, or this log.

| Account | Role | Course enrolment |
|---|---|---|
| `admin1` | Site administrator | Not enrolled in the demonstration course (not required) |
| `teacher1` | Editing teacher | `CSI3140-LAB5` |
| `studenta` | Student A | `CSI3140-LAB5` |
| `studentb` | Student B | `CSI3140-LAB5` |

| Course | Value |
|---|---|
| Full name | CSI 3140 Lab 5 Demonstration Course |
| Short name | `CSI3140-LAB5` |
| Moodle course id | 2 |

## Task checklist

| Lab task | Status | Evidence / notes |
|---|---|---|
| 1. Install Moodle locally | Complete | Moodle and MariaDB containers are running; browser response verified as HTTP 200. Exact Moodle and PHP versions recorded above. |
| 2. Create test users and course | Complete | Administrator, one editing teacher, Student A, and Student B created. Teacher and students are enrolled in the demonstration course. |
| 3. Create and install plugin skeleton | Complete | `local/securecoursehub` contains `version.php`, language strings, protected `index.php`, and plugin README. Moodle upgrade installed `local_securecoursehub`; the page loaded successfully for `teacher1` in course 2. |
| 4. Create database table | Complete | `db/install.xml` installed `local_securecoursehub_req` with id, courseid, userid, title, description, status, response, timecreated, and timemodified. Moodle Database API confirmed the table is present. |
| 5. Define capabilities | Complete | Defined `viewown`, `createrequest`, and `managecourserequests` in `db/access.php`. Verification: `teacher1` is allowed all three; `studenta` is allowed view/create and denied course management. |
| 6. Protect server entry points | Complete | `index.php` loads Moodle, requires course login, establishes the course context, and requires `viewown` before reading records. Create additionally requires `createrequest`; all state changes require a valid sesskey. `ajax.php` independently requires login, validates JSON and sesskey, establishes course context, and requires `managecourserequests`. |
| 7. Student operations | Complete | Enrolled students can create/list/update/delete only their own open requests. Browser checks passed for Student A create, update, and delete. A Student B update attempt returned `requestnotfound`, left Student A's record unchanged, and Student B's own list was empty. |
| 8. Teacher operations | Complete | Authorized teachers can list their course queue, filter by status, set `open`/`inprogress`/`resolved`, and add or replace a response up to 500 characters. Verified as `teacher1`; an in-progress filter returned only the matching request. |
| 9. Moodle Database API | Complete | `classes/local/request_service.php` uses `$DB` CRUD methods only. It validates input, course participation, record existence, course association, ownership, and open-state rules before each write. No user input is concatenated into SQL. |
| 10. JSON and fetch | Complete | `ajax.php` accepts a protected JSON teacher-update request. `amd/src/dashboard.js` uses `fetch()` and updates status/response feedback on the visible page without a reload. Verified as `teacher1` with a successful in-progress status and response update. |
| 11. Security controls | Complete | sesskey validation, capability/context checks, ownership checks, length/status validation, safe errors, Database API use, and escaped PHP/JavaScript rendering are implemented. XSS rendering, invalid input, unauthenticated JSON, authenticated invalid-sesskey, secret-scan, and privacy-review tests passed. |
| 12. Test, document, package | In progress | Mandatory acceptance tests and `TEST_RESULTS.md` evidence are complete. Report creation and submission ZIP packaging are deferred at the user's direction. |

## Notes and decisions

- The local Moodle interface (dashboard, calendar, course layout, etc.) is supplied by the full Moodle installation. The Secure Course Hub plugin contributes its own request page and links it from normal course navigation.
- The plugin source will be created separately under `local/securecoursehub` in the Moodle installation, with the submission containing only that plugin folder and safe evidence.
- The provisioning helper is `scripts/provision_test_data.php`. It is idempotent and is intended to run as Moodle's `daemon` user to avoid cache permission problems.
- The plugin was installed through Moodle's normal non-interactive upgrade process. No Moodle core file was edited.
- Student CRUD uses regular Moodle POST forms; teacher updates use the protected JSON/fetch operation.
- The plugin adds a **Secure Course Hub** item to Moodle's course navigation for users with the `viewown` capability; direct URLs remain useful for testing but are no longer the only entry path. Verified as Student A from the standard course page.
