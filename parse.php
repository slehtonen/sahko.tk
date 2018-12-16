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
define ("REFRESH_RATE", 3600); // seconds

// Declare the interface 'iParse'
interface iParse
{
    /**
     * Gets price now and todays min and max and 7 day avg
     */
    public function getPrices();

    /**
     * Returns array of price information for configured[DAY_RANGE] timeframe
     */
    public function returnCommaSeparatedRange();
}

class ParseNordPool implements iParse {
    /* private members */
    private $dbData;
    private $today;
    private $tomorrowAvailable = false;
    private $db;
    private $minToday;
    private $maxToday;

    /**
     * Connects to database and gets the latest data. Data is updated if
     * database is not touched within defined time[REFRESH_RATE] and
     * tomorrow's data is not yet available.
     */
    function __construct() {
        $this->db = new Database;
        $this->db->connect();
        $this->today = DateHelper::dateNow();
        $this->latestData();
        $this->updateData();
        $this->getData();
    }

    private function round2($number) {
        return number_format ($number, 2, '.' , ',');
    }

    /**
     * Check if tomorrows prices are already available
     **/
    private function latestData() {
        $this->tomorrowAvailable = false;
        $date = DateHelper::getTomorrow($this->today);
        if ($this->db->findDate($date, 1) != 0) {
            $this->tomorrowAvailable = true;
        }
    }

    /**
     * Try updateing the data if tommoro'w data is not yet in the Database
     * and refresh interval is exceeded
     */
    private function updateData() {
        if (!$this->tomorrowAvailable && ($this->db->isUpdatedWithin(REFRESH_RATE) === false)) {
            $data = file_get_contents(NORDPOOL_URL);
            $npData = json_decode($data);
            $npData = $npData->data;

            /* fetched data has 7 days of data available. Go it through and
               update any missing data points to the database */
            for ($i = 0; $i <= 7; $i++) {
                for ($hour = 0; $hour <= 23; $hour++) {
                    $day =  $npData->Rows[$hour]->Columns[$i]->Name;
                    $price = $npData->Rows[$hour]->Columns[$i]->Value;
                    $this->db->addValue($day, $hour, $price);
                }
            }
            // Update "latest update" field in the database
            $this->db->touchDatabase();
        }
    }

    /**
     * Returns data for last two weeks from the database
     */
    private function getData() {

        $date = $this->today;

        // If tomorrow's data is already in the db, use it as today
        if ($this->tomorrowAvailable) {
            $date = DateHelper::getTomorrow($date);
        }

        // get data from database and set it to dbData variable
        // The site supports showing data for 2 weeks.
        $this->dbData = $this->db->getLast2Weeks($date);
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

        return $this->round2($price * VAT / 10);
    }

    private function getDateForDayAndHour($date, $hour) {
        $arr = explode("-",$date);
        $dst = date("I"); // daylight saving time, 0/1

        return mktime($hour + SERVER_TIME_OFFSET + $dst, 0, 0, $arr[1], $arr[2], $arr[0]); // 20:00:00 01-31-2017
    }

    /* get 7 day average. If database does no have values for 7 days, escape earlier. */
    private function get7DayAvg(){
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
                $return[] = '[' . $time.'000,' . $this->getPriceForDayAndHour($date, $hour).']';
            }
            $date = DateHelper::getYesterday($date);
        }
        asort($return);
        return $return;
    }

    public function get4wkAveragesPerHour() {
        $return = array();
        $data = $this->db->get4wkAveragesPerHour($this->today);
        for ($hour = 0; $hour < 24; $hour++) {

            $dst = date("I"); // daylight saving time, 0/1
            $time = mktime($hour + SERVER_TIME_OFFSET + $dst, 0, 0, 0, 0, 2000) * 1000;
            if ($hour == 23) {
                $time = $time - 60*60*24*1000;
            }

            $return[$hour] = "[ $time," . $this->round2($data[$hour] * VAT / 10) ."]";
        }
        return $return;
    }
}
