<?php
/**
 *
 * @author      Knut Kohl <github@knutkohl.de>
 * @copyright   2012-2014 Knut Kohl
 * @license     MIT License (MIT) http://opensource.org/licenses/MIT
 * @version     1.0.0
 */
namespace Equipment\SMA;

/**
 * Main query class
 */
class Webbox {

    /**
     *
     */
    public static function getInstance( $name, $host='192.168.0.168:80' ) {
        if (!isset(self::$Instance[$name])) {
            self::$Instance[$name] = new self($host);
            if (strstr($host, '://') == '') $host = 'http://' . $host;
            self::$Instance[$name]->url = $host . '/rpc';
        }
        return self::$Instance[$name];
    }

    /**
     *
     */
    public function verbose( $verbose ) {
        self::$verbose = !!$verbose;
    }

    /**
     *
     */
    public function __call( $method, $params ) {
        if (preg_match('~^get(.+)$~i', $method, $args)) {
            $class = __NAMESPACE__.'\\'.$args[1];
            $obj = new $class;
            $rpc = $obj->buildRPC($params);
            if ($result = $this->call($rpc)) {
                $obj->set($result);
                return $obj;
            }
        }
    }

    /**
     *
     */
    public function info( $opt='' ) {
        return ($opt AND isset($this->info[$opt])) ? $this->info[$opt] : $this->info;
    }

    /**
     *
     */
    public function isError() {
        return ($this->error != '');
    }

    /**
     *
     */
    public function getError() {
        return $this->error;
    }

    /**
     *
     */
    public function getTrace( $reset=TRUE ) {
        $trace = $this->trace;
        if ($reset) $this->trace = '';
        return $trace;
    }

    /**
     *
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     *
     */
    public function getQuery() {
        return $this->call;
    }

    /**
     *
     */
    public function __destruct() {
        curl_close(self::$curl);
    }

    // -----------------------------------------------------------------------
    // PROTECTED
    // -----------------------------------------------------------------------

    /**
     *
     */
    protected static $Instance = array();

    /**
     *
     */
    protected static $curl;

    /**
     *
     */
    protected static $verbose = FALSE;

    /**
     *
     */
    protected $url;

    /**
     *
     */
    protected $call;

    /**
     *
     */
    protected $response;

    /**
     *
     */
    protected $info;

    /**
     *
     */
    protected $error;

    /**
     *
     */
    protected $trace;

    /**
     *
     */
    protected function __construct( $host ) {
        self::$curl = curl_init();
        curl_setopt(self::$curl, CURLOPT_HEADER, FALSE);
        curl_setopt(self::$curl, CURLINFO_HEADER_OUT, FALSE);
        curl_setopt(self::$curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt(self::$curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt(self::$curl, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt(self::$curl, CURLOPT_POST, TRUE);
        curl_setopt(self::$curl, CURLOPT_TIMEOUT, 5);
        curl_setopt(self::$curl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt(self::$curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    }

    /**
     * Don't clone Singletons
     */
    protected function __clone() {}

    /**
     *
     */
    protected function call( $rpc ) {

        $this->error = '';

        curl_setopt(self::$curl, CURLOPT_URL, $this->url);

        $call = 'RPC='.json_encode($rpc);
        $this->call = 'POST ' . $this->url . '?' . $call;

        // Set POST fields for this call
        curl_setopt(self::$curl, CURLOPT_POSTFIELDS, $call);
        curl_setopt(self::$curl, CURLOPT_VERBOSE, self::$verbose);

        if (self::$verbose) {
            // Get cURL output to STDERR into memory stream
            $fh = fopen('php://memory', 'rw');
            curl_setopt(self::$curl, CURLOPT_STDERR, $fh);
        }

        $this->response = curl_exec(self::$curl);

        if (self::$verbose) {
            rewind($fh);
            while (($buffer = fgets($fh, 4096)) !== FALSE) $this->trace .= $buffer;
            fclose($fh);
        }

        $this->info = curl_getinfo(self::$curl);

        if (!$this->response) {
            $this->error = 'Curl error (' . curl_errno(self::$curl) . '): ' . curl_error(self::$curl);
            return FALSE;
        }

        // Got answer
        $result = json_decode($this->response, TRUE);

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                if (isset($result['result'])) {
                    $this->error = FALSE;
                    // Fine
                    return $result['result'];
                } else {
                    // Set error, return FALSE at end
                    $this->error = $result['error']['message'];
                }
                break;
            case JSON_ERROR_DEPTH:
                $this->error = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $this->error = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $this->error = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $this->error = 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $this->error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $this->error = 'Unknown error';
                break;
        }

        return FALSE;
    }

    /**
     *
     */
    protected function csv( $result ) {
        $csv = '';
        foreach ($result as $data) {
            unset($data['options']);
            $csv .= implode(';', $data) . PHP_EOL;
        }
        return $csv;
    }
}

/**
 * All response classes have to implement this interface
 */
interface IResponse {

    /**
     *
     */
    public function set( $data );

    /**
     *
     */
    public function asArray();

    /**
     *
     */
    public function asCSV();

}

/**
 * Abstract base response class
 */
abstract class Response implements IResponse {

    /**
     *
     */
    public function buildRPC( $params ) {
        $rpc = new \StdClass;
        $rpc->version = '1.0';
        $rpc->id      = (string) rand(1000, 9999);
        $rpc->format  = 'JSON';
        return $rpc;
    }

    /**
     *
     */
    public function asArray() {
        return $this->data;
    }

    protected $data;

}

/**
 * Response class for GetPlantOverview
 */
class PlantOverview extends Response {

    /**
     *
     */
    public function buildRPC( $params ) {
        $rpc = parent::buildRPC($params);
        $rpc->proc = 'GetPlantOverview';
        return $rpc;
    }

    /**
     *
     */
    public function set( $data ) {
        $this->data = $data['overview'];
    }

    /**
     *
     */
    public function asCSV() {
        $csv = '';
        foreach ($this->data as $row) {
            $csv .= implode(';', $row) . PHP_EOL;
        }
        return $csv;
    }
}

/**
 * Response class for GetDevices
 */
class Devices extends Response {

    /**
     *
     */
    public function buildRPC( $params ) {
        $rpc = parent::buildRPC($params);
        $rpc->proc = 'GetDevices';
        return $rpc;
    }

    /**
     *
     */
    public function set( $data ) {
        $this->data = $data['devices'];
    }

    /**
     *
     */
    public function asCSV() {
        $csv = '';
        foreach ($this->data as $row) {
            $csv .= implode(';', $row) . PHP_EOL;
        }
        return $csv;
    }
}

/**
 * Response class for GetProcessDataChannels
 */
class ProcessDataChannels extends Response {

    /**
     *
     */
    public function buildRPC( $params ) {
        $rpc = parent::buildRPC($params);
        $rpc->proc = 'GetProcessDataChannels';
        $rpc->params = new \StdClass;
        $rpc->params->device = $params[0];
        return $rpc;
    }

    /**
     *
     */
    public function set( $data ) {
        $this->data = array_shift($data);
    }

    /**
     *
     */
    public function asCSV() {
        return implode(';', $this->data);
    }
}

/**
 * Response class for GetProcessData
 */
class ProcessData extends Response {

    /**
     *
     */
    public function buildRPC( $params ) {
        $rpc = parent::buildRPC($params);
        $rpc->proc = 'GetProcessData';
        $rpc->params = new \StdClass;

        $d = new \StdClass;
        $d->key = $params[0];

        $channels = isset($params[1]) ? array($params[1]) : NULL;
        $d->channels = $channels;
        $rpc->params->devices = array($d);

        return $rpc;
    }

    /**
     *
     */
    public function set( $data ) {
        $this->data = $data['devices'][0]['channels'];
    }

    /**
     *
     */
    public function asCSV() {
        $csv = '';
        foreach ($this->data as $row) {
            $csv .= implode(';', $row) . PHP_EOL;
        }
        return $csv;
    }
}

/**
 * Response class for GetParameter
 */
class Parameter extends Response {

    /**
     *
     */
    public function buildRPC( $params ) {
        $rpc = parent::buildRPC($params);
        $rpc->proc = 'GetParameter';
        $rpc->params = new \StdClass;

        $d = new \StdClass;
        $d->key = $params[0];

        $channels = isset($params[1]) ? array($params[1]) : NULL;
        $d->channels = $channels;
        $rpc->params->devices = array($d);

        return $rpc;
    }

    /**
     *
     */
    public function set( $data ) {
        $this->data = $data['devices'][0]['channels'];
    }

    /**
     *
     */
    public function asCSV() {
        $csv = '';
        foreach ($this->data as $row) {
            unset($row['options']);
            $csv .= implode(';', $row) . PHP_EOL;
        }
        return $csv;
    }
}
