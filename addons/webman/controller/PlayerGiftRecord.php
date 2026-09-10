<?php

namespace addons\webman\controller;

use addons\webman\model\GameType;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\detail\Detail;
use ExAdmin\ui\component\grid\tag\Tag;


/**
 * 开增明细记录
 */
class PlayerGiftRecord
{
    protected $model;

    public function __construct()
    {
        $this->model = plugin()->webman->config('database.player_gift_record_model');

    }


    /**
     * 开增记录
     * @param $data
     * @return Detail
     */
    public function detail($data): Detail
    {
        $detailArr = (new $this->model)::where('player_game_log_id', $data['id'])->first();
        return Detail::create($detailArr, function (Detail $detail) use ($data) {
            if (isset($data['id']) && $data['id']) {
                $detail->item('player_name', admin_trans('player_gift_record.player_name'));
                $detail->item('machine_name', admin_trans('player_gift_record.machine_name'));
                $detail->item('machine_type', admin_trans('player_gift_record.player_name'))->display(function ($val) {
                    return Html::create()->content([
                        Tag::create(getGameTypeName($val))
                    ]);
                });
                $detail->item('open_num', admin_trans('player_gift_record.open_num'));
                $detail->item('give_num', admin_trans('player_gift_record.give_num'));
                if ($data['type'] == GameType::TYPE_SLOT) {
                    $detail->item('condition', admin_trans('player_gift_record.condition'));
                }
                $detail->item('created_at', admin_trans('player_gift_record.created_at'));
                $detail->item('updated_at', admin_trans('player_gift_record.updated_at'));
            }
        });
    }
}