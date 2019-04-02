<?php

namespace go\core\model;

class Log extends \go\core\orm\Entity {

	const ACTION_ADD = 'add';
	const ACTION_DELETE = 'delete';
	const ACTION_UPDATE = 'update';
	const ACTION_LOGIN = 'login';
	const ACTION_LOGOUT = 'logout';

	public $id;
	public $user_id;
	public $username;
	public $model_id;
	public $model;
	public $ctime;
	public $user_agent;
	public $ip;
	public $controller_route;
	public $action;
	public $message;
	public $jsonData;
	
	protected static function defineMapping(): \go\core\orm\Mapping {
		return parent::defineMapping()->addTable('go_log');
	}

	protected function init() {
		parent::init();

		if ($this->isNew()) {

			if (PHP_SAPI == 'cli') {
				$this->user_agent = 'cli';
			} else {
				$this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
			}

			$this->ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
			$this->controller_route = "JMAP";
			$this->username = GO()->getDbConnection()->selectSingleValue('username')->from('core_user')->where('id', '=', GO()->getUserId())->single();
			$this->user_id = GO()->getUserId();
			$this->ctime = time();
		}
	}

}
