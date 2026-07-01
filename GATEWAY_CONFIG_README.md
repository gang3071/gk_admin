# GatewayWorker 内网连接配置说明

## 问题描述

当管理后台需要连接内网的 GatewayWorker 服务时，会出现以下错误：

```
stream_socket_client(): Unable to connect to tcp://127.0.0.1:1236 (Connection refused)
```

## 解决方案

已添加 GatewayWorker 客户端配置，支持通过环境变量配置内网 Register 服务地址。

### 配置方法

在项目根目录的 `.env` 文件中添加：

```bash
# GatewayWorker Register 服务地址（单个地址）
GATEWAY_REGISTER_ADDRESS=192.168.1.100:1236
```

或配置多个地址（高可用）：

```bash
# 多个地址用逗号分隔
GATEWAY_REGISTER_ADDRESS=192.168.1.100:1236,192.168.1.101:1236
```

### 配置文件说明

- **文件**: `support/bootstrap/GatewayClient.php`
- **加载**: 已在 `config/bootstrap.php` 中自动加载
- **默认值**: `127.0.0.1:1236`（本地）

### 重启服务

修改 `.env` 后需要重启 Webman 服务：

```bash
# Linux
php start.php restart

# Windows (停止后重新启动)
php windows.php
```

## 内网 GatewayWorker 服务器配置

内网服务器需要运行以下服务：

### 1. Register 服务

监听地址：`0.0.0.0:1236` （允许外部连接）

```php
// config/gateway_worker.php
'register' => [
    'listen' => 'text://0.0.0.0:1236',
],
```

### 2. Gateway 服务

需要注册到 Register：

```php
'gateway' => [
    'registerAddress' => '127.0.0.1:1236',  // 本机 Register
    'lanIp' => '192.168.1.100',  // 内网IP
],
```

### 3. BusinessWorker 服务

处理业务逻辑：

```php
'business_worker' => [
    'registerAddress' => '127.0.0.1:1236',
],
```

## 防火墙设置

确保内网服务器的防火墙允许 1236 端口访问：

```bash
# CentOS/RHEL
firewall-cmd --permanent --add-port=1236/tcp
firewall-cmd --reload

# Ubuntu/Debian
ufw allow 1236/tcp
```

## 测试连接

在管理后台服务器测试连接：

```bash
# 测试 Register 服务是否可达
telnet 192.168.1.100 1236

# 或使用 nc
nc -zv 192.168.1.100 1236
```

## 常见问题

### 1. Connection refused
- 检查内网服务器的 GatewayWorker 是否启动
- 检查 Register 服务监听地址是否为 `0.0.0.0:1236`

### 2. Connection timeout
- 检查网络连通性
- 检查防火墙规则
- 检查安全组设置

### 3. 设备状态显示离线
- 确认 Gateway 服务正常运行
- 检查设备连接的 Gateway 地址配置

## 架构说明

```
管理后台服务器                内网 GatewayWorker 服务器
┌─────────────────┐          ┌──────────────────────┐
│  Webman App     │          │   Register (:1236)   │
│                 │  ───────>│                      │
│  Gateway Client │          │   Gateway (:8282)    │
│                 │  <───────│                      │
│                 │          │   BusinessWorker     │
└─────────────────┘          └──────────────────────┘
       │                              │
       │                              │
       └──────── isUidOnline() ───────┘
```

## 相关文件

- `support/bootstrap/GatewayClient.php` - Gateway 客户端配置
- `config/bootstrap.php` - Bootstrap 加载配置
- `addons/webman/controller/MachineController.php` - 使用 Gateway 检测在线状态
