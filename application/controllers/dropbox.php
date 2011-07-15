<?php    if (!defined('BASEPATH')) exit('No direct script access allowed');

class Dropbox extends CI_Controller {

    /**
     * POST variable specifying if the request was AJAX or not
     */
    public $ajax = 0;

    function Dropbox()
    {
        parent::__construct();
        
        /**
         * Store any private common variables
         */
        $this->ajax = $this->input->post('ajax');
        
        /**
         * Load the dependencies
         */
        $this->load->library('dropbox');
        $this->load->model('dropbox_model');
    }
    
    function upload($ajax = "")
    {
        /**
         * Add the file and set error message if failed
         */
        if ($ajax == "ajax")
        {
            $this->ajax = 1;
        }
        
        $error = $this->dropbox_model->upload($this->ajax);

        /**
         * If variable contains value return message
         */
        if (strlen($error) > 0)
        {
            // error, message is in $error
        }
        else
        {
            ($this->ajax)
                ? exit
                : redirect( 'files' );
        }
    }
    
    function authorize()
    {
        /**
         * We're authenticating dropbox here. This is the callback URL from the authorization request.
         * Save the token and account information in the database and redirect back to the appropriate
         * page for error or succes handling.
         */
        $dropbox = new Dropbox();
        
        if ($dropbox->oauth())
        {
            // success, redirect to files page
            redirect( 'files' );
        }
        else
        {
            // error, message is in $dropbox->error
            redirect( 'files' );
        }
    }
    
    function remove()
    {
        /**
         * Validate post request to remove dropbox. Remove record from database and alert the user.
         * 
         * NOT IMPLEMENTED YET
         */
        exit;
    }
    
}
