<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Dropbox API Library
 *
 * Connects users Dropbox accounts to application and allows them
 * to view and manage files.
 */
class Dropbox {

    // change FOLDER_NAME to a public folder you want to store user files into
    //
    const ROOT = '/Public/FOLDER_NAME';
    
    private $CI;
    
    public $oauth;
    public $dropbox;
    public $request_tokens;
    public $access_tokens;
    public $state;
    public $url;
    public $error;
    public $account_info;
    
    function Dropbox()
    {    
        $this->CI =& get_instance();
        $this->CI->load->config('dropbox');
        
        include_once('dropbox/autoload.php');
        
        $this->oauth = new Dropbox_OAuth_PHP($this->CI->config->item('db_key'), $this->CI->config->item('db_secret'));
        $this->dropbox = new Dropbox_API($this->oauth);
        
        /**
         * Get the tokens stored for the user if there are any. Tokens should be a serialized
         * array of the dropbox response oauth token. For this demonstration, everything is stored
         * in the session, but you should store these in the database.
         */
        $data = $this->CI->session->userdata('dropbox_tokens');
        
        if ($data)
        {
            $this->access_tokens = unserialize($data);
            try {
                $this->oauth->setToken($this->access_tokens);
            } catch (Exception $e) {
                $this->unset_data();
                return $this->errorHandle($e, 'setToken');
            }
            
            try {
                $this->account_info = $this->dropbox->getAccountInfo();
            } catch (Exception $e) {
                $this->unset_data();
                return $this->errorHandle($e, 'accountInfo');
            }
        }
    }
    
    function enabled()
    {
        if ($this->access_tokens)
        {
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }
    
    function getFolderContents($folder = self::ROOT)
    {
        try {
            $data = $this->dropbox->getMetaData($folder, FALSE);
        } catch (Exception $e) {
            return $this->errorHandle($e, 'folderContents');
        }
        
        return (isset($data['contents']) && $data['contents'])
            ? $data['contents']
            : array();
    }
    
    function getPublicUrl($path)
    {
        if (! $this->account_info)
        {
            return "";
        }
        
        if (substr($path, 0, 1) !== "/")
        {
            $path = "/". $path;
        }
        
        $path = str_replace("Public/", "", $path);
        $uid = $this->account_info['uid'];
        
        return sprintf("http://dl.dropbox.com/u/%s%s", $uid, $path);
    }
    
    function getFileSize($size)
    {
        if (substr($size, -2) == "MB")
        {
            return substr($size, 0, -2) ." MB";
        }
        elseif (substr($size, -2) == "KB")
        {
            return substr($size, 0, -2) ." KB";
        }
        else
        {
            return $size;
        }
    }
    
    function uploadFile($filename, $file, $folder = self::ROOT)
    {
        try {
            $this->dropbox->putFile($folder .'/'. ltrim($filename, '/'), $file);
        } catch (Exception $e) {
            return $this->errorHandle($e, 'uploadFile');
        }
        
        try {
            $db_file = $this->dropbox->getMetaData($folder .'/'. $filename);
        } catch (Exception $e) {
            return $this->errorHandle($e, 'uploadFile');
        }
        
        return $db_file;
    }
    
    function oauth()
    {
        /**
         * Check if the user has already granted us access to Dropbox (database). If so,
         * then just return the information for their account. Again, these tokens should be 
         * stored in the database but we're using the session for simplicity.
         */
        $data = $this->CI->session->userdata('dropbox_tokens');

        if ($data)
        {
            $this->access_tokens = unserialize($data); 
            $this->CI->session->unset_userdata('dropbox_state');
            
            return TRUE;
        }
        
        /**
         * Perform the OAuth request
         */
        if ($this->CI->session->userdata('dropbox_state'))
        {
            $this->state = $this->CI->session->userdata('dropbox_state');
        }
        else
        {
            $this->CI->session->set_userdata('dropbox_state', 1);
            $this->state = 1;    
        }

        switch ($this->state)
        {
            case 1:
                log_message('info', 'starting request to authorize dropbox account for user');
                
                try {
                    $this->request_tokens = $this->oauth->getRequestToken();
                } catch (Exception $e) {
                    return $this->errorHandle($e, 'requestToken');
                }

                $this->CI->session->set_userdata('dropbox_state', 2);
                $this->CI->session->set_userdata('dropbox_tokens', $this->request_tokens);
                $this->state = 2;
                
                try {
                    $this->url = $this->oauth->getAuthorizeUrl($this->getCallbackUrl());
                } catch (Exception $e) {
                    return $this->errorHandle($e, 'authUrl');
                }

                redirect($this->url);
                exit;
            case 2:
                log_message('info', 'completing request to authorize dropbox account for user');
                
                try {
                    $this->oauth->setToken($this->CI->session->userdata('dropbox_tokens'));
                    $this->access_tokens = $this->oauth->getAccessToken();
                } catch (Exception $e) {
                    return $this->errorHandle($e, 'accessToken');
                }
                
                /**
                 * Create the public folder
                 */
                try {
                    $this->dropbox->createFolder(self::ROOT);
                } catch (Exception $e) {
                    log_message('error', 'dropbox failing to create folder for user but still moving on. this is probably a duplicate folder issue.');
                }
                
                /**
                 * Set state back to 1 if the user gets to this page and there's a problem
                 * writing the data to the database or if they get lost along the way.
                 */
                $this->CI->session->set_userdata('dropbox_state', 1);
                $this->state = 1;    
                
                /**
                 * Save the tokens to the database
                 */
                $this->CI->session->set_userdata('dropbox_tokens', $this->access_tokens);
                $data = serialize($this->access_tokens);
                break;
            default:
                $this->error = $this->CI->lang->line('files_dropbox_error_unknown');
                $this->CI->session->unset_userdata('dropbox_state');
                return FALSE;
        }
        
        return TRUE;
    }
    
    function unset_data()
    {
        $this->CI->session->unset_userdata('dropbox_tokens');
    }
    
    function errorHandle($e, $code = '')
    {
        log_message('error', 'exiting dropbox with code:'. $code, '. $e->getMessage());
        
        $this->error = $e->getMessage();
        $this->CI->session->unset_userdata('dropbox_state');
        $this->CI->session->unset_userdata('dropbox_tokens');
        $this->access_tokens = NULL;
        
        return FALSE;
    }
    
    function getCallbackUrl()
    {
        return site_url('dropbox/authorize');
    }

}
