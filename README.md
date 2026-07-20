# CSI 3140 Lab 5 - Secure Course Hub

## Local environment

This project runs Moodle and MariaDB locally with Docker Compose. The runtime uses the Bitnami legacy Moodle 4.5 image and the Bitnami legacy MariaDB 11.4 image, as recommended in the course chapter.

Verified versions:

- Moodle 4.5.4 (Build: 20250414)
- PHP 8.1.32

Start the environment with:

```sh
docker compose up -d
```

Open Moodle at `http://127.0.0.1:8080`. Use `127.0.0.1`, rather than `localhost`, to keep the browser session host consistent.

The local credentials are intentionally stored only in `.env`, which is excluded from version control and must not be included in the submitted ZIP.

## Demonstration data

The setup includes the site administrator `admin1`, plus the following enrolled users in course `CSI3140-LAB5` (course id 2):

- `teacher1` - Editing teacher
- `studenta` - Student A
- `studentb` - Student B

The three test-user passwords are held only in `.env`. The provisioning helper is repeatable and must run as Moodle's `daemon` user so it does not create root-owned cache files.

Implementation status, environment evidence, and the remaining task checklist are maintained in `LAB_PROGRESS.md`.
Acceptance-test results are maintained in `TEST_RESULTS.md`.

The Moodle plugin source is in `local/securecoursehub`. It is the folder intended for the final plugin-source submission.

Current functionality includes student-owned request CRUD and a teacher-only course queue with status filtering and dynamic JSON/fetch updates. Remaining work focuses on the final security test suite, screenshots, report, and submission packaging.

## Status

- [x] Moodle installation verified at `http://127.0.0.1:8080`
- [x] Administrator, teacher, Student A, and Student B created
- [x] Demonstration course created and roles enrolled
- [ ] Plugin skeleton created
