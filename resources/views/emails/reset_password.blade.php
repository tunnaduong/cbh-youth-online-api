<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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
            padding-top: 16px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }

        .footer a {
            color: #3869d4;
            padding: 0;
            margin: 0;
            background-color: transparent;
        }

        a {
            display: inline-block;
            margin: 10px 0;
            padding: 10px 20px;
            color: #fff;
            background-color: #007bff;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Thiết lập lại mật khẩu</h1>
        </div>
        <p>Xin chào,</p>
        <p>Bạn vừa yêu cầu thiết lập lại mật khẩu. Nhấn vào nút bên dưới để thiết lập lại mật khẩu của bạn:</p>
        <a href="{{ $url }}">Thiết lập lại mật khẩu</a>
        <p>Nếu bạn không yêu cầu thiết lập lại mật khẩu, hãy bỏ qua email này.</p>
        <div class="footer">
            <p>Trân trọng,<br>Đội ngũ CBH Youth Online</p>
            <p>Bạn nhận email này vì đã đăng ký nhận bản tin từ CBH Youth Online.<br>
                @if(!empty($unsubscribeUrl))
                <a href="{{ $unsubscribeUrl }}">Hủy nhận bản tin</a>
                @endif
                &nbsp;|&nbsp;
                <a href="{{ rtrim(env('APP_UI_URL', 'http://localhost:3000'), '/') . '/settings' }}">Cài đặt thông báo</a>
            </p>
        </div>
    </div>
</body>

</html>
