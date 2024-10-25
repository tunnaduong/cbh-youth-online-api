<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác minh Email</title>
</head>

<body>
    <h1>Xin chào, {{ $user->username }}!</h1>
    <p>Cảm ơn bạn vì đã đăng ký. Vui lòng bấm vào link bên dưới để xác minh email của bạn:</p>
    <a href="{{ url('email/verify', $user->email_verification_token) }}">Xác minh địa chỉ Email</a>
    <p>Nếu bạn không tạo tài khoản này thì không cần thực hiện thêm hành động nào.</p>
    <p>Trân trọng,</p>
    <p>Đội ngũ CBH Youth Online</p>
</body>

</html>
