<html>
<head>
    <title>Webbox test</title>
    <style>
    body { font-family: Verdana, Arial, sans-serif }
    hr { height: 0; border: 0; border-top: gray dashed 2px }
    pre, tt { font-size: 120% }
    pre.json { white-space: normal }
    .err { color: red }
    </style>
</head>
<body>
<?php

ini_set('display_errors', 1);
error_reporting(-1);

if (!isset($_GET['ip'])) die('Missing IP or host name as URL parameter: <strong><tt>?ip=...</tt></strong>');  

$host = $_GET['ip'];

$verbose = array_key_exists('verbose', $_GET);

require 'Webbox.php';

/**
 *
 */
function show($func, $result) {
    global $wb, $verbose;
    static $first = TRUE;
    if ($first) $first = FALSE; else echo '<hr/>';
    echo '<h2>', $func, '()</h2>';
    echo '<h3>Webbox request</h3>';
    echo '<pre class="json">', $wb->getQuery(), '</pre>';
    if (!$wb->isError()) {
        echo '<h3>Webbox response</h3>';
        echo '<pre class="json">', $wb->getResponse(), '</pre>';
        echo '<h3>Result</h3>';
        echo '<pre>', print_r($result, TRUE), '</pre>';
        if ($verbose) {
            echo '<h3>cURL trace</h3>';
            echo '<pre>', $wb->getTrace(), '</pre>';
        }
    } else {
        echo '<h3 class="err">Error</h3>';
        echo '<pre>', $wb->getError(), '</pre>';
    }
}

$wb = \Equipment\SMA\Webbox::getInstance(1, $host);
$wb->verbose($verbose);

$PlantOverview = $wb->getPlantOverview();
#show('getPlantOverview',            $PlantOverview);
show('getPlantOverview()->asArray', $PlantOverview->asArray());
show('getPlantOverview()->asCSV',   $PlantOverview->asCSV());

$Devices = $wb->getDevices();
#show('getDevices',            $Devices);
show('getDevices()->asArray', $Devices->asArray());
show('getDevices()->asCSV',   $Devices->asCSV());

foreach ($Devices->asArray() as $device) {
    $device = $device['key'];
    $ProcessDataChannels = $wb->getProcessDataChannels($device);
    #show('getProcessDataChannels',            $ProcessDataChannels);
    show('getProcessDataChannels()->asArray', $ProcessDataChannels->asArray());
    show('getProcessDataChannels()->asCSV',   $ProcessDataChannels->asCSV());

    $ProcessData = $wb->getProcessData($device);
    #show('getProcessData',            $ProcessData);
    show('getProcessData()->asArray', $ProcessData->asArray());
    show('getProcessData()->asCSV',   $ProcessData->asCSV());

    $Parameter = $wb->getParameter($device);
    #show('getParameter',            $Parameter);
    show('getParameter()->asArray', $Parameter->asArray());
    show('getParameter()->asCSV',   $Parameter->asCSV());
}

?>
</body>
</html>
