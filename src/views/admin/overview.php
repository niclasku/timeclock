<?php

/**
 * @var $this yii\web\View
 * @var $days array
 * @var $employee User
 * @var $user User
 * @var $users array
 * @var $months array
 * @var $month int
 * @var $year int
 * @var $previousMonth int
 * @var $previousYear int
 * @var $nextMonth int
 * @var $nextYear int
 * @var $previous string
 * @var $next string
 */

use app\models\Clock;
use app\models\Off;
use app\models\User;
use app\widgets\fontawesome\FA;
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = Yii::t('app', 'Overview');

function totalWork($day) {
    $sum = 0;
    if (array_key_exists('clock', $day)){
        foreach($day['clock'] as $clock) {
            $sum += $clock->clock_out - $clock->clock_in;
        }
    }
    return $sum;
}

function totalBreak($day) {
    $sum = 0;
    if (array_key_exists('clock', $day)){
        $last_out = 0;
        foreach($day['clock'] as $clock) {
            if ($last_out !== 0) {
                $sum += $clock->clock_in - $last_out;
            }
            $last_out = $clock->clock_out;
        }
    }
    return $sum;
}

$totalWorked = 0;
$totalBreaks = 0;
$totalOff = 0;
$totalSick = 0;
$totalVacation = 0;
$totalOther = 0;
foreach ($days as $day) {
    $totalWorked += totalWork($day);
    $totalBreaks += totalBreak($day);
    if (array_key_exists('off', $day)) {
        foreach($day['off'] as $off) {
            $totalOff += 1;
            if ($off->type === Off::TYPE_VACATION) {
                $totalVacation += 1;
            } elseif ($off->type === Off::TYPE_SICK) {
                $totalSick += 1;
            } else {
                $totalOther += 1;
            }
        }
    }
}

?>
<div class="form-group">
    <h1><?= Yii::t('app', 'Overview') ?></h1>
</div>
<div class="row">
    <div class="col-lg-3">
        <div class="form-group">
            <?= Yii::t('app', 'Month') ?>:
        </div>
        <?= Html::beginForm(['admin/overview'], 'get') ?>
        <?= Html::hiddenInput('id', $employee !== null ? $employee->id : null) ?>
        <div class="form-group">
            <?= Html::dropDownList('month', $month, $months, ['class' => 'form-control custom-select']) ?>
        </div>
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group">
                    <?= Html::textInput('year', $year, ['class' => 'form-control']) ?>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-group">
                    <?= Html::submitButton(FA::icon('play'), ['class' => 'btn btn-warning btn-block']) ?>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="form-group btn-group btn-block months" role="group">
                    <?= Html::a(
                        FA::icon('step-backward') . $previous,
                        ['overview', 'month' => $previousMonth, 'year' => $previousYear, 'id' => $employee !== null ? $employee->id : null],
                        ['class' => 'btn btn-primary']
                    ) ?><?= Html::a(
                        FA::icon('step-forward') . $next,
                        ['overview', 'month' => $nextMonth, 'year' => $nextYear, 'id' => $employee !== null ? $employee->id : null],
                        ['class' => 'btn btn-primary']
                    ) ?>
                </div>
            </div>
        </div>
        <?= Html::endForm() ?>
        <div class="form-group mb-5">
            <div class="list-group">
                <?php foreach ($users as $user): ?>
                    <a href="<?= Url::to(['overview', 'month' => $month, 'year' => $year, 'id' => $user->id]) ?>"
                       class="list-group-item <?= $employee !== null && $employee->id === $user->id ? 'active' : '' ?>">
                        <?= Html::encode($user->name) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="row" style="margin-bottom: 20px">
            <div class="col-lg-2">
               <h4>
                   <?= $employee->name ?>
               </h4>
            </div>
            <div class="col-lg-5">
                <table class="table">
                    <tr>
                        <td><?= Yii::t('app', 'Working Time') ?>:</td>
                        <td><?= Yii::$app->formatter->asTime($totalWorked) ?> h</td>
                    </tr>
                    <tr>
                        <td><?= Yii::t('app', 'Breaks') ?>:</td>
                        <td><?= Yii::$app->formatter->asTime($totalBreaks) ?> h</td>
                    </tr>
                    <tr>
                        <td><?= Yii::t('app', 'Off-time') ?>:</td>
                        <td><?= $totalOff . ' ' . Yii::t('app', 'Days') ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-lg-5">
                <table class="table">
                    <tr>
                        <td><?= Off::names()[2] ?>:</td>
                        <td><?= $totalSick . ' ' . Yii::t('app', 'Days') ?></td>
                    </tr>
                    <tr>
                        <td><?= Off::names()[1] ?>:</td>
                        <td><?= $totalVacation . ' ' . Yii::t('app', 'Days') ?></td>
                    </tr>
                    <tr>
                        <td><?= Off::names()[0] ?>:</td>
                        <td><?= $totalOther . ' ' . Yii::t('app', 'Days') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="row form-group">
            <div class="col-lg-12">
                <?php if (Yii::$app->params['adminSessionAdd']): ?>
                    <a href="<?= Url::to(['admin/add']) ?>" class="btn btn-warning btn-sm float-right ml-1">
                        <?= FA::icon('plus') ?> <?= Yii::t('app', 'Add Session') ?>
                    </a>
                <?php endif; ?>
                <?php if (Yii::$app->params['adminOffTimeAdd']): ?>
                    <a href="<?= Url::to(['admin/add-off']) ?>" class="btn btn-warning btn-sm float-right ml-1">
                        <?= FA::icon('plus') ?> <?= Yii::t('app', 'Add Off-Time') ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
        <div class="table-responsive" >
            <table class="table table-hover thead-dark">
                <thead class="thead-light">
                <tr>
                    <th scope="col" style="width: 50px"></th>
                    <th scope="col" style="width: 150px"><?= Yii::t('app', 'Date') ?></th>
                    <th scope="col" style="width: 100px"><?= Yii::t('app', 'From') ?></th>
                    <th scope="col" style="width: 100px"><?= Yii::t('app', 'To') ?></th>
                    <th scope="col" style="width: 140px"><?= Yii::t('app', 'Working Time') ?></th>
                    <th scope="col" style="width: 120px"><?= Yii::t('app', 'Breaks') ?></th>
                    <th scope="col"><?= Yii::t('app', 'Notes') ?></th>
                </tr>
                </thead>
                <tbody>
                    <?php foreach($days as $day): ?>
                        <tr>
                            <td><?= substr(Clock::days()[$day['date']->format('N')], 0, 2) ?></td>
                            <td><?= Yii::$app->formatter->asDate($day['date']->format('Y-m-d')) ?></td>
                            <td>
                                <?php if (array_key_exists('clock', $day)): ?>
                                    <?php foreach($day['clock'] as $clock): ?>
                                        <?= Yii::$app->formatter->asTime($clock->clock_in) ?><br>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (array_key_exists('clock', $day)): ?>
                                    <?php foreach($day['clock'] as $clock): ?>
                                        <?= Yii::$app->formatter->asTime($clock->clock_out) ?><br>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= totalWork($day) ? Yii::$app->formatter->asTime(totalWork($day)) . ' h' : '' ?>
                            </td>

                            <td>
                                <?= totalBreak($day) ? Yii::$app->formatter->asTime(totalBreak($day)) . ' h' : '' ?>
                            </td>
                            <td>
                                <?php $first = true ?>
                                <?php if (array_key_exists('holiday', $day)): ?>
                                    <?php foreach($day['holiday'] as $off): ?>
                                        <?php if (!$first): ?>/<?php endif; ?>
                                        <?= $off->name ?>
                                        <?php $first = false ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (array_key_exists('off', $day)): ?>
                                    <?php foreach($day['off'] as $off): ?>
                                        <?php if (!$first): ?>/<?php endif; ?>
                                        <?= Off::names()[$off->type] ?>
                                        <?php $first = false ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (array_key_exists('clock', $day)): ?>
                                    <?php foreach($day['clock'] as $clock): ?>
                                        <?php if (!$first && !empty($clock->note)): ?>/<?php endif; ?>
                                        <?= $clock->note ?>
                                        <?php $first = false ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>
</div>
