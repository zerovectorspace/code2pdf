<!--
  This file is part of code2pdf.

  code2pdf is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  code2pdf is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with code2pdf.  If not, see <http://www.gnu.org/licenses/>.

  Copyright 2018 Zachary Young
  -->

<?php

function reArrayFiles( &$file_post )
{
  $output = array();
  $file_count = count( $file_post[ 'name' ] );
  $file_keys = array_keys( $file_post );

  for ( $i = 0 ; $i < $file_count ; $i++ )
    foreach ( $file_keys as $key )
      $output[ $i ][ $key ] = $file_post[ $key ][ $i ];

  return $output;
}

function get_rand_str( $n )
{
  $pool = "1234567890ABCDEFGHIJKLMNOPQRSTUVXYZabcdefghijklmnopqrstuvxyz";
  $output = "";

  for ( $i = 0 ; $i < $n ; $i++ )
    $output .= $pool[ random_int( 0, strlen( $pool ) - 1 ) ];

  return $output;
}

function handle_files( &$dir, &$output_zip, &$debug_msg )
{
  $now = date( "Ymd-His" );

  if ( ! mkdir( $dir ) )
  {
    $debug_msg .= "ERROR: Could not make directory: '$dir'<br>";
    return false;
  }
  else
    $debug_msg .= ":) Created directory: '$dir'<br>";

  $zip = new ZipArchive();

  $zip_name = $dir . "/code2pdf-$now.zip";
  $output_zip = $zip_name;

  if ( ! $zip->open( $zip_name, ZipArchive::CREATE ) )
  {
    $debug_msg .= "ERROR: Cannot open '$zip_name'<br>";
    return false;
  }
  else
    $debug_msg .= ":) Zip created successfully as '$zip_name'<br>";

  $files = reArrayFiles( $_FILES["input_files"] );
  foreach( $files as $file )
  {
    $name = $file['name'];
    $tmp_name = $file['tmp_name'];

    // Dir + Filename for 
    $new_name = $dir . "/" . $name;

    if ( ! move_uploaded_file( $tmp_name, $new_name ) )
    {
      $debug_msg .= "ERROR: Could not move the file from '$tmp_name' to '$new_name'<br>";
      return false;
    }
    else
      $debug_msg .= ":) File moved successfully from '$tmp_name' to '$new_name'! :)<br>";

    $sh_output = array();
    $sh_status = 0;
    $code2pdf_command = "./code2pdf.sh -d $dir ";
    exec( $code2pdf_command . $new_name, $sh_output, $sh_status );

    if ( $sh_status !== 0 )
    {
      $debug_msg .= "ERROR: '$code2pdf_command $new_name' failed!<br>";
      return false;
    }
    else
      $debug_msg .= ":) '$code2pdf_command $new_name' was successful!<br>";

    if ( ! $zip->addFile( "$new_name.pdf", "$name.pdf" ) )
    {
      $debug_msg .= "ERROR: Could not add '$new_name.pdf' to '$zip_name'<br>";
      return false;
    }
    else
      $debug_msg .= ":) Added '$new_name.pdf' to '$zip_name'<br>";
  }

  $debug_msg .= "zip has " . $zip->numFiles . " files<br>";
  $debug_msg .= "zip status: " . $zip->getStatusString() . "<br>";

  $zip->close();

  return true;
}
  
if ( $_SERVER['REQUEST_METHOD'] === 'POST' )
{
  $zip = "";
  $debug_msg = "";
  $workdir = "/tmp/" . get_rand_str( 16 );
  $success = handle_files( $workdir, $zip, $debug_msg );
  if ( $success )
  {
    header( 'Content-Description: File Transfer' );
    header( 'Content-Type: application/zip' );
    header( 'Content-Disposition: attachment; filename="' . basename( $zip ) . '"' );
    header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
    header( 'Expires: 0' );
    header( 'Content-Length: ' . filesize( $zip ) );
    ob_clean();
    flush();

    readfile( $zip );
  }
  else
    echo "!!! Error !!!<br><pre><code>" . nl2br( $debug_msg ) . "</code></pre>";

  // Cleanup all files to increase privacy
  register_shutdown_function( "exec", "/usr/bin/rm -rf $workdir" );
}
  
?>


<html>
<head></head>
<body>
  <form enctype="multipart/form-data" action="/" method="POST">
    <input type="file" name="input_files[]" id="input-files" multiple>
    <input type="submit" value="Convert">
  </form>

</body>
</html>
