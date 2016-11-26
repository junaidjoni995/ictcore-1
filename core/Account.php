<?php
/* * ***************************************************************
 * Copyright © 2014 ICT Innovations Pakistan All Rights Reserved   *
 * Developed By: Nasir Iqbal                                       *
 * Website : http://www.ictinnovations.com/                        *
 * Mail : nasir@ictinnovations.com                                 *
 * *************************************************************** */

class Account
{

  /** @const */
  const USER_DEFAULT = -1;
  const COMPANY = -2;
  const ANONYMOUS = -3;

  protected static $table = 'account';
  protected static $primary_key = 'account_id';
  protected static $fields = array(
      'account_id',
      'type',
      'username',
      'passwd',
      'passwd_pin',
      'first_name',
      'last_name',
      'phone',
      'email',
      'address',
      'active',
      'user_id'
  );
  protected static $read_only = array(
      'account_id',
      'type',
      'user_id'
  );

  /**
   * @property-read integer $account_id
   * @var integer
   */
  protected $account_id = NULL;

  /**
   * @property-read string $type
   * @var string 
   */
  protected $type = 'account';

  /**
   * @property string $username
   * @see function Account::set_username()
   * @var string 
   */
  protected $username = NULL;

  /** @var string */
  public $passwd = NULL;

  /** @var string */
  public $passwd_pin = NULL;

  /** @var string */
  public $first_name = NULL;

  /** @var string */
  public $last_name = NULL;

  /** @var string */
  public $phone = NULL;

  /** @var string */
  public $email = NULL;

  /** @var string */
  public $address = NULL;

  /** @var integer */
  public $active = 0;

  /**
   * @property-read integer $user_id
   * @see function Account::associate()
   * @var integer
   */
  protected $user_id = NULL;

  public function capabilities()
  {
    return (Transmission::INBOUND | Transmission::OUTBOUND);
  }

  public function __construct($account_id = NULL)
  {
    if (!empty($account_id)) {
      $this->account_id = $account_id;
      if (Account::ANONYMOUS == $account_id) {
        Corelog::log("Anonymous account: creating instance", Corelog::CRUD);
        $this->account_id = $account_id;
        $this->first_name = 'Anonymous';
        $this->last_name = 'User';
        $this->email = 'anonymous@unknown.com';
        $this->phone = '0000000000';
        $this->address = 'Unknown';
        return $account_id; // don't proceed further        
      } else if (Account::COMPANY == $account_id) {
        Corelog::log("Company account: creating instance", Corelog::CRUD);
        $this->account_id = $account_id;
        $title = conf_get('company:name', 'ICTCore');
        $aTitle = explode(' ', $title, 2);
        $this->first_name = $aTitle[0];
        $this->last_name = isset($aTitle[1]) ? $aTitle[1] : '';
        $this->email = conf_get('company:email', 'no-reply@example.com');
        $this->phone = conf_get('company:phone', '1111111111');
        $this->address = conf_get('company:address', 'PK');
        return $account_id; // don't proceed further
      } else if (Account::USER_DEFAULT == $account_id) {
        Corelog::log("Default account: creating instance", Corelog::CRUD);
        $query = "SELECT account_id FROM " . self::$table . " WHERE active=1 AND created_by=%user_id%
                   ORDER BY account_id DESC LIMIT 1";
        $result = DB::query(self::$table, $query, array('user_id' => user_get('user_id')));
        $data = mysql_fetch_assoc($result);
        $this->account_id = $data['account_id'];
      }
      $this->load();
    }
  }

  public static function construct_from_array($aAccount)
  {
    $oAccount = new Account();
    foreach ($aAccount as $field => $value) {
      $oAccount->$field = $value;
    }
    return $oAccount;
  }

  public static function search($aFilter = array())
  {
    $aAccount = array();
    $from_str = self::$table;
    $aWhere = array();
    foreach ($aFilter as $search_field => $search_value) {
      switch ($search_field) {
        case 'account_id':
          $aWhere[] = "$search_field = $search_value";
          break;
        case 'type':
        case 'username':
        case 'phone':
        case 'email':
        case 'passwd':
        case 'passwd_pin':
        case 'first_name':
        case 'last_name':
          $aWhere[] = "$search_field LIKE '%$search_value%'";
          break;
      }
    }
    if (!empty($aWhere)) {
      $from_str .= ' WHERE ' . implode(' AND ', $aWhere);
    }

    $query = "SELECT account_id, username, first_name, last_name, phone, email FROM " . $from_str;
    Corelog::log("account search with $query", Corelog::DEBUG, array('aFilter' => $aFilter));
    $result = DB::query('account', $query);
    while ($data = mysql_fetch_assoc($result)) {
      $aAccount[$data['account_id']] = $data;
    }

    // if no account found, check for special accounts
    $special_accounts = array(Account::USER_DEFAULT, Account::COMPANY, Account::ANONYMOUS);
    if (empty($aAccount) && isset($aFilter['account_id']) && in_array($aFilter['account_id'], $special_accounts)) {
      $oAccount = new Account($aFilter['account_id']);
      $aAccount[$oAccount->account_id] = array(
          'account_id' => $oAccount->account_id,
          'username' => $oAccount->username,
          'first_name' => $oAccount->first_name,
          'last_name' => $oAccount->last_name,
          'phone' => $oAccount->phone,
          'email' => $oAccount->email
      );
    }

    return $aAccount;
  }

  public function token_get()
  {
    $aToken = array();
    foreach (self::$fields as $field) {
      $aToken[$field] = $this->$field;
    }
    return $aToken;
  }

  public static function getClass($account_id)
  {
    if (ctype_digit(trim($account_id))) {
      $query = "SELECT type FROM " . self::$table . " WHERE account_id='%account_id%' ";
      $result = DB::query(self::$table, $query, array('account_id' => $account_id));
      if (is_resource($result)) {
        $account_type = mysql_result($result, 0);
      }
    } else {
      $account_type = $account_id;
    }
    $class_name = ucfirst(strtolower(trim($account_type)));
    if (class_exists($class_name)) {
      return $class_name;
    } else {
      return false;
    }
  }
  
  public static function load($account_id)
  {
    $class_name = self::getClass($account_id);
    if ($class_name) {
      Corelog::log("Creating instance of : $class_name for account: $account_id", Corelog::CRUD);
      return new $class_name($account_id);
    } else {
      Corelog::log("$class_name class not found, Creating instance of : Account", Corelog::CRUD);
      return new self($account_id);
    }
  }

  protected function _load()
  {
    Corelog::log("Loading account: $this->account_id", Corelog::CRUD);
    $query = "SELECT * FROM " . self::$table . " WHERE account_id='%account_id%' ";
    $result = DB::query(self::$table, $query, array('account_id' => $this->account_id));
    $data = mysql_fetch_assoc($result);
    if ($data) {
      $this->account_id = $data['account_id'];
      $this->username = $data['username'];
      $this->passwd = $data['passwd'];
      $this->passwd_pin = $data['passwd_pin'];
      $this->first_name = $data['first_name'];
      $this->last_name = $data['last_name'];
      $this->phone = $data['phone'];
      $this->email = $data['email'];
      $this->address = $data['address'];
      $this->active = $data['active'];
      $this->user_id = $data['created_by'];
    } else {
      throw new CoreException('404', 'Account not found');
    }
  }

  public function delete()
  {
    Corelog::log("Deleting account: $this->account_id", Corelog::CRUD);
    // also delete all installed program
    $this->remove_program('all');
    // now delete account
    return DB::delete(self::$table, 'account_id', $this->account_id, true);
  }

  public function __isset($field)
  {
    $method_name = 'isset_' . $field;
    if (method_exists($this, $method_name)) {
      return $this->$method_name();
    } else {
      return isset($this->$field);
    }
  }

  public function __get($field)
  {
    $method_name = 'get_' . $field;
    if (method_exists($this, $method_name)) {
      return $this->$method_name();
    } else if (!empty($field) && in_array($field, self::$fields)) {
      return $this->$field;
    }
    return NULL;
  }

  public function __set($field, $value)
  {
    $method_name = 'set_' . $field;
    if (method_exists($this, $method_name)) {
      $this->$method_name($value);
    } else if (empty($field) || !in_array($field, self::$fields) || in_array($field, self::$read_only)) {
      return;
    } else {
      $this->$field = $value;
    }
  }

  protected function set_username($username)
  {
    if (empty($this->username)) {
      $this->username = $username;
    }
  }

  public function save()
  {
    $data = array(
        'account_id' => $this->account_id,
        'username' => $this->username,
        'passwd' => $this->passwd,
        'passwd_pin' => $this->passwd_pin,
        'first_name' => $this->first_name,
        'last_name' => $this->last_name,
        'phone' => $this->phone,
        'email' => $this->email,
        'address' => $this->address,
        'active' => $this->active
            // Note: user_id or created_by field can't be updated here, instead use associate method
    );

    if (isset($data['account_id']) && !empty($data['account_id'])) {
      // update existing record
      $result = DB::update(self::$table, $data, 'account_id', true);
      Corelog::log("Account updated: $this->account_id", Corelog::CRUD);
    } else {
      // add new
      $result = DB::update(self::$table, $data, false, true);
      $this->account_id = $data['account_id'];
      Corelog::log("New account created: $this->account_id", Corelog::CRUD);
    }
    return $result;
  }

  /*
    Associate / assign current account to some user
   */

  public function associate($user_id, $aUser = array())
  {
    Corelog::log("Changing account owner for: $this->account_id from: $this->user_id to: $user_id", Corelog::CRUD);
    $query = "UPDATE " . self::$table . " SET created_by=%user_id% WHERE account_id=%account_id%";
    $result = DB::query(self::$table, $query, array('user_id' => $user_id, 'account_id' => $this->account_id));
    if ($result && !empty($aUser) && is_array($aUser)) {
      foreach ($aUser as $field => $value) {
        $this->__set($field, $value); // set function will do necessary validation
      }
      $this->save();
    }
  }

  public function dissociate()
  {
    // first remove all associated programs
    $this->remove_program('all');
    $this->associate(0);
  }

  /*
    Compile given program with current account
    ( only if given program support it )
   */

  public function install_program($oProgram)
  {
    Corelog::log("Program installation for: $this->account_id Program: $oProgram->name", Corelog::CRUD);
    $oToken = $oProgram->load_token();
    if (array_key_exists('account', $oToken->token['cache'])) {
      $program_data = $oProgram->data;
      $program_data['account'] = $this->account_id;
      $oProgram->data = $program_data;
    } else {
      return false;
    }
    $oProgram->save();
    $oProgram->compile();
    return $oProgram->program_id;
  }

  public function remove_program($program_name = 'all')
  {
    Corelog::log("Removing program from: $this->account_id Program: $program_name", Corelog::CRUD);
    $aProgram = Program::search_resource('account', $this->account_id);
    if ($aProgram) { // no error / false
      foreach (array_keys($aProgram) as $program_id) {
        if (ctype_digit($program_name) && $program_name == $program_id) {
          $oProgram = new Program($program_id);
          $oProgram->delete();
        } else {
          $oProgram = new Program($program_id);
          if (empty($program_name) || 'all' == strtolower($program_name) || strtolower($program_name) == $oProgram->name) {
            $oProgram->delete();
          }
        }
      }
    }
  }

}