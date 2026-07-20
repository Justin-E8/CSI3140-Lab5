<?php
// This file is part of Moodle - https://moodle.org/.

namespace local_securecoursehub\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Applies Secure Course Hub request rules and uses Moodle's Database API.
 */
class request_service {
    /** @var int Maximum request-title length. */
    private const TITLE_MAX_LENGTH = 120;

    /** @var int Maximum request-description length. */
    private const DESCRIPTION_MAX_LENGTH = 2000;

    /** @var int Maximum teacher-response length. */
    private const RESPONSE_MAX_LENGTH = 500;

    /** @var array<string> Allowed request statuses. */
    private const STATUSES = ['open', 'inprogress', 'resolved'];

    /**
     * Returns the allowed request statuses.
     *
     * @return array<string>
     */
    public static function get_statuses(): array {
        return self::STATUSES;
    }

    /**
     * Confirms a value is one of the allowed request statuses.
     *
     * @param string $status Candidate status.
     * @return bool Whether the status is allowed.
     */
    public static function is_valid_status(string $status): bool {
        return in_array($status, self::STATUSES, true);
    }

    /**
     * Creates an open request for the authenticated course participant.
     *
     * @param int $courseid Moodle course id.
     * @param int $userid Authenticated Moodle user id.
     * @param string $title Untrusted title input.
     * @param string $description Untrusted description input.
     * @return \stdClass Newly created request record.
     */
    public static function create_request(int $courseid, int $userid, string $title, string $description): \stdClass {
        global $DB;

        self::require_course_participation($courseid, $userid);
        [$title, $description] = self::validate_content($title, $description);

        $now = time();
        $record = (object)[
            'courseid' => $courseid,
            'userid' => $userid,
            'title' => $title,
            'description' => $description,
            'status' => 'open',
            'response' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('local_securecoursehub_req', $record);

        return $record;
    }

    /**
     * Returns only requests owned by the authenticated user in the selected course.
     *
     * @param int $courseid Moodle course id.
     * @param int $userid Authenticated Moodle user id.
     * @return array<int, \stdClass>
     */
    public static function list_own_requests(int $courseid, int $userid): array {
        global $DB;

        return $DB->get_records_select(
            'local_securecoursehub_req',
            'courseid = ? AND userid = ?',
            [$courseid, $userid],
            'timemodified DESC, id DESC'
        );
    }

    /**
     * Returns requests in one course for an authorized teacher or manager.
     *
     * The query returns only the display name required to identify the requester in the course queue.
     *
     * @param int $courseid Moodle course id.
     * @param string $status Optional validated status filter.
     * @return array<int, \stdClass>
     */
    public static function list_course_requests(int $courseid, string $status = ''): array {
        global $DB;

        $params = [$courseid];
        $where = 'r.courseid = ?';
        if ($status !== '') {
            $status = self::validate_status($status);
            $where .= ' AND r.status = ?';
            $params[] = $status;
        }

        $sql = "SELECT r.id, r.courseid, r.userid, r.title, r.description, r.status, r.response,
                       r.timecreated, r.timemodified, u.firstname, u.lastname
                  FROM {local_securecoursehub_req} r
                  JOIN {user} u ON u.id = r.userid
                 WHERE {$where}
              ORDER BY r.timemodified DESC, r.id DESC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Updates a request's status and optional teacher response in its authorized course.
     *
     * @param int $courseid Moodle course id.
     * @param int $requestid Request record id.
     * @param string $status Untrusted status input.
     * @param string $response Untrusted response input.
     * @return \stdClass Updated request record.
     */
    public static function update_course_request(
        int $courseid,
        int $requestid,
        string $status,
        string $response
    ): \stdClass {
        global $DB;

        if ($requestid <= 0) {
            throw new \moodle_exception('requestnotfound', 'local_securecoursehub');
        }

        $record = $DB->get_record('local_securecoursehub_req', ['id' => $requestid]);
        if (!$record || (int)$record->courseid !== $courseid) {
            throw new \moodle_exception('requestnotfound', 'local_securecoursehub');
        }

        $record->status = self::validate_status($status);
        $record->response = self::validate_response($response);
        $record->timemodified = time();
        $DB->update_record('local_securecoursehub_req', $record);

        return $record;
    }

    /**
     * Updates title and description when the authenticated owner still has an open request.
     *
     * @param int $courseid Moodle course id.
     * @param int $userid Authenticated Moodle user id.
     * @param int $requestid Request record id.
     * @param string $title Untrusted title input.
     * @param string $description Untrusted description input.
     * @return \stdClass Updated request record.
     */
    public static function update_own_open_request(
        int $courseid,
        int $userid,
        int $requestid,
        string $title,
        string $description
    ): \stdClass {
        global $DB;

        $record = self::get_owned_open_request($courseid, $userid, $requestid);
        [$title, $description] = self::validate_content($title, $description);

        $record->title = $title;
        $record->description = $description;
        $record->timemodified = time();
        $DB->update_record('local_securecoursehub_req', $record);

        return $record;
    }

    /**
     * Deletes an open request only when it belongs to the authenticated user and selected course.
     *
     * @param int $courseid Moodle course id.
     * @param int $userid Authenticated Moodle user id.
     * @param int $requestid Request record id.
     */
    public static function delete_own_open_request(int $courseid, int $userid, int $requestid): void {
        global $DB;

        $record = self::get_owned_open_request($courseid, $userid, $requestid);
        $DB->delete_records('local_securecoursehub_req', ['id' => $record->id]);
    }

    /**
     * Requires an enrolled participant for student-side operations.
     *
     * @param int $courseid Moodle course id.
     * @param int $userid Moodle user id.
     */
    private static function require_course_participation(int $courseid, int $userid): void {
        $context = \context_course::instance($courseid);

        if (!is_enrolled($context, $userid, '', true)) {
            throw new \moodle_exception('notenrolled', 'local_securecoursehub');
        }
    }

    /**
     * Loads an owned request and enforces the student's open-state rule.
     *
     * The generic not-found response intentionally does not reveal whether a different user owns the id.
     *
     * @param int $courseid Moodle course id.
     * @param int $userid Authenticated Moodle user id.
     * @param int $requestid Request record id.
     * @return \stdClass Request record.
     */
    private static function get_owned_open_request(int $courseid, int $userid, int $requestid): \stdClass {
        global $DB;

        if ($requestid <= 0) {
            throw new \moodle_exception('requestnotfound', 'local_securecoursehub');
        }

        $record = $DB->get_record('local_securecoursehub_req', ['id' => $requestid]);
        if (!$record || (int)$record->courseid !== $courseid || (int)$record->userid !== $userid) {
            throw new \moodle_exception('requestnotfound', 'local_securecoursehub');
        }
        if ($record->status !== 'open') {
            throw new \moodle_exception('requestnotopen', 'local_securecoursehub');
        }

        return $record;
    }

    /**
     * Cleans untrusted text and applies the request content rules before a write.
     *
     * @param string $title Untrusted title input.
     * @param string $description Untrusted description input.
     * @return array{0: string, 1: string} Clean title and description.
     */
    private static function validate_content(string $title, string $description): array {
        // Preserve literal markup as text; every rendering path escapes it with s() or textContent.
        $title = clean_param($title, PARAM_RAW_TRIMMED);
        $description = clean_param($description, PARAM_RAW_TRIMMED);

        if ($title === '' || \core_text::strlen($title) > self::TITLE_MAX_LENGTH) {
            throw new \moodle_exception('invalidtitle', 'local_securecoursehub');
        }
        if ($description === '' || \core_text::strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
            throw new \moodle_exception('invaliddescription', 'local_securecoursehub');
        }

        return [$title, $description];
    }

    /**
     * Validates a status before it is used for filtering or saving.
     *
     * @param string $status Untrusted status input.
     * @return string Validated status.
     */
    private static function validate_status(string $status): string {
        $status = clean_param($status, PARAM_ALPHANUMEXT);
        if (!self::is_valid_status($status)) {
            throw new \moodle_exception('invalidstatus', 'local_securecoursehub');
        }

        return $status;
    }

    /**
     * Cleans and validates an optional teacher response.
     *
     * @param string $response Untrusted response input.
     * @return string|null Clean response, or null when cleared.
     */
    private static function validate_response(string $response): ?string {
        // Preserve literal markup as text; the PHP and JavaScript renderers escape it.
        $response = clean_param($response, PARAM_RAW_TRIMMED);
        if (\core_text::strlen($response) > self::RESPONSE_MAX_LENGTH) {
            throw new \moodle_exception('invalidresponse', 'local_securecoursehub');
        }

        return $response === '' ? null : $response;
    }
}
