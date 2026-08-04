<x-mail::message :unsubscribe-url="$unsubscribeUrl">
# Thay đổi hình thức phỏng vấn

Thân gửi bạn **{{ $recipientName }},**

CYO xin gửi đến bạn một thông báo quan trọng về buổi phỏng vấn sắp tới:

**Buổi phỏng vấn sẽ được chuyển sang hình thức Online qua Google Meet** thay vì Discord như thông báo trước đó.

Link họp Google Meet đã được đính kèm trong sheet đăng ký lịch phỏng vấn của bạn tại: [{{ $scheduleLink }}]({{ $scheduleLink }})

Các thông tin khác vẫn giữ nguyên:

- **Thời gian:** {{ $date }}

Chúng mình xin lỗi vì sự thay đổi này và mong bạn thông cảm. Hãy chuẩn bị thiết bị có kết nối mạng ổn định để buổi phỏng vấn diễn ra suôn sẻ nhé.

Mọi thắc mắc, vui lòng liên hệ:

- Fanpage: [https://www.facebook.com/cbhyouthonline](https://www.facebook.com/cbhyouthonline)
- Mail: hotro@chuyenbienhoa.com

Trân trọng,

CYO - Diễn đàn học sinh Chuyên Biên Hòa.

<x-mail::button :url="$scheduleLink">
Xem lịch & link họp
</x-mail::button>
</x-mail::message>
