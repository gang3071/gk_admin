<?php


namespace addons\webman\common;

use addons\webman\Admin;
use addons\webman\model\AdminDepartment;
use addons\webman\model\GameType;
use addons\webman\model\MachineReport;
use addons\webman\model\Player;
use addons\webman\model\PlayerDeliveryRecord;
use addons\webman\model\PlayerGameLog;
use addons\webman\model\PlayerLotteryRecord;
use addons\webman\model\PlayerPresentRecord;
use addons\webman\model\PlayerRechargeRecord;
use addons\webman\model\PlayerWithdrawRecord;
use addons\webman\model\PlayGameRecord;
use ExAdmin\ui\component\Component;
use ExAdmin\ui\contract\LoginAbstract;
use ExAdmin\ui\response\Message;
use ExAdmin\ui\response\Response;
use ExAdmin\ui\support\Container;
use ExAdmin\ui\support\Request;
use ExAdmin\ui\support\Token;
use Illuminate\Database\Eloquent\Builder;
use support\Cache;

class Login extends LoginAbstract
{
    
    /**
     * 登陆页
     * @return Component
     */
    public function index(): Component
    {
        $appName = Request()->header('App-Name');
        $view = 'login.vue'; // 默认主站登录页

        // 根据不同的 App-Name 设置不同的标题
        $titles = [
            'zh-CN' => '登录',
            'zh-TW' => '登入',
            'en' => 'Login',
            'jp' => 'ログイン',
        ];

        // 根据不同的 App-Name 选择不同的登录页面和标题
        if ($appName == 'channel') {
            $view = 'channel.vue'; // 渠道/子站登录页
            $titles = [
                'zh-CN' => '渠道登录',
                'zh-TW' => '渠道登入',
                'en' => 'Channel Login',
                'jp' => 'チャネルログイン',
            ];
        } elseif ($appName == 'agent') {
            $view = 'agent.vue'; // 代理登录页
            $titles = [
                'zh-CN' => '代理登录',
                'zh-TW' => '代理登入',
                'en' => 'Agent Login',
                'jp' => 'エージェントログイン',
            ];
        } elseif ($appName == 'store') {
            $view = 'store.vue'; // 店家登录页
            $titles = [
                'zh-CN' => '店家登录',
                'zh-TW' => '店機登入',
                'en' => 'Store Login',
                'jp' => '店舗ログイン',
            ];
        }

        return admin_view(plugin()->webman->getPath() . '/views/' . $view)->attrs([
            'webLogo' => admin_sysconf('web_logo'),
            'webName' => admin_sysconf('web_name'),
            'webMiitbeian' => admin_sysconf('web_miitbeian'),
            'webCopyright' => admin_sysconf('web_copyright'),
            'deBug' => env('APP_DEBUG'),
            'translations' => [
                'zh-CN' => [
                    'title' => $titles['zh-CN'],
                    'username_placeholder' => '请输入账号',
                    'password_placeholder' => '请输入密码',
                    'verify_placeholder' => '请输入验证码',
                    'login_button' => '登录',
                    'username_required' => '请输入账号',
                    'password_required' => '密码输入长度不能少于5位',
                    'verify_required' => '请输入验证码',
                ],
                'zh-TW' => [
                    'title' => $titles['zh-TW'],
                    'username_placeholder' => '請輸入帳號',
                    'password_placeholder' => '請輸入密碼',
                    'verify_placeholder' => '請輸入驗證碼',
                    'login_button' => '登入',
                    'username_required' => '請輸入帳號',
                    'password_required' => '密碼輸入長度不能少於5位',
                    'verify_required' => '請輸入驗證碼',
                ],
                'en' => [
                    'title' => $titles['en'],
                    'username_placeholder' => 'Please enter username',
                    'password_placeholder' => 'Please enter password',
                    'verify_placeholder' => 'Please enter verification code',
                    'login_button' => 'Login',
                    'username_required' => 'Please enter username',
                    'password_required' => 'Password must be at least 5 characters',
                    'verify_required' => 'Please enter verification code',
                ],
                'jp' => [
                    'title' => $titles['jp'],
                    'username_placeholder' => 'ユーザー名を入力してください',
                    'password_placeholder' => 'パスワードを入力してください',
                    'verify_placeholder' => '認証コードを入力してください',
                    'login_button' => 'ログイン',
                    'username_required' => 'ユーザー名を入力してください',
                    'password_required' => 'パスワードは5文字以上である必要があります',
                    'verify_required' => '認証コードを入力してください',
                ],
            ],
        ]);
    }

    /**
     * 渠道/子站登陆页
     * @return Component
     */
    public function channel(): Component
    {
        return admin_view(plugin()->webman->getPath() . '/views/channel.vue')->attrs([
            'webLogo' => admin_sysconf('web_logo'),
            'webName' => admin_sysconf('web_name'),
            'webMiitbeian' => admin_sysconf('web_miitbeian'),
            'webCopyright' => admin_sysconf('web_copyright'),
            'deBug' => env('APP_DEBUG'),
            'translations' => [
                'zh-CN' => [
                    'title' => '渠道登录',
                    'username_placeholder' => '请输入账号',
                    'password_placeholder' => '请输入密码',
                    'verify_placeholder' => '请输入验证码',
                    'login_button' => '登录',
                    'username_required' => '请输入账号',
                    'password_required' => '密码输入长度不能少于5位',
                    'verify_required' => '请输入验证码',
                ],
                'zh-TW' => [
                    'title' => '渠道登入',
                    'username_placeholder' => '請輸入帳號',
                    'password_placeholder' => '請輸入密碼',
                    'verify_placeholder' => '請輸入驗證碼',
                    'login_button' => '登入',
                    'username_required' => '請輸入帳號',
                    'password_required' => '密碼輸入長度不能少於5位',
                    'verify_required' => '請輸入驗證碼',
                ],
                'en' => [
                    'title' => 'Channel Login',
                    'username_placeholder' => 'Please enter username',
                    'password_placeholder' => 'Please enter password',
                    'verify_placeholder' => 'Please enter verification code',
                    'login_button' => 'Login',
                    'username_required' => 'Please enter username',
                    'password_required' => 'Password must be at least 5 characters',
                    'verify_required' => 'Please enter verification code',
                ],
                'jp' => [
                    'title' => 'チャネルログイン',
                    'username_placeholder' => 'ユーザー名を入力してください',
                    'password_placeholder' => 'パスワードを入力してください',
                    'verify_placeholder' => '認証コードを入力してください',
                    'login_button' => 'ログイン',
                    'username_required' => 'ユーザー名を入力してください',
                    'password_required' => 'パスワードは5文字以上である必要があります',
                    'verify_required' => '認証コードを入力してください',
                ],
            ],
        ]);
    }

    /**
     * 代理登陆页
     * @return Component
     */
    public function agent(): Component
    {
        return admin_view(plugin()->webman->getPath() . '/views/agent.vue')->attrs([
            'webLogo' => admin_sysconf('web_logo'),
            'webName' => admin_sysconf('web_name'),
            'webMiitbeian' => admin_sysconf('web_miitbeian'),
            'webCopyright' => admin_sysconf('web_copyright'),
            'deBug' => env('APP_DEBUG'),
            'translations' => [
                'zh-CN' => [
                    'title' => '代理登录',
                    'username_placeholder' => '请输入账号',
                    'password_placeholder' => '请输入密码',
                    'verify_placeholder' => '请输入验证码',
                    'login_button' => '登录',
                    'username_required' => '请输入账号',
                    'password_required' => '密码输入长度不能少于5位',
                    'verify_required' => '请输入验证码',
                ],
                'zh-TW' => [
                    'title' => '代理登入',
                    'username_placeholder' => '請輸入帳號',
                    'password_placeholder' => '請輸入密碼',
                    'verify_placeholder' => '請輸入驗證碼',
                    'login_button' => '登入',
                    'username_required' => '請輸入帳號',
                    'password_required' => '密碼輸入長度不能少於5位',
                    'verify_required' => '請輸入驗證碼',
                ],
                'en' => [
                    'title' => 'Agent Login',
                    'username_placeholder' => 'Please enter username',
                    'password_placeholder' => 'Please enter password',
                    'verify_placeholder' => 'Please enter verification code',
                    'login_button' => 'Login',
                    'username_required' => 'Please enter username',
                    'password_required' => 'Password must be at least 5 characters',
                    'verify_required' => 'Please enter verification code',
                ],
                'jp' => [
                    'title' => 'エージェントログイン',
                    'username_placeholder' => 'ユーザー名を入力してください',
                    'password_placeholder' => 'パスワードを入力してください',
                    'verify_placeholder' => '認証コードを入力してください',
                    'login_button' => 'ログイン',
                    'username_required' => 'ユーザー名を入力してください',
                    'password_required' => 'パスワードは5文字以上である必要があります',
                    'verify_required' => '認証コードを入力してください',
                ],
            ],
        ]);
    }

    /**
     * 店家登陆页
     * @return Component
     */
    public function store(): Component
    {
        return admin_view(plugin()->webman->getPath() . '/views/store.vue')->attrs([
            'webLogo' => admin_sysconf('web_logo'),
            'webName' => admin_sysconf('web_name'),
            'webMiitbeian' => admin_sysconf('web_miitbeian'),
            'webCopyright' => admin_sysconf('web_copyright'),
            'deBug' => env('APP_DEBUG'),
            'translations' => [
                'zh-CN' => [
                    'title' => '店家登录',
                    'username_placeholder' => '请输入账号',
                    'password_placeholder' => '请输入密码',
                    'verify_placeholder' => '请输入验证码',
                    'login_button' => '登录',
                    'username_required' => '请输入账号',
                    'password_required' => '密码输入长度不能少于5位',
                    'verify_required' => '请输入验证码',
                ],
                'zh-TW' => [
                    'title' => '店機登入',
                    'username_placeholder' => '請輸入帳號',
                    'password_placeholder' => '請輸入密碼',
                    'verify_placeholder' => '請輸入驗證碼',
                    'login_button' => '登入',
                    'username_required' => '請輸入帳號',
                    'password_required' => '密碼輸入長度不能少於5位',
                    'verify_required' => '請輸入驗證碼',
                ],
                'en' => [
                    'title' => 'Store Login',
                    'username_placeholder' => 'Please enter username',
                    'password_placeholder' => 'Please enter password',
                    'verify_placeholder' => 'Please enter verification code',
                    'login_button' => 'Login',
                    'username_required' => 'Please enter username',
                    'password_required' => 'Password must be at least 5 characters',
                    'verify_required' => 'Please enter verification code',
                ],
                'jp' => [
                    'title' => '店舗ログイン',
                    'username_placeholder' => 'ユーザー名を入力してください',
                    'password_placeholder' => 'パスワードを入力してください',
                    'verify_placeholder' => '認証コードを入力してください',
                    'login_button' => 'ログイン',
                    'username_required' => 'ユーザー名を入力してください',
                    'password_required' => 'パスワードは5文字以上である必要があります',
                    'verify_required' => '認証コードを入力してください',
                ],
            ],
        ]);
    }
    
    /**
     * 登录验证
     * @param array $data 提交数据
     * @return Message
     */
    public function check(array $data): Message
    {
        $validator = validator($data, [
            'username' => 'required',
            'source' => 'required',
            'password' => 'required|min:5'
        ], [
            'username.required' => admin_trans('login.account_not_empty'),
            'password.required' => admin_trans('login.password_not_empty'),
            'source.required' => admin_trans('login.source_not_empty'),
            'password.min' => admin_trans('login.password_min_length'),
        ]);
        if ($validator->fails()) {
            return message_error($validator->errors()->first());
        }
        $cacheKey = request()->getRealIp() . date('Y-m-d');
        $errorNum = Cache::get($cacheKey);
        if ($errorNum > 3 && !Container::getInstance()->captcha->check($data['verify'], $data['hash'])) {
            return message_error(admin_trans('login.captcha_error'));
        }
        $model = plugin()->webman->config('database.user_model');
        $type = AdminDepartment::TYPE_DEPARTMENT;
        if ($data['source'] == 'channel') {
            $type = AdminDepartment::TYPE_CHANNEL;
        } elseif ($data['source'] == 'agent') {
            $type = AdminDepartment::TYPE_AGENT;
        } elseif ($data['source'] == 'store') {
            $type = AdminDepartment::TYPE_STORE;
        }
        $user = $model::where('username', $data['username'])->where('type', $type)->first();
        if (!$user || !password_verify($data['password'], $user->password)) {
            Cache::set($cacheKey, $errorNum + 1);
            return message_error(admin_trans('login.error'));
        }

        // 保存用户选择的语言到session
        $locale = null;
        if (!empty($data['locale'])) {
            $validLocales = ['zh-CN', 'zh-TW', 'en', 'jp'];
            if (in_array($data['locale'], $validLocales)) {
                $locale = $data['locale'];
                request()->session()->set('locale', $locale);
            }
        }

        return message_success(admin_trans('login.success'))->data([
            'token' => Token::encode($user->toArray()),
            'locale' => $locale, // 返回locale给前端，让前端设置cookie
        ]);
    }
    
    /**
     * 获取验证码
     * @return Response
     */
    public function captcha(): Response
    {
        $cacheKey = request()->getRealIp() . date('Y-m-d');
        $errorNum = Cache::get($cacheKey);
        $captcha = Container::getInstance()->captcha->create();
        $captcha['verification'] = $errorNum > 3;
        return Response::success($captcha);
    }
    
    /**
     * 退出登录
     * @return Message
     */
    public function logout(): Message
    {
        Token::logout();
        $permissionKey = 'ADMIN_PERMISSIONS_' . Admin::id();
        Cache::delete($permissionKey);
        return message_success(admin_trans('login.logout'));
    }
    
    /**
     * 获取验证码
     * @return Response
     */
    public function totalInfo(): Response
    {
        $request = Request::input();
        $type = $request['type'] ?? '';
        $departmentId = $request['department_id'] ?? '';
        $playerId = $request['player_id'] ?? '';
        $adminUserId = $request['admin_user_id'] ?? '';
        $exAdminFilter = $request['ex_admin_filter'] ?? [];
        $data = [];
        switch ($type) {
            case 'PlayerGameLog':
                $parentPlayerId = $request['parent_player_id'] ?? 0;
                $agentPlayerId = $request['agent_player_id'] ?? 0;
                $query = PlayerGameLog::query();
                if (!empty($departmentId)) {
                    $query->where('department_id', $departmentId);
                }
                if (!empty($exAdminFilter['created_at_start'])) {
                    $query->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $query->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
                if (!empty($exAdminFilter['created_at_start'])) {
                    $query->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['department_id'])) {
                    $query->where('department_id', $exAdminFilter['department_id']);
                }
                if (!empty($exAdminFilter['action'])) {
                    $query->where('action', $exAdminFilter['action']);
                }
                if (!empty($parentPlayerId)) {
                    $query->where('parent_player_id', $parentPlayerId);
                }
                if (!empty($agentPlayerId)) {
                    $query->where('agent_player_id', $agentPlayerId);
                }
                if (!empty($exAdminFilter['action_type'])) {
                    switch ($exAdminFilter['action_type']) {
                        case 'admin':
                            $query->where('user_id', '!=', 0)->where('is_system', 0);
                            break;
                        case 'system':
                            $query->where('is_system', 1);
                            break;
                        case 'player':
                            $query->where('is_system', 0)->where('user_id', 0);
                            break;
                    }
                }
                if (!empty($exAdminFilter['machine'])) {
                    $query->whereHas('machine', function ($query) use ($exAdminFilter) {
                        if (!empty($exAdminFilter['machine']['machineLabel']['name'])) {
                            $query->whereHas('machineLabel', function ($query) use ($exAdminFilter) {
                                $query->where('name', 'like',
                                    '%' . $exAdminFilter['machine']['machineLabel']['name'] . '%');
                            });
                        }
                        if (!empty($exAdminFilter['machine']['code'])) {
                            $query->where('code', $exAdminFilter['machine']['code']);
                        }
                        if (!empty($exAdminFilter['machine']['cate_id'])) {
                            $query->whereIn('cate_id', $exAdminFilter['machine']['cate_id']);
                        }
                        if (!empty($exAdminFilter['machine']['producer_id'])) {
                            $query->where('producer_id', $exAdminFilter['machine']['producer_id']);
                        }
                    });
                }
                if (!empty($exAdminFilter['player']['uuid'])) {
                    $query->whereHas('player', function ($query) use ($exAdminFilter) {
                        $query->where('uuid', $exAdminFilter['player']['uuid']);
                    });
                }
                if (!empty($exAdminFilter['player']['phone'])) {
                    $query->whereHas('player', function ($query) use ($exAdminFilter) {
                        $query->where('phone', 'like', '%' . $exAdminFilter['player']['phone'] . '%');
                    });
                }
                
                if (isset($exAdminFilter['date_type'])) {
                    $query->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
                }
                
                if (!empty($exAdminFilter['recommend_id'])) {
                    $recommendId = Player::query()->where('uuid', 'like',
                        '%' . $exAdminFilter['recommend_id'] . '%')->value('id');
                    $query->whereHas('player', function ($query) use ($recommendId) {
                        if ($recommendId) {
                            $query->where('recommend_id', $recommendId);
                        } else {
                            $query->where(function ($query) {
                                $query->where('recommend_id', 0)
                                    ->orWhereNull('recommend_id');
                            });
                        }
                    });
                }
                if (isset($exAdminFilter['search_type'])) {
                    $query->whereHas('player', function ($query) use ($exAdminFilter) {
                        $query->where('is_test', $exAdminFilter['search_type']);
                    });
                }
                $totalData = $query->selectRaw('sum(game_amount) as total_game_amount, sum(turn_point) as total_turn_point, sum(pressure) as total_pressure, sum(score) as total_score,sum(if(`game_amount`<0,`game_amount`,0)) as total_open_amount,sum(if(`game_amount`>0,`game_amount`,0)) as total_wash_amount, sum(chip_amount) as total_chip_amount')->first();
                $data = [
                    [
                        'title' => admin_trans('player_game_log.total_data.total_game_amount'),
                        'number' => !empty($totalData['total_game_amount']) ? floatval($totalData['total_game_amount']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_game_log.total_data.total_chip_amount'),
                        'number' => !empty($totalData['total_chip_amount']) ? floatval($totalData['total_chip_amount']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_game_log.total_data.total_open_amount'),
                        'number' => !empty($totalData['total_open_amount']) ? floatval($totalData['total_open_amount']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_game_log.total_data.total_wash_amount'),
                        'number' => !empty($totalData['total_wash_amount']) ? floatval($totalData['total_wash_amount']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_game_log.total_data.total_pressure'),
                        'number' => !empty($totalData['total_pressure']) ? floatval($totalData['total_pressure']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_game_log.total_data.total_score'),
                        'number' => !empty($totalData['total_score']) ? floatval($totalData['total_score']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_game_log.total_data.total_turn_point'),
                        'number' => !empty($totalData['total_turn_point']) ? floatval($totalData['total_turn_point']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                ];
                break;
            case 'PlayGameRecord':
                $query = PlayGameRecord::query();

                // 代理后台：只统计该代理下级店家的玩家数据
                if (!empty($adminUserId)) {
                    /** @var \addons\webman\model\AdminUser $adminUser */
                    $adminUser = \addons\webman\model\AdminUser::query()->find($adminUserId);
                    if ($adminUser && $adminUser->type === \addons\webman\model\AdminUser::TYPE_AGENT) {
                        // 获取该代理下所有店家的ID
                        $storeIds = $adminUser->childStores()
                            ->where('type', \addons\webman\model\AdminUser::TYPE_STORE)
                            ->pluck('id');
                        // 获取这些店家的所有玩家ID
                        $playerIds = Player::query()
                            ->whereIn('store_admin_id', $storeIds)
                            ->pluck('id');
                        // 只查询这些玩家的游戏记录
                        $query->whereIn('player_id', $playerIds);
                    }
                }
                // 旧逻辑：通过玩家ID过滤（推广员相关）
                elseif (!empty($playerId)) {
                    /** @var Player $player */
                    $player = Player::query()->find($playerId);
                    if (!empty($player->recommend_id)) {
                        $query->where('parent_player_id', $playerId);
                    } else {
                        $query->where('agent_player_id', $playerId);
                    }
                }

                if (!empty($exAdminFilter['created_at_start'])) {
                    $query->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $query->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
                if (!empty($exAdminFilter['platform_id'])) {
                    $query->where('platform_id', $exAdminFilter['platform_id']);
                }
                if (!empty($exAdminFilter['department_id'])) {
                    $query->where('department_id', $exAdminFilter['department_id']);
                }
                if (!empty($exAdminFilter['game_code'])) {
                    $query->where('game_code', $exAdminFilter['game_code']);
                }
                if (!empty($exAdminFilter['order_no'])) {
                    $query->where('order_no', $exAdminFilter['order_no']);
                }
                if (!empty($exAdminFilter['player_uuid'])) {
                    $query->where('player_uuid', 'like', $exAdminFilter['player_uuid'] . '%');
                }
                if (isset($exAdminFilter['date_type'])) {
                    $query->where(getDateWhere($exAdminFilter['date_type'], 'updated_at'));
                }
                if (!empty($exAdminFilter['action_at_start'])) {
                    $query->where('platform_action_at', '>=', $exAdminFilter['action_at_start']);
                }
                if (!empty($exAdminFilter['action_at_end'])) {
                    $query->where('platform_action_at', '<=', $exAdminFilter['action_at_end']);
                }
                if (isset($exAdminFilter['search_type'])) {
                    $query->whereHas('player', function ($query) use ($exAdminFilter) {
                        $query->where('is_test', $exAdminFilter['search_type']);
                    });
                }
                // 店家筛选（应用于统计查询）
                if (!empty($exAdminFilter['player']['store_admin_id'])) {
                    $query->whereHas('player', function ($q) use ($exAdminFilter) {
                        $q->where('store_admin_id', $exAdminFilter['player']['store_admin_id']);
                    });
                }
                $totalData = $query->selectRaw('sum(bet) as all_bet, sum(diff) as all_diff, sum(reward) as all_reward')->first();
                $data = [
                    [
                        'title' => admin_trans('play_game_record.all_bet'),
                        'number' => !empty($totalData['all_bet']) ? floatval($totalData['all_bet']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('play_game_record.all_diff'),
                        'number' => !empty($totalData['all_diff']) ? floatval($totalData['all_diff']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('play_game_record.all_reward'),
                        'number' => !empty($totalData['all_reward']) ? floatval($totalData['all_reward']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                ];
                break;
            case 'MachineReport':
                $query = MachineReport::query();
                if (!empty($departmentId)) {
                    $query->where('department_id', $departmentId);
                }
                if (!empty($exAdminFilter)) {
                    if (!empty($exAdminFilter['date_start'])) {
                        $query->whereDate('date', '>=', $exAdminFilter['date_start']);
                    }
                    if (!empty($exAdminFilter['department_id'])) {
                        $query->where('department_id', $exAdminFilter['department_id']);
                    }
                    if (!empty($exAdminFilter['date_end'])) {
                        $query->whereDate('date', '<=', $exAdminFilter['date_end']);
                    }
                    if (!empty($exAdminFilter['is_test'])) {    //筛选测试数据
                        $query->where('is_test', '=', $exAdminFilter['is_test']);
                    }
                    if (!empty($exAdminFilter['machine'])) {
                        $query->whereHas('machine', function ($query) use ($exAdminFilter) {
                            if (!empty($exAdminFilter['machine']['name'])) {
                                $query->whereHas('machineLabel', function ($query) use ($exAdminFilter) {
                                    $query->where('name', 'like', '%' . $exAdminFilter['machine']['name'] . '%');
                                });
                            }
                            if (!empty($exAdminFilter['machine']['code'])) {
                                $query->where('code', $exAdminFilter['machine']['code']);
                            }
                            if (!empty($exAdminFilter['machine']['producer_id'])) {
                                $query->where('producer_id', $exAdminFilter['machine']['producer_id']);
                            }
                            if (!empty($exAdminFilter['machine']['cate_id'])) {
                                $query->whereIn('cate_id', $exAdminFilter['machine']['cate_id']);
                            }
                        });
                    }
                }
                $machineReport = $query->selectRaw('
                        machine_id,
                        sum(open_amount) as open_amount,
                        sum(wash_amount) as wash_amount,
                        sum(total_amount) as total_amount,
                        sum(open_point) as open_point,
                        sum(wash_point) as wash_point,
                        sum(total_point) as total_point,
                        sum(pressure) as total_pressure,
                        sum(score) as total_score,
                        sum(pressure - score) as total_diff,
                        sum(turn_point) as total_turn_point,
                        sum(lottery_amount) as lottery_amount,
                        sum(activity_amount) as activity_amount,
                        sum(open_amount-wash_amount-lottery_amount-activity_amount) as machine_total_point,
                        odds');
                $where = [];
                if (isset($exAdminFilter['date_type'])) {
                    $where = getDateWhere($exAdminFilter['date_type'], 'date');
                }
                $machineReport->where($where);
                $openAmount = clone $machineReport;
                $washAmount = clone $machineReport;
                $totalAmount = clone $machineReport;
                $openPoint = clone $machineReport;
                $washPoint = clone $machineReport;
                $totalPoint = clone $machineReport;
                $totalActivity = clone $machineReport;
                $totalLottery = clone $machineReport;
                $openAmountTotal = $openAmount->sum('open_amount');
                $washAmountTotal = $washAmount->sum('wash_amount');
                $totalAmount = $totalAmount->sum('total_amount');
                
                $openPointTotal = $openPoint->sum('open_point');
                $washPointTotal = $washPoint->sum('wash_point');
                $totalPointTotal = $totalPoint->sum('total_point');
                $totalActivityAmount = $totalActivity->sum('activity_amount');
                $totalLotteryAmount = $totalLottery->sum('lottery_amount');
                $totalActualWinLoss = ($totalAmount + $totalActivityAmount + $totalLotteryAmount) * -1;
                $data = [
                    [
                        'title' => admin_trans('machine_report.open_amount_total'),
                        'number' => $openAmountTotal,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('machine_report.wash_amount_total'),
                        'number' => $washAmountTotal,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('machine_report.total_amount_total'),
                        'number' => $totalAmount,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('machine_report.open_point_total'),
                        'number' => $openPointTotal,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('machine_report.wash_point_total'),
                        'number' => $washPointTotal,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('machine_report.total_point_total'),
                        'number' => $totalPointTotal,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('machine_report.total_activity_amount'),
                        'number' => $totalActivityAmount,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('machine_report.total_lottery_amount'),
                        'number' => $totalLotteryAmount,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('machine_report.total_actual_win_loss'),
                        'number' => $totalActualWinLoss,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                ];
                break;
            case 'PlayerReport':
                $playGameRecordBaseQuery = PlayGameRecord::query()
                    ->when(!empty($exAdminFilter['uuid']) || !empty($exAdminFilter['real_name']) || !empty($exAdminFilter['phone']) || !empty($exAdminFilter['recommend_promoter']['name']) || (!empty($exAdminFilter['search_is_promoter']) && in_array($exAdminFilter['search_is_promoter'],
                                [
                                    0,
                                    1
                                ])) || !empty($exAdminFilter['department_id']) || !empty($exAdminFilter['search_type']),
                        function (Builder $q) use ($exAdminFilter) {
                            $q->leftjoin('player', 'play_game_record.player_id', '=', 'player.id');
                        });
                $playerDeliveryRecordBaseQuery = PlayerDeliveryRecord::query()->leftjoin('player',
                    'player_delivery_record.player_id', '=', 'player.id');
                if (!empty($exAdminFilter)) {
                    if (!empty($exAdminFilter['uuid'])) {
                        $playGameRecordBaseQuery->where('player.uuid', 'like', '%' . $exAdminFilter['uuid'] . '%');
                    }
                    if (!empty($exAdminFilter['real_name'])) {
                        $playGameRecordBaseQuery->where('player.real_name', 'like',
                            '%' . $exAdminFilter['real_name'] . '%');
                        $playerDeliveryRecordBaseQuery->where('player.real_name', 'like',
                            '%' . $exAdminFilter['real_name'] . '%');
                    }
                    if (!empty($exAdminFilter['phone'])) {
                        $playGameRecordBaseQuery->where('player.phone', 'like', '%' . $exAdminFilter['phone'] . '%');
                        $playerDeliveryRecordBaseQuery->where('player.phone', 'like',
                            '%' . $exAdminFilter['phone'] . '%');
                    }
                    if (!empty($exAdminFilter['recommend_promoter']['name'])) {
                        
                        $playGameRecordBaseQuery->leftjoin('player as rp', 'play_game_record.parent_player_id', '=',
                            'rp.id')
                            ->where(function ($q) use ($exAdminFilter) {
                                $q->where('rp.uuid', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%')
                                    ->orWhere('rp.name', 'like',
                                        '%' . $exAdminFilter['recommend_promoter']['name'] . '%');
                            });
                        $playerDeliveryRecordBaseQuery->whereHas('player', function ($q) use ($exAdminFilter) {
                            $q->whereHas('recommend_promoter', function ($q) use ($exAdminFilter) {
                                $q->whereHas('player', function ($q) use ($exAdminFilter) {
                                    $q->where('uuid', 'like', '%' . $exAdminFilter['recommend_promoter']['name'] . '%')
                                        ->orWhere('name', 'like',
                                            '%' . $exAdminFilter['recommend_promoter']['name'] . '%');
                                });
                            });
                        });
                    }
                    if (!empty($exAdminFilter['search_is_promoter']) && in_array($exAdminFilter['search_is_promoter'],
                            [0, 1])) {
                        $playGameRecordBaseQuery->whereHas('player', function ($q) use ($exAdminFilter) {
                            $q->where('is_promoter', $exAdminFilter['search_is_promoter']);
                        });
                        $playerDeliveryRecordBaseQuery->where('player.is_promoter',
                            $exAdminFilter['search_is_promoter']);
                    }
                    if (!empty($exAdminFilter['department_id'])) {
                        $playGameRecordBaseQuery->where('play_game_record.department_id',
                            $exAdminFilter['department_id']);
                        $playerDeliveryRecordBaseQuery->where('player.department_id', $exAdminFilter['department_id']);
                    }
                    if (!empty($exAdminFilter['search_type'])) {
                        $playGameRecordBaseQuery->where('player.is_test', $exAdminFilter['search_type']);
                        $playerDeliveryRecordBaseQuery->where('player.is_test', $exAdminFilter['search_type']);
                    }
                }
                if (!empty($exAdminFilter)) {
                    if (!empty($exAdminFilter['created_at_start'])) {
                        $playGameRecordBaseQuery->where('play_game_record.created_at', '>=',
                            $exAdminFilter['created_at_start']);
                        $playerDeliveryRecordBaseQuery->where('player_delivery_record.created_at', '>=',
                            $exAdminFilter['created_at_start']);
                    }
                    if (!empty($exAdminFilter['created_at_end'])) {
                        $playGameRecordBaseQuery->where('play_game_record.created_at', '<=',
                            $exAdminFilter['created_at_end']);
                        $playerDeliveryRecordBaseQuery->where('player_delivery_record.created_at', '<=',
                            $exAdminFilter['created_at_end']);
                    }
                    if (!empty($exAdminFilter['type'])) {
                        $playerDeliveryRecordBaseQuery->where('player_delivery_record.type', $exAdminFilter['type']);
                    }
                    if (isset($exAdminFilter['date_type'])) {
                        $playGameRecordBaseQuery->where(getDateWhere($exAdminFilter['date_type'],
                            'play_game_record.created_at'));
                        $playerDeliveryRecordBaseQuery->where(getDateWhere($exAdminFilter['date_type'],
                            'player_delivery_record.created_at'));
                    }
                }
                $summaryDataBetPlayGameRecordBaseQuery = clone $playGameRecordBaseQuery;
                $summaryDataDiffPlayGameRecordBaseQuery = clone $playGameRecordBaseQuery;
                
                $summaryData['bet_total'] = $summaryDataBetPlayGameRecordBaseQuery->sum('bet');
                
                $summaryData['diff_total'] = $summaryDataDiffPlayGameRecordBaseQuery->sum('diff');
                
                $summaryData['self_recharge_total'] = $playerDeliveryRecordBaseQuery->clone()
                    ->where('player_delivery_record.type', PlayerDeliveryRecord::TYPE_RECHARGE)
                    ->whereIn('player_delivery_record.source', ['self_recharge', 'gb_recharge'])
                    ->sum('player_delivery_record.amount');
                
                $summaryData['artificial_recharge_total'] = $playerDeliveryRecordBaseQuery->clone()
                    ->where('player_delivery_record.type', PlayerDeliveryRecord::TYPE_RECHARGE)
                    ->where('player_delivery_record.source', 'artificial_recharge')
                    ->sum('player_delivery_record.amount');
                
                $summaryData['channel_withdrawal_total'] = $playerDeliveryRecordBaseQuery->clone()
                        ->where('player_delivery_record.type', PlayerDeliveryRecord::TYPE_WITHDRAWAL)
                        ->whereIn('player_delivery_record.source', ['channel_withdrawal', 'gb_withdrawal'])
                        ->where('player_delivery_record.withdraw_status', PlayerWithdrawRecord::STATUS_SUCCESS)
                        ->sum('player_delivery_record.amount') * -1;
                
                $summaryData['artificial_withdrawal_total'] = $playerDeliveryRecordBaseQuery->clone()
                        ->where('player_delivery_record.type', PlayerDeliveryRecord::TYPE_WITHDRAWAL)
                        ->where('player_delivery_record.source', 'artificial_withdrawal')
                        ->where('player_delivery_record.withdraw_status', PlayerWithdrawRecord::STATUS_SUCCESS)
                        ->sum('player_delivery_record.amount') * -1;
                
                //玩家转出
                $summaryData['coin_withdraw_total'] = $playerDeliveryRecordBaseQuery->clone()
                    ->where('player_delivery_record.type', PlayerDeliveryRecord::TYPE_PRESENT_IN)
                    ->sum('player_delivery_record.amount');
                
                //币商转入
                $summaryData['coin_transfer_total'] = $playerDeliveryRecordBaseQuery->clone()
                    ->where('player_delivery_record.type', PlayerDeliveryRecord::TYPE_PRESENT_OUT)
                    ->sum('player_delivery_record.amount');
                
                //总上分
                $summaryData['machine_up_total'] = $playerDeliveryRecordBaseQuery->clone()
                    ->where('player_delivery_record.type', PlayerDeliveryRecord::TYPE_MACHINE_UP)
                    ->sum('player_delivery_record.amount');
                //总下分
                $summaryData['machine_down_total'] = $playerDeliveryRecordBaseQuery->clone()
                    ->where('player_delivery_record.type', PlayerDeliveryRecord::TYPE_MACHINE_DOWN)
                    ->sum('player_delivery_record.amount');
                
                //活动奖励
                $summaryData['activity_total'] = $playerDeliveryRecordBaseQuery->clone()
                    ->where('player_delivery_record.type', PlayerDeliveryRecord::TYPE_ACTIVITY_BONUS)
                    ->sum('player_delivery_record.amount');
                //彩金奖励
                $summaryData['lottery_total'] = $playerDeliveryRecordBaseQuery->clone()
                    ->where('player_delivery_record.type', PlayerDeliveryRecord::TYPE_LOTTERY)
                    ->sum('player_delivery_record.amount');
                //管理员加点
                $summaryData['modified_total'] = $playerDeliveryRecordBaseQuery->clone()
                    ->where('player_delivery_record.type', PlayerDeliveryRecord::TYPE_MODIFIED_AMOUNT_ADD)
                    ->sum('player_delivery_record.amount');
                
                //送输赢
                $summaryData['total_diff'] = $summaryData['machine_down_total'] - $summaryData['machine_up_total'] + $summaryData['diff_total'] + $summaryData['activity_total'] + $summaryData['lottery_total'] + $summaryData['modified_total'];
                $summaryData['total_amount'] = $summaryData['self_recharge_total'] + $summaryData['artificial_recharge_total'] + $summaryData['channel_withdrawal_total'] + $summaryData['artificial_withdrawal_total'];
                
                $data = [
                    [
                        'title' => admin_trans('player.self_recharge_total'),
                        'number' => $summaryData['self_recharge_total'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player.artificial_recharge_total'),
                        'number' => $summaryData['artificial_recharge_total'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player.coin_transfer'),
                        'number' => $summaryData['coin_transfer_total'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player.channel_withdrawal_total'),
                        'number' => $summaryData['channel_withdrawal_total'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player.artificial_withdrawal_total'),
                        'number' => $summaryData['artificial_withdrawal_total'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player.coin_withdraw'),
                        'number' => $summaryData['coin_withdraw_total'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player.bet_total'),
                        'number' => $summaryData['bet_total'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player.diff_total'),
                        'number' => $summaryData['diff_total'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player.total_amount'),
                        'number' => $summaryData['total_amount'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_game_log.total_data.total_open_point'),
                        'number' => $summaryData['machine_up_total'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_game_log.total_data.total_wash_point'),
                        'number' => $summaryData['machine_down_total'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player.lottery_total'),
                        'number' => $summaryData['lottery_total'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player.activity_total'),
                        'number' => $summaryData['activity_total'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('reverse_water.fields.all_diff'),
                        'number' => $summaryData['total_diff'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player.modified_total'),
                        'number' => $summaryData['modified_total'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                ];
                break;
            case 'WithdrawRecord':
                $playerWithdrawRecord = PlayerWithdrawRecord::query();
                if (!empty($exAdminFilter)) {
                    if (!empty($exAdminFilter['currency'])) {
                        $playerWithdrawRecord->whereIn('currency', $exAdminFilter['currency']);
                    }
                    if (!empty($exAdminFilter['created_at_start'])) {
                        $playerWithdrawRecord->where('created_at', '>=', $exAdminFilter['created_at_start']);
                    }
                    if (!empty($exAdminFilter['created_at_end'])) {
                        $playerWithdrawRecord->where('created_at', '<=', $exAdminFilter['created_at_end']);
                    }
                    if (!empty($exAdminFilter['finish_time_start'])) {
                        $playerWithdrawRecord->where('finish_time', '>=', $exAdminFilter['finish_time_start']);
                    }
                    if (!empty($exAdminFilter['finish_time_end'])) {
                        $playerWithdrawRecord->where('finish_time', '<=', $exAdminFilter['finish_time_end']);
                    }
                    if (!empty($exAdminFilter['player_id'])) {
                        $playerWithdrawRecord->where('player_id', $exAdminFilter['player_id']);
                    }
                    if (!empty($exAdminFilter['bank_type'])) {
                        $playerWithdrawRecord->where('bank_type', $exAdminFilter['bank_type']);
                    }
                    if (!empty($exAdminFilter['player']['uuid'])) {
                        $playerWithdrawRecord->whereHas('player', function ($query) use ($exAdminFilter) {
                            $query->where('uuid', 'like', '%' . $exAdminFilter['player']['uuid'] . '%');
                        });
                    }
                    if (!empty($exAdminFilter['department_id'])) {
                        $playerWithdrawRecord->where('department_id', $exAdminFilter['department_id']);
                    }
                    if (!empty($exAdminFilter['type'])) {
                        $playerWithdrawRecord->where('type', $exAdminFilter['type']);
                    }
                    if (isset($exAdminFilter['status']) && $exAdminFilter['status'] != null) {
                        $playerWithdrawRecord->where('status', $exAdminFilter['status']);
                    }
                    if (!empty($exAdminFilter['talk_tradeno'])) {
                        $playerWithdrawRecord->where('talk_tradeno', 'like',
                            '%' . $exAdminFilter['talk_tradeno'] . '%');
                    }
                    if (!empty($exAdminFilter['tradeno'])) {
                        $playerWithdrawRecord->where('tradeno', 'like', '%' . $exAdminFilter['tradeno'] . '%');
                    }
                    if (isset($exAdminFilter['date_type'])) {
                        $playerWithdrawRecord->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
                    }
                    if (!empty($exAdminFilter['recommend_uuid'])) {
                        $playerWithdrawRecord->whereHas('player.recommend_player',
                            function ($query) use ($exAdminFilter) {
                                $query->where('uuid', 'like', '%' . $exAdminFilter['recommend_uuid'] . '%');
                            });
                    }
                    if (!empty($exAdminFilter['remark'])) {
                        $playerWithdrawRecord->where('remark', 'like', '%' . $exAdminFilter['remark'] . '%');
                    }
                    if (isset($exAdminFilter['search_type'])) {
                        $playerWithdrawRecord->whereHas('player', function ($query) use ($exAdminFilter) {
                            $query->where('is_test', $exAdminFilter['search_type']);
                        });
                    }
                }
                
                $totalData = $playerWithdrawRecord->selectRaw(
                    'sum(IF(type = 1, point,0)) as total_talk_inmoney,
                            sum(IF(type = 2, point,0)) as total_self_inmoney,
                            sum(IF(type = 3, point,0)) as total_artificial_inmoney,
                            sum(IF(type = 4, point,0)) as total_gb_point,
                            sum(IF(type = 2 and bank_type = 1, money,0)) as total_usdt_inmoney,
                            sum(IF(type = 5, point,0)) as total_coin_inmoney'
                )->first();
                $data = [
                    [
                        'title' => admin_trans('player_withdraw_record.total_data.total_talk_inmoney'),
                        'number' => $totalData['total_talk_inmoney'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_withdraw_record.total_data.total_self_inmoney'),
                        'number' => $totalData['total_self_inmoney'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_withdraw_record.total_data.total_artificial_inmoney'),
                        'number' => $totalData['total_artificial_inmoney'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_withdraw_record.total_data.total_gb_point'),
                        'number' => $totalData['total_gb_point'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_withdraw_record.total_data.total_usdt_inmoney'),
                        'number' => $totalData['total_usdt_inmoney'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_withdraw_record.total_data.total_coin_inmoney'),
                        'number' => $totalData['total_coin_inmoney'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                ];
                break;
            case 'RechargeRecord':
                $playerRechargeRecord = PlayerRechargeRecord::query();
                if (!empty($exAdminFilter)) {
                    if (!empty($exAdminFilter['remark'])) {
                        $playerRechargeRecord->where('remark', 'like', "%{$exAdminFilter['remark']}%");
                    }
                    if (!empty($exAdminFilter['created_at_start'])) {
                        $playerRechargeRecord->where('created_at', '>=', $exAdminFilter['created_at_start']);
                    }
                    if (!empty($exAdminFilter['created_at_end'])) {
                        $playerRechargeRecord->where('created_at', '<=', $exAdminFilter['created_at_end']);
                    }
                    if (!empty($exAdminFilter['finish_time_start'])) {
                        $playerRechargeRecord->where('finish_time', '>=', $exAdminFilter['finish_time_start']);
                    }
                    if (!empty($exAdminFilter['finish_time_end'])) {
                        $playerRechargeRecord->where('finish_time', '<=', $exAdminFilter['finish_time_end']);
                    }
                    if (!empty($exAdminFilter['player_id'])) {
                        $playerRechargeRecord->where('player_id', $exAdminFilter['player_id']);
                    }
                    if (!empty($exAdminFilter['player']['uuid'])) {
                        $playerRechargeRecord->whereHas('player', function ($query) use ($exAdminFilter) {
                            $query->where('uuid', 'like', '%' . $exAdminFilter['player']['uuid'] . '%');
                        });
                    }
                    if (!empty($exAdminFilter['channel_recharge_setting']['type'])) {
                        $playerRechargeRecord->whereHas('channel_recharge_setting',
                            function ($query) use ($exAdminFilter) {
                                $query->where('type', $exAdminFilter['channel_recharge_setting']['type']);
                            });
                    }
                    if (!empty($exAdminFilter['department_id'])) {
                        $playerRechargeRecord->where('department_id', $exAdminFilter['department_id']);
                    }
                    if (!empty($exAdminFilter['type'])) {
                        $playerRechargeRecord->where('type', $exAdminFilter['type']);
                    }
                    if (isset($exAdminFilter['status']) && $exAdminFilter['status'] != null) {
                        $playerRechargeRecord->where('status', $exAdminFilter['status']);
                    }
                    if (!empty($exAdminFilter['talk_tradeno'])) {
                        $playerRechargeRecord->where('talk_tradeno', 'like',
                            '%' . $exAdminFilter['talk_tradeno'] . '%');
                    }
                    if (!empty($exAdminFilter['tradeno'])) {
                        $playerRechargeRecord->where('tradeno', 'like', '%' . $exAdminFilter['tradeno'] . '%');
                    }
                    
                    if (isset($exAdminFilter['date_type'])) {
                        $playerRechargeRecord->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
                    }
                    if (!empty($exAdminFilter['recommend_uuid'])) {
                        $playerRechargeRecord->whereHas('player.recommend_player',
                            function ($query) use ($exAdminFilter) {
                                $query->where('uuid', 'like', '%' . $exAdminFilter['recommend_uuid'] . '%');
                            });
                    }
                    if (isset($exAdminFilter['search_type'])) {
                        $playerRechargeRecord->whereHas('player', function ($query) use ($exAdminFilter) {
                            $query->where('is_test', $exAdminFilter['search_type']);
                        });
                    }
                }
                $totalData = $playerRechargeRecord->selectRaw(
                    "sum(IF(type = 1 and status = 2, point,0)) as total_third_inmoney,
                sum(IF(type = 2 and status = 2, point,0)) as total_self_inmoney,
                sum(IF(type = 3 and status = 2, point,0)) as total_business_inmoney,
                sum(IF(type = 4 and status = 2, point,0)) as total_artificial_inmoney,
                sum(IF(currency = 'USDT' and status = 2, inmoney,0)) as usdt_inmoney,
                sum(IF(type = 5 and status = 2, point,0)) as total_gb_inmoney,
                sum(IF(type = 6 and status = 2, point,0)) as total_machine_inmoney,
                sum(IF(type = 7 and status = 2, point,0)) as total_eh_inmoney
                "
                )->first();
                $data = [
                    [
                        'title' => admin_trans('player_recharge_record.total_data.total_third_inmoney'),
                        'number' => $totalData['total_third_inmoney'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_recharge_record.total_data.total_self_inmoney'),
                        'number' => $totalData['total_self_inmoney'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_recharge_record.total_data.total_artificial_inmoney'),
                        'number' => $totalData['total_artificial_inmoney'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_recharge_record.total_data.total_business_inmoney'),
                        'number' => $totalData['total_business_inmoney'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_recharge_record.total_data.usdt_inmoney'),
                        'number' => $totalData['usdt_inmoney'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_recharge_record.total_data.total_gb_inmoney'),
                        'number' => $totalData['total_gb_inmoney'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_recharge_record.total_data.total_machine_inmoney'),
                        'number' => $totalData['total_machine_inmoney'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_recharge_record.total_data.total_eh_inmoney'),
                        'number' => $totalData['total_eh_inmoney'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                ];
                break;
            case 'PlayerPresent':
                $playerPresentRecord = PlayerPresentRecord::query();
                if (!empty($exAdminFilter['created_at_start'])) {
                    $playerPresentRecord->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $playerPresentRecord->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
                if (isset($exAdminFilter['user_search_type'])) {
                    $playerPresentRecord->whereHas('user', function ($query) use ($exAdminFilter) {
                        $query->where('is_test', $exAdminFilter['user_search_type']);
                    });
                }
                if (isset($exAdminFilter['player_search_type'])) {
                    $playerPresentRecord->whereHas('player', function ($query) use ($exAdminFilter) {
                        $query->where('is_test', $exAdminFilter['player_search_type']);
                    });
                }
                if (!empty($exAdminFilter['user_id'])) {
                    $playerPresentRecord->where('user_id', '=', $exAdminFilter['user_id']);
                }
                if (!empty($exAdminFilter['player_id'])) {
                    $playerPresentRecord->where('player_id', '=', $exAdminFilter['player_id']);
                }
                $totalData = $playerPresentRecord->selectRaw('sum(IF(type = 2, amount,0)) as total_icon_amount, sum(IF(type = 1, amount,0)) as total_player_amount')->first();
                $data = [
                    [
                        'title' => admin_trans('player_present_record.total_data.total_icon_amount'),
                        'number' => $totalData['total_icon_amount'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_present_record.total_data.total_player_amount'),
                        'number' => $totalData['total_player_amount'] ?? 0,
                        'prefix' => '',
                        'suffix' => ''
                    ]
                ];
                break;
            case 'AgentLottery':
                // 代理后台彩金统计
                /** @var \addons\webman\model\AdminUser $currentAdmin */
                $currentAdmin = Admin::user();

                // 创建基础查询（只包含权限过滤）
                $baseQuery = PlayerLotteryRecord::query()
                    ->whereHas('player', function($query) use ($currentAdmin) {
                        $query->where('agent_admin_id', $currentAdmin->id);
                    });

                // 克隆基础查询用于基础统计（会应用所有筛选条件）
                $query = clone $baseQuery;

                // 应用筛选条件
                if (!empty($exAdminFilter['created_at_start'])) {
                    $query->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $query->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
                if (!empty($exAdminFilter['status'])) {
                    $query->where('status', $exAdminFilter['status']);
                }
                if (!empty($exAdminFilter['lottery_type'])) {
                    $query->where('lottery_type', $exAdminFilter['lottery_type']);
                }
                if (!empty($exAdminFilter['lottery_name'])) {
                    $query->where('lottery_name', 'like', '%' . $exAdminFilter['lottery_name'] . '%');
                }
                if (!empty($exAdminFilter['machine_code'])) {
                    $query->where('machine_code', 'like', '%' . $exAdminFilter['machine_code'] . '%');
                }
                if (!empty($exAdminFilter['machine_name'])) {
                    $query->where('machine_name', 'like', '%' . $exAdminFilter['machine_name'] . '%');
                }
                if (!empty($exAdminFilter['player_phone'])) {
                    $query->where('player_phone', 'like', '%' . $exAdminFilter['player_phone'] . '%');
                }
                if (!empty($exAdminFilter['uuid'])) {
                    $query->where('uuid', $exAdminFilter['uuid']);
                }
                if (!empty($exAdminFilter['search_type'])) {
                    $query->where('is_test', $exAdminFilter['search_type']);
                }
                if (!empty($exAdminFilter['search_is_promoter'])) {
                    $query->where('is_promoter', $exAdminFilter['search_is_promoter']);
                }
                // 所属店家筛选
                if (!empty($exAdminFilter['player']['store_admin_id'])) {
                    $query->whereHas('player', function ($q) use ($exAdminFilter) {
                        $q->where('store_admin_id', $exAdminFilter['player']['store_admin_id']);
                    });
                }
                // 游戏分类筛选
                if (!empty($exAdminFilter['cate_id'])) {
                    $cate_id = $exAdminFilter['cate_id'];
                    $query->whereHas('machine', function ($q) use ($cate_id) {
                        $q->whereIn('cate_id', $cate_id);
                    });
                }
                // 日期类型筛选
                if (isset($exAdminFilter['date_type'])) {
                    $query->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
                }

                // 统计数据（应用所有筛选条件）
                $totalData = $query->selectRaw('
                    SUM(IF(status = ' . PlayerLotteryRecord::STATUS_UNREVIEWED . ', amount, 0)) as total_unreviewed_amount,
                    SUM(IF(status = ' . PlayerLotteryRecord::STATUS_REJECT . ', amount, 0)) as total_reject_amount,
                    SUM(IF(status = ' . PlayerLotteryRecord::STATUS_PASS . ', amount, 0)) as total_pass_amount,
                    SUM(IF(status = ' . PlayerLotteryRecord::STATUS_COMPLETE . ', amount, 0)) as total_complete_amount,
                    COUNT(*) as total_count
                ')->first();

                // 按游戏类型分组统计（只保留权限过滤和时间范围，不受其他筛选影响）
                $jpQuery = clone $baseQuery;
                // 只应用时间范围筛选，不应用status、lottery_type等筛选
                if (!empty($exAdminFilter['created_at_start'])) {
                    $jpQuery->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $jpQuery->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
                if (isset($exAdminFilter['date_type'])) {
                    $jpQuery->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
                }
                if (!empty($exAdminFilter['search_type'])) {
                    $jpQuery->where('is_test', $exAdminFilter['search_type']);
                }

                $jpTotalData = $jpQuery->selectRaw('IFNULL(SUM(amount), 0) as total_amount, game_type, lottery_id, lottery_name, lottery_type, lottery_sort')
                    ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
                    ->groupBy('game_type', 'lottery_id', 'lottery_name', 'lottery_type', 'lottery_sort')
                    ->orderBy('lottery_type')
                    ->orderBy('lottery_sort')
                    ->get();

                $data = [
                    [
                        'title' => admin_trans('player_lottery_record.total_data.total_complete_amount'),
                        'number' => !empty($totalData['total_complete_amount']) ? floatval($totalData['total_complete_amount']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_lottery_record.total_data.total_pass_amount'),
                        'number' => !empty($totalData['total_pass_amount']) ? floatval($totalData['total_pass_amount']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_lottery_record.total_data.total_unreviewed_amount'),
                        'number' => !empty($totalData['total_unreviewed_amount']) ? floatval($totalData['total_unreviewed_amount']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_lottery_record.total_data.total_reject_amount'),
                        'number' => !empty($totalData['total_reject_amount']) ? floatval($totalData['total_reject_amount']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_lottery_record.total_data.total_count'),
                        'number' => !empty($totalData['total_count']) ? intval($totalData['total_count']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                ];

                // 添加游戏类型分组统计
                foreach ($jpTotalData as $value) {
                    // 老虎机类型
                    if ($value->game_type == GameType::TYPE_SLOT) {
                        $data[] = [
                            'title' => $value->lottery_name,
                            'number' => floatval($value->total_amount),
                            'prefix' => admin_trans('game_type.game_type.' . GameType::TYPE_SLOT) . ' - ',
                            'suffix' => ''
                        ];
                    }
                    // 钢珠机类型
                    elseif ($value->game_type == GameType::TYPE_STEEL_BALL) {
                        $data[] = [
                            'title' => $value->lottery_name,
                            'number' => floatval($value->total_amount),
                            'prefix' => admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL) . ' - ',
                            'suffix' => ''
                        ];
                    }
                }

                break;

            case 'PlayerLotteryRecord':
            case 'ChannelPlayerLotteryRecord':
                // 创建基础查询（只包含权限过滤）
                $baseQuery = PlayerLotteryRecord::query();

                // 只有店家后台需要数据权限过滤
                if ($type === 'ChannelPlayerLotteryRecord') {
                    // 获取当前用户信息（用于店家权限过滤）
                    $currentPlayerId = Admin::user()->player_id ?? 0;
                    $currentPlayer = Player::query()->find($currentPlayerId);

                    // 数据权限过滤：根据店家类型过滤玩家
                    if (!empty($currentPlayer)) {
                        if ($currentPlayer->type == Player::PLAYER_TYPE_STORE_MACHINE) {
                            // 店家：只显示其直接推荐的玩家的彩金记录
                            $baseQuery->whereExists(function($q) use ($currentPlayerId) {
                                $q->selectRaw(1)
                                    ->from('player')
                                    ->whereColumn('player.id', 'player_lottery_record.player_id')
                                    ->where('player.recommend_id', $currentPlayerId);
                            });
                        } elseif ($currentPlayer->type == Player::PLAYER_TYPE_AGENT) {
                            // 代理：显示其下级店家推荐的所有玩家的彩金记录
                            $baseQuery->whereExists(function($q) use ($currentPlayerId) {
                                $q->selectRaw(1)
                                    ->from('player as p1')
                                    ->join('player as p2', 'p1.id', '=', 'p2.recommend_id')
                                    ->whereColumn('p2.id', 'player_lottery_record.player_id')
                                    ->where('p1.type', Player::PLAYER_TYPE_STORE_MACHINE)
                                    ->where('p1.recommend_id', $currentPlayerId);
                            });
                        } else {
                            // 其他类型不显示数据
                            $baseQuery->where('player_id', 0);
                        }
                    }
                }

                // 克隆基础查询用于基础统计（会应用所有筛选条件）
                $query = clone $baseQuery;

                // 应用筛选条件
                if (!empty($exAdminFilter['created_at_start'])) {
                    $query->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $query->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
                if (!empty($exAdminFilter['status'])) {
                    $query->where('status', $exAdminFilter['status']);
                }
                if (!empty($exAdminFilter['lottery_type'])) {
                    $query->where('lottery_type', $exAdminFilter['lottery_type']);
                }
                if (isset($exAdminFilter['date_type'])) {
                    $query->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
                }
                if (!empty($exAdminFilter['search_type'])) {
                    $query->where('is_test', $exAdminFilter['search_type']);
                }

                // 统计数据（应用所有筛选条件）
                $totalData = $query->selectRaw('
                    SUM(IF(status = ' . PlayerLotteryRecord::STATUS_UNREVIEWED . ', amount, 0)) as total_unreviewed_amount,
                    SUM(IF(status = ' . PlayerLotteryRecord::STATUS_REJECT . ', amount, 0)) as total_reject_amount,
                    SUM(IF(status = ' . PlayerLotteryRecord::STATUS_PASS . ', amount, 0)) as total_pass_amount,
                    SUM(IF(status = ' . PlayerLotteryRecord::STATUS_COMPLETE . ', amount, 0)) as total_complete_amount,
                    COUNT(*) as total_count
                ')->first();

                // 按游戏类型分组统计（只保留权限过滤和时间范围，不受其他筛选影响）
                $jpQuery = clone $baseQuery;
                // 只应用时间范围筛选，不应用status、lottery_type等筛选
                if (!empty($exAdminFilter['created_at_start'])) {
                    $jpQuery->where('created_at', '>=', $exAdminFilter['created_at_start']);
                }
                if (!empty($exAdminFilter['created_at_end'])) {
                    $jpQuery->where('created_at', '<=', $exAdminFilter['created_at_end']);
                }
                if (isset($exAdminFilter['date_type'])) {
                    $jpQuery->where(getDateWhere($exAdminFilter['date_type'], 'created_at'));
                }
                if (!empty($exAdminFilter['search_type'])) {
                    $jpQuery->where('is_test', $exAdminFilter['search_type']);
                }

                $jpTotalData = $jpQuery->selectRaw('IFNULL(SUM(amount), 0) as total_amount, game_type, lottery_id, lottery_name, lottery_type, lottery_sort')
                    ->where('status', PlayerLotteryRecord::STATUS_COMPLETE)
                    ->groupBy('game_type', 'lottery_id', 'lottery_name', 'lottery_type', 'lottery_sort')
                    ->orderBy('lottery_type')
                    ->orderBy('lottery_sort')
                    ->get();

                $data = [
                    [
                        'title' => admin_trans('player_lottery_record.total_data.total_complete_amount'),
                        'number' => !empty($totalData['total_complete_amount']) ? floatval($totalData['total_complete_amount']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_lottery_record.total_data.total_pass_amount'),
                        'number' => !empty($totalData['total_pass_amount']) ? floatval($totalData['total_pass_amount']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_lottery_record.total_data.total_unreviewed_amount'),
                        'number' => !empty($totalData['total_unreviewed_amount']) ? floatval($totalData['total_unreviewed_amount']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_lottery_record.total_data.total_reject_amount'),
                        'number' => !empty($totalData['total_reject_amount']) ? floatval($totalData['total_reject_amount']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                    [
                        'title' => admin_trans('player_lottery_record.total_data.total_count'),
                        'number' => !empty($totalData['total_count']) ? intval($totalData['total_count']) : 0,
                        'prefix' => '',
                        'suffix' => ''
                    ],
                ];

                // 添加游戏类型分组统计
                foreach ($jpTotalData as $value) {
                    // 老虎机类型
                    if ($value->game_type == GameType::TYPE_SLOT) {
                        $data[] = [
                            'title' => $value->lottery_name,
                            'number' => floatval($value->total_amount),
                            'prefix' => admin_trans('game_type.game_type.' . GameType::TYPE_SLOT) . ' - ',
                            'suffix' => ''
                        ];
                    }
                    // 钢珠机类型
                    elseif ($value->game_type == GameType::TYPE_STEEL_BALL) {
                        $data[] = [
                            'title' => $value->lottery_name,
                            'number' => floatval($value->total_amount),
                            'prefix' => admin_trans('game_type.game_type.' . GameType::TYPE_STEEL_BALL) . ' - ',
                            'suffix' => ''
                        ];
                    }
                }

                break;
        }
        return Response::success($data);
    }
}
