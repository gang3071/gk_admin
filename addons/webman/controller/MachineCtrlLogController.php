<?php

namespace addons\webman\controller;

use ExAdmin\ui\support\Request;
use support\Log;

/**
 * 機台控制日誌中心（ClickHouse）
 * @group admin
 */
class MachineCtrlLogController
{
    /**
     * 機台控制日誌
     * @auth true
     * @group admin
     */
    public function index()
    {
        return admin_view(plugin()->webman->getPath() . '/views/machine_ctrl_log_center.vue')
            ->attrs([]);
    }

    /**
     * 查詢機台控制日誌
     * @auth true
     * @group admin
     */
    public function search()
    {
        $machineCode = trim(Request::input('machine_code', ''));
        $startTime   = trim(Request::input('start_time', ''));
        $endTime     = trim(Request::input('end_time', ''));

        if (empty($machineCode)) {
            return json(['code' => 400, 'msg' => '請填寫機台編號']);
        }
        if (empty($startTime) || empty($endTime)) {
            return json(['code' => 400, 'msg' => '請選擇時間範圍']);
        }

        $machineModel = plugin()->webman->config('database.machine_model');
        $machine = $machineModel::withTrashed()->where('code', $machineCode)->first();
        if (!$machine) {
            return json(['code' => 404, 'msg' => "找不到機台編號：{$machineCode}"]);
        }
        $machineId = (int) $machine->id;

        $cfg    = config('clickhouse');
        $client = new \GuzzleHttp\Client();

        $chStart = $startTime . '.000';
        $chEnd   = $endTime   . '.999';

        $sql = "SELECT datetime, level, source_type, log_direction, action, action_desc,
                       raw_send, raw_recv, extra_data, machine_state
                FROM {$cfg['database']}.machine_ctrl
                WHERE machine_id = {$machineId}
                  AND datetime >= '{$chStart}'
                  AND datetime <= '{$chEnd}'
                ORDER BY datetime ASC
                LIMIT 10001
                FORMAT JSON";

        try {
            $response = $client->post($cfg['endpoint'], [
                'auth'    => [$cfg['user'], $cfg['password']],
                'body'    => $sql,
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() !== 200) {
                return json(['code' => 500, 'msg' => 'ClickHouse 返回異常：' . $response->getStatusCode()]);
            }

            $rows      = json_decode($response->getBody()->getContents(), true)['data'] ?? [];
            $truncated = count($rows) > 10000;
            if ($truncated) {
                $rows = array_slice($rows, 0, 10000);
            }

            $machineName = $machine->code;
            if (!empty($machine->name)) {
                $machineName .= '（' . $machine->name . '）';
            }

            return json(['code' => 200, 'data' => [
                'logs'       => $this->processCtrlLogs($rows),
                'machine'    => $machineName,
                'time_range' => "{$startTime} 至 {$endTime}",
                'truncated'  => $truncated,
            ]]);

        } catch (\Throwable $e) {
            Log::channel('default')->error('MachineCtrlLogController::search ClickHouse 查詢失敗', [
                'machine_code' => $machineCode,
                'error'        => $e->getMessage(),
            ]);
            return json(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }

    private function processCtrlLogs(array $rows): array
    {
        $hiddenActions = ['read_point', 'read_win_number', 'push', 'clear_log'];

        $stateKeys = [
            'point'             => ['label' => '當前分',  'color' => '#1890ff'],
            'gaming'            => ['label' => '遊戲中',  'color' => '#52c41a', 'skip_zero' => true, 'bool' => true],
            'gaming_user_id'    => ['label' => '玩家ID',  'color' => '#1890ff', 'skip_zero' => true],
            'reward_status'     => ['label' => '開獎中',  'color' => '#eb2f96', 'skip_zero' => true, 'bool' => true],
            'has_lock'          => ['label' => '鎖定中',  'color' => '#f5222d', 'skip_zero' => true, 'bool' => true],
            'keeping'           => ['label' => '保留中',  'color' => '#fa8c16', 'skip_zero' => true, 'bool' => true],
            'keeping_user_id'   => ['label' => '保留玩家','color' => '#fa8c16', 'skip_zero' => true],
            'open_point'        => ['label' => '開分',    'color' => '#13c2c2'],
            'wash_point'        => ['label' => '洗分',    'color' => '#52c41a'],
            'pressure'          => ['label' => '壓分',    'color' => '#fa8c16'],
            'score'             => ['label' => '得分',    'color' => '#722ed1'],
            'bet'               => ['label' => '總押',    'color' => '#f5222d'],
            'win'               => ['label' => '總贏',    'color' => '#2f54eb'],
            'card_score'        => ['label' => '卡分',    'color' => '#d46b08'],
            'machine_score'     => ['label' => '機台分',  'color' => '#08979c'],
            'player_win_number' => ['label' => '打碼轉',  'color' => '#531dab'],
            'turn'              => ['label' => '剩餘轉',  'color' => '#595959'],
        ];

        $result = [];
        $prevMs = null;

        foreach ($rows as $row) {
            if (in_array($row['action'] ?? '', $hiddenActions, true)) {
                continue;
            }

            $dtStr = $row['datetime'] ?? '';
            preg_match('/(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})(?:\.(\d+))?/', $dtStr, $m);
            $baseTs = isset($m[1]) ? strtotime($m[1]) * 1000 : 0;
            $fracMs = isset($m[2]) ? (int) str_pad(substr($m[2], 0, 3), 3, '0') : 0;
            $curMs  = $baseTs + $fracMs;

            $elapsedMs = $prevMs !== null ? $curMs - $prevMs : null;
            $prevMs    = $curMs;

            if ($row['action'] === 'open' && $row['log_direction'] === 'response') {
                $displayType = 'noise';
            } elseif (in_array($row['action'], ['heartbeat', 'field_update']) || $row['source_type'] === 'heartbeat') {
                $displayType = 'heartbeat';
            } elseif ($row['log_direction'] === 'command') {
                $displayType = 'command';
            } elseif ($row['log_direction'] === 'response') {
                $displayType = 'response';
            } else {
                $displayType = 'event';
            }

            $marker = null;
            $desc   = $row['action_desc'] ?? '';
            if (str_contains($desc, '開分開始'))      { $marker = 'open_start'; }
            elseif (str_contains($desc, '開分結束'))  { $marker = 'open_end';   if ($displayType === 'noise') $displayType = 'response'; }
            elseif (str_contains($desc, '下分開始'))  { $marker = 'wash_start'; }
            elseif (str_contains($desc, '下分結束'))  { $marker = 'wash_end'; }

            $state = [];
            if (!empty($row['machine_state'])) {
                $parsed     = json_decode($row['machine_state'], true) ?? [];
                $seenLabels = [];
                foreach ($stateKeys as $key => $meta) {
                    if (!array_key_exists($key, $parsed)) continue;
                    $val = $parsed[$key];
                    if (!empty($meta['skip_zero']) && !$val) continue;
                    if (in_array($meta['label'], $seenLabels)) continue;
                    $displayVal   = !empty($meta['bool']) ? '是' : $val;
                    $state[]      = ['label' => $meta['label'], 'color' => $meta['color'], 'val' => $displayVal];
                    $seenLabels[] = $meta['label'];
                }
            }

            $extra = [];
            if (!empty($row['extra_data'])) {
                $parsed = json_decode($row['extra_data'], true) ?? [];
                foreach ($parsed as $k => $v) {
                    if (!is_array($v) && !is_null($v) && $v !== '') {
                        $extra[] = ['key' => $k, 'val' => $v];
                    }
                }
                $extra = array_slice($extra, 0, 6);
            }

            $result[] = [
                'time'          => substr($dtStr, 11, 8),
                'elapsed_ms'    => $elapsedMs,
                'level'         => $row['level'],
                'display_type'  => $displayType,
                'source_type'   => $row['source_type'],
                'log_direction' => $row['log_direction'],
                'action'        => $row['action'],
                'action_desc'   => $row['action_desc'],
                'marker'        => $marker,
                'state'         => $state,
                'extra'         => $extra,
                'raw_send'      => $row['raw_send'] ?? '',
                'raw_recv'      => $row['raw_recv'] ?? '',
            ];
        }

        return $result;
    }
}
