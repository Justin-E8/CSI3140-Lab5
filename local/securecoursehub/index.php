<?php
// This file is part of Moodle - https://moodle.org/.

require_once(__DIR__ . '/../../config.php');

use local_securecoursehub\local\request_service;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);

require_login($course);

$context = context_course::instance($course->id);
require_capability('local/securecoursehub:viewown', $context);

$PAGE->set_url(new moodle_url('/local/securecoursehub/index.php', ['courseid' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_securecoursehub'));
$PAGE->set_heading(format_string($course->fullname));

$url = new moodle_url('/local/securecoursehub/index.php', ['courseid' => $course->id]);
$canmanage = has_capability('local/securecoursehub:managecourserequests', $context);
$action = optional_param('action', '', PARAM_ALPHA);
$statusfilter = optional_param('statusfilter', '', PARAM_ALPHANUMEXT);

if ($statusfilter !== '' && !request_service::is_valid_status($statusfilter)) {
    throw new moodle_exception('invalidstatus', 'local_securecoursehub');
}

if ($action !== '') {
    require_sesskey();

    if ($action === 'create') {
        require_capability('local/securecoursehub:createrequest', $context);
        request_service::create_request(
            $course->id,
            $USER->id,
            required_param('title', PARAM_RAW),
            required_param('description', PARAM_RAW)
        );
        redirect($url, get_string('requestcreated', 'local_securecoursehub'));
    }

    if ($action === 'update') {
        request_service::update_own_open_request(
            $course->id,
            $USER->id,
            required_param('requestid', PARAM_INT),
            required_param('title', PARAM_RAW),
            required_param('description', PARAM_RAW)
        );
        redirect($url, get_string('requestupdated', 'local_securecoursehub'));
    }

    if ($action === 'delete') {
        request_service::delete_own_open_request(
            $course->id,
            $USER->id,
            required_param('requestid', PARAM_INT)
        );
        redirect($url, get_string('requestdeleted', 'local_securecoursehub'));
    }

    if ($action === 'teacherupdate') {
        require_capability('local/securecoursehub:managecourserequests', $context);
        request_service::update_course_request(
            $course->id,
            required_param('requestid', PARAM_INT),
            required_param('status', PARAM_ALPHANUMEXT),
            required_param('response', PARAM_RAW)
        );
        redirect($url, get_string('requestmanaged', 'local_securecoursehub'));
    }

    throw new moodle_exception('invalidaction', 'local_securecoursehub');
}

$requests = request_service::list_own_requests($course->id, $USER->id);
$courserequests = $canmanage ? request_service::list_course_requests($course->id, $statusfilter) : [];

if ($canmanage) {
    $ajaxurl = (new moodle_url('/local/securecoursehub/ajax.php'))->out(false);
    $PAGE->requires->js_call_amd('local_securecoursehub/dashboard', 'init', [$ajaxurl]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_securecoursehub'));

$name = s(fullname($USER));
echo html_writer::tag('p', get_string('welcomemessage', 'local_securecoursehub', $name));

if ($canmanage) {
    echo $OUTPUT->notification(get_string('managerwelcome', 'local_securecoursehub'), 'info');
} else {
    echo $OUTPUT->notification(get_string('studentwelcome', 'local_securecoursehub'), 'info');
}

if (has_capability('local/securecoursehub:createrequest', $context)) {
    echo $OUTPUT->heading(get_string('newrequest', 'local_securecoursehub'), 3);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'mb-4']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'create']);
    echo html_writer::tag('div',
        html_writer::label(get_string('title', 'local_securecoursehub'), 'sch-title') .
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'title',
            'id' => 'sch-title',
            'class' => 'form-control',
            'maxlength' => 120,
            'required' => true,
        ]),
        ['class' => 'form-group']
    );
    echo html_writer::tag('div',
        html_writer::label(get_string('description', 'local_securecoursehub'), 'sch-description') .
        html_writer::tag('textarea', '', [
            'name' => 'description',
            'id' => 'sch-description',
            'class' => 'form-control',
            'maxlength' => 2000,
            'rows' => 4,
            'required' => true,
        ]),
        ['class' => 'form-group']
    );
    echo html_writer::tag('button', get_string('submitrequest', 'local_securecoursehub'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->heading(get_string('myrequests', 'local_securecoursehub'), 3);
if (!$requests) {
    echo $OUTPUT->notification(get_string('norequests', 'local_securecoursehub'), 'info');
} else {
    foreach ($requests as $request) {
        $content = html_writer::tag('p', s($request->description));
        $content .= html_writer::tag('p', get_string('statuslabel', 'local_securecoursehub', s($request->status)));
        $content .= html_writer::tag('p', get_string('updatedlabel', 'local_securecoursehub', userdate($request->timemodified)));
        if ($request->response !== null && $request->response !== '') {
            $content .= html_writer::tag('p', get_string('responselabel', 'local_securecoursehub', s($request->response)));
        }

        if ($request->status === 'open') {
            $editform = html_writer::start_tag('form', ['method' => 'post', 'action' => $url]);
            $editform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $editform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'update']);
            $editform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'requestid', 'value' => $request->id]);
            $editform .= html_writer::label(get_string('title', 'local_securecoursehub'), 'sch-title-' . $request->id);
            $editform .= html_writer::empty_tag('input', [
                'type' => 'text',
                'name' => 'title',
                'id' => 'sch-title-' . $request->id,
                'class' => 'form-control',
                'maxlength' => 120,
                'required' => true,
                'value' => $request->title,
            ]);
            $editform .= html_writer::label(get_string('description', 'local_securecoursehub'), 'sch-description-' . $request->id);
            $editform .= html_writer::tag('textarea', s($request->description), [
                'name' => 'description',
                'id' => 'sch-description-' . $request->id,
                'class' => 'form-control',
                'maxlength' => 2000,
                'rows' => 3,
                'required' => true,
            ]);
            $editform .= html_writer::tag('button', get_string('updaterequest', 'local_securecoursehub'), [
                'type' => 'submit',
                'class' => 'btn btn-secondary mt-2',
            ]);
            $editform .= html_writer::end_tag('form');

            $deleteform = html_writer::start_tag('form', ['method' => 'post', 'action' => $url, 'class' => 'mt-2']);
            $deleteform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $deleteform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'delete']);
            $deleteform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'requestid', 'value' => $request->id]);
            $deleteform .= html_writer::tag('button', get_string('deleterequest', 'local_securecoursehub'), [
                'type' => 'submit',
                'class' => 'btn btn-outline-danger',
            ]);
            $deleteform .= html_writer::end_tag('form');

            $content .= html_writer::tag('details',
                html_writer::tag('summary', get_string('editrequest', 'local_securecoursehub')) . $editform . $deleteform,
                ['class' => 'mt-3']
            );
        }

        echo $OUTPUT->box(
            html_writer::tag('h4', s($request->title), ['class' => 'h5']) . $content,
            'generalbox mb-3'
        );
    }
}

if ($canmanage) {
    echo $OUTPUT->heading(get_string('coursequeue', 'local_securecoursehub'), 3);

    $filterform = html_writer::start_tag('form', ['method' => 'get', 'action' => $url, 'class' => 'mb-4']);
    $filterform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $course->id]);
    $filterform .= html_writer::label(get_string('filterstatus', 'local_securecoursehub'), 'sch-status-filter');
    $filterform .= html_writer::tag('select', local_securecoursehub_status_options($statusfilter, true), [
        'name' => 'statusfilter',
        'id' => 'sch-status-filter',
        'class' => 'custom-select ml-2 mr-2',
    ]);
    $filterform .= html_writer::tag('button', get_string('applyfilter', 'local_securecoursehub'), [
        'type' => 'submit',
        'class' => 'btn btn-secondary',
    ]);
    $filterform .= html_writer::end_tag('form');
    echo $filterform;

    echo html_writer::tag('div', '', [
        'id' => 'securecoursehub-ajax-feedback',
        'class' => 'alert d-none',
        'role' => 'status',
        'data-network-error' => get_string('ajaxnetworkerror', 'local_securecoursehub'),
        'data-session-error' => get_string('ajaxsessionerror', 'local_securecoursehub'),
        'data-empty-response' => get_string('noresponse', 'local_securecoursehub'),
    ]);

    if (!$courserequests) {
        echo $OUTPUT->notification(get_string('nocourserequests', 'local_securecoursehub'), 'info');
    } else {
        foreach ($courserequests as $request) {
            $requester = s(trim($request->firstname . ' ' . $request->lastname));
            $response = $request->response === null ? '' : (string)$request->response;
            $queuecontent = html_writer::tag('p', get_string('requesterlabel', 'local_securecoursehub', $requester));
            $queuecontent .= html_writer::tag('p', s($request->description));
            $queuecontent .= html_writer::tag('p', get_string('statuslabel', 'local_securecoursehub',
                html_writer::tag('span', s($request->status), ['class' => 'securecoursehub-status'])
            ));
            $queuecontent .= html_writer::tag('p', get_string('responselabel', 'local_securecoursehub',
                html_writer::tag('span', $response === '' ? get_string('noresponse', 'local_securecoursehub') : s($response), [
                    'class' => 'securecoursehub-response',
                ])
            ));

            $updateform = html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $url,
                'class' => 'securecoursehub-update-form mt-3',
                'data-request-id' => $request->id,
                'data-course-id' => $course->id,
            ]);
            $updateform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $updateform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'teacherupdate']);
            $updateform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'requestid', 'value' => $request->id]);
            $updateform .= html_writer::label(get_string('status', 'local_securecoursehub'), 'sch-queue-status-' . $request->id);
            $updateform .= html_writer::tag('select', local_securecoursehub_status_options($request->status), [
                'name' => 'status',
                'id' => 'sch-queue-status-' . $request->id,
                'class' => 'custom-select',
            ]);
            $updateform .= html_writer::label(get_string('response', 'local_securecoursehub'), 'sch-response-' . $request->id);
            $updateform .= html_writer::tag('textarea', s($response), [
                'name' => 'response',
                'id' => 'sch-response-' . $request->id,
                'class' => 'form-control',
                'maxlength' => 500,
                'rows' => 3,
            ]);
            $updateform .= html_writer::tag('button', get_string('savecourseupdate', 'local_securecoursehub'), [
                'type' => 'submit',
                'class' => 'btn btn-primary mt-2',
            ]);
            $updateform .= html_writer::end_tag('form');

            echo html_writer::tag('div',
                $OUTPUT->box(
                    html_writer::tag('h4', s($request->title), ['class' => 'h5']) . $queuecontent . $updateform,
                    'generalbox mb-3 securecoursehub-request-card'
                ),
                ['data-request-card-id' => $request->id]
            );
        }
    }
}

echo $OUTPUT->footer();

/**
 * Renders the allowed request-status options.
 *
 * @param string $selected Selected status.
 * @param bool $includeall Whether to include the all-statuses filter option.
 * @return string Option markup.
 */
function local_securecoursehub_status_options(string $selected, bool $includeall = false): string {
    $options = '';
    if ($includeall) {
        $attributes = ['value' => ''];
        if ($selected === '') {
            $attributes['selected'] = 'selected';
        }
        $options .= html_writer::tag('option', get_string('allstatuses', 'local_securecoursehub'), $attributes);
    }

    foreach (request_service::get_statuses() as $status) {
        $attributes = ['value' => $status];
        if ($selected === $status) {
            $attributes['selected'] = 'selected';
        }
        $options .= html_writer::tag('option', get_string('status' . $status, 'local_securecoursehub'), $attributes);
    }

    return $options;
}
