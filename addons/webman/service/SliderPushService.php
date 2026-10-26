<?php

namespace addons\webman\service;

use addons\webman\model\Slider;
use support\Log;

/**
 * 轮播图变更推送服务
 *
 * 广告位=待机页面(Slider::AD_POSITION_STANDBY)的轮播图信息或排序发生变动时，
 * 通过 WS 通知客户端重新拉取。数据仍走 homePageAds 接口，本服务只发变更信号，
 * 不携带图片/视频内容，避免推送体过大。
 */
class SliderPushService
{
    /**
     * 推送轮播图变更信号
     *
     * @param Slider $slider 变动的轮播图（软删除时传删除前实例）
     * @param string $action refresh=信息/排序变动 remove=删除
     * @return void
     */
    public static function notify(Slider $slider, string $action = 'refresh'): void
    {
        if (!self::affectsStandby($slider)) {
            return;
        }

        $departmentId = (int)($slider->department_id ?? 0);
        // 频道约定与 ClientMaintainService 一致
        $channel = $departmentId === 0
            ? 'client-all'
            : 'client-department-' . $departmentId;

        try {
            $result = sendSocketMessage($channel, [
                'msg_type' => 'slider_update',
                'action' => $action,
                'ad_position' => Slider::AD_POSITION_STANDBY,
                'department_id' => $departmentId,
                'slider_id' => (int)$slider->id,
                'timestamp' => time(),
            ]);
            Log::info('轮播图变更推送', [
                'channel' => $channel,
                'slider_id' => $slider->id,
                'action' => $action,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            // 推送失败不阻断后台保存，客户端下次拉取接口兜底
            Log::error('轮播图变更推送失败', [
                'slider_id' => $slider->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 本次变动是否影响待机页面
     *
     * 改动前或改动后任一处于待机页面即算影响：
     * 从待机页面改到其它广告位同样要通知客户端把旧素材撤下。
     */
    public static function affectsStandby(Slider $slider): bool
    {
        return (int)($slider->ad_position ?? 0) === Slider::AD_POSITION_STANDBY
            || (int)($slider->getOriginal('ad_position') ?? 0) === Slider::AD_POSITION_STANDBY;
    }
}
