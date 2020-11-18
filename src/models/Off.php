<?php

declare(strict_types=1);

namespace app\models;

use Exception;
use Yii;
use yii\base\InvalidConfigException;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

use function date;
use function in_array;

/**
 * Off model
 *
 * @property int $id
 * @property int $user_id
 * @property string $start_at
 * @property string $end_at
 * @property int $type
 * @property int $approved
 * @property string $note
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $user
 */
class Off extends ActiveRecord implements NoteInterface
{
    public const TYPE_SHORT = 0;
    public const TYPE_VACATION = 1;
    public const TYPE_SICK = 2;
    public const TYPES = [self::TYPE_SHORT, self::TYPE_VACATION, self::TYPE_SICK];

    /**
     * @return array
     */
    public static function names(): array
    {
        return [
            0 => Yii::t('app', 'Other'),
            1 => Yii::t('app', 'Vacation'),
            2 => Yii::t('app', 'Sick Leave'),
        ];
    }

    /**
     * @return array
     */
    public static function icons(): array
    {
        return [
            0 => 'slash',
            1 => 'plane',
            2 => 'medkit',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%off}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            [['type'], 'default', 'value' => self::TYPE_SHORT],
            [['user_id', 'start_at', 'end_at', 'type'], 'required'],
            [['type'], 'in', 'range' => self::TYPES],
            [['user_id'], 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
            [['end_at', 'start_at'], 'date', 'format' => 'yyyy-MM-dd'],
            [['note'], 'string'],
        ];
    }

    /**
     * @return string|null
     */
    public function getNote(): ?string
    {
        return !empty($this->note) ? $this->note : null;
    }

    /**
     * @return ActiveQuery
     */
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Returns number of work days in the period of off-time.
     * Weekends and holidays are excluded.
     * @return int
     */
    public function getWorkDaysOfOffPeriod(): int
    {
        $marker = (int)Yii::$app->formatter->asTimestamp($this->start_at . ' 12:00:00');

        $month = null;
        $holidays = [];
        $workDays = 0;

        while ($marker <= (int)Yii::$app->formatter->asTimestamp($this->end_at . ' 12:00:00')) {
            $currentMonth = (int)date('n', $marker);
            if ($currentMonth !== $month) {
                $month = $currentMonth;
                $holidays = Holiday::getMonthHolidays($month, (int)date('Y'));
            }

            if (!in_array((int)date('j', $marker), $holidays, true)
                && !in_array((int)date('N', $marker), Yii::$app->params['weekendDays'], true)) {
                $workDays++;
            }

            $marker += 24 * 60 * 60;
        }

        return $workDays;
    }

    /**
     * @param int|null $except
     * @param int|null $user_id
     * @return array
     * @throws Exception
     */
    public static function getFutureOffDays(?int $except = null, ?int $user_id = null): array
    {
        if ($user_id === null) {
            $user_id = Yii::$app->user->id;
        }

        $offs = static::find()->where(
            [
                'and',
                ['user_id' => $user_id],
                ['>=', 'end_at', Yii::$app->formatter->asDate('now', 'yyyy-MM-dd')],
            ]
        );

        if ($except !== null) {
            $offs->andWhere(['<>', 'id', $except]);
        }

        $taken = [];

        /* @var $off Off */
        foreach ($offs->each() as $off) {
            $marker = (int)Yii::$app->formatter->asTimestamp($off->start_at . ' 12:00:00');

            while ($marker <= (int)Yii::$app->formatter->asTimestamp($off->end_at . ' 12:00:00')) {
                $taken[] = Yii::$app->formatter->asDate($marker, 'yyyy-MM-dd');
                $marker += 24 * 60 * 60;
            }
        }

        return $taken;
    }

    /**
     * @param Off $off
     */
    public static function sendInfoToApplicant(Off $off): void
    {
        $type = self::names()[$off->type];
        $result = $off->approved;
        $template = null;

        if ($result === 1) {
            $template = 'approve';
            $subject = Yii::t('app', 'Off-time has been approved.');
        } elseif ($result === 2) {
            $template = 'deny';
            $subject = Yii::t('app', 'Off-time has been denied.');
        }

        if ($template !== null) {
            $mail = Yii::$app->mailer->compose(
                [
                    'html' => $template . '-html',
                    'text' => $template . '-text',
                ],
                [
                    'user' => $off->user->name,
                    'type' => $type,
                    'start' => $off->start_at,
                    'end' => $off->end_at,
                ]
            )
                ->setFrom(Yii::$app->params['email'])
                ->setTo([$off->user->email => $off->user->name])
                ->setSubject($subject);

            if (!$mail->send()) {
                Yii::error('Error while sending mail to applicant');
            }
        }
    }

    /**
     * @param Off $off
     * @throws InvalidConfigException
     */
    public static function sendInfoToAdmin(Off $off): void
    {
        $type = self::names()[$off->type];

        if (in_array($off->type, Yii::$app->params['approvableOffTime'], true)) {
            $admins = ArrayHelper::map(
                User::find()->where(
                    [
                        'status' => User::STATUS_ACTIVE,
                        'role' => User::ROLE_ADMIN,
                    ]
                )->all(),
                'email',
                'name'
            );

            if ($admins) {
                $mail = Yii::$app->mailer->compose(
                    [
                        'html' => 'request-html',
                        'text' => 'request-text',
                    ],
                    [
                        'user' => $off->user->name,
                        'type' => $type,
                        'start' => $off->start_at,
                        'end' => $off->end_at,
                        'link' => Url::to(
                            [
                                'admin/off',
                                'month' => Yii::$app->formatter->asDate($off->start_at, 'M'),
                                'year' => Yii::$app->formatter->asDate($off->start_at, 'yyyy'),
                            ],
                            true
                        ),
                    ]
                )
                    ->setFrom(Yii::$app->params['email'])
                    ->setTo($admins)
                    ->setSubject(Yii::t('app', 'New Request'));

                if (!$mail->send()) {
                    Yii::error('Error while sending mail to applicant');
                }
            }
        }
    }

    /**
     * Returns next incoming current user's vacation that is not denied
     * @return Off|null
     * @throws InvalidConfigException
     */
    public static function getNextVacation(): ?Off
    {
        return static::find()->where(
            [
                'and',
                [
                    'user_id' => Yii::$app->user->id,
                    'type' => self::TYPE_VACATION,
                ],
                ['<>', 'approved', 2],
                ['>=', 'start_at', Yii::$app->formatter->asDate('now', 'yyyy-MM-dd')],
            ]
        )->orderBy(['start_at' => SORT_ASC])->one();
    }

    /**
     * Returns all accepted current user's vacations during the current year.
     * @return int
     * @throws InvalidConfigException
     */
    public static function getVacationDaysInYear(): int
    {
        $days = 0;

        $allInYear = static::find()->where(
            [
                'and',
                [
                    'user_id' => Yii::$app->user->id,
                    'type' => self::TYPE_VACATION,
                    'approved' => 1,
                ],
                ['<=', 'start_at', date('Y-12-31')],
                ['>=', 'end_at', date('Y-01-01')],
            ]
        )->orderBy(['start_at' => SORT_ASC]);

        /* @var $off Off */
        foreach ($allInYear->each() as $off) {
            if (Yii::$app->formatter->asDate($off->start_at . ' 12:00:00', 'yyyyMMdd') < date('Y0101')) {
                $off->start_at = date('Y-01-01');
            }
            if (Yii::$app->formatter->asDate($off->end_at . ' 12:00:00', 'yyyyMMdd') > date('Y1231')) {
                $off->end_at = date('Y-12-31');
            }

            $days += $off->getWorkDaysOfOffPeriod();
        }

        return $days;
    }
}
