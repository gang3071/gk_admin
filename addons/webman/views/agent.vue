<template>
    <div class="container">
        <div class="lang-switch">
            <a-select v-model:value="currentLang" size="small" style="width: 120px" @change="handleLangChange">
                <a-select-option value="zh-CN">简体中文</a-select-option>
                <a-select-option value="zh-TW">繁體中文</a-select-option>
                <a-select-option value="en">English</a-select-option>
                <a-select-option value="jp">日本語</a-select-option>
            </a-select>
        </div>
        <div class="login-layout">
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
                    verify_required: '请输入验证码'
                },
                'zh-TW': {
                    title: '代理登入',
                    username_placeholder: '請輸入帳號',
                    password_placeholder: '請輸入密碼',
                    verify_placeholder: '請輸入驗證碼',
                    login_button: '登入',
                    username_required: '請輸入帳號',
                    password_required: '密碼輸入長度不能少於5位',
                    verify_required: '請輸入驗證碼'
                },
                'en': {
                    title: 'Agent Login',
                    username_placeholder: 'Please enter username',
                    password_placeholder: 'Please enter password',
                    verify_placeholder: 'Please enter verification code',
                    login_button: 'Login',
                    username_required: 'Please enter username',
                    password_required: 'Password must be at least 5 characters',
                    verify_required: 'Please enter verification code'
                },
                'jp': {
                    title: 'エージェントログイン',
                    username_placeholder: 'ユーザー名を入力してください',
                    password_placeholder: 'パスワードを入力してください',
                    verify_placeholder: '認証コードを入力してください',
                    login_button: 'ログイン',
                    username_required: 'ユーザー名を入力してください',
                    password_required: 'パスワードは5文字以上である必要があります',
                    verify_required: '認証コードを入力してください'
                }
            })
        }
    },
    data() {
        return {
            currentLang: 'zh-CN',
            verification: false,
            loginForm: {
              username: '',
              password: '',
              verify: '',
              hash: '',
              source: 'agent',
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
        this.updateRules();
        if(this.deBug){
          this.loginForm.username = '';
          this.loginForm.password = '';
        }
        this.getVerify()
    },
    mounted() {
        const savedLang = localStorage.getItem('locale');
        if (savedLang && this.translations[savedLang]) {
            this.currentLang = savedLang;
        }
    },
    methods: {
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
                    this.$router.push(this.redirect || '/' )
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
