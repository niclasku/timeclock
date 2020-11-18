<?= Yii::t('app', 'Hello, {user}', ['user' => $user]) ?>

<?= Yii::t('app', 'Your {type} request ({start} - {end}) has been denied :(', [
    'type' => $type,
    'start' => $start,
    'end' => $end,
]) ?>
