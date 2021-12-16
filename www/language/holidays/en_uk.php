<?php
// holidays with fixed date
$input_holidays['fix']['01-01'] = 'New Year\'s Day';
$input_holidays['fix']['12-25'] = 'Christmas Day';
$input_holidays['fix']['12-26'] = 'Boxing Day';


// holidays with variable date (christian holidays computation is based on the date of easter day)
$input_holidays['var']['-2'] = 'Good Friday';
$input_holidays['var']['0'] = 'Easter Sunday';
$input_holidays['var']['1'] = 'Easter Monday';

$input_holidays['fn'][] = array('New Year\'s Day (substitute)',array('GOHolidaysUK', 'newyear'));
$input_holidays['fn'][] = array('Christmas Day (substitute)',array('GOHolidaysUK', 'christmas'));
$input_holidays['fn'][] = array('Boxing Day (substitute)',array('GOHolidaysUK', 'boxingday'));

$input_holidays['fn'][] = array('Summer bank holiday',array('GOHolidaysUK', 'summerBank'));
$input_holidays['fn'][] = array('Spring bank holiday',array('GOHolidaysUK', 'springBank'));
$input_holidays['fn'][] = array('Early May bank holiday',array('GOHolidaysUK', 'earlyMayBank'));

if (!class_exists('GOHolidaysUK')) {
	class GOHolidaysUK {
		public static function summerBank($year) {
			return (new DateTime('last mon of August '.$year))->format('Y-m-d');
		}

		/**
		 * @throws Exception
		 */
		public static function springBank($year){
			return (new DateTime('last mon of May '.$year))->format('Y-m-d');
		}

		public static function earlyMayBank($year){
			if($year == 2020) {
				return "2020-05-08";
			}
			return (new DateTime('first mon of May '.$year))->format('Y-m-d');
		}

		public static function christmas($year) {
			$date = new DateTime($year . '-12-25');
			return self::substitute($date, 2);
		}

		public static function boxingday($year) {
			$date = new DateTime($year . '-12-26');
			return self::substitute($date, 2);
		}

		public static function newyear($year) {
			$date = new DateTime($year . '-01-01');
			return self::substitute($date);
		}

		private static function substitute(DateTime $date, int $moveDays = null) : ?string {
			$dayOfWeek = $date->format("w");
			if($dayOfWeek == 0 || $dayOfWeek == 6) {
				if(!isset($moveDays)) {
					$moveDays = $dayOfWeek == 6 ? 2 : 1;
				}
				$date->add(new DateInterval("P" . $moveDays . "D"));

				return $date->format("Y-m-d");

			}

			return null;

		}
	}
}
