<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\payment_completed',
        'callback' => '\local_invoicepdf\observer::payment_completed',
        'internal' => false, // This allows the observer to be triggered for events from other plugins
        'priority' => 9999,  // High priority to ensure it runs after the payment is fully processed
    ],
];