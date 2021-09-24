<?php

class RatelimitController extends BaseController {

    public $funcName        = null; // 1.- The $funcName to audit
    public $timesLimit      = 10;   // 2.- Maximum number of call times per time range in seconds (default like a test case)
    public $secsRange       = 3;    // 3.- The size of the audited time ranges in seconds (default like a test case)

    // Bann params
    public $bann           = false;// If bann apply ;)
    public $bannSeconds    = 15;   // The default bann time =D

    // Semaphore lights ;)
    public $green          = array( 'status' => 200, 'message' => 'Wellcome!!'          ); // Wellcome!!
    public $red            = array( 'status' => 406, 'message' => 'You shall not pass!!'); // Stop Jugernaut Crawler!!

    // Test case parameters
    public $test           = false;
    public $loops          = 50;    // Test calls loops ocurrences
    public $sleep          = 0.1;   // Slep between test calls
    public $session;

    // Config and perform 
    function __construct( $funcName = null, $bann = false, $timesLimit = null, $secsRange = null, $bannSeconds = null ){

        $this->session = SessionLib::getInstance();

        if( $bann || $bannSeconds ){
            $this->bann = true; // Activate bann
            $this->bannSeconds = ( $bannSeconds ) ? $bannSeconds : $this->bannSeconds; // Default value bann time if no input
        } 
        if( $funcName ) $this->audit( $funcName, $this->bann, $timesLimit, $secsRange, $bannSeconds );

    }
    
    public function index() {}

    /**
     * It allows controlling the number of times the range of seconds that an endpoint function is attacked 
     * $funcName    string  - The function name to audit (as a main parameter)
     * $bann        bolean  - The bann intention to the user who exceeds the limits ;)    
     * $timesLimit  int     - The maximum number of times this function can be executed at times by the range$secsRange
     * $secsRange   int     - The range of seconds we want to audit
     */
    public function audit( $funcName, $bann = false, $timesLimit = null, $secsRange = null, $bannSeconds = null ){

        $this->funcName     = $funcName;
        $this->bann         = ( $bann       ) ? $bann       : $this->bann; // Default value if no input
        $this->timesLimit   = ( $timesLimit ) ? $timesLimit : $this->timesLimit; // Default value if no input
        $this->secsRange    = ( $secsRange  ) ? $secsRange  : $this->secsRange; // Default value if no input
        if( $bann || $bannSeconds ){
            $this->bann = true;
            $this->bannSeconds  = ( $bannSeconds ) ? $bannSeconds : $this->bannSeconds;
        } 
        self::time( $this->now );

        // Semaphore controlls the range limits excess ;)
        return ( $this->test ) ? $this->_semaphore() : $this->semaphore();

    }

    // Semaphore controlls the range limits excess
    public function semaphore(){
        if( $this->exceed('range') ) $this->stop();
    }

    // Testing calls semaphore...
    public function _semaphore(){
        $exceed = $this->exceed('range');
        $return = ( $exceed ) ? $this->red : $this->green;
        if( $exceed && $this->bann ) $this->bann();
        return $return;
    }

    // Evaluating if the user esceeds the CALL_LIMITS by $_SESSION
    public function exceed(){
        $this->count(); // The rate agent controls each call amount somatory
        $this->status(); // The agent looks the session conditions on this function
        $return = (
            $this->count > $this->limit && $this->range->from <= $this->now->sec && $this->now->sec <= $this->range->to
        ) ? true : false;
        // When the range is overflowed ($now is before $range->to) it becomes reset
        if( $this->range->to <= $this->now->sec ) $this->reset();
        return $return;
    }

    public function set(){
        $return = $this->session->CALL_LIMITS[ $this->funcName ] = (object) array(
            'range'   => (object) array( 'from' => $this->now->sec, 'to' => ( $this->now->sec + $this->secsRange ) ),
            'limit'   => $this->timesLimit,
            'count'   => 1,
        );
        return $return;
    }

    public function reset(){
        return $this->set();
    }

    public static function time( &$moment ){
        list( $moment->msec, $moment->sec ) = explode( ' ', microtime() );
    }

    public function count(){
        if( ! empty( $this->session->CALL_LIMITS[ $this->funcName ] ) ){
            $this->session->CALL_LIMITS[ $this->funcName ]->count++; 
            $this->count = $this->session->CALL_LIMITS[ $this->funcName ]->count;
        }
    }

    public function status(){
        $this->status = ( ! empty( $this->session->CALL_LIMITS[ $this->funcName ] ) ) 
            ? $this->session->CALL_LIMITS[ $this->funcName ]
            : $this->reset();
        $this->range = (object) $this->status->range;
        $this->limit = $this->status->limit;
    }

    public function stop(){
        $this->status();
        if( $this->bann ) $this->bann();
        if( $this->test ){ // In test case...
            $this->red->limit = "$this->count iters >= limit of $this->timesLimit iters in $this->secsRange seconds";
            return $this->red;
        }else{
            header( "Status: {$this->red['status']} Forbidden" );
            header( 'Content-Type: text/html' );
            die( json_encode( $this->red ) );
        }
    }

    // When user is banned, it only can make a 
    public function bann(){
        $this->session->CALL_LIMITS[ $this->funcName ]->range = 
            (object) array( 'from' => $this->now->sec, 'to' => $this->now->sec + $this->bannSeconds );
        $this->session->CALL_LIMITS[ $this->funcName ]->count = 100000000;
    }

    // Testing a big amount of times to see whats happening with the function
    public function unitTest(){
        $this->test = true;
        for( $i = 0; $i <= $this->loops; $i++ ){
            $result = json_encode( $this->audit( $this->funcNameTest ) ).PHP_EOL;//, 10, 3, 15).PHP_EOL; // Usuall test values (non required)
            if( $result && $this->test ) echo $result;
            sleep( $this->sleep );
        }
        die( json_encode( $this->session->CALL_LIMITS[ $this->funcName ]).PHP_EOL );
    }

}

/** Writen with love by David RullÃ¡n */
//$limit = new rateLimit();
//$limit->unitTest();