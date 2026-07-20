# Secure Course Hub Test Results

Last updated: 2026-07-20

Use synthetic accounts and data only. Do not include passwords, database credentials, cookies, sesskeys, or browser developer-tool views that reveal them.

| Test | Role | Expected result | Actual result | Status |
|---|---|---|---|---|
| Open protected plugin page | Student A | Authenticated enrolled user can open Secure Course Hub from course navigation. | Page loaded from the normal course menu. | Pass |
| Create valid request | Student A | An open request is stored with Student A as owner and the current course. | Request creation succeeded and appeared in Student A's list. | Pass |
| View own requests | Student A | Only Student A's requests are shown. | Student A list showed only records owned by Student A. | Pass |
| Modify another student's request | Student B | Request is denied; no unrelated record changes. | Service returned `requestnotfound`; Student A's title was unchanged and Student B saw zero Student A records. | Pass |
| Teacher course queue | Teacher | Authorized requests are displayed. | `teacher1` saw requests for the demonstration course. | Pass |
| Filter course queue | Teacher | Only requests matching the selected status are displayed. | The in-progress filter showed only the matching request. | Pass |
| JSON/fetch teacher update | Teacher | Status and response update without a full page reload. | JSON success feedback appeared; status and response changed in the visible row. | Pass |
| Missing or invalid sesskey | Teacher | State change rejected and data unchanged. | Authenticated teacher JSON request with an invalid sesskey returned HTTP 400; request-table count was unchanged. | Pass |
| Missing required title | Student A | Server rejects the request and preserves stored data. | Service returned `invalidtitle`; request-table count was unchanged. | Pass |
| Injected HTML/JavaScript text | Student A | Markup is displayed as text and does not execute. | Literal `<script>alert(1)</script>` title and description appeared as text in the page and no script ran. | Pass |
| Invalid status and overlength input | Teacher | Server rejects input and does not write partial data. | Invalid status, 121-character title, and 501-character response were rejected; record count and existing record values were unchanged. | Pass |
| Logged-out AJAX call | Logged out user | Authentication is required before data is returned. | Unauthenticated JSON call returned HTTP 400 before plugin data was returned or changed. | Pass |
| Logged-out plugin page | Logged out user | Protected page redirects to Moodle sign-in. | The plugin URL returned an HTTP 303 redirect to Moodle's login page. | Pass |
| Student calls teacher-only endpoint | Student B | Server denies a management action and changes no record. | Request returned HTTP 403; the target request was unchanged. | Pass |
| Teacher requests an unauthorized course | Teacher | Server denies a course the teacher does not manage and changes no record. | Request returned HTTP 403; the target request was unchanged. | Pass |
| Teacher updates missing request | Teacher | Server returns a safe missing-record response and writes nothing. | Request returned HTTP 404; request-table count was unchanged. | Pass |
| Session expiry during JSON update | Teacher, then logged out | The page gives a clear sign-in message and does not change the visible request. | After invalidating the teacher session, the fetch action showed “Your session has expired or you no longer have access. Sign in again and retry.” | Pass |
| Browser diagnostics | Browser check | No JavaScript console errors occur during the normal teacher update flow. | Console error log was empty after the teacher queue and dynamic-update checks. | Pass |
| Plugin secret scan | Source review | Plugin source contains no passwords, database credentials, API keys, or session values. | Scan found only documentation warnings and the user-facing invalid-token message; no secrets or `innerHTML` usage. | Pass |
| Privacy review | Source and data review | Only needed feature data is stored and access is limited by role, course, and ownership. | Table holds course/user ids, request content, status, response, and timestamps only; plugin README documents access and local synthetic-data retention. | Pass |
