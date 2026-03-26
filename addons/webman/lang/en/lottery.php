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

    // Form validation
    'form_validation' => [
        'required_betting_amount_required' => 'Required betting amount cannot be empty',
        'max_pool_amount_required' => 'Maximum pool amount cannot be empty',
        'max_pool_amount_min_1' => 'Maximum pool amount cannot be less than 1',
        'max_pool_amount_max_100000000' => 'Maximum pool amount cannot exceed 100000000',
        'auto_refill_amount_required' => 'When auto-refill is enabled, the guaranteed amount must be greater than 0',
        'auto_refill_amount_exceed_max' => 'Guaranteed amount cannot exceed maximum pool amount',
    ],

    // Burst configuration
    'burst_config' => [
        'divider_title' => 'Burst Configuration',
        'divider_multiplier' => 'Burst Multiplier Configuration',
        'divider_trigger' => 'Burst Trigger Probability Configuration',
        'status_label' => 'Burst Status',
        'status_help' => 'When enabled, burst events will probabilistically trigger when the pool reaches a certain percentage, greatly increasing win probability',
        'duration_label' => 'Burst Duration (minutes)',
        'duration_help' => 'Duration of burst after triggered. E.g., set to 15 minutes, win probability significantly increases for 15 minutes after trigger, higher probability the less time remaining',
        'duration_placeholder' => 'Enter duration, e.g., 15',
    ],

    // Burst multiplier
    'burst_multiplier' => [
        'final_label' => 'Remaining Time ≤ 10%',
        'final_help' => 'Final sprint phase, highest win probability. E.g., last 1.5 minutes of a 15-minute burst, 50x means win probability increased 50 times',
        'stage_4_label' => 'Remaining Time 10% - 30%',
        'stage_4_help' => 'Stage 4, relatively high probability. E.g., minutes 12-13.5 of a 15-minute burst, 25x increase',
        'stage_3_label' => 'Remaining Time 30% - 50%',
        'stage_3_help' => 'Stage 3, medium probability. E.g., minutes 7.5-10.5 of a 15-minute burst, 15x increase',
        'stage_2_label' => 'Remaining Time 50% - 70%',
        'stage_2_help' => 'Stage 2, moderate probability. E.g., minutes 4.5-7.5 of a 15-minute burst, 10x increase',
        'initial_label' => 'Remaining Time 70% - 100%',
        'initial_help' => 'Initial stage, lower probability. E.g., first 10.5 minutes of a 15-minute burst, 5x increase to let players feel the burst atmosphere',
        'description' => '<strong>Description:</strong> During burst, player win probability automatically increases based on remaining time. Higher multiplier means easier to win.<br><strong>Example:</strong> Assume normal win probability is 0.1%, burst lasts 15 minutes:<br>• First 10.5 minutes (70%-100% remaining): Win probability = 0.1% × 5 = 0.5%<br>• Minutes 10.5-12 (30%-70% remaining): Win probability gradually increases to 0.1% × 10 = 1%<br>• Last 1.5 minutes (≤10% remaining): Win probability = 0.1% × 50 = 5% (highest)',
    ],

    // Burst trigger probability
    'burst_trigger' => [
        '95_label' => 'Pool Ratio ≥ 95%',
        '95_help' => 'Pool extremely full, highest trigger probability. E.g., max pool 10000, current ≥9500, 10% probability to trigger burst per bet',
        '90_label' => 'Pool Ratio 90% - 95%',
        '90_help' => 'Pool very full, high trigger probability. E.g., pool 9000-9500, 6% probability to trigger per bet',
        '85_label' => 'Pool Ratio 85% - 90%',
        '85_help' => 'Pool fairly full, relatively high trigger probability. E.g., pool 8500-9000, 4% probability to trigger per bet',
        '80_label' => 'Pool Ratio 80% - 85%',
        '80_help' => 'Pool moderately full, medium trigger probability. E.g., pool 8000-8500, 2.5% probability to trigger per bet',
        '75_label' => 'Pool Ratio 75% - 80%',
        '75_help' => 'Pool moderate, moderate trigger probability. E.g., pool 7500-8000, 1.5% probability to trigger per bet',
        '70_label' => 'Pool Ratio 70% - 75%',
        '70_help' => 'Pool somewhat full, lower trigger probability. E.g., pool 7000-7500, 0.8% probability to trigger per bet',
        '65_label' => 'Pool Ratio 65% - 70%',
        '65_help' => 'Pool medium, low trigger probability. E.g., pool 6500-7000, 0.4% probability to trigger per bet',
        '60_label' => 'Pool Ratio 60% - 65%',
        '60_help' => 'Pool medium, very low trigger probability. E.g., pool 6000-6500, 0.2% probability to trigger per bet',
        '50_label' => 'Pool Ratio 50% - 60%',
        '50_help' => 'Pool half full, extremely low trigger probability. E.g., pool 5000-6000, 0.1% probability to trigger per bet (about 1 in 1000)',
        '40_label' => 'Pool Ratio 40% - 50%',
        '40_help' => 'Pool somewhat low, extremely low trigger probability. E.g., pool 4000-5000, 0.05% probability to trigger per bet (about 1 in 2000)',
        '30_label' => 'Pool Ratio 30% - 40%',
        '30_help' => 'Pool rather low, negligible trigger probability. E.g., pool 3000-4000, 0.02% probability to trigger per bet (about 1 in 5000)',
        '20_label' => 'Pool Ratio 20% - 30%',
        '20_help' => 'Pool very low, almost zero trigger probability. E.g., pool 2000-3000, 0.01% probability to trigger per bet (about 1 in 10000). Suggest: no trigger below 20%',
        'description' => '<strong>Description:</strong> The closer the pool amount to max pool, the higher the burst trigger probability. Checked after every player bet.<br><strong>Example:</strong> Assume max pool is 10000:<br>• Pool 9500 (95%): 10% probability to trigger burst per bet<br>• Pool 8000 (80%): 2.5% probability to trigger burst per bet<br>• Pool 5000 (50%): 0.1% probability to trigger burst per bet<br><strong>Suggestion:</strong> Higher probability when pool is fuller, ensures timely payout and avoids long-term accumulation',
    ],

    // Auto refill configuration
    'auto_refill' => [
        'divider_title' => 'Guaranteed Amount Configuration',
        'status_label' => 'Enable Guaranteed Amount',
        'status_help' => 'When enabled, system will automatically maintain pool above guaranteed amount',
        'amount_label' => 'Guaranteed Amount',
        'amount_help' => 'Minimum pool maintenance amount. Recommended to set as 1-2x max payout amount',
        'amount_placeholder' => 'Enter guaranteed amount, e.g., 5000',
        'description' => '<strong>Description:</strong> Guaranteed amount is the minimum pool maintenance amount, ensuring pool always has sufficient funds for payouts.<br><strong>How it works:</strong><br>• Pool accumulation: If pool is insufficient for payout, auto-refill to guaranteed amount<br>• After payout: If pool is below guaranteed amount, auto-refill to guaranteed amount<br><strong>Example:</strong> Guaranteed amount set to 5000:<br>• Pool 3000, need payout 4000 → refill to 5000 first, payout 4000, remaining 1000 → refill to 5000 again<br>• Pool 6000, after payout of 2000 remaining 4000 → auto-refill to 5000<br><strong>Suggestion:</strong> Guaranteed amount should be 1-2x max payout amount to ensure sufficient funds anytime',
    ],

    // Extended form help text and placeholders
    'form_help_extended' => [
        'current_pool_amount' => 'Current pool amount, deducted when player wins. E.g., pool has 5000, player wins 5000, pool clears to zero',
        'double_trigger_amount' => 'When pool reaches this amount, win probability doubles. E.g., set to 3000, when pool ≥3000, player win probability increases from 0.001 to 0.002',
        'max_payout_amount' => 'Maximum amount a single win can get. E.g., set to 5000, player can win max 5000 per time, even if pool has 10000',
        'max_pool_cap' => 'Pool accumulation cap, stops accumulating and triggers burst when reached. E.g., set to 10000, pool stops growing at 10000 and starts burst probability checks. This field is required',
    ],

    'form_placeholder_extended' => [
        'current_pool_amount' => 'Enter current pool amount, e.g., 5000',
        'double_trigger_amount' => 'Enter double trigger amount, e.g., 3000',
        'max_payout_amount' => 'Enter max payout amount, e.g., 5000',
        'max_pool_cap' => 'Enter max pool amount, e.g., 10000',
    ],

    // Log messages
    'log' => [
        'reset_stats' => 'Reset machine lottery draw statistics',
        'reset_stats_failed' => 'Failed to reset machine lottery draw statistics',
        'clear_stats_success' => 'Successfully cleared lottery statistics',
        'clear_stats_error' => 'Error clearing lottery statistics',
    ],

    // Sync lottery pool functionality
    'sync' => [
        'title' => 'Sync Lottery Pool to Database',
        'current_db_amount' => 'Current Database Total: ',
        'redis_pending_amount' => 'Redis Pending Amount: ',
        'after_sync_amount' => 'Total After Sync: ',
        'success' => 'Sync successful! Synced {count} lotteries',
        'failed' => 'Sync failed: ',
    ],

    // Clear stats details
    'clear_stats_details' => 'Reset:\n• Total checks\n• Total wins\n• Today\'s checks\n• Today\'s wins',

    // Table headers
    'table_headers' => [
        'remaining_time_percentage' => 'Remaining Time Percentage',
        'probability_multiplier' => 'Probability Multiplier',
        'description' => 'Description',
        'pool_ratio' => 'Pool Ratio',
        'trigger_probability' => 'Trigger Probability (%)',
    ],

    // Machine lottery configuration
    'machine_lottery' => [
        'divider_payout_config' => 'Payout Configuration',
        'divider_trigger_config' => 'Trigger Condition Configuration',
        'divider_probability_config' => 'Probability Payout Configuration',
        'payout_ratio_help' => 'Percentage of pool to payout when winning. E.g., set to 100%, payout entire pool when winning; set to 50%, payout half pool',
        'payout_ratio_placeholder' => 'Enter payout ratio, e.g., 100',
        'slot_condition_help' => 'Fixed lottery trigger condition: consecutive game rounds needed in Slot game. E.g., set to 100, trigger lottery after player plays 100 consecutive rounds',
        'slot_condition_placeholder' => 'Enter game rounds, e.g., 100',
        'steel_ball_condition_help' => 'Fixed lottery trigger condition: specific event count needed in Steel Ball game. E.g., set to 50, trigger lottery after player achieves condition 50 times',
        'steel_ball_condition_placeholder' => 'Enter count, e.g., 50',
        'win_ratio_label' => 'Win Probability',
        'minutes_suffix' => 'minutes',
    ],
];
