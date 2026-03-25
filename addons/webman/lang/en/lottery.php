<?php

use addons\webman\model\Lottery;

return [
    'title' => 'Bonus list',
    'lottery_info' => 'Lottery information',
    'fields' => [
        'id' => 'number',
        'name' => 'Bonus name',
        'rate' => 'amount ratio',
        'game_type' => 'Game type',
        'lottery_type' => 'Lottery type',
        'condition' => 'Trigger condition',
        'start_num' => 'Start number',
        'end_num' => 'End number',
        'random_num' => 'Random value',
        'last_player_id' => 'Last winning player id',
        'last_player_name' => 'Last winning player name',
        'last_award_amount' => 'Last award amount',
        'max_amount' => 'Cap amount',
        'lottery_times' => 'Number of distribution times',
        'status' => 'status',
        'sort' => 'sort',
        'created_at' => 'Creation time',
        'updated_at' => 'Update time',
        'deleted_at' => 'Deleted time',
        'win_ratio' => 'probability of winning',
    ],
    'created_at_start' => 'Start time',
    'created_at_end' => 'End time',
    'lottery_type' => [
        Lottery::LOTTERY_TYPE_FIXED => 'Fixed threshold',
        Lottery::LOTTERY_TYPE_RANDOM => 'Random trigger',
    ],
    'placeholder_start_num' => 'Please enter the starting number',
    'placeholder_end_num' => 'Please enter the end point number',
    'placeholder_max_amount' => 'Please enter the cap amount',
    'rul' => [
        'start_num_required' => 'Start number required',
        'start_num_min_1' => 'The starting number cannot be 0',
        'start_num_max_100000000' => 'Set the starting number to 100 million',
        'end_num_required' => 'End number required',
        'end_num_min_1' => 'The end number cannot be 0',
        'end_num_max_100000000' => 'The maximum number of end points is set to 100 million',
        'end_num_gt_start_num' => 'The end number must be greater than the start number',
        'max_amount_min_1' => 'The cap amount cannot be 0',
        'max_amount_max_100000000' => 'The maximum cap amount is set to 100 million',
        'condition_required' => 'Condition is required',
        'max_count_five' => 'Each machine type cannot have more than 5 bonus points',
    ],
    'help' => [
        'rate_help' => 'Get a certain percentage of the prize pool reward',
        'max_amount_help' => 'Get the maximum bonus pool reward',
        'condition_slot_help' => 'When the total score on the scoreboard reaches a certain number of points, you can get a bonus pool reward',
        'condition_jac_help' => 'If the amount of beads dropped reaches a certain number in a single time, you can get a bonus pool reward',
        'start_jac_help' => 'Starting when the current round of bonus calculation begins, the player who rolls for the xth time can win the prize',
        'start_slot_help' => 'Starting when the current round of bonus calculation begins, the player who scores for the xth time will win the prize',
        'double_amount_help' => 'When the bonus pool reaches the set value, double payout will be available',
        'amount_help' => 'Every time Sluo presses points, or every turn of the steel ball, a part of the amount will be accumulated into the corresponding bonus pool',
        'max_amount' => 'Maximum cumulative amount in the lottery pool',
    ],
    'slot_condition_msg' => 'The total score on the scoreboard reached',
    'jac_condition_msg' => 'The number of beads reached in a single time',
    'random_rang' => 'range',
    'edit_slot_lottery_pool' => 'Modify Slot Lottery Pool',
    'edit_jack_lottery_pool' => 'Modify the steel ball lottery pool',
    'lottery_pool_not_fount' => 'Lottery pool not found',
    'edit_slot_double_amount' => 'Modify Slot double bonus settings',
    'edit_jack_double_amount' => 'Modify steel ball double bonus settings',
    'edit_slot_max_amount' => 'Modify the maximum cumulative bonus setting for Slo',
    'edit_jack_max_amount' => 'Modify the maximum listed bonus setting for steel balls',
    'slot_lottery_pool' => 'Slot Lottery Pool',
    'jack_lottery_pool' => 'Steel Ball Lottery Pool',
    'slot_double_lottery_pool' => 'Slot double payout',
    'jack_double_lottery_pool' => 'Steel ball double payout',
    'slot_max_lottery_pool' => "Slo's largest colorful pool",
    'jack_max_lottery_pool' => 'Steel Ball Double Lottery',
    'not_fount' => 'Bonus not found',
    'action_success' => 'Action successful',
    'game_amount' => 'Gaming jackpot',

    'game_amount_1' => 'Gaming jackpot cannot be 0',

    'game_amount_100000000' => 'Maximum gaming jackpot setting: 100 million',

    'game_amount_help' => 'Each bet in the gaming game accumulates a corresponding percentage of the amount into this jackpot',

    'placeholder_game_amount' => 'Please enter the gaming jackpot amount',

    'double_amount' => 'Double payout amount',

    'double_status' => 'Whether to enable double payout',

    'double_amount_help' => 'Double payout will be enabled when the jackpot reaches the set value',

    'placeholder_double_amount' => 'Please enter the double payout trigger value',

    'max_double_amount_min_1' => 'Minimum double payout amount is 0', 'max_double_amount_100000000' => 'Maximum double payout amount is set to 100 million',

    'max_status' => 'Whether to enable maximum payout pool',

    'max_amount_help' => 'Maximum payout pool; when the amount reaches the set amount, the prize pool will no longer accumulate',

    'placeholder_game_max_amount' => 'Please enter the maximum payout amount',

    'max_amount' => 'Maximum payout amount',

    'max_amount_min_1' => 'Minimum maximum payout amount is set to 0',

    'max_amount_100000000' => 'Maximum payout amount is set to 100 million',

    'pool_ratio' => 'Pool ratio',

    'pool_ratio_help' => 'For each bet in a video game, a certain percentage of the amount will go into the prize pool',
    'base_bet_amount' => 'Bet amount limit',

    'base_bet_amount_0' => 'Minimum bet amount limit set to 0',

    'base_bet_amount_100000000' => 'Maximum bet amount limit set to 100 million',

    'base_bet_amount_help' => 'Bet amount limit, each bet must be greater than or equal to this setting to participate in the lottery',
    // New fields
    'max_pool_amount' => 'Maximum Pool Amount',
    'auto_refill_amount' => 'Guaranteed Amount',
    'enabled' => 'Enabled',
    'disabled' => 'Disabled',
    'lottery_stats' => 'Draw Statistics',
    'stats_total' => 'Total',
    'stats_today' => 'Today',
    'stats_times' => 'times',
    'stats_win' => 'Win',
    'stats_error' => 'Failed to get statistics',
    'clear_stats' => 'Clear Statistics',
    'clear_stats_confirm' => 'Are you sure you want to clear all lottery statistics? After clearing, statistics will start from 0 (including total checks, total wins, today\'s checks, today\'s wins)',
    'clear_stats_success_title' => 'Cleared Successfully',
    'clear_stats_success_message' => 'Successfully cleared statistics for {count} lotteries',
    'clear_stats_error_title' => 'Clear Failed',
];
