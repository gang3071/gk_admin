# ============================
# 应用配置（正式环境）
# ============================

APP_DEBUG=false
APP_ENV=pro
APP_URL=https://api.jinzun.org
APP_KEY=base64:RXmA9erMCe+s3qc4Dmyffy6vumqjEolf083K3pTNkkY=
API_DOMAIN=https://api.jinzun.org/
CURRENCY=TWD

# 策略 URL
STRATEGY_URL=http://8.218.226.64:777/#/pages/detail/index?id=

# ============================
# 数据库配置（正式环境）
# ============================

DB_CONNECTION=mysql
DB_HOST=10.59.177.7
DB_PORT=3306
DB_DATABASE=super9
DB_USERNAME=jin
DB_PASSWORD="^%q0[%Y&2_-yedt>"

# 读写分离配置
DB_READ_HOST=10.59.177.7
DB_READ_USERNAME=jin
DB_READ_PASSWORD="^%q0[%Y&2_-yedt>"

DB_WRITE_HOST=10.59.177.7
DB_WRITE_USERNAME=jin
DB_WRITE_PASSWORD="^%q0[%Y&2_-yedt>"
# Register 服务地址（内网地址）
# 单个地址格式：IP:端口
GATEWAY_REGISTER_ADDRESS=10.140.0.13:1236
# ============================
# Redis 配置（正式环境）
# ============================

REDIS_HOST=10.140.0.13
REDIS_PORT=6379
REDIS_PASSWORD=gang3071
REDIS_DB=0

# ============================
# MongoDB 配置（正式环境）
# ============================

MONGODB_HOST=10.140.0.13
MONGODB_PORT=27017
MONGODB_DATABASE=luck3
MONGODB_USERNAME=
MONGODB_PASSWORD=
MONGODB_AUTH_DATABASE=admin

# ============================
# WebSocket Push 服务配置
# ============================

# WebSocket 连接地址（前端使用）
WS_URL=wss://api.jinzun.org

# Push API 地址（后端发送消息使用）
# 使用内网 IP 通信，速度更快且安全
PUSH_API_URL=http://10.140.0.14:3232

# Push 应用密钥（需要与 API 服务器 Push 配置一致）
PUSH_APP_KEY=20f94408fc4c52845f162e92a253c7a3
PUSH_APP_SECRET=3151f8648a6ccd9d4515386f34127e28

# ============================
# Google Cloud Storage 配置
# ============================

GOOGLE_CLOUD_KEY_FILE=comechat-d5578-b1ad675314bd.json
GOOGLE_CLOUD_PROJECT_ID=comechat-d5578
GOOGLE_CLOUD_STORAGE_BUCKET=yjbfile
GOOGLE_CLOUD_STORAGE_PREFIX=jin

# ============================
# IP 白名单配置
# ============================

IP_WHITELIST_ENABLE=false

# ============================
# 游戏平台 API 代理配置
# ============================

# API 服务器地址（gk_work 项目）
# 本地开发：127.0.0.1:8788
# 正式环境：10.140.0.10:8788
# 生产环境：根据实际部署调整
GAME_PLATFORM_PROXY_HOST=10.140.0.13
GAME_PLATFORM_PROXY_PORT=8080

# Telegram Bot Token（从 @BotFather 获取）
# 1. 在 Telegram 中搜索 @BotFather
# 2. 发送 /newbot 创建新机器人
# 3. 按提示设置机器人名称，获取 Token
TELEGRAM_BOT_TOKEN=8771206823:AAG0RTJjudsO-XrC9geY19hSrIXmR-Nm2qs

# Telegram Chat ID（接收告警的群组或频道 ID）
# 获取方式：
# 1. 将机器人添加到群组
# 2. 访问 https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates
# 3. 在返回的 JSON 中找到 chat.id
TELEGRAM_CHAT_ID=-5252216672

# gk_work 机台操作 API 地址（用于后台机台操作）
GK_WORK_API_URL=http://10.140.0.13:8080

#二维码加解密密钥
APP_KEY=base64:RXmA9erMCe+s3qc4Dmyffy6vumqjEolf083K3pTNkkY=

# ============================
# 游戏平台配置（正式环境）
# ============================

# ------------------------
# RSG 电子游戏平台（正式）
# ------------------------
RSG_API_DOMAIN=http://jinzun-api.rsgaming888.com/SingleWallet
RSG_APP_ID=b8xvzh5ctthd
RSG_APP_SECRET=61WF9MCQ
RSG_SYSTEMCODE=Jinzun
RSG_WEBID=Jinzun
RSG_DESKEY=PC2QIWOY
RSG_DESIV=IMYC1K0X

# ------------------------
# DG 真人平台（正式）
# ------------------------
DG_API_DOMAIN=https://api.dg99api.com
DG_APP_ID=DG00BS1201
DG_APP_SECRET=17a9c53c134e496983e2e6eb8d8e558b
DG_ADMIN_URL=https://oo2123.com
DG_ADMIN_USER=DG00BS1201
DG_ADMIN_PASSWORD=f1JJ1x
DG_AGENT_FIX=28957
DG_AGENT_SUFFIX=4LN

# ------------------------
# QT 平台（正式）
# ------------------------
QT_API_DOMAIN=https://api.qtplatform.com
QT_PASSKEY=nf7G1aTaypjtabwM
QT_USERNAME=api_jinzun
QT_PASSWORD=taysFst7

# ------------------------
# BTG 平台（正式）
# ------------------------
BTG_API_DOMAIN=https://game.stgkg.btgame777.com/v2_2
BTG_APP_ID=657534466404751
BTG_MD5_KEY=8541f57ce59109c1419f91a6c3914da1669782de
BTG_APP_SECRET=5b0dbf3542bbbf5c9e70c2515e678f98
BTG_ADMIN_URL=https://agent.stgkg.btgame777.com/
BTG_ADMIN_USER=jinzun
BTG_ADMIN_PASSWORD=88888888

# ------------------------
# Gclub（正式）
# ------------------------
RSG_LIVE_API_DOMAIN=http://rcgapiv2.rcg666.com
RSG_LIVE_CLIENT_ID=0a79753b-f7fe-42a6-83c5-b565751cf34c
RSG_LIVE_CLIENT_SECRET=fcc682cc
RSG_LIVE_SYSTEMCODE=jinzuntest
RSG_LIVE_WEBID=jinzuntest
RSG_LIVE_DESKEY=36814c78
RSG_LIVE_DESIV=43722cb2

# ------------------------
# T9 棋牌平台（正式）
# ------------------------
TNINE_API_DOMAIN=https://tpgameapi.t9cn818.online
TNINE_AGENT_ID=30149
TNINE_API_KEY=XGMliBM8gQzg1YsCNbsjNpMT00ymXUPE

# ------------------------
# T9 电子游戏平台（正式）
# ------------------------
TNINE_SLOT_API_DOMAIN=https://seamless.t9hubtech.com
TNINE_SLOT_AGENT_ID=super9001
TNINE_SLOT_API_KEY=f2a8c5d0e3b7a1c9f6d0e8b4a2c5f1d7e3b9a0c6f4d2e8b1a5c7f0d3b6e9a2c1

# ------------------------
# KT 平台（正式)
# ------------------------
KT_API_DOMAIN=https://tx.api.ktgames.cc/api/v1
KT_PLATFORM=jinzun
KT_HASH_KEY=CUTccvpFz42nQx
KT_AGENT=jinpro_TWD

# ============================
# O8平台（正式)未更新
# ============================
O8_API_DOMAIN=http://ugsapi.ugsdev.com
O8_CLIENT_ID=jinzunSTG
O8_CLIENT_SECRET=DP6vClX79bNelxjGG2cPFlMNoFMArqTXvGsEZCF208ci

# ------------------------
# MT 电子游戏平台（正式）未更新
# ------------------------
MT_API_DOMAIN=https://zone10.ofa16899.net/api/sapphire/
MT_SYSTEM_CODE=Jinzuntest
MT_WEB_ID=jinzunt
MT_CLIENT_ID=sxbebRu2wc
MT_CLIENT_SECRET=Yws0XdQ0Bm
MT_DES_KEY=3ryrVUC7
MT_DesIV=kJZp0rLg

# ============================
# SA平台（正式）未更新
# ============================

SA_API_DOMAIN=https://sai-api.sa-apisvr.com/api/api.aspx
SA_SECRET=75BE8576F1B740768D1ACAF3C8445314
SA_DES_KEY=g9G16nTs
SA_MD5_KEY=GgaIMaiNNtg

# ============================
# SP平台（正式）未更新
# ============================

SP_API_DOMAIN=https://api.sp-portal.com/api/api.aspx
SP_SECRET=EE99B571F3484A1E8644DF404FD6E687
SP_DES_KEY=g9G16nTs
SP_MD5_KEY=GgaIMaiNNtg

# ------------------------
# ATG 平台（正式）未更新
# ------------------------
ATG_API_DOMAIN=https://api.godeebxp.com
ATG_OPERATOR=jinzun
ATG_PROVIDERID=4
ATG_KEY=59eceb441f2b41f18035b7065e59920b
