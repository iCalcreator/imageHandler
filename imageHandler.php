<?php
/**
 * imageHandler.php
 *
 *
 * imageHandler web (REST GET/PUT, json) service interface
 *
 * @package imageHandler
 * @copyright 2015, Kjell-Inge Gustafsson kigkonsult, All rights reserved
 * @author    Kjell-Inge Gustafsson, kigkonsult <ical@kigkonsult.se>
 * @link      http://kigkonsult.se/imageHandler/index.php
 * @license   non-commercial use: Creative Commons
 *            Attribution-NonCommercial-NoDerivatives 4.0 International License
 *            (http://creativecommons.org/licenses/by-nc-nd/4.0/)
 *            commercial use :imageHandler141license / imageHandler14Xlicense
 * @version   1.4
 *
 * json main key             i  / image
 *
 * REST GET/POST keys  OR  json (object) properties
 *                           i  / image     : filename or url, required
 *                           o  / operation : 1/'download', 2/'stream' (default), 3/'save' (to disk)
 *                           n  / name      : image output (display/save) name (opt)
 *                           p              : bool, TRUE jpg/gif output as png (default) , FALSE not
 *                                            TRUE also force png extension
 *                                            settings for the resizeable image, all opt, percent or pixels
 *                           cx             : crop start x coordinate, from left border (0%), to right border (100%), default 0
 *                           cy             : crop start y coordinate, from top border (0%), to bottom border (100%), default 0
 *                           cw / cwidth    : image crop, width
 *                           ch / cheight   : image crop, height
 *                           w  / width
 *                           h  / height
 *                           mw / maxwidth
 *                           mh / maxheight
 */
      /* *******************************************************************
         manage input
         ******************************************************************* */
$keys       = array( 'image', 'i',
                     'operate', 'o', 'name', 'n', 'p',
                     'cx', 'cy', 'cwidth', 'cw', 'cheight', 'ch', 'width', 'w', 'height', 'h', 'maxwidth', 'mw', 'maxheight', 'mh'
                   );
$input      = array();
foreach( $keys as $key ) {
  if( array_key_exists( $key, $_REQUEST ))
    $input[$key] = $_REQUEST[$key];
}
$_REQUEST = $keys = array();
if( FALSE === (bool) ( $imageStr = checkInputValue( 'image', 'i' )))
  exit();
else
  unset( $input['image'], $input['i'] );
        /* *******************************************************************
         imageHandler include and env./log setup and
         ******************************************************************* */
include './imageHandler.class.php';
ini_set( 'memory_limit', '2048M' );              // if managing larger images ??
                                                 // path to imageHandler directory
$basePath   = dirname( __FILE__ ) . DIRECTORY_SEPARATOR;
                                                 // log filename or FALSE
$logFile    = $basePath . 'log' . DIRECTORY_SEPARATOR . 'imageHandler.log';
$logprio    = LOG_DEBUG;                         // default LOG_NOTICE
if( $logFile ) {
  date_default_timezone_set( 'Europe/Stockholm' ); // required if using Log
  include 'Log.php';                             // here are PEAR log used but extended
  class imageHandlerLog extends Log { public function _destruct() { $this->flush(); parent::_destruct(); }}
  $log      = imageHandlerLog::factory( 'file', $logFile, 'ih', array(), $logprio );
}
      /* *******************************************************************
         json check
         ******************************************************************* */
$result     = json_decode( $imageStr, TRUE );
$jres       = jsonTest( json_last_error());
if( empty( $input ) && is_array( $result )) {    // json!!
  if( TRUE !== $jres ) {                         // but json error...
    if( $log ) $log->log( basename( __FILE__ )." $jres, input=".var_export( $imageStr, TRUE ), LOG_ERR );
    exit();
  }
  $input    = $result;
  unset( $result );
  $imageStr = checkInputValue( 'image', 'i' );
  if( $log ) $log->log( basename( __FILE__ )." json input=".var_export( $input, TRUE ), LOG_DEBUG );
}
elseif( $log ) $log->log( basename( __FILE__ ).", input=$imageStr, ".var_export( $input, TRUE ), LOG_DEBUG );
      /* *******************************************************************
         imageHandler optional config
         ******************************************************************* */
if( $logFile ) {
  imageHandler::$logger         = $log;
  imageHandler::$logprio        = $logprio;
}
imageHandler::$defaultOperation = 1;             // default 2
imageHandler::$outputpng        = ( array_key_exists( 'p', $input ) && empty( $input['p'] )) ? FALSE : TRUE; // true default
// imageHandler::$imageLib         = ???         // image storage path, will prefix image (filename) (note, suffixed by '/'!!)
// imageHandler::$filenamePrefix   = ???         // prefix for created (temp/output) filenames
imageHandler::$cache            = '/opt/work/imageHandler/cache/';    // default 'sys_get_temp_dir()'
      /* *******************************************************************
         operate !!
         ******************************************************************* */
$operation  = checkInputValue( 'operate', 'o' );
if( ! is_numeric( $operation )) {
  switch( $operation ) {
    case 'download':  $operation = 1;    break;
    case 'stream'  :  $operation = 2;    break;
    case 'save'    :  $operation = 3;    break;
    default        :  $operation = null; break;
  }
}
imageHandler::Operate( $imageStr,
                       array( 'operation' => $operation,
                              'name'      => checkInputValue( 'name',      'n' ),
                              'cx'        => checkInputValue(              'cx' ),
                              'cy'        => checkInputValue(              'cy' ),
                              'cwidth'    => checkInputValue( 'cwidth',    'cw' ),
                              'cheight'   => checkInputValue( 'cheight',   'ch' ),
                              'width'     => checkInputValue( 'width',     'w' ),
                              'height'    => checkInputValue( 'height',    'h' ),
                              'maxwidth'  => checkInputValue( 'maxwidth',  'mw' ),
                              'maxheight' => checkInputValue( 'maxheight', 'mh' ),
                            )
                     );
/** *************************************************************************
 * checkRequestValues
 *
 * check $input for keys, return first found and not empty (or null)
 *
 * @param string $key1
 * @param string $key2
 * @return mixed
 */
function checkInputValue( $key1, $key2=null ) {
  global $input;
  if( ! empty( $key1 ) && ( array_key_exists( $key1, $input ) && (( 0 == $input[$key1] ) || ! empty( $input[$key1] ))))
    return $input[$key1];
  if( ! empty( $key2 ) && ( array_key_exists( $key2, $input ) && (( 0 == $input[$key2] ) || ! empty( $input[$key2] ))))
    return $input[$key2];
  return null;
}
/**
 * return (decoded) json last error
 *
 * @param mixed $jres
 * @return mixed bool TRUE on success, string on error
 * @static
 */
function jsonTest( $jres  ) {
    switch( $jres ) {
      case JSON_ERROR_NONE           :         return TRUE;  //  No error has occurred
      case JSON_ERROR_DEPTH          :         return 'The maximum stack depth has been exceeded';
      case JSON_ERROR_STATE_MISMATCH :         return 'Invalid or malformed JSON';
      case JSON_ERROR_CTRL_CHAR      :         return 'Control character error, possibly incorrectly encoded';
      case JSON_ERROR_SYNTAX         :         return 'Syntax error';
      default:
        if( version_compare( PHP_VERSION, '5.3.3', '>=' ) && (jres == JSON_ERROR_UTF8 ))
                                               return 'Malformed UTF-8 characters, possibly incorrectly encoded';
        if( version_compare( PHP_VERSION, '5.5', '>=' )) {
          switch( $jres ) {
            case JSON_ERROR_RECURSION        : return 'One or more recursive references in the value to be encoded';
            case JSON_ERROR_INF_OR_NAN       : return 'One or more NAN or INF values in the value to be encoded';
            case JSON_ERROR_UNSUPPORTED_TYPE : return 'A value of a type that cannot be encoded was given';
          }
        }
        return 'Unknown json error code ('.str_replace( PHP_EOL, '', var_export( $jres, TRUE )).')';
  }
}
