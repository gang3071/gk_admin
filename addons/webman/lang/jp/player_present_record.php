<?php

use addons\webman\model\PlayerPresentRecord;

return [
    'title' => 'ターニングポイント記録',
    'user_info' => 'イニシエータ情報',
    'player_info' => '取引オブジェクト',
    'fields' => [
        'id' => 'ID',
        'user_id' => 'イニシエーター',
        'type' => '取引行動',
        'player_id' => 'トランザクションオブジェクト',
        'department_id' => 'チャンネル',
        'tradeno' => '注文番号',
        'amount' => '取引額',
        'user_origin_amount' => '移行前のポイント',
        'user_after_amount' => '移行後のポイント',
        'player_origin_amount' => '移籍前のポイント',
        'player_after_amount' => '移籍後のポイント',
        'created_at' => 'トランザクション時間',
    ],
    'type' => [
        PlayerPresentRecord::TYPE_IN => '転送',
        PlayerPresentRecord::TYPE_OUT => '転送'
    ],
    'search' => [
        'user_placeholder' => 'イニシエーターを選択してください',
        'player_placeholder' => 'トランザクション オブジェクトを選択してください',
        'placeholder' => 'キーワードを入力してください',
        'user_uuid' => 'イニシエーター UID',
        'user_name' => 'イニシエーター名',
        'user_phone' => '開始者の携帯電話番号',
        'player_uuid' => 'トランザクションオブジェクトUID',
        'player_name' => 'トランザクションオブジェクト名',
        'player_phone' => '取引相手の携帯電話番号',
    ],
    'total_data' => [
        'total_icon_amount' => '通貨ディーラーは合計ゲームポイントを転送します',
        'total_player_amount' => 'プレイヤーは合計ゲームポイントを転送します',
    ],
    'user_type' => 'イニシエータタイプ',
    'player_type' => '取引先タイプ',
];
