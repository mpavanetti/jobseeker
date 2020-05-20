<?php if(!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . '/libraries/BaseController.php';


class DeleteJob extends BaseController
{
    /**
     * This is default constructor of the class
     */
    public function __construct()
    {
        parent::__construct();
        $this->isLoggedIn();   
        $this->load->helper('url');
    }

    /**
     * Index Page for this controller.
     */
    public function index()
    {
        if($this->isManager() == TRUE )
        {
            $this->loadThis();
        }
        else
        {
            
            $this->global['pageTitle'] = 'Job Seeker : Delete Job';
        
            $this->loadViews("deleteJob", $this->global, NULL, NULL);
        }
    }

    public function deleteRepository($job_name)
    {
        if($this->isManager() == TRUE )
        {
            $this->loadThis();
        }
        else
        {

         $jenkins_home = $this->global['jenkins_home'];

         function delete ($system, $job_name, $jenkins_home){

             $result = array();
             $exist = false;

             if($jenkins_home != ''){

              $storeFolder = $jenkins_home.'/repository/'.$system.'/jobs/'.$job_name;

              if (file_exists($storeFolder)) {
                $exist = true;
                $result = array('system' => $system, 'exist' => $exist);

                  function rrmdir($src) {
                        $dir = opendir($src);
                        while(false !== ( $file = readdir($dir)) ) {
                            if (( $file != '.' ) && ( $file != '..' )) {
                                $full = $src . '/' . $file;
                                if ( is_dir($full) ) {
                                    rrmdir($full);
                                }
                                else {
                                    unlink($full);
                                }
                            }
                        }
                        closedir($dir);
                        rmdir($src);
                    }

                    rrmdir($storeFolder);
              } 

             } else {
              $storeFolder = 'repository/'.$system.'/jobs/'.$job_name; 
              if (file_exists($storeFolder)) {

                $exist = true;
                $result = array('system' => $system, 'exist' => $exist);

                  function rrmdir($src) {
                        $dir = opendir($src);
                        while(false !== ( $file = readdir($dir)) ) {
                            if (( $file != '.' ) && ( $file != '..' )) {
                                $full = $src . '/' . $file;
                                if ( is_dir($full) ) {
                                    rrmdir($full);
                                }
                                else {
                                    unlink($full);
                                }
                            }
                        }
                        closedir($dir);
                        rmdir($src);
                    }

                    rrmdir($storeFolder);
                    
                 }

             }

             if(count($result) != 0) {
                print_r(json_encode($result, JSON_PRETTY_PRINT));
            }

          }

          delete("batch",$job_name, $jenkins_home);
          delete("bash",$job_name, $jenkins_home);
          delete("talend",$job_name, $jenkins_home);
          delete("python",$job_name, $jenkins_home);

        }
    }

   
    
}

?>