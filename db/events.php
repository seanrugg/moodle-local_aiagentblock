<?php
$observers = [
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_aiagentblock\observer::quiz_attempt_finished',
    ],
];
