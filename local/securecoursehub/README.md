# Secure Course Hub Moodle Plugin

Secure Course Hub is the CSI 3140 Lab 5 local Moodle plugin. It adds secure, course-context help requests while using Moodle's existing authentication, roles, capabilities, sessions, and Database API.

## Current scope

This version provides the plugin metadata, language strings, protected course page, database schema, capability definitions, student CRUD, and the teacher course-management queue. Teachers update status and response through a protected JSON/fetch operation without a full page reload.

## Installation

1. Copy this `securecoursehub` folder to `<moodleroot>/local/securecoursehub`.
2. Sign in as a site administrator and complete the Moodle upgrade prompt, or run Moodle's non-interactive upgrade command.
3. Open `/local/securecoursehub/index.php?courseid=<course-id>` while signed in to an enrolled user account.

For the local lab environment, the demonstration course id is `2`.

Authorized course users can also open the plugin from the normal course navigation through the **Secure Course Hub** item.

## Permissions

The plugin uses course-context capabilities:

| Capability | Default roles |
|---|---|
| `local/securecoursehub:viewown` | Student, editing teacher, manager |
| `local/securecoursehub:createrequest` | Student, editing teacher, manager |
| `local/securecoursehub:managecourserequests` | Editing teacher, manager |

Every protected page or endpoint must enforce its required capability on the server. Client-side visibility will never be used as an authorization decision.

## Data

The `local_securecoursehub_req` table is installed by `db/install.xml`. It stores the required course, owner, request, status, response, and timestamp fields. The allowed statuses to be enforced by the forthcoming service layer are `open`, `inprogress`, and `resolved`.

## Student operations

An enrolled user with `createrequest` can create a request. The plugin stores the authenticated Moodle user id; it never accepts an owner id from the browser. Each user sees only requests where both `courseid` and `userid` match the current course and authenticated user.

Students can update or delete only their own `open` requests. Every state-changing form validates Moodle's `sesskey`, and the service checks ownership, course association, input length, and the open-state rule again on the server.

## Teacher operations and JSON

Users with `managecourserequests` can list requests in their authorized course, filter by status, update an allowed status, and add or replace a response of up to 500 characters. The dynamic update is sent to `ajax.php` as JSON. The endpoint calls `require_login()`, validates the JSON fields and sesskey, establishes the course context, requires `managecourserequests`, verifies the request belongs to that course, and returns a structured JSON success or safe-error response.

## Privacy

The plugin stores only the course id, authenticated user id, request title and description, request status, optional teacher response, and timestamps needed for the feature. Students can access only their own requests; authorized teaching staff can access requests in their course; site administrators act through Moodle's assigned permissions. The plugin does not collect passwords, session cookies, sesskeys, email addresses, or data from outside Moodle.

For this local lab, all accounts and requests are synthetic. Test records are retained only for the demonstration and should be removed by resetting the local test environment after the lab. The submitted ZIP must contain no real personal data, credentials, Moodle core files, or `moodledata` content.

## Submission safety

Submit this plugin folder only, along with the required safe evidence and report. Do not include Moodle core, `moodledata`, `.env`, `config.php`, database credentials, passwords, cookies, or sesskeys.
