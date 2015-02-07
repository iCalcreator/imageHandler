
IMAGEHANDLER

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
 save     - saves (cropped/resized/adapted) image on disk
Output image type for the managed image is (default) png.
Non-resizable images types are returned 'as is'.

The class package includes
  the PHP imageHandler class
    doing the hard work,
  the PHP interface script
    offering service availability,
  a PHP 'index.php' page,
    usable for testing, evaluating and/or image reviewing,
    including a (separate) editable image crop/resize test schema.
The imageHandler class and index page supports PEAR log class or equivalents.

imageHandler is free for personal, evaluating and testing use,
for commercial licences, visit kigkonsult.se.
