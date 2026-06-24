<?php
/**
 * 摸奖券系统配置
 */

return [
    /**
     * 打码量统计配置
     */
    'bet_calculation' => [
        /**
         * 是否统计机台游戏打码量
         */
        'include_machine_game' => true,

        /**
         * 是否统计电子游戏打码量
         * ⚠️ 注意：如果 play_game_record 表数据量巨大，可能影响性能
         * 建议先添加索引：idx_dept_time_status_for_lottery
         */
        'include_online_game' => true,

        /**
         * 电子游戏打码量统计方式
         * 'eloquent' - 使用 Eloquent ORM（功能完整但较慢）
         * 'raw_sql' - 使用原生 SQL（性能更好，推荐）
         */
        'online_game_query_method' => 'raw_sql',
    ],

    /**
     * 性能优化配置
     */
    'performance' => [
        /**
         * 单次扫描最大处理时间（秒）
         * 超过此时间会记录警告日志
         */
        'max_scan_duration' => 30,

        /**
         * 是否记录慢查询日志
         */
        'log_slow_queries' => true,

        /**
         * 慢查询阈值（毫秒）
         */
        'slow_query_threshold' => 1000,
    ],
];
