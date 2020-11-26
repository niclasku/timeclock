<?php

declare(strict_types=1);

namespace app\models;

use Throwable;
use Yii;
use yii\db\ActiveRecord;
use  yii\helpers\Json;

use function count;
use function explode;
use function file_get_contents;
use function preg_match_all;

/**
 * Holiday model
 *
 * @property int $year
 * @property int $month
 * @property int $day
 * @property string $name
 */
class Holiday extends ActiveRecord
{
    /**
     * @var string
     */
    public static $calendarUrl = 'https://feiertage-api.de/api/?jahr=';

    /**
     * @var string
     */
    public static $calendarParams = '&nur_land=SH';

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%holiday}}';
    }

    /**
     * @param int $year
     * @return array
     */
    public static function readHolidays(int $year): array
    {
        $holidays = [];
        $calendar = file_get_contents(static::$calendarUrl . $year . static::$calendarParams);
        $data = Json::decode($calendar);
        foreach (array_keys($data) as $k) {
            $holidays[$k] = $data[$k]['datum'];
        }
        return $holidays;
    }

    /**
     * @param int $year
     * @return bool
     */
    public static function isYearPopulated(int $year): bool
    {
        return static::find()->where(['year' => $year])->exists();
    }

    /**
     * @param int $month
     * @param int $year
     * @return array
     */
    public static function getHolidayDatesMonth(int $month, int $year): array
    {
        $holidays = static::getHolidaysMonth($month, $year);

        $days = [];
        foreach ($holidays as $holiday) {
            $days[] = $holiday->day;
        }
        return $days;
    }

    /**
     * @param int $month
     * @param int $year
     * @return array
     */
    public static function getHolidaysMonth(int $month, int $year): array
    {
        if (!static::isYearPopulated($year)) {
            static::populateYear($year);
        }
        return static::find()->where(
            [
                'month' => $month,
                'year' => $year,
            ]
        )->all();
    }

    /**
     * @param int $year
     * @return array
     */
    public static function getHolidaysYear(int $year): array
    {
        if (!static::isYearPopulated($year)) {
            static::populateYear($year);
        }
        return static::find()->where(['year' => $year])->all();
    }

    /**
     * @param int $year
     */
    public static function populateYear(int $year): void
    {
        try {
            $holidays = static::readHolidays($year);

            foreach ($holidays as $key => $holiday) {
                $date = explode('-', $holiday);
                if (count($date) === 3 && !static::find()->where(
                        [
                            'year' => (int)$date[0],
                            'month' => (int)$date[1],
                            'day' => (int)$date[2],
                            'name' => $key,
                        ]
                    )->exists()) {
                    $day = new static();

                    $day->year = (int)$date[0];
                    $day->month = (int)$date[1];
                    $day->day = (int)$date[2];
                    $day->name = $key;

                    $day->save();
                }
            }
        } catch (Throwable $exception) {
            Yii::error($exception);
        }
    }
}
