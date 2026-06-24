<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ admin_trans('ticket_machine.redeem.title') }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            margin: 0 0 24px 0;
            font-size: 20px;
            color: #1890ff;
        }
        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d9d9d9;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        input[type="text"]:focus {
            border-color: #1890ff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(24,144,255,0.2);
        }
        .detail-section {
            display: none;
            margin-top: 24px;
            padding: 16px;
            background: #fafafa;
            border-radius: 4px;
        }
        .detail-section.show {
            display: block;
        }
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-item:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #666;
        }
        .detail-value {
            font-weight: 500;
            color: #333;
        }
        .btn {
            display: inline-block;
            padding: 10px 24px;
            margin-top: 16px;
            background: #1890ff;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        .btn:hover {
            background: #40a9ff;
        }
        .btn:disabled {
            background: #d9d9d9;
            cursor: not-allowed;
        }
        .message {
            padding: 10px;
            margin-top: 16px;
            border-radius: 4px;
            display: none;
        }
        .message.success {
            background: #f6ffed;
            border: 1px solid #b7eb8f;
            color: #52c41a;
            display: block;
        }
        .message.error {
            background: #fff2f0;
            border: 1px solid #ffccc7;
            color: #ff4d4f;
            display: block;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #999;
        }
        .loading.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ admin_trans('ticket_machine.redeem.title') }}</h1>

        <div class="form-group">
            <label>{{ admin_trans('ticket_machine.redeem.input_qr_code') }}</label>
            <input type="text" id="qr_code_no" placeholder="{{ admin_trans('ticket_machine.redeem.scan_qr_code_placeholder') }}" autofocus>
        </div>

        <div class="loading" id="loading">查询中...</div>

        <div class="detail-section" id="detail">
            <div class="detail-item">
                <span class="detail-label">{{ admin_trans('ticket_machine.redeem.order_id') }}</span>
                <span class="detail-value" id="order_id">-</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">{{ admin_trans('ticket_machine.redeem.store_name') }}</span>
                <span class="detail-value" id="store_name">-</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">{{ admin_trans('ticket_machine.redeem.machine_no') }}</span>
                <span class="detail-value" id="machine_no">-</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">{{ admin_trans('ticket_machine.redeem.score') }}</span>
                <span class="detail-value" id="score">-</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">{{ admin_trans('ticket_machine.redeem.status') }}</span>
                <span class="detail-value" id="status">-</span>
            </div>
        </div>

        <button class="btn" id="redeemBtn" disabled>{{ admin_trans('ticket_machine.redeem.redeem_confirm') }}</button>

        <div class="message" id="message"></div>
    </div>

    <script>
        var currentRecordId = null;
        var timer = null;
        var queryUrl = '{{ $queryUrl }}';
        var redeemUrl = '{{ $redeemUrl }}';

        var qrInput = document.getElementById('qr_code_no');
        var detailSection = document.getElementById('detail');
        var loading = document.getElementById('loading');
        var redeemBtn = document.getElementById('redeemBtn');
        var message = document.getElementById('message');

        // 监听输入事件
        qrInput.addEventListener('input', function() {
            if (timer) {
                clearTimeout(timer);
            }

            timer = setTimeout(function() {
                if (qrInput.value && qrInput.value.length >= 10) {
                    queryRecord(qrInput.value);
                }
            }, 300);
        });

        // 监听回车事件
        qrInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter' && qrInput.value) {
                queryRecord(qrInput.value);
            }
        });

        function queryRecord(qrCodeNo) {
            loading.classList.add('show');
            detailSection.classList.remove('show');
            redeemBtn.disabled = true;
            message.className = 'message';

            fetch(queryUrl + '?qr_code_no=' + encodeURIComponent(qrCodeNo))
                .then(response => response.json())
                .then(data => {
                    loading.classList.remove('show');

                    if (data.code === 0) {
                        currentRecordId = data.data.id;
                        document.getElementById('order_id').textContent = data.data.order_id || '-';
                        document.getElementById('store_name').textContent = data.data.store_name || '-';
                        document.getElementById('machine_no').textContent = data.data.machine_no || '-';
                        document.getElementById('score').textContent = data.data.score || '-';
                        document.getElementById('status').textContent = data.data.status_name || '-';
                        detailSection.classList.add('show');
                        redeemBtn.disabled = false;
                    } else {
                        message.textContent = data.msg;
                        message.className = 'message error';
                    }
                })
                .catch(err => {
                    loading.classList.remove('show');
                    message.textContent = '查询失败';
                    message.className = 'message error';
                });
        }

        redeemBtn.addEventListener('click', function() {
            if (!currentRecordId) {
                return;
            }

            redeemBtn.disabled = true;
            redeemBtn.textContent = '核销中...';

            fetch(redeemUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: currentRecordId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.code === 0) {
                    message.textContent = data.msg || '核销成功';
                    message.className = 'message success';

                    // 重置
                    setTimeout(function() {
                        qrInput.value = '';
                        detailSection.classList.remove('show');
                        redeemBtn.disabled = true;
                        redeemBtn.textContent = '{{ admin_trans("ticket_machine.redeem.redeem_confirm") }}';
                        currentRecordId = null;
                        qrInput.focus();
                    }, 2000);
                } else {
                    message.textContent = data.msg;
                    message.className = 'message error';
                    redeemBtn.disabled = false;
                    redeemBtn.textContent = '{{ admin_trans("ticket_machine.redeem.redeem_confirm") }}';
                }
            })
            .catch(err => {
                message.textContent = '核销失败';
                message.className = 'message error';
                redeemBtn.disabled = false;
                redeemBtn.textContent = '{{ admin_trans("ticket_machine.redeem.redeem_confirm") }}';
            });
        });

        // 页面加载后自动聚焦输入框
        qrInput.focus();
    </script>
</body>
</html>
