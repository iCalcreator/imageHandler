<?php
/**
 * index.php
 *
 * @package imageHandler
 * @subpackage test
 * @copyright 2015, Kjell-Inge Gustafsson kigkonsult, All rights reserved
 * @author    Kjell-Inge Gustafsson, kigkonsult <ical@kigkonsult.se>
 * @link      http://kigkonsult.se/imageHandler/index.php
 * @license   non-commercial use: Creative Commons
 *            Attribution-NonCommercial-NoDerivatives 4.0 International License
 *            (http://creativecommons.org/licenses/by-nc-nd/4.0/)
 *            commercial use :imageHandler141license / imageHandler14Xlicense
 * @version   1.4
 */
$time_startSetup  = microtime( TRUE );
$msg              = array();
      /* *******************************************************************
         config
         ******************************************************************* */
$timezone         = 'Europe/Stockholm';
date_default_timezone_set( $timezone );
/*       path to imageHandler directory                                      */
$basePath         = dirname( dirname( __FILE__ )) . DIRECTORY_SEPARATOR;
/*       URL to imageHandler.class.php directory                             */
$baseUrl          = $_SERVER['REQUEST_SCHEME'] . '://' .
                    $_SERVER['SERVER_NAME'] .
                    dirname( dirname( $_SERVER['SCRIPT_NAME'] )) . DIRECTORY_SEPARATOR;
/*       (default) path to image (test) directory                            */
$defaultImageLib  = $basePath . 'images' . DIRECTORY_SEPARATOR;
/*       log priority or '-1' for no log                                     */
$baseLogPrio      = LOG_DEBUG;
/*       path and filename for (PEAR?) log file                              */
$baseLogFile      = $basePath . 'log' . DIRECTORY_SEPARATOR . 'imageHandler.log';
/*       if operating on large images....                                    */
$baseMemory_limit = ini_get( 'memory_limit' );
/*       imageHandler cache directory or null (default sys_get_temp_dir)     */
$baseCasheDir     = sys_get_temp_dir();
/*       temp/create filename prefix                                         */
$baseFilePrefix   = 'imageHandler_';
/*       image display filter (-1 = no filter)                               */
$baseFileFilter   = -1;
/*       image display order, 'name', 'date asc', 'date desc', 'size', 'type'*/
$baseFileOrder    = 'name';
/*       image crop and thumbnail box limits                                 */
/*       smaller images are dispayed 'as is'                                 */
$baseCropWidth    = '65%'; // percent of image width or in pixels
$baseCropHeight   = '45%'; // percent of image height or in pixels
$baseMaxDispWidth = 180;   // pixels
$baseMaxDispHeight = 100;  // pixels
      /* *******************************************************************
         setup, log
         ******************************************************************* */
$logPrios         = array( -1          => 'NONE',
                           LOG_DEBUG   => 'LOG_DEBUG',
                           LOG_INFO    => 'LOG_INFO',
                           LOG_NOTICE  => 'LOG_NOTICE',
                           LOG_WARNING => 'LOG_WARNING',
                           LOG_ERR     => 'LOG_ERR',
                           LOG_CRIT    => 'LOG_CRIT',
                           LOG_ALERT   => 'LOG_ALERT',
                           LOG_EMERG   => 'LOG_EMERG',
                         );
$log              = FALSE;
$logFile          = ( existAndNotEmpty( 'logFile' )) ? $_REQUEST['logFile'] : $baseLogFile;
if( isset( $_REQUEST['logPrio'] )) {
  $logPrio        = $_REQUEST['logPrio'];
  if( -1 != $logPrio ) {
    if( ! is_file( $logFile )) {
      if( ! is_dir( dirname( $logFile )))
        $msg[]    = "Can't find log directory, ". dirname( $logFile );
      elseif( ! is_writable( dirname( $logFile )))
        $msg[]    = "log directory not writable, ". dirname( $logFile );
      elseif( TRUE !== @touch( $logFile ))
        $msg[]    = "Can't create log file, ".$logFile;
    }
    elseif( ! is_writable( $logFile ))
      $msg[]      = "Can't write to log file, ".$logFile;
    else {                /*        Use PEAR Log or any other log class
                                    supporting fcn 'log( <msg>, <prio> )' and 'flush()'
                                    ex. eClog (http://kigkonsult/eClog) */
      include 'Log.php';  /*        here using PEAR log
                                    imageHandler PEAR Log adapt, force log flush when a crash appears */
      class imageHandlerLog extends Log { public function _destruct() { $this->flush(); parent::_destruct(); }}
      $log        = imageHandlerLog::factory( 'file', $logFile, 'ih', array(), $logPrio );
    }
  } // end if( -1 != $logPrio )
  else
    $logPrio      = -1;
} // end if( isset( $_REQUEST['logPrio'] ))
else { // 1st time only
  $logPrio        = $baseLogPrio;
  $logFile        = $baseLogFile;
  $log            = FALSE;
}
      /* *******************************************************************
         include imageHandler
         ******************************************************************* */
include $basePath.'imageHandler.class.php';
if( $log ) {
  imageHandler::$logger  = $log;
  imageHandler::$logprio = $logPrio;
}
      /* *******************************************************************
         check directories
         ******************************************************************* */
if( $log ) $log->log( basename( __FILE__ ).' 1 $_REQUEST= '.var_export( $_REQUEST, TRUE ), LOG_DEBUG );
// if( $log ) $log->log( basename( __FILE__ ).' 1 $_SERVER= '.var_export( $_SERVER, TRUE ), LOG_DEBUG );
$baseDirectory    = ( existAndNotEmpty( 'baseDirectory' )) ? $_REQUEST['baseDirectory'] : $defaultImageLib;
if( DIRECTORY_SEPARATOR != substr( $baseDirectory, -1 ))
  $baseDirectory .= DIRECTORY_SEPARATOR;
$loadDirectory    = ( existAndNotEmpty( 'loadDirectory' )) ? $_REQUEST['loadDirectory'] : $defaultImageLib;
if( DIRECTORY_SEPARATOR != substr( $loadDirectory, -1 ))
  $loadDirectory .= DIRECTORY_SEPARATOR;
if( $baseDirectory != substr( $loadDirectory, 0, strlen( $baseDirectory ))) {
  if( $log ) $log->log( "baseDirectory=$baseDirectory, loadDirectory=$loadDirectory, change", LOG_DEBUG );  // test ###
  $loadDirectory  = $baseDirectory;
}
// elseif( $log ) $log->log( "baseDirectory=$baseDirectory, loadDirectory=$loadDirectory, No change", LOG_DEBUG );// test ###
$saveDirectory    = ( existAndNotEmpty( 'saveDirectory' )) ? $_REQUEST['saveDirectory'] : $defaultImageLib;
if( DIRECTORY_SEPARATOR != substr( $saveDirectory, -1 ))
  $saveDirectory   .= DIRECTORY_SEPARATOR;
if( ! is_dir( $saveDirectory ))
  $msg[]          = "Storage '$saveDirectory' cannot be found!!";
elseif( ! is_writeable( $saveDirectory ))
  $msg[]          = "Storage '$saveDirectory' not writable";
      /* *******************************************************************
         check other input
         ******************************************************************* */
$outputPng        = ( array_key_exists( 'outputPng', $_REQUEST ) && empty( $_REQUEST['outputPng'] )) ? FALSE : TRUE; // true default
imageHandler::$outputpng = $outputPng;
$cacheDir         = ( existAndNotEmpty( 'cacheDir' ))      ? $_REQUEST['cacheDir']      : $baseCasheDir;
imageHandler::$cache = $cacheDir;
$filePrefix       = ( existAndNotEmpty( 'filePrefix' ))    ? $_REQUEST['filePrefix']    : $baseFilePrefix;
imageHandler::$filenamePrefix = $filePrefix;
$memory_limit     = ( existAndNotEmpty( 'memory_limit' ))  ? $_REQUEST['memory_limit']  : $baseMemory_limit;
if ( $memory_limit != $baseMemory_limit )
  ini_set( 'memory_limit', $memory_limit );
$imageFilterTypes = array( -1                 => 'ALL',
                           IMAGETYPE_GIF      => 'gif',
                           IMAGETYPE_JPEG     => 'jpeg',
                           IMAGETYPE_PNG      => 'png',
                           IMAGETYPE_SWF      => 'swf',
                           IMAGETYPE_PSD      => 'psd',
                           IMAGETYPE_BMP      => 'bmp',
                           IMAGETYPE_TIFF_II  => 'tiff', // intel byte order
                           IMAGETYPE_TIFF_MM  => 'tiff', // motorola byte order
                           IMAGETYPE_JPC      => 'jpc',
                           IMAGETYPE_JP2      => 'jp2',
                           IMAGETYPE_JPX      => 'jpx',
                           IMAGETYPE_JB2      => 'jb2',
                           IMAGETYPE_SWC      => 'swc',
                           IMAGETYPE_IFF      => 'iff',
                           IMAGETYPE_WBMP     => 'wbmp',
                           IMAGETYPE_XBM      => 'xbm',
                           IMAGETYPE_ICO      => 'ico',
                         );
asort( $imageFilterTypes );
$fileFilter       = ( existAndNotEmpty( 'fileFilter' ))    ? $_REQUEST['fileFilter']    : $baseFileFilter;
$fileOrders       = array( 'name', 'date asc', 'date desc', 'size', 'type' );
$fileOrder        = ( existAndNotEmpty( 'fileOrder' ))     ? $_REQUEST['fileOrder']     : $baseFileOrder;
$cropWidth        = ( existAndNotEmpty( 'cropWidth' ))     ? $_REQUEST['cropWidth']     : $baseCropWidth;
$cropHeight       = ( existAndNotEmpty( 'cropHeight' ))    ? $_REQUEST['cropHeight']    : $baseCropHeight;
$maxDispWidth     = ( existAndNotEmpty( 'maxDispWidth' ))  ? $_REQUEST['maxDispWidth']  : $baseMaxDispWidth;
$maxDispHeight    = ( existAndNotEmpty( 'maxDispHeight' )) ? $_REQUEST['maxDispHeight'] : $baseMaxDispHeight;
$action           = $baseUrl  . 'test' . DIRECTORY_SEPARATOR . basename( __FILE__ );
$action2          = $baseUrl  . 'imageHandler.php';
$cssUrl           = $baseUrl  . 'test' . DIRECTORY_SEPARATOR . 'imageHandler.css';
$testCaseIncl     = $basePath . 'test' . DIRECTORY_SEPARATOR . 'cropAndResizeTest.php';
$jsUrl            = $baseUrl  . 'test' . DIRECTORY_SEPARATOR . 'imageHandler.js';
$js2Url           = $action2."?p=".$outputPng;
$files            = array();
$times            = array();
$r                = 0; // managing HTML display page/section background colour switching
$operation        = null;
foreach( $_REQUEST as $k => $v ) {
  if(( 'operation' == substr( $k, 0, 9 )) && ! empty( $v )) {
    $operation    = $v;
    unset( $_REQUEST[$k] );
    break;
  }
}
      /* *******************************************************************
         imageHandler test!!
         ******************************************************************* */
if( ! empty( $operation ) && ( existAndNotEmpty( 'i' ))) {
  $time_start     = microtime( TRUE );
  $file           = urldecode( $_REQUEST['i'] );
  if( $log ) $log->log( "operation=$operation, i='{$file}', file='{$file}'", LOG_DEBUG );
  $filename       = basename( $file );
  imageHandler::$imageLib = $loadDirectory;           // testing imageHandler::$imageLib
  if( ! is_numeric( $operation )) {
    switch( $operation ) {
      case 'download':  $operation = 1;    break;
      case 'stream'  :  $operation = 2;    break;
      case 'save'    :  $operation = 3;    break;
      default        :  $operation = null; break;
    }
  }
//    imageHandler::$outputpng        = FALSE;   // default TRUE
  if( 3 == $operation )
    $outputFilename = $saveDirectory.$filename;     // path+filename, required
  else {
    $outputFilename = null;
    switch( mt_rand( 0, 2 )) {                                  // testing org., anonymous and (imageHandler) generated filename
      case 0 : $outputFilename = $filename; break;
      case 1 : $outputFilename = bin2hex( openssl_random_pseudo_bytes( 3, $cStrong )); break; // 6 random chars
    }
  }
  if( $log ) $log->log( "operation=$operation, i='{$_REQUEST['i']}', file='{$file}'", LOG_DEBUG );
  $result = imageHandler::Operate( $filename,
                                   array( 'operation' => $operation,   // download/stream/save
                                          'name'      => $outputFilename,
                                          'cx'        => ( isset( $_REQUEST['cx'] )) ? $_REQUEST['cx'] : null,
                                          'cy'        => ( isset( $_REQUEST['cy'] )) ? $_REQUEST['cy'] : null,
                                          'cwidth'    => ( existAndNotEmpty( 'cw' )) ? $_REQUEST['cw'] : null,
                                          'cheight'   => ( existAndNotEmpty( 'ch' )) ? $_REQUEST['ch'] : null,
                                          'width'     => ( existAndNotEmpty( 'w' ))  ? $_REQUEST['w']  : null,
                                          'height'    => ( existAndNotEmpty( 'h' ))  ? $_REQUEST['h']  : null,
                                          'maxwidth'  => ( existAndNotEmpty( 'mw' )) ? $_REQUEST['mw'] : null,
                                          'maxheight' => ( existAndNotEmpty( 'mh' )) ? $_REQUEST['mh'] : null,
                                        )
                                 );
// $log->log( "operation=$operation, result=".var_export( $result, TRUE ));// test ###
  if( $log ) $log->flush();
  if( 3 == $operation ) {
    $txt        = ( $result ) ? 'Successfull' : 'Unsuccessfull';
    $msg[]      = "{$txt} (crop/resize and) save of<br><i>".basename( $filename )."</i><br>into <i>{$saveDirectory}</i>.";
//$log->log( 'save + exit'.PHP_EOL.implode( PHP_EOL, $msg ), LOG_DEBUG ); exit();// test ###
    $times['save Image'] = microtime( TRUE ) - $time_start;
    imageHandler::$imageLib = null; // restore imageHandler::$imageLib, used below
  }
  else
    exit();
} // end if( ! empty( $operation ).....
      /* *******************************************************************
         get subdirectories in (base-image) directory
         ******************************************************************* */
$time_start       = microtime( TRUE );
getDirs( $baseDirectory, $dirs );
$times['load subDirs'] = microtime( TRUE ) - $time_start;
      /* *******************************************************************
         get files from loaddirectory
         ******************************************************************* */
if( ! empty( $loadDirectory )) {
  $msgix          = count( $msg );
  $time_start     = microtime( TRUE );
  if( ! is_dir( $loadDirectory ))
    $msg[$msgix]  = "'$loadDirectory' cannot be found!!";
  elseif( ! is_readable( $loadDirectory ))
    $msg[$msgix]  = "$loadDirectory exist but is not readable!!";
  else {
    $files        = getFiles( $loadDirectory, $fileFilter, $fileOrder );
    $msg[$msgix]  = "Found ".count( $files )." images in <i>{$loadDirectory}</i>";
  }
  $times['load Files'] = microtime( TRUE ) - $time_start;
}
//if( $log ) $log->log( 'files='.var_export( $files, TRUE ), LOG_DEBUG );
      /* ******************************************************************* */
$times['total_setup'] = microtime( TRUE ) - $time_startSetup;
      /* *******************************************************************
         page start
         ******************************************************************* */
$time_startPage   = microtime( TRUE );
$tabIndex         = 1;
$obFlags          = ( version_compare( PHP_VERSION, '5.4.0', '>=' )) ? PHP_OUTPUT_HANDLER_STDFLAGS ^ PHP_OUTPUT_HANDLER_REMOVABLE : FALSE;
ob_start( null, 0, $obFlags );
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN">
<HTML>
<HEAD>
<title>imageHandler</title>
<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
<meta name="author"      content="Kjell-Inge Gustafsson kigkonsult">
<meta name="copyright"   content="2015 Kjell-Inge Gustafsson, kigkonsult, All rights reserved">
<meta name="keywords"    content="PHP image handler imageHandler resize thumbnail">
<meta name="description" content="imageHandler is a PHP image crop/resize class, thumbnail">
<link rel="stylesheet" type="text/css" href="<?php echo $cssUrl; ?>">
<?php include $testCaseIncl; ?>
</HEAD>
<BODY>
<a name="top"></a>
<fieldset class="r<?php $r = 1 - $r; echo $r; ?>">
<table border="0">
<tbody>
<tr>
<td><h2>imageHandler</h2></td>
<td class="b br label>">Test and evaluation interface</td>
</tr>
</tbody>
</table>
</fieldset>
<?php /* *******************************************************************
         display form for configuration
         ******************************************************************* */
$legendText       = 'Configuration';
$displayGroupId   = 'configGroup';
$formName         = $displayGroupId.'Form';
$btnTabIndex      = $tabIndex++;
$updTabIndex      = $tabIndex++;
?>
<fieldset class="r<?php $r = 1 - $r; echo $r; ?>">
<legend><?php echo $legendText; ?></legend>
<form id="<?php echo $formName; ?>" action="<?php echo $action; ?>" method="post">
<table border="0">
<tbody>
<tr>
<td rowspan="2">
<table id="<?php echo $displayGroupId; ?>" style="display:none" border="0">
<tbody>
<tr>
<td class="br label w200p">image directory</td>
<td colspan="3"><input type="text" id="baseDirectory" name="baseDirectory" size="40" value="<?php echo $baseDirectory; ?>" tabindex="<?php echo $tabIndex++; ?>"></td>
</tr>
<tr>
<td class="br label">current directory</td>
<td colspan="3">
<select id="loadDirectory" name="loadDirectory" tabindex="<?php echo $tabIndex++; ?>">
<?php
foreach( $dirs as $k => $v ) {
  echo '<option value="'.$k.'" label="'.$k.'"';
  if( $k == $loadDirectory )
    echo ' selected="selected"';
  echo '>'.$v."</option>\n";
}
?>
</select>
</td>
</tr>
<tr>
<td class="br label">storage</td>
<td colspan="3"><input type="text" id="saveDirectory" name="saveDirectory" size="40" value="<?php echo $saveDirectory; ?>" tabindex="<?php echo $tabIndex++; ?>"></td>
</tr>
<tr>
<td class="br label">log</td>
<td>
<select id="logPrio" name="logPrio" onChange="cghLog(this)" tabindex="<?php echo $tabIndex++; ?>">
<?php
foreach( $logPrios as $k => $v ) {
  echo '<option value="'.$k.'" label="'.$k.'"';
  if( $k == $logPrio )
    echo ' selected="selected"';
  echo '>'.$v."</option>\n";
}
$class = ( '-1' == $logPrio ) ? ' class="grey"' : '';
?>
</select>
</td>
<td class="br label w50p">logfile</td>
<?php $class = ( $baseLogFile == $logFile ) ? ' class="grey"' : ''; ?>
<td colspan="2"><input type="text"<?php echo $class; ?> id="logFile" name="logFile" size="40" value="<?php echo $logFile; ?>" tabindex="<?php echo $tabIndex++; ?>"></td>
</tr>
<tr>
<td class="br label">memory_limit</td>
<?php $class = ( $baseMemory_limit == $memory_limit ) ? ' class="grey"' : ''; ?>
<td colspan="3"><input type="text" id="memory_limit" name="memory_limit"<?php echo $class; ?> size="10" value="<?php echo $memory_limit; ?>" tabindex="<?php echo $tabIndex++; ?>"></td>
</tr>
<tr>
<td class="br label">all output png</td>
<td>
<select id="logPrio" name="outputPng" tabindex="<?php echo $tabIndex++; ?>">
<option value="1" label="1"<?php if( $outputPng ) echo ' selected="selected"'; ?>>on</option>
<option value="0" label="0"<?php if( ! $outputPng ) echo ' selected="selected"'; ?>>off</option>
</select>
</td>
<td class="label" colspan="2">for jpg/gif images, all other output png<br>'ON' also force 'png' extension</td>
</tr>
<tr>
<td class="br label">cache</td>
<?php $class = ( $baseCasheDir == $cacheDir ) ? ' class="grey"' : ''; ?>
<td colspan="3"><input type="text" id="cacheDir" name="cacheDir"<?php echo $class; ?> size="40" value="<?php echo $cacheDir; ?>" tabindex="<?php echo $tabIndex++; ?>"></td>
</tr>
<tr>
<td class="br label">temp/create filename prefix</td>
<?php $class = ( $baseFilePrefix == $filePrefix ) ? ' class="grey"' : ''; ?>
<td colspan="3"><input type="text" id="filePrefix" name="filePrefix"<?php echo $class; ?> size="40" value="<?php echo $filePrefix; ?>" tabindex="<?php echo $tabIndex++; ?>"></td>
</tr>
<tr>
<td class="br label">image display filter</td>
<td colspan="3">
<select id="fileFilter" name="fileFilter" tabindex="<?php echo $tabIndex++; ?>">
<?php
foreach( $imageFilterTypes as $k => $v ) {
  echo '<option value="'.$k.'" label="'.$k.'"';
  if( $k == $fileFilter )
    echo ' selected="selected"';
  echo '>'.$v."</option>\n";
}
?>
</select>
</td>
</tr>
<tr>
<td class="br label">image order</td>
<td colspan="3">
<select id="fileOrder" name="fileOrder" tabindex="<?php echo $tabIndex++; ?>">
<?php
foreach( $fileOrders as $v ) {
  echo '<option value="'.$v.'" label="'.$k.'"';
  if( $v == $fileOrder )
    echo ' selected="selected"';
  echo '>'.$v."</option>\n";
}
?>
</select>
</td>
</tr>
<tr>
<td class="br label">image crop width</td>
<?php $class = ( $baseCropWidth == $cropWidth ) ? ' class="grey"' : ''; ?>
<td><input type="text" id="cropWidth" name="cropWidth"<?php echo $class; ?> size="5" value="<?php echo $cropWidth; ?>" tabindex="<?php echo $tabIndex++; ?>"></td>
<td class="label" colspan="2">percent of image width or in pixels</td>
</tr>
<tr>
<td class="br label">image crop height</td>
<?php $class = ( $baseCropHeight == $cropHeight ) ? ' class="grey"' : ''; ?>
<td><input type="text" id="cropHeight" name="cropHeight"<?php echo $class; ?> size="5" value="<?php echo $cropHeight; ?>" tabindex="<?php echo $tabIndex++; ?>"></td>
<td class="label" colspan="2">percent of image height or in pixels</td>
</tr>
<tr>
<td class="br label">max displ.width</td>
<?php $class = ( $baseMaxDispWidth == $maxDispWidth ) ? ' class="grey"' : ''; ?>
<td><input type="text" id="maxDispWidth" name="maxDispWidth"<?php echo $class; ?> size="5" value="<?php echo $maxDispWidth; ?>" tabindex="<?php echo $tabIndex++; ?>"></td>
<td class="label" colspan="2">percent of image width or in pixels</td>
</tr>
<tr>
<td class="br label">max disp.height</td>
<?php $class = ( $baseMaxDispHeight == $maxDispHeight ) ? ' class="grey"' : ''; ?>
<td><input type="text" id="maxDispHeight" name="maxDispHeight"<?php echo $class; ?> size="5" value="<?php echo $maxDispHeight; ?>" tabindex="<?php echo $tabIndex++; ?>"></td>
<td class="label" colspan="2">percent of image height or in pixels</td>
</tr>
</tbody>
</table>
</td>
<td class="br"><button tabindex="<?php echo $btnTabIndex; ?>" type="button" class="btn" title="config display?" onclick="toogleElement('<?php echo $displayGroupId; ?>');">+/-</button></td>
</tr>
<tr>
<td class="b br"><button tabindex="<?php echo $updTabIndex; ?>" type="button" class="btn" title="Update config" onclick="getElementById('<?php echo $formName; ?>').submit();">Update</button></td>
</tr>
</tbody>
</table>
</form>
</fieldset>
<?php
      /* *******************************************************************
         system msg??
         ******************************************************************* */
if( ! empty( $msg )) {
?>
<fieldset class="r<?php $r = 1 - $r; echo $r; ?>">
<table>
<tbody>
<tr>
<td class="label w100p">system message</td>
<td>
<?php echo implode( '<br>', $msg ); ?>
</td>
</tr>
</tbody>
</table>
</fieldset>
<?php
}
      /* *******************************************************************
         prepare to display images
         ******************************************************************* */
$time_start       = microtime( TRUE );
$fx               = 10;
if( ! empty( $files )) {
  $legendText     = 'Images';
  $displayGroupId = 'images';
  $formName       = $displayGroupId.'Form';
?>
<div class="r<?php $r = 1 - $r; echo $r; ?>">
<fieldset>
<legend><?php echo $legendText; ?></legend>
<?php /* *******************************************************************
         table header
         ******************************************************************* */ ?>
<table border="0">
<tbody>
<tr>
<td class="b label w400p">file name</td>
<td class="w50p"></td>
<td class="b br label w100p">imageType</td>
<td class="br label" colspan="2">contenttype</td>
<td class="b br label w100p">size</td>
</tr>
<tr>
<td class="u" colspan="3"></td>
<td class="label u w200p">file extension</td>
<td class="br label u" colspan="2">chg time</td>
</tr>
</tbody>
</table>
<?php /* *******************************************************************
         display data about each and every image
         ******************************************************************* */
  $fx               = 10;
  $imageUrl         = $action2."?i=%s&amp;cw=%s&amp;ch=%s&amp;mw={$maxDispWidth}&amp;mh={$maxDispHeight}&amp;o=2&amp;p=".$outputPng;
  $imageTitle       = '';
  if( ! empty( $cropWidth ) || ! empty( $cropHeight )) {
    $imageTitle    .= 'Cropped (';
    if( ! empty( $cropWidth ))
      $imageTitle  .= "w{$cropWidth}";
    if( ! empty( $cropHeight ))
      $imageTitle  .= "h{$cropHeight}";
    $imageTitle    .= ')';
  }
  if( ! empty( $maxDispWidth ) || ! empty( $maxDispHeight )) {
    if( ! empty( $imageTitle ))
      $imageTitle  .= ' and ';
    $imageTitle    .= 'resized (';
    if( ! empty( $maxDispWidth ))
      $imageTitle  .= "w{$maxDispWidth}";
    if( ! empty( $maxDispHeight ))
      $imageTitle  .= "h{$maxDispHeight}";
    $imageTitle    .= ')';
  }
  if( ! empty( $imageTitle ))
    $imageTitle    .= ', ';
  $imageTitle      .= 'click for full size!';
  $cropWidth        = urlencode( $cropWidth );
  $cropHeight       = urlencode( $cropHeight );
  if( $basePath == substr( $loadDirectory, 0, ( strlen( $basePath ))))
    $imageUrl2      = $baseUrl.str_replace( $basePath, '', $loadDirectory ).'%s';
  else
    $imageUrl2      = $action2."?i={$loadDirectory}%s";
  $onClickClear     = 'onclick="setRow(\'%d\',\'%d\')"';
  if( empty( $testcases ))
    $tnos           = array( 0 => '' );
  else {
    $tnos           = array();
    foreach( $testcases as $tno => $testcase )
      $tnos[$tno]   = $testcase['input'];
  }
  $onChangeTest     = 'onChange="test(\'%s\',\'%d\',this.value);"';
  $onClickAddTest   = 'onClick="testAdd(\'%s\',\'%d\');"';
  $onClickSubTest   = 'onClick="testSub(\'%s\',\'%d\');"';
  $onClickDl        = 'onclick="submitForm(\'%s\',\'%d\',\'download\',%s);"';
  $onClickStream    = 'onclick="submitForm(\'%s\',\'%d\',\'stream\');"';
  $onClickSave      = 'onclick="submitForm(\'%s\',\'%d\',\'save\');"';
  foreach( $files as $file ) {
    $fx            += 1;
    $fileFormName   = $formName.$fx;
    $reSizeable     = imageHandler::isResizable( $file['filename'] );
    if( ! is_bool( $reSizeable )) // i.e. '1'
      $reSizeable   = FALSE;
    $testgroupId    = "test{$fx}";
    $fcnClickDl1    = sprintf( $onClickDl, $fileFormName, $fx, 'true' );
    $fcnClickDl2    = sprintf( $onClickDl, $fileFormName, $fx, 'false' );
    $fcnClickClear  = sprintf( $onClickClear,  $fx, 0 );
    $fcnChangeTest  = sprintf( $onChangeTest,  $fileFormName, $fx );
    $fcnClickAddTest = sprintf( $onClickAddTest, $fileFormName, $fx );
    $fcnClickSubTest = sprintf( $onClickSubTest, $fileFormName, $fx );
    $fcnClickStream = sprintf( $onClickStream, $fileFormName, $fx );
    $fcnClickSave   = sprintf( $onClickSave,   $fileFormName, $fx );
    $APP13exifgroupId = "APP13exif{$fx}";
    $tcInput        = "input{$fx}";
    $tcOutput       = "output{$fx}";
?>
<div  class="r<?php $r = 1 - $r; echo $r; ?>">
<form id="<?php echo $fileFormName; ?>"   action="<?php echo $action; ?>" method="post">
<input type="hidden" name="baseDirectory" value="<?php echo $baseDirectory; ?>">
<input type="hidden" name="loadDirectory" value="<?php echo $loadDirectory; ?>">
<input type="hidden" name="saveDirectory" value="<?php echo $saveDirectory; ?>">
<input type="hidden" name="logPrio"       value="<?php echo $logPrio; ?>">
<input type="hidden" name="logFile"       value="<?php echo $logFile; ?>">
<input type="hidden" name="memory_limit"  value="<?php echo $memory_limit; ?>">
<input type="hidden" name="cacheDir"      value="<?php echo $cacheDir; ?>">
<input type="hidden" name="filePrefix"    value="<?php echo $filePrefix; ?>">
<input type="hidden" name="fileFilter"    value="<?php echo $fileFilter; ?>">
<input type="hidden" name="fileOrder"     value="<?php echo $fileOrder; ?>">
<input type="hidden" name="cropWidth"     value="<?php echo $cropWidth; ?>">
<input type="hidden" name="cropHeight"    value="<?php echo $cropHeight; ?>">
<input type="hidden" name="maxDispWidth"  value="<?php echo $maxDispWidth; ?>">
<input type="hidden" name="maxDispHeight" value="<?php echo $maxDispHeight; ?>">
<input type="hidden" name="operation"     value="" id="operation<?php echo $fx; ?>">
<input type="hidden" name="i" value="<?php echo urlencode( basename( $file['filename'] )); ?>">
<table border="0">
<tbody>
<tr>
<td class="w300p" colspan="2"><?php echo basename( $file['filename'] ); ?></td>
<td class="br" colspan="2"><?php echo $imageFilterTypes[$file['imageType']].' <span class="label">('.$file['imageType'].')</span>'; ?></td>
<td class="br label w150p"><?php echo $file['contenttype']; ?></td>
<td class="br w100p"><?php echo number_format ((float) $file['size'], 0, '.', ' ' ); ?></td>
</tr>
<tr>
<td class="w200p" rowspan="4">
<?php //
    if(( 15 != $file['imageType'] ) && ( 16 != $file['imageType'] ) && // can't display wbmp/xbm in browser...
       ( $maxDispWidth >= $file['width'] ) && ( $maxDispHeight >= $file['height'] )) {
?>
<a class="cursor" tabindex="<?php echo $tabIndex++; ?>" <?php echo $fcnClickDl1; ?>><img src="<?php printf( $imageUrl2, urlencode( basename( $file['filename'] ))); ?>" alt="Preview" title="click for Download"></a>
<?php
    }
    elseif( $reSizeable ) {
?>
<a class="cursor" tabindex="<?php echo $tabIndex++; ?>" <?php echo $fcnClickDl1; ?>><img src="<?php printf( $imageUrl, urlencode( $file['filename'] ), $cropWidth, $cropHeight ); ?>" alt="Preview" title="<?php echo $imageTitle; ?>"></a>
<?php
    }
?>
</td>
<td class="b bc w100p"><span class="label">w&nbsp;</span><?php echo $file['width']; ?></td>
<td class="b bc w100p"><span class="label">h&nbsp;</span><?php echo $file['height']; ?></td>
<td class="br"><?php echo $file['extension']; ?></td>
<td class="br label" colspan="2"><?php echo gmdate( 'Y-m-d H:i:s e', $file['ctime'] ); ?></td>
</tr>
<tr>
<td colspan="4">
<div id="<?php echo $APP13exifgroupId; ?>" style="display:none">
<?php
    if( isset( $file['imageInfo']['APP13'] ) || ( isset( $file['exifData'] ) && ! empty( $file['exifData'] ))) {
      echo '<table border="0"><tbody>'."\n";
      if( isset( $file['imageInfo']['APP13'] )) {
        $APP13            = iptcparse( $file['imageInfo']['APP13'] );
        if( ! empty( $APP13 )) {
          foreach( $APP13 as $APP13key => $APP13value ) {
            echo '<tr><td class="label w100p">'.$APP13key.'</td>';
            if( is_array( $APP13value ))
              $APP13value = implode( ',', $APP13value );
            echo '<td colspan="2">'.htmlspecialchars( $APP13value )."</td></tr>\n";
          }
        }
      } // end if( isset( $file['imageInfo']['APP13'] ))
      if( isset( $file['exifData'] ) && ! empty( $file['exifData'] )) {
        foreach( $file['exifData'] as $exifKey => $exifSection ) {
          foreach( $exifSection as $exifName => $exifValue ) {
            if( is_array( $exifValue )) {
              $exifValueStr = '';
              foreach( $exifValue as $exifValueK2 => $exifValueV2 )
                $exifValueStr .= "$exifValueK2 = $exifValueV2, ";
              $exifValue  = $exifValueStr;
            }
            if(( 'filedatetime' == strtolower( $exifName )) && is_numeric( $exifValue ))
              $exifValue .= '<br><span class="label">('.date( 'Y-m-d H:i:s', (int) $exifValue ).')</span>';
            echo '<tr><td class="label w100p">'.$exifKey.'</td><td class="label w100p">'.$exifName."</td><td>$exifValue</td></tr>\n";
          }
        }
      } // end if( isset( $file['exifData'] ) && ! empty( $file['exifData'] ))
      echo "</tbody></table>\n";
    } // end if( isset( $file['imageInfo']['APP13'] ) || (.....
?>
</div>
</td>
<td class="br">
<?php
    if( isset( $file['imageInfo']['APP13'] ) || ( isset( $file['exifData'] ) && ! empty( $file['exifData'] )))
      echo '<button tabindex="'.$tabIndex++.'" type="button" class="btn" title="Exif metadata" onclick="toogleElement(\''.$APP13exifgroupId.'\');">+/-</button>';
    if( $reSizeable )
      echo '<button tabindex="'.$tabIndex++.'" type="button" class="btn" title="Open test box" onclick="toogleElement(\''.$testgroupId.'\');">+/-</button>';
  ?>
</td>
</tr>
<tr>
<td colspan="5">
<table border="0">
<tbody>
<tr>
<td>
<?php if( $reSizeable ) { ?>
<table id="<?php echo $testgroupId; ?>" style="display:none;" border="0">
<tbody>
<tr>
<td class="bc label w100p" rowspan="4">
<b>test</b>
<br>
no&nbsp;<select id="TestSelect<?php echo $fx; ?>" tabindex="<?php echo $tabIndex++; ?>" <?php echo $fcnChangeTest; ?>>
<?php
    foreach( $tnos as $tno => $title ) {
      echo '<option value="'.$tno.'" label="'.$tno.'"';
      if( $tno == 0 )
        echo ' selected="selected"';
      if( ! empty( $title ))
        echo ' title="'.$title.'"';
      echo '>'.$tno."</option>\n";
    }
?>
</select>
<br>
<div>
<button id="<?php echo $fileFormName; ?>TestSubBtn" tabindex="<?php echo $tabIndex++; ?>" type="button" class="btn" title="Prev test" <?php echo $fcnClickSubTest; ?>><b>-</b></button>
&nbsp;
<button id="<?php echo $fileFormName; ?>TestAddBtn" tabindex="<?php echo $tabIndex++; ?>" type="button" class="btn" title="Next test" <?php echo $fcnClickAddTest; ?>><b>+</b></button>
</div>
<button id="<?php echo $fileFormName; ?>clear" tabindex="<?php echo $tabIndex++; ?>" type="button" class="btn" title="clear" <?php echo $fcnClickClear; ?>>clear</button>
</td>
<td class="b bc label w100p" colspan="2"><?php echo 'crop<br>left-upper<br>x&nbsp;-&nbsp;y'; ?></td>
<td class="b bc label w100p" colspan="2"><?php echo 'crop<br>width-height'; ?></td>

<td class="b bc label w100p" colspan="2"><?php echo 'output<br>width-height'; ?></td>
<td class="b bc label w100p" colspan="2"><?php echo 'in box max<br>width-height';  ?></td>
</tr>
<tr>
<td class="bc"><input type="text" id="cx<?php echo $fx; ?>" name="cx" size="2" value="" tabindex="<?php echo $tabIndex++; ?>" title="percent or in pixels"></td>
<td class="bc"><input type="text" id="cy<?php echo $fx; ?>" name="cy" size="2" value="" tabindex="<?php echo $tabIndex++; ?>" title="percent or in pixels"></td>
<td class="bc"><input type="text" id="cw<?php echo $fx; ?>" name="cw" size="2" value="" tabindex="<?php echo $tabIndex++; ?>" title="percent or in pixels"></td>
<td class="bc"><input type="text" id="ch<?php echo $fx; ?>" name="ch" size="2" value="" tabindex="<?php echo $tabIndex++; ?>" title="percent or in pixels"></td>
<td class="bc"><input type="text" id="w<?php  echo $fx; ?>" name="w"  size="2" value="" tabindex="<?php echo $tabIndex++; ?>" title="percent or in pixels"></td>
<td class="bc"><input type="text" id="h<?php  echo $fx; ?>" name="h"  size="2" value="" tabindex="<?php echo $tabIndex++; ?>" title="percent or in pixels"></td>
<td class="bc"><input type="text" id="mw<?php echo $fx; ?>" name="mw" size="2" value="" tabindex="<?php echo $tabIndex++; ?>" title="percent or in pixels"></td>
<td class="bc"><input type="text" id="mh<?php echo $fx; ?>" name="mh" size="2" value="" tabindex="<?php echo $tabIndex++; ?>" title="percent or in pixels"></td>
</tr>
<tr>
<td class="label" colspan="8"><span>Input&nbsp;:&nbsp;</span><span id="<?php echo $tcInput; ?>" colspan="6"><?php echo $testcases[0]['input']; ?></span></td>
</tr>
<tr>
<td class="label" colspan="8"><span>result&nbsp;:&nbsp;</span><span id="<?php echo $tcOutput; ?>" colspan="6"><?php echo $testcases[0]['result']; ?></span></td>
</tr>
</tbody>
</table>
<?php } // end if( $reSizeable ) ?>
</td>
<td class="b br">
<button id="<?php echo $fileFormName; ?>download" tabindex="<?php echo $tabIndex++; ?>" type="button" class="btn" title="Download" <?php echo $fcnClickDl2; ?>>Download</button>
<br>
<button id="<?php echo $fileFormName; ?>stream"   tabindex="<?php echo $tabIndex++; ?>" type="button" class="btn" title="Stream"   <?php echo $fcnClickStream; ?>>Stream</button>
<br>
<button id="<?php echo $fileFormName; ?>save"     tabindex="<?php echo $tabIndex++; ?>" type="button" class="btn" title="Save"     <?php echo $fcnClickSave; ?>>Save</button>
</tr>
</tbody>
</table>
</td>
</tr>
<tr>
</tr>
</tbody>
</table>
</form>
</div>
<?php
  } // end foreach( $files as $k => $file )
  $times['displ all files'] = microtime( TRUE ) - $time_start;
      /* *******************************************************************
         end form etc...
         ******************************************************************* */ ?>
</fieldset>
</div>
<?php
} // end if( ! empty( $files ))
      /* *******************************************************************
         page end
         ******************************************************************* */
$displayGroupId   = 'bottomExecInfo';
$times['display'] = microtime( TRUE ) - $time_startPage;
$times['total']   = microtime( TRUE ) - $time_startSetup;
$r                = 1 - $r;
$memory_get_usage      = number_format( (float) memory_get_usage( TRUE ), 0, '', ' ' );
$memory_get_peak_usage = number_format( (float) memory_get_peak_usage( TRUE ), 0, '', ' ' );
?>
<a name="bottom"></a><a name="down"></a>
<fieldset class="r<?php $r; echo $r; ?>">
<table border="0">
<tbody>
<tr>
<td class="b labelt w300p" rowspan="2">
<b>imageHandler <?php echo imageHandler::$version; ?></b><br>
Copyright &copy; 2015<br>
Kjell-Inge Gustafsson kigkonsult<br>
All rights reserved
</td>
<td>
<table class="hide"  style="display:none" id="<?php echo $displayGroupId; ?>"><tbody><tr>
<td class="w200p"><table><tbody>
  <tr><td class="br labelh" rowspan="2">Memory</td><td class="labelt w100p">&nbsp;<?php echo $memory_get_usage; ?></td></tr>
  <tr><td class="labelt"><?php echo "($memory_get_peak_usage)"; ?></td></tr>
  </tbody></table></td>
<td><table><tbody>
<?php
unset( $memory_get_usage, $memory_get_peak_usage );
$wl     = 0;
foreach( $times as $key => $time )
  $wl   = ( $wl < strlen( $key )) ? strlen( $key ) : $wl;
$wl    += 2;
foreach( $times as $key => $time ) {
  $k    = str_pad( $key, $wl );
  $t    = number_format( $time, 4 );
  printf( '<tr><td class="labelh">%1$s</td><td class="t"><span class="labelt">%2$s</span> <span class="labelh">sec</span></td></tr>%3$s', $k, $t, PHP_EOL );
}
?>
</tbody></table>
</td>
</tr></tbody></table></td>
<td class="w100p"><button id="TopBtn" name="execBtn" tabindex="<?php echo $tabIndex++; ?>" type="button" class="btn" title="exec data" onclick="toogleElement('<?php echo $displayGroupId; ?>');">+/-</button></td>
<td class="br w50p"><button id="TopBtn" name="TopBtn" tabindex="<?php echo $tabIndex++; ?>" type="button" class="btn" title="Top" onclick="window.location.hash='top';">Top</button></td>
</tr>
<tr>
<td class="b labelt"><?php echo 'Version: <a href="http://www.php.net/" target="_blank">PHP</a> '.PHP_VERSION.' and <a href="http://www.libgd.org/" target="_blank">GD</a> '.GD_VERSION.'.'; ?></td>
<td class="b br labelt" colspan="2">
<a href="http://kigkonsult.se/index.php" title="kigkonsult" tabindex="<?php echo ( $tabIndex + 3 ); ?>">kigkonsult</a><br>
<a href="http://kigkonsult.se/contact/index.php" title="contact" tabindex="<?php echo ( $tabIndex + 3 ); ?>">kigkonsult contact</a><br>
<a href="http://kigkonsult.se/imageHandler/index.php" title="homepage" tabindex="<?php echo ( $tabIndex + 3 ); ?>">imageHandler homepage</a>
</td>
</tr>
</tbody>
</table>
</fieldset>
<?php if( ! empty( $files )) { ?>
<script type="text/javascript">
var testcases=new Array(<?php echo count($testcases); ?>),js2Url='<?php echo $js2Url; ?>';
<?php
foreach( $testcases as $tix => $testcase ) {
  echo "testcases[$tix] = {};\n";
  foreach( $testcase as $key => $value )
    echo "testcases[$tix].$key = '".htmlentities( $value )."';\n";
}
?>
</script>
<?php } ?>
<script type="text/javascript" src="<?php echo $jsUrl; ?>"></script>
</body>
</html>
<?php
while (@ob_end_flush());
if( $log ) $log->flush();
exit();
/** *************************************************************************
 * existAndNotEmpty
 *
 * check $_REQUEST for set and not empty key
 *
 * @param string $key
 * @return bool
 */
function existAndNotEmpty( $key ) {
  return ( array_key_exists( $key, $_REQUEST ) && ! empty( $_REQUEST[$key] )) ? TRUE : FALSE;
}
/**
 * getDirs
 *
 * return array of all subdirectories in directory
 *
 * @param string $directory
 * @return array
 */
function getDirs( $directory, array & $dirs = null ) {
  if( DIRECTORY_SEPARATOR != substr( $directory, -1 ))
    $directory     .= DIRECTORY_SEPARATOR;
  if( empty( $dirs ))
    $dirs           = array( $directory => $directory );
  $iterator         = new DirectoryIterator( $directory );
  $fileCnt          = 0;
  $subDirs          = array();
  foreach( $iterator as $dirPart ) {
    if( $dirPart->isDot())
      continue;
    elseif( $dirPart->isFile())
      $fileCnt     += 1;
    elseif( $dirPart->isDir())
      $subDirs[]    = $dirPart->getPathname();
  }
  if( 0 < $fileCnt )
    $dirs[$directory] = $directory;
  foreach( $subDirs as $subDir )
    getDirs( $subDir, $dirs );
  ksort( $dirs, SORT_STRING );
}
function getDirsOrg( $directory, array & $dirs = null ) {
  if( empty( $dirs ))
    $dirs           = array( $directory => $directory );
  $directory        = new DirectoryIterator( $directory );
  foreach( $directory as $dirPart ) {
    if( ! $dirPart->isDir() || $dirPart->isDot())
      continue;
    $dirName        = $dirPart->getPathname();
    if( DIRECTORY_SEPARATOR != substr( $dirName, -1 ))
      $dirName     .= DIRECTORY_SEPARATOR;
    $dirs[$dirName] = $dirName;
    getDirs( $dirName, $dirs );
  }
  ksort( $dirs, SORT_STRING );
}
/**
 * getFiles
 *
 * return array of files in directory
 *
 * @param string $directory
 * @param int    $fileFilter
 * @param string $fileOrder
 * @return array
 */
function getFiles( $directory, $fileFilter, $fileOrder ) {
  $directory     = new DirectoryIterator( $directory );
  $files         = array();
  foreach( $directory as $file ) {
    if( ! $file->isFile())
      continue;
    $filename    = $file->getPathname();
    if((( FALSE === ( $imageType = @exif_imagetype( $filename ))) || ( 1 > $imageType ) || ( 17 < $imageType )))
      continue;
    if( FALSE === ( $fData = @getimagesize( $filename, $imageInfo )))
      continue;
    if(( -1 != $fileFilter ) && ( $fData[2] != $fileFilter ))
      continue;
    $ctime       = $file->getMTime();
    $contenttype = @image_type_to_mime_type( $imageType );
    $size        = $file->getsize();
    switch( $fileOrder ) {
      case 'date'      :
      case 'date asc'  : $sortKey = $file->getMTime();           break;
      case 'date desc' : $sortKey = PHP_INT_MAX - $ctime;        break;
      case 'size'      : $sortKey = sprintf( "%1$015u", $size ); break;
      case 'type'      : $sortKey = $contenttype;                break;
      case 'name'      :
      default          : $sortKey = $filename;                   break;
    }
    $files[]     = array( 'sortKey'      => $sortKey,
                          'filename'     => $filename,
                          'extension'    => $file->getExtension(),
                          'size'         => $size,
                          'ctime'        => $ctime,
                          'width'        => $fData[0],
                          'height'       => $fData[1],
                          'contenttype'  => $contenttype,
                          'imageType'    => $fData[2],
                          'imageInfo'    => $imageInfo,
                          'exifData'     => @exif_read_data( $filename , null, TRUE ),
                    );
  }
  usort( $files, 'cmp' );
  return $files;
}
function cmp( $a, $b ) {
  return strcasecmp( $a['sortKey'], $b['sortKey'] );
}
