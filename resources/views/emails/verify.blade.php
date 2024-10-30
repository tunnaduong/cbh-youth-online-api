<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Minh Email</title>
</head>

<body>
    <h2>Xin chào, {{ $account->username }}!</h2>
    <p>Cảm ơn bạn đã đăng ký tài khoản trên CBH Youth Online. Vui lòng nhấp vào liên kết bên dưới để xác minh địa chỉ
        email của bạn:</p>
    <a href="{{ env('APP_UI_URL', 'http://localhost:3000') . '/email/verify/' . $verificationCode }}">Xác minh địa chỉ
        email</a>
    <p>Nếu bạn không tạo tài khoản này, bạn không cần thực hiện thêm bất kỳ hành động nào.</p>
    <p>Trân trọng,</p>
    <p>Đội ngũ CBH Youth Online</p>
</body>

</html>
