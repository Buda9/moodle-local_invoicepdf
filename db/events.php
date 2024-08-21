<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\payment_completed',
        'callback' => '\local_invoicepdf\observer::payment_completed',
    ],
];