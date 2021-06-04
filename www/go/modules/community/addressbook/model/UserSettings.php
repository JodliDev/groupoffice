<?php

namespace go\modules\community\addressbook\model;

use go\core\model\User;
use go\core\orm\Property;
use go\modules\community\addressbook\model\Settings as AddresBookModuleSettings;
use go\core\model;
use go\core\model\Acl;
use phpDocumentor\Reflection\Types\Boolean;

class UserSettings extends Property {

	/**
	 * Primary key to User id
	 * 
	 * @var int
	 */
	public $userId;
	
	/**
	 * Default address book ID
	 * 
	 * @var int
	 */
	protected $defaultAddressBookId;

	/**
	 * Remember last selected item option
	 * @var boolean
	 */
	protected $rememberLastItem;

	/**
	 * Last selected item
	 * @var int
	 */
	protected $lastAddressBookId;

	protected $displayAllContactsByDefault;

	protected static function defineMapping() {
		return parent::defineMapping()->addTable("addressbook_user_settings", "abs");
	}

	public function getRememberLastItem() :bool
	{
		return (boolean) $this->rememberLastItem;
	}

	/**
	 * @return bool
	 */
	public function getDisplayAllContactsByDefault() :bool
	{
		return (boolean) $this->displayAllContactsByDefault;
	}

	public function getLastAddressBookId()
	{
		return $this->lastAddressBookId;
	}

	public function getDefaultAddressBookId() {
		if(isset($this->defaultAddressBookId)) {
			return $this->defaultAddressBookId;
		}

		if(!model\Module::isAvailableFor('community', 'addressbook', $this->userId)) {
			return null;
		}

		if(AddresBookModuleSettings::get()->createPersonalAddressBooks){
			$addressBook = AddressBook::find()->where('createdBy', '=', $this->userId)->single();
			if(!$addressBook) {
				$addressBook = new AddressBook();
				$addressBook->createdBy = $this->userId;
				$addressBook->name = User::findById($this->userId, ['displayName'])->displayName;
				if(!$addressBook->save()) {
					throw new \Exception("Could not create default address book");
				}				
			}
		} else {
			$addressBook = AddressBook::find(['id'])->filter(['permissionLevel' => Acl::LEVEL_WRITE, 'permissionLevelUserId' => $this->userId])->single();			
		}

		if($addressBook) {
			$this->defaultAddressBookId = $addressBook->id;
			go()->getDbConnection()->update("addressbook_user_settings", ['defaultAddressBookId' => $this->defaultAddressBookId], ['userId' => $this->userId])->execute();
		}

		return $this->defaultAddressBookId;
		
	}

	public function setDefaultAddressBookId($id)
	{
		$this->defaultAddressBookId = $id;
	}

	public function setRememberLastItem(bool $value)
	{
		$this->rememberLastItem = $value;
	}

	public function setDisplayAllContactsByDefault(bool $value)
	{
		$this->displayAllContactsByDefault = $value;
	}

	public function setLastAddressBookId(?int $addressBookId = null)
	{
		$this->lastAddressBookId = $addressBookId;
	}

	public function getSortBy() {
		return User::findById($this->userId, ['sort_name'])->sort_name == 'first_name' ? 'name' : 'lastName';
	}

	
}
