<?php

declare(strict_types=1);

namespace app\assets;

use yii\web\AssetBundle;

/**
 * Class MomentAsset
 * @package app\assets
 */
class MomentAsset extends AssetBundle
{
    /**
     * {@inheritdoc}
     */
    public $sourcePath = '@npm/moment/';

    /**
     * {@inheritdoc}
     */
    public $js = ['moment.js'];

}
