<?php
// This file is part of Moodle - https://moodle.org/.

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

use local_securecoursehub\local\request_service;

/**
 * Returns one JSON response and terminates the request.
 *
 * @param int $status HTTP status code.
 * @param array $payload Safe response payload.
 */
function local_securecoursehub_json_response(int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

/**
 * Returns a required positive integer from the JSON input.
 *
 * @param array $payload Decoded request payload.
 * @param string $key Required field name.
 * @return int Validated positive integer.
 */
function local_securecoursehub_json_positive_int(array $payload, string $key): int {
    $value = $payload[$key] ?? null;
    if ((!is_int($value) && !(is_string($value) && ctype_digit($value))) || (int)$value <= 0) {
        throw new moodle_exception('invalidrequest', 'local_securecoursehub');
    }

    return (int)$value;
}

try {
    require_login();

    $rawbody = file_get_contents('php://input');
    $payload = json_decode($rawbody, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || ($payload['action'] ?? '') !== 'update_course_request') {
        throw new moodle_exception('invalidaction', 'local_securecoursehub');
    }
    if (!isset($payload['sesskey']) || !is_string($payload['sesskey']) || !confirm_sesskey($payload['sesskey'])) {
        local_securecoursehub_json_response(400, [
            'success' => false,
            'error' => get_string('invalidsesskey', 'local_securecoursehub'),
        ]);
    }
    if (!isset($payload['status'], $payload['response']) || !is_string($payload['status']) || !is_string($payload['response'])) {
        throw new moodle_exception('invalidrequest', 'local_securecoursehub');
    }

    $courseid = local_securecoursehub_json_positive_int($payload, 'courseid');
    $requestid = local_securecoursehub_json_positive_int($payload, 'id');
    $course = get_course($courseid);
    require_login($course);

    $context = context_course::instance($course->id);
    require_capability('local/securecoursehub:managecourserequests', $context);

    $request = request_service::update_course_request(
        $course->id,
        $requestid,
        $payload['status'],
        $payload['response']
    );

    local_securecoursehub_json_response(200, [
        'success' => true,
        'message' => get_string('requestmanaged', 'local_securecoursehub'),
        'request' => [
            'id' => (int)$request->id,
            'status' => $request->status,
            'response' => $request->response ?? '',
            'timemodified' => userdate($request->timemodified),
        ],
    ]);
} catch (required_capability_exception $exception) {
    local_securecoursehub_json_response(403, [
        'success' => false,
        'error' => get_string('accessdenied', 'error'),
    ]);
} catch (moodle_exception $exception) {
    $status = $exception->errorcode === 'requestnotfound' ? 404 : 400;
    $safeerrors = [
        'invalidaction',
        'invalidrequest',
        'invalidstatus',
        'invalidresponse',
        'requestnotfound',
    ];
    $message = in_array($exception->errorcode, $safeerrors, true)
        ? get_string($exception->errorcode, 'local_securecoursehub')
        : get_string('ajaxerror', 'local_securecoursehub');
    local_securecoursehub_json_response($status, ['success' => false, 'error' => $message]);
} catch (JsonException $exception) {
    local_securecoursehub_json_response(400, [
        'success' => false,
        'error' => get_string('invalidrequest', 'local_securecoursehub'),
    ]);
} catch (Throwable $exception) {
    local_securecoursehub_json_response(500, [
        'success' => false,
        'error' => get_string('ajaxservererror', 'local_securecoursehub'),
    ]);
}
