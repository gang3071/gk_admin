<?php

declare(strict_types=1);

namespace app\service;

use Illuminate\Support\Facades\Log;

/**
 * 出票机串口通信服务类
 *
 * 直接通过 PHP 操作串口设备（/dev/ttyUSB0）
 *
 * 协议格式：
 * 帧头(0xFA 0xEA) + 命令类别(1字节) + 具体命令(1字节) + 数据长度(1字节) + 数据域(N字节) + 异或(1字节) + 和(1字节) + 帧尾(0xFB 0xEB)
 */
class TicketMachineService
{
    /** @var resource|null 串口文件句柄 */
    private $handle = null;

    /** @var string 串口路径 */
    private string $port;

    /** @var int 波特率 */
    private int $baudRate;

    // 帧常量
    private const FRAME_HEADER = [0xFA, 0xEA];
    private const FRAME_TAIL = [0xFB, 0xEB];

    // 命令类别
    public const CMD_TYPE_SYSTEM = 0x01;

    // 系统命令
    public const CMD_HEARTBEAT = 0x01;
    public const CMD_SET_DATETIME = 0x02;
    public const CMD_SET_UID = 0x03;
    public const CMD_SET_MACHINE_NO = 0x04;
    public const CMD_SET_STORE_NAME = 0x05;
    public const CMD_SET_SERIAL_NO = 0x06;
    public const CMD_LOTTERY_DATA = 0x07;
    public const CMD_QR_CODE = 0x08;

    public function __construct(?string $port = null, int $baudRate = 115200)
    {
        $this->port = $port ?: env('TICKET_DEFAULT_PORT', '/dev/ttyUSB0');
        $this->baudRate = $baudRate;
    }

    /**
     * 打开串口连接
     *
     * @return bool
     * @throws \RuntimeException
     */
    public function open(): bool
    {
        if ($this->handle !== null) {
            return true;
        }

        // 检查串口设备是否存在
        if (!file_exists($this->port)) {
            throw new \RuntimeException("串口设备不存在: {$this->port}");
        }

        // 打开串口
        $this->handle = fopen($this->port, 'r+b');
        if ($this->handle === false) {
            throw new \RuntimeException("无法打开串口: {$this->port}");
        }

        // 配置串口参数
        $this->configurePort();

        // 清空缓冲区
        $this->flush();

        Log::info("出票机串口已打开", ['port' => $this->port, 'baudRate' => $this->baudRate]);

        return true;
    }

    /**
     * 配置串口参数
     */
    private function configurePort(): void
    {
        // 使用 stty 命令配置串口
        $baudRateMap = [
            9600 => '9600',
            19200 => '19200',
            38400 => '38400',
            57600 => '57600',
            115200 => '115200',
        ];

        $baud = $baudRateMap[$this->baudRate] ?? '115200';

        // 配置串口：8数据位，1停止位，无校验，原始模式
        $cmd = sprintf(
            'stty -F %s %s cs8 -cstopb -parenb raw -echo -echoe -echok',
            escapeshellarg($this->port),
            $baud
        );

        exec($cmd . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            Log::warning("串口配置命令返回非零", ['output' => $output, 'code' => $returnCode]);
        }
    }

    /**
     * 关闭串口
     */
    public function close(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
            Log::info("出票机串口已关闭");
        }
    }

    /**
     * 清空缓冲区
     */
    public function flush(): void
    {
        if ($this->handle !== null) {
            // 清空输入输出缓冲区
            stream_set_blocking($this->handle, false);
            fread($this->handle, 4096);  // 读取并丢弃所有数据
            stream_set_blocking($this->handle, true);
        }
    }

    /**
     * 检查串口是否已打开
     */
    public function isOpen(): bool
    {
        return $this->handle !== null;
    }

    /**
     * 发送数据到串口
     *
     * @param string $data
     * @return int 写入的字节数
     * @throws \RuntimeException
     */
    private function write(string $data): int
    {
        if ($this->handle === null) {
            throw new \RuntimeException("串口未打开");
        }

        $bytesWritten = fwrite($this->handle, $data);
        if ($bytesWritten === false) {
            throw new \RuntimeException("写入串口失败");
        }

        // 确保数据发送出去
        fflush($this->handle);

        return $bytesWritten;
    }

    /**
     * 从串口读取数据
     *
     * @param int $length 读取长度
     * @param int $timeout 超时时间（秒）
     * @return string
     * @throws \RuntimeException
     */
    private function read(int $length = 256, int $timeout = 2): string
    {
        if ($this->handle === null) {
            throw new \RuntimeException("串口未打开");
        }

        // 设置超时
        $read = [$this->handle];
        $write = null;
        $except = null;
        $tvSec = $timeout;
        $tvUsec = 0;

        $result = stream_select($read, $write, $except, $tvSec, $tvUsec);
        if ($result === false) {
            throw new \RuntimeException("stream_select 失败");
        }

        if ($result === 0) {
            return '';  // 超时
        }

        $data = fread($this->handle, $length);
        if ($data === false) {
            throw new \RuntimeException("读取串口失败");
        }

        return $data;
    }

    /**
     * 构造命令帧
     *
     * @param int $cmdType 命令类别
     * @param int $cmd 具体命令
     * @param array $data 数据域
     * @return string 二进制数据
     */
    private function buildFrame(int $cmdType, int $cmd, array $data = []): string
    {
        $frame = [];

        // 帧头
        $frame[] = 0xFA;
        $frame[] = 0xEA;

        // 命令类别
        $frame[] = $cmdType;

        // 具体命令
        $frame[] = $cmd;

        // 数据长度
        $frame[] = count($data);

        // 数据域
        foreach ($data as $byte) {
            $frame[] = $byte & 0xFF;
        }

        // 计算异或校验 (从命令类别到数据域)
        $xor = $cmdType;
        $xor ^= $cmd;
        $xor ^= count($data);
        foreach ($data as $byte) {
            $xor ^= ($byte & 0xFF);
        }
        $frame[] = $xor;

        // 计算和校验 (从命令类别到异或)
        $sum = $cmdType + $cmd + count($data);
        foreach ($data as $byte) {
            $sum += ($byte & 0xFF);
        }
        $sum += $xor;
        $frame[] = $sum & 0xFF;

        // 帧尾
        $frame[] = 0xFB;
        $frame[] = 0xEB;

        // 转换为二进制字符串
        $binary = '';
        foreach ($frame as $byte) {
            $binary .= chr($byte);
        }

        return $binary;
    }

    /**
     * 解析响应帧
     *
     * @param string $data 原始数据
     * @return array|null 解析结果
     */
    private function parseFrame(string $data): ?array
    {
        // 帧结构: 帧头(2) + cmdType(1) + cmd(1) + dataLen(1) + 数据域(N) + XOR(1) + SUM(1) + 帧尾(2) = 9 + N
        if (strlen($data) < 9) {
            return null;
        }

        // 查找帧头
        $startPos = strpos($data, "\xFA\xEA");
        if ($startPos === false) {
            return null;
        }

        // 从帧头开始处理
        $data = substr($data, $startPos);

        if (strlen($data) < 9) {
            return null;
        }

        $dataLen = ord($data[4]);
        $frameLen = 9 + $dataLen;

        if (strlen($data) < $frameLen) {
            return null;
        }

        // 验证帧尾
        if ($data[$frameLen - 2] !== "\xFB" || $data[$frameLen - 1] !== "\xEB") {
            return null;
        }

        return [
            'cmdType' => ord($data[2]),
            'cmd'     => ord($data[3]),
            'dataLen' => $dataLen,
            'data'    => substr($data, 5, $dataLen),
            'raw'     => substr($data, 0, $frameLen),
        ];
    }

    /**
     * 发送命令并获取响应
     *
     * @param int $cmdType 命令类别
     * @param int $cmd 具体命令
     * @param array $data 数据域
     * @return array 响应结果
     */
    public function sendCommand(int $cmdType, int $cmd, array $data = []): array
    {
        try {
            // 打开串口
            $this->open();

            // 构造帧
            $frame = $this->buildFrame($cmdType, $cmd, $data);

            // 记录日志
            Log::debug("出票机发送", [
                'hex' => $this->toHex($frame),
                'cmdType' => sprintf('0x%02X', $cmdType),
                'cmd' => sprintf('0x%02X', $cmd),
            ]);

            // 发送数据
            $this->write($frame);

            // 等待响应
            usleep(100000);  // 100ms

            // 读取响应
            $response = $this->read(256, 2);

            if (empty($response)) {
                return [
                    'success' => false,
                    'error'   => '未收到响应',
                    'sent'    => $this->toHex($frame),
                ];
            }

            // 解析响应
            $parsed = $this->parseFrame($response);

            Log::debug("出票机接收", [
                'hex' => $this->toHex($response),
                'parsed' => $parsed,
            ]);

            if ($parsed === null) {
                return [
                    'success' => false,
                    'error'   => '响应格式错误',
                    'sent'    => $this->toHex($frame),
                    'received' => $this->toHex($response),
                ];
            }

            return [
                'success' => true,
                'sent'    => $this->toHex($frame),
                'response' => [
                    'cmdType' => sprintf('0x%02X', $parsed['cmdType']),
                    'cmd'     => sprintf('0x%02X', $parsed['cmd']),
                    'data'    => $this->toHex($parsed['data']),
                ],
            ];
        } catch (\Exception $e) {
            Log::error("出票机通信错误", ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    // ==================== 业务命令 ====================

    /**
     * 发送心跳
     */
    public function heartbeat(): array
    {
        return $this->sendCommand(self::CMD_TYPE_SYSTEM, self::CMD_HEARTBEAT);
    }

    /**
     * 设置日期时间
     *
     * @param string|null $datetime 格式: Y-m-d H:i:s
     */
    public function setDateTime(?string $datetime = null): array
    {
        $datetime = $datetime ?: date('Y-m-d H:i:s');
        $ts = strtotime($datetime);

        // 协议格式: 年(2字节高位在前) + 月 + 日 + 时 + 分 + 秒
        $year = (int) date('Y', $ts);
        $data = [
            ($year >> 8) & 0xFF,   // 年高位
            $year & 0xFF,          // 年低位
            (int) date('m', $ts),  // 月
            (int) date('d', $ts),  // 日
            (int) date('H', $ts),  // 时
            (int) date('i', $ts),  // 分
            (int) date('s', $ts),  // 秒
        ];

        return $this->sendCommand(self::CMD_TYPE_SYSTEM, self::CMD_SET_DATETIME, $data);
    }

    /**
     * 设置唯一ID
     *
     * @param string $uid 16个字符
     */
    public function setUid(string $uid): array
    {
        // 补齐或截断到16个字符
        $uid = str_pad(substr($uid, 0, 16), 16, '0', STR_PAD_LEFT);
        $data = array_map('ord', str_split($uid));

        return $this->sendCommand(self::CMD_TYPE_SYSTEM, self::CMD_SET_UID, $data);
    }

    /**
     * 设置机台号
     *
     * @param int $machineNo 机台号 (0-65535)
     */
    public function setMachineNo(int $machineNo): array
    {
        $machineNo = max(0, min(65535, $machineNo));

        // 高位在前
        $data = [
            ($machineNo >> 8) & 0xFF,
            $machineNo & 0xFF,
        ];

        return $this->sendCommand(self::CMD_TYPE_SYSTEM, self::CMD_SET_MACHINE_NO, $data);
    }

    /**
     * 设置店名称
     *
     * @param string $storeName 10个字符
     */
    public function setStoreName(string $storeName): array
    {
        // 截断到10个字节（注意：中文可能占用多个字节）
        $bytes = mb_convert_encoding($storeName, 'UTF-8');
        $byteArray = unpack('C*', $bytes);
        $byteArray = array_values($byteArray);

        // 确保正好10个字节
        if (count($byteArray) > 10) {
            $byteArray = array_slice($byteArray, 0, 10);
        } else {
            $byteArray = array_pad($byteArray, 10, 0x20);  // 用空格填充
        }

        return $this->sendCommand(self::CMD_TYPE_SYSTEM, self::CMD_SET_STORE_NAME, $byteArray);
    }

    /**
     * 设置打印序列号
     *
     * @param int $serialNo 序列号 (0-9999999)
     */
    public function setSerialNo(int $serialNo): array
    {
        $serialNo = max(0, min(9999999, $serialNo));

        // 高位在前，4字节
        $data = [
            ($serialNo >> 24) & 0xFF,
            ($serialNo >> 16) & 0xFF,
            ($serialNo >> 8) & 0xFF,
            $serialNo & 0xFF,
        ];

        return $this->sendCommand(self::CMD_TYPE_SYSTEM, self::CMD_SET_SERIAL_NO, $data);
    }

    /**
     * 发送彩票数据
     *
     * @param int $ticketCount 票数
     * @param int $giftCount 赠送数
     * @param int $codeTable 码表
     * @param int $number 数 (0-999999)
     */
    public function sendLotteryData(int $ticketCount, int $giftCount, int $codeTable, int $number): array
    {
        $number = max(0, min(999999, $number));

        // 协议格式: 票数(1字节) + 赠送(1字节) + 码表(1字节) + 数(4字节高位在前)
        $data = [
            $ticketCount & 0xFF,
            $giftCount & 0xFF,
            $codeTable & 0xFF,
            ($number >> 24) & 0xFF,
            ($number >> 16) & 0xFF,
            ($number >> 8) & 0xFF,
            $number & 0xFF,
        ];

        return $this->sendCommand(self::CMD_TYPE_SYSTEM, self::CMD_LOTTERY_DATA, $data);
    }

    /**
     * 发送QR码并打印小票
     *
     * @param string $qrCode QR码内容
     */
    public function sendQrCode(string $qrCode): array
    {
        $data = array_map('ord', str_split($qrCode));

        return $this->sendCommand(self::CMD_TYPE_SYSTEM, self::CMD_QR_CODE, $data);
    }

    /**
     * 发送HEX指令
     *
     * @param string $hex HEX字符串（空格分隔或连续）
     */
    public function sendHex(string $hex): array
    {
        $hex = str_replace(' ', '', $hex);

        // 验证HEX格式
        if (!preg_match('/^[0-9A-Fa-f]+$/', $hex) || strlen($hex) % 2 !== 0) {
            return [
                'success' => false,
                'error'   => '无效的HEX格式',
            ];
        }

        $binary = hex2bin($hex);

        try {
            $this->open();

            Log::debug("出票机发送HEX", ['hex' => $hex]);

            $this->write($binary);

            usleep(100000);

            $response = $this->read(256, 2);

            return [
                'success'  => true,
                'sent'     => $hex,
                'received' => $this->toHex($response),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * 初始化出票机（连接成功后调用）
     *
     * @param string $uid 唯一ID
     * @param int $machineNo 机台号
     * @param string $storeName 店名称
     */
    public function initMachine(string $uid, int $machineNo, string $storeName): array
    {
        $results = [];

        // 1. 同步日期时间
        $results['datetime'] = $this->setDateTime();
        usleep(50000);

        // 2. 设置唯一ID
        $results['uid'] = $this->setUid($uid);
        usleep(50000);

        // 3. 设置机台号
        $results['machine_no'] = $this->setMachineNo($machineNo);
        usleep(50000);

        // 4. 设置店名称
        $results['store_name'] = $this->setStoreName($storeName);

        return [
            'success' => true,
            'data'    => $results,
        ];
    }

    /**
     * 获取串口列表
     *
     * @return array
     */
    public static function getPortList(): array
    {
        $ports = [];

        // Linux: 查找 /dev/ttyUSB* 和 /dev/ttyACM*
        $patterns = ['/dev/ttyUSB*', '/dev/ttyACM*', '/dev/ttyS*'];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) as $port) {
                $ports[] = [
                    'port' => $port,
                    'name' => basename($port),
                ];
            }
        }

        return $ports;
    }

    /**
     * 获取当前串口状态
     */
    public function getPortInfo(): array
    {
        return [
            'port'     => $this->port,
            'baudRate' => $this->baudRate,
            'isOpen'   => $this->isOpen(),
        ];
    }

    /**
     * 二进制转HEX字符串
     */
    private function toHex(string $data): string
    {
        return implode(' ', array_map(function ($b) {
            return sprintf('%02X', ord($b));
        }, str_split($data)));
    }

    /**
     * 析构函数 - 关闭串口
     */
    public function __destruct()
    {
        $this->close();
    }
}
