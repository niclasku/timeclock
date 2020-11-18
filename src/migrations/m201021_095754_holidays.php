<?php

use yii\db\Migration;

/**
 * Class m201021_095754_holidays
 */
class m201021_095754_holidays extends Migration
{
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        $this->delete('{{%holiday}}');
        $this->addColumn('{{%holiday}}', 'name', $this->string());
    }

    public function down()
    {
        $this->dropColumn('{{%holiday}}', 'name');
        return true;
    }
}
