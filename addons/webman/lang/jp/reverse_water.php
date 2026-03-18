<?php

return [
    'title' => '反水奨励記録',
    'fields' => [
        'id' => 'ID',
        'admin_id' => 'オペレータ',
        'platform_id' => 'プラットフォームID',
        'point' => 'あつりょくふごうりょう',
        'reverse_water' => 'はんすい額',
        'real_reverse_water' => '実績リリース数量',
        'all_diff' => '総勝ち負け',
        'platform_ratio' => 'プラットフォームスケール',
        'level_ratio' => '等級加算',
        'date' => '決済日',
        'created_at' => 'リリース時間',
        'receive_time' => '受け取り時間',
        'switch' => 'スイッチ',
    ],
    'created_at_start' => '開始時間',
    'created_at_end' => '終了時間',
    'checkout_time' => '決済時間は毎日01:00',
    'bath_settlement' => '一括決済',
    'profit_settlement_confirm' => '反水奨励金に対して決済操作を行いますが、この操作は不可逆的で確実に操作しますか？',
    'settlement_reward_null' => '決済が必要な反水奨励金はありません',
    'success' => '反水奨励金決済成功'
];
