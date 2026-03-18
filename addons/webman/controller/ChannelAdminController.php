<?php

namespace addons\webman\controller;

use addons\webman\Admin;
use addons\webman\model\AdminDepartment;
use addons\webman\model\AdminRole;
use addons\webman\model\AdminRoleUsers;
use addons\webman\model\AdminUser;
use addons\webman\model\Channel;
use ExAdmin\ui\component\common\Html;
use ExAdmin\ui\component\form\Form;
use ExAdmin\ui\component\grid\badge\Badge;
use ExAdmin\ui\component\grid\grid\Actions;
use ExAdmin\ui\component\grid\grid\Filter;
use ExAdmin\ui\component\grid\grid\Grid;
use ExAdmin\ui\component\grid\tag\Tag;
use ExAdmin\ui\support\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * 渠道用户管理
 * @group channel
 */
class ChannelAdminController
{
    protected $model;
    
    public function __construct()
    {
        $this->model = plugin()->webman->config('database.user_model');
        
    }
    
    /**
     * 渠道用户
     * @group channel
     * @auth true
     */
    public function index(): Grid
    {
        /** @var AdminUser $superAdmin */
        $superAdmin = AdminUser::where('department_id', Admin::user()->department_id)->where('is_super', 1)->first();
        return Grid::create(new $this->model, function (Grid $grid) use ($superAdmin) {
            $grid->title(admin_trans('admin.system_user'));
            $grid->model()
                ->when(plugin()->webman->config('admin_auth_id') != Admin::id(), function (Builder $builder) {
                    $builder->whereKeyNot(plugin()->webman->config('admin_auth_id'));
                })->where('department_id', Admin::user()->department_id)->orderBy('id', 'desc');
            $grid->bordered(true);
            $exAdminFilter = Request::input('ex_admin_filter', []);
            if (isset($exAdminFilter['created_at_start']) && !empty($exAdminFilter['created_at_start'])) {
                $grid->model()->where('created_at', '>=', $exAdminFilter['created_at_start']);
            }
            if (isset($exAdminFilter['created_at_end']) && !empty($exAdminFilter['created_at_end'])) {
                $grid->model()->where('created_at', '<=', $exAdminFilter['created_at_end']);
            }
            $grid->autoHeight();
            $grid->userInfo();
            $grid->column('username', admin_trans('admin.fields.username'))->display(function ($val, $data) {
                if ($data['id'] == plugin()->webman->config('admin_auth_id')) {
                    return Html::create()
                        ->content($val)
                        ->content(
                            Badge::create()->count(admin_trans('admin.super_admin'))->numberStyle([
                                'backgroundColor' => '#1890ff',
                                'marginLeft' => '5px'
                            ])
                        );
                } else {
                    return $val;
                }
            })->copy();
            $grid->column('phone', admin_trans('admin.fields.phone'));
            $grid->column('player.uuid', 'UUID')->copy();
            $grid->column('email', admin_trans('admin.fields.mail'));
            $grid->column('role', admin_trans('admin.admin_role'))->display(function ($val, $data) {
                $roleIds = AdminRoleUsers::query()->where('user_id', $data['id'])->get()->pluck('role_id');
                $roleList = AdminRole::query()->whereIn('id', $roleIds)->get();
                $tags = [];
                /** @var AdminRole $role */
                foreach ($roleList as $role) {
                    $tags[] = Tag::create($role->name)->style([
                        'margin-top' => '1px'
                    ])->color('green');
                }
                return Html::create()->content($tags)->style([
                    'display' => 'inline-flex',
                    'text-align' => 'center',
                    'width' => '100px',
                    'justify-content' => 'space-around',
                    'flex-wrap' => 'wrap'
                ]);
            })->width('100px')->align('center');
            $grid->column('status', admin_trans('admin.fields.status'))->switch();
            $grid->column('type', admin_trans('admin.fields.type'))
                ->display(function ($value, AdminUser $data) {
                    if ($data->is_super == 1) {
                        $tag = Tag::create(admin_trans('admin.fields.is_super'))->color('#3b5999');
                    } else {
                        $tag = Tag::create(admin_trans('department.type.' . AdminDepartment::TYPE_CHANNEL))->color('#f50');
                    }
                    return Html::create()->content([
                        $tag,
                    ]);
                })->sortable();
            $grid->column('created_at', admin_trans('admin.fields.create_at'));
            $grid->expandFilter();
            $grid->hideDelete();
            $grid->setForm()->modal($this->form());
            $grid->filter(function (Filter $filter) {
                $filter->like()->text('username')->placeholder(admin_trans('admin.fields.username'));
                $filter->like()->text('phone')->placeholder(admin_trans('admin.fields.phone'));
                $filter->like()->text('player.uuid')->placeholder(admin_trans('player.fields.uuid'));
                $filter->eq()->select('status')
                    ->placeholder(admin_trans('admin.fields.status'))
                    ->showSearch()
                    ->style(['width' => '200px'])
                    ->dropdownMatchSelectWidth()
                    ->options([
                        1 => admin_trans('admin.normal'),
                        0 => admin_trans('admin.disable')
                    ]);
                $filter->form()->hidden('created_at_start');
                $filter->form()->hidden('created_at_end');
                $filter->form()->dateRange('created_at_start', 'created_at_end',
                    '')->placeholder([admin_trans('public_msg.date_start'), admin_trans('public_msg.date_end')]);
            });
            
            $grid->actions(function (Actions $actions, AdminUser $data) use ($superAdmin) {
                if ($data['id'] == $superAdmin->id) {
                    $actions->hideDel();
                }
                if ($data['id'] != $superAdmin->id) {
                    $actions->dropdown()
                        ->prepend(admin_trans('admin.reset_password'), 'fas fa-key')
                        ->modal($this->resetPassword());
                } else {
                    $actions->dropdown();
                }
            });
            
            $grid->deling(function ($ids) use ($superAdmin) {
                if (is_array($ids) && in_array($superAdmin->id, $ids)) {
                    return message_error(admin_trans('admin.super_admin_delete'));
                }
            });
            
            $grid->updateing(function ($ids, $data) use ($superAdmin) {
                if (in_array($superAdmin->id, $ids)) {
                    if (isset($data['status']) && $data['status'] == 0) {
                        return message_error(admin_trans('admin.super_admin_disabled'));
                    }
                }
            });
        });
    }
    
    /**
     * 系统用户
     * @group channel
     * @auth true
     */
    public function form(): Form
    {
        return Form::create(new $this->model, function (Form $form) {
            $form->title(admin_trans('admin.system_user'));
            $form->text('username', admin_trans('admin.fields.username'))
                ->ruleChsDash()
                ->rule([
                    (string)Rule::unique(plugin()->webman->config('database.user_model'))->ignore($form->input('id')) => admin_trans('admin.username_exist'),
                ])
                ->required()
                ->disabled($form->isEdit());
            $form->text('nickname', admin_trans('admin.fields.nickname'))
                ->ruleChsAlphaNum()
                ->required();
            $form->image('avatar', admin_trans('admin.fields.avatar'))
                ->required();
            if (!$form->isEdit()) {
                $form->password('password', admin_trans('admin.fields.password'))
                    ->default(123456)
                    ->help(admin_trans('admin.pass_help'))
                    ->required();
            }
            $form->text('phone', admin_trans('admin.fields.phone'))
                ->rule([
                    (string)Rule::unique(plugin()->webman->config('database.user_model'))->ignore($form->input('id')) => admin_trans('admin.phone_exist'),
                ])
                ->ruleMobile();
            $form->text('email', admin_trans('admin.fields.mail'))->ruleEmail();
            $form->hidden('department_id')->default(Admin::user()->department_id);
            $form->hidden('type')->default(2);
            if (!$form->isEdit() || $form->driver()->get('is_super') != 1) {
                $roleModel = plugin()->webman->config('database.role_model');
                /** @var Channel $channel */
                $role = $roleModel::where('type', AdminDepartment::TYPE_CHANNEL)
                    ->whereNotIn('id', [config('app.agent_role'), config('app.store_role')])
                    ->pluck('name', 'id')
                    ->toArray();
                $form->checkbox('roles', admin_trans('admin.access_rights'))
                    ->options($role);
                $post = plugin()->webman->config('database.post_model');
                $options = $post::where('status', 1)->pluck('name', 'id')->toArray();
                $form->select('post', admin_trans('admin.post'))
                    ->options($options)
                    ->multiple();
            }
        });
    }

    /**
     * 重置密码
     * @auth true
     * @group channel
     * @return Form
     */
    public function resetPassword(): Form
    {
        return Form::create(new $this->model, function (Form $form) {
            $form->password('password', admin_trans('admin.new_password'))
                ->rule([
                    'confirmed' => admin_trans('admin.password_confim_validate'),
                    'min:6' => admin_trans('admin.password_min_number')
                ])
                ->value('')
                ->required();
            $form->password('password_confirmation', admin_trans('admin.confim_password'))
                ->required();
        });
    }
}
