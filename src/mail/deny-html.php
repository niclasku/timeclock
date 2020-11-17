<p><?= Yii::t('app', 'Hello, {user}', ['user' => $user]) ?></p>
<p><?= Yii::t('app', 'Your {type} request ({start} - {end}) has been denied :(', [
    'type' => $type,
    'start' => $start,
    'end' => $end,
]) ?></p>
