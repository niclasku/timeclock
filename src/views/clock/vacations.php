<?php

/**
 * @var $this yii\web\View
 * @var $year int
 * @var $vacations array
 */

use app\models\Clock;
use app\widgets\fontawesome\FA;
use yii\helpers\Url;

$this->title = Yii::t('app', 'Vacation');

?>
<div class="form-group">
    <h1><?= Yii::t('app', 'Vacation') ?></h1>
</div>
<div class="row" style="margin-bottom: 20px">
    <div class="col-sm-1 text-center align-self-center">
        <a href="<?= Url::to(['vacations', 'year' => $year - 1]) ?>" class="btn btn-warning">
            <?= FA::icon('step-backward') ?>
        </a>
    </div>
    <div class="col-sm-1 text-center align-self-center">
        <h5 style="margin-bottom: 0"><?= $year ?></h5>
    </div>
    <div class="col-sm-1 text-center align-self-center">
        <a href="<?= Url::to(['vacations', 'year' => $year + 1]) ?>" class="btn btn-warning">
            <?= FA::icon('step-forward') ?>
        </a>
    </div>
    <div class="col-sm-1"></div>
    <div class="col-sm-2 align-self-center">
        <div class="bg-primary text-center align-self-center" style="margin-left:auto; margin-right: auto; padding: 2px">
            <?= Yii::t('app', 'Holiday') ?>
        </div>
    </div>
    <div class="col-sm-2 align-self-center">
        <div class="bg-success text-center align-self-center" style="margin-left:auto; margin-right: auto; padding: 2px">
            <?= Yii::t('app', 'Vacation') ?>
        </div>
    </div>
    <div class="col-sm-1"></div>
    <div class="col-sm-3 text-center align-self-center">
        <a href="<?= Url::to(['clock/off-add']) ?>"
           class="btn btn-warning btn-sm float-right ml-1"><?= FA::icon('plus') ?>
            <?= Yii::t('app', 'Add Off-Time') ?></a>
    </div>
</div>

<?php foreach ($vacations as $month): ?>
    <div class="row">
        <div class="table-responsive">
            <table class="table table-bordered table-sm table-active">
                <thead class="thead-light">
                <tr>
                    <th class="align-text-top" scope="col"
                        style=""><?= Clock::months()[$month['range']->start->format('n')] ?></th>
                    <?php $count = 0 ?>
                    <?php foreach ($month['range'] as $date): ?>
                        <?php $count += 1 ?>
                        <th scope="col" style="width:35px;">
                            <?= $date->format('d') ?>. <br>
                            <?= substr(Clock::days()[$date->format('N')], 0, 2) ?>
                        </th>
                    <?php endforeach; ?>
                    <?php if ($count !== 31): ?>
                        <?php foreach (range(1, 31 - $count) as $n): ?>
                            <th scope="col" style="width:35px;"></th>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                    <?php foreach ($month['employees'] as $employee): ?>
                        <tr>
                            <th scope="row"><?= $employee['name'] ?></th>
                            <?php foreach ($employee['day'] as $day): ?>
                                <?php if ($day['holiday']): ?>
                                    <?php if ($day['holiday'] === 1): ?>
                                        <td class="bg-primary"></td>
                                    <?php else: ?>
                                        <td class="table-primary"></td>
                                    <?php endif; ?>
                                <?php elseif ($day['off']): ?>
                                    <td
                                    <?php if ($day['off']->approved === 1): ?>
                                        class="bg-success">
                                    <?php else: ?>
                                        class="table-success">
                                    <?php endif; ?>
                                        <a href=<?= Url::to(['clock/off-edit', 'id' => $day['off']->id]) ?> >
                                            <div style="height:100%; width:100%"><br></div>
                                        </a>
                                    </td>
                                <?php else: ?>
                                    <td class="table-active"></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>