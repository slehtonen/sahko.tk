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

 // CONSTANTS
define ("VAT", 1.24);
define ("NORDPOOL_URL", "http://www.nordpoolspot.com/api/marketdata/page/35?currency=,,EUR,EUR");
class ParseNordPool {

public $mHourlyData;
public $today; // 0 if no tomorrow's data. Or 1 if there is tomorrow available

    function __construct() {
        $this->parseData();
        $this->getToday();
    }
    
    /* Updates cache and sets latest available data to global variable for use */
    function parseData() {

        // If cache does not exist, create file and update
        if (file_exists ("cache.dat" ) === false) {
            $this->updateCache();
        }

        // If last modified over 12 hours ago, update
        if (time() - filemtime("cache.dat") > 60*60*12) {
            $this->updateCache();
        }

        // Get data, update globals
        $this->getData();

        // Check today
        $this->getToday();

        // If todays data is latest, try update
        if ($this->today == 0) {
            if (time() - filemtime("cache.dat") > 180) {
                $this->updateCache();
                $this->getData();
            }
        }
    }

    function updateCache(){
        $data = file_get_contents(NORDPOOL_URL);
        $file = fopen("cache.dat", "w") or die("Unable to open file!");
        fwrite($file, $data);
        fclose($file);
    }
    function getData() {
        $data = file_get_contents("cache.dat");
        $this->mHourlyData = json_decode($data);
        $this->mHourlyData = $this->mHourlyData->data;
    }
    function getToday() {
        $day_now = date('j');
        $day = 0; // Today is first day in the data by default
        // Check if tomorrows data is already available
        $arr = explode("-", $this->mHourlyData->Rows[0]->Columns[0]->Name);
        
        // If so, use today as the second day in the data
        if ($day_now != $arr[0]) {
            $day = 1;
        }
        $this->today = $day;        
    }
    
    /* Gets electricity price for the current hour */
    function getPriceNow() {
        $hours_now = date('H');
        $day_now = $this->today;

        if ($hours_now == 0) {
            $hours_now = 23;
            $day_now = $this->today + 1;
        }
        $hours_now--;
        return round(str_replace(",",".", $this->mHourlyData->Rows[$hours_now]->Columns[$day_now]->Value) * VAT / 10, 2);
    }

    // Return array filled with selected day's prices
    function getDaysDataToArray($day) {
        $return = array();
        for ($hour = 0; $hour < 24; $hour++) {
            $return[] = $this->getPriceForDayAndHour($day, $hour);
        }
        return $return;
    }

    function getPriceForDayAndHour($day, $hour) {
        // Handle the issue that Finland is 1 hour ahead of Nord Pool time (cet)
        if ($hour == 0) {
            $day++; // go one day back
            $hour = 23;
            if ($day > 7) {
                // If we go over board, use nearest value.
                // Todo, use historical data for this (averages yms)
                return round($this->mHourlyData->Rows[23]->Columns[7]->Value * VAT / 10, 2);
            }
        }
        else {
            $hour--;
        }
        
        // This return property of non object
        return str_replace(",",".", $this->mHourlyData->Rows[$hour]->Columns[$day]->Value) * VAT / 10;
    }

    function getDateForDayAndHour($day, $hour) {
        // Handle the issue that Finland is 1 hour ahead of Nord Pool time (cet)

        $date = $this->mHourlyData->Rows[$hour]->Columns[$day]->Name;
        $arr = explode("-",$date);
        $dst = date("I");
        return mktime($hour + 2 + $dst, 0, 0, $arr[1], $arr[0], $arr[2]);
    }

    function get7DayAvg(){
        $total = 0;
        $divider = 7 * 24;
        for ($day = $this->today; $day < 7 + $this->today; $day++) {
            for ($time = 0; $time < 24; $time++) {
                $total += $this->getPriceForDayAndHour($day, $time);
            }                
        }
        return round($total / $divider,2);
    }
    function returnCommaSeparatedRange($start = 0, $end = 6){
        if ($end > (6 + $this->today)) {
            $end = 6 + $this->today;
        }

        $return = array();
        for ($day = $start; $day <= $end; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $time = $this->getDateForDayAndHour($day,$hour);
                // Add three zeros to make it milliseconds
                $return[] = '[' . $time.'000,' . str_replace(
                        ",",".",$this->getPriceForDayAndHour($day, $hour)).']';
            }
        }
        asort($return); // sort it so that time
        return $return;
    }
}

$NordPool = new ParseNordPool;
if(isset($_GET['callback'])) {

    $data = $NordPool->returnCommaSeparatedRange();

    header("content-type: application/json");

    echo $_GET['callback']. '(['.implode(',',$data).'])';
    //echo '('. json_encode($data) . ')';
}

if (isset($_GET['mode'])) {

    switch($_GET['mode']){
        case "average":
            echo $NordPool->get7DayAvg();
            break;
        case "now":
            echo $NordPool->getPriceNow();
            break;
        default:
            echo ":(";
    }
}
/*
else {
echo "nyt " . $NordPool->getPriceNow();
echo "<br />eilen klo 12 ";
echo $NordPool->getPriceForDayAndHour(1,12);
echo "<br />";
echo "7pv keskiarvo: " . $NordPool->calculate7DayAvg();
*/