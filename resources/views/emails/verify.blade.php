<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Minh Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 600px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Xác minh địa chỉ email</h1>
        </div>
        <p>Xin chào, {{ $account->username }}!</p>
        <p>Cảm ơn bạn đã đăng ký tài khoản trên CBH Youth Online. Vui lòng nhấp vào liên kết bên dưới để xác minh địa
            chỉ email của bạn:</p>
        <a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a>
        <p>Nếu bạn không tạo tài khoản này, bạn không cần thực hiện thêm bất kỳ hành động nào.</p>
        <div class="footer">
            <p>Trân trọng,</p>
            <p>Đội ngũ CBH Youth Online</p>
        </div>
    </div>
</body>

</html>
