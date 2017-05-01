<?php
/**
 * Class implements helper functions for date
 */
class DateHelper {
    /**
     * Return current date as YYYY-mm-dd
     * \return    date
     */
    public static function dateNow() {
        return date("Y-m-d");
    }
    /**
     * Take date and convert it into mysql date
     * \param[in] date
     * \return    mysql compatible date
     */
    public static function parseDate($date) {
        return date("Y-m-d", strtotime($date));
    }
    /**
     * Get yesteday's date
     * \param[in] date
     * \return    yesterday's date 
     */
    public static function getYesterday($date) {
        return $day_now = date("Y-m-d", strtotime($date) - 60 * 60 * 24);
    }
    /**
     * Get tomorrow's date
     * \param[in] date
     * \return    tomorrow's date 
     */
    public static function getTomorrow($date) {
        return $day_now = date("Y-m-d", strtotime($date) + 60 * 60 * 24);
    }
}