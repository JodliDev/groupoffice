<?php
namespace go\modules\community\notes;

use Faker\Generator;
use go\core;
use go\core\model\User;
use go\core\model\Acl;
use go\core\model\Group;
use go\core\model\Module as ModuleModel;
use go\core\orm\Mapping;
use go\core\orm\Property;
use go\modules\community\notes\model\Note;
use go\modules\community\notes\model\NoteBook;
use go\modules\community\notes\model\UserSettings;

class Module extends core\Module {	

	public function getAuthor(): string
	{
		return "Intermesh BV";
	}

	protected function rights(): array
	{
		return [
			'mayChangeNotebooks', // allows Nootbook/set (hide ui elements that use this)
		];
	}

	public function autoInstall(): bool
	{
		return true;
	}

	
	protected function afterInstall(ModuleModel $model): bool
	{
		
		$noteBook = new NoteBook();
		$noteBook->name = go()->t("Shared");
		$noteBook->setAcl([
			Group::ID_INTERNAL => Acl::LEVEL_DELETE
		]);
		$noteBook->save();

		
		return parent::afterInstall($model);
	}


	protected function beforeInstall(\go\core\model\Module $model): bool
	{
		// Share module with Internal group
		$model->permissions[Group::ID_INTERNAL] = (new \go\core\model\Permission($model))
			->setRights(['mayRead' => true]);

		return parent::beforeInstall($model); // TODO: Change the autogenerated stub
	}
	
	public function defineListeners() {
		User::on(Property::EVENT_MAPPING, static::class, 'onMap');
		User::on(User::EVENT_BEFORE_DELETE, static::class, 'onUserDelete');
		User::on(User::EVENT_BEFORE_SAVE, static::class, 'onUserBeforeSave');
	}
	
	public static function onMap(Mapping $mapping) {
		$mapping->addHasOne('notesSettings', UserSettings::class, ['id' => 'userId'], true);
	}

	public static function onUserDelete(core\db\Query $query) {
		NoteBook::delete(['createdBy' => $query]);
	}

	public static function onUserBeforeSave(User $user)
	{
		if (!$user->isNew() && $user->isModified('displayName')) {
			$oldName = $user->getOldValue('displayName');
			$nb = NoteBook::find()->where(['createdBy' => $user->id, 'name' => $oldName])->single();
			if ($nb) {
				$nb->name = $user->displayName;
				$nb->save();
			}
		}
	}

	private static $demoTexts;

	private static function demoText(Generator $faker) {
		if(!isset(static::$demoTexts )) {
			static::$demoTexts = [];
			for($i = 0; $i < 20; $i++) {
				static::$demoTexts [] = nl2br($faker->realtext);
			}
		}

		return static::$demoTexts [$faker->numberBetween(0, count(static::$demoTexts) - 1 )];

	}

	public function demo(Generator $faker)
	{
		$noteBooks = NoteBook::find();

		foreach($noteBooks as $noteBook) {
			$count = $faker->numberBetween(3, 20);
			for($i = 0; $i < $count; $i++) {
				echo ".";
				$note = new Note();
				$note->name = core\util\StringUtil::cutString(self::demoText($faker), 20, true, "");
				$note->content = self::demoText($faker);
				$note->createdBy = $noteBook->createdBy;
				$note->noteBookId = $noteBook->id;
				$note->createdAt = $faker->dateTimeBetween("-1 years", "now");
				$note->modifiedAt = $faker->dateTimeBetween($note->createdAt, "now");

				if(!$note->save()) {
					throw new core\orm\exception\SaveException($note);
				}

				if(core\model\Module::isInstalled("community", "comments")) {
					\go\modules\community\comments\Module::demoComments($faker, $note);
				}

				core\model\Link::demo($faker, $note);
			}
		}

	}

}
