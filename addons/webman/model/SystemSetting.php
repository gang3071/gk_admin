<?php

namespace addons\webman\model;

use addons\webman\traits\DataPermissions;
use addons\webman\traits\HasDateTimeFormatter;
use Illuminate\Database\Eloquent\Model;
use support\Cache;

/**
 * Class SystemSetting
 * @property int id 主键
 * @property int department_id 部门/渠道id
 * @property string feature 功能名稱
 * @property int num 数量
 * @property string content 内容
 * @property string date_start 开始时间
 * @property string date_end 结束时间
 * @property int status 状态
 * @property string created_at 创建时间
 * @property string updated_at 最后一次修改时间
 *
 * @package app\model
 */
class SystemSetting extends Model
{
    use HasDateTimeFormatter, DataPermissions;
    
    protected $fillable = ['department_id', 'feature', 'num', 'content', 'date_start', 'date_end', 'status'];
    
    //数据权限字段
    protected $dataAuth = ['department_id' => 'department_id'];
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(plugin()->webman->config('database.system_setting_table'));
    }
    
    /**
     * 模型的 "booted" 方法
     *
     * @return void
     */
    protected static function booted()
    {
        // 保存前加密敏感字段
        static::saving(function (SystemSetting $setting) {
            if ($setting->feature === 'turn_relay_ip' && !empty($setting->content)) {
                // 检查是否已经加密过（避免重复加密）
                if (!$setting->isEncrypted($setting->content)) {
                    $setting->attributes['content'] = \encrypt_sensitive($setting->content);
                }
            }
        });

        static::created(function (SystemSetting $setting) {
            $cacheKey = 'setting-' . $setting->feature . '-' . $setting->department_id;
            Cache::set($cacheKey, $setting);
        });
        static::deleted(function (SystemSetting $setting) {
            $cacheKey = 'setting-' . $setting->feature . '-' . $setting->department_id;
            Cache::delete($cacheKey);
        });
        static::updated(function (SystemSetting $setting) {
            $cacheKey = 'setting-' . $setting->feature . '-' . $setting->department_id;
            Cache::set($cacheKey, $setting);

            // 客户端维护配置更新后立即推送
            if ($setting->feature === 'client_maintain') {
                // 检查是否修改了 status、num、date_start 或 date_end 字段（使用 wasChanged 方法）
                $triggerFields = ['status', 'num', 'date_start', 'date_end'];
                $shouldTrigger = false;

                foreach ($triggerFields as $field) {
                    if ($setting->wasChanged($field)) {
                        $shouldTrigger = true;
                        break;
                    }
                }

                if ($shouldTrigger) {
                    try {
                        $service = new \app\service\ClientMaintainService();
                        $redis = \support\Redis::connection();

                        // 清除推送缓存和状态缓存
                        $departmentId = $setting->department_id ?? 0;
                        $configKey = $departmentId . ':' . $setting->id;
                        $redis->del('client_maintain:notified:' . $configKey . ':start');
                        $redis->del('client_maintain:notified:' . $configKey . ':end');
                        $redis->del('client_maintain:status:' . $departmentId);

                        // 立即检查并推送
                        $service->checkAndNotify();

                        \support\Log::info('客户端维护配置更新，触发立即推送', [
                            'trigger' => 'model_updated',
                            'config_id' => $setting->id,
                            'department_id' => $departmentId,
                            'changed_fields' => array_keys($setting->getChanges()),
                        ]);
                    } catch (\Throwable $e) {
                        \support\Log::error('客户端维护配置更新推送失败', [
                            'error' => $e->getMessage(),
                            'config_id' => $setting->id,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * 检查内容是否已加密
     * @param string $value
     * @return bool
     */
    private function isEncrypted(string $value): bool
    {
        // 如果能base64解码且长度符合加密格式，认为已加密
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }
        // 加密后的数据应该比原始数据长
        return strlen($decoded) > strlen($value);
    }

    /**
     * content 字段访问器 - 自动解密
     * @param string|null $value
     * @return string|null
     */
    public function getContentAttribute($value)
    {
        if ($this->feature === 'turn_relay_ip' && !empty($value)) {
            return \decrypt_sensitive($value);
        }
        return $value;
    }
}