<?php

/************************************
 * Class ParseNordPool
 *
 * NORD POOL JSON -OBJECT USAGE
 *
 * $object->data->Rows[0]->Columns[0]->[Name,Value,Index]
 * Data object has rows which represent hours and Columns which represent days.
 * Rows can have value 0 - 23 and Columns have values 0 - 7. 0 Could be today or tomorrow, depending
 * if tomorrows data is already available.
 *  Rows = time,
 *      0 = 00-01,
 *      ...
 *      23 = 23-00
 *
 *  Columns = date
 *      0 = today / tomorrow
 *      ...
 *      7 = max days back
 ************************************/

 require_once("database.php");
 require_once("date.php");

 // CONSTANTS
define ("VAT", 1.24);
define ("DAY_RANGE", 14);
define ("SERVER_TIME_OFFSET", 2);
define ("NORDPOOL_URL", "http://www.nordpoolspot.com/api/marketdata/page/35?currency=,,EUR,EUR");

class ParseNordPool {
    /* private members */
    private $dbData;
    private $today;
    private $tomorrowAvailable = false;
    private $db;
    private $minToday;
    private $maxToday;

    function __construct() {}

    public function init() {
        $this->db = new Database;
        $this->db->connect();
        $this->today = DateHelper::dateNow();
        $this->latestData();
        $this->getData();
    }

    private function round2($number) {
        return number_format ($number, 2, '.' , ',');
    }

    /**
     * Check if data needs an update and fetch latest data from nordpool and updates the database.
     */
    private function getData() {

        // Get date of latest data entry
        $date = $this->today;
        if ($this->tomorrowAvailable) {
            $date = DateHelper::getTomorrow($date);
        }

        /* update table if not updated within an hour or tomorrow. Do not update if tomorrows data
           is available */
        if (!$this->tomorrowAvailable && ($this->db->isUpdatedWithinAnHour() === false)) {
            $data = file_get_contents(NORDPOOL_URL);
            $npData = json_decode($data);
            $npData = $npData->data;

            // populate database
            for ($i = 0; $i <= 7; $i++) {
                for ($hour = 0; $hour <= 23; $hour++) {
                    $day =  $npData->Rows[$hour]->Columns[$i]->Name;
                    $price = $npData->Rows[$hour]->Columns[$i]->Value;
                    $this->db->addValue($day, $hour, $price);
                }
            }
            // Update latest update field in database
            $this->db->touchDatabase();
        }
        // get data from database and set it to dbData variable
        $this->dbData = $this->db->getLast2Weeks($date);
    }

    /**
     * Check if tomorrows prices are already available
     **/
    private function latestData() {
        $this->tomorrowAvailable = false;
        $date = DateHelper::getTomorrow($this->today);
        // Check if tomorrows data is already available
        if ($this->db->findDate($date, 1) != 0) {
            $this->tomorrowAvailable = true;
        }
    }

    private function getPrice($date, $hour) {
        if (isset($this->dbData['Rows'][$hour]['Columns'][$date]['Value']))
            return $this->dbData['Rows'][$hour]['Columns'][$date]['Value'];
        return 0;
    }

    private function getPriceForDayAndHour($date, $hour) {
        // Handle the issue that Finland is 1 hour ahead of Nord Pool time (cet)
        // today's first hour is yesterdays last in nordpool and todays last is todays 22

        if ($hour == 0) {
            $date = DateHelper::getYesterday($date);
            $hour = 23;
        } else {
            $hour--;
        }
        $price = $this->getPrice($date, $hour);

        return $this->round2(str_replace(",",".", $price) * VAT / 10);
    }

    private function getDateForDayAndHour($date, $hour) {
        $arr = explode("-",$date);
        $dst = date("I"); // daylight saving time, 0/1

        return mktime($hour + SERVER_TIME_OFFSET + $dst, 0, 0, $arr[1], $arr[2], $arr[0]); // 20:00:00 01-31-2017
    }

    /* get 7 day average. If database does no have values for 7 days, escape earlier. */
    public function get7DayAvg(){
        $total = 0;
        $date = $this->today;

        if ($this->tomorrowAvailable)
            $date = DateHelper::getTomorrow($date);

        for ($day = 0; $day < 7; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $price = $this->getPriceForDayAndHour($date, $hour);
                if ($price == 0) // escape if cant get value
                    break;
                $total += $price;
            }
            $date = DateHelper::getYesterday($date);
        }
        $divider = $day * 24;
        return $this->round2($total / $divider, 2);
    }

    /**
     * Gets price now and todays min and max
     */
    public function getPrices() {
        $results = $this->db->getMinMaxForRange($this->today, $this->today);
        return array (
            'min' => $this->round2($results['min'] * VAT / 10),
            'max' => $this->round2($results['max'] * VAT / 10),
            'now' => $this->getPriceForDayAndHour($this->today, date('H')),
            'avg' => $this->get7DayAvg(),
        );
    }

    public function returnCommaSeparatedRange() {

        $date = $this->today;
        if ($this->tomorrowAvailable)
            $date = DateHelper::getTomorrow($date);

        $return = array();
        for ($i = 0; $i < DAY_RANGE + 1; $i++) {
            for ($hour = 0; $hour < 24; $hour++) {

                $time = $this->getDateForDayAndHour($date, $hour);
                // Add three zeros to make it milliseconds
                $return[] = '[' . $time.'000,' . str_replace(
                        ",",".",$this->getPriceForDayAndHour($date, $hour)).']';
            }
            $date = DateHelper::getYesterday($date);
        }
        asort($return);
        return $return;
    }
}
