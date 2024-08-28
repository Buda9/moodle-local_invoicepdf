<?php

namespace local_invoicepdf\analytics\indicator;

class invoices_generated extends \core_analytics\local\indicator\binary {
    public static function get_name() : \lang_string {
        return new \lang_string('indicator:invoicesgenerated', 'local_invoicepdf');
    }

    protected function calculate_sample($sample, $starttime = false, $endtime = false) {
        global $DB;

        $params = ['sampleid' => $sample->get_id()];
        if ($starttime) {
            $params['starttime'] = $starttime;
        }
        if ($endtime) {
            $params['endtime'] = $endtime;
        }

        $sql = "SELECT COUNT(*) FROM {local_invoicepdf_invoices}
                WHERE userid = :sampleid";
        if ($starttime) {
            $sql .= " AND timecreated >= :starttime";
        }
        if ($endtime) {
            $sql .= " AND timecreated <= :endtime";
        }

        return $DB->count_records_sql($sql, $params) > 0 ? 1 : 0;
    }
}