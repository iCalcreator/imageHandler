
IMAGEHANDLER

Author    Kjell-Inge Gustafsson, kigkonsult <ical@kigkonsult.se>
Copyright 2015, Kjell-Inge Gustafsson kigkonsult, All rights reserved.
Version   1.4
License   non-commercial use: Creative Commons
          Attribution-NonCommercial-NoDerivatives 4.0 International License
          (http://creativecommons.org/licenses/by-nc-nd/4.0/)
          commercial use :imageHandler141license / imageHandler14Xlicense
Link      http://kigkonsult.se/imageHandler/index.php
Support   http://kigkonsult.se/contact/index.php



PREFACE

This document describes usage of the software imageHandler.

This document, as well as refered documents, are provided by kigkonsult for
informational purposes and is provided on an "as is" basis without any
warranties expressed or implied.

Information in this document is subject to change without notice and does not
represent a commitment on the part of kigkonsult.

The software described in this document is provided under a license agreement.
The software may be used only in accordance with the terms of that license
agreement. It is against the law to copy or use the software except as
specifically allowed in the license agreement.

It is the users responsibility to ensure the suitability of the software before
using it. In no circumstances will kigkonsult be responsible for the use of the
software, software's outcomes or results or any loss or damage of data or
programs as a result of using the software. The use of the software implies
acceptance of these terms.

This document makes previous versions obsolete.

Product names mentioned herein are or may be trademarks or registered
trademarks of their respective owners.



OVERVIEW

imageHandler is a PHP (gd) back end class package managing
  crop region, resize, adapt size and convert
  of bmp, jpg, gif, png, xbmp and xbm image types
with preserved proportions and (for gif/png) transparency.
For tiff images with an embedded thumbnail, the thumbnail is returned.

ImageHandler offers also availability 'on-the-fly'
  using a web (REST GET/PUT, json) service interface.

Anonymous (source and/or output) filenames (without extension)
and remote image resources are supported.

The imageHandler interface is simple :
  the source image filename or url
and
  the crop, (re-)size and/or adapt directives in pixels or
  percent of the source image size.
If the crop and size arguments are set simultaneously,
cropping is done before (re-)sizing.

The imageHandler output mode is
 download - will open a 'save file'-box in browser
 stream   - (default)
            usable in a 'src' statement in a HTML page,
            (showing a 'thumbnail')
 save     - saves (cropped/resized) image on disk
Output image type for the resized images is (default) png.
Non-resizable images types are returned 'as is'.

The class package includes
  the PHP class
  the PHP web service interface script
  an PHP 'index.php' page,
    usable for testing, evaluating and/or image reviewing,
    including a (separate) editable image crop/resize test schema.
The imageHandler class and index page supports PEAR log class or equivalents.

But, despite the most intense review of requirements and conditions in
architecture, design and test of imageHandler, the imageHandler's ability to
crop and resize an image may still depend on the image origin and the image
displayability on browser capability and/or (external) viewers.

Note, imageHandler focus is image cropping/resizing, not image engineering.



REQUIREMENTS

imageHandler is developed using PHP 5.5.20 and the PHP included gd 2.1.0.
PHP must be compiled with '--enable-exif'. Windows users must have the
mbstring extension enabled.

When operating on (very) large images, some PHP general directives like
'memory_limit' may need to be increased ('1024M' or more)
and also max_execution_time' and others.



FILES

  imageHander/
    docs/
      readme.txt               this file
      <licenses>               licenses

    images/                    images directory, to use in test (empty)
    log/                       log file directory (empty)

    test/
      imageHandler.css         test script css
      imageHandler.js          test script javascript
      index.php                test script
      cropAndResizeTest.php    imageHandler crop/resize testcases

    imageHandler.class.php     the class file
    imageHandler.php           the interface script



OPERATION MODES

imageHandler operates in three modes:

 download
   Will send header
    'Content-Disposition: attachment; filename="<name>"'
   and force the web browser to open a 'save file' box

 stream
   Will send header
     'Content-Disposition: filename="<name>"'
   opens the imageHandler output for display in browser
   usable in an HTML page like
   <img src="http://localhost/imageHandler/imageHandler.php?i=img.php&mw=200">

 save to disk
  Full path to target must be given
  If path is not writable, imageHandler is terminated (and error logged).

imageHandler is checking image type (png,gif etc) by examining image content,
NOT filename extension, i.e. supporting anonymous filenames.

The crop operation is controlled by four arguments: 'cx', 'cy', 'cwidth' and
'cheight' and accept values both in percent and pixels. Percent will be
converted to pixels, based on the source image width and height. The 'cwidth'
and 'cheight' sets the size of the cropped area. Default ('cx' and/or 'cy' are
missing) a centered crop is done. The 'cx' and 'cy' arguments sets a crop start
point, for 'cx' horizontal (left(=0)<->right) position and 'cy' vertical
upper(=0)<->lower) position. If you want to start the crop
  in the left upper corner, set 'cx'=0 and 'cy'=0,
  next to the right lower corner, set 'cx'=99% and 'cy'=99%.

The resizing operation is controlled by four (calling) arguments: 'width',
'height', 'maxwidth' and 'maxheight', accepts also values in percent and
pixels (percent converted as above). If both (and only) 'width' and 'height'
are set, image is reshaped and distorted. If only one ('width' or 'height') is
set, image is proportionally resized. The 'maxwidth' and 'maxheight' arguments
(one or both) overrides 'width' and 'height', and the resize image operation
will preserve proportions according to the 'box', limited by the 'maxwidth'
and/or 'maxheight' arguments. The resize operation will preserve transparency
for png and most gif images.

If error occurs, imageHandler is terminated (and error logged) and FALSE is
returned. If image is not resizable, image is returned 'as is'.
Remote images are cached locally (into a tempfile) before operation (and
removed afterwords).



CONFIGURATION

imageHandler is default set up to work 'out-of-the-box' with
  operation set to 2 (stream)
  all (resized) output in png
  no logging.


imageHandler::$defaultOperation = {int}
                      // One of 1=download, 2=stream, 3=save to disk
                      // default 2, details in 'OPERATION MODES', above.
                      // used if missing in input

imageHandler::$outputpng = {bool}
                      // Affects resezable(!) images only
                      // Default TRUE, all output as png
                      // FALSE, no changed output of resized jpg/gif images

imageHandler::$imageLib = {string}
                      // Default empty
                      // if not empty, it will prefix the image argument, below
                      // If used, MUST be suffixed by '/' (DIRECTORY_SEPARATOR)
                      // Usable if you don't want to show image path in public

imageHandler::$filenamePrefix = {string}
                      // prefix for created (temp/output) filenames
                      // Default 'imageHandler_'

imageHandler::$cache = {string};
                      // tempfile directory
                      // Default result of PHP function 'sys_get_temp_dir()'

                      // Log (opt, if you want to use any logging)
imageHandler::$logger  = {log object instance}
                      // PEAR log object instance or equivalent
                      // Default null
imageHandler::$logprio = {int}
                      // Default 'LOG_NOTICE'

For examples, please examine 'imageHandler.php' or 'index.php'.



CLASS METHODS

imageHandler offers two public methods (and 21 private...).


The isResizable method returns resizeability or not.

Format:

imageHandler::isResizable( image )

  image                     {string}
                      source filename (incl path*)
                      alt. source url

  return (bool) TRUE  if resizable
  return (bool) FALSE if NOT resizable

*) note, imageHandler::$imageLib, above


The Operate method manages the crop/(re-)size factory.

Format:

imageHandler::Operate( image, props )

                      Input argument
  image                     {string}
                      Required
                      source filename (incl path*)
                      alt. source url

                      Output management arguments, all optional
  props  array(
           ['operation'] => {int},
                      1=download, 2=stream (default), 3=save to disk

           ['name']      => {string},
                      Display/output (image-)name (incl path if operate = 3),
                      is generated if missing, based on the current datetime+msec.
                      If the 'imageHandler::$outputpng' directive is set
                      (default TRUE), output will ha a 'png' extension.

                      sizing arguments, se OPERATION MODES, above and remarks **/***, below
           ['cx']        => {mixed},
           ['cy']        => {mixed},
           ['cwidth']    => {mixed},
           ['cheight']   => {mixed},
           ['width']     => {mixed},
           ['height']    => {mixed},
           ['maxwidth']  => {mixed},
           ['maxheight'] => {mixed},
         )

  return FALSE on error (and reason logged)
  return image on success (operate = 1/2)
  return TRUE on success (operate = 3)

Remarks
*)   note, imageHandler::$imageLib, above
**)  values in percent (ex '50%') of source width/height
           or pixels (ex. '200') accepted
***) cx/cy only if cwidth and/or cheight exists



INTERFACE ARGUMENTS

The imageHandler interface, 'imageHandler.php', is the web (REST GET/POST,
json) service.

It is a configuration section in top of script. Remarks, above, are also
applicable here.

The interface accepts (main) json argument
     i  / image             {string}
                      json formatted string, json object properties as below.

The interface accepts the following GET/POST arguments and json (sub-)properties:

  short / full argument key names
     i  / image             {string}
                      filename or url, required *
     o  / operation         {mixed}
                      1/'download', 2/'stream' (default), 3/'save' (to disk)
     n  / name              {string}
                      image output (display/save) name (opt)
     p                      {mixed}
                      bool, TRUE jpg/gif output as png (default), FALSE not
                            TRUE also force png extension
                      int, 1=TRUE, 0=FALSE
                      string, '1'=TRUE, ''=FALSE

                      settings for the resizeable image, all opt, percent or pixels **
     cx                     {mixed}
                      left start crop position
     cy                     {mixed}
                      upper start crop position
     cw / cwidth            {mixed}
                      image crop width
     ch / cheight           {mixed}
                      image crop height
     w  / width             {mixed}
                      image output width
     h  / height            {mixed}
                      image output height
     mw / maxwidth          {mixed}
                      image max output width
     mh / maxheight         {mixed}
                      image max output height


EVALUATION AND TEST

index.php

For evaluation and test, an 'index.php' page (together with supporting css
and javascript scripts) are attached in the 'test' directory, supporting PEAR
log class or equivalents.

You can configure 'index.php' in top of script,
  image directory
  base url
  log settings
  ini_set( 'memory_limit'..
  etc...
Information for each image in the image folder are displayed with name, size,
mime contenttype etc. Exif metadata are displayed, if available, using an
show/hide button. The exif metadata key information in detail can be found in
'http://www.sno.phy.queensu.ca/~phil/exiftool/TagNames/EXIF.html'.
Also imageHandler output modes can be tested.

cropAndResizeTest.php

For each resizeable image, an editable test case schema is available, also
using a show/hide button, and used in 'index.php', testing download of cropped
and/or down-/upsized images. The (more formalized) schema may also work very
well as base for ad hoc and explorative tests. (Note, test images are NOT
included in the package.) During the tests, keep an eye on the temp (/tmp)
folder, extensive tests may fill it up. To test it in a nice way, use a
1000x1000 image (or two, one with width 1000 and another with heigt 1000) and
review the output.

imageHandler.php

You can separately test the 'imageHandler.php' from a web browser:
<serverUrl><path>/imageHandler.php?i=<imagePath>
and add opt. arguments at the end.
ex.
http://localhost/imageHandler/imageHandler.php?i=image.php&mw=200
(replace 'image.php' by image path and file name).



DONATE

You can show your appreciation for our free software, and can support future
development by making a donation of any size by going to
"http://kigkonsult.se/contact/index.php#Donate"
and click the 'Donate' button. Thanks in advance!



SUPPORT

Use the contact page,"http://kigkonsult.se/contact/index.php" for queries,
improvement/development issues or professional support and development.
Please note that paid support or consulting service has the highest priority.

kigkonsult offer professional services for software support, design and
new/re-development, customizations and adaptations of PHP and MySQL/MaridDb
solutions with focus on software lifecycle management, including long term
utility, robustness and maintainability.

