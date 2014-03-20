<?php

class PDO_Manager_User extends PDO_Manager {

	const USER_STATE_DISABLED = 0;
	const USER_STATE_ENABLED = 1;
	const ACTIVATED = true;
	private $_secret_key = 'pDo_MaNaGer_uSer_SEcrEt_KeY';
	private $_user_table = 'users';
	
	public function __construct($dsn, $username, $password, $secret_key='') {
		
		parent::__construct($dsn, $username, $password);
		
		if(!empty($secret_key)) {
			
			$this->_secret_key = $secret_key;
			
		}
		
		if($this->isSession()) {

			session_regenerate_id();
			
		}
		
	}
	
	public function sessionStart() {
		
		session_name($this->getEncryptedValue($this->_secret_key));
		session_start();
		
	}
	
	public function setUserTable($table) {
		
		$this->_user_table = $table;
		
	}
	
	public function isUserExists($params) {
		
		return ($this->getUserId($params) > 0);
		
	}
	
	public function isSession() {
		
		return (!empty(session_id()));
		
	}
	
	public function isLogin($redirect_url='') {
		
		$result = (intval($_SESSION['user_data']['id']) > 0);
		
		if(!$result) {
		
			$this->logout($redirect_url);
				
		}
		
		return $result;
		
	}
	
	public function isEmailExists($email) {
		
		return ($this->getUserId(array('email' => $email)) > 0);
		
	}
	
	public function isActivated($email) {
		
		$id = $this->getUserId(array('email' => $email));
		
		if($id == 0) {
			
			return false;
			
		}
		
		$table = $this->getUserExtraTable();
		$field = 'extra_value';
		$where = 'WHERE user_id = ? AND extra_key = ?';
		$params = array($id, 'activated');
		$activated = intval($this->selectOne($table, $field, $where, $params));
		return ($activated > 0);
		
	}
	
	public function activateValues($email, $time) {
		
		$id = $this->getUserId(array('email' => $email));
		
		if(intval($id) == 0) {
			
			return array();
			
		}
		
		return array(
				
			'id' => $id, 
			'time' => $time, 
			'activate_code' => $this->getActivateCode($email, $time)
				
		);
		
	}
	
	public function activate($id, $time, $activate_code) {
		
		$email = $this->selectOne($this->getUserTable(), 'email', 'WHERE id = ?', array(intval($id)));
		
		if($this->getActivateCode($email, $time) == $activate_code) {
			
			$this->updateUser($id, array(
					'activated' => $this->date
			));
			return true;
			
		}
		
		return false;
		
	}
	
	private function getActivateCode($email, $time) {
		
		return $this->getEncryptedValue($email .'_'. $time);
		
	}

	public function userID() {
	
		return intval($_SESSION['user_data']['id']);
	
	}
	
	public function userData($refresh_flag=false) {

		if($this->isSession() 
				&& !$refresh_flag
				&& !empty($_SESSION['user_data'])) {
			
			return $_SESSION['user_data'];
			
		}
		
		$id = $this->userID();
		
		if($id == 0) {
			
			return array();
			
		}
		
		$user_data = $this->select($this->getUserTable(), '*', 'WHERE id = ?', array($id));
		$user_extra_data = $this->select($this->getUserExtraTable(), '*', 'WHERE user_id = ?', array($id));
		
		$returns = array(
				'id' => $id, 
				'email' => $user_data[0]['email'],
				'password' => $user_data[0]['password']
		);
		
		$user_extra_data_count = count($user_extra_data);
		
		for ($i = 0; $i < $user_extra_data_count; $i++) {
			
			$user_extra_values = $user_extra_data[$i];
			$key = $user_extra_values['extra_key'];
			$value = $user_extra_values['extra_value'];
			$returns[$key] = $value;
			
		}
		
		$_SESSION['user_data'] = $returns;
		return $returns;
		
	}
	
	public function insertUser($params, $activated_flag=false) {
		
		$email = $params['email'];
		$password = $this->getEncryptedValue($params['password']);
		
		if(!empty($email) && !empty($password)) {
			
			$this->insert($this->getUserTable(), 'email, password', array($email, $password));
			$user_id = $this->getLastInsertId('id');
			unset($params['email'], $params['password']);
			
			if($user_id > 0 && count($params) > 0) {
				
				if($activated_flag) {
					
					$params['activated'] = '0';
					
				}
				
				$params['updated'] = $this->date;
				$params['created'] = $this->date;
				
				foreach ($params as $key => $value) {
					
					$this->insert($this->getUserExtraTable(), 'user_id, extra_key, extra_value', array(
							
							$user_id, 
							$key, 
							$value
							
					));
					
				}
				
			}
			
			return true;
			
		}
		
		return false;
		
	}

	public function updateUser($id, $params) {
	
		if(intval($id) < 1) {
			
			return false;
			
		}
		
		$password = $params['password'];
		unset($params['email'], $params['password']);
		
		if(!empty($password)) {
			
			$this->update($this->getUserTable(), 'password = ?', 'WHERE id = ?', array(
					$this->getEncryptedValue($password), 
					$id
			));
			
		}
		
		if(count($params) > 0) {

			$table = $this->getUserExtraTable();
			$insert_fields = 'user_id, extra_key, extra_value';
			$update_set_values = 'extra_value = ?';
			$params['updated'] = $this->date;
			
			foreach ($params as $key => $value) {
				
				$this->insertOnDuplicateKey($table, $insert_fields, $update_set_values, array(
				
						$id,
						$key, 
						$value, 
						$value
				
				));
				
			}
			
		}
		
		$this->userData(true);
		return true;
	
	}
	
	public function deleteUser($id, $params) {

		if(intval($id) < 1) {
				
			return false;
				
		}
		
		$email = $params['email'];
		$password = $this->getEncryptedValue($params['password']);
		$params = array(
				'id' => $id, 
				'email' => $email, 
				'password' => $password
		);
		
		if(!empty($email) && !empty($password) && $this->isUserExists($params)) {

			$this->delete($this->getUserTable(), 'WHERE id = ?', array($id));
			$this->delete($this->getUserExtraTable(), 'WHERE user_id = ?', array($id));
			return true;
			
		}
		
		return false;
		
	}
	
	public function login($email, $password, $activated_flag=false) {
		
		$password = $this->getEncryptedValue($password);
		$params = array(
				'email' => $email, 
				'password' => $password
		);
		$id = $this->getUserId($params);
		
		if($id > 0) {
			
			$_SESSION['user_data']['id'] = $id;
			$user_data = $this->userData(true);
			
			if($activated_flag && intval($user_data['activated']) == 0) {
				
				$this->logout();
				return false;
				
			}
			
			return true;
			
		}
		
		$this->logout();
		return false;
		
	}
	
	public function logout($redirect_url='') {
		
		unset($_SESSION['user_data']);
		
		$session_name = session_name();
		
		if(isset($_COOKIE[$session_name])) {
			
			setcookie($session_name, '', time() - 1800, '/');
			
		}
		
		if(!empty($redirect_url)) {
			
			$this->redirect($redirect_url);
			
		}
		
	}
	
	public function resetPassword($email, $password_length=10) {
		
		$random_value = $this->getEncryptedValue($email .'_'. $this->date);
		$reset_password = substr($random_value, 0, $password_length);
		
		$table = $this->getUserTable();
		$set_values = 'password = ?';
		$where = 'WHERE email = ?';
		$params =  array(
				$this->getEncryptedValue($reset_password), 
				$email
		);
		
		if($this->update($table, $set_values, $where, $params)) {

			return $reset_password;
			
		}
		
		return '';
		
	}
	
	public function create_table($user_table='users') {
		
		$sqls = array(
				
				'CREATE TABLE IF NOT EXISTS `'. $this->getTable($this->getUserTable()) .'` ('.
					'`id` int(11) unsigned NOT NULL AUTO_INCREMENT,'.
					'`email` varchar(100) NOT NULL,'.
					'`password` varchar(64) NOT NULL,'.
					'PRIMARY KEY (`id`),'.
					'UNIQUE KEY `email` (`email`)'.
					') ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;', 
				
				'CREATE TABLE IF NOT EXISTS `'. $this->getTable($this->getUserExtraTable()) .'` ('.
					'`id` int(11) unsigned NOT NULL AUTO_INCREMENT,'.
					'`user_id` int(11) unsigned NOT NULL,'.
					'`extra_key` varchar(255) NOT NULL,'.
					'`extra_value` longtext NOT NULL,'.
					'PRIMARY KEY (`id`),'.
					'UNIQUE KEY `user_id` (`user_id`,`extra_key`)'.
					') ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;'
				
		);
		
		foreach ($sqls as $sql) {
			
			$this->query($sql);
			
		}
		
	}

	private function getUserId($params) {
	
		$where_phrase = array();
		$sql_params = array();
	
		foreach ($params as $key => $value) {
	
			$where_phrase[] = $key .' = ?';
			$sql_params[] = $value;
	
		}
	
		return intval($this->selectOne($this->getUserTable(), 'id', 'WHERE '. implode(' AND ', $where_phrase), $sql_params));
	
	}
	
	private function getUserTable() {
		
		return $this->_user_table;
		
	}
	
	private function getUserExtraTable() {
		
		return $this->getUserTable() .'_extra';
		
	}
	
	private function getEncryptedValue($value) {
		
		return md5($this->_params['secret_key'] .'_'. $value);
		
	}
	
	private function redirect($url) {
		
		header('location: '. $url);
		die();
		
	}
	
}
/*** Example

	require 'pdo_manager.php';
	require 'pdo_manager_user.php';
	
	$pdo = new PDO_Manager_User(DSN, DSN_USER, DSN_PASS, 'secret_key');
	$pdo->sessionStart();			// Skippable if no need to login 
	$pdo->setPrefix('pdo_');		// Skippable
	$pdo->setUserTable('users');	// Skippable (Default: users)

	// Create Table

	$pdo->create_table();

	// Insert User

	$email = 'test@example.com';
	$password = 'password';
	
	if(!$pdo->isActivated($email)) {		// This is skippable if no need to activate.
		
		$pdo->insertUser(array(
				'email' => $email,
				'password' => $password,
				'gender' => 'm',
				'blood_type' => 'a',
				'age' => '25'
		), PDO_Manager_User::ACTIVATED);	// 2nd arg is skippable if no need to activate.

		$activate_values = $pdo->activateValues($email, $pdo->date);
		$activate_url = 'http://example.com/activate.php?'. http_build_query($activate_values);
		
		// Send $activate_url by email.
		// and then in [activate.php]
			
			$id = intval($_GET['id']);
			$time = intval($_GET['time']);
			$activate_code = $_GET['activate_code'];
			
			if($pdo->activate($id, $time, $activate_code)) {
					
				echo 'success!';
					
			} else {
					
				echo 'failed..';
					
			}
		
	} else {
		
		echo 'The email address is already registered.';
		
	}
	
	// Login

	$email = 'test@example.com';
	$password = 'password';
	
	if($pdo->login($email, $password, PDO_Manager_User::ACTIVATED)) {
		
		echo 'seccrss!';
		
	} else {
		
		echo 'failed..';
		
	}
	
	if($pdo->isLogin()) {	// or if($pdo->isLogin($redirect_url)) {
	
		$user_id = $pdo->userID();
		$user_data = $pdo->userData();	// $user_data = $pdo->userData(true); // Data will be refreshed.
		print_r($user_data);
			
		// Update User (*Note: You cannot change email address.)
		
		$pdo->updateUser($user_id, array(
				'password' => 'password',
				'gender' => 'f',
				'blood_type' => 'o',
				'age' => '22',
				'hobby' => 'piano'	// You can add new filed like this.
		));
		
		// Delete User
			
		$pdo->deleteUser($user_id, array(

				'email' => 'test@example.com',
				'password' => 'password'

		));
			
		$pdo->logout();	// or $pdo->logout($redirect_url);
			
	} else {
		
		$email = 'test@example.com';
		
		if($pdo->isEmailExists($email)) {
			
			$password_length = 10;
			echo $reset_password = $pdo->resetPassword($email, $password_length);	// $password_length is skippable.(Default: 10)
			
			// Send $new_password by email.
			
		} else {
			
			echo 'The email is not registered yet.';
			
		}
		
	}
	
***/
