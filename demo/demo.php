<?php

require ('../solarprognose.php');

$dbgTxts = array("Kritisch","Info","Debug","LowLevel");

function writeToCustomLog($lName, $dbgTxt, $output) {
    printf ("%s => %s\n",$lName.$dbgTxt,$output);
}

function exec_debug ($thisTxtDbgLevel, $str) {
    global $debugLevel;
    global $dbgTxts;
    
    if ($thisTxtDbgLevel <= $debugLevel) {
        writeToCustomLog("LBS_ST_LBSID", $dbgTxts[$thisTxtDbgLevel], $str);
    }
}

$debugLevel = 3;

$forecast = new solarPrognose (<INSERT YOUR TOKEN HERE>);
$forecast->setType (SP_TYPE_HOURLY);
$forecast->setDayRange (-2,1);

if ($forecast->requestData ())
{
    if ($data = $forecast->getData()) {
        
        printf ("dataName: %s, source: %s\n", $forecast->getDataLineName(), $forecast->getWeatherSource());
        printf ("next possible Request: %d [%s]\n", $forecast->getNextExecutionTime(), date('r', $forecast->getNextExecutionTime() ) );

        foreach ($data as $k =>$v) {
            printf ("%s [%s] = %f, [%f]\n", $k ,date('r', $k ), $v[0], $v[1]);
        }
    }
    printf ("code: %s\n", $forecast->getHRStatusCode());

}
?>
