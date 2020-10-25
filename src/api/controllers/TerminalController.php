<?php

declare(strict_types=1);

namespace app\api\controllers;

use app\api\TerminalAuthentication;
use app\models\Clock;
use app\models\Off;
use app\models\Terminal;
use app\models\User;
use DateTime;
use Yii;
use yii\base\DynamicModel;
use yii\base\InvalidConfigException;
use yii\console\Response;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\rest\ActiveController;
use yii\web\HttpException;

/**
 * Class TerminalController
 * @package app\api\controllers
 */
class TerminalController extends ActiveController
{
    /**
     * @var string
     */
    public $modelClass = Terminal::class;

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => TerminalAuthentication::class,
        ];
        return $behaviors;
    }

    /**
     * {@inheritdoc}
     */
    public function verbs()
    {
        return [
            'in' => ['POST'],
            'out' => ['POST'],
            'working' => ['GET'],
            'summary' => ['GET'],
            'users' => ['GET'],
            'update' => ['GET'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * Clock in given user
     * @return bool
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionIn()
    {
        $params = (new DynamicModel(['user_id']))->addRule(['user_id'], 'required')
            ->addRule(['user_id'], 'exist', ['targetClass' => User::class, 'targetAttribute' => 'id']);
        $params->load(Yii::$app->getRequest()->getBodyParams(), '');
        if (!$params->validate()) {
            throw new HttpException(422, $params);
        }

        $clock = new Clock();
        $now = Clock::roundToFullMinute((int)Yii::$app->formatter->asTimestamp('now'));

        if (Clock::find()->where(['clock_out' => null, 'user_id' => $params['user_id']])->exists()) {
            throw new HttpException(422, 'User not clocked in');
        }

        $clock->clock_in = $now;
        $clock->clock_out = null;
        $clock->user_id = $params['user_id'];

        if (!$clock->validate()) {
            throw new HttpException(422, $clock->errors);
        }

        return $clock->save(false);
    }

    /**
     * Clock out given user
     * @return bool
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionOut()
    {
        $params = (new DynamicModel(['user_id']))->addRule(['user_id'], 'required')
            ->addRule(['user_id'], 'exist', ['targetClass' => User::class, 'targetAttribute' => 'id']);
        $params->load(Yii::$app->getRequest()->getBodyParams(), '');
        if (!$params->validate()) {
            throw new HttpException(422, $params);
        }

        if (!Clock::find()->where(['clock_out' => null, 'user_id' => $params['user_id']])->exists()) {
            throw new HttpException(422, 'Could not find running session');
        }
        $clock = Clock::find()->where(['clock_out' => null, 'user_id' => $params['user_id']])->one();
        $clock->clock_out = Clock::roundToFullMinute((int)Yii::$app->formatter->asTimestamp('now'));
        if (!$clock->validate()) {
            throw new HttpException(422, $clock->errors);
        }
        if ($clock->isAnotherSessionSaved()) {
            throw new HttpException(422, 'Can not end current session because it overlaps with another ended session');
        }

        return $clock->save(false);
    }

    /**
     * Returns running sessions
     * @return array
     */
    public function actionWorking()
    {
        return Clock::find()->where(['clock_out' => null])->orderBy(['user_id' => SORT_ASC])
            ->select(['user_id', 'clock_in'])->all();
    }

    /**
     * Returns work and vacation summary of given user
     * user id, today's work, this week's work, last week's work, approved vacation
     * @return array|DynamicModel
     * @throws InvalidConfigException
     */
    public function actionSummary()
    {
        $params = (new DynamicModel(['user_id']))->addRule(['user_id'], 'required')
            ->addRule(['user_id'], 'exist', ['targetClass' => User::class, 'targetAttribute' => 'id']);
        $params->load(Yii::$app->getRequest()->getBodyParams(), '');
        if (!$params->validate()) {
            return $params;
        }

        $vacationDays = 0;
        $vacations = Off::find()
            ->where(['and', ['user_id' => $params['user_id']], ['type' => Off::TYPE_VACATION], ['approved' => 1]])
            ->all();
        foreach ($vacations as $vacation) {
            $vacationDays += $vacation->getWorkDaysOfOffPeriod();
        }

        $today = (new DateTime)->setTime(0, 0);
        $tomorrow = (clone $today)->modify('+1 day');
        $weekMon = (clone $today)->modify('this week');
        $nextWeekMon = (clone $today)->modify('this week +7 days');
        $lastWeekMon = (clone $today)->modify('last week');

        $today = (new Query())->from(Clock::tableName())
            ->select(['SUM(clock_out - clock_in) sum',])
            ->where(
                [
                    'and',
                    ['user_id' => $params['user_id']],
                    ['is not', 'clock_out', null],
                    ['>=', 'clock_in', $today->getTimestamp()],
                    ['<', 'clock_out', $tomorrow->getTimestamp()],
                ]
            )->one();

        $thisWeek = (new Query())->from(Clock::tableName())
            ->select(['SUM(clock_out - clock_in) sum',])
            ->where(
                [
                    'and',
                    ['user_id' => $params['user_id']],
                    ['is not', 'clock_out', null],
                    ['>=', 'clock_in', $weekMon->getTimestamp()],
                    ['<', 'clock_out', $nextWeekMon->getTimestamp()],
                ]
            )->one();

        $lastWeek = (new Query())->from(Clock::tableName())
            ->select(['SUM(clock_out - clock_in) sum',])
            ->where(
                [
                    'and',
                    ['user_id' => $params['user_id']],
                    ['is not', 'clock_out', null],
                    ['>=', 'clock_in', $lastWeekMon->getTimestamp()],
                    ['<', 'clock_out', $weekMon->getTimestamp()],
                ]
            )->one();

        return [
            'today' => (int)$today['sum'] ?? 0,
            'this_week' => (int)$thisWeek['sum'] ?? 0,
            'last_week' => (int)$lastWeek['sum'] ?? 0,
            'vacation' => $vacationDays ?? 0,
        ];
    }

    /**
     * Returns user data
     * id, name, tag id, picture
     * @return array|ActiveRecord[]
     */
    public function actionUsers()
    {
        return User::find()->where([
            'and',
            ['status' => User::STATUS_ACTIVE],
            ['is not', 'tag', null],
        ])->select(['id', 'name', 'tag', 'image'])->all();
    }

    /**
     * Returns timestamp of latest user update
     * @return array|ActiveRecord|null
     */
    public function actionUpdate()
    {
        return User::find()->where(['status' => User::STATUS_ACTIVE])->orderBy(['updated_at' => SORT_DESC])
            ->select(['updated_at'])->one();
    }

    /**
     * Returns image for given user
     * @return Response|\yii\web\Response
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionImage()
    {
        $params = (new DynamicModel(['user_id']))->addRule(['user_id'], 'required')
            ->addRule(['user_id'], 'exist', ['targetClass' => User::class, 'targetAttribute' => 'id']);
        $params->load(Yii::$app->getRequest()->getBodyParams(), '');
        if (!$params->validate()) {
            throw new HttpException(422, $params);
        }

        $user = User::findOne(['id' => $params['user_id']]);
        if (empty($user)) {
            throw new HttpException(422, 'Could not find user');
        } elseif (!$user->image) {
            throw new HttpException(422, 'User has no image');
        }

        return Yii::$app->response->sendFile(Yii::$app->params['uploadPath'] . $user->image);
    }
}
