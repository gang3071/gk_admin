<template>
    <div class="container">
        <div v-if="!isCheckingAuth" class="lang-switch">
            <a-select v-model:value="currentLang" size="small" style="width: 120px" @change="handleLangChange">
                <a-select-option value="zh-CN">简体中文</a-select-option>
                <a-select-option value="zh-TW">繁體中文</a-select-option>
                <a-select-option value="en">English</a-select-option>
                <a-select-option value="jp">日本語</a-select-option>
            </a-select>
        </div>
        <div v-if="isCheckingAuth" class="checking-auth">
            <a-spin size="large" />
        </div>
        <div v-else class="login-layout">
            <div class="left">
                <div class="logo-container">
                    <img src="/exadmin/img/login_logo.png" class="logo" v-if="webLogo" />
                </div>
                <div class="left-container">
                    <img src="/exadmin/img/login-box-bg.9027741f.svg" class="ad">
                    <div class="text-block">
                        {{webName}}
                    </div>
                </div>
            </div>
            <div class="right">
                <div class="login-container">
                    <a-form ref="loginForm" :model="loginForm" :rules="loginRules" class="login-form">
                        <div class="title-container">
                            <h3 class="title">
                                <span>{{trans.title}}</span>
                            </h3>
                        </div>
                        <a-form-item name="username">
                            <a-input
                                size="large"
                                v-model:value="loginForm.username"
                                :placeholder="trans.username_placeholder"
                                tabindex="1"
                                auto-complete="on"
                            >
                                <template #prefix>
                                    <UserOutlined />
                                </template>
                            </a-input>
                        </a-form-item>
                        <a-form-item name="password">
                            <a-input-password
                                size="large"
                                v-model:value="loginForm.password"
                                :placeholder="trans.password_placeholder"
                                tabindex="2"
                                auto-complete="on"
                                @keyup.enter.native="handleLogin"
                            >
                                <template #prefix>
                                    <LockOutlined />
                                </template>
                            </a-input-password>
                        </a-form-item>
                        <a-form-item>
                            <a-checkbox v-model:checked="loginForm.remember_me">
                                {{trans.remember_me}}
                            </a-checkbox>
                        </a-form-item>
                        <div v-if="verification" style="display: flex;justify-content: space-between;">
                            <a-form-item name="verify" style="flex:1;margin-right: 10px">
                                <a-input
                                    size="large"
                                    v-model:value="loginForm.verify"
                                    :placeholder="trans.verify_placeholder"
                                    tabindex="3"
                                    auto-complete="on"
                                    maxlength="4"
                                    @keyup.enter.native="handleLogin"
                                >
                                    <template #prefix>
                                        <SafetyCertificateOutlined />
                                    </template>
                                </a-input>
                            </a-form-item>
                            <img :src="verifyImage" :height="40" class="verify" @click="getVerify"/>
                        </div>
                        <a-button :loading="loading" block size="large" type="primary" @click="handleLogin">{{trans.login_button}}</a-button>
                    </a-form>
                </div>
                <div class="icp"><a href="http://beian.miit.gov.cn" target="_blank">{{webMiitbeian}}</a> | {{webCopyright}}</div>
            </div>
        </div>
    </div>
</template>
<script>
export default {
    name: 'Agent',
    props:{
        webLogo: String,
        webName: String,
        webCopyright: String,
        webMiitbeian: String,
        deBug: Boolean,
        translations: {
            type: Object,
            default: () => ({
                'zh-CN': {
                    title: '代理登录',
                    username_placeholder: '请输入账号',
                    password_placeholder: '请输入密码',
                    verify_placeholder: '请输入验证码',
                    login_button: '登录',
                    username_required: '请输入账号',
                    password_required: '密码输入长度不能少于5位',
                    verify_required: '请输入验证码',
                    remember_me: '记住我（15天免登录）',
                },
                'zh-TW': {
                    title: '代理登入',
                    username_placeholder: '請輸入帳號',
                    password_placeholder: '請輸入密碼',
                    verify_placeholder: '請輸入驗證碼',
                    login_button: '登入',
                    username_required: '請輸入帳號',
                    password_required: '密碼輸入長度不能少於5位',
                    verify_required: '請輸入驗證碼',
                    remember_me: '記住我（15天免登入）',
                },
                'en': {
                    title: 'Agent Login',
                    username_placeholder: 'Please enter username',
                    password_placeholder: 'Please enter password',
                    verify_placeholder: 'Please enter verification code',
                    login_button: 'Login',
                    username_required: 'Please enter username',
                    password_required: 'Password must be at least 5 characters',
                    verify_required: 'Please enter verification code',
                    remember_me: 'Remember me (15 days)',
                },
                'jp': {
                    title: 'エージェントログイン',
                    username_placeholder: 'ユーザー名を入力してください',
                    password_placeholder: 'パスワードを入力してください',
                    verify_placeholder: '認証コードを入力してください',
                    login_button: 'ログイン',
                    username_required: 'ユーザー名を入力してください',
                    password_required: 'パスワードは5文字以上である必要があります',
                    verify_required: '認証コードを入力してください',
                    remember_me: 'ログイン状態を保存（15日間）',
                }
            })
        }
    },
    data() {
        return {
            currentLang: 'zh-TW',
            verification: false,
            isCheckingAuth: true,  // 初始为 true，显示 loading
            loginForm: {
              username: '',
              password: '',
              verify: '',
              hash: '',
              source: 'agent',
              remember_me: false,
            },
            loginRules: {},
            loading: false,
            verifyImage: '',
            redirect: null,
        }
    },
    computed: {
        trans() {
            return this.translations[this.currentLang] || this.translations['zh-CN'];
        }
    },
    watch: {
        $route: {
            handler: function(route) {
                if(route.query && route.query.redirect){
                    const index = route.fullPath.indexOf('?redirect=')
                    if(index > -1){
                        this.redirect = route.fullPath.substr(index+10)
                    }
                }
            },
            immediate: true
        },
        currentLang() {
            this.updateRules();
        }
    },
    created(){
        const source = 'agent';
        const autoLoginAttemptKey = `auto_login_attempt_${source}`;
        const rememberMeKey = `ex_admin_remember_me_${source}`;
        const tokenExpireKey = `ex_admin_token_expire_${source}`;

        const fullUrl = window.location.href;
        const hashUrl = window.location.hash;
        const searchUrl = window.location.search;

        console.log('[登录页 created] 完整 URL:', fullUrl);
        console.log('[登录页 created] hash:', hashUrl);
        console.log('[登录页 created] search:', searchUrl);

        const forceCleanup = sessionStorage.getItem('force_cleanup_' + source);
        console.log('[登录页 created] forceCleanup 标记:', forceCleanup);

        if (fullUrl.includes('redirect=') || hashUrl.includes('redirect=') || searchUrl.includes('redirect=') || forceCleanup === 'true') {
            console.log('[登录页 created] ⚠️ 检测到需要清理数据（redirect 或 force_cleanup）');
            this.clearRememberMeData(source);
            sessionStorage.removeItem(autoLoginAttemptKey);
            sessionStorage.removeItem('force_cleanup_' + source);

            console.log('[登录页 created] 数据已清理，显示登录表单');
            this.isCheckingAuth = false;
            this.updateRules();
            if(this.deBug){
              this.loginForm.username = '';
              this.loginForm.password = '';
            }
            this.getVerify();
            return;
        }

        const cookieToken = this.getCookie('ex_admin_token');
        const localToken = localStorage.getItem('ex_admin_token');
        const token = cookieToken || localToken;

        const rememberMe = localStorage.getItem(rememberMeKey) === 'true';
        const expireTime = parseInt(localStorage.getItem(tokenExpireKey));
        const now = Date.now();

        console.log('[登录页 created] token:', token ? '存在' : '不存在');
        console.log('[登录页 created] rememberMe:', rememberMe);
        console.log('[登录页 created] expireTime:', expireTime, '当前时间:', now);

        // 检测重定向循环：如果短时间内多次尝试自动登录，说明 token 在服务器端无效
        const lastAttempt = sessionStorage.getItem(autoLoginAttemptKey);

        console.log('[登录页 created] lastAttempt:', lastAttempt);

        if (lastAttempt) {
            const attemptTime = parseInt(lastAttempt);
            const timeSinceLastAttempt = now - attemptTime;

            console.log('[登录页 created] timeSinceLastAttempt:', timeSinceLastAttempt, 'ms');

            if (timeSinceLastAttempt < 5000 && timeSinceLastAttempt >= 0) {
                console.log('[登录页 created] ⚠️ 检测到重定向循环（5秒内重复访问），清理数据');
                this.clearRememberMeData(source);
                sessionStorage.removeItem(autoLoginAttemptKey);
                this.isCheckingAuth = false;
                this.updateRules();
                if(this.deBug){
                  this.loginForm.username = '';
                  this.loginForm.password = '';
                }
                this.getVerify();
                return;
            }
        }

        // 如果启用了记住我功能且token有效，跳转到首页
        if (rememberMe && expireTime && now < expireTime && token) {
            console.log('[登录页 created] ✅ 检测到有效 token，准备自动跳转');
            // 记录尝试时间，用于检测重定向循环
            sessionStorage.setItem(autoLoginAttemptKey, now.toString());

            // ✅ 保持 loading 状态，不显示登录表单
            // this.isCheckingAuth = false; // 注释掉，保持 loading

            this.$nextTick(() => {
                console.log('[登录页 created] 执行跳转到首页');
                this.$router.replace('/ex-admin/addons-webman-controller-ChannelIndexController/agentIndex');
            });
            return;
        }

        console.log('[登录页 created] ❌ 没有有效 token，显示登录表单');

        // 如果记住我功能已启用但token过期，清理数据
        if (rememberMe && (!expireTime || now >= expireTime)) {
            console.log('[登录页 created] 清理过期的"记住我"数据');
            this.clearRememberMeData(source);
        }

        // 显示登录页面
        this.isCheckingAuth = false;

        this.updateRules();
        if(this.deBug){
          this.loginForm.username = '';
          this.loginForm.password = '';
        }
        this.getVerify()
    },
    mounted() {
        // 检查是否有保存的语言设置
        const savedLang = localStorage.getItem('locale');
        const cookieLang = this.getCookie('ex_admin_lang');

        // 如果既没有localStorage也没有cookie，设置默认为繁体中文
        if (!savedLang && !cookieLang) {
            this.currentLang = 'zh-TW';
            localStorage.setItem('locale', 'zh-TW');
            this.setCookie('ex_admin_lang', 'zh-TW', 365);
        } else if (savedLang && this.translations[savedLang]) {
            this.currentLang = savedLang;
        } else if (cookieLang && this.translations[cookieLang]) {
            this.currentLang = cookieLang;
        }
    },
    methods: {
        clearRememberMeData(source) {
            // 清理所有记住我相关的数据
            const rememberMeKey = `ex_admin_remember_me_${source}`;
            const tokenExpireKey = `ex_admin_token_expire_${source}`;
            const sourceTokenKey = `/${source}_ex-admin-token`;

            localStorage.removeItem(rememberMeKey);
            localStorage.removeItem(tokenExpireKey);
            localStorage.removeItem(sourceTokenKey);
            localStorage.removeItem('ex_admin_token');

            // 清理Cookie中的token
            document.cookie = 'ex_admin_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';

            console.log('[clearRememberMeData] 已清理:', source, '的所有数据');
        },

        handleLangChange(value) {
            localStorage.setItem('locale', value);
        },

        updateRules() {
            const validatePassword = (rule, value, callback) => {
                if (value.length < 5) {
                    return Promise.reject(this.trans.password_required)
                } else {
                    return Promise.resolve()
                }
            };
            this.loginRules = {
                username: [{required: true, trigger: 'change', message: this.trans.username_required}],
                verify: [{required: true, message: this.trans.verify_required}],
                password: [{required: true, trigger: 'change', validator: validatePassword}]
            };
        },

        getVerify() {
            this.$request({
                url:'ex-admin/login/captcha'
            }).then(res => {
                this.verifyImage = res.data.image
                this.loginForm.hash = res.data.hash
                this.verification = res.data.verification
            })
        },

        handleLogin(data) {
            this.$refs.loginForm.validate().then(()=>{
                this.loading = true
                const loginData = {
                    ...this.loginForm,
                    locale: this.currentLang
                };
                this.$action.login(loginData).then(res => {
                    // 设置语言cookie
                    if (res.data && res.data.locale) {
                        this.setCookie('ex_admin_lang', res.data.locale, 365);
                    }

                    // 🎯 处理"记住我"功能 - 确保token在关闭浏览器后仍然有效
                    // 使用source区分不同后台，避免互相影响
                    const source = this.loginForm.source || 'agent';
                    const tokenExpireKey = `ex_admin_token_expire_${source}`;
                    const rememberMeKey = `ex_admin_remember_me_${source}`;

                    // 🔑 获取登录返回的Token
                    const token = res.data && res.data.token ? res.data.token : localStorage.getItem('ex_admin_token');

                    if (res.data && res.data.remember_me && this.loginForm.remember_me) {
                        const tokenExpireTime = Date.now() + (15 * 24 * 60 * 60 * 1000);
                        localStorage.setItem(tokenExpireKey, tokenExpireTime.toString());
                        localStorage.setItem(rememberMeKey, 'true');

                        // 🎯 关键修复：将Token保存到Cookie，设置15天过期
                        if (token) {
                            this.setCookie('ex_admin_token', token, 15);
                        }
                    } else {
                        localStorage.removeItem(tokenExpireKey);
                        localStorage.removeItem(rememberMeKey);
                        // 如果未勾选"记住我"，使用短期Cookie（会话级别）
                        if (token) {
                            this.setCookie('ex_admin_token', token, 1); // 1天
                        }
                    }

                    this.$router.push(this.redirect || '/ex-admin/addons-webman-controller-ChannelIndexController/agentIndex' )
                }).finally(() => {
                    this.loading = false
                }).catch(()=>{
                    this.getVerify()
                })
            })
        },

        setCookie(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
            document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
        },

        getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for(let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }
    }
}
</script>
<style scoped>

.lang-switch {
    position: absolute;
    top: 20px;
    right: 30px;
    z-index: 100;
}

.checking-auth {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: #FFFFFF;
    z-index: 1000;
}






/* 动画效果 */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}


@keyframes pulse {
    0%, 100% {
        opacity: 0.6;
    }
    50% {
        opacity: 1;
    }
}

.logo{

}

.login-layout .left{
    position:relative;
    width: 50%;
    height: 100%;
    margin-left: 150px;
}
.login-layout .left .ad{
    width: 45%;
}
.login-layout .right{
    position:relative;
    width: 50%;
    height: 100%;
}

.icp {
    position: absolute;
    bottom:10px;

    width: 100%;
    color: #000;
    opacity: .5;
    font-size: 12px;

}

.icp a {
    color: #000;
    text-decoration: none;
}
@keyframes bg-run {
    0% {
        background-position-x: 0;
    }

    to {
        background-position-x: -1920px;
    }
}
.container{
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 100%;
    overflow: hidden;
    background-color: #FFFFFF;
}
.container:before {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    margin-left: -48%;
    background-image: url("/exadmin/img/login-bg.b9f5c736.svg");
    background-position: 100%;
    background-repeat: no-repeat;
    background-size: auto 100%;
    content: "";
}
.text-block{
    margin-top: 30px;
    font-size: 32px;
    color:#FFFFFF;
}
.logo-container{
    font-size: 24px;
    color: #fff;
    font-weight: 700;
    position: relative;
    top: 50px;
    margin-left:20px;

}
.logo-container img{
    width: 100px;
    height: 100px;
}
.login-layout {
    height: 100%;
    display: flex;
    position: relative;
}
.left-container{
    position: absolute;
    top:calc(50% - 100px);
    left: 0;
    right: 0;
    bottom: 0;
}
.login-container {
    width: 400px;
    position: absolute;
    top:calc(50% - 250px);
    left:0;
    right: 0;
    bottom: 0;
}
.login-container .login-form {

}

.login-container .tips {
    font-size: 14px;
    color: #fff;
}

.login-container .svg-container {
    padding: 6px 5px 6px 15px;
    color: #889aa4;
    vertical-align: middle;
    display: inline-block;
}
.login-container .title-container .title {
    font-size: 26px;

    font-weight: bold;
}

.login-container .show-pwd {
    position: absolute;
    right: 10px;
    top: 7px;
    font-size: 16px;
    color: #889aa4;
    cursor: pointer;
    user-select: none;
}
.verify{
    height: 40px;
    cursor: pointer;
    border: 1px solid #ccc;
}

</style>
