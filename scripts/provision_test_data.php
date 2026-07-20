<?php
// Creates only the synthetic accounts and course required for Lab 5.
define('CLI_SCRIPT', true);

require('/bitnami/moodle/config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');

$password = getenv('LAB5_TEST_PASSWORD');
if ($password === false || $password === '') {
    throw new moodle_exception('missingtestpassword', 'error');
}

$accounts = [
    [
        'username' => 'teacher1',
        'firstname' => 'Taylor',
        'lastname' => 'Teacher',
        'email' => 'teacher1@example.test',
    ],
    [
        'username' => 'studenta',
        'firstname' => 'Student',
        'lastname' => 'A',
        'email' => 'studenta@example.test',
    ],
    [
        'username' => 'studentb',
        'firstname' => 'Student',
        'lastname' => 'B',
        'email' => 'studentb@example.test',
    ],
];

$users = [];
foreach ($accounts as $account) {
    if ($user = $DB->get_record('user', ['username' => $account['username'], 'deleted' => 0])) {
        $users[$account['username']] = $user;
        continue;
    }

    $user = (object)($account + [
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'password' => hash_internal_user_password($password),
        'city' => 'Ottawa',
        'country' => 'CA',
        'lang' => 'en',
        'timezone' => 'America/Toronto',
    ]);
    $user->id = user_create_user($user, false, false);
    $users[$account['username']] = $user;
}

$course = $DB->get_record('course', ['shortname' => 'CSI3140-LAB5']);
if (!$course) {
    $course = create_course((object)[
        'fullname' => 'CSI 3140 Lab 5 Demonstration Course',
        'shortname' => 'CSI3140-LAB5',
        'category' => 1,
        'visible' => 1,
    ]);
}

$manualenrol = enrol_get_plugin('manual');
$instance = null;
foreach (enrol_get_instances($course->id, true) as $candidate) {
    if ($candidate->enrol === 'manual') {
        $instance = $candidate;
        break;
    }
}
if (!$instance) {
    throw new moodle_exception('manualenrolinstanceunavailable', 'error');
}

$roles = [
    'teacher1' => 'editingteacher',
    'studenta' => 'student',
    'studentb' => 'student',
];
foreach ($roles as $username => $roleshortname) {
    $role = $DB->get_record('role', ['shortname' => $roleshortname], '*', MUST_EXIST);
    $manualenrol->enrol_user($instance, $users[$username]->id, $role->id);
}

echo "Provisioned course {$course->shortname} (id {$course->id}).\n";
foreach ($roles as $username => $roleshortname) {
    echo "- {$username}: {$roleshortname}\n";
}
