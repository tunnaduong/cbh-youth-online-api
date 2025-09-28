<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Minh Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .header h1 {
            color: #319527;
            margin: 0;
            font-size: 24px;
        }

        .content {
            color: #333;
            line-height: 1.6;
        }

        .button {
            display: inline-block;
            background-color: #319527;
            color: white !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 4px;
            margin: 20px 0;
            font-weight: bold;
            text-align: center;
        }

        .button:hover {
            background-color: #267a1f;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 14px;
            color: #666;
        }

        .note {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f8f8;
            border-radius: 4px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Xác minh địa chỉ email</h1>
        </div>
        <div class="content">
            <p>Xin chào, {{ $account->username }}!</p>
            <p>Cảm ơn bạn đã đăng ký tài khoản trên CBH Youth Online. Vui lòng nhấp vào nút bên dưới để xác minh địa chỉ
                email của bạn:</p>
            <div style="text-align: center;">
                <a href="{{ $verificationUrl }}" class="button">Xác minh email của tôi</a>
            </div>
            <div class="note">
                <p>Nếu bạn không thể nhấp vào nút trên, hãy sao chép và dán liên kết sau vào trình duyệt:</p>
                <p style="word-break: break-all;">{{ $verificationUrl }}</p>
            </div>
            <p>Nếu bạn không tạo tài khoản này, bạn không cần thực hiện thêm bất kỳ hành động nào.</p>
        </div>
        <div class="footer">
            <p>Trân trọng,</p>
            <p>Đội ngũ CBH Youth Online</p>
        </div>
    </div>
</body>

</html>
