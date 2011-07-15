<?php    if (!defined('BASEPATH')) exit('No direct script access allowed');

class Dropbox_model extends CI_Model {

    function Dropbox_model()
    {
        parent::__construct();
    }

    function upload($ajax = FALSE)
    {
        /**
         * Check if the user has dropbox enabled
         */
        $dropbox = new Dropbox();

        if (! $dropbox->enabled())
        {
            $error = "User tried to upload file without Dropbox enabled";
            return $error;
        }
        
        /**
         * Get file attributes
         */
        $file_name = $_FILES['file']['name'];
        $temp_name = $_FILES['file']['tmp_name'];
        $file_size = $_FILES['file']['size'];
        $file_type = $_FILES['file']['type'];
        
        /**
         * Did the file get saved to the server?
         */
        if ($file_size <= 0)
        {
            $error = "File size error";
            return $error;
        }
        
        /**
         * Read in file data
         */
        if (! ($file = $dropbox->uploadFile($file_name, $temp_name)))
        {
            return $dropbox->error;
        }
        
        if ($ajax)
        {
            // load your JSON data (view file) and echo out
        }
        
        return FALSE;
    }
    
}
