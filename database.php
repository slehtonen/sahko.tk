<?php

/************************************
 * Class Database
 *
 * TODO: add getLatest, to check what needs to be updated, avoids checking every
 * individual value
 ***********************************/
require_once("date.php");
require_once("database-config.php");
class Database extends DbConfig {

    function connect() {
        $this->link = mysqli_connect($this->host, $this->user, $this->password, $this->database);
    }

    /**
     * Check if there is a database entry from last hour
     */
    function isUpdatedWithin($seconds) {
        $timestamp = date("Y-m-d H:i:s", time() - $seconds);

        $query = "SELECT data_updated FROM np_updated WHERE (data_updated = $timestamp)";
        if ($result = $this->link->query($query))
            return true;
        return false;
    }

    function touchDatabase() {
        $query = "UPDATE np_updated SET data_updated=NOW()";
        $result = $this->link->query($query);
    }

    function getLast2Weeks($end) {
        // hack +1 day (15) to get price for 0 hour, because of time zone difference
        $day_week_ago = date("Y-m-d", time() - 60 * 60 * 24 * 15);

        $query = "SELECT date, hour, price FROM np_data WHERE (date BETWEEN '$day_week_ago' AND '$end')";
        $result = $this->link->query($query);
        if ($result->num_rows == 0) {
            echo "Error: No results";
            return;
        }
        while ($row = $result->fetch_array()) {
            $resultStruct['Rows'][$row['hour']]['Columns'][$row['date']]['Value'] = $row['price'];
        }
        /* free result set */
        $result->close();
        return $resultStruct;
    }

    /**
     * Check if there are values for given date
     */
    function findDate($date, $hour) {
        $query = "SELECT date, hour FROM np_data WHERE date = '$date' AND hour = '$hour'";
        if ($result = $this->link->query($query)) {
            $ret = $result->num_rows;
            /* free result set */
            $result->close();
            return $ret;
        }
    }

      /**
       * Get min and max prices for date range
       * Hack to handle 1 hour time difference between
       * norpool and Finland's time zone. Include last hour from yesterday and
       * skip todays last hour.
       */
      function getMinMaxForRange($date_start, $date_end) {
          $query = "SELECT MIN(price) AS minPrice,
                           MAX(price) AS maxPrice
                    FROM np_data
          WHERE (
              (date BETWEEN '$date_start' AND '$date_end' AND hour != '23')
               OR (date = ($date_start - INTERVAL 1 DAY) AND hour = '23')
          )";
          if ($result = $this->link->query($query)) {
               /* free result set */
               $row = mysqli_fetch_array($result);
               $result->close();
               $resultArray = array (
                   'min' => $row['minPrice'],
                   'max' => $row['maxPrice']
               );
               return $resultArray;
           }
       }
    /**
     * Add value to database if does not exist
     */
    function addValue($day, $hour, $price) {
        /* Select queries return a resultset */
        $date = DateHelper::parseDate($day);
        if ($this->findDate($date, $hour) != 0)
            return;

        $price = str_replace(",",".",$price);

        $this->link->query("INSERT INTO np_data (date, hour, price) VALUES ('".$date."', '".$hour."', '".$price."')");
    }
    /**
     * \todo unused
     */
    function get4wkAveragesPerHour($date) {
        $sql = "SELECT AVG(price) AS avgPrice, hour
                FROM np_data WHERE DATE BETWEEN ('$date' - INTERVAL 28 DAY) AND '$date' GROUP BY hour";

        if ($result = $this->link->query($sql)) {
             /* free result set */

             while ($row = $result->fetch_array()) {
                 $resultArray[$row['hour']] = $row['avgPrice'];
             }
             $result->close();
             return $resultArray;
         }
    }
}
