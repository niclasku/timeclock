<?php

declare(strict_types=1);

namespace app\controllers;

use app\base\BaseController;
use app\models\Clock;
use app\models\ClockForm;
use app\models\Holiday;
use app\models\Off;
use app\models\OffForm;
use app\models\Project;
use app\models\User;
use DateTime;
use DateTimeZone;
use Exception;
use Throwable;
use Yii;
use yii\db\Expression;
use yii\db\Query;
use yii\db\StaleObjectException;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Response;

use function array_merge;
use function date;
use function is_numeric;

/**
 * Class ClockController
 * @package app\controllers
 */
class ClockController extends BaseController
{
    /**
     * @return array
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'off-delete' => ['post'],
                    'start' => ['post'],
                    'stop' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function remember(): array
    {
        return array_merge(
            parent::remember(),
            [
                'history',
                'calendar',
                'projects',
                'edit',
                'add',
                'off-add',
                'off-edit',
                'vacations',
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (Yii::$app->user->identity->role === User::ROLE_EMPLOYEE) {
            switch($action->id) {
                case 'edit':
                    if (!Yii::$app->params['employeeSessionEdit']) {
                        return false;
                    }
                break;
                case 'delete':
                    if (!Yii::$app->params['employeeSessionDelete']) {
                        return false;
                    }
                    break;
                case 'off-edit':
                    if (!Yii::$app->params['employeeOffTimeEdit']) {
                        return false;
                    }
                    break;
                case 'off-delete':
                    if (!Yii::$app->params['employeeOffTimeDelete']) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * @return Response
     */
    public function actionStart(): Response
    {
        $clock = new Clock();

        if (!$clock->start()) {
            Yii::$app->alert->danger(Yii::t('app', 'Error while starting session.'));
        }

        return $this->redirect(['site/index']);
    }

    /**
     * @return Response
     */
    public function actionStop(): Response
    {
        $clock = Clock::session();

        if ($clock === null) {
            Yii::$app->alert->danger(Yii::t('app', 'Can not find any started session.'));
        } elseif (!$clock->stop()) {
            Yii::$app->alert->danger(Yii::t('app', 'Error while ending session.'));
        }

        return $this->redirect(['site/index']);
    }

    /**
     * @param string|int|null $month
     * @param string|int|null $year
     * @return array
     */
    public function getMonthsAndYears($month, $year): array
    {
        if (!is_numeric($month) || $month < 1 || $month > 12) {
            $month = date('n');
        }
        if (!is_numeric($year) || $year < 2018) {
            $year = date('Y');
        }

        $month = (int)$month;
        $year = (int)$year;

        $previousYear = $year;
        $previousMonth = $month - 1;

        if ($previousMonth === 0) {
            $previousMonth = 12;
            $previousYear--;
        }

        $nextYear = $year;
        $nextMonth = $month + 1;

        if ($nextMonth === 13) {
            $nextMonth = 1;
            $nextYear++;
        }

        return [$month, $year, $previousMonth, $previousYear, $nextMonth, $nextYear];
    }

    /**
     * @param string|int|null $month
     * @param string|int|null $year
     * @return string
     */
    public function actionHistory($month = null, $year = null): string
    {
        [$month, $year, $previousMonth, $previousYear, $nextMonth, $nextYear] = $this->getMonthsAndYears($month, $year);

        return $this->render(
            'history',
            [
                'months' => Clock::months(),
                'year' => $year,
                'month' => $month,
                'previous' => Clock::months()[$previousMonth],
                'previousYear' => $previousYear,
                'previousMonth' => $previousMonth,
                'next' => Clock::months()[$nextMonth],
                'nextYear' => $nextYear,
                'nextMonth' => $nextMonth,
                'clock' => Clock::find()->where(
                    [
                        'and',
                        [
                            '>=',
                            'clock_in',
                            (int)Yii::$app->formatter->asTimestamp(
                                $year . '-' . ($month < 10 ? '0' : '') . $month . '-01 00:00:00'
                            ),
                        ],
                        [
                            '<',
                            'clock_in',
                            (int)Yii::$app->formatter->asTimestamp(
                                $nextYear . '-' . ($nextMonth < 10 ? '0' : '') . $nextMonth . '-01 00:00:00'
                            ),
                        ],
                        ['user_id' => Yii::$app->user->id],
                    ]
                )->orderBy(['clock_in' => SORT_DESC])->all(),
                'off' => Off::find()->where(
                    [
                        'and',
                        ['<', 'start_at', $nextYear . '-' . ($nextMonth < 10 ? '0' : '') . $nextMonth . '-01'],
                        ['>=', 'end_at', $year . '-' . ($month < 10 ? '0' : '') . $month . '-01'],
                        ['user_id' => Yii::$app->user->id],
                    ]
                )->orderBy(['start_at' => SORT_DESC])->all(),
            ]
        );
    }

    /**
     * @param string|int|null $year
     * @return string
     */
    public function actionVacations($year = null): string
    {
        [$month, $year, $previousMonth, $previousYear, $nextMonth, $nextYear] = $this->getMonthsAndYears(null, $year);

        $months = [];
        foreach (range(1, 12) as $month) {
            $range = Clock::getMonthPeriod($year, $month);
            $months[] = $range;
        }

        $employees = [];
        $start = $year . '-01-01';
        $end = $year . '-12-31';

        $users = User::find()
            ->where(['status' => User::STATUS_ACTIVE])
            ->indexBy('id')
            ->orderBy(['name' => SORT_ASC])
            ->all();

        $range = Clock::getDatePeriod($start, Yii::$app->formatter->asDate($end . ' +1 day', 'yyyy-MM-dd'));

        $holiday = Holiday::getHolidaysYear($year);
        foreach ($users as $user) {
            foreach ($range as $day) {
                $date = Yii::$app->formatter->asDate($day, 'yyyy-MM-dd');
                $employees[$user->name][$date]['user_id'] = $user->id;
                $employees[$user->name][$date]['off'] = false;
                $employees[$user->name][$date]['holiday'] = false;
                if (in_array((int)$day->format('N'), Yii::$app->params['weekendDays'])) {
                    $employees[$user->name][$date]['holiday'] = 2;
                }
            }
            foreach ($holiday as $item) {
                $date = Yii::$app->formatter->asDate($item->year . '-' . $item->month . '-' . $item->day, 'yyyy-MM-dd');
                $employees[$user->name][$date]['holiday'] = 1;
            }
        }

        $off = Off::find()->where(
            [
                'or',
                [
                    'and',
                    ['>=', 'start_at', $start],
                    ['<=', 'start_at', $end],
                ],
                [
                    'and',
                    ['>=', 'end_at', $start],
                    ['<=', 'end_at', $end],
                ],
                [
                    'and',
                    ['<=', 'start_at', $start],
                    ['>=', 'end_at', $end],
                ],
            ])
            ->all();

        foreach ($off as $offPeriod) {
            $first = Yii::$app->formatter->asTimestamp($offPeriod->start_at) > Yii::$app->formatter->asTimestamp($start) ?
                $offPeriod->start_at : $start;

            $last = Yii::$app->formatter->asTimestamp($offPeriod->end_at) < Yii::$app->formatter->asTimestamp($end) ?
                Yii::$app->formatter->asDate($offPeriod->end_at . ' +1 day', 'yyyy-MM-dd') : $end;

            $period = Clock::getDatePeriod($first, $last);
            foreach($period as $day) {
                $date = Yii::$app->formatter->asDate($day, 'yyyy-MM-dd');
                $employees[$offPeriod->user->name][$date]['id'] = $offPeriod->id;
                $employees[$offPeriod->user->name][$date]['off'] = true;
                if ($offPeriod->approved === 1) {
                    $employees[$offPeriod->user->name][$date]['off'] = 1;
                }
            }
        }

        return $this->render(
            'vacations',
            [
                'months' => $months,
                'employees' => $employees,
                'year' => $year,
            ]
        );
    }

    /**
     * @param string|int|null $month
     * @param string|int|null $year
     * @return string
     */
    public function actionCalendar($month = null, $year = null): string
    {
        [$month, $year, $previousMonth, $previousYear, $nextMonth, $nextYear] = $this->getMonthsAndYears($month, $year);

        $firstDayInMonth = date(
            'N',
            (int)Yii::$app->formatter->asTimestamp(
                $year . '-' . ($month < 10 ? '0' : '') . $month . '-01 12:00:00'
            )
        );
        $daysInMonth = (int)date(
            't',
            (int)Yii::$app->formatter->asTimestamp(
                $year . '-' . ($month < 10 ? '0' : '') . $month . '-01 12:00:00'
            )
        );

        return $this->render(
            'calendar',
            [
                'months' => Clock::months(),
                'year' => $year,
                'month' => $month,
                'previous' => Clock::months()[$previousMonth],
                'previousYear' => $previousYear,
                'previousMonth' => $previousMonth,
                'next' => Clock::months()[$nextMonth],
                'nextYear' => $nextYear,
                'nextMonth' => $nextMonth,
                'firstDayInMonth' => $firstDayInMonth,
                'daysInMonth' => $daysInMonth,
                'clock' => Clock::find()->where(
                    [
                        'and',
                        [
                            '>=',
                            'clock_in',
                            (int)Yii::$app->formatter->asTimestamp(
                                $year . '-' . ($month < 10 ? '0' : '') . $month . '-01 00:00:00'
                            ),
                        ],
                        [
                            '<',
                            'clock_in',
                            (int)Yii::$app->formatter->asTimestamp(
                                $nextYear . '-' . ($nextMonth < 10 ? '0' : '') . $nextMonth . '-01 00:00:00'
                            ),
                        ],
                        ['user_id' => Yii::$app->user->id],
                    ]
                )->orderBy(['clock_in' => SORT_ASC])->all(),
                'holidays' => Holiday::getHolidayDatesMonth($month, $year),
                'off' => Off::find()->where(
                    [
                        'and',
                        ['<', 'start_at', $nextYear . '-' . ($nextMonth < 10 ? '0' : '') . $nextMonth . '-01'],
                        ['>=', 'end_at', $year . '-' . ($month < 10 ? '0' : '') . $month . '-01'],
                        ['user_id' => Yii::$app->user->id],
                    ]
                )->orderBy(['start_at' => SORT_ASC])->all(),
            ]
        );
    }

    /**
     * @param string|int $id
     * @param bool $stay
     * @return Response
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function actionDelete($id, bool $stay = false): Response
    {
        $clock = Clock::find()->where(
            [
                'id' => (int)$id,
                'user_id' => Yii::$app->user->id,
            ]
        )->one();

        if ($clock === null) {
            Yii::$app->alert->danger(Yii::t('app', 'Can not find session of given ID.'));
        } elseif (!$clock->delete()) {
            Yii::$app->alert->danger(Yii::t('app', 'There was an error while deleting session.'));
        } else {
            Yii::$app->alert->success(Yii::t('app', 'Session has been deleted.'));
        }

        return $this->goBack(null, $stay);
    }

    /**
     * @param string|int $id
     * @return string|Response
     * @throws Exception
     */
    public function actionEdit($id)
    {
        $session = Clock::find()->where(
            [
                'id' => (int)$id,
                'user_id' => Yii::$app->user->id,
            ]
        )->one();

        if ($session === null) {
            Yii::$app->alert->danger(Yii::t('app', 'Can not find session of given ID.'));

            return $this->goBack();
        }

        $model = new ClockForm($session);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->alert->success(Yii::t('app', 'Session has been saved.'));

            return $this->goBack();
        }

        return $this->render(
            'edit',
            [
                'session' => $session,
                'model' => $model,
                'projects' => ['' => Yii::t('app', '-- no project --')] + Yii::$app->user->identity->assignedProjects,
            ]
        );
    }

    /**
     * @param string|int|null $month
     * @param string|int|null $year
     * @param string|int|null $day
     * @return string|Response
     * @throws Exception
     */
    public function actionAdd($month = null, $year = null, $day = null)
    {
        if (!is_numeric($month) || $month < 1 || $month > 12) {
            $month = date('n');
        }
        if (!is_numeric($year) || $year < 2018) {
            $year = date('Y');
        }
        if (!is_numeric($day) || $day < 1 || $day > 31) {
            $day = date('j');
        }

        $model = new ClockForm(
            new Clock(
                [
                    'project_id' => Yii::$app->user->identity->project_id,
                    'clock_in' => (new DateTime(
                        $year . '-' . ($month < 10 ? '0' : '') . $month . '-' . ($day < 10 ? '0' : '') . $day . date(
                            ' H:i:s'
                        ),
                        new DateTimeZone(Yii::$app->timeZone)
                    )
                    )->getTimestamp(),
                ]
            )
        );
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->alert->success(Yii::t('app', 'Session has been saved.'));

            return $this->goBack();
        }

        return $this->render(
            'add',
            [
                'model' => $model,
                'projects' => ['' => Yii::t('app', '-- no project --')] + Yii::$app->user->identity->assignedProjects,
            ]
        );
    }

    /**
     * @param string|int|null $month
     * @param string|int|null $year
     * @param string|int|null $day
     * @return string|Response
     * @throws Exception
     */
    public function actionOffAdd($month = null, $year = null, $day = null)
    {
        if (!is_numeric($month) || $month < 1 || $month > 12) {
            $month = date('n');
        }
        if (!is_numeric($year) || $year < 2018) {
            $year = date('Y');
        }
        if (!is_numeric($day) || $day < 1 || $day > 31) {
            $day = date('j');
        }

        $model = new OffForm(
            new Off(
                [
                    'start_at' => $year . '-' . ($month < 10 ? '0' : '') . $month . '-' . ($day < 10 ? '0' : '') . $day,
                    'end_at' => $year . '-' . ($month < 10 ? '0' : '') . $month . '-' . ($day < 10 ? '0' : '') . $day,
                ]
            )
        );
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->alert->success(Yii::t('app', 'Off-time has been saved.'));

            return $this->goBack();
        }

        return $this->render(
            'off-add',
            [
                'model' => $model,
                'marked' => Off::getFutureOffDays(),
            ]
        );
    }

    /**
     * @param string|int $id
     * @return string|Response
     * @throws Exception
     */
    public function actionOffEdit($id)
    {
        $off = Off::find()->where(
            [
                'id' => (int)$id,
                'user_id' => Yii::$app->user->id,
            ]
        )->one();

        if ($off === null) {
            Yii::$app->alert->danger(Yii::t('app', 'Can not find off-time of given ID.'));

            return $this->goBack();
        }

        $model = new OffForm($off);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->alert->success(Yii::t('app', 'Off-time has been saved.'));

            return $this->goBack();
        }

        return $this->render(
            'off-edit',
            [
                'off' => $off,
                'model' => $model,
                'marked' => Off::getFutureOffDays($off->id),
            ]
        );
    }

    /**
     * @param string|int $id
     * @return Response
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function actionOffDelete($id): Response
    {
        $off = Off::find()->where(
            [
                'id' => (int)$id,
                'user_id' => Yii::$app->user->id,
            ]
        )->one();

        if ($off === null) {
            Yii::$app->alert->danger(Yii::t('app', 'Can not find off-time of given ID.'));
        } elseif (Yii::$app->user->identity->role === User::ROLE_EMPLOYEE &&
            ($off->approved === 1 && !Yii::$app->params['employeeOffTimeApprovedDelete'])) {
                Yii::$app->alert->danger(Yii::t('app', 'You are not allowed to delete approved off-times.'));
        } elseif (!$off->delete()) {
            Yii::$app->alert->danger(Yii::t('app', 'There was an error while deleting off-time.'));
        } else {
            Yii::$app->alert->success(Yii::t('app', 'Off-time has been deleted.'));
        }

        return $this->goBack();
    }

    /**
     * @param string|int|null $month
     * @param string|int|null $year
     * @return string
     */
    public function actionProjects($month = null, $year = null): string
    {
        [$month, $year, $previousMonth, $previousYear, $nextMonth, $nextYear] = $this->getMonthsAndYears($month, $year);

        $projects = [];
        $systemProjects = Project::find()->all();
        foreach ($systemProjects as $p) {
            $projects[$p->id] = [
                'name' => $p->name,
                'color' => $p->color,
            ];
        }

        $projectSessions = (new Query())
            ->from(Clock::tableName())
            ->select(
                [
                    'project_id',
                    new Expression('SUM(clock_out - clock_in) time'),
                ]
            )
            ->where(
                [
                    'and',
                    [
                        '>=',
                        'clock_in',
                        (int)Yii::$app->formatter->asTimestamp(
                            $year . '-' . ($month < 10 ? '0' : '') . $month . '-01 00:00:00'
                        ),
                    ],
                    [
                        '<',
                        'clock_in',
                        (int)Yii::$app->formatter->asTimestamp(
                            $nextYear . '-' . ($nextMonth < 10 ? '0' : '') . $nextMonth . '-01 00:00:00'
                        ),
                    ],
                    ['user_id' => Yii::$app->user->id],
                    ['is not', 'clock_out', null],
                    ['is not', 'project_id', null],
                ]
            )
            ->groupBy(['project_id'])
            ->orderBy(['time' => SORT_DESC])
            ->all();

        return $this->render(
            'projects',
            [
                'months' => Clock::months(),
                'year' => $year,
                'month' => $month,
                'previous' => Clock::months()[$previousMonth],
                'previousYear' => $previousYear,
                'previousMonth' => $previousMonth,
                'next' => Clock::months()[$nextMonth],
                'nextYear' => $nextYear,
                'nextMonth' => $nextMonth,
                'projects' => $projects,
                'time' => $projectSessions,
            ]
        );
    }
}
