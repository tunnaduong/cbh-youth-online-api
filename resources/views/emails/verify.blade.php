<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Minh Email</title>
</head>

<body>
    <h1>Xin chào, {{ $account->username }}!</h1>
    <p>Cảm ơn bạn đã đăng ký tài khoản trên CBH Youth Online. Vui lòng nhấp vào liên kết bên dưới để xác minh địa chỉ
        email của bạn:</p>
    <a href="{{ url('email/verify', $verificationCode) }}">Xác Minh Địa Chỉ Email</a>
    <p>Nếu bạn không tạo tài khoản này, bạn không cần thực hiện thêm bất kỳ hành động nào.</p>
    <p>Trân trọng,</p>
    <p>Đội Ngũ CBH Youth Online</p>
</body>

</html>
