<?php
// This file is part of Moodle - https://moodle.org/.

defined('MOODLE_INTERNAL') || die();

/**
 * Adds Secure Course Hub to the regular navigation for authorized course users.
 *
 * @param settings_navigation $navigation The course navigation collection.
 * @param stdClass $course The current course.
 * @param context_course $context The current course context.
 */
function local_securecoursehub_extend_navigation_course($navigation, $course, $context): void {
    if (!has_capability('local/securecoursehub:viewown', $context)) {
        return;
    }

    $url = new moodle_url('/local/securecoursehub/index.php', ['courseid' => $course->id]);
    $node = navigation_node::create(
        get_string('pluginname', 'local_securecoursehub'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'securecoursehub',
        new pix_icon('i/course', '')
    );
    $navigation->add_node($node);
}
