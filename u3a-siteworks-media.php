<?php

class media
{
  var $filename;
  var $img;
  var $caption;
  var $img_source_URL;  // True for URL or false for LOCAL file source


  function __construct($img, $caption)
  {
    $this->img = $img;  // Note: img may be local file reference or URL
    $this->caption = $caption;
    $this->img = str_replace("/home/u3asites/public_html", "https://u3asites.org.uk", $this->img);
    $this->img_source_URL = (substr($this->img, 0, 7) == 'http://') || (substr($this->img, 0, 8) == 'https://') ? true : false;
    $this->filename = basename($this->img);
  }

  function UR_exists($url)
  {
    $result = false;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    $httpContentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Check file exists, is of right content type and access will be ok without credentials      
    //  The only time SiteBuilder will return a web page (text/html) is if the file is missing.  All other files are either application/??? or image/???
    if (($httpCode == 200) && (!str_contains($httpContentType, 'text/html'))) {
      curl_close($ch);
      $result = true;
    }
    // If request is redirected, try the new URL, changing the url stored in the class property
    if ($httpCode == 301) { //file has been redirected
      $this->img = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
      curl_close($ch);
      $result = $this->UR_exists($this->img);
    }
    return $result;
  }

  // Copy file to media library
  // file_get_contents() should work with either local files or http(s) source
  // If the file is type image/jpeg resize to max 1920px width/height

  function getu3afile()
  {
    $uploads = wp_upload_dir();  // NB - using this function will create the subfolder if it does not exist
    $dir = $uploads['path'];
    $destfile = $dir . '/' . $this->filename;
    if (file_put_contents($destfile, file_get_contents($this->img))) {
      error_log("downloaded " . $this->filename);
      if (mime_content_type($destfile) == 'image/jpeg') {
        list($width, $height) = getimagesize($destfile);
        if ($width > 1920 || $height > 1920) {
          $this->resize_image($destfile);
        }
      }
      return true;
    } else {
      error_log("** failed downloading " . $this->filename);
      return false;
    }
  }

  /**
   * Resize a large jpeg image to maximum height or width of 1920px
   * The original file is overwritten with the resized image
   *
   * @param string $filepath
   * @return void
   */
  function resize_image($filepath)
  {
    // calculate new image dimensions

    list($width, $height) = getimagesize($filepath);
    if ($width == $height) {  // square format
      $newheight = 1920;
      $newwidth = 1920;
    } else {
      $ratio = $width / $height;
      if ($width > $height) { // landscape format
        $newheight = round( 1920 / $ratio );
        $newwidth = 1920;
      } else {
        $newheight = 1920; // portrait format
        $newwidth = round( 1920 * $ratio );
      }
    }

    // load into GdImage object, resize then write to original file

    $original = imagecreatefromjpeg($filepath);
    $resized = imagecreatetruecolor($newwidth, $newheight);
    imagecopyresampled($resized, $original, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
    imagejpeg($resized,$filepath,80);
    error_log( "resized " . $this->filename . " $newwidth x $newheight");
    
  }


  /*
  Add file to media library
  If file does not exist or URL not found, either "defaultdoc.pdf" or "u3a-logo.png" depending on filetype of requested file
  */
  function importmedia($details)
  {

    // If the file is already in the media library, just return its ID
    $thefilename = basename($this->img);
    $mediafilename = wp_upload_dir()['path'] . '/' . $thefilename;
    if (file_exists($mediafilename)) {
      $media_url = wp_upload_dir()['url'] . '/' . $thefilename;
      $attach_id = attachment_url_to_postid($media_url);
      if ($attach_id != 0) {
        error_log("using previously downloaded file: " . $thefilename);
        return $attach_id;
      } else {
        // It might be a resized image file ... "picture.jpg" gets renamed "picture-scaled.jpg"
        $thefilename = pathinfo($thefilename, PATHINFO_FILENAME) . '-scaled.' . pathinfo($thefilename, PATHINFO_EXTENSION);
        $media_url = wp_upload_dir()['url'] . '/' . $thefilename;
        $attach_id = attachment_url_to_postid($media_url);
        if ($attach_id != 0) {
          error_log("using previously downloaded file: " . $thefilename);
          return $attach_id;
        }
        error_log("** downloaded file: " . basename($this->img) . " is in filesystem but not found in media library");
      }
    }

    // If we don't want media download while testing, just treat as missing
    $result = "";
    if (!defined('IMPORTMEDIA')) {
      return -1;
    }


    //NT not required. $this->caption = str_replace(" ", "-", $this->caption);

    $fileFound = false;
    if ($this->img_source_URL && $this->UR_exists($this->img)) $fileFound = true;
    if (!$this->img_source_URL && file_exists($this->img)) $fileFound = true;

    if ($fileFound) $fileCopied = $this->getu3afile($this->img);

    //not used now?
    // If we can't find the file, or fail to copy it to the uploads folder, use the placeholder files

    if (!$fileFound || !$fileCopied) {
      $result = -1;
    } else {

      // Add to media library
      //echo '<p>&nbsp; - &nbsp; Adding ' . $this->filename . ' to library</p>';
      $upload_dir = wp_upload_dir();
      $file = $upload_dir['path'] . '/' . $this->filename;
      $wp_filetype = wp_check_filetype($this->filename, null);
      $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_text_field($this->caption),
        'post_excerpt' => sanitize_text_field($this->caption),
        'post_content' => sanitize_text_field($details),
        'post_status' => 'inherit'
      );

      $attach_id = wp_insert_attachment($attachment, $file);
      require_once(ABSPATH . 'wp-admin/includes/image.php');
      $attach_data = wp_generate_attachment_metadata($attach_id, $file);
      wp_update_attachment_metadata($attach_id, $attach_data);
      $result = $attach_id;
    }
    return $result;
  }
}
