<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Auth;
use Spatie\Permission\Traits\HasRoles;

use Tymon\JWTAuth\Contracts\JWTSubject;
/*
 * 得到了访问令牌，我们就能通过令牌获取个人用户信息了，不过在这之前我们需要修改一些配置。
 * 将 Laravel\Passport\HasApiTokens Trait 添加到 App\Models\User 模型中，
 * 这个 Trait 会给你的模型提供一些辅助函数，用于检查已认证用户的令牌和使用范围。
 * */
use Laravel\Passport\HasApiTokens;
class User extends Authenticatable implements JWTSubject
{
    //use Notifiable;
    use Notifiable {
        notify as protected laravelNotify;
    }
    /*
     * 得到了访问令牌，我们就能通过令牌获取个人用户信息了，不过在这之前我们需要修改一些配置。
     * 将 Laravel\Passport\HasApiTokens Trait 添加到 App\Models\User 模型中，
     * 这个 Trait 会给你的模型提供一些辅助函数，用于检查已认证用户的令牌和使用范围。
     * */
    use HasApiTokens;
    public function notify($instance)
    {
        // 如果要通知的人是当前用户，就不必通知了！
        if ($this->id == Auth::id()) {
            return;
        }
        $this->increment('notification_count');
        $this->laravelNotify($instance);
    }
    use HasRoles;

    use Traits\ActiveUserHelper;
    use Traits\LastActivedAtHelper;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'phone', 'email', 'password', 'introduction', 'avatar',
        'weixin_openid', 'weixin_unionid', 'registration_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }
    public function isAuthorOf($model)
    {
        return $this->id == $model->user_id;
    }
    
    public function replies()
    {
        return $this->hasMany(Reply::class);
    }

    /*
     * 清空未读提醒的状态
     * */
    public function markAsRead()
    {
        $this->notification_count = 0;
        $this->save();
        $this->unreadNotifications->markAsRead();
    }

    /*
     * 当我们给属性赋值时，如 $user->password = 'password'，该修改器将被自动调用：
     * */
    public function setPasswordAttribute($value)
    {
        // 如果值的长度等于 60，即认为是已经做过加密的情况
        if (strlen($value) != 60) {

            // 不等于 60，做密码加密处理
            $value = bcrypt($value);
        }

        $this->attributes['password'] = $value;
    }
    /*
     * 同时，我们也需要考虑到其他地方对 avatar 的赋值，用户修改资料时，我们为 avatar 的赋值就是全路径，因此同样的我们需要考虑两种状况：
     * */
    public function setAvatarAttribute($path)
    {
        // 如果不是 `http` 子串开头，那就是从后台上传的，需要补全 URL
        if ( ! starts_with($path, 'http')) {

            // 拼接完整的 URL
            $path = config('app.url') . "/uploads/images/avatars/$path";
        }

        $this->attributes['avatar'] = $path;
    }

    // Rest omitted for brevity

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    /*
   * http://{{host}}/api/authorizations 最终结果报错了，
   * 因为默认情况下，Passport 会通过用户的邮箱查找用户，要支持手机登录，
   * 我们可以在用户模型定义了 findForPassport 方法，
   * Passport 会先检测用户模型是否存在 findForPassport 方法，
   * 如果存在就通过 findForPassport 查找用户，而不是使用默认的邮箱。
   * */
    public function findForPassport($username)
    {
        filter_var($username, FILTER_VALIDATE_EMAIL) ?
            $credentials['email'] = $username :
            $credentials['phone'] = $username;

        return self::where($credentials)->first();
    }
}
