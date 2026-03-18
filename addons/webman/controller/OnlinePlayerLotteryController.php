<?php

namespace addons\webman\controller;

use addons\webman\model\GameLottery;
use addons\webman\model\Player;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\Notice;
use ExAdmin\ui\component\layout\Space;
use ExAdmin\ui\support\Request;
use support\Db;
use support\Log;

/**
 * 在线玩家彩金发放控制器
 */
class OnlinePlayerLotteryController
{
    /**
     * 在线玩家管理页面（包含两个Tab）
     * @return Space
     */
    public function index()
    {
        return Space::create()
            ->content(admin_view(plugin()->webman->getPath() . '/views/online_players.vue')->attrs([
                'lotteryOptions' => $this->getLotteryOptions(),
                'wsUrl' => env('WS_URL', ''),
                'appKey' => config('plugin.webman.push.app.app_key'),
            ]))->style(['width' => '100%', 'display' => 'block']);
    }

    /**
     * 获取实体机台在线玩家列表（API）
     * @return \support\Response
     */
    public function getMachinePlayers()
    {
        $tenSecondsAgo = date('Y-m-d H:i:s', time() - 10);
        $fiveMinutesAgo = date('Y-m-d H:i:s', time() - 300);

        $players = Player::query()
            ->select([
                'player.id',
                'player.uuid',
                'player.name',
                'player.phone',
                'player.avatar',
                'player.department_id',
                'player.is_test',
                'player.is_coin',
                'player.is_promoter',
            ])
            ->selectRaw('MAX(player_game_log.created_at) as last_bet_time')
            ->selectRaw('MAX(player_game_log.pressure) as last_pressure')
            ->join('player_game_record', 'player.id', '=', 'player_game_record.player_id')
            ->join('player_game_log', 'player_game_record.id', '=', 'player_game_log.game_record_id')
            ->where('player_game_record.status', \addons\webman\model\PlayerGameRecord::STATUS_START)
            ->where('player_game_log.created_at', '>=', $tenSecondsAgo)
            ->where('player_game_log.pressure', '>', 0)
            ->groupBy([
                'player.id',
                'player.uuid',
                'player.name',
                'player.phone',
                'player.avatar',
                'player.department_id',
                'player.is_test',
                'player.is_coin',
                'player.is_promoter'
            ])
            ->orderBy('last_bet_time', 'desc')
            ->limit(100)
            ->get();

        $result = [];
        foreach ($players as $player) {
            // 获取当前机台信息
            $record = \addons\webman\model\PlayerGameRecord::query()
                ->with('machine')
                ->where('player_id', $player->id)
                ->where('status', \addons\webman\model\PlayerGameRecord::STATUS_START)
                ->orderBy('id', 'desc')
                ->first();

            // 获取累计押注
            $totalPressure = \addons\webman\model\PlayerGameLog::query()
                ->where('player_id', $player->id)
                ->where('created_at', '>=', $fiveMinutesAgo)
                ->sum('pressure');

            $betSecondsAgo = time() - strtotime($player->last_bet_time);

            $result[] = [
                'id' => $player->id,
                'uuid' => $player->uuid,
                'name' => $player->name ?: $player->uuid,
                'phone' => $player->phone,
                'avatar' => $this->getAvatarUrl($player->avatar),
                'is_test' => $player->is_test,
                'is_coin' => $player->is_coin,
                'is_promoter' => $player->is_promoter,
                'machine_id' => $record?->machine_id,
                'machine_name' => $record?->machine?->name,
                'machine_code' => $record?->machine?->code,
                'last_bet_time' => $player->last_bet_time,
                'bet_seconds_ago' => $betSecondsAgo,
                'total_pressure' => number_format($totalPressure, 2),
                'last_pressure' => number_format($player->last_pressure, 2),
            ];
        }

        return jsonSuccessResponse('success', $result);
    }

    /**
     * 获取电子游戏在线玩家列表（API）
     * @return \support\Response
     */
    public function getGamePlayers()
    {
        $tenSecondsAgo = date('Y-m-d H:i:s', time() - 10);
        $fiveMinutesAgo = date('Y-m-d H:i:s', time() - 300);

        $players = Player::query()
            ->select([
                'player.id',
                'player.uuid',
                'player.name',
                'player.phone',
                'player.avatar',
                'player.department_id',
                'player.is_test',
                'player.is_coin',
                'player.is_promoter',
            ])
            ->selectRaw('MAX(play_game_record.created_at) as last_bet_time')
            ->selectRaw('MAX(play_game_record.bet) as last_bet')
            ->join('play_game_record', 'player.id', '=', 'play_game_record.player_id')
            ->where('play_game_record.created_at', '>=', $tenSecondsAgo)
            ->where('play_game_record.bet', '>', 0)
            ->groupBy([
                'player.id',
                'player.uuid',
                'player.name',
                'player.phone',
                'player.avatar',
                'player.department_id',
                'player.is_test',
                'player.is_coin',
                'player.is_promoter'
            ])
            ->orderBy('last_bet_time', 'desc')
            ->limit(100)
            ->get();

        $result = [];
        foreach ($players as $player) {
            // 获取当前平台信息
            $record = \addons\webman\model\PlayGameRecord::query()
                ->with('gamePlatform')
                ->where('player_id', $player->id)
                ->orderBy('id', 'desc')
                ->first();

            // 获取累计押注
            $totalBet = \addons\webman\model\PlayGameRecord::query()
                ->where('player_id', $player->id)
                ->where('created_at', '>=', $fiveMinutesAgo)
                ->sum('bet');

            $betSecondsAgo = time() - strtotime($player->last_bet_time);

            $result[] = [
                'id' => $player->id,
                'uuid' => $player->uuid,
                'name' => $player->name ?: $player->uuid,
                'phone' => $player->phone,
                'avatar' => $this->getAvatarUrl($player->avatar),
                'is_test' => $player->is_test,
                'is_coin' => $player->is_coin,
                'is_promoter' => $player->is_promoter,
                'platform_id' => $record?->platform_id,
                'platform_name' => $record?->gamePlatform?->name,
                'game_code' => $record?->game_code,
                'last_bet_time' => $player->last_bet_time,
                'bet_seconds_ago' => $betSecondsAgo,
                'total_bet' => number_format($totalBet, 2),
                'last_bet' => number_format($player->last_bet, 2),
            ];
        }

        return jsonSuccessResponse('success', $result);
    }

    /**
     * 发放彩金
     * @return \support\Response
     */
    public function grantLottery()
    {
        $data = Request::input();
        $playerId = $data['player_id'] ?? 0;
        $lotteryId = $data['lottery_id'] ?? 0;
        $amount = $data['amount'] ?? 0;
        $remark = $data['remark'] ?? '';

        // 验证参数
        if (!$playerId || !$lotteryId || !$amount) {
            return jsonFailResponse('参数错误');
        }

        // 获取玩家信息
        $player = Player::find($playerId);
        if (!$player) {
            return jsonFailResponse('玩家不存在');
        }

        // 获取彩金信息
        $lottery = GameLottery::find($lotteryId);
        if (!$lottery) {
            return jsonFailResponse('彩金不存在');
        }

        // 检查彩金池余额
        if ($lottery->amount < $amount) {
            return jsonFailResponse('彩金池余额不足，当前余额: ' . number_format($lottery->amount, 2));
        }

        DB::beginTransaction();
        try {
            // 创建彩金记录
            $record = new PlayerLotteryRecord();
            $record->player_id = $player->id;
            $record->uuid = $player->uuid;
            $record->player_phone = $player->phone ?? '';
            $record->player_name = $player->name ?? '';
            $record->is_coin = $player->is_coin;
            $record->is_promoter = $player->is_promoter;
            $record->is_test = $player->is_test;
            $record->department_id = $player->department_id;
            $record->source = PlayerLotteryRecord::SOURCE_MANUAL; // 手动发放
            $record->amount = $amount;
            $record->lottery_id = $lottery->id;
            $record->lottery_name = $lottery->name;
            $record->lottery_pool_amount = $lottery->amount;
            $record->lottery_rate = $lottery->rate;
            $record->lottery_type = $lottery->lottery_type;
            $record->lottery_sort = $lottery->sort;
            $record->cate_rate = $lottery->rate;
            $record->status = PlayerLotteryRecord::STATUS_PASS;
            $record->remark = $remark;
            $record->save();

            // 扣减彩金池
            $lottery->amount = bcsub($lottery->amount, $amount, 4);
            $lottery->save();

            // 发送站内信
            $notice = new Notice();
            $notice->department_id = $player->department_id;
            $notice->player_id = $player->id;
            $notice->source_id = $record->id;
            $notice->type = Notice::TYPE_LOTTERY;
            $notice->receiver = Notice::RECEIVER_PLAYER;
            $notice->is_private = 1;
            $notice->title = '彩金派彩';
            $notice->content = '恭喜您获得' . $lottery->name . '的彩金奖励，金额: ' . $amount;
            $notice->save();

            DB::commit();

            // 清除彩金缓存
            \app\service\GameLotteryServices::clearAllCache();

            // 推送彩池数据变化
            \app\service\GameLotteryServices::pushLotteryPoolData();

            // 发送Socket消息给玩家
            try {
                sendSocketMessage('player-' . $player->id, [
                    'msg_type' => 'game_player_lottery_allow',
                    'player_id' => $player->id,
                    'has_win' => 1,
                    'lottery_record_id' => $record->id,
                    'lottery_id' => $lottery->id,
                    'lottery_name' => $lottery->name,
                    'lottery_sort' => $lottery->sort,
                    'lottery_type' => $lottery->lottery_type,
                    'amount' => $amount,
                    'lottery_pool_amount' => $lottery->amount,
                    'lottery_rate' => $lottery->rate,
                    'is_manual' => 1, // 标记为手动发放
                ]);

                sendSocketMessage('player-' . $player->id, [
                    'msg_type' => 'player_notice',
                    'player_id' => $player->id,
                    'notice_type' => Notice::TYPE_LOTTERY,
                    'notice_title' => $notice->title,
                    'notice_content' => $notice->content,
                    'amount' => $amount,
                    'notice_num' => Notice::query()->where('player_id', $player->id)->where('status', 0)->count('*')
                ]);
            } catch (\Exception $e) {
                Log::error('发送彩金Socket消息失败: ' . $e->getMessage());
            }

            Log::info('手动发放彩金成功', [
                'player_id' => $player->id,
                'uuid' => $player->uuid,
                'lottery_id' => $lottery->id,
                'lottery_name' => $lottery->name,
                'amount' => $amount,
                'remark' => $remark,
            ]);

            return jsonSuccessResponse('彩金发放成功');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('手动发放彩金失败: ' . $e->getMessage());
            return jsonFailResponse('彩金发放失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取彩金选项
     * @return array
     */
    private function getLotteryOptions(): array
    {
        $lotteries = GameLottery::query()
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort', 'desc')
            ->get();

        $options = [];
        foreach ($lotteries as $lottery) {
            $options[] = [
                'value' => $lottery->id,
                'label' => $lottery->name . ' (彩池: ' . number_format($lottery->amount, 2) . ')',
                'amount' => $lottery->amount,
            ];
        }

        return $options;
    }

    /**
     * 获取头像URL
     * @param $avatar
     * @return string
     */
    private function getAvatarUrl($avatar): string
    {
        if (!$avatar) {
            return '';
        }

        if (is_numeric($avatar)) {
            return config('def_avatar.' . $avatar, '');
        }

        return $avatar;
    }
}