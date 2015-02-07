<?php
/**
 * imageHandler.class.php
 *
 * Crop and/or resize (reshape) image followed by download/stream (or save)
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
 */
class imageHandler {
/**
 * @var string version
 */
  public static $version = '1.4';
/**
 * default operation
 *
 * 1=download, 'download', send header 'Content-Disposition: attachment; filename="<name>"'
 * 2=stream,   'download', send header 'Content-Disposition: filename="<name>"'
 * 3=save      save to disk
 *
 * @var int defaultOperation;
 * @static
 */
  public static $defaultOperation = 2;
/**
 *
 * for (resizable) images, all output as png
 *
 * @var bool outputpng
 * @static
 */
  public static $outputpng = TRUE;
/**
 * image directory
 *
 * @var string imageLib
 * @static
 */
  public static $imageLib = null;
/**
 * prefix for created filenames
 *
 * @var string filenamePrefix
 * @static
 */
  public static $filenamePrefix = 'imageHandler_';
/**
 * cache directory
 *
 * @var string cache
 * @static
 */
  public static $cache = null;
/**
 *
 * any log class supporting log( <msg>, <prio> ) and flush() methods (ex PEAR log)
 *
 * @var object logger
 * @static
 */
  public static $logger = null;
/**
 * log priority level
 *
 * 7=LOG_DEBUG, 6=LOG_INFO, 5=LOG_NOTICE, 4=LOG_WARN, 3=LOG_ERR, 2=LOG_CRIT, 1=LOG_ALERT, 0=LOG_EMERG
 * Recommendation: LOG_NOTICE
 *
 * @var int logprio
 * @static
 */
  public static $logprio = LOG_NOTICE;
/**
 * operation modes
 *
 * @var array operationModes
 * @access private
 * @static
 */
  private static $operationModes = array( 1 => 'download',
                                          2 => 'stream',
                                          3 => 'save'
                                        );
/**
 * image types and (it's contenttype) default extension
 *
 * @var array fmts
 * @access private
 * @static
 */
  private static $iTypes = array( IMAGETYPE_GIF      => array( 'resizable' => TRUE,  'ext' => 'gif' ),
                                  IMAGETYPE_JPEG     => array( 'resizable' => TRUE,  'ext' => 'jpg' ),
                                  IMAGETYPE_JPEG2000 => array( 'resizable' => FALSE, 'ext' => 'jp2' ),
                                  IMAGETYPE_PNG      => array( 'resizable' => TRUE,  'ext' => 'png' ),
                                  IMAGETYPE_SWF      => array( 'resizable' => FALSE, 'ext' => 'swf' ),
                                  IMAGETYPE_PSD      => array( 'resizable' => FALSE, 'ext' => 'psd' ),
                                  IMAGETYPE_BMP      => array( 'resizable' => TRUE,  'ext' => 'bmp' ),
                                  IMAGETYPE_WBMP     => array( 'resizable' => TRUE,  'ext' => 'wbmp' ),
                                  IMAGETYPE_XBM      => array( 'resizable' => TRUE,  'ext' => 'xbm' ),
                                  IMAGETYPE_TIFF_II  => array( 'resizable' => 1,     'ext' => 'tiff' ),
                                  IMAGETYPE_TIFF_MM  => array( 'resizable' => 1,     'ext' => 'tiff' ),
                                  IMAGETYPE_IFF      => array( 'resizable' => FALSE, 'ext' => 'tff' ),
                                  IMAGETYPE_JB2      => array( 'resizable' => FALSE, 'ext' => 'jb2' ),
                                  IMAGETYPE_JPC      => array( 'resizable' => FALSE, 'ext' => 'jpc' ),
                                  IMAGETYPE_JP2      => array( 'resizable' => FALSE, 'ext' => 'jp2' ),
                                  IMAGETYPE_JPX      => array( 'resizable' => FALSE, 'ext' => 'jpx' ),
                                  IMAGETYPE_SWC      => array( 'resizable' => FALSE, 'ext' => 'swc' ),
                                  IMAGETYPE_ICO      => array( 'resizable' => FALSE, 'ext' => 'ico' ),
                                );
/**
 * flags for ob_start
 *
 * @var int obFlags
 * @access private
 * @static
 */
  private static $obFlags = null;
/**
 * headers
 *
 * @var array headers
 * @access private
 * @static
 */
  private static $headers = array( 'ct'  => 'Content-Type: %s',
                                   'cd1' => 'Content-Disposition: attachment; filename="%s"',
                                   'cd2' => 'Content-Disposition: filename="%s"',
                                   'cl'  => 'Content-Length: %s',
                                 );
/**
 * resizing arguments
 *
 * @var array sizeArgs
 * @access private
 * @static
 */
  private static $sizeArgs = array( 'cx', 'cy', 'cwidth', 'cheight', 'width', 'height', 'maxwidth', 'maxheight' );
/* ************************************************************************** */
/*          the workshop                                                      */
/** *************************************************************************
 * isResizable
 *
 * Return bool TRUE if file is resizable, FALSE if not
 * For TIFF files and an embedded thumbnail exist, '1' (one) is returned
 *
 * @param string $file
 * @uses imageHandler::$cache
 * @uses imageHandler::copyRemote2temp()
 * @uses imageHandler::theEnd()
 * @uses imageHandler::$iTypes
 * @return bool
 * @static
 */
  public static function isResizable( $file ) {
    $time_start = microtime( TRUE );
    $inputRsc   = $file;                                  // remote image resource
    if(( FALSE !== ( $urlParts = parse_url( $file ))) && ! empty( $urlParts['scheme'] )) {
      if( empty( self::$cache ))
        self::$cache = sys_get_temp_dir();
      if( FALSE === ( $tempfile = self::copyRemote2temp( $file, 'isRsz' )))
        return self::theEnd( __METHOD__, FALSE, null, null, LOG_ERR );
      $file     = $tempfile;
    }
    elseif( ! empty( self::$imageLib ))                   // local image resource
      $file     = self::$imageLib.$file;
    if( FALSE === self::checkFile( $file ))
      return self::theEnd( __METHOD__, FALSE, $time_start, null, LOG_ERR );
    if( FALSE === ( $fData = @getimagesize( $file ))) {   // do the check
      if( isset( $tempfile ))
        unlink( $tempfile );
      return self::theEnd( __METHOD__, FALSE, $time_start, "FALSE, Unreadable or invalid image ({$inputRsc})", LOG_ERR );
    }
    if(( IMAGETYPE_TIFF_II == $fData[2] ) || ( IMAGETYPE_TIFF_MM == $fData[2] )) {     // intel byte order/motorola byte order TIFF
      $result   = ( FALSE !== @exif_thumbnail( $file )) ? 1 : FALSE;
      if( isset( $tempfile ))
        unlink( $tempfile );
      return self::theEnd( __METHOD__, $result, $time_start, "tiff with enbedded thumbnail", LOG_INFO );
    }
    if( ! isset( self::$iTypes[$fData[2]] ))
      return self::theEnd( __METHOD__, FALSE, $time_start, $inputRsc.', type='.$fData[2].', unknown!!!', LOG_ERR );
    $result     = self::$iTypes[$fData[2]]['resizable'];
    if( isset( $tempfile ))
      unlink( $tempfile );
    $resizable  = ( ! is_bool( $result )) ? '1' : ( $result ) ? 'True' : 'False';
    return self::theEnd( __METHOD__, $result, $time_start, $inputRsc.', type='.$fData[2].', resizable='.$resizable, LOG_INFO );
  }
/** *************************************************************************
 * Operate
 *
 * read image from disk, re-shapes (opt) and return result to browser alt. save to disk
 *
 * @param string $file   image file location or url
 * @param array  $props  image output parameters ((opt) name / (opt) cwidth / (opt) cheight / (opt) width / (opt) height / (opt) maxwidth / (opt) maxheight / (opt) operation )
                         operation: 1=download, 2='stream', 3=save to disk
 * @uses imageHandler::log()
 * @uses imageHandler::$obFlags
 * @uses imageHandler::$cache
 * @uses imageHandler::$version
 * @uses imageHandler::$defaultOperation
 * @uses imageHandler::$outputpng
 * @uses imageHandler::$imageLib
 * @uses imageHandler::$filenamePrefix
 * @uses imageHandler::$logger
 * @uses imageHandler::$logprio;
 * @uses imageHandler::checkInput()
 * @uses imageHandler::imageHandlerExit()
 * @uses imageHandler::getImageMetadata()
 * @uses imageHandler::fixMixedData()
 * @uses imageHandler::$iTypes
 * @uses imageHandler::fixExtension()
 * @uses imageHandler::calculate()
 * @uses imageHandler::$headers
 * @uses imageHandler::returnAsIs()
 * @uses imageHandler::originName()
 * @uses imageHandler::$outputpng
 * @uses imageHandler::doTheJobb()
 * @uses imageHandler::imagecreatefrombmp2()
 * @uses imageHandler::reShape()
 * @return bool TRUE on success, FALSE on error
 * @static
 */
  public static function Operate( $file, array $props ) {
/* ************************************************************************** */
/*          setup, check input and get image resource metadata                */
/* ************************************************************************** */
    $time_start = microtime( TRUE );
    if( empty( self::$obFlags ))
      self::$obFlags = ( version_compare( PHP_VERSION, '5.4.0', '>=' )) ? PHP_OUTPUT_HANDLER_STDFLAGS ^ PHP_OUTPUT_HANDLER_REMOVABLE : FALSE;
    if( empty( self::$cache ))
      self::$cache = sys_get_temp_dir();
    $txt        = 'ver:'.self::$version.' Start, defOp:'.self::$defaultOperation.', outPng:'.self::$outputpng.', iLib:'.self::$imageLib.', cache='.self::$cache.', prefix='.self::$filenamePrefix;
    if( ! empty( self::$logger ))
      $txt     .= ', log+prio:'.self::$logprio;
    self::log( __METHOD__ , $txt, LOG_INFO );
    $d          = array();
    if( FALSE === self::checkInput( $file, $props ))
      return self::imageHandlerExit( FALSE, $file, $d, $props, $time_start );
    if( FALSE === ( $fData = self::getImageMetadata( $file, $props )))
      return self::imageHandlerExit( FALSE, $file, $fData, $props, $time_start );
    self::fixMixedData( $file, $fData, $props );
/* ************************************************************************** */
/*            if resizable, calculate new size and proportions                */
/*            OR return embedded tiff thumbnail image and exit                */
/*            OR return As Is and exit                                        */
/* ************************************************************************** */
    switch( TRUE ) {
      case ( TRUE === self::$iTypes[$fData[2]]['resizable'] ) :
        $ratio   = self::calculate( $file, $fData, $props );
        break;
      case ( 1 == self::$iTypes[$fData[2]]['resizable'] ) :
        if(( 3 != $props['operation'] ) &&       // if a tiff has an embedded thumbnail, return it
           (( PHP_INT_MAX > $props['maxwidth'] ) || ( PHP_INT_MAX > $props['maxheight'] )) &&
           ( FALSE !== ( $image = @exif_thumbnail( $file, $w, $h, $imageType )))) {
          self::fixExtension( $props['name'], $imageType );
          $is    = strlen( $image );
          ob_start( null, 0, self::$obFlags );
          header( sprintf( self::$headers['ct'], image_type_to_mime_type( $imageType ) ));
          header( sprintf( ( 1 == $props['operation'] ) ? self::$headers['cd1'] : self::$headers['cd2'],
                           $props['name'] ));
          header( sprintf( self::$headers['cl'], $is ));
          echo $image;
          @ob_flush();
          flush();
          @imagedestroy( $image );
          return self::imageHandlerExit( TRUE, $file, $fData, $props, $time_start );
        } // else fall through...
      default:
        $result = self::returnAsIs( $file, $fData, $props );
        return self::imageHandlerExit( $result, $file, $fData, $props, $time_start );
        break;
    }
/* ************************************************************************** */
/*            no resize/output format change is required,                     */
/*            operate and return 'as is'                                      */
/*            exit                                                            */
/* ************************************************************************** */
    if(( FALSE === $ratio[0]['doResize'] ) && (( FALSE === self::$outputpng ) || ( IMAGETYPE_PNG == $fData[2] ))) {
      $result = self::returnAsIs( $file, $fData, $props );
      return self::imageHandlerExit( $result, $file, $fData, $props, $time_start );
    }
/* ************************************************************************** */
/*            operate                                                         */
/* ************************************************************************** */
    static $crErrTxt   = "Can't create image object from (%s) %s";
    $result            = FALSE;
    switch( $fData[2] ) {
/*    *********************************************************************** */
      case IMAGETYPE_GIF :
        if( FALSE === ( $image = @imagecreatefromgif( $file ))) {
          self::log( __METHOD__ , sprintf( $crErrTxt, 'gif', self::originName( $file, $props )), LOG_ERR );
          break;
        }
        $result    = TRUE;
        $imageType = IMAGETYPE_GIF;
        break;
/*    *********************************************************************** */
      case IMAGETYPE_JPEG :
        if( FALSE === ( $image = @imagecreatefromjpeg( $file ))) {
          self::log( __METHOD__ , sprintf( $crErrTxt, 'jpeg', self::originName( $file, $props )), LOG_ERR );
          break;
        }
        $result    = TRUE;
        $imageType = IMAGETYPE_JPEG;
        break;
/*    *********************************************************************** */
      case IMAGETYPE_PNG :
        if( FALSE === ( $image = @imagecreatefrompng( $file ))) {
          self::log( __METHOD__ , sprintf( $crErrTxt, 'png', self::originName( $file, $props )), LOG_ERR );
          break;
        }
        $result    = TRUE;
        $imageType = IMAGETYPE_PNG;
        break;
/*    *********************************************************************** */
      case IMAGETYPE_BMP :    // always output as png
        if( FALSE === ( $image = self::imagecreatefrombmp( $file ))) {
          self::log( __METHOD__ , sprintf( $crErrTxt, 'bmp', self::originName( $file, $props )), LOG_ERR );
          break;
        }
        $result    = TRUE;
        $imageType = IMAGETYPE_PNG;
        break;
/*    *********************************************************************** */
      case IMAGETYPE_WBMP :   // always output as png
        if( FALSE === ( $image = @imagecreatefromwbmp( $file ))) {
          self::log( __METHOD__ , sprintf( $crErrTxt, 'wbmp', self::originName( $file, $props )), LOG_ERR );
          break;
        }
        $result    = TRUE;
        $imageType = IMAGETYPE_PNG;
        break;
/* ************************************************************************** */
      case IMAGETYPE_XBM :    // always output as png
        if( FALSE === ( $image = @imagecreatefromxbm( $file ))) {
          self::log( __METHOD__ , sprintf( $crErrTxt, 'xbm', self::originName( $file, $props )), LOG_ERR );
          break;
        }
        $result    = TRUE;
        $imageType = IMAGETYPE_PNG;
        break;
/* ************************************************************************** */
      case IMAGETYPE_ICO :
      case IMAGETYPE_SWF :
      case IMAGETYPE_PSD :
      case IMAGETYPE_TIFF_II : //(intel byte order)
      case IMAGETYPE_TIFF_MM : //(motorola byte order)
      case IMAGETYPE_JPC :
      case IMAGETYPE_JP2 :
      case IMAGETYPE_JPX :
      case IMAGETYPE_JB2 :
      case IMAGETYPE_SWC :
      case IMAGETYPE_IFF :
      case IMAGETYPE_ICO :
      default: // return whatever it is... and 'as is'... and terminate
        $result = self::returnAsIs( $file, $fData, $props );
        return self::imageHandlerExit( $result, $file, $fData, $props, $time_start );
        break;
    } // end switch( $fData[2] )
    if( $result && ( FALSE !== $ratio[0]['doResize'] ) && ( FALSE === ( $image = self::reShape( $fData[2], $file, $image, $ratio, 0 ))))
      $result = FALSE;
    if( $result && ( FALSE !== $ratio[1]['doResize'] ) && ( FALSE === ( $image = self::reShape( $fData[2], $file, $image, $ratio, 1 ))))
      $result = FALSE;
    unset( $ratio );
    if( $result )
      $result = self::doTheJobb( $imageType, $file, $image, $fData, $props );
    if( isset( $image ))
      @imagedestroy( $image );
    return self::imageHandlerExit( $result, $file, $fData, $props, $time_start );
  }
/* ************************************************************************** */
/*          utility functions                                                 */
/** *************************************************************************
 * bmp2gd
 *
 * convert BMP file to gd file
 *
 * @param string $src
 * @param string $dest
 * @uses imageHandler::log()
 * @uses imageHandler::trimOutput()
 * @return bool
 * @access private
 * @static
 * @see http://en.wikipedia.org/wiki/BMP_file_format
 */
  private static function bmp2gd( $src, $dest ) {
    static $txts = array( "Can't open (source) '%s'",
                          "Can't read 14 bytes of (source) '%s'",
                          'vtype/Vsize/v2reserved/Vsrc',
                          "Can't find signature 'BM' in (source) '%s'",
                          "'%s' header=",
                          "Can't read 40 bytes (after first 14)) from (source) '%s'",
                          'Vsize/Vwidth/Vheight/vplanes/vbits/Vcompression/Vimagesize/Vxres/Vyres/Vncolor/Vimportant',
                          "'%s' info=",
                          "Can't open (dest) '%s'",
                          "Can't read %d bytes (after first 54)) from (source) '%s'",
                        );
    $fIn         = basename( $src );
    if( FALSE === ( $fpin = fopen( $src, 'rb' ))) {
      self::log( __METHOD__ , sprintf( $txts[0], $fIn ), LOG_ERR );
      return FALSE;
    }
    if( FALSE === ( $str = fread( $fpin, 14 ))) {
      self::log( __METHOD__ , sprintf( $txts[1], $fIn ), LOG_ERR );
      fclose( $fpin );
      return FALSE;
    }
    $header      = unpack( $txts[2], $str);
    extract( $header );
    if( $type != 0x4D42 ) { // signature "BM"
      self::log( __METHOD__ , sprintf( $txts[3], $fIn ), LOG_ERR );
      fclose( $fpin );
      return FALSE;
    }
    self::log( __METHOD__ , sprintf( $txts[4], $fIn ).self::trimOutput( $header ), LOG_DEBUG );
    if( FALSE === ( $str = fread( $fpin, 40 ))) {
      self::log( __METHOD__ , sprintf( $txts[5], $fIn ), LOG_ERR );
      fclose( $fpin );
      return FALSE;
    }
    $info        = unpack( $txts[6], $str );
    self::log( __METHOD__ , sprintf( $txts[7], $fIn ).self::trimOutput( $info ), LOG_DEBUG );
    extract( $info );
    unset( $str, $header, $info );
    if( FALSE === ( $fpout = fopen( $dest, 'wb' ))) {
      self::log( __METHOD__ , sprintf( $txts[8], $dest ), LOG_ERR );
      fclose( $fpin );
      return false;
    }
    $paletteSize = $src - 54;
    $gdHeader    = '';
    $gdHeader   .= ( $paletteSize == 0 ) ? "\xFF\xFE" : "\xFF\xFF"; // true-color vs. palette
    $gdHeader   .= pack( 'n2', $width, $height );
    $gdHeader   .= ( $paletteSize == 0 ) ? "\x01" : "\x00";
    if( 0 < $paletteSize ) {
      $noColours = $paletteSize / 4;
      $gdHeader .= pack( 'n', $noColours );
    }
    $gdHeader   .= "\xFF\xFF\xFF\xFF"; // no transparency
    fwrite( $fpout, $gdHeader );
    unset( $gdHeader );
    if( 0 < $paletteSize ) {
      if( FALSE === ( $palette = fread( $fpin, $paletteSize ))) {
        self::log( __METHOD__ , sprintf( $txts[9], $paletteSize, $fIn ), LOG_ERR );
        fclose( $fpin );
        fclose( $fpout );
        return FALSE;
      }
      $gdPalette = '';
      $x         = 0;
      while( $x < $paletteSize ) {
        $b       = $palette{$x++};
        $g       = $palette{$x++};
        $r       = $palette{$x++};
        $a       = $palette{$x++};
        $gdPalette .= "$r$g$b$a";
      }
      $gdPalette .= str_repeat( "\x00\x00\x00\x00", ( 256 - $noColours ));
      fwrite( $fpout, $gdPalette );
      unset( $gdPalette, $noColours );
    } // end if( $paletteSize )
    $scanLineSize  = (( $bits * $width ) + 7 ) >> 3;
    $scanLineAlign = ( $scanLineSize & 0x03 ) ? 4 - ( $scanLineSize & 0x03 ) : 0;
    for( $i = 0, $l = $height - 1; $i < $height; $i++, $l-- ) { // BMP stores scan lines starting from bottom
      @fseek( $fpin, $src + (( $scanLineSize + $scanLineAlign ) * $l ));
      $scanLine  = @fread( $fpin, $scanLineSize );
      $gdScanLine = '';
      switch( $bits ) {
        case 24 :
          $x     = 0;
          while( $x < $scanLineSize ) {
            $b   = $scanLine{$x++};
            $g   = $scanLine{$x++};
            $r   = $scanLine{$x++};
            $gdScanLine .= "\x00$r$g$b";
          }
          break;
        case 8 :
          $gdScanLine = $scanLine;
          break;
        case 4 :
          $j     = 0;
          while( $x < $scanLineSize ) {
            $byte = ord( $scanLine{$x++} );
            $p1  = chr( $byte >> 4 );
            $p2  = chr( $byte & 0x0F );
            $gdScanLine .= "$p1$p2";
          }
          $gdScanLine = substr( $gdScanLine, 0, $width );
          break;
        case 1 :
          $x     = 0;
          while( $x < $scanLineSize ) {
            $byte = ord( $scanLine{$x++} );
            $p1  = chr((int) (( $byte & 0x80 ) != 0 ));
            $p2  = chr((int) (( $byte & 0x40 ) != 0 ));
            $p3  = chr((int) (( $byte & 0x20 ) != 0 ));
            $p4  = chr((int) (( $byte & 0x10 ) != 0 ));
            $p5  = chr((int) (( $byte & 0x08 ) != 0 ));
            $p6  = chr((int) (( $byte & 0x04 ) != 0 ));
            $p7  = chr((int) (( $byte & 0x02 ) != 0 ));
            $p8  = chr((int) (( $byte & 0x01 ) != 0 ));
            $gdScanLine .= "$p1$p2$p3$p4$p5$p6$p7$p8";
          }
          $gdScanLine = substr( $gdScanLine, 0, $width );
          break;
      } // end switch( TRUE )
      fwrite( $fpout, $gdScanLine );
    } // end for( $i = 0, $l = $height - 1; $i < $height; $i++, $l-- )
    fclose( $fpin );
    fclose( $fpout );
    return TRUE;
  }
/** *************************************************************************
 * calculate
 *
 * managing sizing arguments
 *
 * @param string $file
 * @param array  $fData  [0]=image width, [1]=image height
 * @param array  $props
 * @uses imageHandler::calculateInBox()
 * @uses imageHandler::theEnd()
 * @return array()
 * @access private
 * @static
 */
  private static function calculate( $file, array $fData, array $props ) {
    static $ltxt  = '%s: wh=%d/%d/%s ';
    $cnt          = 0;                                    // calculateInBox counts
    $op           = '';
    $ratio        = array( 0 => array( 'doResize' => FALSE,
                                       'destW'    => $fData[0],
                                       'destH'    => $fData[1],
                                       'ratio'    => ( $fData[0] / $fData[1] ),
                                       'srcX'     => '0',
                                       'srcY'     => '0',
                                       'srcW'     => $fData[0],
                                       'srcH'     => $fData[1],
                                     ),
                           1 => array( 'doResize' => FALSE ),
                         );
    $x            = 0;
    $txt          = PHP_EOL.sprintf( $ltxt, "(#{$x}) src", $ratio[$x]['srcW'], $ratio[$x]['srcH'], number_format( $ratio[$x]['ratio'], 4 ));
    if(( isset( $props['cwidth'] )  && ( $fData[0] > $props['cwidth'] )) ||
       ( isset( $props['cheight'] ) && ( $fData[1] > $props['cheight'] ))) {
      $ratio[$x]['doResize'] = TRUE;                          // proportional centrered image crop
      $orgCw = $props['cwidth'];  // test
      $orgCh = $props['cheight']; // test
      $ratio[$x]['srcX']   = $props['cx'];
      $ratio[$x]['destW'] = $ratio[$x]['srcW'] = ( $fData[0] - $props['cx'] );
      if( $fData[0] != $ratio[$x]['srcW'] )
        self::log( __METHOD__, basename( $file ).", cx orgW={$fData[0]}, orgCw={$orgCw}, cx={$props['cx']}, srcX={$ratio[$x]['srcX']}, srcW={$ratio[$x]['srcW']}", LOG_DEBUG );
      if( $fData[0] > $props['cwidth'] ) {
        if( $fData[0] < ( $props['cx'] + $props['cwidth'] ))  // out of range to the right, reduce width
          $props['cwidth']  -= ( $props['cx'] + $props['cwidth'] - $fData[0] );
        $ratio[$x]['destW']  = $props['cwidth'];
        $ratio[$x]['srcW']   = $props['cwidth'];
        self::log( __METHOD__, basename( $file ).", cw orgW={$fData[0]}, orgCw={$orgCw}, cx={$props['cx']}, srcX={$ratio[$x]['srcX']}, srcW={$ratio[$x]['srcW']}", LOG_DEBUG );
      }
      $ratio[$x]['destH'] = $ratio[$x]['srcH'] = ( $fData[1] - $props['cy'] );
      $ratio[$x]['srcY']     = $props['cy'];
      if( $fData[1] != $ratio[$x]['srcH'] )
        self::log( __METHOD__, basename( $file ).", cy orgH={$fData[1]}, orgCh={$orgCh}, cy={$props['cy']}, srcY={$ratio[$x]['srcY']}, srcH={$ratio[$x]['srcH']}", LOG_DEBUG );
      if( $fData[1] > $props['cheight'] ) {
        if( $fData[1] < ( $props['cy'] + $props['cheight'] )) // out of range to the bottom, reduce height
          $props['cheight'] -= ( $props['cy'] + $props['cheight'] - $fData[1] );
        $ratio[$x]['destH']  = $props['cheight'];
        $ratio[$x]['srcH']   = $props['cheight'];
        self::log( __METHOD__, basename( $file ).", ch orgH={$fData[1]}, orgCh={$orgCh}, cy={$props['cy']}, srcY={$ratio[$x]['srcY']}, srcH={$ratio[$x]['srcH']}", LOG_DEBUG );
      }
      $ratio[$x]['ratio']    = ( $ratio[$x]['destW'] / $ratio[$x]['destH'] );
      $txt     .= ", xy={$ratio[$x]['srcX']}/{$ratio[$x]['srcY']}";
      $txt     .= sprintf( $ltxt, ' dest', $ratio[$x]['destW'], $ratio[$x]['destH'], number_format( $ratio[$x]['ratio'], 4 )).', doResize:'.$ratio[$x]['doResize'];
      if( isset( $props['maxwidth'] ) || isset( $props['maxheight'] ) || isset( $props['width'] ) || isset( $props['height'] )) {
        $x     += 1;                                          // prep. next action ??
        $ratio[$x]           = array( 'doResize' => FALSE,
                                      'destW'    => $ratio[0]['destW'],
                                      'destH'    => $ratio[0]['destH'],
                                      'ratio'    => ( $ratio[0]['srcW'] / $ratio[0]['srcH'] ), // input ratio!!
                                      'srcX'     => '0',
                                      'srcY'     => '0',
                                      'srcW'     => $ratio[0]['destW'],
                                      'srcH'     => $ratio[0]['destH'],
                                    );
      }
    }
    switch( TRUE ) {
      case ( isset( $props['maxwidth'] ) && isset( $props['maxheight'] )):
        $op       = '4';                                  // proportional resize to fit in box
        $txt     .= sprintf( $ltxt, PHP_EOL."(#{$x}), op{$op} src", $ratio[$x]['srcW'], $ratio[$x]['srcH'], number_format( $ratio[$x]['ratio'], 4 ));
        if(( $ratio[0]['destW'] != $props['maxwidth'] ) || ( $ratio[0]['destH'] != $props['maxheight'] )) {
          $ratio[$x]['doResize'] = TRUE;
          self::calculateInBox( $props['maxwidth'], $props['maxheight'], $ratio[$x], $cnt );
        }
        $txt     .= sprintf( $ltxt, "(#{$x}), op{$op} dest", $ratio[$x]['destW'], $ratio[$x]['destH'], number_format( $ratio[$x]['ratio'], 4 )).', doResize:'.$ratio[$x]['doResize'];
        break;
      case ( isset( $props['width'] ) && isset( $props['height'] )):
        $op       = '7';                                  // un-proportional resize!!
        $ratio[$x]['destW']    = $props['width'];
        $ratio[$x]['destH']    = $props['height'];
        if(( $ratio[$x]['destW'] != $ratio[$x]['srcW'] ) || ( $ratio[$x]['destH'] != $ratio[$x]['srcH'] )) {
          $ratio[$x]['doResize'] = TRUE;
          $ratio[$x]['ratio']  = $ratio[$x]['destW'] / $ratio[$x]['destH'];
        }
        break;
      case ( isset( $props['maxwidth'] )):                // we only got a single maxwidth or width -> proportional resize
        $op      .= ( empty( $op )) ? '5' : '';           // empty $props['maxheight']
        $props['width']        = $props['maxwidth'];      // fall through
      case ( isset( $props['width'] )):                   // empty $props['height']
        $op      .= ( empty( $op )) ? '8' : '';
        $txt     .= sprintf( $ltxt, PHP_EOL."(#{$x}), op{$op} src", $ratio[$x]['srcW'], $ratio[$x]['srcH'], number_format( $ratio[$x]['ratio'], 4 ));
        $ratio[$x]['destW']    = $props['width'];
        if( $ratio[$x]['destW'] != $ratio[$x]['srcW'] ) { // recount height due to width
          $ratio[$x]['doResize'] = TRUE;
          $ratio[$x]['destH']  = (int) floor( $props['width'] / $ratio[$x]['ratio'] );
        }
        $txt     .= sprintf( $ltxt, "(#{$x}), op{$op} dest", $ratio[$x]['destW'], $ratio[$x]['destH'], number_format( $ratio[$x]['ratio'], 4 )).', doResize:'.$ratio[$x]['doResize'];
        break;
      case ( isset( $props['maxheight'] )):               // we only got a single maxheight or height -> proportional resize
        $op      .= ( empty( $op )) ? '6' : '';           // empty $props['maxwidth']
        $props['height']       = $props['maxheight'];     // fall through
      case ( isset( $props['height'] )):                  // empty $props['width']
        $op      .= ( empty( $op )) ? '9' : '';
        $txt     .= sprintf( $ltxt, PHP_EOL."(#{$x}), op{$op} src", $ratio[$x]['srcW'], $ratio[$x]['srcH'], number_format( $ratio[$x]['ratio'], 4 ));
        $ratio[$x]['destH']    = $props['height'];
        if( $ratio[$x]['destH'] != $ratio[$x]['srcH'] ) { // recount width due to height
          $ratio[$x]['doResize'] = TRUE;
          $ratio[$x]['destW']  = (int) floor( $props['height'] * $ratio[$x]['ratio'] );
        }
        $txt     .= sprintf( $ltxt, "(#{$x}), op{$op} dest", $ratio[$x]['destW'], $ratio[$x]['destH'], number_format( $ratio[$x]['ratio'], 4 )).', doResize:'.$ratio[$x]['doResize'];
        break;
      default:
        break;
    }
    return self::theEnd( __METHOD__, $ratio, null, basename( $file ).$txt, LOG_INFO );
  }
/** *************************************************************************
 * calculateBox
 *
 * (proportionally) re-calculate image width/height
 * to fit in a box with dimensions $boxwidth x $boxheight
 *
 * @param int   $boxwidth
 * @param int   $boxheight
 * @param array $ratio
 * @param int   $cnt
 * @return void
 * @access private
 * @static
 */
  private static function calculateInBox( $boxwidth, $boxheight, array & $ratio, & $cnt ) {
    do {
      $cnt             += 1;
      if( $boxheight < $ratio['destH'] ) { // adapt width due to height
        $ratio['destW'] = $boxheight * $ratio['ratio'];
        $ratio['destH'] = $boxheight;
      }
      else {                               // adapt height due to width
        $ratio['destW'] = $boxwidth;
        $ratio['destH'] = $boxwidth / $ratio['ratio'];
      }
    } while(( $boxwidth < $ratio['destW'] ) || ( $boxheight < $ratio['destH'] ));
    $ratio['destW']     = (int) floor((float) $ratio['destW'] );
    $ratio['destH']     = (int) floor((float) $ratio['destH'] );
  }
/** *************************************************************************
 * checkFile
 *
 * checking file existense and readability
 *
 * @param string $file
 * @uses imageHandler::theEnd()
 * @return bool
 * @access private
 * @static
 */
  private static function checkFile( $file ) {
    if( ! is_file( $file ))
      return self::theEnd( __METHOD__, FALSE, null, "'$file' is no file", LOG_ERR );
    elseif( ! is_readable( $file ))
      return self::theEnd( __METHOD__, FALSE, null, "'$file' is NOT readable!", LOG_ERR );
    return TRUE;
  }
/** *************************************************************************
 * checkInput
 *
 * control input directives
 * copy remote resource to (local) temp. file
 * check local rerource, destination, resizing settings
 *
 * @param string $file
 * @param array  $props
 * @uses imageHandler::copyRemote2temp()
 * @uses imageHandler::theEnd()
 * @uses imageHandler::$imageLib
 * @uses imageHandler::checkFile()
 * @uses imageHandler::$defaultOperation
 * @uses imageHandler::$sizeArgs
 * @uses imageHandler::log()
 * @uses imageHandler::trimOutput()
 * @return bool TRUE on success, FALSE on error
 * @access private
 * @static
 */
  private static function checkInput( & $file, array & $props ) {
    $time_start = microtime( TRUE );
    $inputRsc   = $props['source'] = $file;
    if(( FALSE !== ( $urlParts = parse_url( $file ))) && ! empty( $urlParts['scheme'] )) { // check remote resource
      if( FALSE === ( $props['tempfile'] = self::copyRemote2temp( $file, 'rmt' )))
        return self::theEnd( __METHOD__, FALSE, null, null, LOG_ERR );
      $file     = $props['tempfile'];
    }
    else { // local file resource
      if( ! empty( self::$imageLib ))
        $file   = self::$imageLib.$file;
      if( FALSE === self::checkFile( $file ))
        return self::theEnd( __METHOD__, FALSE, null, null, LOG_ERR );
    }
    $props      = array_change_key_case( $props );        // check operation
    if( ! isset( $props['operation'] ) || ! is_numeric( $props['operation'] ) || ( 1 > $props['operation'] ) || ( 3 < $props['operation'] ))
      $props['operation']  = self::$defaultOperation;
    if( self::$outputpng )
      self::fixExtension( $props['name'], IMAGETYPE_PNG );
    if( 3 == $props['operation'] ) {                      // if 'save to disk' - operation, check destination
      if( ! isset( $props['name'] ) || empty( $props['name'] ))
        return self::theEnd( __METHOD__, FALSE, $time_start, '('.basename( $file ).") Missing file copy destination", LOG_ERR );
      elseif( ! is_writeable( dirname( $props['name'] )))
        return self::theEnd( __METHOD__, FALSE, $time_start, "Can't write to directory '".dirname( $props['name'] )."'", LOG_ERR );
      elseif( is_file( $props['name'] ) && ! is_writeable( $props['name'] ))
        return self::theEnd( __METHOD__, FALSE, $time_start, "File '{$props['name']}' exists and can't be replaced...", LOG_ERR );
      elseif( is_file( $props['name'] ))
        self::log(           __METHOD__, "File '{$props['name']}' exists and will be replaced...", LOG_WARNING );
    }
    $chg        = array();
    foreach( self::$sizeArgs as $sizeArg ) {              // check 0 (zero) and percentage settings
      if( isset( $props[$sizeArg] )) {
        $props[$sizeArg]   = trim( $props[$sizeArg] );
        foreach( array( '0%', '0' ) as $zero ) {
          if( $zero != $props[$sizeArg] )
            continue;
          if(( 'cx' == $sizeArg ) || ( 'cy' == $sizeArg )) // accepts both '0' and '0%' but no other
            $props[$sizeArg] = $chg[$sizeArg] = 0;
          else {
            $chg[$sizeArg] = "unset ({$props[$sizeArg]})";
            unset( $props[$sizeArg] );
          }
          continue 2;
        } // end foreach( array( '0%', '0' ) as $zero )
        if(( '%' == substr( $props[$sizeArg], -1 )) && is_numeric( substr( $props[$sizeArg], 0, -1 )))
          continue;
        if((( 0 != $props[$sizeArg] ) && empty( $props[$sizeArg] )) || ! is_numeric( $props[$sizeArg] )) {
          $chg[$sizeArg]   = "unset ({$props[$sizeArg]})";
          unset( $props[$sizeArg] );
        }
      }
    } // end foreach( self::$sizeArgs as $sizeArg )
    if( isset( $props['cwidth'] ) || isset( $props['cheight'] )) { // check to large crop percentage setting
      if( isset( $props['cx'] )      && ( '%' == substr( $props['cx'],      -1 )) && ( 100 < substr( $props['cx'],      0, -1 )))
        $props['cx']       = $chg['cx'] = 0;
      if( isset( $props['cy'] )      && ( '%' == substr( $props['cy'],      -1 )) && ( 100 < substr( $props['cy'],      0, -1 )))
        $props['cy']       = $chg['cy'] = 0;
      if( isset( $props['cwidth'] )  && ( '%' == substr( $props['cwidth'],  -1 )) && ( 100 < substr( $props['cwidth'],  0, -1 )))
        $props['cwidth']   = $chg['cwidth']  = '100%';
      if( isset( $props['cheight'] ) && ( '%' == substr( $props['cheight'], -1 )) && ( 100 < substr( $props['cheight'], 0, -1 )))
        $props['cheight']  = $chg['cheight'] = '100%';
    }
    else {
      if( isset( $props['cx'] )) {
        $chg['cx']         = 'unset';
        unset( $props['cx'] );
      }
      if( isset( $props['cy'] )) {
        $chg['cy']         = 'unset';
        unset( $props['cy'] );
      }
    }
    if( isset( $props['maxwidth'] ) || isset( $props['maxheight'] )) {
      if( array_key_exists( 'width', $props )) {
        $chg['width'] = 'unset(maxw_exist)';
        unset( $props['width'] );
      }
      if( array_key_exists( 'height', $props )) {
        $chg['height'] = 'unset(maxh_exist)';
        unset( $props['height'] );
      }
    }
    $msg        = "Input: file='$inputRsc'";
    if( isset( $tempfile ))
      $msg     .= " ({$tempfile})";
    $msg       .= ', '.var_export( $props, TRUE );
    if( ! empty( $chg ))
      $msg     .= PHP_EOL.', altered:'.self::trimOutput( $chg );
    return self::theEnd(     __METHOD__, TRUE, $time_start, $msg, LOG_DEBUG );
  }
/** *************************************************************************
 * copyRemote2temp
 *
 * copy remote image resource to (local) tempfile
 *
 * @param string $remote
 * @param string $fcnUnique
 * @uses imageHandler::$cache
 * @uses imageHandler::$filenamePrefix
 * @uses imageHandler::log()
 * @uses imageHandler::theEnd()
 * @return mixed, string tempfile on success, bool FALSE on error
 * @access private
 * @static
 */
  private static function copyRemote2temp( $remote, $fcnUnique ) {
    $time_start  = microtime( TRUE );
    static $txt1 = "'%s' copied to (local) '%s'";
    static $txt2 = "(%s) Creating temp file '%s'";
    if( FALSE === ( $tempfile = tempnam( self::$cache, self::$filenamePrefix.$fcnUnique )))
      return self::theEnd( __METHOD__, FALSE, $time_start, "Can't create tempfile for '".basename( $remote )."'", LOG_ERR );
    self::log( __METHOD__ , sprintf( $txt2, basename( $remote ), $tempfile ), LOG_DEBUG );
    if( FALSE !== ( strpos( $remote, ' ' )))
      $remote    = str_replace( ' ', '%20', $remote );
    if( FALSE === @copy( $remote, $tempfile )) {
      unlink( $tempfile );
      return self::theEnd( __METHOD__, FALSE, $time_start, "Can't copy (remote) '{$remote}' to (local) '{$tempfile}'", LOG_ERR );
    }
    return self::theEnd( __METHOD__, $tempfile, $time_start, sprintf( $txt1, $remote, $tempfile ), LOG_INFO );
  }
/** *************************************************************************
 * doTheJobb
 *
 * output management
 *
 * @param int    $imageType
 * @param string $file
 * @param object $imageRsrc
 * @param array  $fData
 * @param array  $props
 * @uses imageHandler::$outputpng
 * @uses imageHandler::returnImage2()
 * @uses imageHandler::theEnd()
 * @return bool
 * @access private
 * @static
 */
  private static function doTheJobb( $imageType, $file, $imageRsrc, array $fData, array $props ) {
    $time_start    = microtime( TRUE );
    if( self::$outputpng || (( IMAGETYPE_JPEG != $imageType ) && ( IMAGETYPE_GIF != $imageType ))) {
      static $pngContentType;
      if( empty( $pngContentType ))
        $pngContentType    = @image_type_to_mime_type( IMAGETYPE_PNG );
      static $ltxt = "'%s', contenttype set to '%s'";
      if( $pngContentType !== $fData['mime'] ) {
//        self::fixExtension( $props['name'], IMAGETYPE_PNG );
        $fData['mime']     = $pngContentType;
        self::log( __METHOD__ , sprintf( $ltxt, basename( $file ), $fData['mime'] ), LOG_DEBUG );
      }
      $imageType   = IMAGETYPE_PNG;
    }
    if( 3 != $props['operation']) {
      $result      = self::returnImage2( $imageType, $imageRsrc, $file, $fData, $props );
      $logPrio     = ( $result ) ? LOG_INFO : LOG_ERR;
      return self::theEnd( __METHOD__, $result, $time_start, basename( $file ), $logPrio );
    }
    switch( $imageType ) {
      case IMAGETYPE_JPEG :
        $result    = @imagejpeg( $imageRsrc, $props['name'] );
        break;
      case IMAGETYPE_GIF :
        $result    = @imagegif(  $imageRsrc, $props['name'] );
        break;
      default:
        $result    = @imagepng(  $imageRsrc, $props['name'] );
        break;
    }
    @imagedestroy( $imageRsrc );
    if( $result )
      return self::theEnd( __METHOD__, TRUE, $time_start, basename( $file ));
    else
      return self::theEnd( __METHOD__, FALSE, $time_start, "error when saving '".basename( $file )."' as '{$props['name']}'", LOG_ERR );
  }
/** *************************************************************************
 * fixExtension
 *
 * set or alter file extension
 *
 * @param string $filename
 * @param int    $imageType
 * @uses imageHandler::$iTypes
 * @return void
 * @access private
 * @static
 * @toto error texts??
 */
  private static function fixExtension( & $filename, $imageType ) {
    if( empty( $filename ))
      return;
    if( ! array_key_exists( $imageType, self::$iTypes ))
      return;
    $ltxt = "'{$filename}' renamed to ";
    $ext         = self::$iTypes[$imageType]['ext'];
    if( FALSE !== ( $pos = strrpos( $filename, '.' )))
      $filename  = substr( $filename, 0, $pos ) . '.' . $ext;
    else
      $filename .= '.' . $ext;
    return self::theEnd( __METHOD__, TRUE, null, "$ltxt '{$filename}'", LOG_DEBUG );
  }
/** *************************************************************************
 * fixMixedData
 *
 * imageHandler prepare using both image ($fData) and input ($props) data
 * if missing, create an image output name,
 * check disk size
 * make sure image output name is a basename
 * opt. extension
 * convert resizing percent to pixels
 * checking cropping directives
 *
 * @param string $file
 * @param array  $fData
 * @param array  $props
 * @uses imageHandler::$filenamePrefix
 * @uses imageHandler::log()
 * @uses imageHandler::theEnd()
 * @uses imageHandler::$outputpng )
 * @uses imageHandler::fixExtension()
 * @uses imageHandler::$sizeArgs
 * @uses imageHandler::trimOutput()
 * @return void
 * @access private
 * @static
 */
  private static function fixMixedData( $file, $fData, & $props ) {
    $fileBaseName = basename( $file );
    if( ! isset( $props['name'] ) || empty( $props['name'] )) { // create a missing (output) filename (op2+3 only)
      $props['name']      = imageHandler::$filenamePrefix.bin2hex( openssl_random_pseudo_bytes( 4, $cStrong )); // prefix + 8 random chars
      self::log( __METHOD__ , "'".$fileBaseName."', output image name set to {$props['name']}", LOG_INFO );
    }
    elseif( 3 == $props['operation'] ) {
      $dirname            = dirname( $props['name'] );
      if(( FALSE !== ( $totds = disk_total_space( $dirname ))) &&
         ( FALSE !== ( $usdds = disk_free_space( $dirname )))) {
        if( $totds < ( $usdds + $fData['fsize'] ))
          return self::theEnd( __METHOD__, TRUE, null, "({$fileBaseName}) no space on disk for dest. image {$props['name']}", LOG_DEBUG );
      }
      if( 0.75 < ( $usdds / $totds ))
        self::log( __METHOD__ , 'Less than 25% memory left on '.dirname( $props['name'] ), LOG_WARNING );
    }
    else
      $props['name']      = basename( $props['name'] );
    $chg                  = array();
    foreach( self::$sizeArgs as $sizeArg ) {  // convert percent to size in pixels
      if( ! isset( $props[$sizeArg] ) || ( '%' != substr( $props[$sizeArg], -1 )))
        continue;
      if(     'cx' == $sizeArg )
        $base             = $fData[0];
      elseif( 'cy' == $sizeArg )
        $base             = $fData[1];
      else
        $base             = ( 'width' == substr( $sizeArg, -5 )) ? $fData[0] : $fData[1];
      if( '100%' == $props[$sizeArg] )
        $props[$sizeArg]  = $chg[$sizeArg] = $base;
      else
        $props[$sizeArg]  = $chg[$sizeArg] = (int) round( substr( $props[$sizeArg], 0, -1 ) * $base / 100 );
    } // end foreach( self::$sizeArgs as $sizeArg )
    if( isset( $props['cwidth'] )  && ( $props['cwidth']  > $fData[0] )) {
      $chg['cwidth']      = 'unset (to lrg)';
      unset( $props['cwidth'] );
    }
    if( isset( $props['cheight'] ) && ( $props['cheight'] > $fData[1] )) {
      $chg['cheight']     = 'unset (to lrg)';
      unset( $props['cheight'] );
    }
    if( isset( $props['cwidth'] ) || isset( $props['cheight'] )) {
      if( ! isset( $props['cwidth'] ))
        $props['cwidth']  = $chg['cwidth']  = $fData[0];
      if( ! isset( $props['cheight'] ))
        $props['cheight'] = $chg['cheight'] = $fData[1];
      if( ! isset( $props['cx'] ))
        $props['cx'] = $chg['cx'] = ( $fData[0] > $props['cwidth'] )  ? (int) round(( $fData[0] - $props['cwidth'] ) / 2 )  : 0;
      elseif( $props['cx'] >= $fData[0] ) {
        $props['cx'] = $chg['cx'] = 0;
        self::log( __METHOD__, "({$fileBaseName}) cx reset to 0" , LOG_WARNING );
      }
      if( ! isset( $props['cy'] ))
        $props['cy'] = $chg['cy'] = ( $fData[1] > $props['cheight'] ) ? (int) round(( $fData[1] - $props['cheight'] ) / 2 ) : 0;
      elseif( $props['cy'] >= $fData[1] ) {
        $props['cy'] = $chg['cy'] = 0;
        self::log( __METHOD__, "({$fileBaseName}) cy reset to 0" , LOG_WARNING );
      }
    } // end if( isset( $props['cwidth'] )...
    return self::theEnd( __METHOD__, TRUE, null, $fileBaseName.', altered:'.self::trimOutput( $chg ), LOG_DEBUG );
  }
/** *************************************************************************
 * getImageMetadata
 *
 * Get PHP getimagesize (fcn) metadata,
 * add filexize,
 * if missing, create an image output name,
 * make sure image output name is a basename
 *
 * @param string $file
 * @param array  $props
 * @uses imageHandler::theEnd()
 * @uses imageHandler::$iTypes
 * @uses imageHandler::log()
 * @return mixed, array on success, FALSE on error
 * @access private
 * @static
 */
  private static function getImageMetadata( $file, array & $props ) {
    $time_start  = microtime( TRUE );
    if( FALSE === ( $fData = @getimagesize( $file, $imageInfo )))
      return self::theEnd( __METHOD__, FALSE, $time_start, "Unsupported picture type, '".basename( $file )."'", LOG_ERR );
    if( ! isset( self::$iTypes[$fData[2]] ))
      return self::theEnd( __METHOD__, FALSE, $time_start, basename( $file ).', type='.$fData[2].',  !!!', LOG_ERR );
    $fData['fsize']        = @filesize( $file );
    return self::theEnd( __METHOD__, $fData, $time_start, "input '".basename( $file )."' has metaData=".var_export( $fData, TRUE ), LOG_INFO );
  }
/** *************************************************************************
 * imagecreatefrombmp
 *
 * create gd image from BMP file
 *
 * @param string $file
 * @uses imageHandler::$cache
 * @uses imageHandler::$filenamePrefix
 * @uses imageHandler::bmp2gd()
 * @uses imageHandler::theEnd()
 * @return mixed image object on success, FALSE on error
 * @access private
 * @static
 */
  private static function imagecreatefrombmp( $file ) {
    $time_start   = microtime( TRUE );
    $fileBaseName = basename( $file );
    if( FALSE === ( $tempfile = tempnam( self::$cache, self::$filenamePrefix.'bmpGD' )))
      return self::theEnd( __METHOD__, FALSE, $time_start, "Can't create tempfile for '".basename( $file )."'", LOG_ERR );
    if( FALSE === self::bmp2gd( $file, $tempfile ))
      return self::theEnd( __METHOD__, FALSE, $time_start, "Can't create (gd) image from '{$fileBaseName}'", LOG_ERR );
    $img          = @imagecreatefromgd( $tempfile );
    unlink( $tempfile );
    if( FALSE === $img )
      return self::theEnd( __METHOD__, FALSE, $time_start, 'imagecreatefromgd (bmp) error ('.$fileBaseName.')', LOG_ERR );
    return self::theEnd( __METHOD__, $img, $time_start, $fileBaseName, LOG_INFO );
  }
/** *************************************************************************
 * imageHandlerExit
 *
 * exit imageHandler
 *
 * @param bool   $result
 * @param string $file
 * @param array  $fData
 * @param array  $props
 * @param float  $time_start
 * @uses imageHandler::$operationModes
 * @uses imageHandler::theEnd()
 * @uses imageHandler::$logger
 * @return bool
 * @access private
 * @static
 */
  private static function imageHandlerExit( $result, & $file, & $fData, array & $props, $time_start ) {
    if( isset( $props['tempfile'] ) && ( FALSE === unlink( $props['tempfile'] )))
      self::log( __METHOD__ , "'".basename( $file )."', can't remove {$props['tempfile']}", LOG_WARNING );
    if( empty( $fData ))
      $fData    = array();
    $fInfo      = ( $result ) ? 'Successfull ' : 'Unsuccessfull ';
    $fInfo     .= self::$operationModes[$props['operation']];
    $fInfo     .= ", '{$props['source']}'";               // input name, work name, output name
    if( $file == $props['source'] ) {
      if( basename( $file ) != basename( $props['name'] ))
        $fInfo .= ' ('.basename( $props['name'] ).')';
    }
    else {
      $fInfo   .= " ({$file})";
      if( basename( $props['source'] ) != basename( $props['name'] ))
        $fInfo .= ", '".basename( $props['name'] )."'";
    }
    if( isset( $fData['fsize'] ))
      $fInfo   .= ", size=".number_format((float) $fData['fsize'], 0, '', ' ' );
    unset( $file, $fData, $props );
    $logPrio    = ( $result ) ? LOG_NOTICE : LOG_ERR;
    self::theEnd( __METHOD__, null, $time_start, $fInfo, $logPrio );
    if( ! empty( self::$logger ))
      self::$logger->flush();
    return $result;
  }
/** *************************************************************************
 * log
 *
 * log message
 *
 * @param string $fcn
 * @param string $message
 * @param int    $prio
 * @uses imageHandler::$logprio
 * @uses imageHandler::$logger
 * @return void
 * @access private
 * @static
 */
  private static function log( $fcn, $message, $prio=null ) {
    if( is_null( $prio ))
      $prio = self::$logprio;
    if( ! empty( self::$logger ) && ! empty( $message ) && ( self::$logprio >= $prio ))
      self::$logger->log( strtoupper( $fcn ).' '.$message, $prio );
  }
/** *************************************************************************
 * originName
 *
 * return origin and, opt, set name
 *
 * @param string $file
 * @param array  $props
 * @return string
 * @access private
 * @static
 */
  private static function originName( $file, $props ) {
    return ( basename( $props['source'] ) == basename( $file )) ? "'".basename( $file )."'" : "'".basename( $props['source'] )."' (".basename( $file ).')';
  }
/** *************************************************************************
 * reShape
 *
 * create (resized) image (gif/jpg/png) object
 *
 * @param int    $imageType
 * @param string $file
 * @param object $image
 * @param array  $ratio
 * @param int    $x
 * @uses imageHandler::log()
 * @uses imageHandler::theEnd()
 * @uses imageHandler::fastimagecopyresampled()
 * @return mixed, image object on success, FALSE on error
 * @access private
 * @static
 * @todo  imagecolortransparent  ...has a very strange behaviour with GD version > 2.
 *        It returns count of colors instead of -1 (as noted) if cannot find transparent color. Be carefull!
 */
  private static function reShape( $imageType, $file, $image, array & $ratio, $x ) {
    $time_start    = microtime( TRUE );
    $fileBaseName  = basename( $file );
    static $errTxt = '#%d, %s, unsuccessfull %s (imageType=%d)';
    static $ltxt   = '%s%d/%d';
    static $endTxt = "#%d, '%s', (imageType=%s), %s";
    if( FALSE === ( $newImage = @imagecreatetruecolor( $ratio[$x]['destW'], $ratio[$x]['destH'] ))) { // create a new truecolour image
      $txt         = sprintf( $errTxt, $x, $fileBaseName, 'imagecreatetruecolor', $imageType );
      $txt        .= sprintf( $ltxt, ' dest: wh=', $ratio[$x]['destW'], $ratio[$x]['destH'] ) ;
      return self::theEnd(   __METHOD__, FALSE, $time_start, $txt, LOG_ERR );
    }
    if( IMAGETYPE_GIF == $imageType ) {
      if( ! array_key_exists( 'trnspClr', $ratio[0] )) {             // Check for a transparent colour index
        $trnspIx1  = imagecolortransparent( $image );                // and get the image transparent colours, if exists
        $ratio[0]['trnspClr'] = ( $trnspIx1 >= 0 ) ? imagecolorsforindex( $image, $trnspIx1 ) : null;
      }
      if( empty( $ratio[0]['trnspClr'] )) {                          // a transparent colour is not set
        if( FALSE === imagefilledrectangle( $newImage, 0, 0, $ratio[$x]['destW'], $ratio[$x]['destH'], imagecolorallocate( $newImage, 255, 255, 255 ))) {
          @imagedestroy( $newImage );
          return self::theEnd(   __METHOD__, FALSE, $time_start, sprintf( $errTxt, $x, $fileBaseName, 'imagefilledrectangle', $imageType ), LOG_ERR );
        }
      }
      else {                                                         // a transparent colour is set
        $errCmd    = null;                                           // allocate in newImage
        if( FALSE === ( $trnspIx2 = imagecolorallocatealpha( $newImage, $ratio[0]['trnspClr']['red'], $ratio[0]['trnspClr']['green'], $ratio[0]['trnspClr']['blue'], $ratio[0]['trnspClr']['alpha'] )))
          $errCmd  = 'imagecolorallocatealpha';
        elseif( FALSE === imagefill( $newImage, 0, 0, $trnspIx2 ))   // Completely fill the background of the new image with the allocated colour.
          $errCmd  = 'imagecolorallocatealpha';
        if( ! empty( $errCmd )) {
          @imagedestroy( $newImage );
          return self::theEnd( __METHOD__, FALSE, $time_start, sprintf( $errTxt, $x, $fileBaseName, $errCmd, $imageType ), LOG_ERR );
        }
        $trnspIx3  = imagecolortransparent( $newImage, $trnspIx2 );  // Set the background colour for new image to transparent
        unset( $trnspIx1, $trnspIx2, $trnspIx3 );
      }
    } // end if( IMAGETYPE_GIF == $imageType )
    if( IMAGETYPE_PNG == $imageType ) {
      $errCmd      = null;
      if(     FALSE === imagealphablending( $newImage, FALSE ))      // Set newImage blending mode off
        $errCmd    = 'imagealphablending';
      elseif( FALSE === imagesavealpha( $newImage, TRUE ))           // Set the flag to save full newImage alpha channel information
        $errCmd    = 'imagesavealpha (new)';
      elseif( FALSE === ( $trnspIx2 = imagecolorallocatealpha( $newImage, 255, 255, 255, 127 ))) // get newImage transparent colour
        $errCmd    = 'imagecolorallocatealpha';                      // Draw a filled rectangle in newImage using the transparent colour
      elseif( FALSE === imagefilledrectangle( $newImage, 0, 0, $ratio[$x]['destW'], $ratio[$x]['destH'], $trnspIx2 ))
        $errCmd    = 'imagefilledrectangle';
      if( ! empty( $errCmd )) {
        @imagedestroy( $newImage );
        return self::theEnd( __METHOD__, FALSE, $time_start, sprintf( $errTxt, $x, $fileBaseName, $errCmd, $imageType ), LOG_ERR );
      }
//      self::log( __METHOD__ , "#{$x}, $fileBaseName, png, imagecolorallocatealpha=$trnspIx2", LOG_DEBUG );
    } // end if( IMAGETYPE_PNG == $imageType )
    if( FALSE === @imagecopyresampled( $newImage,       // resize the image
                                       $image,
                                       0,
                                       0,
                                       $ratio[$x]['srcX'],
                                       $ratio[$x]['srcY'],
                                       $ratio[$x]['destW'],
                                       $ratio[$x]['destH'],
                                       $ratio[$x]['srcW'],
                                       $ratio[$x]['srcH'])) {
      @imagedestroy( $newImage );
      return self::theEnd(   __METHOD__, FALSE, $time_start, sprintf( $errTxt, $x, $fileBaseName, 'imagecopyresampled', $imageType ), LOG_ERR );
    }
    @imagedestroy( $image );
    $crop          = ( ! empty( $ratio[$x]['srcX'] ) || ! empty( $ratio[$x]['srcY'] ));
    $txt           = sprintf( $ltxt, "src: wh=", $ratio[$x]['srcW'], $ratio[$x]['srcH'] );
    if( $crop )
      $txt        .= sprintf( $ltxt, ', xy=', $ratio[$x]['srcX'], $ratio[$x]['srcY'] );
    else
      $txt        .= ', ratio='.number_format(( $ratio[$x]['srcW'] / $ratio[$x]['srcH'] ), 4 );
    $txt          .= sprintf( $ltxt, ' dest: wh=', imagesx( $newImage ), imagesy( $newImage )) ;
    if( ! $crop )
      $txt        .= ', ratio='.number_format( $ratio[$x]['ratio'], 4 );
    return self::theEnd(     __METHOD__, $newImage, $time_start, PHP_EOL.sprintf( $endTxt, $x, $fileBaseName, $imageType, $txt ), LOG_INFO );
  }
/** *************************************************************************
 * returnAsIs
 *
 * return image to browser 'as is'
 *
 * @param string $file
 * @param array  $fData
 * @param array  $props
 * @uses imageHandler::returnImage()
 * @uses imageHandler::saveFile()
 * @uses imageHandler::theEnd()
 * @return bool, TRUE on success, FALSE on error
 * @access private
 * @static
 */
  private static function returnAsIs( $file, array $fData, array $props ) {
    $time_start = microtime( TRUE );
    if( 3 != $props['operation'])
      $result = self::returnImage( $file, $fData, $props );
    else
      $result = self::saveFile( $file, $props['name'] );
    $logPrio  = ( $result ) ? LOG_NOTICE : LOG_ERR;
    return self::theEnd( __METHOD__, $result, $time_start, basename( $file ), $logPrio );
  }
/** *************************************************************************
 * returnImage
 *
 * return image to browser without reshape
 *
 * @param string $file
 * @param array  $fData
 * @param array  $props
 * @uses imageHandler::$obFlags
 * @uses imageHandler::$headers
 * @uses imageHandler::$operationModes
 * @uses imageHandler::theEnd()
 * @return bool, TRUE on success, FALSE on error
 * @access private
 * @static
 */
  private static function returnImage( $file, array $fData, array $props ) {
    $time_start = microtime( TRUE );
    ob_start( null, 0, self::$obFlags );
    header( sprintf( self::$headers['ct'], $fData['mime'] ));
    header( sprintf( ( 1 == $props['operation'] ) ? self::$headers['cd1'] : self::$headers['cd2'],
                     $props['name'] ));
    header( sprintf( self::$headers['cl'], $fData['fsize'] ));
    @readfile( $file );
    @ob_flush();
    flush();
    $fInfo      = self::$operationModes[$props['operation']].", '".basename( $file )."' as '{$props['name']}'";
    return self::theEnd( __METHOD__, TRUE, $time_start, "{$fInfo} contenttype='{$fData['mime']}', size:".number_format((float) $fData['fsize'], 0, '', ' ' ), LOG_INFO );
  }
/** *************************************************************************
 * returnImage2
 *
 * return image (to browser) as a png after reshape
 *
 * @param int    $imageType
 * @param object $image
 * @param string $file
 * @param array  $fData
 * @param array  $props
 * @uses imageHandler::$obFlags
 * @uses imageHandler::theEnd()
 * @uses imageHandler::$headers
 * @uses imageHandler::log()
 * @return bool, TRUE on success, FALSE on error
 * @access private
 * @static
 */
  private static function returnImage2( $imageType, $image, $file, array & $fData, array & $props ) {
    $time_start = microtime( TRUE );
    $fn         = basename( $file );
    static $errTxt = "problem in '%s', image from '%s'";
    ob_start( null, 0, self::$obFlags );
    $errCmd     = null;
    if(( IMAGETYPE_JPEG ==  $imageType ) && ( FALSE === @imagejpeg( $image )))
      $errCmd   = 'imagejpeg';
    elseif(( IMAGETYPE_GIF == $imageType ) && ( FALSE === @imagegif( $image )))
      $errCmd   = 'imagegif';
    elseif( FALSE === @imagepng( $image, null, 0, PNG_NO_FILTER ))
      $errCmd   = 'imagegif';
    if( ! empty( $errCmd )) {
      ob_clean();
      @imagedestroy( $image );
      return self::theEnd( __METHOD__, FALSE, $time_start, sprintf( $errTxt, 'imagepng', $fn ), LOG_ERR );
    }
    $iData      = ob_get_contents();
    ob_clean();
    @imagedestroy( $image );
    $ts         = strlen( $iData );
    if( $fData['fsize'] != $ts )
      $fData['fsize'] = $ts;
    header( sprintf( self::$headers['ct'], $fData['mime'] ));
    header( sprintf( ( 1 == $props['operation'] ) ? self::$headers['cd1'] : self::$headers['cd2'],
                     $props['name'] ));
    header( sprintf( self::$headers['cl'], $fData['fsize'] ));
    echo $iData;
    @ob_flush();
    flush();
    unset( $iData );
    return self::theEnd( __METHOD__, TRUE, null, "{$fn} ({$props['name']})" );
  }
/** *************************************************************************
 * saveFile
 *
 * save image on disk (i.e. copy)
 *
 * @param string $source
 * @param string $dest
 * @uses imageHandler::theEnd()
 * @return bool, TRUE on success, FALSE on error
 * @access private
 * @static
 */
  private static function saveFile( $source, $dest ) {
    $time_start = microtime( TRUE );
    $msg       =  "'{$source}' to '{$dest}'";
    if( FALSE === @copy( $source, $dest ))
      return self::theEnd( __METHOD__, FALSE, $time_start, "Can't copy {$msg}", LOG_ERR );
    return   self::theEnd( __METHOD__, TRUE,  $time_start, "Successfull copy of '{$msg}", LOG_INFO );
  }
/** *************************************************************************
 * theEnd
 *
 * log and return result of calling function
 *
 * @param string $fcn
 * @param bool   $result
 * @param float  $time_start
 * @param string $msg
 * @param int    $logPrio
 * @uses imageHandler::log();
 * @uses imageHandler::$logger
 * @return bool
 * @access private
 * @static
 */
  private static function theEnd( $fcn, $result, $time_start=null, $msg=null, $logPrio=null ) {
    $ltxt    = ( is_null( $time_start )) ? 'End' : 'End, time:'.number_format(( microtime( TRUE ) - $time_start ), 4 );
    if( ! empty( $msg ))
      $ltxt .= ", $msg";
    $ltxt   .= ', memory='.number_format( (float) memory_get_usage( TRUE ), 0, '', ' ' );
    if( is_null( $logPrio ))
      $logPrio = LOG_INFO;
    self::log( $fcn, $ltxt, $logPrio );
    return $result;
  }
/** *************************************************************************
 * trimOutput
 *
 * returns one-row mini output
 *
 * @param mixed $data
 * @return string
 * @access private
 * @static
 */
  private static function trimOutput( $data ) {
    return str_replace( array( PHP_EOL, ' ' ), '', var_export( $data, TRUE ));
  }
}
