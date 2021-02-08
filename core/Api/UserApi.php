<?php

namespace ICT\Core\Api;

/* * ***************************************************************
 * Copyright © 2016 ICT Innovations Pakistan All Rights Reserved   *
 * Developed By: Nasir Iqbal                                       *
 * Website : http://www.ictinnovations.com/                        *
 * Mail : nasir@ictinnovations.com                                 *
 * *************************************************************** */

use ICT\Core\Account;
use ICT\Core\Api;
use ICT\Core\CoreException;
use ICT\Core\User;
use ICT\Core\User\Permission;
use ICT\Core\User\Role;
use ICT\Core\Conf;

class UserApi extends Api
{

  /**
   * Create a new user
   *
   * @url POST /users
   */
  public function create($data = array())
  {
    $this->_authorize('user_create');

    $oUser = new User();
    $this->set($oUser, $data);

    if ($oUser->save()) {
      return $oUser->user_id;
    } else {
      throw new CoreException(417, 'User creation failed');
    }
  }

  /**
   * PUT CRM Configuration
   *
   * @url PUT /users/$user_id/config/$config_name
   */
  public function config_set($user_id, $config_name, $data)
  {
    $this->_authorize('user_update');

    $reference = array();
    $reference['created_by'] = $user_id;
    $reference['class']      = Conf::USER;

    $config_value = $data;

    Conf::set($config_name, $config_value, true, $reference, Conf::PERMISSION_USER_WRITE);
    return true;
  }

  /**
   * GET CRM Configuration
   *
   * @url GET /users/$user_id/config/$config_name
   */
  public function config_get($user_id, $config_name)
  {
    $this->_authorize('user_read');

    return Conf::get($config_name, '');
  } 

  /**
   * List all available users
   *
   * @url GET /users
   */
  public function list_view($query = array())
  {
    $this->_authorize('user_list');
    return User::search((array)$query);
  }

  /**
   * Gets the user by id
   *
   * @url GET /users/$user_id
   */
  public function read($user_id)
  {
    $this->_authorize('user_read');

    $oUser = new User($user_id);
    return $oUser;
  }

  /**
   * Update existing user
   *
   * @url PUT /users/$user_id
   */
  public function update($user_id, $data = array())
  {
    $this->_authorize('user_update');

    $oUser = new User($user_id);
    $this->set($oUser, $data);

    if ($oUser->save()) {
      return $oUser;
    } else {
      throw new CoreException(417, 'User update failed');
    }
  }

  /**
   * Update user passwd
   *
   * @url PUT /users/$user_id/password
   */
  public function update_password($user_id, $data = array())
  {
    $this->_authorize('user_password');

    $oUser = new User($user_id);
    $oUser->password = $data['password'];

    if ($oUser->save()) {
      return $oUser;
    } else {
      throw new CoreException(417, 'User password update failed');
    }
  }

  /**
   * Create a new user
   *
   * @url DELETE /users/$user_id
   */
  public function remove($user_id)
  {
    $this->_authorize('user_delete');

    $oUser = new User($user_id);

    $result = $oUser->delete();
    if ($result) {
      return $result;
    } else {
      throw new CoreException(417, 'User delete failed');
    }
  }

  /**
   * Permission list of user
   *
   * @url GET /users/$user_id/permissions
   */
  public function permission_list_view($user_id, $query = array())
  {
    $this->_authorize('user_list');
    $this->_authorize('permission_list');

    $oUser = new User($user_id);
    return $oUser->search_permission((array)$query);
  }

  /**
   * Allow / authorize user for a certain permission
   *
   * @url PUT /users/$user_id/permissions/$permission_id
   */
  public function allow($user_id, $permission_id)
  {
    $this->_authorize('user_update');
    $this->_authorize('permission_create');

    $oUser = new User($user_id);
    $oUser->permission_assign($permission_id);
    return $oUser->save();
  }

  /**
   * Disallow / prevent a user form using a certain permission
   *
   * @url DELETE /users/$user_id/permissions/$permission_id
   */
  public function disallow($user_id, $permission_id)
  {
    $this->_authorize('user_update');
    $this->_authorize('permission_delete');

    $oUser = new User($user_id);
    $oUser->permission_unassign($permission_id);
    return $oUser->save();
  }

  /**
   * Role list of user
   *
   * @url GET /users/$user_id/roles
   */
  public function role_list_view($user_id, $query = array())
  {
    $this->_authorize('user_list');
    $this->_authorize('role_list');

    $oUser = new User($user_id);
    return $oUser->search_role((array)$query);
  }

  /**
   * Assign a role to user
   *
   * @url PUT /users/$user_id/roles/$role_id
   */
  public function assign($user_id, $role_id)
  {
    $this->_authorize('user_update');
    $this->_authorize('role_update');

    $oUser = new User($user_id);
    $oUser->role_assign($role_id);
    return $oUser->save();
  }

  /**
   * Remove certain role from user
   *
   * @url DELETE /users/$user_id/roles/$role_id
   */
  public function unassign($user_id, $role_id)
  {
    $this->_authorize('user_update');
    $this->_authorize('role_update');

    $oUser = new User($user_id);
    $oUser->role_unassign($role_id);
    return $oUser->save();
  }

  protected static function rest_include()
  {
    return 'Api/User';
  }

  /**
   * List all account assigned to this user
   *
   * @url GET /users/$user_id/accounts
   */
  public function account_list($user_id, $query = array())
  {
    $this->_authorize('user_read');
    $this->_authorize('account_list');

    $filter = (array)$query;
    $filter['created_by'] = $user_id;
    return Account::search($filter);
  }
  
   /**
   * Import Users
   *
   * @url POST /users/csv
      * 
   */
  public function import_csv($data = array(), $mime = 'text/csv')
    {
    global $path_root, $path_cache;
    $newUsers=$errors=array();
    $allowedTypes = array('csv' => 'text/csv', 'txt' => 'text/plain');
    if (in_array($mime, $allowedTypes)) {
      if (!empty($data)) {
        $file_path = $path_cache . DIRECTORY_SEPARATOR . 'users.csv';
        file_put_contents($file_path, $data);
        if (file_exists($file_path)) { 
            $csvFile = fopen($file_path, 'r');

            // Skip the first line
            fgetcsv($csvFile);
            $line_no=0;
            while(($line = fgetcsv($csvFile)) !== FALSE){
              if($line[4]!=""){
                $line_no++;
                // Get row data

              $data=array(
                  'first_name'=>$line[1],
                  'last_name'=>$line[2],
                  'phone'=>$line[3],
                  'email'=>$line[4],
                  'address'=>$line[5],
                  'company'=>(int)$line[6],
                  'country_id'=>(int)$line[7],
                  'language_id'=>(int)$line[8],
                  'timezone_id'=>(int)$line[9]
                );

              $oUser = new User();
              $this->set($oUser, $data);

                if ($oUser->save()) {
                  array_push($newUsers,$oUser->user_id);
                } else {
                  array_push($errors, $line_no);
                }

              }

            }

            fclose($csvFile);

           if(empty($errors)){
                return count($newUsers);
              }
            else{
              throw new CoreException(415, "Rocord(s) at following line(s) not inserted:".json_encode($errors));
            }
        }
        else{
          throw new CoreException(404, "File not found");
        }

      } else {
        throw new CoreException(411, "Empty file");
      }
    } else {
      throw new CoreException(415, "Unsupported File Type");
    }
  }


/**
   * Export Csv
   *
   * @url GET /users/csv
   * 
 */
public function export_list($query = array())
  {

    $this->_authorize('user_list');
    $oUser = User::search((array)$query);
    if ($oUser) {

      $file_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'users.csv';

      $handle = fopen($file_path, 'w');
      if (!$handle) {
        throw new CoreException(500, "Unable to open file");
      }

      foreach($oUser as $aValue) {
        $contact_row = '"'.$aValue['user_id'].'","'.$aValue['first_name'].'","'.$aValue['last_name'].'","'.$aValue['phone'].'","'.$aValue['email'].'",'.
                     '"'.$aValue['address'].'","'.$aValue['company'].'","'.$aValue['country_id'].'","'.$aValue['language_id'].'",'.
                     '"'.$aValue['timezone_id'].'"'."\n";
        fwrite($handle, $contact_row);
      }

      fclose($handle);

      return new SplFileInfo($file_path);
    } else {
      throw new CoreException(404, "User not found");
    }
  }
/**
   * Provide User Sample
   *
   * @url GET /users/sample/csv
 */
  public function sample_csv()
  {
    global $path_data;
    $sample_contact = $path_data . DIRECTORY_SEPARATOR . 'users_sample.csv';
    if (file_exists($sample_contact)) {
      return new SplFileInfo($sample_contact);
    } else {
      throw new CoreException(404, "File not found");
    }
  }
}
